<?php
/**
 * =========================================================
 * AU-AgroSale — Customer Auth: Sliding Sign In / Sign Up
 * =========================================================
 * View   : modules/customer/views/auth/login.php
 * Layout : customer template (templates/customer.php)
 *
 * @var \AAM\Core\View $view
 * @var string         $csrfToken
 * @var array          $errors      Validation errors from previous attempt
 * @var array          $oldInput    Previously submitted non-sensitive fields
 * @var string         $activePanel 'login' | 'register'
 *
 * Design: Sliding Sign In / Sign Up panel
 *   - Light blue/grey background
 *   - White rounded container
 *   - Purple gradient overlay panel
 *   - CSS sliding animation (no library required)
 *   - Social icons are VISUAL DECORATIONS ONLY — no OAuth
 *
 * Security:
 *   - CSRF token embedded in both forms
 *   - Server handles all authentication — JS is UI only
 *   - No credentials stored in localStorage
 * =========================================================
 */
declare(strict_types=1);

$errors      = $errors      ?? [];
$oldInput    = $oldInput    ?? [];
$activePanel = $activePanel ?? 'login';

// Flash message from session (e.g., "please sign in to continue")
$flash = \AAM\Core\SessionManager::get(SESSION_FLASH);
if ($flash) {
    \AAM\Core\SessionManager::delete(SESSION_FLASH);
}

$formError = $errors['_form'] ?? null;
?>

<?php $view->section('styles'); ?>
<style>
/* ── Auth Page Background ─────────────────────────── */
.ca-auth-page {
    min-height: calc(100vh - 64px);
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--c-bg);
    padding: 32px 16px;
}

/* ── Auth Container ───────────────────────────────── */
.ca-auth-container {
    position: relative;
    width: 100%;
    max-width: 780px;
    min-height: 520px;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.12), 0 4px 20px rgba(0,0,0,0.08);
    overflow: hidden;
    display: flex;
}

/* ── Forms Side (left) ────────────────────────────── */
.ca-auth-forms {
    flex: 1;
    position: relative;
    overflow: hidden;
}

/* ── Individual Form Panels ───────────────────────── */
.ca-auth-form-panel {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 44px;
    transition: transform 0.55s cubic-bezier(0.65, 0, 0.35, 1),
                opacity 0.45s ease;
}

.ca-auth-form-panel--signin {
    transform: translateX(0);
    opacity: 1;
}

.ca-auth-form-panel--register {
    transform: translateX(100%);
    opacity: 0;
}

/* Active state — panel slides in */
.ca-auth-container.ca-show-register .ca-auth-form-panel--signin {
    transform: translateX(-100%);
    opacity: 0;
    pointer-events: none;
}

.ca-auth-container.ca-show-register .ca-auth-form-panel--register {
    transform: translateX(0);
    opacity: 1;
    pointer-events: auto;
}

/* ── Form Typography ──────────────────────────────── */
.ca-auth-form-panel h2 {
    font-size: 1.65rem;
    font-weight: 700;
    color: #1a1a2e;
    margin: 0 0 6px;
    text-align: center;
}

/* ── Social Icons (visual only, disabled) ─────────── */
.ca-auth-social {
    display: flex;
    gap: 10px;
    margin: 14px 0 12px;
}

.ca-auth-social-btn {
    width: 40px;
    height: 40px;
    border: 1.5px solid #d0d4dd;
    border-radius: 8px;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: not-allowed;
    opacity: 0.45;
    font-size: 16px;
    color: #555;
    transition: none;
    position: relative;
}

.ca-auth-social-btn[title]:hover::after {
    content: attr(title);
    position: absolute;
    bottom: calc(100% + 6px);
    left: 50%;
    transform: translateX(-50%);
    background: #333;
    color: #fff;
    font-size: 11px;
    white-space: nowrap;
    padding: 4px 8px;
    border-radius: 4px;
    pointer-events: none;
}

.ca-auth-divider {
    font-size: 12px;
    color: #999;
    margin: 0 0 12px;
}

/* ── Form Fields ──────────────────────────────────── */
.ca-auth-field {
    position: relative;
    width: 100%;
    margin-bottom: 12px;
}

.ca-auth-field input {
    width: 100%;
    padding: 13px 16px;
    border: 1.5px solid #e2e6ed;
    border-radius: 10px;
    font-size: 14px;
    color: #1a1a2e;
    background: #f8f9fc;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
    box-sizing: border-box;
}

.ca-auth-field input:focus {
    border-color: #6c47e8;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(108,71,232,0.12);
}

.ca-auth-field input.is-error {
    border-color: #e53e3e;
}

.ca-auth-field-error {
    font-size: 12px;
    color: #e53e3e;
    margin-top: 4px;
    display: none;
}

.ca-auth-field-error.visible {
    display: block;
}

/* ── Forgot Password ──────────────────────────────── */
.ca-auth-forgot {
    align-self: flex-end;
    font-size: 12px;
    color: #6c47e8;
    text-decoration: none;
    margin-bottom: 16px;
    margin-top: -4px;
}

.ca-auth-forgot:hover {
    text-decoration: underline;
}

/* ── Show/Hide Password Toggle ───────────────────── */
.ca-auth-field {
    position: relative;
}

.ca-auth-field--pw input {
    padding-right: 44px;
}

.ca-auth-pw-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    color: #999;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    transition: color 0.2s;
    z-index: 2;
}

.ca-auth-pw-toggle:hover {
    color: #6c47e8;
}

.ca-auth-pw-toggle svg {
    width: 16px;
    height: 16px;
    pointer-events: none;
}

/* ── Alert (form-level error / flash) ────────────── */
.ca-auth-alert {
    width: 100%;
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 13px;
    margin-bottom: 12px;
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.ca-auth-alert--error {
    background: #fff5f5;
    border: 1px solid #feb2b2;
    color: #c53030;
}

.ca-auth-alert--warning {
    background: #fffbeb;
    border: 1px solid #f6c344;
    color: #744210;
}

/* ── Submit Button ────────────────────────────────── */
.ca-auth-submit {
    width: 100%;
    padding: 13px;
    background: #6c47e8;
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.08em;
    cursor: pointer;
    transition: background 0.2s, transform 0.1s;
    text-transform: uppercase;
    margin-top: 4px;
}

.ca-auth-submit:hover {
    background: #5a37c8;
}

.ca-auth-submit:active {
    transform: scale(0.98);
}

.ca-auth-submit:disabled {
    background: #a89fcc;
    cursor: not-allowed;
    transform: none;
}

/* ── Overlay Panel (purple gradient) ─────────────── */
.ca-auth-overlay {
    width: 280px;
    flex-shrink: 0;
    background: linear-gradient(135deg, #7c3aed 0%, #4f46e5 60%, #3730a3 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 48px 32px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

/* Decorative circle in overlay */
.ca-auth-overlay::before {
    content: '';
    position: absolute;
    width: 260px;
    height: 260px;
    border-radius: 50%;
    background: rgba(255,255,255,0.06);
    bottom: -60px;
    right: -60px;
}

.ca-auth-overlay__panel {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 48px 32px;
    text-align: center;
    transition: opacity 0.35s ease, transform 0.45s cubic-bezier(0.65, 0, 0.35, 1);
}

.ca-auth-overlay__panel--signin {
    opacity: 1;
    transform: translateX(0);
}

.ca-auth-overlay__panel--register {
    opacity: 0;
    transform: translateX(20px);
    pointer-events: none;
}

.ca-auth-container.ca-show-register .ca-auth-overlay__panel--signin {
    opacity: 0;
    transform: translateX(-20px);
    pointer-events: none;
}

.ca-auth-container.ca-show-register .ca-auth-overlay__panel--register {
    opacity: 1;
    transform: translateX(0);
    pointer-events: auto;
}

.ca-auth-overlay h3 {
    font-size: 1.4rem;
    font-weight: 700;
    color: #fff;
    margin: 0 0 12px;
}

.ca-auth-overlay p {
    font-size: 13px;
    color: rgba(255,255,255,0.82);
    line-height: 1.6;
    margin: 0 0 28px;
}

.ca-auth-overlay-btn {
    display: inline-block;
    padding: 11px 32px;
    border: 2px solid rgba(255,255,255,0.85);
    border-radius: 30px;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    cursor: pointer;
    background: transparent;
    transition: background 0.2s, color 0.2s;
}

.ca-auth-overlay-btn:hover {
    background: rgba(255,255,255,0.15);
}

/* ── Mobile: stack vertically ─────────────────────── */
@media (max-width: 640px) {
    .ca-auth-container {
        flex-direction: column;
        max-width: 100%;
        border-radius: 16px;
        min-height: auto;
    }

    .ca-auth-overlay {
        width: 100%;
        min-height: 150px;
        padding: 28px 24px;
        order: -1;
    }

    .ca-auth-overlay::before { display: none; }

    .ca-auth-overlay__panel {
        position: relative;
        inset: auto;
        padding: 0;
    }

    .ca-auth-overlay__panel--register {
        display: none;
    }

    .ca-auth-container.ca-show-register .ca-auth-overlay__panel--signin {
        display: none;
    }

    .ca-auth-container.ca-show-register .ca-auth-overlay__panel--register {
        display: flex;
    }

    .ca-auth-forms {
        min-height: 460px;
    }

    .ca-auth-form-panel {
        padding: 28px 24px;
    }
}
</style>
<?php $view->endSection(); ?>

<section class="ca-auth-page">
    <div class="ca-auth-container <?= $activePanel === 'register' ? 'ca-show-register' : '' ?>"
         id="caAuthContainer">

        <!-- ── Forms Side ──────────────────────────────────────── -->
        <div class="ca-auth-forms">

            <!-- Sign In Panel -->
            <div class="ca-auth-form-panel ca-auth-form-panel--signin" id="caSignInPanel">
                <h2>Sign In</h2>

                <!-- Social icons — visual decoration only, no OAuth -->
                <div class="ca-auth-social" aria-label="Social login — not available" role="presentation">
                    <button type="button"
                            class="ca-auth-social-btn"
                            disabled
                            aria-disabled="true"
                            aria-label="Google login not available"
                            title="Social login not available">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                        </svg>
                    </button>
                    <button type="button"
                            class="ca-auth-social-btn"
                            disabled
                            aria-disabled="true"
                            aria-label="Facebook login not available"
                            title="Social login not available">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="#1877F2" aria-hidden="true">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                    </button>
                    <button type="button"
                            class="ca-auth-social-btn"
                            disabled
                            aria-disabled="true"
                            aria-label="GitHub login not available"
                            title="Social login not available">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="#24292e" aria-hidden="true">
                            <path d="M12 0C5.374 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/>
                        </svg>
                    </button>
                    <button type="button"
                            class="ca-auth-social-btn"
                            disabled
                            aria-disabled="true"
                            aria-label="LinkedIn login not available"
                            title="Social login not available">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="#0077B5" aria-hidden="true">
                            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                        </svg>
                    </button>
                </div>

                <p class="ca-auth-divider">or use your email &amp; password</p>

                <?php if ($formError && $activePanel === 'login'): ?>
                    <div class="ca-auth-alert ca-auth-alert--error" role="alert">
                        <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                        <?= e($formError) ?>
                    </div>
                <?php endif; ?>

                <?php if ($flash && $activePanel !== 'register'): ?>
                    <div class="ca-auth-alert ca-auth-alert--<?= e($flash['type'] ?? 'warning') ?>" role="alert">
                        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                        <?= e($flash['message'] ?? '') ?>
                    </div>
                <?php endif; ?>

                <form method="POST"
                      action="<?= $view->url('customer/auth/login') ?>"
                      id="caSignInForm"
                      novalidate
                      style="width:100%">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <div class="ca-auth-field">
                        <input type="email"
                               id="signInEmail"
                               name="email"
                               placeholder="Email"
                               value="<?= e($oldInput['email'] ?? '') ?>"
                               autocomplete="email"
                               required
                               maxlength="191"
                               aria-label="Email address"
                               class="<?= isset($errors['email']) && $activePanel === 'login' ? 'is-error' : '' ?>">
                        <?php if (isset($errors['email']) && $activePanel === 'login'): ?>
                            <div class="ca-auth-field-error visible" role="alert"><?= e($errors['email']) ?></div>
                        <?php else: ?>
                            <div class="ca-auth-field-error" id="signInEmailError" role="alert"></div>
                        <?php endif; ?>
                    </div>

                    <div class="ca-auth-field ca-auth-field--pw">
                        <input type="password"
                               id="signInPassword"
                               name="password"
                               placeholder="Password"
                               autocomplete="current-password"
                               required
                               aria-label="Password"
                               class="<?= isset($errors['password']) && $activePanel === 'login' ? 'is-error' : '' ?>">
                        <button type="button"
                                class="ca-auth-pw-toggle"
                                data-pw-target="signInPassword"
                                aria-label="Show or hide password"
                                tabindex="-1">
                            <!-- eye icon -->
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                        <?php if (isset($errors['password']) && $activePanel === 'login'): ?>
                            <div class="ca-auth-field-error visible" role="alert"><?= e($errors['password']) ?></div>
                        <?php else: ?>
                            <div class="ca-auth-field-error" id="signInPasswordError" role="alert"></div>
                        <?php endif; ?>
                    </div>

                    <a href="<?= $view->url('customer/auth/forgot-password') ?>" class="ca-auth-forgot">
                        Forgot your password?
                    </a>

                    <button type="submit" class="ca-auth-submit" id="signInSubmit">
                        Sign In
                    </button>
                </form>
            </div><!-- /sign in panel -->

            <!-- Sign Up Panel -->
            <div class="ca-auth-form-panel ca-auth-form-panel--register" id="caRegisterPanel">
                <h2>Create Account</h2>

                <!-- Social icons — visual decoration only -->
                <div class="ca-auth-social" aria-label="Social login — not available" role="presentation">
                    <button type="button" class="ca-auth-social-btn" disabled aria-disabled="true" title="Social login not available">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                        </svg>
                    </button>
                    <button type="button" class="ca-auth-social-btn" disabled aria-disabled="true" title="Social login not available">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="#1877F2" aria-hidden="true">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                    </button>
                    <button type="button" class="ca-auth-social-btn" disabled aria-disabled="true" title="Social login not available">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="#24292e" aria-hidden="true">
                            <path d="M12 0C5.374 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/>
                        </svg>
                    </button>
                    <button type="button" class="ca-auth-social-btn" disabled aria-disabled="true" title="Social login not available">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="#0077B5" aria-hidden="true">
                            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                        </svg>
                    </button>
                </div>

                <p class="ca-auth-divider">or use your email for registration</p>

                <?php if ($formError && $activePanel === 'register'): ?>
                    <div class="ca-auth-alert ca-auth-alert--error" role="alert">
                        <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                        <?= e($formError) ?>
                    </div>
                <?php endif; ?>

                <form method="POST"
                      action="<?= $view->url('customer/auth/register') ?>"
                      id="caRegisterForm"
                      novalidate
                      style="width:100%">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <div class="ca-auth-field">
                        <input type="text"
                               id="regFullName"
                               name="full_name"
                               placeholder="Full Name"
                               value="<?= e($oldInput['full_name'] ?? '') ?>"
                               autocomplete="name"
                               required
                               maxlength="160"
                               aria-label="Full name"
                               class="<?= isset($errors['full_name']) ? 'is-error' : '' ?>">
                        <?php if (isset($errors['full_name']) && $activePanel === 'register'): ?>
                            <div class="ca-auth-field-error visible" role="alert"><?= e($errors['full_name']) ?></div>
                        <?php else: ?>
                            <div class="ca-auth-field-error" id="regFullNameError" role="alert"></div>
                        <?php endif; ?>
                    </div>

                    <div class="ca-auth-field">
                        <input type="email"
                               id="regEmail"
                               name="email"
                               placeholder="Email"
                               value="<?= e($oldInput['email'] ?? '') ?>"
                               autocomplete="email"
                               required
                               maxlength="191"
                               aria-label="Email address"
                               class="<?= isset($errors['email']) && $activePanel === 'register' ? 'is-error' : '' ?>">
                        <?php if (isset($errors['email']) && $activePanel === 'register'): ?>
                            <div class="ca-auth-field-error visible" role="alert"><?= e($errors['email']) ?></div>
                        <?php else: ?>
                            <div class="ca-auth-field-error" id="regEmailError" role="alert"></div>
                        <?php endif; ?>
                    </div>

                    <div class="ca-auth-field ca-auth-field--pw">
                        <input type="password"
                               id="regPassword"
                               name="password"
                               placeholder="Password"
                               autocomplete="new-password"
                               required
                               aria-label="Password"
                               class="<?= isset($errors['password']) && $activePanel === 'register' ? 'is-error' : '' ?>">
                        <button type="button"
                                class="ca-auth-pw-toggle"
                                data-pw-target="regPassword"
                                aria-label="Show or hide password"
                                tabindex="-1">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                        <?php if (isset($errors['password']) && $activePanel === 'register'): ?>
                            <div class="ca-auth-field-error visible" role="alert"><?= e($errors['password']) ?></div>
                        <?php else: ?>
                            <div class="ca-auth-field-error" id="regPasswordError" role="alert"></div>
                        <?php endif; ?>
                    </div>

                    <div class="ca-auth-field ca-auth-field--pw">
                        <input type="password"
                               id="regConfirmPassword"
                               name="confirm_password"
                               placeholder="Confirm Password"
                               autocomplete="new-password"
                               required
                               aria-label="Confirm password"
                               class="<?= isset($errors['confirm_password']) ? 'is-error' : '' ?>">
                        <button type="button"
                                class="ca-auth-pw-toggle"
                                data-pw-target="regConfirmPassword"
                                aria-label="Show or hide confirm password"
                                tabindex="-1">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                        <?php if (isset($errors['confirm_password']) && $activePanel === 'register'): ?>
                            <div class="ca-auth-field-error visible" role="alert"><?= e($errors['confirm_password']) ?></div>
                        <?php else: ?>
                            <div class="ca-auth-field-error" id="regConfirmError" role="alert"></div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="ca-auth-submit" id="regSubmit">
                        Sign Up
                    </button>
                </form>
            </div><!-- /register panel -->

        </div><!-- /.ca-auth-forms -->

        <!-- ── Overlay (purple gradient panel) ────────────────────── -->
        <div class="ca-auth-overlay" aria-hidden="true">

            <!-- Panel shown when Sign In is active -->
            <div class="ca-auth-overlay__panel ca-auth-overlay__panel--signin">
                <h3>Hello, Friend!</h3>
                <p>Register with your personal details to use all site features</p>
                <button type="button"
                        class="ca-auth-overlay-btn"
                        id="caShowRegisterBtn"
                        aria-label="Switch to Sign Up form">
                    SIGN UP
                </button>
            </div>

            <!-- Panel shown when Sign Up is active -->
            <div class="ca-auth-overlay__panel ca-auth-overlay__panel--register">
                <h3>Welcome Back!</h3>
                <p>Enter your personal details to use all of our site features</p>
                <button type="button"
                        class="ca-auth-overlay-btn"
                        id="caShowSignInBtn"
                        aria-label="Switch to Sign In form">
                    SIGN IN
                </button>
            </div>

        </div><!-- /.ca-auth-overlay -->

    </div><!-- /.ca-auth-container -->
</section>

<?php $view->section('scripts'); ?>
<script>
/**
 * Customer Auth — Sliding Panel UI
 *
 * RESPONSIBILITY: Animation, UI validation feedback, loading states only.
 * NEVER responsible for authentication logic, session state, or redirects.
 */
(function () {
    'use strict';

    var container    = document.getElementById('caAuthContainer');
    var showRegBtn   = document.getElementById('caShowRegisterBtn');
    var showSignBtn  = document.getElementById('caShowSignInBtn');
    var signInForm   = document.getElementById('caSignInForm');
    var regForm      = document.getElementById('caRegisterForm');
    var signInSubmit = document.getElementById('signInSubmit');
    var regSubmit    = document.getElementById('regSubmit');

    if (!container) return;

    // ── Panel switching ──────────────────────────────
    function showRegister() {
        container.classList.add('ca-show-register');
        var firstField = document.getElementById('regFullName');
        if (firstField) { setTimeout(function(){ firstField.focus(); }, 300); }
    }

    function showSignIn() {
        container.classList.remove('ca-show-register');
        var firstField = document.getElementById('signInEmail');
        if (firstField) { setTimeout(function(){ firstField.focus(); }, 300); }
    }

    if (showRegBtn) { showRegBtn.addEventListener('click', showRegister); }
    if (showSignBtn) { showSignBtn.addEventListener('click', showSignIn); }

    // ── Sign In — client-side validation feedback ────
    if (signInForm) {
        signInForm.addEventListener('submit', function (e) {
            var email    = document.getElementById('signInEmail');
            var password = document.getElementById('signInPassword');
            var emailErr = document.getElementById('signInEmailError');
            var passErr  = document.getElementById('signInPasswordError');
            var valid    = true;

            if (emailErr) emailErr.classList.remove('visible');
            if (passErr)  passErr.classList.remove('visible');
            if (email)    email.classList.remove('is-error');
            if (password) password.classList.remove('is-error');

            if (email && !email.value.trim()) {
                if (emailErr) { emailErr.textContent = 'Email address is required.'; emailErr.classList.add('visible'); }
                if (email)    email.classList.add('is-error');
                valid = false;
            } else if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
                if (emailErr) { emailErr.textContent = 'Please enter a valid email address.'; emailErr.classList.add('visible'); }
                if (email)    email.classList.add('is-error');
                valid = false;
            }

            if (password && !password.value) {
                if (passErr) { passErr.textContent = 'Password is required.'; passErr.classList.add('visible'); }
                if (password) password.classList.add('is-error');
                valid = false;
            }

            if (!valid) { e.preventDefault(); return; }

            // Loading state
            if (signInSubmit) {
                signInSubmit.disabled = true;
                signInSubmit.textContent = 'Signing in…';
            }
        });
    }

    // ── Register — client-side validation feedback ───
    if (regForm) {
        regForm.addEventListener('submit', function (e) {
            var fullName    = document.getElementById('regFullName');
            var email       = document.getElementById('regEmail');
            var password    = document.getElementById('regPassword');
            var confirm     = document.getElementById('regConfirmPassword');
            var nameErr     = document.getElementById('regFullNameError');
            var emailErr    = document.getElementById('regEmailError');
            var passErr     = document.getElementById('regPasswordError');
            var confirmErr  = document.getElementById('regConfirmError');
            var valid       = true;

            [nameErr, emailErr, passErr, confirmErr].forEach(function(el){
                if (el) el.classList.remove('visible');
            });
            [fullName, email, password, confirm].forEach(function(el){
                if (el) el.classList.remove('is-error');
            });

            if (fullName && !fullName.value.trim()) {
                if (nameErr) { nameErr.textContent = 'Full name is required.'; nameErr.classList.add('visible'); }
                if (fullName) fullName.classList.add('is-error');
                valid = false;
            }

            if (email && !email.value.trim()) {
                if (emailErr) { emailErr.textContent = 'Email address is required.'; emailErr.classList.add('visible'); }
                if (email) email.classList.add('is-error');
                valid = false;
            } else if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
                if (emailErr) { emailErr.textContent = 'Please enter a valid email address.'; emailErr.classList.add('visible'); }
                if (email) email.classList.add('is-error');
                valid = false;
            }

            if (password && !password.value) {
                if (passErr) { passErr.textContent = 'Password is required.'; passErr.classList.add('visible'); }
                if (password) password.classList.add('is-error');
                valid = false;
            } else if (password && password.value.length < 8) {
                if (passErr) { passErr.textContent = 'Password must be at least 8 characters.'; passErr.classList.add('visible'); }
                if (password) password.classList.add('is-error');
                valid = false;
            }

            if (confirm && !confirm.value) {
                if (confirmErr) { confirmErr.textContent = 'Please confirm your password.'; confirmErr.classList.add('visible'); }
                if (confirm) confirm.classList.add('is-error');
                valid = false;
            } else if (password && confirm && confirm.value && password.value !== confirm.value) {
                if (confirmErr) { confirmErr.textContent = 'Passwords do not match.'; confirmErr.classList.add('visible'); }
                if (confirm) confirm.classList.add('is-error');
                valid = false;
            }

            if (!valid) { e.preventDefault(); return; }

            // Loading state
            if (regSubmit) {
                regSubmit.disabled = true;
                regSubmit.textContent = 'Creating account…';
            }
        });

        // Live password match preview
        var confirmField = document.getElementById('regConfirmPassword');
        var passField    = document.getElementById('regPassword');
        if (confirmField && passField) {
            confirmField.addEventListener('input', function () {
                var err = document.getElementById('regConfirmError');
                if (!confirmField.value) { if (err) err.classList.remove('visible'); return; }
                if (passField.value !== confirmField.value) {
                    confirmField.classList.add('is-error');
                    if (err) { err.textContent = 'Passwords do not match.'; err.classList.add('visible'); }
                } else {
                    confirmField.classList.remove('is-error');
                    if (err) err.classList.remove('visible');
                }
            });
        }
    }

    // ── Show / Hide Password Toggles ────────────────────
    var EYE_OPEN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" width="16" height="16"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    var EYE_OFF  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" width="16" height="16"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

    document.querySelectorAll('.ca-auth-pw-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-pw-target');
            var inp = document.getElementById(targetId);
            if (!inp) return;
            var showing = inp.type === 'text';
            inp.type = showing ? 'password' : 'text';
            btn.innerHTML = showing ? EYE_OPEN : EYE_OFF;
            btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        });
    });

}());
</script>
<?php $view->endSection(); ?>
