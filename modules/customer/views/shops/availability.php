<?php
/**
 * =========================================================
 * Shop Availability Page — availability.php
 * =========================================================
 * Two modes:
 *   viewMode = 'all_shops'   → shop discovery page (/customer/shops)
 *   viewMode = 'single_shop' → specific shop page  (/customer/shops/{id}/availability)
 *
 * @var string      $viewMode    'all_shops' | 'single_shop'
 * @var array       $shops       All active shops (all_shops mode)
 * @var array|null  $shop        Single shop (single_shop mode)
 * @var int         $shopId
 * @var array       $products    First page of products (single_shop mode)
 * @var array       $pagination
 * @var array       $categories  Categories at this shop
 * @var \AAM\Core\View $view
 */
declare(strict_types=1);

$viewMode   = $viewMode   ?? 'all_shops';
$shops      = $shops      ?? [];
$shop       = $shop       ?? null;
$shopId     = $shopId     ?? 0;
$products   = $products   ?? [];
$pagination = $pagination ?? ['total' => 0, 'totalPages' => 0];
$categories = $categories ?? [];

$shopName   = $shop ? ($shop['shop_name'] ?? $shop['name'] ?? '') : '';

function _saIcon(string $name): string {
    $icons = ['dairy'=>'🥛','milk'=>'🥛','grain'=>'🌾','teff'=>'🌾','wheat'=>'🌾',
               'barley'=>'🌾','egg'=>'🥚','honey'=>'🍯','meat'=>'🥩','vegetable'=>'🥦',
               'fruit'=>'🍎','herb'=>'🌿','legume'=>'🫘','pulse'=>'🫘','oil'=>'🫙'];
    $lc = strtolower($name);
    foreach ($icons as $k => $ico) { if (str_contains($lc, $k)) return $ico; }
    return '📦';
}
?>

<!-- ═══════════════════════════════════════════════════
     TOOLBAR
     ═══════════════════════════════════════════════════ -->
<div class="cp-toolbar">
    <div class="c-container">
        <div class="cp-toolbar__inner">
            <?php if ($viewMode === 'single_shop'): ?>
            <a href="<?= $view->url('customer/shops') ?>"
               class="c-back-nav"
               aria-label="Back to Shops">
                <i class="bi bi-arrow-left c-back-nav__icon" aria-hidden="true"></i>
                <span class="c-back-nav__label">Back to Shops</span>
            </a>
            <?php else: ?>
            <a href="<?= $view->url('customer') ?>"
               class="c-back-nav"
               aria-label="Back to Home">
                <i class="bi bi-arrow-left c-back-nav__icon" aria-hidden="true"></i>
                <span class="c-back-nav__label">Back to Home</span>
            </a>
            <?php endif; ?>
            <nav class="cp-breadcrumb" aria-label="Breadcrumb">
                <a href="<?= $view->url('customer') ?>">Home</a>
                <span class="cp-breadcrumb__sep">/</span>
                <?php if ($viewMode === 'single_shop'): ?>
                <a href="<?= $view->url('customer/shops') ?>">Shops</a>
                <span class="cp-breadcrumb__sep">/</span>
                <span aria-current="page"><?= e($shopName) ?></span>
                <?php else: ?>
                <span aria-current="page">Find a Shop</span>
                <?php endif; ?>
            </nav>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════
     ALL SHOPS MODE — Discovery Page
     ═══════════════════════════════════════════════════ -->
<?php if ($viewMode === 'all_shops'): ?>

<div class="c-container" id="customer-main" style="padding-top:var(--c-space-8);padding-bottom:var(--c-space-16);">

    <div class="c-section-header c-section-header--center" style="margin-bottom:var(--c-space-10);">
        <p class="c-section-label" aria-hidden="true">Multiple Locations</p>
        <h1 class="c-section-title">Find Products Near You</h1>
        <p class="c-section-subtitle">
            Browse available products at each shop. Stock levels reflect real-time inventory.
        </p>
    </div>

    <?php if (empty($shops)): ?>
    <div class="cp-empty-state">
        <div class="cp-empty-state__icon"><i class="bi bi-shop"></i></div>
        <h2 class="cp-empty-state__title">No shops available</h2>
        <p class="cp-empty-state__desc">Shops will appear here once configured.</p>
    </div>

    <?php else: ?>
    <div class="c-grid-shops" role="list" aria-label="Shop locations">
        <?php foreach ($shops as $s): ?>
        <?php
            $sId       = (int)$s['id'];
            $sName     = $s['shop_name'] ?? $s['name'] ?? 'Shop';
            $sLoc      = $s['location'] ?? '';
            $sDesc     = $s['description'] ?? '';
            $sProd     = (int)($s['product_count'] ?? 0);
            $sTopCats  = $s['top_categories'] ?? [];
            $sUrl      = $view->url('customer/shops/' . $sId . '/availability');
            $sImg      = \AAM\Helpers\UploadHelper::shopImageUrl($s['shop_image'] ?? null);
        ?>
        <div class="customer-shop-card" role="listitem"
             data-ca-shop-card="<?= $sId ?>"
             data-ca-shop-card-name="<?= e($sName) ?>">

            <!-- Shop image (visual) -->
            <div class="customer-shop-card__img-wrap" aria-hidden="true">
                <?php if ($sImg): ?>
                <img src="<?= e($sImg) ?>"
                     alt="<?= e($sName) ?>"
                     loading="lazy"
                     class="customer-shop-card__img"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div class="customer-shop-card__img-placeholder" style="display:none;">
                    <i class="bi bi-shop"></i>
                </div>
                <?php else: ?>
                <div class="customer-shop-card__img-placeholder">
                    <i class="bi bi-shop"></i>
                </div>
                <?php endif; ?>
                <div class="csa-status-dot <?= $sProd > 0 ? 'csa-status-dot--active' : 'csa-status-dot--empty' ?>"
                     title="<?= $sProd > 0 ? 'Products in stock' : 'No products currently' ?>"
                     aria-hidden="true"></div>
            </div>

            <div class="customer-shop-card__header">
                <div style="flex:1;min-width:0;">
                    <div class="customer-shop-card__name"><?= e($sName) ?></div>
                    <?php if ($sLoc): ?>
                    <div class="customer-shop-card__location">
                        <i class="bi bi-geo-alt" aria-hidden="true"></i>
                        <?= e($sLoc) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($sDesc): ?>
            <p class="customer-shop-card__desc"><?= e(mb_strimwidth($sDesc, 0, 90, '…')) ?></p>
            <?php endif; ?>

            <div class="customer-shop-card__stats">
                <div class="customer-shop-card__stat">
                    <div class="customer-shop-card__stat-value <?= $sProd === 0 ? 'text-muted' : '' ?>">
                        <?= $sProd ?>
                    </div>
                    <div class="customer-shop-card__stat-label">Products in stock</div>
                </div>
                <?php if (!empty($s['opening_time'])): ?>
                <div class="customer-shop-card__stat">
                    <div class="customer-shop-card__stat-value" style="font-size:var(--c-size-sm);">
                        <?= e(date('g:i A', strtotime($s['opening_time']))) ?>–<?= e(date('g:i A', strtotime($s['closing_time'] ?? '18:00'))) ?>
                    </div>
                    <div class="customer-shop-card__stat-label">Hours</div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Top categories at this shop -->
            <?php if (!empty($sTopCats)): ?>
            <div class="csa-top-cats">
                <?php foreach ($sTopCats as $tc): ?>
                <span class="csa-top-cat">
                    <?= _saIcon($tc['name'] ?? '') ?>
                    <?= e($tc['name'] ?? '') ?>
                    <span class="csa-top-cat__count"><?= (int)$tc['product_count'] ?></span>
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <a href="<?= $sUrl ?>"
               class="c-btn c-btn-primary c-btn-sm csa-view-btn"
               style="align-self:flex-start;margin-top:auto;">
                <i class="bi bi-grid" aria-hidden="true"></i>
                View Products
                <?php if ($sProd > 0): ?>
                <span class="csa-view-btn__count"><?= $sProd ?></span>
                <?php endif; ?>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<?php endif; // all_shops ?>


<!-- ═══════════════════════════════════════════════════
     SINGLE SHOP MODE — Product availability at one shop
     ═══════════════════════════════════════════════════ -->
<?php if ($viewMode === 'single_shop' && $shop): ?>

<!-- Shop header banner -->
<div class="csa-shop-banner">
    <div class="c-container csa-shop-banner__inner">
        <?php $bannerImg = \AAM\Helpers\UploadHelper::shopImageUrl($shop['shop_image'] ?? null); ?>
        <?php if ($bannerImg): ?>
        <div class="csa-shop-banner__img" aria-hidden="true">
            <img src="<?= e($bannerImg) ?>"
                 alt="<?= e($shopName) ?>"
                 loading="eager"
                 onerror="this.parentElement.classList.add('csa-shop-banner__img--placeholder');this.remove();">
        </div>
        <?php else: ?>
        <div class="csa-shop-banner__icon" aria-hidden="true">
            <i class="bi bi-shop"></i>
        </div>
        <?php endif; ?>
        <div class="csa-shop-banner__info">
            <h1 class="csa-shop-banner__name"><?= e($shopName) ?></h1>
            <?php if (!empty($shop['location'])): ?>
            <p class="csa-shop-banner__location">
                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                <?= e($shop['location']) ?>
            </p>
            <?php endif; ?>
            <?php if (!empty($shop['description'])): ?>
            <p class="csa-shop-banner__desc"><?= e($shop['description']) ?></p>
            <?php endif; ?>
        </div>
        <div class="csa-shop-banner__meta">
            <div class="csa-shop-banner__stat">
                <div class="csa-shop-banner__stat-value"><?= (int)($shop['product_count'] ?? 0) ?></div>
                <div class="csa-shop-banner__stat-label">Products available</div>
            </div>
            <?php if (!empty($shop['opening_time'])): ?>
            <div class="csa-shop-banner__stat">
                <div class="csa-shop-banner__stat-value" style="font-size:var(--c-size-sm);">
                    <?= e(date('g:i A', strtotime($shop['opening_time']))) ?>
                    – <?= e(date('g:i A', strtotime($shop['closing_time'] ?? '18:00'))) ?>
                </div>
                <div class="csa-shop-banner__stat-label">Opening hours</div>
            </div>
            <?php endif; ?>
            <a href="<?= $view->url('customer/products?shop_id=' . $shopId) ?>"
               class="c-btn c-btn-primary" style="white-space:nowrap;">
                <i class="bi bi-grid" aria-hidden="true"></i>
                Browse All Products
            </a>
        </div>
    </div>
</div>

<div class="c-container" id="customer-main" style="padding-top:var(--c-space-8);padding-bottom:var(--c-space-16);">

    <!-- Category quick links -->
    <?php if (!empty($categories)): ?>
    <div class="csa-cats-row" aria-label="Categories at this shop">
        <span class="csa-cats-row__label">Browse by category:</span>
        <?php foreach ($categories as $cat): ?>
        <a href="<?= $view->url('customer/products?shop_id=' . $shopId . '&category_id=' . (int)$cat['id']) ?>"
           class="csa-cat-pill">
            <?= _saIcon($cat['name'] ?? '') ?>
            <?= e($cat['name'] ?? '') ?>
            <span class="csa-cat-pill__count"><?= (int)($cat['product_count'] ?? 0) ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Product preview grid -->
    <?php if (empty($products)): ?>
    <div class="cp-empty-state" style="margin-top:var(--c-space-8);">
        <div class="cp-empty-state__icon"><i class="bi bi-box-seam"></i></div>
        <h2 class="cp-empty-state__title">No products currently available</h2>
        <p class="cp-empty-state__desc"><?= e($shopName) ?> has no products in stock at the moment.</p>
        <a href="<?= $view->url('customer/shops') ?>" class="c-btn c-btn-primary">
            <i class="bi bi-geo-alt"></i> Explore other shops
        </a>
    </div>

    <?php else: ?>

    <div class="c-section-header c-section-header--row" style="margin-bottom:var(--c-space-6);">
        <div>
            <p class="c-section-label" aria-hidden="true"><?= e($shopName) ?></p>
            <h2 class="c-section-title" style="font-size:var(--c-size-xl);">
                Available Products
                <span style="font-size:var(--c-size-base);font-weight:var(--c-weight-normal);color:var(--c-text-muted);">
                    (<?= $pagination['total'] ?>)
                </span>
            </h2>
        </div>
        <a href="<?= $view->url('customer/products?shop_id=' . $shopId) ?>"
           class="c-btn c-btn-secondary" style="flex-shrink:0;">
            View all products
            <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </a>
    </div>

    <div class="c-grid-products" role="list" aria-label="Products at <?= e($shopName) ?>">
        <?php foreach ($products as $p): ?>
        <?php
            $pid     = (int)$p['id'];
            $pname   = $p['product_name'] ?? '';
            $price   = (float)($p['selling_price'] ?? 0);
            $unit    = $p['unit_abbreviation'] ?? '';
            $cat     = $p['category_name'] ?? '';
            $avail   = $p['availability'] ?? ['status' => 'unavailable', 'label' => 'Unknown'];
            $isAvail = $avail['status'] !== 'unavailable';
            $detailUrl = $view->url('customer/products/' . $pid . '?shop_id=' . $shopId);
        ?>
        <article class="customer-product-card <?= !$isAvail ? 'customer-product-card--unavailable' : '' ?>"
                 role="listitem" aria-label="<?= e($pname) ?>">

            <a href="<?= $detailUrl ?>" class="customer-product-card__image-wrap" tabindex="-1">
                <?php if (!empty($p['primary_image'])): ?>
                <img src="<?= $view->url(e($p['primary_image'])) ?>"
                     alt="<?= e($pname) ?>" loading="lazy" width="400" height="300">
                <?php else: ?>
                <div class="customer-product-card__image-placeholder">
                    <span class="customer-product-card__placeholder-icon"><?= _saIcon($cat ?: $pname) ?></span>
                </div>
                <?php endif; ?>
                <div class="customer-product-card__badge-row">
                    <?php if ($avail['status'] === 'available'): ?>
                    <span class="c-badge c-badge-available">
                        <i class="bi bi-check-circle-fill"></i> <?= e($avail['label']) ?>
                    </span>
                    <?php elseif ($avail['status'] === 'low'): ?>
                    <span class="c-badge c-badge-low">
                        <i class="bi bi-exclamation-triangle-fill"></i> <?= e($avail['label']) ?>
                    </span>
                    <?php else: ?>
                    <span class="c-badge c-badge-unavailable">Out of stock</span>
                    <?php endif; ?>
                </div>
            </a>

            <div class="customer-product-card__body">
                <?php if ($cat): ?>
                <div class="customer-product-card__category"><?= e($cat) ?></div>
                <?php endif; ?>
                <h3 class="customer-product-card__name">
                    <a href="<?= $detailUrl ?>"><?= e($pname) ?></a>
                </h3>
                <div class="customer-product-card__shop-label">
                    <i class="bi bi-shop" aria-hidden="true"></i>
                    <?= e($shopName) ?>
                </div>
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

    <!-- "See all" CTA if more products exist -->
    <?php if ($pagination['totalPages'] > 1): ?>
    <div style="text-align:center;margin-top:var(--c-space-8);">
        <a href="<?= $view->url('customer/products?shop_id=' . $shopId) ?>" class="c-btn c-btn-primary c-btn-lg">
            <i class="bi bi-grid" aria-hidden="true"></i>
            View all <?= $pagination['total'] ?> products at <?= e($shopName) ?>
        </a>
    </div>
    <?php endif; ?>

    <?php endif; // products ?>

    <!-- Other shops CTA -->
    <?php if (!empty($shops) && count($shops) > 1): ?>
    <div class="csa-other-shops" style="margin-top:var(--c-space-12);">
        <p class="csa-other-shops__label">Other shops:</p>
        <div class="csa-other-shops__list">
            <?php foreach ($shops as $s):
                if ((int)$s['id'] === $shopId) continue;
            ?>
            <a href="<?= $view->url('customer/shops/' . (int)$s['id'] . '/availability') ?>"
               class="customer-shop-pill">
                <i class="bi bi-shop" aria-hidden="true"></i>
                <?= e($s['shop_name'] ?? $s['name'] ?? '') ?>
                <?php if (!empty($s['product_count'])): ?>
                <span class="customer-shop-pill__count">(<?= (int)$s['product_count'] ?>)</span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php endif; // single_shop ?>

<?php $view->section('scripts'); ?>
<script>
(function () {
    'use strict';
    var CA     = window.CustomerApp || {};
    var shopId = <?= (int)$shopId ?>;
    var shopNm = <?= json_encode($shopName) ?>;

    /* ── Add to cart on availability page ───────────────── */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-ca-add-to-cart]');
        if (!btn || btn.disabled) return;
        var d = btn.dataset;
        var sId = d.shopId || String(shopId);
        var sNm = d.shopName || shopNm;

        if (!sId || sId === '0') {
            if (CA.Toast) CA.Toast.warning('Select a shop', 'Please choose a shop first.');
            return;
        }

        // Check cross-shop cart conflict
        if (CA.Cart) {
            var existing = CA.Cart.getAll();
            var hasOther = existing.some(function (i) {
                return i.shopId && i.shopId !== sId;
            });
            if (hasOther) {
                if (!confirm('Your cart has items from a different shop. Add anyway and keep both shops in cart?')) {
                    return;
                }
            }
            CA.Cart.addItem(String(d.productId), d.productName,
                parseFloat(d.productPrice)||0, d.unit||'',
                1, parseFloat(d.maxQty)||0, sId, sNm);
        }
        if (CA.Toast) CA.Toast.success('Added to cart', d.productName + ' added.');
    });

    /* ── Shop card click → navigate to availability page ── */
    document.querySelectorAll('[data-ca-shop-card]').forEach(function (card) {
        card.addEventListener('click', function (e) {
            if (e.target.closest('a') || e.target.closest('button')) return;
            var id = card.dataset.caShopCard;
            if (id) window.location.href = (CA.appUrl || '') + '/customer/shops/' + id + '/availability';
        });
        card.style.cursor = 'pointer';
    });

}());
</script>
<?php $view->endSection(); ?>
