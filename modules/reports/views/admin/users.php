<?php
/**
 * View : modules/reports/views/admin/users.php
 * Purpose : User Report — role breakdown, login trends, user list, failed logins.
 *
 * @var \AAM\Core\View  $view
 * @var array           $summary
 * @var array           $byRole
 * @var array           $userList    paginated result
 * @var array           $loginTrend  {labels, success, failed}
 * @var array           $failedLogins
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

$statusBadge = function(array $u): string {
    if ($u['is_locked'])                          return '<span class="badge bg-danger-subtle text-danger small">Locked</span>';
    if ($u['account_status'] === 'suspended')     return '<span class="badge bg-warning-subtle text-warning small">Suspended</span>';
    if (!$u['is_active'])                         return '<span class="badge bg-secondary-subtle text-secondary small">Inactive</span>';
    return '<span class="badge bg-success-subtle text-success small">Active</span>';
};

$buildPageUrl = function(int $pg) use ($filters, $dateFrom, $dateTo, $activePreset): string {
    $q = http_build_query(array_merge($filters, ['page' => $pg, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'preset' => $activePreset]));
    return url('reports/admin/users') . '?' . $q;
};
?>

<!-- ══ BREADCRUMB + HEADER ══════════════════════════════════════ -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= url('reports/admin') ?>">Admin Reports</a></li>
        <li class="breadcrumb-item active">User Report</li>
    </ol>
</nav>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-people-fill text-primary me-2"></i>User Report</h4>
        <small class="text-muted">
            <?= date('d M Y', strtotime($dateFrom)) ?> – <?= date('d M Y', strtotime($dateTo)) ?>
        </small>
    </div>
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-printer me-1"></i>Print
    </button>
</div>

<!-- ══ SUMMARY CARDS ════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Total Users',       $summary['total'] ?? 0,            'primary',   'bi-people-fill'],
        ['Active',            $summary['active'] ?? 0,           'success',   'bi-person-check-fill'],
        ['Inactive',          $summary['inactive'] ?? 0,         'secondary', 'bi-person-dash-fill'],
        ['Locked',            $summary['locked'] ?? 0,           'danger',    'bi-lock-fill'],
        ['New This Month',    $summary['new_this_month'] ?? 0,   'info',      'bi-person-plus-fill'],
        ['Failed Logins Today',$summary['failed_logins_today'] ?? 0,'warning','bi-exclamation-triangle-fill'],
    ];
    foreach ($cards as [$label, $val, $color, $icon]): ?>
    <div class="col-6 col-xl-2">
        <div class="card border-0 shadow-sm h-100" style="border-top:3px solid var(--bs-<?= $color ?>)!important;">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-muted small"><?= $label ?></span>
                    <i class="bi <?= $icon ?> text-<?= $color ?>"></i>
                </div>
                <div class="fw-bold fs-3 lh-1"><?= $fmt($val) ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ══ ROLE BREAKDOWN + LOGIN TREND ════════════════════════════ -->
<div class="row g-3 mb-4">

    <!-- Role breakdown -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header py-2">
                <span class="fw-semibold small"><i class="bi bi-person-badge me-2 text-primary"></i>Users by Role</span>
            </div>
            <?php if (empty($byRole)): ?>
            <div class="card-body text-center text-muted py-4 small">No role data found.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3 small fw-semibold">Role</th>
                            <th class="small fw-semibold text-end">Total</th>
                            <th class="small fw-semibold text-end">Active</th>
                            <th class="small fw-semibold pe-3 text-end">Locked</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($byRole as $r): ?>
                        <tr>
                            <td class="ps-3 small fw-semibold"><?= e($r['role_label']) ?></td>
                            <td class="small text-end"><?= $fmt($r['user_count']) ?></td>
                            <td class="small text-end text-success"><?= $fmt($r['active_count']) ?></td>
                            <td class="small text-end pe-3 <?= $r['locked_count'] > 0 ? 'text-danger fw-bold' : '' ?>">
                                <?= $fmt($r['locked_count']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Login trend chart -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <span class="fw-semibold small"><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Login Activity</span>
                <!-- Date preset -->
                <div class="d-flex gap-1">
                    <?php foreach (['today' => 'Today', 'week' => 'Week', 'month' => 'Month'] as $p => $lbl): ?>
                    <a href="<?= url('reports/admin/users') ?>?preset=<?= $p ?>"
                       class="btn btn-xs <?= $activePreset === $p ? 'btn-primary' : 'btn-outline-secondary' ?>"
                       style="font-size:.7rem;padding:2px 8px;">
                        <?= $lbl ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card-body py-3">
                <?php if (empty($loginTrend['labels'])): ?>
                <div class="text-center text-muted py-4 small">No login data for this period.</div>
                <?php else: ?>
                <canvas id="loginTrendChart" style="max-height:180px;"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<!-- ══ USER LIST ════════════════════════════════════════════════ -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center py-2">
        <span class="fw-semibold small"><i class="bi bi-table me-2"></i>All Users</span>
        <small class="text-muted"><?= $fmt($userList['total']) ?> records</small>
    </div>

    <!-- Filters -->
    <div class="card-body border-bottom py-2 px-3">
        <form method="get" action="<?= url('reports/admin/users') ?>" class="d-flex gap-2 flex-wrap align-items-center">
            <input type="hidden" name="preset" value="<?= e($activePreset) ?>">
            <input type="search" name="search" class="form-control form-control-sm" style="width:200px;"
                   placeholder="Search name / username…" value="<?= e($filters['search'] ?? '') ?>">
            <select name="role" class="form-select form-select-sm" style="width:180px;">
                <option value="">All Roles</option>
                <?php foreach ($roles as $r): ?>
                <option value="<?= e($r['name']) ?>" <?= ($filters['role'] ?? '') === $r['name'] ? 'selected' : '' ?>>
                    <?= e($r['display_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="form-select form-select-sm" style="width:150px;">
                <option value="">All Statuses</option>
                <option value="active"   <?= ($filters['status'] ?? '') === 'active'   ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="locked"   <?= ($filters['status'] ?? '') === 'locked'   ? 'selected' : '' ?>>Locked</option>
                <option value="suspended"<?= ($filters['status'] ?? '') === 'suspended'? 'selected' : '' ?>>Suspended</option>
            </select>
            <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
            <?php if (!empty($filters['search']) || !empty($filters['role']) || !empty($filters['status'])): ?>
            <a href="<?= url('reports/admin/users') ?>?preset=<?= e($activePreset) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-x me-1"></i>Clear
            </a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($userList['data'])): ?>
    <div class="card-body text-center py-5 text-muted small">
        <i class="bi bi-people d-block fs-2 mb-2 opacity-25"></i>No users found.
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3 small fw-semibold">User</th>
                    <th class="small fw-semibold">Role</th>
                    <th class="small fw-semibold">Status</th>
                    <th class="small fw-semibold text-end">Total Logins</th>
                    <th class="small fw-semibold text-end">Failed (30d)</th>
                    <th class="small fw-semibold">Last Login</th>
                    <th class="small fw-semibold pe-3">Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($userList['data'] as $u): ?>
                <tr>
                    <td class="ps-3">
                        <div class="small fw-semibold"><?= e(trim($u['full_name'])) ?></div>
                        <div class="text-muted" style="font-size:.68rem;">
                            <?= e($u['username']) ?>
                            <?php if ($u['employee_id']): ?> &middot; <?= e($u['employee_id']) ?><?php endif; ?>
                        </div>
                    </td>
                    <td class="small text-muted"><?= e($u['role_label'] ?? '—') ?></td>
                    <td><?= $statusBadge($u) ?></td>
                    <td class="small text-end"><?= $fmt($u['total_logins'] ?? 0) ?></td>
                    <td class="small text-end <?= ($u['failed_30d'] ?? 0) > 3 ? 'text-danger fw-bold' : '' ?>">
                        <?= $fmt($u['failed_30d'] ?? 0) ?>
                    </td>
                    <td class="small text-muted">
                        <?= $u['last_login_at'] ? date('d M Y H:i', strtotime($u['last_login_at'])) : '—' ?>
                    </td>
                    <td class="small text-muted pe-3">
                        <?= $u['created_at'] ? date('d M Y', strtotime($u['created_at'])) : '—' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($userList['totalPages'] > 1): ?>
    <div class="card-footer bg-transparent py-2 px-3">
        <nav><ul class="pagination pagination-sm mb-0 justify-content-center">
            <li class="page-item <?= $userList['page'] <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $buildPageUrl($userList['page'] - 1) ?>"><i class="bi bi-chevron-left"></i></a>
            </li>
            <?php for ($pg = 1; $pg <= $userList['totalPages']; $pg++): ?>
            <li class="page-item <?= $pg === $userList['page'] ? 'active' : '' ?>">
                <a class="page-link" href="<?= $buildPageUrl($pg) ?>"><?= $pg ?></a>
            </li>
            <?php endfor; ?>
            <li class="page-item <?= $userList['page'] >= $userList['totalPages'] ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $buildPageUrl($userList['page'] + 1) ?>"><i class="bi bi-chevron-right"></i></a>
            </li>
        </ul></nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- ══ RECENT FAILED LOGINS ════════════════════════════════════ -->
<?php if (!empty($failedLogins)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-header py-2">
        <span class="fw-semibold small"><i class="bi bi-shield-exclamation me-2 text-danger"></i>Recent Failed Login Attempts</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3 small fw-semibold">Date &amp; Time</th>
                    <th class="small fw-semibold">Username Attempted</th>
                    <th class="small fw-semibold">IP Address</th>
                    <th class="small fw-semibold">Browser / OS</th>
                    <th class="small fw-semibold pe-3">Reason</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($failedLogins as $fl): ?>
                <tr>
                    <td class="ps-3 small text-muted">
                        <?= !empty($fl['attempted_at']) ? date('d M Y H:i', strtotime($fl['attempted_at'])) : '—' ?>
                    </td>
                    <td class="small fw-semibold"><?= e($fl['username_attempted'] ?? '—') ?></td>
                    <td class="small font-monospace text-muted"><?= e($fl['ip_address'] ?? '—') ?></td>
                    <td class="small text-muted"><?= e(($fl['browser'] ?? '—') . ' / ' . ($fl['os_name'] ?? '—')) ?></td>
                    <td class="small pe-3">
                        <span class="badge bg-danger-subtle text-danger"><?= e($fl['failure_reason'] ?? 'Unknown') ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php $view->section('scripts'); ?>
<script>
(function(){
    var trend = <?= json_encode($loginTrend, JSON_THROW_ON_ERROR) ?>;
    var ctx   = document.getElementById('loginTrendChart');
    if (!ctx || !trend.labels || !trend.labels.length) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: trend.labels,
            datasets: [
                { label: 'Successful', data: trend.success, backgroundColor: 'rgba(25,135,84,.75)', borderRadius: 3 },
                { label: 'Failed',     data: trend.failed,  backgroundColor: 'rgba(220,53,69,.65)',  borderRadius: 3 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: true,
            animation: false,
            plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } },
            scales: {
                x: { stacked: false, ticks: { font: { size: 10 }, maxRotation: 45 } },
                y: { beginAtZero: true, ticks: { font: { size: 11 } } }
            }
        }
    });
})();
</script>
<?php $view->endSection(); ?>
