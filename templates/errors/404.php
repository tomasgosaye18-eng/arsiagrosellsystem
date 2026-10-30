<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : templates/errors/404.php
 * Purpose  : 404 Not Found error page.
 * =========================================================
 *
 * @var int    $code
 * @var string $title
 * @var string $message
 */
declare(strict_types=1);
$appName = \AAM\Config\Application::get('name', 'Arsi Agri-Manage');
$appUrl  = rtrim(\AAM\Config\Application::get('url', ''), '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found | <?= htmlspecialchars($appName) ?></title>
    <link rel="stylesheet" href="<?= $appUrl ?>/public/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $appUrl ?>/public/assets/css/bootstrap-icons.css">
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f4f6fb; }
        .error-code { font-size: 6rem; font-weight: 900; color: #dee2e6; line-height: 1; }
    </style>
</head>
<body>
<div class="text-center p-4" style="max-width:520px;width:100%;">
    <div class="error-code" aria-hidden="true">404</div>
    <i class="bi bi-search display-4 text-muted d-block mb-3" aria-hidden="true"></i>
    <h1 class="h3 fw-bold text-dark">Page Not Found</h1>
    <p class="text-muted mb-4">
        <?= htmlspecialchars($message ?? 'The page you requested does not exist or has been moved.') ?>
    </p>
    <div class="d-flex gap-2 justify-content-center flex-wrap">
        <?php if (\AAM\Core\SessionManager::isAuthenticated()): ?>
            <a href="<?= $appUrl ?>/dashboard" class="btn btn-primary">
                <i class="bi bi-house me-1"></i>Back to Dashboard
            </a>
        <?php else: ?>
            <a href="<?= $appUrl ?>/login" class="btn btn-primary">
                <i class="bi bi-box-arrow-in-right me-1"></i>Login
            </a>
        <?php endif; ?>
        <button onclick="history.back()" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Go Back
        </button>
    </div>
    <p class="text-muted small mt-4">
        <strong><?= htmlspecialchars($appName) ?></strong> &mdash; Arsi University
    </p>
</div>
</body>
</html>
