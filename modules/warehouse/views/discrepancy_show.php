<?php
/**
 * Discrepancy Detail View — Phase 10
 * @var array  $discrepancy
 * @var array  $warehouse
 * @var string $userRole
 * @var bool   $canResolve
 */
declare(strict_types=1);
$discrepancy ??= [];
$canResolve  ??= false;
$badge = \AAM\Warehouse\Models\DiscrepancyModel::statusBadge($discrepancy['status'] ?? '');
$label = \AAM\Warehouse\Models\DiscrepancyModel::statusLabel($discrepancy['status'] ?? '');
$type  = \AAM\Warehouse\Models\DiscrepancyModel::typeLabel($discrepancy['discrepancy_type'] ?? '');

// Transfer discrepancies (WH→Shop) are controlled exclusively by the GM approval
// workflow. WM cannot manually resolve them — suppress the Update Status button.
$isTransferDiscrepancy = ($discrepancy['reference_type'] ?? '') === 'transfer';
$canManuallyResolve    = $canResolve && !$isTransferDiscrepancy
                         && in_array($discrepancy['status'] ?? '', ['open','under_review'], true);
?>

<div class="d-flex align-items-center gap-3 mb-4">
  <a href="<?= url('warehouse/discrepancies') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
  <div class="flex-grow-1">
    <h5 class="mb-0 fw-bold">
      <?= e($discrepancy['discrepancy_number'] ?? '') ?>
      <span class="badge bg-<?= $badge ?> ms-2"><?= $label ?></span>
    </h5>
    <small class="text-muted">
      <?= e($type) ?>
      &middot; Reported: <?= $discrepancy['reported_at'] ? date('d M Y H:i', strtotime($discrepancy['reported_at'])) : '—' ?>
      &middot; <?= e($discrepancy['warehouse_name'] ?? $warehouse['name'] ?? '—') ?>
    </small>
  </div>
  <?php if ($canManuallyResolve): ?>
  <button class="btn btn-success btn-sm"
          data-bs-toggle="modal" data-bs-target="#resolveModal">
    <i class="bi bi-check2-circle me-1"></i>Update Status
  </button>
  <?php elseif ($isTransferDiscrepancy): ?>
  <a href="<?= url('transfers/' . ($discrepancy['reference_id'] ?? '')) ?>"
     class="btn btn-outline-info btn-sm">
    <i class="bi bi-arrow-right-circle me-1"></i>View Transfer
  </a>
  <?php endif; ?>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="card border-0 shadow-sm text-center h-100">
      <div class="card-body py-3">
        <div class="fw-bold fs-4 text-primary"><?= number_format((float)($discrepancy['expected_quantity'] ?? 0), 3) ?></div>
        <div class="text-muted small">Expected</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm text-center h-100">
      <div class="card-body py-3">
        <div class="fw-bold fs-4 text-<?= (float)($discrepancy['variance_quantity'] ?? 0) > 0 ? 'danger' : 'success' ?>">
          <?= number_format((float)($discrepancy['actual_quantity'] ?? 0), 3) ?>
        </div>
        <div class="text-muted small">Actual Received</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm text-center h-100">
      <div class="card-body py-3">
        <div class="fw-bold fs-4 text-danger">
          <?= number_format(abs((float)($discrepancy['variance_quantity'] ?? 0)), 3) ?>
        </div>
        <div class="text-muted small">Variance (<?= number_format((float)($discrepancy['variance_percent'] ?? 0), 1) ?>%)</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm text-center h-100">
      <div class="card-body py-3">
        <span class="badge bg-<?= $badge ?> fs-6"><?= $label ?></span>
        <div class="text-muted small mt-1">Current Status</div>
      </div>
    </div>
  </div>
</div>

<!-- Detail Card -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header fw-semibold small">
    <i class="bi bi-clipboard-data me-2 text-danger"></i>Discrepancy Details
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <dl class="row mb-0">
          <dt class="col-sm-5 small text-muted">Discrepancy #</dt>
          <dd class="col-sm-7 small fw-semibold"><?= e($discrepancy['discrepancy_number'] ?? '—') ?></dd>

          <dt class="col-sm-5 small text-muted">Reference</dt>
          <dd class="col-sm-7 small">
            <?= ucfirst($discrepancy['reference_type'] ?? '') ?> #<?= $discrepancy['reference_id'] ?? '—' ?>
          </dd>

          <dt class="col-sm-5 small text-muted">Product</dt>
          <dd class="col-sm-7 small fw-semibold"><?= e($discrepancy['product_name'] ?? '—') ?></dd>

          <dt class="col-sm-5 small text-muted">SKU</dt>
          <dd class="col-sm-7 small text-muted"><?= e($discrepancy['product_sku'] ?? '—') ?></dd>

          <dt class="col-sm-5 small text-muted">Batch #</dt>
          <dd class="col-sm-7 small"><?= e($discrepancy['batch_number'] ?? '—') ?></dd>

          <dt class="col-sm-5 small text-muted">Type</dt>
          <dd class="col-sm-7 small"><span class="badge bg-secondary-subtle text-secondary"><?= e($type) ?></span></dd>
        </dl>
      </div>
      <div class="col-md-6">
        <dl class="row mb-0">
          <dt class="col-sm-5 small text-muted">Farm</dt>
          <dd class="col-sm-7 small"><?= e($discrepancy['farm_name'] ?? '—') ?></dd>

          <dt class="col-sm-5 small text-muted">Shop</dt>
          <dd class="col-sm-7 small"><?= e($discrepancy['shop_name'] ?? '—') ?></dd>

          <dt class="col-sm-5 small text-muted">Reported By</dt>
          <dd class="col-sm-7 small"><?= e($discrepancy['reported_by_name'] ?? '—') ?></dd>

          <dt class="col-sm-5 small text-muted">Reported At</dt>
          <dd class="col-sm-7 small">
            <?= $discrepancy['reported_at'] ? date('d M Y H:i', strtotime($discrepancy['reported_at'])) : '—' ?>
          </dd>

          <dt class="col-sm-5 small text-muted">Resolved By</dt>
          <dd class="col-sm-7 small"><?= e($discrepancy['resolved_by_name'] ?? '—') ?></dd>

          <dt class="col-sm-5 small text-muted">Resolved At</dt>
          <dd class="col-sm-7 small">
            <?= $discrepancy['resolved_at'] ? date('d M Y H:i', strtotime($discrepancy['resolved_at'])) : '—' ?>
          </dd>
        </dl>
      </div>
    </div>

    <?php if (!empty($discrepancy['reason'])): ?>
    <hr class="my-3">
    <div>
      <strong class="small">Reason / Description:</strong>
      <p class="mb-0 small mt-1 text-muted"><?= e($discrepancy['reason']) ?></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($discrepancy['resolution_notes'])): ?>
    <hr class="my-3">
    <div class="alert alert-success py-2 mb-0">
      <strong class="small"><i class="bi bi-check2-circle me-1"></i>Resolution Notes:</strong>
      <p class="mb-0 small mt-1"><?= e($discrepancy['resolution_notes']) ?></p>
    </div>
    <?php endif; ?>

    <?php if ($isTransferDiscrepancy): ?>
    <hr class="my-3">
    <div class="alert alert-info py-2 mb-0 small">
      <i class="bi bi-info-circle me-1"></i>
      <strong>GM-Workflow Discrepancy.</strong>
      This discrepancy is part of the Warehouse → Shop transfer receiving workflow
      and is reviewed by the General Manager. Status is managed automatically —
      manual update is not allowed.
      <?php if (!empty($discrepancy['gm_notes'])): ?>
      <div class="mt-1"><strong>GM Notes:</strong> <?= e($discrepancy['gm_notes']) ?></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($canManuallyResolve): ?>
<!-- Resolve Modal -->
<div class="modal fade" id="resolveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST"
          action="<?= url('warehouse/discrepancies/' . ($discrepancy['id'] ?? '') . '/status') ?>">
      <?php csrf_input(); ?>
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title">Update Discrepancy Status</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-semibold">New Status *</label>
            <select name="status" class="form-select" required>
              <option value="under_review" <?= ($discrepancy['status'] ?? '') === 'open' ? '' : 'selected' ?>>
                Under Review
              </option>
              <option value="resolved">Resolved</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
          <div class="mb-0">
            <label class="form-label small fw-semibold">Resolution Notes</label>
            <textarea name="notes" class="form-control" rows="3"
                      placeholder="Describe the investigation findings or resolution…"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success btn-sm">Update Status</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
