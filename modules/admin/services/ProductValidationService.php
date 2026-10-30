<?php
/**
 * =========================================================
 * PRODUCT VALIDATION SERVICE
 * =========================================================
 * File: app/modules/admin/services/ProductValidationService.php
 * Purpose: Centralized validation for product management
 * Dependencies: CategoryModel, UnitModel, ProductModel
 * 
 * Provides comprehensive validation for categories, units, and products
 * with reusable validation rules and error messaging.
 * 
 * @package AAM
 * @subpackage Admin\Services
 * @version 1.0.0 (Phase 8B)
 * =========================================================
 */

namespace AAM\Admin\Services;

use AAM\Admin\Models\CategoryModel;
use AAM\Admin\Models\UnitModel;
use AAM\Admin\Models\ProductModel;

class ProductValidationService
{
    private CategoryModel $categoryModel;
    private UnitModel $unitModel;
    private ProductModel $productModel;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->categoryModel = new CategoryModel();
        $this->unitModel = new UnitModel();
        $this->productModel = new ProductModel();
    }
    
    /**
     * Validate category data
     * 
     * @param array $data Input data
     * @param int|null $categoryId Exclude ID (for updates)
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateCategory(array $data, ?int $categoryId = null): array
    {
        $errors = [];

        // Category name validation
        if (empty($data['category_name'])) {
            $errors['category_name'] = 'Category name is required';
        } elseif (strlen($data['category_name']) < 2) {
            $errors['category_name'] = 'Category name must be at least 2 characters';
        } elseif (strlen($data['category_name']) > 100) {
            $errors['category_name'] = 'Category name must not exceed 100 characters';
        } elseif ($this->categoryModel->nameExists($data['category_name'], $categoryId)) {
            $errors['category_name'] = 'Category name already exists';
        }

        // Description validation
        if (!empty($data['description']) && strlen($data['description']) > 1000) {
            $errors['description'] = 'Description must not exceed 1000 characters';
        }

        // Display order validation
        if (isset($data['display_order']) && !is_numeric($data['display_order'])) {
            $errors['display_order'] = 'Display order must be a number';
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }
    
    /**
     * Validate unit data
     * 
     * @param array $data Input data
     * @param int|null $unitId Exclude ID (for updates)
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateUnit(array $data, ?int $unitId = null): array
    {
        $errors = [];

        // Unit name validation (maps to DB `name` column)
        $name = $data['unit_name'] ?? $data['name'] ?? '';
        if (empty($name)) {
            $errors['unit_name'] = 'Unit name is required';
        } elseif (strlen($name) < 2) {
            $errors['unit_name'] = 'Unit name must be at least 2 characters';
        } elseif (strlen($name) > 60) {
            $errors['unit_name'] = 'Unit name must not exceed 60 characters';
        } elseif ($this->unitModel->nameExists($name, $unitId)) {
            $errors['unit_name'] = 'Unit name already exists';
        }

        // Abbreviation validation
        if (empty($data['abbreviation'])) {
            $errors['abbreviation'] = 'Abbreviation is required';
        } elseif (strlen($data['abbreviation']) > 20) {
            $errors['abbreviation'] = 'Abbreviation must not exceed 20 characters';
        }

        // Description validation (optional)
        if (!empty($data['description']) && strlen($data['description']) > 255) {
            $errors['description'] = 'Description must not exceed 255 characters';
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }
    
    /**
     * Validate product data
     * 
     * @param array $data Input data
     * @param int|null $productId Exclude ID (for updates)
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateProduct(array $data, ?int $productId = null): array
    {
        $errors = [];
        
        // Product code validation
        if (empty($data['product_code'])) {
            $errors['product_code'] = 'Product code is required';
        } elseif (!preg_match('/^[A-Z0-9_-]+$/', $data['product_code'])) {
            $errors['product_code'] = 'Product code must contain only uppercase letters, numbers, hyphens, and underscores';
        } elseif (strlen($data['product_code']) > 50) {
            $errors['product_code'] = 'Product code must not exceed 50 characters';
        } elseif ($this->productModel->codeExists($data['product_code'], $productId)) {
            $errors['product_code'] = 'Product code already exists';
        }
        
        // Barcode validation
        if (!empty($data['barcode'])) {
            if (!preg_match('/^[0-9A-Z-]+$/', $data['barcode'])) {
                $errors['barcode'] = 'Barcode must contain only numbers, uppercase letters, and hyphens';
            } elseif (strlen($data['barcode']) > 50) {
                $errors['barcode'] = 'Barcode must not exceed 50 characters';
            } elseif ($this->productModel->barcodeExists($data['barcode'], $productId)) {
                $errors['barcode'] = 'Barcode already exists';
            }
        }
        
        // Product name validation
        if (empty($data['product_name'])) {
            $errors['product_name'] = 'Product name is required';
        } elseif (strlen($data['product_name']) < 2) {
            $errors['product_name'] = 'Product name must be at least 2 characters';
        } elseif (strlen($data['product_name']) > 200) {
            $errors['product_name'] = 'Product name must not exceed 200 characters';
        }
        
        // Category validation
        if (empty($data['category_id'])) {
            $errors['category_id'] = 'Category is required';
        } elseif (!is_numeric($data['category_id'])) {
            $errors['category_id'] = 'Invalid category';
        }
        
        // Unit validation
        if (empty($data['unit_id'])) {
            $errors['unit_id'] = 'Unit of measurement is required';
        } elseif (!is_numeric($data['unit_id'])) {
            $errors['unit_id'] = 'Invalid unit';
        }
        
        // Price validations
        if (isset($data['cost_price'])) {
            if (!is_numeric($data['cost_price'])) {
                $errors['cost_price'] = 'Cost price must be a number';
            } elseif ($data['cost_price'] < 0) {
                $errors['cost_price'] = 'Cost price cannot be negative';
            }
        }
        
        if (isset($data['selling_price'])) {
            if (!is_numeric($data['selling_price'])) {
                $errors['selling_price'] = 'Selling price must be a number';
            } elseif ($data['selling_price'] < 0) {
                $errors['selling_price'] = 'Selling price cannot be negative';
            }
        }
        
        // Stock level validations
        if (isset($data['minimum_stock']) && !is_numeric($data['minimum_stock'])) {
            $errors['minimum_stock'] = 'Minimum stock must be a number';
        }
        
        if (isset($data['maximum_stock']) && !is_numeric($data['maximum_stock'])) {
            $errors['maximum_stock'] = 'Maximum stock must be a number';
        }
        
        if (isset($data['reorder_level']) && !is_numeric($data['reorder_level'])) {
            $errors['reorder_level'] = 'Reorder level must be a number';
        }
        
        if (isset($data['reorder_quantity']) && $data['reorder_quantity'] !== null) {
            if (!is_numeric($data['reorder_quantity'])) {
                $errors['reorder_quantity'] = 'Reorder quantity must be a number';
            } elseif ($data['reorder_quantity'] <= 0) {
                $errors['reorder_quantity'] = 'Reorder quantity must be greater than zero';
            }
        }
        
        // Expiry validations
        if (isset($data['shelf_life_days']) && $data['shelf_life_days'] !== null) {
            if (!is_numeric($data['shelf_life_days'])) {
                $errors['shelf_life_days'] = 'Shelf life must be a number';
            } elseif ($data['shelf_life_days'] <= 0) {
                $errors['shelf_life_days'] = 'Shelf life must be greater than zero';
            }
        }
        
        // Product status validation
        $validStatuses = ['active', 'inactive', 'discontinued', 'out_of_stock'];
        if (!empty($data['product_status']) && !in_array($data['product_status'], $validStatuses)) {
            $errors['product_status'] = 'Invalid product status';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate image upload
     * 
     * @param array $file $_FILES array element
     * @param int $maxSizeMB Maximum file size in MB
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateImage(array $file, int $maxSizeMB = 5): array
    {
        $errors = [];
        
        // Check if file was uploaded
        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errors['file'] = 'File is too large';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errors['file'] = 'File was only partially uploaded';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errors['file'] = 'No file was uploaded';
                    break;
                default:
                    $errors['file'] = 'File upload failed';
            }
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Validate file size
        $maxSize = $maxSizeMB * 1024 * 1024; // Convert MB to bytes
        if ($file['size'] > $maxSize) {
            $errors['file'] = "File size must not exceed {$maxSizeMB}MB";
        }
        
        // Validate MIME type
        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!in_array($file['type'], $allowedMimes)) {
            $errors['file'] = 'Only JPEG, PNG, and WEBP images are allowed';
        }
        
        // Validate file extension
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions)) {
            $errors['file'] = 'Invalid file extension';
        }
        
        // Validate image dimensions (optional - can be used for min/max checks)
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            $errors['file'] = 'File is not a valid image';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate price change data
     * 
     * @param array $data Price change data
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validatePriceChange(array $data): array
    {
        $errors = [];
        
        // Price type validation
        if (empty($data['price_type']) || !in_array($data['price_type'], ['COST', 'SELLING'])) {
            $errors['price_type'] = 'Invalid price type';
        }
        
        // New price validation
        if (!isset($data['new_price'])) {
            $errors['new_price'] = 'New price is required';
        } elseif (!is_numeric($data['new_price'])) {
            $errors['new_price'] = 'New price must be a number';
        } elseif ($data['new_price'] < 0) {
            $errors['new_price'] = 'New price cannot be negative';
        }
        
        // Effective date validation
        if (empty($data['effective_date'])) {
            $errors['effective_date'] = 'Effective date is required';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['effective_date'])) {
            $errors['effective_date'] = 'Invalid date format (use YYYY-MM-DD)';
        }
        
        // Reason validation (optional but recommended)
        if (!empty($data['reason']) && strlen($data['reason']) > 255) {
            $errors['reason'] = 'Reason must not exceed 255 characters';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

