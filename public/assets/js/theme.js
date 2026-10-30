/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * File     : public/assets/js/theme.js
 * Purpose  : Light / Dark Mode theme system.
 *            - Reads saved preference from localStorage
 *            - Falls back to OS prefers-color-scheme
 *            - Sets data-theme="light|dark" on <html>
 *            - Provides toggle UI wired into the topbar
 *            - Updates Chart.js defaults on theme change
 *            - Exposes window.AAMTheme for external use
 *
 * LOAD ORDER:
 *   1. Inline <script> in <head>  → sets theme before paint (no flash)
 *   2. This file (after DOM ready) → wires the toggle button
 *
 * STORAGE KEY: "aam_theme"  values: "light" | "dark"
 * =========================================================
 */

'use strict';

/* ─────────────────────────────────────────────────────────────
 * PART A — EARLY INIT (also called from inline <head> script)
 *
 * This function is intentionally side-effect-safe: it only
 * touches the <html> attribute. It must work before DOM ready.
 * ─────────────────────────────────────────────────────────────*/
(function () {

    var STORAGE_KEY = 'aam_theme';

    /**
     * Detect which theme should be active:
     *  1. Saved user preference
     *  2. OS prefers-color-scheme
     *  3. Light fallback
     */
    function resolveTheme() {
        var saved = null;
        try { saved = localStorage.getItem(STORAGE_KEY); } catch (e) {}
        if (saved === 'dark' || saved === 'light') return saved;

        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        return 'light';
    }

    /**
     * Apply theme to <html data-theme="...">
     */
    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
    }

    // Apply immediately (called from inline head script AND on load)
    applyTheme(resolveTheme());

    // Expose namespace so the rest of this file can extend it
    window.AAMTheme = window.AAMTheme || {};
    window.AAMTheme._storageKey  = STORAGE_KEY;
    window.AAMTheme._resolveTheme = resolveTheme;
    window.AAMTheme._applyTheme  = applyTheme;

})();

/* ─────────────────────────────────────────────────────────────
 * PART B — FULL THEME MANAGER (DOM-dependent)
 * ─────────────────────────────────────────────────────────────*/
(function () {

    var STORAGE_KEY = window.AAMTheme._storageKey;

    // ── Public API ────────────────────────────────────────────

    /**
     * Get the current active theme ("light" | "dark")
     */
    AAMTheme.current = function () {
        return document.documentElement.getAttribute('data-theme') || 'light';
    };

    /**
     * Set theme and persist the choice
     */
    AAMTheme.set = function (theme) {
        if (theme !== 'light' && theme !== 'dark') return;

        document.documentElement.setAttribute('data-theme', theme);

        try { localStorage.setItem(STORAGE_KEY, theme); } catch (e) {}

        AAMTheme._syncToggleBtn(theme);
        AAMTheme._updateCharts(theme);

        // Dispatch a custom event so other scripts can react
        try {
            document.dispatchEvent(new CustomEvent('aamThemeChange', { detail: { theme: theme } }));
        } catch (e) {}
    };

    /**
     * Toggle between light and dark
     */
    AAMTheme.toggle = function () {
        AAMTheme.set(AAMTheme.current() === 'dark' ? 'light' : 'dark');
    };

    // ── Toggle Button ─────────────────────────────────────────

    /**
     * Inject the toggle button into the topbar right-side group.
     * Placed just before the notifications bell, inside
     * the existing .d-flex.align-items-center.gap-2.ms-auto
     */
    AAMTheme._injectToggleBtn = function () {
        // Avoid double-injection
        if (document.getElementById('themeToggleBtn')) return;

        var rightGroup = document.querySelector(
            '#topbar .d-flex.align-items-center.gap-2.ms-auto'
        );
        if (!rightGroup) return;

        var btn = document.createElement('button');
        btn.id            = 'themeToggleBtn';
        btn.type          = 'button';
        btn.setAttribute('aria-label', 'Toggle dark/light mode');
        btn.setAttribute('title',      'Toggle theme');

        // Wire click
        btn.addEventListener('click', function () {
            AAMTheme.toggle();
        });

        // Insert before the notifications bell dropdown wrapper.
        // The bell button sits inside a .dropdown div — we want to insert
        // the theme toggle before that .dropdown wrapper, not before the
        // button itself, so we walk up to find the direct child of rightGroup.
        var bell = document.getElementById('notificationBell');
        var bellWrapper = bell ? bell.closest('.dropdown') : null;

        if (bellWrapper && bellWrapper.parentNode === rightGroup) {
            rightGroup.insertBefore(btn, bellWrapper);
        } else if (bell && bell.parentNode === rightGroup) {
            // bell is a direct child (no wrapper) — insert before it
            rightGroup.insertBefore(btn, bell);
        } else {
            // Fallback: append at end (should not happen in normal layout)
            rightGroup.appendChild(btn);
        }

        // Set initial visual state
        AAMTheme._syncToggleBtn(AAMTheme.current());
    };

    /**
     * Update button icon and label to reflect active theme
     */
    AAMTheme._syncToggleBtn = function (theme) {
        var btn = document.getElementById('themeToggleBtn');
        if (!btn) return;

        if (theme === 'dark') {
            btn.innerHTML =
                '<i class="bi bi-sun-fill theme-icon" aria-hidden="true"></i>' +
                '<span class="theme-label">Light</span>';
            btn.setAttribute('aria-label', 'Switch to light mode');
            btn.setAttribute('title',      'Switch to light mode');
        } else {
            btn.innerHTML =
                '<i class="bi bi-moon-stars-fill theme-icon" aria-hidden="true"></i>' +
                '<span class="theme-label">Dark</span>';
            btn.setAttribute('aria-label', 'Switch to dark mode');
            btn.setAttribute('title',      'Switch to dark mode');
        }
    };

    // ── Chart.js integration ──────────────────────────────────

    /**
     * Update Chart.js global defaults and re-render all live charts
     * when theme changes.
     */
    AAMTheme._updateCharts = function (theme) {
        if (typeof Chart === 'undefined') return;

        var style     = getComputedStyle(document.documentElement);
        var textColor = style.getPropertyValue('--chart-text').trim()    || (theme === 'dark' ? '#909aab' : '#6c757d');
        var gridColor = style.getPropertyValue('--chart-grid').trim()    || (theme === 'dark' ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.05)');
        var tooltipBg = style.getPropertyValue('--chart-tooltip-bg').trim()     || (theme === 'dark' ? '#2a3038' : '#fff');
        var tooltipBd = style.getPropertyValue('--chart-tooltip-border').trim() || (theme === 'dark' ? '#373d44' : '#e8ecf0');
        var tooltipTx = style.getPropertyValue('--chart-tooltip-text').trim()   || (theme === 'dark' ? '#dee2e6' : '#212529');

        // Update global defaults (affects new charts)
        Chart.defaults.color = textColor;
        Chart.defaults.plugins.tooltip.backgroundColor = tooltipBg;
        Chart.defaults.plugins.tooltip.borderColor     = tooltipBd;
        Chart.defaults.plugins.tooltip.titleColor      = tooltipTx;
        Chart.defaults.plugins.tooltip.bodyColor       = tooltipTx;
        Chart.defaults.plugins.tooltip.borderWidth     = 1;

        // --- Update all registered (live) chart instances ---
        var chartInstances = [];

        // Chart.js v3+: Chart.instances is an object keyed by id
        if (Chart.instances) {
            Object.values(Chart.instances).forEach(function (chart) {
                chartInstances.push(chart);
            });
        }

        // Also collect from AAMDashboard if available
        if (window.AAMDashboard && AAMDashboard.charts) {
            Object.values(AAMDashboard.charts).forEach(function (chart) {
                if (chart && chartInstances.indexOf(chart) === -1) {
                    chartInstances.push(chart);
                }
            });
        }

        chartInstances.forEach(function (chart) {
            if (!chart || !chart.config) return;

            try {
                // Update scale grid/tick colours
                var scales = chart.options.scales || {};
                Object.keys(scales).forEach(function (axisKey) {
                    var axis = scales[axisKey];
                    if (axis.grid)  axis.grid.color  = gridColor;
                    if (axis.ticks) axis.ticks.color  = textColor;
                    if (axis.title) axis.title.color  = textColor;
                });

                // Update legend label colour
                if (chart.options.plugins && chart.options.plugins.legend) {
                    chart.options.plugins.legend.labels = chart.options.plugins.legend.labels || {};
                    chart.options.plugins.legend.labels.color = textColor;
                }

                // Update doughnut / pie borderColor for dark (separate slices visually)
                if (chart.config.type === 'doughnut' || chart.config.type === 'pie') {
                    var ds = chart.data.datasets || [];
                    ds.forEach(function (dataset) {
                        dataset.borderColor = theme === 'dark' ? '#252a2f' : '#fff';
                    });
                }

                chart.update('none'); // 'none' = no animation, just redraw
            } catch (e) {
                // Silently skip if chart has already been destroyed
            }
        });
    };

    // ── OS preference listener ────────────────────────────────

    /**
     * Listen for OS theme changes and auto-apply only when
     * the user has no saved preference.
     */
    AAMTheme._listenOsPreference = function () {
        if (!window.matchMedia) return;

        window.matchMedia('(prefers-color-scheme: dark)')
              .addEventListener('change', function (e) {
                  var saved = null;
                  try { saved = localStorage.getItem(STORAGE_KEY); } catch (err) {}
                  // Only follow OS change if user has no saved preference
                  if (!saved) {
                      AAMTheme.set(e.matches ? 'dark' : 'light');
                  }
              });
    };

    // ── DOM-ready bootstrap ───────────────────────────────────

    function onReady() {
        // Re-apply theme (ensure body / html are in sync)
        var current = AAMTheme.current();

        // Inject toggle button into topbar
        AAMTheme._injectToggleBtn();

        // Sync button with current theme
        AAMTheme._syncToggleBtn(current);

        // Apply Chart.js colours for current theme
        // (delayed slightly to let dashboard.js create charts first)
        setTimeout(function () {
            AAMTheme._updateCharts(current);
        }, 400);

        // Listen for OS preference changes
        AAMTheme._listenOsPreference();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onReady);
    } else {
        onReady();
    }

    // ── Re-apply chart colours after AJAX-loaded content ─────
    // Some dashboards load charts via AJAX; hook into AAMDashboard
    // refresh if it fires after theme.js is loaded.
    document.addEventListener('aamThemeChange', function (e) {
        // Already handled inside AAMTheme.set() — this listener
        // is available for third-party code that needs to react.
    });

})();
