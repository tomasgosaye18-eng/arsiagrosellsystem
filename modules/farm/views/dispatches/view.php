<?php
/**
 * =========================================================
 * AAM — Farm Module — Dispatch Detail
 * =========================================================
 * @var \AAM\Core\View $view
 * @var array          $dispatch   includes $dispatch['items']
 * @var string         $userRole
 * =========================================================
 */
declare(strict_types=1);

use AAM\Farm\Models\FarmDispatchModel;
use AAM\Farm\Models\HarvestBatchModel;

$isAdmin    = in_array($userRole ?? '', [ROLE_ADMIN, ROLE_GENERAL_MANAGER], true);
$isFM       = ($userRole ?? '') === ROLE_FARM_MANAGER;
$isGM       = ($userRole ?? '') === ROLE_GENERAL_MANAGER;
$farmId     = (int)$dispatch['farm_id'];
$dispatchId = (int)$dispatch['id'];

$status    = $dispatch['status'] ?? '';
$ds        = FarmDispatchModel::getStatusBadgeClass($status);
$dl        = FarmDispatchModel::getStatusLabel($status);

// Security: Farm Manager can only dispatch AFTER GM approval (status = approved).
// Legacy 'pending' rows (pre-workflow) can also be dispatched for backward compat.
$canDispatch = $isFM && FarmDispatchModel::isDispatchable($status);
$canCancel   = FarmDispatchModel::isCancellable($status) && !$isGM;

// GM-specific actions: can approve/reject only when pending_approval
$canGMApprove = $isGM && $status === FarmDispatchModel::STATUS_PENDING_APPROVAL;
$canGMReject  = $isGM && $status === FarmDispatchModel::STATUS_PENDING_APPROVAL;

$totalQty  = array_sum(array_column($dispatch['items'] ?? [], 'quantity_dispatched'));
?>

<!-- ── Header ───────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">

<?php if ($status === FarmDispatchModel::STATUS_REJECTED): ?>
<!-- Rejection banner — full width, shown before header actions -->
<div class="col-12 mb-2">
  <div class="alert alert-danger d-flex gap-3 align-items-start mb-0">
    <i class="bi bi-x-circle-fill fs-4 flex-shrink-0 mt-1"></i>
    <div class="flex-grow-1">
      <div class="fw-bold">Dispatch Rejected by General Manager</div>
      <?php if (!empty($dispatch['rejection_reason'])): ?>
      <div class="small mt-1">
        <strong>Rejection Reason:</strong> <?= e($dispatch['rejection_reason']) ?>
      </div>
      <?php endif; ?>
      <?php if (!empty($dispatch['gm_reviewed_at'])): ?>
      <div class="small text-muted mt-1">
        <?php
        $gmName = '';
        if (!empty($dispatch['gm_reviewed_by'])) {
            $gmRow = \AAM\Core\Database::getInstance()->fetchOne(
                "SELECT CONCAT(first_name,' ',last_name) AS n FROM users WHERE id=:id",
                ['id' => (int)$dispatch['gm_reviewed_by']]
            );
            $gmName = $gmRow['n'] ?? '';
        }
        ?>
        <?= $gmName ? 'Rejected by: <strong>' . e($gmName) . '</strong> &nbsp;|&nbsp;' : '' ?>
        <?= date('d/m/Y H:i', strtotime($dispatch['gm_reviewed_at'])) ?>
      </div>
      <?php endif; ?>
      <div class="small mt-1 text-muted">
        No harvest stock has been deducted. You may create a new dispatch request after addressing the issue.
      </div>
      <?php if (in_array($userRole ?? '', [ROLE_FARM_MANAGER], true)): ?>
      <div class="mt-2">
        <a href="<?= url('farms/' . ($dispatch['farm_id'] ?? '') . '/dispatches/create') ?>"
           class="btn btn-warning btn-sm">
          <i class="bi bi-plus-circle me-1"></i>Create a Corrected Dispatch Request
        </a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-truck me-2 text-primary"></i>
      <?= e($dispatch['dispatch_number'] ?? "Dispatch #{$dispatchId}") ?>
      <span class="ms-2 badge bg-<?= $ds ?>-subtle text-<?= $ds ?>"><?= $dl ?></span>
    </h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('farm/dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= url('farms') ?>">Farms</a></li>
        <li class="breadcrumb-item">
          <a href="<?= url('farms/' . $farmId) ?>"><?= e($dispatch['farm_name'] ?? '') ?></a>
        </li>
        <li class="breadcrumb-item">
          <a href="<?= url('farms/' . $farmId . '/dispatches') ?>">Dispatch</a>
        </li>
        <li class="breadcrumb-item active"><?= e($dispatch['dispatch_number'] ?? $dispatchId) ?></li>
      </ol>
    </nav>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($canDispatch): ?>
    <form method="POST" action="<?= url('dispatches/' . $dispatchId . '/dispatch') ?>"
          onsubmit="return confirm('Mark this dispatch as In Transit? Farm harvest stock will be deducted.')">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <button type="submit" class="btn btn-primary btn-sm">
        <i class="bi bi-send-fill me-1"></i>Mark Dispatched
      </button>
    </form>
    <?php endif; ?>
    <?php if ($canCancel): ?>
    <form method="POST" action="<?= url('dispatches/' . $dispatchId . '/cancel') ?>"
          onsubmit="return confirm('Cancel this dispatch?\nHarvest quantities will be restored.')">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <button type="submit" class="btn btn-outline-danger btn-sm">
        <i class="bi bi-x-circle me-1"></i>Cancel
      </button>
    </form>
    <?php endif; ?>
    <?php if ($canGMApprove): ?>
    <button type="button" class="btn btn-success btn-sm"
            data-bs-toggle="modal" data-bs-target="#approveDispatchModal">
      <i class="bi bi-check-lg me-1"></i>Approve
    </button>
    <?php endif; ?>
    <?php if ($canGMReject): ?>
    <button type="button" class="btn btn-danger btn-sm"
            data-bs-toggle="modal" data-bs-target="#rejectDispatchModal">
      <i class="bi bi-x-lg me-1"></i>Reject
    </button>
    <?php endif; ?>
  </div>
</div>

<?php $view->flash(); ?>

<?php // ── Approval Status Banners ────────────────────────────── ?>
<?php if ($status === FarmDispatchModel::STATUS_FINAL_REJECTED): ?>
<div class="alert alert-dark d-flex gap-3 align-items-start mb-3 border border-danger">
  <i class="bi bi-slash-circle-fill fs-4 flex-shrink-0 mt-1 text-danger"></i>
  <div class="flex-grow-1">
    <div class="fw-bold text-danger">Final Rejection — Transfer Permanently Closed</div>
    <div class="small mt-1">
      The receiving discrepancy for this dispatch was rejected by the General Manager
      <?= \AAM\Warehouse\Models\ReceivingModel::MAX_REJECTIONS ?> times.
      <strong>Resubmission is no longer allowed.</strong>
      The dispatched stock has been automatically returned to the Farm.
    </div>
    <?php
    // Pull last rejection reason from the receiving record linked to this dispatch
    $db3 = \AAM\Core\Database::getInstance();
    $rcv = $db3->fetchOne(
        "SELECT wr.receiving_number, wr.rejection_count,
                GROUP_CONCAT(wd.gm_notes ORDER BY wd.gm_reviewed_at DESC SEPARATOR '|||') AS reasons
         FROM warehouse_receiving wr
         LEFT JOIN warehouse_discrepancies wd
               ON wd.reference_type = 'receiving' AND wd.reference_id = wr.id
         WHERE wr.dispatch_id = :did
         GROUP BY wr.id
         LIMIT 1",
        ['did' => $dispatchId]
    );
    $lastReason = '';
    if ($rcv && !empty($rcv['reasons'])) {
        $parts      = explode('|||', $rcv['reasons']);
        $lastReason = trim($parts[0] ?? '');
    }
    ?>
    <?php if ($lastReason): ?>
    <div class="mt-1 small"><strong>Final Rejection Reason:</strong> <?= e($lastReason) ?></div>
    <?php endif; ?>
    <?php if (!empty($rcv['rejection_count'])): ?>
    <div class="mt-1 small text-muted">
      Rejection count: <?= (int)$rcv['rejection_count'] ?> / <?= \AAM\Warehouse\Models\ReceivingModel::MAX_REJECTIONS ?>
    </div>
    <?php endif; ?>
    <div class="mt-2 small text-success">
      <i class="bi bi-check-circle me-1"></i>Farm harvest stock has been restored.
    </div>
  </div>
</div>
<?php elseif ($status === FarmDispatchModel::STATUS_PENDING_APPROVAL): ?>
<div class="alert alert-warning border-0 shadow-sm mb-3 d-flex gap-3 align-items-start">
  <i class="bi bi-hourglass-split fs-5 text-warning flex-shrink-0 mt-1"></i>
  <div>
    <strong>Awaiting General Manager Approval</strong><br>
    <span class="small text-muted">
      This dispatch has been submitted and is awaiting GM review.
      <?= $isFM ? 'You will be notified once the GM approves or rejects it. Dispatch is blocked until then.' : 'You can approve or reject using the buttons above.' ?>
    </span>
  </div>
</div>
<?php elseif ($status === FarmDispatchModel::STATUS_APPROVED): ?>
<div class="alert alert-success border-0 shadow-sm mb-3 d-flex gap-3 align-items-start">
  <i class="bi bi-check-circle-fill fs-5 text-success flex-shrink-0 mt-1"></i>
  <div>
    <strong>Approved by General Manager</strong><br>
    <span class="small text-muted">
      <?php if (!empty($dispatch['gm_reviewed_by'])): ?>
      Reviewed on <?= !empty($dispatch['gm_reviewed_at']) ? date('d M Y H:i', strtotime($dispatch['gm_reviewed_at'])) : '—' ?>.
      <?php endif; ?>
      <?= $isFM ? '<strong>You can now mark this dispatch as sent.</strong> Farm stock will be deducted when you click "Mark Dispatched".' : 'The Farm Manager can now physically dispatch the goods.' ?>
    </span>
    <?php if (!empty($dispatch['gm_notes'])): ?>
    <div class="mt-1 small"><em class="text-muted">GM note: <?= e($dispatch['gm_notes']) ?></em></div>
    <?php endif; ?>
  </div>
</div>
<?php elseif ($status === FarmDispatchModel::STATUS_REJECTED): ?>
<div class="alert alert-danger border-0 shadow-sm mb-3 d-flex gap-3 align-items-start">
  <i class="bi bi-x-circle-fill fs-5 text-danger flex-shrink-0 mt-1"></i>
  <div>
    <strong>Rejected by General Manager</strong><br>
    <?php if (!empty($dispatch['rejection_reason'])): ?>
    <div class="small mt-1"><strong>Reason:</strong> <?= e($dispatch['rejection_reason']) ?></div>
    <?php endif; ?>
    <div class="small text-muted mt-1">
      No stock has been deducted. This dispatch cannot be sent.
      <?= $isFM ? 'Please create a new dispatch request if needed.' : '' ?>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="row g-3">

  <!-- ── Traceability Flow Card ────────────────────────────── -->
  <div class="col-12">
    <div class="card border-0 shadow-sm theme-tint-success">
      <div class="card-body py-3 px-4">
        <div class="d-flex align-items-center flex-wrap gap-2">

          <!-- FARM -->
          <div class="text-center">
            <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center
                        justify-content-center mx-auto mb-1"
                 style="width:44px;height:44px;">
              <i class="bi bi-flower1 text-success fs-5"></i>
            </div>
            <div class="fw-bold small"><?= e($dispatch['farm_name'] ?? '—') ?></div>
            <div class="text-muted" style="font-size:.68rem;">FARM</div>
          </div>

          <i class="bi bi-arrow-right text-muted fs-5 mx-2"></i>

          <!-- DISPATCH -->
          <div class="text-center">
            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center
                        justify-content-center mx-auto mb-1"
                 style="width:44px;height:44px;">
              <i class="bi bi-truck text-primary fs-5"></i>
            </div>
            <div class="fw-bold small font-monospace">
              <?= e($dispatch['dispatch_number'] ?? '—') ?>
            </div>
            <div class="text-muted" style="font-size:.68rem;">DISPATCH</div>
          </div>

          <i class="bi bi-arrow-right text-muted fs-5 mx-2"></i>

          <!-- WAREHOUSE -->
          <div class="text-center">
            <div class="rounded-circle bg-secondary bg-opacity-10 d-flex align-items-center
                        justify-content-center mx-auto mb-1"
                 style="width:44px;height:44px;">
              <i class="bi bi-building text-secondary fs-5"></i>
            </div>
            <div class="fw-bold small"><?= e($dispatch['warehouse_name'] ?? '—') ?></div>
            <div class="text-muted" style="font-size:.68rem;">WAREHOUSE</div>
          </div>

          <div class="ms-auto d-none d-md-block">
            <div class="text-muted small">Total Quantity</div>
            <div class="fw-bold fs-4 text-primary"><?= number_format($totalQty, 2) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Dispatch Details ─────────────────────────────────── -->
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header">
        <span class="fw-semibold small">
          <i class="bi bi-info-circle me-2 text-primary"></i>Dispatch Details
        </span>
      </div>
      <div class="card-body">
        <div class="row g-2">
          <?php
          $fields = [
            ['Dispatch Number', e($dispatch['dispatch_number'] ?? '—')],
            ['Source Farm',     e($dispatch['farm_name'] ?? '—')],
            ['Destination',     e($dispatch['warehouse_name'] ?? '—')],
            ['Status',          "<span class=\"badge bg-{$ds}-subtle text-{$ds}\">{$dl}</span>"],
            ['Dispatch Date',   !empty($dispatch['dispatch_date']) ? date('d M Y', strtotime($dispatch['dispatch_date'])) : '—'],
            ['Expected Arrival',!empty($dispatch['expected_arrival_date']) ? date('d M Y', strtotime($dispatch['expected_arrival_date'])) : '—'],
            ['Initiated By',    e($dispatch['initiated_by_name'] ?? '—')],
            ['GM Review',       !empty($dispatch['gm_reviewed_at']) ? date('d M Y H:i', strtotime($dispatch['gm_reviewed_at'])) : '—'],
            ['Received By',     !empty($dispatch['received_by_name']) ? e($dispatch['received_by_name']) : '—'],
            ['Received Date',   !empty($dispatch['received_date']) ? date('d M Y', strtotime($dispatch['received_date'])) : '—'],
            ['Created',         !empty($dispatch['created_at']) ? date('d M Y H:i', strtotime($dispatch['created_at'])) : '—'],
          ];
          foreach ($fields as [$label, $value]):
          ?>
          <div class="col-sm-6">
            <div class="text-muted small"><?= $label ?></div>
            <div class="fw-semibold small"><?= $value ?></div>
          </div>
          <?php endforeach; ?>
        </div>

        <?php if (!empty($dispatch['rejection_reason'])): ?>
        <hr>
        <div class="text-danger small mb-1 fw-semibold"><i class="bi bi-x-circle me-1"></i>Rejection Reason</div>
        <div class="small text-danger"><?= nl2br(e($dispatch['rejection_reason'])) ?></div>
        <?php endif; ?>

        <?php if (!empty($dispatch['notes'])): ?>
        <hr>
        <div class="text-muted small mb-1">Notes</div>
        <div class="small"><?= nl2br(e($dispatch['notes'])) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── Receiving Status ─────────────────────────────────── -->
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header">
        <span class="fw-semibold small">
          <i class="bi bi-inbox-fill me-2 text-success"></i>Receiving Status
        </span>
      </div>
      <div class="card-body">
        <?php
        $statusSteps = [
          ['pending_approval', 'Pending GM Approval', FarmDispatchModel::STATUS_PENDING_APPROVAL],
          ['approved',         'Approved',            FarmDispatchModel::STATUS_APPROVED],
          ['in_transit',       'In Transit',          FarmDispatchModel::STATUS_IN_TRANSIT],
          ['received',         'Received',            FarmDispatchModel::STATUS_RECEIVED],
        ];
        $currentStatus = $dispatch['status'] ?? '';
        $statusOrder = [
            'pending_approval' => 0,
            'approved'         => 1,
            'pending'          => 1,   // legacy: treat same as approved for display
            'in_transit'       => 2,
            'received'         => 3,
            'partially_received'            => 3,
            'received_with_discrepancy'     => 3,
            'final_rejected'                => 3,
        ];
        $currentOrder  = $statusOrder[$currentStatus] ?? -1;
        $isRejected    = $currentStatus === FarmDispatchModel::STATUS_REJECTED;
        $isCancelled   = $currentStatus === FarmDispatchModel::STATUS_CANCELLED;
        $isFinalRejected = $currentStatus === FarmDispatchModel::STATUS_FINAL_REJECTED;
        ?>
        <?php foreach ($statusSteps as $i => [$key, $label, $const]): ?>
        <?php $stepOrder = $statusOrder[$key] ?? $i; ?>
        <div class="d-flex align-items-center gap-3 <?= $i > 0 ? 'mt-3' : '' ?>">
          <?php if (($isRejected || $isCancelled) && $i >= 1): ?>
          <div class="rounded-circle bg-danger bg-opacity-10 d-flex align-items-center
                      justify-content-center flex-shrink-0"
               style="width:32px;height:32px;">
            <i class="bi bi-x-circle text-danger small"></i>
          </div>
          <div>
            <div class="small text-muted"><?= $label ?></div>
          </div>
          <?php elseif ($isRejected && $i === 0): ?>
          <div class="rounded-circle bg-danger bg-opacity-10 d-flex align-items-center
                      justify-content-center flex-shrink-0"
               style="width:32px;height:32px;">
            <i class="bi bi-x-circle-fill text-danger small"></i>
          </div>
          <div>
            <div class="small fw-semibold">Rejected</div>
            <span class="badge bg-danger-subtle text-danger small">Blocked</span>
          </div>
          <?php elseif ($isCancelled && $i === 0): ?>
          <div class="rounded-circle bg-secondary bg-opacity-10 d-flex align-items-center
                      justify-content-center flex-shrink-0"
               style="width:32px;height:32px;">
            <i class="bi bi-slash-circle text-secondary small"></i>
          </div>
          <div>
            <div class="small fw-semibold">Cancelled</div>
            <span class="badge bg-secondary-subtle text-secondary small">Cancelled</span>
          </div>
          <?php elseif ($currentOrder >= $stepOrder): ?>
          <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center
                      justify-content-center flex-shrink-0"
               style="width:32px;height:32px;">
            <i class="bi bi-check-circle-fill text-success small"></i>
          </div>
          <div>
            <div class="small fw-semibold"><?= $label ?></div>
            <?php if ($currentStatus === $const || ($key === 'approved' && $currentStatus === 'pending')): ?>
            <span class="badge bg-success-subtle text-success small">Current</span>
            <?php endif; ?>
          </div>
          <?php else: ?>
          <div class="rounded-circle bg-secondary bg-opacity-10 d-flex align-items-center
                      justify-content-center flex-shrink-0"
               style="width:32px;height:32px;">
            <i class="bi bi-circle text-secondary small"></i>
          </div>
          <div>
            <div class="small text-muted"><?= $label ?></div>
          </div>
          <?php endif; ?>
        </div>
        <?php if ($i < count($statusSteps) - 1): ?>
        <div class="ms-4 ps-2" style="border-left:2px dashed #dee2e6;height:16px;margin-left:15px!important;"></div>
        <?php endif; ?>
        <?php endforeach; ?>

        <?php if ($currentStatus === FarmDispatchModel::STATUS_PARTIALLY_RECEIVED): ?>
        <div class="mt-3 alert alert-warning py-2 small mb-0">
          <i class="bi bi-exclamation-triangle me-1"></i>
          <strong>Partially received.</strong>
          Not all items were received at the warehouse.
        </div>
        <?php endif; ?>
        <?php if ($currentStatus === FarmDispatchModel::STATUS_RECEIVED_WITH_DISCREPANCY): ?>
        <div class="mt-3 alert alert-danger py-2 small mb-0">
          <i class="bi bi-exclamation-octagon me-1"></i>
          <strong>Received with discrepancy.</strong>
          Quantities received did not match what was dispatched.
        </div>
        <?php endif; ?>
        <?php if ($isFinalRejected): ?>
        <div class="mt-3 alert alert-dark py-2 small mb-0 border border-danger">
          <i class="bi bi-slash-circle-fill text-danger me-1"></i>
          <strong class="text-danger">Final Rejected.</strong>
          Receiving discrepancy rejected by GM twice.
          Farm harvest stock has been restored automatically.
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<!-- ── Dispatch Items ────────────────────────────────────── -->
<div class="card border-0 shadow-sm mt-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span class="fw-semibold small">
      <i class="bi bi-list-ul me-2 text-primary"></i>Dispatch Items
    </span>
    <span class="badge bg-primary-subtle text-primary">
      <?= count($dispatch['items'] ?? []) ?> item(s)
    </span>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="ps-3 small">Product</th>
          <th class="small">Batch</th>
          <th class="small">Harvest Date</th>
          <th class="small">Quality</th>
          <th class="text-end small">Dispatched</th>
          <th class="text-end small pe-3">Received</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($dispatch['items'])): ?>
        <tr>
          <td colspan="6" class="text-center py-3 text-muted small">No items.</td>
        </tr>
        <?php else: ?>
        <?php foreach ($dispatch['items'] as $item):
          $qc = HarvestBatchModel::getQualityBadgeClass($item['quality_grade'] ?? '');
        ?>
        <tr>
          <td class="ps-3 small fw-semibold"><?= e($item['product_name'] ?? '—') ?></td>
          <td class="small font-monospace text-muted"><?= e($item['batch_number'] ?? '—') ?></td>
          <td class="small text-muted">
            <?= !empty($item['harvest_date']) ? date('d M Y', strtotime($item['harvest_date'])) : '—' ?>
          </td>
          <td>
            <span class="badge bg-<?= $qc ?>-subtle text-<?= $qc ?> small">
              <?= e($item['quality_grade'] ?? '—') ?>
            </span>
          </td>
          <td class="text-end small fw-semibold">
            <?= number_format((float)($item['quantity_dispatched'] ?? 0), 2) ?>
            <span class="text-muted"><?= e($item['unit_abbr'] ?? '') ?></span>
          </td>
          <td class="text-end small pe-3">
            <?php if ($item['quantity_received'] !== null): ?>
            <span class="<?= (float)$item['quantity_received'] < (float)$item['quantity_dispatched'] ? 'text-warning fw-bold' : 'text-success fw-bold' ?>">
              <?= number_format((float)$item['quantity_received'], 2) ?>
            </span>
            <?php else: ?>
            <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <tr class="table-light">
          <td colspan="4" class="ps-3 small fw-semibold text-end">Total:</td>
          <td class="text-end small fw-bold"><?= number_format($totalQty, 2) ?></td>
          <td class="pe-3"></td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($canGMApprove): ?>
<!-- ── GM Approve Modal ──────────────────────────────────── -->
<div class="modal fade" id="approveDispatchModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= url('dispatches/' . $dispatchId . '/approve') ?>">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i>Approve Dispatch</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="small mb-3">
            You are approving
            <strong><?= e($dispatch['dispatch_number'] ?? '') ?></strong>
            from <strong><?= e($dispatch['farm_name'] ?? '') ?></strong>
            → <strong><?= e($dispatch['warehouse_name'] ?? '') ?></strong>.<br>
            The Farm Manager will be able to physically dispatch after this approval.
            <br><span class="text-muted">Farm stock is deducted only when they mark it as dispatched.</span>
          </p>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Note (optional)</label>
            <input type="text" name="gm_notes" class="form-control form-control-sm"
                   placeholder="Optional note for the Farm Manager…">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success btn-sm">
            <i class="bi bi-check-lg me-1"></i>Confirm Approval
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($canGMReject): ?>
<!-- ── GM Reject Modal ───────────────────────────────────── -->
<div class="modal fade" id="rejectDispatchModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="<?= url('dispatches/' . $dispatchId . '/reject') ?>">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title"><i class="bi bi-x-circle me-2"></i>Reject Dispatch</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="small mb-3">
            You are rejecting
            <strong><?= e($dispatch['dispatch_number'] ?? '') ?></strong>
            from <strong><?= e($dispatch['farm_name'] ?? '') ?></strong>.<br>
            <span class="text-danger fw-semibold">No stock will be deducted.</span>
            The Farm Manager will see your rejection reason.
          </p>
          <div class="mb-3">
            <label class="form-label small fw-semibold">
              Reason for Rejection <span class="text-danger">*</span>
            </label>
            <textarea name="rejection_reason" class="form-control" rows="4"
                      placeholder="Provide a clear reason (e.g. incorrect destination, quantity issue, quality concern…)"
                      required></textarea>
            <div class="form-text">This is mandatory and will be visible to the Farm Manager.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger btn-sm">
            <i class="bi bi-x-lg me-1"></i>Confirm Rejection
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
