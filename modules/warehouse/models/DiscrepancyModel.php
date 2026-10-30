<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 10
 * =========================================================
 * File    : modules/warehouse/models/DiscrepancyModel.php
 * Purpose : Data access for warehouse_discrepancies.
 *           Immutable records — INSERT only, never deleted.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Warehouse\Models;

use AAM\Core\BaseModel;

class DiscrepancyModel extends BaseModel
{
    protected string $table      = 'warehouse_discrepancies';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = false;

    protected array $fillable = [
        'discrepancy_number', 'reference_type', 'reference_id',
        'receiving_item_id', 'warehouse_id', 'farm_id', 'shop_id',
        'product_id', 'batch_number',
        'expected_quantity', 'actual_quantity',
        'variance_quantity', 'variance_percent',
        'discrepancy_type', 'reason', 'status',
        'reported_by', 'resolved_by', 'resolved_at', 'resolution_notes',
    ];

    public const STATUS_OPEN              = 'open';
    public const STATUS_UNDER_REVIEW     = 'under_review';
    public const STATUS_RESOLVED         = 'resolved';
    public const STATUS_REJECTED         = 'rejected';
    public const STATUS_DISCREPANCY_PENDING = 'discrepancy_pending'; // receiving-level alias

    // ──────────────────────────────────────────────────────
    // READ
    // ──────────────────────────────────────────────────────

    /**
     * BaseModel::paginate() compatibility override.
     */
    public function paginate(
        int    $page       = 1,
        int    $perPage    = 20,
        string $search     = '',
        array  $searchCols = [],
        array  $conditions = [],
        string $orderBy    = 'id',
        string $direction  = 'DESC'
    ): array {
        $warehouseId = (int)($conditions['warehouse_id'] ?? 1);
        return $this->paginateWarehouse($warehouseId, $page, $perPage);
    }

    /**
     * Paginated list of discrepancies for a warehouse.
     */
    public function paginateWarehouse(
        int   $warehouseId,
        int   $page    = 1,
        int   $perPage = PAGINATION_DEFAULT,
        array $filters = []
    ): array {
        $page    = max(1, $page);
        $perPage = min($perPage, PAGINATION_MAX);
        $offset  = ($page - 1) * $perPage;

        [$where, $params] = $this->buildFilters($warehouseId, $filters);

        $total = (int)($this->rawQueryOne(
            "SELECT COUNT(*) AS c FROM warehouse_discrepancies wd WHERE {$where}", $params
        )['c'] ?? 0);

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $data = $this->rawQuery(
            "SELECT
                wd.*,
                p.name         AS product_name,
                p.sku          AS product_sku,
                f.name         AS farm_name,
                s.name         AS shop_name,
                CONCAT(rp.first_name,' ',rp.last_name) AS reported_by_name,
                CONCAT(rv.first_name,' ',rv.last_name) AS resolved_by_name
             FROM warehouse_discrepancies wd
             LEFT JOIN products p   ON p.id  = wd.product_id
             LEFT JOIN farms    f   ON f.id  = wd.farm_id
             LEFT JOIN shops    s   ON s.id  = wd.shop_id
             LEFT JOIN users    rp  ON rp.id = wd.reported_by
             LEFT JOIN users    rv  ON rv.id = wd.resolved_by
             WHERE {$where}
             ORDER BY wd.reported_at DESC
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
     * Single discrepancy with full details.
     */
    public function getWithDetails(int $id): ?array
    {
        return $this->rawQueryOne(
            "SELECT
                wd.*,
                p.name         AS product_name,
                p.sku          AS product_sku,
                w.name         AS warehouse_name,
                f.name         AS farm_name,
                s.name         AS shop_name,
                CONCAT(rp.first_name,' ',rp.last_name) AS reported_by_name,
                CONCAT(rv.first_name,' ',rv.last_name) AS resolved_by_name
             FROM warehouse_discrepancies wd
             LEFT JOIN products    p  ON p.id  = wd.product_id
             LEFT JOIN warehouses  w  ON w.id  = wd.warehouse_id
             LEFT JOIN farms       f  ON f.id  = wd.farm_id
             LEFT JOIN shops       s  ON s.id  = wd.shop_id
             LEFT JOIN users       rp ON rp.id = wd.reported_by
             LEFT JOIN users       rv ON rv.id = wd.resolved_by
             WHERE wd.id = :id",
            ['id' => $id]
        );
    }

    /**
     * Count open discrepancies.
     */
    public function countOpen(int $warehouseId): int
    {
        return (int)($this->rawQueryOne(
            "SELECT COUNT(*) AS c FROM warehouse_discrepancies
             WHERE warehouse_id = :wid AND status IN ('open','under_review')",
            ['wid' => $warehouseId]
        )['c'] ?? 0);
    }

    // ──────────────────────────────────────────────────────
    // WRITE
    // ──────────────────────────────────────────────────────

    /**
     * Get all discrepancies for a Warehouse→Shop transfer, with GM decision data.
     * Used by the TransferController show page and GM review pages.
     */
    public function getForTransfer(int $transferId): array
    {
        return $this->rawQuery(
            "SELECT
                wd.*,
                p.name          AS product_name,
                p.sku           AS product_sku,
                u.abbreviation  AS unit_abbr,
                w.name          AS warehouse_name,
                s.name          AS shop_name,
                CONCAT(rp.first_name,' ',rp.last_name) AS reported_by_name,
                CONCAT(gm.first_name,' ',gm.last_name) AS gm_reviewed_by_name
             FROM warehouse_discrepancies wd
             LEFT JOIN products         p  ON p.id  = wd.product_id
             LEFT JOIN units_of_measure u  ON u.id  = (
                 SELECT wti.unit_id FROM warehouse_transfer_items wti
                 WHERE wti.transfer_id = wd.reference_id
                   AND wti.product_id  = wd.product_id
                 LIMIT 1
             )
             LEFT JOIN warehouses       w  ON w.id  = wd.warehouse_id
             LEFT JOIN shops            s  ON s.id  = wd.shop_id
             LEFT JOIN users            rp ON rp.id = wd.reported_by
             LEFT JOIN users            gm ON gm.id = wd.gm_reviewed_by
             WHERE wd.reference_type = 'transfer'
               AND wd.reference_id   = :tid
             ORDER BY wd.id ASC",
            ['tid' => $transferId]
        );
    }

    /**
     * Get the workflow log for a discrepancy — transfer context.
     * Returns both receiving_id and transfer_id rows.
     */
    public function getWorkflowLogForTransfer(int $discrepancyId): array
    {
        return $this->rawQuery(
            "SELECT dwl.*, CONCAT(u.first_name,' ',u.last_name) AS actor_name
             FROM discrepancy_workflow_log dwl
             LEFT JOIN users u ON u.id = dwl.actor_user_id
             WHERE dwl.discrepancy_id = :did
             ORDER BY dwl.created_at ASC",
            ['did' => $discrepancyId]
        );
    }

    /**
     * Get pending transfer discrepancies for GM review
     * (transfers with status = discrepancy_pending).
     */
    public function getPendingTransferGMReview(): array
    {
        return $this->rawQuery(
            "SELECT
                wt.id              AS transfer_id,
                wt.transfer_number,
                wt.transfer_date,
                wt.total_quantity  AS total_sent,
                wt.status          AS transfer_status,
                w.name             AS warehouse_name,
                s.name             AS shop_name,
                s.location         AS shop_location,
                CONCAT(ui.first_name,' ',ui.last_name) AS initiated_by_name,
                CONCAT(ur.first_name,' ',ur.last_name) AS received_by_name,
                COUNT(wd.id)              AS discrepancy_count,
                SUM(wd.variance_quantity) AS total_missing,
                SUM(wd.actual_quantity)   AS total_received
             FROM warehouse_transfers wt
             JOIN warehouse_discrepancies wd
                  ON wd.reference_type = 'transfer'
                 AND wd.reference_id   = wt.id
                 AND wd.gm_action IS NULL
             LEFT JOIN warehouses w  ON w.id  = wt.warehouse_id
             LEFT JOIN shops      s  ON s.id  = wt.shop_id
             LEFT JOIN users      ui ON ui.id = wt.initiated_by
             LEFT JOIN users      ur ON ur.id = wt.received_by
             WHERE wt.status = 'discrepancy_pending'
             GROUP BY wt.id, wt.transfer_number, wt.transfer_date,
                      wt.total_quantity, wt.status,
                      w.name, s.name, s.location,
                      ui.first_name, ui.last_name,
                      ur.first_name, ur.last_name
             ORDER BY wt.updated_at ASC"
        );
    }

    /**
     * Get all discrepancies for a receiving record, with GM decision data.
     * Used by the WM receiving-detail and GM review pages.
     */
    public function getForReceiving(int $receivingId): array
    {
        return $this->rawQuery(
            "SELECT
                wd.*,
                p.name          AS product_name,
                p.sku           AS product_sku,
                u.abbreviation  AS unit_abbr,
                f.name          AS farm_name,
                w.name          AS warehouse_name,
                CONCAT(rp.first_name,' ',rp.last_name) AS reported_by_name,
                CONCAT(gm.first_name,' ',gm.last_name) AS gm_reviewed_by_name
             FROM warehouse_discrepancies wd
             LEFT JOIN products         p  ON p.id  = wd.product_id
             LEFT JOIN units_of_measure u  ON u.id  = (SELECT unit_id FROM warehouse_receiving_items WHERE id = wd.receiving_item_id LIMIT 1)
             LEFT JOIN farms            f  ON f.id  = wd.farm_id
             LEFT JOIN warehouses       w  ON w.id  = wd.warehouse_id
             LEFT JOIN users            rp ON rp.id = wd.reported_by
             LEFT JOIN users            gm ON gm.id = wd.gm_reviewed_by
             WHERE wd.reference_type = 'receiving' AND wd.reference_id = :rid
             ORDER BY wd.id ASC",
            ['rid' => $receivingId]
        );
    }

    /**
     * Get pending discrepancy receivings for GM review.
     */
    public function getPendingGMReview(): array
    {
        return $this->rawQuery(
            "SELECT
                wr.id AS receiving_id,
                wr.receiving_number,
                wr.received_date,
                wr.total_expected,
                wr.total_received,
                wr.status AS receiving_status,
                f.name   AS farm_name,
                w.name   AS warehouse_name,
                CONCAT(u.first_name,' ',u.last_name) AS received_by_name,
                fd.dispatch_number,
                fd.dispatch_date,
                COUNT(wd.id) AS discrepancy_count,
                SUM(wd.variance_quantity) AS total_missing
             FROM warehouse_receiving wr
             JOIN warehouse_discrepancies wd ON wd.reference_type = 'receiving' AND wd.reference_id = wr.id
             LEFT JOIN farm_dispatches fd ON fd.id = wr.dispatch_id
             LEFT JOIN farms           f  ON f.id  = wr.farm_id
             LEFT JOIN warehouses      w  ON w.id  = wr.warehouse_id
             LEFT JOIN users           u  ON u.id  = wr.received_by
             WHERE wr.status = 'discrepancy_pending'
               AND wd.gm_action IS NULL
             GROUP BY wr.id, wr.receiving_number, wr.received_date,
                      wr.total_expected, wr.total_received, wr.status,
                      f.name, w.name, u.first_name, u.last_name,
                      fd.dispatch_number, fd.dispatch_date
             ORDER BY wr.received_date ASC"
        );
    }

    /**
     * Get the workflow log for a discrepancy.
     */
    public function getWorkflowLog(int $discrepancyId): array
    {
        return $this->rawQuery(
            "SELECT dwl.*, CONCAT(u.first_name,' ',u.last_name) AS actor_name
             FROM discrepancy_workflow_log dwl
             LEFT JOIN users u ON u.id = dwl.actor_user_id
             WHERE dwl.discrepancy_id = :did
             ORDER BY dwl.created_at ASC",
            ['did' => $discrepancyId]
        );
    }

    /**
     * Generate unique discrepancy number DIS-YYYY-NNNN.
     */
    public function generateNumber(): string
    {
        $year = date('Y');
        $row  = $this->rawQueryOne(
            "SELECT discrepancy_number FROM warehouse_discrepancies
             WHERE discrepancy_number LIKE :p ORDER BY id DESC LIMIT 1",
            ['p' => "DIS-{$year}-%"]
        );
        $seq = $row ? (int)substr(strrchr($row['discrepancy_number'], '-'), 1) + 1 : 1;
        return "DIS-{$year}-" . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create a new discrepancy record.
     * Supports explicit 'status' override (e.g. 'under_review' for GM workflow).
     */
    public function createDiscrepancy(array $data): int
    {
        $data['discrepancy_number'] = $this->generateNumber();
        // Default status to 'open' unless caller specifies
        if (!isset($data['status'])) {
            $data['status'] = self::STATUS_OPEN;
        }

        $cols = implode(', ', array_map(fn($c) => "`{$c}`", array_keys($data)));
        $phs  = implode(', ', array_map(fn($c) => ":{$c}", array_keys($data)));

        $this->rawExecute(
            "INSERT INTO warehouse_discrepancies ({$cols}) VALUES ({$phs})",
            $data
        );
        return $this->db->lastInsertId();
    }

    /**
     * Update discrepancy status (under_review / resolved / rejected).
     */
    public function updateStatus(
        int     $id,
        string  $status,
        int     $resolvedBy,
        ?string $notes = null
    ): void {
        $this->rawExecute(
            "UPDATE warehouse_discrepancies
             SET status           = :s,
                 resolved_by      = :rb,
                 resolved_at      = CASE WHEN :s2 = 'resolved' THEN NOW() ELSE resolved_at END,
                 resolution_notes = COALESCE(:notes, resolution_notes),
                 updated_at       = NOW()
             WHERE id = :id",
            [
                's'     => $status,
                's2'    => $status,
                'rb'    => $resolvedBy,
                'notes' => $notes,
                'id'    => $id,
            ]
        );
    }

    // ──────────────────────────────────────────────────────
    // LABEL HELPERS
    // ──────────────────────────────────────────────────────
    public static function statusLabel(string $s): string
    {
        return match($s) {
            'open'           => 'Open',
            'under_review'   => 'Under Review',
            'resolved'       => 'Resolved',
            'rejected'       => 'Rejected',
            'gm_rejected'    => 'GM Rejected',
            'final_rejected' => 'Final Rejected',
            default          => ucfirst(str_replace('_', ' ', $s)),
        };
    }

    public static function statusBadge(string $s): string
    {
        return match($s) {
            'open'           => 'danger',
            'under_review'   => 'warning',
            'resolved'       => 'success',
            'rejected'       => 'secondary',
            'gm_rejected'    => 'danger',
            'final_rejected' => 'dark',
            default          => 'secondary',
        };
    }

    public static function typeLabel(string $t): string
    {
        return match($t) {
            'short_delivery'    => 'Short Delivery',
            'over_delivery'     => 'Over Delivery',
            'damaged'           => 'Damaged',
            'spoiled'           => 'Spoiled',
            'wrong_product'     => 'Wrong Product',
            'wrong_unit'        => 'Wrong Unit',
            'expired'           => 'Expired',
            'packaging_damage'  => 'Packaging Damage',
            'other'             => 'Other',
            default             => ucfirst(str_replace('_', ' ', $t)),
        };
    }

    private function buildFilters(int $warehouseId, array $filters): array
    {
        $where  = ['wd.warehouse_id = :wid'];
        $params = ['wid' => $warehouseId];

        // BUSINESS RULE: The WM discrepancies page shows ONLY receiving discrepancies
        // (Farm→Warehouse, reference_type = 'receiving').
        // Transfer discrepancies (Warehouse→Shop, reference_type = 'transfer') are
        // managed exclusively through the GM approval workflow and must NOT appear
        // here — they are not manually resolvable by the Warehouse Manager.
        $where[] = "wd.reference_type = 'receiving'";

        if (!empty($filters['status'])) {
            $where[]          = 'wd.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[]      = "(wd.discrepancy_number LIKE :q OR p.name LIKE :q2)";
            $params['q']  = '%' . $filters['search'] . '%';
            $params['q2'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['date_from'])) {
            $where[]         = 'wd.reported_at >= :dfrom';
            $params['dfrom'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[]       = 'wd.reported_at <= :dto';
            $params['dto'] = $filters['date_to'] . ' 23:59:59';
        }

        return [implode(' AND ', $where), $params];
    }
}
