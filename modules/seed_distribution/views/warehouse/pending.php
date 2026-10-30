<?php
declare(strict_types=1);
$warehouse ??= [];
$result    ??= ['data' => [], 'total' => 0, 'page' => 1, 'totalPages' => 1];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-hourglass-split me-2 text-warning"></i>Pending Approval</h4>
    <p class="text-muted small mb-0">Seed distributions awaiting GM approval</p>
  </div>
  <a href="<?= url('seed-distribution/new') ?>" class="btn btn-success btn-sm">
    <i class="bi bi-plus-lg me-1"></i>New Distribution
  </a>
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
            <th>Ref #</th>
            <th>Product</th>
            <th>Grade</th>
            <th class="text-end">Qty</th>
            <th class="text-end">Unit Price</th>
            <th class="text-end">Total</th>
            <th>Recipient</th>
            <th>Date</th>
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
            <td><code class="text-warning"><?= e($row['distribution_number']) ?></code></td>
            <td class="small"><?= e($row['product_name']) ?></td>
            <td><?= $row['grade_name'] ? '<span class="badge bg-secondary-subtle text-dark border small">'.e($row['grade_name']).'</span>' : '<span class="text-muted">—</span>' ?></td>
            <td class="text-end small">
              <?= number_format((float)$row['quantity'], 2) ?>
              <span class="text-muted"><?= e($row['unit_abbr'] ?? '') ?></span>
            </td>
            <td class="text-end small">
              <?= $unitPrc > 0 ? 'ETB '.number_format($unitPrc, 2) : '<span class="text-muted">—</span>' ?>
            </td>
            <td class="text-end small fw-semibold">
              <?= $totalAmt > 0 ? 'ETB '.number_format($totalAmt, 2) : '<span class="text-muted">—</span>' ?>
            </td>
            <td class="small"><?= e($row['recipient_name'] ?? '—') ?></td>
            <td class="small"><?= $row['distribution_date'] ? e(date('d/m/Y', strtotime($row['distribution_date']))) : '—' ?></td>
            <td class="text-nowrap">
              <a href="<?= url('seed-distribution/' . $row['id']) ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-eye"></i>
              </a>
              <button class="btn btn-sm btn-outline-danger ms-1"
                      data-cancel-id="<?= (int)$row['id'] ?>"
                      data-ref="<?= e($row['distribution_number']) ?>"
                      title="Cancel">
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
document.querySelectorAll('[data-cancel-id]').forEach(function(btn){
  btn.addEventListener('click', function(){
    var id  = this.dataset.cancelId;
    var ref = this.dataset.ref;
    if (!confirm('Cancel distribution ' + ref + '?')) return;
    var fd = new FormData();
    fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').content : '');
    fetch('<?= url('seed-distribution') ?>/' + id + '/cancel', {
      method: 'POST', credentials: 'same-origin',
      headers: {'X-Requested-With':'XMLHttpRequest'}, body: fd
    })
    .then(function(r){ return r.json(); })
    .then(function(resp){
      if (resp.success) location.reload();
      else alert(resp.message || 'Cancel failed');
    });
  });
});
</script>
<?php $view->endSection(); ?>
