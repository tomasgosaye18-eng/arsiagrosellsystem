<?php
/**
 * AAM — Roles & Permissions Index
 * @var \AAM\Core\View $view
 * @var array          $roles
 * @var int            $totalPerms
 */
declare(strict_types=1);

$roleColors = [
    'administrator'     => ['primary',  'bi-shield-fill-check'],
    'general_manager'   => ['success',  'bi-briefcase-fill'],
    'farm_manager'      => ['success',  'bi-flower1'],
    'warehouse_manager' => ['info',     'bi-building-fill'],
    'salesperson'       => ['warning',  'bi-receipt'],
];

$roleDescriptions = [
    'administrator'     => 'System administration: users, security, backup, organization, products.',
    'general_manager'   => 'Business oversight: farm, warehouse, transfers, shops, approvals, reports.',
    'farm_manager'      => 'Farm operations: harvest recording, dispatch preparation (own farm only).',
    'warehouse_manager' => 'Warehouse operations: receiving, inventory, transfers (own warehouse only).',
    'salesperson'       => 'POS sales and shop receiving (own assigned shop only).',
];
?>

<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-person-badge-fill me-2 text-primary"></i>Roles &amp; Permissions</h4>
        <p class="text-muted small mb-0">
            System roles and their granted permission sets.
            <span class="fw-semibold"><?= $totalPerms ?></span> total permissions defined.
        </p>
    </div>
</div>

<!-- Role Cards -->
<div class="row g-3">
<?php foreach ($roles as $role):
    [$color, $icon] = $roleColors[$role['name']] ?? ['secondary', 'bi-person-fill'];
    $userCount       = (int)($role['user_count']       ?? 0);
    $permCount       = (int)($role['permission_count'] ?? 0);
    $desc            = $roleDescriptions[$role['name']] ?? ($role['description'] ?? '');
?>
<div class="col-md-6 col-xl-4">
    <div class="card border-0 shadow-sm h-100 card-hover" style="border-top:4px solid var(--bs-<?= $color ?>)!important;">
        <div class="card-body d-flex flex-column">
            <!-- Role Header -->
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="rounded-circle bg-<?= $color ?> bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:48px;height:48px;">
                    <i class="bi <?= $icon ?> text-<?= $color ?> fs-4"></i>
                </div>
                <div class="overflow-hidden">
                    <h6 class="mb-0 fw-bold"><?= e($role['display_name']) ?></h6>
                    <code class="text-muted" style="font-size:.72rem;"><?= e($role['name']) ?></code>
                </div>
                <span class="ms-auto badge <?= $role['is_active'] ? 'bg-success' : 'bg-secondary' ?> flex-shrink-0">
                    <?= $role['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
            </div>

            <!-- Description -->
            <?php if ($desc): ?>
            <p class="text-muted small mb-3 flex-grow-1"><?= e($desc) ?></p>
            <?php else: ?>
            <div class="flex-grow-1"></div>
            <?php endif; ?>

            <!-- Stats — factual counts only, no misleading percentage -->
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <div class="bg-light rounded p-2 text-center">
                        <div class="fw-bold fs-5 text-<?= $color ?>"><?= number_format($userCount) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">
                            <?= $userCount === 1 ? 'User' : 'Users' ?>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="bg-light rounded p-2 text-center">
                        <div class="fw-bold fs-5 text-<?= $color ?>"><?= number_format($permCount) ?></div>
                        <div class="text-muted" style="font-size:.72rem;">Permissions</div>
                    </div>
                </div>
            </div>

            <a href="<?= url('admin/roles/' . $role['id']) ?>" class="btn btn-outline-<?= $color ?> btn-sm w-100">
                <i class="bi bi-eye me-1"></i>View Permissions
            </a>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- Info note -->
<div class="alert alert-info d-flex align-items-start gap-2 mt-4" role="note">
    <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
    <div class="small">
        <strong>Access = Role Permission + Location Assignment.</strong>
        Each role defines <em>what</em> a user can do. The location assignment (Organization &rarr; Assignments)
        defines <em>where</em> they can do it. A Farm Manager assigned to Milk Farm can only operate within that farm.
        Role permission changes require a database migration.
    </div>
</div>
