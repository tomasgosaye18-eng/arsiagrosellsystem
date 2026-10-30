<?php
/**
 * =========================================================
 * FARMS – DETAIL VIEW
 * =========================================================
 * File    : modules/organization/views/farms/show.php
 * Purpose : Farm detail page with all metadata, GPS,
 *           current assignments, and assignment history.
 * Deps    : FarmController, FarmModel, AssignmentModel
 * =========================================================
 */
declare(strict_types=1);
$pageTitle = 'Farm Details';
$farm      ??= [];
$farmId    = (int)($farm['farm_id'] ?? $farm['id'] ?? 0);
$farmCode  = $farm['farm_code'] ?? 'FARM-???';
$farmName  = $farm['farm_name'] ?? $farm['name'] ?? '—';
$typeClass = 'type-' . strtolower($farm['farm_type'] ?? 'mixed');
$typeLabel = ucwords(strtolower(str_replace('_', ' ', $farm['farm_type'] ?? 'Mixed')));
?>
<?php $view->section('styles'); ?>
<link rel="stylesheet" href="<?= $view->asset('css/organization.css') ?>">
<?php $view->endSection(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-flower1-fill me-2 text-success"></i>Farm Details</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= url('admin/organization') ?>">Organization</a></li>
        <li class="breadcrumb-item"><a href="<?= url('admin/organization/farms') ?>">Farms</a></li>
        <li class="breadcrumb-item active"><?= e($farmName) ?></li>
      </ol>
    </nav>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('admin/organization/farms/' . $farmId . '/edit') ?>"
       class="btn btn-primary btn-sm">
      <i class="bi bi-pencil-fill me-1"></i>Edit
    </a>
    <a href="<?= url('admin/organization/farms') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>
  </div>
</div>

<div class="row g-3">

  <!-- Main Detail Card -->
  <div class="col-lg-7">
    <div class="detail-card mb-3">
      <div class="detail-header farm-header">
        <div class="detail-code"><?= e($farmCode) ?></div>
        <div class="detail-title"><?= e($farmName) ?></div>
        <div class="d-flex align-items-center gap-2 mt-2 flex-wrap">
          <span class="type-badge <?= $typeClass ?>"><?= $typeLabel ?></span>
          <span class="status-pill <?= $farm['is_active'] ? 'active' : 'inactive' ?>">
            <i class="bi bi-circle-fill" style="font-size:.5rem;"></i>
            <?= $farm['is_active'] ? 'Active' : 'Inactive' ?>
          </span>
          <?php if ($farm['land_area_ha']): ?>
          <span class="gps-badge">
            <i class="bi bi-arrows-angle-expand"></i>
            <?= number_format((float)$farm['land_area_ha'], 2) ?> ha
          </span>
          <?php endif; ?>
        </div>
      </div>

      <div class="p-3">
        <!-- Location Info -->
        <div class="detail-meta-item">
          <div class="detail-meta-icon bg-success bg-opacity-10 text-success"><i class="bi bi-geo-alt-fill"></i></div>
          <div>
            <div class="detail-meta-label">Location</div>
            <div class="detail-meta-value"><?= e($farm['location'] ?? '—') ?></div>
            <?php if (!empty($farm['address'])): ?>
            <div class="detail-meta-label mt-1"><?= e($farm['address']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <?php if (!empty($farm['latitude']) && !empty($farm['longitude'])): ?>
        <div class="detail-meta-item">
          <div class="detail-meta-icon bg-info bg-opacity-10 text-info"><i class="bi bi-crosshair2"></i></div>
          <div>
            <div class="detail-meta-label">GPS Coordinates</div>
            <div class="detail-meta-value">
              <span class="gps-badge">
                <i class="bi bi-geo"></i>
                <?= e($farm['latitude']) ?>, <?= e($farm['longitude']) ?>
              </span>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Manager -->
        <div class="detail-meta-item">
          <div class="detail-meta-icon bg-warning bg-opacity-10" style="color:#856404;"><i class="bi bi-person-badge-fill"></i></div>
          <div>
            <div class="detail-meta-label">Manager</div>
            <div class="detail-meta-value"><?= !empty($farm['manager_name']) ? e($farm['manager_name']) : '<em class="text-muted">Unassigned</em>' ?></div>
          </div>
        </div>

        <!-- Contact -->
        <div class="detail-meta-item">
          <div class="detail-meta-icon bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-telephone-fill"></i></div>
          <div>
            <div class="detail-meta-label">Phone</div>
            <div class="detail-meta-value"><?= $farm['phone'] ? e($farm['phone']) : '—' ?></div>
          </div>
        </div>
        <div class="detail-meta-item">
          <div class="detail-meta-icon bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-envelope-fill"></i></div>
          <div>
            <div class="detail-meta-label">Email</div>
            <div class="detail-meta-value"><?= $farm['email'] ? e($farm['email']) : '—' ?></div>
          </div>
        </div>

        <!-- Description -->
        <?php if (!empty($farm['description'])): ?>
        <div class="detail-meta-item">
          <div class="detail-meta-icon bg-light text-muted"><i class="bi bi-file-text"></i></div>
          <div>
            <div class="detail-meta-label">Description</div>
            <div class="detail-meta-value"><?= e($farm['description']) ?></div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Audit -->
        <div class="detail-meta-item">
          <div class="detail-meta-icon bg-light text-muted"><i class="bi bi-clock-history"></i></div>
          <div>
            <div class="detail-meta-label">Created / Updated</div>
            <div class="detail-meta-value">
              <?= e($farm['creator_name'] ?? '—') ?> · <?= !empty($farm['created_at']) ? e(date('d/m/Y H:i', strtotime($farm['created_at']))) : '—' ?>
            </div>
            <?php if (!empty($farm['updated_at']) && $farm['updated_at'] !== $farm['created_at']): ?>
            <div class="detail-meta-label mt-1">
              Last updated: <?= e(date('d/m/Y H:i', strtotime($farm['updated_at']))) ?>
            </div>
            <?php endif; ?>
          </div>
        </div>

      </div><!-- /p-3 -->
    </div>
  </div>

  <!-- Right: Assignments -->
  <div class="col-lg-5">

    <!-- Active Assignments -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-person-badge-fill me-2 text-info"></i>Current Assignments</span>
        <a href="<?= url('admin/organization/assignments?location_type=farm') ?>"
           class="btn btn-sm btn-outline-info">Manage</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($assignments)): ?>
        <div class="empty-state py-3">
          <i class="bi bi-person-dash empty-icon" style="font-size:2rem;"></i>
          <p class="mb-0 text-muted small">No staff assigned to this farm</p>
        </div>
        <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($assignments as $a): ?>
          <div class="list-group-item d-flex align-items-center gap-3 py-2 px-3">
            <div class="rounded-circle bg-info bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:32px;height:32px;">
              <i class="bi bi-person-fill text-info small"></i>
            </div>
            <div class="flex-grow-1">
              <div class="fw-semibold small"><?= e($a['user_full_name'] ?? $a['username'] ?? 'User #' . $a['user_id']) ?></div>
              <div class="text-muted" style="font-size:.72rem;"><?= e(ucwords(str_replace('_',' ',$a['assignment_role']))) ?></div>
            </div>
            <small class="text-muted"><?= e(date('d/m/Y', strtotime($a['assigned_at']))) ?></small>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Assignment History -->
    <?php if (!empty($history)): ?>
    <div class="card border-0 shadow-sm">
      <div class="card-header"><i class="bi bi-clock-history me-2 text-secondary"></i>Assignment History</div>
      <div class="card-body p-0" style="max-height:280px;overflow-y:auto;">
        <div class="list-group list-group-flush">
          <?php foreach ($history as $h): ?>
          <div class="list-group-item d-flex align-items-start gap-2 py-2 px-3">
            <?php
              $actionClass = match($h['action']) {
                'ASSIGNED' => 'text-success', 'REMOVED' => 'text-danger', default => 'text-warning'
              };
              $actionIcon = match($h['action']) {
                'ASSIGNED' => 'bi-person-plus-fill', 'REMOVED' => 'bi-person-dash-fill', default => 'bi-arrow-repeat'
              };
            ?>
            <i class="bi <?= $actionIcon ?> <?= $actionClass ?> mt-1 flex-shrink-0"></i>
            <div class="flex-grow-1">
              <div class="small fw-semibold"><?= e($h['user_name'] ?? 'User #' . $h['user_id']) ?></div>
              <div class="text-muted" style="font-size:.72rem;">
                <?= e(ucwords(str_replace('_',' ',$h['assignment_role']))) ?>
                · <?= e(date('d/m/Y', strtotime($h['performed_at']))) ?>
              </div>
              <?php if ($h['notes']): ?>
              <div class="text-muted fst-italic" style="font-size:.72rem;"><?= e($h['notes']) ?></div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div>

</div>

<?php $view->section('scripts'); ?>
<script src="<?= $view->asset('js/organization.js') ?>"></script>
<script>
(function () {
  'use strict';
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
    new bootstrap.Tooltip(el);
  });
})();
</script>
<?php $view->endSection(); ?>
