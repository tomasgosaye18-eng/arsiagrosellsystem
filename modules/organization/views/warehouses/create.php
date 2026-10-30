<?php
declare(strict_types=1);
$pageTitle      = 'Add New Warehouse';
$warehouseTypes ??= [];
$nextCode       ??= 'WH-???';
?>
<?php $view->section('styles'); ?>
<link rel="stylesheet" href="<?= $view->asset('css/organization.css') ?>">
<?php $view->endSection(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-building-fill me-2 text-primary"></i>Add New Warehouse</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('admin/organization') ?>">Organization</a></li>
        <li class="breadcrumb-item"><a href="<?= url('admin/organization/warehouses') ?>">Warehouses</a></li>
        <li class="breadcrumb-item active">Add New</li>
      </ol>
    </nav>
  </div>
  <a href="<?= url('admin/organization/warehouses') ?>" class="btn btn-outline-secondary btn-sm">
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
        <form id="warehouseForm" novalidate>
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

          <!-- Warehouse Code — system-generated, read-only -->
          <div class="mb-3">
            <label class="form-label fw-semibold text-muted small">Warehouse Code
              <span class="badge bg-secondary ms-1" style="font-size:.65rem;">AUTO-GENERATED</span>
            </label>
            <div class="input-group">
              <span class="input-group-text bg-light"><i class="bi bi-hash text-primary"></i></span>
              <input type="text" class="form-control bg-light font-monospace fw-bold text-primary"
                     value="<?= e($nextCode) ?>" readonly tabindex="-1">
              <span class="input-group-text bg-light text-muted"><i class="bi bi-lock-fill"></i></span>
            </div>
            <div class="form-text">Generated automatically. Cannot be modified.</div>
          </div>

          <!-- Warehouse Name -->
          <div class="mb-3">
            <label for="name" class="form-label fw-semibold">Warehouse Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="name" name="name"
                   maxlength="150" required autofocus placeholder="e.g. Central Warehouse">
          </div>

          <!-- Warehouse Type -->
          <div class="mb-3">
            <label for="warehouse_type" class="form-label fw-semibold">Type</label>
            <select class="form-select" id="warehouse_type" name="warehouse_type">
              <?php foreach ($warehouseTypes as $k => $v): ?>
              <option value="<?= e($k) ?>"><?= e($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Location -->
          <div class="mb-3">
            <label for="location" class="form-label fw-semibold">Location</label>
            <input type="text" class="form-control" id="location" name="location"
                   maxlength="255" placeholder="e.g. Main Campus, Block B">
          </div>

          <!-- Description -->
          <div class="mb-3">
            <label for="description" class="form-label fw-semibold">
              Description <span class="text-muted small fw-normal">(optional)</span>
            </label>
            <textarea class="form-control" id="description" name="description" rows="3" maxlength="500"></textarea>
          </div>

          <!-- Status -->
          <div class="mb-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
              <label class="form-check-label fw-semibold" for="is_active">Active</label>
            </div>
          </div>

          <!-- Seed Distribution Capability -->
          <div class="mb-4">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="seed_distribution_enabled"
                     name="seed_distribution_enabled" value="1">
              <label class="form-check-label fw-semibold" for="seed_distribution_enabled">
                <i class="bi bi-flower1 me-1 text-success"></i>Seed Distribution
              </label>
            </div>
            <div class="form-text">Enable this warehouse for improved seed distribution to external farmers.</div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4" id="submitBtn">
              <i class="bi bi-check-lg me-1"></i>Create Warehouse
            </button>
            <a href="<?= url('admin/organization/warehouses') ?>" class="btn btn-outline-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card border-0 bg-primary-subtle h-100">
      <div class="card-body p-4">
        <h6 class="fw-bold text-primary mb-3"><i class="bi bi-info-circle me-2"></i>About Warehouse Codes</h6>
        <ul class="small text-muted ps-3 mb-0">
          <li class="mb-2">Warehouse Code follows the format <code>WH-001</code>, <code>WH-002</code>, etc.</li>
          <li class="mb-2">Generated and saved automatically by the system.</li>
          <li class="mb-2">Used in receiving, inventory, and transfer workflows.</li>
          <li>Cannot be changed after creation.</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php $view->section('scripts'); ?>
<script>
(function () {
  'use strict';
  var form = document.getElementById('warehouseForm');
  var submitBtn = document.getElementById('submitBtn');
  var errBox = document.getElementById('formErrors');
  var errList = document.getElementById('errorList');

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    errBox.classList.add('d-none'); errList.innerHTML = '';

    var name = document.getElementById('name').value.trim();
    if (!name) {
      errList.innerHTML = '<li>Warehouse name is required</li>';
      errBox.classList.remove('d-none');
      document.getElementById('name').focus();
      return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating…';

    var fd = new FormData(form);
    fd.set('is_active', document.getElementById('is_active').checked ? '1' : '0');
    fd.set('seed_distribution_enabled', document.getElementById('seed_distribution_enabled').checked ? '1' : '0');
    fetch('<?= url('admin/organization/warehouses') ?>', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd
    })
    .then(function (r) { return r.json(); })
    .then(function (resp) {
      if (resp.success) {
        window.location.href = '<?= url('admin/organization/warehouses') ?>';
      } else {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Create Warehouse';
        Object.values(resp.errors || {}).forEach(function (m) { if (m) errList.innerHTML += '<li>' + m + '</li>'; });
        if (!errList.innerHTML) errList.innerHTML = '<li>' + (resp.message || 'Error') + '</li>';
        errBox.classList.remove('d-none');
      }
    })
    .catch(function () {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Create Warehouse';
      errList.innerHTML = '<li>Network error.</li>'; errBox.classList.remove('d-none');
    });
  });
})();
</script>
<?php $view->endSection(); ?>
