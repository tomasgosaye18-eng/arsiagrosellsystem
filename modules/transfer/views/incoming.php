<?php
/**
 * Incoming Transfers — Destination Shop View (Salesperson)
 * @var array  $transfers  @var array $pagination @var array $filters
 * @var array  $shop       @var int   $shopId     @var string $userRole
 */
declare(strict_types=1);
$transfers ??= [];
$filters   ??= [];
$shop      ??= null;
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0 fw-bold">
    <i class="bi bi-box-arrow-in-down me-2 text-success"></i>Incoming Transfers
    <?php if ($shop): ?>
    <small class="text-muted fw-normal ms-1">— <?= e($shop['name']) ?></small>
    <?php endif; ?>
  </h5>
</div>

<div class="alert alert-info d-flex gap-2 align-items-start small mb-3">
  <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
  <div>
    This view shows transfers addressed to your shop. When goods arrive, click
    <strong>Receive</strong> on a transfer with status <span class="badge bg-primary">In Transit</span>.
    If you enter a quantity that differs from what was dispatched, a <strong>discrepancy reason is required</strong>
    and the General Manager must approve before shop inventory is updated.
    Transfers with status <span class="badge bg-warning text-dark">Pending GM Approval</span>
    are awaiting the GM's decision.
    If the GM rejects, you can <strong>Re-submit</strong> with corrected information.
  </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-12 col-md-4">
        <input type="search" name="search" class="form-control form-control-sm"
               placeholder="Transfer # or warehouse name…"
               value="<?= e($filters['search'] ?? '') ?>">
      </div>
      <div class="col-6 col-md-2">
        <select name="status" class="form-select form-select-sm">
          <option value="">All Statuses</option>
          <?php foreach ([
            'in_transit'                => 'In Transit',
            'discrepancy_pending'       => 'Pending GM Approval',
            'gm_rejected'               => 'GM Rejected',
            'received_with_discrepancy' => 'Completed w/ Discrepancy',
            'final_rejected'            => 'Final Rejected',
            'pending'                   => 'Pending',
            'approved'                  => 'Approved',
            'received'                  => 'Received',
            'rejected'                  => 'Rejected by GM',
            'cancelled'                 => 'Cancelled',
          ] as $val => $lbl): ?>
          <option value="<?= $val ?>" <?= ($filters['status'] ?? '') === $val ? 'selected' : '' ?>>
            <?= $lbl ?>
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
        <a href="<?= url('transfers/incoming') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($transfers)): ?>
      <div class="text-center py-5 text-muted">
        <i class="bi bi-box-arrow-in-down display-6 d-block mb-2 opacity-25"></i>
        <p class="mb-0">No incoming transfers found.</p>
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th class="ps-3">Transfer #</th>
            <th>From Warehouse</th>
            <th>Items</th>
            <th>Total Qty</th>
            <th>Dispatch Date</th>
            <th>Initiated By</th>
            <th>Status</th>
            <th class="pe-3 text-end">Action</th>
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
            <td class="small"><?= e($t['warehouse_name'] ?? '—') ?></td>
            <td class="small text-muted"><?= (int)($t['total_items'] ?? 0) ?></td>
            <td class="small fw-semibold"><?= number_format((float)$t['total_quantity'], 1) ?></td>
            <td class="small"><?= $t['transfer_date'] ? date('d/m/Y', strtotime($t['transfer_date'])) : '—' ?></td>
            <td class="small text-muted"><?= e($t['initiated_by_name'] ?? '—') ?></td>
            <td><span class="badge bg-<?= $badge ?>"><?= $label ?></span></td>
            <td class="pe-3 text-end">
              <a href="<?= url('transfers/' . $t['id']) ?>" class="btn btn-outline-secondary btn-sm py-0">View</a>
              <?php if ($t['status'] === 'in_transit'): ?>
              <a href="<?= url('transfers/' . $t['id'] . '/receive') ?>"
                 class="btn btn-success btn-sm py-0">
                <i class="bi bi-check2-circle me-1"></i>Receive
              </a>
              <?php elseif ($t['status'] === 'discrepancy_pending'): ?>
              <span class="badge bg-danger px-2 py-1" title="Awaiting General Manager approval">
                <i class="bi bi-hourglass-split me-1"></i>Pending GM
              </span>
              <?php elseif ($t['status'] === 'gm_rejected'): ?>
              <a href="<?= url('transfers/' . $t['id'] . '/receive') ?>"
                 class="btn btn-warning btn-sm py-0">
                <i class="bi bi-arrow-repeat me-1"></i>Re-submit
              </a>
              <?php elseif ($t['status'] === 'received_with_discrepancy'): ?>
              <span class="badge bg-success px-2 py-1">
                <i class="bi bi-check2-circle me-1"></i>Completed
              </span>
              <?php elseif ($t['status'] === 'final_rejected'): ?>
              <span class="badge bg-dark px-2 py-1" title="Transfer permanently closed after 2 GM rejections">
                <i class="bi bi-slash-circle-fill me-1"></i>Final Rejected
              </span>
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
