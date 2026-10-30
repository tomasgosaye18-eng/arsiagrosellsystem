<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 11
 * =========================================================
 * File    : modules/inventory/models/StockAdjustmentModel.php
 * Purpose : Data access for stock_adjustments.
 *           INSERT-driven — adjustments are transactions,
 *           never direct edits to inventory quantity.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Inventory\Models;

use AAM\Core\BaseModel;

class StockAdjustmentModel extends BaseModel
{
    protected string $table      = 'stock_adjustments';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = false;

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_APPLIED  = 'applied';

    public const REASONS = [
        'damaged'          => 'Damaged Goods',
        'spoilage'         => 'Spoilage / Expiry',
        'theft'            => 'Theft / Loss',
        'found_stock'      => 'Found / Surplus Stock',
        'measurement_error'=> 'Measurement / Recording Error',
        'transfer_error'   => 'Transfer Error',
        'correction'       => 'General Correction',
        'other'            => 'Other',
    ];

    public function generateNumber(): string
    {
        $year = date('Y');
        $row  = $this->rawQueryOne(
            "SELECT adjustment_number FROM stock_adjustments
             WHERE adjustment_number LIKE :p ORDER BY id DESC LIMIT 1",
            ['p' => "ADJ-{$year}-%"]
        );
        $seq = $row ? (int) substr(strrchr($row['adjustment_number'], '-'), 1) + 1 : 1;
        return "ADJ-{$year}-" . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }

    public function paginate(
        int    $page       = 1,
        int    $perPage    = 20,
        string $search     = '',
        array  $searchCols = [],
        array  $conditions = [],
        string $orderBy    = 'id',
        string $direction  = 'DESC'
    ): array {
        return $this->paginateLocation(
            $conditions['location_type'] ?? '',
            (int)($conditions['location_id'] ?? 0),
            $page, $perPage
        );
    }

    public function paginateLocation(
        string $locationType,
        int    $locationId,
        int    $page    = 1,
        int    $perPage = PAGINATION_DEFAULT,
        array  $filters = []
    ): array {
        $page    = max(1, $page);
        $perPage = min($perPage, PAGINATION_MAX);
        $offset  = ($page - 1) * $perPage;

        $where  = ['1=1'];
        $params = [];

        if ($locationType !== '') {
            $where[]          = 'sa.location_type = :ltype';
            $params['ltype']  = $locationType;
        }
        if ($locationId > 0) {
            $where[]          = 'sa.location_id = :lid';
            $params['lid']    = $locationId;
        }
        if (!empty($filters['status'])) {
            $where[]          = 'sa.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[]          = "(sa.adjustment_number LIKE :q OR p.name LIKE :q2)";
            $params['q']      = '%' . $filters['search'] . '%';
            $params['q2']     = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['date_from'])) {
            $where[]          = 'DATE(sa.created_at) >= :dfrom';
            $params['dfrom']  = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]          = 'DATE(sa.created_at) <= :dto';
            $params['dto']    = $filters['date_to'];
        }

        $w = implode(' AND ', $where);

        $total = (int)($this->rawQueryOne(
            "SELECT COUNT(*) AS c FROM stock_adjustments sa
             LEFT JOIN products p ON p.id = sa.product_id
             WHERE {$w}", $params
        )['c'] ?? 0);

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $data = $this->rawQuery(
            "SELECT sa.*,
                    p.name            AS product_name,
                    p.sku             AS product_sku,
                    u.abbreviation    AS unit_abbr,
                    CONCAT(rq.first_name,' ',rq.last_name) AS requested_by_name,
                    CONCAT(ap.first_name,' ',ap.last_name) AS approved_by_name
             FROM stock_adjustments sa
             LEFT JOIN products p         ON p.id  = sa.product_id
             LEFT JOIN units_of_measure u ON u.id  = sa.unit_id
             LEFT JOIN users rq           ON rq.id = sa.requested_by
             LEFT JOIN users ap           ON ap.id = sa.approved_by
             WHERE {$w}
             ORDER BY sa.created_at DESC
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

    public function getWithDetails(int $id): ?array
    {
        return $this->rawQueryOne(
            "SELECT sa.*,
                    p.name            AS product_name,
                    p.sku             AS product_sku,
                    u.abbreviation    AS unit_abbr,
                    CONCAT(rq.first_name,' ',rq.last_name) AS requested_by_name,
                    CONCAT(ap.first_name,' ',ap.last_name) AS approved_by_name
             FROM stock_adjustments sa
             LEFT JOIN products p         ON p.id  = sa.product_id
             LEFT JOIN units_of_measure u ON u.id  = sa.unit_id
             LEFT JOIN users rq           ON rq.id = sa.requested_by
             LEFT JOIN users ap           ON ap.id = sa.approved_by
             WHERE sa.id = :id",
            ['id' => $id]
        );
    }

    public static function statusLabel(string $s): string
    {
        return match($s) {
            'pending'  => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'applied'  => 'Applied',
            default    => ucfirst($s),
        };
    }

    public static function statusBadge(string $s): string
    {
        return match($s) {
            'pending'  => 'warning',
            'approved' => 'info',
            'rejected' => 'secondary',
            'applied'  => 'success',
            default    => 'secondary',
        };
    }

    /**
     * Paginate across all locations (for GM cross-system view).
     */
    public function paginateAll(
        int   $page    = 1,
        int   $perPage = 25,
        array $filters = []
    ): array {
        return $this->paginateLocation('', 0, $page, $perPage, $filters);
    }

    /**
     * Count records by status.
     */
    public function countByStatus(string $status): int
    {
        return (int)($this->rawQueryOne(
            "SELECT COUNT(*) AS c FROM stock_adjustments WHERE status = :s",
            ['s' => $status]
        )['c'] ?? 0);
    }
}
