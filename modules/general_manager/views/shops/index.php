<?php
/**
 * GM — All Shops
 * Centralized monitoring view for General Manager.
 * Data is sourced from the shops table via GeneralManagerService::getAllShopsDetail().
 * No static/fake data. Reflects live DB state including edits and status changes.
 *
 * @var array $shops            Per-shop detail rows from getAllShopsDetail()
 * @var array $shopPerformance  Aggregate KPIs from getShopPerformance()
 * @var array $topProducts      Top-selling products from getTopProducts()
 */
declare(strict_types=1);

$shops          ??= [];
$shopPerformance??= [];
$topProducts    ??= [];

$n = fn($v, int $d = 0): string =>
    is_numeric($v) ? number_format((float)$v, $d) : '—';

$etb = fn($v): string =>
    'ETB ' . number_format((float)$v, 2);

$activeCount   = count(array_filter($shops, fn($s) => !empty($s['is_active'])));
$inactiveCount = count($shops) - $activeCount;
?>

<!-- ── Page Header ─────────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-4"
     style="background:linear-gradient(135deg,#4a2000,#8a5500);">
    <div class="card-body py-3 text-white d-flex align-items-center gap-3 flex-wrap">
        <div class="rounded-circle bg-white bg-opacity-20 d-flex align-items-center
                    justify-content-center flex-shrink-0"
             style="width:48px;height:48px;">
            <i class="bi bi-shop fs-4"></i>
        </div>
        <div class="flex-grow-1">
            <h5 class="mb-0 fw-bold">All Shops</h5>
            <small class="opacity-75">Centralized shop monitoring — General Manager</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= url('gm/shops/sales') ?>"
               class="btn btn-outline-light btn-sm">
                <i class="bi bi-bar-chart me-1"></i>Sales Overview
            </a>
        </div>
    </div>
</div>

<!-- ── KPI Strip ──────────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">

    <!-- Total Shops -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid var(--bs-warning)!important;">
            <div class="card-body py-3">
                <i class="bi bi-shop text-warning fs-3 d-block mb-1"></i>
                <div class="fw-bold fs-5"><?= $n(count($shops)) ?></div>
                <div class="text-muted small">Total Shops</div>
            </div>
        </div>
    </div>

    <!-- Sales Today -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid var(--bs-success)!important;">
            <div class="card-body py-3">
                <i class="bi bi-cart-check-fill text-success fs-3 d-block mb-1"></i>
                <div class="fw-bold fs-5"><?= $n($shopPerformance['sales_today'] ?? 0) ?></div>
                <div class="text-muted small">Sales Today</div>
            </div>
        </div>
    </div>

    <!-- Revenue Today -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid var(--bs-success)!important;">
            <div class="card-body py-3">
                <i class="bi bi-currency-dollar text-success fs-3 d-block mb-1"></i>
                <div class="fw-bold fs-5" style="font-size:.95rem!important;">
                    <?= $etb($shopPerformance['revenue_today'] ?? 0) ?>
                </div>
                <div class="text-muted small">Revenue Today</div>
            </div>
        </div>
    </div>

    <!-- Sales This Month -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid var(--bs-primary)!important;">
            <div class="card-body py-3">
                <i class="bi bi-calendar-month text-primary fs-3 d-block mb-1"></i>
                <div class="fw-bold fs-5"><?= $n($shopPerformance['sales_this_month'] ?? 0) ?></div>
                <div class="text-muted small">Sales This Month</div>
            </div>
        </div>
    </div>

    <!-- Low Stock -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid var(--bs-danger)!important;">
            <div class="card-body py-3">
                <i class="bi bi-exclamation-triangle text-danger fs-3 d-block mb-1"></i>
                <div class="fw-bold fs-5"><?= $n($shopPerformance['shop_low_stock'] ?? 0) ?></div>
                <div class="text-muted small">Shop Low Stock</div>
            </div>
        </div>
    </div>

    <!-- Incoming Transfers -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid var(--bs-info)!important;">
            <div class="card-body py-3">
                <i class="bi bi-truck text-info fs-3 d-block mb-1"></i>
                <div class="fw-bold fs-5"><?= $n($shopPerformance['transfers_incoming'] ?? 0) ?></div>
                <div class="text-muted small">Incoming Transfers</div>
            </div>
        </div>
    </div>

</div>

<!-- ── Main Content ───────────────────────────────────────────────────────── -->
<div class="row g-3">

    <!-- Shops Table -->
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex align-items-center
                         justify-content-between py-2">
                <div class="fw-semibold small">
                    <i class="bi bi-shop me-2 text-warning"></i>All Shops
                    <span class="badge bg-warning text-dark ms-2"><?= $activeCount ?> active</span>
                    <?php if ($inactiveCount > 0): ?>
                    <span class="badge bg-secondary ms-1"><?= $inactiveCount ?> inactive</span>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            onclick="filterShopTable('all')">All</button>
                    <button type="button" class="btn btn-outline-warning btn-sm"
                            onclick="filterShopTable('active')">Active only</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            onclick="filterShopTable('inactive')">Inactive</button>
                </div>
            </div>

            <div class="card-body p-0">
                <?php if (empty($shops)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-shop d-block fs-2 mb-2 opacity-25"></i>
                    <p class="mb-1 fw-semibold">No shops found</p>
                    <small>Shops created through Organization → Shops
                           will appear here automatically.</small>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle"
                           id="shopsTable">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="min-width:95px;">Code</th>
                                <th style="min-width:150px;">Shop Name</th>
                                <th style="min-width:100px;">Type</th>
                                <th style="min-width:140px;">Location</th>
                                <th style="min-width:155px;">Assigned Salesperson</th>
                                <th class="text-center" style="min-width:80px;">Status</th>
                                <th class="text-end" style="min-width:80px;">Stock</th>
                                <th class="text-end" style="min-width:80px;">Low Stock</th>
                                <th class="text-end" style="min-width:90px;">Sales Today</th>
                                <th class="text-end" style="min-width:110px;">Revenue Today</th>
                                <th class="text-end" style="min-width:95px;">Sales / Month</th>
                                <th class="text-end pe-3" style="min-width:90px;">In Transit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shops as $s):
                                $isActive    = !empty($s['is_active']);
                                $rowClass    = $isActive ? '' : 'table-secondary text-muted';
                                $shopName    = $s['shop_name'] ?? $s['name'] ?? '—';
                                $shopCode    = $s['shop_code'] ?? ('SHOP-' . str_pad((string)$s['id'], 3, '0', STR_PAD_LEFT));
                                $mgr         = trim((string)($s['manager_name'] ?? ''));
                                $mgr         = ($mgr === '' || $mgr === ' ') ? null : $mgr;
                                $lowStockCnt = (int)($s['low_stock_lines'] ?? 0);
                                $inTransit   = (int)($s['incoming_transfers'] ?? 0);
                                $salesToday  = (int)($s['sales_today'] ?? 0);
                                $revToday    = (float)($s['revenue_today'] ?? 0);
                                $salesMonth  = (int)($s['sales_this_month'] ?? 0);
                                $stock       = (float)($s['available_stock'] ?? $s['total_stock'] ?? 0);
                            ?>
                            <tr class="shop-row <?= $rowClass ?>"
                                data-status="<?= $isActive ? 'active' : 'inactive' ?>">

                                <!-- Shop Code -->
                                <td class="ps-3">
                                    <span class="badge bg-warning bg-opacity-15 text-warning
                                                 border border-warning border-opacity-25
                                                 font-monospace small"
                                          style="color:#7a5700!important;">
                                        <?= e($shopCode) ?>
                                    </span>
                                </td>

                                <!-- Shop Name -->
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if (!empty($s['shop_image'])): ?>
                                        <img src="<?= e(upload_url('shops/' . $s['shop_image'])) ?>"
                                             alt="<?= e($shopName) ?>"
                                             style="width:32px;height:32px;object-fit:cover;border-radius:6px;flex-shrink:0;"
                                             onerror="this.style.display='none'">
                                        <?php else: ?>
                                        <div style="width:32px;height:32px;border-radius:6px;background:rgba(253,126,20,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                            <i class="bi bi-shop" style="color:#fd7e14;font-size:14px;"></i>
                                        </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-semibold small"><?= e($shopName) ?></div>
                                            <?php if (!empty($s['phone'])): ?>
                                            <div class="text-muted" style="font-size:.68rem;">
                                                <i class="bi bi-telephone me-1"></i><?= e($s['phone']) ?>
                                            </div>
                                            <?php elseif (!empty($s['created_at'])): ?>
                                            <div class="text-muted" style="font-size:.68rem;">
                                                Est. <?= date('M Y', strtotime($s['created_at'])) ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <!-- Type -->
                                <td class="small">
                                    <?php
                                    $shopTypeMap = [
                                        'RETAIL'    => ['Retail',    'warning',   'bi-shop'],
                                        'CAFETERIA' => ['Cafeteria', 'success',   'bi-cup-hot-fill'],
                                        'KIOSK'     => ['Kiosk',     'info',      'bi-shop-window'],
                                        'OUTLET'    => ['Outlet',    'primary',   'bi-bag-fill'],
                                        'OTHER'     => ['Other',     'secondary', 'bi-three-dots'],
                                    ];
                                    $shopTypeKey = strtoupper(trim((string)($s['shop_type'] ?? '')));
                                    [$stLabel, $stColor, $stIco] =
                                        $shopTypeMap[$shopTypeKey] ?? [$shopTypeKey ?: '—', 'secondary', 'bi-shop'];
                                    ?>
                                    <?php if ($stLabel && $stLabel !== '—'): ?>
                                    <span class="badge bg-<?= $stColor ?> bg-opacity-15
                                                 text-<?= $stColor ?> border
                                                 border-<?= $stColor ?> border-opacity-25"
                                          <?= $stColor === 'warning' ? 'style="color:#7a5700!important;"' : '' ?>>
                                        <i class="bi <?= $stIco ?> me-1"></i><?= e($stLabel) ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Location -->
                                <td class="small text-muted">
                                    <?php if (!empty($s['location'])): ?>
                                    <i class="bi bi-geo-alt me-1 text-warning opacity-75"></i>
                                    <?= e($s['location']) ?>
                                    <?php else: ?>
                                    <span class="fst-italic">Not specified</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Assigned Salesperson -->
                                <td class="small">
                                    <?php if ($mgr): ?>
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="rounded-circle bg-warning bg-opacity-15
                                                     d-inline-flex align-items-center
                                                     justify-content-center flex-shrink-0"
                                              style="width:22px;height:22px;">
                                            <i class="bi bi-person-fill text-warning"
                                               style="font-size:.6rem;color:#7a5700!important;"></i>
                                        </span>
                                        <div>
                                            <div class="fw-semibold lh-sm"><?= e($mgr) ?></div>
                                            <?php if (!empty($s['manager_username'])): ?>
                                            <div class="text-muted" style="font-size:.65rem;">
                                                @<?= e($s['manager_username']) ?>
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
                                           <?= $stock > 0 ? 'text-dark' : 'text-muted' ?>">
                                    <?= $n($stock, 1) ?>
                                </td>

                                <!-- Low Stock Lines -->
                                <td class="text-end small">
                                    <?php if ($lowStockCnt > 0): ?>
                                    <span class="badge bg-danger rounded-pill">
                                        <?= $lowStockCnt ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Sales Today -->
                                <td class="text-end small fw-semibold
                                           <?= $salesToday > 0 ? 'text-success' : 'text-muted' ?>">
                                    <?= $n($salesToday) ?>
                                </td>

                                <!-- Revenue Today -->
                                <td class="text-end small fw-semibold
                                           <?= $revToday > 0 ? 'text-success' : 'text-muted' ?>">
                                    <?= $revToday > 0 ? $etb($revToday) : '—' ?>
                                </td>

                                <!-- Sales This Month -->
                                <td class="text-end small
                                           <?= $salesMonth > 0 ? 'text-primary fw-semibold' : 'text-muted' ?>">
                                    <?= $n($salesMonth) ?>
                                </td>

                                <!-- Incoming Transfers (in transit) -->
                                <td class="text-end small pe-3">
                                    <?php if ($inTransit > 0): ?>
                                    <span class="badge bg-info text-dark rounded-pill">
                                        <?= $inTransit ?>
                                    </span>
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

            <?php if (!empty($shops)): ?>
            <div class="card-footer bg-white border-top py-2 px-3">
                <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Showing all <?= count($shops) ?> shop<?= count($shops) !== 1 ? 's' : '' ?>
                    registered in the system. Data reflects live database state.
                    New shops created from
                    <a href="<?= url('admin/organization/shops') ?>"
                       class="text-decoration-none">
                        Organization → Shops
                    </a>
                    appear here automatically.
                </small>
            </div>
            <?php endif; ?>
        </div><!-- /.card -->
    </div><!-- /.col -->

    <!-- ── Top Products This Month ──────────────────────────────────────── -->
    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-2">
                <span class="fw-semibold small">
                    <i class="bi bi-trophy-fill me-2 text-warning"></i>
                    Top Products This Month
                </span>
            </div>
            <div class="card-body p-0" style="max-height:460px;overflow-y:auto;">
                <?php if (empty($topProducts)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-box-seam d-block fs-2 mb-2 opacity-25"></i>
                    <small class="fw-semibold">No sales data yet</small><br>
                    <small>Sales made through the POS terminal will appear here.</small>
                </div>
                <?php else: ?>
                <!-- Find max revenue for relative bar widths -->
                <?php $maxRev = max(array_map(fn($p) => (float)($p['revenue'] ?? 0), $topProducts)); ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($topProducts as $i => $p):
                        $rev    = (float)($p['revenue'] ?? 0);
                        $qty    = (float)($p['qty_sold'] ?? 0);
                        $pct    = $maxRev > 0 ? round($rev / $maxRev * 100) : 0;
                        $medals = ['bi-trophy-fill text-warning',
                                   'bi-award-fill text-secondary',
                                   'bi-award text-warning'];
                        $medal  = $medals[$i] ?? 'bi-dot text-muted';
                    ?>
                    <li class="list-group-item px-3 py-2 border-0 border-bottom">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi <?= $medal ?> flex-shrink-0"
                               style="font-size:.85rem;"></i>
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="small fw-semibold text-truncate lh-sm">
                                    <?= e($p['product_name'] ?? '—') ?>
                                </div>
                                <?php if (!empty($p['sku'])): ?>
                                <div class="text-muted" style="font-size:.65rem;">
                                    SKU: <?= e($p['sku']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <div class="small fw-bold text-success">
                                    <?= $etb($rev) ?>
                                </div>
                                <div class="text-muted" style="font-size:.65rem;">
                                    <?= $n($qty, 1) ?> units
                                </div>
                            </div>
                        </div>
                        <!-- Revenue bar -->
                        <div class="progress" style="height:3px;">
                            <div class="progress-bar bg-warning"
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
            <div class="card-footer bg-white border-top py-2 px-3 text-center">
                <a href="<?= url('gm/shops/sales') ?>"
                   class="btn btn-outline-warning btn-sm">
                    <i class="bi bi-bar-chart me-1"></i>Full Sales Overview
                </a>
            </div>
        </div><!-- /.card -->
    </div><!-- /.col -->

</div><!-- /.row -->

<script>
function filterShopTable(filter) {
    document.querySelectorAll('.shop-row').forEach(function(row) {
        row.style.display =
            (filter === 'all' || row.dataset.status === filter) ? '' : 'none';
    });
}
</script>
