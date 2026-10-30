<?php
declare(strict_types=1);
$warehouse ??= [];
$result    ??= ['data' => [], 'total' => 0, 'page' => 1, 'totalPages' => 1];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-check-circle-fill me-2 text-success"></i>Approved / Ready to Release</h4>
    <p class="text-muted small mb-0">GM-approved distributions waiting for seed release</p>
  </div>
</div>

<?php if (empty($result['data'])): ?>
<div class="alert alert-info">
  <i class="bi bi-info-circle me-2"></i>No approved distributions ready for release.
</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Ref #</th>
            <th>Product</th>
            <th>Grade</th>
            <th class="text-end">Qty</th>
            <th class="text-end">Unit Price</th>
            <th class="text-end">Total</th>
            <th>Recipient</th>
            <th>Approved</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($result['data'] as $row): ?>
          <?php
          $unitPrc  = (float)($row['unit_price']   ?? 0);
          $totalAmt = (float)($row['total_amount'] ?? 0);
          ?>
          <tr>
            <td><code class="text-success"><?= e($row['distribution_number']) ?></code></td>
            <td class="small"><?= e($row['product_name']) ?></td>
            <td><?= $row['grade_name'] ? '<span class="badge bg-secondary-subtle text-dark border small">'.e($row['grade_name']).'</span>' : '<span class="text-muted">—</span>' ?></td>
            <td class="text-end small">
              <?= number_format((float)$row['quantity'], 2) ?>
              <span class="text-muted"><?= e($row['unit_abbr'] ?? '') ?></span>
            </td>
            <td class="text-end small">
              <?= $unitPrc > 0 ? 'ETB '.number_format($unitPrc, 2) : '<span class="text-muted">—</span>' ?>
            </td>
            <td class="text-end small fw-bold text-success">
              <?= $totalAmt > 0 ? 'ETB '.number_format($totalAmt, 2) : '<span class="text-muted">—</span>' ?>
            </td>
            <td class="small"><?= e($row['recipient_name'] ?? '—') ?></td>
            <td class="small"><?= $row['approved_at'] ? e(date('d/m/Y H:i', strtotime($row['approved_at']))) : '—' ?></td>
            <td class="text-nowrap">
              <button class="btn btn-sm btn-success release-btn"
                      data-release-id="<?= (int)$row['id'] ?>"
                      data-ref="<?= e($row['distribution_number']) ?>"
                      data-qty="<?= (float)$row['quantity'] ?>"
                      data-product="<?= e($row['product_name']) ?>"
                      data-total="<?= number_format($totalAmt, 2) ?>">
                <i class="bi bi-box-arrow-right me-1"></i>Release
              </button>
              <a href="<?= url('seed-distribution/' . $row['id']) ?>" class="btn btn-sm btn-outline-secondary ms-1">
                <i class="bi bi-eye"></i>
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
        <ul id="releaseDetails" class="mb-3"></ul>
        <div class="alert alert-warning mb-0 small">
          <i class="bi bi-exclamation-triangle me-1"></i>
          This will deduct stock from warehouse inventory immediately. This action cannot be undone.
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="confirmRelease">
          <i class="bi bi-check-lg me-1"></i>Confirm Release
        </button>
      </div>
    </div>
  </div>
</div>

<?php $view->section('scripts'); ?>
<script>
(function(){
  var pendingId = null;
  var modal     = new bootstrap.Modal(document.getElementById('releaseModal'));

  document.querySelectorAll('.release-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      pendingId = this.dataset.releaseId;
      var details = document.getElementById('releaseDetails');
      details.innerHTML =
        '<li><strong>Ref:</strong> ' + this.dataset.ref + '</li>' +
        '<li><strong>Product:</strong> ' + this.dataset.product + '</li>' +
        '<li><strong>Quantity:</strong> ' + parseFloat(this.dataset.qty).toFixed(2) + '</li>' +
        (this.dataset.total && parseFloat(this.dataset.total) > 0
          ? '<li><strong>Total Amount:</strong> ETB ' + this.dataset.total + '</li>'
          : '');
      modal.show();
    });
  });

  document.getElementById('confirmRelease').addEventListener('click', function(){
    if (!pendingId) return;
    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Releasing…';

    var csrfToken = '';
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) csrfToken = csrfMeta.content;

    var fd = new FormData();
    fd.append('csrf_token', csrfToken);

    fetch('<?= url('seed-distribution') ?>/' + pendingId + '/release', {
      method: 'POST', credentials: 'same-origin',
      headers: {'X-Requested-With':'XMLHttpRequest'}, body: fd
    })
    .then(function(r){ return r.json(); })
    .then(function(resp){
      modal.hide();
      if (resp.success) {
        location.reload();
      } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Confirm Release';
        alert('Release failed: ' + (resp.message || 'Unknown error'));
      }
    })
    .catch(function(){
      modal.hide();
      alert('Network error. Please try again.');
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Confirm Release';
    });
  });
})();
</script>
<?php $view->endSection(); ?>
