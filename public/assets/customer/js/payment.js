/**
 * =============================================================
 * AU-AgroSale — Payment Page JS
 * public/assets/customer/js/payment.js
 * =============================================================
 * Phase 7 — Mobile Banking / Dynamic QR Payment
 *
 * Handles:
 *  - Expiry countdown timer
 *  - Polling the verify endpoint (every 3s)
 *  - Status pill transitions (PENDING → PAID / FAILED)
 *  - Dev-mode "simulate payment" button
 *  - Cancel order with confirmation
 *  - Status banner transitions
 *  - Redirect on success / failure
 *
 * IMPORTANT:
 *  - Payment is NEVER marked as paid by this script.
 *  - The backend /customer/checkout/verify endpoint is the
 *    only authority on payment status.
 *  - This script only reads the status and updates the UI.
 * =============================================================
 */
(function () {
    'use strict';

    /* ── Read server-injected data ──────────────────────── */
    var D = window.PaymentData;
    if (!D || !D.orderId) return;

    // Compute clock offset: server time vs client time
    // This corrects for timezone mismatches and clock skew
    var clockOffset = D.serverNow ? (D.serverNow - Date.now()) : 0;

    /* ── DOM refs ──────────────────────────────────────── */
    var countdownEl    = document.getElementById('payCountdown');
    var expiryEl       = document.getElementById('payExpiry');
    var statusBanner   = document.getElementById('payStatusBanner');
    var statusIcon     = document.getElementById('payStatusIcon');
    var statusMsg      = document.getElementById('payStatusMessage');
    var pollingWrap    = document.getElementById('payPolling');
    var pollingMsg     = document.getElementById('payPollingMsg');
    var statusPill     = document.getElementById('payStatusPill');
    var statusPillDot  = statusPill  ? statusPill.querySelector('.cpy-status-pill__dot')   : null;
    var statusPillLbl  = statusPill  ? statusPill.querySelector('.cpy-status-pill__label') : null;
    var devConfirmBtn  = document.getElementById('devConfirmBtn');
    var devActions     = document.getElementById('devActions');
    var cancelBtn      = document.getElementById('cancelOrderBtn');
    var cancelRow      = document.getElementById('cancelRow');
    var summaryStatus  = document.querySelector('.cpy-summary-status-pending');

    var pollInterval  = null;
    var countdownInt  = null;
    var isComplete    = false;
    var pollCount     = 0;

    /* ── Countdown timer ───────────────────────────────── */
    if (D.expiresAt && D.expiresAt > 0 && countdownEl) {
        // Check immediately on page load — order may already be expired
        var checkExpiry = function () {
            // Use server-clock-adjusted time to avoid timezone/clock-skew issues
            var remaining = D.expiresAt - (Date.now() + clockOffset);
            if (remaining <= 0) {
                clearInterval(countdownInt);
                countdownEl.textContent = 'Expired';
                if (expiryEl) expiryEl.classList.add('cpy-expiry--expired');
                stopPolling();
                showStatus('warning', 'Payment window has expired. Please start a new order.');
                updateStatusPill('expired');
                if (cancelRow) cancelRow.style.display = 'none';
                isComplete = true;
            } else {
                var mins = Math.floor(remaining / 60000);
                var secs = Math.floor((remaining % 60000) / 1000);
                countdownEl.textContent = mins + ':' + (secs < 10 ? '0' : '') + secs + ' remaining';
            }
        };

        // Run immediately on load
        checkExpiry();

        // Only start interval if not already expired
        if (!isComplete) {
            countdownInt = setInterval(checkExpiry, 1000);
        }
    }

    /* ── Status banner ─────────────────────────────────── */
    function showStatus(type, message) {
        if (!statusBanner) return;

        // Reset to base class only, then add variant
        statusBanner.className = 'cpy-status-banner';

        var iconClass = {
            success: 'bi bi-check-circle-fill',
            error:   'bi bi-x-circle-fill',
            warning: 'bi bi-exclamation-circle-fill'
        }[type] || 'bi bi-info-circle-fill';

        if (statusIcon) statusIcon.className = iconClass;
        if (statusMsg)  statusMsg.textContent = message;

        statusBanner.classList.add('cpy-status-banner--' + type);
        statusBanner.removeAttribute('hidden');
    }

    /* ── Status pill update ────────────────────────────── */
    function updateStatusPill(state) {
        if (!statusPill) return;

        var labels = {
            pending:  'PENDING — Waiting for Payment',
            paid:     'PAID — Payment Confirmed',
            failed:   'FAILED — Payment Not Completed',
            expired:  'EXPIRED — Payment Window Closed',
            cancelled:'CANCELLED'
        };

        // Swap pill colour
        statusPill.className = 'cpy-status-pill';
        if (state === 'paid') {
            statusPill.style.background    = 'var(--c-success-bg)';
            statusPill.style.borderColor   = 'rgba(46,125,50,.25)';
            statusPill.style.color         = 'var(--c-success)';
            if (statusPillDot) statusPillDot.style.background = 'var(--c-success)';
        } else if (state === 'failed' || state === 'expired' || state === 'cancelled') {
            statusPill.style.background    = 'var(--c-danger-bg)';
            statusPill.style.borderColor   = 'rgba(198,40,40,.2)';
            statusPill.style.color         = 'var(--c-danger)';
            if (statusPillDot) statusPillDot.style.background = 'var(--c-danger)';
        }

        if (statusPillLbl) {
            statusPillLbl.textContent = labels[state] || state.toUpperCase();
        }

        // Also update the sidebar summary status chip
        if (summaryStatus) {
            if (state === 'paid') {
                summaryStatus.className = 'cpy-summary-status-paid';
                summaryStatus.innerHTML = '<i class="bi bi-check-circle-fill" aria-hidden="true"></i> PAID';
                summaryStatus.style.background  = 'var(--c-success-bg)';
                summaryStatus.style.color       = 'var(--c-success)';
                summaryStatus.style.borderColor = 'rgba(46,125,50,.2)';
            }
        }
    }

    /* ── Polling ────────────────────────────────────────── */
    function startPolling() {
        if (pollInterval) return;
        setTimeout(pollStatus, 2000);
        pollInterval = setInterval(pollStatus, D.pollInterval || 3000);
    }

    function stopPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }

    var pollingMessages = [
        'Waiting for payment confirmation…',
        'Checking payment status…',
        'Still waiting…',
        'Verifying with provider…',
        'Almost there…'
    ];

    function pollStatus() {
        if (isComplete) { stopPolling(); return; }

        pollCount++;
        if (pollingMsg) {
            pollingMsg.textContent = pollingMessages[
                Math.min(pollCount - 1, pollingMessages.length - 1)
            ];
        }

        fetch(D.verifyUrl, {
            method: 'POST',
            headers: {
                'Content-Type':     'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                csrf_token: D.csrfToken,
                order_id:   D.orderId
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res || !res.success) return;
            var data   = res.data   || {};
            var status = data.status || 'pending';

            if (status === 'paid') {
                isComplete = true;
                stopPolling();
                clearInterval(countdownInt);

                updateStatusPill('paid');
                showStatus('success', 'Payment confirmed! Redirecting to your order…');

                if (pollingWrap) pollingWrap.style.display = 'none';
                if (devActions)  devActions.style.display  = 'none';
                if (cancelRow)   cancelRow.style.display   = 'none';

                setTimeout(function () {
                    window.location.href = D.successUrl;
                }, 1800);

            } else if (status === 'failed' || status === 'cancelled') {
                isComplete = true;
                stopPolling();
                clearInterval(countdownInt);

                updateStatusPill('failed');
                showStatus('error', 'Payment was not completed. Please return to your cart and try again.');

                if (pollingWrap) pollingWrap.style.display = 'none';
                if (cancelBtn)   cancelBtn.textContent     = '← Return to Cart';

            } else if (status === 'expired') {
                isComplete = true;
                stopPolling();
                clearInterval(countdownInt);

                updateStatusPill('expired');
                showStatus('warning', 'Payment window expired. Please start a new order.');

                if (pollingWrap) pollingWrap.style.display = 'none';
                if (cancelBtn)   cancelBtn.textContent     = '← Return to Cart';
            }
            // status === 'pending' → keep polling, no UI change
        })
        .catch(function () {
            // Network error — retry on next interval
        });
    }

    /* ── Dev confirm button (non-production only) ───────── */
    if (devConfirmBtn) {
        devConfirmBtn.addEventListener('click', function () {
            devConfirmBtn.disabled = true;
            devConfirmBtn.innerHTML =
                '<span class="c-spinner c-spinner-sm" aria-hidden="true"></span> Processing…';

            fetch(D.devUrl, {
                method: 'POST',
                headers: {
                    'Content-Type':     'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    csrf_token: D.csrfToken,
                    order_id:   D.orderId,
                    payment_id: D.paymentId
                })
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success && res.data && res.data.status === 'paid') {
                    isComplete = true;
                    stopPolling();
                    clearInterval(countdownInt);

                    updateStatusPill('paid');
                    showStatus('success', 'Payment confirmed! Redirecting…');

                    if (pollingWrap) pollingWrap.style.display = 'none';
                    if (devActions)  devActions.style.display  = 'none';
                    if (cancelRow)   cancelRow.style.display   = 'none';

                    setTimeout(function () {
                        window.location.href = D.successUrl;
                    }, 1500);
                } else {
                    devConfirmBtn.disabled = false;
                    devConfirmBtn.innerHTML =
                        '<i class="bi bi-check2-all" aria-hidden="true"></i> Simulate Payment (Dev Only)';
                    alert('Dev confirm: ' + (res.message || 'Unexpected response'));
                }
            })
            .catch(function (err) {
                devConfirmBtn.disabled = false;
                devConfirmBtn.innerHTML =
                    '<i class="bi bi-check2-all" aria-hidden="true"></i> Simulate Payment (Dev Only)';
                console.error('Dev confirm error:', err);
            });
        });
    }

    /* ── Cancel order ────────────────────────────────────── */
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            if (!confirm('Cancel this order and return to cart?')) return;

            cancelBtn.disabled = true;
            cancelBtn.innerHTML =
                '<span class="c-spinner c-spinner-sm" aria-hidden="true"></span> Cancelling…';

            fetch(D.cancelUrl, {
                method: 'POST',
                headers: {
                    'Content-Type':     'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    csrf_token: D.csrfToken,
                    order_id:   D.orderId
                })
            })
            .then(function (r) { return r.json(); })
            .then(function () {
                stopPolling();
                clearInterval(countdownInt);
                window.location.href = D.cartUrl;
            })
            .catch(function () {
                window.location.href = D.cartUrl;
            });
        });
    }

    /* ── Start ───────────────────────────────────────────── */
    // Only start polling if not already expired on page load
    if (!isComplete) {
        startPolling();
    }

}());
