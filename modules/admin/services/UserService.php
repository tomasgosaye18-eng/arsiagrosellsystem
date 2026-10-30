<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 8A
 * =========================================================
 * File        : modules/admin/services/UserService.php
 * Purpose     : User management orchestration service.
 *               Coordinates user CRUD, status changes,
 *               duplicate checks, activity logging, and
 *               password resets for the admin module.
 * Dependencies: UserModel, RoleModel, ActivityLogModel,
 *               PasswordUtility, PasswordService, Logger.
 * How it works:
 *   createUser()      → validate uniqueness → hash pw → insert → log
 *   updateUser()      → validate → update → sync session if self → log
 *   toggleStatus()    → flip is_active / account_status → log
 *   softDeleteUser()  → mark deleted → log
 *   restoreUser()     → mark active → log
 *   unlockUser()      → reset lock flags → log
 *   adminResetPw()    → generate temp pw → hash → store → log
 *   searchUsers()     → paginateWithRole proxy with extended filters
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Admin\Services;

use AAM\Auth\Models\UserModel;
use AAM\Auth\Models\RoleModel;
use AAM\Admin\Models\ActivityLogModel;
use AAM\Auth\Services\PasswordService;
use AAM\Security\PasswordUtility;
use AAM\Core\Database;
use AAM\Core\Logger;
use AAM\Core\SessionManager;

class UserService
{
    private UserModel        $userModel;
    private RoleModel        $roleModel;
    private PasswordService  $passwordService;
    private ActivityLogModel $activityModel;
    private Database         $db;

    public function __construct()
    {
        $this->userModel       = new UserModel();
        $this->roleModel       = new RoleModel();
        $this->passwordService = new PasswordService();
        $this->activityModel   = new ActivityLogModel();
        $this->db              = Database::getInstance();
    }

    // ─── Create User ──────────────────────────────────────────────────────────

    /**
     * Create a new user account.
     *
     * @param array<string,mixed> $data  Validated form data
     * @return array{success: bool, message: string, user_id: int|null, errors: array}
     */
    public function createUser(array $data): array
    {
        // Uniqueness checks
        if ($this->usernameExists($data['username'])) {
            return $this->fail('Username already exists. Please choose a different username.', ['username' => ['Username is already taken.']]);
        }

        if (!empty($data['email']) && $this->emailExists($data['email'])) {
            return $this->fail('Email address is already registered.', ['email' => ['This email is already in use.']]);
        }

        if (!empty($data['employee_id']) && $this->employeeIdExists($data['employee_id'])) {
            return $this->fail('Employee ID already exists.', ['employee_id' => ['This Employee ID is already assigned.']]);
        }

        // Password strength
        $pwValidation = PasswordUtility::validate($data['password']);
        if (!$pwValidation['valid']) {
            return $this->fail('Password does not meet requirements.', ['password' => $pwValidation['errors']]);
        }

        // Hash and build insert array
        $hash = PasswordUtility::hash($data['password']);

        $insertData = [
            'employee_id'          => $data['employee_id']  ?: null,
            'role_id'              => (int) $data['role_id'],
            'username'             => $data['username'],
            'password_hash'        => $hash,
            'first_name'           => $data['first_name'],
            'middle_name'          => $data['middle_name']  ?: null,
            'last_name'            => $data['last_name'],
            'gender'               => $data['gender']       ?: null,
            'email'                => $data['email']        ?: null,
            'phone'                => $data['phone']        ?: null,
            'is_active'            => 1,
            'account_status'       => 'active',
            'must_change_password' => 1,
            'created_by'           => SessionManager::userId(),
        ];

        $userId = $this->userModel->create($insertData);

        // Assign location if provided
        if (!empty($data['location_type']) && !empty($data['location_id'])) {
            $this->assignLocation($userId, $data['location_type'], (int) $data['location_id']);
        }

        // Activity log
        $actorName = SessionManager::get(SESSION_USER_NAME, 'System');
        $this->activityModel->log(
            ActivityLogModel::ACTION_USER_CREATED,
            "{$actorName} created user account: {$data['username']}",
            'users',
            $userId
        );

        Logger::security('Admin created user', [
            'admin_id'   => SessionManager::userId(),
            'new_user'   => $userId,
            'username'   => $data['username'],
        ]);

        return ['success' => true, 'message' => "User '{$data['username']}' created successfully.", 'user_id' => $userId, 'errors' => []];
    }

    // ─── Update User ──────────────────────────────────────────────────────────

    /**
     * Update an existing user account.
     *
     * @param int                 $userId
     * @param array<string,mixed> $data
     * @return array{success: bool, message: string, errors: array}
     */
    public function updateUser(int $userId, array $data): array
    {
        $user = $this->userModel->find($userId);
        if (!$user) {
            return $this->fail('User not found.');
        }

        // Email uniqueness (excluding self)
        if (!empty($data['email']) && $this->emailExists($data['email'], $userId)) {
            return $this->fail('Email already in use.', ['email' => ['This email belongs to another account.']]);
        }

        // Employee ID uniqueness (excluding self)
        if (!empty($data['employee_id']) && $this->employeeIdExists($data['employee_id'], $userId)) {
            return $this->fail('Employee ID already assigned.', ['employee_id' => ['This Employee ID is already in use.']]);
        }

        $updateData = [
            'employee_id' => $data['employee_id'] ?: null,
            'first_name'  => $data['first_name'],
            'middle_name' => $data['middle_name'] ?: null,
            'last_name'   => $data['last_name'],
            'gender'      => $data['gender']      ?: null,
            'email'       => $data['email']        ?: null,
            'phone'       => $data['phone']        ?: null,
            'role_id'     => (int) $data['role_id'],
        ];

        $this->userModel->update($userId, $updateData);

        // Update location assignment
        if (isset($data['location_type'])) {
            $this->updateLocationAssignment(
                $userId,
                $data['location_type'] ?: null,
                !empty($data['location_id']) ? (int) $data['location_id'] : null
            );
        }

        // Activity log
        $actorName = SessionManager::get(SESSION_USER_NAME, 'System');
        $this->activityModel->log(
            ActivityLogModel::ACTION_USER_UPDATED,
            "{$actorName} updated user: {$user['username']}",
            'users',
            $userId
        );

        Logger::security('Admin updated user', [
            'admin_id'    => SessionManager::userId(),
            'target_user' => $userId,
        ]);

        return ['success' => true, 'message' => 'User updated successfully.', 'errors' => []];
    }

    // ─── Activate / Deactivate ────────────────────────────────────────────────

    /**
     * Toggle user active/inactive status.
     *
     * @return array{success: bool, message: string}
     */
    public function toggleStatus(int $userId, int $adminId): array
    {
        if ($userId === $adminId) {
            return $this->fail('You cannot change your own activation status.');
        }

        $user = $this->userModel->find($userId);
        if (!$user) {
            return $this->fail('User not found.');
        }

        $isCurrentlyActive = (int) $user['is_active'] === 1 && $user['account_status'] === 'active';

        $newActive  = $isCurrentlyActive ? 0 : 1;
        $newStatus  = $isCurrentlyActive ? 'inactive' : 'active';
        $action     = $isCurrentlyActive ? ActivityLogModel::ACTION_USER_DEACTIVATED : ActivityLogModel::ACTION_USER_ACTIVATED;
        $label      = $isCurrentlyActive ? 'deactivated' : 'activated';

        $this->userModel->update($userId, [
            'is_active'      => $newActive,
            'account_status' => $newStatus,
        ]);

        $actorName = SessionManager::get(SESSION_USER_NAME, 'System');
        $this->activityModel->log(
            $action,
            "{$actorName} {$label} user: {$user['username']}",
            'users',
            $userId
        );

        Logger::security("Admin {$label} user", [
            'admin_id'    => $adminId,
            'target_user' => $userId,
            'new_status'  => $newStatus,
        ]);

        return ['success' => true, 'message' => "User account {$label} successfully."];
    }

    // ─── Soft Delete / Restore ────────────────────────────────────────────────

    /**
     * Soft-delete a user (set account_status = 'deleted', is_active = 0).
     *
     * @return array{success: bool, message: string}
     */
    public function softDeleteUser(int $userId, int $adminId): array
    {
        if ($userId === $adminId) {
            return $this->fail('You cannot delete your own account.');
        }

        $user = $this->userModel->find($userId);
        if (!$user) {
            return $this->fail('User not found.');
        }

        $this->userModel->update($userId, [
            'is_active'      => 0,
            'account_status' => 'deleted',
        ]);

        $actorName = SessionManager::get(SESSION_USER_NAME, 'System');
        $this->activityModel->log(
            ActivityLogModel::ACTION_USER_DELETED,
            "{$actorName} deleted user: {$user['username']}",
            'users',
            $userId
        );

        Logger::security('Admin soft-deleted user', [
            'admin_id'    => $adminId,
            'target_user' => $userId,
        ]);

        return ['success' => true, 'message' => "User '{$user['username']}' has been deleted."];
    }

    /**
     * Restore a soft-deleted user.
     *
     * @return array{success: bool, message: string}
     */
    public function restoreUser(int $userId): array
    {
        $user = $this->userModel->find($userId);
        if (!$user) {
            return $this->fail('User not found.');
        }

        $this->userModel->update($userId, [
            'is_active'      => 1,
            'account_status' => 'active',
        ]);

        $actorName = SessionManager::get(SESSION_USER_NAME, 'System');
        $this->activityModel->log(
            ActivityLogModel::ACTION_USER_ACTIVATED,
            "{$actorName} restored user: {$user['username']}",
            'users',
            $userId
        );

        return ['success' => true, 'message' => "User '{$user['username']}' has been restored."];
    }

    // ─── Unlock ───────────────────────────────────────────────────────────────

    /**
     * Unlock a locked user account.
     *
     * @return array{success: bool, message: string}
     */
    public function unlockUser(int $userId): array
    {
        $user = $this->userModel->find($userId);
        if (!$user) {
            return $this->fail('User not found.');
        }

        $this->userModel->unlockAccount($userId);

        $actorName = SessionManager::get(SESSION_USER_NAME, 'System');
        $this->activityModel->log(
            ActivityLogModel::ACTION_USER_UNLOCKED,
            "{$actorName} unlocked account: {$user['username']}",
            'users',
            $userId
        );

        Logger::security('Admin unlocked user', [
            'admin_id'    => SessionManager::userId(),
            'target_user' => $userId,
        ]);

        return ['success' => true, 'message' => 'Account unlocked successfully.'];
    }

    // ─── Password Reset ───────────────────────────────────────────────────────

    /**
     * Admin-initiated password reset — generates a secure temp password.
     *
     * @return array{success: bool, message: string, temp_password: string|null}
     */
    public function adminResetPassword(int $userId): array
    {
        $user = $this->userModel->find($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found.', 'temp_password' => null];
        }

        $tempPassword = PasswordUtility::generate(12);
        $result       = $this->passwordService->adminResetPassword($userId, $tempPassword);

        if (!$result['success']) {
            return ['success' => false, 'message' => $result['message'], 'temp_password' => null];
        }

        $actorName = SessionManager::get(SESSION_USER_NAME, 'System');
        $this->activityModel->log(
            ActivityLogModel::ACTION_PASSWORD_RESET,
            "{$actorName} reset password for: {$user['username']}",
            'users',
            $userId
        );

        Logger::security('Admin reset password', [
            'admin_id'    => SessionManager::userId(),
            'target_user' => $userId,
        ]);

        return ['success' => true, 'message' => 'Password reset successfully.', 'temp_password' => $tempPassword];
    }

    // ─── Location Assignment ──────────────────────────────────────────────────

    /**
     * Assign a user to a location (farm / warehouse / shop).
     */
    public function assignLocation(int $userId, string $locationType, int $locationId): void
    {
        try {
            // Remove existing assignment
            $this->db->execute(
                "DELETE FROM user_location_assignments WHERE user_id = :uid",
                ['uid' => $userId]
            );

            $this->db->execute(
                "INSERT INTO user_location_assignments
                    (user_id, location_type, location_id, assigned_at)
                 VALUES (:uid, :type, :loc_id, NOW())",
                ['uid' => $userId, 'type' => $locationType, 'loc_id' => $locationId]
            );
        } catch (\Throwable $e) {
            Logger::warning('Location assignment failed: ' . $e->getMessage());
        }
    }

    /**
     * Update or clear a user's location assignment.
     */
    public function updateLocationAssignment(int $userId, ?string $locationType, ?int $locationId): void
    {
        try {
            $this->db->execute(
                "DELETE FROM user_location_assignments WHERE user_id = :uid",
                ['uid' => $userId]
            );

            if ($locationType && $locationId) {
                $this->assignLocation($userId, $locationType, $locationId);
            }
        } catch (\Throwable $e) {
            Logger::warning('Location assignment update failed: ' . $e->getMessage());
        }
    }

    // ─── Queries ──────────────────────────────────────────────────────────────

    /**
     * Paginated user search with extended Phase 8A filters.
     *
     * @return array{data: array, total: int, page: int, perPage: int, totalPages: int}
     */
    public function searchUsers(
        int    $page       = 1,
        int    $perPage    = 20,
        string $search     = '',
        string $roleFilter = '',
        string $statusFilter = '',
        string $sortBy     = 'created_at',
        string $sortDir    = 'DESC'
    ): array {
        $allowedSorts = ['id', 'username', 'first_name', 'last_name', 'created_at', 'last_login_at', 'account_status'];
        $allowedDirs  = ['ASC', 'DESC'];

        $sortBy  = in_array($sortBy,  $allowedSorts, true) ? $sortBy  : 'created_at';
        $sortDir = in_array(strtoupper($sortDir), $allowedDirs, true) ? strtoupper($sortDir) : 'DESC';

        $page   = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $params = [];
        $wheres = ["u.account_status != 'deleted'"];

        if ($search !== '') {
            $wheres[] = "(u.username LIKE :s1 OR u.first_name LIKE :s2 OR u.last_name LIKE :s3 OR u.phone LIKE :s4 OR u.email LIKE :s5 OR u.employee_id LIKE :s6)";
            $params['s1'] = "%{$search}%";
            $params['s2'] = "%{$search}%";
            $params['s3'] = "%{$search}%";
            $params['s4'] = "%{$search}%";
            $params['s5'] = "%{$search}%";
            $params['s6'] = "%{$search}%";
        }

        if ($roleFilter !== '') {
            $wheres[]       = 'r.name = :role';
            $params['role'] = $roleFilter;
        }

        if ($statusFilter !== '') {
            if ($statusFilter === 'locked') {
                $wheres[] = 'u.is_locked = 1';
            } else {
                $wheres[]          = 'u.account_status = :status';
                $params['status']  = $statusFilter;
            }
        }

        $whereClause = 'WHERE ' . implode(' AND ', $wheres);

        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id {$whereClause}",
            $params
        );

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $data = $this->db->fetchAll(
            "SELECT u.id, u.employee_id, u.username,
                    u.first_name, u.middle_name, u.last_name,
                    u.gender, u.email, u.phone, u.profile_photo,
                    u.is_active, u.account_status, u.is_locked,
                    u.failed_login_attempts, u.must_change_password,
                    u.last_login_at, u.created_at,
                    r.name AS role_name, r.display_name AS role_display_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             {$whereClause}
             ORDER BY u.{$sortBy} {$sortDir}
             LIMIT :limit OFFSET :offset",
            $params
        );

        return [
            'data'       => $data,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int) ceil($total / max(1, $perPage)),
            'from'       => $total > 0 ? $offset + 1 : 0,
            'to'         => min($offset + $perPage, $total),
        ];
    }

    /**
     * Get full user detail with role, permissions, and location for the view page.
     *
     * @return array<string,mixed>|null
     */
    public function getUserDetail(int $userId): ?array
    {
        $user = $this->userModel->findWithRole($userId);
        if (!$user) {
            return null;
        }

        // Permissions grouped by module
        $user['permissions'] = $this->roleModel->getPermissionsByModule((int) $user['role_id']);

        // Location assignment
        try {
            $location = $this->db->fetchOne(
                "SELECT ula.*, 
                    COALESCE(f.name, w.name, s.name) AS location_name
                 FROM user_location_assignments ula
                 LEFT JOIN farms f      ON ula.location_type = 'farm'      AND f.id = ula.location_id
                 LEFT JOIN warehouses w ON ula.location_type = 'warehouse' AND w.id = ula.location_id
                 LEFT JOIN shops s      ON ula.location_type = 'shop'      AND s.id = ula.location_id
                 WHERE ula.user_id = :uid
                 LIMIT 1",
                ['uid' => $userId]
            );
            $user['location'] = $location;
        } catch (\Throwable) {
            $user['location'] = null;
        }

        return $user;
    }

    /**
     * Get all available location options for the create/edit form.
     *
     * @return array{farms: array, warehouses: array, shops: array}
     */
    public function getLocationOptions(): array
    {
        $farms      = $this->safeQuery("SELECT id, name FROM farms WHERE is_active = 1 ORDER BY name");
        $warehouses = $this->safeQuery("SELECT id, name FROM warehouses WHERE is_active = 1 ORDER BY name");
        $shops      = $this->safeQuery("SELECT id, name FROM shops WHERE is_active = 1 ORDER BY name");

        return compact('farms', 'warehouses', 'shops');
    }

    // ─── Internal Helpers ─────────────────────────────────────────────────────

    private function usernameExists(string $username, int $exceptId = 0): bool
    {
        $sql    = "SELECT COUNT(*) FROM users WHERE LOWER(username) = LOWER(:u)";
        $params = ['u' => $username];
        if ($exceptId > 0) {
            $sql    .= " AND id != :id";
            $params['id'] = $exceptId;
        }
        return (int) $this->db->fetchColumn($sql, $params) > 0;
    }

    private function emailExists(string $email, int $exceptId = 0): bool
    {
        $sql    = "SELECT COUNT(*) FROM users WHERE LOWER(email) = LOWER(:e)";
        $params = ['e' => $email];
        if ($exceptId > 0) {
            $sql    .= " AND id != :id";
            $params['id'] = $exceptId;
        }
        return (int) $this->db->fetchColumn($sql, $params) > 0;
    }

    private function employeeIdExists(string $employeeId, int $exceptId = 0): bool
    {
        $sql    = "SELECT COUNT(*) FROM users WHERE employee_id = :eid";
        $params = ['eid' => $employeeId];
        if ($exceptId > 0) {
            $sql    .= " AND id != :id";
            $params['id'] = $exceptId;
        }
        return (int) $this->db->fetchColumn($sql, $params) > 0;
    }

    /**
     * @return array{success: bool, message: string, user_id: null, errors: array}
     */
    private function fail(string $message, array $errors = []): array
    {
        return ['success' => false, 'message' => $message, 'user_id' => null, 'errors' => $errors];
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function safeQuery(string $sql): array
    {
        try {
            return $this->db->fetchAll($sql);
        } catch (\Throwable) {
            return [];
        }
    }
}
