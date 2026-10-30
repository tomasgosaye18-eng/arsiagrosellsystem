<?php
/**
 * Shift Management — Phase 12
 * @var array|null $activeShift  @var array  $shop
 * @var array  $shiftHistory     @var string $csrfToken
 * @var string $userRole
 */
declare(strict_types=1);
$activeShift  ??= null;
$shop         ??= [];
$shiftHistory ??= [];

use AAM\POS\Models\ShiftModel;
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h5 class="mb-0 fw-bold">
        <i class="bi bi-person-badge me-2 text-primary"></i>Shift Management
        <?php if (!empty($shop['name'])): ?>
        <small class="text-muted fw-normal fs-6">— <?= e($shop['name']) ?></small>
        <?php endif; ?>
    </h5>
    <a href="<?= url('pos') ?>" class="btn btn-success btn-sm">
        <i class="bi bi-cart3 me-1"></i>POS Terminal
    </a>
</div>

<?php if ($activeShift): ?>
<!-- Active Shift Card -->
<div class="card border-success border-2 shadow-sm mb-3">
    <div class="card-header bg-success bg-opacity-10 d-flex justify-content-between align-items-center">
        <span class="fw-semibold text-success"><i class="bi bi-circle-fill me-1" style="font-size:.6rem;"></i>Active Shift — <?= e($activeShift['shift_number']) ?></span>
        <small class="text-muted">Opened <?= date('d/m/Y H:i', strtotime($activeShift['opened_at'])) ?></small>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-4">
                <div class="text-muted small mb-1">Transactions</div>
                <div class="fw-bold fs-4"><?= (int)$activeShift['total_transactions'] ?></div>
            </div>
            <div class="col-4">
                <div class="text-muted small mb-1">Total Sales</div>
                <div class="fw-bold fs-4 text-success">ETB <?= number_format((float)$activeShift['total_sales_amount'], 2) ?></div>
            </div>
            <div class="col-4">
                <div class="text-muted small mb-1">Cash Collected</div>
                <div class="fw-bold fs-4">ETB <?= number_format((float)$activeShift['cash_collected'], 2) ?></div>
            </div>
        </div>
        <form action="<?= url('pos/shift/close') ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <div class="mb-2">
                <label class="form-label small fw-semibold">Closing Notes (optional)</label>
                <textarea name="closing_notes" class="form-control form-control-sm" rows="2"
                          placeholder="Summarize your shift…"></textarea>
            </div>
            <button type="submit" class="btn btn-danger btn-sm"
                    onclick="return confirm('Close current shift?')">
                <i class="bi bi-stop-circle me-1"></i>Close Shift
            </button>
        </form>
    </div>
</div>

<?php else: ?>
<!-- Open Shift Card -->
<div class="card border-warning border-2 shadow-sm mb-3">
    <div class="card-header bg-warning bg-opacity-10">
        <span class="fw-semibold text-warning"><i class="bi bi-pause-circle me-1"></i>No Active Shift</span>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">Open a shift to start processing sales.</p>
        <form action="<?= url('pos/shift/open') ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <div class="mb-2">
                <label class="form-label small fw-semibold">Opening Notes (optional)</label>
                <textarea name="opening_notes" class="form-control form-control-sm" rows="2"
                          placeholder="E.g. Opening float: ETB 0"></textarea>
            </div>
            <button type="submit" class="btn btn-success btn-sm">
                <i class="bi bi-play-circle me-1"></i>Open Shift
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Shift History -->
<div class="card border-0 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold small">Recent Shifts</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($shiftHistory)): ?>
        <div class="text-center py-4 text-muted small">No shift history yet.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 small">Shift #</th>
                        <th class="small">Opened</th>
                        <th class="small">Closed</th>
                        <th class="text-end small">Transactions</th>
                        <th class="text-end small">Total Sales</th>
                        <th class="text-center small">Status</th>
                        <th class="text-center small pe-3">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shiftHistory as $s):
                        $badge = ShiftModel::statusBadge($s['status']);
                    ?>
                    <tr>
                        <td class="ps-3 small fw-semibold"><?= e($s['shift_number']) ?></td>
                        <td class="small"><?= date('d/m H:i', strtotime($s['opened_at'])) ?></td>
                        <td class="small text-muted"><?= $s['closed_at'] ? date('d/m H:i', strtotime($s['closed_at'])) : '—' ?></td>
                        <td class="text-end small"><?= (int)$s['total_transactions'] ?></td>
                        <td class="text-end small fw-semibold">ETB <?= number_format((float)$s['total_sales_amount'], 2) ?></td>
                        <td class="text-center"><span class="badge bg-<?= $badge ?>"><?= ucfirst($s['status']) ?></span></td>
                        <td class="text-center pe-3">
                            <a href="<?= url('pos/shift/' . $s['id']) ?>"
                               class="btn btn-outline-secondary btn-sm py-0 px-2">
                                <i class="bi bi-eye small"></i>
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
