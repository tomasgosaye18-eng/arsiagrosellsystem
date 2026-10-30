<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 7
 * =========================================================
 * File     : modules/auth/views/access_denied.php
 * Layout   : templates/master.php  (or minimal for AJAX)
 * Purpose  : Standalone access-denied page rendered when
 *            a user with valid auth attempts to access a
 *            resource outside their role. Friendlier than
 *            the raw 403 error template.
 * Dependencies: RoleMiddleware, PermissionMiddleware
 * =========================================================
 *
 * @var \AAM\Core\View $view
 * @var string|null    $requiredRole     (optional)
 * @var string|null    $requiredPermission (optional)
 */
declare(strict_types=1);

$userRole  = \AAM\Core\SessionManager::get(SESSION_USER_ROLE, '');
$userName  = \AAM\Core\SessionManager::get(SESSION_USER_NAME, 'User');
?>

<div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">
        <div class="card shadow-sm border-0 text-center">
            <div class="card-body py-5 px-4">

                <!-- Icon -->
                <div class="mb-4">
                    <span class="d-inline-flex align-items-center justify-content-center
                                 bg-warning bg-opacity-10 rounded-circle"
                          style="width:80px;height:80px;" aria-hidden="true">
                        <i class="bi bi-shield-exclamation text-warning" style="font-size:2.5rem;"></i>
                    </span>
                </div>

                <h4 class="fw-bold mb-2">Access Denied</h4>
                <p class="text-muted mb-1">
                    Hello, <strong><?= e($userName) ?></strong>.
                </p>
                <p class="text-muted mb-4">
                    Your current role (<em><?= e(\AAM\Helpers\StringHelper::titleCase($userRole)) ?></em>)
                    does not have permission to access this page or perform this action.
                </p>

                <?php if (!empty($requiredRole)): ?>
                <div class="alert alert-light border small mb-4">
                    <strong>Required role:</strong>
                    <?= e(\AAM\Helpers\StringHelper::titleCase($requiredRole)) ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($requiredPermission)): ?>
                <div class="alert alert-light border small mb-4">
                    <strong>Required permission:</strong>
                    <code><?= e($requiredPermission) ?></code>
                </div>
                <?php endif; ?>

                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a href="<?= url('dashboard') ?>" class="btn btn-primary">
                        <i class="bi bi-house me-1"></i>Back to Dashboard
                    </a>
                    <button onclick="history.back()" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Go Back
                    </button>
                </div>

                <p class="text-muted small mt-4">
                    If you believe this is a mistake, contact your
                    <strong>System Administrator</strong>.
                </p>

            </div>
        </div>
    </div>
</div>
