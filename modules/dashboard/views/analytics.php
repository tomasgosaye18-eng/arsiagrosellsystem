<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 9
 * =========================================================
 * File     : modules/dashboard/views/analytics.php
 * Purpose  : Business Analytics — Charts, KPI trends,
 *            shop comparison, product performance.
 *            Data loads from /api/dashboard/charts.
 * =========================================================
 *
 * @var \AAM\Core\View $view
 */
declare(strict_types=1);
?>

<!-- ── Page Header ──────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Business Analytics</h4>
    <p class="text-muted small mb-0">Visual insights — sales trends, shop performance, product rankings</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('reports') ?>" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-file-earmark-bar-graph me-1"></i>Reports
    </a>
  </div>
</div>

<!-- ── Loading State ──────────────────────────────────────── -->
<div id="analyticsLoading" class="text-center py-5">
  <div class="spinner-border text-primary" role="status">
    <span class="visually-hidden">Loading…</span>
  </div>
  <p class="text-muted mt-3 small">Loading analytics data…</p>
</div>

<!-- ── Charts (hidden until loaded) ──────────────────────── -->
<div id="analyticsContent" style="display:none;">

  <!-- Row 1: Sales Trend + Shop Performance -->
  <div class="row g-3 mb-4">

    <div class="col-lg-7">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header fw-semibold small">
          <i class="bi bi-bar-chart-fill me-2 text-success"></i>Sales Trend — Last 14 Days
        </div>
        <div class="card-body py-3">
          <canvas id="salesTrendChart" style="max-height:220px;"></canvas>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header fw-semibold small">
          <i class="bi bi-shop me-2 text-warning"></i>Shop Performance — Last 30 Days
        </div>
        <div class="card-body py-3">
          <canvas id="shopPerfChart" style="max-height:220px;"></canvas>
          <div id="shopPerfEmpty" class="text-center py-4 text-muted d-none small">No shop sales data yet.</div>
        </div>
      </div>
    </div>

  </div>

  <!-- Row 2: Top Products + Stock Movement -->
  <div class="row g-3 mb-4">

    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header fw-semibold small">
          <i class="bi bi-box-seam-fill me-2 text-primary"></i>Top Products by Revenue — Last 30 Days
        </div>
        <div class="card-body py-3">
          <canvas id="topProductsChart" style="max-height:220px;"></canvas>
          <div id="topProductsEmpty" class="text-center py-4 text-muted d-none small">No product sales data yet.</div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header fw-semibold small">
          <i class="bi bi-arrow-left-right me-2 text-info"></i>Warehouse Stock Movement — Last 14 Days
        </div>
        <div class="card-body py-3">
          <canvas id="stockMvtChart" style="max-height:220px;"></canvas>
          <div id="stockMvtEmpty" class="text-center py-4 text-muted d-none small">No movement data yet.</div>
        </div>
      </div>
    </div>

  </div>

  <!-- Row 3: Production Trend -->
  <div class="row g-3">
    <div class="col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-header fw-semibold small">
          <i class="bi bi-flower1 me-2 text-success"></i>Farm Production Trend — Last 14 Days
        </div>
        <div class="card-body py-3">
          <canvas id="productionChart" style="max-height:180px;"></canvas>
          <div id="productionEmpty" class="text-center py-4 text-muted d-none small">No production data yet.</div>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- ── Error State ──────────────────────────────────────── -->
<div id="analyticsError" class="text-center py-5 d-none">
  <i class="bi bi-exclamation-circle-fill display-4 text-danger opacity-50 d-block mb-3"></i>
  <h5 class="fw-semibold">Could not load analytics data</h5>
  <p class="text-muted small">Please refresh the page or check your connection.</p>
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
    document.getElementById('analyticsLoading').style.display = 'none';
    if (!resp.success) {
      document.getElementById('analyticsError').classList.remove('d-none');
      return;
    }
    document.getElementById('analyticsContent').style.display = '';
    var d = resp.data;
    buildSalesChart(d.sales_trend);
    buildShopChart(d.shop_performance);
    buildTopProductsChart(d.top_products);
    buildStockMvtChart(d.stock_movement);
    buildProductionChart(d.production_trend);
  })
  .catch(function() {
    document.getElementById('analyticsLoading').style.display = 'none';
    document.getElementById('analyticsError').classList.remove('d-none');
  });

  function buildSalesChart(data) {
    if (!data || !data.labels || !data.labels.length) return;
    new Chart(document.getElementById('salesTrendChart'), {
      type: 'bar',
      data: {
        labels: data.labels,
        datasets: [
          { label: 'Revenue (ETB)', data: data.data, backgroundColor: 'rgba(25,135,84,.7)', borderRadius: 4, yAxisID: 'y' },
          { label: 'Transactions', data: data.counts, type: 'line', borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,.08)', borderWidth: 2, pointRadius: 3, tension: 0.3, fill: true, yAxisID: 'y1' }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: true,
        animation: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top', labels: { font: { size: 11 } } } },
        scales: {
          y:  { beginAtZero: true, ticks: { font: { size: 11 } } },
          y1: { beginAtZero: true, position: 'right', ticks: { precision: 0, font: { size: 11 } }, grid: { drawOnChartArea: false } },
          x:  { ticks: { font: { size: 10 } } }
        }
      }
    });
  }

  function buildShopChart(data) {
    if (!data || !data.labels || !data.labels.length) {
      document.getElementById('shopPerfEmpty').classList.remove('d-none');
      return;
    }
    new Chart(document.getElementById('shopPerfChart'), {
      type: 'doughnut',
      data: {
        labels: data.labels,
        datasets: [{ data: data.data, backgroundColor: data.colors, borderWidth: 2 }]
      },
      options: {
        responsive: true, maintainAspectRatio: true, cutout: '55%',
        animation: false,
        plugins: {
          legend: { position: 'right', labels: { font: { size: 11 }, boxWidth: 12 } },
          tooltip: {
            callbacks: {
              label: function(ctx) {
                return ' ' + ctx.label + ': ETB ' + Number(ctx.raw).toLocaleString();
              }
            }
          }
        }
      }
    });
  }

  function buildTopProductsChart(data) {
    if (!data || !data.labels || !data.labels.length) {
      document.getElementById('topProductsEmpty').classList.remove('d-none');
      return;
    }
    new Chart(document.getElementById('topProductsChart'), {
      type: 'bar',
      data: {
        labels: data.labels,
        datasets: [{
          label: 'Revenue (ETB)',
          data: data.data,
          backgroundColor: [
            '#0d6efd','#198754','#fd7e14','#dc3545','#6f42c1','#0dcaf0','#20c997','#ffc107'
          ],
          borderRadius: 4
        }]
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

  function buildStockMvtChart(data) {
    if (!data || !data.labels || !data.labels.length) {
      document.getElementById('stockMvtEmpty').classList.remove('d-none');
      return;
    }
    new Chart(document.getElementById('stockMvtChart'), {
      type: 'bar',
      data: {
        labels: data.labels,
        datasets: [
          { label: 'In', data: data.in, backgroundColor: 'rgba(25,135,84,.7)', borderRadius: 3 },
          { label: 'Out', data: data.out, backgroundColor: 'rgba(220,53,69,.6)', borderRadius: 3 }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: true,
        animation: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top', labels: { font: { size: 11 } } } },
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0, font: { size: 11 } } },
          x: { ticks: { font: { size: 10 } } }
        }
      }
    });
  }

  function buildProductionChart(data) {
    if (!data || !data.labels || !data.labels.length) {
      document.getElementById('productionEmpty').classList.remove('d-none');
      return;
    }
    new Chart(document.getElementById('productionChart'), {
      type: 'line',
      data: {
        labels: data.labels,
        datasets: [{
          label: 'Harvested (kg)',
          data: data.data,
          borderColor: '#198754',
          backgroundColor: 'rgba(25,135,84,.1)',
          borderWidth: 2,
          pointRadius: 3,
          tension: 0.35,
          fill: true
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
