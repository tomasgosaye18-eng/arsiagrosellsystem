<?php
/**
 * Shop Stock Movements
 * @var array  $shop  @var int $shopId  @var array $movements
 * @var array  $pagination  @var array $filters  @var string $userRole
 */
declare(strict_types=1);
$movements  ??= [];
$filters    ??= [];
$shop       ??= null;
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold">
    <i class="bi bi-clock-history me-2 text-primary"></i>
    Stock Movements — <?= e($shop['name'] ?? 'Shop') ?>
  </h5>
  <a href="<?= url('dashboard') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Dashboard
  </a>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-12 col-md-3">
        <input type="search" name="search" class="form-control form-control-sm"
               placeholder="Product name or reference #…" value="<?= e($filters['search'] ?? '') ?>">
      </div>
      <div class="col-6 col-md-2">
        <select name="movement_type" class="form-select form-select-sm">
          <option value="">All Types</option>
          <?php foreach ([
            'transfer_in' => 'Transfer In',
            'sale'        => 'Sale',
            'adjustment'  => 'Adjustment',
            'return'      => 'Return',
            'discrepancy' => 'Discrepancy',
            'damage'      => 'Damage',
            'expiry'      => 'Expiry',
          ] as $v => $l): ?>
          <option value="<?= $v ?>" <?= ($filters['movement_type']??'')===$v?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <input type="date" name="date_from" class="form-control form-control-sm"
               value="<?= e($filters['date_from'] ?? '') ?>">
      </div>
      <div class="col-6 col-md-2">
        <input type="date" name="date_to" class="form-control form-control-sm"
               value="<?= e($filters['date_to'] ?? '') ?>">
      </div>
      <div class="col-auto">
        <button class="btn btn-primary btn-sm">Filter</button>
        <a href="<?= url('shop/stock-movements') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($movements)): ?>
    <div class="text-center py-5 text-muted">
      <i class="bi bi-clock-history display-6 d-block mb-2 opacity-25"></i>
      No stock movement records found.
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Date/Time</th>
            <th>Product</th>
            <th>Batch</th>
            <th>Type</th>
            <th class="text-end">Before</th>
            <th class="text-end text-success">In</th>
            <th class="text-end text-danger">Out</th>
            <th class="text-end">After</th>
            <th>Reference</th>
            <th class="pe-3">By</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($movements as $m):
            $typeBadge = \AAM\Warehouse\Models\StockMovementModel::typeBadge($m['movement_type'] ?? '');
            $typeLabel = \AAM\Warehouse\Models\StockMovementModel::typeLabel($m['movement_type'] ?? '');
          ?>
          <tr>
            <td class="ps-3 small text-muted">
              <?= date('d/m/Y H:i', strtotime($m['moved_at'])) ?>
            </td>
            <td class="small">
              <div class="fw-semibold"><?= e($m['product_name'] ?? '—') ?></div>
              <div class="text-muted" style="font-size:.68rem;"><?= e($m['product_sku'] ?? '') ?></div>
            </td>
            <td class="small text-muted"><?= e($m['batch_number'] ?? '—') ?></td>
            <td>
              <span class="badge bg-<?= $typeBadge ?>-subtle text-<?= $typeBadge ?> border border-<?= $typeBadge ?>-subtle small">
                <?= $typeLabel ?>
              </span>
            </td>
            <td class="small text-end text-muted"><?= number_format((float)$m['quantity_before'], 3) ?></td>
            <td class="small text-end <?= (float)$m['quantity_in'] > 0 ? 'fw-semibold text-success' : 'text-muted' ?>">
              <?= (float)$m['quantity_in'] > 0 ? '+' . number_format((float)$m['quantity_in'], 3) : '—' ?>
            </td>
            <td class="small text-end <?= (float)$m['quantity_out'] > 0 ? 'fw-semibold text-danger' : 'text-muted' ?>">
              <?= (float)$m['quantity_out'] > 0 ? '-' . number_format((float)$m['quantity_out'], 3) : '—' ?>
            </td>
            <td class="small text-end fw-semibold"><?= number_format((float)$m['quantity_after'], 3) ?></td>
            <td class="small text-muted"><?= e($m['reference_number'] ?? '—') ?></td>
            <td class="pe-3 small text-muted"><?= e($m['performed_by_name'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
      <small class="text-muted">
        Showing <?= $pagination['from'] ?>–<?= $pagination['to'] ?> of <?= number_format($pagination['total']) ?>
      </small>
      <nav><ul class="pagination pagination-sm mb-0">
        <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
        <li class="page-item <?= $p === ($pagination['page'] ?? 1) ? 'active' : '' ?>">
          <a class="page-link" href="?page=<?= $p ?>&<?= http_build_query(array_filter($filters)) ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>
      </ul></nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
