<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 7: Authentication
 * =========================================================
 * File        : modules/auth/controllers/AuthController.php
 * Purpose     : Authentication controller.
 *               Routes: GET/POST /login, GET /logout,
 *               GET/POST /change-password (forced),
 *               GET /profile
 * Dependencies: BaseController, AuthenticationService,
 *               PasswordService, SessionService, Validator.
 * How it works:
 *   showLogin()      → render login view (minimal layout)
 *   login()          → validate → AuthenticationService::attempt()
 *                      → redirect dashboard or force-change-pw
 *   logout()         → AuthenticationService::logout() → /login
 *   showChangePassword() → render change-password view
 *   changePassword() → PasswordService::changePassword() → dashboard
 *   profile()        → render profile view
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Auth\Controllers;

use AAM\Core\BaseController;
use AAM\Core\SessionManager;
use AAM\Auth\Services\AuthenticationService;
use AAM\Auth\Services\PasswordService;
use AAM\Auth\Services\SessionService;
use AAM\Auth\Models\LoginHistoryModel;
use AAM\Auth\Models\RememberMeModel;
use AAM\Admin\Models\ActivityLogModel;
use AAM\Helpers\Validator;
use AAM\Core\Logger;

class AuthController extends BaseController
{
    private AuthenticationService $authService;
    private PasswordService       $passwordService;
    private SessionService        $sessionService;
    private ActivityLogModel      $activityModel;

    public function __construct()
    {
        parent::__construct();
        $this->authService     = new AuthenticationService();
        $this->passwordService = new PasswordService();
        $this->sessionService  = new SessionService();
        $this->activityModel   = new ActivityLogModel();
    }

    // ─── Login Page ───────────────────────────────────────────────────────────

    /**
     * GET /login
     * Show the login page. Redirect if already authenticated.
     * Handles ?expired=1 to show session-expired message.
     */
    public function showLogin(): void
    {
        // Already logged in — redirect to dashboard
        if (SessionManager::isAuthenticated() && $this->sessionService->isValid()) {
            $this->redirect($this->getDashboardUrl());
            return;
        }

        // Try auto-login from remember-me cookie
        if (!SessionManager::isAuthenticated()) {
            if ($this->authService->attemptFromCookie()) {
                $this->redirect($this->getDashboardUrl());
                return;
            }
        }

        // Show session-expired alert when redirected from timeout countdown
        if ($this->query('expired') === '1') {
            $this->flash(FLASH_WARNING, 'Your session expired due to inactivity. Please log in again.');
        }

        $this->render('auth.login', [
            'pageTitle'      => 'Login',
            'sessionExpired' => $this->query('expired') === '1',
        ], 'minimal');
    }

    /**
     * GET /session-expired
     * Dedicated session-expired page.
     */
    public function sessionExpired(): void
    {
        $this->render('auth.session_expired', [
            'pageTitle' => 'Session Expired',
        ], 'minimal');
    }

    /**
     * POST /login
     * Process login form submission.
     */
    public function login(): void
    {
        $this->verifyCsrf();

        $credential = trim((string) $this->request->rawPost('credential', ''));
        $password   = (string) $this->request->rawPost('password', '');
        $remember   = (bool)   $this->input('remember_me', false);

        // Server-side validation
        $validator = new Validator(
            ['credential' => $credential, 'password' => $password],
            ['credential' => 'required|min:3|max:80', 'password' => 'required|min:4']
        );

        if ($validator->fails()) {
            SessionManager::set('validation_errors', $validator->errors());
            SessionManager::set('old_input', ['credential' => $credential]);
            $this->redirect('/login');
            return;
        }

        // Attempt authentication
        $result = $this->authService->attempt(
            $credential,
            $password,
            $remember,
            $this->request->ip(),
            $this->request->userAgent()
        );

        if (!$result['success']) {
            SessionManager::set('validation_errors', ['credential' => [$result['message']]]);
            SessionManager::set('old_input', ['credential' => $credential]);
            $this->redirect('/login');
            return;
        }

        // Check if forced password change required
        if ($this->authService->mustChangePassword()) {
            $this->flash(FLASH_WARNING, 'You must change your password before continuing.');
            $this->redirect('/change-password');
            return;
        }

        // Redirect to intended URL or dashboard
        $intended = SessionManager::get('intended_url', '');
        SessionManager::delete('intended_url');

        $this->flash(FLASH_SUCCESS, 'Welcome back, ' . e(SessionManager::get(SESSION_USER_NAME)) . '!');

        // Log login activity
        try {
            $this->activityModel->log(
                ActivityLogModel::ACTION_LOGIN,
                SessionManager::get(SESSION_USER_NAME, 'User') . ' logged in',
                'users',
                $this->userId()
            );
        } catch (\Throwable) {
            // Logging failure must never prevent login
        }

        $this->redirect($intended ?: $this->getDashboardUrl());
    }

    // ─── Logout ───────────────────────────────────────────────────────────────

    /**
     * GET /logout
     */
    public function logout(): void
    {
        // Log logout before session is destroyed — but never let a log failure block logout
        if ($this->userId()) {
            try {
                $this->activityModel->log(
                    ActivityLogModel::ACTION_LOGOUT,
                    SessionManager::get(SESSION_USER_NAME, 'User') . ' logged out',
                    'users',
                    $this->userId()
                );
            } catch (\Throwable) {
                // Logging failure must never prevent logout
            }
        }

        $allDevices = (bool) $this->query('all_devices', false);
        $this->authService->logout($allDevices);

        $this->flash(FLASH_SUCCESS, 'You have been logged out successfully.');
        $this->redirect('/login');
    }

    // ─── Forced Password Change ───────────────────────────────────────────────

    /**
     * GET /change-password
     */
    public function showChangePassword(): void
    {
        $this->requireAuth();
        $this->render('auth.change_password', [
            'pageTitle' => 'Change Password',
            'forced'    => $this->authService->mustChangePassword(),
        ], 'minimal');
    }

    /**
     * POST /change-password
     */
    public function changePassword(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $userId          = $this->userId();
        $currentPassword = (string) $this->request->rawPost('current_password', '');
        $newPassword     = (string) $this->request->rawPost('new_password', '');
        $confirmPassword = (string) $this->request->rawPost('confirm_password', '');

        $result = $this->passwordService->changePassword(
            $userId,
            $currentPassword,
            $newPassword,
            $confirmPassword
        );

        if (!$result['success']) {
            if (!empty($result['errors'])) {
                SessionManager::set('validation_errors', $result['errors']);
            }
            $this->flash(FLASH_ERROR, $result['message']);
            $this->redirect('/change-password');
            return;
        }

        $this->flash(FLASH_SUCCESS, 'Password changed successfully.');
        $this->redirect($this->getDashboardUrl());
    }

    // ─── Profile ──────────────────────────────────────────────────────────────

    /**
     * GET /profile
     */
    public function profile(): void
    {
        $this->requireAuth();

        $user         = $this->currentUser();
        $sessionInfo  = $this->sessionService->getInfo();
        $loginHistory = (new LoginHistoryModel())->getForUser($this->userId(), 10);
        $activeSessions = (new RememberMeModel())->getForUser($this->userId());

        $this->render('auth.profile', [
            'pageTitle'      => 'My Profile',
            'user'           => $user,
            'sessionInfo'    => $sessionInfo,
            'loginHistory'   => $loginHistory,
            'activeSessions' => $activeSessions,
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Get the correct dashboard URL for the current user's role.
     * Each role has a dedicated landing page after login.
     */
    private function getDashboardUrl(): string
    {
        $role = SessionManager::userRole();
        return match($role) {
            ROLE_ADMIN             => url('admin/dashboard'),
            ROLE_FARM_MANAGER      => url('farm/dashboard'),
            ROLE_GENERAL_MANAGER   => url('gm/dashboard'),
            ROLE_WAREHOUSE_MANAGER => url('warehouse'),
            ROLE_SALESPERSON       => url('pos'),
            default                => url('dashboard'),
        };
    }
}
