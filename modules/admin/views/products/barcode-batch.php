<?php
/**
 * =========================================================
 * BARCODE BATCH PRINT
 * =========================================================
 * File    : modules/admin/views/products/barcode-batch.php
 * Layout  : print
 * Purpose : Print multiple barcode labels in one page
 * Deps    : BarcodeController
 * =========================================================
 * @var array  $products  Each has 'barcode_svg' pre-rendered
 * @var string $size      small | medium | large
 */
declare(strict_types=1);
$dims = ['small'=>160,'medium'=>220,'large'=>300];
$w    = $dims[$size] ?? 220;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Batch Barcode Print (<?= count($products) ?> labels)</title>
  <style>
    *  { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, sans-serif; background: #fff; }
    .no-print { background: #f8f9fa; border-bottom: 1px solid #dee2e6;
                padding: 10px 16px; display: flex; gap: 10px; align-items: center; }
    .no-print button { padding: 5px 14px; border-radius: 4px; border: 1px solid #aaa;
                       cursor: pointer; font-size: 13px; background: #fff; }
    .no-print .btn-primary { background: #0d6efd; color: #fff; border-color: #0d6efd; }
    .grid { display: flex; flex-wrap: wrap; gap: 8px; padding: 12px; }
    .label { width: <?= $w ?>px; border: 1px solid #ccc; border-radius: 6px;
             padding: 8px; text-align: center; page-break-inside: avoid; }
    .label .pname { font-weight: 700; font-size: 11px; margin-bottom: 2px;
                    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .label .pcode { font-family: monospace; font-size: 10px; color: #666; margin-bottom: 5px; }
    .label .bsvg  { margin: 0 auto; }
    .label .bnum  { font-family: monospace; font-size: 11px; margin-top: 3px; }
    .label .price { font-weight: 700; font-size: 12px; margin-top: 4px; color: #198754; }
    @media print {
      .no-print { display: none !important; }
      .grid { padding: 3mm; gap: 4mm; }
      .label { border: 1px solid #888; }
    }
  </style>
</head>
<body>
  <div class="no-print">
    <strong>Batch Barcode Print</strong>
    <span style="color:#666;font-size:13px;"><?= count($products) ?> label(s)</span>
    <button onclick="window.close()">✕ Close</button>
    <button class="btn-primary" onclick="window.print()">🖨 Print All</button>
  </div>
  <div class="grid">
    <?php foreach ($products as $p): ?>
    <div class="label">
      <div class="pname" title="<?= e($p['product_name']) ?>">
        <?= e(mb_strimwidth($p['product_name'], 0, 28, '…')) ?>
      </div>
      <div class="pcode"><?= e($p['product_code']) ?></div>
      <div class="bsvg"><?= $p['barcode_svg'] ?? '' ?></div>
      <div class="bnum"><?= e($p['barcode']) ?></div>
      <?php if (!empty($p['selling_price'])): ?>
      <div class="price">ETB <?= number_format((float)$p['selling_price'], 2) ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</body>
</html>
