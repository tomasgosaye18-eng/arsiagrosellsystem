<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : templates/topbar.php
 * Purpose  : Fixed top navigation bar.
 *            Hamburger, logo, global search, notifications,
 *            user profile dropdown.
 * =========================================================
 *
 * @var \AAM\Core\View            $view
 * @var array<string,mixed>|null  $currentUser
 * @var string|null               $userRole
 * @var string                    $appName
 * @var string                    $csrfToken
 */
declare(strict_types=1);

$userName     = e(\AAM\Core\SessionManager::get(SESSION_USER_NAME, 'User'));
$unreadCount  = 0;

// Profile photo — read from session (synced on every profile save)
$_userData    = \AAM\Core\SessionManager::get(SESSION_USER_DATA, []);
$_photoFile   = $_userData['profile_photo'] ?? null;
$_avatarUrl   = $_photoFile
    ? \AAM\Helpers\UploadHelper::profileImageUrl($_photoFile)
    : null;

// Load unread notification count (safely — fails silently if DB not ready)
try {
    $uid = \AAM\Core\SessionManager::userId();
    if ($uid) {
        $unreadCount = (int) \AAM\Core\Database::getInstance()->fetchColumn(
            "SELECT COUNT(*) FROM notifications WHERE recipient_user_id = :uid AND is_read = 0",
            ['uid' => $uid]
        );
    }
} catch (\Throwable $e) {
    $unreadCount = 0;
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top shadow-sm" id="topbar" role="navigation" aria-label="Top navigation">
    <div class="container-fluid">

        <!-- ─── Hamburger + Logo ─────────────────────────────── -->
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-link text-white p-0 me-2" id="sidebarToggle" aria-label="Toggle sidebar" aria-controls="sidebar" aria-expanded="true">
                <i class="bi bi-list fs-4"></i>
            </button>

            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="<?= url('dashboard') ?>">
                <img src="<?= $view->asset('images/favicon.svg') ?>" alt="AAM Logo" width="28" height="28" class="d-inline-block"
                     onerror="this.style.display='none'">
                <span class="d-none d-md-inline"><?= e($appName) ?></span>
            </a>
        </div>

        <!-- ─── Mobile Search Toggle (tablet/mobile only) ──────── -->
        <button class="btn btn-link text-white p-1 btn-mobile-search" id="mobileSearchToggle"
                aria-label="Search" title="Search">
            <i class="bi bi-search fs-5"></i>
        </button>

        <!-- ─── Right Side Icons ──────────────────────────────── -->
        <div class="d-flex align-items-center gap-2 ms-auto">

            <!-- Global Search -->
            <div class="d-none d-lg-block position-relative" style="width: 300px;">
                <input type="search"
                       class="form-control form-control-sm bg-white bg-opacity-10 border-0 text-white"
                       id="globalSearch"
                       placeholder="Search products, sales, transfers..."
                       autocomplete="off"
                       aria-label="Global search"
                       style="--bs-bg-opacity: .15; padding-right: 2rem;">
                <i class="bi bi-search position-absolute top-50 end-0 translate-middle-y me-2 text-white opacity-75" aria-hidden="true"></i>
                <!-- Search results dropdown -->
                <div id="searchResults" class="dropdown-menu w-100 shadow-lg" aria-live="polite" style="display:none;"></div>
            </div>

            <!-- Notifications Bell -->
            <div class="dropdown">
                <button class="btn btn-link text-white position-relative p-1"
                        id="notificationBell"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                        aria-label="Notifications (<?= $unreadCount ?> unread)">
                    <i class="bi bi-bell-fill fs-5"></i>
                    <?php if ($unreadCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                              id="notifBadge" aria-label="<?= $unreadCount ?> unread notifications">
                            <?= $unreadCount > 99 ? '99+' : $unreadCount ?>
                        </span>
                    <?php endif; ?>
                </button>

                <div class="dropdown-menu dropdown-menu-end shadow-lg p-0" style="min-width: 340px; max-height: 420px; overflow-y: auto;"
                     aria-labelledby="notificationBell">
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom theme-dropdown-header">
                        <span class="fw-semibold small">Notifications</span>
                        <a href="<?= url('notifications') ?>" class="small text-primary">View all</a>
                    </div>
                    <div id="notificationList">
                        <div class="text-center py-4 text-muted small" id="notifPlaceholder">
                            <i class="bi bi-arrow-repeat d-block fs-3 mb-1 notif-loading-spinner" style="display:none!important"></i>
                            <i class="bi bi-bell-slash d-block fs-3 mb-1" id="notifEmptyIcon"></i>
                            No notifications
                        </div>
                    </div>
                    <div class="border-top px-3 py-2 theme-dropdown-header text-center">
                        <form method="POST" action="<?= url('notifications/read-all') ?>" class="d-inline">
                            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                            <button type="submit" class="btn btn-link btn-sm p-0 text-muted">Mark all as read</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- User Profile Dropdown -->
            <div class="dropdown">
                <button class="btn btn-link text-white d-flex align-items-center gap-1 p-1 text-decoration-none"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                        aria-label="User menu">
                    <?php if ($_avatarUrl): ?>
                        <img src="<?= e($_avatarUrl) ?>"
                             alt="<?= $userName ?>"
                             width="32" height="32"
                             class="rounded-circle object-fit-cover"
                             style="width:32px;height:32px;object-fit:cover;border:2px solid rgba(255,255,255,.35);"
                             onerror="this.outerHTML='<span class=\'avatar-sm bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center\' style=\'width:32px;height:32px\' aria-hidden=\'true\'><i class=\'bi bi-person-fill small text-white\'></i></span>'"
                             aria-hidden="true">
                    <?php else: ?>
                        <div class="avatar-sm bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center"
                             style="width:32px;height:32px;" aria-hidden="true">
                            <i class="bi bi-person-fill small text-white"></i>
                        </div>
                    <?php endif; ?>
                    <span class="d-none d-md-inline small fw-semibold"><?= $userName ?></span>
                    <i class="bi bi-chevron-down small" aria-hidden="true"></i>
                </button>

                <ul class="dropdown-menu dropdown-menu-end shadow" aria-label="User options">
                    <li>
                        <span class="dropdown-item-text d-flex align-items-center gap-2 py-2">
                            <?php if ($_avatarUrl): ?>
                                <img src="<?= e($_avatarUrl) ?>"
                                     alt=""
                                     width="36" height="36"
                                     class="rounded-circle flex-shrink-0"
                                     style="width:36px;height:36px;object-fit:cover;border:2px solid #e8ecf0;"
                                     onerror="this.outerHTML='<span class=\'rounded-circle bg-primary d-flex align-items-center justify-content-center flex-shrink-0\' style=\'width:36px;height:36px\'><i class=\'bi bi-person-fill text-white small\'></i></span>'"
                                     aria-hidden="true">
                            <?php else: ?>
                                <span class="rounded-circle bg-primary d-flex align-items-center justify-content-center flex-shrink-0"
                                      style="width:36px;height:36px;" aria-hidden="true">
                                    <i class="bi bi-person-fill text-white small"></i>
                                </span>
                            <?php endif; ?>
                            <div>
                                <div class="fw-semibold small"><?= $userName ?></div>
                                <div class="text-muted" style="font-size:.75rem;"><?= e(\AAM\Helpers\StringHelper::titleCase($userRole ?? '')) ?></div>
                            </div>
                        </span>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="<?= url('profile') ?>">
                            <i class="bi bi-person-circle me-2" aria-hidden="true"></i>My Profile
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="<?= url('profile/change-password') ?>">
                            <i class="bi bi-key me-2" aria-hidden="true"></i>Change Password
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="<?= url('logout') ?>">
                            <i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>

        </div><!-- /.d-flex -->
    </div><!-- /.container-fluid -->
</nav>
<!-- Mobile Search Overlay (slides over the topbar on small screens) -->
<div id="mobileSearchOverlay" role="search" aria-label="Mobile search">
    <button class="btn btn-link text-white p-1 flex-shrink-0" id="mobileSearchClose" aria-label="Close search">
        <i class="bi bi-arrow-left fs-5"></i>
    </button>
    <div class="position-relative flex-grow-1">
        <input type="search"
               class="form-control form-control-sm bg-white bg-opacity-10 border-0 text-white"
               id="mobileSearchInput"
               placeholder="Search products, sales, transfers…"
               autocomplete="off"
               aria-label="Mobile search"
               style="--bs-bg-opacity: .15;">
        <i class="bi bi-search position-absolute top-50 end-0 translate-middle-y me-2 text-white opacity-75" aria-hidden="true"></i>
        <div id="mobileSearchResults" class="dropdown-menu w-100 shadow-lg" aria-live="polite" style="display:none;"></div>
    </div>
</div>

<!-- Spacer for fixed topbar height -->
<div style="height: 56px;" aria-hidden="true"></div>

<script>
/* ============================================================
 * Notification Bell — AJAX dropdown loader
 * Fetches GET /api/notifications on every bell open.
 * Updates badge count in real-time.
 * ============================================================ */
(function () {
    'use strict';

    const bell        = document.getElementById('notificationBell');
    const listEl      = document.getElementById('notificationList');
    const badgeEl     = document.getElementById('notifBadge');
    const apiUrl      = '<?= url('api/notifications') ?>';
    const markReadUrl = '<?= url('api/notifications') ?>'; // base, /{id}/read appended
    const csrfToken   = '<?= e($csrfToken) ?>';
    const viewAllUrl  = '<?= url('notifications') ?>';

    if (!bell || !listEl) return;

    /* ── Priority colour map ─────────────────────────────── */
    const priorityClass = {
        critical : 'bg-danger',
        high     : 'bg-danger',
        medium   : 'bg-warning',
        low      : 'bg-secondary',
    };

    /* ── Notification type icon map ─────────────────────── */
    function typeIcon(type) {
        const map = {
            dispatch_pending  : 'bi-hourglass-split text-warning',
            dispatch_approved : 'bi-check-circle-fill text-success',
            dispatch_rejected : 'bi-x-circle-fill text-danger',
            dispatch_received : 'bi-inbox-fill text-info',
            transfer_pending  : 'bi-arrow-left-right text-warning',
            transfer_received : 'bi-check2-all text-success',
            low_stock         : 'bi-exclamation-triangle-fill text-warning',
            out_of_stock      : 'bi-x-octagon-fill text-danger',
            system_announcement: 'bi-megaphone-fill text-primary',
            user_message      : 'bi-chat-dots-fill text-primary',
        };
        return map[type] || 'bi-bell-fill text-secondary';
    }

    /* ── Render one notification row ─────────────────────── */
    function buildRow(n) {
        const isUnread   = n.is_read == 0;
        const dotHtml    = isUnread
            ? '<span class="position-absolute top-50 start-0 translate-middle-y ms-n1 rounded-circle bg-primary" style="width:6px;height:6px;" aria-hidden="true"></span>'
            : '';
        const href       = n.related_url || viewAllUrl;
        const bgClass    = isUnread ? 'bg-light' : '';
        const pClass     = priorityClass[n.priority] || 'bg-secondary';
        const icon       = typeIcon(n.notification_type);
        // Truncate message to 2 lines worth
        const msgShort   = (n.message || '').split('\n')[0].slice(0, 80)
                           + ((n.message || '').length > 80 ? '…' : '');

        return `<a href="${escHtml(href)}"
                   class="dropdown-item px-3 py-2 border-bottom position-relative ${bgClass} notif-item"
                   data-id="${escHtml(String(n.id))}"
                   data-read="${n.is_read}"
                   style="white-space:normal;line-height:1.3;"
                   aria-label="${isUnread ? 'Unread: ' : ''}${escHtml(n.title)}">
            ${dotHtml}
            <div class="d-flex align-items-start gap-2" style="padding-left:${isUnread ? '10px' : '0'}">
                <div class="flex-shrink-0 mt-1">
                    <i class="bi ${icon} fs-5"></i>
                </div>
                <div class="flex-grow-1 overflow-hidden">
                    <div class="fw-semibold small text-truncate">${escHtml(n.title)}</div>
                    <div class="text-muted" style="font-size:.78rem;">${escHtml(msgShort)}</div>
                    <div class="text-muted mt-1" style="font-size:.7rem;">
                        <span class="badge ${pClass} me-1" style="font-size:.55rem;">${escHtml(n.priority || 'medium')}</span>
                        ${escHtml(n.created_ago || '')}
                    </div>
                </div>
            </div>
        </a>`;
    }

    /* ── HTML-escape helper ──────────────────────────────── */
    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /* ── Update topbar badge ─────────────────────────────── */
    function updateBadge(count) {
        if (count > 0) {
            const label = count > 99 ? '99+' : String(count);
            if (badgeEl) {
                badgeEl.textContent = label;
                badgeEl.style.display = '';
            } else {
                // Create badge dynamically if it didn't exist on page load
                const b = document.createElement('span');
                b.id        = 'notifBadge';
                b.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                b.setAttribute('aria-label', count + ' unread notifications');
                b.textContent = label;
                bell.appendChild(b);
            }
        } else {
            if (badgeEl) badgeEl.style.display = 'none';
        }
    }

    /* ── Render the dropdown list ────────────────────────── */
    function renderList(notifications) {
        if (!notifications || notifications.length === 0) {
            listEl.innerHTML = `
                <div class="text-center py-4 text-muted small">
                    <i class="bi bi-bell-slash d-block fs-3 mb-1"></i>
                    No notifications
                </div>`;
            return;
        }
        listEl.innerHTML = notifications.map(buildRow).join('');
    }

    /* ── Show loading spinner ────────────────────────────── */
    function showLoading() {
        listEl.innerHTML = `
            <div class="text-center py-4 text-muted small">
                <div class="spinner-border spinner-border-sm mb-1" role="status">
                    <span class="visually-hidden">Loading…</span>
                </div>
                <div>Loading notifications…</div>
            </div>`;
    }

    /* ── Fetch from API ──────────────────────────────────── */
    function fetchNotifications() {
        showLoading();
        fetch(apiUrl, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(function(data) {
            if (data && data.success) {
                updateBadge(data.data.unread_count || 0);
                renderList(data.data.notifications || []);
            } else {
                renderList([]);
            }
        })
        .catch(function() {
            listEl.innerHTML = `
                <div class="text-center py-3 text-muted small">
                    <i class="bi bi-exclamation-circle d-block fs-4 mb-1 text-danger"></i>
                    Could not load notifications.
                </div>`;
        });
    }

    /* ── Mark individual notification as read on click ────── */
    listEl.addEventListener('click', function(e) {
        const item = e.target.closest('.notif-item');
        if (!item) return;
        const id   = item.dataset.id;
        const read = item.dataset.read;
        if (id && read == 0) {
            // Fire-and-forget — mark as read via AJAX POST
            fetch(markReadUrl + '/' + id + '/read', {
                method  : 'POST',
                headers : {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type'    : 'application/x-www-form-urlencoded',
                },
                body: '_token=' + encodeURIComponent(csrfToken)
            }).catch(function(){});
            // Optimistically update the dot + bg
            item.dataset.read = '1';
            item.classList.remove('bg-light');
            const dot = item.querySelector('.rounded-circle.bg-primary');
            if (dot) dot.remove();
            // Reduce badge count
            const cur = parseInt(bell.querySelector('#notifBadge') && bell.querySelector('#notifBadge').textContent || '0', 10);
            if (cur > 0) updateBadge(cur - 1);
        }
    });

    /* ── Load on dropdown open ───────────────────────────── */
    bell.addEventListener('show.bs.dropdown', fetchNotifications);

    /* ── Periodic background refresh (every 60 s) ────────── */
    setInterval(function() {
        fetch(apiUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.ok ? r.json() : null; })
            .then(function(d) {
                if (d && d.success) updateBadge(d.data.unread_count || 0);
            })
            .catch(function(){});
    }, 60000);

})();
</script>
