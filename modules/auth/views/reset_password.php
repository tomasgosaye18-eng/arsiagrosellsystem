<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 7
 * View     : modules/auth/views/reset_password.php
 * Layout   : templates/minimal.php
 * Purpose  : Reset password form — user enters new password
 *            using a valid reset token.
 * =========================================================
 *
 * @var \AAM\Core\View $view
 * @var string         $token     Raw reset token from URL
 * @var string         $username  Username of the account being reset
 * @var string         $csrfToken
 */
declare(strict_types=1);
?>

<div class="text-center mb-4">
    <i class="bi bi-shield-lock fs-2 text-primary d-block mb-2" aria-hidden="true"></i>
    <h2 class="h5 fw-bold mb-1">Set New Password</h2>
    <p class="text-muted small mb-0">
        Setting new password for account:
        <strong><?= e($username) ?></strong>
    </p>
</div>

<form method="POST"
      action="<?= url('reset-password/' . urlencode($token)) ?>"
      novalidate
      data-validate="true"
      autocomplete="off">

    <?php csrf_input(); ?>

    <!-- ── New Password ─────────────────────────────────── -->
    <div class="mb-1">
        <label for="new_password" class="form-label fw-semibold">New Password</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0">
                <i class="bi bi-key text-muted" aria-hidden="true"></i>
            </span>
            <input type="password"
                   class="form-control border-start-0"
                   id="new_password"
                   name="new_password"
                   placeholder="Enter new password"
                   autocomplete="new-password"
                   data-rules="required|password_strength"
                   data-label="New password"
                   autofocus
                   required>
            <button class="btn btn-outline-secondary border-start-0 toggle-pw"
                    type="button"
                    data-target="new_password"
                    aria-label="Show/hide password">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <!-- ── Strength Meter ───────────────────────────────── -->
    <div class="mb-3">
        <div class="progress mt-2" style="height:5px;" role="progressbar"
             aria-label="Password strength" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar" id="strengthBar" style="width:0%"></div>
        </div>
        <small class="text-muted mt-1 d-block" id="strengthLabel" aria-live="polite">
            Min 8 chars, uppercase, lowercase, number required.
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
                   class="form-control border-start-0"
                   id="confirm_password"
                   name="confirm_password"
                   placeholder="Re-enter new password"
                   autocomplete="new-password"
                   data-rules="required|confirmed"
                   data-label="Confirm password"
                   required>
            <button class="btn btn-outline-secondary border-start-0 toggle-pw"
                    type="button"
                    data-target="confirm_password"
                    aria-label="Show/hide password">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="d-grid gap-2">
        <button type="submit" class="btn btn-primary btn-lg fw-semibold">
            <i class="bi bi-check2-circle me-2" aria-hidden="true"></i>
            Reset Password
        </button>
        <a href="<?= url('login') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Cancel
        </a>
    </div>

</form>

<?php $view->section('scripts'); ?>
<script>
document.querySelectorAll('.toggle-pw').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var inp  = document.getElementById(this.getAttribute('data-target'));
        var icon = this.querySelector('i');
        if (inp.type === 'password') { inp.type = 'text';     icon.className = 'bi bi-eye-slash'; }
        else                         { inp.type = 'password'; icon.className = 'bi bi-eye'; }
    });
});

document.getElementById('new_password').addEventListener('input', function () {
    var pw = this.value, score = 0, bar = document.getElementById('strengthBar'),
        lbl = document.getElementById('strengthLabel'), miss = [];
    if (pw.length >= 8)    { score++; } else { miss.push('8+ chars'); }
    if (/[A-Z]/.test(pw))  { score++; } else { miss.push('uppercase'); }
    if (/[a-z]/.test(pw))  { score++; } else { miss.push('lowercase'); }
    if (/[0-9]/.test(pw))  { score++; } else { miss.push('number'); }
    var cls = ['bg-danger','bg-danger','bg-warning','bg-info','bg-success'][score];
    bar.style.width = (score * 25) + '%';
    bar.className = 'progress-bar ' + cls;
    lbl.textContent = miss.length ? 'Needs: ' + miss.join(', ') + '.' : '✓ Password looks good!';
    lbl.className = miss.length ? 'mt-1 d-block small text-muted' : 'mt-1 d-block small text-success';
});

document.getElementById('confirm_password').addEventListener('input', function () {
    var match = this.value === document.getElementById('new_password').value;
    this.classList.toggle('is-valid',   match && this.value.length > 0);
    this.classList.toggle('is-invalid', !match && this.value.length > 0);
});
</script>
<?php $view->endSection(); ?>
