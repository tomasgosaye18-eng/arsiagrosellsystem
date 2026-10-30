<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * View     : modules/reports/views/farm/production.php
 * Purpose  : Production Report — aggregated by product.
 *
 * @var \AAM\Core\View  $view
 * @var array|null      $farm
 * @var int             $farmId
 * @var array           $rows                 one row per product
 * @var float           $grandTotalQty
 * @var int             $grandTotalHarvests
 * @var array           $trend                {labels: string[], data: float[]}
 * @var array           $products             for filter dropdown
 * @var array           $filters
 * @var string          $userRole
 */
declare(strict_types=1);

$farmName    = $farm['name'] ?? 'All Farms';
$f           = $filters;
$fmt         = fn(float $v): string => number_format($v, 1);
$fmtInt      = fn(int $v): string   => number_format($v);
$presetUrl   = fn(string $p): string => url('reports/farm/production') . '?preset=' . $p;
$activePreset = $f['preset'] ?? 'month';
?>

<!-- ══════════════════════════════════════════════════════════
     BREADCRUMB + HEADER
     ══════════════════════════════════════════════════════════ -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= url('reports/farm') ?>">Farm Reports</a></li>
        <li class="breadcrumb-item active">Production Report</li>
    </ol>
</nav>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">
            <i class="bi bi-bar-chart-steps text-success me-2"></i>Production Report
        </h4>
        <small class="text-muted">
            <?= e($farmName) ?> &middot;
            <?= $f['date_from'] ? date('d M Y', strtotime($f['date_from'])) : '' ?>
            <?= ($f['date_from'] && $f['date_to']) ? '–' : '' ?>
            <?= $f['date_to']   ? date('d M Y', strtotime($f['date_to']))   : '' ?>
        </small>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer me-1"></i>Print
        </button>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     FILTERS
     ══════════════════════════════════════════════════════════ -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2 px-3">
        <form method="get" action="<?= url('reports/farm/production') ?>"
              class="d-flex align-items-center gap-2 flex-wrap">

            <span class="text-muted small fw-semibold me-1">Period:</span>
            <?php foreach (['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'custom' => 'Custom'] as $p => $label): ?>
            <a href="<?= $presetUrl($p) ?>"
               class="btn btn-sm <?= $activePreset === $p ? 'btn-success' : 'btn-outline-secondary' ?>">
                <?= $label ?>
            </a>
            <?php endforeach; ?>

            <?php if ($activePreset === 'custom'): ?>
            <div class="d-flex align-items-center gap-1 ms-2">
                <input type="date" name="date_from" class="form-control form-control-sm"
                       value="<?= e($f['date_from']) ?>" style="width:140px;">
                <span class="text-muted small">to</span>
                <input type="date" name="date_to" class="form-control form-control-sm"
                       value="<?= e($f['date_to']) ?>" style="width:140px;">
                <input type="hidden" name="preset" value="custom">
            </div>
            <?php endif; ?>

            <!-- Product filter -->
            <select name="product_id" class="form-select form-select-sm ms-auto" style="width:180px;"
                    onchange="this.form.submit()">
                <option value="">All Products</option>
                <?php foreach ($products as $p): ?>
                <option value="<?= $p['id'] ?>"
                        <?= (int)($f['product_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                    <?= e($p['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <?php if ($activePreset === 'custom'): ?>
            <button class="btn btn-success btn-sm">Apply</button>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     SUMMARY TOTALS
     ══════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100" style="border-top:3px solid #198754!important;">
            <div class="card-body py-3 px-3">
                <div class="text-muted small mb-1">Total Produced</div>
                <div class="fw-bold fs-4 lh-1 text-success">
                    <?= number_format($grandTotalQty, 1) ?>
                </div>
                <div class="text-muted" style="font-size:.72rem;">units (all products)</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100" style="border-top:3px solid #20c997!important;">
            <div class="card-body py-3 px-3">
                <div class="text-muted small mb-1">Harvest Records</div>
                <div class="fw-bold fs-4 lh-1"><?= number_format($grandTotalHarvests) ?></div>
                <div class="text-muted" style="font-size:.72rem;">batches in period</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100" style="border-top:3px solid #6c757d!important;">
            <div class="card-body py-3 px-3">
                <div class="text-muted small mb-1">Products</div>
                <div class="fw-bold fs-4 lh-1"><?= count($rows) ?></div>
                <div class="text-muted" style="font-size:.72rem;">product types recorded</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100" style="border-top:3px solid #0d6efd!important;">
            <div class="card-body py-3 px-3">
                <div class="text-muted small mb-1">Avg per Batch</div>
                <div class="fw-bold fs-4 lh-1">
                    <?= $grandTotalHarvests > 0
                        ? number_format($grandTotalQty / $grandTotalHarvests, 1)
                        : '—' ?>
                </div>
                <div class="text-muted" style="font-size:.72rem;">units per harvest</div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     TREND CHART  (small, above table)
     ══════════════════════════════════════════════════════════ -->
<?php if (!empty($trend['labels'])): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center py-2">
        <span class="fw-semibold small">
            <i class="bi bi-bar-chart-fill me-2 text-success"></i>
            Production Trend
        </span>
        <small class="text-muted">Daily totals for selected period</small>
    </div>
    <div class="card-body py-3">
        <canvas id="productionTrendChart" style="max-height:160px;"></canvas>
    </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════
     PRODUCTION TABLE
     ══════════════════════════════════════════════════════════ -->
<div class="card border-0 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center py-2">
        <span class="fw-semibold small">
            <i class="bi bi-table me-2"></i>Production by Product
        </span>
        <small class="text-muted"><?= count($rows) ?> product(s)</small>
    </div>

    <?php if (empty($rows)): ?>
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-basket d-block fs-2 mb-2 opacity-25"></i>
        <small>No production records found for the selected period and filters.</small>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3 small fw-semibold">Product</th>
                    <th class="small fw-semibold text-end">Total Produced</th>
                    <th class="small fw-semibold">Unit</th>
                    <th class="small fw-semibold text-end">Harvest Records</th>
                    <th class="small fw-semibold">First Harvest</th>
                    <th class="small fw-semibold pe-3">Last Harvest</th>
                    <th class="small fw-semibold pe-3">Share</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row):
                    $share = $grandTotalQty > 0
                        ? round(((float)$row['total_produced'] / $grandTotalQty) * 100, 1)
                        : 0;
                ?>
                <tr>
                    <td class="ps-3 small fw-semibold">
                        <?= e($row['product_name'] ?? '—') ?>
                        <?php if (!empty($row['product_sku'])): ?>
                        <div class="text-muted" style="font-size:.68rem;"><?= e($row['product_sku']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="small text-end fw-bold text-success">
                        <?= number_format((float)$row['total_produced'], 1) ?>
                    </td>
                    <td class="small text-muted">
                        <?= e($row['unit_abbr'] ?? $row['unit_name'] ?? '—') ?>
                    </td>
                    <td class="small text-end">
                        <?= number_format((int)$row['harvest_count']) ?>
                    </td>
                    <td class="small text-muted">
                        <?= !empty($row['first_harvest']) ? date('d M Y', strtotime($row['first_harvest'])) : '—' ?>
                    </td>
                    <td class="small text-muted pe-3">
                        <?= !empty($row['last_harvest']) ? date('d M Y', strtotime($row['last_harvest'])) : '—' ?>
                    </td>
                    <td class="pe-3" style="min-width:120px;">
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:6px;">
                                <div class="progress-bar bg-success"
                                     style="width:<?= $share ?>%;"
                                     role="progressbar"
                                     aria-valuenow="<?= $share ?>"
                                     aria-valuemin="0"
                                     aria-valuemax="100"></div>
                            </div>
                            <span class="small text-muted" style="min-width:38px;"><?= $share ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
                <tr class="fw-bold">
                    <td class="ps-3 small">Total</td>
                    <td class="small text-end text-success">
                        <?= number_format($grandTotalQty, 1) ?>
                    </td>
                    <td></td>
                    <td class="small text-end"><?= number_format($grandTotalHarvests) ?></td>
                    <td colspan="3" class="pe-3"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php $view->section('scripts'); ?>
<script>
(function () {
    'use strict';
    var trendData = <?= json_encode($trend, JSON_THROW_ON_ERROR) ?>;
    var ctx = document.getElementById('productionTrendChart');
    if (!ctx || !trendData.labels || !trendData.labels.length) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: trendData.labels,
            datasets: [{
                label: 'Produced',
                data: trendData.data,
                backgroundColor: 'rgba(25,135,84,.75)',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            animation: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { font: { size: 11 } } },
                x: { ticks: { font: { size: 10 }, maxRotation: 45 } }
            }
        }
    });
})();
</script>
<?php $view->endSection(); ?>
