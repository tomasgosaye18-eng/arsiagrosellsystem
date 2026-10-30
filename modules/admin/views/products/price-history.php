<?php
/**
 * =========================================================
 * PRICE HISTORY – VIEW
 * =========================================================
 * File    : modules/admin/views/products/price-history.php
 * Purpose : Full price history for a product with stats.
 *           The "Update Price" button is intentionally
 *           NOT shown — selling price management is the
 *           General Manager's responsibility (/gm/products).
 *           This view is read-only for all roles.
 * =========================================================
 */
declare(strict_types=1);
$isGM    = ($userRole ?? '') === ROLE_GENERAL_MANAGER;
$isAdmin = ($userRole ?? '') === ROLE_ADMIN;
// Breadcrumb & back links differ by role
$productsUrl = $isGM ? url('gm/products') : url('admin/products');
$productUrl  = $isGM
    ? url('gm/products/' . (int)$product['product_id'] . '/pricing')
    : url('admin/products/' . (int)$product['product_id']);
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-graph-up me-2 text-success"></i>Price History</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= url('dashboard') ?>">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= $productsUrl ?>">Products</a></li>
        <li class="breadcrumb-item"><a href="<?= $productUrl ?>"><?= e($product['product_name']) ?></a></li>
        <li class="breadcrumb-item active">Price History</li>
      </ol>
    </nav>
  </div>
  <div class="d-flex gap-2">
    <?php if ($isGM): ?>
    <a href="<?= url('gm/products/' . (int)$product['product_id'] . '/pricing') ?>"
       class="btn btn-success btn-sm">
      <i class="bi bi-tag-fill me-1"></i>Manage Price
    </a>
    <?php else: ?>
    <span class="badge bg-secondary py-2 px-3 align-self-center" title="Only the General Manager can change selling prices">
      <i class="bi bi-lock-fill me-1"></i>Read-only
    </span>
    <?php endif; ?>
    <a href="<?= $productUrl ?>"
       class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
  </div>
</div>

<!-- Current Prices -->
<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="card border-0 shadow-sm border-start border-4 border-secondary h-100">
      <div class="card-body py-3 px-3">
        <div class="text-muted small">Current Cost Price</div>
        <div class="fw-bold fs-4 text-secondary">ETB <?= number_format((float)$product['cost_price'],2) ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm border-start border-4 border-success h-100">
      <div class="card-body py-3 px-3">
        <div class="text-muted small">Current Selling Price</div>
        <div class="fw-bold fs-4 text-success">ETB <?= number_format((float)$product['selling_price'],2) ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm border-start border-4 border-primary h-100">
      <div class="card-body py-3 px-3">
        <div class="text-muted small">Markup</div>
        <div class="fw-bold fs-4 text-primary">
          <?= $product['markup_percentage'] ? round($product['markup_percentage'],1).'%' : '—' ?>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm border-start border-4 border-info h-100">
      <div class="card-body py-3 px-3">
        <div class="text-muted small">Total Price Changes</div>
        <div class="fw-bold fs-4 text-info"><?= (int)($stats['total_changes'] ?? 0) ?></div>
      </div>
    </div>
  </div>
</div>

<!-- Filter Tabs -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-white py-2">
    <ul class="nav nav-tabs card-header-tabs mb-0">
      <li class="nav-item">
        <a class="nav-link <?= !$type ? 'active' : '' ?>"
           href="<?= url('admin/products/'.$product['product_id'].'/price-history') ?>">All</a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $type === 'SELLING' ? 'active' : '' ?>"
           href="<?= url('admin/products/'.$product['product_id'].'/price-history') ?>?type=SELLING">Selling Price</a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $type === 'COST' ? 'active' : '' ?>"
           href="<?= url('admin/products/'.$product['product_id'].'/price-history') ?>?type=COST">Cost Price</a>
      </li>
    </ul>
  </div>
</div>

<!-- History Table -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($history)): ?>
    <div class="text-center py-5 text-muted">
      <i class="bi bi-graph-up display-4 d-block mb-3 opacity-25"></i>
      <h6>No price history yet</h6>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">#</th>
            <th>Type</th>
            <th class="text-end">Old Price</th>
            <th class="text-end">New Price</th>
            <th class="text-end">Change</th>
            <th class="text-end">% Change</th>
            <th>Effective Date</th>
            <th>Reason</th>
            <th>Changed By</th>
            <th>Recorded At</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $i => $ph): ?>
          <tr>
            <td class="ps-3 text-muted small"><?= $i+1 ?></td>
            <td>
              <?php
              $priceType = $ph['price_type'] ?? 'SELLING';
              // Derive from change_reason if price_type column is absent
              if ($priceType === 'SELLING' && !empty($ph['change_reason']) && str_starts_with($ph['change_reason'], '[COST]')) {
                  $priceType = 'COST';
              }
              ?>
              <span class="badge <?= $priceType === 'SELLING' ? 'bg-success' : 'bg-secondary' ?>">
                <?= e($priceType) ?>
              </span>
            </td>
            <td class="text-end font-monospace small">
              <?= $ph['old_price'] > 0 ? 'ETB '.number_format((float)$ph['old_price'],2) : '<span class="text-muted">Initial</span>' ?>
            </td>
            <td class="text-end font-monospace fw-semibold">ETB <?= number_format((float)$ph['new_price'],2) ?></td>
            <td class="text-end font-monospace">
              <?php $diff = $ph['price_difference'] ?? ($ph['new_price'] - $ph['old_price']); ?>
              <span class="<?= $diff >= 0 ? 'text-success' : 'text-danger' ?>">
                <?= $diff >= 0 ? '+' : '' ?><?= number_format((float)$diff, 2) ?>
              </span>
            </td>
            <td class="text-end">
              <?php
              $pct = $ph['percentage_change'] ?? null;
              if ($pct !== null): ?>
              <span class="<?= $pct >= 0 ? 'text-success' : 'text-danger' ?>">
                <?= $pct >= 0 ? '+' : '' ?><?= number_format((float)$pct, 1) ?>%
              </span>
              <?php else: ?>
              <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td class="small"><?= e($ph['effective_date'] ?? date('Y-m-d', strtotime($ph['changed_at'] ?? 'now'))) ?></td>
            <td class="small text-muted" style="max-width:160px;" title="<?= e($ph['reason'] ?? $ph['change_reason'] ?? '') ?>">
              <?php
              $displayReason = $ph['reason'] ?? $ph['change_reason'] ?? '—';
              // Strip [TYPE] prefix if present
              $displayReason = preg_replace('/^\[[A-Z]+\]\s*/', '', $displayReason) ?: '—';
              ?>
              <?= e(mb_strimwidth($displayReason, 0, 40, '…')) ?>
            </td>
            <td class="small text-muted"><?= e($ph['changed_by_username'] ?? '—') ?></td>
            <td class="small text-muted"><?= date_display($ph['changed_at']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($isGM): ?>
<!-- GM info box: direct link to dedicated pricing page -->
<div class="alert alert-success border-0 shadow-sm d-flex align-items-center gap-2 mt-3" role="alert">
  <i class="bi bi-tag-fill fs-5"></i>
  <div>
    <strong>Set / Update Selling Price</strong> — use the dedicated pricing page.
    <a href="<?= url('gm/products/' . (int)$product['product_id'] . '/pricing') ?>"
       class="btn btn-success btn-sm ms-2">
      <i class="bi bi-tag-fill me-1"></i>Go to Pricing
    </a>
  </div>
</div>
<?php else: ?>
<div class="alert alert-secondary border-0 shadow-sm d-flex align-items-center gap-2 mt-3" role="alert">
  <i class="bi bi-lock-fill"></i>
  <div class="small">
    Selling price changes are managed by the <strong>General Manager</strong> only.
    This page shows the audit trail of all past price changes.
  </div>
</div>
<?php endif; ?>
