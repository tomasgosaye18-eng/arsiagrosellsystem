<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 9
 * =========================================================
 * View     : modules/inventory/views/stub.php
 * Purpose  : Operational placeholder for inventory module pages.
 * =========================================================
 *
 * @var string $pageTitle
 * @var string $module
 */
declare(strict_types=1);

$title = e($pageTitle ?? $module ?? 'Inventory');

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$tabs = [
  'inventory'         => ['bi-clipboard-data',  'Overview'],
  'inventory/batches' => ['bi-layers',           'Batches'],
  'inventory/adjust'  => ['bi-sliders',          'Adjustment'],
];
?>

<!-- Tabs -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-0">
    <nav class="nav nav-tabs nav-fill px-3 pt-2">
      <?php foreach ($tabs as $path => [$tabIcon, $tabLabel]):
        $isActive = str_ends_with(rtrim($uriPath, '/'), '/' . ltrim($path, '/'));
      ?>
      <a href="<?= url($path) ?>" class="nav-link <?= $isActive ? 'active' : '' ?> small">
        <i class="bi <?= $tabIcon ?> me-1"></i><?= $tabLabel ?>
      </a>
      <?php endforeach; ?>
    </nav>
  </div>
</div>

<!-- Status Card -->
<div class="card border-0 shadow-sm">
  <div class="card-body py-5 text-center">
    <div class="mb-4">
      <span class="d-inline-flex align-items-center justify-content-center bg-secondary bg-opacity-10 rounded-circle"
            style="width:80px;height:80px;">
        <i class="bi bi-clipboard-data text-secondary" style="font-size:2.5rem;"></i>
      </span>
    </div>
    <h5 class="fw-bold mb-2"><?= $title ?></h5>
    <p class="text-muted mb-1 small">
      The inventory management interface will show real-time stock levels<br>
      across the central warehouse and all retail shops.
    </p>
    <p class="text-muted small">
      <i class="bi bi-info-circle me-1"></i>
      Stock KPIs — total stock, low stock alerts — are already tracked on the
      <a href="<?= url('dashboard') ?>">Dashboard</a>.
    </p>
    <div class="mt-4 d-flex gap-2 justify-content-center flex-wrap">
      <a href="<?= url('dashboard') ?>" class="btn btn-secondary">
        <i class="bi bi-speedometer2 me-1"></i>Dashboard
      </a>
      <a href="<?= url('warehouse') ?>" class="btn btn-outline-primary">
        <i class="bi bi-building me-1"></i>Warehouse Stock
      </a>
      <a href="<?= url('admin/products') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-box-seam me-1"></i>Products
      </a>
    </div>
  </div>
</div>
