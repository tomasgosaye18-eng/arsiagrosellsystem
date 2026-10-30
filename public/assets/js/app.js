/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * File     : public/assets/js/app.js
 * Purpose  : Global application JavaScript.
 *            Handles: sidebar toggle, CSRF for AJAX,
 *            auto-dismiss alerts, confirmation dialogs,
 *            notification polling, global search.
 * =========================================================
 */

'use strict';

// ─── AAM Global Namespace ─────────────────────────────────────────────────────
window.AAM = window.AAM || {};

// ─── Chart.js Global Defaults — disable animation on all charts ──────────────
// Animation serves no purpose in a data management system and causes
// visual noise on every page load / data refresh.
if (typeof Chart !== 'undefined') {
    Chart.defaults.animation   = false;
    Chart.defaults.transitions = { active: { animation: { duration: 0 } } };
}

// ─── Bootstrap on DOM Ready ───────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {

    AAM.init();

});

// ─── Initialiser ─────────────────────────────────────────────────────────────
AAM.init = function () {
    AAM.initSidebarToggle();
    AAM.initAutoDismissAlerts();
    AAM.initConfirmDialogs();
    AAM.initGlobalSearch();
    AAM.initNotificationPoll();
    AAM.initFormLoading();
    AAM.initTooltips();
    AAM.initNumberInputs();
    AAM.initUserLiveSearch();
    AAM.initMobileSearch();      // responsive mobile search overlay
    AAM.initResponsiveTables();  // ensure all tables have responsive wrappers
};

// ─── Sidebar Toggle ───────────────────────────────────────────────────────────
AAM.initSidebarToggle = function () {
    var btn      = document.getElementById('sidebarToggle');
    var sidebar  = document.getElementById('sidebar');
    var backdrop = document.getElementById('sidebarBackdrop');
    if (!btn || !sidebar) return;

    // ── Helpers ──────────────────────────────────────────────────
    function isMobileTablet() {
        return window.innerWidth < 992;
    }

    function openMobileSidebar() {
        sidebar.classList.add('show');
        if (backdrop) backdrop.classList.add('show');
        document.body.style.overflow = 'hidden';
        btn.setAttribute('aria-expanded', 'true');
    }

    function closeMobileSidebar() {
        sidebar.classList.remove('show');
        if (backdrop) backdrop.classList.remove('show');
        document.body.style.overflow = '';
        btn.setAttribute('aria-expanded', 'false');
    }

    function toggleDesktopCollapse() {
        var collapsed = document.body.classList.toggle('sidebar-collapsed');
        btn.setAttribute('aria-expanded', String(!collapsed));
        localStorage.setItem('aam_sidebar_collapsed', collapsed ? '1' : '0');
    }

    // ── Restore saved state (desktop only) ───────────────────────
    if (!isMobileTablet()) {
        if (localStorage.getItem('aam_sidebar_collapsed') === '1') {
            document.body.classList.add('sidebar-collapsed');
            btn.setAttribute('aria-expanded', 'false');
        }
    }

    // ── Hamburger click ───────────────────────────────────────────
    btn.addEventListener('click', function () {
        if (isMobileTablet()) {
            if (sidebar.classList.contains('show')) {
                closeMobileSidebar();
            } else {
                openMobileSidebar();
            }
        } else {
            toggleDesktopCollapse();
        }
    });

    // ── Backdrop click — close sidebar ────────────────────────────
    if (backdrop) {
        backdrop.addEventListener('click', function () {
            closeMobileSidebar();
        });
    }

    // ── Outside click ─────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        if (isMobileTablet() &&
            sidebar.classList.contains('show') &&
            !sidebar.contains(e.target) &&
            e.target !== btn &&
            !btn.contains(e.target)) {
            closeMobileSidebar();
        }
    });

    // ── Auto-close sidebar when nav link clicked on mobile/tablet ─
    sidebar.addEventListener('click', function (e) {
        if (!isMobileTablet()) return;
        var link = e.target.closest('a.nav-link, a.sidebar-link-sub');
        if (!link) return;
        if (link.getAttribute('data-bs-toggle') === 'collapse') return;
        setTimeout(function () { closeMobileSidebar(); }, 80);
    });

    // ── Reset on window resize ────────────────────────────────────
    window.addEventListener('resize', function () {
        if (!isMobileTablet()) {
            sidebar.classList.remove('show');
            if (backdrop) backdrop.classList.remove('show');
            document.body.style.overflow = '';
        }
    });
};

// ─── Auto-Dismiss Alerts ──────────────────────────────────────────────────────
AAM.initAutoDismissAlerts = function () {
    // Success and info alerts auto-dismiss after 5 seconds
    document.querySelectorAll('.alert-success, .alert-info').forEach(function (el) {
        setTimeout(function () {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
            if (bsAlert) bsAlert.close();
        }, 5000);
    });
};

// ─── Confirmation Dialogs ─────────────────────────────────────────────────────
AAM.initConfirmDialogs = function () {
    // Any element with data-confirm="message" shows a confirm dialog before
    // submitting its form or following its link.
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            const msg = el.dataset.confirm || 'Are you sure you want to proceed?';
            if (!window.confirm(msg)) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });
};

// ─── Global Search ────────────────────────────────────────────────────────────
AAM.initGlobalSearch = function () {
    var input   = document.getElementById('globalSearch');
    var results = document.getElementById('searchResults');
    if (!input || !results) return;

    var debounceTimer = null;
    var lastQuery     = '';

    // Category icons map
    var ICONS = {
        products:   'bi-box-seam',
        farms:      'bi-flower1',
        warehouses: 'bi-building',
        shops:      'bi-shop',
        users:      'bi-person-circle',
        transfers:  'bi-arrow-left-right',
        sales:      'bi-receipt',
        inventory:  'bi-boxes',
    };

    input.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        var query = input.value.trim();

        if (query.length < 2) {
            results.style.display = 'none';
            results.innerHTML     = '';
            lastQuery = '';
            return;
        }

        if (query === lastQuery) return;

        debounceTimer = setTimeout(function () {
            lastQuery = query;
            AAM.ajax('GET',
                (window.AAM.appUrl || '').replace(/\/$/, '') + '/api/search?q=' + encodeURIComponent(query),
                null,
                function (data) {
                    if (!data || !data.success) {
                        results.innerHTML     = '<div class="aam-sr-error"><i class="bi bi-exclamation-circle me-1"></i>Unable to search. Please try again.</div>';
                        results.style.display = 'block';
                        return;
                    }
                    var html = AAM.renderSearchResults(data.data || {}, ICONS);
                    results.innerHTML     = html;
                    results.style.display = 'block';
                },
                function () {
                    results.innerHTML     = '<div class="aam-sr-error"><i class="bi bi-exclamation-circle me-1"></i>Unable to search. Please try again.</div>';
                    results.style.display = 'block';
                }
            );
        }, 300);
    });

    // Keyboard navigation
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            results.style.display = 'none';
            input.blur();
            return;
        }
        if (e.key === 'ArrowDown') {
            var first = results.querySelector('.dropdown-item');
            if (first) { e.preventDefault(); first.focus(); }
        }
        if (e.key === 'Enter' && input.value.trim().length >= 2) {
            // Navigate to full results if pressed in the input
        }
    });

    // Arrow key navigation within results
    results.addEventListener('keydown', function (e) {
        var items = Array.from(results.querySelectorAll('.dropdown-item'));
        var idx   = items.indexOf(document.activeElement);
        if (e.key === 'ArrowDown' && idx < items.length - 1) {
            e.preventDefault(); items[idx + 1].focus();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (idx > 0) items[idx - 1].focus();
            else input.focus();
        } else if (e.key === 'Escape') {
            results.style.display = 'none';
            input.focus();
        }
    });

    // Close on outside click
    document.addEventListener('click', function (e) {
        if (!input.contains(e.target) && !results.contains(e.target)) {
            results.style.display = 'none';
        }
    });

    // Reopen on focus if value present
    input.addEventListener('focus', function () {
        if (input.value.trim().length >= 2 && results.innerHTML.trim()) {
            results.style.display = 'block';
        }
    });
};

// Category labels for display
AAM._searchLabels = {
    products:   'Products',
    farms:      'Farms',
    warehouses: 'Warehouses',
    shops:      'Shops',
    users:      'Users',
    transfers:  'Transfers',
    sales:      'Sales',
    inventory:  'Inventory',
};

AAM.renderSearchResults = function (data, icons) {
    var html     = '';
    var hasAny   = false;
    var ORDER    = ['products','farms','warehouses','shops','users','transfers','sales','inventory'];

    ORDER.forEach(function (group) {
        var items = data[group];
        if (!items || !items.length) return;
        hasAny = true;
        var icon  = icons[group]  || 'bi-search';
        var label = AAM._searchLabels[group] || group.charAt(0).toUpperCase() + group.slice(1);

        html += '<span class="aam-search-group-label">' + label + '</span>';
        var base = (window.AAM.appUrl || '').replace(/\/$/, '');
        items.forEach(function (item) {
            var href = item.url.match(/^https?:\/\//) ? item.url : base + '/' + item.url.replace(/^\//, '');
            html += '<a href="' + AAM.escapeHtml(href) + '" class="dropdown-item d-flex align-items-center gap-2">' +
                    '<span class="aam-sr-icon"><i class="bi ' + icon + ' text-primary opacity-75" aria-hidden="true"></i></span>' +
                    '<span class="flex-grow-1 text-truncate">' + AAM.escapeHtml(item.label) + '</span>' +
                    (item.meta ? '<span class="text-muted ms-auto ps-2 flex-shrink-0" style="font-size:.72rem;">' + AAM.escapeHtml(item.meta) + '</span>' : '') +
                    '</a>';
        });
    });

    if (!hasAny) {
        html = '<div class="aam-sr-empty"><i class="bi bi-search me-1 opacity-50"></i>No results found.</div>';
    } else {
        html += '<div class="aam-sr-footer"><i class="bi bi-search me-1"></i>Showing top results</div>';
    }

    return html;
};

// ─── Notification Polling ─────────────────────────────────────────────────────
AAM.initNotificationPoll = function () {
    const badge = document.getElementById('notifBadge');

    function poll () {
        AAM.ajax('GET', '/api/notifications', null, function (data) {
            if (!data.success) return;
            const count = data.data ? data.data.unread_count || 0 : 0;

            if (badge) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = count > 0 ? '' : 'none';
            }

            // Render notification list
            const list = document.getElementById('notificationList');
            if (list && data.data && data.data.notifications) {
                list.innerHTML = AAM.renderNotifications(data.data.notifications);
            }
        });
    }

    // Poll every 60 seconds
    poll();
    setInterval(poll, 60000);
};

AAM.renderNotifications = function (items) {
    if (!items.length) {
        return '<div class="text-center py-4 text-muted small"><i class="bi bi-bell-slash d-block fs-3 mb-1"></i>No notifications</div>';
    }

    const priorityIcon = {
        critical: 'bi-x-circle-fill text-danger',
        high:     'bi-exclamation-triangle-fill text-warning',
        medium:   'bi-info-circle-fill text-info',
        low:      'bi-check-circle text-success',
    };

    return items.map(function (n) {
        const icon    = priorityIcon[n.priority] || 'bi-bell text-secondary';
        const unread  = !n.is_read ? 'fw-semibold bg-light' : '';
        const destUrl = n.related_url ? n.related_url : null;
        return '<a href="' + (destUrl ? AAM.escapeHtml(destUrl) : '#') + '" ' +
               'class="dropdown-item py-2 border-bottom ' + unread + '" ' +
               'data-notif-id="' + n.id + '" ' +
               'data-notif-url="' + (destUrl ? AAM.escapeHtml(destUrl) : '') + '">' +
               '<div class="d-flex gap-2 align-items-start">' +
               '<i class="bi ' + icon + ' flex-shrink-0 mt-1"></i>' +
               '<div class="flex-grow-1 overflow-hidden">' +
               '<div class="small text-truncate">' + AAM.escapeHtml(n.title) + '</div>' +
               '<div class="text-muted text-truncate" style="font-size:.75rem;">' + AAM.escapeHtml(n.created_ago || '') + '</div>' +
               '</div>' +
               (!n.is_read ? '<span class="flex-shrink-0 ms-1 mt-2 bg-primary rounded-circle d-inline-block" style="width:7px;height:7px;"></span>' : '') +
               '</div></a>';
    }).join('');
};

// Wire up AJAX mark-read on notification dropdown item click
document.addEventListener('click', function (e) {
    var item = e.target.closest('[data-notif-id]');
    if (!item) return;

    var notifId  = item.getAttribute('data-notif-id');
    var destUrl  = item.getAttribute('data-notif-url');
    var isUnread = item.classList.contains('fw-semibold');

    if (isUnread && notifId) {
        // Prevent navigation until mark-read is done (only a brief AJAX call)
        e.preventDefault();
        var csrfToken = document.querySelector('input[name=csrf_token]');
        var token     = csrfToken ? csrfToken.value : '';

        AAM.ajax('POST', '/api/notifications/' + notifId + '/read',
            { csrf_token: token },
            function () {
                // Update badge
                var badge = document.getElementById('notifBadge');
                if (badge) {
                    var cur = parseInt(badge.textContent, 10) || 0;
                    cur     = Math.max(0, cur - 1);
                    badge.textContent  = cur > 99 ? '99+' : cur;
                    badge.style.display = cur > 0 ? '' : 'none';
                }
                // Visual feedback
                item.classList.remove('fw-semibold', 'bg-light');
                var dot = item.querySelector('.bg-primary.rounded-circle');
                if (dot) dot.remove();
                // Navigate
                if (destUrl) window.location.href = destUrl;
            }
        );
    }
});

// ─── Form Loading State ───────────────────────────────────────────────────────
AAM.initFormLoading = function () {
    // Show spinner on submit buttons when form submits
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function () {
            const btn = form.querySelector('button[type=submit]');
            if (btn && !btn.dataset.noLoading) {
                const original = btn.innerHTML;
                btn.innerHTML  = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Processing...';
                btn.disabled   = true;

                // Restore after 15 seconds as fallback
                setTimeout(function () { btn.innerHTML = original; btn.disabled = false; }, 15000);
            }
        });
    });
};

// ─── Bootstrap Tooltips ───────────────────────────────────────────────────────
AAM.initTooltips = function () {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el, { trigger: 'hover' });
    });
};

// ─── Number Input +/- Buttons ─────────────────────────────────────────────────
AAM.initNumberInputs = function () {
    document.querySelectorAll('[data-qty-input]').forEach(function (wrapper) {
        const input = wrapper.querySelector('input[type=number]');
        const plus  = wrapper.querySelector('[data-plus]');
        const minus = wrapper.querySelector('[data-minus]');
        if (!input) return;

        if (plus) plus.addEventListener('click', function () {
            const step = parseFloat(input.step) || 1;
            const max  = parseFloat(input.max)  || Infinity;
            input.value = Math.min(max, parseFloat(input.value || '0') + step);
            input.dispatchEvent(new Event('change'));
        });

        if (minus) minus.addEventListener('click', function () {
            const step = parseFloat(input.step) || 1;
            const min  = parseFloat(input.min)  || 0;
            input.value = Math.max(min, parseFloat(input.value || '0') - step);
            input.dispatchEvent(new Event('change'));
        });
    });
};

// ─── AJAX Helper ─────────────────────────────────────────────────────────────
/**
 * Make a JSON AJAX request.
 *
 * @param {string}   method    HTTP method
 * @param {string}   url       Relative or absolute URL
 * @param {object}   data      POST body data
 * @param {function} callback  fn(responseData, status)
 * @param {function} onError   fn(error)
 */
AAM.ajax = function (method, url, data, callback, onError) {
    const appUrl = (window.AAM.appUrl || '').replace(/\/$/, '');
    const fullUrl = url.startsWith('http') ? url : appUrl + url;

    const headers = {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN':     window.AAM.csrfToken || '',
        'Accept':           'application/json',
    };

    const options = { method: method, headers: headers };

    if (data && (method === 'POST' || method === 'PUT')) {
        headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(data);
    }

    fetch(fullUrl, options)
        .then(function (res) { return res.json(); })
        .then(function (json) { if (callback) callback(json); })
        .catch(function (err) {
            console.error('AAM AJAX error:', err);
            if (onError) onError(err);
        });
};

// ─── Security Helper ─────────────────────────────────────────────────────────
AAM.escapeHtml = function (str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g,  '&amp;')
        .replace(/</g,  '&lt;')
        .replace(/>/g,  '&gt;')
        .replace(/"/g,  '&quot;')
        .replace(/'/g,  '&#39;');
};

// ─── Flash / Toast from JS ────────────────────────────────────────────────────
AAM.showFlash = function (type, message) {
    const map   = { success: 'success', error: 'danger', warning: 'warning', info: 'info' };
    const bsCls = map[type] || 'info';
    const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
    const icon  = icons[type] || 'bi-info-circle-fill';

    const html = '<div class="alert alert-' + bsCls + ' alert-dismissible fade show d-flex align-items-center gap-2 mb-3" role="alert">' +
                 '<i class="bi ' + icon + ' flex-shrink-0"></i><div>' + AAM.escapeHtml(message) + '</div>' +
                 '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';

    const container = document.getElementById('main-content');
    if (container) container.insertAdjacentHTML('afterbegin', html);

    if (type === 'success' || type === 'info') {
        setTimeout(function () {
            const el = container ? container.querySelector('.alert') : null;
            if (el) bootstrap.Alert.getOrCreateInstance(el).close();
        }, 5000);
    }
};

/**
 * Toast notification — used by AJAX action buttons in user management.
 * Falls back to showFlash if Bootstrap toast not supported.
 *
 * @param {string} message
 * @param {string} type  success | error | warning | info
 */
AAM.toast = function (message, type) {
    type = type || 'info';

    // Try Bootstrap toast container
    let container = document.getElementById('aamToastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'aamToastContainer';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '1090';
        document.body.appendChild(container);
    }

    const map   = { success: 'success', error: 'danger', warning: 'warning', info: 'info' };
    const bsCls = map[type] || 'info';
    const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
    const icon  = icons[type] || 'bi-info-circle-fill';

    const id      = 'toast-' + Date.now();
    const toastEl = document.createElement('div');
    toastEl.id        = id;
    toastEl.className = 'toast align-items-center text-bg-' + bsCls + ' border-0';
    toastEl.setAttribute('role', 'alert');
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');
    toastEl.innerHTML =
        '<div class="d-flex">' +
        '<div class="toast-body d-flex align-items-center gap-2">' +
        '<i class="bi ' + icon + ' flex-shrink-0"></i>' +
        '<span>' + AAM.escapeHtml(message) + '</span>' +
        '</div>' +
        '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>' +
        '</div>';

    container.appendChild(toastEl);

    const bsToast = new bootstrap.Toast(toastEl, { delay: 4000, autohide: true });
    bsToast.show();

    toastEl.addEventListener('hidden.bs.toast', function () {
        toastEl.remove();
    });
};

// ─── Phase 8A: User Management Live Search ────────────────────────────────────
AAM.initUserLiveSearch = function () {
    var input = document.getElementById('search');
    if (!input) return;

    // Auto-submit filter form when user clears the search field
    input.addEventListener('search', function () {
        if (this.value === '') {
            var form = document.getElementById('filterForm');
            if (form) form.submit();
        }
    });
};

// ─── Mobile Search Overlay ────────────────────────────────────────────────────
AAM.initMobileSearch = function () {
    var toggleBtn  = document.getElementById('mobileSearchToggle');
    var overlay    = document.getElementById('mobileSearchOverlay');
    var closeBtn   = document.getElementById('mobileSearchClose');
    var mInput     = document.getElementById('mobileSearchInput');
    var mResults   = document.getElementById('mobileSearchResults');

    if (!toggleBtn || !overlay) return;

    var ICONS = {
        products:   'bi-box-seam',
        farms:      'bi-flower1',
        warehouses: 'bi-building',
        shops:      'bi-shop',
        users:      'bi-person-circle',
        transfers:  'bi-arrow-left-right',
        sales:      'bi-receipt',
        inventory:  'bi-boxes',
    };

    function openOverlay() {
        overlay.classList.add('show');
        if (mInput) { mInput.value = ''; mInput.focus(); }
    }

    function closeOverlay() {
        overlay.classList.remove('show');
        if (mResults) { mResults.style.display = 'none'; mResults.innerHTML = ''; }
    }

    toggleBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        openOverlay();
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            closeOverlay();
        });
    }

    if (mInput && mResults) {
        var debounceTimer = null;
        var lastQuery     = '';

        mInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            var query = mInput.value.trim();

            if (query.length < 2) {
                mResults.style.display = 'none';
                mResults.innerHTML     = '';
                lastQuery = '';
                return;
            }
            if (query === lastQuery) return;

            debounceTimer = setTimeout(function () {
                lastQuery = query;
                AAM.ajax('GET',
                    (window.AAM.appUrl || '').replace(/\/$/, '') + '/api/search?q=' + encodeURIComponent(query),
                    null,
                    function (data) {
                        if (!data || !data.success) {
                            mResults.innerHTML     = '<div class="aam-sr-error p-2"><i class="bi bi-exclamation-circle me-1"></i>Unable to search.</div>';
                            mResults.style.display = 'block';
                            return;
                        }
                        var html = AAM.renderSearchResults(data.data || {}, ICONS);
                        mResults.innerHTML     = html;
                        mResults.style.display = 'block';
                    },
                    function () {
                        mResults.innerHTML     = '<div class="aam-sr-error p-2"><i class="bi bi-exclamation-circle me-1"></i>Unable to search.</div>';
                        mResults.style.display = 'block';
                    }
                );
            }, 300);
        });

        mInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeOverlay();
            }
        });

        // Close overlay when a result is clicked
        mResults.addEventListener('click', function (e) {
            if (e.target.closest('.dropdown-item')) {
                setTimeout(closeOverlay, 100);
            }
        });
    }
};

// ─── Responsive Tables: auto-wrap bare tables ────────────────────────────────
// Ensures every <table> inside #main-content lives inside a .table-responsive
// wrapper so horizontal scroll is contained to the table, not the page.
AAM.initResponsiveTables = function () {
    var main = document.getElementById('main-content');
    if (!main) return;

    main.querySelectorAll('table').forEach(function (table) {
        // Skip if already inside a .table-responsive wrapper
        if (table.parentElement && table.parentElement.classList.contains('table-responsive')) return;
        // Skip tiny utility tables (e.g., inside forms)
        if (table.rows.length === 0) return;

        var wrapper = document.createElement('div');
        wrapper.className = 'table-responsive';
        table.parentNode.insertBefore(wrapper, table);
        wrapper.appendChild(table);
    });
};
