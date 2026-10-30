<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 9
 * =========================================================
 * File     : modules/dashboard/views/warehouse_manager.php
  * Purpose  : Warehouse Manager dashboard.
 *            Focus: receiving, stock, transfers, discrepancies.
 *            Answers: What arrived? What is in stock?
 *                     What needs attention? What is going out?
 * =========================================================
 *
 * @var \AAM\Core\View    $view
 * @var string            $userName
 * @var string            $userRole
 * @var array             $warehouseKpis
 */
declare(strict_types=1);

$warehouseKpis ??= [];
$isWM = ($userRole ?? '') === ROLE_WAREHOUSE_MANAGER;

$fmt = fn(mixed $v): string => is_numeric($v) ? number_format((float)$v) : ($v ?? '—');
?>

<!-- ── Welcome Banner ─────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-12">
    <div class="card border-0 shadow-sm" style="background:linear-gradient(135deg,#0d6efd,#0dcaf0);color:#fff;">
      <div class="card-body d-flex align-items-center gap-3 py-3">
        <div class="rounded-circle bg-white bg-opacity-20 d-flex align-items-center justify-content-center flex-shrink-0"
             style="width:48px;height:48px;">
          <i class="bi bi-building fs-5"></i>
        </div>
        <div class="flex-grow-1">
          <?php if (!empty($warehouseInfo)): ?>
            <h5 class="mb-0 fw-bold"><?= e($warehouseInfo['warehouse_name'] ?? $warehouseInfo['name'] ?? 'Warehouse') ?></h5>
            <small class="opacity-75">
              <?php if (!empty($warehouseInfo['warehouse_code'])): ?>
              <span class="font-monospace me-2"><?= e($warehouseInfo['warehouse_code']) ?></span> &middot;
              <?php endif; ?>
              Warehouse Manager: <?= e($userName) ?> &mdash; <?= date('l, d F Y') ?>
            </small>
          <?php elseif ($warehouseId > 0): ?>
            <h5 class="mb-0 fw-bold">Warehouse #<?= $warehouseId ?></h5>
            <small class="opacity-75">Warehouse Manager: <?= e($userName) ?></small>
          <?php else: ?>
            <h5 class="mb-0 fw-bold">No Warehouse Assigned</h5>
            <small class="opacity-75">Contact your System Administrator to assign you to a warehouse.</small>
          <?php endif; ?>
        </div>
        <?php if ($warehouseId > 0): ?>
        <div class="d-none d-md-flex gap-2 flex-wrap">
          <a href="<?= url('warehouse/receiving') ?>" class="btn btn-light btn-sm fw-semibold">
            <i class="bi bi-inbox-fill me-1"></i>Receiving Queue
          </a>
          <a href="<?= url('transfers') ?>" class="btn btn-outline-light btn-sm">
            <i class="bi bi-arrow-left-right me-1"></i>Transfers
          </a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($warehouseId <= 0): ?>
<!-- No assignment — show helpful message instead of empty KPIs -->
<div class="row g-3">
  <div class="col-12">
    <div class="card border-0 shadow-sm border-warning">
      <div class="card-body text-center py-5">
        <i class="bi bi-building-x display-4 text-muted d-block mb-3"></i>
        <h5 class="fw-bold">You are not assigned to a Warehouse</h5>
        <p class="text-muted mb-0">
          Please ask the System Administrator to assign you to a warehouse using the
          <strong>Organization → Assignments</strong> section.
        </p>
      </div>
    </div>
  </div>
</div>
<?php else: ?>

<!-- ══════════════════════════════════════════════════════════
     LEVEL 1 — WAREHOUSE KPIs
     ══════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">

  <!-- Total Stock -->
  <div class="col-6 col-xl-2">
    <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #0d6efd!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-boxes text-primary"></i>
          <span class="text-muted small">Total Stock</span>
        </div>
        <div class="fw-bold fs-3"><?= $fmt($warehouseKpis['total_stock'] ?? 0) ?></div>
        <div class="text-muted" style="font-size:.75rem;">Units in warehouse</div>
      </div>
    </div>
  </div>

  <!-- Received Today -->
  <div class="col-6 col-xl-2">
    <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #198754!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-inbox-fill text-success"></i>
          <span class="text-muted small">Received Today</span>
        </div>
        <div class="fw-bold fs-3"><?= $fmt($warehouseKpis['received_today'] ?? 0) ?></div>
        <div class="text-muted" style="font-size:.75rem;">Batches received</div>
      </div>
    </div>
  </div>

  <!-- Pending Receiving -->
  <div class="col-6 col-xl-2">
    <div class="card border-0 shadow-sm h-100" style="border-left:4px solid <?= ($warehouseKpis['pending_receipts'] ?? $warehouseKpis['pending_receiving'] ?? 0) > 0 ? '#ffc107' : '#dee2e6' ?>!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-hourglass-split text-warning"></i>
          <span class="text-muted small">Pending Receiving</span>
        </div>
        <div class="fw-bold fs-3 <?= ($warehouseKpis['pending_receipts'] ?? $warehouseKpis['pending_receiving'] ?? 0) > 0 ? 'text-warning' : '' ?>">
          <?= $fmt($warehouseKpis['pending_receipts'] ?? $warehouseKpis['pending_receiving'] ?? 0) ?>
        </div>
        <div class="text-muted" style="font-size:.75rem;">Dispatches en route</div>
      </div>
    </div>
  </div>

  <!-- Pending Transfers -->
  <div class="col-6 col-xl-2">
    <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #0dcaf0!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-arrow-left-right text-info"></i>
          <span class="text-muted small">Pending Transfers</span>
        </div>
        <div class="fw-bold fs-3"><?= $fmt($warehouseKpis['pending_transfers'] ?? 0) ?></div>
        <div class="text-muted" style="font-size:.75rem;">To retail shops</div>
      </div>
    </div>
  </div>

  <!-- Low Stock -->
  <div class="col-6 col-xl-2">
    <div class="card border-0 shadow-sm h-100"
         style="border-left:4px solid <?= ($warehouseKpis['low_stock'] ?? 0) > 0 ? '#fd7e14' : '#dee2e6' ?>!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-exclamation-circle" style="color:#fd7e14;"></i>
          <span class="text-muted small">Low Stock</span>
        </div>
        <div class="fw-bold fs-3<?= ($warehouseKpis['low_stock'] ?? 0) > 0 ? '' : ' text-muted' ?>"
             <?= ($warehouseKpis['low_stock'] ?? 0) > 0 ? 'style="color:#fd7e14;"' : '' ?>>
          <?= $fmt($warehouseKpis['low_stock'] ?? 0) ?>
        </div>
        <div class="text-muted" style="font-size:.75rem;">Below minimum level</div>
      </div>
    </div>
  </div>

  <!-- Discrepancies -->
  <div class="col-6 col-xl-2">
    <div class="card border-0 shadow-sm h-100" style="border-left:4px solid <?= ($warehouseKpis['open_discrepancies'] ?? 0) > 0 ? '#dc3545' : '#dee2e6' ?>!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-clipboard-x text-danger"></i>
          <span class="text-muted small">Discrepancies</span>
        </div>
        <div class="fw-bold fs-3 <?= ($warehouseKpis['open_discrepancies'] ?? 0) > 0 ? 'text-danger' : '' ?>">
          <?= $fmt($warehouseKpis['open_discrepancies'] ?? 0) ?>
        </div>
        <div class="text-muted" style="font-size:.75rem;">Open reports</div>
      </div>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════════
     LEVEL 2 — ALERTS
     ══════════════════════════════════════════════════════════ -->
<?php
$alerts = [];
if (($warehouseKpis['pending_receipts'] ?? $warehouseKpis['pending_receiving'] ?? 0) > 0)
    $alerts[] = ['warning', 'bi-inbox-fill', ($warehouseKpis['pending_receipts'] ?? $warehouseKpis['pending_receiving']).' shipment(s) dispatched from farm and awaiting receiving confirmation.', url('warehouse/receiving'), 'Receiving Queue'];
if (($warehouseKpis['open_discrepancies'] ?? 0) > 0)
    $alerts[] = ['danger',  'bi-clipboard-x-fill', ($warehouseKpis['open_discrepancies']).' discrepancy report(s) require attention.', url('warehouse/discrepancies'), 'Review'];
if (($warehouseKpis['low_stock'] ?? 0) > 0)
    $alerts[] = ['warning', 'bi-exclamation-triangle-fill', ($warehouseKpis['low_stock']).' product(s) are below minimum stock level.', url('inventory'), 'View Inventory'];
?>
<?php if (!empty($alerts)): ?>
<div class="row g-2 mb-4">
  <?php foreach ($alerts as [$color, $icon, $msg, $href, $btnLabel]): ?>
  <div class="col-12">
    <div class="alert alert-<?= $color ?> d-flex align-items-center justify-content-between py-2 mb-0" role="alert">
      <div class="d-flex align-items-center gap-2">
        <i class="bi <?= $icon ?> flex-shrink-0"></i>
        <span class="small"><?= e($msg) ?></span>
      </div>
      <a href="<?= $href ?>" class="btn btn-<?= $color ?> btn-sm py-0 px-2 flex-shrink-0 ms-2"><?= $btnLabel ?></a>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════
     LEVEL 3 — OPERATIONAL PANELS
     ══════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">

  <!-- Operational Status -->
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header fw-semibold small">
        <i class="bi bi-diagram-2-fill me-2 text-primary"></i>Warehouse Operations
      </div>
      <div class="card-body p-0">
        <?php
        $ops = [
          [
            'Inventory',
            'bi-boxes', 'primary',
            url('warehouse'),
            'View all stock',
            $fmt($warehouseKpis['total_stock'] ?? 0) . ' units',
            null,
          ],
          [
            'Receiving Queue',
            'bi-inbox-fill', 'warning',
            url('warehouse/receiving'),
            'Confirm incoming dispatches',
            $fmt($warehouseKpis['pending_receipts'] ?? $warehouseKpis['pending_receiving'] ?? 0) . ' awaiting',
            $warehouseKpis['pending_receipts'] ?? $warehouseKpis['pending_receiving'] ?? 0,
          ],
          [
            'Discrepancies',
            'bi-clipboard-x', 'danger',
            url('warehouse/discrepancies'),
            'Review stock discrepancies',
            $fmt($warehouseKpis['open_discrepancies'] ?? 0) . ' open',
            $warehouseKpis['open_discrepancies'] ?? 0,
          ],
          [
            'Stock Transfers',
            'bi-arrow-left-right', 'info',
            url('transfers'),
            'Transfer stock to shops',
            $fmt($warehouseKpis['pending_transfers'] ?? 0) . ' pending',
            $warehouseKpis['pending_transfers'] ?? 0,
          ],
        ];
        foreach ($ops as [$title, $icon, $color, $href, $desc, $badge, $count]):
          $hasAlert = $count !== null && $count > 0;
        ?>
        <a href="<?= $href ?>" class="d-flex align-items-center gap-3 px-3 py-3 border-bottom text-decoration-none text-dark hover-bg-light">
          <div class="rounded bg-<?= $color ?> bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
               style="width:40px;height:40px;">
            <i class="bi <?= $icon ?> text-<?= $color ?> fs-5"></i>
          </div>
          <div class="flex-grow-1">
            <div class="fw-semibold"><?= $title ?></div>
            <div class="text-muted small"><?= $desc ?></div>
          </div>
          <span class="badge bg-<?= $hasAlert ? $color : 'secondary bg-opacity-25 text-secondary' ?> flex-shrink-0">
            <?= $badge ?>
          </span>
          <i class="bi bi-chevron-right text-muted small flex-shrink-0"></i>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Quick Actions -->
  <div class="col-lg-5 d-flex flex-column gap-3">

    <div class="card border-0 shadow-sm">
      <div class="card-header fw-semibold small">
        <i class="bi bi-lightning-charge-fill me-2 text-warning"></i>Quick Actions
      </div>
      <div class="card-body d-flex flex-column gap-2">
        <a href="<?= url('warehouse/receiving') ?>" class="btn btn-primary w-100">
          <i class="bi bi-inbox-fill me-2"></i>Receive Incoming Stock
        </a>
        <a href="<?= url('transfers/create') ?>" class="btn btn-outline-info w-100">
          <i class="bi bi-arrow-left-right me-2"></i>Create Transfer
        </a>
        <a href="<?= url('inventory/adjustments/create') ?>" class="btn btn-outline-warning w-100">
          <i class="bi bi-sliders me-2"></i>New Stock Adjustment
        </a>
        <a href="<?= url('inventory') ?>" class="btn btn-outline-secondary w-100">
          <i class="bi bi-clipboard-data me-2"></i>View Inventory
        </a>
        <?php if ($isWM): ?>
        <a href="<?= url('warehouse/discrepancies') ?>" class="btn btn-outline-danger w-100">
          <i class="bi bi-clipboard-x me-2"></i>View Discrepancies
        </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Warehouse Workflow -->
    <div class="card border-0 shadow-sm">
      <div class="card-header fw-semibold small">
        <i class="bi bi-diagram-2 me-2 text-primary"></i>Warehouse Workflow
      </div>
      <div class="card-body p-0">
        <?php
        $steps = [
          ['FARM DISPATCH',    'bi-truck',           'success',  'Incoming from farms'],
          ['RECEIVING',        'bi-inbox-fill',      'warning',  'Confirm & verify'],
          ['WAREHOUSE STOCK',  'bi-boxes',           'primary',  'Store & manage'],
          ['TRANSFER OUT',     'bi-arrow-left-right','info',     'Send to shops'],
        ];
        foreach ($steps as $i => [$stage, $icon, $color, $desc]):
        ?>
        <div class="d-flex align-items-center gap-3 px-3 py-2 <?= $i < count($steps)-1 ? 'border-bottom' : '' ?>">
          <div class="rounded-circle bg-<?= $color ?> bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
               style="width:28px;height:28px;">
            <i class="bi <?= $icon ?> text-<?= $color ?>" style="font-size:.75rem;"></i>
          </div>
          <div>
            <div class="small fw-bold"><?= $stage ?></div>
            <div class="text-muted" style="font-size:.72rem;"><?= $desc ?></div>
          </div>
          <?php if ($i < count($steps)-1): ?>
          <i class="bi bi-chevron-right ms-auto text-muted small"></i>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>

</div>

<!-- ══════════════════════════════════════════════════════════
     LEVEL 4 — STOCK MOVEMENT CHART
     ══════════════════════════════════════════════════════════ -->
<div class="row g-3">
  <div class="col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-header">
        <span class="fw-semibold small"><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Stock Movement — Last 14 Days</span>
      </div>
      <div class="card-body py-3">
        <canvas id="stockMovementChart" style="max-height:180px;"></canvas>
        <div id="stockEmpty" class="text-center py-4 text-muted d-none">
          <i class="bi bi-bar-chart d-block fs-3 mb-2 opacity-25"></i>
          <small>No stock movement data recorded yet.</small>
        </div>
      </div>
    </div>
  </div>
</div>

<?php endif; /* end warehouseId > 0 */ ?>

<?php $view->section('scripts'); ?>
<script>
(function () {
  'use strict';

  fetch('<?= url('api/dashboard/charts') ?>', {
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': window.AAM && window.AAM.csrfToken ? window.AAM.csrfToken : ''
    },
    credentials: 'same-origin'
  })
  .then(function(r) { return r.json(); })
  .then(function(resp) {
    if (!resp.success) return;
    buildStockChart(resp.data.stock_movement);
  })
  .catch(function(err) { console.warn('Chart load failed:', err); });

  function buildStockChart(data) {
    if (!data || !data.labels || !data.labels.length) {
      var empty = document.getElementById('stockEmpty');
      if (empty) empty.classList.remove('d-none');
      return;
    }
    var ctx = document.getElementById('stockMovementChart');
    if (!ctx) return;
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: data.labels,
        datasets: [
          {
            label: 'Stock In',
            data: data.in,
            backgroundColor: 'rgba(25,135,84,.7)',
            borderRadius: 4
          },
          {
            label: 'Stock Out',
            data: data.out,
            backgroundColor: 'rgba(220,53,69,.6)',
            borderRadius: 4
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        animation: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top', labels: { font: { size: 11 } } } },
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0, font: { size: 11 } } },
          x: { ticks: { font: { size: 10 } } }
        }
      }
    });
  }
})();
</script>
<?php $view->endSection(); ?>
