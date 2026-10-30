<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 7
 * =========================================================
 * File     : modules/auth/views/forgot_password_sent.php
 * Layout   : templates/minimal.php
 * Purpose  : Shown after a reset token is generated.
 *            In v1 (no email), the admin copies the token
 *            to the user. Token displayed on screen.
 * Dependencies: PasswordController::forgot()
 * =========================================================
 *
 * @var \AAM\Core\View      $view
 * @var string|null         $token   Raw reset token (or null if user not found)
 * @var array|null          $user    User record (or null if not found)
 * @var string              $message
 */
declare(strict_types=1);
?>

<div class="text-center mb-4">
    <div class="bg-success bg-opacity-10 border border-success rounded-3 p-3 d-inline-block mb-3">
        <i class="bi bi-check-circle fs-2 text-success" aria-hidden="true"></i>
    </div>
    <h2 class="h5 fw-bold mb-1">Reset Token Generated</h2>
    <p class="text-muted small mb-0"><?= e($message) ?></p>
</div>

<?php if ($token && $user): ?>

    <!-- ── Token Display (Admin-to-User) ──────────────────── -->
    <div class="alert alert-warning d-flex gap-2 mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
        <div>
            <strong>Administrator Action Required:</strong>
            Copy this token and give it securely to the user
            <strong><?= e(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></strong>
            (username: <code><?= e($user['username'] ?? '') ?></code>).
        </div>
    </div>

    <div class="mb-4">
        <label class="form-label fw-semibold" for="resetToken">Reset Token</label>
        <div class="input-group">
            <input type="text"
                   class="form-control font-monospace"
                   id="resetToken"
                   value="<?= e($token) ?>"
                   readonly
                   aria-label="Reset token">
            <button class="btn btn-outline-secondary"
                    type="button"
                    id="copyTokenBtn"
                    title="Copy token to clipboard"
                    aria-label="Copy token">
                <i class="bi bi-clipboard" id="copyIcon"></i>
            </button>
        </div>
        <small class="text-muted d-flex align-items-center gap-1 mt-1">
            <i class="bi bi-clock text-warning"></i>
            Token expires in <strong>60 minutes</strong>.
        </small>
    </div>

    <div class="mb-4">
        <label class="form-label fw-semibold">Reset Link for User</label>
        <div class="input-group">
            <input type="text"
                   class="form-control font-monospace small"
                   id="resetLink"
                   value="<?= e(url('reset-password/' . $token)) ?>"
                   readonly
                   aria-label="Reset link">
            <button class="btn btn-outline-secondary"
                    type="button"
                    id="copyLinkBtn"
                    title="Copy link to clipboard"
                    aria-label="Copy link">
                <i class="bi bi-clipboard"></i>
            </button>
        </div>
        <small class="text-muted">Share this link with the user OR let them enter the token on the reset form.</small>
    </div>

    <div class="d-grid gap-2">
        <a href="<?= url('reset-password/' . $token) ?>"
           class="btn btn-primary">
            <i class="bi bi-arrow-right-circle me-1"></i>
            Go to Reset Password Form
        </a>
        <a href="<?= url('forgot-password') ?>" class="btn btn-outline-secondary">
            Generate Another Token
        </a>
    </div>

<?php else: ?>
    <!-- User not found — generic message (security: don't reveal) -->
    <div class="d-grid gap-2">
        <a href="<?= url('login') ?>" class="btn btn-primary">
            <i class="bi bi-arrow-left me-1"></i>Back to Login
        </a>
    </div>
<?php endif; ?>

<div class="mt-4 p-3 bg-light rounded-3 border small text-muted">
    <i class="bi bi-info-circle me-1 text-primary"></i>
    <strong>Security note:</strong> This token is single-use and expires automatically.
    If unused after 60 minutes, it becomes invalid and a new one must be generated.
</div>

<?php $view->section('scripts'); ?>
<script>
// ── Copy to clipboard ─────────────────────────────────────
function copyToClipboard(text, btn) {
    navigator.clipboard.writeText(text).then(function () {
        var icon = btn.querySelector('i');
        icon.className = 'bi bi-clipboard-check text-success';
        btn.title = 'Copied!';
        setTimeout(function () {
            icon.className = 'bi bi-clipboard';
            btn.title = 'Copy to clipboard';
        }, 2500);
    }).catch(function () {
        // Fallback for older browsers
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    });
}

document.getElementById('copyTokenBtn').addEventListener('click', function () {
    copyToClipboard(document.getElementById('resetToken').value, this);
});

document.getElementById('copyLinkBtn').addEventListener('click', function () {
    copyToClipboard(document.getElementById('resetLink').value, this);
});
</script>
<?php $view->endSection(); ?>
