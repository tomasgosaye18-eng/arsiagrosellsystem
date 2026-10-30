<?php
/**
 * Shift Detail / Summary — Phase 12
 * @var array  $shift     @var string $userRole
 */
declare(strict_types=1);

use AAM\POS\Models\ShiftModel;
use AAM\POS\Models\SalesModel;

$shift ??= [];
$sales  = $shift['sales'] ?? [];
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h5 class="mb-0 fw-bold">
        <i class="bi bi-person-badge me-2 text-primary"></i>
        Shift Summary — <?= e($shift['shift_number'] ?? '') ?>
    </h5>
    <div class="d-flex gap-2">
        <a href="<?= url('pos/shift') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Shifts
        </a>
        <a href="<?= url('pos') ?>" class="btn btn-success btn-sm">
            <i class="bi bi-cart3 me-1"></i>POS
        </a>
    </div>
</div>

<!-- Shift info + KPIs -->
<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header"><span class="fw-semibold small">Shift Details</span></div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-sm-5 text-muted">Seller</dt>
                    <dd class="col-sm-7 fw-semibold"><?= e(($shift['seller_first_name'] ?? '') . ' ' . ($shift['seller_last_name'] ?? '')) ?></dd>
                    <dt class="col-sm-5 text-muted">Shop</dt>
                    <dd class="col-sm-7"><?= e($shift['shop_name'] ?? '—') ?></dd>
                    <dt class="col-sm-5 text-muted">Opened</dt>
                    <dd class="col-sm-7"><?= date('d/m/Y H:i', strtotime($shift['opened_at'])) ?></dd>
                    <dt class="col-sm-5 text-muted">Closed</dt>
                    <dd class="col-sm-7">
                        <?= $shift['closed_at']
                            ? date('d/m/Y H:i', strtotime($shift['closed_at']))
                            : '<span class="text-success fw-bold">Still Open</span>' ?>
                    </dd>
                    <dt class="col-sm-5 text-muted">Status</dt>
                    <dd class="col-sm-7">
                        <span class="badge bg-<?= ShiftModel::statusBadge($shift['status'] ?? 'open') ?>">
                            <?= ucfirst($shift['status'] ?? 'open') ?>
                        </span>
                    </dd>
                    <?php if (!empty($shift['opening_notes'])): ?>
                    <dt class="col-sm-5 text-muted">Opening Notes</dt>
                    <dd class="col-sm-7 text-muted"><?= e($shift['opening_notes']) ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($shift['closing_notes'])): ?>
                    <dt class="col-sm-5 text-muted">Closing Notes</dt>
                    <dd class="col-sm-7 text-muted"><?= e($shift['closing_notes']) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <?php
        $kpis = [
            ['Transactions',   (int)($shift['total_transactions'] ?? 0),   'bi-receipt',    'primary'],
            ['Total Sales',    'ETB ' . number_format((float)($shift['total_sales_amount'] ?? 0), 2), 'bi-cash-stack', 'success'],
            ['Cash Collected', 'ETB ' . number_format((float)($shift['cash_collected'] ?? 0), 2),     'bi-wallet',     'info'],
        ];
        ?>
        <div class="row g-3">
            <?php foreach ($kpis as [$label, $val, $icon, $color]): ?>
            <div class="col-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="text-muted"><?= $label ?></small>
                            <i class="bi <?= $icon ?> text-<?= $color ?>"></i>
                        </div>
                        <div class="fw-bold fs-5"><?= $val ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Sales in this shift -->
<div class="card border-0 shadow-sm">
    <div class="card-header">
        <span class="fw-semibold small">Sales in This Shift (<?= count($sales) ?>)</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($sales)): ?>
        <div class="text-center py-4 text-muted small">No sales recorded in this shift.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 small">Receipt #</th>
                        <th class="small">Sale #</th>
                        <th class="small">Time</th>
                        <th class="text-end small">Total (ETB)</th>
                        <th class="text-center small">Status</th>
                        <th class="text-center small pe-3">Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $s):
                        $badge = SalesModel::statusBadge($s['status']);
                        $label = SalesModel::statusLabel($s['status']);
                    ?>
                    <tr>
                        <td class="ps-3 small fw-semibold"><?= e($s['receipt_number']) ?></td>
                        <td class="small text-muted"><?= e($s['sale_number']) ?></td>
                        <td class="small"><?= date('H:i', strtotime($s['completed_at'])) ?></td>
                        <td class="text-end small fw-semibold"><?= number_format((float)$s['total_amount'], 2) ?></td>
                        <td class="text-center">
                            <span class="badge bg-<?= $badge ?>"><?= $label ?></span>
                        </td>
                        <td class="text-center pe-3">
                            <a href="<?= url('pos/receipt/' . $s['id']) ?>"
                               class="btn btn-outline-primary btn-sm py-0 px-2" target="_blank">
                                <i class="bi bi-receipt small"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
