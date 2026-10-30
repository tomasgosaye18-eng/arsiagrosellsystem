<?php
/** GM — Harvest Activity @var array $harvests */
declare(strict_types=1);
$harvests ??= [];
$n  = fn($v,$d=0) => is_numeric($v) ? number_format((float)$v,$d) : '—';
$dt = fn($v) => $v ? date('d/m/Y', strtotime($v)) : '—';
$statusBadge = ['active'=>'bg-success','partially_dispatched'=>'bg-warning text-dark','fully_dispatched'=>'bg-secondary','depleted'=>'bg-dark','cancelled'=>'bg-danger','expired'=>'bg-danger'];
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h5 class="fw-bold mb-0"><i class="bi bi-basket2-fill me-2 text-success"></i>Harvest Activity</h5>
    <small class="text-muted">All harvest batches — read-only monitor</small>
  </div>
  <a href="<?= url('gm/farms') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Farms</a>
</div>
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($harvests)): ?>
    <div class="text-center py-5 text-muted"><i class="bi bi-basket2 d-block fs-3 mb-2 opacity-25"></i><small>No harvest records.</small></div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr><th class="ps-3">Batch #</th><th>Farm</th><th>Product</th><th>Harvest Date</th><th class="text-end">Initial (kg)</th><th class="text-end">Available (kg)</th><th>Status</th><th class="pe-3">Recorded By</th></tr>
        </thead>
        <tbody>
          <?php foreach ($harvests as $h): ?>
          <tr>
            <td class="ps-3 small fw-semibold"><a href="<?= url('harvests/'.$h['id']) ?>" class="text-decoration-none"><?= e($h['batch_number'] ?? '#'.$h['id']) ?></a></td>
            <td class="small"><?= e($h['farm_name'] ?? '—') ?></td>
            <td class="small"><?= e($h['product_name'] ?? '—') ?></td>
            <td class="small"><?= $dt($h['harvest_date'] ?? null) ?></td>
            <td class="text-end small fw-semibold"><?= $n($h['initial_quantity'] ?? 0, 1) ?></td>
            <td class="text-end small text-success"><?= $n($h['remaining_quantity'] ?? 0, 1) ?></td>
            <td><span class="badge <?= $statusBadge[$h['status'] ?? ''] ?? 'bg-secondary' ?> small"><?= ucfirst(str_replace('_',' ',$h['status'] ?? '')) ?></span></td>
            <td class="small text-muted pe-3"><?= e($h['recorded_by_name'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
