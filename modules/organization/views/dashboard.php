<?php
/**
 * =========================================================
 * ORGANIZATION DASHBOARD VIEW
 * =========================================================
 * File    : modules/organization/views/dashboard.php
 * Purpose : Executive organization overview showing KPIs
 *           for farms, warehouses, shops, and assignments.
 * Deps    : OrganizationController, FarmModel,
 *           WarehouseModel, ShopModel, AssignmentModel
 * How     : Rendered by OrganizationController::dashboard()
 * =========================================================
 */
declare(strict_types=1);
$pageTitle = 'Organization Dashboard';
?>
<?php $view->section('styles'); ?>
<link rel="stylesheet" href="<?= $view->asset('css/organization.css') ?>">
<?php $view->endSection(); ?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-diagram-3-fill me-2 text-primary"></i>Organization Management</h4>
    <p class="text-muted small mb-0">Arsi University — Farms, Warehouses, Shops &amp; Locations</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= url('admin/organization/farms/create') ?>" class="btn btn-success btn-sm">
      <i class="bi bi-plus-lg me-1"></i>Add Farm
    </a>
    <a href="<?= url('admin/organization/warehouses/create') ?>" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i>Add Warehouse
    </a>
    <a href="<?= url('admin/organization/shops/create') ?>" class="btn btn-warning btn-sm text-dark">
      <i class="bi bi-plus-lg me-1"></i>Add Shop
    </a>
  </div>
</div>

<!-- ── KPI Strip ────────────────────────────────────────── -->
<div class="row g-3 mb-4">

  <!-- Farms -->
  <div class="col-6 col-sm-4 col-xl-2">
    <div class="card org-kpi-card kpi-farm h-100">
      <div class="card-body d-flex align-items-center gap-3 py-3 px-3">
        <div class="kpi-icon-wrap bg-success bg-opacity-10 text-success">
          <i class="bi bi-flower1"></i>
        </div>
        <div>
          <div class="kpi-num"><?= (int)($farmStats['total'] ?? 0) ?></div>
          <div class="kpi-label">Farms</div>
          <div class="kpi-sub">
            <span class="text-success"><?= (int)($farmStats['active'] ?? 0) ?> active</span>
          </div>
        </div>
      </div>
      <a href="<?= url('admin/organization/farms') ?>" class="stretched-link" aria-label="View farms"></a>
    </div>
  </div>

  <!-- Warehouses -->
  <div class="col-6 col-sm-4 col-xl-2">
    <div class="card org-kpi-card kpi-warehouse h-100">
      <div class="card-body d-flex align-items-center gap-3 py-3 px-3">
        <div class="kpi-icon-wrap bg-primary bg-opacity-10 text-primary">
          <i class="bi bi-building"></i>
        </div>
        <div>
          <div class="kpi-num"><?= (int)($warehouseStats['total'] ?? 0) ?></div>
          <div class="kpi-label">Warehouses</div>
          <div class="kpi-sub">
            <span class="text-primary"><?= (int)($warehouseStats['active'] ?? 0) ?> active</span>
          </div>
        </div>
      </div>
      <a href="<?= url('admin/organization/warehouses') ?>" class="stretched-link" aria-label="View warehouses"></a>
    </div>
  </div>

  <!-- Shops -->
  <div class="col-6 col-sm-4 col-xl-2">
    <div class="card org-kpi-card kpi-shop h-100">
      <div class="card-body d-flex align-items-center gap-3 py-3 px-3">
        <div class="kpi-icon-wrap" style="background:rgba(253,126,20,.12);color:#fd7e14;">
          <i class="bi bi-shop"></i>
        </div>
        <div>
          <div class="kpi-num"><?= (int)($shopStats['total'] ?? 0) ?></div>
          <div class="kpi-label">Shops</div>
          <div class="kpi-sub" style="color:#fd7e14;"><?= (int)($shopStats['active'] ?? 0) ?> active</div>
        </div>
      </div>
      <a href="<?= url('admin/organization/shops') ?>" class="stretched-link" aria-label="View shops"></a>
    </div>
  </div>

  <!-- Assignments -->
  <div class="col-6 col-sm-4 col-xl-2">
    <div class="card org-kpi-card kpi-assignment h-100">
      <div class="card-body d-flex align-items-center gap-3 py-3 px-3">
        <div class="kpi-icon-wrap bg-info bg-opacity-10 text-info">
          <i class="bi bi-person-badge-fill"></i>
        </div>
        <div>
          <div class="kpi-num"><?= (int)($assignmentStats['total_active'] ?? 0) ?></div>
          <div class="kpi-label">Assignments</div>
          <div class="kpi-sub text-muted"><?= (int)($assignmentStats['farm_assignments'] ?? 0) + (int)($assignmentStats['warehouse_assignments'] ?? 0) + (int)($assignmentStats['shop_assignments'] ?? 0) ?> across all locations</div>
        </div>
      </div>
      <a href="<?= url('admin/organization/assignments') ?>" class="stretched-link" aria-label="View assignments"></a>
    </div>
  </div>

</div><!-- /.row KPIs -->

<!-- ── Main Content Row ─────────────────────────────────── -->
<div class="row g-3">

  <!-- Left: Recent Farms -->
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-flower1 me-2 text-success"></i>Recent Farms</span>
        <a href="<?= url('admin/organization/farms') ?>" class="btn btn-sm btn-outline-success">View All</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($recentFarms)): ?>
        <div class="empty-state py-4">
          <i class="bi bi-flower1 empty-icon"></i>
          <p class="mb-0 text-muted small">No farms registered yet.</p>
        </div>
        <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($recentFarms as $farm): ?>
          <a href="<?= url('admin/organization/farms/' . $farm['farm_id']) ?>"
             class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-2 px-3">
            <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:36px;height:36px;">
              <i class="bi bi-flower1 text-success"></i>
            </div>
            <div class="flex-grow-1 overflow-hidden">
              <div class="fw-semibold small text-truncate"><?= e($farm['farm_name']) ?></div>
              <div class="text-muted" style="font-size:.72rem;">
                <span class="font-monospace"><?= e($farm['farm_code']) ?></span>
                · <?= e($farm['location'] ?? 'No location') ?>
              </div>
            </div>
            <?php
              $typeClass = 'type-' . strtolower($farm['farm_type'] ?? 'mixed');
              $typeLabel = ucfirst(strtolower($farm['farm_type'] ?? 'Mixed'));
            ?>
            <span class="type-badge <?= $typeClass ?>"><?= $typeLabel ?></span>
            <span class="status-pill <?= $farm['is_active'] ? 'active' : 'inactive' ?>">
              <i class="bi bi-circle-fill" style="font-size:.5rem;"></i>
              <?= $farm['is_active'] ? 'Active' : 'Inactive' ?>
            </span>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Right: Recent Shops -->
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-shop me-2" style="color:#fd7e14;"></i>Recent Shops</span>
        <a href="<?= url('admin/organization/shops') ?>" class="btn btn-sm btn-outline-warning">View All</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($recentShops)): ?>
        <div class="empty-state py-4">
          <i class="bi bi-shop empty-icon"></i>
          <p class="mb-0 text-muted small">No shops registered yet.</p>
        </div>
        <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($recentShops as $shop): ?>
          <a href="<?= url('admin/organization/shops/' . $shop['shop_id']) ?>"
             class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-2 px-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:36px;height:36px;background:rgba(253,126,20,.1);">
              <i class="bi bi-shop" style="color:#fd7e14;"></i>
            </div>
            <div class="flex-grow-1 overflow-hidden">
              <div class="fw-semibold small text-truncate"><?= e($shop['shop_name']) ?></div>
              <div class="text-muted" style="font-size:.72rem;">
                <span class="font-monospace"><?= e($shop['shop_code']) ?></span>
                · <?= e($shop['location'] ?? 'No location') ?>
                <?php if (!empty($shop['opening_time']) && !empty($shop['closing_time'])): ?>
                <span class="ms-1 hours-badge py-0">
                  <i class="bi bi-clock"></i>
                  <?= e(substr($shop['opening_time'], 0, 5)) ?>–<?= e(substr($shop['closing_time'], 0, 5)) ?>
                </span>
                <?php endif; ?>
              </div>
            </div>
            <span class="type-badge type-<?= strtolower($shop['shop_type'] ?? 'retail') ?>">
              <?= ucfirst(strtolower($shop['shop_type'] ?? 'Retail')) ?>
            </span>
            <span class="status-pill <?= $shop['is_active'] ? 'active' : 'inactive' ?>">
              <i class="bi bi-circle-fill" style="font-size:.5rem;"></i>
              <?= $shop['is_active'] ? 'Active' : 'Inactive' ?>
            </span>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div><!-- /.row main content -->

<!-- ── Quick Nav ───────────────────────────────────────── -->
<div class="row g-3 mt-1">
  <?php
  $quickLinks = [
    ['url' => 'admin/organization/farms',       'icon' => 'bi-flower1',          'label' => 'Manage Farms',       'color' => 'success'],
    ['url' => 'admin/organization/warehouses',  'icon' => 'bi-building',         'label' => 'Manage Warehouses',  'color' => 'primary'],
    ['url' => 'admin/organization/shops',       'icon' => 'bi-shop',             'label' => 'Manage Shops',       'color' => 'warning'],
    ['url' => 'admin/organization/assignments', 'icon' => 'bi-person-badge-fill','label' => 'Assignments',        'color' => 'secondary'],
  ];
  foreach ($quickLinks as $ql): ?>
  <div class="col-6 col-sm-4 col-md-2">
    <a href="<?= url($ql['url']) ?>"
       class="card text-decoration-none card-hover h-100">
      <div class="card-body text-center py-3">
        <i class="bi <?= $ql['icon'] ?> text-<?= $ql['color'] ?> fs-4 d-block mb-1"></i>
        <span class="small fw-semibold text-dark d-block"><?= $ql['label'] ?></span>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Assignments Summary ─────────────────────────────── -->
<div class="row g-3 mt-1">

  <div class="col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-person-badge-fill me-2 text-info"></i>Assignments Summary</span>
        <a href="<?= url('admin/organization/assignments') ?>" class="btn btn-sm btn-outline-info">Manage</a>
      </div>
      <div class="card-body">
        <div class="row g-2">
          <?php
          $assignItems = [
            ['Farm Assignments',      $assignmentStats['farm_assignments']      ?? 0, '#198754', 'bi-flower1'],
            ['Warehouse Assignments', $assignmentStats['warehouse_assignments'] ?? 0, '#0d6efd', 'bi-building'],
            ['Shop Assignments',      $assignmentStats['shop_assignments']      ?? 0, '#fd7e14', 'bi-shop'],
          ];
          foreach ($assignItems as [$albl, $acount, $acolor, $aicon]):
          ?>
          <div class="col-12">
            <div class="d-flex align-items-center gap-3 p-2 rounded theme-surface-subtle">
              <i class="bi <?= $aicon ?> fs-5" style="color:<?= $acolor ?>;width:24px;"></i>
              <div class="flex-grow-1">
                <div class="small fw-semibold"><?= $albl ?></div>
                <div class="progress mt-1" style="height:5px;">
                  <?php
                  $total = (int)($assignmentStats['total_active'] ?? 1) ?: 1;
                  $pct   = min(100, round($acount / $total * 100));
                  ?>
                  <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $acolor ?>;"></div>
                </div>
              </div>
              <span class="badge" style="background:<?= $acolor ?>20;color:<?= $acolor ?>;font-weight:700;">
                <?= (int)$acount ?>
              </span>
            </div>
          </div>
          <?php endforeach; ?>
          <div class="col-12 mt-1">
            <div class="text-center small text-muted pt-1">
              <strong><?= (int)($assignmentStats['total_active'] ?? 0) ?></strong> total active assignments &mdash;
              <strong><?= (int)($assignmentStats['total_history_events'] ?? 0) ?></strong> history events
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

</div><!-- /.row assignments -->

<!-- ── Organization Map Summary ────────────────────────── -->
<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-map me-2 text-success"></i>Organization Map Summary</span>
      </div>
      <div class="card-body">
        <div class="row g-3 align-items-start">
          <!-- University root -->
          <div class="col-12 col-md-3 text-center">
            <div class="p-3 rounded border theme-tint-primary" style="border-color:#0d6efd!important;">
              <i class="bi bi-university fs-2 text-primary d-block mb-2"></i>
              <div class="fw-bold">Arsi University</div>
              <div class="text-muted small">Organization Root</div>
            </div>
          </div>
          <!-- Arrow -->
          <div class="col-12 col-md-1 text-center d-flex align-items-center justify-content-center">
            <i class="bi bi-arrow-right-circle-fill text-muted fs-3 d-none d-md-block"></i>
            <i class="bi bi-arrow-down-circle-fill text-muted fs-3 d-md-none"></i>
          </div>
          <!-- Entities -->
          <div class="col-12 col-md-8">
            <div class="row g-2">
              <div class="col-12 col-sm-4">
                <div class="p-3 rounded border h-100 theme-tint-success" style="border-left:4px solid #198754!important;">
                  <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-flower1-fill text-success"></i>
                    <span class="fw-semibold small">Farms</span>
                  </div>
                  <div class="display-6 fw-bold text-success"><?= (int)($farmStats['total'] ?? 0) ?></div>
                  <div class="small text-muted mt-1">
                    <?= (int)($farmStats['active'] ?? 0) ?> active ·
                    <?= (int)($farmStats['inactive'] ?? 0) ?> inactive
                  </div>
                  <div class="small text-muted">
                    <?= (int)($farmStats['with_manager'] ?? 0) ?> with manager
                  </div>
                </div>
              </div>
              <div class="col-12 col-sm-4">
                <div class="p-3 rounded border h-100 theme-tint-primary" style="border-left:4px solid #0d6efd!important;">
                  <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-building-fill text-primary"></i>
                    <span class="fw-semibold small">Warehouses</span>
                  </div>
                  <div class="display-6 fw-bold text-primary"><?= (int)($warehouseStats['total'] ?? 0) ?></div>
                  <div class="small text-muted mt-1">
                    <?= (int)($warehouseStats['active'] ?? 0) ?> active ·
                    <?= (int)($warehouseStats['inactive'] ?? 0) ?> inactive
                  </div>
                  <div class="small text-muted">
                    <?= number_format((float)($warehouseStats['total_capacity'] ?? 0), 0) ?> tons capacity
                  </div>
                </div>
              </div>
              <div class="col-12 col-sm-4">
                <div class="p-3 rounded border h-100 theme-tint-orange" style="border-left:4px solid #fd7e14!important;">
                  <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-shop" style="color:#fd7e14;"></i>
                    <span class="fw-semibold small">Retail Shops</span>
                  </div>
                  <div class="display-6 fw-bold" style="color:#fd7e14;"><?= (int)($shopStats['total'] ?? 0) ?></div>
                  <div class="small text-muted mt-1">
                    <?= (int)($shopStats['active'] ?? 0) ?> active ·
                    <?= (int)($shopStats['inactive'] ?? 0) ?> inactive
                  </div>
                  <div class="small text-muted">
                    <?= (int)($shopStats['retail_shops'] ?? 0) ?> retail · <?= (int)($shopStats['cafeteria_shops'] ?? 0) ?> cafeteria
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div><!-- /.row map summary -->

<?php $view->section('scripts'); ?>
<script src="<?= $view->asset('js/organization.js') ?>"></script>
<script>
(function () {
  'use strict';
  // Initialize tooltips
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
    new bootstrap.Tooltip(el);
  });
})();
</script>
<?php $view->endSection(); ?>
