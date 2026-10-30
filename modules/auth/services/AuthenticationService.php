<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 7: Authentication
 * =========================================================
 * File        : modules/auth/services/AuthenticationService.php
 * Purpose     : Core authentication service.
 *               Handles login, logout, remember-me,
 *               session binding, and auto-login from cookie.
 *               Orchestrates: UserModel, LoginHistoryModel,
 *               RememberMeModel, SessionManager, Logger.
 * Dependencies: UserModel, LoginHistoryModel, RememberMeModel,
 *               SessionManager, PasswordUtility, Logger, Cookie.
 * How it works:
 *   Login flow:
 *     1. Find user by credential
 *     2. Check account status
 *     3. Verify password (bcrypt)
 *     4. Record login history
 *     5. Bind session (loginUser)
 *     6. Optionally set remember-me cookie
 *   Auto-login flow:
 *     1. Read selector from cookie
 *     2. Find token row by selector
 *     3. Hash-compare validator
 *     4. Re-bind session
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Auth\Services;

use AAM\Auth\Models\UserModel;
use AAM\Auth\Models\LoginHistoryModel;
use AAM\Auth\Models\RememberMeModel;
use AAM\Core\SessionManager;
use AAM\Core\Logger;
use AAM\Core\Cookie;
use AAM\Security\PasswordUtility;
use AAM\Config\Application;

class AuthenticationService
{
    // Remember-me cookie name
    private const REMEMBER_COOKIE  = 'aam_remember';
    // Cookie lifetime in days
    private const REMEMBER_DAYS    = 30;
    // Max login attempts before lockout — read from config/security.php constant
    private const MAX_ATTEMPTS = 5; // fallback; runtime value comes from \MAX_FAILED_LOGIN_ATTEMPTS

    private UserModel        $userModel;
    private LoginHistoryModel $historyModel;
    private RememberMeModel  $rememberModel;

    public function __construct()
    {
        $this->userModel     = new UserModel();
        $this->historyModel  = new LoginHistoryModel();
        $this->rememberModel = new RememberMeModel();
    }

    // ─── Login ────────────────────────────────────────────────────────────────

    /**
     * Attempt to log in a user.
     *
     * @param string $credential  Username or phone number
     * @param string $password    Plain-text password
     * @param bool   $remember    Set remember-me cookie
     * @param string $ip          Client IP address
     * @param string $userAgent   Client User-Agent
     *
     * @return array{success: bool, message: string, user: array|null}
     */
    public function attempt(
        string $credential,
        string $password,
        bool   $remember  = false,
        string $ip        = '',
        string $userAgent = ''
    ): array {
        $ip        = $ip        ?: ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $userAgent = $userAgent ?: substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

        // ── 1. IP-level brute force check ─────────────────────────────────────
        $recentIpFails = $this->historyModel->countRecentFailedByIp($ip, 60);
        if ($recentIpFails >= 20) {
            Logger::security('IP blocked — too many failed login attempts', ['ip' => $ip]);
            $this->historyModel->record(null, $credential, false, $ip, $userAgent, 'IP rate limit exceeded');
            return ['success' => false, 'message' => 'Too many failed attempts from your location. Please try again in 1 hour.', 'user' => null];
        }

        // ── 2. Find user ──────────────────────────────────────────────────────
        $user = $this->userModel->findByCredential($credential);

        if ($user === null) {
            $this->historyModel->record(null, $credential, false, $ip, $userAgent, 'User not found');
            Logger::security('Login failed — unknown credential', ['credential' => $credential, 'ip' => $ip]);
            // Generic message — don't reveal whether user exists
            return ['success' => false, 'message' => 'Invalid username or password.', 'user' => null];
        }

        // ── 3. Account status check ───────────────────────────────────────────
        $blockReason = $this->userModel->getLoginBlockReason($user);
        if ($blockReason !== null) {
            $this->historyModel->record($user['id'], $credential, false, $ip, $userAgent, $blockReason);
            Logger::security('Login blocked — account status', ['user_id' => $user['id'], 'status' => $user['account_status']]);
            return ['success' => false, 'message' => $blockReason, 'user' => null];
        }

        // ── 4. Password verification ──────────────────────────────────────────
        if (!PasswordUtility::verify($password, $user['password_hash'])) {
            // Use the config/security.php constant; fall back to the class constant
            $maxAttempts = defined('MAX_FAILED_LOGIN_ATTEMPTS') ? \MAX_FAILED_LOGIN_ATTEMPTS : self::MAX_ATTEMPTS;
            $attempts    = $this->userModel->incrementFailedLogins($user['id'], (int)$maxAttempts);

            $this->historyModel->record($user['id'], $credential, false, $ip, $userAgent, 'Wrong password');
            Logger::security('Login failed — wrong password', ['user_id' => $user['id'], 'attempts' => $attempts]);

            $remaining = max(0, (int)$maxAttempts - $attempts);
            $msg = $remaining > 0
                ? "Invalid username or password. {$remaining} attempt(s) remaining before lockout."
                : 'Your account has been locked due to too many failed attempts. Contact the administrator.';

            return ['success' => false, 'message' => $msg, 'user' => null];
        }

        // ── 5. Successful login ───────────────────────────────────────────────
        $this->userModel->recordSuccessfulLogin($user['id']);

        // Rehash if cost factor changed
        if (PasswordUtility::needsRehash($user['password_hash'])) {
            $newHash = PasswordUtility::hash($password);
            $this->userModel->updatePassword($user['id'], $newHash);
            $user['password_hash'] = $newHash;
        }

        // If there is already an authenticated session (e.g. a different user was
        // logged in on this browser), destroy it first so that only ONE active
        // authenticated identity ever exists per browser session.
        if (SessionManager::isAuthenticated()) {
            $existingUserId    = SessionManager::userId();
            $existingSessionId = session_id();
            // Record the old session's logout before destroying it
            if ($existingUserId) {
                try {
                    $this->historyModel->recordLogout($existingSessionId);
                } catch (\Throwable) {
                    // History failure must never block login
                }
            }
            // Remove the current remember-me token for the previous user
            $cookieValue = Cookie::get('aam_remember', null, false);
            if ($cookieValue && str_contains((string)$cookieValue, ':')) {
                [$selector] = explode(':', (string)$cookieValue, 2);
                $this->rememberModel->deleteBySelector($selector);
            }
            SessionManager::logoutUser();
        }

        // Bind session
        SessionManager::loginUser($user);

        // Record history
        $sessionId = session_id();
        $this->historyModel->record($user['id'], $credential, true, $ip, $userAgent, '', $sessionId);

        Logger::info('User logged in', ['user_id' => $user['id'], 'username' => $user['username'], 'ip' => $ip]);

        // ── 6. Remember me ────────────────────────────────────────────────────
        if ($remember) {
            $this->setRememberMeCookie($user['id'], $userAgent, $ip);
        }

        return ['success' => true, 'message' => 'Login successful.', 'user' => $user];
    }

    // ─── Auto-Login from Cookie ───────────────────────────────────────────────

    /**
     * Attempt to automatically log in from a remember-me cookie.
     * Called on every request before auth check when session is empty.
     *
     * @return bool True if auto-login succeeded
     */
    public function attemptFromCookie(): bool
    {
        // Clean raw cookie value (no decryption — we store selector plaintext)
        $cookieValue = Cookie::get(self::REMEMBER_COOKIE, null, false);

        if (empty($cookieValue) || !str_contains((string)$cookieValue, ':')) {
            return false;
        }

        [$selector, $validator] = explode(':', (string)$cookieValue, 2);

        if (strlen($selector) !== 32 || strlen($validator) !== 64) {
            $this->clearRememberCookie();
            return false;
        }

        // Look up token by selector
        $tokenRow = $this->rememberModel->findBySelector($selector);

        if ($tokenRow === null) {
            $this->clearRememberCookie();
            return false;
        }

        // Validate the secret (hash_equals prevents timing attacks)
        $hashedValidator = hash('sha256', $validator);
        if (!hash_equals($tokenRow['hashed_token'], $hashedValidator)) {
            // Possible token theft — delete ALL tokens for this user
            Logger::security('Remember-me token mismatch — possible theft, deleting all tokens', [
                'user_id'  => $tokenRow['user_id'],
                'selector' => $selector,
                'ip'       => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
            $this->rememberModel->deleteForUser((int)$tokenRow['user_id']);
            $this->clearRememberCookie();
            return false;
        }

        // Token valid — check user account status
        if ((int)$tokenRow['is_active'] === 0 || $tokenRow['account_status'] !== 'active') {
            $this->clearRememberCookie();
            return false;
        }

        // Load full user data
        $user = $this->userModel->findWithRole((int)$tokenRow['user_id']);
        if ($user === null) {
            $this->clearRememberCookie();
            return false;
        }

        // Bind session
        SessionManager::loginUser($user);

        // Rotate token (issue new validator, keep selector)
        $newValidator    = bin2hex(random_bytes(32));
        $hashedNew       = hash('sha256', $newValidator);
        $this->db_updateToken($selector, $hashedNew);
        $this->setRememberCookieRaw($selector, $newValidator);

        // Update last-used
        $this->rememberModel->touchToken($selector);

        Logger::info('Auto-login from remember-me cookie', [
            'user_id'  => $user['id'],
            'ip'       => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);

        return true;
    }

    // ─── Logout ───────────────────────────────────────────────────────────────

    /**
     * Log out the current user.
     *
     * @param bool $allDevices  If true, remove all remember-me tokens (logout everywhere)
     */
    public function logout(bool $allDevices = false): void
    {
        $userId    = SessionManager::userId();
        $sessionId = session_id();

        if ($userId) {
            // Record logout in history
            $this->historyModel->recordLogout($sessionId);

            // Remove remember-me tokens
            if ($allDevices) {
                $this->rememberModel->deleteForUser($userId);
            } else {
                // Remove only the current device's cookie
                $cookieValue = Cookie::get(self::REMEMBER_COOKIE, null, false);
                if ($cookieValue && str_contains((string)$cookieValue, ':')) {
                    [$selector] = explode(':', (string)$cookieValue, 2);
                    $this->rememberModel->deleteBySelector($selector);
                }
            }

            Logger::info('User logged out', ['user_id' => $userId]);
        }

        $this->clearRememberCookie();
        SessionManager::logoutUser();
        SessionManager::destroy();
    }

    // ─── Password-Force-Change Check ──────────────────────────────────────────

    /**
     * Check if the current user must change their password.
     */
    public function mustChangePassword(): bool
    {
        $userData = SessionManager::get(SESSION_USER_DATA, []);
        return !empty($userData['must_change_password']);
    }

    // ─── Remember-Me Cookie Helpers ───────────────────────────────────────────

    /**
     * Generate and store a new remember-me token pair.
     */
    private function setRememberMeCookie(int $userId, string $userAgent, string $ip): void
    {
        // Generate cryptographically secure selector and validator
        $selector  = bin2hex(random_bytes(16)); // 32 hex chars
        $validator = bin2hex(random_bytes(32)); // 64 hex chars
        $hashedValidator = hash('sha256', $validator);

        // Delete any expired tokens first
        $this->rememberModel->deleteExpired();

        // Store in DB
        $this->rememberModel->store($userId, $selector, $hashedValidator, $userAgent, $ip, self::REMEMBER_DAYS);

        // Set cookie: selector:validator
        $this->setRememberCookieRaw($selector, $validator);
    }

    /**
     * Set the raw remember-me cookie value.
     */
    private function setRememberCookieRaw(string $selector, string $validator): void
    {
        $value    = $selector . ':' . $validator;
        $expires  = time() + (self::REMEMBER_DAYS * 86400);
        $secure   = defined('SECURE_COOKIES') ? \SECURE_COOKIES : (bool) Application::get('session.secure', false);

        setcookie(self::REMEMBER_COOKIE, $value, [
            'expires'  => $expires,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    /**
     * Update the hashed token for a selector (token rotation).
     */
    private function db_updateToken(string $selector, string $hashedToken): void
    {
        \AAM\Core\Database::getInstance()->execute(
            "UPDATE remember_me_tokens
             SET hashed_token = :tok, last_used_at = NOW()
             WHERE selector = :sel",
            ['tok' => $hashedToken, 'sel' => $selector]
        );
    }

    /**
     * Clear the remember-me cookie from the browser.
     */
    private function clearRememberCookie(): void
    {
        Cookie::delete(self::REMEMBER_COOKIE);
    }
}
