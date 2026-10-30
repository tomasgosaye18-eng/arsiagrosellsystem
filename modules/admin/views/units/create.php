<?php
/**
 * UNITS – CREATE FORM
 */
declare(strict_types=1);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-rulers me-2 text-primary"></i>Add New Unit</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= url('admin/units') ?>">Units</a></li>
        <li class="breadcrumb-item active">Create</li>
      </ol>
    </nav>
  </div>
  <a href="<?= url('admin/units') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
</div>

<div class="row justify-content-center">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-bottom py-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-plus-circle me-2 text-primary"></i>Unit Details</h6>
      </div>
      <div class="card-body p-4">
        <form id="unitForm">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

          <!-- Name + Abbreviation -->
          <div class="row g-3 mb-3">
            <div class="col-md-8">
              <label class="form-label fw-semibold">Unit Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="unit_name"
                     maxlength="60" placeholder="e.g. Kilogram" autocomplete="off">
              <div class="invalid-feedback" id="err_unit_name"></div>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Abbreviation <span class="text-danger">*</span></label>
              <input type="text" class="form-control font-monospace" name="abbreviation"
                     maxlength="20" placeholder="e.g. kg" autocomplete="off">
              <div class="invalid-feedback" id="err_abbreviation"></div>
            </div>
          </div>

          <!-- Type + Status -->
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Unit Type</label>
              <select class="form-select" name="unit_type">
                <option value="">— Select Type —</option>
                <?php foreach ($unit_types as $k => $v): ?>
                <option value="<?= e($k) ?>"><?= e($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Status</label>
              <div class="mt-2">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" name="is_active"
                         value="1" id="is_active" checked>
                  <label class="form-check-label fw-semibold" for="is_active" id="statusLabel">Active</label>
                </div>
              </div>
            </div>
          </div>

          <!-- Description -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Description</label>
            <textarea class="form-control" name="description" rows="2"
                      maxlength="255" placeholder="Optional description…"></textarea>
          </div>

          <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary px-4" id="submitBtn">
              <span class="spinner-border spinner-border-sm me-2 d-none" id="spinner"></span>
              <i class="bi bi-check-lg me-1"></i>Save Unit
            </button>
            <a href="<?= url('admin/units') ?>" class="btn btn-outline-secondary">Cancel</a>
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

  document.getElementById('is_active').addEventListener('change', function () {
    document.getElementById('statusLabel').textContent = this.checked ? 'Active' : 'Inactive';
  });

  var form      = document.getElementById('unitForm');
  var submitBtn = document.getElementById('submitBtn');
  var spinner   = document.getElementById('spinner');

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    clearErrors();
    submitBtn.disabled = true;
    spinner.classList.remove('d-none');

    var fd = new FormData(form);
    if (!document.getElementById('is_active').checked) { fd.set('is_active', '0'); }

    fetch('<?= url('admin/units') ?>', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd
    })
    .then(r => r.json())
    .then(function (resp) {
      submitBtn.disabled = false;
      spinner.classList.add('d-none');
      if (resp.success) {
        AAM.toast(resp.message, 'success');
        setTimeout(() => window.location.href = resp.redirect || '<?= url('admin/units') ?>', 900);
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
      var el  = document.querySelector('[name="' + key + '"]');
      var err = document.getElementById('err_' + key);
      if (el)  el.classList.add('is-invalid');
      if (err) { err.textContent = errors[key]; err.style.display = 'block'; }
    });
  }
  function clearErrors() {
    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    document.querySelectorAll('[id^="err_"]').forEach(el => { el.textContent = ''; el.style.display = ''; });
  }
})();
</script>
<?php $view->endSection(); ?>
