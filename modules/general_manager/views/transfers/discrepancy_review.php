<?php
/**
 * GM — Transfer Receiving Discrepancy Review Detail
 *
 * @var array  $transfer      Full transfer with items
 * @var array  $warehouse
 * @var array  $discrepancies Discrepancy records for this transfer
 * @var array  $workflowLogs  [discrepancy_id => [...log rows]]
 */
declare(strict_types=1);

use AAM\Warehouse\Models\TransferModel;
use AAM\Warehouse\Models\DiscrepancyModel;

$transfer      ??= [];
$warehouse     ??= [];
$discrepancies ??= [];
$workflowLogs  ??= [];

$status       = $transfer['status'] ?? '';
$isPending    = $status === TransferModel::STATUS_DISCREPANCY_PENDING;
$isApproved   = in_array($status, [TransferModel::STATUS_GM_APPROVED, TransferModel::STATUS_RECEIVED_WITH_DISCREPANCY], true);
$isRejected   = $status === TransferModel::STATUS_GM_REJECTED;
$isFinalRejected = $status === TransferModel::STATUS_FINAL_REJECTED;

$totalSent     = 0.0;
$totalReceived = 0.0;
foreach ($transfer['items'] ?? [] as $item) {
    $totalSent     += (float)$item['quantity_sent'];
    $totalReceived += (float)($item['quantity_received'] ?? 0);
}
$totalMissing = $totalSent - $totalReceived;
?>

<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
  <a href="<?= url('gm/transfers/discrepancies/pending-review') ?>"
     class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back to Pending
  </a>
  <div>
    <h5 class="mb-0 fw-bold">
      <i class="bi bi-shield-exclamation me-2 text-warning"></i>
      Discrepancy Review — <?= e($transfer['transfer_number'] ?? '') ?>
    </h5>
    <small class="text-muted">
      <?= e($warehouse['name'] ?? '—') ?> &rarr; <?= e($transfer['shop_name'] ?? '—') ?>
    </small>
  </div>
  <span class="badge bg-<?= TransferModel::statusBadge($status) ?> ms-auto">
    <?= TransferModel::statusLabel($status) ?>
  </span>
</div>

<!-- Summary KPI cards -->
<div class="row g-3 mb-4">
  <?php $kpis = [
    ['Sent (Dispatched)',  number_format($totalSent,     3), 'primary',   'box-arrow-right'],
    ['Received by Shop',   number_format($totalReceived, 3), 'success',   'box-arrow-in-left'],
    ['Missing / Variance', number_format($totalMissing,  3), 'danger',    'exclamation-triangle'],
    ['Status',             TransferModel::statusLabel($status), TransferModel::statusBadge($status), 'info-circle'],
  ];
  foreach ($kpis as [$lbl, $val, $col, $ico]): ?>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body text-center py-3">
        <i class="bi bi-<?= $ico ?> fs-4 text-<?= $col ?> d-block mb-1"></i>
        <div class="fw-bold"><?= e($val) ?></div>
        <div class="text-muted small"><?= $lbl ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Transfer line items -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header fw-semibold small">
    <i class="bi bi-table me-2"></i>Transfer Line Items — Quantity Comparison
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Product</th>
            <th class="text-end">Sent</th>
            <th class="text-end">Received</th>
            <th class="text-end">Missing</th>
            <th class="text-end pe-3">Variance %</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($transfer['items'] ?? [] as $item):
            $sent     = (float)$item['quantity_sent'];
            $received = (float)($item['quantity_received'] ?? 0);
            $missing  = $sent - $received;
            $varPct   = $sent > 0 ? round(abs($missing / $sent) * 100, 1) : 0;
            $hasVar   = abs($missing) > 0.001;
          ?>
          <tr class="<?= $hasVar ? 'table-danger' : '' ?>">
            <td class="ps-3 small">
              <div class="fw-semibold"><?= e($item['product_name'] ?? '—') ?></div>
              <div class="text-muted" style="font-size:.68rem;"><?= e($item['product_sku'] ?? '') ?></div>
            </td>
            <td class="text-end small fw-bold text-primary">
              <?= number_format($sent, 3) ?> <?= e($item['unit_abbr'] ?? '') ?>
            </td>
            <td class="text-end small fw-bold text-success">
              <?= number_format($received, 3) ?> <?= e($item['unit_abbr'] ?? '') ?>
            </td>
            <td class="text-end small fw-bold <?= $hasVar ? 'text-danger' : 'text-success' ?>">
              <?= $hasVar ? number_format($missing, 3) : '<i class="bi bi-check2"></i>' ?>
            </td>
            <td class="text-end pe-3 small">
              <?php if ($hasVar): ?>
              <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                <?= $varPct ?>%
              </span>
              <?php else: ?>
              <span class="text-success small">OK</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Discrepancy records with reasons and workflow log -->
<?php foreach ($discrepancies as $dis):
  $logs = $workflowLogs[$dis['id']] ?? [];
?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header d-flex align-items-center justify-content-between small fw-semibold">
    <span>
      <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>
      <?= e($dis['discrepancy_number'] ?? '') ?>
      — <?= e($dis['product_name'] ?? '—') ?>
    </span>
    <span class="badge bg-<?= DiscrepancyModel::statusBadge($dis['status']) ?>">
      <?= DiscrepancyModel::statusLabel($dis['status']) ?>
    </span>
  </div>
  <div class="card-body">
    <div class="row g-3 mb-3">
      <div class="col-sm-4">
        <div class="small text-muted">Expected (Sent)</div>
        <div class="fw-bold text-primary"><?= number_format((float)$dis['expected_quantity'], 3) ?></div>
      </div>
      <div class="col-sm-4">
        <div class="small text-muted">Actual (Received)</div>
        <div class="fw-bold text-success"><?= number_format((float)$dis['actual_quantity'], 3) ?></div>
      </div>
      <div class="col-sm-4">
        <div class="small text-muted">Variance / Missing</div>
        <div class="fw-bold text-danger">
          <?= number_format((float)$dis['variance_quantity'], 3) ?>
          <span class="text-muted fw-normal">(<?= number_format((float)$dis['variance_percent'], 1) ?>%)</span>
        </div>
      </div>
    </div>

    <div class="mb-3">
      <div class="small text-muted fw-semibold mb-1">Reason Provided by Salesperson</div>
      <div class="p-2 bg-light rounded border small">
        <?= nl2br(e($dis['reason'] ?? '—')) ?>
      </div>
    </div>

    <div class="mb-2">
      <div class="small text-muted">
        Reported by: <strong><?= e($dis['reported_by_name'] ?? '—') ?></strong>
        &nbsp;|&nbsp;
        <?= $dis['reported_at'] ? date('d/m/Y H:i', strtotime($dis['reported_at'])) : '' ?>
      </div>
    </div>

    <?php if (!empty($dis['gm_action'])): ?>
    <div class="alert alert-<?= $dis['gm_action'] === 'approved' ? 'success' : 'danger' ?> small py-2 mt-2 mb-0">
      <strong>GM Decision:</strong>
      <?= $dis['gm_action'] === 'approved' ? 'Approved' : 'Rejected' ?>
      <?php if (!empty($dis['gm_notes'])): ?>
      — <?= e($dis['gm_notes']) ?>
      <?php endif; ?>
      <span class="text-muted ms-2">
        by <?= e($dis['gm_reviewed_by_name'] ?? '—') ?>
        <?= $dis['gm_reviewed_at'] ? 'on ' . date('d/m/Y H:i', strtotime($dis['gm_reviewed_at'])) : '' ?>
      </span>
    </div>
    <?php endif; ?>

    <!-- Workflow log -->
    <?php if (!empty($logs)): ?>
    <div class="mt-3">
      <div class="small fw-semibold text-muted mb-2">Workflow History</div>
      <div class="timeline small">
        <?php foreach ($logs as $log): ?>
        <div class="d-flex gap-2 mb-2 align-items-start">
          <span class="badge bg-secondary mt-1" style="font-size:.65rem;min-width:90px;text-align:center;">
            <?php echo match($log['action']) {
              'submitted'         => '<span class="badge bg-warning text-dark">Submitted by SP</span>',
              'gm_approved'       => '<span class="badge bg-success">GM Approved</span>',
              'gm_rejected'       => '<span class="badge bg-danger">GM Rejected</span>',
              'gm_final_rejected' => '<span class="badge bg-dark border border-danger text-white">GM Final Rejected</span>',
              'final_rejected'    => '<span class="badge bg-dark text-danger border border-danger">Final Rejected</span>',
              'stock_restored'    => '<span class="badge bg-success">Stock Restored</span>',
              'resubmitted'       => '<span class="badge bg-warning text-dark">Resubmitted</span>',
              default             => '<span class="badge bg-secondary">' . e(ucfirst(str_replace('_', ' ', $log['action']))) . '</span>',
            }; ?>
          </span>
          <div>
            <span class="fw-semibold"><?= e($log['actor_name'] ?? 'System') ?></span>
            <span class="text-muted"> — <?= $log['created_at'] ? date('d/m/Y H:i', strtotime($log['created_at'])) : '' ?></span>
            <?php if (!empty($log['notes'])): ?>
            <div class="text-muted"><?= e($log['notes']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<!-- GM ACTION PANEL — only shown when pending -->
<?php if ($isPending): ?>
<div class="card border-warning border-2 shadow-sm mb-4">
  <div class="card-header bg-warning bg-opacity-10 fw-semibold small">
    <i class="bi bi-shield-check me-2 text-warning"></i>
    Your Decision Required
  </div>
  <div class="card-body">
    <p class="small text-muted mb-3">
      Review the discrepancy details above.
      <strong>Approving</strong> will update shop inventory with the actual received quantity only
      (missing <?= number_format($totalMissing, 3) ?> will NOT be added).
      <strong>Rejecting</strong> returns the discrepancy to the Salesperson for correction.
    </p>

    <div class="row g-3">
      <!-- APPROVE -->
      <div class="col-md-6">
        <div class="card border-success h-100">
          <div class="card-body">
            <h6 class="text-success fw-bold mb-3">
              <i class="bi bi-check-circle-fill me-1"></i>Approve Discrepancy
            </h6>
            <p class="small text-muted mb-3">
              Shop inventory will be updated with
              <strong class="text-success"><?= number_format($totalReceived, 3) ?></strong>
              (actual received).<br>
              Missing <strong class="text-danger"><?= number_format($totalMissing, 3) ?></strong>
              will be permanently recorded as a discrepancy loss.
            </p>
            <form method="POST"
                  action="<?= url('gm/transfers/' . ($transfer['id'] ?? '') . '/discrepancy-approve') ?>"
                  id="approveForm">
              <?php csrf_input(); ?>
              <div class="mb-3">
                <label class="form-label small fw-semibold">Notes (optional)</label>
                <textarea name="gm_notes" class="form-control form-control-sm" rows="2"
                          placeholder="Optional notes about this approval…"></textarea>
              </div>
              <button type="submit" class="btn btn-success w-100"
                      onclick="return confirm('Approve this discrepancy?\n\nShop inventory will be updated with the actual received quantity.\nMissing ' + '<?= number_format($totalMissing, 3) ?>' + ' will NOT be added.')">
                <i class="bi bi-check-circle me-1"></i>Approve &amp; Update Shop Inventory
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- REJECT -->
      <div class="col-md-6">
        <div class="card border-danger h-100">
          <div class="card-body">
            <h6 class="text-danger fw-bold mb-3">
              <i class="bi bi-x-circle-fill me-1"></i>Reject Discrepancy
            </h6>
            <p class="small text-muted mb-3">
              The discrepancy will be returned to the Salesperson for review and re-submission.
              Shop inventory will NOT be updated.
              <strong>A rejection reason is required.</strong>
            </p>
            <form method="POST"
                  action="<?= url('gm/transfers/' . ($transfer['id'] ?? '') . '/discrepancy-reject') ?>"
                  id="rejectForm">
              <?php csrf_input(); ?>
              <div class="mb-3">
                <label class="form-label small fw-semibold">
                  Rejection Reason <span class="text-danger">*</span>
                </label>
                <textarea name="rejection_reason"
                          class="form-control form-control-sm"
                          rows="3"
                          required
                          placeholder="Explain why the discrepancy is rejected…"></textarea>
              </div>
              <button type="submit" class="btn btn-danger w-100"
                      onclick="return confirm('Reject this discrepancy?\n\nThe Salesperson will be notified to re-submit.')">
                <i class="bi bi-x-circle me-1"></i>Reject &amp; Return for Correction
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php elseif ($isApproved): ?>
<div class="alert alert-success d-flex gap-2 align-items-center mb-3">
  <i class="bi bi-check-circle-fill fs-5"></i>
  <div>
    <strong>Discrepancy Approved.</strong>
    Shop inventory was updated with the actual received quantity
    (<strong><?= number_format($totalReceived, 3) ?></strong>).
    Missing <strong><?= number_format($totalMissing, 3) ?></strong> recorded as discrepancy loss.
    <?php if (!empty($transfer['discrepancy_gm_notes'])): ?>
    <div class="small mt-1 text-muted">GM Notes: <?= e($transfer['discrepancy_gm_notes']) ?></div>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($isRejected): ?>
<div class="alert alert-danger d-flex gap-2 align-items-center mb-3">
  <i class="bi bi-x-circle-fill fs-5"></i>
  <div>
    <strong>Discrepancy Rejected.</strong>
    The Salesperson has been notified to re-submit.
    <?php if (!empty($transfer['discrepancy_gm_notes'])): ?>
    <div class="small mt-1">Rejection Reason: <?= e($transfer['discrepancy_gm_notes']) ?></div>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($isFinalRejected): ?>
<div class="alert alert-dark d-flex gap-2 align-items-start mb-3 border border-danger">
  <i class="bi bi-slash-circle-fill fs-4 flex-shrink-0 mt-1 text-danger"></i>
  <div class="flex-grow-1">
    <strong class="text-danger">Final Rejection — Transfer Permanently Closed</strong><br>
    <div class="small mt-1">
      This transfer discrepancy was rejected <strong><?= TransferModel::MAX_DISCREPANCY_REJECTIONS ?></strong> times.
      Resubmission is no longer allowed. The transfer is permanently closed.
    </div>
    <?php
    $rejCount = (int)($transfer['discrepancy_rejection_count'] ?? 0);
    $stockRestored = (int)($transfer['discrepancy_stock_restored'] ?? 0);
    ?>
    <?php if (!empty($transfer['discrepancy_gm_notes'])): ?>
    <div class="small mt-1"><strong>Last Rejection Reason:</strong> <?= e($transfer['discrepancy_gm_notes']) ?></div>
    <?php endif; ?>
    <div class="small text-muted mt-1">
      Total GM rejection attempts: <strong><?= $rejCount ?></strong>
    </div>
    <?php if ($stockRestored): ?>
    <div class="small text-success mt-1">
      <i class="bi bi-check-circle me-1"></i>Warehouse stock has been automatically restored.
    </div>
    <?php endif; ?>
    <div class="small mt-2 text-muted">
      Sent: <strong><?= number_format($totalSent, 3) ?></strong>
      &nbsp;|&nbsp; Received by shop: <strong><?= number_format($totalReceived, 3) ?></strong>
      &nbsp;|&nbsp; Missing (not added to shop): <strong class="text-danger"><?= number_format($totalMissing, 3) ?></strong>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="d-flex gap-2 mt-3">
  <a href="<?= url('gm/transfers/discrepancies/pending-review') ?>"
     class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-list me-1"></i>Back to Pending List
  </a>
  <a href="<?= url('transfers/' . ($transfer['id'] ?? '')) ?>"
     class="btn btn-outline-primary btn-sm">
    <i class="bi bi-eye me-1"></i>View Full Transfer
  </a>
</div>
