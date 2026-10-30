<?php
/**
 * =========================================================
 * ASSIGNMENT CONTROLLER
 * =========================================================
 * File    : modules/organization/controllers/AssignmentController.php
 * Purpose : Manages staff assignments to farms, warehouses,
 *           and shops. Supports create, reassign, remove,
 *           history viewing, and assignment summaries.
 * Deps    : BaseController, AssignmentModel, FarmModel,
 *           WarehouseModel, ShopModel, AssignmentService,
 *           ActivityLogModel
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Organization\Controllers;

use AAM\Core\BaseController;
use AAM\Organization\Models\AssignmentModel;
use AAM\Organization\Models\FarmModel;
use AAM\Organization\Models\WarehouseModel;
use AAM\Organization\Models\ShopModel;
use AAM\Organization\Services\AssignmentService;
use AAM\Admin\Models\ActivityLogModel;

class AssignmentController extends BaseController
{
    private AssignmentModel  $assignmentModel;
    private FarmModel        $farmModel;
    private WarehouseModel   $warehouseModel;
    private ShopModel        $shopModel;
    private AssignmentService $assignmentService;
    private ActivityLogModel $activityLog;

    public function __construct()
    {
        parent::__construct();
        $this->assignmentModel   = new AssignmentModel();
        $this->farmModel         = new FarmModel();
        $this->warehouseModel    = new WarehouseModel();
        $this->shopModel         = new ShopModel();
        $this->assignmentService = new AssignmentService();
        $this->activityLog       = new ActivityLogModel();
    }

    // ── Assignment list ───────────────────────────────────────────────────────

    public function index(): void
    {
        $filters = [
            'location_type'   => $this->query('location_type', ''),
            'assignment_role' => $this->query('assignment_role', ''),
            'is_active'       => $this->query('is_active', '1'),
            'user_id'         => $this->query('user_id', ''),
        ];
        $page    = max(1, (int)$this->query('page', 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        $assignments = $this->assignmentModel->getAllWithRelations($filters, $perPage, $offset);
        $total       = $this->assignmentModel->countWithFilters($filters);

        $this->render('organization/assignments/index', [
            'title'       => 'Assignment Management',
            'assignments' => $assignments,
            'filters'     => $filters,
            'stats'       => $this->assignmentModel->getStatistics(),
            'roles'       => $this->assignmentService->getAssignableRoles(),
            'farms'       => $this->farmModel->getActiveFarmsForSelect(),
            'warehouses'  => $this->warehouseModel->getActiveWarehousesForSelect(),
            'shops'       => $this->shopModel->getActiveShopsForSelect(),
            'pagination'  => [
                'current_page'  => $page,
                'total_pages'   => (int)ceil($total / $perPage),
                'total_records' => $total,
                'per_page'      => $perPage,
                'from'          => $total > 0 ? $offset + 1 : 0,
                'to'            => min($offset + $perPage, $total),
            ],
        ]);
    }

    // ── Create assignment (AJAX) ──────────────────────────────────────────────

    public function store(): void
    {
        $this->verifyCsrf();

        $data = [
            'user_id'         => (int)$this->input('user_id', 0),
            'location_type'   => $this->input('location_type', ''),
            'location_id'     => (int)$this->input('location_id', 0),
            'assignment_role' => $this->input('assignment_role', ''),
            'notes'           => trim($this->input('notes', '')),
        ];

        $result = $this->assignmentService->createAssignment($data, $this->userId());

        if ($result['success']) {
            $this->jsonSuccess(['assignment_id' => $result['assignment_id']], 'Assignment created successfully');
        } else {
            $this->jsonError(
                $result['errors']['general'] ?? 'Validation failed',
                HTTP_UNPROCESSABLE_ENTITY,
                $result['errors']
            );
        }
    }

    // ── Remove assignment (AJAX) ──────────────────────────────────────────────

    public function remove(int $id): void
    {
        $this->verifyCsrf();
        $notes  = trim($this->input('notes', ''));
        $result = $this->assignmentService->removeAssignment($id, $this->userId(), $notes ?: null);

        if ($result['success']) {
            $this->jsonSuccess(null, 'Assignment removed successfully');
        } else {
            $this->jsonError($result['error'] ?? 'Remove failed', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ── History for a user (AJAX) ─────────────────────────────────────────────

    public function userHistory(int $userId): void
    {
        $history = $this->assignmentModel->getHistoryForUser($userId, 50);
        $this->json(['success' => true, 'history' => $history]);
    }

    // ── History for a location (AJAX) ─────────────────────────────────────────

    public function locationHistory(string $type, int $id): void
    {
        $allowed = ['farm', 'warehouse', 'shop'];
        if (!in_array($type, $allowed, true)) {
            $this->jsonError('Invalid location type', HTTP_UNPROCESSABLE_ENTITY);
            return;
        }
        $history = $this->assignmentModel->getHistoryForLocation($type, $id, 50);
        $this->json(['success' => true, 'history' => $history]);
    }

    // ── Users eligible for a role (AJAX for form) ─────────────────────────────

    public function getUsersByRole(): void
    {
        $role  = $this->query('role', '');
        if (!$role) { $this->jsonError('Role required', HTTP_UNPROCESSABLE_ENTITY); return; }
        $users = $this->assignmentModel->getUsersByRole($role);
        $this->json(['success' => true, 'users' => $users]);
    }

    // ── Locations by type (AJAX for form) ────────────────────────────────────

    public function getLocationsByType(): void
    {
        $type = $this->query('type', '');
        $locations = match ($type) {
            'farm'      => $this->farmModel->getActiveFarmsForSelect(),
            'warehouse' => $this->warehouseModel->getActiveWarehousesForSelect(),
            'shop'      => $this->shopModel->getActiveShopsForSelect(),
            default     => [],
        };
        $this->json(['success' => true, 'locations' => $locations]);
    }
}
