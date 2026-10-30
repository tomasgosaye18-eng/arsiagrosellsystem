<?php
/**
 * View: modules/notifications/views/send_admin.php
 * Layout: templates/master.php
 * Purpose: System Administrator — compose and send a system-wide,
 *          role-based, or single-user notification.
 *
 * @var \AAM\Core\View $view
 * @var array[]        $roles   All active roles
 * @var array[]        $users   All active users with role info
 */
declare(strict_types=1);

$priorityOptions = [
    'low'      => ['label' => 'Low',      'class' => 'success'],
    'medium'   => ['label' => 'Medium',   'class' => 'info'],
    'high'     => ['label' => 'High',     'class' => 'warning'],
    'critical' => ['label' => 'Critical', 'class' => 'danger'],
];

$suggestionTemplates = [
    'Security announcement: please review and update your password.',
    'Backup completed successfully.',
    'System downtime notice — please save your work.',
    'Important system-wide announcement.',
    'Please log out and log back in to apply updates.',
];
?>

<div class="row justify-content-center">
<div class="col-lg-8">

<!-- Page header -->
<div class="d-flex align-items-center gap-2 mb-4">
    <div class="rounded-circle d-flex align-items-center justify-content-center"
         style="width:44px;height:44px;background:rgba(255,193,7,.15);border:2px solid rgba(255,193,7,.3);">
        <i class="bi bi-shield-fill-check fs-5" style="color:#ffc107;"></i>
    </div>
    <div>
        <h4 class="mb-0 fw-semibold">Send System Notification</h4>
        <p class="text-muted small mb-0">
            Send system-wide, role-based, or user-specific announcements.
        </p>
    </div>
</div>

<!-- Compose form -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom">
        <i class="bi bi-send-fill me-2" style="color:#ffc107;"></i>
        Compose System Notification
    </div>
    <div class="card-body">
        <form method="POST" action="<?= url('admin/notifications/send') ?>" id="adminNotifForm">
            <?php csrf_input(); ?>

            <!-- ── Recipient Type ─────────────────────────────────── -->
            <div class="mb-4">
                <label class="form-label fw-semibold">Recipient Type <span class="text-danger">*</span></label>
                <div class="d-flex flex-wrap gap-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio"
                               name="recipient_type" id="rt_all"
                               value="all" checked
                               onchange="toggleAdminFields(this.value)">
                        <label class="form-check-label" for="rt_all">
                            <i class="bi bi-broadcast me-1 text-danger"></i>All Users
                            <span class="badge bg-danger ms-1" style="font-size:.6rem;">Broadcast</span>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio"
                               name="recipient_type" id="rt_role"
                               value="role"
                               onchange="toggleAdminFields(this.value)">
                        <label class="form-check-label" for="rt_role">
                            <i class="bi bi-people-fill me-1 text-primary"></i>By Role
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio"
                               name="recipient_type" id="rt_user"
                               value="user"
                               onchange="toggleAdminFields(this.value)">
                        <label class="form-check-label" for="rt_user">
                            <i class="bi bi-person-fill me-1 text-success"></i>Specific User
                        </label>
                    </div>
                </div>
            </div>

            <!-- ── "All users" notice ─────────────────────────────── -->
            <div class="mb-4" id="field_all">
                <div class="alert alert-warning d-flex align-items-center gap-2 mb-0">
                    <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                    <div class="small">
                        <strong>Broadcast mode:</strong> This notification will be delivered
                        to every active user in the system.
                        Use this only for critical system-wide announcements.
                    </div>
                </div>
            </div>

            <!-- ── Role selector ─────────────────────────────────── -->
            <div class="mb-4 d-none" id="field_role">
                <label for="recipient_role" class="form-label fw-semibold">
                    Target Role <span class="text-danger">*</span>
                </label>
                <select name="recipient_role" id="recipient_role" class="form-select">
                    <option value="">— Select role —</option>
                    <?php foreach ($roles as $role): ?>
                    <option value="<?= e($role['name']) ?>">
                        <?= e($role['display_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text text-muted">
                    Every active user with this role will receive the notification.
                </div>
            </div>

            <!-- ── User selector ─────────────────────────────────── -->
            <div class="mb-4 d-none" id="field_user">
                <label for="recipient_id" class="form-label fw-semibold">
                    Recipient User <span class="text-danger">*</span>
                </label>
                <select name="recipient_id" id="recipient_id" class="form-select">
                    <option value="">— Select user —</option>
                    <?php
                    $grouped = [];
                    foreach ($users as $u) {
                        $grouped[$u['role_display']][] = $u;
                    }
                    ksort($grouped);
                    foreach ($grouped as $roleDisplay => $roleUsers): ?>
                    <optgroup label="<?= e($roleDisplay) ?>">
                        <?php foreach ($roleUsers as $u): ?>
                        <option value="<?= (int)$u['id'] ?>">
                            <?= e(trim($u['first_name'] . ' ' . $u['last_name'])) ?>
                            (<?= e($u['username']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ── Title ─────────────────────────────────────────── -->
            <div class="mb-3">
                <label for="title" class="form-label fw-semibold">
                    Title <span class="text-danger">*</span>
                </label>
                <input type="text" name="title" id="title"
                       class="form-control"
                       maxlength="200"
                       placeholder="e.g. Scheduled Maintenance"
                       required>
            </div>

            <!-- ── Quick Templates ────────────────────────────────── -->
            <div class="mb-3">
                <label class="form-label text-muted small">Quick Templates</label>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($suggestionTemplates as $tpl): ?>
                    <button type="button"
                            class="btn btn-outline-secondary btn-sm"
                            onclick="applyAdminTemplate(<?= htmlspecialchars(json_encode($tpl), ENT_QUOTES) ?>)">
                        <?= e($tpl) ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── Message ───────────────────────────────────────── -->
            <div class="mb-3">
                <label for="message" class="form-label fw-semibold">
                    Message <span class="text-danger">*</span>
                </label>
                <textarea name="message" id="message"
                          class="form-control"
                          rows="5"
                          maxlength="2000"
                          placeholder="Write your system message here…"
                          required></textarea>
                <div class="form-text text-muted text-end small">
                    <span id="msgCount">0</span>/2000
                </div>
            </div>

            <!-- ── Priority ──────────────────────────────────────── -->
            <div class="mb-4">
                <label class="form-label fw-semibold">Priority</label>
                <div class="d-flex flex-wrap gap-3">
                    <?php foreach ($priorityOptions as $value => $opt): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="radio"
                               name="priority" id="prio_<?= $value ?>"
                               value="<?= $value ?>"
                               <?= $value === 'medium' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="prio_<?= $value ?>">
                            <span class="badge bg-<?= $opt['class'] ?>"><?= $opt['label'] ?></span>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── Actions ───────────────────────────────────────── -->
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning text-dark fw-semibold">
                    <i class="bi bi-send-fill me-1"></i>Send Notification
                </button>
                <a href="<?= url('admin/dashboard') ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-speedometer2 me-1"></i>Back to Dashboard
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Info card -->
<div class="card border-0 shadow-sm mt-3">
    <div class="card-body p-3">
        <p class="small text-muted mb-2">
            <i class="bi bi-shield-check me-1 text-warning"></i>
            <strong>System Administrator scope:</strong> You can send notifications to
            all users, any specific role, or a specific user.
        </p>
        <p class="small text-muted mb-0">
            These notifications appear under the
            <strong>system_announcement</strong> type and display with the
            appropriate priority indicator in recipients' bell menus.
        </p>
    </div>
</div>

</div><!-- /.col -->
</div><!-- /.row -->

<script>
function toggleAdminFields(type) {
    ['field_all','field_role','field_user'].forEach(function(id) {
        document.getElementById(id).classList.add('d-none');
    });
    document.getElementById('field_' + type).classList.remove('d-none');

    var roleSelect = document.getElementById('recipient_role');
    var userSelect = document.getElementById('recipient_id');
    roleSelect.required = (type === 'role');
    userSelect.required = (type === 'user');
}

function applyAdminTemplate(tpl) {
    var msg = document.getElementById('message');
    msg.value = tpl;
    updateCount();
    msg.focus();
}

function updateCount() {
    var msg = document.getElementById('message');
    document.getElementById('msgCount').textContent = msg.value.length;
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('message').addEventListener('input', updateCount);
    toggleAdminFields('all');
});
</script>
