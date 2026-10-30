<?php
/**
 * =========================================================
 * PRODUCTS – LIST VIEW
 * =========================================================
 * File    : modules/admin/views/products/index.php
 * Purpose : Enterprise product list — search, filter,
 *           barcode lookup, status management, pagination.
 * Deps    : ProductController, ProductModel, CategoryModel
 *
 * Role behaviour:
 *   ROLE_ADMIN / ROLE_GENERAL_MANAGER — full management UI
 *   All other roles (incl. ROLE_FARM_MANAGER) — read-only
 *     catalog: no price/cost/financial data, no write actions.
 * =========================================================
 */
declare(strict_types=1);

// True only for roles that may manage products.
// ROLE_ADMIN can manage products but should NOT see cost/price financial data.
// ROLE_GENERAL_MANAGER is the only role that sees price/cost columns.
$isMgr      = in_array($userRole ?? '', [ROLE_ADMIN, ROLE_GENERAL_MANAGER], true);
$canSeePrice = ($userRole ?? '') === ROLE_GENERAL_MANAGER;

$statusBadge = static function (string $status): string {
    $map = [
        'active'       => ['bg-success',          'Active'],
        'inactive'     => ['bg-secondary',         'Inactive'],
        'discontinued' => ['bg-danger',            'Discontinued'],
        'out_of_stock' => ['bg-warning text-dark', 'Out of Stock'],
    ];
    [$cls, $lbl] = $map[strtolower($status)] ?? ['bg-light text-dark border', ucfirst(str_replace('_',' ',$status))];
    return '<span class="badge ' . $cls . ' rounded-pill">' . e($lbl) . '</span>';
};

// URL helpers — non-managers use the read-only /products route.
$listUrl = $isMgr ? url('admin/products') : url('products');
$showUrl = static fn(int $id): string => $isMgr
    ? url('admin/products/' . $id)
    : url('products/' . $id);
?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-box-seam-fill me-2 text-primary"></i>
      <?= $isMgr ? 'Product Management' : 'Product Catalog' ?>
    </h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item active">Products</li>
      </ol>
    </nav>
  </div>
  <?php if ($isMgr): ?>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= url('admin/categories') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-tags me-1"></i>Categories
    </a>
    <a href="<?= url('admin/units') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-rulers me-1"></i>Units
    </a>
    <a href="<?= url('admin/products/create') ?>" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i>Add Product
    </a>
  </div>
  <?php endif; ?>
</div>

<!-- ── Stats Strip ──────────────────────────────────────── -->
<div class="row g-2 mb-3">
<?php
// Farm Manager sees only operational stats (total / active / inactive / categories).
// Managers see all six including featured and out-of-stock.
$statItems = $isMgr
    ? [
        ['total_products',   'Total',       'bi-box-seam-fill',            'primary'],
        ['active_products',  'Active',      'bi-check-circle-fill',        'success'],
        ['status_inactive',  'Inactive',    'bi-slash-circle-fill',        'secondary'],
        ['out_of_stock',     'Out of Stock','bi-exclamation-triangle-fill','warning'],
        ['featured_products','Featured',    'bi-star-fill',                'info'],
        ['categories_used',  'Categories',  'bi-tags-fill',                'dark'],
      ]
    : [
        ['total_products',  'Total',      'bi-box-seam-fill',     'primary'],
        ['active_products', 'Active',     'bi-check-circle-fill', 'success'],
        ['status_inactive', 'Inactive',   'bi-slash-circle-fill', 'secondary'],
        ['categories_used', 'Categories', 'bi-tags-fill',         'dark'],
      ];

$colClass = $isMgr ? 'col-6 col-sm-4 col-md-2' : 'col-6 col-sm-3';
foreach ($statItems as [$key, $label, $icon, $color]): ?>
<div class="<?= $colClass ?>">
  <div class="card border-0 shadow-sm h-100">
    <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
      <i class="bi <?= $icon ?> text-<?= $color ?> fs-5"></i>
      <div>
        <div class="fw-bold lh-1"><?= (int)($stats[$key] ?? 0) ?></div>
        <div class="text-muted" style="font-size:.7rem;"><?= $label ?></div>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- ── Filters Bar ───────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-3" id="filtersCard">
  <div class="card-body py-2 px-3">
    <form method="GET" action="<?= $listUrl ?>" id="filterForm" class="row g-2 align-items-end">

      <!-- Search -->
      <div class="col-md-4">
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
          <input type="search" class="form-control" name="search" id="searchInput"
                 value="<?= e($filters['search'] ?? '') ?>"
                 placeholder="Search products, code, barcode…" autocomplete="off">
        </div>
      </div>

      <!-- Category -->
      <div class="col-md-3">
        <select class="form-select form-select-sm" name="category_id" id="categoryFilter">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
          <option value="<?= (int)$cat['category_id'] ?>"
            <?= (string)($filters['category_id'] ?? '') === (string)$cat['category_id'] ? 'selected' : '' ?>>
            <?= e($cat['category_name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Status -->
      <div class="col-md-2">
        <select class="form-select form-select-sm" name="product_status" id="statusFilter">
          <option value="">All Status</option>
          <?php foreach ($statuses as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= ($filters['product_status'] ?? '') === $k ? 'selected' : '' ?>>
            <?= e($v) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ($canSeePrice): ?>
      <!-- Price Range — General Manager only -->
      <div class="col-md-2">
        <div class="input-group input-group-sm">
          <input type="number" class="form-control" name="price_min"
                 value="<?= e($filters['price_min'] ?? '') ?>" placeholder="Min ₿" min="0" step="0.01">
          <input type="number" class="form-control" name="price_max"
                 value="<?= e($filters['price_max'] ?? '') ?>" placeholder="Max ₿" min="0" step="0.01">
        </div>
      </div>
      <div class="col-md-1 d-flex gap-1">
      <?php else: ?>
      <div class="col-md-3 d-flex gap-1">
      <?php endif; ?>
        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
          <i class="bi bi-funnel me-1"></i>Filter
        </button>
        <?php if (array_filter($filters)): ?>
        <a href="<?= $listUrl ?>" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-x-lg"></i>
        </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- ── Barcode Quick Search ──────────────────────────────── -->
<div class="card border-0 shadow-sm mb-3 border-start border-3 border-info">
  <div class="card-body py-2 px-3">
    <div class="row g-2 align-items-center">
      <div class="col-auto">
        <i class="bi bi-upc-scan text-info fs-5"></i>
        <span class="fw-semibold ms-1 small">Barcode Lookup</span>
      </div>
      <div class="col-md-4">
        <div class="input-group input-group-sm">
          <input type="text" class="form-control font-monospace" id="barcodeSearchInput"
                 placeholder="Scan or type barcode…" autocomplete="off">
          <button class="btn btn-info btn-sm" id="barcodeSearchBtn" type="button">
            <i class="bi bi-search"></i>
          </button>
        </div>
      </div>
      <div class="col" id="barcodeResult"></div>
    </div>
  </div>
</div>

<!-- ── Products Table ────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($products)): ?>
    <div class="text-center py-5 text-muted">
      <i class="bi bi-box-seam display-4 d-block mb-3 opacity-25"></i>
      <h6 class="fw-semibold">No products found</h6>
      <?php if (array_filter($filters)): ?>
        <a href="<?= $listUrl ?>" class="btn btn-outline-secondary btn-sm mt-2">Clear Filters</a>
      <?php elseif ($isMgr): ?>
        <a href="<?= url('admin/products/create') ?>" class="btn btn-primary btn-sm mt-2">
          <i class="bi bi-plus-lg me-1"></i>Add First Product
        </a>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle" id="productsTable">
        <thead class="table-light">
          <tr>
            <?php if ($isMgr): ?>
            <th class="ps-3" style="width:36px;">
              <input type="checkbox" class="form-check-input" id="selectAll">
            </th>
            <?php endif; ?>
            <th style="width:56px;">Image</th>
            <th>Product</th>
            <th>Barcode</th>
            <th>Category</th>
            <th>Unit</th>
            <?php if ($canSeePrice): ?>
            <th class="text-end">Cost</th>
            <th class="text-end">Price</th>
            <?php endif; ?>
            <th class="text-center">Status</th>
            <?php if ($isMgr): ?>
            <th class="text-center">Featured</th>
            <?php endif; ?>
            <th>Updated</th>
            <th class="text-end pe-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $p):
            // primary_image is stored as 'public/uploads/products/filename.jpg'
            // file_exists() works relative to ROOT_PATH (app/), so this is correct.
            $imgRelPath = $p['primary_image'] ?? '';
            $hasImage   = $imgRelPath !== '' && file_exists($imgRelPath);
          ?>
          <tr data-id="<?= (int)$p['product_id'] ?>">
            <?php if ($isMgr): ?>
            <td class="ps-3">
              <input type="checkbox" class="form-check-input row-check" value="<?= (int)$p['product_id'] ?>">
            </td>
            <?php endif; ?>
            <td>
              <?php if ($hasImage): ?>
              <img src="<?= upload_url(str_replace('public/uploads/', '', $imgRelPath)) ?>"
                   alt="<?= e($p['product_name']) ?>"
                   class="rounded"
                   style="width:44px;height:44px;object-fit:cover;"
                   data-bs-toggle="tooltip" data-bs-placement="right"
                   title="<?= e($p['product_name']) ?>">
              <?php else: ?>
              <div class="rounded bg-light d-flex align-items-center justify-content-center"
                   style="width:44px;height:44px;">
                <i class="bi bi-image text-muted" style="opacity:.35;"></i>
              </div>
              <?php endif; ?>
            </td>
            <td>
              <div class="fw-semibold">
                <a href="<?= $showUrl((int)$p['product_id']) ?>"
                   class="text-decoration-none text-dark">
                  <?= e($p['product_name']) ?>
                </a>
                <?php if ($isMgr && $p['is_featured']): ?>
                  <i class="bi bi-star-fill text-warning ms-1" title="Featured" style="font-size:.75rem;"></i>
                <?php endif; ?>
                <?php if ($p['requires_expiry']): ?>
                  <i class="bi bi-clock-history text-info ms-1" title="Expiry tracked" style="font-size:.75rem;"></i>
                <?php endif; ?>
              </div>
              <div class="text-muted" style="font-size:.72rem;">
                <span class="font-monospace"><?= e($p['product_code']) ?></span>
                <?php if (!empty($p['brand'])): ?> · <?= e($p['brand']) ?><?php endif; ?>
              </div>
              <?php if (!empty($p['scientific_name'])): ?>
              <div class="text-muted fst-italic" style="font-size:.68rem;"><?= e($p['scientific_name']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <span class="font-monospace small text-muted"><?= e($p['barcode'] ?? '—') ?></span>
              <?php if ($isMgr && !empty($p['barcode'])): ?>
              <a href="<?= url('admin/products/' . $p['product_id'] . '/barcode/print') ?>"
                 target="_blank" class="btn btn-xs btn-link p-0 ms-1 text-secondary" title="Print barcode">
                <i class="bi bi-printer" style="font-size:.75rem;"></i>
              </a>
              <?php endif; ?>
            </td>
            <td>
              <div class="d-flex align-items-center gap-1">
                <?php if (!empty($p['category_icon'])): ?>
                <i class="bi <?= e($p['category_icon']) ?> text-primary" style="font-size:.85rem;"></i>
                <?php endif; ?>
                <span class="small"><?= e($p['category_name']) ?></span>
              </div>
            </td>
            <td class="small">
              <?= e($p['unit_abbreviation'] ?? $p['unit_name'] ?? '—') ?>
            </td>
            <?php if ($canSeePrice): ?>
            <td class="text-end small text-muted">
              <?= $p['cost_price'] > 0 ? 'ETB ' . number_format((float)$p['cost_price'], 2) : '—' ?>
            </td>
            <td class="text-end fw-semibold">
              <?php
              $sp = (float)($p['selling_price'] ?? 0);
              if ($sp > 0): ?>
              <span class="text-success">ETB <?= number_format($sp, 2) ?></span>
              <?php if (!empty($p['markup_percentage'])): ?>
              <div class="text-muted" style="font-size:.7rem;">+<?= round($p['markup_percentage'], 1) ?>%</div>
              <?php endif; ?>
              <?php else: ?>
              <span class="badge bg-warning text-dark"
                    title="No selling price set — blocked from POS until GM sets a price">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>Not Set
              </span>
              <?php endif; ?>
            </td>
            <?php endif; ?>
            <td class="text-center">
              <?php if ($isMgr): ?>
              <button class="btn btn-sm px-1 py-0 border-0 bg-transparent btn-toggle-status"
                      data-id="<?= (int)$p['product_id'] ?>"
                      data-action="<?= url('admin/products/' . $p['product_id'] . '/toggle') ?>">
                <?= $statusBadge($p['product_status']) ?>
              </button>
              <?php else: ?>
              <?= $statusBadge($p['product_status']) ?>
              <?php endif; ?>
            </td>
            <?php if ($isMgr): ?>
            <td class="text-center">
              <button class="btn btn-sm px-1 py-0 border-0 bg-transparent btn-toggle-featured"
                      data-id="<?= (int)$p['product_id'] ?>"
                      data-action="<?= url('admin/products/' . $p['product_id'] . '/toggle-featured') ?>"
                      title="<?= $p['is_featured'] ? 'Remove from featured' : 'Mark as featured' ?>">
                <i class="bi <?= $p['is_featured'] ? 'bi-star-fill text-warning' : 'bi-star text-muted' ?>"></i>
              </button>
            </td>
            <?php endif; ?>
            <td class="small text-muted"><?= date_display($p['updated_at']) ?></td>
            <td class="text-end pe-3">
              <div class="d-flex justify-content-end gap-1">
                <a href="<?= $showUrl((int)$p['product_id']) ?>"
                   class="btn btn-sm btn-outline-info" title="View details">
                  <i class="bi bi-eye-fill"></i>
                </a>
                <?php if ($isMgr): ?>
                <a href="<?= url('admin/products/' . $p['product_id'] . '/edit') ?>"
                   class="btn btn-sm btn-outline-primary" title="Edit">
                  <i class="bi bi-pencil-fill"></i>
                </a>
                <button class="btn btn-sm btn-outline-danger btn-delete"
                        data-id="<?= (int)$p['product_id'] ?>"
                        data-name="<?= e($p['product_name']) ?>"
                        data-action="<?= url('admin/products/' . $p['product_id'] . '/delete') ?>"
                        title="Delete">
                  <i class="bi bi-trash3-fill"></i>
                </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Table Footer -->
    <div class="d-flex flex-wrap justify-content-between align-items-center px-3 py-3 border-top gap-2">
      <div class="text-muted small">
        Showing <strong><?= count($products) ?></strong> of
        <strong><?= $pagination['total_records'] ?></strong> products
        <?php if ($isMgr): ?>
        <span class="ms-3 text-primary" id="selectedCount" style="display:none;">
          <i class="bi bi-check2-square me-1"></i><span id="selectedNum">0</span> selected –
          <button type="button" class="btn btn-link p-0 small" id="batchPrintBtn">
            <i class="bi bi-printer me-1"></i>Print Barcodes
          </button>
        </span>
        <?php endif; ?>
      </div>
      <?php if ($pagination['total_pages'] > 1): ?>
      <nav aria-label="Products pagination">
        <ul class="pagination pagination-sm mb-0">
          <?php for ($pg = 1; $pg <= $pagination['total_pages']; $pg++): ?>
          <li class="page-item <?= $pg === $pagination['current_page'] ? 'active' : '' ?>">
            <a class="page-link"
               href="<?= $listUrl ?>?page=<?= $pg ?>&<?= http_build_query(array_filter($filters)) ?>">
              <?= $pg ?>
            </a>
          </li>
          <?php endfor; ?>
        </ul>
      </nav>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($isMgr): ?>
<!-- Hidden batch-print form — managers only -->
<form id="batchForm" method="POST" action="<?= url('admin/barcode/batch-print') ?>" target="_blank">
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
  <input type="hidden" name="size" value="medium">
  <div id="batchInputs"></div>
</form>
<?php endif; ?>

<?php $view->section('scripts'); ?>
<script>
(function () {
  'use strict';
  var isMgr     = <?= $isMgr ? 'true' : 'false' ?>;
  var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
  var listUrl   = '<?= $listUrl ?>';
  var barcodeSearchUrl = '<?= url('admin/products/data/barcode') ?>';

  // ── Bootstrap tooltips ──────────────────────────────────
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el);
  });

  // ── Manager-only: Select All + Batch Print ──────────────
  if (isMgr) {
    var selectAll     = document.getElementById('selectAll');
    var selectedCount = document.getElementById('selectedCount');
    var selectedNum   = document.getElementById('selectedNum');

    function updateSelectionUI() {
      var n = document.querySelectorAll('.row-check:checked').length;
      selectedCount.style.display = n > 0 ? 'inline' : 'none';
      if (selectedNum) selectedNum.textContent = n;
    }

    selectAll?.addEventListener('change', function () {
      document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
      updateSelectionUI();
    });
    document.querySelectorAll('.row-check').forEach(cb => {
      cb.addEventListener('change', updateSelectionUI);
    });

    document.getElementById('batchPrintBtn')?.addEventListener('click', function () {
      var ids = [...document.querySelectorAll('.row-check:checked')].map(cb => cb.value);
      var batchInputs = document.getElementById('batchInputs');
      batchInputs.innerHTML = ids
        .map(id => '<input type="hidden" name="product_ids[]" value="' + id + '">')
        .join('');
      document.getElementById('batchForm').submit();
    });
  }

  // ── AJAX helper ─────────────────────────────────────────
  function post(url, cb) {
    var fd = new FormData();
    fd.append('csrf_token', csrfToken);
    fetch(url, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: fd
    })
    .then(r => r.json())
    .then(resp => resp.success ? cb(resp) : AAM.toast(resp.message, 'error'))
    .catch(() => AAM.toast('Network error', 'error'));
  }

  // ── Manager-only: Toggle Status ─────────────────────────
  if (isMgr) {
    document.querySelectorAll('.btn-toggle-status').forEach(btn => {
      btn.addEventListener('click', function () {
        post(this.dataset.action, () => {
          AAM.toast('Status updated', 'success');
          setTimeout(() => location.reload(), 700);
        });
      });
    });

    // ── Manager-only: Toggle Featured ─────────────────────
    document.querySelectorAll('.btn-toggle-featured').forEach(btn => {
      btn.addEventListener('click', function () {
        post(this.dataset.action, resp => {
          var icon = this.querySelector('i');
          if (resp.is_featured) {
            icon.className = 'bi bi-star-fill text-warning';
            this.title = 'Remove from featured';
          } else {
            icon.className = 'bi bi-star text-muted';
            this.title = 'Mark as featured';
          }
        });
      });
    });

    // ── Manager-only: Delete ───────────────────────────────
    document.querySelectorAll('.btn-delete').forEach(btn => {
      btn.addEventListener('click', function () {
        if (!confirm('Delete "' + this.dataset.name + '"?\nProduct will be soft-deleted and can be restored.')) return;
        post(this.dataset.action, () => {
          AAM.toast('Product deleted', 'success');
          this.closest('tr').style.opacity = '0';
          setTimeout(() => location.reload(), 900);
        });
      });
    });
  }

  // ── Barcode Search ──────────────────────────────────────
  var barcodeInput  = document.getElementById('barcodeSearchInput');
  var barcodeResult = document.getElementById('barcodeResult');

  function doBarcodeLookup() {
    var code = barcodeInput.value.trim();
    if (!code) return;
    barcodeResult.innerHTML = '<span class="spinner-border spinner-border-sm text-info me-2"></span>';
    fetch(barcodeSearchUrl + '?barcode=' + encodeURIComponent(code), {
      credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(function (resp) {
      if (resp.success) {
        var p = resp.product;
        // Show product name, code, unit, category — NO price for anyone here
        // (price badge omitted; managers can click View to see full details)
        barcodeResult.innerHTML =
          '<div class="d-flex align-items-center gap-2 flex-wrap">' +
          '<i class="bi bi-check-circle-fill text-success"></i>' +
          '<strong>' + p.product_name + '</strong>' +
          '<span class="text-muted small font-monospace">' + p.product_code + '</span>' +
          '<a href="' + listUrl.replace(/\/products.*/, '/products/') + p.product_id +
             '" class="btn btn-xs btn-outline-primary btn-sm py-0">View</a>' +
          '</div>';
      } else {
        barcodeResult.innerHTML =
          '<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>Not found: ' + code + '</span>';
      }
    })
    .catch(() => {
      barcodeResult.innerHTML = '<span class="text-danger">Lookup failed</span>';
    });
  }

  document.getElementById('barcodeSearchBtn')?.addEventListener('click', doBarcodeLookup);
  barcodeInput?.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); doBarcodeLookup(); }
  });

  // ── Auto-submit filters on select change ────────────────
  ['categoryFilter', 'statusFilter'].forEach(function (id) {
    document.getElementById(id)?.addEventListener('change', function () {
      document.getElementById('filterForm').submit();
    });
  });
})();
</script>
<?php $view->endSection(); ?>
