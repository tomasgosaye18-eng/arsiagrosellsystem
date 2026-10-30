<?php
/**
 * =========================================================
 * ORGANIZATION VALIDATION SERVICE
 * =========================================================
 * File    : modules/organization/services/OrgValidationService.php
 * Purpose : Centralized server-side validation for all
 *           organization entities: farms, warehouses, shops,
 *           and assignments.
 * Deps    : FarmModel, WarehouseModel, ShopModel
 * How     : Each validate*() returns ['valid'=>bool,'errors'=>[]].
 *           Models are lazy-loaded to avoid unnecessary DB calls.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Organization\Services;

use AAM\Organization\Models\FarmModel;
use AAM\Organization\Models\WarehouseModel;
use AAM\Organization\Models\ShopModel;

class OrgValidationService
{
    private FarmModel      $farmModel;
    private WarehouseModel $warehouseModel;
    private ShopModel      $shopModel;

    public function __construct()
    {
        $this->farmModel      = new FarmModel();
        $this->warehouseModel = new WarehouseModel();
        $this->shopModel      = new ShopModel();
    }

    // ─── Farm ─────────────────────────────────────────────────────────────────

    public function validateFarm(array $data, ?int $farmId = null): array
    {
        $errors = [];

        // Farm code
        if (empty($data['farm_code'])) {
            $errors['farm_code'] = 'Farm code is required';
        } elseif (!preg_match('/^[A-Z0-9_-]+$/', $data['farm_code'])) {
            $errors['farm_code'] = 'Farm code must contain only uppercase letters, numbers, hyphens, and underscores';
        } elseif (strlen($data['farm_code']) > 20) {
            $errors['farm_code'] = 'Farm code must not exceed 20 characters';
        } elseif ($this->farmModel->codeExists($data['farm_code'], $farmId)) {
            $errors['farm_code'] = 'Farm code already exists';
        }

        // Farm name
        if (empty($data['farm_name'])) {
            $errors['farm_name'] = 'Farm name is required';
        } elseif (strlen($data['farm_name']) < 3) {
            $errors['farm_name'] = 'Farm name must be at least 3 characters';
        } elseif (strlen($data['farm_name']) > 150) {
            $errors['farm_name'] = 'Farm name must not exceed 150 characters';
        } elseif ($this->farmModel->nameExists($data['farm_name'], $farmId)) {
            $errors['farm_name'] = 'Farm name already exists';
        }

        // Farm type
        $validTypes = array_keys($this->farmModel->getFarmTypes());
        if (!empty($data['farm_type']) && !in_array($data['farm_type'], $validTypes, true)) {
            $errors['farm_type'] = 'Invalid farm type selected';
        }

        // Phone
        if (!empty($data['phone']) && !$this->isValidEthiopianPhone($data['phone'])) {
            $errors['phone'] = 'Enter a valid Ethiopian phone number (e.g. +251911123456 or 0911123456)';
        }

        // Email
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address';
        }

        // GPS
        if (!empty($data['latitude']) && (!is_numeric($data['latitude']) || abs((float)$data['latitude']) > 90)) {
            $errors['latitude'] = 'Latitude must be a number between -90 and 90';
        }
        if (!empty($data['longitude']) && (!is_numeric($data['longitude']) || abs((float)$data['longitude']) > 180)) {
            $errors['longitude'] = 'Longitude must be a number between -180 and 180';
        }

        // Land area
        if (!empty($data['land_area_ha']) && (!is_numeric($data['land_area_ha']) || (float)$data['land_area_ha'] < 0)) {
            $errors['land_area_ha'] = 'Land area must be a positive number';
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    // ─── Warehouse ────────────────────────────────────────────────────────────

    public function validateWarehouse(array $data, ?int $warehouseId = null): array
    {
        $errors = [];

        if (empty($data['warehouse_code'])) {
            $errors['warehouse_code'] = 'Warehouse code is required';
        } elseif (!preg_match('/^[A-Z0-9_-]+$/', $data['warehouse_code'])) {
            $errors['warehouse_code'] = 'Warehouse code must contain only uppercase letters, numbers, hyphens, and underscores';
        } elseif (strlen($data['warehouse_code']) > 20) {
            $errors['warehouse_code'] = 'Warehouse code must not exceed 20 characters';
        } elseif ($this->warehouseModel->codeExists($data['warehouse_code'], $warehouseId)) {
            $errors['warehouse_code'] = 'Warehouse code already exists';
        }

        if (empty($data['warehouse_name'])) {
            $errors['warehouse_name'] = 'Warehouse name is required';
        } elseif (strlen($data['warehouse_name']) < 3) {
            $errors['warehouse_name'] = 'Warehouse name must be at least 3 characters';
        } elseif (strlen($data['warehouse_name']) > 150) {
            $errors['warehouse_name'] = 'Warehouse name must not exceed 150 characters';
        } elseif ($this->warehouseModel->nameExists($data['warehouse_name'], $warehouseId)) {
            $errors['warehouse_name'] = 'Warehouse name already exists';
        }

        $validTypes = array_keys($this->warehouseModel->getWarehouseTypes());
        if (!empty($data['warehouse_type']) && !in_array($data['warehouse_type'], $validTypes, true)) {
            $errors['warehouse_type'] = 'Invalid warehouse type';
        }

        if (!empty($data['phone']) && !$this->isValidEthiopianPhone($data['phone'])) {
            $errors['phone'] = 'Enter a valid Ethiopian phone number';
        }
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address';
        }
        if (!empty($data['capacity_tons']) && (!is_numeric($data['capacity_tons']) || (float)$data['capacity_tons'] < 0)) {
            $errors['capacity_tons'] = 'Capacity must be a positive number';
        }
        if (!empty($data['capacity_sqm']) && (!is_numeric($data['capacity_sqm']) || (float)$data['capacity_sqm'] < 0)) {
            $errors['capacity_sqm'] = 'Floor area must be a positive number';
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    // ─── Shop ─────────────────────────────────────────────────────────────────

    public function validateShop(array $data, ?int $shopId = null): array
    {
        $errors = [];

        if (empty($data['shop_code'])) {
            $errors['shop_code'] = 'Shop code is required';
        } elseif (!preg_match('/^[A-Z0-9_-]+$/', $data['shop_code'])) {
            $errors['shop_code'] = 'Shop code must contain only uppercase letters, numbers, hyphens, and underscores';
        } elseif ($this->shopModel->codeExists($data['shop_code'], $shopId)) {
            $errors['shop_code'] = 'Shop code already exists';
        }

        if (empty($data['shop_name'])) {
            $errors['shop_name'] = 'Shop name is required';
        } elseif (strlen($data['shop_name']) < 3) {
            $errors['shop_name'] = 'Shop name must be at least 3 characters';
        } elseif ($this->shopModel->nameExists($data['shop_name'], $shopId)) {
            $errors['shop_name'] = 'Shop name already exists';
        }

        $validTypes = array_keys($this->shopModel->getShopTypes());
        if (!empty($data['shop_type']) && !in_array($data['shop_type'], $validTypes, true)) {
            $errors['shop_type'] = 'Invalid shop type';
        }

        if (!empty($data['phone']) && !$this->isValidEthiopianPhone($data['phone'])) {
            $errors['phone'] = 'Enter a valid Ethiopian phone number';
        }
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address';
        }

        // Opening / closing time
        if (!empty($data['opening_time']) && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data['opening_time'])) {
            $errors['opening_time'] = 'Enter a valid time (HH:MM)';
        }
        if (!empty($data['closing_time']) && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data['closing_time'])) {
            $errors['closing_time'] = 'Enter a valid time (HH:MM)';
        }
        if (!empty($data['opening_time']) && !empty($data['closing_time'])) {
            if (strtotime($data['opening_time']) >= strtotime($data['closing_time'])) {
                $errors['closing_time'] = 'Closing time must be after opening time';
            }
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    // ─── Assignment ───────────────────────────────────────────────────────────

    public function validateAssignment(array $data): array
    {
        $errors = [];
        if (empty($data['user_id'])) $errors['user_id'] = 'User is required';
        if (empty($data['location_type']) || !in_array($data['location_type'], ['farm','warehouse','shop'], true)) {
            $errors['location_type'] = 'Location type is required';
        }
        if (empty($data['location_id'])) $errors['location_id'] = 'Location is required';
        if (empty($data['assignment_role'])) $errors['assignment_role'] = 'Role is required';
        return ['valid' => empty($errors), 'errors' => $errors];
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function isValidEthiopianPhone(string $phone): bool
    {
        // Accepts: +251XXXXXXXXX, 0XXXXXXXXX (9-10 digit local), or +1... etc.
        return (bool)preg_match('/^(\+251[0-9]{9}|0[0-9]{9,10}|\+?[0-9]{7,15})$/', preg_replace('/[\s\-()]/', '', $phone));
    }
}
