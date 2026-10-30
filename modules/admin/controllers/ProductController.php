<?php
/**
 * =========================================================
 * PRODUCT CONTROLLER
 * =========================================================
 * File    : modules/admin/controllers/ProductController.php
 * Purpose : Full product lifecycle management: CRUD,
 *           image gallery, status & featured toggling,
 *           barcode search, price history, CSV export.
 * Deps    : BaseController, ProductService, ProductModel,
 *           CategoryModel, UnitModel, ProductImageModel,
 *           ProductPriceHistoryModel, ActivityLogModel,
 *           BarcodeService
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Admin\Controllers;

use AAM\Core\BaseController;
use AAM\Admin\Models\ProductModel;
use AAM\Admin\Models\CategoryModel;
use AAM\Admin\Models\UnitModel;
use AAM\Admin\Models\ProductImageModel;
use AAM\Admin\Models\ProductPriceHistoryModel;
use AAM\Admin\Models\ActivityLogModel;
use AAM\Admin\Services\ProductService;
use AAM\Admin\Services\BarcodeService;

class ProductController extends BaseController
{
    private ProductModel $productModel;
    private CategoryModel $categoryModel;
    private UnitModel $unitModel;
    private ProductImageModel $imageModel;
    private ProductPriceHistoryModel $priceHistoryModel;
    private ActivityLogModel $activityLog;
    private ProductService $productService;
    private BarcodeService $barcodeService;

    public function __construct()
    {
        parent::__construct();
        $this->productModel      = new ProductModel();
        $this->categoryModel     = new CategoryModel();
        $this->unitModel         = new UnitModel();
        $this->imageModel        = new ProductImageModel();
        $this->priceHistoryModel = new ProductPriceHistoryModel();
        $this->activityLog       = new ActivityLogModel();
        $this->productService    = new ProductService();
        $this->barcodeService    = new BarcodeService();
    }

    // â”€â”€ List â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function index(): void
    {
        $this->requireAuth();

        $filters = [
            'search'         => $this->query('search', ''),
            'category_id'    => $this->query('category_id', ''),
            'is_active'      => $this->query('is_active', ''),
            'product_status' => $this->query('product_status', ''),
            // Price-range filters are silenced for non-management roles.
            'price_min'      => $this->isMgr() ? $this->query('price_min', '') : '',
            'price_max'      => $this->isMgr() ? $this->query('price_max', '') : '',
            'is_featured'    => $this->query('is_featured', ''),
        ];
        $page    = max(1, (int)$this->query('page', 1));
        $perPage = 15;
        $offset  = ($page - 1) * $perPage;

        $products = $this->productModel->getAllWithRelations($filters, $perPage, $offset);
        $total    = $this->productModel->countWithFilters($filters);

        $this->render('admin/products/index', [
            'title'      => $this->isMgr() ? 'Product Management' : 'Product Catalog',
            'products'   => $products,
            'filters'    => $filters,
            'stats'      => $this->productModel->getStatistics(),
            'categories' => $this->categoryModel->getActiveCategories(),
            'statuses'   => $this->productModel->getProductStatuses(),
            'pagination' => [
                'current_page'  => $page,
                'total_pages'   => (int)ceil($total / $perPage),
                'total_records' => $total,
                'per_page'      => $perPage,
            ],
        ]);
    }

    // â”€â”€ Create form â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function create(): void
    {
        $this->requireRole(ROLE_ADMIN, ROLE_GENERAL_MANAGER);

        $this->render('admin/products/create', [
            'title'      => 'Add New Product',
            'categories' => $this->categoryModel->getActiveCategories(),
            'units'      => $this->unitModel->getActiveUnits(),
            'next_code'  => $this->productModel->getNextCode(),
            'barcode'    => $this->barcodeService->generateBarcodeNumber(),
            'statuses'   => $this->productModel->getProductStatuses(),
        ]);
    }

    // â”€â”€ Store â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function store(): void
    {
        $this->requireRole(ROLE_ADMIN, ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $data      = $this->collectFormData();
        $imageFile = $_FILES['primary_image'] ?? null;

        // Selling price is the General Manager's exclusive responsibility.
        // When the System Administrator creates a product, the selling price
        // is forced to 0 (NULL/unset). The GM must set it via
        // POST /gm/products/{id}/pricing before the product can be sold.
        if ($this->userRole() === ROLE_ADMIN) {
            $data['unit_price']    = 0;
            $data['selling_price'] = 0;
        }

        $result = $this->productService->createProduct($data, $imageFile, $this->userId());

        if ($result['success']) {
            $this->jsonSuccess(
                ['product_id' => $result['product_id'], 'barcode' => $result['barcode'],
                 'redirect' => url('admin/products')],
                'Product created successfully'
            );
        } else {
            $this->jsonError(
                $result['errors']['general'] ?? 'Validation failed',
                HTTP_UNPROCESSABLE_ENTITY,
                $result['errors']
            );
        }
    }

    // â”€â”€ Show â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function show(int $id): void
    {
        $this->requireAuth();

        $product = $this->productModel->getByIdWithRelations($id);
        if (!$product) {
            $back = $this->isMgr() ? url('admin/products') : url('products');
            $this->redirectWith($back, FLASH_ERROR, 'Product not found');
            return;
        }

        $images = $this->imageModel->getProductImages($id);

        // Farm Manager (and any other non-management role) gets the
        // read-only catalog view — no price, cost, or write controls.
        if (!$this->isMgr()) {
            $this->render('admin/products/show_farm', [
                'title'   => 'Product Details',
                'product' => $product,
                'images'  => $images,
            ]);
            return;
        }

        // Full management detail view for Admin / General Manager.
        $this->render('admin/products/show', [
            'title'         => 'Product Details',
            'product'       => $product,
            'images'        => $images,
            'price_history' => $this->priceHistoryModel->getProductPriceHistory($id, null, 20),
            'price_stats'   => $this->priceHistoryModel->getPriceStatistics($id),
        ]);
    }

    // â”€â”€ Edit form â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function edit(int $id): void
    {
        $this->requireRole(ROLE_ADMIN, ROLE_GENERAL_MANAGER);

        $product = $this->productModel->getByIdWithRelations($id);
        if (!$product) {
            $this->redirectWith(url('admin/products'), FLASH_ERROR, 'Product not found');
            return;
        }

        $this->render('admin/products/edit', [
            'title'      => 'Edit Product',
            'product'    => $product,
            'categories' => $this->categoryModel->getActiveCategories(),
            'units'      => $this->unitModel->getActiveUnits(),
            'images'     => $this->imageModel->getProductImages($id),
            'statuses'   => $this->productModel->getProductStatuses(),
        ]);
    }

    // â”€â”€ Update â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function update(int $id): void
    {
        $this->requireRole(ROLE_ADMIN, ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $data = $this->collectFormData();

        // Selling price is the General Manager's exclusive responsibility.
        // Strip any price data submitted by the System Administrator so it
        // cannot overwrite a price the GM has already set.
        if ($this->userRole() === ROLE_ADMIN) {
            unset($data['unit_price'], $data['selling_price']);
            // Also prevent the service from recording a SELLING price history entry
            // by removing the price fields entirely.
        }

        $result = $this->productService->updateProduct($id, $data, $this->userId());

        if ($result['success']) {
            $this->jsonSuccess(['redirect' => url('admin/products')], 'Product updated successfully');
        } else {
            $this->jsonError(
                $result['errors']['general'] ?? 'Validation failed',
                HTTP_UNPROCESSABLE_ENTITY,
                $result['errors']
            );
        }
    }

    // â”€â”€ Toggle active status â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function toggleStatus(int $id): void
    {
        $this->requireRole(ROLE_ADMIN, ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();
        $product = $this->productModel->find($id);
        if (!$product) { $this->jsonError('Not found', HTTP_NOT_FOUND); return; }

        if ($this->productModel->toggleStatus($id)) {
            $s = $product['is_active'] ? 'inactive' : 'active';
            $this->jsonSuccess(['new_status' => $s], "Product set to {$s}");
        } else {
            $this->jsonError('Status update failed', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // â”€â”€ Toggle featured â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function toggleFeatured(int $id): void
    {
        $this->requireRole(ROLE_ADMIN, ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();
        $product = $this->productModel->find($id);
        if (!$product) { $this->jsonError('Not found', HTTP_NOT_FOUND); return; }

        if ($this->productModel->toggleFeatured($id)) {
            $featured = !(bool)$product['is_featured'];
            $this->jsonSuccess(['is_featured' => $featured], $featured ? 'Product featured' : 'Product unfeatured');
        } else {
            $this->jsonError('Failed to toggle featured', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // â”€â”€ Update product_status enum â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function updateProductStatus(int $id): void
    {
        $this->requireRole(ROLE_ADMIN, ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();
        $status  = $this->input('product_status', '');
        $allowed = array_keys($this->productModel->getProductStatuses());
        if (!in_array($status, $allowed, true)) {
            $this->jsonError('Invalid status', HTTP_UNPROCESSABLE_ENTITY);
            return;
        }
        if ($this->productModel->updateStatus($id, $status)) {
            $this->jsonSuccess(['status' => $status], 'Status updated');
        } else {
            $this->jsonError('Failed to update status', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // â”€â”€ Soft delete â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function delete(int $id): void
    {
        $this->requireRole(ROLE_ADMIN, ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();
        $result = $this->productService->deleteProduct($id, $this->userId());
        if ($result['success']) {
            $this->jsonSuccess(null, 'Product deleted');
        } else {
            $this->jsonError($result['error'] ?? 'Delete failed', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // â”€â”€ Restore â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function restore(int $id): void
    {
        $this->requireRole(ROLE_ADMIN, ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();
        $result = $this->productService->restoreProduct($id, $this->userId());
        if ($result['success']) {
            $this->jsonSuccess(null, 'Product restored');
        } else {
            $this->jsonError('Restore failed', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // â”€â”€ Upload image â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function uploadImage(int $id): void
    {
        $this->requireRole(ROLE_ADMIN, ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();
        $file      = $_FILES['product_image'] ?? null;
        $isPrimary = (bool)$this->input('is_primary', false);
        if (!$file) { $this->jsonError('No file received', HTTP_UNPROCESSABLE_ENTITY); return; }

        $result = $this->productService->uploadProductImage($id, $file, $this->userId(), $isPrimary);
        if ($result['success']) {
            $this->jsonSuccess(['image' => $result['image']], 'Image uploaded');
        } else {
            $this->jsonError($result['errors']['file'] ?? 'Upload failed', HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    // â”€â”€ Set primary image â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function setPrimaryImage(int $productId, int $imageId): void
    {
        $this->requireRole(ROLE_ADMIN, ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();
        if ($this->imageModel->setAsPrimary($imageId, $productId)) {
            $img = $this->imageModel->getImageById($imageId);
            if ($img) {
                $this->productModel->update($productId, [
                    'primary_image' => $img['image_path'] ?? $img['filename'] ?? null,
                    'updated_by'    => $this->userId(),
                ]);
            }
            $this->jsonSuccess(null, 'Primary image updated');
        } else {
            $this->jsonError('Failed to set primary image', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // â”€â”€ Delete image â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function deleteImage(int $productId, int $imageId): void
    {
        $this->requireRole(ROLE_ADMIN, ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();
        $result = $this->productService->deleteProductImage($imageId, $productId, $this->userId());
        if ($result['success']) {
            $this->jsonSuccess(null, 'Image deleted');
        } else {
            $this->jsonError($result['error'] ?? 'Delete failed', HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // â”€â”€ Barcode quick search (AJAX) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function barcodeSearch(): void
    {
        $barcode = trim($this->query('barcode', ''));
        if (!$barcode) { $this->jsonError('Barcode required', HTTP_UNPROCESSABLE_ENTITY); return; }
        $product = $this->productModel->findByBarcode($barcode);
        if ($product) {
            $this->jsonSuccess(['product' => $product]);
        } else {
            $this->jsonError('Product not found', HTTP_NOT_FOUND);
        }
    }

    // â”€â”€ Live search (AJAX) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function search(): void
    {
        $filters = [
            'search'         => $this->query('q', ''),
            'category_id'    => $this->query('category_id', ''),
            'product_status' => $this->query('status', ''),
        ];
        $this->json(['success' => true, 'products' => $this->productModel->getAllWithRelations($filters, 20, 0)]);
    }

    // â”€â”€ Price history (AJAX) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function priceHistory(int $id): void
    {
        $this->requireRole(ROLE_ADMIN, ROLE_GENERAL_MANAGER);

        $type    = $this->query('type') ?: null;
        $history = $this->priceHistoryModel->getProductPriceHistory($id, $type, 50);
        $stats   = $this->priceHistoryModel->getPriceStatistics($id);
        $this->json(['success' => true, 'history' => $history, 'stats' => $stats]);
    }

    // â”€â”€ Export CSV â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function exportCsv(): void
    {
        $this->requireRole(ROLE_ADMIN, ROLE_GENERAL_MANAGER);

        $filters  = [
            'category_id'    => $this->query('category_id', ''),
            'product_status' => $this->query('product_status', ''),
            'is_active'      => $this->query('is_active', ''),
        ];
        $products = $this->productModel->getAllWithRelations($filters, 5000, 0);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="products_' . date('Ymd_His') . '.csv"');
        header('Cache-Control: no-store, no-cache');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Code','Barcode','Name','Scientific Name','Category','Unit','Brand',
                        'Cost Price','Selling Price','Markup %','Min Stock','Max Stock',
                        'Reorder Level','Requires Expiry','Shelf Life Days','Storage',
                        'Status','Active','Featured','Created At']);
        foreach ($products as $p) {
            fputcsv($out, [
                $p['product_code'], $p['barcode'] ?? '', $p['product_name'],
                $p['scientific_name'] ?? '', $p['category_name'],
                $p['unit_name'].' ('.$p['unit_abbreviation'].')',
                $p['brand'] ?? '', $p['cost_price'], $p['selling_price'],
                $p['markup_percentage'] ?? '', $p['minimum_stock'],
                $p['maximum_stock'] ?? '', $p['reorder_level'],
                $p['requires_expiry'] ? 'Yes' : 'No',
                $p['shelf_life_days'] ?? '', $p['storage_conditions'] ?? '',
                $p['product_status'], $p['is_active'] ? 'Yes' : 'No',
                $p['is_featured'] ? 'Yes' : 'No', $p['created_at'],
            ]);
        }
        fclose($out);
        exit;
    }

    // â”€â”€ Export categories CSV â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    public function exportCategoriesCsv(): void
    {
        $this->requireRole(ROLE_ADMIN, ROLE_GENERAL_MANAGER);

        $categories = $this->categoryModel->getAllWithCreator([], 1000, 0);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="categories_' . date('Ymd_His') . '.csv"');
        header('Cache-Control: no-store, no-cache');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Code','Name','Description','Display Order','Icon','Products','Active','Created At']);
        foreach ($categories as $cat) {
            fputcsv($out, [
                $cat['category_code'], $cat['category_name'], $cat['description'] ?? '',
                $cat['display_order'], $cat['icon'] ?? '', $cat['product_count'],
                $cat['is_active'] ? 'Yes' : 'No', $cat['created_at'],
            ]);
        }
        fclose($out);
        exit;
    }

    // â”€â”€ Private: collect form data â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    // -- Private: management role check -----------------------------------------
    /** Returns true for roles that may manage (write) products. */
    private function isMgr(): bool
    {
        return in_array($this->userRole(), [ROLE_ADMIN, ROLE_GENERAL_MANAGER], true);
    }

    private function collectFormData(): array
    {
        // Map form field names → actual DB column names in `products` table.
        // Views use "product_name", "product_code", "selling_price" etc., but
        // the DB stores "name", "sku", "unit_price" etc.
        return [
            // DB column  ← form field
            'sku'             => strtoupper(trim($this->input('product_code', ''))),
            'barcode'         => trim($this->input('barcode', '')) ?: null,
            'name'            => trim($this->input('product_name', '')),
            'description'     => trim($this->input('description', '')),
            'category_id'     => (int)$this->input('category_id', 0),
            'unit_id'         => (int)$this->input('unit_id', 0),
            'cost_price'      => (float)$this->input('cost_price', 0),
            'unit_price'      => (float)$this->input('selling_price', 0),
            'min_stock_level' => (int)$this->input('minimum_stock', 0),
            'reorder_point'   => (int)$this->input('reorder_level', 0),
            'is_perishable'   => (int)$this->input('requires_expiry', 0),
            'shelf_life_days' => $this->input('shelf_life_days') ?: null,
            'product_status'  => $this->input('product_status', 'active'),
            'is_featured'     => (int)$this->input('is_featured', 0),
            'is_active'       => (int)$this->input('is_active', 1),
            // View-friendly aliases passed through for service/validation layer
            'product_code'        => strtoupper(trim($this->input('product_code', ''))),
            'product_name'        => trim($this->input('product_name', '')),
            'selling_price'       => (float)$this->input('selling_price', 0),
            'minimum_stock'       => (int)$this->input('minimum_stock', 0),
            'reorder_level'       => (int)$this->input('reorder_level', 0),
            'requires_expiry'     => (int)$this->input('requires_expiry', 0),
            'price_change_reason' => trim($this->input('price_change_reason', '')),
        ];
    }
}

