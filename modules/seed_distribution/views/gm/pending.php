<?php
declare(strict_types=1);
$result ??= ['data' => [], 'total' => 0, 'page' => 1, 'totalPages' => 1];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-hourglass-split me-2 text-warning"></i>Pending Seed Distribution Approvals</h4>
    <p class="text-muted small mb-0"><?= (int)$result['total'] ?> distributions awaiting your approval</p>
  </div>
</div>

<?php if (empty($result['data'])): ?>
<div class="alert alert-success">
  <i class="bi bi-check-circle me-2"></i>No pending distributions. All up to date.
</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Ref #</th><th>Warehouse</th><th>Product</th><th>Grade</th>
            <th class="text-end">Qty</th><th>Type</th><th>Recipient</th>
            <th>Location</th><th>Submitted</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($result['data'] as $row): ?>
          <tr>
            <td><code class="text-warning"><?= e($row['distribution_number']) ?></code></td>
            <td class="small"><?= e($row['warehouse_name'] ?? '—') ?></td>
            <td><?= e($row['product_name']) ?></td>
            <td><?= $row['grade_name'] ? '<span class="badge bg-secondary-subtle text-dark border">'.e($row['grade_name']).'</span>' : '—' ?></td>
            <td class="text-end fw-bold"><?= number_format((float)$row['quantity'], 2) ?> <small class="text-muted"><?= e($row['unit_abbr'] ?? '') ?></small></td>
            <td>
              <?php if ($row['distribution_type'] === 'warehouse_pickup'): ?>
              <span class="badge bg-info-subtle text-info border border-info-subtle small"><i class="bi bi-building me-1"></i>Pickup</span>
              <?php else: ?>
              <span class="badge bg-primary-subtle text-primary border border-primary-subtle small"><i class="bi bi-geo-alt me-1"></i>Field</span>
              <?php endif; ?>
            </td>
            <td class="small"><?= e($row['recipient_name'] ?? '—') ?></td>
            <td class="small text-muted"><?= e(substr($row['destination_location'] ?? '', 0, 25)) ?></td>
            <td class="small"><?= $row['created_at'] ? e(date('d/m/Y H:i', strtotime($row['created_at']))) : '—' ?></td>
            <td>
              <a href="<?= url('gm/seed-distribution/' . $row['id']) ?>" class="btn btn-sm btn-outline-secondary me-1" title="View">
                <i class="bi bi-eye"></i>
              </a>
              <button class="btn btn-sm btn-success approve-btn me-1"
                      data-id="<?= (int)$row['id'] ?>"
                      data-ref="<?= e($row['distribution_number']) ?>"
                      title="Approve">
                <i class="bi bi-check-lg"></i>
              </button>
              <button class="btn btn-sm btn-danger reject-btn"
                      data-id="<?= (int)$row['id'] ?>"
                      data-ref="<?= e($row['distribution_number']) ?>"
                      title="Reject">
                <i class="bi bi-x-lg"></i>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 bg-danger text-white">
        <h5 class="modal-title"><i class="bi bi-x-circle me-2"></i>Reject Distribution</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <p>Rejecting: <strong id="rejectRef"></strong></p>
        <label class="form-label fw-semibold">Rejection Reason <span class="text-danger">*</span></label>
        <textarea class="form-control" id="rejectionReason" rows="3" maxlength="500" required
                  placeholder="Explain why this distribution is being rejected…"></textarea>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmReject">
          <i class="bi bi-x-circle me-1"></i>Reject
        </button>
      </div>
    </div>
  </div>
</div>

<?php $view->section('scripts'); ?>
<script>
(function(){
  var pendingRejectId = null;
  var rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));

  function postAction(url, extraData) {
    var fd = new FormData();
    fd.append('csrf_token', '<?= csrf_token() ?>');
    if (extraData) { Object.entries(extraData).forEach(function(kv){ fd.append(kv[0], kv[1]); }); }
    return fetch(url, { method: 'POST', credentials: 'same-origin', headers: {'X-Requested-With':'XMLHttpRequest'}, body: fd }).then(function(r){ return r.json(); });
  }

  document.querySelectorAll('.approve-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      var id = this.dataset.id, ref = this.dataset.ref;
      if (!confirm('Approve distribution ' + ref + '?')) return;
      postAction('<?= url('gm/seed-distribution') ?>/' + id + '/approve')
        .then(function(resp){ if (resp.success) location.reload(); else alert(resp.message || 'Failed'); });
    });
  });

  document.querySelectorAll('.reject-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      pendingRejectId = this.dataset.id;
      document.getElementById('rejectRef').textContent = this.dataset.ref;
      document.getElementById('rejectionReason').value = '';
      rejectModal.show();
    });
  });

  document.getElementById('confirmReject').addEventListener('click', function(){
    var reason = document.getElementById('rejectionReason').value.trim();
    if (!reason) { alert('Please provide a rejection reason.'); return; }
    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Rejecting…';
    postAction('<?= url('gm/seed-distribution') ?>/' + pendingRejectId + '/reject', {rejection_reason: reason})
      .then(function(resp){
        rejectModal.hide();
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-x-circle me-1"></i>Reject';
        if (resp.success) location.reload();
        else alert(resp.message || 'Failed');
      });
  });
})();
</script>
<?php $view->endSection(); ?>
