<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 11
 * =========================================================
 * File    : modules/inventory/services/InventoryService.php
 * Purpose : Orchestrates inventory control operations.
 *           All multi-table writes wrapped in DB transactions.
 *           Enforces:
 *             - No negative inventory
 *             - No silent history modification
 *             - Audit trail for every business action
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Inventory\Services;

use AAM\Core\Database;
use AAM\Core\SessionManager;
use AAM\Inventory\Models\InventoryModel;
use AAM\Inventory\Models\StockAdjustmentModel;
use AAM\Inventory\Models\InventoryBatchModel;
use AAM\Warehouse\Models\StockMovementModel;
use AAM\Warehouse\Models\WarehouseInventoryModel;
use AAM\Admin\Models\ActivityLogModel;

class InventoryService
{
    private Database               $db;
    private StockAdjustmentModel   $adjModel;
    private InventoryBatchModel    $batchModel;
    private StockMovementModel     $smlModel;
    private WarehouseInventoryModel $whInvModel;
    private ActivityLogModel       $auditLog;

    public function __construct()
    {
        $this->db         = Database::getInstance();
        $this->adjModel   = new StockAdjustmentModel();
        $this->batchModel = new InventoryBatchModel();
        $this->smlModel   = new StockMovementModel();
        $this->whInvModel = new WarehouseInventoryModel();
        $this->auditLog   = new ActivityLogModel();
    }

    // =========================================================
    // STOCK ADJUSTMENT
    // =========================================================

    /**
     * Submit a stock adjustment request.
     * Does NOT apply to inventory yet — status = pending.
     *
     * @throws \RuntimeException on validation failure
     */
    public function requestAdjustment(
        string $locationType,
        int    $locationId,
        int    $productId,
        float  $physicalQuantity,
        string $reason,
        string $notes         = '',
        ?string $batchNumber  = null,
        ?int   $unitId        = null
    ): int {
        if ($physicalQuantity < 0) {
            throw new \RuntimeException('Physical quantity cannot be negative.');
        }

        $systemQty = $this->getCurrentQty($locationType, $locationId, $productId);
        $variance  = $physicalQuantity - $systemQty;

        $userId    = (int)SessionManager::userId();
        $adjNumber = $this->adjModel->generateNumber();

        $adjId = $this->db->transaction(function () use (
            $locationType, $locationId, $productId, $physicalQuantity,
            $systemQty, $variance, $reason, $notes, $batchNumber, $unitId,
            $userId, $adjNumber
        ): int {
            $this->db->execute(
                "INSERT INTO stock_adjustments
                    (adjustment_number, location_type, location_id, product_id,
                     batch_number, unit_id, system_quantity, physical_quantity,
                     variance, reason, notes, status, requested_by)
                 VALUES
                    (:num, :lt, :lid, :pid, :bn, :uid, :sq, :pq, :var, :reason, :notes, 'pending', :by)",
                [
                    'num'    => $adjNumber,
                    'lt'     => $locationType,
                    'lid'    => $locationId,
                    'pid'    => $productId,
                    'bn'     => $batchNumber,
                    'uid'    => $unitId,
                    'sq'     => $systemQty,
                    'pq'     => $physicalQuantity,
                    'var'    => $variance,
                    'reason' => $reason,
                    'notes'  => $notes ?: null,
                    'by'     => $userId,
                ]
            );
            $adjId = $this->db->lastInsertId();

            $productName   = $this->getProductName($productId);
            $locationIdKey = $locationType . '_id';
            $this->auditLog->logBusiness([
                'action'        => ActivityLogModel::ACTION_STOCK_ADJUSTED,
                'module'        => 'inventory',
                'entity_type'   => 'adjustment',
                'entity_label'  => $adjNumber,
                'record_id'     => $adjId,
                'description'   => "Adjustment {$adjNumber} requested for {$productName}. System: {$systemQty}, Physical: {$physicalQuantity}, Variance: {$variance}",
                'location_type' => $locationType,
                $locationIdKey  => $locationId,
                'old_values'    => ['quantity' => $systemQty],
                'new_values'    => ['physical' => $physicalQuantity, 'variance' => $variance, 'reason' => $reason],
            ]);

            return $adjId;
        });

        // ── Notify all General Managers: a new adjustment needs review ─────────
        // Runs outside the transaction — failure must never roll back the insert.
        $productName = $this->getProductName($productId);
        $varSign     = $variance >= 0 ? '+' : '';
        $locationLabel = ucfirst($locationType) . " #{$locationId}";
        $this->notifyAllGMs(
            'adjustment_pending',
            "Adjustment {$adjNumber} Awaiting Your Approval",
            "A stock adjustment was submitted for your review.\n"
            . "Adjustment: {$adjNumber}\n"
            . "Product: {$productName}\n"
            . "Location: {$locationLabel}\n"
            . "System Qty: " . number_format($systemQty, 3)
            . " → Physical: " . number_format($physicalQuantity, 3)
            . " (Variance: {$varSign}" . number_format($variance, 3) . ")\n"
            . "Reason: {$reason}\n"
            . "Please approve or reject this adjustment.",
            $adjId,
            url("gm/adjustments")
        );
        // ─────────────────────────────────────────────────────────────────────

        return $adjId;
    }

    /**
     * Approve and apply a stock adjustment.
     * Actually modifies inventory and logs a stock movement.
     *
     * @throws \RuntimeException on validation or state failure
     */
    public function approveAdjustment(int $adjustmentId, string $notes = ''): void
    {
        $adj = $this->adjModel->find($adjustmentId);
        if (!$adj) {
            throw new \RuntimeException("Adjustment #{$adjustmentId} not found.");
        }
        if ($adj['status'] !== StockAdjustmentModel::STATUS_PENDING) {
            throw new \RuntimeException("Only pending adjustments can be approved.");
        }

        $userId = (int)SessionManager::userId();

        // Self-approval prevention: the person who requested cannot approve
        if ((int)($adj['requested_by'] ?? 0) === $userId) {
            throw new \RuntimeException("You cannot approve an adjustment you submitted.");
        }
        $locationType = $adj['location_type'];
        $locationId   = (int)$adj['location_id'];
        $productId    = (int)$adj['product_id'];
        $variance     = (float)$adj['variance'];
        $adjNumber    = $adj['adjustment_number'];

        $this->db->transaction(function () use (
            $adj, $adjustmentId, $userId, $locationType, $locationId,
            $productId, $variance, $adjNumber, $notes
        ): void {
            // Apply inventory change
            $beforeQty = $this->getCurrentQty($locationType, $locationId, $productId);
            $afterQty  = max(0, $beforeQty + $variance);

            $this->applyInventoryChange($locationType, $locationId, $productId, $variance);

            // Log movement
            $this->smlModel->log([
                'movement_type'    => StockMovementModel::TYPE_ADJUSTMENT,
                'location_type'    => $locationType,
                'location_id'      => $locationId,
                'product_id'       => $productId,
                'batch_number'     => $adj['batch_number'] ?? null,
                'quantity_before'  => $beforeQty,
                'quantity_in'      => $variance > 0 ? $variance : 0,
                'quantity_out'     => $variance < 0 ? abs($variance) : 0,
                'quantity_after'   => $afterQty,
                'reference_type'   => 'stock_adjustments',
                'reference_id'     => $adjustmentId,
                'reference_number' => $adjNumber,
                'performed_by'     => $userId,
                'notes'            => "Adjustment approved: {$adj['reason']}" . ($notes ? ". {$notes}" : ''),
            ]);

            // Update batch table if batch tracked
            if (!empty($adj['batch_number'])) {
                $this->batchModel->upsertBatch(
                    $locationType, $locationId, $productId,
                    $adj['batch_number'], $variance,
                    !empty($adj['unit_id']) ? (int)$adj['unit_id'] : null,
                    null, null,
                    'adjustment', $adjustmentId
                );
            }

            // Mark adjustment as applied
            $this->db->execute(
                "UPDATE stock_adjustments
                 SET status='applied', approved_by=:by, applied_at=NOW(), updated_at=NOW()
                 WHERE id=:id",
                ['by' => $userId, 'id' => $adjustmentId]
            );

            $productName   = $this->getProductName($productId);
            $locationIdKey = $locationType . '_id';
            $this->auditLog->logBusiness([
                'action'        => ActivityLogModel::ACTION_STOCK_ADJUSTED,
                'module'        => 'inventory',
                'entity_type'   => 'adjustment',
                'entity_label'  => $adjNumber,
                'record_id'     => $adjustmentId,
                'description'   => "Adjustment {$adjNumber} approved and applied for {$productName}. Before: {$beforeQty}, After: {$afterQty}",
                'location_type' => $locationType,
                $locationIdKey  => $locationId,
                'old_values'    => ['quantity' => $beforeQty],
                'new_values'    => ['quantity' => $afterQty, 'variance' => $variance],
            ]);
        });

        // ── Notify the WM who submitted the adjustment ────────────────────────
        $requesterUserId = (int)($adj['requested_by'] ?? 0);
        if ($requesterUserId) {
            $productName  = $this->getProductName($productId);
            $varSign      = $variance >= 0 ? '+' : '';
            $notesPart    = $notes !== '' ? "\nApproval Notes: {$notes}" : '';
            $newQty       = $this->getCurrentQty($locationType, $locationId, $productId);
            $this->notifyUser(
                $requesterUserId,
                'adjustment_approved',
                "Adjustment {$adjNumber} Approved ✓",
                "Your stock adjustment {$adjNumber} has been approved and applied.\n"
                . "Product: {$productName}\n"
                . "Variance applied: {$varSign}" . number_format($variance, 3) . "\n"
                . "New quantity: " . number_format($newQty, 3)
                . $notesPart,
                url("inventory/adjustments/{$adjustmentId}"),
                $adjustmentId
            );
        }
        // ─────────────────────────────────────────────────────────────────────
    }

    /**
     * Reject a pending adjustment.
     */
    public function rejectAdjustment(int $adjustmentId, string $notes = ''): void
    {
        $adj = $this->adjModel->find($adjustmentId);
        if (!$adj || $adj['status'] !== StockAdjustmentModel::STATUS_PENDING) {
            throw new \RuntimeException("Only pending adjustments can be rejected.");
        }

        $userId = (int)SessionManager::userId();
        $this->db->execute(
            "UPDATE stock_adjustments
             SET status='rejected', approved_by=:by, notes=CONCAT(COALESCE(notes,''), :n), updated_at=NOW()
             WHERE id=:id",
            ['by' => $userId, 'n' => ($notes ? "\n[Rejected] " . $notes : ''), 'id' => $adjustmentId]
        );

        // ── Notify the WM who submitted the adjustment ────────────────────────
        $requesterUserId = (int)($adj['requested_by'] ?? 0);
        if ($requesterUserId && $requesterUserId !== $userId) {
            $productName = $this->getProductName((int)$adj['product_id']);
            $adjNumber   = $adj['adjustment_number'];
            $notesPart   = $notes !== '' ? "\nRejection Notes: {$notes}" : '';
            $this->notifyUser(
                $requesterUserId,
                'adjustment_rejected',
                "Adjustment {$adjNumber} Rejected",
                "Your stock adjustment {$adjNumber} has been rejected by the General Manager.\n"
                . "Product: {$productName}\n"
                . "Variance requested: " . number_format((float)$adj['variance'], 3)
                . $notesPart . "\n"
                . "No inventory change has been applied.",
                url("inventory/adjustments/{$adjustmentId}"),
                $adjustmentId
            );
        }
        // ─────────────────────────────────────────────────────────────────────
    }

    // =========================================================
    // PRIVATE HELPERS
    // =========================================================

    /**
     * Send an in-system notification to a single user.
     * Failure is silently swallowed — notifications must never block business logic.
     */
    private function notifyUser(
        int    $recipientId,
        string $type,
        string $title,
        string $message,
        string $relatedUrl = '',
        int    $refId      = 0
    ): void {
        try {
            $this->db->execute(
                "INSERT INTO notifications
                    (recipient_user_id, sender_user_id, notification_type,
                     title, message, reference_type, reference_id, related_url)
                 VALUES (:rid, :sid, :type, :title, :msg, 'stock_adjustments', :roid, :url)",
                [
                    'rid'   => $recipientId,
                    'sid'   => (int)\AAM\Core\SessionManager::userId() ?: null,
                    'type'  => $type,
                    'title' => $title,
                    'msg'   => $message,
                    'roid'  => $refId ?: null,
                    'url'   => $relatedUrl ?: null,
                ]
            );
        } catch (\Throwable) {}
    }

    /**
     * Notify every active General Manager about a new pending adjustment.
     */
    private function notifyAllGMs(
        string $type,
        string $title,
        string $message,
        int    $adjId,
        string $relatedUrl = ''
    ): void {
        try {
            $gms = $this->db->fetchAll(
                "SELECT u.id FROM users u
                 JOIN roles r ON r.id = u.role_id
                 WHERE r.name = 'general_manager'
                   AND u.is_active = 1
                   AND u.account_status = 'active'"
            );
            $senderId = (int)\AAM\Core\SessionManager::userId() ?: null;
            foreach ($gms as $gm) {
                $this->db->execute(
                    "INSERT INTO notifications
                        (recipient_user_id, sender_user_id, notification_type,
                         title, message, reference_type, reference_id, related_url)
                     VALUES (:rid, :sid, :type, :title, :msg, 'stock_adjustments', :roid, :url)",
                    [
                        'rid'   => (int)$gm['id'],
                        'sid'   => $senderId,
                        'type'  => $type,
                        'title' => $title,
                        'msg'   => $message,
                        'roid'  => $adjId,
                        'url'   => $relatedUrl ?: null,
                    ]
                );
            }
        } catch (\Throwable) {}
    }

    private function getCurrentQty(string $locationType, int $locationId, int $productId): float
    {
        if ($locationType === 'warehouse') {
            $row = $this->db->fetchOne(
                "SELECT current_quantity FROM warehouse_inventory
                 WHERE warehouse_id=:lid AND product_id=:pid",
                ['lid' => $locationId, 'pid' => $productId]
            );
        } else {
            $row = $this->db->fetchOne(
                "SELECT current_quantity FROM shop_inventory
                 WHERE shop_id=:lid AND product_id=:pid",
                ['lid' => $locationId, 'pid' => $productId]
            );
        }
        return (float)($row['current_quantity'] ?? 0);
    }

    private function applyInventoryChange(
        string $locationType,
        int    $locationId,
        int    $productId,
        float  $delta
    ): void {
        if ($locationType === 'warehouse') {
            $this->db->execute(
                "UPDATE warehouse_inventory
                 SET current_quantity   = GREATEST(0, current_quantity + :d),
                     available_quantity = GREATEST(0, available_quantity + :d2),
                     last_movement_at   = NOW(),
                     last_movement_type = 'adjustment',
                     updated_at         = NOW()
                 WHERE warehouse_id=:lid AND product_id=:pid",
                ['d' => $delta, 'd2' => $delta, 'lid' => $locationId, 'pid' => $productId]
            );
        } else {
            $existing = $this->db->fetchOne(
                "SELECT id FROM shop_inventory WHERE shop_id=:lid AND product_id=:pid",
                ['lid' => $locationId, 'pid' => $productId]
            );
            if ($existing) {
                $this->db->execute(
                    "UPDATE shop_inventory
                     SET current_quantity   = GREATEST(0, current_quantity + :d),
                         available_quantity = GREATEST(0, available_quantity + :d2),
                         last_movement_at   = NOW(),
                         last_movement_type = 'adjustment',
                         updated_at         = NOW()
                     WHERE shop_id=:lid AND product_id=:pid",
                    ['d' => $delta, 'd2' => $delta, 'lid' => $locationId, 'pid' => $productId]
                );
            }
        }
    }

    private function getProductName(int $productId): string
    {
        return $this->db->fetchOne(
            "SELECT name FROM products WHERE id=:id", ['id' => $productId]
        )['name'] ?? "Product #{$productId}";
    }
}
