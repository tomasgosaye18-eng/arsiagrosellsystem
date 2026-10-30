<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 10
 * =========================================================
 * File    : modules/warehouse/services/WarehouseService.php
 * Purpose : Orchestrates all warehouse business operations.
 *           All multi-table writes are wrapped in DB transactions.
 *           No negative stock. No silent history modification.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Warehouse\Services;

use AAM\Core\Database;
use AAM\Core\SessionManager;
use AAM\Warehouse\Models\WarehouseInventoryModel;
use AAM\Warehouse\Models\ReceivingModel;
use AAM\Warehouse\Models\DiscrepancyModel;
use AAM\Warehouse\Models\TransferModel;
use AAM\Warehouse\Models\StockMovementModel;
use AAM\Admin\Models\ActivityLogModel;
use AAM\Inventory\Models\InventoryBatchModel;

class WarehouseService
{
    private WarehouseInventoryModel $invModel;
    private ReceivingModel          $rcvModel;
    private DiscrepancyModel        $disModel;
    private TransferModel           $trnModel;
    private StockMovementModel      $smlModel;
    private ActivityLogModel        $auditLog;
    private InventoryBatchModel     $batchModel;
    private Database                $db;

    public function __construct()
    {
        $this->invModel   = new WarehouseInventoryModel();
        $this->rcvModel   = new ReceivingModel();
        $this->disModel   = new DiscrepancyModel();
        $this->trnModel   = new TransferModel();
        $this->smlModel   = new StockMovementModel();
        $this->auditLog   = new ActivityLogModel();
        $this->batchModel = new InventoryBatchModel();
        $this->db         = Database::getInstance();
    }

    // =========================================================
    // RECEIVING WORKFLOW
    // =========================================================

    /**
     * Send an in-system notification to a user.
     */
    private function notify(
        int    $recipientUserId,
        string $type,
        string $title,
        string $message,
        string $referenceType = '',
        int    $referenceId   = 0,
        string $relatedUrl    = ''
    ): void {
        try {
            $this->db->execute(
                "INSERT INTO notifications
                    (recipient_user_id, sender_user_id, notification_type,
                     title, message, reference_type, reference_id, related_url)
                 VALUES (:rid, :sid, :type, :title, :msg, :rtype, :roid, :url)",
                [
                    'rid'   => $recipientUserId,
                    'sid'   => (int)SessionManager::userId() ?: null,
                    'type'  => $type,
                    'title' => $title,
                    'msg'   => $message,
                    'rtype' => $referenceType ?: null,
                    'roid'  => $referenceId   ?: null,
                    'url'   => $relatedUrl    ?: null,
                ]
            );
        } catch (\Throwable) {
            // Notification failure must never break the main workflow
        }
    }

    /**
     * Notify all General Manager users of a pending discrepancy.
     */
    private function notifyGMsOfDiscrepancy(
        int    $receivingId,
        string $rcvNumber,
        string $farmName,
        string $warehouseName,
        float  $totalExpected,
        float  $totalReceived
    ): void {
        $gms = $this->db->fetchAll(
            "SELECT u.id FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE r.name = 'general_manager' AND u.is_active = 1"
        );
        $missing = $totalExpected - $totalReceived;
        $title   = "Discrepancy Review Required — {$rcvNumber}";
        $message = "Warehouse receiving {$rcvNumber} from {$farmName} to {$warehouseName} "
                 . "has a discrepancy requiring your review.\n"
                 . "Expected: " . number_format($totalExpected, 3) . " kg | "
                 . "Received: " . number_format($totalReceived, 3) . " kg | "
                 . "Missing: "  . number_format($missing, 3) . " kg.\n"
                 . "Please review and approve or reject this discrepancy.";
        foreach ($gms as $gm) {
            $this->notify((int)$gm['id'], 'discrepancy_gm_review', $title, $message,
                          'warehouse_receiving', $receivingId,
                          url("warehouse/receiving/{$receivingId}"));
        }
    }

    /**
     * Notify the Farm Manager (dispatch initiator) of final receiving outcome.
     */
    private function notifyFarmManagerOfCompletion(
        int    $dispatchId,
        string $rcvNumber,
        float  $totalExpected,
        float  $totalReceived,
        string $farmName,
        string $warehouseName,
        bool   $hadDiscrepancy
    ): void {
        // Find dispatch initiator
        $initiator = $this->db->fetchOne(
            "SELECT fd.initiated_by FROM farm_dispatches fd WHERE fd.id = :id",
            ['id' => $dispatchId]
        );
        if (!$initiator || !(int)$initiator['initiated_by']) return;

        $missing = $totalExpected - $totalReceived;
        if ($hadDiscrepancy) {
            $title   = "Transfer {$rcvNumber}: Discrepancy Resolved";
            $message = "Transfer {$rcvNumber} was dispatched from {$farmName} with "
                     . number_format($totalExpected, 3) . " kg.\n"
                     . "Warehouse {$warehouseName} received " . number_format($totalReceived, 3) . " kg.\n"
                     . "A discrepancy of " . number_format($missing, 3) . " kg was reported.\n"
                     . "The discrepancy was reviewed and approved by the General Manager.\n"
                     . "Warehouse inventory increased by " . number_format($totalReceived, 3) . " kg only.";
        } else {
            $title   = "Transfer {$rcvNumber}: Fully Received";
            $message = "Transfer {$rcvNumber} from {$farmName} was fully received by "
                     . "warehouse {$warehouseName} (" . number_format($totalReceived, 3) . " kg).";
        }
        $this->notify((int)$initiator['initiated_by'], 'receiving_completed', $title, $message,
                      'warehouse_receiving', $dispatchId,
                      url("dispatches/{$dispatchId}"));
    }

    /**
     * Confirm receiving of a farm dispatch.
     *
     * DISCREPANCY WORKFLOW (NEW):
     *   If actual quantity != expected quantity for ANY item:
     *     - A discrepancy reason is REQUIRED for that item.
     *     - warehouse_receiving status is set to 'discrepancy_pending'.
     *     - warehouse_discrepancies records are created (status = 'under_review').
     *     - Inventory is NOT updated yet.
     *     - GM is notified for review.
     *     - WM cannot mark as received until GM approves.
     *
     *   After GM approves (see gmApproveReceivingDiscrepancy):
     *     - WM calls completeReceivingAfterGMApproval.
     *     - Inventory updated with ACTUAL received quantities only.
     *     - Farm Manager notified.
     *
     * Normal flow (no discrepancy): inventory updated immediately.
     *
     * @throws \RuntimeException on validation failure
     */
    public function confirmReceiving(
        int    $dispatchId,
        int    $warehouseId,
        array  $items,
        string $notes = ''
    ): array {
        if (empty($items)) {
            throw new \RuntimeException('No items provided for receiving.');
        }

        if ($this->rcvModel->existsForDispatch($dispatchId)) {
            throw new \RuntimeException('A receiving record already exists for this dispatch.');
        }

        // DISCREPANCY VALIDATION: if any item has a variance, a reason is REQUIRED
        foreach ($items as $item) {
            $expected = (float)$item['quantity_expected'];
            $received = (float)$item['quantity_received'];
            if (abs($expected - $received) > 0.001) {
                $reason = trim($item['discrepancy_reason'] ?? '');
                if ($reason === '') {
                    $productId = (int)$item['product_id'];
                    $productName = $this->db->fetchOne(
                        "SELECT name FROM products WHERE id = :id", ['id' => $productId]
                    )['name'] ?? "Product #{$productId}";
                    throw new \RuntimeException(
                        "Discrepancy reason is required for \"{$productName}\" "
                        . "(Expected: " . number_format($expected, 3) . ", Received: " . number_format($received, 3) . "). "
                        . "Please enter the reason for the quantity difference."
                    );
                }
            }
        }

        $userId = (int)SessionManager::userId();

        return $this->db->transaction(function () use (
            $dispatchId, $warehouseId, $items, $notes, $userId
        ): array {
            // Fetch dispatch to get farm_id
            $dispatch = $this->db->fetchOne(
                "SELECT id, farm_id, warehouse_id FROM farm_dispatches WHERE id = :id",
                ['id' => $dispatchId]
            );

            $farmId = (int)($dispatch['farm_id'] ?? 0);
            $farmName = $this->db->fetchOne(
                "SELECT name FROM farms WHERE id = :id", ['id' => $farmId]
            )['name'] ?? "Farm #{$farmId}";
            $warehouseName = $this->db->fetchOne(
                "SELECT name FROM warehouses WHERE id = :id", ['id' => $warehouseId]
            )['name'] ?? "Warehouse #{$warehouseId}";

            // Calculate totals
            $totalExpected = array_sum(array_column($items, 'quantity_expected'));
            $totalReceived = array_sum(array_column($items, 'quantity_received'));

            // Determine if ANY item has a discrepancy
            $discrepancyCount = 0;
            foreach ($items as $item) {
                if (abs((float)$item['quantity_received'] - (float)$item['quantity_expected']) > 0.001) {
                    $discrepancyCount++;
                }
            }

            // DISCREPANCY WORKFLOW GATE:
            // If any item has a variance → status = discrepancy_pending
            // Do NOT update inventory yet — must wait for GM approval
            $hasDiscrepancy = $discrepancyCount > 0;
            if ($hasDiscrepancy) {
                $overallStatus = 'discrepancy_pending';
            } elseif ($totalReceived <= 0) {
                $overallStatus = ReceivingModel::STATUS_REJECTED;
            } else {
                $overallStatus = ReceivingModel::STATUS_RECEIVED;
            }

            // Create receiving header
            $rcvNumber   = $this->rcvModel->generateReceivingNumber();
            $this->db->execute(
                "INSERT INTO warehouse_receiving
                    (receiving_number, dispatch_id, warehouse_id, farm_id,
                     received_by, received_date, status,
                     total_expected, total_received, notes)
                 VALUES (:rnum, :did, :wid, :fid, :rb, CURDATE(), :status, :te, :tr, :notes)",
                [
                    'rnum'   => $rcvNumber,
                    'did'    => $dispatchId,
                    'wid'    => $warehouseId,
                    'fid'    => $farmId ?: null,
                    'rb'     => $userId,
                    'status' => $overallStatus,
                    'te'     => $totalExpected,
                    'tr'     => $totalReceived,
                    'notes'  => $notes ?: null,
                ]
            );
            $receivingId = $this->db->lastInsertId();

            // Process each item
            foreach ($items as $item) {
                $expected = (float)$item['quantity_expected'];
                $received = (float)$item['quantity_received'];
                $variance = $expected - $received;
                $varPct   = $expected > 0 ? round(abs($variance / $expected) * 100, 2) : 0;

                $itemStatus = 'accepted';
                if ($received <= 0)           $itemStatus = 'rejected';
                elseif ($variance > 0.001)    $itemStatus = 'partial';

                // Insert receiving item
                $this->db->execute(
                    "INSERT INTO warehouse_receiving_items
                        (receiving_id, dispatch_item_id, product_id, unit_id,
                         batch_number, production_date, expiry_date,
                         quantity_expected, quantity_received, quantity_variance,
                         variance_percent, item_status, notes)
                     VALUES
                        (:rid, :diid, :pid, :uid, :bn, :pd, :ed,
                         :qe, :qr, :qv, :vp, :st, :notes)",
                    [
                        'rid'   => $receivingId,
                        'diid'  => (int)($item['dispatch_item_id'] ?? 0),
                        'pid'   => (int)$item['product_id'],
                        'uid'   => !empty($item['unit_id']) ? (int)$item['unit_id'] : null,
                        'bn'    => $item['batch_number']    ?? null,
                        'pd'    => $item['production_date'] ?? null,
                        'ed'    => $item['expiry_date']     ?? null,
                        'qe'    => $expected,
                        'qr'    => $received,
                        'qv'    => $variance,
                        'vp'    => $varPct,
                        'st'    => $itemStatus,
                        'notes' => $item['notes'] ?? null,
                    ]
                );
                $receivingItemId = $this->db->lastInsertId();

                // KEY RULE: Only update inventory immediately if NO discrepancy on this receiving.
                // If there is ANY discrepancy, inventory is updated ONLY after GM approval
                // (see completeReceivingAfterGMApproval).
                if (!$hasDiscrepancy && $received > 0) {
                    $movement = $this->invModel->increaseStock(
                        $warehouseId,
                        (int)$item['product_id'],
                        $received,
                        !empty($item['unit_id']) ? (int)$item['unit_id'] : null,
                        $item['batch_number']    ?? null,
                        $item['production_date'] ?? null,
                        $item['expiry_date']     ?? null,
                        'receiving'
                    );

                    $this->smlModel->log([
                        'movement_type'    => StockMovementModel::TYPE_RECEIVING,
                        'location_type'    => 'warehouse',
                        'location_id'      => $warehouseId,
                        'product_id'       => (int)$item['product_id'],
                        'batch_number'     => $item['batch_number'] ?? null,
                        'quantity_before'  => $movement['before'],
                        'quantity_in'      => $received,
                        'quantity_out'     => 0,
                        'quantity_after'   => $movement['after'],
                        'reference_type'   => 'warehouse_receiving',
                        'reference_id'     => $receivingId,
                        'reference_number' => $rcvNumber,
                        'performed_by'     => $userId,
                        'notes'            => "Received from dispatch #{$dispatchId}",
                    ]);

                    if (!empty($item['batch_number'])) {
                        $this->batchModel->upsertBatch(
                            'warehouse', $warehouseId,
                            (int)$item['product_id'],
                            $item['batch_number'],
                            $received,
                            !empty($item['unit_id']) ? (int)$item['unit_id'] : null,
                            $item['production_date'] ?? null,
                            $item['expiry_date']     ?? null,
                            'receiving', $receivingId
                        );
                    }
                }

                // Create discrepancy record for every variance item
                if (abs($variance) > 0.001) {
                    $disType = $variance > 0 ? 'short_delivery' : 'over_delivery';

                    $discrepancyId = $this->disModel->createDiscrepancy([
                        'reference_type'    => 'receiving',
                        'reference_id'      => $receivingId,
                        'receiving_item_id' => $receivingItemId,
                        'warehouse_id'      => $warehouseId,
                        'farm_id'           => $farmId ?: null,
                        'product_id'        => (int)$item['product_id'],
                        'batch_number'      => $item['batch_number'] ?? null,
                        'expected_quantity' => $expected,
                        'actual_quantity'   => $received,
                        'variance_quantity' => $variance,
                        'variance_percent'  => $varPct,
                        'discrepancy_type'  => $disType,
                        'reason'            => trim($item['discrepancy_reason'] ?? ''),
                        'status'            => DiscrepancyModel::STATUS_UNDER_REVIEW,
                        'reported_by'       => $userId,
                    ]);

                    // Log workflow step: submitted
                    $this->db->execute(
                        "INSERT INTO discrepancy_workflow_log
                            (discrepancy_id, receiving_id, action, actor_user_id, notes, snapshot)
                         VALUES (:did, :rid, 'submitted', :uid, :notes, :snap)",
                        [
                            'did'  => $discrepancyId,
                            'rid'  => $receivingId,
                            'uid'  => $userId,
                            'notes'=> "WM reported discrepancy: expected={$expected}, actual={$received}",
                            'snap' => json_encode([
                                'expected_quantity' => $expected,
                                'actual_quantity'   => $received,
                                'variance'          => $variance,
                                'reason'            => $item['discrepancy_reason'] ?? '',
                            ]),
                        ]
                    );
                }
            }

            // Update farm dispatch status
            if ($hasDiscrepancy) {
                // Farm dispatch stays in_transit until GM approves and WM completes
                $dispatchStatus = 'in_transit';
            } elseif ($overallStatus === ReceivingModel::STATUS_REJECTED) {
                $dispatchStatus = 'cancelled';
            } else {
                $dispatchStatus = 'received';
            }

            $this->db->execute(
                "UPDATE farm_dispatches SET status = :s, updated_at = NOW() WHERE id = :id",
                ['s' => $dispatchStatus, 'id' => $dispatchId]
            );

            // Business audit trail
            $this->auditLog->logBusiness([
                'action'       => ActivityLogModel::ACTION_STOCK_RECEIVED,
                'module'       => 'warehouse',
                'entity_type'  => 'receiving',
                'entity_label' => $rcvNumber,
                'record_id'    => $receivingId,
                'description'  => "Receiving {$rcvNumber}: expected {$totalExpected}, received {$totalReceived}" .
                                  ($discrepancyCount > 0 ? " ({$discrepancyCount} discrepanc" .
                                  ($discrepancyCount > 1 ? 'ies' : 'y') . " — pending GM review)" : ""),
                'warehouse_id' => $warehouseId,
                'farm_id'      => $farmId ?: null,
                'new_values'   => [
                    'receiving_number'  => $rcvNumber,
                    'total_expected'    => $totalExpected,
                    'total_received'    => $totalReceived,
                    'status'            => $overallStatus,
                    'discrepancy_count' => $discrepancyCount,
                ],
            ]);

            // Notify GMs if there are discrepancies requiring review
            if ($hasDiscrepancy) {
                $this->notifyGMsOfDiscrepancy(
                    $receivingId, $rcvNumber,
                    $farmName, $warehouseName,
                    $totalExpected, $totalReceived
                );
            } else {
                // No discrepancy: notify farm manager immediately
                $this->notifyFarmManagerOfCompletion(
                    $dispatchId, $rcvNumber,
                    $totalExpected, $totalReceived,
                    $farmName, $warehouseName, false
                );
            }

            return [
                'receiving_id'       => $receivingId,
                'discrepancies'      => $discrepancyCount,
                'discrepancy_pending'=> $hasDiscrepancy,
            ];
        });
    }

    // =========================================================
    // DISCREPANCY GM APPROVAL WORKFLOW (RECEIVING)
    // =========================================================

    /**
     * GM approves a receiving discrepancy.
     *
     * RULES:
     *  - Only callable by ROLE_GENERAL_MANAGER.
     *  - Receiving must be in 'discrepancy_pending' status.
     *  - Marks the discrepancy(ies) as gm_action = 'approved'.
     *  - Does NOT update inventory — WM must call completeReceivingAfterGMApproval.
     *  - Notifies the Warehouse Manager.
     *
     * @throws \RuntimeException
     */
    public function gmApproveReceivingDiscrepancy(int $receivingId, string $gmNotes = ''): void
    {
        $receiving = $this->rcvModel->getWithItems($receivingId);
        if (!$receiving) {
            throw new \RuntimeException("Receiving record #{$receivingId} not found.");
        }
        if ($receiving['status'] !== 'discrepancy_pending') {
            throw new \RuntimeException(
                "Only receivings with status 'Discrepancy Pending' can be reviewed. "
                . "Current status: " . ReceivingModel::statusLabel($receiving['status']) . "."
            );
        }

        $userId = (int)SessionManager::userId();

        $this->db->transaction(function () use ($receivingId, $receiving, $userId, $gmNotes): void {
            // Update all open discrepancies for this receiving to gm_approved
            $this->db->execute(
                "UPDATE warehouse_discrepancies
                 SET gm_action = 'approved',
                     gm_reviewed_by = :uid,
                     gm_reviewed_at = NOW(),
                     gm_notes = :notes,
                     status = 'resolved',
                     updated_at = NOW()
                 WHERE reference_type = 'receiving'
                   AND reference_id = :rid
                   AND gm_action IS NULL",
                ['uid' => $userId, 'notes' => $gmNotes ?: null, 'rid' => $receivingId]
            );

            // Update receiving status to gm_approved
            $this->db->execute(
                "UPDATE warehouse_receiving SET status = 'gm_approved', updated_at = NOW()
                 WHERE id = :id",
                ['id' => $receivingId]
            );

            // Log workflow step
            $discrepancyIds = $this->db->fetchAll(
                "SELECT id FROM warehouse_discrepancies
                 WHERE reference_type = 'receiving' AND reference_id = :rid",
                ['rid' => $receivingId]
            );
            foreach ($discrepancyIds as $dis) {
                $this->db->execute(
                    "INSERT INTO discrepancy_workflow_log
                        (discrepancy_id, receiving_id, action, actor_user_id, notes, snapshot)
                     VALUES (:did, :rid, 'gm_approved', :uid, :notes, :snap)",
                    [
                        'did'  => (int)$dis['id'],
                        'rid'  => $receivingId,
                        'uid'  => $userId,
                        'notes'=> "GM approved discrepancy.",
                        'snap' => json_encode(['gm_notes' => $gmNotes, 'gm_user_id' => $userId]),
                    ]
                );
            }

            // Notify Warehouse Manager
            $wmUserId = (int)($receiving['received_by'] ?? 0);
            if ($wmUserId) {
                $rcvNumber = $receiving['receiving_number'];
                $this->notify(
                    $wmUserId,
                    'discrepancy_gm_approved',
                    "Discrepancy Approved — {$rcvNumber}",
                    "The General Manager has approved the discrepancy for receiving {$rcvNumber}. "
                    . "You may now complete the receiving and update warehouse inventory."
                    . ($gmNotes ? "\nGM Notes: {$gmNotes}" : ""),
                    'warehouse_receiving',
                    $receivingId,
                    url("warehouse/receiving/{$receivingId}")
                );
            }

            $this->auditLog->logBusiness([
                'action'       => 'discrepancy_gm_approved',
                'module'       => 'warehouse',
                'entity_type'  => 'receiving',
                'entity_label' => $receiving['receiving_number'],
                'record_id'    => $receivingId,
                'description'  => "GM (user #{$userId}) approved discrepancy for receiving {$receiving['receiving_number']}.",
                'warehouse_id' => (int)$receiving['warehouse_id'],
            ]);
        });
    }

    /**
     * GM rejects a receiving discrepancy — returns it to the WM for correction.
     *
     * RULES:
     *  - Only callable by ROLE_GENERAL_MANAGER.
     *  - Receiving must be in 'discrepancy_pending' status.
     *  - Increments rejection_count on the receiving record.
     *  - If rejection_count reaches MAX_REJECTIONS (2):
     *      → Triggers applyFinalRejectionReceiving() automatically.
     *      → Status → 'final_rejected'. No resubmission allowed.
     *      → Farm harvest stock restored.
     *      → Farm Manager + WM notified with "final rejection" message.
     *  - If rejection_count < MAX_REJECTIONS:
     *      → Status → 'gm_rejected'. WM may resubmit once.
     *      → WM notified with rejection reason.
     *
     * @throws \RuntimeException
     */
    public function gmRejectReceivingDiscrepancy(int $receivingId, string $reason): void
    {
        if (trim($reason) === '') {
            throw new \RuntimeException("A rejection reason is required.");
        }

        $receiving = $this->rcvModel->getWithItems($receivingId);
        if (!$receiving) {
            throw new \RuntimeException("Receiving record #{$receivingId} not found.");
        }
        if ($receiving['status'] !== 'discrepancy_pending') {
            throw new \RuntimeException(
                "Only receivings with status 'Discrepancy Pending' can be reviewed. "
                . "Current status: " . ReceivingModel::statusLabel($receiving['status']) . "."
            );
        }

        $userId         = (int)SessionManager::userId();
        $newCount       = (int)($receiving['rejection_count'] ?? 0) + 1;
        $isFinalRejection = ($newCount >= ReceivingModel::MAX_REJECTIONS);

        $this->db->transaction(function () use (
            $receivingId, $receiving, $userId, $reason, $newCount, $isFinalRejection
        ): void {
            // ── 1. Mark discrepancies as gm_rejected ─────────────────────────
            // Both first and final GM rejection → 'rejected' on warehouse_discrepancies.
            // (The ENUM only supports: open, under_review, resolved, rejected)
            // Resubmit eligibility is controlled by warehouse_receiving.status
            // and warehouse_receiving.final_rejected — NOT by this status.
            $this->db->execute(
                "UPDATE warehouse_discrepancies
                 SET gm_action = 'rejected',
                     gm_reviewed_by = :uid,
                     gm_reviewed_at = NOW(),
                     gm_notes = :reason,
                     status = 'rejected',
                     updated_at = NOW()
                 WHERE reference_type = 'receiving'
                   AND reference_id = :rid
                   AND gm_action IS NULL",
                [
                    'uid'    => $userId,
                    'reason' => $reason,
                    'rid'    => $receivingId,
                ]
            );

            // ── 2. Increment rejection_count on the receiving record ──────────
            $newStatus = $isFinalRejection
                ? ReceivingModel::STATUS_FINAL_REJECTED
                : ReceivingModel::STATUS_GM_REJECTED;

            $this->db->execute(
                "UPDATE warehouse_receiving
                 SET status           = :st,
                     rejection_count  = :rc,
                     final_rejected   = :fr,
                     updated_at       = NOW()
                 WHERE id = :id",
                [
                    'st' => $newStatus,
                    'rc' => $newCount,
                    'fr' => $isFinalRejection ? 1 : 0,
                    'id' => $receivingId,
                ]
            );

            // ── 3. Workflow log ───────────────────────────────────────────────
            $discrepancyIds = $this->db->fetchAll(
                "SELECT id FROM warehouse_discrepancies
                 WHERE reference_type = 'receiving' AND reference_id = :rid",
                ['rid' => $receivingId]
            );
            $action = $isFinalRejection ? 'gm_final_rejected' : 'gm_rejected';
            foreach ($discrepancyIds as $dis) {
                $this->db->execute(
                    "INSERT INTO discrepancy_workflow_log
                        (discrepancy_id, receiving_id, action, actor_user_id, notes, snapshot)
                     VALUES (:did, :rid, :action, :uid, :notes, :snap)",
                    [
                        'did'    => (int)$dis['id'],
                        'rid'    => $receivingId,
                        'action' => $action,
                        'uid'    => $userId,
                        'notes'  => "GM rejected discrepancy (count {$newCount}): {$reason}",
                        'snap'   => json_encode([
                            'rejection_reason' => $reason,
                            'rejection_count'  => $newCount,
                            'is_final'         => $isFinalRejection,
                            'gm_user_id'       => $userId,
                        ]),
                    ]
                );
            }

            if ($isFinalRejection) {
                // ── 4a. FINAL REJECTION — trigger stock restoration ───────────
                // Done inside the same transaction
                $this->applyFinalRejectionReceiving(
                    $receivingId, $receiving, $userId, $reason, $discrepancyIds
                );
            } else {
                // ── 4b. FIRST REJECTION — notify WM to resubmit ──────────────
                $wmUserId  = (int)($receiving['received_by'] ?? 0);
                $rcvNumber = $receiving['receiving_number'];
                if ($wmUserId) {
                    $this->notify(
                        $wmUserId,
                        'discrepancy_gm_rejected',
                        "Discrepancy Rejected — {$rcvNumber}",
                        "The General Manager has rejected the discrepancy for receiving {$rcvNumber}. "
                        . "You may correct and resubmit once more.\n"
                        . "Reason: {$reason}\n"
                        . "⚠ Note: if rejected again, the transfer will be permanently closed.",
                        'warehouse_receiving',
                        $receivingId,
                        url("warehouse/receiving/{$receivingId}")
                    );
                }

                $this->auditLog->logBusiness([
                    'action'       => 'discrepancy_gm_rejected',
                    'module'       => 'warehouse',
                    'entity_type'  => 'receiving',
                    'entity_label' => $receiving['receiving_number'],
                    'record_id'    => $receivingId,
                    'description'  => "GM (user #{$userId}) rejected discrepancy for receiving "
                                    . "{$receiving['receiving_number']} "
                                    . "(rejection #{$newCount}). Reason: {$reason}",
                    'warehouse_id' => (int)$receiving['warehouse_id'],
                ]);
            }
        });
    }

    /**
     * Apply final rejection for a Farm→Warehouse receiving discrepancy.
     *
     * Called automatically inside gmRejectReceivingDiscrepancy when
     * rejection_count reaches MAX_REJECTIONS.
     *
     * WHAT THIS DOES:
     *  1. Restores dispatched quantities to source farm harvest batches.
     *  2. Logs a REVERSAL stock movement for full audit trail.
     *  3. Sets farm_dispatches.status = 'final_rejected'.
     *  4. Sets stock_restored = 1 (idempotency guard).
     *  5. Notifies Farm Manager with full details.
     *  6. Notifies Warehouse Manager that transfer is closed.
     *  7. Logs business audit trail.
     *
     * IMPORTANT: Called INSIDE an existing DB transaction.
     * Do NOT open a nested transaction here.
     *
     * @param int   $receivingId
     * @param array $receiving   Full receiving record with items
     * @param int   $userId      GM user performing the action
     * @param string $reason     Rejection reason
     * @param array $discrepancyIds  [['id' => int], ...]
     */
    private function applyFinalRejectionReceiving(
        int    $receivingId,
        array  $receiving,
        int    $userId,
        string $reason,
        array  $discrepancyIds
    ): void {
        $dispatchId  = (int)$receiving['dispatch_id'];
        $warehouseId = (int)$receiving['warehouse_id'];
        $rcvNumber   = $receiving['receiving_number'];

        // Idempotency guard — never restore twice
        $alreadyRestored = (int)($receiving['stock_restored'] ?? 0);
        if ($alreadyRestored) {
            return;
        }

        // ── A. Fetch dispatch + items to restore harvest stock ────────────────
        $dispatch = $this->db->fetchOne(
            "SELECT fd.*, f.name AS farm_name
             FROM farm_dispatches fd
             LEFT JOIN farms f ON f.id = fd.farm_id
             WHERE fd.id = :id",
            ['id' => $dispatchId]
        );

        if (!$dispatch) {
            // Log error but don't fail the main transaction
            $this->auditLog->logBusiness([
                'action'       => 'final_rejection_error',
                'module'       => 'warehouse',
                'entity_type'  => 'receiving',
                'entity_label' => $rcvNumber,
                'record_id'    => $receivingId,
                'description'  => "Final rejection: dispatch #{$dispatchId} not found — stock not restored.",
                'warehouse_id' => $warehouseId,
            ]);
            return;
        }

        $dispatchItems = $this->db->fetchAll(
            "SELECT di.*, hb.remaining_quantity AS batch_remaining
             FROM farm_dispatch_items di
             LEFT JOIN harvest_batches hb ON hb.id = di.harvest_batch_id
             WHERE di.dispatch_id = :did",
            ['did' => $dispatchId]
        );

        $farmName      = $dispatch['farm_name'] ?? "Farm #{$dispatch['farm_id']}";
        $warehouseName = $receiving['warehouse_name'] ?? "Warehouse #{$warehouseId}";

        // ── B. Restore harvest quantities to source farm ──────────────────────
        $harvestModel = new \AAM\Farm\Models\HarvestBatchModel();
        $totalRestored = 0.0;

        foreach ($dispatchItems as $item) {
            $qty       = (float)$item['quantity_dispatched'];
            $batchId   = (int)$item['harvest_batch_id'];
            $productId = (int)$item['product_id'];

            if ($qty <= 0 || !$batchId) continue;

            // Restore to harvest batch
            $harvestModel->restoreQuantity($batchId, $qty);
            $totalRestored += $qty;

            // Log reversal movement on the warehouse side (records the cancellation)
            $warehouseStock = $this->db->fetchOne(
                "SELECT COALESCE(current_quantity, 0) AS qty
                 FROM warehouse_inventory WHERE warehouse_id = :wid AND product_id = :pid",
                ['wid' => $warehouseId, 'pid' => $productId]
            );
            $currQty = (float)($warehouseStock['qty'] ?? 0);

            $this->smlModel->log([
                'movement_type'    => StockMovementModel::TYPE_REVERSAL,
                'location_type'    => 'warehouse',
                'location_id'      => $warehouseId,
                'product_id'       => $productId,
                'batch_number'     => $item['batch_number'] ?? null,
                'quantity_before'  => $currQty,
                'quantity_in'      => 0,
                'quantity_out'     => 0,
                'quantity_after'   => $currQty,
                'reference_type'   => 'warehouse_receiving',
                'reference_id'     => $receivingId,
                'reference_number' => $rcvNumber,
                'performed_by'     => $userId,
                'notes'            => "FINAL REJECTION — {$rcvNumber}: dispatch #{$dispatchId} "
                                    . "returned to farm. {$qty} units restored to harvest batch "
                                    . "#{$batchId}. Reason: {$reason}",
            ]);
        }

        // ── C. Set stock_restored = 1 (idempotency) ───────────────────────────
        $this->db->execute(
            "UPDATE warehouse_receiving
             SET stock_restored = 1, updated_at = NOW()
             WHERE id = :id",
            ['id' => $receivingId]
        );

        // ── C2. Set warehouse_discrepancies.status = 'rejected' ───────────────
        // Ensures the WM discrepancies page shows the correct final status
        // and no manual resolution is possible after final rejection.
        $this->db->execute(
            "UPDATE warehouse_discrepancies
             SET status = 'rejected', updated_at = NOW()
             WHERE reference_type = 'receiving'
               AND reference_id   = :rid",
            ['rid' => $receivingId]
        );

        // ── D. Set farm dispatch status = final_rejected ──────────────────────
        $this->db->execute(
            "UPDATE farm_dispatches
             SET status = 'final_rejected', updated_at = NOW()
             WHERE id = :id",
            ['id' => $dispatchId]
        );

        // ── E. Workflow log: final_rejection + stock_restored ─────────────────
        foreach ($discrepancyIds as $dis) {
            $this->db->execute(
                "INSERT INTO discrepancy_workflow_log
                    (discrepancy_id, receiving_id, action, actor_user_id, notes, snapshot)
                 VALUES (:did, :rid, 'final_rejected', :uid, :notes, :snap)",
                [
                    'did'  => (int)$dis['id'],
                    'rid'  => $receivingId,
                    'uid'  => $userId,
                    'notes'=> "FINAL REJECTION: receiving closed. "
                            . number_format($totalRestored, 3) . " units returned to farm.",
                    'snap' => json_encode([
                        'rejection_count'  => ReceivingModel::MAX_REJECTIONS,
                        'total_restored'   => $totalRestored,
                        'dispatch_id'      => $dispatchId,
                        'reason'           => $reason,
                    ]),
                ]
            );
            $this->db->execute(
                "INSERT INTO discrepancy_workflow_log
                    (discrepancy_id, receiving_id, action, actor_user_id, notes)
                 VALUES (:did, :rid, 'stock_restored', :uid, :notes)",
                [
                    'did'  => (int)$dis['id'],
                    'rid'  => $receivingId,
                    'uid'  => $userId,
                    'notes'=> "Farm harvest stock restored: "
                            . number_format($totalRestored, 3) . " units → {$farmName}.",
                ]
            );
        }

        // ── F. Notify Farm Manager ─────────────────────────────────────────────
        $initiator = $this->db->fetchOne(
            "SELECT initiated_by FROM farm_dispatches WHERE id = :id", ['id' => $dispatchId]
        );
        if ($initiator && (int)$initiator['initiated_by']) {
            $totalExpected = (float)$receiving['total_expected'];
            $totalReceived = (float)$receiving['total_received'];
            $missing       = $totalExpected - $totalReceived;

            $this->notify(
                (int)$initiator['initiated_by'],
                'discrepancy_final_rejected',
                "Transfer {$rcvNumber} — Final Rejection",
                "Transfer {$rcvNumber} ({$farmName} → {$warehouseName}) has been "
                . "finally rejected after " . ReceivingModel::MAX_REJECTIONS . " General Manager reviews.\n"
                . "Sent: " . number_format($totalExpected, 3) . " | "
                . "Warehouse reported received: " . number_format($totalReceived, 3) . " | "
                . "Missing: " . number_format($missing, 3) . "\n"
                . "The transfer has been permanently closed and "
                . number_format($totalRestored, 3) . " units have been returned to the Farm.\n"
                . "Rejection Reason: {$reason}",
                'warehouse_receiving',
                $receivingId,
                url("dispatches/{$dispatchId}")
            );
        }

        // ── G. Notify Warehouse Manager ────────────────────────────────────────
        $wmUserId  = (int)($receiving['received_by'] ?? 0);
        $rcvNumber = $receiving['receiving_number'];
        if ($wmUserId) {
            $this->notify(
                $wmUserId,
                'discrepancy_final_rejected',
                "Receiving {$rcvNumber} — Final Rejection",
                "Receiving {$rcvNumber} has been finally rejected by the General Manager "
                . "after " . ReceivingModel::MAX_REJECTIONS . " rejection attempts.\n"
                . "Resubmission is no longer allowed. The transfer is permanently closed.\n"
                . "Source farm stock has been automatically restored.\n"
                . "Reason: {$reason}",
                'warehouse_receiving',
                $receivingId,
                url("warehouse/receiving/{$receivingId}")
            );
        }

        // ── H. Business audit trail ────────────────────────────────────────────
        $this->auditLog->logBusiness([
            'action'       => 'discrepancy_final_rejected',
            'module'       => 'warehouse',
            'entity_type'  => 'receiving',
            'entity_label' => $rcvNumber,
            'record_id'    => $receivingId,
            'description'  => "FINAL REJECTION: receiving {$rcvNumber} permanently closed "
                            . "after " . ReceivingModel::MAX_REJECTIONS . " GM rejections. "
                            . number_format($totalRestored, 3) . " units restored to {$farmName}. "
                            . "Reason: {$reason}",
            'warehouse_id' => $warehouseId,
            'farm_id'      => (int)$dispatch['farm_id'],
        ]);
    }

    /**
     * Warehouse Manager resubmits a receiving discrepancy after GM rejection.
     *
     * RULES:
     *  - Receiving must be in 'gm_rejected' status.
     *  - WM provides corrected received quantities and reasons.
     *  - Old discrepancy records are soft-reset (gm_action cleared, status back
     *    to 'under_review') so audit history is preserved.
     *  - New discrepancy records are created for any remaining variances.
     *  - Receiving status returns to 'discrepancy_pending'.
     *  - GMs are re-notified.
     *  - If the WM confirms the quantities NOW match (no discrepancy), the
     *    receiving is completed immediately as a normal full receipt.
     *
     * @param int   $receivingId
     * @param array $items  [{receiving_item_id, quantity_received, discrepancy_reason}]
     * @throws \RuntimeException
     */
    public function resubmitReceivingDiscrepancy(int $receivingId, array $items): void
    {
        $receiving = $this->rcvModel->getWithItems($receivingId);
        if (!$receiving) {
            throw new \RuntimeException("Receiving record #{$receivingId} not found.");
        }

        // Block if already finally rejected
        if ((int)($receiving['final_rejected'] ?? 0) === 1
            || $receiving['status'] === ReceivingModel::STATUS_FINAL_REJECTED) {
            throw new \RuntimeException(
                "This receiving has been permanently closed after "
                . ReceivingModel::MAX_REJECTIONS . " General Manager rejections. "
                . "Resubmission is no longer allowed."
            );
        }

        if ($receiving['status'] !== ReceivingModel::STATUS_GM_REJECTED) {
            throw new \RuntimeException(
                "Only receivings with status 'GM Rejected' can be resubmitted. "
                . "Current status: " . ReceivingModel::statusLabel($receiving['status']) . "."
            );
        }

        // Validate: if any item still has a variance, a reason is required
        $existingItems = $receiving['items'];
        $itemMap = [];
        foreach ($existingItems as $ei) {
            $itemMap[(int)$ei['id']] = $ei;
        }
        foreach ($items as $item) {
            $rcvItemId = (int)($item['receiving_item_id'] ?? 0);
            $orig      = $itemMap[$rcvItemId] ?? null;
            if (!$orig) continue;
            $expected = (float)$orig['quantity_expected'];
            $received = (float)($item['quantity_received'] ?? 0);
            if (abs($expected - $received) > 0.001) {
                $reason = trim($item['discrepancy_reason'] ?? '');
                if ($reason === '') {
                    $productName = $orig['product_name'] ?? "Item #{$rcvItemId}";
                    throw new \RuntimeException(
                        "Discrepancy reason is required for \"{$productName}\" "
                        . "(Expected: " . number_format($expected, 3) . ", "
                        . "Received: " . number_format($received, 3) . ")."
                    );
                }
            }
        }

        $userId      = (int)SessionManager::userId();
        $warehouseId = (int)$receiving['warehouse_id'];
        $rcvNumber   = $receiving['receiving_number'];
        $dispatchId  = (int)$receiving['dispatch_id'];

        $this->db->transaction(function () use (
            $receivingId, $receiving, $items, $itemMap,
            $userId, $warehouseId, $rcvNumber, $dispatchId
        ): void {
            // ── 1. Soft-reset existing discrepancy records ──────────────────
            // Clear gm_action so they become reviewable again; preserve the
            // audit trail by keeping the old records (history is in workflow log).
            $this->db->execute(
                "UPDATE warehouse_discrepancies
                 SET gm_action      = NULL,
                     gm_reviewed_by = NULL,
                     gm_reviewed_at = NULL,
                     gm_notes       = NULL,
                     status         = 'under_review',
                     updated_at     = NOW()
                 WHERE reference_type = 'receiving'
                   AND reference_id   = :rid",
                ['rid' => $receivingId]
            );

            // Log resubmission reset
            $discrepancyIds = $this->db->fetchAll(
                "SELECT id FROM warehouse_discrepancies
                 WHERE reference_type = 'receiving' AND reference_id = :rid",
                ['rid' => $receivingId]
            );
            foreach ($discrepancyIds as $dis) {
                $this->db->execute(
                    "INSERT INTO discrepancy_workflow_log
                        (discrepancy_id, receiving_id, action, actor_user_id, notes)
                     VALUES (:did, :rid, 'resubmitted', :uid, :notes)",
                    [
                        'did'   => (int)$dis['id'],
                        'rid'   => $receivingId,
                        'uid'   => $userId,
                        'notes' => "WM resubmitted discrepancy after GM rejection.",
                    ]
                );
            }

            // ── 2. Update receiving item quantities & recalculate totals ────
            $totalExpected  = 0.0;
            $totalReceived  = 0.0;
            $discrepancyCount = 0;

            foreach ($items as $item) {
                $rcvItemId = (int)($item['receiving_item_id'] ?? 0);
                $received  = max(0.0, (float)($item['quantity_received'] ?? 0));
                $orig      = $itemMap[$rcvItemId] ?? null;
                if (!$orig) continue;

                $expected       = (float)$orig['quantity_expected'];
                $variance       = $expected - $received;
                $varPct         = $expected > 0 ? round(abs($variance / $expected) * 100, 2) : 0;
                $totalExpected += $expected;
                $totalReceived += $received;

                $itemStatus = 'accepted';
                if ($received <= 0)        $itemStatus = 'rejected';
                elseif ($variance > 0.001) $itemStatus = 'partial';

                $this->db->execute(
                    "UPDATE warehouse_receiving_items
                     SET quantity_received  = :qr,
                         quantity_variance  = :qv,
                         variance_percent   = :vp,
                         item_status        = :st,
                         updated_at         = NOW()
                     WHERE id = :id",
                    [
                        'qr' => $received,
                        'qv' => $variance,
                        'vp' => $varPct,
                        'st' => $itemStatus,
                        'id' => $rcvItemId,
                    ]
                );

                if (abs($variance) > 0.001) {
                    $discrepancyCount++;
                    $disType = $variance > 0 ? 'short_delivery' : 'over_delivery';
                    $reason  = trim($item['discrepancy_reason'] ?? '');

                    // Update the existing discrepancy record with new quantities
                    $this->db->execute(
                        "UPDATE warehouse_discrepancies
                         SET actual_quantity   = :aq,
                             variance_quantity = :vq,
                             variance_percent  = :vp,
                             discrepancy_type  = :dt,
                             reason            = :reason,
                             updated_at        = NOW()
                         WHERE reference_type = 'receiving'
                           AND reference_id   = :rid
                           AND product_id     = :pid",
                        [
                            'aq'     => $received,
                            'vq'     => $variance,
                            'vp'     => $varPct,
                            'dt'     => $disType,
                            'reason' => $reason,
                            'rid'    => $receivingId,
                            'pid'    => (int)$orig['product_id'],
                        ]
                    );

                    // Log the resubmission with new quantities
                    foreach ($discrepancyIds as $dis) {
                        $this->db->execute(
                            "INSERT INTO discrepancy_workflow_log
                                (discrepancy_id, receiving_id, action, actor_user_id, notes, snapshot)
                             SELECT id, :rid, 'resubmitted_quantities', :uid, :notes, :snap
                             FROM warehouse_discrepancies
                             WHERE reference_type = 'receiving'
                               AND reference_id   = :rid2
                               AND product_id     = :pid",
                            [
                                'rid'   => $receivingId,
                                'uid'   => $userId,
                                'notes' => "WM updated quantities: expected={$expected}, received={$received}",
                                'snap'  => json_encode([
                                    'expected_quantity' => $expected,
                                    'actual_quantity'   => $received,
                                    'variance'          => $variance,
                                    'reason'            => $reason,
                                ]),
                                'rid2'  => $receivingId,
                                'pid'   => (int)$orig['product_id'],
                            ]
                        );
                    }
                }
            }

            // ── 3. Update receiving header totals ───────────────────────────
            $this->db->execute(
                "UPDATE warehouse_receiving
                 SET total_received = :tr, updated_at = NOW()
                 WHERE id = :id",
                ['tr' => $totalReceived, 'id' => $receivingId]
            );

            // ── 4. Set new status ───────────────────────────────────────────
            if ($discrepancyCount > 0) {
                // Still has discrepancies — return to GM review queue
                $this->db->execute(
                    "UPDATE warehouse_receiving SET status = 'discrepancy_pending', updated_at = NOW()
                     WHERE id = :id",
                    ['id' => $receivingId]
                );
                // Re-notify GMs
                $farmName      = $receiving['farm_name']      ?? "Farm #{$receiving['farm_id']}";
                $warehouseName = $receiving['warehouse_name'] ?? "Warehouse #{$warehouseId}";
                $this->notifyGMsOfDiscrepancy(
                    $receivingId, $rcvNumber,
                    $farmName, $warehouseName,
                    $totalExpected, $totalReceived
                );
            } else {
                // All quantities now match — complete immediately
                // (no discrepancies remain, apply inventory)
                $this->db->execute(
                    "UPDATE warehouse_receiving SET status = 'received', updated_at = NOW()
                     WHERE id = :id",
                    ['id' => $receivingId]
                );
                $this->db->execute(
                    "UPDATE farm_dispatches SET status = 'received', updated_at = NOW()
                     WHERE id = :id",
                    ['id' => $dispatchId]
                );

                // Apply inventory with confirmed quantities
                foreach ($items as $item) {
                    $rcvItemId = (int)($item['receiving_item_id'] ?? 0);
                    $received  = max(0.0, (float)($item['quantity_received'] ?? 0));
                    $orig      = $itemMap[$rcvItemId] ?? null;
                    if (!$orig || $received <= 0) continue;

                    $productId = (int)$orig['product_id'];
                    $unitId    = !empty($orig['unit_id']) ? (int)$orig['unit_id'] : null;

                    $movement = $this->invModel->increaseStock(
                        $warehouseId, $productId, $received, $unitId,
                        $orig['batch_number']    ?? null,
                        $orig['production_date'] ?? null,
                        $orig['expiry_date']     ?? null,
                        'receiving'
                    );

                    $this->smlModel->log([
                        'movement_type'    => StockMovementModel::TYPE_RECEIVING,
                        'location_type'    => 'warehouse',
                        'location_id'      => $warehouseId,
                        'product_id'       => $productId,
                        'batch_number'     => $orig['batch_number'] ?? null,
                        'quantity_before'  => $movement['before'],
                        'quantity_in'      => $received,
                        'quantity_out'     => 0,
                        'quantity_after'   => $movement['after'],
                        'reference_type'   => 'warehouse_receiving',
                        'reference_id'     => $receivingId,
                        'reference_number' => $rcvNumber,
                        'performed_by'     => $userId,
                        'notes'            => "Resubmit resolved — received from dispatch #{$dispatchId}",
                    ]);
                }

                // Notify Farm Manager
                $farmName      = $receiving['farm_name']      ?? "Farm";
                $warehouseName = $receiving['warehouse_name'] ?? "Warehouse";
                $this->notifyFarmManagerOfCompletion(
                    $dispatchId, $rcvNumber,
                    $totalExpected, $totalReceived,
                    $farmName, $warehouseName, false
                );
            }

            $this->auditLog->logBusiness([
                'action'       => 'discrepancy_resubmitted',
                'module'       => 'warehouse',
                'entity_type'  => 'receiving',
                'entity_label' => $rcvNumber,
                'record_id'    => $receivingId,
                'description'  => "WM (user #{$userId}) resubmitted receiving discrepancy for {$rcvNumber} "
                                . "after GM rejection. Remaining discrepancies: {$discrepancyCount}.",
                'warehouse_id' => $warehouseId,
            ]);
        });
    }

    /**
     * Warehouse Manager completes receiving after GM approval.
     *
     * RULES:
     *  - Receiving must be in 'gm_approved' status.
     *  - Inventory is updated with ACTUAL received quantities only.
     *  - Missing quantity is NOT added to inventory.
     *  - Farm Manager is notified.
     *  - Workflow log updated.
     *
     * @throws \RuntimeException
     */
    public function completeReceivingAfterGMApproval(int $receivingId): void
    {
        $receiving = $this->rcvModel->getWithItems($receivingId);
        if (!$receiving) {
            throw new \RuntimeException("Receiving record #{$receivingId} not found.");
        }
        if ($receiving['status'] !== 'gm_approved') {
            throw new \RuntimeException(
                "Receiving must be in 'GM Approved' status before you can complete it. "
                . "Current status: " . ReceivingModel::statusLabel($receiving['status']) . "."
            );
        }

        $userId      = (int)SessionManager::userId();
        $warehouseId = (int)$receiving['warehouse_id'];
        $rcvNumber   = $receiving['receiving_number'];
        $dispatchId  = (int)$receiving['dispatch_id'];

        $this->db->transaction(function () use (
            $receivingId, $receiving, $warehouseId, $rcvNumber, $dispatchId, $userId
        ): void {
            $items         = $receiving['items'];
            $totalExpected = (float)$receiving['total_expected'];
            $totalReceived = (float)$receiving['total_received'];
            $farmName      = $receiving['farm_name'] ?? "Farm";
            $warehouseName = $receiving['warehouse_name'] ?? "Warehouse";

            // Apply inventory for each item using ACTUAL received qty
            foreach ($items as $item) {
                $received  = (float)$item['quantity_received'];
                $productId = (int)$item['product_id'];
                $unitId    = !empty($item['unit_id']) ? (int)$item['unit_id'] : null;

                if ($received <= 0) continue;

                $movement = $this->invModel->increaseStock(
                    $warehouseId,
                    $productId,
                    $received,
                    $unitId,
                    $item['batch_number']    ?? null,
                    $item['production_date'] ?? null,
                    $item['expiry_date']     ?? null,
                    'receiving'
                );

                $this->smlModel->log([
                    'movement_type'    => StockMovementModel::TYPE_RECEIVING,
                    'location_type'    => 'warehouse',
                    'location_id'      => $warehouseId,
                    'product_id'       => $productId,
                    'batch_number'     => $item['batch_number'] ?? null,
                    'quantity_before'  => $movement['before'],
                    'quantity_in'      => $received,
                    'quantity_out'     => 0,
                    'quantity_after'   => $movement['after'],
                    'reference_type'   => 'warehouse_receiving',
                    'reference_id'     => $receivingId,
                    'reference_number' => $rcvNumber,
                    'performed_by'     => $userId,
                    'notes'            => "Received (post-GM-approval) from dispatch #{$dispatchId}",
                ]);

                if (!empty($item['batch_number'])) {
                    $this->batchModel->upsertBatch(
                        'warehouse', $warehouseId, $productId,
                        $item['batch_number'],
                        $received,
                        $unitId,
                        $item['production_date'] ?? null,
                        $item['expiry_date']     ?? null,
                        'receiving', $receivingId
                    );
                }

                // Mark discrepancy inventory_applied
                $this->db->execute(
                    "UPDATE warehouse_discrepancies
                     SET inventory_applied = 1, updated_at = NOW()
                     WHERE reference_type = 'receiving' AND reference_id = :rid
                       AND product_id = :pid",
                    ['rid' => $receivingId, 'pid' => $productId]
                );
            }

            // Set receiving status to 'received'
            $this->db->execute(
                "UPDATE warehouse_receiving SET status = 'received', updated_at = NOW() WHERE id = :id",
                ['id' => $receivingId]
            );

            // Update farm dispatch status
            $this->db->execute(
                "UPDATE farm_dispatches SET status = 'received_with_discrepancy', updated_at = NOW()
                 WHERE id = :id",
                ['id' => $dispatchId]
            );

            // Log workflow step for each discrepancy
            $discrepancyIds = $this->db->fetchAll(
                "SELECT id FROM warehouse_discrepancies
                 WHERE reference_type = 'receiving' AND reference_id = :rid",
                ['rid' => $receivingId]
            );
            foreach ($discrepancyIds as $dis) {
                $this->db->execute(
                    "INSERT INTO discrepancy_workflow_log
                        (discrepancy_id, receiving_id, action, actor_user_id, notes)
                     VALUES (:did, :rid, 'inventory_applied', :uid, :notes)",
                    [
                        'did'  => (int)$dis['id'],
                        'rid'  => $receivingId,
                        'uid'  => $userId,
                        'notes'=> "WM completed receiving. Inventory updated with actual qty={$totalReceived}.",
                    ]
                );
            }

            // Notify Farm Manager
            $this->notifyFarmManagerOfCompletion(
                $dispatchId, $rcvNumber,
                $totalExpected, $totalReceived,
                $farmName, $warehouseName, true
            );

            // Log notification step
            foreach ($discrepancyIds as $dis) {
                $this->db->execute(
                    "INSERT INTO discrepancy_workflow_log
                        (discrepancy_id, receiving_id, action, actor_user_id, notes)
                     VALUES (:did, :rid, 'farm_notified', :uid, :notes)",
                    [
                        'did'  => (int)$dis['id'],
                        'rid'  => $receivingId,
                        'uid'  => $userId,
                        'notes'=> "Farm Manager notified of discrepancy resolution.",
                    ]
                );
            }

            $this->auditLog->logBusiness([
                'action'       => ActivityLogModel::ACTION_STOCK_RECEIVED,
                'module'       => 'warehouse',
                'entity_type'  => 'receiving',
                'entity_label' => $rcvNumber,
                'record_id'    => $receivingId,
                'description'  => "Receiving {$rcvNumber} completed post-GM-approval. "
                                . "Inventory increased by {$totalReceived} (actual). "
                                . "Missing " . ($totalExpected - $totalReceived) . " NOT added.",
                'warehouse_id' => $warehouseId,
            ]);
        });
    }

    // =========================================================
    // TRANSFER WORKFLOW
    // =========================================================

    /**
     * Create a warehouse → shop transfer order.
     * Validates available stock before reserving.
     *
     * @param int    $warehouseId
     * @param int    $shopId
     * @param array  $items [{product_id, unit_id, batch_number, expiry_date,
     *                        quantity_requested, notes}]
     * @param string $notes
     * @return int   Transfer ID
     * @throws \RuntimeException on insufficient stock
     */
    public function createTransfer(
        int    $warehouseId,
        int    $shopId,
        array  $items,
        string $notes = ''
    ): int {
        if (empty($items)) {
            throw new \RuntimeException('No items provided for transfer.');
        }

        $userId = (int)SessionManager::userId();

        // Pre-validate stock BEFORE opening transaction
        foreach ($items as $item) {
            $stock = $this->invModel->getStock($warehouseId, (int)$item['product_id']);
            $requested = (float)$item['quantity_requested'];
            $available = (float)($stock['available_quantity'] ?? 0);

            if ($requested <= 0) {
                throw new \RuntimeException("Quantity must be greater than zero.");
            }
            if ($available < $requested) {
                $name = $stock['product_name'] ?? "Product #{$item['product_id']}";
                throw new \RuntimeException(
                    "Insufficient stock for {$name}. " .
                    "Available: " . number_format($available, 3) .
                    ", Requested: " . number_format($requested, 3) . "."
                );
            }
        }

        $transferId = $this->db->transaction(function () use (
            $warehouseId, $shopId, $items, $notes, $userId
        ): int {
            $trnNumber   = $this->trnModel->generateTransferNumber();
            $totalQty    = array_sum(array_column($items, 'quantity_requested'));

            $this->db->execute(
                "INSERT INTO warehouse_transfers
                    (transfer_number, warehouse_id, shop_id, status,
                     initiated_by, transfer_date,
                     total_items, total_quantity, notes)
                 VALUES
                    (:tnum, :wid, :sid, 'pending', :by, CURDATE(),
                     :ti, :tq, :notes)",
                [
                    'tnum'  => $trnNumber,
                    'wid'   => $warehouseId,
                    'sid'   => $shopId,
                    'by'    => $userId,
                    'ti'    => count($items),
                    'tq'    => $totalQty,
                    'notes' => $notes ?: null,
                ]
            );
            $transferId = $this->db->lastInsertId();

            // Insert items and reserve stock
            foreach ($items as $item) {
                $qty = (float)$item['quantity_requested'];

                $this->db->execute(
                    "INSERT INTO warehouse_transfer_items
                        (transfer_id, product_id, unit_id, batch_number, expiry_date,
                         quantity_requested, quantity_sent, item_status, notes)
                     VALUES (:tid, :pid, :uid, :bn, :ed, :qr, :qs, 'pending', :notes)",
                    [
                        'tid'   => $transferId,
                        'pid'   => (int)$item['product_id'],
                        'uid'   => !empty($item['unit_id']) ? (int)$item['unit_id'] : null,
                        'bn'    => $item['batch_number'] ?? null,
                        'ed'    => $item['expiry_date']  ?? null,
                        'qr'    => $qty,
                        'qs'    => $qty,
                        'notes' => $item['notes'] ?? null,
                    ]
                );

                // Reserve stock
                $this->invModel->reserveStock($warehouseId, (int)$item['product_id'], $qty);
            }

            // Business audit trail
            $shopName = $this->db->fetchOne(
                "SELECT name FROM shops WHERE id = :id", ['id' => $shopId]
            )['name'] ?? "Shop #{$shopId}";

            $this->auditLog->logBusiness([
                'action'       => ActivityLogModel::ACTION_TRANSFER_CREATED,
                'module'       => 'warehouse',
                'entity_type'  => 'transfer',
                'entity_label' => $trnNumber,
                'record_id'    => $transferId,
                'description'  => "Transfer {$trnNumber} created: {$totalQty} units to {$shopName}",
                'warehouse_id' => $warehouseId,
                'shop_id'      => $shopId,
                'new_values'   => [
                    'transfer_number' => $trnNumber,
                    'total_quantity'  => $totalQty,
                    'shop'            => $shopName,
                    'status'          => 'pending',
                ],
            ]);

            return $transferId;
        });

        // ── Notify all GMs that a new transfer needs their approval ──────────
        // Called outside the transaction so a notification failure never rolls
        // back the successfully created transfer.
        try {
            $trnFull     = $this->trnModel->getWithItems($transferId);
            $wName       = $trnFull['warehouse_name'] ?? "Warehouse #{$warehouseId}";
            $sName       = $trnFull['shop_name']      ?? "Shop #{$shopId}";
            $products    = array_unique(array_column($trnFull['items'] ?? [], 'product_name'));
            $productList = implode(', ', array_filter($products)) ?: count($trnFull['items'] ?? []) . ' product(s)';
            $qty         = array_sum(array_column($trnFull['items'] ?? [], 'quantity_requested'));
            $this->notifyGMsOfTransferPending(
                $transferId,
                $trnFull['transfer_number'] ?? "TRN-{$transferId}",
                $wName,
                $sName,
                (float)$qty,
                $productList,
                $userId
            );
        } catch (\Throwable) {}

        return $transferId;
    }

    /**
     * Approve a transfer (pending → approved).
     * Only General Manager / Admin may approve.
     * The user who created the transfer CANNOT approve it (self-approval prevention).
     *
     * @throws \RuntimeException on validation failure
     */
    public function approveTransfer(int $transferId): void
    {
        $transfer = $this->trnModel->getWithItems($transferId);
        if (!$transfer) {
            throw new \RuntimeException("Transfer #{$transferId} not found.");
        }
        if ($transfer['status'] !== TransferModel::STATUS_PENDING) {
            throw new \RuntimeException(
                "Only pending transfers can be approved. Current status: " .
                TransferModel::statusLabel($transfer['status']) . "."
            );
        }

        $userId = (int)SessionManager::userId();

        // Self-approval prevention: the creator cannot approve their own transfer
        if ($userId > 0 && $userId === (int)($transfer['initiated_by'] ?? 0)) {
            throw new \RuntimeException(
                "You cannot approve a transfer you created. " .
                "An authorized General Manager or Administrator must approve it."
            );
        }

        $trnNumber = $transfer['transfer_number'];

        $this->db->execute(
            "UPDATE warehouse_transfers
             SET status = :s, approved_by = :ab, updated_at = NOW()
             WHERE id = :id",
            ['s' => TransferModel::STATUS_APPROVED, 'ab' => $userId, 'id' => $transferId]
        );

        $this->auditLog->logBusiness([
            'action'       => ActivityLogModel::ACTION_TRANSFER_APPROVED,
            'module'       => 'warehouse',
            'entity_type'  => 'transfer',
            'entity_label' => $trnNumber,
            'record_id'    => $transferId,
            'description'  => "Transfer {$trnNumber} approved by user #{$userId}.",
            'warehouse_id' => (int)$transfer['warehouse_id'],
            'shop_id'      => (int)$transfer['shop_id'],
            'old_values'   => ['status' => TransferModel::STATUS_PENDING],
            'new_values'   => ['status' => TransferModel::STATUS_APPROVED, 'approved_by' => $userId],
        ]);

        // ── Notify the Warehouse Manager who created the transfer ────────────
        $initiatorUserId = (int)($transfer['initiated_by'] ?? 0);
        if ($initiatorUserId) {
            $warehouseName = $this->db->fetchOne(
                "SELECT name FROM warehouses WHERE id = :id",
                ['id' => (int)$transfer['warehouse_id']]
            )['name'] ?? "Warehouse #{$transfer['warehouse_id']}";

            $shopName = $this->db->fetchOne(
                "SELECT name FROM shops WHERE id = :id",
                ['id' => (int)$transfer['shop_id']]
            )['name'] ?? "Shop #{$transfer['shop_id']}";

            $totalQty    = array_sum(array_column($transfer['items'], 'quantity_requested'));
            $productNames = array_unique(array_column($transfer['items'], 'product_name'));
            $productList  = implode(', ', array_filter($productNames)) ?: count($transfer['items']) . ' product(s)';

            $this->notify(
                $initiatorUserId,
                'transfer_approved',
                "Transfer {$trnNumber} Approved ✓",
                "Transfer {$trnNumber} ({$warehouseName} → {$shopName}) has been approved "
                . "by the General Manager.\n"
                . "Products: {$productList}\n"
                . "Total Quantity: " . number_format($totalQty, 3) . "\n"
                . "You can now dispatch the goods to the shop.",
                'warehouse_transfers',
                $transferId,
                url("transfers/{$transferId}")
            );
        }
        // ────────────────────────────────────────────────────────────────────
    }

    /**
     * Reject a Warehouse→Shop transfer (pending or approved → rejected).
     *
     * RULES:
     *  - Only callable by ROLE_GENERAL_MANAGER.
     *  - Transfer must be in 'pending' OR 'approved' status (not yet dispatched).
     *  - Rejection reason is MANDATORY.
     *  - Stock reservations are released (available_quantity restored).
     *  - A 'reversal' stock movement is logged for full audit trail.
     *  - Warehouse Manager is notified with the rejection reason.
     *  - Transfer status → 'rejected' (distinct from 'cancelled').
     *  - Idempotency: throws if transfer is already rejected/cancelled.
     *
     * IMPORTANT: This handles the BEFORE-DISPATCH rejection case only.
     *   At both 'pending' and 'approved' stages, stock is RESERVED
     *   (reserved_quantity, not yet deducted from current_quantity).
     *   releaseReservation() restores available_quantity back to the warehouse.
     *   No physical stock left the warehouse, so no actual deduction to reverse.
     *   The reversal log entry documents that the reservation was released.
     *
     * Stock Restoration Rule:
     *   SOURCE WAREHOUSE ← stock reservations released → available_quantity restored
     *   DESTINATION SHOP ← no change (stock never arrived)
     *
     * Do NOT confuse with receiving discrepancy workflow (gmRejectTransferDiscrepancy).
     *
     * @throws \RuntimeException on validation or state failure
     */
    public function rejectTransfer(int $transferId, string $reason = ''): void
    {
        // Reason is mandatory — no silent rejections
        if (trim($reason) === '') {
            throw new \RuntimeException(
                'A rejection reason is required. Please state why this transfer is being rejected.'
            );
        }

        $transfer = $this->trnModel->getWithItems($transferId);
        if (!$transfer) {
            throw new \RuntimeException("Transfer #{$transferId} not found.");
        }

        // Idempotency guard — prevent double rejection
        if ($transfer['status'] === TransferModel::STATUS_REJECTED) {
            throw new \RuntimeException(
                "Transfer {$transfer['transfer_number']} has already been rejected. " .
                "It cannot be rejected again."
            );
        }

        // Rejectable statuses: pending (awaiting GM approval) or approved (GM-approved but not yet dispatched)
        // Both states have stock RESERVED but not physically deducted — releaseReservation() handles both.
        $rejectableStatuses = [
            TransferModel::STATUS_PENDING,
            TransferModel::STATUS_APPROVED,
        ];
        if (!in_array($transfer['status'], $rejectableStatuses, true)) {
            throw new \RuntimeException(
                "Only pending or approved transfers can be rejected before dispatch. " .
                "Current status: " . TransferModel::statusLabel($transfer['status']) . ". " .
                "Transfers that have been dispatched (in transit) or already received " .
                "cannot be rejected here."
            );
        }

        $userId      = (int)SessionManager::userId();
        $warehouseId = (int)$transfer['warehouse_id'];
        $trnNumber   = $transfer['transfer_number'];

        $this->db->transaction(function () use (
            $transfer, $transferId, $warehouseId, $trnNumber, $userId, $reason
        ): void {

            // ── 1. Release all stock reservations ──────────────────────────
            // At 'pending' stage, stock is reserved (reserved_qty++) but NOT
            // physically deducted from current_quantity. Releasing brings
            // available_quantity back: available = current - reserved.
            foreach ($transfer['items'] as $item) {
                $qty       = (float)$item['quantity_requested'];
                $productId = (int)$item['product_id'];

                if ($qty <= 0) continue;

                // Capture before/after for movement log
                $stockBefore = $this->invModel->getStock($warehouseId, $productId);
                $availBefore = (float)($stockBefore['available_quantity'] ?? 0);

                $this->invModel->releaseReservation($warehouseId, $productId, $qty);

                $stockAfter  = $this->invModel->getStock($warehouseId, $productId);
                $availAfter  = (float)($stockAfter['available_quantity'] ?? 0);
                $currQty     = (float)($stockAfter['current_quantity']   ?? 0);

                // ── 2. Log reversal stock movement (immutable audit entry) ──
                // Records clearly: what was reserved, what was restored, why.
                $this->smlModel->log([
                    'movement_type'    => StockMovementModel::TYPE_REVERSAL,
                    'location_type'    => 'warehouse',
                    'location_id'      => $warehouseId,
                    'product_id'       => $productId,
                    'batch_number'     => $item['batch_number'] ?? null,
                    'quantity_before'  => $currQty,   // current_quantity unchanged (reservation only)
                    'quantity_in'      => $qty,        // reservation released = stock made available again
                    'quantity_out'     => 0,
                    'quantity_after'   => $currQty,    // current_quantity stays the same
                    'reference_type'   => 'warehouse_transfers',
                    'reference_id'     => $transferId,
                    'reference_number' => $trnNumber,
                    'performed_by'     => $userId,
                    'notes'            => "Transfer {$trnNumber} rejected by GM (user #{$userId}). " .
                                         "Reservation of {$qty} units released. " .
                                         "Available restored from " . number_format($availBefore, 3) .
                                         " to " . number_format($availAfter, 3) . ". " .
                                         "Reason: {$reason}",
                ]);
            }

            // ── 3. Set transfer status to 'rejected' ───────────────────────
            // Distinct from 'cancelled' — rejected = GM action, cancelled = WM action
            $this->db->execute(
                "UPDATE warehouse_transfers
                 SET status           = :s,
                     rejection_reason = :reason,
                     rejected_by      = :rb,
                     rejected_at      = NOW(),
                     updated_at       = NOW()
                 WHERE id = :id",
                [
                    's'      => TransferModel::STATUS_REJECTED,
                    'reason' => $reason,
                    'rb'     => $userId,
                    'id'     => $transferId,
                ]
            );

            // ── 4. Notify the Warehouse Manager who created the transfer ────
            $initiatorUserId = (int)($transfer['initiated_by'] ?? 0);
            if ($initiatorUserId) {
                $shopName = $this->db->fetchOne(
                    "SELECT name FROM shops WHERE id = :id",
                    ['id' => (int)$transfer['shop_id']]
                )['name'] ?? "Shop #{$transfer['shop_id']}";

                $warehouseName = $this->db->fetchOne(
                    "SELECT name FROM warehouses WHERE id = :id",
                    ['id' => $warehouseId]
                )['name'] ?? "Warehouse #{$warehouseId}";

                $totalQty = array_sum(array_column($transfer['items'], 'quantity_requested'));
                $productNames = array_unique(array_column($transfer['items'], 'product_name'));
                $productList  = implode(', ', array_filter($productNames));

                $this->notify(
                    $initiatorUserId,
                    'transfer_rejected',
                    "Transfer {$trnNumber} Rejected by General Manager",
                    "Transfer {$trnNumber} ({$warehouseName} → {$shopName}) " .
                    "has been rejected by the General Manager.\n" .
                    "Products: {$productList}\n" .
                    "Total Quantity: " . number_format($totalQty, 3) . "\n" .
                    "Rejection Reason: {$reason}\n\n" .
                    "Stock reservations have been automatically released. " .
                    "Your warehouse available stock has been restored.",
                    'warehouse_transfers',
                    $transferId,
                    url("transfers/{$transferId}")
                );
            }

            // ── 5. Business audit trail ─────────────────────────────────────
            $this->auditLog->logBusiness([
                'action'       => ActivityLogModel::ACTION_TRANSFER_REJECTED,
                'module'       => 'transfer',
                'entity_type'  => 'transfer',
                'entity_label' => $trnNumber,
                'record_id'    => $transferId,
                'description'  => "Transfer {$trnNumber} REJECTED by GM (user #{$userId}) " .
                                  "from status '{$transfer['status']}'. " .
                                  "Reason: {$reason}. " .
                                  "Stock reservations released. Warehouse inventory restored.",
                'warehouse_id' => $warehouseId,
                'shop_id'      => (int)$transfer['shop_id'],
                'old_values'   => ['status' => $transfer['status']],
                'new_values'   => [
                    'status'           => TransferModel::STATUS_REJECTED,
                    'rejection_reason' => $reason,
                    'rejected_by'      => $userId,
                ],
            ]);
        });
    }

    /**
     * Mark transfer as dispatched (approved → in_transit).
     * REQUIRES status = approved. Cannot dispatch a pending (unapproved) transfer.
     * At this point warehouse stock is DEDUCTED.
     */
    public function dispatchTransfer(int $transferId): void
    {
        $transfer = $this->trnModel->getWithItems($transferId);
        if (!$transfer) {
            throw new \RuntimeException("Transfer #{$transferId} not found.");
        }
        // Dispatch requires prior approval — pending alone is not enough
        if ($transfer['status'] !== TransferModel::STATUS_APPROVED) {
            throw new \RuntimeException(
                "Transfer must be approved before it can be dispatched. " .
                "Current status: " . TransferModel::statusLabel($transfer['status']) . ". " .
                "A General Manager or Administrator must approve it first."
            );
        }

        $userId      = (int)SessionManager::userId();
        $warehouseId = (int)$transfer['warehouse_id'];
        $trnNumber   = $transfer['transfer_number'];

        $this->db->transaction(function () use ($transfer, $transferId, $warehouseId, $trnNumber, $userId): void {
            foreach ($transfer['items'] as $item) {
                $qty       = (float)$item['quantity_sent'];
                $productId = (int)$item['product_id'];

                // Release reservation and decrease actual stock
                $this->invModel->releaseReservation($warehouseId, $productId, $qty);
                $movement = $this->invModel->decreaseStock($warehouseId, $productId, $qty, 'transfer_out');

                $this->smlModel->log([
                    'movement_type'    => StockMovementModel::TYPE_TRANSFER_OUT,
                    'location_type'    => 'warehouse',
                    'location_id'      => $warehouseId,
                    'product_id'       => $productId,
                    'batch_number'     => $item['batch_number'] ?? null,
                    'quantity_before'  => $movement['before'],
                    'quantity_in'      => 0,
                    'quantity_out'     => $qty,
                    'quantity_after'   => $movement['after'],
                    'reference_type'   => 'warehouse_transfers',
                    'reference_id'     => $transferId,
                    'reference_number' => $trnNumber,
                    'performed_by'     => $userId,
                    'notes'            => "Transfer dispatched to shop #{$transfer['shop_id']}",
                ]);

                // Deduct from inventory_batches (batch traceability — FEFO deduction)
                if (!empty($item['batch_number'])) {
                    $this->batchModel->upsertBatch(
                        'warehouse', $warehouseId, $productId,
                        $item['batch_number'],
                        -$qty,           // negative delta = deduction
                        !empty($item['unit_id']) ? (int)$item['unit_id'] : null,
                        null, null,
                        'transfer', $transferId
                    );
                }
            }

            $this->trnModel->setStatus($transferId, TransferModel::STATUS_IN_TRANSIT, $userId);

            $this->auditLog->logBusiness([
                'action'       => ActivityLogModel::ACTION_TRANSFER_DISPATCHED,
                'module'       => 'warehouse',
                'entity_type'  => 'transfer',
                'entity_label' => $trnNumber,
                'record_id'    => $transferId,
                'description'  => "Transfer {$trnNumber} dispatched to " . ($transfer['shop_name'] ?? ''),
                'warehouse_id' => $warehouseId,
                'shop_id'      => (int)$transfer['shop_id'],
            ]);
        });

        // ── Notify destination shop salesperson(s) that goods are on the way ─
        // Called outside the transaction — failure must never roll back the dispatch.
        try {
            $warehouseName = $transfer['warehouse_name'] ?? "Warehouse #{$warehouseId}";
            $shopName      = $transfer['shop_name']      ?? "Shop #{$transfer['shop_id']}";
            $productNames  = array_unique(array_column($transfer['items'] ?? [], 'product_name'));
            $productList   = implode(', ', array_filter($productNames))
                             ?: count($transfer['items'] ?? []) . ' product(s)';
            $totalQty      = array_sum(array_column($transfer['items'] ?? [], 'quantity_sent'));

            $this->notifyShopOfDispatch(
                $transferId,
                $trnNumber,
                $warehouseName,
                $shopName,
                (int)$transfer['shop_id'],
                (float)$totalQty,
                $productList
            );
        } catch (\Throwable) {}
        // ─────────────────────────────────────────────────────────────────────
    }

    /**
     * Confirm transfer received by shop (Salesperson).
     *
     * DISCREPANCY WORKFLOW (Warehouse → Shop):
     *   If received qty != sent qty for ANY item:
     *     - A discrepancy reason is REQUIRED for that item (validated before transaction).
     *     - Transfer status → 'discrepancy_pending'.
     *     - warehouse_discrepancies records created (status = 'under_review').
     *     - Shop inventory is NOT updated yet.
     *     - GM is notified for review.
     *     - Salesperson sees "Pending GM Approval" status.
     *
     *   After GM approves (gmApproveTransferDiscrepancy):
     *     - Shop inventory updated with ACTUAL received quantities only.
     *     - Missing qty is NOT added to shop inventory.
     *     - Transfer status → 'received_with_discrepancy'.
     *     - Warehouse Manager + Salesperson notified.
     *
     *   If GM rejects (gmRejectTransferDiscrepancy):
     *     - Transfer status → 'gm_rejected'.
     *     - Shop inventory NOT updated.
     *     - Salesperson notified with rejection reason.
     *     - Salesperson may resubmit with corrected data (resubmitTransferDiscrepancy).
     *
     * Normal flow (no discrepancy): shop inventory updated immediately.
     *
     * @throws \RuntimeException on validation failure
     */
    public function confirmTransferReceived(
        int    $transferId,
        array  $itemsReceived,
        string $notes = ''
    ): array {
        $transfer = $this->trnModel->getWithItems($transferId);
        if (!$transfer) {
            throw new \RuntimeException("Transfer #{$transferId} not found.");
        }

        // Allow re-submission after GM rejection — salesperson corrects and resubmits
        // Block if transfer is finally rejected (no resubmission after 2nd rejection)
        if ((int)($transfer['discrepancy_final_rejected'] ?? 0) === 1
            || $transfer['status'] === TransferModel::STATUS_FINAL_REJECTED) {
            throw new \RuntimeException(
                "Transfer {$transfer['transfer_number']} has been permanently closed after "
                . TransferModel::MAX_DISCREPANCY_REJECTIONS . " General Manager rejections. "
                . "Resubmission is no longer allowed."
            );
        }

        $allowedStatuses = [
            TransferModel::STATUS_IN_TRANSIT,
            TransferModel::STATUS_GM_REJECTED,
        ];
        if (!in_array($transfer['status'], $allowedStatuses, true)) {
            throw new \RuntimeException(
                "Transfer must be In Transit (or GM-Rejected for re-submission) to be received. " .
                "Current status: " . TransferModel::statusLabel($transfer['status']) . "."
            );
        }

        // Self-receive prevention
        $receiverId  = (int)SessionManager::userId();
        $initiatorId = (int)($transfer['initiated_by'] ?? 0);
        if ($receiverId > 0 && $receiverId === $initiatorId) {
            throw new \RuntimeException(
                "Security violation: You cannot confirm receipt of a transfer you created. " .
                "A different authorized user at the destination must receive this transfer."
            );
        }

        // ── DISCREPANCY VALIDATION ──────────────────────────────────────────
        // If any item has a variance, a reason is REQUIRED before we open a transaction.
        foreach ($itemsReceived as $itemData) {
            $qtyRecv = (float)($itemData['quantity_received'] ?? 0);

            // Find original item
            $origItem = null;
            foreach ($transfer['items'] as $ti) {
                if ((int)$ti['id'] === (int)($itemData['item_id'] ?? 0)) {
                    $origItem = $ti;
                    break;
                }
            }
            if (!$origItem) continue;

            $qtySent = (float)$origItem['quantity_sent'];
            if (abs($qtySent - $qtyRecv) > 0.001) {
                $reason = trim($itemData['discrepancy_reason'] ?? '');
                if ($reason === '') {
                    $productName = $origItem['product_name'] ?? "Product #{$origItem['product_id']}";
                    throw new \RuntimeException(
                        "Discrepancy reason is required for \"{$productName}\" " .
                        "(Sent: " . number_format($qtySent, 3) . ", " .
                        "Received: " . number_format($qtyRecv, 3) . "). " .
                        "Please enter the reason for the quantity difference."
                    );
                }
            }
        }

        $userId      = $receiverId;
        $warehouseId = (int)$transfer['warehouse_id'];
        $shopId      = (int)$transfer['shop_id'];
        $trnNumber   = $transfer['transfer_number'];

        return $this->db->transaction(function () use (
            $transfer, $transferId, $warehouseId, $shopId,
            $trnNumber, $itemsReceived, $notes, $userId
        ): array {
            $warehouseName = $this->db->fetchOne(
                "SELECT name FROM warehouses WHERE id = :id", ['id' => $warehouseId]
            )['name'] ?? "Warehouse #{$warehouseId}";

            $shopName = $this->db->fetchOne(
                "SELECT name FROM shops WHERE id = :id", ['id' => $shopId]
            )['name'] ?? "Shop #{$shopId}";

            // ── If resubmitting after GM rejection, reset previous discrepancy
            //    records so the new submission starts clean.
            //    The old records are soft-reset (gm_action cleared, status back
            //    to under_review) so the audit history is preserved.
            if ($transfer['status'] === TransferModel::STATUS_GM_REJECTED) {
                $this->db->execute(
                    "UPDATE warehouse_discrepancies
                     SET gm_action      = NULL,
                         gm_reviewed_by = NULL,
                         gm_reviewed_at = NULL,
                         gm_notes       = NULL,
                         status         = 'under_review',
                         updated_at     = NOW()
                     WHERE reference_type = 'transfer'
                       AND reference_id   = :tid",
                    ['tid' => $transferId]
                );
                // Log the resubmission reset
                $this->db->execute(
                    "INSERT INTO discrepancy_workflow_log
                        (discrepancy_id, transfer_id, action, actor_user_id, notes)
                     SELECT id, :tid, 'resubmitted', :uid, 'Salesperson resubmitted after GM rejection.'
                     FROM warehouse_discrepancies
                     WHERE reference_type = 'transfer' AND reference_id = :tid2",
                    ['tid' => $transferId, 'uid' => $userId, 'tid2' => $transferId]
                );
                // Also reset the GM decision columns on the transfer itself
                $this->db->execute(
                    "UPDATE warehouse_transfers
                     SET discrepancy_reviewed_by = NULL,
                         discrepancy_reviewed_at = NULL,
                         discrepancy_gm_action   = NULL,
                         discrepancy_gm_notes    = NULL,
                         updated_at              = NOW()
                     WHERE id = :id",
                    ['id' => $transferId]
                );
            }

            $discrepancyCount = 0;
            $totalSent        = 0;
            $totalReceived    = 0;

            // ── First pass: determine if any discrepancy exists ──────────────
            foreach ($itemsReceived as $itemData) {
                $qtyRecv = (float)($itemData['quantity_received'] ?? 0);
                $origItem = null;
                foreach ($transfer['items'] as $ti) {
                    if ((int)$ti['id'] === (int)($itemData['item_id'] ?? 0)) {
                        $origItem = $ti; break;
                    }
                }
                if (!$origItem) continue;

                $qtySent       = (float)$origItem['quantity_sent'];
                $totalSent    += $qtySent;
                $totalReceived += $qtyRecv;

                if (abs($qtySent - $qtyRecv) > 0.001) {
                    $discrepancyCount++;
                }
            }

            $hasDiscrepancy = $discrepancyCount > 0;

            // ── Second pass: process each item ──────────────────────────────
            foreach ($itemsReceived as $itemData) {
                $itemId  = (int)($itemData['item_id'] ?? 0);
                $qtyRecv = (float)($itemData['quantity_received'] ?? 0);

                $origItem = null;
                foreach ($transfer['items'] as $ti) {
                    if ((int)$ti['id'] === $itemId) { $origItem = $ti; break; }
                }
                if (!$origItem) continue;

                $qtySent   = (float)$origItem['quantity_sent'];
                $productId = (int)$origItem['product_id'];
                $unitId    = !empty($origItem['unit_id']) ? (int)$origItem['unit_id'] : null;

                // Always update the item's received quantity on the transfer line
                $this->trnModel->updateItemReceived($itemId, $qtyRecv);

                // ── INVENTORY SAFETY GATE ────────────────────────────────────
                // CRITICAL: Only update shop inventory immediately if there is
                // NO discrepancy on this entire transfer.
                // If ANY item has a variance, ALL inventory updates are deferred
                // until GM approves (see applyShopInventoryAfterGMApproval).
                if (!$hasDiscrepancy && $qtyRecv > 0) {
                    $beforeShop = $this->getShopStock($shopId, $productId);
                    $this->upsertShopInventory(
                        $shopId, $productId, $qtyRecv, $unitId,
                        $origItem['batch_number'] ?? null,
                        $origItem['expiry_date']  ?? null
                    );
                    $afterShop = $this->getShopStock($shopId, $productId);

                    $this->smlModel->log([
                        'movement_type'    => StockMovementModel::TYPE_TRANSFER_IN,
                        'location_type'    => 'shop',
                        'location_id'      => $shopId,
                        'product_id'       => $productId,
                        'batch_number'     => $origItem['batch_number'] ?? null,
                        'quantity_before'  => $beforeShop,
                        'quantity_in'      => $qtyRecv,
                        'quantity_out'     => 0,
                        'quantity_after'   => $afterShop,
                        'reference_type'   => 'warehouse_transfers',
                        'reference_id'     => $transferId,
                        'reference_number' => $trnNumber,
                        'performed_by'     => $userId,
                        'notes'            => "Transfer fully received at shop #{$shopId}",
                    ]);

                    if (!empty($origItem['batch_number'])) {
                        $this->batchModel->upsertBatch(
                            'shop', $shopId, $productId,
                            $origItem['batch_number'],
                            $qtyRecv, $unitId,
                            null,
                            $origItem['expiry_date'] ?? null,
                            'transfer', $transferId
                        );
                    }
                }

                // ── Create discrepancy record for every variance item ─────────
                if (abs($qtySent - $qtyRecv) > 0.001) {
                    $variance = $qtySent - $qtyRecv;
                    $varPct   = $qtySent > 0
                        ? round(abs($variance / $qtySent) * 100, 2)
                        : 0;

                    $discrepancyId = $this->disModel->createDiscrepancy([
                        'reference_type'    => 'transfer',
                        'reference_id'      => $transferId,
                        'warehouse_id'      => $warehouseId,
                        'shop_id'           => $shopId,
                        'product_id'        => $productId,
                        'batch_number'      => $origItem['batch_number'] ?? null,
                        'expected_quantity' => $qtySent,
                        'actual_quantity'   => $qtyRecv,
                        'variance_quantity' => $variance,
                        'variance_percent'  => $varPct,
                        'discrepancy_type'  => $variance > 0 ? 'short_delivery' : 'over_delivery',
                        'reason'            => trim($itemData['discrepancy_reason'] ?? ''),
                        'status'            => DiscrepancyModel::STATUS_UNDER_REVIEW,
                        'reported_by'       => $userId,
                    ]);

                    // Workflow log — step: submitted
                    $this->db->execute(
                        "INSERT INTO discrepancy_workflow_log
                            (discrepancy_id, transfer_id, action, actor_user_id, notes, snapshot)
                         VALUES (:did, :tid, 'submitted', :uid, :notes, :snap)",
                        [
                            'did'  => $discrepancyId,
                            'tid'  => $transferId,
                            'uid'  => $userId,
                            'notes'=> "Salesperson reported discrepancy: sent={$qtySent}, received={$qtyRecv}",
                            'snap' => json_encode([
                                'expected_quantity' => $qtySent,
                                'actual_quantity'   => $qtyRecv,
                                'variance'          => $variance,
                                'reason'            => $itemData['discrepancy_reason'] ?? '',
                            ]),
                        ]
                    );
                }
            }

            // ── Set transfer status ──────────────────────────────────────────
            if ($hasDiscrepancy) {
                // Block finalisation — GM must review
                $this->db->execute(
                    "UPDATE warehouse_transfers
                     SET status      = 'discrepancy_pending',
                         received_by = :rb,
                         notes       = CONCAT(COALESCE(notes,''), :n),
                         updated_at  = NOW()
                     WHERE id = :id",
                    [
                        'rb' => $userId,
                        'n'  => $notes ? "\n[Receiving notes] {$notes}" : '',
                        'id' => $transferId,
                    ]
                );
            } else {
                // Perfect match — finalise immediately
                $this->trnModel->setStatus($transferId, TransferModel::STATUS_RECEIVED, $userId);
                if ($notes) {
                    $this->db->execute(
                        "UPDATE warehouse_transfers
                         SET notes = CONCAT(COALESCE(notes,''), :n), updated_at = NOW()
                         WHERE id = :id",
                        ['n' => "\n[Receiving notes] {$notes}", 'id' => $transferId]
                    );
                }
            }

            // ── Notifications ────────────────────────────────────────────────
            if ($hasDiscrepancy) {
                $missing = $totalSent - $totalReceived;
                $this->notifyGMsOfTransferDiscrepancy(
                    $transferId, $trnNumber,
                    $warehouseName, $shopName,
                    $totalSent, $totalReceived, $missing
                );
            }

            // ── Audit trail ──────────────────────────────────────────────────
            $this->auditLog->logBusiness([
                'action'       => ActivityLogModel::ACTION_TRANSFER_RECEIVED,
                'module'       => 'transfer',
                'entity_type'  => 'transfer',
                'entity_label' => $trnNumber,
                'record_id'    => $transferId,
                'description'  => "Transfer {$trnNumber} receiving submitted by user #{$userId}. " .
                                  "Sent: {$totalSent}, Received: {$totalReceived}" .
                                  ($hasDiscrepancy
                                      ? " — {$discrepancyCount} discrepancy(ies) pending GM approval."
                                      : " — fully received, shop inventory updated."),
                'warehouse_id' => $warehouseId,
                'shop_id'      => $shopId,
                'new_values'   => [
                    'total_sent'       => $totalSent,
                    'total_received'   => $totalReceived,
                    'discrepancies'    => $discrepancyCount,
                    'has_discrepancy'  => $hasDiscrepancy,
                ],
            ]);

            return [
                'transfer_id'         => $transferId,
                'has_discrepancy'     => $hasDiscrepancy,
                'discrepancy_count'   => $discrepancyCount,
                'discrepancy_pending' => $hasDiscrepancy,
            ];
        });
    }

    // =========================================================
    // TRANSFER DISCREPANCY — GM APPROVAL WORKFLOW
    // =========================================================

    /**
     * GM approves a Warehouse→Shop transfer receiving discrepancy.
     *
     * RULES:
     *  - Only callable by ROLE_GENERAL_MANAGER.
     *  - Transfer must be in 'discrepancy_pending' status.
     *  - Marks all discrepancy records as gm_action = 'approved'.
     *  - Applies shop inventory with ACTUAL received quantities only.
     *  - Missing quantity is NOT added to shop inventory.
     *  - Transfer status → 'received_with_discrepancy'.
     *  - Notifies Salesperson and Warehouse Manager.
     *
     * @throws \RuntimeException
     */
    public function gmApproveTransferDiscrepancy(int $transferId, string $gmNotes = ''): void
    {
        $transfer = $this->trnModel->getWithItems($transferId);
        if (!$transfer) {
            throw new \RuntimeException("Transfer #{$transferId} not found.");
        }
        if ($transfer['status'] !== TransferModel::STATUS_DISCREPANCY_PENDING) {
            throw new \RuntimeException(
                "Only transfers with status 'Pending GM Approval' can be reviewed. " .
                "Current status: " . TransferModel::statusLabel($transfer['status']) . "."
            );
        }

        $userId      = (int)SessionManager::userId();
        $warehouseId = (int)$transfer['warehouse_id'];
        $shopId      = (int)$transfer['shop_id'];
        $trnNumber   = $transfer['transfer_number'];

        $this->db->transaction(function () use (
            $transfer, $transferId, $warehouseId, $shopId, $trnNumber, $userId, $gmNotes
        ): void {
            // ── 1. Mark all open discrepancies as gm_approved ────────────────
            $this->db->execute(
                "UPDATE warehouse_discrepancies
                 SET gm_action      = 'approved',
                     gm_reviewed_by = :uid,
                     gm_reviewed_at = NOW(),
                     gm_notes       = :notes,
                     status         = 'resolved',
                     updated_at     = NOW()
                 WHERE reference_type = 'transfer'
                   AND reference_id   = :tid
                   AND gm_action IS NULL",
                ['uid' => $userId, 'notes' => $gmNotes ?: null, 'tid' => $transferId]
            );

            // ── 2. Store GM decision on the transfer record itself ────────────
            $this->db->execute(
                "UPDATE warehouse_transfers
                 SET discrepancy_reviewed_by = :uid,
                     discrepancy_reviewed_at = NOW(),
                     discrepancy_gm_action   = 'approved',
                     discrepancy_gm_notes    = :notes,
                     updated_at              = NOW()
                 WHERE id = :id",
                ['uid' => $userId, 'notes' => $gmNotes ?: null, 'id' => $transferId]
            );

            // ── 3. Apply shop inventory with ACTUAL received quantities ───────
            // INVENTORY SAFETY: only the actual received qty per item is added.
            // The missing qty is permanently recorded as discrepancy/loss.
            foreach ($transfer['items'] as $item) {
                $qtyRecv   = (float)($item['quantity_received'] ?? 0);
                $productId = (int)$item['product_id'];
                $unitId    = !empty($item['unit_id']) ? (int)$item['unit_id'] : null;

                if ($qtyRecv <= 0) continue;

                $beforeShop = $this->getShopStock($shopId, $productId);
                $this->upsertShopInventory(
                    $shopId, $productId, $qtyRecv, $unitId,
                    $item['batch_number'] ?? null,
                    $item['expiry_date']  ?? null
                );
                $afterShop = $this->getShopStock($shopId, $productId);

                $this->smlModel->log([
                    'movement_type'    => StockMovementModel::TYPE_TRANSFER_IN,
                    'location_type'    => 'shop',
                    'location_id'      => $shopId,
                    'product_id'       => $productId,
                    'batch_number'     => $item['batch_number'] ?? null,
                    'quantity_before'  => $beforeShop,
                    'quantity_in'      => $qtyRecv,
                    'quantity_out'     => 0,
                    'quantity_after'   => $afterShop,
                    'reference_type'   => 'warehouse_transfers',
                    'reference_id'     => $transferId,
                    'reference_number' => $trnNumber,
                    'performed_by'     => $userId,
                    'notes'            => "Transfer {$trnNumber}: shop inventory applied post-GM-approval. Actual qty={$qtyRecv}.",
                ]);

                if (!empty($item['batch_number'])) {
                    $this->batchModel->upsertBatch(
                        'shop', $shopId, $productId,
                        $item['batch_number'],
                        $qtyRecv, $unitId,
                        null,
                        $item['expiry_date'] ?? null,
                        'transfer', $transferId
                    );
                }

                // Mark inventory_applied on discrepancy record
                $this->db->execute(
                    "UPDATE warehouse_discrepancies
                     SET inventory_applied = 1, updated_at = NOW()
                     WHERE reference_type = 'transfer'
                       AND reference_id   = :tid
                       AND product_id     = :pid",
                    ['tid' => $transferId, 'pid' => $productId]
                );
            }

            // ── 4. Finalise transfer status ───────────────────────────────────
            $this->trnModel->setStatus(
                $transferId,
                TransferModel::STATUS_RECEIVED_WITH_DISCREPANCY,
                $userId
            );

            // ── 5. Workflow log ───────────────────────────────────────────────
            $discrepancyIds = $this->db->fetchAll(
                "SELECT id FROM warehouse_discrepancies
                 WHERE reference_type = 'transfer' AND reference_id = :tid",
                ['tid' => $transferId]
            );
            foreach ($discrepancyIds as $dis) {
                $this->db->execute(
                    "INSERT INTO discrepancy_workflow_log
                        (discrepancy_id, transfer_id, action, actor_user_id, notes, snapshot)
                     VALUES (:did, :tid, 'gm_approved', :uid, :notes, :snap)",
                    [
                        'did'  => (int)$dis['id'],
                        'tid'  => $transferId,
                        'uid'  => $userId,
                        'notes'=> "GM approved transfer discrepancy. Shop inventory updated with actual qty.",
                        'snap' => json_encode(['gm_notes' => $gmNotes, 'gm_user_id' => $userId]),
                    ]
                );
            }

            // ── 6. Notifications ──────────────────────────────────────────────
            $warehouseName = $this->db->fetchOne(
                "SELECT name FROM warehouses WHERE id = :id", ['id' => $warehouseId]
            )['name'] ?? "Warehouse #{$warehouseId}";
            $shopName = $this->db->fetchOne(
                "SELECT name FROM shops WHERE id = :id", ['id' => $shopId]
            )['name'] ?? "Shop #{$shopId}";

            // Notify salesperson (receiver)
            $receiverUserId = (int)($transfer['received_by'] ?? 0);
            if ($receiverUserId) {
                $totalSent     = array_sum(array_column($transfer['items'], 'quantity_sent'));
                $totalReceived = array_sum(array_column($transfer['items'], 'quantity_received'));
                $missing       = $totalSent - $totalReceived;
                $this->notify(
                    $receiverUserId,
                    'transfer_discrepancy_approved',
                    "Discrepancy Approved — Transfer {$trnNumber}",
                    "The General Manager has approved the receiving discrepancy for transfer {$trnNumber} " .
                    "({$warehouseName} → {$shopName}).\n" .
                    "Shop inventory has been updated with the actual received quantity.\n" .
                    "Sent: " . number_format($totalSent, 3) . " | " .
                    "Received: " . number_format($totalReceived, 3) . " | " .
                    "Missing (recorded): " . number_format($missing, 3) .
                    ($gmNotes ? "\nGM Notes: {$gmNotes}" : ""),
                    'warehouse_transfers',
                    $transferId,
                    url("transfers/{$transferId}")
                );
            }

            // Notify warehouse manager (initiator)
            $initiatorUserId = (int)($transfer['initiated_by'] ?? 0);
            if ($initiatorUserId && $initiatorUserId !== $receiverUserId) {
                $totalSent     = array_sum(array_column($transfer['items'], 'quantity_sent'));
                $totalReceived = array_sum(array_column($transfer['items'], 'quantity_received'));
                $missing       = $totalSent - $totalReceived;
                $this->notify(
                    $initiatorUserId,
                    'transfer_discrepancy_approved',
                    "Transfer {$trnNumber}: Discrepancy Resolved",
                    "Transfer {$trnNumber} ({$warehouseName} → {$shopName}) receiving discrepancy " .
                    "has been approved by the General Manager.\n" .
                    "Sent: " . number_format($totalSent, 3) . " | " .
                    "Received by shop: " . number_format($totalReceived, 3) . " | " .
                    "Missing: " . number_format($missing, 3) . " (recorded as discrepancy loss).",
                    'warehouse_transfers',
                    $transferId,
                    url("transfers/{$transferId}")
                );
            }

            // ── 7. Audit trail ────────────────────────────────────────────────
            $totalSent     = array_sum(array_column($transfer['items'], 'quantity_sent'));
            $totalReceived = array_sum(array_column($transfer['items'], 'quantity_received'));
            $this->auditLog->logBusiness([
                'action'       => 'transfer_discrepancy_gm_approved',
                'module'       => 'transfer',
                'entity_type'  => 'transfer',
                'entity_label' => $trnNumber,
                'record_id'    => $transferId,
                'description'  => "GM (user #{$userId}) approved discrepancy for transfer {$trnNumber}. " .
                                  "Shop inventory updated: +{$totalReceived} (actual). " .
                                  "Missing " . ($totalSent - $totalReceived) . " NOT added to shop.",
                'warehouse_id' => $warehouseId,
                'shop_id'      => $shopId,
            ]);
        });
    }

    /**
     * GM rejects a Warehouse→Shop transfer receiving discrepancy.
     *
     * RULES:
     *  - Only callable by ROLE_GENERAL_MANAGER.
     *  - Transfer must be in 'discrepancy_pending' status.
     *  - Increments discrepancy_rejection_count on the transfer.
     *  - If count reaches MAX_DISCREPANCY_REJECTIONS (2):
     *      → Status → 'final_rejected'. No resubmission.
     *      → Warehouse stock restored.
     *      → Salesperson + WM notified with final-rejection message.
     *  - If count < MAX_DISCREPANCY_REJECTIONS:
     *      → Status → 'gm_rejected'. Salesperson may resubmit once.
     *
     * @throws \RuntimeException
     */
    public function gmRejectTransferDiscrepancy(int $transferId, string $reason): void
    {
        if (trim($reason) === '') {
            throw new \RuntimeException("A rejection reason is required.");
        }

        $transfer = $this->trnModel->getWithItems($transferId);
        if (!$transfer) {
            throw new \RuntimeException("Transfer #{$transferId} not found.");
        }
        if ($transfer['status'] !== TransferModel::STATUS_DISCREPANCY_PENDING) {
            throw new \RuntimeException(
                "Only transfers with status 'Pending GM Approval' can be reviewed. " .
                "Current status: " . TransferModel::statusLabel($transfer['status']) . "."
            );
        }

        $userId      = (int)SessionManager::userId();
        $warehouseId = (int)$transfer['warehouse_id'];
        $shopId      = (int)$transfer['shop_id'];
        $trnNumber   = $transfer['transfer_number'];
        $newCount    = (int)($transfer['discrepancy_rejection_count'] ?? 0) + 1;
        $isFinal     = ($newCount >= TransferModel::MAX_DISCREPANCY_REJECTIONS);

        $this->db->transaction(function () use (
            $transfer, $transferId, $warehouseId, $shopId,
            $trnNumber, $userId, $reason, $newCount, $isFinal
        ): void {
            // ── 1. Mark discrepancies as gm_rejected ─────────────────────────
            // Always set to 'rejected' — both first and final GM rejection.
            // The ENUM only supports: open, under_review, resolved, rejected.
            // Resubmit eligibility is controlled by warehouse_transfers.status
            // and warehouse_transfers.discrepancy_final_rejected — NOT this field.
            $this->db->execute(
                "UPDATE warehouse_discrepancies
                 SET gm_action      = 'rejected',
                     gm_reviewed_by = :uid,
                     gm_reviewed_at = NOW(),
                     gm_notes       = :reason,
                     status         = 'rejected',
                     updated_at     = NOW()
                 WHERE reference_type = 'transfer'
                   AND reference_id   = :tid
                   AND gm_action IS NULL",
                ['uid' => $userId, 'reason' => $reason, 'tid' => $transferId]
            );

            // ── 2. Store GM rejection on transfer + increment count ───────────
            $newStatus = $isFinal
                ? TransferModel::STATUS_FINAL_REJECTED
                : TransferModel::STATUS_GM_REJECTED;

            $this->db->execute(
                "UPDATE warehouse_transfers
                 SET discrepancy_reviewed_by          = :uid,
                     discrepancy_reviewed_at          = NOW(),
                     discrepancy_gm_action            = 'rejected',
                     discrepancy_gm_notes             = :reason,
                     discrepancy_rejection_count      = :rc,
                     discrepancy_final_rejected       = :fr,
                     status                           = :st,
                     updated_at                       = NOW()
                 WHERE id = :id",
                [
                    'uid'    => $userId,
                    'reason' => $reason,
                    'rc'     => $newCount,
                    'fr'     => $isFinal ? 1 : 0,
                    'st'     => $newStatus,
                    'id'     => $transferId,
                ]
            );

            // ── 3. Workflow log ───────────────────────────────────────────────
            $discrepancyIds = $this->db->fetchAll(
                "SELECT id FROM warehouse_discrepancies
                 WHERE reference_type = 'transfer' AND reference_id = :tid",
                ['tid' => $transferId]
            );
            $action = $isFinal ? 'gm_final_rejected' : 'gm_rejected';
            foreach ($discrepancyIds as $dis) {
                $this->db->execute(
                    "INSERT INTO discrepancy_workflow_log
                        (discrepancy_id, transfer_id, action, actor_user_id, notes, snapshot)
                     VALUES (:did, :tid, :action, :uid, :notes, :snap)",
                    [
                        'did'    => (int)$dis['id'],
                        'tid'    => $transferId,
                        'action' => $action,
                        'uid'    => $userId,
                        'notes'  => "GM rejected transfer discrepancy (count {$newCount}): {$reason}",
                        'snap'   => json_encode([
                            'rejection_reason' => $reason,
                            'rejection_count'  => $newCount,
                            'is_final'         => $isFinal,
                            'gm_user_id'       => $userId,
                        ]),
                    ]
                );
            }

            $warehouseName = $this->db->fetchOne(
                "SELECT name FROM warehouses WHERE id = :id", ['id' => $warehouseId]
            )['name'] ?? "Warehouse #{$warehouseId}";
            $shopName = $this->db->fetchOne(
                "SELECT name FROM shops WHERE id = :id", ['id' => $shopId]
            )['name'] ?? "Shop #{$shopId}";

            if ($isFinal) {
                // ── 4a. FINAL — restore warehouse stock ───────────────────────
                $this->applyFinalRejectionTransfer(
                    $transferId, $transfer, $userId, $reason,
                    $warehouseName, $shopName, $discrepancyIds
                );
            } else {
                // ── 4b. FIRST REJECTION — notify and allow resubmit ───────────
                $receiverUserId  = (int)($transfer['received_by'] ?? 0);
                $initiatorUserId = (int)($transfer['initiated_by'] ?? 0);

                if ($receiverUserId) {
                    $this->notify(
                        $receiverUserId,
                        'transfer_discrepancy_rejected',
                        "Discrepancy Rejected — Transfer {$trnNumber}",
                        "The General Manager has rejected the receiving discrepancy for "
                        . "transfer {$trnNumber} ({$warehouseName} → {$shopName}).\n"
                        . "Please review and re-submit with corrected information.\n"
                        . "Rejection Reason: {$reason}\n"
                        . "⚠ Note: if rejected again, the transfer will be permanently closed.",
                        'warehouse_transfers',
                        $transferId,
                        url("transfers/{$transferId}/receive")
                    );
                }
                if ($initiatorUserId && $initiatorUserId !== $receiverUserId) {
                    $this->notify(
                        $initiatorUserId,
                        'transfer_discrepancy_rejected',
                        "Transfer {$trnNumber}: Receiving Discrepancy Rejected by GM",
                        "The General Manager has rejected the receiving discrepancy for "
                        . "transfer {$trnNumber} ({$warehouseName} → {$shopName}).\n"
                        . "The Shop user has been asked to review and re-submit.\n"
                        . "Rejection Reason: {$reason}",
                        'warehouse_transfers',
                        $transferId,
                        url("transfers/{$transferId}")
                    );
                }

                $this->auditLog->logBusiness([
                    'action'       => 'transfer_discrepancy_gm_rejected',
                    'module'       => 'transfer',
                    'entity_type'  => 'transfer',
                    'entity_label' => $trnNumber,
                    'record_id'    => $transferId,
                    'description'  => "GM (user #{$userId}) rejected discrepancy for transfer "
                                    . "{$trnNumber} (rejection #{$newCount}). Reason: {$reason}",
                    'warehouse_id' => $warehouseId,
                    'shop_id'      => $shopId,
                ]);
            }
        });
    }

    /**
     * Apply final rejection for a Warehouse→Shop transfer receiving discrepancy.
     *
     * Called automatically inside gmRejectTransferDiscrepancy when
     * discrepancy_rejection_count reaches MAX_DISCREPANCY_REJECTIONS.
     *
     * WHAT THIS DOES:
     *  1. Restores the original dispatched quantity back to warehouse inventory.
     *  2. Logs a REVERSAL stock movement (idempotency-guarded).
     *  3. Sets discrepancy_stock_restored = 1.
     *  4. Notifies Salesperson + Warehouse Manager.
     *  5. Logs audit trail.
     *
     * IMPORTANT: Called INSIDE an existing DB transaction.
     */
    private function applyFinalRejectionTransfer(
        int    $transferId,
        array  $transfer,
        int    $userId,
        string $reason,
        string $warehouseName,
        string $shopName,
        array  $discrepancyIds
    ): void {
        $warehouseId = (int)$transfer['warehouse_id'];
        $trnNumber   = $transfer['transfer_number'];

        // Idempotency guard — never restore twice
        if ((int)($transfer['discrepancy_stock_restored'] ?? 0)) {
            return;
        }

        $totalRestored = 0.0;

        // ── A. Restore dispatched quantities to warehouse inventory ───────────
        // At this point stock was already DEDUCTED from warehouse at dispatch time.
        // We need to add it back using increaseStock().
        foreach ($transfer['items'] as $item) {
            $qty       = (float)$item['quantity_sent'];
            $productId = (int)$item['product_id'];
            $unitId    = !empty($item['unit_id']) ? (int)$item['unit_id'] : null;

            if ($qty <= 0) continue;

            $movement = $this->invModel->increaseStock(
                $warehouseId, $productId, $qty, $unitId,
                $item['batch_number'] ?? null,
                null, null,
                'reversal'
            );
            $totalRestored += $qty;

            $this->smlModel->log([
                'movement_type'    => StockMovementModel::TYPE_REVERSAL,
                'location_type'    => 'warehouse',
                'location_id'      => $warehouseId,
                'product_id'       => $productId,
                'batch_number'     => $item['batch_number'] ?? null,
                'quantity_before'  => $movement['before'],
                'quantity_in'      => $qty,
                'quantity_out'     => 0,
                'quantity_after'   => $movement['after'],
                'reference_type'   => 'warehouse_transfers',
                'reference_id'     => $transferId,
                'reference_number' => $trnNumber,
                'performed_by'     => $userId,
                'notes'            => "FINAL REJECTION — transfer {$trnNumber}: "
                                    . "{$qty} units returned to warehouse. "
                                    . "Reason: {$reason}",
            ]);

            // Restore batch inventory tracking
            if (!empty($item['batch_number'])) {
                $this->batchModel->upsertBatch(
                    'warehouse', $warehouseId, $productId,
                    $item['batch_number'],
                    $qty, $unitId,
                    null, $item['expiry_date'] ?? null,
                    'reversal', $transferId
                );
            }
        }

        // ── B. Mark stock restored ────────────────────────────────────────────
        $this->db->execute(
            "UPDATE warehouse_transfers
             SET discrepancy_stock_restored = 1, updated_at = NOW()
             WHERE id = :id",
            ['id' => $transferId]
        );

        // ── B2. Set warehouse_discrepancies.status = 'rejected' ───────────────
        // Ensures the discrepancy record reflects the final closed state
        // and no manual resolution attempt is possible.
        $this->db->execute(
            "UPDATE warehouse_discrepancies
             SET status = 'rejected', updated_at = NOW()
             WHERE reference_type = 'transfer'
               AND reference_id   = :tid",
            ['tid' => $transferId]
        );

        // ── C. Workflow log ───────────────────────────────────────────────────
        foreach ($discrepancyIds as $dis) {
            $this->db->execute(
                "INSERT INTO discrepancy_workflow_log
                    (discrepancy_id, transfer_id, action, actor_user_id, notes, snapshot)
                 VALUES (:did, :tid, 'final_rejected', :uid, :notes, :snap)",
                [
                    'did'  => (int)$dis['id'],
                    'tid'  => $transferId,
                    'uid'  => $userId,
                    'notes'=> "FINAL REJECTION: transfer closed. "
                            . number_format($totalRestored, 3) . " units returned to warehouse.",
                    'snap' => json_encode([
                        'rejection_count'  => TransferModel::MAX_DISCREPANCY_REJECTIONS,
                        'total_restored'   => $totalRestored,
                        'reason'           => $reason,
                    ]),
                ]
            );
            $this->db->execute(
                "INSERT INTO discrepancy_workflow_log
                    (discrepancy_id, transfer_id, action, actor_user_id, notes)
                 VALUES (:did, :tid, 'stock_restored', :uid, :notes)",
                [
                    'did'  => (int)$dis['id'],
                    'tid'  => $transferId,
                    'uid'  => $userId,
                    'notes'=> "Warehouse stock restored: "
                            . number_format($totalRestored, 3) . " units → {$warehouseName}.",
                ]
            );
        }

        // ── D. Notify salesperson ─────────────────────────────────────────────
        $receiverUserId  = (int)($transfer['received_by'] ?? 0);
        $initiatorUserId = (int)($transfer['initiated_by'] ?? 0);
        $totalSent       = array_sum(array_column($transfer['items'], 'quantity_sent'));
        $totalReceived   = array_sum(array_column($transfer['items'], 'quantity_received'));
        $missing         = $totalSent - $totalReceived;

        if ($receiverUserId) {
            $this->notify(
                $receiverUserId,
                'transfer_discrepancy_final_rejected',
                "Transfer {$trnNumber} — Final Rejection",
                "Transfer {$trnNumber} ({$warehouseName} → {$shopName}) has been permanently "
                . "closed after " . TransferModel::MAX_DISCREPANCY_REJECTIONS . " General Manager reviews.\n"
                . "Sent: " . number_format($totalSent, 3) . " | "
                . "Reported received: " . number_format($totalReceived, 3) . " | "
                . "Missing: " . number_format($missing, 3) . "\n"
                . "Resubmission is no longer allowed.\n"
                . "Rejection Reason: {$reason}",
                'warehouse_transfers',
                $transferId,
                url("transfers/{$transferId}")
            );
        }

        // ── E. Notify Warehouse Manager ───────────────────────────────────────
        if ($initiatorUserId) {
            $this->notify(
                $initiatorUserId,
                'transfer_discrepancy_final_rejected',
                "Transfer {$trnNumber} — Final Rejection: Stock Restored",
                "Transfer {$trnNumber} ({$warehouseName} → {$shopName}) receiving discrepancy "
                . "has been finally rejected after "
                . TransferModel::MAX_DISCREPANCY_REJECTIONS . " GM rejections.\n"
                . "Warehouse stock has been automatically restored: "
                . number_format($totalRestored, 3) . " units returned.\n"
                . "Rejection Reason: {$reason}",
                'warehouse_transfers',
                $transferId,
                url("transfers/{$transferId}")
            );
        }

        // ── F. Audit trail ────────────────────────────────────────────────────
        $this->auditLog->logBusiness([
            'action'       => 'transfer_discrepancy_final_rejected',
            'module'       => 'transfer',
            'entity_type'  => 'transfer',
            'entity_label' => $trnNumber,
            'record_id'    => $transferId,
            'description'  => "FINAL REJECTION: transfer {$trnNumber} permanently closed "
                            . "after " . TransferModel::MAX_DISCREPANCY_REJECTIONS . " GM rejections. "
                            . number_format($totalRestored, 3) . " units restored to {$warehouseName}. "
                            . "Reason: {$reason}",
            'warehouse_id' => $warehouseId,
            'shop_id'      => (int)$transfer['shop_id'],
        ]);
    }
    // =========================================================
    // TRANSFER DISCREPANCY — NOTIFICATION HELPERS
    // =========================================================

    /**
     * Notify all General Manager users of a pending transfer discrepancy.
     */
    private function notifyGMsOfTransferDiscrepancy(
        int    $transferId,
        string $trnNumber,
        string $warehouseName,
        string $shopName,
        float  $totalSent,
        float  $totalReceived,
        float  $missing
    ): void {
        $gms = $this->db->fetchAll(
            "SELECT u.id FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE r.name = 'general_manager' AND u.is_active = 1"
        );
        $title   = "Transfer Discrepancy Requires Approval — {$trnNumber}";
        $message = "Transfer {$trnNumber} ({$warehouseName} → {$shopName}) has a receiving discrepancy " .
                   "requiring your review.\n" .
                   "Sent: "     . number_format($totalSent,     3) . " | " .
                   "Received: " . number_format($totalReceived, 3) . " | " .
                   "Missing: "  . number_format($missing,       3) . ".\n" .
                   "Shop inventory is BLOCKED until you approve or reject.";
        foreach ($gms as $gm) {
            $this->notify(
                (int)$gm['id'],
                'transfer_discrepancy_gm_review',
                $title,
                $message,
                'warehouse_transfers',
                $transferId,
                url("gm/transfers/{$transferId}/discrepancy-review")
            );
        }
    }

    /**
     * Notify all General Manager users that a new transfer is awaiting approval.
     * Called immediately after createTransfer() completes.
     */
    private function notifyGMsOfTransferPending(
        int    $transferId,
        string $trnNumber,
        string $warehouseName,
        string $shopName,
        float  $totalQty,
        string $productList,
        int    $initiatorUserId
    ): void {
        $gms = $this->db->fetchAll(
            "SELECT u.id FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE r.name = 'general_manager' AND u.is_active = 1"
        );
        $title   = "Transfer Approval Required — {$trnNumber}";
        $message = "Warehouse Manager submitted transfer {$trnNumber} "
                 . "({$warehouseName} → {$shopName}).\n"
                 . "Products: {$productList}\n"
                 . "Total Quantity: " . number_format($totalQty, 3) . "\n"
                 . "Please review and approve or reject this transfer request.";
        foreach ($gms as $gm) {
            $this->notify(
                (int)$gm['id'],
                'transfer_pending',
                $title,
                $message,
                'warehouse_transfers',
                $transferId,
                url("gm/transfers/pending")
            );
        }
    }

    /**
     * Notify the salesperson(s) assigned to a shop that a transfer is now in transit.
     * Called immediately after dispatchTransfer() sets status = in_transit.
     */
    private function notifyShopOfDispatch(
        int    $transferId,
        string $trnNumber,
        string $warehouseName,
        string $shopName,
        int    $shopId,
        float  $totalQty,
        string $productList
    ): void {
        $salespersons = $this->db->fetchAll(
            "SELECT u.id FROM users u
             JOIN roles r ON r.id = u.role_id
             JOIN organization_assignments oa ON oa.user_id = u.id
             WHERE r.name = 'salesperson'
               AND oa.location_type = 'shop'
               AND oa.location_id   = :sid
               AND oa.is_active     = 1
               AND u.is_active      = 1
             UNION
             SELECT u.id FROM users u
             JOIN roles r ON r.id = u.role_id
             JOIN user_location_assignments ula ON ula.user_id = u.id
             WHERE r.name = 'salesperson'
               AND ula.location_type = 'shop'
               AND ula.location_id   = :sid2
               AND u.is_active       = 1",
            ['sid' => $shopId, 'sid2' => $shopId]
        );
        if (empty($salespersons)) return;

        $title   = "Transfer {$trnNumber} Is On Its Way";
        $message = "Transfer {$trnNumber} from {$warehouseName} is now in transit to {$shopName}.\n"
                 . "Products: {$productList}\n"
                 . "Total Quantity: " . number_format($totalQty, 3) . "\n"
                 . "Please prepare to receive the goods when they arrive.";
        foreach ($salespersons as $sp) {
            $this->notify(
                (int)$sp['id'],
                'transfer_dispatched',
                $title,
                $message,
                'warehouse_transfers',
                $transferId,
                url("transfers/{$transferId}/receive")
            );
        }
    }

    /**
     * Cancel a transfer (only pending/draft/approved status).
     * Releases inventory reservations.
     */
    public function cancelTransfer(int $transferId): void
    {
        $transfer = $this->trnModel->getWithItems($transferId);
        if (!$transfer) {
            throw new \RuntimeException("Transfer #{$transferId} not found.");
        }
        $cancellable = [TransferModel::STATUS_PENDING, TransferModel::STATUS_DRAFT, TransferModel::STATUS_APPROVED];
        if (!in_array($transfer['status'], $cancellable, true)) {
            throw new \RuntimeException(
                "Only pending, draft, or approved transfers can be cancelled. " .
                "Current status: " . TransferModel::statusLabel($transfer['status']) . "."
            );
        }

        $userId      = (int)SessionManager::userId();
        $warehouseId = (int)$transfer['warehouse_id'];
        $trnNumber   = $transfer['transfer_number'];

        $this->db->transaction(function () use ($transfer, $transferId, $warehouseId, $trnNumber, $userId): void {
            // Release stock reservations
            foreach ($transfer['items'] as $item) {
                $this->invModel->releaseReservation(
                    $warehouseId,
                    (int)$item['product_id'],
                    (float)$item['quantity_requested']
                );
            }

            $this->trnModel->setStatus($transferId, TransferModel::STATUS_CANCELLED);

            $this->auditLog->logBusiness([
                'action'       => ActivityLogModel::ACTION_TRANSFER_CANCELLED,
                'module'       => 'warehouse',
                'entity_type'  => 'transfer',
                'entity_label' => $trnNumber,
                'record_id'    => $transferId,
                'description'  => "Transfer {$trnNumber} cancelled.",
                'warehouse_id' => $warehouseId,
                'shop_id'      => (int)$transfer['shop_id'],
            ]);
        });
    }

    // =========================================================
    // DISCREPANCY WORKFLOW
    // =========================================================

    public function updateDiscrepancyStatus(
        int    $discrepancyId,
        string $status,
        string $notes = ''
    ): void {
        $dis = $this->disModel->find($discrepancyId);
        if (!$dis) throw new \RuntimeException("Discrepancy #{$discrepancyId} not found.");

        $userId = (int)SessionManager::userId();
        $this->disModel->updateStatus($discrepancyId, $status, $userId, $notes ?: null);

        $action = $status === DiscrepancyModel::STATUS_RESOLVED
            ? ActivityLogModel::ACTION_DISCREPANCY_APPROVED
            : ActivityLogModel::ACTION_DISCREPANCY_REJECTED;

        $this->auditLog->logBusiness([
            'action'       => $action,
            'module'       => 'warehouse',
            'entity_type'  => 'discrepancy',
            'entity_label' => $dis['discrepancy_number'] ?? "DIS-{$discrepancyId}",
            'record_id'    => $discrepancyId,
            'description'  => "Discrepancy #{$discrepancyId} set to {$status}." .
                              ($notes ? " Notes: {$notes}" : ""),
            'warehouse_id' => (int)$dis['warehouse_id'],
        ]);
    }

    // =========================================================
    // PRIVATE HELPERS
    // =========================================================

    private function upsertShopInventory(
        int     $shopId,
        int     $productId,
        float   $qty,
        ?int    $unitId      = null,
        ?string $batchNumber = null,
        ?string $expiryDate  = null
    ): void {
        $existing = $this->db->fetchOne(
            "SELECT id, current_quantity FROM shop_inventory
             WHERE shop_id = :sid AND product_id = :pid",
            ['sid' => $shopId, 'pid' => $productId]
        );

        if ($existing) {
            $this->db->execute(
                "UPDATE shop_inventory
                 SET current_quantity   = current_quantity + :q,
                     available_quantity = available_quantity + :q2,
                     last_movement_at   = NOW(),
                     last_movement_type = 'transfer_in',
                     batch_number       = COALESCE(:bn, batch_number),
                     expiry_date        = COALESCE(:ed, expiry_date),
                     updated_at         = NOW()
                 WHERE shop_id = :sid AND product_id = :pid",
                [
                    'q'   => $qty,
                    'q2'  => $qty,
                    'bn'  => $batchNumber,
                    'ed'  => $expiryDate,
                    'sid' => $shopId,
                    'pid' => $productId,
                ]
            );
        } else {
            $this->db->execute(
                "INSERT INTO shop_inventory
                    (shop_id, product_id, current_quantity, reserved_quantity,
                     available_quantity, unit_id, batch_number, expiry_date,
                     last_movement_at, last_movement_type)
                 VALUES (:sid, :pid, :q, 0, :q2, :uid, :bn, :ed, NOW(), 'transfer_in')",
                [
                    'sid' => $shopId,
                    'pid' => $productId,
                    'q'   => $qty,
                    'q2'  => $qty,
                    'uid' => $unitId,
                    'bn'  => $batchNumber,
                    'ed'  => $expiryDate,
                ]
            );
        }
    }

    private function getShopStock(int $shopId, int $productId): float
    {
        return (float)($this->db->fetchOne(
            "SELECT current_quantity FROM shop_inventory
             WHERE shop_id = :sid AND product_id = :pid",
            ['sid' => $shopId, 'pid' => $productId]
        )['current_quantity'] ?? 0);
    }
}
