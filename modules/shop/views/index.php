<?php
/**
 * Shop List — Phase 12 (updated)
 * @var array  $shops    @var string $userRole
 */
declare(strict_types=1);
$shops ??= [];
$f  = fn($v) => number_format((float)$v, 1);
$fi = fn($v) => number_format((int)$v);

$isSeller    = in_array($userRole ?? '', [ROLE_SALESPERSON], true);
$isAdmin     = in_array($userRole ?? '', [ROLE_ADMIN, ROLE_GENERAL_MANAGER], true);
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold">
    <i class="bi bi-shop me-2 text-primary"></i>Retail Shops
  </h5>
  <?php if ($isSeller): ?>
  <a href="<?= url('pos') ?>" class="btn btn-success btn-sm">
    <i class="bi bi-cart3 me-1"></i>Go to POS Terminal
  </a>
  <?php endif; ?>
</div>

<?php if (empty($shops)): ?>
<div class="card border-0 shadow-sm">
  <div class="card-body text-center py-5 text-muted">
    <i class="bi bi-shop display-6 d-block mb-2 opacity-25"></i>
    <small>No shops found.</small>
  </div>
</div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($shops as $shop): ?>
  <div class="col-12 col-md-6 col-lg-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <!-- Shop header -->
        <div class="d-flex align-items-start gap-3 mb-3">
          <?php if (!empty($shop['shop_image'])): ?>
          <div class="rounded flex-shrink-0 overflow-hidden" style="width:48px;height:48px;">
            <img src="<?= e(upload_url('shops/' . $shop['shop_image'])) ?>"
                 alt="<?= e($shop['name'] ?? 'Shop') ?>"
                 style="width:100%;height:100%;object-fit:cover;"
                 onerror="this.parentElement.innerHTML='<div class=\'d-flex align-items-center justify-content-center h-100 bg-primary bg-opacity-10\' style=\'border-radius:50%\'><i class=\'bi bi-shop text-primary fs-5\'></i></div>'">
          </div>
          <?php else: ?>
          <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center
                      justify-content-center flex-shrink-0" style="width:48px;height:48px;">
            <i class="bi bi-shop text-primary fs-5"></i>
          </div>
          <?php endif; ?>
          <div class="overflow-hidden flex-grow-1">
            <div class="fw-bold text-truncate"><?= e($shop['name']) ?></div>
            <div class="text-muted small text-truncate"><?= e($shop['location'] ?? '—') ?></div>
            <?php if (!empty($shop['shop_code'])): ?>
            <div class="text-secondary" style="font-size:.7rem;"><?= e($shop['shop_code']) ?></div>
            <?php endif; ?>
          </div>
          <span class="badge bg-<?= ($shop['is_active'] ?? 1) ? 'success' : 'secondary' ?>-subtle
                                 text-<?= ($shop['is_active'] ?? 1) ? 'success' : 'secondary' ?>
                                 border border-<?= ($shop['is_active'] ?? 1) ? 'success' : 'secondary' ?>
                                 border-opacity-25 small flex-shrink-0">
            <?= ($shop['is_active'] ?? 1) ? 'Active' : 'Inactive' ?>
          </span>
        </div>

        <!-- KPI mini-grid -->
        <div class="row g-2 text-center mb-3">
          <div class="col-3">
            <div class="fw-bold text-primary"><?= $fi($shop['product_count'] ?? 0) ?></div>
            <div class="text-muted" style="font-size:.65rem;">Products</div>
          </div>
          <div class="col-3">
            <div class="fw-bold text-info"><?= $fi($shop['sellers_count'] ?? 0) ?></div>
            <div class="text-muted" style="font-size:.65rem;">Sellers</div>
          </div>
          <div class="col-3">
            <div class="fw-bold text-<?= ($shop['out_of_stock'] ?? 0) > 0 ? 'danger' : 'success' ?>">
              <?= $fi($shop['out_of_stock'] ?? 0) ?>
            </div>
            <div class="text-muted" style="font-size:.65rem;">Out Stock</div>
          </div>
          <div class="col-3">
            <div class="fw-bold text-<?= ($shop['today_sales'] ?? 0) > 0 ? 'success' : 'muted' ?>">
              <?= number_format((float)($shop['today_sales'] ?? 0), 0) ?>
            </div>
            <div class="text-muted" style="font-size:.65rem;">Today Sales</div>
          </div>
        </div>
        <!-- Stock value row -->
        <div class="d-flex justify-content-between align-items-center mb-3
                    px-2 py-1 rounded bg-light">
          <small class="text-muted">Stock Value</small>
          <small class="fw-semibold text-primary">
            ETB <?= number_format((float)($shop['stock_value'] ?? 0), 2) ?>
          </small>
        </div>

        <!-- Action buttons (role-filtered) -->
        <div class="d-flex gap-2 flex-wrap">
          <?php if ($isSeller): ?>
          <a href="<?= url('pos') ?>"
             class="btn btn-success btn-sm flex-grow-1">
            <i class="bi bi-cart3 me-1"></i>POS
          </a>
          <?php endif; ?>
          <a href="<?= url('shops/' . $shop['id'] . '/inventory') ?>"
             class="btn btn-primary btn-sm <?= ($isSeller) ? '' : 'flex-grow-1' ?>">
            <i class="bi bi-boxes me-1"></i>Inventory
          </a>
          <?php if (!$isSeller): ?>
          <a href="<?= url('shops/' . $shop['id'] . '/sales') ?>"
             class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-receipt me-1"></i>Sales
          </a>
          <?php endif; ?>
          <a href="<?= url('shops/' . $shop['id']) ?>"
             class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-eye"></i>
          </a>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
