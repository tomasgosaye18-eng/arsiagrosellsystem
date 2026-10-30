<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/reports/models/FarmReportModel.php
 * Purpose : Data access for Farm Manager Reports.
 *           Provides queries for:
 *             1. Production Report  — aggregated harvest totals
 *             2. Harvest Report     — detailed harvest batch list
 *             3. Dispatch Report    — farm-to-warehouse dispatches
 *
 * All queries are scoped to the authenticated user's assigned
 * farm. Admin / GM receive unscoped (all-farm) data.
 *
 * Depends on: harvest_batches, farm_dispatches,
 *             farm_dispatch_items, products,
 *             units_of_measure, users, farms
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Reports\Models;

use AAM\Core\BaseModel;

class FarmReportModel extends BaseModel
{
    protected string $table      = 'harvest_batches';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = false;

    // ──────────────────────────────────────────────────────
    // SUMMARY CARDS — top of the Reports page
    // ──────────────────────────────────────────────────────

    /**
     * 4 summary card values for the reports page header.
     *
     * @param int    $farmId    0 = all farms (admin/GM)
     * @param string $dateFrom  Y-m-d or ''
     * @param string $dateTo    Y-m-d or ''
     * @return array{
     *   total_production: float,
     *   total_harvests: int,
     *   total_dispatched: int,
     *   pending_dispatches: int,
     * }
     */
    public function getSummaryCards(
        int    $farmId   = 0,
        string $dateFrom = '',
        string $dateTo   = ''
    ): array {
        [$hWhere, $hParams] = $this->harvestDateScope($farmId, $dateFrom, $dateTo);
        [$dWhere, $dParams] = $this->dispatchDateScope($farmId, $dateFrom, $dateTo);

        $totalProduction = (float)($this->rawQueryOne(
            "SELECT COALESCE(SUM(hb.initial_quantity), 0) AS v
             FROM harvest_batches hb
             WHERE {$hWhere}",
            $hParams
        )['v'] ?? 0);

        $totalHarvests = (int)($this->rawQueryOne(
            "SELECT COUNT(*) AS v
             FROM harvest_batches hb
             WHERE {$hWhere}",
            $hParams
        )['v'] ?? 0);

        $totalDispatched = (int)($this->rawQueryOne(
            "SELECT COUNT(*) AS v
             FROM farm_dispatches d
             WHERE {$dWhere}
               AND d.status != 'cancelled'",
            $dParams
        )['v'] ?? 0);

        // Pending = harvest batches still waiting to be dispatched
        $pendFarmClause  = $farmId > 0 ? "AND hb.farm_id = :pfid" : "";
        $pendFarmParams  = $farmId > 0 ? ['pfid' => $farmId] : [];
        $pendingDispatch = (int)($this->rawQueryOne(
            "SELECT COUNT(*) AS v
             FROM harvest_batches hb
             WHERE hb.status IN ('active','partially_dispatched') {$pendFarmClause}",
            $pendFarmParams
        )['v'] ?? 0);

        return [
            'total_production'  => $totalProduction,
            'total_harvests'    => $totalHarvests,
            'total_dispatched'  => $totalDispatched,
            'pending_dispatches'=> $pendingDispatch,
        ];
    }

    // ──────────────────────────────────────────────────────
    // 1. PRODUCTION REPORT
    // ──────────────────────────────────────────────────────

    /**
     * Production report — aggregated by product.
     * Returns one row per product with total produced qty
     * and harvest count, ordered by total desc.
     *
     * @param int    $farmId
     * @param string $dateFrom
     * @param string $dateTo
     * @param int    $productId  0 = all products
     * @return array<int, array<string, mixed>>
     */
    public function getProductionByProduct(
        int    $farmId    = 0,
        string $dateFrom  = '',
        string $dateTo    = '',
        int    $productId = 0
    ): array {
        [$where, $params] = $this->harvestDateScope($farmId, $dateFrom, $dateTo);

        if ($productId > 0) {
            $where              .= ' AND hb.product_id = :pid';
            $params['pid']       = $productId;
        }

        return $this->rawQuery(
            "SELECT
                p.id            AS product_id,
                p.name          AS product_name,
                p.sku           AS product_sku,
                u.name          AS unit_name,
                u.abbreviation  AS unit_abbr,
                COUNT(hb.id)                         AS harvest_count,
                COALESCE(SUM(hb.initial_quantity), 0) AS total_produced,
                COALESCE(MIN(hb.harvest_date), '')    AS first_harvest,
                COALESCE(MAX(hb.harvest_date), '')    AS last_harvest
             FROM harvest_batches hb
             LEFT JOIN products         p ON p.id  = hb.product_id
             LEFT JOIN units_of_measure u ON u.id  = hb.unit_id
             WHERE {$where}
             GROUP BY hb.product_id, p.id, p.name, p.sku, u.name, u.abbreviation
             ORDER BY total_produced DESC",
            $params
        );
    }

    /**
     * Production trend — daily totals for a sparkline/bar chart.
     * Returns last N days bucketed by day.
     *
     * @return array{labels: string[], data: float[]}
     */
    public function getProductionTrend(
        int    $farmId   = 0,
        string $dateFrom = '',
        string $dateTo   = ''
    ): array {
        // Use supplied range or last 30 days as fallback
        $from = $dateFrom ?: date('Y-m-d', strtotime('-29 days'));
        $to   = $dateTo   ?: date('Y-m-d');

        $farmClause  = $farmId > 0 ? "AND hb.farm_id = :fid" : "";
        $farmParam   = $farmId > 0 ? ['fid' => $farmId] : [];

        $rows = $this->rawQuery(
            "SELECT
                DATE_FORMAT(hb.harvest_date, '%d %b') AS day_label,
                DATE(hb.harvest_date)                  AS day,
                COALESCE(SUM(hb.initial_quantity), 0)  AS total_qty
             FROM harvest_batches hb
             WHERE hb.harvest_date BETWEEN :from AND :to {$farmClause}
             GROUP BY day, day_label
             ORDER BY day ASC",
            array_merge(['from' => $from, 'to' => $to], $farmParam)
        );

        return [
            'labels' => array_column($rows, 'day_label'),
            'data'   => array_map('floatval', array_column($rows, 'total_qty')),
        ];
    }

    // ──────────────────────────────────────────────────────
    // 2. HARVEST REPORT
    // ──────────────────────────────────────────────────────

    /**
     * Paginated harvest batch list for the Harvest Report.
     *
     * @param int    $farmId
     * @param int    $page
     * @param int    $perPage
     * @param array{
     *   search?: string,
     *   product_id?: int,
     *   date_from?: string,
     *   date_to?: string,
     *   status?: string,
     * } $filters
     * @return array{
     *   data: array,
     *   total: int,
     *   page: int,
     *   perPage: int,
     *   totalPages: int,
     *   from: int,
     *   to: int,
     * }
     */
    public function paginateHarvestReport(
        int   $farmId  = 0,
        int   $page    = 1,
        int   $perPage = 25,
        array $filters = []
    ): array {
        $page    = max(1, $page);
        $perPage = min($perPage, 100);
        $offset  = ($page - 1) * $perPage;

        [$where, $params] = $this->buildHarvestFilters($farmId, $filters);

        $total = (int)($this->rawQueryOne(
            "SELECT COUNT(*) AS c
             FROM harvest_batches hb
             LEFT JOIN products p ON p.id = hb.product_id
             WHERE {$where}",
            $params
        )['c'] ?? 0);

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $data = $this->rawQuery(
            "SELECT
                hb.id,
                hb.harvest_date,
                hb.batch_number,
                hb.initial_quantity,
                hb.remaining_quantity,
                hb.quality_grade,
                hb.status,
                hb.notes,
                p.id   AS product_id,
                p.name AS product_name,
                u.abbreviation AS unit_abbr,
                f.name AS farm_name,
                CONCAT(usr.first_name, ' ', usr.last_name) AS recorded_by_name
             FROM harvest_batches hb
             LEFT JOIN products         p   ON p.id   = hb.product_id
             LEFT JOIN units_of_measure u   ON u.id   = hb.unit_id
             LEFT JOIN farms            f   ON f.id   = hb.farm_id
             LEFT JOIN users            usr ON usr.id = hb.recorded_by
             WHERE {$where}
             ORDER BY hb.harvest_date DESC, hb.id DESC
             LIMIT :limit OFFSET :offset",
            $params
        );

        return [
            'data'       => $data,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int) ceil($total / max(1, $perPage)),
            'from'       => $total > 0 ? $offset + 1 : 0,
            'to'         => min($offset + $perPage, $total),
        ];
    }

    // ──────────────────────────────────────────────────────
    // 3. DISPATCH REPORT
    // ──────────────────────────────────────────────────────

    /**
     * Paginated dispatch list for the Dispatch Report.
     *
     * @param int    $farmId
     * @param int    $page
     * @param int    $perPage
     * @param array{
     *   date_from?: string,
     *   date_to?: string,
     *   product_id?: int,
     *   status?: string,
     *   search?: string,
     * } $filters
     * @return array{
     *   data: array,
     *   total: int,
     *   page: int,
     *   perPage: int,
     *   totalPages: int,
     *   from: int,
     *   to: int,
     * }
     */
    public function paginateDispatchReport(
        int   $farmId  = 0,
        int   $page    = 1,
        int   $perPage = 25,
        array $filters = []
    ): array {
        $page    = max(1, $page);
        $perPage = min($perPage, 100);
        $offset  = ($page - 1) * $perPage;

        [$where, $params] = $this->buildDispatchFilters($farmId, $filters);

        $total = (int)($this->rawQueryOne(
            "SELECT COUNT(DISTINCT d.id) AS c
             FROM farm_dispatches d
             LEFT JOIN farm_dispatch_items di ON di.dispatch_id = d.id
             WHERE {$where}",
            $params
        )['c'] ?? 0);

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $data = $this->rawQuery(
            "SELECT
                d.id,
                d.dispatch_number,
                d.dispatch_date,
                d.status,
                d.notes,
                d.expected_arrival_date,
                f.name  AS farm_name,
                w.name  AS warehouse_name,
                CONCAT(ui.first_name, ' ', ui.last_name) AS initiated_by_name,
                (SELECT COUNT(*) FROM farm_dispatch_items di2 WHERE di2.dispatch_id = d.id) AS item_count,
                (SELECT COALESCE(SUM(di2.quantity_dispatched), 0) FROM farm_dispatch_items di2
                 WHERE di2.dispatch_id = d.id) AS total_qty,
                (SELECT GROUP_CONCAT(DISTINCT p2.name ORDER BY p2.name SEPARATOR ', ')
                 FROM farm_dispatch_items di2
                 LEFT JOIN products p2 ON p2.id = di2.product_id
                 WHERE di2.dispatch_id = d.id) AS products_list
             FROM farm_dispatches d
             LEFT JOIN farms      f  ON f.id  = d.farm_id
             LEFT JOIN warehouses w  ON w.id  = d.warehouse_id
             LEFT JOIN users      ui ON ui.id = d.initiated_by
             WHERE {$where}
             GROUP BY d.id
             ORDER BY d.dispatch_date DESC, d.id DESC
             LIMIT :limit OFFSET :offset",
            $params
        );

        return [
            'data'       => $data,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int) ceil($total / max(1, $perPage)),
            'from'       => $total > 0 ? $offset + 1 : 0,
            'to'         => min($offset + $perPage, $total),
        ];
    }

    // ──────────────────────────────────────────────────────
    // DROPDOWN HELPERS
    // ──────────────────────────────────────────────────────

    /**
     * Product list for filter dropdowns (only products that have harvest data).
     *
     * @param int $farmId  0 = all farms
     * @return array<int, array{id: int, name: string}>
     */
    public function getHarvestedProducts(int $farmId = 0): array
    {
        $clause = $farmId > 0 ? "AND hb.farm_id = :fid" : "";
        $bind   = $farmId > 0 ? ['fid' => $farmId] : [];

        return $this->rawQuery(
            "SELECT DISTINCT p.id, p.name
             FROM products p
             INNER JOIN harvest_batches hb ON hb.product_id = p.id
             WHERE 1=1 {$clause}
             ORDER BY p.name ASC",
            $bind
        );
    }

    /**
     * Product list for dispatch filter (products that appear in dispatch items).
     *
     * @param int $farmId  0 = all farms
     * @return array<int, array{id: int, name: string}>
     */
    public function getDispatchedProducts(int $farmId = 0): array
    {
        $farmClause = $farmId > 0 ? "AND d.farm_id = :fid" : "";
        $bind       = $farmId > 0 ? ['fid' => $farmId] : [];

        return $this->rawQuery(
            "SELECT DISTINCT p.id, p.name
             FROM products p
             INNER JOIN farm_dispatch_items di ON di.product_id = p.id
             INNER JOIN farm_dispatches d ON d.id = di.dispatch_id
             WHERE 1=1 {$farmClause}
             ORDER BY p.name ASC",
            $bind
        );
    }

    // ──────────────────────────────────────────────────────
    // PRIVATE — FILTER BUILDERS
    // ──────────────────────────────────────────────────────

    /**
     * Build WHERE clause + params for harvest_batches scoped queries.
     */
    private function harvestDateScope(int $farmId, string $dateFrom, string $dateTo): array
    {
        $where  = ['1=1'];
        $params = [];

        if ($farmId > 0) {
            $where[]         = 'hb.farm_id = :fid';
            $params['fid']   = $farmId;
        }
        if ($dateFrom !== '') {
            $where[]           = 'hb.harvest_date >= :dfrom';
            $params['dfrom']   = $dateFrom;
        }
        if ($dateTo !== '') {
            $where[]           = 'hb.harvest_date <= :dto';
            $params['dto']     = $dateTo;
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * Build WHERE clause + params for farm_dispatches scoped queries.
     */
    private function dispatchDateScope(int $farmId, string $dateFrom, string $dateTo): array
    {
        $where  = ['1=1'];
        $params = [];

        if ($farmId > 0) {
            $where[]          = 'd.farm_id = :fid';
            $params['fid']    = $farmId;
        }
        if ($dateFrom !== '') {
            $where[]            = 'd.dispatch_date >= :dfrom';
            $params['dfrom']    = $dateFrom;
        }
        if ($dateTo !== '') {
            $where[]            = 'd.dispatch_date <= :dto';
            $params['dto']      = $dateTo;
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * Build WHERE + params for the harvest report paginator.
     */
    private function buildHarvestFilters(int $farmId, array $f): array
    {
        $where  = ['1=1'];
        $params = [];

        if ($farmId > 0) {
            $where[]          = 'hb.farm_id = :fid';
            $params['fid']    = $farmId;
        }

        if (!empty($f['date_from'])) {
            $where[]            = 'hb.harvest_date >= :dfrom';
            $params['dfrom']    = $f['date_from'];
        }
        if (!empty($f['date_to'])) {
            $where[]            = 'hb.harvest_date <= :dto';
            $params['dto']      = $f['date_to'];
        }
        if (!empty($f['product_id'])) {
            $where[]            = 'hb.product_id = :pid';
            $params['pid']      = (int)$f['product_id'];
        }
        if (!empty($f['status'])) {
            $where[]            = 'hb.status = :status';
            $params['status']   = $f['status'];
        }
        if (!empty($f['search'])) {
            $where[]            = "(hb.batch_number LIKE :q OR p.name LIKE :q)";
            $params['q']        = '%' . $f['search'] . '%';
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * Build WHERE + params for the dispatch report paginator.
     * Joins farm_dispatch_items when filtering by product.
     */
    private function buildDispatchFilters(int $farmId, array $f): array
    {
        $where  = ['1=1'];
        $params = [];

        if ($farmId > 0) {
            $where[]          = 'd.farm_id = :fid';
            $params['fid']    = $farmId;
        }
        if (!empty($f['date_from'])) {
            $where[]            = 'd.dispatch_date >= :dfrom';
            $params['dfrom']    = $f['date_from'];
        }
        if (!empty($f['date_to'])) {
            $where[]            = 'd.dispatch_date <= :dto';
            $params['dto']      = $f['date_to'];
        }
        if (!empty($f['status'])) {
            $where[]            = 'd.status = :status';
            $params['status']   = $f['status'];
        }
        if (!empty($f['product_id'])) {
            $where[]            = 'EXISTS (SELECT 1 FROM farm_dispatch_items di
                                          WHERE di.dispatch_id = d.id
                                            AND di.product_id = :pid)';
            $params['pid']      = (int)$f['product_id'];
        }
        if (!empty($f['search'])) {
            $where[]            = 'd.dispatch_number LIKE :q';
            $params['q']        = '%' . $f['search'] . '%';
        }

        return [implode(' AND ', $where), $params];
    }
}
