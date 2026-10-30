<?php
/**
 * GM — All Farms
 * Centralized monitoring view for General Manager.
 * Data is sourced from the farms table via GeneralManagerService::getAllFarmsDetail().
 * No static/fake data. Reflects live DB state including edits and status changes.
 *
 * @var array $farms            Per-farm detail rows from getAllFarmsDetail()
 * @var array $farmPerformance  Aggregate KPIs from getFarmPerformance()
 */
declare(strict_types=1);

$farms           ??= [];
$farmPerformance ??= [];

$n = fn($v, int $d = 0): string =>
    is_numeric($v) ? number_format((float)$v, $d) : '—';

$kg = fn($v): string =>
    is_numeric($v) ? number_format((float)$v, 1) . ' kg' : '—';

// Separate active vs inactive for the summary counts
$activeCount   = count(array_filter($farms, fn($f) => !empty($f['is_active'])));
$inactiveCount = count($farms) - $activeCount;
?>

<!-- ── Page Header ─────────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-4"
     style="background:linear-gradient(135deg,#1a4a2e,#0d7a3e);">
    <div class="card-body py-3 text-white d-flex align-items-center gap-3 flex-wrap">
        <div class="rounded-circle bg-white bg-opacity-20 d-flex align-items-center
                    justify-content-center flex-shrink-0"
             style="width:48px;height:48px;">
            <i class="bi bi-flower1 fs-4"></i>
        </div>
        <div class="flex-grow-1">
            <h5 class="mb-0 fw-bold">All Farms</h5>
            <small class="opacity-75">Centralized farm monitoring — General Manager</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= url('gm/farms/harvests') ?>"
               class="btn btn-outline-light btn-sm">
                <i class="bi bi-basket2 me-1"></i>Harvests
            </a>
            <a href="<?= url('gm/farms/dispatches') ?>"
               class="btn btn-outline-light btn-sm">
                <i class="bi bi-truck me-1"></i>Dispatches
            </a>
        </div>
    </div>
</div>

<!-- ── KPI Strip ──────────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">

    <!-- Total Farms -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid var(--bs-success)!important;">
            <div class="card-body py-3">
                <i class="bi bi-flower1 text-success fs-3 d-block mb-1"></i>
                <div class="fw-bold fs-5"><?= $n(count($farms)) ?></div>
                <div class="text-muted small">Total Farms</div>
            </div>
        </div>
    </div>

    <!-- Active -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid var(--bs-success)!important;">
            <div class="card-body py-3">
                <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-1"></i>
                <div class="fw-bold fs-5"><?= $n($activeCount) ?></div>
                <div class="text-muted small">Active</div>
            </div>
        </div>
    </div>

    <!-- Harvests this month -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid var(--bs-success)!important;">
            <div class="card-body py-3">
                <i class="bi bi-basket2-fill text-success fs-3 d-block mb-1"></i>
                <div class="fw-bold fs-5"><?= $n($farmPerformance['harvests_this_month'] ?? 0) ?></div>
                <div class="text-muted small">Harvests / Month</div>
            </div>
        </div>
    </div>

    <!-- kg this month -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid var(--bs-info)!important;">
            <div class="card-body py-3">
                <i class="bi bi-boxes text-info fs-3 d-block mb-1"></i>
                <div class="fw-bold fs-5">
                    <?= $n($farmPerformance['kg_this_month'] ?? 0, 0) ?>
                    <small class="fw-normal text-muted" style="font-size:.65rem;">kg</small>
                </div>
                <div class="text-muted small">Kg This Month</div>
            </div>
        </div>
    </div>

    <!-- Pending Dispatch -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid var(--bs-warning)!important;">
            <div class="card-body py-3">
                <i class="bi bi-truck text-warning fs-3 d-block mb-1"></i>
                <div class="fw-bold fs-5"><?= $n($farmPerformance['pending_dispatch'] ?? 0) ?></div>
                <div class="text-muted small">Pending Dispatch</div>
            </div>
        </div>
    </div>

    <!-- Dispatches this month -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid var(--bs-primary)!important;">
            <div class="card-body py-3">
                <i class="bi bi-send-fill text-primary fs-3 d-block mb-1"></i>
                <div class="fw-bold fs-5"><?= $n($farmPerformance['dispatches_this_month'] ?? 0) ?></div>
                <div class="text-muted small">Dispatches / Month</div>
            </div>
        </div>
    </div>

</div>

<!-- ── Farm Table ─────────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex align-items-center justify-content-between py-2">
        <div class="fw-semibold small">
            <i class="bi bi-flower1 me-2 text-success"></i>All Farms
            <span class="badge bg-success ms-2"><?= $activeCount ?> active</span>
            <?php if ($inactiveCount > 0): ?>
            <span class="badge bg-secondary ms-1"><?= $inactiveCount ?> inactive</span>
            <?php endif; ?>
        </div>
        <!-- Filter toggle (purely visual state — no server round-trip) -->
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnShowAll"
                    onclick="filterFarmTable('all')">
                All
            </button>
            <button type="button" class="btn btn-outline-success btn-sm" id="btnShowActive"
                    onclick="filterFarmTable('active')">
                Active only
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnShowInactive"
                    onclick="filterFarmTable('inactive')">
                Inactive
            </button>
        </div>
    </div>

    <div class="card-body p-0">
        <?php if (empty($farms)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-flower1 d-block fs-2 mb-2 opacity-25"></i>
            <p class="mb-1 fw-semibold">No farms found</p>
            <small>Farms created through Organization → Farms will appear here automatically.</small>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle" id="farmsTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="min-width:100px;">Code</th>
                        <th style="min-width:160px;">Farm Name</th>
                        <th style="min-width:110px;">Type</th>
                        <th style="min-width:140px;">Location</th>
                        <th style="min-width:150px;">Assigned Manager</th>
                        <th class="text-center" style="min-width:80px;">Status</th>
                        <th class="text-end" style="min-width:90px;">Available (kg)</th>
                        <th class="text-end" style="min-width:100px;">Pending Dispatch</th>
                        <th class="text-end" style="min-width:100px;">Harvests (30d)</th>
                        <th class="text-end pe-3" style="min-width:110px;">kg This Month</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($farms as $f):
                        $isActive  = !empty($f['is_active']);
                        $rowClass  = $isActive ? '' : 'table-secondary text-muted';
                        $hasPending = (int)($f['pending_dispatches'] ?? 0) > 0;
                        $farmName  = $f['farm_name'] ?? $f['name'] ?? '—';
                        $farmCode  = $f['farm_code'] ?? ('FARM-' . str_pad((string)$f['id'], 3, '0', STR_PAD_LEFT));
                        $mgr       = trim((string)($f['manager_name'] ?? ''));
                        $mgr       = ($mgr === '' || $mgr === ' ') ? null : $mgr;
                    ?>
                    <tr class="farm-row <?= $rowClass ?>"
                        data-status="<?= $isActive ? 'active' : 'inactive' ?>">

                        <!-- Farm Code -->
                        <td class="ps-3">
                            <span class="badge bg-success bg-opacity-10 text-success
                                         border border-success border-opacity-25
                                         font-monospace small">
                                <?= e($farmCode) ?>
                            </span>
                        </td>

                        <!-- Farm Name -->
                        <td>
                            <div class="fw-semibold small"><?= e($farmName) ?></div>
                            <?php if (!empty($f['created_at'])): ?>
                            <div class="text-muted" style="font-size:.68rem;">
                                Est. <?= date('M Y', strtotime($f['created_at'])) ?>
                            </div>
                            <?php endif; ?>
                        </td>

                        <!-- Type -->
                        <td class="small">
                            <?php
                            $typeMap = [
                                'CROP'         => ['CROP',         'success',  'bi-flower2'],
                                'LIVESTOCK'    => ['LIVESTOCK',    'warning',  'bi-piggy-bank'],
                                'MIXED'        => ['MIXED',        'info',     'bi-layers'],
                                'DAIRY'        => ['DAIRY',        'primary',  'bi-droplet-fill'],
                                'POULTRY'      => ['POULTRY',      'warning',  'bi-egg-fried'],
                                'HORTICULTURE' => ['HORTICULTURE', 'success',  'bi-tree'],
                                'OTHER'        => ['OTHER',        'secondary','bi-three-dots'],
                            ];
                            $typeKey = strtoupper(trim((string)($f['farm_type'] ?? '')));
                            [$typeLabel, $typeColor, $typeIco] = $typeMap[$typeKey] ?? [$typeKey ?: '—', 'secondary', 'bi-flower1'];
                            ?>
                            <?php if ($typeLabel !== '—'): ?>
                            <span class="badge bg-<?= $typeColor ?> bg-opacity-15
                                         text-<?= $typeColor ?> border border-<?= $typeColor ?>
                                         border-opacity-25">
                                <i class="bi <?= $typeIco ?> me-1"></i><?= e($typeLabel) ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Location -->
                        <td class="small text-muted">
                            <?php if (!empty($f['location'])): ?>
                            <i class="bi bi-geo-alt me-1 text-success opacity-75"></i><?= e($f['location']) ?>
                            <?php else: ?>
                            <span class="text-muted fst-italic">Not specified</span>
                            <?php endif; ?>
                        </td>

                        <!-- Assigned Manager -->
                        <td class="small">
                            <?php if ($mgr): ?>
                            <div class="d-flex align-items-center gap-1">
                                <span class="rounded-circle bg-success bg-opacity-15 d-inline-flex
                                             align-items-center justify-content-center flex-shrink-0"
                                      style="width:22px;height:22px;">
                                    <i class="bi bi-person-fill text-success" style="font-size:.6rem;"></i>
                                </span>
                                <div>
                                    <div class="fw-semibold lh-sm"><?= e($mgr) ?></div>
                                    <?php if (!empty($f['manager_username'])): ?>
                                    <div class="text-muted" style="font-size:.65rem;">
                                        @<?= e($f['manager_username']) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php else: ?>
                            <span class="text-danger fst-italic">
                                <i class="bi bi-exclamation-triangle me-1"></i>Unassigned
                            </span>
                            <?php endif; ?>
                        </td>

                        <!-- Status -->
                        <td class="text-center">
                            <?php if ($isActive): ?>
                            <span class="badge bg-success rounded-pill">
                                <i class="bi bi-check-circle me-1"></i>Active
                            </span>
                            <?php else: ?>
                            <span class="badge bg-secondary rounded-pill">
                                <i class="bi bi-pause-circle me-1"></i>Inactive
                            </span>
                            <?php endif; ?>
                        </td>

                        <!-- Available Quantity -->
                        <td class="text-end small fw-semibold
                                   <?= (float)($f['available_qty'] ?? 0) > 0 ? 'text-success' : 'text-muted' ?>">
                            <?= $kg($f['available_qty'] ?? 0) ?>
                        </td>

                        <!-- Pending Dispatches -->
                        <td class="text-end small">
                            <?php if ($hasPending): ?>
                            <span class="badge bg-warning text-dark rounded-pill">
                                <?= (int)($f['pending_dispatches'] ?? 0) ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>

                        <!-- Harvests last 30 days -->
                        <td class="text-end small">
                            <?php $rh = (int)($f['recent_harvests'] ?? 0); ?>
                            <?php if ($rh > 0): ?>
                            <span class="fw-semibold text-success"><?= $n($rh) ?></span>
                            <?php else: ?>
                            <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>

                        <!-- kg this month -->
                        <td class="text-end small pe-3">
                            <?php $kgM = (float)($f['kg_this_month'] ?? 0); ?>
                            <?php if ($kgM > 0): ?>
                            <span class="fw-semibold"><?= $kg($kgM) ?></span>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>

                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div><!-- /.card-body -->

    <?php if (!empty($farms)): ?>
    <div class="card-footer bg-white border-top py-2 px-3">
        <small class="text-muted">
            <i class="bi bi-info-circle me-1"></i>
            Showing all <?= count($farms) ?> farm<?= count($farms) !== 1 ? 's' : '' ?>
            registered in the system. Data reflects live database state.
            New farms created from
            <a href="<?= url('admin/organization/farms') ?>" class="text-decoration-none">
                Organization → Farms
            </a>
            appear here automatically.
        </small>
    </div>
    <?php endif; ?>
</div><!-- /.card -->

<script>
function filterFarmTable(filter) {
    document.querySelectorAll('.farm-row').forEach(function(row) {
        if (filter === 'all') {
            row.style.display = '';
        } else {
            row.style.display = (row.dataset.status === filter) ? '' : 'none';
        }
    });
    // Update button states
    ['All','Active','Inactive'].forEach(function(label) {
        var btn = document.getElementById('btnShow' + label);
        if (btn) btn.classList.remove('active');
    });
    var activeBtn = {all: 'btnShowAll', active: 'btnShowActive', inactive: 'btnShowInactive'}[filter];
    if (activeBtn) document.getElementById(activeBtn)?.classList.add('active');
}
// Default to showing all
filterFarmTable('all');
</script>
