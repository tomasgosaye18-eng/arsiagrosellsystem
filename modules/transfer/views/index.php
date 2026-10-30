<?php
/**
 * Transfer List — Phase 10
 * @var array $transfers @var array $pagination @var array $filters
 * @var array $shops @var array $warehouse @var int $warehouseId @var string $userRole
 */
declare(strict_types=1);
$transfers  ??= [];
$filters    ??= [];
$shops      ??= [];
$canCreate  = in_array($userRole ?? '', [ROLE_WAREHOUSE_MANAGER], true);
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold">
    <i class="bi bi-arrow-right-circle me-2 text-primary"></i>Transfers
  </h5>
  <?php if ($canCreate): ?>
  <a href="<?= url('transfers/create') ?>" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i>New Transfer
  </a>
  <?php endif; ?>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-12 col-md-3">
        <input type="search" name="search" class="form-control form-control-sm"
               placeholder="Transfer # or shop name…"
               value="<?= e($filters['search'] ?? '') ?>">
      </div>
      <div class="col-6 col-md-2">
        <select name="shop_id" class="form-select form-select-sm">
          <option value="">All Shops</option>
          <?php foreach ($shops as $s): ?>
          <option value="<?= $s['id'] ?>" <?= ($filters['shop_id'] ?? '') == $s['id'] ? 'selected' : '' ?>>
            <?= e($s['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <select name="status" class="form-select form-select-sm">
          <option value="">All Statuses</option>
          <?php foreach ([
            'draft'                     => 'Draft',
            'pending'                   => 'Pending Approval',
            'approved'                  => 'Approved',
            'in_transit'                => 'In Transit',
            'received'                  => 'Received',
            'partially_received'        => 'Partially Received',
            'discrepancy'               => 'Discrepancy',
            'discrepancy_pending'       => 'Pending GM Approval',
            'gm_rejected'               => 'GM Rejected — Awaiting Correction',
            'received_with_discrepancy' => 'Completed w/ Discrepancy',
            'final_rejected'            => 'Final Rejected',
            'rejected'                  => 'Rejected by GM',
            'cancelled'                 => 'Cancelled',
          ] as $s => $sl): ?>
          <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>>
            <?= $sl ?>
          </option>
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
        <a href="<?= url('transfers') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($transfers)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-arrow-right-circle display-6 d-block mb-2 opacity-25"></i>
        <p class="mb-0">No transfers found.</p>
        <?php if ($canCreate): ?>
        <a href="<?= url('transfers/create') ?>" class="btn btn-primary btn-sm mt-2">Create First Transfer</a>
        <?php endif; ?>
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Transfer #</th>
            <th>Shop</th>
            <th>Items</th>
            <th>Total Qty</th>
            <th>Date</th>
            <th>Initiated By</th>
            <th>Status</th>
            <th class="pe-3 text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($transfers as $t):
            $badge = \AAM\Warehouse\Models\TransferModel::statusBadge($t['status']);
            $label = \AAM\Warehouse\Models\TransferModel::statusLabel($t['status']);
          ?>
          <tr>
            <td class="ps-3 small fw-semibold">
              <a href="<?= url('transfers/' . $t['id']) ?>"><?= e($t['transfer_number']) ?></a>
            </td>
            <td class="small"><?= e($t['shop_name'] ?? '—') ?></td>
            <td class="small text-muted"><?= (int)($t['total_items'] ?? 0) ?></td>
            <td class="small fw-semibold"><?= number_format((float)$t['total_quantity'], 1) ?></td>
            <td class="small"><?= $t['transfer_date'] ? date('d/m/Y', strtotime($t['transfer_date'])) : '—' ?></td>
            <td class="small text-muted"><?= e($t['initiated_by_name'] ?? '—') ?></td>
            <td><span class="badge bg-<?= $badge ?>"><?= $label ?></span></td>
            <td class="pe-3 text-end">
              <a href="<?= url('transfers/' . $t['id']) ?>" class="btn btn-outline-secondary btn-sm py-0">View</a>
              <?php
              // GM / Admin: quick Approve button on pending transfers
              if ($t['status'] === 'pending'
                  && in_array($userRole ?? '', [ROLE_ADMIN, ROLE_GENERAL_MANAGER], true)): ?>
              <a href="<?= url('transfers/' . $t['id']) ?>"
                 class="btn btn-success btn-sm py-0">Review</a>
              <?php endif; ?>
              <?php
              // Warehouse Manager ONLY: Dispatch button — only on APPROVED transfers
              // GM cannot dispatch; they only approve/reject
              if ($t['status'] === 'approved'
                  && in_array($userRole ?? '', [ROLE_WAREHOUSE_MANAGER], true)): ?>
              <form method="POST" action="<?= url('transfers/' . $t['id'] . '/dispatch') ?>"
                    class="d-inline" onsubmit="return confirm('Dispatch this transfer?\nWarehouse stock will be deducted.')">
                <?php csrf_input(); ?>
                <button class="btn btn-primary btn-sm py-0"><i class="bi bi-truck me-1"></i>Dispatch</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
      <small class="text-muted">
        Showing <?= $pagination['from'] ?>–<?= $pagination['to'] ?> of <?= number_format($pagination['total']) ?> records
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

<?php if (!file_exists(MODULES_PATH . '/transfer/views/stub.php')): ?>
<?php endif; ?>
