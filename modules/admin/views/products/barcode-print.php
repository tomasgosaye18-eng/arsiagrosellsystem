<?php
/**
 * =========================================================
 * BARCODE PRINT PAGE
 * =========================================================
 * File    : modules/admin/views/products/barcode-print.php
 * Layout  : print (bare)
 * Purpose : Printable barcode label for a single product
 * Deps    : BarcodeController
 * =========================================================
 */
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Barcode – <?= e($product['product_name']) ?></title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, sans-serif; background: #fff; color: #000; }
    .no-print { background: #f8f9fa; border-bottom: 1px solid #dee2e6; padding: 12px 20px;
                display: flex; justify-content: space-between; align-items: center; gap: 10px; }
    .no-print .btn { padding: 6px 16px; border-radius: 4px; border: 1px solid #aaa;
                     cursor: pointer; font-size: 14px; background: #fff; }
    .no-print .btn-primary { background: #0d6efd; color: #fff; border-color: #0d6efd; }
    .print-area { padding: 20px; display: flex; flex-wrap: wrap; gap: 12px; }
    .label { border: 1px solid #ccc; border-radius: 6px; padding: 12px 16px;
             text-align: center; page-break-inside: avoid; }
    .label.small-size  { width: 200px; }
    .label.medium-size { width: 280px; }
    .label.large-size  { width: 360px; }
    .label .product-name { font-weight: bold; font-size: 13px; margin-bottom: 4px; }
    .label .product-code { font-family: monospace; font-size: 11px; color: #666; margin-bottom: 8px; }
    .label .barcode-svg  { margin: 0 auto 4px; }
    .label .barcode-text { font-family: monospace; font-size: 13px; margin-top: 4px; }
    .label .price        { font-weight: bold; font-size: 16px; margin-top: 6px; }
    .label .category     { font-size: 10px; color: #888; }
    @media print {
      .no-print { display: none !important; }
      .print-area { padding: 4mm; gap: 4mm; }
      .label { border: 1px solid #999; break-inside: avoid; }
      body { background: #fff; }
    }
    .size-btns button { padding: 4px 12px; margin-right: 4px; border-radius: 4px;
                        border: 1px solid #aaa; cursor: pointer; font-size: 13px; }
    .size-btns button.active { background: #0d6efd; color: #fff; border-color: #0d6efd; }
  </style>
</head>
<body>
  <!-- Controls (hidden when printing) -->
  <div class="no-print">
    <div>
      <strong><i class="bi bi-printer"></i> Barcode Print</strong>
      <span style="margin-left:12px;color:#666;font-size:13px;"><?= e($product['product_name']) ?></span>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span style="font-size:13px;">Label size:</span>
      <div class="size-btns">
        <button onclick="setSize('small')" id="btn-small">Small</button>
        <button onclick="setSize('medium')" id="btn-medium" class="active">Medium</button>
        <button onclick="setSize('large')" id="btn-large">Large</button>
      </div>
      <button class="btn" onclick="window.close()">✕ Close</button>
      <button class="btn btn-primary" onclick="window.print()">🖨 Print</button>
    </div>
  </div>

  <!-- Print Area -->
  <div class="print-area" id="printArea">
    <div class="label medium-size" id="labelCard">
      <div class="product-name"><?= e($product['product_name']) ?></div>
      <div class="product-code"><?= e($product['product_code']) ?></div>
      <?php if ($product['category_name']): ?>
      <div class="category"><?= e($product['category_name']) ?></div>
      <?php endif; ?>
      <div class="barcode-svg">
        <?= $barcode_svg ?>
      </div>
      <div class="barcode-text"><?= e($product['barcode']) ?></div>
      <?php if ($product['selling_price'] > 0): ?>
      <div class="price">ETB <?= number_format((float)$product['selling_price'], 2) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <script>
  function setSize(s) {
    document.getElementById('labelCard').className = 'label ' + s + '-size';
    ['small','medium','large'].forEach(function(x) {
      document.getElementById('btn-'+x).className = x === s ? 'active' : '';
    });
  }

  // Quantity: allow user to duplicate label for printing multiple copies
  document.addEventListener('keydown', function (e) {
    if (e.ctrlKey && e.key === 'p') {
      setTimeout(function () { window.print(); }, 100);
    }
  });
  </script>
</body>
</html>
