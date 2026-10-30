<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) â€” Phase 8D
 * =========================================================
 * File    : modules/settings/views/backups/index.php
 * Purpose : Backup & Restore center. Backup history table,
 *           create backup, download, verify, restore, delete.
 * =========================================================
 */
declare(strict_types=1);
$pageTitle = 'Backup & Restore';
$statusClasses = [
    'pending'   => 'bg-secondary-subtle text-secondary',
    'running'   => 'bg-info-subtle text-info',
    'completed' => 'bg-success-subtle text-success',
    'failed'    => 'bg-danger-subtle text-danger',
    'verified'  => 'bg-primary-subtle text-primary',
    'deleted'   => 'bg-secondary-subtle text-secondary',
];
?>
<?php $view->section('styles'); ?>
<link rel="stylesheet" href="<?= $view->asset('css/settings.css') ?>">
<?php $view->endSection(); ?>

<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-cloud-arrow-down me-2 text-success"></i>Backup &amp; Restore</h4>
    <nav aria-label="breadcrumb"><ol class="breadcrumb small mb-0">
      <li class="breadcrumb-item"><a href="<?= url('settings/security') ?>">Settings</a></li>
      <li class="breadcrumb-item active">Backup &amp; Restore</li>
    </ol></nav>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('settings/backup') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-calendar-check me-1"></i>Schedule Config
    </a>
    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#backupModal">
      <i class="bi bi-cloud-plus me-1"></i>Create Backup
    </button>
  </div>
</div>

<!-- Disk usage bar -->
<?php if (!empty($diskInfo)): ?>
<div class="card settings-card mb-3">
  <div class="card-body py-2 px-3">
    <div class="d-flex justify-content-between align-items-center mb-1">
      <span class="small fw-semibold text-muted"><i class="bi bi-hdd me-1"></i>Disk Usage</span>
      <span class="small text-muted">
        <?= $diskInfo['used_gb'] ?> GB used of <?= $diskInfo['total_gb'] ?> GB
        (<?= $diskInfo['percent_used'] ?>%)
      </span>
    </div>
    <div class="progress" style="height:8px;">
      <?php $pct = (float)$diskInfo['percent_used']; ?>
      <div class="progress-bar <?= $pct > 80 ? 'bg-danger' : ($pct > 60 ? 'bg-warning' : 'bg-success') ?>"
           style="width:<?= $pct ?>%"></div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Filter bar -->
<div class="filter-bar mb-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-auto">
      <select name="status" class="form-select form-select-sm">
        <option value="">All Statuses</option>
        <?php foreach (['completed','failed','verified','deleted','running'] as $s): ?>
        <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>>
          <?= ucfirst($s) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <select name="backup_type" class="form-select form-select-sm">
        <option value="">All Types</option>
        <option value="manual"      <?= ($filters['backup_type'] ?? '') === 'manual'      ? 'selected' : '' ?>>Manual</option>
        <option value="scheduled"   <?= ($filters['backup_type'] ?? '') === 'scheduled'   ? 'selected' : '' ?>>Scheduled</option>
        <option value="pre_restore" <?= ($filters['backup_type'] ?? '') === 'pre_restore' ? 'selected' : '' ?>>Pre-Restore</option>
      </select>
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-primary btn-sm">
        <i class="bi bi-funnel me-1"></i>Filter
      </button>
      <a href="<?= url('settings/backups') ?>" class="btn btn-outline-secondary btn-sm ms-1">Clear</a>
    </div>
  </form>
</div>

<!-- Backups table -->
<div class="card settings-card">
  <div class="card-body p-0">
    <?php if (empty($backups['data'])): ?>
    <div class="empty-state">
      <i class="bi bi-cloud-slash empty-icon"></i>
      <h6>No backups yet</h6>
      <p class="text-muted small">Create your first backup to protect your data.</p>
      <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#backupModal">
        <i class="bi bi-cloud-plus me-1"></i>Create Backup
      </button>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table settings-table mb-0">
        <thead><tr>
          <th>Filename</th>
          <th>Type</th>
          <th>Status</th>
          <th>Size</th>
          <th>Created By</th>
          <th>Date</th>
          <th class="text-end">Actions</th>
        </tr></thead>
        <tbody>
          <?php foreach ($backups['data'] as $b): ?>
          <tr id="brow_<?= (int)$b['id'] ?>">
            <td>
              <div class="fw-semibold small font-monospace"><?= e($b['filename']) ?></div>
              <?php if ($b['notes']): ?>
              <div class="text-muted" style="font-size:.72rem;"><?= e($b['notes']) ?></div>
              <?php endif; ?>
              <?php if ($b['checksum']): ?>
              <div class="text-muted" style="font-size:.65rem;" title="SHA-256">
                <i class="bi bi-shield-check text-success me-1"></i>SHA256: <?= substr($b['checksum'], 0, 16) ?>â€¦
              </div>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge bg-secondary-subtle text-dark">
                <?= ucfirst(str_replace('_', ' ', $b['backup_type'])) ?>
              </span>
            </td>
            <td>
              <span class="badge <?= $statusClasses[$b['status']] ?? 'bg-secondary' ?>">
                <?= ucfirst($b['status']) ?>
              </span>
            </td>
            <td class="small">
              <?php $mb = $b['file_size_bytes'] ? round($b['file_size_bytes'] / 1024 / 1024, 2) : 0; ?>
              <?= $mb > 0 ? "{$mb} MB" : 'â€”' ?>
            </td>
            <td class="small"><?= e($b['created_by_name'] ?? 'System') ?></td>
            <td class="small text-muted">
              <?= $b['created_at'] ? date('d/m/Y H:i', strtotime($b['created_at'])) : 'â€”' ?>
              <?php if ($b['completed_at']): ?>
              <br><span class="text-success" style="font-size:.65rem;">Done: <?= date('H:i:s', strtotime($b['completed_at'])) ?></span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <?php if ($b['status'] === 'completed' || $b['status'] === 'verified'): ?>
              <div class="d-flex gap-1 justify-content-end">
                <a href="<?= url('settings/backups/' . $b['id'] . '/download') ?>"
                   class="btn btn-outline-primary btn-sm" title="Download"
                   data-bs-toggle="tooltip">
                  <i class="bi bi-download"></i>
                </a>
                <button type="button" class="btn btn-outline-success btn-sm" title="Verify"
                        onclick="verifyBackup(<?= (int)$b['id'] ?>)"
                        data-bs-toggle="tooltip">
                  <i class="bi bi-shield-check"></i>
                </button>
                <button type="button" class="btn btn-outline-warning btn-sm" title="Restore"
                        onclick="restoreBackup(<?= (int)$b['id'] ?>, '<?= addslashes($b['filename']) ?>')"
                        data-bs-toggle="tooltip">
                  <i class="bi bi-arrow-counterclockwise"></i>
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" title="Delete"
                        onclick="deleteBackup(<?= (int)$b['id'] ?>)"
                        data-bs-toggle="tooltip">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
              <?php elseif ($b['status'] === 'failed'): ?>
              <button type="button" class="btn btn-outline-danger btn-sm" title="Delete"
                      onclick="deleteBackup(<?= (int)$b['id'] ?>)">
                <i class="bi bi-trash"></i>
              </button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($backups['totalPages'] > 1): ?>
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
      <span class="text-muted small">
        Showing <?= $backups['from'] ?? 1 ?>â€“<?= $backups['to'] ?? $backups['total'] ?>
        of <?= $backups['total'] ?> backups
      </span>
      <nav>
        <ul class="pagination pagination-sm mb-0">
          <?php for ($i = 1; $i <= $backups['totalPages']; $i++): ?>
          <li class="page-item <?= $i === $backups['page'] ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $i ?>&status=<?= e($filters['status']) ?>&backup_type=<?= e($filters['backup_type']) ?>">
              <?= $i ?>
            </a>
          </li>
          <?php endfor; ?>
        </ul>
      </nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- â”€â”€ Create Backup Modal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
<div class="modal fade" id="backupModal" tabindex="-1" aria-labelledby="backupModalLabel">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="backupModalLabel">
          <i class="bi bi-cloud-plus me-2 text-success"></i>Create Manual Backup
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="backupProgress" class="d-none text-center py-3">
          <div class="spinner-border text-success mb-2" style="width:3rem;height:3rem;"></div>
          <p class="text-muted mb-0">Creating backupâ€¦ please wait.</p>
        </div>
        <div id="backupForm">
          <p class="text-muted small">
            Creates a full database backup (mysqldump). The file is stored on the server.
          </p>
          <div class="mb-3">
            <label class="form-label">Notes <span class="text-muted">(optional)</span></label>
            <input type="text" class="form-control" id="backupNotes" maxlength="500"
                   placeholder="e.g. Before Phase 9 deployment">
          </div>
          <input type="hidden" id="backupCsrfToken" value="<?= csrf_token() ?>">
        </div>
        <div id="backupResult" class="d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="startBackupBtn">
          <i class="bi bi-cloud-plus me-1"></i>Start Backup
        </button>
      </div>
    </div>
  </div>
</div>

<?php $view->section('scripts'); ?>
<script src="<?= $view->asset('js/settings.js') ?>"></script>
<script>
(function() {
  'use strict';

  // Tooltips
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

  // Create backup
  document.getElementById('startBackupBtn').addEventListener('click', function() {
    var btn   = this;
    var form  = document.getElementById('backupForm');
    var prog  = document.getElementById('backupProgress');
    var res   = document.getElementById('backupResult');
    var notes = document.getElementById('backupNotes').value;
    var token = document.getElementById('backupCsrfToken').value;

    btn.disabled = true;
    form.classList.add('d-none');
    prog.classList.remove('d-none');
    res.classList.add('d-none');

    var fd = new FormData();
    fd.append('csrf_token', token);
    fd.append('notes', notes);

    fetch('<?= url('settings/backups/create') ?>', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: fd
    })
    .then(r => r.json())
    .then(function(resp) {
      prog.classList.add('d-none');
      res.classList.remove('d-none');
      if (resp.success) {
        res.className = 'alert alert-success small';
        res.innerHTML = '<i class="bi bi-check-circle me-1"></i>Backup created: <strong>' + resp.data.filename + '</strong>';
        setTimeout(() => window.location.reload(), 1500);
      } else {
        res.className = 'alert alert-danger small';
        res.innerHTML = '<i class="bi bi-x-circle me-1"></i>' + (resp.message || 'Backup failed.');
        btn.disabled = false;
        form.classList.remove('d-none');
      }
    })
    .catch(function() {
      prog.classList.add('d-none');
      res.className = 'alert alert-danger small';
      res.innerHTML = 'Network error.';
      res.classList.remove('d-none');
      btn.disabled = false;
      form.classList.remove('d-none');
    });
  });

  // Verify
  window.verifyBackup = function(id) {
    AAMSettings.ajaxAction('<?= url('settings/backups/') ?>' + id + '/verify', {}, function(resp) {
      if (resp.success) AAM.toast('Backup verified successfully.', 'success');
      else AAM.toast(resp.message || 'Verification failed.', 'error');
    });
  };

  // Delete
  window.deleteBackup = function(id) {
    AAMSettings.confirmDanger('Delete this backup?', 'This will remove the backup file permanently.', function() {
      AAMSettings.ajaxAction('<?= url('settings/backups/') ?>' + id + '/delete', {}, function(resp) {
        if (resp.success) {
          AAM.toast('Backup deleted.', 'success');
          var row = document.getElementById('brow_' + id);
          if (row) row.remove();
        } else {
          AAM.toast(resp.message || 'Delete failed.', 'error');
        }
      });
    });
  };

  // Restore
  window.restoreBackup = function(id, filename) {
    AAMSettings.confirmDanger(
      'Restore Database?',
      'This will overwrite the current database with backup: ' + filename + '. A pre-restore backup will be created first.',
      function() {
        AAMSettings.ajaxAction('<?= url('settings/backups/') ?>' + id + '/restore', {}, function(resp) {
          if (resp.success) {
            AAM.toast('Database restored successfully.', 'success');
            setTimeout(() => window.location.reload(), 1500);
          } else {
            AAM.toast(resp.message || 'Restore failed.', 'error');
          }
        });
      },
      'bg-danger'
    );
  };
})();
</script>
<?php $view->endSection(); ?>

