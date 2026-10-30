<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 10
 * =========================================================
 * File    : modules/warehouse/models/StockMovementModel.php
 * Purpose : Data access for stock_movement_log.
 *           Append-only ledger — INSERT only, never updated/deleted.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Warehouse\Models;

use AAM\Core\BaseModel;

class StockMovementModel extends BaseModel
{
    protected string $table      = 'stock_movement_log';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = false;

    // Movement type constants
    public const TYPE_RECEIVING         = 'receiving';
    public const TYPE_TRANSFER_OUT      = 'transfer_out';
    public const TYPE_TRANSFER_IN       = 'transfer_in';
    public const TYPE_ADJUSTMENT        = 'adjustment';
    public const TYPE_DISCREPANCY       = 'discrepancy';
    public const TYPE_RETURN            = 'return';
    public const TYPE_INITIAL           = 'initial';
    public const TYPE_REVERSAL          = 'reversal';  // stock restored on transfer/dispatch rejection
    public const TYPE_SEED_DISTRIBUTION = 'seed_distribution'; // seed distributed to external farmers

    /**
     * Log a stock movement (INSERT only).
     */
    public function log(array $data): int
    {
        $this->rawExecute(
            "INSERT INTO stock_movement_log
                (movement_type, location_type, location_id, product_id,
                 batch_number, quantity_before, quantity_in, quantity_out,
                 quantity_after, reference_type, reference_id, reference_number,
                 performed_by, notes, moved_at)
             VALUES
                (:type, :ltype, :lid, :pid,
                 :bn, :before, :in, :out,
                 :after, :rtype, :rid, :rnum,
                 :by, :notes, NOW())",
            [
                'type'   => $data['movement_type'],
                'ltype'  => $data['location_type'],
                'lid'    => (int)$data['location_id'],
                'pid'    => (int)$data['product_id'],
                'bn'     => $data['batch_number']    ?? null,
                'before' => (float)($data['quantity_before'] ?? 0),
                'in'     => (float)($data['quantity_in']     ?? 0),
                'out'    => (float)($data['quantity_out']    ?? 0),
                'after'  => (float)($data['quantity_after']  ?? 0),
                'rtype'  => $data['reference_type']  ?? null,
                'rid'    => isset($data['reference_id']) ? (int)$data['reference_id'] : null,
                'rnum'   => $data['reference_number'] ?? null,
                'by'     => isset($data['performed_by']) ? (int)$data['performed_by'] : null,
                'notes'  => $data['notes'] ?? null,
            ]
        );
        return $this->db->lastInsertId();
    }

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
        $locationId   = (int)($conditions['location_id']   ?? 1);
        $locationType = (string)($conditions['location_type'] ?? 'warehouse');
        return $this->paginateByLocation($locationId, $locationType, $page, $perPage);
    }

    /**
     * Paginated stock movement history.
     */
    public function paginateByLocation(
        int   $locationId,
        string $locationType = 'warehouse',
        int   $page    = 1,
        int   $perPage = PAGINATION_DEFAULT,
        array $filters = []
    ): array {
        $page    = max(1, $page);
        $perPage = min($perPage, PAGINATION_MAX);
        $offset  = ($page - 1) * $perPage;

        $where  = ['sml.location_type = :ltype', 'sml.location_id = :lid'];
        $params = ['ltype' => $locationType, 'lid' => $locationId];

        if (!empty($filters['product_id'])) {
            $where[]           = 'sml.product_id = :pid';
            $params['pid']     = (int)$filters['product_id'];
        }
        if (!empty($filters['movement_type'])) {
            $where[]           = 'sml.movement_type = :mtype';
            $params['mtype']   = $filters['movement_type'];
        }
        if (!empty($filters['date_from'])) {
            $where[]           = 'DATE(sml.moved_at) >= :dfrom';
            $params['dfrom']   = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]           = 'DATE(sml.moved_at) <= :dto';
            $params['dto']     = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);

        $total = (int)($this->rawQueryOne(
            "SELECT COUNT(*) AS c FROM stock_movement_log sml WHERE {$whereClause}", $params
        )['c'] ?? 0);

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $data = $this->rawQuery(
            "SELECT
                sml.*,
                p.name         AS product_name,
                p.sku          AS product_sku,
                CONCAT(u.first_name,' ',u.last_name) AS performed_by_name
             FROM stock_movement_log sml
             LEFT JOIN products p ON p.id  = sml.product_id
             LEFT JOIN users    u ON u.id  = sml.performed_by
             WHERE {$whereClause}
             ORDER BY sml.moved_at DESC
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

    public static function typeLabel(string $t): string
    {
        return match($t) {
            'receiving'         => 'Receiving',
            'transfer_out'      => 'Transfer Out',
            'transfer_in'       => 'Transfer In',
            'adjustment'        => 'Adjustment',
            'discrepancy'       => 'Discrepancy',
            'return'            => 'Return',
            'initial'           => 'Initial',
            'reversal'          => 'Stock Reversal',
            'seed_distribution' => 'Seed Distribution',
            default             => ucfirst(str_replace('_', ' ', $t)),
        };
    }

    public static function typeBadge(string $t): string
    {
        return match($t) {
            'receiving'         => 'success',
            'transfer_out'      => 'primary',
            'transfer_in'       => 'info',
            'adjustment'        => 'warning',
            'discrepancy'       => 'danger',
            'return'            => 'secondary',
            'initial'           => 'secondary',
            'reversal'          => 'warning',
            'seed_distribution' => 'success',
            default             => 'secondary',
        };
    }
}
