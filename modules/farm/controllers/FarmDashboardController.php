<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 9A
 * =========================================================
 * File    : modules/farm/controllers/FarmDashboardController.php
 * Purpose : Farm Manager dashboard controller.
 *           Scoped strictly to the farm assigned to the
 *           authenticated user. No cross-farm data leaks.
 *
 * Routes  :
 *   GET /farm/dashboard           → index()
 *   GET /api/dashboard/farm-charts → chartData()  (AJAX)
 *
 * Access  : ROLE_FARM_MANAGER only (admin/GM may also view
 *           the dashboard for monitoring purposes).
 *
 * Farm scope:
 *   Resolved from SESSION_LOCATION_ID (set during login by
 *   UserModel::findByCredential → attachLocationAssignment).
 *   Falls back to loading from user_location_assignments if
 *   the session key is missing (e.g. after session refresh).
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Farm\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;
use AAM\Core\SessionManager;

class FarmDashboardController extends BaseController
{
    // ──────────────────────────────────────────────────────────────────────────
    // GET /farm/dashboard
    // ──────────────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_FARM_MANAGER, ROLE_GENERAL_MANAGER);

        $db       = Database::getInstance();
        $userId   = $this->userId();
        $userName = SessionManager::get(SESSION_USER_NAME, 'User');
        $role     = $this->userRole();

        // ── Resolve assigned farm ─────────────────────────────────────────────
        $farmId = $this->resolveAssignedFarmId($db, (int)$userId);

        // Load farm info
        $farm = null;
        if ($farmId > 0) {
            try {
                $farm = $db->fetchOne(
                    "SELECT id, name, location, farm_type, area_hectares
                     FROM farms WHERE id = :id AND is_active = 1",
                    ['id' => $farmId]
                );
            } catch (\Throwable) {}
        }

        // ── KPIs (all scoped to assigned farm) ────────────────────────────────
        $kpis = $this->getKpis($db, $farmId);

        // ── Recent harvests ───────────────────────────────────────────────────
        $recentHarvests = $this->getRecentHarvests($db, $farmId);

        // ── Dispatch status summary ───────────────────────────────────────────
        $dispatchSummary = $this->getDispatchSummary($db, $farmId);

        // ── Production by product (last 30 days) ─────────────────────────────
        $productionByProduct = $this->getProductionByProduct($db, $farmId);

        $this->render('farm.dashboard', [
            'pageTitle'          => 'Farm Dashboard',
            'userName'           => $userName,
            'userRole'           => $role,
            'farm'               => $farm,
            'farmId'             => $farmId,
            'kpis'               => $kpis,
            'recentHarvests'     => $recentHarvests,
            'dispatchSummary'    => $dispatchSummary,
            'productionByProduct'=> $productionByProduct,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GET /api/dashboard/farm-charts  (AJAX — called by the dashboard JS)
    // ──────────────────────────────────────────────────────────────────────────

    public function chartData(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_FARM_MANAGER, ROLE_GENERAL_MANAGER);

        $db     = Database::getInstance();
        $farmId = $this->resolveAssignedFarmId($db, (int)$this->userId());

        $this->jsonSuccess([
            'production_trend' => $this->getProductionTrend($db, $farmId),
            'dispatch_trend'   => $this->getDispatchTrend($db, $farmId),
        ], 'Farm chart data loaded.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private — Farm scope resolver
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Resolve the farm_id for the authenticated user.
     *
     * Priority order:
     *  1. SESSION_LOCATION_ID (set by loginUser via findByCredential)
     *  2. user_location_assignments table (Phase 5A)
     *  3. organization_assignments table (Phase 8C)
     *
     * Admin and GM bypass farm scoping — they receive 0 (all farms).
     */
    private function resolveAssignedFarmId(Database $db, int $userId): int
    {
        $role = $this->userRole();

        // Admin / GM see all — return 0 means no farm filter
        if (in_array($role, [ROLE_GENERAL_MANAGER], true)) {
            return 0;
        }

        // 1. From session
        $sessionType = SessionManager::get(SESSION_LOCATION_TYPE);
        $sessionId   = (int)SessionManager::get(SESSION_LOCATION_ID, 0);
        if ($sessionType === 'farm' && $sessionId > 0) {
            return $sessionId;
        }

        // 2. From user_location_assignments
        try {
            $row = $db->fetchOne(
                "SELECT location_id FROM user_location_assignments
                 WHERE user_id = :uid AND location_type = 'farm'
                 LIMIT 1",
                ['uid' => $userId]
            );
            if ($row) {
                $id = (int)$row['location_id'];
                // Backfill session so future requests are instant
                SessionManager::set(SESSION_LOCATION_TYPE, 'farm');
                SessionManager::set(SESSION_LOCATION_ID, $id);
                return $id;
            }
        } catch (\Throwable) {}

        // 3. From organization_assignments
        try {
            $row = $db->fetchOne(
                "SELECT location_id FROM organization_assignments
                 WHERE user_id = :uid AND location_type = 'farm' AND is_active = 1
                 ORDER BY assigned_at DESC LIMIT 1",
                ['uid' => $userId]
            );
            if ($row) {
                $id = (int)$row['location_id'];
                SessionManager::set(SESSION_LOCATION_TYPE, 'farm');
                SessionManager::set(SESSION_LOCATION_ID, $id);
                return $id;
            }
        } catch (\Throwable) {}

        return 0; // unassigned
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private — Data methods (all scoped to $farmId, gracefully degrade)
    // ──────────────────────────────────────────────────────────────────────────

    /** @return array<string, int|float> */
    private function getKpis(Database $db, int $farmId): array
    {
        $w  = $farmId > 0 ? "AND h.farm_id = :fid" : "";
        $b  = $farmId > 0 ? ['fid' => $farmId] : [];
        $dw = $farmId > 0 ? "AND d.farm_id = :fid" : "";

        return [
            'today_harvests'        => $this->safe($db,
                "SELECT COUNT(*) FROM harvest_batches h
                 WHERE DATE(h.harvest_date) = CURDATE() $w", $b),

            'monthly_harvests'      => $this->safe($db,
                "SELECT COUNT(*) FROM harvest_batches h
                 WHERE YEAR(h.harvest_date)  = YEAR(NOW())
                   AND MONTH(h.harvest_date) = MONTH(NOW()) $w", $b),

            'total_kg_today'        => $this->safeFloat($db,
                "SELECT COALESCE(SUM(h.initial_quantity),0) FROM harvest_batches h
                 WHERE DATE(h.harvest_date) = CURDATE() $w", $b),

            'total_kg_this_month'   => $this->safeFloat($db,
                "SELECT COALESCE(SUM(h.initial_quantity),0) FROM harvest_batches h
                 WHERE YEAR(h.harvest_date)  = YEAR(NOW())
                   AND MONTH(h.harvest_date) = MONTH(NOW()) $w", $b),

            'pending_dispatch'      => $this->safe($db,
                "SELECT COUNT(*) FROM harvest_batches h
                 WHERE h.status IN ('active','partially_dispatched') $w", $b),

            'dispatched_this_month' => $this->safe($db,
                "SELECT COUNT(*) FROM farm_dispatches d
                 WHERE YEAR(d.dispatch_date)  = YEAR(NOW())
                   AND MONTH(d.dispatch_date) = MONTH(NOW())
                   AND d.status != 'cancelled' $dw",
                $farmId > 0 ? ['fid' => $farmId] : []),

            'total_products'        => $this->safe($db,
                "SELECT COUNT(DISTINCT h.product_id) FROM harvest_batches h
                 WHERE h.status != 'depleted' $w", $b),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function getRecentHarvests(Database $db, int $farmId): array
    {
        $where = $farmId > 0 ? "AND h.farm_id = :fid" : "";
        $bind  = $farmId > 0 ? ['fid' => $farmId] : [];
        try {
            return $db->fetchAll(
                "SELECT h.id, h.harvest_date, h.initial_quantity AS quantity_kg,
                        h.remaining_quantity, h.status, h.notes, h.batch_number,
                        h.quality_grade,
                        p.name AS product_name, p.id AS product_id,
                        f.name AS farm_name,
                        u.first_name, u.last_name
                 FROM harvest_batches h
                 LEFT JOIN products p ON p.id = h.product_id
                 LEFT JOIN farms    f ON f.id = h.farm_id
                 LEFT JOIN users    u ON u.id = h.recorded_by
                 WHERE 1=1 $where
                 ORDER BY h.harvest_date DESC, h.id DESC
                 LIMIT 10",
                $bind
            ) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<string,int> */
    private function getDispatchSummary(Database $db, int $farmId): array
    {
        $where = $farmId > 0 ? "WHERE d.farm_id = :fid" : "";
        $bind  = $farmId > 0 ? ['fid' => $farmId] : [];
        try {
            $rows = $db->fetchAll(
                "SELECT d.status, COUNT(*) AS cnt
                 FROM farm_dispatches d $where
                 GROUP BY d.status",
                $bind
            ) ?: [];
            $summary = [];
            foreach ($rows as $r) {
                $summary[$r['status']] = (int)$r['cnt'];
            }
            return $summary;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function getProductionByProduct(Database $db, int $farmId): array
    {
        $where = $farmId > 0 ? "AND h.farm_id = :fid" : "";
        $bind  = $farmId > 0 ? ['fid' => $farmId] : [];
        try {
            return $db->fetchAll(
                "SELECT p.name AS product_name,
                        COUNT(h.id)                          AS harvest_count,
                        COALESCE(SUM(h.initial_quantity),0)  AS total_kg
                 FROM harvest_batches h
                 LEFT JOIN products p ON p.id = h.product_id
                 WHERE h.harvest_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
                   AND h.status NOT IN ('depleted','quarantine') $where
                 GROUP BY h.product_id, p.name
                 ORDER BY total_kg DESC
                 LIMIT 8",
                $bind
            ) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array{labels:string[], data:float[]} */
    private function getProductionTrend(Database $db, int $farmId): array
    {
        $where = $farmId > 0 ? "AND h.farm_id = :fid" : "";
        $bind  = $farmId > 0 ? ['fid' => $farmId] : [];
        try {
            $rows = $db->fetchAll(
                "SELECT DATE_FORMAT(h.harvest_date,'%d %b') AS day_label,
                        DATE(h.harvest_date)                 AS day,
                        COALESCE(SUM(h.initial_quantity),0)  AS total_kg
                 FROM harvest_batches h
                 WHERE h.harvest_date >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
                   AND h.status NOT IN ('depleted','quarantine') $where
                 GROUP BY day, day_label
                 ORDER BY day ASC",
                $bind
            ) ?: [];
        } catch (\Throwable) {
            $rows = [];
        }
        return [
            'labels' => array_column($rows, 'day_label'),
            'data'   => array_map('floatval', array_column($rows, 'total_kg')),
        ];
    }

    /** @return array{labels:string[], data:int[]} */
    private function getDispatchTrend(Database $db, int $farmId): array
    {
        $where = $farmId > 0 ? "AND d.farm_id = :fid" : "";
        $bind  = $farmId > 0 ? ['fid' => $farmId] : [];
        try {
            $rows = $db->fetchAll(
                "SELECT DATE_FORMAT(d.dispatch_date,'%d %b') AS day_label,
                        DATE(d.dispatch_date)                  AS day,
                        COUNT(*)                               AS total
                 FROM farm_dispatches d
                 WHERE d.dispatch_date >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
                   AND d.status != 'cancelled' $where
                 GROUP BY day, day_label
                 ORDER BY day ASC",
                $bind
            ) ?: [];
        } catch (\Throwable) {
            $rows = [];
        }
        return [
            'labels' => array_column($rows, 'day_label'),
            'data'   => array_map('intval', array_column($rows, 'total')),
        ];
    }

    // ── Safe query helpers ────────────────────────────────────────────────────

    private function safe(Database $db, string $sql, array $bind = []): int
    {
        try {
            return (int)($db->fetchColumn($sql, $bind) ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function safeFloat(Database $db, string $sql, array $bind = []): float
    {
        try {
            return (float)($db->fetchColumn($sql, $bind) ?? 0);
        } catch (\Throwable) {
            return 0.0;
        }
    }
}
