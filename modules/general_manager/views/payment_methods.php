<?php
/**
 * View : general_manager/payment_methods.php
 * Purpose : GM read-only view of payment method status.
 *           GM can see which methods are active/inactive.
 *           GM cannot activate, deactivate, or configure.
 *
 * @var array  $methods   All payment methods (no provider_config)
 * @var string $userRole
 */
declare(strict_types=1);
$methods ??= [];
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-credit-card-2-front-fill me-2 text-primary"></i>Payment Methods
    </h4>
    <nav aria-label="breadcrumb"><ol class="breadcrumb small mb-0">
      <li class="breadcrumb-item"><a href="<?= url('gm/dashboard') ?>">Dashboard</a></li>
      <li class="breadcrumb-item active">Payment Methods</li>
    </ol></nav>
  </div>
</div>

<!-- GM info banner -->
<div class="alert alert-info d-flex gap-2 align-items-start py-2 mb-4 small">
  <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
  <div>
    <strong>View only.</strong>
    Payment methods are managed by the System Administrator under
    <em>System Configuration → Payment Methods</em>.
    Active methods appear in the POS terminal for salespersons.
    Inactive methods are hidden from POS but historical transactions are preserved.
  </div>
</div>

<?php if (empty($methods)): ?>
<div class="text-center py-5 text-muted">
  <i class="bi bi-credit-card display-4 d-block mb-3 opacity-25"></i>
  <p>No payment methods found. Contact System Administrator.</p>
</div>
<?php else: ?>

<div class="row g-3">
  <?php foreach ($methods as $method):
    $isActive = (bool)(int)$method['is_active'];
    $isCash   = ($method['type'] === 'cash');
    $txnCount = (int)($method['transaction_count'] ?? 0);
  ?>
  <div class="col-lg-4 col-md-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex align-items-start gap-3 mb-3">
          <div class="rounded p-2 <?= $isCash ? 'bg-success bg-opacity-10' : 'bg-primary bg-opacity-10' ?>"
               style="font-size:1.3rem;">
            <i class="bi <?= $isCash ? 'bi-cash-coin text-success' : 'bi-phone-fill text-primary' ?>"></i>
          </div>
          <div class="flex-grow-1 min-w-0">
            <div class="fw-bold"><?= e($method['name']) ?></div>
            <div class="d-flex align-items-center gap-1 mt-1">
              <span class="rounded-circle d-inline-block"
                    style="width:8px;height:8px;background:<?= $isActive ? '#198754' : '#dc3545' ?>;
                           box-shadow:0 0 5px <?= $isActive ? 'rgba(25,135,84,.5)' : 'rgba(220,53,69,.4)' ?>;"></span>
              <span class="small fw-semibold <?= $isActive ? 'text-success' : 'text-danger' ?>">
                <?= $isActive ? 'ACTIVE' : 'INACTIVE' ?>
              </span>
              <span class="badge ms-1 <?= $isCash ? 'bg-success bg-opacity-10 text-success' : 'bg-primary bg-opacity-10 text-primary' ?> border"
                    style="font-size:.68rem;">
                <?= $isCash ? 'Cash' : 'Mobile Banking' ?>
              </span>
            </div>
          </div>
        </div>

        <?php if (!empty($method['description'])): ?>
        <p class="small text-muted mb-2" style="font-size:.78rem;"><?= e($method['description']) ?></p>
        <?php endif; ?>

        <div class="d-flex gap-3 mb-2" style="font-size:.75rem;">
          <div><span class="text-muted">Code:</span> <code><?= e($method['code']) ?></code></div>
          <?php if (!$isCash && !empty($method['provider_code'])): ?>
          <div><span class="text-muted">Provider:</span> <code><?= e($method['provider_code']) ?></code></div>
          <?php endif; ?>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top"
             style="font-size:.75rem;">
          <span class="text-muted">
            <i class="bi bi-receipt me-1"></i>
            <?= number_format($txnCount) ?> transaction<?= $txnCount !== 1 ? 's' : '' ?>
          </span>
          <?php if ($isActive): ?>
          <span class="badge bg-success bg-opacity-10 text-success border" style="font-size:.68rem;">
            <i class="bi bi-check-circle me-1"></i>Available in POS
          </span>
          <?php else: ?>
          <span class="badge bg-secondary bg-opacity-10 text-secondary border" style="font-size:.68rem;">
            <i class="bi bi-eye-slash me-1"></i>Hidden from POS
          </span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Active POS preview -->
<?php
$active = array_filter($methods, fn($m) => (bool)(int)$m['is_active']);
$inactive = array_filter($methods, fn($m) => !(bool)(int)$m['is_active']);
?>
<div class="card border-0 shadow-sm mt-4">
  <div class="card-header d-flex align-items-center gap-2 py-2">
    <i class="bi bi-cart3 text-primary small"></i>
    <strong class="small">Current POS Payment Selector</strong>
    <span class="badge bg-info ms-auto" style="font-size:.68rem;">Salesperson view</span>
  </div>
  <div class="card-body py-3" style="max-width:400px;">
    <?php if (empty($active)): ?>
      <div class="text-danger small">
        <i class="bi bi-exclamation-triangle me-1"></i>
        No active payment methods — POS is blocked. Contact System Administrator.
      </div>
    <?php else: ?>
      <div class="mb-2 small fw-semibold text-muted">Payment Method:</div>
      <div class="d-flex gap-3 flex-wrap mb-2">
        <?php foreach ($active as $m): if ($m['type'] !== 'cash') continue; ?>
        <div class="form-check">
          <input class="form-check-input" type="radio" disabled checked>
          <label class="form-check-label small">
            <i class="bi bi-cash-coin me-1 text-success"></i><?= e($m['name']) ?>
          </label>
        </div>
        <?php endforeach; ?>
        <?php $hasMobile = count(array_filter($active, fn($m) => $m['type'] === 'mobile_banking')) > 0; ?>
        <?php if ($hasMobile): ?>
        <div class="form-check">
          <input class="form-check-input" type="radio" disabled>
          <label class="form-check-label small">
            <i class="bi bi-phone-fill me-1 text-primary"></i>Mobile Banking
          </label>
        </div>
        <?php endif; ?>
      </div>
      <?php if ($hasMobile): ?>
      <div class="ms-3 ps-2 border-start border-primary border-opacity-25 small">
        <div class="fw-semibold text-muted mb-1">Provider:</div>
        <div class="d-flex gap-3 flex-wrap">
          <?php foreach ($active as $m): if ($m['type'] !== 'mobile_banking') continue; ?>
          <div class="form-check">
            <input class="form-check-input" type="radio" disabled>
            <label class="form-check-label small">
              <?php if ($m['code'] === 'cbe_birr'): ?>
                <i class="bi bi-bank me-1 text-primary"></i>
              <?php else: ?>
                <i class="bi bi-telephone-fill me-1 text-warning"></i>
              <?php endif; ?>
              <?= e($m['name']) ?>
            </label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($inactive)): ?>
    <div class="mt-3 pt-2 border-top small text-muted">
      <i class="bi bi-eye-slash me-1"></i>Hidden from POS:
      <?php foreach ($inactive as $m): ?>
      <span class="badge bg-secondary opacity-50 me-1"><?= e($m['name']) ?></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php endif; ?>
