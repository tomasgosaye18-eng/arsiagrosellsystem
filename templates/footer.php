<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : templates/footer.php
 * Purpose  : Page footer component.
 *            Version info, copyright, quick links.
 * =========================================================
 *
 * @var \AAM\Core\View $view
 * @var string         $appName
 */
declare(strict_types=1);

$year    = date('Y');
$version = \AAM\Config\Application::get('version', '1.0.0');
$env     = \AAM\Config\Environment::current();
?>
<footer class="footer mt-auto py-3 bg-light border-top" role="contentinfo">
    <div class="container-fluid px-4">
        <div class="row align-items-center">

            <div class="col-md-6 text-muted small">
                &copy; <?= $year ?> <?= e($appName ?? 'Arsi Agri-Manage') ?> &mdash; Arsi University.
                All rights reserved.
            </div>

            <div class="col-md-6 text-md-end text-muted small">
            </div>

        </div>
    </div>
</footer>
