<?php
/**
 * View : settings/payment_methods/detail.php
 * Purpose : Single payment method detail + audit history
 * @var array  $method   Payment method row (no provider_config)
 * @var array  $audit    Audit log entries
 * @var string $csrfToken
 * @var string $userRole
 */
declare(strict_types=1);

$method  ??= [];
$audit   ??= [];
$isActive = (bool)(int)($method['is_active'] ?? 0);
$isCash   = ($method['type'] ?? '') === 'cash';
$txnCount = (int)($method['transaction_count'] ?? 0);
?>

<!-- Breadcrumb -->
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-<?= $isCash ? 'cash-coin' : 'phone-fill' ?> me-2 text-primary"></i>
      <?= e($method['name'] ?? '') ?>
    </h4>
    <nav aria-label="breadcrumb"><ol class="breadcrumb small mb-0">
      <li class="breadcrumb-item"><a href="<?= url('admin/dashboard') ?>">System Admin</a></li>
      <li class="breadcrumb-item"><a href="<?= url('settings/payment-methods') ?>">Payment Methods</a></li>
      <li class="breadcrumb-item active"><?= e($method['name'] ?? '') ?></li>
    </ol></nav>
  </div>
  <a href="<?= url('settings/payment-methods') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back to Payment Methods
  </a>
</div>

<div class="row g-3">
  <!-- Left: Details card -->
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-info-circle text-primary"></i>
        <strong class="small">Method Details</strong>
        <span class="ms-auto badge <?= $isActive ? 'bg-success' : 'bg-danger' ?>">
          <?= $isActive ? 'ACTIVE' : 'INACTIVE' ?>
        </span>
      </div>
      <div class="card-body">
        <table class="table table-sm table-borderless" style="font-size:.85rem;">
          <tr>
            <td class="text-muted" style="width:40%;">Code</td>
            <td><code><?= e($method['code'] ?? '') ?></code></td>
          </tr>
          <tr>
            <td class="text-muted">Display Name</td>
            <td><?= e($method['name'] ?? '') ?></td>
          </tr>
          <tr>
            <td class="text-muted">Type</td>
            <td>
              <span class="badge <?= $isCash ? 'bg-success bg-opacity-10 text-success' : 'bg-primary bg-opacity-10 text-primary' ?> border">
                <?= $isCash ? 'Cash' : 'Mobile Banking' ?>
              </span>
            </td>
          </tr>
          <?php if (!$isCash && !empty($method['provider_code'])): ?>
          <tr>
            <td class="text-muted">Provider Code</td>
            <td><code><?= e($method['provider_code']) ?></code></td>
          </tr>
          <?php endif; ?>
          <tr>
            <td class="text-muted">Sort Order</td>
            <td><?= (int)($method['sort_order'] ?? 0) ?></td>
          </tr>
          <tr>
            <td class="text-muted">Description</td>
            <td><?= e($method['description'] ?? '—') ?></td>
          </tr>
          <tr>
            <td class="text-muted">Transactions</td>
            <td>
              <span class="badge bg-secondary"><?= number_format($txnCount) ?></span>
              <?php if ($txnCount > 0): ?>
              <small class="text-muted ms-1">historical records preserved</small>
              <?php endif; ?>
            </td>
          </tr>
          <tr>
            <td class="text-muted">Created</td>
            <td><?= !empty($method['created_at']) ? date('d/m/Y H:i', strtotime($method['created_at'])) : '—' ?></td>
          </tr>
          <tr>
            <td class="text-muted">Last Updated</td>
            <td>
              <?= !empty($method['updated_at']) ? date('d/m/Y H:i', strtotime($method['updated_at'])) : '—' ?>
              <?php if (!empty($method['updated_by_username'])): ?>
              <div class="text-muted small">by <?= e($method['updated_by_username']) ?></div>
              <?php endif; ?>
            </td>
          </tr>
          <?php if (!$isCash): ?>
          <tr>
            <td class="text-muted">Provider Config</td>
            <td>
              <?php if ((bool)(int)($method['is_config_encrypted'] ?? 0)): ?>
              <span class="badge bg-success bg-opacity-10 text-success border" style="font-size:.7rem;">
                <i class="bi bi-lock-fill me-1"></i>Encrypted
              </span>
              <?php else: ?>
              <span class="badge bg-warning bg-opacity-10 text-warning border" style="font-size:.7rem;">
                <i class="bi bi-key me-1"></i>Stored (unencrypted)
              </span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endif; ?>
        </table>

        <!-- Actions -->
        <div class="d-flex gap-2 flex-wrap mt-2 border-top pt-3">
          <!-- Toggle -->
          <form action="<?= url('settings/payment-methods/' . (int)$method['id'] . '/toggle') ?>"
                method="POST" id="detailToggleForm">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <button type="submit"
                    class="btn btn-sm <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                    onclick="return confirm('<?= $isActive ? 'Deactivate' : 'Activate' ?> &quot;<?= e(addslashes($method['name'])) ?>&quot;?')">
              <i class="bi <?= $isActive ? 'bi-toggle-on' : 'bi-toggle-off' ?> me-1"></i>
              <?= $isActive ? 'Deactivate' : 'Activate' ?>
            </button>
          </form>

          <?php if (!$isCash): ?>
          <a href="<?= url('settings/payment-methods/' . (int)$method['id'] . '/config') ?>"
             class="btn btn-outline-primary btn-sm">
            <i class="bi bi-gear me-1"></i>Configure Provider
          </a>
          <?php endif; ?>
        </div>

        <?php if (!$isActive && $txnCount > 0): ?>
        <div class="alert alert-success py-2 mt-3 mb-0 small">
          <i class="bi bi-shield-check me-1"></i>
          This method is inactive but <?= number_format($txnCount) ?> historical transaction(s)
          remain fully visible in reports and receipts.
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Right: Audit history -->
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-clock-history text-secondary"></i>
        <strong class="small">Configuration Audit History</strong>
        <span class="badge bg-secondary ms-auto" style="font-size:.7rem;">
          <?= count($audit) ?> entries
        </span>
      </div>
      <?php if (empty($audit)): ?>
      <div class="card-body text-center py-4 text-muted small">
        <i class="bi bi-clock d-block mb-2 opacity-25 fs-3"></i>
        No configuration changes recorded yet.
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" style="font-size:.8rem;">
          <thead class="table-light">
            <tr>
              <th>Date/Time</th>
              <th>Action</th>
              <th>Changed By</th>
              <th>Detail</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($audit as $entry): ?>
            <tr>
              <td class="text-muted text-nowrap">
                <?= date('d/m/Y H:i', strtotime($entry['changed_at'])) ?>
              </td>
              <td>
                <?php
                $actionBadge = match($entry['action']) {
                    'activated'      => ['bg-success', 'bi-toggle-on',   'Activated'],
                    'deactivated'    => ['bg-danger',  'bi-toggle-off',  'Deactivated'],
                    'config_updated' => ['bg-primary', 'bi-gear',        'Config Updated'],
                    'created'        => ['bg-info',    'bi-plus-circle', 'Created'],
                    default          => ['bg-secondary','bi-circle',     ucfirst($entry['action'])],
                };
                ?>
                <span class="badge <?= $actionBadge[0] ?>" style="font-size:.7rem;">
                  <i class="bi <?= $actionBadge[1] ?> me-1"></i><?= $actionBadge[2] ?>
                </span>
              </td>
              <td><?= e($entry['changed_by_username'] ?? '—') ?></td>
              <td class="text-muted">
                <?php if ($entry['action'] === 'config_updated' && !empty($entry['new_value'])): ?>
                  <?= e($entry['new_value']) ?>
                <?php elseif (!empty($entry['notes'])): ?>
                  <?= e($entry['notes']) ?>
                <?php elseif (!empty($entry['field_changed'])): ?>
                  <?= e($entry['field_changed']) ?>:
                  <code><?= e($entry['old_value'] ?? '') ?></code> →
                  <code><?= e($entry['new_value'] ?? '') ?></code>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
