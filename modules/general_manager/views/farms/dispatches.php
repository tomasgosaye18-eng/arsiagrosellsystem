<?php
/** GM — Farm Dispatches @var array $dispatches */
declare(strict_types=1);
$dispatches ??= [];
$n  = fn($v,$d=0) => is_numeric($v) ? number_format((float)$v,$d) : '—';
$dt = fn($v) => $v ? date('d/m/Y', strtotime($v)) : '—';
$statusBadge = ['pending'=>'bg-secondary','in_transit'=>'bg-info text-dark','received'=>'bg-success','partially_received'=>'bg-warning text-dark','cancelled'=>'bg-danger'];
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h5 class="fw-bold mb-0"><i class="bi bi-truck me-2 text-info"></i>Farm Dispatches</h5>
    <small class="text-muted">Farm → Warehouse dispatches — read-only monitor</small>
  </div>
  <a href="<?= url('gm/farms') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Farms</a>
</div>
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($dispatches)): ?>
    <div class="text-center py-5 text-muted"><i class="bi bi-truck d-block fs-3 mb-2 opacity-25"></i><small>No dispatch records.</small></div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr><th class="ps-3">Dispatch #</th><th>Farm</th><th>Product</th><th>Dispatch Date</th><th>Warehouse</th><th class="text-end">Qty (kg)</th><th>Status</th><th class="pe-3">Dispatched By</th></tr>
        </thead>
        <tbody>
          <?php foreach ($dispatches as $d): ?>
          <tr>
            <td class="ps-3 small fw-semibold"><a href="<?= url('dispatches/'.$d['id']) ?>" class="text-decoration-none"><?= e($d['dispatch_number'] ?? '#'.$d['id']) ?></a></td>
            <td class="small"><?= e($d['farm_name'] ?? '—') ?></td>
            <td class="small"><?= e($d['product_name'] ?? '—') ?></td>
            <td class="small"><?= $dt($d['dispatch_date'] ?? null) ?></td>
            <td class="small"><?= e($d['warehouse_name'] ?? '—') ?></td>
            <td class="text-end small fw-semibold"><?= $n($d['dispatched_quantity'] ?? 0, 1) ?></td>
            <td><span class="badge <?= $statusBadge[$d['status'] ?? ''] ?? 'bg-secondary' ?> small"><?= ucfirst(str_replace('_',' ',$d['status'] ?? '')) ?></span></td>
            <td class="small text-muted pe-3"><?= e($d['dispatched_by_name'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
