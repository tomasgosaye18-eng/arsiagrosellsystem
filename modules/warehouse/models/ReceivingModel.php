<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 10
 * =========================================================
 * File    : modules/warehouse/models/ReceivingModel.php
 * Purpose : Data access for warehouse_receiving and
 *           warehouse_receiving_items.
 *           Handles full receiving workflow:
 *             pending dispatch → confirm/partial/reject
 *             → warehouse inventory increase.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Warehouse\Models;

use AAM\Core\BaseModel;

class ReceivingModel extends BaseModel
{
    protected string $table      = 'warehouse_receiving';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = false;

    protected array $fillable = [
        'receiving_number', 'dispatch_id', 'warehouse_id', 'farm_id',
        'received_by', 'received_date', 'status',
        'total_expected', 'total_received', 'notes',
    ];

    // Status constants
    public const STATUS_PENDING                    = 'pending';
    public const STATUS_RECEIVED                   = 'received';
    public const STATUS_PARTIALLY_RECEIVED         = 'partially_received';
    public const STATUS_DISCREPANCY                = 'discrepancy';
    public const STATUS_DISCREPANCY_PENDING        = 'discrepancy_pending';
    public const STATUS_GM_APPROVED                = 'gm_approved';
    public const STATUS_GM_REJECTED                = 'gm_rejected';
    public const STATUS_FINAL_REJECTED             = 'final_rejected';
    public const STATUS_REJECTED                   = 'rejected';
    public const STATUS_CANCELLED                  = 'cancelled';

    public const MAX_REJECTIONS = 2; // After this count → final rejection

    // ──────────────────────────────────────────────────────
    // READ
    // ──────────────────────────────────────────────────────

    /**
     * Paginated receiving list for a warehouse.
     * NOTE: signature matches BaseModel::paginate() requirement.
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
        // Extract warehouseId from conditions if passed
        $warehouseId = (int)($conditions['warehouse_id'] ?? 1);
        return $this->paginateWarehouse($warehouseId, $page, $perPage);
    }

    /**
     * Paginated receiving list for a specific warehouse with filters.
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
            "SELECT COUNT(*) AS c FROM warehouse_receiving wr WHERE {$where}", $params
        )['c'] ?? 0);

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $data = $this->rawQuery(
            "SELECT
                wr.*,
                fd.dispatch_number,
                fd.dispatch_date,
                fd.status           AS dispatch_status,
                f.name              AS farm_name,
                w.name              AS warehouse_name,
                CONCAT(u.first_name,' ',u.last_name) AS received_by_name,
                (SELECT COUNT(*) FROM warehouse_receiving_items wri
                 WHERE wri.receiving_id = wr.id) AS item_count
             FROM warehouse_receiving wr
             LEFT JOIN farm_dispatches fd ON fd.id = wr.dispatch_id
             LEFT JOIN farms           f  ON f.id  = wr.farm_id
             LEFT JOIN warehouses      w  ON w.id  = wr.warehouse_id
             LEFT JOIN users           u  ON u.id  = wr.received_by
             WHERE {$where}
             ORDER BY wr.received_date DESC, wr.id DESC
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
     * Get full receiving record with line items.
     */
    public function getWithItems(int $id): ?array
    {
        $rec = $this->rawQueryOne(
            "SELECT
                wr.*,
                fd.dispatch_number,
                fd.dispatch_date,
                fd.status           AS dispatch_status,
                f.name              AS farm_name,
                f.location          AS farm_location,
                w.name              AS warehouse_name,
                CONCAT(u.first_name,' ',u.last_name) AS received_by_name
             FROM warehouse_receiving wr
             LEFT JOIN farm_dispatches fd ON fd.id  = wr.dispatch_id
             LEFT JOIN farms           f  ON f.id   = wr.farm_id
             LEFT JOIN warehouses      w  ON w.id   = wr.warehouse_id
             LEFT JOIN users           u  ON u.id   = wr.received_by
             WHERE wr.id = :id",
            ['id' => $id]
        );

        if (!$rec) return null;

        $rec['items'] = $this->rawQuery(
            "SELECT
                wri.*,
                p.name         AS product_name,
                p.sku          AS product_sku,
                u.abbreviation AS unit_abbr,
                u.name         AS unit_name
             FROM warehouse_receiving_items wri
             LEFT JOIN products         p ON p.id = wri.product_id
             LEFT JOIN units_of_measure u ON u.id = wri.unit_id
             WHERE wri.receiving_id = :rid
             ORDER BY wri.id ASC",
            ['rid' => $id]
        );

        return $rec;
    }

    /**
     * Pending dispatches (in_transit) that have no receiving record yet.
     * These appear in the "Receiving Queue".
     */
    public function getPendingDispatches(int $warehouseId): array
    {
        return $this->rawQuery(
            "SELECT
                fd.*,
                f.name AS farm_name,
                f.location AS farm_location,
                CONCAT(ui.first_name,' ',ui.last_name) AS initiated_by_name,
                (SELECT COALESCE(SUM(di.quantity_dispatched), 0)
                 FROM farm_dispatch_items di WHERE di.dispatch_id = fd.id) AS total_qty,
                (SELECT COUNT(*) FROM farm_dispatch_items di
                 WHERE di.dispatch_id = fd.id) AS item_count
             FROM farm_dispatches fd
             LEFT JOIN farms f  ON f.id  = fd.farm_id
             LEFT JOIN users ui ON ui.id = fd.initiated_by
             WHERE fd.warehouse_id = :wid
               AND fd.status IN ('in_transit','pending')
               AND NOT EXISTS (
                   SELECT 1 FROM warehouse_receiving wr
                   WHERE wr.dispatch_id = fd.id
                     AND wr.status NOT IN ('cancelled','final_rejected')
               )
             ORDER BY fd.dispatch_date ASC",
            ['wid' => $warehouseId]
        );
    }

    /**
     * Count of pending dispatches awaiting receiving.
     */
    public function countPending(int $warehouseId): int
    {
        return (int)($this->rawQueryOne(
            "SELECT COUNT(*) AS c FROM farm_dispatches fd
             WHERE fd.warehouse_id = :wid
               AND fd.status IN ('in_transit','pending')
               AND NOT EXISTS (
                   SELECT 1 FROM warehouse_receiving wr
                   WHERE wr.dispatch_id = fd.id
                     AND wr.status NOT IN ('cancelled','final_rejected')
               )",
            ['wid' => $warehouseId]
        )['c'] ?? 0);
    }

    /**
     * Get dispatch with items for the receiving form.
     */
    public function getDispatchForReceiving(int $dispatchId): ?array
    {
        $dispatch = $this->rawQueryOne(
            "SELECT fd.*,
                    f.name     AS farm_name,
                    f.location AS farm_location,
                    w.name     AS warehouse_name,
                    CONCAT(u.first_name,' ',u.last_name) AS initiated_by_name
             FROM farm_dispatches fd
             LEFT JOIN farms      f ON f.id = fd.farm_id
             LEFT JOIN warehouses w ON w.id = fd.warehouse_id
             LEFT JOIN users      u ON u.id = fd.initiated_by
             WHERE fd.id = :id",
            ['id' => $dispatchId]
        );

        if (!$dispatch) return null;

        $dispatch['items'] = $this->rawQuery(
            "SELECT
                di.*,
                p.name          AS product_name,
                p.sku           AS product_sku,
                p.is_perishable,
                u.abbreviation  AS unit_abbr,
                u.name          AS unit_name,
                hb.batch_number AS batch_number,
                hb.production_date,
                hb.expiry_date,
                hb.quality_grade
             FROM farm_dispatch_items di
             LEFT JOIN products         p  ON p.id  = di.product_id
             LEFT JOIN units_of_measure u  ON u.id  = di.unit_id
             LEFT JOIN harvest_batches  hb ON hb.id = di.harvest_batch_id
             WHERE di.dispatch_id = :did
             ORDER BY di.id ASC",
            ['did' => $dispatchId]
        );

        return $dispatch;
    }

    /**
     * Today's received quantity for dashboard.
     * Excludes cancelled, rejected, and final_rejected receivings —
     * final_rejected stock was returned to farm and must not count.
     */
    public function getTodayReceived(int $warehouseId): float
    {
        return (float)($this->rawQueryOne(
            "SELECT COALESCE(SUM(total_received), 0) AS v
             FROM warehouse_receiving
             WHERE warehouse_id = :wid
               AND received_date = CURDATE()
               AND status NOT IN ('cancelled','rejected','final_rejected')",
            ['wid' => $warehouseId]
        )['v'] ?? 0);
    }

    // ──────────────────────────────────────────────────────
    // WRITE
    // ──────────────────────────────────────────────────────

    /**
     * Generate unique receiving number RCV-YYYY-NNNN.
     */
    public function generateReceivingNumber(): string
    {
        $year = date('Y');
        $row  = $this->rawQueryOne(
            "SELECT receiving_number FROM warehouse_receiving
             WHERE receiving_number LIKE :p ORDER BY id DESC LIMIT 1",
            ['p' => "RCV-{$year}-%"]
        );
        $seq = $row ? (int)substr(strrchr($row['receiving_number'], '-'), 1) + 1 : 1;
        return "RCV-{$year}-" . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Insert receiving item rows.
     */
    public function insertItems(int $receivingId, array $items): void
    {
        foreach ($items as $item) {
            $expected  = (float)($item['quantity_expected'] ?? 0);
            $received  = (float)($item['quantity_received'] ?? 0);
            $variance  = $expected - $received;
            $varPct    = $expected > 0 ? round(abs($variance / $expected) * 100, 2) : 0;

            $status = 'accepted';
            if ($received <= 0)              $status = 'rejected';
            elseif ($variance > 0)           $status = 'partial';
            elseif ($received > $expected)   $status = 'accepted'; // over-delivery still accepted

            $this->rawExecute(
                "INSERT INTO warehouse_receiving_items
                    (receiving_id, dispatch_item_id, product_id, unit_id,
                     batch_number, production_date, expiry_date,
                     quantity_expected, quantity_received, quantity_variance,
                     variance_percent, item_status, notes)
                 VALUES
                    (:rid, :diid, :pid, :uid,
                     :bn, :pd, :ed,
                     :qe, :qr, :qv, :vp, :st, :notes)",
                [
                    'rid'   => $receivingId,
                    'diid'  => (int)($item['dispatch_item_id'] ?? 0),
                    'pid'   => (int)($item['product_id']),
                    'uid'   => !empty($item['unit_id']) ? (int)$item['unit_id'] : null,
                    'bn'    => $item['batch_number'] ?? null,
                    'pd'    => $item['production_date'] ?? null,
                    'ed'    => $item['expiry_date'] ?? null,
                    'qe'    => $expected,
                    'qr'    => $received,
                    'qv'    => $variance,
                    'vp'    => $varPct,
                    'st'    => $status,
                    'notes' => $item['notes'] ?? null,
                ]
            );
        }
    }

    /**
     * Update totals on the header record.
     */
    public function updateTotals(int $receivingId, float $expected, float $received): void
    {
        $this->rawExecute(
            "UPDATE warehouse_receiving
             SET total_expected = :te, total_received = :tr, updated_at = NOW()
             WHERE id = :id",
            ['te' => $expected, 'tr' => $received, 'id' => $receivingId]
        );
    }

    /**
     * Set the receiving status.
     */
    public function setStatus(int $id, string $status): void
    {
        $this->rawExecute(
            "UPDATE warehouse_receiving SET status = :s, updated_at = NOW() WHERE id = :id",
            ['s' => $status, 'id' => $id]
        );
    }

    /**
     * Check if a dispatch already has a (non-cancelled) receiving record.
     */
    public function existsForDispatch(int $dispatchId): bool
    {
        return (bool)$this->rawQueryOne(
            "SELECT id FROM warehouse_receiving
             WHERE dispatch_id = :did AND status != 'cancelled' LIMIT 1",
            ['did' => $dispatchId]
        );
    }

    // ──────────────────────────────────────────────────────
    // STATUS HELPERS
    // ──────────────────────────────────────────────────────
    public static function statusLabel(string $s): string
    {
        return match($s) {
            'pending'              => 'Pending',
            'received'             => 'Received',
            'partially_received'   => 'Partial',
            'discrepancy'          => 'Discrepancy',
            'discrepancy_pending'  => 'Discrepancy — Pending GM Review',
            'gm_approved'          => 'GM Approved — Awaiting Completion',
            'gm_rejected'          => 'GM Rejected — Return for Review',
            'final_rejected'       => 'Final Rejected',
            'rejected'             => 'Rejected',
            'cancelled'            => 'Cancelled',
            default                => ucfirst($s),
        };
    }

    public static function statusBadge(string $s): string
    {
        return match($s) {
            'pending'              => 'warning',
            'received'             => 'success',
            'partially_received'   => 'primary',
            'discrepancy'          => 'danger',
            'discrepancy_pending'  => 'warning',
            'gm_approved'          => 'info',
            'gm_rejected'          => 'danger',
            'final_rejected'       => 'dark',
            'rejected'             => 'dark',
            'cancelled'            => 'secondary',
            default                => 'secondary',
        };
    }

    private function buildFilters(int $warehouseId, array $filters): array
    {
        $where  = ['wr.warehouse_id = :wid'];
        $params = ['wid' => $warehouseId];

        if (!empty($filters['status'])) {
            $where[]          = 'wr.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[]      = "(wr.receiving_number LIKE :q OR fd.dispatch_number LIKE :q2 OR f.name LIKE :q3)";
            $params['q']  = '%' . $filters['search'] . '%';
            $params['q2'] = '%' . $filters['search'] . '%';
            $params['q3'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['date_from'])) {
            $where[]           = 'wr.received_date >= :dfrom';
            $params['dfrom']   = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]         = 'wr.received_date <= :dto';
            $params['dto']   = $filters['date_to'];
        }
        if (!empty($filters['farm_id'])) {
            $where[]          = 'wr.farm_id = :fid';
            $params['fid']    = (int)$filters['farm_id'];
        }

        return [implode(' AND ', $where), $params];
    }
}
