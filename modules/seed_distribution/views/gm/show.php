<?php
declare(strict_types=1);
$dist ??= [];
$statusColors = ['pending'=>'warning','approved'=>'info','released'=>'primary','completed'=>'success','cancelled'=>'secondary','rejected'=>'danger'];
$sc = $statusColors[$dist['status'] ?? ''] ?? 'secondary';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-flower1 me-2 text-success"></i><?= e($dist['distribution_number'] ?? '') ?>
    </h4>
    <span class="badge bg-<?= $sc ?>-subtle text-<?= $sc ?> border border-<?= $sc ?>-subtle"><?= ucfirst($dist['status'] ?? '') ?></span>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('gm/seed-distribution/distributions') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>
    <?php if ($dist['status'] === 'pending'): ?>
    <button class="btn btn-success btn-sm" id="approveBtn" data-id="<?= (int)$dist['id'] ?>">
      <i class="bi bi-check-lg me-1"></i>Approve
    </button>
    <button class="btn btn-danger btn-sm" id="rejectBtn" data-id="<?= (int)$dist['id'] ?>">
      <i class="bi bi-x-lg me-1"></i>Reject
    </button>
    <?php endif; ?>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-success bg-opacity-10 border-0">
        <span class="fw-semibold"><i class="bi bi-flower1 me-2 text-success"></i>Seed Information</span>
      </div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-4 text-muted">Reference</dt><dd class="col-sm-8 fw-bold"><?= e($dist['distribution_number']) ?></dd>
          <dt class="col-sm-4 text-muted">Warehouse</dt><dd class="col-sm-8"><?= e($dist['warehouse_name'] ?? '—') ?></dd>
          <dt class="col-sm-4 text-muted">Product</dt><dd class="col-sm-8 fw-semibold"><?= e($dist['product_name'] ?? '—') ?></dd>
          <dt class="col-sm-4 text-muted">Grade / Class</dt>
          <dd class="col-sm-8"><?= $dist['grade_name'] ? '<span class="badge bg-secondary-subtle text-dark border">'.e($dist['grade_name']).'</span>' : '<span class="text-muted">—</span>' ?></dd>
          <dt class="col-sm-4 text-muted">Quantity</dt>
          <dd class="col-sm-8 fw-bold h5 mb-0"><?= number_format((float)($dist['quantity'] ?? 0), 2) ?> <?= e($dist['unit_abbr'] ?? '') ?></dd>
          <?php
          $unitPrice = (float)($dist['unit_price']  ?? 0);
          $totalAmt  = (float)($dist['total_amount'] ?? 0);
          ?>
          <dt class="col-sm-4 text-muted mt-2">Unit Price</dt>
          <dd class="col-sm-8 mt-2">
            <?php if ($unitPrice > 0): ?>
              <span class="fw-semibold text-success">ETB <?= number_format($unitPrice, 2) ?></span>
              <span class="text-muted small"> / <?= e($dist['unit_abbr'] ?? 'unit') ?></span>
              <span class="badge bg-secondary-subtle text-secondary border ms-1" style="font-size:.65rem;">price at distribution</span>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </dd>
          <dt class="col-sm-4 text-muted">Total Price</dt>
          <dd class="col-sm-8">
            <?php if ($totalAmt > 0): ?>
              <span class="fw-bold fs-5 text-dark">ETB <?= number_format($totalAmt, 2) ?></span>
              <?php if ($unitPrice > 0): ?>
              <div class="text-muted small"><?= number_format((float)($dist['quantity'] ?? 0), 2) ?> × ETB <?= number_format($unitPrice, 2) ?></div>
              <?php endif; ?>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </dd>
          <dt class="col-sm-4 text-muted mt-2">Type</dt>
          <dd class="col-sm-8 mt-2"><?= $dist['distribution_type'] === 'warehouse_pickup' ? '<i class="bi bi-building me-1"></i>Warehouse Pickup' : '<i class="bi bi-geo-alt me-1"></i>Field / Location Distribution' ?></dd>
          <dt class="col-sm-4 text-muted">Date</dt><dd class="col-sm-8"><?= $dist['distribution_date'] ? e(date('d/m/Y', strtotime($dist['distribution_date']))) : '—' ?></dd>
          <dt class="col-sm-4 text-muted">Destination</dt><dd class="col-sm-8"><?= e($dist['destination_location'] ?? '—') ?></dd>
          <?php if (!empty($dist['program_name'])): ?><dt class="col-sm-4 text-muted">Program</dt><dd class="col-sm-8"><?= e($dist['program_name']) ?></dd><?php endif; ?>
          <?php if (!empty($dist['notes'])): ?><dt class="col-sm-4 text-muted">Notes</dt><dd class="col-sm-8"><?= e($dist['notes']) ?></dd><?php endif; ?>
        </dl>
      </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-primary bg-opacity-10 border-0">
        <span class="fw-semibold"><i class="bi bi-person-fill me-2 text-primary"></i>Recipient</span>
      </div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-4 text-muted">Name</dt><dd class="col-sm-8"><?= e($dist['recipient_name'] ?? '—') ?></dd>
          <dt class="col-sm-4 text-muted">Phone</dt><dd class="col-sm-8"><?= e($dist['recipient_phone'] ?? '—') ?></dd>
          <dt class="col-sm-4 text-muted">Organization</dt><dd class="col-sm-8"><?= e($dist['recipient_organization'] ?? '—') ?></dd>
          <?php if (!empty($dist['recipient_notes'])): ?><dt class="col-sm-4 text-muted">Notes</dt><dd class="col-sm-8"><?= e($dist['recipient_notes']) ?></dd><?php endif; ?>
        </dl>
      </div>
    </div>

    <?php if ($dist['status'] === 'rejected' && !empty($dist['rejection_reason'])): ?>
    <div class="alert alert-danger">
      <strong><i class="bi bi-x-circle me-1"></i>Rejected:</strong> <?= e($dist['rejection_reason']) ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-5">
    <?php
    $unitPrice = (float)($dist['unit_price']  ?? 0);
    $totalAmt  = (float)($dist['total_amount'] ?? 0);
    ?>
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
          <span><?= number_format((float)($dist['quantity'] ?? 0), 2) ?> <?= e($dist['unit_abbr'] ?? '') ?></span>
        </div>
        <hr class="my-2">
        <div class="d-flex justify-content-between align-items-center">
          <span class="fw-bold">Total Price</span>
          <span class="fw-bold fs-5 text-success">ETB <?= number_format($totalAmt, 2) ?></span>
        </div>
      </div>
    </div>
    <?php endif; ?>
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header border-0"><span class="fw-semibold"><i class="bi bi-activity me-2"></i>Status</span></div>
      <div class="card-body">
        <div class="d-flex flex-column gap-2">
          <?php
          $steps = [
              ['Submitted',   $dist['created_at'] ?? null, $dist['created_by_name'] ?? null, 'pending'],
              ['Approved',    $dist['approved_at'] ?? null, $dist['approved_by_name'] ?? null, 'approved'],
              ['Released',    $dist['released_at'] ?? null, $dist['released_by_name'] ?? null, 'released'],
              ['Completed',   null, null, 'completed'],
          ];
          $order = ['pending','approved','released','completed'];
          $ci    = array_search($dist['status'], $order);
          foreach ($steps as $step):
            $si   = array_search($step[3], $order);
            $done = $ci !== false && $si <= $ci;
          ?>
          <div class="d-flex gap-2 align-items-start">
            <div class="rounded-circle bg-<?= $done ? 'success' : 'light' ?> d-flex align-items-center justify-content-center border flex-shrink-0"
                 style="width:28px;height:28px;">
              <i class="bi bi-check<?= $done ? '-lg text-white' : ' text-secondary' ?>" style="font-size:.75rem;"></i>
            </div>
            <div>
              <div class="fw-semibold small"><?= $step[0] ?></div>
              <?php if ($step[1]): ?>
              <div class="text-muted" style="font-size:.7rem;"><?= e(date('d/m/Y H:i', strtotime($step[1]))) ?><?= $step[2] ? ' · '.e($step[2]) : '' ?></div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <?php if (!empty($dist['quantity_before'])): ?>
    <div class="card border-0 bg-light mb-3">
      <div class="card-body p-3">
        <p class="fw-semibold small mb-2"><i class="bi bi-boxes me-1"></i>Inventory Impact</p>
        <div class="d-flex justify-content-between small"><span class="text-muted">Before</span><span><?= number_format((float)$dist['quantity_before'], 3) ?></span></div>
        <div class="d-flex justify-content-between small text-danger fw-bold"><span>Deducted</span><span>-<?= number_format((float)$dist['quantity'], 3) ?></span></div>
        <hr class="my-1">
        <div class="d-flex justify-content-between small fw-bold"><span>After</span><span><?= number_format((float)$dist['quantity_after'], 3) ?></span></div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 bg-danger text-white">
        <h5 class="modal-title"><i class="bi bi-x-circle me-2"></i>Reject Distribution</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <label class="form-label fw-semibold">Rejection Reason <span class="text-danger">*</span></label>
        <textarea class="form-control" id="rejectionReason" rows="3" maxlength="500" required></textarea>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmReject"><i class="bi bi-x-circle me-1"></i>Reject</button>
      </div>
    </div>
  </div>
</div>

<?php if ($dist['status'] === 'pending'): ?>
<?php $view->section('scripts'); ?>
<script>
(function(){
  var rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));
  var distId = <?= (int)$dist['id'] ?>;
  var csrf   = '<?= csrf_token() ?>';

  function post(url, extra) {
    var fd = new FormData(); fd.append('csrf_token', csrf);
    if (extra) Object.entries(extra).forEach(function(e){ fd.append(e[0],e[1]); });
    return fetch(url, {method:'POST',credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(function(r){ return r.json(); });
  }

  document.getElementById('approveBtn').addEventListener('click', function(){
    if (!confirm('Approve this distribution?')) return;
    post('<?= url('gm/seed-distribution') ?>/' + distId + '/approve')
      .then(function(r){ if (r.success) location.reload(); else alert(r.message || 'Failed'); });
  });

  document.getElementById('rejectBtn').addEventListener('click', function(){
    document.getElementById('rejectionReason').value = '';
    rejectModal.show();
  });

  document.getElementById('confirmReject').addEventListener('click', function(){
    var reason = document.getElementById('rejectionReason').value.trim();
    if (!reason) { alert('Reason required.'); return; }
    var btn = this; btn.disabled = true;
    post('<?= url('gm/seed-distribution') ?>/' + distId + '/reject', {rejection_reason: reason})
      .then(function(r){
        rejectModal.hide(); btn.disabled = false;
        if (r.success) location.reload(); else alert(r.message || 'Failed');
      });
  });
})();
</script>
<?php $view->endSection(); ?>
<?php endif; ?>
