<?php
/** GM — Warehouse Receiving Monitor @var array $receivings */
declare(strict_types=1);
$receivings ??= [];
$n  = fn($v,$d=0) => is_numeric($v) ? number_format((float)$v,$d) : '—';
$dt = fn($v) => $v ? date('d/m/Y', strtotime($v)) : '—';
$statusBadge = ['pending'=>'bg-warning text-dark','confirmed'=>'bg-success','partial'=>'bg-info text-dark','cancelled'=>'bg-danger','rejected'=>'bg-danger'];
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h5 class="fw-bold mb-0"><i class="bi bi-inbox-fill me-2 text-primary"></i>Warehouse Receiving</h5>
    <small class="text-muted">Farm shipments received at warehouse — read-only</small>
  </div>
  <a href="<?= url('gm/warehouses') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($receivings)): ?>
    <div class="text-center py-5 text-muted"><i class="bi bi-inbox d-block fs-3 mb-2 opacity-25"></i><small>No receiving records.</small></div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Dispatch #</th><th>Farm</th><th>Warehouse</th>
            <th>Date</th><th class="text-end">Qty Received</th>
            <th>Status</th><th class="pe-3">Received By</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($receivings as $r): ?>
          <tr>
            <td class="ps-3 small fw-semibold"><?= e($r['dispatch_number'] ?? '#'.$r['id']) ?></td>
            <td class="small"><?= e($r['farm_name'] ?? '—') ?></td>
            <td class="small"><?= e($r['warehouse_name'] ?? '—') ?></td>
            <td class="small"><?= $dt($r['received_date'] ?? null) ?></td>
            <td class="text-end small fw-semibold"><?= $n($r['total_received'] ?? 0, 1) ?></td>
            <td><span class="badge <?= $statusBadge[$r['status'] ?? ''] ?? 'bg-secondary' ?> small"><?= ucfirst($r['status'] ?? '—') ?></span></td>
            <td class="small text-muted pe-3"><?= e($r['received_by_name'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
