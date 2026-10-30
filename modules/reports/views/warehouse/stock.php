<?php
/**
 * Stock Report — AAM Warehouse Reports
 * @var array $rows @var array $summary @var array $filters
 * @var array $categories @var array $products @var array $warehouse
 */
declare(strict_types=1);
$rows    ??= [];
$filters ??= [];

function stockStatus(float $avail, float $minLevel, ?string $expiry): array {
    if ($expiry && strtotime($expiry) < time()) return ['EXPIRED','danger'];
    if ($expiry && strtotime($expiry) <= strtotime('+7 days')) return ['EXPIRING SOON','warning'];
    if ($avail <= 0) return ['OUT OF STOCK','danger'];
    if ($minLevel > 0 && $avail <= $minLevel) return ['LOW STOCK','warning'];
    return ['IN STOCK','success'];
}
?>

<!-- Header + Print -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <a href="<?= url('reports/warehouse') ?>" class="btn btn-outline-secondary btn-sm me-2">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>
    <span class="fw-bold fs-5"><i class="bi bi-boxes me-2 text-primary"></i>Stock Report</span>
    <small class="text-muted ms-2"><?= e($warehouse['name'] ?? '') ?></small>
  </div>
  <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-printer me-1"></i>Print
  </button>
</div>

<!-- Summary Cards -->
<div class="row g-2 mb-3">
  <?php foreach ([
    ['Total Products', $summary['total_products'] ?? 0, 'secondary'],
    ['Available',      number_format((float)($summary['total_available'] ?? 0),1), 'success'],
    ['Low Stock',      $summary['low_stock'] ?? 0,   'warning'],
    ['Out of Stock',   $summary['out_of_stock'] ?? 0,'danger'],
    ['Expired',        $summary['expired'] ?? 0,     'danger'],
  ] as [$l,$v,$c]): ?>
  <div class="col-6 col-md-2">
    <div class="card border-0 shadow-sm text-center py-2">
      <div class="fw-bold text-<?= $c ?>"><?= $v ?></div>
      <div class="text-muted" style="font-size:.72rem;"><?= $l ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3 d-print-none">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-12 col-md-3">
        <select name="product_id" class="form-select form-select-sm">
          <option value="">All Products</option>
          <?php foreach ($products as $p): ?>
          <option value="<?= $p['id'] ?>" <?= ($filters['product_id'] ?? 0) == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <select name="category_id" class="form-select form-select-sm">
          <option value="">All Categories</option>
          <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ($filters['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <select name="status" class="form-select form-select-sm">
          <option value="">All Statuses</option>
          <?php foreach (['in_stock'=>'In Stock','low_stock'=>'Low Stock','out_of_stock'=>'Out of Stock','expiring_soon'=>'Expiring Soon','expired'=>'Expired'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= ($filters['status'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <input type="text" name="batch" class="form-control form-control-sm"
               placeholder="Batch #" value="<?= e($filters['batch'] ?? '') ?>">
      </div>
      <div class="col-auto">
        <button class="btn btn-primary btn-sm">Filter</button>
        <a href="<?= url('reports/warehouse/stock') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($rows)): ?>
      <div class="text-center py-5 text-muted"><i class="bi bi-boxes display-6 d-block mb-2 opacity-25"></i><small>No inventory records found.</small></div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Product</th><th>Code</th><th>Category</th>
            <th>Available</th><th>Reserved</th><th>Unit</th>
            <th>Batch</th><th>Expiry</th><th>Status</th><th class="pe-3">Last Movement</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            [$sl, $sc] = stockStatus((float)$r['available_quantity'], (float)($r['min_stock_level']??0), $r['expiry_date']??null);
          ?>
          <tr>
            <td class="ps-3 small fw-semibold"><?= e($r['product_name']) ?></td>
            <td class="small text-muted"><?= e($r['product_code'] ?? '—') ?></td>
            <td class="small"><?= e($r['category_name'] ?? '—') ?></td>
            <td class="small fw-bold text-success"><?= number_format((float)$r['available_quantity'],3) ?></td>
            <td class="small text-warning"><?= number_format((float)$r['reserved_quantity'],3) ?></td>
            <td class="small"><?= e($r['unit_abbr'] ?? '—') ?></td>
            <td class="small"><?= e($r['batch_number'] ?? '—') ?></td>
            <td class="small <?= ($r['expiry_date'] && strtotime($r['expiry_date']) < time()) ? 'text-danger fw-bold' : '' ?>">
              <?= $r['expiry_date'] ? date('d/m/Y', strtotime($r['expiry_date'])) : '—' ?>
            </td>
            <td><span class="badge bg-<?= $sc ?>-subtle text-<?= $sc ?> small"><?= $sl ?></span></td>
            <td class="small text-muted pe-3">
              <?= $r['last_movement_at'] ? date('d/m/Y H:i', strtotime($r['last_movement_at'])) : '—' ?>
              <?php if ($r['last_movement_type']): ?>
              <div style="font-size:.65rem;"><?= ucfirst(str_replace('_',' ',$r['last_movement_type'])) ?></div>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="px-3 py-2 border-top text-muted small">
      Showing <?= count($rows) ?> product(s)
    </div>
    <?php endif; ?>
  </div>
</div>
