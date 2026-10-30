<?php
/**
 * Inventory — Stock Adjustments List — Phase 11
 * @var array  $adjustments  @var array  $pagination   @var array  $filters
 * @var string $locationType @var int    $locationId   @var string $locationName
 */
declare(strict_types=1);
$adjustments ??= [];
$pagination  ??= [];
$filters     ??= [];
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold">
    <i class="bi bi-sliders me-2 text-warning"></i>
    <?= e($locationName) ?> — Stock Adjustments
  </h5>
  <div class="d-flex gap-2">
    <a href="<?= url('inventory/adjustments/create') ?>" class="btn btn-warning btn-sm fw-semibold">
      <i class="bi bi-plus-circle me-1"></i>New Adjustment
    </a>
    <a href="<?= url('inventory') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-speedometer2 me-1"></i>Overview
    </a>
  </div>
</div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <input type="hidden" name="lt"  value="<?= e($locationType) ?>">
      <input type="hidden" name="lid" value="<?= (int)$locationId ?>">
      <div class="col-12 col-md-3">
        <input type="search" name="search" class="form-control form-control-sm"
               placeholder="Adjustment #, product…"
               value="<?= e($filters['search']??'') ?>">
      </div>
      <div class="col-6 col-md-2">
        <select name="status" class="form-select form-select-sm">
          <option value="">All Statuses</option>
          <?php foreach (['pending','approved','rejected','applied'] as $st): ?>
          <option value="<?= $st ?>" <?= ($filters['status']??'')===$st?'selected':'' ?>>
            <?= \AAM\Inventory\Models\StockAdjustmentModel::statusLabel($st) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <input type="date" name="date_from" class="form-control form-control-sm"
               value="<?= e($filters['date_from']??'') ?>">
      </div>
      <div class="col-6 col-md-2">
        <input type="date" name="date_to" class="form-control form-control-sm"
               value="<?= e($filters['date_to']??'') ?>">
      </div>
      <div class="col-auto">
        <button class="btn btn-primary btn-sm">Filter</button>
        <a href="<?= url('inventory/adjustments?lt='.e($locationType).'&lid='.(int)$locationId) ?>"
           class="btn btn-outline-secondary btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($adjustments)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-sliders display-6 d-block mb-2 opacity-25"></i>
        <small>No adjustment records found.</small>
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Number</th>
            <th>Product</th>
            <th>System Qty</th>
            <th>Physical Qty</th>
            <th>Variance</th>
            <th>Reason</th>
            <th>Requested By</th>
            <th>Date</th>
            <th class="pe-3">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($adjustments as $adj):
            $badge   = \AAM\Inventory\Models\StockAdjustmentModel::statusBadge($adj['status']);
            $label   = \AAM\Inventory\Models\StockAdjustmentModel::statusLabel($adj['status']);
            $variance = (float)$adj['variance'];
          ?>
          <tr>
            <td class="ps-3 small fw-semibold">
              <a href="<?= url('inventory/adjustments/' . $adj['id']) ?>"><?= e($adj['adjustment_number']) ?></a>
            </td>
            <td class="small">
              <div class="fw-semibold"><?= e($adj['product_name'] ?? '—') ?></div>
              <?php if ($adj['product_sku']): ?><div class="text-muted" style="font-size:.68rem;"><?= e($adj['product_sku']) ?></div><?php endif; ?>
            </td>
            <td class="small"><?= number_format((float)$adj['system_quantity'], 3) ?> <?= e($adj['unit_abbr']??'') ?></td>
            <td class="small"><?= number_format((float)$adj['physical_quantity'], 3) ?> <?= e($adj['unit_abbr']??'') ?></td>
            <td class="small fw-bold <?= $variance < 0 ? 'text-danger' : ($variance > 0 ? 'text-success' : 'text-muted') ?>">
              <?= $variance >= 0 ? '+' : '' ?><?= number_format($variance, 3) ?>
            </td>
            <td class="small text-muted"><?= e(\AAM\Inventory\Models\StockAdjustmentModel::REASONS[$adj['reason']] ?? $adj['reason']) ?></td>
            <td class="small text-muted"><?= e($adj['requested_by_name'] ?? '—') ?></td>
            <td class="small text-muted"><?= date('d/m/Y', strtotime($adj['created_at'])) ?></td>
            <td class="pe-3"><span class="badge bg-<?= $badge ?>"><?= $label ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (($pagination['totalPages']??1) > 1): ?>
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
      <small class="text-muted">Showing <?= $pagination['from'] ?>–<?= $pagination['to'] ?> of <?= number_format($pagination['total']) ?></small>
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
