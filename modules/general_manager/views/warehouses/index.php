<?php
/**
 * GM — All Warehouses
 * Centralized monitoring view for General Manager.
 * Data is sourced from the warehouses table via GeneralManagerService::getAllWarehousesDetail().
 * No static/fake data. Reflects live DB state including edits and status changes.
 *
 * @var array $warehouses     Per-warehouse detail rows from getAllWarehousesDetail()
 * @var array $warehousePerf  Aggregate KPIs from getWarehousePerformance()
 * @var array $lowStock       Low-stock items from getLowStockItems()
 */
declare(strict_types=1);

$warehouses    ??= [];
$warehousePerf ??= [];
$lowStock      ??= [];

$n = fn($v, int $d = 0): string =>
    is_numeric($v) ? number_format((float)$v, $d) : '—';

$activeCount   = count(array_filter($warehouses, fn($w) => !empty($w['is_active'])));
$inactiveCount = count($warehouses) - $activeCount;
?>

<!-- ── Page Header ─────────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-4"
     style="background:linear-gradient(135deg,#0d2e4a,#0d5c8a);">
    <div class="card-body py-3 text-white d-flex align-items-center gap-3 flex-wrap">
        <div class="rounded-circle bg-white bg-opacity-20 d-flex align-items-center
                    justify-content-center flex-shrink-0"
             style="width:48px;height:48px;">
            <i class="bi bi-building fs-4"></i>
        </div>
        <div class="flex-grow-1">
            <h5 class="mb-0 fw-bold">All Warehouses</h5>
            <small class="opacity-75">Centralized warehouse monitoring — General Manager</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= url('gm/warehouses/receiving') ?>"
               class="btn btn-outline-light btn-sm">
                <i class="bi bi-inbox me-1"></i>Receiving
            </a>
            <a href="<?= url('gm/warehouses/discrepancies') ?>"
               class="btn btn-outline-light btn-sm">
                <i class="bi bi-clipboard-x me-1"></i>Discrepancies
            </a>
            <a href="<?= url('warehouse/movements') ?>"
               class="btn btn-outline-light btn-sm">
                <i class="bi bi-clock-history me-1"></i>Stock Movements
            </a>
        </div>
    </div>
</div>

<!-- ── KPI Strip ──────────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">

    <!-- Total Warehouses -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid var(--bs-primary)!important;">
            <div class="card-body py-3">
                <i class="bi bi-building text-primary fs-3 d-block mb-1"></i>
                <div class="fw-bold fs-5"><?= $n(count($warehouses)) ?></div>
                <div class="text-muted small">Total Warehouses</div>
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

    <!-- Total Stock -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid var(--bs-primary)!important;">
            <div class="card-body py-3">
                <i class="bi bi-boxes text-primary fs-3 d-block mb-1"></i>
                <div class="fw-bold fs-5"><?= $n($warehousePerf['total_stock'] ?? 0, 0) ?></div>
                <div class="text-muted small">Total Stock Units</div>
            </div>
        </div>
    </div>

    <!-- Pending Receipts -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid var(--bs-warning)!important;">
            <div class="card-body py-3">
                <i class="bi bi-inbox-fill text-warning fs-3 d-block mb-1"></i>
                <div class="fw-bold fs-5"><?= $n($warehousePerf['pending_receipts'] ?? 0) ?></div>
                <div class="text-muted small">Pending Receipts</div>
            </div>
        </div>
    </div>

    <!-- Open Discrepancies -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid var(--bs-danger)!important;">
            <div class="card-body py-3">
                <i class="bi bi-clipboard-x text-danger fs-3 d-block mb-1"></i>
                <div class="fw-bold fs-5"><?= $n($warehousePerf['open_discrepancies'] ?? 0) ?></div>
                <div class="text-muted small">Open Discrepancies</div>
            </div>
        </div>
    </div>

    <!-- Low Stock Lines -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid var(--bs-warning)!important;">
            <div class="card-body py-3">
                <i class="bi bi-exclamation-triangle text-warning fs-3 d-block mb-1"></i>
                <div class="fw-bold fs-5"><?= $n($warehousePerf['low_stock_lines'] ?? 0) ?></div>
                <div class="text-muted small">Low Stock Lines</div>
            </div>
        </div>
    </div>

</div>

<!-- ── Main Content ───────────────────────────────────────────────────────── -->
<div class="row g-3">

    <!-- Warehouses Table (full width; low-stock panel below on mobile, beside on desktop) -->
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex align-items-center
                         justify-content-between py-2">
                <div class="fw-semibold small">
                    <i class="bi bi-building me-2 text-primary"></i>All Warehouses
                    <span class="badge bg-primary ms-2"><?= $activeCount ?> active</span>
                    <?php if ($inactiveCount > 0): ?>
                    <span class="badge bg-secondary ms-1"><?= $inactiveCount ?> inactive</span>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            onclick="filterWhTable('all')">All</button>
                    <button type="button" class="btn btn-outline-primary btn-sm"
                            onclick="filterWhTable('active')">Active only</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            onclick="filterWhTable('inactive')">Inactive</button>
                </div>
            </div>

            <div class="card-body p-0">
                <?php if (empty($warehouses)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-building d-block fs-2 mb-2 opacity-25"></i>
                    <p class="mb-1 fw-semibold">No warehouses found</p>
                    <small>Warehouses created through Organization → Warehouses
                           will appear here automatically.</small>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle"
                           id="warehousesTable">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="min-width:95px;">Code</th>
                                <th style="min-width:160px;">Warehouse Name</th>
                                <th style="min-width:110px;">Type</th>
                                <th style="min-width:140px;">Location</th>
                                <th style="min-width:155px;">Assigned Manager</th>
                                <th class="text-center" style="min-width:80px;">Status</th>
                                <th class="text-end" style="min-width:90px;">Stock</th>
                                <th class="text-end" style="min-width:90px;">Products</th>
                                <th class="text-end" style="min-width:100px;">Pending Recv.</th>
                                <th class="text-end" style="min-width:90px;">In Transit</th>
                                <th class="text-end pe-3" style="min-width:105px;">Discrepancies</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($warehouses as $w):
                                $isActive   = !empty($w['is_active']);
                                $rowClass   = $isActive ? '' : 'table-secondary text-muted';
                                $whName     = $w['warehouse_name'] ?? $w['name'] ?? '—';
                                $whCode     = $w['warehouse_code'] ?? ('WH-' . str_pad((string)$w['id'], 3, '0', STR_PAD_LEFT));
                                $mgr        = trim((string)($w['manager_name'] ?? ''));
                                $mgr        = ($mgr === '' || $mgr === ' ') ? null : $mgr;
                                $pendRecv   = (int)($w['pending_receiving'] ?? 0);
                                $inTransit  = (int)($w['incoming_transfers'] ?? 0);
                                $discrepancy= (int)($w['open_discrepancies'] ?? 0);
                                $totalStock = (float)($w['total_stock'] ?? 0);
                            ?>
                            <tr class="wh-row <?= $rowClass ?>"
                                data-status="<?= $isActive ? 'active' : 'inactive' ?>">

                                <!-- Warehouse Code -->
                                <td class="ps-3">
                                    <span class="badge bg-primary bg-opacity-10 text-primary
                                                 border border-primary border-opacity-25
                                                 font-monospace small">
                                        <?= e($whCode) ?>
                                    </span>
                                </td>

                                <!-- Warehouse Name -->
                                <td>
                                    <div class="fw-semibold small"><?= e($whName) ?></div>
                                    <?php if (!empty($w['created_at'])): ?>
                                    <div class="text-muted" style="font-size:.68rem;">
                                        Est. <?= date('M Y', strtotime($w['created_at'])) ?>
                                    </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Type -->
                                <td class="small">
                                    <?php
                                    $whTypeMap = [
                                        'CENTRAL'      => ['Central',      'primary',   'bi-building-fill'],
                                        'COLD_STORAGE' => ['Cold Storage', 'info',      'bi-thermometer-low'],
                                        'DRY_STORAGE'  => ['Dry Storage',  'warning',   'bi-archive-fill'],
                                        'DISTRIBUTION' => ['Distribution', 'success',   'bi-diagram-3-fill'],
                                        'OTHER'        => ['Other',        'secondary', 'bi-three-dots'],
                                    ];
                                    $whTypeKey = strtoupper(trim((string)($w['warehouse_type'] ?? '')));
                                    [$whTypeLabel, $whTypeColor, $whTypeIco] =
                                        $whTypeMap[$whTypeKey] ?? [$whTypeKey ?: '—', 'secondary', 'bi-building'];
                                    ?>
                                    <?php if ($whTypeLabel && $whTypeLabel !== '—'): ?>
                                    <span class="badge bg-<?= $whTypeColor ?> bg-opacity-15
                                                 text-<?= $whTypeColor ?> border
                                                 border-<?= $whTypeColor ?> border-opacity-25">
                                        <i class="bi <?= $whTypeIco ?> me-1"></i><?= e($whTypeLabel) ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Location -->
                                <td class="small text-muted">
                                    <?php if (!empty($w['location'])): ?>
                                    <i class="bi bi-geo-alt me-1 text-primary opacity-75"></i>
                                    <?= e($w['location']) ?>
                                    <?php else: ?>
                                    <span class="fst-italic">Not specified</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Assigned Manager -->
                                <td class="small">
                                    <?php if ($mgr): ?>
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="rounded-circle bg-primary bg-opacity-15
                                                     d-inline-flex align-items-center
                                                     justify-content-center flex-shrink-0"
                                              style="width:22px;height:22px;">
                                            <i class="bi bi-person-fill text-primary"
                                               style="font-size:.6rem;"></i>
                                        </span>
                                        <div>
                                            <div class="fw-semibold lh-sm"><?= e($mgr) ?></div>
                                            <?php if (!empty($w['manager_username'])): ?>
                                            <div class="text-muted" style="font-size:.65rem;">
                                                @<?= e($w['manager_username']) ?>
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

                                <!-- Current Stock -->
                                <td class="text-end small fw-semibold
                                           <?= $totalStock > 0 ? 'text-dark' : 'text-muted' ?>">
                                    <?= $n($totalStock, 1) ?>
                                </td>

                                <!-- Product Lines -->
                                <td class="text-end small text-muted">
                                    <?= $n($w['product_lines'] ?? 0) ?>
                                </td>

                                <!-- Pending Receiving -->
                                <td class="text-end small">
                                    <?php if ($pendRecv > 0): ?>
                                    <a href="<?= url('gm/warehouses/receiving') ?>"
                                       class="badge bg-warning text-dark rounded-pill
                                              text-decoration-none">
                                        <?= $pendRecv ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Incoming Transfers (in transit) -->
                                <td class="text-end small">
                                    <?php if ($inTransit > 0): ?>
                                    <span class="badge bg-info text-dark rounded-pill">
                                        <?= $inTransit ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Open Discrepancies -->
                                <td class="text-end small pe-3">
                                    <?php if ($discrepancy > 0): ?>
                                    <a href="<?= url('gm/warehouses/discrepancies') ?>"
                                       class="badge bg-danger rounded-pill text-decoration-none">
                                        <?= $discrepancy ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>

                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div><!-- /.card-body -->

            <?php if (!empty($warehouses)): ?>
            <div class="card-footer bg-white border-top py-2 px-3">
                <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Showing all <?= count($warehouses) ?> warehouse<?= count($warehouses) !== 1 ? 's' : '' ?>
                    registered in the system. Data reflects live database state.
                    New warehouses created from
                    <a href="<?= url('admin/organization/warehouses') ?>"
                       class="text-decoration-none">
                        Organization → Warehouses
                    </a>
                    appear here automatically.
                </small>
            </div>
            <?php endif; ?>
        </div><!-- /.card -->
    </div><!-- /.col -->

    <!-- ── Low Stock Panel ──────────────────────────────────────────────── -->
    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-2">
                <span class="fw-semibold small">
                    <i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i>
                    Low Stock Items
                </span>
                <?php if (!empty($lowStock)): ?>
                <span class="badge bg-warning text-dark ms-2"><?= count($lowStock) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body p-0" style="max-height:420px;overflow-y:auto;">
                <?php if (empty($lowStock)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-check-circle d-block fs-2 mb-2 text-success opacity-50"></i>
                    <small class="fw-semibold">No low stock items</small><br>
                    <small>All warehouse inventory is above minimum levels.</small>
                </div>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($lowStock as $ls):
                        $qty    = (float)($ls['current_quantity'] ?? 0);
                        $min    = (float)($ls['min_stock_level'] ?? 0);
                        $pct    = $min > 0 ? min(100, round($qty / $min * 100)) : 0;
                        $danger = $qty <= 0;
                    ?>
                    <li class="list-group-item px-3 py-2 border-0 border-bottom">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <div class="flex-grow-1 me-2">
                                <div class="small fw-semibold lh-sm">
                                    <?= e($ls['product_name'] ?? '—') ?>
                                </div>
                                <div class="text-muted" style="font-size:.68rem;">
                                    <i class="bi bi-building me-1"></i>
                                    <?= e($ls['warehouse_name'] ?? '—') ?>
                                    <?php if (!empty($ls['sku'])): ?>
                                    <span class="ms-1 opacity-75"><?= e($ls['sku']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <div class="small fw-bold
                                            <?= $danger ? 'text-danger' : 'text-warning' ?>">
                                    <?= number_format($qty, 1) ?>
                                    <?php if (!empty($ls['unit_abbr'])): ?>
                                    <small class="fw-normal text-muted"><?= e($ls['unit_abbr']) ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted" style="font-size:.65rem;">
                                    min: <?= number_format($min) ?>
                                </div>
                            </div>
                        </div>
                        <!-- Stock level bar -->
                        <div class="progress" style="height:3px;">
                            <div class="progress-bar bg-<?= $danger ? 'danger' : 'warning' ?>"
                                 role="progressbar"
                                 style="width:<?= $pct ?>%"
                                 aria-valuenow="<?= $pct ?>"
                                 aria-valuemin="0"
                                 aria-valuemax="100">
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            <?php if (!empty($lowStock)): ?>
            <div class="card-footer bg-white border-top py-2 px-3 text-center">
                <a href="<?= url('gm/inventory') ?>" class="btn btn-outline-warning btn-sm">
                    <i class="bi bi-eye me-1"></i>View Full Inventory
                </a>
            </div>
            <?php endif; ?>
        </div><!-- /.card -->
    </div><!-- /.col -->

</div><!-- /.row -->

<script>
function filterWhTable(filter) {
    document.querySelectorAll('.wh-row').forEach(function(row) {
        row.style.display =
            (filter === 'all' || row.dataset.status === filter) ? '' : 'none';
    });
}
</script>
