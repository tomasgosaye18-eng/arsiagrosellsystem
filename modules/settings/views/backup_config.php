<?php
/**
 * File    : modules/settings/views/backup_config.php
 * Purpose : Backup schedule configuration (auto backup settings).
 */
declare(strict_types=1);
$pageTitle = 'Backup Configuration';
$val = function(string $key, mixed $default = '') use ($settings): mixed {
    return $settings[$key]['typed_value'] ?? $settings[$key]['setting_value'] ?? $default;
};
?>
<?php $view->section('styles'); ?>
<link rel="stylesheet" href="<?= $view->asset('css/settings.css') ?>">
<?php $view->endSection(); ?>

<div class="d-flex justify-content-between align-items-start mb-3">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-calendar-check me-2 text-success"></i>Backup Configuration</h4>
    <nav aria-label="breadcrumb"><ol class="breadcrumb small mb-0">
      <li class="breadcrumb-item"><a href="<?= url('settings/backups') ?>">Backup Center</a></li>
      <li class="breadcrumb-item active">Schedule Config</li>
    </ol></nav>
  </div>
  <a href="<?= url('settings/backups') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Backup History
  </a>
</div>

<div id="formAlert" class="d-none mb-3"></div>

<form id="settingsForm" novalidate>
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card settings-card mb-3">
        <div class="card-header settings-card-header"><i class="bi bi-clock me-2"></i>Automatic Backup Schedule</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="auto_enabled"
                       name="auto_enabled" value="1"
                       <?= (bool)(int)$val('backup.auto_enabled', '0') ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="auto_enabled">
                  Enable Automatic Scheduled Backups
                </label>
              </div>
              <div class="form-text">Requires a cron job: <code>php /path/to/app/artisan backup:run</code></div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Frequency</label>
              <select class="form-select" name="frequency">
                <option value="daily"   <?= $val('backup.frequency', 'daily')   === 'daily'   ? 'selected' : '' ?>>Daily</option>
                <option value="weekly"  <?= $val('backup.frequency', 'daily')   === 'weekly'  ? 'selected' : '' ?>>Weekly</option>
                <option value="monthly" <?= $val('backup.frequency', 'daily')   === 'monthly' ? 'selected' : '' ?>>Monthly</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Backup Time</label>
              <input type="time" class="form-control" name="backup_time"
                     value="<?= e($val('backup.time', '02:00')) ?>">
              <div class="form-text">24-hour format. Low-traffic hours recommended.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Retention Days</label>
              <input type="number" class="form-control" name="retention_days"
                     value="<?= (int)$val('backup.retention_days', 30) ?>"
                     min="1" max="365">
              <div class="form-text">Auto-delete backups older than N days</div>
            </div>
          </div>
        </div>
      </div>

      <div class="alert alert-info small">
        <i class="bi bi-info-circle me-1"></i>
        <strong>Note:</strong> Automatic scheduling requires a server cron job.
        Manual backup is always available in the <a href="<?= url('settings/backups') ?>">Backup Center</a>.
      </div>
    </div>
    <div class="col-lg-4">
      <div class="d-grid gap-2">
        <button type="submit" class="btn btn-success" id="submitBtn">
          <i class="bi bi-floppy me-1"></i>Save Backup Config
        </button>
        <a href="<?= url('settings/backups') ?>" class="btn btn-outline-primary">
          <i class="bi bi-cloud-arrow-down me-1"></i>Create Backup Now
        </a>
        <a href="<?= url('settings/security') ?>" class="btn btn-outline-secondary">Back</a>
      </div>
    </div>
  </div>
</form>

<?php $view->section('scripts'); ?>
<script src="<?= $view->asset('js/settings.js') ?>"></script>
<script>
AAMSettings.bindAjaxForm('settingsForm', '<?= url('settings/backup') ?>', {
  successMsg:'Backup configuration saved.', submitBtnId:'submitBtn', alertId:'formAlert',
  transform: function(fd) {
    fd.set('auto_enabled', document.getElementById('auto_enabled').checked ? '1' : '0');
    return fd;
  }
});
</script>
<?php $view->endSection(); ?>
