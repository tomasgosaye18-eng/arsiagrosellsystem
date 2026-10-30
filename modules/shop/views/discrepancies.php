<?php
/**
 * Shop Discrepancy Reports
 * @var array  $shop   @var int    $shopId   @var array  $discrepancies
 * @var array  $pagination  @var array  $filters  @var string $userRole
 */
declare(strict_types=1);
$discrepancies ??= [];
$filters       ??= [];
$shop          ??= null;
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold">
    <i class="bi bi-clipboard-x me-2 text-danger"></i>
    Discrepancies — <?= e($shop['name'] ?? 'Shop') ?>
  </h5>
  <a href="<?= url('dashboard') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Dashboard
  </a>
</div>

<div class="alert alert-info small d-flex gap-2 align-items-start mb-3">
  <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
  <div>Discrepancies are recorded when received quantity differs from the dispatched quantity.
  They are automatically created during the receiving process and must be reviewed by the General Manager.</div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-12 col-md-3">
        <input type="search" name="search" class="form-control form-control-sm"
               placeholder="Transfer #, product name…" value="<?= e($filters['search'] ?? '') ?>">
      </div>
      <div class="col-6 col-md-2">
        <select name="status" class="form-select form-select-sm">
          <option value="">All Statuses</option>
          <?php foreach (['open'=>'Open','under_review'=>'Under Review','resolved'=>'Resolved','rejected'=>'Rejected'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= ($filters['status']??'')===$v?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <input type="date" name="date_from" class="form-control form-control-sm"
               value="<?= e($filters['date_from'] ?? '') ?>">
      </div>
      <div class="col-6 col-md-2">
        <input type="date" name="date_to" class="form-control form-control-sm"
               value="<?= e($filters['date_to'] ?? '') ?>">
      </div>
      <div class="col-auto">
        <button class="btn btn-primary btn-sm">Filter</button>
        <a href="<?= url('shop/discrepancies') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($discrepancies)): ?>
    <div class="text-center py-5 text-muted">
      <i class="bi bi-clipboard-check display-6 d-block mb-2 opacity-25"></i>
      <p class="mb-0">No discrepancy reports found.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Report #</th>
            <th>Transfer #</th>
            <th>Product</th>
            <th>Batch</th>
            <th class="text-end">Sent</th>
            <th class="text-end">Received</th>
            <th class="text-end text-danger">Variance</th>
            <th>Reported</th>
            <th>Status</th>
            <th class="pe-3">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($discrepancies as $d):
            // For transfer discrepancies, show the transfer's final status if it's more severe
            $transferStatus = $d['transfer_status'] ?? '';
            $isFinalRej = ($transferStatus === 'final_rejected')
                          || (int)($d['discrepancy_final_rejected'] ?? 0) === 1;
            if ($isFinalRej) {
                $badge = 'dark';
                $label = 'Final Rejected';
            } else {
                $badge = \AAM\Warehouse\Models\DiscrepancyModel::statusBadge($d['status']);
                $label = \AAM\Warehouse\Models\DiscrepancyModel::statusLabel($d['status']);
            }
            $var   = (float)$d['variance_quantity'];
          ?>
          <tr>
            <td class="ps-3 small fw-semibold"><?= e($d['discrepancy_number']) ?></td>
            <td class="small">
              <?php if (!empty($d['transfer_number'])): ?>
              <a href="<?= url('transfers/' . $d['reference_id']) ?>"><?= e($d['transfer_number']) ?></a>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td class="small">
              <div class="fw-semibold"><?= e($d['product_name'] ?? '—') ?></div>
              <div class="text-muted" style="font-size:.68rem;"><?= e($d['product_sku'] ?? '') ?></div>
            </td>
            <td class="small text-muted"><?= e($d['batch_number'] ?? '—') ?></td>
            <td class="small text-end"><?= number_format((float)$d['expected_quantity'], 3) ?></td>
            <td class="small text-end"><?= number_format((float)$d['actual_quantity'], 3) ?></td>
            <td class="small text-end fw-semibold text-danger">
              <?= $var > 0 ? '-' : '+' ?><?= number_format(abs($var), 3) ?>
              <div class="text-muted fw-normal" style="font-size:.65rem;">
                <?= number_format((float)$d['variance_percent'], 1) ?>%
              </div>
            </td>
            <td class="small text-muted"><?= date('d/m/Y', strtotime($d['reported_at'])) ?></td>
            <td><span class="badge bg-<?= $badge ?>"><?= $label ?></span></td>
            <td class="pe-3">
              <a href="<?= url('shop/discrepancies/' . $d['id']) ?>"
                 class="btn btn-outline-secondary btn-sm py-0">View</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
      <small class="text-muted">
        Showing <?= $pagination['from'] ?>–<?= $pagination['to'] ?> of <?= number_format($pagination['total']) ?>
      </small>
      <nav><ul class="pagination pagination-sm mb-0">
        <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
        <li class="page-item <?= $p === ($pagination['page'] ?? 1) ? 'active' : '' ?>">
          <a class="page-link" href="?page=<?= $p ?>&<?= http_build_query(array_filter($filters)) ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>
      </ul></nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
