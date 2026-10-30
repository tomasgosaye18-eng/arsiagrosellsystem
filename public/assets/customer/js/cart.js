/**
 * ============================================================
 * AU-AgroSale — Customer Cart
 * cart.js
 * ============================================================
 * Phase 4 — Session-backed cart with server-side validation.
 *
 * Add-to-cart:
 *   1. Calls POST /customer/cart/add (server validates stock + price)
 *   2. Updates localStorage mirror for badge counts on other pages
 *   3. Updates badge from server's cart_count response
 *
 * Business rule (spec §17):
 *   The server is the source of truth.
 *   Price, stock, shop — ALL validated server-side.
 *   LocalStorage is only a UI mirror for the badge counter.
 * ============================================================
 */

(function () {
    'use strict';

    window.CustomerApp = window.CustomerApp || {};
    var CA = window.CustomerApp;

    var CART_KEY = 'ca_cart';

    /* ══════════════════════════════════════════════════════
       CART SERVICE
       ══════════════════════════════════════════════════════ */
    CA.Cart = (function () {

        /* ── Internal helpers ────────────────────────────── */
        function _read() {
            try {
                var raw = localStorage.getItem(CART_KEY);
                return raw ? JSON.parse(raw) : [];
            } catch (_) { return []; }
        }

        function _write(cart) {
            try {
                localStorage.setItem(CART_KEY, JSON.stringify(cart));
            } catch (_) {}
        }

        function _total(cart) {
            return cart.reduce(function (s, i) {
                return s + (parseInt(i.qty, 10) || 0);
            }, 0);
        }

        function _sync(cart) {
            if (CA.CartBadge) CA.CartBadge.update(_total(cart));
            document.dispatchEvent(new CustomEvent('ca:cartUpdated', { detail: { cart: cart } }));
        }

        /* ── Public API ──────────────────────────────────── */

        /**
         * Get all items in cart.
         * @returns {Array}
         */
        function getAll() {
            return _read();
        }

        /**
         * Get total item count (sum of all quantities).
         * @returns {number}
         */
        function count() {
            return _total(_read());
        }

        /**
         * Get cart subtotal (price × qty for all items).
         * @returns {number}
         */
        function subtotal() {
            return _read().reduce(function (s, i) {
                return s + ((parseFloat(i.price) || 0) * (parseInt(i.qty, 10) || 0));
            }, 0);
        }

        /**
         * Add an item or increase its quantity.
         * Respects maxQty if provided.
         *
         * @param {string} productId
         * @param {string} productName
         * @param {number} price
         * @param {string} unit
         * @param {number} qty       — units to add (default 1)
         * @param {number} maxQty   — available shop stock limit (0 = no limit)
         * @param {string} shopId   — selected shop id (from ShopSelector)
         * @param {string} shopName
         * @returns {boolean} — false if stock limit would be exceeded
         */
        function addItem(productId, productName, price, unit, qty, maxQty, shopId, shopName) {
            qty    = parseInt(qty, 10)    || 1;
            maxQty = parseInt(maxQty, 10) || 0;

            var cart = _read();
            var idx  = cart.findIndex(function (i) {
                return i.productId === String(productId) &&
                       (shopId ? i.shopId === String(shopId) : true);
            });

            if (idx >= 0) {
                var newQty = cart[idx].qty + qty;
                if (maxQty > 0 && newQty > maxQty) {
                    if (CA.Toast) {
                        CA.Toast.warning(
                            'Stock limit reached',
                            'Only ' + maxQty + ' ' + (unit || 'units') + ' available at this shop.'
                        );
                    }
                    return false;
                }
                cart[idx].qty = newQty;
            } else {
                cart.push({
                    productId:   String(productId),
                    productName: productName || '',
                    price:       parseFloat(price) || 0,
                    unit:        unit || '',
                    qty:         qty,
                    maxQty:      maxQty,
                    shopId:      shopId   ? String(shopId)   : null,
                    shopName:    shopName || null
                });
            }

            _write(cart);
            _sync(cart);
            return true;
        }

        /**
         * Update quantity of a specific cart item.
         * Pass qty=0 to remove.
         *
         * @param {string} productId
         * @param {number} qty
         * @param {string} shopId  — optional, scopes to a specific shop
         */
        function updateQty(productId, qty, shopId) {
            qty = parseInt(qty, 10) || 0;
            var cart = _read();
            var idx  = cart.findIndex(function (i) {
                return i.productId === String(productId) &&
                       (shopId ? i.shopId === String(shopId) : true);
            });

            if (idx < 0) return;

            if (qty <= 0) {
                cart.splice(idx, 1);
            } else {
                var maxQty = cart[idx].maxQty || 0;
                if (maxQty > 0 && qty > maxQty) {
                    qty = maxQty;
                    if (CA.Toast) {
                        CA.Toast.warning(
                            'Stock limit',
                            'Only ' + maxQty + ' ' + (cart[idx].unit || 'units') + ' available.'
                        );
                    }
                }
                cart[idx].qty = qty;
            }

            _write(cart);
            _sync(cart);
        }

        /**
         * Remove a product from the cart entirely.
         *
         * @param {string} productId
         * @param {string} shopId — optional
         */
        function removeItem(productId, shopId) {
            var cart = _read().filter(function (i) {
                if (i.productId !== String(productId)) return true;
                if (shopId && i.shopId !== String(shopId)) return true;
                return false;
            });
            _write(cart);
            _sync(cart);
        }

        /**
         * Clear cart completely.
         */
        function clear() {
            _write([]);
            _sync([]);
        }

        /**
         * Check if product is already in cart.
         *
         * @param {string} productId
         * @param {string} shopId — optional
         * @returns {object|null}
         */
        function getItem(productId, shopId) {
            return _read().find(function (i) {
                return i.productId === String(productId) &&
                       (shopId ? i.shopId === String(shopId) : true);
            }) || null;
        }

        return {
            getAll:     getAll,
            count:      count,
            subtotal:   subtotal,
            addItem:    addItem,
            updateQty:  updateQty,
            removeItem: removeItem,
            clear:      clear,
            getItem:    getItem
        };
    }());

    /* ══════════════════════════════════════════════════════
       CART PAGE UI
       Handles quantity stepper interactions on cart/index.php.
       ══════════════════════════════════════════════════════ */
    CA.CartPage = (function () {

        function _updateRowTotal(row, qty, price) {
            var totalEl = row.querySelector('[data-ca-row-total]');
            if (!totalEl) return;
            var total = (parseFloat(price) || 0) * (parseInt(qty, 10) || 0);
            totalEl.textContent = 'ETB\u00a0' + total.toLocaleString('en-ET', {
                minimumFractionDigits: 2, maximumFractionDigits: 2
            });
        }

        function _refreshSummary() {
            var subtotalEl = document.querySelector('[data-ca-cart-subtotal]');
            if (subtotalEl) {
                var sub = CA.Cart.subtotal();
                subtotalEl.textContent = 'ETB\u00a0' + sub.toLocaleString('en-ET', {
                    minimumFractionDigits: 2, maximumFractionDigits: 2
                });
            }
            var countEl = document.querySelector('[data-ca-cart-item-count]');
            if (countEl) {
                countEl.textContent = CA.Cart.count();
            }
        }

        function init() {
            var cartContainer = document.querySelector('[data-ca-cart-page]');
            if (!cartContainer) return;

            /* Quantity stepper buttons */
            cartContainer.addEventListener('click', function (e) {
                // Decrease
                var dec = e.target.closest('[data-ca-qty-dec]');
                if (dec) {
                    var row       = dec.closest('[data-ca-cart-row]');
                    var productId = row.dataset.caProductId;
                    var shopId    = row.dataset.caShopId || null;
                    var input     = row.querySelector('[data-ca-qty-input]');
                    var price     = parseFloat(row.dataset.caPrice) || 0;
                    var newQty    = Math.max(0, (parseInt(input.value, 10) || 1) - 1);
                    input.value   = newQty;
                    CA.Cart.updateQty(productId, newQty, shopId);
                    _updateRowTotal(row, newQty, price);
                    _refreshSummary();
                    if (newQty === 0) {
                        row.style.opacity = '0';
                        row.style.transition = 'opacity .25s';
                        setTimeout(function () { row.remove(); _refreshSummary(); }, 260);
                    }
                    return;
                }

                // Increase
                var inc = e.target.closest('[data-ca-qty-inc]');
                if (inc) {
                    var row       = inc.closest('[data-ca-cart-row]');
                    var productId = row.dataset.caProductId;
                    var shopId    = row.dataset.caShopId || null;
                    var input     = row.querySelector('[data-ca-qty-input]');
                    var price     = parseFloat(row.dataset.caPrice) || 0;
                    var maxQty    = parseInt(row.dataset.caMaxQty, 10) || 0;
                    var newQty    = (parseInt(input.value, 10) || 1) + 1;
                    if (maxQty > 0 && newQty > maxQty) {
                        if (CA.Toast) CA.Toast.warning('Stock limit', 'Only ' + maxQty + ' available.');
                        return;
                    }
                    input.value = newQty;
                    CA.Cart.updateQty(productId, newQty, shopId);
                    _updateRowTotal(row, newQty, price);
                    _refreshSummary();
                    return;
                }

                // Remove item
                var removeBtn = e.target.closest('[data-ca-remove-item]');
                if (removeBtn) {
                    var row       = removeBtn.closest('[data-ca-cart-row]');
                    var productId = row.dataset.caProductId;
                    var shopId    = row.dataset.caShopId || null;
                    CA.Cart.removeItem(productId, shopId);
                    row.style.opacity = '0';
                    row.style.transition = 'opacity .25s';
                    setTimeout(function () { row.remove(); _refreshSummary(); }, 260);
                }
            });

            /* Direct input change */
            cartContainer.addEventListener('change', function (e) {
                var input = e.target.closest('[data-ca-qty-input]');
                if (!input) return;
                var row       = input.closest('[data-ca-cart-row]');
                var productId = row.dataset.caProductId;
                var shopId    = row.dataset.caShopId || null;
                var price     = parseFloat(row.dataset.caPrice) || 0;
                var maxQty    = parseInt(row.dataset.caMaxQty, 10) || 0;
                var qty       = Math.max(1, parseInt(input.value, 10) || 1);
                if (maxQty > 0 && qty > maxQty) {
                    qty = maxQty;
                    if (CA.Toast) CA.Toast.warning('Stock limit', 'Only ' + maxQty + ' available.');
                }
                input.value = qty;
                CA.Cart.updateQty(productId, qty, shopId);
                _updateRowTotal(row, qty, price);
                _refreshSummary();
            });

            _refreshSummary();
        }

        return { init: init };
    }());

    /* ══════════════════════════════════════════════════════
       SERVER-SIDE ADD-TO-CART
       Phase 4: POST /customer/cart/add with full validation.
       ══════════════════════════════════════════════════════ */
    CA.CartAPI = (function () {

        function _appUrl() {
            return (CA.appUrl || window.CartPageState && window.CartPageState.appUrl || '').replace(/\/$/, '');
        }
        function _csrf() {
            return CA.csrfToken
                || (window.CartPageState && window.CartPageState.csrfToken)
                || (document.querySelector('meta[name="csrf-token"]') || {}).content
                || '';
        }

        /**
         * Add item to cart via server endpoint.
         * @param {number} productId
         * @param {number} shopId
         * @param {number} qty
         * @param {function} callback  fn(err, response)
         */
        function addToCart(productId, shopId, qty, callback) {
            if (!productId || !shopId) {
                if (typeof callback === 'function') {
                    callback('Product ID and shop ID are required.', null);
                }
                return;
            }

            var body =
                'csrf_token='  + encodeURIComponent(_csrf()) +
                '&product_id=' + encodeURIComponent(productId) +
                '&shop_id='    + encodeURIComponent(shopId) +
                '&quantity='   + encodeURIComponent(qty || 1);

            fetch(_appUrl() + '/customer/cart/add', {
                method:  'POST',
                headers: {
                    'Content-Type':     'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (typeof callback === 'function') callback(null, res);
            })
            .catch(function (err) {
                if (typeof callback === 'function') callback(err, null);
            });
        }

        return { addToCart: addToCart };
    }());

    /* ══════════════════════════════════════════════════════
       BOOT — listens for 'ca:addToCart' from customer.js
       ══════════════════════════════════════════════════════ */
    function boot() {
        /**
         * Handle add-to-cart events dispatched by product cards.
         * Now calls the server endpoint for validation.
         */
        document.addEventListener('ca:addToCart', function (e) {
            var d            = e.detail || {};
            var selectedShop = CA.ShopSelector ? CA.ShopSelector.getSelected() : null;
            var shopId       = selectedShop ? selectedShop.id : (CA.selectedShopId || 0);
            var shopName     = selectedShop ? selectedShop.name : (CA.selectedShopName || '');

            if (!shopId) {
                if (CA.Toast) CA.Toast.warning(
                    'No shop selected',
                    'Please select a pickup shop before adding items to your cart.'
                );
                return;
            }

            /* Visual feedback on the originating button */
            var btn = d._btn || null;
            if (btn) {
                btn.disabled = true;
                var icon = btn.querySelector('i');
                if (icon) {
                    var origClass = icon.className;
                    icon.className = 'bi bi-hourglass-split';
                }
            }

            CA.CartAPI.addToCart(d.productId, shopId, d.qty || 1, function (err, res) {
                /* Restore button */
                if (btn) {
                    btn.disabled = false;
                    var ic = btn.querySelector('i');
                    if (ic) {
                        ic.className = 'bi bi-check-lg';
                        btn.style.background = 'var(--c-success)';
                        setTimeout(function () {
                            ic.className     = 'bi bi-plus-lg';
                            btn.style.background = '';
                        }, 1200);
                    }
                }

                if (err) {
                    if (CA.Toast) CA.Toast.error('Error', 'Could not add item to cart.');
                    return;
                }

                if (res.success) {
                    var data  = res.data  || {};
                    var count = data.cart_count !== undefined ? data.cart_count : null;

                    /* Update badge from server */
                    if (count !== null && CA.CartBadge) CA.CartBadge.update(count);

                    /* Mirror to localStorage for badge on other pages */
                    if (data.item) {
                        _mirrorAddToLocalStorage(data.item, count);
                    }

                    if (CA.Toast) CA.Toast.success(
                        'Added to cart',
                        res.message || (d.productName || 'Item') + ' added to your cart.'
                    );
                } else {
                    var msg = res.message || 'Could not add item.';
                    if (CA.Toast) CA.Toast.warning('Cannot add item', msg);

                    /* Restore icon to original on error */
                    if (btn) {
                        var ic = btn.querySelector('i');
                        if (ic) {
                            ic.className     = 'bi bi-plus-lg';
                            btn.style.background = '';
                        }
                    }
                }
            });
        });

        /* Init cart page UI if on cart page */
        CA.CartPage.init();
    }

    function _mirrorAddToLocalStorage(item, serverCount) {
        try {
            var raw  = localStorage.getItem('ca_cart');
            var cart = raw ? JSON.parse(raw) : [];
            var pid  = String(item.product_id || item.productId || '');
            var sid  = String(item.shop_id    || item.shopId    || '');
            var idx  = cart.findIndex(function (i) {
                return String(i.productId) === pid && String(i.shopId || '') === sid;
            });

            if (idx >= 0) {
                cart[idx].qty      = parseFloat(item.quantity)  || cart[idx].qty;
                cart[idx].price    = parseFloat(item.price)     || cart[idx].price;
                cart[idx].maxQty   = parseFloat(item.available) || cart[idx].maxQty;
            } else {
                cart.push({
                    productId:   pid,
                    productName: item.product_name || item.productName || '',
                    price:       parseFloat(item.price) || 0,
                    unit:        item.unit || '',
                    qty:         parseFloat(item.quantity) || 1,
                    maxQty:      parseFloat(item.available) || 0,
                    shopId:      sid || null,
                    shopName:    item.shop_name || item.shopName || null
                });
            }

            localStorage.setItem('ca_cart', JSON.stringify(cart));

            /* Badge from server is authoritative */
            if (serverCount !== null && serverCount !== undefined && CA.CartBadge) {
                CA.CartBadge.update(serverCount);
            } else {
                var total = cart.reduce(function (s, i) {
                    return s + (parseInt(i.qty, 10) || 0);
                }, 0);
                if (CA.CartBadge) CA.CartBadge.update(total);
            }
        } catch (_) {}
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

}());
