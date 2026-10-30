<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 9
 * =========================================================
 * View     : modules/farm/views/stub.php
 * Purpose  : Operational placeholder for farm module pages.
 *            Full farm operations (harvests, dispatches, records)
 *            are being implemented in the next phase.
 * =========================================================
 *
 * @var string $pageTitle
 * @var string $module
 */
declare(strict_types=1);

$icon  = 'bi-flower1';
$color = 'success';
$title = e($pageTitle ?? $module ?? 'Farm Operations');
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi <?= $icon ?> me-2 text-<?= $color ?>"></i><?= $title ?>
    </h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item active">Farm Operations</li>
      </ol>
    </nav>
  </div>
</div>

<!-- Operation tabs -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-0">
    <nav class="nav nav-tabs nav-fill px-3 pt-2">
      <?php
      $tabs = [
        'farms'              => ['bi-grid-fill',   'All Farms'],
        'farms'              => ['bi-basket',       'Harvests'],
        'farms'              => ['bi-truck',        'Dispatches'],
      ];
      $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
      $active  = 0;
      foreach (array_values($tabs) as $i => [$tabIcon, $tabLabel]):
      ?>
      <a href="<?= url('farms') ?>"
         class="nav-link <?= $i === $active ? 'active' : '' ?> small">
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
      <span class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 rounded-circle"
            style="width:80px;height:80px;">
        <i class="bi bi-flower1 text-success" style="font-size:2.5rem;"></i>
      </span>
    </div>
    <h5 class="fw-bold mb-2"><?= $title ?></h5>
    <p class="text-muted mb-1 small">
      Farm operations module is ready. Data will populate once<br>
      harvest and dispatch records are recorded through this interface.
    </p>
    <p class="text-muted small">
      <i class="bi bi-info-circle me-1"></i>
      Farm productivity KPIs are already reflected on your
      <a href="<?= url('dashboard') ?>">Farm Dashboard</a>.
    </p>
    <div class="mt-4 d-flex gap-2 justify-content-center flex-wrap">
      <a href="<?= url('dashboard') ?>" class="btn btn-success">
        <i class="bi bi-speedometer2 me-1"></i>Farm Dashboard
      </a>
      <a href="<?= url('products') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-box-seam me-1"></i>View Products
      </a>
    </div>
  </div>
</div>
