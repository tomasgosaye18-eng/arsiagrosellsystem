<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 8A
 * =========================================================
 * File     : modules/auth/views/profile_edit.php
 * Layout   : templates/master.php
 * Purpose  : Edit own profile — update first/middle/last
 *            name, gender, email, phone, and profile photo.
 *            Phase 8A adds middle_name, gender, email fields.
 * Dependencies: ProfileController, UserModel, UploadHelper.
 * =========================================================
 *
 * @var \AAM\Core\View      $view
 * @var array<string,mixed> $user
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

$avatarUrl = \AAM\Helpers\UploadHelper::profileImageUrl($user['profile_photo'] ?? null);

$statusColors = [
    'active'    => 'success',
    'inactive'  => 'secondary',
    'suspended' => 'danger',
    'locked'    => 'warning text-dark',
    'pending'   => 'info',
];
$statusColor = $statusColors[$user['account_status'] ?? 'active'] ?? 'secondary';
?>

<!-- ── Page Header ───────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">
            <i class="bi bi-person-fill me-2 text-primary"></i>Edit Profile
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="<?= url('dashboard') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('profile') ?>">My Profile</a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </nav>
    </div>
    <a href="<?= url('profile') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Profile
    </a>
</div>

<div class="row g-4">

    <!-- ── Left: Photo + Quick Info ──────────────────────── -->
    <div class="col-lg-3">

        <!-- Photo Card -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header fw-semibold small">
                <i class="bi bi-image me-2 text-primary"></i>Profile Photo
            </div>
            <div class="card-body text-center py-4">
                <img src="<?= e($avatarUrl) ?>"
                     alt="Your profile photo"
                     id="photoPreview"
                     class="rounded-circle shadow mb-3"
                     style="width:90px;height:90px;object-fit:cover;border:3px solid #dee2e6;"
                     onerror="this.src='<?= asset('images/no-avatar.svg') ?>'">

                <form method="POST"
                      action="<?= url('profile/photo') ?>"
                      enctype="multipart/form-data"
                      id="photoUploadForm">
                    <?php csrf_input(); ?>
                    <div class="mb-2">
                        <label for="profile_photo" class="form-label small fw-semibold mb-1 d-block">
                            Upload New Photo
                        </label>
                        <input type="file"
                               class="form-control form-control-sm"
                               id="profile_photo"
                               name="profile_photo"
                               accept="image/jpeg,image/png,image/webp"
                               aria-describedby="photoHelp">
                        <small id="photoHelp" class="text-muted d-block mt-1">
                            JPG/PNG/WebP · Max 5 MB
                        </small>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary w-100">
                        <i class="bi bi-upload me-1"></i>Upload Photo
                    </button>
                </form>

                <?php if ($user['profile_photo']): ?>
                <form method="POST"
                      action="<?= url('profile/photo/delete') ?>"
                      class="mt-2"
                      onsubmit="return confirm('Remove your profile photo?')">
                    <?php csrf_input(); ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                        <i class="bi bi-trash me-1"></i>Remove Photo
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Account Info (read-only) -->
        <div class="card border-0 shadow-sm">
            <div class="card-header fw-semibold small">
                <i class="bi bi-person-vcard me-2 text-primary"></i>Account Info
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between py-2 px-3">
                    <span class="text-muted small">Username</span>
                    <span class="fw-semibold small">@<?= e($user['username'] ?? '—') ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between py-2 px-3">
                    <span class="text-muted small">Role</span>
                    <span class="small fw-semibold">
                        <?= e(\AAM\Helpers\StringHelper::titleCase($user['role_name'] ?? '')) ?>
                    </span>
                </li>
                <li class="list-group-item d-flex justify-content-between py-2 px-3">
                    <span class="text-muted small">Status</span>
                    <span class="badge bg-<?= $statusColor ?>">
                        <?= e(ucfirst($user['account_status'] ?? 'active')) ?>
                    </span>
                </li>
                <li class="list-group-item d-flex justify-content-between py-2 px-3">
                    <span class="text-muted small">Member Since</span>
                    <span class="small"><?= date_display($user['created_at'] ?? null) ?></span>
                </li>
            </ul>
        </div>

    </div>

    <!-- ── Right: Edit Form ───────────────────────────────── -->
    <div class="col-lg-9">
        <div class="card border-0 shadow-sm">
            <div class="card-header fw-semibold">
                <i class="bi bi-pencil-square me-2 text-primary"></i>Personal Information
            </div>
            <div class="card-body p-4">

                <form method="POST"
                      action="<?= url('profile/edit') ?>"
                      novalidate
                      id="profileEditForm">
                    <?php csrf_input(); ?>

                    <div class="row g-3">

                        <!-- First Name -->
                        <div class="col-md-4">
                            <label for="first_name" class="form-label small fw-semibold">
                                First Name <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control form-control-sm <?= $ie('first_name') ?>"
                                   id="first_name"
                                   name="first_name"
                                   value="<?= $v('first_name') ?>"
                                   placeholder="First name"
                                   required
                                   autofocus>
                            <?= $fe('first_name') ?>
                        </div>

                        <!-- Middle Name -->
                        <div class="col-md-4">
                            <label for="middle_name" class="form-label small fw-semibold">
                                Middle Name
                            </label>
                            <input type="text"
                                   class="form-control form-control-sm <?= $ie('middle_name') ?>"
                                   id="middle_name"
                                   name="middle_name"
                                   value="<?= $v('middle_name') ?>"
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
                                   id="last_name"
                                   name="last_name"
                                   value="<?= $v('last_name') ?>"
                                   placeholder="Last name"
                                   required>
                            <?= $fe('last_name') ?>
                        </div>

                        <!-- Gender -->
                        <div class="col-md-4">
                            <label for="gender" class="form-label small fw-semibold">Gender</label>
                            <select class="form-select form-select-sm <?= $ie('gender') ?>"
                                    id="gender"
                                    name="gender">
                                <option value="">— Not specified —</option>
                                <?php
                                $genders = [
                                    'male'              => 'Male',
                                    'female'            => 'Female',
                                    'other'             => 'Other',
                                    'prefer_not_to_say' => 'Prefer not to say',
                                ];
                                $currentGender = $old['gender'] ?? $user['gender'] ?? '';
                                foreach ($genders as $gVal => $gLabel): ?>
                                <option value="<?= $gVal ?>"
                                    <?= $currentGender === $gVal ? 'selected' : '' ?>>
                                    <?= $gLabel ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?= $fe('gender') ?>
                        </div>

                        <!-- Email -->
                        <div class="col-md-8">
                            <label for="email" class="form-label small fw-semibold">
                                Email Address
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light">
                                    <i class="bi bi-envelope text-muted"></i>
                                </span>
                                <input type="email"
                                       class="form-control <?= $ie('email') ?>"
                                       id="email"
                                       name="email"
                                       value="<?= $v('email') ?>"
                                       placeholder="you@example.com">
                            </div>
                            <?= $fe('email') ?>
                            <small class="text-muted">Used for notifications. Must be unique.</small>
                        </div>

                        <!-- Phone -->
                        <div class="col-md-6">
                            <label for="phone" class="form-label small fw-semibold">
                                Phone Number
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light">
                                    <i class="bi bi-telephone text-muted"></i>
                                </span>
                                <input type="tel"
                                       class="form-control <?= $ie('phone') ?>"
                                       id="phone"
                                       name="phone"
                                       value="<?= $v('phone') ?>"
                                       placeholder="e.g. 0912345678">
                            </div>
                            <?= $fe('phone') ?>
                            <small class="text-muted">Ethiopian format: 09/07XXXXXXXX or +251XXXXXXXXX</small>
                        </div>

                        <!-- Username (read-only) -->
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Username</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light">
                                    <i class="bi bi-at text-muted"></i>
                                </span>
                                <input type="text"
                                       class="form-control bg-light"
                                       value="<?= e($user['username'] ?? '') ?>"
                                       readonly
                                       aria-label="Username — cannot be changed">
                            </div>
                            <small class="text-muted">Username cannot be changed.</small>
                        </div>

                    </div><!-- /.row -->

                    <hr class="mt-4">

                    <!-- Change Password Link -->
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <a href="<?= url('profile/change-password') ?>"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-key me-1"></i>Change Password
                        </a>
                        <div class="d-flex gap-2">
                            <a href="<?= url('profile') ?>" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-x-lg me-1"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary btn-sm" id="saveBtn">
                                <i class="bi bi-check-lg me-1"></i>Save Changes
                            </button>
                        </div>
                    </div>

                </form>

            </div>
        </div>
    </div>

</div><!-- /.row -->

<?php $view->section('scripts'); ?>
<script>
(function () {
    'use strict';

    // ── Photo preview before upload ───────────────────────────
    var photoInput = document.getElementById('profile_photo');
    if (photoInput) {
        photoInput.addEventListener('change', function () {
            var file = this.files[0];
            if (!file) return;

            var allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (!allowed.includes(file.type)) {
                alert('Please select a JPG, PNG, or WebP image.');
                this.value = '';
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                alert('File is too large. Maximum 5 MB allowed.');
                this.value = '';
                return;
            }

            var reader = new FileReader();
            reader.onload = function (e) {
                document.getElementById('photoPreview').src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    // ── Basic client-side required check ─────────────────────
    var form = document.getElementById('profileEditForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            var firstName = document.getElementById('first_name').value.trim();
            var lastName  = document.getElementById('last_name').value.trim();
            if (!firstName || !lastName) {
                e.preventDefault();
                if (!firstName) document.getElementById('first_name').focus();
                else            document.getElementById('last_name').focus();
            }
        });
    }
})();
</script>
<?php $view->endSection(); ?>
