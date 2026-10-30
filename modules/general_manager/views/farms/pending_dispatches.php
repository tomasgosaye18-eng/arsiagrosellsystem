<?php
/**
 * GM — Pending Farm Dispatch Approvals
 *
 * @var array  $dispatches  Rows from FarmDispatchModel::getPendingApproval()
 */
declare(strict_types=1);

$dispatches ??= [];
?>

<!-- ── Page Header ────────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-4"
     style="background:linear-gradient(135deg,#1a4a2e,#0d7a3e);">
    <div class="card-body py-3 text-white d-flex align-items-center gap-3 flex-wrap">
        <div class="rounded-circle bg-white bg-opacity-20 d-flex align-items-center
                    justify-content-center flex-shrink-0"
             style="width:48px;height:48px;">
            <i class="bi bi-hourglass-split fs-4"></i>
        </div>
        <div class="flex-grow-1">
            <h5 class="mb-0 fw-bold">Pending Dispatch Approvals</h5>
            <small class="opacity-75">
                Review and approve or reject farm dispatch requests — General Manager
            </small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= url('gm/farms/dispatches') ?>"
               class="btn btn-outline-light btn-sm">
                <i class="bi bi-list-ul me-1"></i>All Dispatches
            </a>
            <a href="<?= url('gm/farms') ?>"
               class="btn btn-outline-light btn-sm">
                <i class="bi bi-flower1 me-1"></i>All Farms
            </a>
        </div>
    </div>
</div>

<?php $view->flash(); ?>

<!-- ── Workflow Info Banner ───────────────────────────────────────────────── -->
<div class="alert alert-info border-0 shadow-sm mb-4 d-flex gap-3 align-items-start">
    <i class="bi bi-info-circle-fill text-info flex-shrink-0 mt-1"></i>
    <div class="small">
        <strong>Approval Workflow:</strong>
        Farm Manager submits a dispatch request →
        <strong>You review here</strong> →
        If <span class="text-success fw-semibold">Approved</span>, Farm Manager can physically send the goods (stock deducted at dispatch time) →
        If <span class="text-danger fw-semibold">Rejected</span>, dispatch is blocked and no stock is deducted.
        <br>A rejection <strong>reason is mandatory</strong>.
    </div>
</div>

<?php if (empty($dispatches)): ?>
<!-- ── Empty State ────────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
    <div class="text-center py-5 text-muted">
        <i class="bi bi-check-circle d-block fs-1 mb-3 text-success opacity-50"></i>
        <h6 class="fw-semibold mb-1">No pending approvals</h6>
        <p class="mb-0 small">All farm dispatch requests have been reviewed.</p>
    </div>
</div>

<?php else: ?>
<!-- ── Pending Dispatch Cards ─────────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="fw-semibold text-muted small">
        <span class="badge bg-warning text-dark fs-6 me-2"><?= count($dispatches) ?></span>
        dispatch request<?= count($dispatches) !== 1 ? 's' : '' ?> awaiting your decision
    </span>
</div>

<div class="row g-3">
    <?php foreach ($dispatches as $d):
        $daysWaiting = (int)($d['days_waiting'] ?? 0);
        $urgencyClass = $daysWaiting >= 3 ? 'danger' : ($daysWaiting >= 1 ? 'warning' : 'success');
        $urgencyText  = $daysWaiting === 0 ? 'Today' : ($daysWaiting === 1 ? '1 day ago' : "{$daysWaiting} days ago");
    ?>
    <div class="col-12">
        <div class="card border-0 shadow-sm border-start border-4 border-warning">
            <div class="card-body">

                <!-- Header row -->
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-warning text-dark fw-bold font-monospace fs-6">
                                <?= e($d['dispatch_number'] ?? "DSP-{$d['id']}") ?>
                            </span>
                            <span class="badge bg-<?= $urgencyClass ?> bg-opacity-15
                                         text-<?= $urgencyClass ?> border border-<?= $urgencyClass ?>
                                         border-opacity-25 small">
                                <i class="bi bi-clock me-1"></i>Submitted <?= $urgencyText ?>
                            </span>
                        </div>
                        <div class="text-muted small mt-1">
                            Dispatch date: <strong><?= !empty($d['dispatch_date'])
                                ? date('d M Y', strtotime($d['dispatch_date'])) : '—' ?></strong>
                            <?php if (!empty($d['expected_arrival_date'])): ?>
                            · Expected arrival: <strong><?= date('d M Y', strtotime($d['expected_arrival_date'])) ?></strong>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="<?= url('dispatches/' . (int)$d['id']) ?>"
                       class="btn btn-outline-secondary btn-sm" target="_blank">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Full Detail
                    </a>
                </div>

                <!-- Route + requester -->
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="bg-light rounded p-2">
                            <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;">Source Farm</div>
                            <div class="fw-semibold small mt-1">
                                <i class="bi bi-flower1 me-1 text-success"></i>
                                <?= e($d['farm_name'] ?? '—') ?>
                            </div>
                            <?php if (!empty($d['farm_location'])): ?>
                            <div class="text-muted" style="font-size:.7rem;">
                                <i class="bi bi-geo-alt me-1"></i><?= e($d['farm_location']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light rounded p-2">
                            <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;">Destination Warehouse</div>
                            <div class="fw-semibold small mt-1">
                                <i class="bi bi-building me-1 text-primary"></i>
                                <?= e($d['warehouse_name'] ?? '—') ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light rounded p-2">
                            <div class="text-muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;">Requested By</div>
                            <div class="fw-semibold small mt-1">
                                <i class="bi bi-person-fill me-1 text-success"></i>
                                <?= e($d['initiated_by_name'] ?? '—') ?>
                            </div>
                            <?php if (!empty($d['initiated_by_username'])): ?>
                            <div class="text-muted" style="font-size:.7rem;">
                                @<?= e($d['initiated_by_username']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Summary stats -->
                <div class="d-flex gap-3 mb-3 flex-wrap">
                    <div class="text-center bg-success bg-opacity-10 rounded px-3 py-2">
                        <div class="fw-bold text-success"><?= (int)($d['item_count'] ?? 0) ?></div>
                        <div class="text-muted" style="font-size:.68rem;">Products</div>
                    </div>
                    <div class="text-center bg-primary bg-opacity-10 rounded px-3 py-2">
                        <div class="fw-bold text-primary"><?= number_format((float)($d['total_qty'] ?? 0), 2) ?></div>
                        <div class="text-muted" style="font-size:.68rem;">Total Qty</div>
                    </div>
                    <?php if (!empty($d['notes'])): ?>
                    <div class="flex-grow-1 bg-light rounded px-3 py-2">
                        <div class="text-muted" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;">Notes from Farm Manager</div>
                        <div class="small mt-1 fst-italic"><?= e($d['notes']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ── Action Buttons ──────────────────────────────────── -->
                <div class="d-flex gap-2 flex-wrap align-items-center border-top pt-3">

                    <!-- APPROVE -->
                    <form method="POST"
                          action="<?= url('dispatches/' . (int)$d['id'] . '/approve') ?>"
                          onsubmit="return confirm('Approve dispatch <?= e($d['dispatch_number'] ?? '') ?>? The Farm Manager will be able to physically send the goods.');">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <div class="input-group input-group-sm">
                            <input type="text" name="gm_notes" class="form-control"
                                   placeholder="Optional approval note…" style="min-width:220px;">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-lg me-1"></i>Approve
                            </button>
                        </div>
                    </form>

                    <!-- REJECT (opens modal) -->
                    <button type="button"
                            class="btn btn-danger btn-sm"
                            data-bs-toggle="modal"
                            data-bs-target="#rejectModal<?= (int)$d['id'] ?>">
                        <i class="bi bi-x-lg me-1"></i>Reject
                    </button>

                </div><!-- /.actions -->

            </div><!-- /.card-body -->
        </div><!-- /.card -->
    </div><!-- /.col -->

    <!-- ── Reject Modal ──────────────────────────────────────────────────── -->
    <div class="modal fade" id="rejectModal<?= (int)$d['id'] ?>"
         tabindex="-1" aria-labelledby="rejectLabel<?= (int)$d['id'] ?>"
         aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form method="POST"
                      action="<?= url('dispatches/' . (int)$d['id'] . '/reject') ?>">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="rejectLabel<?= (int)$d['id'] ?>">
                            <i class="bi bi-x-circle me-2"></i>Reject Dispatch
                        </h5>
                        <button type="button" class="btn-close btn-close-white"
                                data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small mb-3">
                            You are rejecting
                            <strong><?= e($d['dispatch_number'] ?? '') ?></strong>
                            from <strong><?= e($d['farm_name'] ?? '') ?></strong>.
                            <br>
                            <span class="text-danger">No stock will be deducted.</span>
                            The Farm Manager will see this rejection reason.
                        </p>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">
                                Rejection Reason <span class="text-danger">*</span>
                            </label>
                            <textarea name="rejection_reason"
                                      class="form-control"
                                      rows="4"
                                      placeholder="Provide a clear reason for rejection (e.g. incorrect destination, quantity discrepancy, quality issue…)"
                                      required></textarea>
                            <div class="form-text">
                                This is mandatory and will be visible to the Farm Manager.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="bi bi-x-lg me-1"></i>Confirm Rejection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div><!-- /.modal -->

    <?php endforeach; ?>
</div><!-- /.row -->
<?php endif; ?>
