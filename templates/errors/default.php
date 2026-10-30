<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : templates/errors/default.php
 * Purpose  : Generic error page fallback.
 *            Used for any HTTP error without a specific
 *            template (e.g. 405, 422, 503).
 * =========================================================
 *
 * @var int    $code
 * @var string $title
 * @var string $message
 */
declare(strict_types=1);

$appName = 'Arsi Agri-Manage';
$appUrl  = 'http://localhost/aam/app';
try {
    if (class_exists('\\AAM\\Config\\Application')) {
        $appName = \AAM\Config\Application::get('name', $appName);
        $appUrl  = rtrim((string)\AAM\Config\Application::get('url', $appUrl), '/');
    }
} catch (\Throwable $ignored) {}

$code    = $code    ?? 500;
$title   = $title   ?? 'An Error Occurred';
$message = $message ?? 'An unexpected error occurred. Please try again.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= (int)$code ?> <?= htmlspecialchars($title) ?> | <?= htmlspecialchars($appName) ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f4f6fb;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #212529;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,.1);
            max-width: 520px;
            width: 100%;
            padding: 3rem 2.5rem;
            text-align: center;
        }
        .code { font-size: 5rem; font-weight: 900; color: #6c757d; line-height: 1; margin-bottom: .5rem; }
        h1   { font-size: 1.4rem; font-weight: 700; margin-bottom: .75rem; }
        p    { color: #6c757d; margin-bottom: 1.5rem; }
        .btn {
            display: inline-block;
            padding: .5rem 1.25rem;
            background: #0d6efd;
            color: #fff;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: .875rem;
            margin: .25rem;
        }
        .btn-secondary { background: #6c757d; }
        .footer-note { margin-top: 2rem; font-size: .78rem; color: #adb5bd; }
    </style>
</head>
<body>
<div class="card" role="main">
    <div class="code" aria-label="Error code <?= (int)$code ?>"><?= (int)$code ?></div>
    <h1><?= htmlspecialchars($title) ?></h1>
    <p><?= htmlspecialchars($message) ?></p>
    <div>
        <a href="<?= htmlspecialchars($appUrl) ?>/dashboard" class="btn">
            Go to Dashboard
        </a>
        <button onclick="history.back()" class="btn btn-secondary" style="cursor:pointer;border:none;">
            Go Back
        </button>
    </div>
    <p class="footer-note">
        <strong><?= htmlspecialchars($appName) ?></strong> &mdash; Arsi University
    </p>
</div>
</body>
</html>
