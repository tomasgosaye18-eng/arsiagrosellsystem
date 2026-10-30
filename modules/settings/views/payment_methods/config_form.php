<?php
/**
 * View : settings/payment_methods/config_form.php
 * Purpose : Provider configuration form for a mobile banking method.
 *
 * SECURITY:
 *   - Sensitive fields (api_key, app_key) are rendered as
 *     password inputs with "Leave blank to keep existing" placeholder.
 *     Their current values are NEVER shown or pre-filled.
 *   - Non-sensitive fields may be pre-filled from existing config
 *     only if the controller/service decides it is safe to expose them.
 *     Currently the controller does NOT pre-fill any values —
 *     the admin must re-enter all fields on update.
 *
 * @var array  $method    Payment method row (no provider_config values)
 * @var array  $fieldMeta Config field definitions from PaymentMethodService
 * @var string $csrfToken
 * @var string $userRole
 */
declare(strict_types=1);

$method    ??= [];
$fieldMeta ??= [];
$methodId    = (int)($method['id'] ?? 0);
?>

<!-- Breadcrumb -->
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-gear-fill me-2 text-primary"></i>Configure <?= e($method['name'] ?? '') ?>
    </h4>
    <nav aria-label="breadcrumb"><ol class="breadcrumb small mb-0">
      <li class="breadcrumb-item"><a href="<?= url('admin/dashboard') ?>">System Admin</a></li>
      <li class="breadcrumb-item"><a href="<?= url('settings/payment-methods') ?>">Payment Methods</a></li>
      <li class="breadcrumb-item"><a href="<?= url('settings/payment-methods/' . $methodId) ?>"><?= e($method['name'] ?? '') ?></a></li>
      <li class="breadcrumb-item active">Configure</li>
    </ol></nav>
  </div>
  <a href="<?= url('settings/payment-methods/' . $methodId) ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back to Detail
  </a>
</div>

<!-- Security notice -->
<div class="alert alert-warning d-flex gap-2 align-items-start py-2 mb-4 small">
  <i class="bi bi-shield-lock-fill flex-shrink-0 mt-1 text-warning"></i>
  <div>
    <strong>Security Notice.</strong>
    Provider configuration (API keys, credentials) is stored server-side only.
    Sensitive fields are marked with <i class="bi bi-lock-fill text-danger"></i> and
    are <strong>never</strong> exposed to HTML, JavaScript, or browser storage.
    Leave sensitive fields blank to keep the existing stored value.
    Only System Administrators can access this page.
  </div>
</div>

<?php if (empty($fieldMeta)): ?>
<div class="alert alert-info small">
  <i class="bi bi-info-circle me-1"></i>
  No configurable fields are defined for provider code <code><?= e($method['provider_code'] ?? '') ?></code>.
</div>
<?php else: ?>

<div class="row">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-<?= ($method['code'] ?? '') === 'telebirr' ? 'telephone-fill text-warning' : 'bank text-primary' ?>"></i>
        <strong class="small"><?= e($method['name'] ?? '') ?> Provider Configuration</strong>
      </div>
      <div class="card-body">
        <form action="<?= url('settings/payment-methods/' . $methodId . '/config') ?>"
              method="POST" autocomplete="off" id="configForm">
          <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
          <!-- Disable browser autocomplete on the whole form -->
          <input type="text"     name="fake_user"  style="display:none;" autocomplete="username">
          <input type="password" name="fake_pass"  style="display:none;" autocomplete="new-password">

          <!-- Description / Notes -->
          <div class="mb-4">
            <label class="form-label fw-semibold">
              Configuration Notes <span class="text-muted fw-normal small">(optional)</span>
            </label>
            <input type="text" name="description" class="form-control"
                   placeholder="e.g. Sandbox credentials for testing — update before go-live"
                   maxlength="255">
            <div class="form-text">Internal note about this configuration. Not shown to salespersons.</div>
          </div>

          <hr class="my-3">

          <!-- Dynamic fields from fieldMeta -->
          <?php foreach ($fieldMeta as $key => $field): ?>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="cfg_<?= e($key) ?>">
              <?= e($field['label']) ?>
              <?php if ($field['sensitive']): ?>
              <i class="bi bi-lock-fill text-danger ms-1" title="Sensitive — stored securely, never shown"></i>
              <?php endif; ?>
            </label>

            <?php if ($field['type'] === 'select' && !empty($field['options'])): ?>
            <select name="<?= e($key) ?>" id="cfg_<?= e($key) ?>" class="form-select">
              <option value="">— Select —</option>
              <?php foreach ($field['options'] as $val => $label): ?>
              <option value="<?= e($val) ?>"><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>

            <?php elseif ($field['type'] === 'password'): ?>
            <input type="password"
                   name="<?= e($key) ?>"
                   id="cfg_<?= e($key) ?>"
                   class="form-control"
                   placeholder="<?= e($field['placeholder']) ?>"
                   autocomplete="new-password">
            <div class="form-text text-muted">
              <i class="bi bi-info-circle me-1"></i>
              Leave blank to keep the existing stored value. Only enter a new value to change it.
            </div>

            <?php elseif ($field['type'] === 'url'): ?>
            <input type="url"
                   name="<?= e($key) ?>"
                   id="cfg_<?= e($key) ?>"
                   class="form-control"
                   placeholder="<?= e($field['placeholder']) ?>">

            <?php else: ?>
            <input type="text"
                   name="<?= e($key) ?>"
                   id="cfg_<?= e($key) ?>"
                   class="form-control"
                   placeholder="<?= e($field['placeholder']) ?>"
                   autocomplete="off">
            <?php endif; ?>
          </div>
          <?php endforeach; ?>

          <hr class="mt-4 mb-3">

          <div class="d-flex gap-2 flex-wrap">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-floppy me-1"></i>Save Configuration
            </button>
            <a href="<?= url('settings/payment-methods/' . $methodId) ?>"
               class="btn btn-outline-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Right: Integration status -->
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header small"><i class="bi bi-plug me-2"></i>Integration Status</div>
      <div class="card-body small">
        <div class="alert alert-info py-2 mb-2">
          <i class="bi bi-info-circle me-1"></i>
          <strong>Development Mode:</strong> Official CBE Birr and Telebirr API credentials
          are required for live payments. Until configured, all mobile payments remain
          in <strong>PENDING</strong> status and are clearly marked as development-mode.
        </div>
        <ul class="mb-0 ps-3" style="line-height:2;">
          <li>Configure sandbox credentials to enable test flow.</li>
          <li>Set <strong>Environment = Production</strong> only when ready to go live.</li>
          <li>Never paste production credentials in a development environment.</li>
          <li>Keep API keys secret — never share in emails or chat.</li>
        </ul>
      </div>
    </div>
    <div class="card border-0 shadow-sm">
      <div class="card-header small"><i class="bi bi-shield-lock me-2 text-success"></i>Security Reminders</div>
      <div class="card-body small text-muted">
        <ul class="mb-0 ps-3" style="line-height:2;">
          <li>API keys are stored server-side only.</li>
          <li>Keys are <strong>never</strong> sent to the browser.</li>
          <li>All config changes are recorded in the audit log.</li>
          <li>Only System Administrators can access this page.</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php endif; ?>
