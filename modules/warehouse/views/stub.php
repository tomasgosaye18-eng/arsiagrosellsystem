<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 9
 * =========================================================
 * View     : modules/warehouse/views/stub.php
 * Purpose  : Operational placeholder for warehouse module pages.
 * =========================================================
 *
 * @var string $pageTitle
 * @var string $module
 */
declare(strict_types=1);

$title = e($pageTitle ?? $module ?? 'Warehouse');

// Determine active tab from URI
$uriPath    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$tabs = [
  'warehouse'              => ['bi-boxes',      'Inventory',        'primary'],
  'warehouse/receiving'    => ['bi-inbox-fill',  'Receiving',       'warning'],
  'warehouse/discrepancies'=> ['bi-clipboard-x', 'Discrepancies',   'danger'],
];
?>

<!-- Operation tabs -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-0">
    <nav class="nav nav-tabs nav-fill px-3 pt-2">
      <?php foreach ($tabs as $path => [$tabIcon, $tabLabel, $tabColor]):
        $isActive = str_contains($uriPath, str_replace('warehouse', '', $path)) && $path !== 'warehouse'
            ? true
            : ($path === 'warehouse' && !str_contains($uriPath, 'receiving') && !str_contains($uriPath, 'discrepanc'));
      ?>
      <a href="<?= url($path) ?>"
         class="nav-link <?= $isActive ? 'active' : '' ?> small">
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
      <span class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 rounded-circle"
            style="width:80px;height:80px;">
        <i class="bi bi-building text-primary" style="font-size:2.5rem;"></i>
      </span>
    </div>
    <h5 class="fw-bold mb-2"><?= $title ?></h5>
    <p class="text-muted mb-1 small">
      The warehouse operations interface is active. Stock levels and<br>
      movement data will appear here as receiving and transfers are recorded.
    </p>
    <p class="text-muted small">
      <i class="bi bi-info-circle me-1"></i>
      Warehouse KPIs are already visible on your
      <a href="<?= url('dashboard') ?>">Warehouse Dashboard</a>.
    </p>
    <div class="mt-4 d-flex gap-2 justify-content-center flex-wrap">
      <a href="<?= url('dashboard') ?>" class="btn btn-primary">
        <i class="bi bi-speedometer2 me-1"></i>Warehouse Dashboard
      </a>
      <a href="<?= url('inventory') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-clipboard-data me-1"></i>Inventory Overview
      </a>
      <a href="<?= url('transfers') ?>" class="btn btn-outline-info">
        <i class="bi bi-arrow-left-right me-1"></i>Transfers
      </a>
    </div>
  </div>
</div>
