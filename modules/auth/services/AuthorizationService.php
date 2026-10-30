<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 7: Authentication
 * =========================================================
 * File        : modules/auth/services/AuthorizationService.php
 * Purpose     : Role-Based Access Control (RBAC) service.
 *               Checks whether the authenticated user has
 *               a specific role or permission code.
 *               Caches permissions in session per-request.
 * Dependencies: RoleModel, SessionManager.
 * How it works:
 *   - hasRole()       : compares session role name
 *   - hasPermission() : loads permission codes from DB
 *                       (cached in session for the request)
 *   - check()         : throws ForbiddenException if denied
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Auth\Services;

use AAM\Auth\Models\RoleModel;
use AAM\Core\SessionManager;
use AAM\Core\Logger;
use AAM\Exceptions\ForbiddenException;
use AAM\Exceptions\UnauthorizedException;

class AuthorizationService
{
    private const PERM_CACHE_KEY = 'aam_permissions';

    private RoleModel $roleModel;

    public function __construct()
    {
        $this->roleModel = new RoleModel();
    }

    // ─── Role Checks ─────────────────────────────────────────────────────────

    /**
     * Check if the authenticated user has a specific role.
     *
     * @param string|string[] $roles  Single role name or array of role names
     */
    public function hasRole(string|array $roles): bool
    {
        $userRole = SessionManager::userRole();
        if ($userRole === null) {
            return false;
        }
        $roles = (array) $roles;
        return in_array($userRole, $roles, true);
    }

    /**
     * Require authentication and optionally one of the given roles.
     * Throws UnauthorizedException or ForbiddenException.
     *
     * @param string|string[]|null $roles  null = only require auth
     * @throws UnauthorizedException
     * @throws ForbiddenException
     */
    public function require(string|array|null $roles = null): void
    {
        if (!SessionManager::isAuthenticated()) {
            throw new UnauthorizedException('Authentication required.');
        }

        if ($roles !== null && !$this->hasRole($roles)) {
            Logger::notice('Authorization denied — role check failed', [
                'user_id'        => SessionManager::userId(),
                'user_role'      => SessionManager::userRole(),
                'required_roles' => (array) $roles,
                'uri'            => $_SERVER['REQUEST_URI'] ?? '',
            ]);
            throw new ForbiddenException('You do not have permission to access this resource.');
        }
    }

    // ─── Permission Checks ────────────────────────────────────────────────────

    /**
     * Check if the current user has a specific permission code.
     * Permission codes format: module.entity.action
     * e.g. 'farm.harvest.create', 'admin.users.delete'
     */
    public function hasPermission(string $permissionCode): bool
    {
        $permissions = $this->loadPermissions();
        return in_array($permissionCode, $permissions, true);
    }

    /**
     * Check multiple permissions (user must have ALL of them).
     *
     * @param string[] $permissionCodes
     */
    public function hasAllPermissions(array $permissionCodes): bool
    {
        $permissions = $this->loadPermissions();
        foreach ($permissionCodes as $code) {
            if (!in_array($code, $permissions, true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check multiple permissions (user must have AT LEAST ONE).
     *
     * @param string[] $permissionCodes
     */
    public function hasAnyPermission(array $permissionCodes): bool
    {
        $permissions = $this->loadPermissions();
        foreach ($permissionCodes as $code) {
            if (in_array($code, $permissions, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Require a specific permission — throws ForbiddenException if denied.
     *
     * @throws UnauthorizedException
     * @throws ForbiddenException
     */
    public function requirePermission(string $permissionCode): void
    {
        if (!SessionManager::isAuthenticated()) {
            throw new UnauthorizedException('Authentication required.');
        }

        if (!$this->hasPermission($permissionCode)) {
            Logger::notice('Permission denied', [
                'user_id'    => SessionManager::userId(),
                'permission' => $permissionCode,
                'uri'        => $_SERVER['REQUEST_URI'] ?? '',
            ]);
            throw new ForbiddenException('Permission denied: ' . $permissionCode);
        }
    }

    /**
     * Get all permission codes for the current user.
     * Returns empty array if not authenticated.
     *
     * @return string[]
     */
    public function getPermissions(): array
    {
        return $this->loadPermissions();
    }

    // ─── Location Access ──────────────────────────────────────────────────────

    /**
     * Check if the current user is assigned to a specific location.
     * Farm Managers see only their farms; Salespersons their shops.
     *
     * @param string $locationType 'farm' | 'shop' | 'warehouse'
     * @param int    $locationId
     */
    public function canAccessLocation(string $locationType, int $locationId): bool
    {
        // Admins and General Managers can access all locations
        if ($this->hasRole([ROLE_ADMIN, ROLE_GENERAL_MANAGER])) {
            return true;
        }

        // Warehouse managers can access warehouse
        if ($locationType === LOCATION_WAREHOUSE && $this->hasRole(ROLE_WAREHOUSE_MANAGER)) {
            return true;
        }

        // Check user's assigned location
        $sessionLocType = SessionManager::get(SESSION_LOCATION_TYPE);
        $sessionLocId   = SessionManager::get(SESSION_LOCATION_ID);

        return $sessionLocType === $locationType && (int)$sessionLocId === $locationId;
    }

    // ─── Internal ─────────────────────────────────────────────────────────────

    /**
     * Load and cache permissions for the current user's role.
     *
     * @return string[]
     */
    private function loadPermissions(): array
    {
        // Return cached permissions if available
        $cached = SessionManager::get(self::PERM_CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        if (!SessionManager::isAuthenticated()) {
            return [];
        }

        $userData = SessionManager::get(SESSION_USER_DATA, []);
        $roleId   = (int)($userData['role_id'] ?? 0);

        if ($roleId === 0) {
            return [];
        }

        try {
            $permissions = $this->roleModel->getPermissionCodes($roleId);
            SessionManager::set(self::PERM_CACHE_KEY, $permissions);
            return $permissions;
        } catch (\Throwable $e) {
            Logger::error('Failed to load permissions: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Clear cached permissions (call after role change).
     */
    public function clearPermissionCache(): void
    {
        SessionManager::delete(self::PERM_CACHE_KEY);
    }
}
