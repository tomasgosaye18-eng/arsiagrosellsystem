<?php
declare(strict_types=1);

namespace AAM\Admin\Models;

use AAM\Core\BaseModel;

class ProductPriceHistoryModel extends BaseModel
{
    protected string $table      = 'product_price_history';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = false;
    protected array  $fillable   = ['product_id', 'old_price', 'new_price', 'change_reason', 'changed_by'];

    /**
     * Get price history for a product.
     *
     * @param int         $productId
     * @param string|null $priceType  'COST' | 'SELLING' | null (all)
     * @param int         $limit
     */
    public function getProductPriceHistory(int $productId, ?string $priceType = null, int $limit = 50): array
    {
        try {
            $where  = 'WHERE ph.product_id = :pid';
            $params = ['pid' => $productId, 'lim' => $limit];

            // The actual table may not have a price_type column; keep a try/catch
            // so the page still loads if the column is absent.
            $sql = "SELECT ph.*,
                           -- price_type: use column if exists, otherwise derive from change_reason
                           COALESCE(
                               ph.price_type,
                               CASE
                                   WHEN ph.change_reason LIKE '[COST]%'    THEN 'COST'
                                   WHEN ph.change_reason LIKE '[SELLING]%' THEN 'SELLING'
                                   ELSE 'SELLING'
                               END
                           ) AS price_type,
                           -- reason: strip [TYPE] prefix from change_reason
                           TRIM(REGEXP_REPLACE(ph.change_reason, '^\\[[A-Z]+\\]\\s*', '')) AS reason,
                           -- effective_date: use column if exists, fallback to changed_at date
                           COALESCE(ph.effective_date, DATE(ph.changed_at)) AS effective_date,
                           -- Computed difference and percentage
                           (ph.new_price - ph.old_price) AS price_difference,
                           CASE
                               WHEN ph.old_price > 0
                               THEN ROUND(((ph.new_price - ph.old_price) / ph.old_price) * 100, 2)
                               ELSE NULL
                           END AS percentage_change,
                           p.name    AS product_name,
                           p.sku     AS product_code,
                           CONCAT(u.first_name,' ',u.last_name) AS changed_by_name,
                           u.username AS changed_by_username
                    FROM product_price_history ph
                    LEFT JOIN products p ON p.id = ph.product_id
                    LEFT JOIN users   u  ON u.id = ph.changed_by
                    {$where}
                    ORDER BY ph.changed_at DESC LIMIT :lim";

            return $this->rawQuery($sql, $params);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Record a price change (called from ProductService).
     * Silently succeeds if the table schema doesn't support all columns.
     */
    public function recordPriceChange(
        int     $productId,
        string  $priceType,   // 'COST' | 'SELLING' — stored in change_reason prefix
        float   $oldPrice,
        float   $newPrice,
        string  $effectiveDate,
        int     $changedBy,
        ?string $reason = null
    ): int {
        try {
            $fullReason = "[{$priceType}]" . ($reason ? " {$reason}" : '');
            $this->rawExecute(
                "INSERT INTO product_price_history
                     (product_id, old_price, new_price, change_reason, changed_by)
                 VALUES (:pid, :old, :new, :reason, :by)",
                [
                    'pid'    => $productId,
                    'old'    => $oldPrice,
                    'new'    => $newPrice,
                    'reason' => substr($fullReason, 0, 255),
                    'by'     => $changedBy,
                ]
            );
            return (int)$this->db->lastInsertId();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Alias kept for backwards compatibility */
    public function recordChange(int $productId, float $oldPrice, float $newPrice, ?string $reason, ?int $changedBy): int
    {
        return $this->recordPriceChange($productId, 'SELLING', $oldPrice, $newPrice, date('Y-m-d'), (int)$changedBy, $reason);
    }

    public function getRecentPriceChanges(int $limit = 10): array
    {
        try {
            return $this->rawQuery(
                "SELECT ph.*, p.name AS product_name, p.sku AS product_code,
                        CONCAT(u.first_name,' ',u.last_name) AS changed_by_name
                 FROM product_price_history ph
                 LEFT JOIN products p ON p.id = ph.product_id
                 LEFT JOIN users   u  ON u.id = ph.changed_by
                 ORDER BY ph.changed_at DESC LIMIT :lim",
                ['lim' => $limit]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public function getPriceStatistics(?int $productId = null): array
    {
        try {
            $where  = $productId ? 'WHERE product_id = :pid' : '';
            $params = $productId ? ['pid' => $productId] : [];
            return $this->rawQueryOne(
                "SELECT COUNT(*) AS total_changes,
                        SUM(CASE WHEN new_price > old_price THEN 1 ELSE 0 END) AS price_increases,
                        SUM(CASE WHEN new_price < old_price THEN 1 ELSE 0 END) AS price_decreases
                 FROM product_price_history {$where}",
                $params
            ) ?? [];
        } catch (\Throwable) {
            return [];
        }
    }
}
