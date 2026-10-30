<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 12
 * =========================================================
 * File    : modules/pos/models/SalesModel.php
 * Purpose : Data access for sales, sale_items.
 *           Handles sale creation, retrieval, voiding,
 *           receipt data, and reporting queries.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\POS\Models;

use AAM\Core\BaseModel;
use AAM\Core\Database;

class SalesModel extends BaseModel
{
    protected string $table      = 'sales';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = false;

    protected array $fillable = [
        'sale_number','receipt_number','shop_id','seller_id','shift_id',
        'status','subtotal','discount_amount','total_amount',
        'cash_received','change_given','total_items','notes',
        'payment_method','payment_provider','payment_reference',
        'payment_status','verified_at',
        'void_reason','voided_by','voided_at','sale_date','completed_at',
    ];

    // ── Number generators ─────────────────────────────────
    /**
     * Both generators use the same COUNT(*) baseline so ordinals stay
     * in sync. Since SAL- and REC- prefixes differ, the UNIQUE
     * constraint on each column prevents collisions independently.
     * Called inside a transaction so COUNT reads are stable.
     */
    public function nextSaleNumber(): string
    {
        $year  = date('Y');
        $count = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM sales WHERE YEAR(completed_at)=:y",
            ['y' => $year]
        );
        return 'SAL-' . $year . '-' . str_pad((string)($count + 1), 6, '0', STR_PAD_LEFT);
    }

    public function nextReceiptNumber(): string
    {
        $year  = date('Y');
        $count = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM sales WHERE YEAR(completed_at)=:y",
            ['y' => $year]
        );
        // Format: REC-YYYY-NNNNNN (e.g. REC-2026-000001)
        return 'REC-' . $year . '-' . str_pad((string)($count + 1), 6, '0', STR_PAD_LEFT);
    }

    // ── Full sale with items ───────────────────────────────
    /**
     * @return array<string,mixed>|null
     */
    public function getWithItems(int $saleId): ?array
    {
        $sale = $this->db->fetchOne(
            "SELECT s.*,
                    sh.name         AS shop_name,
                    sh.location     AS shop_location,
                    u.first_name    AS seller_first_name,
                    u.last_name     AS seller_last_name,
                    u.username      AS seller_username,
                    vu.username     AS voided_by_username
             FROM sales s
             JOIN shops sh ON sh.id = s.shop_id
             JOIN users  u  ON u.id  = s.seller_id
             LEFT JOIN users vu ON vu.id = s.voided_by
             WHERE s.id = :id",
            ['id' => $saleId]
        );

        if (!$sale) return null;

        $sale['items'] = $this->db->fetchAll(
            "SELECT si.*,
                    u.abbreviation AS unit_abbr
             FROM sale_items si
             LEFT JOIN units_of_measure u ON u.id = si.unit_id
             WHERE si.sale_id = :sid
             ORDER BY si.id ASC",
            ['sid' => $saleId]
        );

        return $sale;
    }

    // ── Paginate for shop ──────────────────────────────────
    /**
     * @return array{data:array,total:int,page:int,perPage:int,totalPages:int}
     */
    public function paginateByShop(
        int    $shopId,
        int    $page     = 1,
        int    $perPage  = PAGINATION_DEFAULT,
        array  $filters  = []
    ): array {
        $page   = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $wheres = ['s.shop_id = :shop_id'];
        $params = ['shop_id' => $shopId];

        if (!empty($filters['seller_id'])) {
            $wheres[]           = 's.seller_id = :seller_id';
            $params['seller_id'] = (int)$filters['seller_id'];
        }
        if (!empty($filters['status'])) {
            $wheres[]         = 's.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $wheres[]            = 's.sale_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $wheres[]          = 's.sale_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }
        if (!empty($filters['search'])) {
            $wheres[]     = "(s.sale_number LIKE :q OR s.receipt_number LIKE :q2)";
            $params['q']  = '%' . $filters['search'] . '%';
            $params['q2'] = '%' . $filters['search'] . '%';
        }

        $w     = implode(' AND ', $wheres);
        $total = (int)($this->db->fetchColumn(
            "SELECT COUNT(*) FROM sales s WHERE {$w}", $params
        ) ?? 0);

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $data = $this->db->fetchAll(
            "SELECT s.*,
                    u.first_name AS seller_first_name,
                    u.last_name  AS seller_last_name
             FROM sales s
             JOIN users u ON u.id = s.seller_id
             WHERE {$w}
             ORDER BY s.completed_at DESC
             LIMIT :limit OFFSET :offset",
            $params
        );

        return [
            'data'       => $data,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int)ceil($total / max(1, $perPage)),
        ];
    }

    // ── Paginate for a seller (their own sales only) ───────
    /**
     * @return array{data:array,total:int,page:int,perPage:int,totalPages:int}
     */
    public function paginateBySeller(
        int   $sellerId,
        int   $shopId,
        int   $page    = 1,
        int   $perPage = PAGINATION_DEFAULT,
        array $filters = []
    ): array {
        $filters['seller_id'] = $sellerId;
        return $this->paginateByShop($shopId, $page, $perPage, $filters);
    }

    // ── Daily totals for a shop ────────────────────────────
    public function getDailyTotals(int $shopId, string $date): array
    {
        return $this->db->fetchOne(
            "SELECT
                COUNT(*)                                             AS total_transactions,
                COALESCE(SUM(total_amount),0)                       AS total_sales,
                SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN status='voided'    THEN 1 ELSE 0 END) AS voided
             FROM sales
             WHERE shop_id=:sid AND sale_date=:dt",
            ['sid' => $shopId, 'dt' => $date]
        ) ?? [];
    }

    // ── Today's totals for a specific seller ──────────────
    public function getSellerDayTotals(int $sellerId, int $shopId): array
    {
        return $this->db->fetchOne(
            "SELECT
                COUNT(*)                                             AS total_transactions,
                COALESCE(SUM(total_amount),0)                       AS total_sales,
                COALESCE(SUM(total_items),0)                        AS total_items_sold
             FROM sales
             WHERE seller_id=:uid AND shop_id=:sid
               AND sale_date=CURDATE() AND status='completed'",
            ['uid' => $sellerId, 'sid' => $shopId]
        ) ?? [];
    }

    // ── Sales status badge ────────────────────────────────
    public static function statusBadge(string $status): string
    {
        return match($status) {
            'completed'          => 'success',
            'voided'             => 'danger',
            'returned'           => 'warning',
            'partially_returned' => 'info',
            default              => 'secondary',
        };
    }

    public static function statusLabel(string $status): string
    {
        return match($status) {
            'completed'          => 'Completed',
            'voided'             => 'Voided',
            'returned'           => 'Returned',
            'partially_returned' => 'Partial Return',
            default              => ucfirst($status),
        };
    }
}
