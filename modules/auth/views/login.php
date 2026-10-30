<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 7
 * View     : modules/auth/views/login.php
 * Layout   : templates/minimal.php
 * Purpose  : Login form — username + password + remember me.
 *
 * ── AUTHENTICATION LAYER IS UNCHANGED ─────────────────────
 * Field names, IDs, POST action, CSRF, error variables, and
 * all session-based validation logic are identical to the
 * original. Only the HTML presentation has been upgraded.
 * ──────────────────────────────────────────────────────────
 *
 * @var \AAM\Core\View $view
 * @var string         $csrfToken
 */
declare(strict_types=1);

/* ── Preserve original session read logic ─────────────── */
$oldCredential  = \AAM\Core\SessionManager::get('old_input', [])['credential'] ?? '';
$valErrors      = \AAM\Core\SessionManager::get('validation_errors', []);
\AAM\Core\SessionManager::delete('old_input');
\AAM\Core\SessionManager::delete('validation_errors');

/* Convenience booleans */
$credError = !empty($valErrors['credential']);
$pwError   = !empty($valErrors['password']);
?>

<!-- ── Form title ────────────────────────────────────── -->
<p class="aam-login-form-title">Welcome back</p>
<p class="aam-login-form-sub">Sign in to continue to your account</p>

<!-- ================================================================
     FORM
     action / method / novalidate / data-validate are UNCHANGED.
     Field names (credential, password, remember_me) are UNCHANGED.
     CSRF token injection is UNCHANGED.
     ================================================================ -->
<form method="POST"
      action="<?= url('login') ?>"
      novalidate
      data-validate="true"
      autocomplete="on"
      id="aam-login-form">

    <?php csrf_input(); ?>

    <!-- ── Credential Field ─────────────────────────── -->
    <div class="aam-login-field">

        <label for="credential" class="aam-login-label">
            Username or Employee ID
        </label>

        <div class="aam-login-input-wrap <?= $credError ? 'has-error' : '' ?>">
            <span class="aam-login-input-icon" aria-hidden="true">
                <i class="bi bi-person"></i>
            </span>
            <input type="text"
                   class="aam-login-input"
                   id="credential"
                   name="credential"
                   value="<?= e($oldCredential) ?>"
                   placeholder="Enter username or phone number"
                   autocomplete="username"
                   data-rules="required|min:3"
                   data-label="Username"
                   autofocus
                   required
                   aria-describedby="<?= $credError ? 'cred-error' : '' ?>"
                   aria-invalid="<?= $credError ? 'true' : 'false' ?>">
        </div>

        <?php if ($credError): ?>
            <div class="aam-login-field-error" id="cred-error" role="alert">
                <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                <?= e($valErrors['credential'][0]) ?>
            </div>
        <?php endif; ?>

    </div>
    <!-- /.aam-login-field -->

    <!-- ── Password Field ───────────────────────────── -->
    <div class="aam-login-field">

        <label for="password" class="aam-login-label">Password</label>

        <div class="aam-login-input-wrap <?= $pwError ? 'has-error' : '' ?>">
            <span class="aam-login-input-icon" aria-hidden="true">
                <i class="bi bi-lock"></i>
            </span>
            <input type="password"
                   class="aam-login-input"
                   id="password"
                   name="password"
                   placeholder="Enter your password"
                   autocomplete="current-password"
                   data-rules="required"
                   data-label="Password"
                   required
                   aria-describedby="<?= $pwError ? 'pw-error' : 'capsWarnRegion' ?>"
                   aria-invalid="<?= $pwError ? 'true' : 'false' ?>">

            <!-- Password visibility toggle (frontend-only — does NOT affect POST) -->
            <button type="button"
                    class="aam-login-pw-toggle"
                    id="togglePassword"
                    aria-label="Show password"
                    title="Toggle password visibility">
                <i class="bi bi-eye" id="toggleIcon" aria-hidden="true"></i>
            </button>
        </div>

        <?php if ($pwError): ?>
            <div class="aam-login-field-error" id="pw-error" role="alert">
                <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                <?= e($valErrors['password'][0]) ?>
            </div>
        <?php endif; ?>

        <!-- Caps Lock warning — injected by JS -->
        <div class="aam-login-capslock" id="capsWarnRegion" style="display:none;" role="status" aria-live="polite">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            <span>Caps Lock is on</span>
        </div>

    </div>
    <!-- /.aam-login-field -->

    <!-- ── Remember me + Forgot password ──────────── -->
    <div class="aam-login-options">

        <label class="aam-login-check-label" for="remember_me">
            <input type="checkbox"
                   id="remember_me"
                   name="remember_me"
                   value="1">
            Remember me for 30 days
        </label>



    </div>

    <!-- ── Submit button ────────────────────────────── -->
    <button type="submit"
            class="aam-login-btn"
            id="loginBtn"
            aria-label="Sign in to AAM">
        <i class="bi bi-box-arrow-in-right" aria-hidden="true" id="loginBtnIcon"></i>
        <span id="loginBtnText">Sign In</span>
    </button>

</form>

<!-- ── Help text ────────────────────────────────────── -->
<div class="aam-login-help" aria-label="Login help">
    <p class="aam-login-help-row">
        <i class="bi bi-shield-check text-success" aria-hidden="true"></i>
        Secure login &mdash; your connection is encrypted
    </p>
    <p class="aam-login-help-row">
        <i class="bi bi-headset" aria-hidden="true"></i>
        Having trouble? Contact your <strong>System Administrator</strong>
    </p>
</div>

<!-- ================================================================
     JAVASCRIPT — frontend only.
     1. Password toggle  : type="password" ↔ type="text" ONLY.
     2. Caps Lock warning: cosmetic only.
     3. Loading state    : disables button during submit,
                           re-enables after 10 s (fallback).
     NONE of these touch the POST data or authentication flow.
     ================================================================ -->
<?php $view->section('scripts'); ?>
<script>
(function () {
    'use strict';

    /* ── 1. Password visibility toggle ───────────────── */
    var toggleBtn  = document.getElementById('togglePassword');
    var pwInput    = document.getElementById('password');
    var toggleIcon = document.getElementById('toggleIcon');

    if (toggleBtn && pwInput) {
        toggleBtn.addEventListener('click', function () {
            if (pwInput.type === 'password') {
                pwInput.type         = 'text';
                toggleIcon.className = 'bi bi-eye-slash';
                toggleBtn.setAttribute('aria-label', 'Hide password');
            } else {
                pwInput.type         = 'password';
                toggleIcon.className = 'bi bi-eye';
                toggleBtn.setAttribute('aria-label', 'Show password');
            }
        });
    }

    /* ── 2. Caps Lock warning ─────────────────────────── */
    var capsRegion = document.getElementById('capsWarnRegion');
    if (pwInput && capsRegion) {
        pwInput.addEventListener('keyup', function (e) {
            capsRegion.style.display = e.getModifierState('CapsLock') ? '' : 'none';
        });
        pwInput.addEventListener('blur', function () {
            capsRegion.style.display = 'none';
        });
    }

    /* ── 3. Loading state on submit ───────────────────── */
    var form       = document.getElementById('aam-login-form');
    var loginBtn   = document.getElementById('loginBtn');
    var loginBtnIcon = document.getElementById('loginBtnIcon');
    var loginBtnText = document.getElementById('loginBtnText');

    if (form && loginBtn) {
        form.addEventListener('submit', function () {
            loginBtn.disabled = true;

            /* Swap icon → spinner */
            if (loginBtnIcon) {
                var spinner = document.createElement('span');
                spinner.className = 'aam-btn-spinner';
                spinner.setAttribute('role', 'status');
                spinner.setAttribute('aria-hidden', 'true');
                loginBtnIcon.replaceWith(spinner);
            }
            if (loginBtnText) {
                loginBtnText.textContent = 'Signing in\u2026';
            }

            /* Safety re-enable after 10 s (page reload / server error) */
            setTimeout(function () {
                loginBtn.disabled = false;
                if (loginBtnText) loginBtnText.textContent = 'Sign In';
                var sp = loginBtn.querySelector('.aam-btn-spinner');
                if (sp) {
                    var ico = document.createElement('i');
                    ico.className = 'bi bi-box-arrow-in-right';
                    ico.setAttribute('aria-hidden', 'true');
                    ico.id = 'loginBtnIcon';
                    sp.replaceWith(ico);
                }
            }, 10000);
        });
    }

})();
</script>
<?php $view->endSection(); ?>
