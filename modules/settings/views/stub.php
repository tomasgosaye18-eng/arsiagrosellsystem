<?php
/**
 * View: modules/settings/views/stub.php
 * Purpose: Shared stub for all settings pages — Phase 7.
 * @var string $pageTitle
 */
declare(strict_types=1);
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h4 class="fw-bold mb-0">
        <i class="bi bi-gear-fill me-2 text-primary"></i><?= e($pageTitle ?? 'Settings') ?>
    </h4>
</div>

<!-- Settings Navigation Tabs -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-0">
        <nav class="nav nav-tabs nav-fill px-3 pt-2">
            <?php
            $tabs = [
                'settings'            => ['icon' => 'bi-sliders',         'label' => 'General'],
                'settings/financial'  => ['icon' => 'bi-currency-dollar', 'label' => 'Financial'],
                'settings/inventory'  => ['icon' => 'bi-boxes',           'label' => 'Inventory'],
                'settings/security'   => ['icon' => 'bi-shield-lock',     'label' => 'Security'],
                'settings/backup'     => ['icon' => 'bi-cloud-arrow-up',  'label' => 'Backup'],
            ];
            $currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            foreach ($tabs as $path => $tab):
                $isActive = str_ends_with(rtrim($currentUri, '/'), '/' . $path) ||
                            str_ends_with(rtrim($currentUri, '/'), rtrim($path, '/'));
            ?>
            <a href="<?= url($path) ?>"
               class="nav-link <?= $isActive ? 'active' : '' ?>">
                <i class="bi <?= $tab['icon'] ?> me-1"></i><?= $tab['label'] ?>
            </a>
            <?php endforeach; ?>
        </nav>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <i class="bi bi-tools display-3 text-warning opacity-30 d-block mb-3"></i>
        <h5 class="fw-bold"><?= e($pageTitle ?? 'Settings') ?></h5>
        <p class="text-muted mb-4">
            Full settings management will be implemented in <strong>Phase 8</strong>.<br>
            Authentication, RBAC, and all user management features are active.
        </p>
        <a href="<?= url('admin/dashboard') ?>" class="btn btn-primary">
            <i class="bi bi-house me-1"></i>Back to Dashboard
        </a>
    </div>
</div>
