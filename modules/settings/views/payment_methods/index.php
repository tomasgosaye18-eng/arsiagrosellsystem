<?php
/**
 * =========================================================
 * View : settings/payment_methods/index.php
 * Purpose : Payment Methods management page
 *           System Administration → System Configuration →
 *           Payment Methods
 * @var array  $methods    All payment methods with transaction counts
 * @var string $userRole
 * @var string $csrfToken
 * =========================================================
 */
declare(strict_types=1);

$methods ??= [];
?>
<?php $view->section('styles'); ?>
<style>
.pm-card{border:1.5px solid var(--theme-border,#dee2e6);border-radius:10px;padding:20px;
  background:var(--theme-card,#fff);transition:box-shadow .15s;}
.pm-card:hover{box-shadow:0 3px 12px rgba(0,0,0,.08);}
.pm-card.pm-active{border-color:rgba(25,135,84,.35);}
.pm-card.pm-inactive{border-color:rgba(220,53,69,.25);opacity:.85;}
.pm-type-badge{font-size:.72rem;font-weight:600;padding:3px 10px;border-radius:20px;}
.pm-status-dot{width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:6px;flex-shrink:0;}
.pm-status-dot.active{background:#198754;box-shadow:0 0 6px rgba(25,135,84,.5);}
.pm-status-dot.inactive{background:#dc3545;}
.pm-icon{width:48px;height:48px;border-radius:10px;display:flex;align-items:center;
  justify-content:center;font-size:1.4rem;flex-shrink:0;}
.pm-icon.cash{background:rgba(25,135,84,.1);color:#198754;}
.pm-icon.mobile{background:rgba(13,110,253,.1);color:#0d6efd;}
.pm-actions .btn{font-size:.8rem;}
.audit-note{font-size:.72rem;color:var(--theme-text-muted,#6c757d);}
</style>
<?php $view->endSection(); ?>

<!-- Page header -->
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-credit-card-2-front-fill me-2 text-primary"></i>Payment Methods
    </h4>
    <nav aria-label="breadcrumb"><ol class="breadcrumb small mb-0">
      <li class="breadcrumb-item"><a href="<?= url('admin/dashboard') ?>">System Admin</a></li>
      <li class="breadcrumb-item">System Configuration</li>
      <li class="breadcrumb-item active">Payment Methods</li>
    </ol></nav>
  </div>
</div>

<!-- Info banner -->
<div class="alert alert-info d-flex gap-2 align-items-start py-2 mb-4 small">
  <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
  <div>
    <strong>Centralized Payment Method Management.</strong>
    Payment methods listed here are the only methods available at the AU-AgroSale POS terminal.
    Deactivating a method removes it from the POS immediately — historical transactions are always preserved.
    Salespersons cannot create, edit, or delete payment methods.
  </div>
</div>

<?php if (empty($methods)): ?>
<div class="text-center py-5 text-muted">
  <i class="bi bi-credit-card display-4 d-block mb-3 opacity-25"></i>
  <h5>No payment methods found.</h5>
  <p class="small">Run migration <code>2026_08_16_000037_payment_methods.sql</code> to seed the initial methods.</p>
</div>
<?php else: ?>

<!-- Methods grid -->
<div class="row g-3 mb-4">
  <?php foreach ($methods as $method):
    $isActive   = (bool)(int)$method['is_active'];
    $isCash     = ($method['type'] === 'cash');
    $txnCount   = (int)($method['transaction_count'] ?? 0);
    $hasHistory = $txnCount > 0;
  ?>
  <div class="col-lg-4 col-md-6">
    <div class="pm-card <?= $isActive ? 'pm-active' : 'pm-inactive' ?> h-100">
      <div class="d-flex align-items-start gap-3 mb-3">
        <div class="pm-icon <?= $isCash ? 'cash' : 'mobile' ?>">
          <i class="bi <?= $isCash ? 'bi-cash-coin' : 'bi-phone-fill' ?>"></i>
        </div>
        <div class="flex-grow-1 min-w-0">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <h6 class="fw-bold mb-0"><?= e($method['name']) ?></h6>
            <span class="pm-type-badge <?= $isCash ? 'bg-success bg-opacity-10 text-success' : 'bg-primary bg-opacity-10 text-primary' ?>">
              <?= $isCash ? 'Cash' : 'Mobile Banking' ?>
            </span>
          </div>
          <div class="d-flex align-items-center mt-1">
            <span class="pm-status-dot <?= $isActive ? 'active' : 'inactive' ?>"></span>
            <span class="fw-semibold small <?= $isActive ? 'text-success' : 'text-danger' ?>">
              <?= $isActive ? 'ACTIVE' : 'INACTIVE' ?>
            </span>
          </div>
        </div>
      </div>

      <!-- Description -->
      <?php if (!empty($method['description'])): ?>
      <p class="small text-muted mb-2" style="font-size:.78rem;"><?= e($method['description']) ?></p>
      <?php endif; ?>

      <!-- Stats row -->
      <div class="d-flex gap-3 mb-3" style="font-size:.75rem;">
        <div>
          <span class="text-muted">Code:</span>
          <code class="ms-1"><?= e($method['code']) ?></code>
        </div>
        <?php if (!$isCash && !empty($method['provider_code'])): ?>
        <div>
          <span class="text-muted">Provider:</span>
          <code class="ms-1"><?= e($method['provider_code']) ?></code>
        </div>
        <?php endif; ?>
      </div>

      <!-- Transaction count -->
      <div class="mb-3">
        <span class="badge <?= $hasHistory ? 'bg-secondary' : 'bg-light text-muted border' ?>"
              style="font-size:.72rem;">
          <i class="bi bi-receipt me-1"></i>
          <?= number_format($txnCount) ?> historical transaction<?= $txnCount !== 1 ? 's' : '' ?>
        </span>
        <?php if ($hasHistory && !$isActive): ?>
        <div class="audit-note mt-1">
          <i class="bi bi-shield-check text-success me-1"></i>
          Historical transactions are preserved and reportable.
        </div>
        <?php endif; ?>
      </div>

      <!-- Updated by -->
      <?php if (!empty($method['updated_by_username'])): ?>
      <div class="audit-note mb-3">
        Last updated by <strong><?= e($method['updated_by_username']) ?></strong>
        <?php if (!empty($method['updated_at'])): ?>
        · <?= date('d/m/Y H:i', strtotime($method['updated_at'])) ?>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Actions -->
      <div class="pm-actions d-flex gap-2 flex-wrap">
        <a href="<?= url('settings/payment-methods/' . (int)$method['id']) ?>"
           class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-eye me-1"></i>View Details
        </a>

        <?php if (!$isCash): ?>
        <a href="<?= url('settings/payment-methods/' . (int)$method['id'] . '/config') ?>"
           class="btn btn-outline-primary btn-sm">
          <i class="bi bi-gear me-1"></i>Configure
        </a>
        <?php endif; ?>

        <!-- Toggle Active/Inactive -->
        <form action="<?= url('settings/payment-methods/' . (int)$method['id'] . '/toggle') ?>"
              method="POST" class="d-inline" id="toggleForm<?= (int)$method['id'] ?>">
          <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
          <button type="button"
                  class="btn btn-sm <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                  onclick="confirmToggle(<?= (int)$method['id'] ?>, '<?= e(addslashes($method['name'])) ?>', <?= $isActive ? 'true' : 'false' ?>)">
            <i class="bi <?= $isActive ? 'bi-toggle-on' : 'bi-toggle-off' ?> me-1"></i>
            <?= $isActive ? 'Deactivate' : 'Activate' ?>
          </button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- POS Preview panel -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header d-flex align-items-center gap-2">
    <i class="bi bi-cart3 text-primary"></i>
    <strong class="small">POS Payment Selector Preview</strong>
    <span class="badge bg-info ms-auto" style="font-size:.7rem;">How this appears to Salesperson</span>
  </div>
  <div class="card-body" style="max-width:380px;">
    <div class="mb-2">
      <label class="form-label small fw-semibold mb-1">Payment Method</label>
      <div class="d-flex gap-3 flex-wrap">
        <?php foreach ($methods as $m): if (!(bool)(int)$m['is_active']) continue; ?>
          <?php if ($m['type'] === 'cash'): ?>
          <div class="form-check">
            <input class="form-check-input" type="radio" disabled checked>
            <label class="form-check-label small">
              <i class="bi bi-cash-coin me-1 text-success"></i><?= e($m['name']) ?>
            </label>
          </div>
          <?php endif; ?>
          <?php if ($m['type'] === 'mobile_banking'): ?>
          <div class="form-check">
            <input class="form-check-input" type="radio" disabled>
            <label class="form-check-label small">
              <i class="bi bi-phone-fill me-1 text-primary"></i>Mobile Banking
            </label>
          </div>
          <?php break; /* only show Mobile Banking once */ endif; ?>
        <?php endforeach; ?>
      </div>
    </div>

    <?php
    $activeMobile = array_filter($methods, fn($m) => $m['type'] === 'mobile_banking' && (bool)(int)$m['is_active']);
    if (!empty($activeMobile)):
    ?>
    <div class="mt-2 ms-3 ps-2 border-start border-primary border-opacity-25">
      <label class="form-label small fw-semibold mb-1">Provider</label>
      <div class="d-flex gap-3 flex-wrap">
        <?php foreach ($activeMobile as $m): ?>
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
    <?php elseif (empty($activeMobile) && !empty($methods)): ?>
    <div class="text-muted small mt-2 ms-3">
      <i class="bi bi-info-circle me-1"></i>
      Mobile Banking option is hidden — no mobile providers are active.
    </div>
    <?php endif; ?>

    <?php $inactiveMethods = array_filter($methods, fn($m) => !(bool)(int)$m['is_active']); ?>
    <?php if (!empty($inactiveMethods)): ?>
    <div class="mt-3 pt-2 border-top">
      <div class="small text-muted mb-1"><i class="bi bi-eye-slash me-1"></i>Hidden from POS (inactive):</div>
      <?php foreach ($inactiveMethods as $m): ?>
      <span class="badge bg-secondary me-1 mb-1" style="font-size:.72rem;opacity:.6;">
        <?= e($m['name']) ?>
      </span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php endif; ?>

<!-- Confirm toggle modal -->
<div class="modal fade" id="toggleConfirmModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width:380px;">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h6 class="modal-title fw-bold" id="toggleModalTitle"></h6>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-0">
        <p class="mb-0 small" id="toggleModalBody"></p>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-sm" id="toggleConfirmBtn" onclick="submitToggle()">Confirm</button>
      </div>
    </div>
  </div>
</div>

<?php $view->section('scripts'); ?>
<script>
var _toggleFormId = null;

function confirmToggle(methodId, name, isCurrentlyActive) {
    _toggleFormId = 'toggleForm' + methodId;
    const title = document.getElementById('toggleModalTitle');
    const body  = document.getElementById('toggleModalBody');
    const btn   = document.getElementById('toggleConfirmBtn');

    if (isCurrentlyActive) {
        title.innerHTML = '<i class="bi bi-toggle-on text-danger me-2"></i>Deactivate ' + escHtml(name) + '?';
        body.innerHTML  = 'Deactivating <strong>' + escHtml(name) + '</strong> will immediately hide it from the POS terminal. ' +
                          'Salespersons will no longer be able to select it for new sales. ' +
                          '<br><br><span class="text-success"><i class="bi bi-shield-check me-1"></i>All historical transactions using this method are preserved.</span>';
        btn.className   = 'btn btn-danger btn-sm';
        btn.textContent = 'Deactivate';
    } else {
        title.innerHTML = '<i class="bi bi-toggle-off text-success me-2"></i>Activate ' + escHtml(name) + '?';
        body.innerHTML  = 'Activating <strong>' + escHtml(name) + '</strong> will make it available for selection in the POS terminal immediately.';
        btn.className   = 'btn btn-success btn-sm';
        btn.textContent = 'Activate';
    }

    var modal = new bootstrap.Modal(document.getElementById('toggleConfirmModal'));
    modal.show();
}

function submitToggle() {
    if (_toggleFormId) {
        document.getElementById(_toggleFormId).submit();
    }
}

function escHtml(s) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(String(s || '')));
    return d.innerHTML;
}
</script>
<?php $view->endSection(); ?>
