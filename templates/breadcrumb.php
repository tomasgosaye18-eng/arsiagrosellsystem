<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : templates/breadcrumb.php
 * Purpose  : Breadcrumb navigation component.
 *            Auto-builds breadcrumb from current URI path.
 *            Maximum 4 levels deep.
 * =========================================================
 */
declare(strict_types=1);

$uri      = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$appBase  = parse_url((string) \AAM\Config\Application::get('url', ''), PHP_URL_PATH) ?? '';
$relative = ltrim(str_replace($appBase, '', (string) $uri), '/');
$segments = array_filter(explode('/', $relative));

// Map path segments to display labels
$labels = [
    'admin'           => 'Administration',
    'users'           => 'Users',
    'products'        => 'Products',
    'categories'      => 'Categories',
    'units'           => 'Units of Measure',
    'audit-logs'      => 'Business Audit Trail',
    'farms'           => 'Farms',
    'harvests'        => 'Harvests',
    'dispatches'      => 'Dispatches',
    'warehouse'       => 'Warehouse',
    'receiving'       => 'Receiving Queue',
    'discrepancies'   => 'Discrepancies',
    'transfers'       => 'Transfers',
    'inventory'       => 'Inventory',
    'batches'         => 'Batches',
    'adjust'          => 'Adjustment',
    'shops'           => 'Shops',
    'pos'             => 'Point of Sale',
    'receipt'         => 'Receipt',
    'history'         => 'History',
    'reports'         => 'Reports',
    'analytics'       => 'Analytics',
    'dashboard'       => 'Dashboard',
    'notifications'   => 'Notifications',
    'settings'        => 'Settings',
    'security'        => 'Security',
    'backups'         => 'Backup & Restore',
    'create'          => 'New',
    'edit'            => 'Edit',
    'confirm'         => 'Confirm',
    'profile'         => 'My Profile',
    'change-password' => 'Change Password',
    'organization'    => 'Organization',
    'warehouses'      => 'Warehouses',
    'assignments'     => 'Assignments',
    'hierarchy'       => 'Hierarchy',
    'price-history'   => 'Price History',
    'price-update'    => 'Update Price',
    'barcode'         => 'Barcode',
    'export'          => 'Export',
    'show'            => 'Details',
];

// Skip breadcrumb on dashboard
if (empty($segments) || (count($segments) === 1 && reset($segments) === 'dashboard')) {
    return;
}
?>

<div class="container-fluid px-4 py-1 border-bottom breadcrumb-wrapper" style="background:var(--theme-footer-bg,#f8f9fa);">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small py-1">

            <!-- Home always first -->
            <li class="breadcrumb-item">
                <a href="<?= url('dashboard') ?>" class="text-decoration-none">
                    <i class="bi bi-house-fill" aria-hidden="true"></i>
                    <span class="visually-hidden">Home</span>
                </a>
            </li>

            <?php
            $path   = '';
            $segs   = array_values($segments);
            $total  = count($segs);

            // Limit to 3 breadcrumb segments (after Home)
            if ($total > 3) {
                echo '<li class="breadcrumb-item text-muted" aria-hidden="true">&hellip;</li>';
                $segs  = array_slice($segs, $total - 3);
                $total = 3;
            }

            foreach ($segs as $i => $segment):
                $path   .= '/' . $segment;
                $label   = $labels[$segment] ?? \AAM\Helpers\StringHelper::titleCase($segment);
                $isLast  = ($i === $total - 1);

                // If segment looks like a numeric ID, show it abbreviated
                if (is_numeric($segment)) {
                    $label = '#' . $segment;
                }
            ?>
                <?php if ($isLast): ?>
                    <li class="breadcrumb-item active fw-semibold" aria-current="page">
                        <?= e($label) ?>
                    </li>
                <?php else: ?>
                    <li class="breadcrumb-item">
                        <a href="<?= url(ltrim($path, '/')) ?>" class="text-decoration-none"><?= e($label) ?></a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>

        </ol>
    </nav>
</div>
