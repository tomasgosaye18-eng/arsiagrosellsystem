<?php
/**
 * =========================================================
 * WAREHOUSES – DETAIL VIEW
 * =========================================================
 * File    : modules/organization/views/warehouses/show.php
 * Purpose : Warehouse detail page with all metadata,
 *           capacity info, current assignments, and history.
 * Deps    : WarehouseController, WarehouseModel
 * =========================================================
 */
declare(strict_types=1);
$pageTitle = 'Warehouse Details';
$warehouse ??= [];
$whId      = (int)($warehouse['warehouse_id'] ?? $warehouse['id'] ?? 0);
$whCode    = $warehouse['warehouse_code'] ?? 'WH-???';
$whName    = $warehouse['warehouse_name'] ?? $warehouse['name'] ?? '—';
$typeKey   = strtolower(str_replace('_', '-', $warehouse['warehouse_type'] ?? 'central'));
$typeLabel = ucwords(strtolower(str_replace('_', ' ', $warehouse['warehouse_type'] ?? 'Central')));
?>
<?php $view->section('styles'); ?>
<link rel="stylesheet" href="<?= $view->asset('css/organization.css') ?>">
<?php $view->endSection(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-building-fill me-2 text-primary"></i>Warehouse Details</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= url('admin/organization/warehouses') ?>">Warehouses</a></li>
        <li class="breadcrumb-item active"><?= e($whName) ?></li>
      </ol>
    </nav>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('admin/organization/warehouses/' . $whId . '/edit') ?>"
       class="btn btn-primary btn-sm"><i class="bi bi-pencil-fill me-1"></i>Edit</a>
    <a href="<?= url('admin/organization/warehouses') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="detail-card mb-3">
      <div class="detail-header warehouse-header">
        <div class="detail-code"><?= e($whCode) ?></div>
        <div class="detail-title"><?= e($whName) ?></div>
        <div class="d-flex align-items-center gap-2 mt-2 flex-wrap">
          <span class="type-badge type-<?= $typeKey ?>"><?= $typeLabel ?></span>
          <span class="status-pill <?= $warehouse['is_active'] ? 'active' : 'inactive' ?>">
            <i class="bi bi-circle-fill" style="font-size:.5rem;"></i>
            <?= $warehouse['is_active'] ? 'Active' : 'Inactive' ?>
          </span>
          <?php if (!empty($warehouse['seed_distribution_enabled'])): ?>
          <span class="badge bg-success-subtle text-success border border-success-subtle">
            <i class="bi bi-flower1 me-1"></i>Seed Distribution ON
          </span>
          <?php else: ?>
          <span class="badge bg-secondary-subtle text-secondary border">
            <i class="bi bi-flower1 me-1"></i>Seed Distribution OFF
          </span>
          <?php endif; ?>
        </div>
      </div>
      <div class="p-3">
        <!-- Capacity -->
        <?php if (!empty($warehouse['capacity_tons']) || !empty($warehouse['capacity_sqm'])): ?>
        <div class="detail-meta-item">
          <div class="detail-meta-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-box-seam"></i></div>
          <div>
            <div class="detail-meta-label">Capacity</div>
            <div class="detail-meta-value">
              <?php if ($warehouse['capacity_tons']): ?>
              <span class="me-3"><?= number_format((float)$warehouse['capacity_tons'], 2) ?> tons</span>
              <?php endif; ?>
              <?php if ($warehouse['capacity_sqm']): ?>
              <span><?= number_format((float)$warehouse['capacity_sqm'], 0) ?> m²</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Location -->
        <div class="detail-meta-item">
          <div class="detail-meta-icon bg-success bg-opacity-10 text-success"><i class="bi bi-geo-alt-fill"></i></div>
          <div>
            <div class="detail-meta-label">Location</div>
            <div class="detail-meta-value"><?= e($warehouse['location'] ?? '—') ?></div>
            <?php if ($warehouse['address']): ?>
            <div class="detail-meta-label"><?= e($warehouse['address']) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Manager -->
        <div class="detail-meta-item">
          <div class="detail-meta-icon bg-warning bg-opacity-10" style="color:#856404;"><i class="bi bi-person-badge-fill"></i></div>
          <div>
            <div class="detail-meta-label">Manager</div>
            <div class="detail-meta-value"><?= !empty($warehouse['manager_name']) ? e($warehouse['manager_name']) : '<em class="text-muted">Unassigned</em>' ?></div>
          </div>
        </div>

        <!-- Contact -->
        <div class="detail-meta-item">
          <div class="detail-meta-icon bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-telephone-fill"></i></div>
          <div>
            <div class="detail-meta-label">Phone</div>
            <div class="detail-meta-value"><?= e($warehouse['phone'] ?? '—') ?></div>
          </div>
        </div>
        <div class="detail-meta-item">
          <div class="detail-meta-icon bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-envelope-fill"></i></div>
          <div>
            <div class="detail-meta-label">Email</div>
            <div class="detail-meta-value"><?= e($warehouse['email'] ?? '—') ?></div>
          </div>
        </div>

        <?php if (!empty($warehouse['description'])): ?>
        <div class="detail-meta-item">
          <div class="detail-meta-icon bg-light text-muted"><i class="bi bi-file-text"></i></div>
          <div>
            <div class="detail-meta-label">Description</div>
            <div class="detail-meta-value"><?= e($warehouse['description']) ?></div>
          </div>
        </div>
        <?php endif; ?>

        <div class="detail-meta-item">
          <div class="detail-meta-icon bg-light text-muted"><i class="bi bi-clock-history"></i></div>
          <div>
            <div class="detail-meta-label">Created / Updated</div>
            <div class="detail-meta-value">
              <?= e($warehouse['creator_name'] ?? '—') ?> · <?= !empty($warehouse['created_at']) ? e(date('d/m/Y H:i', strtotime($warehouse['created_at']))) : '—' ?>
            </div>
            <?php if (!empty($warehouse['updated_at']) && $warehouse['updated_at'] !== $warehouse['created_at']): ?>
            <div class="detail-meta-label mt-1">Updated: <?= e(date('d/m/Y H:i', strtotime($warehouse['updated_at']))) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-person-badge-fill me-2 text-info"></i>Current Assignments</span>
        <a href="<?= url('admin/organization/assignments?location_type=warehouse') ?>"
           class="btn btn-sm btn-outline-info">Manage</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($assignments)): ?>
        <div class="empty-state py-3">
          <i class="bi bi-person-dash empty-icon" style="font-size:2rem;"></i>
          <p class="mb-0 text-muted small">No staff assigned</p>
        </div>
        <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($assignments as $a): ?>
          <div class="list-group-item d-flex align-items-center gap-3 py-2 px-3">
            <div class="rounded-circle bg-info bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0" style="width:32px;height:32px;">
              <i class="bi bi-person-fill text-info small"></i>
            </div>
            <div class="flex-grow-1">
              <div class="fw-semibold small"><?= e($a['user_full_name'] ?? $a['username'] ?? 'User #' . $a['user_id']) ?></div>
              <div class="text-muted" style="font-size:.72rem;"><?= e(ucwords(str_replace('_',' ',$a['assignment_role']))) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php $view->section('scripts'); ?>
<script src="<?= $view->asset('js/organization.js') ?>"></script>
<script>(function(){})();</script>
<?php $view->endSection(); ?>
