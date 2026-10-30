<?php
/**
 * General Manager — Executive Dashboard
 * Route: GET /gm/dashboard
 *
 * @var array  $kpis
 * @var array  $farmPerformance
 * @var array  $warehousePerf
 * @var array  $transferPipeline
 * @var array  $pendingApprovals
 * @var array  $shopPerformance
 * @var array  $shopSalesBreakdown
 * @var array  $topProducts
 * @var array  $stockAlerts
 * @var array  $recentActivity
 * @var array  $customerOrderKpis
 * @var array  $customerOrdersByShop
 * @var array  $recentCustomerOrders
 * @var string $userName
 */
declare(strict_types=1);

use AAM\GeneralManager\Services\GMCustomerOrderService as GCOS;

$kpis               ??= [];
$farmPerformance    ??= [];
$warehousePerf      ??= [];
$transferPipeline   ??= [];
$pendingApprovals   ??= [];
$pendingFarmDispatches ??= [];
$shopPerformance    ??= [];
$shopSalesBreakdown ??= [];
$topProducts        ??= [];
$stockAlerts        ??= [];
$recentActivity     ??= [];
$pendingTransferDiscrepancyReviews ??= [];
$customerOrderKpis     ??= [];
$customerOrdersByShop  ??= [];
$recentCustomerOrders  ??= [];

$n  = fn($v, $d=0) => is_numeric($v) ? number_format((float)$v, $d) : '—';
$c  = fn($v) => 'ETB '.number_format((float)$v, 2);
$dt = fn($v) => $v ? date('d/m/Y H:i', strtotime($v)) : '—';
?>
<!-- Welcome Banner -->
<div class="row g-3 mb-4">
  <div class="col-12">
    <div class="card border-0 shadow-sm" style="background:linear-gradient(135deg,#1a4a2e 0%,#0d5c8a 100%);">
      <div class="card-body d-flex align-items-center gap-3 py-3 text-white">
        <div class="rounded-circle bg-white bg-opacity-20 d-flex align-items-center justify-content-center flex-shrink-0" style="width:52px;height:52px;">
          <i class="bi bi-briefcase-fill fs-4"></i>
        </div>
        <div class="flex-grow-1">
          <h5 class="mb-0 fw-bold">Welcome back, <?= e($userName) ?></h5>
          <small class="opacity-75">General Manager &mdash; Executive Dashboard &mdash; <?= date('l, d F Y') ?></small>
        </div>
        <div class="d-none d-md-flex gap-2 flex-wrap">
          <?php if (($transferPipeline['pending_approval'] ?? 0) > 0): ?>
          <a href="<?= url('gm/transfers/pending') ?>" class="btn btn-warning btn-sm fw-semibold">
            <i class="bi bi-hourglass-split me-1"></i><?= (int)$transferPipeline['pending_approval'] ?> Transfer<?= $transferPipeline['pending_approval'] > 1 ? 's' : '' ?>
          </a>
          <?php endif; ?>
          <?php if (!empty($pendingFarmDispatches)): ?>
          <a href="<?= url('gm/farms/dispatches/pending') ?>" class="btn btn-warning btn-sm fw-semibold">
            <i class="bi bi-flower1 me-1"></i><?= count($pendingFarmDispatches) ?> Dispatch<?= count($pendingFarmDispatches) > 1 ? 'es' : '' ?>
          </a>
          <?php endif; ?>
          <?php if (($kpis['pending_adjustments'] ?? 0) > 0): ?>
          <a href="<?= url('gm/adjustments/pending') ?>" class="btn btn-warning btn-sm fw-semibold">
            <i class="bi bi-sliders me-1"></i><?= (int)$kpis['pending_adjustments'] ?> Adjustment<?= $kpis['pending_adjustments'] > 1 ? 's' : '' ?>
          </a>
          <?php endif; ?>
          <?php if (($kpis['pending_discrepancy_reviews'] ?? 0) > 0): ?>
          <a href="<?= url('gm/warehouses/discrepancies/pending-review') ?>" class="btn btn-danger btn-sm fw-semibold">
            <i class="bi bi-shield-exclamation me-1"></i><?= (int)$kpis['pending_discrepancy_reviews'] ?> Discrepancy Review<?= $kpis['pending_discrepancy_reviews'] > 1 ? 's' : '' ?>
          </a>
          <?php endif; ?>
          <?php if (!empty($pendingTransferDiscrepancyReviews)): ?>
          <a href="<?= url('gm/transfers/discrepancies/pending-review') ?>" class="btn btn-danger btn-sm fw-semibold">
            <i class="bi bi-exclamation-triangle-fill me-1"></i><?= count($pendingTransferDiscrepancyReviews) ?> Transfer Discrepanc<?= count($pendingTransferDiscrepancyReviews) > 1 ? 'ies' : 'y' ?>
          </a>
          <?php endif; ?>
          <a href="<?= url('gm/transfers') ?>" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left-right me-1"></i>Transfers</a>
          <a href="<?= url('gm/reports') ?>" class="btn btn-outline-light btn-sm"><i class="bi bi-file-bar-graph me-1"></i>Reports</a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- KPI Row -->
<div class="row g-2 mb-2"><div class="col-12"><span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:.06em;"><i class="bi bi-activity me-1 text-success"></i>Executive Summary</span></div></div>
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100" style="border-top:3px solid #198754!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small">Sales Today</span>
          <div class="rounded bg-success bg-opacity-10 p-1"><i class="bi bi-cart-check-fill text-success"></i></div>
        </div>
        <div class="fw-bold fs-3 lh-1"><?= $n($shopPerformance['sales_today'] ?? 0) ?></div>
        <div class="text-success small mt-1"><?= $c($shopPerformance['revenue_today'] ?? 0) ?></div>
        <div class="text-muted" style="font-size:.7rem;"><?= $n($shopPerformance['sales_this_month'] ?? 0) ?> this month</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100" style="border-top:3px solid #0d6efd!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small">Warehouse Stock</span>
          <div class="rounded bg-primary bg-opacity-10 p-1"><i class="bi bi-building text-primary"></i></div>
        </div>
        <div class="fw-bold fs-3 lh-1"><?= $n($warehousePerf['total_stock'] ?? 0) ?></div>
        <div class="text-primary small mt-1"><?= $n($warehousePerf['total_warehouses'] ?? 0) ?> warehouse<?= ($warehousePerf['total_warehouses'] ?? 0) != 1 ? 's' : '' ?></div>
        <div class="text-muted" style="font-size:.7rem;"><?= $n($warehousePerf['low_stock_lines'] ?? 0) ?> low stock lines</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100" style="border-top:3px solid #fd7e14!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small">Transfer Pipeline</span>
          <div class="rounded p-1" style="background:rgba(253,126,20,.12);"><i class="bi bi-arrow-left-right" style="color:#fd7e14;"></i></div>
        </div>
        <div class="fw-bold fs-3 lh-1"><?= $n(($transferPipeline['pending_approval'] ?? 0) + ($transferPipeline['approved'] ?? 0) + ($transferPipeline['in_transit'] ?? 0)) ?></div>
        <div class="mt-1" style="color:#fd7e14;font-size:.83rem;"><?= $n($transferPipeline['pending_approval'] ?? 0) ?> awaiting approval</div>
        <div class="text-muted" style="font-size:.7rem;"><?= $n($transferPipeline['in_transit'] ?? 0) ?> in transit</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100" style="border-top:3px solid #20c997!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small">Farm Production</span>
          <div class="rounded bg-success bg-opacity-10 p-1"><i class="bi bi-flower1 text-success"></i></div>
        </div>
        <div class="fw-bold fs-3 lh-1"><?= $n($farmPerformance['kg_this_month'] ?? 0) ?><small class="fs-6 fw-normal text-muted ms-1">kg</small></div>
        <div class="text-success small mt-1"><?= $n($farmPerformance['harvests_this_month'] ?? 0) ?> harvests this month</div>
        <div class="text-muted" style="font-size:.7rem;"><?= $n($farmPerformance['pending_dispatch'] ?? 0) ?> pending dispatch</div>
      </div>
    </div>
  </div>
</div>

<!-- Alerts -->
<?php
$alerts = [];
if (($transferPipeline['pending_approval'] ?? 0) > 0)
    $alerts[] = ['warning', 'bi-hourglass-split', ($transferPipeline['pending_approval']).' transfer(s) awaiting your approval.', url('gm/transfers/pending'), 'Review Now'];
if (!empty($pendingFarmDispatches))
    $alerts[] = ['warning', 'bi-flower1', count($pendingFarmDispatches).' farm dispatch request(s) awaiting your approval. No stock leaves the farm until you approve.', url('gm/farms/dispatches/pending'), 'Review Now'];
if (($kpis['pending_adjustments'] ?? 0) > 0)
    $alerts[] = ['warning', 'bi-sliders', ($kpis['pending_adjustments']).' stock adjustment(s) pending your approval.', url('gm/adjustments/pending'), 'Review Now'];
if (($kpis['pending_discrepancy_reviews'] ?? 0) > 0)
    $alerts[] = ['danger', 'bi-exclamation-triangle-fill', ($kpis['pending_discrepancy_reviews']).' receiving discrepancy(ies) require your review before warehouse inventory can be updated.', url('gm/warehouses/discrepancies/pending-review'), 'Review Now'];
if (!empty($pendingTransferDiscrepancyReviews))
    $alerts[] = ['danger', 'bi-exclamation-triangle-fill', count($pendingTransferDiscrepancyReviews).' transfer receiving discrepancy(ies) require your approval before shop inventory is updated.', url('gm/transfers/discrepancies/pending-review'), 'Review Now'];
if (($warehousePerf['open_discrepancies'] ?? 0) > 0)
    $alerts[] = ['danger', 'bi-clipboard-x-fill', ($warehousePerf['open_discrepancies']).' unresolved warehouse discrepancy(ies).', url('warehouse/discrepancies'), 'Review'];
if (($warehousePerf['low_stock_lines'] ?? 0) > 0)
    $alerts[] = ['warning', 'bi-exclamation-triangle-fill', ($warehousePerf['low_stock_lines']).' product line(s) at low warehouse stock.', url('gm/inventory'), 'View'];
if (($warehousePerf['out_of_stock_lines'] ?? 0) > 0)
    $alerts[] = ['danger', 'bi-slash-circle-fill', ($warehousePerf['out_of_stock_lines']).' product line(s) out of stock.', url('gm/inventory'), 'View'];
if (($shopPerformance['shop_low_stock'] ?? 0) > 0)
    $alerts[] = ['warning', 'bi-shop', ($shopPerformance['shop_low_stock']).' shop product line(s) at low stock.', url('gm/shops'), 'View'];
?>
<?php if (!empty($alerts)): ?>
<div class="row g-2 mb-4">
  <div class="col-12"><span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:.06em;"><i class="bi bi-bell-fill me-1 text-danger"></i>Attention Required</span></div>
  <?php foreach ($alerts as [$color, $icon, $msg, $href, $btn]): ?>
  <div class="col-12">
    <div class="alert alert-<?= $color ?> d-flex align-items-center justify-content-between py-2 mb-0" role="alert">
      <div class="d-flex align-items-center gap-2"><i class="bi <?= $icon ?> flex-shrink-0"></i><span class="small"><?= e($msg) ?></span></div>
      <a href="<?= $href ?>" class="btn btn-<?= $color ?> btn-sm py-0 px-2 flex-shrink-0 ms-2"><?= $btn ?></a>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Pending Approvals -->
<?php if (!empty($pendingApprovals)): ?>
<div class="row g-3 mb-4">
  <div class="col-12">
    <div class="card border-0 shadow-sm" style="border-left:4px solid #ffc107!important;">
      <div class="card-header d-flex justify-content-between align-items-center bg-warning bg-opacity-10 py-2">
        <span class="fw-semibold small"><i class="bi bi-hourglass-split me-2 text-warning"></i>Pending Transfer Approvals &mdash; Your Action Required <span class="badge bg-warning text-dark ms-1"><?= count($pendingApprovals) ?></span></span>
        <a href="<?= url('gm/transfers/pending') ?>" class="btn btn-outline-warning btn-sm">View All</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr><th class="ps-3">Transfer #</th><th>From</th><th>To</th><th class="text-end">Items</th><th class="text-end">Qty</th><th>Requested By</th><th>Waiting</th><th class="pe-3 text-end">Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach (array_slice($pendingApprovals, 0, 5) as $t): ?>
              <tr>
                <td class="ps-3 small fw-semibold"><a href="<?= url('transfers/'.$t['id']) ?>"><?= e($t['transfer_number'] ?? '#'.$t['id']) ?></a></td>
                <td class="small text-muted"><?= e($t['warehouse_name'] ?? '—') ?></td>
                <td class="small"><?= e($t['shop_name'] ?? '—') ?></td>
                <td class="small text-end"><?= (int)($t['total_items'] ?? 0) ?></td>
                <td class="small text-end fw-semibold"><?= $n($t['total_quantity'] ?? 0, 1) ?></td>
                <td class="small text-muted"><?= e($t['initiated_by_name'] ?? '—') ?></td>
                <td class="small"><?php $days = (int)($t['days_waiting'] ?? 0); ?><span class="badge <?= $days > 2 ? 'bg-danger' : ($days > 0 ? 'bg-warning text-dark' : 'bg-secondary') ?>"><?= $days === 0 ? 'Today' : $days.'d' ?></span></td>
                <td class="pe-3 text-end">
                  <a href="<?= url('transfers/'.$t['id']) ?>" class="btn btn-outline-secondary btn-sm py-0 px-2 me-1">Detail</a>
                  <form method="POST" action="<?= url('gm/transfers/'.$t['id'].'/approve') ?>" class="d-inline" onsubmit="return confirm('Approve this transfer?')">
                    <?php csrf_input(); ?>
                    <button class="btn btn-success btn-sm py-0 px-2"><i class="bi bi-check-lg"></i> Approve</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Pending Farm Dispatch Approvals — GM Action Required -->
<?php if (!empty($pendingFarmDispatches)): ?>
<div class="row g-3 mb-4">
  <div class="col-12">
    <div class="card border-0 shadow-sm" style="border-left:4px solid #198754!important;">
      <div class="card-header d-flex justify-content-between align-items-center bg-success bg-opacity-10 py-2">
        <span class="fw-semibold small">
          <i class="bi bi-flower1 me-2 text-success"></i>
          Pending Farm Dispatch Approvals &mdash; Your Action Required
          <span class="badge bg-warning text-dark ms-1"><?= count($pendingFarmDispatches) ?></span>
        </span>
        <a href="<?= url('gm/farms/dispatches/pending') ?>" class="btn btn-outline-success btn-sm">View All</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Dispatch #</th>
                <th>Farm</th>
                <th>Destination</th>
                <th class="text-end">Batches</th>
                <th class="text-end">Total Qty</th>
                <th>Requested By</th>
                <th>Waiting</th>
                <th class="pe-3 text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_slice($pendingFarmDispatches, 0, 5) as $fd): ?>
              <tr>
                <td class="ps-3 small fw-semibold font-monospace">
                  <a href="<?= url('dispatches/'.$fd['id']) ?>" class="text-decoration-none">
                    <?= e($fd['dispatch_number'] ?? '#'.$fd['id']) ?>
                  </a>
                </td>
                <td class="small fw-semibold"><?= e($fd['farm_name'] ?? '—') ?></td>
                <td class="small text-muted"><?= e($fd['warehouse_name'] ?? '—') ?></td>
                <td class="small text-end"><?= (int)($fd['item_count'] ?? 0) ?></td>
                <td class="small text-end fw-semibold"><?= number_format((float)($fd['total_qty'] ?? 0), 2) ?></td>
                <td class="small text-muted"><?= e($fd['initiated_by_name'] ?? '—') ?></td>
                <td class="small">
                  <?php $days = (int)($fd['days_waiting'] ?? 0); ?>
                  <span class="badge <?= $days > 2 ? 'bg-danger' : ($days > 0 ? 'bg-warning text-dark' : 'bg-secondary') ?>">
                    <?= $days === 0 ? 'Today' : $days.'d' ?>
                  </span>
                </td>
                <td class="pe-3 text-end">
                  <a href="<?= url('dispatches/'.$fd['id']) ?>"
                     class="btn btn-outline-secondary btn-sm py-0 px-2 me-1">Detail</a>
                  <form method="POST" action="<?= url('dispatches/'.$fd['id'].'/approve') ?>"
                        class="d-inline"
                        onsubmit="return confirm('Approve dispatch <?= e($fd['dispatch_number'] ?? '') ?>?')">
                    <?php csrf_input(); ?>
                    <button class="btn btn-success btn-sm py-0 px-2">
                      <i class="bi bi-check-lg"></i>
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer bg-success bg-opacity-10 py-2 small text-success">
          <i class="bi bi-info-circle me-1"></i>
          Farm stock is <strong>not deducted</strong> until the Farm Manager physically dispatches after your approval.
          <?php if (count($pendingFarmDispatches) > 5): ?>
          <a href="<?= url('gm/farms/dispatches/pending') ?>" class="ms-2 text-success">
            +<?= count($pendingFarmDispatches) - 5 ?> more &rarr;
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Pending Discrepancy Reviews — GM Action Required -->
<?php if (!empty($pendingDiscrepancyReviews)): ?>
<div class="row g-3 mb-4">
  <div class="col-12">
    <div class="card border-0 shadow-sm" style="border-left:4px solid #dc3545!important;">
      <div class="card-header d-flex justify-content-between align-items-center bg-danger bg-opacity-10 py-2">
        <span class="fw-semibold small">
          <i class="bi bi-shield-exclamation me-2 text-danger"></i>
          Receiving Discrepancies — Your Approval Required
          <span class="badge bg-danger ms-1"><?= count($pendingDiscrepancyReviews) ?></span>
        </span>
        <a href="<?= url('gm/warehouses/discrepancies/pending-review') ?>"
           class="btn btn-outline-danger btn-sm">View All</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Receiving #</th>
                <th>Farm</th>
                <th>Warehouse</th>
                <th>Products</th>
                <th class="text-end">Expected</th>
                <th class="text-end">Actual</th>
                <th class="text-end">Missing</th>
                <th>Dispatch Date</th>
                <th class="pe-3 text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_slice($pendingDiscrepancyReviews, 0, 5) as $dr): ?>
              <tr>
                <td class="ps-3 small fw-semibold text-danger">
                  <?= e($dr['receiving_number']) ?>
                </td>
                <td class="small fw-semibold"><?= e($dr['farm_name'] ?? '—') ?></td>
                <td class="small"><?= e($dr['warehouse_name'] ?? '—') ?></td>
                <td class="small text-muted"><?= e($dr['product_names'] ?? '—') ?></td>
                <td class="small fw-bold text-primary text-end"><?= number_format((float)$dr['total_expected'], 3) ?></td>
                <td class="small fw-bold text-success text-end"><?= number_format((float)$dr['total_received'], 3) ?></td>
                <td class="small fw-bold text-danger text-end"><?= number_format((float)$dr['total_missing'], 3) ?></td>
                <td class="small"><?= !empty($dr['dispatch_date']) ? date('d/m/Y', strtotime($dr['dispatch_date'])) : '—' ?></td>
                <td class="pe-3 text-end">
                  <a href="<?= url('gm/warehouses/receiving/' . $dr['receiving_id'] . '/discrepancy-review') ?>"
                     class="btn btn-danger btn-sm py-0 px-2">
                    <i class="bi bi-shield-check me-1"></i>Review
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer bg-danger bg-opacity-10 py-2 small text-danger">
          <i class="bi bi-info-circle me-1"></i>
          Warehouse inventory is <strong>blocked</strong> until you approve or reject each discrepancy.
          Approving allows the Warehouse Manager to complete receiving with the actual quantity only.
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Business Flow: Farm + Warehouse -->
<div class="row g-2 mb-2 mt-2"><div class="col-12"><span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:.06em;"><i class="bi bi-diagram-2-fill me-1 text-info"></i>Business Flow Overview</span></div></div>
<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header d-flex justify-content-between align-items-center py-2">
        <span class="fw-semibold small"><i class="bi bi-flower1 me-2 text-success"></i>Farm Performance</span>
        <a href="<?= url('gm/farms') ?>" class="btn btn-outline-success btn-sm">View Farms</a>
      </div>
      <div class="card-body pb-2">
        <div class="row g-2">
          <?php foreach ([
            [$farmPerformance['total_farms'] ?? 0,                    'Farms',               'success','bi-flower1'],
            [$farmPerformance['harvests_this_month'] ?? 0,            'Harvests / Month',    'success','bi-basket2-fill'],
            [$n($farmPerformance['kg_this_month'] ?? 0).' kg',        'Kg Produced',         'success','bi-boxes'],
            [$farmPerformance['pending_dispatch'] ?? 0,               'Pending Dispatch',    'warning','bi-truck'],
            [$farmPerformance['dispatches_this_month'] ?? 0,          'Dispatches / Month',  'info',   'bi-send-fill'],
            [$n($farmPerformance['dispatched_kg_this_month'] ?? 0).' kg','Dispatched Kg',    'primary','bi-arrow-right-circle'],
          ] as [$val,$lbl,$col,$ico]): ?>
          <div class="col-4">
            <div class="rounded border text-center py-2 px-1">
              <i class="bi <?= $ico ?> text-<?= $col ?> d-block mb-1" style="font-size:1.1rem;"></i>
              <div class="fw-bold small"><?= is_numeric($val) ? number_format((float)$val) : e($val) ?></div>
              <div class="text-muted" style="font-size:.65rem;"><?= $lbl ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header d-flex justify-content-between align-items-center py-2">
        <span class="fw-semibold small"><i class="bi bi-building me-2 text-primary"></i>Warehouse Performance</span>
        <a href="<?= url('gm/warehouses') ?>" class="btn btn-outline-primary btn-sm">View Warehouses</a>
      </div>
      <div class="card-body pb-2">
        <div class="row g-2">
          <?php foreach ([
            [$warehousePerf['total_warehouses'] ?? 0,   'Warehouses',          'primary','bi-building'],
            [$n($warehousePerf['total_stock'] ?? 0),    'Total Stock Units',   'primary','bi-boxes'],
            [$warehousePerf['pending_receipts'] ?? 0,   'Pending Receipts',    'warning','bi-inbox-fill'],
            [$warehousePerf['open_discrepancies'] ?? 0, 'Open Discrepancies',  'danger', 'bi-clipboard-x'],
            [$warehousePerf['low_stock_lines'] ?? 0,    'Low Stock Lines',     'warning','bi-exclamation-triangle'],
            [$warehousePerf['expiring_7d'] ?? 0,        'Expiring ≤7 Days',    'danger', 'bi-calendar-x'],
          ] as [$val,$lbl,$col,$ico]): ?>
          <div class="col-4">
            <div class="rounded border text-center py-2 px-1">
              <i class="bi <?= $ico ?> text-<?= $col ?> d-block mb-1" style="font-size:1.1rem;"></i>
              <div class="fw-bold small"><?= is_numeric($val) ? number_format((float)$val) : e($val) ?></div>
              <div class="text-muted" style="font-size:.65rem;"><?= $lbl ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Transfer Pipeline + Shop Performance -->
<div class="row g-3 mb-4">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header d-flex justify-content-between align-items-center py-2">
        <span class="fw-semibold small"><i class="bi bi-arrow-left-right me-2 text-warning"></i>Transfer Pipeline</span>
        <a href="<?= url('gm/transfers') ?>" class="btn btn-outline-warning btn-sm">All Transfers</a>
      </div>
      <div class="card-body p-0">
        <?php foreach ([
          ['Pending Approval', $transferPipeline['pending_approval'] ?? 0, 'warning','bi-hourglass-split',   url('gm/transfers/pending'), true],
          ['Approved',         $transferPipeline['approved'] ?? 0,         'success','bi-check-circle-fill', url('gm/transfers?status=approved'), false],
          ['In Transit',       $transferPipeline['in_transit'] ?? 0,       'info',   'bi-truck',             url('gm/transfers?status=in_transit'), false],
          ['Received Today',   $transferPipeline['received_today'] ?? 0,   'primary','bi-box-arrow-in-down', url('gm/transfers?status=received'), false],
          ['W/ Discrepancy',   $transferPipeline['with_discrepancy'] ?? 0, 'danger', 'bi-exclamation-diamond',url('gm/transfers?status=received_with_discrepancy'), false],
          ['Received / Month', $transferPipeline['received_this_month']??0,'secondary','bi-calendar-check',  url('gm/transfers'), false],
        ] as [$lbl,$cnt,$col,$ico,$href,$badge]): ?>
        <a href="<?= $href ?>" class="d-flex align-items-center gap-3 px-3 py-2 border-bottom text-decoration-none text-dark">
          <div class="rounded bg-<?= $col ?> bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0" style="width:30px;height:30px;">
            <i class="bi <?= $ico ?> text-<?= $col ?>" style="font-size:.8rem;"></i>
          </div>
          <div class="flex-grow-1"><div class="small fw-semibold"><?= $lbl ?></div></div>
          <span class="badge <?= $cnt > 0 ? 'bg-'.$col.($col==='warning'?' text-dark':'') : 'bg-secondary bg-opacity-25 text-secondary' ?> flex-shrink-0"><?= number_format($cnt) ?></span>
          <?php if ($badge && $cnt > 0): ?><span class="badge bg-danger ms-1 flex-shrink-0" style="font-size:.55rem;">ACTION</span><?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header d-flex justify-content-between align-items-center py-2">
        <span class="fw-semibold small"><i class="bi bi-shop me-2 text-warning"></i>Shop Performance &mdash; This Month</span>
        <a href="<?= url('gm/shops') ?>" class="btn btn-outline-warning btn-sm">View Shops</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($shopSalesBreakdown)): ?>
        <div class="text-center py-4 text-muted"><i class="bi bi-shop d-block fs-3 mb-2 opacity-25"></i><small>No shop sales data yet.</small></div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light"><tr><th class="ps-3">Shop</th><th class="text-end">Transactions</th><th class="text-end pe-3">Revenue (ETB)</th></tr></thead>
            <tbody>
              <?php foreach ($shopSalesBreakdown as $s): ?>
              <tr>
                <td class="ps-3 small fw-semibold"><a href="<?= url('shops/'.$s['id']) ?>" class="text-decoration-none"><?= e($s['shop_name'] ?? '—') ?></a></td>
                <td class="text-end small text-muted"><?= number_format((int)$s['tx_count']) ?></td>
                <td class="text-end small fw-semibold pe-3"><?= number_format((float)$s['revenue'], 2) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light"><tr><td class="ps-3 small fw-bold">Total</td><td class="text-end small fw-bold"><?= number_format(array_sum(array_column($shopSalesBreakdown,'tx_count'))) ?></td><td class="text-end small fw-bold pe-3"><?= number_format(array_sum(array_column($shopSalesBreakdown,'revenue')), 2) ?></td></tr></tfoot>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Customer Orders Section (new — integrates customer/shop order workflow) -->
<?php if (($customerOrderKpis['orders_this_month'] ?? 0) > 0 || ($customerOrderKpis['active_orders'] ?? 0) > 0): ?>
<div class="row g-2 mb-2 mt-2">
  <div class="col-12">
    <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:.06em;">
      <i class="bi bi-bag-check-fill me-1 text-info"></i>Customer Orders
    </span>
  </div>
</div>
<div class="row g-3 mb-4">

  <!-- Customer Order KPIs strip -->
  <div class="col-12">
    <div class="card border-0 shadow-sm" style="border-left:4px solid #0dcaf0!important;">
      <div class="card-body py-2 px-3">
        <div class="row g-2 text-center">
          <?php
          $coKpiItems = [
            [$customerOrderKpis['orders_today']      ?? 0, 'Orders Today',      'warning',   'bi-clock-history'],
            [$customerOrderKpis['active_orders']     ?? 0, 'Active Queue',      'info',      'bi-hourglass-split'],
            [$customerOrderKpis['ready_for_pickup']  ?? 0, 'Ready for Pickup',  'success',   'bi-bag-check-fill'],
            [$customerOrderKpis['completed_today']   ?? 0, 'Completed Today',   'success',   'bi-check2-circle'],
            [$customerOrderKpis['orders_this_month'] ?? 0, 'Orders / Month',    'primary',   'bi-calendar-month'],
            ['ETB '.number_format((float)($customerOrderKpis['revenue_this_month'] ?? 0), 2), 'Revenue / Month', 'success', 'bi-currency-exchange'],
          ];
          foreach ($coKpiItems as [$val, $lbl, $col, $ico]):
          ?>
          <div class="col-6 col-md-4 col-xl-2">
            <div class="rounded border py-2 px-1 h-100">
              <i class="bi <?= $ico ?> text-<?= $col ?> d-block mb-1" style="font-size:1.1rem;"></i>
              <div class="fw-bold small"><?= is_numeric($val) ? number_format((float)$val) : e($val) ?></div>
              <div class="text-muted" style="font-size:.65rem;"><?= $lbl ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="card-footer bg-white border-top py-1 px-3 text-end">
        <a href="<?= url('gm/orders') ?>" class="btn btn-outline-info btn-sm">
          <i class="bi bi-bag-check-fill me-1"></i>View All Orders
        </a>
        <?php if (($customerOrderKpis['active_orders'] ?? 0) > 0): ?>
        <a href="<?= url('gm/orders?status=active') ?>" class="btn btn-info btn-sm ms-1">
          <i class="bi bi-hourglass-split me-1"></i><?= (int)($customerOrderKpis['active_orders']) ?> Active
        </a>
        <?php endif; ?>
        <?php if (($customerOrderKpis['ready_for_pickup'] ?? 0) > 0): ?>
        <a href="<?= url('gm/orders?status=ready_for_pickup') ?>" class="btn btn-success btn-sm ms-1">
          <i class="bi bi-bell-fill me-1"></i><?= (int)($customerOrderKpis['ready_for_pickup']) ?> Ready
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Customer Orders by Shop + Recent Orders -->
  <?php if (!empty($customerOrdersByShop) || !empty($recentCustomerOrders)): ?>
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header d-flex justify-content-between align-items-center py-2">
        <span class="fw-semibold small">
          <i class="bi bi-shop me-2 text-info"></i>Orders by Shop — This Month
        </span>
        <a href="<?= url('gm/orders') ?>" class="btn btn-outline-info btn-sm py-0 px-2">All</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($customerOrdersByShop)): ?>
        <div class="text-center py-3 text-muted"><small>No orders this month.</small></div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Shop</th>
                <th class="text-center">Active</th>
                <th class="text-center">Done</th>
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
                  <?php $active = (int)($cs['active_orders'] ?? 0); ?>
                  <?php if ($active > 0): ?>
                  <span class="badge bg-info text-dark"><?= $active ?></span>
                  <?php else: ?>
                  <span class="text-muted">0</span>
                  <?php endif; ?>
                </td>
                <td class="text-center small text-success fw-semibold">
                  <?= (int)($cs['completed_orders'] ?? 0) ?>
                </td>
                <td class="text-end small fw-semibold pe-3">
                  <?php $rev = (float)($cs['completed_revenue'] ?? 0); ?>
                  <?= $rev > 0 ? 'ETB '.number_format($rev, 2) : '<span class="text-muted">—</span>' ?>
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

  <div class="col-lg-7">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header d-flex justify-content-between align-items-center py-2">
        <span class="fw-semibold small">
          <i class="bi bi-clock-history me-2 text-info"></i>Recent Customer Orders
        </span>
        <a href="<?= url('gm/orders') ?>" class="btn btn-outline-info btn-sm py-0 px-2">All Orders</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($recentCustomerOrders)): ?>
        <div class="text-center py-3 text-muted">
          <i class="bi bi-bag d-block fs-3 mb-2 opacity-25"></i>
          <small>No customer orders yet.</small>
        </div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0 align-middle">
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
                    <?= e($co['order_reference'] ?? '#'.$co['id']) ?>
                  </a>
                </td>
                <td class="small"><?= e($co['customer_name'] ?? '—') ?></td>
                <td class="small text-muted"><?= e($co['shop_name'] ?? '—') ?></td>
                <td class="small fw-semibold text-end">
                  ETB <?= number_format((float)($co['total_amount'] ?? 0), 2) ?>
                </td>
                <td class="pe-3">
                  <span class="badge bg-<?= $badge ?> rounded-pill" style="font-size:.62rem;">
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
  <?php endif; ?>

</div><!-- /.row (customer orders) -->
<?php endif; ?>

<!-- Revenue Trend + Top Products -->
<div class="row g-2 mb-2 mt-2"><div class="col-12"><span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:.06em;"><i class="bi bi-bar-chart-fill me-1 text-success"></i>Sales Performance</span></div></div>
<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center py-2">
        <span class="fw-semibold small"><i class="bi bi-bar-chart me-2 text-success"></i>Revenue Trend — Last 30 Days</span>
        <a href="<?= url('gm/analytics') ?>" class="btn btn-outline-success btn-sm">Analytics</a>
      </div>
      <div class="card-body py-3">
        <canvas id="gmRevChart" style="max-height:220px;"></canvas>
        <div id="gmRevEmpty" class="text-center py-4 text-muted d-none"><i class="bi bi-bar-chart d-block fs-3 mb-2 opacity-25"></i><small>No revenue data yet.</small></div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header d-flex justify-content-between align-items-center py-2">
        <span class="fw-semibold small"><i class="bi bi-trophy-fill me-2 text-warning"></i>Top Products</span>
        <a href="<?= url('gm/analytics/products') ?>" class="btn btn-outline-warning btn-sm">All</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($topProducts)): ?>
        <div class="text-center py-4 text-muted"><i class="bi bi-box-seam d-block fs-3 mb-2 opacity-25"></i><small>No sales data yet.</small></div>
        <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($topProducts as $i => $p): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 border-0 border-bottom">
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-secondary rounded-pill" style="min-width:22px;font-size:.65rem;"><?= $i+1 ?></span>
              <div>
                <div class="small fw-semibold"><?= e($p['product_name'] ?? '—') ?></div>
                <div class="text-muted" style="font-size:.68rem;"><?= $n($p['qty_sold'] ?? 0, 1) ?> units sold</div>
              </div>
            </div>
            <span class="small fw-bold text-success"><?= $c($p['revenue'] ?? 0) ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Stock Alerts + Recent Activity -->
<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header d-flex justify-content-between align-items-center py-2">
        <span class="fw-semibold small"><i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i>Stock Alerts</span>
        <a href="<?= url('gm/inventory') ?>" class="btn btn-outline-warning btn-sm">View Inventory</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($stockAlerts)): ?>
        <div class="text-center py-4 text-muted"><i class="bi bi-check-circle-fill d-block fs-3 mb-2 text-success opacity-50"></i><small>All stock levels are healthy.</small></div>
        <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($stockAlerts as $a): ?>
          <li class="list-group-item d-flex align-items-center justify-content-between py-2 px-3 border-0 border-bottom">
            <div>
              <div class="small fw-semibold"><?= e($a['product_name'] ?? '—') ?></div>
              <div class="text-muted" style="font-size:.68rem;">WH: <?= $n($a['warehouse_qty'] ?? 0) ?> &bull; Shop: <?= $n($a['shop_qty'] ?? 0) ?></div>
            </div>
            <span class="badge <?= ($a['stock_status'] ?? '') === 'out_of_stock' ? 'bg-danger' : 'bg-warning text-dark' ?>">
              <?= ($a['stock_status'] ?? '') === 'out_of_stock' ? 'Out of Stock' : 'Low Stock' ?>
            </span>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header py-2">
        <span class="fw-semibold small"><i class="bi bi-clock-history me-2 text-secondary"></i>Recent Activity</span>
      </div>
      <div class="card-body p-0">
        <?php if (empty($recentActivity)): ?>
        <div class="text-center py-4 text-muted"><i class="bi bi-activity d-block fs-3 mb-2 opacity-25"></i><small>No recent activity.</small></div>
        <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php
          $typeMap = ['sale'=>['success','bi-cart-check'],'transfer'=>['warning','bi-arrow-left-right'],'harvest'=>['info','bi-basket2'],'receiving'=>['primary','bi-inbox']];
          foreach (array_slice($recentActivity, 0, 8) as $ev):
            [$col,$ico] = $typeMap[$ev['event_type'] ?? ''] ?? ['secondary','bi-dot'];
          ?>
          <li class="list-group-item py-2 px-3 border-0 border-bottom">
            <div class="d-flex align-items-center gap-2">
              <div class="rounded bg-<?= $col ?> bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0" style="width:28px;height:28px;">
                <i class="bi <?= $ico ?> text-<?= $col ?>" style="font-size:.75rem;"></i>
              </div>
              <div class="flex-grow-1">
                <div class="small fw-semibold"><?= ucfirst($ev['event_type'] ?? '') ?> — <?= e($ev['location_name'] ?? '—') ?></div>
                <div class="text-muted" style="font-size:.68rem;"><?= e($ev['actor'] ?? '') ?> &bull; <?= $dt($ev['event_at'] ?? null) ?></div>
              </div>
              <?php if (!empty($ev['amount'])): ?>
              <span class="small text-<?= $col ?> fw-semibold flex-shrink-0"><?= $n($ev['amount'], 1) ?></span>
              <?php endif; ?>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  fetch('<?= url('api/gm/revenue-trend') ?>')
    .then(r => r.json())
    .then(res => {
      if (!res.success || !res.data || !res.data.labels || !res.data.labels.length) {
        document.getElementById('gmRevEmpty').classList.remove('d-none');
        return;
      }
      const d = res.data;
      new Chart(document.getElementById('gmRevChart'), {
        type: 'bar',
        data: {
          labels: d.labels,
          datasets: [{
            label: 'Revenue (ETB)',
            data: d.revenue,
            backgroundColor: 'rgba(25,135,84,.7)',
            borderColor: '#198754',
            borderWidth: 1,
            borderRadius: 3
          }]
        },
        options: {
          responsive: true,
          animation: false,
          plugins: { legend: { display: false } },
          scales: {
            y: { beginAtZero: true, ticks: { callback: v => 'ETB ' + v.toLocaleString() } },
            x: { ticks: { maxRotation: 45, font: { size: 10 } } }
          }
        }
      });
    })
    .catch(() => document.getElementById('gmRevEmpty').classList.remove('d-none'));
});
</script>
