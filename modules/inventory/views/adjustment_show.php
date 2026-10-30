<?php
/**
 * Inventory — Adjustment Detail — Phase 11
 * @var array  $adj         @var bool   $canApprove  @var string $userRole
 */
declare(strict_types=1);
$adj ??= [];
$badge = \AAM\Inventory\Models\StockAdjustmentModel::statusBadge($adj['status'] ?? '');
$label = \AAM\Inventory\Models\StockAdjustmentModel::statusLabel($adj['status'] ?? '');
$variance = (float)($adj['variance'] ?? 0);
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold">
    <i class="bi bi-sliders me-2 text-warning"></i>
    Adjustment — <?= e($adj['adjustment_number'] ?? '') ?>
    <span class="badge bg-<?= $badge ?> ms-2"><?= $label ?></span>
  </h5>
  <a href="<?= url('inventory/adjustments') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
</div>

<?php if (($adj['status']??'')==='pending'): ?>
<div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-3">
  <i class="bi bi-hourglass-split fs-5"></i>
  <div class="small">
    This adjustment is <strong>pending General Manager approval</strong>.
    Inventory will not change until the GM reviews and approves it.
  </div>
</div>
<?php endif; ?>
<?php if (($adj['status']??'')==='rejected'): ?>
<div class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-3">
  <i class="bi bi-x-circle-fill fs-5"></i>
  <div class="small">This adjustment was <strong>rejected</strong>. Inventory was not changed.</div>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header"><span class="fw-semibold small">Adjustment Details</span></div>
      <div class="card-body">
        <dl class="row mb-0 small">
          <dt class="col-sm-5 text-muted">Number</dt>
          <dd class="col-sm-7 fw-semibold"><?= e($adj['adjustment_number'] ?? '') ?></dd>

          <dt class="col-sm-5 text-muted">Product</dt>
          <dd class="col-sm-7">
            <?= e($adj['product_name'] ?? '—') ?>
            <?php if ($adj['product_sku']): ?><small class="text-muted ms-1">(<?= e($adj['product_sku']) ?>)</small><?php endif; ?>
          </dd>

          <dt class="col-sm-5 text-muted">Location</dt>
          <dd class="col-sm-7"><?= ucfirst($adj['location_type'] ?? '') ?> #<?= (int)($adj['location_id'] ?? 0) ?></dd>

          <dt class="col-sm-5 text-muted">Batch</dt>
          <dd class="col-sm-7"><?= e($adj['batch_number'] ?? '—') ?></dd>

          <dt class="col-sm-5 text-muted">System Quantity</dt>
          <dd class="col-sm-7"><?= number_format((float)($adj['system_quantity']??0),3) ?> <?= e($adj['unit_abbr']??'') ?></dd>

          <dt class="col-sm-5 text-muted">Physical Quantity</dt>
          <dd class="col-sm-7 fw-bold"><?= number_format((float)($adj['physical_quantity']??0),3) ?> <?= e($adj['unit_abbr']??'') ?></dd>

          <dt class="col-sm-5 text-muted">Variance</dt>
          <dd class="col-sm-7 fw-bold <?= $variance < 0 ? 'text-danger' : ($variance > 0 ? 'text-success' : 'text-muted') ?>">
            <?= $variance >= 0 ? '+' : '' ?><?= number_format($variance, 3) ?>
          </dd>

          <dt class="col-sm-5 text-muted">Reason</dt>
          <dd class="col-sm-7"><?= e(\AAM\Inventory\Models\StockAdjustmentModel::REASONS[$adj['reason']??''] ?? $adj['reason'] ?? '—') ?></dd>

          <dt class="col-sm-5 text-muted">Notes</dt>
          <dd class="col-sm-7 text-muted"><?= e($adj['notes'] ?? '—') ?></dd>
        </dl>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header"><span class="fw-semibold small">Workflow</span></div>
      <div class="card-body">
        <dl class="row mb-0 small">
          <dt class="col-sm-5 text-muted">Status</dt>
          <dd class="col-sm-7"><span class="badge bg-<?= $badge ?>"><?= $label ?></span></dd>

          <dt class="col-sm-5 text-muted">Requested By</dt>
          <dd class="col-sm-7"><?= e($adj['requested_by_name'] ?? '—') ?></dd>

          <dt class="col-sm-5 text-muted">Requested At</dt>
          <dd class="col-sm-7 text-muted"><?= date('d/m/Y H:i', strtotime($adj['created_at'] ?? 'now')) ?></dd>

          <dt class="col-sm-5 text-muted">Approved By</dt>
          <dd class="col-sm-7"><?= e($adj['approved_by_name'] ?? '—') ?></dd>

          <dt class="col-sm-5 text-muted">Applied At</dt>
          <dd class="col-sm-7 text-muted"><?= $adj['applied_at'] ? date('d/m/Y H:i',strtotime($adj['applied_at'])) : '—' ?></dd>
        </dl>

        <?php if ($canApprove && ($adj['status']??'')==='pending'): ?>
        <hr>
        <div class="alert alert-info py-2 small mb-2">
          <i class="bi bi-info-circle me-1"></i>
          <strong>GM Approval Required:</strong> Only the General Manager can approve or reject this adjustment.
        </div>
        <form method="POST" action="<?= url('inventory/adjustments/'.$adj['id'].'/approve') ?>" class="mb-2">
          <?= csrf_field() ?>
          <div class="mb-2">
            <input type="text" name="notes" class="form-control form-control-sm"
                   placeholder="Approval notes (optional)">
          </div>
          <button type="submit" class="btn btn-success btn-sm fw-semibold"
                  onclick="return confirm('Apply this adjustment to inventory?')">
            <i class="bi bi-check-circle me-1"></i>Approve &amp; Apply
          </button>
        </form>
        <form method="POST" action="<?= url('inventory/adjustments/'.$adj['id'].'/reject') ?>">
          <?= csrf_field() ?>
          <div class="mb-2">
            <input type="text" name="notes" class="form-control form-control-sm"
                   placeholder="Rejection reason">
          </div>
          <button type="submit" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-x-circle me-1"></i>Reject
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
