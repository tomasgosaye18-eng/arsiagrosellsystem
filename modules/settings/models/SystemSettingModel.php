<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 8D
 * =========================================================
 * File    : modules/settings/models/SystemSettingModel.php
 * Purpose : CRUD for system_settings key-value store.
 *           Supports typed retrieval (string, int, bool, json).
 *           Provides group-based bulk fetch for settings pages.
 * Deps    : BaseModel, Database
 * How     : SettingsService calls get(), set(), getGroup()
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Settings\Models;

use AAM\Core\BaseModel;

class SystemSettingModel extends BaseModel
{
    protected string $table      = 'system_settings';
    protected bool   $softDelete = false;
    protected array  $fillable   = [
        'setting_key', 'setting_value', 'data_type',
        'group_name', 'description', 'is_public', 'is_encrypted', 'updated_by',
    ];

    // ── Single value get ───────────────────────────────────────────────────
    /**
     * Get a typed setting value by key.
     */
    public function getValue(string $key, mixed $default = null): mixed
    {
        $row = $this->db->fetchOne(
            "SELECT `setting_value`, `data_type` FROM `system_settings`
             WHERE `setting_key` = :key LIMIT 1",
            ['key' => $key]
        );
        if (!$row) {
            return $default;
        }
        return $this->cast($row['setting_value'], $row['data_type']);
    }

    // ── Single value set ───────────────────────────────────────────────────
    /**
     * Upsert a setting value.
     */
    public function setValue(string $key, mixed $value, int $userId): bool
    {
        $existing = $this->db->fetchOne(
            "SELECT id FROM `system_settings` WHERE `setting_key` = :key LIMIT 1",
            ['key' => $key]
        );
        if ($existing) {
            $this->db->execute(
                "UPDATE `system_settings`
                 SET `setting_value` = :val, `updated_by` = :uid
                 WHERE `setting_key` = :key",
                ['val' => (string) $value, 'uid' => $userId, 'key' => $key]
            );
        } else {
            $this->db->execute(
                "INSERT INTO `system_settings`
                    (`setting_key`, `setting_value`, `updated_by`)
                 VALUES (:key, :val, :uid)",
                ['key' => $key, 'val' => (string) $value, 'uid' => $userId]
            );
        }
        return true;
    }

    // ── Group fetch ────────────────────────────────────────────────────────
    /**
     * Fetch all rows belonging to a group, keyed by setting_key.
     * @return array<string, array<string,mixed>>
     */
    public function getGroup(string $group): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM `system_settings` WHERE `group_name` = :g ORDER BY `id`",
            ['g' => $group]
        );
        $out = [];
        foreach ($rows as $r) {
            $r['typed_value'] = $this->cast($r['setting_value'], $r['data_type']);
            $out[$r['setting_key']] = $r;
        }
        return $out;
    }

    // ── Bulk save ──────────────────────────────────────────────────────────
    /**
     * Bulk upsert an associative array of key => value.
     * @param array<string,mixed> $data
     */
    public function bulkSet(array $data, int $userId): void
    {
        foreach ($data as $key => $value) {
            $this->setValue($key, $value, $userId);
        }
    }

    // ── Public settings for frontend ───────────────────────────────────────
    /**
     * Fetch only is_public=1 settings as key=>value map.
     * @return array<string,mixed>
     */
    public function getPublicSettings(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT `setting_key`, `setting_value`, `data_type`
             FROM `system_settings` WHERE `is_public` = 1"
        );
        $out = [];
        foreach ($rows as $r) {
            $out[$r['setting_key']] = $this->cast($r['setting_value'], $r['data_type']);
        }
        return $out;
    }

    // ── All groups ─────────────────────────────────────────────────────────
    /**
     * @return array<string, array<string, mixed>>
     */
    public function getAllGrouped(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM `system_settings` ORDER BY `group_name`, `id`"
        );
        $out = [];
        foreach ($rows as $r) {
            $r['typed_value'] = $this->cast($r['setting_value'], $r['data_type']);
            $out[$r['group_name']][$r['setting_key']] = $r;
        }
        return $out;
    }

    // ── Internal type casting ─────────────────────────────────────────────
    private function cast(mixed $value, string $type): mixed
    {
        if ($value === null) return null;
        return match ($type) {
            'integer' => (int) $value,
            'float'   => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json'    => json_decode((string) $value, true),
            default   => (string) $value,
        };
    }
}
