<?php
/**
 * GM — Product Catalog (Read-only + Pricing)
 * Route: GET /gm/products
 *
 * @var array  $products
 * @var array  $categories
 * @var array  $filters
 * @var array  $pagination
 * @var int    $noPriceCount
 */
declare(strict_types=1);

$products     ??= [];
$categories   ??= [];
$filters      ??= [];
$pagination   ??= [];
$noPriceCount ??= 0;

$n = fn($v, $d=0) => is_numeric($v) ? number_format((float)$v, $d) : '—';
$c = fn($v) => 'ETB ' . number_format((float)$v, 2);

$statusBadge = [
    'ACTIVE'        => 'bg-success',
    'INACTIVE'      => 'bg-secondary',
    'DISCONTINUED'  => 'bg-danger',
    'OUT_OF_STOCK'  => 'bg-warning text-dark',
    'ARCHIVED'      => 'bg-dark',
];
?>
<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h5 class="fw-bold mb-0"><i class="bi bi-box-seam-fill me-2 text-primary"></i>Product Catalog</h5>
    <small class="text-muted">General Manager — view products &amp; manage selling prices</small>
  </div>
  <a href="<?= url('gm/dashboard') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-speedometer2 me-1"></i>Dashboard
  </a>
</div>

<?php if ($noPriceCount > 0): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3 py-2" role="alert">
  <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
  <div>
    <strong><?= $noPriceCount ?> product<?= $noPriceCount !== 1 ? 's' : '' ?> have no selling price set</strong>
    and are currently <strong>blocked from POS sales</strong>.
    <a href="<?= url('gm/products?price_filter=no_price') ?>" class="alert-link ms-1">View unpriced products →</a>
  </div>
</div>
<?php endif; ?>

<!-- Filter bar -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2 px-3">
    <form method="GET" action="" class="row g-2 align-items-end">
      <div class="col-md-3">
        <input type="text" name="search" class="form-control form-control-sm"
               placeholder="Search name, SKU, barcode…" value="<?= e($filters['search'] ?? '') ?>">
      </div>
      <div class="col-md-3">
        <select name="category_id" class="form-select form-select-sm">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id'] ?>" <?= (int)($filters['category_id'] ?? 0) === (int)$cat['id'] ? 'selected' : '' ?>>
            <?= e($cat['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="status" class="form-select form-select-sm">
          <option value="">All Statuses</option>
          <?php foreach (['ACTIVE','INACTIVE','DISCONTINUED','OUT_OF_STOCK','ARCHIVED'] as $s): ?>
          <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>>
            <?= ucfirst(strtolower(str_replace('_', ' ', $s))) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="price_filter" class="form-select form-select-sm">
          <option value="">All Prices</option>
          <option value="no_price" <?= ($filters['price_filter'] ?? '') === 'no_price' ? 'selected' : '' ?>>
            No Price Set (POS Blocked)
          </option>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-1">
        <button class="btn btn-primary btn-sm px-3"><i class="bi bi-search me-1"></i>Search</button>
        <a href="<?= url('gm/products') ?>" class="btn btn-outline-secondary btn-sm px-2">
          <i class="bi bi-x"></i>
        </a>
      </div>
    </form>
  </div>
</div>

<!-- Stats strip -->
<div class="row g-2 mb-3">
  <div class="col-auto">
    <span class="text-muted small">
      Showing <?= count($products) ?> of <?= $n($pagination['total'] ?? 0) ?> products
      <?php if (($filters['price_filter'] ?? '') === 'no_price'): ?>
      <span class="badge bg-warning text-dark ms-1">Unpriced / POS Blocked</span>
      <?php endif; ?>
    </span>
  </div>
</div>

<!-- Product table -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($products)): ?>
    <div class="text-center py-5 text-muted">
      <i class="bi bi-box-seam d-block fs-3 mb-2 opacity-25"></i>
      <small>No products found matching the selected filters.</small>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Product</th>
            <th>SKU</th>
            <th>Category</th>
            <th>Unit</th>
            <th class="text-end">Selling Price</th>
            <th class="text-end">WH Stock</th>
            <th class="text-end">Shop Stock</th>
            <th class="text-end">Total Stock</th>
            <th class="text-center">Status</th>
            <th class="text-end pe-3">Pricing</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $p):
            $totalStock = (float)($p['warehouse_stock'] ?? 0) + (float)($p['shop_stock'] ?? 0);
            $minLevel   = (float)($p['min_stock_level'] ?? 0);
            $stockClass = $totalStock <= 0 ? 'text-danger fw-semibold' : ($minLevel > 0 && $totalStock <= $minLevel ? 'text-warning fw-semibold' : '');
          ?>
          <tr>
            <td class="ps-3">
              <div class="small fw-semibold"><?= e($p['product_name'] ?? '—') ?></div>
              <?php if (!empty($p['barcode'])): ?>
              <div class="text-muted" style="font-size:.68rem;"><?= e($p['barcode']) ?></div>
              <?php endif; ?>
            </td>
            <td class="small text-muted"><?= e($p['sku'] ?? '—') ?></td>
            <td class="small"><?= e($p['category_name'] ?? '—') ?></td>
            <td class="small text-muted"><?= e($p['unit_abbr'] ?? $p['unit_name'] ?? '—') ?></td>
            <td class="text-end small fw-semibold">
              <?php
              $sp = (float)($p['selling_price'] ?? 0);
              if ($sp > 0): ?>
              <span class="text-success">ETB <?= number_format($sp, 2) ?></span>
              <?php else: ?>
              <span class="badge bg-warning text-dark" title="No approved selling price — product is blocked from POS">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>Not Set
              </span>
              <?php endif; ?>
            </td>
            <td class="text-end small"><?= $n($p['warehouse_stock'] ?? 0, 1) ?></td>
            <td class="text-end small"><?= $n($p['shop_stock'] ?? 0, 1) ?></td>
            <td class="text-end small <?= $stockClass ?>"><?= $n($totalStock, 1) ?></td>
            <td class="text-center">
              <span class="badge <?= $statusBadge[$p['product_status'] ?? ''] ?? 'bg-secondary' ?> small">
                <?= ucfirst(strtolower(str_replace('_', ' ', $p['product_status'] ?? '—'))) ?>
              </span>
              <?php if ($p['is_featured'] ?? false): ?>
              <i class="bi bi-star-fill text-warning ms-1" title="Featured" style="font-size:.7rem;"></i>
              <?php endif; ?>
            </td>
            <td class="text-end pe-3">
              <?php $sp2 = (float)($p['selling_price'] ?? 0); ?>
              <a href="<?= url('gm/products/' . (int)$p['id'] . '/pricing') ?>"
                 class="btn btn-sm <?= $sp2 > 0 ? 'btn-outline-success' : 'btn-warning' ?>"
                 title="<?= $sp2 > 0 ? 'Update Selling Price' : 'Set Selling Price — Product is blocked from POS until price is set' ?>">
                <i class="bi bi-tag-fill me-1"></i><?= $sp2 > 0 ? 'Pricing' : 'Set Price' ?>
              </a>
              <a href="<?= url('gm/products/' . (int)$p['id'] . '/price-history') ?>"
                 class="btn btn-sm btn-outline-secondary ms-1" title="Price History & Analytics">
                <i class="bi bi-graph-up"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if (($pagination['last_page'] ?? 1) > 1): ?>
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
      <small class="text-muted">
        Page <?= $pagination['current_page'] ?> of <?= $pagination['last_page'] ?>
        (<?= $n($pagination['total']) ?> products)
      </small>
      <div class="d-flex gap-1 flex-wrap">
        <?php
        $cur = $pagination['current_page'];
        $last = $pagination['last_page'];
        $pages = array_unique(array_filter([1, $cur-1, $cur, $cur+1, $last], fn($p) => $p >= 1 && $p <= $last));
        sort($pages);
        $prev = null;
        foreach ($pages as $pg):
          if ($prev !== null && $pg - $prev > 1): ?>
          <span class="btn btn-sm btn-outline-secondary disabled px-2">…</span>
          <?php endif; ?>
          <a href="?<?= http_build_query(array_merge($filters, ['page' => $pg])) ?>"
             class="btn btn-sm <?= $pg === $cur ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $pg ?></a>
        <?php $prev = $pg; endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Notice -->
<div class="mt-3 text-muted small d-flex align-items-center gap-2">
  <i class="bi bi-shield-lock-fill text-success"></i>
  Use the <strong>Pricing</strong> / <strong>Set Price</strong> button to set or update the official selling price.
  Only the General Manager can set selling prices.
  Products with no price are <span class="text-danger fw-semibold">blocked from POS sales</span> until a price is set.
</div>
