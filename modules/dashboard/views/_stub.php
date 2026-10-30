<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 9
 * =========================================================
 * View     : modules/dashboard/views/_stub.php
 * Purpose  : Fallback welcome view for roles whose dedicated
 *            dashboard is not yet assigned. Shows role-aware
 *            quick navigation links.
 * =========================================================
 *
 * @var \AAM\Core\View $view
 * @var string         $userName
 * @var string         $userRole
 */
declare(strict_types=1);

$roleLabel = e(\AAM\Helpers\StringHelper::titleCase($userRole ?? ''));

$roleLinks = [
    ROLE_FARM_MANAGER      => [
        ['url' => 'farms',       'icon' => 'bi-flower1',          'label' => 'My Farms'],
        ['url' => 'reports',     'icon' => 'bi-file-earmark-bar-graph', 'label' => 'Reports'],
        ['url' => 'profile',     'icon' => 'bi-person-circle',    'label' => 'My Profile'],
    ],
    ROLE_WAREHOUSE_MANAGER => [
        ['url' => 'warehouse',   'icon' => 'bi-building',         'label' => 'Warehouse'],
        ['url' => 'transfers',   'icon' => 'bi-arrow-left-right', 'label' => 'Transfers'],
        ['url' => 'inventory',   'icon' => 'bi-clipboard-data',   'label' => 'Inventory'],
    ],
    ROLE_GENERAL_MANAGER   => [
        ['url' => 'reports',     'icon' => 'bi-file-earmark-bar-graph', 'label' => 'Reports'],
        ['url' => 'analytics',   'icon' => 'bi-graph-up-arrow',   'label' => 'Analytics'],
        ['url' => 'admin/audit-logs', 'icon' => 'bi-clipboard2-data', 'label' => 'Audit Trail'],
    ],
];

$links = $roleLinks[$userRole] ?? [
    ['url' => 'profile', 'icon' => 'bi-person-circle', 'label' => 'My Profile'],
];
?>

<div class="row justify-content-center">
    <div class="col-lg-8">

        <!-- Welcome Card -->
        <div class="card border-0 bg-primary text-white shadow mb-4">
            <div class="card-body py-4 text-center">
                <i class="bi bi-house-fill fs-1 mb-2 opacity-75 d-block"></i>
                <h4 class="fw-bold mb-1">Welcome, <?= e($userName ?? 'User') ?></h4>
                <p class="mb-0 opacity-75"><?= $roleLabel ?> &mdash; <?= date('l, d F Y') ?></p>
            </div>
        </div>

        <!-- Quick Navigation -->
        <div class="card border-0 shadow-sm">
            <div class="card-header fw-semibold small">
                <i class="bi bi-grid-fill me-2 text-primary"></i>Quick Navigation
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($links as $link): ?>
                    <div class="col-4">
                        <a href="<?= url($link['url']) ?>"
                           class="btn btn-outline-primary w-100 d-flex flex-column align-items-center py-3 gap-1 h-100">
                            <i class="bi <?= $link['icon'] ?> fs-4"></i>
                            <span style="font-size:.8rem;"><?= e($link['label']) ?></span>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>
</div>
