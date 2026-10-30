<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 9
 * =========================================================
 * View     : modules/reports/views/stub.php
 * Purpose  : Reports module placeholder.
 *            Navigation tabs are fully functional.
 *            Report data populates once operational modules
 *            (sales, harvests, transfers) are active.
 * =========================================================
 *
 * @var string $pageTitle
 * @var string $module
 */
declare(strict_types=1);

$title      = e($pageTitle ?? $module ?? 'Reports');
$currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

$tabs = [
    'reports'                    => ['bi-list-ul',             'All Reports',        'primary'],
    'reports/farm-production'    => ['bi-flower1',             'Farm Production',    'success'],
    'reports/warehouse-inventory'=> ['bi-building',            'Warehouse',          'info'],
    'reports/transfers'          => ['bi-arrow-left-right',    'Transfers',          'secondary'],
    'reports/sales'              => ['bi-receipt',             'Sales & Revenue',    'warning'],
];

$reportTypes = [
    ['bi-flower1',          'success',   'Farm Production Report',      'Harvest quantities, products, dispatch dates by farm and period'],
    ['bi-inbox-fill',       'primary',   'Warehouse Receiving Report',  'All received dispatches with quantities and verification status'],
    ['bi-arrow-left-right', 'info',      'Transfer Report',             'Stock transfers from warehouse to shops with approval history'],
    ['bi-shop',             'warning',   'Shop Sales Report',           'Daily, weekly and monthly sales per shop and seller'],
    ['bi-boxes',            'secondary', 'Inventory Report',            'Current stock levels across warehouse and all shops'],
    ['bi-clipboard-x',      'danger',    'Discrepancy Report',          'Stock count discrepancies with resolution status'],
    ['bi-receipt',          'dark',      'Product Sales Report',        'Revenue and volume per product across all shops'],
];
?>

<!-- Report Navigation Tabs -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-0">
        <nav class="nav nav-tabs nav-fill px-3 pt-2" role="tablist">
            <?php foreach ($tabs as $path => [$icon, $label, $color]):
                $isActive = str_ends_with(rtrim($currentUri, '/'), '/' . rtrim($path, '/'));
            ?>
            <a href="<?= url($path) ?>"
               class="nav-link <?= $isActive ? 'active' : '' ?> small"
               role="tab">
                <i class="bi <?= $icon ?> me-1"></i><?= $label ?>
            </a>
            <?php endforeach; ?>
        </nav>
    </div>
</div>

<!-- Report Type Overview -->
<div class="row g-3 mb-4">
    <?php foreach ($reportTypes as [$icon, $color, $label, $desc]): ?>
    <div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-start gap-3 py-3">
                <div class="rounded bg-<?= $color ?> bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:40px;height:40px;">
                    <i class="bi <?= $icon ?> text-<?= $color ?> fs-5"></i>
                </div>
                <div>
                    <div class="fw-semibold small"><?= $label ?></div>
                    <div class="text-muted" style="font-size:.78rem;"><?= $desc ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Status -->
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <div class="mb-4">
            <span class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 rounded-circle"
                  style="width:80px;height:80px;">
                <i class="bi bi-file-earmark-bar-graph text-primary" style="font-size:2.5rem;"></i>
            </span>
        </div>
        <h5 class="fw-bold mb-2"><?= $title ?></h5>
        <p class="text-muted mb-1 small">
            Reports are ready to generate — they will populate with data as<br>
            farm harvests, sales, transfers, and inventory movements are recorded.
        </p>
        <p class="text-muted small">
            <i class="bi bi-info-circle me-1"></i>
            For live visual trends, see
            <a href="<?= url('analytics') ?>">Analytics</a>.
        </p>
        <div class="mt-4 d-flex gap-2 justify-content-center flex-wrap">
            <a href="<?= url('dashboard') ?>" class="btn btn-primary">
                <i class="bi bi-speedometer2 me-1"></i>Dashboard
            </a>
            <a href="<?= url('analytics') ?>" class="btn btn-outline-primary">
                <i class="bi bi-graph-up-arrow me-1"></i>Analytics
            </a>
        </div>
    </div>
</div>
