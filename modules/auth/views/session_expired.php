<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 7
 * =========================================================
 * File     : modules/auth/views/session_expired.php
 * Layout   : templates/minimal.php
 * Purpose  : Shown when the user's session has expired
 *            due to inactivity, or when the ?expired=1
 *            parameter is detected on the login page.
 *            Also rendered by the session timeout countdown.
 * Dependencies: AuthController, SessionManager
 * =========================================================
 *
 * @var \AAM\Core\View $view
 */
declare(strict_types=1);
?>

<div class="text-center mb-4">
    <div class="bg-warning bg-opacity-10 border border-warning rounded-3 p-3 d-inline-block mb-3">
        <i class="bi bi-clock-history fs-2 text-warning" aria-hidden="true"></i>
    </div>
    <h2 class="h5 fw-bold mb-1">Session Expired</h2>
    <p class="text-muted small mb-0">
        Your session has ended due to inactivity.
        Please log in again to continue.
    </p>
</div>

<div class="alert alert-info d-flex gap-2 mb-4 small" role="alert">
    <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
    <div>
        For security, sessions automatically expire after
        <strong>30 minutes</strong> of inactivity.
        Your work has been saved up to your last action.
    </div>
</div>

<div class="d-grid">
    <a href="<?= url('login') ?>" class="btn btn-primary btn-lg fw-semibold">
        <i class="bi bi-box-arrow-in-right me-2"></i>Log In Again
    </a>
</div>

<div class="mt-4 text-center text-muted small">
    <i class="bi bi-shield-check me-1 text-success"></i>
    Your data is secure. No information was lost.
</div>
