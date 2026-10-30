<?php
/**
 * GM — Customer Order Detail (read-only)
 * Route: GET /gm/orders/{id}
 *
 * @var array  $order
 * @var array  $auditTrail
 */
declare(strict_types=1);

use AAM\GeneralManager\Services\GMCustomerOrderService as GCOS;

$order      ??= [];
$auditTrail ??= [];

$n   = fn($v, int $d = 0): string => is_numeric($v) ? number_format((float)$v, $d) : '—';
$etb = fn($v): string => 'ETB ' . number_format((float)$v, 2);
$dt  = fn($v): string => $v ? date('d/m/Y H:i', strtotime($v)) : '—';

$oStatus = $order['order_status']   ?? '';
$pStatus = $order['payment_status'] ?? '';
$oBadge  = GCOS::statusBadge($oStatus);
$oLabel  = GCOS::statusLabel($oStatus);
$pBadge  = GCOS::paymentStatusBadge($pStatus);
?>

<!-- Back -->
<div class="mb-3">
    <a href="<?= url('gm/orders') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Orders
    </a>
</div>

<!-- ── Order Header ────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-4"
     style="border-left:4px solid var(--bs-<?= $oBadge ?>)!important;">
    <div class="card-body">
        <div class="row g-3 align-items-start">
            <div class="col-md-6">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="rounded-circle bg-secondary bg-opacity-10
                                d-flex align-items-center justify-content-center"
                         style="width:48px;height:48px;">
                        <i class="bi bi-bag-check-fill fs-5 text-secondary"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold font-monospace">
                            <?= e($order['order_reference'] ?? '—') ?>
                        </h5>
                        <small class="text-muted">
                            <?= $dt($order['created_at'] ?? null) ?>
                        </small>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="badge bg-<?= $oBadge ?> rounded-pill">
                        <?= e($oLabel) ?>
                    </span>
                    <span class="badge bg-<?= $pBadge ?> rounded-pill">
                        Payment: <?= ucfirst($pStatus) ?>
                    </span>
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
                    <?= (int)($order['item_count'] ?? count($order['items'] ?? [])) ?> item(s)
                </div>
                <?php if (!empty($order['payment_method_name'])): ?>
                <div class="text-muted small">
                    <i class="bi bi-credit-card me-1"></i>
                    <?= e($order['payment_method_name']) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($order['payment_reference'])): ?>
                <div class="text-muted" style="font-size:.7rem;" class="font-monospace">
                    Ref: <?= e($order['payment_reference']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">

    <!-- ── Left Column ──────────────────────────────────── -->
    <div class="col-lg-8">

        <!-- Order Items -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-2">
                <span class="fw-semibold small">
                    <i class="bi bi-list-ul me-2 text-secondary"></i>Order Items
                </span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($order['items'])): ?>
                <div class="text-center py-4 text-muted">
                    <small>No items found for this order.</small>
                </div>
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
                            <?php foreach ($order['items'] as $i => $item): ?>
                            <tr>
                                <td class="ps-3 text-muted small"><?= $i + 1 ?></td>
                                <td>
                                    <div class="small fw-semibold">
                                        <?= e($item['product_name'] ?? '—') ?>
                                    </div>
                                    <?php if (!empty($item['product_sku'])): ?>
                                    <div class="text-muted" style="font-size:.65rem;">
                                        SKU: <?= e($item['product_sku']) ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end small">
                                    <?= $etb($item['unit_price'] ?? 0) ?>
                                </td>
                                <td class="text-center small">
                                    <?= $n($item['quantity'] ?? 0, 3) ?>
                                    <?php if (!empty($item['unit_abbr'])): ?>
                                    <span class="text-muted"><?= e($item['unit_abbr']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end small fw-semibold pe-3">
                                    <?= $etb($item['subtotal'] ?? 0) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="4" class="text-end ps-3 fw-semibold small">Total</td>
                                <td class="text-end pe-3 fw-bold">
                                    <?= $etb($order['total_amount'] ?? 0) ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Status History / Audit Trail -->
        <?php if (!empty($auditTrail)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-2">
                <span class="fw-semibold small">
                    <i class="bi bi-clock-history me-2 text-secondary"></i>Status History
                </span>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($auditTrail as $entry):
                        $newStatus = $entry['new_status'] ?? '';
                        $badge     = GCOS::statusBadge($newStatus);
                        $label     = GCOS::statusLabel($newStatus);
                    ?>
                    <li class="list-group-item d-flex align-items-start gap-3 py-2 px-3
                               border-0 border-bottom">
                        <div class="rounded-circle bg-<?= $badge ?> bg-opacity-10
                                    d-flex align-items-center justify-content-center flex-shrink-0 mt-1"
                             style="width:28px;height:28px;">
                            <i class="bi bi-circle-fill text-<?= $badge ?>"
                               style="font-size:.45rem;"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="small fw-semibold">
                                <?php if (!empty($entry['old_status'])): ?>
                                <span class="text-muted">
                                    <?= e(GCOS::statusLabel($entry['old_status'])) ?>
                                </span>
                                <i class="bi bi-arrow-right mx-1 text-muted small"></i>
                                <?php endif; ?>
                                <span class="badge bg-<?= $badge ?> rounded-pill">
                                    <?= e($label) ?>
                                </span>
                            </div>
                            <?php if (!empty($entry['description'])): ?>
                            <div class="text-muted small"><?= e($entry['description']) ?></div>
                            <?php endif; ?>
                            <div class="text-muted" style="font-size:.67rem;">
                                <?= e($entry['user_name'] ?? 'System') ?>
                                <?php if (!empty($entry['username'])): ?>
                                (@<?= e($entry['username']) ?>)
                                <?php endif; ?>
                                &bull; <?= $dt($entry['action_at'] ?? null) ?>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /.col-lg-8 -->

    <!-- ── Right Column ─────────────────────────────────── -->
    <div class="col-lg-4">

        <!-- Customer Info -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-2">
                <span class="fw-semibold small">
                    <i class="bi bi-person-fill me-2 text-secondary"></i>Customer
                </span>
            </div>
            <div class="card-body py-3">
                <div class="fw-semibold mb-1"><?= e($order['customer_name'] ?? '—') ?></div>
                <?php if (!empty($order['customer_phone'])): ?>
                <div class="text-muted small mb-1">
                    <i class="bi bi-telephone me-1"></i><?= e($order['customer_phone']) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($order['customer_email'])): ?>
                <div class="text-muted small mb-1">
                    <i class="bi bi-envelope me-1"></i><?= e($order['customer_email']) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($order['notes'])): ?>
                <div class="mt-2 p-2 bg-light rounded small text-muted">
                    <i class="bi bi-chat-left-text me-1"></i><?= e($order['notes']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Shop & Pickup Info -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-2">
                <span class="fw-semibold small">
                    <i class="bi bi-shop me-2 text-warning"></i>Shop & Pickup
                </span>
            </div>
            <div class="card-body py-3 small">
                <div class="mb-1 fw-semibold"><?= e($order['shop_name'] ?? '—') ?></div>
                <?php if (!empty($order['shop_location'])): ?>
                <div class="text-muted mb-2">
                    <i class="bi bi-geo-alt me-1"></i><?= e($order['shop_location']) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($order['pickup_instructions'])): ?>
                <div class="p-2 bg-light rounded text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    <?= e($order['pickup_instructions']) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($order['processed_by_name'])): ?>
                <div class="mt-2 text-muted">
                    <i class="bi bi-person-check me-1"></i>
                    Processed by: <?= e($order['processed_by_name']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Key Timestamps -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-2">
                <span class="fw-semibold small">
                    <i class="bi bi-clock me-2 text-secondary"></i>Timeline
                </span>
            </div>
            <div class="card-body py-2 px-3 small">
                <?php
                $timeline = [
                    ['Order Created',   $order['created_at']   ?? null, 'secondary', 'bi-plus-circle'],
                    ['Payment Received',$order['paid_at']       ?? null, 'info',      'bi-credit-card'],
                    ['Ready for Pickup',$order['ready_at']      ?? null, 'success',   'bi-bag-check'],
                    ['Completed',       $order['completed_at']  ?? null, 'success',   'bi-check2-all'],
                    ['Cancelled',       $order['cancelled_at']  ?? null, 'danger',    'bi-x-circle'],
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

        <!-- GM Note: Read-only -->
        <div class="alert alert-secondary d-flex gap-2 py-2 small">
            <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
            <div>
                <strong>GM — monitoring only.</strong><br>
                Order processing (prepare, ready, complete) is handled
                by the shop salesperson at
                <a href="<?= url('customer/shop-orders?shop_id=' . (int)($order['shop_id'] ?? 0)) ?>"
                   class="alert-link">the shop order queue</a>.
            </div>
        </div>

    </div><!-- /.col-lg-4 -->

</div><!-- /.row -->
