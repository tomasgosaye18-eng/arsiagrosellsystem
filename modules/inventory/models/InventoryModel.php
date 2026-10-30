<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 11
 * =========================================================
 * File    : modules/inventory/models/InventoryModel.php
 * Purpose : Multi-location inventory queries.
 *           Reads from warehouse_inventory and shop_inventory.
 *           Does NOT duplicate Phase 10 mutation methods —
 *           those remain in WarehouseInventoryModel.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Inventory\Models;

use AAM\Core\BaseModel;

class InventoryModel extends BaseModel
{
    protected string $table      = 'warehouse_inventory';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = false;

    // ── Status helpers ────────────────────────────────────
    public static function stockStatusLabel(
        float   $available,
        float   $minLevel,
        ?string $expiryDate
    ): array {
        if ($expiryDate && strtotime($expiryDate) < time()) {
            return ['EXPIRED', 'danger'];
        }
        if ($expiryDate && strtotime($expiryDate) <= strtotime('+7 days')) {
            return ['EXPIRING SOON', 'warning'];
        }
        if ($available <= 0)                                    return ['OUT OF STOCK', 'danger'];
        if ($minLevel > 0 && $available <= $minLevel)          return ['LOW STOCK', 'warning'];
        return ['IN STOCK', 'success'];
    }

    // ── Dashboard KPIs (cross-location) ───────────────────
    public function getOrganizationKpis(): array
    {
        $wh = $this->rawQueryOne(
            "SELECT
                COUNT(*)                                              AS warehouse_products,
                COALESCE(SUM(current_quantity),0)                    AS warehouse_total,
                COALESCE(SUM(available_quantity),0)                  AS warehouse_available,
                COALESCE(SUM(reserved_quantity),0)                   AS warehouse_reserved,
                SUM(CASE WHEN available_quantity<=0 THEN 1 ELSE 0 END) AS wh_out_of_stock,
                SUM(CASE WHEN min_stock_level>0 AND available_quantity>0
                          AND available_quantity<=min_stock_level THEN 1 ELSE 0 END) AS wh_low_stock,
                SUM(CASE WHEN expiry_date IS NOT NULL
                          AND expiry_date<=CURDATE() THEN 1 ELSE 0 END) AS wh_expired,
                SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date>CURDATE()
                          AND expiry_date<=DATE_ADD(CURDATE(),INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS wh_expiring_soon
             FROM warehouse_inventory"
        ) ?? [];

        $sh = $this->rawQueryOne(
            "SELECT
                COUNT(*)                                              AS shop_products,
                COALESCE(SUM(current_quantity),0)                    AS shop_total,
                COALESCE(SUM(available_quantity),0)                  AS shop_available
             FROM shop_inventory"
        ) ?? [];

        return array_merge($wh, $sh);
    }

    // ── Warehouse-scoped KPIs ──────────────────────────────
    public function getWarehouseKpis(int $warehouseId): array
    {
        return $this->rawQueryOne(
            "SELECT
                COUNT(*)                                               AS total_products,
                COALESCE(SUM(current_quantity),0)                     AS total_stock,
                COALESCE(SUM(available_quantity),0)                   AS available_stock,
                COALESCE(SUM(reserved_quantity),0)                    AS reserved_stock,
                SUM(CASE WHEN available_quantity<=0 THEN 1 ELSE 0 END) AS out_of_stock,
                SUM(CASE WHEN min_stock_level>0 AND available_quantity>0
                          AND available_quantity<=min_stock_level THEN 1 ELSE 0 END) AS low_stock,
                SUM(CASE WHEN expiry_date IS NOT NULL
                          AND expiry_date<=CURDATE() THEN 1 ELSE 0 END) AS expired,
                SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date>CURDATE()
                          AND expiry_date<=DATE_ADD(CURDATE(),INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS expiring_soon
             FROM warehouse_inventory WHERE warehouse_id=:wid",
            ['wid' => $warehouseId]
        ) ?? [];
    }

    // ── Shop-scoped KPIs ──────────────────────────────────
    public function getShopKpis(int $shopId): array
    {
        return $this->rawQueryOne(
            "SELECT
                COUNT(*)                                               AS total_products,
                COALESCE(SUM(current_quantity),0)                     AS total_stock,
                COALESCE(SUM(available_quantity),0)                   AS available_stock,
                SUM(CASE WHEN available_quantity<=0 THEN 1 ELSE 0 END) AS out_of_stock,
                SUM(CASE WHEN min_stock_level>0 AND available_quantity>0
                          AND available_quantity<=min_stock_level THEN 1 ELSE 0 END) AS low_stock
             FROM shop_inventory WHERE shop_id=:sid",
            ['sid' => $shopId]
        ) ?? [];
    }

    // ── All-location paginated inventory ──────────────────
    public function paginateAll(
        int   $page    = 1,
        int   $perPage = 20,
        array $filters = []
    ): array {
        $page    = max(1, $page);
        $perPage = min($perPage, PAGINATION_MAX);
        $offset  = ($page - 1) * $perPage;

        [$where, $params] = $this->buildAllFilters($filters);

        $total = (int)($this->rawQueryOne(
            "SELECT COUNT(*) AS c FROM ({$this->allLocationsSQL($where)}) t", $params
        )['c'] ?? 0);

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $data = $this->rawQuery(
            $this->allLocationsSQL($where) .
            " ORDER BY location_type, location_name, product_name
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

    private function allLocationsSQL(string $where): string
    {
        return "SELECT * FROM (
            SELECT
                wi.id,
                'warehouse'                        AS location_type,
                wi.warehouse_id                    AS location_id,
                w.name                             AS location_name,
                wi.product_id,
                p.name                             AS product_name,
                p.sku                              AS product_sku,
                p.is_perishable,
                c.name                             AS category_name,
                u.abbreviation                     AS unit_abbr,
                wi.batch_number,
                wi.current_quantity,
                wi.available_quantity,
                wi.reserved_quantity,
                wi.min_stock_level,
                wi.expiry_date,
                wi.last_movement_at,
                wi.last_movement_type
            FROM warehouse_inventory wi
            JOIN products p ON p.id = wi.product_id
            LEFT JOIN warehouses w     ON w.id = wi.warehouse_id
            LEFT JOIN categories c     ON c.id = p.category_id
            LEFT JOIN units_of_measure u ON u.id = wi.unit_id
            UNION ALL
            SELECT
                si.id,
                'shop'                             AS location_type,
                si.shop_id                         AS location_id,
                s.name                             AS location_name,
                si.product_id,
                p.name                             AS product_name,
                p.sku                              AS product_sku,
                p.is_perishable,
                c.name                             AS category_name,
                u.abbreviation                     AS unit_abbr,
                si.batch_number,
                si.current_quantity,
                si.available_quantity,
                si.reserved_quantity,
                si.min_stock_level,
                si.expiry_date,
                si.last_movement_at,
                si.last_movement_type
            FROM shop_inventory si
            JOIN products p ON p.id = si.product_id
            LEFT JOIN shops s              ON s.id = si.shop_id
            LEFT JOIN categories c         ON c.id = p.category_id
            LEFT JOIN units_of_measure u   ON u.id = si.unit_id
        ) t WHERE {$where}";
    }

    private function buildAllFilters(array $f): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($f['location_type'])) {
            $where[]               = 'location_type = :ltype';
            $params['ltype']       = $f['location_type'];
        }
        if (!empty($f['location_id'])) {
            $where[]               = 'location_id = :lid';
            $params['lid']         = (int)$f['location_id'];
        }
        if (!empty($f['search'])) {
            $where[]               = "(product_name LIKE :q OR product_sku LIKE :q2 OR batch_number LIKE :q3)";
            $params['q']           = '%' . $f['search'] . '%';
            $params['q2']          = '%' . $f['search'] . '%';
            $params['q3']          = '%' . $f['search'] . '%';
        }
        if (!empty($f['category_id'])) {
            // category_id is lost in union; skip for now
        }
        if (!empty($f['status'])) {
            switch ($f['status']) {
                case 'out_of_stock':   $where[] = 'available_quantity <= 0'; break;
                case 'low_stock':      $where[] = 'min_stock_level > 0 AND available_quantity > 0 AND available_quantity <= min_stock_level'; break;
                case 'expiring_soon':  $where[] = 'expiry_date IS NOT NULL AND expiry_date > CURDATE() AND expiry_date <= DATE_ADD(CURDATE(),INTERVAL 7 DAY)'; break;
                case 'expired':        $where[] = 'expiry_date IS NOT NULL AND expiry_date < CURDATE()'; break;
                case 'in_stock':       $where[] = 'available_quantity > 0'; break;
            }
        }
        if (!empty($f['batch'])) {
            $where[]               = 'batch_number LIKE :batch';
            $params['batch']       = '%' . $f['batch'] . '%';
        }

        return [implode(' AND ', $where), $params];
    }
}
