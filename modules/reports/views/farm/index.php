<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * View     : modules/reports/views/farm/index.php
 * Purpose  : Farm Manager Reports hub.
 *            Shows 4 summary cards + navigation to the
 *            3 report types (Production, Harvest, Dispatch).
 *
 * @var \AAM\Core\View  $view
 * @var array|null      $farm
 * @var int             $farmId
 * @var array           $summary     total_production, total_harvests, total_dispatched, pending_dispatches
 * @var array           $products
 * @var string          $dateFrom
 * @var string          $dateTo
 * @var string          $datePreset
 * @var string          $userRole
 */
declare(strict_types=1);

$farmName = $farm['name'] ?? 'All Farms';
$fmt      = fn(mixed $v): string => is_numeric($v) ? number_format((float)$v, 1) : ($v ?? '—');
$fmtInt   = fn(mixed $v): string => number_format((int)$v);
$currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
?>

<!-- ══════════════════════════════════════════════════════════
     PAGE HEADER
     ══════════════════════════════════════════════════════════ -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">
            <i class="bi bi-file-earmark-bar-graph text-success me-2"></i>Farm Reports
        </h4>
        <small class="text-muted">
            <?= e($farmName) ?> &middot;
            <?= $dateFrom ? date('d M Y', strtotime($dateFrom)) : '' ?>
            <?= ($dateFrom && $dateTo) ? '–' : '' ?>
            <?= $dateTo   ? date('d M Y', strtotime($dateTo))   : '' ?>
        </small>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     DATE RANGE QUICK FILTER
     ══════════════════════════════════════════════════════════ -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2 px-3">
        <form method="get" action="<?= url('reports/farm') ?>"
              class="d-flex align-items-center gap-2 flex-wrap">

            <span class="text-muted small fw-semibold me-1">Period:</span>

            <?php foreach (['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'custom' => 'Custom'] as $p => $label): ?>
            <a href="<?= url('reports/farm') ?>?preset=<?= $p ?>"
               class="btn btn-sm <?= $datePreset === $p ? 'btn-success' : 'btn-outline-secondary' ?>">
                <?= $label ?>
            </a>
            <?php endforeach; ?>

            <?php if ($datePreset === 'custom'): ?>
            <div class="d-flex align-items-center gap-1 ms-2">
                <input type="date" name="date_from" class="form-control form-control-sm"
                       value="<?= e($dateFrom) ?>" style="width:140px;" required>
                <span class="text-muted small">to</span>
                <input type="date" name="date_to" class="form-control form-control-sm"
                       value="<?= e($dateTo) ?>"   style="width:140px;" required>
                <input type="hidden" name="preset" value="custom">
                <button class="btn btn-success btn-sm">Apply</button>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     SUMMARY CARDS
     ══════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">

    <!-- Total Production -->
    <div class="col-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100" style="border-top:3px solid #198754!important;">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small">Total Production</span>
                    <div class="rounded bg-success bg-opacity-10 p-1">
                        <i class="bi bi-basket-fill text-success"></i>
                    </div>
                </div>
                <div class="fw-bold fs-3 lh-1">
                    <?= number_format((float)($summary['total_production'] ?? 0), 1) ?>
                </div>
                <div class="text-muted" style="font-size:.72rem;">units produced in period</div>
            </div>
        </div>
    </div>

    <!-- Total Harvests -->
    <div class="col-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100" style="border-top:3px solid #20c997!important;">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small">Total Harvests</span>
                    <div class="rounded bg-success bg-opacity-10 p-1" style="--bs-bg-opacity:.08;">
                        <i class="bi bi-calendar-check text-success"></i>
                    </div>
                </div>
                <div class="fw-bold fs-3 lh-1">
                    <?= $fmtInt($summary['total_harvests'] ?? 0) ?>
                </div>
                <div class="text-muted" style="font-size:.72rem;">harvest batches recorded</div>
            </div>
        </div>
    </div>

    <!-- Total Dispatched -->
    <div class="col-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100" style="border-top:3px solid #0d6efd!important;">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small">Total Dispatched</span>
                    <div class="rounded bg-primary bg-opacity-10 p-1">
                        <i class="bi bi-send-check-fill text-primary"></i>
                    </div>
                </div>
                <div class="fw-bold fs-3 lh-1">
                    <?= $fmtInt($summary['total_dispatched'] ?? 0) ?>
                </div>
                <div class="text-muted" style="font-size:.72rem;">dispatches sent to warehouse</div>
            </div>
        </div>
    </div>

    <!-- Pending Dispatches -->
    <?php $pending = (int)($summary['pending_dispatches'] ?? 0); ?>
    <div class="col-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100"
             style="border-top:3px solid <?= $pending > 0 ? '#ffc107' : '#dee2e6' ?>!important;">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small">Pending Dispatch</span>
                    <div class="rounded p-1" style="background:rgba(255,193,7,.12);">
                        <i class="bi bi-hourglass-split text-warning"></i>
                    </div>
                </div>
                <div class="fw-bold fs-3 lh-1 <?= $pending > 0 ? 'text-warning' : '' ?>">
                    <?= $fmtInt($pending) ?>
                </div>
                <div class="text-muted" style="font-size:.72rem;">batches awaiting dispatch</div>
            </div>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════════════════════
     REPORT NAVIGATION CARDS
     ══════════════════════════════════════════════════════════ -->
<div class="row g-3">

    <!-- Production Report -->
    <div class="col-md-4">
        <a href="<?= url('reports/farm/production') ?>?preset=<?= e($datePreset) ?>&date_from=<?= e($dateFrom) ?>&date_to=<?= e($dateTo) ?>"
           class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 card-hover">
                <div class="card-body d-flex align-items-start gap-3 py-4">
                    <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center
                                justify-content-center flex-shrink-0" style="width:52px;height:52px;">
                        <i class="bi bi-bar-chart-steps text-success fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-bold mb-1">Production Report</div>
                        <div class="text-muted small">
                            Total production aggregated by product.
                            Shows quantities, harvest counts, and a trend chart.
                        </div>
                        <div class="mt-2">
                            <span class="badge bg-success-subtle text-success small">
                                <?= number_format((float)($summary['total_production'] ?? 0), 1) ?> units produced
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-top-0 py-2 px-3">
                    <span class="text-success small fw-semibold">
                        View Report <i class="bi bi-arrow-right ms-1"></i>
                    </span>
                </div>
            </div>
        </a>
    </div>

    <!-- Harvest Report -->
    <div class="col-md-4">
        <a href="<?= url('reports/farm/harvest') ?>?preset=<?= e($datePreset) ?>&date_from=<?= e($dateFrom) ?>&date_to=<?= e($dateTo) ?>"
           class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 card-hover">
                <div class="card-body d-flex align-items-start gap-3 py-4">
                    <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center
                                justify-content-center flex-shrink-0" style="width:52px;height:52px;">
                        <i class="bi bi-basket2-fill text-success fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-bold mb-1">Harvest Report</div>
                        <div class="text-muted small">
                            Detailed list of all harvest batches. Filter by date,
                            product, and status. View and search records.
                        </div>
                        <div class="mt-2">
                            <span class="badge bg-success-subtle text-success small">
                                <?= $fmtInt($summary['total_harvests'] ?? 0) ?> harvest records
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-top-0 py-2 px-3">
                    <span class="text-success small fw-semibold">
                        View Report <i class="bi bi-arrow-right ms-1"></i>
                    </span>
                </div>
            </div>
        </a>
    </div>

    <!-- Dispatch Report -->
    <div class="col-md-4">
        <a href="<?= url('reports/farm/dispatch') ?>?preset=<?= e($datePreset) ?>&date_from=<?= e($dateFrom) ?>&date_to=<?= e($dateTo) ?>"
           class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 card-hover">
                <div class="card-body d-flex align-items-start gap-3 py-4">
                    <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center
                                justify-content-center flex-shrink-0" style="width:52px;height:52px;">
                        <i class="bi bi-truck text-primary fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-bold mb-1">Dispatch Report</div>
                        <div class="text-muted small">
                            Farm-to-warehouse dispatch records. Track status,
                            quantities sent, and receiving confirmation.
                        </div>
                        <div class="mt-2">
                            <span class="badge bg-primary-subtle text-primary small">
                                <?= $fmtInt($summary['total_dispatched'] ?? 0) ?> dispatches
                            </span>
                            <?php if ($pending > 0): ?>
                            <span class="badge bg-warning-subtle text-warning small ms-1">
                                <?= $pending ?> pending
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-top-0 py-2 px-3">
                    <span class="text-primary small fw-semibold">
                        View Report <i class="bi bi-arrow-right ms-1"></i>
                    </span>
                </div>
            </div>
        </a>
    </div>

</div>

<style>
.card-hover { transition: box-shadow .18s, transform .18s; cursor: pointer; }
.card-hover:hover { box-shadow: 0 .5rem 1.5rem rgba(0,0,0,.12) !important; transform: translateY(-2px); }
</style>
