<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 8A
 * =========================================================
 * File     : modules/admin/views/users/edit.php
 * Layout   : templates/master.php
 * Purpose  : Edit user — full Phase 8A form with all new
 *            profile fields, location assignment, status
 *            management inline controls, and quick actions.
 * Dependencies: UserController, UserService.
 * =========================================================
 *
 * @var \AAM\Core\View      $view
 * @var array<string,mixed> $user
 * @var array<int,array>    $roles
 * @var array               $farms
 * @var array               $warehouses
 * @var array               $shops
 * @var array|null          $locationAssignment
 */
declare(strict_types=1);

$valErrors = \AAM\Core\SessionManager::get('validation_errors', []);
$old       = \AAM\Core\SessionManager::get('old_input', []);
\AAM\Core\SessionManager::delete('validation_errors');
\AAM\Core\SessionManager::delete('old_input');

$v  = fn(string $k) => e($old[$k] ?? $user[$k] ?? '');
$ie = fn(string $k) => !empty($valErrors[$k]) ? 'is-invalid' : '';
$fe = fn(string $k) => !empty($valErrors[$k])
    ? '<div class="invalid-feedback d-block" role="alert">' . e($valErrors[$k][0]) . '</div>'
    : '';

$uid          = (int) $user['id'];
$isSelf       = $uid === \AAM\Core\SessionManager::userId();
$isActive     = (int) $user['is_active'] === 1 && $user['account_status'] === 'active';
$isLocked     = (bool) (int) ($user['is_locked'] ?? 0);
$avatarUrl    = \AAM\Helpers\UploadHelper::profileImageUrl($user['profile_photo'] ?? null);

$currentLocType = $locationAssignment['location_type'] ?? '';
$currentLocId   = (int) ($locationAssignment['location_id'] ?? 0);

$statusColors = [
    'active'    => 'success',
    'inactive'  => 'secondary',
    'suspended' => 'danger',
    'locked'    => 'warning text-dark',
    'pending'   => 'info',
    'deleted'   => 'dark',
];
$statusColor = $statusColors[$user['account_status'] ?? 'active'] ?? 'secondary';
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-person-gear me-2 text-primary"></i>Edit User
    </h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('admin/dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= url('admin/users') ?>">Users</a></li>
        <li class="breadcrumb-item"><a href="<?= url('admin/users/' . $uid) ?>"><?= e($user['username']) ?></a></li>
        <li class="breadcrumb-item active">Edit</li>
      </ol>
    </nav>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('admin/users/' . $uid) ?>" class="btn btn-outline-info btn-sm">
      <i class="bi bi-eye me-1"></i>View
    </a>
    <a href="<?= url('admin/users') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>
  </div>
</div>

<form method="POST" action="<?= url('admin/users/' . $uid) ?>" novalidate id="editUserForm">
  <?php csrf_input(); ?>

  <div class="row g-4">

    <!-- ── Left Panel ─────────────────────────────────────── -->
    <div class="col-lg-3">

      <!-- Avatar Card -->
      <div class="card border-0 shadow-sm text-center mb-3">
        <div class="card-body py-4">
          <img src="<?= e($avatarUrl) ?>"
               alt="Profile photo of <?= e($user['first_name']) ?>"
               class="rounded-circle shadow mb-3"
               style="width:90px;height:90px;object-fit:cover;border:3px solid #dee2e6;"
               onerror="this.src='<?= asset('images/no-avatar.svg') ?>'">
          <h6 class="fw-bold mb-1">
            <?= e(trim($user['first_name'] . ' ' . $user['last_name'])) ?>
          </h6>
          <div class="text-muted small mb-2">@<?= e($user['username']) ?></div>
          <span class="badge bg-<?= $statusColor ?> rounded-pill">
            <?= e(ucfirst($user['account_status'] ?? 'active')) ?>
          </span>
          <?php if ($isLocked): ?>
            <span class="badge bg-danger rounded-pill ms-1">
              <i class="bi bi-lock-fill"></i> Locked
            </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Account Settings -->
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header fw-semibold small">
          <i class="bi bi-shield-lock me-2 text-primary"></i>Account Settings
        </div>
        <div class="card-body">

          <!-- Role -->
          <div class="mb-3">
            <label for="role_id" class="form-label small fw-semibold">
              Role <span class="text-danger">*</span>
            </label>
            <select class="form-select form-select-sm" id="role_id" name="role_id" required>
              <?php foreach ($roles as $r): ?>
              <option value="<?= (int)$r['id'] ?>"
                <?= (int)($old['role_id'] ?? $user['role_id']) === (int)$r['id'] ? 'selected' : '' ?>>
                <?= e($r['display_name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Username (read-only) -->
          <div class="mb-3">
            <label class="form-label small fw-semibold">Username</label>
            <div class="input-group input-group-sm">
              <span class="input-group-text bg-light"><i class="bi bi-at text-muted"></i></span>
              <input type="text" class="form-control bg-light"
                     value="<?= e($user['username']) ?>" readonly>
            </div>
            <small class="text-muted">Cannot be changed.</small>
          </div>

          <!-- Location Type -->
          <div class="mb-3">
            <label for="location_type" class="form-label small fw-semibold">Location</label>
            <select class="form-select form-select-sm" id="location_type" name="location_type">
              <option value="">— No Assignment —</option>
              <?php if (!empty($farms)): ?>
              <option value="farm" <?= ($old['location_type'] ?? $currentLocType) === 'farm' ? 'selected' : '' ?>>Farm</option>
              <?php endif; ?>
              <?php if (!empty($warehouses)): ?>
              <option value="warehouse" <?= ($old['location_type'] ?? $currentLocType) === 'warehouse' ? 'selected' : '' ?>>Warehouse</option>
              <?php endif; ?>
              <?php if (!empty($shops)): ?>
              <option value="shop" <?= ($old['location_type'] ?? $currentLocType) === 'shop' ? 'selected' : '' ?>>Shop</option>
              <?php endif; ?>
            </select>
          </div>

          <div id="locationSelectWrap" style="display:none;">
            <label for="location_id" class="form-label small fw-semibold">Select Location</label>
            <select class="form-select form-select-sm" id="location_id" name="location_id">
              <option value="">— Select —</option>
            </select>
          </div>

        </div>
      </div>

      <!-- Quick Actions (admin cannot perform on self) -->
      <?php if (!$isSelf): ?>
      <div class="card border-0 shadow-sm">
        <div class="card-header fw-semibold small">
          <i class="bi bi-lightning me-2 text-warning"></i>Quick Actions
        </div>
        <div class="card-body d-grid gap-2">

          <?php if ($isLocked): ?>
          <form method="POST" action="<?= url('admin/users/' . $uid . '/unlock') ?>">
            <?php csrf_input(); ?>
            <button type="submit" class="btn btn-outline-warning btn-sm w-100 btn-confirm"
                    data-confirm="Unlock this user's account?">
              <i class="bi bi-unlock-fill me-1"></i>Unlock Account
            </button>
          </form>
          <?php endif; ?>

          <form method="POST" action="<?= url('admin/users/' . $uid . '/toggle') ?>">
            <?php csrf_input(); ?>
            <button type="submit"
                    class="btn btn-outline-<?= $isActive ? 'danger' : 'success' ?> btn-sm w-100 btn-confirm"
                    data-confirm="<?= $isActive ? 'Deactivate this account? The user will be unable to log in.' : 'Activate this account?' ?>">
              <i class="bi bi-person-<?= $isActive ? 'slash' : 'check' ?> me-1"></i>
              <?= $isActive ? 'Deactivate' : 'Activate' ?>
            </button>
          </form>

          <form method="POST" action="<?= url('admin/users/' . $uid . '/reset') ?>" id="resetPwForm">
            <?php csrf_input(); ?>
            <button type="button" class="btn btn-outline-warning btn-sm w-100" id="btnResetPw">
              <i class="bi bi-key-fill me-1"></i>Reset Password
            </button>
          </form>

        </div>
      </div>
      <?php endif; ?>

    </div><!-- /.col-lg-3 -->

    <!-- ── Right Panel ────────────────────────────────────── -->
    <div class="col-lg-9">
      <div class="card border-0 shadow-sm">
        <div class="card-header fw-semibold">
          <i class="bi bi-person-vcard me-2 text-primary"></i>Personal Information
        </div>
        <div class="card-body p-4">

          <div class="row g-3">

            <!-- Employee ID -->
            <div class="col-md-4">
              <label for="employee_id" class="form-label small fw-semibold">Employee ID</label>
              <input type="text"
                     class="form-control form-control-sm <?= $ie('employee_id') ?>"
                     id="employee_id" name="employee_id"
                     value="<?= $v('employee_id') ?>"
                     maxlength="30">
              <?= $fe('employee_id') ?>
            </div>

            <!-- Gender -->
            <div class="col-md-4">
              <label for="gender" class="form-label small fw-semibold">Gender</label>
              <select class="form-select form-select-sm <?= $ie('gender') ?>"
                      id="gender" name="gender">
                <option value="">— Not specified —</option>
                <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other', 'prefer_not_to_say' => 'Prefer not to say'] as $gVal => $gLabel): ?>
                <option value="<?= $gVal ?>" <?= ($old['gender'] ?? $user['gender'] ?? '') === $gVal ? 'selected' : '' ?>>
                  <?= $gLabel ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Member Since (display only) -->
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Member Since</label>
              <div class="form-control form-control-sm bg-light">
                <?= date_display($user['created_at'] ?? null) ?>
              </div>
            </div>

            <!-- First Name -->
            <div class="col-md-4">
              <label for="first_name" class="form-label small fw-semibold">
                First Name <span class="text-danger">*</span>
              </label>
              <input type="text"
                     class="form-control form-control-sm <?= $ie('first_name') ?>"
                     id="first_name" name="first_name"
                     value="<?= $v('first_name') ?>"
                     required autofocus>
              <?= $fe('first_name') ?>
            </div>

            <!-- Middle Name -->
            <div class="col-md-4">
              <label for="middle_name" class="form-label small fw-semibold">Middle Name</label>
              <input type="text"
                     class="form-control form-control-sm <?= $ie('middle_name') ?>"
                     id="middle_name" name="middle_name"
                     value="<?= $v('middle_name') ?>">
              <?= $fe('middle_name') ?>
            </div>

            <!-- Last Name -->
            <div class="col-md-4">
              <label for="last_name" class="form-label small fw-semibold">
                Last Name <span class="text-danger">*</span>
              </label>
              <input type="text"
                     class="form-control form-control-sm <?= $ie('last_name') ?>"
                     id="last_name" name="last_name"
                     value="<?= $v('last_name') ?>"
                     required>
              <?= $fe('last_name') ?>
            </div>

            <!-- Email -->
            <div class="col-md-6">
              <label for="email" class="form-label small fw-semibold">Email Address</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text bg-light"><i class="bi bi-envelope text-muted"></i></span>
                <input type="email"
                       class="form-control <?= $ie('email') ?>"
                       id="email" name="email"
                       value="<?= $v('email') ?>"
                       placeholder="user@arsi.edu.et">
              </div>
              <?= $fe('email') ?>
            </div>

            <!-- Phone -->
            <div class="col-md-6">
              <label for="phone" class="form-label small fw-semibold">Phone Number</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text bg-light"><i class="bi bi-telephone text-muted"></i></span>
                <input type="tel"
                       class="form-control <?= $ie('phone') ?>"
                       id="phone" name="phone"
                       value="<?= $v('phone') ?>"
                       placeholder="e.g. 0912345678">
              </div>
              <?= $fe('phone') ?>
            </div>

            <!-- Account Status (read-only display) -->
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Account Status</label>
              <div class="form-control form-control-sm bg-light d-flex align-items-center gap-2">
                <span class="badge bg-<?= $statusColor ?>">
                  <?= e(ucfirst($user['account_status'] ?? 'active')) ?>
                </span>
                <?php if ($isLocked): ?>
                  <span class="badge bg-danger ms-1">
                    <i class="bi bi-lock-fill me-1"></i>Locked (<?= (int) $user['failed_login_attempts'] ?> fails)
                  </span>
                <?php endif; ?>
              </div>
            </div>

            <!-- Last Login (display only) -->
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Last Login</label>
              <div class="form-control form-control-sm bg-light">
                <?= $user['last_login_at'] ? datetime_display($user['last_login_at']) : 'Never logged in' ?>
              </div>
            </div>

          </div><!-- /.row -->

          <hr class="mt-4">

          <div class="d-flex justify-content-end gap-2">
            <a href="<?= url('admin/users/' . $uid) ?>" class="btn btn-outline-secondary btn-sm">
              <i class="bi bi-x-lg me-1"></i>Cancel
            </a>
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="bi bi-check-lg me-1"></i>Save Changes
            </button>
          </div>

        </div>
      </div>
    </div>

  </div><!-- /.row -->
</form>

<!-- Password Reset Result Modal -->
<div class="modal fade" id="resetResultModal" tabindex="-1" aria-labelledby="resetResultLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title fw-bold" id="resetResultLabel">
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
          Share this password securely. The user must change it on next login.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php
$locData = [
    'farm'      => array_map(fn($f) => ['id' => (int)$f['id'], 'name' => $f['name']], $farms ?? []),
    'warehouse' => array_map(fn($w) => ['id' => (int)$w['id'], 'name' => $w['name']], $warehouses ?? []),
    'shop'      => array_map(fn($s) => ['id' => (int)$s['id'], 'name' => $s['name']], $shops ?? []),
];
?>

<?php $view->section('scripts'); ?>
<script>
(function () {
  'use strict';

  var locData         = <?= json_encode($locData, JSON_HEX_TAG) ?>;
  var currentLocType  = <?= json_encode($currentLocType) ?>;
  var currentLocId    = <?= (int) $currentLocId ?>;
  var csrfToken       = document.querySelector('meta[name="csrf-token"]')
                          ? document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                          : '';

  // ── Location dropdown ─────────────────────────────────────
  var locTypeEl  = document.getElementById('location_type');
  var locWrap    = document.getElementById('locationSelectWrap');
  var locSelectEl = document.getElementById('location_id');

  function populateLocations(type, selectedId) {
    var items = locData[type] || [];
    locSelectEl.innerHTML = '<option value="">— Select —</option>';
    items.forEach(function (item) {
      var opt = document.createElement('option');
      opt.value       = item.id;
      opt.textContent = item.name;
      if (item.id === selectedId) opt.selected = true;
      locSelectEl.appendChild(opt);
    });
    locWrap.style.display = items.length ? 'block' : 'none';
  }

  // Pre-populate on load
  if (currentLocType) {
    populateLocations(currentLocType, currentLocId);
  }

  locTypeEl.addEventListener('change', function () {
    populateLocations(this.value, 0);
  });

  // ── Confirm-required forms ────────────────────────────────
  document.querySelectorAll('.btn-confirm').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      if (!confirm(this.dataset.confirm || 'Are you sure?')) {
        e.preventDefault();
      }
    });
  });

  // ── Password Reset (AJAX) ─────────────────────────────────
  var resetModal = new bootstrap.Modal(document.getElementById('resetResultModal'));
  var tempPwField = document.getElementById('tempPwField');

  var btnResetPw = document.getElementById('btnResetPw');
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
      .catch(function() { alert('Network error.'); });
    });
  }

  var copyBtn = document.getElementById('copyTempPw');
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
