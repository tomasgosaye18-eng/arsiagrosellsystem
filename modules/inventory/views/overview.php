<?php
/**
 * Inventory Overview — Phase 11
 * @var array  $kpis          @var string $locationType  @var int    $locationId
 * @var string $locationName  @var bool   $isAdmin       @var array  $expiringSoon
 * @var array  $lowStock      @var array  $recentAdj     @var string $userRole
 * @var array  $warehouses    @var array  $shops
 */
declare(strict_types=1);
$kpis         ??= [];
$expiringSoon ??= [];
$lowStock     ??= [];
$recentAdj    ??= [];
$fi  = fn($v) => number_format((int)$v);
$f   = fn($v) => number_format((float)$v, 1);
?>

<!-- Banner -->
<div class="card border-0 shadow-sm mb-4"
     style="background:linear-gradient(135deg,#198754 0%,#20c997 100%);color:#fff;">
  <div class="card-body d-flex align-items-center gap-3 py-3 flex-wrap">
    <div class="rounded-circle bg-white bg-opacity-20 d-flex align-items-center
                justify-content-center flex-shrink-0" style="width:52px;height:52px;">
      <i class="bi bi-clipboard-data fs-4"></i>
    </div>
    <div class="flex-grow-1">
      <h5 class="mb-0 fw-bold"><?= e($locationName) ?> — Inventory</h5>
      <small class="opacity-75"><?= date('l, d F Y') ?> &nbsp;&middot;&nbsp;
        <?= $isAdmin ? 'Organization-wide view' : ucfirst($locationType) . ' location' ?>
      </small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <?php if ($isAdmin): ?>
      <form class="d-flex gap-1 align-items-center" method="GET">
        <input type="hidden" name="lt" value="warehouse">
        <select name="lid" class="form-select form-select-sm" style="max-width:160px;" onchange="this.form.submit()">
          <option value="">— Warehouse —</option>
          <?php foreach ($warehouses as $w): ?>
          <option value="<?= $w['id'] ?>" <?= ($locationType==='warehouse'&&$locationId==$w['id'])?'selected':''?>>
            <?= e($w['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </form>
      <form class="d-flex gap-1 align-items-center" method="GET">
        <input type="hidden" name="lt" value="shop">
        <select name="lid" class="form-select form-select-sm" style="max-width:160px;" onchange="this.form.submit()">
          <option value="">— Shop —</option>
          <?php foreach ($shops as $s): ?>
          <option value="<?= $s['id'] ?>" <?= ($locationType==='shop'&&$locationId==$s['id'])?'selected':''?>>
            <?= e($s['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </form>
      <?php endif; ?>
      <a href="<?= url('inventory/adjustments/create') ?>" class="btn btn-light btn-sm fw-semibold">
        <i class="bi bi-sliders me-1"></i>New Adjustment
      </a>
    </div>
  </div>
</div>

<!-- KPIs -->
<div class="row g-3 mb-3">
  <?php
  $cards = $isAdmin ? [
    ['Total WH Products',  $fi($kpis['warehouse_products']??0), 'bi-building',           'primary'],
    ['WH Total Stock',     $f($kpis['warehouse_total']    ??0), 'bi-boxes',              'info'],
    ['WH Available',       $f($kpis['warehouse_available']??0), 'bi-check-circle',       'success'],
    ['WH Reserved',        $f($kpis['warehouse_reserved'] ??0), 'bi-lock',               'warning'],
    ['Shop Products',      $fi($kpis['shop_products']     ??0), 'bi-shop',               'primary'],
    ['Shop Stock',         $f($kpis['shop_total']         ??0), 'bi-cart3',              'info'],
    ['Out of Stock',       $fi(($kpis['wh_out_of_stock']??0)+0),'bi-x-circle',           'danger'],
    ['Low Stock',          $fi($kpis['wh_low_stock']      ??0), 'bi-exclamation-triangle','warning'],
  ] : [
    ['Products',    $fi($kpis['total_products']  ??0), 'bi-box-seam',             'primary'],
    ['Total Stock', $f($kpis['total_stock']      ??0), 'bi-boxes',                'info'],
    ['Available',   $f($kpis['available_stock']  ??0), 'bi-check-circle',         'success'],
    ['Reserved',    $f($kpis['reserved_stock']   ??0), 'bi-lock',                 'warning'],
    ['Out of Stock',$fi($kpis['out_of_stock']    ??0), 'bi-x-circle',             'danger'],
    ['Low Stock',   $fi($kpis['low_stock']       ??0), 'bi-exclamation-triangle', 'warning'],
    ['Expiring Soon',$fi($kpis['expiring_soon']  ??0), 'bi-calendar-x',           'warning'],
    ['Expired',     $fi($kpis['expired']         ??0), 'bi-calendar2-x',          'danger'],
  ];
  foreach ($cards as [$label, $value, $icon, $color]): ?>
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100" style="border-top:3px solid var(--bs-<?= $color ?>)!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small"><?= $label ?></span>
          <div class="rounded bg-<?= $color ?> bg-opacity-10 p-1">
            <i class="bi <?= $icon ?> text-<?= $color ?>"></i>
          </div>
        </div>
        <div class="fw-bold fs-3 lh-1"><?= $value ?></div>
        <div class="text-muted" style="font-size:.7rem;"><?= $label ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Alerts -->
<?php if (!empty($expiringSoon)): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3 py-2">
  <i class="bi bi-calendar-x-fill flex-shrink-0"></i>
  <strong><?= count($expiringSoon) ?> batch(es) expiring within 7 days.</strong>
  <a href="<?= url('inventory/batches?status=expiring_soon') ?>" class="ms-auto btn btn-warning btn-sm py-0 px-2">View</a>
</div>
<?php endif; ?>
<?php if (!empty($lowStock)): ?>
<div class="alert alert-danger d-flex align-items-center gap-2 mb-3 py-2">
  <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
  <strong><?= count($lowStock) ?> product(s) below minimum stock level.</strong>
  <a href="<?= url('warehouse/inventory?status=low_stock') ?>" class="ms-auto btn btn-danger btn-sm py-0 px-2">View</a>
</div>
<?php endif; ?>

<!-- Quick Links -->
<div class="row g-3 mb-3">
  <div class="col-12 col-md-4">
    <a href="<?= url('inventory/movements') ?>" class="card border-0 shadow-sm text-decoration-none h-100">
      <div class="card-body text-center py-3">
        <i class="bi bi-clock-history fs-3 text-primary mb-2 d-block"></i>
        <div class="fw-semibold small">Stock Movements</div>
        <div class="text-muted" style="font-size:.7rem;">Full ledger history</div>
      </div>
    </a>
  </div>
  <div class="col-12 col-md-4">
    <a href="<?= url('inventory/batches') ?>" class="card border-0 shadow-sm text-decoration-none h-100">
      <div class="card-body text-center py-3">
        <i class="bi bi-layers fs-3 text-info mb-2 d-block"></i>
        <div class="fw-semibold small">Batch Tracking</div>
        <div class="text-muted" style="font-size:.7rem;">FEFO batch view</div>
      </div>
    </a>
  </div>
  <div class="col-12 col-md-4">
    <a href="<?= url('inventory/adjustments') ?>" class="card border-0 shadow-sm text-decoration-none h-100">
      <div class="card-body text-center py-3">
        <i class="bi bi-sliders fs-3 text-warning mb-2 d-block"></i>
        <div class="fw-semibold small">Adjustments</div>
        <div class="text-muted" style="font-size:.7rem;">Inventory corrections</div>
      </div>
    </a>
  </div>
</div>

<!-- Inventory Snapshot -->
<?php if (!empty($invSnapshot) && !$isAdmin): ?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span class="fw-semibold small">
      <i class="bi bi-boxes me-2 text-primary"></i>Current Inventory — <?= e($locationName) ?>
    </span>
    <?php if ($locationType === 'warehouse'): ?>
    <a href="<?= url('warehouse/inventory') ?>" class="btn btn-outline-primary btn-sm">Full View</a>
    <?php else: ?>
    <a href="<?= url('shops') ?>" class="btn btn-outline-primary btn-sm">Shops</a>
    <?php endif; ?>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Product</th>
            <th>Category</th>
            <th>Available</th>
            <th>Reserved</th>
            <th>Min Level</th>
            <th class="pe-3">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($invSnapshot as $row):
            [$stLabel,$stColor] = \AAM\Warehouse\Models\WarehouseInventoryModel::stockStatusLabel(
                (float)$row['available_quantity'],
                (float)($row['min_stock_level'] ?? 0),
                $row['expiry_date'] ?? null
            );
            $unit = e($row['unit_abbr'] ?? '');
          ?>
          <tr>
            <td class="ps-3 small">
              <div class="fw-semibold"><?= e($row['product_name']) ?></div>
              <?php if ($row['product_sku']): ?><div class="text-muted" style="font-size:.68rem;"><?= e($row['product_sku']) ?></div><?php endif; ?>
            </td>
            <td class="small text-muted"><?= e($row['category_name'] ?? '—') ?></td>
            <td class="small fw-bold text-success"><?= number_format((float)$row['available_quantity'],1) ?> <?= $unit ?></td>
            <td class="small text-warning"><?= number_format((float)$row['reserved_quantity'],1) ?> <?= $unit ?></td>
            <td class="small text-muted"><?= (float)($row['min_stock_level']??0)>0 ? number_format((float)$row['min_stock_level'],1).' '.$unit : '—' ?></td>
            <td class="pe-3"><span class="badge bg-<?= $stColor ?>-subtle text-<?= $stColor ?>"><?= $stLabel ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Recent Adjustments -->
<div class="card border-0 shadow-sm mt-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span class="fw-semibold small"><i class="bi bi-sliders me-2 text-warning"></i>Recent Adjustments</span>
    <a href="<?= url('inventory/adjustments') ?>" class="btn btn-outline-warning btn-sm">View All</a>
  </div>
  <div class="card-body p-0">
    <?php if (empty($recentAdj)): ?>
      <div class="text-center py-4 text-muted small">No adjustments yet.</div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3 small">Number</th>
            <th class="small">Product</th>
            <th class="small">Variance</th>
            <th class="small">Reason</th>
            <th class="small pe-3">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentAdj as $adj):
            $badge = \AAM\Inventory\Models\StockAdjustmentModel::statusBadge($adj['status']);
            $label = \AAM\Inventory\Models\StockAdjustmentModel::statusLabel($adj['status']);
          ?>
          <tr>
            <td class="ps-3 small fw-semibold">
              <a href="<?= url('inventory/adjustments/' . $adj['id']) ?>"><?= e($adj['adjustment_number']) ?></a>
            </td>
            <td class="small"><?= e($adj['product_name'] ?? '—') ?></td>
            <td class="small <?= (float)$adj['variance'] < 0 ? 'text-danger' : 'text-success' ?>">
              <?= (float)$adj['variance'] >= 0 ? '+' : '' ?><?= number_format((float)$adj['variance'], 3) ?>
            </td>
            <td class="small text-muted"><?= e(\AAM\Inventory\Models\StockAdjustmentModel::REASONS[$adj['reason']] ?? $adj['reason']) ?></td>
            <td class="pe-3"><span class="badge bg-<?= $badge ?>"><?= $label ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
