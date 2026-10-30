<?php
/**
 * Sales History — Phase 12
 * @var array  $shop         @var array  $sales
 * @var array  $pagination   @var array  $filters
 * @var array  $dailyTotals  @var string $userRole
 */
declare(strict_types=1);
$sales        ??= [];
$shop         ??= [];
$filters      ??= [];
$pagination   ??= [];
$dailyTotals  ??= [];

use AAM\POS\Models\SalesModel;
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h5 class="mb-0 fw-bold">
        <i class="bi bi-clock-history me-2 text-primary"></i>Sales History
        <?php if (!empty($shop['name'])): ?>
        <small class="text-muted fw-normal fs-6">— <?= e($shop['name']) ?></small>
        <?php endif; ?>
    </h5>
    <a href="<?= url('pos') ?>" class="btn btn-success btn-sm">
        <i class="bi bi-cart3 me-1"></i>POS Terminal
    </a>
</div>

<!-- Day Summary KPIs -->
<div class="row g-3 mb-3">
    <?php
    $kpis = [
        ['Today Transactions', $dailyTotals['total_transactions'] ?? 0, 'bi-receipt', 'primary'],
        ['Today Sales (ETB)',  number_format((float)($dailyTotals['total_sales'] ?? 0), 2), 'bi-cash-stack', 'success'],
        ['Completed',          $dailyTotals['completed'] ?? 0, 'bi-check-circle', 'success'],
        ['Voided',             $dailyTotals['voided'] ?? 0, 'bi-x-circle', 'danger'],
    ];
    foreach ($kpis as [$label, $value, $icon, $color]):
    ?>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <small class="text-muted"><?= $label ?></small>
                    <i class="bi <?= $icon ?> text-<?= $color ?>"></i>
                </div>
                <div class="fw-bold fs-5"><?= $value ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-sm-auto">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm"
                       value="<?= e($filters['date_from'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="col-sm-auto">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm"
                       value="<?= e($filters['date_to'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="col-sm-auto">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="completed" <?= ($filters['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="voided"    <?= ($filters['status'] ?? '') === 'voided'    ? 'selected' : '' ?>>Voided</option>
                </select>
            </div>
            <div class="col-sm-3">
                <label class="form-label small mb-1">Search</label>
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Sale # / Receipt #" value="<?= e($filters['search'] ?? '') ?>">
            </div>
            <div class="col-sm-auto">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-search me-1"></i>Filter
                </button>
                <a href="<?= url('pos/history') ?>" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Sales Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($sales)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-receipt display-5 d-block mb-2 opacity-25"></i>
            No sales found for the selected filters.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 small">Receipt #</th>
                        <th class="small">Sale #</th>
                        <th class="small">Date / Time</th>
                        <th class="small">Seller</th>
                        <th class="text-end small">Items</th>
                        <th class="text-end small">Total</th>
                        <th class="text-end small">Cash</th>
                        <th class="text-end small">Change</th>
                        <th class="text-center small">Method</th>
                        <th class="text-center small">Status</th>
                        <th class="text-center small pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $s):
                        $badge = SalesModel::statusBadge($s['status']);
                        $label = SalesModel::statusLabel($s['status']);
                    ?>
                    <tr>
                        <td class="ps-3 small fw-semibold"><?= e($s['receipt_number']) ?></td>
                        <td class="small text-muted"><?= e($s['sale_number']) ?></td>
                        <td class="small"><?= date('d/m/Y H:i', strtotime($s['completed_at'])) ?></td>
                        <td class="small"><?= e(($s['seller_first_name'] ?? '') . ' ' . ($s['seller_last_name'] ?? '')) ?></td>
                        <td class="text-end small"><?= (int)$s['total_items'] ?></td>
                        <td class="text-end small">ETB <?= number_format((float)$s['total_amount'], 2) ?></td>
                        <td class="text-end small">ETB <?= number_format((float)$s['cash_received'], 2) ?></td>
                        <td class="text-end small">ETB <?= number_format((float)$s['change_given'], 2) ?></td>
                        <td class="text-center">
                            <span class="badge bg-success-subtle text-success border border-success-subtle small">
                                <?= strtoupper($s['payment_method'] ?? 'CASH') ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?= $badge ?>"><?= $label ?></span>
                        </td>
                        <td class="text-center pe-3">
                            <a href="<?= url('pos/receipt/' . $s['id']) ?>"
                               class="btn btn-outline-primary btn-sm py-0 px-2"
                               target="_blank" title="View Receipt">
                                <i class="bi bi-receipt small"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
            <small class="text-muted">
                Showing <?= $pagination['from'] ?? 1 ?>–<?= $pagination['to'] ?? count($sales) ?>
                of <?= $pagination['total'] ?? count($sales) ?> sales
            </small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($pg = 1; $pg <= $pagination['totalPages']; $pg++): ?>
                    <li class="page-item <?= $pg === ($pagination['page'] ?? 1) ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $pg])) ?>">
                            <?= $pg ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
