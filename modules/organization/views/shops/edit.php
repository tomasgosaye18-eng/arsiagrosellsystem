<?php
declare(strict_types=1);
$pageTitle  = 'Edit Shop';
$shop       ??= [];
$shopTypes  ??= [];
$shopId     = (int)($shop['id'] ?? $shop['shop_id'] ?? 0);
$shopCode   = $shop['shop_code'] ?? 'SHOP-???';
$shopName   = $shop['shop_name'] ?? $shop['name'] ?? '';
$shopType   = $shop['shop_type'] ?? 'RETAIL';
$loc        = $shop['location'] ?? '';
$desc       = $shop['description'] ?? '';
$active     = (bool)($shop['is_active'] ?? 1);
$shopImage  = $shop['shop_image'] ?? null;

// Build current image URL using the same pattern as UploadHelper::shopImageUrl()
$currentImgUrl = null;
if ($shopImage) {
    $currentImgUrl = upload_url('shops/' . $shopImage);
}
?>
<?php $view->section('styles'); ?>
<link rel="stylesheet" href="<?= $view->asset('css/organization.css') ?>">
<style>
.shop-img-upload-box{border:2px dashed #dee2e6;border-radius:10px;padding:20px;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;background:#fafafa;}
.shop-img-upload-box:hover,.shop-img-upload-box.dragover{border-color:#fd7e14;background:#fff8f3;}
.shop-img-preview-wrap{position:relative;display:inline-block;width:100%;margin-top:8px;}
.shop-img-preview{width:100%;max-height:220px;object-fit:cover;border-radius:8px;display:block;}
.shop-img-actions{display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;}
.shop-img-new-box{display:none;}
.shop-img-new-box.show{display:block;}
.shop-img-new-preview{width:100%;max-height:180px;object-fit:cover;border-radius:8px;display:none;margin-top:8px;}
.shop-img-new-preview.show{display:block;}
</style>
<?php $view->endSection(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-pencil-fill me-2" style="color:#fd7e14;"></i>Edit Shop</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('admin/organization') ?>">Organization</a></li>
        <li class="breadcrumb-item"><a href="<?= url('admin/organization/shops') ?>">Shops</a></li>
        <li class="breadcrumb-item active"><?= e($shopCode) ?></li>
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
        <form id="editShopForm" novalidate enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="remove_image" id="removeImageFlag" value="">

          <!-- Shop Code — READ-ONLY -->
          <div class="mb-3">
            <label class="form-label fw-semibold text-muted small">Shop Code
              <span class="badge bg-secondary ms-1" style="font-size:.65rem;">READ-ONLY</span>
            </label>
            <div class="input-group">
              <span class="input-group-text bg-light"><i class="bi bi-hash" style="color:#fd7e14;"></i></span>
              <input type="text" class="form-control bg-light font-monospace fw-bold"
                     style="color:#c45800;" value="<?= e($shopCode) ?>" readonly tabindex="-1">
              <span class="input-group-text bg-light text-muted"><i class="bi bi-lock-fill"></i></span>
            </div>
            <div class="form-text">System-generated. Cannot be modified.</div>
          </div>

          <!-- Shop Name -->
          <div class="mb-3">
            <label for="name" class="form-label fw-semibold">Shop Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="name" name="name"
                   value="<?= e($shopName) ?>" maxlength="150" required>
          </div>

          <!-- Shop Type -->
          <div class="mb-3">
            <label for="shop_type" class="form-label fw-semibold">Shop Type</label>
            <select class="form-select" id="shop_type" name="shop_type">
              <?php foreach ($shopTypes as $k => $v): ?>
              <option value="<?= e($k) ?>" <?= $shopType === $k ? 'selected' : '' ?>><?= e($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Location -->
          <div class="mb-3">
            <label for="location" class="form-label fw-semibold">Location</label>
            <input type="text" class="form-control" id="location" name="location"
                   value="<?= e($loc) ?>" maxlength="255">
          </div>

          <!-- Description -->
          <div class="mb-3">
            <label for="description" class="form-label fw-semibold">
              Description <span class="text-muted small fw-normal">(optional)</span>
            </label>
            <textarea class="form-control" id="description" name="description"
                      rows="3" maxlength="500"><?= e($desc) ?></textarea>
          </div>

          <!-- Shop Image -->
          <div class="mb-3">
            <label class="form-label fw-semibold">
              Shop Image
              <span class="text-muted small fw-normal">(JPG, PNG, WEBP — max 5 MB)</span>
            </label>

            <?php if ($currentImgUrl): ?>
            <!-- Existing image -->
            <div id="currentImgSection">
              <div class="shop-img-preview-wrap">
                <img id="currentImgEl"
                     src="<?= e($currentImgUrl) ?>"
                     alt="Current shop image for <?= e($shopName) ?>"
                     class="shop-img-preview"
                     onerror="this.parentElement.innerHTML='<div class=\'text-muted small p-2\'>Image file not found on disk.</div>'">
              </div>
              <div class="shop-img-actions">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnChangeImg">
                  <i class="bi bi-arrow-repeat me-1"></i>Change Image
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" id="btnRemoveImg">
                  <i class="bi bi-trash3 me-1"></i>Remove Image
                </button>
              </div>
            </div>
            <?php else: ?>
            <!-- No existing image — show upload box -->
            <div id="noImgSection">
              <div class="shop-img-upload-box" id="shopImgBox" role="button" tabindex="0"
                   aria-label="Upload shop image. Click or drag and drop.">
                <i class="bi bi-image text-muted" style="font-size:2rem;"></i>
                <p class="mb-1 text-muted small mt-2">Click to select or drag &amp; drop an image</p>
                <p class="mb-0 text-muted" style="font-size:.72rem;">JPG · PNG · WEBP — max 5 MB</p>
                <input type="file" name="shop_image" id="shopImageInput"
                       accept="image/jpeg,image/png,image/webp"
                       class="d-none" aria-label="Upload shop image">
              </div>
              <img id="newImgPreview" class="shop-img-new-preview" src="" alt="New shop image preview">
              <div id="newImgActions" class="shop-img-actions" style="display:none;">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="newImgChange">
                  <i class="bi bi-arrow-repeat me-1"></i>Change
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" id="newImgClear">
                  <i class="bi bi-x-lg me-1"></i>Remove
                </button>
              </div>
            </div>
            <?php endif; ?>

            <!-- Change-image section (hidden by default when image already exists) -->
            <div class="shop-img-new-box mt-2 <?= $currentImgUrl ? '' : 'd-none' ?>" id="changeImgSection">
              <div class="shop-img-upload-box" id="shopImgBoxChange" role="button" tabindex="0"
                   aria-label="Select new shop image">
                <i class="bi bi-cloud-upload text-muted" style="font-size:1.8rem;"></i>
                <p class="mb-0 text-muted small mt-1">Click to select new image</p>
                <input type="file" name="shop_image" id="shopImageInputChange"
                       accept="image/jpeg,image/png,image/webp"
                       class="d-none" aria-label="Select replacement shop image">
              </div>
              <img id="changeImgPreview" class="shop-img-new-preview" src="" alt="New shop image preview">
              <div class="shop-img-actions mt-1" id="changeImgActions" style="display:none;">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="changeImgReSelect">
                  <i class="bi bi-arrow-repeat me-1"></i>Re-select
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" id="changeImgCancel">
                  <i class="bi bi-x-lg me-1"></i>Cancel
                </button>
              </div>
            </div>

            <div id="shopImgError" class="text-danger small mt-1 d-none"></div>
          </div>

          <!-- Status -->
          <div class="mb-4">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                     value="1" <?= $active ? 'checked' : '' ?>>
              <label class="form-check-label fw-semibold" for="is_active">Active</label>
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-warning text-dark px-4" id="submitBtn">
              <i class="bi bi-check-lg me-1"></i>Save Changes
            </button>
            <a href="<?= url('admin/organization/shops') ?>" class="btn btn-outline-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card border-0 bg-light h-100">
      <div class="card-body p-4">
        <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>Editing Shop</h6>
        <table class="table table-sm table-borderless small mb-0">
          <tr><td class="text-muted">Code</td><td class="fw-bold font-monospace" style="color:#c45800;"><?= e($shopCode) ?></td></tr>
          <tr><td class="text-muted">ID</td><td class="fw-bold">#<?= $shopId ?></td></tr>
          <tr><td class="text-muted">Created</td><td><?= isset($shop['created_at']) ? e(date('d/m/Y', strtotime($shop['created_at']))) : '—' ?></td></tr>
        </table>
        <hr class="my-3">
        <p class="small text-muted mb-0">
          <i class="bi bi-shield-check me-1" style="color:#fd7e14;"></i>
          Editing will not affect existing POS sessions, sales, or assignments.
          The Shop Code and ID are permanent.
        </p>
        <hr class="my-3">
        <div class="small text-muted">
          <i class="bi bi-image me-1" style="color:#fd7e14;"></i>
          <strong>Shop Image</strong><br>
          Displayed on the customer storefront.<br>
          Removing an image does not affect shop data, inventory, or orders.
        </div>
      </div>
    </div>
  </div>
</div>

<?php $view->section('scripts'); ?>
<script>
(function () {
  'use strict';

  var csrf      = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  var MAX_BYTES = 5 * 1024 * 1024;
  var ALLOWED   = ['image/jpeg', 'image/png', 'image/webp'];
  var errDiv    = document.getElementById('shopImgError');

  function imgError(msg) {
    errDiv.textContent = msg;
    errDiv.classList.remove('d-none');
  }
  function imgClearError() {
    errDiv.classList.add('d-none');
  }

  function validateFile(file) {
    if (!file) return true;
    if (file.size > MAX_BYTES) { imgError('Image must not exceed 5 MB.'); return false; }
    if (!ALLOWED.includes(file.type)) { imgError('Only JPG, PNG, or WEBP images are allowed.'); return false; }
    imgClearError();
    return true;
  }

  function bindUploadBox(boxId, inputId, previewId, actionsId, cancelBtnId, reSelectBtnId) {
    var box     = document.getElementById(boxId);
    var input   = document.getElementById(inputId);
    var preview = document.getElementById(previewId);
    var actions = document.getElementById(actionsId);
    if (!box || !input) return;

    function show(file) {
      if (!validateFile(file)) return;
      var reader = new FileReader();
      reader.onload = function (e) {
        if (preview) { preview.src = e.target.result; preview.classList.add('show'); preview.style.display = 'block'; }
        if (actions) { actions.style.display = 'flex'; }
        box.style.display = 'none';
      };
      reader.readAsDataURL(file);
    }

    box.addEventListener('click', function () { input.click(); });
    box.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') input.click(); });
    box.addEventListener('dragover', function (e) { e.preventDefault(); box.classList.add('dragover'); });
    box.addEventListener('dragleave', function () { box.classList.remove('dragover'); });
    box.addEventListener('drop', function (e) {
      e.preventDefault(); box.classList.remove('dragover');
      var f = e.dataTransfer.files[0];
      if (f) { input.files = e.dataTransfer.files; show(f); }
    });
    input.addEventListener('change', function () { show(input.files[0] || null); });

    if (reSelectBtnId) {
      var reSelBtn = document.getElementById(reSelectBtnId);
      if (reSelBtn) reSelBtn.addEventListener('click', function () { input.click(); });
    }
    if (cancelBtnId) {
      var cancelBtn = document.getElementById(cancelBtnId);
      if (cancelBtn) cancelBtn.addEventListener('click', function () {
        if (preview) { preview.classList.remove('show'); preview.style.display = 'none'; preview.src = ''; }
        if (actions) { actions.style.display = 'none'; }
        box.style.display = '';
        input.value = '';
        imgClearError();
        // If this is the change-section cancel, hide the whole section
        var changeSection = document.getElementById('changeImgSection');
        if (changeSection) { changeSection.style.display = 'none'; }
        var currentSection = document.getElementById('currentImgSection');
        if (currentSection) { currentSection.style.display = ''; }
      });
    }
  }

  <?php if ($currentImgUrl): ?>
  // Has existing image
  var btnChange = document.getElementById('btnChangeImg');
  var btnRemove = document.getElementById('btnRemoveImg');
  var currentSection = document.getElementById('currentImgSection');
  var changeSection  = document.getElementById('changeImgSection');
  var removeFlag     = document.getElementById('removeImageFlag');

  if (btnChange) {
    btnChange.addEventListener('click', function () {
      currentSection.style.display = 'none';
      changeSection.style.display = 'block';
      removeFlag.value = '';
    });
  }

  if (btnRemove) {
    btnRemove.addEventListener('click', function () {
      if (!confirm('Remove this shop image? The shop itself will not be affected.')) return;
      // Send AJAX request to remove-image endpoint
      var fd = new FormData();
      fd.append('csrf_token', csrf);
      fetch('<?= url('admin/organization/shops/' . $shopId . '/remove-image') ?>', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd
      })
      .then(function (r) { return r.json(); })
      .then(function (resp) {
        if (resp.success) {
          currentSection.innerHTML =
            '<div class="text-muted small p-2"><i class="bi bi-image me-1"></i>No image. Use the form below to upload one.</div>';
          // Show a basic upload box in place
          var noImgDiv = document.createElement('div');
          noImgDiv.innerHTML =
            '<div class="shop-img-upload-box mt-2" id="shopImgBox2" role="button" tabindex="0">' +
            '<i class="bi bi-image text-muted" style="font-size:2rem;"></i>' +
            '<p class="mb-1 text-muted small mt-2">Click to select an image</p>' +
            '<input type="file" name="shop_image" id="shopImageInput2" accept="image/jpeg,image/png,image/webp" class="d-none">' +
            '</div>' +
            '<img id="newPreview2" style="display:none;width:100%;max-height:180px;object-fit:cover;border-radius:8px;margin-top:8px;" src="" alt="">';
          changeSection.parentNode.insertBefore(noImgDiv, changeSection);
          // Wire up the new box
          var newBox = document.getElementById('shopImgBox2');
          var newInput = document.getElementById('shopImageInput2');
          var newPrev  = document.getElementById('newPreview2');
          if (newBox && newInput) {
            newBox.addEventListener('click', function () { newInput.click(); });
            newInput.addEventListener('change', function () {
              var f = newInput.files[0];
              if (!validateFile(f)) return;
              var reader = new FileReader();
              reader.onload = function (e) {
                if (newPrev) { newPrev.src = e.target.result; newPrev.style.display = 'block'; }
                newBox.style.display = 'none';
              };
              reader.readAsDataURL(f);
            });
          }
        } else {
          alert(resp.message || 'Failed to remove image.');
        }
      })
      .catch(function () { alert('Network error.'); });
    });
  }

  // Bind the change-image upload box
  bindUploadBox('shopImgBoxChange', 'shopImageInputChange', 'changeImgPreview', 'changeImgActions', 'changeImgCancel', 'changeImgReSelect');

  <?php else: ?>
  // No existing image — bind regular upload box
  bindUploadBox('shopImgBox', 'shopImageInput', 'newImgPreview', 'newImgActions', 'newImgClear', 'newImgChange');
  <?php endif; ?>

  /* ── Form submit ─────────────────────────────────────── */
  var form      = document.getElementById('editShopForm');
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
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving…';

    // ── FIX: prevent duplicate shop_image entries in FormData ──
    // The form may contain two <input name="shop_image"> elements
    // (shopImageInput and shopImageInputChange). Only one should be
    // active at a time. Temporarily disable all empty file inputs
    // so FormData only includes the one that actually has a file.
    var allFileInputs = form.querySelectorAll('input[type="file"][name="shop_image"]');
    var tempDisabled = [];
    allFileInputs.forEach(function (inp) {
      if (!inp.files || inp.files.length === 0) {
        inp.disabled = true;
        tempDisabled.push(inp);
      }
    });

    var fd = new FormData(form);

    // Re-enable the temporarily disabled inputs
    tempDisabled.forEach(function (inp) { inp.disabled = false; });

    fd.set('is_active', document.getElementById('is_active').checked ? '1' : '0');
    fetch('<?= url('admin/organization/shops/' . $shopId . '/update') ?>', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd
    })
    .then(function (r) { return r.json(); })
    .then(function (resp) {
      if (resp.success) {
        window.location.href = '<?= url('admin/organization/shops') ?>';
      } else {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Save Changes';
        Object.values(resp.errors || {}).forEach(function (m) { if (m) errList.innerHTML += '<li>' + m + '</li>'; });
        if (!errList.innerHTML) errList.innerHTML = '<li>' + (resp.message || 'Error') + '</li>';
        errBox.classList.remove('d-none');
      }
    })
    .catch(function () {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Save Changes';
      errList.innerHTML = '<li>Network error. Please try again.</li>';
      errBox.classList.remove('d-none');
    });
  });
})();
</script>
<?php $view->endSection(); ?>
