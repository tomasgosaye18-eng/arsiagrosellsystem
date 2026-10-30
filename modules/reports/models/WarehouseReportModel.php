<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Warehouse Reports
 * =========================================================
 * File    : modules/reports/models/WarehouseReportModel.php
 * Purpose : Data access for all 5 Warehouse Reports:
 *           1. Stock Report
 *           2. Receiving Report
 *           3. Transfer Report
 *           4. Discrepancy Report
 *           5. Expiry Report
 *
 * All queries use REAL existing tables.
 * No dummy data. No duplicate tables created.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Reports\Models;

use AAM\Core\BaseModel;

class WarehouseReportModel extends BaseModel
{
    protected string $table      = 'warehouse_inventory';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = false;

    // ──────────────────────────────────────────────────────
    // 1. STOCK REPORT
    // ──────────────────────────────────────────────────────

    public function getStockReport(int $warehouseId, array $f = []): array
    {
        $where  = ['wi.warehouse_id = :wid'];
        $params = ['wid' => $warehouseId];

        if (!empty($f['product_id'])) {
            $where[] = 'wi.product_id = :pid';
            $params['pid'] = (int)$f['product_id'];
        }
        if (!empty($f['category_id'])) {
            $where[] = 'p.category_id = :cat';
            $params['cat'] = (int)$f['category_id'];
        }
        if (!empty($f['batch'])) {
            $where[] = 'wi.batch_number LIKE :bn';
            $params['bn'] = '%' . $f['batch'] . '%';
        }
        if (!empty($f['status'])) {
            switch ($f['status']) {
                case 'in_stock':      $where[] = 'wi.available_quantity > 0 AND (wi.min_stock_level = 0 OR wi.available_quantity > wi.min_stock_level)'; break;
                case 'low_stock':     $where[] = 'wi.min_stock_level > 0 AND wi.available_quantity > 0 AND wi.available_quantity <= wi.min_stock_level'; break;
                case 'out_of_stock':  $where[] = 'wi.available_quantity <= 0'; break;
                case 'expiring_soon': $where[] = 'wi.expiry_date IS NOT NULL AND wi.expiry_date > CURDATE() AND wi.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)'; break;
                case 'expired':       $where[] = 'wi.expiry_date IS NOT NULL AND wi.expiry_date < CURDATE()'; break;
            }
        }

        $wc = implode(' AND ', $where);
        return $this->rawQuery(
            "SELECT
                wi.*,
                p.name          AS product_name,
                p.sku           AS product_code,
                c.name          AS category_name,
                u.abbreviation  AS unit_abbr,
                u.name          AS unit_name
             FROM warehouse_inventory wi
             JOIN products         p ON p.id = wi.product_id
             LEFT JOIN categories  c ON c.id = p.category_id
             LEFT JOIN units_of_measure u ON u.id = wi.unit_id
             WHERE {$wc}
             ORDER BY
                CASE WHEN wi.expiry_date IS NOT NULL AND wi.expiry_date < CURDATE() THEN 0
                     WHEN wi.expiry_date IS NOT NULL AND wi.expiry_date <= DATE_ADD(CURDATE(),INTERVAL 7 DAY) THEN 1
                     WHEN wi.available_quantity <= 0 THEN 2
                     WHEN wi.min_stock_level > 0 AND wi.available_quantity <= wi.min_stock_level THEN 3
                     ELSE 4 END ASC, p.name ASC",
            $params
        );
    }

    public function getStockSummary(int $warehouseId): array
    {
        return $this->rawQueryOne(
            "SELECT
                COUNT(*) AS total_products,
                COALESCE(SUM(current_quantity),0)  AS total_stock,
                COALESCE(SUM(available_quantity),0) AS total_available,
                SUM(CASE WHEN available_quantity <= 0 THEN 1 ELSE 0 END) AS out_of_stock,
                SUM(CASE WHEN min_stock_level>0 AND available_quantity>0
                          AND available_quantity<=min_stock_level THEN 1 ELSE 0 END) AS low_stock,
                SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 1 ELSE 0 END) AS expired
             FROM warehouse_inventory WHERE warehouse_id = :wid",
            ['wid' => $warehouseId]
        ) ?? [];
    }

    // ──────────────────────────────────────────────────────
    // 2. RECEIVING REPORT
    // ──────────────────────────────────────────────────────

    public function getReceivingReport(int $warehouseId, array $f = [], int $page = 1, int $perPage = 25): array
    {
        $where  = ['wr.warehouse_id = :wid'];
        $params = ['wid' => $warehouseId];

        if (!empty($f['farm_id']))    { $where[] = 'wr.farm_id = :fid';    $params['fid']    = (int)$f['farm_id']; }
        if (!empty($f['status']))     { $where[] = 'wr.status = :status';  $params['status'] = $f['status']; }
        if (!empty($f['date_from']))  { $where[] = 'wr.received_date >= :dfrom'; $params['dfrom'] = $f['date_from']; }
        if (!empty($f['date_to']))    { $where[] = 'wr.received_date <= :dto';   $params['dto']   = $f['date_to']; }
        if (!empty($f['product_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM warehouse_receiving_items wri WHERE wri.receiving_id=wr.id AND wri.product_id=:pid)';
            $params['pid'] = (int)$f['product_id'];
        }
        if (!empty($f['batch'])) {
            $where[] = 'EXISTS (SELECT 1 FROM warehouse_receiving_items wri WHERE wri.receiving_id=wr.id AND wri.batch_number LIKE :bn)';
            $params['bn'] = '%' . $f['batch'] . '%';
        }

        $wc     = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        $total  = (int)($this->rawQueryOne("SELECT COUNT(*) AS c FROM warehouse_receiving wr WHERE {$wc}", $params)['c'] ?? 0);
        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $data = $this->rawQuery(
            "SELECT wr.*,
                fd.dispatch_number,
                f.name  AS farm_name,
                CONCAT(u.first_name,' ',u.last_name) AS received_by_name
             FROM warehouse_receiving wr
             LEFT JOIN farm_dispatches fd ON fd.id = wr.dispatch_id
             LEFT JOIN farms           f  ON f.id  = wr.farm_id
             LEFT JOIN users           u  ON u.id  = wr.received_by
             WHERE {$wc}
             ORDER BY wr.received_date DESC, wr.id DESC
             LIMIT :limit OFFSET :offset",
            $params
        );

        return ['data'=>$data,'total'=>$total,'page'=>$page,'perPage'=>$perPage,
                'totalPages'=>(int)ceil($total/max(1,$perPage)),
                'from'=>$total>0?$offset+1:0,'to'=>min($offset+$perPage,$total)];
    }

    public function getReceivingItems(int $receivingId): array
    {
        return $this->rawQuery(
            "SELECT wri.*, p.name AS product_name, p.sku, u.abbreviation AS unit_abbr
             FROM warehouse_receiving_items wri
             JOIN products p ON p.id = wri.product_id
             LEFT JOIN units_of_measure u ON u.id = wri.unit_id
             WHERE wri.receiving_id = :rid ORDER BY wri.id",
            ['rid' => $receivingId]
        );
    }

    public function getReceivingSummary(int $warehouseId, string $dateFrom='', string $dateTo=''): array
    {
        $where  = ['wr.warehouse_id = :wid'];
        $params = ['wid' => $warehouseId];
        if ($dateFrom) { $where[] = 'wr.received_date >= :dfrom'; $params['dfrom'] = $dateFrom; }
        if ($dateTo)   { $where[] = 'wr.received_date <= :dto';   $params['dto']   = $dateTo; }
        $wc = implode(' AND ', $where);
        return $this->rawQueryOne(
            "SELECT COUNT(*) AS total_receivings,
                    COALESCE(SUM(total_expected),0)  AS total_expected,
                    COALESCE(SUM(total_received),0)  AS total_received,
                    COALESCE(SUM(total_expected-total_received),0) AS total_variance,
                    SUM(CASE WHEN status='received' THEN 1 ELSE 0 END) AS fully_received,
                    SUM(CASE WHEN status IN ('partially_received','discrepancy') THEN 1 ELSE 0 END) AS with_variance
             FROM warehouse_receiving wr WHERE {$wc}
               AND wr.status NOT IN ('cancelled','final_rejected')",
            $params
        ) ?? [];
    }

    // ──────────────────────────────────────────────────────
    // 3. TRANSFER REPORT
    // ──────────────────────────────────────────────────────

    public function getTransferReport(int $warehouseId, array $f = [], int $page = 1, int $perPage = 25): array
    {
        $where  = ['wt.warehouse_id = :wid'];
        $params = ['wid' => $warehouseId];

        if (!empty($f['shop_id']))    { $where[] = 'wt.shop_id = :sid';     $params['sid']    = (int)$f['shop_id']; }
        if (!empty($f['status']))     { $where[] = 'wt.status = :status';   $params['status'] = $f['status']; }
        if (!empty($f['date_from']))  { $where[] = 'wt.transfer_date >= :dfrom'; $params['dfrom'] = $f['date_from']; }
        if (!empty($f['date_to']))    { $where[] = 'wt.transfer_date <= :dto';   $params['dto']   = $f['date_to']; }
        if (!empty($f['search']))     { $where[] = 'wt.transfer_number LIKE :q'; $params['q']   = '%'.$f['search'].'%'; }
        if (!empty($f['product_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM warehouse_transfer_items wti WHERE wti.transfer_id=wt.id AND wti.product_id=:pid)';
            $params['pid'] = (int)$f['product_id'];
        }

        $wc     = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        $total  = (int)($this->rawQueryOne("SELECT COUNT(*) AS c FROM warehouse_transfers wt WHERE {$wc}", $params)['c'] ?? 0);
        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $data = $this->rawQuery(
            "SELECT wt.*,
                w.name AS warehouse_name,
                s.name AS shop_name,
                CONCAT(ui.first_name,' ',ui.last_name) AS initiated_by_name,
                CONCAT(ur.first_name,' ',ur.last_name) AS received_by_name
             FROM warehouse_transfers wt
             LEFT JOIN warehouses w  ON w.id  = wt.warehouse_id
             LEFT JOIN shops      s  ON s.id  = wt.shop_id
             LEFT JOIN users      ui ON ui.id = wt.initiated_by
             LEFT JOIN users      ur ON ur.id = wt.received_by
             WHERE {$wc}
             ORDER BY wt.transfer_date DESC, wt.id DESC
             LIMIT :limit OFFSET :offset",
            $params
        );

        return ['data'=>$data,'total'=>$total,'page'=>$page,'perPage'=>$perPage,
                'totalPages'=>(int)ceil($total/max(1,$perPage)),
                'from'=>$total>0?$offset+1:0,'to'=>min($offset+$perPage,$total)];
    }

    public function getTransferItems(int $transferId): array
    {
        return $this->rawQuery(
            "SELECT wti.*, p.name AS product_name, p.sku, u.abbreviation AS unit_abbr
             FROM warehouse_transfer_items wti
             JOIN products p ON p.id = wti.product_id
             LEFT JOIN units_of_measure u ON u.id = wti.unit_id
             WHERE wti.transfer_id = :tid ORDER BY wti.id",
            ['tid' => $transferId]
        );
    }

    public function getTransferSummary(int $warehouseId, string $dateFrom='', string $dateTo=''): array
    {
        $where  = ['wt.warehouse_id = :wid'];
        $params = ['wid' => $warehouseId];
        if ($dateFrom) { $where[] = 'wt.transfer_date >= :dfrom'; $params['dfrom'] = $dateFrom; }
        if ($dateTo)   { $where[] = 'wt.transfer_date <= :dto';   $params['dto']   = $dateTo; }
        $wc = implode(' AND ', $where);
        return $this->rawQueryOne(
            "SELECT COUNT(*) AS total_transfers,
                    COALESCE(SUM(total_quantity),0) AS total_qty,
                    SUM(CASE WHEN status='received' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN status IN ('pending','approved','in_transit') THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled
             FROM warehouse_transfers wt WHERE {$wc}",
            $params
        ) ?? [];
    }

    // ──────────────────────────────────────────────────────
    // 4. DISCREPANCY REPORT
    // ──────────────────────────────────────────────────────

    public function getDiscrepancyReport(int $warehouseId, array $f = [], int $page = 1, int $perPage = 25): array
    {
        $where  = ['wd.warehouse_id = :wid'];
        $params = ['wid' => $warehouseId];

        if (!empty($f['status']))    { $where[] = 'wd.status = :status';   $params['status'] = $f['status']; }
        if (!empty($f['product_id'])){ $where[] = 'wd.product_id = :pid';  $params['pid']    = (int)$f['product_id']; }
        if (!empty($f['date_from'])) { $where[] = 'DATE(wd.reported_at) >= :dfrom'; $params['dfrom'] = $f['date_from']; }
        if (!empty($f['date_to']))   { $where[] = 'DATE(wd.reported_at) <= :dto';   $params['dto']   = $f['date_to']; }
        if (!empty($f['search']))    { $where[] = "(wd.discrepancy_number LIKE :q OR p.name LIKE :q2)"; $params['q'] = '%'.$f['search'].'%'; $params['q2'] = '%'.$f['search'].'%'; }
        if (!empty($f['type']))      { $where[] = 'wd.discrepancy_type = :type'; $params['type'] = $f['type']; }

        $wc     = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        $total  = (int)($this->rawQueryOne(
            "SELECT COUNT(*) AS c FROM warehouse_discrepancies wd JOIN products p ON p.id=wd.product_id WHERE {$wc}", $params
        )['c'] ?? 0);
        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $data = $this->rawQuery(
            "SELECT wd.*,
                p.name AS product_name, p.sku,
                f.name AS farm_name,
                s.name AS shop_name,
                CONCAT(rp.first_name,' ',rp.last_name) AS reported_by_name,
                CONCAT(rv.first_name,' ',rv.last_name) AS resolved_by_name
             FROM warehouse_discrepancies wd
             JOIN products p ON p.id = wd.product_id
             LEFT JOIN farms  f  ON f.id  = wd.farm_id
             LEFT JOIN shops  s  ON s.id  = wd.shop_id
             LEFT JOIN users  rp ON rp.id = wd.reported_by
             LEFT JOIN users  rv ON rv.id = wd.resolved_by
             WHERE {$wc}
             ORDER BY wd.reported_at DESC
             LIMIT :limit OFFSET :offset",
            $params
        );

        return ['data'=>$data,'total'=>$total,'page'=>$page,'perPage'=>$perPage,
                'totalPages'=>(int)ceil($total/max(1,$perPage)),
                'from'=>$total>0?$offset+1:0,'to'=>min($offset+$perPage,$total)];
    }

    public function getDiscrepancySummary(int $warehouseId): array
    {
        return $this->rawQueryOne(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status='open' THEN 1 ELSE 0 END) AS open_count,
                    SUM(CASE WHEN status='under_review' THEN 1 ELSE 0 END) AS under_review,
                    SUM(CASE WHEN status='resolved' THEN 1 ELSE 0 END) AS resolved,
                    COALESCE(SUM(ABS(variance_quantity)),0) AS total_variance
             FROM warehouse_discrepancies WHERE warehouse_id = :wid",
            ['wid' => $warehouseId]
        ) ?? [];
    }

    // ──────────────────────────────────────────────────────
    // 5. EXPIRY REPORT
    // ──────────────────────────────────────────────────────

    public function getExpiryReport(int $warehouseId, array $f = []): array
    {
        $where  = ['wi.warehouse_id = :wid', 'wi.expiry_date IS NOT NULL', 'wi.current_quantity > 0'];
        $params = ['wid' => $warehouseId];

        if (!empty($f['product_id'])) { $where[] = 'wi.product_id = :pid'; $params['pid'] = (int)$f['product_id']; }
        if (!empty($f['batch']))      { $where[] = 'wi.batch_number LIKE :bn'; $params['bn'] = '%'.$f['batch'].'%'; }
        if (!empty($f['status'])) {
            switch ($f['status']) {
                case 'expired':       $where[] = 'wi.expiry_date < CURDATE()'; break;
                case 'expiring_soon': $where[] = 'wi.expiry_date >= CURDATE() AND wi.expiry_date <= DATE_ADD(CURDATE(),INTERVAL 30 DAY)'; break;
                case 'normal':        $where[] = 'wi.expiry_date > DATE_ADD(CURDATE(),INTERVAL 30 DAY)'; break;
            }
        }

        $wc = implode(' AND ', $where);
        return $this->rawQuery(
            "SELECT
                wi.*,
                p.name         AS product_name,
                p.sku          AS product_code,
                p.is_perishable,
                u.abbreviation AS unit_abbr,
                DATEDIFF(wi.expiry_date, CURDATE()) AS days_remaining
             FROM warehouse_inventory wi
             JOIN products p ON p.id = wi.product_id
             LEFT JOIN units_of_measure u ON u.id = wi.unit_id
             WHERE {$wc}
             ORDER BY wi.expiry_date ASC",
            $params
        );
    }

    // ──────────────────────────────────────────────────────
    // DROPDOWN HELPERS
    // ──────────────────────────────────────────────────────

    public function getWarehouseProducts(int $warehouseId): array
    {
        return $this->rawQuery(
            "SELECT DISTINCT p.id, p.name FROM warehouse_inventory wi
             JOIN products p ON p.id = wi.product_id
             WHERE wi.warehouse_id = :wid ORDER BY p.name",
            ['wid' => $warehouseId]
        );
    }

    public function getWarehouseFarms(int $warehouseId): array
    {
        return $this->rawQuery(
            "SELECT DISTINCT f.id, f.name FROM warehouse_receiving wr
             JOIN farms f ON f.id = wr.farm_id
             WHERE wr.warehouse_id = :wid ORDER BY f.name",
            ['wid' => $warehouseId]
        );
    }

    public function getWarehouseShops(int $warehouseId): array
    {
        return $this->rawQuery(
            "SELECT DISTINCT s.id, s.name FROM warehouse_transfers wt
             JOIN shops s ON s.id = wt.shop_id
             WHERE wt.warehouse_id = :wid ORDER BY s.name",
            ['wid' => $warehouseId]
        );
    }
}
