<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 10
 * =========================================================
 * File    : modules/warehouse/models/TransferModel.php
 * Purpose : Data access for warehouse_transfers and
 *           warehouse_transfer_items.
 *           Handles full transfer workflow:
 *             create → pending → in_transit → received
 *           With validation: available stock >= requested qty.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Warehouse\Models;

use AAM\Core\BaseModel;

class TransferModel extends BaseModel
{
    protected string $table      = 'warehouse_transfers';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = false;

    protected array $fillable = [
        'transfer_number', 'warehouse_id', 'shop_id', 'status',
        'initiated_by', 'approved_by', 'received_by',
        'transfer_date', 'expected_date', 'received_date',
        'total_items', 'total_quantity', 'notes',
    ];

    public const STATUS_DRAFT                      = 'draft';
    public const STATUS_PENDING                    = 'pending';
    public const STATUS_APPROVED                   = 'approved';
    public const STATUS_IN_TRANSIT                 = 'in_transit';
    public const STATUS_RECEIVED                   = 'received';
    public const STATUS_PARTIALLY_RECEIVED         = 'partially_received';
    public const STATUS_DISCREPANCY                = 'discrepancy';
    public const STATUS_DISCREPANCY_PENDING        = 'discrepancy_pending';
    public const STATUS_GM_APPROVED                = 'gm_approved';
    public const STATUS_GM_REJECTED                = 'gm_rejected';
    public const STATUS_RECEIVED_WITH_DISCREPANCY  = 'received_with_discrepancy';
    public const STATUS_FINAL_REJECTED             = 'final_rejected';
    public const STATUS_REJECTED                   = 'rejected';   // GM rejected transfer creation
    public const STATUS_CANCELLED                  = 'cancelled';  // WM cancelled before dispatch

    public const MAX_DISCREPANCY_REJECTIONS = 2; // After this count → final rejection

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
     * Paginated transfers for a destination shop (salesperson / receiving view).
     * Defaults to showing in_transit transfers first so the receiver sees what needs action.
     */
    public function paginateShop(
        int   $shopId,
        int   $page    = 1,
        int   $perPage = PAGINATION_DEFAULT,
        array $filters = []
    ): array {
        $page    = max(1, $page);
        $perPage = min($perPage, PAGINATION_MAX);
        $offset  = ($page - 1) * $perPage;

        $where  = ['wt.shop_id = :sid'];
        $params = ['sid' => $shopId > 0 ? $shopId : 0];

        if (!empty($filters['status'])) {
            $where[]          = 'wt.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[]      = "(wt.transfer_number LIKE :q OR w.name LIKE :q2)";
            $params['q']  = '%' . $filters['search'] . '%';
            $params['q2'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['date_from'])) {
            $where[]         = 'wt.transfer_date >= :dfrom';
            $params['dfrom'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]       = 'wt.transfer_date <= :dto';
            $params['dto'] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);

        $total = (int)($this->rawQueryOne(
            "SELECT COUNT(*) AS c FROM warehouse_transfers wt WHERE {$whereClause}", $params
        )['c'] ?? 0);

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $data = $this->rawQuery(
            "SELECT
                wt.*,
                w.name        AS warehouse_name,
                s.name        AS shop_name,
                s.location    AS shop_location,
                CONCAT(ui.first_name,' ',ui.last_name) AS initiated_by_name,
                CONCAT(ur.first_name,' ',ur.last_name) AS received_by_name
             FROM warehouse_transfers wt
             LEFT JOIN warehouses w  ON w.id = wt.warehouse_id
             LEFT JOIN shops      s  ON s.id = wt.shop_id
             LEFT JOIN users      ui ON ui.id = wt.initiated_by
             LEFT JOIN users      ur ON ur.id = wt.received_by
             WHERE {$whereClause}
             ORDER BY
               CASE wt.status
                 WHEN 'in_transit' THEN 1
                 WHEN 'approved'   THEN 2
                 WHEN 'pending'    THEN 3
                 ELSE 4
               END ASC,
               wt.transfer_date DESC, wt.id DESC
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
     * Paginated transfers for a warehouse.
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
            "SELECT COUNT(*) AS c FROM warehouse_transfers wt WHERE {$where}", $params
        )['c'] ?? 0);

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $data = $this->rawQuery(
            "SELECT
                wt.*,
                w.name        AS warehouse_name,
                s.name        AS shop_name,
                s.location    AS shop_location,
                CONCAT(ui.first_name,' ',ui.last_name) AS initiated_by_name,
                CONCAT(ur.first_name,' ',ur.last_name) AS received_by_name
             FROM warehouse_transfers wt
             LEFT JOIN warehouses w  ON w.id = wt.warehouse_id
             LEFT JOIN shops      s  ON s.id = wt.shop_id
             LEFT JOIN users      ui ON ui.id = wt.initiated_by
             LEFT JOIN users      ur ON ur.id = wt.received_by
             WHERE {$where}
             ORDER BY wt.transfer_date DESC, wt.id DESC
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
     * Get full transfer with line items.
     */
    public function getWithItems(int $id): ?array
    {
        $transfer = $this->rawQueryOne(
            "SELECT
                wt.*,
                w.name        AS warehouse_name,
                w.location    AS warehouse_location,
                s.name        AS shop_name,
                s.location    AS shop_location,
                CONCAT(ui.first_name,' ',ui.last_name) AS initiated_by_name,
                CONCAT(ur.first_name,' ',ur.last_name) AS received_by_name
             FROM warehouse_transfers wt
             LEFT JOIN warehouses w  ON w.id = wt.warehouse_id
             LEFT JOIN shops      s  ON s.id = wt.shop_id
             LEFT JOIN users      ui ON ui.id = wt.initiated_by
             LEFT JOIN users      ur ON ur.id = wt.received_by
             WHERE wt.id = :id",
            ['id' => $id]
        );

        if (!$transfer) return null;

        $transfer['items'] = $this->rawQuery(
            "SELECT
                wti.*,
                p.name          AS product_name,
                p.sku           AS product_sku,
                u.abbreviation  AS unit_abbr,
                u.name          AS unit_name
             FROM warehouse_transfer_items wti
             LEFT JOIN products         p ON p.id = wti.product_id
             LEFT JOIN units_of_measure u ON u.id = wti.unit_id
             WHERE wti.transfer_id = :tid
             ORDER BY wti.id ASC",
            ['tid' => $id]
        );

        return $transfer;
    }

    /**
     * Count pending transfers (for dashboard).
     */
    public function countPending(int $warehouseId): int
    {
        return (int)($this->rawQueryOne(
            "SELECT COUNT(*) AS c FROM warehouse_transfers
             WHERE warehouse_id = :wid
               AND status = 'pending'",
            ['wid' => $warehouseId]
        )['c'] ?? 0);
    }

    /**
     * Today's dispatched quantity.
     * Excludes cancelled, draft, rejected, and final_rejected transfers —
     * final_rejected stock was returned to warehouse and must not count.
     */
    public function getTodayDispatched(int $warehouseId): float
    {
        return (float)($this->rawQueryOne(
            "SELECT COALESCE(SUM(total_quantity), 0) AS v
             FROM warehouse_transfers
             WHERE warehouse_id = :wid
               AND transfer_date = CURDATE()
               AND status NOT IN ('cancelled','draft','rejected','final_rejected')",
            ['wid' => $warehouseId]
        )['v'] ?? 0);
    }

    // ──────────────────────────────────────────────────────
    // WRITE
    // ──────────────────────────────────────────────────────

    /**
     * Generate unique transfer number TRF-YYYY-NNNN.
     */
    public function generateTransferNumber(): string
    {
        $year = date('Y');
        $row  = $this->rawQueryOne(
            "SELECT transfer_number FROM warehouse_transfers
             WHERE transfer_number LIKE :p ORDER BY id DESC LIMIT 1",
            ['p' => "TRF-{$year}-%"]
        );
        $seq = $row ? (int)substr(strrchr($row['transfer_number'], '-'), 1) + 1 : 1;
        return "TRF-{$year}-" . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Insert line items for a transfer.
     */
    public function insertItems(int $transferId, array $items): void
    {
        foreach ($items as $item) {
            $this->rawExecute(
                "INSERT INTO warehouse_transfer_items
                    (transfer_id, product_id, unit_id, batch_number, expiry_date,
                     quantity_requested, quantity_sent, item_status, notes)
                 VALUES
                    (:tid, :pid, :uid, :bn, :ed, :qr, :qs, 'pending', :notes)",
                [
                    'tid'   => $transferId,
                    'pid'   => (int)$item['product_id'],
                    'uid'   => !empty($item['unit_id']) ? (int)$item['unit_id'] : null,
                    'bn'    => $item['batch_number'] ?? null,
                    'ed'    => $item['expiry_date']  ?? null,
                    'qr'    => (float)$item['quantity_requested'],
                    'qs'    => (float)$item['quantity_requested'], // same as requested initially
                    'notes' => $item['notes'] ?? null,
                ]
            );
        }
    }

    /**
     * Update transfer status.
     */
    public function setStatus(int $id, string $status, ?int $userId = null): void
    {
        $extra  = '';
        $params = ['s' => $status, 'id' => $id];

        $receivedStatuses = [
            self::STATUS_RECEIVED,
            self::STATUS_PARTIALLY_RECEIVED,
            self::STATUS_RECEIVED_WITH_DISCREPANCY,
        ];

        if (in_array($status, $receivedStatuses, true)) {
            $extra = ', received_date = COALESCE(received_date, CURDATE()), received_by = COALESCE(received_by, :rb)';
            $params['rb'] = $userId;
        } elseif ($status === self::STATUS_IN_TRANSIT) {
            $extra = ', transfer_date = COALESCE(transfer_date, CURDATE())';
        }

        $this->rawExecute(
            "UPDATE warehouse_transfers SET status = :s {$extra}, updated_at = NOW() WHERE id = :id",
            $params
        );
    }

    /**
     * Update item received quantities.
     */
    public function updateItemReceived(int $itemId, float $qtyReceived): void
    {
        $status = 'received';
        // We get expected by fetching the item
        $item = $this->rawQueryOne(
            "SELECT quantity_requested FROM warehouse_transfer_items WHERE id = :id",
            ['id' => $itemId]
        );
        if ($item) {
            $requested = (float)$item['quantity_requested'];
            if ($qtyReceived <= 0)               $status = 'discrepancy';
            elseif ($qtyReceived < $requested)   $status = 'partial';
            elseif ($qtyReceived >= $requested)  $status = 'received';
        }

        $this->rawExecute(
            "UPDATE warehouse_transfer_items
             SET quantity_received = :qr, item_status = :st
             WHERE id = :id",
            ['qr' => $qtyReceived, 'st' => $status, 'id' => $itemId]
        );
    }

    // ──────────────────────────────────────────────────────
    // LABEL HELPERS
    // ──────────────────────────────────────────────────────
    public static function statusLabel(string $s): string
    {
        return match($s) {
            'draft'                     => 'Draft',
            'pending'                   => 'Pending Approval',
            'approved'                  => 'Approved',
            'in_transit'                => 'In Transit',
            'received'                  => 'Received',
            'partially_received'        => 'Partially Received',
            'discrepancy'               => 'Discrepancy',
            'discrepancy_pending'       => 'Pending GM Approval',
            'gm_approved'               => 'GM Approved',
            'gm_rejected'               => 'Rejected — Awaiting Correction',
            'received_with_discrepancy' => 'Completed with Discrepancy',
            'final_rejected'            => 'Final Rejected',
            'rejected'                  => 'Rejected by GM',
            'cancelled'                 => 'Cancelled',
            default                     => ucfirst(str_replace('_', ' ', $s)),
        };
    }

    public static function statusBadge(string $s): string
    {
        return match($s) {
            'draft'                     => 'secondary',
            'pending'                   => 'warning',
            'approved'                  => 'info',
            'in_transit'                => 'primary',
            'received'                  => 'success',
            'partially_received'        => 'success',
            'discrepancy'               => 'danger',
            'discrepancy_pending'       => 'danger',
            'gm_approved'               => 'warning',
            'gm_rejected'               => 'secondary',
            'received_with_discrepancy' => 'success',
            'final_rejected'            => 'dark',
            'rejected'                  => 'danger',
            'cancelled'                 => 'secondary',
            default                     => 'secondary',
        };
    }

    /**
     * Returns true if the transfer is in a terminal/completed state.
     */
    public static function isCompleted(string $s): bool
    {
        return in_array($s, [
            self::STATUS_RECEIVED,
            self::STATUS_PARTIALLY_RECEIVED,
            self::STATUS_RECEIVED_WITH_DISCREPANCY,
            self::STATUS_FINAL_REJECTED,
            self::STATUS_CANCELLED,
        ], true);
    }

    /**
     * Get pending transfer discrepancies for GM review.
     * Returns transfers whose status = discrepancy_pending.
     */
    public function getPendingDiscrepancyReview(): array
    {
        return $this->rawQuery(
            "SELECT
                wt.*,
                w.name  AS warehouse_name,
                s.name  AS shop_name,
                s.location AS shop_location,
                CONCAT(ui.first_name,' ',ui.last_name) AS initiated_by_name,
                CONCAT(ur.first_name,' ',ur.last_name) AS received_by_name,
                COUNT(wd.id)           AS discrepancy_count,
                SUM(wd.variance_quantity) AS total_missing,
                SUM(wd.expected_quantity) AS total_expected_dis,
                SUM(wd.actual_quantity)   AS total_received_dis
             FROM warehouse_transfers wt
             LEFT JOIN warehouses w  ON w.id  = wt.warehouse_id
             LEFT JOIN shops      s  ON s.id  = wt.shop_id
             LEFT JOIN users      ui ON ui.id = wt.initiated_by
             LEFT JOIN users      ur ON ur.id = wt.received_by
             LEFT JOIN warehouse_discrepancies wd
                    ON wd.reference_type = 'transfer'
                   AND wd.reference_id   = wt.id
                   AND wd.gm_action IS NULL
             WHERE wt.status = 'discrepancy_pending'
             GROUP BY wt.id, w.name, s.name, s.location,
                      ui.first_name, ui.last_name,
                      ur.first_name, ur.last_name
             ORDER BY wt.updated_at ASC"
        );
    }

    /**
     * Count transfers pending GM discrepancy review (for dashboard badges).
     */
    public function countPendingDiscrepancyReview(): int
    {
        return (int)($this->rawQueryOne(
            "SELECT COUNT(*) AS c FROM warehouse_transfers
             WHERE status = 'discrepancy_pending'"
        )['c'] ?? 0);
    }

    private function buildFilters(int $warehouseId, array $filters): array
    {
        $where  = ['wt.warehouse_id = :wid'];
        $params = ['wid' => $warehouseId];

        if (!empty($filters['status'])) {
            $where[]          = 'wt.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['shop_id'])) {
            $where[]           = 'wt.shop_id = :sid';
            $params['sid']     = (int)$filters['shop_id'];
        }
        if (!empty($filters['search'])) {
            $where[]      = "(wt.transfer_number LIKE :q OR s.name LIKE :q2)";
            $params['q']  = '%' . $filters['search'] . '%';
            $params['q2'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['date_from'])) {
            $where[]         = 'wt.transfer_date >= :dfrom';
            $params['dfrom'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]       = 'wt.transfer_date <= :dto';
            $params['dto'] = $filters['date_to'];
        }

        return [implode(' AND ', $where), $params];
    }
}
