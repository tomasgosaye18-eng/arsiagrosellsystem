<?php
/**
 * Receiving Queue — Phase 10 + Discrepancy Workflow
 * @var array  $pendingDispatches
 * @var array  $receivings
 * @var array  $pagination
 * @var array  $filters
 * @var array  $farms
 * @var string $tab
 * @var array  $warehouse
 * @var string $userRole
 */
declare(strict_types=1);
$pendingDispatches ??= [];
$receivings        ??= [];
$filters           ??= [];
$farms             ??= [];
$tab               ??= 'pending';

// Count receivings needing WM attention in the discrepancy workflow
$disActiveCount = 0;
foreach ($receivings as $r) {
    if (in_array($r['status'] ?? '', ['discrepancy_pending','gm_approved','gm_rejected'], true)) {
        $disActiveCount++;
    }
}
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold">
    <i class="bi bi-inbox-fill me-2 text-success"></i>Receiving
    <?php if (count($pendingDispatches) > 0): ?>
    <span class="badge bg-warning text-dark ms-1"><?= count($pendingDispatches) ?> pending</span>
    <?php endif; ?>
  </h5>
  <a href="<?= url('warehouse') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-speedometer2 me-1"></i>Overview
  </a>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'pending' ? 'active' : '' ?>"
       href="<?= url('warehouse/receiving?tab=pending') ?>">
      Pending Dispatches
      <?php if (count($pendingDispatches) > 0): ?>
      <span class="badge bg-warning text-dark ms-1"><?= count($pendingDispatches) ?></span>
      <?php endif; ?>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'discrepancy' ? 'active' : '' ?>"
       href="<?= url('warehouse/receiving?tab=discrepancy') ?>">
      <i class="bi bi-exclamation-triangle me-1"></i>Discrepancy Workflow
      <?php if ($disActiveCount > 0): ?>
      <span class="badge bg-danger ms-1"><?= $disActiveCount ?></span>
      <?php endif; ?>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'history' ? 'active' : '' ?>"
       href="<?= url('warehouse/receiving?tab=history') ?>">
      Receiving History
    </a>
  </li>
</ul>

<?php if ($tab === 'pending'): ?>
<!-- ══════════════ PENDING DISPATCHES TAB ══════════════ -->
<?php if (empty($pendingDispatches)): ?>
<div class="card border-0 shadow-sm">
  <div class="card-body text-center py-5 text-muted">
    <i class="bi bi-inbox display-6 d-block mb-2 opacity-25"></i>
    <p class="mb-0">No pending dispatches waiting to be received.</p>
    <small>When a farm marks a dispatch as "In Transit", it will appear here.</small>
  </div>
</div>
<?php else: ?>
<div class="alert alert-info d-flex gap-2 align-items-start small mb-3">
  <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
  <div>
    Click <strong>Receive</strong> to enter actual received quantities.
    If the quantity differs from what was dispatched, a
    <strong>discrepancy reason is required</strong> and the receiving will be sent to the
    <strong>General Manager for approval</strong> before inventory is updated.
  </div>
</div>
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Dispatch #</th>
            <th>Farm</th>
            <th>Products</th>
            <th>Total Qty</th>
            <th>Dispatch Date</th>
            <th>Sent By</th>
            <th class="pe-3 text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingDispatches as $d): ?>
          <tr>
            <td class="ps-3 fw-semibold small"><?= e($d['dispatch_number']) ?></td>
            <td class="small fw-semibold"><?= e($d['farm_name'] ?? '—') ?></td>
            <td class="small text-muted"><?= (int)($d['item_count'] ?? 0) ?> item(s)</td>
            <td class="small fw-bold"><?= number_format((float)($d['total_qty'] ?? 0), 3) ?></td>
            <td class="small"><?= $d['dispatch_date'] ? date('d/m/Y', strtotime($d['dispatch_date'])) : '—' ?></td>
            <td class="small text-muted"><?= e($d['initiated_by_name'] ?? '—') ?></td>
            <td class="pe-3 text-end">
              <a href="<?= url('warehouse/receiving/' . $d['id'] . '/confirm') ?>"
                 class="btn btn-success btn-sm">
                <i class="bi bi-check2-circle me-1"></i>Receive
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php elseif ($tab === 'discrepancy'): ?>
<!-- ══════════════ DISCREPANCY WORKFLOW TAB ══════════════ -->
<div class="alert alert-warning d-flex gap-2 align-items-start small mb-3">
  <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
  <div>
    <strong>Discrepancy Pending GM Review:</strong> The General Manager must approve before you
    can complete receiving and update inventory.<br>
    <strong>GM Approved:</strong> Click <em>Complete Receiving</em> to finalize —
    inventory will be updated with the <em>actual received quantity only</em>.<br>
    <strong>GM Rejected:</strong> Review the rejection reason and take corrective action.
  </div>
</div>
<?php
$disReceivings = array_filter($receivings, fn($r) =>
    in_array($r['status'] ?? '', ['discrepancy_pending','gm_approved','gm_rejected','discrepancy'], true)
);
?>
<?php if (empty($disReceivings)): ?>
<div class="card border-0 shadow-sm">
  <div class="card-body text-center py-5 text-muted">
    <i class="bi bi-check2-circle display-6 d-block mb-2 text-success opacity-50"></i>
    <p class="mb-0">No active discrepancy workflows at this time.</p>
  </div>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Receiving #</th>
            <th>Dispatch #</th>
            <th>Farm</th>
            <th>Expected</th>
            <th>Actual</th>
            <th>Missing</th>
            <th>Date</th>
            <th>Status</th>
            <th class="pe-3 text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($disReceivings as $r):
            $badge   = \AAM\Warehouse\Models\ReceivingModel::statusBadge($r['status']);
            $label   = \AAM\Warehouse\Models\ReceivingModel::statusLabel($r['status']);
            $missing = (float)$r['total_expected'] - (float)$r['total_received'];
          ?>
          <tr>
            <td class="ps-3 fw-semibold small">
              <a href="<?= url('warehouse/receiving/' . $r['id']) ?>"><?= e($r['receiving_number']) ?></a>
            </td>
            <td class="small text-muted"><?= e($r['dispatch_number'] ?? '—') ?></td>
            <td class="small fw-semibold"><?= e($r['farm_name'] ?? '—') ?></td>
            <td class="small fw-bold text-primary"><?= number_format((float)$r['total_expected'], 3) ?></td>
            <td class="small fw-bold text-success"><?= number_format((float)$r['total_received'], 3) ?></td>
            <td class="small fw-bold text-danger"><?= number_format($missing, 3) ?></td>
            <td class="small"><?= $r['received_date'] ? date('d/m/Y', strtotime($r['received_date'])) : '—' ?></td>
            <td><span class="badge bg-<?= $badge ?>"><?= $label ?></span></td>
            <td class="pe-3 text-end">
              <?php if ($r['status'] === 'gm_approved'): ?>
              <form method="POST"
                    action="<?= url('warehouse/receiving/' . $r['id'] . '/complete') ?>"
                    class="d-inline"
                    onsubmit="return confirm('Complete this receiving?\n\nInventory will be updated with ACTUAL received quantity (<?= number_format((float)$r['total_received'],3) ?>) only.\nThe missing <?= number_format($missing,3) ?> will NOT be added.\n\nContinue?')">
                <?php csrf_input(); ?>
                <button class="btn btn-success btn-sm">
                  <i class="bi bi-check2-circle me-1"></i>Complete Receiving
                </button>
              </form>
              <?php elseif ($r['status'] === 'discrepancy_pending'): ?>
              <span class="badge bg-warning text-dark px-2 py-1">
                <i class="bi bi-hourglass-split me-1"></i>Awaiting GM Review
              </span>
              <?php elseif ($r['status'] === 'gm_rejected'): ?>
              <a href="<?= url('warehouse/receiving/' . $r['id'] . '/resubmit') ?>"
                 class="btn btn-warning btn-sm">
                <i class="bi bi-arrow-repeat me-1"></i>Resubmit
              </a>
              <?php elseif ($r['status'] === 'final_rejected'): ?>
              <span class="badge bg-dark px-2 py-1">
                <i class="bi bi-slash-circle me-1"></i>Final Rejected
              </span>
              <?php endif; ?>
              <a href="<?= url('warehouse/receiving/' . $r['id']) ?>"
                 class="btn btn-outline-secondary btn-sm ms-1">View</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ══════════════ HISTORY TAB ══════════════ -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <input type="hidden" name="tab" value="history">
      <div class="col-12 col-md-3">
        <input type="search" name="search" class="form-control form-control-sm"
               placeholder="Receiving # or dispatch #"
               value="<?= e($filters['search'] ?? '') ?>">
      </div>
      <div class="col-6 col-md-2">
        <select name="status" class="form-select form-select-sm">
          <option value="">All Statuses</option>
          <?php foreach ([
            'pending'             => 'Pending',
            'received'            => 'Received',
            'partially_received'  => 'Partially Received',
            'discrepancy'         => 'Discrepancy',
            'discrepancy_pending' => 'Discrepancy — Pending GM',
            'gm_approved'         => 'GM Approved',
            'gm_rejected'         => 'GM Rejected',
            'final_rejected'      => 'Final Rejected',
            'rejected'            => 'Rejected',
            'cancelled'           => 'Cancelled',
          ] as $s => $sl): ?>
          <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>>
            <?= $sl ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <select name="farm_id" class="form-select form-select-sm">
          <option value="">All Farms</option>
          <?php foreach ($farms as $farm): ?>
          <option value="<?= $farm['id'] ?>" <?= ($filters['farm_id'] ?? '') == $farm['id'] ? 'selected' : '' ?>>
            <?= e($farm['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <input type="date" name="date_from" class="form-control form-control-sm"
               value="<?= e($filters['date_from'] ?? '') ?>">
      </div>
      <div class="col-6 col-md-2">
        <input type="date" name="date_to" class="form-control form-control-sm"
               value="<?= e($filters['date_to'] ?? '') ?>">
      </div>
      <div class="col-auto">
        <button class="btn btn-primary btn-sm">Filter</button>
        <a href="<?= url('warehouse/receiving?tab=history') ?>"
           class="btn btn-outline-secondary btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($receivings)): ?>
    <div class="text-center py-5 text-muted small">No receiving records found.</div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Receiving #</th>
            <th>Dispatch #</th>
            <th>Farm</th>
            <th>Expected</th>
            <th>Received</th>
            <th>Items</th>
            <th>Date</th>
            <th>Received By</th>
            <th class="pe-3">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($receivings as $r):
            $badge = \AAM\Warehouse\Models\ReceivingModel::statusBadge($r['status']);
            $label = \AAM\Warehouse\Models\ReceivingModel::statusLabel($r['status']);
          ?>
          <tr>
            <td class="ps-3 small fw-semibold">
              <a href="<?= url('warehouse/receiving/' . $r['id']) ?>"><?= e($r['receiving_number']) ?></a>
            </td>
            <td class="small text-muted"><?= e($r['dispatch_number'] ?? '—') ?></td>
            <td class="small"><?= e($r['farm_name'] ?? '—') ?></td>
            <td class="small"><?= number_format((float)$r['total_expected'], 3) ?></td>
            <td class="small fw-semibold text-success"><?= number_format((float)$r['total_received'], 3) ?></td>
            <td class="small text-muted"><?= (int)($r['item_count'] ?? 0) ?></td>
            <td class="small"><?= $r['received_date'] ? date('d/m/Y', strtotime($r['received_date'])) : '—' ?></td>
            <td class="small text-muted"><?= e($r['received_by_name'] ?? '—') ?></td>
            <td class="pe-3"><span class="badge bg-<?= $badge ?>"><?= $label ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
      <small class="text-muted">
        Showing <?= $pagination['from'] ?>–<?= $pagination['to'] ?>
        of <?= number_format($pagination['total']) ?> records
      </small>
      <nav>
        <ul class="pagination pagination-sm mb-0">
          <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
          <li class="page-item <?= $p === ($pagination['page'] ?? 1) ? 'active' : '' ?>">
            <a class="page-link"
               href="?tab=history&page=<?= $p ?>&<?= http_build_query(array_filter($filters)) ?>">
              <?= $p ?>
            </a>
          </li>
          <?php endfor; ?>
        </ul>
      </nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
