<?php
declare(strict_types=1);
$pageTitle = 'Shop Management';
$canManage = in_array(\AAM\Core\SessionManager::userRole() ?? '', [ROLE_ADMIN], true);
$shops     ??= [];
$shopTypes ??= [];
$stats     ??= [];
$filters   ??= [];
$pagination ??= ['from'=>0,'to'=>0,'total_records'=>0,'total_pages'=>0,'current_page'=>1];
?>
<?php $view->section('styles'); ?>
<link rel="stylesheet" href="<?= $view->asset('css/organization.css') ?>">
<?php $view->endSection(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-shop-window me-2" style="color:#fd7e14;"></i>Shop Management</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('admin/organization') ?>">Organization</a></li>
        <li class="breadcrumb-item active">Shops</li>
      </ol>
    </nav>
  </div>
  <?php if ($canManage): ?>
  <a href="<?= url('admin/organization/shops/create') ?>" class="btn btn-warning btn-sm text-dark">
    <i class="bi bi-plus-lg me-1"></i>Add Shop
  </a>
  <?php endif; ?>
</div>

<!-- Stats -->
<div class="row g-2 mb-3">
  <?php foreach ([
    ['total',   'Total',    'bi-shop',              'warning'],
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
  <form method="GET" action="<?= url('admin/organization/shops') ?>" class="row g-2 align-items-end" id="filterForm">
    <div class="col-md-4">
      <div class="input-group input-group-sm">
        <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
        <input type="search" class="form-control" name="search" id="searchInput"
               value="<?= e($filters['search'] ?? '') ?>" placeholder="Search name or location…">
      </div>
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
      <a href="<?= url('admin/organization/shops') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($shops)): ?>
    <div class="text-center py-5">
      <i class="bi bi-shop display-4 text-muted d-block mb-2"></i>
      <h6 class="text-muted">No shops found</h6>
      <?php if ($canManage): ?>
      <a href="<?= url('admin/organization/shops/create') ?>" class="btn btn-warning btn-sm mt-2 text-dark">
        <i class="bi bi-plus-lg me-1"></i>Add First Shop
      </a>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Shop</th>
            <th>Type</th>
            <th>Location</th>
            <th>Manager / Seller</th>
            <th class="text-center">Status</th>
            <th>Created</th>
            <?php if ($canManage): ?><th class="pe-3 text-end">Actions</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($shops as $shop): ?>
          <?php
            $shopId   = (int)($shop['shop_id'] ?? $shop['id']);
            $shopName = $shop['shop_name'] ?? $shop['name'] ?? '—';
            $shopCode = $shop['shop_code'] ?? 'SHP' . str_pad((string)$shopId, 3, '0', STR_PAD_LEFT);
            $shopType = $shop['shop_type'] ?? 'RETAIL';
            $isActive = (bool)($shop['is_active'] ?? 1);
          ?>
          <tr>
            <td class="ps-3">
              <div class="d-flex align-items-center gap-2">
                <?php if (!empty($shop['shop_image'])): ?>
                <img src="<?= e(upload_url('shops/' . $shop['shop_image'])) ?>"
                     alt="<?= e($shopName) ?>"
                     style="width:36px;height:36px;object-fit:cover;border-radius:6px;flex-shrink:0;"
                     onerror="this.style.display='none'">
                <?php else: ?>
                <div style="width:36px;height:36px;border-radius:6px;background:#f3f3f3;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                  <i class="bi bi-shop" style="color:#ccc;font-size:16px;"></i>
                </div>
                <?php endif; ?>
                <div>
                  <div class="fw-semibold">
                    <a href="<?= url('admin/organization/shops/' . $shopId) ?>" class="text-decoration-none text-dark">
                      <?= e($shopName) ?>
                    </a>
                  </div>
                  <small class="text-muted font-monospace"><?= e($shopCode) ?></small>
                </div>
              </div>
            </td>
            <td>
              <span class="type-badge type-retail"><?= e($shopTypes[$shopType] ?? $shopType) ?></span>
            </td>
            <td class="small text-muted">
              <?php $loc = $shop['location'] ?? ''; ?>
              <?= $loc ? e($loc) : '—' ?>
            </td>
            <td class="small">
              <?= !empty($shop['manager_name']) ? e($shop['manager_name']) : '<span class="text-muted fst-italic">Unassigned</span>' ?>
            </td>
            <td class="text-center">
              <?php if ($canManage): ?>
              <button class="btn btn-sm border-0 bg-transparent p-0 btn-toggle-status"
                      data-id="<?= $shopId ?>"
                      data-action="<?= url('admin/organization/shops/' . $shopId . '/toggle') ?>"
                      data-current="<?= (int)$isActive ?>">
                <span class="badge <?= $isActive ? 'bg-success' : 'bg-secondary' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span>
              </button>
              <?php else: ?>
              <span class="badge <?= $isActive ? 'bg-success' : 'bg-secondary' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span>
              <?php endif; ?>
            </td>
            <td class="small text-muted"><?= e(date('d/m/Y', strtotime($shop['created_at'] ?? 'now'))) ?></td>
            <?php if ($canManage): ?>
            <td class="pe-3 text-end">
              <div class="d-flex justify-content-end gap-1">
                <a href="<?= url('admin/organization/shops/' . $shopId . '/edit') ?>"
                   class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil-fill"></i></a>
                <?php if ($isActive): ?>
                <button class="btn btn-sm btn-outline-danger btn-delete"
                        data-id="<?= $shopId ?>" data-name="<?= e($shopName) ?>"
                        data-action="<?= url('admin/organization/shops/' . $shopId . '/delete') ?>"
                        title="Delete"><i class="bi bi-trash3-fill"></i></button>
                <?php else: ?>
                <button class="btn btn-sm btn-outline-success btn-restore"
                        data-id="<?= $shopId ?>"
                        data-action="<?= url('admin/organization/shops/' . $shopId . '/restore') ?>"
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
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
      <small class="text-muted">Showing <?= $pagination['from'] ?>–<?= $pagination['to'] ?> of <?= $pagination['total_records'] ?></small>
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
      .then(function(r){ if(r.success){cb(r);}else{alert(r.message||'Error');} })
      .catch(function(){alert('Network error');});
  }
  document.querySelectorAll('.btn-toggle-status').forEach(function(b){
    b.addEventListener('click',function(){if(!confirm('Toggle status?'))return;post(this.dataset.action,function(){location.reload();});});
  });
  document.querySelectorAll('.btn-delete').forEach(function(b){
    b.addEventListener('click',function(){if(!confirm('Delete "'+this.dataset.name+'"?'))return;post(this.dataset.action,function(){location.reload();});});
  });
  document.querySelectorAll('.btn-restore').forEach(function(b){
    b.addEventListener('click',function(){if(!confirm('Restore?'))return;post(this.dataset.action,function(){location.reload();});});
  });
  var si=document.getElementById('searchInput');
  if(si){var t;si.addEventListener('input',function(){clearTimeout(t);t=setTimeout(function(){si.closest('form').submit();},500);});}
  var sf=document.getElementById('statusFilter');
  if(sf) sf.addEventListener('change',function(){sf.closest('form').submit();});
})();
</script>
<?php $view->endSection(); ?>
