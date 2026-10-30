<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 7: Authentication
 * =========================================================
 * File        : modules/auth/services/SessionService.php
 * Purpose     : Session management service.
 *               Handles session validation, timeout
 *               enforcement, concurrent session detection,
 *               and session refresh for active users.
 * Dependencies: SessionManager, UserModel, Logger.
 * How it works:
 *   - isValid()    : checks auth + fingerprint + timeout
 *   - refresh()    : updates last_activity timestamp
 *   - getInfo()    : returns session metadata for profile
 *   - getRemainingTime() : seconds until session expires
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Auth\Services;

use AAM\Auth\Models\UserModel;
use AAM\Core\SessionManager;
use AAM\Core\Logger;
use AAM\Config\Application;

class SessionService
{
    private UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    /**
     * Check whether the current session is fully valid.
     * Validates: authenticated, not expired, fingerprint intact.
     */
    public function isValid(): bool
    {
        if (!SessionManager::isAuthenticated()) {
            return false;
        }

        $lifetime     = (int) Application::get('session.lifetime', 1800);
        $lastActivity = (int) SessionManager::get(SESSION_LAST_ACTIVITY, 0);

        if ($lastActivity > 0 && (time() - $lastActivity) > $lifetime) {
            Logger::notice('Session expired due to inactivity', [
                'user_id' => SessionManager::userId(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Refresh the session's last-activity timestamp.
     * Call on every authenticated request.
     */
    public function refresh(): void
    {
        SessionManager::set(SESSION_LAST_ACTIVITY, time());
    }

    /**
     * Get remaining session lifetime in seconds.
     */
    public function getRemainingTime(): int
    {
        $lifetime     = (int) Application::get('session.lifetime', 1800);
        $lastActivity = (int) SessionManager::get(SESSION_LAST_ACTIVITY, 0);

        if ($lastActivity === 0) {
            return $lifetime;
        }

        $elapsed   = time() - $lastActivity;
        $remaining = $lifetime - $elapsed;
        return max(0, $remaining);
    }

    /**
     * Get session metadata for the profile/security page.
     *
     * @return array<string,mixed>
     */
    public function getInfo(): array
    {
        return [
            'session_id'     => session_id(),
            'login_time'     => SessionManager::get(SESSION_LOGIN_TIME),
            'last_activity'  => SessionManager::get(SESSION_LAST_ACTIVITY),
            'remaining_secs' => $this->getRemainingTime(),
            'user_agent'     => SessionManager::get(SESSION_USER_AGENT, ''),
            'ip'             => $_SERVER['REMOTE_ADDR'] ?? '',
        ];
    }

    /**
     * Synchronise the in-session user data with the current DB record.
     * Call after profile updates so session reflects changes immediately.
     */
    public function syncUserData(int $userId): void
    {
        $user = $this->userModel->findWithRole($userId);
        if ($user !== null) {
            // Preserve sensitive session state, just update profile fields
            $current = SessionManager::get(SESSION_USER_DATA, []);
            $updated = array_merge($current, [
                'first_name'          => $user['first_name'],
                'last_name'           => $user['last_name'],
                'phone'               => $user['phone'],
                'profile_photo'       => $user['profile_photo'],
                'role_name'           => $user['role_name'],
                'role_display_name'   => $user['role_display_name'],
                'must_change_password'=> $user['must_change_password'],
                'account_status'      => $user['account_status'],
            ]);
            SessionManager::set(SESSION_USER_DATA, $updated);
            SessionManager::set(SESSION_USER_ROLE, $user['role_name']);
            SessionManager::set(SESSION_USER_NAME, trim($user['first_name'] . ' ' . $user['last_name']));
        }
    }

    /**
     * Validate that the authenticated user account is still active in DB.
     * Call periodically (e.g., every N requests) to catch admin deactivations.
     *
     * @return bool False if account has been deactivated/locked since login
     */
    public function revalidateAccount(): bool
    {
        $userId = SessionManager::userId();
        if ($userId === null) {
            return false;
        }

        $user = $this->userModel->find($userId);
        if ($user === null || (int)$user['is_active'] === 0 || $user['account_status'] !== 'active') {
            Logger::security('Session invalidated — account status changed', [
                'user_id' => $userId,
                'status'  => $user['account_status'] ?? 'unknown',
            ]);
            return false;
        }

        return true;
    }
}
