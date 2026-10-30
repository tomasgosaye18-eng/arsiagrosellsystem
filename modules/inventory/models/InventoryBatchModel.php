<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 11
 * =========================================================
 * File    : modules/inventory/models/InventoryBatchModel.php
 * Purpose : Data access for inventory_batches.
 *           Supports FEFO lookups and batch traceability.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Inventory\Models;

use AAM\Core\BaseModel;

class InventoryBatchModel extends BaseModel
{
    protected string $table      = 'inventory_batches';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = false;

    protected array $fillable = [
        'location_type','location_id','product_id','batch_number',
        'production_date','expiry_date','quantity','unit_id',
        'source_type','source_id','notes',
    ];

    /**
     * Upsert a batch line for a location.
     * Called when receiving or transfer-in adds stock with a batch number.
     */
    public function upsertBatch(
        string  $locationType,
        int     $locationId,
        int     $productId,
        string  $batchNumber,
        float   $quantityDelta,
        ?int    $unitId          = null,
        ?string $productionDate  = null,
        ?string $expiryDate      = null,
        string  $sourceType      = 'receiving',
        ?int    $sourceId        = null
    ): void {
        $existing = $this->rawQueryOne(
            "SELECT id, quantity FROM inventory_batches
             WHERE location_type=:lt AND location_id=:lid
               AND product_id=:pid AND batch_number=:bn",
            ['lt' => $locationType, 'lid' => $locationId, 'pid' => $productId, 'bn' => $batchNumber]
        );

        if ($existing) {
            $newQty = max(0, (float)$existing['quantity'] + $quantityDelta);
            $this->rawExecute(
                "UPDATE inventory_batches
                 SET quantity=:q, updated_at=NOW()
                 WHERE id=:id",
                ['q' => $newQty, 'id' => $existing['id']]
            );
        } else {
            $this->rawExecute(
                "INSERT INTO inventory_batches
                    (location_type,location_id,product_id,batch_number,
                     production_date,expiry_date,quantity,unit_id,
                     source_type,source_id)
                 VALUES (:lt,:lid,:pid,:bn,:pd,:ed,:qty,:uid,:st,:sid)",
                [
                    'lt'  => $locationType,
                    'lid' => $locationId,
                    'pid' => $productId,
                    'bn'  => $batchNumber,
                    'pd'  => $productionDate,
                    'ed'  => $expiryDate,
                    'qty' => max(0, $quantityDelta),
                    'uid' => $unitId,
                    'st'  => $sourceType,
                    'sid' => $sourceId,
                ]
            );
        }
    }

    /**
     * FEFO batch for a product+location (earliest valid expiry first).
     */
    public function getFefoNext(
        string $locationType,
        int    $locationId,
        int    $productId
    ): ?array {
        return $this->rawQueryOne(
            "SELECT * FROM inventory_batches
             WHERE location_type=:lt AND location_id=:lid AND product_id=:pid
               AND quantity>0
               AND (expiry_date IS NULL OR expiry_date >= CURDATE())
             ORDER BY expiry_date ASC, id ASC
             LIMIT 1",
            ['lt' => $locationType, 'lid' => $locationId, 'pid' => $productId]
        );
    }

    /**
     * All batches for a product+location ordered by FEFO.
     */
    public function getBatchesByProduct(
        string $locationType,
        int    $locationId,
        int    $productId
    ): array {
        return $this->rawQuery(
            "SELECT ib.*, u.abbreviation AS unit_abbr
             FROM inventory_batches ib
             LEFT JOIN units_of_measure u ON u.id = ib.unit_id
             WHERE ib.location_type=:lt AND ib.location_id=:lid AND ib.product_id=:pid
               AND ib.quantity > 0
             ORDER BY
               CASE WHEN ib.expiry_date IS NULL THEN 1 ELSE 0 END,
               ib.expiry_date ASC",
            ['lt' => $locationType, 'lid' => $locationId, 'pid' => $productId]
        );
    }

    /**
     * Paginated batch list for a location.
     */
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

        $where  = ['ib.location_type=:lt', 'ib.location_id=:lid'];
        $params = ['lt' => $locationType, 'lid' => $locationId];

        if (!empty($filters['search'])) {
            $where[]     = "(p.name LIKE :q OR ib.batch_number LIKE :q2 OR p.sku LIKE :q3)";
            $params['q']  = '%' . $filters['search'] . '%';
            $params['q2'] = '%' . $filters['search'] . '%';
            $params['q3'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['status'])) {
            switch ($filters['status']) {
                case 'expired':       $where[] = 'ib.expiry_date IS NOT NULL AND ib.expiry_date < CURDATE()'; break;
                case 'expiring_soon': $where[] = 'ib.expiry_date IS NOT NULL AND ib.expiry_date >= CURDATE() AND ib.expiry_date <= DATE_ADD(CURDATE(),INTERVAL 7 DAY)'; break;
                case 'active':        $where[] = '(ib.expiry_date IS NULL OR ib.expiry_date >= CURDATE()) AND ib.quantity > 0'; break;
            }
        }

        $w = implode(' AND ', $where);

        $total = (int)($this->rawQueryOne(
            "SELECT COUNT(*) AS c FROM inventory_batches ib
             JOIN products p ON p.id=ib.product_id
             WHERE {$w}", $params
        )['c'] ?? 0);

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $data = $this->rawQuery(
            "SELECT ib.*,
                    p.name          AS product_name,
                    p.sku           AS product_sku,
                    c.name          AS category_name,
                    u.abbreviation  AS unit_abbr
             FROM inventory_batches ib
             JOIN products p              ON p.id = ib.product_id
             LEFT JOIN categories c       ON c.id = p.category_id
             LEFT JOIN units_of_measure u ON u.id = ib.unit_id
             WHERE {$w}
             ORDER BY
               CASE WHEN ib.expiry_date IS NOT NULL AND ib.expiry_date < CURDATE() THEN 0
                    WHEN ib.expiry_date IS NOT NULL AND ib.expiry_date <= DATE_ADD(CURDATE(),INTERVAL 7 DAY) THEN 1
                    ELSE 2 END ASC,
               ib.expiry_date ASC, p.name ASC
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

    public static function expiryStatus(?string $expiryDate): array
    {
        if (!$expiryDate)                                                  return ['N/A',           'secondary'];
        if (strtotime($expiryDate) < time())                               return ['Expired',       'danger'];
        if (strtotime($expiryDate) <= strtotime('+7 days'))                return ['Expiring Soon', 'warning'];
        return ['Valid', 'success'];
    }
}
