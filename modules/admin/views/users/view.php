<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 8A
 * =========================================================
 * File     : modules/admin/views/users/view.php
 * Layout   : templates/master.php
 * Purpose  : User detail page — full profile, role, permissions,
 *            recent activity, and login history.
 * Dependencies: UserController, UserService, LoginHistoryModel,
 *               ActivityLogModel.
 * =========================================================
 *
 * @var \AAM\Core\View         $view
 * @var array<string,mixed>    $user
 * @var array<int,array>       $loginHistory
 * @var array<int,array>       $recentActivities
 */
declare(strict_types=1);

$uid       = (int) $user['id'];
$isSelf    = $uid === \AAM\Core\SessionManager::userId();
$isActive  = (int) $user['is_active'] === 1 && $user['account_status'] === 'active';
$isLocked  = (bool) (int) ($user['is_locked'] ?? 0);
$fullName  = trim($user['first_name'] . ' ' . ($user['middle_name'] ?? '') . ' ' . $user['last_name']);
$avatarUrl = \AAM\Helpers\UploadHelper::profileImageUrl($user['profile_photo'] ?? null);

$statusColors = [
    'active'    => 'success',
    'inactive'  => 'secondary',
    'suspended' => 'danger',
    'locked'    => 'warning text-dark',
    'pending'   => 'info',
    'deleted'   => 'dark',
];
$statusColor = $statusColors[$user['account_status'] ?? 'active'] ?? 'secondary';

$genderLabel = match($user['gender'] ?? '') {
    'male'              => 'Male',
    'female'            => 'Female',
    'other'             => 'Other',
    'prefer_not_to_say' => 'Prefer not to say',
    default             => '—',
};

// Activity icon/color
$actionMeta = [
    'user.login'            => ['bi-box-arrow-in-right', 'success'],
    'user.logout'           => ['bi-box-arrow-right',    'secondary'],
    'user.created'          => ['bi-person-plus-fill',   'primary'],
    'user.updated'          => ['bi-pencil-square',      'info'],
    'user.deleted'          => ['bi-trash3-fill',        'danger'],
    'user.activated'        => ['bi-person-check-fill',  'success'],
    'user.deactivated'      => ['bi-person-slash',       'warning'],
    'user.locked'           => ['bi-lock-fill',          'danger'],
    'user.unlocked'         => ['bi-unlock-fill',        'success'],
    'password.reset'        => ['bi-key-fill',           'warning'],
    'password.changed'      => ['bi-shield-lock-fill',   'primary'],
    'profile.updated'       => ['bi-person-fill',        'info'],
    'profile.photo_uploaded'=> ['bi-camera-fill',        'primary'],
];
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-person-badge me-2 text-primary"></i>User Profile
    </h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('admin/dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= url('admin/users') ?>">Users</a></li>
        <li class="breadcrumb-item active"><?= e($user['username']) ?></li>
      </ol>
    </nav>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('admin/users/' . $uid . '/edit') ?>" class="btn btn-primary btn-sm">
      <i class="bi bi-pencil-fill me-1"></i>Edit
    </a>
    <a href="<?= url('admin/users') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>
  </div>
</div>

<div class="row g-4">

  <!-- ── Left Column ──────────────────────────────────────── -->
  <div class="col-lg-4">

    <!-- Profile Card -->
    <div class="card border-0 shadow-sm text-center mb-3">
      <div class="card-body py-4">
        <img src="<?= e($avatarUrl) ?>"
             alt="Profile photo"
             class="rounded-circle shadow mb-3"
             style="width:100px;height:100px;object-fit:cover;border:3px solid #dee2e6;"
             onerror="this.src='<?= asset('images/no-avatar.svg') ?>'">
        <h5 class="fw-bold mb-1"><?= e($fullName) ?></h5>
        <div class="text-muted small mb-2">
          <i class="bi bi-at me-1"></i><?= e($user['username']) ?>
        </div>
        <div class="mb-2">
          <span class="badge bg-light text-dark border fw-normal">
            <?= e($user['role_display_name'] ?? $user['role_name'] ?? '—') ?>
          </span>
        </div>
        <span class="badge bg-<?= $statusColor ?> rounded-pill px-3 py-1">
          <?= e(ucfirst($user['account_status'] ?? 'active')) ?>
        </span>
        <?php if ($isLocked): ?>
          <span class="badge bg-danger rounded-pill px-2 py-1 ms-1">
            <i class="bi bi-lock-fill me-1"></i>Locked
          </span>
        <?php endif; ?>
        <?php if ($user['must_change_password']): ?>
          <div class="mt-2">
            <span class="badge bg-warning text-dark small">
              <i class="bi bi-key me-1"></i>Must Change Password
            </span>
          </div>
        <?php endif; ?>
        <?php if ($isSelf): ?>
          <div class="mt-2">
            <span class="badge bg-primary small">This is you</span>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Info List -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header fw-semibold small">
        <i class="bi bi-person-vcard me-2 text-primary"></i>Account Information
      </div>
      <ul class="list-group list-group-flush">
        <?php
        $infoRows = [
          ['Employee ID',    $user['employee_id']    ?? '—',  'bi-badge-identity'],
          ['Gender',         $genderLabel,                     'bi-gender-ambiguous'],
          ['Email',          $user['email']          ?? '—',  'bi-envelope'],
          ['Phone',          $user['phone']          ?? '—',  'bi-telephone'],
          ['Failed Logins',  (string) ($user['failed_login_attempts'] ?? 0), 'bi-exclamation-triangle'],
          ['Last Login',     $user['last_login_at'] ? \AAM\Helpers\DateHelper::timeAgo($user['last_login_at']) : 'Never', 'bi-clock'],
          ['Password Changed', $user['password_changed_at'] ? date_display($user['password_changed_at']) : 'Never', 'bi-key'],
          ['Created',        date_display($user['created_at'] ?? null), 'bi-calendar'],
        ];
        foreach ($infoRows as [$lbl, $val, $icon]): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
          <span class="text-muted small"><i class="bi <?= $icon ?> me-2"></i><?= $lbl ?></span>
          <span class="small fw-semibold text-end" style="max-width:160px;word-break:break-all;"><?= e($val) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <!-- Location Assignment -->
    <?php if (!empty($user['location'])): ?>
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header fw-semibold small">
        <i class="bi bi-geo-alt-fill me-2 text-success"></i>Location Assignment
      </div>
      <div class="card-body py-2 px-3">
        <div class="d-flex align-items-center gap-2">
          <?php
          $locIcon = match($user['location']['location_type'] ?? '') {
              'farm'      => 'bi-tree-fill',
              'warehouse' => 'bi-building-fill',
              'shop'      => 'bi-shop-window',
              default     => 'bi-geo-alt',
          };
          ?>
          <i class="bi <?= $locIcon ?> text-success fs-5"></i>
          <div>
            <div class="fw-semibold small"><?= e($user['location']['location_name'] ?? '—') ?></div>
            <div class="text-muted" style="font-size:.72rem;">
              <?= e(ucfirst($user['location']['location_type'] ?? '')) ?>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Admin Actions -->
    <?php if (!$isSelf): ?>
    <div class="card border-0 shadow-sm">
      <div class="card-header fw-semibold small">
        <i class="bi bi-lightning me-2 text-warning"></i>Admin Actions
      </div>
      <div class="card-body d-grid gap-2">

        <a href="<?= url('admin/users/' . $uid . '/edit') ?>" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-pencil-fill me-1"></i>Edit Profile
        </a>

        <?php if ($isLocked): ?>
        <form method="POST" action="<?= url('admin/users/' . $uid . '/unlock') ?>">
          <?php csrf_input(); ?>
          <button type="submit" class="btn btn-outline-warning btn-sm w-100"
                  onclick="return confirm('Unlock this account?')">
            <i class="bi bi-unlock-fill me-1"></i>Unlock Account
          </button>
        </form>
        <?php endif; ?>

        <form method="POST" action="<?= url('admin/users/' . $uid . '/toggle') ?>">
          <?php csrf_input(); ?>
          <button type="submit"
                  class="btn btn-outline-<?= $isActive ? 'danger' : 'success' ?> btn-sm w-100"
                  onclick="return confirm('<?= $isActive ? 'Deactivate this account?' : 'Activate this account?' ?>')">
            <i class="bi bi-person-<?= $isActive ? 'slash' : 'check' ?> me-1"></i>
            <?= $isActive ? 'Deactivate' : 'Activate' ?>
          </button>
        </form>

        <button type="button" class="btn btn-outline-warning btn-sm" id="btnResetPw">
          <i class="bi bi-key-fill me-1"></i>Reset Password
        </button>

        <?php if ($user['account_status'] !== 'deleted'): ?>
        <form method="POST" action="<?= url('admin/users/' . $uid . '/delete') ?>">
          <?php csrf_input(); ?>
          <button type="submit" class="btn btn-outline-danger btn-sm w-100"
                  onclick="return confirm('Delete this user account? This can be undone later.')">
            <i class="bi bi-trash3-fill me-1"></i>Delete Account
          </button>
        </form>
        <?php else: ?>
        <form method="POST" action="<?= url('admin/users/' . $uid . '/restore') ?>">
          <?php csrf_input(); ?>
          <button type="submit" class="btn btn-outline-success btn-sm w-100"
                  onclick="return confirm('Restore this account?')">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Restore Account
          </button>
        </form>
        <?php endif; ?>

      </div>
    </div>
    <?php endif; ?>

  </div><!-- /.col-lg-4 -->

  <!-- ── Right Column ─────────────────────────────────────── -->
  <div class="col-lg-8">

    <!-- Permissions Card -->
    <?php if (!empty($user['permissions'])): ?>
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold small">
          <i class="bi bi-shield-check me-2 text-success"></i>Role Permissions
        </span>
        <span class="badge bg-success-subtle text-success">
          <?= e($user['role_display_name'] ?? $user['role_name']) ?>
        </span>
      </div>
      <div class="card-body">
        <div class="row g-2">
          <?php foreach ($user['permissions'] as $module => $perms): ?>
          <div class="col-md-6">
            <div class="border rounded p-2 h-100">
              <div class="fw-semibold small mb-2 text-primary border-bottom pb-1">
                <i class="bi bi-puzzle me-1"></i><?= e(ucwords(str_replace('_', ' ', $module))) ?>
              </div>
              <?php foreach ($perms as $perm): ?>
              <div class="d-flex align-items-center gap-1 mb-1">
                <i class="bi bi-check-circle-fill text-success" style="font-size:.7rem;"></i>
                <span style="font-size:.72rem;"><?= e($perm['description'] ?? $perm['code']) ?></span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header fw-semibold small">
        <i class="bi bi-shield-check me-2 text-success"></i>Role Permissions
      </div>
      <div class="card-body text-center py-4 text-muted">
        <i class="bi bi-shield-x fs-3 d-block mb-2 opacity-30"></i>
        <small>No permissions configured for this role.</small>
      </div>
    </div>
    <?php endif; ?>

    <!-- Recent Activity -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header fw-semibold small">
        <i class="bi bi-activity me-2 text-primary"></i>Recent Activity
        <span class="badge bg-primary-subtle text-primary ms-2"><?= count($recentActivities) ?></span>
      </div>
      <div class="card-body p-0">
        <?php if (empty($recentActivities)): ?>
          <div class="text-center py-4 text-muted">
            <i class="bi bi-inbox fs-3 d-block mb-2 opacity-25"></i>
            <small>No activity recorded yet.</small>
          </div>
        <?php else: ?>
          <div class="activity-feed">
            <?php foreach ($recentActivities as $act):
              [$actIcon, $actColor] = $actionMeta[$act['action']] ?? ['bi-activity', 'secondary'];
            ?>
            <div class="d-flex align-items-start gap-3 px-3 py-2 border-bottom">
              <div class="rounded-circle bg-<?= $actColor ?> bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
                   style="width:32px;height:32px;">
                <i class="bi <?= $actIcon ?> text-<?= $actColor ?>" style="font-size:.8rem;"></i>
              </div>
              <div class="flex-grow-1">
                <div class="small fw-semibold"><?= e(\AAM\Helpers\StringHelper::truncate($act['description'], 80)) ?></div>
                <div class="text-muted" style="font-size:.7rem;">
                  <?= e($act['ip_address'] ?? '') ?>
                </div>
              </div>
              <div class="text-muted text-end flex-shrink-0" style="font-size:.7rem;min-width:80px;">
                <?= \AAM\Helpers\DateHelper::timeAgo($act['created_at']) ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Login History -->
    <div class="card border-0 shadow-sm">
      <div class="card-header fw-semibold small">
        <i class="bi bi-journal-text me-2 text-primary"></i>Login History
        <span class="badge bg-primary-subtle text-primary ms-2"><?= count($loginHistory) ?></span>
      </div>
      <div class="card-body p-0">
        <?php if (empty($loginHistory)): ?>
          <div class="text-center py-4 text-muted">
            <i class="bi bi-clock fs-3 d-block mb-2 opacity-25"></i>
            <small>No login history yet.</small>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Result</th>
                  <th>Date &amp; Time</th>
                  <th>Browser</th>
                  <th>OS</th>
                  <th>Device</th>
                  <th>IP Address</th>
                  <th>Reason</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($loginHistory as $entry): ?>
                <tr>
                  <td class="ps-3">
                    <?php if ($entry['was_successful']): ?>
                      <span class="badge bg-success rounded-pill">
                        <i class="bi bi-check-lg me-1"></i>Success
                      </span>
                    <?php else: ?>
                      <span class="badge bg-danger rounded-pill">
                        <i class="bi bi-x-lg me-1"></i>Failed
                      </span>
                    <?php endif; ?>
                  </td>
                  <td class="small"><?= datetime_display($entry['attempted_at'] ?? null) ?></td>
                  <td class="small"><?= e($entry['browser'] ?? '—') ?></td>
                  <td class="small"><?= e($entry['os_name'] ?? '—') ?></td>
                  <td class="small">
                    <?php
                    $devIcon = match($entry['device_type'] ?? 'desktop') {
                        'mobile' => 'bi-phone',
                        'tablet' => 'bi-tablet',
                        default  => 'bi-laptop',
                    };
                    ?>
                    <i class="bi <?= $devIcon ?> me-1 text-muted"></i><?= e($entry['device_type'] ?? 'desktop') ?>
                  </td>
                  <td class="small text-muted"><?= e($entry['ip_address'] ?? '—') ?></td>
                  <td class="small text-muted">
                    <?= $entry['failure_reason'] ? e($entry['failure_reason']) : '<span class="text-success">—</span>' ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /.col-lg-8 -->
</div><!-- /.row -->

<!-- Password Reset Result Modal -->
<div class="modal fade" id="resetResultModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title fw-bold">
          <i class="bi bi-key-fill me-2"></i>Password Reset
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">New temporary password for <strong><?= e($user['username']) ?></strong>:</p>
        <div class="input-group">
          <input type="text" class="form-control fw-bold font-monospace" id="tempPwField" readonly>
          <button class="btn btn-outline-secondary" type="button" id="copyTempPw">
            <i class="bi bi-clipboard"></i>
          </button>
        </div>
        <div class="alert alert-warning mt-3 py-2 small">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          Share securely. User must change on next login.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php $view->section('scripts'); ?>
<script>
(function () {
  'use strict';

  var csrfToken  = document.querySelector('meta[name="csrf-token"]')
                     ? document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                     : '';
  var resetModal = new bootstrap.Modal(document.getElementById('resetResultModal'));
  var tempPwField = document.getElementById('tempPwField');
  var btnResetPw  = document.getElementById('btnResetPw');
  var copyBtn     = document.getElementById('copyTempPw');

  if (btnResetPw) {
    btnResetPw.addEventListener('click', function () {
      if (!confirm('Reset password for "<?= e($user['username']) ?>"? A new temporary password will be generated.')) return;
      var fd = new FormData();
      fd.append('_token', csrfToken);
      fetch('<?= url('admin/users/' . $uid . '/reset') ?>', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
      })
      .then(function(r) { return r.json(); })
      .then(function(resp) {
        if (resp.success && resp.data && resp.data.temp_password) {
          tempPwField.value = resp.data.temp_password;
          resetModal.show();
        } else {
          alert(resp.message || 'Reset failed.');
        }
      })
      .catch(function () { alert('Network error.'); });
    });
  }

  if (copyBtn) {
    copyBtn.addEventListener('click', function () {
      navigator.clipboard.writeText(tempPwField.value).then(function () {
        copyBtn.innerHTML = '<i class="bi bi-clipboard-check text-success"></i>';
        setTimeout(function () { copyBtn.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 2000);
      });
    });
  }
})();
</script>
<?php $view->endSection(); ?>
