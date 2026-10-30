<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/general_manager/controllers/GMPricingController.php
 * Purpose : General Manager — Selling Price Management.
 *           The General Manager is the ONLY role authorised
 *           to set and update official selling prices.
 *           System Administrator, Farm Manager, Warehouse
 *           Manager, and Salesperson must NOT change prices.
 *
 * Routes  :
 *   GET  /gm/products/{id}/pricing   → pricing()
 *   POST /gm/products/{id}/pricing   → updatePrice()
 *
 * Access  : ROLE_GENERAL_MANAGER only
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\GeneralManager\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;
use AAM\Admin\Models\ProductPriceHistoryModel;

class GMPricingController extends BaseController
{
    private Database $db;
    private ProductPriceHistoryModel $priceHistory;

    public function __construct()
    {
        parent::__construct();
        $this->db           = Database::getInstance();
        $this->priceHistory = new ProductPriceHistoryModel();
    }

    // =========================================================
    // GET /gm/products/{id}/pricing
    // View current price and price history for a product.
    // =========================================================
    public function pricing(int $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $product = $this->loadProduct($id);
        if (!$product) {
            $this->flash(FLASH_ERROR, 'Product not found.');
            $this->redirect('/gm/products');
            return;
        }

        $history = $this->getPriceHistory($id);
        $stats   = $this->priceHistory->getPriceStatistics($id);

        $this->render('general_manager.pricing.index', [
            'pageTitle' => 'Product Pricing — ' . e($product['product_name']),
            'product'   => $product,
            'history'   => $history,
            'stats'     => $stats,
        ]);
    }

    // =========================================================
    // POST /gm/products/{id}/pricing
    // Update the official selling price of a product.
    // ONLY General Manager can call this endpoint.
    // The price is saved to products.unit_price (the real
    // database column) and recorded in product_price_history.
    // =========================================================
    public function updatePrice(int $id): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER); // Hard enforced — no other role allowed
        $this->verifyCsrf();

        $product = $this->loadProduct($id);
        if (!$product) {
            $this->jsonError('Product not found.', HTTP_NOT_FOUND);
            return;
        }

        // Validate input
        $newPriceRaw = $this->input('new_price', '');
        $reason      = trim($this->input('reason', ''));

        if ($newPriceRaw === '' || !is_numeric($newPriceRaw)) {
            $this->jsonError('Please enter a valid selling price.', HTTP_UNPROCESSABLE_ENTITY, [
                'new_price' => 'A numeric selling price is required.',
            ]);
            return;
        }

        $newPrice = round((float)$newPriceRaw, 2);

        if ($newPrice <= 0) {
            $this->jsonError('Selling price must be greater than zero. A price of 0 would block this product from POS sales.', HTTP_UNPROCESSABLE_ENTITY, [
                'new_price' => 'Selling price must be greater than zero.',
            ]);
            return;
        }

        $oldPrice = (float)($product['unit_price'] ?? 0);

        // Only reject "same price" when the old price was actually set (> 0).
        // If old price is 0 (never set), always allow setting a new price.
        if ($oldPrice > 0 && abs($newPrice - $oldPrice) < 0.001) {
            $this->jsonError('The new price is the same as the current price. No change recorded.', HTTP_UNPROCESSABLE_ENTITY, [
                'new_price' => 'New price must be different from the current price.',
            ]);
            return;
        }

        // Atomically update price and record history
        try {
            $this->db->transaction(function () use ($id, $newPrice, $oldPrice, $reason, $product) {
                // 1. Update the official selling price in the products table.
                //    unit_price IS the selling price (aliased in views).
                $this->db->execute(
                    "UPDATE products
                     SET unit_price  = :price,
                         updated_by  = :uid,
                         updated_at  = NOW()
                     WHERE id = :pid AND deleted_at IS NULL",
                    [
                        'price' => $newPrice,
                        'uid'   => $this->userId(),
                        'pid'   => $id,
                    ]
                );

                // 2. Record in price history.
                //    Compute percentage change (avoid division by zero).
                $pctChange = null;
                if ($oldPrice > 0) {
                    $pctChange = round((($newPrice - $oldPrice) / $oldPrice) * 100, 2);
                }

                // Try the extended schema first (with price_type, effective_date, etc.)
                // Fall back to the simple schema if columns are missing.
                try {
                    $this->db->execute(
                        "INSERT INTO product_price_history
                             (product_id, price_type, old_price, new_price,
                              effective_date, percentage_change, reason, changed_by, changed_at)
                         VALUES
                             (:pid, 'SELLING', :old, :new,
                              :edate, :pct, :reason, :by, NOW())",
                        [
                            'pid'    => $id,
                            'old'    => $oldPrice,
                            'new'    => $newPrice,
                            'edate'  => date('Y-m-d'),
                            'pct'    => $pctChange,
                            'reason' => $reason !== '' ? substr($reason, 0, 255) : null,
                            'by'     => $this->userId(),
                        ]
                    );
                } catch (\Throwable) {
                    // Fallback: minimal schema (old_price, new_price, change_reason, changed_by)
                    $this->db->execute(
                        "INSERT INTO product_price_history
                             (product_id, old_price, new_price, change_reason, changed_by)
                         VALUES (:pid, :old, :new, :reason, :by)",
                        [
                            'pid'    => $id,
                            'old'    => $oldPrice,
                            'new'    => $newPrice,
                            'reason' => '[SELLING]' . ($reason !== '' ? " {$reason}" : ''),
                            'by'     => $this->userId(),
                        ]
                    );
                }

                // 3. Business audit trail
                try {
                    $auditLog = new \AAM\Admin\Models\ActivityLogModel();
                    $action   = $oldPrice <= 0 ? 'PRICE_SET' : 'PRICE_UPDATED';
                    $verb     = $oldPrice <= 0 ? 'set initial selling price for' : 'updated selling price for';
                    $auditLog->logBusiness([
                        'action'       => $action,
                        'module'       => 'products',
                        'entity_type'  => 'product',
                        'entity_label' => ($product['product_name'] ?? "Product #{$id}"),
                        'record_id'    => $id,
                        'description'  => sprintf(
                            "GM %s \"%s\": %s → ETB %.2f%s",
                            $verb,
                            $product['product_name'] ?? "Product #{$id}",
                            $oldPrice > 0 ? 'ETB ' . number_format($oldPrice, 2) : '(not set)',
                            $newPrice,
                            $reason !== '' ? ". Reason: {$reason}" : ''
                        ),
                        'old_values'   => ['unit_price' => $oldPrice],
                        'new_values'   => ['unit_price' => $newPrice],
                    ]);
                } catch (\Throwable) {
                    // Non-critical — never fail a price update over audit logging
                }
            });

            $this->jsonSuccess(
                [
                    'product_id'   => $id,
                    'new_price'    => $newPrice,
                    'old_price'    => $oldPrice,
                    'product_name' => $product['product_name'] ?? '',
                ],
                sprintf(
                    'Selling price %s to ETB %s. Product is now available for POS sales.',
                    $oldPrice <= 0 ? 'set' : 'updated',
                    number_format($newPrice, 2)
                )
            );

        } catch (\Throwable $e) {
            \AAM\Core\Logger::error('GMPricingController: price update failed', [
                'product_id' => $id,
                'user_id'    => $this->userId(),
                'error'      => $e->getMessage(),
            ]);
            $this->jsonError(
                'Price update failed. Please try again.',
                HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    // =========================================================
    // Private helpers
    // =========================================================

    /**
     * Load a single active product with category and unit info.
     * Returns the real DB columns plus display aliases.
     */
    private function loadProduct(int $productId): ?array
    {
        try {
            return $this->db->fetchOne(
                "SELECT
                    p.id             AS product_id,
                    p.id,
                    p.name           AS product_name,
                    p.sku,
                    p.barcode,
                    p.unit_price,
                    p.unit_price     AS selling_price,
                    p.cost_price,
                    p.product_status,
                    p.is_active,
                    p.updated_at,
                    c.name           AS category_name,
                    u.name           AS unit_name,
                    u.abbreviation   AS unit_abbr
                 FROM products p
                 LEFT JOIN categories c       ON c.id = p.category_id
                 LEFT JOIN units_of_measure u ON u.id = p.unit_id
                 WHERE p.id = :pid AND p.deleted_at IS NULL",
                ['pid' => $productId]
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Load price history for a product, newest first.
     * Handles both the simple and extended schema gracefully.
     */
    private function getPriceHistory(int $productId): array
    {
        try {
            // Try extended schema first
            return $this->db->fetchAll(
                "SELECT
                    ph.id,
                    ph.old_price,
                    ph.new_price,
                    COALESCE(ph.price_type, 'SELLING')       AS price_type,
                    ph.effective_date,
                    ph.price_difference,
                    ph.percentage_change,
                    COALESCE(ph.reason, ph.change_reason, '') AS reason,
                    ph.changed_at,
                    CONCAT(u.first_name, ' ', u.last_name)   AS changed_by_name,
                    u.username                                AS changed_by_username
                 FROM product_price_history ph
                 LEFT JOIN users u ON u.id = ph.changed_by
                 WHERE ph.product_id = :pid
                 ORDER BY ph.changed_at DESC
                 LIMIT 50",
                ['pid' => $productId]
            );
        } catch (\Throwable) {
            // Fallback to minimal schema
            try {
                return $this->db->fetchAll(
                    "SELECT
                        ph.id,
                        ph.old_price,
                        ph.new_price,
                        'SELLING'                                    AS price_type,
                        NULL                                         AS effective_date,
                        (ph.new_price - ph.old_price)                AS price_difference,
                        NULL                                         AS percentage_change,
                        COALESCE(ph.change_reason, '')               AS reason,
                        ph.changed_at,
                        CONCAT(u.first_name, ' ', u.last_name)       AS changed_by_name,
                        u.username                                    AS changed_by_username
                     FROM product_price_history ph
                     LEFT JOIN users u ON u.id = ph.changed_by
                     WHERE ph.product_id = :pid
                     ORDER BY ph.changed_at DESC
                     LIMIT 50",
                    ['pid' => $productId]
                );
            } catch (\Throwable) {
                return [];
            }
        }
    }
}
