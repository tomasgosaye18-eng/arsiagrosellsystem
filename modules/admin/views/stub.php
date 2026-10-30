<?php
/**
 * View: modules/admin/views/stub.php
 * Purpose: Shared stub for admin module pages not yet implemented.
 * @var \AAM\Core\View $view
 * @var string         $module
 * @var string         $pageTitle
 */
declare(strict_types=1);
?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <i class="bi bi-tools display-3 text-warning opacity-40 d-block mb-3"></i>
        <h4 class="fw-bold"><?= e($module ?? $pageTitle ?? 'Module') ?></h4>
        <p class="text-muted mb-4">
            This module will be fully implemented in <strong>Phase 8</strong>.<br>
            Authentication and user management are active.
        </p>
        <a href="<?= url('dashboard') ?>" class="btn btn-primary">
            <i class="bi bi-house me-1"></i>Back to Dashboard
        </a>
    </div>
</div>
