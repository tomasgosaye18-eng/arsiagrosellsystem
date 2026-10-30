<?php
declare(strict_types=1);
$warehouse ??= [];
$seedStock ??= [];
$gradesMap ??= [];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-flower1 me-2 text-success"></i>Available Seeds</h4>
    <p class="text-muted small mb-0">
      <i class="bi bi-building me-1"></i><?= e($warehouse['name'] ?? '') ?>
      — Seed stock from warehouse inventory
    </p>
  </div>
  <a href="<?= url('seed-distribution/new') ?>" class="btn btn-success">
    <i class="bi bi-plus-lg me-1"></i>New Distribution
  </a>
</div>

<?php if (empty($seedStock)): ?>
<div class="alert alert-info">
  <i class="bi bi-info-circle me-2"></i>
  No seed products found in inventory for this warehouse.
  Ensure seed products are stocked and marked as seed in the product catalog.
</div>
<?php else: ?>
<?php
$grouped = [];
foreach ($seedStock as $row) {
    $grouped[$row['product_name']][] = $row;
}
?>
<?php foreach ($grouped as $productName => $rows): ?>
<?php $first = $rows[0]; ?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-success bg-opacity-10 border-0 py-2 px-3">
    <div class="d-flex align-items-center gap-2">
      <i class="bi bi-flower1 text-success"></i>
      <span class="fw-bold"><?= e($productName) ?></span>
      <span class="badge bg-success text-white ms-auto"><?= e($first['unit_abbr'] ?? 'unit') ?></span>
    </div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Grade / Class</th>
            <th class="text-end">Total Qty</th>
            <th class="text-end">Available</th>
            <th class="text-end">Reserved</th>
            <th>Batch</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
<?php foreach ($rows as $row): ?>
<?php
    $pid     = (int)$row['product_id'];
    $grades  = $gradesMap[$pid] ?? [];
    $avail   = (float)$row['available_quantity'];
    $current = (float)$row['current_quantity'];
    $statColor = $avail > 0 ? 'success' : 'danger';
    $statLabel = $avail > 0 ? 'In Stock' : 'Out of Stock';
?>
<?php if (!empty($grades)): ?>
<?php foreach ($grades as $grade): ?>
<tr>
  <td><span class="badge bg-secondary-subtle text-dark border"><?= e($grade['grade_name']) ?></span></td>
  <td class="text-end fw-semibold"><?= number_format($current, 2) ?></td>
  <td class="text-end text-<?= $statColor ?> fw-bold"><?= number_format($avail, 2) ?></td>
  <td class="text-end text-muted"><?= number_format((float)$row['reserved_quantity'], 2) ?></td>
  <td class="text-muted small"><?= e($row['batch_number'] ?? '—') ?></td>
  <td><span class="badge bg-<?= $statColor ?>-subtle text-<?= $statColor ?> border border-<?= $statColor ?>-subtle"><?= $statLabel ?></span></td>
  <td>
    <?php if ($avail > 0): ?>
    <a href="<?= url('seed-distribution/new?product_id=' . $pid . '&seed_grade_id=' . $grade['id']) ?>"
       class="btn btn-sm btn-outline-success">
      <i class="bi bi-box-arrow-right me-1"></i>Distribute
    </a>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr>
  <td><span class="text-muted fst-italic">No grades configured</span></td>
  <td class="text-end fw-semibold"><?= number_format($current, 2) ?></td>
  <td class="text-end text-<?= $statColor ?> fw-bold"><?= number_format($avail, 2) ?></td>
  <td class="text-end text-muted"><?= number_format((float)$row['reserved_quantity'], 2) ?></td>
  <td class="text-muted small"><?= e($row['batch_number'] ?? '—') ?></td>
  <td><span class="badge bg-<?= $statColor ?>-subtle text-<?= $statColor ?> border border-<?= $statColor ?>-subtle"><?= $statLabel ?></span></td>
  <td>
    <?php if ($avail > 0): ?>
    <a href="<?= url('seed-distribution/new?product_id=' . $pid) ?>" class="btn btn-sm btn-outline-success">
      <i class="bi bi-box-arrow-right me-1"></i>Distribute
    </a>
    <?php endif; ?>
  </td>
</tr>
<?php endif; ?>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
