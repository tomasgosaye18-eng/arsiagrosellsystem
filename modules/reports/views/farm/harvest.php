<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * View     : modules/reports/views/farm/harvest.php
 * Purpose  : Harvest Report — detailed, paginated list.
 *
 * @var \AAM\Core\View  $view
 * @var array|null      $farm
 * @var int             $farmId
 * @var array           $harvests      current page rows
 * @var array           $pagination    {data, total, page, perPage, totalPages, from, to}
 * @var array           $products      for filter dropdown
 * @var array           $filters
 * @var string          $userRole
 */
declare(strict_types=1);

$farmName     = $farm['name'] ?? 'All Farms';
$f            = $filters;
$activePreset = $f['preset'] ?? 'month';

// Status badge map (mirrors HarvestBatchModel constants)
$statusMap = [
    'active'               => ['success',   'Active'],
    'partially_dispatched' => ['warning',   'Partial Dispatch'],
    'fully_dispatched'     => ['primary',   'Fully Dispatched'],
    'depleted'             => ['secondary', 'Depleted'],
    'expired'              => ['danger',    'Expired'],
    'quarantine'           => ['dark',      'Quarantine'],
];

$buildPageUrl = function(int $pg) use ($f): string {
    $qs = http_build_query(array_merge($f, ['page' => $pg]));
    return url('reports/farm/harvest') . '?' . $qs;
};
?>

<!-- ══════════════════════════════════════════════════════════
     BREADCRUMB + HEADER
     ══════════════════════════════════════════════════════════ -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= url('reports/farm') ?>">Farm Reports</a></li>
        <li class="breadcrumb-item active">Harvest Report</li>
    </ol>
</nav>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">
            <i class="bi bi-basket2-fill text-success me-2"></i>Harvest Report
        </h4>
        <small class="text-muted">
            <?= e($farmName) ?> &middot;
            <?= $f['date_from'] ? date('d M Y', strtotime($f['date_from'])) : '' ?>
            <?= ($f['date_from'] && $f['date_to']) ? '–' : '' ?>
            <?= $f['date_to']   ? date('d M Y', strtotime($f['date_to']))   : '' ?>
            <?php if ($pagination['total'] > 0): ?>
            &middot; <?= number_format($pagination['total']) ?> records
            <?php endif; ?>
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
        <form method="get" action="<?= url('reports/farm/harvest') ?>" id="filterForm">

            <!-- Preset Buttons Row -->
            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                <span class="text-muted small fw-semibold me-1">Period:</span>
                <?php foreach (['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'custom' => 'Custom'] as $p => $label): ?>
                <a href="<?= url('reports/farm/harvest') ?>?preset=<?= $p ?>"
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
                <?php else: ?>
                <input type="hidden" name="preset" value="<?= e($activePreset) ?>">
                <?php endif; ?>
            </div>

            <!-- Search + Dropdowns Row -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <input type="search" name="search" class="form-control form-control-sm"
                       placeholder="Search batch or product…"
                       value="<?= e($f['search'] ?? '') ?>" style="width:200px;">

                <select name="product_id" class="form-select form-select-sm" style="width:180px;">
                    <option value="">All Products</option>
                    <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>"
                            <?= (int)($f['product_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                        <?= e($p['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <select name="status" class="form-select form-select-sm" style="width:180px;">
                    <option value="">All Statuses</option>
                    <?php foreach ($statusMap as $val => [$cls, $lbl]): ?>
                    <option value="<?= $val ?>" <?= ($f['status'] ?? '') === $val ? 'selected' : '' ?>>
                        <?= $lbl ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <button class="btn btn-success btn-sm">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>

                <?php if (!empty($f['search']) || !empty($f['product_id']) || !empty($f['status'])): ?>
                <a href="<?= url('reports/farm/harvest') ?>?preset=<?= e($activePreset) ?>"
                   class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-x me-1"></i>Clear
                </a>
                <?php endif; ?>
            </div>

        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     HARVEST TABLE
     ══════════════════════════════════════════════════════════ -->
<div class="card border-0 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center py-2">
        <span class="fw-semibold small">
            <i class="bi bi-table me-2"></i>Harvest Records
        </span>
        <?php if ($pagination['total'] > 0): ?>
        <small class="text-muted">
            Showing <?= number_format($pagination['from']) ?>–<?= number_format($pagination['to']) ?>
            of <?= number_format($pagination['total']) ?>
        </small>
        <?php endif; ?>
    </div>

    <?php if (empty($harvests)): ?>
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-basket d-block fs-2 mb-2 opacity-25"></i>
        <small>No harvest records found for the selected period and filters.</small>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3 small fw-semibold">Date</th>
                    <th class="small fw-semibold">Product</th>
                    <th class="small fw-semibold text-end">Quantity</th>
                    <th class="small fw-semibold">Unit</th>
                    <th class="small fw-semibold">Batch</th>
                    <th class="small fw-semibold">Recorded By</th>
                    <th class="small fw-semibold pe-3">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($harvests as $h):
                    [$sc, $sl] = $statusMap[$h['status'] ?? ''] ?? ['secondary', ucfirst($h['status'] ?? '—')];
                ?>
                <tr>
                    <td class="ps-3 small text-muted">
                        <?= !empty($h['harvest_date']) ? date('d M Y', strtotime($h['harvest_date'])) : '—' ?>
                    </td>
                    <td class="small fw-semibold">
                        <?= e($h['product_name'] ?? '—') ?>
                    </td>
                    <td class="small text-end fw-bold text-success">
                        <?= isset($h['initial_quantity'])
                            ? number_format((float)$h['initial_quantity'], 1)
                            : '—' ?>
                    </td>
                    <td class="small text-muted">
                        <?= e($h['unit_abbr'] ?? '—') ?>
                    </td>
                    <td class="small font-monospace">
                        <a href="<?= url('harvests/' . $h['id']) ?>"
                           class="text-decoration-none text-dark">
                            <?= e($h['batch_number'] ?? '—') ?>
                        </a>
                    </td>
                    <td class="small text-muted">
                        <?= e($h['recorded_by_name'] ?? '—') ?>
                    </td>
                    <td class="pe-3">
                        <span class="badge bg-<?= $sc ?>-subtle text-<?= $sc ?> small">
                            <?= $sl ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pagination['totalPages'] > 1): ?>
    <div class="card-footer bg-transparent border-top py-2 px-3">
        <nav aria-label="Page navigation">
            <ul class="pagination pagination-sm mb-0 justify-content-center">

                <li class="page-item <?= $pagination['page'] <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link"
                       href="<?= $buildPageUrl($pagination['page'] - 1) ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>

                <?php
                $start = max(1, $pagination['page'] - 2);
                $end   = min($pagination['totalPages'], $pagination['page'] + 2);
                if ($start > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="<?= $buildPageUrl(1) ?>">1</a>
                </li>
                <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <?php endif; ?>

                <?php for ($pg = $start; $pg <= $end; $pg++): ?>
                <li class="page-item <?= $pg === $pagination['page'] ? 'active' : '' ?>">
                    <a class="page-link" href="<?= $buildPageUrl($pg) ?>"><?= $pg ?></a>
                </li>
                <?php endfor; ?>

                <?php if ($end < $pagination['totalPages']): ?>
                <?php if ($end < $pagination['totalPages'] - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <li class="page-item">
                    <a class="page-link" href="<?= $buildPageUrl($pagination['totalPages']) ?>">
                        <?= $pagination['totalPages'] ?>
                    </a>
                </li>
                <?php endif; ?>

                <li class="page-item <?= $pagination['page'] >= $pagination['totalPages'] ? 'disabled' : '' ?>">
                    <a class="page-link"
                       href="<?= $buildPageUrl($pagination['page'] + 1) ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>

            </ul>
        </nav>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>
