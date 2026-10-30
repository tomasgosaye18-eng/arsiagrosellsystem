<?php
/**
 * =========================================================
 * ASSIGNMENTS – LIST VIEW
 * =========================================================
 * File    : modules/organization/views/assignments/index.php
 * Purpose : Staff assignment management: view active
 *           assignments, create new, remove, and view
 *           assignment history per user or location.
 * Deps    : AssignmentController, AssignmentModel
 * =========================================================
 */
declare(strict_types=1);
$pageTitle = 'Assignment Management';
?>
<?php $view->section('styles'); ?>
<link rel="stylesheet" href="<?= $view->asset('css/organization.css') ?>">
<?php $view->endSection(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-person-badge-fill me-2 text-info"></i>Assignment Management</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= url('admin/organization') ?>">Organization</a></li>
        <li class="breadcrumb-item active">Assignments</li>
      </ol>
    </nav>
  </div>
  <button type="button" class="btn btn-primary btn-sm" id="btnNewAssignment">
    <i class="bi bi-plus-lg me-1"></i>New Assignment
  </button>
</div>

<!-- Stats Strip -->
<div class="row g-2 mb-3">
  <?php
  $statItems = [
    ['total_active',         'Active',      'bi-person-badge-fill',  'info'],
    ['farm_assignments',     'Farm Assigns','bi-flower1-fill',       'success'],
    ['warehouse_assignments','WH Assigns',  'bi-building-fill',      'primary'],
    ['shop_assignments',     'Shop Assigns','bi-shop',               'warning'],
    ['total_history_events', 'History',     'bi-clock-history',      'secondary'],
  ];
  foreach ($statItems as [$k, $label, $icon, $color]): ?>
  <div class="col-6 col-sm-4 col-md-2">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
        <i class="bi <?= $icon ?> text-<?= $color ?> fs-5 flex-shrink-0"></i>
        <div>
          <div class="fw-bold lh-1"><?= (int)($stats[$k] ?? 0) ?></div>
          <div class="text-muted" style="font-size:.7rem;"><?= $label ?></div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="filter-bar mb-3">
  <form method="GET" action="<?= url('admin/organization/assignments') ?>" id="filterForm" class="row g-2 align-items-end">
    <div class="col-md-3">
      <select class="form-select form-select-sm" name="location_type" id="typeFilter">
        <option value="">All Locations</option>
        <option value="farm"      <?= ($filters['location_type'] ?? '') === 'farm'      ? 'selected' : '' ?>>Farms</option>
        <option value="warehouse" <?= ($filters['location_type'] ?? '') === 'warehouse' ? 'selected' : '' ?>>Warehouses</option>
        <option value="shop"      <?= ($filters['location_type'] ?? '') === 'shop'      ? 'selected' : '' ?>>Shops</option>
      </select>
    </div>
    <div class="col-md-3">
      <select class="form-select form-select-sm" name="assignment_role" id="roleFilter">
        <option value="">All Roles</option>
        <?php foreach ($roles as $k => $v): ?>
        <option value="<?= e($k) ?>" <?= ($filters['assignment_role'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <select class="form-select form-select-sm" name="is_active" id="statusFilter">
        <option value="1" <?= ($filters['is_active'] ?? '1') === '1' ? 'selected' : '' ?>>Active</option>
        <option value="0" <?= ($filters['is_active'] ?? '') === '0' ? 'selected' : '' ?>>Removed</option>
        <option value=""  <?= ($filters['is_active'] ?? '') === '' ? 'selected' : '' ?>>All</option>
      </select>
    </div>
    <div class="col-md-2 d-flex gap-1">
      <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button>
      <?php if (array_filter($filters)): ?>
      <a href="<?= url('admin/organization/assignments') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Assignments Table -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($assignments)): ?>
    <div class="empty-state">
      <i class="bi bi-person-badge empty-icon"></i>
      <h6>No assignments found</h6>
      <button type="button" class="btn btn-primary btn-sm mt-2" id="btnNewAssignmentEmpty">
        <i class="bi bi-plus-lg me-1"></i>Create Assignment
      </button>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle org-table">
        <thead>
          <tr>
            <th class="ps-3">Staff Member</th>
            <th>Role</th>
            <th>Location Type</th>
            <th>Location</th>
            <th>Assigned By</th>
            <th>Assigned At</th>
            <th class="text-center">Status</th>
            <th class="action-col pe-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($assignments as $a): ?>
          <tr>
            <td class="ps-3">
              <div class="fw-semibold small">
                <?= e($a['user_full_name'] ?? $a['username'] ?? 'User #' . $a['user_id']) ?>
              </div>
              <div class="entity-meta"><?= e($a['user_email'] ?? '') ?></div>
            </td>
            <td>
              <span class="badge bg-primary-subtle text-primary fw-semibold">
                <?= e(ucwords(str_replace('_', ' ', $a['assignment_role']))) ?>
              </span>
            </td>
            <td>
              <span class="assign-badge <?= e($a['location_type']) ?>">
                <i class="bi <?= $a['location_type'] === 'farm' ? 'bi-flower1' : ($a['location_type'] === 'warehouse' ? 'bi-building' : 'bi-shop') ?>"></i>
                <?= e(ucfirst($a['location_type'])) ?>
              </span>
            </td>
            <td class="entity-meta fw-semibold"><?= e($a['location_name'] ?? 'Location #' . $a['location_id']) ?></td>
            <td class="entity-meta"><?= e($a['assigned_by_name'] ?? '—') ?></td>
            <td class="entity-meta"><?= e(date('d/m/Y', strtotime($a['assigned_at']))) ?></td>
            <td class="text-center">
              <span class="status-pill <?= $a['is_active'] ? 'active' : 'inactive' ?>">
                <i class="bi bi-circle-fill" style="font-size:.5rem;"></i>
                <?= $a['is_active'] ? 'Active' : 'Removed' ?>
              </span>
            </td>
            <td class="action-col pe-3">
              <div class="d-flex justify-content-end gap-1">
                <button class="btn btn-sm btn-outline-secondary btn-view-history"
                        data-user-id="<?= (int)$a['user_id'] ?>"
                        data-name="<?= e($a['user_full_name'] ?? 'User #' . $a['user_id']) ?>"
                        title="View History">
                  <i class="bi bi-clock-history"></i>
                </button>
                <?php if ($a['is_active']): ?>
                <button class="btn btn-sm btn-outline-danger btn-remove-assignment"
                        data-id="<?= (int)$a['assignment_id'] ?>"
                        data-name="<?= e($a['user_full_name'] ?? 'this user') ?>"
                        title="Remove Assignment">
                  <i class="bi bi-person-dash-fill"></i>
                </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="d-flex flex-wrap justify-content-between align-items-center px-3 py-3 border-top gap-2">
      <div class="text-muted small">
        Showing <strong><?= $pagination['from'] ?>–<?= $pagination['to'] ?></strong>
        of <strong><?= $pagination['total_records'] ?></strong> assignments
      </div>
      <?php if ($pagination['total_pages'] > 1): ?>
      <nav>
        <ul class="pagination pagination-sm mb-0">
          <?php for ($pg = 1; $pg <= $pagination['total_pages']; $pg++): ?>
          <li class="page-item <?= $pg === $pagination['current_page'] ? 'active' : '' ?>">
            <a class="page-link" href="<?= url('admin/organization/assignments') ?>?page=<?= $pg ?>&<?= http_build_query(array_filter($filters)) ?>"><?= $pg ?></a>
          </li>
          <?php endfor; ?>
        </ul>
      </nav>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ── New Assignment Modal ────────────────────────────── -->
<div class="modal fade" id="assignmentModal" tabindex="-1" aria-labelledby="assignmentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="assignmentModalLabel"><i class="bi bi-person-badge-fill me-2 text-info"></i>New Assignment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="assignmentForm" novalidate>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <div id="assignErrBox" class="alert alert-danger d-none mb-3"></div>

          <div class="mb-3">
            <label for="assign_role" class="form-label">Role <span class="text-danger">*</span></label>
            <select class="form-select" id="assign_role" name="assignment_role" required>
              <option value="">— Select Role —</option>
              <?php foreach ($roles as $k => $v): ?>
              <option value="<?= e($k) ?>"><?= e($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label for="assign_user_id" class="form-label">Staff Member <span class="text-danger">*</span></label>
            <select class="form-select" id="assign_user_id" name="user_id" required>
              <option value="">— Select Role First —</option>
            </select>
          </div>

          <div class="mb-3">
            <label for="assign_location_type" class="form-label">Location Type <span class="text-danger">*</span></label>
            <select class="form-select" id="assign_location_type" name="location_type" required>
              <option value="">— Select Type —</option>
              <option value="farm">Farm</option>
              <option value="warehouse">Warehouse</option>
              <option value="shop">Shop</option>
            </select>
            <div class="form-text text-muted small" id="locTypeHint"></div>
          </div>

          <div class="mb-3">
            <label for="assign_location_id" class="form-label">Location <span class="text-danger">*</span></label>
            <select class="form-select" id="assign_location_id" name="location_id" required>
              <option value="">— Select Location Type First —</option>
            </select>
          </div>

          <div class="mb-3">
            <label for="assign_notes" class="form-label">Notes <small class="text-muted">(optional)</small></label>
            <input type="text" class="form-control" id="assign_notes" name="notes" maxlength="255">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="assignSubmitBtn">
            <i class="bi bi-check-lg me-1"></i>Create Assignment
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── History Modal ──────────────────────────────────── -->
<div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="historyModalLabel"><i class="bi bi-clock-history me-2"></i>Assignment History</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0" id="historyBody">
        <div class="text-center py-4"><span class="spinner-border text-primary"></span></div>
      </div>
    </div>
  </div>
</div>

<?php $view->section('scripts'); ?>
<script src="<?= $view->asset('js/organization.js') ?>"></script>
<script>
(function () {
  'use strict';
  var csrfToken     = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  var assignModal   = new bootstrap.Modal(document.getElementById('assignmentModal'));
  var histModal     = new bootstrap.Modal(document.getElementById('historyModal'));
  var assignForm    = document.getElementById('assignmentForm');
  var assignSubmit  = document.getElementById('assignSubmitBtn');
  var assignErrBox  = document.getElementById('assignErrBox');

  // Open assignment modal
  ['btnNewAssignment','btnNewAssignmentEmpty'].forEach(function(id) {
    document.getElementById(id)?.addEventListener('click', function() {
      assignForm.reset();
      assignErrBox.classList.add('d-none');
      document.getElementById('assign_user_id').innerHTML = '<option value="">— Select Role First —</option>';
      document.getElementById('assign_location_id').innerHTML = '<option value="">— Select Location Type First —</option>';
      var hint = document.getElementById('locTypeHint');
      if (hint) hint.textContent = '';
      assignModal.show();
    });
  });

  // Role → Location Type auto-mapping (Prompt §9, §10, §11)
  var roleToLocationType = {
    'farm_manager':       'farm',
    'warehouse_manager':  'warehouse',
    'salesperson':        'shop'
  };

  // Dependent user dropdown
  document.getElementById('assign_role').addEventListener('change', function() {
    var role = this.value;
    var userSel  = document.getElementById('assign_user_id');
    var locType  = document.getElementById('assign_location_type');
    var locSel   = document.getElementById('assign_location_id');

    if (!role) {
      userSel.innerHTML = '<option value="">— Select Role First —</option>';
      locType.value = '';
      locSel.innerHTML = '<option value="">— Select Location Type First —</option>';
      return;
    }

    // Auto-set location type based on role
    var autoType = roleToLocationType[role] || '';
    if (autoType) {
      locType.value = autoType;
      locType.dispatchEvent(new Event('change'));
    }

    userSel.innerHTML = '<option value="">Loading…</option>';
    fetch(window.AAM.appUrl + '/admin/organization/assignments/data/users-by-role?role=' + encodeURIComponent(role), {
      credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'}
    }).then(function(r){return r.json();}).then(function(resp) {
      userSel.innerHTML = '<option value="">— Select User —</option>';
      (resp.users||[]).forEach(function(u) {
        var alreadyAssigned = u.has_active_assignment ? ' (currently assigned)' : '';
        userSel.innerHTML += '<option value="'+u.user_id+'">' + (u.full_name||u.username) + ' ('+u.username+')' + alreadyAssigned + '</option>';
      });
    });
  });

  // Dependent location dropdown
  document.getElementById('assign_location_type').addEventListener('change', function() {
    var locType = this.value;
    var locSel  = document.getElementById('assign_location_id');
    var hint    = document.getElementById('locTypeHint');
    if (!locType) {
      locSel.innerHTML = '<option value="">— Select Location Type First —</option>';
      if (hint) hint.textContent = '';
      return;
    }
    // Hint based on type
    var hints = { farm: 'Assign Farm Manager to a Farm', warehouse: 'Assign Warehouse Manager to a Warehouse', shop: 'Assign Salesperson to a Shop' };
    if (hint) hint.textContent = hints[locType] || '';
    locSel.innerHTML = '<option value="">Loading…</option>';
    fetch(window.AAM.appUrl + '/admin/organization/assignments/data/locations-by-type?type=' + locType, {
      credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'}
    }).then(function(r){return r.json();}).then(function(resp) {
      locSel.innerHTML = '<option value="">— Select Location —</option>';
      (resp.locations||[]).forEach(function(loc) {
        var id   = loc.id || loc.farm_id || loc.warehouse_id || loc.shop_id;
        var name = loc.farm_name || loc.warehouse_name || loc.shop_name || loc.name || ('Location #' + id);
        var code = loc.farm_code || loc.warehouse_code || loc.shop_code || '';
        locSel.innerHTML += '<option value="' + id + '">' + name + (code ? ' (' + code + ')' : '') + '</option>';
      });
    });
  });

  assignForm.addEventListener('submit', function(e) {
    e.preventDefault();
    assignErrBox.classList.add('d-none');
    var errors = [];
    if (!document.getElementById('assign_role').value)          errors.push('Role is required');
    if (!document.getElementById('assign_user_id').value)       errors.push('Staff member is required');
    if (!document.getElementById('assign_location_type').value) errors.push('Location type is required');
    if (!document.getElementById('assign_location_id').value)   errors.push('Location is required');
    if (errors.length) {
      assignErrBox.innerHTML = errors.join('<br>');
      assignErrBox.classList.remove('d-none'); return;
    }
    assignSubmit.disabled = true;
    assignSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating…';

    var fd = new FormData(assignForm);
    fetch(window.AAM.appUrl + '/admin/organization/assignments', {
      method:'POST', credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd
    }).then(function(r){return r.json();}).then(function(resp) {
      if (resp.success) {
        assignModal.hide();
        AAM.toast('Assignment created successfully','success');
        setTimeout(function(){location.reload();},700);
      } else {
        assignSubmit.disabled = false;
        assignSubmit.innerHTML = '<i class="bi bi-check-lg me-1"></i>Create Assignment';
        assignErrBox.innerHTML = Object.values(resp.errors||{}).filter(Boolean).join('<br>') || resp.message;
        assignErrBox.classList.remove('d-none');
      }
    }).catch(function(){AAM.toast('Network error','error'); assignSubmit.disabled=false; assignSubmit.innerHTML='<i class="bi bi-check-lg me-1"></i>Create Assignment';});
  });

  document.getElementById('assignmentModal').addEventListener('hidden.bs.modal', function() {
    assignSubmit.disabled = false;
    assignSubmit.innerHTML = '<i class="bi bi-check-lg me-1"></i>Create Assignment';
  });

  // Remove assignment
  document.querySelectorAll('.btn-remove-assignment').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var id = this.dataset.id;
      var name = this.dataset.name;
      if (!confirm('Remove assignment for "' + name + '"?')) return;
      var fd = new FormData(); fd.append('csrf_token', csrfToken);
      fetch(window.AAM.appUrl + '/admin/organization/assignments/' + id + '/remove', {
        method:'POST', credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd
      }).then(function(r){return r.json();}).then(function(resp) {
        if (resp.success) { AAM.toast('Assignment removed','success'); setTimeout(function(){location.reload();},700); }
        else { AAM.toast(resp.message||'Error','error'); }
      });
    });
  });

  // View history
  document.querySelectorAll('.btn-view-history').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var userId = this.dataset.userId;
      var name   = this.dataset.name;
      document.getElementById('historyModalLabel').innerHTML = '<i class="bi bi-clock-history me-2"></i>History – ' + name;
      document.getElementById('historyBody').innerHTML = '<div class="text-center py-4"><span class="spinner-border text-primary"></span></div>';
      histModal.show();

      fetch(window.AAM.appUrl + '/admin/organization/assignments/user/' + userId + '/history', {
        credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'}
      }).then(function(r){return r.json();}).then(function(resp) {
        var h = resp.history || [];
        if (!h.length) {
          document.getElementById('historyBody').innerHTML = '<div class="empty-state py-4"><p>No history found.</p></div>';
          return;
        }
        var html = '<div class="list-group list-group-flush">';
        h.forEach(function(item) {
          var ic = item.action === 'ASSIGNED' ? 'bi-person-plus-fill text-success' : (item.action === 'REMOVED' ? 'bi-person-dash-fill text-danger' : 'bi-arrow-repeat text-warning');
          html += '<div class="list-group-item d-flex gap-3 py-2 px-3"><i class="bi ' + ic + ' mt-1 flex-shrink-0"></i><div class="flex-grow-1"><div class="fw-semibold small">' + (item.action||'') + ' — ' + (item.location_type||'') + ' #' + (item.location_id||'') + '</div><div class="text-muted" style="font-size:.72rem;">' + (item.assignment_role||'') + ' · ' + (item.performed_at||'').slice(0,10) + '</div>' + (item.notes ? '<div class="text-muted fst-italic" style="font-size:.72rem;">' + item.notes + '</div>' : '') + '</div></div>';
        });
        html += '</div>';
        document.getElementById('historyBody').innerHTML = html;
      });
    });
  });

  // Auto-submit filters
  ['typeFilter','roleFilter','statusFilter'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.addEventListener('change', function(){document.getElementById('filterForm').submit();});
  });
})();
</script>
<?php $view->endSection(); ?>
