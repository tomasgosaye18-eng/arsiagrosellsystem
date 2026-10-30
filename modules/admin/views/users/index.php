<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 8A
 * =========================================================
 * File     : modules/admin/views/users/index.php
 * Layout   : templates/master.php
 * Purpose  : Full enterprise user management list.
 *            Searchable, sortable, filterable, paginated.
 *            Live search, advanced filters, bulk actions,
 *            inline status management, SweetAlert confirms.
 * Dependencies: UserController, UserService.
 * =========================================================
 *
 * @var \AAM\Core\View         $view
 * @var array<int,array>       $users
 * @var array<string,mixed>    $pagination
 * @var array<int,array>       $roles
 * @var string                 $search
 * @var string                 $roleFilter
 * @var string                 $statusFilter
 * @var string                 $sortBy
 * @var string                 $sortDir
 * @var int                    $perPage
 * @var array<string,int>      $stats
 */
declare(strict_types=1);

// ── Helpers ────────────────────────────────────────────────
$statusBadge = static function(string $status, bool $isLocked): string {
    $map = [
        'active'    => ['bg-success',   'Active'],
        'inactive'  => ['bg-secondary', 'Inactive'],
        'suspended' => ['bg-danger',    'Suspended'],
        'locked'    => ['bg-warning text-dark', 'Locked'],
        'pending'   => ['bg-info text-dark',    'Pending'],
        'deleted'   => ['bg-dark',      'Deleted'],
    ];
    [$cls, $lbl] = $map[$status] ?? ['bg-secondary', ucfirst($status)];
    $out = '<span class="badge ' . $cls . ' rounded-pill">' . e($lbl) . '</span>';
    if ($isLocked && $status !== 'locked') {
        $out .= ' <span class="badge bg-danger rounded-pill ms-1"><i class="bi bi-lock-fill"></i></span>';
    }
    return $out;
};

$sortLink = static function(string $col, string $label, string $currentSort, string $currentDir, string $base): string {
    $newDir = ($currentSort === $col && $currentDir === 'DESC') ? 'ASC' : 'DESC';
    $icon   = $currentSort === $col
        ? ($currentDir === 'ASC' ? 'bi-sort-up' : 'bi-sort-down')
        : 'bi-sort';
    return '<a href="' . $base . '&sort=' . $col . '&dir=' . $newDir . '" class="text-decoration-none text-dark">'
        . e($label) . ' <i class="bi ' . $icon . ' ms-1"></i></a>';
};

$baseUrl = url('admin/users') . '?search=' . urlencode($search)
    . '&role=' . urlencode($roleFilter)
    . '&status=' . urlencode($statusFilter)
    . '&per_page=' . $perPage;

$currentUserId = \AAM\Core\SessionManager::userId();
?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-people-fill me-2 text-primary"></i>User Management
    </h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('admin/dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item active">Users</li>
      </ol>
    </nav>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('admin/users/create') ?>" class="btn btn-primary">
      <i class="bi bi-person-plus-fill me-1"></i>Add User
    </a>
  </div>
</div>

<!-- ── Stats Strip ──────────────────────────────────────── -->
<div class="row g-2 mb-3">
  <?php
  $statItems = [
    ['total_users', 'Total', 'bi-people', 'primary', ''],
    ['active',  'Active',   'bi-person-check', 'success',   '?status=active'],
    ['inactive','Issues',   'bi-person-slash',  'warning',   '?status=inactive'],
    ['locked',  'Locked',   'bi-lock',          'danger',    '?status=locked'],
  ];
  $statKeys = ['total', 'active', 'inactive', 'locked'];
  foreach ($statItems as $i => [$statKey, $statLabel, $statIcon, $statColor, $statHref]): ?>
  <div class="col-sm-3">
    <a href="<?= url('admin/users') . $statHref ?>" class="text-decoration-none">
      <div class="card border-0 shadow-sm card-hover">
        <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
          <i class="bi <?= $statIcon ?> text-<?= $statColor ?> fs-5"></i>
          <div>
            <div class="fw-bold lh-1"><?= $stats[$statKeys[$i]] ?? 0 ?></div>
            <div class="text-muted" style="font-size:.72rem;"><?= $statLabel ?></div>
          </div>
        </div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Search & Filters ─────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2 px-3">
    <form method="GET" action="<?= url('admin/users') ?>" id="filterForm" class="row g-2 align-items-end">

      <!-- Live Search -->
      <div class="col-md-4">
        <label for="search" class="form-label small fw-semibold mb-1">Search</label>
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
          <input type="search"
                 class="form-control"
                 id="search"
                 name="search"
                 value="<?= e($search) ?>"
                 placeholder="Name, username, email, phone, ID…"
                 autocomplete="off"
                 id="liveSearchInput">
        </div>
      </div>

      <!-- Role Filter -->
      <div class="col-md-2">
        <label for="role" class="form-label small fw-semibold mb-1">Role</label>
        <select class="form-select form-select-sm" id="role" name="role">
          <option value="">All Roles</option>
          <?php foreach ($roles as $r): ?>
          <option value="<?= e($r['name']) ?>" <?= $roleFilter === $r['name'] ? 'selected' : '' ?>>
            <?= e($r['display_name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Status Filter -->
      <div class="col-md-2">
        <label for="status" class="form-label small fw-semibold mb-1">Status</label>
        <select class="form-select form-select-sm" id="status" name="status">
          <option value="">All Status</option>
          <option value="active"    <?= $statusFilter === 'active'    ? 'selected' : '' ?>>Active</option>
          <option value="inactive"  <?= $statusFilter === 'inactive'  ? 'selected' : '' ?>>Inactive</option>
          <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
          <option value="locked"    <?= $statusFilter === 'locked'    ? 'selected' : '' ?>>Locked</option>
          <option value="pending"   <?= $statusFilter === 'pending'   ? 'selected' : '' ?>>Pending</option>
        </select>
      </div>

      <!-- Per Page -->
      <div class="col-md-1">
        <label class="form-label small fw-semibold mb-1">Show</label>
        <select class="form-select form-select-sm" name="per_page">
          <?php foreach ([10, 20, 50, 100] as $pp): ?>
          <option value="<?= $pp ?>" <?= $perPage === $pp ? 'selected' : '' ?>><?= $pp ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Buttons -->
      <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
          <i class="bi bi-funnel me-1"></i>Filter
        </button>
        <?php if ($search || $roleFilter || $statusFilter): ?>
        <a href="<?= url('admin/users') ?>" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-x-lg"></i>
        </a>
        <?php endif; ?>
      </div>

    </form>
  </div>
</div>

<!-- ── User Table ────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">

    <?php if (empty($users)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-people display-4 d-block mb-3 opacity-20"></i>
        <h6 class="fw-semibold">No users found</h6>
        <?php if ($search || $roleFilter || $statusFilter): ?>
          <p class="small mb-3">Try clearing your filters.</p>
          <a href="<?= url('admin/users') ?>" class="btn btn-outline-secondary btn-sm">Clear Filters</a>
        <?php else: ?>
          <p class="small mb-3">No users have been created yet.</p>
          <a href="<?= url('admin/users/create') ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-person-plus me-1"></i>Create First User
          </a>
        <?php endif; ?>
      </div>
    <?php else: ?>

      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle" id="usersTable">
          <thead class="table-light">
            <tr>
              <th class="ps-3" style="width:36px;">
                <input type="checkbox" class="form-check-input" id="selectAll" title="Select all">
              </th>
              <th><?= $sortLink('id', '#', $sortBy, $sortDir, $baseUrl) ?></th>
              <th><?= $sortLink('first_name', 'User', $sortBy, $sortDir, $baseUrl) ?></th>
              <th>Role</th>
              <th>Status</th>
              <th><?= $sortLink('last_login_at', 'Last Login', $sortBy, $sortDir, $baseUrl) ?></th>
              <th><?= $sortLink('created_at', 'Created', $sortBy, $sortDir, $baseUrl) ?></th>
              <th class="text-end pe-3">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u):
              $uid      = (int) $u['id'];
              $isSelf   = $uid === $currentUserId;
              $isLocked = (bool) (int) $u['is_locked'];
              $isActive = (int) $u['is_active'] === 1 && $u['account_status'] === 'active';
              $fullName = trim($u['first_name'] . ' ' . ($u['middle_name'] ? $u['middle_name'][0] . '. ' : '') . $u['last_name']);
              $avatarUrl = \AAM\Helpers\UploadHelper::profileImageUrl($u['profile_photo'] ?? null);
            ?>
            <tr class="user-row <?= !$isActive ? 'opacity-75' : '' ?>" data-user-id="<?= $uid ?>">

              <!-- Checkbox -->
              <td class="ps-3">
                <?php if (!$isSelf): ?>
                <input type="checkbox" class="form-check-input row-check" value="<?= $uid ?>">
                <?php endif; ?>
              </td>

              <!-- ID -->
              <td class="text-muted small"><?= $uid ?></td>

              <!-- User Info -->
              <td>
                <div class="d-flex align-items-center gap-2">
                  <img src="<?= e($avatarUrl) ?>"
                       alt=""
                       class="rounded-circle flex-shrink-0"
                       style="width:36px;height:36px;object-fit:cover;"
                       onerror="this.src='<?= asset('images/no-avatar.svg') ?>'">
                  <div>
                    <div class="fw-semibold small lh-sm">
                      <a href="<?= url('admin/users/' . $uid) ?>" class="text-decoration-none text-dark user-name-link">
                        <?= e($fullName) ?>
                      </a>
                      <?php if ($isSelf): ?>
                        <span class="badge bg-primary ms-1" style="font-size:.6rem;">You</span>
                      <?php endif; ?>
                      <?php if ($u['must_change_password']): ?>
                        <span class="badge bg-warning text-dark ms-1" title="Must change password"
                              style="font-size:.6rem;"><i class="bi bi-key"></i></span>
                      <?php endif; ?>
                    </div>
                    <div class="text-muted" style="font-size:.72rem;">@<?= e($u['username']) ?></div>
                    <?php if (!empty($u['email'])): ?>
                      <div class="text-muted" style="font-size:.7rem;">
                        <i class="bi bi-envelope me-1"></i><?= e($u['email']) ?>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($u['phone'])): ?>
                      <div class="text-muted" style="font-size:.7rem;">
                        <i class="bi bi-telephone me-1"></i><?= e($u['phone']) ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </td>

              <!-- Role -->
              <td>
                <span class="badge bg-light text-dark border fw-normal">
                  <?= e($u['role_display_name'] ?? $u['role_name'] ?? '—') ?>
                </span>
              </td>

              <!-- Status -->
              <td><?= $statusBadge($u['account_status'] ?? 'active', $isLocked) ?></td>

              <!-- Last Login -->
              <td class="small text-muted">
                <?= $u['last_login_at'] ? \AAM\Helpers\DateHelper::timeAgo($u['last_login_at']) : '<span class="text-muted fst-italic">Never</span>' ?>
              </td>

              <!-- Created -->
              <td class="small text-muted"><?= date_display($u['created_at']) ?></td>

              <!-- Actions -->
              <td class="text-end pe-3">
                <div class="d-flex justify-content-end gap-1">

                  <!-- View -->
                  <a href="<?= url('admin/users/' . $uid) ?>"
                     class="btn btn-sm btn-outline-info btn-action" title="View details">
                    <i class="bi bi-eye-fill"></i>
                  </a>

                  <!-- Edit -->
                  <a href="<?= url('admin/users/' . $uid . '/edit') ?>"
                     class="btn btn-sm btn-outline-primary btn-action" title="Edit user">
                    <i class="bi bi-pencil-fill"></i>
                  </a>

                  <!-- Unlock (if locked) -->
                  <?php if ($isLocked): ?>
                  <button type="button"
                          class="btn btn-sm btn-outline-warning btn-action btn-unlock"
                          title="Unlock account"
                          data-user-id="<?= $uid ?>"
                          data-username="<?= e($u['username']) ?>"
                          data-action="<?= url('admin/users/' . $uid . '/unlock') ?>">
                    <i class="bi bi-unlock-fill"></i>
                  </button>
                  <?php endif; ?>

                  <!-- Toggle Active (not self) -->
                  <?php if (!$isSelf): ?>
                  <button type="button"
                          class="btn btn-sm btn-outline-<?= $isActive ? 'secondary' : 'success' ?> btn-action btn-toggle"
                          title="<?= $isActive ? 'Deactivate' : 'Activate' ?>"
                          data-user-id="<?= $uid ?>"
                          data-username="<?= e($u['username']) ?>"
                          data-active="<?= $isActive ? '1' : '0' ?>"
                          data-action="<?= url('admin/users/' . $uid . '/toggle') ?>">
                    <i class="bi bi-person-<?= $isActive ? 'slash' : 'check' ?>"></i>
                  </button>
                  <?php endif; ?>

                  <!-- Reset Password -->
                  <button type="button"
                          class="btn btn-sm btn-outline-warning btn-action btn-reset-pw"
                          title="Reset password"
                          data-user-id="<?= $uid ?>"
                          data-username="<?= e($u['username']) ?>"
                          data-action="<?= url('admin/users/' . $uid . '/reset') ?>">
                    <i class="bi bi-key-fill"></i>
                  </button>

                  <!-- Soft Delete (not self) -->
                  <?php if (!$isSelf && $u['account_status'] !== 'deleted'): ?>
                  <button type="button"
                          class="btn btn-sm btn-outline-danger btn-action btn-delete"
                          title="Delete user"
                          data-user-id="<?= $uid ?>"
                          data-username="<?= e($u['username']) ?>"
                          data-action="<?= url('admin/users/' . $uid . '/delete') ?>">
                    <i class="bi bi-trash3-fill"></i>
                  </button>
                  <?php elseif (!$isSelf && $u['account_status'] === 'deleted'): ?>
                  <button type="button"
                          class="btn btn-sm btn-outline-success btn-action btn-restore"
                          title="Restore user"
                          data-user-id="<?= $uid ?>"
                          data-username="<?= e($u['username']) ?>"
                          data-action="<?= url('admin/users/' . $uid . '/restore') ?>">
                    <i class="bi bi-arrow-counterclockwise"></i>
                  </button>
                  <?php endif; ?>

                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div><!-- /.table-responsive -->

      <!-- Pagination + Info -->
      <div class="d-flex flex-wrap justify-content-between align-items-center px-3 py-3 border-top gap-2">
        <div class="text-muted small">
          Showing
          <strong><?= $pagination['from'] ?? 1 ?></strong>
          –
          <strong><?= $pagination['to']   ?? count($users) ?></strong>
          of
          <strong><?= $pagination['total'] ?></strong>
          users
        </div>
        <?php if ((int) $pagination['totalPages'] > 1): ?>
        <nav aria-label="User list pagination">
          <?php
          $pager = new \AAM\Core\Pagination(
              (int) $pagination['total'],
              (int) $pagination['perPage'],
              (int) $pagination['page'],
              $baseUrl
          );
          echo $pager->render();
          ?>
        </nav>
        <?php endif; ?>
      </div>

    <?php endif; ?>
  </div>
</div>

<!-- Hidden POST form (reused by JS for all action buttons) -->
<form id="actionForm" method="POST" style="display:none;">
  <?php csrf_input(); ?>
</form>

<!-- ── Password Reset Result Modal ──────────────────────── -->
<div class="modal fade" id="resetResultModal" tabindex="-1" aria-labelledby="resetResultModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title fw-bold" id="resetResultModalLabel">
          <i class="bi bi-key-fill me-2"></i>Password Reset
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">New temporary password for <strong id="resetUsername"></strong>:</p>
        <div class="input-group">
          <input type="text" class="form-control fw-bold font-monospace" id="tempPwField" readonly>
          <button class="btn btn-outline-secondary" type="button" id="copyTempPw" title="Copy to clipboard">
            <i class="bi bi-clipboard"></i>
          </button>
        </div>
        <div class="alert alert-warning mt-3 py-2 small">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          Share this password with the user securely. They <strong>must change it</strong> on next login.
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
  var actionForm = document.getElementById('actionForm');

  // ── Helper: POST via fetch (AJAX) ──────────────────────────
  function postAction(url, successCb, errorCb) {
    var fd = new FormData();
    fd.append('_token', csrfToken);
    fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(resp) {
      if (resp.success) { successCb(resp); }
      else              { errorCb(resp.message || 'An error occurred.'); }
    })
    .catch(function() { errorCb('Network error. Please try again.'); });
  }

  // ── Helper: show toast ─────────────────────────────────────
  function showToast(msg, type) {
    if (window.AAM && window.AAM.toast) {
      window.AAM.toast(msg, type);
    } else {
      alert(msg);
    }
  }

  // ── Select All checkbox ────────────────────────────────────
  var selectAll = document.getElementById('selectAll');
  if (selectAll) {
    selectAll.addEventListener('change', function() {
      document.querySelectorAll('.row-check').forEach(function(cb) {
        cb.checked = selectAll.checked;
      });
    });
  }

  // ── Toggle (activate/deactivate) ───────────────────────────
  document.querySelectorAll('.btn-toggle').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var username = btn.dataset.username;
      var isActive = btn.dataset.active === '1';
      var action   = btn.dataset.action;
      var msg = isActive
        ? 'Deactivate user "' + username + '"? They will be unable to log in.'
        : 'Activate user "' + username + '"? They will regain login access.';

      if (!confirm(msg)) return;

      postAction(action,
        function(resp) {
          showToast(resp.message, 'success');
          setTimeout(function() { location.reload(); }, 800);
        },
        function(err) { showToast(err, 'error'); }
      );
    });
  });

  // ── Unlock ─────────────────────────────────────────────────
  document.querySelectorAll('.btn-unlock').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var username = btn.dataset.username;
      if (!confirm('Unlock account for "' + username + '"?')) return;
      postAction(btn.dataset.action,
        function(resp) {
          showToast(resp.message, 'success');
          setTimeout(function() { location.reload(); }, 800);
        },
        function(err) { showToast(err, 'error'); }
      );
    });
  });

  // ── Soft Delete ────────────────────────────────────────────
  document.querySelectorAll('.btn-delete').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var username = btn.dataset.username;
      if (!confirm('Delete user "' + username + '"?\n\nThis will deactivate the account. It can be restored later.')) return;
      postAction(btn.dataset.action,
        function(resp) {
          showToast(resp.message, 'success');
          setTimeout(function() { location.reload(); }, 800);
        },
        function(err) { showToast(err, 'error'); }
      );
    });
  });

  // ── Restore ────────────────────────────────────────────────
  document.querySelectorAll('.btn-restore').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var username = btn.dataset.username;
      if (!confirm('Restore user "' + username + '"? Account will be set to Active.')) return;
      postAction(btn.dataset.action,
        function(resp) {
          showToast(resp.message, 'success');
          setTimeout(function() { location.reload(); }, 800);
        },
        function(err) { showToast(err, 'error'); }
      );
    });
  });

  // ── Reset Password ─────────────────────────────────────────
  var resetModal    = new bootstrap.Modal(document.getElementById('resetResultModal'));
  var resetUsername = document.getElementById('resetUsername');
  var tempPwField   = document.getElementById('tempPwField');
  var copyBtn       = document.getElementById('copyTempPw');

  document.querySelectorAll('.btn-reset-pw').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var username = btn.dataset.username;
      if (!confirm('Reset password for "' + username + '"?\n\nA new temporary password will be generated.')) return;

      postAction(btn.dataset.action,
        function(resp) {
          resetUsername.textContent = username;
          tempPwField.value         = resp.data && resp.data.temp_password ? resp.data.temp_password : '(see flash message)';
          resetModal.show();
        },
        function(err) { showToast(err, 'error'); }
      );
    });
  });

  if (copyBtn) {
    copyBtn.addEventListener('click', function() {
      navigator.clipboard.writeText(tempPwField.value).then(function() {
        copyBtn.innerHTML = '<i class="bi bi-clipboard-check text-success"></i>';
        setTimeout(function() { copyBtn.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 2000);
      });
    });
  }

  // ── Auto-submit filter on select change ────────────────────
  ['role', 'status', 'per_page'].forEach(function(name) {
    var el = document.querySelector('[name="' + name + '"]');
    if (el) el.addEventListener('change', function() {
      document.getElementById('filterForm').submit();
    });
  });

})();
</script>
<?php $view->endSection(); ?>
