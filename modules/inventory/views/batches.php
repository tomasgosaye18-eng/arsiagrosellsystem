<?php
/**
 * Inventory — Batch Inventory — Phase 11
 * @var array  $batches      @var array  $pagination   @var array  $filters
 * @var string $locationType @var int    $locationId   @var string $locationName
 */
declare(strict_types=1);
$batches    ??= [];
$pagination ??= [];
$filters    ??= [];
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold">
    <i class="bi bi-layers me-2 text-info"></i>
    <?= e($locationName) ?> — Batch Inventory
  </h5>
  <a href="<?= url('inventory') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-speedometer2 me-1"></i>Overview
  </a>
</div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <input type="hidden" name="lt"  value="<?= e($locationType) ?>">
      <input type="hidden" name="lid" value="<?= (int)$locationId ?>">
      <div class="col-12 col-md-4">
        <input type="search" name="search" class="form-control form-control-sm"
               placeholder="Product, batch, SKU…"
               value="<?= e($filters['search']??'') ?>">
      </div>
      <div class="col-6 col-md-2">
        <select name="status" class="form-select form-select-sm">
          <option value="">All Statuses</option>
          <option value="active"       <?= ($filters['status']??'')==='active'       ?'selected':'' ?>>Active</option>
          <option value="expiring_soon"<?= ($filters['status']??'')==='expiring_soon'?'selected':'' ?>>Expiring Soon</option>
          <option value="expired"      <?= ($filters['status']??'')==='expired'      ?'selected':'' ?>>Expired</option>
        </select>
      </div>
      <div class="col-auto">
        <button class="btn btn-primary btn-sm">Filter</button>
        <a href="<?= url('inventory/batches?lt='.e($locationType).'&lid='.(int)$locationId) ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($batches)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-layers display-6 d-block mb-2 opacity-25"></i>
        <small>No batch records found.</small>
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Product</th>
            <th>Category</th>
            <th>Batch</th>
            <th>Quantity</th>
            <th>Unit</th>
            <th>Production Date</th>
            <th>Expiry Date</th>
            <th class="pe-3">Expiry Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($batches as $b):
            [$exLabel, $exBadge] = \AAM\Inventory\Models\InventoryBatchModel::expiryStatus($b['expiry_date'] ?? null);
          ?>
          <tr>
            <td class="ps-3">
              <div class="fw-semibold small"><?= e($b['product_name']) ?></div>
              <?php if ($b['product_sku']): ?><div class="text-muted" style="font-size:.68rem;"><?= e($b['product_sku']) ?></div><?php endif; ?>
            </td>
            <td class="small text-muted"><?= e($b['category_name'] ?? '—') ?></td>
            <td class="small fw-semibold"><?= e($b['batch_number']) ?></td>
            <td class="small fw-bold <?= (float)$b['quantity']<=0?'text-muted':'' ?>"><?= number_format((float)$b['quantity'],3) ?></td>
            <td class="small text-muted"><?= e($b['unit_abbr'] ?? '—') ?></td>
            <td class="small text-muted"><?= $b['production_date'] ? date('d/m/Y',strtotime($b['production_date'])) : '—' ?></td>
            <td class="small <?= ($b['expiry_date']&&strtotime($b['expiry_date'])<time())?'text-danger fw-bold':'' ?>">
              <?= $b['expiry_date'] ? date('d/m/Y',strtotime($b['expiry_date'])) : '—' ?>
            </td>
            <td class="pe-3"><span class="badge bg-<?= $exBadge ?>"><?= $exLabel ?></span></td>
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
