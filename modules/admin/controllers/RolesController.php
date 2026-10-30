<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/admin/controllers/RolesController.php
 * Purpose : Read-only Roles & Permissions viewer.
 *           Lets Admin and General Manager inspect all roles,
 *           their descriptions, and the permissions granted
 *           to each role. Write operations are intentionally
 *           excluded — role permissions are managed through
 *           database migrations to prevent accidental
 *           privilege escalation.
 * Routes  :
 *   GET /admin/roles                  → index()
 *   GET /admin/roles/{id}             → show()
 * Access  : ROLE_ADMIN
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Admin\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;

class RolesController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * GET /admin/roles
     * List all roles with permission counts and user counts.
     */
    public function index(): void
    {
        $this->requireRole(ROLE_ADMIN);

        $db = Database::getInstance();

        $roles = [];
        try {
            $roles = $db->fetchAll(
                "SELECT
                    r.*,
                    COUNT(DISTINCT u.id)             AS user_count,
                    COUNT(DISTINCT rp.permission_id) AS permission_count
                 FROM roles r
                 LEFT JOIN users u  ON u.role_id = r.id
                     AND u.account_status != 'deleted'
                 LEFT JOIN role_permissions rp ON rp.role_id = r.id
                 WHERE r.is_active = 1
                 GROUP BY r.id
                 ORDER BY r.id ASC"
            );
        } catch (\Throwable $e) {
            \AAM\Core\Logger::error('RolesController::index() DB error: ' . $e->getMessage());
            $roles = [];
        }

        // Total permissions in system
        $totalPerms = 0;
        try {
            $totalPerms = (int) $db->fetchColumn("SELECT COUNT(*) FROM permissions WHERE is_active = 1");
        } catch (\Throwable) {}

        $this->render('admin.roles.index', [
            'pageTitle'   => 'Roles & Permissions',
            'roles'       => $roles,
            'totalPerms'  => $totalPerms,
        ]);
    }

    /**
     * GET /admin/roles/{id}
     * Show a single role with all its permissions grouped by module.
     * Permissions shown: all system permissions, with is_granted flag
     * indicating which ones are assigned to this role.
     */
    public function show(string $id): void
    {
        $this->requireRole(ROLE_ADMIN);

        $roleId = (int) $id;
        $db     = Database::getInstance();

        $role = null;
        try {
            $role = $db->fetchOne(
                "SELECT r.*,
                    COUNT(DISTINCT u.id) AS user_count
                 FROM roles r
                 LEFT JOIN users u ON u.role_id = r.id
                     AND u.account_status != 'deleted'
                 WHERE r.id = :id
                 GROUP BY r.id",
                ['id' => $roleId]
            );
        } catch (\Throwable) {}

        if (!$role) {
            $this->flash(FLASH_ERROR, 'Role not found.');
            $this->redirect('/admin/roles');
            return;
        }

        // All permissions in system grouped by module,
        // with is_granted=1 for those assigned to this role.
        $allPermissions = [];
        try {
            $rows = $db->fetchAll(
                "SELECT p.*,
                    CASE WHEN rp.role_id IS NOT NULL THEN 1 ELSE 0 END AS is_granted
                 FROM permissions p
                 LEFT JOIN role_permissions rp
                     ON rp.permission_id = p.id AND rp.role_id = :role_id
                 WHERE p.is_active = 1
                 ORDER BY p.module, p.label, p.code",
                ['role_id' => $roleId]
            );
            foreach ($rows as $row) {
                $allPermissions[$row['module']][] = $row;
            }
        } catch (\Throwable) {}

        // Users assigned to this role
        $users = [];
        try {
            $users = $db->fetchAll(
                "SELECT id, username, first_name, last_name, account_status, is_active, last_login_at
                 FROM users
                 WHERE role_id = :role_id AND account_status != 'deleted'
                 ORDER BY first_name, last_name
                 LIMIT 50",
                ['role_id' => $roleId]
            );
        } catch (\Throwable) {}

        $this->render('admin.roles.show', [
            'pageTitle'      => 'Role: ' . e($role['display_name']),
            'role'           => $role,
            'allPermissions' => $allPermissions,
            'users'          => $users,
        ]);
    }
}
