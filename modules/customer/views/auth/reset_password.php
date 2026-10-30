<?php
/**
 * =========================================================
 * AU-AgroSale — Customer Reset Password
 * =========================================================
 * View   : modules/customer/views/auth/reset_password.php
 * Layout : customer template (templates/customer.php)
 *
 * @var \AAM\Core\View $view
 * @var string         $csrfToken
 * @var string         $token      Raw token from URL
 * @var string|null    $error
 */
declare(strict_types=1);

$error = $error ?? null;
?>

<?php $view->section('styles'); ?>
<style>
.ca-rp-page {
    min-height: calc(100vh - 64px);
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--c-bg);
    padding: 32px 16px;
}

.ca-rp-card {
    width: 100%;
    max-width: 440px;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.10), 0 4px 20px rgba(0,0,0,0.06);
    padding: 44px 40px;
}

.ca-rp-icon {
    width: 64px;
    height: 64px;
    background: linear-gradient(135deg, #7c3aed, #4f46e5);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}

.ca-rp-icon svg {
    width: 30px;
    height: 30px;
    stroke: #fff;
    fill: none;
}

.ca-rp-card h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1a1a2e;
    text-align: center;
    margin: 0 0 8px;
}

.ca-rp-card p {
    font-size: 13.5px;
    color: #666;
    text-align: center;
    margin: 0 0 24px;
}

.ca-rp-field {
    position: relative;
    margin-bottom: 12px;
}

.ca-rp-field input {
    width: 100%;
    padding: 13px 44px 13px 16px;
    border: 1.5px solid #e2e6ed;
    border-radius: 10px;
    font-size: 14px;
    color: #1a1a2e;
    background: #f8f9fc;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
    box-sizing: border-box;
}

.ca-rp-field input:focus {
    border-color: #6c47e8;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(108,71,232,0.12);
}

.ca-rp-field input.is-error {
    border-color: #e53e3e;
}

.ca-rp-pw-toggle {
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
    line-height: 1;
    transition: color 0.2s;
    z-index: 2;
}

.ca-rp-pw-toggle:hover { color: #6c47e8; }
.ca-rp-pw-toggle svg { width: 16px; height: 16px; pointer-events: none; }

.ca-rp-field-error {
    font-size: 12px;
    color: #e53e3e;
    margin-top: 4px;
    display: none;
}

.ca-rp-field-error.visible { display: block; }

/* Strength meter */
.ca-rp-strength-bar {
    height: 5px;
    background: #e2e6ed;
    border-radius: 3px;
    overflow: hidden;
    margin: 6px 0 4px;
}

.ca-rp-strength-fill {
    height: 100%;
    width: 0%;
    border-radius: 3px;
    transition: width 0.25s, background 0.25s;
}

.ca-rp-strength-label {
    font-size: 11.5px;
    color: #888;
    margin-bottom: 12px;
    display: block;
}

.ca-rp-error-alert {
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

.ca-rp-submit {
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
    margin-top: 8px;
}

.ca-rp-submit:hover { background: #5a37c8; }
.ca-rp-submit:active { transform: scale(0.98); }
.ca-rp-submit:disabled { background: #a89fcc; cursor: not-allowed; transform: none; }

.ca-rp-back {
    display: block;
    text-align: center;
    margin-top: 14px;
    font-size: 13px;
    color: #6c47e8;
    text-decoration: none;
}

.ca-rp-back:hover { text-decoration: underline; }
</style>
<?php $view->endSection(); ?>

<section class="ca-rp-page">
    <div class="ca-rp-card">

        <div class="ca-rp-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
        </div>

        <h2>Set New Password</h2>
        <p>Choose a strong password for your account.</p>

        <?php if ($error): ?>
            <div class="ca-rp-error-alert" role="alert">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST"
              action="<?= $view->url('customer/auth/reset-password/' . urlencode($token)) ?>"
              novalidate
              id="caRpForm"
              autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <!-- New Password -->
            <div class="ca-rp-field">
                <input type="password"
                       id="rpNewPassword"
                       name="new_password"
                       placeholder="New password"
                       autocomplete="new-password"
                       required
                       aria-label="New password"
                       autofocus>
                <button type="button"
                        class="ca-rp-pw-toggle"
                        data-rp-target="rpNewPassword"
                        aria-label="Show or hide password"
                        tabindex="-1">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>

            <!-- Strength meter -->
            <div class="ca-rp-strength-bar" role="progressbar" aria-label="Password strength" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                <div class="ca-rp-strength-fill" id="rpStrengthFill"></div>
            </div>
            <span class="ca-rp-strength-label" id="rpStrengthLabel" aria-live="polite">
                Min 8 chars, uppercase, lowercase, number required.
            </span>
            <div class="ca-rp-field-error" id="rpNewPasswordError" role="alert"></div>

            <!-- Confirm Password -->
            <div class="ca-rp-field" style="margin-top:4px;">
                <input type="password"
                       id="rpConfirmPassword"
                       name="confirm_password"
                       placeholder="Confirm new password"
                       autocomplete="new-password"
                       required
                       aria-label="Confirm new password">
                <button type="button"
                        class="ca-rp-pw-toggle"
                        data-rp-target="rpConfirmPassword"
                        aria-label="Show or hide confirm password"
                        tabindex="-1">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
            <div class="ca-rp-field-error" id="rpConfirmError" role="alert"></div>

            <button type="submit" class="ca-rp-submit" id="rpSubmit">
                Reset Password
            </button>
        </form>

        <a href="<?= $view->url('customer/auth/login') ?>" class="ca-rp-back">
            ← Back to Sign In
        </a>

    </div>
</section>

<?php $view->section('scripts'); ?>
<script>
(function () {
    'use strict';

    // ── Show / Hide toggles ──────────────────────────────
    var EYE_OPEN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" width="16" height="16"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    var EYE_OFF  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" width="16" height="16"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

    document.querySelectorAll('.ca-rp-pw-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var inp = document.getElementById(btn.getAttribute('data-rp-target'));
            if (!inp) return;
            var showing = inp.type === 'text';
            inp.type = showing ? 'password' : 'text';
            btn.innerHTML = showing ? EYE_OPEN : EYE_OFF;
            btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        });
    });

    // ── Strength meter ───────────────────────────────────
    var pwInp    = document.getElementById('rpNewPassword');
    var fill     = document.getElementById('rpStrengthFill');
    var label    = document.getElementById('rpStrengthLabel');

    var COLORS = ['#e53e3e', '#e53e3e', '#f6ad55', '#68d391', '#48bb78'];

    if (pwInp) {
        pwInp.addEventListener('input', function () {
            var pw = pwInp.value, score = 0, miss = [];
            if (pw.length >= 8)   { score++; } else { miss.push('8+ chars'); }
            if (/[A-Z]/.test(pw)) { score++; } else { miss.push('uppercase'); }
            if (/[a-z]/.test(pw)) { score++; } else { miss.push('lowercase'); }
            if (/[0-9]/.test(pw)) { score++; } else { miss.push('number'); }
            if (fill)  { fill.style.width = (score * 25) + '%'; fill.style.background = COLORS[score]; }
            if (label) {
                label.textContent = miss.length
                    ? 'Needs: ' + miss.join(', ') + '.'
                    : '✓ Password looks good!';
                label.style.color = miss.length ? '#888' : '#16a34a';
            }
        });
    }

    // ── Live confirm match ───────────────────────────────
    var confirmInp = document.getElementById('rpConfirmPassword');
    var confirmErr = document.getElementById('rpConfirmError');

    if (confirmInp && pwInp) {
        confirmInp.addEventListener('input', function () {
            if (!confirmInp.value) { if (confirmErr) confirmErr.classList.remove('visible'); return; }
            var match = confirmInp.value === pwInp.value;
            confirmInp.classList.toggle('is-error', !match);
            if (confirmErr) {
                confirmErr.textContent = match ? '' : 'Passwords do not match.';
                confirmErr.classList.toggle('visible', !match);
            }
        });
    }

    // ── Form validation ──────────────────────────────────
    var form    = document.getElementById('caRpForm');
    var newPwErr = document.getElementById('rpNewPasswordError');
    var submitBtn = document.getElementById('rpSubmit');

    if (form) {
        form.addEventListener('submit', function (e) {
            var valid = true;
            [newPwErr, confirmErr].forEach(function (el) { if (el) { el.textContent = ''; el.classList.remove('visible'); } });
            if (pwInp) pwInp.classList.remove('is-error');
            if (confirmInp) confirmInp.classList.remove('is-error');

            if (!pwInp || !pwInp.value) {
                if (newPwErr) { newPwErr.textContent = 'Password is required.'; newPwErr.classList.add('visible'); }
                if (pwInp) pwInp.classList.add('is-error');
                valid = false;
            } else if (pwInp.value.length < 8) {
                if (newPwErr) { newPwErr.textContent = 'Password must be at least 8 characters.'; newPwErr.classList.add('visible'); }
                if (pwInp) pwInp.classList.add('is-error');
                valid = false;
            }

            if (!confirmInp || !confirmInp.value) {
                if (confirmErr) { confirmErr.textContent = 'Please confirm your password.'; confirmErr.classList.add('visible'); }
                if (confirmInp) confirmInp.classList.add('is-error');
                valid = false;
            } else if (pwInp && confirmInp && pwInp.value !== confirmInp.value) {
                if (confirmErr) { confirmErr.textContent = 'Passwords do not match.'; confirmErr.classList.add('visible'); }
                if (confirmInp) confirmInp.classList.add('is-error');
                valid = false;
            }

            if (!valid) { e.preventDefault(); return; }
            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Resetting…'; }
        });
    }

}());
</script>
<?php $view->endSection(); ?>
