<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 7: Authentication
 * =========================================================
 * File        : modules/auth/models/RoleModel.php
 * Purpose     : Role data access model.
 *               Fetches roles, permissions, and role-
 *               permission mapping from the database.
 *               Used by AuthorizationService for RBAC.
 * Dependencies: AAM\Core\BaseModel, roles table,
 *               permissions table, role_permissions table.
 * How it works: Provides methods to get all permissions
 *               for a given role ID, check if a role has
 *               a specific permission, and list all roles.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Auth\Models;

use AAM\Core\BaseModel;

class RoleModel extends BaseModel
{
    protected string $table      = 'roles';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = true;

    protected array $fillable = [
        'name', 'display_name', 'description', 'is_active',
    ];

    /**
     * Get all active roles.
     *
     * @return array<int, array<string,mixed>>
     */
    public function getAllActive(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM roles WHERE is_active = 1 ORDER BY id ASC"
        );
    }

    /**
     * Find a role by its code name (e.g. 'administrator').
     *
     * @return array<string,mixed>|null
     */
    public function findByName(string $name): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM roles WHERE name = :name LIMIT 1",
            ['name' => $name]
        );
    }

    /**
     * Get all permission codes granted to a role.
     *
     * @return string[]
     */
    public function getPermissionCodes(int $roleId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT p.code
             FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = :role_id
             ORDER BY p.module, p.code",
            ['role_id' => $roleId]
        );

        return array_column($rows, 'code');
    }

    /**
     * Get all permissions grouped by module for a role.
     *
     * @return array<string, array<string,mixed>[]>
     */
    public function getPermissionsByModule(int $roleId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT p.*
             FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = :role_id
             ORDER BY p.module, p.code",
            ['role_id' => $roleId]
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['module']][] = $row;
        }
        return $grouped;
    }

    /**
     * Check if a role has a specific permission code.
     */
    public function hasPermission(int $roleId, string $permissionCode): bool
    {
        $count = (int) $this->db->fetchColumn(
            "SELECT COUNT(*)
             FROM role_permissions rp
             JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = :role_id AND p.code = :code",
            ['role_id' => $roleId, 'code' => $permissionCode]
        );
        return $count > 0;
    }

    /**
     * Get all permissions in the system.
     *
     * @return array<int, array<string,mixed>>
     */
    public function getAllPermissions(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM permissions ORDER BY module, code"
        );
    }
}
