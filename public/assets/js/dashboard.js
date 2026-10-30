/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * File     : public/assets/js/dashboard.js
 * Purpose  : Dashboard chart initialisation and KPI logic.
 *            Uses Chart.js (loaded in master.php).
 *            Fetches data from /api/dashboard/charts via AJAX.
 *            Role-aware: only initialises charts present on page.
 * =========================================================
 */

'use strict';

window.AAMDashboard = window.AAMDashboard || {};

// ─── Bootstrap ───────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    AAMDashboard.init();
});

// ─── Defaults ────────────────────────────────────────────────────────────────
AAMDashboard.defaults = {
    colors: {
        primary:   '#0d6efd',
        success:   '#198754',
        warning:   '#ffc107',
        danger:    '#dc3545',
        info:      '#0dcaf0',
        secondary: '#6c757d',
        light:     '#f8f9fa',
        grid:      'rgba(0,0,0,.05)',
    },
    fontFamily: "'Inter', -apple-system, sans-serif",
    fontSize:   12,
    animation:  { duration: 600, easing: 'easeInOutQuart' },
};

/**
 * Return theme-aware chart colour values by reading CSS variables
 * set by theme.css on <html data-theme="...">
 */
AAMDashboard.themeColors = function () {
    var style     = getComputedStyle(document.documentElement);
    var gridColor = style.getPropertyValue('--chart-grid').trim()    || 'rgba(0,0,0,.05)';
    var textColor = style.getPropertyValue('--chart-text').trim()    || '#6c757d';
    return { grid: gridColor, text: textColor };
};

// ─── Chart.js Global Defaults ────────────────────────────────────────────────
AAMDashboard.applyGlobalDefaults = function () {
    if (typeof Chart === 'undefined') return;

    var tc = AAMDashboard.themeColors();

    Chart.defaults.font.family = AAMDashboard.defaults.fontFamily;
    Chart.defaults.font.size   = AAMDashboard.defaults.fontSize;
    Chart.defaults.color       = tc.text;
    Chart.defaults.plugins.legend.labels.boxWidth = 12;
    Chart.defaults.plugins.legend.labels.padding  = 16;
    Chart.defaults.animation   = AAMDashboard.defaults.animation;
    Chart.defaults.responsive  = true;
    Chart.defaults.maintainAspectRatio = false;

    // Keep grid colour in sync with theme
    AAMDashboard.defaults.colors.grid = tc.grid;
};

// ─── Initialise ──────────────────────────────────────────────────────────────
AAMDashboard.init = function () {
    AAMDashboard.applyGlobalDefaults();

    // Initialise each chart canvas if it exists on the current page
    AAMDashboard.initSalesChart();
    AAMDashboard.initInventoryChart();
    AAMDashboard.initProductPieChart();
    AAMDashboard.initFarmProductionChart();
    AAMDashboard.initRevenueLineChart();

    // Animate KPI numbers on load
    AAMDashboard.animateKpiCounters();

    // Refresh charts every 5 minutes
    setInterval(AAMDashboard.refreshAll, 300000);
};

// ─── Sales Trend Bar Chart (Admin / General Manager dashboard) ───────────────
AAMDashboard.initSalesChart = function () {
    const canvas = document.getElementById('salesTrendChart');
    if (!canvas) return;

    AAMDashboard.showSkeleton(canvas);

    AAM.ajax('GET', '/api/dashboard/charts?type=sales_trend', null, function (res) {
        AAMDashboard.hideSkeleton(canvas);
        if (!res.success || !res.data) return;

        const data = res.data;
        AAMDashboard.charts = AAMDashboard.charts || {};
        AAMDashboard.charts.sales = new Chart(canvas, {
            type: 'bar',
            data: {
                labels:   data.labels,
                datasets: [{
                    label:           'Revenue (Br)',
                    data:            data.values,
                    backgroundColor: AAMDashboard.defaults.colors.primary + 'cc',
                    borderColor:     AAMDashboard.defaults.colors.primary,
                    borderWidth:     1,
                    borderRadius:    6,
                }],
            },
            options: {
                plugins: {
                    legend:  { display: false },
                    tooltip: { callbacks: { label: ctx => ' Br ' + Number(ctx.raw).toLocaleString('en', { minimumFractionDigits: 2 }) } },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid:        { color: AAMDashboard.defaults.colors.grid },
                        ticks:       { callback: v => 'Br ' + Number(v).toLocaleString() },
                    },
                    x: { grid: { display: false } },
                },
            },
        });
    });
};

// ─── Inventory Levels Bar Chart (Warehouse / Admin) ──────────────────────────
AAMDashboard.initInventoryChart = function () {
    const canvas = document.getElementById('inventoryLevelsChart');
    if (!canvas) return;

    AAM.ajax('GET', '/api/dashboard/charts?type=inventory_levels', null, function (res) {
        if (!res.success || !res.data) return;
        const data = res.data;
        AAMDashboard.charts = AAMDashboard.charts || {};
        AAMDashboard.charts.inventory = new Chart(canvas, {
            type: 'bar',
            data: {
                labels:   data.labels,
                datasets: [
                    {
                        label:           'Current Stock',
                        data:            data.current,
                        backgroundColor: AAMDashboard.defaults.colors.success + 'bb',
                        borderColor:     AAMDashboard.defaults.colors.success,
                        borderWidth:     1,
                        borderRadius:    4,
                    },
                    {
                        label:           'Low Stock Threshold',
                        data:            data.threshold,
                        backgroundColor: AAMDashboard.defaults.colors.warning + '55',
                        borderColor:     AAMDashboard.defaults.colors.warning,
                        borderWidth:     1,
                        borderRadius:    4,
                        type:            'line',
                        borderDash:      [6, 3],
                        pointRadius:     0,
                        fill:            false,
                    },
                ],
            },
            options: {
                plugins: { tooltip: { mode: 'index' } },
                scales: {
                    y: { beginAtZero: true, grid: { color: AAMDashboard.defaults.colors.grid } },
                    x: { grid: { display: false } },
                },
            },
        });
    });
};

// ─── Product Mix Doughnut Chart ──────────────────────────────────────────────
AAMDashboard.initProductPieChart = function () {
    const canvas = document.getElementById('productMixChart');
    if (!canvas) return;

    AAM.ajax('GET', '/api/dashboard/charts?type=product_mix', null, function (res) {
        if (!res.success || !res.data) return;
        const data = res.data;
        const colours = [
            '#0d6efd','#198754','#ffc107','#dc3545','#0dcaf0',
            '#6f42c1','#fd7e14','#20c997','#d63384','#6c757d',
        ];
        AAMDashboard.charts = AAMDashboard.charts || {};
        AAMDashboard.charts.productMix = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels:   data.labels,
                datasets: [{
                    data:            data.values,
                    backgroundColor: colours.slice(0, data.labels.length),
                    borderWidth:     2,
                    borderColor:     '#fff',
                    hoverOffset:     8,
                }],
            },
            options: {
                cutout: '65%',
                plugins: {
                    legend:  { position: 'bottom' },
                    tooltip: { callbacks: { label: ctx => ' ' + ctx.label + ': ' + ctx.raw + ' kg' } },
                },
            },
        });
    });
};

// ─── Farm Production Bar Chart ───────────────────────────────────────────────
AAMDashboard.initFarmProductionChart = function () {
    const canvas = document.getElementById('farmProductionChart');
    if (!canvas) return;

    AAM.ajax('GET', '/api/dashboard/charts?type=farm_production', null, function (res) {
        if (!res.success || !res.data) return;
        const data = res.data;
        AAMDashboard.charts = AAMDashboard.charts || {};
        AAMDashboard.charts.farmProduction = new Chart(canvas, {
            type: 'bar',
            data: {
                labels:   data.labels,
                datasets: [{
                    label:           'Harvested (kg)',
                    data:            data.values,
                    backgroundColor: AAMDashboard.defaults.colors.success + 'cc',
                    borderColor:     AAMDashboard.defaults.colors.success,
                    borderWidth:     1,
                    borderRadius:    6,
                }],
            },
            options: {
                indexAxis: 'y',
                plugins:   { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { color: AAMDashboard.defaults.colors.grid } },
                    y: { grid: { display: false } },
                },
            },
        });
    });
};

// ─── Revenue Line Chart (GM Executive Dashboard) ─────────────────────────────
AAMDashboard.initRevenueLineChart = function () {
    const canvas = document.getElementById('revenueLineChart');
    if (!canvas) return;

    AAM.ajax('GET', '/api/dashboard/charts?type=revenue_trend', null, function (res) {
        if (!res.success || !res.data) return;
        const data = res.data;
        AAMDashboard.charts = AAMDashboard.charts || {};
        AAMDashboard.charts.revenue = new Chart(canvas, {
            type: 'line',
            data: {
                labels:   data.labels,
                datasets: data.series.map(function (series, i) {
                    const c = [
                        AAMDashboard.defaults.colors.primary,
                        AAMDashboard.defaults.colors.success,
                        AAMDashboard.defaults.colors.warning,
                    ][i] || AAMDashboard.defaults.colors.secondary;
                    return {
                        label:            series.label,
                        data:             series.values,
                        borderColor:      c,
                        backgroundColor:  c + '22',
                        borderWidth:      2,
                        fill:             true,
                        tension:          0.4,
                        pointRadius:      3,
                        pointHoverRadius: 5,
                    };
                }),
            },
            options: {
                plugins: { tooltip: { mode: 'index', intersect: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid:        { color: AAMDashboard.defaults.colors.grid },
                        ticks:       { callback: v => 'Br ' + Number(v).toLocaleString() },
                    },
                    x: { grid: { display: false } },
                },
            },
        });
    });
};

// ─── Refresh All Charts ───────────────────────────────────────────────────────
AAMDashboard.refreshAll = function () {
    if (!AAMDashboard.charts) return;

    Object.values(AAMDashboard.charts).forEach(function (chart) {
        if (chart && typeof chart.destroy === 'function') {
            // Re-init by re-calling init (charts will re-fetch)
        }
    });

    // Re-init all charts
    AAMDashboard.initSalesChart();
    AAMDashboard.initInventoryChart();
    AAMDashboard.initProductPieChart();
    AAMDashboard.initFarmProductionChart();
    AAMDashboard.initRevenueLineChart();
};

// ─── KPI Counter Animation ────────────────────────────────────────────────────
/**
 * Animate numbers from 0 to their target value on page load.
 * Apply data-kpi="true" to any element containing a number.
 */
AAMDashboard.animateKpiCounters = function () {
    document.querySelectorAll('[data-kpi-animate]').forEach(function (el) {
        const target   = parseFloat(el.getAttribute('data-kpi-animate')) || 0;
        const decimals = parseInt(el.getAttribute('data-kpi-decimals')) || 0;
        const prefix   = el.getAttribute('data-kpi-prefix') || '';
        const suffix   = el.getAttribute('data-kpi-suffix') || '';
        const duration = 800;
        const start    = performance.now();

        function update(now) {
            const elapsed  = now - start;
            const progress = Math.min(elapsed / duration, 1);
            const ease     = 1 - Math.pow(1 - progress, 3); // Ease out cubic
            const current  = target * ease;

            el.textContent = prefix + current.toFixed(decimals) + suffix;

            if (progress < 1) {
                requestAnimationFrame(update);
            } else {
                el.textContent = prefix + target.toFixed(decimals) + suffix;
            }
        }

        requestAnimationFrame(update);
    });
};

// ─── Skeleton Helpers ─────────────────────────────────────────────────────────
AAMDashboard.showSkeleton = function (canvas) {
    if (!canvas.parentElement) return;
    const sk = document.createElement('div');
    sk.className    = 'skeleton';
    sk.id           = 'sk-' + canvas.id;
    sk.style.height = canvas.style.height || '200px';
    canvas.parentElement.insertBefore(sk, canvas);
    canvas.style.display = 'none';
};

AAMDashboard.hideSkeleton = function (canvas) {
    const sk = document.getElementById('sk-' + canvas.id);
    if (sk) sk.remove();
    canvas.style.display = '';
};

// ─── Theme Change Listener ────────────────────────────────────────────────────
// When the user switches theme, re-apply Chart.js global defaults and
// redraw all dashboard charts so colours match the new theme immediately.
document.addEventListener('aamThemeChange', function () {
    AAMDashboard.applyGlobalDefaults();
    // theme.js handles updating live chart instances; dashboard only
    // needs to refresh its own global defaults here.
});
