<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/settings/services/SettingsService.php
 * Purpose : Business logic layer for system settings.
 *           Handles validation, type coercion, cache invalidation,
 *           and bulk save of grouped settings.
 * Deps    : SystemSettingModel
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Settings\Services;

use AAM\Settings\Models\SystemSettingModel;

class SettingsService
{
    private SystemSettingModel $model;

    public function __construct()
    {
        $this->model = new SystemSettingModel();
    }

    // ── Public read helpers ────────────────────────────────────────────────
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->model->getValue($key, $default);
    }

    public function getGroup(string $group): array
    {
        return $this->model->getGroup($group);
    }

    public function getAllGrouped(): array
    {
        return $this->model->getAllGrouped();
    }

    // ── Save grouped settings ─────────────────────────────────────────────
    /**
     * Validate and save a map of key => value for one settings group.
     * @param array<string,mixed> $raw   Raw POST data
     * @param string              $group Setting group name
     * @param int                 $uid   Acting user ID
     * @return array{success:bool, errors:array<string,string>}
     */
    public function saveGroup(array $raw, string $group, int $uid): array
    {
        $errors = [];

        match ($group) {
            'security' => $errors = $this->validateSecurity($raw),
            'backup'   => $errors = $this->validateBackup($raw),
            default    => [],
        };

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $this->model->bulkSet($raw, $uid);
        return ['success' => true, 'errors' => []];
    }

    // ── Validation helpers ────────────────────────────────────────────────
    private function validateSecurity(array $data): array
    {
        $errors = [];
        if (isset($data['security.max_login_attempts'])) {
            $v = (int) $data['security.max_login_attempts'];
            if ($v < 3 || $v > 20) {
                $errors['security.max_login_attempts'] = 'Must be 3–20 attempts.';
            }
        }
        if (isset($data['security.password_min_length'])) {
            $v = (int) $data['security.password_min_length'];
            if ($v < 6 || $v > 32) {
                $errors['security.password_min_length'] = 'Password length must be 6–32.';
            }
        }
        return $errors;
    }

    private function validateBackup(array $data): array
    {
        $errors = [];
        if (!empty($data['backup.retention_days'])) {
            $v = (int) $data['backup.retention_days'];
            if ($v < 1 || $v > 365) {
                $errors['backup.retention_days'] = 'Retention must be 1–365 days.';
            }
        }
        return $errors;
    }
}
