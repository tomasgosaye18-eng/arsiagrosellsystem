<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 7
 * View     : modules/auth/views/change_password.php
 * Layout   : templates/minimal.php
 * Purpose  : Change password form — current + new + confirm.
 *            Used for both forced (first login) and voluntary
 *            password changes from the profile page.
 * =========================================================
 *
 * @var \AAM\Core\View $view
 * @var string         $csrfToken
 * @var bool           $forced   True = must change before proceeding
 */
declare(strict_types=1);

$valErrors = \AAM\Core\SessionManager::get('validation_errors', []);
\AAM\Core\SessionManager::delete('validation_errors');
?>

<?php if ($forced): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill flex-shrink-0" aria-hidden="true"></i>
    <div>
        <strong>Password change required.</strong>
        You must set a new password before you can access the system.
    </div>
</div>
<?php endif; ?>

<form method="POST"
      action="<?= url('change-password') ?>"
      novalidate
      data-validate="true"
      autocomplete="off">

    <?php csrf_input(); ?>

    <!-- ── Current Password ────────────────────────────── -->
    <div class="mb-3">
        <label for="current_password" class="form-label fw-semibold">
            Current Password
        </label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0">
                <i class="bi bi-lock text-muted" aria-hidden="true"></i>
            </span>
            <input type="password"
                   class="form-control border-start-0 <?= !empty($valErrors['current_password']) ? 'is-invalid' : '' ?>"
                   id="current_password"
                   name="current_password"
                   placeholder="Enter current password"
                   autocomplete="current-password"
                   data-rules="required"
                   data-label="Current password"
                   autofocus
                   required>
            <button class="btn btn-outline-secondary toggle-pw"
                    type="button" data-target="current_password" aria-label="Show/hide password">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </div>
        <?php if (!empty($valErrors['current_password'])): ?>
            <div class="invalid-feedback d-block" role="alert">
                <?= e($valErrors['current_password'][0]) ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── New Password ─────────────────────────────────── -->
    <div class="mb-1">
        <label for="new_password" class="form-label fw-semibold">New Password</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0">
                <i class="bi bi-key text-muted" aria-hidden="true"></i>
            </span>
            <input type="password"
                   class="form-control border-start-0 <?= !empty($valErrors['new_password']) ? 'is-invalid' : '' ?>"
                   id="new_password"
                   name="new_password"
                   placeholder="Enter new password"
                   autocomplete="new-password"
                   data-rules="required|password_strength"
                   data-label="New password"
                   required>
            <button class="btn btn-outline-secondary toggle-pw"
                    type="button" data-target="new_password" aria-label="Show/hide password">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </div>
        <?php if (!empty($valErrors['new_password'])): ?>
            <div class="invalid-feedback d-block" role="alert">
                <?= e($valErrors['new_password'][0]) ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── Password Strength Meter ──────────────────────── -->
    <div class="mb-3">
        <div class="progress mt-2" style="height: 5px;" role="progressbar"
             aria-label="Password strength" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar" id="strengthBar" style="width: 0%;"></div>
        </div>
        <small class="text-muted mt-1 d-block" id="strengthLabel" aria-live="polite">
            Password requirements: min 8 chars, uppercase, lowercase, number.
        </small>
    </div>

    <!-- ── Confirm Password ─────────────────────────────── -->
    <div class="mb-4">
        <label for="confirm_password" class="form-label fw-semibold">Confirm New Password</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0">
                <i class="bi bi-key-fill text-muted" aria-hidden="true"></i>
            </span>
            <input type="password"
                   class="form-control border-start-0 <?= !empty($valErrors['confirm_password']) ? 'is-invalid' : '' ?>"
                   id="confirm_password"
                   name="confirm_password"
                   placeholder="Re-enter new password"
                   autocomplete="new-password"
                   data-rules="required"
                   data-label="Confirm password"
                   required>
            <button class="btn btn-outline-secondary toggle-pw"
                    type="button" data-target="confirm_password" aria-label="Show/hide password">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </div>
        <?php if (!empty($valErrors['confirm_password'])): ?>
            <div class="invalid-feedback d-block" role="alert">
                <?= e($valErrors['confirm_password'][0]) ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── Submit ───────────────────────────────────────── -->
    <div class="d-grid gap-2">
        <button type="submit" class="btn btn-primary btn-lg fw-semibold">
            <i class="bi bi-check2-circle me-2" aria-hidden="true"></i>
            Change Password
        </button>
        <?php if (!$forced): ?>
        <a href="<?= url('profile') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-x-lg me-1" aria-hidden="true"></i>
            Cancel
        </a>
        <?php endif; ?>
    </div>

</form>

<?php $view->section('scripts'); ?>
<script>
// ── Toggle password visibility ────────────────────────────
document.querySelectorAll('.toggle-pw').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var id    = this.getAttribute('data-target');
        var input = document.getElementById(id);
        var icon  = this.querySelector('i');
        if (input.type === 'password') {
            input.type    = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            input.type    = 'password';
            icon.className = 'bi bi-eye';
        }
    });
});

// ── Strength meter ────────────────────────────────────────
document.getElementById('new_password').addEventListener('input', function () {
    var pw    = this.value;
    var bar   = document.getElementById('strengthBar');
    var label = document.getElementById('strengthLabel');
    var score = 0;
    var msgs  = [];

    if (pw.length >= 8)          { score++; } else { msgs.push('at least 8 characters'); }
    if (/[A-Z]/.test(pw))        { score++; } else { msgs.push('an uppercase letter'); }
    if (/[a-z]/.test(pw))        { score++; } else { msgs.push('a lowercase letter'); }
    if (/[0-9]/.test(pw))        { score++; } else { msgs.push('a number'); }

    var pct    = score * 25;
    var colors = ['bg-danger','bg-danger','bg-warning','bg-info','bg-success'];
    var labels = ['Very Weak','Weak','Fair','Good','Strong'];

    bar.style.width = pct + '%';
    bar.className   = 'progress-bar ' + (colors[score] || 'bg-danger');
    bar.closest('[role=progressbar]').setAttribute('aria-valuenow', pct);

    if (msgs.length === 0) {
        label.textContent = '✓ ' + labels[score] + ' password';
        label.className   = 'mt-1 d-block small text-success';
    } else {
        label.textContent = 'Needs: ' + msgs.join(', ') + '.';
        label.className   = 'mt-1 d-block small text-muted';
    }
});

// ── Confirm match indicator ───────────────────────────────
document.getElementById('confirm_password').addEventListener('input', function () {
    var newPw = document.getElementById('new_password').value;
    var match = this.value === newPw && this.value.length > 0;
    this.classList.toggle('is-valid',   match);
    this.classList.toggle('is-invalid', !match && this.value.length > 0);

    // Show/hide inline mismatch feedback
    var fb = document.getElementById('confirm-mismatch-msg');
    if (!fb) {
        fb = document.createElement('div');
        fb.id        = 'confirm-mismatch-msg';
        fb.className = 'invalid-feedback d-block';
        fb.setAttribute('role', 'alert');
        this.closest('.mb-4').appendChild(fb);
    }
    if (!match && this.value.length > 0) {
        fb.textContent = 'Passwords do not match.';
    } else {
        fb.textContent = '';
    }
});
</script>
<?php $view->endSection(); ?>
