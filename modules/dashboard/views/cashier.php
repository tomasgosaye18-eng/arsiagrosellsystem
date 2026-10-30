<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 12 (updated) + Phase 6
 * =========================================================
 * File     : modules/dashboard/views/cashier.php
 * Purpose  : Seller / Salesperson shop dashboard.
 *            Shows assigned shop KPIs, shift status,
 *            low-stock alerts, recent sales, quick actions,
 *            and customer order status widget.
 * =========================================================
 *
 * @var \AAM\Core\View    $view
 * @var string            $userName
 * @var string            $userRole
 * @var array             $shopKpis
 * @var array             $recentSales
 * @var array|null        $shopInfo
 * @var array|null        $activeShift
 * @var array             $customerOrderKpis
 */
declare(strict_types=1);

$customerOrderKpis ??= [];

$shopKpis    ??= [];
$recentSales ??= [];
$shopInfo    ??= null;
$activeShift ??= null;

$fmt     = fn(mixed $v): string => is_numeric($v) ? number_format((float)$v) : ($v ?? '—');
$fmtCurr = fn(float $v): string => 'ETB ' . number_format($v, 2);

$shopName    = $shopInfo['name'] ?? 'Your Shop';
$shiftOpen   = !empty($activeShift) && ($activeShift['status'] ?? '') === 'open';
?>

<!-- ── Welcome Banner ─────────────────────────────────────── -->
<div class="row g-3 mb-3">
  <div class="col-12">
    <div class="card border-0 shadow-sm"
         style="background:linear-gradient(135deg,#6f42c1,#0dcaf0);color:#fff;">
      <div class="card-body d-flex align-items-center gap-3 py-3 flex-wrap">
        <div class="rounded-circle bg-white bg-opacity-20 d-flex align-items-center
                    justify-content-center flex-shrink-0"
             style="width:48px;height:48px;">
          <i class="bi bi-shop fs-5"></i>
        </div>
        <div class="flex-grow-1">
          <h5 class="mb-0 fw-bold"><?= e($shopName) ?></h5>
          <small class="opacity-75">
            Seller: <?= e($userName) ?> &mdash; <?= date('l, d F Y') ?>
          </small>
        </div>
        <!-- Shift indicator in banner -->
        <?php if ($shiftOpen): ?>
        <span class="badge bg-success bg-opacity-75 px-3 py-2"
              style="font-size:.8rem;border-radius:20px;">
          <i class="bi bi-circle-fill me-1" style="font-size:.5rem;"></i>
          Shift Open · <?= date('H:i', strtotime($activeShift['opened_at'])) ?>
        </span>
        <?php else: ?>
        <a href="<?= url('pos/shift') ?>"
           class="badge bg-warning text-dark bg-opacity-75 px-3 py-2 text-decoration-none"
           style="font-size:.8rem;border-radius:20px;">
          <i class="bi bi-pause-circle me-1"></i>No Shift — Open One
        </a>
        <?php endif; ?>
        <!-- Primary Action -->
        <a href="<?= url('pos') ?>" class="btn btn-light fw-bold px-4">
          <i class="bi bi-cart3 me-2"></i>OPEN POS
        </a>
      </div>
    </div>
  </div>
</div>

<!-- ── Shift Status Card ──────────────────────────────────── -->
<div class="row g-3 mb-3">
  <div class="col-12">
    <?php if ($shiftOpen): ?>
    <div class="card border-success border-2 shadow-sm">
      <div class="card-body py-2 px-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div class="d-flex align-items-center gap-3">
            <div>
              <span class="badge bg-success">SHIFT OPEN</span>
              <span class="ms-2 small fw-semibold"><?= e($activeShift['shift_number']) ?></span>
            </div>
            <div class="text-muted small">
              <i class="bi bi-clock me-1"></i>
              Opened: <?= date('d/m H:i', strtotime($activeShift['opened_at'])) ?>
            </div>
            <div class="text-muted small">
              <i class="bi bi-receipt me-1"></i>
              <?= (int)($activeShift['total_transactions'] ?? 0) ?> transactions
            </div>
            <div class="text-success small fw-semibold">
              <i class="bi bi-cash me-1"></i>
              ETB <?= number_format((float)($activeShift['total_sales_amount'] ?? 0), 2) ?>
            </div>
          </div>
          <div class="d-flex gap-2">
            <a href="<?= url('pos/shift') ?>"
               class="btn btn-outline-success btn-sm">
              <i class="bi bi-stop-circle me-1"></i>Close Shift
            </a>
            <a href="<?= url('pos/shift/' . (int)$activeShift['id']) ?>"
               class="btn btn-outline-secondary btn-sm">
              <i class="bi bi-eye me-1"></i>Detail
            </a>
          </div>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div class="card border-warning border-2 shadow-sm">
      <div class="card-body py-2 px-3">
        <div class="d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-warning text-dark">NO ACTIVE SHIFT</span>
            <small class="text-muted">
              Open a shift before processing sales.
            </small>
          </div>
          <a href="<?= url('pos/shift') ?>"
             class="btn btn-warning btn-sm fw-semibold">
            <i class="bi bi-play-circle me-1"></i>Open Shift
          </a>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     LEVEL 1 — SHOP KPIs
     ══════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-3">

  <!-- Sales Today -->
  <div class="col-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #198754!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-cart-check-fill text-success"></i>
          <span class="text-muted small">Sales Today</span>
        </div>
        <div class="fw-bold fs-3"><?= $fmt($shopKpis['sales_today'] ?? 0) ?></div>
        <div class="text-success small mt-1"><?= $fmtCurr((float)($shopKpis['revenue_today'] ?? 0)) ?></div>
        <a href="<?= url('pos/history') ?>" class="text-muted d-block" style="font-size:.7rem;">View history →</a>
      </div>
    </div>
  </div>

  <!-- Current Stock -->
  <div class="col-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #0d6efd!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-boxes text-primary"></i>
          <span class="text-muted small">Shop Stock</span>
        </div>
        <div class="fw-bold fs-3"><?= $fmt($shopKpis['total_stock'] ?? 0) ?></div>
        <div class="text-muted small mt-1">Units available</div>
        <div class="text-muted" style="font-size:.7rem;"><?= $fmt($shopKpis['low_stock'] ?? 0) ?> low stock items</div>
      </div>
    </div>
  </div>

  <!-- Low Stock -->
  <div class="col-6 col-xl-3">
    <?php $hasLow = ($shopKpis['low_stock'] ?? 0) > 0; ?>
    <div class="card border-0 shadow-sm h-100"
         style="border-left:4px solid <?= $hasLow ? '#fd7e14' : '#dee2e6' ?>!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-exclamation-circle" style="color:#fd7e14;"></i>
          <span class="text-muted small">Low Stock</span>
        </div>
        <div class="fw-bold fs-3 <?= $hasLow ? '' : 'text-muted' ?>"
             <?= $hasLow ? 'style="color:#fd7e14;"' : '' ?>>
          <?= $fmt($shopKpis['low_stock'] ?? 0) ?>
        </div>
        <div class="text-muted small mt-1">Products below minimum</div>
      </div>
    </div>
  </div>

  <!-- Incoming Transfers — clickable -->
  <div class="col-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #0dcaf0!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-arrow-down-circle-fill text-info"></i>
          <span class="text-muted small">Incoming Transfers</span>
        </div>
        <div class="fw-bold fs-3"><?= $fmt($shopKpis['pending_transfers'] ?? 0) ?></div>
        <div class="text-muted small mt-1">Stock en route from warehouse</div>
        <?php if (($shopKpis['pending_transfers'] ?? 0) > 0): ?>
        <a href="<?= url('transfers/incoming') ?>"
           class="text-info d-block mt-1" style="font-size:.7rem;">
          View incoming →
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════════
     LEVEL 2 — ALERTS
     ══════════════════════════════════════════════════════════ -->
<?php
$coActive    = (int)($customerOrderKpis['active_count']    ?? 0);
$coNew       = (int)($customerOrderKpis['new_count']       ?? 0);
$coPreparing = (int)($customerOrderKpis['preparing_count'] ?? 0);
$coReady     = (int)($customerOrderKpis['ready_count']     ?? 0);
?>

<?php if ($coNew > 0 || $coReady > 0): ?>
<div class="row g-2 mb-2">
  <div class="col-12">
    <?php if ($coNew > 0): ?>
    <div class="alert alert-info d-flex align-items-center justify-content-between py-2 mb-2" role="alert">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-bag-check-fill flex-shrink-0"></i>
        <span class="small">
          <strong><?= $coNew ?></strong> new paid customer order<?= $coNew > 1 ? 's' : '' ?> waiting for preparation.
        </span>
      </div>
      <a href="<?= url('customer/shop-orders?status=paid') ?>"
         class="btn btn-info btn-sm text-white text-nowrap">
        <i class="bi bi-eye me-1"></i>View
      </a>
    </div>
    <?php endif; ?>
    <?php if ($coReady > 0): ?>
    <div class="alert alert-success d-flex align-items-center justify-content-between py-2 mb-2" role="alert">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill flex-shrink-0"></i>
        <span class="small">
          <strong><?= $coReady ?></strong> order<?= $coReady > 1 ? 's' : '' ?> ready for customer pickup.
        </span>
      </div>
      <a href="<?= url('customer/shop-orders?status=ready_for_pickup') ?>"
         class="btn btn-success btn-sm text-nowrap">
        <i class="bi bi-eye me-1"></i>View
      </a>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if (($shopKpis['low_stock'] ?? 0) > 0): ?>
<div class="row g-2 mb-4">
  <div class="col-12">
    <div class="alert alert-warning d-flex align-items-center justify-content-between py-2 mb-0" role="alert">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
        <span class="small"><strong><?= $shopKpis['low_stock'] ?></strong> product(s) in this shop are running low on stock.</span>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════
     LEVEL 3 — RECENT SALES + QUICK ACTIONS
     ══════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">

  <!-- Recent Sales -->
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold small"><i class="bi bi-receipt me-2 text-success"></i>Recent Sales</span>
        <a href="<?= url('pos/history') ?>" class="btn btn-outline-success btn-sm">View All</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($recentSales)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-cart-x display-6 d-block mb-2 opacity-25"></i>
            <small>No sales recorded today yet.</small>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th class="px-3 small fw-semibold">Receipt #</th>
                  <th class="small fw-semibold">Items</th>
                  <th class="small fw-semibold">Total</th>
                  <th class="small fw-semibold">Time</th>
                  <th class="small fw-semibold">Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentSales as $sale):
                  $statusColors = ['completed' => 'success', 'voided' => 'danger', 'pending' => 'warning'];
                  $sColor = $statusColors[$sale['status'] ?? 'completed'] ?? 'secondary';
                ?>
                <tr>
                  <td class="px-3 small fw-semibold"><?= e($sale['receipt_number'] ?? '#' . ($sale['id'] ?? '—')) ?></td>
                  <td class="small text-muted"><?= $fmt($sale['items_count'] ?? 0) ?> item(s)</td>
                  <td class="small fw-semibold"><?= $fmtCurr((float)($sale['total_amount'] ?? 0)) ?></td>
                  <td class="small text-muted"><?= isset($sale['created_at']) ? date('H:i', strtotime($sale['created_at'])) : '—' ?></td>
                  <td><span class="badge bg-<?= $sColor ?>-subtle text-<?= $sColor ?> small"><?= ucfirst($sale['status'] ?? 'completed') ?></span></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Quick Actions -->
  <div class="col-lg-4 d-flex flex-column gap-3">

    <!-- Primary POS Action -->
    <div class="card border-0 shadow-sm">
      <div class="card-body d-flex flex-column gap-2">
        <a href="<?= url('pos') ?>" class="btn btn-success btn-lg w-100 fw-bold py-3">
          <i class="bi bi-cart3 fs-4 me-2"></i>OPEN POS TERMINAL
        </a>
        <a href="<?= url('pos/history') ?>" class="btn btn-outline-secondary w-100">
          <i class="bi bi-clock-history me-2"></i>Sales History
        </a>
      </div>
    </div>

    <!-- Shop Stock Summary -->
    <div class="card border-0 shadow-sm">
      <div class="card-header fw-semibold small">
        <i class="bi bi-boxes me-2 text-primary"></i>Stock Status
      </div>
      <div class="card-body p-0">
        <?php
        $shopIdForLinks = $shopInfo['id'] ?? 0;
        $stockRows = [
          ['Available',     $fmt($shopKpis['total_stock'] ?? 0)  . ' units',   'success'],
          ['Low Stock',     $fmt($shopKpis['low_stock'] ?? 0)    . ' items',   ($shopKpis['low_stock'] ?? 0) > 0 ? 'warning' : 'muted'],
          ['Out of Stock',  $fmt($shopKpis['out_of_stock'] ?? 0) . ' items',   ($shopKpis['out_of_stock'] ?? 0) > 0 ? 'danger'  : 'muted'],
          ['Incoming',      $fmt($shopKpis['pending_transfers'] ?? 0) . ' transfers', 'info'],
        ];
        foreach ($stockRows as $i => [$label, $val, $color]):
        ?>
        <div class="d-flex justify-content-between align-items-center px-3 py-2 <?= $i < count($stockRows)-1 ? 'border-bottom' : '' ?>">
          <span class="small text-muted"><?= $label ?></span>
          <span class="small fw-semibold text-<?= $color ?>"><?= $val ?></span>
        </div>
        <?php endforeach; ?>
        <!-- Quick navigation links -->
        <div class="px-3 py-2 border-top d-flex flex-column gap-1">
          <?php if ($shopIdForLinks > 0): ?>
          <a href="<?= url('transfers/incoming') ?>"
             class="btn btn-outline-info btn-sm w-100">
            <i class="bi bi-box-arrow-in-down me-1"></i>
            Incoming Transfers
            <?php if (($shopKpis['pending_transfers'] ?? 0) > 0): ?>
            <span class="badge bg-info text-white ms-1"><?= $shopKpis['pending_transfers'] ?></span>
            <?php endif; ?>
          </a>
          <a href="<?= url('shops/' . $shopIdForLinks . '/inventory') ?>"
             class="btn btn-outline-primary btn-sm w-100">
            <i class="bi bi-boxes me-1"></i>Shop Inventory
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Customer Orders Widget — Phase 6 -->
    <?php if ($coActive > 0 || true): ?>
    <div class="card border-0 shadow-sm <?= $coNew > 0 || $coReady > 0 ? 'border-2 border-success' : '' ?>">
      <div class="card-header fw-semibold small d-flex justify-content-between align-items-center">
        <span><i class="bi bi-bag-check me-2 text-success"></i>Customer Orders</span>
        <?php if ($coActive > 0): ?>
        <span class="badge bg-success"><?= $coActive ?> active</span>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <?php
        $coRows = [
          ['New / Paid',        $coNew,       'info',    'paid'],
          ['Preparing',         $coPreparing, 'primary', 'preparing'],
          ['Ready for Pickup',  $coReady,     'success', 'ready_for_pickup'],
        ];
        foreach ($coRows as $i => [$coLabel, $coVal, $coColor, $coStatus]):
        ?>
        <div class="d-flex justify-content-between align-items-center px-3 py-2
             <?= $i < count($coRows)-1 ? 'border-bottom' : '' ?>
             <?= $coVal > 0 ? "bg-{$coColor}-subtle" : '' ?>">
          <span class="small <?= $coVal > 0 ? "text-{$coColor} fw-semibold" : 'text-muted' ?>">
            <?= $coLabel ?>
          </span>
          <span class="badge bg-<?= $coVal > 0 ? $coColor : 'secondary' ?> bg-opacity-75">
            <?= $coVal ?>
          </span>
        </div>
        <?php endforeach; ?>
        <div class="px-3 py-2 border-top">
          <a href="<?= url('customer/shop-orders') ?>"
             class="btn btn-outline-success btn-sm w-100">
            <i class="bi bi-bag-check me-1"></i>
            Manage Customer Orders
            <?php if ($coActive > 0): ?>
            <span class="badge bg-success ms-1"><?= $coActive ?></span>
            <?php endif; ?>
          </a>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div>

</div>


