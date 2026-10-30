<?php
/**
 * Inventory — Stock Movements — Phase 11
 * @var array  $movements    @var array  $pagination   @var array  $filters
 * @var array  $products     @var string $locationType @var int    $locationId
 * @var string $locationName @var string $userRole
 */
declare(strict_types=1);
$movements  ??= [];
$pagination ??= [];
$filters    ??= [];
$products   ??= [];
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold">
    <i class="bi bi-clock-history me-2 text-primary"></i>
    <?= e($locationName) ?> — Stock Movements
  </h5>
  <a href="<?= url('inventory') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-speedometer2 me-1"></i>Overview
  </a>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <input type="hidden" name="lt"  value="<?= e($locationType) ?>">
      <input type="hidden" name="lid" value="<?= (int)$locationId ?>">
      <div class="col-12 col-md-3">
        <select name="product_id" class="form-select form-select-sm">
          <option value="">All Products</option>
          <?php foreach ($products as $p): ?>
          <option value="<?= $p['id'] ?>" <?= ($filters['product_id']??'')==$p['id']?'selected':'' ?>>
            <?= e($p['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <select name="movement_type" class="form-select form-select-sm">
          <option value="">All Types</option>
          <?php foreach (['receiving','transfer_out','transfer_in','adjustment','discrepancy','return','sale','count','damage','expiry'] as $t): ?>
          <option value="<?= $t ?>" <?= ($filters['movement_type']??'')===$t?'selected':'' ?>>
            <?= \AAM\Warehouse\Models\StockMovementModel::typeLabel($t) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <input type="date" name="date_from" class="form-control form-control-sm"
               value="<?= e($filters['date_from']??'') ?>" placeholder="From">
      </div>
      <div class="col-6 col-md-2">
        <input type="date" name="date_to" class="form-control form-control-sm"
               value="<?= e($filters['date_to']??'') ?>" placeholder="To">
      </div>
      <div class="col-auto">
        <button class="btn btn-primary btn-sm">Filter</button>
        <a href="<?= url('inventory/movements?lt='.e($locationType).'&lid='.(int)$locationId) ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($movements)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-clock-history display-6 d-block mb-2 opacity-25"></i>
        <small>No movement records found.</small>
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Date / Time</th>
            <th>Type</th>
            <th>Product</th>
            <th>Batch</th>
            <th>Before</th>
            <th>In</th>
            <th>Out</th>
            <th>After</th>
            <th>Reference</th>
            <th class="pe-3">User</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($movements as $m):
            $badge = \AAM\Warehouse\Models\StockMovementModel::typeBadge($m['movement_type']);
            $label = \AAM\Warehouse\Models\StockMovementModel::typeLabel($m['movement_type']);
          ?>
          <tr>
            <td class="ps-3 small text-muted"><?= date('d/m/Y H:i', strtotime($m['moved_at'])) ?></td>
            <td><span class="badge bg-<?= $badge ?>"><?= $label ?></span></td>
            <td class="small">
              <div class="fw-semibold"><?= e($m['product_name'] ?? '—') ?></div>
              <?php if (!empty($m['product_sku'])): ?><div class="text-muted" style="font-size:.68rem;"><?= e($m['product_sku']) ?></div><?php endif; ?>
            </td>
            <td class="small text-muted"><?= e($m['batch_number'] ?? '—') ?></td>
            <td class="small"><?= number_format((float)$m['quantity_before'], 3) ?></td>
            <td class="small text-success fw-semibold"><?= (float)$m['quantity_in'] > 0 ? '+'.number_format((float)$m['quantity_in'],3) : '—' ?></td>
            <td class="small text-danger fw-semibold"><?= (float)$m['quantity_out'] > 0 ? '-'.number_format((float)$m['quantity_out'],3) : '—' ?></td>
            <td class="small fw-bold"><?= number_format((float)$m['quantity_after'], 3) ?></td>
            <td class="small">
              <?php if ($m['reference_number']): ?>
              <span class="text-muted"><?= e($m['reference_number']) ?></span>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td class="pe-3 small text-muted"><?= e($m['performed_by_name'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (($pagination['totalPages']??1) > 1): ?>
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
      <small class="text-muted">
        Showing <?= $pagination['from'] ?>–<?= $pagination['to'] ?> of <?= number_format($pagination['total']) ?>
      </small>
      <nav><ul class="pagination pagination-sm mb-0">
        <?php for ($p=1; $p<=$pagination['totalPages']; $p++): ?>
        <li class="page-item <?= $p==($pagination['page']??1)?'active':'' ?>">
          <a class="page-link" href="?page=<?= $p ?>&lt=<?= e($locationType) ?>&lid=<?= $locationId ?>&<?= http_build_query(array_filter($filters)) ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>
      </ul></nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
