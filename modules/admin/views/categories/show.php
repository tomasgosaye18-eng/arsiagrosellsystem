<?php
/**
 * =========================================================
 * PRODUCT CATEGORIES – DETAIL VIEW
 * =========================================================
 * File    : modules/admin/views/categories/show.php
 * Purpose : View category details and its products
 * =========================================================
 * @var array $category
 */
declare(strict_types=1);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi <?= e($category['icon'] ?? 'bi-tag-fill') ?> me-2 text-primary"></i>
      <?= e($category['category_name']) ?>
    </h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= url('admin/categories') ?>">Categories</a></li>
        <li class="breadcrumb-item active"><?= e($category['category_name']) ?></li>
      </ol>
    </nav>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('admin/categories/' . $category['category_id'] . '/edit') ?>"
       class="btn btn-primary btn-sm">
      <i class="bi bi-pencil-fill me-1"></i>Edit
    </a>
    <a href="<?= url('admin/categories') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-4">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4 text-center">
        <div class="mb-3 d-flex align-items-center justify-content-center mx-auto"
             style="width:80px;height:80px;border-radius:20px;" class="bg-primary-subtle">
          <i class="bi <?= e($category['icon'] ?? 'bi-tag') ?> text-primary"
             style="font-size:2rem;"></i>
        </div>
        <h5 class="fw-bold mb-1"><?= e($category['category_name']) ?></h5>
        <div class="badge bg-light text-dark border font-monospace mb-2">
          <?= e($category['category_code']) ?>
        </div>
        <div class="mb-3">
          <span class="badge <?= $category['is_active'] ? 'bg-success' : 'bg-secondary' ?> rounded-pill">
            <?= $category['is_active'] ? 'Active' : 'Inactive' ?>
          </span>
        </div>
        <?php if ($category['description']): ?>
        <p class="text-muted small"><?= nl2br(e($category['description'])) ?></p>
        <?php endif; ?>
      </div>
      <div class="card-footer bg-light border-top-0 py-2 px-3">
        <div class="row text-center g-0">
          <div class="col-4 border-end">
            <div class="fw-bold"><?= (int)$category['product_count'] ?></div>
            <div class="text-muted" style="font-size:.7rem;">Products</div>
          </div>
          <div class="col-4 border-end">
            <div class="fw-bold"><?= (int)$category['display_order'] ?></div>
            <div class="text-muted" style="font-size:.7rem;">Order</div>
          </div>
          <div class="col-4">
            <div class="fw-bold"><?= $category['is_active'] ? '✓' : '✗' ?></div>
            <div class="text-muted" style="font-size:.7rem;">Active</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Meta -->
    <div class="card border-0 shadow-sm mt-3">
      <div class="card-body p-3 small">
        <div class="mb-1">
          <span class="text-muted">Created by:</span>
          <strong class="ms-1">
            <?= e($category['creator_full_name'] ?? $category['creator_name'] ?? '—') ?>
          </strong>
        </div>
        <div class="mb-1">
          <span class="text-muted">Created at:</span>
          <strong class="ms-1"><?= date_display($category['created_at']) ?></strong>
        </div>
        <div>
          <span class="text-muted">Updated at:</span>
          <strong class="ms-1"><?= date_display($category['updated_at']) ?></strong>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
        <h6 class="fw-semibold mb-0">
          <i class="bi bi-box-seam me-2 text-primary"></i>
          Products in this Category
          <span class="badge bg-primary ms-2"><?= (int)$category['product_count'] ?></span>
        </h6>
        <a href="<?= url('admin/products') ?>?category_id=<?= (int)$category['category_id'] ?>"
           class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:.75rem;">
          View All
        </a>
      </div>
      <div class="card-body p-3">
        <?php if ((int)$category['product_count'] === 0): ?>
        <div class="text-center text-muted py-4">
          <i class="bi bi-box-seam d-block mb-2 fs-4 opacity-25"></i>
          <div class="small">No products in this category yet</div>
          <a href="<?= url('admin/products/create') ?>" class="btn btn-primary btn-sm mt-2">
            <i class="bi bi-plus-lg me-1"></i>Add Product
          </a>
        </div>
        <?php else: ?>
        <a href="<?= url('admin/products') ?>?category_id=<?= (int)$category['category_id'] ?>"
           class="btn btn-outline-primary w-100">
          <i class="bi bi-box-seam me-1"></i>
          Browse <?= (int)$category['product_count'] ?> Products in this Category
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
