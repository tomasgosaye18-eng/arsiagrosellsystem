<?php
/**
 * Expiry Report — AAM Warehouse Reports
 * @var array  $rows          @var array $filters     @var array $products
 * @var int    $expiredCount  @var int   $soonCount   @var int   $normalCount
 * @var array  $warehouse
 */
declare(strict_types=1);
$rows         ??= [];
$filters      ??= [];
$products     ??= [];
$expiredCount ??= 0;
$soonCount    ??= 0;
$normalCount  ??= 0;

function expiryStatus(?int $days): array {
    if ($days === null) return ['—', 'secondary'];
    if ($days < 0)   return ['EXPIRED',       'danger'];
    if ($days <= 7)  return ['EXPIRING SOON', 'danger'];
    if ($days <= 30) return ['EXPIRING SOON', 'warning'];
    return ['NORMAL', 'success'];
}
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <a href="<?= url('reports/warehouse') ?>" class="btn btn-outline-secondary btn-sm me-2"><i class="bi bi-arrow-left me-1"></i>Back</a>
    <span class="fw-bold fs-5"><i class="bi bi-calendar-x me-2 text-warning"></i>Expiry Report</span>
    <small class="text-muted ms-2"><?= e($warehouse['name'] ?? '') ?></small>
  </div>
  <button onclick="window.print()" class="btn btn-outline-secondary btn-sm d-print-none">
    <i class="bi bi-printer me-1"></i>Print
  </button>
</div>

<!-- Alerts -->
<?php if ($expiredCount > 0): ?>
<div class="alert alert-danger d-flex align-items-center gap-2 mb-3 py-2">
  <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
  <strong><?= $expiredCount ?> product(s) have EXPIRED.</strong> Immediate action required.
</div>
<?php endif; ?>
<?php if ($soonCount > 0): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3 py-2">
  <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
  <strong><?= $soonCount ?> product(s) expiring within 30 days.</strong> Plan for usage or disposal.
</div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="row g-2 mb-3">
  <?php foreach ([
    ['Total with Expiry', count($rows),     'secondary'],
    ['Expired',           $expiredCount,    'danger'],
    ['Expiring Soon',     $soonCount,       'warning'],
    ['Normal',            $normalCount,     'success'],
  ] as [$l,$v,$c]): ?>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center py-2">
      <div class="fw-bold fs-4 text-<?= $c ?>"><?= $v ?></div>
      <div class="text-muted small"><?= $l ?></div>
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
          <option value="<?= $p['id'] ?>" <?= ($filters['product_id']??0)==$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <select name="status" class="form-select form-select-sm">
          <option value="">All Statuses</option>
          <option value="expired"       <?= ($filters['status']??'')==='expired'       ?'selected':'' ?>>Expired</option>
          <option value="expiring_soon" <?= ($filters['status']??'')==='expiring_soon' ?'selected':'' ?>>Expiring Soon (30 days)</option>
          <option value="normal"        <?= ($filters['status']??'')==='normal'        ?'selected':'' ?>>Normal</option>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <input type="text" name="batch" class="form-control form-control-sm"
               placeholder="Batch #" value="<?= e($filters['batch']??'') ?>">
      </div>
      <div class="col-auto">
        <button class="btn btn-primary btn-sm">Filter</button>
        <a href="<?= url('reports/warehouse/expiry') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($rows)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-calendar-check display-6 d-block mb-2 opacity-25"></i>
        <small>No products with expiry dates found. Products without expiry dates are not shown here.</small>
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Product</th><th>Code</th><th>Batch</th>
            <th>Qty Available</th><th>Unit</th>
            <th>Production Date</th><th>Expiry Date</th>
            <th>Days Remaining</th><th class="pe-3">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            $days = isset($r['days_remaining']) ? (int)$r['days_remaining'] : null;
            [$sl, $sc] = expiryStatus($days);
            $rowClass = $sc === 'danger' ? 'table-danger' : ($sc === 'warning' ? 'table-warning' : '');
          ?>
          <tr class="<?= $rowClass ?>">
            <td class="ps-3 small fw-semibold"><?= e($r['product_name']??'—') ?></td>
            <td class="small text-muted"><?= e($r['product_code']??'—') ?></td>
            <td class="small"><?= e($r['batch_number']??'—') ?></td>
            <td class="small fw-bold"><?= number_format((float)$r['available_quantity'],3) ?></td>
            <td class="small"><?= e($r['unit_abbr']??'—') ?></td>
            <td class="small text-muted">
              <?= $r['production_date'] ? date('d/m/Y', strtotime($r['production_date'])) : '—' ?>
            </td>
            <td class="small fw-semibold <?= $sc === 'danger' ? 'text-danger' : '' ?>">
              <?= $r['expiry_date'] ? date('d/m/Y', strtotime($r['expiry_date'])) : '—' ?>
            </td>
            <td class="small fw-bold <?= $sc === 'danger' ? 'text-danger' : ($sc === 'warning' ? 'text-warning' : 'text-success') ?>">
              <?php if ($days === null): ?>—
              <?php elseif ($days < 0): ?>
                <span class="badge bg-danger">Expired <?= abs($days) ?> days ago</span>
              <?php else: ?>
                <?= $days ?> days
              <?php endif; ?>
            </td>
            <td class="pe-3">
              <span class="badge bg-<?= $sc ?>-subtle text-<?= $sc ?> small"><?= $sl ?></span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="px-3 py-2 border-top text-muted small">
      Showing <?= count($rows) ?> product(s) with expiry dates &mdash; Sorted by expiry date (earliest first)
    </div>
    <?php endif; ?>
  </div>
</div>
