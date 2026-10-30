<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Seed Distribution Module
 * =========================================================
 * File    : modules/seed_distribution/models/SeedDistributionModel.php
 * Purpose : Data access for seed_distributions table.
 *           Provides CRUD, status transitions, reference
 *           number generation, and reporting queries.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\SeedDistribution\Models;

use AAM\Core\BaseModel;

class SeedDistributionModel extends BaseModel
{
    protected string $table      = 'seed_distributions';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = false;

    protected array $fillable = [
        'distribution_number', 'warehouse_id', 'product_id', 'seed_grade_id',
        'unit_id', 'distribution_type', 'quantity', 'unit_price', 'total_amount',
        'status',
        'destination_location', 'recipient_name', 'recipient_phone',
        'recipient_organization', 'recipient_notes',
        'program_name', 'distribution_date', 'notes',
        'created_by', 'approved_by', 'approved_at',
        'released_by', 'released_at', 'rejection_reason',
        'stock_movement_id', 'quantity_before', 'quantity_after',
    ];

    // ── Status constants ──────────────────────────────────
    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_RELEASED  = 'released';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REJECTED  = 'rejected';

    // ── Type constants ────────────────────────────────────
    public const TYPE_PICKUP = 'warehouse_pickup';
    public const TYPE_FIELD  = 'field_distribution';

    // ── Reference number generation ───────────────────────

    public function generateNumber(): string
    {
        $year = date('Y');
        $row  = $this->rawQueryOne(
            "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(distribution_number, '-', -1) AS UNSIGNED)), 0) AS mx
             FROM seed_distributions
             WHERE distribution_number LIKE :prefix",
            ['prefix' => "SEED-{$year}-%"]
        );
        $next = ((int)($row['mx'] ?? 0)) + 1;
        return 'SEED-' . $year . '-' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    }

    // ── Single record ────────────────────────────────────

    public function getByIdWithRelations(int $id): ?array
    {
        return $this->rawQueryOne(
            "SELECT sd.*,
                    p.name          AS product_name,
                    p.sku           AS product_sku,
                    p.is_seed,
                    p.unit_price    AS current_unit_price,
                    sg.grade_name,
                    sg.grade_code,
                    u.abbreviation  AS unit_abbr,
                    u.name          AS unit_name,
                    w.name          AS warehouse_name,
                    COALESCE(w.warehouse_code, CONCAT('WH-',LPAD(w.id,3,'0'))) AS warehouse_code,
                    w.seed_distribution_enabled,
                    CONCAT(cb.first_name,' ',cb.last_name) AS created_by_name,
                    CONCAT(ab.first_name,' ',ab.last_name) AS approved_by_name,
                    CONCAT(rb.first_name,' ',rb.last_name) AS released_by_name
             FROM seed_distributions sd
             LEFT JOIN products         p  ON p.id  = sd.product_id
             LEFT JOIN seed_grades      sg ON sg.id = sd.seed_grade_id
             LEFT JOIN units_of_measure u  ON u.id  = sd.unit_id
             LEFT JOIN warehouses       w  ON w.id  = sd.warehouse_id
             LEFT JOIN users            cb ON cb.id = sd.created_by
             LEFT JOIN users            ab ON ab.id = sd.approved_by
             LEFT JOIN users            rb ON rb.id = sd.released_by
             WHERE sd.id = :id",
            ['id' => $id]
        );
    }

    // ── Paginated list ────────────────────────────────────

    public function paginateList(
        int   $page    = 1,
        int   $perPage = 20,
        array $filters = []
    ): array {
        $page    = max(1, $page);
        $perPage = min($perPage, PAGINATION_MAX);
        $offset  = ($page - 1) * $perPage;

        [$where, $params] = $this->buildFilters($filters);

        $total = (int)($this->rawQueryOne(
            "SELECT COUNT(*) AS c
             FROM seed_distributions sd
             JOIN products p ON p.id = sd.product_id
             WHERE {$where}",
            $params
        )['c'] ?? 0);

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $data = $this->rawQuery(
            "SELECT sd.*,
                    p.name          AS product_name,
                    sg.grade_name,
                    sg.grade_code,
                    u.abbreviation  AS unit_abbr,
                    w.name          AS warehouse_name,
                    CONCAT(cb.first_name,' ',cb.last_name) AS created_by_name,
                    CONCAT(ab.first_name,' ',ab.last_name) AS approved_by_name
             FROM seed_distributions sd
             JOIN products p         ON p.id  = sd.product_id
             LEFT JOIN seed_grades sg ON sg.id = sd.seed_grade_id
             LEFT JOIN units_of_measure u ON u.id = sd.unit_id
             LEFT JOIN warehouses w  ON w.id  = sd.warehouse_id
             LEFT JOIN users cb      ON cb.id = sd.created_by
             LEFT JOIN users ab      ON ab.id = sd.approved_by
             WHERE {$where}
             ORDER BY sd.created_at DESC
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

    // ── By warehouse (for WM views) ───────────────────────

    public function paginateByWarehouse(
        int   $warehouseId,
        int   $page    = 1,
        int   $perPage = 20,
        array $filters = []
    ): array {
        $filters['warehouse_id'] = $warehouseId;
        return $this->paginateList($page, $perPage, $filters);
    }

    // ── KPI overview ─────────────────────────────────────

    public function getKpis(array $filters = []): array
    {
        [$where, $params] = $this->buildFilters($filters);

        return $this->rawQueryOne(
            "SELECT
                COUNT(*)                                          AS total_distributions,
                COALESCE(SUM(quantity),0)                         AS total_quantity,
                SUM(CASE WHEN status='pending'   THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN status='approved'  THEN 1 ELSE 0 END) AS approved_count,
                SUM(CASE WHEN status='released'  THEN 1 ELSE 0 END) AS released_count,
                SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed_count,
                SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
                SUM(CASE WHEN status='rejected'  THEN 1 ELSE 0 END) AS rejected_count,
                SUM(CASE WHEN distribution_type='warehouse_pickup' THEN quantity ELSE 0 END) AS pickup_quantity,
                SUM(CASE WHEN distribution_type='field_distribution' THEN quantity ELSE 0 END) AS field_quantity
             FROM seed_distributions sd
             JOIN products p ON p.id = sd.product_id
             WHERE {$where}",
            $params
        ) ?? [];
    }

    // ── By-product summary for GM overview ───────────────

    public function getSummaryByProduct(array $filters = []): array
    {
        [$where, $params] = $this->buildFilters($filters);

        return $this->rawQuery(
            "SELECT
                p.id        AS product_id,
                p.name      AS product_name,
                sg.grade_name,
                COUNT(*)    AS distribution_count,
                COALESCE(SUM(sd.quantity),0) AS total_quantity,
                u.abbreviation AS unit_abbr
             FROM seed_distributions sd
             JOIN products p         ON p.id  = sd.product_id
             LEFT JOIN seed_grades sg ON sg.id = sd.seed_grade_id
             LEFT JOIN units_of_measure u ON u.id = sd.unit_id
             WHERE {$where}
               AND sd.status NOT IN ('cancelled','rejected')
             GROUP BY p.id, p.name, sg.grade_name, u.abbreviation
             ORDER BY p.name ASC",
            $params
        );
    }

    // ── Recent distributions ─────────────────────────────

    public function getRecent(int $limit = 10, array $filters = []): array
    {
        [$where, $params] = $this->buildFilters($filters);
        $params['limit'] = $limit;

        return $this->rawQuery(
            "SELECT sd.*,
                    p.name         AS product_name,
                    sg.grade_name,
                    u.abbreviation AS unit_abbr,
                    w.name         AS warehouse_name
             FROM seed_distributions sd
             JOIN products p         ON p.id  = sd.product_id
             LEFT JOIN seed_grades sg ON sg.id = sd.seed_grade_id
             LEFT JOIN units_of_measure u ON u.id = sd.unit_id
             LEFT JOIN warehouses w   ON w.id  = sd.warehouse_id
             WHERE {$where}
             ORDER BY sd.created_at DESC
             LIMIT :limit",
            $params
        );
    }

    // ── Status transitions ────────────────────────────────

    public function approve(int $id, int $approvedBy): bool
    {
        return (bool)$this->rawExecute(
            "UPDATE seed_distributions
             SET status='approved', approved_by=:by, approved_at=NOW(), updated_at=NOW()
             WHERE id=:id AND status='pending'",
            ['by' => $approvedBy, 'id' => $id]
        );
    }

    public function reject(int $id, int $rejectedBy, string $reason): bool
    {
        return (bool)$this->rawExecute(
            "UPDATE seed_distributions
             SET status='rejected', approved_by=:by, approved_at=NOW(),
                 rejection_reason=:reason, updated_at=NOW()
             WHERE id=:id AND status='pending'",
            ['by' => $rejectedBy, 'id' => $id, 'reason' => $reason]
        );
    }

    public function release(int $id, int $releasedBy, int $movementId, float $qtyBefore, float $qtyAfter): bool
    {
        return (bool)$this->rawExecute(
            "UPDATE seed_distributions
             SET status='released', released_by=:by, released_at=NOW(),
                 stock_movement_id=:mid, quantity_before=:qb, quantity_after=:qa,
                 updated_at=NOW()
             WHERE id=:id AND status='approved'",
            [
                'by'  => $releasedBy,
                'mid' => $movementId,
                'qb'  => $qtyBefore,
                'qa'  => $qtyAfter,
                'id'  => $id,
            ]
        );
    }

    public function complete(int $id): bool
    {
        return (bool)$this->rawExecute(
            "UPDATE seed_distributions
             SET status='completed', updated_at=NOW()
             WHERE id=:id AND status='released'",
            ['id' => $id]
        );
    }

    public function cancel(int $id, int $userId): bool
    {
        return (bool)$this->rawExecute(
            "UPDATE seed_distributions
             SET status='cancelled', updated_at=NOW()
             WHERE id=:id AND status IN ('pending','approved')",
            ['id' => $id]
        );
    }

    // ── Filters builder ───────────────────────────────────

    private function buildFilters(array $f): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($f['warehouse_id'])) {
            $where[]              = 'sd.warehouse_id = :wid';
            $params['wid']        = (int)$f['warehouse_id'];
        }
        if (!empty($f['product_id'])) {
            $where[]              = 'sd.product_id = :pid';
            $params['pid']        = (int)$f['product_id'];
        }
        if (!empty($f['seed_grade_id'])) {
            $where[]              = 'sd.seed_grade_id = :sgid';
            $params['sgid']       = (int)$f['seed_grade_id'];
        }
        if (!empty($f['status'])) {
            $where[]              = 'sd.status = :status';
            $params['status']     = $f['status'];
        }
        if (!empty($f['distribution_type'])) {
            $where[]              = 'sd.distribution_type = :dtype';
            $params['dtype']      = $f['distribution_type'];
        }
        if (!empty($f['date_from'])) {
            $where[]              = 'sd.distribution_date >= :dfrom';
            $params['dfrom']      = $f['date_from'];
        }
        if (!empty($f['date_to'])) {
            $where[]              = 'sd.distribution_date <= :dto';
            $params['dto']        = $f['date_to'];
        }
        if (!empty($f['search'])) {
            $where[]              = "(sd.distribution_number LIKE :q OR sd.recipient_name LIKE :q2 OR sd.destination_location LIKE :q3 OR p.name LIKE :q4)";
            $params['q']          = '%' . $f['search'] . '%';
            $params['q2']         = '%' . $f['search'] . '%';
            $params['q3']         = '%' . $f['search'] . '%';
            $params['q4']         = '%' . $f['search'] . '%';
        }

        return [implode(' AND ', $where), $params];
    }
}
