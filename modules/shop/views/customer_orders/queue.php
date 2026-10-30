<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Step 8
 * Shop-Side Customer Order Queue
 * modules/shop/views/customer_orders/queue.php
 * =========================================================
 * @var array       $orders
 * @var array       $kpis
 * @var array       $filters
 * @var array       $pagination
 * @var array|null  $shop
 * @var int         $shopId
 * @var \AAM\Core\View $view
 */
declare(strict_types=1);

$orders     = $orders     ?? [];
$kpis       = $kpis       ?? [];
$filters    = $filters    ?? [];
$pagination = $pagination ?? [];
$shop       = $shop       ?? null;
$shopName   = $shop['name'] ?? 'Shop';

$statusFilter = $filters['status']    ?? '';
$payFilter    = $filters['payment']   ?? '';
$search       = $filters['search']    ?? '';
$dateFrom     = $filters['date_from'] ?? '';
$dateTo       = $filters['date_to']   ?? '';

use AAM\Customer\Services\CustomerOrderService;

$statusLabel = fn(string $s) => CustomerOrderService::statusLabel($s);
$statusBadge = fn(string $s) => CustomerOrderService::statusBadge($s);

// Build URL helper preserving current filters — uses $view->url() for correct base path
$filterUrl = function(array $overrides) use ($statusFilter, $payFilter, $search, $dateFrom, $dateTo, $view): string {
    $params = array_merge([
        'status'    => $statusFilter,
        'payment'   => $payFilter,
        'search'    => $search,
        'date_from' => $dateFrom,
        'date_to'   => $dateTo,
        'page'      => 1,
    ], $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return $view->url('customer/shop-orders') . '?' . http_build_query($params);
};

// Determine if any filter is active
$hasFilters = ($statusFilter !== '' || $payFilter !== '' || $search !== '' || $dateFrom !== '' || $dateTo !== '');
?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">
            <i class="bi bi-bag-check me-2 text-success"></i>Customer Orders
        </h1>
        <p class="text-muted mb-0 small">
            Pickup orders for <strong><?= e($shopName) ?></strong>
            <?php if ($hasFilters): ?>
            <span class="ms-2 badge bg-info-subtle text-info border border-info-subtle">Filtered</span>
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button"
                class="btn btn-outline-primary btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#searchOrderModal"
                title="Find order by reference for pickup">
            <i class="bi bi-search me-1"></i>Find Order
        </button>
        <a href="<?= $view->url('customer/shop-orders') ?>"
           class="btn btn-outline-secondary btn-sm"
           title="Refresh queue"
           id="refreshBtn">
            <i class="bi bi-arrow-clockwise me-1" id="refreshIcon"></i>Refresh
        </a>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     KPI STRIP — clickable counters link to filtered view
     ═══════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
    <div class="col-6 col-sm-3">
        <a href="<?= $filterUrl(['status' => 'paid']) ?>"
           class="card border-0 shadow-sm text-decoration-none h-100
                  <?= $statusFilter === 'paid' ? 'border-2 border-info' : '' ?>">
            <div class="card-body py-3 px-3 text-center">
                <div class="fw-bold fs-3 text-info"><?= (int)($kpis['new_count'] ?? 0) ?></div>
                <div class="small text-muted">New / Paid</div>
                <?php if (($kpis['new_count'] ?? 0) > 0): ?>
                <span class="badge bg-info mt-1" style="font-size:.6rem;">Needs attention</span>
                <?php endif; ?>
            </div>
        </a>
    </div>
    <div class="col-6 col-sm-3">
        <a href="<?= $filterUrl(['status' => 'preparing']) ?>"
           class="card border-0 shadow-sm text-decoration-none h-100
                  <?= $statusFilter === 'preparing' ? 'border-2 border-primary' : '' ?>">
            <div class="card-body py-3 px-3 text-center">
                <div class="fw-bold fs-3 text-primary"><?= (int)($kpis['preparing_count'] ?? 0) ?></div>
                <div class="small text-muted">Preparing</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-sm-3">
        <a href="<?= $filterUrl(['status' => 'ready_for_pickup']) ?>"
           class="card border-0 shadow-sm text-decoration-none h-100
                  <?= $statusFilter === 'ready_for_pickup' ? 'border-2 border-success' : '' ?>">
            <div class="card-body py-3 px-3 text-center">
                <div class="fw-bold fs-3 text-success"><?= (int)($kpis['ready_count'] ?? 0) ?></div>
                <div class="small text-muted">Ready</div>
                <?php if (($kpis['ready_count'] ?? 0) > 0): ?>
                <span class="badge bg-success mt-1" style="font-size:.6rem;">Awaiting pickup</span>
                <?php endif; ?>
            </div>
        </a>
    </div>
    <div class="col-6 col-sm-3">
        <a href="<?= $filterUrl(['status' => 'completed']) ?>"
           class="card border-0 shadow-sm text-decoration-none h-100
                  <?= $statusFilter === 'completed' ? 'border-2 border-secondary' : '' ?>">
            <div class="card-body py-3 px-3 text-center">
                <div class="fw-bold fs-3 text-muted"><?= (int)($kpis['completed_today'] ?? 0) ?></div>
                <div class="small text-muted">Done Today</div>
            </div>
        </a>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     SEARCH + FILTER BAR
     ═══════════════════════════════════════════════════════ -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <form method="GET" action="<?= $view->url('customer/shop-orders') ?>"
              class="row g-2 align-items-end" id="filterForm">

            <!-- Search -->
            <div class="col-12 col-md-4">
                <label class="form-label small fw-semibold mb-1">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-search text-muted small"></i>
                    </span>
                    <input type="text"
                           name="search"
                           value="<?= e($search) ?>"
                           class="form-control border-start-0"
                           placeholder="Order ref, pickup code, customer, phone..."
                           autocomplete="off">
                </div>
            </div>

            <!-- Status -->
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="paid"             <?= $statusFilter === 'paid'             ? 'selected' : '' ?>>New / Paid</option>
                    <option value="preparing"        <?= $statusFilter === 'preparing'        ? 'selected' : '' ?>>Preparing</option>
                    <option value="ready_for_pickup" <?= $statusFilter === 'ready_for_pickup' ? 'selected' : '' ?>>Ready for Pickup</option>
                    <option value="completed"        <?= $statusFilter === 'completed'        ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled"        <?= $statusFilter === 'cancelled'        ? 'selected' : '' ?>>Cancelled</option>
                    <option value="payment_failed"   <?= $statusFilter === 'payment_failed'   ? 'selected' : '' ?>>Payment Failed</option>
                </select>
            </div>

            <!-- Payment -->
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Payment</label>
                <select name="payment" class="form-select">
                    <option value="">Any</option>
                    <option value="paid"    <?= $payFilter === 'paid'    ? 'selected' : '' ?>>Paid</option>
                    <option value="pending" <?= $payFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="failed"  <?= $payFilter === 'failed'  ? 'selected' : '' ?>>Failed</option>
                </select>
            </div>

            <!-- Date from -->
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">From</label>
                <input type="date" name="date_from" class="form-control"
                       value="<?= e($dateFrom) ?>">
            </div>

            <!-- Date to -->
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">To</label>
                <input type="date" name="date_to" class="form-control"
                       value="<?= e($dateTo) ?>">
            </div>

            <!-- Actions -->
            <div class="col-12 d-flex gap-2 justify-content-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <a href="<?= $view->url('customer/shop-orders') ?>"
                   class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle me-1"></i>Clear
                </a>
            </div>

        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     QUICK FILTER TABS
     ═══════════════════════════════════════════════════════ -->
<div class="mb-3 d-flex gap-2 flex-wrap align-items-center">
    <a href="<?= $view->url('customer/shop-orders') ?>"
       class="btn btn-sm <?= ($statusFilter === '' && !$hasFilters) ? 'btn-dark' : 'btn-outline-secondary' ?>">
        All
        <span class="badge bg-secondary ms-1"><?= (int)($pagination['total'] ?? 0) ?></span>
    </a>
    <a href="<?= $filterUrl(['status' => 'paid', 'payment' => '', 'search' => '', 'date_from' => '', 'date_to' => '']) ?>"
       class="btn btn-sm <?= $statusFilter === 'paid' ? 'btn-info text-white' : 'btn-outline-info' ?>">
        <i class="bi bi-cash-coin me-1"></i>New
        <?php if (($kpis['new_count'] ?? 0) > 0): ?>
        <span class="badge bg-info bg-opacity-75 ms-1"><?= (int)$kpis['new_count'] ?></span>
        <?php endif; ?>
    </a>
    <a href="<?= $filterUrl(['status' => 'preparing', 'payment' => '', 'search' => '', 'date_from' => '', 'date_to' => '']) ?>"
       class="btn btn-sm <?= $statusFilter === 'preparing' ? 'btn-primary' : 'btn-outline-primary' ?>">
        <i class="bi bi-gear me-1"></i>Preparing
        <?php if (($kpis['preparing_count'] ?? 0) > 0): ?>
        <span class="badge bg-primary bg-opacity-75 ms-1"><?= (int)$kpis['preparing_count'] ?></span>
        <?php endif; ?>
    </a>
    <a href="<?= $filterUrl(['status' => 'ready_for_pickup', 'payment' => '', 'search' => '', 'date_from' => '', 'date_to' => '']) ?>"
       class="btn btn-sm <?= $statusFilter === 'ready_for_pickup' ? 'btn-success' : 'btn-outline-success' ?>">
        <i class="bi bi-check2-circle me-1"></i>Ready
        <?php if (($kpis['ready_count'] ?? 0) > 0): ?>
        <span class="badge bg-success bg-opacity-75 ms-1"><?= (int)$kpis['ready_count'] ?></span>
        <?php endif; ?>
    </a>
    <a href="<?= $filterUrl(['status' => 'completed', 'payment' => '', 'search' => '', 'date_from' => '', 'date_to' => '']) ?>"
       class="btn btn-sm <?= $statusFilter === 'completed' ? 'btn-secondary' : 'btn-outline-secondary' ?>">
        <i class="bi bi-check2-all me-1"></i>Completed
    </a>
    <a href="<?= $filterUrl(['status' => 'cancelled', 'payment' => '', 'search' => '', 'date_from' => '', 'date_to' => '']) ?>"
       class="btn btn-sm <?= $statusFilter === 'cancelled' ? 'btn-secondary' : 'btn-outline-secondary' ?>">
        <i class="bi bi-x-circle me-1"></i>Cancelled
    </a>

    <!-- Auto-refresh toggle -->
    <div class="ms-auto d-flex align-items-center gap-2">
        <span class="small text-muted d-none d-md-inline" id="lastRefreshedLabel"></span>
        <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" id="autoRefreshToggle"
                   role="switch" title="Auto-refresh every 30s">
            <label class="form-check-label small" for="autoRefreshToggle">Auto</label>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     ORDER LIST — Desktop table + Mobile cards
     ═══════════════════════════════════════════════════════ -->
<?php if (empty($orders)): ?>

<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <i class="bi bi-inbox display-4 text-muted opacity-50 d-block mb-3"></i>
        <?php if ($hasFilters): ?>
        <h5 class="text-muted">No orders match your filters</h5>
        <p class="text-muted small">Try clearing the filters or adjusting your search.</p>
        <a href="<?= $view->url('customer/shop-orders') ?>"
           class="btn btn-outline-secondary btn-sm mt-2">
            <i class="bi bi-x-circle me-1"></i>Clear Filters
        </a>
        <?php else: ?>
        <h5 class="text-muted">No customer orders yet</h5>
        <p class="text-muted small mb-0">
            Paid pickup orders appear here automatically when customers complete payment.
        </p>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>

<!-- ── Desktop table (hidden on mobile) ─────────────────── -->
<div class="card border-0 shadow-sm d-none d-md-block">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
        <span class="small fw-semibold text-muted">
            Showing <?= (int)($pagination['from'] ?? 0) ?>–<?= (int)($pagination['to'] ?? 0) ?>
            of <?= (int)($pagination['total'] ?? 0) ?> orders
        </span>
        <span class="small text-muted">
            Sorted: urgent first
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="min-width:130px;">Order</th>
                    <th style="min-width:140px;">Customer</th>
                    <th style="min-width:100px;">Date</th>
                    <th class="text-center" style="min-width:60px;">Items</th>
                    <th class="text-end" style="min-width:100px;">Total</th>
                    <th class="text-center" style="min-width:90px;">Payment</th>
                    <th class="text-center" style="min-width:130px;">Status</th>
                    <th class="text-center" style="min-width:80px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order):
                    $badge  = $statusBadge($order['order_status']);
                    $label  = $statusLabel($order['order_status']);
                    $total  = number_format((float)$order['total_amount'], 2);
                    $date   = $order['created_at'] ? date('d/m/Y H:i', strtotime($order['created_at'])) : '—';
                    $paidAt = $order['paid_at']    ? date('d/m H:i',   strtotime($order['paid_at']))    : null;
                    $icount = (int)($order['item_count'] ?? 0);
                    if ($icount === 0) {
                        // Fallback if item_count not populated
                        $icount = '—';
                    }

                    $rowClass = match($order['order_status']) {
                        'ready_for_pickup' => 'table-success',
                        'paid'             => 'table-info',
                        default            => '',
                    };
                    $pBadge = match($order['payment_status']) {
                        'paid'      => 'success',
                        'pending'   => 'warning',
                        'failed'    => 'danger',
                        default     => 'secondary',
                    };
                    $pLabel = CustomerOrderService::paymentStatusLabel($order['payment_status']);
                ?>
                <tr class="<?= $rowClass ?>">
                    <td class="ps-3">
                        <a href="<?= $view->url('customer/shop-orders/' . (int)$order['id']) ?>"
                           class="fw-semibold font-monospace text-dark text-decoration-none d-block"
                           style="font-size:.82rem;">
                            <?= e($order['order_reference']) ?>
                        </a>
                        <?php if (!empty($order['pickup_code'])): ?>
                        <div class="text-warning" style="font-size:.72rem;">
                            <i class="bi bi-key me-1"></i><?= e($order['pickup_code']) ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($paidAt): ?>
                        <div class="text-muted" style="font-size:.7rem;">Paid: <?= $paidAt ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="fw-semibold small"><?= e($order['customer_name']) ?></div>
                        <?php if ($order['customer_phone']): ?>
                        <div class="text-muted" style="font-size:.73rem;">
                            <i class="bi bi-telephone me-1"></i><?= e($order['customer_phone']) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted"><?= $date ?></td>
                    <td class="text-center">
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                            <?= $icount ?>
                        </span>
                    </td>
                    <td class="text-end fw-semibold small">ETB <?= $total ?></td>
                    <td class="text-center">
                        <span class="badge bg-<?= $pBadge ?>-subtle text-<?= $pBadge ?> border border-<?= $pBadge ?>-subtle"
                              style="font-size:.72rem;">
                            <?= $pLabel ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-<?= $badge ?>" style="font-size:.72rem;">
                            <?php if ($order['order_status'] === 'ready_for_pickup'): ?>
                            <i class="bi bi-check-circle me-1"></i>
                            <?php elseif ($order['order_status'] === 'preparing'): ?>
                            <i class="bi bi-gear me-1"></i>
                            <?php elseif ($order['order_status'] === 'paid'): ?>
                            <i class="bi bi-cash-coin me-1"></i>
                            <?php elseif ($order['order_status'] === 'completed'): ?>
                            <i class="bi bi-check2-all me-1"></i>
                            <?php elseif ($order['order_status'] === 'cancelled'): ?>
                            <i class="bi bi-x-circle me-1"></i>
                            <?php endif; ?>
                            <?= $label ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <a href="<?= $view->url('customer/shop-orders/' . (int)$order['id']) ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="small text-muted">
            Page <?= (int)($pagination['page'] ?? 1) ?> of <?= (int)($pagination['totalPages'] ?? 1) ?>
        </span>
        <nav aria-label="Order list pagination">
            <ul class="pagination pagination-sm mb-0">
                <?php
                $curPage   = (int)($pagination['page'] ?? 1);
                $totalPages = (int)($pagination['totalPages'] ?? 1);
                $start     = max(1, $curPage - 2);
                $end       = min($totalPages, $curPage + 2);
                if ($curPage > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="<?= $filterUrl(['page' => $curPage - 1]) ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php endif;
                if ($start > 1): ?>
                <li class="page-item"><a class="page-link" href="<?= $filterUrl(['page' => 1]) ?>">1</a></li>
                <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif;
                endif;
                for ($p = $start; $p <= $end; $p++): ?>
                <li class="page-item <?= $p === $curPage ? 'active' : '' ?>">
                    <a class="page-link" href="<?= $filterUrl(['page' => $p]) ?>"><?= $p ?></a>
                </li>
                <?php endfor;
                if ($end < $totalPages):
                    if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <li class="page-item">
                    <a class="page-link" href="<?= $filterUrl(['page' => $totalPages]) ?>"><?= $totalPages ?></a>
                </li>
                <?php endif;
                if ($curPage < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="<?= $filterUrl(['page' => $curPage + 1]) ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- ── Mobile card list (visible only on mobile) ────────── -->
<div class="d-md-none" id="mobileOrderList">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="small text-muted">
            <?= (int)($pagination['from'] ?? 0) ?>–<?= (int)($pagination['to'] ?? 0) ?>
            of <?= (int)($pagination['total'] ?? 0) ?> orders
        </span>
    </div>

    <?php foreach ($orders as $order):
        $badge  = $statusBadge($order['order_status']);
        $label  = $statusLabel($order['order_status']);
        $total  = number_format((float)$order['total_amount'], 2);
        $date   = $order['created_at'] ? date('d/m/Y H:i', strtotime($order['created_at'])) : '—';
        $pBadge = match($order['payment_status']) {
            'paid'      => 'success',
            'pending'   => 'warning',
            'failed'    => 'danger',
            default     => 'secondary',
        };
        $pLabel = CustomerOrderService::paymentStatusLabel($order['payment_status']);
        $cardBorder = match($order['order_status']) {
            'ready_for_pickup' => 'border-success',
            'paid'             => 'border-info',
            'preparing'        => 'border-primary',
            default            => '',
        };
    ?>
    <div class="card border-0 shadow-sm mb-3 <?= $cardBorder ? 'border-start border-3 ' . $cardBorder : '' ?>">
        <div class="card-body p-3">
            <!-- Header row: ref + status badge -->
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <a href="<?= $view->url('customer/shop-orders/' . (int)$order['id']) ?>"
                       class="fw-bold font-monospace text-dark text-decoration-none"
                       style="font-size:.9rem;">
                        <?= e($order['order_reference']) ?>
                    </a>
                    <?php if (!empty($order['pickup_code'])): ?>
                    <div class="text-warning" style="font-size:.73rem;">
                        <i class="bi bi-key me-1"></i><?= e($order['pickup_code']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <span class="badge bg-<?= $badge ?> ms-2">
                    <?php if ($order['order_status'] === 'ready_for_pickup'): ?>
                    <i class="bi bi-check-circle me-1"></i>
                    <?php elseif ($order['order_status'] === 'preparing'): ?>
                    <i class="bi bi-gear me-1"></i>
                    <?php elseif ($order['order_status'] === 'paid'): ?>
                    <i class="bi bi-cash-coin me-1"></i>
                    <?php elseif ($order['order_status'] === 'completed'): ?>
                    <i class="bi bi-check2-all me-1"></i>
                    <?php endif; ?>
                    <?= $label ?>
                </span>
            </div>

            <!-- Customer + amount row -->
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <div class="fw-semibold small"><?= e($order['customer_name']) ?></div>
                    <?php if ($order['customer_phone']): ?>
                    <div class="text-muted" style="font-size:.75rem;">
                        <i class="bi bi-telephone me-1"></i><?= e($order['customer_phone']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="text-end">
                    <div class="fw-bold text-success">ETB <?= $total ?></div>
                    <div class="text-muted" style="font-size:.72rem;"><?= $date ?></div>
                </div>
            </div>

            <!-- Payment + action row -->
            <div class="d-flex justify-content-between align-items-center">
                <span class="badge bg-<?= $pBadge ?>-subtle text-<?= $pBadge ?> border border-<?= $pBadge ?>-subtle small">
                    <i class="bi bi-credit-card me-1"></i><?= $pLabel ?>
                </span>
                <a href="<?= $view->url('customer/shop-orders/' . (int)$order['id']) ?>"
                   class="btn btn-sm btn-<?= $badge === 'success' ? 'success' : 'outline-primary' ?>"
                   style="min-height:40px;min-width:90px;">
                    <?php if ($order['order_status'] === 'ready_for_pickup'): ?>
                    <i class="bi bi-bag-check me-1"></i>Verify Pickup
                    <?php elseif ($order['order_status'] === 'paid'): ?>
                    <i class="bi bi-play-circle me-1"></i>Start
                    <?php elseif ($order['order_status'] === 'preparing'): ?>
                    <i class="bi bi-check2-circle me-1"></i>Mark Ready
                    <?php else: ?>
                    <i class="bi bi-eye me-1"></i>View
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Mobile pagination -->
    <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
    <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
        <?php if (($pagination['page'] ?? 1) > 1): ?>
        <a href="<?= $filterUrl(['page' => ($pagination['page'] ?? 1) - 1]) ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-chevron-left me-1"></i>Prev
        </a>
        <?php else: ?>
        <span></span>
        <?php endif; ?>
        <span class="small text-muted">
            Page <?= (int)($pagination['page'] ?? 1) ?> / <?= (int)($pagination['totalPages'] ?? 1) ?>
        </span>
        <?php if (($pagination['page'] ?? 1) < ($pagination['totalPages'] ?? 1)): ?>
        <a href="<?= $filterUrl(['page' => ($pagination['page'] ?? 1) + 1]) ?>"
           class="btn btn-outline-secondary btn-sm">
            Next<i class="bi bi-chevron-right ms-1"></i>
        </a>
        <?php else: ?>
        <span></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div><!-- /#mobileOrderList -->

<?php endif; ?>


<!-- ═══════════════════════════════════════════════════════
     PICKUP SEARCH MODAL — find order at pickup desk
     ═══════════════════════════════════════════════════════ -->
<div class="modal fade" id="searchOrderModal" tabindex="-1"
     aria-labelledby="searchOrderModalLabel" aria-modal="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="searchOrderModalLabel">
                    <i class="bi bi-search me-2 text-primary"></i>Find Customer Order
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="text-muted small mb-3">
                    Search by order reference, pickup code, customer name, phone, or payment reference.
                </p>
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text"
                           id="pickupSearchInput"
                           class="form-control form-control-lg"
                           placeholder="e.g. ORD-2026-000123 or pickup code X8K3M7"
                           autocomplete="off"
                           autofocus>
                    <button type="button" class="btn btn-primary" id="pickupSearchBtn">
                        Search
                    </button>
                </div>
                <div id="pickupSearchResults"></div>
            </div>
        </div>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════
     SCRIPTS
     ═══════════════════════════════════════════════════════ -->
<script>
(function () {
    'use strict';

    /* ── Pickup search modal ─────────────────────────── */
    var input    = document.getElementById('pickupSearchInput');
    var btn      = document.getElementById('pickupSearchBtn');
    var results  = document.getElementById('pickupSearchResults');
    var timer    = null;

    function escHtml(s) {
        return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function doSearch() {
        var q = (input ? input.value : '').trim();
        if (q.length < 3) {
            results.innerHTML = '<p class="text-muted small py-2">Type at least 3 characters to search.</p>';
            return;
        }
        results.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></div>';

        var appUrl = (window.AAM && window.AAM.appUrl) ? window.AAM.appUrl.replace(/\/+$/, '') : '';

        fetch(appUrl + '/api/customer/shop-orders/search?q=' + encodeURIComponent(q), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (!data.success || !data.results || data.results.length === 0) {
                results.innerHTML = '<div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-1"></i>No orders found.</div>';
                return;
            }
            var html = '<div class="list-group">';
            data.results.forEach(function(o) {
                var badgeCls   = o.status_badge || 'secondary';
                var pickupHtml = o.pickup_code
                    ? '<span class="ms-2 badge bg-warning-subtle text-warning border border-warning-subtle"><i class="bi bi-key me-1"></i>' + escHtml(o.pickup_code) + '</span>'
                    : '';
                var payBadge = o.payment_status === 'paid' ? 'success' : (o.payment_status === 'pending' ? 'warning' : 'danger');
                html += '<a href="' + appUrl + '/customer/shop-orders/' + o.id + '" class="list-group-item list-group-item-action">' +
                    '<div class="d-flex justify-content-between align-items-center flex-wrap gap-1">' +
                        '<div class="d-flex align-items-center gap-2 flex-wrap">' +
                            '<span class="fw-bold font-monospace">' + escHtml(o.order_reference) + '</span>' +
                            pickupHtml +
                            '<span class="badge bg-' + badgeCls + '">' + escHtml(o.status_label) + '</span>' +
                            '<span class="badge bg-' + payBadge + '-subtle text-' + payBadge + ' border border-' + payBadge + '-subtle small">' + escHtml(o.payment_status || '') + '</span>' +
                        '</div>' +
                        '<span class="fw-semibold text-success small">' + escHtml(o.total_fmt) + '</span>' +
                    '</div>' +
                    '<div class="small text-muted mt-1">' +
                        '<i class="bi bi-person me-1"></i>' + escHtml(o.customer_name) +
                        (o.customer_phone ? ' <i class="bi bi-telephone ms-2 me-1"></i>' + escHtml(o.customer_phone) : '') +
                        (o.shop_name ? ' <i class="bi bi-shop ms-2 me-1"></i>' + escHtml(o.shop_name) : '') +
                    '</div>' +
                '</a>';
            });
            html += '</div>';
            results.innerHTML = html;
        })
        .catch(function() {
            results.innerHTML = '<div class="alert alert-danger py-2 small">Search failed. Please try again.</div>';
        });
    }

    if (btn)   btn.addEventListener('click', doSearch);
    if (input) {
        input.addEventListener('keydown', function(e){ if (e.key === 'Enter') doSearch(); });
        input.addEventListener('input',   function(){
            clearTimeout(timer);
            timer = setTimeout(doSearch, 400);
        });
    }

    /* ── Auto-refresh ────────────────────────────────── */
    var autoToggle = document.getElementById('autoRefreshToggle');
    var refreshBtn = document.getElementById('refreshBtn');
    var refreshIcon = document.getElementById('refreshIcon');
    var lastLabel  = document.getElementById('lastRefreshedLabel');
    var refreshInterval = null;
    var REFRESH_MS = 30000; // 30 seconds

    function updateLastLabel() {
        if (lastLabel) {
            var now = new Date();
            var h   = String(now.getHours()).padStart(2,'0');
            var m   = String(now.getMinutes()).padStart(2,'0');
            var s   = String(now.getSeconds()).padStart(2,'0');
            lastLabel.textContent = 'Updated ' + h + ':' + m + ':' + s;
        }
    }

    function doRefresh() {
        if (refreshIcon) {
            refreshIcon.style.transition = 'transform 0.5s';
            refreshIcon.style.transform  = 'rotate(360deg)';
            setTimeout(function(){ refreshIcon.style.transform = ''; }, 600);
        }
        window.location.reload();
    }

    if (autoToggle) {
        // Restore auto-refresh preference
        try {
            if (localStorage.getItem('aam_co_autorefresh') === '1') {
                autoToggle.checked = true;
                refreshInterval = setInterval(doRefresh, REFRESH_MS);
            }
        } catch(e) {}

        autoToggle.addEventListener('change', function() {
            if (autoToggle.checked) {
                refreshInterval = setInterval(doRefresh, REFRESH_MS);
                try { localStorage.setItem('aam_co_autorefresh', '1'); } catch(e) {}
            } else {
                clearInterval(refreshInterval);
                refreshInterval = null;
                try { localStorage.removeItem('aam_co_autorefresh'); } catch(e) {}
            }
        });
    }

    updateLastLabel();

    /* ── Highlight active/urgent rows on mobile ──────── */
    // Already done via card border classes in PHP above.

    /* ── New order badge notification ───────────────── */
    // If there are NEW (paid, unprepared) orders — update the page <title> with a count
    var newCount = <?= (int)($kpis['new_count'] ?? 0) ?>;
    if (newCount > 0 && document.title) {
        document.title = '(' + newCount + ' New) ' + document.title.replace(/^\(\d+ New\) /, '');
    }

})();
</script>
