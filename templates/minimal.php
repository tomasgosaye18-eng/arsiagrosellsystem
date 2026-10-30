<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : templates/minimal.php
 * Purpose  : Minimal layout — used for:
 *              - Login page
 *              - Change Password (forced)
 *            No sidebar, no topbar, no breadcrumb.
 *            Centered card layout.
 * =========================================================
 *
 * @var \AAM\Core\View $view
 * @var string         $content     Rendered view content
 * @var string         $appName
 * @var string         $appUrl
 * @var string         $csrfToken
 * @var string         $pageTitle
 */
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Login';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">

    <title><?= e($pageTitle) ?> | <?= e($appName) ?></title>

    <link rel="icon" type="image/svg+xml" href="<?= $view->asset('images/favicon.svg') ?>">
    <link rel="stylesheet" href="<?= $view->asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= $view->asset('css/bootstrap-icons.css') ?>">
    <!-- login.css is scoped to .aam-login-page — does not affect other pages -->
    <link rel="stylesheet" href="<?= $view->asset('css/login.css') ?>">

    <!-- No-flash theme detection (identical to master.php) -->
    <script>
    (function(){
        var k='aam_theme', s=null;
        try{ s=localStorage.getItem(k); }catch(e){}
        var t=(s==='dark'||s==='light')?s:
              (window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');
        document.documentElement.setAttribute('data-theme', t);
    })();
    </script>

    <?php $view->yield('styles'); ?>
</head>
<body>

<!-- ===================================================
     .aam-login-page wraps EVERYTHING so no styles leak
     =================================================== -->
<div class="aam-login-page">
<div class="aam-login-wrapper" role="main">

    <!-- ── Login Card ─────────────────────────────────── -->
    <div class="aam-login-card">

        <!-- ── Header ────────────────────────────────── -->
        <div class="aam-login-header">

            <!-- Logo -->
            <div class="aam-login-logo" aria-hidden="true">
                <img src="<?= $view->asset('images/favicon.svg') ?>"
                     alt="AAM Logo"
                     width="34" height="34"
                     onerror="this.replaceWith(document.createTextNode('🌾'))">
            </div>

            <!-- App name + university -->
            <h1 class="aam-login-app-name"><?= e($appName) ?></h1>
            <p class="aam-login-tagline">Arsi University &middot; Agricultural Management System</p>

            <!-- Compact trust badges -->
            <div class="aam-login-header-badges" aria-hidden="true">
                <span class="aam-login-badge">
                    <i class="bi bi-shield-lock-fill me-1"></i>Secure Access
                </span>
                <span class="aam-login-badge">
                    <i class="bi bi-building me-1"></i>Enterprise
                </span>
            </div>
        </div>
        <!-- /.aam-login-header -->

        <!-- ── Flash / Validation Alerts ─────────────── -->
        <div class="aam-login-alerts" id="aam-login-alerts" aria-live="polite">
            <?php $view->flash(); ?>
        </div>

        <!-- ── Page Content (the form) ───────────────── -->
        <div class="aam-login-body">
            <?= $content ?>
        </div>
        <!-- /.aam-login-body -->

        <!-- ── Footer ────────────────────────────────── -->
        <div class="aam-login-footer">
            &copy; <?= date('Y') ?> <?= e($appName) ?> &mdash; Arsi University. All rights reserved.
        </div>

    </div><!-- /.aam-login-card -->

</div><!-- /.aam-login-wrapper -->
</div><!-- /.aam-login-page -->

<script src="<?= $view->asset('js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= $view->asset('js/validation.js') ?>"></script>

<script>
    window.AAM = window.AAM || {};
    window.AAM.csrfToken = '<?= e($csrfToken) ?>';
    window.AAM.appUrl    = '<?= e($appUrl) ?>';
</script>

<?php $view->yield('scripts'); ?>

</body>
</html>
