<?php
/**
 * =========================================================
 * PRODUCTS – CREATE FORM  (Product Catalog / Master Data)
 * =========================================================
 * File    : modules/admin/views/products/create.php
 * Purpose : Clean catalog-only product creation.
 *           No pricing. No stock. No batch. No expiry.
 *           Product = master data only.
 *           Stock enters through Farm → Warehouse → Transfer → Shop.
 *           Pricing is set by General Manager separately.
 * Deps    : ProductController, BarcodeService
 * =========================================================
 */
declare(strict_types=1);
?>

<!-- ── Header ────────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-plus-circle-fill me-2 text-primary"></i>Add New Product
    </h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= url('admin/products') ?>">Products</a></li>
        <li class="breadcrumb-item active">Add New</li>
      </ol>
    </nav>
  </div>
  <a href="<?= url('admin/products') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
</div>

<!-- ── Info Banner ───────────────────────────────────────── -->
<div class="alert alert-info border-0 shadow-sm d-flex align-items-start gap-2 mb-4 py-2" role="note">
  <i class="bi bi-info-circle-fill mt-1 flex-shrink-0"></i>
  <div class="small">
    <strong>Product Catalog — Master Data Only.</strong>
    Creating a product does <strong>not</strong> create stock.
    Stock enters the system through Farm → Warehouse → Transfer → Shop Receiving.
    Selling price is set by the General Manager separately.
  </div>
</div>

<form id="productForm" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

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

            <!-- Product Code — auto-generated, read-only display, editable -->
            <div class="col-md-4">
              <label for="product_code" class="form-label fw-semibold">
                Product Code <span class="text-danger">*</span>
              </label>
              <input type="text"
                     class="form-control font-monospace text-uppercase"
                     id="product_code"
                     name="product_code"
                     value="<?= e($next_code) ?>"
                     maxlength="50"
                     autocomplete="off"
                     aria-describedby="codeHelp err_product_code">
              <div class="form-text" id="codeHelp">Auto-generated. Edit only if needed.</div>
              <div class="invalid-feedback" id="err_product_code"></div>
            </div>

            <!-- Barcode — optional, auto-generated -->
            <div class="col-md-8">
              <label for="barcodeInput" class="form-label fw-semibold">
                Barcode <span class="text-muted fw-normal small">(optional — auto-generated if left empty)</span>
              </label>
              <div class="input-group">
                <input type="text"
                       class="form-control font-monospace"
                       id="barcodeInput"
                       name="barcode"
                       value="<?= e($barcode) ?>"
                       maxlength="50"
                       autocomplete="off"
                       placeholder="Leave blank to auto-generate"
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
                     maxlength="200"
                     placeholder="e.g. Teff — White Grade A"
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
                <option value="<?= (int)$cat['category_id'] ?>">
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
                <option value="<?= (int)$u['unit_id'] ?>">
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
                        maxlength="1000"
                        placeholder="Brief product description…"></textarea>
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
              <option value="<?= e($k) ?>" <?= $k === 'active' ? 'selected' : '' ?>><?= e($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox"
                   id="is_active" name="is_active" value="1" checked>
            <label class="form-check-label fw-semibold" for="is_active" id="activeLbl">Active</label>
          </div>

        </div>
      </div>

      <!-- Product Image (optional) -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3">
          <h6 class="fw-semibold mb-0">
            <i class="bi bi-image me-2 text-secondary"></i>Product Image
            <span class="text-muted fw-normal small ms-1">(optional)</span>
          </h6>
        </div>
        <div class="card-body p-3 text-center">
          <div id="imageDropZone"
               class="border rounded-2 d-flex flex-column align-items-center justify-content-center bg-light"
               style="height:180px;cursor:pointer;transition:border-color .15s;"
               role="button"
               tabindex="0"
               aria-label="Click to upload product image">
            <i class="bi bi-cloud-upload display-6 text-muted d-block"></i>
            <div class="text-muted small mt-2">Click or drag image here</div>
            <div class="text-muted" style="font-size:.7rem;">JPEG, PNG, WEBP · Max 5 MB</div>
          </div>
          <img id="imagePreview" src="#" alt="Image preview"
               class="d-none rounded-2 mt-0"
               style="width:100%;max-height:180px;object-fit:contain;">
          <input type="file" class="d-none" id="imageFileInput"
                 name="primary_image" accept=".jpg,.jpeg,.png,.webp">
          <div class="mt-2 d-flex gap-2 justify-content-center">
            <button type="button" class="btn btn-outline-secondary btn-sm d-none"
                    id="clearImageBtn">
              <i class="bi bi-x-lg me-1"></i>Remove
            </button>
          </div>
          <div class="text-danger small mt-1" id="err_image"></div>
        </div>
      </div>

      <!-- What comes next -->
      <div class="card border-0 shadow-sm border-start border-4 border-info">
        <div class="card-body py-3 px-3">
          <div class="small fw-semibold text-info mb-1">
            <i class="bi bi-diagram-3 me-1"></i>After creating a product:
          </div>
          <ol class="small text-muted mb-0 ps-3">
            <li>Farm harvests and dispatches to Warehouse</li>
            <li>Warehouse receives and stores stock</li>
            <li>Warehouse Manager creates a Transfer to Shop</li>
            <li>GM approves the Transfer</li>
            <li>Shop receives stock via Salesperson</li>
            <li>GM sets the selling price</li>
            <li>POS uses the price automatically</li>
          </ol>
        </div>
      </div>

    </div><!-- /col-lg-4 -->
  </div><!-- /row -->

  <!-- ── Form Footer ───────────────────────────────────────── -->
  <div class="card border-0 shadow-sm mt-2">
    <div class="card-body py-3 px-4 d-flex gap-3 flex-wrap align-items-center">
      <button type="submit" class="btn btn-primary px-5 fw-semibold" id="submitBtn">
        <span class="spinner-border spinner-border-sm me-2 d-none" id="spinner" aria-hidden="true"></span>
        <i class="bi bi-check-lg me-1" id="submitIcon"></i>Save Product
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

  var APP_URL = '<?= rtrim(e(\AAM\Config\Application::get('url','')), '/') ?>';

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

  // ── Image upload & preview ────────────────────────────────
  var dropZone   = document.getElementById('imageDropZone');
  var fileInput  = document.getElementById('imageFileInput');
  var preview    = document.getElementById('imagePreview');
  var clearBtn   = document.getElementById('clearImageBtn');
  var errImage   = document.getElementById('err_image');

  function handleFile(file) {
    if (!file) return;
    if (!['image/jpeg','image/png','image/webp'].includes(file.type)) {
      errImage.textContent = 'Only JPEG, PNG, WEBP images allowed.';
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      errImage.textContent = 'Image must be 5 MB or smaller.';
      return;
    }
    errImage.textContent = '';
    var reader = new FileReader();
    reader.onload = function (e) {
      preview.src = e.target.result;
      preview.classList.remove('d-none');
      dropZone.classList.add('d-none');
      clearBtn.classList.remove('d-none');
    };
    reader.readAsDataURL(file);
  }

  dropZone.addEventListener('click', function () { fileInput.click(); });
  dropZone.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') fileInput.click(); });
  dropZone.addEventListener('dragover', function (e) { e.preventDefault(); this.style.borderColor = '#0d6efd'; });
  dropZone.addEventListener('dragleave', function () { this.style.borderColor = ''; });
  dropZone.addEventListener('drop', function (e) {
    e.preventDefault();
    this.style.borderColor = '';
    var f = e.dataTransfer.files[0];
    if (f) { fileInput.files = e.dataTransfer.files; handleFile(f); }
  });
  fileInput.addEventListener('change', function () { handleFile(this.files[0]); });

  clearBtn.addEventListener('click', function () {
    fileInput.value = '';
    preview.src = '#';
    preview.classList.add('d-none');
    dropZone.classList.remove('d-none');
    this.classList.add('d-none');
    errImage.textContent = '';
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
    // Ensure is_active sends 0 when unchecked
    if (!document.getElementById('is_active').checked) {
      fd.set('is_active', '0');
    }

    fetch(APP_URL + '/admin/products', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: fd
    })
    .then(function (r) { return r.json(); })
    .then(function (resp) {
      submitBtn.disabled = false;
      spinner.classList.add('d-none');
      submitIcon.classList.remove('d-none');

      if (resp.success) {
        if (typeof AAM !== 'undefined') AAM.toast('Product created successfully.', 'success');
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
