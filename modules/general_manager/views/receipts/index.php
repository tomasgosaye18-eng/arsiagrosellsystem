<?php
/**
 * GM — Unified Sales Receipts (read-only)
 * Route: GET /gm/receipts
 * Covers both POS/Salesperson sales and Customer E-Commerce orders.
 *
 * @var array  $kpis
 * @var array  $receipts
 * @var array  $pagination
 * @var array  $filters
 * @var array  $shops
 */
declare(strict_types=1);

use AAM\GeneralManager\Services\GMSalesReceiptsService as SVC;

$kpis       ??= [];
$receipts   ??= [];
$pagination ??= [];
$filters    ??= [];
$shops      ??= [];

$n   = fn($v, int $d = 0): string => is_numeric($v) ? number_format((float)$v, $d) : '—';
$etb = fn($v): string => 'ETB ' . number_format((float)$v, 2);
$dt  = fn($v): string => $v ? date('d/m/Y H:i', strtotime($v)) : '—';

$channelOptions = [
    ''             => 'All Channels',
    'pos'          => 'POS / Salesperson',
    'ecommerce'    => 'Customer E-Commerce',
    'seed'         => 'Seed Distribution',
];

$statusOptions = [
    ''                  => 'All Statuses',
    'completed'         => 'Completed',
    'voided'            => 'Voided (POS)',
    'returned'          => 'Returned (POS)',
    'paid'              => 'Paid (Order)',
    'preparing'         => 'Preparing (Order)',
    'ready_for_pickup'  => 'Ready for Pickup',
    'released'          => 'Released (Seed)',
    'pending'           => 'Pending (Seed)',
    'approved'          => 'Approved (Seed)',
    'rejected'          => 'Rejected (Seed)',
    'cancelled'         => 'Cancelled',
];

$pmOptions = [
    ''              => 'All Payment Methods',
    'cash'          => 'Cash',
    'mobile_money'  => 'Mobile Money',
    'mobile_banking'=> 'Mobile Banking (Order)',
];
?>

<!-- ── Page Header ─────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-4"
     style="background:linear-gradient(135deg,#1b3a2d,#2d6a4f);">
    <div class="card-body py-3 text-white d-flex align-items-center gap-3 flex-wrap">
        <div class="rounded-circle bg-white bg-opacity-20 d-flex align-items-center
                    justify-content-center flex-shrink-0"
             style="width:48px;height:48px;">
            <i class="bi bi-receipt fs-4"></i>
        </div>
        <div class="flex-grow-1">
            <h5 class="mb-0 fw-bold">Sales Receipts</h5>
            <small class="opacity-75">Unified view — POS &amp; Customer E-Commerce — General Manager (read-only)</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= url('gm/orders') ?>"
               class="btn btn-outline-light btn-sm">
                <i class="bi bi-bag-check-fill me-1"></i>Orders
            </a>
            <a href="<?= url('gm/shops/sales') ?>"
               class="btn btn-outline-light btn-sm">
                <i class="bi bi-bar-chart me-1"></i>Sales Overview
            </a>
        </div>
    </div>
</div>

<!-- ── KPI Strip ──────────────────────────────────────── -->
<div class="row g-3 mb-4">

    <!-- POS Today -->
    <div class="col-6 col-md-3 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid #198754!important;">
            <div class="card-body py-3">
                <div class="d-flex justify-content-center align-items-center gap-1 mb-1">
                    <span class="badge bg-success" style="font-size:.6rem;">POS</span>
                </div>
                <div class="fw-bold fs-5"><?= $n($kpis['pos_today'] ?? 0) ?></div>
                <div class="text-muted small">Transactions Today</div>
            </div>
        </div>
    </div>

    <!-- POS Revenue Today -->
    <div class="col-6 col-md-3 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid #198754!important;">
            <div class="card-body py-3">
                <div class="d-flex justify-content-center align-items-center gap-1 mb-1">
                    <span class="badge bg-success" style="font-size:.6rem;">POS</span>
                </div>
                <div class="fw-bold" style="font-size:.88rem;"><?= $etb($kpis['pos_revenue_today'] ?? 0) ?></div>
                <div class="text-muted small">Revenue Today</div>
            </div>
        </div>
    </div>

    <!-- E-Commerce Today -->
    <div class="col-6 col-md-3 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid var(--bs-info)!important;">
            <div class="card-body py-3">
                <div class="d-flex justify-content-center align-items-center gap-1 mb-1">
                    <span class="badge bg-info text-dark" style="font-size:.6rem;">ECO</span>
                </div>
                <div class="fw-bold fs-5"><?= $n($kpis['eco_today'] ?? 0) ?></div>
                <div class="text-muted small">Orders Today</div>
            </div>
        </div>
    </div>

    <!-- E-Commerce Revenue Today -->
    <div class="col-6 col-md-3 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid var(--bs-info)!important;">
            <div class="card-body py-3">
                <div class="d-flex justify-content-center align-items-center gap-1 mb-1">
                    <span class="badge bg-info text-dark" style="font-size:.6rem;">ECO</span>
                </div>
                <div class="fw-bold" style="font-size:.88rem;"><?= $etb($kpis['eco_revenue_today'] ?? 0) ?></div>
                <div class="text-muted small">Revenue Today</div>
            </div>
        </div>
    </div>

    <!-- Combined Month -->
    <div class="col-6 col-md-3 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid var(--bs-primary)!important;">
            <div class="card-body py-3">
                <i class="bi bi-calendar-month text-primary fs-3 d-block mb-1"></i>
                <div class="fw-bold fs-5"><?= $n(($kpis['pos_month'] ?? 0) + ($kpis['eco_month'] ?? 0)) ?></div>
                <div class="text-muted small">Total / Month</div>
            </div>
        </div>
    </div>

    <!-- Combined Revenue Month -->
    <div class="col-6 col-md-3 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid #20c997!important;">
            <div class="card-body py-3">
                <i class="bi bi-currency-exchange fs-3 d-block mb-1" style="color:#20c997;"></i>
                <div class="fw-bold" style="font-size:.88rem;">
                    <?= $etb(($kpis['pos_revenue_month'] ?? 0) + ($kpis['eco_revenue_month'] ?? 0) + ($kpis['seed_revenue_month'] ?? 0)) ?>
                </div>
                <div class="text-muted small">Revenue / Month</div>
            </div>
        </div>
    </div>

    <!-- Seed Distributions Today -->
    <div class="col-6 col-md-3 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid #6f42c1!important;">
            <div class="card-body py-3">
                <div class="d-flex justify-content-center align-items-center gap-1 mb-1">
                    <span class="badge" style="background:#6f42c1;font-size:.6rem;">SEED</span>
                </div>
                <div class="fw-bold fs-5"><?= $n($kpis['seed_today'] ?? 0) ?></div>
                <div class="text-muted small">Distributions Today</div>
            </div>
        </div>
    </div>

    <!-- Seed Revenue Today -->
    <div class="col-6 col-md-3 col-xl-2">
        <div class="card border-0 shadow-sm text-center h-100"
             style="border-top:3px solid #6f42c1!important;">
            <div class="card-body py-3">
                <div class="d-flex justify-content-center align-items-center gap-1 mb-1">
                    <span class="badge" style="background:#6f42c1;font-size:.6rem;">SEED</span>
                </div>
                <div class="fw-bold" style="font-size:.88rem;"><?= $etb($kpis['seed_revenue_today'] ?? 0) ?></div>
                <div class="text-muted small">Revenue Today</div>
            </div>
        </div>
    </div>

</div>

<!-- ── Channel Quick Filters ──────────────────────────── -->
<div class="d-flex gap-2 flex-wrap align-items-center mb-3">
    <span class="text-muted small fw-semibold me-1">Channel:</span>
    <?php
    $curCh = $filters['channel'] ?? '';
    $chButtons = [
        [''          , 'All',               'secondary'],
        ['pos'       , 'POS / Salesperson', 'success'],
        ['ecommerce' , 'E-Commerce',        'info'],
        ['seed'      , 'Seed Distribution', 'purple'],
    ];
    foreach ($chButtons as [$val, $lbl, $col]):
    $btnClass = $col === 'purple'
        ? ($curCh === $val ? 'btn text-white' : 'btn btn-outline-secondary')
        : ($curCh === $val ? 'btn btn-'.$col : 'btn btn-outline-'.$col);
    $extraStyle = $col === 'purple'
        ? ($curCh === $val ? 'background:#6f42c1;border-color:#6f42c1;' : 'color:#6f42c1;border-color:#6f42c1;')
        : '';
    ?>
    <a href="<?= url('gm/receipts') ?>?channel=<?= urlencode($val) ?>"
       class="btn btn-sm <?= $btnClass ?>"
       style="<?= $extraStyle ?>">
        <?= e($lbl) ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- ── Filters ────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2 px-3">
        <form method="GET" action="<?= url('gm/receipts') ?>"
              class="row g-2 align-items-end">

            <input type="hidden" name="channel" value="<?= e($filters['channel'] ?? '') ?>">

            <div class="col-12 col-md-3">
                <input type="search" name="search"
                       value="<?= e($filters['search'] ?? '') ?>"
                       class="form-control form-control-sm"
                       placeholder="Ref, receipt, seller, customer…">
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
                <select name="payment_method" class="form-select form-select-sm">
                    <?php foreach ($pmOptions as $v => $l): ?>
                    <option value="<?= e($v) ?>" <?= ($filters['payment_method'] ?? '') === $v ? 'selected' : '' ?>>
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
                       class="form-control form-control-sm" title="Date from">
            </div>

            <div class="col-6 col-md-1">
                <input type="date" name="date_to"
                       value="<?= e($filters['date_to'] ?? '') ?>"
                       class="form-control form-control-sm" title="Date to">
            </div>

            <div class="col-12 col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="bi bi-search"></i>
                </button>
                <a href="<?= url('gm/receipts') ?>"
                   class="btn btn-outline-secondary btn-sm" title="Clear filters">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>

        </form>
    </div>
</div>

<!-- ── Receipts Table ─────────────────────────────────── -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex align-items-center
                justify-content-between py-2">
        <div class="fw-semibold small">
            <i class="bi bi-receipt me-2 text-success"></i>
            Receipts
            <?php if (($pagination['total'] ?? 0) > 0): ?>
            <span class="badge bg-secondary ms-1"><?= number_format($pagination['total']) ?></span>
            <?php endif; ?>
        </div>
        <small class="text-muted">
            GM — read only. Click any row to view full receipt.
        </small>
    </div>

    <div class="card-body p-0">
        <?php if (empty($receipts)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-receipt d-block fs-2 mb-2 opacity-25"></i>
            <p class="mb-1 fw-semibold">No receipts found</p>
            <small>
                <?php
                $hasFilters = !empty($filters['search'])
                    || !empty($filters['status'])
                    || !empty($filters['payment_method'])
                    || !empty($filters['shop_id'])
                    || !empty($filters['date_from'])
                    || !empty($filters['date_to']);
                ?>
                <?php if ($hasFilters): ?>
                    Try adjusting your filters.
                    <a href="<?= url('gm/receipts') ?>" class="text-decoration-none ms-1">Clear all</a>
                <?php else: ?>
                    Sales and customer orders will appear here once transactions are processed.
                <?php endif; ?>
            </small>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="min-width:40px;">Ch</th>
                        <th style="min-width:150px;">Reference</th>
                        <th style="min-width:140px;">Seller / Customer / Farmer</th>
                        <th style="min-width:110px;">Shop / Warehouse</th>
                        <th class="text-center" style="min-width:50px;">Items</th>
                        <th class="text-end" style="min-width:110px;">Amount</th>
                        <th style="min-width:110px;">Payment</th>
                        <th style="min-width:120px;">Status</th>
                        <th style="min-width:130px;">Date / Time</th>
                        <th class="pe-3 text-end" style="min-width:80px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($receipts as $r):
                        $ch     = $r['channel'] ?? 'pos';
                        $status = $r['status']  ?? '';
                        $isPOS  = $ch === 'pos';
                        $isSeed = $ch === 'seed';
                        $badge  = $isPOS  ? SVC::posStatusBadge($status)
                                : ($isSeed ? SVC::seedStatusBadge($status)
                                           : SVC::ecoStatusBadge($status));
                        $label  = $isPOS  ? SVC::posStatusLabel($status)
                                : ($isSeed ? SVC::seedStatusLabel($status)
                                           : SVC::ecoStatusLabel($status));
                        $detailUrl = $isPOS
                            ? url('gm/receipts/pos/'   . (int)$r['id'])
                            : ($isSeed
                                ? url('gm/seed-distribution/' . (int)$r['id'])
                                : url('gm/receipts/order/' . (int)$r['id']));
                        $rowClass = ($status === 'voided' || $status === 'cancelled' || $status === 'rejected') ? 'text-muted' : '';
                    ?>
                    <tr class="<?= $rowClass ?>">

                        <!-- Channel badge -->
                        <td class="ps-3">
                            <?php if ($isPOS): ?>
                            <span class="badge bg-success" style="font-size:.58rem;letter-spacing:.03em;">POS</span>
                            <?php elseif ($isSeed): ?>
                            <span class="badge" style="background:#6f42c1;font-size:.58rem;letter-spacing:.03em;">SEED</span>
                            <?php else: ?>
                            <span class="badge bg-info text-dark" style="font-size:.58rem;letter-spacing:.03em;">ECO</span>
                            <?php endif; ?>
                        </td>

                        <!-- Reference -->
                        <td>
                            <a href="<?= $detailUrl ?>"
                               class="fw-semibold text-decoration-none font-monospace small">
                                <?= e($r['receipt_ref'] ?? $r['order_ref'] ?? '#'.$r['id']) ?>
                            </a>
                            <?php if ($isPOS && !empty($r['order_ref']) && $r['order_ref'] !== $r['receipt_ref']): ?>
                            <div class="text-muted font-monospace" style="font-size:.62rem;">
                                <?= e($r['order_ref']) ?>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($r['payment_reference'])): ?>
                            <div class="text-muted" style="font-size:.6rem;"><?= e($r['payment_reference']) ?></div>
                            <?php endif; ?>
                        </td>

                        <!-- Seller / Customer -->
                        <td>
                            <div class="small fw-semibold">
                                <?= e($r['seller_customer_name'] ?? '—') ?>
                            </div>
                            <?php if (!empty($r['seller_customer_username'])): ?>
                            <div class="text-muted" style="font-size:.65rem;">@<?= e($r['seller_customer_username']) ?></div>
                            <?php elseif (!$isPOS && !$isSeed && !empty($r['customer_phone'])): ?>
                            <div class="text-muted" style="font-size:.65rem;">
                                <i class="bi bi-telephone me-1"></i><?= e($r['customer_phone']) ?>
                            </div>
                            <?php elseif ($isSeed && !empty($r['customer_phone'])): ?>
                            <div class="text-muted" style="font-size:.65rem;">
                                <i class="bi bi-telephone me-1"></i><?= e($r['customer_phone']) ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($isSeed): ?>
                            <div class="text-muted" style="font-size:.62rem;color:#6f42c1!important;">
                                <i class="bi bi-flower1 me-1"></i>Farmer
                            </div>
                            <?php endif; ?>
                        </td>

                        <!-- Shop / Warehouse -->
                        <td class="small text-muted">
                            <?php if ($isSeed): ?>
                            <i class="bi bi-building me-1" style="color:#6f42c1;"></i><?= e($r['shop_name'] ?? '—') ?>
                            <?php else: ?>
                            <?= e($r['shop_name'] ?? '—') ?>
                            <?php endif; ?>
                        </td>

                        <!-- Items -->
                        <td class="text-center small"><?= (int)($r['item_count'] ?? 0) ?></td>

                        <!-- Amount -->
                        <td class="text-end small fw-semibold">
                            <?= $etb($r['total_amount'] ?? 0) ?>
                            <?php if ((float)($r['discount_amount'] ?? 0) > 0): ?>
                            <div class="text-danger" style="font-size:.62rem;">
                                -<?= $etb($r['discount_amount']) ?>
                            </div>
                            <?php endif; ?>
                        </td>

                        <!-- Payment -->
                        <td class="small">
                            <?php if ($isSeed): ?>
                            <span class="text-muted"><i class="bi bi-flower1 me-1"></i>Seed Dist.</span>
                            <?php else: ?>
                            <?= e(SVC::paymentMethodLabel($r['payment_method'] ?? '', $r['payment_provider'] ?? '')) ?>
                            <?php if ($isPOS && !empty($r['cash_received']) && (float)$r['cash_received'] > 0): ?>
                            <div class="text-muted" style="font-size:.62rem;">
                                Recv: <?= $etb($r['cash_received']) ?>
                            </div>
                            <?php endif; ?>
                            <?php endif; ?>
                        </td>

                        <!-- Status -->
                        <td>
                            <span class="badge bg-<?= $badge ?> rounded-pill small">
                                <?= e($label) ?>
                            </span>
                        </td>

                        <!-- Date/Time -->
                        <td class="small text-muted"><?= $dt($r['transaction_at'] ?? null) ?></td>

                        <!-- Action -->
                        <td class="pe-3 text-end">
                            <a href="<?= $detailUrl ?>"
                               class="btn btn-outline-secondary btn-sm py-0 px-2"
                               title="View receipt">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if (!$isSeed): ?>
                            <a href="<?= $detailUrl ?>?print=1"
                               class="btn btn-outline-secondary btn-sm py-0 px-2 ms-1"
                               title="Print receipt" target="_blank">
                                <i class="bi bi-printer"></i>
                            </a>
                            <?php endif; ?>
                        </td>

                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div><!-- /.card-body -->

    <!-- ── Pagination ─────────────────────────────────── -->
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
            Showing <?= $from ?>–<?= $to ?> of <?= number_format($total) ?> records
        </small>
        <?php if ($totalPages > 1): ?>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php
                $qp = array_filter([
                    'channel'        => $filters['channel']        ?? '',
                    'status'         => $filters['status']         ?? '',
                    'payment_method' => $filters['payment_method'] ?? '',
                    'shop_id'        => ($filters['shop_id'] ?? 0) > 0 ? $filters['shop_id'] : '',
                    'date_from'      => $filters['date_from']      ?? '',
                    'date_to'        => $filters['date_to']        ?? '',
                    'search'         => $filters['search']         ?? '',
                ]);
                $qStr = $qp ? '&' . http_build_query($qp) : '';
                $start = max(1, $page - 2);
                $end   = min($totalPages, $page + 2);
                ?>
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= url('gm/receipts') ?>?page=<?= $page-1 ?><?= $qStr ?>">&laquo;</a>
                </li>
                <?php for ($p = $start; $p <= $end; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= url('gm/receipts') ?>?page=<?= $p ?><?= $qStr ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= url('gm/receipts') ?>?page=<?= $page+1 ?><?= $qStr ?>">&raquo;</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div><!-- /.card -->
