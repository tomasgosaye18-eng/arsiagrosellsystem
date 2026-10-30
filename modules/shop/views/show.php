<?php
/**
 * Shop Detail — Phase 12 (updated)
 * @var array  $shop   @var array  $kpis   @var array  $recentTransfers
 * @var array  $todaySales  @var string $userRole
 */
declare(strict_types=1);
$shop            ??= [];
$kpis            ??= [];
$recentTransfers ??= [];
$todaySales      ??= [];
$f  = fn($v) => number_format((float)$v, 1);
$fi = fn($v) => number_format((int)$v);

$isSeller      = ($userRole ?? '') === ROLE_SALESPERSON;
$isAdmin       = in_array($userRole ?? '', [ROLE_ADMIN, ROLE_GENERAL_MANAGER], true);
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold">
    <i class="bi bi-shop me-2 text-primary"></i><?= e($shop['name'] ?? 'Shop') ?>
  </h5>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($isSeller): ?>
    <a href="<?= url('pos') ?>" class="btn btn-success btn-sm">
      <i class="bi bi-cart3 me-1"></i>POS Terminal
    </a>
    <?php endif; ?>
    <a href="<?= url('shops/' . ($shop['id'] ?? '') . '/inventory') ?>"
       class="btn btn-primary btn-sm">
      <i class="bi bi-boxes me-1"></i>Inventory
    </a>
    <?php if (!$isSeller): ?>
    <a href="<?= url('shops/' . ($shop['id'] ?? '') . '/sales') ?>"
       class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-receipt me-1"></i>Sales
    </a>
    <?php endif; ?>
    <a href="<?= url('shops') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>All Shops
    </a>
  </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-3">
  <?php
  $cards = [
    ['Products',      $fi($kpis['total_products'] ??0),  'bi-box-seam',            'primary'],
    ['Total Stock',   $f($kpis['total_stock']     ??0),  'bi-boxes',               'info'],
    ['Available',     $f($kpis['available_stock'] ??0),  'bi-check-circle',        'success'],
    ['Out of Stock',  $fi($kpis['out_of_stock']   ??0),  'bi-x-circle',            'danger'],
    ['Low Stock',     $fi($kpis['low_stock']      ??0),  'bi-exclamation-triangle','warning'],
    ['Today Sales',   'ETB '.number_format((float)($todaySales['total_sales']??0),2), 'bi-cash-coin', 'success'],
  ];
  foreach ($cards as [$label,$value,$icon,$color]): ?>
  <div class="col-6 col-md-4 col-lg-2">
    <div class="card border-0 shadow-sm" style="border-top:3px solid var(--bs-<?= $color ?>)!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <span class="text-muted small"><?= $label ?></span>
          <div class="rounded bg-<?= $color ?> bg-opacity-10 p-1">
            <i class="bi <?= $icon ?> text-<?= $color ?> small"></i>
          </div>
        </div>
        <div class="fw-bold fs-5 lh-1"><?= $value ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <!-- Shop Details -->
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header"><span class="fw-semibold small">Shop Details</span></div>
      <div class="card-body">
        <?php
          $shopImgUrl = !empty($shop['shop_image']) ? upload_url('shops/' . $shop['shop_image']) : null;
        ?>
        <?php if ($shopImgUrl): ?>
        <div class="mb-3" style="border-radius:8px;overflow:hidden;height:140px;">
          <img src="<?= e($shopImgUrl) ?>"
               alt="<?= e($shop['name'] ?? 'Shop') ?>"
               style="width:100%;height:100%;object-fit:cover;"
               onerror="this.parentElement.style.display='none'">
        </div>
        <?php endif; ?>
        <dl class="row small mb-0">
          <dt class="col-sm-4 text-muted">Name</dt>
          <dd class="col-sm-8"><?= e($shop['name'] ?? '—') ?></dd>
          <dt class="col-sm-4 text-muted">Location</dt>
          <dd class="col-sm-8"><?= e($shop['location'] ?? '—') ?></dd>
          <?php if (!empty($shop['description'])): ?>
          <dt class="col-sm-4 text-muted">Description</dt>
          <dd class="col-sm-8 text-muted"><?= e($shop['description']) ?></dd>
          <?php endif; ?>
          <dt class="col-sm-4 text-muted">Today Tx</dt>
          <dd class="col-sm-8 fw-semibold text-primary"><?= $fi($todaySales['total_transactions'] ?? 0) ?></dd>
          <dt class="col-sm-4 text-muted">Today Sales</dt>
          <dd class="col-sm-8 fw-bold text-success">ETB <?= number_format((float)($todaySales['total_sales'] ?? 0), 2) ?></dd>
        </dl>

        <?php if ($isSeller): ?>
        <div class="mt-3 d-flex gap-2">
          <a href="<?= url('pos') ?>" class="btn btn-success btn-sm">
            <i class="bi bi-cart3 me-1"></i>Open POS
          </a>
          <a href="<?= url('pos/shift') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-person-badge me-1"></i>My Shift
          </a>
          <a href="<?= url('pos/history') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-receipt me-1"></i>Sales
          </a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Recent Transfers -->
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold small">
          <i class="bi bi-arrow-right-circle me-2 text-primary"></i>Recent Transfers
        </span>
        <?php if (!$isSeller): ?>
        <a href="<?= url('transfers?shop_id=' . ($shop['id'] ?? '')) ?>"
           class="btn btn-outline-primary btn-sm">View All</a>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <?php if (empty($recentTransfers)): ?>
          <div class="text-center py-4 text-muted small">No transfer records yet.</div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th class="ps-3 small">Transfer #</th>
                <th class="small">Warehouse</th>
                <th class="text-end small">Qty</th>
                <th class="small pe-3">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentTransfers as $t):
                $badge = \AAM\Warehouse\Models\TransferModel::statusBadge($t['status']);
                $label = \AAM\Warehouse\Models\TransferModel::statusLabel($t['status']);
              ?>
              <tr>
                <td class="ps-3 small fw-semibold">
                  <?php if (!$isSeller): ?>
                  <a href="<?= url('transfers/' . $t['id']) ?>"><?= e($t['transfer_number']) ?></a>
                  <?php else: ?>
                  <?= e($t['transfer_number']) ?>
                  <?php endif; ?>
                </td>
                <td class="small text-muted"><?= e($t['warehouse_name'] ?? '—') ?></td>
                <td class="text-end small"><?= number_format((float)$t['total_quantity'], 1) ?></td>
                <td class="pe-3"><span class="badge bg-<?= $badge ?>"><?= $label ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
