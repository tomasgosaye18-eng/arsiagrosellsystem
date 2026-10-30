<?php
/**
 * =========================================================
 * BARCODE CONTROLLER
 * =========================================================
 * File    : modules/admin/controllers/BarcodeController.php
 * Purpose : Barcode image delivery, SVG generation,
 *           number generation/validation, single & batch
 *           label printing.
 * Deps    : BaseController, BarcodeService, ProductModel
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Admin\Controllers;

use AAM\Core\BaseController;
use AAM\Admin\Models\ProductModel;
use AAM\Admin\Services\BarcodeService;

class BarcodeController extends BaseController
{
    private ProductModel $productModel;
    private BarcodeService $barcodeService;

    public function __construct()
    {
        parent::__construct();
        $this->productModel   = new ProductModel();
        $this->barcodeService = new BarcodeService();
    }

    /**
     * Output barcode PNG image directly (for <img src="..."> use)
     * GET /admin/products/{id}/barcode/image
     */
    public function image(int $id): void
    {
        $product = $this->productModel->find($id);
        if (!$product || empty($product['barcode'])) {
            http_response_code(HTTP_NOT_FOUND);
            exit;
        }
        $this->barcodeService->outputBarcodeImage($product['barcode'], 'png', 300, 80);
        exit;
    }

    /**
     * Render barcode PNG from raw code value (no DB)
     * GET /admin/barcode/render?code=XXXXXX
     */
    public function renderImage(): void
    {
        $code = trim($this->query('code', ''));
        if (empty($code)) {
            http_response_code(HTTP_UNPROCESSABLE_ENTITY);
            exit;
        }
        $this->barcodeService->outputBarcodeImage($code, 'png', 300, 80);
        exit;
    }

    /**
     * Generate a fresh unique barcode number (AJAX)
     * GET /admin/barcode/generate
     */
    public function generate(): void
    {
        $prefix = $this->query('prefix', '123');
        do {
            $barcode = $this->barcodeService->generateBarcodeNumber($prefix);
        } while ($this->productModel->barcodeExists($barcode));

        $this->jsonSuccess(['barcode' => $barcode]);
    }

    /**
     * Validate a barcode value (AJAX)
     * GET /admin/barcode/validate?code=XXXXXX&product_id=N
     */
    public function validateBarcode(): void
    {
        $code      = trim($this->query('code', ''));
        $productId = (int)$this->query('product_id', 0);

        if (empty($code)) {
            $this->jsonError('Barcode is required', HTTP_UNPROCESSABLE_ENTITY);
            return;
        }

        $exists = $this->productModel->barcodeExists($code, $productId ?: null);

        $this->json([
            'success'  => true,
            'is_valid' => !$exists,
            'message'  => $exists ? 'Barcode already in use' : 'Barcode is available',
        ]);
    }

    /**
     * Return barcode SVG markup (AJAX – used in form preview)
     * GET /admin/barcode/svg?code=XXXXXX
     */
    public function svg(): void
    {
        $code = trim($this->query('code', ''));
        if (empty($code)) {
            $this->jsonError('Code is required', HTTP_UNPROCESSABLE_ENTITY);
            return;
        }
        $svg = $this->barcodeService->generateBarcodeSVG($code, 300, 80);
        $this->jsonSuccess(['svg' => $svg]);
    }

    /**
     * Single-product barcode print page
     * GET /admin/products/{id}/barcode/print
     */
    public function print(int $id): void
    {
        $this->requireRole(ROLE_ADMIN, ROLE_GENERAL_MANAGER);

        $product = $this->productModel->getByIdWithRelations($id);
        if (!$product) {
            $this->redirectWith(url('admin/products'), FLASH_ERROR, 'Product not found');
            return;
        }

        $size       = $this->query('size', 'medium');
        $barcodeSvg = $this->barcodeService->generateBarcodeSVG($product['barcode'], 300, 80);

        $this->render('admin/products/barcode-print', [
            'title'       => 'Print Barcode – ' . $product['product_name'],
            'product'     => $product,
            'barcode_svg' => $barcodeSvg,
            'size'        => $size,
            'layout'      => 'print',
        ]);
    }

    /**
     * Batch barcode print page
     * POST /admin/barcode/batch-print
     */
    public function batchPrint(): void
    {
        $this->requireRole(ROLE_ADMIN, ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $productIds = $this->input('product_ids', []);
        $size       = $this->input('size', 'medium');

        if (empty($productIds) || !is_array($productIds)) {
            $this->redirectWith(url('admin/products'), FLASH_ERROR, 'No products selected');
            return;
        }

        $products = [];
        foreach ($productIds as $id) {
            $product = $this->productModel->getByIdWithRelations((int)$id);
            if ($product && !empty($product['barcode'])) {
                $product['barcode_svg'] = $this->barcodeService->generateBarcodeSVG(
                    $product['barcode'], 280, 70
                );
                $products[] = $product;
            }
        }

        if (empty($products)) {
            $this->redirectWith(url('admin/products'), FLASH_ERROR, 'No valid barcodes found');
            return;
        }

        $this->render('admin/products/barcode-batch', [
            'title'    => 'Batch Barcode Print',
            'products' => $products,
            'size'     => $size,
            'layout'   => 'print',
        ]);
    }
}
