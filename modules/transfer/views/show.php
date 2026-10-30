<?php
/**
 * Transfer Detail — AAM Business Workflow
 *
 * @var array  $transfer
 * @var array  $warehouse
 * @var string $userRole
 * @var array  $allowedActions        Keys: canApprove, canReject, canDispatch, canReceive, canCancel
 * @var array  $discrepancies         Discrepancy records (populated when status involves discrepancy)
 * @var array  $workflowLogs          [discrepancy_id => [...log rows]]
 * @var bool   $showDiscrepancyPanel  True when a discrepancy panel should be shown
 */
declare(strict_types=1);

use AAM\Warehouse\Models\TransferModel;
use AAM\Warehouse\Models\DiscrepancyModel;

$transfer             ??= [];
$allowedActions       ??= ['canApprove'=>false,'canReject'=>false,'canDispatch'=>false,'canReceive'=>false,'canCancel'=>false];
$discrepancies        ??= [];
$workflowLogs         ??= [];
$showDiscrepancyPanel ??= false;
$items  = $transfer['items'] ?? [];
$badge  = TransferModel::statusBadge($transfer['status'] ?? '');
$label  = TransferModel::statusLabel($transfer['status'] ?? '');
$status = $transfer['status'] ?? '';

// Back link: salesperson goes back to incoming list, others go to outgoing list
$backUrl = in_array($userRole, [ROLE_SALESPERSON], true)
    ? url('transfers/incoming')
    : url('transfers');
?>

<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
  <a href="<?= $backUrl ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
  <div class="flex-grow-1">
    <h5 class="mb-0 fw-bold">
      <?= e($transfer['transfer_number'] ?? '') ?>
      <span class="badge bg-<?= $badge ?> ms-2"><?= $label ?></span>
    </h5>
    <small class="text-muted">
      From: <strong><?= e($transfer['warehouse_name'] ?? '—') ?></strong>
      &rarr; To: <strong><?= e($transfer['shop_name'] ?? '—') ?></strong>
      &middot; <?= $transfer['transfer_date'] ? date('d M Y', strtotime($transfer['transfer_date'])) : '' ?>
    </small>
  </div>

  <!-- ══════════════════════════════════════════════════════
       ACTION BUTTONS — driven entirely by $allowedActions.
       These are computed in the controller based on role + status.
       Do NOT add role checks here — that would duplicate logic.
       ══════════════════════════════════════════════════════ -->
  <div class="d-flex gap-2 flex-wrap">

    <?php if ($allowedActions['canApprove']): ?>
    <!-- GM / Admin: pending → approved -->
    <form method="POST" action="<?= url('transfers/' . $transfer['id'] . '/approve') ?>">
      <?php csrf_input(); ?>
      <button class="btn btn-success btn-sm">
        <i class="bi bi-check-circle me-1"></i>Approve
      </button>
    </form>
    <?php endif; ?>

    <?php if ($allowedActions['canReject']): ?>
    <!-- GM / Admin: pending → cancelled (rejected) -->
    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
      <i class="bi bi-x-circle me-1"></i>Reject
    </button>
    <?php endif; ?>

    <?php if ($allowedActions['canDispatch']): ?>
    <!-- Warehouse Manager: approved → in_transit -->
    <form method="POST" action="<?= url('transfers/' . $transfer['id'] . '/dispatch') ?>"
          onsubmit="return confirm('Dispatch this transfer?\n\nWarehouse stock will be deducted immediately.')">
      <?php csrf_input(); ?>
      <button class="btn btn-primary btn-sm">
        <i class="bi bi-truck me-1"></i>Dispatch
      </button>
    </form>
    <?php endif; ?>

    <?php if ($allowedActions['canReceive']): ?>
    <!-- Salesperson only: in_transit → confirm receipt / resubmit after rejection -->
    <a href="<?= url('transfers/' . $transfer['id'] . '/receive') ?>"
       class="btn btn-<?= $status === TransferModel::STATUS_GM_REJECTED ? 'warning' : 'success' ?> btn-sm">
      <i class="bi bi-<?= $status === TransferModel::STATUS_GM_REJECTED ? 'arrow-repeat' : 'box-arrow-in-down' ?> me-1"></i>
      <?= $status === TransferModel::STATUS_GM_REJECTED ? 'Re-submit Receiving' : 'Confirm Receipt' ?>
    </a>
    <?php endif; ?>

    <?php if ($status === TransferModel::STATUS_IN_TRANSIT && $userRole === ROLE_WAREHOUSE_MANAGER): ?>
    <span class="badge bg-primary text-white px-3 py-2" style="font-size:.8rem;">
      <i class="bi bi-hourglass-split me-1"></i>Awaiting Shop Receipt
    </span>
    <?php endif; ?>

    <?php if ($status === TransferModel::STATUS_DISCREPANCY_PENDING && $userRole === ROLE_WAREHOUSE_MANAGER): ?>
    <span class="badge bg-danger text-white px-3 py-2" style="font-size:.8rem;">
      <i class="bi bi-exclamation-triangle me-1"></i>Discrepancy Pending GM Approval
    </span>
    <?php endif; ?>

    <?php if ($status === TransferModel::STATUS_DISCREPANCY_PENDING && $userRole === ROLE_GENERAL_MANAGER): ?>
    <a href="<?= url('gm/transfers/' . $transfer['id'] . '/discrepancy-review') ?>"
       class="btn btn-warning btn-sm">
      <i class="bi bi-shield-check me-1"></i>Review Discrepancy
    </a>
    <?php endif; ?>

    <?php if ($status === TransferModel::STATUS_PENDING && $userRole === ROLE_WAREHOUSE_MANAGER): ?>
    <span class="badge bg-warning text-dark px-3 py-2" style="font-size:.8rem;">
      <i class="bi bi-clock me-1"></i>Awaiting GM Approval
    </span>
    <?php endif; ?>
    <?php if ($status === TransferModel::STATUS_FINAL_REJECTED): ?>
    <span class="badge bg-dark px-3 py-2" style="font-size:.8rem;">
      <i class="bi bi-slash-circle me-1"></i>Final Rejected — Closed
    </span>
    <?php endif; ?>
    <?php if ($allowedActions['canCancel']): ?>
    <form method="POST" action="<?= url('transfers/' . $transfer['id'] . '/cancel') ?>"
          onsubmit="return confirm('Cancel this transfer?\nStock reservations will be released.')">
      <?php csrf_input(); ?>
      <button class="btn btn-outline-danger btn-sm">
        <i class="bi bi-x-lg me-1"></i>Cancel
      </button>
    </form>
    <?php endif; ?>

  </div>
</div>

<!-- ── Workflow status trail ─────────────────────────────────── -->
<?php
$steps = [
    'pending'                   => ['Pending Approval',       'warning',   'bi-clock'],
    'approved'                  => ['Approved',               'info',      'bi-check-circle'],
    'in_transit'                => ['In Transit',             'primary',   'bi-truck'],
    'received'                  => ['Received',               'success',   'bi-check2-circle'],
    'discrepancy_pending'       => ['Pending GM Approval',    'danger',    'bi-exclamation-triangle'],
    'gm_approved'               => ['GM Approved',            'warning',   'bi-shield-check'],
    'gm_rejected'               => ['GM Rejected',            'secondary', 'bi-shield-x'],
    'received_with_discrepancy' => ['Completed w/ Discrepancy','success',  'bi-check2-circle'],
    'final_rejected'            => ['Final Rejected',         'dark',      'bi-slash-circle'],
    'discrepancy'               => ['Discrepancy',            'danger',    'bi-exclamation-triangle'],
    'cancelled'                 => ['Cancelled',              'secondary', 'bi-x-circle'],
];
$mainFlow  = ['pending','approved','in_transit','received'];
$disFlow   = ['pending','approved','in_transit','discrepancy_pending','gm_approved','received_with_discrepancy'];
$finalFlow = ['pending','approved','in_transit','discrepancy_pending','gm_rejected','final_rejected'];
$isDisFlow   = in_array($status, ['discrepancy_pending','gm_approved','gm_rejected','received_with_discrepancy'], true);
$isFinalFlow = $status === TransferModel::STATUS_FINAL_REJECTED;
$flowOrder = $isFinalFlow ? $finalFlow : ($isDisFlow ? $disFlow : $mainFlow);
$currentIdx = array_search($status, $flowOrder, true);
?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <div class="d-flex align-items-center gap-0 flex-wrap small">
      <?php foreach ($flowOrder as $si => $step):
        $active = ($step === $status);
        $done   = ($currentIdx !== false && $si < $currentIdx);
        $sc     = $active ? ($steps[$step][1] ?? 'secondary') : ($done ? 'success' : 'light');
        $tc     = ($active || $done) ? 'white' : 'muted';
      ?>
      <span class="badge bg-<?= $sc ?> text-<?= $tc ?> px-2 py-1">
        <i class="bi <?= $steps[$step][2] ?> me-1"></i><?= $steps[$step][0] ?>
      </span>
      <?php if ($si < count($flowOrder) - 1): ?>
      <i class="bi bi-chevron-right text-muted mx-1"></i>
      <?php endif; ?>
      <?php endforeach; ?>
      <?php if ($status === TransferModel::STATUS_GM_REJECTED): ?>
      <span class="ms-2 badge bg-secondary text-white px-2 py-1">
        <i class="bi bi-shield-x me-1"></i>GM Rejected — Awaiting Correction
      </span>
      <?php elseif ($status === TransferModel::STATUS_FINAL_REJECTED): ?>
      <span class="ms-2 badge bg-dark text-white px-2 py-1">
        <i class="bi bi-slash-circle-fill me-1"></i>Final Rejected — Transfer Permanently Closed
      </span>
      <?php elseif (in_array($status, ['discrepancy','cancelled'], true)): ?>
      <span class="ms-2 badge bg-<?= $steps[$status][1] ?? 'secondary' ?> text-white px-2 py-1">
        <i class="bi <?= $steps[$status][2] ?> me-1"></i><?= $steps[$status][0] ?>
      </span>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($status === TransferModel::STATUS_REJECTED): ?>
<div class="alert alert-danger d-flex gap-3 align-items-start mb-3">
  <i class="bi bi-x-circle-fill fs-4 flex-shrink-0 mt-1"></i>
  <div class="flex-grow-1">
    <div class="fw-bold fs-6">Transfer Rejected by General Manager</div>
    <?php
    // Load rejection details
    $db2 = \AAM\Core\Database::getInstance();
    $rejectedByName = '';
    $rejectedAt     = $transfer['rejected_at'] ?? '';
    $rejectionReason= $transfer['rejection_reason'] ?? '';
    if (!empty($transfer['rejected_by'])) {
        $rej = $db2->fetchOne(
            "SELECT CONCAT(first_name,' ',last_name) AS n FROM users WHERE id=:id",
            ['id' => (int)$transfer['rejected_by']]
        );
        $rejectedByName = $rej['n'] ?? '';
    }
    // Fallback: read from DB directly
    if (empty($rejectionReason)) {
        $raw = $db2->fetchOne(
            "SELECT rejection_reason, rejected_by, rejected_at FROM warehouse_transfers WHERE id=:id",
            ['id' => $transfer['id']]
        );
        $rejectionReason = $raw['rejection_reason'] ?? '';
        $rejectedAt      = $raw['rejected_at']      ?? $rejectedAt;
    }
    ?>
    <?php if ($rejectionReason): ?>
    <div class="mt-1 small">
      <strong>Rejection Reason:</strong> <?= e($rejectionReason) ?>
    </div>
    <?php endif; ?>
    <div class="mt-1 small text-muted">
      <?php if ($rejectedByName): ?>Rejected by: <strong><?= e($rejectedByName) ?></strong> &nbsp;|&nbsp;<?php endif; ?>
      <?php if ($rejectedAt): ?><?= date('d/m/Y H:i', strtotime($rejectedAt)) ?><?php endif; ?>
    </div>
    <?php if ($userRole === ROLE_WAREHOUSE_MANAGER): ?>
    <div class="mt-2 small">
      Stock reservations have been automatically released. Your available inventory has been restored.
    </div>
    <div class="mt-2">
      <a href="<?= url('transfers/create') ?>" class="btn btn-warning btn-sm">
        <i class="bi bi-plus-circle me-1"></i>Create a Corrected Transfer
      </a>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($status === TransferModel::STATUS_FINAL_REJECTED): ?>
<div class="alert alert-dark d-flex gap-3 align-items-start mb-3 border border-danger">
  <i class="bi bi-slash-circle-fill fs-4 flex-shrink-0 mt-1 text-danger"></i>
  <div class="flex-grow-1">
    <div class="fw-bold text-danger fs-6">Final Rejection — Transfer Permanently Closed</div>
    <div class="small mt-1">
      The receiving discrepancy for this transfer was rejected by the General Manager
      <strong><?= \AAM\Warehouse\Models\TransferModel::MAX_DISCREPANCY_REJECTIONS ?></strong> times.
      <strong>No further action is possible.</strong>
      Warehouse stock has been automatically restored.
    </div>
    <?php
    // Show last GM rejection reason from transfer record
    $finalReason = $transfer['discrepancy_gm_notes'] ?? '';
    if (empty($finalReason) && !empty($discrepancies)) {
        foreach ($discrepancies as $_d) {
            if (!empty($_d['gm_notes'])) { $finalReason = $_d['gm_notes']; break; }
        }
    }
    $rejCount = (int)($transfer['discrepancy_rejection_count'] ?? 0);
    ?>
    <?php if ($finalReason): ?>
    <div class="mt-1 small"><strong>Final Rejection Reason:</strong> <?= e($finalReason) ?></div>
    <?php endif; ?>
    <?php if ($rejCount): ?>
    <div class="mt-1 small text-muted">
      Rejection count: <strong><?= $rejCount ?></strong> / <?= \AAM\Warehouse\Models\TransferModel::MAX_DISCREPANCY_REJECTIONS ?>
    </div>
    <?php endif; ?>
    <?php if ((int)($transfer['discrepancy_stock_restored'] ?? 0)): ?>
    <div class="mt-1 small text-success">
      <i class="bi bi-check-circle me-1"></i>Warehouse stock has been restored.
    </div>
    <?php endif; ?>
    <?php if ($userRole === ROLE_WAREHOUSE_MANAGER): ?>
    <div class="mt-2 small text-muted">
      The shop has not received any stock from this transfer.
      If you need to re-supply the shop, please create a new transfer.
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── Summary Cards ─────────────────────────────────────────── -->
<div class="row g-3 mb-3">
  <?php
  $approvedByName = '';
  if (!empty($transfer['approved_by'])) {
      $db2 = \AAM\Core\Database::getInstance();
      $apv = $db2->fetchOne(
          "SELECT CONCAT(first_name,' ',last_name) AS n FROM users WHERE id = :id",
          ['id' => $transfer['approved_by']]
      );
      $approvedByName = $apv['n'] ?? "User #{$transfer['approved_by']}";
  }
  $cards = [
    ['Initiated By',  $transfer['initiated_by_name'] ?? '—',                    'secondary', 'bi-person'],
    ['Approved By',   $approvedByName ?: ($status === 'pending' ? 'Pending' : '—'), 'info',  'bi-person-check'],
    ['Total Items',   count($items),                                             'primary',   'bi-box'],
    ['Total Quantity',number_format((float)$transfer['total_quantity'], 1),      'dark',      'bi-hash'],
  ];
  foreach ($cards as [$lbl, $val, $col, $icon]):
  ?>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center h-100">
      <div class="card-body py-3">
        <i class="bi <?= $icon ?> text-<?= $col ?> mb-1 d-block"></i>
        <div class="fw-bold"><?= e((string)$val) ?></div>
        <div class="text-muted small"><?= $lbl ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Items Table ────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
  <div class="card-header fw-semibold small">
    <i class="bi bi-list-check me-2 text-primary"></i>Transfer Items
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Product</th>
            <th>Batch</th>
            <th>Expiry</th>
            <th>Requested</th>
            <th>Sent</th>
            <th>Received</th>
            <th class="pe-3">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item):
            $sm = ['pending'=>'warning','sent'=>'primary','received'=>'success','partial'=>'warning','discrepancy'=>'danger'];
            $sc = $sm[$item['item_status'] ?? ''] ?? 'secondary';
          ?>
          <tr>
            <td class="ps-3">
              <div class="fw-semibold small"><?= e($item['product_name'] ?? '—') ?></div>
              <div class="text-muted" style="font-size:.68rem;"><?= e($item['product_sku'] ?? '') ?></div>
            </td>
            <td class="small"><?= e($item['batch_number'] ?? '—') ?></td>
            <td class="small"><?= $item['expiry_date'] ? date('d/m/Y', strtotime($item['expiry_date'])) : '—' ?></td>
            <td class="small"><?= number_format((float)$item['quantity_requested'], 3) ?> <?= e($item['unit_abbr'] ?? '') ?></td>
            <td class="small fw-semibold text-primary"><?= number_format((float)$item['quantity_sent'], 3) ?> <?= e($item['unit_abbr'] ?? '') ?></td>
            <td class="small <?= (float)$item['quantity_received'] > 0 ? 'fw-semibold text-success' : 'text-muted' ?>">
              <?= (float)$item['quantity_received'] > 0 ? number_format((float)$item['quantity_received'], 3) : '—' ?>
            </td>
            <td class="pe-3">
              <span class="badge bg-<?= $sc ?>-subtle text-<?= $sc ?> border border-<?= $sc ?>-subtle">
                <?= ucfirst(str_replace('_', ' ', $item['item_status'] ?? '')) ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if (!empty($transfer['notes'])): ?>
<div class="card border-0 shadow-sm mt-3">
  <div class="card-body">
    <strong class="small">Notes:</strong>
    <p class="mb-0 small text-muted"><?= nl2br(e($transfer['notes'])) ?></p>
  </div>
</div>
<?php endif; ?>

<!-- ── Discrepancy Panel ──────────────────────────────────────── -->
<?php if ($showDiscrepancyPanel && !empty($discrepancies)): ?>
<div class="card border-0 shadow-sm mt-3">
  <div class="card-header fw-semibold small d-flex align-items-center justify-content-between">
    <span>
      <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>
      Receiving Discrepancies
    </span>
    <span class="badge bg-<?= TransferModel::statusBadge($status) ?>">
      <?= TransferModel::statusLabel($status) ?>
    </span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Discrepancy #</th>
            <th>Product</th>
            <th class="text-end">Sent</th>
            <th class="text-end">Received</th>
            <th class="text-end text-danger">Missing</th>
            <th>Reason</th>
            <th class="pe-3">GM Decision</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($discrepancies as $dis): ?>
          <tr>
            <td class="ps-3 small fw-semibold"><?= e($dis['discrepancy_number'] ?? '—') ?></td>
            <td class="small"><?= e($dis['product_name'] ?? '—') ?></td>
            <td class="text-end small fw-bold text-primary">
              <?= number_format((float)$dis['expected_quantity'], 3) ?>
            </td>
            <td class="text-end small fw-bold text-success">
              <?= number_format((float)$dis['actual_quantity'], 3) ?>
            </td>
            <td class="text-end small fw-bold text-danger">
              <?= number_format((float)$dis['variance_quantity'], 3) ?>
            </td>
            <td class="small text-muted" style="max-width:200px;">
              <?= e(mb_strimwidth($dis['reason'] ?? '—', 0, 80, '…')) ?>
            </td>
            <td class="pe-3 small">
              <?php if (!empty($dis['gm_action'])): ?>
              <span class="badge bg-<?= $dis['gm_action'] === 'approved' ? 'success' : 'danger' ?>">
                <?= $dis['gm_action'] === 'approved' ? 'Approved' : 'Rejected' ?>
              </span>
              <?php else: ?>
              <span class="badge bg-warning text-dark">Pending GM Review</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php
  // Show GM decision summary at bottom of panel
  if ($status === TransferModel::STATUS_RECEIVED_WITH_DISCREPANCY): ?>
  <div class="card-footer bg-success-subtle small text-success">
    <i class="bi bi-check-circle-fill me-1"></i>
    <strong>Approved by GM.</strong>
    Shop inventory updated with actual received quantities. Missing quantities recorded as discrepancy loss.
    <?php if (!empty($transfer['discrepancy_gm_notes'])): ?>
    — GM Notes: <?= e($transfer['discrepancy_gm_notes']) ?>
    <?php endif; ?>
  </div>
  <?php elseif ($status === TransferModel::STATUS_GM_REJECTED): ?>
  <div class="card-footer bg-danger-subtle small text-danger">
    <i class="bi bi-x-circle-fill me-1"></i>
    <strong>Rejected by GM.</strong>
    <?php if (!empty($transfer['discrepancy_gm_notes'])): ?>
    Reason: <?= e($transfer['discrepancy_gm_notes']) ?>
    <?php endif; ?>
    <?php if ($allowedActions['canReceive']): ?>
    <a href="<?= url('transfers/' . $transfer['id'] . '/receive') ?>"
       class="btn btn-warning btn-sm ms-3">
      <i class="bi bi-arrow-repeat me-1"></i>Re-submit Receiving
    </a>
    <?php endif; ?>
  </div>
  <?php elseif ($status === TransferModel::STATUS_DISCREPANCY_PENDING): ?>
  <div class="card-footer bg-warning-subtle small text-warning-emphasis">
    <i class="bi bi-hourglass-split me-1"></i>
    <strong>Waiting for GM approval.</strong>
    Shop inventory is blocked until the General Manager reviews this discrepancy.
  </div>
  <?php elseif ($status === TransferModel::STATUS_FINAL_REJECTED): ?>
  <div class="card-footer bg-dark small text-white border-top border-danger">
    <i class="bi bi-slash-circle-fill text-danger me-1"></i>
    <strong class="text-danger">Final Rejection.</strong>
    Transfer permanently closed after
    <?= TransferModel::MAX_DISCREPANCY_REJECTIONS ?> GM rejection attempts.
    Warehouse stock restored. Shop received <strong>0</strong> units from this transfer.
    <?php if (!empty($transfer['discrepancy_gm_notes'])): ?>
    <div class="mt-1">Last rejection reason: <?= e($transfer['discrepancy_gm_notes']) ?></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ── Reject Modal (GM only — transfer creation rejection) ──────── -->
<?php if ($allowedActions['canReject']): ?>
<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="<?= url('transfers/' . $transfer['id'] . '/reject') ?>"
            id="rejectTransferForm">
        <?php csrf_input(); ?>
        <div class="modal-header">
          <h6 class="modal-title fw-bold text-danger">
            <i class="bi bi-x-circle me-2"></i>Reject Transfer
          </h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-warning small py-2 mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Rejecting this transfer will:
            <ul class="mb-0 mt-1">
              <li>Set transfer status to <strong>Rejected</strong></li>
              <li>Automatically release all stock reservations</li>
              <li>Restore available warehouse inventory</li>
              <li>Notify the Warehouse Manager with your reason</li>
              <li>The Warehouse Manager can correct and create a new transfer</li>
            </ul>
          </div>
          <p class="small text-muted mb-3">
            Rejecting <strong><?= e($transfer['transfer_number'] ?? '') ?></strong>
            — <?= e($transfer['warehouse_name'] ?? '') ?> → <?= e($transfer['shop_name'] ?? '') ?>
            (<?= number_format((float)($transfer['total_quantity'] ?? 0), 1) ?> units)
            &nbsp;|&nbsp; Current status: <strong><?= TransferModel::statusLabel($transfer['status'] ?? '') ?></strong>
          </p>
          <label class="form-label small fw-semibold">
            Reason for Rejection <span class="text-danger">*</span>
          </label>
          <textarea name="reason"
                    class="form-control form-control-sm"
                    rows="3"
                    required
                    minlength="5"
                    placeholder="State clearly why this transfer is being rejected…"></textarea>
          <div class="form-text text-muted small mt-1">
            This reason will be recorded permanently and sent to the Warehouse Manager.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm"
                  data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger btn-sm">
            <i class="bi bi-x-circle me-1"></i>Confirm Rejection &amp; Release Stock
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
