<?php
/**
 * GM — Pending Transfer Approvals
 * @var array $pendingApprovals        Transfers awaiting GM approval (status=pending)
 * @var array $approvedNotDispatched   Transfers approved but not yet dispatched (GM can still reject)
 * @var array $pipeline
 * @var int   $pendingDiscrepancyCount
 */
declare(strict_types=1);
$pendingApprovals        ??= [];
$approvedNotDispatched   ??= [];
$pipeline                ??= [];
$pendingDiscrepancyCount ??= 0;
$n  = fn($v,$d=0) => is_numeric($v) ? number_format((float)$v,$d) : '—';
$dt = fn($v) => $v ? date('d/m/Y H:i', strtotime($v)) : '—';
?>
<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h5 class="fw-bold mb-0"><i class="bi bi-hourglass-split me-2 text-warning"></i>Transfer Approvals &amp; Active Transfers</h5>
    <small class="text-muted">Review transfers awaiting your decision, or reject approved transfers before dispatch.</small>
  </div>
  <a href="<?= url('gm/transfers') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-list me-1"></i>All Transfers</a>
</div>

<?php if ($pendingDiscrepancyCount > 0): ?>
<!-- Receiving Discrepancy Alert -->
<div class="alert alert-danger d-flex align-items-center gap-3 mb-4">
  <i class="bi bi-exclamation-triangle-fill fs-4 flex-shrink-0"></i>
  <div class="flex-grow-1">
    <div class="fw-bold">
      <?= $pendingDiscrepancyCount ?> transfer receiving discrepanc<?= $pendingDiscrepancyCount > 1 ? 'ies require' : 'y requires' ?> your approval
    </div>
    <div class="small">Shop inventory is blocked until you review and approve or reject each discrepancy.</div>
  </div>
  <a href="<?= url('gm/transfers/discrepancies/pending-review') ?>"
     class="btn btn-danger btn-sm flex-shrink-0">
    <i class="bi bi-shield-exclamation me-1"></i>
    Review Now
    <span class="badge bg-white text-danger ms-1"><?= $pendingDiscrepancyCount ?></span>
  </a>
</div>
<?php endif; ?>

<!-- Pipeline summary -->
<div class="row g-2 mb-4">
  <?php foreach ([
    ['Pending Approval',$pipeline['pending_approval'] ?? 0,'warning'],
    ['Approved',        $pipeline['approved'] ?? 0,        'success'],
    ['In Transit',      $pipeline['in_transit'] ?? 0,      'info'],
  ] as [$lbl,$cnt,$col]): ?>
  <div class="col-4 col-md-2">
    <div class="card border-0 shadow-sm text-center" style="border-top:3px solid var(--bs-<?= $col ?>)!important;">
      <div class="card-body py-2">
        <div class="fw-bold fs-5 text-<?= $col ?>"><?= $n($cnt) ?></div>
        <div class="text-muted" style="font-size:.72rem;"><?= $lbl ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ═══ SECTION 1: Pending GM Approval ═══ -->
<h6 class="fw-bold text-warning mb-2 mt-3">
  <i class="bi bi-hourglass-split me-2"></i>Awaiting Your Approval
  <?php if (!empty($pendingApprovals)): ?>
  <span class="badge bg-warning text-dark ms-1"><?= count($pendingApprovals) ?></span>
  <?php endif; ?>
</h6>

<?php if (empty($pendingApprovals)): ?>
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body text-center py-4 text-muted">
    <i class="bi bi-check-circle-fill d-block fs-3 mb-2 text-success opacity-50"></i>
    <strong>No transfers awaiting approval.</strong>
  </div>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm mb-4" style="border-left:4px solid #ffc107!important;">
  <div class="card-header bg-warning bg-opacity-10 py-2 fw-semibold small">
    <i class="bi bi-hourglass-split me-2 text-warning"></i>
    <?= count($pendingApprovals) ?> Transfer<?= count($pendingApprovals) > 1 ? 's' : '' ?> Awaiting Your Decision
  </div>
  <div class="card-body p-0">
    <?php foreach ($pendingApprovals as $t): ?>
    <div class="border-bottom p-3">
      <div class="row align-items-center g-2">
        <div class="col-md-3">
          <div class="fw-semibold small">
            <a href="<?= url('transfers/'.$t['id']) ?>" class="text-decoration-none"><?= e($t['transfer_number'] ?? '#'.$t['id']) ?></a>
          </div>
          <div class="text-muted" style="font-size:.72rem;">
            <?php $days = (int)($t['days_waiting'] ?? 0); ?>
            Waiting <span class="fw-semibold <?= $days > 2 ? 'text-danger' : ($days > 0 ? 'text-warning' : 'text-success') ?>">
              <?= $days === 0 ? 'since today' : $days.'d' ?>
            </span>
          </div>
        </div>
        <div class="col-md-4">
          <div class="small text-muted">From: <span class="fw-semibold text-dark"><?= e($t['warehouse_name'] ?? '—') ?></span></div>
          <div class="small text-muted">To: <span class="fw-semibold text-dark"><?= e($t['shop_name'] ?? '—') ?></span></div>
          <div class="text-muted" style="font-size:.68rem;">by <?= e($t['initiated_by_name'] ?? '—') ?></div>
        </div>
        <div class="col-md-2">
          <div class="small"><?= $n($t['total_items'] ?? 0) ?> item<?= ($t['total_items'] ?? 0) != 1 ? 's' : '' ?></div>
          <div class="small fw-semibold"><?= $n($t['total_quantity'] ?? 0, 1) ?> units</div>
        </div>
        <div class="col-md-3 d-flex gap-2 justify-content-md-end flex-wrap">
          <a href="<?= url('transfers/'.$t['id']) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-eye me-1"></i>Review
          </a>
          <form method="POST" action="<?= url('gm/transfers/'.$t['id'].'/approve') ?>" class="d-inline"
                onsubmit="return confirm('Approve transfer <?= e($t['transfer_number'] ?? '#'.$t['id']) ?>?\nThe Warehouse Manager will be able to dispatch it.')">
            <?php csrf_input(); ?>
            <button class="btn btn-success btn-sm"><i class="bi bi-check-lg me-1"></i>Approve</button>
          </form>
          <button type="button" class="btn btn-danger btn-sm"
                  data-bs-toggle="modal" data-bs-target="#rejectModal<?= $t['id'] ?>">
            <i class="bi bi-x-lg me-1"></i>Reject
          </button>
        </div>
      </div>
    </div>
    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal<?= $t['id'] ?>" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form method="POST" action="<?= url('gm/transfers/'.$t['id'].'/reject') ?>">
            <?php csrf_input(); ?>
            <div class="modal-header">
              <h6 class="modal-title fw-bold text-danger"><i class="bi bi-x-circle me-2"></i>Reject — <?= e($t['transfer_number'] ?? '#'.$t['id']) ?></h6>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <p class="small text-muted mb-2">
                From <strong><?= e($t['warehouse_name'] ?? '') ?></strong>
                to <strong><?= e($t['shop_name'] ?? '') ?></strong>
                &mdash; <?= $n($t['total_quantity'] ?? 0, 1) ?> units
              </p>
              <div class="alert alert-warning small py-2 mb-2">
                Stock reservations will be released and warehouse inventory restored.
              </div>
              <label class="form-label small fw-semibold">Reason for rejection <span class="text-danger">*</span></label>
              <textarea name="reason" class="form-control form-control-sm" rows="3"
                        placeholder="State the reason for rejecting this transfer…" required minlength="5"></textarea>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-danger btn-sm">
                <i class="bi bi-x-circle me-1"></i>Confirm Rejection &amp; Release Stock
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ═══ SECTION 2: Approved — Not Yet Dispatched (GM can still reject) ═══ -->
<?php if (!empty($approvedNotDispatched)): ?>
<h6 class="fw-bold text-success mb-2 mt-3">
  <i class="bi bi-check-circle me-2"></i>Approved — Awaiting Dispatch
  <span class="badge bg-success ms-1"><?= count($approvedNotDispatched) ?></span>
  <small class="text-muted fw-normal ms-2" style="font-size:.75rem;">GM can still reject before the Warehouse Manager dispatches</small>
</h6>
<div class="card border-0 shadow-sm mb-4" style="border-left:4px solid #198754!important;">
  <div class="card-header bg-success bg-opacity-10 py-2 fw-semibold small">
    <i class="bi bi-truck me-2 text-success"></i>
    <?= count($approvedNotDispatched) ?> Approved Transfer<?= count($approvedNotDispatched) > 1 ? 's' : '' ?> — Awaiting Dispatch
  </div>
  <div class="card-body p-0">
    <?php foreach ($approvedNotDispatched as $t): ?>
    <div class="border-bottom p-3">
      <div class="row align-items-center g-2">
        <div class="col-md-3">
          <div class="fw-semibold small">
            <a href="<?= url('transfers/'.$t['id']) ?>" class="text-decoration-none"><?= e($t['transfer_number'] ?? '#'.$t['id']) ?></a>
          </div>
          <div class="text-muted" style="font-size:.72rem;">
            Approved
            <?php $days = (int)($t['days_since_approval'] ?? 0); ?>
            <?= $days === 0 ? 'today' : $days.'d ago' ?>
            <?php if (!empty($t['approved_by_name'])): ?>
            by <?= e($t['approved_by_name']) ?>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-md-4">
          <div class="small text-muted">From: <span class="fw-semibold text-dark"><?= e($t['warehouse_name'] ?? '—') ?></span></div>
          <div class="small text-muted">To: <span class="fw-semibold text-dark"><?= e($t['shop_name'] ?? '—') ?></span></div>
          <div class="text-muted" style="font-size:.68rem;">Created by <?= e($t['initiated_by_name'] ?? '—') ?></div>
        </div>
        <div class="col-md-2">
          <div class="small"><?= $n($t['total_items'] ?? 0) ?> item<?= ($t['total_items'] ?? 0) != 1 ? 's' : '' ?></div>
          <div class="small fw-semibold"><?= $n($t['total_quantity'] ?? 0, 1) ?> units</div>
        </div>
        <div class="col-md-3 d-flex gap-2 justify-content-md-end flex-wrap">
          <a href="<?= url('transfers/'.$t['id']) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-eye me-1"></i>View
          </a>
          <button type="button" class="btn btn-danger btn-sm"
                  data-bs-toggle="modal" data-bs-target="#rejectApprovedModal<?= $t['id'] ?>">
            <i class="bi bi-x-lg me-1"></i>Reject
          </button>
        </div>
      </div>
    </div>
    <!-- Reject Modal for approved transfer -->
    <div class="modal fade" id="rejectApprovedModal<?= $t['id'] ?>" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form method="POST" action="<?= url('gm/transfers/'.$t['id'].'/reject') ?>">
            <?php csrf_input(); ?>
            <div class="modal-header">
              <h6 class="modal-title fw-bold text-danger">
                <i class="bi bi-x-circle me-2"></i>Reject Approved Transfer — <?= e($t['transfer_number'] ?? '#'.$t['id']) ?>
              </h6>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <p class="small text-muted mb-2">
                From <strong><?= e($t['warehouse_name'] ?? '') ?></strong>
                to <strong><?= e($t['shop_name'] ?? '') ?></strong>
                &mdash; <?= $n($t['total_quantity'] ?? 0, 1) ?> units
              </p>
              <div class="alert alert-warning small py-2 mb-2">
                <i class="bi bi-info-circle me-1"></i>
                This transfer was already approved but has <strong>not yet been dispatched</strong>.
                Rejecting it will:
                <ul class="mb-0 mt-1">
                  <li>Release all stock reservations immediately</li>
                  <li>Restore available warehouse inventory</li>
                  <li>Notify the Warehouse Manager</li>
                  <li>Allow the WM to create a corrected transfer</li>
                </ul>
              </div>
              <label class="form-label small fw-semibold">Reason for rejection <span class="text-danger">*</span></label>
              <textarea name="reason" class="form-control form-control-sm" rows="3"
                        placeholder="State why this approved transfer is being rejected…" required minlength="5"></textarea>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-danger btn-sm">
                <i class="bi bi-x-circle me-1"></i>Confirm Rejection &amp; Release Stock
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
