<?php
/**
 * View: modules/notifications/views/send_gm.php
 * Layout: templates/master.php
 * Purpose: General Manager — compose and send a business/operational
 *          notification to Farm Managers, Warehouse Managers,
 *          Salespersons, or a specific user in those roles.
 *
 * @var \AAM\Core\View $view
 * @var string[]       $gmRoles   Allowed target role names
 * @var array[]        $users     Active users in gmRoles
 */
declare(strict_types=1);

$roleLabels = [
    ROLE_FARM_MANAGER      => 'Farm Managers',
    ROLE_WAREHOUSE_MANAGER => 'Warehouse Managers',
    ROLE_SALESPERSON       => 'Salespersons',
];

$priorityOptions = [
    'low'      => ['label' => 'Low',      'class' => 'success'],
    'medium'   => ['label' => 'Medium',   'class' => 'info'],
    'high'     => ['label' => 'High',     'class' => 'warning'],
    'critical' => ['label' => 'Critical', 'class' => 'danger'],
];

$suggestionTemplates = [
    'Transfer approved — please proceed with dispatch.',
    'Transfer rejected — please review and resubmit.',
    'Stock discrepancy requires your attention.',
    'Dispatch issue — please contact the warehouse team.',
    'Receiving issue — please verify the received quantities.',
    'Important operational announcement.',
    'Please review your pending tasks.',
];
?>

<div class="row justify-content-center">
<div class="col-lg-8">

<!-- Page header -->
<div class="d-flex align-items-center gap-2 mb-4">
    <div class="rounded-circle d-flex align-items-center justify-content-center bg-success bg-opacity-10"
         style="width:44px;height:44px;">
        <i class="bi bi-megaphone-fill text-success fs-5"></i>
    </div>
    <div>
        <h4 class="mb-0 fw-semibold">Send Business Notification</h4>
        <p class="text-muted small mb-0">
            Notify your farm managers, warehouse managers, or salespersons.
        </p>
    </div>
</div>

<!-- Compose form -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom">
        <i class="bi bi-send-fill me-2 text-success"></i>
        Compose Notification
    </div>
    <div class="card-body">
        <form method="POST" action="<?= url('notifications/send') ?>" id="gmNotifForm">
            <?php csrf_input(); ?>

            <!-- ── Recipient Type ─────────────────────────────────── -->
            <div class="mb-4">
                <label class="form-label fw-semibold">Recipient Type <span class="text-danger">*</span></label>
                <div class="d-flex flex-wrap gap-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio"
                               name="recipient_type" id="rt_role"
                               value="role" checked
                               onchange="toggleRecipientFields(this.value)">
                        <label class="form-check-label" for="rt_role">
                            <i class="bi bi-people-fill me-1 text-success"></i>By Role
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio"
                               name="recipient_type" id="rt_user"
                               value="user"
                               onchange="toggleRecipientFields(this.value)">
                        <label class="form-check-label" for="rt_user">
                            <i class="bi bi-person-fill me-1 text-primary"></i>Specific User
                        </label>
                    </div>
                </div>
            </div>

            <!-- ── Role selector ─────────────────────────────────── -->
            <div class="mb-4" id="field_role">
                <label for="recipient_role" class="form-label fw-semibold">
                    Target Role <span class="text-danger">*</span>
                </label>
                <select name="recipient_role" id="recipient_role"
                        class="form-select" required>
                    <option value="">— Select role —</option>
                    <?php foreach ($gmRoles as $roleName): ?>
                    <option value="<?= e($roleName) ?>">
                        <?= e($roleLabels[$roleName] ?? ucwords(str_replace('_',' ',$roleName))) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text text-muted">
                    The notification will be sent to every active user with this role.
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
                        $grouped[$u['role_name']][] = $u;
                    }
                    foreach ($grouped as $roleName => $roleUsers): ?>
                    <optgroup label="<?= e($roleLabels[$roleName] ?? ucwords(str_replace('_',' ',$roleName))) ?>">
                        <?php foreach ($roleUsers as $u): ?>
                        <option value="<?= (int)$u['id'] ?>">
                            <?= e(trim($u['first_name'] . ' ' . $u['last_name'])) ?>
                            (<?= e($u['username']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>
                <div class="form-text text-muted">
                    Only users from allowed operational roles are listed.
                </div>
            </div>

            <!-- ── Title ─────────────────────────────────────────── -->
            <div class="mb-3">
                <label for="title" class="form-label fw-semibold">
                    Title <span class="text-danger">*</span>
                </label>
                <input type="text" name="title" id="title"
                       class="form-control"
                       maxlength="200"
                       placeholder="e.g. Transfer Approved"
                       required>
            </div>

            <!-- ── Quick Templates ────────────────────────────────── -->
            <div class="mb-3">
                <label class="form-label text-muted small">Quick Templates</label>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($suggestionTemplates as $tpl): ?>
                    <button type="button"
                            class="btn btn-outline-secondary btn-sm"
                            onclick="applyTemplate(<?= htmlspecialchars(json_encode($tpl), ENT_QUOTES) ?>)">
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
                          placeholder="Write your message here…"
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
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-send-fill me-1"></i>Send Notification
                </button>
                <a href="<?= url('notifications') ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-bell me-1"></i>View My Notifications
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Info card -->
<div class="card border-0 shadow-sm mt-3">
    <div class="card-body p-3">
        <p class="small text-muted mb-2">
            <i class="bi bi-info-circle-fill me-1 text-info"></i>
            <strong>Scope reminder:</strong> As General Manager you can notify
            Farm Managers, Warehouse Managers, and Salespersons.
            System-wide announcements are managed by the System Administrator.
        </p>
        <p class="small text-muted mb-0">
            Recipients will see the notification in their bell icon and on their
            Notifications page immediately.
        </p>
    </div>
</div>

</div><!-- /.col -->
</div><!-- /.row -->

<script>
function toggleRecipientFields(type) {
    var roleField = document.getElementById('field_role');
    var userField = document.getElementById('field_user');
    var roleSelect = document.getElementById('recipient_role');
    var userSelect = document.getElementById('recipient_id');

    if (type === 'role') {
        roleField.classList.remove('d-none');
        userField.classList.add('d-none');
        roleSelect.required = true;
        userSelect.required = false;
    } else {
        roleField.classList.add('d-none');
        userField.classList.remove('d-none');
        roleSelect.required = false;
        userSelect.required = true;
    }
}

function applyTemplate(tpl) {
    var msg = document.getElementById('message');
    msg.value = tpl;
    updateMsgCount();
    msg.focus();
}

function updateMsgCount() {
    var msg = document.getElementById('message');
    document.getElementById('msgCount').textContent = msg.value.length;
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('message').addEventListener('input', updateMsgCount);
    // Initialize fields
    toggleRecipientFields('role');
});
</script>
