/**
 * ============================================================
 * AU-AgroSale — Customer Search
 * search.js
 * ============================================================
 * Live search suggestions for the customer storefront.
 * Reads from /api/customer/search or falls back gracefully.
 * Keyboard navigation: ArrowUp / ArrowDown / Enter / Escape.
 * ============================================================
 */

(function () {
    'use strict';

    window.CustomerApp = window.CustomerApp || {};
    var CA = window.CustomerApp;

    CA.Search = (function () {

        var input, suggestionsBox;
        var currentIndex = -1;
        var lastQuery    = '';
        var cache        = {};
        var XHR          = null;

        var DEBOUNCE_MS  = 260;
        var MIN_CHARS    = 2;

        /* ── Wire up ──────────────────────────────────────── */
        function init() {
            input          = document.getElementById('customerSearchInput');
            suggestionsBox = document.getElementById('customerSearchSuggestions');

            if (!input) return;

            input.addEventListener('input',   CA.utils.debounce(_onInput, DEBOUNCE_MS));
            input.addEventListener('keydown',  _onKeydown);
            input.addEventListener('focus',    _onFocus);

            document.addEventListener('click', function (e) {
                if (!e.target.closest('.customer-navbar__search-wrap')) _close();
            });

            // Clear button
            var clearBtn = document.querySelector('.customer-navbar__search-clear');
            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    input.value = '';
                    input.focus();
                    _close();
                });
            }

            // Mobile search mirror
            var mobileInput = document.getElementById('customerMobileSearchInput');
            if (mobileInput) {
                mobileInput.addEventListener('input', CA.utils.debounce(function () {
                    input.value = mobileInput.value;
                    _onInput.call(mobileInput);
                }, DEBOUNCE_MS));
            }
        }

        /* ── Input handler ────────────────────────────────── */
        function _onInput() {
            var q = input.value.trim();
            if (q === lastQuery) return;
            lastQuery    = q;
            currentIndex = -1;

            if (q.length < MIN_CHARS) {
                _close();
                return;
            }

            _fetchSuggestions(q);
        }

        /* ── Focus: reopen if query present ──────────────── */
        function _onFocus() {
            if (input.value.trim().length >= MIN_CHARS && suggestionsBox) {
                suggestionsBox.classList.add('open');
            }
        }

        /* ── Keyboard nav ─────────────────────────────────── */
        function _onKeydown(e) {
            if (!suggestionsBox || !suggestionsBox.classList.contains('open')) {
                if (e.key === 'Enter') _submitSearch();
                return;
            }

            var items = suggestionsBox.querySelectorAll('.customer-search-suggestion-item');

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                currentIndex = Math.min(currentIndex + 1, items.length - 1);
                _highlightItem(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                currentIndex = Math.max(currentIndex - 1, -1);
                _highlightItem(items);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (currentIndex >= 0 && items[currentIndex]) {
                    items[currentIndex].click();
                } else {
                    _submitSearch();
                }
            } else if (e.key === 'Escape') {
                _close();
                input.blur();
            }
        }

        function _highlightItem(items) {
            items.forEach(function (el, i) {
                el.classList.toggle('selected', i === currentIndex);
                if (i === currentIndex) el.scrollIntoView({ block: 'nearest' });
            });
        }

        /* ── Fetch suggestions ────────────────────────────── */
        function _fetchSuggestions(q) {
            if (cache[q]) {
                _render(q, cache[q]);
                return;
            }

            // Show loading indicator
            _showLoading();

            // Cancel in-flight request
            if (XHR) { XHR.abort(); }

            // Include selected shop so results are scoped to that shop's stock
            var shopId = (CA.ShopSelector && CA.ShopSelector.getId())
                ? CA.ShopSelector.getId()
                : (CA.selectedShopId || 0);
            var url = CA.appUrl + '/api/customer/search?q=' + encodeURIComponent(q)
                    + (shopId ? '&shop_id=' + encodeURIComponent(shopId) : '');

            XHR = new XMLHttpRequest();
            XHR.open('GET', url, true);
            XHR.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            XHR.onreadystatechange = function () {
                if (XHR.readyState !== 4) return;
                if (XHR.status === 200) {
                    try {
                        var data  = JSON.parse(XHR.responseText);
                        var items = (data && data.data) ? data.data : [];
                        cache[q]  = items;
                        _render(q, items);
                    } catch (_) {
                        _renderFallback(q);
                    }
                } else {
                    _renderFallback(q);
                }
            };
            XHR.send();
        }

        /* ── Render suggestions ───────────────────────────── */
        function _render(q, items) {
            if (!suggestionsBox) return;

            if (!items || items.length === 0) {
                _renderEmpty(q);
                return;
            }

            var html = '<div class="customer-search-suggestions__section-title">Products</div>';

            items.slice(0, 8).forEach(function (item) {
                var name     = CA.utils.esc(item.product_name || item.name || '');
                var category = CA.utils.esc(item.category_name || '');
                var price    = item.selling_price
                    ? CA.utils.formatPrice(item.selling_price)
                    : '';
                var highlighted = name.replace(
                    new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi'),
                    '<mark>$1</mark>'
                );
                html +=
                    '<button type="button" class="customer-search-suggestion-item"' +
                    ' data-ca-product-id="' + CA.utils.esc(item.id || item.product_id || '') + '"' +
                    ' data-ca-product-name="' + CA.utils.esc(item.product_name || item.name || '') + '"' +
                    ' role="option">' +
                    '<i class="bi bi-box-seam customer-search-suggestion-item__icon"></i>' +
                    '<div class="customer-search-suggestion-item__text">' +
                    highlighted +
                    (category ? '<span style="color:var(--c-text-muted);font-size:var(--c-size-xs);margin-left:6px;">' + category + '</span>' : '') +
                    '</div>' +
                    (price ? '<span style="color:var(--c-price);font-size:var(--c-size-xs);font-weight:600;flex-shrink:0;">' + price + '</span>' : '') +
                    '</button>';
            });

            html += '<div class="customer-search-suggestions__footer">View all results for "' + CA.utils.esc(q) + '"</div>';

            suggestionsBox.innerHTML = html;
            suggestionsBox.classList.add('open');

            // Wire suggestion clicks
            suggestionsBox.querySelectorAll('.customer-search-suggestion-item').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var productId = btn.dataset.caProductId;
                    var productName = btn.dataset.caProductName;
                    if (productId) {
                        window.location.href = CA.appUrl + '/customer/products/' + productId;
                    } else {
                        input.value = productName;
                        _submitSearch();
                    }
                    _close();
                });
            });

            // View all footer
            var footer = suggestionsBox.querySelector('.customer-search-suggestions__footer');
            if (footer) {
                footer.addEventListener('click', _submitSearch);
            }
        }

        function _renderEmpty(q) {
            if (!suggestionsBox) return;
            suggestionsBox.innerHTML =
                '<div style="padding:var(--c-space-5) var(--c-space-4);text-align:center;color:var(--c-text-muted);font-size:var(--c-size-sm);">' +
                '<i class="bi bi-search" style="font-size:24px;opacity:.4;display:block;margin-bottom:var(--c-space-2);"></i>' +
                'No results for "<strong>' + CA.utils.esc(q) + '</strong>"' +
                '</div>';
            suggestionsBox.classList.add('open');
        }

        function _renderFallback(q) {
            // If the API endpoint doesn't exist yet, silently close
            _close();
        }

        function _showLoading() {
            if (!suggestionsBox) return;
            suggestionsBox.innerHTML =
                '<div style="padding:var(--c-space-4);text-align:center;">' +
                '<span class="c-spinner c-spinner-sm"></span>' +
                '</div>';
            suggestionsBox.classList.add('open');
        }

        function _close() {
            if (suggestionsBox) suggestionsBox.classList.remove('open');
            currentIndex = -1;
        }

        function _submitSearch() {
            var q = input ? input.value.trim() : '';
            if (!q) return;
            var shopId = (CA.ShopSelector && CA.ShopSelector.getId())
                ? CA.ShopSelector.getId()
                : (CA.selectedShopId || 0);
            var url = CA.appUrl + '/customer/products/search?q=' + encodeURIComponent(q)
                    + (shopId ? '&shop_id=' + encodeURIComponent(shopId) : '');
            window.location.href = url;
        }

        return { init: init };
    }());

    /* ── Boot ─────────────────────────────────────────────── */
    function boot() {
        CA.Search.init();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

}());
