<?php
/**
 * Warehouse Reports Hub — AAM Phase 10
 * @var array $warehouse @var array $summary @var array $disSummary
 */
declare(strict_types=1);
$summary    ??= [];
$disSummary ??= [];
$wh = $warehouse['name'] ?? 'Warehouse';
?>

<div class="d-flex align-items-center gap-3 mb-4">
  <div>
    <h5 class="mb-0 fw-bold"><i class="bi bi-file-earmark-bar-graph me-2 text-primary"></i>Warehouse Reports</h5>
    <small class="text-muted"><?= e($wh) ?> &mdash; Operational Reports</small>
  </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
  <?php
  $cards = [
    ['Total Products',  number_format((int)($summary['total_products'] ?? 0)), 'bi-box-seam',      'primary'],
    ['In Stock',        number_format((int)($summary['total_products'] ?? 0) - (int)($summary['out_of_stock'] ?? 0)), 'bi-check-circle', 'success'],
    ['Low Stock',       number_format((int)($summary['low_stock'] ?? 0)),       'bi-exclamation-triangle', 'warning'],
    ['Expired',         number_format((int)($summary['expired'] ?? 0)),         'bi-x-circle',     'danger'],
    ['Open Discrepancies', number_format((int)($disSummary['open_count'] ?? 0)), 'bi-clipboard-x', 'danger'],
  ];
  foreach ($cards as [$label, $val, $icon, $color]):
  ?>
  <div class="col-6 col-md-4 col-xl">
    <div class="card border-0 shadow-sm text-center h-100" style="border-top:3px solid var(--bs-<?= $color ?>)!important;">
      <div class="card-body py-3">
        <i class="bi <?= $icon ?> text-<?= $color ?> fs-4 d-block mb-1"></i>
        <div class="fw-bold fs-4"><?= $val ?></div>
        <div class="text-muted small"><?= $label ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Report Cards -->
<div class="row g-3">
  <?php
  $reports = [
    ['Stock Report',       'reports/warehouse/stock',        'bi-boxes',                'primary', 'Current inventory levels, batch details, stock status and expiry.'],
    ['Receiving Report',   'reports/warehouse/receiving',    'bi-inbox-fill',           'success', 'Products received from farms — quantities, variances, and status.'],
    ['Transfer Report',    'reports/warehouse/transfers',    'bi-arrow-right-circle',   'info',    'Products transferred to retail shops — history and status.'],
    ['Discrepancy Report', 'reports/warehouse/discrepancies','bi-clipboard-x',          'danger',  'Quantity mismatches found during receiving or transfer.'],
    ['Expiry Report',      'reports/warehouse/expiry',       'bi-calendar-x',           'warning', 'Products approaching or past their expiry date.'],
  ];
  foreach ($reports as [$title, $path, $icon, $color, $desc]):
  ?>
  <div class="col-md-6 col-xl-4">
    <a href="<?= url($path) ?>" class="card border-0 shadow-sm h-100 text-decoration-none hover-shadow">
      <div class="card-body d-flex align-items-start gap-3 py-3">
        <div class="rounded bg-<?= $color ?> bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
             style="width:44px;height:44px;">
          <i class="bi <?= $icon ?> text-<?= $color ?> fs-5"></i>
        </div>
        <div>
          <div class="fw-semibold text-dark"><?= $title ?></div>
          <div class="text-muted small"><?= $desc ?></div>
        </div>
        <i class="bi bi-chevron-right text-muted ms-auto align-self-center"></i>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>
