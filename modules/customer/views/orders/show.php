<?php
/**
 * =========================================================
 * AU-AgroSale — Order Detail Page (Step 9)
 * modules/customer/views/orders/show.php
 * =========================================================
 * Displays complete order information including:
 *  - Order status with visual timeline
 *  - All order items with historical prices (from DB snapshots)
 *  - Payment information (method, reference, status, amount)
 *  - Pickup shop (from the order record — NOT current session shop)
 *  - Customer information recorded on the order
 *  - Receipt access when applicable
 *
 * SECURITY: Order ownership is verified in OrderController.
 * This view never re-checks ownership — it trusts the controller.
 *
 * @var array $order  Full order row + items
 * @var \AAM\Core\View $view
 */
declare(strict_types=1);

$order   = $order ?? [];
$items   = $order['items'] ?? [];
$orderRef= $order['order_reference']  ?? '';
$orderId = (int)($order['id']         ?? 0);
$status  = $order['order_status']     ?? '';
$payStatus= $order['payment_status']  ?? '';
$total   = number_format((float)($order['total_amount'] ?? 0), 2);
$subtotal= number_format((float)($order['subtotal']     ?? $order['total_amount'] ?? 0), 2);

// Shop — always from the order record, never from session
$shopName= $order['shop_name']    ?? '';
$shopLoc = $order['shop_address'] ?? $order['shop_location'] ?? '';

// Payment
$pmName  = $order['payment_method_name'] ?? '';
$pmType  = $order['payment_method_type'] ?? '';
$payRef  = $order['payment_reference']   ?? '';

// Timestamps
$createdAt   = $order['created_at']   ? date('M j, Y, g:i A', strtotime($order['created_at']))   : '—';
$paidAt      = $order['paid_at']      ? date('M j, Y, g:i A', strtotime($order['paid_at']))       : null;
$readyAt     = $order['ready_at']     ? date('M j, Y, g:i A', strtotime($order['ready_at']))      : null;
$completedAt = $order['completed_at'] ? date('M j, Y, g:i A', strtotime($order['completed_at'])) : null;
$pickup      = $order['pickup_instructions'] ?? null;
$pickupCode  = !empty($order['pickup_code']) ? $order['pickup_code'] : $orderRef;

// Customer info recorded on the order
$custName  = $order['customer_name']  ?? '';
$custPhone = $order['customer_phone'] ?? '';
$custEmail = $order['customer_email'] ?? '';

use AAM\Customer\Services\CustomerOrderService;
$statusLabel = CustomerOrderService::statusLabel($status);
$statusBadge = CustomerOrderService::statusBadge($status);
$payLabel    = CustomerOrderService::paymentStatusLabel($payStatus);
$payBadge    = $payStatus === 'paid' ? 'success' : ($payStatus === 'failed' || $payStatus === 'expired' ? 'danger' : 'warning');

// Derive which timeline steps are done based on REAL order status
$stepOrderPlaced      = true;   // always done once we have an order
$stepPayment          = in_array($status, ['paid', 'preparing', 'ready_for_pickup', 'completed'], true)
                        || ($status === 'pending_payment' && $pmType === 'cash');
$stepPreparing        = in_array($status, ['preparing', 'ready_for_pickup', 'completed'], true);
$stepReadyForPickup   = in_array($status, ['ready_for_pickup', 'completed'], true);
$stepPickedUp         = $status === 'completed';
$isCancelled          = in_array($status, ['cancelled', 'payment_failed'], true);

$isPaidOrHigher = in_array($status, ['paid', 'preparing', 'ready_for_pickup', 'completed'], true);
$isCash         = ($pmType === 'cash');
$canViewReceipt = $isPaidOrHigher || ($status === 'pending_payment' && $isCash);
$canPay         = ($status === 'pending_payment' && !$isCash);
?>
<?php $view->section('styles'); ?>
<link rel="stylesheet" href="<?= $view->url('public/assets/customer/css/orders.css') ?>?v=<?= file_exists(PUBLIC_PATH . '/assets/customer/css/orders.css') ? filemtime(PUBLIC_PATH . '/assets/customer/css/orders.css') : '1' ?>">
<?php $view->endSection(); ?>

<!-- ── Breadcrumb ───────────────────────────────────────────── -->
<div class="cp-toolbar">
    <div class="c-container">
        <div class="cp-toolbar__inner">
            <a href="<?= $view->url('customer/orders') ?>"
               class="c-back-nav"
               aria-label="Back to My Orders">
                <i class="bi bi-arrow-left c-back-nav__icon" aria-hidden="true"></i>
                <span class="c-back-nav__label">Back to My Orders</span>
            </a>
            <nav class="cp-breadcrumb" aria-label="Breadcrumb">
                <a href="<?= $view->url('customer') ?>">Home</a>
                <span class="cp-breadcrumb__sep" aria-hidden="true">›</span>
                <a href="<?= $view->url('customer/orders') ?>">My Orders</a>
                <span class="cp-breadcrumb__sep" aria-hidden="true">›</span>
                <span aria-current="page"><?= e($orderRef) ?></span>
            </nav>
        </div>
    </div>
</div>

<div class="c-container cord-layout cord-layout--detail" id="customer-main">

    <!-- ── Order header ──────────────────────────────────── -->
    <div class="cord-detail-header">
        <div class="cord-detail-header__left">
            <h1 class="cord-detail-title"><?= e($orderRef) ?></h1>
            <span class="cord-detail-date">
                <i class="bi bi-calendar3" aria-hidden="true"></i>
                <?= $createdAt ?>
            </span>
        </div>
        <div class="cord-detail-header__badges">
            <span class="c-badge c-badge-<?= $statusBadge ?>" aria-label="Order status">
                <?= e($statusLabel) ?>
            </span>
            <span class="c-badge c-badge-<?= $payBadge ?>" aria-label="Payment status">
                Payment: <?= e($payLabel) ?>
            </span>
        </div>
    </div>

    <?php if ($isCancelled): ?>
    <!-- Cancelled notice -->
    <div class="cord-cancelled-notice" role="alert" aria-live="assertive">
        <i class="bi bi-x-circle-fill" aria-hidden="true"></i>
        <div>
            <strong>This order has been <?= $status === 'payment_failed' ? 'failed due to payment issues' : 'cancelled' ?>.</strong>
            <?php if (!empty($order['cancellation_reason'])): ?>
            <span><?= e($order['cancellation_reason']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($status === 'ready_for_pickup'): ?>
    <!-- Ready for pickup banner -->
    <div class="cord-ready-banner" role="alert" aria-live="polite">
        <div class="cord-ready-banner__icon" aria-hidden="true">
            <i class="bi bi-bag-check-fill"></i>
        </div>
        <div>
            <strong>Your order is ready for pickup!</strong>
            <span>Head to <em><?= e($shopName) ?></em> and show your order reference.</span>
        </div>
    </div>
    <?php endif; ?>

    <div class="cord-detail-grid">

        <!-- ════════════════════════════════════════════
             LEFT COLUMN: Items + Payment info
             ════════════════════════════════════════════ -->
        <div class="cord-detail-main">

            <!-- ── Order items ────────────────────────── -->
            <div class="cord-card">
                <div class="cord-card__head">
                    <i class="bi bi-bag" aria-hidden="true"></i>
                    Order Items
                    <span class="cord-card__head-count"><?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?></span>
                </div>

                <?php if (!empty($items)): ?>
                <ul class="cord-items-list" role="list" aria-label="Ordered items">
                    <?php foreach ($items as $item):
                        $pName  = $item['product_name'] ?? '';
                        $pQty   = (float)($item['quantity']   ?? 0);
                        $pPrice = (float)($item['unit_price'] ?? 0);
                        $pSub   = (float)($item['subtotal']   ?? $pQty * $pPrice);
                        $pUnit  = $item['unit_abbr'] ?? ($item['unit_name'] ?? '');
                        $pImg   = $item['product_image'] ?? null;
                        $pQtyFmt= ($pQty == (int)$pQty) ? (int)$pQty : number_format($pQty, 2);
                    ?>
                    <li class="cord-item-row" role="listitem">
                        <div class="cord-item-row__img" aria-hidden="true">
                            <?php if ($pImg): ?>
                            <img src="<?= $view->url(e($pImg)) ?>"
                                 alt="<?= e($pName) ?>"
                                 loading="lazy"
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                            <div class="cord-item-row__img-ph" style="display:none;" aria-hidden="true">📦</div>
                            <?php else: ?>
                            <div class="cord-item-row__img-ph" aria-hidden="true">📦</div>
                            <?php endif; ?>
                        </div>
                        <div class="cord-item-row__info">
                            <div class="cord-item-row__name"><?= e($pName) ?></div>
                            <div class="cord-item-row__meta">
                                <?= $pQtyFmt ?>
                                <?php if ($pUnit): ?>
                                <span class="cord-unit"><?= e($pUnit) ?></span>
                                <?php endif; ?>
                                <span class="cord-item-row__meta-sep" aria-hidden="true">×</span>
                                ETB <?= number_format($pPrice, 2) ?>
                                <?php if ($pUnit): ?>
                                <span class="cord-item-row__meta-unit">per <?= e($pUnit) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="cord-item-row__sub" aria-label="Subtotal">
                            <span class="cord-item-row__sub-label">Subtotal</span>
                            <strong>ETB <?= number_format($pSub, 2) ?></strong>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Total footer -->
                <div class="cord-items-footer">
                    <div class="cord-items-footer__row">
                        <span>Subtotal</span>
                        <span>ETB <?= $subtotal ?></span>
                    </div>
                    <div class="cord-items-footer__row cord-items-footer__row--total">
                        <strong>Total</strong>
                        <strong class="cord-price">ETB <?= $total ?></strong>
                    </div>
                </div>
                <?php else: ?>
                <p class="cord-card__empty">No items found for this order.</p>
                <?php endif; ?>
            </div>

            <!-- ── Payment information ────────────────── -->
            <div class="cord-card">
                <div class="cord-card__head">
                    <i class="bi bi-credit-card" aria-hidden="true"></i>
                    Payment Information
                </div>
                <div class="cord-detail-rows">
                    <div class="cord-detail-row">
                        <span>Payment Method</span>
                        <strong><?= e($pmName ?: ($isCash ? 'Cash' : 'Mobile Banking')) ?></strong>
                    </div>
                    <div class="cord-detail-row">
                        <span>Payment Reference</span>
                        <strong class="cord-mono"><?= e($payRef ?: '—') ?></strong>
                    </div>
                    <div class="cord-detail-row">
                        <span>Payment Status</span>
                        <span class="c-badge c-badge-<?= $payBadge ?>">
                            <?= $payStatus === 'paid' ? '<i class="bi bi-check-circle-fill" aria-hidden="true"></i>' : '' ?>
                            <?= e($payLabel) ?>
                        </span>
                    </div>
                    <?php if ($paidAt): ?>
                    <div class="cord-detail-row">
                        <span>Paid At</span>
                        <strong><?= $paidAt ?></strong>
                    </div>
                    <?php endif; ?>
                    <div class="cord-detail-row cord-detail-row--total">
                        <span>Amount <?= $payStatus === 'paid' ? 'Paid' : 'Due' ?></span>
                        <strong class="cord-price">ETB <?= $total ?></strong>
                    </div>
                </div>

                <?php if ($canPay): ?>
                <div class="cord-card__cta">
                    <a href="<?= $view->url('customer/checkout/payment/' . $orderId) ?>"
                       class="c-btn c-btn-accent">
                        <i class="bi bi-credit-card" aria-hidden="true"></i>
                        Complete Payment Now
                    </a>
                </div>
                <?php elseif ($isCash && $status === 'pending_payment'): ?>
                <div class="cord-instructions">
                    <i class="bi bi-cash-coin" aria-hidden="true"></i>
                    Pay with cash when you collect your order at <strong><?= e($shopName) ?></strong>.
                </div>
                <?php endif; ?>
            </div>

            <!-- ── Customer information ───────────────── -->
            <?php if ($custName || $custPhone || $custEmail): ?>
            <div class="cord-card">
                <div class="cord-card__head">
                    <i class="bi bi-person" aria-hidden="true"></i>
                    Customer Information
                </div>
                <div class="cord-detail-rows">
                    <?php if ($custName): ?>
                    <div class="cord-detail-row">
                        <span>Name</span>
                        <strong><?= e($custName) ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if ($custPhone): ?>
                    <div class="cord-detail-row">
                        <span>Phone</span>
                        <strong><?= e($custPhone) ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if ($custEmail): ?>
                    <div class="cord-detail-row">
                        <span>Email</span>
                        <strong><?= e($custEmail) ?></strong>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /.cord-detail-main -->

        <!-- ════════════════════════════════════════════
             RIGHT COLUMN: Status timeline + Pickup info
             ════════════════════════════════════════════ -->
        <div class="cord-detail-side">

            <!-- ── Order timeline ─────────────────────── -->
            <div class="cord-card">
                <div class="cord-card__head">
                    <i class="bi bi-clock-history" aria-hidden="true"></i>
                    Order Timeline
                </div>

                <?php if ($isCancelled): ?>
                <ol class="cord-timeline" aria-label="Order timeline">
                    <li class="cord-timeline__item cord-timeline__item--done">
                        <div class="cord-timeline__dot" aria-hidden="true"><i class="bi bi-check"></i></div>
                        <div class="cord-timeline__content">
                            <strong>Order Placed</strong>
                            <span><?= $createdAt ?></span>
                        </div>
                    </li>
                    <li class="cord-timeline__item cord-timeline__item--cancelled">
                        <div class="cord-timeline__dot" aria-hidden="true"><i class="bi bi-x"></i></div>
                        <div class="cord-timeline__content">
                            <strong><?= $status === 'payment_failed' ? 'Payment Failed' : 'Cancelled' ?></strong>
                            <?php if (!empty($order['cancelled_at'])): ?>
                            <span><?= date('M j, Y, g:i A', strtotime($order['cancelled_at'])) ?></span>
                            <?php endif; ?>
                        </div>
                    </li>
                </ol>
                <?php else: ?>
                <ol class="cord-timeline" aria-label="Order progress timeline">
                    <!-- Step 1: Order Placed — always done -->
                    <li class="cord-timeline__item cord-timeline__item--done">
                        <div class="cord-timeline__dot" aria-hidden="true"><i class="bi bi-check"></i></div>
                        <div class="cord-timeline__content">
                            <strong>Order Placed</strong>
                            <span><?= $createdAt ?></span>
                        </div>
                    </li>

                    <!-- Step 2: Payment -->
                    <li class="cord-timeline__item <?= $stepPayment ? 'cord-timeline__item--done' : 'cord-timeline__item--pending' ?>">
                        <div class="cord-timeline__dot" aria-hidden="true">
                            <?= $stepPayment ? '<i class="bi bi-check"></i>' : '<i class="bi bi-clock"></i>' ?>
                        </div>
                        <div class="cord-timeline__content">
                            <strong><?= $isCash ? 'Cash Payment' : 'Payment Confirmed' ?></strong>
                            <span>
                                <?php if ($stepPayment && $paidAt): ?>
                                    <?= $paidAt ?>
                                <?php elseif ($stepPayment && $isCash): ?>
                                    Pay at shop
                                <?php else: ?>
                                    Waiting for payment…
                                <?php endif; ?>
                            </span>
                        </div>
                    </li>

                    <!-- Step 3: Preparing -->
                    <li class="cord-timeline__item <?= $stepPreparing ? 'cord-timeline__item--done' : ($stepPayment ? 'cord-timeline__item--active' : 'cord-timeline__item--pending') ?>">
                        <div class="cord-timeline__dot" aria-hidden="true">
                            <?= $stepPreparing ? '<i class="bi bi-check"></i>' : ($stepPayment ? '<i class="bi bi-hourglass-split"></i>' : '<i class="bi bi-clock"></i>') ?>
                        </div>
                        <div class="cord-timeline__content">
                            <strong>Preparing</strong>
                            <span>
                                <?php if ($stepPreparing): ?>
                                    Completed
                                <?php elseif ($stepPayment): ?>
                                    In progress…
                                <?php else: ?>
                                    Waiting…
                                <?php endif; ?>
                            </span>
                        </div>
                    </li>

                    <!-- Step 4: Ready for Pickup -->
                    <li class="cord-timeline__item <?= $stepReadyForPickup ? 'cord-timeline__item--done' : ($stepPreparing ? 'cord-timeline__item--active' : 'cord-timeline__item--pending') ?>">
                        <div class="cord-timeline__dot" aria-hidden="true">
                            <?= $stepReadyForPickup ? '<i class="bi bi-check"></i>' : ($stepPreparing ? '<i class="bi bi-bag-check"></i>' : '<i class="bi bi-clock"></i>') ?>
                        </div>
                        <div class="cord-timeline__content">
                            <strong>Ready for Pickup</strong>
                            <span>
                                <?php if ($readyAt): ?>
                                    <?= $readyAt ?>
                                <?php elseif ($stepReadyForPickup): ?>
                                    —
                                <?php elseif ($stepPreparing): ?>
                                    Almost ready…
                                <?php else: ?>
                                    Pending…
                                <?php endif; ?>
                            </span>
                        </div>
                    </li>

                    <!-- Step 5: Picked Up -->
                    <li class="cord-timeline__item <?= $stepPickedUp ? 'cord-timeline__item--done' : 'cord-timeline__item--pending' ?>">
                        <div class="cord-timeline__dot" aria-hidden="true">
                            <?= $stepPickedUp ? '<i class="bi bi-check"></i>' : '<i class="bi bi-clock"></i>' ?>
                        </div>
                        <div class="cord-timeline__content">
                            <strong>Picked Up</strong>
                            <span>
                                <?php if ($completedAt): ?>
                                    <?= $completedAt ?>
                                <?php elseif ($stepPickedUp): ?>
                                    —
                                <?php else: ?>
                                    Pending…
                                <?php endif; ?>
                            </span>
                        </div>
                    </li>
                </ol>
                <?php endif; ?>
            </div>

            <!-- ── Pickup shop information ────────────── -->
            <div class="cord-card">
                <div class="cord-card__head">
                    <i class="bi bi-shop" aria-hidden="true"></i>
                    Pickup Shop
                </div>
                <div class="cord-pickup-shop">
                    <div class="cord-pickup-shop__icon" aria-hidden="true">
                        <i class="bi bi-geo-alt-fill"></i>
                    </div>
                    <div class="cord-pickup-shop__info">
                        <strong class="cord-pickup-shop__name"><?= e($shopName) ?></strong>
                        <?php if ($shopLoc): ?>
                        <span class="cord-pickup-shop__location"><?= e($shopLoc) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($pickup): ?>
                <div class="cord-instructions" style="margin-top: var(--c-space-4);">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    <span><?= e($pickup) ?></span>
                </div>
                <?php else: ?>
                <div class="cord-instructions" style="margin-top: var(--c-space-4);">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    Show your order reference <strong><?= e($orderRef) ?></strong>
                    at the <strong><?= e($shopName) ?></strong> counter.
                </div>
                <?php endif; ?>
            </div>

            <!-- ── Pickup Code ────────────────────────── -->
            <?php if (!$isCancelled): ?>
            <div class="cord-card cord-card--qr">
                <div class="cord-card__head">
                    <i class="bi bi-qr-code" aria-hidden="true"></i>
                    Pickup Code
                </div>
                <div class="cord-qr-wrap">
                    <div class="cord-qr-canvas"
                         id="cordQrCode"
                         data-qr="<?= e($pickupCode) ?>"
                         role="img"
                         aria-label="Pickup QR code for order <?= e($orderRef) ?>">
                    </div>
                    <div class="cord-qr-code-text" aria-label="Pickup code">
                        <?= e($pickupCode) ?>
                    </div>
                    <?php if (!empty($order['pickup_code']) && $order['pickup_code'] !== $orderRef): ?>
                    <div style="margin-top:.4rem;font-size:.78rem;color:var(--c-muted);text-align:center;">
                        Order ref: <code><?= e($orderRef) ?></code>
                    </div>
                    <?php endif; ?>
                    <p class="cord-qr-hint">
                        <i class="bi bi-phone" aria-hidden="true"></i>
                        Show this code at <strong><?= e($shopName) ?></strong> to collect your order.
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── Actions ────────────────────────────── -->
            <div class="cord-detail-actions">
                <?php if ($canViewReceipt): ?>
                <a href="<?= $view->url('customer/orders/' . $orderId . '/receipt') ?>"
                   class="c-btn c-btn-primary">
                    <i class="bi bi-receipt" aria-hidden="true"></i>
                    View Receipt
                </a>
                <?php endif; ?>

                <?php if ($canPay): ?>
                <a href="<?= $view->url('customer/checkout/payment/' . $orderId) ?>"
                   class="c-btn c-btn-accent">
                    <i class="bi bi-credit-card" aria-hidden="true"></i>
                    Complete Payment
                </a>
                <?php endif; ?>

            </div>

        </div><!-- /.cord-detail-side -->

    </div><!-- /.cord-detail-grid -->

</div><!-- /.cord-layout -->

<?php if (empty($__accountLayoutInclude)): ?>
<?php $view->section('scripts'); ?>
<script src="<?= $view->asset('js/qrcode.min.js') ?>"></script>
<script>
(function () {
    'use strict';

    /* ── QR Rendering ────────────────────────────────────── */
    function initQr() {
        var qrEl = document.getElementById('cordQrCode');
        if (!qrEl || !qrEl.dataset.qr) return;
        var payload = qrEl.dataset.qr;
        if (typeof QRCode !== 'undefined') {
            qrEl.innerHTML = '';
            try {
                new QRCode(qrEl, {
                    text:         payload,
                    width:        160,
                    height:       160,
                    colorDark:    '#1a2e1b',
                    colorLight:   '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
            } catch (e) {
                qrEl.classList.add('cord-qr-canvas--fallback');
                qrEl.textContent = payload;
            }
        } else {
            qrEl.classList.add('cord-qr-canvas--fallback');
            qrEl.textContent = payload;
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initQr);
    } else {
        setTimeout(initQr, 120);
    }

    /* ── Auto-refresh ────────────────────────────────────── */
    var activeStatuses = ['pending_payment', 'paid', 'preparing', 'ready_for_pickup'];
    var currentStatus  = '<?= addslashes($status) ?>';

    if (activeStatuses.indexOf(currentStatus) !== -1) {
        setTimeout(function () {
            window.location.reload();
        }, 30000);
    }
}());
</script>
<?php $view->endSection(); ?>
<?php endif; /* $__accountLayoutInclude */ ?>
