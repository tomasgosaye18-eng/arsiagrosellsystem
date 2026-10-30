<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : templates/errors/403.php
 * Purpose  : 403 Forbidden / Access Denied error page.
 *            Shown when a user does not have permission
 *            to access a route or resource.
 * =========================================================
 *
 * @var int    $code
 * @var string $title
 * @var string $message
 */
declare(strict_types=1);
$appName = \AAM\Config\Application::get('name', 'Arsi Agri-Manage');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Access Denied | <?= htmlspecialchars($appName) ?></title>
    <link rel="stylesheet" href="<?= rtrim(\AAM\Config\Application::get('url', ''), '/') ?>/public/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= rtrim(\AAM\Config\Application::get('url', ''), '/') ?>/public/assets/css/bootstrap-icons.css">
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f4f6fb; }
        .error-card { max-width: 500px; width: 100%; }
        .error-icon { font-size: 5rem; color: #ffc107; }
    </style>
</head>
<body>
<div class="error-card text-center p-4">
    <i class="bi bi-shield-lock error-icon d-block mb-3" aria-hidden="true"></i>
    <h1 class="h2 fw-bold text-dark">403 — Access Denied</h1>
    <p class="text-muted mb-4">
        <?= htmlspecialchars($message ?? 'You do not have permission to access this page.') ?>
    </p>
    <div class="d-flex gap-2 justify-content-center flex-wrap">
        <?php if (\AAM\Core\SessionManager::isAuthenticated()): ?>
            <a href="<?= rtrim(\AAM\Config\Application::get('url', ''), '/') ?>/dashboard"
               class="btn btn-primary">
                <i class="bi bi-house me-1"></i>Back to Dashboard
            </a>
        <?php endif; ?>
        <a href="<?= rtrim(\AAM\Config\Application::get('url', ''), '/') ?>/login"
           class="btn btn-outline-secondary">
            <i class="bi bi-box-arrow-in-right me-1"></i>Login Page
        </a>
    </div>
    <p class="text-muted small mt-4">
        <strong><?= htmlspecialchars($appName) ?></strong> &mdash; Arsi University
    </p>
</div>
</body>
</html>
