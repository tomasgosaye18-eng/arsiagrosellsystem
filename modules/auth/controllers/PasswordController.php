<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 7: Authentication
 * =========================================================
 * File        : modules/auth/controllers/PasswordController.php
 * Purpose     : Forgot/reset password controller.
 *               Routes: GET/POST /forgot-password,
 *               GET/POST /reset-password/{token}
 * Dependencies: BaseController, PasswordService.
 * How it works:
 *   showForgot()    → render forgot-password form
 *   forgot()        → POST → generate token → show token on screen
 *                     (In v1: no email; admin shares token manually)
 *   showReset()     → GET  → validate token → render reset form
 *   reset()         → POST → PasswordService::resetPassword() → /login
 *
 * NOTE: In production with email support, the token would be
 *       emailed to the user. In v1, admin copies it to the user.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Auth\Controllers;

use AAM\Core\BaseController;
use AAM\Auth\Services\PasswordService;
use AAM\Core\Logger;

class PasswordController extends BaseController
{
    private PasswordService $passwordService;

    public function __construct()
    {
        parent::__construct();
        $this->passwordService = new PasswordService();
    }

    // ─── Forgot Password ──────────────────────────────────────────────────────

    /**
     * GET /forgot-password
     */
    public function showForgot(): void
    {
        $this->render('auth.forgot_password', [
            'pageTitle' => 'Forgot Password',
        ], 'minimal');
    }

    /**
     * POST /forgot-password
     * Generate a reset token and display it (v1: no email).
     */
    public function forgot(): void
    {
        $this->verifyCsrf();

        $username = trim((string) $this->input('username', ''));

        if (empty($username)) {
            $this->flash(FLASH_ERROR, 'Please enter your username.');
            $this->redirect('/forgot-password');
            return;
        }

        $result = $this->passwordService->generateResetToken($username);

        if (!$result['success']) {
            $this->flash(FLASH_ERROR, $result['message']);
            $this->redirect('/forgot-password');
            return;
        }

        Logger::security('Password reset requested', ['username' => $username]);

        // In v1: display token on screen. Admin copies it to user.
        // In production: this token would be emailed.
        $this->render('auth.forgot_password_sent', [
            'pageTitle' => 'Reset Token Generated',
            'token'     => $result['token'],      // Raw token shown to admin
            'user'      => $result['user'],
            'message'   => $result['message'],
        ], 'minimal');
    }

    // ─── Reset Password ───────────────────────────────────────────────────────

    /**
     * GET /reset-password/{token}
     * Validate token and show the reset form.
     */
    public function showReset(string $token): void
    {
        $user = $this->passwordService->validateResetToken($token);

        if ($user === null) {
            $this->render('auth.reset_password_invalid', [
                'pageTitle' => 'Invalid Reset Link',
            ], 'minimal');
            return;
        }

        $this->render('auth.reset_password', [
            'pageTitle' => 'Reset Password',
            'token'     => $token,
            'username'  => $user['username'],
        ], 'minimal');
    }

    /**
     * POST /reset-password/{token}
     * Process the new password submission.
     */
    public function reset(string $token): void
    {
        $this->verifyCsrf();

        $newPassword     = (string) $this->request->rawPost('new_password', '');
        $confirmPassword = (string) $this->request->rawPost('confirm_password', '');

        $result = $this->passwordService->resetPassword($token, $newPassword, $confirmPassword);

        if (!$result['success']) {
            $this->flash(FLASH_ERROR, $result['message']);
            $this->redirect('/reset-password/' . urlencode($token));
            return;
        }

        $this->flash(FLASH_SUCCESS, $result['message']);
        $this->redirect('/login');
    }
}
