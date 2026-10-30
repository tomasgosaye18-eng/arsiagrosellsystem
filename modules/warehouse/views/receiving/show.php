<?php
/**
 * Receiving Detail View — with Discrepancy Workflow
 * @var array  $receiving
 * @var array  $warehouse
 * @var string $userRole
 * @var array  $discrepancies   Populated when status involves discrepancy
 * @var array  $workflowLogs    keyed by discrepancy_id
 */
declare(strict_types=1);
$receiving     ??= [];
$discrepancies ??= [];
$workflowLogs  ??= [];
$items          = $receiving['items'] ?? [];
$status         = $receiving['status'] ?? '';
$badge          = \AAM\Warehouse\Models\ReceivingModel::statusBadge($status);
$label          = \AAM\Warehouse\Models\ReceivingModel::statusLabel($status);
$isWM           = in_array($userRole, [ROLE_WAREHOUSE_MANAGER], true);
$isGM           = in_array($userRole, [ROLE_GENERAL_MANAGER], true);
?>

<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
  <a href="<?= url('warehouse/receiving?tab=history') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
  <div class="flex-grow-1">
    <h5 class="mb-0 fw-bold">
      <?= e($receiving['receiving_number'] ?? '') ?>
      <span class="badge bg-<?= $badge ?> ms-2"><?= $label ?></span>
    </h5>
    <small class="text-muted">
      Dispatch: <strong><?= e($receiving['dispatch_number'] ?? '—') ?></strong>
      &middot; Farm: <strong><?= e($receiving['farm_name'] ?? '—') ?></strong>
      &middot; Received: <?= $receiving['received_date'] ? date('d M Y', strtotime($receiving['received_date'])) : '—' ?>
      &middot; By: <?= e($receiving['received_by_name'] ?? '—') ?>
    </small>
  </div>
  <?php if ($status === 'gm_approved' && $isWM): ?>
  <form method="POST" action="<?= url('warehouse/receiving/' . $receiving['id'] . '/complete') ?>"
        onsubmit="return confirm('Complete this receiving?\n\nWarehouse inventory will be updated with the ACTUAL received quantity only.\nThe missing quantity will NOT be added.\n\nContinue?')">
    <?php csrf_input(); ?>
    <button class="btn btn-success">
      <i class="bi bi-check2-circle me-1"></i>Complete Receiving &amp; Update Inventory
    </button>
  </form>
  <?php endif; ?>
  <?php if ($status === 'gm_rejected' && $isWM && !(int)($receiving['final_rejected'] ?? 0)): ?>
  <a href="<?= url('warehouse/receiving/' . $receiving['id'] . '/resubmit') ?>"
     class="btn btn-warning">
    <i class="bi bi-arrow-repeat me-1"></i>Resubmit Discrepancy
  </a>
  <?php endif; ?>
  <?php if ($status === 'discrepancy_pending' && $isGM): ?>
  <a href="<?= url('gm/warehouses/receiving/' . $receiving['id'] . '/discrepancy-review') ?>"
     class="btn btn-warning">
    <i class="bi bi-shield-check me-1"></i>Review Discrepancy
  </a>
  <?php endif; ?>
</div>

<!-- ── Workflow Status Banner ──────────────────────────────── -->
<?php if ($status === 'discrepancy_pending'): ?>
<div class="alert alert-warning d-flex gap-2 align-items-start mb-3">
  <i class="bi bi-clock-history fs-5 flex-shrink-0 mt-1"></i>
  <div>
    <strong>Discrepancy Pending GM Review</strong><br>
    <span class="small">
      A quantity discrepancy was reported. This receiving is waiting for General Manager approval
      before warehouse inventory can be updated. You cannot mark this as received until the GM approves.
    </span>
  </div>
</div>
<?php elseif ($status === 'gm_approved'): ?>
<div class="alert alert-info d-flex gap-2 align-items-start mb-3">
  <i class="bi bi-shield-check fs-5 flex-shrink-0 mt-1"></i>
  <div>
    <strong>Discrepancy Approved by General Manager</strong><br>
    <span class="small">
      The GM has reviewed and approved the discrepancy.
      <?php if ($isWM): ?>
      You can now <strong>Complete Receiving</strong> — warehouse inventory will be updated with
      the actual received quantity (<?= number_format((float)$receiving['total_received'], 3) ?>) only.
      The missing <?= number_format((float)$receiving['total_expected'] - (float)$receiving['total_received'], 3) ?>
      will NOT be added.
      <?php endif; ?>
    </span>
  </div>
</div>
<?php elseif ($status === 'gm_rejected'): ?>
<div class="alert alert-danger d-flex gap-2 align-items-start mb-3">
  <i class="bi bi-x-circle fs-5 flex-shrink-0 mt-1"></i>
  <div class="flex-grow-1">
    <strong>Discrepancy Rejected by General Manager</strong><br>
    <span class="small">
      The GM has rejected this discrepancy. Review the rejection reason in the discrepancy records
      below and resubmit with corrected quantities or reasons.
      <?php
      $rejCount = (int)($receiving['rejection_count'] ?? 0);
      $maxRej   = \AAM\Warehouse\Models\ReceivingModel::MAX_REJECTIONS;
      ?>
      <?php if ($rejCount >= $maxRej - 1): ?>
      <strong class="text-danger d-block mt-1">
        ⚠ Warning: This is rejection #<?= $rejCount ?> of <?= $maxRej ?>.
        A second rejection will permanently close this transfer.
      </strong>
      <?php endif; ?>
    </span>
  </div>
  <?php if ($isWM && !(int)($receiving['final_rejected'] ?? 0)): ?>
  <a href="<?= url('warehouse/receiving/' . $receiving['id'] . '/resubmit') ?>"
     class="btn btn-warning btn-sm flex-shrink-0">
    <i class="bi bi-arrow-repeat me-1"></i>Resubmit
  </a>
  <?php endif; ?>
</div>

<?php elseif ($status === 'final_rejected'): ?>
<div class="alert alert-dark d-flex gap-2 align-items-start mb-3 border border-danger">
  <i class="bi bi-slash-circle-fill fs-4 flex-shrink-0 mt-1 text-danger"></i>
  <div class="flex-grow-1">
    <strong class="text-danger">Final Rejection — Transfer Permanently Closed</strong><br>
    <span class="small">
      This receiving has been finally rejected by the General Manager after
      <?= \AAM\Warehouse\Models\ReceivingModel::MAX_REJECTIONS ?> rejection attempts.
      <strong>Resubmission is no longer allowed.</strong>
      The source farm stock has been automatically restored.
    </span>
    <?php
    // Show rejection counts and last reason from discrepancy records
    $lastReason = '';
    foreach ($discrepancies as $d) {
        if (!empty($d['gm_notes'])) { $lastReason = $d['gm_notes']; break; }
    }
    ?>
    <?php if ($lastReason): ?>
    <div class="mt-1 small"><strong>Final Rejection Reason:</strong> <?= e($lastReason) ?></div>
    <?php endif; ?>
    <?php if ((int)($receiving['stock_restored'] ?? 0)): ?>
    <div class="mt-1 small text-success">
      <i class="bi bi-check-circle me-1"></i>
      Farm harvest stock has been restored.
    </div>
    <?php endif; ?>
  </div>
</div>
<?php elseif ($status === 'received' && !empty($discrepancies)): ?>
<div class="alert alert-success d-flex gap-2 align-items-start mb-3">
  <i class="bi bi-check2-circle fs-5 flex-shrink-0 mt-1"></i>
  <div>
    <strong>Receiving Completed</strong><br>
    <span class="small">
      Discrepancy was reviewed and approved by the General Manager. Inventory updated with
      actual received quantity. Farm Manager has been notified.
    </span>
  </div>
</div>
<?php endif; ?>

<!-- ── KPI Cards ──────────────────────────────────────────── -->
<div class="row g-3 mb-3">
  <?php $missing = (float)$receiving['total_expected'] - (float)$receiving['total_received']; ?>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center h-100">
      <div class="card-body py-3">
        <div class="fw-bold fs-4 text-primary"><?= number_format((float)$receiving['total_expected'], 3) ?></div>
        <div class="text-muted small">Expected</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center h-100">
      <div class="card-body py-3">
        <div class="fw-bold fs-4 text-success"><?= number_format((float)$receiving['total_received'], 3) ?></div>
        <div class="text-muted small">Actual Received</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center h-100">
      <div class="card-body py-3">
        <div class="fw-bold fs-4 <?= $missing > 0.001 ? 'text-danger' : 'text-success' ?>">
          <?= $missing > 0.001 ? number_format($missing, 3) : '—' ?>
        </div>
        <div class="text-muted small">Missing</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center h-100">
      <div class="card-body py-3">
        <div class="fw-bold fs-4"><?= count($items) ?></div>
        <div class="text-muted small">Product Lines</div>
      </div>
    </div>
  </div>
</div>

<!-- ── Items Table ────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header fw-semibold small">
    <i class="bi bi-list-check me-2 text-success"></i>Received Items
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Product</th>
            <th>Batch</th>
            <th>Expiry</th>
            <th>Expected Qty</th>
            <th>Actual Qty</th>
            <th>Missing</th>
            <th class="pe-3">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item):
            $sm = ['accepted'=>'success','partial'=>'warning','rejected'=>'danger','pending'=>'secondary','discrepancy'=>'danger'];
            $sc = $sm[$item['item_status'] ?? ''] ?? 'secondary';
            $itemMissing = (float)$item['quantity_expected'] - (float)$item['quantity_received'];
          ?>
          <tr>
            <td class="ps-3">
              <div class="fw-semibold small"><?= e($item['product_name'] ?? '—') ?></div>
              <div class="text-muted" style="font-size:.68rem;"><?= e($item['product_sku'] ?? '') ?></div>
            </td>
            <td class="small"><?= e($item['batch_number'] ?? '—') ?></td>
            <td class="small <?= ($item['expiry_date'] && strtotime($item['expiry_date']) < time()) ? 'text-danger' : '' ?>">
              <?= $item['expiry_date'] ? date('d/m/Y', strtotime($item['expiry_date'])) : '—' ?>
            </td>
            <td class="small fw-semibold text-primary"><?= number_format((float)$item['quantity_expected'], 3) ?></td>
            <td class="small fw-semibold text-success"><?= number_format((float)$item['quantity_received'], 3) ?></td>
            <td class="small <?= $itemMissing > 0.001 ? 'text-danger fw-semibold' : 'text-muted' ?>">
              <?= $itemMissing > 0.001 ? number_format($itemMissing, 3) . ' (' . number_format((float)$item['variance_percent'], 1) . '%)' : '—' ?>
            </td>
            <td class="pe-3">
              <span class="badge bg-<?= $sc ?>-subtle text-<?= $sc ?>"><?= ucfirst(str_replace('_', ' ', $item['item_status'] ?? '')) ?></span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ── Discrepancy Details ────────────────────────────────── -->
<?php if (!empty($discrepancies)): ?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header fw-semibold small bg-warning-subtle">
    <i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i>
    Discrepancy Records
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Discrepancy #</th>
            <th>Product</th>
            <th>Expected</th>
            <th>Actual</th>
            <th>Missing</th>
            <th>Reason</th>
            <th>GM Action</th>
            <th class="pe-3">GM Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($discrepancies as $dis):
            $gmBadge = match($dis['gm_action'] ?? '') {
                'approved' => 'success',
                'rejected' => 'danger',
                default    => 'warning',
            };
            $gmLabel = match($dis['gm_action'] ?? '') {
                'approved' => 'Approved',
                'rejected' => 'Rejected',
                default    => 'Pending',
            };
          ?>
          <tr>
            <td class="ps-3 small fw-semibold"><?= e($dis['discrepancy_number']) ?></td>
            <td class="small"><?= e($dis['product_name'] ?? '—') ?></td>
            <td class="small text-primary fw-semibold"><?= number_format((float)$dis['expected_quantity'], 3) ?></td>
            <td class="small text-success fw-semibold"><?= number_format((float)$dis['actual_quantity'], 3) ?></td>
            <td class="small text-danger fw-semibold"><?= number_format((float)$dis['variance_quantity'], 3) ?></td>
            <td class="small"><?= e($dis['reason']) ?></td>
            <td><span class="badge bg-<?= $gmBadge ?>"><?= $gmLabel ?></span>
              <?php if (!empty($dis['gm_reviewed_by_name'])): ?>
              <div class="text-muted" style="font-size:.68rem;"><?= e($dis['gm_reviewed_by_name']) ?></div>
              <?php endif; ?>
            </td>
            <td class="pe-3 small text-muted"><?= e($dis['gm_notes'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Workflow Log -->
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
            <th class="ps-3">Step</th>
            <th>Actor</th>
            <th>Notes</th>
            <th class="pe-3">Timestamp</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($log as $entry): ?>
          <tr>
            <td class="ps-3">
              <?php
              $actionLabel = match($entry['action']) {
                'submitted'         => '<span class="badge bg-warning text-dark">Submitted by WM</span>',
                'gm_approved'       => '<span class="badge bg-success">GM Approved</span>',
                'gm_rejected'       => '<span class="badge bg-danger">GM Rejected</span>',
                'inventory_applied' => '<span class="badge bg-primary">Inventory Updated</span>',
                'farm_notified'     => '<span class="badge bg-secondary">Farm Notified</span>',
                default             => '<span class="badge bg-secondary">' . e($entry['action']) . '</span>',
              };
              echo $actionLabel;
              ?>
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

<?php endif; ?>

<?php if ($receiving['notes']): ?>
<div class="card border-0 shadow-sm mt-3">
  <div class="card-body">
    <strong class="small">Notes:</strong>
    <p class="mb-0 small text-muted"><?= e($receiving['notes']) ?></p>
  </div>
</div>
<?php endif; ?>
