<?php
/**
 * Farm Manager — Product Catalog (Read-Only)
 * Route: GET /farm/products
 * NO admin controls, NO prices, NO CRUD.
 *
 * @var array $products
 * @var array $categories
 * @var array $filters
 * @var array $pagination
 */
declare(strict_types=1);

$products   ??= [];
$categories ??= [];
$filters    ??= [];
$pagination ??= [];

$statusBadge = [
    'active'        => ['bg-success',          'Active'],
    'inactive'      => ['bg-secondary',         'Inactive'],
    'discontinued'  => ['bg-danger',            'Discontinued'],
    'out_of_stock'  => ['bg-warning text-dark', 'Out of Stock'],
    // uppercase aliases for legacy data
    'ACTIVE'        => ['bg-success',          'Active'],
    'INACTIVE'      => ['bg-secondary',         'Inactive'],
    'DISCONTINUED'  => ['bg-danger',            'Discontinued'],
    'OUT_OF_STOCK'  => ['bg-warning text-dark', 'Out of Stock'],
    'ARCHIVED'      => ['bg-dark',              'Archived'],
];
?>
<!-- Page Header -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-box-seam-fill me-2 text-primary"></i>Product Catalog
    </h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('farm/dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item active">Products</li>
      </ol>
    </nav>
  </div>
</div>

<!-- Filter / Search -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2 px-3">
    <form method="GET" action="" class="row g-2 align-items-end">
      <div class="col-md-4">
        <input type="text" name="search" class="form-control form-control-sm"
               placeholder="Search name, code, barcode…"
               value="<?= e($filters['search'] ?? '') ?>">
      </div>
      <div class="col-md-3">
        <select name="category_id" class="form-select form-select-sm">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id'] ?>"
            <?= (int)($filters['categoryId'] ?? 0) === (int)$cat['id'] ? 'selected' : '' ?>>
            <?= e($cat['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="status" class="form-select form-select-sm">
          <option value="">All Statuses</option>
          <?php foreach (array_keys($statusBadge) as $s): ?>
          <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>>
            <?= ucfirst(strtolower(str_replace('_', ' ', $s))) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 d-flex gap-1">
        <button type="submit" class="btn btn-primary btn-sm px-3">
          <i class="bi bi-search me-1"></i>Search
        </button>
        <a href="<?= url('farm/products') ?>" class="btn btn-outline-secondary btn-sm px-3">
          <i class="bi bi-x me-1"></i>Clear
        </a>
      </div>
    </form>
  </div>
</div>

<!-- Result count -->
<div class="mb-2">
  <span class="text-muted small">
    Showing <?= count($products) ?> of <?= number_format($pagination['total'] ?? 0) ?> products
  </span>
</div>

<!-- Product Grid / Table -->
<?php if (empty($products)): ?>
<div class="card border-0 shadow-sm">
  <div class="card-body text-center py-5 text-muted">
    <i class="bi bi-box-seam d-block fs-2 mb-2 opacity-25"></i>
    <p class="mb-0">No products found matching your search.</p>
  </div>
</div>
<?php else: ?>
<div class="row g-3 mb-3">
  <?php foreach ($products as $p):
    [$badgeCls, $badgeLbl] = $statusBadge[$p['product_status'] ?? ''] ?? ['bg-secondary','Unknown'];
  ?>
  <div class="col-sm-6 col-lg-4 col-xl-3">
    <div class="card border-0 shadow-sm h-100">
      <!-- Product image -->
      <?php if (!empty($p['primary_image'])): ?>
      <img src="<?= url(e($p['primary_image'])) ?>"
           class="card-img-top object-fit-cover" style="height:140px;"
           alt="<?= e($p['product_name']) ?>">
      <?php else: ?>
      <div class="bg-light d-flex align-items-center justify-content-center" style="height:140px;">
        <i class="bi bi-image text-muted" style="font-size:2rem;opacity:.3;"></i>
      </div>
      <?php endif; ?>

      <div class="card-body py-2 px-3">
        <div class="d-flex justify-content-between align-items-start mb-1">
          <h6 class="fw-semibold mb-0 small lh-sm"><?= e($p['product_name']) ?></h6>
          <span class="badge <?= $badgeCls ?> ms-1 flex-shrink-0" style="font-size:.6rem;"><?= $badgeLbl ?></span>
        </div>
        <div class="text-muted" style="font-size:.72rem;">
          Code: <strong><?= e($p['product_code'] ?? '—') ?></strong>
        </div>
        <div class="text-muted" style="font-size:.72rem;">
          Category: <?= e($p['category_name'] ?? '—') ?>
        </div>
        <div class="text-muted" style="font-size:.72rem;">
          Unit: <?= e($p['unit_name'] ?? '—') ?>
          <?php if (!empty($p['unit_abbr'])): ?>
          (<?= e($p['unit_abbr']) ?>)
          <?php endif; ?>
        </div>
        <?php if (!empty($p['description'])): ?>
        <p class="text-muted mt-1 mb-0" style="font-size:.7rem;line-height:1.3;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">
          <?= e($p['description']) ?>
        </p>
        <?php endif; ?>
      </div>
      <div class="card-footer bg-transparent border-top pt-2 pb-2 px-3">
        <a href="<?= url('farm/products/' . $p['id']) ?>"
           class="btn btn-outline-primary btn-sm w-100 py-1">
          <i class="bi bi-eye me-1"></i>View Details
        </a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if (($pagination['last_page'] ?? 1) > 1): ?>
<div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
  <small class="text-muted">
    Page <?= $pagination['current_page'] ?> of <?= $pagination['last_page'] ?>
    &nbsp;&bull;&nbsp; <?= number_format($pagination['total']) ?> products total
  </small>
  <div class="d-flex gap-1 flex-wrap">
    <?php
    $cur  = $pagination['current_page'];
    $last = $pagination['last_page'];
    $qBase = array_filter(['search' => $filters['search'] ?? '', 'category_id' => $filters['categoryId'] ?? 0, 'status' => $filters['status'] ?? '']);
    $pages = array_unique(array_filter([1, $cur-1, $cur, $cur+1, $last], fn($p) => $p >= 1 && $p <= $last));
    sort($pages);
    $prev = null;
    foreach ($pages as $pg):
      if ($prev !== null && $pg - $prev > 1): ?>
        <span class="btn btn-sm btn-outline-secondary disabled px-2">…</span>
      <?php endif; ?>
      <a href="?<?= http_build_query(array_merge($qBase, ['page' => $pg])) ?>"
         class="btn btn-sm <?= $pg === $cur ? 'btn-primary' : 'btn-outline-secondary' ?>">
        <?= $pg ?>
      </a>
    <?php $prev = $pg; endforeach; ?>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Read-only notice -->
<div class="mt-3 text-muted small d-flex align-items-center gap-1">
  <i class="bi bi-lock-fill text-secondary opacity-50"></i>
  Product catalog is read-only for Farm Manager.
</div>
