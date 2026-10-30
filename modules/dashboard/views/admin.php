<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 9
 * =========================================================
 * File     : modules/dashboard/views/admin.php
 * Purpose  : System Administrator dashboard.
 *            Business overview + system management.
 *            Phase 9: business-focused, no clutter.
 * =========================================================
 *
 * @var \AAM\Core\View      $view
 * @var string              $userName
 * @var array<string,mixed> $user
 * @var array<string,mixed> $kpis
 * @var array               $recentActivities
 * @var array<string,mixed> $systemStatus
 */
declare(strict_types=1);

$kpis             ??= [];
$recentActivities ??= [];
$systemStatus     ??= [];

$fmt = fn(mixed $v): string => is_numeric($v) ? number_format((float)$v) : ($v ?? '—');
$fmtCurr = fn(float $v): string => 'ETB ' . number_format($v, 2);
?>

<!-- ── Welcome Banner ─────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-12">
    <div class="card border-0 bg-primary text-white shadow-sm">
      <div class="card-body d-flex align-items-center gap-3 py-3">
        <div class="rounded-circle bg-white bg-opacity-20 d-flex align-items-center justify-content-center flex-shrink-0"
             style="width:48px;height:48px;">
          <i class="bi bi-shield-fill-check fs-5"></i>
        </div>
        <div class="flex-grow-1">
          <h5 class="mb-0 fw-bold">Welcome back, <?= e($userName) ?></h5>
          <small class="opacity-75">System Administrator &mdash; <?= date('l, d F Y') ?></small>
        </div>
        <div class="d-none d-md-flex gap-2 flex-wrap">
          <a href="<?= url('admin/users/create') ?>" class="btn btn-outline-light btn-sm">
            <i class="bi bi-person-plus me-1"></i>Add User
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     LEVEL 1 — CRITICAL BUSINESS KPIs
     ══════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-2">
  <div class="col-12">
    <p class="text-muted small fw-semibold text-uppercase mb-2" style="letter-spacing:.06em;">
      <i class="bi bi-activity me-1 text-primary"></i>Business Overview
    </p>
  </div>
</div>

<div class="row g-3 mb-4">

  <!-- Sales Today -->
  <div class="col-6 col-sm-4 col-xl-2">
    <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #198754!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-cart-check-fill text-success"></i>
          <span class="text-muted small">Sales Today</span>
        </div>
        <div class="fw-bold fs-4"><?= $fmt($kpis['sales_today'] ?? 0) ?></div>
        <div class="text-muted small">
          <?= $fmtCurr($kpis['revenue_today'] ?? 0) ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Sales This Month -->
  <div class="col-6 col-sm-4 col-xl-2">
    <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #0d6efd!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-calendar-check-fill text-primary"></i>
          <span class="text-muted small">This Month</span>
        </div>
        <div class="fw-bold fs-4"><?= $fmt($kpis['sales_this_month'] ?? 0) ?></div>
        <div class="text-muted small">Transactions</div>
      </div>
    </div>
  </div>

  <!-- Total Stock -->
  <div class="col-6 col-sm-4 col-xl-2">
    <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #0dcaf0!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-boxes text-info"></i>
          <span class="text-muted small">Warehouse Stock</span>
        </div>
        <div class="fw-bold fs-4"><?= $fmt($kpis['total_warehouse_stock'] ?? 0) ?></div>
        <div class="text-muted small">Units in warehouse</div>
      </div>
    </div>
  </div>

  <!-- Products -->
  <div class="col-6 col-sm-4 col-xl-2">
    <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #fd7e14!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-box-seam" style="color:#fd7e14;"></i>
          <span class="text-muted small">Products</span>
        </div>
        <div class="fw-bold fs-4"><?= $fmt($kpis['total_products'] ?? 0) ?></div>
        <div class="text-muted small">Active products</div>
      </div>
    </div>
  </div>

  <!-- Active Shops -->
  <div class="col-6 col-sm-4 col-xl-2">
    <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #6f42c1!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-shop text-purple" style="color:#6f42c1;"></i>
          <span class="text-muted small">Retail Shops</span>
        </div>
        <div class="fw-bold fs-4"><?= $fmt($kpis['active_shops'] ?? 0) ?></div>
        <div class="text-muted small">Active shops</div>
      </div>
    </div>
  </div>

  <!-- System Users -->
  <div class="col-6 col-sm-4 col-xl-2">
    <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #6c757d!important;">
      <div class="card-body py-3 px-3">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-people-fill text-secondary"></i>
          <span class="text-muted small">System Users</span>
        </div>
        <div class="fw-bold fs-4"><?= $fmt($kpis['total_users'] ?? 0) ?></div>
        <div class="text-muted small"><?= $fmt($kpis['active_users'] ?? 0) ?> active</div>
      </div>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════════
     LEVEL 2 — ALERTS & PENDING ACTIONS
     ══════════════════════════════════════════════════════════ -->
<?php
$alerts = [];
if (($kpis['locked_users'] ?? 0) > 0)
    $alerts[] = ['danger', 'bi-lock-fill', ($kpis['locked_users']).' locked account(s) need attention.', url('admin/users?status=locked'), 'Unlock'];
if (($kpis['low_stock_items'] ?? 0) > 0)
    $alerts[] = ['warning', 'bi-exclamation-triangle-fill', ($kpis['low_stock_items']).' product(s) below minimum stock level.', url('inventory'), 'View Inventory'];
if (($kpis['open_discrepancies'] ?? 0) > 0)
    $alerts[] = ['warning', 'bi-clipboard-x-fill', ($kpis['open_discrepancies']).' open discrepancy report(s).', url('warehouse/discrepancies'), 'View'];
if (($kpis['pending_transfers'] ?? 0) > 0)
    $alerts[] = ['info', 'bi-arrow-left-right', ($kpis['pending_transfers']).' transfer(s) pending.', url('transfers'), 'View Transfers'];
if (($kpis['pending_dispatches'] ?? 0) > 0)
    $alerts[] = ['info', 'bi-truck', ($kpis['pending_dispatches']).' harvest(s) awaiting dispatch.', url('farms'), 'View Farms'];
?>
<?php if (!empty($alerts)): ?>
<div class="row g-2 mb-4">
  <?php foreach ($alerts as [$color, $icon, $msg, $href, $btnLabel]): ?>
  <div class="col-12">
    <div class="alert alert-<?= $color ?> d-flex align-items-center justify-content-between py-2 mb-0" role="alert">
      <div class="d-flex align-items-center gap-2">
        <i class="bi <?= $icon ?> flex-shrink-0"></i>
        <span class="small"><?= e($msg) ?></span>
      </div>
      <a href="<?= $href ?>" class="btn btn-<?= $color ?> btn-sm py-0 px-2 flex-shrink-0 ms-2"><?= $btnLabel ?></a>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>



<!-- ══════════════════════════════════════════════════════════
     LEVEL 4 — OPERATIONAL SUMMARY (module links)
     ══════════════════════════════════════════════════════════ -->
<div class="row g-3">

  <!-- Operations quick-view -->
  <?php
  $modules = [
    ['Farms',       $kpis['total_farms'] ?? 0,          'bi-flower1',        'success', url('farms'),                'Active farms'],
    ['Warehouses',  $kpis['total_warehouses'] ?? 0,     'bi-building',       'primary', url('warehouse'),            'Active warehouses'],
    ['Products',    $kpis['total_products'] ?? 0,       'bi-box-seam',       'warning', url('admin/products'),       'Active products'],
    ['Shops',       $kpis['active_shops'] ?? 0,         'bi-shop',           'info',    url('admin/organization/shops'), 'Active shops'],
    ['Transfers',   $kpis['pending_transfers'] ?? 0,    'bi-arrow-left-right','secondary',url('transfers'),          'Pending transfers'],
    ['Discrepancies',$kpis['open_discrepancies'] ?? 0,  'bi-clipboard-x',    'danger',  url('warehouse/discrepancies'),'Open discrepancies'],
  ];
  foreach ($modules as [$label, $value, $icon, $color, $href, $sub]): ?>
  <div class="col-6 col-md-4 col-xl-2">
    <a href="<?= $href ?>" class="card border-0 shadow-sm text-decoration-none h-100 card-hover">
      <div class="card-body text-center py-3">
        <i class="bi <?= $icon ?> text-<?= $color ?> fs-3 d-block mb-1"></i>
        <div class="fw-bold fs-5"><?= $fmt($value) ?></div>
        <div class="small fw-semibold text-dark"><?= $label ?></div>
        <div class="text-muted" style="font-size:.72rem;"><?= $sub ?></div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>

</div>
