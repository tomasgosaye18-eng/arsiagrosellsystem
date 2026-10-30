<?php
/**
 * =========================================================
 * PRODUCT SERVICE
 * =========================================================
 * File    : modules/admin/services/ProductService.php
 * Purpose : Business logic orchestration for product management.
 *           Handles create, update, soft-delete, restore,
 *           image upload/delete, price tracking.
 * Deps    : ProductModel, ProductImageModel,
 *           ProductPriceHistoryModel, BarcodeService,
 *           ProductImageUploadService, ProductValidationService,
 *           ActivityLogModel, Database
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Admin\Services;

use AAM\Admin\Models\ProductModel;
use AAM\Admin\Models\ProductImageModel;
use AAM\Admin\Models\ProductPriceHistoryModel;
use AAM\Admin\Models\ActivityLogModel;
use AAM\Core\Database;

class ProductService
{
    private ProductModel $productModel;
    private ProductImageModel $imageModel;
    private ProductPriceHistoryModel $priceHistoryModel;
    private BarcodeService $barcodeService;
    private ProductImageUploadService $imageUploadService;
    private ProductValidationService $validationService;
    private ActivityLogModel $activityLog;

    public function __construct()
    {
        $this->productModel      = new ProductModel();
        $this->imageModel        = new ProductImageModel();
        $this->priceHistoryModel = new ProductPriceHistoryModel();
        $this->barcodeService    = new BarcodeService();
        $this->imageUploadService = new ProductImageUploadService();
        $this->validationService = new ProductValidationService();
        $this->activityLog       = new ActivityLogModel();
    }

    // ── Create product ────────────────────────────────────
    public function createProduct(array $data, ?array $imageFile = null, int $userId = 0): array
    {
        $v = $this->validationService->validateProduct($data);
        if (!$v['valid']) {
            return ['success' => false, 'errors' => $v['errors']];
        }

        if ($imageFile && ($imageFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $iv = $this->validationService->validateImage($imageFile);
            if (!$iv['valid']) {
                return ['success' => false, 'errors' => $iv['errors']];
            }
        }

        $db = Database::getInstance();

        try {
            $db->beginTransaction();

            // Auto-generate barcode
            if (empty($data['barcode'])) {
                do {
                    $barcode = $this->barcodeService->generateBarcodeNumber();
                } while ($this->productModel->barcodeExists($barcode));
                $data['barcode'] = $barcode;
            }

            // Auto-generate SKU code
            if (empty($data['sku']) && empty($data['product_code'])) {
                $data['sku'] = $this->productModel->getNextCode();
            } elseif (empty($data['sku']) && !empty($data['product_code'])) {
                $data['sku'] = $data['product_code'];
            }

            $data['name']       = $data['name'] ?? $data['product_name'] ?? '';
            $data['unit_price'] = $data['unit_price'] ?? $data['selling_price'] ?? 0;
            $data['created_by'] = $userId;
            $data['is_active']  = $data['is_active'] ?? 1;
            $data['product_status'] = $data['product_status'] ?? 'active';
            unset($data['price_change_reason']);

            // Image upload
            $primaryImagePath = null;
            $uploadResult     = [];
            if ($imageFile && ($imageFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $uploadResult = $this->imageUploadService->uploadProductImage($imageFile);
                if ($uploadResult['success']) {
                    $primaryImagePath     = $uploadResult['path'];
                    $data['primary_image'] = $primaryImagePath;
                }
            }

            $productId = $this->productModel->create($data);
            if (!$productId) {
                throw new \Exception('Insert failed');
            }

            // Save image record
            if ($primaryImagePath) {
                $this->imageModel->create([
                    'product_id' => $productId,
                    'filename'   => $primaryImagePath,
                    'alt_text'   => $data['name'] ?? $data['product_name'] ?? '',
                    'is_primary' => 1,
                    'sort_order' => 0,
                ]);
            }

            // Initial price history
            $costPrice    = (float)($data['cost_price']  ?? 0);
            $sellingPrice = (float)($data['unit_price']  ?? $data['selling_price'] ?? 0);
            if ($costPrice > 0) {
                $this->priceHistoryModel->recordPriceChange(
                    $productId, 'COST', 0.00, $costPrice,
                    date('Y-m-d'), $userId, 'Initial cost price'
                );
            }
            if ($sellingPrice > 0) {
                $this->priceHistoryModel->recordPriceChange(
                    $productId, 'SELLING', 0.00, $sellingPrice,
                    date('Y-m-d'), $userId, 'Initial selling price'
                );
            }

            // Activity log
            $productName = $data['name'] ?? $data['product_name'] ?? "#{$productId}";
            $productCode = $data['sku']  ?? $data['product_code'] ?? '';
            $this->activityLog->log(
                'product.created',
                "Created product: {$productName} ({$productCode})",
                'products', $productId, $userId
            );

            $db->commit();

            return [
                'success'    => true,
                'product_id' => $productId,
                'barcode'    => $data['barcode'],
                'message'    => 'Product created successfully',
            ];

        } catch (\Exception $e) {
            $db->rollback();
            if (!empty($primaryImagePath)) {
                $this->imageUploadService->deleteImageFile($primaryImagePath);
            }
            return ['success' => false, 'errors' => ['general' => 'Failed to create product. ' . $e->getMessage()]];
        }
    }

    // ── Update product ────────────────────────────────────
    public function updateProduct(int $productId, array $data, int $userId = 0): array
    {
        $existing = $this->productModel->getByIdWithRelations($productId);
        if (!$existing) {
            return ['success' => false, 'errors' => ['general' => 'Product not found']];
        }

        $v = $this->validationService->validateProduct($data, $productId);
        if (!$v['valid']) {
            return ['success' => false, 'errors' => $v['errors']];
        }

        $db = Database::getInstance();

        try {
            $db->beginTransaction();

            // Support both aliased and DB column names for prices.
            // If neither selling_price nor unit_price is present in $data, the
            // Admin has been stripped of price-setting capability — preserve the
            // existing selling price by falling back to $oldSelling.
            $oldCost    = (float)$existing['cost_price'];
            $oldSelling = (float)$existing['selling_price'];
            $newCost    = array_key_exists('cost_price', $data)
                            ? (float)$data['cost_price']
                            : $oldCost;
            $newSelling = array_key_exists('selling_price', $data)
                            ? (float)$data['selling_price']
                            : (array_key_exists('unit_price', $data) ? (float)$data['unit_price'] : $oldSelling);
            $reason     = $data['price_change_reason'] ?? 'Updated via product edit';

            if (abs($newCost - $oldCost) > 0.001) {
                $this->priceHistoryModel->recordPriceChange(
                    $productId, 'COST', $oldCost, $newCost, date('Y-m-d'), $userId, $reason
                );
            }
            if (abs($newSelling - $oldSelling) > 0.001) {
                $this->priceHistoryModel->recordPriceChange(
                    $productId, 'SELLING', $oldSelling, $newSelling, date('Y-m-d'), $userId, $reason
                );
            }

            $data['updated_by'] = $userId;
            unset($data['price_change_reason']);

            // Never let the model->update() call overwrite selling price through
            // the general product edit path.  Selling price changes are handled
            // exclusively by GMPricingController.  Remove the price aliases from
            // the update payload so the model writes only non-price catalog fields.
            // (The GM path uses a dedicated atomic update in GMPricingController
            //  which writes unit_price directly — this does NOT pass through here.)
            unset($data['selling_price'], $data['unit_price']);

            $affected = $this->productModel->update($productId, $data);
            if ($affected === false) {
                throw new \Exception('Update failed');
            }

            $productName = $data['product_name'] ?? $data['name'] ?? "#{$productId}";
            $this->activityLog->log(
                'product.updated',
                "Updated product: {$productName}",
                'products', $productId, $userId
            );

            $db->commit();

            return ['success' => true, 'message' => 'Product updated successfully'];

        } catch (\Exception $e) {
            $db->rollback();
            return ['success' => false, 'errors' => ['general' => 'Failed to update product.']];
        }
    }

    // ── Upload image ──────────────────────────────────────
    public function uploadProductImage(int $productId, array $imageFile, int $userId = 0, bool $setAsPrimary = false): array
    {
        $product = $this->productModel->find($productId);
        if (!$product) {
            return ['success' => false, 'errors' => ['general' => 'Product not found']];
        }

        $v = $this->validationService->validateImage($imageFile);
        if (!$v['valid']) {
            return ['success' => false, 'errors' => $v['errors']];
        }

        $imageCount = $this->imageModel->countProductImages($productId);
        if ($imageCount >= 10) {
            return ['success' => false, 'errors' => ['file' => 'Maximum 10 images per product']];
        }

        try {
            $uploadResult = $this->imageUploadService->uploadProductImage($imageFile);
            if (!$uploadResult['success']) {
                return ['success' => false, 'errors' => ['file' => $uploadResult['error'] ?? 'Upload failed']];
            }

            $isPrimary    = ($imageCount === 0 || $setAsPrimary) ? 1 : 0;
            $displayOrder = $this->imageModel->getMaxDisplayOrder($productId) + 1;

            $imageId = $this->imageModel->create([
                'product_id' => $productId,
                'filename'   => $uploadResult['path'],
                'alt_text'   => $product['name'] ?? $product['product_name'] ?? '',
                'is_primary' => $isPrimary,
                'sort_order' => $displayOrder,
            ]);

            if ($isPrimary) {
                $this->productModel->update($productId, [
                    'primary_image' => $uploadResult['path'],
                    'updated_by'    => $userId,
                ]);
            }

            $totalImages = $this->imageModel->countProductImages($productId);
            $this->productModel->update($productId, ['has_multiple_images' => $totalImages > 1 ? 1 : 0]);

            return ['success' => true, 'image' => $this->imageModel->getImageById($imageId)];

        } catch (\Exception $e) {
            return ['success' => false, 'errors' => ['file' => 'Image upload failed']];
        }
    }

    // ── Delete image ──────────────────────────────────────
    public function deleteProductImage(int $imageId, int $productId, int $userId = 0): array
    {
        $image = $this->imageModel->getImageById($imageId, $productId);
        if (!$image) {
            return ['success' => false, 'error' => 'Image not found'];
        }

        $deletedRow = $this->imageModel->deleteImage($imageId, $productId);
        if (!$deletedRow) {
            return ['success' => false, 'error' => 'Failed to delete image'];
        }

        // Clean up the physical file (filename = image_path alias)
        $filePath = $image['image_path'] ?? $image['filename'] ?? null;
        if ($filePath) {
            $this->imageUploadService->deleteImageFile($filePath);
        }

        if (($image['image_type'] ?? '') === 'PRIMARY' || ($image['is_primary'] ?? 0)) {
            $nextImages = $this->imageModel->getProductImages($productId);
            if (!empty($nextImages)) {
                $next = $nextImages[0];
                $this->imageModel->setAsPrimary($next['image_id'], $productId);
                $this->productModel->update($productId, [
                    'primary_image' => $next['image_path'],
                    'updated_by'    => $userId,
                ]);
            } else {
                $this->productModel->update($productId, [
                    'primary_image'      => null,
                    'has_multiple_images' => 0,
                    'updated_by'         => $userId,
                ]);
            }
        }

        $remaining = $this->imageModel->countProductImages($productId);
        $this->productModel->update($productId, ['has_multiple_images' => $remaining > 1 ? 1 : 0]);

        return ['success' => true];
    }

    // ── Delete product (soft) ─────────────────────────────
    public function deleteProduct(int $productId, int $userId = 0): array
    {
        $product = $this->productModel->find($productId);
        if (!$product) {
            return ['success' => false, 'error' => 'Product not found'];
        }

        // Use softDelete to set deleted_at (queries filter by deleted_at IS NULL)
        $ok = $this->productModel->softDelete($productId);
        if ($ok) {
            $productName = $product['name'] ?? $product['product_name'] ?? "#{$productId}";
            $this->activityLog->log(
                'product.deleted',
                "Deleted product: {$productName}",
                'products', $productId, $userId
            );
            return ['success' => true];
        }

        return ['success' => false, 'error' => 'Delete failed'];
    }

    // ── Restore product ───────────────────────────────────
    public function restoreProduct(int $productId, int $userId = 0): array
    {
        $affected = $this->productModel->restore($productId);
        if ($affected > 0) {
            $product = $this->productModel->find($productId);
            $this->activityLog->log(
                'product.restored',
                "Restored product: " . ($product['name'] ?? $product['product_name'] ?? $productId),
                'products', $productId, $userId
            );
            return ['success' => true];
        }
        return ['success' => false, 'error' => 'Restore failed'];
    }

    // ── Update prices ─────────────────────────────────────
    public function updateProductPrices(int $productId, array $priceData, int $userId = 0): array
    {
        $v = $this->validationService->validatePriceChange($priceData);
        if (!$v['valid']) {
            return ['success' => false, 'errors' => $v['errors']];
        }

        $product = $this->productModel->find($productId);
        if (!$product) {
            return ['success' => false, 'errors' => ['general' => 'Product not found']];
        }

        $priceType = $priceData['price_type'];
        $newPrice  = (float)$priceData['new_price'];
        $oldPrice  = ($priceType === 'COST') ? (float)$product['cost_price'] : (float)$product['selling_price'];

        if (abs($newPrice - $oldPrice) < 0.001) {
            return ['success' => false, 'errors' => ['new_price' => 'New price is the same as current']];
        }

        $db = Database::getInstance();

        try {
            $db->beginTransaction();

            $field      = ($priceType === 'COST') ? 'cost_price' : 'selling_price';
            $updateData = [$field => $newPrice, 'updated_by' => $userId];
            $cost       = ($priceType === 'COST')    ? $newPrice : (float)$product['cost_price'];
            $selling    = ($priceType === 'SELLING') ? $newPrice : (float)$product['selling_price'];
            if ($cost > 0) {
                $updateData['markup_percentage'] = round((($selling - $cost) / $cost) * 100, 2);
            }

            $this->productModel->update($productId, $updateData);
            $this->priceHistoryModel->recordPriceChange(
                $productId, $priceType, $oldPrice, $newPrice,
                $priceData['effective_date'], $userId, $priceData['reason'] ?? null
            );

            $db->commit();
            return ['success' => true, 'message' => 'Price updated'];

        } catch (\Exception $e) {
            $db->rollback();
            return ['success' => false, 'errors' => ['general' => 'Price update failed']];
        }
    }

    // ── Dashboard stats ───────────────────────────────────
    public function getDashboardStats(): array
    {
        return [
            'totals'     => $this->productModel->getStatistics(),
            'by_category' => $this->productModel->getProductsByCategory(),
            'recent'     => $this->productModel->getRecentProducts(5),
        ];
    }
}
