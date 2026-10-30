<?php
declare(strict_types=1);

namespace AAM\Organization\Services;

use AAM\Organization\Models\FarmModel;
use AAM\Organization\Models\AssignmentModel;
use AAM\Admin\Models\ActivityLogModel;

class FarmService
{
    private FarmModel        $farmModel;
    private ActivityLogModel $activityLog;

    public function __construct()
    {
        $this->farmModel   = new FarmModel();
        $this->activityLog = new ActivityLogModel();
    }

    public function createFarm(array $data, ?int $userId): array
    {
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            return ['success' => false, 'errors' => ['name' => 'Farm name is required']];
        }
        if (mb_strlen($name) < 2) {
            return ['success' => false, 'errors' => ['name' => 'Farm name must be at least 2 characters']];
        }
        if ($this->farmModel->nameExists($name)) {
            return ['success' => false, 'errors' => ['name' => 'A farm with this name already exists']];
        }

        // Generate unique code in backend — never from user input
        $farmCode = $this->farmModel->getNextCode();

        $insert = [
            'name'        => $name,
            'farm_name'   => $name,
            'farm_code'   => $farmCode,
            'farm_type'   => $data['farm_type'] ?? 'MIXED',
            'location'    => trim($data['location'] ?? ''),
            'description' => trim($data['description'] ?? ''),
            'is_active'   => (int)($data['is_active'] ?? 1),
            'created_by'  => $userId,
        ];

        $farmId = $this->farmModel->create($insert);
        if ($farmId) {
            $this->activityLog->log('farm_create', "Farm '{$name}' [{$farmCode}] created", 'farms', $farmId, $userId);
            return ['success' => true, 'farm_id' => $farmId, 'farm_code' => $farmCode];
        }

        return ['success' => false, 'errors' => ['general' => 'Failed to save farm to database']];
    }

    public function updateFarm(int $id, array $data, ?int $userId): array
    {
        $farm = $this->farmModel->find($id);
        if (!$farm) {
            return ['success' => false, 'errors' => ['general' => 'Farm not found']];
        }

        $name = trim($data['name'] ?? '');
        if ($name === '') {
            return ['success' => false, 'errors' => ['name' => 'Farm name is required']];
        }
        if ($this->farmModel->nameExists($name, $id)) {
            return ['success' => false, 'errors' => ['name' => 'A farm with this name already exists']];
        }

        $this->farmModel->update($id, [
            'name'        => $name,
            'farm_name'   => $name,
            'farm_type'   => $data['farm_type'] ?? $farm['farm_type'] ?? 'MIXED',
            'location'    => trim($data['location'] ?? ''),
            'description' => trim($data['description'] ?? ''),
            'is_active'   => (int)($data['is_active'] ?? 1),
            'updated_by'  => $userId,
        ]);
        $this->activityLog->log('farm_update', "Farm #{$id} updated", 'farms', $id, $userId);
        return ['success' => true];
    }

    public function deleteFarm(int $id, ?int $userId): array
    {
        $farm = $this->farmModel->find($id);
        if (!$farm) {
            return ['success' => false, 'error' => 'Farm not found'];
        }
        $this->farmModel->softDelete($id);
        $this->activityLog->log('farm_delete', "Farm #{$id} deleted", 'farms', $id, $userId);
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
