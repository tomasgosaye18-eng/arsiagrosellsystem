<?php
/**
 * View : modules/reports/views/admin/audit.php
 * Purpose : Audit Summary Report — actions by module, top users, trend, log list.
 *
 * @var \AAM\Core\View  $view
 * @var array           $summary
 * @var array           $byModule
 * @var array           $topUsers
 * @var array           $auditTrend {labels, data}
 * @var array           $logList    paginated
 * @var array           $roles
 * @var array           $filters
 * @var string          $dateFrom
 * @var string          $dateTo
 * @var string          $datePreset
 * @var string          $userRole
 */
declare(strict_types=1);

$fmt         = fn(mixed $v): string => is_numeric($v) ? number_format((int)$v) : ($v ?? '—');
$activePreset = $datePreset ?? 'month';
// $baseUrl: 'reports/admin/audit' for admin, 'gm/reports/audit' for GM
$baseUrl      = $baseUrl ?? 'reports/admin/audit';
$backUrl      = str_starts_with($baseUrl, 'gm/') ? url('gm/reports') : url('reports/admin');

// Module badge colours matching AuditController
$modColor = [
    'farm'      => 'success',
    'warehouse' => 'primary',
    'transfer'  => 'info',
    'shop'      => 'warning',
    'sale'      => 'warning',
    'product'   => 'secondary',
    'user'      => 'dark',
    'admin'     => 'danger',
    'system'    => 'light',
    'harvest'   => 'success',
    'dispatch'  => 'success',
    'inventory' => 'info',
    'organization' => 'primary',
    'receiving' => 'info',
];

$buildPageUrl = function(int $pg) use ($filters, $activePreset, $baseUrl): string {
    $q = http_build_query(array_merge($filters, ['page' => $pg, 'preset' => $activePreset]));
    return url($baseUrl) . '?' . $q;
};
?>

<!-- ══ BREADCRUMB + HEADER ══════════════════════════════════════ -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= $backUrl ?>">Reports</a></li>
        <li class="breadcrumb-item active">Audit Summary</li>
    </ol>
</nav>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">
            <i class="bi bi-clipboard2-data-fill me-2" style="color:#6f42c1;"></i>Audit Summary Report
        </h4>
        <small class="text-muted">
            <?= date('d M Y', strtotime($dateFrom)) ?> – <?= date('d M Y', strtotime($dateTo)) ?>
        </small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('admin/audit-logs') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-clipboard2-data me-1"></i>Full Audit Trail
        </a>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer me-1"></i>Print
        </button>
    </div>
</div>

<!-- ══ DATE PRESET BAR ══════════════════════════════════════════ -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2 px-3">
        <form method="get" action="<?= url($baseUrl) ?>" class="d-flex gap-2 flex-wrap align-items-center">
            <span class="text-muted small fw-semibold me-1">Period:</span>
            <?php foreach (['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'custom' => 'Custom'] as $p => $lbl): ?>
            <a href="<?= url($baseUrl) ?>?preset=<?= $p ?>"
               class="btn btn-sm <?= $activePreset === $p ? 'btn-primary' : 'btn-outline-secondary' ?>">
                <?= $lbl ?>
            </a>
            <?php endforeach; ?>
            <?php if ($activePreset === 'custom'): ?>
            <div class="d-flex align-items-center gap-1 ms-2">
                <input type="date" name="date_from" class="form-control form-control-sm" style="width:140px;"
                       value="<?= e($dateFrom) ?>">
                <span class="text-muted small">to</span>
                <input type="date" name="date_to" class="form-control form-control-sm" style="width:140px;"
                       value="<?= e($dateTo) ?>">
                <input type="hidden" name="preset" value="custom">
                <button class="btn btn-primary btn-sm">Apply</button>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- ══ SUMMARY CARDS ════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
    <?php
    $auditCards = [
        ['Total Actions',    $summary['total_actions'] ?? 0,  '#6f42c1', 'bi-clipboard2-data-fill'],
        ['Unique Users',     $summary['unique_users'] ?? 0,   '#0d6efd', 'bi-people-fill'],
        ['Modules Active',   $summary['modules_active'] ?? 0, '#198754', 'bi-grid-fill'],
        ['Actions Today',    $summary['actions_today'] ?? 0,  '#fd7e14', 'bi-activity'],
    ];
    foreach ($auditCards as [$lbl, $val, $color, $icon]): ?>
    <div class="col-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100" style="border-top:3px solid <?= $color ?>!important;">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small"><?= $lbl ?></span>
                    <i class="bi <?= $icon ?>" style="color:<?= $color ?>;"></i>
                </div>
                <div class="fw-bold fs-3 lh-1"><?= $fmt($val) ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ══ BY MODULE + TOP USERS ════════════════════════════════════ -->
<div class="row g-3 mb-4">

    <!-- Actions by module -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header py-2">
                <span class="fw-semibold small"><i class="bi bi-grid me-2" style="color:#6f42c1;"></i>Actions by Module</span>
            </div>
            <?php if (empty($byModule)): ?>
            <div class="card-body text-center text-muted small py-4">No audit data for this period.</div>
            <?php else: ?>
            <div class="card-body p-0">
                <?php
                $maxCount = max(array_column($byModule, 'action_count') ?: [1]);
                foreach ($byModule as $i => $m):
                    $color = $modColor[$m['module']] ?? 'secondary';
                    $pct   = $maxCount > 0 ? round(($m['action_count'] / $maxCount) * 100) : 0;
                ?>
                <div class="d-flex align-items-center gap-3 px-3 py-2 <?= $i < count($byModule)-1 ? 'border-bottom' : '' ?>">
                    <span class="badge bg-<?= $color ?>-subtle text-<?= $color ?> small text-uppercase"
                          style="min-width:80px;text-align:center;">
                        <?= e($m['module']) ?>
                    </span>
                    <div class="flex-grow-1">
                        <div class="progress" style="height:6px;">
                            <div class="progress-bar bg-<?= $color ?>" style="width:<?= $pct ?>%;"></div>
                        </div>
                    </div>
                    <span class="small fw-semibold" style="min-width:36px;text-align:right;">
                        <?= $fmt($m['action_count']) ?>
                    </span>
                    <span class="text-muted small" style="min-width:60px;">
                        <?= $fmt($m['unique_users']) ?> user(s)
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top active users -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header py-2">
                <span class="fw-semibold small"><i class="bi bi-trophy-fill me-2 text-warning"></i>Most Active Users</span>
            </div>
            <?php if (empty($topUsers)): ?>
            <div class="card-body text-center text-muted small py-4">No data for this period.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3 small fw-semibold">#</th>
                            <th class="small fw-semibold">User</th>
                            <th class="small fw-semibold">Role</th>
                            <th class="small fw-semibold text-end">Actions</th>
                            <th class="small fw-semibold pe-3">Last Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topUsers as $i => $u): ?>
                        <tr>
                            <td class="ps-3 small text-muted"><?= $i + 1 ?></td>
                            <td class="small fw-semibold">
                                <?= e($u['user_name'] ?? '—') ?>
                                <div class="text-muted" style="font-size:.68rem;"><?= e($u['username'] ?? '') ?></div>
                            </td>
                            <td class="small text-muted"><?= e($u['role_name'] ?? '—') ?></td>
                            <td class="small text-end fw-bold"><?= $fmt($u['action_count']) ?></td>
                            <td class="small text-muted pe-3">
                                <?= !empty($u['last_action_at']) ? date('d M Y H:i', strtotime($u['last_action_at'])) : '—' ?>
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

<!-- ══ TREND CHART ══════════════════════════════════════════════ -->
<?php if (!empty($auditTrend['labels'])): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header py-2">
        <span class="fw-semibold small"><i class="bi bi-bar-chart-fill me-2" style="color:#6f42c1;"></i>Activity Trend</span>
    </div>
    <div class="card-body py-3">
        <canvas id="auditTrendChart" style="max-height:160px;"></canvas>
    </div>
</div>
<?php endif; ?>

<!-- ══ RECENT AUDIT LOG ══════════════════════════════════════════ -->
<div class="card border-0 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center py-2">
        <span class="fw-semibold small"><i class="bi bi-list-ul me-2"></i>Audit Log</span>
        <small class="text-muted"><?= $fmt($logList['total']) ?> records</small>
    </div>

    <!-- Filters -->
    <div class="card-body border-bottom py-2 px-3">
        <form method="get" action="<?= url($baseUrl) ?>" class="d-flex gap-2 flex-wrap align-items-center">
            <input type="hidden" name="preset" value="<?= e($activePreset) ?>">
            <input type="hidden" name="date_from" value="<?= e($dateFrom) ?>">
            <input type="hidden" name="date_to"   value="<?= e($dateTo) ?>">
            <input type="search" name="search" class="form-control form-control-sm" style="width:200px;"
                   placeholder="Search description…" value="<?= e($filters['search'] ?? '') ?>">
            <select name="module" class="form-select form-select-sm" style="width:160px;">
                <option value="">All Modules</option>
                <?php foreach (array_unique(array_column($byModule, 'module')) as $mod): ?>
                <option value="<?= e($mod) ?>" <?= ($filters['module'] ?? '') === $mod ? 'selected' : '' ?>>
                    <?= ucfirst(e($mod)) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
            <?php if (!empty($filters['search']) || !empty($filters['module'])): ?>
            <a href="<?= url($baseUrl) ?>?preset=<?= e($activePreset) ?>&date_from=<?= e($dateFrom) ?>&date_to=<?= e($dateTo) ?>"
               class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-x me-1"></i>Clear
            </a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($logList['data'])): ?>
    <div class="card-body text-center text-muted small py-5">
        <i class="bi bi-clipboard2 d-block fs-2 mb-2 opacity-25"></i>No audit records for this period.
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3 small fw-semibold">Date &amp; Time</th>
                    <th class="small fw-semibold">User</th>
                    <th class="small fw-semibold">Module</th>
                    <th class="small fw-semibold">Action</th>
                    <th class="small fw-semibold">Entity</th>
                    <th class="small fw-semibold pe-3">Description</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logList['data'] as $log):
                    $mColor = $modColor[$log['module'] ?? ''] ?? 'secondary';
                    $location = $log['farm_name'] ?? $log['warehouse_name'] ?? $log['shop_name'] ?? null;
                ?>
                <tr>
                    <td class="ps-3 small text-muted font-monospace" style="font-size:.72rem;">
                        <?= !empty($log['action_at']) ? date('d M Y H:i', strtotime($log['action_at'])) : '—' ?>
                    </td>
                    <td class="small">
                        <span class="fw-semibold"><?= e($log['user_name'] ?? 'System') ?></span>
                        <?php if (!empty($log['role_name'])): ?>
                        <div class="text-muted" style="font-size:.68rem;"><?= e($log['role_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-<?= $mColor ?>-subtle text-<?= $mColor ?> small">
                            <?= e(ucfirst($log['module'] ?? '—')) ?>
                        </span>
                    </td>
                    <td class="small text-muted">
                        <?= e(strtoupper(str_replace(['.','_'], ' ', $log['action_type'] ?? '—'))) ?>
                    </td>
                    <td class="small text-muted">
                        <?= e($log['entity_label'] ?? $log['entity_type'] ?? '—') ?>
                        <?php if ($location): ?>
                        <div style="font-size:.68rem;" class="text-muted"><?= e($location) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="small pe-3 text-muted" style="max-width:260px;">
                        <span class="text-truncate d-inline-block" style="max-width:250px;"
                              title="<?= e($log['description'] ?? '') ?>">
                            <?= e($log['description'] ?? '—') ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($logList['totalPages'] > 1): ?>
    <div class="card-footer bg-transparent py-2 px-3">
        <nav><ul class="pagination pagination-sm mb-0 justify-content-center">
            <li class="page-item <?= $logList['page'] <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $buildPageUrl($logList['page'] - 1) ?>"><i class="bi bi-chevron-left"></i></a>
            </li>
            <?php
            $start = max(1, $logList['page'] - 2);
            $end   = min($logList['totalPages'], $logList['page'] + 2);
            for ($pg = $start; $pg <= $end; $pg++): ?>
            <li class="page-item <?= $pg === $logList['page'] ? 'active' : '' ?>">
                <a class="page-link" href="<?= $buildPageUrl($pg) ?>"><?= $pg ?></a>
            </li>
            <?php endfor; ?>
            <li class="page-item <?= $logList['page'] >= $logList['totalPages'] ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $buildPageUrl($logList['page'] + 1) ?>"><i class="bi bi-chevron-right"></i></a>
            </li>
        </ul></nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php $view->section('scripts'); ?>
<script>
(function(){
    var trend = <?= json_encode($auditTrend, JSON_THROW_ON_ERROR) ?>;
    var ctx   = document.getElementById('auditTrendChart');
    if (!ctx || !trend.labels || !trend.labels.length) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: trend.labels,
            datasets: [{
                label: 'Actions',
                data:  trend.data,
                backgroundColor: 'rgba(111,66,193,.7)',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: true,
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
