<?php
/**
 * Stock Movement History — Phase 10
 * @var array  $movements   @var array $pagination  @var array $filters
 * @var array  $products    @var array $warehouse   @var int   $warehouseId
 * @var string $userRole
 */
declare(strict_types=1);
$movements  ??= [];
$filters    ??= [];
$products   ??= [];
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold">
    <i class="bi bi-clock-history me-2 text-secondary"></i>Stock Movement History
    <small class="text-muted fw-normal fs-6 ms-1"><?= e($warehouse['name'] ?? '') ?></small>
  </h5>
  <a href="<?= url('warehouse') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-speedometer2 me-1"></i>Overview
  </a>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <input type="hidden" name="wid" value="<?= $warehouseId ?>">
      <div class="col-12 col-md-3">
        <select name="product_id" class="form-select form-select-sm">
          <option value="">All Products</option>
          <?php foreach ($products as $p): ?>
          <option value="<?= $p['id'] ?>" <?= ($filters['product_id'] ?? 0) == $p['id'] ? 'selected' : '' ?>>
            <?= e($p['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <select name="movement_type" class="form-select form-select-sm">
          <option value="">All Types</option>
          <?php foreach (['receiving','transfer_out','transfer_in','adjustment','discrepancy','return','initial'] as $t): ?>
          <option value="<?= $t ?>" <?= ($filters['movement_type'] ?? '') === $t ? 'selected' : '' ?>>
            <?= \AAM\Warehouse\Models\StockMovementModel::typeLabel($t) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <input type="date" name="date_from" class="form-control form-control-sm"
               value="<?= e($filters['date_from'] ?? '') ?>" placeholder="From">
      </div>
      <div class="col-6 col-md-2">
        <input type="date" name="date_to" class="form-control form-control-sm"
               value="<?= e($filters['date_to'] ?? '') ?>" placeholder="To">
      </div>
      <div class="col-auto">
        <button class="btn btn-primary btn-sm">Filter</button>
        <a href="<?= url('warehouse/movements') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Movement Table -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($movements)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-clock-history display-6 d-block mb-2 opacity-25"></i>
        <p class="mb-0 small">No stock movements recorded yet.</p>
        <small>Movements are created when you confirm receivings or dispatch transfers.</small>
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Date &amp; Time</th>
            <th>Product</th>
            <th>Batch</th>
            <th>Type</th>
            <th class="text-success">In</th>
            <th class="text-danger">Out</th>
            <th>Balance</th>
            <th>Reference</th>
            <th class="pe-3">Performed By</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($movements as $m):
            $tbadge = \AAM\Warehouse\Models\StockMovementModel::typeBadge($m['movement_type']);
            $tlabel = \AAM\Warehouse\Models\StockMovementModel::typeLabel($m['movement_type']);
          ?>
          <tr>
            <td class="ps-3 small text-muted">
              <?= $m['moved_at'] ? date('d/m/Y H:i', strtotime($m['moved_at'])) : '—' ?>
            </td>
            <td class="small">
              <div class="fw-semibold"><?= e($m['product_name'] ?? '—') ?></div>
              <?php if (!empty($m['product_sku'])): ?>
              <div class="text-muted" style="font-size:.68rem;"><?= e($m['product_sku']) ?></div>
              <?php endif; ?>
            </td>
            <td class="small text-muted"><?= e($m['batch_number'] ?? '—') ?></td>
            <td>
              <span class="badge bg-<?= $tbadge ?>-subtle text-<?= $tbadge ?> small"><?= $tlabel ?></span>
            </td>
            <td class="small fw-semibold text-success">
              <?= (float)$m['quantity_in'] > 0 ? '+' . number_format((float)$m['quantity_in'], 3) : '—' ?>
            </td>
            <td class="small fw-semibold text-danger">
              <?= (float)$m['quantity_out'] > 0 ? '-' . number_format((float)$m['quantity_out'], 3) : '—' ?>
            </td>
            <td class="small fw-bold"><?= number_format((float)$m['quantity_after'], 3) ?></td>
            <td class="small text-muted">
              <?php if (!empty($m['reference_number'])): ?>
                <span title="<?= e($m['reference_type'] ?? '') ?>">
                  <?= e($m['reference_number']) ?>
                </span>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
            <td class="small text-muted pe-3"><?= e($m['performed_by_name'] ?? '—') ?></td>
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
      <nav aria-label="Movements pagination">
        <ul class="pagination pagination-sm mb-0">
          <?php
          $currentPage  = $pagination['page'] ?? 1;
          $totalPages   = $pagination['totalPages'] ?? 1;
          $queryParams  = array_filter($filters);
          $queryParams['wid'] = $warehouseId;
          for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++):
          ?>
          <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $p ?>&<?= http_build_query($queryParams) ?>"><?= $p ?></a>
          </li>
          <?php endfor; ?>
        </ul>
      </nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
