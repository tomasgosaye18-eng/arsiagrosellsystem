<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : templates/sidebar.php
 * Purpose  : Role-filtered sidebar navigation.
 *            ROLE_GENERAL_MANAGER  → business/operational nav
 *            ROLE_ADMIN            → system administration nav
 *            All other roles       → their own operational nav
 * =========================================================
 */
declare(strict_types=1);

$currentUri = $_SERVER['REQUEST_URI'] ?? '/';
$appBase    = rtrim(
    parse_url((string) \AAM\Config\Application::get('url', ''), PHP_URL_PATH) ?? '',
    '/'
);

$isActive = function(string $path) use ($currentUri, $appBase): bool {
    $relative = str_replace($appBase, '', $currentUri);
    return str_starts_with($relative, '/' . ltrim($path, '/'));
};

$isExact = function(string $path) use ($currentUri, $appBase): bool {
    $relative = rtrim(str_replace($appBase, '', strtok($currentUri, '?')), '/');
    return $relative === '/' . ltrim($path, '/') || ($path === 'dashboard' && $relative === '');
};

$canSee = function(array $roles) use ($userRole): bool {
    return empty($roles) || in_array($userRole, $roles, true);
};

$admin = ROLE_ADMIN;
$farm  = ROLE_FARM_MANAGER;
$wh    = ROLE_WAREHOUSE_MANAGER;
$cash  = ROLE_SALESPERSON;
$gm    = ROLE_GENERAL_MANAGER;

$farmOpen        = $isActive('farms') || $isActive('farm/dashboard') || $isActive('harvests') || $isActive('dispatches');
$farmReportsOpen = $isActive('reports/farm');
$whOpen          = $isActive('warehouse') || $isActive('transfers') || $isActive('inventory');
$orgOpen         = $isActive('admin/organization');
$cfgOpen         = $isActive('settings');
$adminOpen       = $isActive('admin/dashboard');
?>

<style>
.sidebar-zone-biz {
    font-size: .6rem;
    font-weight: 700;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: rgba(255,255,255,.4);
    padding: 0 8px;
    display: block;
}
.sidebar-admin-zone {
    font-size: .6rem;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: #ffc107;
    padding: 0 8px;
    display: block;
}
</style>

<nav id="sidebar"
     class="sidebar bg-dark text-white d-flex flex-column"
     aria-label="Main navigation">

    <!-- ── User Badge ─────────────────────────────────────── -->
    <div class="px-3 py-3 border-bottom border-secondary flex-shrink-0">
        <div class="d-flex align-items-center gap-2">
            <div class="d-flex align-items-center justify-content-center flex-shrink-0 rounded-circle
                        <?= $userRole === $admin ? '' : 'bg-primary' ?>"
                 style="width:36px;height:36px;
                        <?= $userRole === $admin ? 'background:rgba(255,193,7,.2);border:2px solid rgba(255,193,7,.4);' : '' ?>">
                <i class="bi <?= $userRole === $admin ? 'bi-shield-fill-check' : 'bi-person-fill' ?> text-white small"
                   style="<?= $userRole === $admin ? 'color:#ffc107!important;' : '' ?>"></i>
            </div>
            <div class="overflow-hidden">
                <div class="fw-semibold small text-white text-truncate lh-sm">
                    <?= e(\AAM\Core\SessionManager::get(SESSION_USER_NAME, 'User')) ?>
                </div>
                <div class="text-secondary" style="font-size:.72rem;">
                    <?= e(\AAM\Helpers\StringHelper::titleCase($userRole ?? '')) ?>
                </div>
            </div>
            <?php if ($userRole === $admin): ?>
            <span title="System Administrator" style="margin-left:auto;width:8px;height:8px;
                  border-radius:50%;background:#ffc107;flex-shrink:0;box-shadow:0 0 6px #ffc107;"></span>
            <?php elseif ($userRole === $gm): ?>
            <span title="General Manager" style="margin-left:auto;width:8px;height:8px;
                  border-radius:50%;background:#198754;flex-shrink:0;box-shadow:0 0 6px #198754;"></span>
            <?php endif; ?>
        </div>
    </div>

    <ul class="nav flex-column px-2 py-2 flex-grow-1" style="font-size:.875rem;">

        <!-- ═══════════════════════════════════════════════
             SYSTEM ADMINISTRATOR NAV (ROLE_ADMIN only)
             ═══════════════════════════════════════════════ -->
        <?php if ($userRole === $admin): ?>

        <!-- Overview -->
        <li class="nav-item mt-1">
            <span class="sidebar-admin-zone mt-1"><i class="bi bi-shield-fill me-1"></i>System Administration</span>
        </li>
        <li class="nav-item">
            <a href="<?= url('admin/dashboard') ?>"
               class="nav-link sidebar-link <?= $isActive('admin/dashboard') ? 'active' : '' ?>">
                <i class="bi bi-speedometer2 me-2"></i>
                <span class="sidebar-label">Dashboard</span>
            </a>
        </li>

        <!-- User Management -->
        <li class="nav-item mt-2">
            <span class="sidebar-zone-biz">Users</span>
        </li>
        <li class="nav-item">
            <a href="<?= url('admin/users') ?>"
               class="nav-link sidebar-link <?= $isActive('admin/users') ? 'active' : '' ?>">
                <i class="bi bi-people-fill me-2"></i>
                <span class="sidebar-label">User Management</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= url('admin/roles') ?>"
               class="nav-link sidebar-link <?= $isActive('admin/roles') ? 'active' : '' ?>">
                <i class="bi bi-person-badge-fill me-2"></i>
                <span class="sidebar-label">Roles &amp; Permissions</span>
            </a>
        </li>

        <!-- Organization -->
        <li class="nav-item mt-2">
            <span class="sidebar-zone-biz">Organization</span>
        </li>
        <?php $orgOpen = $isActive('admin/organization'); ?>
        <li class="nav-item">
            <a class="nav-link sidebar-link d-flex align-items-center <?= $orgOpen ? 'active' : '' ?>"
               data-bs-toggle="collapse" href="#navOrg"
               aria-expanded="<?= $orgOpen ? 'true' : 'false' ?>" aria-controls="navOrg">
                <i class="bi bi-diagram-3-fill me-2"></i>
                <span class="sidebar-label flex-grow-1">Organization</span>
                <i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse <?= $orgOpen ? 'show' : '' ?>" id="navOrg">
                <ul class="nav flex-column ms-3 border-start border-secondary ps-2 mt-1">
                    <li><a href="<?= url('admin/organization') ?>"
                           class="nav-link sidebar-link-sub <?= $isExact('admin/organization') ? 'active' : '' ?>">
                        <i class="bi bi-speedometer2 me-1"></i>Overview
                    </a></li>
                    <li><a href="<?= url('admin/organization/farms') ?>"
                           class="nav-link sidebar-link-sub <?= $isActive('admin/organization/farms') ? 'active' : '' ?>">
                        <i class="bi bi-flower1 me-1"></i>Farms
                    </a></li>
                    <li><a href="<?= url('admin/organization/warehouses') ?>"
                           class="nav-link sidebar-link-sub <?= $isActive('admin/organization/warehouses') ? 'active' : '' ?>">
                        <i class="bi bi-building me-1"></i>Warehouses
                    </a></li>
                    <li><a href="<?= url('admin/organization/shops') ?>"
                           class="nav-link sidebar-link-sub <?= $isActive('admin/organization/shops') ? 'active' : '' ?>">
                        <i class="bi bi-shop me-1"></i>Shops
                    </a></li>
                    <li><a href="<?= url('admin/organization/assignments') ?>"
                           class="nav-link sidebar-link-sub <?= $isActive('admin/organization/assignments') ? 'active' : '' ?>">
                        <i class="bi bi-person-badge-fill me-1"></i>Assignments
                    </a></li>
                </ul>
            </div>
        </li>

        <!-- Product Catalog -->
        <li class="nav-item mt-2">
            <span class="sidebar-zone-biz">Product Catalog</span>
        </li>
        <li class="nav-item">
            <a href="<?= url('admin/products') ?>"
               class="nav-link sidebar-link <?= ($isActive('admin/products') || $isActive('admin/categories') || $isActive('admin/units')) ? 'active' : '' ?>">
                <i class="bi bi-box-seam-fill me-2"></i>
                <span class="sidebar-label">Products</span>
            </a>
        </li>

        <!-- Reports (admin: user, org, audit) -->
        <li class="nav-item mt-2">
            <span class="sidebar-zone-biz">Reports</span>
        </li>
        <?php $adminReportsOpen = $isActive('reports/admin'); ?>
        <li class="nav-item">
            <a class="nav-link sidebar-link d-flex align-items-center <?= $adminReportsOpen ? 'active' : '' ?>"
               data-bs-toggle="collapse" href="#navAdminReports"
               aria-expanded="<?= $adminReportsOpen ? 'true' : 'false' ?>" aria-controls="navAdminReports">
                <i class="bi bi-file-earmark-bar-graph me-2"></i>
                <span class="sidebar-label flex-grow-1">Reports</span>
                <i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse <?= $adminReportsOpen ? 'show' : '' ?>" id="navAdminReports">
                <ul class="nav flex-column ms-3 border-start border-secondary ps-2 mt-1">
                    <li><a href="<?= url('reports/admin/users') ?>" class="nav-link sidebar-link-sub <?= $isActive('reports/admin/users') ? 'active' : '' ?>">
                        <i class="bi bi-people-fill me-1"></i>User Report</a></li>
                    <li><a href="<?= url('reports/admin/organization') ?>" class="nav-link sidebar-link-sub <?= $isActive('reports/admin/organization') ? 'active' : '' ?>">
                        <i class="bi bi-diagram-3-fill me-1"></i>Organization</a></li>
                </ul>
            </div>
        </li>

        <!-- System Config -->
        <li class="nav-item mt-2">
            <span class="sidebar-zone-biz">System Config</span>
        </li>
        <?php $cfgOpen = $isActive('settings'); ?>
        <li class="nav-item">
            <a class="nav-link sidebar-link d-flex align-items-center <?= $cfgOpen ? 'active' : '' ?>"
               data-bs-toggle="collapse" href="#navSysConfig"
               aria-expanded="<?= $cfgOpen ? 'true' : 'false' ?>" aria-controls="navSysConfig">
                <i class="bi bi-gear-wide-connected me-2"></i>
                <span class="sidebar-label flex-grow-1">Settings</span>
                <i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse <?= $cfgOpen ? 'show' : '' ?>" id="navSysConfig">
                <ul class="nav flex-column ms-3 border-start border-secondary ps-2 mt-1">
                    <li><a href="<?= url('settings/backups') ?>"
                           class="nav-link sidebar-link-sub <?= $isActive('settings/backups') ? 'active' : '' ?>">
                        <i class="bi bi-cloud-arrow-down-fill me-1"></i>Backup &amp; Restore
                    </a></li>
                    <li><a href="<?= url('settings/payment-methods') ?>"
                           class="nav-link sidebar-link-sub <?= $isActive('settings/payment-methods') ? 'active' : '' ?>">
                        <i class="bi bi-credit-card-2-front-fill me-1"></i>Payment Methods
                    </a></li>
                    <li><a href="<?= url('settings/user-activity') ?>"
                           class="nav-link sidebar-link-sub <?= $isActive('settings/user-activity') ? 'active' : '' ?>">
                        <i class="bi bi-activity me-1"></i>User Activity
                    </a></li>
                </ul>
            </div>
        </li>

        <!-- Notifications -->
        <li class="nav-item mt-2">
            <span class="sidebar-zone-biz">Notifications</span>
        </li>
        <li class="nav-item">
            <a href="<?= url('notifications') ?>"
               class="nav-link sidebar-link <?= ($isActive('notifications') && !$isActive('notifications/send') && !$isActive('admin/notifications')) ? 'active' : '' ?>">
                <i class="bi bi-bell-fill me-2"></i>
                <span class="sidebar-label">My Notifications</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= url('admin/notifications/send') ?>"
               class="nav-link sidebar-link <?= $isActive('admin/notifications') ? 'active' : '' ?>">
                <i class="bi bi-send-fill me-2" style="color:#ffc107;"></i>
                <span class="sidebar-label">Send Notification</span>
            </a>
        </li>

        <?php endif; /* end ROLE_ADMIN */ ?>

        <!-- ═══════════════════════════════════════════════
             GENERAL MANAGER NAV (ROLE_GENERAL_MANAGER only)
             NEW — Dedicated /gm/* route namespace.
             Business/operational management only.
             NO user management. NO system config.
             ═══════════════════════════════════════════════ -->
        <?php if ($userRole === $gm):
            $gmBase        = 'gm/';
            $gmDashOpen    = $isActive('gm/dashboard') || $isExact('gm/dashboard');
            $gmFarmOpen    = $isActive('gm/farms') || $isActive('gm/farms/dispatches/pending');
            $gmWhOpen      = $isActive('gm/warehouses');
            $gmTrfOpen     = $isActive('gm/transfers');
            $gmShopOpen    = $isActive('gm/shops');            $gmInvOpen     = $isActive('gm/inventory') || $isActive('gm/adjustments');
            $gmRptOpen     = $isActive('gm/reports');
            $gmAnOpen      = $isActive('gm/analytics');
            $gmRptSubOpen  = $isActive('gm/reports/audit');
            $gmProdOpen    = $isActive('gm/products');
            $gmPayOpen     = $isActive('gm/payment-methods');
            $gmAuditOpen   = false;
            $gmSeedOpen    = $isActive('gm/seed-distribution');
        ?>

        <!-- OVERVIEW -->
        <li class="nav-item mt-1">
            <span class="sidebar-zone-biz mt-1"><i class="bi bi-speedometer2 me-1 text-success"></i>Overview</span>
        </li>
        <li class="nav-item">
            <a href="<?= url('gm/dashboard') ?>"
               class="nav-link sidebar-link <?= $isActive('gm/dashboard') ? 'active' : '' ?>">
                <i class="bi bi-speedometer2 me-2"></i>
                <span class="sidebar-label">Dashboard</span>
            </a>
        </li>

        <!-- OPERATIONS -->
        <li class="nav-item mt-2">
            <span class="sidebar-zone-biz">Operations</span>
        </li>

        <!-- Farm -->
        <?php
        // Count farm dispatches awaiting GM approval (for sidebar badge)
        $gmPendingDispatchCount = 0;
        try {
            $gmPendingDispatchCount = (int)(\AAM\Core\Database::getInstance()->fetchColumn(
                "SELECT COUNT(*) FROM farm_dispatches WHERE status = 'pending_approval'",
                []
            ) ?? 0);
        } catch (\Throwable $_ex) { $gmPendingDispatchCount = 0; }
        ?>
        <li class="nav-item">
            <a class="nav-link sidebar-link d-flex align-items-center <?= $gmFarmOpen ? 'active' : '' ?>"
               data-bs-toggle="collapse" href="#navGmFarm"
               aria-expanded="<?= $gmFarmOpen ? 'true' : 'false' ?>" aria-controls="navGmFarm">
                <i class="bi bi-flower1 me-2"></i>
                <span class="sidebar-label flex-grow-1">Farm</span>
                <?php if ($gmPendingDispatchCount > 0): ?>
                <span class="badge bg-danger rounded-pill me-1" style="font-size:.55rem;"
                      title="<?= $gmPendingDispatchCount ?> dispatch(es) awaiting approval">
                    <?= $gmPendingDispatchCount > 99 ? '99+' : $gmPendingDispatchCount ?>
                </span>
                <?php endif; ?>
                <i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse <?= $gmFarmOpen ? 'show' : '' ?>" id="navGmFarm">
                <ul class="nav flex-column ms-3 border-start border-secondary ps-2 mt-1">
                    <li><a href="<?= url('gm/farms') ?>"
                           class="nav-link sidebar-link-sub <?= $isExact('gm/farms') ? 'active' : '' ?>">
                        <i class="bi bi-grid-fill me-1"></i>All Farms
                    </a></li>
                    <li><a href="<?= url('gm/farms/harvests') ?>"
                           class="nav-link sidebar-link-sub <?= $isActive('gm/farms/harvests') ? 'active' : '' ?>">
                        <i class="bi bi-basket2-fill me-1"></i>Harvests
                    </a></li>
                    <li><a href="<?= url('gm/farms/dispatches') ?>"
                           class="nav-link sidebar-link-sub <?= $isExact('gm/farms/dispatches') ? 'active' : '' ?>">
                        <i class="bi bi-truck me-1"></i>Dispatches
                    </a></li>
                    <li><a href="<?= url('gm/farms/dispatches/pending') ?>"
                           class="nav-link sidebar-link-sub d-flex align-items-center <?= $isActive('gm/farms/dispatches/pending') ? 'active' : '' ?>">
                        <i class="bi bi-hourglass-split me-1 text-warning"></i>
                        <span class="text-warning fw-semibold flex-grow-1">Pending Approvals</span>
                        <?php if ($gmPendingDispatchCount > 0): ?>
                        <span class="badge bg-danger rounded-pill" style="font-size:.6rem;"
                              aria-label="<?= $gmPendingDispatchCount ?> pending">
                            <?= $gmPendingDispatchCount > 99 ? '99+' : $gmPendingDispatchCount ?>
                        </span>
                        <?php endif; ?>
                    </a></li>
                </ul>
            </div>
        </li>

        <!-- Warehouse -->
        <li class="nav-item">
            <a class="nav-link sidebar-link d-flex align-items-center <?= $gmWhOpen ? 'active' : '' ?>"
               data-bs-toggle="collapse" href="#navGmWarehouse"
               aria-expanded="<?= $gmWhOpen ? 'true' : 'false' ?>" aria-controls="navGmWarehouse">
                <i class="bi bi-building me-2"></i>
                <span class="sidebar-label flex-grow-1">Warehouse</span>
                <i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse <?= $gmWhOpen ? 'show' : '' ?>" id="navGmWarehouse">
                <ul class="nav flex-column ms-3 border-start border-secondary ps-2 mt-1">
                    <li><a href="<?= url('gm/warehouses') ?>"
                           class="nav-link sidebar-link-sub <?= $isExact('gm/warehouses') ? 'active' : '' ?>">
                        <i class="bi bi-grid-fill me-1"></i>All Warehouses
                    </a></li>
                    <li><a href="<?= url('gm/warehouses/receiving') ?>"
                           class="nav-link sidebar-link-sub <?= $isActive('gm/warehouses/receiving') ? 'active' : '' ?>">
                        <i class="bi bi-inbox-fill me-1"></i>Receiving
                    </a></li>
                    <li><a href="<?= url('gm/warehouses/discrepancies') ?>"
                           class="nav-link sidebar-link-sub <?= $isActive('gm/warehouses/discrepancies') ? 'active' : '' ?>">
                        <i class="bi bi-clipboard-x me-1"></i>Discrepancies
                    </a></li>
                    <li><a href="<?= url('gm/warehouses/discrepancies/pending-review') ?>"
                           class="nav-link sidebar-link-sub d-flex align-items-center <?= $isActive('gm/warehouses/discrepancies/pending-review') ? 'active' : '' ?>">
                        <i class="bi bi-shield-check me-1 text-warning"></i>
                        <span class="text-warning fw-semibold flex-grow-1">Pending Review</span>
                        <?php
                        // Count farm→warehouse receiving discrepancies awaiting GM review
                        $gmRcvDiscrepancyCount = 0;
                        try {
                            $gmRcvDiscrepancyCount = (int)(\AAM\Core\Database::getInstance()->fetchColumn(
                                "SELECT COUNT(*) FROM warehouse_receiving WHERE status = 'discrepancy_pending'",
                                []
                            ) ?? 0);
                        } catch (\Throwable $_ex) { $gmRcvDiscrepancyCount = 0; }
                        ?>
                        <?php if ($gmRcvDiscrepancyCount > 0): ?>
                        <span class="badge bg-warning text-dark rounded-pill" style="font-size:.6rem;"
                              aria-label="<?= $gmRcvDiscrepancyCount ?> pending">
                            <?= $gmRcvDiscrepancyCount > 99 ? '99+' : $gmRcvDiscrepancyCount ?>
                        </span>
                        <?php endif; ?>
                    </a></li>
                    <li><a href="<?= url('warehouse/movements') ?>"
                           class="nav-link sidebar-link-sub <?= $isActive('warehouse/movements') ? 'active' : '' ?>">
                        <i class="bi bi-clock-history me-1"></i>Stock Movements
                    </a></li>
                </ul>
            </div>
        </li>

        <!-- Transfers — GM approves -->
        <?php
        // Count warehouse transfers awaiting GM approval (for sidebar badge)
        $gmPendingTransferCount = 0;
        try {
            $gmPendingTransferCount = (int)(\AAM\Core\Database::getInstance()->fetchColumn(
                "SELECT COUNT(*) FROM warehouse_transfers WHERE status = 'pending'",
                []
            ) ?? 0);
        } catch (\Throwable $_ex) { $gmPendingTransferCount = 0; }
        ?>
        <li class="nav-item">
            <a class="nav-link sidebar-link d-flex align-items-center <?= $gmTrfOpen ? 'active' : '' ?>"
               data-bs-toggle="collapse" href="#navGmTransfers"
               aria-expanded="<?= $gmTrfOpen ? 'true' : 'false' ?>" aria-controls="navGmTransfers">
                <i class="bi bi-arrow-left-right me-2"></i>
                <span class="sidebar-label flex-grow-1">Transfers</span>
                <?php if ($gmPendingTransferCount > 0): ?>
                <span class="badge bg-danger rounded-pill me-1" style="font-size:.55rem;"
                      title="<?= $gmPendingTransferCount ?> transfer(s) awaiting approval">
                    <?= $gmPendingTransferCount > 99 ? '99+' : $gmPendingTransferCount ?>
                </span>
                <?php endif; ?>
                <i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse <?= $gmTrfOpen ? 'show' : '' ?>" id="navGmTransfers">
                <ul class="nav flex-column ms-3 border-start border-secondary ps-2 mt-1">
                    <li><a href="<?= url('gm/transfers') ?>"
                           class="nav-link sidebar-link-sub <?= $isExact('gm/transfers') ? 'active' : '' ?>">
                        <i class="bi bi-list-ul me-1"></i>All Transfers
                    </a></li>
                    <li><a href="<?= url('gm/transfers/pending') ?>"
                           class="nav-link sidebar-link-sub d-flex align-items-center <?= $isActive('gm/transfers/pending') ? 'active' : '' ?>">
                        <i class="bi bi-hourglass-split me-1 text-warning"></i>
                        <span class="flex-grow-1">Pending Approval</span>
                        <?php if ($gmPendingTransferCount > 0): ?>
                        <span class="badge bg-danger rounded-pill" style="font-size:.6rem;"
                              aria-label="<?= $gmPendingTransferCount ?> pending">
                            <?= $gmPendingTransferCount > 99 ? '99+' : $gmPendingTransferCount ?>
                        </span>
                        <?php endif; ?>
                    </a></li>
                    <li><a href="<?= url('gm/transfers/discrepancies/pending-review') ?>"
                           class="nav-link sidebar-link-sub d-flex align-items-center <?= $isActive('gm/transfers/discrepancies') ? 'active' : '' ?>">
                        <i class="bi bi-shield-exclamation me-1 text-danger"></i>
                        <span class="text-danger fw-semibold flex-grow-1">Discrepancy Review</span>
                        <?php
                        // Count transfer discrepancies awaiting GM review
                        $gmTrfDiscrepancyCount = 0;
                        try {
                            $gmTrfDiscrepancyCount = (int)(\AAM\Core\Database::getInstance()->fetchColumn(
                                "SELECT COUNT(*) FROM warehouse_transfers WHERE status = 'discrepancy_pending'",
                                []
                            ) ?? 0);
                        } catch (\Throwable $_ex) { $gmTrfDiscrepancyCount = 0; }
                        ?>
                        <?php if ($gmTrfDiscrepancyCount > 0): ?>
                        <span class="badge bg-danger rounded-pill" style="font-size:.6rem;"
                              aria-label="<?= $gmTrfDiscrepancyCount ?> pending">
                            <?= $gmTrfDiscrepancyCount > 99 ? '99+' : $gmTrfDiscrepancyCount ?>
                        </span>
                        <?php endif; ?>
                    </a></li>
                    <li><a href="<?= url('gm/transfers?status=in_transit') ?>"
                           class="nav-link sidebar-link-sub <?= str_contains($currentUri,'gm/transfers') && str_contains($currentUri,'in_transit') ? 'active' : '' ?>">
                        <i class="bi bi-truck me-1"></i>In Transit
                    </a></li>
                </ul>
            </div>
        </li>

        <!-- Shops -->
        <li class="nav-item">
            <a class="nav-link sidebar-link d-flex align-items-center <?= $gmShopOpen ? 'active' : '' ?>"
               data-bs-toggle="collapse" href="#navGmShops"
               aria-expanded="<?= $gmShopOpen ? 'true' : 'false' ?>" aria-controls="navGmShops">
                <i class="bi bi-shop me-2"></i>
                <span class="sidebar-label flex-grow-1">Shops</span>
                <i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse <?= $gmShopOpen ? 'show' : '' ?>" id="navGmShops">
                <ul class="nav flex-column ms-3 border-start border-secondary ps-2 mt-1">
                    <li><a href="<?= url('gm/shops') ?>"
                           class="nav-link sidebar-link-sub <?= $isExact('gm/shops') ? 'active' : '' ?>">
                        <i class="bi bi-grid-fill me-1"></i>All Shops
                    </a></li>
                    <li><a href="<?= url('gm/shops/sales') ?>"
                           class="nav-link sidebar-link-sub <?= $isActive('gm/shops/sales') ? 'active' : '' ?>">
                        <i class="bi bi-bar-chart-line me-1"></i>Sales Overview
                    </a></li>
                </ul>
            </div>
        </li>

        <!-- MANAGEMENT -->
        <li class="nav-item mt-2">
            <span class="sidebar-zone-biz">Management</span>
        </li>

        <!-- Seed Distribution — GM monitoring & approval -->
        <li class="nav-item">
            <a class="nav-link sidebar-link d-flex align-items-center <?= $gmSeedOpen ? 'active' : '' ?>"
               data-bs-toggle="collapse" href="#navGmSeedDist"
               aria-expanded="<?= $gmSeedOpen ? 'true' : 'false' ?>" aria-controls="navGmSeedDist">
                <i class="bi bi-flower1 me-2 text-success"></i>
                <span class="sidebar-label flex-grow-1">Seed Distribution</span>
                <?php
                // Show pending badge
                try {
                    $sdDb = \AAM\Core\Database::getInstance();
                    $sdPending = (int)($sdDb->fetchOne("SELECT COUNT(*) AS c FROM seed_distributions WHERE status='pending'")['c'] ?? 0);
                } catch (\Throwable $sdEx) { $sdPending = 0; }
                if ($sdPending > 0): ?>
                <span class="badge bg-warning text-dark" style="font-size:.55rem;"><?= $sdPending ?></span>
                <?php endif; ?>
                <i class="bi bi-chevron-down small ms-1"></i>
            </a>
            <div class="collapse <?= $gmSeedOpen ? 'show' : '' ?>" id="navGmSeedDist">
                <ul class="nav flex-column ms-3 border-start border-secondary ps-2 mt-1">
                    <li><a href="<?= url('gm/seed-distribution') ?>"
                           class="nav-link sidebar-link-sub <?= $isExact('gm/seed-distribution') ? 'active' : '' ?>">
                        <i class="bi bi-speedometer2 me-1"></i>Overview
                    </a></li>
                    <li><a href="<?= url('gm/seed-distribution/products') ?>"
                           class="nav-link sidebar-link-sub <?= $isActive('gm/seed-distribution/products') ? 'active' : '' ?>">
                        <i class="bi bi-box-seam me-1"></i>Seed Products
                    </a></li>
                    <li><a href="<?= url('gm/seed-distribution/stock') ?>"
                           class="nav-link sidebar-link-sub <?= $isActive('gm/seed-distribution/stock') ? 'active' : '' ?>">
                        <i class="bi bi-boxes me-1"></i>Seed Stock
                    </a></li>
                    <li><a href="<?= url('gm/seed-distribution/pending') ?>"
                           class="nav-link sidebar-link-sub <?= $isActive('gm/seed-distribution/pending') ? 'active' : '' ?>">
                        <i class="bi bi-hourglass-split me-1 text-warning"></i>
                        <span class="text-warning fw-semibold">Pending Approvals</span>
                    </a></li>
                    <li><a href="<?= url('gm/seed-distribution/distributions') ?>"
                           class="nav-link sidebar-link-sub <?= $isActive('gm/seed-distribution/distributions') ? 'active' : '' ?>">
                        <i class="bi bi-truck me-1"></i>Distributions
                    </a></li>
                    <li><a href="<?= url('gm/seed-distribution/history') ?>"
                           class="nav-link sidebar-link-sub <?= $isActive('gm/seed-distribution/history') ? 'active' : '' ?>">
                        <i class="bi bi-clock-history me-1"></i>History
                    </a></li>
                </ul>
            </div>
        </li>

        <!-- Customer Orders — GM monitoring view -->
        <?php $gmOrderOpen = $isActive('gm/orders'); ?>
        <li class="nav-item">
            <a href="<?= url('gm/orders') ?>"
               class="nav-link sidebar-link <?= $gmOrderOpen ? 'active' : '' ?>">
                <i class="bi bi-bag-check-fill me-2"></i>
                <span class="sidebar-label">Orders</span>
            </a>
        </li>

        <!-- Sales Receipts — GM unified read-only view (POS + E-Commerce) -->
        <?php $gmReceiptsOpen = $isActive('gm/receipts'); ?>
        <li class="nav-item">
            <a href="<?= url('gm/receipts') ?>"
               class="nav-link sidebar-link <?= $gmReceiptsOpen ? 'active' : '' ?>">
                <i class="bi bi-receipt me-2"></i>
                <span class="sidebar-label">Sales Receipts</span>
            </a>
        </li>

        <!-- Inventory — overview + pending approvals -->
        <li class="nav-item">
            <a class="nav-link sidebar-link d-flex align-items-center <?= $gmInvOpen ? 'active' : '' ?>"
               data-bs-toggle="collapse" href="#navGmInventory"
               aria-expanded="<?= $gmInvOpen ? 'true' : 'false' ?>" aria-controls="navGmInventory">
                <i class="bi bi-boxes me-2"></i>
                <span class="sidebar-label flex-grow-1">Inventory</span>
                <i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse <?= $gmInvOpen ? 'show' : '' ?>" id="navGmInventory">
                <ul class="nav flex-column ms-3 border-start border-secondary ps-2 mt-1">
                    <li><a href="<?= url('gm/inventory') ?>"
                           class="nav-link sidebar-link-sub <?= $isExact('gm/inventory') ? 'active' : '' ?>">
                        <i class="bi bi-speedometer2 me-1"></i>Overview
                    </a></li>
                    <li><a href="<?= url('gm/adjustments') ?>"
                           class="nav-link sidebar-link-sub <?= $isActive('gm/adjustments') ? 'active' : '' ?>">
                        <i class="bi bi-sliders me-1 text-warning"></i>Adjustments
                    </a></li>
                </ul>
            </div>
        </li>

        <!-- Product Catalog — GM read-only view at /gm/products -->
        <li class="nav-item">
            <a href="<?= url('gm/products') ?>"
               class="nav-link sidebar-link <?= ($isActive('gm/products') && !str_contains($currentUri, '/pricing') && !str_contains($currentUri, '/price-history')) ? 'active' : '' ?>">
                <i class="bi bi-box-seam me-2"></i>
                <span class="sidebar-label">Products</span>
            </a>
        </li>

        <!-- Payment Methods — GM read-only status view -->
        <li class="nav-item">
            <a href="<?= url('gm/payment-methods') ?>"
               class="nav-link sidebar-link <?= $isActive('gm/payment-methods') ? 'active' : '' ?>">
                <i class="bi bi-credit-card-2-front me-2"></i>
                <span class="sidebar-label">Payment Methods</span>
            </a>
        </li>

        <!-- INSIGHTS -->
        <li class="nav-item mt-2">
            <span class="sidebar-zone-biz">Insights</span>
        </li>

        <!-- Reports -->
        <li class="nav-item">
            <a class="nav-link sidebar-link d-flex align-items-center <?= ($gmRptOpen || $gmRptSubOpen) ? 'active' : '' ?>"
               data-bs-toggle="collapse" href="#navGmReports"
               aria-expanded="<?= ($gmRptOpen || $gmRptSubOpen) ? 'true' : 'false' ?>" aria-controls="navGmReports">
                <i class="bi bi-file-earmark-bar-graph me-2"></i>
                <span class="sidebar-label flex-grow-1">Reports</span>
                <i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse <?= ($gmRptOpen || $gmRptSubOpen) ? 'show' : '' ?>" id="navGmReports">
                <ul class="nav flex-column ms-3 border-start border-secondary ps-2 mt-1">
                    <li><a href="<?= url('gm/reports') ?>"
                           class="nav-link sidebar-link-sub <?= $gmRptOpen ? 'active' : '' ?>">
                        <i class="bi bi-grid me-1"></i>Overview</a></li>
                    <li><a href="<?= url('gm/reports/audit') ?>"
                           class="nav-link sidebar-link-sub <?= $gmRptSubOpen ? 'active' : '' ?>">
                        <i class="bi bi-clipboard2-data-fill me-1"></i>Audit Summary</a></li>
                </ul>
            </div>
        </li>

        <!-- Analytics -->
        <li class="nav-item">
            <a class="nav-link sidebar-link d-flex align-items-center <?= $gmAnOpen ? 'active' : '' ?>"
               data-bs-toggle="collapse" href="#navGmAnalytics"
               aria-expanded="<?= $gmAnOpen ? 'true' : 'false' ?>" aria-controls="navGmAnalytics">
                <i class="bi bi-graph-up-arrow me-2"></i>
                <span class="sidebar-label flex-grow-1">Analytics</span>
                <i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse <?= $gmAnOpen ? 'show' : '' ?>" id="navGmAnalytics">
                <ul class="nav flex-column ms-3 border-start border-secondary ps-2 mt-1">
                    <li><a href="<?= url('gm/analytics') ?>"
                           class="nav-link sidebar-link-sub <?= $isExact('gm/analytics') ? 'active' : '' ?>">
                        <i class="bi bi-bar-chart me-1"></i>Overview
                    </a></li>
                    <li><a href="<?= url('gm/analytics/products') ?>"
                           class="nav-link sidebar-link-sub <?= $isActive('gm/analytics/products') ? 'active' : '' ?>">
                        <i class="bi bi-box-seam me-1"></i>Product Analytics
                    </a></li>
                </ul>
            </div>
        </li>

        <!-- ACCOUNT -->
        <li class="nav-item mt-2">
            <span class="sidebar-zone-biz">Account</span>
        </li>
        <?php
        // Unread notification count for GM
        $gmUnreadNotifCount = 0;
        try {
            $gmUid = \AAM\Core\SessionManager::userId();
            if ($gmUid) {
                $gmUnreadNotifCount = (int)(\AAM\Core\Database::getInstance()->fetchColumn(
                    "SELECT COUNT(*) FROM notifications WHERE recipient_user_id = :uid AND is_read = 0",
                    ['uid' => $gmUid]
                ) ?? 0);
            }
        } catch (\Throwable $_ex) { $gmUnreadNotifCount = 0; }
        ?>
        <li class="nav-item">
            <a href="<?= url('notifications') ?>"
               class="nav-link sidebar-link d-flex align-items-center <?= ($isActive('notifications') && !$isActive('notifications/send')) ? 'active' : '' ?>">
                <i class="bi bi-bell-fill me-2"></i>
                <span class="sidebar-label flex-grow-1">My Notifications</span>
                <?php if ($gmUnreadNotifCount > 0): ?>
                <span class="badge bg-danger rounded-pill" style="font-size:.6rem;"
                      aria-label="<?= $gmUnreadNotifCount ?> unread">
                    <?= $gmUnreadNotifCount > 99 ? '99+' : $gmUnreadNotifCount ?>
                </span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?= url('notifications/send') ?>"
               class="nav-link sidebar-link <?= $isActive('notifications/send') ? 'active' : '' ?>">
                <i class="bi bi-send-fill me-2 text-success"></i>
                <span class="sidebar-label">Send Notification</span>
            </a>
        </li>



        <?php endif; /* end ROLE_GENERAL_MANAGER */ ?>

        <!-- ═══════════════════════════════════════════════
             ALL OTHER ROLES (farm, warehouse, shop, pos)
             ═══════════════════════════════════════════════ -->
        <?php if ($userRole !== $admin && $userRole !== $gm): ?>

        <!-- Overview -->
        <li class="nav-item mt-1">
            <span class="sidebar-zone-biz mt-1">Overview</span>
        </li>
        <li class="nav-item">
            <a href="<?= $userRole === ROLE_FARM_MANAGER ? url('farm/dashboard') : url('dashboard') ?>"
               class="nav-link sidebar-link <?= ($isExact('dashboard') || $isActive('farm/dashboard')) ? 'active' : '' ?>">
                <i class="bi bi-speedometer2 me-2"></i>
                <span class="sidebar-label">Dashboard</span>
            </a>
        </li>

        <!-- Farm (farm manager only) -->
        <?php if ($canSee([$farm])): ?>
        <li class="nav-item mt-2"><span class="sidebar-zone-biz">Operations</span></li>
        <?php $farmOpen = $isActive('farms') || $isActive('farm/dashboard') || $isActive('harvests') || $isActive('dispatches');
              $sidebarFarmId   = (int)\AAM\Core\SessionManager::get(SESSION_LOCATION_ID, 0);
              $sidebarFarmType = \AAM\Core\SessionManager::get(SESSION_LOCATION_TYPE, '');
              $hasFarm         = ($sidebarFarmType === 'farm' && $sidebarFarmId > 0);
        ?>
        <li class="nav-item">
            <a class="nav-link sidebar-link d-flex align-items-center <?= $farmOpen ? 'active' : '' ?>"
               data-bs-toggle="collapse" href="#navFarm"
               aria-expanded="<?= $farmOpen ? 'true' : 'false' ?>" aria-controls="navFarm">
                <i class="bi bi-flower1 me-2"></i>
                <span class="sidebar-label flex-grow-1">Farm</span>
                <i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse <?= $farmOpen ? 'show' : '' ?>" id="navFarm">
                <ul class="nav flex-column ms-3 border-start border-secondary ps-2 mt-1">
                    <?php if ($hasFarm): ?>
                    <li><a href="<?= url('farms/' . $sidebarFarmId) ?>"
                           class="nav-link sidebar-link-sub <?= $isExact('farms/' . $sidebarFarmId) ? 'active' : '' ?>">
                        <i class="bi bi-flower1 me-1"></i>My Farm</a></li>
                    <li><a href="<?= url('farms/' . $sidebarFarmId . '/harvests') ?>"
                           class="nav-link sidebar-link-sub <?= str_contains($currentUri,'/harvests') ? 'active' : '' ?>">
                        <i class="bi bi-basket2-fill me-1"></i>Harvest</a></li>
                    <li><a href="<?= url('farms/' . $sidebarFarmId . '/dispatches') ?>"
                           class="nav-link sidebar-link-sub <?= str_contains($currentUri,'/dispatches') ? 'active' : '' ?>">
                        <i class="bi bi-truck me-1"></i>Dispatch</a></li>
                    <?php else: ?>
                    <li><a href="<?= url('farms') ?>" class="nav-link sidebar-link-sub">
                        <i class="bi bi-grid-fill me-1"></i>Farms</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </li>
        <?php endif; ?>

        <!-- Warehouse (warehouse manager) -->
        <?php if ($canSee([$wh])): ?>
        <?php if (!$canSee([$farm])): ?>
        <li class="nav-item mt-2"><span class="sidebar-zone-biz">Operations</span></li>
        <?php endif; ?>
        <?php
              // For WH sidebar-open: WM includes inventory section
              $whOpen = $isActive('warehouse') || $isActive('transfers')
                        || ($canSee([$wh]) && $isActive('inventory'));
        ?>
        <li class="nav-item">
            <a class="nav-link sidebar-link d-flex align-items-center <?= $whOpen ? 'active' : '' ?>"
               data-bs-toggle="collapse" href="#navWarehouse"
               aria-expanded="<?= $whOpen ? 'true' : 'false' ?>" aria-controls="navWarehouse">
                <i class="bi bi-building me-2"></i>
                <span class="sidebar-label flex-grow-1">Warehouse</span>
                <i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse <?= $whOpen ? 'show' : '' ?>" id="navWarehouse">
                <ul class="nav flex-column ms-3 border-start border-secondary ps-2 mt-1">
                    <li><a href="<?= url('warehouse') ?>" class="nav-link sidebar-link-sub <?= $isExact('warehouse') ? 'active' : '' ?>">
                        <i class="bi bi-speedometer2 me-1"></i>Overview</a></li>
                    <li><a href="<?= url('warehouse/receiving') ?>" class="nav-link sidebar-link-sub <?= $isActive('warehouse/receiving') ? 'active' : '' ?>"><i class="bi bi-inbox-fill me-1"></i>Receiving</a></li>
                    <li><a href="<?= url('warehouse/discrepancies') ?>" class="nav-link sidebar-link-sub <?= $isActive('warehouse/discrepancies') ? 'active' : '' ?>"><i class="bi bi-clipboard-x me-1"></i>Discrepancies</a></li>
                    <li>
                        <?php
                        // Unread notification count for WM (for Transfers link badge)
                        $wmUnreadNotifCount = 0;
                        try {
                            $wmUid = \AAM\Core\SessionManager::userId();
                            if ($wmUid) {
                                $wmUnreadNotifCount = (int)(\AAM\Core\Database::getInstance()->fetchColumn(
                                    "SELECT COUNT(*) FROM notifications WHERE recipient_user_id = :uid AND is_read = 0",
                                    ['uid' => $wmUid]
                                ) ?? 0);
                            }
                        } catch (\Throwable $_ex) { $wmUnreadNotifCount = 0; }
                        ?>
                        <a href="<?= url('transfers') ?>"
                           class="nav-link sidebar-link-sub d-flex align-items-center <?= $isActive('transfers') ? 'active' : '' ?>">
                            <i class="bi bi-arrow-right-circle me-1"></i>
                            <span class="flex-grow-1">Transfers</span>
                            <?php if ($wmUnreadNotifCount > 0): ?>
                            <span class="badge bg-danger rounded-pill" style="font-size:.6rem;"
                                  aria-label="<?= $wmUnreadNotifCount ?> unread">
                                <?= $wmUnreadNotifCount > 99 ? '99+' : $wmUnreadNotifCount ?>
                            </span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>
        </li>
        <?php endif; ?>

        <!-- Products — Farm Manager gets dedicated /farm/products (read-only) -->
        <?php if ($canSee([$farm])): ?>
        <li class="nav-item mt-2"><span class="sidebar-zone-biz">Inventory</span></li>
        <li class="nav-item">
            <a href="<?= url('farm/products') ?>"
               class="nav-link sidebar-link <?= $isActive('farm/products') ? 'active' : '' ?>">
                <i class="bi bi-box-seam me-2"></i>
                <span class="sidebar-label">Products</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- Products + Inventory — Warehouse Manager read-only products + inventory ops -->
        <?php if ($canSee([$wh]) && !$canSee([$farm])):
              $invSectionOpen = $isActive('products') || $isActive('inventory');
        ?>
        <li class="nav-item mt-2"><span class="sidebar-zone-biz">Inventory</span></li>
        <li class="nav-item">
            <a href="<?= url('products') ?>"
               class="nav-link sidebar-link <?= ($isActive('products') && !$isActive('admin/products')) ? 'active' : '' ?>">
                <i class="bi bi-box-seam me-2"></i>
                <span class="sidebar-label">Products</span>
            </a>
        </li>
        <?php $invOpen2 = $isActive('inventory'); ?>
        <li class="nav-item">
            <a class="nav-link sidebar-link d-flex align-items-center <?= $invOpen2 ? 'active' : '' ?>"
               data-bs-toggle="collapse" href="#navWhInvSection"
               aria-expanded="<?= $invOpen2 ? 'true' : 'false' ?>" aria-controls="navWhInvSection">
                <i class="bi bi-boxes me-2"></i>
                <span class="sidebar-label flex-grow-1">Inventory</span>
                <i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse <?= $invOpen2 ? 'show' : '' ?>" id="navWhInvSection">
                <ul class="nav flex-column ms-3 border-start border-secondary ps-2 mt-1">
                    <li><a href="<?= url('inventory') ?>" class="nav-link sidebar-link-sub <?= $isExact('inventory') ? 'active' : '' ?>"><i class="bi bi-speedometer2 me-1"></i>Overview</a></li>
                    <li><a href="<?= url('inventory/movements') ?>" class="nav-link sidebar-link-sub <?= $isActive('inventory/movements') ? 'active' : '' ?>"><i class="bi bi-clock-history me-1"></i>Stock Movements</a></li>
                    <li><a href="<?= url('inventory/adjustments') ?>" class="nav-link sidebar-link-sub <?= $isActive('inventory/adjustments') ? 'active' : '' ?>"><i class="bi bi-sliders me-1"></i>Adjustments</a></li>
                </ul>
            </div>
        </li>

        <!-- Seed Distribution — Warehouse (only if enabled for this warehouse) -->
        <?php
        $sdWhId      = (int)\AAM\Core\SessionManager::get(SESSION_LOCATION_ID, 0);
        $sdLocType   = \AAM\Core\SessionManager::get(SESSION_LOCATION_TYPE, '');
        $sdEnabled   = false;
        if ($sdLocType === 'warehouse' && $sdWhId > 0) {
            try {
                $sdDb2   = \AAM\Core\Database::getInstance();
                $sdRow   = $sdDb2->fetchOne(
                    "SELECT seed_distribution_enabled FROM warehouses WHERE id=:id AND is_active=1 AND deleted_at IS NULL",
                    ['id' => $sdWhId]
                );
                $sdEnabled = (bool)($sdRow['seed_distribution_enabled'] ?? false);
            } catch (\Throwable $sdEx2) { $sdEnabled = false; }
        }
        ?>
        <?php if ($sdEnabled): ?>
        <?php $sdOpen = $isActive('seed-distribution'); ?>
        <li class="nav-item mt-2"><span class="sidebar-zone-biz text-success"><i class="bi bi-flower1 me-1"></i>Seed Distribution</span></li>
        <li class="nav-item">
            <a class="nav-link sidebar-link d-flex align-items-center <?= $sdOpen ? 'active' : '' ?>"
               data-bs-toggle="collapse" href="#navSeedDist"
               aria-expanded="<?= $sdOpen ? 'true' : 'false' ?>" aria-controls="navSeedDist">
                <i class="bi bi-flower1 me-2 text-success"></i>
                <span class="sidebar-label flex-grow-1">Seed Distribution</span>
                <i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse <?= $sdOpen ? 'show' : '' ?>" id="navSeedDist">
                <ul class="nav flex-column ms-3 border-start border-secondary ps-2 mt-1">
                    <li><a href="<?= url('seed-distribution/seeds') ?>"
                           class="nav-link sidebar-link-sub <?= $isActive('seed-distribution/seeds') ? 'active' : '' ?>">
                        <i class="bi bi-boxes me-1 text-success"></i>Available Seeds
                    </a></li>
                    <li><a href="<?= url('seed-distribution/new') ?>"
                           class="nav-link sidebar-link-sub <?= $isActive('seed-distribution/new') ? 'active' : '' ?>">
                        <i class="bi bi-plus-circle me-1 text-success"></i>New Distribution
                    </a></li>
                    <li><a href="<?= url('seed-distribution/pending') ?>"
                           class="nav-link sidebar-link-sub <?= $isActive('seed-distribution/pending') ? 'active' : '' ?>">
                        <i class="bi bi-hourglass-split me-1 text-warning"></i>
                        <span class="text-warning fw-semibold">Pending Approval</span>
                    </a></li>
                    <li><a href="<?= url('seed-distribution/approved') ?>"
                           class="nav-link sidebar-link-sub <?= $isActive('seed-distribution/approved') ? 'active' : '' ?>">
                        <i class="bi bi-check-circle me-1 text-success"></i>Approved / Ready
                    </a></li>
                    <li><a href="<?= url('seed-distribution/released') ?>"
                           class="nav-link sidebar-link-sub <?= $isActive('seed-distribution/released') ? 'active' : '' ?>">
                        <i class="bi bi-truck me-1 text-primary"></i>Released / Dispatched
                    </a></li>
                    <li><a href="<?= url('seed-distribution/history') ?>"
                           class="nav-link sidebar-link-sub <?= $isActive('seed-distribution/history') ? 'active' : '' ?>">
                        <i class="bi bi-clock-history me-1"></i>History
                    </a></li>
                </ul>
            </div>
        </li>
        <?php endif; /* sdEnabled */ ?>

        <?php endif; /* wh && !farm */  ?>



        <!-- Sales / POS (salesperson) -->
        <?php
        $sidebarShopId  = (int)\AAM\Core\SessionManager::get(SESSION_LOCATION_ID, 0);
        $sidebarLocType = \AAM\Core\SessionManager::get(SESSION_LOCATION_TYPE, '');
        $hasShop        = ($sidebarLocType === 'shop' && $sidebarShopId > 0);
        $showPosNav     = $canSee([$cash]);
        ?>
        <?php if ($showPosNav): ?>
        <li class="nav-item mt-2"><span class="sidebar-zone-biz">Sales</span></li>
        <?php $posOpen = $isActive('pos') || $isActive('shop'); ?>
        <li class="nav-item">
            <a class="nav-link sidebar-link d-flex align-items-center <?= $posOpen ? 'active' : '' ?>"
               data-bs-toggle="collapse" href="#navPOS"
               aria-expanded="<?= $posOpen ? 'true' : 'false' ?>" aria-controls="navPOS">
                <i class="bi bi-cart3 me-2"></i>
                <span class="sidebar-label flex-grow-1">Shop &amp; POS</span>
                <?php if ($userRole === $cash): ?>
                <span class="badge bg-success" style="font-size:.55rem;margin-right:4px;">LIVE</span>
                <?php endif; ?>
                <i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse <?= $posOpen ? 'show' : '' ?>" id="navPOS">
                <ul class="nav flex-column ms-3 border-start border-secondary ps-2 mt-1">
                    <?php if ($hasShop): ?>
                    <li><a href="<?= url('shops/' . $sidebarShopId) ?>" class="nav-link sidebar-link-sub <?= $isExact('shops/' . $sidebarShopId) ? 'active' : '' ?>">
                        <i class="bi bi-shop me-1"></i>Shop Overview</a></li>
                    <?php endif; ?>
                    <li><a href="<?= url('pos') ?>" class="nav-link sidebar-link-sub <?= $isExact('pos') ? 'active' : '' ?>">
                        <i class="bi bi-cart-fill me-1"></i>POS Terminal</a></li>
                    <li><a href="<?= url('pos/history') ?>" class="nav-link sidebar-link-sub <?= $isActive('pos/history') ? 'active' : '' ?>">
                        <i class="bi bi-receipt me-1"></i>Sales History</a></li>
                    <li><a href="<?= url('pos/shift') ?>" class="nav-link sidebar-link-sub <?= $isActive('pos/shift') ? 'active' : '' ?>">
                        <i class="bi bi-person-badge me-1"></i>My Shift</a></li>
                    <?php if ($hasShop): ?>
                    <li><a href="<?= url('shops/' . $sidebarShopId . '/inventory') ?>" class="nav-link sidebar-link-sub <?= $isActive('shops/' . $sidebarShopId . '/inventory') ? 'active' : '' ?>">
                        <i class="bi bi-boxes me-1"></i>Shop Inventory</a></li>
                    <li><a href="<?= url('shops/' . $sidebarShopId . '/sales') ?>" class="nav-link sidebar-link-sub <?= $isActive('shops/' . $sidebarShopId . '/sales') ? 'active' : '' ?>">
                        <i class="bi bi-bar-chart-line me-1"></i>Shop Sales</a></li>
                    <li>
                        <?php
                        // Count transfers in_transit destined for this shop (badge on Receiving link)
                        $spInTransitCount = 0;
                        try {
                            if ($sidebarShopId > 0) {
                                $spInTransitCount = (int)(\AAM\Core\Database::getInstance()->fetchColumn(
                                    "SELECT COUNT(*) FROM warehouse_transfers
                                     WHERE shop_id = :sid AND status = 'in_transit'",
                                    ['sid' => $sidebarShopId]
                                ) ?? 0);
                            }
                        } catch (\Throwable $_ex) { $spInTransitCount = 0; }
                        ?>
                        <a href="<?= url('transfers/incoming') ?>"
                           class="nav-link sidebar-link-sub d-flex align-items-center <?= $isActive('transfers/incoming') ? 'active' : '' ?>">
                            <i class="bi bi-box-arrow-in-down me-1"></i>
                            <span class="flex-grow-1">Receiving</span>
                            <?php if ($spInTransitCount > 0): ?>
                            <span class="badge bg-danger rounded-pill" style="font-size:.6rem;"
                                  title="<?= $spInTransitCount ?> transfer(s) in transit"
                                  aria-label="<?= $spInTransitCount ?> incoming">
                                <?= $spInTransitCount > 99 ? '99+' : $spInTransitCount ?>
                            </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li><a href="<?= url('shop/stock-movements') ?>" class="nav-link sidebar-link-sub <?= $isActive('shop/stock-movements') ? 'active' : '' ?>">
                        <i class="bi bi-clock-history me-1"></i>Stock Movements</a></li>
                    <li><a href="<?= url('shop/discrepancies') ?>" class="nav-link sidebar-link-sub <?= $isActive('shop/discrepancies') ? 'active' : '' ?>">
                        <i class="bi bi-clipboard-x me-1"></i>Discrepancies</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </li>
        <?php endif; /* showPosNav */ ?>

        <!-- Customer Orders — Phase 6 (salesperson only) -->
        <?php
        if ($showPosNav) {
            $coBadgeCount = 0;
            try {
                $coShopId2  = (int)\AAM\Core\SessionManager::get(SESSION_LOCATION_ID, 0);
                $coLocType2 = \AAM\Core\SessionManager::get(SESSION_LOCATION_TYPE, '');
                if ($coLocType2 === 'shop' && $coShopId2 > 0) {
                    $coDb  = \AAM\Core\Database::getInstance();
                    $coRow = $coDb->fetchOne(
                        "SELECT COUNT(*) AS c FROM customer_orders
                         WHERE shop_id = :sid
                           AND order_status IN ('paid','preparing','ready_for_pickup')",
                        ['sid' => $coShopId2]
                    );
                    $coBadgeCount = (int)($coRow['c'] ?? 0);
                }
            } catch (\Throwable $coEx) {
                $coBadgeCount = 0;
            }
            $coActiveClass = $isActive('customer/shop-orders') ? ' active' : '';
            echo '<li class="nav-item">';
            echo '<a href="' . url('customer/shop-orders') . '" class="nav-link sidebar-link d-flex align-items-center' . $coActiveClass . '">';
            echo '<i class="bi bi-bag-check me-2"></i>';
            echo '<span class="sidebar-label flex-grow-1">Customer Orders</span>';
            if ($coBadgeCount > 0) {
                echo '<span class="badge bg-success" style="font-size:.6rem;">' . (int)$coBadgeCount . '</span>';
            }
            echo '</a></li>';
        }
        ?>

        <!-- Reports (farm manager) -->
        <?php if ($canSee([$farm])): ?>
        <li class="nav-item mt-2"><span class="sidebar-zone-biz">Insights</span></li>
        <?php $farmReportsOpen = $isActive('reports/farm'); ?>
        <li class="nav-item">
            <a class="nav-link sidebar-link d-flex align-items-center <?= $farmReportsOpen ? 'active' : '' ?>"
               data-bs-toggle="collapse" href="#navFarmReports"
               aria-expanded="<?= $farmReportsOpen ? 'true' : 'false' ?>" aria-controls="navFarmReports">
                <i class="bi bi-file-earmark-bar-graph me-2"></i>
                <span class="sidebar-label flex-grow-1">Reports</span>
                <i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse <?= $farmReportsOpen ? 'show' : '' ?>" id="navFarmReports">
                <ul class="nav flex-column ms-3 border-start border-secondary ps-2 mt-1">
                    <li><a href="<?= url('reports/farm/production') ?>" class="nav-link sidebar-link-sub <?= $isActive('reports/farm/production') ? 'active' : '' ?>">
                        <i class="bi bi-bar-chart-steps me-1"></i>Production</a></li>
                    <li><a href="<?= url('reports/farm/harvest') ?>" class="nav-link sidebar-link-sub <?= $isActive('reports/farm/harvest') ? 'active' : '' ?>">
                        <i class="bi bi-basket2-fill me-1"></i>Harvest</a></li>
                    <li><a href="<?= url('reports/farm/dispatch') ?>" class="nav-link sidebar-link-sub <?= $isActive('reports/farm/dispatch') ? 'active' : '' ?>">
                        <i class="bi bi-truck me-1"></i>Dispatch</a></li>
                </ul>
            </div>
        </li>
        <?php endif; ?>

        <!-- Reports (warehouse manager) -->
        <?php if ($canSee([$wh])): ?>
        <li class="nav-item mt-2"><span class="sidebar-zone-biz">Insights</span></li>
        <?php $whReportsOpen = $isActive('reports/warehouse'); ?>
        <li class="nav-item">
            <a class="nav-link sidebar-link d-flex align-items-center <?= $whReportsOpen ? 'active' : '' ?>"
               data-bs-toggle="collapse" href="#navWhReports"
               aria-expanded="<?= $whReportsOpen ? 'true' : 'false' ?>" aria-controls="navWhReports">
                <i class="bi bi-file-earmark-bar-graph me-2"></i>
                <span class="sidebar-label flex-grow-1">Reports</span>
                <i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse <?= $whReportsOpen ? 'show' : '' ?>" id="navWhReports">
                <ul class="nav flex-column ms-3 border-start border-secondary ps-2 mt-1">
                    <li><a href="<?= url('reports/warehouse/stock') ?>" class="nav-link sidebar-link-sub <?= $isActive('reports/warehouse/stock') ? 'active' : '' ?>"><i class="bi bi-boxes me-1"></i>Stock Report</a></li>
                    <li><a href="<?= url('reports/warehouse/receiving') ?>" class="nav-link sidebar-link-sub <?= $isActive('reports/warehouse/receiving') ? 'active' : '' ?>"><i class="bi bi-inbox-fill me-1"></i>Receiving Report</a></li>
                    <li><a href="<?= url('reports/warehouse/transfers') ?>" class="nav-link sidebar-link-sub <?= $isActive('reports/warehouse/transfers') ? 'active' : '' ?>"><i class="bi bi-arrow-right-circle me-1"></i>Transfer Report</a></li>
                    <li><a href="<?= url('reports/warehouse/discrepancies') ?>" class="nav-link sidebar-link-sub <?= $isActive('reports/warehouse/discrepancies') ? 'active' : '' ?>"><i class="bi bi-clipboard-x me-1"></i>Discrepancy Report</a></li>
                    <li><a href="<?= url('reports/warehouse/expiry') ?>" class="nav-link sidebar-link-sub <?= $isActive('reports/warehouse/expiry') ? 'active' : '' ?>"><i class="bi bi-calendar-x me-1"></i>Expiry Report</a></li>
                </ul>
            </div>
        </li>
        <?php endif; ?>

        <!-- Account (all non-admin/non-GM) -->
        <li class="nav-item mt-2"><span class="sidebar-zone-biz">Account</span></li>
        <?php
        // Unread notification count for non-admin/non-GM roles
        $roleUnreadNotifCount = 0;
        try {
            $roleUid = \AAM\Core\SessionManager::userId();
            if ($roleUid) {
                $roleUnreadNotifCount = (int)(\AAM\Core\Database::getInstance()->fetchColumn(
                    "SELECT COUNT(*) FROM notifications WHERE recipient_user_id = :uid AND is_read = 0",
                    ['uid' => $roleUid]
                ) ?? 0);
            }
        } catch (\Throwable $_ex) { $roleUnreadNotifCount = 0; }
        ?>
        <li class="nav-item">
            <a href="<?= url('notifications') ?>"
               class="nav-link sidebar-link d-flex align-items-center <?= $isActive('notifications') ? 'active' : '' ?>">
                <i class="bi bi-bell-fill me-2"></i>
                <span class="sidebar-label flex-grow-1">Notifications</span>
                <?php if ($roleUnreadNotifCount > 0): ?>
                <span class="badge bg-danger rounded-pill" style="font-size:.6rem;"
                      aria-label="<?= $roleUnreadNotifCount ?> unread">
                    <?= $roleUnreadNotifCount > 99 ? '99+' : $roleUnreadNotifCount ?>
                </span>
                <?php endif; ?>
            </a>
        </li>

        <?php endif; /* end other roles */ ?>

    </ul><!-- /.nav -->

    <!-- ── Footer ──────────────────────────────────────────── -->
    <div class="px-3 py-2 border-top border-secondary flex-shrink-0 text-center">
        <small class="text-secondary">
            <?= e(\AAM\Config\Application::get('name', 'AAM')) ?>
            v<?= e(\AAM\Config\Application::get('version', '1.0.0')) ?>
        </small>
    </div>

</nav>

<!-- Mobile/Tablet sidebar backdrop overlay -->
<div id="sidebarBackdrop" aria-hidden="true"></div>
