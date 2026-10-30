<?php
/**
 * GM Discrepancy Review — Receiving
 * General Manager reviews quantity discrepancy and approves or rejects.
 *
 * @var array  $receiving      Full receiving record with items
 * @var array  $discrepancies  Discrepancy records for this receiving
 * @var array  $workflowLogs   keyed by discrepancy_id
 */
declare(strict_types=1);
$receiving     ??= [];
$discrepancies ??= [];
$workflowLogs  ??= [];
$items          = $receiving['items'] ?? [];
$status         = $receiving['status'] ?? '';
$rcvId          = (int)($receiving['id'] ?? 0);
$canAct         = ($status === 'discrepancy_pending');
$isGMApproved   = ($status === 'gm_approved');
$isGMRejected   = ($status === 'gm_rejected');
$isFinalRejected = ($status === 'final_rejected');
$rejCount       = (int)($receiving['rejection_count'] ?? 0);
$maxRejections  = \AAM\Warehouse\Models\ReceivingModel::MAX_REJECTIONS;
?>

<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
  <a href="<?= url('gm/warehouses/discrepancies/pending-review') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back to Pending Reviews
  </a>
  <div class="flex-grow-1">
    <h5 class="mb-0 fw-bold">
      <i class="bi bi-shield-check me-2 text-warning"></i>
      Discrepancy Review — <?= e($receiving['receiving_number'] ?? '') ?>
    </h5>
    <small class="text-muted">
      Farm: <strong><?= e($receiving['farm_name'] ?? '—') ?></strong>
      &rarr; Warehouse: <strong><?= e($receiving['warehouse_name'] ?? '—') ?></strong>
      &middot; Dispatch: <?= e($receiving['dispatch_number'] ?? '—') ?>
      &middot; Date: <?= $receiving['received_date'] ? date('d M Y', strtotime($receiving['received_date'])) : '—' ?>
    </small>
  </div>
</div>

<?php if (!$canAct): ?>
<?php if ($isFinalRejected): ?>
<div class="alert alert-dark d-flex gap-2 align-items-start mb-3 border border-danger">
  <i class="bi bi-slash-circle-fill fs-4 flex-shrink-0 mt-1 text-danger"></i>
  <div class="flex-grow-1">
    <div class="fw-bold text-danger">Final Rejection — Transfer Permanently Closed</div>
    <div class="small mt-1">
      This receiving discrepancy was rejected <strong><?= $maxRejections ?></strong> times.
      The transfer is permanently closed and the dispatched stock has been automatically
      returned to the source Farm.
    </div>
    <?php
    $lastReason = '';
    foreach ($discrepancies as $_d) {
        if (!empty($_d['gm_notes'])) { $lastReason = $_d['gm_notes']; break; }
    }
    ?>
    <?php if ($lastReason): ?>
    <div class="mt-1 small"><strong>Final Rejection Reason:</strong> <?= e($lastReason) ?></div>
    <?php endif; ?>
    <?php if ($rejCount): ?>
    <div class="small text-muted mt-1">Total rejections: <strong><?= $rejCount ?></strong> / <?= $maxRejections ?></div>
    <?php endif; ?>
    <?php if ((int)($receiving['stock_restored'] ?? 0)): ?>
    <div class="mt-1 small text-success">
      <i class="bi bi-check-circle me-1"></i>Farm harvest stock has been restored.
    </div>
    <?php endif; ?>
    <div class="mt-2 small text-muted">
      Sent: <strong><?= number_format((float)$receiving['total_expected'], 3) ?></strong>
      &nbsp;|&nbsp; Warehouse received: <strong><?= number_format((float)$receiving['total_received'], 3) ?></strong>
      &nbsp;|&nbsp; Missing (not added to warehouse): <strong class="text-danger"><?= number_format((float)$receiving['total_expected'] - (float)$receiving['total_received'], 3) ?></strong>
    </div>
  </div>
</div>
<?php elseif ($isGMRejected): ?>
<div class="alert alert-danger mb-3 small">
  <i class="bi bi-x-circle-fill me-2"></i>
  Discrepancy has been <strong>rejected</strong> (rejection #<?= $rejCount ?>).
  The Warehouse Manager has been notified to correct and resubmit.
  <?php if ($rejCount >= $maxRejections - 1): ?>
  <div class="mt-1 fw-bold">⚠ Next rejection will trigger Final Rejection — no resubmission allowed.</div>
  <?php endif; ?>
</div>
<?php elseif ($isGMApproved): ?>
<div class="alert alert-success mb-3 small">
  <i class="bi bi-check-circle-fill me-2"></i>
  Discrepancy has been <strong>approved</strong>.
  The Warehouse Manager can now complete the receiving. Inventory will be updated with the actual received quantity only.
</div>
<?php else: ?>
<div class="alert alert-secondary mb-3 small">
  <i class="bi bi-info-circle me-2"></i>
  This discrepancy has already been <strong><?= $status === 'gm_approved' ? 'approved' : 'reviewed' ?></strong>.
  No further action required.
</div>
<?php endif; ?>
<?php endif; ?>

<!-- ── Summary Cards ──────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center h-100 border-primary border-2">
      <div class="card-body py-3">
        <div class="fw-bold fs-3 text-primary"><?= number_format((float)$receiving['total_expected'], 3) ?></div>
        <div class="text-muted small">Expected (Dispatched)</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center h-100 border-success border-2">
      <div class="card-body py-3">
        <div class="fw-bold fs-3 text-success"><?= number_format((float)$receiving['total_received'], 3) ?></div>
        <div class="text-muted small">Actual Received</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center h-100 border-danger border-2">
      <div class="card-body py-3">
        <?php $missing = (float)$receiving['total_expected'] - (float)$receiving['total_received']; ?>
        <div class="fw-bold fs-3 text-danger"><?= number_format($missing, 3) ?></div>
        <div class="text-muted small">Missing</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center h-100">
      <div class="card-body py-3">
        <div class="fw-bold fs-3"><?= count($discrepancies) ?></div>
        <div class="text-muted small">Discrepancy Lines</div>
      </div>
    </div>
  </div>
</div>

<!-- ── Dispatch Info ──────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header fw-semibold small">
    <i class="bi bi-info-circle me-2 text-info"></i>Dispatch & Receiving Information
  </div>
  <div class="card-body">
    <div class="row g-2 small">
      <div class="col-md-4"><span class="text-muted">Dispatch Number:</span> <strong><?= e($receiving['dispatch_number'] ?? '—') ?></strong></div>
      <div class="col-md-4"><span class="text-muted">Dispatch Date:</span> <strong><?= $receiving['dispatch_date'] ? date('d M Y', strtotime($receiving['dispatch_date'])) : '—' ?></strong></div>
      <div class="col-md-4"><span class="text-muted">Received By (WM):</span> <strong><?= e($receiving['received_by_name'] ?? '—') ?></strong></div>
      <div class="col-md-4"><span class="text-muted">Farm:</span> <strong><?= e($receiving['farm_name'] ?? '—') ?></strong></div>
      <div class="col-md-4"><span class="text-muted">Warehouse:</span> <strong><?= e($receiving['warehouse_name'] ?? '—') ?></strong></div>
      <div class="col-md-4"><span class="text-muted">Receiving Date:</span> <strong><?= $receiving['received_date'] ? date('d M Y', strtotime($receiving['received_date'])) : '—' ?></strong></div>
    </div>
  </div>
</div>

<!-- ── Discrepancy Details ────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header fw-semibold small bg-warning-subtle">
    <i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i>
    Discrepancy Details — What the Warehouse Manager Reported
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Discrepancy #</th>
            <th>Product</th>
            <th>Batch</th>
            <th>Expected Qty</th>
            <th>Actual Qty</th>
            <th>Missing Qty</th>
            <th>Type</th>
            <th class="pe-3">WM Reason</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($discrepancies as $dis): ?>
          <tr>
            <td class="ps-3 small fw-semibold text-warning"><?= e($dis['discrepancy_number']) ?></td>
            <td>
              <div class="fw-semibold small"><?= e($dis['product_name'] ?? '—') ?></div>
              <div class="text-muted" style="font-size:.68rem;"><?= e($dis['product_sku'] ?? '') ?></div>
            </td>
            <td class="small"><?= e($dis['batch_number'] ?? '—') ?></td>
            <td class="small fw-bold text-primary"><?= number_format((float)$dis['expected_quantity'], 3) ?></td>
            <td class="small fw-bold text-success"><?= number_format((float)$dis['actual_quantity'], 3) ?></td>
            <td class="small fw-bold text-danger"><?= number_format((float)$dis['variance_quantity'], 3) ?></td>
            <td><span class="badge bg-secondary small"><?= \AAM\Warehouse\Models\DiscrepancyModel::typeLabel($dis['discrepancy_type'] ?? 'other') ?></span></td>
            <td class="pe-3 small"><?= e($dis['reason']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ── GM Decision Buttons ───────────────────────────────── -->
<?php if ($canAct): ?>
<div class="row g-3 mb-3">
  <div class="col-md-6">
    <div class="card border-success border-2 h-100">
      <div class="card-body">
        <h6 class="text-success fw-bold"><i class="bi bi-check-circle me-2"></i>Approve Discrepancy</h6>
        <p class="small text-muted mb-3">
          Approve this discrepancy. The Warehouse Manager will be notified and can then complete the
          receiving — inventory will be updated with the <strong>actual received quantity
          (<?= number_format((float)$receiving['total_received'], 3) ?>)</strong> only.
          The missing <?= number_format($missing, 3) ?> will NOT be added.
        </p>
        <form method="POST" action="<?= url('gm/warehouses/receiving/' . $rcvId . '/discrepancy-approve') ?>"
              onsubmit="return confirm('Approve this discrepancy?\n\nWarehouse inventory will be updated with ACTUAL received quantity only after the WM completes receiving.')">
          <?php csrf_input(); ?>
          <div class="mb-2">
            <label class="form-label small fw-semibold">GM Notes (optional)</label>
            <textarea name="gm_notes" class="form-control form-control-sm" rows="2"
                      placeholder="Any notes about the approval decision…"></textarea>
          </div>
          <button class="btn btn-success w-100">
            <i class="bi bi-check-circle me-1"></i>Approve Discrepancy
          </button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card border-danger border-2 h-100">
      <div class="card-body">
        <h6 class="text-danger fw-bold"><i class="bi bi-x-circle me-2"></i>Reject / Return for Review</h6>
        <p class="small text-muted mb-3">
          Reject this discrepancy and return it to the Warehouse Manager for correction.
          Inventory will NOT be updated. The WM will be notified with your reason.
        </p>
        <form method="POST" action="<?= url('gm/warehouses/receiving/' . $rcvId . '/discrepancy-reject') ?>"
              onsubmit="return confirm('Reject this discrepancy?\n\nThe Warehouse Manager will be notified to review and correct.')">
          <?php csrf_input(); ?>
          <div class="mb-2">
            <label class="form-label small fw-semibold">Rejection Reason <span class="text-danger">*</span></label>
            <textarea name="rejection_reason" class="form-control form-control-sm" rows="2"
                      placeholder="State why this discrepancy is being rejected…" required></textarea>
          </div>
          <button class="btn btn-danger w-100">
            <i class="bi bi-x-circle me-1"></i>Reject — Return for Review
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── Workflow History ───────────────────────────────────── -->
<?php foreach ($discrepancies as $dis):
  $log = $workflowLogs[$dis['id']] ?? [];
  if (empty($log)) continue;
?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header fw-semibold small">
    <i class="bi bi-clock-history me-2 text-secondary"></i>
    Workflow History — <?= e($dis['discrepancy_number']) ?>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Step</th><th>Actor</th><th>Notes</th><th class="pe-3">Timestamp</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($log as $entry): ?>
          <tr>
            <td class="ps-3">
              <?php echo match($entry['action']) {
                'submitted'         => '<span class="badge bg-warning text-dark">Submitted by WM</span>',
                'gm_approved'       => '<span class="badge bg-success">GM Approved</span>',
                'gm_rejected'       => '<span class="badge bg-danger">GM Rejected</span>',
                'gm_final_rejected' => '<span class="badge bg-dark border border-danger text-white">GM Final Rejected</span>',
                'final_rejected'    => '<span class="badge bg-dark text-danger border border-danger">Final Rejected</span>',
                'stock_restored'    => '<span class="badge bg-success">Stock Restored to Farm</span>',
                'resubmitted'       => '<span class="badge bg-warning text-dark">Resubmitted by WM</span>',
                'inventory_applied' => '<span class="badge bg-primary">Inventory Updated</span>',
                'farm_notified'     => '<span class="badge bg-secondary">Farm Notified</span>',
                default             => '<span class="badge bg-secondary">' . e($entry['action']) . '</span>',
              }; ?>
            </td>
            <td class="small"><?= e($entry['actor_name'] ?? 'System') ?></td>
            <td class="small text-muted"><?= e($entry['notes'] ?? '') ?></td>
            <td class="pe-3 small text-muted"><?= $entry['created_at'] ? date('d/m/Y H:i', strtotime($entry['created_at'])) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endforeach; ?>
