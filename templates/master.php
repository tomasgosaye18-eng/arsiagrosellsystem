<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : templates/master.php
 * Purpose  : Master layout template.
 *            Every authenticated page is rendered inside
 *            this HTML shell.
 *            Includes: head, topbar, sidebar, content, footer.
 * =========================================================
 *
 * @var \AAM\Core\View            $view
 * @var string                    $content   Rendered view content
 * @var string                    $appName
 * @var string                    $appUrl
 * @var array<string,mixed>|null  $currentUser
 * @var string|null               $userRole
 * @var string                    $csrfToken
 * @var string                    $pageTitle   Optional — set in each view
 */

declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Arsi Agri-Manage — Agricultural Product Management System">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">

    <title><?= e($pageTitle) ?> | <?= e($appName) ?></title>

    <link rel="icon" type="image/svg+xml" href="<?= $view->asset('images/favicon.svg') ?>">

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="<?= $view->asset('css/bootstrap.min.css') ?>">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="<?= $view->asset('css/bootstrap-icons.css') ?>">
    <!-- Custom Application CSS -->
    <link rel="stylesheet" href="<?= $view->asset('css/app.css') ?>">
    <!-- Theme CSS (light/dark mode layer — loaded after app.css) -->
    <link rel="stylesheet" href="<?= $view->asset('css/theme.css') ?>">

    <?php $view->yield('styles'); ?>

    <!--
        NO-FLASH THEME SCRIPT
        Must run synchronously in <head> BEFORE the page paints.
        Sets data-theme on <html> from localStorage / OS preference
        so the correct CSS variables are active from the first paint.
        Kept intentionally minimal — no DOM manipulation here.
    -->
    <script>
    (function(){
        var k='aam_theme', s=null;
        try{ s=localStorage.getItem(k); }catch(e){}
        var t=(s==='dark'||s==='light')?s:
              (window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');
        document.documentElement.setAttribute('data-theme', t);
    })();
    </script>
</head>
<body>

<!-- Skip to main content (accessibility) -->
<a href="#main-content" class="skip-link visually-hidden-focusable">Skip to main content</a>

<?php $view->include('topbar', compact('currentUser', 'userRole', 'appName', 'view', 'csrfToken')); ?>

<div class="d-flex" id="wrapper">

    <?php $view->include('sidebar', compact('currentUser', 'userRole', 'view')); ?>

    <!-- ─── Main Content ────────────────────────────────────────────── -->
    <div id="page-content-wrapper" class="flex-grow-1">

        <?php $view->include('breadcrumb'); ?>

        <main class="container-fluid py-4 px-4" id="main-content" role="main">

            <!-- Flash Message -->
            <?php $view->flash(); ?>

            <!-- Page Content (rendered by controller) -->
            <?= $content ?>

        </main>

        <?php $view->include('footer', compact('appName', 'view')); ?>

    </div><!-- /#page-content-wrapper -->

</div><!-- /#wrapper -->

<!-- Bootstrap 5 JS Bundle (includes Popper) -->
<script src="<?= $view->asset('js/bootstrap.bundle.min.js') ?>"></script>
<!-- Chart.js -->
<script src="<?= $view->asset('js/chart.min.js') ?>"></script>
<!-- Global Application JS -->
<script src="<?= $view->asset('js/app.js') ?>"></script>
<!-- Theme System (light/dark toggle — loaded after app.js and chart.min.js) -->
<script src="<?= $view->asset('js/theme.js') ?>"></script>

<!-- CSRF token for AJAX requests -->
<script>
    window.AAM = window.AAM || {};
    window.AAM.csrfToken = '<?= e($csrfToken) ?>';
    window.AAM.appUrl    = '<?= e($appUrl) ?>';
    window.AAM.userRole  = '<?= e($userRole ?? '') ?>';
</script>

<?php $view->yield('scripts'); ?>

</body>
</html>
