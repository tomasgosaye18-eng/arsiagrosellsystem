<?php
/**
 * =========================================================
 * PRODUCTS – FARM MANAGER READ-ONLY DETAIL VIEW
 * =========================================================
 * File    : modules/admin/views/products/show_farm.php
 * Purpose : Product detail page scoped to Farm Manager.
 *           Shows ONLY catalog information relevant to
 *           farm production: name, code, category, unit,
 *           description, status, and barcode.
 *           NO price, cost, margin, or write controls.
 * Access  : ROLE_FARM_MANAGER (enforced in controller)
 * =========================================================
 */
declare(strict_types=1);

$statusBadge = static function (string $status): string {
    $map = [
        'ACTIVE'       => ['bg-success',          'Active'],
        'INACTIVE'     => ['bg-secondary',         'Inactive'],
        'DISCONTINUED' => ['bg-danger',            'Discontinued'],
        'OUT_OF_STOCK' => ['bg-warning text-dark', 'Out of Stock'],
        'ARCHIVED'     => ['bg-dark',              'Archived'],
    ];
    [$cls, $lbl] = $map[$status] ?? ['bg-light text-dark border', $status];
    return '<span class="badge ' . $cls . ' rounded-pill">' . e($lbl) . '</span>';
};
?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-box-seam me-2 text-primary"></i><?= e($product['product_name']) ?>
    </h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= url('products') ?>">Products</a></li>
        <li class="breadcrumb-item active"><?= e($product['product_code']) ?></li>
      </ol>
    </nav>
  </div>
  <a href="<?= url('products') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back to Catalog
  </a>
</div>

<div class="row g-3">

  <!-- ── Main Info ────────────────────────────────────── -->
  <div class="col-lg-8">

    <!-- Identity Card -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white border-bottom py-2">
        <h6 class="fw-semibold mb-0">
          <i class="bi bi-info-circle me-2 text-primary"></i>Product Information
        </h6>
      </div>
      <div class="card-body p-3">
        <div class="row g-3">

          <!-- Product Code -->
          <div class="col-md-6">
            <div class="text-muted small mb-1">Product Code / SKU</div>
            <span class="badge bg-light text-dark border font-monospace fs-6">
              <?= e($product['product_code']) ?>
            </span>
          </div>

          <!-- Barcode -->
          <div class="col-md-6">
            <div class="text-muted small mb-1">Barcode</div>
            <?php if (!empty($product['barcode'])): ?>
            <span class="font-monospace fw-semibold"><?= e($product['barcode']) ?></span>
            <div id="productBarcodeSvg" class="mt-2"></div>
            <?php else: ?>
            <span class="text-muted">—</span>
            <?php endif; ?>
          </div>

          <!-- Category -->
          <div class="col-md-4">
            <div class="text-muted small mb-1">Category</div>
            <div class="d-flex align-items-center gap-1">
              <i class="bi <?= e($product['category_icon'] ?? 'bi-tag') ?> text-primary"></i>
              <span><?= e($product['category_name'] ?? '—') ?></span>
            </div>
          </div>

          <!-- Unit of Measure -->
          <div class="col-md-4">
            <div class="text-muted small mb-1">Unit of Measure</div>
            <span class="fw-semibold">
              <?= e($product['unit_name'] ?? '—') ?>
              <?php if (!empty($product['unit_abbreviation'])): ?>
              <span class="text-muted fw-normal">(<?= e($product['unit_abbreviation']) ?>)</span>
              <?php endif; ?>
            </span>
          </div>

          <!-- Status -->
          <div class="col-md-4">
            <div class="text-muted small mb-1">Status</div>
            <?= $statusBadge($product['product_status']) ?>
            <?php if (!$product['is_active']): ?>
            <span class="badge bg-secondary ms-1">Inactive</span>
            <?php endif; ?>
          </div>

          <!-- Brand / Scientific Name -->
          <?php if (!empty($product['brand'])): ?>
          <div class="col-md-6">
            <div class="text-muted small mb-1">Brand</div>
            <span><?= e($product['brand']) ?></span>
          </div>
          <?php endif; ?>

          <?php if (!empty($product['scientific_name'])): ?>
          <div class="col-md-6">
            <div class="text-muted small mb-1">Scientific Name</div>
            <em><?= e($product['scientific_name']) ?></em>
          </div>
          <?php endif; ?>

          <!-- Description -->
          <?php if (!empty($product['description'])): ?>
          <div class="col-12">
            <div class="text-muted small mb-1">Description</div>
            <p class="mb-0 small"><?= nl2br(e($product['description'])) ?></p>
          </div>
          <?php endif; ?>

        </div>
      </div>
    </div>

    <!-- Storage / Expiry Info (useful for farm operations) -->
    <?php if ($product['requires_expiry'] || !empty($product['storage_conditions'])): ?>
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white border-bottom py-2">
        <h6 class="fw-semibold mb-0">
          <i class="bi bi-thermometer-half me-2 text-info"></i>Handling &amp; Storage
        </h6>
      </div>
      <div class="card-body p-3 small">
        <?php if ($product['requires_expiry']): ?>
        <div class="alert alert-info py-2 mb-2">
          <i class="bi bi-clock-history me-2"></i>
          <strong>Expiry tracking enabled</strong>
          <?php if (!empty($product['shelf_life_days'])): ?>
          · Shelf Life: <?= (int)$product['shelf_life_days'] ?> days
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($product['storage_conditions'])): ?>
        <div>
          <span class="text-muted">Storage Conditions:</span>
          <span class="ms-2"><?= e($product['storage_conditions']) ?></span>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /col-lg-8 -->

  <!-- ── Right Sidebar ───────────────────────────────── -->
  <div class="col-lg-4">

    <!-- Product Image -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white border-bottom py-2">
        <h6 class="fw-semibold mb-0">
          <i class="bi bi-image me-2 text-secondary"></i>Product Image
        </h6>
      </div>
      <div class="card-body p-3">
        <?php if (!empty($images)): ?>
        <?php
          $primary = null;
          foreach ($images as $img) {
              if ($img['image_type'] === 'PRIMARY') { $primary = $img; break; }
          }
          $display = $primary ?? $images[0] ?? null;
        ?>
        <?php if ($display): ?>
      <img src="<?= upload_url(str_replace('public/uploads/', '', $display['image_path'])) ?>"
             alt="<?= e($product['product_name']) ?>"
             class="img-fluid rounded"
             style="width:100%;max-height:200px;object-fit:cover;">
        <?php endif; ?>
        <?php else: ?>
        <div class="text-center text-muted py-3">
          <i class="bi bi-image d-block mb-1 fs-4 opacity-25"></i>
          <div class="small">No image available</div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Quick Reference Card -->
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-bottom py-2">
        <h6 class="fw-semibold mb-0">
          <i class="bi bi-card-checklist me-2 text-success"></i>Quick Reference
        </h6>
      </div>
      <div class="card-body p-3 small">
        <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
          <span class="text-muted">Name</span>
          <span class="fw-semibold text-end"><?= e($product['product_name']) ?></span>
        </div>
        <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
          <span class="text-muted">Code</span>
          <span class="font-monospace"><?= e($product['product_code']) ?></span>
        </div>
        <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
          <span class="text-muted">Category</span>
          <span><?= e($product['category_name'] ?? '—') ?></span>
        </div>
        <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
          <span class="text-muted">Unit</span>
          <span><?= e($product['unit_abbreviation'] ?? $product['unit_name'] ?? '—') ?></span>
        </div>
        <div class="d-flex justify-content-between align-items-center py-1">
          <span class="text-muted">Status</span>
          <?= $statusBadge($product['product_status']) ?>
        </div>
      </div>
    </div>

  </div><!-- /col-lg-4 -->
</div>

<?php $view->section('scripts'); ?>
<script>
(function () {
  'use strict';
  var barcode = '<?= e($product['barcode'] ?? '') ?>';
  if (!barcode) return;
  // Render barcode SVG inline — read-only display, no print link shown
  fetch('<?= url('admin/barcode/svg') ?>?code=' + encodeURIComponent(barcode), {
    credentials: 'same-origin',
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(function (resp) {
    if (resp.success) {
      var el = document.getElementById('productBarcodeSvg');
      if (el) el.innerHTML = resp.svg;
    }
  });
})();
</script>
<?php $view->endSection(); ?>
