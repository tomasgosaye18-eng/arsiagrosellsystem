<?php
/**
 * =========================================================
 * SHOPS – DETAIL VIEW
 * =========================================================
 * File    : modules/organization/views/shops/show.php
 * Purpose : Shop detail page with operating hours,
 *           assignments, and history.
 * Deps    : ShopController, ShopModel
 * =========================================================
 */
declare(strict_types=1);
$pageTitle = 'Shop Details';
$shop      ??= [];
$shopId    = (int)($shop['shop_id'] ?? $shop['id'] ?? 0);
$shopCode  = $shop['shop_code'] ?? 'SHOP-???';
$shopName  = $shop['shop_name'] ?? $shop['name'] ?? '—';
$typeClass = 'type-' . strtolower($shop['shop_type'] ?? 'retail');
$typeLabel = ucwords(strtolower($shop['shop_type'] ?? 'Retail'));
$shopImageUrl = !empty($shop['shop_image']) ? upload_url('shops/' . $shop['shop_image']) : null;
?>
<?php $view->section('styles'); ?>
<link rel="stylesheet" href="<?= $view->asset('css/organization.css') ?>">
<?php $view->endSection(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-shop-window me-2" style="color:#fd7e14;"></i>Shop Details</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= url('admin/organization/shops') ?>">Shops</a></li>
        <li class="breadcrumb-item active"><?= e($shopName) ?></li>
      </ol>
    </nav>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('admin/organization/shops/' . $shopId . '/edit') ?>"
       class="btn btn-primary btn-sm"><i class="bi bi-pencil-fill me-1"></i>Edit</a>
    <a href="<?= url('admin/organization/shops') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="detail-card mb-3">
      <div class="detail-header shop-header">
        <?php if ($shopImageUrl): ?>
        <div style="height:180px;overflow:hidden;border-radius:10px 10px 0 0;margin:-1px -1px 0 -1px;">
          <img src="<?= e($shopImageUrl) ?>"
               alt="<?= e($shopName) ?>"
               style="width:100%;height:100%;object-fit:cover;"
               onerror="this.parentElement.style.display='none'">
        </div>
        <?php endif; ?>
        <div class="detail-code"><?= e($shopCode) ?></div>
        <div class="detail-title"><?= e($shopName) ?></div>
        <div class="d-flex align-items-center gap-2 mt-2 flex-wrap">
          <span class="type-badge <?= $typeClass ?>"><?= $typeLabel ?></span>
          <span class="status-pill <?= $shop['is_active'] ? 'active' : 'inactive' ?>">
            <i class="bi bi-circle-fill" style="font-size:.5rem;"></i>
            <?= $shop['is_active'] ? 'Active' : 'Inactive' ?>
          </span>
        <?php if (!empty($shop['opening_time']) && !empty($shop['closing_time'])): ?>
          <span class="hours-badge">
            <i class="bi bi-clock"></i>
            <?= e(substr($shop['opening_time'],0,5)) ?> – <?= e(substr($shop['closing_time'],0,5)) ?>
          </span>
          <?php endif; ?>
        </div>
      </div>
      <div class="p-3">
        <div class="detail-meta-item">
          <div class="detail-meta-icon bg-success bg-opacity-10 text-success"><i class="bi bi-geo-alt-fill"></i></div>
          <div>
            <div class="detail-meta-label">Location</div>
            <div class="detail-meta-value"><?= e($shop['location'] ?? '—') ?></div>
            <?php if ($shop['address']): ?><div class="detail-meta-label"><?= e($shop['address']) ?></div><?php endif; ?>
          </div>
        </div>
        <div class="detail-meta-item">
          <div class="detail-meta-icon bg-warning bg-opacity-10" style="color:#856404;"><i class="bi bi-person-badge-fill"></i></div>
          <div>
            <div class="detail-meta-label">Manager</div>
            <div class="detail-meta-value"><?= !empty($shop['manager_name']) ? e($shop['manager_name']) : '<em class="text-muted">Unassigned</em>' ?></div>
          </div>
        </div>
        <div class="detail-meta-item">
          <div class="detail-meta-icon bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-telephone-fill"></i></div>
          <div>
            <div class="detail-meta-label">Phone</div>
            <div class="detail-meta-value"><?= e($shop['phone'] ?? '—') ?></div>
          </div>
        </div>
        <div class="detail-meta-item">
          <div class="detail-meta-icon bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-envelope-fill"></i></div>
          <div>
            <div class="detail-meta-label">Email</div>
            <div class="detail-meta-value"><?= e($shop['email'] ?? '—') ?></div>
          </div>
        </div>
        <?php if (!empty($shop['description'])): ?>
        <div class="detail-meta-item">
          <div class="detail-meta-icon bg-light text-muted"><i class="bi bi-file-text"></i></div>
          <div>
            <div class="detail-meta-label">Description</div>
            <div class="detail-meta-value"><?= e($shop['description']) ?></div>
          </div>
        </div>
        <?php endif; ?>
        <div class="detail-meta-item">
          <div class="detail-meta-icon bg-light text-muted"><i class="bi bi-clock-history"></i></div>
          <div>
            <div class="detail-meta-label">Created / Updated</div>
            <div class="detail-meta-value">
              <?= e($shop['creator_name'] ?? '—') ?> · <?= !empty($shop['created_at']) ? e(date('d/m/Y H:i', strtotime($shop['created_at']))) : '—' ?>
            </div>
            <?php if (!empty($shop['updated_at']) && $shop['updated_at'] !== $shop['created_at']): ?>
            <div class="detail-meta-label mt-1">Updated: <?= e(date('d/m/Y H:i', strtotime($shop['updated_at']))) ?></div>
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
        <a href="<?= url('admin/organization/assignments?location_type=shop') ?>"
           class="btn btn-sm btn-outline-info">Manage</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($assignments)): ?>
        <div class="empty-state py-3">
          <i class="bi bi-person-dash empty-icon" style="font-size:2rem;"></i>
          <p class="mb-0 text-muted small">No staff assigned to this shop</p>
        </div>
        <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($assignments as $a): ?>
          <div class="list-group-item d-flex align-items-center gap-3 py-2 px-3">
            <div class="rounded-circle bg-info bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0" style="width:32px;height:32px;">
              <i class="bi bi-person-fill text-info small"></i>
            </div>
            <div class="flex-grow-1">
              <div class="fw-semibold small"><?= e($a['user_full_name'] ?? 'User #' . $a['user_id']) ?></div>
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
