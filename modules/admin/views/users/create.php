<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 8A
 * =========================================================
 * File     : modules/admin/views/users/create.php
 * Layout   : templates/master.php
 * Purpose  : Create new user — full Phase 8A form with
 *            employee ID, middle name, gender, email,
 *            location assignment, real-time validation,
 *            password strength meter, and photo preview.
 * Dependencies: UserController, UserService.
 * =========================================================
 *
 * @var \AAM\Core\View   $view
 * @var array            $roles
 * @var array            $farms
 * @var array            $warehouses
 * @var array            $shops
 * @var array            $valErrors  (from session)
 * @var array            $oldInput   (from session)
 */
declare(strict_types=1);

$valErrors = \AAM\Core\SessionManager::get('validation_errors', []);
$old       = \AAM\Core\SessionManager::get('old_input', []);
\AAM\Core\SessionManager::delete('validation_errors');
\AAM\Core\SessionManager::delete('old_input');

$o  = fn(string $k, string $d = '') => e($old[$k] ?? $d);
$ie = fn(string $k) => !empty($valErrors[$k]) ? 'is-invalid' : '';
$fe = fn(string $k) => !empty($valErrors[$k])
    ? '<div class="invalid-feedback d-block" role="alert">' . e($valErrors[$k][0]) . '</div>'
    : '';
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-person-plus-fill me-2 text-primary"></i>Create New User
    </h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('admin/dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= url('admin/users') ?>">Users</a></li>
        <li class="breadcrumb-item active">Create</li>
      </ol>
    </nav>
  </div>
  <a href="<?= url('admin/users') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Back
  </a>
</div>

<form method="POST" action="<?= url('admin/users') ?>" novalidate id="createUserForm">
  <?php csrf_input(); ?>

  <div class="row g-4">

    <!-- ── Left: Profile Photo Preview ───────────────────── -->
    <div class="col-lg-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-4">
          <img src="<?= asset('images/no-avatar.svg') ?>"
               id="photoPreview"
               alt="Photo preview"
               class="rounded-circle mb-3 shadow-sm"
               style="width:100px;height:100px;object-fit:cover;border:3px solid #dee2e6;">
          <div class="text-muted small">Photo will be set after creation or through profile edit.</div>
        </div>
      </div>

      <!-- Account Settings Card -->
      <div class="card border-0 shadow-sm mt-3">
        <div class="card-header fw-semibold small">
          <i class="bi bi-shield-lock me-2 text-primary"></i>Account Settings
        </div>
        <div class="card-body">

          <!-- Role -->
          <div class="mb-3">
            <label for="role_id" class="form-label small fw-semibold">
              Role <span class="text-danger">*</span>
            </label>
            <select class="form-select form-select-sm <?= $ie('role_id') ?>"
                    id="role_id" name="role_id" required>
              <option value="">— Select Role —</option>
              <?php foreach ($roles as $r): ?>
              <option value="<?= (int)$r['id'] ?>"
                <?= (int)($old['role_id'] ?? 0) === (int)$r['id'] ? 'selected' : '' ?>>
                <?= e($r['display_name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <?= $fe('role_id') ?>
          </div>

          <!-- Location Type -->
          <div class="mb-3">
            <label for="location_type" class="form-label small fw-semibold">Location Assignment</label>
            <select class="form-select form-select-sm" id="location_type" name="location_type">
              <option value="">— No Assignment —</option>
              <?php if (!empty($farms)): ?>
              <option value="farm" <?= ($old['location_type'] ?? '') === 'farm' ? 'selected' : '' ?>>Farm</option>
              <?php endif; ?>
              <?php if (!empty($warehouses)): ?>
              <option value="warehouse" <?= ($old['location_type'] ?? '') === 'warehouse' ? 'selected' : '' ?>>Warehouse</option>
              <?php endif; ?>
              <?php if (!empty($shops)): ?>
              <option value="shop" <?= ($old['location_type'] ?? '') === 'shop' ? 'selected' : '' ?>>Retail Shop</option>
              <?php endif; ?>
            </select>
          </div>

          <!-- Location Select (dynamic) -->
          <div id="locationSelectWrap" style="display:none;">
            <label for="location_id" class="form-label small fw-semibold">Select Location</label>
            <select class="form-select form-select-sm" id="location_id" name="location_id">
              <option value="">— Select —</option>
            </select>
          </div>

        </div>
      </div>
    </div>

    <!-- ── Right: Main Form ───────────────────────────────── -->
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
                     value="<?= $o('employee_id') ?>"
                     placeholder="e.g. AU-2024-001"
                     maxlength="30">
              <?= $fe('employee_id') ?>
              <small class="text-muted">Institutional staff ID (optional)</small>
            </div>

            <!-- Username -->
            <div class="col-md-4">
              <label for="username" class="form-label small fw-semibold">
                Username <span class="text-danger">*</span>
              </label>
              <div class="input-group input-group-sm">
                <span class="input-group-text bg-light"><i class="bi bi-at text-muted"></i></span>
                <input type="text"
                       class="form-control <?= $ie('username') ?>"
                       id="username" name="username"
                       value="<?= $o('username') ?>"
                       placeholder="e.g. abebe.bekele"
                       autocomplete="off" required autofocus>
              </div>
              <?= $fe('username') ?>
              <small class="text-muted">Must be unique.</small>
            </div>

            <!-- Gender -->
            <div class="col-md-4">
              <label for="gender" class="form-label small fw-semibold">Gender</label>
              <select class="form-select form-select-sm <?= $ie('gender') ?>"
                      id="gender" name="gender">
                <option value="">— Not specified —</option>
                <option value="male"              <?= ($old['gender'] ?? '') === 'male'              ? 'selected' : '' ?>>Male</option>
                <option value="female"            <?= ($old['gender'] ?? '') === 'female'            ? 'selected' : '' ?>>Female</option>
                <option value="other"             <?= ($old['gender'] ?? '') === 'other'             ? 'selected' : '' ?>>Other</option>
                <option value="prefer_not_to_say" <?= ($old['gender'] ?? '') === 'prefer_not_to_say' ? 'selected' : '' ?>>Prefer not to say</option>
              </select>
              <?= $fe('gender') ?>
            </div>

            <!-- First Name -->
            <div class="col-md-4">
              <label for="first_name" class="form-label small fw-semibold">
                First Name <span class="text-danger">*</span>
              </label>
              <input type="text"
                     class="form-control form-control-sm <?= $ie('first_name') ?>"
                     id="first_name" name="first_name"
                     value="<?= $o('first_name') ?>"
                     placeholder="Enter first name" required>
              <?= $fe('first_name') ?>
            </div>

            <!-- Middle Name -->
            <div class="col-md-4">
              <label for="middle_name" class="form-label small fw-semibold">Middle Name</label>
              <input type="text"
                     class="form-control form-control-sm <?= $ie('middle_name') ?>"
                     id="middle_name" name="middle_name"
                     value="<?= $o('middle_name') ?>"
                     placeholder="Optional">
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
                     value="<?= $o('last_name') ?>"
                     placeholder="Enter last name" required>
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
                       value="<?= $o('email') ?>"
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
                       value="<?= $o('phone') ?>"
                       placeholder="e.g. 0912345678">
              </div>
              <?= $fe('phone') ?>
              <small class="text-muted">Ethiopian format: 09/07XXXXXXXX or +251XXXXXXXXX</small>
            </div>

            <!-- Password -->
            <div class="col-md-6">
              <label for="password" class="form-label small fw-semibold">
                Temporary Password <span class="text-danger">*</span>
              </label>
              <div class="input-group input-group-sm">
                <input type="password"
                       class="form-control <?= $ie('password') ?>"
                       id="password" name="password"
                       placeholder="Min 8 chars, upper, lower, number"
                       autocomplete="new-password" required>
                <button class="btn btn-outline-secondary" type="button" id="togglePw" title="Show/hide">
                  <i class="bi bi-eye" id="togglePwIcon"></i>
                </button>
                <button class="btn btn-outline-secondary" type="button" id="genPw" title="Auto-generate">
                  <i class="bi bi-magic"></i>
                </button>
              </div>
              <?= $fe('password') ?>
              <!-- Strength meter -->
              <div class="progress mt-1" style="height:4px;" id="strengthBar" role="progressbar"
                   aria-label="Password strength">
                <div class="progress-bar" id="strengthProgress" style="width:0%;"></div>
              </div>
              <small id="strengthLabel" class="text-muted">Enter a password to check strength.</small>
            </div>

            <!-- Confirm Password -->
            <div class="col-md-6">
              <label for="pw_confirm" class="form-label small fw-semibold">Confirm Password</label>
              <div class="input-group input-group-sm">
                <input type="password"
                       class="form-control"
                       id="pw_confirm"
                       placeholder="Repeat password"
                       autocomplete="new-password">
                <span class="input-group-text bg-light" id="pwMatchIcon">
                  <i class="bi bi-question text-muted"></i>
                </span>
              </div>
              <div id="pwMatchFeedback" class="invalid-feedback" style="display:none;"></div>
            </div>

          </div><!-- /.row -->

          <hr class="mt-4">
          <div class="alert alert-info d-flex gap-2 py-2 small mb-3">
            <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
            <span>The user will be required to change this password on first login. Share it securely.</span>
          </div>

          <div class="d-flex justify-content-end gap-2">
            <a href="<?= url('admin/users') ?>" class="btn btn-outline-secondary">
              <i class="bi bi-x-lg me-1"></i>Cancel
            </a>
            <button type="submit" class="btn btn-primary" id="submitBtn">
              <i class="bi bi-person-plus-fill me-1"></i>Create User
            </button>
          </div>

        </div><!-- /.card-body -->
      </div><!-- /.card -->
    </div><!-- /.col-lg-9 -->

  </div><!-- /.row -->
</form>

<?php
// Build JS location data for dynamic dropdowns
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

  var locData = <?= json_encode($locData, JSON_HEX_TAG) ?>;

  // ── Location dynamic dropdown ───────────────────────────────
  var locType   = document.getElementById('location_type');
  var locWrap   = document.getElementById('locationSelectWrap');
  var locSelect = document.getElementById('location_id');

  locType.addEventListener('change', function () {
    var type   = this.value;
    var items  = locData[type] || [];
    locSelect.innerHTML = '<option value="">— Select —</option>';
    items.forEach(function (item) {
      var opt = document.createElement('option');
      opt.value       = item.id;
      opt.textContent = item.name;
      locSelect.appendChild(opt);
    });
    locWrap.style.display = items.length ? 'block' : 'none';
  });

  // ── Toggle password visibility ─────────────────────────────
  document.getElementById('togglePw').addEventListener('click', function () {
    var pw   = document.getElementById('password');
    var icon = document.getElementById('togglePwIcon');
    pw.type  = pw.type === 'password' ? 'text' : 'password';
    icon.className = pw.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
  });

  // ── Generate password ──────────────────────────────────────
  document.getElementById('genPw').addEventListener('click', function () {
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#';
    var arr   = new Uint8Array(14);
    window.crypto.getRandomValues(arr);
    var pw = 'A' + String.fromCharCode(97 + arr[0] % 26)
           + (arr[1] % 9 + 1)
           + Array.from(arr.slice(2, 14), function(b) { return chars[b % chars.length]; }).join('');
    var pwEl = document.getElementById('password');
    pwEl.value = pw;
    pwEl.type  = 'text';
    document.getElementById('togglePwIcon').className = 'bi bi-eye-slash';
    pwEl.dispatchEvent(new Event('input'));
    checkMatch();
  });

  // ── Password strength ──────────────────────────────────────
  var strengthProgress = document.getElementById('strengthProgress');
  var strengthLabel    = document.getElementById('strengthLabel');

  document.getElementById('password').addEventListener('input', function () {
    var pw     = this.value;
    var score  = 0;
    var labels = ['', 'Very Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'];
    var colors = ['', 'danger', 'danger', 'warning', 'info', 'success'];

    if (pw.length >= 8)              score++;
    if (/[A-Z]/.test(pw))            score++;
    if (/[a-z]/.test(pw))            score++;
    if (/[0-9]/.test(pw))            score++;
    if (/[^A-Za-z0-9]/.test(pw))     score++;

    strengthProgress.style.width = (score * 20) + '%';
    strengthProgress.className   = 'progress-bar bg-' + (colors[score] || 'secondary');
    strengthLabel.textContent    = pw.length ? (labels[score] || '') : 'Enter a password to check strength.';
    strengthLabel.className      = 'small text-' + (colors[score] || 'muted');
    checkMatch();
  });

  // ── Password match ─────────────────────────────────────────
  var pwConfirm   = document.getElementById('pw_confirm');
  var pwMatchIcon = document.getElementById('pwMatchIcon').querySelector('i');
  var pwMatchFb   = document.getElementById('pwMatchFeedback');

  function checkMatch() {
    var pw  = document.getElementById('password').value;
    var cpw = pwConfirm.value;
    if (!cpw) {
      pwMatchIcon.className = 'bi bi-question text-muted';
      pwConfirm.classList.remove('is-valid','is-invalid');
      pwMatchFb.style.display = 'none';
      return;
    }
    if (pw === cpw) {
      pwMatchIcon.className = 'bi bi-check-lg text-success';
      pwConfirm.classList.replace('is-invalid','is-valid');
      pwConfirm.classList.add('is-valid');
      pwMatchFb.style.display = 'none';
    } else {
      pwMatchIcon.className = 'bi bi-x-lg text-danger';
      pwConfirm.classList.replace('is-valid','is-invalid');
      pwConfirm.classList.add('is-invalid');
      pwMatchFb.textContent    = 'Passwords do not match.';
      pwMatchFb.style.display  = 'block';
    }
  }

  pwConfirm.addEventListener('input', checkMatch);

  // ── Client-side submit guard ───────────────────────────────
  document.getElementById('createUserForm').addEventListener('submit', function (e) {
    var pw  = document.getElementById('password').value;
    var cpw = pwConfirm.value;

    if (cpw && pw !== cpw) {
      e.preventDefault();
      checkMatch();
      pwConfirm.focus();
    }
  });

})();
</script>
<?php $view->endSection(); ?>
