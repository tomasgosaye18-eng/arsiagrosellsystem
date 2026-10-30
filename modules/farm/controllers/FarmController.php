<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 9A
 * =========================================================
 * File    : modules/farm/controllers/FarmController.php
 * Purpose : Farm listing and farm detail pages.
 *
 * Routes  :
 *   GET /farms       → index()  — All Farms list
 *   GET /farms/{id}  → show()   — Farm Details page
 *
 * Access  :
 *   ROLE_FARM_MANAGER — sees only assigned farm(s)
 *   ROLE_ADMIN / ROLE_GENERAL_MANAGER — sees all farms
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Farm\Controllers;

use AAM\Core\BaseController;
use AAM\Core\SessionManager;
use AAM\Farm\Models\FarmModel;
use AAM\Farm\Models\HarvestBatchModel;
use AAM\Farm\Models\FarmDispatchModel;

class FarmController extends BaseController
{
    private FarmModel         $farmModel;
    private HarvestBatchModel $harvestModel;
    private FarmDispatchModel $dispatchModel;

    public function __construct()
    {
        parent::__construct();
        $this->farmModel     = new FarmModel();
        $this->harvestModel  = new HarvestBatchModel();
        $this->dispatchModel = new FarmDispatchModel();
    }

    // ──────────────────────────────────────────────────────
    // GET /farms  — All Farms
    // ──────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_FARM_MANAGER, ROLE_GENERAL_MANAGER);

        $role   = $this->userRole();
        $userId = (int)$this->userId();

        // Farm Manager must NOT see the All Farms list.
        // Redirect them directly to their assigned farm.
        if ($role === ROLE_FARM_MANAGER) {
            $farmId = (int)\AAM\Core\SessionManager::get(SESSION_LOCATION_ID, 0);
            $ltype  = \AAM\Core\SessionManager::get(SESSION_LOCATION_TYPE, '');

            if ($ltype !== 'farm' || $farmId <= 0) {
                // Try DB fallback
                $db  = \AAM\Core\Database::getInstance();
                $row = $db->fetchOne(
                    "SELECT location_id FROM user_location_assignments
                     WHERE user_id=:uid AND location_type='farm'
                     ORDER BY assigned_at DESC LIMIT 1",
                    ['uid' => $userId]
                );
                $farmId = $row ? (int)$row['location_id'] : 0;
            }

            if ($farmId > 0) {
                $this->redirect("/farms/{$farmId}");
                return;
            }

            // No farm assigned — show friendly error
            $this->flash(FLASH_ERROR, 'You are not assigned to any farm. Contact your administrator.');
            $this->redirect('/dashboard');
            return;
        }

        $filters = [
            'search'    => $this->query('search', ''),
            'farm_type' => $this->query('farm_type', ''),
            'status'    => $this->query('status', ''),
        ];

        // Farm manager scoping: only see assigned farm
        $farms = $this->getFarmsForRole($role, $userId, $filters);

        // Stats for admin/GM
        $stats = null;
        if (in_array($role, [ROLE_GENERAL_MANAGER], true)) {
            $stats = $this->computeStats($farms);
        }

        $farmTypes = $this->farmModel->getDistinctTypes();

        $this->render('farm.farms.index', [
            'pageTitle' => 'All Farms',
            'farms'     => $farms,
            'filters'   => $filters,
            'farmTypes' => $farmTypes,
            'stats'     => $stats,
            'userRole'  => $role,
        ]);
    }

    // ──────────────────────────────────────────────────────
    // GET /farms/{id}  — Farm Details
    // ──────────────────────────────────────────────────────

    public function show(string $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_FARM_MANAGER, ROLE_GENERAL_MANAGER);

        $farmId = (int)$id;
        $role   = $this->userRole();
        $userId = (int)$this->userId();

        $farm = $this->farmModel->findAssigned($farmId, $userId, $role);

        if (!$farm) {
            $this->flash(FLASH_ERROR, 'Farm not found or you do not have permission to view it.');
            $this->redirect('/farms');
        }

        $stats   = $this->farmModel->getSummaryStats($farmId);
        $tab     = $this->query('tab', 'overview');

        // Overview tab data
        $recentHarvests   = $this->harvestModel->paginateFarm($farmId, 1, 10)['data'];
        $recentDispatches = $this->dispatchModel->paginateFarm($farmId, 1, 10)['data'];

        $this->render('farm.farms.show', [
            'pageTitle'        => $farm['name'],
            'farm'             => $farm,
            'stats'            => $stats,
            'tab'              => $tab,
            'recentHarvests'   => $recentHarvests,
            'recentDispatches' => $recentDispatches,
            'userRole'         => $role,
        ]);
    }

    // ──────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────

    private function getFarmsForRole(string $role, int $userId, array $filters): array
    {
        if (in_array($role, [ROLE_GENERAL_MANAGER], true)) {
            return $this->farmModel->getAllWithSummary($filters);
        }

        // Farm manager: resolve assigned farm from session / DB
        $farmId = $this->resolveAssignedFarmId($userId);

        if ($farmId <= 0) {
            return [];
        }

        // Get all farms with summary, then filter to assigned farm only
        $all = $this->farmModel->getAllWithSummary($filters);
        return array_values(array_filter($all, fn($f) => (int)$f['id'] === $farmId));
    }

    private function resolveAssignedFarmId(int $userId): int
    {
        $sessionType = SessionManager::get(SESSION_LOCATION_TYPE);
        $sessionId   = (int)SessionManager::get(SESSION_LOCATION_ID, 0);

        if ($sessionType === 'farm' && $sessionId > 0) {
            return $sessionId;
        }

        // Try user_location_assignments
        $db = $this->farmModel->getDb();
        try {
            $row = $db->fetchOne(
                "SELECT location_id FROM user_location_assignments
                 WHERE user_id = :uid AND location_type = 'farm' LIMIT 1",
                ['uid' => $userId]
            );
            if ($row) {
                $id = (int)$row['location_id'];
                SessionManager::set(SESSION_LOCATION_TYPE, 'farm');
                SessionManager::set(SESSION_LOCATION_ID, $id);
                return $id;
            }
        } catch (\Throwable) {}

        return 0;
    }

    private function computeStats(array $farms): array
    {
        $total    = count($farms);
        $active   = count(array_filter($farms, fn($f) => (int)$f['is_active'] === 1));
        $pending  = array_sum(array_column($farms, 'pending_dispatches'));
        $batches  = array_sum(array_column($farms, 'active_batches'));

        return compact('total', 'active', 'pending', 'batches');
    }
}
