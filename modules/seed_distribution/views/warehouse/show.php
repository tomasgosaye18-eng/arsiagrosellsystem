<?php
declare(strict_types=1);
$dist      ??= [];
$warehouse ??= [];

$statusColors = [
    'pending'   => 'warning',
    'approved'  => 'info',
    'released'  => 'primary',
    'completed' => 'success',
    'cancelled' => 'secondary',
    'rejected'  => 'danger',
];
$sc = $statusColors[$dist['status'] ?? ''] ?? 'secondary';

$canReceipt = in_array($dist['status'] ?? '', ['released', 'completed'], true);
$unitPrice  = (float)($dist['unit_price']   ?? 0);
$totalAmt   = (float)($dist['total_amount'] ?? 0);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-flower1 me-2 text-success"></i>
      <?= e($dist['distribution_number'] ?? '') ?>
    </h4>
    <span class="badge bg-<?= $sc ?>-subtle text-<?= $sc ?> border border-<?= $sc ?>-subtle">
      <?= ucfirst($dist['status'] ?? '') ?>
    </span>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= url('seed-distribution/history') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back to History
    </a>
    <?php if (($dist['status'] ?? '') === 'approved'): ?>
    <button class="btn btn-sm btn-success" id="releaseBtn"
            data-id="<?= (int)($dist['id'] ?? 0) ?>"
            data-ref="<?= e($dist['distribution_number'] ?? '') ?>"
            data-qty="<?= (float)($dist['quantity'] ?? 0) ?>"
            data-product="<?= e($dist['product_name'] ?? '') ?>"
            data-total="<?= number_format((float)($dist['total_amount'] ?? 0), 2) ?>">
      <i class="bi bi-box-arrow-right me-1"></i>Release Seed
    </button>
    <?php endif; ?>
    <?php if ($canReceipt): ?>
    <a href="<?= url('seed-distribution/' . (int)$dist['id'] . '/receipt') ?>"
       class="btn btn-sm btn-outline-success" target="_blank">
      <i class="bi bi-receipt me-1"></i>View Receipt
    </a>
    <a href="<?= url('seed-distribution/' . (int)$dist['id'] . '/receipt') ?>?autoprint=1"
       class="btn btn-sm btn-success" target="_blank">
      <i class="bi bi-printer-fill me-1"></i>Print Receipt
    </a>
    <?php endif; ?>
    <?php if (($dist['status'] ?? '') === 'released'): ?>
    <button class="btn btn-sm btn-outline-primary" id="completeBtn"
            data-id="<?= (int)($dist['id'] ?? 0) ?>">
      <i class="bi bi-check2-all me-1"></i>Mark as Completed
    </button>
    <?php endif; ?>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <!-- Seed Info -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-success bg-opacity-10 border-0">
        <span class="fw-semibold"><i class="bi bi-flower1 me-2 text-success"></i>Seed Information</span>
      </div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-4 text-muted">Product</dt>
          <dd class="col-sm-8 fw-semibold"><?= e($dist['product_name'] ?? '—') ?></dd>

          <dt class="col-sm-4 text-muted">Grade / Class</dt>
          <dd class="col-sm-8">
            <?= $dist['grade_name']
              ? '<span class="badge bg-secondary-subtle text-dark border">'.e($dist['grade_name']).'</span>'
              : '<span class="text-muted">—</span>' ?>
          </dd>

          <dt class="col-sm-4 text-muted">Quantity</dt>
          <dd class="col-sm-8 fw-bold">
            <?= number_format((float)($dist['quantity'] ?? 0), 3) ?>
            <?= e($dist['unit_abbr'] ?? '') ?>
          </dd>

          <dt class="col-sm-4 text-muted">Unit Price</dt>
          <dd class="col-sm-8">
            <?php if ($unitPrice > 0): ?>
              <span class="fw-semibold text-success">ETB <?= number_format($unitPrice, 2) ?></span>
              <span class="text-muted small"> / <?= e($dist['unit_abbr'] ?? 'unit') ?></span>
              <span class="badge bg-secondary-subtle text-secondary border ms-1" style="font-size:.65rem;">price at distribution</span>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </dd>

          <dt class="col-sm-4 text-muted">Total Amount</dt>
          <dd class="col-sm-8">
            <?php if ($totalAmt > 0): ?>
              <span class="fw-bold fs-6">ETB <?= number_format($totalAmt, 2) ?></span>
              <?php if ($unitPrice > 0): ?>
              <div class="text-muted small">
                <?= number_format((float)$dist['quantity'], 3) ?> × ETB <?= number_format($unitPrice, 2) ?>
              </div>
              <?php endif; ?>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </dd>

          <dt class="col-sm-4 text-muted">Distribution Type</dt>
          <dd class="col-sm-8">
            <?= $dist['distribution_type'] === 'warehouse_pickup'
              ? '<i class="bi bi-building me-1"></i>Warehouse Pickup'
              : '<i class="bi bi-geo-alt me-1"></i>Field / Location Distribution' ?>
          </dd>

          <dt class="col-sm-4 text-muted">Date</dt>
          <dd class="col-sm-8">
            <?= $dist['distribution_date'] ? e(date('d/m/Y', strtotime($dist['distribution_date']))) : '—' ?>
          </dd>

          <dt class="col-sm-4 text-muted">Destination</dt>
          <dd class="col-sm-8"><?= e($dist['destination_location'] ?? '—') ?></dd>

          <dt class="col-sm-4 text-muted">Warehouse</dt>
          <dd class="col-sm-8"><?= e($dist['warehouse_name'] ?? '—') ?></dd>
        </dl>
      </div>
    </div>

    <!-- Recipient -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-primary bg-opacity-10 border-0">
        <span class="fw-semibold"><i class="bi bi-person-fill me-2 text-primary"></i>Recipient</span>
      </div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-4 text-muted">Name</dt>
          <dd class="col-sm-8 fw-semibold"><?= e($dist['recipient_name'] ?? '—') ?></dd>

          <dt class="col-sm-4 text-muted">Phone</dt>
          <dd class="col-sm-8"><?= e($dist['recipient_phone'] ?? '—') ?></dd>

          <dt class="col-sm-4 text-muted">Organization</dt>
          <dd class="col-sm-8"><?= e($dist['recipient_organization'] ?? '—') ?></dd>

          <dt class="col-sm-4 text-muted">Program</dt>
          <dd class="col-sm-8"><?= e($dist['program_name'] ?? '—') ?></dd>

          <?php if (!empty($dist['recipient_notes'])): ?>
          <dt class="col-sm-4 text-muted">Notes</dt>
          <dd class="col-sm-8"><?= e($dist['recipient_notes']) ?></dd>
          <?php endif; ?>
        </dl>
      </div>
    </div>

    <?php if (!empty($dist['notes'])): ?>
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <p class="text-muted small mb-1 fw-semibold">Notes</p>
        <p class="mb-0"><?= e($dist['notes']) ?></p>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-5">
    <!-- Financial Summary -->
    <?php if ($unitPrice > 0 || $totalAmt > 0): ?>
    <div class="card border-0 shadow-sm mb-3 border-success">
      <div class="card-header bg-success bg-opacity-10 border-0">
        <span class="fw-semibold text-success"><i class="bi bi-cash-coin me-1"></i>Financial Summary</span>
      </div>
      <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="text-muted small">Unit Price (snapshot)</span>
          <span class="fw-semibold">ETB <?= number_format($unitPrice, 2) ?> / <?= e($dist['unit_abbr'] ?? 'unit') ?></span>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="text-muted small">Quantity</span>
          <span><?= number_format((float)$dist['quantity'], 3) ?> <?= e($dist['unit_abbr'] ?? '') ?></span>
        </div>
        <hr class="my-2">
        <div class="d-flex justify-content-between align-items-center">
          <span class="fw-bold">Total Amount</span>
          <span class="fw-bold fs-5 text-success">ETB <?= number_format($totalAmt, 2) ?></span>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Workflow Status -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header border-0">
        <span class="fw-semibold"><i class="bi bi-activity me-2"></i>Workflow Status</span>
      </div>
      <div class="card-body">
        <ul class="list-unstyled mb-0">
          <?php
          $steps = [
              'pending'   => ['Submitted', $dist['created_at'] ?? null, $dist['created_by_name'] ?? null],
              'approved'  => ['Approved',  $dist['approved_at'] ?? null, $dist['approved_by_name'] ?? null],
              'released'  => ['Released',  $dist['released_at'] ?? null, $dist['released_by_name'] ?? null],
              'completed' => ['Completed', null, null],
          ];
          $statusOrder = ['pending', 'approved', 'released', 'completed'];
          $currentIdx  = array_search($dist['status'] ?? 'pending', $statusOrder);
          if ($dist['status'] === 'rejected' || $dist['status'] === 'cancelled') $currentIdx = -1;
          ?>
          <?php foreach ($steps as $key => [$label, $at, $by]): ?>
          <?php
          $keyIdx = array_search($key, $statusOrder);
          $done   = $currentIdx !== false && $keyIdx <= $currentIdx;
          $color  = $done ? 'success' : 'secondary';
          ?>
          <li class="d-flex gap-2 mb-2">
            <div class="rounded-circle bg-<?= $color ?><?= $done ? '' : '-subtle' ?> d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:28px;height:28px;">
              <i class="bi bi-check<?= $done ? '-lg text-white' : ' text-secondary' ?>" style="font-size:.75rem;"></i>
            </div>
            <div>
              <div class="fw-semibold small"><?= $label ?></div>
              <?php if ($at): ?>
              <div class="text-muted" style="font-size:.72rem;">
                <?= e(date('d/m/Y H:i', strtotime($at))) ?>
                <?= $by ? ' — '.e($by) : '' ?>
              </div>
              <?php endif; ?>
            </div>
          </li>
          <?php endforeach; ?>

          <?php if ($dist['status'] === 'rejected'): ?>
          <li class="d-flex gap-2 mb-2">
            <div class="rounded-circle bg-danger d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:28px;height:28px;">
              <i class="bi bi-x-lg text-white" style="font-size:.75rem;"></i>
            </div>
            <div>
              <div class="fw-semibold small text-danger">Rejected</div>
              <?php if (!empty($dist['rejection_reason'])): ?>
              <div class="text-muted" style="font-size:.72rem;"><?= e($dist['rejection_reason']) ?></div>
              <?php endif; ?>
            </div>
          </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>

    <!-- Inventory Impact -->
    <?php if (!empty($dist['quantity_before'])): ?>
    <div class="card border-0 bg-light mb-3">
      <div class="card-body p-3">
        <p class="fw-semibold small mb-2"><i class="bi bi-boxes me-1"></i>Inventory Impact</p>
        <div class="d-flex justify-content-between small">
          <span class="text-muted">Before</span>
          <span><?= number_format((float)$dist['quantity_before'], 3) ?></span>
        </div>
        <div class="d-flex justify-content-between small text-danger fw-bold">
          <span>Deducted</span>
          <span>-<?= number_format((float)$dist['quantity'], 3) ?></span>
        </div>
        <hr class="my-1">
        <div class="d-flex justify-content-between small fw-bold">
          <span>After</span>
          <span><?= number_format((float)$dist['quantity_after'], 3) ?></span>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Receipt Actions -->
    <?php if ($canReceipt): ?>
    <div class="card border-0 border-success shadow-sm mb-3">
      <div class="card-body p-3 text-center">
        <p class="fw-semibold small mb-2"><i class="bi bi-receipt me-1 text-success"></i>Seed Distribution Receipt</p>
        <div class="d-flex gap-2 justify-content-center">
          <a href="<?= url('seed-distribution/' . (int)$dist['id'] . '/receipt') ?>"
             class="btn btn-outline-success btn-sm" target="_blank">
            <i class="bi bi-eye me-1"></i>View
          </a>
          <a href="<?= url('seed-distribution/' . (int)$dist['id'] . '/receipt') ?>?autoprint=1"
             class="btn btn-success btn-sm" target="_blank">
            <i class="bi bi-printer me-1"></i>Print
          </a>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if (($dist['status'] ?? '') === 'approved'): ?>
<!-- Release Confirm Modal -->
<div class="modal fade" id="releaseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-success text-white border-0">
        <h5 class="modal-title"><i class="bi bi-box-arrow-right me-2"></i>Release Seed</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <p class="mb-2">You are about to release:</p>
        <ul id="releaseDetailsList" class="mb-3"></ul>
        <div class="alert alert-warning mb-0 small">
          <i class="bi bi-exclamation-triangle me-1"></i>
          This will immediately deduct stock from warehouse inventory. This cannot be undone.
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="confirmReleaseBtn">
          <i class="bi bi-check-lg me-1"></i>Confirm Release
        </button>
      </div>
    </div>
  </div>
</div>
<?php $view->section('scripts'); ?>
<script>
(function(){
  var releaseBtn  = document.getElementById('releaseBtn');
  var modal       = new bootstrap.Modal(document.getElementById('releaseModal'));
  var confirmBtn  = document.getElementById('confirmReleaseBtn');
  var detailsList = document.getElementById('releaseDetailsList');

  releaseBtn.addEventListener('click', function(){
    var total = this.dataset.total;
    detailsList.innerHTML =
      '<li><strong>Ref:</strong> ' + this.dataset.ref + '</li>' +
      '<li><strong>Product:</strong> ' + this.dataset.product + '</li>' +
      '<li><strong>Quantity:</strong> ' + parseFloat(this.dataset.qty).toFixed(3) + '</li>' +
      (total && parseFloat(total) > 0 ? '<li><strong>Total Amount:</strong> ETB ' + total + '</li>' : '');
    modal.show();
  });

  confirmBtn.addEventListener('click', function(){
    var distId = releaseBtn.dataset.id;
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Releasing…';
    var fd = new FormData();
    var meta = document.querySelector('meta[name="csrf-token"]');
    fd.append('csrf_token', meta ? meta.content : '');
    fetch('<?= url('seed-distribution') ?>/' + distId + '/release', {
      method: 'POST', credentials: 'same-origin',
      headers: {'X-Requested-With': 'XMLHttpRequest'}, body: fd
    })
    .then(function(r){ return r.json(); })
    .then(function(resp){
      modal.hide();
      if (resp.success) {
        location.reload();
      } else {
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Confirm Release';
        alert('Release failed: ' + (resp.message || 'Unknown error'));
      }
    })
    .catch(function(){
      modal.hide();
      confirmBtn.disabled = false;
      confirmBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Confirm Release';
      alert('Network error. Please try again.');
    });
  });
})();
</script>
<?php $view->endSection(); ?>
<?php endif; ?>

<?php if (($dist['status'] ?? '') === 'released'): ?>
<?php $view->section('scripts'); ?>
<script>
(function(){
  var btn = document.getElementById('completeBtn');
  if (!btn) return;
  btn.addEventListener('click', function(){
    if (!confirm('Mark this distribution as COMPLETED?\n\nThis confirms the seed was delivered to the recipient.')) return;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
    var fd = new FormData();
    var meta = document.querySelector('meta[name="csrf-token"]');
    fd.append('csrf_token', meta ? meta.content : '');
    fetch('<?= url('seed-distribution') ?>/' + btn.dataset.id + '/complete', {
      method: 'POST', credentials: 'same-origin',
      headers: {'X-Requested-With':'XMLHttpRequest'}, body: fd
    })
    .then(function(r){ return r.json(); })
    .then(function(resp){
      if (resp.success) { location.reload(); }
      else {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check2-all me-1"></i>Mark as Completed';
        alert(resp.message || 'Failed to complete.');
      }
    })
    .catch(function(){
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-check2-all me-1"></i>Mark as Completed';
      alert('Network error.');
    });
  });
})();
</script>
<?php $view->endSection(); ?>
<?php endif; ?>
