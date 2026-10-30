<?php
/**
 * =========================================================
 * AU-AgroSale — Order Confirmation / Success Page
 * modules/customer/views/checkout/success.php
 * =========================================================
 * Phase 7 — Premium order confirmation screen.
 * Reuses the full customer design system (variables, components,
 * checkout.css). Does NOT introduce new CSS frameworks.
 *
 * @var array  $order      Full order row + items from getOrderByReferenceForSession()
 * @var string $shopName
 * @var \AAM\Core\View $view
 */
declare(strict_types=1);

$order     = $order     ?? [];
$shopName  = $shopName  ?? ($order['shop_name'] ?? '');
$orderRef  = $order['order_reference']               ?? '';
$orderId   = (int)($order['id']                      ?? 0);
$shopId    = (int)($order['shop_id']                 ?? 0);
$total     = number_format((float)($order['total_amount']    ?? 0), 2);
$subtotal  = number_format((float)($order['subtotal']        ?? $order['total_amount'] ?? 0), 2);
$status    = $order['order_status']                  ?? 'pending_payment';
$payStatus = $order['payment_status']                ?? 'pending';
$pmName    = $order['payment_method_name']           ?? 'Mobile Banking';
$pmType    = $order['payment_method_type']           ?? 'mobile_banking';
$address   = $order['shop_address'] ?? $order['shop_location'] ?? '';
$items     = $order['items']                         ?? [];
$createdAt = $order['created_at']  ? date('d M Y, g:i A', strtotime($order['created_at'])) : '—';
$notes     = $order['notes']                         ?? '';

use AAM\Customer\Services\CustomerOrderService;
$statusLabel = CustomerOrderService::statusLabel($status);
$statusBadge = CustomerOrderService::statusBadge($status);

// Determine display state
$isPaid     = in_array($status, ['paid','preparing','ready_for_pickup','completed'], true);
$isCash     = ($pmType === 'cash');
$isReady    = ($status === 'ready_for_pickup');

// QR / pickup code — prefer the DB pickup_code, fall back to order reference
$pickupCode = !empty($order['pickup_code']) ? $order['pickup_code'] : $orderRef;

// Payment status label
$payLabel   = ($payStatus === 'paid') ? 'PAID'
            : (($isCash)             ? 'PAY AT SHOP'
                                     : strtoupper(CustomerOrderService::paymentStatusLabel($payStatus)));
$payBadge   = ($payStatus === 'paid') ? 'success'
            : (($isCash)             ? 'warning' : 'warning');
?>
<?php $view->section('styles'); ?>
<link rel="stylesheet" href="<?= $view->url('public/assets/customer/css/checkout.css') ?>?v=<?= filemtime(PUBLIC_PATH . '/assets/customer/css/checkout.css') ?>">
<link rel="stylesheet" href="<?= $view->url('public/assets/customer/css/success.css') ?>?v=<?= file_exists(PUBLIC_PATH . '/assets/customer/css/success.css') ? filemtime(PUBLIC_PATH . '/assets/customer/css/success.css') : '1' ?>">
<?php $view->endSection(); ?>

<!-- ══════════════════════════════════════════════════════════
     BREADCRUMB TOOLBAR
     ══════════════════════════════════════════════════════════ -->
<div class="cp-toolbar">
    <div class="c-container cp-toolbar__inner">
        <a href="<?= $view->url('customer') ?>"
           class="c-back-nav"
           aria-label="Back to Home">
            <i class="bi bi-arrow-left c-back-nav__icon" aria-hidden="true"></i>
            <span class="c-back-nav__label">Back to Home</span>
        </a>
        <nav class="cp-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= $view->url('customer') ?>">Home</a>
            <span class="cp-breadcrumb__sep" aria-hidden="true">›</span>
            <a href="<?= $view->url('customer/checkout') ?>">Checkout</a>
            <span class="cp-breadcrumb__sep" aria-hidden="true">›</span>
            <span aria-current="page">Order Confirmed</span>
        </nav>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     SUCCESS HERO
     ══════════════════════════════════════════════════════════ -->
<section class="csc-hero" aria-label="Order confirmation" id="customer-main">
    <div class="c-container">
        <div class="csc-hero__inner">

            <!-- Animated check icon -->
            <div class="csc-hero__icon-wrap" aria-hidden="true">
                <?php if ($isPaid): ?>
                <div class="csc-hero__icon csc-hero__icon--success">
                    <i class="bi bi-check-lg"></i>
                </div>
                <?php else: ?>
                <div class="csc-hero__icon csc-hero__icon--pending">
                    <i class="bi bi-clock-fill"></i>
                </div>
                <?php endif; ?>
                <!-- Ripple rings -->
                <div class="csc-hero__ring csc-hero__ring--1" aria-hidden="true"></div>
                <div class="csc-hero__ring csc-hero__ring--2" aria-hidden="true"></div>
            </div>

            <?php if ($isPaid): ?>
            <h1 class="csc-hero__title">Order Confirmed!</h1>
            <p class="csc-hero__sub">
                Thank you for your order. Your payment has been received and<br class="csc-hero__br">
                your order is being prepared for pickup.
            </p>
            <?php elseif ($isCash): ?>
            <h1 class="csc-hero__title">Order Placed!</h1>
            <p class="csc-hero__sub">
                Your order has been received. Please pay with cash<br class="csc-hero__br">
                when you collect your order at the shop.
            </p>
            <?php else: ?>
            <h1 class="csc-hero__title">Order Received</h1>
            <p class="csc-hero__sub">
                Your order is waiting for payment confirmation.
            </p>
            <?php endif; ?>

            <!-- Quick hero chips -->
            <div class="csc-hero__chips" role="list" aria-label="Order status">
                <div class="csc-hero__chip csc-hero__chip--ref" role="listitem">
                    <i class="bi bi-hash" aria-hidden="true"></i>
                    <span><?= e($orderRef) ?></span>
                </div>
                <div class="csc-hero__chip csc-hero__chip--<?= $payBadge ?>" role="listitem">
                    <i class="bi bi-<?= $payStatus === 'paid' ? 'check-circle-fill' : 'clock' ?>" aria-hidden="true"></i>
                    <span>Payment: <?= e($payLabel) ?></span>
                </div>
                <div class="csc-hero__chip csc-hero__chip--shop" role="listitem">
                    <i class="bi bi-shop" aria-hidden="true"></i>
                    <span><?= e($shopName) ?></span>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════════════════
     MAIN CONTENT — 2-column on desktop
     ══════════════════════════════════════════════════════════ -->
<div class="c-container csc-layout">

    <!-- ══════════════════════════════════════════
         LEFT COLUMN
         ══════════════════════════════════════════ -->
    <div class="csc-main">

        <!-- ── Order Summary Card ─────────────────── -->
        <div class="csc-card c-animate-fade-up">
            <div class="csc-card__head">
                <i class="bi bi-receipt" aria-hidden="true"></i>
                Order Summary
            </div>
            <div class="csc-detail-rows">
                <div class="csc-detail-row">
                    <span class="csc-detail-row__label">Order Number</span>
                    <strong class="csc-detail-row__val csc-mono"><?= e($orderRef) ?></strong>
                </div>
                <div class="csc-detail-row">
                    <span class="csc-detail-row__label">Date</span>
                    <strong class="csc-detail-row__val"><?= $createdAt ?></strong>
                </div>
                <div class="csc-detail-row">
                    <span class="csc-detail-row__label">Payment Method</span>
                    <strong class="csc-detail-row__val"><?= e($pmName) ?></strong>
                </div>
                <div class="csc-detail-row">
                    <span class="csc-detail-row__label">Payment Status</span>
                    <span class="csc-badge csc-badge--<?= $payBadge ?>">
                        <i class="bi bi-<?= $payStatus === 'paid' ? 'check-circle-fill' : 'clock' ?>" aria-hidden="true"></i>
                        <?= e($payLabel) ?>
                    </span>
                </div>
                <div class="csc-detail-row csc-detail-row--total">
                    <span class="csc-detail-row__label">Total</span>
                    <strong class="csc-detail-row__val csc-price">ETB <?= $total ?></strong>
                </div>
            </div>
        </div>

        <!-- ── Ordered Products ───────────────────── -->
        <?php if (!empty($items)): ?>
        <div class="csc-card c-animate-fade-up">
            <div class="csc-card__head">
                <i class="bi bi-bag-check" aria-hidden="true"></i>
                Your Items
                <span class="csc-card__head-count"><?= count($items) ?> item<?= count($items) > 1 ? 's' : '' ?></span>
            </div>

            <ul class="csc-items-list" role="list" aria-label="Ordered items">
                <?php foreach ($items as $item):
                    $pName   = $item['product_name'] ?? '';
                    $pQty    = (float)($item['quantity']  ?? 0);
                    $pPrice  = (float)($item['unit_price'] ?? 0);
                    $pSub    = (float)($item['subtotal']   ?? ($pQty * $pPrice));
                    $pUnit   = $item['unit_abbr'] ?? ($item['unit_name'] ?? '');
                    $pImage  = $item['product_image'] ?? null;
                    $pQtyFmt = ($pQty == (int)$pQty) ? (int)$pQty : $pQty;
                ?>
                <li class="csc-item" role="listitem">
                    <!-- Product image -->
                    <div class="csc-item__img" aria-hidden="true">
                        <?php if ($pImage): ?>
                        <img src="<?= $view->url(e($pImage)) ?>"
                             alt="<?= e($pName) ?>"
                             loading="lazy"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <div class="csc-item__img-placeholder" style="display:none;">📦</div>
                        <?php else: ?>
                        <div class="csc-item__img-placeholder">📦</div>
                        <?php endif; ?>
                    </div>

                    <!-- Product info -->
                    <div class="csc-item__info">
                        <div class="csc-item__name"><?= e($pName) ?></div>
                        <div class="csc-item__meta">
                            <?php if ($pUnit): ?>
                            <span><?= $pQtyFmt ?> <?= e($pUnit) ?></span>
                            <span class="csc-item__meta-sep" aria-hidden="true">×</span>
                            <?php else: ?>
                            <span>Qty: <?= $pQtyFmt ?></span>
                            <span class="csc-item__meta-sep" aria-hidden="true">×</span>
                            <?php endif; ?>
                            <span>ETB <?= number_format($pPrice, 2) ?></span>
                        </div>
                    </div>

                    <!-- Subtotal -->
                    <div class="csc-item__subtotal">
                        <span class="csc-item__subtotal-label">Subtotal</span>
                        <strong class="csc-item__subtotal-val">ETB <?= number_format($pSub, 2) ?></strong>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>

            <!-- Price summary footer -->
            <div class="csc-price-summary">
                <div class="csc-price-row">
                    <span>Subtotal</span>
                    <span>ETB <?= $subtotal ?></span>
                </div>
                <?php if ($payStatus === 'paid'): ?>
                <div class="csc-price-row csc-price-row--paid">
                    <span><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Payment</span>
                    <span class="csc-price-paid">Paid</span>
                </div>
                <?php endif; ?>
                <div class="csc-price-row csc-price-row--total">
                    <strong>Total</strong>
                    <strong class="csc-price">ETB <?= $total ?></strong>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Pickup Instructions ─────────────────── -->
        <div class="csc-card csc-card--instructions c-animate-fade-up">
            <div class="csc-card__head">
                <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                How to Pick Up Your Order
            </div>
            <ol class="csc-steps-list" aria-label="Pickup instructions">
                <li class="csc-steps-list__item">
                    <div class="csc-steps-list__num" aria-hidden="true">1</div>
                    <div class="csc-steps-list__text">
                        <strong>Go to your selected pickup shop</strong>
                        <span><?= e($shopName) ?><?= $address ? ' — ' . e($address) : '' ?></span>
                    </div>
                </li>
                <li class="csc-steps-list__item">
                    <div class="csc-steps-list__num" aria-hidden="true">2</div>
                    <div class="csc-steps-list__text">
                        <strong>Show your Order Number or QR Code</strong>
                        <span>Bring your order confirmation or show your phone screen to the salesperson.</span>
                    </div>
                </li>
                <li class="csc-steps-list__item">
                    <div class="csc-steps-list__num" aria-hidden="true">3</div>
                    <div class="csc-steps-list__text">
                        <strong>The salesperson verifies your order</strong>
                        <span>They will confirm your order details and prepare your products.</span>
                    </div>
                </li>
                <li class="csc-steps-list__item">
                    <div class="csc-steps-list__num" aria-hidden="true">4</div>
                    <div class="csc-steps-list__text">
                        <strong>Collect your products</strong>
                        <?php if ($isCash): ?>
                        <span>Pay with cash and collect your fresh products from <?= e($shopName) ?>.</span>
                        <?php else: ?>
                        <span>Enjoy your fresh, quality products from Arsi University farms.</span>
                        <?php endif; ?>
                    </div>
                </li>
            </ol>
            <?php if ($isCash): ?>
            <div class="csc-notice csc-notice--warning">
                <i class="bi bi-cash-coin" aria-hidden="true"></i>
                <p>Please bring cash to pay at the shop counter when you collect your order.</p>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /.csc-main -->

    <!-- ══════════════════════════════════════════
         RIGHT COLUMN — Sticky sidebar
         ══════════════════════════════════════════ -->
    <aside class="csc-side" aria-label="Order and pickup details">

        <!-- ── Pickup Information Card ─────────────── -->
        <div class="csc-card csc-card--pickup c-animate-fade-up">
            <div class="csc-card__head">
                <i class="bi bi-shop" aria-hidden="true"></i>
                Pickup Information
            </div>

            <div class="csc-pickup-shop">
                <div class="csc-pickup-shop__icon" aria-hidden="true">
                    <i class="bi bi-geo-alt-fill"></i>
                </div>
                <div class="csc-pickup-shop__info">
                    <div class="csc-pickup-shop__name"><?= e($shopName) ?></div>
                    <?php if ($address): ?>
                    <div class="csc-pickup-shop__location"><?= e($address) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pickup status badge -->
            <div class="csc-pickup-status">
                <?php if ($isReady): ?>
                <div class="csc-pickup-status__badge csc-pickup-status__badge--ready">
                    <i class="bi bi-bag-check-fill" aria-hidden="true"></i>
                    Ready for Pickup
                </div>
                <p class="csc-pickup-status__note">Your order is ready. Go to the shop now!</p>
                <?php elseif ($isPaid): ?>
                <div class="csc-pickup-status__badge csc-pickup-status__badge--preparing">
                    <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                    Preparing Order
                </div>
                <p class="csc-pickup-status__note">Your order is being prepared. You will be notified when it's ready.</p>
                <?php elseif ($isCash): ?>
                <div class="csc-pickup-status__badge csc-pickup-status__badge--pending">
                    <i class="bi bi-cash-coin" aria-hidden="true"></i>
                    Awaiting Pickup &amp; Payment
                </div>
                <p class="csc-pickup-status__note">Visit the shop and pay with cash to complete your order.</p>
                <?php else: ?>
                <div class="csc-pickup-status__badge csc-pickup-status__badge--pending">
                    <i class="bi bi-clock" aria-hidden="true"></i>
                    Awaiting Payment
                </div>
                <p class="csc-pickup-status__note">Once payment is confirmed, your order will be prepared.</p>
                <?php endif; ?>
            </div>

            <div class="csc-pickup-instruction">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                <p>Please visit the selected shop and show your order number or pickup QR code.</p>
            </div>
        </div>

        <!-- ── QR / Pickup Code Card ────────────────── -->
        <div class="csc-card csc-card--qr c-animate-fade-up">
            <div class="csc-card__head">
                <i class="bi bi-qr-code" aria-hidden="true"></i>
                Pickup Code
            </div>

            <!-- QR code canvas -->
            <div class="csc-qr-wrap">
                <div class="csc-qr-canvas"
                     id="cscQrCode"
                     data-qr="<?= e($pickupCode) ?>"
                     role="img"
                     aria-label="Pickup QR code for order <?= e($orderRef) ?>">
                </div>
                <div class="csc-qr-code-text" aria-label="Order pickup code">
                    <?= e($pickupCode) ?>
                </div>
                <?php if (!empty($order['pickup_code']) && $order['pickup_code'] !== $orderRef): ?>
                <div style="margin-top:.5rem;font-size:.78rem;color:#6c757d;text-align:center;">
                    Order ref: <code><?= e($orderRef) ?></code>
                </div>
                <?php endif; ?>
                <p class="csc-qr-hint">
                    <i class="bi bi-phone" aria-hidden="true"></i>
                    Show this code at the pickup counter.
                    The salesperson will verify it before handing over your products.
                </p>
            </div>
        </div>

        <!-- ── Action Buttons ───────────────────────── -->
        <div class="csc-actions c-animate-fade-up">
            <a href="<?= $view->url('customer/orders/' . $orderId) ?>"
               class="c-btn c-btn-primary c-btn-lg csc-actions__btn">
                <i class="bi bi-bag-check" aria-hidden="true"></i>
                View My Order
            </a>
            <a href="<?= $view->url('customer/products' . ($shopId ? '?shop_id=' . $shopId : '')) ?>"
               class="c-btn c-btn-secondary c-btn-lg csc-actions__btn">
                <i class="bi bi-grid" aria-hidden="true"></i>
                Continue Shopping
            </a>
            <?php if ($orderId): ?>
            <a href="<?= $view->url('customer/orders/' . $orderId . '/receipt') ?>"
               class="csc-actions__receipt-link">
                <i class="bi bi-receipt" aria-hidden="true"></i>
                Print Receipt
            </a>
            <?php endif; ?>
        </div>

    </aside><!-- /.csc-side -->

</div><!-- /.csc-layout -->

<!-- ══════════════════════════════════════════════════════════
     TRUST / INFO STRIP
     ══════════════════════════════════════════════════════════ -->
<div class="csc-trust-strip">
    <div class="c-container cart-feature-strip__inner">
        <div class="cart-feature-item">
            <i class="bi bi-tree-fill cart-feature-item__icon" aria-hidden="true"></i>
            <div>
                <div class="cart-feature-item__title">Fresh from University Farms</div>
                <div class="cart-feature-item__desc">Quality products grown by Arsi University</div>
            </div>
        </div>
        <div class="cart-feature-item">
            <i class="bi bi-shield-check cart-feature-item__icon" aria-hidden="true"></i>
            <div>
                <div class="cart-feature-item__title">Secure Payment</div>
                <div class="cart-feature-item__desc">Your payment is safe and verified</div>
            </div>
        </div>
        <div class="cart-feature-item">
            <i class="bi bi-shop cart-feature-item__icon" aria-hidden="true"></i>
            <div>
                <div class="cart-feature-item__title">Easy Pickup</div>
                <div class="cart-feature-item__desc">Collect from your selected shop</div>
            </div>
        </div>
        <div class="cart-feature-item">
            <i class="bi bi-award-fill cart-feature-item__icon" aria-hidden="true"></i>
            <div>
                <div class="cart-feature-item__title">Quality Assured</div>
                <div class="cart-feature-item__desc">Verified and trusted products</div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     FOOTER — reuse from checkout page pattern
     ══════════════════════════════════════════════════════════ -->
<?php
$footerShops = [];
try {
    $shopSvc = new \AAM\Customer\Services\ShopAvailabilityService();
    $footerShops = $shopSvc->getActiveShops();
} catch (\Throwable $e) {}
?>
<footer class="ch-footer" role="contentinfo">
    <div class="c-container">
        <div class="ch-footer__grid">
            <!-- Brand -->
            <div class="ch-footer__brand-col">
                <a href="<?= $view->url('customer') ?>" class="ch-footer__brand" aria-label="AU-AgroSale home">
                    <div class="customer-navbar__logo" style="width:32px;height:32px;font-size:15px;" aria-hidden="true">🌿</div>
                    <div>
                        <div class="ch-footer__brand-name">AU-AgroSale</div>
                        <div class="ch-footer__brand-sub">Arsi University</div>
                    </div>
                </a>
                <p class="ch-footer__about">Quality agricultural products directly from Arsi University farms to your table.</p>
                <div class="ch-footer__social" role="list" aria-label="Social links">
                    <a href="#" class="ch-footer__social-link" aria-label="Facebook" role="listitem"><i class="bi bi-facebook" aria-hidden="true"></i></a>
                    <a href="#" class="ch-footer__social-link" aria-label="Instagram" role="listitem"><i class="bi bi-instagram" aria-hidden="true"></i></a>
                    <a href="#" class="ch-footer__social-link" aria-label="Telegram" role="listitem"><i class="bi bi-telegram" aria-hidden="true"></i></a>
                    <a href="#" class="ch-footer__social-link" aria-label="WhatsApp" role="listitem"><i class="bi bi-whatsapp" aria-hidden="true"></i></a>
                </div>
            </div>
            <!-- Quick Links -->
            <nav class="ch-footer__nav-col" aria-label="Quick links">
                <div class="ch-footer__nav-heading">Quick Links</div>
                <ul class="ch-footer__nav-list">
                    <li><a href="<?= $view->url('customer') ?>">Home</a></li>
                    <li><a href="<?= $view->url('customer/products') ?>">Products</a></li>
                    <li><a href="<?= $view->url('customer/shops') ?>">Shops</a></li>
                    <li><a href="<?= $view->url('customer/orders') ?>">My Orders</a></li>
                    <li><a href="#">About Us</a></li>
                </ul>
            </nav>
            <!-- Customer Service -->
            <nav class="ch-footer__nav-col" aria-label="Customer service">
                <div class="ch-footer__nav-heading">Customer Service</div>
                <ul class="ch-footer__nav-list">
                    <li><a href="#">How to Order</a></li>
                    <li><a href="#">Payment Methods</a></li>
                    <li><a href="#">Pickup Information</a></li>
                    <li><a href="#">Help Center</a></li>
                </ul>
            </nav>
            <!-- Our Shops -->
            <nav class="ch-footer__nav-col" aria-label="Shop locations">
                <div class="ch-footer__nav-heading">Our Shops</div>
                <ul class="ch-footer__nav-list">
                    <?php foreach (array_slice($footerShops, 0, 5) as $s): ?>
                    <li>
                        <a href="<?= $view->url('customer/shops/' . (int)$s['id'] . '/availability') ?>">
                            <?= e($s['shop_name'] ?? $s['name'] ?? '') ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                    <li><a href="<?= $view->url('customer/shops') ?>">All Shop Locations</a></li>
                </ul>
            </nav>
            <!-- Contact -->
            <div class="ch-footer__contact-col">
                <div class="ch-footer__nav-heading">Contact Us</div>
                <ul class="ch-footer__contact-list">
                    <li><i class="bi bi-geo-alt-fill" aria-hidden="true"></i>Arsi University, Asella<br>Oromia, Ethiopia</li>
                    <li><i class="bi bi-telephone-fill" aria-hidden="true"></i>+251 911 234567</li>
                    <li><i class="bi bi-envelope-fill" aria-hidden="true"></i>info@au-agrosale.edu.et</li>
                    <li><i class="bi bi-clock-fill" aria-hidden="true"></i>Mon &ndash; Sat: 8:00 AM &ndash; 6:00 PM</li>
                </ul>
            </div>
        </div>
        <div class="ch-footer__bottom">
            <p class="ch-footer__copyright">&copy; <?= date('Y') ?> AU-AgroSale. All rights reserved.</p>
            <div class="ch-footer__bottom-links">
                <a href="#">Privacy Policy</a>
                <span aria-hidden="true">·</span>
                <a href="#">Terms of Service</a>
            </div>
        </div>
    </div>
</footer>

<?php $view->section('scripts'); ?>
<!--
    QR code library — loaded from CDN with graceful fallback.
    If unavailable, the order reference text is shown instead.
-->
<script src="<?= $view->asset('js/qrcode.min.js') ?>"></script>
<script>
(function () {
    'use strict';

    /* ── QR Rendering ───────────────────────────────────── */
    function initQr() {
        var qrEl = document.getElementById('cscQrCode');
        if (!qrEl || !qrEl.dataset.qr) return;

        var payload = qrEl.dataset.qr;

        if (typeof QRCode !== 'undefined') {
            qrEl.innerHTML = '';
            try {
                new QRCode(qrEl, {
                    text:         payload,
                    width:        180,
                    height:       180,
                    colorDark:    '#1a2e1b',
                    colorLight:   '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
            } catch (e) {
                renderFallback(qrEl, payload);
            }
        } else {
            renderFallback(qrEl, payload);
        }
    }

    function renderFallback(el, text) {
        el.classList.add('csc-qr-canvas--fallback');
        el.textContent = text;
        el.setAttribute('aria-label', 'Pickup code: ' + text);
    }

    /* Run after DOM + scripts ready */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initQr);
    } else {
        /* CDN script may still be loading — short defer */
        setTimeout(initQr, 120);
    }
}());
</script>
<?php $view->endSection(); ?>
