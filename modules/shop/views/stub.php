<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 9
 * =========================================================
 * View     : modules/shop/views/stub.php
 * Purpose  : Operational placeholder for shop module pages.
 * =========================================================
 *
 * @var string $pageTitle
 * @var string $module
 */
declare(strict_types=1);

$title = e($pageTitle ?? $module ?? 'Shop Operations');

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$tabs = [
  'shops'             => ['bi-grid-fill',       'All Shops'],
  'pos'               => ['bi-cart3',            'Point of Sale'],
  'transfers'         => ['bi-arrow-left-right', 'Transfers'],
];
?>

<!-- Tabs -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-0">
    <nav class="nav nav-tabs nav-fill px-3 pt-2">
      <?php foreach ($tabs as $path => [$tabIcon, $tabLabel]):
        $isActive = str_contains($uriPath, '/' . ltrim($path, '/'));
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
      <span class="d-inline-flex align-items-center justify-content-center rounded-circle"
            style="width:80px;height:80px;background:rgba(253,126,20,.12);">
        <i class="bi bi-shop" style="font-size:2.5rem;color:#fd7e14;"></i>
      </span>
    </div>
    <h5 class="fw-bold mb-2"><?= $title ?></h5>
    <p class="text-muted mb-1 small">
      The retail shop operations module will provide per-shop inventory<br>
      management, sales tracking, and stock transfer receiving.
    </p>
    <p class="text-muted small">
      <i class="bi bi-info-circle me-1"></i>
      Shop list and assignments are managed under
      <a href="<?= url('admin/organization/shops') ?>">Organization → Shops</a>.
    </p>
    <div class="mt-4 d-flex gap-2 justify-content-center flex-wrap">
      <a href="<?= url('dashboard') ?>" class="btn btn-warning text-dark">
        <i class="bi bi-speedometer2 me-1"></i>Dashboard
      </a>
      <a href="<?= url('admin/organization/shops') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-diagram-3 me-1"></i>Manage Shops
      </a>
      <a href="<?= url('pos') ?>" class="btn btn-outline-success">
        <i class="bi bi-cart3 me-1"></i>Open POS
      </a>
    </div>
  </div>
</div>
