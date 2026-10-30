<?php
/**
 * =========================================================
 * Customer Search Results Page — search.php
 * =========================================================
 * @var array       $products
 * @var array       $pagination
 * @var string      $searchQuery
 * @var array|null  $shop
 * @var int         $shopId
 * @var array       $shops
 * @var array       $categories
 * @var \AAM\Core\View $view
 */
declare(strict_types=1);

$products    = $products    ?? [];
$pagination  = $pagination  ?? ['total' => 0, 'page' => 1, 'totalPages' => 0, 'from' => 0, 'to' => 0];
$searchQuery = $searchQuery ?? '';
$shop        = $shop        ?? null;
$shopId      = $shopId      ?? 0;
$shops       = $shops       ?? [];
$categories  = $categories  ?? [];
$shopName    = $shop ? ($shop['shop_name'] ?? $shop['name'] ?? '') : '';

function _srIcon(string $name): string {
    $icons = ['dairy'=>'🥛','milk'=>'🥛','grain'=>'🌾','teff'=>'🌾','wheat'=>'🌾',
               'barley'=>'🌾','egg'=>'🥚','honey'=>'🍯','meat'=>'🥩','vegetable'=>'🥦',
               'fruit'=>'🍎','herb'=>'🌿','legume'=>'🫘','pulse'=>'🫘','oil'=>'🫙'];
    $lc = strtolower($name);
    foreach ($icons as $k => $ico) { if (str_contains($lc, $k)) return $ico; }
    return '📦';
}
?>

<div class="cp-toolbar">
    <div class="c-container">
        <div class="cp-toolbar__inner">
            <a href="<?= $view->url('customer/products' . ($shopId ? '?shop_id=' . $shopId : '')) ?>"
               class="c-back-nav"
               aria-label="Back to Products">
                <i class="bi bi-arrow-left c-back-nav__icon" aria-hidden="true"></i>
                <span class="c-back-nav__label">Back to Products</span>
            </a>
            <nav class="cp-breadcrumb" aria-label="Breadcrumb">
                <a href="<?= $view->url('customer') ?>">Home</a>
                <span class="cp-breadcrumb__sep">/</span>
                <a href="<?= $view->url('customer/products' . ($shopId ? '?shop_id=' . $shopId : '')) ?>">Products</a>
                <span class="cp-breadcrumb__sep">/</span>
                <span aria-current="page">Search</span>
            </nav>
            <?php if ($shopId && $shopName): ?>
            <div class="cp-shop-context">
                <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                <span>Shop: <strong><?= e($shopName) ?></strong></span>
                <a href="<?= $view->url('customer/shops') ?>" class="cp-shop-context__change">Change</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="c-container" id="customer-main" style="padding-top:var(--c-space-8);padding-bottom:var(--c-space-16);">

    <!-- Search header -->
    <div class="cps-header">
        <h1 class="cps-title">
            <?php if ($searchQuery): ?>
                Results for "<strong><?= e($searchQuery) ?></strong>"
            <?php else: ?>
                Search Products
            <?php endif; ?>
        </h1>
        <?php if ($pagination['total'] > 0): ?>
        <p class="cps-count">
            <?= $pagination['from'] ?>–<?= $pagination['to'] ?> of
            <?= $pagination['total'] ?> product<?= $pagination['total'] !== 1 ? 's' : '' ?>
            <?= $shopName ? 'at <strong>' . e($shopName) . '</strong>' : '' ?>
        </p>
        <?php endif; ?>
    </div>

    <!-- Inline search bar (large) -->
    <form method="get" action="<?= $view->url('customer/products/search') ?>" class="cps-search-form" role="search">
        <?php if ($shopId): ?>
        <input type="hidden" name="shop_id" value="<?= $shopId ?>">
        <?php endif; ?>
        <div class="cps-search-input-wrap">
            <i class="bi bi-search cps-search-icon" aria-hidden="true"></i>
            <input type="search"
                   name="q"
                   class="cps-search-input"
                   value="<?= e($searchQuery) ?>"
                   placeholder="Search products, categories…"
                   aria-label="Search products"
                   autocomplete="off"
                   autofocus>
            <?php if ($searchQuery): ?>
            <a href="<?= $view->url('customer/products/search' . ($shopId ? '?shop_id=' . $shopId : '')) ?>"
               class="cps-search-clear" aria-label="Clear search">
                <i class="bi bi-x"></i>
            </a>
            <?php endif; ?>
        </div>
        <button type="submit" class="c-btn c-btn-primary">
            <i class="bi bi-search" aria-hidden="true"></i> Search
        </button>
    </form>

    <!-- Shop filter -->
    <?php if (!empty($shops)): ?>
    <div class="cps-shop-row" role="list" aria-label="Filter by shop">
        <span class="cps-shop-row__label">Shop:</span>
        <a href="<?= $view->url('customer/products/search' . ($searchQuery ? '?q=' . urlencode($searchQuery) : '')) ?>"
           class="customer-shop-pill <?= !$shopId ? 'active' : '' ?>"
           role="listitem">All Shops</a>
        <?php foreach ($shops as $s): ?>
        <a href="<?= $view->url('customer/products/search?shop_id=' . (int)$s['id'] . ($searchQuery ? '&q=' . urlencode($searchQuery) : '')) ?>"
           class="customer-shop-pill <?= $shopId === (int)$s['id'] ? 'active' : '' ?>"
           role="listitem">
            <?= e($s['shop_name'] ?? $s['name'] ?? '') ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Results -->
    <?php if (empty($products) && $searchQuery): ?>
    <div class="cp-empty-state" style="margin-top:var(--c-space-12);">
        <div class="cp-empty-state__icon"><i class="bi bi-search"></i></div>
        <h2 class="cp-empty-state__title">No products found for "<?= e($searchQuery) ?>"</h2>
        <p class="cp-empty-state__desc">
            Try a different keyword<?= $shopName ? ', or browse all products at ' . e($shopName) : '' ?>.
        </p>
        <div class="cp-empty-state__actions">
            <a href="<?= $view->url('customer/products' . ($shopId ? '?shop_id=' . $shopId : '')) ?>"
               class="c-btn c-btn-secondary">Browse all products</a>
            <?php if ($shopId): ?>
            <a href="<?= $view->url('customer/shops') ?>" class="c-btn c-btn-primary">Try another shop</a>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif (empty($products)): ?>
    <div class="cp-empty-state" style="margin-top:var(--c-space-12);">
        <div class="cp-empty-state__icon"><i class="bi bi-box-seam"></i></div>
        <h2 class="cp-empty-state__title">Enter a search term</h2>
        <p class="cp-empty-state__desc">Type a product name, category, or code to search.</p>
        <?php if (!empty($categories)): ?>
        <div style="margin-top:var(--c-space-8);">
            <p style="font-size:var(--c-size-sm);color:var(--c-text-muted);margin-bottom:var(--c-space-4);">Or browse by category:</p>
            <div class="c-grid-categories">
                <?php foreach (array_slice($categories, 0, 6) as $cat): ?>
                <a href="<?= $view->url('customer/products?' . ($shopId ? 'shop_id=' . $shopId . '&' : '') . 'category_id=' . (int)$cat['id']) ?>"
                   class="customer-category-card">
                    <div class="customer-category-card__icon"><?= _srIcon($cat['name'] ?? '') ?></div>
                    <div class="customer-category-card__name"><?= e($cat['name'] ?? '') ?></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <div class="c-grid-products" role="list" aria-label="Search results">
        <?php foreach ($products as $p): ?>
        <?php
            $pid     = (int)$p['id'];
            $pname   = $p['product_name'] ?? '';
            $price   = (float)($p['selling_price'] ?? 0);
            $unit    = $p['unit_abbreviation'] ?? '';
            $cat     = $p['category_name'] ?? '';
            $avail   = $p['availability'] ?? ['status' => 'unavailable', 'label' => 'Unknown'];
            $isAvail = $avail['status'] !== 'unavailable';
            $detailUrl = $view->url('customer/products/' . $pid . ($shopId ? '?shop_id=' . $shopId : ''));
        ?>
        <article class="customer-product-card <?= !$isAvail ? 'customer-product-card--unavailable' : '' ?>"
                 role="listitem" aria-label="<?= e($pname) ?>">
            <a href="<?= $detailUrl ?>" class="customer-product-card__image-wrap" tabindex="-1">
                <?php if (!empty($p['primary_image'])): ?>
                <img src="<?= $view->url(e($p['primary_image'])) ?>"
                     alt="<?= e($pname) ?>" loading="lazy" width="400" height="300">
                <?php else: ?>
                <div class="customer-product-card__image-placeholder">
                    <span class="customer-product-card__placeholder-icon"><?= _srIcon($cat ?: $pname) ?></span>
                </div>
                <?php endif; ?>
                <div class="customer-product-card__badge-row">
                    <?php if ($avail['status'] === 'available'): ?>
                    <span class="c-badge c-badge-available"><i class="bi bi-check-circle-fill"></i> In stock</span>
                    <?php elseif ($avail['status'] === 'low'): ?>
                    <span class="c-badge c-badge-low"><i class="bi bi-exclamation-triangle-fill"></i> <?= e($avail['label']) ?></span>
                    <?php else: ?>
                    <span class="c-badge c-badge-unavailable">Out of stock</span>
                    <?php endif; ?>
                </div>
            </a>
            <div class="customer-product-card__body">
                <?php if ($cat): ?><div class="customer-product-card__category"><?= e($cat) ?></div><?php endif; ?>
                <h3 class="customer-product-card__name"><a href="<?= $detailUrl ?>"><?= e($pname) ?></a></h3>
            </div>
            <div class="customer-product-card__footer">
                <div class="customer-product-card__price-block">
                    <span class="customer-product-card__price">ETB <?= number_format($price, 2) ?></span>
                    <?php if ($unit): ?>
                    <span class="customer-product-card__price-unit">/ <?= e($unit) ?></span>
                    <?php endif; ?>
                </div>
                <button type="button"
                        class="customer-product-card__add-btn <?= !$isAvail ? 'disabled' : '' ?>"
                        <?= !$isAvail ? 'disabled aria-disabled="true"' : '' ?>
                        data-ca-add-to-cart
                        data-product-id="<?= $pid ?>"
                        data-product-name="<?= e($pname) ?>"
                        data-product-price="<?= e($price) ?>"
                        data-unit="<?= e($unit) ?>"
                        data-max-qty="<?= e((float)($p['available_quantity'] ?? 0)) ?>"
                        data-shop-id="<?= $shopId ?>"
                        data-shop-name="<?= e($shopName) ?>"
                        aria-label="Add <?= e($pname) ?> to cart">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i>
                </button>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($pagination['totalPages'] > 1): ?>
    <nav class="cp-pagination" aria-label="Search result pages" style="margin-top:var(--c-space-10);">
        <?php
        $baseUrl = $view->url('customer/products/search?'
            . ($shopId ? 'shop_id=' . $shopId . '&' : '')
            . ($searchQuery ? 'q=' . urlencode($searchQuery) . '&' : '')
            . 'page=');
        $cur = (int)$pagination['page'];
        $tot = (int)$pagination['totalPages'];
        if ($cur > 1): ?>
        <a href="<?= $baseUrl . ($cur - 1) ?>" class="cp-page-btn" aria-label="Previous">
            <i class="bi bi-chevron-left"></i>
        </a>
        <?php endif;
        for ($i = max(1, $cur - 2); $i <= min($tot, $cur + 2); $i++): ?>
        <a href="<?= $baseUrl . $i ?>"
           class="cp-page-btn <?= $i === $cur ? 'active' : '' ?>"
           <?= $i === $cur ? 'aria-current="page"' : '' ?>>
            <?= $i ?>
        </a>
        <?php endfor;
        if ($cur < $tot): ?>
        <a href="<?= $baseUrl . ($cur + 1) ?>" class="cp-page-btn" aria-label="Next">
            <i class="bi bi-chevron-right"></i>
        </a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>

</div>

<?php $view->section('scripts'); ?>
<script>
(function () {
    'use strict';
    var CA     = window.CustomerApp || {};
    var shopId = <?= (int)$shopId ?>;
    var shopNm = <?= json_encode($shopName) ?>;

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-ca-add-to-cart]');
        if (!btn || btn.disabled) return;
        if (!shopId && !btn.dataset.shopId) {
            if (CA.Toast) CA.Toast.warning('Select a shop', 'Choose a shop before adding to cart.');
            return;
        }
        var d = btn.dataset;
        if (CA.Cart) {
            CA.Cart.addItem(
                String(d.productId), d.productName,
                parseFloat(d.productPrice)||0, d.unit||'',
                1, parseFloat(d.maxQty)||0,
                d.shopId || String(shopId),
                d.shopName || shopNm
            );
        }
        if (CA.Toast) CA.Toast.success('Added to cart', d.productName + ' added.');
    });
}());
</script>
<?php $view->endSection(); ?>
