<?php
/**
 * =========================================================
 * AU-AgroSale — Customer Checkout Page
 * modules/customer/views/checkout/index.php
 * =========================================================
 * Phase 6 — Premium 2-column checkout UI matching the
 * AU-AgroSale reference design.
 *
 * @var int    $shopId
 * @var string $shopName
 * @var array  $shop
 * @var array  $cart
 * @var float  $subtotal
 * @var int    $itemCount
 * @var int    $distinctCount
 * @var array  $paymentMethods   Active mobile banking methods
 * @var string $customerName
 * @var string $customerPhone
 * @var string $customerEmail
 * @var array  $warnings
 * @var bool   $hasIssues
 * @var \AAM\Core\View $view
 */
declare(strict_types=1);

$cart           = $cart           ?? [];
$subtotal       = (float)($subtotal ?? 0);
$itemCount      = (int)($itemCount  ?? 0);
$distinctCount  = (int)($distinctCount ?? count($cart));
$paymentMethods = $paymentMethods ?? [];
$customerName   = $customerName ?? '';
$customerPhone  = $customerPhone  ?? '';
$customerEmail  = $customerEmail  ?? '';
$warnings       = $warnings       ?? [];
$hasIssues      = (bool)($hasIssues ?? false);
$firstMethod    = !empty($paymentMethods) ? $paymentMethods[0] : null;

// Determine if cash is active
$hasCash = false;
foreach ($paymentMethods as $pm) {
    if (($pm['type'] ?? '') === 'cash' || ($pm['code'] ?? '') === 'cash') {
        $hasCash = true;
        break;
    }
}

$shopLocation = $shop['location'] ?? '';
$shopAddress  = $shop['address']  ?? '';
?>
<?php $view->section('styles'); ?>
<link rel="stylesheet" href="<?= $view->url('public/assets/customer/css/checkout.css') ?>?v=<?= filemtime(PUBLIC_PATH . '/assets/customer/css/checkout.css') ?>">
<?php $view->endSection(); ?>

<!-- ══════════════════════════════════════════════════════════
     ANNOUNCE BAR
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

<!-- ── Breadcrumb toolbar ───────────────────────────────────── -->
<div class="cp-toolbar">
    <div class="c-container cp-toolbar__inner">
        <a href="<?= $view->url('customer/cart') ?>"
           class="c-back-nav"
           aria-label="Back to Cart">
            <i class="bi bi-arrow-left c-back-nav__icon" aria-hidden="true"></i>
            <span class="c-back-nav__label">Back to Cart</span>
        </a>
        <nav class="cp-breadcrumb" aria-label="Breadcrumb">
            <a href="<?= $view->url('customer') ?>">Home</a>
            <span class="cp-breadcrumb__sep" aria-hidden="true">›</span>
            <a href="<?= $view->url('customer/cart') ?>">Cart</a>
            <span class="cp-breadcrumb__sep" aria-hidden="true">›</span>
            <span aria-current="page">Checkout</span>
        </nav>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MAIN CHECKOUT CONTENT
     ══════════════════════════════════════════════════════════ -->
<div class="c-container cko-wrapper" id="customer-main">

    <!-- Page title -->
    <div class="cko-page-header">
        <h1 class="cko-page-title">Checkout</h1>
        <p class="cko-page-subtitle">Review your order, provide your details and choose a payment method.</p>
    </div>

    <!-- ── Flash messages from controller ── -->
    <?php
    $flash = \AAM\Core\SessionManager::get(SESSION_FLASH);
    if ($flash):
        \AAM\Core\SessionManager::delete(SESSION_FLASH);
        $fType    = $flash['type']    ?? 'info';
        $fMsg     = $flash['message'] ?? '';
        $fVariant = match($fType) {
            FLASH_ERROR   => 'error',
            FLASH_WARNING => 'warning',
            FLASH_SUCCESS => 'success',
            default       => 'info',
        };
        $fIcon = match($fType) {
            FLASH_ERROR   => 'bi-x-circle-fill',
            FLASH_WARNING => 'bi-exclamation-triangle-fill',
            FLASH_SUCCESS => 'bi-check-circle-fill',
            default       => 'bi-info-circle-fill',
        };
    ?>
    <div class="customer-alert customer-alert--<?= $fVariant ?> cko-flash-alert"
         role="alert" aria-live="assertive">
        <i class="bi <?= $fIcon ?> customer-alert__icon" aria-hidden="true"></i>
        <div class="customer-alert__body"><?= e($fMsg) ?></div>
    </div>
    <?php endif; ?>

    <!-- Cart issues -->
    <?php if ($hasIssues): ?>
    <div class="customer-alert customer-alert--warning cko-flash-alert" role="alert">
        <i class="bi bi-exclamation-triangle-fill customer-alert__icon" aria-hidden="true"></i>
        <div class="customer-alert__body">
            <div class="customer-alert__title">Cart issues detected</div>
            Some items need attention.
            <a href="<?= $view->url('customer/cart') ?>" class="cko-alert-link">Review cart →</a>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST"
          action="<?= $view->url('customer/checkout/place') ?>"
          id="checkoutForm"
          novalidate
          autocomplete="on">
        <?= csrf_field() ?>
        <input type="hidden" name="shop_id" value="<?= (int)$shopId ?>">

        <!-- 2-column layout: form left, summary right -->
        <div class="cko-layout">

            <!-- ════════════════════════════════════════════
                 LEFT COLUMN — Steps
                 ════════════════════════════════════════════ -->
            <div class="cko-steps" role="main">

                <!-- ── STEP 1: Pickup Information ─────────── -->
                <section class="cko-step" aria-labelledby="cko-step1-title">
                    <div class="cko-step__header">
                        <span class="cko-step__num" aria-hidden="true">
                            <i class="bi bi-geo-alt-fill"></i>
                        </span>
                        <h2 class="cko-step__title" id="cko-step1-title">1. Pickup Information</h2>
                    </div>

                    <div class="cko-step__body">
                        <div class="cko-shop-card">
                            <div class="cko-shop-card__icon" aria-hidden="true">
                                <i class="bi bi-shop"></i>
                            </div>
                            <div class="cko-shop-card__info">
                                <div class="cko-shop-card__name"><?= e($shopName) ?></div>
                                <?php if ($shopLocation || $shopAddress): ?>
                                <div class="cko-shop-card__location">
                                    <i class="bi bi-pin-map-fill" aria-hidden="true"></i>
                                    <?= e($shopLocation ?: $shopAddress) ?>
                                </div>
                                <?php endif; ?>
                                <div class="cko-shop-card__pickup-label">
                                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                                    Pickup only from this shop
                                </div>
                            </div>
                            <a href="<?= $view->url('customer/cart') ?>"
                               class="cko-shop-card__change"
                               aria-label="Change pickup shop">
                                Change Shop
                            </a>
                        </div>
                    </div>
                </section>

                <!-- ── STEP 2: Customer Information ──────── -->
                <section class="cko-step" aria-labelledby="cko-step2-title">
                    <div class="cko-step__header">
                        <span class="cko-step__num" aria-hidden="true">
                            <i class="bi bi-person-fill"></i>
                        </span>
                        <div>
                            <h2 class="cko-step__title" id="cko-step2-title">2. Customer Information</h2>
                            <p class="cko-step__subtitle">Your contact details for order updates and notifications.</p>
                        </div>
                    </div>

                    <div class="cko-step__body">
                        <div class="cko-fields-grid">
                            <div class="cko-field">
                                <label for="co_customer_name" class="cko-label">
                                    Full Name <span class="cko-required" aria-label="required">*</span>
                                </label>
                                <input type="text"
                                       id="co_customer_name"
                                       name="customer_name"
                                       class="co-input cko-input"
                                       required
                                       placeholder="Enter your full name"
                                       value="<?= e($customerName) ?>"
                                       autocomplete="name"
                                       maxlength="200"
                                       aria-required="true">
                            </div>
                            <div class="cko-field">
                                <label for="co_customer_phone" class="cko-label">Phone Number</label>
                                <input type="tel"
                                       id="co_customer_phone"
                                       name="customer_phone"
                                       class="co-input cko-input"
                                       placeholder="+251 911 234567"
                                       value="<?= e($customerPhone) ?>"
                                       autocomplete="tel"
                                       maxlength="30">
                            </div>
                        </div>
                        <div class="cko-field cko-field--full">
                            <label for="co_customer_email" class="cko-label">
                                Email Address
                            </label>
                            <input type="email"
                                   id="co_customer_email"
                                   name="customer_email"
                                   class="co-input cko-input"
                                   placeholder="tomas.gosaye@gmail.com"
                                   value="<?= e($customerEmail) ?>"
                                   autocomplete="email"
                                   maxlength="200">
                        </div>
                        <label class="cko-remember-label" for="cko_remember">
                            <input type="checkbox" id="cko_remember" class="cko-checkbox">
                            <span>Use this information for my next orders</span>
                        </label>
                    </div>
                </section>

                <!-- ── STEP 3: Payment Method ─────────────── -->
                <section class="cko-step" aria-labelledby="cko-step3-title">
                    <div class="cko-step__header">
                        <span class="cko-step__num" aria-hidden="true">
                            <i class="bi bi-credit-card-fill"></i>
                        </span>
                        <div>
                            <h2 class="cko-step__title" id="cko-step3-title">3. Payment Method</h2>
                            <p class="cko-step__subtitle">Choose your preferred payment method.</p>
                        </div>
                    </div>

                    <div class="cko-step__body">
                        <?php if (empty($paymentMethods)): ?>
                        <div class="customer-alert customer-alert--warning" role="alert">
                            <i class="bi bi-exclamation-triangle-fill customer-alert__icon" aria-hidden="true"></i>
                            <div class="customer-alert__body">
                                No payment methods are currently active. Please contact the shop or try again later.
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="cko-payment-list" role="radiogroup" aria-label="Payment method selection">
                            <?php foreach ($paymentMethods as $idx => $pm):
                                $pmId   = (int)$pm['id'];
                                $pmCode = $pm['code'] ?? '';
                                $pmType = $pm['type'] ?? '';
                                $pmName = $pm['display_name'] ?? $pm['name'] ?? '';
                                $pmDesc = $pm['description'] ?? '';
                                $isFirst = $idx === 0;

                                // Choose icon
                                if ($pmCode === 'cbe_birr') {
                                    $icon = 'bi-bank2';
                                } elseif ($pmCode === 'telebirr') {
                                    $icon = 'bi-phone-fill';
                                } elseif ($pmType === 'cash') {
                                    $icon = 'bi-cash-coin';
                                } else {
                                    $icon = 'bi-wallet2';
                                }

                                // Description fallback
                                if (!$pmDesc && $pmType === 'cash') {
                                    $pmDesc = 'Pay with cash when you pick up your order at the shop.';
                                } elseif (!$pmDesc && $pmType === 'mobile_banking') {
                                    $pmDesc = 'Pay securely using mobile banking. You will get a QR code after placing the order.';
                                }
                            ?>
                            <label class="cko-pm-card<?= $isFirst ? ' cko-pm-card--selected' : '' ?>"
                                   for="pm_<?= $pmId ?>"
                                   data-pm-label>
                                <input type="radio"
                                       id="pm_<?= $pmId ?>"
                                       name="payment_method_id"
                                       value="<?= $pmId ?>"
                                       class="cko-pm-radio"
                                       <?= $isFirst ? 'checked' : '' ?>
                                       required
                                       aria-describedby="pm_desc_<?= $pmId ?>">
                                <!-- Radio ring -->
                                <span class="cko-pm-radio-ui" aria-hidden="true">
                                    <span class="cko-pm-radio-dot"></span>
                                </span>
                                <!-- Icon -->
                                <span class="cko-pm-icon" aria-hidden="true">
                                    <i class="bi <?= $icon ?>"></i>
                                </span>
                                <!-- Label + description -->
                                <span class="cko-pm-text">
                                    <strong class="cko-pm-name"><?= e($pmName) ?></strong>
                                    <span class="cko-pm-desc" id="pm_desc_<?= $pmId ?>"><?= e($pmDesc) ?></span>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>

                        <!-- Secure payment notice -->
                        <div class="cko-secure-note">
                            <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                            <span>Secure Checkout — Your payment information is safe with us. We do not store your payment details.</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- ── STEP 4: Order Notes ────────────────── -->
                <section class="cko-step cko-step--notes" aria-labelledby="cko-step4-title">
                    <div class="cko-step__header">
                        <span class="cko-step__num cko-step__num--muted" aria-hidden="true">
                            <i class="bi bi-pencil"></i>
                        </span>
                        <div>
                            <h2 class="cko-step__title" id="cko-step4-title">4. Order Notes <span class="cko-optional-tag">(Optional)</span></h2>
                            <p class="cko-step__subtitle">Add any special instructions for the shop (optional).</p>
                        </div>
                    </div>
                    <div class="cko-step__body">
                        <div class="cko-field cko-field--full">
                            <textarea id="cko_notes"
                                      name="notes"
                                      class="co-input cko-input cko-textarea"
                                      rows="3"
                                      maxlength="250"
                                      placeholder="E.g. Please prepare items before my arrival..."
                                      aria-describedby="cko_notes_count"></textarea>
                            <div class="cko-notes-count">
                                <span id="cko_notes_count">0</span> / 250
                            </div>
                        </div>
                    </div>
                </section>

            </div><!-- /.cko-steps -->

            <!-- ════════════════════════════════════════════
                 RIGHT COLUMN — Order Summary (sticky)
                 ════════════════════════════════════════════ -->
            <aside class="cko-summary" aria-label="Order summary" id="ckoSummaryCard">

                <div class="cko-summary__inner">

                    <h2 class="cko-summary__title">Order Summary</h2>

                    <!-- Items header -->
                    <div class="cko-summary-items-header">
                        <span class="cko-summary-items-count">Items (<?= $distinctCount ?>)</span>
                        <a href="<?= $view->url('customer/cart') ?>" class="cko-summary-edit-cart">
                            Edit Cart
                        </a>
                    </div>

                    <!-- Product list -->
                    <ul class="cko-summary-products" role="list" aria-label="Ordered products">
                        <?php foreach ($cart as $item):
                            $pImg = $item['primary_image'] ?? null;
                            $pName = $item['product_name'] ?? '';
                            $pQty  = (float)$item['quantity'];
                            $pPrice= (float)$item['price'];
                            $pSub  = round($pQty * $pPrice, 2);
                            $pUnit = $item['unit_name'] ?? ($item['unit'] ?? '');
                            // Display measurement: qty + unit_name
                            $pMeasure = '';
                            if (!empty($item['unit_name'])) {
                                $pMeasure = ($pQty == (int)$pQty ? (int)$pQty : $pQty) . ' ' . $item['unit_name'];
                            } elseif (!empty($item['unit'])) {
                                $pMeasure = ($pQty == (int)$pQty ? (int)$pQty : $pQty) . ' ' . $item['unit'];
                            }
                            $isBlocking = in_array($item['status'] ?? 'ok', ['unavailable','out_of_stock'], true);
                        ?>
                        <li class="cko-summary-product <?= $isBlocking ? 'cko-summary-product--issue' : '' ?>" role="listitem">
                            <div class="cko-summary-product__img" aria-hidden="true">
                                <?php if ($pImg): ?>
                                <img src="<?= $view->url(e($pImg)) ?>"
                                     alt="<?= e($pName) ?>"
                                     loading="lazy"
                                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                <div class="cko-summary-product__img-placeholder" style="display:none;">📦</div>
                                <?php else: ?>
                                <div class="cko-summary-product__img-placeholder">📦</div>
                                <?php endif; ?>
                                <span class="cko-summary-product__qty-badge" aria-label="Quantity: <?= $pQty ?>">
                                    <?= $pQty == (int)$pQty ? (int)$pQty : $pQty ?>
                                </span>
                            </div>
                            <div class="cko-summary-product__info">
                                <div class="cko-summary-product__name"><?= e($pName) ?></div>
                                <?php if ($pMeasure): ?>
                                <div class="cko-summary-product__unit"><?= e($pMeasure) ?></div>
                                <?php endif; ?>
                                <div class="cko-summary-product__qty-label">Qty: <?= $pQty == (int)$pQty ? (int)$pQty : $pQty ?></div>
                            </div>
                            <div class="cko-summary-product__price">
                                <?php if ($isBlocking): ?>
                                <span class="cko-summary-product__price--unavail">—</span>
                                <?php else: ?>
                                ETB <?= number_format($pSub, 2) ?>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <!-- Divider -->
                    <hr class="cko-summary-divider" aria-hidden="true">

                    <!-- Subtotal row -->
                    <div class="cko-summary-row">
                        <span>Subtotal</span>
                        <span id="ckoSubtotal">ETB <?= number_format($subtotal, 2) ?></span>
                    </div>

                    <!-- Total row -->
                    <div class="cko-summary-total">
                        <span>Total</span>
                        <strong id="ckoTotal">ETB <?= number_format($subtotal, 2) ?></strong>
                    </div>

                    <!-- Pickup shop row -->
                    <div class="cko-summary-shop">
                        <div class="cko-summary-shop__icon" aria-hidden="true">
                            <i class="bi bi-shop"></i>
                        </div>
                        <div class="cko-summary-shop__info">
                            <div class="cko-summary-shop__label">Pickup from</div>
                            <div class="cko-summary-shop__name"><?= e($shopName) ?></div>
                            <?php if ($shopLocation): ?>
                            <div class="cko-summary-shop__location"><?= e($shopLocation) ?></div>
                            <?php endif; ?>
                        </div>
                        <a href="<?= $view->url('customer/cart') ?>"
                           class="cko-summary-shop__change"
                           aria-label="Change pickup shop">
                            Change Shop
                        </a>
                    </div>

                    <!-- Place Order button -->
                    <?php if (!empty($paymentMethods)): ?>
                    <button type="submit"
                            id="coPlaceOrderBtn"
                            class="c-btn c-btn-primary cko-place-btn"
                            <?= ($hasIssues) ? 'disabled aria-disabled="true"' : '' ?>>
                        <span class="cko-place-btn__text">
                            <i class="bi bi-lock-fill" aria-hidden="true"></i>
                            Place Order &amp; Pay
                        </span>
                        <span class="cko-place-btn__spinner" hidden aria-hidden="true">
                            <span class="c-spinner c-spinner-sm"></span>
                            Processing…
                        </span>
                    </button>
                    <?php else: ?>
                    <button type="button"
                            class="c-btn cko-place-btn"
                            disabled
                            aria-disabled="true"
                            style="background:var(--c-border);color:var(--c-text-muted);cursor:not-allowed;">
                        No Payment Methods Available
                    </button>
                    <?php endif; ?>

                    <!-- Trust row -->
                    <div class="cko-summary-trust">
                        <i class="bi bi-shield-check" aria-hidden="true"></i>
                        <span>Secure checkout</span>
                        <span class="cko-trust-sep" aria-hidden="true">•</span>
                        <span>Your data is safe</span>
                    </div>

                    <!-- Pickup availability notice -->
                    <?php if (!$hasIssues && $shopName): ?>
                    <div class="cko-summary-pickup-notice">
                        <i class="bi bi-bag-check" aria-hidden="true"></i>
                        All items are available for pickup from <?= e($shopName) ?>.
                    </div>
                    <?php elseif ($hasIssues): ?>
                    <div class="cko-summary-pickup-notice cko-summary-pickup-notice--warn">
                        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                        Some items need attention. <a href="<?= $view->url('customer/cart') ?>">Review cart</a>
                    </div>
                    <?php endif; ?>

                </div><!-- /.cko-summary__inner -->
            </aside>

        </div><!-- /.cko-layout -->

    </form>

    <!-- ── YOU MAY ALSO LIKE (recommended from cart page logic) ── -->
    <?php
    // Load up to 8 recommended products at this shop
    // We reuse the cart service recommended products approach
    $recommended = [];
    try {
        $cartProductIds = array_map(fn($item) => (int)$item['product_id'], $cart);
        $productSvc = new \AAM\Customer\Services\CustomerProductService();
        $recommended = $productSvc->getRecommendedProducts($shopId, $cartProductIds, 8);
    } catch (\Throwable $e) {
        // silently skip if error
    }
    ?>
    <?php if (!empty($recommended)): ?>
    <section class="cko-recommendations cart-recommend-section c-section-bg-alt"
             aria-labelledby="ckoRecommendTitle"
             style="margin-top:var(--c-space-12);border-radius:var(--c-radius-xl);padding:var(--c-space-10) 0;">
        <div>
            <div class="cart-recommend-header">
                <h2 class="cart-recommend-title" id="ckoRecommendTitle">You may also like</h2>
            </div>

            <div class="cart-recommend-slider-wrap">
                <button class="cart-recommend-arrow cart-recommend-arrow--prev"
                        id="ckoRecommendPrev"
                        type="button"
                        aria-label="Previous products">
                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                </button>

                <div class="cart-recommend-track-outer">
                    <div class="cart-recommend-track" id="ckoRecommendTrack" role="list" aria-label="Recommended products">
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
                            <a href="<?= $view->url('customer/products/' . $rpId . '?shop_id=' . $shopId) ?>"
                               class="cart-rec-card__img-link"
                               tabindex="-1" aria-hidden="true">
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
                                    <a href="<?= $view->url('customer/products/' . $rpId . '?shop_id=' . $shopId) ?>">
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
                                        data-product-price="<?= $rpPrice ?>"
                                        data-unit="<?= $rpUnit ?>"
                                        data-max-qty="<?= (int)$rpAvail ?>"
                                        aria-label="Add <?= $rpName ?> to cart"
                                        <?= $rpAvail <= 0 ? 'disabled aria-disabled="true"' : '' ?>>
                                    <i class="bi bi-cart-plus" aria-hidden="true"></i>
                                    <?= $rpAvail <= 0 ? 'Out of Stock' : '+ Add to Cart' ?>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button class="cart-recommend-arrow cart-recommend-arrow--next"
                        id="ckoRecommendNext"
                        type="button"
                        aria-label="Next products">
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </section>
    <?php endif; ?>

</div><!-- /.c-container -->

<!-- ── TRUST FEATURE STRIP ────────────────────────────────── -->
<div class="cart-feature-strip" style="margin-top:var(--c-space-12);">
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
     FOOTER (copy from cart page)
     ══════════════════════════════════════════════════════════ -->
<?php
// Get shops for footer — reuse session/already-loaded variable
$footerShops = [];
try {
    $shopSvc = new \AAM\Customer\Services\ShopAvailabilityService();
    $footerShops = $shopSvc->getActiveShops();
} catch (\Throwable $e) {}
?>
<footer class="ch-footer" role="contentinfo">
    <div class="c-container">
        <div class="ch-footer__grid">
            <!-- Brand -->
            <div class="ch-footer__brand-col">
                <a href="<?= $view->url('customer') ?>" class="ch-footer__brand" aria-label="AU-AgroSale home">
                    <div class="customer-navbar__logo" style="width:32px;height:32px;font-size:15px;" aria-hidden="true">🌿</div>
                    <div>
                        <div class="ch-footer__brand-name">AU-AgroSale</div>
                        <div class="ch-footer__brand-sub">Arsi University</div>
                    </div>
                </a>
                <p class="ch-footer__about">Quality agricultural products directly from Arsi University farms to your table.</p>
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
                    <?php foreach (array_slice($footerShops, 0, 5) as $s): ?>
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
                    <li><i class="bi bi-geo-alt-fill" aria-hidden="true"></i>Arsi University, Asella<br>Oromia, Ethiopia</li>
                    <li><i class="bi bi-telephone-fill" aria-hidden="true"></i>+251 911 234567</li>
                    <li><i class="bi bi-envelope-fill" aria-hidden="true"></i>info@au-agrosale.edu.et</li>
                    <li><i class="bi bi-clock-fill" aria-hidden="true"></i>Mon &ndash; Sat: 8:00 AM &ndash; 6:00 PM</li>
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
        </div>
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

<?php $view->section('scripts'); ?>
<script>
window.CheckoutPageState = {
    shopId:    <?= (int)$shopId ?>,
    shopName:  <?= json_encode($shopName) ?>,
    hasIssues: <?= $hasIssues ? 'true' : 'false' ?>,
    csrfToken: <?= json_encode($csrfToken ?? '') ?>,
    appUrl:    <?= json_encode(rtrim((string)\AAM\Config\Application::get('url', ''), '/')) ?>
};
</script>
<script src="<?= $view->url('public/assets/customer/js/checkout.js') ?>?v=<?= filemtime(PUBLIC_PATH . '/assets/customer/js/checkout.js') ?>"></script>
<?php if (!empty($recommended)): ?>
<script>
(function(){
    var track   = document.getElementById('ckoRecommendTrack');
    var prevBtn = document.getElementById('ckoRecommendPrev');
    var nextBtn = document.getElementById('ckoRecommendNext');
    if (!track || !prevBtn || !nextBtn) return;
    var SCROLL_BY = 600;
    prevBtn.addEventListener('click', function(){
        track.parentElement.scrollBy({left:-SCROLL_BY,behavior:'smooth'});
    });
    nextBtn.addEventListener('click', function(){
        track.parentElement.scrollBy({left:SCROLL_BY,behavior:'smooth'});
    });
}());
</script>
<?php endif; ?>
<?php $view->endSection(); ?>
