<?php
declare(strict_types=1);
$pageTitle = 'Farm Management';
$canManage = in_array(
    \AAM\Core\SessionManager::userRole() ?? '',
    [ROLE_ADMIN], true
);
$farmTypes ??= [];
$farms     ??= [];
$stats     ??= [];
$filters   ??= [];
$pagination ??= ['from'=>0,'to'=>0,'total_records'=>0,'total_pages'=>0,'current_page'=>1];
?>
<?php $view->section('styles'); ?>
<link rel="stylesheet" href="<?= $view->asset('css/organization.css') ?>">
<?php $view->endSection(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-flower1-fill me-2 text-success"></i>Farm Management</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('admin/organization') ?>">Organization</a></li>
        <li class="breadcrumb-item active">Farms</li>
      </ol>
    </nav>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($canManage): ?>
    <a href="<?= url('admin/organization/farms/create') ?>" class="btn btn-success btn-sm">
      <i class="bi bi-plus-lg me-1"></i>Add Farm
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- Stats -->
<div class="row g-2 mb-3">
  <?php foreach ([
    ['total',   'Total',    'bi-flower1-fill',      'success'],
    ['active',  'Active',   'bi-check-circle-fill', 'success'],
    ['inactive','Inactive', 'bi-slash-circle-fill', 'secondary'],
  ] as [$k, $label, $icon, $color]): ?>
  <div class="col-6 col-sm-4 col-md-2">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
        <i class="bi <?= $icon ?> text-<?= $color ?> fs-5"></i>
        <div>
          <div class="fw-bold lh-1"><?= (int)($stats[$k] ?? 0) ?></div>
          <div class="text-muted" style="font-size:.7rem;"><?= $label ?></div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filter -->
<div class="filter-bar mb-3">
  <form method="GET" action="<?= url('admin/organization/farms') ?>" class="row g-2 align-items-end" id="filterForm">
    <div class="col-md-4">
      <div class="input-group input-group-sm">
        <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
        <input type="search" class="form-control" name="search" id="searchInput"
               value="<?= e($filters['search'] ?? '') ?>" placeholder="Search farm name or location…">
      </div>
    </div>
    <div class="col-md-2">
      <select class="form-select form-select-sm" name="farm_type" id="typeFilter">
        <option value="">All Types</option>
        <?php foreach ($farmTypes as $k => $v): ?>
        <option value="<?= e($k) ?>" <?= ($filters['farm_type'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <select class="form-select form-select-sm" name="is_active" id="statusFilter">
        <option value="">All Status</option>
        <option value="1" <?= ($filters['is_active'] ?? '') === '1' ? 'selected' : '' ?>>Active</option>
        <option value="0" <?= ($filters['is_active'] ?? '') === '0' ? 'selected' : '' ?>>Inactive</option>
      </select>
    </div>
    <div class="col-md-2 d-flex gap-1">
      <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button>
      <?php if (!empty(array_filter($filters))): ?>
      <a href="<?= url('admin/organization/farms') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($farms)): ?>
    <div class="empty-state text-center py-5">
      <i class="bi bi-flower1 display-4 text-muted d-block mb-2"></i>
      <h6 class="text-muted">No farms found</h6>
      <?php if ($canManage): ?>
      <a href="<?= url('admin/organization/farms/create') ?>" class="btn btn-success btn-sm mt-2">
        <i class="bi bi-plus-lg me-1"></i>Add First Farm
      </a>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Farm</th>
            <th>Type</th>
            <th>Location</th>
            <th>Manager</th>
            <th class="text-center">Status</th>
            <th>Created</th>
            <?php if ($canManage): ?><th class="pe-3 text-end">Actions</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($farms as $farm): ?>
          <?php
            $farmId   = (int)($farm['farm_id'] ?? $farm['id']);
            $farmName = $farm['farm_name'] ?? $farm['name'] ?? '—';
            $farmCode = $farm['farm_code'] ?? 'FRM' . str_pad((string)$farmId, 3, '0', STR_PAD_LEFT);
            $isActive = (bool)($farm['is_active'] ?? 1);
          ?>
          <tr>
            <td class="ps-3">
              <div class="fw-semibold">
                <a href="<?= url('admin/organization/farms/' . $farmId) ?>" class="text-decoration-none text-dark">
                  <?= e($farmName) ?>
                </a>
              </div>
              <small class="text-muted font-monospace"><?= e($farmCode) ?></small>
            </td>
            <td>
              <span class="badge bg-success-subtle text-success"><?= e($farmTypes[$farm['farm_type'] ?? ''] ?? ($farm['farm_type'] ?? 'MIXED')) ?></span>
            </td>
            <td class="small text-muted">
              <?php $loc = $farm['location'] ?? ''; ?>
              <?= $loc ? e($loc) : '—' ?>
            </td>
            <td class="small">
              <?= $farm['manager_name'] ?? '<span class="text-muted fst-italic">Unassigned</span>' ?>
            </td>
            <td class="text-center">
              <?php if ($canManage): ?>
              <button class="btn btn-sm border-0 bg-transparent p-0 btn-toggle-status"
                      data-id="<?= $farmId ?>"
                      data-action="<?= url('admin/organization/farms/' . $farmId . '/toggle') ?>"
                      data-current="<?= (int)$isActive ?>">
                <span class="badge <?= $isActive ? 'bg-success' : 'bg-secondary' ?>">
                  <?= $isActive ? 'Active' : 'Inactive' ?>
                </span>
              </button>
              <?php else: ?>
              <span class="badge <?= $isActive ? 'bg-success' : 'bg-secondary' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span>
              <?php endif; ?>
            </td>
            <td class="small text-muted"><?= e(date('d/m/Y', strtotime($farm['created_at'] ?? 'now'))) ?></td>
            <?php if ($canManage): ?>
            <td class="pe-3 text-end">
              <div class="d-flex justify-content-end gap-1">
                <a href="<?= url('admin/organization/farms/' . $farmId . '/edit') ?>"
                   class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil-fill"></i></a>
                <?php if ($isActive): ?>
                <button class="btn btn-sm btn-outline-danger btn-delete"
                        data-id="<?= $farmId ?>"
                        data-name="<?= e($farmName) ?>"
                        data-action="<?= url('admin/organization/farms/' . $farmId . '/delete') ?>"
                        title="Delete"><i class="bi bi-trash3-fill"></i></button>
                <?php else: ?>
                <button class="btn btn-sm btn-outline-success btn-restore"
                        data-id="<?= $farmId ?>"
                        data-action="<?= url('admin/organization/farms/' . $farmId . '/restore') ?>"
                        title="Restore"><i class="bi bi-arrow-counterclockwise"></i></button>
                <?php endif; ?>
              </div>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
      <small class="text-muted">
        Showing <?= $pagination['from'] ?>–<?= $pagination['to'] ?> of <?= $pagination['total_records'] ?>
      </small>
      <nav><ul class="pagination pagination-sm mb-0">
        <?php for ($pg = 1; $pg <= $pagination['total_pages']; $pg++): ?>
        <li class="page-item <?= $pg === $pagination['current_page'] ? 'active' : '' ?>">
          <a class="page-link" href="?page=<?= $pg ?>&<?= http_build_query(array_filter($filters)) ?>"><?= $pg ?></a>
        </li>
        <?php endfor; ?>
      </ul></nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php $view->section('scripts'); ?>
<script>
(function(){
  'use strict';
  var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  function post(url, cb) {
    var fd = new FormData(); fd.append('csrf_token', csrf);
    fetch(url, {method:'POST',credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd})
      .then(function(r){return r.json();})
      .then(function(resp){ if(resp.success){cb(resp);}else{alert(resp.message||'Error');} })
      .catch(function(){alert('Network error');});
  }

  // Toggle status
  document.querySelectorAll('.btn-toggle-status').forEach(function(btn){
    btn.addEventListener('click', function(){
      if (!confirm('Toggle farm status?')) return;
      var self = this;
      post(self.dataset.action, function(){ location.reload(); });
    });
  });

  // Delete
  document.querySelectorAll('.btn-delete').forEach(function(btn){
    btn.addEventListener('click', function(){
      if (!confirm('Delete farm "' + this.dataset.name + '"? It can be restored later.')) return;
      var self = this;
      post(self.dataset.action, function(){ location.reload(); });
    });
  });

  // Restore
  document.querySelectorAll('.btn-restore').forEach(function(btn){
    btn.addEventListener('click', function(){
      if (!confirm('Restore this farm?')) return;
      var self = this;
      post(self.dataset.action, function(){ location.reload(); });
    });
  });

  // Search debounce
  var si = document.getElementById('searchInput');
  if (si) { var t; si.addEventListener('input', function(){ clearTimeout(t); t=setTimeout(function(){si.closest('form').submit();},500); }); }

  // Auto-submit selects
  ['typeFilter','statusFilter'].forEach(function(id){
    var el = document.getElementById(id); if(el) el.addEventListener('change',function(){el.closest('form').submit();});
  });
})();
</script>
<?php $view->endSection(); ?>
