<?php
/**
 * =========================================================
 * AU-AgroSale — Payment Waiting Screen
 * modules/customer/views/checkout/payment.php
 * =========================================================
 * Phase 7 — Mobile Banking / Dynamic QR Payment
 *
 * Displays the payment QR code and polls for verification.
 * Works in both live (real provider API) and dev modes.
 *
 * CRITICAL: This page NEVER marks payment as paid by itself.
 * Payment = PENDING until the backend verifies with the provider.
 * The JS polls /customer/checkout/verify every 3 seconds.
 *
 * @var array  $order
 * @var int    $orderId
 * @var array  $paymentData
 * @var string $shopName
 * @var \AAM\Core\View $view
 */
declare(strict_types=1);

$order       = $order       ?? [];
$paymentData = $paymentData ?? [];
$orderId     = (int)($orderId ?? ($order['id'] ?? 0));
$shopName    = $shopName ?? ($order['shop_name'] ?? '');

$orderRef    = $order['order_reference']              ?? '';
$payRef      = $paymentData['payment_reference']      ?? ($order['payment_reference'] ?? '');
$amount      = number_format((float)($paymentData['amount'] ?? $order['total_amount'] ?? 0), 2);
$amountRaw   = (float)($paymentData['amount']         ?? $order['total_amount'] ?? 0);
$qrPayload   = $paymentData['qr_payload']             ?? '';
$isLive      = (bool)($paymentData['is_live']         ?? false);
$expiresAt   = $paymentData['expires_at']             ?? ($order['expires_at'] ?? '');
$providerName= $paymentData['provider_name']          ?? 'Mobile Banking';
$displayHint = $paymentData['display_hint']           ?? null;

// If no real QR payload, build a useful one from available data
if (!$qrPayload && $payRef) {
    $qrPayload = $payRef; // At minimum, the unique payment reference is scannable
}

$expiresTs   = $expiresAt ? strtotime($expiresAt) * 1000 : 0;
// Pass server's current time to let JS compute relative expiry correctly
// This avoids client/server clock skew and timezone mismatches
$serverNowTs = time() * 1000;
$isDev       = defined('APP_ENV') && APP_ENV !== 'production';
$ttlMinutes  = $paymentData['ttl_minutes'] ?? 15;
?>
<?php $view->section('styles'); ?>
<link rel="stylesheet" href="<?= $view->url('public/assets/customer/css/checkout.css') ?>?v=<?= filemtime(PUBLIC_PATH . '/assets/customer/css/checkout.css') ?>">
<?php $view->endSection(); ?>

<!-- ── Breadcrumb toolbar ──────────────────────────────────── -->
<div class="cp-toolbar">
    <div class="c-container cp-toolbar__inner">
        <a href="<?= $view->url('customer/checkout') ?>"
           class="c-back-nav"
           aria-label="Back to Checkout">
            <i class="bi bi-arrow-left c-back-nav__icon" aria-hidden="true"></i>
            <span class="c-back-nav__label">Back to Checkout</span>
        </a>
        <nav class="cp-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= $view->url('customer') ?>">Home</a>
            <span class="cp-breadcrumb__sep" aria-hidden="true">›</span>
            <span>Checkout</span>
            <span class="cp-breadcrumb__sep" aria-hidden="true">›</span>
            <span aria-current="page">Payment</span>
        </nav>
        <?php if ($shopName): ?>
        <div class="cp-shop-context" aria-label="Pickup shop">
            <i class="bi bi-shop" aria-hidden="true"></i>
            <span><?= e($shopName) ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     PAYMENT SCREEN — 2-column on desktop, single-column mobile
     ══════════════════════════════════════════════════════════ -->
<div class="c-container cpy-page-layout" id="customer-main">

    <!-- ── LEFT / MAIN COLUMN ─────────────────────────────── -->
    <div class="cpy-main-col">

        <div class="cpy-card c-animate-fade-up" id="paymentCard">

            <!-- Header -->
            <div class="cpy-header">
                <div class="cpy-header__icon-wrap" aria-hidden="true">
                    <i class="bi bi-phone-fill cpy-header__icon"></i>
                </div>
                <h1 class="cpy-header__title">Complete Your Payment</h1>
                <p class="cpy-header__sub">Order <strong class="cpy-header__ref"><?= e($orderRef) ?></strong></p>
            </div>

            <!-- Amount + expiry row -->
            <div class="cpy-amount-row">
                <div class="cpy-amount">
                    <span class="cpy-amount__label">Amount to Pay</span>
                    <strong class="cpy-amount__value" aria-live="polite">ETB <?= $amount ?></strong>
                </div>
                <?php if ($expiresTs): ?>
                <div class="cpy-expiry" id="payExpiry" aria-live="polite" aria-label="Payment window expires in">
                    <i class="bi bi-clock" aria-hidden="true"></i>
                    <span id="payCountdown">Calculating…</span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Status banner (initially hidden — shown by JS on state change) -->
            <div id="payStatusBanner"
                 class="cpy-status-banner"
                 role="alert"
                 aria-live="assertive"
                 aria-atomic="true"
                 hidden>
                <i id="payStatusIcon" aria-hidden="true"></i>
                <span id="payStatusMessage"></span>
            </div>

            <?php if ($isDev && !$isLive): ?>
            <!-- DEV MODE notice — hidden in production -->
            <div class="cpy-dev-notice" role="note" aria-label="Development mode notice">
                <i class="bi bi-code-slash" aria-hidden="true"></i>
                <div>
                    <strong>Development Mode</strong>
                    <p>No real payment provider is connected. Use the button below to simulate a successful payment.</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── QR / Payment section ──────────────────── -->
            <section class="cpy-qr-section" id="qrSection" aria-label="Payment QR code">

                <?php
                // Build the QR payload. Use payment reference as the content so it's unique per order.
                // When provider is live, $qrPayload comes from the real provider API.
                // In dev mode, we build a structured payload with all payment info.
                $qrContent = $qrPayload;
                if (!$isLive && $payRef) {
                    // Build a structured QR payload for dev mode — contains all info a banker needs
                    $qrContent = json_encode([
                        'type'      => 'AU-AgroSale Payment',
                        'reference' => $payRef,
                        'amount'    => number_format($amountRaw, 2, '.', ''),
                        'currency'  => 'ETB',
                        'provider'  => $providerName,
                        'order'     => $orderRef,
                        'shop'      => $shopName,
                    ]);
                }
                if (!$qrContent) {
                    $qrContent = $payRef ?: $orderRef;
                }
                ?>

                <div class="cpy-qr-wrap">
                    <!-- QR code canvas — rendered by qrcode.js -->
                    <div class="cpy-qr-outer">
                        <?php if (!$isLive): ?>
                        <div class="cpy-qr-dev-badge" aria-label="Development mode QR" role="note">
                            <i class="bi bi-info-circle" aria-hidden="true"></i> Demo QR
                        </div>
                        <?php endif; ?>
                        <div id="qrCodeCanvas"
                             class="cpy-qr-canvas"
                             data-qr="<?= e($qrContent) ?>"
                             role="img"
                             aria-label="Payment QR code — reference <?= e($payRef) ?>">
                        </div>
                    </div>

                    <?php if ($isLive): ?>
                    <p class="cpy-qr-hint">
                        <i class="bi bi-phone" aria-hidden="true"></i>
                        Open your <strong><?= e($providerName) ?></strong> app and scan this QR code
                        to pay <strong>ETB <?= $amount ?></strong>.
                    </p>
                    <?php else: ?>
                    <p class="cpy-qr-hint cpy-qr-hint--dev">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        Dev mode: use the Simulate button below instead of scanning.
                    </p>
                    <?php endif; ?>
                </div>

                <!-- Payment reference display — always visible -->
                <div class="cpy-pay-ref-box" aria-label="Payment reference">
                    <div class="cpy-pay-ref-box__label">Payment Reference</div>
                    <div class="cpy-pay-ref-box__ref" id="payRefDisplay"><?= e($payRef) ?></div>
                    <button type="button"
                            class="cpy-pay-ref-box__copy"
                            onclick="navigator.clipboard && navigator.clipboard.writeText('<?= e($payRef) ?>').then(function(){this.innerHTML='<i class=\'bi bi-check\' aria-hidden=\'true\'></i> Copied';}.bind(this))"
                            aria-label="Copy payment reference to clipboard"
                            title="Copy reference">
                        <i class="bi bi-clipboard" aria-hidden="true"></i>
                    </button>
                </div>

            </section>

            <!-- ── Payment status (PENDING pill) ─────────── -->
            <div class="cpy-status-pill" aria-live="polite" id="payStatusPill">
                <span class="cpy-status-pill__dot" aria-hidden="true"></span>
                <span class="cpy-status-pill__label">PENDING — Waiting for Payment</span>
            </div>

            <!-- ── Dev confirm button (dev only) ─────────── -->
            <?php if ($isDev && !$isLive): ?>
            <div class="cpy-dev-actions" id="devActions">
                <button type="button"
                        id="devConfirmBtn"
                        class="c-btn c-btn-accent"
                        aria-label="Simulate payment confirmation (development only)">
                    <i class="bi bi-check2-all" aria-hidden="true"></i>
                    Simulate Payment (Dev Only)
                </button>
            </div>
            <?php endif; ?>

            <!-- ── Polling indicator ──────────────────────── -->
            <div class="cpy-polling" id="payPolling" aria-live="polite" aria-atomic="true">
                <div class="cpy-polling__dots" aria-hidden="true">
                    <span></span><span></span><span></span>
                </div>
                <span id="payPollingMsg">Waiting for payment confirmation…</span>
            </div>

            <!-- ── Cancel link ────────────────────────────── -->
            <div class="cpy-cancel-row" id="cancelRow">
                <form method="POST"
                      action="<?= $view->url('customer/checkout/cancel') ?>"
                      id="cancelOrderForm"
                      style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="order_id" value="<?= $orderId ?>">
                </form>
                <button type="button"
                        id="cancelOrderBtn"
                        class="co-link-muted"
                        aria-label="Cancel this order and return to cart">
                    <i class="bi bi-x-circle" aria-hidden="true"></i>
                    Cancel order and return to cart
                </button>
            </div>

        </div><!-- /.cpy-card -->

    </div><!-- /.cpy-main-col -->

    <!-- ── RIGHT / SIDEBAR COLUMN ─────────────────────────── -->
    <aside class="cpy-side-col" aria-label="Payment instructions and order summary">

        <!-- Instructions card -->
        <div class="cpy-info-card c-animate-fade-up">
            <div class="cpy-info-card__head">
                <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                How to Complete Payment
            </div>
            <ol class="cpy-steps" aria-label="Payment steps">
                <li class="cpy-steps__item">
                    <span class="cpy-steps__num" aria-hidden="true">1</span>
                    <div class="cpy-steps__text">
                        <strong>Open your mobile banking app</strong>
                        <span>Launch <strong><?= e($providerName) ?></strong> on your phone.</span>
                    </div>
                </li>
                <li class="cpy-steps__item">
                    <span class="cpy-steps__num" aria-hidden="true">2</span>
                    <div class="cpy-steps__text">
                        <strong>Scan the QR code or use the reference</strong>
                        <span>Scan the code displayed or enter the payment reference manually.</span>
                    </div>
                </li>
                <li class="cpy-steps__item">
                    <span class="cpy-steps__num" aria-hidden="true">3</span>
                    <div class="cpy-steps__text">
                        <strong>Confirm the exact amount</strong>
                        <span>The amount must be exactly <strong>ETB <?= $amount ?></strong>. Do not change it.</span>
                    </div>
                </li>
                <li class="cpy-steps__item">
                    <span class="cpy-steps__num" aria-hidden="true">4</span>
                    <div class="cpy-steps__text">
                        <strong>Wait for confirmation</strong>
                        <span>This page will automatically update when your payment is verified. Do not close this page.</span>
                    </div>
                </li>
            </ol>
        </div>

        <!-- Order summary card -->
        <div class="cpy-info-card cpy-order-summary c-animate-fade-up">
            <div class="cpy-info-card__head">
                <i class="bi bi-receipt" aria-hidden="true"></i>
                Order Summary
            </div>
            <div class="cpy-summary-rows">
                <div class="cpy-summary-row">
                    <span>Order Number</span>
                    <strong class="cpy-summary-mono"><?= e($orderRef) ?></strong>
                </div>
                <div class="cpy-summary-row">
                    <span>Payment Method</span>
                    <strong><?= e($providerName) ?></strong>
                </div>
                <div class="cpy-summary-row">
                    <span>Pickup Shop</span>
                    <strong><?= e($shopName) ?></strong>
                </div>
                <div class="cpy-summary-row cpy-summary-row--total">
                    <span>Total Amount</span>
                    <strong class="cpy-summary-price">ETB <?= $amount ?></strong>
                </div>
                <div class="cpy-summary-row">
                    <span>Status</span>
                    <span class="cpy-summary-status-pending">
                        <i class="bi bi-clock" aria-hidden="true"></i>
                        PENDING
                    </span>
                </div>
            </div>
        </div>

        <!-- Security notice -->
        <div class="cpy-security-note">
            <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
            <span>Your payment is processed securely. We never store your banking credentials.</span>
        </div>

    </aside><!-- /.cpy-side-col -->

</div><!-- /.cpy-page-layout -->

<!-- ══════════════════════════════════════════════════════════
     SERVER-INJECTED DATA FOR JS (no credentials, no secrets)
     ══════════════════════════════════════════════════════════ -->
<script>
window.PaymentData = {
    orderId:      <?= $orderId ?>,
    paymentId:    <?= (int)($paymentData['payment_id'] ?? 0) ?>,
    orderRef:     <?= json_encode($orderRef) ?>,
    payRef:       <?= json_encode($payRef) ?>,
    expiresAt:    <?= (int)$expiresTs ?>,
    serverNow:    <?= (int)$serverNowTs ?>,
    isLive:       <?= $isLive ? 'true' : 'false' ?>,
    isDev:        <?= $isDev  ? 'true' : 'false' ?>,
    verifyUrl:    <?= json_encode($view->url('customer/checkout/verify')) ?>,
    devUrl:       <?= json_encode($view->url('customer/payment/dev-confirm')) ?>,
    cancelUrl:    <?= json_encode($view->url('customer/checkout/cancel')) ?>,
    successUrl:   <?= json_encode($view->url('customer/checkout/success/' . urlencode($orderRef))) ?>,
    ordersUrl:    <?= json_encode($view->url('customer/orders')) ?>,
    cartUrl:      <?= json_encode($view->url('customer/cart')) ?>,
    csrfToken:    <?= json_encode(csrf_token()) ?>,
    pollInterval: 3000
};
</script>

<?php $view->section('scripts'); ?>
<!--
    QR code library — loaded from CDN.
    Graceful fallback: if unavailable, the payment reference
    text is displayed instead of a QR image.
-->
<script src="<?= $view->asset('js/qrcode.min.js') ?>"></script>
<script>
/* ── QR rendering ── init runs after CDN script attempts to load */
(function () {
    function initQr() {
        var el = document.getElementById('qrCodeCanvas');
        if (!el || !el.dataset.qr) return;
        var payload = el.dataset.qr;
        if (typeof QRCode !== 'undefined') {
            el.innerHTML = '';
            try {
                new QRCode(el, {
                    text:         payload,
                    width:        200,
                    height:       200,
                    colorDark:    '#000000',
                    colorLight:   '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
            } catch (e) {
                renderFallback(el, payload);
            }
        } else {
            renderFallback(el, payload);
        }
    }
    function renderFallback(el, text) {
        el.style.padding    = '12px';
        el.style.fontSize   = '11px';
        el.style.color      = '#555';
        el.style.wordBreak  = 'break-all';
        el.style.textAlign  = 'center';
        el.textContent = text;
        el.setAttribute('aria-label', 'Payment reference: ' + text);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { setTimeout(initQr, 100); });
    } else {
        setTimeout(initQr, 100);
    }
}());
</script>
<script src="<?= $view->url('public/assets/customer/js/payment.js') ?>?v=<?= filemtime(PUBLIC_PATH . '/assets/customer/js/payment.js') ?>"></script>
<?php $view->endSection(); ?>
