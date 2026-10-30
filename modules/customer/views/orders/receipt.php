<?php
/**
 * =========================================================
 * AU-AgroSale — Order Receipt / Printable Confirmation
 * modules/customer/views/orders/receipt.php
 * =========================================================
 * @var array $order
 * @var \AAM\Core\View $view
 */
declare(strict_types=1);

$order    = $order ?? [];
$items    = $order['items'] ?? [];
$orderRef = $order['order_reference'] ?? '';
$payRef   = $order['payment_reference'] ?? '';
$total    = number_format((float)($order['total_amount'] ?? 0), 2);
$shopName = $order['shop_name']   ?? '';
$address  = $order['shop_address'] ?? $order['shop_location'] ?? '';
$pmName   = $order['payment_method_name'] ?? 'Mobile Banking';
$date     = $order['created_at']   ? date('d M Y, H:i', strtotime($order['created_at']))   : '—';
$paidAt   = $order['paid_at']      ? date('d M Y, H:i', strtotime($order['paid_at']))       : $date;
$pickupCode = !empty($order['pickup_code']) ? $order['pickup_code'] : $orderRef;

use AAM\Customer\Services\CustomerOrderService;
$statusLabel = CustomerOrderService::statusLabel($order['order_status'] ?? '');
$statusBadge = CustomerOrderService::statusBadge($order['order_status'] ?? '');
?>
<?php $view->section('styles'); ?>
<link rel="stylesheet" href="<?= $view->url('public/assets/customer/css/orders.css') ?>?v=<?= file_exists(PUBLIC_PATH . '/assets/customer/css/orders.css') ? filemtime(PUBLIC_PATH . '/assets/customer/css/orders.css') : '1' ?>">
<style>
@media print {
    .cp-toolbar, .cord-receipt-actions, nav { display: none !important; }
    .c-container { max-width: 100% !important; padding: 0 !important; }
    .cord-receipt-card { box-shadow: none !important; border: none !important; }
}
</style>
<?php $view->endSection(); ?>

<!-- ── Breadcrumb ────────────────────────────────────────────── -->
<div class="cp-toolbar">
    <div class="c-container">
        <div class="cp-toolbar__inner">
            <a href="<?= $view->url('customer/orders/' . (int)$order['id']) ?>"
               class="c-back-nav"
               aria-label="Back to Order">
                <i class="bi bi-arrow-left c-back-nav__icon" aria-hidden="true"></i>
                <span class="c-back-nav__label">Back to Order</span>
            </a>
            <nav class="cp-breadcrumb" aria-label="Breadcrumb">
                <a href="<?= $view->url('customer') ?>">Home</a>
                <span class="cp-breadcrumb__sep">/</span>
                <a href="<?= $view->url('customer/orders') ?>">My Orders</a>
                <span class="cp-breadcrumb__sep">/</span>
                <a href="<?= $view->url('customer/orders/' . (int)$order['id']) ?>"><?= e($orderRef) ?></a>
                <span class="cp-breadcrumb__sep">/</span>
                <span aria-current="page">Receipt</span>
            </nav>
            <button onclick="window.print()" class="c-btn c-btn-outline c-btn-sm">
                <i class="bi bi-printer" aria-hidden="true"></i>
                Print
            </button>
        </div>
    </div>
</div>

<div class="c-container" id="customer-main" style="padding:var(--c-space-8) var(--c-space-4) var(--c-space-16);">

    <div class="cord-receipt-card c-animate-fade-up" role="main" aria-label="Order receipt">

        <!-- Header -->
        <div class="cord-receipt-header">
            <div class="cord-receipt-brand">
                <strong class="cord-receipt-brand__name">Arsi University</strong>
                <span class="cord-receipt-brand__sub">AU-AgroSale</span>
            </div>
            <div class="cord-receipt-status">
                <span class="c-badge c-badge-<?= $statusBadge ?>" style="font-size:.9rem;padding:.4em .9em;">
                    <?= e($statusLabel) ?>
                </span>
            </div>
        </div>

        <hr class="cord-receipt-divider">

        <!-- Order & payment references -->
        <div class="cord-receipt-refs">
            <div class="cord-receipt-ref-row">
                <span>Order Reference</span>
                <strong class="cord-mono"><?= e($orderRef) ?></strong>
            </div>
            <div class="cord-receipt-ref-row">
                <span>Payment Reference</span>
                <strong class="cord-mono"><?= e($payRef ?: '—') ?></strong>
            </div>
            <div class="cord-receipt-ref-row">
                <span>Date</span>
                <strong><?= $date ?></strong>
            </div>
            <div class="cord-receipt-ref-row">
                <span>Paid At</span>
                <strong><?= $paidAt ?></strong>
            </div>
        </div>

        <hr class="cord-receipt-divider">

        <!-- Pickup shop -->
        <div class="cord-receipt-shop">
            <div class="cord-receipt-shop__label">
                <i class="bi bi-shop" aria-hidden="true"></i>
                Pickup Shop
            </div>
            <strong><?= e($shopName) ?></strong>
            <?php if ($address): ?>
            <span><?= e($address) ?></span>
            <?php endif; ?>
        </div>

        <hr class="cord-receipt-divider">

        <!-- Items table -->
        <table class="cord-receipt-table" aria-label="Ordered items">
            <thead>
                <tr>
                    <th scope="col">Product</th>
                    <th scope="col" class="text-right">Qty</th>
                    <th scope="col" class="text-right">Price</th>
                    <th scope="col" class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <?= e($item['product_name']) ?>
                        <?php if (!empty($item['unit_abbr'])): ?>
                        <span class="cord-unit"><?= e($item['unit_abbr']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-right"><?= number_format((float)$item['quantity'], 2) ?></td>
                    <td class="text-right">ETB <?= number_format((float)$item['unit_price'], 2) ?></td>
                    <td class="text-right">ETB <?= number_format((float)$item['subtotal'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="cord-receipt-total">
                    <td colspan="3"><strong>Total Paid</strong></td>
                    <td class="text-right"><strong>ETB <?= $total ?></strong></td>
                </tr>
            </tfoot>
        </table>

        <hr class="cord-receipt-divider">

        <!-- Payment info -->
        <div class="cord-receipt-payment">
            <div class="cord-receipt-ref-row">
                <span>Payment Method</span>
                <strong><?= e($pmName) ?></strong>
            </div>
            <div class="cord-receipt-ref-row">
                <span>Payment Status</span>
                <?php
                $recPayStatus = $order['payment_status'] ?? 'paid';
                $recPayBadge  = $recPayStatus === 'paid' ? 'success' : 'warning';
                $recPayLabel  = ($order['payment_method_type'] ?? '') === 'cash' && $recPayStatus === 'pending'
                    ? 'Pay at Shop' : strtoupper(\AAM\Customer\Services\CustomerOrderService::paymentStatusLabel($recPayStatus));
                ?>
                <span class="c-badge c-badge-<?= $recPayBadge ?>">
                    <?= $recPayStatus === 'paid' ? '<i class="bi bi-check-circle-fill" aria-hidden="true"></i>' : '' ?>
                    <?= e($recPayLabel) ?>
                </span>
            </div>
        </div>

        <hr class="cord-receipt-divider">

        <!-- Pickup code / QR -->
        <div class="cord-receipt-pickup-code" style="text-align:center;padding:var(--c-space-4) 0;">
            <div style="font-size:.8rem;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--c-muted);margin-bottom:var(--c-space-3);">
                <i class="bi bi-qr-code" aria-hidden="true"></i>
                Pickup Code
            </div>
            <div id="receiptQrCode"
                 data-qr="<?= e($pickupCode) ?>"
                 role="img"
                 aria-label="Pickup QR code"
                 style="display:inline-block;margin:0 auto var(--c-space-3);background:#fff;padding:8px;border-radius:8px;border:1px solid #e2e8f0;">
            </div>
            <div style="font-size:1.15rem;font-weight:700;letter-spacing:.12em;font-family:monospace;color:var(--c-text);">
                <?= e($pickupCode) ?>
            </div>
            <?php if (!empty($order['pickup_code']) && $order['pickup_code'] !== $orderRef): ?>
            <div style="font-size:.75rem;color:var(--c-muted);margin-top:.3rem;">
                Order ref: <?= e($orderRef) ?>
            </div>
            <?php endif; ?>
        </div>

        <hr class="cord-receipt-divider">

        <!-- Pickup instructions -->
        <div class="cord-receipt-instructions" role="note">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <p>
                Show this receipt or your order reference
                <strong><?= e($orderRef) ?></strong>
                at <strong><?= e($shopName) ?></strong> to collect your order.
            </p>
        </div>

        <!-- Footer -->
        <div class="cord-receipt-footer">
            <span>Thank you for shopping with AU-AgroSale</span>
            <span class="cord-receipt-footer__time">Printed: <?= date('d M Y, H:i') ?></span>
        </div>

    </div><!-- /.cord-receipt-card -->

    <!-- Actions (hidden on print) -->
    <div class="cord-receipt-actions">
        <button onclick="window.print()" class="c-btn c-btn-primary">
            <i class="bi bi-printer" aria-hidden="true"></i> Print Receipt
        </button>
    </div>

</div>

<?php if (empty($__accountLayoutInclude)): ?>
<?php $view->section('scripts'); ?>
<script src="<?= $view->asset('js/qrcode.min.js') ?>"></script>
<script>
(function () {
    'use strict';
    function initQr() {
        var el = document.getElementById('receiptQrCode');
        if (!el || !el.dataset.qr) return;
        var payload = el.dataset.qr;
        if (typeof QRCode !== 'undefined') {
            el.innerHTML = '';
            try {
                new QRCode(el, {
                    text:         payload,
                    width:        140,
                    height:       140,
                    colorDark:    '#1a2e1b',
                    colorLight:   '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
            } catch (e) {
                el.textContent = payload;
            }
        } else {
            el.textContent = payload;
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initQr);
    } else {
        setTimeout(initQr, 120);
    }
}());
</script>
<?php $view->endSection(); ?>
<?php endif; /* $__accountLayoutInclude */ ?>
