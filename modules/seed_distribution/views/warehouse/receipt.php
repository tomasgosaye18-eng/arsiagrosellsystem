<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Seed Distribution Receipt
 * =========================================================
 * Layout  : print  (templates/print.php)
 * Route   : GET /seed-distribution/{id}/receipt
 * Access  : ROLE_WAREHOUSE_MANAGER
 */
declare(strict_types=1);

$dist      ??= [];
$warehouse ??= [];
$appName   ??= 'AU-AgroSale';

$unitPrice  = (float)($dist['unit_price']   ?? 0);
$totalAmt   = (float)($dist['total_amount'] ?? 0);
$qty        = (float)($dist['quantity']     ?? 0);
$unitAbbr   = $dist['unit_abbr'] ?? 'unit';

$distNum    = $dist['distribution_number'] ?? '';
$receiptNum = 'RCP-' . ltrim(str_replace('SEED-', '', $distNum), '0');

$issuedAt = !empty($dist['released_at'])
    ? date('d M Y, H:i', strtotime($dist['released_at']))
    : (!empty($dist['created_at'])
        ? date('d M Y, H:i', strtotime($dist['created_at']))
        : date('d M Y, H:i'));

$distDate = !empty($dist['distribution_date'])
    ? date('d M Y', strtotime($dist['distribution_date']))
    : '—';

$statusLabels = [
    'pending'   => 'Pending Approval',
    'approved'  => 'Approved',
    'released'  => 'Released',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
    'rejected'  => 'Rejected',
];
$statusLabel = $statusLabels[$dist['status'] ?? ''] ?? ucfirst($dist['status'] ?? '');
$isCompleted = in_array($dist['status'] ?? '', ['released','completed'], true);

$whName = $dist['warehouse_name'] ?? ($warehouse['name'] ?? 'Warehouse');
$whCode = $dist['warehouse_code'] ?? ($warehouse['warehouse_code'] ?? '');

$issuedBy = trim($dist['released_by_name'] ?? $dist['created_by_name'] ?? '—');
$farmerName = $dist['recipient_name'] ?? '—';
?>

<?php $view->section('styles'); ?>
<style>
  /* Override print-page width for A4-style receipt */
  .print-page {
    width: 160mm !important;
    max-width: 100% !important;
    padding: 10mm 12mm !important;
  }

  .receipt-org-header {
    text-align: center;
    padding-bottom: 10px;
    margin-bottom: 12px;
    border-bottom: 3px double #333;
  }
  .receipt-org-header .uni-name {
    font-size: 15px;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
  }
  .receipt-org-header .sys-name {
    font-size: 11px;
    color: #444;
    margin-top: 1px;
  }
  .receipt-title {
    text-align: center;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    background: #222;
    color: #fff;
    padding: 5px 0;
    margin: 8px 0;
    border-radius: 2px;
  }
  .receipt-meta-row {
    display: flex;
    justify-content: space-between;
    font-size: 10px;
    margin-bottom: 2px;
  }
  .receipt-meta-row .label { color: #666; }
  .receipt-meta-row .value { font-weight: 600; text-align: right; }
  .receipt-divider {
    border: none;
    border-top: 1px dashed #999;
    margin: 8px 0;
  }
  .receipt-divider-solid {
    border: none;
    border-top: 1px solid #333;
    margin: 8px 0;
  }
  .receipt-section-title {
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: #888;
    margin-bottom: 4px;
  }
  .receipt-items-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
  }
  .receipt-items-table thead tr {
    border-bottom: 2px solid #333;
  }
  .receipt-items-table thead th {
    padding: 3px 4px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .03em;
  }
  .receipt-items-table tbody tr {
    border-bottom: 1px dashed #ddd;
  }
  .receipt-items-table tbody td {
    padding: 5px 4px;
    vertical-align: top;
  }
  .receipt-items-table tfoot tr {
    border-top: 2px solid #333;
  }
  .receipt-items-table tfoot td {
    padding: 6px 4px;
    font-weight: 700;
    font-size: 13px;
  }
  .product-name-cell { font-weight: 700; }
  .product-meta-cell { font-size: 9px; color: #777; margin-top: 1px; }
  .text-right { text-align: right; }
  .badge-status {
    display: inline-block;
    padding: 1px 6px;
    border-radius: 3px;
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
  }
  .receipt-watermark {
    text-align: center;
    font-size: 8px;
    color: #bbb;
    margin-top: 10px;
    letter-spacing: .04em;
  }
  .total-highlight {
    background: #f8f9fa;
    padding: 2px 4px;
    border-radius: 2px;
  }

  @media print {
    .print-page { width: 100% !important; }
    .no-print { display: none !important; }
  }
</style>
<?php $view->endSection(); ?>

<!-- ── Organization Header ──────────────────────────── -->
<div class="receipt-org-header">
  <div class="uni-name">Arsi University</div>
  <div class="sys-name"><?= e($appName) ?> — Agricultural Input Management</div>
  <?php if ($whName): ?>
  <div style="font-size:10px;color:#555;margin-top:3px;">
    <?= e($whName) ?><?= $whCode ? ' &nbsp;|&nbsp; ' . e($whCode) : '' ?>
  </div>
  <?php endif; ?>
</div>

<!-- ── Receipt Title ─────────────────────────────────── -->
<div class="receipt-title">&#127807; Seed Distribution Receipt</div>

<!-- ── Receipt Reference Rows ───────────────────────── -->
<div style="margin-bottom:8px;">
  <div class="receipt-meta-row">
    <span class="label">Receipt Number</span>
    <span class="value" style="font-family:monospace;"><?= e($receiptNum) ?></span>
  </div>
  <div class="receipt-meta-row">
    <span class="label">Distribution #</span>
    <span class="value" style="font-family:monospace;font-weight:800;"><?= e($distNum) ?></span>
  </div>
  <div class="receipt-meta-row">
    <span class="label">Date / Time</span>
    <span class="value"><?= $issuedAt ?></span>
  </div>
  <div class="receipt-meta-row">
    <span class="label">Distribution Date</span>
    <span class="value"><?= $distDate ?></span>
  </div>
  <div class="receipt-meta-row">
    <span class="label">Status</span>
    <span class="value"><span class="badge-status"><?= $statusLabel ?></span></span>
  </div>
  <div class="receipt-meta-row">
    <span class="label">Issued By</span>
    <span class="value"><?= e($issuedBy) ?></span>
  </div>
  <div class="receipt-meta-row">
    <span class="label">Warehouse</span>
    <span class="value"><?= e($whName) ?><?= $whCode ? ' (' . e($whCode) . ')' : '' ?></span>
  </div>
</div>

<hr class="receipt-divider">

<!-- ── Farmer / Recipient ────────────────────────────── -->
<div class="receipt-section-title">&#128100; Farmer / Recipient</div>
<div style="margin-bottom:8px;">
  <div class="receipt-meta-row">
    <span class="label">Name</span>
    <span class="value" style="font-weight:700;"><?= e($farmerName) ?></span>
  </div>
  <?php if (!empty($dist['recipient_phone'])): ?>
  <div class="receipt-meta-row">
    <span class="label">Phone</span>
    <span class="value"><?= e($dist['recipient_phone']) ?></span>
  </div>
  <?php endif; ?>
  <?php if (!empty($dist['recipient_organization'])): ?>
  <div class="receipt-meta-row">
    <span class="label">Organization</span>
    <span class="value"><?= e($dist['recipient_organization']) ?></span>
  </div>
  <?php endif; ?>
  <?php if (!empty($dist['destination_location'])): ?>
  <div class="receipt-meta-row">
    <span class="label">Location</span>
    <span class="value"><?= e($dist['destination_location']) ?></span>
  </div>
  <?php endif; ?>
  <?php if (!empty($dist['program_name'])): ?>
  <div class="receipt-meta-row">
    <span class="label">Program</span>
    <span class="value"><?= e($dist['program_name']) ?></span>
  </div>
  <?php endif; ?>
  <div class="receipt-meta-row">
    <span class="label">Dist. Type</span>
    <span class="value">
      <?= $dist['distribution_type'] === 'warehouse_pickup' ? 'Warehouse Pickup' : 'Field Distribution' ?>
    </span>
  </div>
</div>

<hr class="receipt-divider">

<!-- ── Seed Items Table ──────────────────────────────── -->
<div class="receipt-section-title">&#127807; Seed Product Details</div>
<table class="receipt-items-table">
  <thead>
    <tr>
      <th style="text-align:left;">Product</th>
      <th style="text-align:center;">Qty</th>
      <th style="text-align:right;">Unit Price</th>
      <th style="text-align:right;">Total</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>
        <div class="product-name-cell"><?= e($dist['product_name'] ?? '—') ?></div>
        <?php if (!empty($dist['grade_name'])): ?>
        <div class="product-meta-cell">
          Grade: <?= e($dist['grade_name']) ?>
          <?= !empty($dist['grade_code']) ? ' (' . e($dist['grade_code']) . ')' : '' ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($dist['product_sku'])): ?>
        <div class="product-meta-cell">SKU: <?= e($dist['product_sku']) ?></div>
        <?php endif; ?>
      </td>
      <td style="text-align:center;">
        <?= number_format($qty, 3) ?><br>
        <span style="font-size:9px;color:#888;"><?= e($unitAbbr) ?></span>
      </td>
      <td style="text-align:right;">
        <?= $unitPrice > 0 ? 'ETB ' . number_format($unitPrice, 2) : '—' ?><br>
        <?php if ($unitPrice > 0): ?>
        <span style="font-size:9px;color:#888;">/ <?= e($unitAbbr) ?></span>
        <?php endif; ?>
      </td>
      <td style="text-align:right;font-weight:700;">
        <?= $totalAmt > 0 ? 'ETB ' . number_format($totalAmt, 2) : '—' ?>
      </td>
    </tr>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="3" style="text-align:right;font-size:11px;">TOTAL AMOUNT</td>
      <td class="text-right total-highlight" style="font-size:15px;color:#000;">
        ETB <?= number_format($totalAmt, 2) ?>
      </td>
    </tr>
  </tfoot>
</table>

<!-- Calculation note -->
<?php if ($unitPrice > 0 && $qty > 0): ?>
<div style="font-size:9px;color:#888;text-align:right;margin-top:3px;">
  <?= number_format($qty, 3) ?> <?= e($unitAbbr) ?> &times; ETB <?= number_format($unitPrice, 2) ?> = ETB <?= number_format($totalAmt, 2) ?>
</div>
<?php endif; ?>

<hr class="receipt-divider-solid" style="margin-top:10px;">

<!-- ── Notes ────────────────────────────────────────── -->
<?php if (!empty($dist['notes'])): ?>
<div style="font-size:10px;color:#555;margin-bottom:8px;">
  <strong>Notes:</strong> <?= e($dist['notes']) ?>
</div>
<?php endif; ?>

<!-- ── Price Lock Notice ─────────────────────────────── -->
<div style="text-align:center;font-size:9px;color:#999;margin:6px 0;padding:4px;border:1px dashed #ddd;border-radius:2px;">
  Unit price is locked at the time of distribution and will not change if prices are updated later.
</div>

<!-- ── Signature Lines ───────────────────────────────── -->
<div style="display:flex;justify-content:space-between;margin-top:14px;font-size:10px;">
  <div style="text-align:center;width:42%;">
    <div style="border-top:1px solid #333;padding-top:4px;margin-top:24px;">
      Issued By
    </div>
    <div style="color:#555;"><?= e($issuedBy) ?></div>
  </div>
  <div style="text-align:center;width:42%;">
    <div style="border-top:1px solid #333;padding-top:4px;margin-top:24px;">
      Received By (Farmer Signature)
    </div>
    <div style="color:#555;"><?= e($farmerName) ?></div>
  </div>
</div>

<!-- ── Watermark ─────────────────────────────────────── -->
<div class="receipt-watermark">
  This is an official seed distribution receipt &mdash; <?= e($appName) ?> &mdash; Arsi University<br>
  Generated: <?= date('d/m/Y H:i:s') ?>
</div>

<!-- ── Action Buttons (screen only) ─────────────────── -->
<div class="no-print" style="margin-top:18px;display:flex;gap:8px;flex-wrap:wrap;justify-content:center;">
  <button onclick="window.print()" class="btn btn-success btn-sm">
    <i class="bi bi-printer me-1"></i>Print Receipt
  </button>
  <a href="<?= url('seed-distribution/' . (int)($dist['id'] ?? 0)) ?>"
     class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back to Record
  </a>
  <a href="<?= url('seed-distribution/history') ?>"
     class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-clock-history me-1"></i>History
  </a>
</div>
