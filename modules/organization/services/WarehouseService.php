<?php
declare(strict_types=1);

namespace AAM\Organization\Services;

use AAM\Organization\Models\WarehouseModel;
use AAM\Organization\Models\AssignmentModel;
use AAM\Admin\Models\ActivityLogModel;

class WarehouseService
{
    private WarehouseModel   $warehouseModel;
    private ActivityLogModel $activityLog;

    public function __construct()
    {
        $this->warehouseModel = new WarehouseModel();
        $this->activityLog    = new ActivityLogModel();
    }

    public function createWarehouse(array $data, ?int $userId): array
    {
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            return ['success' => false, 'errors' => ['name' => 'Warehouse name is required']];
        }
        if (mb_strlen($name) < 2) {
            return ['success' => false, 'errors' => ['name' => 'Warehouse name must be at least 2 characters']];
        }
        if ($this->warehouseModel->nameExists($name)) {
            return ['success' => false, 'errors' => ['name' => 'A warehouse with this name already exists']];
        }

        $code = $this->warehouseModel->getNextCode();

        $insert = [
            'name'                       => $name,
            'warehouse_name'             => $name,
            'warehouse_code'             => $code,
            'warehouse_type'             => $data['warehouse_type'] ?? 'CENTRAL',
            'location'                   => trim($data['location'] ?? ''),
            'description'                => trim($data['description'] ?? ''),
            'is_active'                  => (int)($data['is_active'] ?? 1),
            'seed_distribution_enabled'  => (int)($data['seed_distribution_enabled'] ?? 0),
            'created_by'                 => $userId,
        ];

        $id = $this->warehouseModel->create($insert);
        if ($id) {
            $this->activityLog->log('warehouse_create', "Warehouse '{$name}' [{$code}] created", 'warehouses', $id, $userId);
            return ['success' => true, 'warehouse_id' => $id, 'warehouse_code' => $code];
        }

        return ['success' => false, 'errors' => ['general' => 'Failed to save warehouse to database']];
    }

    public function updateWarehouse(int $id, array $data, ?int $userId): array
    {
        $wh = $this->warehouseModel->find($id);
        if (!$wh) {
            return ['success' => false, 'errors' => ['general' => 'Warehouse not found']];
        }

        $name = trim($data['name'] ?? '');
        if ($name === '') {
            return ['success' => false, 'errors' => ['name' => 'Warehouse name is required']];
        }
        if ($this->warehouseModel->nameExists($name, $id)) {
            return ['success' => false, 'errors' => ['name' => 'A warehouse with this name already exists']];
        }

        $this->warehouseModel->update($id, [
            'name'                       => $name,
            'warehouse_name'             => $name,
            'warehouse_type'             => $data['warehouse_type'] ?? $wh['warehouse_type'] ?? 'CENTRAL',
            'location'                   => trim($data['location'] ?? ''),
            'description'                => trim($data['description'] ?? ''),
            'is_active'                  => (int)($data['is_active'] ?? 1),
            'seed_distribution_enabled'  => (int)($data['seed_distribution_enabled'] ?? 0),
            'updated_by'                 => $userId,
        ]);
        $this->activityLog->log('warehouse_update', "Warehouse #{$id} updated", 'warehouses', $id, $userId);
        return ['success' => true];
    }

    public function deleteWarehouse(int $id, ?int $userId): array
    {
        if (!$this->warehouseModel->find($id)) {
            return ['success' => false, 'error' => 'Warehouse not found'];
        }
        $this->warehouseModel->softDelete($id);
        $this->activityLog->log('warehouse_delete', "Warehouse #{$id} deleted", 'warehouses', $id, $userId);
        return ['success' => true];
    }

    public function getLocationAssignments(string $type, int $id): array
    {
        try {
            return (new AssignmentModel())->getActiveForLocation($type, $id);
        } catch (\Throwable) { return []; }
    }

    public function getLocationHistory(string $type, int $id): array
    {
        try {
            return (new AssignmentModel())->getHistoryForLocation($type, $id, 20);
        } catch (\Throwable) { return []; }
    }
}
