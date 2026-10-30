<?php
declare(strict_types=1);
$pageTitle = 'Add New Shop';
$shopTypes ??= [];
$nextCode  ??= 'SHOP-???';
?>
<?php $view->section('styles'); ?>
<link rel="stylesheet" href="<?= $view->asset('css/organization.css') ?>">
<style>
.shop-img-upload-box{border:2px dashed #dee2e6;border-radius:10px;padding:20px;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;background:#fafafa;}
.shop-img-upload-box:hover,.shop-img-upload-box.dragover{border-color:#fd7e14;background:#fff8f3;}
.shop-img-preview{width:100%;max-height:200px;object-fit:cover;border-radius:8px;display:none;margin-top:10px;}
.shop-img-preview.show{display:block;}
.shop-img-actions{display:none;gap:8px;margin-top:8px;justify-content:center;}
.shop-img-actions.show{display:flex;}
</style>
<?php $view->endSection(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-shop-window me-2" style="color:#fd7e14;"></i>Add New Shop</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('admin/organization') ?>">Organization</a></li>
        <li class="breadcrumb-item"><a href="<?= url('admin/organization/shops') ?>">Shops</a></li>
        <li class="breadcrumb-item active">Add New</li>
      </ol>
    </nav>
  </div>
  <a href="<?= url('admin/organization/shops') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
</div>

<div id="formErrors" class="alert alert-danger d-none mb-3">
  <strong>Please fix:</strong>
  <ul class="mb-0 mt-1 ps-3" id="errorList"></ul>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <!-- enctype must be multipart/form-data to support file upload -->
        <form id="shopForm" novalidate enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

          <!-- Shop Code — system-generated, read-only -->
          <div class="mb-3">
            <label class="form-label fw-semibold text-muted small">Shop Code
              <span class="badge bg-secondary ms-1" style="font-size:.65rem;">AUTO-GENERATED</span>
            </label>
            <div class="input-group">
              <span class="input-group-text bg-light"><i class="bi bi-hash" style="color:#fd7e14;"></i></span>
              <input type="text" class="form-control bg-light font-monospace fw-bold"
                     style="color:#c45800;" value="<?= e($nextCode) ?>" readonly tabindex="-1">
              <span class="input-group-text bg-light text-muted"><i class="bi bi-lock-fill"></i></span>
            </div>
            <div class="form-text">Generated automatically. Cannot be modified.</div>
          </div>

          <!-- Shop Name -->
          <div class="mb-3">
            <label for="name" class="form-label fw-semibold">Shop Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="name" name="name"
                   maxlength="150" required autofocus placeholder="e.g. Main Campus Shop">
          </div>

          <!-- Shop Type -->
          <div class="mb-3">
            <label for="shop_type" class="form-label fw-semibold">Shop Type</label>
            <select class="form-select" id="shop_type" name="shop_type">
              <?php foreach ($shopTypes as $k => $v): ?>
              <option value="<?= e($k) ?>"><?= e($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Location -->
          <div class="mb-3">
            <label for="location" class="form-label fw-semibold">Location</label>
            <input type="text" class="form-control" id="location" name="location"
                   maxlength="255" placeholder="e.g. Main Campus, Block A">
          </div>

          <!-- Description -->
          <div class="mb-3">
            <label for="description" class="form-label fw-semibold">
              Description <span class="text-muted small fw-normal">(optional)</span>
            </label>
            <textarea class="form-control" id="description" name="description" rows="3" maxlength="500"></textarea>
          </div>

          <!-- Shop Image -->
          <div class="mb-3">
            <label class="form-label fw-semibold">
              Shop Image
              <span class="text-muted small fw-normal">(optional — JPG, PNG, WEBP, max 5 MB)</span>
            </label>
            <div class="shop-img-upload-box" id="shopImgBox" role="button" tabindex="0"
                 aria-label="Upload shop image. Click or drag and drop.">
              <i class="bi bi-image text-muted" style="font-size:2rem;"></i>
              <p class="mb-1 text-muted small mt-2">Click to select or drag &amp; drop an image</p>
              <p class="mb-0 text-muted" style="font-size:.72rem;">JPG · PNG · WEBP — max 5 MB</p>
              <input type="file" name="shop_image" id="shopImageInput"
                     accept="image/jpeg,image/png,image/webp"
                     class="d-none" aria-label="Upload shop image">
            </div>
            <img id="shopImgPreview" class="shop-img-preview" src="" alt="Shop image preview">
            <div class="shop-img-actions" id="shopImgActions">
              <button type="button" class="btn btn-sm btn-outline-secondary" id="shopImgChange">
                <i class="bi bi-arrow-repeat me-1"></i>Change
              </button>
              <button type="button" class="btn btn-sm btn-outline-danger" id="shopImgClear">
                <i class="bi bi-x-lg me-1"></i>Remove
              </button>
            </div>
            <div id="shopImgError" class="text-danger small mt-1 d-none"></div>
          </div>

          <!-- Status -->
          <div class="mb-4">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
              <label class="form-check-label fw-semibold" for="is_active">Active</label>
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-warning text-dark px-4" id="submitBtn">
              <i class="bi bi-check-lg me-1"></i>Create Shop
            </button>
            <a href="<?= url('admin/organization/shops') ?>" class="btn btn-outline-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card border-0 h-100 theme-tint-orange">
      <div class="card-body p-4">
        <h6 class="fw-bold mb-3" style="color:#c45800;"><i class="bi bi-info-circle me-2"></i>About Shop Codes</h6>
        <ul class="small text-muted ps-3 mb-3">
          <li class="mb-2">Shop Code follows the format <code>SHOP-001</code>, <code>SHOP-002</code>, etc.</li>
          <li class="mb-2">Generated and saved automatically by the system.</li>
          <li class="mb-2">Used in POS, sales, inventory, and transfer workflows.</li>
          <li>Cannot be changed after creation.</li>
        </ul>
        <hr class="my-3">
        <h6 class="fw-bold mb-2" style="color:#c45800;"><i class="bi bi-image me-2"></i>Shop Image</h6>
        <ul class="small text-muted ps-3 mb-0">
          <li class="mb-2">Displayed on the customer storefront.</li>
          <li class="mb-2">Recommended ratio: 16:9 or 4:3 landscape.</li>
          <li class="mb-2">Formats: JPG, PNG, WEBP — max 5 MB.</li>
          <li>Can be changed or removed later.</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php $view->section('scripts'); ?>
<script>
(function () {
  'use strict';

  /* ── Image upload preview ───────────────────────────── */
  var box     = document.getElementById('shopImgBox');
  var input   = document.getElementById('shopImageInput');
  var preview = document.getElementById('shopImgPreview');
  var actions = document.getElementById('shopImgActions');
  var errDiv  = document.getElementById('shopImgError');
  var MAX_BYTES = 5 * 1024 * 1024;
  var ALLOWED   = ['image/jpeg', 'image/png', 'image/webp'];

  function showPreview(file) {
    errDiv.classList.add('d-none');
    if (!file) return clearPreview();
    if (file.size > MAX_BYTES) {
      errDiv.textContent = 'Image must not exceed 5 MB.';
      errDiv.classList.remove('d-none');
      clearPreview(); return;
    }
    if (!ALLOWED.includes(file.type)) {
      errDiv.textContent = 'Only JPG, PNG, or WEBP images are allowed.';
      errDiv.classList.remove('d-none');
      clearPreview(); return;
    }
    var reader = new FileReader();
    reader.onload = function (e) {
      preview.src = e.target.result;
      preview.classList.add('show');
      actions.classList.add('show');
      box.style.display = 'none';
    };
    reader.readAsDataURL(file);
  }

  function clearPreview() {
    preview.classList.remove('show');
    preview.src = '';
    actions.classList.remove('show');
    box.style.display = '';
    input.value = '';
  }

  box.addEventListener('click', function () { input.click(); });
  box.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') input.click(); });
  box.addEventListener('dragover', function (e) { e.preventDefault(); box.classList.add('dragover'); });
  box.addEventListener('dragleave', function () { box.classList.remove('dragover'); });
  box.addEventListener('drop', function (e) {
    e.preventDefault(); box.classList.remove('dragover');
    var f = e.dataTransfer.files[0];
    if (f) { input.files = e.dataTransfer.files; showPreview(f); }
  });
  input.addEventListener('change', function () { showPreview(input.files[0] || null); });
  document.getElementById('shopImgChange').addEventListener('click', function () {
    clearPreview(); setTimeout(function () { input.click(); }, 50);
  });
  document.getElementById('shopImgClear').addEventListener('click', clearPreview);

  /* ── Form submit ────────────────────────────────────── */
  var form      = document.getElementById('shopForm');
  var submitBtn = document.getElementById('submitBtn');
  var errBox    = document.getElementById('formErrors');
  var errList   = document.getElementById('errorList');

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    errBox.classList.add('d-none'); errList.innerHTML = '';
    var name = document.getElementById('name').value.trim();
    if (!name) {
      errList.innerHTML = '<li>Shop name is required</li>';
      errBox.classList.remove('d-none');
      document.getElementById('name').focus(); return;
    }
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating…';
    var fd = new FormData(form);
    fd.set('is_active', document.getElementById('is_active').checked ? '1' : '0');
    fetch('<?= url('admin/organization/shops') ?>', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd
    })
    .then(function (r) { return r.json(); })
    .then(function (resp) {
      if (resp.success) {
        window.location.href = '<?= url('admin/organization/shops') ?>';
      } else {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Create Shop';
        Object.values(resp.errors || {}).forEach(function (m) { if (m) errList.innerHTML += '<li>' + m + '</li>'; });
        if (!errList.innerHTML) errList.innerHTML = '<li>' + (resp.message || 'Error') + '</li>';
        errBox.classList.remove('d-none');
      }
    })
    .catch(function () {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Create Shop';
      errList.innerHTML = '<li>Network error. Please try again.</li>';
      errBox.classList.remove('d-none');
    });
  });
})();
</script>
<?php $view->endSection(); ?>
