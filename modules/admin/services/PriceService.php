<?php
/**
 * =========================================================
 * PRICE SERVICE
 * =========================================================
 * File    : modules/admin/services/PriceService.php
 * Purpose : Single & bulk price updates with history tracking
 * Deps    : ProductModel, ProductPriceHistoryModel,
 *           ActivityLogModel, ProductValidationService, Database
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Admin\Services;

use AAM\Admin\Models\ProductModel;
use AAM\Admin\Models\ProductPriceHistoryModel;
use AAM\Admin\Models\ActivityLogModel;
use AAM\Core\Database;

class PriceService
{
    private ProductModel $productModel;
    private ProductPriceHistoryModel $priceHistoryModel;
    private ActivityLogModel $activityLog;
    private ProductValidationService $validationService;

    public function __construct()
    {
        $this->productModel      = new ProductModel();
        $this->priceHistoryModel = new ProductPriceHistoryModel();
        $this->activityLog       = new ActivityLogModel();
        $this->validationService = new ProductValidationService();
    }

    /**
     * Update a single product price
     */
    public function updatePrice(
        int $productId,
        string $priceType,
        float $newPrice,
        string $effectiveDate,
        int $userId,
        ?string $reason = null
    ): array {
        $v = $this->validationService->validatePriceChange([
            'price_type'     => $priceType,
            'new_price'      => $newPrice,
            'effective_date' => $effectiveDate,
            'reason'         => $reason,
        ]);
        if (!$v['valid']) {
            return ['success' => false, 'errors' => $v['errors']];
        }

        $product = $this->productModel->find($productId);
        if (!$product) {
            return ['success' => false, 'errors' => ['general' => 'Product not found']];
        }

        // Selling price must always be > 0.
        // A value of 0 would make the product permanently blocked from POS.
        if ($priceType === 'SELLING' && $newPrice <= 0) {
            return ['success' => false, 'errors' => ['new_price' => 'Selling price must be greater than zero.']];
        }

        // products.unit_price is the selling price (aliased in views).
        // products.cost_price is the cost price.
        $oldPrice = ($priceType === 'COST')
            ? (float)($product['cost_price'] ?? 0)
            : (float)($product['unit_price'] ?? 0);

        if (abs($newPrice - $oldPrice) < 0.001) {
            return ['success' => false, 'errors' => ['new_price' => 'Price is the same as current']];
        }

        $db = Database::getInstance();
        try {
            $db->beginTransaction();

            // Map price_type to the actual DB column name.
            // SELLING → unit_price (the official selling price column)
            // COST    → cost_price
            $field      = ($priceType === 'COST') ? 'cost_price' : 'unit_price';
            $updateData = [$field => $newPrice, 'updated_by' => $userId];

            $cost    = ($priceType === 'COST')    ? $newPrice : (float)($product['cost_price'] ?? 0);
            $selling = ($priceType === 'SELLING') ? $newPrice : (float)($product['unit_price'] ?? 0);
            if ($cost > 0 && $selling > 0) {
                $updateData['markup_percentage'] = round((($selling - $cost) / $cost) * 100, 2);
            }

            $this->productModel->update($productId, $updateData);

            $this->priceHistoryModel->recordPriceChange(
                $productId, $priceType, $oldPrice, $newPrice,
                $effectiveDate, $userId, $reason
            );

            $this->activityLog->log(
                'product.price_changed',
                "Changed {$priceType} price for product #{$productId}: {$oldPrice} to {$newPrice}",
                'products', $productId, $userId
            );

            $db->commit();
            return ['success' => true, 'message' => 'Price updated'];

        } catch (\Exception $e) {
            $db->rollback();
            return ['success' => false, 'errors' => ['general' => 'Price update failed']];
        }
    }

    /**
     * Bulk price adjustment (percentage or fixed amount)
     */
    public function bulkAdjust(
        array $productIds,
        string $priceType,
        string $adjustType,
        float $value,
        string $effectiveDate,
        int $userId,
        ?string $reason = 'Bulk price adjustment'
    ): array {
        $updated = 0;
        $failed  = 0;

        foreach ($productIds as $id) {
            $product = $this->productModel->find((int)$id);
            if (!$product) { $failed++; continue; }

            $current = ($priceType === 'COST')
                ? (float)$product['cost_price']
                : (float)$product['selling_price'];

            $newPrice = ($adjustType === 'PERCENTAGE')
                ? $current * (1 + $value / 100)
                : $current + $value;

            $newPrice = max(0, round($newPrice, 2));

            $result = $this->updatePrice((int)$id, $priceType, $newPrice, $effectiveDate, $userId, $reason);
            $result['success'] ? $updated++ : $failed++;
        }

        return ['updated' => $updated, 'failed' => $failed];
    }

    /**
     * Get price analysis for a product
     */
    public function getPriceAnalysis(int $productId): array
    {
        $product = $this->productModel->find($productId);
        if (!$product) return [];

        return [
            'current_cost'    => (float)$product['cost_price'],
            'current_selling' => (float)$product['selling_price'],
            'markup'          => (float)($product['markup_percentage'] ?? 0),
            'cost_history'    => $this->priceHistoryModel->getProductPriceHistory($productId, 'COST', 12),
            'selling_history' => $this->priceHistoryModel->getProductPriceHistory($productId, 'SELLING', 12),
            'stats'           => $this->priceHistoryModel->getPriceStatistics($productId),
        ];
    }
}
