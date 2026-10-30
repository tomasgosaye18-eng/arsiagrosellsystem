<?php
/**
 * PRODUCT CATEGORIES – CREATE FORM
 */
declare(strict_types=1);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0">Add New Category</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= url('admin/categories') ?>">Categories</a></li>
        <li class="breadcrumb-item active">Create</li>
      </ol>
    </nav>
  </div>
  <a href="<?= url('admin/categories') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
</div>

<div class="row justify-content-center">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-bottom py-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-plus-circle me-2 text-primary"></i>Category Details</h6>
      </div>
      <div class="card-body p-4">
        <form id="categoryForm">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

          <!-- Category Name -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="category_name" id="category_name"
                   maxlength="100" placeholder="e.g. Grains & Cereals" autocomplete="off">
            <div class="invalid-feedback" id="err_category_name"></div>
          </div>

          <!-- Description -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Description</label>
            <textarea class="form-control" name="description" rows="3"
                      maxlength="1000" placeholder="Brief description of this category…"></textarea>
          </div>

          <!-- Order + Status -->
          <div class="row g-3 mb-3">
            <div class="col-md-5">
              <label class="form-label fw-semibold">Display Order</label>
              <input type="number" class="form-control" name="display_order"
                     value="0" min="0" max="9999">
            </div>
            <div class="col-md-7">
              <label class="form-label fw-semibold">Status</label>
              <div class="d-flex align-items-center mt-2 gap-2">
                <div class="form-check form-switch mb-0">
                  <input class="form-check-input" type="checkbox" name="is_active"
                         id="is_active" value="1" checked>
                  <label class="form-check-label fw-semibold" for="is_active" id="statusLabel">Active</label>
                </div>
              </div>
            </div>
          </div>

          <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary px-4" id="submitBtn">
              <span class="spinner-border spinner-border-sm me-2 d-none" id="spinner"></span>
              <i class="bi bi-check-lg me-1"></i>Save Category
            </button>
            <a href="<?= url('admin/categories') ?>" class="btn btn-outline-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php $view->section('scripts'); ?>
<script>
(function () {
  'use strict';

  var toggle      = document.getElementById('is_active');
  var statusLabel = document.getElementById('statusLabel');
  toggle.addEventListener('change', function () {
    statusLabel.textContent = this.checked ? 'Active' : 'Inactive';
  });

  var form      = document.getElementById('categoryForm');
  var submitBtn = document.getElementById('submitBtn');
  var spinner   = document.getElementById('spinner');

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    clearErrors();
    submitBtn.disabled = true;
    spinner.classList.remove('d-none');

    var fd = new FormData(form);
    if (!toggle.checked) { fd.set('is_active', '0'); }

    fetch('<?= url('admin/categories') ?>', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: fd
    })
    .then(r => r.json())
    .then(function (resp) {
      submitBtn.disabled = false;
      spinner.classList.add('d-none');
      if (resp.success) {
        AAM.toast(resp.message, 'success');
        setTimeout(() => window.location.href = resp.redirect || '<?= url('admin/categories') ?>', 900);
      } else {
        if (resp.errors) showErrors(resp.errors);
        else AAM.toast(resp.message || 'Validation failed', 'error');
      }
    })
    .catch(function () {
      submitBtn.disabled = false;
      spinner.classList.add('d-none');
      AAM.toast('Network error', 'error');
    });
  });

  function showErrors(errors) {
    Object.keys(errors).forEach(function (key) {
      var input = document.querySelector('[name="' + key + '"]');
      var errEl = document.getElementById('err_' + key);
      if (input) input.classList.add('is-invalid');
      if (errEl) { errEl.textContent = errors[key]; errEl.style.display = 'block'; }
    });
  }

  function clearErrors() {
    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    document.querySelectorAll('[id^="err_"]').forEach(el => { el.textContent = ''; el.style.display = ''; });
  }
})();
</script>
<?php $view->endSection(); ?>
