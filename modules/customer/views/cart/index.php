<?php
/**
 * =========================================================
 * AU-AgroSale — Customer Cart Page
 * modules/customer/views/cart/index.php
 * =========================================================
 * Reference-matched premium e-commerce cart UI.
 *
 * @var int         $shopId
 * @var string      $shopName
 * @var array|null  $shop
 * @var array       $shops
 * @var array       $cart          Validated cart items from CartService::recheckCart()
 * @var array       $warnings      Per-item and global warnings
 * @var float       $subtotal      Server-calculated subtotal (valid items only)
 * @var int         $itemCount     Total quantity of valid items
 * @var int         $distinctCount Number of distinct product lines
 * @var bool        $hasIssues     True if any item has a warning
 * @var array       $recommended   Recommended products
 * @var \AAM\Core\View $view
 */
declare(strict_types=1);

$shopId        = $shopId        ?? 0;
$shopName      = $shopName      ?? '';
$shop          = $shop          ?? null;
$shops         = $shops         ?? [];
$cart          = $cart          ?? [];
$warnings      = $warnings      ?? [];
$subtotal      = (float)($subtotal ?? 0);
$itemCount     = (int)($itemCount  ?? 0);
$distinctCount = (int)($distinctCount ?? 0);
$hasIssues     = (bool)($hasIssues   ?? false);
$recommended   = $recommended   ?? [];

// Helpers
function cartFmt(float $n): string {
    return 'ETB ' . number_format($n, 2);
}
function cartItemStatus(array $item): string {
    return $item['status'] ?? 'ok';
}
function cartItemHasIssue(array $item): bool {
    return in_array(cartItemStatus($item), ['unavailable', 'out_of_stock', 'adjusted'], true);
}
function cartItemIsBlocking(array $item): bool {
    return in_array(cartItemStatus($item), ['unavailable', 'out_of_stock'], true);
}
?>
<?php $view->section('styles'); ?>
<link rel="stylesheet" href="<?= $view->url('public/assets/customer/css/cart.css') ?>?v=<?= filemtime(PUBLIC_PATH . '/assets/customer/css/cart.css') ?>">
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
            <span class="ch-announce-bar__sep" aria-hidden="true">•</span>
            <span class="ch-announce-bar__item">
                <i class="bi bi-shield-check" aria-hidden="true"></i>
                Secure payment
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

<!-- ── Breadcrumb toolbar ─────────────────────────────────── -->
<div class="cp-toolbar">
    <div class="c-container cp-toolbar__inner">
        <a href="<?= $view->url('customer/products' . ($shopId ? '?shop_id=' . $shopId : '')) ?>"
           class="c-back-nav"
           aria-label="Continue Shopping — back to Products">
            <i class="bi bi-arrow-left c-back-nav__icon" aria-hidden="true"></i>
            <span class="c-back-nav__label">Continue Shopping</span>
        </a>
        <nav class="cp-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= $view->url('customer') ?>">Home</a>
            <span class="cp-breadcrumb__sep" aria-hidden="true">›</span>
            <span aria-current="page">Cart</span>
        </nav>
    </div>
</div>

<!-- ── Main content ──────────────────────────────────────── -->
<div class="c-container cart-page-container" id="customer-main">

    <?php if (empty($cart)): ?>
    <!-- ══════════════════════════════════════════════════
         EMPTY CART STATE
         ══════════════════════════════════════════════════ -->
    <div class="cart-empty-state c-animate-fade-up">
        <div class="cart-empty-state__icon" aria-hidden="true">
            <i class="bi bi-cart-x"></i>
        </div>
        <h1 class="cart-empty-state__title">Your cart is empty</h1>
        <p class="cart-empty-state__desc">
            <?php if ($shopId && $shopName): ?>
                Browse fresh products from <strong><?= e($shopName) ?></strong> and add your favourites.
            <?php else: ?>
                Select a shop and start adding products to your cart.
            <?php endif; ?>
        </p>
        <div class="cart-empty-state__actions">
            <a href="<?= $view->url('customer/products' . ($shopId ? '?shop_id=' . $shopId : '')) ?>"
               class="c-btn c-btn-primary c-btn-lg">
                <i class="bi bi-grid-fill" aria-hidden="true"></i>
                Browse Products
            </a>
            <?php if (!$shopId): ?>
            <a href="<?= $view->url('customer/shops') ?>" class="c-btn c-btn-secondary c-btn-lg">
                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                Find a Shop
            </a>
            <?php endif; ?>
        </div>
        <?php if ($shopId && $shopName): ?>
        <p class="cart-empty-state__shop-hint">
            <i class="bi bi-shop" aria-hidden="true"></i>
            Selected shop: <strong><?= e($shopName) ?></strong>
            &ensp;·&ensp;
            <button type="button" class="cart-empty-state__change-shop" id="cartChangeShopTriggerEmpty">
                Change shop
            </button>
        </p>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- ══════════════════════════════════════════════════
         CART WITH ITEMS
         ══════════════════════════════════════════════════ -->

    <!-- Page heading -->
    <div class="cart-page-header">
        <h1 class="cart-page-title">Your Shopping Cart</h1>
    </div>

    <!-- Pickup Shop Banner -->
    <?php if ($shopId && $shopName): ?>
    <div class="cart-pickup-banner">
        <div class="cart-pickup-banner__left">
            <i class="bi bi-geo-alt-fill cart-pickup-banner__icon" aria-hidden="true"></i>
            <span class="cart-pickup-banner__label">Pickup Shop:</span>
            <span class="cart-pickup-banner__name"><?= e($shopName) ?><?php if (!empty($shop['location'])): ?>, <?= e($shop['location']) ?><?php endif; ?></span>
        </div>
        <button type="button" class="cart-pickup-banner__btn" id="cartChangeShopTrigger" aria-label="Change pickup shop">
            Change Shop
        </button>
    </div>
    <?php else: ?>
    <div class="cart-pickup-banner cart-pickup-banner--empty">
        <div class="cart-pickup-banner__left">
            <i class="bi bi-exclamation-triangle cart-pickup-banner__icon" aria-hidden="true"></i>
            <span class="cart-pickup-banner__label">No shop selected.</span>
            <a href="<?= $view->url('customer/shops') ?>" class="cart-pickup-banner__link">Choose a shop to continue.</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Global warnings banner -->
    <?php if ($hasIssues): ?>
    <?php
        $blockingCount = count(array_filter($cart, fn($i) => cartItemIsBlocking($i)));
        $hasBlocking   = $blockingCount > 0;
    ?>
    <div class="customer-alert customer-alert--warning cart-issues-banner" id="cartIssuesBanner" role="alert">
        <i class="bi bi-exclamation-triangle-fill customer-alert__icon" aria-hidden="true"></i>
        <div class="customer-alert__body">
            <div class="customer-alert__title">Stock changes detected</div>
            <div>Some items in your cart have changed since you last visited. Review the items below before proceeding.</div>
            <?php if ($hasBlocking): ?>
            <button type="button"
                    class="c-btn c-btn-sm"
                    id="removeUnavailableBtn"
                    style="margin-top:var(--c-space-3);color:var(--c-danger);border-color:var(--c-danger);background:transparent;">
                <i class="bi bi-trash3" aria-hidden="true"></i>
                Remove <?= $blockingCount ?> unavailable item<?= $blockingCount !== 1 ? 's' : '' ?>
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Cart layout: items + summary sidebar -->
    <div class="cart-layout" id="cartLayout">

        <!-- ── Left: Cart items ───────────────────────── -->
        <div class="cart-items-col">

            <!-- Desktop table header -->
            <div class="cart-table-header" role="row" aria-hidden="true">
                <span class="cart-th cart-th-product">Product</span>
                <span class="cart-th cart-th-price">Price</span>
                <span class="cart-th cart-th-qty">Quantity</span>
                <span class="cart-th cart-th-subtotal">Subtotal</span>
                <span class="cart-th cart-th-action">Action</span>
            </div>

            <!-- Cart items list -->
            <div id="cartItemsList" role="list" aria-label="Cart items">
                <?php foreach ($cart as $item):
                    $pid         = (int)$item['product_id'];
                    $sid         = (int)($item['shop_id'] ?? $shopId);
                    $qty         = (float)$item['quantity'];
                    $price       = (float)$item['price'];
                    $available   = (float)($item['available'] ?? 0);
                    $subtotalItem = round($qty * $price, 2);
                    $status      = cartItemStatus($item);
                    $hasIssueRow = cartItemHasIssue($item);
                    $isBlocking  = cartItemIsBlocking($item);
                    $warning     = $item['warning'] ?? null;
                    $unit        = $item['unit'] ?? '';
                    $imgFile     = $item['primary_image'] ?? null;
                    // Build measurement label: "1 Liter", "2 kg", "12 Pieces" etc.
                    // Matches reference design: qty + unit_name shown below product name
                    $measurement = '';
                    if (!empty($item['unit_name'])) {
                        $measurement = number_format($qty, $qty == (int)$qty ? 0 : 2) . ' ' . $item['unit_name'];
                    } elseif ($unit) {
                        $measurement = number_format($qty, $qty == (int)$qty ? 0 : 2) . ' ' . $unit;
                    }
                ?>
                <div class="cart-item-row <?= $isBlocking ? 'cart-item-row--unavailable' : ($hasIssueRow ? 'cart-item-row--warning' : '') ?>"
                     data-pid="<?= $pid ?>"
                     data-sid="<?= $sid ?>"
                     data-price="<?= $price ?>"
                     data-available="<?= $available ?>"
                     data-unit="<?= e($unit) ?>"
                     data-status="<?= e($status) ?>"
                     id="cartRow_<?= $pid ?>"
                     role="listitem">

                    <!-- Product image -->
                    <div class="cart-item-row__img" aria-hidden="true">
                        <?php if ($imgFile): ?>
                        <img src="<?= $view->url(e($imgFile)) ?>"
                             alt="<?= e($item['product_name'] ?? '') ?>"
                             loading="lazy"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <div class="cart-item-row__img-placeholder" style="display:none;" aria-hidden="true">📦</div>
                        <?php else: ?>
                        <div class="cart-item-row__img-placeholder" aria-hidden="true">📦</div>
                        <?php endif; ?>
                        <?php if ($isBlocking): ?>
                        <div class="cart-item-row__img-overlay">
                            <span>Unavailable</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Product info -->
                    <div class="cart-item-row__info">
                        <div class="cart-item-row__name">
                            <a href="<?= $view->url('customer/products/' . $pid . ($shopId ? '?shop_id=' . $shopId : '')) ?>">
                                <?= e($item['product_name'] ?? '') ?>
                            </a>
                        </div>
                        <?php if ($measurement): ?>
                        <div class="cart-item-row__measurement"><?= e($measurement) ?></div>
                        <?php endif; ?>
                        <?php if ($available > 0 && !$isBlocking): ?>
                        <div class="cart-item-row__stock">
                            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                            <?= number_format($available, $available == (int)$available ? 0 : 2) ?><?= $unit ? ' ' . e($unit) : '' ?> available
                        </div>
                        <?php elseif ($isBlocking): ?>
                        <div class="cart-item-row__stock cart-item-row__stock--unavail">
                            <i class="bi bi-x-circle-fill" aria-hidden="true"></i>
                            <?= $status === 'out_of_stock' ? 'Out of stock' : 'Unavailable' ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($warning): ?>
                        <div class="cart-item-warning" role="alert">
                            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                            <?= e($warning) ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Unit price (desktop) -->
                    <div class="cart-item-row__price">
                        <span class="cart-item-price-val"><?= cartFmt($price) ?></span>
                        <?php if ($unit): ?>
                        <span class="cart-item-price-unit">/ <?= e($unit) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Quantity stepper -->
                    <div class="cart-item-row__qty">
                        <?php if ($isBlocking): ?>
                        <span class="cart-item-qty-blocked">Unavailable</span>
                        <?php else: ?>
                        <div class="cart-qty-stepper" data-cart-stepper>
                            <button type="button"
                                    class="cart-qty-stepper__btn"
                                    data-qty-dec
                                    aria-label="Decrease quantity"
                                    <?= $qty <= 1 ? 'disabled' : '' ?>>
                                <i class="bi bi-dash" aria-hidden="true"></i>
                            </button>
                            <input type="number"
                                   class="cart-qty-stepper__input"
                                   data-qty-input
                                   value="<?= (int)$qty ?>"
                                   min="1"
                                   max="<?= (int)$available ?>"
                                   aria-label="Quantity for <?= e($item['product_name'] ?? '') ?>">
                            <button type="button"
                                    class="cart-qty-stepper__btn"
                                    data-qty-inc
                                    aria-label="Increase quantity"
                                    <?= $qty >= $available ? 'disabled' : '' ?>>
                                <i class="bi bi-plus" aria-hidden="true"></i>
                            </button>
                        </div>
                        <?php if ($available > 0 && $available <= 5): ?>
                        <div class="cart-qty-hint">
                            Only <?= number_format($available, 0) ?><?= $unit ? ' ' . e($unit) : '' ?> left
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Row subtotal -->
                    <div class="cart-item-row__subtotal" data-row-subtotal>
                        <?php if ($isBlocking): ?>
                        <span class="cart-item-subtotal-na">—</span>
                        <?php else: ?>
                        <?= cartFmt($subtotalItem) ?>
                        <?php endif; ?>
                    </div>

                    <!-- Remove button -->
                    <button type="button"
                            class="cart-item-row__remove"
                            data-remove-item
                            aria-label="Remove <?= e($item['product_name'] ?? 'item') ?> from cart">
                        <i class="bi bi-trash3" aria-hidden="true"></i>
                    </button>

                    <!-- Loading overlay (per row, shown during AJAX) -->
                    <div class="cart-item-row__loader" aria-hidden="true">
                        <div class="c-spinner c-spinner-sm"></div>
                    </div>

                </div><!-- /.cart-item-row -->
                <?php endforeach; ?>
            </div><!-- /#cartItemsList -->

            <!-- Cart actions row -->
            <div class="cart-actions-row">
                <button type="button" class="c-btn c-btn-ghost c-btn-sm cart-clear-btn" id="cartClearBtn">
                    <i class="bi bi-trash3" aria-hidden="true"></i>
                    Clear Cart
                </button>
            </div>

        </div><!-- /.cart-items-col -->

        <!-- ── Right: Order summary sidebar ───────────── -->
        <aside class="cart-summary-col" aria-label="Order summary">
            <div class="cart-summary-card" id="cartSummaryCard">

                <h2 class="cart-summary-card__title">Order Summary</h2>

                <!-- Summary rows -->
                <div class="cart-summary-rows" id="cartSummaryRows">
                    <div class="cart-summary-row">
                        <span>Items <span class="cart-summary-count" id="cartSummaryCount">(<?= $distinctCount ?>)</span></span>
                        <span id="cartSummarySubtotal"><?= cartFmt($subtotal) ?></span>
                    </div>
                    <div class="cart-summary-row">
                        <span>Subtotal</span>
                        <span><?= cartFmt($subtotal) ?></span>
                    </div>
                </div>

                <!-- Total -->
                <div class="cart-summary-total" id="cartSummaryTotal">
                    <span>Total</span>
                    <span id="cartSummaryTotalAmt"><?= cartFmt($subtotal) ?></span>
                </div>

                <!-- Pickup shop card -->
                <?php if ($shopId && $shopName): ?>
                <div class="cart-summary-shop">
                    <div class="cart-summary-shop__icon" aria-hidden="true">
                        <i class="bi bi-shop"></i>
                    </div>
                    <div class="cart-summary-shop__info">
                        <div class="cart-summary-shop__label">Pickup Shop</div>
                        <div class="cart-summary-shop__name"><?= e($shopName) ?></div>
                        <?php if (!empty($shop['location'])): ?>
                        <div class="cart-summary-shop__location">
                            <?= e($shop['location']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <button type="button"
                            class="cart-summary-shop__change"
                            id="cartChangeShopSummary"
                            aria-label="Change pickup shop">
                        Change Shop
                    </button>
                </div>
                <?php endif; ?>

                <!-- Issues warning -->
                <?php if ($hasIssues): ?>
                <div class="cart-summary-issues-alert" id="cartSummaryIssuesAlert" role="alert">
                    <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                    Some items need attention before checkout.
                </div>
                <?php endif; ?>

                <!-- Checkout CTA -->
                <div class="cart-summary-cta">
                    <button type="button"
                            class="c-btn c-btn-primary c-btn-lg cart-checkout-btn"
                            id="cartCheckoutBtn"
                            <?= ($hasIssues || !$shopId) ? 'disabled aria-disabled="true"' : '' ?>>
                        <i class="bi bi-bag-check-fill" aria-hidden="true"></i>
                        Proceed to Checkout
                    </button>

                    <!-- Secure message -->
                    <div class="cart-secure-msg">
                        <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                        <span>Secure checkout &bull; Your data is safe</span>
                    </div>
                </div>

                <!-- Pickup availability notice -->
                <?php if ($shopId && $shopName && !$hasIssues): ?>
                <div class="cart-pickup-notice">
                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                    <span>All items are available for pickup from <strong><?= e($shopName) ?></strong>.</span>
                </div>
                <?php elseif ($shopId && $hasIssues): ?>
                <div class="cart-pickup-notice cart-pickup-notice--warn">
                    <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                    <span>Some items may not be available at <strong><?= e($shopName) ?></strong>.</span>
                </div>
                <?php endif; ?>

                <!-- Trust icons -->
                <div class="cart-trust-icons">
                    <div class="cart-trust-icon">
                        <i class="bi bi-shield-check" aria-hidden="true"></i>
                        <span>Secure Payment</span>
                    </div>
                    <div class="cart-trust-icon">
                        <i class="bi bi-shop" aria-hidden="true"></i>
                        <span>Pickup Only</span>
                    </div>
                    <div class="cart-trust-icon">
                        <i class="bi bi-patch-check" aria-hidden="true"></i>
                        <span>Quality Assured</span>
                    </div>
                    <div class="cart-trust-icon">
                        <i class="bi bi-tree" aria-hidden="true"></i>
                        <span>Fresh from Farms</span>
                    </div>
                </div>

            </div><!-- /.cart-summary-card -->
        </aside>

    </div><!-- /.cart-layout -->

    <?php endif; // end cart has items ?>

</div><!-- /.cart-page-container -->

<!-- ══════════════════════════════════════════════════════════
     YOU MAY ALSO LIKE — Recommended Products
     ══════════════════════════════════════════════════════════ -->
<?php if (!empty($recommended)): ?>
<section class="cart-recommend-section c-section-bg-alt" aria-labelledby="cartRecommendTitle">
    <div class="c-container">

        <div class="cart-recommend-header">
            <h2 class="cart-recommend-title" id="cartRecommendTitle">You may also like</h2>
        </div>

        <div class="cart-recommend-slider-wrap">
            <button class="cart-recommend-arrow cart-recommend-arrow--prev"
                    id="cartRecommendPrev"
                    aria-label="Previous products">
                <i class="bi bi-chevron-left" aria-hidden="true"></i>
            </button>

            <div class="cart-recommend-track-outer">
                <div class="cart-recommend-track" id="cartRecommendTrack" role="list" aria-label="Recommended products">
                    <?php foreach ($recommended as $rp):
                        $rpId    = (int)$rp['id'];
                        $rpName  = e($rp['product_name'] ?? '');
                        $rpPrice = (float)($rp['selling_price'] ?? 0);
                        $rpUnit  = e($rp['unit_abbreviation'] ?? '');
                        $rpImg   = $rp['primary_image'] ?? null;
                        $rpAvail = (float)($rp['available_quantity'] ?? 0);
                        $rpCat   = e($rp['category_name'] ?? '');
                    ?>
                    <div class="cart-rec-card" role="listitem">
                        <a href="<?= $view->url('customer/products/' . $rpId . ($shopId ? '?shop_id=' . $shopId : '')) ?>"
                           class="cart-rec-card__img-link"
                           tabindex="-1"
                           aria-hidden="true">
                            <?php if ($rpImg): ?>
                            <img src="<?= $view->url(e($rp['primary_image'])) ?>"
                                 alt="<?= $rpName ?>"
                                 loading="lazy"
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                            <div class="cart-rec-card__img-placeholder" style="display:none;" aria-hidden="true">📦</div>
                            <?php else: ?>
                            <div class="cart-rec-card__img-placeholder" aria-hidden="true">📦</div>
                            <?php endif; ?>
                        </a>
                        <div class="cart-rec-card__body">
                            <?php if ($rpCat): ?>
                            <div class="cart-rec-card__cat"><?= $rpCat ?></div>
                            <?php endif; ?>
                            <div class="cart-rec-card__name">
                                <a href="<?= $view->url('customer/products/' . $rpId . ($shopId ? '?shop_id=' . $shopId : '')) ?>">
                                    <?= $rpName ?>
                                </a>
                            </div>
                            <div class="cart-rec-card__price">
                                ETB <?= number_format($rpPrice, 2) ?>
                                <?php if ($rpUnit): ?>
                                <span class="cart-rec-card__unit">/ <?= $rpUnit ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="cart-rec-card__footer">
                            <button type="button"
                                    class="cart-rec-card__add-btn"
                                    data-ca-add-to-cart
                                    data-product-id="<?= $rpId ?>"
                                    data-product-name="<?= $rpName ?>"
                                    data-product-price="<?= e($rpPrice) ?>"
                                    data-unit="<?= $rpUnit ?>"
                                    data-max-qty="<?= (int)$rpAvail ?>"
                                    aria-label="Add <?= $rpName ?> to cart"
                                    <?= $rpAvail <= 0 ? 'disabled aria-disabled="true"' : '' ?>>
                                <i class="bi bi-cart-plus" aria-hidden="true"></i>
                                <?= $rpAvail <= 0 ? 'Out of Stock' : 'Add to Cart' ?>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <button class="cart-recommend-arrow cart-recommend-arrow--next"
                    id="cartRecommendNext"
                    aria-label="Next products">
                <i class="bi bi-chevron-right" aria-hidden="true"></i>
            </button>
        </div>

    </div>
</section>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════
     TRUST FEATURE STRIP
     ══════════════════════════════════════════════════════════ -->
<div class="cart-feature-strip">
    <div class="c-container cart-feature-strip__inner">
        <div class="cart-feature-item">
            <i class="bi bi-tree-fill cart-feature-item__icon" aria-hidden="true"></i>
            <div>
                <div class="cart-feature-item__title">From Our Farms</div>
                <div class="cart-feature-item__desc">Quality products grown by Arsi University farms</div>
            </div>
        </div>
        <div class="cart-feature-item">
            <i class="bi bi-patch-check-fill cart-feature-item__icon" aria-hidden="true"></i>
            <div>
                <div class="cart-feature-item__title">Fresh &amp; Healthy</div>
                <div class="cart-feature-item__desc">100% natural and chemical free</div>
            </div>
        </div>
        <div class="cart-feature-item">
            <i class="bi bi-shop cart-feature-item__icon" aria-hidden="true"></i>
            <div>
                <div class="cart-feature-item__title">Easy Pickup</div>
                <div class="cart-feature-item__desc">Pick up from your selected shop</div>
            </div>
        </div>
        <div class="cart-feature-item">
            <i class="bi bi-shield-lock-fill cart-feature-item__icon" aria-hidden="true"></i>
            <div>
                <div class="cart-feature-item__title">Safe Payment</div>
                <div class="cart-feature-item__desc">Secure mobile banking payments</div>
            </div>
        </div>
        <div class="cart-feature-item">
            <i class="bi bi-award-fill cart-feature-item__icon" aria-hidden="true"></i>
            <div>
                <div class="cart-feature-item__title">Trusted Source</div>
                <div class="cart-feature-item__desc">Verified and trusted by thousands of customers</div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     FOOTER
     ══════════════════════════════════════════════════════════ -->
<footer class="ch-footer" role="contentinfo">
    <div class="c-container">
        <div class="ch-footer__grid">

            <!-- Brand col -->
            <div class="ch-footer__brand-col">
                <a href="<?= $view->url('customer') ?>" class="ch-footer__brand" aria-label="AU-AgroSale home">
                    <div class="customer-navbar__logo" style="width:32px;height:32px;font-size:15px;" aria-hidden="true">🌿</div>
                    <div>
                        <div class="ch-footer__brand-name">AU-AgroSale</div>
                        <div class="ch-footer__brand-sub">Arsi University</div>
                    </div>
                </a>
                <p class="ch-footer__about">
                    Quality agricultural products directly from Arsi University farms to your table.
                </p>
                <div class="ch-footer__social" role="list" aria-label="Social links">
                    <a href="#" class="ch-footer__social-link" aria-label="Facebook" role="listitem"><i class="bi bi-facebook" aria-hidden="true"></i></a>
                    <a href="#" class="ch-footer__social-link" aria-label="Instagram" role="listitem"><i class="bi bi-instagram" aria-hidden="true"></i></a>
                    <a href="#" class="ch-footer__social-link" aria-label="Telegram" role="listitem"><i class="bi bi-telegram" aria-hidden="true"></i></a>
                    <a href="#" class="ch-footer__social-link" aria-label="WhatsApp" role="listitem"><i class="bi bi-whatsapp" aria-hidden="true"></i></a>
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
                    <li><a href="#">About Us</a></li>
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
                    <?php foreach (array_slice($shops, 0, 5) as $s): ?>
                    <li>
                        <a href="<?= $view->url('customer/shops/' . (int)$s['id'] . '/availability') ?>">
                            <?= e($s['shop_name'] ?? $s['name'] ?? '') ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                    <li><a href="<?= $view->url('customer/shops') ?>">All Shop Locations</a></li>
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

                <div class="ch-footer__newsletter" style="margin-top:var(--c-space-5);">
                    <div class="ch-footer__nav-heading">Stay Updated</div>
                    <p class="ch-footer__newsletter-desc">Subscribe to get updates on new products and offers.</p>
                    <form class="ch-footer__subscribe-form" action="#" method="post" onsubmit="return false;" aria-label="Newsletter subscription">
                        <input type="email" class="ch-footer__subscribe-input" placeholder="Enter your email" aria-label="Email address">
                        <button type="submit" class="ch-footer__subscribe-btn">Subscribe</button>
                    </form>
                </div>
            </div>

        </div><!-- /.ch-footer__grid -->

        <div class="ch-footer__bottom">
            <p class="ch-footer__copyright">&copy; <?= date('Y') ?> AU-AgroSale. All rights reserved.</p>
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

<!-- ══════════════════════════════════════════════════════════
     CHANGE SHOP MODAL
     ══════════════════════════════════════════════════════════ -->
<div id="cartChangeShopModal" class="customer-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="changeShopModalTitle" aria-hidden="true">
    <div class="customer-modal" style="max-width:480px;">
        <div class="customer-modal__header">
            <h3 class="customer-modal__title" id="changeShopModalTitle">
                <i class="bi bi-shop" aria-hidden="true"></i>
                Change Shop
            </h3>
            <button type="button" class="customer-modal__close" data-modal-close aria-label="Close">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>
        <div class="customer-modal__body">
            <!-- Step 1: shop picker -->
            <div id="changeShopStep1">
                <p class="cart-modal-desc">
                    Select a new pickup shop. If your cart contains items unavailable at the new shop, you will be asked to confirm before proceeding.
                </p>
                <div class="cart-modal-shop-list" id="changeShopList" role="listbox" aria-label="Available shops">
                    <?php foreach ($shops as $s):
                        $sid = (int)$s['id'];
                        $sName = e($s['shop_name'] ?? $s['name'] ?? '');
                        $isSelected = $sid === $shopId;
                    ?>
                    <button type="button"
                            class="cart-modal-shop-option <?= $isSelected ? 'selected' : '' ?>"
                            data-shop-id="<?= $sid ?>"
                            data-shop-name="<?= $sName ?>"
                            role="option"
                            aria-selected="<?= $isSelected ? 'true' : 'false' ?>">
                        <div class="cart-modal-shop-option__icon" aria-hidden="true">
                            <i class="bi bi-<?= $isSelected ? 'check-circle-fill' : 'shop' ?>"></i>
                        </div>
                        <div class="cart-modal-shop-option__info">
                            <div class="cart-modal-shop-option__name"><?= $sName ?></div>
                            <?php if (!empty($s['location'])): ?>
                            <div class="cart-modal-shop-option__loc">
                                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                                <?= e($s['location']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="cart-modal-shop-option__count">
                            <?= (int)($s['product_count'] ?? 0) ?> products
                        </div>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Step 2: conflict warning (filled by JS) -->
            <div id="changeShopStep2" style="display:none;">
                <div class="cart-modal-conflict-banner" id="changeShopConflictBanner">
                    <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                    <strong id="changeShopConflictCount">0 items</strong> are not available at <span id="changeShopConflictName"></span>.
                </div>
                <div id="changeShopUnavailableList" class="cart-modal-unavailable-list"></div>
                <p class="cart-modal-conflict-note">These items will be removed from your cart if you continue.</p>
            </div>

            <!-- Loading spinner -->
            <div id="changeShopLoading" class="cart-modal-loading" style="display:none;" aria-live="polite">
                <div class="c-spinner"></div>
                <span>Checking availability…</span>
            </div>
        </div>
        <div class="customer-modal__footer" id="changeShopFooter">
            <button type="button" class="c-btn c-btn-ghost" data-modal-close>
                Keep Current Shop
            </button>
            <button type="button" class="c-btn c-btn-primary" id="changeShopConfirmBtn" style="display:none;">
                <i class="bi bi-check-lg" aria-hidden="true"></i>
                Change Shop
            </button>
        </div>
    </div>
</div>

<?php $view->section('scripts'); ?>
<script>
(function () {
    'use strict';
    window.CartPageState = {
        shopId:    <?= (int)$shopId ?>,
        shopName:  <?= json_encode($shopName) ?>,
        hasIssues: <?= $hasIssues ? 'true' : 'false' ?>,
        csrfToken: <?= json_encode($csrfToken ?? '') ?>,
        appUrl:    <?= json_encode(rtrim((string)\AAM\Config\Application::get('url', ''), '/')) ?>
    };
}());
</script>
<script>
/**
 * Cart page — shop session auto-sync.
 *
 * Problem: User selects a shop via the navbar selector (stored in localStorage
 * AND synced to PHP session via AJAX). But if the session sync was missed
 * (AJAX failure, session expired, cross-tab etc.) the cart page renders with
 * shopId=0 and shows "No shop selected" even though a shop IS selected in
 * localStorage.
 *
 * Fix: On cart page load, if the server sent shopId=0 but localStorage has a
 * valid shop, silently re-persist it to the session, then reload the page so
 * CartController::index() can render the correct shop-aware cart.
 */
(function () {
    'use strict';
    // Only run when the server thinks no shop is selected
    if (window.CartPageState && window.CartPageState.shopId) return;

    // Guard against infinite reload loop (in case select endpoint keeps failing)
    var _reloadKey = 'ca_cart_shop_sync';
    try {
        if (sessionStorage.getItem(_reloadKey) === '1') {
            sessionStorage.removeItem(_reloadKey);
            return; // already tried once this tab session — don't loop
        }
    } catch (_) {}

    var CA = window.CustomerApp || {};
    var appUrl   = (CA.appUrl || '').replace(/\/$/, '');
    var csrf     = CA.csrfToken
        || (document.querySelector('meta[name="csrf-token"]') || {}).content
        || '';

    // Read from localStorage (same key ShopSelector uses)
    var shopData = null;
    try {
        var raw = localStorage.getItem('ca_selected_shop');
        if (raw) shopData = JSON.parse(raw);
    } catch (_) {}

    if (!shopData || !shopData.id) return;  // nothing stored either — genuine "no shop"

    // We have a shop in localStorage but session doesn't know — re-sync silently
    fetch(appUrl + '/customer/shops/select', {
        method:  'POST',
        headers: {
            'Content-Type':     'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'csrf_token=' + encodeURIComponent(csrf)
            + '&shop_id='   + encodeURIComponent(shopData.id)
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        if (res.success) {
            // Mark that we are about to reload so guard above can catch a second pass
            try { sessionStorage.setItem(_reloadKey, '1'); } catch (_) {}
            // Session is now up-to-date — reload so the cart renders with shop info
            window.location.reload();
        }
    })
    .catch(function () {
        // Network failure — do nothing; user can still use "Change shop" on the page
    });
}());
</script>
<script src="<?= $view->url('public/assets/customer/js/cart-page.js') ?>?v=<?= file_exists(PUBLIC_PATH . '/assets/customer/js/cart-page.js') ? filemtime(PUBLIC_PATH . '/assets/customer/js/cart-page.js') : '1' ?>"></script>

<!-- Recommended products slider -->
<?php if (!empty($recommended)): ?>
<script>
(function () {
    'use strict';
    var track   = document.getElementById('cartRecommendTrack');
    var prevBtn = document.getElementById('cartRecommendPrev');
    var nextBtn = document.getElementById('cartRecommendNext');
    if (!track || !prevBtn || !nextBtn) return;

    var CARD_W   = 200;   // approximate card width + gap
    var SCROLL_BY = CARD_W * 3;

    prevBtn.addEventListener('click', function () {
        track.parentElement.scrollBy({ left: -SCROLL_BY, behavior: 'smooth' });
    });
    nextBtn.addEventListener('click', function () {
        track.parentElement.scrollBy({ left: SCROLL_BY, behavior: 'smooth' });
    });
}());
</script>
<?php endif; ?>
<?php $view->endSection(); ?>
