<?php
declare(strict_types=1);
$seedStock      ??= [];
$seedProducts   ??= [];
$seedWarehouses ??= [];
$filters        ??= [];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-boxes me-2 text-primary"></i>Seed Stock</h4>
    <p class="text-muted small mb-0">Current seed inventory across all enabled warehouses</p>
  </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body p-3">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-3">
        <select name="warehouse_id" class="form-select form-select-sm">
          <option value="">All Warehouses</option>
          <?php foreach ($seedWarehouses as $wh): ?>
          <option value="<?= $wh['id'] ?>" <?= ($filters['warehouse_id'] ?? '') == $wh['id'] ? 'selected' : '' ?>><?= e($wh['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <select name="product_id" class="form-select form-select-sm">
          <option value="">All Seed Products</option>
          <?php foreach ($seedProducts as $sp): ?>
          <option value="<?= $sp['id'] ?>" <?= ($filters['product_id'] ?? '') == $sp['id'] ? 'selected' : '' ?>><?= e($sp['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1"><button type="submit" class="btn btn-sm btn-primary w-100">Filter</button></div>
      <div class="col-md-1"><a href="<?= url('gm/seed-distribution/stock') ?>" class="btn btn-sm btn-outline-secondary w-100">Clear</a></div>
    </form>
  </div>
</div>

<?php if (empty($seedStock)): ?>
<div class="alert alert-info">
  <i class="bi bi-info-circle me-2"></i>No seed stock found. Ensure seed products are marked as seed and available in warehouse inventory.
</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Warehouse</th><th>Product</th><th>Batch</th><th class="text-end">Total</th>
            <th class="text-end">Available</th><th class="text-end">Reserved</th><th>Unit</th><th>Last Movement</th><th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($seedStock as $row):
            $avail   = (float)$row['available_quantity'];
            $color   = $avail > 0 ? 'success' : 'danger';
            $label   = $avail > 0 ? 'In Stock' : 'Out of Stock';
          ?>
          <tr>
            <td>
              <div class="fw-semibold small"><?= e($row['warehouse_name']) ?></div>
              <div class="text-muted" style="font-size:.7rem;"><?= e($row['warehouse_code'] ?? '') ?></div>
            </td>
            <td class="fw-semibold"><?= e($row['product_name']) ?></td>
            <td class="small text-muted font-monospace"><?= e($row['batch_number'] ?? '—') ?></td>
            <td class="text-end"><?= number_format((float)$row['current_quantity'], 2) ?></td>
            <td class="text-end text-<?= $color ?> fw-bold"><?= number_format($avail, 2) ?></td>
            <td class="text-end text-muted"><?= number_format((float)$row['reserved_quantity'], 2) ?></td>
            <td class="small"><?= e($row['unit_abbr'] ?? '—') ?></td>
            <td class="small text-muted"><?= $row['last_movement_at'] ? e(date('d/m/Y', strtotime($row['last_movement_at']))) : '—' ?></td>
            <td><span class="badge bg-<?= $color ?>-subtle text-<?= $color ?> border border-<?= $color ?>-subtle"><?= $label ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="p-3 text-muted small border-top">
      <?= count($seedStock) ?> seed stock record(s) across <?= count($seedWarehouses) ?> seed-enabled warehouse(s)
    </div>
  </div>
</div>
<?php endif; ?>
