<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : templates/print.php
 * Purpose  : Print layout — used for:
 *              - Receipt printing
 *              - Report PDF/print views
 *              - Dispatch notes
 *              - Transfer confirmation sheets
 *            Minimal HTML, no navigation, print-optimized.
 * =========================================================
 *
 * @var \AAM\Core\View $view
 * @var string         $content
 * @var string         $appName
 * @var string         $pageTitle
 */
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Print';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">

    <title><?= e($pageTitle) ?> | <?= e($appName) ?></title>

    <link rel="stylesheet" href="<?= $view->asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= $view->asset('css/bootstrap-icons.css') ?>">
    <link rel="stylesheet" href="<?= $view->asset('css/print.css') ?>">

    <style>
        /* ── Screen preview styles ─────────────────────────── */
        body {
            background: #e8ecf0;
            margin: 0;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            font-size: 13px;
        }

        .print-page {
            background: #fff;
            width: 80mm;         /* Thermal receipt width */
            max-width: 100%;
            margin: 0 auto;
            padding: 12mm;
            box-shadow: 0 2px 20px rgba(0,0,0,.12);
            border-radius: 4px;
        }

        /* A4 variant — used by reports */
        .print-page.a4 {
            width: 210mm;
            min-height: 297mm;
            padding: 15mm 20mm;
        }

        .print-header {
            text-align: center;
            border-bottom: 2px dashed #333;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .print-header .org-name {
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .print-header .sys-name {
            font-size: 11px;
            color: #555;
        }

        .print-footer {
            border-top: 2px dashed #333;
            margin-top: 10px;
            padding-top: 8px;
            text-align: center;
            font-size: 11px;
            color: #666;
        }

        .print-actions {
            text-align: center;
            margin-top: 20px;
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        /* ── Print media query ─────────────────────────────── */
        @media print {
            body { background: none; padding: 0; }
            .print-page { box-shadow: none; border-radius: 0; margin: 0; width: 100%; }
            .print-actions { display: none !important; }
            .no-print { display: none !important; }

            /* Force black ink */
            * { color: #000 !important; background: transparent !important; }
            a { text-decoration: none; }
        }
    </style>

    <?php $view->yield('styles'); ?>
</head>
<body>

<!-- Print Action Buttons (hidden on actual print) -->
<div class="print-actions no-print">
    <button onclick="window.print()" class="btn btn-primary btn-sm">
        <i class="bi bi-printer me-1"></i>Print
    </button>
    <button onclick="window.close()" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-x-lg me-1"></i>Close
    </button>
</div>

<!-- Printable Content -->
<div class="print-page" id="printArea">

    <!-- Standard Header -->
    <div class="print-header">
        <div class="org-name">Arsi University</div>
        <div class="sys-name"><?= e($appName) ?></div>
        <div style="font-size:10px;color:#888;margin-top:3px;">
            <?= date('d/m/Y H:i') ?>
        </div>
    </div>

    <!-- Page-Specific Content -->
    <?= $content ?>

    <!-- Standard Footer -->
    <div class="print-footer">
        <p style="margin:0;">Thank you</p>
        <p style="margin:0;font-size:10px;">This is a system-generated document</p>
        <p style="margin:0;font-size:10px;"><?= e($appName) ?> &mdash; Arsi University</p>
    </div>

</div><!-- /.print-page -->

<script src="<?= $view->asset('js/bootstrap.bundle.min.js') ?>"></script>

<?php $view->yield('scripts'); ?>

<!-- Auto-print if ?autoprint=1 in URL -->
<script>
    if (new URLSearchParams(window.location.search).get('autoprint') === '1') {
        window.addEventListener('load', function() { window.print(); });
    }
</script>

</body>
</html>
