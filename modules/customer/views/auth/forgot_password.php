<?php
/**
 * =========================================================
 * AU-AgroSale — Customer Forgot Password
 * =========================================================
 * View   : modules/customer/views/auth/forgot_password.php
 * Layout : customer template (templates/customer.php)
 *
 * @var \AAM\Core\View $view
 * @var string         $csrfToken
 * @var string|null    $error
 */
declare(strict_types=1);

$error = $error ?? null;
?>

<?php $view->section('styles'); ?>
<style>
.ca-fp-page {
    min-height: calc(100vh - 64px);
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--c-bg);
    padding: 32px 16px;
}

.ca-fp-card {
    width: 100%;
    max-width: 440px;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.10), 0 4px 20px rgba(0,0,0,0.06);
    padding: 44px 40px;
}

.ca-fp-icon {
    width: 64px;
    height: 64px;
    background: linear-gradient(135deg, #7c3aed, #4f46e5);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}

.ca-fp-icon svg {
    width: 30px;
    height: 30px;
    color: #fff;
    stroke: #fff;
    fill: none;
}

.ca-fp-card h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1a1a2e;
    text-align: center;
    margin: 0 0 8px;
}

.ca-fp-card p {
    font-size: 13.5px;
    color: #666;
    text-align: center;
    line-height: 1.6;
    margin: 0 0 28px;
}

.ca-fp-field {
    position: relative;
    margin-bottom: 16px;
}

.ca-fp-field input {
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

.ca-fp-field input:focus {
    border-color: #6c47e8;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(108,71,232,0.12);
}

.ca-fp-field input.is-error {
    border-color: #e53e3e;
}

.ca-fp-error {
    width: 100%;
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 13px;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fff5f5;
    border: 1px solid #feb2b2;
    color: #c53030;
}

.ca-fp-submit {
    width: 100%;
    padding: 13px;
    background: #6c47e8;
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.06em;
    cursor: pointer;
    transition: background 0.2s, transform 0.1s;
    text-transform: uppercase;
    margin-top: 4px;
}

.ca-fp-submit:hover { background: #5a37c8; }
.ca-fp-submit:active { transform: scale(0.98); }

.ca-fp-back {
    display: block;
    text-align: center;
    margin-top: 16px;
    font-size: 13px;
    color: #6c47e8;
    text-decoration: none;
}

.ca-fp-back:hover { text-decoration: underline; }

.ca-fp-note {
    margin-top: 24px;
    padding: 12px 16px;
    background: #f0f4ff;
    border-radius: 10px;
    font-size: 12px;
    color: #555;
    line-height: 1.6;
}

.ca-fp-note strong { color: #1a1a2e; }
</style>
<?php $view->endSection(); ?>

<section class="ca-fp-page">
    <div class="ca-fp-card">

        <!-- Icon -->
        <div class="ca-fp-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
        </div>

        <h2>Forgot Password?</h2>
        <p>Enter the email address for your account and we'll send you a reset link.</p>

        <?php if ($error): ?>
            <div class="ca-fp-error" role="alert">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST"
              action="<?= $view->url('customer/auth/forgot-password') ?>"
              novalidate
              id="caFpForm">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <div class="ca-fp-field">
                <input type="email"
                       id="fpEmail"
                       name="email"
                       placeholder="Your email address"
                       autocomplete="email"
                       required
                       maxlength="191"
                       aria-label="Email address"
                       autofocus>
                <div class="ca-auth-field-error" id="fpEmailError" role="alert"></div>
            </div>

            <button type="submit" class="ca-fp-submit" id="fpSubmit">
                Send Reset Link
            </button>
        </form>

        <a href="<?= $view->url('customer/auth/login') ?>" class="ca-fp-back">
            ← Back to Sign In
        </a>

        <div class="ca-fp-note">
            <strong>How it works:</strong> A secure one-time reset link will be generated.
            Tokens expire after <strong>60 minutes</strong>. If you don't receive it,
            contact the shop administrator.
        </div>

    </div>
</section>

<?php $view->section('scripts'); ?>
<script>
(function () {
    'use strict';
    var form  = document.getElementById('caFpForm');
    var email = document.getElementById('fpEmail');
    var err   = document.getElementById('fpEmailError');
    var btn   = document.getElementById('fpSubmit');

    if (!form) return;

    form.addEventListener('submit', function (e) {
        if (err) { err.textContent = ''; err.classList.remove('visible'); }
        if (email) email.classList.remove('is-error');

        if (!email || !email.value.trim()) {
            if (err) { err.textContent = 'Email address is required.'; err.classList.add('visible'); }
            if (email) email.classList.add('is-error');
            e.preventDefault();
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
            if (err) { err.textContent = 'Please enter a valid email address.'; err.classList.add('visible'); }
            if (email) email.classList.add('is-error');
            e.preventDefault();
            return;
        }

        if (btn) { btn.disabled = true; btn.textContent = 'Sending…'; }
    });
}());
</script>
<?php $view->endSection(); ?>
