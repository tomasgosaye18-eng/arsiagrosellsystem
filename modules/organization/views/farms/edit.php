<?php
declare(strict_types=1);
$pageTitle = 'Edit Farm';
$farm      ??= [];
$farmTypes ??= [];
$farmId    = (int)($farm['id'] ?? $farm['farm_id'] ?? 0);
$farmCode  = $farm['farm_code'] ?? 'FARM-???';
$farmName  = $farm['farm_name'] ?? $farm['name'] ?? '';
$farmType  = $farm['farm_type'] ?? 'MIXED';
$location  = $farm['location'] ?? '';
$desc      = $farm['description'] ?? '';
$isActive  = (bool)($farm['is_active'] ?? 1);
?>
<?php $view->section('styles'); ?>
<link rel="stylesheet" href="<?= $view->asset('css/organization.css') ?>">
<?php $view->endSection(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-pencil-fill me-2 text-success"></i>Edit Farm</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('admin/organization') ?>">Organization</a></li>
        <li class="breadcrumb-item"><a href="<?= url('admin/organization/farms') ?>">Farms</a></li>
        <li class="breadcrumb-item active"><?= e($farmCode) ?></li>
      </ol>
    </nav>
  </div>
  <a href="<?= url('admin/organization/farms') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
</div>

<div id="formErrors" class="alert alert-danger d-none mb-3">
  <strong>Please fix:</strong>
  <ul class="mb-0 mt-1 ps-3" id="errorList"></ul>
</div>

<div class="row">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <form id="editFarmForm" novalidate>
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

          <!-- Farm Code — READ-ONLY, never editable -->
          <div class="mb-3">
            <label class="form-label fw-semibold text-muted small">Farm Code
              <span class="badge bg-secondary ms-1" style="font-size:.65rem;">READ-ONLY</span>
            </label>
            <div class="input-group">
              <span class="input-group-text bg-light"><i class="bi bi-hash text-success"></i></span>
              <input type="text" class="form-control bg-light font-monospace fw-bold text-success"
                     value="<?= e($farmCode) ?>" readonly tabindex="-1"
                     title="Farm Code cannot be changed">
              <span class="input-group-text bg-light text-muted"><i class="bi bi-lock-fill"></i></span>
            </div>
            <div class="form-text">System-generated identifier. Cannot be modified.</div>
          </div>

          <!-- Farm Name -->
          <div class="mb-3">
            <label for="name" class="form-label fw-semibold">Farm Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="name" name="name"
                   value="<?= e($farmName) ?>" maxlength="150" required>
          </div>

          <!-- Farm Type -->
          <div class="mb-3">
            <label for="farm_type" class="form-label fw-semibold">Farm Type</label>
            <select class="form-select" id="farm_type" name="farm_type">
              <?php foreach ($farmTypes as $k => $v): ?>
              <option value="<?= e($k) ?>" <?= $farmType === $k ? 'selected' : '' ?>><?= e($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Location -->
          <div class="mb-3">
            <label for="location" class="form-label fw-semibold">Location</label>
            <input type="text" class="form-control" id="location" name="location"
                   value="<?= e($location) ?>" maxlength="255">
          </div>

          <!-- Description -->
          <div class="mb-3">
            <label for="description" class="form-label fw-semibold">
              Description <span class="text-muted small fw-normal">(optional)</span>
            </label>
            <textarea class="form-control" id="description" name="description"
                      rows="3" maxlength="500"><?= e($desc) ?></textarea>
          </div>

          <!-- Status -->
          <div class="mb-4">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                     value="1" <?= $isActive ? 'checked' : '' ?>>
              <label class="form-check-label fw-semibold" for="is_active">Active</label>
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-success px-4" id="submitBtn">
              <i class="bi bi-check-lg me-1"></i>Save Changes
            </button>
            <a href="<?= url('admin/organization/farms') ?>" class="btn btn-outline-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Info panel -->
  <div class="col-lg-5">
    <div class="card border-0 bg-light h-100">
      <div class="card-body p-4">
        <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>Editing Farm</h6>
        <table class="table table-sm table-borderless small mb-0">
          <tr><td class="text-muted">Farm Code</td><td class="fw-bold font-monospace text-success"><?= e($farmCode) ?></td></tr>
          <tr><td class="text-muted">Farm ID</td><td class="fw-bold">#<?= $farmId ?></td></tr>
          <tr><td class="text-muted">Created</td><td><?= isset($farm['created_at']) ? e(date('d/m/Y', strtotime($farm['created_at']))) : '—' ?></td></tr>
        </table>
        <hr class="my-3">
        <p class="small text-muted mb-0">
          <i class="bi bi-shield-check text-success me-1"></i>
          Editing this farm will not affect existing assignments or workflows.
          The Farm Code and ID remain permanent.
        </p>
      </div>
    </div>
  </div>
</div>

<?php $view->section('scripts'); ?>
<script>
(function () {
  'use strict';
  var form      = document.getElementById('editFarmForm');
  var submitBtn = document.getElementById('submitBtn');
  var errBox    = document.getElementById('formErrors');
  var errList   = document.getElementById('errorList');

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    errBox.classList.add('d-none');
    errList.innerHTML = '';

    var name = document.getElementById('name').value.trim();
    if (!name) {
      errList.innerHTML = '<li>Farm name is required</li>';
      errBox.classList.remove('d-none');
      document.getElementById('name').focus();
      return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving…';

    var fd = new FormData(form);
    fd.set('is_active', document.getElementById('is_active').checked ? '1' : '0');

    fetch('<?= url('admin/organization/farms/' . $farmId . '/update') ?>', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: fd
    })
    .then(function (r) { return r.json(); })
    .then(function (resp) {
      if (resp.success) {
        window.location.href = '<?= url('admin/organization/farms') ?>';
      } else {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Save Changes';
        var errs = resp.errors || {};
        if (Object.keys(errs).length) {
          Object.values(errs).forEach(function (m) { if (m) errList.innerHTML += '<li>' + m + '</li>'; });
        } else {
          errList.innerHTML = '<li>' + (resp.message || 'An error occurred') + '</li>';
        }
        errBox.classList.remove('d-none');
        errBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
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
