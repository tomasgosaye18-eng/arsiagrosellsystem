<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 8A
 * =========================================================
 * File        : modules/admin/controllers/UserController.php
 * Purpose     : Full enterprise user management controller.
 *               Handles CRUD, status management, live search,
 *               advanced filtering, view/details, soft delete,
 *               restore, unlock, and admin password reset.
 * Dependencies: BaseController, UserService, RoleModel,
 *               ActivityLogModel, Validator.
 * Routes      :
 *   GET    /admin/users                → index()
 *   GET    /admin/users/create         → create()
 *   POST   /admin/users                → store()
 *   GET    /admin/users/{id}           → view()
 *   GET    /admin/users/{id}/edit      → edit()
 *   POST   /admin/users/{id}           → update()
 *   POST   /admin/users/{id}/toggle    → toggle()
 *   POST   /admin/users/{id}/delete    → destroy()
 *   POST   /admin/users/{id}/restore   → restore()
 *   POST   /admin/users/{id}/unlock    → unlock()
 *   POST   /admin/users/{id}/reset     → resetPassword()
 *   GET    /admin/users/search         → liveSearch() (AJAX)
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Admin\Controllers;

use AAM\Core\BaseController;
use AAM\Core\SessionManager;
use AAM\Admin\Services\UserService;
use AAM\Auth\Models\UserModel;
use AAM\Auth\Models\RoleModel;
use AAM\Auth\Models\LoginHistoryModel;
use AAM\Admin\Models\ActivityLogModel;
use AAM\Helpers\Validator;
use AAM\Core\Logger;

class UserController extends BaseController
{
    private UserService      $userService;
    private UserModel        $userModel;
    private RoleModel        $roleModel;
    private ActivityLogModel $activityModel;

    public function __construct()
    {
        parent::__construct();
        $this->userService   = new UserService();
        $this->userModel     = new UserModel();
        $this->roleModel     = new RoleModel();
        $this->activityModel = new ActivityLogModel();
    }

    // ─── Index / List ─────────────────────────────────────────────────────────

    /**
     * GET /admin/users
     */
    public function index(): void
    {
        $this->requireRole(ROLE_ADMIN);

        $page         = max(1, (int) $this->query('page', 1));
        $search       = trim((string) $this->query('search', ''));
        $roleFilter   = trim((string) $this->query('role', ''));
        $statusFilter = trim((string) $this->query('status', ''));
        $sortBy       = trim((string) $this->query('sort', 'created_at'));
        $sortDir      = strtoupper(trim((string) $this->query('dir', 'DESC')));
        $perPage      = (int) $this->query('per_page', 20);
        $perPage      = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 20;

        $result = $this->userService->searchUsers(
            $page, $perPage, $search, $roleFilter, $statusFilter, $sortBy, $sortDir
        );
        $roles  = $this->roleModel->getAllActive();

        // Stats for the header strip
        $stats = [
            'total'    => (int) $this->db_count("SELECT COUNT(*) FROM users WHERE account_status != 'deleted'"),
            'active'   => (int) $this->db_count("SELECT COUNT(*) FROM users WHERE account_status = 'active' AND is_active = 1"),
            'locked'   => (int) $this->db_count("SELECT COUNT(*) FROM users WHERE is_locked = 1"),
            'inactive' => (int) $this->db_count("SELECT COUNT(*) FROM users WHERE account_status IN ('inactive','suspended','pending')"),
        ];

        $this->render('admin.users.index', [
            'pageTitle'    => 'User Management',
            'users'        => $result['data'],
            'pagination'   => $result,
            'roles'        => $roles,
            'search'       => $search,
            'roleFilter'   => $roleFilter,
            'statusFilter' => $statusFilter,
            'sortBy'       => $sortBy,
            'sortDir'      => $sortDir,
            'perPage'      => $perPage,
            'stats'        => $stats,
        ]);
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    /**
     * GET /admin/users/create
     */
    public function create(): void
    {
        $this->requireRole(ROLE_ADMIN);

        $roles   = $this->roleModel->getAllActive();
        $options = $this->userService->getLocationOptions();

        $this->render('admin.users.create', [
            'pageTitle' => 'Create New User',
            'roles'     => $roles,
            'farms'     => $options['farms'],
            'warehouses'=> $options['warehouses'],
            'shops'     => $options['shops'],
        ]);
    }

    /**
     * POST /admin/users
     */
    public function store(): void
    {
        $this->requireRole(ROLE_ADMIN);
        $this->verifyCsrf();

        $data = $this->collectUserFormData();
        $data['password'] = (string) $this->request->rawPost('password', '');

        // Server-side validation
        $validator = new Validator($data, $this->createRules());
        if ($validator->fails()) {
            SessionManager::set('validation_errors', $validator->errors());
            SessionManager::set('old_input', $data);
            $this->flash(FLASH_ERROR, 'Please correct the errors below.');
            $this->redirect('/admin/users/create');
            return;
        }

        $result = $this->userService->createUser($data);

        if (!$result['success']) {
            SessionManager::set('validation_errors', $result['errors']);
            SessionManager::set('old_input', $data);
            $this->flash(FLASH_ERROR, $result['message']);
            $this->redirect('/admin/users/create');
            return;
        }

        $this->flash(FLASH_SUCCESS, $result['message'] . ' They must change their password on first login.');
        $this->redirect('/admin/users');
    }

    // ─── View (Detail) ────────────────────────────────────────────────────────

    /**
     * GET /admin/users/{id}
     */
    public function view(string $id): void
    {
        $this->requireRole(ROLE_ADMIN);

        $userId = (int) $id;
        $user   = $this->userService->getUserDetail($userId);

        if (!$user) {
            $this->flash(FLASH_ERROR, 'User not found.');
            $this->redirect('/admin/users');
            return;
        }

        $loginHistory     = (new LoginHistoryModel())->getForUser($userId, 15);
        $recentActivities = $this->activityModel->getForUser($userId, 15);

        $this->render('admin.users.view', [
            'pageTitle'        => 'User: ' . e($user['username']),
            'user'             => $user,
            'loginHistory'     => $loginHistory,
            'recentActivities' => $recentActivities,
        ]);
    }

    // ─── Edit ─────────────────────────────────────────────────────────────────

    /**
     * GET /admin/users/{id}/edit
     */
    public function edit(string $id): void
    {
        $this->requireRole(ROLE_ADMIN);

        $userId = (int) $id;
        $user   = $this->userModel->findWithRole($userId);

        if (!$user) {
            $this->flash(FLASH_ERROR, 'User not found.');
            $this->redirect('/admin/users');
            return;
        }

        $roles   = $this->roleModel->getAllActive();
        $options = $this->userService->getLocationOptions();

        // Load current location assignment
        try {
            $locationAssignment = \AAM\Core\Database::getInstance()->fetchOne(
                "SELECT * FROM user_location_assignments WHERE user_id = :uid LIMIT 1",
                ['uid' => $userId]
            );
        } catch (\Throwable) {
            $locationAssignment = null;
        }

        $this->render('admin.users.edit', [
            'pageTitle'          => 'Edit User — ' . e($user['username']),
            'user'               => $user,
            'roles'              => $roles,
            'farms'              => $options['farms'],
            'warehouses'         => $options['warehouses'],
            'shops'              => $options['shops'],
            'locationAssignment' => $locationAssignment,
        ]);
    }

    /**
     * POST /admin/users/{id}
     */
    public function update(string $id): void
    {
        $this->requireRole(ROLE_ADMIN);
        $this->verifyCsrf();

        $userId = (int) $id;
        $user   = $this->userModel->find($userId);

        if (!$user) {
            $this->flash(FLASH_ERROR, 'User not found.');
            $this->redirect('/admin/users');
            return;
        }

        $data = $this->collectUserFormData();

        // Validation rules for update (username is read-only)
        $validator = new Validator($data, $this->updateRules());
        if ($validator->fails()) {
            SessionManager::set('validation_errors', $validator->errors());
            SessionManager::set('old_input', $data);
            $this->flash(FLASH_ERROR, 'Please correct the errors below.');
            $this->redirect('/admin/users/' . $userId . '/edit');
            return;
        }

        $result = $this->userService->updateUser($userId, $data);

        if (!$result['success']) {
            SessionManager::set('validation_errors', $result['errors']);
            SessionManager::set('old_input', $data);
            $this->flash(FLASH_ERROR, $result['message']);
            $this->redirect('/admin/users/' . $userId . '/edit');
            return;
        }

        $this->flash(FLASH_SUCCESS, 'User updated successfully.');
        $this->redirect('/admin/users/' . $userId);
    }

    // ─── Toggle Active ────────────────────────────────────────────────────────

    /**
     * POST /admin/users/{id}/toggle
     */
    public function toggle(string $id): void
    {
        $this->requireRole(ROLE_ADMIN);
        $this->verifyCsrf();

        $result = $this->userService->toggleStatus((int) $id, $this->userId());

        if ($this->request->isAjax()) {
            $result['success']
                ? $this->jsonSuccess(null, $result['message'])
                : $this->jsonError($result['message']);
            return;
        }

        $this->flash($result['success'] ? FLASH_SUCCESS : FLASH_ERROR, $result['message']);
        $this->redirect('/admin/users');
    }

    // ─── Soft Delete ──────────────────────────────────────────────────────────

    /**
     * POST /admin/users/{id}/delete
     */
    public function destroy(string $id): void
    {
        $this->requireRole(ROLE_ADMIN);
        $this->verifyCsrf();

        $result = $this->userService->softDeleteUser((int) $id, $this->userId());

        if ($this->request->isAjax()) {
            $result['success']
                ? $this->jsonSuccess(null, $result['message'])
                : $this->jsonError($result['message']);
            return;
        }

        $this->flash($result['success'] ? FLASH_SUCCESS : FLASH_ERROR, $result['message']);
        $this->redirect('/admin/users');
    }

    // ─── Restore ──────────────────────────────────────────────────────────────

    /**
     * POST /admin/users/{id}/restore
     */
    public function restore(string $id): void
    {
        $this->requireRole(ROLE_ADMIN);
        $this->verifyCsrf();

        $result = $this->userService->restoreUser((int) $id);

        if ($this->request->isAjax()) {
            $result['success']
                ? $this->jsonSuccess(null, $result['message'])
                : $this->jsonError($result['message']);
            return;
        }

        $this->flash($result['success'] ? FLASH_SUCCESS : FLASH_ERROR, $result['message']);
        $this->redirect('/admin/users');
    }

    // ─── Unlock ───────────────────────────────────────────────────────────────

    /**
     * POST /admin/users/{id}/unlock
     */
    public function unlock(string $id): void
    {
        $this->requireRole(ROLE_ADMIN);
        $this->verifyCsrf();

        $result = $this->userService->unlockUser((int) $id);

        if ($this->request->isAjax()) {
            $result['success']
                ? $this->jsonSuccess(null, $result['message'])
                : $this->jsonError($result['message']);
            return;
        }

        $this->flash($result['success'] ? FLASH_SUCCESS : FLASH_ERROR, $result['message']);
        $this->redirectBack('/admin/users');
    }

    // ─── Password Reset ───────────────────────────────────────────────────────

    /**
     * POST /admin/users/{id}/reset
     */
    public function resetPassword(string $id): void
    {
        $this->requireRole(ROLE_ADMIN);
        $this->verifyCsrf();

        $result = $this->userService->adminResetPassword((int) $id);

        if ($this->request->isAjax()) {
            $result['success']
                ? $this->jsonSuccess(['temp_password' => $result['temp_password']], $result['message'])
                : $this->jsonError($result['message']);
            return;
        }

        if ($result['success']) {
            // Show temp password in a flash — admin copies to user
            $this->flash(
                FLASH_SUCCESS,
                "Password reset. Temporary password: <strong>" .
                e($result['temp_password'] ?? '') .
                "</strong> — give this to the user. They must change it on next login."
            );
        } else {
            $this->flash(FLASH_ERROR, $result['message']);
        }

        $this->redirectBack('/admin/users');
    }

    // ─── Live Search (AJAX) ───────────────────────────────────────────────────

    /**
     * GET /admin/users/search   (AJAX only)
     * Returns a JSON array of matching users for live-search dropdown.
     */
    public function liveSearch(): void
    {
        $this->requireRole(ROLE_ADMIN);

        if (!$this->request->isAjax()) {
            $this->jsonError('AJAX only.', HTTP_BAD_REQUEST);
            return;
        }

        $q      = trim((string) $this->query('q', ''));
        $result = $this->userService->searchUsers(1, 10, $q);

        $items = array_map(static function (array $u): array {
            return [
                'id'       => (int) $u['id'],
                'username' => $u['username'],
                'name'     => trim($u['first_name'] . ' ' . $u['last_name']),
                'role'     => $u['role_display_name'] ?? $u['role_name'],
                'status'   => $u['account_status'],
                'photo'    => $u['profile_photo'] ?? null,
            ];
        }, $result['data']);

        $this->jsonSuccess($items);
    }

    // ─── Internal Helpers ─────────────────────────────────────────────────────

    /**
     * Collect and sanitize all user form fields.
     *
     * @return array<string,mixed>
     */
    private function collectUserFormData(): array
    {
        return [
            'employee_id'   => trim((string) $this->input('employee_id', '')),
            'first_name'    => trim((string) $this->input('first_name', '')),
            'middle_name'   => trim((string) $this->input('middle_name', '')),
            'last_name'     => trim((string) $this->input('last_name', '')),
            'gender'        => trim((string) $this->input('gender', '')),
            'username'      => trim((string) $this->input('username', '')),
            'email'         => trim((string) $this->input('email', '')),
            'phone'         => trim((string) $this->input('phone', '')),
            'role_id'       => (int) $this->input('role_id', 0),
            'location_type' => trim((string) $this->input('location_type', '')),
            'location_id'   => (int) $this->input('location_id', 0),
        ];
    }

    /**
     * Validation rules for create.
     *
     * @return array<string, string>
     */
    private function createRules(): array
    {
        return [
            'first_name'  => 'required|min:2|max:80',
            'last_name'   => 'required|min:2|max:80',
            'username'    => 'required|min:3|max:80',
            'email'       => 'nullable|email|max:191',
            'phone'       => 'nullable|phone',
            'role_id'     => 'required|integer',
            'employee_id' => 'nullable|max:30',
            'gender'      => 'nullable|in:male,female,other,prefer_not_to_say',
        ];
    }

    /**
     * Validation rules for update (username not re-validated).
     *
     * @return array<string, string>
     */
    private function updateRules(): array
    {
        return [
            'first_name'  => 'required|min:2|max:80',
            'last_name'   => 'required|min:2|max:80',
            'email'       => 'nullable|email|max:191',
            'phone'       => 'nullable|phone',
            'role_id'     => 'required|integer',
            'employee_id' => 'nullable|max:30',
            'gender'      => 'nullable|in:male,female,other,prefer_not_to_say',
        ];
    }

    /**
     * Safe DB count — returns 0 on error.
     */
    private function db_count(string $sql): int
    {
        try {
            return (int) \AAM\Core\Database::getInstance()->fetchColumn($sql);
        } catch (\Throwable) {
            return 0;
        }
    }
}
