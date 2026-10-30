<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : templates/customer.php
 * Purpose  : Customer-facing mini e-commerce layout.
 *            COMPLETELY separate from master.php.
 *            No admin sidebar. No admin topbar.
 *            No admin CSS. No admin JS.
 *            All customer styles are scoped under .customer-app
 * =========================================================
 *
 * @var \AAM\Core\View  $view
 * @var string          $content
 * @var string          $appName
 * @var string          $appUrl
 * @var string          $csrfToken
 * @var string|null     $pageTitle
 */

declare(strict_types=1);

$pageTitle = $pageTitle ?? 'AU-AgroSale';

// Detect resolved theme for no-flash inline script
// (mirrors customer.js Theme logic, but runs before paint)
$customerTheme = 'light'; // server default; JS will apply persisted preference on load
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="AU-AgroSale — Fresh agricultural products from Arsi University farms.">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">

    <title><?= e($pageTitle) ?> | AU-AgroSale</title>

    <link rel="icon" type="image/svg+xml" href="<?= $view->asset('images/favicon.svg') ?>">

    <!--
        Customer CSS — completely isolated from admin/dashboard/POS styles.
        Only Bootstrap Icons is shared (glyph font, no class conflicts).
    -->
    <link rel="stylesheet" href="<?= $view->asset('css/bootstrap-icons.css') ?>">
    <link rel="stylesheet" href="<?= $view->url('public/assets/customer/css/customer.css') ?>?v=<?= filemtime(PUBLIC_PATH . '/assets/customer/css/customer.css') ?>">

    <?php $view->yield('styles'); ?>

    <!--
        NO-FLASH THEME SCRIPT — must run before first paint.
        Applies .c-theme-dark / data-customer-theme="dark" immediately
        from localStorage so there is no flash of wrong theme.
        Runs synchronously in <head> — no DOMContentLoaded wait.
    -->
    <script>
    (function(){
        var k='ca_theme', p=null;
        try{ p=localStorage.getItem(k); }catch(e){}
        var dark=(p==='dark')||(p!=='light'&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches);
        // We cannot access .customer-app yet (DOM not built),
        // so write a one-shot inline style that is overridden once
        // the div renders. The real class application happens in
        // customer.js Theme.init() which runs on DOMContentLoaded,
        // but we set a data attribute on <html> for immediate CSS:
        if(dark){ document.documentElement.setAttribute('data-ca-prefers','dark'); }
    })();
    </script>
</head>
<body style="margin:0;padding:0;background:#f7f9f7;">

<!-- Skip to main content (accessibility) -->
<a href="#customer-main"
   style="position:absolute;top:-40px;left:8px;z-index:9999;background:#2e7d32;color:#fff;padding:8px 16px;border-radius:4px;text-decoration:none;font-size:14px;transition:top .2s;"
   onfocus="this.style.top='8px'" onblur="this.style.top='-40px'">
    Skip to main content
</a>

<!-- ── Customer app root ── ALL customer styles scoped here ── -->
<div class="customer-app" id="customerApp">

    <!-- ── Navbar ─────────────────────────────────────────── -->
    <header class="customer-navbar" role="banner">
        <div class="c-container customer-navbar__inner">

            <!-- Hamburger (mobile) -->
            <button class="customer-navbar__hamburger"
                    data-ca-menu-open
                    aria-label="Open menu"
                    aria-expanded="false"
                    aria-controls="customerMobileMenu">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <!-- Brand -->
            <a href="<?= $view->url('customer') ?>" class="customer-navbar__brand" aria-label="AU-AgroSale home">
                <div class="customer-navbar__logo" aria-hidden="true">🌿</div>
                <div>
                    <div class="customer-navbar__brand-name">AU-AgroSale</div>
                    <div class="customer-navbar__brand-sub">Arsi University</div>
                </div>
            </a>

            <!-- Search (desktop) -->
            <div class="customer-navbar__search-wrap" role="search">
                <div class="customer-navbar__search-pill">
                    <input type="search"
                           id="customerSearchInput"
                           class="customer-navbar__search-input"
                           placeholder="Search products, categories or shops..."
                           aria-label="Search products"
                           aria-autocomplete="list"
                           aria-controls="customerSearchSuggestions"
                           autocomplete="off"
                           spellcheck="false">
                    <button class="customer-navbar__search-btn"
                            type="button"
                            aria-label="Search"
                            onclick="(function(){var q=document.getElementById('customerSearchInput').value.trim();if(q){window.location.href=(window.CustomerApp&&window.CustomerApp.appUrl||'')+'/customer/products/search?q='+encodeURIComponent(q);}})()">
                        <i class="bi bi-search" aria-hidden="true"></i>
                    </button>
                </div>
                <!-- Suggestions dropdown — outside the flex pill -->
                <div id="customerSearchSuggestions"
                     class="customer-search-suggestions"
                     role="listbox"
                     aria-label="Search suggestions">
                </div>
            </div>

            <!-- Nav links (desktop) -->
            <?php
                $currentUri = $_SERVER['REQUEST_URI'] ?? '';
                $isHome     = rtrim($currentUri, '/') === rtrim(parse_url($view->url('customer'), PHP_URL_PATH), '/');
                $isProducts = str_contains($currentUri, '/customer/products');
                $isShops    = str_contains($currentUri, '/customer/shops');
                $isAbout    = str_contains($currentUri, '/customer/about');
                $isAccount  = str_contains($currentUri, '/customer/account') || str_contains($currentUri, '/customer/orders');
            ?>
            <nav class="customer-navbar__nav" aria-label="Main navigation">
                <a href="<?= $view->url('customer') ?>"
                   class="customer-navbar__nav-link <?= $isHome ? 'active' : '' ?>">
                    <i class="bi bi-house-fill" aria-hidden="true"></i>
                    Home
                </a>
                <a href="<?= $view->url('customer/products') ?>"
                   class="customer-navbar__nav-link <?= $isProducts ? 'active' : '' ?>">
                    <i class="bi bi-grid-fill" aria-hidden="true"></i>
                    Products
                </a>
                <a href="<?= $view->url('customer/shops') ?>"
                   class="customer-navbar__nav-link <?= $isShops ? 'active' : '' ?>">
                    <i class="bi bi-shop" aria-hidden="true"></i>
                    Shops
                </a>
                <a href="<?= $view->url('customer/about') ?>"
                   class="customer-navbar__nav-link <?= $isAbout ? 'active' : '' ?>">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    About
                </a>
            </nav>

            <!-- Actions -->
            <div class="customer-navbar__actions">

                <!-- Shop selector pill (desktop) -->
                <div class="customer-shop-selector" role="navigation" aria-label="Shop location">
                    <button class="customer-shop-selector__trigger"
                            data-ca-shop-trigger
                            aria-haspopup="listbox"
                            aria-label="Select shop location">
                        <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                        <span class="customer-shop-selector__name" data-ca-shop-name>Select Shop</span>
                        <i class="bi bi-chevron-down" aria-hidden="true"></i>
                    </button>
                    <div class="customer-shop-selector__dropdown"
                         role="listbox"
                         aria-label="Available shops">
                        <div class="customer-search-suggestions__section-title">Choose a shop</div>
                        <div id="navShopOptions"></div>
                    </div>
                </div>

                <!-- Cart icon + badge -->
                <a href="<?= $view->url('customer/cart') ?>"
                   class="customer-navbar__action-btn"
                   aria-label="Shopping cart">
                    <i class="bi bi-cart3" aria-hidden="true"></i>
                    <span class="customer-cart-badge"
                          data-ca-cart-count
                          style="display:none;"
                          aria-label="items in cart">0</span>
                </a>

                <!-- Separator: Cart | Profile -->
                <div class="customer-navbar__actions-sep" aria-hidden="true"></div>

                <!-- Profile icon + dropdown -->
                <?php
                    // Auth state — derived from server-side session, NOT localStorage
                    $isAuthenticated = \AAM\Core\SessionManager::isAuthenticated();
                    $sessionUserName = \AAM\Core\SessionManager::get(SESSION_USER_NAME);
                    $sessionName     = $sessionUserName
                                       ?: (string)($_SESSION['ca_customer_name'] ?? '');
                    $customerInitial = $sessionName
                                       ? mb_strtoupper(mb_substr(trim($sessionName), 0, 1))
                                       : null;
                ?>

                <?php if ($isAuthenticated): ?>
                <!-- Authenticated: circular avatar + chevron dropdown -->
                <div class="ca-account-menu" id="caAccountMenu">
                    <a href="<?= $view->url('customer/account') ?>"
                       class="ca-account-menu__trigger ca-account-menu__trigger--icon <?= $isAccount ? 'active' : '' ?>"
                       id="caAccountMenuLink"
                       aria-label="My account"
                       title="My Account">
                        <span class="ca-account-menu__avatar ca-account-menu__avatar--lg" aria-hidden="true"><?= e($customerInitial ?? '?') ?></span>
                    </a>
                    <button class="ca-account-menu__chevron-btn"
                            id="caAccountMenuTrigger"
                            data-ca-account-trigger
                            aria-haspopup="true"
                            aria-expanded="false"
                            aria-controls="caAccountMenuDropdown"
                            aria-label="Account menu"
                            title="Account options">
                        <i class="bi bi-chevron-down" aria-hidden="true"></i>
                    </button>
                    <div class="ca-account-menu__dropdown"
                         id="caAccountMenuDropdown"
                         role="menu"
                         aria-label="Account options">
                        <div class="ca-account-menu__header">
                            <div class="ca-account-menu__header-avatar" aria-hidden="true"><?= e($customerInitial ?? '?') ?></div>
                            <div class="ca-account-menu__header-info">
                                <div class="ca-account-menu__header-name"><?= e(trim($sessionName) ?: 'Customer') ?></div>
                                <div class="ca-account-menu__header-role">Customer</div>
                            </div>
                        </div>
                        <div class="ca-account-menu__divider" role="separator"></div>
                        <a href="<?= $view->url('customer/account') ?>" class="ca-account-menu__item" role="menuitem">
                            <span class="ca-account-menu__item-icon" aria-hidden="true"><i class="bi bi-person-circle"></i></span>
                            <span>My Account</span>
                        </a>
                        <a href="<?= $view->url('customer/orders') ?>" class="ca-account-menu__item" role="menuitem">
                            <span class="ca-account-menu__item-icon" aria-hidden="true"><i class="bi bi-bag-check"></i></span>
                            <span>My Orders</span>
                        </a>
                        <a href="<?= $view->url('customer/account/favorites') ?>" class="ca-account-menu__item" role="menuitem">
                            <span class="ca-account-menu__item-icon" aria-hidden="true"><i class="bi bi-heart"></i></span>
                            <span>Favorites</span>
                        </a>
                        <a href="<?= $view->url('customer/account/receipts') ?>" class="ca-account-menu__item" role="menuitem">
                            <span class="ca-account-menu__item-icon" aria-hidden="true"><i class="bi bi-receipt"></i></span>
                            <span>Receipts</span>
                        </a>
                        <div class="ca-account-menu__divider" role="separator"></div>
                        <a href="<?= $view->url('customer/account/signout') ?>"
                           class="ca-account-menu__item ca-account-menu__item--signout"
                           role="menuitem"
                           onclick="return confirm('Sign out of this session?')">
                            <span class="ca-account-menu__item-icon" aria-hidden="true"><i class="bi bi-box-arrow-right"></i></span>
                            <span>Sign Out</span>
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <!-- Guest: pill-shaped Sign In button matching navbar style -->
                <a href="<?= $view->url('customer/auth/login') ?>"
                   class="customer-navbar__account-btn"
                   aria-label="Sign in to your account">
                    <i class="bi bi-person-circle" aria-hidden="true"></i>
                    <span>Sign In</span>
                    <i class="bi bi-chevron-down" aria-hidden="true"></i>
                </a>
                <?php endif; ?>

                <!-- Separator: Profile | Theme -->
                <div class="customer-navbar__actions-sep" aria-hidden="true"></div>

                <!-- Dark / Light mode toggle -->
                <button class="customer-navbar__theme-btn"
                        data-ca-theme-toggle
                        aria-label="Toggle dark mode"
                        title="Toggle dark / light mode">
                    <i class="bi bi-moon-fill" aria-hidden="true"></i>
                </button>

            </div>
        </div>
    </header>

    <!-- Mobile search bar (below navbar on mobile, hidden on desktop via CSS) -->
    <div class="customer-mobile-search" id="customerMobileSearch" role="search" aria-label="Product search">
        <div class="customer-navbar__search-wrap" style="max-width:100%;">
            <div class="customer-navbar__search-pill">
                <input type="search"
                       id="customerMobileSearchInput"
                       class="customer-navbar__search-input"
                       placeholder="Search products…"
                       aria-label="Search products"
                       autocomplete="off">
                <button class="customer-navbar__search-btn"
                        type="button"
                        aria-label="Search">
                    <i class="bi bi-search" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- ── Main Content ────────────────────────────────────── -->
    <main id="customer-main" tabindex="-1">
        <?= $content ?>
    </main>

    <!-- ── Mobile menu drawer ─────────────────────────────── -->
    <div id="customerMobileMenu"
         class="customer-mobile-menu"
         role="dialog"
         aria-modal="true"
         aria-label="Navigation menu">
        <div class="customer-mobile-menu__backdrop" aria-hidden="true"></div>
        <nav class="customer-mobile-menu__drawer">
            <div class="customer-mobile-menu__header">
                <div style="display:flex;align-items:center;gap:8px;">
                    <div class="customer-navbar__logo" style="width:30px;height:30px;font-size:15px;" aria-hidden="true">🌿</div>
                    <span style="font-weight:700;color:var(--c-text);font-size:var(--c-size-base);">AU-AgroSale</span>
                </div>
                <button class="customer-mobile-menu__close"
                        data-ca-menu-close
                        aria-label="Close menu">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
            <div class="customer-mobile-menu__nav" role="list">
                <a href="<?= $view->url('customer') ?>"
                   class="customer-mobile-menu__nav-item <?= $isHome ? 'active' : '' ?>"
                   role="listitem">
                    <i class="bi bi-house-fill" aria-hidden="true"></i>
                    Home
                </a>
                <a href="<?= $view->url('customer/products') ?>"
                   class="customer-mobile-menu__nav-item <?= $isProducts ? 'active' : '' ?>"
                   role="listitem">
                    <i class="bi bi-grid-fill" aria-hidden="true"></i>
                    Products
                </a>
                <a href="<?= $view->url('customer/shops') ?>"
                   class="customer-mobile-menu__nav-item <?= $isShops ? 'active' : '' ?>"
                   role="listitem">
                    <i class="bi bi-shop" aria-hidden="true"></i>
                    Shops
                </a>
                <a href="<?= $view->url('customer/about') ?>"
                   class="customer-mobile-menu__nav-item <?= $isAbout ? 'active' : '' ?>"
                   role="listitem">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    About
                </a>
                <a href="<?= $view->url('customer/cart') ?>"
                   class="customer-mobile-menu__nav-item"
                   role="listitem">
                    <i class="bi bi-bag" aria-hidden="true"></i>
                    Cart
                    <span class="customer-cart-badge"
                          data-ca-cart-count
                          style="display:none;position:static;margin-left:auto;"
                          aria-label="items in cart">0</span>
                </a>

                <hr class="c-divider" style="margin:var(--c-space-3) 0;">

                <!-- My Account section in mobile drawer -->
                <?php
                    $isAuthMob  = \AAM\Core\SessionManager::isAuthenticated();
                    $mobUname   = \AAM\Core\SessionManager::get(SESSION_USER_NAME);
                    $mobName    = $mobUname ?: (string)($_SESSION['ca_customer_name'] ?? '');
                    $mobInitial = $mobName ? mb_strtoupper(mb_substr(trim($mobName), 0, 1)) : null;
                ?>
                <div class="customer-mobile-menu__account-header">
                    <div class="customer-mobile-menu__account-avatar" aria-hidden="true"><?= e($mobInitial ?? '?') ?></div>
                    <div>
                        <div class="customer-mobile-menu__account-name"><?= $mobName ? e($mobName) : 'Guest' ?></div>
                        <div class="customer-mobile-menu__account-role"><?= $isAuthMob ? 'Customer Account' : 'Guest' ?></div>
                    </div>
                </div>

                <?php if ($isAuthMob): ?>
                <a href="<?= $view->url('customer/account') ?>"
                   class="customer-mobile-menu__nav-item <?= $isAccount ? 'active' : '' ?>"
                   role="listitem">
                    <i class="bi bi-person-circle" aria-hidden="true"></i>
                    My Account
                </a>
                <a href="<?= $view->url('customer/orders') ?>"
                   class="customer-mobile-menu__nav-item"
                   role="listitem">
                    <i class="bi bi-bag-check" aria-hidden="true"></i>
                    My Orders
                </a>
                <a href="<?= $view->url('customer/account/favorites') ?>"
                   class="customer-mobile-menu__nav-item"
                   role="listitem">
                    <i class="bi bi-heart" aria-hidden="true"></i>
                    Favorites
                </a>
                <a href="<?= $view->url('customer/account/receipts') ?>"
                   class="customer-mobile-menu__nav-item"
                   role="listitem">
                    <i class="bi bi-receipt" aria-hidden="true"></i>
                    Receipts
                </a>
                <a href="<?= $view->url('customer/account/signout') ?>"
                   class="customer-mobile-menu__nav-item customer-mobile-menu__nav-item--signout"
                   role="listitem"
                   onclick="return confirm('Sign out of this session?')">
                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                    Sign Out
                </a>
                <?php else: ?>
                <a href="<?= $view->url('customer/auth/login') ?>"
                   class="customer-mobile-menu__nav-item"
                   role="listitem">
                    <i class="bi bi-person-circle" aria-hidden="true"></i>
                    Sign In / Sign Up
                </a>
                <?php endif; ?>

                <hr class="c-divider" style="margin:var(--c-space-3) 0;">
                <!-- Theme toggle in mobile menu -->
                <button class="customer-mobile-menu__nav-item"
                        data-ca-theme-toggle
                        style="background:none;border:none;cursor:pointer;width:100%;text-align:left;"
                        aria-label="Toggle theme">
                    <i class="bi bi-moon-fill" aria-hidden="true"></i>
                    Toggle Dark Mode
                </button>
            </div>
        </nav>
    </div>

    <!-- Toast container -->
    <div class="customer-toast-container" role="region" aria-label="Notifications" aria-live="polite"></div>

</div><!-- /.customer-app -->

<!--
    Bootstrap 5 JS for accordion / collapse (categories etc.)
    Loaded here ONLY for the customer layout.
    This is the same bundle already present in public/assets/js/.
    It is safe to load again — Bootstrap 5 is idempotent.
    If you prefer a separate copy, place it in customer/js/.
-->
<script src="<?= $view->asset('js/bootstrap.bundle.min.js') ?>"></script>

<!-- Customer JS -->
<script src="<?= $view->url('public/assets/customer/js/customer.js') ?>?v=<?= filemtime(PUBLIC_PATH . '/assets/customer/js/customer.js') ?>"></script>
<script src="<?= $view->url('public/assets/customer/js/cart.js') ?>?v=<?= filemtime(PUBLIC_PATH . '/assets/customer/js/cart.js') ?>"></script>
<script src="<?= $view->url('public/assets/customer/js/search.js') ?>?v=<?= filemtime(PUBLIC_PATH . '/assets/customer/js/search.js') ?>"></script>
<script src="<?= $view->url('public/assets/customer/js/favorites.js') ?>?v=<?= file_exists(PUBLIC_PATH . '/assets/customer/js/favorites.js') ? filemtime(PUBLIC_PATH . '/assets/customer/js/favorites.js') : '1' ?>"></script>

<!-- Global JS context for customer pages -->
<script>
    window.CustomerApp = window.CustomerApp || {};
    window.CustomerApp.appUrl       = '<?= e(rtrim((string)\AAM\Config\Application::get('url', ''), '/')) ?>';
    window.CustomerApp.csrfToken    = '<?= e($csrfToken) ?>';
    window.CustomerApp.selectedShopId   = <?= (int)($_SESSION['ca_selected_shop_id']   ?? 0) ?>;
    window.CustomerApp.selectedShopName = <?= json_encode((string)($_SESSION['ca_selected_shop_name'] ?? ''), JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php
// Inject all active shops into every customer page so the navbar dropdown works everywhere.
// Uses existing Database connection — tiny query, cached by PHP per request.
try {
    $db = \AAM\Core\Database::getInstance();
    $navShops = $db->fetchAll(
        "SELECT id, COALESCE(shop_name, name) AS shop_name, location
         FROM shops
         WHERE is_active = 1 AND deleted_at IS NULL
         ORDER BY name ASC"
    );
} catch (\Throwable $e) {
    $navShops = [];
}
?>
<script>
(function(){
    var CA = window.CustomerApp || {};
    var activeId = CA.selectedShopId || 0;
    var shops = <?= json_encode(
        array_map(fn($s) => [
            'id'       => (int)$s['id'],
            'name'     => $s['shop_name'] ?? '',
            'location' => $s['location']  ?? '',
        ], $navShops),
        JSON_UNESCAPED_UNICODE
    ) ?>;

    function buildNavDropdown() {
        var container = document.getElementById('navShopOptions');
        if (!container || !shops.length) return;
        // Only build if not already populated by page-level script
        if (container.children.length > 0) return;
        var html = '';
        shops.forEach(function(s) {
            var active = activeId && s.id == activeId;
            html +=
                '<button type="button"' +
                ' class="customer-shop-selector__option' + (active ? ' selected' : '') + '"' +
                ' data-ca-shop-option="'      + s.id  + '"' +
                ' data-ca-shop-option-name="' + s.name.replace(/"/g, '&quot;') + '"' +
                ' role="option">' +
                '<div class="customer-shop-selector__option-icon">' +
                  '<i class="bi bi-' + (active ? 'check-circle-fill' : 'shop') + '"' +
                  (active ? ' style="color:var(--c-primary);"' : '') + '></i>' +
                '</div>' +
                '<div style="flex:1;min-width:0;">' +
                  '<div style="font-size:var(--c-size-sm);font-weight:' + (active ? '600' : '500') + ';' +
                  (active ? 'color:var(--c-primary);' : '') + '">' + s.name + '</div>' +
                  (s.location ? '<div style="font-size:var(--c-size-xs);color:var(--c-text-muted);">' + s.location + '</div>' : '') +
                '</div>' +
                '</button>';
        });
        container.innerHTML = html;
    }

    // Update navbar shop name from session
    function setNavShopName() {
        var name = CA.selectedShopName || '';
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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            buildNavDropdown();
            setNavShopName();
        });
    } else {
        buildNavDropdown();
        setNavShopName();
    }
}());
</script>

<?php $view->yield('scripts'); ?>

<?php
// ── Flash message → customer toast ──────────────────────────────────────────
// The customer layout has no HTML flash banner — it uses the JS toast system.
// Inject any pending flash message as a one-shot CA.Toast call.
$_caFlash = \AAM\Core\SessionManager::get(SESSION_FLASH);
if ($_caFlash):
    \AAM\Core\SessionManager::delete(SESSION_FLASH);
    $_caType = match ((string)($_caFlash['type'] ?? '')) {
        FLASH_SUCCESS => 'success',
        FLASH_ERROR   => 'error',
        FLASH_WARNING => 'warning',
        default       => 'info',
    };
    $_caTitle = match ($_caType) {
        'success' => 'Success',
        'error'   => 'Error',
        'warning' => 'Warning',
        default   => 'Notice',
    };
    $_caMsg = addslashes(htmlspecialchars_decode(strip_tags((string)($_caFlash['message'] ?? ''))));
?>
<script>
(function() {
    function fireFlash() {
        if (window.CustomerApp && window.CustomerApp.Toast) {
            window.CustomerApp.Toast.<?= $_caType ?>('<?= $_caTitle ?>', '<?= $_caMsg ?>');
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fireFlash);
    } else {
        setTimeout(fireFlash, 100);
    }
}());
</script>
<?php endif; ?>

</body>
</html>
