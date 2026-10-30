<?php
/**
 * GM — Inventory Overview
 * @var array $inventory
 * @var array $stockAlerts
 * @var array $warehousePerf
 */
declare(strict_types=1);
$inventory    ??= [];
$stockAlerts  ??= [];
$warehousePerf??= [];
$n = fn($v,$d=0) => is_numeric($v) ? number_format((float)$v,$d) : '—';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h5 class="fw-bold mb-0"><i class="bi bi-boxes me-2 text-primary"></i>Inventory Overview</h5>
    <small class="text-muted">Combined warehouse + shop stock — read-only</small>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('inventory') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-building me-1"></i>Warehouse Detail</a>
    <a href="<?= url('gm/warehouses') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
</div>

<!-- KPI strip -->
<div class="row g-3 mb-4">
  <?php foreach ([
    ['Warehouses',       $warehousePerf['total_warehouses'] ?? 0,  'primary','bi-building'],
    ['Warehouse Stock',  $n($warehousePerf['total_stock'] ?? 0),   'primary','bi-boxes'],
    ['Low Stock Lines',  $warehousePerf['low_stock_lines'] ?? 0,   'warning','bi-exclamation-triangle'],
    ['Out of Stock',     $warehousePerf['out_of_stock_lines'] ?? 0,'danger', 'bi-slash-circle'],
    ['Open Discrepanc.', $warehousePerf['open_discrepancies'] ?? 0,'danger', 'bi-clipboard-x'],
    ['Expiring ≤7 Days', $warehousePerf['expiring_7d'] ?? 0,       'danger', 'bi-calendar-x'],
  ] as [$lbl,$val,$col,$ico]): ?>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card border-0 shadow-sm text-center h-100" style="border-top:3px solid var(--bs-<?= $col ?>)!important;">
      <div class="card-body py-3">
        <i class="bi <?= $ico ?> text-<?= $col ?> fs-3 d-block mb-1"></i>
        <div class="fw-bold fs-5"><?= is_numeric($val) ? number_format((float)$val) : e($val) ?></div>
        <div class="text-muted small"><?= $lbl ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <!-- Stock alerts -->
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header fw-semibold small py-2"><i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i>Stock Alerts</div>
      <div class="card-body p-0">
        <?php if (empty($stockAlerts)): ?>
        <div class="text-center py-4 text-muted"><i class="bi bi-check-circle-fill d-block fs-3 mb-2 text-success opacity-50"></i><small>All stock healthy.</small></div>
        <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($stockAlerts as $a): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 border-0 border-bottom">
            <div>
              <div class="small fw-semibold"><?= e($a['product_name'] ?? '—') ?></div>
              <div class="text-muted" style="font-size:.68rem;">WH: <?= $n($a['warehouse_qty'] ?? 0) ?> &bull; Shop: <?= $n($a['shop_qty'] ?? 0) ?></div>
            </div>
            <span class="badge <?= ($a['stock_status'] ?? '') === 'out_of_stock' ? 'bg-danger' : 'bg-warning text-dark' ?> small">
              <?= ($a['stock_status'] ?? '') === 'out_of_stock' ? 'Out' : 'Low' ?>
            </span>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <!-- Full inventory table -->
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header fw-semibold small py-2"><i class="bi bi-table me-2 text-primary"></i>All Products — Stock Levels</div>
      <div class="card-body p-0">
        <?php if (empty($inventory)): ?>
        <div class="text-center py-4 text-muted"><small>No inventory data.</small></div>
        <?php else: ?>
        <div class="table-responsive" style="max-height:500px;overflow-y:auto;">
          <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light sticky-top">
              <tr>
                <th class="ps-3">Product</th><th>Category</th>
                <th class="text-end">Warehouse</th><th class="text-end">Shop</th>
                <th class="text-end">Total</th><th class="pe-3 text-end">Min Level</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($inventory as $item): ?>
              <?php
              $total  = (float)($item['total_qty'] ?? 0);
              $minLvl = (float)($item['min_stock_level'] ?? 0);
              $rowClass = $total <= 0 ? 'table-danger' : ($minLvl > 0 && $total <= $minLvl ? 'table-warning' : '');
              ?>
              <tr class="<?= $rowClass ?>">
                <td class="ps-3 small fw-semibold"><?= e($item['product_name'] ?? '—') ?></td>
                <td class="small text-muted"><?= e($item['category_name'] ?? '—') ?></td>
                <td class="text-end small"><?= $n($item['warehouse_qty'] ?? 0, 1) ?></td>
                <td class="text-end small"><?= $n($item['shop_qty'] ?? 0, 1) ?></td>
                <td class="text-end small fw-semibold"><?= $n($total, 1) ?></td>
                <td class="text-end small pe-3 text-muted"><?= $minLvl > 0 ? $n($minLvl) : '—' ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
