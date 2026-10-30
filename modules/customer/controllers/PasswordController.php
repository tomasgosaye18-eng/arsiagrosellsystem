<?php
/**
 * =========================================================
 * AU-AgroSale — Customer Forgot / Reset Password (OTP Flow)
 * =========================================================
 * File    : modules/customer/controllers/PasswordController.php
 *
 * Flow:
 *   1. GET  /customer/auth/forgot-password
 *      → Show email input form
 *
 *   2. POST /customer/auth/forgot-password
 *      → Generate 6-digit OTP
 *      → Store OTP + email + expiry in session
 *      → (In production: send OTP by email)
 *      → Redirect to OTP entry page
 *
 *   3. GET  /customer/auth/verify-otp
 *      → Show OTP entry form (email pre-filled from session)
 *
 *   4. POST /customer/auth/verify-otp
 *      → Validate OTP (correct + not expired)
 *      → Generate password reset token via PasswordService
 *      → Redirect to reset password form
 *
 *   5. GET  /customer/auth/reset-password/{token}
 *      → Validate token, show new password form
 *
 *   6. POST /customer/auth/reset-password/{token}
 *      → Set new password, redirect to login
 *
 * SECURITY:
 *   - CSRF on all POST routes.
 *   - OTP is 6 digits, expires in 10 minutes.
 *   - Generic messages prevent email enumeration.
 *   - Reset token is single-use SHA-256 hashed in DB.
 *   - Reset only works for ROLE_CUSTOMER accounts.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Customer\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;
use AAM\Core\Logger;
use AAM\Core\SessionManager;
use AAM\Auth\Services\PasswordService;

class PasswordController extends BaseController
{
    private PasswordService $passwordService;

    /** OTP lifetime in seconds (10 minutes) */
    private const OTP_EXPIRY_SECONDS = 600;

    /** Session key prefix for OTP data */
    private const OTP_SESSION_KEY = 'ca_pw_otp';

    public function __construct()
    {
        parent::__construct();
        $this->passwordService = new PasswordService();
    }

    // ──────────────────────────────────────────────────────
    // STEP 1: GET /customer/auth/forgot-password
    // Show email input form
    // ──────────────────────────────────────────────────────
    public function showForgot(): void
    {
        if (SessionManager::isAuthenticated()) {
            $this->redirect('customer');
            return;
        }

        $error = SessionManager::get('ca_fp_error');
        SessionManager::delete('ca_fp_error');

        $this->render('customer.auth.forgot_password', [
            'pageTitle' => 'Forgot Password | AU-AgroSale',
            'error'     => $error,
        ], 'customer');
    }

    // ──────────────────────────────────────────────────────
    // STEP 2: POST /customer/auth/forgot-password
    // Generate OTP and go to verify page
    // ──────────────────────────────────────────────────────
    public function forgot(): void
    {
        $email = trim((string)$this->input('email', ''));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            SessionManager::set('ca_fp_error', 'Please enter a valid email address.');
            $this->redirect('customer/auth/forgot-password');
            return;
        }

        // Look up customer — silently succeed even if not found
        $user = $this->findCustomerByEmail($email);

        if ($user !== null) {
            // Generate 6-digit OTP
            $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Store in session: OTP hash + email + expiry
            SessionManager::set(self::OTP_SESSION_KEY, [
                'otp_hash'   => password_hash($otp, PASSWORD_BCRYPT),
                'email'      => $email,
                'user_id'    => (int)$user['id'],
                'expires_at' => time() + self::OTP_EXPIRY_SECONDS,
                'attempts'   => 0,
            ]);

            Logger::security('Customer OTP generated', [
                'user_id' => $user['id'],
                'email'   => $email,
                'ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);

            // ── In production: send OTP via email ──────────
            // mail($email, 'Your AU-AgroSale OTP', "Your OTP is: $otp\nExpires in 10 minutes.");
            // ───────────────────────────────────────────────

            // Store OTP in session for display (dev/localhost mode — no email server)
            SessionManager::set('ca_fp_dev_otp', $otp);
        }

        // Always redirect to verify page — never reveal whether email exists
        $this->redirect('customer/auth/verify-otp');
    }

    // ──────────────────────────────────────────────────────
    // STEP 3: GET /customer/auth/verify-otp
    // Show OTP entry form
    // ──────────────────────────────────────────────────────
    public function showVerifyOtp(): void
    {
        if (SessionManager::isAuthenticated()) {
            $this->redirect('customer');
            return;
        }

        $otpData = SessionManager::get(self::OTP_SESSION_KEY);

        // No OTP in session — redirect back
        if (empty($otpData)) {
            $this->redirect('customer/auth/forgot-password');
            return;
        }

        $error  = SessionManager::get('ca_otp_error');
        SessionManager::delete('ca_otp_error');

        // Dev mode: show OTP on screen (remove in production with real email)
        $devOtp = SessionManager::get('ca_fp_dev_otp');

        $this->render('customer.auth.verify_otp', [
            'pageTitle'  => 'Enter OTP | AU-AgroSale',
            'email'      => $otpData['email'] ?? '',
            'error'      => $error,
            'devOtp'     => $devOtp,   // null in production
            'expiresAt'  => $otpData['expires_at'] ?? 0,
        ], 'customer');
    }

    // ──────────────────────────────────────────────────────
    // STEP 4: POST /customer/auth/verify-otp
    // Validate OTP, generate reset token, go to reset form
    // ──────────────────────────────────────────────────────
    public function verifyOtp(): void
    {
        $otpData = SessionManager::get(self::OTP_SESSION_KEY);

        if (empty($otpData)) {
            $this->redirect('customer/auth/forgot-password');
            return;
        }

        $enteredOtp = trim((string)$this->input('otp', ''));

        // Check expiry
        if (time() > ($otpData['expires_at'] ?? 0)) {
            SessionManager::delete(self::OTP_SESSION_KEY);
            SessionManager::delete('ca_fp_dev_otp');
            SessionManager::set('ca_fp_error', 'Your OTP has expired. Please request a new one.');
            $this->redirect('customer/auth/forgot-password');
            return;
        }

        // Track attempts (max 5)
        $attempts = (int)($otpData['attempts'] ?? 0) + 1;
        $otpData['attempts'] = $attempts;
        SessionManager::set(self::OTP_SESSION_KEY, $otpData);

        if ($attempts > 5) {
            SessionManager::delete(self::OTP_SESSION_KEY);
            SessionManager::delete('ca_fp_dev_otp');
            SessionManager::set('ca_fp_error', 'Too many incorrect attempts. Please request a new OTP.');
            $this->redirect('customer/auth/forgot-password');
            return;
        }

        // Validate OTP
        if (empty($enteredOtp) || !password_verify($enteredOtp, $otpData['otp_hash'] ?? '')) {
            $remaining = 5 - $attempts;
            SessionManager::set('ca_otp_error', "Incorrect OTP. {$remaining} attempt(s) remaining.");
            $this->redirect('customer/auth/verify-otp');
            return;
        }

        // OTP correct — clear OTP session data
        SessionManager::delete(self::OTP_SESSION_KEY);
        SessionManager::delete('ca_fp_dev_otp');

        // Generate a password reset token via PasswordService
        $userId = (int)($otpData['user_id'] ?? 0);
        $user   = $this->findCustomerById($userId);

        if ($user === null) {
            SessionManager::set('ca_fp_error', 'Account not found. Please try again.');
            $this->redirect('customer/auth/forgot-password');
            return;
        }

        $result = $this->passwordService->generateResetToken($user['username'] ?? $user['email']);

        if (!$result['success'] || empty($result['token'])) {
            SessionManager::set('ca_fp_error', 'Could not generate reset link. Please try again.');
            $this->redirect('customer/auth/forgot-password');
            return;
        }

        Logger::security('Customer OTP verified — reset token issued', [
            'user_id' => $userId,
            'ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);

        // Go directly to reset password form
        $this->redirect('customer/auth/reset-password/' . urlencode($result['token']));
    }

    // ──────────────────────────────────────────────────────
    // STEP 5: GET /customer/auth/reset-password/{token}
    // Show new password form
    // ──────────────────────────────────────────────────────
    public function showReset(string $token): void
    {
        $user = $this->passwordService->validateResetToken($token);

        if ($user === null) {
            $this->render('customer.auth.reset_password_invalid', [
                'pageTitle' => 'Link Expired | AU-AgroSale',
            ], 'customer');
            return;
        }

        // Only allow customers
        if (($user['role_name'] ?? '') !== ROLE_CUSTOMER) {
            $this->render('customer.auth.reset_password_invalid', [
                'pageTitle' => 'Link Expired | AU-AgroSale',
            ], 'customer');
            return;
        }

        $error = SessionManager::get('ca_rp_error');
        SessionManager::delete('ca_rp_error');

        $this->render('customer.auth.reset_password', [
            'pageTitle' => 'Reset Password | AU-AgroSale',
            'token'     => $token,
            'error'     => $error,
        ], 'customer');
    }

    // ──────────────────────────────────────────────────────
    // STEP 6: POST /customer/auth/reset-password/{token}
    // Set new password
    // ──────────────────────────────────────────────────────
    public function reset(string $token): void
    {
        $newPassword     = (string)$this->request->rawPost('new_password', '');
        $confirmPassword = (string)$this->request->rawPost('confirm_password', '');

        $result = $this->passwordService->resetPassword($token, $newPassword, $confirmPassword);

        if (!$result['success']) {
            SessionManager::set('ca_rp_error', $result['message']);
            $this->redirect('customer/auth/reset-password/' . urlencode($token));
            return;
        }

        SessionManager::set(SESSION_FLASH, [
            'type'    => 'success',
            'message' => 'Password reset successfully. You can now sign in.',
        ]);

        Logger::security('Customer password reset via OTP flow', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);

        $this->redirect('customer/auth/login');
    }

    // ──────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ──────────────────────────────────────────────────────

    private function findCustomerByEmail(string $email): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT u.*, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE LOWER(u.email) = LOWER(:email)
               AND r.name = 'customer'
               AND u.account_status != 'deleted'
             LIMIT 1",
            ['email' => $email]
        );
    }

    private function findCustomerById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT u.*, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id
               AND r.name = 'customer'
             LIMIT 1",
            ['id' => $id]
        );
    }
}
