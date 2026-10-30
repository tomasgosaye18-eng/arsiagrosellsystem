<?php
/**
 * Warehouse Inventory — Phase 10
 * @var array $inventory @var array $pagination @var array $filters
 * @var array $categories @var array $warehouse @var int $warehouseId
 */
declare(strict_types=1);
$inventory   ??= [];
$pagination  ??= [];
$filters     ??= [];
$categories  ??= [];
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold">
    <i class="bi bi-boxes me-2 text-primary"></i>
    <?= e($warehouse['name'] ?? 'Warehouse') ?> &mdash; Inventory
  </h5>
  <a href="<?= url('warehouse') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-speedometer2 me-1"></i>Overview
  </a>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" action="<?= url('warehouse/inventory') ?>" class="row g-2 align-items-end">
      <input type="hidden" name="wid" value="<?= $warehouseId ?>">
      <div class="col-12 col-md-4">
        <input type="search" name="search" class="form-control form-control-sm"
               placeholder="Search product, SKU, batch…"
               value="<?= e($filters['search'] ?? '') ?>">
      </div>
      <div class="col-6 col-md-2">
        <select name="category_id" class="form-select form-select-sm">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id'] ?>" <?= ($filters['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
            <?= e($cat['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <select name="status" class="form-select form-select-sm">
          <option value="">All Statuses</option>
          <option value="in_stock"     <?= ($filters['status'] ?? '') === 'in_stock'      ? 'selected' : '' ?>>In Stock</option>
          <option value="low_stock"    <?= ($filters['status'] ?? '') === 'low_stock'     ? 'selected' : '' ?>>Low Stock</option>
          <option value="out_of_stock" <?= ($filters['status'] ?? '') === 'out_of_stock'  ? 'selected' : '' ?>>Out of Stock</option>
          <option value="expiring_soon"<?= ($filters['status'] ?? '') === 'expiring_soon' ? 'selected' : '' ?>>Expiring Soon</option>
          <option value="expired"      <?= ($filters['status'] ?? '') === 'expired'       ? 'selected' : '' ?>>Expired</option>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <input type="text" name="batch" class="form-control form-control-sm"
               placeholder="Batch number"
               value="<?= e($filters['batch'] ?? '') ?>">
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="<?= url('warehouse/inventory') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($inventory)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-boxes display-6 d-block mb-2 opacity-25"></i>
        <small>No inventory records found.</small>
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
            <th>Batch</th>
            <th>Expiry</th>
            <th class="pe-3">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($inventory as $row):
            [$statusLabel, $statusColor] = \AAM\Warehouse\Models\WarehouseInventoryModel::stockStatusLabel(
                (float)$row['available_quantity'],
                (float)($row['min_stock_level'] ?? 0),
                $row['expiry_date'] ?? null
            );
            $unit = e($row['unit_abbr'] ?? '');
          ?>
          <tr>
            <td class="ps-3">
              <div class="fw-semibold small"><?= e($row['product_name']) ?></div>
              <?php if ($row['product_sku']): ?>
              <div class="text-muted" style="font-size:.68rem;"><?= e($row['product_sku']) ?></div>
              <?php endif; ?>
            </td>
            <td class="small text-muted"><?= e($row['category_name'] ?? '—') ?></td>
            <td class="small fw-bold text-success"><?= number_format((float)$row['available_quantity'], 1) ?> <?= $unit ?></td>
            <td class="small text-warning"><?= number_format((float)$row['reserved_quantity'], 1) ?> <?= $unit ?></td>
            <td class="small"><?= number_format((float)$row['current_quantity'], 1) ?> <?= $unit ?></td>
            <td class="small text-muted"><?= (float)$row['min_stock_level'] > 0 ? number_format((float)$row['min_stock_level'], 1) . ' ' . $unit : '—' ?></td>
            <td class="small"><?= e($row['batch_number'] ?? '—') ?></td>
            <td class="small <?= ($row['expiry_date'] && strtotime($row['expiry_date']) < time()) ? 'text-danger fw-bold' : '' ?>">
              <?= $row['expiry_date'] ? date('d/m/Y', strtotime($row['expiry_date'])) : '—' ?>
            </td>
            <td class="pe-3">
              <span class="badge bg-<?= $statusColor ?>-subtle text-<?= $statusColor ?> small">
                <?= $statusLabel ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <!-- Pagination -->
    <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
      <small class="text-muted">
        Showing <?= $pagination['from'] ?>–<?= $pagination['to'] ?> of <?= number_format($pagination['total']) ?> records
      </small>
      <nav aria-label="Inventory pagination">
        <ul class="pagination pagination-sm mb-0">
          <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
          <li class="page-item <?= $p === ($pagination['page'] ?? 1) ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $p ?>&<?= http_build_query(array_filter($filters)) ?>">
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
