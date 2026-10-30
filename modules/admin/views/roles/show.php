<?php
/**
 * AAM — Role Detail: Permissions & Users
 * @var \AAM\Core\View $view
 * @var array          $role
 * @var array          $allPermissions   module => [permission rows with is_granted flag]
 * @var array          $users
 */
declare(strict_types=1);

$roleColors = [
    'administrator'     => 'primary',
    'general_manager'   => 'success',
    'farm_manager'      => 'success',
    'warehouse_manager' => 'info',
    'salesperson'       => 'warning',
];
$color = $roleColors[$role['name']] ?? 'secondary';

$grantedCount = 0;
$totalInSystem = 0; // total permissions defined in the system
foreach ($allPermissions as $module => $perms) {
    foreach ($perms as $p) {
        $totalInSystem++;
        if ($p['is_granted']) $grantedCount++;
    }
}
?>

<!-- Back + Header -->
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="<?= url('admin/roles') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>All Roles
    </a>
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-person-badge-fill me-2 text-<?= $color ?>"></i><?= e($role['display_name']) ?></h4>
        <p class="text-muted small mb-0"><code><?= e($role['name']) ?></code> &mdash; <?= e($role['description'] ?? '') ?></p>
    </div>
    <span class="ms-auto badge bg-<?= $color ?> fs-6"><?= $grantedCount ?> permissions granted</span>
</div>

<div class="row g-4">

    <!-- Left: Permissions by module -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header fw-semibold small">
                <i class="bi bi-key-fill me-2 text-<?= $color ?>"></i>Permissions by Module
            </div>
            <div class="card-body p-0">
                <?php if (empty($allPermissions)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-key d-block fs-2 mb-2 opacity-25"></i>
                    <small>No permissions found.</small>
                </div>
                <?php else: ?>
                <div class="accordion accordion-flush" id="permAccordion">
                <?php
                $modIcons = [
                    'auth'          => ['bi-box-arrow-in-right', 'secondary'],
                    'admin'         => ['bi-shield-fill-check',  'danger'],
                    'farm'          => ['bi-flower1',            'success'],
                    'warehouse'     => ['bi-building-fill',      'primary'],
                    'transfer'      => ['bi-arrow-left-right',   'info'],
                    'shop'          => ['bi-shop',               'warning'],
                    'pos'           => ['bi-cart3',              'warning'],
                    'reports'       => ['bi-file-bar-graph-fill','secondary'],
                    'dashboard'     => ['bi-speedometer2',       'primary'],
                    'inventory'     => ['bi-clipboard-data',     'primary'],
                    'notifications' => ['bi-bell-fill',          'info'],
                ];
                $i = 0;
                foreach ($allPermissions as $module => $perms):
                    [$mIcon, $mColor] = $modIcons[$module] ?? ['bi-puzzle-fill', 'secondary'];
                    $granted = array_filter($perms, fn($p) => $p['is_granted']);
                    $isOpen  = count($granted) > 0;
                    $i++;
                ?>
                <div class="accordion-item border-0 border-bottom">
                    <h2 class="accordion-header">
                        <button class="accordion-button <?= $isOpen ? '' : 'collapsed' ?> py-2 small"
                                type="button" data-bs-toggle="collapse"
                                data-bs-target="#modPane<?= $i ?>"
                                aria-expanded="<?= $isOpen ? 'true' : 'false' ?>">
                            <i class="bi <?= $mIcon ?> text-<?= $mColor ?> me-2"></i>
                            <span class="fw-semibold text-capitalize"><?= e($module) ?></span>
                        <span class="badge bg-<?= count($granted) > 0 ? $mColor : 'secondary' ?> ms-2"><?= count($granted) ?> granted</span>
                        </button>
                    </h2>
                    <div id="modPane<?= $i ?>" class="accordion-collapse collapse <?= $isOpen ? 'show' : '' ?>">
                        <div class="accordion-body py-2 px-3">
                            <div class="row g-1">
                            <?php foreach ($perms as $perm): ?>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center gap-2 py-1">
                                    <?php if ($perm['is_granted']): ?>
                                    <i class="bi bi-check-circle-fill text-success flex-shrink-0"></i>
                                    <?php else: ?>
                                    <i class="bi bi-circle text-muted opacity-25 flex-shrink-0"></i>
                                    <?php endif; ?>
                                    <div>
                                        <div class="<?= $perm['is_granted'] ? 'text-dark' : 'text-muted' ?>" style="font-size:.8rem;">
                                            <?= e($perm['label'] ?: ucwords(str_replace(['.','_'], ' ', $perm['code']))) ?>
                                        </div>
                                        <code class="text-muted" style="font-size:.68rem;"><?= e($perm['code']) ?></code>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right: Users with this role -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header fw-semibold small d-flex justify-content-between align-items-center">
                <span><i class="bi bi-people-fill me-2 text-<?= $color ?>"></i>Users (<?= count($users) ?>)</span>
                <a href="<?= url('admin/users?role=' . urlencode($role['name'])) ?>" class="btn btn-outline-<?= $color ?> btn-sm">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($users)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-person-slash d-block fs-3 mb-2 opacity-25"></i>
                    <small>No users assigned to this role.</small>
                </div>
                <?php else: ?>
                <?php foreach ($users as $u):
                    $statusColor = match($u['account_status']) {
                        'active'    => 'success',
                        'inactive'  => 'secondary',
                        'suspended' => 'danger',
                        'locked'    => 'warning',
                        default     => 'secondary',
                    };
                ?>
                <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                    <div class="rounded-circle bg-<?= $color ?> bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:32px;height:32px;">
                        <i class="bi bi-person-fill text-<?= $color ?>" style="font-size:.8rem;"></i>
                    </div>
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="small fw-semibold text-truncate">
                            <?= e(trim($u['first_name'] . ' ' . $u['last_name'])) ?>
                        </div>
                        <div class="text-muted" style="font-size:.72rem;"><?= e($u['username']) ?></div>
                    </div>
                    <span class="badge bg-<?= $statusColor ?> flex-shrink-0" style="font-size:.65rem;">
                        <?= ucfirst($u['account_status']) ?>
                    </span>
                </div>
                <?php endforeach; ?>
                <?php if (count($users) >= 50): ?>
                <div class="text-center py-2">
                    <a href="<?= url('admin/users?role=' . urlencode($role['name'])) ?>" class="small text-muted">
                        View all users with this role →
                    </a>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Role Stats -->
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header fw-semibold small">
                <i class="bi bi-bar-chart-fill me-2 text-<?= $color ?>"></i>Role Summary
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between py-2 px-3">
                    <span class="text-muted small">Role ID</span>
                    <span class="small fw-semibold"><?= (int)$role['id'] ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between py-2 px-3">
                    <span class="text-muted small">Status</span>
                    <span class="badge bg-<?= $role['is_active'] ? 'success' : 'secondary' ?>">
                        <?= $role['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </li>
                <li class="list-group-item d-flex justify-content-between py-2 px-3">
                    <span class="text-muted small">Users assigned</span>
                    <span class="small fw-semibold"><?= number_format((int)($role['user_count'] ?? 0)) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between py-2 px-3">
                    <span class="text-muted small">Permissions granted</span>
                    <span class="small fw-semibold text-<?= $color ?>"><?= $grantedCount ?></span>
                </li>
            </ul>
        </div>
    </div>

</div>
