<?php
/**
 * Shop Inventory — Phase 11
 * @var array  $shop        @var int    $shopId      @var array  $inventory
 * @var array  $pagination  @var array  $filters     @var array  $categories
 * @var string $userRole
 */
declare(strict_types=1);
$inventory  ??= [];
$pagination ??= [];
$filters    ??= [];
$categories ??= [];
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold">
    <i class="bi bi-boxes me-2 text-primary"></i>
    <?= e($shop['name'] ?? 'Shop') ?> &mdash; Inventory
  </h5>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (!in_array($userRole ?? '', [ROLE_SALESPERSON], true)): ?>
    <a href="<?= url('inventory/adjustments/create?lt=shop&lid=' . (int)$shopId) ?>"
       class="btn btn-warning btn-sm">
      <i class="bi bi-sliders me-1"></i>Adjust Stock
    </a>
    <?php endif; ?>
    <a href="<?= url('shops/' . (int)$shopId) ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-shop me-1"></i>Shop Overview
    </a>
    <?php if (!in_array($userRole ?? '', [ROLE_SALESPERSON], true)): ?>
    <a href="<?= url('shops') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>All Shops
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-12 col-md-4">
        <input type="search" name="search" class="form-control form-control-sm"
               placeholder="Product name, SKU, batch…"
               value="<?= e($filters['search'] ?? '') ?>">
      </div>
      <div class="col-6 col-md-2">
        <select name="category_id" class="form-select form-select-sm">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id'] ?>"
                  <?= ($filters['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
            <?= e($cat['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <select name="status" class="form-select form-select-sm">
          <option value="">All Statuses</option>
          <option value="in_stock"    <?= ($filters['status']??'')==='in_stock'    ?'selected':'' ?>>In Stock</option>
          <option value="low_stock"   <?= ($filters['status']??'')==='low_stock'   ?'selected':'' ?>>Low Stock</option>
          <option value="out_of_stock"<?= ($filters['status']??'')==='out_of_stock'?'selected':'' ?>>Out of Stock</option>
        </select>
      </div>
      <div class="col-auto">
        <button class="btn btn-primary btn-sm">Filter</button>
        <a href="<?= url('shops/' . (int)$shopId . '/inventory') ?>"
           class="btn btn-outline-secondary btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Inventory Table -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($inventory)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-boxes display-6 d-block mb-2 opacity-25"></i>
        <small>No inventory records found for this shop.</small>
        <div class="mt-3">
          <a href="<?= url('transfers/create') ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-arrow-right-circle me-1"></i>Create Transfer to This Shop
          </a>
        </div>
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Product</th>
            <th>Category</th>
            <th>Available</th>
            <th>Reserved</th>
            <th>Total</th>
            <th>Min Level</th>
            <th>Expiry / Last Movement</th>
            <th class="pe-3">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($inventory as $row):
            $available = (float)$row['available_quantity'];
            $minLevel  = (float)($row['min_stock_level'] ?? 0);
            $unit      = e($row['unit_abbr'] ?? '');

            // Status logic
            if ($row['expiry_date'] && strtotime($row['expiry_date']) < time()) {
                $stLabel = 'EXPIRED';       $stColor = 'danger';
            } elseif ($row['expiry_date'] && strtotime($row['expiry_date']) <= strtotime('+7 days')) {
                $stLabel = 'EXPIRING SOON'; $stColor = 'warning';
            } elseif ($available <= 0) {
                $stLabel = 'OUT OF STOCK';  $stColor = 'danger';
            } elseif ($minLevel > 0 && $available <= $minLevel) {
                $stLabel = 'LOW STOCK';     $stColor = 'warning';
            } else {
                $stLabel = 'IN STOCK';      $stColor = 'success';
            }
          ?>
          <tr>
            <td class="ps-3">
              <div class="fw-semibold small"><?= e($row['product_name']) ?></div>
              <?php if ($row['product_sku']): ?>
              <div class="text-muted" style="font-size:.68rem;"><?= e($row['product_sku']) ?></div>
              <?php endif; ?>
              <?php if (!empty($row['batch_number'])): ?>
              <div class="text-secondary" style="font-size:.68rem;">
                <i class="bi bi-tag me-1"></i><?= e($row['batch_number']) ?>
              </div>
              <?php endif; ?>
            </td>
            <td class="small text-muted"><?= e($row['category_name'] ?? '—') ?></td>
            <td class="small fw-bold text-success">
              <?= number_format($available, 1) ?> <?= $unit ?>
            </td>
            <td class="small text-warning">
              <?= number_format((float)$row['reserved_quantity'], 1) ?> <?= $unit ?>
            </td>
            <td class="small">
              <?= number_format((float)$row['current_quantity'], 1) ?> <?= $unit ?>
            </td>
            <td class="small text-muted">
              <?= $minLevel > 0 ? number_format($minLevel, 1) . ' ' . $unit : '—' ?>
            </td>
            <td class="small">
              <?php if (!empty($row['expiry_date'])): ?>
              <div class="<?= $stColor === 'danger' || $stColor === 'warning' ? 'text-' . $stColor . ' fw-semibold' : 'text-muted' ?>">
                <?= date('d/m/Y', strtotime($row['expiry_date'])) ?>
              </div>
              <?php else: ?>
              <span class="text-muted">
                <?= $row['last_movement_at'] ? date('d/m/Y', strtotime($row['last_movement_at'])) : '—' ?>
              </span>
              <?php endif; ?>
            </td>
            <td class="pe-3">
              <span class="badge bg-<?= $stColor ?>-subtle text-<?= $stColor ?> small">
                <?= $stLabel ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
      <small class="text-muted">
        Showing <?= $pagination['from'] ?>–<?= $pagination['to'] ?>
        of <?= number_format($pagination['total']) ?> records
      </small>
      <nav>
        <ul class="pagination pagination-sm mb-0">
          <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
          <li class="page-item <?= $p === ($pagination['page'] ?? 1) ? 'active' : '' ?>">
            <a class="page-link"
               href="?page=<?= $p ?>&<?= http_build_query(array_filter($filters)) ?>">
              <?= $p ?>
            </a>
          </li>
          <?php endfor; ?>
        </ul>
      </nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
