/**
 * ============================================================
 * AU-AgroSale — Customer Favorites
 * favorites.js
 * ============================================================
 * Handles:
 *   - Heart button clicks on product cards (product listing,
 *     home page, product detail, favorites page)
 *   - AJAX POST to /customer/favorites/toggle
 *   - Immediate optimistic UI update (heart ♡ ↔ ❤️)
 *   - Toast feedback via CA.Toast
 *   - Redirect to login if unauthenticated (401 response)
 *   - Page reload on favorites page after remove (keeps list fresh)
 *   - Favorites count badge in account sidebar
 *
 * Pattern follows the existing cart.js conventions:
 *   - Uses fetch() with URL-encoded body
 *   - Reads csrf from meta[name="csrf-token"]
 *   - Uses window.CustomerApp namespace (CA)
 *   - Uses CA.Toast for notifications
 * ============================================================
 */

(function () {
    'use strict';

    window.CustomerApp = window.CustomerApp || {};
    var CA = window.CustomerApp;

    /* ══════════════════════════════════════════════════════
       FAVORITES API
       ══════════════════════════════════════════════════════ */
    CA.Favorites = (function () {

        function _appUrl() {
            return (CA.appUrl || '').replace(/\/$/, '');
        }

        function _csrf() {
            return CA.csrfToken
                || (document.querySelector('meta[name="csrf-token"]') || {}).content
                || '';
        }

        /**
         * Toggle a product's favorite state via server.
         *
         * @param {number}   productId
         * @param {function} callback  fn(err, response)
         */
        function toggle(productId, callback) {
            if (!productId) {
                if (typeof callback === 'function') callback('Invalid product.', null);
                return;
            }

            var body = 'csrf_token='  + encodeURIComponent(_csrf())
                     + '&product_id=' + encodeURIComponent(productId);

            fetch(_appUrl() + '/customer/favorites/toggle', {
                method:  'POST',
                headers: {
                    'Content-Type':     'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body
            })
            .then(function (r) {
                // 401 → not authenticated
                if (r.status === 401) {
                    return { _authRequired: true };
                }
                return r.json();
            })
            .then(function (res) {
                if (typeof callback === 'function') callback(null, res);
            })
            .catch(function (err) {
                if (typeof callback === 'function') callback(err, null);
            });
        }

        return { toggle: toggle };
    }());

    /* ══════════════════════════════════════════════════════
       HEART BUTTON HANDLER
       ══════════════════════════════════════════════════════ */

    /**
     * Determine if we are currently on the Favorites account page.
     * Used to decide whether to reload after removal.
     */
    function _isOnFavoritesPage() {
        return window.location.pathname.indexOf('/customer/account/favorites') !== -1;
    }

    /**
     * Update every heart button on the page that matches a product ID.
     * Keeps product listing + detail in sync if both are visible.
     */
    function _updateAllButtons(productId, isFavorited) {
        document.querySelectorAll('[data-ca-favorite-toggle][data-product-id="' + productId + '"]')
            .forEach(function (btn) {
                var icon = btn.querySelector('i');
                var label = btn.querySelector('.cpd-fav-btn__label');

                if (isFavorited) {
                    btn.classList.add('cpl-card__wishlist--active');
                    btn.classList.add('ch-product-card__wishlist--active');
                    btn.classList.add('cpd-fav-btn--active');
                    btn.classList.add('caf-card__heart--active');
                    btn.setAttribute('aria-pressed', 'true');
                    var pname = btn.dataset.productName || 'product';
                    btn.setAttribute('aria-label', 'Remove ' + pname + ' from favorites');
                    if (icon) icon.className = 'bi bi-heart-fill';
                    if (label) label.textContent = 'Saved';
                } else {
                    btn.classList.remove('cpl-card__wishlist--active');
                    btn.classList.remove('ch-product-card__wishlist--active');
                    btn.classList.remove('cpd-fav-btn--active');
                    btn.classList.remove('caf-card__heart--active');
                    btn.setAttribute('aria-pressed', 'false');
                    var pname2 = btn.dataset.productName || 'product';
                    btn.setAttribute('aria-label', 'Save ' + pname2 + ' to favorites');
                    if (icon) icon.className = 'bi bi-heart';
                    if (label) label.textContent = 'Save';
                }
            });
    }

    /**
     * Update the favorites count badge in the account sidebar nav item.
     */
    function _updateCountBadge(count) {
        document.querySelectorAll('[data-ca-favorites-count]').forEach(function (el) {
            el.textContent = count > 0 ? String(count) : '';
            el.style.display = count > 0 ? 'inline-flex' : 'none';
        });
    }

    /**
     * Show a loading spinner state on the button while request is in flight.
     */
    function _setLoading(btn, loading) {
        if (!btn) return;
        btn.disabled = loading;
        var icon = btn.querySelector('i');
        if (!icon) return;
        if (loading) {
            btn.dataset._origIcon = icon.className;
            icon.className = 'bi bi-hourglass-split';
        } else {
            if (btn.dataset._origIcon) {
                icon.className = btn.dataset._origIcon;
                delete btn.dataset._origIcon;
            }
        }
    }

    /**
     * Handle a heart button click.
     */
    function _handleFavoriteClick(btn) {
        var productId   = btn.dataset.productId;
        var productName = btn.dataset.productName || 'product';

        if (!productId) return;

        // Prevent double-click while request is in flight
        if (btn.disabled) return;

        // Optimistic UI: flip state immediately before server responds
        var currentlyFavorited = btn.getAttribute('aria-pressed') === 'true';
        var willBeFavorited    = !currentlyFavorited;

        _setLoading(btn, true);
        _updateAllButtons(productId, willBeFavorited);

        CA.Favorites.toggle(productId, function (err, res) {
            _setLoading(btn, false);

            if (err) {
                // Revert optimistic update on network error
                _updateAllButtons(productId, currentlyFavorited);
                if (CA.Toast) CA.Toast.error('Error', 'Could not update favorites. Please try again.');
                return;
            }

            // Auth required — redirect to login
            if (res && res._authRequired) {
                // Revert UI before redirecting
                _updateAllButtons(productId, currentlyFavorited);
                var loginUrl = (CA.appUrl || '').replace(/\/$/, '') + '/customer/auth/login';
                if (CA.Toast) {
                    CA.Toast.info('Sign in required', 'Please sign in to save favorites.');
                    setTimeout(function () { window.location.href = loginUrl; }, 1200);
                } else {
                    window.location.href = loginUrl;
                }
                return;
            }

            if (!res || !res.success) {
                // Revert on server-side failure
                _updateAllButtons(productId, currentlyFavorited);
                if (CA.Toast) CA.Toast.warning('Could not update', res && res.message ? res.message : 'Please try again.');
                return;
            }

            // Server confirmed — apply definitive state
            var serverFavorited = res.data && res.data.favorited;
            _updateAllButtons(productId, serverFavorited);

            // Update count badge
            if (res.data && typeof res.data.count !== 'undefined') {
                _updateCountBadge(res.data.count);
            }

            // Toast notification
            if (CA.Toast) {
                if (serverFavorited) {
                    CA.Toast.success('Saved to Favorites', productName + ' added to your favorites.');
                } else {
                    CA.Toast.info('Removed', productName + ' removed from favorites.');
                }
            }

            // On the favorites page: remove the card after a short delay,
            // then reload if it was the last item (to show empty state).
            if (_isOnFavoritesPage() && !serverFavorited) {
                var card = btn.closest('.caf-card');
                if (card) {
                    card.style.transition = 'opacity .25s ease, transform .25s ease';
                    card.style.opacity    = '0';
                    card.style.transform  = 'scale(0.95)';
                    setTimeout(function () {
                        card.remove();
                        // Check if grid is now empty
                        var grid = document.querySelector('.caf-grid');
                        if (grid && grid.children.length === 0) {
                            // Reload to show proper empty state
                            window.location.reload();
                        }
                    }, 280);
                }
            }
        });
    }

    /* ══════════════════════════════════════════════════════
       BOOT — wire click handler on the entire document
       ══════════════════════════════════════════════════════ */
    function boot() {
        // Single delegated listener — catches buttons rendered
        // inside product grids, favorites page, and product detail.
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-ca-favorite-toggle]');
            if (!btn) return;

            // Prevent accidental navigation on product card image wraps
            e.preventDefault();
            e.stopPropagation();

            _handleFavoriteClick(btn);
        });

        // Keyboard: Enter / Space trigger the same handler (accessibility)
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var btn = e.target.closest('[data-ca-favorite-toggle]');
            if (!btn) return;
            e.preventDefault();
            _handleFavoriteClick(btn);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

}());
