<?php
/**
 * =========================================================
 * PRODUCTS – DETAIL VIEW
 * =========================================================
 * File    : modules/admin/views/products/show.php
 * Purpose : Full product detail page with image gallery,
 *           price history, barcode display, stock info.
 * Deps    : ProductController
 * =========================================================
 */
declare(strict_types=1);

$statusBadge = static function (string $status): string {
    $map = [
        'active'       => ['bg-success',          'Active'],
        'inactive'     => ['bg-secondary',         'Inactive'],
        'discontinued' => ['bg-danger',            'Discontinued'],
        'out_of_stock' => ['bg-warning text-dark', 'Out of Stock'],
    ];
    [$cls, $lbl] = $map[strtolower($status)] ?? ['bg-light text-dark border', ucfirst(str_replace('_',' ',$status))];
    return '<span class="badge '.$cls.' rounded-pill">'.$lbl.'</span>';
};
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-box-seam me-2 text-primary"></i><?= e($product['product_name']) ?></h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= url('admin/products') ?>">Products</a></li>
        <li class="breadcrumb-item active"><?= e($product['product_code']) ?></li>
      </ol>
    </nav>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= url('admin/products/' . $product['product_id'] . '/barcode/print') ?>" target="_blank"
       class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i>Print Barcode</a>
    <a href="<?= url('admin/products/' . $product['product_id'] . '/edit') ?>"
       class="btn btn-primary btn-sm"><i class="bi bi-pencil-fill me-1"></i>Edit</a>
    <a href="<?= url('admin/products') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>
  </div>
</div>

<div class="row g-3">

  <!-- ── Main Info ────────────────────────────────────── -->
  <div class="col-lg-8">

    <!-- Identity -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body p-3">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="text-muted small mb-1">Product Code</div>
            <span class="badge bg-light text-dark border font-monospace fs-6"><?= e($product['product_code']) ?></span>
          </div>
          <div class="col-md-6">
            <div class="text-muted small mb-1">Barcode</div>
            <span class="font-monospace fw-semibold"><?= e($product['barcode'] ?? '—') ?></span>
            <?php if ($product['barcode']): ?>
            <div id="productBarcodeSvg" class="mt-2"></div>
            <?php endif; ?>
          </div>
          <div class="col-md-4">
            <div class="text-muted small mb-1">Category</div>
            <div class="d-flex align-items-center gap-1">
              <i class="bi <?= e($product['category_icon'] ?? 'bi-tag') ?> text-primary"></i>
              <span><?= e($product['category_name']) ?></span>
            </div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small mb-1">Unit</div>
            <span><?= e($product['unit_name']) ?> (<?= e($product['unit_abbreviation']) ?>)</span>
          </div>
          <div class="col-md-4">
            <div class="text-muted small mb-1">Brand</div>
            <span><?= e($product['brand'] ?? '—') ?></span>
          </div>
          <?php if ($product['scientific_name']): ?>
          <div class="col-md-6">
            <div class="text-muted small mb-1">Scientific Name</div>
            <em><?= e($product['scientific_name']) ?></em>
          </div>
          <?php endif; ?>
          <?php if ($product['description']): ?>
          <div class="col-12">
            <div class="text-muted small mb-1">Description</div>
            <p class="mb-0 small"><?= nl2br(e($product['description'])) ?></p>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Stock -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white border-bottom py-2">
        <h6 class="fw-semibold mb-0"><i class="bi bi-boxes me-2 text-warning"></i>Stock Settings</h6>
      </div>
      <div class="card-body p-3">
        <div class="row g-3 text-center">
          <?php foreach ([
            ['Minimum Stock', $product['minimum_stock'],'bg-light'],
            ['Maximum Stock', $product['maximum_stock'] ?? '—','bg-light'],
            ['Reorder Level', $product['reorder_level'],'bg-warning bg-opacity-10'],
            ['Reorder Qty', $product['reorder_quantity'] ?? '—','bg-light'],
          ] as [$label,$val,$bg]): ?>
          <div class="col-6 col-md-3">
            <div class="border rounded p-2 <?= $bg ?>">
              <div class="text-muted small"><?= $label ?></div>
              <div class="fw-bold"><?= is_numeric($val) ? number_format((float)$val,2) : $val ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if ($product['requires_expiry']): ?>
        <div class="mt-3 alert alert-info py-2 mb-0 small">
          <i class="bi bi-clock-history me-2"></i>
          <strong>Expiry tracking enabled</strong> · Shelf Life: <?= $product['shelf_life_days'] ? $product['shelf_life_days'] . ' days' : 'Not set' ?>
          <?php if ($product['storage_conditions']): ?>· Storage: <?= e($product['storage_conditions']) ?><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div><!-- /col-lg-8 -->

  <!-- ── Right Sidebar ───────────────────────────────── -->
  <div class="col-lg-4">

    <!-- Status Card -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="fw-semibold">Status</span>
          <?= $statusBadge($product['product_status']) ?>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="text-muted small">Active</span>
          <span class="badge <?= $product['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
            <?= $product['is_active'] ? 'Yes' : 'No' ?>
          </span>
        </div>
        <div class="d-flex justify-content-between align-items-center">
          <span class="text-muted small">Featured</span>
          <i class="bi <?= $product['is_featured'] ? 'bi-star-fill text-warning' : 'bi-star text-muted' ?>"></i>
        </div>
        <hr class="my-2">
        <div class="text-muted small">
          <div>Created by: <strong><?= e($product['creator_full_name'] ?? $product['creator_name'] ?? '—') ?></strong></div>
          <div>Created: <strong><?= date_display($product['created_at']) ?></strong></div>
          <?php if ($product['updater_name']): ?>
          <div>Updated by: <strong><?= e($product['updater_full_name'] ?? $product['updater_name']) ?></strong></div>
          <div>Updated: <strong><?= date_display($product['updated_at']) ?></strong></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Image Gallery -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white border-bottom py-2">
        <h6 class="fw-semibold mb-0"><i class="bi bi-images me-2 text-secondary"></i>Images</h6>
      </div>
      <div class="card-body p-3">
        <?php if (!empty($images)): ?>
        <div class="row g-2">
          <?php foreach ($images as $img): ?>
          <div class="col-4">
            <div class="position-relative">
              <img src="<?= upload_url(str_replace('public/uploads/', '', $img['image_path'])) ?>"
                   alt="Product Image"
                   class="img-fluid rounded <?= $img['image_type']==='PRIMARY' ? 'border border-primary border-2' : '' ?>"
                   style="width:100%;height:80px;object-fit:cover;">
              <?php if ($img['image_type']==='PRIMARY'): ?>
              <span class="badge bg-primary position-absolute top-0 start-0 m-1"
                    style="font-size:.6rem;">Primary</span>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-center text-muted py-3">
          <i class="bi bi-image d-block mb-1 fs-4 opacity-25"></i>
          <div class="small">No images uploaded</div>
        </div>
        <?php endif; ?>
        <a href="<?= url('admin/products/' . $product['product_id'] . '/edit') ?>"
           class="btn btn-outline-secondary btn-sm w-100 mt-2">
          <i class="bi bi-plus-lg me-1"></i>Manage Images
        </a>
      </div>
    </div>

    <!-- Physical -->
    <?php if ($product['weight'] || $product['dimensions'] || $product['color']): ?>
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-bottom py-2">
        <h6 class="fw-semibold mb-0"><i class="bi bi-rulers me-2 text-secondary"></i>Physical</h6>
      </div>
      <div class="card-body p-3 small">
        <?php if ($product['weight']): ?>
        <div class="d-flex justify-content-between mb-1">
          <span class="text-muted">Weight</span>
          <span><?= $product['weight'] ?> <?= e($product['weight_unit'] ?? '') ?></span>
        </div>
        <?php endif; ?>
        <?php if ($product['dimensions']): ?>
        <div class="d-flex justify-content-between mb-1">
          <span class="text-muted">Dimensions</span>
          <span><?= e($product['dimensions']) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($product['color']): ?>
        <div class="d-flex justify-content-between mb-1">
          <span class="text-muted">Color</span>
          <span><?= e($product['color']) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($product['size']): ?>
        <div class="d-flex justify-content-between">
          <span class="text-muted">Size</span>
          <span><?= e($product['size']) ?></span>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /col-lg-4 -->
</div>

<?php $view->section('scripts'); ?>
<script>
(function () {
  'use strict';
  var barcode = '<?= e($product['barcode'] ?? '') ?>';
  if (barcode) {
    fetch('<?= url('admin/barcode/svg') ?>?code=' + encodeURIComponent(barcode), {
      credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'}
    })
    .then(r => r.json())
    .then(resp => {
      if (resp.success) {
        document.getElementById('productBarcodeSvg').innerHTML = resp.svg;
      }
    });
  }
})();
</script>
<?php $view->endSection(); ?>
