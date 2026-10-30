<?php
/**
 * GM — Customer Order Receipt (read-only, view + print)
 * Route: GET /gm/receipts/order/{id}
 *        GET /gm/receipts/order/{id}?print=1  → print layout
 *
 * @var array  $order
 * @var string $userRole
 */
declare(strict_types=1);

use AAM\GeneralManager\Services\GMSalesReceiptsService as SVC;

$order    ??= [];
$userRole ??= '';
$items     = $order['items'] ?? [];
$isPrint   = ($_GET['print'] ?? '') === '1';

$etb = fn($v): string => 'ETB ' . number_format((float)$v, 2);
$dt  = fn($v): string => $v ? date('d/m/Y H:i', strtotime($v)) : '—';
$d   = fn($v): string => $v ? date('d/m/Y', strtotime($v)) : '—';

$oStatus     = $order['order_status']   ?? '';
$pStatus     = $order['payment_status'] ?? '';
$oBadge      = SVC::ecoStatusBadge($oStatus);
$oLabel      = SVC::ecoStatusLabel($oStatus);
$pmName      = $order['payment_method_name'] ?? ($order['payment_method_type'] ?? 'Unknown');
$pmType      = $order['payment_method_type'] ?? '';
?>

<?php if (!$isPrint): ?>
<!-- ── Screen navigation ──────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-3 no-print">
    <a href="<?= url('gm/receipts') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Receipts
    </a>
    <div class="d-flex gap-2">
        <a href="<?= url('gm/orders/' . (int)$order['id']) ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-bag-check me-1"></i>View Order
        </a>
        <a href="<?= url('gm/receipts/order/' . (int)$order['id']) ?>?print=1"
           target="_blank"
           class="btn btn-outline-primary btn-sm">
            <i class="bi bi-printer me-1"></i>Print
        </a>
    </div>
</div>

<!-- ── Header card ────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-4"
     style="border-left:4px solid var(--bs-<?= $oBadge ?>)!important;">
    <div class="card-body">
        <div class="row g-3 align-items-start">
            <div class="col-md-6">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="rounded-circle bg-info bg-opacity-10
                                d-flex align-items-center justify-content-center"
                         style="width:48px;height:48px;">
                        <i class="bi bi-bag-check-fill fs-5 text-info"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold font-monospace">
                            <?= e($order['order_reference'] ?? '—') ?>
                        </h5>
                        <?php if (!empty($order['payment_reference'])): ?>
                        <div class="small text-muted font-monospace">
                            Pay Ref: <?= e($order['payment_reference']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="badge bg-info text-dark" style="font-size:.65rem;letter-spacing:.04em;">E-COMMERCE</span>
                    <span class="badge bg-<?= $oBadge ?> rounded-pill"><?= e($oLabel) ?></span>
                    <span class="badge bg-secondary bg-opacity-15 text-dark rounded-pill">
                        <i class="bi bi-shop me-1"></i><?= e($order['shop_name'] ?? '—') ?>
                    </span>
                </div>
            </div>
            <div class="col-md-6 text-md-end">
                <div class="fs-3 fw-bold text-success mb-1">
                    <?= $etb($order['total_amount'] ?? 0) ?>
                </div>
                <div class="text-muted small">
                    <?= (int)($order['item_count'] ?? count($items)) ?> item(s)
                    &bull; <?= e($pmName) ?>
                </div>
                <div class="text-muted small"><?= $dt($order['created_at'] ?? null) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">

    <!-- ── Left: items ───────────────────────────────────── -->
    <div class="col-lg-8">

        <!-- Order Items -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-2">
                <span class="fw-semibold small">
                    <i class="bi bi-list-ul me-2 text-secondary"></i>Order Items
                </span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($items)): ?>
                <div class="text-center py-4 text-muted small">No items found.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">#</th>
                                <th>Product</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end pe-3">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $i => $item): ?>
                            <tr>
                                <td class="ps-3 text-muted small"><?= $i + 1 ?></td>
                                <td>
                                    <div class="small fw-semibold"><?= e($item['product_name'] ?? '—') ?></div>
                                    <?php if (!empty($item['product_sku'])): ?>
                                    <div class="text-muted" style="font-size:.65rem;">SKU: <?= e($item['product_sku']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end small"><?= $etb($item['unit_price'] ?? 0) ?></td>
                                <td class="text-center small">
                                    <?= number_format((float)($item['quantity'] ?? 0), 3) ?>
                                    <?php if (!empty($item['unit_abbr'])): ?>
                                    <span class="text-muted"><?= e($item['unit_abbr']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end small fw-semibold pe-3"><?= $etb($item['subtotal'] ?? 0) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="4" class="text-end ps-3 fw-semibold small">Total</td>
                                <td class="text-end pe-3 fw-bold"><?= $etb($order['total_amount'] ?? 0) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pickup instructions / notes -->
        <?php if (!empty($order['pickup_instructions']) || !empty($order['notes'])): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-2">
                <span class="fw-semibold small">
                    <i class="bi bi-info-circle me-2 text-secondary"></i>Notes &amp; Instructions
                </span>
            </div>
            <div class="card-body py-3 small">
                <?php if (!empty($order['pickup_instructions'])): ?>
                <div class="mb-2">
                    <span class="text-muted">Pickup Instructions:</span>
                    <div class="p-2 bg-light rounded mt-1"><?= e($order['pickup_instructions']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($order['notes'])): ?>
                <div>
                    <span class="text-muted">Customer Notes:</span>
                    <div class="p-2 bg-light rounded mt-1"><?= e($order['notes']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- ── Right: metadata ───────────────────────────────── -->
    <div class="col-lg-4">

        <!-- Customer -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-2">
                <span class="fw-semibold small">
                    <i class="bi bi-person-fill me-2 text-secondary"></i>Customer
                </span>
            </div>
            <div class="card-body py-3 small">
                <div class="fw-semibold mb-1"><?= e($order['customer_name'] ?? '—') ?></div>
                <?php if (!empty($order['customer_phone'])): ?>
                <div class="text-muted mb-1"><i class="bi bi-telephone me-1"></i><?= e($order['customer_phone']) ?></div>
                <?php endif; ?>
                <?php if (!empty($order['customer_email'])): ?>
                <div class="text-muted"><i class="bi bi-envelope me-1"></i><?= e($order['customer_email']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payment -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-2">
                <span class="fw-semibold small">
                    <i class="bi bi-credit-card me-2 text-secondary"></i>Payment
                </span>
            </div>
            <div class="card-body py-2 px-3 small">
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Method</span>
                    <span class="fw-semibold"><?= e($pmName) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Payment Status</span>
                    <span class="badge bg-<?= ($pStatus === 'paid') ? 'success' : 'secondary' ?> rounded-pill">
                        <?= ucfirst($pStatus) ?>
                    </span>
                </div>
                <?php if (!empty($order['payment_reference'])): ?>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Reference</span>
                    <span class="font-monospace" style="font-size:.65rem;"><?= e($order['payment_reference']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($order['paid_at'])): ?>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Paid At</span>
                    <span><?= $dt($order['paid_at']) ?></span>
                </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between py-1 border-top mt-1 pt-2">
                    <span class="text-muted fw-semibold">Total</span>
                    <span class="fw-bold"><?= $etb($order['total_amount'] ?? 0) ?></span>
                </div>
            </div>
        </div>

        <!-- Shop & Pickup -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-2">
                <span class="fw-semibold small">
                    <i class="bi bi-shop me-2 text-warning"></i>Shop &amp; Pickup
                </span>
            </div>
            <div class="card-body py-3 small">
                <div class="fw-semibold"><?= e($order['shop_name'] ?? '—') ?></div>
                <?php if (!empty($order['shop_location'])): ?>
                <div class="text-muted mt-1"><i class="bi bi-geo-alt me-1"></i><?= e($order['shop_location']) ?></div>
                <?php endif; ?>
                <?php if (!empty($order['processed_by_name'])): ?>
                <div class="text-muted mt-2">
                    <i class="bi bi-person-check me-1"></i>Processed by: <?= e($order['processed_by_name']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Timeline -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-2">
                <span class="fw-semibold small">
                    <i class="bi bi-clock me-2 text-secondary"></i>Timeline
                </span>
            </div>
            <div class="card-body py-2 px-3 small">
                <?php
                $timeline = [
                    ['Order Created',    $order['created_at']   ?? null, 'secondary', 'bi-plus-circle'],
                    ['Payment Received', $order['paid_at']       ?? null, 'info',      'bi-credit-card'],
                    ['Ready for Pickup', $order['ready_at']      ?? null, 'success',   'bi-bag-check'],
                    ['Completed',        $order['completed_at']  ?? null, 'success',   'bi-check2-all'],
                    ['Cancelled',        $order['cancelled_at']  ?? null, 'danger',    'bi-x-circle'],
                ];
                foreach ($timeline as [$lbl, $ts, $col, $ico]):
                    if (!$ts) continue;
                ?>
                <div class="d-flex align-items-center gap-2 py-1 border-bottom">
                    <i class="bi <?= $ico ?> text-<?= $col ?> flex-shrink-0"></i>
                    <div>
                        <div class="fw-semibold lh-sm"><?= e($lbl) ?></div>
                        <div class="text-muted" style="font-size:.68rem;"><?= $dt($ts) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- GM Note -->
        <div class="alert alert-secondary d-flex gap-2 py-2 small">
            <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
            <div>
                <strong>GM — read only.</strong><br>
                Order management is handled at
                <a href="<?= url('customer/shop-orders?shop_id=' . (int)($order['shop_id'] ?? 0)) ?>"
                   class="alert-link">the shop order queue</a>.
            </div>
        </div>

    </div>

</div><!-- /.row -->

<?php else: /* PRINT LAYOUT */ ?>

<!-- ── Minimal print layout ─────────────────────────── -->
<div style="max-width:320px;margin:0 auto;font-family:monospace;font-size:11px;padding:8px;">

    <div style="text-align:center;margin-bottom:8px;">
        <div style="font-size:16px;font-weight:700;"><?= e($order['shop_name'] ?? 'Shop') ?></div>
        <?php if (!empty($order['shop_location'])): ?>
        <div style="color:#555;"><?= e($order['shop_location']) ?></div>
        <?php endif; ?>
        <div style="font-size:10px;color:#888;margin-top:2px;">Customer Order Receipt — GM Copy</div>
    </div>

    <table style="width:100%;border-collapse:collapse;margin-bottom:6px;">
        <tr><td style="color:#666;">Order Ref</td><td style="text-align:right;font-weight:700;"><?= e($order['order_reference'] ?? '') ?></td></tr>
        <?php if (!empty($order['payment_reference'])): ?>
        <tr><td style="color:#666;">Pay Ref</td><td style="text-align:right;font-size:10px;"><?= e($order['payment_reference']) ?></td></tr>
        <?php endif; ?>
        <tr><td style="color:#666;">Customer</td><td style="text-align:right;"><?= e($order['customer_name'] ?? '') ?></td></tr>
        <?php if (!empty($order['customer_phone'])): ?>
        <tr><td style="color:#666;">Phone</td><td style="text-align:right;"><?= e($order['customer_phone']) ?></td></tr>
        <?php endif; ?>
        <tr><td style="color:#666;">Date</td><td style="text-align:right;"><?= $d($order['created_at'] ?? null) ?></td></tr>
        <tr><td style="color:#666;">Payment</td><td style="text-align:right;font-weight:600;"><?= e(strtoupper($pmName)) ?></td></tr>
        <tr><td style="color:#666;">Status</td><td style="text-align:right;"><?= e($oLabel) ?></td></tr>
    </table>

    <div style="border-top:1px dashed #ccc;border-bottom:1px dashed #ccc;padding:4px 0;margin:4px 0;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="font-weight:700;">
                    <th style="text-align:left;">Item</th>
                    <th style="text-align:right;">Qty</th>
                    <th style="text-align:right;">Price</th>
                    <th style="text-align:right;">Sub</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= e($item['product_name']) ?></td>
                    <td style="text-align:right;"><?= number_format((float)$item['quantity'], 2) ?><?= ' ' . e($item['unit_abbr'] ?? '') ?></td>
                    <td style="text-align:right;"><?= number_format((float)$item['unit_price'], 2) ?></td>
                    <td style="text-align:right;font-weight:600;"><?= number_format((float)$item['subtotal'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <table style="width:100%;border-collapse:collapse;margin-top:4px;">
        <tr><td style="color:#666;">Items</td><td style="text-align:right;"><?= (int)($order['item_count'] ?? count($items)) ?></td></tr>
        <tr style="font-weight:700;font-size:13px;border-top:1px solid #333;">
            <td style="padding-top:3px;">TOTAL</td>
            <td style="text-align:right;padding-top:3px;">ETB <?= number_format((float)($order['total_amount'] ?? 0), 2) ?></td>
        </tr>
        <tr><td style="color:#666;">Pay Status</td><td style="text-align:right;font-weight:700;"><?= e(strtoupper($pStatus)) ?></td></tr>
        <?php if (!empty($order['paid_at'])): ?>
        <tr><td style="color:#666;">Paid At</td><td style="text-align:right;"><?= $d($order['paid_at']) ?> <?= $order['paid_at'] ? date('H:i', strtotime($order['paid_at'])) : '' ?></td></tr>
        <?php endif; ?>
    </table>

    <?php if (!empty($order['cancellation_reason'])): ?>
    <div style="margin-top:6px;font-size:10px;color:#dc3545;border-top:1px dashed #dc3545;padding-top:4px;">
        Cancelled: <?= e($order['cancellation_reason']) ?>
    </div>
    <?php endif; ?>

    <div style="margin-top:12px;font-size:9px;color:#999;text-align:center;border-top:1px solid #eee;padding-top:4px;">
        GM Copy — <?= date('d/m/Y H:i') ?>
    </div>

</div>

<script>window.addEventListener('load', function(){ window.print(); });</script>

<?php endif; /* /print */ ?>
