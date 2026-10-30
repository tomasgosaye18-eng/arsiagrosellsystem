<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 9
 * =========================================================
 * File     : modules/dashboard/views/product_analytics.php
 * Purpose  : Product-focused analytics view.
 *            Top products by revenue, quantity sold,
 *            and production trend.
 * =========================================================
 *
 * @var \AAM\Core\View $view
 */
declare(strict_types=1);
?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-box-seam-fill me-2 text-primary"></i>Product Analytics</h4>
    <p class="text-muted small mb-0">Revenue, sales volume, and production performance by product</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('analytics') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>All Analytics
    </a>
    <a href="<?= url('admin/products') ?>" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-box-seam me-1"></i>Manage Products
    </a>
  </div>
</div>

<!-- ── Loading State ──────────────────────────────────────── -->
<div id="paLoading" class="text-center py-5">
  <div class="spinner-border text-primary" role="status">
    <span class="visually-hidden">Loading…</span>
  </div>
  <p class="text-muted mt-3 small">Loading product analytics…</p>
</div>

<!-- ── Charts ────────────────────────────────────────────── -->
<div id="paContent" style="display:none;">

  <div class="row g-3 mb-4">

    <!-- Top 8 by Revenue -->
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header fw-semibold small">
          <i class="bi bi-trophy-fill me-2 text-warning"></i>Top 8 Products by Revenue — Last 30 Days
        </div>
        <div class="card-body py-3">
          <canvas id="topRevChart" style="max-height:260px;"></canvas>
          <div id="topRevEmpty" class="empty-state d-none">
            <i class="bi bi-box-seam empty-icon"></i>
            <p class="small mb-0">No sales data yet.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Top 8 by Quantity -->
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header fw-semibold small">
          <i class="bi bi-bar-chart-steps me-2 text-success"></i>Top 8 Products by Volume — Last 30 Days
        </div>
        <div class="card-body py-3">
          <canvas id="topQtyChart" style="max-height:260px;"></canvas>
          <div id="topQtyEmpty" class="empty-state d-none">
            <i class="bi bi-box-seam empty-icon"></i>
            <p class="small mb-0">No sales data yet.</p>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- Production Trend -->
  <div class="row g-3">
    <div class="col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-header fw-semibold small">
          <i class="bi bi-flower1 me-2 text-success"></i>Production Trend (Harvest kg) — Last 14 Days
        </div>
        <div class="card-body py-3">
          <canvas id="paProdChart" style="max-height:200px;"></canvas>
          <div id="paProdEmpty" class="empty-state d-none">
            <i class="bi bi-bar-chart empty-icon"></i>
            <p class="small mb-0">No production data yet.</p>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- ── Error State ──────────────────────────────────────── -->
<div id="paError" class="text-center py-5 d-none">
  <i class="bi bi-exclamation-circle-fill display-4 text-danger opacity-50 d-block mb-3"></i>
  <h5 class="fw-semibold">Could not load data</h5>
  <p class="text-muted small">Please refresh or check your connection.</p>
  <button class="btn btn-outline-primary btn-sm" onclick="location.reload()">Retry</button>
</div>

<?php $view->section('scripts'); ?>
<script>
(function () {
  'use strict';

  fetch('<?= url('api/dashboard/charts') ?>', {
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': window.AAM && window.AAM.csrfToken ? window.AAM.csrfToken : ''
    },
    credentials: 'same-origin'
  })
  .then(function(r) { return r.json(); })
  .then(function(resp) {
    document.getElementById('paLoading').style.display = 'none';
    if (!resp.success) { document.getElementById('paError').classList.remove('d-none'); return; }
    document.getElementById('paContent').style.display = '';
    var d = resp.data;
    buildRevChart(d.top_products);
    buildQtyChart(d.top_products);
    buildProdChart(d.production_trend);
  })
  .catch(function() {
    document.getElementById('paLoading').style.display = 'none';
    document.getElementById('paError').classList.remove('d-none');
  });

  var COLORS = ['#0d6efd','#198754','#fd7e14','#dc3545','#6f42c1','#0dcaf0','#20c997','#ffc107'];

  function buildRevChart(data) {
    if (!data || !data.labels || !data.labels.length) {
      document.getElementById('topRevEmpty').classList.remove('d-none'); return;
    }
    new Chart(document.getElementById('topRevChart'), {
      type: 'bar',
      data: {
        labels: data.labels,
        datasets: [{ label: 'Revenue (ETB)', data: data.data, backgroundColor: COLORS, borderRadius: 4 }]
      },
      options: {
        responsive: true, maintainAspectRatio: true, indexAxis: 'y',
        animation: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { beginAtZero: true, ticks: { font: { size: 10 } } },
          y: { ticks: { font: { size: 10 } } }
        }
      }
    });
  }

  function buildQtyChart(data) {
    if (!data || !data.quantities || !data.quantities.length) {
      document.getElementById('topQtyEmpty').classList.remove('d-none'); return;
    }
    new Chart(document.getElementById('topQtyChart'), {
      type: 'bar',
      data: {
        labels: data.labels,
        datasets: [{ label: 'Units Sold', data: data.quantities, backgroundColor: COLORS.map(function(c){ return c + 'cc'; }), borderRadius: 4 }]
      },
      options: {
        responsive: true, maintainAspectRatio: true, indexAxis: 'y',
        animation: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } } },
          y: { ticks: { font: { size: 10 } } }
        }
      }
    });
  }

  function buildProdChart(data) {
    if (!data || !data.labels || !data.labels.length) {
      document.getElementById('paProdEmpty').classList.remove('d-none'); return;
    }
    new Chart(document.getElementById('paProdChart'), {
      type: 'line',
      data: {
        labels: data.labels,
        datasets: [{
          label: 'Harvested (kg)', data: data.data,
          borderColor: '#198754', backgroundColor: 'rgba(25,135,84,.1)',
          borderWidth: 2, pointRadius: 3, tension: 0.35, fill: true
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: true,
        animation: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, ticks: { font: { size: 11 } } },
          x: { ticks: { font: { size: 10 } } }
        }
      }
    });
  }
})();
</script>
<?php $view->endSection(); ?>
