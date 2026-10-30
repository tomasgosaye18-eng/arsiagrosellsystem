<?php
declare(strict_types=1);
$warehouse ??= [];
$result    ??= ['data' => [], 'total' => 0, 'page' => 1, 'totalPages' => 1];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-truck me-2 text-primary"></i>Released / Dispatched</h4>
    <p class="text-muted small mb-0">Seeds released — awaiting distribution completion confirmation</p>
  </div>
</div>

<?php if (empty($result['data'])): ?>
<div class="alert alert-info">
  <i class="bi bi-info-circle me-2"></i>No released distributions pending completion.
</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Ref #</th><th>Product</th><th>Grade</th>
            <th class="text-end">Qty</th><th>Type</th>
            <th>Recipient</th><th>Location</th><th>Released At</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($result['data'] as $row): ?>
          <tr>
            <td><code class="text-primary"><?= e($row['distribution_number']) ?></code></td>
            <td><?= e($row['product_name']) ?></td>
            <td><?= $row['grade_name'] ? '<span class="badge bg-secondary-subtle text-dark border">'.e($row['grade_name']).'</span>' : '<span class="text-muted">—</span>' ?></td>
            <td class="text-end fw-bold text-primary"><?= number_format((float)$row['quantity'], 2) ?> <small class="text-muted"><?= e($row['unit_abbr'] ?? '') ?></small></td>
            <td>
              <?php if ($row['distribution_type'] === 'warehouse_pickup'): ?>
              <span class="badge bg-info-subtle text-info border border-info-subtle small"><i class="bi bi-building me-1"></i>Pickup</span>
              <?php else: ?>
              <span class="badge bg-primary-subtle text-primary border border-primary-subtle small"><i class="bi bi-geo-alt me-1"></i>Field</span>
              <?php endif; ?>
            </td>
            <td class="small"><?= e($row['recipient_name'] ?? '—') ?></td>
            <td class="small text-muted"><?= e(substr($row['destination_location'] ?? '', 0, 25)) ?></td>
            <td class="small"><?= $row['released_at'] ? e(date('d/m/Y H:i', strtotime($row['released_at']))) : '—' ?></td>
            <td>
              <button class="btn btn-sm btn-success complete-btn"
                      data-id="<?= (int)$row['id'] ?>"
                      data-ref="<?= e($row['distribution_number']) ?>"
                      data-recipient="<?= e($row['recipient_name'] ?? '') ?>"
                      title="Mark as Completed">
                <i class="bi bi-check2-all me-1"></i>Mark Complete
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

<?php if ($result['totalPages'] > 1): ?>
<nav class="mt-3">
  <ul class="pagination pagination-sm">
    <?php for ($p = 1; $p <= $result['totalPages']; $p++): ?>
    <li class="page-item <?= $p === $result['page'] ? 'active' : '' ?>">
      <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
    </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>
<?php endif; ?>

<?php $view->section('scripts'); ?>
<script>
(function(){
  document.querySelectorAll('.complete-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      var id        = this.dataset.id;
      var ref       = this.dataset.ref;
      var recipient = this.dataset.recipient;
      if (!confirm('Mark distribution ' + ref + ' as COMPLETED?\n\nRecipient: ' + recipient + '\n\nThis confirms the seed was delivered.')) return;

      var btn2 = this;
      btn2.disabled = true;
      btn2.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

      var fd = new FormData();
      var meta = document.querySelector('meta[name="csrf-token"]');
      fd.append('csrf_token', meta ? meta.content : '');

      fetch('<?= url('seed-distribution') ?>/' + id + '/complete', {
        method: 'POST', credentials: 'same-origin',
        headers: {'X-Requested-With': 'XMLHttpRequest'}, body: fd
      })
      .then(function(r){ return r.json(); })
      .then(function(resp){
        if (resp.success) { location.reload(); }
        else {
          btn2.disabled = false;
          btn2.innerHTML = '<i class="bi bi-check2-all me-1"></i>Mark Complete';
          alert(resp.message || 'Failed to complete.');
        }
      })
      .catch(function(){
        btn2.disabled = false;
        btn2.innerHTML = '<i class="bi bi-check2-all me-1"></i>Mark Complete';
        alert('Network error.');
      });
    });
  });
})();
</script>
<?php $view->endSection(); ?>
