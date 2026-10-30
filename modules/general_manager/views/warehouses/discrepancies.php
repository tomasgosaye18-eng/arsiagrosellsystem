<?php
/** GM — Warehouse Discrepancies Monitor @var array $discrepancies */
declare(strict_types=1);
$discrepancies ??= [];
$dt = fn($v) => $v ? date('d/m/Y', strtotime($v)) : '—';
$statusBadge = ['open'=>'bg-danger','under_review'=>'bg-warning text-dark','resolved'=>'bg-success','rejected'=>'bg-secondary'];
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h5 class="fw-bold mb-0"><i class="bi bi-clipboard-x-fill me-2 text-danger"></i>Stock Discrepancies</h5>
    <small class="text-muted">Open discrepancies across all warehouses — read-only</small>
  </div>
  <a href="<?= url('gm/warehouses') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($discrepancies)): ?>
    <div class="text-center py-5 text-muted">
      <i class="bi bi-check-circle-fill d-block fs-3 mb-2 text-success opacity-50"></i>
      <small>No open discrepancies.</small>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Discrepancy #</th><th>Warehouse</th><th>Transfer #</th>
            <th>Date</th><th>Status</th><th class="pe-3">Reported By</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($discrepancies as $d): ?>
          <tr>
            <td class="ps-3 small fw-semibold">
              <a href="<?= url('warehouse/discrepancies/'.$d['id']) ?>" class="text-decoration-none">
                <?= e($d['discrepancy_number'] ?? '#'.$d['id']) ?>
              </a>
            </td>
            <td class="small"><?= e($d['warehouse_name'] ?? '—') ?></td>
            <td class="small text-muted"><?= e($d['transfer_number'] ?? '—') ?></td>
            <td class="small"><?= $dt($d['created_at'] ?? null) ?></td>
            <td><span class="badge <?= $statusBadge[$d['status'] ?? ''] ?? 'bg-secondary' ?> small"><?= ucfirst(str_replace('_',' ',$d['status'] ?? '')) ?></span></td>
            <td class="small text-muted pe-3"><?= e($d['reported_by_name'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
