<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 10
 * =========================================================
 * File    : modules/warehouse/models/WarehouseInventoryModel.php
 * Purpose : Data access for warehouse_inventory.
 *           Provides stock queries, FEFO-aware lookups,
 *           and atomic inventory mutation methods.
 *
 * RULES:
 *   RULE 1: current_quantity >= 0 always.
 *   RULE 2: available_quantity = current - reserved.
 *   RULE 5: Every movement is logged in stock_movement_log.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Warehouse\Models;

use AAM\Core\BaseModel;

class WarehouseInventoryModel extends BaseModel
{
    protected string $table      = 'warehouse_inventory';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = false;

    protected array $fillable = [
        'warehouse_id', 'product_id', 'batch_number',
        'current_quantity', 'reserved_quantity', 'available_quantity',
        'unit_id', 'min_stock_level',
        'production_date', 'expiry_date',
        'last_movement_at', 'last_movement_type',
    ];

    // ── Status label helpers ──────────────────────────────
    public static function stockStatusLabel(
        float $available,
        float $minLevel,
        ?string $expiryDate
    ): array {
        // Expired
        if ($expiryDate && strtotime($expiryDate) < time()) {
            return ['EXPIRED', 'danger'];
        }
        // Expiring soon (within 7 days)
        if ($expiryDate && strtotime($expiryDate) <= strtotime('+7 days')) {
            return ['EXPIRING SOON', 'warning'];
        }
        if ($available <= 0)           return ['OUT OF STOCK', 'danger'];
        if ($minLevel > 0 && $available <= $minLevel) return ['LOW STOCK', 'warning'];
        return ['IN STOCK', 'success'];
    }

    // ──────────────────────────────────────────────────────
    // READ — inventory list
    // ──────────────────────────────────────────────────────

    /**
     * Paginated inventory list for a warehouse with filters.
     */
    public function paginateWarehouse(
        int   $warehouseId,
        int   $page       = 1,
        int   $perPage    = PAGINATION_DEFAULT,
        array $filters    = []
    ): array {
        $page    = max(1, $page);
        $perPage = min($perPage, PAGINATION_MAX);
        $offset  = ($page - 1) * $perPage;

        [$where, $params] = $this->buildFilters($warehouseId, $filters);

        $total = (int)($this->rawQueryOne(
            "SELECT COUNT(*) AS c
             FROM warehouse_inventory wi
             JOIN products p ON p.id = wi.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE {$where}",
            $params
        )['c'] ?? 0);

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $data = $this->rawQuery(
            "SELECT
                wi.*,
                p.name          AS product_name,
                p.sku           AS product_sku,
                p.is_perishable,
                p.min_stock_level AS product_min_stock,
                c.name          AS category_name,
                u.abbreviation  AS unit_abbr,
                u.name          AS unit_name
             FROM warehouse_inventory wi
             JOIN products p ON p.id = wi.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN units_of_measure u ON u.id = wi.unit_id
             WHERE {$where}
             ORDER BY
                CASE WHEN wi.expiry_date IS NOT NULL AND wi.expiry_date <= CURDATE() THEN 0
                     WHEN wi.expiry_date IS NOT NULL AND wi.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1
                     WHEN wi.available_quantity = 0 THEN 2
                     WHEN wi.min_stock_level > 0 AND wi.available_quantity <= wi.min_stock_level THEN 3
                     ELSE 4 END ASC,
                p.name ASC
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

    /**
     * Get stock for a specific product in a warehouse.
     */
    public function getStock(int $warehouseId, int $productId): ?array
    {
        return $this->rawQueryOne(
            "SELECT wi.*,
                    p.name AS product_name, p.sku,
                    u.abbreviation AS unit_abbr
             FROM warehouse_inventory wi
             JOIN products p ON p.id = wi.product_id
             LEFT JOIN units_of_measure u ON u.id = wi.unit_id
             WHERE wi.warehouse_id = :wid AND wi.product_id = :pid
             LIMIT 1",
            ['wid' => $warehouseId, 'pid' => $productId]
        );
    }

    /**
     * All products with stock in a warehouse (for transfer dropdowns).
     */
    public function getAvailableProducts(int $warehouseId): array
    {
        return $this->rawQuery(
            "SELECT wi.product_id, wi.available_quantity, wi.batch_number,
                    wi.expiry_date, wi.unit_id,
                    p.name AS product_name, p.sku,
                    u.abbreviation AS unit_abbr
             FROM warehouse_inventory wi
             JOIN products p ON p.id = wi.product_id
             LEFT JOIN units_of_measure u ON u.id = wi.unit_id
             WHERE wi.warehouse_id = :wid
               AND wi.available_quantity > 0
               AND (wi.expiry_date IS NULL OR wi.expiry_date > CURDATE())
             ORDER BY p.name ASC",
            ['wid' => $warehouseId]
        );
    }

    /**
     * Dashboard KPI aggregates.
     */
    public function getDashboardKpis(int $warehouseId): array
    {
        $row = $this->rawQueryOne(
            "SELECT
                COUNT(*) AS total_products,
                COALESCE(SUM(current_quantity), 0) AS total_stock,
                COALESCE(SUM(available_quantity), 0) AS available_stock,
                COALESCE(SUM(reserved_quantity), 0) AS reserved_stock,
                SUM(CASE WHEN available_quantity <= 0 THEN 1 ELSE 0 END) AS out_of_stock_count,
                SUM(CASE WHEN min_stock_level > 0
                          AND available_quantity > 0
                          AND available_quantity <= min_stock_level THEN 1 ELSE 0 END) AS low_stock_count,
                SUM(CASE WHEN expiry_date IS NOT NULL
                          AND expiry_date <= CURDATE() THEN 1 ELSE 0 END) AS expired_count,
                SUM(CASE WHEN expiry_date IS NOT NULL
                          AND expiry_date > CURDATE()
                          AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS expiring_soon_count
             FROM warehouse_inventory
             WHERE warehouse_id = :wid",
            ['wid' => $warehouseId]
        );
        return $row ?? [];
    }

    /**
     * Products expiring within $days (for alerts).
     */
    public function getExpiringSoon(int $warehouseId, int $days = 7): array
    {
        return $this->rawQuery(
            "SELECT wi.*, p.name AS product_name, u.abbreviation AS unit_abbr
             FROM warehouse_inventory wi
             JOIN products p ON p.id = wi.product_id
             LEFT JOIN units_of_measure u ON u.id = wi.unit_id
             WHERE wi.warehouse_id = :wid
               AND wi.expiry_date IS NOT NULL
               AND wi.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
               AND wi.current_quantity > 0
             ORDER BY wi.expiry_date ASC",
            ['wid' => $warehouseId, 'days' => $days]
        );
    }

    /**
     * Low stock products.
     */
    public function getLowStock(int $warehouseId): array
    {
        return $this->rawQuery(
            "SELECT wi.*, p.name AS product_name, u.abbreviation AS unit_abbr
             FROM warehouse_inventory wi
             JOIN products p ON p.id = wi.product_id
             LEFT JOIN units_of_measure u ON u.id = wi.unit_id
             WHERE wi.warehouse_id = :wid
               AND wi.min_stock_level > 0
               AND wi.available_quantity <= wi.min_stock_level
               AND wi.available_quantity > 0
             ORDER BY (wi.available_quantity / wi.min_stock_level) ASC",
            ['wid' => $warehouseId]
        );
    }

    // ──────────────────────────────────────────────────────
    // WRITE — stock mutations (called inside transactions)
    // ──────────────────────────────────────────────────────

    /**
     * Increase warehouse stock (called when receiving is confirmed).
     * Creates the row if it doesn't exist (upsert).
     */
    public function increaseStock(
        int     $warehouseId,
        int     $productId,
        float   $qty,
        ?int    $unitId       = null,
        ?string $batchNumber  = null,
        ?string $productionDate = null,
        ?string $expiryDate   = null,
        string  $movementType = 'receiving'
    ): array {
        $existing = $this->rawQueryOne(
            "SELECT * FROM warehouse_inventory
             WHERE warehouse_id = :wid AND product_id = :pid",
            ['wid' => $warehouseId, 'pid' => $productId]
        );

        $before = (float)($existing['current_quantity'] ?? 0);
        $after  = $before + $qty;

        if ($existing) {
            $this->rawExecute(
                "UPDATE warehouse_inventory
                 SET current_quantity   = current_quantity + :qty,
                     available_quantity = available_quantity + :qty2,
                     last_movement_at   = NOW(),
                     last_movement_type = :mtype,
                     unit_id            = COALESCE(:uid, unit_id),
                     batch_number       = COALESCE(:bn, batch_number),
                     production_date    = COALESCE(:pd, production_date),
                     expiry_date        = COALESCE(:ed, expiry_date),
                     updated_at         = NOW()
                 WHERE warehouse_id = :wid AND product_id = :pid",
                [
                    'qty'   => $qty,
                    'qty2'  => $qty,
                    'mtype' => $movementType,
                    'uid'   => $unitId,
                    'bn'    => $batchNumber,
                    'pd'    => $productionDate,
                    'ed'    => $expiryDate,
                    'wid'   => $warehouseId,
                    'pid'   => $productId,
                ]
            );
        } else {
            $this->rawExecute(
                "INSERT INTO warehouse_inventory
                    (warehouse_id, product_id, current_quantity, reserved_quantity,
                     available_quantity, unit_id, batch_number,
                     production_date, expiry_date,
                     last_movement_at, last_movement_type)
                 VALUES (:wid, :pid, :qty, 0, :qty2, :uid, :bn, :pd, :ed, NOW(), :mtype)",
                [
                    'wid'   => $warehouseId,
                    'pid'   => $productId,
                    'qty'   => $qty,
                    'qty2'  => $qty,
                    'uid'   => $unitId,
                    'bn'    => $batchNumber,
                    'pd'    => $productionDate,
                    'ed'    => $expiryDate,
                    'mtype' => $movementType,
                ]
            );
        }

        return ['before' => $before, 'after' => $after, 'change' => $qty];
    }

    /**
     * Decrease warehouse stock (called when transfer is dispatched).
     * Validates availability before decreasing.
     *
     * @throws \RuntimeException if insufficient stock
     */
    public function decreaseStock(
        int    $warehouseId,
        int    $productId,
        float  $qty,
        string $movementType = 'transfer_out'
    ): array {
        $existing = $this->rawQueryOne(
            "SELECT * FROM warehouse_inventory
             WHERE warehouse_id = :wid AND product_id = :pid",
            ['wid' => $warehouseId, 'pid' => $productId]
        );

        if (!$existing) {
            throw new \RuntimeException("No warehouse inventory record found for this product.");
        }

        $available = (float)$existing['available_quantity'];
        if ($qty > $available) {
            throw new \RuntimeException(
                "Insufficient warehouse stock. Available: " .
                number_format($available, 3) .
                ", Requested: " . number_format($qty, 3)
            );
        }

        $before = (float)$existing['current_quantity'];
        $after  = $before - $qty;

        $this->rawExecute(
            "UPDATE warehouse_inventory
             SET current_quantity   = current_quantity - :qty,
                 available_quantity = available_quantity - :qty2,
                 last_movement_at   = NOW(),
                 last_movement_type = :mtype,
                 updated_at         = NOW()
             WHERE warehouse_id = :wid AND product_id = :pid",
            [
                'qty'   => $qty,
                'qty2'  => $qty,
                'mtype' => $movementType,
                'wid'   => $warehouseId,
                'pid'   => $productId,
            ]
        );

        return ['before' => $before, 'after' => $after, 'change' => -$qty];
    }

    /**
     * Reserve stock for a pending transfer.
     */
    public function reserveStock(int $warehouseId, int $productId, float $qty): void
    {
        $existing = $this->rawQueryOne(
            "SELECT available_quantity FROM warehouse_inventory
             WHERE warehouse_id = :wid AND product_id = :pid",
            ['wid' => $warehouseId, 'pid' => $productId]
        );

        if (!$existing || (float)$existing['available_quantity'] < $qty) {
            throw new \RuntimeException("Insufficient stock to reserve.");
        }

        $this->rawExecute(
            "UPDATE warehouse_inventory
             SET reserved_quantity  = reserved_quantity + :qty,
                 available_quantity = available_quantity - :qty2,
                 updated_at         = NOW()
             WHERE warehouse_id = :wid AND product_id = :pid",
            ['qty' => $qty, 'qty2' => $qty, 'wid' => $warehouseId, 'pid' => $productId]
        );
    }

    /**
     * Release reservation (on transfer cancel or confirm).
     */
    public function releaseReservation(int $warehouseId, int $productId, float $qty): void
    {
        $this->rawExecute(
            "UPDATE warehouse_inventory
             SET reserved_quantity  = GREATEST(0, reserved_quantity - :qty),
                 available_quantity = available_quantity + :qty2,
                 updated_at         = NOW()
             WHERE warehouse_id = :wid AND product_id = :pid",
            ['qty' => $qty, 'qty2' => $qty, 'wid' => $warehouseId, 'pid' => $productId]
        );
    }

    // ──────────────────────────────────────────────────────
    // PRIVATE
    // ──────────────────────────────────────────────────────

    private function buildFilters(int $warehouseId, array $filters): array
    {
        $where  = ['wi.warehouse_id = :wid'];
        $params = ['wid' => $warehouseId];

        if (!empty($filters['search'])) {
            $where[]         = "(p.name LIKE :q OR p.sku LIKE :q2 OR wi.batch_number LIKE :q3)";
            $params['q']     = '%' . $filters['search'] . '%';
            $params['q2']    = '%' . $filters['search'] . '%';
            $params['q3']    = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['category_id'])) {
            $where[]          = 'p.category_id = :cat';
            $params['cat']    = (int)$filters['category_id'];
        }

        if (!empty($filters['status'])) {
            switch ($filters['status']) {
                case 'out_of_stock':
                    $where[] = 'wi.available_quantity <= 0';
                    break;
                case 'low_stock':
                    $where[] = 'wi.min_stock_level > 0 AND wi.available_quantity > 0 AND wi.available_quantity <= wi.min_stock_level';
                    break;
                case 'expiring_soon':
                    $where[] = 'wi.expiry_date IS NOT NULL AND wi.expiry_date > CURDATE() AND wi.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
                    break;
                case 'expired':
                    $where[] = 'wi.expiry_date IS NOT NULL AND wi.expiry_date < CURDATE()';
                    break;
                case 'in_stock':
                    $where[] = 'wi.available_quantity > 0';
                    break;
            }
        }

        if (!empty($filters['batch'])) {
            $where[]           = 'wi.batch_number LIKE :batch';
            $params['batch']   = '%' . $filters['batch'] . '%';
        }

        return [implode(' AND ', $where), $params];
    }
}
