<?php
/**
 * AAM — Farm Dispatch List
 *
 * @var array  $farm
 * @var array  $dispatches
 * @var array  $pagination
 * @var array  $filters
 * @var string $userRole
 */
declare(strict_types=1);

use AAM\Farm\Models\FarmDispatchModel;

$farmId  = (int)$farm['id'];
$isGM    = ($userRole ?? '') === ROLE_GENERAL_MANAGER;

$statusOptions = [
    FarmDispatchModel::STATUS_PENDING_APPROVAL           => 'Pending GM Approval',
    FarmDispatchModel::STATUS_APPROVED                   => 'Approved',
    FarmDispatchModel::STATUS_REJECTED                   => 'Rejected',
    FarmDispatchModel::STATUS_IN_TRANSIT                 => 'In Transit',
    FarmDispatchModel::STATUS_RECEIVED                   => 'Received',
    FarmDispatchModel::STATUS_PARTIALLY_RECEIVED         => 'Partially Received',
    FarmDispatchModel::STATUS_RECEIVED_WITH_DISCREPANCY  => 'Received — Discrepancy',
    FarmDispatchModel::STATUS_FINAL_REJECTED             => 'Final Rejected',
    FarmDispatchModel::STATUS_CANCELLED                  => 'Cancelled',
];

// Count pending approval for banner
$pendingCount = count(array_filter($dispatches ?? [], fn($d) => ($d['status'] ?? '') === FarmDispatchModel::STATUS_PENDING_APPROVAL));
?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">
            <i class="bi bi-truck me-2 text-success"></i>Dispatches
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="<?= url('farm/dashboard') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= url('farms') ?>">Farms</a></li>
                <li class="breadcrumb-item">
                    <a href="<?= url('farms/' . $farmId) ?>"><?= e($farm['name']) ?></a>
                </li>
                <li class="breadcrumb-item active">Dispatches</li>
            </ol>
        </nav>
    </div>
    <?php if (!$isGM): ?>
    <a href="<?= url('farms/' . $farmId . '/dispatches/create') ?>"
       class="btn btn-success btn-sm">
        <i class="bi bi-plus-lg me-1"></i>New Dispatch Request
    </a>
    <?php endif; ?>
</div>

<?php $view->flash(); ?>

<!-- ── Pending Approval Banner (Farm Manager only) ────── -->
<?php if (!$isGM && $pendingCount > 0): ?>
<div class="alert alert-warning border-0 shadow-sm mb-3 d-flex gap-2 align-items-center">
    <i class="bi bi-hourglass-split flex-shrink-0"></i>
    <div class="small">
        <strong><?= $pendingCount ?> dispatch request<?= $pendingCount !== 1 ? 's' : '' ?></strong>
        awaiting General Manager approval.
        You will be able to dispatch them once approved.
    </div>
</div>
<?php endif; ?>

<!-- ── Workflow Info (Farm Manager) ──────────────────── -->
<?php if (!$isGM): ?>
<div class="alert alert-light border shadow-sm mb-3 py-2 small">
    <i class="bi bi-info-circle me-1 text-primary"></i>
    <strong>Dispatch Workflow:</strong>
    Create request
    → <span class="text-warning fw-semibold">Pending GM Approval</span>
    → <span class="text-success fw-semibold">Approved</span>
    → You can dispatch
    → <span class="text-info fw-semibold">In Transit</span>
    → Warehouse receives
</div>
<?php endif; ?>

<!-- ── Filters ────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2 px-3">
        <form method="GET" action="<?= url('farms/' . $farmId . '/dispatches') ?>"
              class="row g-2 align-items-end">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="search" class="form-control" name="search"
                           value="<?= e($filters['search'] ?? '') ?>"
                           placeholder="Dispatch number…">
                </div>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="status">
                    <option value="">All Status</option>
                    <?php foreach ($statusOptions as $k => $v): ?>
                    <option value="<?= e($k) ?>"
                        <?= ($filters['status'] ?? '') === $k ? 'selected' : '' ?>>
                        <?= $v ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <input type="date" class="form-control" name="date_from"
                           value="<?= e($filters['date_from'] ?? '') ?>">
                    <input type="date" class="form-control" name="date_to"
                           value="<?= e($filters['date_to'] ?? '') ?>">
                </div>
            </div>
            <div class="col-auto d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <?php if (array_filter($filters)): ?>
                <a href="<?= url('farms/' . $farmId . '/dispatches') ?>"
                   class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-x-lg"></i>
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- ── Table ──────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($dispatches)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-truck display-4 d-block mb-3 opacity-25"></i>
            <h6 class="fw-semibold">No dispatches found</h6>
            <?php if (array_filter($filters)): ?>
            <a href="<?= url('farms/' . $farmId . '/dispatches') ?>"
               class="btn btn-outline-secondary btn-sm mt-2">Clear Filters</a>
            <?php elseif (!$isGM): ?>
            <a href="<?= url('farms/' . $farmId . '/dispatches/create') ?>"
               class="btn btn-success btn-sm mt-2">
                <i class="bi bi-plus-lg me-1"></i>Create First Dispatch Request
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Dispatch #</th>
                        <th>Destination</th>
                        <th class="text-end">Items</th>
                        <th class="text-end">Total Qty</th>
                        <th>Dispatch Date</th>
                        <th>Status</th>
                        <th>Requested By</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dispatches as $d):
                        $status      = $d['status'] ?? '';
                        $badgeClass  = FarmDispatchModel::getStatusBadgeClass($status);
                        $statusLabel = FarmDispatchModel::getStatusLabel($status);
                        $canCancel   = FarmDispatchModel::isCancellable($status);
                        $canDispatch = !$isGM && FarmDispatchModel::isDispatchable($status);
                        $isPending   = $status === FarmDispatchModel::STATUS_PENDING_APPROVAL;
                        $isRejected  = $status === FarmDispatchModel::STATUS_REJECTED;
                        $isFinalRej  = $status === FarmDispatchModel::STATUS_FINAL_REJECTED;
                    ?>
                    <tr class="<?= $isFinalRej ? 'table-dark' : ($isRejected ? 'table-danger' : ($isPending ? 'table-warning' : '')) ?>">
                        <td class="ps-3">
                            <a href="<?= url('dispatches/' . (int)$d['id']) ?>"
                               class="text-decoration-none fw-semibold font-monospace small">
                                <?= e($d['dispatch_number'] ?? "DSP-{$d['id']}") ?>
                            </a>
                        </td>
                        <td class="small">
                            <i class="bi bi-building me-1 text-muted"></i>
                            <?= e($d['warehouse_name'] ?? '—') ?>
                        </td>
                        <td class="text-end small"><?= (int)($d['item_count'] ?? 0) ?></td>
                        <td class="text-end small fw-semibold">
                            <?= number_format((float)($d['total_qty'] ?? 0), 2) ?>
                        </td>
                        <td class="small text-muted">
                            <?= !empty($d['dispatch_date'])
                                ? date('d M Y', strtotime($d['dispatch_date'])) : '—' ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $badgeClass ?>-subtle text-<?= $badgeClass ?> small">
                                <?php if ($isPending): ?>
                                <i class="bi bi-hourglass-split me-1"></i>
                                <?php elseif ($isRejected): ?>
                                <i class="bi bi-x-circle me-1"></i>
                                <?php elseif ($isFinalRej): ?>
                                <i class="bi bi-slash-circle-fill me-1"></i>
                                <?php elseif ($status === FarmDispatchModel::STATUS_APPROVED): ?>
                                <i class="bi bi-check-circle me-1"></i>
                                <?php endif; ?>
                                <?= $statusLabel ?>
                            </span>
                            <?php if ($isPending && !$isGM): ?>
                            <div class="text-muted" style="font-size:.65rem;">Awaiting GM review</div>
                            <?php elseif ($isFinalRej): ?>
                            <div class="text-danger" style="font-size:.65rem;">Stock restored to farm</div>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= e($d['initiated_by_name'] ?? '—') ?></td>
                        <td class="text-end pe-3">
                            <div class="d-flex justify-content-end gap-1">
                                <!-- View -->
                                <a href="<?= url('dispatches/' . (int)$d['id']) ?>"
                                   class="btn btn-sm btn-outline-secondary" title="View">
                                    <i class="bi bi-eye-fill"></i>
                                </a>

                                <!-- Dispatch (FM only, approved status only) -->
                                <?php if ($canDispatch): ?>
                                <form method="POST"
                                      action="<?= url('dispatches/' . (int)$d['id'] . '/dispatch') ?>"
                                      onsubmit="return confirm('Mark this dispatch as sent? Farm stock will be deducted.');">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <button type="submit" class="btn btn-sm btn-success" title="Send / Dispatch">
                                        <i class="bi bi-send-fill"></i>
                                    </button>
                                </form>
                                <?php endif; ?>

                                <!-- Cancelled indicator when blocked -->
                                <?php if ($isPending && !$isGM): ?>
                                <span class="btn btn-sm btn-outline-warning disabled"
                                      title="Awaiting GM approval — cannot dispatch yet">
                                    <i class="bi bi-hourglass-split"></i>
                                </span>
                                <?php endif; ?>

                                <!-- Cancel -->
                                <?php if ($canCancel): ?>
                                <form method="POST"
                                      action="<?= url('dispatches/' . (int)$d['id'] . '/cancel') ?>"
                                      onsubmit="return confirm('Cancel this dispatch?');">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                </form>
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
                Showing <strong><?= count($dispatches) ?></strong>
                of <strong><?= $pagination['total'] ?></strong>
            </div>
            <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($pg = 1; $pg <= $pagination['totalPages']; $pg++): ?>
                    <li class="page-item <?= $pg === ($pagination['page'] ?? 1) ? 'active' : '' ?>">
                        <a class="page-link"
                           href="?page=<?= $pg ?>&<?= http_build_query(array_filter($filters)) ?>">
                            <?= $pg ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
