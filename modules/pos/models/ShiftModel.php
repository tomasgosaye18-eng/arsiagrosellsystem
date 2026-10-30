<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 12
 * =========================================================
 * File    : modules/pos/models/ShiftModel.php
 * Purpose : Data access for seller_shifts table.
 *           Open/close shifts, query active shift,
 *           shift summary for end-of-shift report.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\POS\Models;

use AAM\Core\BaseModel;

class ShiftModel extends BaseModel
{
    protected string $table      = 'seller_shifts';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = false;

    protected array $fillable = [
        'shift_number','shop_id','seller_id','status',
        'opened_at','closed_at','opening_notes','closing_notes',
        'total_transactions','total_sales_amount','cash_collected',
    ];

    // ── Number generator ──────────────────────────────────
    public function nextShiftNumber(): string
    {
        $year  = date('Y');
        $count = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM seller_shifts WHERE YEAR(opened_at)=:y",
            ['y' => $year]
        );
        return 'SHF-' . $year . '-' . str_pad((string)($count + 1), 5, '0', STR_PAD_LEFT);
    }

    // ── Active shift for a seller ──────────────────────────
    /**
     * @return array<string,mixed>|null
     */
    public function getActiveShift(int $sellerId, int $shopId): ?array
    {
        return $this->db->fetchOne(
            "SELECT ss.*,
                    sh.name AS shop_name,
                    u.first_name AS seller_first_name,
                    u.last_name  AS seller_last_name
             FROM seller_shifts ss
             JOIN shops sh ON sh.id = ss.shop_id
             JOIN users  u  ON u.id  = ss.seller_id
             WHERE ss.seller_id=:uid AND ss.shop_id=:sid AND ss.status='open'
             ORDER BY ss.opened_at DESC
             LIMIT 1",
            ['uid' => $sellerId, 'sid' => $shopId]
        );
    }

    // ── Shift with sales summary ───────────────────────────
    /**
     * @return array<string,mixed>|null
     */
    public function getWithSummary(int $shiftId): ?array
    {
        $shift = $this->db->fetchOne(
            "SELECT ss.*,
                    sh.name AS shop_name,
                    sh.location AS shop_location,
                    u.first_name AS seller_first_name,
                    u.last_name  AS seller_last_name
             FROM seller_shifts ss
             JOIN shops sh ON sh.id = ss.shop_id
             JOIN users  u  ON u.id  = ss.seller_id
             WHERE ss.id=:id",
            ['id' => $shiftId]
        );

        if (!$shift) return null;

        // Attach sales in this shift
        $shift['sales'] = $this->db->fetchAll(
            "SELECT id, sale_number, receipt_number, total_amount, status, completed_at
             FROM sales WHERE shift_id=:sid ORDER BY completed_at DESC",
            ['sid' => $shiftId]
        );

        return $shift;
    }

    // ── Update totals when a sale is added ─────────────────
    public function incrementTotals(int $shiftId, float $amount, int $items = 1): void
    {
        $this->db->execute(
            "UPDATE seller_shifts
             SET total_transactions  = total_transactions + :items,
                 total_sales_amount  = total_sales_amount + :amount,
                 cash_collected      = cash_collected     + :amount2
             WHERE id=:id",
            ['items' => $items, 'amount' => $amount, 'amount2' => $amount, 'id' => $shiftId]
        );
    }

    // ── Paginate shifts for a seller ───────────────────────
    /**
     * @return array{data:array,total:int,page:int,perPage:int,totalPages:int}
     */
    public function paginateBySeller(
        int $sellerId,
        int $shopId,
        int $page    = 1,
        int $perPage = PAGINATION_DEFAULT
    ): array {
        $page   = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $total = (int)($this->db->fetchColumn(
            "SELECT COUNT(*) FROM seller_shifts WHERE seller_id=:uid AND shop_id=:sid",
            ['uid' => $sellerId, 'sid' => $shopId]
        ) ?? 0);

        $data = $this->db->fetchAll(
            "SELECT * FROM seller_shifts
             WHERE seller_id=:uid AND shop_id=:sid
             ORDER BY opened_at DESC
             LIMIT :lim OFFSET :off",
            ['uid' => $sellerId, 'sid' => $shopId, 'lim' => $perPage, 'off' => $offset]
        );

        return [
            'data'       => $data,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int)ceil($total / max(1, $perPage)),
        ];
    }

    // ── Status helpers ────────────────────────────────────
    public static function statusBadge(string $status): string
    {
        return match($status) {
            'open'      => 'success',
            'closed'    => 'secondary',
            'abandoned' => 'warning',
            default     => 'secondary',
        };
    }
}
