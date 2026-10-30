<?php
/**
 * =========================================================
 * AU-AgroSale — Customer Reset Password Invalid / Expired
 * =========================================================
 * View   : modules/customer/views/auth/reset_password_invalid.php
 * Layout : customer template (templates/customer.php)
 *
 * @var \AAM\Core\View $view
 * @var string         $csrfToken
 */
declare(strict_types=1);
?>

<?php $view->section('styles'); ?>
<style>
.ca-rpi-page {
    min-height: calc(100vh - 64px);
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--c-bg);
    padding: 32px 16px;
}

.ca-rpi-card {
    width: 100%;
    max-width: 420px;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.10), 0 4px 20px rgba(0,0,0,0.06);
    padding: 44px 40px;
    text-align: center;
}

.ca-rpi-icon {
    width: 68px;
    height: 68px;
    background: linear-gradient(135deg, #f97316, #ef4444);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}

.ca-rpi-icon svg {
    width: 32px;
    height: 32px;
    stroke: #fff;
    fill: none;
}

.ca-rpi-card h2 {
    font-size: 1.45rem;
    font-weight: 700;
    color: #1a1a2e;
    margin: 0 0 10px;
}

.ca-rpi-card p {
    font-size: 14px;
    color: #555;
    line-height: 1.7;
    margin: 0 0 28px;
}

.ca-rpi-btn {
    display: block;
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

.ca-rpi-btn:hover { background: #5a37c8; color: #fff; }

.ca-rpi-link {
    display: block;
    text-align: center;
    font-size: 13px;
    color: #6c47e8;
    text-decoration: none;
    margin-top: 8px;
}

.ca-rpi-link:hover { text-decoration: underline; }
</style>
<?php $view->endSection(); ?>

<section class="ca-rpi-page">
    <div class="ca-rpi-card">

        <div class="ca-rpi-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
        </div>

        <h2>Link Expired or Invalid</h2>
        <p>
            This password reset link has expired or has already been used.
            Reset links are valid for <strong>60 minutes</strong> and can only be used once.
        </p>

        <a href="<?= $view->url('customer/auth/forgot-password') ?>" class="ca-rpi-btn">
            Request a New Link
        </a>

        <a href="<?= $view->url('customer/auth/login') ?>" class="ca-rpi-link">
            ← Back to Sign In
        </a>

    </div>
</section>
