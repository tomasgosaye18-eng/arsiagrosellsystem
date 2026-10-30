<?php
/**
 * GM — Customer Orders Monitoring
 * Route: GET /gm/orders
 *
 * @var array  $kpis
 * @var array  $orders
 * @var array  $pagination
 * @var array  $filters
 * @var array  $shops
 */
declare(strict_types=1);

use AAM\GeneralManager\Services\GMCustomerOrderService as GCOS;

$kpis       ??= [];
$orders     ??= [];
$pagination ??= [];
$filters    ??= [];
$shops      ??= [];

$n   = fn($v, int $d = 0): string => is_numeric($v) ? number_format((float)$v, $d) : '—';
$etb = fn($v): string => 'ETB ' . number_format((float)$v, 2);
$dt  = fn($v): string => $v ? date('d/m/Y H:i', strtotime($v)) : '—';
$d   = fn($v): string => $v ? date('d/m/Y', strtotime($v)) : '—';

$statusOptions = [
    ''                  => 'All Statuses',
    'active'            => 'Active (Paid/Preparing/Ready)',
    'pending_payment'   => 'Pending Payment',
    'paid'              => 'Paid — Awaiting Prep',
    'preparing'         => 'Preparing',
    'ready_for_pickup'  => 'Ready for Pickup',
    'completed'         => 'Completed',
    'cancelled'         => 'Cancelled',
    'payment_failed'    => 'Payment Failed',
];

$payOptions = [
    ''          => 'All Payments',
    'pending'   => 'Pending',
    'paid'      => 'Paid',
    'failed'    => 'Failed',
    'expired'   => 'Expired',
    'cancelled' => 'Cancelled',
];
?>

<!-- ── Page Header ─────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-4"
     style="background:linear-gradient(135deg,#1a3a4a,#0d5c6e);">
    <div class="card-body py-3 text-white d-flex align-items-center gap-3 flex-wrap">
        <div class="rounded-circle bg-white bg-opacity-20 d-flex align-items-center
                    justify-content-center flex-shrink-0"
             style="width:48px;height:48px;">
            <i class="bi bi-bag-check-fill fs-4"></i>
        </div>
        <div class="flex-grow-1">
            <h5 class="mb-0 fw-bold">Customer Orders</h5>
            <small class="opacity-75">Cross-shop order monitoring — General Manager (read-only)</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= url('gm/shops/sales') ?>"
               class="btn btn-outline-light btn-sm">
                <i class="bi bi-bar-chart me-1"></i>Sales Overview
            </a>
            <a href="<?= url('gm/analytics') ?>"
               class="btn btn-outline-light btn-sm">
                <i class="bi bi-graph-up me-1"></i>Analytics
            </a>
        </div>
    </div>
</div>

<!-- ── KPI Strip ──────────────────────────────────────── -->
<div class="row g-3 mb-4">

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid var(--bs-warning)!important;">
            <div class="card-body py-3">
                <i class="bi bi-clock-history text-warning fs-3 d-block mb-1"></i>
                <div class="fw-bold fs-5"><?= $n($kpis['orders_today'] ?? 0) ?></div>
                <div class="text-muted small">Orders Today</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid var(--bs-info)!important;">
            <div class="card-body py-3">
                <i class="bi bi-hourglass-split text-info fs-3 d-block mb-1"></i>
                <div class="fw-bold fs-5"><?= $n($kpis['active_orders'] ?? 0) ?></div>
                <div class="text-muted small">Active Queue</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid #198754!important;">
            <div class="card-body py-3">
                <i class="bi bi-bag-check-fill text-success fs-3 d-block mb-1"></i>
                <div class="fw-bold fs-5"><?= $n($kpis['ready_for_pickup'] ?? 0) ?></div>
                <div class="text-muted small">Ready for Pickup</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid #198754!important;">
            <div class="card-body py-3">
                <i class="bi bi-check2-circle text-success fs-3 d-block mb-1"></i>
                <div class="fw-bold fs-5"><?= $n($kpis['completed_today'] ?? 0) ?></div>
                <div class="text-muted small">Completed Today</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid var(--bs-primary)!important;">
            <div class="card-body py-3">
                <i class="bi bi-calendar-month text-primary fs-3 d-block mb-1"></i>
                <div class="fw-bold fs-5"><?= $n($kpis['completed_this_month'] ?? 0) ?></div>
                <div class="text-muted small">Completed / Month</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid #20c997!important;">
            <div class="card-body py-3">
                <i class="bi bi-currency-exchange text-success fs-3 d-block mb-1"
                   style="color:#20c997!important;"></i>
                <div class="fw-bold" style="font-size:.9rem!important;">
                    <?= $etb($kpis['revenue_this_month'] ?? 0) ?>
                </div>
                <div class="text-muted small">Revenue / Month</div>
            </div>
        </div>
    </div>

</div>

<!-- ── Order Status Quick Filters ─────────────────────── -->
<div class="row g-2 mb-3">
    <div class="col-12">
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <span class="text-muted small fw-semibold me-1">Quick:</span>
            <?php
            $qf = [
                [''                => ['All Orders',        'secondary', (int)($kpis['orders_this_month'] ?? 0)]],
                ['active'          => ['Active',            'info',      (int)($kpis['active_orders'] ?? 0)]],
                ['ready_for_pickup'=> ['Ready',             'success',   (int)($kpis['ready_for_pickup'] ?? 0)]],
                ['completed'       => ['Completed',         'success',   (int)($kpis['completed_this_month'] ?? 0)]],
                ['pending_payment' => ['Pending Payment',   'warning',   (int)($kpis['pending_payment'] ?? 0)]],
                ['cancelled'       => ['Cancelled',         'danger',    (int)($kpis['cancelled_this_month'] ?? 0)]],
            ];
            $cur = $filters['status'] ?? '';
            foreach ($qf as $item):
                [$val, [$lbl, $col, $cnt]] = [array_key_first($item), reset($item)];
            ?>
            <a href="<?= url('gm/orders') ?>?status=<?= urlencode($val) ?>"
               class="btn btn-sm <?= $cur === $val ? 'btn-'.$col : 'btn-outline-'.$col ?>">
                <?= e($lbl) ?>
                <?php if ($cnt > 0): ?>
                <span class="badge <?= $cur === $val ? 'bg-white text-'.$col : 'bg-'.$col.' text-white' ?> ms-1">
                    <?= $cnt ?>
                </span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ── Filters Card ────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2 px-3">
        <form method="GET" action="<?= url('gm/orders') ?>"
              class="row g-2 align-items-end">

            <div class="col-12 col-md-3">
                <input type="search" name="search" value="<?= e($filters['search'] ?? '') ?>"
                       class="form-control form-control-sm"
                       placeholder="Order ref, customer, phone…">
            </div>

            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <?php foreach ($statusOptions as $v => $l): ?>
                    <option value="<?= e($v) ?>" <?= ($filters['status'] ?? '') === $v ? 'selected' : '' ?>>
                        <?= e($l) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <select name="payment_status" class="form-select form-select-sm">
                    <?php foreach ($payOptions as $v => $l): ?>
                    <option value="<?= e($v) ?>" <?= ($filters['payment_status'] ?? '') === $v ? 'selected' : '' ?>>
                        <?= e($l) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <select name="shop_id" class="form-select form-select-sm">
                    <option value="">All Shops</option>
                    <?php foreach ($shops as $sh): ?>
                    <option value="<?= (int)$sh['id'] ?>"
                            <?= (int)($filters['shop_id'] ?? 0) === (int)$sh['id'] ? 'selected' : '' ?>>
                        <?= e($sh['shop_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-md-1">
                <input type="date" name="date_from"
                       value="<?= e($filters['date_from'] ?? '') ?>"
                       class="form-control form-control-sm"
                       title="Date from">
            </div>

            <div class="col-6 col-md-1">
                <input type="date" name="date_to"
                       value="<?= e($filters['date_to'] ?? '') ?>"
                       class="form-control form-control-sm"
                       title="Date to">
            </div>

            <div class="col-12 col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="bi bi-search"></i>
                </button>
                <a href="<?= url('gm/orders') ?>"
                   class="btn btn-outline-secondary btn-sm" title="Clear">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>

        </form>
    </div>
</div>

<!-- ── Orders Table ────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex align-items-center
                justify-content-between py-2">
        <div class="fw-semibold small">
            <i class="bi bi-bag-check-fill me-2 text-info"></i>
            Customer Orders
            <?php if (($pagination['total'] ?? 0) > 0): ?>
            <span class="badge bg-secondary ms-1"><?= number_format($pagination['total']) ?></span>
            <?php endif; ?>
        </div>
        <small class="text-muted">
            GM view — read only. Process orders at the shop level.
        </small>
    </div>

    <div class="card-body p-0">
        <?php if (empty($orders)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-bag d-block fs-2 mb-2 opacity-25"></i>
            <p class="mb-1 fw-semibold">No orders found</p>
            <small>
                <?php if (!empty(array_filter($filters))): ?>
                    Try adjusting your filters.
                    <a href="<?= url('gm/orders') ?>" class="text-decoration-none ms-1">Clear all</a>
                <?php else: ?>
                    Customer orders will appear here once customers start placing orders.
                <?php endif; ?>
            </small>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="min-width:140px;">Order Ref</th>
                        <th style="min-width:130px;">Customer</th>
                        <th style="min-width:120px;">Shop</th>
                        <th class="text-center" style="min-width:60px;">Items</th>
                        <th class="text-end" style="min-width:110px;">Amount</th>
                        <th style="min-width:100px;">Payment</th>
                        <th style="min-width:140px;">Order Status</th>
                        <th style="min-width:130px;">Date</th>
                        <th class="pe-3 text-end" style="min-width:75px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o):
                        $oStatus  = $o['order_status']   ?? '';
                        $pStatus  = $o['payment_status'] ?? '';
                        $oBadge   = GCOS::statusBadge($oStatus);
                        $oLabel   = GCOS::statusLabel($oStatus);
                        $pBadge   = GCOS::paymentStatusBadge($pStatus);
                        $rowClass = match($oStatus) {
                            'ready_for_pickup' => 'table-success bg-opacity-50',
                            'paid'             => 'table-info bg-opacity-25',
                            'preparing'        => '',
                            'cancelled','payment_failed' => 'table-secondary',
                            default            => '',
                        };
                    ?>
                    <tr class="<?= $rowClass ?>">

                        <!-- Order Ref -->
                        <td class="ps-3">
                            <a href="<?= url('gm/orders/' . (int)$o['id']) ?>"
                               class="fw-semibold text-decoration-none font-monospace small">
                                <?= e($o['order_reference'] ?? '#' . $o['id']) ?>
                            </a>
                            <?php if (!empty($o['payment_reference'])): ?>
                            <div class="text-muted" style="font-size:.63rem;">
                                <?= e($o['payment_reference']) ?>
                            </div>
                            <?php endif; ?>
                        </td>

                        <!-- Customer -->
                        <td>
                            <div class="small fw-semibold"><?= e($o['customer_name'] ?? '—') ?></div>
                            <?php if (!empty($o['customer_phone'])): ?>
                            <div class="text-muted" style="font-size:.68rem;">
                                <i class="bi bi-telephone me-1"></i><?= e($o['customer_phone']) ?>
                            </div>
                            <?php endif; ?>
                        </td>

                        <!-- Shop -->
                        <td class="small text-muted"><?= e($o['shop_name'] ?? '—') ?></td>

                        <!-- Items -->
                        <td class="text-center small">
                            <?= (int)($o['item_count'] ?? 0) ?>
                        </td>

                        <!-- Amount -->
                        <td class="text-end small fw-semibold">
                            <?= $etb($o['total_amount'] ?? 0) ?>
                        </td>

                        <!-- Payment Status -->
                        <td>
                            <span class="badge bg-<?= $pBadge ?> rounded-pill small">
                                <?= ucfirst($pStatus) ?>
                            </span>
                            <?php if (!empty($o['payment_method_name'])): ?>
                            <div class="text-muted" style="font-size:.63rem;">
                                <?= e($o['payment_method_name']) ?>
                            </div>
                            <?php endif; ?>
                        </td>

                        <!-- Order Status -->
                        <td>
                            <span class="badge bg-<?= $oBadge ?> rounded-pill">
                                <?php if ($oStatus === 'ready_for_pickup'): ?>
                                <i class="bi bi-bell-fill me-1"></i>
                                <?php endif; ?>
                                <?= e($oLabel) ?>
                            </span>
                            <?php if ($oStatus === 'completed' && !empty($o['completed_at'])): ?>
                            <div class="text-muted" style="font-size:.63rem;">
                                Picked up <?= $d($o['completed_at']) ?>
                            </div>
                            <?php elseif ($oStatus === 'paid' && !empty($o['paid_at'])): ?>
                            <div class="text-muted" style="font-size:.63rem;">
                                Paid <?= $dt($o['paid_at']) ?>
                            </div>
                            <?php endif; ?>
                        </td>

                        <!-- Date -->
                        <td class="small text-muted">
                            <?= $dt($o['created_at'] ?? null) ?>
                        </td>

                        <!-- Action -->
                        <td class="pe-3 text-end">
                            <a href="<?= url('gm/orders/' . (int)$o['id']) ?>"
                               class="btn btn-outline-secondary btn-sm py-0 px-2">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>

                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div><!-- /.card-body -->

    <!-- ── Pagination ──────────────────────────────────── -->
    <?php
    $total      = (int)($pagination['total']      ?? 0);
    $page       = (int)($pagination['page']       ?? 1);
    $totalPages = (int)($pagination['totalPages'] ?? 1);
    $from       = (int)($pagination['from']       ?? 0);
    $to         = (int)($pagination['to']         ?? 0);
    ?>
    <?php if ($total > 0): ?>
    <div class="card-footer bg-white border-top d-flex align-items-center
                justify-content-between py-2 px-3 flex-wrap gap-2">
        <small class="text-muted">
            Showing <?= $from ?>–<?= $to ?> of <?= number_format($total) ?> orders
        </small>
        <?php if ($totalPages > 1): ?>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php
                // Build query params preserving filters
                $qp = array_filter([
                    'status'         => $filters['status']         ?? '',
                    'payment_status' => $filters['payment_status'] ?? '',
                    'shop_id'        => $filters['shop_id'] > 0 ? $filters['shop_id'] : '',
                    'date_from'      => $filters['date_from']      ?? '',
                    'date_to'        => $filters['date_to']        ?? '',
                    'search'         => $filters['search']         ?? '',
                ]);
                $qStr = $qp ? '&' . http_build_query($qp) : '';

                $start = max(1, $page - 2);
                $end   = min($totalPages, $page + 2);
                ?>
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= url('gm/orders') ?>?page=<?= $page-1 ?><?= $qStr ?>">
                        &laquo;
                    </a>
                </li>
                <?php for ($p = $start; $p <= $end; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= url('gm/orders') ?>?page=<?= $p ?><?= $qStr ?>">
                        <?= $p ?>
                    </a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= url('gm/orders') ?>?page=<?= $page+1 ?><?= $qStr ?>">
                        &raquo;
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div><!-- /.card -->
