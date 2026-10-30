<?php
/**
 * =========================================================
 * PRODUCTS – EDIT FORM  (Product Catalog / Master Data)
 * =========================================================
 * File    : modules/admin/views/products/edit.php
 * Purpose : Clean catalog-only product editing.
 *           No pricing. No stock. No batch. No expiry.
 *           Product = master data only.
 *           Selling price is managed by General Manager.
 *           Stock is managed through Farm/Warehouse/Transfer/Shop.
 * Deps    : ProductController, BarcodeService
 * =========================================================
 */
declare(strict_types=1);
$pid = (int)($product['product_id'] ?? $product['id'] ?? 0);
?>

<!-- ── Header ────────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-pencil-square me-2 text-primary"></i>Edit Product
    </h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= url('admin/products') ?>">Products</a></li>
        <li class="breadcrumb-item active"><?= e($product['product_name']) ?></li>
      </ol>
    </nav>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= url('admin/products/' . $pid) ?>"
       class="btn btn-outline-info btn-sm">
      <i class="bi bi-eye me-1"></i>View
    </a>
    <a href="<?= url('admin/products') ?>"
       class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>
  </div>
</div>

<form id="productForm" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
  <input type="hidden" name="_product_id" value="<?= $pid ?>">

  <div class="row g-4">

    <!-- ── LEFT: Main Fields ─────────────────────────────── -->
    <div class="col-lg-8">

      <!-- Basic Information -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3">
          <h6 class="fw-semibold mb-0">
            <i class="bi bi-box-seam-fill me-2 text-primary"></i>Product Information
          </h6>
        </div>
        <div class="card-body p-4">
          <div class="row g-3">

            <!-- Product Code -->
            <div class="col-md-4">
              <label for="product_code" class="form-label fw-semibold">
                Product Code <span class="text-danger">*</span>
              </label>
              <input type="text"
                     class="form-control font-monospace text-uppercase"
                     id="product_code"
                     name="product_code"
                     value="<?= e($product['product_code'] ?? $product['sku'] ?? '') ?>"
                     maxlength="50"
                     autocomplete="off"
                     aria-describedby="err_product_code">
              <div class="invalid-feedback" id="err_product_code"></div>
            </div>

            <!-- Barcode -->
            <div class="col-md-8">
              <label for="barcodeInput" class="form-label fw-semibold">
                Barcode <span class="text-muted fw-normal small">(optional)</span>
              </label>
              <div class="input-group">
                <input type="text"
                       class="form-control font-monospace"
                       id="barcodeInput"
                       name="barcode"
                       value="<?= e($product['barcode'] ?? '') ?>"
                       maxlength="50"
                       autocomplete="off"
                       aria-describedby="err_barcode">
                <button type="button" class="btn btn-outline-secondary"
                        id="generateBarcodeBtn" title="Generate new barcode">
                  <i class="bi bi-arrow-clockwise"></i>
                </button>
              </div>
              <div class="invalid-feedback" id="err_barcode"></div>
            </div>

            <!-- Product Name -->
            <div class="col-12">
              <label for="product_name" class="form-label fw-semibold">
                Product Name <span class="text-danger">*</span>
              </label>
              <input type="text"
                     class="form-control"
                     id="product_name"
                     name="product_name"
                     value="<?= e($product['product_name']) ?>"
                     maxlength="200"
                     autocomplete="off"
                     aria-describedby="err_product_name">
              <div class="invalid-feedback" id="err_product_name"></div>
            </div>

            <!-- Category -->
            <div class="col-md-6">
              <label for="category_id" class="form-label fw-semibold">
                Category <span class="text-danger">*</span>
              </label>
              <select class="form-select" id="category_id" name="category_id"
                      aria-describedby="err_category_id">
                <option value="">— Select Category —</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= (int)$cat['category_id'] ?>"
                  <?= (int)($product['category_id'] ?? 0) === (int)$cat['category_id'] ? 'selected' : '' ?>>
                  <?= e($cat['category_name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback" id="err_category_id"></div>
            </div>

            <!-- Unit of Measure -->
            <div class="col-md-6">
              <label for="unit_id" class="form-label fw-semibold">
                Unit of Measure <span class="text-danger">*</span>
              </label>
              <select class="form-select" id="unit_id" name="unit_id"
                      aria-describedby="err_unit_id">
                <option value="">— Select Unit —</option>
                <?php foreach ($units as $u): ?>
                <option value="<?= (int)$u['unit_id'] ?>"
                  <?= (int)($product['unit_id'] ?? 0) === (int)$u['unit_id'] ? 'selected' : '' ?>>
                  <?= e($u['unit_name']) ?> (<?= e($u['abbreviation']) ?>)
                </option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback" id="err_unit_id"></div>
            </div>

            <!-- Description -->
            <div class="col-12">
              <label for="description" class="form-label fw-semibold">
                Description <span class="text-muted fw-normal small">(optional)</span>
              </label>
              <textarea class="form-control"
                        id="description"
                        name="description"
                        rows="3"
                        maxlength="1000"><?= e($product['description'] ?? '') ?></textarea>
            </div>

          </div><!-- /row -->
        </div>
      </div>


    </div><!-- /col-lg-8 -->

    <!-- ── RIGHT: Status & Image ──────────────────────────── -->
    <div class="col-lg-4">

      <!-- Status -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3">
          <h6 class="fw-semibold mb-0">
            <i class="bi bi-toggles me-2 text-secondary"></i>Status
          </h6>
        </div>
        <div class="card-body p-4">

          <div class="mb-3">
            <label for="product_status" class="form-label fw-semibold">Product Status</label>
            <select class="form-select" id="product_status" name="product_status">
              <?php foreach ($statuses as $k => $v): ?>
              <option value="<?= e($k) ?>"
                <?= ($product['product_status'] ?? 'active') === $k ? 'selected' : '' ?>>
                <?= e($v) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox"
                   id="is_active" name="is_active" value="1"
                   <?= ($product['is_active'] ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="is_active" id="activeLbl">
              <?= ($product['is_active'] ?? 1) ? 'Active' : 'Inactive' ?>
            </label>
          </div>

        </div>
      </div>

      <!-- Image Gallery -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3">
          <h6 class="fw-semibold mb-0">
            <i class="bi bi-images me-2 text-secondary"></i>Images
          </h6>
        </div>
        <div class="card-body p-3">

          <!-- Existing images -->
          <?php if (!empty($images)): ?>
          <div class="row g-2 mb-3" id="imageGallery">
            <?php foreach ($images as $img): ?>
            <div class="col-4" id="img-container-<?= (int)$img['image_id'] ?>">
              <div class="position-relative border rounded overflow-hidden" style="height:76px;">
                <img src="<?= upload_url(str_replace('public/uploads/', '', $img['image_path'] ?? $img['filename'] ?? '')) ?>"
                     alt="Product image"
                     class="w-100 h-100"
                     style="object-fit:cover;">
                <?php if (($img['image_type'] ?? '') === 'PRIMARY' || ($img['is_primary'] ?? 0)): ?>
                <span class="badge bg-primary position-absolute top-0 start-0 m-1"
                      style="font-size:.5rem;">Primary</span>
                <?php endif; ?>
                <div class="position-absolute bottom-0 start-0 end-0 d-flex gap-1 p-1"
                     style="background:rgba(0,0,0,.5);">
                  <?php if (!($img['is_primary'] ?? 0) && ($img['image_type'] ?? '') !== 'PRIMARY'): ?>
                  <button type="button"
                          class="btn btn-xs btn-light btn-set-primary py-0 px-1 flex-grow-1"
                          style="font-size:.6rem;"
                          data-product-id="<?= $pid ?>"
                          data-image-id="<?= (int)$img['image_id'] ?>"
                          title="Set as primary">
                    <i class="bi bi-star-fill text-warning"></i>
                  </button>
                  <?php endif; ?>
                  <button type="button"
                          class="btn btn-xs btn-danger btn-delete-image py-0 px-1 flex-grow-1"
                          style="font-size:.6rem;"
                          data-product-id="<?= $pid ?>"
                          data-image-id="<?= (int)$img['image_id'] ?>"
                          title="Delete">
                    <i class="bi bi-trash3-fill"></i>
                  </button>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <!-- Upload drop zone -->
          <div id="imageDropZone"
               class="border rounded-2 p-3 text-center bg-light"
               style="cursor:pointer;border-style:dashed!important;border-width:2px!important;">
            <i class="bi bi-cloud-upload text-muted fs-5 d-block mb-1"></i>
            <div class="small text-muted">Drop or click to upload</div>
            <div class="text-muted" style="font-size:.68rem;">JPEG · PNG · WEBP · Max 5MB</div>
          </div>
          <input type="file" class="d-none" id="additionalImageInput"
                 accept=".jpg,.jpeg,.png,.webp" multiple>
          <div id="uploadProgress" class="mt-2 small"></div>
        </div>
      </div>

      <!-- Meta -->
      <div class="card border-0 bg-light border mb-0">
        <div class="card-body py-2 px-3 small text-muted">
          <div>
            <strong>Created:</strong>
            <?= date('d/m/Y H:i', strtotime($product['created_at'] ?? 'now')) ?>
            <?php if (!empty($product['creator_full_name'] ?? $product['creator_name'] ?? null)): ?>
            by <strong><?= e($product['creator_full_name'] ?? $product['creator_name']) ?></strong>
            <?php endif; ?>
          </div>
          <?php if (!empty($product['updater_name'] ?? null)): ?>
          <div class="mt-1">
            <strong>Updated:</strong>
            <?= date('d/m/Y H:i', strtotime($product['updated_at'] ?? 'now')) ?>
            by <strong><?= e($product['updater_full_name'] ?? $product['updater_name']) ?></strong>
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /col-lg-4 -->
  </div><!-- /row -->

  <!-- ── Form Footer ───────────────────────────────────────── -->
  <div class="card border-0 shadow-sm mt-2">
    <div class="card-body py-3 px-4 d-flex gap-3 flex-wrap align-items-center">
      <button type="submit" class="btn btn-primary px-5 fw-semibold" id="submitBtn">
        <span class="spinner-border spinner-border-sm me-2 d-none" id="spinner" aria-hidden="true"></span>
        <i class="bi bi-check-lg me-1" id="submitIcon"></i>Update Product
      </button>
      <a href="<?= url('admin/products') ?>" class="btn btn-outline-secondary px-4">Cancel</a>
      <span class="text-muted small ms-auto">
        <span class="text-danger">*</span> Required fields
      </span>
    </div>
  </div>

</form>

<?php $view->section('scripts'); ?>
<script>
(function () {
  'use strict';

  var APP_URL    = '<?= rtrim(e(\AAM\Config\Application::get('url','')), '/') ?>';
  var PRODUCT_ID = <?= $pid ?>;
  var csrfToken  = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

  // ── Product code: keep uppercase ─────────────────────────
  document.getElementById('product_code').addEventListener('input', function () {
    var pos = this.selectionStart;
    this.value = this.value.toUpperCase();
    this.setSelectionRange(pos, pos);
  });

  // ── Barcode: generate ─────────────────────────────────────
  document.getElementById('generateBarcodeBtn').addEventListener('click', function () {
    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    fetch(APP_URL + '/admin/barcode/generate', {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (resp) {
      if (resp.success) {
        var barcode = resp.barcode ?? (resp.data && resp.data.barcode);
        if (barcode) document.getElementById('barcodeInput').value = barcode;
      }
    })
    .catch(function () {})
    .finally(function () {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i>';
    });
  });

  // ── Active label ──────────────────────────────────────────
  document.getElementById('is_active').addEventListener('change', function () {
    document.getElementById('activeLbl').textContent = this.checked ? 'Active' : 'Inactive';
  });

  // ── Image drag & drop upload (AJAX for edit) ──────────────
  var dropZone  = document.getElementById('imageDropZone');
  var fileInput = document.getElementById('additionalImageInput');
  var uploadProg = document.getElementById('uploadProgress');

  dropZone.addEventListener('click', function () { fileInput.click(); });
  dropZone.addEventListener('dragover', function (e) {
    e.preventDefault(); this.style.borderColor = '#0d6efd';
  });
  dropZone.addEventListener('dragleave', function () {
    this.style.borderColor = '';
  });
  dropZone.addEventListener('drop', function (e) {
    e.preventDefault(); this.style.borderColor = '';
    uploadFiles(e.dataTransfer.files);
  });
  fileInput.addEventListener('change', function () {
    uploadFiles(this.files);
    this.value = '';
  });

  function uploadFiles(files) {
    Array.from(files).forEach(function (file) {
      if (!['image/jpeg','image/png','image/webp'].includes(file.type)) {
        if (typeof AAM !== 'undefined') AAM.toast(file.name + ' — unsupported format', 'error');
        return;
      }
      if (file.size > 5 * 1024 * 1024) {
        if (typeof AAM !== 'undefined') AAM.toast(file.name + ' — max 5 MB', 'error');
        return;
      }
      var fd = new FormData();
      fd.append('csrf_token', csrfToken);
      fd.append('product_image', file);
      uploadProg.innerHTML = '<span class="spinner-border spinner-border-sm me-1 text-primary"></span>Uploading…';

      fetch(APP_URL + '/admin/products/' + PRODUCT_ID + '/images', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd
      })
      .then(function (r) { return r.json(); })
      .then(function (resp) {
        uploadProg.innerHTML = '';
        if (resp.success) {
          if (typeof AAM !== 'undefined') AAM.toast('Image uploaded.', 'success');
          setTimeout(function () { location.reload(); }, 800);
        } else {
          if (typeof AAM !== 'undefined') AAM.toast(resp.message || 'Upload failed.', 'error');
        }
      })
      .catch(function () {
        uploadProg.innerHTML = '';
        if (typeof AAM !== 'undefined') AAM.toast('Upload error.', 'error');
      });
    });
  }

  // ── Set primary image ─────────────────────────────────────
  document.querySelectorAll('.btn-set-primary').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var fd = new FormData();
      fd.append('csrf_token', csrfToken);
      fetch(APP_URL + '/admin/products/' + this.dataset.productId
              + '/images/' + this.dataset.imageId + '/primary', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd
      })
      .then(function (r) { return r.json(); })
      .then(function (resp) {
        if (resp.success) {
          if (typeof AAM !== 'undefined') AAM.toast('Primary image updated.', 'success');
          setTimeout(function () { location.reload(); }, 700);
        } else {
          if (typeof AAM !== 'undefined') AAM.toast(resp.message || 'Failed.', 'error');
        }
      });
    });
  });

  // ── Delete image ──────────────────────────────────────────
  document.querySelectorAll('.btn-delete-image').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!confirm('Delete this image?')) return;
      var self = this;
      var fd = new FormData();
      fd.append('csrf_token', csrfToken);
      fetch(APP_URL + '/admin/products/' + self.dataset.productId
              + '/images/' + self.dataset.imageId + '/delete', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd
      })
      .then(function (r) { return r.json(); })
      .then(function (resp) {
        if (resp.success) {
          var container = document.getElementById('img-container-' + self.dataset.imageId);
          if (container) container.remove();
          if (typeof AAM !== 'undefined') AAM.toast('Image deleted.', 'success');
        } else {
          if (typeof AAM !== 'undefined') AAM.toast(resp.message || 'Delete failed.', 'error');
        }
      });
    });
  });

  // ── Form Submit ───────────────────────────────────────────
  var form      = document.getElementById('productForm');
  var submitBtn = document.getElementById('submitBtn');
  var spinner   = document.getElementById('spinner');
  var submitIcon = document.getElementById('submitIcon');

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    clearErrors();

    submitBtn.disabled = true;
    spinner.classList.remove('d-none');
    submitIcon.classList.add('d-none');

    var fd = new FormData(form);
    if (!document.getElementById('is_active').checked) {
      fd.set('is_active', '0');
    }

    fetch(APP_URL + '/admin/products/' + PRODUCT_ID + '/update', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd
    })
    .then(function (r) { return r.json(); })
    .then(function (resp) {
      submitBtn.disabled = false;
      spinner.classList.add('d-none');
      submitIcon.classList.remove('d-none');

      if (resp.success) {
        if (typeof AAM !== 'undefined') AAM.toast('Product updated successfully.', 'success');
        setTimeout(function () {
          window.location.href = resp.redirect || (APP_URL + '/admin/products');
        }, 800);
      } else {
        if (resp.errors) showErrors(resp.errors);
        if (typeof AAM !== 'undefined') AAM.toast(resp.message || 'Please fix the errors below.', 'error');
      }
    })
    .catch(function () {
      submitBtn.disabled = false;
      spinner.classList.add('d-none');
      submitIcon.classList.remove('d-none');
      if (typeof AAM !== 'undefined') AAM.toast('Network error. Please try again.', 'error');
    });
  });

  function showErrors(errors) {
    Object.keys(errors).forEach(function (key) {
      var input = form.querySelector('[name="' + key + '"]');
      var errEl = document.getElementById('err_' + key);
      if (input) input.classList.add('is-invalid');
      if (errEl) { errEl.textContent = errors[key]; errEl.style.display = 'block'; }
    });
    var first = form.querySelector('.is-invalid');
    if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function clearErrors() {
    form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
    form.querySelectorAll('[id^="err_"]').forEach(function (el) { el.textContent = ''; el.style.display = ''; });
  }

})();
</script>
<?php $view->endSection(); ?>
