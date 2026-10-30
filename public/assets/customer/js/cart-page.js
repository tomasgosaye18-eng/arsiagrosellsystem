/**
 * ============================================================
 * AU-AgroSale — Customer Cart Page JS
 * cart-page.js
 * ============================================================
 * Phase 4 — server-side validated cart interactions.
 *
 * Responsibilities:
 *   - Qty stepper: dec / inc / direct input → POST /customer/cart/update
 *   - Remove item  → POST /customer/cart/remove  (smooth animation)
 *   - Clear cart   → POST /customer/cart/clear
 *   - Change-shop modal: shop picker → POST /customer/cart/check-shop
 *                                    → POST /customer/cart/change-shop
 *   - Summary totals updated in real-time after every server response
 *   - Cart badge (header) kept in sync
 *   - LocalStorage mirror updated after every mutation
 *
 * Security:
 *   All mutations go through the server.
 *   Product IDs, shop IDs, and prices are NEVER sent from the
 *   browser. The server reads them from the session cart and DB.
 *   Only quantity is sent for update requests.
 * ============================================================
 */

(function () {
    'use strict';

    /* ── Namespace ──────────────────────────────────────── */
    var CA  = window.CustomerApp   || {};
    var CPS = window.CartPageState || {};

    var APP_URL   = CPS.appUrl    || (CA.appUrl    || '');
    var CSRF      = CPS.csrfToken || (CA.csrfToken || '');
    var SHOP_ID   = CPS.shopId    || 0;

    /* ── Debounce timer per product row (keyed by pid) ── */
    var _debounceTimers = {};

    /* ══════════════════════════════════════════════════════
       AJAX HELPER
       ══════════════════════════════════════════════════════ */
    function ajax(path, data, callback) {
        var body = 'csrf_token=' + encodeURIComponent(CSRF);
        if (data && typeof data === 'object') {
            Object.keys(data).forEach(function (k) {
                body += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(data[k]);
            });
        }

        fetch(APP_URL + path, {
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

    /* ══════════════════════════════════════════════════════
       FORMATTING
       ══════════════════════════════════════════════════════ */
    function fmtPrice(n) {
        n = parseFloat(n) || 0;
        return 'ETB ' + n.toLocaleString('en-ET', {
            minimumFractionDigits:  2,
            maximumFractionDigits:  2
        });
    }

    /* ══════════════════════════════════════════════════════
       SUMMARY + BADGE UPDATE
       ══════════════════════════════════════════════════════ */
    function refreshSummaryFromDOM() {
        var subtotal       = 0;
        var distinctCount  = 0;
        var totalItemCount = 0;

        document.querySelectorAll('.cart-item-row').forEach(function (row) {
            /* Skip rows that are unavailable / out_of_stock */
            var st = row.dataset.status || 'ok';
            if (st === 'unavailable' || st === 'out_of_stock') return;
            /* Also skip by CSS class (set after server response) */
            if (row.classList.contains('cart-item-row--unavailable')) return;

            var price  = parseFloat(row.dataset.price)     || 0;
            var input  = row.querySelector('[data-qty-input]');
            var qty    = input ? (parseInt(input.value, 10) || 0) : 0;
            var rowSub = price * qty;

            /* update row subtotal element */
            var subEl = row.querySelector('[data-row-subtotal]');
            if (subEl && qty > 0) subEl.textContent = fmtPrice(rowSub);

            if (qty > 0) {
                subtotal      += rowSub;
                distinctCount++;
                totalItemCount += qty;
            }
        });

        /* Summary card */
        var subEl = document.getElementById('cartSummarySubtotal');
        if (subEl) subEl.textContent = fmtPrice(subtotal);

        var totEl = document.getElementById('cartSummaryTotalAmt');
        if (totEl) totEl.textContent = fmtPrice(subtotal);

        var cntEl = document.getElementById('cartSummaryCount');
        if (cntEl) cntEl.textContent = '(' + distinctCount + ')';

        var lblEl = document.getElementById('cartItemCountLabel');
        if (lblEl) lblEl.textContent =
            '(' + distinctCount + ' item' + (distinctCount !== 1 ? 's' : '') + ')';

        /* Badge */
        if (CA.CartBadge) CA.CartBadge.update(totalItemCount);

        /* Also mirror to localStorage so badge works on other pages */
        _mirrorToLocalStorage();

        return subtotal;
    }

    function _mirrorToLocalStorage() {
        try {
            var items = [];
            document.querySelectorAll('.cart-item-row').forEach(function (row) {
                if (row.dataset.status === 'unavailable' ||
                    row.dataset.status === 'out_of_stock') return;
                var pid   = row.dataset.pid;
                var sid   = row.dataset.sid;
                var price = parseFloat(row.dataset.price) || 0;
                var unit  = row.dataset.unit || '';
                var input = row.querySelector('[data-qty-input]');
                var qty   = input ? (parseInt(input.value, 10) || 0) : 0;
                var name  = (row.querySelector('.cart-item-row__name a') ||
                             row.querySelector('.cart-item-row__name'));
                var pname = name ? (name.textContent || '').trim() : '';
                if (qty > 0) {
                    items.push({
                        productId:   pid,
                        productName: pname,
                        price:       price,
                        unit:        unit,
                        qty:         qty,
                        maxQty:      parseInt(row.dataset.available, 10) || 0,
                        shopId:      sid || null,
                        shopName:    CPS.shopName || ''
                    });
                }
            });
            localStorage.setItem('ca_cart', JSON.stringify(items));
        } catch (_) {}
    }

    /* ══════════════════════════════════════════════════════
       ROW HELPERS
       ══════════════════════════════════════════════════════ */
    function getRowData(row) {
        return {
            pid:       row.dataset.pid       || '',
            sid:       row.dataset.sid       || SHOP_ID || '',
            price:     parseFloat(row.dataset.price) || 0,
            available: parseFloat(row.dataset.available) || 0,
            unit:      row.dataset.unit || ''
        };
    }

    function setRowLoading(row, on) {
        row.classList.toggle('loading', on);
    }

    function removeRowAnimated(row, callback) {
        row.classList.add('cart-item-row--removing');
        setTimeout(function () {
            row.remove();
            if (typeof callback === 'function') callback();
        }, 280);
    }

    function showRowWarning(row, msg) {
        var existing = row.querySelector('.cart-item-warning');
        if (existing) {
            existing.innerHTML =
                '<i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> ' +
                _esc(msg);
            return;
        }
        var info = row.querySelector('.cart-item-row__info');
        if (!info) return;
        var div = document.createElement('div');
        div.className = 'cart-item-warning';
        div.setAttribute('role', 'alert');
        div.innerHTML =
            '<i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> ' +
            _esc(msg);
        info.appendChild(div);
        row.classList.add('cart-item-row--warning');
    }

    function clearRowWarning(row) {
        var w = row.querySelector('.cart-item-warning');
        if (w) w.remove();
        row.classList.remove('cart-item-row--warning');
        row.classList.remove('cart-item-row--unavailable');
    }

    function _esc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function toggleIssuesBanner(hasIssues) {
        var banner = document.getElementById('cartIssuesBanner');
        if (banner) banner.style.display = hasIssues ? '' : 'none';

        var summaryAlert = document.getElementById('cartSummaryIssuesAlert');
        if (summaryAlert) summaryAlert.style.display = hasIssues ? '' : 'none';

        var checkoutBtn = document.getElementById('cartCheckoutBtn');
        if (checkoutBtn) {
            checkoutBtn.disabled = hasIssues || !SHOP_ID;
            checkoutBtn.setAttribute('aria-disabled', (hasIssues || !SHOP_ID) ? 'true' : 'false');
        }
    }

    function checkAnyIssues() {
        var has = document.querySelector(
            '.cart-item-row--warning, .cart-item-row--unavailable'
        ) !== null;
        toggleIssuesBanner(has);
    }

    /* ══════════════════════════════════════════════════════
       UPDATE QUANTITY  (debounced — 450ms)
       ══════════════════════════════════════════════════════ */
    function updateQty(row, newQty) {
        var d = getRowData(row);
        if (!d.pid) return;

        clearTimeout(_debounceTimers[d.pid]);
        _debounceTimers[d.pid] = setTimeout(function () {
            _sendUpdate(row, d, newQty);
        }, 450);
    }

    function _sendUpdate(row, d, qty) {
        setRowLoading(row, true);

        ajax('/customer/cart/update', {
            product_id: d.pid,
            shop_id:    d.sid,
            quantity:   qty
        }, function (err, res) {
            setRowLoading(row, false);

            if (err) {
                if (CA.Toast) CA.Toast.error('Network error', 'Could not update cart. Please try again.');
                return;
            }

            if (res.success || res.data) {
                var data = res.data || {};

                /* If item was adjusted to available max */
                if (data.adjusted) {
                    var newQ = data.quantity || 0;
                    var input = row.querySelector('[data-qty-input]');
                    if (input) input.value = newQ;
                    row.dataset.available = newQ;
                    showRowWarning(row, res.message || data.message || 'Quantity adjusted.');
                    if (CA.Toast) CA.Toast.warning('Stock limit', res.message || 'Quantity adjusted to available stock.');
                }

                /* If item was removed (no longer available) */
                if (data.removed) {
                    removeRowAnimated(row, function () {
                        refreshSummaryFromDOM();
                        checkAnyIssues();
                        maybeShowEmptyState();
                    });
                    if (CA.Toast) CA.Toast.warning('Item removed', res.message || 'Product no longer available.');
                    return;
                }

                /* Update available from server response */
                if (data.available !== undefined) {
                    row.dataset.available = data.available;
                    var incBtn = row.querySelector('[data-qty-inc]');
                    if (incBtn) incBtn.disabled = qty >= parseFloat(data.available);
                }

                clearRowWarning(row);
                refreshSummaryFromDOM();
                checkAnyIssues();

            } else {
                /* Server says qty is invalid */
                var errMsg = res.message || 'Could not update quantity.';
                var avail  = (res.errors && res.errors.available !== undefined)
                    ? res.errors.available : null;

                if (avail !== null && parseFloat(avail) <= 0) {
                    /* Out of stock — mark row */
                    row.dataset.status = 'out_of_stock';
                    row.classList.add('cart-item-row--unavailable');
                    showRowWarning(row, errMsg);
                } else if (avail !== null) {
                    /* Cap at available */
                    var input = row.querySelector('[data-qty-input]');
                    if (input) input.value = avail;
                    showRowWarning(row, errMsg);
                } else {
                    showRowWarning(row, errMsg);
                }

                if (CA.Toast) CA.Toast.warning('Stock changed', errMsg);
                refreshSummaryFromDOM();
                checkAnyIssues();
            }
        });
    }

    /* ══════════════════════════════════════════════════════
       REMOVE ITEM
       ══════════════════════════════════════════════════════ */
    function removeItem(row) {
        var d = getRowData(row);
        if (!d.pid) return;

        setRowLoading(row, true);

        ajax('/customer/cart/remove', {
            product_id: d.pid,
            shop_id:    d.sid
        }, function (err, res) {
            if (err) {
                setRowLoading(row, false);
                if (CA.Toast) CA.Toast.error('Error', 'Could not remove item.');
                return;
            }

            var data = res.data || {};
            var name = (row.querySelector('.cart-item-row__name a') ||
                        row.querySelector('.cart-item-row__name'));
            var pname = name ? (name.textContent || '').trim() : 'Item';

            removeRowAnimated(row, function () {
                refreshSummaryFromDOM();
                checkAnyIssues();
                maybeShowEmptyState();
            });

            if (CA.Toast) CA.Toast.success('Removed', pname + ' removed from cart.');

            /* sync badge from server count */
            if (CA.CartBadge && data.cart_count !== undefined) {
                CA.CartBadge.update(data.cart_count);
            }
        });
    }

    /* ══════════════════════════════════════════════════════
       CLEAR CART
       ══════════════════════════════════════════════════════ */
    function clearCart() {
        ajax('/customer/cart/clear', {}, function (err, res) {
            if (err) {
                if (CA.Toast) CA.Toast.error('Error', 'Could not clear cart.');
                return;
            }

            /* Remove all rows */
            document.querySelectorAll('.cart-item-row').forEach(function (row) {
                row.remove();
            });

            if (CA.CartBadge) CA.CartBadge.update(0);
            try { localStorage.setItem('ca_cart', '[]'); } catch (_) {}

            /* Show empty state */
            showEmptyState();
            if (CA.Toast) CA.Toast.info('Cart cleared', 'Your cart has been cleared.');
        });
    }

    /* ══════════════════════════════════════════════════════
       EMPTY STATE
       ══════════════════════════════════════════════════════ */
    function maybeShowEmptyState() {
        var remaining = document.querySelectorAll('.cart-item-row').length;
        if (remaining === 0) showEmptyState();
    }

    function showEmptyState() {
        /* Reload the page so the server renders the empty state cleanly */
        window.location.reload();
    }

    /* ══════════════════════════════════════════════════════
       CHANGE SHOP MODAL
       ══════════════════════════════════════════════════════ */
    var _pendingShopId   = null;
    var _pendingShopName = null;

    var modal        = document.getElementById('cartChangeShopModal');
    var step1        = document.getElementById('changeShopStep1');
    var step2        = document.getElementById('changeShopStep2');
    var loading      = document.getElementById('changeShopLoading');
    var confirmBtn   = document.getElementById('changeShopConfirmBtn');
    var footer       = document.getElementById('changeShopFooter');

    function openChangeShopModal() {
        if (!modal) return;
        /* Reset to step 1 */
        if (step1)    { step1.style.display    = ''; }
        if (step2)    { step2.style.display    = 'none'; }
        if (loading)  { loading.style.display  = 'none'; }
        if (confirmBtn) { confirmBtn.style.display = 'none'; }
        _pendingShopId   = null;
        _pendingShopName = null;

        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        /* Focus first shop option */
        setTimeout(function () {
            var first = modal.querySelector('.cart-modal-shop-option:not(.selected)');
            if (first) first.focus();
        }, 120);
    }

    function closeChangeShopModal() {
        if (!modal) return;
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function checkShopConflict(newShopId, newShopName) {
        if (!step1 || !step2 || !loading) return;

        /* Disable all shop options while checking */
        modal.querySelectorAll('.cart-modal-shop-option').forEach(function (btn) {
            btn.classList.add('checking');
        });

        step1.style.display   = 'none';
        loading.style.display = '';

        ajax('/customer/cart/check-shop', { shop_id: newShopId }, function (err, res) {
            loading.style.display = 'none';

            /* Re-enable options */
            modal.querySelectorAll('.cart-modal-shop-option').forEach(function (btn) {
                btn.classList.remove('checking');
            });

            if (err || !res) {
                step1.style.display = '';
                if (CA.Toast) CA.Toast.error('Error', 'Could not check shop availability.');
                return;
            }

            var data = res.data || {};

            if (!data.has_conflict) {
                /* No conflict — apply immediately */
                applyShopChange(newShopId, newShopName);
                return;
            }

            /* Show conflict warning */
            _pendingShopId   = newShopId;
            _pendingShopName = newShopName;

            var conflictCount = data.unavailable_count || 0;
            var countEl = document.getElementById('changeShopConflictCount');
            var nameEl  = document.getElementById('changeShopConflictName');
            if (countEl) countEl.textContent =
                conflictCount + ' item' + (conflictCount !== 1 ? 's' : '');
            if (nameEl)  nameEl.textContent  = newShopName;

            /* Build unavailable items list */
            var listEl = document.getElementById('changeShopUnavailableList');
            if (listEl && data.unavailable) {
                var html = '';
                data.unavailable.forEach(function (item) {
                    html +=
                        '<div class="cart-modal-unavailable-item">' +
                        '<i class="bi bi-x-circle-fill" aria-hidden="true"></i>' +
                        '<span>' + _esc(item.product_name || '') +
                        (item.quantity ? ' — ' + item.quantity +
                            (item.unit ? ' ' + _esc(item.unit) : '') : '') +
                        '</span>' +
                        '</div>';
                });
                listEl.innerHTML = html;
            }

            step2.style.display = '';
            if (confirmBtn) confirmBtn.style.display = '';

            /* Focus confirm button */
            if (confirmBtn) confirmBtn.focus();
        });
    }

    function applyShopChange(shopId, shopName) {
        if (loading) loading.style.display = '';
        if (step2)   step2.style.display   = 'none';
        if (confirmBtn) confirmBtn.disabled = true;

        ajax('/customer/cart/change-shop', {
            shop_id: shopId
        }, function (err, res) {
            if (loading) loading.style.display = 'none';
            if (confirmBtn) confirmBtn.disabled = false;

            closeChangeShopModal();

            if (err || !res) {
                if (CA.Toast) CA.Toast.error('Error', 'Could not change shop. Please try again.');
                return;
            }

            /* Reload page — server has new cart + new shop */
            if (CA.Toast) CA.Toast.success('Shop changed', 'Your pickup shop has been updated.');
            setTimeout(function () { window.location.reload(); }, 600);
        });
    }

    /* ══════════════════════════════════════════════════════
       REMOVE UNAVAILABLE ITEMS (bulk)
       Removes all rows marked unavailable / out_of_stock
       one by one, then refreshes summary.
       ══════════════════════════════════════════════════════ */
    function removeUnavailableItems() {
        var rows = [];
        document.querySelectorAll('.cart-item-row').forEach(function (row) {
            var st = row.dataset.status || 'ok';
            if (st === 'unavailable' || st === 'out_of_stock' ||
                row.classList.contains('cart-item-row--unavailable')) {
                rows.push(row);
            }
        });

        if (rows.length === 0) {
            if (CA.Toast) CA.Toast.info('Nothing to remove', 'No unavailable items found.');
            return;
        }

        /* Fire one remove request per unavailable item sequentially */
        var remaining = rows.length;
        var removed   = 0;

        rows.forEach(function (row) {
            var d = getRowData(row);
            if (!d.pid) { remaining--; return; }

            setRowLoading(row, true);
            ajax('/customer/cart/remove', {
                product_id: d.pid,
                shop_id:    d.sid
            }, function (err) {
                removed++;
                removeRowAnimated(row, function () {
                    if (removed >= remaining) {
                        refreshSummaryFromDOM();
                        checkAnyIssues();
                        maybeShowEmptyState();
                        if (CA.Toast) CA.Toast.success(
                            'Removed',
                            removed + ' unavailable item' + (removed !== 1 ? 's' : '') + ' removed.'
                        );
                    }
                });
            });
        });
    }

    /* ══════════════════════════════════════════════════════
       EVENT WIRING
       ══════════════════════════════════════════════════════ */
    function init() {

        /* ── Quantity stepper ─────────────────────────── */
        document.addEventListener('click', function (e) {

            /* Decrease */
            var dec = e.target.closest('[data-qty-dec]');
            if (dec) {
                var row   = dec.closest('.cart-item-row');
                if (!row) return;
                var input = row.querySelector('[data-qty-input]');
                if (!input) return;
                var cur   = parseInt(input.value, 10) || 1;
                if (cur <= 1) {
                    /* Removing item when qty reaches 0 */
                    if (confirm('Remove this item from your cart?')) {
                        removeItem(row);
                    }
                    return;
                }
                var nv = cur - 1;
                input.value = nv;
                dec.disabled = nv <= 1;
                var inc = row.querySelector('[data-qty-inc]');
                if (inc) inc.disabled = false;
                updateQty(row, nv);
                refreshSummaryFromDOM();
                return;
            }

            /* Increase */
            var inc = e.target.closest('[data-qty-inc]');
            if (inc) {
                var row   = inc.closest('.cart-item-row');
                if (!row) return;
                var input = row.querySelector('[data-qty-input]');
                if (!input) return;
                var avail = parseFloat(row.dataset.available) || Infinity;
                var cur   = parseInt(input.value, 10) || 0;
                if (cur >= avail) {
                    if (CA.Toast) CA.Toast.warning(
                        'Stock limit',
                        'Only ' + avail + (row.dataset.unit ? ' ' + row.dataset.unit : '') + ' available.'
                    );
                    return;
                }
                var nv = cur + 1;
                input.value = nv;
                var dec2 = row.querySelector('[data-qty-dec]');
                if (dec2) dec2.disabled = false;
                inc.disabled = nv >= avail;
                updateQty(row, nv);
                refreshSummaryFromDOM();
                return;
            }

            /* Remove */
            var rmBtn = e.target.closest('[data-remove-item]');
            if (rmBtn) {
                var row = rmBtn.closest('.cart-item-row');
                if (row) removeItem(row);
                return;
            }

            /* Clear cart */
            if (e.target.closest('#cartClearBtn')) {
                if (confirm('Are you sure you want to clear your entire cart?')) {
                    clearCart();
                }
                return;
            }

            /* Remove unavailable items */
            if (e.target.closest('#removeUnavailableBtn')) {
                removeUnavailableItems();
                return;
            }

            /* ── Proceed to Checkout ──────────────────── */
            if (e.target.closest('#cartCheckoutBtn')) {
                var btn = document.getElementById('cartCheckoutBtn');
                if (!btn || btn.disabled) return;
                /* Navigate to checkout — shop ID validated server-side */
                window.location.href = APP_URL + '/customer/checkout';
                return;
            }

            /* Mobile sticky checkout (if present) */
            if (e.target.closest('#cartStickyCheckoutBtn')) {
                var btn2 = document.getElementById('cartStickyCheckoutBtn');
                if (!btn2 || btn2.disabled) return;
                window.location.href = APP_URL + '/customer/checkout';
                return;
            }

            /* Modal triggers */
            if (
                e.target.closest('#cartChangeShopTrigger')     ||
                e.target.closest('#cartChangeShopTriggerEmpty') ||
                e.target.closest('#cartChangeShopSummary')
            ) {
                openChangeShopModal();
                return;
            }

            /* Modal close */
            if (e.target.closest('[data-modal-close]') ||
                e.target === modal) {
                closeChangeShopModal();
                return;
            }

            /* Shop option in modal */
            var shopOpt = e.target.closest('.cart-modal-shop-option');
            if (shopOpt && shopOpt.closest('#changeShopStep1')) {
                var newId   = shopOpt.dataset.shopId;
                var newName = shopOpt.dataset.shopName || '';

                /* Deselect all, select clicked */
                modal.querySelectorAll('.cart-modal-shop-option').forEach(function (b) {
                    b.classList.remove('selected');
                    b.setAttribute('aria-selected', 'false');
                    var ic = b.querySelector('i');
                    if (ic) ic.className = 'bi bi-shop';
                });
                shopOpt.classList.add('selected');
                shopOpt.setAttribute('aria-selected', 'true');
                var ic = shopOpt.querySelector('i');
                if (ic) ic.className = 'bi bi-check-circle-fill';

                /* If same shop — just close */
                if (parseInt(newId, 10) === SHOP_ID) {
                    if (CA.Toast) CA.Toast.info('Same shop', 'You are already picking up from this shop.');
                    closeChangeShopModal();
                    return;
                }

                checkShopConflict(newId, newName);
                return;
            }

            /* Confirm shop change */
            if (e.target.closest('#changeShopConfirmBtn')) {
                if (_pendingShopId) {
                    applyShopChange(_pendingShopId, _pendingShopName);
                }
                return;
            }
        });

        /* ── Direct qty input (blur / enter) ─────────── */
        document.addEventListener('change', function (e) {
            var input = e.target.closest('[data-qty-input]');
            if (!input) return;
            var row   = input.closest('.cart-item-row');
            if (!row) return;
            var avail = parseFloat(row.dataset.available) || 0;
            var raw   = parseInt(input.value, 10) || 1;
            var qty   = Math.max(1, raw);

            if (avail > 0 && qty > avail) {
                qty = Math.floor(avail);
                input.value = qty;
                showRowWarning(row, 'Only ' + avail + (row.dataset.unit ? ' ' + row.dataset.unit : '') + ' available.');
                if (CA.Toast) CA.Toast.warning('Stock limit', 'Quantity capped at available stock.');
            }

            if (qty < 1) {
                input.value = 1;
                qty = 1;
            }

            var dec = row.querySelector('[data-qty-dec]');
            var inc = row.querySelector('[data-qty-inc]');
            if (dec) dec.disabled = qty <= 1;
            if (inc) inc.disabled = avail > 0 && qty >= avail;

            updateQty(row, qty);
            refreshSummaryFromDOM();
        });

        /* ── Keyboard: Enter on qty input ────────────── */
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                var input = e.target.closest('[data-qty-input]');
                if (input) {
                    e.preventDefault();
                    input.blur();
                }
            }
            /* ESC closes modal */
            if (e.key === 'Escape' && modal && modal.classList.contains('open')) {
                closeChangeShopModal();
            }
        });

        /* ── Modal backdrop click ─────────────────────── */
        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeChangeShopModal();
            });
        }

        /* ── Initial badge sync from server ──────────── */
        refreshSummaryFromDOM();
        checkAnyIssues();
    }

    /* ── Boot ─────────────────────────────────────────── */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}());
