<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 9A
 * =========================================================
 * File    : modules/farm/models/FarmDispatchModel.php
 * Purpose : Data access for `farm_dispatches` and
 *           `farm_dispatch_items`.
 *
 * BUSINESS RULES:
 *   RULE 5:  Dispatch qty cannot exceed available harvest qty.
 *   RULE 6:  Farm dispatch destination = Central Warehouse only.
 *   RULE 7:  Dispatched records are not freely editable.
 *   RULE 10: Traceability: Farm → Harvest → Dispatch → WH.
 *
 * Status flow (with GM approval gate):
 *   pending_approval  → approved (GM approves)
 *                     → rejected (GM rejects — dispatch blocked permanently)
 *   approved          → in_transit (Farm Manager marks dispatched)
 *                     → cancelled (Farm Manager cancels before dispatch)
 *   in_transit        → received | partially_received | received_with_discrepancy
 *                     → cancelled (unusual — only before warehouse confirms)
 *
 * SECURITY:
 *   Harvest quantities are deducted ONLY when Farm Manager marks dispatched
 *   (status: approved → in_transit), NOT at creation time.
 *   If GM rejects, no stock is ever deducted.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Farm\Models;

use AAM\Core\BaseModel;

class FarmDispatchModel extends BaseModel
{
    protected string $table      = 'farm_dispatches';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = false;

    protected array $fillable = [
        'farm_id', 'warehouse_id', 'dispatch_number',
        'dispatch_date', 'expected_arrival_date',
        'status', 'notes',
        'initiated_by', 'received_by', 'received_date',
    ];

    // ── Status constants ──────────────────────────────────
    public const STATUS_PENDING_APPROVAL              = 'pending_approval';
    public const STATUS_APPROVED                      = 'approved';
    public const STATUS_REJECTED                      = 'rejected';
    public const STATUS_PENDING                       = 'pending';          // legacy — kept for existing rows
    public const STATUS_IN_TRANSIT                    = 'in_transit';
    public const STATUS_RECEIVED                      = 'received';
    public const STATUS_PARTIALLY_RECEIVED            = 'partially_received';
    public const STATUS_RECEIVED_WITH_DISCREPANCY     = 'received_with_discrepancy';
    public const STATUS_FINAL_REJECTED                = 'final_rejected';   // receiving discrepancy finally rejected after 2 GM rejections
    public const STATUS_CANCELLED                     = 'cancelled';

    // ──────────────────────────────────────────────────────
    // READ — dispatches
    // ──────────────────────────────────────────────────────

    /**
     * Paginated dispatch list for a farm.
     *
     * @param int  $farmId 0 = all (admin/GM)
     */
    public function paginateFarm(
        int    $farmId  = 0,
        int    $page    = 1,
        int    $perPage = 20,
        array  $filters = []
    ): array {
        $page    = max(1, $page);
        $perPage = min($perPage, PAGINATION_MAX);
        $offset  = ($page - 1) * $perPage;

        [$where, $params] = $this->buildFilters($farmId, $filters);

        $total = (int)($this->rawQueryOne(
            "SELECT COUNT(*) AS c FROM farm_dispatches d WHERE {$where}",
            $params
        )['c'] ?? 0);

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $data = $this->rawQuery(
            "SELECT
                d.*,
                f.name   AS farm_name,
                w.name   AS warehouse_name,
                CONCAT(ui.first_name,' ',ui.last_name) AS initiated_by_name,
                CONCAT(ur.first_name,' ',ur.last_name) AS received_by_name,
                -- item count summary
                (SELECT COUNT(*) FROM farm_dispatch_items di WHERE di.dispatch_id = d.id) AS item_count,
                (SELECT COALESCE(SUM(di.quantity_dispatched),0)
                 FROM farm_dispatch_items di WHERE di.dispatch_id = d.id)                  AS total_qty
             FROM farm_dispatches d
             LEFT JOIN farms      f  ON f.id  = d.farm_id
             LEFT JOIN warehouses w  ON w.id  = d.warehouse_id
             LEFT JOIN users      ui ON ui.id = d.initiated_by
             LEFT JOIN users      ur ON ur.id = d.received_by
             WHERE {$where}
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

    /**
     * Full dispatch detail with items and traceability data.
     */
    public function getWithItems(int $id): ?array
    {
        $dispatch = $this->rawQueryOne(
            "SELECT
                d.*,
                f.name          AS farm_name,
                f.location      AS farm_location,
                f.farm_type,
                w.name          AS warehouse_name,
                w.location      AS warehouse_location,
                CONCAT(ui.first_name,' ',ui.last_name) AS initiated_by_name,
                CONCAT(ur.first_name,' ',ur.last_name) AS received_by_name
             FROM farm_dispatches d
             LEFT JOIN farms      f  ON f.id  = d.farm_id
             LEFT JOIN warehouses w  ON w.id  = d.warehouse_id
             LEFT JOIN users      ui ON ui.id = d.initiated_by
             LEFT JOIN users      ur ON ur.id = d.received_by
             WHERE d.id = :id",
            ['id' => $id]
        );

        if (!$dispatch) {
            return null;
        }

        $dispatch['items'] = $this->rawQuery(
            "SELECT
                di.*,
                p.name          AS product_name,
                p.sku           AS product_sku,
                u.abbreviation  AS unit_abbr,
                hb.batch_number AS batch_number,
                hb.harvest_date,
                hb.quality_grade
             FROM farm_dispatch_items di
             LEFT JOIN products         p  ON p.id  = di.product_id
             LEFT JOIN units_of_measure u  ON u.id  = di.unit_id
             LEFT JOIN harvest_batches  hb ON hb.id = di.harvest_batch_id
             WHERE di.dispatch_id = :did
             ORDER BY di.id ASC",
            ['did' => $id]
        );

        return $dispatch;
    }

    // ──────────────────────────────────────────────────────
    // WRITE
    // ──────────────────────────────────────────────────────

    /**
     * Create a dispatch header + items within a transaction.
     * Validates quantity availability before committing.
     *
     * @param array<string, mixed>   $header  Farm dispatch header fields
     * @param array<int, array{
     *   harvest_batch_id: int,
     *   product_id: int,
     *   quantity_dispatched: float,
     *   unit_id: int,
     *   notes?: string,
     * }> $items
     * @return int New dispatch ID
     * @throws \RuntimeException on validation failure
     */
    public function createWithItems(array $header, array $items): int
    {
        return $this->db->transaction(function () use ($header, $items): int {
            // Dispatch starts as pending_approval — awaiting GM decision.
            // Harvest quantities are NOT deducted here; deduction happens
            // only when the Farm Manager marks the dispatch as in_transit
            // (after GM approval). This ensures no stock leaves the farm
            // until the GM has approved.
            $header['status'] = self::STATUS_PENDING_APPROVAL;

            // Insert header
            $cols = implode(', ', array_map(fn($c) => "`{$c}`", array_keys($header)));
            $phs  = implode(', ', array_map(fn($c) => ":{$c}", array_keys($header)));
            $this->db->execute(
                "INSERT INTO farm_dispatches ({$cols}) VALUES ({$phs})",
                $header
            );
            $dispatchId = $this->db->lastInsertId();

            // Insert items — NO quantity deduction yet
            foreach ($items as $item) {
                $qty     = (float)$item['quantity_dispatched'];
                $batchId = (int)$item['harvest_batch_id'];

                // Validate requested quantity is available, but do NOT deduct.
                $batch = (new HarvestBatchModel())->find($batchId);
                if (!$batch) {
                    throw new \RuntimeException("Harvest batch #{$batchId} not found.");
                }
                $remaining = (float)$batch['remaining_quantity'];
                if ($qty > $remaining + 0.0001) {
                    throw new \RuntimeException(
                        "Dispatch quantity ({$qty}) exceeds the available harvest quantity ({$remaining})."
                    );
                }

                $this->db->execute(
                    "INSERT INTO farm_dispatch_items
                        (dispatch_id, harvest_batch_id, product_id,
                         quantity_dispatched, unit_id, item_status, notes)
                     VALUES
                        (:did, :hbid, :pid, :qty, :uid, 'pending', :notes)",
                    [
                        'did'   => $dispatchId,
                        'hbid'  => $batchId,
                        'pid'   => (int)$item['product_id'],
                        'qty'   => $qty,
                        'uid'   => (int)$item['unit_id'],
                        'notes' => $item['notes'] ?? null,
                    ]
                );
            }

            return $dispatchId;
        });
    }

    /**
     * Cancel a dispatch.
     * Allowed statuses: pending_approval, approved (before dispatch).
     * Legacy 'pending' is also allowed for backward-compat with existing rows.
     * If quantities were already deducted (status was approved and FM had
     * already physically dispatched items to staging), restore them.
     * For pending_approval / approved (not yet in_transit), quantities were
     * never deducted so no restoration needed.
     *
     * @throws \RuntimeException if status does not allow cancellation
     */
    public function cancel(int $id): void
    {
        $dispatch = $this->find($id);
        if (!$dispatch) {
            throw new \RuntimeException("Dispatch #{$id} not found.");
        }

        $cancellable = [
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_APPROVED,
            self::STATUS_PENDING,   // legacy rows
            self::STATUS_IN_TRANSIT,
        ];

        if (!in_array($dispatch['status'], $cancellable, true)) {
            throw new \RuntimeException(
                "This dispatch cannot be cancelled (status: {$dispatch['status']})."
            );
        }

        $this->db->transaction(function () use ($id, $dispatch): void {
            // Only restore harvest quantities if stock was already deducted.
            // Deduction happens at markDispatched() (in_transit transition),
            // so restore only when status was already in_transit.
            if ($dispatch['status'] === self::STATUS_IN_TRANSIT) {
                $items = $this->rawQuery(
                    "SELECT harvest_batch_id, quantity_dispatched
                     FROM farm_dispatch_items WHERE dispatch_id = :did",
                    ['did' => $id]
                );
                $harvestModel = new HarvestBatchModel();
                foreach ($items as $item) {
                    $harvestModel->restoreQuantity(
                        (int)$item['harvest_batch_id'],
                        (float)$item['quantity_dispatched']
                    );
                }
            }

            $this->rawExecute(
                "UPDATE farm_dispatches SET status = 'cancelled', updated_at = NOW()
                 WHERE id = :id",
                ['id' => $id]
            );
        });
    }

    /**
     * Mark a dispatch as dispatched (approved → in_transit).
     * SECURITY: Only allowed when status = 'approved'.
     * This is the point at which harvest quantities are deducted from stock.
     *
     * @throws \RuntimeException if not in approved state
     */
    public function markDispatched(int $id): void
    {
        $dispatch = $this->find($id);
        if (!$dispatch) {
            throw new \RuntimeException("Dispatch #{$id} not found.");
        }

        // Legacy support: allow old 'pending' rows to be dispatched directly
        // if they pre-date the approval workflow.
        $allowedStatuses = [self::STATUS_APPROVED, self::STATUS_PENDING];

        if (!in_array($dispatch['status'], $allowedStatuses, true)) {
            if ($dispatch['status'] === self::STATUS_PENDING_APPROVAL) {
                throw new \RuntimeException(
                    "This dispatch is awaiting General Manager approval. "
                    . "You cannot dispatch until it has been approved."
                );
            }
            if ($dispatch['status'] === self::STATUS_REJECTED) {
                throw new \RuntimeException(
                    "This dispatch was rejected by the General Manager and cannot be dispatched."
                );
            }
            throw new \RuntimeException(
                "Only approved dispatches can be marked as dispatched (current status: {$dispatch['status']})."
            );
        }

        $this->db->transaction(function () use ($id): void {
            // Deduct harvest quantities NOW — at the point of actual physical dispatch.
            $items = $this->rawQuery(
                "SELECT harvest_batch_id, quantity_dispatched
                 FROM farm_dispatch_items WHERE dispatch_id = :did",
                ['did' => $id]
            );
            $harvestModel = new HarvestBatchModel();
            foreach ($items as $item) {
                $harvestModel->deductQuantity(
                    (int)$item['harvest_batch_id'],
                    (float)$item['quantity_dispatched']
                );
            }

            $this->rawExecute(
                "UPDATE farm_dispatches SET status = 'in_transit', updated_at = NOW()
                 WHERE id = :id",
                ['id' => $id]
            );
        });
    }

    /**
     * GM approves a dispatch (pending_approval → approved).
     * Does NOT deduct stock — that happens when Farm Manager dispatches.
     *
     * @throws \RuntimeException if dispatch is not pending approval
     */
    public function approve(int $id, int $gmUserId, string $notes = ''): void
    {
        $dispatch = $this->find($id);
        if (!$dispatch) {
            throw new \RuntimeException("Dispatch #{$id} not found.");
        }
        if ($dispatch['status'] !== self::STATUS_PENDING_APPROVAL) {
            throw new \RuntimeException(
                "Only dispatches pending approval can be approved (current status: {$dispatch['status']})."
            );
        }

        $this->rawExecute(
            "UPDATE farm_dispatches
             SET status = 'approved',
                 gm_reviewed_by = :uid,
                 gm_reviewed_at = NOW(),
                 gm_notes       = :notes,
                 updated_at     = NOW()
             WHERE id = :id",
            ['id' => $id, 'uid' => $gmUserId, 'notes' => $notes ?: null]
        );
    }

    /**
     * GM rejects a dispatch (pending_approval → rejected).
     * No stock is ever deducted. Rejection reason is mandatory.
     *
     * @throws \RuntimeException if dispatch is not pending approval or reason is empty
     */
    public function reject(int $id, int $gmUserId, string $reason): void
    {
        if (trim($reason) === '') {
            throw new \RuntimeException("A rejection reason is required.");
        }
        $dispatch = $this->find($id);
        if (!$dispatch) {
            throw new \RuntimeException("Dispatch #{$id} not found.");
        }
        if ($dispatch['status'] !== self::STATUS_PENDING_APPROVAL) {
            throw new \RuntimeException(
                "Only dispatches pending approval can be rejected (current status: {$dispatch['status']})."
            );
        }

        $this->rawExecute(
            "UPDATE farm_dispatches
             SET status           = 'rejected',
                 gm_reviewed_by   = :uid,
                 gm_reviewed_at   = NOW(),
                 rejection_reason = :reason,
                 updated_at       = NOW()
             WHERE id = :id",
            ['id' => $id, 'uid' => $gmUserId, 'reason' => $reason]
        );
    }

    /**
     * Fetch all dispatches with status = 'pending_approval'.
     * Used by GM pending-approvals page.
     */
    public function getPendingApproval(): array
    {
        return $this->rawQuery(
            "SELECT
                d.*,
                f.name          AS farm_name,
                f.location      AS farm_location,
                f.farm_type,
                w.name          AS warehouse_name,
                CONCAT(ui.first_name,' ',ui.last_name) AS initiated_by_name,
                ui.username     AS initiated_by_username,
                COUNT(di.id)    AS item_count,
                COALESCE(SUM(di.quantity_dispatched), 0) AS total_qty,
                DATEDIFF(CURDATE(), DATE(d.created_at)) AS days_waiting
             FROM farm_dispatches d
             LEFT JOIN farms      f  ON f.id  = d.farm_id
             LEFT JOIN warehouses w  ON w.id  = d.warehouse_id
             LEFT JOIN users      ui ON ui.id = d.initiated_by
             LEFT JOIN farm_dispatch_items di ON di.dispatch_id = d.id
             WHERE d.status = 'pending_approval'
             GROUP BY d.id, f.name, f.location, f.farm_type, w.name,
                      ui.first_name, ui.last_name, ui.username
             ORDER BY d.created_at ASC"
        );
    }

    /**
     * Count dispatches awaiting GM approval.
     * Used in GM dashboard badge.
     */
    public function countPendingApproval(): int
    {
        return (int)($this->rawQueryOne(
            "SELECT COUNT(*) AS c FROM farm_dispatches WHERE status = 'pending_approval'"
        )['c'] ?? 0);
    }

    // ──────────────────────────────────────────────────────
    // DISPATCH NUMBER GENERATION
    // ──────────────────────────────────────────────────────

    /**
     * Generate a unique dispatch number.
     * Format: DSP-YYYY-NNNN  (e.g. DSP-2026-0001)
     */
    public function generateDispatchNumber(): string
    {
        $year = date('Y');
        $row  = $this->rawQueryOne(
            "SELECT dispatch_number FROM farm_dispatches
             WHERE dispatch_number LIKE :pattern
             ORDER BY id DESC LIMIT 1",
            ['pattern' => "DSP-{$year}-%"]
        );

        if ($row) {
            $parts = explode('-', $row['dispatch_number']);
            $seq   = (int)end($parts) + 1;
        } else {
            $seq = 1;
        }

        return "DSP-{$year}-" . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }

    // ──────────────────────────────────────────────────────
    // STATUS HELPERS
    // ──────────────────────────────────────────────────────

    public static function getStatusLabel(string $status): string
    {
        return match($status) {
            self::STATUS_PENDING_APPROVAL             => 'Pending GM Approval',
            self::STATUS_APPROVED                     => 'Approved',
            self::STATUS_REJECTED                     => 'Rejected',
            self::STATUS_PENDING                      => 'Pending',
            self::STATUS_IN_TRANSIT                   => 'In Transit',
            self::STATUS_RECEIVED                     => 'Received',
            self::STATUS_PARTIALLY_RECEIVED           => 'Received (Partial)',
            self::STATUS_RECEIVED_WITH_DISCREPANCY    => 'Received — Discrepancy',
            self::STATUS_FINAL_REJECTED               => 'Final Rejected',
            self::STATUS_CANCELLED                    => 'Cancelled',
            default                                   => ucfirst($status),
        };
    }

    public static function getStatusBadgeClass(string $status): string
    {
        return match($status) {
            self::STATUS_PENDING_APPROVAL             => 'warning',
            self::STATUS_APPROVED                     => 'success',
            self::STATUS_REJECTED                     => 'danger',
            self::STATUS_PENDING                      => 'warning',
            self::STATUS_IN_TRANSIT                   => 'info',
            self::STATUS_RECEIVED                     => 'success',
            self::STATUS_PARTIALLY_RECEIVED           => 'primary',
            self::STATUS_RECEIVED_WITH_DISCREPANCY    => 'danger',
            self::STATUS_FINAL_REJECTED               => 'dark',
            self::STATUS_CANCELLED                    => 'secondary',
            default                                   => 'secondary',
        };
    }

    /**
     * True if dispatch can be physically sent (requires GM approval).
     */
    public static function isDispatchable(string $status): bool
    {
        // Legacy 'pending' rows pre-dating the approval workflow are also allowed
        return in_array($status, [self::STATUS_APPROVED, self::STATUS_PENDING], true);
    }

    /**
     * True if dispatch can be edited (only before GM decision).
     */
    public static function isEditable(string $status): bool
    {
        return $status === self::STATUS_PENDING_APPROVAL;
    }

    /**
     * True if dispatch can be cancelled.
     */
    public static function isCancellable(string $status): bool
    {
        return in_array($status, [
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_APPROVED,
            self::STATUS_PENDING,   // legacy
            self::STATUS_IN_TRANSIT,
        ], true);
    }

    /**
     * True if dispatch has been fully or partially received.
     */
    public static function isReceived(string $status): bool
    {
        return in_array($status, [
            self::STATUS_RECEIVED,
            self::STATUS_PARTIALLY_RECEIVED,
            self::STATUS_RECEIVED_WITH_DISCREPANCY,
        ], true);
    }

    // ──────────────────────────────────────────────────────
    // PRIVATE
    // ──────────────────────────────────────────────────────

    private function buildFilters(int $farmId, array $filters): array
    {
        $where  = [];
        $params = [];

        if ($farmId > 0) {
            $where[]       = 'd.farm_id = :fid';
            $params['fid'] = $farmId;
        }

        if (!empty($filters['status'])) {
            $where[]           = 'd.status = :status';
            $params['status']  = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $where[]           = 'd.dispatch_date >= :dfrom';
            $params['dfrom']   = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[]           = 'd.dispatch_date <= :dto';
            $params['dto']     = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $where[]           = 'd.dispatch_number LIKE :q';
            $params['q']       = '%' . $filters['search'] . '%';
        }

        return [
            $where ? implode(' AND ', $where) : '1=1',
            $params,
        ];
    }
}
