<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 7: Authentication
 * =========================================================
 * File        : modules/auth/services/PasswordService.php
 * Purpose     : Password management service.
 *               Handles password change, forgot password
 *               token generation, and password reset.
 *               Enforces strength rules. Logs all changes.
 * Dependencies: UserModel, PasswordUtility, Logger,
 *               SessionManager.
 * How it works:
 *   Change password  : verify old → validate new → hash → store
 *   Forgot password  : generate token → hash → store → return token
 *   Reset password   : find by hashed token → validate new → hash → store
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Auth\Services;

use AAM\Auth\Models\UserModel;
use AAM\Core\Database;
use AAM\Core\Logger;
use AAM\Core\SessionManager;
use AAM\Security\PasswordUtility;

class PasswordService
{
    /** Reset token lifetime in minutes */
    private const RESET_TOKEN_EXPIRY_MINS = 60;

    private UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    // ─── Change Password ──────────────────────────────────────────────────────

    /**
     * Change the current user's password.
     * Verifies the old password before allowing change.
     *
     * @param int    $userId
     * @param string $currentPassword  Plain-text current password
     * @param string $newPassword      Plain-text new password
     * @param string $confirmPassword  Must match $newPassword
     *
     * @return array{success: bool, message: string, errors: array}
     */
    public function changePassword(
        int    $userId,
        string $currentPassword,
        string $newPassword,
        string $confirmPassword
    ): array {
        $errors = [];

        // Load user
        $user = $this->userModel->find($userId);
        if ($user === null) {
            return ['success' => false, 'message' => 'User not found.', 'errors' => []];
        }

        // Verify current password
        if (!PasswordUtility::verify($currentPassword, $user['password_hash'])) {
            $errors['current_password'] = ['Current password is incorrect.'];
            return ['success' => false, 'message' => 'Current password is incorrect.', 'errors' => $errors];
        }

        // Validate new password
        $validation = $this->validateNewPassword($newPassword, $confirmPassword, $currentPassword);
        if (!empty($validation)) {
            return ['success' => false, 'message' => 'Password does not meet requirements.', 'errors' => $validation];
        }

        // Hash and store
        $hash = PasswordUtility::hash($newPassword);
        $this->userModel->updatePassword($userId, $hash);

        // Update session
        $userData = SessionManager::get(SESSION_USER_DATA, []);
        $userData['must_change_password'] = 0;
        SessionManager::set(SESSION_USER_DATA, $userData);

        Logger::security('Password changed by user', ['user_id' => $userId]);

        return ['success' => true, 'message' => 'Password changed successfully.', 'errors' => []];
    }

    /**
     * Force-set a user's password (admin reset — no current password needed).
     */
    public function adminResetPassword(int $targetUserId, string $newPassword): array
    {
        $validation = PasswordUtility::validate($newPassword);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => implode(' ', $validation['errors']), 'errors' => $validation['errors']];
        }

        $hash = PasswordUtility::hash($newPassword);
        $this->userModel->updatePassword($targetUserId, $hash);

        // Force must_change_password
        \AAM\Core\Database::getInstance()->execute(
            "UPDATE users SET must_change_password = 1 WHERE id = :id",
            ['id' => $targetUserId]
        );

        Logger::security('Admin password reset', [
            'admin_id'   => SessionManager::userId(),
            'target_uid' => $targetUserId,
        ]);

        return ['success' => true, 'message' => 'Password reset successfully. User must change it on next login.', 'errors' => []];
    }

    // ─── Forgot Password ──────────────────────────────────────────────────────

    /**
     * Generate a password reset token for a given username.
     * Returns the raw token (to be sent via UI — no email in v1).
     * Stores the SHA-256 hash in the database.
     *
     * @return array{success: bool, message: string, token: string|null, user: array|null}
     */
    public function generateResetToken(string $username): array
    {
        $user = $this->userModel->findByUsername($username);

        if ($user === null) {
            // Don't reveal whether user exists
            return [
                'success' => true,
                'message' => 'If an account with that username exists, a reset token has been generated.',
                'token'   => null,
                'user'    => null,
            ];
        }

        // Check account can reset
        $blockReason = $this->userModel->getLoginBlockReason($user);
        if ($blockReason !== null && $user['account_status'] !== 'locked') {
            return ['success' => false, 'message' => $blockReason, 'token' => null, 'user' => null];
        }

        // Generate raw token (32 bytes = 64 hex chars)
        $rawToken    = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        // Store hashed token with expiry
        $this->userModel->storeResetToken($user['id'], $hashedToken, self::RESET_TOKEN_EXPIRY_MINS);

        Logger::security('Password reset token generated', ['user_id' => $user['id']]);

        return [
            'success' => true,
            'message' => 'Reset token generated. Valid for ' . self::RESET_TOKEN_EXPIRY_MINS . ' minutes.',
            'token'   => $rawToken,
            'user'    => $user,
        ];
    }

    /**
     * Validate a reset token (check it exists and is not expired).
     * Returns the user or null if invalid.
     *
     * @return array<string,mixed>|null
     */
    public function validateResetToken(string $rawToken): ?array
    {
        $hashedToken = hash('sha256', $rawToken);
        return $this->userModel->findByResetToken($hashedToken);
    }

    /**
     * Reset a user's password using a valid reset token.
     *
     * @param string $rawToken       The token from the URL/form
     * @param string $newPassword
     * @param string $confirmPassword
     *
     * @return array{success: bool, message: string}
     */
    public function resetPassword(string $rawToken, string $newPassword, string $confirmPassword): array
    {
        // Validate token
        $user = $this->validateResetToken($rawToken);
        if ($user === null) {
            return ['success' => false, 'message' => 'This reset link is invalid or has expired. Please request a new one.'];
        }

        // Validate new password
        $errors = $this->validateNewPassword($newPassword, $confirmPassword);
        if (!empty($errors)) {
            return ['success' => false, 'message' => 'Password does not meet requirements: ' . implode(' ', array_merge(...array_values($errors)))];
        }

        // Hash and update
        $hash = PasswordUtility::hash($newPassword);
        $this->userModel->updatePassword($user['id'], $hash);
        $this->userModel->clearResetToken($user['id']);

        // Unlock if account was locked
        if ($user['account_status'] === 'locked') {
            $this->userModel->unlockAccount($user['id']);
        }

        Logger::security('Password reset via token', ['user_id' => $user['id']]);

        return ['success' => true, 'message' => 'Password reset successfully. You can now log in.'];
    }

    // ─── Internal Validation ──────────────────────────────────────────────────

    /**
     * Validate a new password against all rules.
     *
     * @return array<string, string[]> Field-keyed errors (empty = valid)
     */
    private function validateNewPassword(
        string  $newPassword,
        string  $confirmPassword,
        ?string $currentPassword = null
    ): array {
        $errors = [];

        // Must not match current password (plain-text comparison — both are plain text here)
        if ($currentPassword !== null && $newPassword === $currentPassword) {
            $errors['new_password'][] = 'New password cannot be the same as your current password.';
        }

        // Strength check
        $strength = PasswordUtility::validate($newPassword);
        if (!$strength['valid']) {
            $errors['new_password'] = array_merge($errors['new_password'] ?? [], $strength['errors']);
        }

        // Common password check
        if (PasswordUtility::isCommonPassword($newPassword)) {
            $errors['new_password'][] = 'This password is too common. Please choose a more unique password.';
        }

        // Confirmation match
        if ($newPassword !== $confirmPassword) {
            $errors['confirm_password'][] = 'Passwords do not match.';
        }

        return $errors;
    }
}
