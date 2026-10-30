<?php
/**
 * GM — POS Sale Receipt (read-only, view + print)
 * Route: GET /gm/receipts/pos/{id}
 *        GET /gm/receipts/pos/{id}?print=1  → print layout
 *
 * @var array  $sale
 * @var string $userRole
 */
declare(strict_types=1);

use AAM\GeneralManager\Services\GMSalesReceiptsService as SVC;

$sale     ??= [];
$userRole ??= '';
$items     = $sale['items'] ?? [];
$isVoided  = ($sale['status'] ?? '') === 'voided';
$isPrint   = ($_GET['print'] ?? '') === '1';

$etb = fn($v): string => 'ETB ' . number_format((float)$v, 2);
$dt  = fn($v): string => $v ? date('d/m/Y H:i', strtotime($v)) : '—';
$d   = fn($v): string => $v ? date('d/m/Y', strtotime($v)) : '—';

$pmLabel = SVC::paymentMethodLabel(
    $sale['payment_method'] ?? 'cash',
    $sale['payment_provider'] ?? ''
);
$statusBadge = SVC::posStatusBadge($sale['status'] ?? '');
$statusLabel = SVC::posStatusLabel($sale['status'] ?? '');
?>

<?php if (!$isPrint): ?>
<!-- ── Screen navigation (hidden on print) ───────────── -->
<div class="d-flex align-items-center justify-content-between mb-3 no-print">
    <a href="<?= url('gm/receipts') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Receipts
    </a>
    <div class="d-flex gap-2">
        <a href="<?= url('gm/receipts/pos/' . (int)$sale['id']) ?>?print=1"
           target="_blank"
           class="btn btn-outline-primary btn-sm">
            <i class="bi bi-printer me-1"></i>Print
        </a>
    </div>
</div>

<!-- ── Header card (screen layout) ──────────────────── -->
<div class="card border-0 shadow-sm mb-4"
     style="border-left:4px solid var(--bs-<?= $statusBadge ?>)!important;">
    <div class="card-body">
        <div class="row g-3 align-items-start">
            <div class="col-md-6">
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="rounded-circle bg-success bg-opacity-10
                                d-flex align-items-center justify-content-center"
                         style="width:48px;height:48px;">
                        <i class="bi bi-receipt-cutoff fs-5 text-success"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold font-monospace">
                            <?= e($sale['receipt_number'] ?? '—') ?>
                        </h5>
                        <div class="small text-muted">
                            Sale: <span class="font-monospace"><?= e($sale['sale_number'] ?? '—') ?></span>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="badge bg-success" style="font-size:.65rem;letter-spacing:.04em;">POS SALE</span>
                    <span class="badge bg-<?= $statusBadge ?> rounded-pill">
                        <?php if ($isVoided): ?><i class="bi bi-x-circle me-1"></i><?php endif; ?>
                        <?= e($statusLabel) ?>
                    </span>
                    <span class="badge bg-secondary bg-opacity-15 text-dark rounded-pill">
                        <i class="bi bi-shop me-1"></i><?= e($sale['shop_name'] ?? '—') ?>
                    </span>
                </div>
            </div>
            <div class="col-md-6 text-md-end">
                <div class="fs-3 fw-bold <?= $isVoided ? 'text-decoration-line-through text-muted' : 'text-success' ?> mb-1">
                    <?= $etb($sale['total_amount'] ?? 0) ?>
                </div>
                <div class="text-muted small">
                    <?= (int)($sale['total_items'] ?? 0) ?> item(s)
                    &bull; <?= e($pmLabel) ?>
                </div>
                <div class="text-muted small"><?= $dt($sale['completed_at'] ?? null) ?></div>
                <?php if (!empty($sale['payment_reference'])): ?>
                <div class="text-muted font-monospace" style="font-size:.68rem;">
                    Ref: <?= e($sale['payment_reference']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">

    <!-- ── Left Column: items ──────────────────────────── -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-2">
                <span class="fw-semibold small">
                    <i class="bi bi-list-ul me-2 text-secondary"></i>Sale Items
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
                            <?php if ((float)($sale['discount_amount'] ?? 0) > 0): ?>
                            <tr>
                                <td colspan="4" class="text-end ps-3 small">Discount</td>
                                <td class="text-end pe-3 small text-danger">
                                    -<?= $etb($sale['discount_amount']) ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td colspan="4" class="text-end ps-3 fw-semibold small">Total</td>
                                <td class="text-end pe-3 fw-bold"><?= $etb($sale['total_amount'] ?? 0) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isVoided): ?>
        <div class="card border-0 shadow-sm border-danger mb-3"
             style="border-left:4px solid #dc3545!important;">
            <div class="card-body py-3">
                <div class="fw-bold text-danger mb-2">
                    <i class="bi bi-x-circle me-2"></i>Void Information
                </div>
                <div class="row g-2 small">
                    <div class="col-sm-4 text-muted">Voided by</div>
                    <div class="col-sm-8">
                        <?= e($sale['voided_by_first_name'] ?? '') . ' ' . e($sale['voided_by_last_name'] ?? '') ?>
                        <?php if (!empty($sale['voided_by_username'])): ?>
                        <span class="text-muted">(@<?= e($sale['voided_by_username']) ?>)</span>
                        <?php endif; ?>
                    </div>
                    <div class="col-sm-4 text-muted">Voided at</div>
                    <div class="col-sm-8"><?= $dt($sale['voided_at'] ?? null) ?></div>
                    <div class="col-sm-4 text-muted">Reason</div>
                    <div class="col-sm-8"><?= e($sale['void_reason'] ?? '—') ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Right Column: metadata ──────────────────────── -->
    <div class="col-lg-4">

        <!-- Seller & Shop -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-2">
                <span class="fw-semibold small">
                    <i class="bi bi-person-fill me-2 text-secondary"></i>Seller &amp; Shop
                </span>
            </div>
            <div class="card-body py-3 small">
                <div class="fw-semibold mb-1">
                    <?= e(($sale['seller_first_name'] ?? '') . ' ' . ($sale['seller_last_name'] ?? '')) ?>
                    <?php if (!empty($sale['seller_username'])): ?>
                    <span class="text-muted fw-normal">(@<?= e($sale['seller_username']) ?>)</span>
                    <?php endif; ?>
                </div>
                <div class="text-muted mb-2">
                    <i class="bi bi-shop me-1"></i><?= e($sale['shop_name'] ?? '—') ?>
                    <?php if (!empty($sale['shop_location'])): ?>
                    <div class="mt-1"><i class="bi bi-geo-alt me-1"></i><?= e($sale['shop_location']) ?></div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($sale['notes'])): ?>
                <div class="p-2 bg-light rounded text-muted mt-2">
                    <i class="bi bi-chat-left-text me-1"></i><?= e($sale['notes']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payment Details -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-2">
                <span class="fw-semibold small">
                    <i class="bi bi-credit-card me-2 text-secondary"></i>Payment
                </span>
            </div>
            <div class="card-body py-2 px-3 small">
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Method</span>
                    <span class="fw-semibold"><?= e($pmLabel) ?></span>
                </div>
                <?php if (($sale['payment_method'] ?? '') === 'cash'): ?>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Cash Received</span>
                    <span><?= $etb($sale['cash_received'] ?? 0) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Change Given</span>
                    <span class="text-success fw-semibold"><?= $etb($sale['change_given'] ?? 0) ?></span>
                </div>
                <?php else: ?>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Payment Status</span>
                    <span class="fw-semibold"><?= e(strtoupper($sale['payment_status'] ?? 'PAID')) ?></span>
                </div>
                <?php if (!empty($sale['payment_reference'])): ?>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Reference</span>
                    <span class="font-monospace" style="font-size:.68rem;"><?= e($sale['payment_reference']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($sale['verified_at'])): ?>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Verified At</span>
                    <span><?= $dt($sale['verified_at']) ?></span>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                <div class="d-flex justify-content-between py-1 border-top mt-1 pt-2">
                    <span class="text-muted fw-semibold">Total</span>
                    <span class="fw-bold"><?= $etb($sale['total_amount'] ?? 0) ?></span>
                </div>
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
                <div class="d-flex align-items-center gap-2 py-1 border-bottom">
                    <i class="bi bi-check2-circle text-success flex-shrink-0"></i>
                    <div>
                        <div class="fw-semibold lh-sm">Sale Completed</div>
                        <div class="text-muted" style="font-size:.68rem;"><?= $dt($sale['completed_at'] ?? null) ?></div>
                    </div>
                </div>
                <?php if ($isVoided && !empty($sale['voided_at'])): ?>
                <div class="d-flex align-items-center gap-2 py-1">
                    <i class="bi bi-x-circle text-danger flex-shrink-0"></i>
                    <div>
                        <div class="fw-semibold lh-sm text-danger">Voided</div>
                        <div class="text-muted" style="font-size:.68rem;"><?= $dt($sale['voided_at']) ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- GM Note -->
        <div class="alert alert-secondary d-flex gap-2 py-2 small">
            <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
            <div>
                <strong>GM — read only.</strong><br>
                Sale voiding (if needed) is processed at the shop POS terminal.
            </div>
        </div>

    </div>

</div><!-- /.row -->

<?php else: /* PRINT LAYOUT */ ?>

<!-- ── Minimal print layout ─────────────────────────── -->
<div style="max-width:320px;margin:0 auto;font-family:monospace;font-size:11px;padding:8px;">

    <div style="text-align:center;margin-bottom:8px;">
        <div style="font-size:16px;font-weight:700;"><?= e($sale['shop_name'] ?? 'Shop') ?></div>
        <?php if (!empty($sale['shop_location'])): ?>
        <div style="color:#555;"><?= e($sale['shop_location']) ?></div>
        <?php endif; ?>
        <div style="font-size:10px;color:#888;margin-top:2px;">POS Sale Receipt — GM Copy</div>
    </div>

    <?php if ($isVoided): ?>
    <div style="text-align:center;padding:4px;border:2px dashed #dc3545;margin-bottom:8px;">
        <strong style="color:#dc3545;">⚠ VOIDED</strong>
    </div>
    <?php endif; ?>

    <table style="width:100%;border-collapse:collapse;margin-bottom:6px;">
        <tr><td style="color:#666;">Receipt #</td><td style="text-align:right;font-weight:700;"><?= e($sale['receipt_number'] ?? '') ?></td></tr>
        <tr><td style="color:#666;">Sale #</td><td style="text-align:right;"><?= e($sale['sale_number'] ?? '') ?></td></tr>
        <tr><td style="color:#666;">Date</td><td style="text-align:right;"><?= $d($sale['completed_at'] ?? null) ?> <?= $sale['completed_at'] ? date('H:i', strtotime($sale['completed_at'])) : '' ?></td></tr>
        <tr><td style="color:#666;">Seller</td><td style="text-align:right;"><?= e(($sale['seller_first_name'] ?? '') . ' ' . ($sale['seller_last_name'] ?? '')) ?></td></tr>
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
        <tr><td style="color:#666;">Items</td><td style="text-align:right;"><?= (int)($sale['total_items'] ?? 0) ?></td></tr>
        <tr><td style="color:#666;">Subtotal</td><td style="text-align:right;">ETB <?= number_format((float)($sale['subtotal'] ?? 0), 2) ?></td></tr>
        <?php if ((float)($sale['discount_amount'] ?? 0) > 0): ?>
        <tr><td style="color:#666;">Discount</td><td style="text-align:right;">-ETB <?= number_format((float)$sale['discount_amount'], 2) ?></td></tr>
        <?php endif; ?>
        <tr style="font-weight:700;font-size:13px;border-top:1px solid #333;">
            <td style="padding-top:3px;">TOTAL</td>
            <td style="text-align:right;padding-top:3px;">ETB <?= number_format((float)($sale['total_amount'] ?? 0), 2) ?></td>
        </tr>
        <tr><td style="color:#666;">Payment</td><td style="text-align:right;font-weight:600;"><?= e(strtoupper($pmLabel)) ?></td></tr>
        <?php if (($sale['payment_method'] ?? '') === 'cash'): ?>
        <tr><td style="color:#666;">Cash</td><td style="text-align:right;">ETB <?= number_format((float)($sale['cash_received'] ?? 0), 2) ?></td></tr>
        <tr><td style="color:#28a745;font-weight:700;">Change</td><td style="text-align:right;color:#28a745;font-weight:700;">ETB <?= number_format((float)($sale['change_given'] ?? 0), 2) ?></td></tr>
        <?php else: ?>
        <tr><td style="color:#666;">Status</td><td style="text-align:right;font-weight:700;"><?= e(strtoupper($sale['payment_status'] ?? 'PAID')) ?></td></tr>
        <?php if (!empty($sale['payment_reference'])): ?>
        <tr><td style="color:#666;">Ref</td><td style="text-align:right;font-size:10px;"><?= e($sale['payment_reference']) ?></td></tr>
        <?php endif; ?>
        <?php endif; ?>
    </table>

    <?php if (!empty($sale['notes'])): ?>
    <div style="margin-top:6px;font-size:10px;color:#666;border-top:1px dashed #ccc;padding-top:4px;">
        Note: <?= e($sale['notes']) ?>
    </div>
    <?php endif; ?>

    <?php if ($isVoided): ?>
    <div style="margin-top:8px;font-size:10px;color:#dc3545;text-align:center;border-top:1px dashed #dc3545;padding-top:4px;">
        VOIDED — Reason: <?= e($sale['void_reason'] ?? '—') ?>
    </div>
    <?php endif; ?>

    <div style="margin-top:12px;font-size:9px;color:#999;text-align:center;border-top:1px solid #eee;padding-top:4px;">
        GM Copy — <?= date('d/m/Y H:i') ?>
    </div>

</div>

<script>window.addEventListener('load', function(){ window.print(); });</script>

<?php endif; /* /print */ ?>
