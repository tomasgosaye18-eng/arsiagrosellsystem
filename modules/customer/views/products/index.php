<?php
/**
 * =========================================================
 * Customer Product Catalog — index.php
 * =========================================================
 * Variables from ProductController::index():
 * @var array       $products          Current page product rows
 * @var array       $pagination        {data, total, page, perPage, totalPages, from, to}
 * @var array|null  $shop              Selected shop row (or null)
 * @var int         $shopId            Selected shop ID (0 = none)
 * @var array       $shops             All active shops
 * @var array       $categories        Categories with product_count
 * @var array|null  $selectedCategory
 * @var int         $categoryId
 * @var string      $sort
 * @var string      $searchQuery
 * @var string|null $errorMessage
 * @var \AAM\Core\View $view
 */
declare(strict_types=1);

$products    = $products    ?? [];
$pagination  = $pagination  ?? ['total' => 0, 'page' => 1, 'totalPages' => 0, 'from' => 0, 'to' => 0];
$shop        = $shop        ?? null;
$shopId      = $shopId      ?? 0;
$shops       = $shops       ?? [];
$categories  = $categories  ?? [];
$categoryId  = $categoryId  ?? 0;
$sort        = $sort        ?? '';
$searchQuery = $searchQuery ?? '';
$errorMessage = $errorMessage ?? null;

$shopName = $shop ? ($shop['shop_name'] ?? $shop['name'] ?? '') : '';

// Category icons helper
function _pCatIcon(string $name): string {
    $icons = ['dairy'=>'🥛','milk'=>'🥛','grain'=>'🌾','teff'=>'🌾','wheat'=>'🌾',
               'barley'=>'🌾','egg'=>'🥚','honey'=>'🍯','meat'=>'🥩','vegetable'=>'🥦',
               'fruit'=>'🍎','herb'=>'🌿','legume'=>'🫘','pulse'=>'🫘','oil'=>'🫙'];
    $lc = strtolower($name);
    foreach ($icons as $k => $ico) { if (str_contains($lc, $k)) return $ico; }
    return '📦';
}

// Compute price range from current products for slider
$allPrices = array_column($products, 'selling_price');
$minProductPrice = $allPrices ? (float)min($allPrices) : 0;
$maxProductPrice = $allPrices ? (float)max($allPrices) : 500;
$priceMin = isset($_GET['price_min']) ? (float)$_GET['price_min'] : 0;
$priceMax = isset($_GET['price_max']) ? (float)$_GET['price_max'] : max(500, $maxProductPrice);

// Availability counts from current page
$inStockCount  = 0;
$lowStockCount = 0;
$outStockCount = 0;
foreach ($products as $p) {
    $s = $p['availability']['status'] ?? 'unavailable';
    if ($s === 'available') $inStockCount++;
    elseif ($s === 'low') $lowStockCount++;
    else $outStockCount++;
}
$totalCount = (int)$pagination['total'];

$sortBaseUrl = $view->url('customer/products?'
    . ($shopId ? 'shop_id=' . $shopId . '&' : '')
    . ($categoryId ? 'category_id=' . $categoryId . '&' : '')
    . ($searchQuery ? 'q=' . urlencode($searchQuery) . '&' : '')
    . 'sort=');
?>

<!-- ═══════════════════════════════════════════════════════
     BREADCRUMB + PAGE HEADER
     ═══════════════════════════════════════════════════════ -->
<div class="cpl-page-header">
    <div class="c-container">

        <!-- Back navigation: only when a category, search, or availability filter is active -->
        <?php if ($categoryId || $searchQuery || ($availability ?? '')): ?>
        <a href="<?= $view->url('customer/products' . ($shopId ? '?shop_id=' . $shopId : '')) ?>"
           class="c-back-nav"
           style="margin-bottom:var(--c-space-2);"
           aria-label="Back to all Products">
            <i class="bi bi-arrow-left c-back-nav__icon" aria-hidden="true"></i>
            <span class="c-back-nav__label">Back to Products</span>
        </a>
        <?php endif; ?>

        <!-- Breadcrumb -->
        <nav class="cpl-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= $view->url('customer') ?>" class="cpl-breadcrumb__link">
                <i class="bi bi-house-fill" aria-hidden="true"></i>
                Home
            </a>
            <span class="cpl-breadcrumb__sep" aria-hidden="true">›</span>
            <span class="cpl-breadcrumb__current" aria-current="page">Products</span>
            <?php if ($selectedCategory ?? null): ?>
            <span class="cpl-breadcrumb__sep" aria-hidden="true">›</span>
            <span class="cpl-breadcrumb__current"><?= e(($selectedCategory)['name'] ?? '') ?></span>
            <?php endif; ?>
        </nav>

        <!-- Title row -->
        <div class="cpl-title-row">
            <div class="cpl-title-block">
                <h1 class="cpl-page-title">All Products</h1>
                <p class="cpl-page-desc">
                    Discover fresh and quality agricultural products<?= $shopName ? ' from ' . e($shopName) . '.' : ' from Arsi University farms.' ?>
                </p>
            </div>

            <!-- Count + Sort + View toggle -->
            <div class="cpl-header-controls" role="toolbar" aria-label="Product display controls">
                <span class="cpl-product-count" aria-live="polite" aria-atomic="true">
                    <?= $totalCount ?> Product<?= $totalCount !== 1 ? 's' : '' ?> found
                </span>

                <div class="cpl-sort-wrap">
                    <label for="cplSortSelect" class="cpl-sort-label">Sort by:</label>
                    <select id="cplSortSelect"
                            class="cpl-sort-select"
                            aria-label="Sort products by"
                            onchange="window.location.href=this.value">
                        <?php
                        $sorts = [
                            ''             => 'Recommended',
                            'name_asc'     => 'Name A–Z',
                            'name_desc'    => 'Name Z–A',
                            'price_asc'    => 'Price: Low → High',
                            'price_desc'   => 'Price: High → Low',
                            'availability' => 'Availability',
                        ];
                        foreach ($sorts as $val => $label): ?>
                        <option value="<?= $sortBaseUrl . e($val) ?>" <?= $sort === $val ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Grid / List toggle -->
                <div class="cpl-view-toggle" role="group" aria-label="View mode">
                    <button type="button"
                            class="cpl-view-btn cpl-view-btn--grid active"
                            id="cplViewGrid"
                            aria-pressed="true"
                            aria-label="Grid view"
                            title="Grid view">
                        <i class="bi bi-grid-fill" aria-hidden="true"></i>
                    </button>
                    <button type="button"
                            class="cpl-view-btn cpl-view-btn--list"
                            id="cplViewList"
                            aria-pressed="false"
                            aria-label="List view"
                            title="List view">
                        <i class="bi bi-list-ul" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     CATALOG LAYOUT
     ═══════════════════════════════════════════════════════ -->
<div class="c-container cpl-layout" id="cplLayout">

    <!-- ── SIDEBAR ──────────────────────────────────────── -->
    <aside class="cpl-sidebar" id="cplSidebar" aria-label="Product filters">

        <!-- Backdrop (mobile) -->
        <div class="cpl-sidebar__backdrop" id="cplSidebarBackdrop" aria-hidden="true"></div>

        <div class="cpl-sidebar__panel" role="dialog" aria-modal="true" aria-label="Filter products">

            <!-- Sidebar header (mobile only) -->
            <div class="cpl-sidebar__header">
                <span class="cpl-sidebar__header-title">
                    <i class="bi bi-funnel-fill" aria-hidden="true"></i>
                    Filters
                </span>
                <button class="cpl-sidebar__close"
                        id="cplSidebarClose"
                        aria-label="Close filters">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>

            <!-- ── CATEGORIES ──────────────────────────────── -->
            <div class="cpl-sb-section">
                <h3 class="cpl-sb-section__title">Categories</h3>
                <div class="cpl-sb-cat-list" role="list">

                    <!-- All Categories -->
                    <?php
                    $catTotal = array_sum(array_column($categories, 'product_count')) ?: $totalCount;
                    $allUrl = $view->url('customer/products'
                        . ($shopId ? '?shop_id=' . $shopId : '')
                        . ($sort && !$shopId ? '?sort=' . e($sort) : ($sort && $shopId ? '&sort=' . e($sort) : '')));
                    ?>
                    <a href="<?= $allUrl ?>"
                       class="cpl-sb-cat-item <?= !$categoryId ? 'cpl-sb-cat-item--active' : '' ?>"
                       role="listitem">
                        <span class="cpl-sb-cat-item__icon cpl-sb-cat-item__icon--all" aria-hidden="true">
                            <i class="bi bi-grid-fill"></i>
                        </span>
                        <span class="cpl-sb-cat-item__name">All Categories</span>
                        <span class="cpl-sb-cat-item__count"><?= $catTotal ?></span>
                    </a>

                    <?php foreach ($categories as $cat):
                        $cId    = (int)$cat['id'];
                        $cName  = $cat['name'] ?? '';
                        $cCount = (int)($cat['product_count'] ?? 0);
                        $cUrl   = $view->url('customer/products?'
                            . ($shopId ? 'shop_id=' . $shopId . '&' : '')
                            . 'category_id=' . $cId
                            . ($sort ? '&sort=' . e($sort) : ''));
                    ?>
                    <a href="<?= $cUrl ?>"
                       class="cpl-sb-cat-item <?= $categoryId === $cId ? 'cpl-sb-cat-item--active' : '' ?>"
                       role="listitem">
                        <span class="cpl-sb-cat-item__icon" aria-hidden="true"><?= _pCatIcon($cName) ?></span>
                        <span class="cpl-sb-cat-item__name"><?= e($cName) ?></span>
                        <?php if ($cCount): ?>
                        <span class="cpl-sb-cat-item__count"><?= $cCount ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>

                </div>
            </div>

            <!-- ── PRICE RANGE ─────────────────────────────── -->
            <div class="cpl-sb-section">
                <h3 class="cpl-sb-section__title">Price Range</h3>
                <div class="cpl-price-filter">
                    <div class="cpl-price-labels">
                        <span class="cpl-price-label-min" id="cplPriceLabelMin">ETB <?= number_format($priceMin, 0) ?></span>
                        <span class="cpl-price-label-max" id="cplPriceLabelMax">ETB <?= number_format($priceMax, 0) ?>+</span>
                    </div>
                    <div class="cpl-price-slider-wrap">
                        <input type="range"
                               id="cplPriceMin"
                               class="cpl-price-range"
                               min="0"
                               max="<?= (int)max(500, ceil($maxProductPrice / 50) * 50) ?>"
                               value="<?= (int)$priceMin ?>"
                               step="10"
                               aria-label="Minimum price">
                        <input type="range"
                               id="cplPriceMax"
                               class="cpl-price-range"
                               min="0"
                               max="<?= (int)max(500, ceil($maxProductPrice / 50) * 50) ?>"
                               value="<?= (int)$priceMax ?>"
                               step="10"
                               aria-label="Maximum price">
                        <div class="cpl-price-track" id="cplPriceTrack"></div>
                    </div>
                </div>
            </div>

<?php
$availability = $availability ?? '';
$availCounts  = $availCounts  ?? ['in_stock' => 0, 'low_stock' => 0, 'out_of_stock' => 0];

// Build base URL for availability filter links (preserves all other filters)
$availBaseParams = [];
if ($shopId)      $availBaseParams[] = 'shop_id='    . $shopId;
if ($categoryId)  $availBaseParams[] = 'category_id=' . $categoryId;
if ($sort)        $availBaseParams[] = 'sort='        . urlencode($sort);
if ($searchQuery) $availBaseParams[] = 'q='           . urlencode($searchQuery);
$availBase = $view->url('customer/products?' . implode('&', $availBaseParams));
$availSep  = empty($availBaseParams) ? '?' : '&';
?>
            <!-- ── AVAILABILITY ────────────────────────────── -->
            <div class="cpl-sb-section">
                <h3 class="cpl-sb-section__title">Availability</h3>
                <div class="cpl-sb-avail-list">

                    <a href="<?= $availBase . $availSep ?>availability=in_stock"
                       class="cpl-sb-avail-item <?= $availability === 'in_stock' ? 'cpl-sb-avail-item--checked' : '' ?>"
                       aria-pressed="<?= $availability === 'in_stock' ? 'true' : 'false' ?>">
                        <?php if ($availability === 'in_stock'): ?>
                        <span class="cpl-sb-avail-check" aria-hidden="true">
                            <i class="bi bi-check2"></i>
                        </span>
                        <?php else: ?>
                        <span class="cpl-sb-avail-box" aria-hidden="true"></span>
                        <?php endif; ?>
                        <span class="cpl-sb-avail-label">In Stock</span>
                        <span class="cpl-sb-avail-count"><?= $availCounts['in_stock'] ?></span>
                    </a>

                    <a href="<?= $availBase . $availSep ?>availability=low_stock"
                       class="cpl-sb-avail-item <?= $availability === 'low_stock' ? 'cpl-sb-avail-item--checked' : '' ?>"
                       aria-pressed="<?= $availability === 'low_stock' ? 'true' : 'false' ?>">
                        <?php if ($availability === 'low_stock'): ?>
                        <span class="cpl-sb-avail-check" aria-hidden="true">
                            <i class="bi bi-check2"></i>
                        </span>
                        <?php else: ?>
                        <span class="cpl-sb-avail-box" aria-hidden="true"></span>
                        <?php endif; ?>
                        <span class="cpl-sb-avail-label">Low Stock</span>
                        <span class="cpl-sb-avail-count"><?= $availCounts['low_stock'] ?></span>
                    </a>

                    <a href="<?= $availBase . $availSep ?>availability=out_of_stock"
                       class="cpl-sb-avail-item <?= $availability === 'out_of_stock' ? 'cpl-sb-avail-item--checked' : '' ?>"
                       aria-pressed="<?= $availability === 'out_of_stock' ? 'true' : 'false' ?>">
                        <?php if ($availability === 'out_of_stock'): ?>
                        <span class="cpl-sb-avail-check" aria-hidden="true">
                            <i class="bi bi-check2"></i>
                        </span>
                        <?php else: ?>
                        <span class="cpl-sb-avail-box" aria-hidden="true"></span>
                        <?php endif; ?>
                        <span class="cpl-sb-avail-label">Out of Stock</span>
                        <span class="cpl-sb-avail-count"><?= $availCounts['out_of_stock'] ?></span>
                    </a>

                    <?php if ($availability): ?>
                    <a href="<?= $availBase ?>"
                       class="cpl-sb-avail-clear">
                        <i class="bi bi-x-circle" aria-hidden="true"></i>
                        Clear availability filter
                    </a>
                    <?php endif; ?>

                </div>
            </div>

            <!-- ── CLEAR FILTERS ───────────────────────────── -->
            <div class="cpl-sb-section cpl-sb-section--clear">
                <a href="<?= $view->url('customer/products' . ($shopId ? '?shop_id=' . $shopId : '')) ?>"
                   class="cpl-sb-clear-btn <?= (!$categoryId && !$searchQuery && !$availability) ? 'cpl-sb-clear-btn--inactive' : '' ?>">
                    <i class="bi bi-x-circle" aria-hidden="true"></i>
                    Clear Filters
                </a>
            </div>

        </div><!-- /.cpl-sidebar__panel -->
    </aside><!-- /.cpl-sidebar -->

    <!-- ── MAIN AREA ─────────────────────────────────────── -->
    <main class="cpl-main" id="customer-main">

        <!-- Mobile filter button -->
        <button type="button"
                class="cpl-filter-toggle"
                id="cplFilterToggle"
                aria-controls="cplSidebar"
                aria-expanded="false"
                aria-label="Open filters">
            <i class="bi bi-funnel" aria-hidden="true"></i>
            Filters
            <?php if ($categoryId || $shopId || $searchQuery || $availability): ?>
            <span class="cpl-filter-badge"><?= ($categoryId ? 1 : 0) + ($searchQuery ? 1 : 0) + ($availability ? 1 : 0) ?></span>
            <?php endif; ?>
        </button>

        <!-- ── SELECTED SHOP / PICKUP BANNER ─────────────── -->
        <?php if ($shopId && $shopName): ?>
        <div class="cpl-shop-banner">
            <div class="cpl-shop-banner__left">
                <span class="cpl-shop-banner__icon" aria-hidden="true">
                    <i class="bi bi-geo-alt-fill"></i>
                </span>
                <span class="cpl-shop-banner__label">Selected Shop:</span>
                <strong class="cpl-shop-banner__name"><?= e($shopName) ?></strong>
                <a href="<?= $view->url('customer/shops') ?>"
                   class="cpl-shop-banner__change-btn">
                    Change Shop
                </a>
            </div>
            <div class="cpl-shop-banner__right">
                <i class="bi bi-truck" aria-hidden="true"></i>
                <div class="cpl-shop-banner__pickup-info">
                    <strong>Pickup Only</strong>
                    <span>No delivery. Pick up from the shop.</span>
                </div>
            </div>
        </div>
        <?php elseif (!empty($shops)): ?>
        <div class="cpl-shop-banner cpl-shop-banner--prompt">
            <span class="cpl-shop-banner__icon" aria-hidden="true">
                <i class="bi bi-geo-alt"></i>
            </span>
            <span>Select a shop to see accurate stock availability</span>
            <button type="button" class="cpl-shop-banner__change-btn" data-ca-open-shop-select>
                <i class="bi bi-shop" aria-hidden="true"></i>
                Choose a Shop
            </button>
        </div>
        <?php endif; ?>

        <!-- Active filter pills -->
        <?php if ($searchQuery || $categoryId || $availability): ?>
        <div class="cpl-active-filters" aria-label="Active filters">
            <span class="cpl-active-filters__label">Active filters:</span>
            <?php if ($searchQuery): ?>
            <span class="cpl-filter-pill">
                <i class="bi bi-search" aria-hidden="true"></i>
                <?= e($searchQuery) ?>
                <a href="<?= $view->url('customer/products?' . ($shopId ? 'shop_id=' . $shopId . '&' : '') . ($categoryId ? 'category_id=' . $categoryId . '&' : '') . ($availability ? 'availability=' . urlencode($availability) : '')) ?>"
                   class="cpl-filter-pill__remove"
                   aria-label="Remove search filter">&times;</a>
            </span>
            <?php endif; ?>
            <?php if (($selectedCategory ?? null)): ?>
            <span class="cpl-filter-pill">
                <?= e($selectedCategory['name']) ?>
                <a href="<?= $view->url('customer/products?' . ($shopId ? 'shop_id=' . $shopId . '&' : '') . ($sort ? 'sort=' . e($sort) . '&' : '') . ($availability ? 'availability=' . urlencode($availability) : '')) ?>"
                   class="cpl-filter-pill__remove"
                   aria-label="Remove category filter">&times;</a>
            </span>
            <?php endif; ?>
            <?php if ($availability): ?>
            <?php $availLabels = ['in_stock' => 'In Stock', 'low_stock' => 'Low Stock', 'out_of_stock' => 'Out of Stock']; ?>
            <span class="cpl-filter-pill">
                <i class="bi bi-circle-fill" aria-hidden="true" style="font-size:.6em"></i>
                <?= $availLabels[$availability] ?? e($availability) ?>
                <a href="<?= $view->url('customer/products?' . ($shopId ? 'shop_id=' . $shopId . '&' : '') . ($categoryId ? 'category_id=' . $categoryId . '&' : '') . ($sort ? 'sort=' . e($sort) . '&' : '') . ($searchQuery ? 'q=' . urlencode($searchQuery) : '')) ?>"
                   class="cpl-filter-pill__remove"
                   aria-label="Remove availability filter">&times;</a>
            </span>
            <?php endif; ?>
            <a href="<?= $view->url('customer/products' . ($shopId ? '?shop_id=' . $shopId : '')) ?>"
               class="cpl-clear-all-link">Clear all</a>
        </div>
        <?php endif; ?>

        <!-- ══════════════════════════════════════════════
             CONTENT: ERROR / EMPTY / PRODUCTS
             ══════════════════════════════════════════════ -->
        <?php if ($errorMessage): ?>
        <div class="cpl-empty-state">
            <div class="cpl-empty-state__icon"><i class="bi bi-exclamation-circle"></i></div>
            <h2 class="cpl-empty-state__title">Product Not Found</h2>
            <p class="cpl-empty-state__desc"><?= e($errorMessage) ?></p>
            <a href="<?= $view->url('customer/products') ?>" class="c-btn c-btn-primary">
                <i class="bi bi-grid"></i> Browse Products
            </a>
        </div>

        <?php elseif (empty($products)): ?>
        <div class="cpl-empty-state">
            <div class="cpl-empty-state__icon"><i class="bi bi-box-seam"></i></div>
            <?php if ($searchQuery): ?>
                <h2 class="cpl-empty-state__title">No products found for "<?= e($searchQuery) ?>"</h2>
                <p class="cpl-empty-state__desc">
                    Try a different keyword<?= $shopName ? ' or browse all products at ' . e($shopName) : '' ?>.
                </p>
                <div class="cpl-empty-state__actions">
                    <a href="<?= $view->url('customer/products' . ($shopId ? '?shop_id=' . $shopId : '')) ?>"
                       class="c-btn c-btn-secondary">Clear search</a>
                    <?php if ($shopId): ?>
                    <a href="<?= $view->url('customer/shops') ?>" class="c-btn c-btn-primary">Try another shop</a>
                    <?php endif; ?>
                </div>
            <?php elseif (!$shopId): ?>
                <h2 class="cpl-empty-state__title">Select a shop to see products</h2>
                <p class="cpl-empty-state__desc">Choose a shop location to browse available products and stock.</p>
                <a href="<?= $view->url('customer/shops') ?>" class="c-btn c-btn-primary">
                    <i class="bi bi-geo-alt"></i> Find a Shop
                </a>
            <?php else: ?>
                <h2 class="cpl-empty-state__title">No products available at <?= e($shopName) ?></h2>
                <p class="cpl-empty-state__desc">This shop currently has no products in stock.</p>
                <div class="cpl-empty-state__actions">
                    <a href="<?= $view->url('customer/shops') ?>" class="c-btn c-btn-secondary">Explore other shops</a>
                    <?php if ($categoryId): ?>
                    <a href="<?= $view->url('customer/products?shop_id=' . $shopId) ?>"
                       class="c-btn c-btn-primary">View all categories</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php else: ?>

        <!-- ── PRODUCT GRID ──────────────────────────────── -->
        <div class="cpl-product-grid" role="list" aria-label="Products" id="cplProductGrid">
            <?php foreach ($products as $p): ?>
            <?php
                $pid      = (int)$p['id'];
                $pname    = $p['product_name'] ?? '';
                $price    = (float)($p['selling_price'] ?? 0);
                $unit     = $p['unit_abbreviation'] ?? $p['unit_name'] ?? '';
                $cat      = $p['category_name'] ?? '';
                $imgFile  = $p['primary_image'] ?? null;
                $avail    = $p['availability'] ?? ['status' => 'unavailable', 'label' => 'Unknown', 'qty_label' => ''];
                $availQty = (float)($p['available_quantity'] ?? 0);
                $isAvail  = $avail['status'] !== 'unavailable';
                $detailUrl = $view->url('customer/products/' . $pid . ($shopId ? '?shop_id=' . $shopId : ''));

                // Determine badge
                $badgeLabel = '';
                $badgeClass = '';
                if ($avail['status'] === 'low') {
                    $badgeLabel = 'Low Stock'; $badgeClass = 'cpl-badge--low';
                } elseif (!empty($p['is_featured'])) {
                    $badgeLabel = 'Staple'; $badgeClass = 'cpl-badge--staple';
                } elseif (!empty($p['is_perishable'])) {
                    $badgeLabel = 'Natural'; $badgeClass = 'cpl-badge--natural';
                } elseif ($avail['status'] === 'available') {
                    $badgeLabel = 'Fresh'; $badgeClass = 'cpl-badge--fresh';
                }
            ?>
            <article class="cpl-card <?= !$isAvail ? 'cpl-card--unavailable' : '' ?>"
                     role="listitem"
                     aria-label="<?= e($pname) ?>">

                <!-- Image -->
                <a href="<?= $detailUrl ?>"
                   class="cpl-card__img-wrap"
                   tabindex="-1"
                   aria-hidden="true">
                    <?php if ($imgFile): ?>
                    <img src="<?= $view->url(e($imgFile)) ?>"
                         alt="<?= e($pname) ?>"
                         loading="lazy"
                         width="400" height="300">
                    <?php else: ?>
                    <div class="cpl-card__img-placeholder" aria-hidden="true">
                        <span><?= _pCatIcon($cat ?: $pname) ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- Status badge (top-left) -->
                    <?php if ($badgeLabel): ?>
                    <span class="cpl-card-badge <?= $badgeClass ?>"><?= e($badgeLabel) ?></span>
                    <?php endif; ?>

                    <!-- Wishlist (top-right) -->
                    <button type="button"
                            class="cpl-card__wishlist <?= isset($favoritedIds) && in_array($pid, $favoritedIds, true) ? 'cpl-card__wishlist--active' : '' ?>"
                            data-ca-favorite-toggle
                            data-product-id="<?= $pid ?>"
                            data-product-name="<?= e($pname) ?>"
                            aria-label="<?= isset($favoritedIds) && in_array($pid, $favoritedIds, true) ? 'Remove ' . e($pname) . ' from favorites' : 'Save ' . e($pname) . ' to favorites' ?>"
                            aria-pressed="<?= isset($favoritedIds) && in_array($pid, $favoritedIds, true) ? 'true' : 'false' ?>">
                        <i class="bi <?= isset($favoritedIds) && in_array($pid, $favoritedIds, true) ? 'bi-heart-fill' : 'bi-heart' ?>" aria-hidden="true"></i>
                    </button>
                </a>

                <!-- Body -->
                <div class="cpl-card__body">
                    <?php if ($cat): ?>
                    <div class="cpl-card__category"><?= e($cat) ?></div>
                    <?php endif; ?>

                    <h3 class="cpl-card__name">
                        <a href="<?= $detailUrl ?>"><?= e($pname) ?></a>
                    </h3>

                    <!-- Price -->
                    <div class="cpl-card__price-row">
                        <span class="cpl-card__price">ETB <?= number_format($price, 2) ?></span>
                        <?php if ($unit): ?>
                        <span class="cpl-card__price-unit">/ <?= e($unit) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Availability line -->
                    <?php if ($avail['status'] === 'available'): ?>
                    <div class="cpl-card__avail cpl-card__avail--ok">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                        <?= e($avail['label']) ?>
                    </div>
                    <?php elseif ($avail['status'] === 'low'): ?>
                    <div class="cpl-card__avail cpl-card__avail--low">
                        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                        <?= e($avail['label']) ?>
                    </div>
                    <?php else: ?>
                    <div class="cpl-card__avail cpl-card__avail--out">
                        <i class="bi bi-x-circle-fill" aria-hidden="true"></i>
                        Out of stock
                    </div>
                    <?php endif; ?>

                    <!-- Shop label -->
                    <?php if ($shopId && $shopName): ?>
                    <div class="cpl-card__shop">
                        <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                        <?= e($shopName) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Add to Cart -->
                <div class="cpl-card__footer">
                    <button type="button"
                            class="cpl-card__add-btn <?= !$isAvail ? 'cpl-card__add-btn--disabled' : '' ?>"
                            <?= !$isAvail ? 'disabled aria-disabled="true"' : '' ?>
                            data-ca-add-to-cart
                            data-product-id="<?= $pid ?>"
                            data-product-name="<?= e($pname) ?>"
                            data-product-price="<?= e($price) ?>"
                            data-unit="<?= e($unit) ?>"
                            data-max-qty="<?= e($availQty) ?>"
                            data-shop-id="<?= $shopId ?>"
                            data-shop-name="<?= e($shopName) ?>"
                            aria-label="Add <?= e($pname) ?> to cart">
                        <i class="bi bi-bag-plus-fill" aria-hidden="true"></i>
                        <?= $isAvail ? 'Add to Cart' : 'Out of Stock' ?>
                    </button>
                </div>

            </article><!-- /.cpl-card -->
            <?php endforeach; ?>
        </div><!-- /.cpl-product-grid -->

        <!-- ── PAGINATION ────────────────────────────────── -->
        <?php if ($pagination['totalPages'] > 1): ?>
        <nav class="cpl-pagination" aria-label="Product pages">
            <?php
            $baseUrl = $view->url('customer/products?'
                . ($shopId    ? 'shop_id='    . $shopId    . '&' : '')
                . ($categoryId ? 'category_id=' . $categoryId . '&' : '')
                . ($sort      ? 'sort='       . e($sort)   . '&' : '')
                . ($searchQuery ? 'q='        . urlencode($searchQuery) . '&' : '')
                . 'page=');
            $current = (int)$pagination['page'];
            $total   = (int)$pagination['totalPages'];
            ?>

            <!-- Previous -->
            <?php if ($current > 1): ?>
            <a href="<?= $baseUrl . ($current - 1) ?>"
               class="cpl-page-btn cpl-page-btn--nav"
               aria-label="Previous page">
                <i class="bi bi-chevron-left" aria-hidden="true"></i>
            </a>
            <?php else: ?>
            <span class="cpl-page-btn cpl-page-btn--nav cpl-page-btn--disabled" aria-disabled="true">
                <i class="bi bi-chevron-left" aria-hidden="true"></i>
            </span>
            <?php endif; ?>

            <!-- Pages -->
            <?php
            $start = max(1, $current - 2);
            $end   = min($total, $current + 2);
            if ($start > 1): ?>
                <a href="<?= $baseUrl . '1' ?>" class="cpl-page-btn">1</a>
                <?php if ($start > 2): ?><span class="cpl-page-ellipsis">…</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start; $i <= $end; $i++): ?>
            <a href="<?= $baseUrl . $i ?>"
               class="cpl-page-btn <?= $i === $current ? 'cpl-page-btn--active' : '' ?>"
               <?= $i === $current ? 'aria-current="page"' : '' ?>>
                <?= $i ?>
            </a>
            <?php endfor; ?>

            <?php if ($end < $total): ?>
                <?php if ($end < $total - 1): ?><span class="cpl-page-ellipsis">…</span><?php endif; ?>
                <a href="<?= $baseUrl . $total ?>" class="cpl-page-btn"><?= $total ?></a>
            <?php endif; ?>

            <!-- Next -->
            <?php if ($current < $total): ?>
            <a href="<?= $baseUrl . ($current + 1) ?>"
               class="cpl-page-btn cpl-page-btn--nav"
               aria-label="Next page">
                <i class="bi bi-chevron-right" aria-hidden="true"></i>
            </a>
            <?php else: ?>
            <span class="cpl-page-btn cpl-page-btn--nav cpl-page-btn--disabled" aria-disabled="true">
                <i class="bi bi-chevron-right" aria-hidden="true"></i>
            </span>
            <?php endif; ?>
        </nav>
        <?php endif; ?>

        <?php endif; /* end products/empty/error */ ?>

    </main><!-- /.cpl-main -->
</div><!-- /.cpl-layout -->

<!-- Shop Change Warning Modal -->
<div id="cplShopChangeModal"
     class="cpl-modal"
     role="dialog"
     aria-modal="true"
     aria-label="Cart conflict"
     style="display:none;">
    <div class="cpl-modal__backdrop"></div>
    <div class="cpl-modal__content">
        <div class="cpl-modal__icon"><i class="bi bi-bag-x-fill"></i></div>
        <h2 class="cpl-modal__title">Cart contains items from another shop</h2>
        <p class="cpl-modal__body" id="cplShopChangeModalBody">
            Some items in your cart are not available at the newly selected shop.
        </p>
        <div class="cpl-modal__actions">
            <button type="button" class="c-btn c-btn-secondary" id="cplModalKeepShop">
                Keep current shop
            </button>
            <button type="button"
                    class="c-btn c-btn-primary"
                    style="background:var(--c-danger);border-color:var(--c-danger);"
                    id="cplModalClearAndChange">
                Clear cart &amp; change shop
            </button>
        </div>
    </div>
</div>

<?php $view->section('scripts'); ?>
<script>
(function () {
    'use strict';

    var CA       = window.CustomerApp || {};
    var appUrl   = CA.appUrl || '';
    var shopId   = <?= (int)$shopId ?>;
    var shopName = <?= json_encode($shopName, JSON_UNESCAPED_UNICODE) ?>;

    /* ── Sidebar toggle (mobile) ──────────────────────── */
    var sidebar   = document.getElementById('cplSidebar');
    var backdrop  = document.getElementById('cplSidebarBackdrop');
    var closeBtn  = document.getElementById('cplSidebarClose');
    var filterBtn = document.getElementById('cplFilterToggle');

    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('open');
        document.body.style.overflow = 'hidden';
        if (filterBtn) filterBtn.setAttribute('aria-expanded', 'true');
    }
    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('open');
        document.body.style.overflow = '';
        if (filterBtn) filterBtn.setAttribute('aria-expanded', 'false');
    }

    if (filterBtn) filterBtn.addEventListener('click', openSidebar);
    if (backdrop)  backdrop.addEventListener('click', closeSidebar);
    if (closeBtn)  closeBtn.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) closeSidebar();
    });

    /* ── Grid / List view toggle ──────────────────────── */
    var gridBtn  = document.getElementById('cplViewGrid');
    var listBtn  = document.getElementById('cplViewList');
    var grid     = document.getElementById('cplProductGrid');

    function setView(mode) {
        if (!grid) return;
        if (mode === 'list') {
            grid.classList.add('cpl-product-grid--list');
            grid.classList.remove('cpl-product-grid--grid');
            if (gridBtn) { gridBtn.classList.remove('active'); gridBtn.setAttribute('aria-pressed', 'false'); }
            if (listBtn) { listBtn.classList.add('active');    listBtn.setAttribute('aria-pressed', 'true');  }
            try { localStorage.setItem('ca_catalog_view', 'list'); } catch(_) {}
        } else {
            grid.classList.remove('cpl-product-grid--list');
            grid.classList.add('cpl-product-grid--grid');
            if (gridBtn) { gridBtn.classList.add('active');     gridBtn.setAttribute('aria-pressed', 'true');  }
            if (listBtn) { listBtn.classList.remove('active');  listBtn.setAttribute('aria-pressed', 'false'); }
            try { localStorage.setItem('ca_catalog_view', 'grid'); } catch(_) {}
        }
    }

    // Restore from localStorage
    try {
        var savedView = localStorage.getItem('ca_catalog_view');
        if (savedView === 'list') setView('list');
    } catch(_) {}

    if (gridBtn) gridBtn.addEventListener('click', function () { setView('grid'); });
    if (listBtn) listBtn.addEventListener('click', function () { setView('list'); });

    /* ── Price range slider ───────────────────────────── */
    var rangeMin   = document.getElementById('cplPriceMin');
    var rangeMax   = document.getElementById('cplPriceMax');
    var labelMin   = document.getElementById('cplPriceLabelMin');
    var labelMax   = document.getElementById('cplPriceLabelMax');
    var track      = document.getElementById('cplPriceTrack');
    var priceTimer = null;

    function updatePriceTrack() {
        if (!rangeMin || !rangeMax || !track) return;
        var min  = parseInt(rangeMin.min, 10) || 0;
        var max  = parseInt(rangeMin.max, 10) || 500;
        var lo   = parseInt(rangeMin.value, 10);
        var hi   = parseInt(rangeMax.value, 10);
        var pct1 = ((lo - min) / (max - min)) * 100;
        var pct2 = ((hi - min) / (max - min)) * 100;
        track.style.left  = pct1 + '%';
        track.style.width = (pct2 - pct1) + '%';
        if (labelMin) labelMin.textContent = 'ETB ' + lo;
        if (labelMax) labelMax.textContent = 'ETB ' + hi + '+';
    }

    function applyPriceFilter() {
        if (!rangeMin || !rangeMax) return;
        var lo = parseInt(rangeMin.value, 10);
        var hi = parseInt(rangeMax.value, 10);
        var url = '<?= $view->url('customer/products?') ?>'
            + '<?= $shopId ? 'shop_id=' . $shopId . '&' : '' ?>'
            + '<?= $categoryId ? 'category_id=' . $categoryId . '&' : '' ?>'
            + '<?= $sort ? 'sort=' . e($sort) . '&' : '' ?>'
            + 'price_min=' + lo + '&price_max=' + hi;
        window.location.href = url;
    }

    if (rangeMin) {
        rangeMin.addEventListener('input', function () {
            var lo = parseInt(rangeMin.value, 10);
            var hi = parseInt(rangeMax.value, 10);
            if (lo > hi - 10) rangeMin.value = hi - 10;
            updatePriceTrack();
            clearTimeout(priceTimer);
            priceTimer = setTimeout(applyPriceFilter, 800);
        });
    }
    if (rangeMax) {
        rangeMax.addEventListener('input', function () {
            var lo  = parseInt(rangeMin.value, 10);
            var hi  = parseInt(rangeMax.value, 10);
            if (hi < lo + 10) rangeMax.value = lo + 10;
            updatePriceTrack();
            clearTimeout(priceTimer);
            priceTimer = setTimeout(applyPriceFilter, 800);
        });
    }
    updatePriceTrack();

    /* ── Add to Cart ──────────────────────────────────── */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-ca-add-to-cart]');
        if (!btn || btn.disabled) return;

        var pid   = btn.dataset.productId;
        var name  = btn.dataset.productName;
        var price = parseFloat(btn.dataset.productPrice) || 0;
        var unit  = btn.dataset.unit || '';
        var max   = parseFloat(btn.dataset.maxQty) || 0;
        var sId   = btn.dataset.shopId || shopId;
        var sName = btn.dataset.shopName || shopName;

        if (!sId) {
            if (CA.Toast) CA.Toast.warning('Select a shop', 'Please choose a shop before adding items.');
            return;
        }

        _addToCart(pid, name, price, unit, max, sId, sName, btn);
    });

    function _addToCart(pid, name, price, unit, max, sId, sName, btn) {
        var prevHtml = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-hourglass-split" aria-hidden="true"></i>';
        btn.disabled  = true;

        var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        fetch(appUrl + '/customer/cart/add', {
            method:  'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'csrf_token=' + encodeURIComponent(csrf)
                + '&product_id=' + encodeURIComponent(pid)
                + '&shop_id='    + encodeURIComponent(sId)
                + '&quantity=1'
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) {
                var data  = res.data || {};
                var count = data.cart_count;
                if (count !== undefined && CA.CartBadge) CA.CartBadge.update(count);
                // Mirror to localStorage
                try {
                    var raw = localStorage.getItem('ca_cart');
                    var cartArr = raw ? JSON.parse(raw) : [];
                    var idx = cartArr.findIndex(function(i) {
                        return String(i.productId) === String(pid) && String(i.shopId || '') === String(sId);
                    });
                    if (data.item) {
                        if (idx >= 0) { cartArr[idx].qty = parseFloat(data.item.quantity) || cartArr[idx].qty; }
                        else { cartArr.push({ productId: String(pid), productName: name, price: price, unit: unit, qty: 1, maxQty: max, shopId: String(sId), shopName: sName }); }
                        localStorage.setItem('ca_cart', JSON.stringify(cartArr));
                    }
                } catch(_) {}
                if (CA.Toast) CA.Toast.success('Added to cart', name + ' added successfully.');
                btn.innerHTML = '<i class="bi bi-check-lg" aria-hidden="true"></i>';
                btn.style.background = 'var(--c-success)';
                setTimeout(function () {
                    btn.innerHTML        = prevHtml;
                    btn.style.background = '';
                    btn.disabled         = false;
                }, 1400);
            } else {
                if (CA.Toast) CA.Toast.warning('Cannot add', res.message || 'Not enough stock.');
                btn.innerHTML = prevHtml;
                btn.disabled  = false;
            }
        })
        .catch(function () {
            if (CA.Toast) CA.Toast.error('Error', 'Could not add item. Please try again.');
            btn.innerHTML = prevHtml;
            btn.disabled  = false;
        });
    }

    /* ── Shop change modal ────────────────────────────── */
    var modal        = document.getElementById('cplShopChangeModal');
    var keepBtn      = document.getElementById('cplModalKeepShop');
    var clearBtn     = document.getElementById('cplModalClearAndChange');
    var pendingUrl   = null;

    function showModal(url, newShopName) {
        if (!modal) { window.location.href = url; return; }
        pendingUrl = url;
        var body = document.getElementById('cplShopChangeModalBody');
        if (body) body.textContent = 'Your cart has items from another shop. '
            + (newShopName ? 'Switching to "' + newShopName + '" may remove some items.' : '');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        if (!modal) return;
        modal.style.display = 'none';
        document.body.style.overflow = '';
        pendingUrl = null;
    }

    if (keepBtn)  keepBtn.addEventListener('click', closeModal);
    if (clearBtn) clearBtn.addEventListener('click', function () {
        if (CA.Cart) CA.Cart.clear();
        closeModal();
        if (pendingUrl) window.location.href = pendingUrl;
    });

    var modalBackdrop = modal ? modal.querySelector('.cpl-modal__backdrop') : null;
    if (modalBackdrop) modalBackdrop.addEventListener('click', closeModal);

    // Sort change → show skeleton (optional UX)
    var sortEl = document.getElementById('cplSortSelect');
    if (sortEl) sortEl.addEventListener('change', function () {
        // Brief visual feedback before navigation
        if (grid) grid.style.opacity = '0.5';
    });

}());
</script>
<?php $view->endSection(); ?>
