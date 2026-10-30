<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : templates/errors/500.php
 * Purpose  : 500 Internal Server Error page.
 *            Shown on unhandled exceptions in production.
 *            Never shows technical details to the user.
 * =========================================================
 *
 * @var int    $code
 * @var string $title
 * @var string $message
 */
declare(strict_types=1);

// Safe fallback — Application class may not be loaded if error is very early
$appName = 'Arsi Agri-Manage';
$appUrl  = 'http://localhost/aam/app';
try {
    if (class_exists('\\AAM\\Config\\Application')) {
        $appName = \AAM\Config\Application::get('name', $appName);
        $appUrl  = rtrim((string)\AAM\Config\Application::get('url', $appUrl), '/');
    }
} catch (\Throwable $ignored) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 Server Error | <?= htmlspecialchars($appName) ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
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
        .code { font-size: 5rem; font-weight: 900; color: #dc3545; line-height: 1; margin-bottom: .5rem; }
        h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: .75rem; }
        p  { color: #6c757d; margin-bottom: 1.5rem; line-height: 1.6; }
        .btn {
            display: inline-block;
            padding: .5rem 1.25rem;
            background: #0d6efd;
            color: #fff;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: .875rem;
        }
        .btn:hover { background: #0a58ca; }
        .footer-note { margin-top: 2rem; font-size: .78rem; color: #adb5bd; }
    </style>
</head>
<body>
<div class="card" role="main">
    <div class="code" aria-label="Error 500">500</div>
    <h1>Something Went Wrong</h1>
    <p>
        An unexpected server error occurred. Our team has been notified.
        Please try again in a few minutes. If the problem persists, contact your System Administrator.
    </p>
    <a href="<?= htmlspecialchars($appUrl) ?>/login" class="btn">
        Return to Login
    </a>
    <p class="footer-note">
        <strong><?= htmlspecialchars($appName) ?></strong> &mdash; Arsi University
    </p>
</div>
</body>
</html>
