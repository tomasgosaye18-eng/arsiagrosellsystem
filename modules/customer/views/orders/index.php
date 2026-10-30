<?php
/**
 * =========================================================
 * AU-AgroSale — My Orders Page (Step 9)
 * modules/customer/views/orders/index.php
 * =========================================================
 * Features:
 *  - Real order data from session-scoped DB queries
 *  - Filter by order status
 *  - Sort: Newest / Oldest / Highest Total / Lowest Total
 *  - Search by order reference or payment reference
 *  - Pagination
 *  - Professional empty state
 *  - Auto-refresh when orders are in pending/active states
 *  - Dark/light mode via existing CSS variables
 *  - Fully responsive — no horizontal scroll on mobile
 *
 * SECURITY: customer_session_id ownership is enforced in the
 * controller and service — never in this view.
 *
 * @var array  $orders
 * @var int    $total
 * @var int    $page
 * @var int    $perPage
 * @var int    $totalPages
 * @var string $filter
 * @var string $sort
 * @var string $search
 * @var int    $shopId
 * @var string $shopName
 * @var \AAM\Core\View $view
 */
declare(strict_types=1);

$orders     = $orders     ?? [];
$total      = (int)($total      ?? 0);
$page       = (int)($page       ?? 1);
$perPage    = (int)($perPage    ?? 10);
$totalPages = (int)($totalPages ?? 1);
$filter     = $filter     ?? 'all';
$sort       = $sort       ?? 'newest';
$search     = $search     ?? '';
$shopId     = $shopId     ?? 0;
$shopName   = $shopName   ?? '';

use AAM\Customer\Services\CustomerOrderService;

// Helper to build URL with updated query params
function cordUrl(string $base, array $overrides = [], array $current = []): string {
    $params = array_merge($current, $overrides);
    // Remove empty values
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return $base . (count($params) ? '?' . http_build_query($params) : '');
}

$qParams = [
    'filter' => $filter !== 'all'  ? $filter : '',
    'sort'   => $sort   !== 'newest' ? $sort  : '',
    'q'      => $search,
    'page'   => $page > 1 ? $page : '',
];

$filterLabels = [
    'all'              => 'All Orders',
    'pending_payment'  => 'Pending Payment',
    'paid'             => 'Paid',
    'preparing'        => 'Preparing',
    'ready_for_pickup' => 'Ready for Pickup',
    'completed'        => 'Completed / Picked Up',
    'cancelled'        => 'Cancelled',
    'payment_failed'   => 'Payment Failed',
];

$activeFilters = [
    'all', 'pending_payment', 'paid', 'preparing', 'ready_for_pickup', 'completed', 'cancelled',
];
?>
<?php $view->section('styles'); ?>
<link rel="stylesheet" href="<?= $view->url('public/assets/customer/css/orders.css') ?>?v=<?= file_exists(PUBLIC_PATH . '/assets/customer/css/orders.css') ? filemtime(PUBLIC_PATH . '/assets/customer/css/orders.css') : '1' ?>">
<?php $view->endSection(); ?>

<!-- ── Breadcrumb toolbar ────────────────────────────────────── -->
<div class="cp-toolbar">
    <div class="c-container">
        <div class="cp-toolbar__inner">
            <a href="<?= $view->url('customer/account') ?>"
               class="c-back-nav"
               aria-label="Back to Account">
                <i class="bi bi-arrow-left c-back-nav__icon" aria-hidden="true"></i>
                <span class="c-back-nav__label">Back to Account</span>
            </a>
            <nav class="cp-breadcrumb" aria-label="Breadcrumb">
                <a href="<?= $view->url('customer') ?>">Home</a>
                <span class="cp-breadcrumb__sep" aria-hidden="true">›</span>
                <span aria-current="page">My Orders</span>
            </nav>
            <?php if ($shopId && $shopName): ?>
            <div class="cp-shop-context">
                <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                <span>Pickup: <strong><?= e($shopName) ?></strong></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="c-container cord-layout" id="customer-main">

    <!-- Page heading -->
    <div class="cord-header">
        <div class="cord-header__left">
            <h1 class="cord-title">My Orders</h1>
            <p class="cord-sub">
                Track your pickup orders and view receipts.
                <?php if ($total > 0): ?>
                <span class="cord-total-count"><?= $total ?> order<?= $total !== 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </p>
        </div>
        <a href="<?= $view->url('customer/products' . ($shopId ? '?shop_id=' . $shopId : '')) ?>"
           class="c-btn c-btn-secondary c-btn-sm cord-header__cta">
            <i class="bi bi-grid" aria-hidden="true"></i>
            Browse Products
        </a>
    </div>

    <!-- ── Search + Filter + Sort bar ──────────────────────── -->
    <div class="cord-toolbar">

        <!-- Search -->
        <form method="GET" action="<?= $view->url('customer/orders') ?>" class="cord-search-form" role="search">
            <div class="cord-search-wrap">
                <i class="bi bi-search cord-search-icon" aria-hidden="true"></i>
                <input
                    type="search"
                    name="q"
                    value="<?= e($search) ?>"
                    placeholder="Search by order # or payment ref…"
                    class="cord-search-input"
                    aria-label="Search orders"
                    autocomplete="off"
                    maxlength="60"
                >
                <?php if ($filter !== 'all'): ?>
                <input type="hidden" name="filter" value="<?= e($filter) ?>">
                <?php endif; ?>
                <?php if ($sort !== 'newest'): ?>
                <input type="hidden" name="sort" value="<?= e($sort) ?>">
                <?php endif; ?>
                <?php if ($search): ?>
                <a href="<?= $view->url(cordUrl('customer/orders', ['q' => ''], $qParams)) ?>"
                   class="cord-search-clear" aria-label="Clear search">
                    <i class="bi bi-x" aria-hidden="true"></i>
                </a>
                <?php endif; ?>
                <button type="submit" class="c-btn c-btn-primary c-btn-sm cord-search-btn">
                    Search
                </button>
            </div>
        </form>

        <!-- Sort -->
        <div class="cord-sort-wrap">
            <label class="cord-sort-label" for="cord-sort-select">Sort:</label>
            <select id="cord-sort-select"
                    class="cord-sort-select"
                    aria-label="Sort orders"
                    onchange="cordChangeSort(this.value)">
                <option value="newest"     <?= $sort === 'newest'     ? 'selected' : '' ?>>Newest First</option>
                <option value="oldest"     <?= $sort === 'oldest'     ? 'selected' : '' ?>>Oldest First</option>
                <option value="total_high" <?= $sort === 'total_high' ? 'selected' : '' ?>>Highest Total</option>
                <option value="total_low"  <?= $sort === 'total_low'  ? 'selected' : '' ?>>Lowest Total</option>
            </select>
        </div>

    </div><!-- /.cord-toolbar -->

    <!-- ── Status filter tabs ───────────────────────────────── -->
    <nav class="cord-filters" aria-label="Filter orders by status" role="tablist">
        <?php foreach ($activeFilters as $f): ?>
        <a href="<?= $view->url(cordUrl('customer/orders', ['filter' => $f, 'page' => ''], $qParams)) ?>"
           class="cord-filter-tab<?= $filter === $f ? ' cord-filter-tab--active' : '' ?>"
           role="tab"
           aria-selected="<?= $filter === $f ? 'true' : 'false' ?>"
           aria-label="<?= e($filterLabels[$f] ?? ucfirst($f)) ?>">
            <?php if ($f === 'all'): ?>
                <i class="bi bi-list-ul" aria-hidden="true"></i>
            <?php elseif ($f === 'pending_payment'): ?>
                <i class="bi bi-clock" aria-hidden="true"></i>
            <?php elseif ($f === 'paid'): ?>
                <i class="bi bi-check-circle" aria-hidden="true"></i>
            <?php elseif ($f === 'preparing'): ?>
                <i class="bi bi-hourglass-split" aria-hidden="true"></i>
            <?php elseif ($f === 'ready_for_pickup'): ?>
                <i class="bi bi-bag-check" aria-hidden="true"></i>
            <?php elseif ($f === 'completed'): ?>
                <i class="bi bi-check2-all" aria-hidden="true"></i>
            <?php elseif ($f === 'cancelled'): ?>
                <i class="bi bi-x-circle" aria-hidden="true"></i>
            <?php endif; ?>
            <span class="cord-filter-tab__label"><?= e($filterLabels[$f] ?? ucfirst(str_replace('_', ' ', $f))) ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($search): ?>
    <!-- Search notice -->
    <div class="cord-search-notice" role="status" aria-live="polite">
        <i class="bi bi-search" aria-hidden="true"></i>
        Showing results for "<strong><?= e($search) ?></strong>"
        <a href="<?= $view->url(cordUrl('customer/orders', ['q' => ''], $qParams)) ?>" class="cord-search-notice__clear">
            Clear search
        </a>
    </div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
    <!-- ── Empty state ───────────────────────────────────────── -->
    <div class="cp-empty-state" role="region" aria-label="No orders found">
        <?php if ($search || $filter !== 'all'): ?>
        <div class="cp-empty-state__icon" aria-hidden="true">🔍</div>
        <h2 class="cp-empty-state__title">No orders found</h2>
        <p class="cp-empty-state__desc">
            <?php if ($search): ?>
            No orders match "<strong><?= e($search) ?></strong>".
            Try a different order number or payment reference.
            <?php else: ?>
            No orders with status "<strong><?= e($filterLabels[$filter] ?? $filter) ?></strong>".
            <?php endif; ?>
        </p>
        <div class="cp-empty-state__actions">
            <a href="<?= $view->url('customer/orders') ?>" class="c-btn c-btn-secondary">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                View All Orders
            </a>
        </div>
        <?php else: ?>
        <div class="cp-empty-state__icon" aria-hidden="true">🧾</div>
        <h2 class="cp-empty-state__title">You haven't placed any orders yet</h2>
        <p class="cp-empty-state__desc">
            Discover fresh products from Arsi University Farm.
        </p>
        <div class="cp-empty-state__actions">
            <a href="<?= $view->url('customer/products' . ($shopId ? '?shop_id=' . $shopId : '')) ?>"
               class="c-btn c-btn-primary">
                <i class="bi bi-grid" aria-hidden="true"></i>
                Browse Products
            </a>
            <?php if (!$shopId): ?>
            <a href="<?= $view->url('customer/shops') ?>" class="c-btn c-btn-secondary">
                <i class="bi bi-shop" aria-hidden="true"></i>
                Select a Shop
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php else: ?>

    <!-- ── Orders list ──────────────────────────────────────── -->
    <div class="cord-list" role="list" aria-label="Your orders" aria-live="polite">
        <?php foreach ($orders as $order):
            $status      = $order['order_status']   ?? '';
            $payStatus   = $order['payment_status'] ?? '';
            $statusLabel = CustomerOrderService::statusLabel($status);
            $statusBadge = CustomerOrderService::statusBadge($status);
            $payLabel    = CustomerOrderService::paymentStatusLabel($payStatus);
            $payBadge    = $payStatus === 'paid' ? 'success' : ($payStatus === 'failed' || $payStatus === 'expired' ? 'danger' : 'warning');
            $total       = number_format((float)$order['total_amount'], 2);
            $date        = $order['created_at'] ? date('M j, Y', strtotime($order['created_at'])) : '—';
            $time        = $order['created_at'] ? date('g:i A', strtotime($order['created_at']))  : '';
            $itemCount   = (int)($order['item_count'] ?? 0);
            $pmType      = $order['payment_method_type'] ?? '';
            $pmName      = $order['payment_method_name'] ?? '';
        ?>
        <article class="cord-item cord-item--<?= $statusBadge ?>"
                 role="listitem"
                 data-order-id="<?= (int)$order['id'] ?>"
                 data-status="<?= e($status) ?>">

            <!-- Top row: ref + date + badges -->
            <div class="cord-item__top">
                <div class="cord-item__ref">
                    <span class="cord-item__order-ref"><?= e($order['order_reference']) ?></span>
                    <span class="cord-item__date">
                        <i class="bi bi-calendar3" aria-hidden="true"></i>
                        <?= $date ?>
                        <?php if ($time): ?>
                        <span class="cord-item__time"><?= $time ?></span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="cord-item__badges">
                    <span class="c-badge c-badge-<?= $statusBadge ?>" aria-label="Order status: <?= e($statusLabel) ?>">
                        <?php if ($status === 'ready_for_pickup'): ?>
                            <i class="bi bi-bag-check-fill" aria-hidden="true"></i>
                        <?php elseif ($status === 'completed'): ?>
                            <i class="bi bi-check2-all" aria-hidden="true"></i>
                        <?php elseif ($status === 'preparing'): ?>
                            <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                        <?php elseif ($status === 'paid'): ?>
                            <i class="bi bi-check-circle" aria-hidden="true"></i>
                        <?php elseif ($status === 'cancelled' || $status === 'payment_failed'): ?>
                            <i class="bi bi-x-circle" aria-hidden="true"></i>
                        <?php else: ?>
                            <i class="bi bi-clock" aria-hidden="true"></i>
                        <?php endif; ?>
                        <?= e($statusLabel) ?>
                    </span>
                    <span class="c-badge c-badge-<?= $payBadge ?>" aria-label="Payment status: <?= e($payLabel) ?>">
                        <?= $payStatus === 'paid' ? '<i class="bi bi-check-circle-fill" aria-hidden="true"></i>' : '' ?>
                        <?= e($payLabel) ?>
                    </span>
                </div>
            </div>

            <!-- Mid row: shop + items + total -->
            <div class="cord-item__mid">
                <div class="cord-item__details">
                    <div class="cord-item__shop">
                        <i class="bi bi-shop" aria-hidden="true"></i>
                        <span><?= e($order['shop_name'] ?? 'Shop') ?></span>
                    </div>
                    <?php if ($itemCount > 0): ?>
                    <div class="cord-item__item-count">
                        <i class="bi bi-bag" aria-hidden="true"></i>
                        <span><?= $itemCount ?> item<?= $itemCount !== 1 ? 's' : '' ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($pmName): ?>
                    <div class="cord-item__payment-method">
                        <i class="bi bi-<?= $pmType === 'cash' ? 'cash-coin' : 'phone' ?>" aria-hidden="true"></i>
                        <span><?= e($pmName) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="cord-item__total" aria-label="Order total">
                    <span class="cord-item__total-label">Total</span>
                    <strong class="cord-item__total-val">ETB <?= $total ?></strong>
                </div>
            </div>

            <!-- Action row -->
            <div class="cord-item__actions">
                <a href="<?= $view->url('customer/orders/' . (int)$order['id']) ?>"
                   class="c-btn c-btn-secondary c-btn-sm"
                   aria-label="View order <?= e($order['order_reference']) ?>">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                    View Order
                </a>

                <?php if (in_array($status, ['paid', 'preparing', 'ready_for_pickup', 'completed'], true)
                       || ($status === 'pending_payment' && $pmType === 'cash')): ?>
                <a href="<?= $view->url('customer/orders/' . (int)$order['id'] . '/receipt') ?>"
                   class="c-btn c-btn-outline c-btn-sm"
                   aria-label="View receipt for <?= e($order['order_reference']) ?>">
                    <i class="bi bi-receipt" aria-hidden="true"></i>
                    Receipt
                </a>
                <?php endif; ?>

                <?php if ($status === 'pending_payment' && $pmType !== 'cash'): ?>
                <a href="<?= $view->url('customer/checkout/payment/' . (int)$order['id']) ?>"
                   class="c-btn c-btn-accent c-btn-sm"
                   aria-label="Complete payment for <?= e($order['order_reference']) ?>">
                    <i class="bi bi-credit-card" aria-hidden="true"></i>
                    Pay Now
                </a>
                <?php endif; ?>

                <?php if ($status === 'ready_for_pickup'): ?>
                <span class="cord-item__ready-badge" aria-label="Ready for pickup">
                    <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                    Ready for Pickup!
                </span>
                <?php endif; ?>
            </div>

        </article>
        <?php endforeach; ?>
    </div><!-- /.cord-list -->

    <!-- ── Pagination ──────────────────────────────────────── -->
    <?php if ($totalPages > 1): ?>
    <nav class="cord-pagination" aria-label="Order list pagination" role="navigation">
        <?php if ($page > 1): ?>
        <a href="<?= $view->url(cordUrl('customer/orders', ['page' => $page - 1], $qParams)) ?>"
           class="cord-pagination__btn" aria-label="Previous page">
            <i class="bi bi-chevron-left" aria-hidden="true"></i>
            <span>Previous</span>
        </a>
        <?php else: ?>
        <span class="cord-pagination__btn cord-pagination__btn--disabled" aria-disabled="true">
            <i class="bi bi-chevron-left" aria-hidden="true"></i>
            <span>Previous</span>
        </span>
        <?php endif; ?>

        <div class="cord-pagination__pages" role="list">
            <?php
            $startPage = max(1, $page - 2);
            $endPage   = min($totalPages, $page + 2);
            if ($startPage > 1):
            ?>
            <a href="<?= $view->url(cordUrl('customer/orders', ['page' => 1], $qParams)) ?>"
               class="cord-pagination__page" role="listitem" aria-label="Page 1">1</a>
            <?php if ($startPage > 2): ?>
            <span class="cord-pagination__ellipsis" aria-hidden="true">…</span>
            <?php endif; ?>
            <?php endif; ?>

            <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
            <?php if ($p === $page): ?>
            <span class="cord-pagination__page cord-pagination__page--current"
                  role="listitem"
                  aria-label="Page <?= $p ?>, current"
                  aria-current="page"><?= $p ?></span>
            <?php else: ?>
            <a href="<?= $view->url(cordUrl('customer/orders', ['page' => $p], $qParams)) ?>"
               class="cord-pagination__page"
               role="listitem"
               aria-label="Page <?= $p ?>"><?= $p ?></a>
            <?php endif; ?>
            <?php endfor; ?>

            <?php if ($endPage < $totalPages):
                if ($endPage < $totalPages - 1): ?>
                <span class="cord-pagination__ellipsis" aria-hidden="true">…</span>
            <?php endif; ?>
            <a href="<?= $view->url(cordUrl('customer/orders', ['page' => $totalPages], $qParams)) ?>"
               class="cord-pagination__page" role="listitem" aria-label="Page <?= $totalPages ?>"><?= $totalPages ?></a>
            <?php endif; ?>
        </div>

        <?php if ($page < $totalPages): ?>
        <a href="<?= $view->url(cordUrl('customer/orders', ['page' => $page + 1], $qParams)) ?>"
           class="cord-pagination__btn" aria-label="Next page">
            <span>Next</span>
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
        </a>
        <?php else: ?>
        <span class="cord-pagination__btn cord-pagination__btn--disabled" aria-disabled="true">
            <span>Next</span>
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
        </span>
        <?php endif; ?>
    </nav>

    <p class="cord-pagination__info" aria-live="polite">
        Showing <?= (($page - 1) * $perPage) + 1 ?>–<?= min($page * $perPage, $total) ?> of <?= $total ?> orders
    </p>
    <?php endif; ?>

    <?php endif; ?>

</div><!-- /.cord-layout -->

<?php $view->section('scripts'); ?>
<script>
(function () {
    'use strict';

    // ── Sort change ──────────────────────────────────────────
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

    // ── Auto-refresh when orders are pending or active ───────
    var orderItems = document.querySelectorAll('.cord-item[data-status]');
    var needsRefresh = false;
    var activeStatuses = ['pending_payment', 'paid', 'preparing', 'ready_for_pickup'];

    orderItems.forEach(function (el) {
        if (activeStatuses.indexOf(el.dataset.status) !== -1) {
            needsRefresh = true;
        }
    });

    // Refresh every 30s if any order is in an active state
    if (needsRefresh) {
        setTimeout(function () {
            window.location.reload();
        }, 30000);
    }

}());
</script>
<?php $view->endSection(); ?>
