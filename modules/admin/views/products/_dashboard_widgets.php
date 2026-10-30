<?php
/**
 * =========================================================
 * PRODUCT DASHBOARD WIDGETS – PARTIAL
 * =========================================================
 * File    : modules/admin/views/products/_dashboard_widgets.php
 * Purpose : Reusable product management widgets for admin
 *           dashboard: total products, by-category chart,
 *           near-reorder list, inactive products, recent.
 * Usage   : <?php include view_path('admin/products/_dashboard_widgets.php'); ?>
 *           Or rendered via DashboardController.
 * Deps    : ProductModel::getStatistics(),
 *           ProductModel::getProductsByCategory(),
 *           ProductModel::getRecentProducts()
 * =========================================================
 * @var array $productStats
 * @var array $productsByCategory
 * @var array $recentProducts
 */
declare(strict_types=1);

$stats      = $productStats ?? [];
$byCategory = $productsByCategory ?? [];
$recent     = $recentProducts ?? [];
?>

<!-- ── Product KPI Row ──────────────────────────────────── -->
<div class="row g-2 mb-3">

  <!-- Total Products -->
  <div class="col-6 col-xl-3">
    <a href="<?= url('admin/products') ?>" class="text-decoration-none">
      <div class="card border-0 shadow-sm h-100 kpi-primary">
        <div class="card-body d-flex align-items-center gap-3 py-3 px-3">
          <div class="kpi-icon rounded-3 bg-primary bg-opacity-10
                      d-flex align-items-center justify-content-center flex-shrink-0"
               style="width:48px;height:48px;">
            <i class="bi bi-box-seam-fill text-primary fs-4"></i>
          </div>
          <div>
            <div class="fw-bold fs-3 lh-1 mb-1">
              <?= number_format((int)($stats['total_products'] ?? 0)) ?>
            </div>
            <div class="text-muted small">Total Products</div>
          </div>
        </div>
      </div>
    </a>
  </div>

  <!-- Active Products -->
  <div class="col-6 col-xl-3">
    <a href="<?= url('admin/products') ?>?is_active=1" class="text-decoration-none">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3 py-3 px-3">
          <div class="rounded-3 bg-success bg-opacity-10
                      d-flex align-items-center justify-content-center flex-shrink-0"
               style="width:48px;height:48px;">
            <i class="bi bi-check-circle-fill text-success fs-4"></i>
          </div>
          <div>
            <div class="fw-bold fs-3 lh-1 mb-1">
              <?= number_format((int)($stats['active_products'] ?? 0)) ?>
            </div>
            <div class="text-muted small">Active</div>
          </div>
        </div>
      </div>
    </a>
  </div>

  <!-- Categories Used -->
  <div class="col-6 col-xl-3">
    <a href="<?= url('admin/categories') ?>" class="text-decoration-none">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3 py-3 px-3">
          <div class="rounded-3 bg-info bg-opacity-10
                      d-flex align-items-center justify-content-center flex-shrink-0"
               style="width:48px;height:48px;">
            <i class="bi bi-tags-fill text-info fs-4"></i>
          </div>
          <div>
            <div class="fw-bold fs-3 lh-1 mb-1">
              <?= number_format((int)($stats['categories_used'] ?? 0)) ?>
            </div>
            <div class="text-muted small">Categories</div>
          </div>
        </div>
      </div>
    </a>
  </div>

  <!-- Out of Stock / Inactive -->
  <div class="col-6 col-xl-3">
    <a href="<?= url('admin/products') ?>?product_status=OUT_OF_STOCK" class="text-decoration-none">
      <div class="card border-0 shadow-sm h-100 <?= (int)($stats['out_of_stock'] ?? 0) > 0 ? 'border-warning border-2' : '' ?>">
        <div class="card-body d-flex align-items-center gap-3 py-3 px-3">
          <div class="rounded-3 bg-warning bg-opacity-10
                      d-flex align-items-center justify-content-center flex-shrink-0"
               style="width:48px;height:48px;">
            <i class="bi bi-exclamation-triangle-fill text-warning fs-4"></i>
          </div>
          <div>
            <div class="fw-bold fs-3 lh-1 mb-1">
              <?= number_format((int)($stats['out_of_stock'] ?? 0)) ?>
            </div>
            <div class="text-muted small">Out of Stock</div>
          </div>
        </div>
      </div>
    </a>
  </div>

</div><!-- /KPI row -->

<!-- ── Products by Category + Recent Products ─────────── -->
<div class="row g-3 mb-3">

  <!-- Products by Category -->
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
        <h6 class="fw-semibold mb-0">
          <i class="bi bi-pie-chart-fill me-2 text-primary"></i>Products by Category
        </h6>
        <a href="<?= url('admin/categories') ?>" class="btn btn-outline-secondary btn-sm py-0 px-2"
           style="font-size:.75rem;">View All</a>
      </div>
      <div class="card-body p-3">
        <?php if (empty($byCategory)): ?>
          <div class="text-center text-muted py-3 small">No data yet</div>
        <?php else: ?>
          <?php
          $maxCount = max(array_column($byCategory, 'product_count'));
          $palette  = ['#0d6efd','#198754','#0dcaf0','#ffc107','#dc3545',
                       '#6f42c1','#fd7e14','#20c997','#e83e8c','#6c757d'];
          $i = 0;
          foreach ($byCategory as $row):
            $pct   = $maxCount > 0 ? round(($row['product_count'] / $maxCount) * 100) : 0;
            $color = $palette[$i % count($palette)];
            $i++;
          ?>
          <div class="mb-2">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <div class="d-flex align-items-center gap-1">
                <i class="bi <?= e($row['icon'] ?? 'bi-tag') ?>" style="color:<?= $color ?>; font-size:.8rem;"></i>
                <span class="small fw-semibold"><?= e($row['category_name']) ?></span>
              </div>
              <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-dark border small"><?= (int)$row['product_count'] ?></span>
                <span class="text-muted" style="font-size:.68rem;"><?= (int)$row['active_count'] ?> active</span>
              </div>
            </div>
            <div class="progress" style="height:6px;border-radius:3px;">
              <div class="progress-bar" role="progressbar"
                   style="width:<?= $pct ?>%;background:<?= $color ?>;"
                   aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Recently Added Products -->
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
        <h6 class="fw-semibold mb-0">
          <i class="bi bi-clock-history me-2 text-success"></i>Recently Added Products
        </h6>
        <a href="<?= url('admin/products') ?>" class="btn btn-outline-secondary btn-sm py-0 px-2"
           style="font-size:.75rem;">View All</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($recent)): ?>
          <div class="text-center text-muted py-4 small">No products yet</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Product</th>
                  <th>Category</th>
                  <th class="text-end">Price</th>
                  <th class="text-muted pe-3" style="font-size:.7rem;">Added</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recent as $p): ?>
                <tr>
                  <td class="ps-3">
                    <div class="d-flex align-items-center gap-2">
                      <?php if (!empty($p['primary_image']) && file_exists($p['primary_image'])): ?>
                      <img src="<?= upload_url(str_replace('public/uploads/', '', $p['primary_image'])) ?>"
                           alt="" class="rounded" style="width:32px;height:32px;object-fit:cover;">
                      <?php else: ?>
                      <div class="rounded bg-light d-flex align-items-center justify-content-center"
                           style="width:32px;height:32px;flex-shrink:0;">
                        <i class="bi bi-image text-muted" style="font-size:.75rem;"></i>
                      </div>
                      <?php endif; ?>
                      <div>
                        <div class="fw-semibold small lh-sm">
                          <a href="<?= url('admin/products/' . $p['product_id']) ?>"
                             class="text-decoration-none text-dark">
                            <?= e(mb_strimwidth($p['product_name'], 0, 30, '…')) ?>
                          </a>
                        </div>
                        <div class="text-muted font-monospace" style="font-size:.68rem;">
                          <?= e($p['product_code']) ?>
                        </div>
                      </div>
                    </div>
                  </td>
                  <td class="small text-muted"><?= e($p['category_name']) ?></td>
                  <td class="text-end fw-semibold small">
                    ETB <?= number_format((float)$p['selling_price'], 2) ?>
                    <div class="text-muted" style="font-size:.68rem;">/<?= e($p['unit_abbreviation']) ?></div>
                  </td>
                  <td class="pe-3 text-muted" style="font-size:.72rem;">
                    <?= date_display($p['created_at']) ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div><!-- /row -->

<!-- ── Quick Action Buttons ─────────────────────────────── -->
<div class="row g-2">
  <div class="col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-body py-2 px-3 d-flex flex-wrap gap-2 align-items-center">
        <span class="text-muted small fw-semibold me-2">Products:</span>
        <a href="<?= url('admin/products/create') ?>" class="btn btn-primary btn-sm">
          <i class="bi bi-plus-lg me-1"></i>Add Product
        </a>
        <a href="<?= url('admin/categories/create') ?>" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-tag me-1"></i>Add Category
        </a>
        <a href="<?= url('admin/units/create') ?>" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-rulers me-1"></i>Add Unit
        </a>
        <a href="<?= url('admin/products') ?>?product_status=OUT_OF_STOCK"
           class="btn btn-outline-warning btn-sm">
          <i class="bi bi-exclamation-triangle me-1"></i>Out of Stock
        </a>
        <a href="<?= url('admin/products') ?>?is_active=0"
           class="btn btn-outline-danger btn-sm">
          <i class="bi bi-slash-circle me-1"></i>Inactive Products
        </a>
        <a href="<?= url('admin/prices/recent') ?>"
           class="btn btn-outline-info btn-sm">
          <i class="bi bi-graph-up me-1"></i>Recent Price Changes
        </a>
      </div>
    </div>
  </div>
</div>
