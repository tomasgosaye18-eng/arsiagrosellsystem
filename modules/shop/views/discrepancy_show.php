<?php
/**
 * Shop — Discrepancy Detail
 * Shows the discrepancy record with full GM decision status.
 * For transfer discrepancies: shows pending/approved/rejected GM state clearly.
 *
 * @var array  $dis          @var bool   $canResolve  @var string $userRole
 */
declare(strict_types=1);

use AAM\Warehouse\Models\DiscrepancyModel;
use AAM\Warehouse\Models\TransferModel;

$dis        ??= [];
$canResolve ??= false;

$badge = DiscrepancyModel::statusBadge($dis['status'] ?? '');
$label = DiscrepancyModel::statusLabel($dis['status'] ?? '');
$type  = DiscrepancyModel::typeLabel($dis['discrepancy_type'] ?? '');
$var   = (float)($dis['variance_quantity'] ?? 0);

// Determine if this is a transfer discrepancy with a GM decision pending/made
$isTransferDiscrepancy = ($dis['reference_type'] ?? '') === 'transfer';
$gmAction   = $dis['gm_action']          ?? '';
$gmNotes    = $dis['gm_notes']           ?? '';
$gmName     = $dis['gm_reviewed_by_name'] ?? '';
$gmAt       = $dis['gm_reviewed_at']     ?? '';

// Look up the transfer status for context
$transferStatus = '';
if ($isTransferDiscrepancy && !empty($dis['reference_id'])) {
    $db = \AAM\Core\Database::getInstance();
    $t  = $db->fetchOne(
        "SELECT status, discrepancy_gm_notes FROM warehouse_transfers WHERE id = :id",
        ['id' => (int)$dis['reference_id']]
    );
    $transferStatus = $t['status'] ?? '';
    if (empty($gmNotes) && !empty($t['discrepancy_gm_notes'])) {
        $gmNotes = $t['discrepancy_gm_notes'];
    }
}
?>

<div class="d-flex align-items-center gap-3 mb-4">
  <a href="<?= url('shop/discrepancies') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
  <div class="flex-grow-1">
    <h5 class="mb-0 fw-bold">
      <?= e($dis['discrepancy_number'] ?? '') ?>
      <span class="badge bg-<?= $badge ?> ms-2"><?= $label ?></span>
    </h5>
    <small class="text-muted">
      Transfer: <?= e($dis['transfer_number'] ?? '—') ?>
      &middot; Reported: <?= date('d/m/Y H:i', strtotime($dis['reported_at'] ?? 'now')) ?>
    </small>
  </div>
</div>

<!-- ── GM Decision Banner (transfer discrepancies) ─────────────── -->
<?php if ($isTransferDiscrepancy): ?>
<?php if ($transferStatus === TransferModel::STATUS_DISCREPANCY_PENDING || ($isTransferDiscrepancy && !$gmAction)): ?>
<div class="alert alert-warning d-flex gap-2 align-items-start mb-3">
  <i class="bi bi-hourglass-split fs-5 flex-shrink-0 mt-1"></i>
  <div>
    <div class="fw-bold">Pending General Manager Approval</div>
    <div class="small mt-1">
      This discrepancy has been submitted and is awaiting the General Manager's review.
      <strong>Shop inventory will NOT be updated until the GM approves.</strong>
    </div>
  </div>
</div>

<?php elseif ($gmAction === 'approved' || $transferStatus === TransferModel::STATUS_RECEIVED_WITH_DISCREPANCY): ?>
<div class="alert alert-success d-flex gap-2 align-items-start mb-3">
  <i class="bi bi-check-circle-fill fs-5 flex-shrink-0 mt-1"></i>
  <div>
    <div class="fw-bold">Approved by General Manager</div>
    <div class="small mt-1">
      Shop inventory has been updated with the actual received quantity
      (<strong><?= number_format((float)($dis['actual_quantity'] ?? 0), 3) ?></strong>).
      The missing <?= number_format(abs($var), 3) ?> has been recorded as a discrepancy loss.
    </div>
    <?php if ($gmName): ?>
    <div class="small text-muted mt-1">
      Approved by: <strong><?= e($gmName) ?></strong>
      <?= $gmAt ? 'on ' . date('d/m/Y H:i', strtotime($gmAt)) : '' ?>
    </div>
    <?php endif; ?>
    <?php if ($gmNotes): ?>
    <div class="small mt-1">GM Notes: <?= e($gmNotes) ?></div>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($gmAction === 'rejected' || $transferStatus === TransferModel::STATUS_GM_REJECTED): ?>
<div class="alert alert-danger d-flex gap-2 align-items-start mb-3">
  <i class="bi bi-x-circle-fill fs-5 flex-shrink-0 mt-1"></i>
  <div>
    <div class="fw-bold">Rejected by General Manager</div>
    <?php if ($gmNotes): ?>
    <div class="small mt-1"><strong>Rejection Reason:</strong> <?= e($gmNotes) ?></div>
    <?php endif; ?>
    <?php if ($gmName): ?>
    <div class="small text-muted mt-1">
      Rejected by: <strong><?= e($gmName) ?></strong>
      <?= $gmAt ? 'on ' . date('d/m/Y H:i', strtotime($gmAt)) : '' ?>
    </div>
    <?php endif; ?>
    <div class="small mt-2">
      <a href="<?= url('transfers/' . ($dis['reference_id'] ?? '') . '/receive') ?>"
         class="btn btn-warning btn-sm">
        <i class="bi bi-arrow-repeat me-1"></i>Re-submit Receiving
      </a>
    </div>
  </div>
</div>

<?php elseif ($transferStatus === TransferModel::STATUS_FINAL_REJECTED): ?>
<?php
// Fetch stock_restored and rejection_count from transfer record
$db2 = \AAM\Core\Database::getInstance();
$trnExtra = $db2->fetchOne(
    "SELECT discrepancy_rejection_count, discrepancy_stock_restored, discrepancy_gm_notes
     FROM warehouse_transfers WHERE id = :id",
    ['id' => (int)$dis['reference_id']]
);
$finalRejReason = $trnExtra['discrepancy_gm_notes'] ?? $gmNotes;
?>
<div class="alert alert-dark d-flex gap-2 align-items-start mb-3 border border-danger">
  <i class="bi bi-slash-circle-fill fs-4 flex-shrink-0 mt-1 text-danger"></i>
  <div class="flex-grow-1">
    <div class="fw-bold text-danger">Final Rejection — Transfer Permanently Closed</div>
    <div class="small mt-1">
      This transfer discrepancy was rejected by the General Manager
      <strong><?= TransferModel::MAX_DISCREPANCY_REJECTIONS ?></strong> times.
      <strong>Resubmission is no longer allowed.</strong>
      Warehouse stock has been automatically restored. No inventory has been added to the shop.
    </div>
    <?php if (!empty($finalRejReason)): ?>
    <div class="mt-1 small"><strong>Final Rejection Reason:</strong> <?= e($finalRejReason) ?></div>
    <?php endif; ?>
    <?php if ($gmName): ?>
    <div class="small text-muted mt-1">
      Rejected by: <strong><?= e($gmName) ?></strong>
      <?= $gmAt ? 'on ' . date('d/m/Y H:i', strtotime($gmAt)) : '' ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($trnExtra['discrepancy_rejection_count'])): ?>
    <div class="small text-muted mt-1">
      Rejection count: <strong><?= (int)$trnExtra['discrepancy_rejection_count'] ?></strong>
      / <?= TransferModel::MAX_DISCREPANCY_REJECTIONS ?>
    </div>
    <?php endif; ?>
    <?php if ((int)($trnExtra['discrepancy_stock_restored'] ?? 0)): ?>
    <div class="mt-1 small text-success">
      <i class="bi bi-check-circle me-1"></i>Warehouse stock has been restored.
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<div class="row g-3 mb-3">
  <!-- Details -->
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header fw-semibold small">
        <i class="bi bi-info-circle me-2 text-danger"></i>Discrepancy Details
      </div>
      <div class="card-body">
        <dl class="row small mb-0">
          <dt class="col-sm-4 text-muted">Product</dt>
          <dd class="col-sm-8 fw-semibold"><?= e($dis['product_name'] ?? '—') ?>
            <?php if ($dis['product_sku']): ?>
            <span class="text-muted fw-normal ms-1">(<?= e($dis['product_sku']) ?>)</span>
            <?php endif; ?>
          </dd>

          <dt class="col-sm-4 text-muted">Batch</dt>
          <dd class="col-sm-8"><?= e($dis['batch_number'] ?? '—') ?></dd>

          <dt class="col-sm-4 text-muted">Source Warehouse</dt>
          <dd class="col-sm-8"><?= e($dis['warehouse_name'] ?? '—') ?></dd>

          <dt class="col-sm-4 text-muted">Destination Shop</dt>
          <dd class="col-sm-8"><?= e($dis['shop_name'] ?? '—') ?></dd>

          <dt class="col-sm-4 text-muted">Transfer #</dt>
          <dd class="col-sm-8">
            <?php if (!empty($dis['transfer_number'])): ?>
            <a href="<?= url('transfers/' . $dis['reference_id']) ?>"><?= e($dis['transfer_number']) ?></a>
            <?php else: ?>—<?php endif; ?>
          </dd>

          <dt class="col-sm-4 text-muted">Type</dt>
          <dd class="col-sm-8"><?= e($type) ?></dd>

          <dt class="col-sm-4 text-muted">Reason Submitted</dt>
          <dd class="col-sm-8"><?= e($dis['reason'] ?? '—') ?></dd>

          <dt class="col-sm-4 text-muted">Reported By</dt>
          <dd class="col-sm-8"><?= e($dis['reported_by_name'] ?? '—') ?></dd>

          <?php if ($gmAction): ?>
          <dt class="col-sm-4 text-muted">GM Decision</dt>
          <dd class="col-sm-8">
            <span class="badge bg-<?= $gmAction === 'approved' ? 'success' : 'danger' ?>">
              <?= $gmAction === 'approved' ? 'Approved' : 'Rejected' ?>
            </span>
            <?php if ($gmName): ?>
            <span class="text-muted ms-2 small">by <?= e($gmName) ?></span>
            <?php endif; ?>
          </dd>
          <?php endif; ?>

          <?php if (!empty($dis['resolved_by_name'])): ?>
          <dt class="col-sm-4 text-muted">Resolved By</dt>
          <dd class="col-sm-8"><?= e($dis['resolved_by_name']) ?>
            <span class="text-muted ms-1">
              <?= $dis['resolved_at'] ? '(' . date('d/m/Y', strtotime($dis['resolved_at'])) . ')' : '' ?>
            </span>
          </dd>
          <?php endif; ?>

          <?php if (!empty($dis['resolution_notes'])): ?>
          <dt class="col-sm-4 text-muted">Resolution</dt>
          <dd class="col-sm-8 text-muted"><?= e($dis['resolution_notes']) ?></dd>
          <?php endif; ?>
        </dl>
      </div>
    </div>
  </div>

  <!-- Quantity summary -->
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header fw-semibold small">
        <i class="bi bi-bar-chart me-2 text-primary"></i>Quantity Summary
      </div>
      <div class="card-body">
        <?php
        $rows = [
          ['Dispatched (Sent)', $dis['expected_quantity'] ?? 0, 'primary'],
          ['Received by Shop',  $dis['actual_quantity']   ?? 0, 'success'],
          ['Missing / Variance',abs($var),                      $var > 0 ? 'danger' : 'warning'],
        ];
        foreach ($rows as [$lbl, $qty, $color]):
        ?>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
          <span class="text-muted small"><?= $lbl ?></span>
          <span class="fw-bold text-<?= $color ?>">
            <?= $lbl === 'Missing / Variance' && $var > 0 ? '−' : '' ?><?= number_format((float)$qty, 3) ?>
          </span>
        </div>
        <?php endforeach; ?>
        <div class="d-flex justify-content-between align-items-center py-2">
          <span class="text-muted small">Variance %</span>
          <span class="fw-bold text-danger"><?= number_format((float)($dis['variance_percent'] ?? 0), 2) ?>%</span>
        </div>

        <?php if ($gmAction === 'approved'): ?>
        <div class="mt-3 p-2 bg-success-subtle rounded border border-success-subtle small">
          <i class="bi bi-check-circle-fill text-success me-1"></i>
          <strong>Shop inventory updated:</strong>
          +<?= number_format((float)($dis['actual_quantity'] ?? 0), 3) ?> (actual received only).<br>
          Missing <?= number_format(abs($var), 3) ?> permanently recorded as discrepancy loss.
        </div>
        <?php elseif ($gmAction === 'rejected' && $transferStatus !== TransferModel::STATUS_FINAL_REJECTED): ?>
        <div class="mt-3 p-2 bg-danger-subtle rounded border border-danger-subtle small">
          <i class="bi bi-x-circle-fill text-danger me-1"></i>
          <strong>Shop inventory NOT updated.</strong>
          Discrepancy was rejected. Please re-submit.
        </div>
        <?php elseif ($transferStatus === TransferModel::STATUS_FINAL_REJECTED): ?>
        <div class="mt-3 p-2 bg-dark rounded border border-danger small text-white">
          <i class="bi bi-slash-circle-fill text-danger me-1"></i>
          <strong>Final Rejection — shop inventory NOT updated.</strong><br>
          Transfer is permanently closed. No stock from this transfer will be added to the shop.
        </div>
        <?php elseif (!$gmAction && $isTransferDiscrepancy): ?>
        <div class="mt-3 p-2 bg-warning-subtle rounded border border-warning-subtle small">
          <i class="bi bi-hourglass-split text-warning me-1"></i>
          <strong>Shop inventory pending.</strong>
          Awaiting GM approval before inventory is updated.
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- GM resolve form (GM-only manual status update for non-transfer discrepancies) -->
<?php if ($canResolve && in_array($dis['status'] ?? '', ['open','under_review'], true) && !$isTransferDiscrepancy): ?>
<div class="card border-0 shadow-sm mt-3">
  <div class="card-header fw-semibold small">
    <i class="bi bi-person-check me-2 text-success"></i>Update Status
  </div>
  <div class="card-body">
    <form method="POST" action="<?= url('shop/discrepancies/' . ($dis['id'] ?? '') . '/status') ?>">
      <?php csrf_input(); ?>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label small fw-semibold">New Status</label>
          <select name="status" class="form-select form-select-sm" required>
            <option value="">— Select —</option>
            <option value="under_review">Under Review</option>
            <option value="resolved">Resolved</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Resolution Notes</label>
          <input type="text" name="notes" class="form-control form-control-sm"
                 placeholder="Explain the resolution…">
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button type="submit" class="btn btn-success btn-sm w-100">Update</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
