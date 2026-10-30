<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Seed Distribution Module
 * =========================================================
 * File    : modules/seed_distribution/services/SeedDistributionService.php
 * Purpose : Business logic for seed distribution.
 *           - Authorization checks (warehouse capability + role)
 *           - Stock validation (no negative stock)
 *           - Inventory deduction via existing WarehouseInventoryModel
 *           - Movement logging via existing StockMovementModel
 *           - Audit trail via existing ActivityLogModel
 *           - Reference number generation
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\SeedDistribution\Services;

use AAM\SeedDistribution\Models\SeedDistributionModel;
use AAM\SeedDistribution\Models\SeedGradeModel;
use AAM\Warehouse\Models\WarehouseInventoryModel;
use AAM\Warehouse\Models\StockMovementModel;
use AAM\Admin\Models\ActivityLogModel;
use AAM\Core\Database;
use AAM\Core\SessionManager;

class SeedDistributionService
{
    private SeedDistributionModel  $model;
    private SeedGradeModel         $gradeModel;
    private WarehouseInventoryModel $invModel;
    private StockMovementModel     $movModel;
    private ActivityLogModel       $audit;
    private Database               $db;

    public function __construct()
    {
        $this->model      = new SeedDistributionModel();
        $this->gradeModel = new SeedGradeModel();
        $this->invModel   = new WarehouseInventoryModel();
        $this->movModel   = new StockMovementModel();
        $this->audit      = new ActivityLogModel();
        $this->db         = Database::getInstance();
    }

    // ── Authorization helpers ─────────────────────────────

    /**
     * Check if a warehouse has seed distribution enabled.
     */
    public function warehouseHasSeedDistribution(int $warehouseId): bool
    {
        $row = $this->db->fetchOne(
            "SELECT seed_distribution_enabled FROM warehouses WHERE id = :id AND deleted_at IS NULL AND is_active = 1",
            ['id' => $warehouseId]
        );
        return (bool)($row['seed_distribution_enabled'] ?? false);
    }

    /**
     * Get the warehouse assigned to the current session user.
     * Returns warehouse row or null.
     */
    public function getUserWarehouse(?int $userId): ?array
    {
        if (!$userId) return null;

        return $this->db->fetchOne(
            "SELECT w.*, w.seed_distribution_enabled
             FROM user_location_assignments ula
             JOIN warehouses w ON w.id = ula.location_id
             WHERE ula.user_id = :uid
               AND ula.location_type = 'warehouse'
               AND w.deleted_at IS NULL
               AND w.is_active = 1
             LIMIT 1",
            ['uid' => $userId]
        );
    }

    /**
     * Check if user is authorized for seed distribution operations.
     * Returns ['allowed' => bool, 'warehouse' => array|null, 'error' => string|null]
     */
    public function checkWarehouseAccess(?int $userId, ?int $warehouseId = null): array
    {
        $warehouse = $this->getUserWarehouse($userId);

        if (!$warehouse) {
            return ['allowed' => false, 'warehouse' => null, 'error' => 'You are not assigned to any active warehouse.'];
        }

        if ($warehouseId && (int)$warehouse['id'] !== $warehouseId) {
            return ['allowed' => false, 'warehouse' => $warehouse, 'error' => 'Access denied for this warehouse.'];
        }

        if (!$warehouse['seed_distribution_enabled']) {
            return ['allowed' => false, 'warehouse' => $warehouse, 'error' => 'Seed Distribution is not enabled for this warehouse.'];
        }

        return ['allowed' => true, 'warehouse' => $warehouse, 'error' => null];
    }

    // ── Seed products for a warehouse ────────────────────

    /**
     * Get available seed products from existing warehouse inventory.
     * Returns products flagged as is_seed with their stock and GM price.
     */
    public function getSeedStock(int $warehouseId): array
    {
        return $this->db->fetchAll(
            "SELECT wi.*,
                    p.id            AS product_id,
                    p.name          AS product_name,
                    p.sku           AS product_sku,
                    p.is_seed,
                    p.unit_price,
                    u.abbreviation  AS unit_abbr,
                    u.name          AS unit_name
             FROM warehouse_inventory wi
             JOIN products p ON p.id = wi.product_id AND p.is_seed = 1 AND p.is_active = 1
             LEFT JOIN units_of_measure u ON u.id = wi.unit_id
             WHERE wi.warehouse_id = :wid
             ORDER BY p.name ASC",
            ['wid' => $warehouseId]
        );
    }

    /**
     * Get seed products with grades, for all warehouses (GM view).
     */
    public function getSeedStockAllWarehouses(array $filters = []): array
    {
        $where  = ['p.is_seed = 1', 'p.is_active = 1'];
        $params = [];

        if (!empty($filters['warehouse_id'])) {
            $where[]      = 'wi.warehouse_id = :wid';
            $params['wid'] = (int)$filters['warehouse_id'];
        }
        if (!empty($filters['product_id'])) {
            $where[]       = 'wi.product_id = :pid';
            $params['pid'] = (int)$filters['product_id'];
        }

        $whereClause = implode(' AND ', $where);

        return $this->db->fetchAll(
            "SELECT wi.*,
                    p.id            AS product_id,
                    p.name          AS product_name,
                    p.sku           AS product_sku,
                    w.name          AS warehouse_name,
                    COALESCE(w.warehouse_code, CONCAT('WH-',LPAD(w.id,3,'0'))) AS warehouse_code,
                    w.seed_distribution_enabled,
                    u.abbreviation  AS unit_abbr
             FROM warehouse_inventory wi
             JOIN products p  ON p.id = wi.product_id
             JOIN warehouses w ON w.id = wi.warehouse_id AND w.deleted_at IS NULL
             LEFT JOIN units_of_measure u ON u.id = wi.unit_id
             WHERE {$whereClause}
             ORDER BY w.name ASC, p.name ASC",
            $params
        );
    }

    // ── Create distribution ───────────────────────────────

    public function createDistribution(array $data, int $userId): array
    {
        $warehouseId = (int)($data['warehouse_id'] ?? 0);
        $productId   = (int)($data['product_id']   ?? 0);
        $quantity    = (float)($data['quantity']    ?? 0);

        // Validate warehouse seed capability
        if (!$this->warehouseHasSeedDistribution($warehouseId)) {
            return ['success' => false, 'error' => 'Seed Distribution is not enabled for this warehouse.'];
        }

        // Validate product is a seed product
        $product = $this->db->fetchOne(
            "SELECT * FROM products WHERE id = :id AND is_seed = 1 AND is_active = 1 AND deleted_at IS NULL",
            ['id' => $productId]
        );
        if (!$product) {
            return ['success' => false, 'error' => 'Invalid or inactive seed product.'];
        }

        // ── Price validation (REQUIRED before distribution) ──────────────────
        // The unit_price is the authoritative GM-configured price from products.unit_price.
        // Warehouse Manager cannot override this — it is always fetched from the DB.
        $priceInfo = $this->getPriceForProduct($productId);
        if (!$priceInfo['has_price']) {
            return [
                'success' => false,
                'error'   => 'Price has not been configured for this seed. Please contact the General Manager.',
            ];
        }
        $unitPrice   = $priceInfo['unit_price'];   // snapshot
        $totalAmount = round($quantity * $unitPrice, 2);

        // Validate quantity
        if ($quantity <= 0) {
            return ['success' => false, 'error' => 'Quantity must be greater than zero.'];
        }

        // Validate recipient name required
        if (empty(trim($data['recipient_name'] ?? ''))) {
            return ['success' => false, 'error' => 'Recipient / farmer name is required.'];
        }

        // Validate distribution date
        if (empty($data['distribution_date'])) {
            return ['success' => false, 'error' => 'Distribution date is required.'];
        }

        // Generate reference number
        $distNumber = $this->model->generateNumber();

        $insert = [
            'distribution_number'    => $distNumber,
            'warehouse_id'           => $warehouseId,
            'product_id'             => $productId,
            'seed_grade_id'          => !empty($data['seed_grade_id']) ? (int)$data['seed_grade_id'] : null,
            'unit_id'                => !empty($data['unit_id']) ? (int)$data['unit_id'] : null,
            'distribution_type'      => $data['distribution_type'] ?? 'warehouse_pickup',
            'quantity'               => $quantity,
            'unit_price'             => $unitPrice,   // price snapshot
            'total_amount'           => $totalAmount, // pre-computed total
            'status'                 => SeedDistributionModel::STATUS_PENDING,
            'destination_location'   => trim($data['destination_location'] ?? ''),
            'recipient_name'         => trim($data['recipient_name']),
            'recipient_phone'        => trim($data['recipient_phone'] ?? ''),
            'recipient_organization' => trim($data['recipient_organization'] ?? ''),
            'recipient_notes'        => trim($data['recipient_notes'] ?? ''),
            'program_name'           => trim($data['program_name'] ?? ''),
            'distribution_date'      => $data['distribution_date'],
            'notes'                  => trim($data['notes'] ?? ''),
            'created_by'             => $userId,
        ];

        $id = $this->model->create($insert);
        if (!$id) {
            return ['success' => false, 'error' => 'Failed to save distribution record.'];
        }

        // Audit trail
        $this->audit->log(
            'seed_distribution.created',
            "Seed distribution {$distNumber} created for {$product['name']} — qty {$quantity} × ETB {$unitPrice} = ETB {$totalAmount}",
            'seed_distributions', $id, $userId
        );
        $this->audit->logBusiness([
            'action'       => 'seed_distribution.created',
            'module'       => 'seed_distribution',
            'entity_type'  => 'seed_distribution',
            'entity_label' => "{$distNumber} — {$product['name']}",
            'record_id'    => $id,
            'description'  => "Seed distribution {$distNumber} submitted for approval: {$product['name']} qty={$quantity} × ETB {$unitPrice} = ETB {$totalAmount}",
            'warehouse_id' => $warehouseId,
            'new_values'   => $insert,
        ]);

        return ['success' => true, 'id' => $id, 'distribution_number' => $distNumber];
    }

    // ── Approve ───────────────────────────────────────────

    public function approve(int $id, int $gmUserId): array
    {
        $dist = $this->model->getByIdWithRelations($id);
        if (!$dist) return ['success' => false, 'error' => 'Distribution not found.'];
        if ($dist['status'] !== SeedDistributionModel::STATUS_PENDING) {
            return ['success' => false, 'error' => 'Only pending distributions can be approved.'];
        }

        // Stock check at approval time
        $stock = $this->invModel->getStock((int)$dist['warehouse_id'], (int)$dist['product_id']);
        if (!$stock || (float)$stock['available_quantity'] < (float)$dist['quantity']) {
            $avail = $stock ? number_format((float)$stock['available_quantity'], 3) : '0';
            return ['success' => false, 'error' => "Insufficient stock. Available: {$avail}, Requested: {$dist['quantity']}."];
        }

        $ok = $this->model->approve($id, $gmUserId);
        if (!$ok) return ['success' => false, 'error' => 'Approval failed. Record may have changed.'];

        $this->audit->log(
            'seed_distribution.approved',
            "Seed distribution {$dist['distribution_number']} approved",
            'seed_distributions', $id, $gmUserId
        );
        $this->audit->logBusiness([
            'action'       => 'seed_distribution.approved',
            'module'       => 'seed_distribution',
            'entity_type'  => 'seed_distribution',
            'entity_label' => $dist['distribution_number'],
            'record_id'    => $id,
            'description'  => "Distribution {$dist['distribution_number']} approved by GM",
            'warehouse_id' => (int)$dist['warehouse_id'],
        ]);

        return ['success' => true];
    }

    // ── Reject ────────────────────────────────────────────

    public function reject(int $id, int $gmUserId, string $reason): array
    {
        $dist = $this->model->getByIdWithRelations($id);
        if (!$dist) return ['success' => false, 'error' => 'Distribution not found.'];
        if ($dist['status'] !== SeedDistributionModel::STATUS_PENDING) {
            return ['success' => false, 'error' => 'Only pending distributions can be rejected.'];
        }

        $ok = $this->model->reject($id, $gmUserId, $reason);
        if (!$ok) return ['success' => false, 'error' => 'Rejection failed.'];

        $this->audit->logBusiness([
            'action'       => 'seed_distribution.rejected',
            'module'       => 'seed_distribution',
            'entity_type'  => 'seed_distribution',
            'entity_label' => $dist['distribution_number'],
            'record_id'    => $id,
            'description'  => "Distribution {$dist['distribution_number']} rejected: {$reason}",
            'warehouse_id' => (int)$dist['warehouse_id'],
        ]);

        return ['success' => true];
    }

    // ── Release (deduct stock) ────────────────────────────

    public function release(int $id, int $wmUserId): array
    {
        $dist = $this->model->getByIdWithRelations($id);
        if (!$dist) return ['success' => false, 'error' => 'Distribution not found.'];
        if ($dist['status'] !== SeedDistributionModel::STATUS_APPROVED) {
            return ['success' => false, 'error' => 'Only approved distributions can be released.'];
        }

        $warehouseId = (int)$dist['warehouse_id'];
        $productId   = (int)$dist['product_id'];
        $qty         = (float)$dist['quantity'];

        // Final stock validation
        $stock = $this->invModel->getStock($warehouseId, $productId);
        if (!$stock || (float)$stock['available_quantity'] < $qty) {
            $avail = $stock ? number_format((float)$stock['available_quantity'], 3) : '0';
            return ['success' => false, 'error' => "Insufficient stock. Available: {$avail}, Requested: {$qty}."];
        }

        // Execute in a transaction
        try {
            $this->db->beginTransaction();

            // Deduct stock using existing WarehouseInventoryModel
            $change = $this->invModel->decreaseStock($warehouseId, $productId, $qty, 'seed_distribution');

            // Log movement using existing StockMovementModel
            $movementId = $this->movModel->log([
                'movement_type'   => 'seed_distribution',
                'location_type'   => 'warehouse',
                'location_id'     => $warehouseId,
                'product_id'      => $productId,
                'batch_number'    => $stock['batch_number'] ?? null,
                'quantity_before' => $change['before'],
                'quantity_in'     => 0,
                'quantity_out'    => $qty,
                'quantity_after'  => $change['after'],
                'reference_type'  => 'seed_distributions',
                'reference_id'    => $id,
                'reference_number'=> $dist['distribution_number'],
                'performed_by'    => $wmUserId,
                'notes'           => "Seed distribution to: " . ($dist['recipient_name'] ?? 'Unknown'),
            ]);

            // Update distribution status
            $this->model->release($id, $wmUserId, $movementId, $change['before'], $change['after']);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => 'Release failed: ' . $e->getMessage()];
        }

        // Audit
        $this->audit->logBusiness([
            'action'       => 'seed_distribution.released',
            'module'       => 'seed_distribution',
            'entity_type'  => 'seed_distribution',
            'entity_label' => $dist['distribution_number'],
            'record_id'    => $id,
            'description'  => "Seed distribution {$dist['distribution_number']} released — stock deducted by {$qty}",
            'warehouse_id' => $warehouseId,
            'old_values'   => ['available_quantity' => $change['before']],
            'new_values'   => ['available_quantity' => $change['after']],
        ]);

        return ['success' => true];
    }

    // ── Cancel ────────────────────────────────────────────

    public function cancel(int $id, int $userId): array
    {
        $dist = $this->model->getByIdWithRelations($id);
        if (!$dist) return ['success' => false, 'error' => 'Distribution not found.'];
        if (!in_array($dist['status'], [SeedDistributionModel::STATUS_PENDING, SeedDistributionModel::STATUS_APPROVED], true)) {
            return ['success' => false, 'error' => 'Only pending or approved distributions can be cancelled.'];
        }

        $this->model->cancel($id, $userId);

        $this->audit->logBusiness([
            'action'       => 'seed_distribution.cancelled',
            'module'       => 'seed_distribution',
            'entity_type'  => 'seed_distribution',
            'entity_label' => $dist['distribution_number'],
            'record_id'    => $id,
            'description'  => "Seed distribution {$dist['distribution_number']} cancelled",
            'warehouse_id' => (int)$dist['warehouse_id'],
        ]);

        return ['success' => true];
    }

    // ── Mark Complete ─────────────────────────────────────

    public function complete(int $id, int $userId): array
    {
        $dist = $this->model->getByIdWithRelations($id);
        if (!$dist) return ['success' => false, 'error' => 'Distribution not found.'];
        if ($dist['status'] !== SeedDistributionModel::STATUS_RELEASED) {
            return ['success' => false, 'error' => 'Only released distributions can be marked as completed.'];
        }

        $ok = $this->model->complete($id);
        if (!$ok) return ['success' => false, 'error' => 'Could not update status. Record may have changed.'];

        $this->audit->log(
            'seed_distribution.completed',
            "Seed distribution {$dist['distribution_number']} marked as completed",
            'seed_distributions', $id, $userId
        );
        $this->audit->logBusiness([
            'action'       => 'seed_distribution.completed',
            'module'       => 'seed_distribution',
            'entity_type'  => 'seed_distribution',
            'entity_label' => $dist['distribution_number'],
            'record_id'    => $id,
            'description'  => "Distribution {$dist['distribution_number']} completed — seed delivered to {$dist['recipient_name']}",
            'warehouse_id' => (int)$dist['warehouse_id'],
        ]);

        return ['success' => true];
    }

    // ── Seed products list ────────────────────────────────

    public function getAllSeedProducts(): array
    {
        return $this->db->fetchAll(
            "SELECT p.id, p.name, p.sku, p.is_active,
                    p.unit_price,
                    u.abbreviation AS unit_abbr,
                    u.name         AS unit_name
             FROM products p
             LEFT JOIN units_of_measure u ON u.id = p.unit_id
             WHERE p.is_seed = 1 AND p.deleted_at IS NULL
             ORDER BY p.name ASC"
        );
    }

    // ── Price lookup for a seed product ──────────────────

    /**
     * Get the current GM-configured price for a seed product.
     * Returns ['has_price' => bool, 'unit_price' => float, 'unit_abbr' => string]
     * The price lives in products.unit_price — set by GMPricingController.
     */
    public function getPriceForProduct(int $productId): array
    {
        $row = $this->db->fetchOne(
            "SELECT p.unit_price,
                    u.abbreviation AS unit_abbr,
                    u.name         AS unit_name
             FROM products p
             LEFT JOIN units_of_measure u ON u.id = p.unit_id
             WHERE p.id = :pid AND p.is_seed = 1 AND p.is_active = 1 AND p.deleted_at IS NULL",
            ['pid' => $productId]
        );

        if (!$row) {
            return ['has_price' => false, 'unit_price' => 0.0, 'unit_abbr' => ''];
        }

        $price = (float)($row['unit_price'] ?? 0);
        return [
            'has_price'  => $price > 0,
            'unit_price' => $price,
            'unit_abbr'  => $row['unit_abbr'] ?? '',
            'unit_name'  => $row['unit_name'] ?? '',
        ];
    }

    // ── Grade management ──────────────────────────────────

    public function createGrade(array $data, int $userId): array
    {
        $productId = (int)($data['product_id'] ?? 0);
        $name      = trim($data['grade_name'] ?? '');
        $code      = strtoupper(trim(str_replace(' ', '_', $data['grade_code'] ?? $name)));

        if (!$productId)  return ['success' => false, 'error' => 'Product is required.'];
        if ($name === '')  return ['success' => false, 'error' => 'Grade name is required.'];
        if ($this->gradeModel->gradeCodeExists($productId, $code)) {
            return ['success' => false, 'error' => 'A grade with this code already exists for this product.'];
        }

        $id = $this->gradeModel->create([
            'product_id'    => $productId,
            'grade_name'    => $name,
            'grade_code'    => $code,
            'description'   => trim($data['description'] ?? ''),
            'display_order' => (int)($data['display_order'] ?? 0),
            'is_active'     => 1,
            'created_by'    => $userId,
        ]);

        if (!$id) return ['success' => false, 'error' => 'Failed to save grade.'];

        $this->audit->log('seed_grade.created', "Seed grade '{$name}' created for product #{$productId}", 'seed_grades', $id, $userId);
        return ['success' => true, 'id' => $id];
    }
}
