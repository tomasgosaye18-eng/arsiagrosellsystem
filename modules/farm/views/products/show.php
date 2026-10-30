<?php
/**
 * Farm Manager — Product Detail (Read-Only)
 * Route: GET /farm/products/{id}
 * NO prices, NO admin controls.
 *
 * @var array $product
 */
declare(strict_types=1);

$product ??= [];

$statusBadge = [
    'active'        => ['bg-success',          'Active'],
    'inactive'      => ['bg-secondary',         'Inactive'],
    'discontinued'  => ['bg-danger',            'Discontinued'],
    'out_of_stock'  => ['bg-warning text-dark', 'Out of Stock'],
    // uppercase aliases for legacy data
    'ACTIVE'        => ['bg-success',          'Active'],
    'INACTIVE'      => ['bg-secondary',         'Inactive'],
    'DISCONTINUED'  => ['bg-danger',            'Discontinued'],
    'OUT_OF_STOCK'  => ['bg-warning text-dark', 'Out of Stock'],
    'ARCHIVED'      => ['bg-dark',              'Archived'],
];
[$badgeCls, $badgeLbl] = $statusBadge[$product['product_status'] ?? ''] ?? ['bg-secondary', 'Unknown'];
?>
<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-box-seam me-2 text-primary"></i><?= e($product['product_name'] ?? '—') ?>
    </h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('farm/dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= url('farm/products') ?>">Products</a></li>
        <li class="breadcrumb-item active"><?= e($product['product_code'] ?? '') ?></li>
      </ol>
    </nav>
  </div>
  <a href="<?= url('farm/products') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back to Catalog
  </a>
</div>

<div class="row g-3">
  <!-- Left: main info -->
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white border-bottom py-2">
        <h6 class="fw-semibold mb-0">
          <i class="bi bi-info-circle me-2 text-primary"></i>Product Information
        </h6>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-sm-6">
            <label class="form-label small fw-semibold text-muted">Product Name</label>
            <p class="mb-0 fw-semibold"><?= e($product['product_name'] ?? '—') ?></p>
          </div>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold text-muted">Product Code / SKU</label>
            <p class="mb-0"><code><?= e($product['product_code'] ?? '—') ?></code></p>
          </div>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold text-muted">Category</label>
            <p class="mb-0"><?= e($product['category_name'] ?? '—') ?></p>
          </div>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold text-muted">Unit of Measure</label>
            <p class="mb-0">
              <?= e($product['unit_name'] ?? '—') ?>
              <?php if (!empty($product['unit_abbr'])): ?>
              <span class="text-muted">(<?= e($product['unit_abbr']) ?>)</span>
              <?php endif; ?>
              <?php if (!empty($product['unit_type'])): ?>
              <span class="badge bg-light text-dark border ms-1 small"><?= e($product['unit_type']) ?></span>
              <?php endif; ?>
            </p>
          </div>
          <?php if (!empty($product['brand'])): ?>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold text-muted">Brand</label>
            <p class="mb-0"><?= e($product['brand']) ?></p>
          </div>
          <?php endif; ?>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold text-muted">Status</label>
            <p class="mb-0"><span class="badge <?= $badgeCls ?>"><?= $badgeLbl ?></span></p>
          </div>
          <?php if (!empty($product['barcode'])): ?>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold text-muted">Barcode</label>
            <p class="mb-0"><code><?= e($product['barcode']) ?></code></p>
          </div>
          <?php endif; ?>
          <?php if (!empty($product['description'])): ?>
          <div class="col-12">
            <label class="form-label small fw-semibold text-muted">Description</label>
            <p class="mb-0 text-muted small"><?= nl2br(e($product['description'])) ?></p>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Storage & Physical info -->
    <?php
    $hasStorage = !empty($product['storage_conditions']) || !empty($product['shelf_life_days'])
               || !empty($product['weight']) || !empty($product['dimensions'])
               || !empty($product['color']) || !empty($product['size']);
    ?>
    <?php if ($hasStorage): ?>
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-bottom py-2">
        <h6 class="fw-semibold mb-0">
          <i class="bi bi-archive me-2 text-secondary"></i>Storage &amp; Physical
        </h6>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <?php if (!empty($product['storage_conditions'])): ?>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold text-muted">Storage Conditions</label>
            <p class="mb-0 small"><?= e($product['storage_conditions']) ?></p>
          </div>
          <?php endif; ?>
          <?php if (!empty($product['shelf_life_days'])): ?>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold text-muted">Shelf Life</label>
            <p class="mb-0 small"><?= (int)$product['shelf_life_days'] ?> days</p>
          </div>
          <?php endif; ?>
          <?php if (!empty($product['requires_expiry'])): ?>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold text-muted">Requires Expiry Tracking</label>
            <p class="mb-0 small"><?= $product['requires_expiry'] ? 'Yes' : 'No' ?></p>
          </div>
          <?php endif; ?>
          <?php if (!empty($product['weight'])): ?>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold text-muted">Weight</label>
            <p class="mb-0 small"><?= e($product['weight']) ?> <?= e($product['weight_unit'] ?? '') ?></p>
          </div>
          <?php endif; ?>
          <?php if (!empty($product['dimensions'])): ?>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold text-muted">Dimensions</label>
            <p class="mb-0 small"><?= e($product['dimensions']) ?></p>
          </div>
          <?php endif; ?>
          <?php if (!empty($product['color'])): ?>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold text-muted">Color</label>
            <p class="mb-0 small"><?= e($product['color']) ?></p>
          </div>
          <?php endif; ?>
          <?php if (!empty($product['size'])): ?>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold text-muted">Size</label>
            <p class="mb-0 small"><?= e($product['size']) ?></p>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Right: image + quick info -->
  <div class="col-lg-4">
    <!-- Product image -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body text-center p-3">
        <?php if (!empty($product['primary_image'])): ?>
        <img src="<?= url(e($product['primary_image'])) ?>"
             class="img-fluid rounded" style="max-height:200px;object-fit:contain;"
             alt="<?= e($product['product_name']) ?>">
        <?php else: ?>
        <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height:160px;">
          <i class="bi bi-image text-muted opacity-25" style="font-size:3rem;"></i>
        </div>
        <p class="text-muted small mt-2 mb-0">No image available</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Quick info card -->
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-bottom py-2">
        <h6 class="fw-semibold mb-0 small">Quick Info</h6>
      </div>
      <ul class="list-group list-group-flush">
        <li class="list-group-item d-flex justify-content-between py-2 small">
          <span class="text-muted">Status</span>
          <span class="badge <?= $badgeCls ?>"><?= $badgeLbl ?></span>
        </li>
        <li class="list-group-item d-flex justify-content-between py-2 small">
          <span class="text-muted">Category</span>
          <span class="fw-semibold"><?= e($product['category_name'] ?? '—') ?></span>
        </li>
        <li class="list-group-item d-flex justify-content-between py-2 small">
          <span class="text-muted">Unit</span>
          <span><?= e($product['unit_abbr'] ?? $product['unit_name'] ?? '—') ?></span>
        </li>
        <?php if ($product['requires_expiry'] ?? false): ?>
        <li class="list-group-item d-flex justify-content-between py-2 small">
          <span class="text-muted">Expiry Tracking</span>
          <span class="badge bg-warning text-dark">Required</span>
        </li>
        <?php endif; ?>
        <?php if (!empty($product['min_stock_level'])): ?>
        <li class="list-group-item d-flex justify-content-between py-2 small">
          <span class="text-muted">Min Stock Level</span>
          <span><?= number_format((float)$product['min_stock_level'], 1) ?></span>
        </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</div>

<!-- Read-only notice -->
<div class="mt-3 text-muted small d-flex align-items-center gap-1">
  <i class="bi bi-lock-fill text-secondary opacity-50"></i>
  Product details are read-only for Farm Manager.
</div>
