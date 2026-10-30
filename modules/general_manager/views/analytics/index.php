<?php
/**
 * GM — Business Analytics
 * @var array $revenueTrend
 * @var array $shopBreakdown
 * @var array $shopPerf
 * @var array $topProducts
 * @var array $customerOrdersByShop
 * @var array $customerOrderKpis
 */
declare(strict_types=1);
$revenueTrend         ??= ['labels'=>[],'revenue'=>[],'transactions'=>[]];
$shopBreakdown        ??= [];
$shopPerf             ??= [];
$topProducts          ??= [];
$customerOrdersByShop ??= [];
$customerOrderKpis    ??= [];
$c = fn($v) => 'ETB '.number_format((float)$v, 2);
$n = fn($v,$d=0) => is_numeric($v) ? number_format((float)$v,$d) : '—';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h5 class="fw-bold mb-0"><i class="bi bi-graph-up-arrow me-2 text-success"></i>Business Analytics</h5>
    <small class="text-muted">Revenue trends and performance insights — General Manager</small>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('gm/orders') ?>" class="btn btn-outline-info btn-sm"><i class="bi bi-bag-check-fill me-1"></i>Orders</a>
    <a href="<?= url('gm/analytics/products') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-box-seam me-1"></i>Product Analytics</a>
  </div>
</div>

<!-- Revenue Trend Chart -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header fw-semibold small py-2">
    <i class="bi bi-bar-chart me-2 text-success"></i>Revenue Trend — Last 30 Days
  </div>
  <div class="card-body">
    <canvas id="analyticsRevenueChart" style="max-height:260px;"></canvas>
    <div id="analyticsEmpty" class="text-center py-4 text-muted d-none">
      <i class="bi bi-bar-chart d-block fs-3 mb-2 opacity-25"></i><small>No revenue data for this period.</small>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Shop Performance Chart -->
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header fw-semibold small py-2"><i class="bi bi-pie-chart-fill me-2 text-warning"></i>Revenue by Shop — This Month</div>
      <div class="card-body">
        <?php if (empty($shopBreakdown)): ?>
        <div class="text-center py-4 text-muted"><i class="bi bi-shop d-block fs-3 mb-2 opacity-25"></i><small>No data.</small></div>
        <?php else: ?>
        <canvas id="shopPieChart" style="max-height:220px;"></canvas>
        <ul class="list-group list-group-flush mt-3">
          <?php foreach ($shopBreakdown as $s): ?>
          <li class="list-group-item d-flex justify-content-between py-1 px-0 border-0 border-bottom small">
            <span><?= e($s['shop_name'] ?? '—') ?></span>
            <span class="fw-semibold"><?= number_format((float)$s['revenue'],2) ?> <span class="text-muted fw-normal">(<?= $n($s['tx_count']) ?> tx)</span></span>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <!-- Top Products -->
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header fw-semibold small py-2 d-flex justify-content-between">
        <span><i class="bi bi-trophy-fill me-2 text-warning"></i>Top Products — This Month</span>
        <a href="<?= url('gm/analytics/products') ?>" class="btn btn-outline-warning btn-sm py-0 px-2">All</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($topProducts)): ?>
        <div class="text-center py-4 text-muted"><small>No product sales data.</small></div>
        <?php else: ?>
        <?php $maxRev = max(array_column($topProducts, 'revenue') ?: [1]); ?>
        <div class="p-3">
          <?php foreach ($topProducts as $i => $p): ?>
          <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="small fw-semibold"><?= ($i+1).'. '.e($p['product_name'] ?? '—') ?></span>
              <span class="small text-success fw-bold"><?= $c($p['revenue'] ?? 0) ?></span>
            </div>
            <div class="progress" style="height:6px;">
              <div class="progress-bar bg-success" style="width:<?= $maxRev > 0 ? round(($p['revenue']/$maxRev)*100) : 0 ?>%"></div>
            </div>
            <div class="text-muted" style="font-size:.65rem;"><?= $n($p['qty_sold'] ?? 0, 1) ?> units sold</div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const d = <?= json_encode($revenueTrend) ?>;
  if (!d.labels || !d.labels.length) {
    document.getElementById('analyticsEmpty').classList.remove('d-none');
  } else {
    new Chart(document.getElementById('analyticsRevenueChart'), {
      type: 'bar',
      data: {
        labels: d.labels,
        datasets: [
          { label: 'Revenue (ETB)', data: d.revenue, backgroundColor: 'rgba(25,135,84,.7)', borderColor:'#198754', borderWidth:1, borderRadius:3, yAxisID:'y' },
          { label: 'Transactions',  data: d.transactions, type:'line', borderColor:'#0d6efd', backgroundColor:'rgba(13,110,253,.1)', tension:.3, pointRadius:3, yAxisID:'y1' }
        ]
      },
      options: {
        responsive:true,
        animation: false,
        plugins: { legend:{ position:'top', labels:{ font:{ size:11 } } } },
        scales: {
          y:  { beginAtZero:true, ticks:{ callback: v => 'ETB '+v.toLocaleString() }, position:'left' },
          y1: { beginAtZero:true, position:'right', grid:{ drawOnChartArea:false }, ticks:{ font:{ size:10 } } },
          x:  { ticks:{ maxRotation:45, font:{ size:10 } } }
        }
      }
    });
  }
  <?php if (!empty($shopBreakdown)): ?>
  const sd = <?= json_encode($shopBreakdown) ?>;
  const palette = ['#0d6efd','#198754','#fd7e14','#dc3545','#6f42c1','#0dcaf0','#20c997','#ffc107'];
  new Chart(document.getElementById('shopPieChart'), {
    type: 'doughnut',
    data: {
      labels: sd.map(s => s.shop_name),
      datasets:[{ data: sd.map(s => parseFloat(s.revenue)), backgroundColor: sd.map((_,i) => palette[i%palette.length]), borderWidth:2 }]
    },
    options: { responsive:true, animation: false, plugins:{ legend:{ display:false } } }
  });
  <?php endif; ?>
});
</script>

<!-- ── Customer Orders Analytics ─────────────────────── -->
<?php if (!empty($customerOrdersByShop) || ($customerOrderKpis['orders_this_month'] ?? 0) > 0): ?>
<div class="card border-0 shadow-sm mt-4">
  <div class="card-header fw-semibold small py-2 d-flex justify-content-between align-items-center">
    <span><i class="bi bi-bag-check-fill me-2 text-info"></i>Customer Pickup Orders — This Month</span>
    <a href="<?= url('gm/orders') ?>" class="btn btn-outline-info btn-sm py-0 px-2">Monitor Orders</a>
  </div>
  <div class="card-body">
    <!-- KPI mini-strip -->
    <div class="row g-2 mb-3">
      <?php foreach ([
        ['Total Orders',    $customerOrderKpis['orders_this_month']    ?? 0, 'secondary'],
        ['Completed',       $customerOrderKpis['completed_this_month'] ?? 0, 'success'],
        ['Active Now',      $customerOrderKpis['active_orders']        ?? 0, 'info'],
        ['Cancelled',       $customerOrderKpis['cancelled_this_month'] ?? 0, 'danger'],
        ['Revenue',         'ETB '.number_format((float)($customerOrderKpis['revenue_this_month'] ?? 0),2), 'success'],
      ] as [$lbl, $val, $col]): ?>
      <div class="col-6 col-md-2">
        <div class="text-center rounded border py-2 px-1">
          <div class="fw-bold small text-<?= $col ?>">
            <?= is_numeric($val) ? number_format((float)$val) : e($val) ?>
          </div>
          <div class="text-muted" style="font-size:.65rem;"><?= $lbl ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if (!empty($customerOrdersByShop)): ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Shop</th>
            <th class="text-center">Total Orders</th>
            <th class="text-center">Active</th>
            <th class="text-center">Completed</th>
            <th class="text-center">Cancelled</th>
            <th class="text-end pe-3">Revenue (ETB)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($customerOrdersByShop as $cs): ?>
          <tr>
            <td class="ps-3 small fw-semibold">
              <a href="<?= url('gm/orders?shop_id='.(int)$cs['shop_id']) ?>" class="text-decoration-none">
                <?= e($cs['shop_name'] ?? '—') ?>
              </a>
            </td>
            <td class="text-center small"><?= (int)($cs['total_orders'] ?? 0) ?></td>
            <td class="text-center small">
              <?php $a = (int)($cs['active_orders'] ?? 0); ?>
              <?= $a > 0 ? '<span class="badge bg-info text-dark">'.$a.'</span>' : '0' ?>
            </td>
            <td class="text-center small text-success fw-semibold">
              <?= (int)($cs['completed_orders'] ?? 0) ?>
            </td>
            <td class="text-center small text-danger">
              <?= (int)($cs['cancelled_orders'] ?? 0) ?>
            </td>
            <td class="text-end small fw-semibold pe-3">
              <?php $r = (float)($cs['completed_revenue'] ?? 0); ?>
              <?= $r > 0 ? number_format($r, 2) : '—' ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
