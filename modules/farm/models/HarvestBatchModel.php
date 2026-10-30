<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 9A
 * =========================================================
 * File    : modules/farm/models/HarvestBatchModel.php
 * Purpose : Data access for `harvest_batches`.
 *           Handles creation, editing, status transitions,
 *           quantity control, and batch ID generation.
 *
 * BUSINESS RULES ENFORCED:
 *   - Quantity must be > 0
 *   - REJECTED quality blocks normal dispatch
 *   - Status transitions are controlled
 *   - No hard deletes; use CANCELLED status instead
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Farm\Models;

use AAM\Core\BaseModel;

class HarvestBatchModel extends BaseModel
{
    protected string $table      = 'harvest_batches';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = false; // uses status-based cancellation

    protected array $fillable = [
        'farm_id', 'product_id', 'batch_number',
        'production_date', 'harvest_date', 'expiry_date',
        'initial_quantity', 'remaining_quantity',
        'unit_id', 'quality_grade', 'status', 'notes',
        'recorded_by',
    ];

    // ── Status constants ──────────────────────────────────
    public const STATUS_ACTIVE               = 'active';
    public const STATUS_PARTIALLY_DISPATCHED = 'partially_dispatched';
    public const STATUS_FULLY_DISPATCHED     = 'fully_dispatched';
    public const STATUS_DEPLETED             = 'depleted';
    public const STATUS_EXPIRED              = 'expired';
    public const STATUS_QUARANTINE           = 'quarantine';

    // ── Quality constants ─────────────────────────────────
    public const QUALITY_A        = 'A';
    public const QUALITY_B        = 'B';
    public const QUALITY_C        = 'C';
    public const QUALITY_UNGRADED = 'Ungraded';

    // ──────────────────────────────────────────────────────
    // READ
    // ──────────────────────────────────────────────────────

    /**
     * Paginated harvest list for a farm with product / unit joins.
     *
     * @param int  $farmId  0 = all farms (admin/GM)
     * @param array{
     *   search?: string,
     *   product_id?: int,
     *   status?: string,
     *   quality?: string,
     *   date_from?: string,
     *   date_to?: string,
     *   batch?: string,
     * } $filters
     * @return array{data: array, total: int, page: int, perPage: int, totalPages: int}
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
            "SELECT COUNT(*) AS c FROM harvest_batches hb
             LEFT JOIN products p ON p.id = hb.product_id
             WHERE {$where}",
            $params
        )['c'] ?? 0);

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $data = $this->rawQuery(
            "SELECT
                hb.*,
                p.name   AS product_name,
                p.sku    AS product_sku,
                u.name   AS unit_name,
                u.abbreviation AS unit_abbr,
                f.name   AS farm_name,
                CONCAT(usr.first_name,' ',usr.last_name) AS recorded_by_name
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

    /**
     * Single harvest batch with all related data.
     */
    public function getWithDetails(int $id): ?array
    {
        return $this->rawQueryOne(
            "SELECT
                hb.*,
                p.name          AS product_name,
                p.sku           AS product_sku,
                p.is_perishable AS product_is_perishable,
                u.name          AS unit_name,
                u.abbreviation  AS unit_abbr,
                f.name          AS farm_name,
                f.farm_type,
                CONCAT(usr.first_name,' ',usr.last_name) AS recorded_by_name
             FROM harvest_batches hb
             LEFT JOIN products         p   ON p.id   = hb.product_id
             LEFT JOIN units_of_measure u   ON u.id   = hb.unit_id
             LEFT JOIN farms            f   ON f.id   = hb.farm_id
             LEFT JOIN users            usr ON usr.id = hb.recorded_by
             WHERE hb.id = :id",
            ['id' => $id]
        );
    }

    /**
     * Harvest batches eligible for dispatch (have remaining quantity, not rejected).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDispatchable(int $farmId): array
    {
        $bind = ['fid' => $farmId];
        return $this->rawQuery(
            "SELECT
                hb.*,
                p.name         AS product_name,
                u.abbreviation AS unit_abbr
             FROM harvest_batches hb
             LEFT JOIN products         p ON p.id = hb.product_id
             LEFT JOIN units_of_measure u ON u.id = hb.unit_id
             WHERE hb.farm_id = :fid
               AND hb.status IN ('active','partially_dispatched')
               AND hb.quality_grade != 'C'
               AND hb.remaining_quantity > 0
             ORDER BY hb.harvest_date ASC, hb.id ASC",
            $bind
        );
    }

    // ──────────────────────────────────────────────────────
    // WRITE
    // ──────────────────────────────────────────────────────

    /**
     * Create a new harvest batch record.
     * Sets remaining_quantity = initial_quantity.
     *
     * @param array<string, mixed> $data
     * @return int New record ID
     */
    public function createBatch(array $data): int
    {
        $data['remaining_quantity'] = $data['initial_quantity'];
        $data['status']             = self::STATUS_ACTIVE;
        return $this->create($data);
    }

    /**
     * Update editable fields on a harvest record.
     * Only allowed when status = 'active'.
     *
     * @param array<string, mixed> $data
     */
    public function updateBatch(int $id, array $data): int
    {
        return $this->update($id, $data);
    }

    /**
     * Deduct dispatched quantity and update status automatically.
     * Called during dispatch creation (within a transaction).
     *
     * RULE: remaining_quantity can never go below zero.
     *
     * @throws \RuntimeException if quantity exceeds remaining
     */
    public function deductQuantity(int $batchId, float $qty): void
    {
        $batch = $this->find($batchId);
        if (!$batch) {
            throw new \RuntimeException("Harvest batch #{$batchId} not found.");
        }

        $remaining = (float)$batch['remaining_quantity'];
        if ($qty > $remaining + 0.0001) {
            throw new \RuntimeException(
                "Dispatch quantity ({$qty}) exceeds the available harvest quantity ({$remaining})."
            );
        }

        $newRemaining = max(0.0, $remaining - $qty);
        $newStatus    = $newRemaining <= 0.0001
            ? self::STATUS_FULLY_DISPATCHED
            : self::STATUS_PARTIALLY_DISPATCHED;

        $this->rawExecute(
            "UPDATE harvest_batches
             SET remaining_quantity = :rem, status = :st
             WHERE id = :id",
            ['rem' => $newRemaining, 'st' => $newStatus, 'id' => $batchId]
        );
    }

    /**
     * Restore quantity back when a dispatch is cancelled.
     * Called within a transaction.
     */
    public function restoreQuantity(int $batchId, float $qty): void
    {
        $batch = $this->find($batchId);
        if (!$batch) {
            return;
        }

        $newRemaining = (float)$batch['remaining_quantity'] + $qty;
        $initial      = (float)$batch['initial_quantity'];
        $newRemaining = min($newRemaining, $initial); // never exceed initial

        $newStatus = $newRemaining >= $initial
            ? self::STATUS_ACTIVE
            : self::STATUS_PARTIALLY_DISPATCHED;

        $this->rawExecute(
            "UPDATE harvest_batches
             SET remaining_quantity = :rem, status = :st
             WHERE id = :id",
            ['rem' => $newRemaining, 'st' => $newStatus, 'id' => $batchId]
        );
    }

    // ──────────────────────────────────────────────────────
    // BATCH NUMBER GENERATION
    // ──────────────────────────────────────────────────────

    /**
     * Generate a unique harvest batch number.
     * Format: HARV-YYYY-NNNN  (e.g. HARV-2026-0001)
     */
    public function generateBatchNumber(): string
    {
        $year = date('Y');
        $row  = $this->rawQueryOne(
            "SELECT batch_number FROM harvest_batches
             WHERE batch_number LIKE :pattern
             ORDER BY id DESC LIMIT 1",
            ['pattern' => "HARV-{$year}-%"]
        );

        if ($row) {
            $parts = explode('-', $row['batch_number']);
            $seq   = (int)end($parts) + 1;
        } else {
            $seq = 1;
        }

        return "HARV-{$year}-" . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }

    // ──────────────────────────────────────────────────────
    // STATUS HELPERS
    // ──────────────────────────────────────────────────────

    public static function getStatusLabel(string $status): string
    {
        return match($status) {
            self::STATUS_ACTIVE               => 'Active',
            self::STATUS_PARTIALLY_DISPATCHED => 'Partially Dispatched',
            self::STATUS_FULLY_DISPATCHED     => 'Fully Dispatched',
            self::STATUS_DEPLETED             => 'Depleted',
            self::STATUS_EXPIRED              => 'Expired',
            self::STATUS_QUARANTINE           => 'Quarantine / Rejected',
            default                           => ucfirst($status),
        };
    }

    public static function getStatusBadgeClass(string $status): string
    {
        return match($status) {
            self::STATUS_ACTIVE               => 'success',
            self::STATUS_PARTIALLY_DISPATCHED => 'warning',
            self::STATUS_FULLY_DISPATCHED     => 'primary',
            self::STATUS_DEPLETED             => 'secondary',
            self::STATUS_EXPIRED              => 'danger',
            self::STATUS_QUARANTINE           => 'dark',
            default                           => 'secondary',
        };
    }

    public static function getQualityBadgeClass(string $grade): string
    {
        return match($grade) {
            self::QUALITY_A        => 'success',
            self::QUALITY_B        => 'primary',
            self::QUALITY_C        => 'danger',
            self::QUALITY_UNGRADED => 'secondary',
            default                => 'secondary',
        };
    }

    /**
     * True if a batch status is editable (only 'active' can be freely edited).
     */
    public static function isEditable(string $status): bool
    {
        return $status === self::STATUS_ACTIVE;
    }

    // ──────────────────────────────────────────────────────
    // PRIVATE
    // ──────────────────────────────────────────────────────

    private function buildFilters(int $farmId, array $filters): array
    {
        $where  = [];
        $params = [];

        if ($farmId > 0) {
            $where[]          = 'hb.farm_id = :fid';
            $params['fid']    = $farmId;
        }

        if (!empty($filters['search'])) {
            $where[]             = "(hb.batch_number LIKE :q OR p.name LIKE :q)";
            $params['q']         = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['product_id'])) {
            $where[]             = 'hb.product_id = :pid';
            $params['pid']       = (int)$filters['product_id'];
        }

        if (!empty($filters['status'])) {
            $where[]             = 'hb.status = :status';
            $params['status']    = $filters['status'];
        }

        if (!empty($filters['quality'])) {
            $where[]             = 'hb.quality_grade = :quality';
            $params['quality']   = $filters['quality'];
        }

        if (!empty($filters['date_from'])) {
            $where[]             = 'hb.harvest_date >= :dfrom';
            $params['dfrom']     = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[]             = 'hb.harvest_date <= :dto';
            $params['dto']       = $filters['date_to'];
        }

        if (!empty($filters['batch'])) {
            $where[]             = 'hb.batch_number LIKE :batch';
            $params['batch']     = '%' . $filters['batch'] . '%';
        }

        return [
            $where ? implode(' AND ', $where) : '1=1',
            $params,
        ];
    }
}
