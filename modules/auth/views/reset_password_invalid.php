<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 7
 * =========================================================
 * File     : modules/auth/views/reset_password_invalid.php
 * Layout   : templates/minimal.php
 * Purpose  : Shown when a reset token is invalid or expired.
 * Dependencies: PasswordController::showReset()
 * =========================================================
 *
 * @var \AAM\Core\View $view
 */
declare(strict_types=1);
?>

<div class="text-center mb-4">
    <div class="bg-danger bg-opacity-10 border border-danger rounded-3 p-3 d-inline-block mb-3">
        <i class="bi bi-x-circle fs-2 text-danger" aria-hidden="true"></i>
    </div>
    <h2 class="h5 fw-bold mb-1">Invalid or Expired Link</h2>
    <p class="text-muted small mb-0">
        This password reset link is no longer valid.
    </p>
</div>

<div class="alert alert-warning d-flex gap-2 mb-4" role="alert">
    <i class="bi bi-clock-history flex-shrink-0 mt-1"></i>
    <div>
        Reset tokens <strong>expire after 60 minutes</strong> and can only be used once.
        Please request a new token to reset your password.
    </div>
</div>

<div class="d-grid gap-2">
    <a href="<?= url('forgot-password') ?>" class="btn btn-primary">
        <i class="bi bi-arrow-clockwise me-1"></i>Request New Reset Token
    </a>
    <a href="<?= url('login') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Login
    </a>
</div>
