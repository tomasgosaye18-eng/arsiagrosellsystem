<?php
/**
 * =========================================================
 * PRICE CONTROLLER
 * =========================================================
 * File    : modules/admin/controllers/PriceController.php
 * Purpose : Single price update, bulk price adjustment,
 *           price history page, and recent-changes AJAX.
 * Deps    : BaseController, PriceService,
 *           ProductPriceHistoryModel, ProductModel
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Admin\Controllers;

use AAM\Core\BaseController;
use AAM\Admin\Models\ProductModel;
use AAM\Admin\Models\ProductPriceHistoryModel;
use AAM\Admin\Services\PriceService;

class PriceController extends BaseController
{
    private ProductModel $productModel;
    private ProductPriceHistoryModel $priceHistoryModel;
    private PriceService $priceService;

    public function __construct()
    {
        parent::__construct();
        $this->productModel      = new ProductModel();
        $this->priceHistoryModel = new ProductPriceHistoryModel();
        $this->priceService      = new PriceService();
    }

    /**
     * Price history full page
     * GET /admin/products/{id}/price-history
     * Admin can VIEW price history. To UPDATE prices, use /gm/products/{id}/pricing (GM only).
     */
    public function history(int $id): void
    {
        $this->requireRole(ROLE_ADMIN, ROLE_GENERAL_MANAGER);

        $product = $this->productModel->getByIdWithRelations($id);
        if (!$product) {
            $this->redirectWith(url('admin/products'), FLASH_ERROR, 'Product not found');
            return;
        }

        $type    = $this->query('type') ?: null;
        $history = $this->priceHistoryModel->getProductPriceHistory($id, $type, 100);
        $stats   = $this->priceHistoryModel->getPriceStatistics($id);

        $this->render('admin/products/price-history', [
            'title'   => 'Price History – ' . $product['product_name'],
            'product' => $product,
            'history' => $history,
            'stats'   => $stats,
            'type'    => $type,
        ]);
    }

    /**
     * Update price for one product (AJAX POST)
     * POST /admin/products/{id}/price-update
     *
     * NOTE: This route has been removed from the route file.
     * Selling price updates are now handled exclusively by
     * GMPricingController at POST /gm/products/{id}/pricing.
     * This method is kept only for backward compatibility
     * and is restricted to ROLE_GENERAL_MANAGER only.
     */
    public function update(int $id): void
    {
        // Selling price management is exclusively the General Manager's responsibility.
        // System Administrator must NOT change selling prices.
        $this->requireRole(ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $result = $this->priceService->updatePrice(
            $id,
            $this->input('price_type', ''),
            (float)$this->input('new_price', 0),
            $this->input('effective_date', date('Y-m-d')),
            $this->userId(),
            trim($this->input('reason', '')) ?: null
        );

        if ($result['success']) {
            $this->jsonSuccess(null, $result['message'] ?? 'Price updated');
        } else {
            $this->jsonError(
                $result['errors']['general'] ?? 'Validation failed',
                HTTP_UNPROCESSABLE_ENTITY,
                $result['errors']
            );
        }
    }

    /**
     * Bulk price adjustment across multiple products
     * POST /admin/prices/bulk-update
     */
    public function bulkUpdate(): void
    {
        $this->requireRole(ROLE_ADMIN, ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $productIds   = $this->input('product_ids', []);
        $priceType    = $this->input('price_type', 'SELLING');
        $adjustType   = $this->input('adjust_type', 'PERCENTAGE');
        $adjustValue  = (float)$this->input('adjust_value', 0);
        $effectiveDate = $this->input('effective_date', date('Y-m-d'));
        $reason       = trim($this->input('reason', 'Bulk price update')) ?: 'Bulk price update';

        if (empty($productIds) || !is_array($productIds)) {
            $this->jsonError('No products selected', HTTP_UNPROCESSABLE_ENTITY);
            return;
        }

        $result = $this->priceService->bulkAdjust(
            $productIds, $priceType, $adjustType,
            $adjustValue, $effectiveDate, $this->userId(), $reason
        );

        $this->jsonSuccess(
            ['updated' => $result['updated'], 'failed' => $result['failed']],
            "Updated {$result['updated']} product(s)" .
                ($result['failed'] ? ", {$result['failed']} failed" : '')
        );
    }

    /**
     * Recent price changes (dashboard widget AJAX)
     * GET /admin/prices/recent
     */
    public function recent(): void
    {
        $limit   = min(20, (int)$this->query('limit', 10));
        $changes = $this->priceHistoryModel->getRecentPriceChanges($limit);
        $this->json(['success' => true, 'changes' => $changes]);
    }
}
