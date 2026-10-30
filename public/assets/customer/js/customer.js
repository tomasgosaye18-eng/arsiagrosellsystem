/**
 * ============================================================
 * AU-AgroSale — Customer Frontend Bootstrap
 * customer.js
 * ============================================================
 * Initialises the customer frontend.
 * Manages: theme persistence, mobile menu, shop selector,
 * toast notifications, global utilities.
 *
 * Isolated from admin/dashboard JS via window.CustomerApp namespace.
 * ============================================================
 */

(function () {
    'use strict';

    /* ── Namespace ────────────────────────────────────────── */
    window.CustomerApp = window.CustomerApp || {};
    const CA = window.CustomerApp;

    /* ── Storage keys ─────────────────────────────────────── */
    const THEME_KEY      = 'ca_theme';       // 'light' | 'dark' | 'system'
    const SHOP_KEY       = 'ca_selected_shop'; // shop id string
    const CART_KEY       = 'ca_cart';

    /* ── Resolved app root (for AJAX calls) ──────────────── */
    CA.appUrl = (typeof window.AAM !== 'undefined' && window.AAM.appUrl)
        ? window.AAM.appUrl.replace(/\/$/, '')
        : '';

    /* ══════════════════════════════════════════════════════
       THEME
       ══════════════════════════════════════════════════════ */
    CA.Theme = (function () {

        const root = document.querySelector('.customer-app');

        function getPreference() {
            try { return localStorage.getItem(THEME_KEY) || 'system'; }
            catch (_) { return 'system'; }
        }

        function resolve(pref) {
            if (pref === 'dark')  return 'dark';
            if (pref === 'light') return 'light';
            return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
                ? 'dark' : 'light';
        }

        function apply(pref) {
            if (!root) return;
            const resolved = resolve(pref);
            if (resolved === 'dark') {
                root.setAttribute('data-customer-theme', 'dark');
                root.classList.add('c-theme-dark');
                root.classList.remove('c-theme-light');
            } else {
                root.removeAttribute('data-customer-theme');
                root.classList.remove('c-theme-dark');
                root.classList.add('c-theme-light');
            }
            // Remove the pre-flash attribute from <html> once real classes are applied
            document.documentElement.removeAttribute('data-ca-prefers');
            // Update toggle button aria labels
            document.querySelectorAll('[data-ca-theme-toggle]').forEach(function (btn) {
                btn.setAttribute('aria-label',
                    resolved === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.className = resolved === 'dark'
                        ? 'bi bi-sun-fill'
                        : 'bi bi-moon-fill';
                }
            });
        }

        function toggle() {
            const current = resolve(getPreference());
            const next    = current === 'dark' ? 'light' : 'dark';
            try { localStorage.setItem(THEME_KEY, next); } catch (_) {}
            apply(next);
        }

        function init() {
            apply(getPreference());
            // Listen for OS theme changes when set to 'system'
            if (window.matchMedia) {
                window.matchMedia('(prefers-color-scheme: dark)')
                    .addEventListener('change', function () {
                        if (getPreference() === 'system') apply('system');
                    });
            }
            // Wire toggle buttons
            document.addEventListener('click', function (e) {
                if (e.target.closest('[data-ca-theme-toggle]')) toggle();
            });
        }

        return { init: init, toggle: toggle, current: function () { return resolve(getPreference()); } };
    }());

    /* ══════════════════════════════════════════════════════
       MOBILE MENU
       ══════════════════════════════════════════════════════ */
    CA.MobileMenu = (function () {

        var menu, backdrop, openBtn, closeBtn;

        function open() {
            if (!menu) return;
            menu.classList.add('open');
            document.body.style.overflow = 'hidden';
            if (closeBtn) closeBtn.focus();
        }

        function close() {
            if (!menu) return;
            menu.classList.remove('open');
            document.body.style.overflow = '';
            if (openBtn) openBtn.focus();
        }

        function init() {
            menu     = document.getElementById('customerMobileMenu');
            backdrop = document.querySelector('.customer-mobile-menu__backdrop');
            openBtn  = document.querySelector('[data-ca-menu-open]');
            closeBtn = document.querySelector('[data-ca-menu-close]');

            if (openBtn)  openBtn.addEventListener('click', open);
            if (closeBtn) closeBtn.addEventListener('click', close);
            if (backdrop) backdrop.addEventListener('click', close);

            // Close on ESC
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && menu && menu.classList.contains('open')) close();
            });
        }

        return { init: init, open: open, close: close };
    }());

    /* ══════════════════════════════════════════════════════
       SHOP SELECTOR
       ══════════════════════════════════════════════════════ */
    CA.ShopSelector = (function () {

        var currentShopId   = null;
        var currentShopName = null;

        function select(id, name) {
            currentShopId   = id;
            currentShopName = name;
            try {
                localStorage.setItem(SHOP_KEY, JSON.stringify({ id: id, name: name }));
            } catch (_) {}
            _updateUI();
            _dispatchChange();
        }

        function getSelected() {
            try {
                var s = localStorage.getItem(SHOP_KEY);
                return s ? JSON.parse(s) : null;
            } catch (_) { return null; }
        }

        function _updateUI() {
            // Update navbar shop selector display
            document.querySelectorAll('[data-ca-shop-name]').forEach(function (el) {
                el.textContent = currentShopName || 'Select Shop';
            });
            // Toggle has-selection class on trigger
            document.querySelectorAll('[data-ca-shop-trigger]').forEach(function (btn) {
                btn.classList.toggle('has-selection', !!currentShopId);
                btn.setAttribute('aria-label',
                    currentShopId
                        ? 'Shop location: ' + (currentShopName || 'selected')
                        : 'Select shop location'
                );
            });
            // Mark active option in dropdown
            document.querySelectorAll('[data-ca-shop-option]').forEach(function (el) {
                el.classList.toggle('selected', el.dataset.caShopOption == currentShopId);
            });
            // Mark active pill in strip
            document.querySelectorAll('[data-ca-shop-pill]').forEach(function (el) {
                el.classList.toggle('active', el.dataset.caShopPill == currentShopId);
                el.setAttribute('aria-pressed', el.dataset.caShopPill == currentShopId ? 'true' : 'false');
            });
            // Mark active card on home page
            document.querySelectorAll('[data-ca-shop-card]').forEach(function (el) {
                el.classList.toggle('selected', el.dataset.caShopCard == currentShopId);
            });
        }

        function _dispatchChange() {
            document.dispatchEvent(new CustomEvent('ca:shopChanged', {
                detail: { id: currentShopId, name: currentShopName }
            }));
        }

        function _persistToSession(id, name) {
            // Sync selection to PHP session via AJAX so server-side pages
            // (product catalog, availability) know the selected shop.
            var cartItems = [];
            try {
                var raw = localStorage.getItem('ca_cart');
                if (raw) {
                    var parsed = JSON.parse(raw);
                    if (Array.isArray(parsed)) {
                        cartItems = parsed.map(function (i) {
                            return { product_id: i.productId };
                        });
                    }
                }
            } catch (_) {}

            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            var csrf     = csrfMeta ? csrfMeta.content : '';

            fetch((CA.appUrl || '') + '/customer/shops/select', {
                method:  'POST',
                headers: {
                    'Content-Type':     'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'csrf_token='   + encodeURIComponent(csrf)
                    + '&shop_id='     + encodeURIComponent(id)
                    + '&cart_items='  + encodeURIComponent(JSON.stringify(cartItems))
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success && res.data && res.data.had_conflict && res.data.unavailable_ids && res.data.unavailable_ids.length > 0) {
                    // Warn about cart conflict
                    if (CA.Toast) {
                        CA.Toast.warning(
                            'Some cart items unavailable',
                            'Items from your cart may not be available at ' + (name || 'the new shop') + '.'
                        );
                    }
                    document.dispatchEvent(new CustomEvent('ca:shopCartConflict', {
                        detail: {
                            newShopId:      id,
                            newShopName:    name,
                            unavailableIds: res.data.unavailable_ids
                        }
                    }));
                }
            })
            .catch(function () {
                // Session persist failed — localStorage still works for client-side UX
            });
        }

        function init() {
            var saved = getSelected();
            // If server already has a selection (from PHP session), prefer it
            // to ensure localStorage and session stay in sync on first load.
            var serverShopId   = CA.selectedShopId   || 0;
            var serverShopName = CA.selectedShopName  || '';
            if (serverShopId && serverShopName && (!saved || saved.id != serverShopId)) {
                // Server has a shop selected that localStorage doesn't know about
                try {
                    localStorage.setItem(SHOP_KEY, JSON.stringify({ id: serverShopId, name: serverShopName }));
                } catch (_) {}
                saved = { id: serverShopId, name: serverShopName };
            }
            if (saved) {
                currentShopId   = saved.id;
                currentShopName = saved.name;
                _updateUI();
            }

            // Wire shop option clicks (dropdown)
            document.addEventListener('click', function (e) {
                var opt = e.target.closest('[data-ca-shop-option]');
                if (opt) {
                    select(opt.dataset.caShopOption, opt.dataset.caShopOptionName);
                    _persistToSession(opt.dataset.caShopOption, opt.dataset.caShopOptionName);
                    // Close dropdown
                    var dd = opt.closest('.customer-shop-selector__dropdown');
                    if (dd) dd.classList.remove('open');
                }
                // Pill clicks
                var pill = e.target.closest('[data-ca-shop-pill]');
                if (pill) {
                    select(pill.dataset.caShopPill, pill.dataset.caShopPillName);
                    _persistToSession(pill.dataset.caShopPill, pill.dataset.caShopPillName);
                }
                // Card clicks
                var card = e.target.closest('[data-ca-shop-card]');
                if (card && !e.target.closest('.c-btn')) {
                    select(card.dataset.caShopCard, card.dataset.caShopCardName);
                    _persistToSession(card.dataset.caShopCard, card.dataset.caShopCardName);
                }
            });

            // Toggle selector dropdown
            document.addEventListener('click', function (e) {
                var trigger = e.target.closest('[data-ca-shop-trigger]');
                // "Choose a shop" prompt button opens the navbar shop selector
                var openBtn = e.target.closest('[data-ca-open-shop-select]');
                if (openBtn) {
                    var dd = document.querySelector('.customer-shop-selector__dropdown');
                    if (dd) {
                        dd.classList.toggle('open');
                        var t = document.querySelector('[data-ca-shop-trigger]');
                        if (t) t.focus();
                    }
                    e.stopPropagation();
                    return;
                }
                if (trigger) {
                    var dd = trigger.nextElementSibling;
                    if (dd) dd.classList.toggle('open');
                    e.stopPropagation();
                    return;
                }
                // Close on outside click
                document.querySelectorAll('.customer-shop-selector__dropdown.open').forEach(function (dd) {
                    dd.classList.remove('open');
                });
            });
        }

        return {
            init: init,
            select: select,
            getSelected: getSelected,
            getId: function () { return currentShopId; }
        };
    }());

    /* ══════════════════════════════════════════════════════
       TOAST NOTIFICATIONS
       ══════════════════════════════════════════════════════ */
    CA.Toast = (function () {

        var container;

        var ICONS = {
            success: 'bi-check-circle-fill',
            error:   'bi-x-circle-fill',
            warning: 'bi-exclamation-triangle-fill',
            info:    'bi-info-circle-fill'
        };

        function _getContainer() {
            if (!container) {
                container = document.querySelector('.customer-toast-container');
                if (!container) {
                    container = document.createElement('div');
                    container.className = 'customer-toast-container';
                    document.querySelector('.customer-app').appendChild(container);
                }
            }
            return container;
        }

        function show(type, title, message, duration) {
            duration = duration || 4000;
            var c    = _getContainer();
            var toast = document.createElement('div');
            toast.className = 'customer-toast customer-toast--' + type;
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');
            toast.innerHTML =
                '<i class="bi ' + (ICONS[type] || ICONS.info) + ' customer-toast__icon"></i>' +
                '<div class="customer-toast__body">' +
                  '<div class="customer-toast__title">' + _esc(title) + '</div>' +
                  (message ? '<div class="customer-toast__msg">' + _esc(message) + '</div>' : '') +
                '</div>' +
                '<button class="customer-toast__close" aria-label="Close">' +
                  '<i class="bi bi-x"></i>' +
                '</button>';

            c.appendChild(toast);

            toast.querySelector('.customer-toast__close').addEventListener('click', function () {
                _remove(toast);
            });

            if (duration > 0) {
                setTimeout(function () { _remove(toast); }, duration);
            }
        }

        function _remove(el) {
            el.style.opacity = '0';
            el.style.transform = 'translateX(24px)';
            el.style.transition = 'all .25s ease';
            setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 250);
        }

        function _esc(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        return {
            success: function (title, msg, dur) { show('success', title, msg, dur); },
            error:   function (title, msg, dur) { show('error',   title, msg, dur); },
            warning: function (title, msg, dur) { show('warning', title, msg, dur); },
            info:    function (title, msg, dur) { show('info',    title, msg, dur); }
        };
    }());

    /* ══════════════════════════════════════════════════════
       CART (badge counter — logic in cart.js)
       ══════════════════════════════════════════════════════ */
    CA.CartBadge = (function () {

        function update(count) {
            document.querySelectorAll('[data-ca-cart-count]').forEach(function (el) {
                el.textContent = count > 99 ? '99+' : String(count);
                el.style.display = count > 0 ? 'flex' : 'none';
            });
        }

        function init() {
            // Read cart from localStorage and update badge immediately
            try {
                var raw  = localStorage.getItem(CART_KEY);
                var cart = raw ? JSON.parse(raw) : [];
                var total = Array.isArray(cart)
                    ? cart.reduce(function (s, i) { return s + (parseInt(i.qty, 10) || 0); }, 0)
                    : 0;
                update(total);
            } catch (_) { update(0); }
        }

        return { init: init, update: update };
    }());

    /* ══════════════════════════════════════════════════════
       UTILITY
       ══════════════════════════════════════════════════════ */
    CA.utils = {
        /** Debounce a function call */
        debounce: function (fn, delay) {
            var t;
            return function () {
                var ctx  = this;
                var args = arguments;
                clearTimeout(t);
                t = setTimeout(function () { fn.apply(ctx, args); }, delay);
            };
        },
        /** Format ETB price */
        formatPrice: function (amount) {
            var n = parseFloat(amount) || 0;
            return 'ETB\u00a0' + n.toLocaleString('en-ET', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },
        /** Escape HTML */
        esc: function (str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }
    };

    /* ══════════════════════════════════════════════════════
       ADD-TO-CART INTERACTION
       Handles the + button on product cards.
       Fires a 'ca:addToCart' event that cart.js listens to.
       cart.js then calls the server endpoint for validation.
       ══════════════════════════════════════════════════════ */
    CA.AddToCart = (function () {

        function _onAddClick(btn) {
            var productId   = btn.dataset.productId;
            var productName = btn.dataset.productName || '';
            var price       = parseFloat(btn.dataset.productPrice) || 0;
            var unit        = btn.dataset.unit || '';
            var maxQty      = parseFloat(btn.dataset.maxQty) || 0;

            if (!productId) return;

            // Dispatch event — cart.js handles the server call
            // Pass the button element so cart.js can show loading state
            document.dispatchEvent(new CustomEvent('ca:addToCart', {
                detail: {
                    productId:   productId,
                    productName: productName,
                    price:       price,
                    unit:        unit,
                    qty:         1,
                    maxQty:      maxQty,
                    _btn:        btn         // pass ref for loading state
                }
            }));
        }

        function init() {
            document.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-ca-add-to-cart]');
                if (btn && !btn.disabled) _onAddClick(btn);
            });
        }

        return { init: init };
    }());

    /* ══════════════════════════════════════════════════════
       LOADING STATES
       Handles skeleton cards and spinner visibility.
       ══════════════════════════════════════════════════════ */
    CA.Loading = (function () {

        function showPageLoader() {
            var el = document.getElementById('caPageLoader');
            if (el) el.style.display = 'flex';
        }

        function hidePageLoader() {
            var el = document.getElementById('caPageLoader');
            if (el) el.style.display = 'none';
        }

        function init() {
            // Hide any skeleton cards once images load
            document.querySelectorAll('.customer-product-card.skeleton').forEach(function (card) {
                card.classList.remove('skeleton');
            });
        }

        return { init: init, show: showPageLoader, hide: hidePageLoader };
    }());

    /* ══════════════════════════════════════════════════════
       CATEGORY SELECTION (home page filter)
       ══════════════════════════════════════════════════════ */
    CA.CategoryFilter = (function () {

        function init() {
            document.addEventListener('click', function (e) {
                var card = e.target.closest('.customer-category-card');
                if (!card) return;
                // Visual active state
                document.querySelectorAll('.customer-category-card').forEach(function (c) {
                    c.classList.remove('active');
                });
                card.classList.add('active');
                // Navigation happens via the <a href> — no JS needed
            });
        }

        return { init: init };
    }());

    /* ══════════════════════════════════════════════════════
       PRODUCT FILTER (section 13 — "Product filtering")
       Pill-tab category/filter selection with visual state.
       Actual data filtering happens server-side via URL params
       or AJAX (Phase 3). This module handles the visual state.
       ══════════════════════════════════════════════════════ */
    CA.ProductFilter = (function () {

        function init() {
            // Pill tab clicks
            document.addEventListener('click', function (e) {
                var tab = e.target.closest('.customer-pill-tab');
                if (!tab) return;

                var group = tab.closest('.customer-pill-tabs');
                if (!group) return;

                // Deactivate all tabs in this group
                group.querySelectorAll('.customer-pill-tab').forEach(function (t) {
                    t.classList.remove('active');
                    t.setAttribute('aria-selected', 'false');
                });

                // Activate clicked tab
                tab.classList.add('active');
                tab.setAttribute('aria-selected', 'true');

                // If tab has a data-filter-url, navigate there
                if (tab.dataset.filterUrl) {
                    window.location.href = tab.dataset.filterUrl;
                    return;
                }

                // Otherwise dispatch event for AJAX filtering (Phase 3)
                var filterValue = tab.dataset.filterValue;
                var filterType  = tab.dataset.filterType;
                if (filterValue) {
                    document.dispatchEvent(new CustomEvent('ca:filterChanged', {
                        detail: { type: filterType, value: filterValue }
                    }));
                }
            });
        }

        return { init: init };
    }());

    /* ══════════════════════════════════════════════════════
       ACCOUNT MENU DROPDOWN
       The avatar icon (#caAccountMenuLink) is a direct link
       to My Account Center. The chevron button (#caAccountMenuTrigger)
       opens the quick-access dropdown.
       ══════════════════════════════════════════════════════ */
    CA.AccountMenu = (function () {

        var menu, trigger, dropdown;

        function open() {
            if (!dropdown) return;
            dropdown.classList.add('open');
            if (trigger) trigger.setAttribute('aria-expanded', 'true');
        }

        function close() {
            if (!dropdown) return;
            dropdown.classList.remove('open');
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
        }

        function toggle() {
            if (dropdown && dropdown.classList.contains('open')) {
                close();
            } else {
                open();
            }
        }

        function init() {
            menu     = document.getElementById('caAccountMenu');
            // Trigger is now the chevron button, not the avatar link
            trigger  = document.getElementById('caAccountMenuTrigger');
            dropdown = document.getElementById('caAccountMenuDropdown');

            if (!trigger || !dropdown) return;

            // Toggle dropdown on chevron click
            trigger.addEventListener('click', function (e) {
                e.stopPropagation();
                toggle();
            });

            // Close on outside click
            document.addEventListener('click', function (e) {
                if (menu && !menu.contains(e.target)) {
                    close();
                }
            });

            // Close on Escape key
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && dropdown.classList.contains('open')) {
                    close();
                    if (trigger) trigger.focus();
                }
            });

            // Keyboard navigation within dropdown items
            dropdown.addEventListener('keydown', function (e) {
                var items = Array.from(dropdown.querySelectorAll('.ca-account-menu__item'));
                var idx   = items.indexOf(document.activeElement);
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    var next = items[idx + 1] || items[0];
                    if (next) next.focus();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    var prev = items[idx - 1] || items[items.length - 1];
                    if (prev) prev.focus();
                }
            });
        }

        return { init: init, open: open, close: close };
    }());

    /* ══════════════════════════════════════════════════════
       BOOT
       ══════════════════════════════════════════════════════ */
    function boot() {
        CA.Theme.init();
        CA.MobileMenu.init();
        CA.ShopSelector.init();
        CA.AccountMenu.init();
        CA.CartBadge.init();
        CA.AddToCart.init();
        CA.Loading.init();
        CA.CategoryFilter.init();
        CA.ProductFilter.init();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

}());
