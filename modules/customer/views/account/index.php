<?php
/**
 * =========================================================
 * AU-AgroSale — My Account Page
 * modules/customer/views/account/index.php
 * =========================================================
 * Renders all account sub-sections via the $section variable:
 *   'overview'  — account summary + recent order
 *   'pickup'    — preferred pickup shop selector
 *   'security'  — session / sign-out controls
 *   'help'      — help & support info
 *
 * @var string      $section        current active section
 * @var string      $customerName
 * @var string      $customerPhone
 * @var string      $customerEmail
 * @var array|null  $recentOrder    overview only
 * @var int         $preferredShopId  pickup only
 * @var string      $preferredShopName pickup only
 * @var array       $shops          pickup only
 * @var \AAM\Core\View $view
 */
declare(strict_types=1);

$section           = $section           ?? 'overview';
$customerName      = $customerName      ?? '';
$customerPhone     = $customerPhone     ?? '';
$customerEmail     = $customerEmail     ?? '';
$recentOrder       = $recentOrder       ?? null;
$preferredShopId   = $preferredShopId   ?? 0;
$preferredShopName = $preferredShopName ?? '';
$shops             = $shops             ?? [];
$receiptOrders     = $receiptOrders     ?? [];
$receiptTotal      = $receiptTotal      ?? 0;
$favorites         = $favorites         ?? [];
$favoritesCount    = $favoritesCount    ?? 0;
$shopId            = $shopId            ?? 0;
$shopName          = $shopName          ?? '';

$initial = $customerName ? mb_strtoupper(mb_substr($customerName, 0, 1)) : '?';

// Nav items definition
$navItems = [
    'overview'  => ['icon' => 'bi-person-circle',   'label' => 'Profile',             'url' => 'customer/account'],
    'orders'    => ['icon' => 'bi-bag-check',        'label' => 'My Orders',           'url' => 'customer/account/orders'],
    'receipts'  => ['icon' => 'bi-receipt',          'label' => 'Receipts',            'url' => 'customer/account/receipts'],
    'favorites' => ['icon' => 'bi-heart',            'label' => 'Favorites',           'url' => 'customer/account/favorites'],
    'pickup'    => ['icon' => 'bi-geo-alt',          'label' => 'Pickup Preferences',  'url' => 'customer/account/pickup'],
    'security'  => ['icon' => 'bi-shield-check',     'label' => 'Security',            'url' => 'customer/account/security'],
    'help'      => ['icon' => 'bi-question-circle',  'label' => 'Help & Support',      'url' => 'customer/account/help'],
];

use AAM\Customer\Services\CustomerOrderService;
?>

<!-- ── Breadcrumb toolbar ──────────────────────────────────── -->
<div class="cp-toolbar">
    <div class="c-container">
        <div class="cp-toolbar__inner">
            <?php if ($section !== 'overview'): ?>
            <a href="<?= $view->url('customer/account') ?>"
               class="c-back-nav"
               aria-label="Back to My Account">
                <i class="bi bi-arrow-left c-back-nav__icon" aria-hidden="true"></i>
                <span class="c-back-nav__label">Back</span>
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
                <span class="cp-breadcrumb__sep" aria-hidden="true">›</span>
                <?php if ($section !== 'overview'): ?>
                    <a href="<?= $view->url('customer/account') ?>">My Account</a>
                    <span class="cp-breadcrumb__sep" aria-hidden="true">›</span>
                    <span aria-current="page"><?= e($navItems[$section]['label'] ?? ucfirst($section)) ?></span>
                <?php else: ?>
                    <span aria-current="page">My Account</span>
                <?php endif; ?>
            </nav>
        </div>
    </div>
</div>

<div class="c-container cac-page">

    <!-- Mobile tab strip (replaces sidebar on small screens) -->
    <nav class="cac-mobile-tabs" aria-label="Account sections">
        <?php foreach ($navItems as $key => $nav): ?>
        <a href="<?= $view->url($nav['url']) ?>"
           class="cac-mobile-tab <?= ($section === $key) ? 'active' : '' ?>"
           aria-current="<?= ($section === $key) ? 'page' : 'false' ?>">
            <i class="bi <?= $nav['icon'] ?>" aria-hidden="true"></i>
            <?= e($nav['label']) ?>
            <?php if ($key === 'favorites' && $favoritesCount > 0): ?>
            <span class="caf-nav-badge" aria-label="<?= $favoritesCount ?> saved"><?= $favoritesCount ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
        <a href="<?= $view->url('customer/account/signout') ?>"
           class="cac-mobile-tab cac-mobile-tab--signout"
           onclick="return confirm('Sign out of this session?')">
            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
            Sign Out
        </a>
    </nav>

    <!-- Two-column layout -->
    <div class="cac-layout">

        <!-- ── Left sidebar ─────────────────────────────────── -->
        <aside class="cac-sidebar" aria-label="Account navigation">

            <!-- Profile header -->
            <div class="cac-sidebar__profile">
                <div class="cac-sidebar__avatar" aria-hidden="true"><?= e($initial) ?></div>
                <div>
                    <div class="cac-sidebar__name"><?= $customerName ? e($customerName) : 'Customer' ?></div>
                    <div class="cac-sidebar__role">Customer Account</div>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="cac-sidebar__nav" aria-label="Account sections">
                <?php foreach ($navItems as $key => $nav): ?>
                <a href="<?= $view->url($nav['url']) ?>"
                   class="cac-sidebar__nav-item <?= ($section === $key) ? 'active' : '' ?>"
                   aria-current="<?= ($section === $key) ? 'page' : 'false' ?>">
                    <i class="bi <?= $nav['icon'] ?>" aria-hidden="true"></i>
                    <?= e($nav['label']) ?>
                    <?php if ($key === 'favorites' && $favoritesCount > 0): ?>
                    <span class="caf-nav-badge"
                          data-ca-favorites-count
                          aria-label="<?= $favoritesCount ?> saved products"><?= $favoritesCount ?></span>
                    <?php elseif ($key === 'favorites'): ?>
                    <span class="caf-nav-badge"
                          data-ca-favorites-count
                          style="display:none;"
                          aria-hidden="true"></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>

                <div class="cac-sidebar__divider" role="separator"></div>

                <a href="<?= $view->url('customer/account/signout') ?>"
                   class="cac-sidebar__nav-item cac-sidebar__nav-item--signout"
                   onclick="return confirm('Sign out of this session?')">
                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                    Sign Out
                </a>
            </nav>
        </aside>

        <!-- ── Right content area ───────────────────────────── -->
        <div class="cac-content" role="main" id="customer-main">

            <?php if ($section === 'overview'): ?>
            <!-- ════════════════════════════════════════════
                 MY PROFILE (account overview)
                 ════════════════════════════════════════════ -->

            <!-- Profile info card -->
            <?php
            // Split name into first/last for the edit form
            $nameParts     = explode(' ', $customerName, 2);
            $editFirstName = $nameParts[0] ?? '';
            $editLastName  = $nameParts[1] ?? '';
            ?>

            <div class="cac-card" id="cac-profile-card">
                <div class="cac-card__header">
                    <div class="cac-card__header-left">
                        <div class="cac-card__header-icon" aria-hidden="true">
                            <i class="bi bi-person-circle"></i>
                        </div>
                        <div>
                            <h2 class="cac-card__title">Profile Information</h2>
                            <p class="cac-card__sub">Your session identity used for orders.</p>
                        </div>
                    </div>
                    <!-- Toggle between view and edit mode -->
                    <button type="button"
                            id="cac-profile-edit-btn"
                            class="c-btn c-btn-secondary c-btn-sm"
                            aria-expanded="false"
                            aria-controls="cac-profile-edit-form"
                            onclick="cacToggleProfileEdit()">
                        <i class="bi bi-pencil" aria-hidden="true"></i>
                        Edit
                    </button>
                </div>
                <div class="cac-card__body">

                    <!-- ── VIEW mode ─────────────────────────── -->
                    <div id="cac-profile-view">
                        <div class="cac-profile-rows">
                            <div class="cac-profile-row">
                                <span class="cac-profile-row__label">Full Name</span>
                                <span class="cac-profile-row__value <?= $customerName ? '' : 'cac-profile-row__value--empty' ?>">
                                    <?= $customerName ? e($customerName) : 'Not provided' ?>
                                </span>
                            </div>
                            <div class="cac-profile-row">
                                <span class="cac-profile-row__label">Phone</span>
                                <span class="cac-profile-row__value <?= $customerPhone ? '' : 'cac-profile-row__value--empty' ?>">
                                    <?= $customerPhone ? e($customerPhone) : 'Not provided' ?>
                                </span>
                            </div>
                            <div class="cac-profile-row">
                                <span class="cac-profile-row__label">Email</span>
                                <span class="cac-profile-row__value <?= $customerEmail ? '' : 'cac-profile-row__value--empty' ?>">
                                    <?= $customerEmail ? e($customerEmail) : 'Not provided' ?>
                                </span>
                            </div>
                        </div>
                        <p style="font-size:var(--c-size-xs);color:var(--c-text-muted);margin-top:var(--c-space-4);">
                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                            Your profile details will be pre-filled at checkout.
                        </p>
                    </div>

                    <!-- ── EDIT mode (hidden by default) ─────── -->
                    <form id="cac-profile-edit-form"
                          method="POST"
                          action="<?= $view->url('customer/account/profile') ?>"
                          novalidate
                          style="display:none;">
                        <?= \AAM\Security\CSRF::field() ?>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--c-space-4);margin-bottom:var(--c-space-4);">
                            <!-- First Name -->
                            <div>
                                <label for="cac-first-name"
                                       style="display:block;font-size:var(--c-size-xs);font-weight:var(--c-weight-semi);color:var(--c-text-muted);margin-bottom:var(--c-space-1);text-transform:uppercase;letter-spacing:.05em;">
                                    First Name
                                </label>
                                <input type="text"
                                       id="cac-first-name"
                                       name="first_name"
                                       value="<?= e($editFirstName) ?>"
                                       maxlength="80"
                                       autocomplete="given-name"
                                       class="c-form-input"
                                       style="width:100%;"
                                       placeholder="First name">
                            </div>
                            <!-- Last Name -->
                            <div>
                                <label for="cac-last-name"
                                       style="display:block;font-size:var(--c-size-xs);font-weight:var(--c-weight-semi);color:var(--c-text-muted);margin-bottom:var(--c-space-1);text-transform:uppercase;letter-spacing:.05em;">
                                    Last Name
                                </label>
                                <input type="text"
                                       id="cac-last-name"
                                       name="last_name"
                                       value="<?= e($editLastName) ?>"
                                       maxlength="80"
                                       autocomplete="family-name"
                                       class="c-form-input"
                                       style="width:100%;"
                                       placeholder="Last name">
                            </div>
                        </div>

                        <!-- Phone -->
                        <div style="margin-bottom:var(--c-space-4);">
                            <label for="cac-phone"
                                   style="display:block;font-size:var(--c-size-xs);font-weight:var(--c-weight-semi);color:var(--c-text-muted);margin-bottom:var(--c-space-1);text-transform:uppercase;letter-spacing:.05em;">
                                Phone Number
                            </label>
                            <input type="tel"
                                   id="cac-phone"
                                   name="phone"
                                   value="<?= e($customerPhone) ?>"
                                   maxlength="20"
                                   autocomplete="tel"
                                   class="c-form-input"
                                   style="width:100%;"
                                   placeholder="+251 9xxxxxxx">
                            <p style="font-size:var(--c-size-xs);color:var(--c-text-muted);margin-top:var(--c-space-1);">
                                Format: digits, spaces, dashes, optional leading +
                            </p>
                        </div>

                        <!-- Email (read-only — cannot be changed here) -->
                        <div style="margin-bottom:var(--c-space-5);">
                            <label for="cac-email"
                                   style="display:block;font-size:var(--c-size-xs);font-weight:var(--c-weight-semi);color:var(--c-text-muted);margin-bottom:var(--c-space-1);text-transform:uppercase;letter-spacing:.05em;">
                                Email Address
                            </label>
                            <input type="email"
                                   id="cac-email"
                                   value="<?= e($customerEmail) ?>"
                                   class="c-form-input"
                                   style="width:100%;opacity:.7;cursor:not-allowed;"
                                   readonly
                                   disabled
                                   aria-readonly="true">
                            <p style="font-size:var(--c-size-xs);color:var(--c-text-muted);margin-top:var(--c-space-1);">
                                <i class="bi bi-lock" aria-hidden="true"></i>
                                Email address cannot be changed here.
                            </p>
                        </div>

                        <!-- Actions -->
                        <div style="display:flex;gap:var(--c-space-3);flex-wrap:wrap;">
                            <button type="submit" class="c-btn c-btn-primary c-btn-sm">
                                <i class="bi bi-check-lg" aria-hidden="true"></i>
                                Save Changes
                            </button>
                            <button type="button"
                                    class="c-btn c-btn-secondary c-btn-sm"
                                    onclick="cacToggleProfileEdit()">
                                <i class="bi bi-x-lg" aria-hidden="true"></i>
                                Cancel
                            </button>
                        </div>
                    </form>

                </div>
            </div>

            <!-- Recent order card -->
            <div class="cac-card">
                <div class="cac-card__header">
                    <div class="cac-card__header-left">
                        <div class="cac-card__header-icon" aria-hidden="true">
                            <i class="bi bi-bag-check"></i>
                        </div>
                        <div>
                            <h2 class="cac-card__title">Recent Order</h2>
                            <p class="cac-card__sub">Your most recent pickup order.</p>
                        </div>
                    </div>
                    <a href="<?= $view->url('customer/orders') ?>" class="c-btn c-btn-secondary c-btn-sm">
                        <i class="bi bi-list-ul" aria-hidden="true"></i>
                        View All Orders
                    </a>
                </div>
                <div class="cac-card__body">
                    <?php if ($recentOrder): ?>
                    <?php
                        $roStatus   = $recentOrder['order_status']   ?? '';
                        $roPaySt    = $recentOrder['payment_status'] ?? '';
                        $roLabel    = CustomerOrderService::statusLabel($roStatus);
                        $roBadge    = CustomerOrderService::statusBadge($roStatus);
                        $roPayLabel = CustomerOrderService::paymentStatusLabel($roPaySt);
                        $roPayBadge = $roPaySt === 'paid' ? 'success' : ($roPaySt === 'failed' || $roPaySt === 'expired' ? 'danger' : 'warning');
                        $roTotal    = number_format((float)($recentOrder['total_amount'] ?? 0), 2);
                        $roDate     = $recentOrder['created_at'] ? date('M j, Y', strtotime($recentOrder['created_at'])) : '—';
                    ?>
                    <div class="cac-recent-order">
                        <div class="cac-recent-order__ref-col">
                            <div class="cac-recent-order__ref"><?= e($recentOrder['order_reference'] ?? '—') ?></div>
                            <div class="cac-recent-order__date">
                                <i class="bi bi-calendar3" aria-hidden="true"></i>
                                <?= $roDate ?>
                            </div>
                            <div class="cac-recent-order__shop">
                                <i class="bi bi-shop" aria-hidden="true"></i>
                                <?= e($recentOrder['shop_name'] ?? 'Pickup Shop') ?>
                            </div>
                            <div class="cac-recent-order__badges" style="margin-top:var(--c-space-2);">
                                <span class="c-badge c-badge-<?= $roBadge ?>">
                                    <?= e($roLabel) ?>
                                </span>
                                <span class="c-badge c-badge-<?= $roPayBadge ?>">
                                    <?= e($roPayLabel) ?>
                                </span>
                            </div>
                        </div>
                        <div class="cac-recent-order__total-col">
                            <span class="cac-recent-order__total-label">Total</span>
                            <strong class="cac-recent-order__total-value">ETB <?= $roTotal ?></strong>
                            <a href="<?= $view->url('customer/orders/' . (int)$recentOrder['id']) ?>"
                               class="c-btn c-btn-secondary c-btn-sm">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                                View Order
                            </a>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="cac-no-orders">
                        <span class="cac-no-orders__icon">🧾</span>
                        <p class="cac-no-orders__text">You haven't placed any orders yet.</p>
                        <a href="<?= $view->url('customer/products') ?>" class="c-btn c-btn-primary c-btn-sm">
                            <i class="bi bi-grid" aria-hidden="true"></i>
                            Browse Products
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick links -->
            <div class="cac-card">
                <div class="cac-card__header">
                    <div class="cac-card__header-left">
                        <div class="cac-card__header-icon" aria-hidden="true">
                            <i class="bi bi-lightning"></i>
                        </div>
                        <div>
                            <h2 class="cac-card__title">Quick Links</h2>
                        </div>
                    </div>
                </div>
                <div class="cac-card__body">
                    <div style="display:flex;flex-wrap:wrap;gap:var(--c-space-3);">
                        <a href="<?= $view->url('customer/products') ?>" class="c-btn c-btn-secondary c-btn-sm">
                            <i class="bi bi-grid" aria-hidden="true"></i>
                            Browse Products
                        </a>
                        <a href="<?= $view->url('customer/orders') ?>" class="c-btn c-btn-secondary c-btn-sm">
                            <i class="bi bi-bag-check" aria-hidden="true"></i>
                            My Orders
                        </a>
                        <a href="<?= $view->url('customer/cart') ?>" class="c-btn c-btn-secondary c-btn-sm">
                            <i class="bi bi-cart3" aria-hidden="true"></i>
                            My Cart
                        </a>
                        <a href="<?= $view->url('customer/shops') ?>" class="c-btn c-btn-secondary c-btn-sm">
                            <i class="bi bi-shop" aria-hidden="true"></i>
                            Shops
                        </a>
                    </div>
                </div>
            </div>

            <?php elseif ($section === 'pickup'): ?>
            <!-- ════════════════════════════════════════════
                 PICKUP PREFERENCES
                 ════════════════════════════════════════════ -->
            <div class="cac-card">
                <div class="cac-card__header">
                    <div class="cac-card__header-left">
                        <div class="cac-card__header-icon" aria-hidden="true">
                            <i class="bi bi-geo-alt"></i>
                        </div>
                        <div>
                            <h2 class="cac-card__title">Pickup Preferences</h2>
                            <p class="cac-card__sub">Your current pickup shop selection.</p>
                        </div>
                    </div>
                </div>
                <div class="cac-card__body">

                    <div class="cac-pickup-note">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        <div>
                            <strong>Note:</strong> This shows your currently selected pickup shop.
                            You can change your shop at any time using the shop selector in the navigation bar,
                            or when browsing products. Each order keeps its own pickup shop — changing this
                            preference does not affect existing orders.
                        </div>
                    </div>

                    <?php if ($preferredShopId && $preferredShopName): ?>
                    <div style="margin-bottom:var(--c-space-5);">
                        <p style="font-size:var(--c-size-xs);font-weight:var(--c-weight-semi);color:var(--c-text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:var(--c-space-3);">
                            Currently Selected
                        </p>
                        <div class="cac-shop-option selected" style="cursor:default;">
                            <div class="cac-shop-option__icon">
                                <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div class="cac-shop-option__name"><?= e($preferredShopName) ?></div>
                            </div>
                            <div class="cac-shop-option__check">
                                <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($shops)): ?>
                    <p style="font-size:var(--c-size-xs);font-weight:var(--c-weight-semi);color:var(--c-text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:var(--c-space-3);">
                        Available Shops
                    </p>
                    <div class="cac-shop-list">
                        <?php foreach ($shops as $shop):
                            $isSelected = ($preferredShopId === (int)$shop['id']);
                        ?>
                        <div class="cac-shop-option <?= $isSelected ? 'selected' : '' ?>"
                             data-ca-shop-option="<?= (int)$shop['id'] ?>"
                             data-ca-shop-option-name="<?= e($shop['shop_name'] ?? '') ?>"
                             role="button"
                             tabindex="0"
                             aria-pressed="<?= $isSelected ? 'true' : 'false' ?>"
                             style="cursor:pointer;">
                            <div class="cac-shop-option__icon">
                                <i class="bi bi-<?= $isSelected ? 'geo-alt-fill' : 'shop' ?>" aria-hidden="true"></i>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div class="cac-shop-option__name"><?= e($shop['shop_name'] ?? '') ?></div>
                                <?php if (!empty($shop['location'])): ?>
                                <div class="cac-shop-option__location">
                                    <i class="bi bi-geo" aria-hidden="true"></i>
                                    <?= e($shop['location']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="cac-shop-option__check">
                                <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <p style="font-size:var(--c-size-xs);color:var(--c-text-muted);margin-top:var(--c-space-4);">
                        <i class="bi bi-arrow-left-right" aria-hidden="true"></i>
                        Clicking a shop above sets it as your active shop selection — the same as using the shop selector in the navbar.
                    </p>
                    <?php else: ?>
                    <p style="color:var(--c-text-muted);font-size:var(--c-size-sm);">No shops available at this time.</p>
                    <?php endif; ?>

                </div>
            </div>

            <?php elseif ($section === 'security'): ?>
            <!-- ════════════════════════════════════════════
                 SECURITY
                 ════════════════════════════════════════════ -->
            <div class="cac-card">
                <div class="cac-card__header">
                    <div class="cac-card__header-left">
                        <div class="cac-card__header-icon" aria-hidden="true">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <div>
                            <h2 class="cac-card__title">Security</h2>
                            <p class="cac-card__sub">Manage your session and account access.</p>
                        </div>
                    </div>
                </div>
                <div class="cac-card__body">
                    <div class="cac-security-items">

                        <!-- Active session info -->
                        <div class="cac-security-item">
                            <div class="cac-security-item__info">
                                <div class="cac-security-item__icon">
                                    <i class="bi bi-person-check" aria-hidden="true"></i>
                                </div>
                                <div>
                                    <div class="cac-security-item__title">Active Session</div>
                                    <div class="cac-security-item__desc">
                                        You are browsing as
                                        <strong><?= $customerName ? e($customerName) : 'an anonymous customer' ?></strong>.
                                        Your orders are linked to this browser session.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Session data note -->
                        <div class="cac-security-item">
                            <div class="cac-security-item__info">
                                <div class="cac-security-item__icon">
                                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                                </div>
                                <div>
                                    <div class="cac-security-item__title">Session-Based Identity</div>
                                    <div class="cac-security-item__desc">
                                        AU-AgroSale uses session-based customer identity.
                                        Your name and phone number are collected at checkout and stored in your
                                        browser session only — they are not stored in a permanent customer account.
                                        Your order history is accessible from this device/browser for this session.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sign out -->
                        <div class="cac-security-item">
                            <div class="cac-security-item__info">
                                <div class="cac-security-item__icon cac-security-item__icon--danger">
                                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                                </div>
                                <div>
                                    <div class="cac-security-item__title">Sign Out</div>
                                    <div class="cac-security-item__desc">
                                        Clear your session data including your name, phone, selected shop, and cart.
                                        Your order history will no longer be accessible from this browser after signing out.
                                    </div>
                                </div>
                            </div>
                            <a href="<?= $view->url('customer/account/signout') ?>"
                               class="c-btn c-btn-sm"
                               style="background:var(--c-danger-bg);color:var(--c-danger);border:1.5px solid var(--c-danger);flex-shrink:0;"
                               onclick="return confirm('This will clear your session data. Your order history will no longer be accessible from this browser. Continue?')">
                                <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                                Sign Out
                            </a>
                        </div>

                    </div>
                </div>
            </div>

            <?php elseif ($section === 'receipts'): ?>
            <!-- ════════════════════════════════════════════
                 RECEIPTS
                 ════════════════════════════════════════════ -->
            <div class="cac-card">
                <div class="cac-card__header">
                    <div class="cac-card__header-left">
                        <div class="cac-card__header-icon" aria-hidden="true">
                            <i class="bi bi-receipt"></i>
                        </div>
                        <div>
                            <h2 class="cac-card__title">Receipts</h2>
                            <p class="cac-card__sub">Your paid order receipts for printing or reference.</p>
                        </div>
                    </div>
                    <a href="<?= $view->url('customer/orders') ?>" class="c-btn c-btn-secondary c-btn-sm">
                        <i class="bi bi-list-ul" aria-hidden="true"></i>
                        All Orders
                    </a>
                </div>
                <div class="cac-card__body">

                    <?php if (!empty($receiptOrders)): ?>
                    <div class="cac-receipt-list">
                        <?php foreach ($receiptOrders as $ro):
                            $roStatus   = $ro['order_status']   ?? '';
                            $roPaySt    = $ro['payment_status'] ?? '';
                            $roLabel    = CustomerOrderService::statusLabel($roStatus);
                            $roBadge    = CustomerOrderService::statusBadge($roStatus);
                            $roPayLabel = CustomerOrderService::paymentStatusLabel($roPaySt);
                            $roPayBadge = $roPaySt === 'paid' ? 'success' : ($roPaySt === 'failed' || $roPaySt === 'expired' ? 'danger' : 'warning');
                            $roTotal    = number_format((float)($ro['total_amount'] ?? 0), 2);
                            $roDate     = $ro['created_at'] ? date('M j, Y', strtotime($ro['created_at'])) : '—';
                            $roShop     = $ro['shop_name'] ?? '';
                            $roItems    = (int)($ro['item_count'] ?? 0);
                            $roPmType   = $ro['payment_method_type'] ?? '';
                            $roPmName   = $ro['payment_method_name'] ?? '';
                        ?>
                        <div class="cac-receipt-item">
                            <div class="cac-receipt-item__main">
                                <div class="cac-receipt-item__ref"><?= e($ro['order_reference'] ?? '—') ?></div>
                                <div class="cac-receipt-item__meta">
                                    <span><i class="bi bi-calendar3" aria-hidden="true"></i> <?= $roDate ?></span>
                                    <?php if ($roShop): ?>
                                    <span><i class="bi bi-shop" aria-hidden="true"></i> <?= e($roShop) ?></span>
                                    <?php endif; ?>
                                    <?php if ($roItems > 0): ?>
                                    <span><i class="bi bi-bag" aria-hidden="true"></i> <?= $roItems ?> item<?= $roItems !== 1 ? 's' : '' ?></span>
                                    <?php endif; ?>
                                    <?php if ($roPmName): ?>
                                    <span><i class="bi bi-<?= $roPmType === 'cash' ? 'cash-coin' : 'phone' ?>" aria-hidden="true"></i> <?= e($roPmName) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="cac-receipt-item__badges">
                                    <span class="c-badge c-badge-<?= $roBadge ?>"><?= e($roLabel) ?></span>
                                    <span class="c-badge c-badge-<?= $roPayBadge ?>">
                                        <?= $roPaySt === 'paid' ? '<i class="bi bi-check-circle-fill" aria-hidden="true"></i> ' : '' ?>
                                        <?= e($roPayLabel) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="cac-receipt-item__aside">
                                <strong class="cac-receipt-item__total">ETB <?= $roTotal ?></strong>
                                <div class="cac-receipt-item__actions">
                                    <a href="<?= $view->url('customer/orders/' . (int)$ro['id'] . '/receipt') ?>"
                                       class="c-btn c-btn-primary c-btn-sm"
                                       aria-label="View receipt for <?= e($ro['order_reference'] ?? '') ?>">
                                        <i class="bi bi-receipt" aria-hidden="true"></i>
                                        Receipt
                                    </a>
                                    <a href="<?= $view->url('customer/orders/' . (int)$ro['id']) ?>"
                                       class="c-btn c-btn-secondary c-btn-sm"
                                       aria-label="View order <?= e($ro['order_reference'] ?? '') ?>">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                        Order
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($receiptTotal > count($receiptOrders)): ?>
                    <div style="margin-top:var(--c-space-5);text-align:center;">
                        <a href="<?= $view->url('customer/orders?filter=completed') ?>" class="c-btn c-btn-secondary c-btn-sm">
                            <i class="bi bi-list-ul" aria-hidden="true"></i>
                            View All Orders
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php else: ?>
                    <div class="cac-no-orders">
                        <span class="cac-no-orders__icon">🧾</span>
                        <p class="cac-no-orders__text">No receipts yet. Receipts become available after payment is confirmed.</p>
                        <a href="<?= $view->url('customer/products') ?>" class="c-btn c-btn-primary c-btn-sm">
                            <i class="bi bi-grid" aria-hidden="true"></i>
                            Browse Products
                        </a>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

            <?php elseif ($section === 'favorites'): ?>
            <!-- ════════════════════════════════════════════
                 FAVORITES / WISHLIST
                 ════════════════════════════════════════════ -->
            <?php $view->section('styles'); ?>
            <link rel="stylesheet" href="<?= $view->url('public/assets/customer/css/favorites.css') ?>?v=<?= file_exists(PUBLIC_PATH . '/assets/customer/css/favorites.css') ? filemtime(PUBLIC_PATH . '/assets/customer/css/favorites.css') : '1' ?>">
            <?php $view->endSection(); ?>

            <div class="cac-card">
                <div class="cac-card__header">
                    <div class="cac-card__header-left">
                        <div class="cac-card__header-icon" aria-hidden="true">
                            <i class="bi bi-heart<?= $favoritesCount > 0 ? '-fill' : '' ?>"></i>
                        </div>
                        <div>
                            <h2 class="cac-card__title">My Favorites</h2>
                            <p class="cac-card__sub">Products you saved for later.<?= $favoritesCount > 0 ? ' ' . $favoritesCount . ' saved product' . ($favoritesCount !== 1 ? 's' : '') . '.' : '' ?></p>
                        </div>
                    </div>
                    <a href="<?= $view->url('customer/products') ?>" class="c-btn c-btn-secondary c-btn-sm">
                        <i class="bi bi-grid" aria-hidden="true"></i>
                        Browse Products
                    </a>
                </div>
                <div class="cac-card__body">

                    <?php if (empty($favorites)): ?>
                    <!-- ── EMPTY STATE ──────────────────────────── -->
                    <div class="caf-empty">
                        <div class="caf-empty__icon" aria-hidden="true">
                            <i class="bi bi-heart"></i>
                        </div>
                        <h3 class="caf-empty__title">Your Favorites Are Empty</h3>
                        <p class="caf-empty__desc">Save products you love and easily find them here later.</p>
                        <a href="<?= $view->url('customer/products') ?>"
                           class="c-btn c-btn-primary">
                            <i class="bi bi-grid" aria-hidden="true"></i>
                            Browse Products
                        </a>
                    </div>

                    <?php else: ?>
                    <!-- ── FAVORITES GRID ───────────────────────── -->
                    <div class="caf-grid" role="list" aria-label="Favorite products">
                        <?php foreach ($favorites as $fav):
                            $fpid      = (int)($fav['id'] ?? 0);
                            $fpname    = $fav['product_name'] ?? '';
                            $fprice    = (float)($fav['selling_price'] ?? 0);
                            $funit     = $fav['unit_abbreviation'] ?? $fav['unit_name'] ?? '';
                            $fcat      = $fav['category_name'] ?? '';
                            $fimg      = $fav['primary_image'] ?? null;
                            $favail    = $fav['availability'] ?? ['status' => 'unavailable', 'label' => 'Unavailable', 'qty_label' => ''];
                            $fAvailQty = (float)($fav['available_quantity'] ?? 0);
                            $fIsAvail  = $favail['status'] !== 'unavailable';
                            $fIsUnavailable = !empty($fav['product_unavailable']);
                            $fdetailUrl = $view->url('customer/products/' . $fpid . ($shopId ? '?shop_id=' . $shopId : ''));
                            $fimg_icon = '📦';
                            if ($fcat) {
                                $icons = ['dairy'=>'🥛','milk'=>'🥛','grain'=>'🌾','teff'=>'🌾','wheat'=>'🌾',
                                          'barley'=>'🌾','egg'=>'🥚','honey'=>'🍯','meat'=>'🥩','vegetable'=>'🥦',
                                          'fruit'=>'🍎','herb'=>'🌿','legume'=>'🫘','pulse'=>'🫘','oil'=>'🫙'];
                                $lc = strtolower($fcat);
                                foreach ($icons as $k => $ico) { if (str_contains($lc, $k)) { $fimg_icon = $ico; break; } }
                            }
                        ?>
                        <article class="caf-card <?= $fIsUnavailable ? 'caf-card--unavailable' : '' ?>"
                                 role="listitem"
                                 aria-label="<?= e($fpname) ?>">

                            <!-- Product Image -->
                            <div class="caf-card__img-wrap">
                                <?php if ($fpid && !$fIsUnavailable): ?>
                                <a href="<?= $fdetailUrl ?>" tabindex="-1" aria-hidden="true">
                                <?php endif; ?>
                                    <?php if ($fimg && !$fIsUnavailable): ?>
                                    <img src="<?= $view->url(e($fimg)) ?>"
                                         alt="<?= e($fpname) ?>"
                                         loading="lazy"
                                         width="400" height="300">
                                    <?php else: ?>
                                    <div class="caf-card__img-placeholder" aria-hidden="true">
                                        <span><?= $fimg_icon ?></span>
                                    </div>
                                    <?php endif; ?>
                                <?php if ($fpid && !$fIsUnavailable): ?>
                                </a>
                                <?php endif; ?>

                                <!-- Availability overlay for out-of-stock -->
                                <?php if (!$fIsAvail && !$fIsUnavailable): ?>
                                <div class="caf-card__out-badge">
                                    <i class="bi bi-x-circle-fill" aria-hidden="true"></i>
                                    Out of stock
                                </div>
                                <?php endif; ?>

                                <?php if ($fIsUnavailable): ?>
                                <div class="caf-card__out-badge caf-card__out-badge--unavailable">
                                    <i class="bi bi-slash-circle" aria-hidden="true"></i>
                                    Unavailable
                                </div>
                                <?php endif; ?>

                                <!-- Heart remove button -->
                                <button type="button"
                                        class="caf-card__heart caf-card__heart--active"
                                        data-ca-favorite-toggle
                                        data-product-id="<?= $fpid ?>"
                                        data-product-name="<?= e($fpname) ?>"
                                        aria-label="Remove <?= e($fpname) ?> from favorites"
                                        aria-pressed="true">
                                    <i class="bi bi-heart-fill" aria-hidden="true"></i>
                                </button>
                            </div>

                            <!-- Card Body -->
                            <div class="caf-card__body">
                                <?php if ($fcat): ?>
                                <div class="caf-card__category"><?= e($fcat) ?></div>
                                <?php endif; ?>

                                <h3 class="caf-card__name">
                                    <?php if ($fpid && !$fIsUnavailable): ?>
                                    <a href="<?= $fdetailUrl ?>"><?= e($fpname) ?></a>
                                    <?php else: ?>
                                    <?= e($fpname ?: 'Product no longer available') ?>
                                    <?php endif; ?>
                                </h3>

                                <?php if (!$fIsUnavailable): ?>
                                <div class="caf-card__price-row">
                                    <span class="caf-card__price">ETB <?= number_format($fprice, 2) ?></span>
                                    <?php if ($funit): ?>
                                    <span class="caf-card__price-unit">/ <?= e($funit) ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($favail['status'] === 'available'): ?>
                                <div class="caf-card__avail caf-card__avail--ok">
                                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                                    In stock<?= $shopId ? ' at ' . e($shopName) : '' ?>
                                </div>
                                <?php elseif ($favail['status'] === 'low'): ?>
                                <div class="caf-card__avail caf-card__avail--low">
                                    <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                                    <?= e($favail['label']) ?>
                                </div>
                                <?php else: ?>
                                <div class="caf-card__avail caf-card__avail--out">
                                    <i class="bi bi-x-circle-fill" aria-hidden="true"></i>
                                    Out of stock<?= $shopId ? ' at ' . e($shopName) : '' ?>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Card Footer: Add to Cart + Remove -->
                            <div class="caf-card__footer">
                                <?php if (!$fIsUnavailable && $fIsAvail && $shopId): ?>
                                <button type="button"
                                        class="caf-card__cart-btn"
                                        data-ca-add-to-cart
                                        data-product-id="<?= $fpid ?>"
                                        data-product-name="<?= e($fpname) ?>"
                                        data-product-price="<?= e($fprice) ?>"
                                        data-unit="<?= e($funit) ?>"
                                        data-max-qty="<?= e($fAvailQty) ?>"
                                        data-shop-id="<?= $shopId ?>"
                                        data-shop-name="<?= e($shopName) ?>"
                                        aria-label="Add <?= e($fpname) ?> to cart">
                                    <i class="bi bi-bag-plus-fill" aria-hidden="true"></i>
                                    Add to Cart
                                </button>
                                <?php elseif (!$fIsUnavailable && $fIsAvail && !$shopId): ?>
                                <a href="<?= $fdetailUrl ?>"
                                   class="caf-card__cart-btn caf-card__cart-btn--link"
                                   aria-label="View <?= e($fpname) ?> and select shop">
                                    <i class="bi bi-shop" aria-hidden="true"></i>
                                    Select Shop
                                </a>
                                <?php elseif (!$fIsUnavailable): ?>
                                <button type="button"
                                        class="caf-card__cart-btn caf-card__cart-btn--disabled"
                                        disabled
                                        aria-disabled="true">
                                    <i class="bi bi-x-circle" aria-hidden="true"></i>
                                    Out of Stock
                                </button>
                                <?php else: ?>
                                <button type="button"
                                        class="caf-card__remove-btn"
                                        data-ca-favorite-toggle
                                        data-product-id="<?= $fpid ?>"
                                        data-product-name="<?= e($fpname) ?>"
                                        aria-label="Remove unavailable item from favorites">
                                    <i class="bi bi-trash3" aria-hidden="true"></i>
                                    Remove
                                </button>
                                <?php endif; ?>
                            </div>

                        </article>
                        <?php endforeach; ?>
                    </div><!-- /.caf-grid -->
                    <?php endif; ?>

                </div>
            </div>

            <?php $view->section('scripts'); ?>
            <?php $view->endSection(); ?>

            <?php elseif ($section === 'help'): ?>
            <!-- ════════════════════════════════════════════
                 HELP & SUPPORT
                 ════════════════════════════════════════════ -->
            <div class="cac-card">
                <div class="cac-card__header">
                    <div class="cac-card__header-left">
                        <div class="cac-card__header-icon" aria-hidden="true">
                            <i class="bi bi-question-circle"></i>
                        </div>
                        <div>
                            <h2 class="cac-card__title">Help &amp; Support</h2>
                            <p class="cac-card__sub">Guides and answers for shopping at AU-AgroSale.</p>
                        </div>
                    </div>
                </div>
                <div class="cac-card__body">

                    <div class="cac-help-grid" style="margin-bottom:var(--c-space-6);">

                        <div class="cac-help-item">
                            <div class="cac-help-item__icon">
                                <i class="bi bi-cart-check" aria-hidden="true"></i>
                            </div>
                            <div>
                                <div class="cac-help-item__title">How to Order</div>
                                <div class="cac-help-item__desc">
                                    Browse products, select a pickup shop, add items to your cart, fill in your details, and complete payment at checkout.
                                </div>
                            </div>
                        </div>

                        <div class="cac-help-item">
                            <div class="cac-help-item__icon">
                                <i class="bi bi-credit-card" aria-hidden="true"></i>
                            </div>
                            <div>
                                <div class="cac-help-item__title">Payment Help</div>
                                <div class="cac-help-item__desc">
                                    AU-AgroSale accepts cash on pickup and mobile payment. If a payment fails, you can retry from My Orders.
                                </div>
                            </div>
                        </div>

                        <div class="cac-help-item">
                            <div class="cac-help-item__icon">
                                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                            </div>
                            <div>
                                <div class="cac-help-item__title">Pickup Information</div>
                                <div class="cac-help-item__desc">
                                    After your order is confirmed, head to your selected shop. Show your order reference number to the staff member to collect your items.
                                </div>
                            </div>
                        </div>

                        <div class="cac-help-item">
                            <div class="cac-help-item__icon">
                                <i class="bi bi-bag-check" aria-hidden="true"></i>
                            </div>
                            <div>
                                <div class="cac-help-item__title">Order Status</div>
                                <div class="cac-help-item__desc">
                                    Track your order in My Orders. Statuses: Pending → Paid → Preparing → Ready for Pickup → Completed.
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Contact strip -->
                    <div class="cac-contact-strip">
                        <i class="bi bi-building cac-contact-strip__icon" aria-hidden="true"></i>
                        <div class="cac-contact-strip__text">
                            <strong>Arsi University Agri-Management</strong><br>
                            For additional support, visit any AU-AgroSale shop location and speak with a staff member.
                        </div>
                        <a href="<?= $view->url('customer/shops') ?>" class="c-btn c-btn-secondary c-btn-sm">
                            <i class="bi bi-shop" aria-hidden="true"></i>
                            Find a Shop
                        </a>
                    </div>

                </div>
            </div>

            <?php elseif ($section === 'orders'): ?>
            <!-- ════════════════════════════════════════════
                 MY ORDERS
                 ════════════════════════════════════════════ -->
            <?php
            $orders        = $orders        ?? [];
            $ordersTotal   = $ordersTotal   ?? 0;
            $ordersPage    = $ordersPage    ?? 1;
            $ordersPerPage = $ordersPerPage ?? 10;
            $ordersFilter  = $ordersFilter  ?? 'all';
            $ordersSort    = $ordersSort    ?? 'newest';
            $ordersSearch  = $ordersSearch  ?? '';
            $ordersTotalPages = max(1, (int)ceil($ordersTotal / $ordersPerPage));

            $filterTabs = [
                'all'              => ['label' => 'All',        'icon' => 'bi-list-ul'],
                'pending_payment'  => ['label' => 'Pending',    'icon' => 'bi-clock'],
                'paid'             => ['label' => 'Paid',       'icon' => 'bi-cash-coin'],
                'preparing'        => ['label' => 'Preparing',  'icon' => 'bi-gear'],
                'ready_for_pickup' => ['label' => 'Ready',      'icon' => 'bi-bag-check'],
                'completed'        => ['label' => 'Completed',  'icon' => 'bi-check-circle'],
                'cancelled'        => ['label' => 'Cancelled',  'icon' => 'bi-x-circle'],
            ];
            ?>

            <?php $view->section('styles'); ?>
            <link rel="stylesheet" href="<?= $view->url('public/assets/customer/css/orders.css') ?>?v=<?= file_exists(PUBLIC_PATH . '/assets/customer/css/orders.css') ? filemtime(PUBLIC_PATH . '/assets/customer/css/orders.css') : '1' ?>">
            <?php $view->endSection(); ?>

            <!-- Page header -->
            <div class="cord-header">
                <div class="cord-header__left">
                    <h2 class="cord-title" style="font-size:var(--c-size-2xl);">My Orders</h2>
                    <p class="cord-sub">
                        <span class="cord-total-count"><?= $ordersTotal ?> order<?= $ordersTotal !== 1 ? 's' : '' ?></span>
                        <?php if ($ordersSearch): ?>
                        <span>matching "<strong><?= e($ordersSearch) ?></strong>"</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- Filter tabs -->
            <nav class="cord-filters" aria-label="Filter orders">
                <?php foreach ($filterTabs as $val => $tab): ?>
                <a href="<?= $view->url('customer/account/orders') ?>?filter=<?= $val ?><?= $ordersSearch ? '&search=' . urlencode($ordersSearch) : '' ?>"
                   class="cord-filter-tab <?= $ordersFilter === $val ? 'cord-filter-tab--active' : '' ?>"
                   aria-current="<?= $ordersFilter === $val ? 'page' : 'false' ?>">
                    <i class="bi <?= $tab['icon'] ?>" aria-hidden="true"></i>
                    <span class="cord-filter-tab__label"><?= e($tab['label']) ?></span>
                </a>
                <?php endforeach; ?>
            </nav>

            <!-- Search + sort toolbar -->
            <div class="cord-toolbar">
                <form method="GET"
                      action="<?= $view->url('customer/account/orders') ?>"
                      class="cord-search-form"
                      role="search">
                    <input type="hidden" name="filter" value="<?= e($ordersFilter) ?>">
                    <div class="cord-search-wrap">
                        <i class="bi bi-search cord-search-icon" aria-hidden="true"></i>
                        <input type="search"
                               name="search"
                               class="cord-search-input"
                               placeholder="Search by order reference…"
                               value="<?= e($ordersSearch) ?>"
                               aria-label="Search orders">
                        <?php if ($ordersSearch): ?>
                        <a href="<?= $view->url('customer/account/orders') ?>?filter=<?= urlencode($ordersFilter) ?>"
                           class="cord-search-clear"
                           aria-label="Clear search">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </a>
                        <?php endif; ?>
                        <button type="submit" class="c-btn c-btn-primary cord-search-btn">
                            <i class="bi bi-search" aria-hidden="true"></i>
                            Search
                        </button>
                    </div>
                </form>

                <div class="cord-sort-wrap">
                    <label for="ordersSortSelect" class="cord-sort-label">Sort:</label>
                    <select id="ordersSortSelect"
                            class="cord-sort-select"
                            onchange="location.href='<?= $view->url('customer/account/orders') ?>?filter=<?= urlencode($ordersFilter) ?>&search=<?= urlencode($ordersSearch) ?>&sort='+this.value">
                        <option value="newest"     <?= $ordersSort === 'newest'     ? 'selected' : '' ?>>Newest First</option>
                        <option value="oldest"     <?= $ordersSort === 'oldest'     ? 'selected' : '' ?>>Oldest First</option>
                        <option value="total_high" <?= $ordersSort === 'total_high' ? 'selected' : '' ?>>Highest Amount</option>
                        <option value="total_low"  <?= $ordersSort === 'total_low'  ? 'selected' : '' ?>>Lowest Amount</option>
                    </select>
                </div>
            </div>

            <?php if ($ordersSearch): ?>
            <div class="cord-search-notice" role="status">
                <i class="bi bi-search" aria-hidden="true"></i>
                Results for "<strong><?= e($ordersSearch) ?></strong>"
                <a href="<?= $view->url('customer/account/orders') ?>?filter=<?= urlencode($ordersFilter) ?>"
                   class="cord-search-notice__clear">Clear search</a>
            </div>
            <?php endif; ?>

            <?php if (!empty($orders)): ?>
            <!-- Orders list -->
            <div class="cord-list" role="list">
                <?php foreach ($orders as $ord):
                    $oStatus   = $ord['order_status']   ?? '';
                    $oPaySt    = $ord['payment_status'] ?? '';
                    $oLabel    = CustomerOrderService::statusLabel($oStatus);
                    $oBadge    = CustomerOrderService::statusBadge($oStatus);
                    $oPayLabel = CustomerOrderService::paymentStatusLabel($oPaySt);
                    $oPayBadge = $oPaySt === 'paid' ? 'success' : ($oPaySt === 'failed' || $oPaySt === 'expired' ? 'danger' : 'warning');
                    $oTotal    = number_format((float)($ord['total_amount'] ?? 0), 2);
                    $oDate     = $ord['created_at'] ? date('d M Y', strtotime($ord['created_at'])) : '—';
                    $oTime     = $ord['created_at'] ? date('H:i', strtotime($ord['created_at'])) : '';
                    $oShop     = $ord['shop_name'] ?? '';
                    $oPmName   = $ord['payment_method_name'] ?? '';
                    $oPmType   = $ord['payment_method_type'] ?? '';
                    $oItems    = (int)($ord['item_count'] ?? 0);
                ?>
                <article class="cord-item cord-item--<?= $oBadge ?>" role="listitem">

                    <!-- Top: ref + badges -->
                    <div class="cord-item__top">
                        <div class="cord-item__ref">
                            <span class="cord-item__order-ref"><?= e($ord['order_reference'] ?? '—') ?></span>
                            <span class="cord-item__date">
                                <i class="bi bi-calendar3" aria-hidden="true"></i>
                                <?= $oDate ?>
                                <?php if ($oTime): ?>
                                <span class="cord-item__time"><?= $oTime ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="cord-item__badges">
                            <span class="c-badge c-badge-<?= $oBadge ?>"><?= e($oLabel) ?></span>
                            <span class="c-badge c-badge-<?= $oPayBadge ?>"><?= e($oPayLabel) ?></span>
                        </div>
                    </div>

                    <!-- Mid: details + total -->
                    <div class="cord-item__mid">
                        <div class="cord-item__details">
                            <?php if ($oShop): ?>
                            <div class="cord-item__shop">
                                <i class="bi bi-shop" aria-hidden="true"></i>
                                <?= e($oShop) ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($oPmName): ?>
                            <div class="cord-item__payment-method">
                                <i class="bi bi-<?= $oPmType === 'cash' ? 'cash-coin' : 'phone' ?>" aria-hidden="true"></i>
                                <?= e($oPmName) ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($oItems > 0): ?>
                            <div class="cord-item__item-count">
                                <i class="bi bi-bag" aria-hidden="true"></i>
                                <?= $oItems ?> item<?= $oItems !== 1 ? 's' : '' ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="cord-item__total">
                            <span class="cord-item__total-label">Total</span>
                            <span class="cord-item__total-val">ETB <?= $oTotal ?></span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="cord-item__actions">
                        <?php if ($oStatus === 'ready_for_pickup'): ?>
                        <span class="cord-item__ready-badge">
                            <i class="bi bi-bag-check-fill" aria-hidden="true"></i>
                            Ready for Pickup!
                        </span>
                        <?php endif; ?>
                        <a href="<?= $view->url('customer/account/orders/' . (int)$ord['id']) ?>"
                           class="c-btn c-btn-secondary c-btn-sm"
                           style="margin-left:auto;">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                            View Order
                        </a>
                        <?php if (in_array($oStatus, ['paid','preparing','ready_for_pickup','completed'], true)
                                  || ($oStatus === 'pending_payment' && $oPmType === 'cash')): ?>
                        <a href="<?= $view->url('customer/account/orders/' . (int)$ord['id'] . '/receipt') ?>"
                           class="c-btn c-btn-secondary c-btn-sm">
                            <i class="bi bi-receipt" aria-hidden="true"></i>
                            Receipt
                        </a>
                        <?php endif; ?>
                    </div>

                </article>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($ordersTotalPages > 1): ?>
            <nav class="cord-pagination" aria-label="Orders pagination">
                <?php if ($ordersPage > 1): ?>
                <a href="<?= $view->url('customer/account/orders') ?>?page=<?= $ordersPage - 1 ?>&filter=<?= urlencode($ordersFilter) ?>&search=<?= urlencode($ordersSearch) ?>&sort=<?= urlencode($ordersSort) ?>"
                   class="cord-pagination__btn">
                    <i class="bi bi-chevron-left" aria-hidden="true"></i> Prev
                </a>
                <?php else: ?>
                <span class="cord-pagination__btn cord-pagination__btn--disabled">
                    <i class="bi bi-chevron-left" aria-hidden="true"></i> Prev
                </span>
                <?php endif; ?>

                <div class="cord-pagination__pages">
                    <?php for ($p = 1; $p <= $ordersTotalPages; $p++): ?>
                    <?php if ($p === $ordersPage): ?>
                    <span class="cord-pagination__page cord-pagination__page--current"><?= $p ?></span>
                    <?php elseif ($p === 1 || $p === $ordersTotalPages || abs($p - $ordersPage) <= 1): ?>
                    <a href="<?= $view->url('customer/account/orders') ?>?page=<?= $p ?>&filter=<?= urlencode($ordersFilter) ?>&search=<?= urlencode($ordersSearch) ?>&sort=<?= urlencode($ordersSort) ?>"
                       class="cord-pagination__page"><?= $p ?></a>
                    <?php elseif (abs($p - $ordersPage) === 2): ?>
                    <span class="cord-pagination__ellipsis">…</span>
                    <?php endif; ?>
                    <?php endfor; ?>
                </div>

                <?php if ($ordersPage < $ordersTotalPages): ?>
                <a href="<?= $view->url('customer/account/orders') ?>?page=<?= $ordersPage + 1 ?>&filter=<?= urlencode($ordersFilter) ?>&search=<?= urlencode($ordersSearch) ?>&sort=<?= urlencode($ordersSort) ?>"
                   class="cord-pagination__btn">
                    Next <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </a>
                <?php else: ?>
                <span class="cord-pagination__btn cord-pagination__btn--disabled">
                    Next <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </span>
                <?php endif; ?>
            </nav>
            <p class="cord-pagination__info">
                Page <?= $ordersPage ?> of <?= $ordersTotalPages ?>
                &nbsp;·&nbsp; <?= $ordersTotal ?> total order<?= $ordersTotal !== 1 ? 's' : '' ?>
            </p>
            <?php endif; ?>

            <?php else: ?>
            <!-- Empty state -->
            <div class="cp-empty-state">
                <div class="cp-empty-state__icon">🛍️</div>
                <h3 class="cp-empty-state__title">
                    <?= $ordersSearch ? 'No results found' : ($ordersFilter !== 'all' ? 'No orders here' : 'No orders yet') ?>
                </h3>
                <p class="cp-empty-state__desc">
                    <?php if ($ordersSearch): ?>
                        No orders match "<strong><?= e($ordersSearch) ?></strong>". Try a different reference number.
                    <?php elseif ($ordersFilter !== 'all'): ?>
                        You have no orders with status "<?= e($filterTabs[$ordersFilter]['label'] ?? $ordersFilter) ?>".
                    <?php else: ?>
                        You haven't placed any orders yet. Browse our products and start shopping!
                    <?php endif; ?>
                </p>
                <div class="cp-empty-state__actions">
                    <?php if ($ordersSearch || $ordersFilter !== 'all'): ?>
                    <a href="<?= $view->url('customer/account/orders') ?>" class="c-btn c-btn-secondary c-btn-sm">
                        <i class="bi bi-x-circle" aria-hidden="true"></i>
                        Clear Filters
                    </a>
                    <?php endif; ?>
                    <a href="<?= $view->url('customer/products') ?>" class="c-btn c-btn-primary c-btn-sm">
                        <i class="bi bi-grid" aria-hidden="true"></i>
                        Browse Products
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <?php elseif ($section === 'order_detail'): ?>
            <!-- ════════════════════════════════════════════
                 ORDER DETAIL (embedded in account layout)
                 ════════════════════════════════════════════ -->
            <?php $view->section('styles'); ?>
            <link rel="stylesheet" href="<?= $view->url('public/assets/customer/css/orders.css') ?>?v=<?= file_exists(PUBLIC_PATH . '/assets/customer/css/orders.css') ? filemtime(PUBLIC_PATH . '/assets/customer/css/orders.css') : '1' ?>">
            <?php $view->endSection(); ?>

            <!-- Back link -->
            <div style="margin-bottom:var(--c-space-4);">
                <a href="<?= $view->url('customer/account/orders') ?>"
                   class="c-btn c-btn-secondary c-btn-sm">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                    Back to My Orders
                </a>
            </div>

            <?php
            // Render the order detail view inline — reuse its full content
            $__accountLayoutInclude = true;
            if (file_exists(MODULES_PATH . '/customer/views/orders/show.php')) {
                include MODULES_PATH . '/customer/views/orders/show.php';
            }
            unset($__accountLayoutInclude);
            ?>

            <?php $view->section('scripts'); ?>
            <script src="<?= $view->asset('js/qrcode.min.js') ?>"></script>
            <script>
            (function () {
                'use strict';
                function initQr() {
                    var qrEl = document.getElementById('cordQrCode');
                    if (!qrEl || !qrEl.dataset.qr) return;
                    var payload = qrEl.dataset.qr;
                    if (typeof QRCode !== 'undefined') {
                        qrEl.innerHTML = '';
                        try {
                            new QRCode(qrEl, {
                                text:         payload,
                                width:        160,
                                height:       160,
                                colorDark:    '#1a2e1b',
                                colorLight:   '#ffffff',
                                correctLevel: QRCode.CorrectLevel.M
                            });
                        } catch (e) {
                            qrEl.classList.add('cord-qr-canvas--fallback');
                            qrEl.textContent = payload;
                        }
                    } else {
                        qrEl.classList.add('cord-qr-canvas--fallback');
                        qrEl.textContent = payload;
                    }
                }
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initQr);
                } else {
                    setTimeout(initQr, 150);
                }
            }());
            </script>
            <?php $view->endSection(); ?>

            <?php elseif ($section === 'order_receipt'): ?>
            <!-- ════════════════════════════════════════════
                 RECEIPT (embedded in account layout)
                 ════════════════════════════════════════════ -->
            <?php $view->section('styles'); ?>
            <link rel="stylesheet" href="<?= $view->url('public/assets/customer/css/orders.css') ?>?v=<?= file_exists(PUBLIC_PATH . '/assets/customer/css/orders.css') ? filemtime(PUBLIC_PATH . '/assets/customer/css/orders.css') : '1' ?>">
            <?php $view->endSection(); ?>

            <!-- Back link -->
            <div style="margin-bottom:var(--c-space-4);display:flex;gap:var(--c-space-2);">
                <a href="<?= $view->url('customer/account/orders/' . (int)($order['id'] ?? 0)) ?>"
                   class="c-btn c-btn-secondary c-btn-sm">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                    Back to Order
                </a>
                <a href="<?= $view->url('customer/account/orders') ?>"
                   class="c-btn c-btn-secondary c-btn-sm">
                    <i class="bi bi-list-ul" aria-hidden="true"></i>
                    My Orders
                </a>
            </div>

            <?php
            $__accountLayoutInclude = true;
            if (file_exists(MODULES_PATH . '/customer/views/orders/receipt.php')) {
                include MODULES_PATH . '/customer/views/orders/receipt.php';
            }
            unset($__accountLayoutInclude);
            ?>

            <?php $view->section('scripts'); ?>
            <script src="<?= $view->asset('js/qrcode.min.js') ?>"></script>
            <script>
            (function () {
                'use strict';
                function initQr() {
                    var el = document.getElementById('receiptQrCode');
                    if (!el || !el.dataset.qr) return;
                    var payload = el.dataset.qr;
                    if (typeof QRCode !== 'undefined') {
                        el.innerHTML = '';
                        try {
                            new QRCode(el, {
                                text:         payload,
                                width:        140,
                                height:       140,
                                colorDark:    '#1a2e1b',
                                colorLight:   '#ffffff',
                                correctLevel: QRCode.CorrectLevel.M
                            });
                        } catch (e) {
                            el.classList.add('cord-qr-canvas--fallback');
                            el.textContent = payload;
                        }
                    } else {
                        el.classList.add('cord-qr-canvas--fallback');
                        el.textContent = payload;
                    }
                }
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initQr);
                } else {
                    setTimeout(initQr, 150);
                }
            }());
            </script>
            <?php $view->endSection(); ?>

            <?php endif; ?>
    </div><!-- /.cac-layout -->

</div><!-- /.cac-page -->

<?php $view->section('scripts'); ?>
<script>
(function () {
    'use strict';

    // ── Profile edit toggle ───────────────────────────────
    window.cacToggleProfileEdit = function () {
        var view    = document.getElementById('cac-profile-view');
        var form    = document.getElementById('cac-profile-edit-form');
        var btn     = document.getElementById('cac-profile-edit-btn');
        var editing = form.style.display !== 'none';

        if (editing) {
            form.style.display = 'none';
            view.style.display = '';
            btn.setAttribute('aria-expanded', 'false');
            btn.innerHTML = '<i class="bi bi-pencil" aria-hidden="true"></i> Edit';
        } else {
            form.style.display = '';
            view.style.display = 'none';
            btn.setAttribute('aria-expanded', 'true');
            btn.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i> Cancel';
            document.getElementById('cac-first-name').focus();
        }
    };

    // ── Pickup preferences: wire shop option clicks ───────────
    // Clicking a shop in the pickup list uses the existing CA.ShopSelector
    // to persist the selection to the PHP session, same as navbar.
    var shopOptions = document.querySelectorAll('[data-ca-shop-option]');
    shopOptions.forEach(function (opt) {
        opt.addEventListener('click', function () {
            var id   = opt.getAttribute('data-ca-shop-option');
            var name = opt.getAttribute('data-ca-shop-option-name');
            if (window.CustomerApp && window.CustomerApp.ShopSelector) {
                window.CustomerApp.ShopSelector.select(id, name);
                // persist to PHP session
                var csrfMeta = document.querySelector('meta[name="csrf-token"]');
                var csrf     = csrfMeta ? csrfMeta.content : '';
                fetch((window.CustomerApp.appUrl || '') + '/customer/shops/select', {
                    method:  'POST',
                    headers: {
                        'Content-Type':     'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'csrf_token=' + encodeURIComponent(csrf)
                       + '&shop_id='   + encodeURIComponent(id)
                       + '&cart_items=' + encodeURIComponent('[]')
                }).then(function () {
                    // Visual feedback: mark selected
                    document.querySelectorAll('[data-ca-shop-option]').forEach(function (o) {
                        o.classList.toggle('selected', o.getAttribute('data-ca-shop-option') === id);
                        o.setAttribute('aria-pressed', o.getAttribute('data-ca-shop-option') === id ? 'true' : 'false');
                    });
                    if (window.CustomerApp && window.CustomerApp.Toast) {
                        window.CustomerApp.Toast.success('Shop Updated', 'Pickup shop set to ' + name);
                    }
                }).catch(function () {
                    if (window.CustomerApp && window.CustomerApp.Toast) {
                        window.CustomerApp.Toast.error('Update Failed', 'Could not save shop preference.');
                    }
                });
            }
        });

        // Keyboard: Enter/Space acts as click
        opt.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                opt.click();
            }
        });
    });
}());
</script>
<?php $view->endSection(); ?>
