<?php
/**
 * =========================================================
 * AU-AgroSale — Customer OTP Verification
 * =========================================================
 * View   : modules/customer/views/auth/verify_otp.php
 * Layout : customer template (templates/customer.php)
 *
 * @var \AAM\Core\View $view
 * @var string         $csrfToken
 * @var string         $email       Masked email shown to user
 * @var string|null    $error
 * @var string|null    $devOtp      OTP shown on screen (dev/no-email mode only)
 * @var int            $expiresAt   Unix timestamp when OTP expires
 */
declare(strict_types=1);

$error     = $error     ?? null;
$devOtp    = $devOtp    ?? null;
$expiresAt = $expiresAt ?? 0;

// Mask email: a@gmail.com → a**@gmail.com
function maskEmail(string $email): string {
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) return $email;
    $name   = $parts[0];
    $domain = $parts[1];
    $visible = mb_substr($name, 0, 1);
    $masked  = $visible . str_repeat('*', max(2, mb_strlen($name) - 1));
    return $masked . '@' . $domain;
}
$maskedEmail = maskEmail($email ?? '');
?>

<?php $view->section('styles'); ?>
<style>
.ca-otp-page {
    min-height: calc(100vh - 64px);
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--c-bg);
    padding: 32px 16px;
}

.ca-otp-card {
    width: 100%;
    max-width: 420px;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.10), 0 4px 20px rgba(0,0,0,0.06);
    padding: 44px 40px;
    text-align: center;
}

.ca-otp-icon {
    width: 68px;
    height: 68px;
    background: linear-gradient(135deg, #7c3aed, #4f46e5);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}

.ca-otp-icon svg {
    width: 32px;
    height: 32px;
    stroke: #fff;
    fill: none;
}

.ca-otp-card h2 {
    font-size: 1.45rem;
    font-weight: 700;
    color: #1a1a2e;
    margin: 0 0 8px;
}

.ca-otp-card p {
    font-size: 13.5px;
    color: #555;
    line-height: 1.7;
    margin: 0 0 24px;
}

.ca-otp-card p strong {
    color: #1a1a2e;
}

/* OTP boxes */
.ca-otp-inputs {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-bottom: 8px;
}

.ca-otp-inputs input {
    width: 48px;
    height: 56px;
    border: 2px solid #e2e6ed;
    border-radius: 12px;
    font-size: 22px;
    font-weight: 700;
    text-align: center;
    color: #1a1a2e;
    background: #f8f9fc;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
    caret-color: transparent;
}

.ca-otp-inputs input:focus {
    border-color: #6c47e8;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(108,71,232,0.12);
}

.ca-otp-inputs input.is-error {
    border-color: #e53e3e;
}

/* Hidden combined input for form submission */
#caOtpHidden { display: none; }

.ca-otp-error {
    font-size: 12.5px;
    color: #e53e3e;
    margin-bottom: 14px;
    min-height: 18px;
    display: block;
    text-align: center;
}

/* Timer */
.ca-otp-timer {
    font-size: 12px;
    color: #888;
    margin-bottom: 20px;
    display: block;
}

.ca-otp-timer span {
    font-weight: 700;
    color: #6c47e8;
}

.ca-otp-timer.expired span {
    color: #e53e3e;
}

/* Dev OTP box */
.ca-otp-dev-box {
    background: #fffbeb;
    border: 1.5px dashed #f6c344;
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 20px;
    font-size: 13px;
    color: #744210;
}

.ca-otp-dev-box strong {
    display: block;
    font-size: 28px;
    letter-spacing: 8px;
    color: #1a1a2e;
    margin: 6px 0 4px;
    font-family: monospace;
}

.ca-otp-submit {
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
    margin-bottom: 12px;
}

.ca-otp-submit:hover { background: #5a37c8; }
.ca-otp-submit:active { transform: scale(0.98); }
.ca-otp-submit:disabled { background: #a89fcc; cursor: not-allowed; transform: none; }

.ca-otp-resend {
    font-size: 13px;
    color: #6c47e8;
    cursor: pointer;
    background: none;
    border: none;
    text-decoration: underline;
    padding: 0;
    display: block;
    margin: 0 auto 12px;
}

.ca-otp-back {
    display: block;
    font-size: 13px;
    color: #6c47e8;
    text-decoration: none;
    margin-top: 8px;
}

.ca-otp-back:hover { text-decoration: underline; }
</style>
<?php $view->endSection(); ?>

<section class="ca-otp-page">
    <div class="ca-otp-card">

        <div class="ca-otp-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.82a19.79 19.79 0 01-3.07-8.67A2 2 0 012 1h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 8.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
            </svg>
        </div>

        <h2>Enter OTP</h2>
        <p>
            We sent a 6-digit code to<br>
            <strong><?= e($maskedEmail) ?></strong><br>
            Enter it below to continue.
        </p>

        <?php if ($devOtp): ?>
        <!-- DEV MODE — remove in production when email is configured -->
        <div class="ca-otp-dev-box" role="note" aria-label="Development OTP">
            <span>📧 No email server — your OTP is:</span>
            <strong id="devOtpDisplay"><?= e($devOtp) ?></strong>
            <span style="font-size:11px;">(This box only shows in development mode)</span>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <span class="ca-otp-error" role="alert"><?= e($error) ?></span>
        <?php else: ?>
            <span class="ca-otp-error" id="caOtpError" role="alert"></span>
        <?php endif; ?>

        <!-- Countdown timer -->
        <span class="ca-otp-timer" id="caOtpTimer" aria-live="polite">
            Code expires in <span id="caOtpCountdown">10:00</span>
        </span>

        <form method="POST"
              action="<?= $view->url('customer/auth/verify-otp') ?>"
              id="caOtpForm"
              novalidate>
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="otp" id="caOtpHidden">

            <div class="ca-otp-inputs" role="group" aria-label="Enter 6-digit OTP">
                <input type="text" inputmode="numeric" maxlength="1" class="ca-otp-box" aria-label="Digit 1" autocomplete="off">
                <input type="text" inputmode="numeric" maxlength="1" class="ca-otp-box" aria-label="Digit 2" autocomplete="off">
                <input type="text" inputmode="numeric" maxlength="1" class="ca-otp-box" aria-label="Digit 3" autocomplete="off">
                <input type="text" inputmode="numeric" maxlength="1" class="ca-otp-box" aria-label="Digit 4" autocomplete="off">
                <input type="text" inputmode="numeric" maxlength="1" class="ca-otp-box" aria-label="Digit 5" autocomplete="off">
                <input type="text" inputmode="numeric" maxlength="1" class="ca-otp-box" aria-label="Digit 6" autocomplete="off">
            </div>

            <button type="submit" class="ca-otp-submit" id="caOtpSubmit">
                Verify OTP
            </button>
        </form>

        <a href="<?= $view->url('customer/auth/forgot-password') ?>" class="ca-otp-resend" aria-label="Resend OTP">
            Resend OTP
        </a>

        <a href="<?= $view->url('customer/auth/login') ?>" class="ca-otp-back">
            ← Back to Sign In
        </a>

    </div>
</section>

<?php $view->section('scripts'); ?>
<script>
(function () {
    'use strict';

    var boxes   = Array.from(document.querySelectorAll('.ca-otp-box'));
    var hidden  = document.getElementById('caOtpHidden');
    var form    = document.getElementById('caOtpForm');
    var errEl   = document.getElementById('caOtpError');
    var submitBtn = document.getElementById('caOtpSubmit');

    // ── Auto-advance between boxes ───────────────────────
    boxes.forEach(function (box, i) {
        box.addEventListener('input', function () {
            var val = box.value.replace(/\D/g, '');
            box.value = val ? val[0] : '';
            if (val && i < boxes.length - 1) {
                boxes[i + 1].focus();
            }
            syncHidden();
        });

        box.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && !box.value && i > 0) {
                boxes[i - 1].focus();
                boxes[i - 1].value = '';
                syncHidden();
            }
        });

        box.addEventListener('paste', function (e) {
            e.preventDefault();
            var pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
            pasted.split('').slice(0, 6).forEach(function (ch, j) {
                if (boxes[j]) boxes[j].value = ch;
            });
            var next = Math.min(pasted.length, 5);
            if (boxes[next]) boxes[next].focus();
            syncHidden();
        });
    });

    // Auto-focus first box
    if (boxes[0]) boxes[0].focus();

    function syncHidden() {
        if (hidden) hidden.value = boxes.map(function(b){ return b.value; }).join('');
    }

    // ── Auto-submit when all 6 digits filled ────────────
    function checkAutoSubmit() {
        var val = boxes.map(function(b){ return b.value; }).join('');
        if (val.length === 6 && /^\d{6}$/.test(val)) {
            syncHidden();
            setTimeout(function() { form && form.submit(); }, 300);
        }
    }
    boxes.forEach(function(b){ b.addEventListener('input', checkAutoSubmit); });

    // ── Form validation ──────────────────────────────────
    if (form) {
        form.addEventListener('submit', function (e) {
            syncHidden();
            var val = hidden ? hidden.value : '';
            if (!val || val.length < 6) {
                if (errEl) { errEl.textContent = 'Please enter the complete 6-digit OTP.'; }
                boxes.forEach(function(b){ b.classList.add('is-error'); });
                e.preventDefault();
                return;
            }
            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Verifying…'; }
        });
    }

    // ── Countdown timer ──────────────────────────────────
    var expiresAt = <?= (int)$expiresAt ?>;
    var timerEl   = document.getElementById('caOtpCountdown');
    var timerWrap = document.getElementById('caOtpTimer');

    function updateTimer() {
        var remaining = expiresAt - Math.floor(Date.now() / 1000);
        if (remaining <= 0) {
            if (timerEl)   timerEl.textContent = '0:00';
            if (timerWrap) timerWrap.classList.add('expired');
            if (timerEl)   timerEl.textContent = 'Expired';
            if (timerWrap) timerWrap.innerHTML = '<span>OTP expired.</span> <a href="<?= $view->url('customer/auth/forgot-password') ?>" style="color:#6c47e8;text-decoration:underline;">Request new OTP</a>';
            if (submitBtn) submitBtn.disabled = true;
            return;
        }
        var m = Math.floor(remaining / 60);
        var s = remaining % 60;
        if (timerEl) timerEl.textContent = m + ':' + (s < 10 ? '0' : '') + s;
        setTimeout(updateTimer, 1000);
    }

    if (expiresAt > 0) updateTimer();

    // ── Auto-fill dev OTP into boxes ─────────────────────
    var devOtp = document.getElementById('devOtpDisplay');
    if (devOtp) {
        var code = devOtp.textContent.trim().replace(/\D/g, '');
        if (code.length === 6) {
            code.split('').forEach(function (ch, i) {
                if (boxes[i]) boxes[i].value = ch;
            });
            syncHidden();
            if (boxes[5]) boxes[5].focus();
        }
    }

}());
</script>
<?php $view->endSection(); ?>
