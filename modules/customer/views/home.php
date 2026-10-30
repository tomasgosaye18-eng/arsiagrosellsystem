<?php
/**
 * =========================================================
 * Customer Home Page
 * modules/customer/views/home.php
 * =========================================================
 * Rendered inside templates/customer.php (customer layout).
 * All markup is inside .customer-app (applied by layout).
 *
 * Variables available from controller:
 * @var array   $shops      Active shops from DB (may be empty array)
 * @var array   $categories Active categories (may be empty array)
 * @var array   $products   Featured products (may be empty array)
 * @var \AAM\Core\View $view
 * @var string  $appUrl
 */
declare(strict_types=1);

$shops      = $shops      ?? [];
$categories = $categories ?? [];
$products   = $products   ?? [];
$pageTitle  = $pageTitle  ?? 'AU-AgroSale — Fresh Farm Products';
$selectedShopId   = $selectedShopId   ?? 0;
$selectedShopName = $selectedShopName ?? '';

function _customerCategoryIcon(string $name): string {
    static $icons = null;
    if ($icons === null) {
        $icons = [
            'dairy'        => '🥛',
            'milk'         => '🥛',
            'grain'        => '🌾',
            'teff'         => '🌾',
            'wheat'        => '🌾',
            'barley'       => '🌾',
            'egg'          => '🥚',
            'honey'        => '🍯',
            'meat'         => '🥩',
            'vegetable'    => '🥦',
            'fruit'        => '🍎',
            'herb'         => '🌿',
            'legume'       => '🫘',
            'pulse'        => '🫘',
            'oil'          => '🫙',
            'default'      => '📦',
        ];
    }
    $lc = strtolower($name);
    foreach ($icons as $key => $icon) {
        if ($key !== 'default' && str_contains($lc, $key)) return $icon;
    }
    return $icons['default'];
}

$fallbackCategories = [
    ['id' => 0, 'name' => 'Dairy',      'product_count' => 0],
    ['id' => 0, 'name' => 'Grains',     'product_count' => 0],
    ['id' => 0, 'name' => 'Eggs',       'product_count' => 0],
    ['id' => 0, 'name' => 'Honey',      'product_count' => 0],
    ['id' => 0, 'name' => 'Meat',       'product_count' => 0],
    ['id' => 0, 'name' => 'Vegetables', 'product_count' => 0],
    ['id' => 0, 'name' => 'Fruits',     'product_count' => 0],
    ['id' => 0, 'name' => 'Oils',       'product_count' => 0],
    ['id' => 0, 'name' => 'Other',      'product_count' => 0],
];
$displayCategories = !empty($categories) ? $categories : $fallbackCategories;
$displayShops      = $shops;
$displayProducts   = $products;

$heroBgImage  = $view->url('public/assets/customer/images/placeholders/hero.png');
$heroBgExists = file_exists(PUBLIC_PATH . '/assets/customer/images/placeholders/hero.png');
?>

<?php $view->section('styles'); ?>
<?php $view->endSection(); ?>

<!-- ══════════════════════════════════════════════════════════
     ANNOUNCEMENT BAR
     ══════════════════════════════════════════════════════════ -->
<div class="ch-announce-bar" role="note" aria-label="Site announcements">
    <div class="c-container ch-announce-bar__inner">
        <div class="ch-announce-bar__items">
            <span class="ch-announce-bar__item">
                <i class="bi bi-patch-check-fill" aria-hidden="true"></i>
                Quality products from Arsi University farms
            </span>
            <span class="ch-announce-bar__sep" aria-hidden="true">•</span>
            <span class="ch-announce-bar__item">
                <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                Pickup from your selected shop
            </span>
        </div>
        <div class="ch-announce-bar__right">
            <a href="#" class="ch-announce-bar__link">
                <i class="bi bi-telephone-fill" aria-hidden="true"></i>
                Customer Support
            </a>
            <span class="ch-announce-bar__sep" aria-hidden="true">•</span>
            <a href="#" class="ch-announce-bar__link">
                <i class="bi bi-phone" aria-hidden="true"></i>
                +251 911 234567
            </a>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     HERO SECTION
     ══════════════════════════════════════════════════════════ -->
<section class="ch-hero <?= $heroBgExists ? 'ch-hero--has-image' : '' ?>"
         aria-labelledby="heroHeading">

    <?php if ($heroBgExists): ?>
    <div class="ch-hero__bg" aria-hidden="true">
        <img src="<?= $heroBgImage ?>"
             alt=""
             loading="eager"
             fetchpriority="high"
             decoding="sync"
             width="1440"
             height="580">
    </div>
    <?php else: ?>
    <!-- Decorative farm produce SVG pattern when no image — CSS handles the gradient bg -->
    <div class="ch-hero__bg ch-hero__bg--gradient" aria-hidden="true">
        <div class="ch-hero__bg-art"></div>
    </div>
    <?php endif; ?>

    <div class="c-container ch-hero__inner">

        <!-- LEFT: Text content -->
        <div class="ch-hero__content">

            <span class="ch-hero__badge">
                <i class="bi bi-patch-check-fill" aria-hidden="true"></i>
                Fresh &amp; Healthy
            </span>

            <h1 class="ch-hero__title" id="heroHeading">
                Fresh Products.<br>
                <span class="ch-hero__title-accent">Right From Our Farms.</span>
            </h1>

            <p class="ch-hero__subtitle">
                Discover quality agricultural products from
                Arsi University farms and choose the shop
                closest to you.
            </p>

            <div class="ch-hero__ctas">
                <a href="<?= $view->url('customer/products') ?>"
                   class="ch-hero__btn-primary">
                    <i class="bi bi-grid-fill" aria-hidden="true"></i>
                    Shop Products
                </a>
                <a href="#chSectionShops"
                   class="ch-hero__btn-secondary">
                    <i class="bi bi-geo-alt" aria-hidden="true"></i>
                    Find a Shop
                </a>
            </div>

            <!-- Trust pills at bottom of hero content -->
            <div class="ch-hero__trust">
                <div class="ch-hero__trust-item">
                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                    <span>Quality Assured</span>
                    <small>From our university farms</small>
                </div>
                <div class="ch-hero__trust-item">
                    <i class="bi bi-award" aria-hidden="true"></i>
                    <span>Verified &amp; Trusted</span>
                    <small>Reliable and transparent</small>
                </div>
                <div class="ch-hero__trust-item">
                    <i class="bi bi-bag-check" aria-hidden="true"></i>
                    <span>Easy Pickup</span>
                    <small>From selected shops</small>
                </div>
            </div>
        </div>

        <!-- RIGHT: decorative area — when bg image is present it shows through naturally -->
        <div class="ch-hero__visual" aria-hidden="true">
            <?php if ($heroBgExists): ?>
            <!-- Background image fills the hero; right side is intentionally clear -->
            <?php else: ?>
            <!-- Illustrated fallback when no hero image -->
            <div class="ch-hero__illustration">
                <div class="ch-hero__illustration-inner">
                    <div class="ch-hero__ill-grid">
                        <div class="ch-hero__ill-item">🥛<span>Fresh Milk</span></div>
                        <div class="ch-hero__ill-item">🌾<span>Teff</span></div>
                        <div class="ch-hero__ill-item">🥚<span>Farm Eggs</span></div>
                        <div class="ch-hero__ill-item">🍯<span>Honey</span></div>
                        <div class="ch-hero__ill-item">🥩<span>Meat</span></div>
                        <div class="ch-hero__ill-item">🥦<span>Vegetables</span></div>
                    </div>
                    <div class="ch-hero__ill-badge">
                        <i class="bi bi-patch-check-fill"></i>
                        <div>
                            <b>100%</b>
                            <span>From Our Farms</span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</section>

<!-- ══════════════════════════════════════════════════════════
     SHOP SELECTION STRIP
     ══════════════════════════════════════════════════════════ -->
<?php if (!empty($displayShops)): ?>
<section class="ch-shop-strip" id="chSectionShops" aria-labelledby="shopStripLabel">
    <div class="c-container ch-shop-strip__inner">
        <div class="ch-shop-strip__left">
            <p class="ch-shop-strip__label" id="shopStripLabel">
                Where would you like to shop today?
            </p>
            <p class="ch-shop-strip__sublabel">Products and availability may vary by shop.</p>
        </div>
        <div class="ch-shop-strip__scroll-wrap">
            <div class="ch-shop-strip__cards" role="list" aria-label="Available shops">
                <?php foreach ($displayShops as $idx => $shop):
                    $shopId    = (int)$shop['id'];
                    $shopName  = $shop['shop_name'] ?? $shop['name'] ?? 'Shop';
                    $location  = $shop['location'] ?? '';
                    $prodCount = (int)($shop['product_count'] ?? 0);
                    $isSelected = $selectedShopId && $selectedShopId === $shopId;
                    $stripImgUrl = \AAM\Helpers\UploadHelper::shopImageUrl($shop['shop_image'] ?? null);
                ?>
                <button type="button"
                        class="ch-shop-card <?= $isSelected ? 'ch-shop-card--selected' : '' ?>"
                        data-ca-shop-pill="<?= $shopId ?>"
                        data-ca-shop-pill-name="<?= e($shopName) ?>"
                        role="listitem"
                        aria-pressed="<?= $isSelected ? 'true' : 'false' ?>"
                        aria-label="<?= e($shopName) ?>">
                    <div class="ch-shop-card__img-wrap">
                        <?php if ($stripImgUrl): ?>
                        <img src="<?= e($stripImgUrl) ?>"
                             alt="<?= e($shopName) ?>"
                             loading="lazy"
                             style="width:100%;height:100%;object-fit:cover;"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <div class="ch-shop-card__img-placeholder" aria-hidden="true" style="display:none;">
                            <i class="bi bi-building"></i>
                        </div>
                        <?php else: ?>
                        <div class="ch-shop-card__img-placeholder" aria-hidden="true">
                            <i class="bi bi-building"></i>
                        </div>
                        <?php endif; ?>
                        <?php if ($isSelected): ?>
                        <div class="ch-shop-card__check" aria-hidden="true">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="ch-shop-card__body">
                        <div class="ch-shop-card__name"><?= e($shopName) ?></div>
                        <?php if ($location): ?>
                        <div class="ch-shop-card__location">
                            <i class="bi bi-geo-alt" aria-hidden="true"></i>
                            <?= e($location) ?>
                        </div>
                        <?php endif; ?>
                        <div class="ch-shop-card__count">
                            <?= $prodCount > 0 ? $prodCount . ' products available' : 'View products' ?>
                        </div>
                    </div>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <a href="<?= $view->url('customer/shops') ?>" class="ch-shop-strip__all" aria-label="View all shops">
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
        </a>
    </div>
</section>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════
     SHOP BY CATEGORY
     ══════════════════════════════════════════════════════════ -->
<section class="c-section c-section-surface" aria-labelledby="chCatHeading">
    <div class="c-container">
        <div class="ch-section-header">
            <h2 class="ch-section-title" id="chCatHeading">Shop by Category</h2>
            <a href="<?= $view->url('customer/products') ?>"
               class="ch-section-link" aria-label="View all categories">
                View all categories
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>
        </div>

        <div class="ch-cat-grid" role="list" aria-label="Product categories">
            <?php foreach (array_slice($displayCategories, 0, 9) as $cat):
                $catId   = (int)($cat['id'] ?? 0);
                $catName = $cat['name'] ?? 'Category';
                $catIcon = _customerCategoryIcon($catName);
                $catUrl  = $catId > 0
                    ? $view->url('customer/products?category_id=' . $catId)
                    : $view->url('customer/products');
                $isLast  = $catName === 'Other';
            ?>
            <a href="<?= $catUrl ?>"
               class="ch-cat-card <?= $isLast ? 'ch-cat-card--other' : '' ?>"
               role="listitem"
               aria-label="Browse <?= e($catName) ?>">
                <?php if ($isLast): ?>
                <div class="ch-cat-card__icon ch-cat-card__icon--grid" aria-hidden="true">
                    <i class="bi bi-grid-3x3-gap-fill"></i>
                </div>
                <?php else: ?>
                <div class="ch-cat-card__icon" aria-hidden="true"><?= $catIcon ?></div>
                <?php endif; ?>
                <div class="ch-cat-card__name"><?= e($catName) ?></div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════════════════
     FEATURED PRODUCTS
     ══════════════════════════════════════════════════════════ -->
<section class="c-section c-section-bg-alt" aria-labelledby="chProductsHeading">
    <div class="c-container">
        <div class="ch-section-header">
            <h2 class="ch-section-title" id="chProductsHeading">Featured Products</h2>
            <a href="<?= $view->url('customer/products') ?>"
               class="ch-section-link" aria-label="View all products">
                View all products
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>
        </div>

        <?php if (!empty($displayProducts)): ?>
        <div class="ch-products-grid" role="list" aria-label="Featured products">
            <?php foreach (array_slice($displayProducts, 0, 6) as $product):
                $pid      = (int)($product['id'] ?? $product['product_id'] ?? 0);
                $pname    = $product['product_name'] ?? $product['name'] ?? 'Product';
                $price    = (float)($product['selling_price'] ?? $product['unit_price'] ?? 0);
                $unit     = $product['unit_abbreviation'] ?? $product['unit_abbr'] ?? '';
                $cat      = $product['category_name'] ?? '';
                $imgFile  = $product['primary_image'] ?? null;
                $shopName = $product['shop_name'] ?? null;
                $avail    = isset($product['available_quantity'])
                    ? (int)$product['available_quantity']
                    : null;
                $expiryDate = $product['expiry_date'] ?? null;
                $isExpired  = ($expiryDate && strtotime($expiryDate) < time());
                $outOfStock = ($isExpired || ($avail !== null && $avail <= 0));
            ?>
            <article class="ch-product-card <?= $outOfStock ? 'ch-product-card--unavailable' : '' ?>"
                     role="listitem"
                     aria-label="<?= e($pname) ?>">

                <!-- Image -->
                <div class="ch-product-card__img-wrap">
                    <a href="<?= $view->url('customer/products/' . $pid) ?>"
                       tabindex="-1"
                       aria-hidden="true">
                        <?php if ($imgFile): ?>
                        <img src="<?= $view->url(e($imgFile)) ?>"
                             alt="<?= e($pname) ?>"
                             loading="lazy">
                        <?php else: ?>
                        <div class="ch-product-card__img-placeholder">
                            <?= _customerCategoryIcon($cat ?: $pname) ?>
                        </div>
                        <?php endif; ?>
                    </a>
                    <!-- Wishlist button -->
                    <button type="button"
                            class="ch-product-card__wishlist <?= isset($favoritedIds) && in_array($pid, $favoritedIds, true) ? 'ch-product-card__wishlist--active' : '' ?>"
                            data-ca-favorite-toggle
                            data-product-id="<?= $pid ?>"
                            data-product-name="<?= e($pname) ?>"
                            aria-label="<?= isset($favoritedIds) && in_array($pid, $favoritedIds, true) ? 'Remove ' . e($pname) . ' from favorites' : 'Save ' . e($pname) . ' to favorites' ?>"
                            aria-pressed="<?= isset($favoritedIds) && in_array($pid, $favoritedIds, true) ? 'true' : 'false' ?>">
                        <i class="bi <?= isset($favoritedIds) && in_array($pid, $favoritedIds, true) ? 'bi-heart-fill' : 'bi-heart' ?>" aria-hidden="true"></i>
                    </button>                </div>

                <!-- Body -->
                <div class="ch-product-card__body">
                    <?php if ($cat): ?>
                    <div class="ch-product-card__category"><?= e($cat) ?></div>
                    <?php endif; ?>

                    <h3 class="ch-product-card__name">
                        <a href="<?= $view->url('customer/products/' . $pid) ?>">
                            <?= e($pname) ?>
                        </a>
                    </h3>

                    <div class="ch-product-card__price-row">
                        <span class="ch-product-card__price">ETB <?= number_format($price, 2) ?></span>
                        <?php if ($unit): ?>
                        <span class="ch-product-card__unit">/ <?= e($unit) ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($avail !== null && $avail > 0): ?>
                    <div class="ch-product-card__avail">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                        <?= $avail ?> <?= $unit ? e($unit) : 'units' ?> available
                    </div>
                    <?php endif; ?>

                    <?php if ($shopName): ?>
                    <div class="ch-product-card__shop">
                        <i class="bi bi-shop" aria-hidden="true"></i>
                        <?= e($shopName) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Footer -->
                <div class="ch-product-card__footer">
                    <button type="button"
                            class="ch-product-card__add-btn"
                            data-ca-add-to-cart
                            data-product-id="<?= $pid ?>"
                            data-product-name="<?= e($pname) ?>"
                            data-product-price="<?= e($price) ?>"
                            data-unit="<?= e($unit) ?>"
                            data-max-qty="<?= $avail ?? 0 ?>"
                            aria-label="Add <?= e($pname) ?> to cart"
                            <?= $outOfStock ? 'disabled aria-disabled="true"' : '' ?>>
                        <i class="bi bi-cart-plus" aria-hidden="true"></i>
                        <?= $outOfStock ? 'Out of Stock' : 'Add to Cart' ?>
                    </button>
                </div>

            </article>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
        <!-- Empty state -->
        <div class="customer-empty-state">
            <div class="customer-empty-state__icon" aria-hidden="true">
                <i class="bi bi-box-seam"></i>
            </div>
            <h3 class="customer-empty-state__title">Products Coming Soon</h3>
            <p class="customer-empty-state__desc">
                Our farm products are being added to the catalog. Check back soon.
            </p>
            <a href="<?= $view->url('customer/products') ?>" class="c-btn c-btn-primary">
                <i class="bi bi-grid" aria-hidden="true"></i>
                Browse Products
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ══════════════════════════════════════════════════════════
     FIND PRODUCTS NEAR YOU (Shop Cards)
     ══════════════════════════════════════════════════════════ -->
<?php if (!empty($displayShops)): ?>
<section class="c-section c-section-surface" aria-labelledby="chShopsHeading">
    <div class="c-container">
        <div class="ch-section-header">
            <h2 class="ch-section-title" id="chShopsHeading">Find Products Near You</h2>
            <a href="<?= $view->url('customer/shops') ?>"
               class="ch-section-link" aria-label="View all shops">
                View all shops
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>
        </div>

        <div class="ch-shops-grid" role="list" aria-label="Shop locations">
            <?php foreach ($displayShops as $shop):
                $shopId    = (int)$shop['id'];
                $shopName  = $shop['shop_name'] ?? $shop['name'] ?? 'Shop';
                $location  = $shop['location'] ?? '';
                $prodCount = (int)($shop['product_count'] ?? 0);
                $shopUrl   = $view->url('customer/shops/' . $shopId . '/availability');
                $shopImg   = \AAM\Helpers\UploadHelper::shopImageUrl($shop['shop_image'] ?? null);
            ?>
            <div class="ch-shop-loc-card"
                 role="listitem"
                 data-ca-shop-card="<?= $shopId ?>"
                 data-ca-shop-card-name="<?= e($shopName) ?>"
                 tabindex="0"
                 aria-label="<?= e($shopName) ?>">

                <!-- Shop image -->
                <div class="ch-shop-loc-card__img" aria-hidden="true">
                    <?php if ($shopImg): ?>
                    <img src="<?= e($shopImg) ?>"
                         alt="<?= e($shopName) ?>"
                         loading="lazy"
                         style="width:100%;height:100%;object-fit:cover;"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                    <div class="ch-shop-loc-card__img-placeholder" style="display:none;">
                        <i class="bi bi-building"></i>
                    </div>
                    <?php else: ?>
                    <div class="ch-shop-loc-card__img-placeholder">
                        <i class="bi bi-building"></i>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Body -->
                <div class="ch-shop-loc-card__body">
                    <div class="ch-shop-loc-card__name"><?= e($shopName) ?></div>
                    <?php if ($location): ?>
                    <div class="ch-shop-loc-card__location">
                        <i class="bi bi-geo-alt" aria-hidden="true"></i>
                        <?= e($location) ?>
                    </div>
                    <?php endif; ?>
                    <div class="ch-shop-loc-card__count">
                        <?= $prodCount > 0 ? $prodCount . ' products available' : 'View products' ?>
                    </div>
                    <a href="<?= $shopUrl ?>"
                       class="ch-shop-loc-card__btn"
                       onclick="event.stopPropagation();">
                        View Products
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════
     HOW IT WORKS + WHY CHOOSE US
     ══════════════════════════════════════════════════════════ -->
<section class="c-section c-section-bg-alt" aria-labelledby="chHowHeading">
    <div class="c-container">
        <div class="ch-how-why-grid">

            <!-- How It Works -->
            <div class="ch-how-it-works">
                <h2 class="ch-section-title ch-how-it-works__title" id="chHowHeading">How It Works</h2>

                <div class="ch-steps" role="list">
                    <div class="ch-step" role="listitem">
                        <div class="ch-step__num" aria-hidden="true">
                            <i class="bi bi-grid-fill"></i>
                        </div>
                        <div class="ch-step__content">
                            <h3 class="ch-step__title">1. Browse</h3>
                            <p class="ch-step__desc">Explore products from our farms</p>
                        </div>
                    </div>
                    <div class="ch-step" role="listitem">
                        <div class="ch-step__num" aria-hidden="true">
                            <i class="bi bi-geo-alt-fill"></i>
                        </div>
                        <div class="ch-step__content">
                            <h3 class="ch-step__title">2. Choose Shop</h3>
                            <p class="ch-step__desc">Select the shop nearest to you</p>
                        </div>
                    </div>
                    <div class="ch-step" role="listitem">
                        <div class="ch-step__num" aria-hidden="true">
                            <i class="bi bi-credit-card-2-front-fill"></i>
                        </div>
                        <div class="ch-step__content">
                            <h3 class="ch-step__title">3. Order &amp; Pay</h3>
                            <p class="ch-step__desc">Place your order and pay securely</p>
                        </div>
                    </div>
                    <div class="ch-step" role="listitem">
                        <div class="ch-step__num" aria-hidden="true">
                            <i class="bi bi-bag-check-fill"></i>
                        </div>
                        <div class="ch-step__content">
                            <h3 class="ch-step__title">4. Pick Up</h3>
                            <p class="ch-step__desc">Pick up your order from the selected shop</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Why Choose AU-AgroSale -->
            <div class="ch-why-choose" aria-labelledby="chWhyHeading">
                <div class="ch-why-choose__card">
                    <h2 class="ch-section-title" id="chWhyHeading" style="margin-bottom: var(--c-space-5);">
                        Why Choose AU-AgroSale?
                    </h2>
                    <ul class="ch-why-list" role="list">
                        <li class="ch-why-item">
                            <i class="bi bi-check-circle-fill ch-why-item__icon" aria-hidden="true"></i>
                            Quality products from Arsi University farms
                        </li>
                        <li class="ch-why-item">
                            <i class="bi bi-check-circle-fill ch-why-item__icon" aria-hidden="true"></i>
                            Verified and trusted sources
                        </li>
                        <li class="ch-why-item">
                            <i class="bi bi-check-circle-fill ch-why-item__icon" aria-hidden="true"></i>
                            Transparent pricing
                        </li>
                        <li class="ch-why-item">
                            <i class="bi bi-check-circle-fill ch-why-item__icon" aria-hidden="true"></i>
                            Convenient pickup locations
                        </li>
                    </ul>

                    <!-- Decorative produce image placeholder -->
                    <div class="ch-why-choose__visual" aria-hidden="true">
                        <div class="ch-why-choose__emoji-grid">
                            <span>🥛</span><span>🌾</span>
                            <span>🥩</span><span>🥦</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════════════════
     FOOTER
     ══════════════════════════════════════════════════════════ -->
<footer class="ch-footer" role="contentinfo">
    <div class="c-container">
        <div class="ch-footer__grid">

            <!-- Brand col -->
            <div class="ch-footer__brand-col">
                <a href="<?= $view->url('customer') ?>"
                   class="ch-footer__brand"
                   aria-label="AU-AgroSale home">
                    <div class="customer-navbar__logo" style="width:32px;height:32px;font-size:15px;" aria-hidden="true">🌿</div>
                    <div>
                        <div class="ch-footer__brand-name">AU-AgroSale</div>
                        <div class="ch-footer__brand-sub">Arsi University</div>
                    </div>
                </a>
                <p class="ch-footer__about">
                    AU-AgroSale is the official online platform for purchasing quality agricultural products from Arsi University farms.
                </p>
                <div class="ch-footer__social" role="list" aria-label="Social links">
                    <a href="#" class="ch-footer__social-link" aria-label="Twitter" role="listitem">
                        <i class="bi bi-twitter-x" aria-hidden="true"></i>
                    </a>
                    <a href="#" class="ch-footer__social-link" aria-label="Instagram" role="listitem">
                        <i class="bi bi-instagram" aria-hidden="true"></i>
                    </a>
                    <a href="#" class="ch-footer__social-link" aria-label="Telegram" role="listitem">
                        <i class="bi bi-telegram" aria-hidden="true"></i>
                    </a>
                    <a href="#" class="ch-footer__social-link" aria-label="WhatsApp" role="listitem">
                        <i class="bi bi-whatsapp" aria-hidden="true"></i>
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <nav class="ch-footer__nav-col" aria-label="Quick links">
                <div class="ch-footer__nav-heading">Quick Links</div>
                <ul class="ch-footer__nav-list">
                    <li><a href="<?= $view->url('customer') ?>">Home</a></li>
                    <li><a href="<?= $view->url('customer/products') ?>">Products</a></li>
                    <li><a href="<?= $view->url('customer/products') ?>">Categories</a></li>
                    <li><a href="<?= $view->url('customer/shops') ?>">Shops</a></li>
                    <li><a href="<?= $view->url('customer/orders') ?>">My Orders</a></li>
                    <li><a href="<?= $view->url('customer/cart') ?>">Cart</a></li>
                    <li><a href="<?= $view->url('customer/about') ?>">About Us</a></li>
                </ul>
            </nav>

            <!-- Customer Service -->
            <nav class="ch-footer__nav-col" aria-label="Customer service">
                <div class="ch-footer__nav-heading">Customer Service</div>
                <ul class="ch-footer__nav-list">
                    <li><a href="#">How to Order</a></li>
                    <li><a href="#">Payment Methods</a></li>
                    <li><a href="#">Pickup Information</a></li>
                    <li><a href="#">Returns &amp; Refunds</a></li>
                    <li><a href="#">Help Center</a></li>
                </ul>
            </nav>

            <!-- Our Shops -->
            <nav class="ch-footer__nav-col" aria-label="Shop locations">
                <div class="ch-footer__nav-heading">Our Shops</div>
                <ul class="ch-footer__nav-list">
                    <?php if (!empty($displayShops)): ?>
                    <?php foreach (array_slice($displayShops, 0, 5) as $shop): ?>
                    <li>
                        <a href="<?= $view->url('customer/shops/' . (int)$shop['id'] . '/availability') ?>">
                            <?= e($shop['shop_name'] ?? $shop['name']) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <li><a href="<?= $view->url('customer/shops') ?>">All Shop Locations</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <!-- Contact + Newsletter -->
            <div class="ch-footer__contact-col">
                <div class="ch-footer__nav-heading">Contact Us</div>
                <ul class="ch-footer__contact-list">
                    <li>
                        <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                        Arsi University, Asella<br>Oromia, Ethiopia
                    </li>
                    <li>
                        <i class="bi bi-telephone-fill" aria-hidden="true"></i>
                        +251 911 234567
                    </li>
                    <li>
                        <i class="bi bi-envelope-fill" aria-hidden="true"></i>
                        info@au-agrosale.edu.et
                    </li>
                    <li>
                        <i class="bi bi-clock-fill" aria-hidden="true"></i>
                        Mon &ndash; Sat: 8:00 AM &ndash; 6:00 PM
                    </li>
                </ul>

                <div class="ch-footer__newsletter" style="margin-top: var(--c-space-5);">
                    <div class="ch-footer__nav-heading">Stay Updated</div>
                    <p class="ch-footer__newsletter-desc">
                        Subscribe to get updates on new products and special offers.
                    </p>
                    <form class="ch-footer__subscribe-form"
                          action="#"
                          method="post"
                          onsubmit="return false;"
                          aria-label="Newsletter subscription">
                        <input type="email"
                               class="ch-footer__subscribe-input"
                               placeholder="Enter your email"
                               aria-label="Email address for newsletter">
                        <button type="submit" class="ch-footer__subscribe-btn">
                            Subscribe
                        </button>
                    </form>
                </div>
            </div>

        </div><!-- /.ch-footer__grid -->

        <div class="ch-footer__bottom">
            <p class="ch-footer__copyright">
                &copy; <?= date('Y') ?> AU-AgroSale. All rights reserved.
            </p>
            <div class="ch-footer__bottom-links">
                <a href="#">Privacy Policy</a>
                <span aria-hidden="true">·</span>
                <a href="#">Terms of Service</a>
                <span aria-hidden="true">·</span>
                <a href="#">Sitemap</a>
            </div>
        </div>
    </div>
</footer>

<?php $view->section('scripts'); ?>
<script>
/* ── Home page: populate navbar shop dropdown + reflect active selection ── */
(function(){
    var CA = window.CustomerApp || {};
    var activeShopId = <?= (int)$selectedShopId ?>;

    var shops = <?= json_encode(
        array_map(function($s) {
            return [
                'id'           => (int)$s['id'],
                'name'         => $s['shop_name'] ?? $s['name'] ?? '',
                'location'     => $s['location'] ?? '',
                'product_count'=> (int)($s['product_count'] ?? 0),
            ];
        }, $displayShops),
        JSON_UNESCAPED_UNICODE
    ) ?>;

    function buildShopDropdown() {
        var container = document.getElementById('navShopOptions');
        if (!container) return;

        if (!shops.length) {
            container.innerHTML =
                '<div style="padding:var(--c-space-3) var(--c-space-4);' +
                'font-size:var(--c-size-xs);color:var(--c-text-muted);">No shops available</div>';
            return;
        }

        var html = '';
        shops.forEach(function(s) {
            var isActive = (activeShopId && s.id == activeShopId);
            html +=
                '<button type="button"' +
                ' class="customer-shop-selector__option' + (isActive ? ' selected' : '') + '"' +
                ' data-ca-shop-option="'      + s.id + '"' +
                ' data-ca-shop-option-name="' + s.name.replace(/"/g, '&quot;') + '"' +
                ' role="option"' +
                (isActive ? ' aria-selected="true"' : '') + '>' +
                '<div class="customer-shop-selector__option-icon">' +
                  '<i class="bi bi-' + (isActive ? 'check-circle-fill' : 'shop') + '"' +
                  ' style="' + (isActive ? 'color:var(--c-primary);' : '') + '"></i>' +
                '</div>' +
                '<div style="flex:1;min-width:0;">' +
                  '<div style="font-size:var(--c-size-sm);font-weight:' + (isActive ? '600' : '500') + ';' +
                  (isActive ? 'color:var(--c-primary);' : '') + '">' + s.name + '</div>' +
                  (s.location ? '<div style="font-size:var(--c-size-xs);color:var(--c-text-muted);">' + s.location + '</div>' : '') +
                '</div>' +
                (s.product_count > 0
                    ? '<span style="font-size:10px;background:var(--c-primary-light);color:var(--c-primary);' +
                      'padding:2px 6px;border-radius:999px;flex-shrink:0;">' + s.product_count + '</span>'
                    : '') +
                '</button>';
        });
        container.innerHTML = html;
    }

    function markActivePill() {
        if (!activeShopId) return;
        document.querySelectorAll('[data-ca-shop-pill]').forEach(function(btn) {
            var isActive = btn.dataset.caShopPill == activeShopId;
            btn.classList.toggle('ch-shop-card--selected', isActive);
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    function updateNavShopName() {
        var name = <?= json_encode($selectedShopName, JSON_UNESCAPED_UNICODE) ?>;
        if (!name && CA.ShopSelector) {
            var sel = CA.ShopSelector.getSelected();
            if (sel) name = sel.name;
        }
        if (name) {
            document.querySelectorAll('[data-ca-shop-name]').forEach(function(el) {
                el.textContent = name;
            });
        }
    }

    function init() {
        buildShopDropdown();
        markActivePill();
        updateNavShopName();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
</script>
<?php $view->endSection(); ?>
