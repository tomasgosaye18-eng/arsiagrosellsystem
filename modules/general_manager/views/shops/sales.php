<?php
/**
 * GM — Sales Overview
 *
 * @var array $shopBreakdown        POS sales by shop this month
 * @var array $recentSales          Recent POS sales
 * @var array $topProducts          Top selling products
 * @var array $shopPerf             POS shop performance KPIs
 * @var array $customerOrdersByShop Customer orders by shop this month
 * @var array $customerOrderKpis    Customer order aggregate KPIs
 * @var array $recentCustomerOrders Recent customer orders
 */
declare(strict_types=1);

use AAM\GeneralManager\Services\GMCustomerOrderService as GCOS;

$shopBreakdown        ??= [];
$recentSales          ??= [];
$topProducts          ??= [];
$shopPerf             ??= [];
$customerOrdersByShop ??= [];
$customerOrderKpis    ??= [];
$recentCustomerOrders ??= [];

$n  = fn($v, $d = 0) => is_numeric($v) ? number_format((float)$v, $d) : '—';
$c  = fn($v) => 'ETB ' . number_format((float)$v, 2);
$dt = fn($v) => $v ? date('d/m/Y H:i', strtotime($v)) : '—';
?>

<!-- ── Page Header ─────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h5 class="fw-bold mb-0">
      <i class="bi bi-bar-chart-line me-2 text-success"></i>Sales Overview
    </h5>
    <small class="text-muted">Cross-shop sales performance — General Manager</small>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= url('gm/orders') ?>" class="btn btn-outline-info btn-sm">
      <i class="bi bi-bag-check-fill me-1"></i>Customer Orders
    </a>
    <a href="<?= url('gm/shops') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-shop me-1"></i>All Shops
    </a>
    <a href="<?= url('gm/analytics') ?>" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-graph-up-arrow me-1"></i>Analytics
    </a>
  </div>
</div>

<!-- ── POS KPI Strip ───────────────────────────────────── -->
<div class="row g-2 mb-1">
  <div class="col-12">
    <small class="text-muted fw-semibold text-uppercase" style="letter-spacing:.06em;">
      <i class="bi bi-shop me-1 text-warning"></i>POS / Walk-in Sales
    </small>
  </div>
</div>
<div class="row g-3 mb-3">
  <?php foreach ([
    ['Sales Today',      $shopPerf['sales_today']       ?? 0, $c($shopPerf['revenue_today']       ?? 0), 'success'],
    ['Sales This Week',  $shopPerf['sales_this_week']    ?? 0, $c($shopPerf['revenue_this_week']   ?? 0), 'primary'],
    ['Sales This Month', $shopPerf['sales_this_month']   ?? 0, $c($shopPerf['revenue_this_month']  ?? 0), 'info'],
    ['Active Shops',     $shopPerf['total_shops']        ?? 0, ($shopPerf['transfers_incoming'] ?? 0) . ' incoming transfers', 'warning'],
  ] as [$lbl, $count, $sub, $col]): ?>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body py-2 px-3">
        <div class="text-muted small"><?= e($lbl) ?></div>
        <div class="fw-bold fs-4 text-<?= $col ?>">
          <?= is_numeric($count) ? number_format((float)$count) : e($count) ?>
        </div>
        <div class="text-<?= $col ?> small"><?= e($sub) ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Customer Order KPI Strip ───────────────────────── -->
<?php if (($customerOrderKpis['orders_this_month'] ?? 0) > 0 || ($customerOrderKpis['active_orders'] ?? 0) > 0): ?>
<div class="row g-2 mb-1">
  <div class="col-12">
    <small class="text-muted fw-semibold text-uppercase" style="letter-spacing:.06em;">
      <i class="bi bi-bag-check-fill me-1 text-info"></i>Customer Pickup Orders
    </small>
  </div>
</div>
<div class="row g-3 mb-4">
  <?php foreach ([
    ['Orders Today',      $customerOrderKpis['orders_today']        ?? 0, $c($customerOrderKpis['revenue_today'] ?? 0),        'warning'],
    ['Active Queue',      $customerOrderKpis['active_orders']        ?? 0, ($customerOrderKpis['ready_for_pickup'] ?? 0) . ' ready for pickup', 'info'],
    ['Completed / Month', $customerOrderKpis['completed_this_month'] ?? 0, $c($customerOrderKpis['revenue_this_month'] ?? 0),  'success'],
    ['Cancelled / Month', $customerOrderKpis['cancelled_this_month'] ?? 0, 'orders not completed',                              'danger'],
  ] as [$lbl, $count, $sub, $col]): ?>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body py-2 px-3">
        <div class="text-muted small"><?= e($lbl) ?></div>
        <div class="fw-bold fs-4 text-<?= $col ?>">
          <?= is_numeric($count) ? number_format((float)$count) : e($count) ?>
        </div>
        <div class="text-<?= $col ?> small"><?= e($sub) ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="mb-4"></div>
<?php endif; ?>

<!-- ── Shop Tables ─────────────────────────────────────── -->
<div class="row g-3 mb-4">

  <!-- POS Shop Breakdown -->
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header fw-semibold small py-2">
        <i class="bi bi-shop me-2 text-warning"></i>POS Sales by Shop — This Month
      </div>
      <div class="card-body p-0">
        <?php if (empty($shopBreakdown)): ?>
        <div class="text-center py-4 text-muted"><small>No POS data.</small></div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Shop</th>
                <th class="text-end">Transactions</th>
                <th class="text-end pe-3">Revenue</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($shopBreakdown as $s): ?>
              <tr>
                <td class="ps-3 small fw-semibold">
                  <a href="<?= url('shops/' . $s['id']) ?>" class="text-decoration-none">
                    <?= e($s['shop_name']) ?>
                  </a>
                </td>
                <td class="text-end small"><?= $n($s['tx_count']) ?></td>
                <td class="text-end small fw-semibold pe-3">
                  <?= number_format((float)$s['revenue'], 2) ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
              <tr>
                <td class="ps-3 small fw-bold">Total</td>
                <td class="text-end small fw-bold">
                  <?= $n(array_sum(array_column($shopBreakdown, 'tx_count'))) ?>
                </td>
                <td class="text-end small fw-bold pe-3">
                  <?= number_format(array_sum(array_column($shopBreakdown, 'revenue')), 2) ?>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Customer Orders by Shop -->
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header fw-semibold small py-2 d-flex justify-content-between align-items-center">
        <span>
          <i class="bi bi-bag-check-fill me-2 text-info"></i>Customer Orders by Shop — This Month
        </span>
        <a href="<?= url('gm/orders') ?>" class="btn btn-outline-info btn-sm py-0 px-2">All Orders</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($customerOrdersByShop)): ?>
        <div class="text-center py-4 text-muted"><small>No customer orders this month.</small></div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Shop</th>
                <th class="text-center">Active</th>
                <th class="text-center">Completed</th>
                <th class="text-center">Cancelled</th>
                <th class="text-end pe-3">Revenue</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($customerOrdersByShop as $cs): ?>
              <tr>
                <td class="ps-3 small fw-semibold">
                  <a href="<?= url('gm/orders?shop_id=' . (int)$cs['shop_id']) ?>"
                     class="text-decoration-none">
                    <?= e($cs['shop_name'] ?? '—') ?>
                  </a>
                </td>
                <td class="text-center small">
                  <?php $a = (int)($cs['active_orders'] ?? 0); ?>
                  <?php if ($a > 0): ?>
                  <span class="badge bg-info text-dark"><?= $a ?></span>
                  <?php else: ?>
                  <span class="text-muted">0</span>
                  <?php endif; ?>
                </td>
                <td class="text-center small text-success fw-semibold">
                  <?= (int)($cs['completed_orders'] ?? 0) ?>
                </td>
                <td class="text-center small text-danger">
                  <?= (int)($cs['cancelled_orders'] ?? 0) ?>
                </td>
                <td class="text-end small fw-semibold pe-3">
                  <?php $r = (float)($cs['completed_revenue'] ?? 0); ?>
                  <?= $r > 0 ? $c($r) : '<span class="text-muted">—</span>' ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
              <tr>
                <td class="ps-3 small fw-bold">Total</td>
                <td class="text-center small fw-bold">
                  <?= array_sum(array_column($customerOrdersByShop, 'active_orders')) ?>
                </td>
                <td class="text-center small fw-bold text-success">
                  <?= array_sum(array_column($customerOrdersByShop, 'completed_orders')) ?>
                </td>
                <td class="text-center small text-danger">
                  <?= array_sum(array_column($customerOrdersByShop, 'cancelled_orders')) ?>
                </td>
                <td class="text-end small fw-bold pe-3">
                  <?= $c(array_sum(array_column($customerOrdersByShop, 'completed_revenue'))) ?>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div><!-- /.row -->

<!-- ── Recent Activity ─────────────────────────────────── -->
<div class="row g-3">

  <!-- Recent POS Sales -->
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header fw-semibold small py-2">
        <i class="bi bi-receipt me-2 text-success"></i>Recent POS Sales
      </div>
      <div class="card-body p-0">
        <?php if (empty($recentSales)): ?>
        <div class="text-center py-4 text-muted"><small>No POS sales records.</small></div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Sale #</th>
                <th>Shop</th>
                <th>Seller</th>
                <th class="text-end">Amount</th>
                <th class="pe-3">Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_slice($recentSales, 0, 12) as $s): ?>
              <tr>
                <td class="ps-3 small fw-semibold">
                  <?= e($s['sale_number'] ?? '#' . $s['id']) ?>
                </td>
                <td class="small"><?= e($s['shop_name'] ?? '—') ?></td>
                <td class="small text-muted"><?= e($s['seller_name'] ?? '—') ?></td>
                <td class="text-end small fw-semibold text-success">
                  <?= number_format((float)$s['total_amount'], 2) ?>
                </td>
                <td class="small text-muted pe-3">
                  <?= $dt($s['completed_at'] ?? null) ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Recent Customer Orders -->
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header fw-semibold small py-2 d-flex justify-content-between align-items-center">
        <span>
          <i class="bi bi-bag-check-fill me-2 text-info"></i>Recent Customer Orders
        </span>
        <a href="<?= url('gm/orders') ?>" class="btn btn-outline-info btn-sm py-0 px-2">All</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($recentCustomerOrders)): ?>
        <div class="text-center py-4 text-muted">
          <i class="bi bi-bag d-block fs-3 mb-2 opacity-25"></i>
          <small>No customer orders yet.</small>
        </div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Order Ref</th>
                <th>Customer</th>
                <th>Shop</th>
                <th class="text-end">Amount</th>
                <th class="pe-3">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentCustomerOrders as $co):
                $badge = GCOS::statusBadge($co['order_status'] ?? '');
                $label = GCOS::statusLabel($co['order_status'] ?? '');
              ?>
              <tr>
                <td class="ps-3">
                  <a href="<?= url('gm/orders/' . (int)$co['id']) ?>"
                     class="small fw-semibold text-decoration-none font-monospace">
                    <?= e($co['order_reference'] ?? '#' . $co['id']) ?>
                  </a>
                </td>
                <td class="small"><?= e($co['customer_name'] ?? '—') ?></td>
                <td class="small text-muted"><?= e($co['shop_name'] ?? '—') ?></td>
                <td class="text-end small fw-semibold">
                  ETB <?= number_format((float)($co['total_amount'] ?? 0), 2) ?>
                </td>
                <td class="pe-3">
                  <span class="badge bg-<?= $badge ?> rounded-pill" style="font-size:.6rem;">
                    <?= e($label) ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div><!-- /.row -->
