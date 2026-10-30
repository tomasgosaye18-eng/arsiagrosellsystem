<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 8A (updated)
 * =========================================================
 * File     : modules/auth/views/profile.php
 * Layout   : templates/master.php
 * Purpose  : User profile page — full Phase 8A version.
 *            Shows all new fields: email, middle name, gender,
 *            employee ID, session info, login history,
 *            and active remember-me sessions.
 * Dependencies: ProfileController, SessionService,
 *               LoginHistoryModel, RememberMeModel.
 * =========================================================
 *
 * @var \AAM\Core\View            $view
 * @var array<string,mixed>       $user
 * @var array<string,mixed>       $sessionInfo
 * @var array<int,array>          $loginHistory
 * @var array<int,array>          $activeSessions
 */
declare(strict_types=1);

$avatarUrl = \AAM\Helpers\UploadHelper::profileImageUrl($user['profile_photo'] ?? null);
$fullName  = e(trim(
    ($user['first_name'] ?? '') . ' ' .
    ($user['middle_name'] ? $user['middle_name'] . ' ' : '') .
    ($user['last_name']  ?? '')
));
$roleLabel = e(\AAM\Helpers\StringHelper::titleCase($user['role_name'] ?? ''));

// Controller sets this — only true for administrator role
$showLoginHistory = $showLoginHistory ?? false;

$statusMap = [
    'active'    => ['bg-success',          'Active'],
    'inactive'  => ['bg-secondary',        'Inactive'],
    'suspended' => ['bg-danger',           'Suspended'],
    'locked'    => ['bg-warning text-dark','Locked'],
    'pending'   => ['bg-info text-dark',   'Pending'],
    'deleted'   => ['bg-dark',             'Deleted'],
];
[$statusClass, $statusLabel] = $statusMap[$user['account_status'] ?? 'active'] ?? ['bg-secondary', 'Unknown'];

$genderLabel = match($user['gender'] ?? '') {
    'male'              => 'Male',
    'female'            => 'Female',
    'other'             => 'Other',
    'prefer_not_to_say' => 'Prefer not to say',
    default             => '—',
};
?>

<!-- ── Page Header ───────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">
            <i class="bi bi-person-circle me-2 text-primary"></i>My Profile
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="<?= url('dashboard') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">My Profile</li>
            </ol>
        </nav>
    </div>
    <a href="<?= url('profile/edit') ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-pencil-square me-1"></i>Edit Profile
    </a>
</div>

<div class="row g-4">

    <!-- ── Left Column ──────────────────────────────────────── -->
    <div class="col-lg-4">

        <!-- Profile Summary Card -->
        <div class="card shadow-sm border-0">
            <div class="card-body text-center pt-4 pb-3">
                <div class="position-relative d-inline-block mb-3">
                    <img src="<?= e($avatarUrl) ?>"
                         alt="Profile photo of <?= $fullName ?>"
                         class="rounded-circle shadow"
                         style="width:100px;height:100px;object-fit:cover;border:3px solid #e8ecf0;"
                         onerror="this.src='<?= asset('images/no-avatar.svg') ?>'">
                    <a href="<?= url('profile/edit') ?>"
                       class="position-absolute bottom-0 end-0 btn btn-sm btn-primary rounded-circle
                              d-flex align-items-center justify-content-center p-0"
                       style="width:28px;height:28px;"
                       title="Change photo" aria-label="Change profile photo">
                        <i class="bi bi-camera-fill" style="font-size:.7rem;"></i>
                    </a>
                </div>

                <h5 class="fw-bold mb-1"><?= $fullName ?></h5>
                <p class="text-muted small mb-1">
                    <i class="bi bi-shield-fill-check me-1 text-primary"></i><?= $roleLabel ?>
                </p>
                <?php if (!empty($user['employee_id'])): ?>
                <p class="text-muted small mb-2">
                    <i class="bi bi-badge-identity me-1"></i><?= e($user['employee_id']) ?>
                </p>
                <?php endif; ?>
                <span class="badge <?= $statusClass ?> rounded-pill px-3 py-1 mb-3">
                    <?= e($statusLabel) ?>
                </span>

                <div class="d-grid gap-2 mt-2">
                    <a href="<?= url('profile/edit') ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-pencil-square me-1"></i>Edit Profile
                    </a>
                    <a href="<?= url('profile/change-password') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-key me-1"></i>Change Password
                    </a>
                </div>
            </div>
        </div>

        <!-- Account Details Card -->
        <div class="card shadow-sm border-0 mt-3">
            <div class="card-header fw-semibold small">
                <i class="bi bi-person-vcard me-2 text-primary"></i>Account Details
            </div>
            <ul class="list-group list-group-flush">
                <?php
                $detailRows = [
                    ['bi-at',             'Username',         e($user['username'] ?? '—')],
                    ['bi-gender-ambiguous','Gender',          $genderLabel],
                    ['bi-envelope',       'Email',            e($user['email'] ?? '—')],
                    ['bi-telephone',      'Phone',            e($user['phone'] ?? '—')],
                    ['bi-key',            'Password Changed', $user['password_changed_at']
                                                              ? date_display($user['password_changed_at'])
                                                              : 'Never'],
                    ['bi-clock',          'Last Login',       $user['last_login_at']
                                                              ? \AAM\Helpers\DateHelper::timeAgo($user['last_login_at'])
                                                              : 'Never'],
                    ['bi-calendar',       'Member Since',     date_display($user['created_at'] ?? null)],
                ];
                foreach ($detailRows as [$ico, $lbl, $val]): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                    <span class="text-muted small">
                        <i class="bi <?= $ico ?> me-1"></i><?= $lbl ?>
                    </span>
                    <span class="fw-semibold small text-end" style="max-width:150px;word-break:break-all;">
                        <?= $val ?>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Current Session Card -->
        <div class="card shadow-sm border-0 mt-3">
            <div class="card-header fw-semibold small">
                <i class="bi bi-clock-history me-2 text-primary"></i>Current Session
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between py-2 px-3">
                    <span class="text-muted small">Logged In</span>
                    <span class="fw-semibold small">
                        <?= $sessionInfo['login_time']
                            ? \AAM\Helpers\DateHelper::timeAgo(date('Y-m-d H:i:s', $sessionInfo['login_time']))
                            : '—' ?>
                    </span>
                </li>
                <li class="list-group-item d-flex justify-content-between py-2 px-3">
                    <span class="text-muted small">Session Expires</span>
                    <span class="fw-semibold small" id="sessionCountdown">
                        <?= gmdate('i:s', max(0, $sessionInfo['remaining_secs'])) ?> remaining
                    </span>
                </li>
                <li class="list-group-item d-flex justify-content-between py-2 px-3">
                    <span class="text-muted small">IP Address</span>
                    <span class="fw-semibold small"><?= e($sessionInfo['ip'] ?? '—') ?></span>
                </li>
            </ul>
        </div>

    </div><!-- /.col-lg-4 -->

    <!-- ── Right Column ─────────────────────────────────────── -->
    <div class="col-lg-8">

        <!-- Active Sessions -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="fw-semibold small">
                    <i class="bi bi-devices me-2 text-primary"></i>
                    Active Sessions
                    <span class="badge bg-primary rounded-pill ms-1"><?= count($activeSessions) ?></span>
                </div>
                <?php if (count($activeSessions) > 0): ?>
                <form method="POST" action="<?= url('profile/revoke-all-sessions') ?>"
                      onsubmit="return confirm('Log out from all other devices?')">
                    <?php csrf_input(); ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-power me-1"></i>Revoke All
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($activeSessions)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-device-hdd fs-3 d-block mb-2 opacity-30"></i>
                        <small>No remembered sessions active.</small>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Device</th>
                                    <th>IP Address</th>
                                    <th>Created</th>
                                    <th>Last Used</th>
                                    <th>Expires</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activeSessions as $session): ?>
                                <tr>
                                    <td class="ps-3">
                                        <i class="bi bi-laptop me-1 text-muted"></i>
                                        <span class="small text-truncate d-inline-block" style="max-width:130px;"
                                              title="<?= e($session['user_agent'] ?? '') ?>">
                                            <?= e(\AAM\Helpers\StringHelper::truncate($session['user_agent'] ?? 'Unknown', 35)) ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted"><?= e($session['ip_address'] ?? '—') ?></td>
                                    <td class="small"><?= datetime_display($session['created_at'] ?? null) ?></td>
                                    <td class="small">
                                        <?= $session['last_used_at']
                                            ? \AAM\Helpers\DateHelper::timeAgo($session['last_used_at'])
                                            : '—' ?>
                                    </td>
                                    <td class="small"><?= datetime_display($session['expires_at'] ?? null) ?></td>
                                    <td class="text-end pe-3">
                                        <form method="POST" action="<?= url('profile/revoke-session') ?>" class="d-inline">
                                            <?php csrf_input(); ?>
                                            <input type="hidden" name="selector" value="<?= e($session['selector']) ?>">
                                            <button type="submit"
                                                    class="btn btn-sm btn-link text-danger p-0"
                                                    title="Revoke this session"
                                                    onclick="return confirm('Revoke this session?')">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Login History — Administrator only -->
        <?php if ($showLoginHistory): ?>
        <div class="card shadow-sm border-0">
            <div class="card-header fw-semibold small">
                <i class="bi bi-journal-text me-2 text-primary"></i>Recent Login History
            </div>
            <div class="card-body p-0">
                <?php if (empty($loginHistory)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-clock fs-3 d-block mb-2 opacity-30"></i>
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
                                            <span class="badge bg-danger rounded-pill"
                                                  title="<?= e($entry['failure_reason'] ?? '') ?>">
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
                                        <i class="bi <?= $devIcon ?> me-1 text-muted"></i>
                                        <?= e(ucfirst($entry['device_type'] ?? 'desktop')) ?>
                                    </td>
                                    <td class="small text-muted"><?= e($entry['ip_address'] ?? '—') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; // showLoginHistory ?>

    </div><!-- /.col-lg-8 -->
</div><!-- /.row -->

<?php $view->section('scripts'); ?>
<script>
(function () {
    'use strict';
    // ── Session countdown ─────────────────────────────────────
    var remaining = <?= max(0, (int) ($sessionInfo['remaining_secs'] ?? 1800)) ?>;
    var el = document.getElementById('sessionCountdown');
    if (!el || remaining <= 0) return;

    function tick() {
        remaining = Math.max(0, remaining - 1);
        var mins = Math.floor(remaining / 60).toString().padStart(2, '0');
        var secs = (remaining % 60).toString().padStart(2, '0');
        el.textContent = mins + ':' + secs + ' remaining';
        if (remaining <= 300) {
            el.classList.add('text-warning', 'fw-semibold');
        }
        if (remaining <= 0) {
            el.textContent = 'Expired';
            el.classList.replace('text-warning', 'text-danger');
            window.location.href = '<?= url('login') ?>?expired=1';
        }
    }
    setInterval(tick, 1000);
}());
</script>
<?php $view->endSection(); ?>
