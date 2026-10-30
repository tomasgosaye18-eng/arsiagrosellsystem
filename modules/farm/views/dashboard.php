<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 9A
 * =========================================================
 * File     : modules/farm/views/dashboard.php
 * Purpose  : Farm Manager operational dashboard.
 *            All data is scoped to the authenticated user's
 *            assigned farm — no cross-farm data is shown.
 *
 * @var \AAM\Core\View    $view
 * @var string            $userName
 * @var string            $userRole
 * @var array|null        $farm                  Assigned farm record
 * @var int               $farmId                0 = admin/GM (all farms)
 * @var array             $kpis                  Aggregated counters
 * @var array             $recentHarvests         Recent harvest rows
 * @var array             $dispatchSummary        status => count map
 * @var array             $productionByProduct    Top products by kg
 * =========================================================
 */
declare(strict_types=1);

$kpis                ??= [];
$recentHarvests      ??= [];
$dispatchSummary     ??= [];
$productionByProduct ??= [];
$farm                ??= null;
$farmId              ??= 0;

$fmt     = fn(mixed $v): string => is_numeric($v) ? number_format((float)$v) : ($v ?? '—');
$fmtKg   = fn(float $v): string => number_format($v, 1) . ' kg';

$farmName     = $farm['name']     ?? 'Unassigned Farm';
$farmLocation = $farm['location'] ?? '';
$farmType     = $farm['farm_type'] ?? '';

$pendingDispatch = (int)($kpis['pending_dispatch'] ?? 0);
?>

<!-- ══════════════════════════════════════════════════════════
     WELCOME BANNER
     ══════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
  <div class="col-12">
    <div class="card border-0 shadow-sm"
         style="background:linear-gradient(135deg,#198754 0%,#20c997 100%);color:#fff;">
      <div class="card-body d-flex align-items-center gap-3 py-3">
        <div class="rounded-circle bg-white bg-opacity-20 d-flex align-items-center
                    justify-content-center flex-shrink-0"
             style="width:52px;height:52px;">
          <i class="bi bi-flower1 fs-4"></i>
        </div>
        <div class="flex-grow-1 overflow-hidden">
          <h5 class="mb-0 fw-bold text-truncate"><?= e($farmName) ?></h5>
          <small class="opacity-75">
            Farm Manager: <?= e($userName) ?>
            <?php if ($farmLocation): ?>&nbsp;&middot;&nbsp;<?= e($farmLocation) ?><?php endif; ?>
            <?php if ($farmType): ?>&nbsp;&middot;&nbsp;<?= e($farmType) ?><?php endif; ?>
            &nbsp;&middot;&nbsp;<?= date('l, d F Y') ?>
          </small>
        </div>
        <div class="d-none d-md-flex gap-2 flex-wrap">
          <?php
          $dashFarmId = $farmId > 0 ? $farmId : null;
          ?>
          <a href="<?= $dashFarmId ? url('farms/' . $dashFarmId . '/harvests/create') : url('farms') ?>"
             class="btn btn-light btn-sm fw-semibold">
            <i class="bi bi-basket-fill me-1"></i>Record Harvest
          </a>
          <a href="<?= $dashFarmId ? url('farms/' . $dashFarmId . '/dispatches/create') : url('farms') ?>"
             class="btn btn-outline-light btn-sm">
            <i class="bi bi-truck me-1"></i>Dispatch
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($farmId === 0 && !in_array($userRole ?? '', [ROLE_ADMIN, ROLE_GENERAL_MANAGER])): ?>
<!-- ── No Assignment Warning ──────────────────────────────── -->
<div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
  <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
  <div>
    <strong>No farm assigned.</strong>
    You have not been assigned to a farm yet. Please contact the system administrator.
  </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════
     LEVEL 1 — CRITICAL FARM KPIs
     ══════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">

  <!-- Today's Harvests -->
  <div class="col-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100"
         style="border-top:3px solid #198754!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small">Today's Harvests</span>
          <div class="rounded bg-success bg-opacity-10 p-1">
            <i class="bi bi-basket-fill text-success"></i>
          </div>
        </div>
        <div class="fw-bold fs-3 lh-1"><?= $fmt($kpis['today_harvests'] ?? 0) ?></div>
        <div class="text-success small mt-1"><?= $fmtKg((float)($kpis['total_kg_today'] ?? 0)) ?></div>
        <div class="text-muted" style="font-size:.7rem;">batches recorded today</div>
      </div>
    </div>
  </div>

  <!-- Monthly Production -->
  <div class="col-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100"
         style="border-top:3px solid #20c997!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small">This Month</span>
          <div class="rounded bg-success bg-opacity-10 p-1" style="--bs-bg-opacity:.08;">
            <i class="bi bi-calendar-check text-success"></i>
          </div>
        </div>
        <div class="fw-bold fs-3 lh-1"><?= $fmt($kpis['monthly_harvests'] ?? 0) ?></div>
        <div class="text-success small mt-1"><?= $fmtKg((float)($kpis['total_kg_this_month'] ?? 0)) ?></div>
        <div class="text-muted" style="font-size:.7rem;">harvests this month</div>
      </div>
    </div>
  </div>

  <!-- Pending Dispatch -->
  <div class="col-6 col-xl-3">
    <?php $alertPending = $pendingDispatch > 0; ?>
    <div class="card border-0 shadow-sm h-100"
         style="border-top:3px solid <?= $alertPending ? '#ffc107' : '#dee2e6' ?>!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small">Awaiting Dispatch</span>
          <div class="rounded p-1" style="background:rgba(255,193,7,.12);">
            <i class="bi bi-hourglass-split text-warning"></i>
          </div>
        </div>
        <div class="fw-bold fs-3 lh-1 <?= $alertPending ? 'text-warning' : '' ?>">
          <?= $fmt($pendingDispatch) ?>
        </div>
        <div class="text-muted small mt-1">batches ready to dispatch</div>
        <?php if ($alertPending): ?>
        <a href="<?= $farmId > 0 ? url('farms/' . $farmId . '/dispatches') : url('farms') ?>"
           class="text-warning d-block"
           style="font-size:.7rem;">Dispatch now →</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Dispatched This Month -->
  <div class="col-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100"
         style="border-top:3px solid #0d6efd!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small">Dispatched</span>
          <div class="rounded bg-primary bg-opacity-10 p-1">
            <i class="bi bi-send-check-fill text-primary"></i>
          </div>
        </div>
        <div class="fw-bold fs-3 lh-1"><?= $fmt($kpis['dispatched_this_month'] ?? 0) ?></div>
        <div class="text-muted small mt-1">sent to warehouse this month</div>
        <div class="text-muted" style="font-size:.7rem;">
          <?= $fmt($kpis['total_products'] ?? 0) ?> product type(s)
        </div>
      </div>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════════
     LEVEL 2 — ALERTS
     ══════════════════════════════════════════════════════════ -->
<?php if ($pendingDispatch > 0): ?>
<div class="row g-2 mb-4">
  <div class="col-12">
    <div class="alert alert-warning d-flex align-items-center
                justify-content-between py-2 mb-0" role="alert">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
        <span class="small">
          <strong><?= $pendingDispatch ?></strong>
          harvest batch<?= $pendingDispatch > 1 ? 'es are' : ' is' ?> ready and waiting to be
          dispatched to the central warehouse.
        </span>
      </div>
        <a href="<?= $pendingDispatch > 0 && $farmId > 0 ? url('farms/' . $farmId . '/dispatches') : url('farms') ?>"
         class="btn btn-warning btn-sm py-0 px-2 flex-shrink-0 ms-2">
        Dispatch Now
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════
     LEVEL 3 — RECENT HARVESTS + QUICK ACTIONS
     ══════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">

  <!-- Recent Harvests table -->
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold small">
          <i class="bi bi-basket me-2 text-success"></i>Recent Harvests
        </span>
        <a href="<?= $farmId > 0 ? url('farms/' . $farmId . '/harvests') : url('farms') ?>"
           class="btn btn-outline-success btn-sm">View All Farms</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($recentHarvests)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-basket display-6 d-block mb-2 opacity-25"></i>
            <small>No harvest records yet. Start recording harvests from your farm.</small>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th class="ps-3 small fw-semibold">Batch / Product</th>
                  <th class="small fw-semibold">Qty (kg)</th>
                  <th class="small fw-semibold">Date</th>
                  <th class="small fw-semibold">Status</th>
                  <th class="small fw-semibold pe-3">Recorded by</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentHarvests as $h):
                  $statusMap = [
                    'active'               => ['success',   'Active'],
                    'partially_dispatched' => ['warning',   'Partial Dispatch'],
                    'fully_dispatched'     => ['primary',   'Fully Dispatched'],
                    'depleted'             => ['secondary', 'Depleted'],
                    'expired'              => ['danger',    'Expired'],
                    'quarantine'           => ['dark',      'Quarantine'],
                  ];
                  [$sc, $sl] = $statusMap[$h['status'] ?? ''] ?? ['secondary', ucfirst($h['status'] ?? '—')];
                  $recorder = trim(($h['first_name'] ?? '') . ' ' . ($h['last_name'] ?? ''));
                ?>
                <tr>
                  <td class="ps-3 small fw-semibold">
                    <?= e($h['product_name'] ?? '—') ?>
                    <?php if (!empty($h['batch_number'])): ?>
                    <div class="text-muted" style="font-size:.68rem;">
                      <?= e($h['batch_number']) ?>
                    </div>
                    <?php endif; ?>
                  </td>
                  <td class="small">
                    <?= isset($h['quantity_kg'])
                        ? number_format((float)$h['quantity_kg'], 1)
                        : '—' ?>
                  </td>
                  <td class="small text-muted">
                    <?= isset($h['harvest_date'])
                        ? date('d M Y', strtotime($h['harvest_date']))
                        : '—' ?>
                  </td>
                  <td>
                    <span class="badge bg-<?= $sc ?>-subtle text-<?= $sc ?> small">
                      <?= $sl ?>
                    </span>
                  </td>
                  <td class="small text-muted pe-3">
                    <?= $recorder ? e($recorder) : '—' ?>
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

  <!-- Right column: Quick Actions + Dispatch Status + Workflow -->
  <div class="col-lg-4 d-flex flex-column gap-3">

    <!-- Quick Actions -->
    <div class="card border-0 shadow-sm">
      <div class="card-header fw-semibold small">
        <i class="bi bi-lightning-charge-fill me-2 text-warning"></i>Quick Actions
      </div>
      <div class="card-body d-flex flex-column gap-2">
        <?php $dashFarmId = $farmId > 0 ? $farmId : null; ?>
        <a href="<?= $dashFarmId ? url('farms/' . $dashFarmId . '/harvests/create') : url('farms') ?>"
           class="btn btn-success w-100">
          <i class="bi bi-basket-fill me-2"></i>Record Harvest
        </a>
        <a href="<?= $dashFarmId ? url('farms/' . $dashFarmId . '/dispatches/create') : url('farms') ?>"
           class="btn btn-outline-primary w-100">
          <i class="bi bi-truck me-2"></i>Create Dispatch
        </a>
        <a href="<?= url('farms') ?>" class="btn btn-outline-secondary w-100">
          <i class="bi bi-flower1 me-2"></i>My Farms
        </a>
        <a href="<?= url('products') ?>" class="btn btn-outline-secondary w-100">
          <i class="bi bi-box-seam me-2"></i>View Products
        </a>
      </div>
    </div>

    <!-- Dispatch Status Summary -->
    <div class="card border-0 shadow-sm">
      <div class="card-header fw-semibold small">
        <i class="bi bi-truck me-2 text-primary"></i>Dispatch Status
      </div>
      <div class="card-body p-0">
        <?php
        $statusRows = [
          ['pending',            'Pending',            'warning',   'bi-hourglass-split'],
          ['in_transit',         'In Transit',         'info',      'bi-truck'],
          ['received',           'Received',           'success',   'bi-check-circle-fill'],
          ['partially_received', 'Partially Received', 'primary',   'bi-check2-circle'],
          ['cancelled',          'Cancelled',          'secondary', 'bi-x-circle'],
        ];
        $hasAny = !empty($dispatchSummary);
        foreach ($statusRows as $i => [$key, $label, $color, $icon]):
          $count = $dispatchSummary[$key] ?? 0;
        ?>
        <div class="d-flex align-items-center gap-3 px-3 py-2
                    <?= $i < count($statusRows)-1 ? 'border-bottom' : '' ?>">
          <div class="rounded bg-<?= $color ?> bg-opacity-10 d-flex align-items-center
                      justify-content-center flex-shrink-0"
               style="width:28px;height:28px;">
            <i class="bi <?= $icon ?> text-<?= $color ?>" style="font-size:.75rem;"></i>
          </div>
          <div class="flex-grow-1">
            <div class="small fw-semibold"><?= $label ?></div>
          </div>
          <span class="badge <?= $count > 0 ? 'bg-'.$color : 'bg-secondary bg-opacity-25 text-secondary' ?>">
            <?= $count ?>
          </span>
        </div>
        <?php endforeach; ?>
        <?php if (!$hasAny): ?>
        <div class="text-center py-3 text-muted small">No dispatch records yet.</div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     LEVEL 3b — PRODUCT PRODUCTION SUMMARY (last 30 days)
     ══════════════════════════════════════════════════════════ -->
<?php if (!empty($productionByProduct)): ?>
<div class="row g-3 mb-4">
  <div class="col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-header fw-semibold small">
        <i class="bi bi-bar-chart-steps me-2 text-success"></i>
        Product Production Summary — Last 30 Days
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th class="ps-3 small fw-semibold">Product</th>
                <th class="small fw-semibold">Batches</th>
                <th class="small fw-semibold">Total (kg)</th>
                <th class="small fw-semibold pe-3">Share</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $totalKg = array_sum(array_column($productionByProduct, 'total_kg'));
              foreach ($productionByProduct as $pp):
                $share = $totalKg > 0
                    ? round(((float)$pp['total_kg'] / $totalKg) * 100, 1)
                    : 0;
              ?>
              <tr>
                <td class="ps-3 small fw-semibold">
                  <?= e($pp['product_name'] ?? '—') ?>
                </td>
                <td class="small text-muted">
                  <?= $fmt($pp['harvest_count']) ?>
                </td>
                <td class="small fw-semibold text-success">
                  <?= number_format((float)$pp['total_kg'], 1) ?> kg
                </td>
                <td class="pe-3" style="min-width:100px;">
                  <div class="d-flex align-items-center gap-2">
                    <div class="progress flex-grow-1" style="height:6px;">
                      <div class="progress-bar bg-success"
                           style="width:<?= $share ?>%;"
                           role="progressbar"
                           aria-valuenow="<?= $share ?>"
                           aria-valuemin="0"
                           aria-valuemax="100"></div>
                    </div>
                    <span class="small text-muted" style="min-width:32px;">
                      <?= $share ?>%
                    </span>
                  </div>
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

<!-- ══════════════════════════════════════════════════════════
     LEVEL 4 — PRODUCTION TREND CHART
     ══════════════════════════════════════════════════════════ -->
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold small">
          <i class="bi bi-bar-chart-fill me-2 text-success"></i>
          Production Trend — Last 14 Days (kg)
        </span>
      </div>
      <div class="card-body py-3">
        <canvas id="productionChart" style="max-height:200px;"></canvas>
        <div id="productionEmpty"
             class="text-center py-4 text-muted d-none">
          <i class="bi bi-bar-chart d-block fs-3 mb-2 opacity-25"></i>
          <small>No production data for the last 14 days.</small>
        </div>
      </div>
    </div>
  </div>

  <!-- Farm Workflow Card -->
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header fw-semibold small">
        <i class="bi bi-diagram-2 me-2 text-success"></i>Farm → Warehouse Flow
      </div>
      <div class="card-body p-0">
        <?php
        $flowSteps = [
          ['FARM',      'bi-flower1',       'success',   'Agricultural production'],
          ['HARVEST',   'bi-basket',        'success',   'Record harvest batch'],
          ['DISPATCH',  'bi-truck',         'primary',   'Send to warehouse'],
          ['RECEIVING', 'bi-building-fill', 'secondary', 'Central warehouse'],
        ];
        foreach ($flowSteps as $i => [$stage, $icon, $color, $desc]):
        ?>
        <div class="d-flex align-items-center gap-3 px-3 py-2
                    <?= $i < count($flowSteps)-1 ? 'border-bottom' : '' ?>">
          <div class="rounded-circle bg-<?= $color ?> bg-opacity-10 d-flex
                      align-items-center justify-content-center flex-shrink-0"
               style="width:30px;height:30px;">
            <i class="bi <?= $icon ?> text-<?= $color ?>" style="font-size:.8rem;"></i>
          </div>
          <div>
            <div class="small fw-bold"><?= $stage ?></div>
            <div class="text-muted" style="font-size:.72rem;"><?= $desc ?></div>
          </div>
          <?php if ($i < count($flowSteps)-1): ?>
          <i class="bi bi-chevron-right ms-auto text-muted small"></i>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php $view->section('scripts'); ?>
<script>
(function () {
  'use strict';

  fetch('<?= url('api/dashboard/farm-charts') ?>', {
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': window.AAM && window.AAM.csrfToken ? window.AAM.csrfToken : ''
    },
    credentials: 'same-origin'
  })
  .then(function(r) { return r.json(); })
  .then(function(resp) {
    if (!resp.success) return;
    buildProductionChart(resp.data.production_trend);
  })
  .catch(function(err) { console.warn('Farm chart load failed:', err); });

  function buildProductionChart(data) {
    var empty = document.getElementById('productionEmpty');
    var ctx   = document.getElementById('productionChart');

    if (!data || !data.labels || !data.labels.length) {
      if (empty) empty.classList.remove('d-none');
      if (ctx)   ctx.style.display = 'none';
      return;
    }

    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: data.labels,
        datasets: [{
          label: 'Harvested (kg)',
          data: data.data,
          backgroundColor: 'rgba(25,135,84,.75)',
          borderRadius: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        animation: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { font: { size: 11 } },
            title: { display: true, text: 'kg', font: { size: 10 } }
          },
          x: { ticks: { font: { size: 10 } } }
        }
      }
    });
  }
})();
</script>
<?php $view->endSection(); ?>
