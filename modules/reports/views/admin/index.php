<?php
/**
 * View : modules/reports/views/admin/index.php
 * Purpose : Administration Reports hub — summary cards + 3 report navigation cards.
 *
 * @var \AAM\Core\View  $view
 * @var array           $summary
 * @var string          $userRole
 */
declare(strict_types=1);

$fmt    = fn(mixed $v): string => is_numeric($v) ? number_format((int)$v) : ($v ?? '—');
$isAdmin = in_array($userRole ?? '', [ROLE_ADMIN, ROLE_GENERAL_MANAGER], true);
?>

<!-- ══ PAGE HEADER ══════════════════════════════════════════════ -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">
            <i class="bi bi-shield-fill-check text-primary me-2"></i>Administration Reports
        </h4>
        <small class="text-muted">System overview — Users, Organization &amp; Audit Trail</small>
    </div>
</div>

<!-- ══ SUMMARY CARDS ════════════════════════════════════════════ -->
<div class="row g-3 mb-4">

    <div class="col-6 col-xl-2">
        <div class="card border-0 shadow-sm h-100" style="border-top:3px solid #0d6efd!important;">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small">Total Users</span>
                    <i class="bi bi-people-fill text-primary"></i>
                </div>
                <div class="fw-bold fs-3 lh-1"><?= $fmt($summary['total_users'] ?? 0) ?></div>
                <div class="text-muted" style="font-size:.72rem;">registered accounts</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-xl-2">
        <div class="card border-0 shadow-sm h-100" style="border-top:3px solid #198754!important;">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small">Active Users</span>
                    <i class="bi bi-person-check-fill text-success"></i>
                </div>
                <div class="fw-bold fs-3 lh-1 text-success"><?= $fmt($summary['active_users'] ?? 0) ?></div>
                <div class="text-muted" style="font-size:.72rem;">active &amp; enabled</div>
            </div>
        </div>
    </div>

    <?php $locked = (int)($summary['locked_users'] ?? 0); ?>
    <div class="col-6 col-xl-2">
        <div class="card border-0 shadow-sm h-100" style="border-top:3px solid <?= $locked > 0 ? '#dc3545' : '#dee2e6' ?>!important;">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small">Locked</span>
                    <i class="bi bi-lock-fill <?= $locked > 0 ? 'text-danger' : 'text-secondary' ?>"></i>
                </div>
                <div class="fw-bold fs-3 lh-1 <?= $locked > 0 ? 'text-danger' : '' ?>"><?= $fmt($locked) ?></div>
                <div class="text-muted" style="font-size:.72rem;">locked accounts</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-xl-2">
        <div class="card border-0 shadow-sm h-100" style="border-top:3px solid #20c997!important;">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small">Locations</span>
                    <i class="bi bi-geo-fill text-success"></i>
                </div>
                <div class="fw-bold fs-3 lh-1"><?= $fmt($summary['total_locations'] ?? 0) ?></div>
                <div class="text-muted" style="font-size:.72rem;">farms + warehouses + shops</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-xl-2">
        <div class="card border-0 shadow-sm h-100" style="border-top:3px solid #fd7e14!important;">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small">Assignments</span>
                    <i class="bi bi-person-badge-fill" style="color:#fd7e14;"></i>
                </div>
                <div class="fw-bold fs-3 lh-1"><?= $fmt($summary['total_assignments'] ?? 0) ?></div>
                <div class="text-muted" style="font-size:.72rem;">active staff assignments</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-xl-2">
        <div class="card border-0 shadow-sm h-100" style="border-top:3px solid #6f42c1!important;">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small">Audit Today</span>
                    <i class="bi bi-clipboard2-data-fill" style="color:#6f42c1;"></i>
                </div>
                <div class="fw-bold fs-3 lh-1"><?= $fmt($summary['audit_today'] ?? 0) ?></div>
                <div class="text-muted" style="font-size:.72rem;">actions logged today</div>
            </div>
        </div>
    </div>

</div>

<!-- ══ REPORT NAVIGATION CARDS ══════════════════════════════════ -->
<div class="row g-3">

    <!-- User Report — System Administrator only -->
    <?php if (($userRole ?? '') === ROLE_ADMIN): ?>
    <div class="col-md-4">
        <a href="<?= url('reports/admin/users') ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 rpt-card">
                <div class="card-body d-flex align-items-start gap-3 py-4">
                    <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center
                                justify-content-center flex-shrink-0" style="width:54px;height:54px;">
                        <i class="bi bi-people-fill text-primary fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-bold mb-1">User Report</div>
                        <div class="text-muted small">
                            User accounts by role, login trends, failed attempts, and account status breakdown.
                        </div>
                        <div class="mt-2 d-flex gap-1 flex-wrap">
                            <span class="badge bg-primary-subtle text-primary small">
                                <?= $fmt($summary['total_users'] ?? 0) ?> users
                            </span>
                            <?php if (($summary['locked_users'] ?? 0) > 0): ?>
                            <span class="badge bg-danger-subtle text-danger small">
                                <?= $fmt($summary['locked_users']) ?> locked
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
    <?php endif; ?>

    <!-- Organization Report -->
    <div class="col-md-4">
        <a href="<?= url('reports/admin/organization') ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 rpt-card">
                <div class="card-body d-flex align-items-start gap-3 py-4">
                    <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center
                                justify-content-center flex-shrink-0" style="width:54px;height:54px;">
                        <i class="bi bi-diagram-3-fill text-success fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-bold mb-1">Organization Report</div>
                        <div class="text-muted small">
                            Farms, warehouses, and shops overview with staff assignments and coverage status.
                        </div>
                        <div class="mt-2">
                            <span class="badge bg-success-subtle text-success small">
                                <?= $fmt($summary['total_locations'] ?? 0) ?> locations
                            </span>
                            <span class="badge bg-warning-subtle text-warning small ms-1">
                                <?= $fmt($summary['total_assignments'] ?? 0) ?> staff assigned
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

    <!-- Audit Summary -->
    <div class="col-md-4">
        <a href="<?= url('reports/admin/audit') ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 rpt-card">
                <div class="card-body d-flex align-items-start gap-3 py-4">
                    <div class="rounded-circle d-flex align-items-center justify-content-center
                                flex-shrink-0" style="width:54px;height:54px;background:rgba(111,66,193,.1);">
                        <i class="bi bi-clipboard2-data-fill fs-4" style="color:#6f42c1;"></i>
                    </div>
                    <div>
                        <div class="fw-bold mb-1">Audit Summary</div>
                        <div class="text-muted small">
                            Who did what and when — actions by module, most active users, operational trend.
                        </div>
                        <div class="mt-2">
                            <span class="badge small" style="background:rgba(111,66,193,.15);color:#6f42c1;">
                                <?= $fmt($summary['audit_today'] ?? 0) ?> actions today
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-top-0 py-2 px-3">
                    <span class="small fw-semibold" style="color:#6f42c1;">
                        View Report <i class="bi bi-arrow-right ms-1"></i>
                    </span>
                </div>
            </div>
        </a>
    </div>

</div>

<!-- Quick link to full Audit Trail -->
<div class="mt-4 text-center">
    <a href="<?= url('admin/audit-logs') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-clipboard2-data me-1"></i>Open Full Business Audit Trail
    </a>
</div>

<style>
.rpt-card { transition: box-shadow .18s, transform .18s; cursor: pointer; }
.rpt-card:hover { box-shadow: 0 .5rem 1.5rem rgba(0,0,0,.12) !important; transform: translateY(-2px); }
</style>
