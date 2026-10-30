<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 9
 * =========================================================
 * View     : modules/pos/views/stub.php
 * Purpose  : POS terminal placeholder.
 *            The POS is the primary interface for salespersons.
 *            Full implementation in the sales phase.
 * =========================================================
 *
 * @var string $pageTitle
 * @var string $module
 */
declare(strict_types=1);
?>

<div class="row justify-content-center">
  <div class="col-lg-7">

    <!-- POS Header -->
    <div class="card border-0 shadow-sm mb-4"
         style="background:linear-gradient(135deg,#198754,#20c997);color:#fff;">
      <div class="card-body d-flex align-items-center gap-3 py-4">
        <div class="rounded-circle bg-white bg-opacity-20 d-flex align-items-center justify-content-center flex-shrink-0"
             style="width:56px;height:56px;">
          <i class="bi bi-cart3" style="font-size:1.6rem;"></i>
        </div>
        <div>
          <h4 class="fw-bold mb-1">Point of Sale</h4>
          <p class="mb-0 opacity-75 small">
            Cash Sales Terminal — Arsi Agri-Manage
          </p>
        </div>
      </div>
    </div>

    <!-- POS Features preview -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header fw-semibold small">
        <i class="bi bi-list-check me-2 text-success"></i>POS Features
      </div>
      <div class="list-group list-group-flush">
        <?php
        $features = [
          ['bi-search',           'success',   'Fast product search by name or barcode'],
          ['bi-cart-plus-fill',   'primary',   'Cart management with quantity and subtotals'],
          ['bi-calculator',       'info',      'Automatic total, tax, and change calculation'],
          ['bi-cash-coin',        'success',   'Cash-only payment processing'],
          ['bi-receipt',          'warning',   'Instant receipt generation and printing'],
          ['bi-clipboard-minus',  'secondary', 'Automatic inventory deduction on sale'],
          ['bi-arrow-counterclockwise','danger','Sale void with reason'],
          ['bi-printer-fill',     'dark',      'Receipt reprint by transaction ID'],
        ];
        foreach ($features as [$icon, $color, $desc]):
        ?>
        <div class="list-group-item d-flex align-items-center gap-3 py-2">
          <i class="bi <?= $icon ?> text-<?= $color ?> fs-5 flex-shrink-0"></i>
          <span class="small"><?= $desc ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Status -->
    <div class="card border-0 shadow-sm">
      <div class="card-body text-center py-4">
        <i class="bi bi-clock-history fs-1 text-muted opacity-50 d-block mb-3"></i>
        <h6 class="fw-bold mb-1">POS Terminal — Implementation Pending</h6>
        <p class="text-muted small mb-3">
          The full POS terminal is being implemented in the Sales phase.<br>
          Salesperson accounts and shop assignments are already configured.
        </p>
        <div class="d-flex gap-2 justify-content-center flex-wrap">
          <a href="<?= url('dashboard') ?>" class="btn btn-success">
            <i class="bi bi-house me-1"></i>Back to Dashboard
          </a>
          <a href="<?= url('admin/products') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-box-seam me-1"></i>View Products
          </a>
        </div>
      </div>
    </div>

  </div>
</div>
