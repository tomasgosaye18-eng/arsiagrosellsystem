<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Step 8
 * Shop-Side Customer Order Detail + Pickup Verification
 * modules/shop/views/customer_orders/show.php
 * =========================================================
 * @var array      $order
 * @var array      $auditTrail
 * @var int        $shopId
 * @var bool       $canProcess
 * @var \AAM\Core\View $view
 */
declare(strict_types=1);

$order       = $order      ?? [];
$auditTrail  = $auditTrail ?? [];
$shopId      = $shopId     ?? 0;
$canProcess  = $canProcess ?? false;
$items       = $order['items'] ?? [];
$orderRef    = $order['order_reference'] ?? '';
$pickupCode  = $order['pickup_code']     ?? '';
$total       = number_format((float)($order['total_amount'] ?? 0), 2);
$status      = $order['order_status']    ?? '';
$paidAt      = !empty($order['paid_at'])       ? date('d/m/Y H:i', strtotime($order['paid_at']))       : null;
$readyAt     = !empty($order['ready_at'])      ? date('d/m/Y H:i', strtotime($order['ready_at']))      : null;
$completedAt = !empty($order['completed_at'])  ? date('d/m/Y H:i', strtotime($order['completed_at']))  : null;
$createdAt   = !empty($order['created_at'])    ? date('d/m/Y H:i', strtotime($order['created_at']))    : '—';

use AAM\Customer\Services\CustomerOrderService;
$statusLabel = CustomerOrderService::statusLabel($status);
$statusBadge = CustomerOrderService::statusBadge($status);

// Pickup is valid only when paid + ready_for_pickup
$canCompletePickup = (
    $status === 'ready_for_pickup' &&
    $order['payment_status'] === 'paid' &&
    !empty($items) &&
    $canProcess
);
?>

<!-- ── Breadcrumb ─────────────────────────────────────── -->
<div class="page-header mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small mb-2">
            <li class="breadcrumb-item">
                <a href="<?= $view->url('customer/shop-orders') ?>">
                    <i class="bi bi-bag-check me-1"></i>Customer Orders
                </a>
            </li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($orderRef) ?></li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h1 class="h4 mb-1 d-flex align-items-center flex-wrap gap-2">
                <code class="fs-5"><?= e($orderRef) ?></code>
                <span class="badge bg-<?= $statusBadge ?> fs-6">
                    <?php if ($status === 'ready_for_pickup'): ?><i class="bi bi-check-circle me-1"></i>
                    <?php elseif ($status === 'preparing'):    ?><i class="bi bi-gear-fill me-1"></i>
                    <?php elseif ($status === 'paid'):          ?><i class="bi bi-cash-coin me-1"></i>
                    <?php elseif ($status === 'completed'):     ?><i class="bi bi-check2-all me-1"></i>
                    <?php elseif ($status === 'cancelled'):     ?><i class="bi bi-x-circle me-1"></i>
                    <?php endif; ?>
                    <?= $statusLabel ?>
                </span>
                <?php if ($order['payment_status'] === 'paid'): ?>
                <span class="badge bg-success"><i class="bi bi-lock-fill me-1"></i>PAID</span>
                <?php elseif ($order['payment_status'] === 'pending'): ?>
                <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>PAYMENT PENDING</span>
                <?php endif; ?>
            </h1>
            <div class="small text-muted">
                <i class="bi bi-clock me-1"></i><?= $createdAt ?>
                <?php if (!empty($order['shop_name'])): ?>
                &nbsp;·&nbsp;<i class="bi bi-shop me-1"></i><?= e($order['shop_name']) ?>
                <?php endif; ?>
            </div>
        </div>
        <a href="<?= $view->url('customer/shop-orders') ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Queue
        </a>
    </div>
</div>

<div class="row g-4">

    <!-- ═══════════════════════════════════════════════
         LEFT COLUMN: Items + Customer info + Timeline
         ═══════════════════════════════════════════════ -->
    <div class="col-12 col-lg-7">

        <!-- Order Items -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bag me-2 text-primary"></i>Order Items</span>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                    <?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?>
                </span>
            </div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="min-width:160px;">Product</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end d-none d-sm-table-cell">Unit Price</th>
                            <th class="text-end pe-3">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="ps-3">
                                <div class="fw-semibold small"><?= e($item['product_name']) ?></div>
                                <?php if (!empty($item['product_sku'])): ?>
                                <div class="text-muted" style="font-size:.72rem;">
                                    SKU: <?= e($item['product_sku']) ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end small">
                                <span class="fw-semibold"><?= rtrim(rtrim(number_format((float)$item['quantity'], 3), '0'), '.') ?></span>
                                <?php if (!empty($item['unit_abbr'])): ?>
                                <span class="text-muted"><?= e($item['unit_abbr']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-muted small d-none d-sm-table-cell">
                                ETB <?= number_format((float)$item['unit_price'], 2) ?>
                            </td>
                            <td class="text-end pe-3 fw-semibold small">
                                ETB <?= number_format((float)$item['subtotal'], 2) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <td colspan="2" class="ps-3 fw-bold">Order Total</td>
                            <td class="d-none d-sm-table-cell"></td>
                            <td class="text-end pe-3 fw-bold fs-5 text-success">
                                ETB <?= $total ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Customer & Payment Info -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header">
                <i class="bi bi-person me-2 text-primary"></i>Customer &amp; Payment Details
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="text-muted" style="font-size:.75rem;">Customer Name</div>
                        <div class="fw-semibold"><?= e($order['customer_name']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted" style="font-size:.75rem;">Phone</div>
                        <div>
                            <?php if (!empty($order['customer_phone'])): ?>
                            <a href="tel:<?= e($order['customer_phone']) ?>"
                               class="text-decoration-none">
                                <i class="bi bi-telephone me-1"></i><?= e($order['customer_phone']) ?>
                            </a>
                            <?php else: ?>—
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted" style="font-size:.75rem;">Pickup Shop</div>
                        <div class="fw-semibold">
                            <i class="bi bi-shop me-1 text-success"></i>
                            <?= e($order['shop_name'] ?? '—') ?>
                            <?php if (!empty($order['shop_location'])): ?>
                            <div class="small text-muted fw-normal"><?= e($order['shop_location']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted" style="font-size:.75rem;">Order Date</div>
                        <div><?= $createdAt ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted" style="font-size:.75rem;">Payment Method</div>
                        <div><?= e($order['payment_method_name'] ?? 'Mobile Banking') ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted" style="font-size:.75rem;">Payment Status</div>
                        <?php
                        $pBadge = match($order['payment_status'] ?? '') {
                            'paid'      => 'success',
                            'pending'   => 'warning',
                            'failed'    => 'danger',
                            'expired'   => 'secondary',
                            'cancelled' => 'secondary',
                            default     => 'secondary',
                        };
                        ?>
                        <span class="badge bg-<?= $pBadge ?>">
                            <i class="bi bi-<?= $pBadge === 'success' ? 'check-circle' : 'clock' ?> me-1"></i>
                            <?= CustomerOrderService::paymentStatusLabel($order['payment_status'] ?? '') ?>
                        </span>
                    </div>
                    <div class="col-12">
                        <div class="text-muted" style="font-size:.75rem;">Payment Reference</div>
                        <div>
                            <code class="user-select-all small"><?= e($order['payment_reference'] ?? '—') ?></code>
                        </div>
                    </div>
                    <?php if ($paidAt): ?>
                    <div class="col-sm-6">
                        <div class="text-muted" style="font-size:.75rem;">Paid At</div>
                        <div><i class="bi bi-check-circle-fill text-success me-1"></i><?= $paidAt ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($order['notes'])): ?>
                    <div class="col-12">
                        <div class="text-muted" style="font-size:.75rem;">Order Notes</div>
                        <div class="small"><?= e($order['notes']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Order History / Audit Trail -->
        <?php if (!empty($auditTrail)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header">
                <i class="bi bi-clock-history me-2 text-secondary"></i>Order History
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($auditTrail as $entry):
                        // Data comes from customer_order_status_changes:
                        // columns: old_status, new_status, description, action_at, user_name, username
                        $oldS  = $entry['old_status'] ?? '';
                        $newS  = $entry['new_status']  ?? '';
                        $atStr = !empty($entry['action_at'])
                                   ? date('d/m/Y H:i', strtotime($entry['action_at']))
                                   : '—';
                        $who   = $entry['user_name'] ?? ($entry['username'] ?? 'System');
                    ?>
                    <li class="list-group-item px-3 py-2">
                        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                            <div>
                                <?php if ($oldS && $newS): ?>
                                <span class="badge bg-<?= CustomerOrderService::statusBadge($oldS) ?> bg-opacity-50 small">
                                    <?= CustomerOrderService::statusLabel($oldS) ?>
                                </span>
                                <i class="bi bi-arrow-right mx-1 text-muted small"></i>
                                <span class="badge bg-<?= CustomerOrderService::statusBadge($newS) ?>">
                                    <?= CustomerOrderService::statusLabel($newS) ?>
                                </span>
                                <?php else: ?>
                                <span class="small fw-semibold">
                                    <?= e($entry['description'] ?? '') ?>
                                </span>
                                <?php endif; ?>
                                <?php if (!empty($entry['description']) && ($oldS || $newS)): ?>
                                <div class="text-muted small mt-1 fst-italic">
                                    <?= e($entry['description']) ?>
                                </div>
                                <?php endif; ?>
                                <div class="text-muted small mt-1">
                                    <i class="bi bi-person me-1"></i><?= e($who) ?>
                                </div>
                            </div>
                            <div class="text-muted small text-nowrap">
                                <i class="bi bi-clock me-1"></i><?= $atStr ?>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /.col-lg-7 -->

    <!-- ═══════════════════════════════════════════════
         RIGHT COLUMN: Status Card + Actions
         ═══════════════════════════════════════════════ -->
    <div class="col-12 col-lg-5">

        <!-- ── Pickup Code Card ─────────────────────── -->
        <?php if (!empty($pickupCode) && in_array($status, ['pending_payment','paid','preparing','ready_for_pickup'], true)): ?>
        <div class="card border-0 shadow-sm mb-4 border-start border-4 border-warning">
            <div class="card-body text-center py-4">
                <div class="text-muted small mb-1">
                    <i class="bi bi-key me-1"></i>Pickup Verification Code
                </div>
                <div class="display-4 fw-bold font-monospace text-warning letter-spacing-wide">
                    <?= e($pickupCode) ?>
                </div>
                <div class="text-muted small mt-2">
                    Customer shows this code at pickup. Matches order reference
                    <strong><?= e($orderRef) ?></strong>.
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Status + Actions Card ────────────────── -->
        <div class="card border-0 shadow-sm mb-4 border-start border-4 border-<?= $statusBadge ?>">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-activity me-1 text-<?= $statusBadge ?>"></i>
                <span>Order Status &amp; Actions</span>
            </div>
            <div class="card-body">

                <!-- Status indicator + timeline -->
                <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                         style="width:52px;height:52px;background:rgba(var(--bs-<?= $statusBadge ?>-rgb),.15);flex-shrink:0;">
                        <?php if ($status === 'ready_for_pickup'): ?>
                        <i class="bi bi-check-circle-fill fs-4 text-<?= $statusBadge ?>"></i>
                        <?php elseif ($status === 'preparing'): ?>
                        <i class="bi bi-gear-fill fs-4 text-<?= $statusBadge ?>"></i>
                        <?php elseif ($status === 'paid'): ?>
                        <i class="bi bi-cash-coin fs-4 text-<?= $statusBadge ?>"></i>
                        <?php elseif ($status === 'completed'): ?>
                        <i class="bi bi-check2-all fs-4 text-<?= $statusBadge ?>"></i>
                        <?php elseif ($status === 'cancelled' || $status === 'payment_failed'): ?>
                        <i class="bi bi-x-circle-fill fs-4 text-<?= $statusBadge ?>"></i>
                        <?php else: ?>
                        <i class="bi bi-clock fs-4 text-<?= $statusBadge ?>"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="fw-bold fs-5"><?= $statusLabel ?></div>
                        <div class="small text-muted">Current order status</div>
                    </div>
                </div>

                <!-- Timeline markers -->
                <div class="small mb-4">
                    <div class="d-flex gap-2 mb-2 align-items-center">
                        <i class="bi bi-circle-fill text-muted" style="font-size:.5rem;"></i>
                        <span class="text-muted">Order placed: <strong><?= $createdAt ?></strong></span>
                    </div>
                    <?php if ($paidAt): ?>
                    <div class="d-flex gap-2 mb-2 align-items-center">
                        <i class="bi bi-check-circle-fill text-success" style="font-size:.85rem;"></i>
                        <span class="text-muted">Payment confirmed: <strong><?= $paidAt ?></strong></span>
                    </div>
                    <?php else: ?>
                    <div class="d-flex gap-2 mb-2 align-items-center">
                        <i class="bi bi-clock text-warning" style="font-size:.85rem;"></i>
                        <span class="text-muted">Awaiting payment confirmation</span>
                    </div>
                    <?php endif; ?>
                    <?php if ($readyAt): ?>
                    <div class="d-flex gap-2 mb-2 align-items-center">
                        <i class="bi bi-check-circle-fill text-success" style="font-size:.85rem;"></i>
                        <span class="text-muted">Ready for pickup: <strong><?= $readyAt ?></strong></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($completedAt): ?>
                    <div class="d-flex gap-2 mb-2 align-items-center">
                        <i class="bi bi-check2-all text-success" style="font-size:.85rem;"></i>
                        <span class="text-muted">Completed: <strong><?= $completedAt ?></strong></span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($order['pickup_instructions'])): ?>
                <div class="alert alert-info py-2 small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>Pickup Instructions:</strong> <?= e($order['pickup_instructions']) ?>
                </div>
                <?php endif; ?>

                <!-- Payment not paid warning — block all actions -->
                <?php if ($order['payment_status'] !== 'paid' && !in_array($status, ['completed','cancelled','payment_failed'], true)): ?>

                <?php
                    $isCashOrder = (($order['payment_method_type'] ?? '') === 'cash');
                ?>

                <?php if ($canProcess && $isCashOrder && $status === 'pending_payment'): ?>
                <!-- ── CONFIRM CASH PAYMENT ─────────────────────── -->
                <div class="alert alert-warning small mb-3">
                    <i class="bi bi-cash-coin me-2"></i>
                    <strong>Cash payment pending.</strong><br>
                    The customer should pay <strong>ETB <?= $total ?></strong> in cash.
                    Confirm once you have received the full amount.
                </div>
                <button type="button"
                        class="btn btn-warning w-100 mb-3 fw-bold"
                        style="min-height:48px;font-size:1rem;"
                        onclick="openCashConfirmModal()">
                    <i class="bi bi-cash-coin me-2"></i>Confirm Cash Payment
                </button>

                <!-- Cash Confirmation Modal -->
                <div class="modal fade" id="cashConfirmModal" tabindex="-1"
                     aria-labelledby="cashConfirmModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
                        <div class="modal-content">
                            <div class="modal-header border-0 pb-0">
                                <h6 class="modal-title fw-bold" id="cashConfirmModalLabel">
                                    <i class="bi bi-cash-coin me-2 text-warning"></i>Confirm Cash Payment
                                </h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <dl class="row small mb-3">
                                    <dt class="col-5 text-muted fw-normal">Order</dt>
                                    <dd class="col-7"><code class="fw-bold"><?= e($orderRef) ?></code></dd>
                                    <dt class="col-5 text-muted fw-normal">Customer</dt>
                                    <dd class="col-7"><?= e($order['customer_name'] ?? '—') ?></dd>
                                    <dt class="col-5 text-muted fw-normal">Pickup Shop</dt>
                                    <dd class="col-7"><?= e($order['shop_name'] ?? '—') ?></dd>
                                    <dt class="col-5 text-muted fw-normal">Amount Due</dt>
                                    <dd class="col-7 fw-bold text-success fs-6">ETB <?= $total ?></dd>
                                    <dt class="col-5 text-muted fw-normal">Payment Method</dt>
                                    <dd class="col-7">Cash</dd>
                                </dl>
                                <hr>
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox"
                                           id="cashReceivedCheck" required>
                                    <label class="form-check-label small fw-semibold"
                                           for="cashReceivedCheck">
                                        I confirm that I have received the full cash amount
                                        of <strong>ETB <?= $total ?></strong> from the customer.
                                    </label>
                                </div>
                            </div>
                            <div class="modal-footer border-0 pt-0">
                                <button type="button" class="btn btn-secondary btn-sm"
                                        data-bs-dismiss="modal">Cancel</button>
                                <form method="POST"
                                      action="<?= $view->url('customer/shop-orders/' . (int)$order['id'] . '/confirm-cash') ?>"
                                      id="cashConfirmForm">
                                    <?= csrf_field() ?>
                                    <button type="submit"
                                            id="cashConfirmSubmitBtn"
                                            class="btn btn-warning btn-sm fw-bold"
                                            disabled>
                                        <i class="bi bi-check2-circle me-1"></i>Confirm Payment
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                function openCashConfirmModal() {
                    var modal = new bootstrap.Modal(document.getElementById('cashConfirmModal'));
                    modal.show();
                    // Reset checkbox on every open
                    document.getElementById('cashReceivedCheck').checked = false;
                    document.getElementById('cashConfirmSubmitBtn').disabled = true;
                }
                document.getElementById('cashReceivedCheck').addEventListener('change', function () {
                    document.getElementById('cashConfirmSubmitBtn').disabled = !this.checked;
                });
                document.getElementById('cashConfirmForm').addEventListener('submit', function () {
                    var btn = document.getElementById('cashConfirmSubmitBtn');
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Confirming…';
                });
                </script>

                <?php else: ?>
                <div class="alert alert-warning small mb-3">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Payment not confirmed.</strong><br>
                    This order cannot be processed until payment is confirmed PAID.
                    Actions are disabled until payment is verified.
                </div>
                <?php endif; ?>

                <?php endif; ?>

                <?php if ($canProcess && $order['payment_status'] === 'paid'): ?>

                    <!-- ── START PREPARING ─────────────────────── -->
                    <?php if ($status === 'paid'): ?>
                    <form method="POST"
                          action="<?= $view->url('customer/shop-orders/' . (int)$order['id'] . '/prepare') ?>">
                        <?= csrf_field() ?>
                        <button type="submit"
                                class="btn btn-primary w-100 mb-2"
                                style="min-height:48px;font-size:1rem;">
                            <i class="bi bi-play-circle-fill me-2"></i>Start Preparing
                        </button>
                        <p class="text-muted small text-center mb-3">
                            Acknowledge this order and begin preparing items.
                        </p>
                    </form>
                    <?php endif; ?>

                    <!-- ── MARK READY FOR PICKUP ─────────────────  -->
                    <?php if (in_array($status, ['paid', 'preparing'], true)): ?>
                    <form method="POST"
                          action="<?= $view->url('customer/shop-orders/' . (int)$order['id'] . '/ready') ?>">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label for="instructions" class="form-label small fw-semibold">
                                Pickup Instructions
                                <span class="text-muted fw-normal">(optional)</span>
                            </label>
                            <input type="text"
                                   id="instructions"
                                   name="instructions"
                                   class="form-control form-control-sm"
                                   placeholder="e.g. Go to counter 2, ask for delivery">
                        </div>
                        <button type="submit"
                                class="btn btn-success w-100 mb-3"
                                style="min-height:48px;font-size:1rem;">
                            <i class="bi bi-check2-circle me-2"></i>Mark Ready for Pickup
                        </button>
                    </form>
                    <?php endif; ?>

                    <!-- ── PICKUP VERIFICATION + CONFIRM ──────────── -->
                    <?php if ($status === 'ready_for_pickup'): ?>

                    <!-- Verification checklist -->
                    <div class="card border-success mb-3">
                        <div class="card-header bg-success bg-opacity-10 py-2 text-success">
                            <i class="bi bi-shield-check me-2"></i>Pickup Verification Checklist
                        </div>
                        <ul class="list-group list-group-flush small">
                            <li class="list-group-item py-2 d-flex align-items-center gap-2">
                                <i class="bi bi-check-circle-fill text-success"></i>
                                Order Ref: <strong class="font-monospace"><?= e($orderRef) ?></strong>
                            </li>
                            <?php if (!empty($pickupCode)): ?>
                            <li class="list-group-item py-2 d-flex align-items-center gap-2">
                                <i class="bi bi-key-fill text-warning"></i>
                                Pickup Code: <strong class="font-monospace text-warning"><?= e($pickupCode) ?></strong>
                            </li>
                            <?php endif; ?>
                            <li class="list-group-item py-2 d-flex align-items-center gap-2">
                                <i class="bi bi-shop text-success"></i>
                                Pickup at: <strong><?= e($order['shop_name'] ?? '—') ?></strong>
                            </li>
                            <li class="list-group-item py-2 d-flex align-items-center gap-2">
                                <?php if ($order['payment_status'] === 'paid'): ?>
                                <i class="bi bi-check-circle-fill text-success"></i>
                                <span>Payment: <strong class="text-success">PAID ✓</strong></span>
                                <?php else: ?>
                                <i class="bi bi-x-circle-fill text-danger"></i>
                                <span>Payment: <strong class="text-danger">NOT PAID — cannot complete</strong></span>
                                <?php endif; ?>
                            </li>
                            <li class="list-group-item py-2 d-flex align-items-center gap-2">
                                <i class="bi bi-check-circle-fill text-success"></i>
                                <?= count($items) ?> product<?= count($items) !== 1 ? 's' : '' ?> ready for handover
                            </li>
                        </ul>
                    </div>

                    <?php if ($canCompletePickup): ?>
                    <form method="POST"
                          action="<?= $view->url('customer/shop-orders/' . (int)$order['id'] . '/complete') ?>"
                          onsubmit="return confirmPickup()">
                        <?= csrf_field() ?>
                        <button type="submit"
                                class="btn btn-success w-100 btn-lg fw-bold"
                                style="min-height:54px;font-size:1.1rem;">
                            <i class="bi bi-bag-check-fill me-2"></i>Confirm Pickup — Hand Over to Customer
                        </button>
                    </form>
                    <p class="text-muted small mt-2 text-center">
                        <i class="bi bi-shield-check me-1"></i>
                        Inventory is already reserved. This action records the handover only.
                    </p>
                    <?php else: ?>
                    <div class="alert alert-warning small">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Cannot confirm pickup — payment must be PAID before completing this order.
                    </div>
                    <?php endif; ?>

                    <?php endif; /* ready_for_pickup */ ?>

                <?php elseif (!$canProcess && !in_array($status, ['completed','cancelled','payment_failed'], true)): ?>
                <!-- Non-salesperson viewing: show status only, no action buttons -->
                <div class="alert alert-light border small">
                    <i class="bi bi-eye me-1"></i>
                    View-only access. Only assigned salespersons can process this order.
                </div>
                <?php endif; /* canProcess */ ?>

                <!-- ── COMPLETED STATE ─────────────────────────── -->
                <?php if ($status === 'completed'): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                    <strong>Order Completed</strong><br>
                    <span class="small">Customer has received their products.</span>
                    <?php if ($completedAt): ?>
                    <div class="small mt-1 text-muted">Completed at <?= $completedAt ?></div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($order['sale_id'])): ?>
                <div class="small text-muted text-center">
                    <i class="bi bi-receipt me-1"></i>
                    Sale record: <strong>#<?= (int)$order['sale_id'] ?></strong>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <!-- ── CANCELLED / FAILED STATE ───────────────────── -->
                <?php if (in_array($status, ['cancelled', 'payment_failed'], true)): ?>
                <div class="alert alert-secondary mb-0">
                    <i class="bi bi-x-circle me-2 fs-5"></i>
                    <strong>Order <?= $status === 'cancelled' ? 'Cancelled' : 'Payment Failed' ?></strong>
                    <?php if (!empty($order['cancellation_reason'])): ?>
                    <div class="small mt-1"><?= e($order['cancellation_reason']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div><!-- /.card-body -->
        </div><!-- /.card -->

        <!-- ── Order Summary Card ──────────────────── -->
        <div class="card border-0 shadow-sm">
            <div class="card-header">
                <i class="bi bi-info-circle me-2"></i>Order Summary
            </div>
            <div class="card-body py-2">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted fw-normal">Order #</dt>
                    <dd class="col-7"><code><?= e($orderRef) ?></code></dd>

                    <?php if (!empty($pickupCode)): ?>
                    <dt class="col-5 text-muted fw-normal">Pickup Code</dt>
                    <dd class="col-7">
                        <code class="text-warning fw-bold"><?= e($pickupCode) ?></code>
                    </dd>
                    <?php endif; ?>

                    <dt class="col-5 text-muted fw-normal">Items</dt>
                    <dd class="col-7"><?= count($items) ?> product<?= count($items) !== 1 ? 's' : '' ?></dd>

                    <dt class="col-5 text-muted fw-normal">Total</dt>
                    <dd class="col-7 fw-bold text-success">ETB <?= $total ?></dd>

                    <dt class="col-5 text-muted fw-normal">Shop</dt>
                    <dd class="col-7"><?= e($order['shop_name'] ?? '—') ?></dd>

                    <?php if (!empty($order['processed_by_name'])): ?>
                    <dt class="col-5 text-muted fw-normal">Processed By</dt>
                    <dd class="col-7"><?= e($order['processed_by_name']) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

    </div><!-- /.col-lg-5 -->

</div><!-- /.row -->

<script>
function confirmPickup() {
    return confirm(
        'Confirm customer pickup for order <?= e(addslashes($orderRef)) ?>?\n\n' +
        'This marks the order as COMPLETED.\n' +
        'The customer has received their products.\n\n' +
        'Proceed?'
    );
}
</script>
