<?php
/**
 * =========================================================
 * AU-AgroSale — Customer Forgot Password Sent / Confirmation
 * =========================================================
 * View   : modules/customer/views/auth/forgot_password_sent.php
 * Layout : customer template (templates/customer.php)
 *
 * Shows a generic success message regardless of whether the
 * email actually exists (prevents email enumeration).
 *
 * In v1 (no email server): if $token and $user are provided,
 * the admin token is displayed so the admin can relay it.
 * In production with email: only show the generic message.
 *
 * @var \AAM\Core\View  $view
 * @var string          $csrfToken
 * @var string|null     $token   Raw reset token (admin-only mode)
 * @var array|null      $user    User row (admin-only mode)
 */
declare(strict_types=1);

$token = $token ?? null;
$user  = $user  ?? null;
?>

<?php $view->section('styles'); ?>
<style>
.ca-fps-page {
    min-height: calc(100vh - 64px);
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--c-bg);
    padding: 32px 16px;
}

.ca-fps-card {
    width: 100%;
    max-width: 440px;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.10), 0 4px 20px rgba(0,0,0,0.06);
    padding: 44px 40px;
    text-align: center;
}

.ca-fps-icon {
    width: 72px;
    height: 72px;
    background: linear-gradient(135deg, #22c55e, #16a34a);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}

.ca-fps-icon svg {
    width: 34px;
    height: 34px;
    stroke: #fff;
    fill: none;
}

.ca-fps-card h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1a1a2e;
    margin: 0 0 10px;
}

.ca-fps-card p {
    font-size: 14px;
    color: #555;
    line-height: 1.7;
    margin: 0 0 24px;
}

/* Admin token display (v1 no-email mode) */
.ca-fps-token-box {
    background: #fffbeb;
    border: 1px solid #f6c344;
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 20px;
    text-align: left;
}

.ca-fps-token-box p {
    font-size: 12.5px;
    color: #744210;
    margin: 0 0 12px;
    text-align: left;
}

.ca-fps-token-row {
    display: flex;
    gap: 8px;
    align-items: center;
}

.ca-fps-token-input {
    flex: 1;
    font-family: monospace;
    font-size: 13px;
    padding: 10px 12px;
    border: 1.5px solid #e2e6ed;
    border-radius: 8px;
    background: #f8f9fc;
    color: #1a1a2e;
    word-break: break-all;
}

.ca-fps-copy-btn {
    padding: 10px 14px;
    background: #fff;
    border: 1.5px solid #e2e6ed;
    border-radius: 8px;
    cursor: pointer;
    color: #6c47e8;
    font-size: 14px;
    transition: background 0.2s, border-color 0.2s;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 4px;
}

.ca-fps-copy-btn:hover {
    background: #f0ebff;
    border-color: #6c47e8;
}

.ca-fps-btn {
    display: block;
    width: 100%;
    padding: 13px;
    background: #6c47e8;
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    text-decoration: none;
    cursor: pointer;
    transition: background 0.2s;
    text-align: center;
    margin-bottom: 12px;
}

.ca-fps-btn:hover { background: #5a37c8; color: #fff; }

.ca-fps-btn-outline {
    display: block;
    text-align: center;
    font-size: 13px;
    color: #6c47e8;
    text-decoration: none;
    margin-top: 8px;
}

.ca-fps-btn-outline:hover { text-decoration: underline; }
</style>
<?php $view->endSection(); ?>

<section class="ca-fps-page">
    <div class="ca-fps-card">

        <div class="ca-fps-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </div>

        <h2>Check Your Email</h2>
        <p>
            A 6-digit OTP has been sent to your email address.
            Please check your inbox and enter the code on the next screen.
        </p>

        <a href="<?= $view->url('customer/auth/verify-otp') ?>" class="ca-fps-btn">
            Enter OTP
        </a>

        <a href="<?= $view->url('customer/auth/forgot-password') ?>" class="ca-fps-btn-outline">
            Try a different email
        </a>

    </div>
</section>

<?php $view->section('scripts'); ?>
<script>
(function () {
    var btn = document.getElementById('caFpsCopyBtn');
    var inp = document.getElementById('caFpsToken');
    if (!btn || !inp) return;

    btn.addEventListener('click', function () {
        navigator.clipboard.writeText(inp.value).then(function () {
            btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg> Copied!';
            btn.style.color = '#16a34a';
            btn.style.borderColor = '#16a34a';
            setTimeout(function () {
                btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg> Copy';
                btn.style.color = '';
                btn.style.borderColor = '';
            }, 2500);
        }).catch(function () {
            inp.select();
            document.execCommand('copy');
        });
    });
}());
</script>
<?php $view->endSection(); ?>
