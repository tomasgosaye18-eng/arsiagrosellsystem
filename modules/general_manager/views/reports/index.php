<?php
/**
 * GM — Business Reports Hub
 * @var array $farmPerf
 * @var array $warehousePerf
 * @var array $shopPerf
 * @var array $transferPipeline
 */
declare(strict_types=1);
$farmPerf        ??= [];
$warehousePerf   ??= [];
$shopPerf        ??= [];
$transferPipeline??= [];
$n = fn($v,$d=0) => is_numeric($v) ? number_format((float)$v,$d) : '—';
$c = fn($v) => 'ETB '.number_format((float)$v, 2);
?>
<div class="mb-4">
  <h5 class="fw-bold mb-1"><i class="bi bi-file-earmark-bar-graph me-2 text-secondary"></i>Business Reports</h5>
  <p class="text-muted small mb-0">All reports are based on live operational data. System administration reports are not shown here.</p>
</div>

<div class="row g-3">
  <!-- Farm Reports -->
  <div class="col-md-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100" style="border-top:4px solid #198754!important;">
      <div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-3">
          <div class="rounded bg-success bg-opacity-10 p-2"><i class="bi bi-flower1 text-success fs-5"></i></div>
          <div><div class="fw-semibold">Farm Reports</div><div class="text-muted" style="font-size:.72rem;">Production &amp; dispatch analysis</div></div>
        </div>
        <div class="row g-1 mb-3">
          <div class="col-6 text-center py-2 rounded border">
            <div class="fw-bold"><?= $n($farmPerf['harvests_this_month'] ?? 0) ?></div>
            <div class="text-muted" style="font-size:.65rem;">Harvests / Month</div>
          </div>
          <div class="col-6 text-center py-2 rounded border">
            <div class="fw-bold"><?= $n($farmPerf['dispatches_this_month'] ?? 0) ?></div>
            <div class="text-muted" style="font-size:.65rem;">Dispatches / Month</div>
          </div>
        </div>
        <div class="d-grid gap-1">
          <a href="<?= url('reports/farm/production') ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-bar-chart me-1"></i>Production Report</a>
          <a href="<?= url('reports/farm/harvest') ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-basket2 me-1"></i>Harvest Report</a>
          <a href="<?= url('reports/farm/dispatch') ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-truck me-1"></i>Dispatch Report</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Warehouse Reports -->
  <div class="col-md-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100" style="border-top:4px solid #0d6efd!important;">
      <div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-3">
          <div class="rounded bg-primary bg-opacity-10 p-2"><i class="bi bi-building text-primary fs-5"></i></div>
          <div><div class="fw-semibold">Warehouse Reports</div><div class="text-muted" style="font-size:.72rem;">Stock, receiving &amp; transfers</div></div>
        </div>
        <div class="row g-1 mb-3">
          <div class="col-6 text-center py-2 rounded border">
            <div class="fw-bold"><?= $n($warehousePerf['total_stock'] ?? 0) ?></div>
            <div class="text-muted" style="font-size:.65rem;">Total Stock</div>
          </div>
          <div class="col-6 text-center py-2 rounded border">
            <div class="fw-bold <?= ($warehousePerf['open_discrepancies'] ?? 0) > 0 ? 'text-danger' : '' ?>"><?= $n($warehousePerf['open_discrepancies'] ?? 0) ?></div>
            <div class="text-muted" style="font-size:.65rem;">Discrepancies</div>
          </div>
        </div>
        <div class="d-grid gap-1">
          <a href="<?= url('reports/warehouse/stock') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-boxes me-1"></i>Stock Report</a>
          <a href="<?= url('reports/warehouse/receiving') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-inbox me-1"></i>Receiving Report</a>
          <a href="<?= url('reports/warehouse/transfers') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-left-right me-1"></i>Transfer Report</a>
          <a href="<?= url('reports/warehouse/discrepancies') ?>" class="btn btn-outline-danger btn-sm"><i class="bi bi-clipboard-x me-1"></i>Discrepancy Report</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Sales Reports -->
  <div class="col-md-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100" style="border-top:4px solid #fd7e14!important;">
      <div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-3">
          <div class="rounded p-2" style="background:rgba(253,126,20,.12);"><i class="bi bi-shop fs-5" style="color:#fd7e14;"></i></div>
          <div><div class="fw-semibold">Sales Reports</div><div class="text-muted" style="font-size:.72rem;">Shop &amp; POS performance</div></div>
        </div>
        <div class="row g-1 mb-3">
          <div class="col-6 text-center py-2 rounded border">
            <div class="fw-bold text-success"><?= $n($shopPerf['sales_this_month'] ?? 0) ?></div>
            <div class="text-muted" style="font-size:.65rem;">Sales / Month</div>
          </div>
          <div class="col-6 text-center py-2 rounded border">
            <div class="fw-bold text-success" style="font-size:.75rem;"><?= $c($shopPerf['revenue_this_month'] ?? 0) ?></div>
            <div class="text-muted" style="font-size:.65rem;">Revenue</div>
          </div>
        </div>
        <div class="d-grid gap-1">
          <a href="<?= url('gm/shops/sales') ?>" class="btn btn-outline-warning btn-sm"><i class="bi bi-bar-chart-line me-1"></i>Sales Overview</a>
          <a href="<?= url('gm/orders') ?>" class="btn btn-outline-info btn-sm"><i class="bi bi-bag-check-fill me-1"></i>Customer Orders</a>
          <a href="<?= url('gm/analytics') ?>" class="btn btn-outline-warning btn-sm"><i class="bi bi-graph-up-arrow me-1"></i>Analytics</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Organisation / Audit -->
  <div class="col-md-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100" style="border-top:4px solid #6f42c1!important;">
      <div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-3">
          <div class="rounded bg-opacity-10 p-2" style="background:rgba(111,66,193,.1);"><i class="bi bi-diagram-3-fill fs-5" style="color:#6f42c1;"></i></div>
          <div><div class="fw-semibold">Organisation &amp; Audit</div><div class="text-muted" style="font-size:.72rem;">Business structure &amp; audit trail</div></div>
        </div>
        <div class="row g-1 mb-3">
          <div class="col-6 text-center py-2 rounded border">
            <div class="fw-bold"><?= $n($transferPipeline['pending_approval'] ?? 0) ?></div>
            <div class="text-muted" style="font-size:.65rem;">Pending Approvals</div>
          </div>
          <div class="col-6 text-center py-2 rounded border">
            <div class="fw-bold"><?= $n($transferPipeline['with_discrepancy'] ?? 0) ?></div>
            <div class="text-muted" style="font-size:.65rem;">Transfer Issues</div>
          </div>
        </div>
        <div class="d-grid gap-1">
          <a href="<?= url('reports/admin/organization') ?>" class="btn btn-sm" style="border-color:#6f42c1;color:#6f42c1;"><i class="bi bi-diagram-3 me-1"></i>Organisation Report</a>
          <a href="<?= url('gm/reports/audit') ?>" class="btn btn-sm" style="border-color:#6f42c1;color:#6f42c1;"><i class="bi bi-clipboard2-data me-1"></i>Audit Summary</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Seed Distribution Reports -->
  <div class="col-md-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100" style="border-top:4px solid #198754!important;">
      <div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-3">
          <div class="rounded bg-success bg-opacity-10 p-2"><i class="bi bi-flower1 text-success fs-5"></i></div>
          <div><div class="fw-semibold">Seed Distribution</div><div class="text-muted" style="font-size:.72rem;">Improved seed distribution reports</div></div>
        </div>
        <?php
        try {
            $sdDb = \AAM\Core\Database::getInstance();
            $sdKpi = $sdDb->fetchOne(
                "SELECT COUNT(*) AS total, COALESCE(SUM(quantity),0) AS total_qty,
                        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending
                 FROM seed_distributions"
            ) ?? [];
        } catch (\Throwable $e) { $sdKpi = []; }
        ?>
        <div class="row g-1 mb-3">
          <div class="col-6 text-center py-2 rounded border">
            <div class="fw-bold"><?= $n($sdKpi['total'] ?? 0) ?></div>
            <div class="text-muted" style="font-size:.65rem;">Total Distributions</div>
          </div>
          <div class="col-6 text-center py-2 rounded border">
            <div class="fw-bold <?= ($sdKpi['pending'] ?? 0) > 0 ? 'text-warning' : '' ?>"><?= $n($sdKpi['pending'] ?? 0) ?></div>
            <div class="text-muted" style="font-size:.65rem;">Pending Approval</div>
          </div>
        </div>
        <div class="d-grid gap-1">
          <a href="<?= url('gm/seed-distribution') ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-speedometer2 me-1"></i>SD Overview</a>
          <a href="<?= url('gm/seed-distribution/distributions') ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-truck me-1"></i>Distributions</a>
          <a href="<?= url('gm/seed-distribution/history') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clock-history me-1"></i>History</a>
          <a href="<?= url('gm/seed-distribution/stock') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-boxes me-1"></i>Seed Stock</a>
        </div>
      </div>
    </div>
  </div>

</div>
