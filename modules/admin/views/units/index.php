<?php
/**
 * =========================================================
 * UNITS OF MEASUREMENT – LIST VIEW
 * =========================================================
 * File    : modules/admin/views/units/index.php
 * Purpose : List all units with search, type filter, pagination
 * Deps    : UnitController, UnitModel
 * =========================================================
 */
declare(strict_types=1);
$unitTypeBadge = static function (?string $type): string {
    if (!$type) return '<span class="badge bg-light text-dark border">—</span>';
    $map = [
        'weight' => 'bg-info text-dark',
        'volume' => 'bg-primary',
        'length' => 'bg-secondary',
        'piece'  => 'bg-success',
        'area'   => 'bg-warning text-dark',
        'other'  => 'bg-light text-dark border',
    ];
    $key = strtolower($type);
    return '<span class="badge ' . ($map[$key] ?? 'bg-light border') . '">' . e(ucfirst($key)) . '</span>';
};
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-rulers me-2 text-primary"></i>Units of Measurement</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= url('admin/products') ?>">Products</a></li>
        <li class="breadcrumb-item active">Units</li>
      </ol>
    </nav>
  </div>
  <a href="<?= url('admin/units/create') ?>" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i>Add Unit
  </a>
</div>

<!-- Stats -->
<div class="row g-2 mb-3">
  <?php foreach ([
      ['total_units',   'Total',    'bi-rulers',             'primary'],
      ['active_units',  'Active',   'bi-check-circle-fill',  'success'],
      ['inactive_units','Inactive', 'bi-slash-circle-fill',  'secondary'],
  ] as [$key,$label,$icon,$color]): ?>
  <div class="col-sm-3">
    <div class="card border-0 shadow-sm">
      <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
        <i class="bi <?= $icon ?> text-<?= $color ?> fs-5"></i>
        <div>
          <div class="fw-bold lh-1"><?= (int)($stats[$key] ?? 0) ?></div>
          <div class="text-muted" style="font-size:.72rem;"><?= $label ?></div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2 px-3">
    <form method="GET" action="<?= url('admin/units') ?>" id="filterForm" class="row g-2 align-items-end">
      <div class="col-md-4">
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
          <input type="search" class="form-control" name="search"
                 value="<?= e($filters['search']) ?>" placeholder="Code, name, abbreviation…">
        </div>
      </div>
      <div class="col-md-2">
        <select class="form-select form-select-sm" name="unit_type" id="typeFilter">
          <option value="">All Types</option>
          <?php foreach ($unit_types as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= ($filters['unit_type'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select class="form-select form-select-sm" name="status" id="statusFilter">
          <option value="">All Status</option>
          <option value="1" <?= ($filters['status'] ?? '') === '1' ? 'selected' : '' ?>>Active</option>
          <option value="0" <?= ($filters['status'] ?? '') === '0' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button>
        <?php if ($filters['search'] || ($filters['status'] ?? '') !== '' || ($filters['unit_type'] ?? '') !== ''): ?>
        <a href="<?= url('admin/units') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($units)): ?>
    <div class="text-center py-5 text-muted">
      <i class="bi bi-rulers display-4 d-block mb-3 opacity-25"></i>
      <h6>No units found</h6>
      <?php if ($filters['search'] || ($filters['status'] ?? '') !== ''): ?>
        <a href="<?= url('admin/units') ?>" class="btn btn-outline-secondary btn-sm mt-2">Clear Filters</a>
      <?php else: ?>
        <a href="<?= url('admin/units/create') ?>" class="btn btn-primary btn-sm mt-2">Add First Unit</a>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">#</th>
            <th>Name</th>
            <th>Abbr.</th>
            <th>Type</th>
            <th>Description</th>
            <th class="text-center">Products</th>
            <th class="text-center">Status</th>
            <th class="text-end pe-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($units as $unit): ?>
          <tr data-id="<?= (int)$unit['unit_id'] ?>">
            <td class="ps-3 text-muted small"><?= (int)$unit['unit_id'] ?></td>
            <td class="fw-semibold"><?= e($unit['unit_name']) ?></td>
            <td class="font-monospace text-primary"><?= e($unit['abbreviation']) ?></td>
            <td><?= $unitTypeBadge($unit['unit_type']) ?></td>
            <td class="text-muted small"><?= e(mb_strimwidth($unit['description'] ?? '', 0, 50, '…')) ?></td>
            <td class="text-center">
              <span class="badge <?= (int)$unit['product_count'] > 0 ? 'bg-primary' : 'bg-light text-muted border' ?>">
                <?= (int)$unit['product_count'] ?>
              </span>
            </td>
            <td class="text-center">
              <button class="btn btn-sm px-2 py-0 btn-toggle-status
                             <?= $unit['is_active'] ? 'btn-outline-success' : 'btn-outline-secondary' ?>"
                      data-id="<?= (int)$unit['unit_id'] ?>"
                      data-action="<?= url('admin/units/' . $unit['unit_id'] . '/toggle') ?>">
                <i class="bi <?= $unit['is_active'] ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-secondary' ?>"></i>
              </button>
            </td>
            <td class="text-end pe-3">
              <div class="d-flex justify-content-end gap-1">
                <a href="<?= url('admin/units/' . $unit['unit_id'] . '/edit') ?>"
                   class="btn btn-sm btn-outline-primary" title="Edit">
                  <i class="bi bi-pencil-fill"></i>
                </a>
                <?php if ((int)$unit['product_count'] === 0): ?>
                <button class="btn btn-sm btn-outline-danger btn-delete"
                        data-id="<?= (int)$unit['unit_id'] ?>"
                        data-name="<?= e($unit['unit_name']) ?>"
                        data-action="<?= url('admin/units/' . $unit['unit_id'] . '/delete') ?>"
                        title="Delete">
                  <i class="bi bi-trash3-fill"></i>
                </button>
                <?php else: ?>
                <button class="btn btn-sm btn-outline-danger disabled" disabled title="In use – cannot delete">
                  <i class="bi bi-trash3-fill"></i>
                </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="d-flex flex-wrap justify-content-between align-items-center px-3 py-3 border-top gap-2">
      <div class="text-muted small">
        Showing <strong><?= count($units) ?></strong> of <strong><?= $pagination['total_records'] ?></strong> units
      </div>
      <?php if ($pagination['total_pages'] > 1): ?>
      <nav>
        <ul class="pagination pagination-sm mb-0">
          <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
          <li class="page-item <?= $p === $pagination['current_page'] ? 'active' : '' ?>">
            <a class="page-link" href="<?= url('admin/units') ?>?page=<?= $p ?>&search=<?= urlencode($filters['search']) ?>&status=<?= urlencode($filters['status'] ?? '') ?>&unit_type=<?= urlencode($filters['unit_type'] ?? '') ?>">
              <?= $p ?>
            </a>
          </li>
          <?php endfor; ?>
        </ul>
      </nav>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php $view->section('scripts'); ?>
<script>
(function () {
  'use strict';
  var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

  function post(url, cb) {
    var fd = new FormData();
    fd.append('csrf_token', csrfToken);
    fetch(url, { method:'POST', credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd })
      .then(r => r.json())
      .then(resp => resp.success ? cb(resp) : AAM.toast(resp.message, 'error'))
      .catch(() => AAM.toast('Network error', 'error'));
  }

  document.querySelectorAll('.btn-toggle-status').forEach(btn => {
    btn.addEventListener('click', function () {
      post(this.dataset.action, resp => {
        AAM.toast(resp.message, 'success');
        setTimeout(() => location.reload(), 700);
      });
    });
  });

  document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', function () {
      if (!confirm('Delete unit "' + this.dataset.name + '"?')) return;
      post(this.dataset.action, resp => {
        AAM.toast(resp.message, 'success');
        setTimeout(() => location.reload(), 700);
      });
    });
  });

  ['typeFilter','statusFilter'].forEach(id => {
    document.getElementById(id)?.addEventListener('change', () => document.getElementById('filterForm').submit());
  });
})();
</script>
<?php $view->endSection(); ?>
