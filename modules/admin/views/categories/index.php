<?php
/**
 * =========================================================
 * PRODUCT CATEGORIES – LIST VIEW
 * =========================================================
 * File    : modules/admin/views/categories/index.php
 * Layout  : templates/master.php
 * Purpose : List all product categories with search, filter,
 *           pagination, status toggle, soft delete & restore.
 * Deps    : CategoryController, CategoryModel
 * =========================================================
 */
declare(strict_types=1);
?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-tag-fill me-2 text-primary"></i>Product Categories
    </h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= url('admin/products') ?>">Products</a></li>
        <li class="breadcrumb-item active">Categories</li>
      </ol>
    </nav>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('admin/categories/create') ?>" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i>Add Category
    </a>
  </div>
</div>

<!-- ── Stats Strip ──────────────────────────────────────── -->
<div class="row g-2 mb-3">
  <div class="col-sm-3">
    <div class="card border-0 shadow-sm">
      <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
        <i class="bi bi-grid-fill text-primary fs-5"></i>
        <div>
          <div class="fw-bold lh-1"><?= (int)($stats['total_categories'] ?? 0) ?></div>
          <div class="text-muted" style="font-size:.72rem;">Total</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-3">
    <div class="card border-0 shadow-sm">
      <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill text-success fs-5"></i>
        <div>
          <div class="fw-bold lh-1"><?= (int)($stats['active_categories'] ?? 0) ?></div>
          <div class="text-muted" style="font-size:.72rem;">Active</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-3">
    <div class="card border-0 shadow-sm">
      <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
        <i class="bi bi-slash-circle-fill text-secondary fs-5"></i>
        <div>
          <div class="fw-bold lh-1"><?= (int)($stats['inactive_categories'] ?? 0) ?></div>
          <div class="text-muted" style="font-size:.72rem;">Inactive</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-3">
    <div class="card border-0 shadow-sm">
      <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
        <i class="bi bi-box-seam-fill text-warning fs-5"></i>
        <div>
          <div class="fw-bold lh-1"><?= (int)($stats['categories_with_products'] ?? 0) ?></div>
          <div class="text-muted" style="font-size:.72rem;">With Products</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Filters ───────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2 px-3">
    <form method="GET" action="<?= url('admin/categories') ?>" id="filterForm" class="row g-2 align-items-end">
      <div class="col-md-5">
        <label class="form-label small fw-semibold mb-1">Search</label>
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
          <input type="search" class="form-control" name="search"
                 value="<?= e($filters['search']) ?>"
                 placeholder="Code, name, description…" autocomplete="off">
        </div>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-semibold mb-1">Status</label>
        <select class="form-select form-select-sm" name="status" id="statusFilter">
          <option value="">All Status</option>
          <option value="1" <?= $filters['status'] === '1' ? 'selected' : '' ?>>Active</option>
          <option value="0" <?= $filters['status'] === '0' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
          <i class="bi bi-funnel me-1"></i>Filter
        </button>
        <?php if ($filters['search'] || $filters['status'] !== ''): ?>
          <a href="<?= url('admin/categories') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-x-lg"></i>
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- ── Table ─────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($categories)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-tags display-4 d-block mb-3 opacity-25"></i>
        <h6 class="fw-semibold">No categories found</h6>
        <?php if ($filters['search'] || $filters['status'] !== ''): ?>
          <a href="<?= url('admin/categories') ?>" class="btn btn-outline-secondary btn-sm mt-2">Clear Filters</a>
        <?php else: ?>
          <a href="<?= url('admin/categories/create') ?>" class="btn btn-primary btn-sm mt-2">
            <i class="bi bi-plus-lg me-1"></i>Create First Category
          </a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th class="ps-3" style="width:46px;">#</th>
              <th>Category Name</th>
              <th>Description</th>
              <th class="text-center">Products</th>
              <th class="text-center">Order</th>
              <th class="text-center">Status</th>
              <th>Created By</th>
              <th>Created At</th>
              <th class="text-end pe-3">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($categories as $cat): ?>
            <tr data-id="<?= (int)$cat['category_id'] ?>">
              <td class="ps-3 text-muted small"><?= (int)$cat['category_id'] ?></td>
              <td>
                <div class="fw-semibold"><?= e($cat['category_name']) ?></div>
                <?php if ($cat['parent_category_name']): ?>
                  <div class="text-muted" style="font-size:.72rem;">
                    <i class="bi bi-diagram-3 me-1"></i><?= e($cat['parent_category_name']) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td class="text-muted small" style="max-width:200px;">
                <?= e(mb_strimwidth($cat['description'] ?? '', 0, 60, '…')) ?>
              </td>
              <td class="text-center">
                <span class="badge <?= (int)$cat['product_count'] > 0 ? 'bg-primary' : 'bg-light text-muted border' ?>">
                  <?= (int)$cat['product_count'] ?>
                </span>
              </td>
              <td class="text-center text-muted small"><?= (int)$cat['display_order'] ?></td>
              <td class="text-center">
                <button class="btn btn-sm px-2 py-0 btn-toggle-status
                               <?= $cat['is_active'] ? 'btn-outline-success' : 'btn-outline-secondary' ?>"
                        data-id="<?= (int)$cat['category_id'] ?>"
                        data-action="<?= url('admin/categories/' . $cat['category_id'] . '/toggle') ?>"
                        title="<?= $cat['is_active'] ? 'Active – click to deactivate' : 'Inactive – click to activate' ?>">
                  <i class="bi <?= $cat['is_active'] ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-secondary' ?>"></i>
                </button>
              </td>
              <td class="small text-muted"><?= e($cat['creator_full_name'] ?? $cat['creator_name'] ?? '—') ?></td>
              <td class="small text-muted"><?= date_display($cat['created_at']) ?></td>
              <td class="text-end pe-3">
                <div class="d-flex justify-content-end gap-1">
                  <a href="<?= url('admin/categories/' . $cat['category_id']) ?>"
                     class="btn btn-sm btn-outline-info" title="View">
                    <i class="bi bi-eye-fill"></i>
                  </a>
                  <a href="<?= url('admin/categories/' . $cat['category_id'] . '/edit') ?>"
                     class="btn btn-sm btn-outline-primary" title="Edit">
                    <i class="bi bi-pencil-fill"></i>
                  </a>
                  <?php if ((int)$cat['product_count'] === 0): ?>
                  <button class="btn btn-sm btn-outline-danger btn-delete"
                          data-id="<?= (int)$cat['category_id'] ?>"
                          data-name="<?= e($cat['category_name']) ?>"
                          data-action="<?= url('admin/categories/' . $cat['category_id'] . '/delete') ?>"
                          title="Delete">
                    <i class="bi bi-trash3-fill"></i>
                  </button>
                  <?php else: ?>
                  <button class="btn btn-sm btn-outline-danger disabled" disabled title="Has products – cannot delete">
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
          Showing <strong><?= count($categories) ?></strong>
          of <strong><?= $pagination['total_records'] ?></strong> categories
        </div>
        <?php if ($pagination['total_pages'] > 1): ?>
        <nav>
          <ul class="pagination pagination-sm mb-0">
            <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
            <li class="page-item <?= $p === $pagination['current_page'] ? 'active' : '' ?>">
              <a class="page-link" href="<?= url('admin/categories') ?>?page=<?= $p ?>&search=<?= urlencode($filters['search']) ?>&status=<?= urlencode($filters['status']) ?>">
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
    fetch(url, { method: 'POST', credentials: 'same-origin', headers: {'X-Requested-With':'XMLHttpRequest'}, body: fd })
      .then(r => r.json())
      .then(resp => resp.success ? cb(resp) : AAM.toast(resp.message, 'error'))
      .catch(() => AAM.toast('Network error', 'error'));
  }

  // Toggle status
  document.querySelectorAll('.btn-toggle-status').forEach(btn => {
    btn.addEventListener('click', function () {
      post(this.dataset.action, resp => {
        AAM.toast(resp.message, 'success');
        setTimeout(() => location.reload(), 700);
      });
    });
  });

  // Delete
  document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', function () {
      if (!confirm('Delete category "' + this.dataset.name + '"?\nThis cannot be undone.')) return;
      post(this.dataset.action, resp => {
        AAM.toast(resp.message, 'success');
        setTimeout(() => location.reload(), 700);
      });
    });
  });

  // Status filter auto-submit
  document.getElementById('statusFilter')?.addEventListener('change', function () {
    document.getElementById('filterForm').submit();
  });
})();
</script>
<?php $view->endSection(); ?>
