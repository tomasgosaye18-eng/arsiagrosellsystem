<?php
/**
 * =========================================================
 * Customer Product Detail Page — show.php
 * =========================================================
 * @var array       $product      Full product row + availability
 * @var array|null  $shop         Selected shop
 * @var int         $shopId
 * @var array       $shops        All active shops
 * @var array       $related      Related products (same category, same shop)
 * @var bool        $isFavorited  Whether the logged-in customer has favorited this product
 * @var \AAM\Core\View $view
 */
declare(strict_types=1);

$product     = $product     ?? [];
$shop        = $shop        ?? null;
$shopId      = $shopId      ?? 0;
$shops       = $shops       ?? [];
$related     = $related     ?? [];
$isFavorited = $isFavorited ?? false;

$pid      = (int)($product['id'] ?? 0);
$pname    = $product['product_name'] ?? '';
$price    = (float)($product['selling_price'] ?? 0);
$unit     = $product['unit_abbreviation'] ?? $product['unit_name'] ?? '';
$unitFull = $product['unit_name'] ?? $unit;
$cat      = $product['category_name'] ?? '';
$catId    = (int)($product['category_id'] ?? 0);
$desc     = $product['description'] ?? '';
$sku      = $product['sku'] ?? $product['product_code'] ?? '';
$imgFile  = $product['primary_image'] ?? null;
$images   = $product['images'] ?? [];
$avail    = $product['availability'] ?? ['status' => 'unavailable', 'label' => 'Unknown', 'qty_label' => ''];
$availQty = (float)($product['available_quantity'] ?? 0);
$shopName = $shop ? ($shop['shop_name'] ?? $shop['name'] ?? '') : '';
$isAvail  = $avail['status'] !== 'unavailable';
$isLow    = $avail['status'] === 'low';
$isPeri   = !empty($product['is_perishable']);
$expiry   = $product['expiry_date'] ?? null;

// Resolve primary image
$primaryImage = null;
foreach ($images as $img) {
    if ($img['is_primary']) { $primaryImage = $img['filename']; break; }
}
if (!$primaryImage && $imgFile) { $primaryImage = $imgFile; }
$galleryImages = $images; // all images including primary for thumbnails

// Category icon helper
function _pdCatIcon(string $name): string {
    $icons = [
        'dairy'     => '🥛', 'milk'   => '🥛',
        'grain'     => '🌾', 'teff'   => '🌾', 'wheat'  => '🌾', 'barley' => '🌾',
        'egg'       => '🥚',
        'honey'     => '🍯',
        'meat'      => '🥩', 'chicken'=> '🍗',
        'vegetable' => '🥦',
        'fruit'     => '🍎',
        'herb'      => '🌿',
        'legume'    => '🫘', 'pulse'  => '🫘',
        'oil'       => '🫙',
        'other'     => '📦',
    ];
    $lc = strtolower($name);
    foreach ($icons as $k => $ico) {
        if (str_contains($lc, $k)) return $ico;
    }
    return '📦';
}
$productIcon = _pdCatIcon($cat ?: $pname);

// Format price nicely (remove trailing zeros after decimal if .00)
$priceFormatted = number_format($price, 2);

// Build product URL with/without shop
$productsUrl = $view->url('customer/products' . ($shopId ? '?shop_id=' . $shopId : ''));
$catUrl      = $catId
    ? $view->url('customer/products?' . ($shopId ? 'shop_id=' . $shopId . '&' : '') . 'category_id=' . $catId)
    : $productsUrl;
?>

<!-- ═══════════════════════════════════════════════════════════
     PRODUCT DETAIL PAGE
     ═══════════════════════════════════════════════════════════ -->

<!-- ── Breadcrumb / Toolbar ──────────────────────────────────── -->
<div class="cpd-topbar">
    <div class="c-container">
        <!-- Back navigation (flex item, sits left of breadcrumb) -->
        <a href="<?= $productsUrl ?>"
           class="c-back-nav"
           aria-label="Back to Products">
            <i class="bi bi-arrow-left c-back-nav__icon" aria-hidden="true"></i>
            <span class="c-back-nav__label">Back to Products</span>
        </a>
        <nav class="cpd-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= $view->url('customer') ?>" class="cpd-breadcrumb__link">
                <i class="bi bi-house"></i>
                <span>Home</span>
            </a>
            <i class="bi bi-chevron-right cpd-breadcrumb__sep" aria-hidden="true"></i>
            <a href="<?= $productsUrl ?>" class="cpd-breadcrumb__link">Products</a>

            <?php if ($cat && $catId): ?>
            <i class="bi bi-chevron-right cpd-breadcrumb__sep" aria-hidden="true"></i>
            <a href="<?= $catUrl ?>" class="cpd-breadcrumb__link"><?= e($cat) ?></a>
            <?php endif; ?>

            <i class="bi bi-chevron-right cpd-breadcrumb__sep" aria-hidden="true"></i>
            <span class="cpd-breadcrumb__current" aria-current="page"><?= e($pname) ?></span>
        </nav>

        <?php if ($shopId && $shopName): ?>
        <div class="cpd-shop-pill">
            <i class="bi bi-geo-alt-fill"></i>
            <span><?= e($shopName) ?></span>
            <a href="<?= $view->url('customer/shops') ?>" class="cpd-shop-pill__change">Change</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Main Product Layout ───────────────────────────────────── -->
<div class="c-container" id="customer-main">
    <div class="cpd-layout">

        <!-- ════════════════════════════════════════════════
             LEFT: IMAGE GALLERY
             ════════════════════════════════════════════════ -->
        <div class="cpd-gallery">

            <!-- Main image -->
            <div class="cpd-gallery__main" id="cpdGalleryMain">

                <?php if ($primaryImage): ?>
                <img id="cpdMainImg"
                     src="<?= $view->url(e($primaryImage)) ?>"
                     alt="<?= e($pname) ?>"
                     loading="eager"
                     width="640" height="640">
                <?php else: ?>
                <div class="cpd-gallery__placeholder" aria-label="No image available">
                    <span class="cpd-gallery__placeholder-icon"><?= $productIcon ?></span>
                    <span class="cpd-gallery__placeholder-text">No image available</span>
                </div>
                <?php endif; ?>

                <!-- Overlay badges -->
                <div class="cpd-gallery__overlay-badges">
                    <?php if ($isPeri): ?>
                    <span class="cpd-overlay-badge cpd-overlay-badge--info">
                        <i class="bi bi-thermometer-half"></i> Perishable
                    </span>
                    <?php endif; ?>

                    <?php if (!empty($product['is_featured'])): ?>
                    <span class="cpd-overlay-badge cpd-overlay-badge--featured">
                        <i class="bi bi-star-fill"></i> Featured
                    </span>
                    <?php endif; ?>
                </div>

                <?php if ($avail['status'] === 'low'): ?>
                <div class="cpd-gallery__stock-banner cpd-gallery__stock-banner--low">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <?= e($avail['label']) ?>
                </div>
                <?php elseif ($avail['status'] === 'unavailable'): ?>
                <div class="cpd-gallery__stock-banner cpd-gallery__stock-banner--out">
                    <i class="bi bi-x-circle-fill"></i>
                    Out of stock
                </div>
                <?php endif; ?>
            </div>

            <!-- Thumbnail strip -->
            <?php if (count($galleryImages) > 1): ?>
            <div class="cpd-gallery__thumbs" role="list" aria-label="Product images">
                <?php foreach ($galleryImages as $gimg): ?>
                <button type="button"
                        class="cpd-gallery__thumb <?= $gimg['is_primary'] ? 'active' : '' ?>"
                        data-cpd-thumb-src="<?= $view->url(e($gimg['filename'])) ?>"
                        data-cpd-thumb-alt="<?= e($gimg['alt_text'] ?? $pname) ?>"
                        aria-label="View image: <?= e($gimg['alt_text'] ?? '') ?>"
                        role="listitem">
                    <img src="<?= $view->url(e($gimg['filename'])) ?>"
                         alt="<?= e($gimg['alt_text'] ?? $pname) ?>"
                         loading="lazy" width="80" height="80">
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Pickup notice -->
            <div class="cpd-pickup-notice">
                <i class="bi bi-truck"></i>
                <div>
                    <strong>Pickup Only</strong>
                    <span>No delivery. Pick up from the shop.</span>
                </div>
            </div>
        </div><!-- /.cpd-gallery -->


        <!-- ════════════════════════════════════════════════
             RIGHT: PRODUCT INFO
             ════════════════════════════════════════════════ -->
        <div class="cpd-info">

            <!-- Category link -->
            <?php if ($cat): ?>
            <div class="cpd-info__category">
                <a href="<?= $catUrl ?>" class="cpd-info__category-link">
                    <?= _pdCatIcon($cat) ?> <?= e($cat) ?>
                </a>
            </div>
            <?php endif; ?>

            <!-- Product name + Favorite button -->
            <div class="cpd-info__name-row">
                <h1 class="cpd-info__name"><?= e($pname) ?></h1>
                <button type="button"
                        class="cpd-fav-btn <?= $isFavorited ? 'cpd-fav-btn--active' : '' ?>"
                        data-ca-favorite-toggle
                        data-product-id="<?= $pid ?>"
                        data-product-name="<?= e($pname) ?>"
                        aria-label="<?= $isFavorited ? 'Remove ' . e($pname) . ' from favorites' : 'Save ' . e($pname) . ' to favorites' ?>"
                        aria-pressed="<?= $isFavorited ? 'true' : 'false' ?>">
                    <i class="bi <?= $isFavorited ? 'bi-heart-fill' : 'bi-heart' ?>" aria-hidden="true"></i>
                    <span class="cpd-fav-btn__label"><?= $isFavorited ? 'Saved' : 'Save' ?></span>
                </button>
            </div>

            <!-- Price block -->
            <div class="cpd-info__price-block">
                <div class="cpd-info__price-row">
                    <span class="cpd-info__price">ETB <?= $priceFormatted ?></span>
                    <?php if ($unit): ?>
                    <span class="cpd-info__price-unit">/ <?= e($unit) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Availability Panel ──────────────────────── -->
            <div class="cpd-avail-panel cpd-avail-panel--<?= e($avail['status']) ?>">
                <div class="cpd-avail-panel__header">
                    <i class="bi bi-shop" aria-hidden="true"></i>
                    <?php if ($shopId && $shopName): ?>
                    <span>
                        <strong><?= e($shopName) ?></strong>
                        <a href="<?= $view->url('customer/shops') ?>" class="cpd-avail-panel__change">Change shop</a>
                    </span>
                    <?php else: ?>
                    <span>
                        No shop selected
                        <a href="<?= $view->url('customer/shops') ?>" class="cpd-avail-panel__change">Select a shop</a>
                    </span>
                    <?php endif; ?>
                </div>

                <div class="cpd-avail-panel__status">
                    <?php if ($avail['status'] === 'available'): ?>
                        <i class="bi bi-check-circle-fill cpd-avail-icon cpd-avail-icon--ok"></i>
                        <span class="cpd-avail-label cpd-avail-label--ok">In stock</span>
                        <?php if ($avail['qty_label']): ?>
                        <span class="cpd-avail-qty">
                            — <?= e($avail['qty_label']) ?> <?= e($unit) ?> available
                        </span>
                        <?php endif; ?>

                    <?php elseif ($avail['status'] === 'low'): ?>
                        <i class="bi bi-exclamation-triangle-fill cpd-avail-icon cpd-avail-icon--low"></i>
                        <span class="cpd-avail-label cpd-avail-label--low">Low stock</span>
                        <?php if ($avail['qty_label']): ?>
                        <span class="cpd-avail-qty cpd-avail-qty--low">
                            — <?= e($avail['label']) ?>
                        </span>
                        <?php endif; ?>

                    <?php else: ?>
                        <i class="bi bi-x-circle-fill cpd-avail-icon cpd-avail-icon--out"></i>
                        <span class="cpd-avail-label cpd-avail-label--out">Out of stock</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Cart Section ────────────────────────────── -->
            <?php if ($shopId && $isAvail): ?>
            <div class="cpd-cart-section">
                <div class="cpd-cart-section__qty-row">
                    <label class="cpd-cart-section__qty-label" for="cpdQtyInput">Quantity</label>
                    <div class="cpd-qty-control" role="group" aria-label="Quantity selector">
                        <button type="button" class="cpd-qty-btn" id="cpdQtyDec" aria-label="Decrease quantity">
                            <i class="bi bi-dash-lg" aria-hidden="true"></i>
                        </button>
                        <input type="number"
                               id="cpdQtyInput"
                               class="cpd-qty-input"
                               value="1"
                               min="1"
                               max="<?= (int)$availQty ?>"
                               aria-label="Quantity"
                               aria-describedby="cpdQtyHint">
                        <button type="button" class="cpd-qty-btn" id="cpdQtyInc" aria-label="Increase quantity">
                            <i class="bi bi-plus-lg" aria-hidden="true"></i>
                        </button>
                    </div>
                    <?php if ($availQty > 0): ?>
                    <p class="cpd-qty-hint" id="cpdQtyHint">
                        Max: <?= number_format($availQty, 0) ?> <?= e($unit) ?>
                    </p>
                    <?php endif; ?>
                </div>

                <button type="button"
                        class="c-btn c-btn-primary c-btn-lg cpd-add-btn"
                        id="cpdAddToCartBtn"
                        data-product-id="<?= $pid ?>"
                        data-product-name="<?= e($pname) ?>"
                        data-product-price="<?= e($price) ?>"
                        data-unit="<?= e($unit) ?>"
                        data-max-qty="<?= e($availQty) ?>"
                        data-shop-id="<?= $shopId ?>"
                        data-shop-name="<?= e($shopName) ?>">
                    <i class="bi bi-bag-plus-fill" aria-hidden="true"></i>
                    Add to Cart
                </button>
            </div>

            <?php elseif (!$shopId): ?>
            <!-- No shop selected -->
            <div class="cpd-noShop-cta">
                <div class="cpd-noShop-cta__msg">
                    <i class="bi bi-geo-alt" aria-hidden="true"></i>
                    <span>Select a shop to check availability and add to cart.</span>
                </div>
                <div class="cpd-noShop-cta__shops">
                    <?php foreach (array_slice($shops, 0, 4) as $s): ?>
                    <a href="<?= $view->url('customer/products/' . $pid . '?shop_id=' . (int)$s['id']) ?>"
                       class="c-btn c-btn-secondary c-btn-sm">
                        <i class="bi bi-shop" aria-hidden="true"></i>
                        <?= e($s['shop_name'] ?? $s['name'] ?? '') ?>
                    </a>
                    <?php endforeach; ?>
                    <?php if (count($shops) > 4): ?>
                    <a href="<?= $view->url('customer/shops') ?>" class="cpd-noShop-cta__more">
                        All shops <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php else: ?>
            <!-- Unavailable at this shop -->
            <div class="cpd-unavail-cta">
                <i class="bi bi-x-circle-fill" aria-hidden="true"></i>
                <div class="cpd-unavail-cta__body">
                    <strong>Out of stock</strong>
                    <span>This product is not available at <em><?= e($shopName) ?></em> right now.</span>
                </div>
            </div>
            <a href="<?= $view->url('customer/shops') ?>" class="c-btn c-btn-secondary cpd-check-shops-btn">
                <i class="bi bi-shop" aria-hidden="true"></i>
                Check other shops
            </a>
            <?php endif; ?>

            <!-- ── Product Attributes ──────────────────────── -->
            <div class="cpd-attributes">
                <h3 class="cpd-attributes__title">Product Details</h3>
                <div class="cpd-attributes__grid">

                    <?php if ($cat): ?>
                    <div class="cpd-attr-row">
                        <span class="cpd-attr-label"><i class="bi bi-tag"></i> Category</span>
                        <span class="cpd-attr-value"><?= e($cat) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($unit): ?>
                    <div class="cpd-attr-row">
                        <span class="cpd-attr-label"><i class="bi bi-rulers"></i> Unit</span>
                        <span class="cpd-attr-value"><?= e($unitFull) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($sku): ?>
                    <div class="cpd-attr-row">
                        <span class="cpd-attr-label"><i class="bi bi-upc-scan"></i> SKU</span>
                        <span class="cpd-attr-value cpd-attr-value--mono"><?= e($sku) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($isPeri): ?>
                    <div class="cpd-attr-row">
                        <span class="cpd-attr-label"><i class="bi bi-thermometer-half"></i> Type</span>
                        <span class="cpd-attr-value">
                            <span class="cpd-type-badge">Perishable</span>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if ($expiry && $shopId): ?>
                    <div class="cpd-attr-row">
                        <span class="cpd-attr-label"><i class="bi bi-calendar-event"></i> Best before</span>
                        <span class="cpd-attr-value"><?= e(date('d M Y', strtotime($expiry))) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($shopId && $shopName): ?>
                    <div class="cpd-attr-row">
                        <span class="cpd-attr-label"><i class="bi bi-shop"></i> Shop</span>
                        <span class="cpd-attr-value"><?= e($shopName) ?></span>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

            <!-- ── Description ────────────────────────────── -->
            <?php if ($desc): ?>
            <div class="cpd-description">
                <h3 class="cpd-description__title">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    About this product
                </h3>
                <p class="cpd-description__body"><?= nl2br(e($desc)) ?></p>
            </div>
            <?php endif; ?>

        </div><!-- /.cpd-info -->
    </div><!-- /.cpd-layout -->
</div><!-- /.c-container -->

<!-- ═══════════════════════════════════════════════════════════
     RELATED PRODUCTS
     ═══════════════════════════════════════════════════════════ -->
<?php if (!empty($related)): ?>
<section class="cpd-related" aria-labelledby="relatedHeading">
    <div class="c-container">
        <div class="cpd-related__header">
            <div>
                <p class="c-section-label">More like this</p>
                <h2 class="c-section-title" id="relatedHeading">Related Products</h2>
            </div>
            <a href="<?= $catUrl ?>" class="c-btn c-btn-secondary">
                See all in <?= e($cat ?: 'category') ?>
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>
        </div>

        <div class="c-grid-products" role="list" aria-label="Related products">
            <?php foreach ($related as $rp):
                $rpid   = (int)$rp['id'];
                $rpname = $rp['product_name'] ?? '';
                $rprice = (float)($rp['selling_price'] ?? 0);
                $runit  = $rp['unit_abbreviation'] ?? '';
                $ravail = $rp['availability'] ?? ['status' => 'unavailable', 'label' => ''];
                $rpOk   = $ravail['status'] !== 'unavailable';
                $rpUrl  = $view->url('customer/products/' . $rpid . ($shopId ? '?shop_id=' . $shopId : ''));
                $rpQty  = (float)($rp['available_quantity'] ?? 0);
            ?>
            <article class="customer-product-card <?= !$rpOk ? 'customer-product-card--unavailable' : '' ?>"
                     role="listitem"
                     aria-label="<?= e($rpname) ?>">
                <a href="<?= $rpUrl ?>" class="customer-product-card__image-wrap" tabindex="-1" aria-hidden="true">
                    <?php if (!empty($rp['primary_image'])): ?>
                    <img src="<?= $view->url(e($rp['primary_image'])) ?>"
                         alt="<?= e($rpname) ?>" loading="lazy" width="400" height="300">
                    <?php else: ?>
                    <div class="customer-product-card__image-placeholder">
                        <span class="customer-product-card__placeholder-icon"><?= _pdCatIcon($cat) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="customer-product-card__badge-row">
                        <?php if ($ravail['status'] === 'available'): ?>
                        <span class="c-badge c-badge-available"><i class="bi bi-check-circle-fill"></i> In stock</span>
                        <?php elseif ($ravail['status'] === 'low'): ?>
                        <span class="c-badge c-badge-low"><i class="bi bi-exclamation-triangle-fill"></i> <?= e($ravail['label']) ?></span>
                        <?php else: ?>
                        <span class="c-badge c-badge-unavailable">Out of stock</span>
                        <?php endif; ?>
                    </div>
                </a>
                <div class="customer-product-card__body">
                    <div class="customer-product-card__category"><?= e($rp['category_name'] ?? '') ?></div>
                    <h3 class="customer-product-card__name">
                        <a href="<?= $rpUrl ?>"><?= e($rpname) ?></a>
                    </h3>
                    <?php if ($rpOk && $rpQty > 0): ?>
                    <div class="customer-product-card__meta">
                        <i class="bi bi-check-circle-fill" style="color:var(--c-success);font-size:12px;"></i>
                        <span class="customer-product-card__unit"><?= number_format($rpQty, 0) ?> <?= e($runit) ?> available</span>
                    </div>
                    <?php if ($shopName): ?>
                    <div class="customer-product-card__shop">
                        <i class="bi bi-geo-alt-fill"></i>
                        <?= e($shopName) ?>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="customer-product-card__footer">
                    <div class="customer-product-card__price-block">
                        <span class="customer-product-card__price">ETB <?= number_format($rprice, 2) ?></span>
                        <?php if ($runit): ?>
                        <span class="customer-product-card__price-unit">/ <?= e($runit) ?></span>
                        <?php endif; ?>
                    </div>
                    <button type="button"
                            class="customer-product-card__add-btn <?= !$rpOk ? 'disabled' : '' ?>"
                            <?= !$rpOk ? 'disabled aria-disabled="true"' : '' ?>
                            data-ca-add-to-cart
                            data-product-id="<?= $rpid ?>"
                            data-product-name="<?= e($rpname) ?>"
                            data-product-price="<?= e($rprice) ?>"
                            data-unit="<?= e($runit) ?>"
                            data-max-qty="<?= e($rpQty) ?>"
                            data-shop-id="<?= $shopId ?>"
                            data-shop-name="<?= e($shopName) ?>"
                            aria-label="Add <?= e($rpname) ?> to cart">
                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                    </button>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php $view->section('scripts'); ?>
<script>
(function () {
    'use strict';

    var CA       = window.CustomerApp || {};
    var appUrl   = CA.appUrl || '';
    var maxQty   = <?= (float)$availQty ?>;
    var shopId   = <?= (int)$shopId ?>;
    var shopName = <?= json_encode($shopName) ?>;
    var pid      = <?= (int)$pid ?>;
    var pname    = <?= json_encode($pname) ?>;
    var price    = <?= (float)$price ?>;
    var unit     = <?= json_encode($unit) ?>;

    /* ── Quantity stepper ─────────────────────────────── */
    var qtyInput = document.getElementById('cpdQtyInput');
    var decBtn   = document.getElementById('cpdQtyDec');
    var incBtn   = document.getElementById('cpdQtyInc');
    var addBtn   = document.getElementById('cpdAddToCartBtn');

    function _clamp(val) {
        val = parseInt(val, 10) || 1;
        if (val < 1) val = 1;
        if (maxQty > 0 && val > maxQty) {
            val = Math.floor(maxQty);
            if (CA.Toast) CA.Toast.warning('Stock limit', 'Only ' + maxQty + ' ' + unit + ' available.');
        }
        return val;
    }

    if (decBtn && qtyInput) {
        decBtn.addEventListener('click', function () {
            qtyInput.value = _clamp(parseInt(qtyInput.value, 10) - 1);
        });
    }
    if (incBtn && qtyInput) {
        incBtn.addEventListener('click', function () {
            qtyInput.value = _clamp(parseInt(qtyInput.value, 10) + 1);
        });
    }
    if (qtyInput) {
        qtyInput.addEventListener('change', function () { qtyInput.value = _clamp(qtyInput.value); });
        qtyInput.addEventListener('blur',   function () { qtyInput.value = _clamp(qtyInput.value); });
    }

    /* ── Add to Cart ──────────────────────────────────── */
    if (addBtn) {
        addBtn.addEventListener('click', function () {
            var qty = _clamp(qtyInput ? parseInt(qtyInput.value, 10) : 1);
            if (qty <= 0) return;

            addBtn.disabled = true;
            addBtn.innerHTML = '<i class="bi bi-hourglass-split" aria-hidden="true"></i> Adding…';

            var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            fetch(appUrl + '/customer/cart/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'csrf_token=' + encodeURIComponent(csrf)
                    + '&product_id=' + encodeURIComponent(pid)
                    + '&shop_id='    + encodeURIComponent(shopId)
                    + '&quantity='   + encodeURIComponent(qty)
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    var data  = res.data || {};
                    var count = data.cart_count;

                    // Update badge from server
                    if (count !== undefined && CA.CartBadge) CA.CartBadge.update(count);

                    // Mirror to localStorage
                    try {
                        var raw     = localStorage.getItem('ca_cart');
                        var cartArr = raw ? JSON.parse(raw) : [];
                        var idx = cartArr.findIndex(function (i) {
                            return String(i.productId) === String(pid) &&
                                   String(i.shopId || '') === String(shopId);
                        });
                        if (idx >= 0) {
                            cartArr[idx].qty = parseFloat((data.item && data.item.quantity) || cartArr[idx].qty);
                        } else {
                            cartArr.push({
                                productId:   String(pid),
                                productName: pname,
                                price:       price,
                                unit:        unit,
                                qty:         qty,
                                maxQty:      maxQty,
                                shopId:      String(shopId),
                                shopName:    shopName
                            });
                        }
                        localStorage.setItem('ca_cart', JSON.stringify(cartArr));
                    } catch (_) {}

                    if (CA.Toast) CA.Toast.success(
                        'Added to cart',
                        qty + ' × ' + pname + ' added.'
                    );

                    addBtn.innerHTML = '<i class="bi bi-check-lg" aria-hidden="true"></i> Added!';
                    addBtn.style.background = 'var(--c-success)';
                    setTimeout(function () {
                        addBtn.innerHTML         = '<i class="bi bi-bag-plus-fill" aria-hidden="true"></i> Add to Cart';
                        addBtn.style.background  = '';
                        addBtn.disabled          = false;
                    }, 1500);

                } else {
                    if (CA.Toast) CA.Toast.warning('Cannot add', res.message || 'Not enough stock.');
                    addBtn.innerHTML = '<i class="bi bi-bag-plus-fill" aria-hidden="true"></i> Add to Cart';
                    addBtn.disabled  = false;

                    if (res.errors && res.errors.available !== undefined) {
                        maxQty = parseFloat(res.errors.available) || 0;
                        if (qtyInput) qtyInput.max = Math.floor(maxQty);
                    }
                }
            })
            .catch(function () {
                if (CA.Toast) CA.Toast.error('Error', 'Could not add item. Please try again.');
                addBtn.innerHTML = '<i class="bi bi-bag-plus-fill" aria-hidden="true"></i> Add to Cart';
                addBtn.disabled  = false;
            });
        });
    }

    /* ── Thumbnail gallery ────────────────────────────── */
    document.querySelectorAll('[data-cpd-thumb-src]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var mainImg = document.getElementById('cpdMainImg');
            if (mainImg) {
                mainImg.src = btn.dataset.cpdThumbSrc;
                mainImg.alt = btn.dataset.cpdThumbAlt || '';
            }
            document.querySelectorAll('.cpd-gallery__thumb').forEach(function (b) {
                b.classList.remove('active');
            });
            btn.classList.add('active');
        });
    });

}());
</script>
<?php $view->endSection(); ?>
