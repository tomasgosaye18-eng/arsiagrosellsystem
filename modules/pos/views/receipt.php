<?php
/**
 * Receipt View — Phase 12
 * Uses print layout (thermal 80mm style)
 * @var array  $sale     @var string $userRole
 * @var string $appName  @var string $csrfToken
 */
declare(strict_types=1);
$sale    ??= [];
$items   = $sale['items'] ?? [];
$isVoided = ($sale['status'] ?? '') === 'voided';
$canVoid  = in_array($userRole ?? '', [ROLE_ADMIN, ROLE_GENERAL_MANAGER], true)
            && !$isVoided;
?>

<!-- Shop name (org header already provided by print.php layout) -->
<div style="text-align:center;margin-bottom:8px;">
    <strong style="font-size:13px;"><?= e($sale['shop_name'] ?? 'Shop') ?></strong><br>
    <small style="color:#666;"><?= e($sale['shop_location'] ?? '') ?></small>
</div>

<?php if ($isVoided): ?>
<div style="text-align:center;margin:8px 0;padding:4px;border:2px dashed #dc3545;">
    <strong style="color:#dc3545;font-size:13px;">⚠ VOIDED</strong>
</div>
<?php endif; ?>

<table style="width:100%;font-size:11px;border-collapse:collapse;margin-bottom:6px;">
    <tr>
        <td style="color:#666;width:45%;">Receipt #</td>
        <td style="text-align:right;font-weight:700;"><?= e($sale['receipt_number'] ?? '') ?></td>
    </tr>
    <tr>
        <td style="color:#666;">Sale #</td>
        <td style="text-align:right;"><?= e($sale['sale_number'] ?? '') ?></td>
    </tr>
    <tr>
        <td style="color:#666;">Date</td>
        <td style="text-align:right;"><?= date('d/m/Y H:i', strtotime($sale['completed_at'] ?? 'now')) ?></td>
    </tr>
    <tr>
        <td style="color:#666;">Seller</td>
        <td style="text-align:right;"><?= e(($sale['seller_first_name'] ?? '') . ' ' . ($sale['seller_last_name'] ?? '')) ?></td>
    </tr>
</table>

<div style="border-top:1px dashed #ccc;border-bottom:1px dashed #ccc;padding:4px 0;margin:4px 0;">
    <table style="width:100%;font-size:11px;border-collapse:collapse;">
        <thead>
            <tr style="font-weight:700;">
                <th style="text-align:left;padding-bottom:3px;">Item</th>
                <th style="text-align:right;padding-bottom:3px;">Qty</th>
                <th style="text-align:right;padding-bottom:3px;">Price</th>
                <th style="text-align:right;padding-bottom:3px;">Sub</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td style="padding:1px 0;"><?= e($item['product_name']) ?></td>
                <td style="text-align:right;padding:1px 0;"><?= number_format((float)$item['quantity'], 2) ?> <?= e($item['unit_abbr'] ?? '') ?></td>
                <td style="text-align:right;padding:1px 0;"><?= number_format((float)$item['unit_price'], 2) ?>/<?= e($item['unit_abbr'] ?? 'unit') ?></td>
                <td style="text-align:right;padding:1px 0;font-weight:600;"><?= number_format((float)$item['subtotal'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<table style="width:100%;font-size:12px;border-collapse:collapse;margin-top:4px;">
    <tr>
        <td style="color:#666;">Items</td>
        <td style="text-align:right;"><?= (int)($sale['total_items'] ?? 0) ?></td>
    </tr>
    <tr>
        <td style="color:#666;">Subtotal</td>
        <td style="text-align:right;">ETB <?= number_format((float)($sale['subtotal'] ?? 0), 2) ?></td>
    </tr>
    <?php if ((float)($sale['discount_amount'] ?? 0) > 0): ?>
    <tr>
        <td style="color:#666;">Discount</td>
        <td style="text-align:right;">- ETB <?= number_format((float)$sale['discount_amount'], 2) ?></td>
    </tr>
    <?php endif; ?>
    <tr style="font-weight:700;font-size:14px;border-top:1px solid #333;">
        <td style="padding-top:4px;">TOTAL</td>
        <td style="text-align:right;padding-top:4px;">ETB <?= number_format((float)($sale['total_amount'] ?? 0), 2) ?></td>
    </tr>
    <tr>
        <td style="color:#666;font-size:11px;">Payment Method</td>
        <td style="text-align:right;font-size:11px;font-weight:600;text-transform:uppercase;">
            <?php
            $pm = $sale['payment_method'] ?? 'cash';
            $prov = $sale['payment_provider'] ?? '';
            // Build display label using provider name when available
            if ($pm === 'mobile_money' && $prov !== '') {
                $provLabels = [
                    'cbe_birr'     => 'CBE Birr',
                    'telebirr'     => 'Telebirr',
                    'other_mobile' => 'Mobile Money',
                ];
                echo e($provLabels[$prov] ?? strtoupper($prov));
            } else {
                $pmLabels = [
                    'cash'         => 'Cash',
                    'mobile_money' => 'Mobile Money',
                    'credit'       => 'Credit',
                    'other'        => 'Other',
                ];
                echo e($pmLabels[$pm] ?? strtoupper($pm));
            }
            ?>
        </td>
    </tr>
    <?php if (!empty($sale['payment_reference'])): ?>
    <tr>
        <td style="color:#666;font-size:11px;">Payment Ref</td>
        <td style="text-align:right;font-size:10px;font-family:monospace;"><?= e($sale['payment_reference']) ?></td>
    </tr>
    <?php endif; ?>
    <?php if (($sale['payment_method'] ?? 'cash') === 'mobile_money'): ?>
    <tr>
        <td style="color:#666;font-size:11px;">Payment Status</td>
        <td style="text-align:right;font-size:11px;font-weight:700;color:#28a745;">
            <?= e(strtoupper($sale['payment_status'] ?? 'PAID')) ?>
        </td>
    </tr>
    <?php else: ?>
    <tr>
        <td style="color:#666;font-size:11px;">Cash Received</td>
        <td style="text-align:right;font-size:11px;">ETB <?= number_format((float)($sale['cash_received'] ?? 0), 2) ?></td>
    </tr>
    <tr style="font-weight:700;">
        <td style="color:#28a745;">Change</td>
        <td style="text-align:right;color:#28a745;">ETB <?= number_format((float)($sale['change_given'] ?? 0), 2) ?></td>
    </tr>
    <?php endif; ?>
</table>

<?php if (!empty($sale['notes'])): ?>
<div style="margin-top:6px;font-size:10px;color:#666;border-top:1px dashed #ccc;padding-top:4px;">
    Note: <?= e($sale['notes']) ?>
</div>
<?php endif; ?>

<?php if ($isVoided): ?>
<div style="margin-top:8px;font-size:10px;color:#dc3545;text-align:center;">
    VOIDED by: <?= e($sale['voided_by_username'] ?? '—') ?> | <?= $sale['voided_at'] ? date('d/m/Y H:i', strtotime($sale['voided_at'])) : '—' ?><br>
    Reason: <?= e($sale['void_reason'] ?? '—') ?>
</div>
<?php endif; ?>

<!-- Action buttons (screen only, hidden on print) -->
<div class="no-print" style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;justify-content:center;">
    <button onclick="window.print()" class="btn btn-primary btn-sm">
        <i class="bi bi-printer me-1"></i>Print
    </button>
    <a href="<?= url('pos') ?>" class="btn btn-success btn-sm">
        <i class="bi bi-cart3 me-1"></i>New Sale
    </a>
    <a href="<?= url('pos/history') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-clock-history me-1"></i>History
    </a>

    <?php if ($canVoid): ?>
    <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#voidModal">
        <i class="bi bi-x-circle me-1"></i>Void Sale
    </button>
    <?php endif; ?>
</div>

<!-- Void Modal -->
<?php if ($canVoid): ?>
<div class="modal fade no-print" id="voidModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:360px;">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h6 class="modal-title text-danger fw-bold"><i class="bi bi-exclamation-circle me-1"></i>Void Sale</h6>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= url('pos/void/' . (int)$sale['id']) ?>" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <p class="small text-muted">This will reverse the stock deduction. The transaction record is preserved.</p>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Reason for voiding <span class="text-danger">*</span></label>
                        <textarea name="void_reason" class="form-control form-control-sm" rows="3" required
                                  placeholder="Enter reason…"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="submit" class="btn btn-danger btn-sm w-100">
                        <i class="bi bi-x-circle me-1"></i>Confirm Void
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
