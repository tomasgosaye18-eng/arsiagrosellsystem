/**
 * =============================================================
 * AU-AgroSale — Orders Page JS  (Step 9)
 * public/assets/customer/js/orders.js
 * =============================================================
 * Handles:
 *  - Sort select → URL navigation
 *  - Auto-refresh when orders are in active states
 *  - Print receipt shortcut
 *  - Filter tab keyboard navigation (a11y)
 *  - Pagination keyboard support
 * =============================================================
 */

(function () {
    'use strict';

    /* ── Sort select → URL ──────────────────────────────────── */
    /* cordChangeSort is also declared inline in the view so it's
       available synchronously on the onchange attribute.
       This block is a no-op if the function already exists. */
    if (typeof window.cordChangeSort === 'undefined') {
        window.cordChangeSort = function (value) {
            var url = new URL(window.location.href);
            if (value === 'newest') {
                url.searchParams.delete('sort');
            } else {
                url.searchParams.set('sort', value);
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        };
    }

    /* ── Auto-refresh for active/pending orders ─────────────── */
    var REFRESH_INTERVAL_MS = 30000;   // 30 seconds
    var activeStatuses = [
        'pending_payment',
        'paid',
        'preparing',
        'ready_for_pickup'
    ];

    function hasActiveOrders() {
        // Check order list (index page)
        var items = document.querySelectorAll('.cord-item[data-status]');
        for (var i = 0; i < items.length; i++) {
            if (activeStatuses.indexOf(items[i].dataset.status) !== -1) {
                return true;
            }
        }
        // Check order detail page (single status in JS variable injected by view)
        if (window.__cordOrderStatus &&
            activeStatuses.indexOf(window.__cordOrderStatus) !== -1) {
            return true;
        }
        return false;
    }

    if (hasActiveOrders()) {
        setTimeout(function () {
            window.location.reload();
        }, REFRESH_INTERVAL_MS);
    }

    /* ── Print button shortcut ──────────────────────────────── */
    var printBtn = document.querySelector('[data-action="print"]');
    if (printBtn) {
        printBtn.addEventListener('click', function () {
            window.print();
        });
    }

    /* ── Filter tab keyboard navigation ────────────────────── */
    var filterNav = document.querySelector('.cord-filters');
    if (filterNav) {
        filterNav.addEventListener('keydown', function (e) {
            var tabs = Array.from(
                filterNav.querySelectorAll('.cord-filter-tab')
            );
            var idx = tabs.indexOf(document.activeElement);
            if (idx === -1) return;

            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                e.preventDefault();
                var next = tabs[(idx + 1) % tabs.length];
                next.focus();
            } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                e.preventDefault();
                var prev = tabs[(idx - 1 + tabs.length) % tabs.length];
                prev.focus();
            }
        });
    }

    /* ── Scroll filter tabs to keep active tab visible ──────── */
    var activeTab = document.querySelector('.cord-filter-tab--active');
    if (activeTab && filterNav) {
        activeTab.scrollIntoView({ block: 'nearest', inline: 'center' });
    }

}());
