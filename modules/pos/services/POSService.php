<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 12
 * =========================================================
 * File    : modules/pos/services/POSService.php
 * Purpose : Atomic POS transaction service.
 *           Handles:
 *             - Stock validation per shop
 *             - Sale + sale_items creation
 *             - shop_inventory decrease
 *             - stock_movement_log entry
 *             - audit_log entry
 *             - shift totals update
 *           ALL inside a single DB transaction.
 *           CASH SALES ONLY. No credit, no deductions.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\POS\Services;

use AAM\Core\Database;
use AAM\Core\SessionManager;
use AAM\POS\Models\SalesModel;
use AAM\POS\Models\ShiftModel;
use AAM\Admin\Models\ActivityLogModel;

class POSService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // =========================================================
    // processSale()
    // Called by POSController::sale() after CSRF + auth checks.
    //
    // $shopId   — server-side resolved (never from form input directly)
    // $sellerId — from session
    // $shiftId  — nullable (shift not strictly required)
    // $items    — [['product_id' => int, 'quantity' => float, 'unit_price' => float], ...]
    // $cashReceived — amount given by customer
    //
    // Returns: sale ID on success
    // Throws:  \RuntimeException with user-friendly message on failure
    // =========================================================
    public function processSale(
        int   $shopId,
        int   $sellerId,
        ?int  $shiftId,
        array $items,
        float $cashReceived,
        string $notes = ''
    ): int {
        if (empty($items)) {
            throw new \RuntimeException('No items in cart. Please add products before completing the sale.');
        }

        // ── Step 1: Load and validate every item stock (before any writes) ──
        //
        // SECURITY: The unit_price submitted by the browser (cart_items JSON)
        // is intentionally IGNORED. The authoritative selling price is ALWAYS
        // fetched directly from products.unit_price in the database.
        // This prevents a salesperson from manipulating the price via browser
        // tools, modified requests, or direct POST tampering.
        //
        $validatedItems = [];
        foreach ($items as $item) {
            $productId = (int)($item['product_id'] ?? 0);
            $quantity  = (float)($item['quantity']   ?? 0);
            // NOTE: $item['unit_price'] from the browser is deliberately NOT used.

            if ($productId <= 0 || $quantity <= 0) {
                throw new \RuntimeException('Invalid item data in cart. Please refresh and try again.');
            }

            // Fetch product info, CURRENT shop stock, and AUTHORITATIVE price from DB.
            // unit_price is the official selling price set by the General Manager.
            $row = $this->db->fetchOne(
                "SELECT
                    p.id, p.name, p.sku, p.unit_id,
                    p.unit_price                                    AS official_price,
                    COALESCE(si.available_quantity, 0)              AS available_qty,
                    si.expiry_date
                 FROM products p
                 LEFT JOIN shop_inventory si
                   ON si.product_id = p.id AND si.shop_id = :sid
                 WHERE p.id = :pid AND p.is_active = 1",
                ['sid' => $shopId, 'pid' => $productId]
            );

            if (!$row) {
                throw new \RuntimeException("Product ID {$productId} not found or is inactive.");
            }

            // ── EXPIRY GATE (Food Safety / Public Health) ─────────────────────
            // An expired product MUST NOT be sold regardless of available stock.
            // This check cannot be bypassed — it is enforced server-side on every
            // sale attempt, even if the POS UI was somehow manipulated.
            if (!empty($row['expiry_date']) && strtotime($row['expiry_date']) < time()) {
                throw new \RuntimeException(
                    "Sale blocked: \"{$row['name']}\" has expired (expiry: " .
                    date('d/m/Y', strtotime($row['expiry_date'])) .
                    "). Expired products cannot be sold."
                );
            }

            // Always use the price from the database — never trust the browser.
            $officialPrice = ($row['official_price'] !== null) ? (float)$row['official_price'] : null;

            // ── SELLING PRICE GATE (Business Rule) ───────────────────────────
            // A product MUST have a valid selling price (> 0) set by the
            // General Manager before it can be sold through POS.
            // NULL or zero means the GM has not approved a price yet.
            // This check is performed server-side and cannot be bypassed.
            if ($officialPrice === null || $officialPrice <= 0) {
                throw new \RuntimeException(
                    "Sale blocked: \"{$row['name']}\" does not have an approved selling price. " .
                    "Please contact the General Manager."
                );
            }

            $available = (float)$row['available_qty'];
            if ($available < $quantity) {
                throw new \RuntimeException(
                    "Insufficient stock for \"{$row['name']}\". " .
                    "Available: {$available}, Requested: {$quantity}."
                );
            }

            $validatedItems[] = [
                'product_id'   => $productId,
                'unit_id'      => $row['unit_id'],
                'product_name' => $row['name'],
                'product_sku'  => $row['sku'],
                'quantity'     => $quantity,
                'unit_price'   => $officialPrice,  // authoritative price from DB
                'subtotal'     => round($quantity * $officialPrice, 2),
                'available'    => $available,
            ];
        }

        // ── Step 2: Calculate totals ───────────────────────────────────────
        $subtotal     = array_sum(array_column($validatedItems, 'subtotal'));
        $totalAmount  = round($subtotal, 2);
        $changeGiven  = round($cashReceived - $totalAmount, 2);

        if ($cashReceived < $totalAmount) {
            throw new \RuntimeException(
                "Cash received (ETB " . number_format($cashReceived, 2) . ") " .
                "is less than total (ETB " . number_format($totalAmount, 2) . ")."
            );
        }

        // ── Step 3: Execute atomically ─────────────────────────────────────
        $salesModel = new SalesModel();
        $shiftModel = new ShiftModel();
        $saleId     = 0;

        $this->db->transaction(function() use (
            $shopId, $sellerId, $shiftId, $validatedItems,
            $subtotal, $totalAmount, $cashReceived, $changeGiven,
            $notes, $salesModel, $shiftModel, &$saleId
        ) {
            $saleNumber    = $salesModel->nextSaleNumber();
            $receiptNumber = $salesModel->nextReceiptNumber();
            $today         = date('Y-m-d');

            // 3a. Insert sale header
            $saleId = $salesModel->create([
                'sale_number'     => $saleNumber,
                'receipt_number'  => $receiptNumber,
                'shop_id'         => $shopId,
                'seller_id'       => $sellerId,
                'shift_id'        => $shiftId,
                'status'          => 'completed',
                'payment_method'  => 'cash',
                'subtotal'        => $subtotal,
                'discount_amount' => 0.00,
                'total_amount'    => $totalAmount,
                'cash_received'   => $cashReceived,
                'change_given'    => $changeGiven,
                'total_items'     => count($validatedItems),
                'notes'           => substr($notes, 0, 500),
                'sale_date'       => $today,
                'completed_at'    => date('Y-m-d H:i:s'),
            ]);

            foreach ($validatedItems as $item) {
                // 3b. Insert sale line item
                $this->db->execute(
                    "INSERT INTO sale_items
                        (sale_id, product_id, unit_id, product_name, product_sku, quantity, unit_price, subtotal)
                     VALUES
                        (:sale_id,:pid,:uid,:pname,:psku,:qty,:price,:sub)",
                    [
                        'sale_id' => $saleId,
                        'pid'     => $item['product_id'],
                        'uid'     => $item['unit_id'],
                        'pname'   => $item['product_name'],
                        'psku'    => $item['product_sku'],
                        'qty'     => $item['quantity'],
                        'price'   => $item['unit_price'],
                        'sub'     => $item['subtotal'],
                    ]
                );

                // 3c. Decrease shop_inventory
                $this->db->execute(
                    "UPDATE shop_inventory
                     SET current_quantity   = current_quantity   - :qty,
                         available_quantity = available_quantity - :qty2,
                         last_movement_at   = NOW(),
                         last_movement_type = 'sale'
                     WHERE shop_id=:sid AND product_id=:pid",
                    [
                        'qty'  => $item['quantity'],
                        'qty2' => $item['quantity'],
                        'sid'  => $shopId,
                        'pid'  => $item['product_id'],
                    ]
                );

                // 3d. Append to stock_movement_log (immutable ledger)
                $before = $item['available'];
                $after  = $before - $item['quantity'];

                $this->db->execute(
                    "INSERT INTO stock_movement_log
                        (movement_type, location_type, location_id, product_id,
                         quantity_before, quantity_in, quantity_out, quantity_after,
                         reference_type, reference_id, reference_number,
                         performed_by, notes, moved_at)
                     VALUES
                        ('sale','shop',:loc,:pid,
                         :before, :qty_in, :out, :after,
                         'sales',:ref_id,:ref_num,
                         :user, :notes, NOW())",
                    [
                        'loc'     => $shopId,
                        'pid'     => $item['product_id'],
                        'before'  => $before,
                        'qty_in'  => 0,
                        'out'     => $item['quantity'],
                        'after'   => $after,
                        'ref_id'  => $saleId,
                        'ref_num' => $saleNumber,
                        'user'    => $sellerId,
                        'notes'   => "Sale: {$item['product_name']}",
                    ]
                );
            }

            // 3e. Update shift totals
            if ($shiftId) {
                $shiftModel->incrementTotals($shiftId, $totalAmount);
            }

            // 3f. Business audit trail
            $auditLog = new ActivityLogModel();
            $auditLog->logBusiness([
                'action'       => ActivityLogModel::ACTION_SALE_CREATED,
                'module'       => 'shop',
                'entity_type'  => 'sale',
                'entity_label' => "Receipt #{$receiptNumber} — ETB " . number_format($totalAmount, 2),
                'record_id'    => $saleId,
                'description'  => "Cash sale completed: " . count($validatedItems) . " item(s), " .
                                  "Total ETB " . number_format($totalAmount, 2) . ", " .
                                  "Cash ETB " . number_format($cashReceived, 2) . ", " .
                                  "Change ETB " . number_format($changeGiven, 2),
                'shop_id'      => $shopId,
                'new_values'   => [
                    'sale_number'    => $saleNumber,
                    'receipt_number' => $receiptNumber,
                    'total_amount'   => $totalAmount,
                    'cash_received'  => $cashReceived,
                    'change_given'   => $changeGiven,
                    'items_count'    => count($validatedItems),
                ],
            ]);

            // 3g. Check for low-stock / out-of-stock after sale and notify
            // warehouse managers for this shop
            $this->triggerLowStockNotifications($shopId, $validatedItems, $saleId);
        });

        return $saleId;
    }

    // =========================================================
    // voidSale()
    // Only authorized roles can void (admin/GM).
    // Reverses the stock decrease and marks sale as voided.
    // =========================================================
    public function voidSale(int $saleId, int $voidedBy, string $reason): void
    {
        if (empty(trim($reason))) {
            throw new \RuntimeException('A reason is required to void a sale.');
        }

        $sale = $this->db->fetchOne(
            "SELECT * FROM sales WHERE id=:id", ['id' => $saleId]
        );

        if (!$sale) {
            throw new \RuntimeException('Sale not found.');
        }
        if ($sale['status'] !== 'completed') {
            throw new \RuntimeException('Only completed sales can be voided.');
        }

        $items = $this->db->fetchAll(
            "SELECT * FROM sale_items WHERE sale_id=:sid", ['sid' => $saleId]
        );

        $this->db->transaction(function() use ($sale, $items, $saleId, $voidedBy, $reason) {
            // Mark sale voided
            $this->db->execute(
                "UPDATE sales SET status='voided', void_reason=:reason,
                                  voided_by=:by, voided_at=NOW()
                 WHERE id=:id",
                ['reason' => substr($reason, 0, 500), 'by' => $voidedBy, 'id' => $saleId]
            );

            $shopId = (int)$sale['shop_id'];

            foreach ($items as $item) {
                // Capture current available_quantity BEFORE the restore (for accurate log)
                $currentRow = $this->db->fetchOne(
                    "SELECT available_quantity FROM shop_inventory WHERE shop_id=:sid AND product_id=:pid",
                    ['sid' => $shopId, 'pid' => $item['product_id']]
                );
                $qtyBefore = (float)($currentRow['available_quantity'] ?? 0);
                $qtyAfter  = $qtyBefore + (float)$item['quantity'];

                // Restore shop inventory
                $this->db->execute(
                    "UPDATE shop_inventory
                     SET current_quantity   = current_quantity   + :qty,
                         available_quantity = available_quantity + :qty2,
                         last_movement_at   = NOW(),
                         last_movement_type = 'adjustment'
                     WHERE shop_id=:sid AND product_id=:pid",
                    [
                        'qty'  => $item['quantity'],
                        'qty2' => $item['quantity'],
                        'sid'  => $shopId,
                        'pid'  => $item['product_id'],
                    ]
                );

                // Log reversal with accurate before/after values
                $this->db->execute(
                    "INSERT INTO stock_movement_log
                        (movement_type, location_type, location_id, product_id,
                         quantity_before, quantity_in, quantity_out, quantity_after,
                         reference_type, reference_id, reference_number,
                         performed_by, notes, moved_at)
                     VALUES
                        ('adjustment','shop',:loc,:pid,
                         :before, :qty, :qty_out, :after,
                         'sales',:ref_id,:ref_num,
                         :user, :notes, NOW())",
                    [
                        'loc'     => $shopId,
                        'pid'     => $item['product_id'],
                        'before'  => $qtyBefore,
                        'qty'     => $item['quantity'],
                        'qty_out' => 0,
                        'after'   => $qtyAfter,
                        'ref_id'  => $saleId,
                        'ref_num' => $sale['sale_number'],
                        'user'    => $voidedBy,
                        'notes'   => "VOID: {$sale['sale_number']} — {$item['product_name']}",
                    ]
                );
            }

            // Update shift totals (reverse)
            if (!empty($sale['shift_id'])) {
                $this->db->execute(
                    "UPDATE seller_shifts
                     SET total_sales_amount  = total_sales_amount  - :amt,
                         cash_collected      = cash_collected      - :amt2
                     WHERE id=:id",
                    [
                        'amt'  => (float)$sale['total_amount'],
                        'amt2' => (float)$sale['total_amount'],
                        'id'   => (int)$sale['shift_id'],
                    ]
                );
            }

            // Audit trail
            $auditLog = new ActivityLogModel();
            $auditLog->logBusiness([
                'action'       => ActivityLogModel::ACTION_SALE_VOIDED,
                'module'       => 'shop',
                'entity_type'  => 'sale',
                'entity_label' => "Sale {$sale['sale_number']} voided",
                'record_id'    => $saleId,
                'description'  => "Sale {$sale['sale_number']} voided by user #{$voidedBy}. Reason: {$reason}",
                'shop_id'      => $shopId,
                'old_values'   => ['status' => 'completed', 'total_amount' => $sale['total_amount']],
                'new_values'   => ['status' => 'voided', 'void_reason' => $reason],
            ]);
        });
    }

    // =========================================================
    // getShopProductsForPOS()
    // Returns active products with current shop stock and price.
    // ONLY returns products with available_quantity > 0.
    //
    // Business Rule — Selling Price Gate:
    //   Products are returned even if they have no selling price
    //   set, but they are marked with price_available = false.
    //   The POS terminal renders them as "Not Available for Sale"
    //   so the salesperson can see the product exists but cannot
    //   add it to the cart.  The backend sale() endpoint enforces
    //   the same rule server-side (belt-and-suspenders).
    // =========================================================
    public function getShopProductsForPOS(int $shopId, string $q = '', int $categoryId = 0): array
    {
        $where  = [
            'p.is_active = 1',
            'si.available_quantity > 0',
            'si.shop_id = :sid',
            // Hard block: expired products must never appear in the POS terminal.
            // Selling expired food/agricultural products is a public health risk.
            '(si.expiry_date IS NULL OR si.expiry_date >= CURDATE())',
        ];
        $params = ['sid' => $shopId];

        if ($q !== '') {
            $where[]      = "(p.name LIKE :q OR p.sku LIKE :q2 OR p.barcode LIKE :q3)";
            $params['q']  = "%{$q}%";
            $params['q2'] = "%{$q}%";
            $params['q3'] = "%{$q}%";
        }
        if ($categoryId > 0) {
            $where[]       = 'p.category_id = :cat';
            $params['cat'] = $categoryId;
        }

        $w = implode(' AND ', $where);

        $rows = $this->db->fetchAll(
            "SELECT
                p.id            AS product_id,
                p.name          AS product_name,
                p.sku           AS product_sku,
                p.barcode,
                p.category_id,
                c.name          AS category_name,
                u.abbreviation  AS unit_abbr,
                si.available_quantity,
                p.unit_price    AS current_price
             FROM products p
             JOIN shop_inventory si ON si.product_id = p.id
             LEFT JOIN categories c       ON c.id = p.category_id
             LEFT JOIN units_of_measure u ON u.id = p.unit_id
             WHERE {$w}
             ORDER BY c.name ASC, p.name ASC
             LIMIT 200",
            $params
        );

        // Annotate each row with a price_available flag.
        // price_available = true  → GM has set a valid selling price (> 0)
        // price_available = false → price is NULL or 0 → POS must NOT allow sale
        foreach ($rows as &$row) {
            $price = $row['current_price'];
            $row['price_available'] = ($price !== null && (float)$price > 0);
        }
        unset($row);

        return $rows;
    }

    // =========================================================
    // getLowStockProducts()
    // Returns shop products below min_stock_level.
    // =========================================================
    public function getLowStockProducts(int $shopId): array
    {
        return $this->db->fetchAll(
            "SELECT
                si.product_id,
                p.name          AS product_name,
                p.sku,
                u.abbreviation  AS unit_abbr,
                si.available_quantity,
                si.min_stock_level
             FROM shop_inventory si
             JOIN products p ON p.id = si.product_id
             LEFT JOIN units_of_measure u ON u.id = si.unit_id
             WHERE si.shop_id = :sid
               AND si.min_stock_level > 0
               AND si.available_quantity <= si.min_stock_level
             ORDER BY (si.available_quantity / GREATEST(si.min_stock_level,1)) ASC
             LIMIT 20",
            ['sid' => $shopId]
        );
    }

    // =========================================================
    // triggerLowStockNotifications()
    // Called after a completed sale.
    // Checks each sold product's remaining stock against
    // min_stock_level. Creates a notification for:
    //   - NOTIF_LOW_STOCK   if available <= min_stock_level
    //   - NOTIF_OUT_OF_STOCK if available <= 0
    // Notifies warehouse managers.
    // Does NOT duplicate — skips if a notification for the same
    // product + shop was already sent in the last 2 hours.
    // =========================================================
    private function triggerLowStockNotifications(
        int   $shopId,
        array $soldItems,
        int   $saleId
    ): void {
        // Get the shop name once
        try {
            $shop = $this->db->fetchOne(
                "SELECT name FROM shops WHERE id=:id", ['id' => $shopId]
            );
            $shopName = $shop['name'] ?? "Shop #{$shopId}";
        } catch (\Throwable) {
            return;
        }

        // Find users to notify: warehouse managers assigned to this shop
        // or any warehouse manager for the org
        try {
            $recipients = $this->db->fetchAll(
                "SELECT DISTINCT u.id AS user_id
                 FROM users u
                 JOIN roles r ON r.id = u.role_id
                 WHERE u.is_active = 1
                   AND r.name = :wm
                 LIMIT 20",
                ['wm' => ROLE_WAREHOUSE_MANAGER]
            );
        } catch (\Throwable) {
            return;
        }

        if (empty($recipients)) {
            return;
        }

        foreach ($soldItems as $item) {
            // Check current stock after sale
            try {
                $row = $this->db->fetchOne(
                    "SELECT si.available_quantity, si.min_stock_level,
                            p.name AS product_name
                     FROM shop_inventory si
                     JOIN products p ON p.id = si.product_id
                     WHERE si.shop_id=:sid AND si.product_id=:pid",
                    ['sid' => $shopId, 'pid' => $item['product_id']]
                );
            } catch (\Throwable) {
                continue;
            }

            if (!$row) {
                continue;
            }

            $available    = (float)$row['available_quantity'];
            $minLevel     = (float)($row['min_stock_level'] ?? 0);
            $productName  = $row['product_name'] ?? $item['product_name'];

            // Determine notification type
            $notifType = null;
            $title     = '';
            $message   = '';

            if ($available <= 0) {
                $notifType = NOTIF_OUT_OF_STOCK;
                $title     = "Out of Stock: {$productName}";
                $message   = "{$productName} in {$shopName} is now out of stock. " .
                             "A transfer from warehouse is required.";
            } elseif ($minLevel > 0 && $available <= $minLevel) {
                $notifType = NOTIF_LOW_STOCK;
                $title     = "Low Stock: {$productName}";
                $message   = "{$productName} in {$shopName} is low. " .
                             "Current: {$available}, Minimum: {$minLevel}. " .
                             "Consider transferring more stock.";
            }

            if (!$notifType) {
                continue;
            }

            // Avoid duplicate notifications for same product+shop within 2 hours
            try {
                $existing = $this->db->fetchColumn(
                    "SELECT COUNT(*) FROM notifications
                     WHERE notification_type=:nt
                       AND reference_type='shop_inventory'
                       AND reference_id=:pid
                       AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)",
                    ['nt' => $notifType, 'pid' => $item['product_id']]
                );
                if ((int)$existing > 0) {
                    continue;
                }
            } catch (\Throwable) {
                // If notifications table query fails, skip silently
                continue;
            }

            // Insert notification for each recipient
            foreach ($recipients as $recipient) {
                try {
                    $this->db->execute(
                        "INSERT INTO notifications
                            (recipient_user_id, sender_user_id, notification_type,
                             title, message, reference_type, reference_id, is_read, created_at)
                         VALUES
                            (:uid, NULL, :ntype,
                             :title, :msg, 'shop_inventory', :ref_id, 0, NOW())",
                        [
                            'uid'    => (int)$recipient['user_id'],
                            'ntype'  => $notifType,
                            'title'  => substr($title,   0, 200),
                            'msg'    => substr($message, 0, 1000),
                            'ref_id' => $item['product_id'],
                        ]
                    );
                } catch (\Throwable) {
                    // Non-critical — never break a sale over a notification failure
                }
            }
        }
    }
}
