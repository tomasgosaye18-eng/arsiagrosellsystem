<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 7
 * View     : modules/auth/views/forgot_password.php
 * Layout   : templates/minimal.php
 * Purpose  : Forgot password form — enter username to
 *            generate a reset token.
 * =========================================================
 *
 * @var \AAM\Core\View $view
 * @var string         $csrfToken
 */
declare(strict_types=1);
?>

<div class="text-center mb-4">
    <div class="bg-warning bg-opacity-10 border border-warning rounded-3 p-3 d-inline-block mb-3">
        <i class="bi bi-key fs-2 text-warning" aria-hidden="true"></i>
    </div>
    <h2 class="h5 fw-bold mb-1">Forgot Your Password?</h2>
    <p class="text-muted small mb-0">
        Enter your username below. The administrator will provide you with a reset token.
    </p>
</div>

<form method="POST"
      action="<?= url('forgot-password') ?>"
      novalidate
      data-validate="true">

    <?php csrf_input(); ?>

    <div class="mb-4">
        <label for="username" class="form-label fw-semibold">Username</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0">
                <i class="bi bi-person text-muted" aria-hidden="true"></i>
            </span>
            <input type="text"
                   class="form-control border-start-0"
                   id="username"
                   name="username"
                   placeholder="Enter your username"
                   autocomplete="username"
                   data-rules="required|min:3"
                   data-label="Username"
                   autofocus
                   required>
        </div>
        <small class="text-muted">
            Use the exact username you log in with.
        </small>
    </div>

    <div class="d-grid gap-2">
        <button type="submit" class="btn btn-warning fw-semibold">
            <i class="bi bi-send me-2" aria-hidden="true"></i>
            Request Reset Token
        </button>
        <a href="<?= url('login') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>
            Back to Login
        </a>
    </div>

</form>

<div class="mt-4 p-3 bg-light rounded-3 border">
    <p class="small text-muted mb-0">
        <i class="bi bi-info-circle me-1 text-primary"></i>
        <strong>How it works:</strong> A secure reset token will be generated.
        The System Administrator will give you this token to enter on the next screen.
        Tokens expire after <strong>60 minutes</strong>.
    </p>
</div>
