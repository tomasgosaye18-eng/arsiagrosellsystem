<?php
/**
 * View : modules/reports/views/admin/organization.php
 * Purpose : Organization Report — farms, warehouses, shops, staff assignments.
 *
 * @var \AAM\Core\View  $view
 * @var array           $summary
 * @var array           $farms
 * @var array           $warehouses
 * @var array           $shops
 * @var array           $assignments
 * @var string          $userRole
 */
declare(strict_types=1);

$fmt = fn(mixed $v): string => is_numeric($v) ? number_format((int)$v) : ($v ?? '—');

// Location type icon/color map
$locMeta = [
    'farm'      => ['bi-flower1',     'success'],
    'warehouse' => ['bi-building',    'primary'],
    'shop'      => ['bi-shop',        'warning'],
];
?>

<!-- ══ BREADCRUMB + HEADER ══════════════════════════════════════ -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= url('reports/admin') ?>">Admin Reports</a></li>
        <li class="breadcrumb-item active">Organization Report</li>
    </ol>
</nav>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">
            <i class="bi bi-diagram-3-fill text-success me-2"></i>Organization Report
        </h4>
        <small class="text-muted">Farms, Warehouses &amp; Shops — Staff Assignment Coverage</small>
    </div>
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-printer me-1"></i>Print
    </button>
</div>

<!-- ══ SUMMARY CARDS ════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
    <?php
    $orgCards = [
        ['Farms',              $summary['total_farms'] ?? 0,        'success',   'bi-flower1'],
        ['Warehouses',         $summary['total_warehouses'] ?? 0,   'primary',   'bi-building'],
        ['Shops',              $summary['total_shops'] ?? 0,        'warning',   'bi-shop'],
        ['Active Assignments', $summary['total_assignments'] ?? 0,  'info',      'bi-person-badge-fill'],
        ['Unassigned Staff',   $summary['unassigned_staff'] ?? 0,   'danger',    'bi-person-x-fill'],
    ];
    foreach ($orgCards as [$lbl, $val, $color, $icon]): ?>
    <div class="col-6 col-xl">
        <div class="card border-0 shadow-sm h-100" style="border-top:3px solid var(--bs-<?= $color ?>)!important;">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-muted small"><?= $lbl ?></span>
                    <i class="bi <?= $icon ?> text-<?= $color ?>"></i>
                </div>
                <div class="fw-bold fs-3 lh-1 <?= ($lbl === 'Unassigned Staff' && $val > 0) ? 'text-danger' : '' ?>">
                    <?= $fmt($val) ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ══ FARMS ════════════════════════════════════════════════════ -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header py-2">
        <span class="fw-semibold small"><i class="bi bi-flower1 me-2 text-success"></i>Farms</span>
        <span class="badge bg-success-subtle text-success ms-2"><?= count($farms) ?></span>
    </div>
    <?php if (empty($farms)): ?>
    <div class="card-body text-center text-muted small py-4">No farms found.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3 small fw-semibold">Farm</th>
                    <th class="small fw-semibold">Type</th>
                    <th class="small fw-semibold">Location</th>
                    <th class="small fw-semibold">Manager</th>
                    <th class="small fw-semibold text-end">Staff</th>
                    <th class="small fw-semibold text-end">Harvests</th>
                    <th class="small fw-semibold">Last Harvest</th>
                    <th class="small fw-semibold pe-3">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($farms as $f): ?>
                <tr>
                    <td class="ps-3 small fw-semibold">
                        <?= e($f['name']) ?>
                        <div class="text-muted" style="font-size:.68rem;"><?= e($f['farm_code'] ?? '') ?></div>
                    </td>
                    <td class="small text-muted"><?= e(ucfirst(strtolower($f['farm_type'] ?? '—'))) ?></td>
                    <td class="small text-muted"><?= e($f['location'] ?? '—') ?></td>
                    <td class="small"><?= e($f['manager_name'] ?? '—') ?></td>
                    <td class="small text-end"><?= $fmt($f['staff_count']) ?></td>
                    <td class="small text-end"><?= $fmt($f['total_harvests']) ?></td>
                    <td class="small text-muted">
                        <?= !empty($f['last_harvest_date']) ? date('d M Y', strtotime($f['last_harvest_date'])) : '—' ?>
                    </td>
                    <td class="pe-3">
                        <span class="badge <?= $f['is_active'] ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?> small">
                            <?= $f['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ══ WAREHOUSES + SHOPS (side by side) ════════════════════════ -->
<div class="row g-3 mb-4">

    <!-- Warehouses -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header py-2">
                <span class="fw-semibold small"><i class="bi bi-building me-2 text-primary"></i>Warehouses</span>
                <span class="badge bg-primary-subtle text-primary ms-2"><?= count($warehouses) ?></span>
            </div>
            <?php if (empty($warehouses)): ?>
            <div class="card-body text-center text-muted small py-4">No warehouses found.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3 small fw-semibold">Warehouse</th>
                            <th class="small fw-semibold">Manager</th>
                            <th class="small fw-semibold text-end">Staff</th>
                            <th class="small fw-semibold text-end pe-3">Received</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($warehouses as $w): ?>
                        <tr>
                            <td class="ps-3 small fw-semibold">
                                <?= e($w['name']) ?>
                                <div class="text-muted" style="font-size:.68rem;"><?= e($w['location'] ?? '') ?></div>
                            </td>
                            <td class="small text-muted"><?= e($w['manager_name'] ?? '—') ?></td>
                            <td class="small text-end"><?= $fmt($w['staff_count']) ?></td>
                            <td class="small text-end pe-3"><?= $fmt($w['total_received'] ?? 0) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Shops -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header py-2">
                <span class="fw-semibold small"><i class="bi bi-shop me-2 text-warning"></i>Shops</span>
                <span class="badge bg-warning-subtle text-warning ms-2"><?= count($shops) ?></span>
            </div>
            <?php if (empty($shops)): ?>
            <div class="card-body text-center text-muted small py-4">No shops found.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3 small fw-semibold">Shop</th>
                            <th class="small fw-semibold">Manager</th>
                            <th class="small fw-semibold text-end pe-3">Staff</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shops as $s): ?>
                        <tr>
                            <td class="ps-3 small fw-semibold">
                                <?= e($s['name']) ?>
                                <div class="text-muted" style="font-size:.68rem;"><?= e($s['location'] ?? '') ?></div>
                            </td>
                            <td class="small text-muted"><?= e($s['manager_name'] ?? '—') ?></td>
                            <td class="small text-end pe-3"><?= $fmt($s['staff_count']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ══ STAFF ASSIGNMENTS ════════════════════════════════════════ -->
<div class="card border-0 shadow-sm">
    <div class="card-header py-2">
        <span class="fw-semibold small"><i class="bi bi-person-badge-fill me-2 text-info"></i>Active Staff Assignments</span>
        <span class="badge bg-info-subtle text-info ms-2"><?= count($assignments) ?></span>
    </div>
    <?php if (empty($assignments)): ?>
    <div class="card-body text-center text-muted small py-4">No active assignments found.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3 small fw-semibold">Staff Member</th>
                    <th class="small fw-semibold">System Role</th>
                    <th class="small fw-semibold">Location Type</th>
                    <th class="small fw-semibold">Assigned To</th>
                    <th class="small fw-semibold">Assignment Role</th>
                    <th class="small fw-semibold">Assigned By</th>
                    <th class="small fw-semibold pe-3">Since</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assignments as $a):
                    [$locIcon, $locColor] = $locMeta[$a['location_type']] ?? ['bi-geo', 'secondary'];
                ?>
                <tr>
                    <td class="ps-3 small fw-semibold">
                        <?= e($a['user_name'] ?? '—') ?>
                        <div class="text-muted" style="font-size:.68rem;"><?= e($a['username'] ?? '') ?></div>
                    </td>
                    <td class="small text-muted"><?= e($a['role_label'] ?? '—') ?></td>
                    <td class="small">
                        <span class="badge bg-<?= $locColor ?>-subtle text-<?= $locColor ?>">
                            <i class="bi <?= $locIcon ?> me-1"></i>
                            <?= ucfirst($a['location_type']) ?>
                        </span>
                    </td>
                    <td class="small fw-semibold"><?= e($a['location_name'] ?? '—') ?></td>
                    <td class="small text-muted"><?= e(str_replace('_', ' ', $a['assignment_role'])) ?></td>
                    <td class="small text-muted"><?= e($a['assigned_by_name'] ?? '—') ?></td>
                    <td class="small text-muted pe-3">
                        <?= !empty($a['assigned_at']) ? date('d M Y', strtotime($a['assigned_at'])) : '—' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
