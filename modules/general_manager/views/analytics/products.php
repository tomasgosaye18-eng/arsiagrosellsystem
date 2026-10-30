<?php
/**
 * GM — Product Analytics
 * @var array $topProducts
 * @var array $stockAlerts
 */
declare(strict_types=1);
$topProducts ??= [];
$stockAlerts ??= [];
$n = fn($v,$d=0) => is_numeric($v) ? number_format((float)$v,$d) : '—';
$c = fn($v) => 'ETB '.number_format((float)$v,2);
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h5 class="fw-bold mb-0"><i class="bi bi-box-seam me-2 text-primary"></i>Product Analytics</h5>
    <small class="text-muted">Product sales performance this month — General Manager</small>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('gm/products') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-list me-1"></i>Product Catalog</a>
    <a href="<?= url('gm/analytics') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Analytics</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
      <div class="card-header fw-semibold small py-2"><i class="bi bi-trophy-fill me-2 text-warning"></i>Top Selling Products — This Month</div>
      <div class="card-body p-0">
        <?php if (empty($topProducts)): ?>
        <div class="text-center py-5 text-muted"><i class="bi bi-box-seam d-block fs-3 mb-2 opacity-25"></i><small>No sales data this month.</small></div>
        <?php else: ?>
        <?php $maxRev = max(array_column($topProducts,'revenue') ?: [1]); ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr><th class="ps-3">#</th><th>Product</th><th>SKU</th><th class="text-end">Qty Sold</th><th class="text-end pe-3">Revenue</th></tr>
            </thead>
            <tbody>
              <?php foreach ($topProducts as $i => $p): ?>
              <tr>
                <td class="ps-3 small text-muted"><?= $i+1 ?></td>
                <td class="small fw-semibold"><?= e($p['product_name'] ?? '—') ?></td>
                <td class="small text-muted"><?= e($p['sku'] ?? '—') ?></td>
                <td class="text-end small"><?= $n($p['qty_sold'] ?? 0, 1) ?></td>
                <td class="text-end small fw-semibold text-success pe-3"><?= $c($p['revenue'] ?? 0) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header fw-semibold small py-2"><i class="bi bi-bar-chart me-2 text-primary"></i>Revenue Chart</div>
      <div class="card-body"><canvas id="prodChart" style="max-height:220px;"></canvas></div>
    </div>
    <div class="card border-0 shadow-sm">
      <div class="card-header fw-semibold small py-2"><i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i>Stock Alerts</div>
      <div class="card-body p-0">
        <?php if (empty($stockAlerts)): ?>
        <div class="text-center py-3 text-muted"><small>All stock healthy.</small></div>
        <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach (array_slice($stockAlerts, 0, 8) as $a): ?>
          <li class="list-group-item d-flex justify-content-between py-2 px-3 border-0 border-bottom small">
            <span><?= e($a['product_name'] ?? '—') ?></span>
            <span class="badge <?= ($a['stock_status'] ?? '') === 'out_of_stock' ? 'bg-danger' : 'bg-warning text-dark' ?>">
              <?= ($a['stock_status'] ?? '') === 'out_of_stock' ? 'Out' : 'Low' ?>
            </span>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  <?php if (!empty($topProducts)): ?>
  const tp = <?= json_encode(array_slice($topProducts, 0, 10)) ?>;
  const pal = ['#198754','#0d6efd','#fd7e14','#dc3545','#6f42c1','#0dcaf0','#20c997','#ffc107','#adb5bd','#495057'];
  new Chart(document.getElementById('prodChart'), {
    type: 'bar',
    data: {
      labels: tp.map(p => p.product_name),
      datasets: [{ label: 'Revenue (ETB)', data: tp.map(p => parseFloat(p.revenue)), backgroundColor: pal, borderRadius:3 }]
    },
    options: {
      indexAxis: 'y',
      responsive:true,
      animation: false,
      plugins:{ legend:{ display:false } },
      scales:{ x:{ ticks:{ callback: v => 'ETB '+v.toLocaleString() } } }
    }
  });
  <?php endif; ?>
});
</script>
