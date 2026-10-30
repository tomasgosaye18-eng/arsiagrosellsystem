/**
 * =============================================================
 * AU-AgroSale — Checkout Page JS
 * public/assets/customer/js/checkout.js
 * =============================================================
 * Handles:
 *  - Payment method radio visual selection
 *  - Form validation with user-friendly inline errors
 *  - Submit spinner + duplicate-click prevention
 *  - Notes character counter
 *  - Scroll-to-error on validation failure
 * =============================================================
 */
(function () {
    'use strict';

    /* ── DOM refs ─────────────────────────────────────────── */
    var form      = document.getElementById('checkoutForm');
    var placeBtn  = document.getElementById('coPlaceOrderBtn');
    var pmCards   = document.querySelectorAll('.cko-pm-card');
    var nameInput = document.getElementById('co_customer_name');
    var notesArea = document.getElementById('cko_notes');
    var notesCnt  = document.getElementById('cko_notes_count');

    if (!form) return;

    /* ── Payment method visual selection ─────────────────── */
    function refreshPmSelection() {
        pmCards.forEach(function (label) {
            var radio = label.querySelector('.cko-pm-radio');
            if (radio && radio.checked) {
                label.classList.add('cko-pm-card--selected');
            } else {
                label.classList.remove('cko-pm-card--selected');
            }
        });
    }

    pmCards.forEach(function (label) {
        label.addEventListener('click', function () {
            var radio = label.querySelector('.cko-pm-radio');
            if (radio) {
                radio.checked = true;
                refreshPmSelection();
            }
        });
        // Keyboard support: Space/Enter activates selection
        label.addEventListener('keydown', function (e) {
            if (e.key === ' ' || e.key === 'Enter') {
                e.preventDefault();
                var radio = label.querySelector('.cko-pm-radio');
                if (radio) {
                    radio.checked = true;
                    refreshPmSelection();
                }
            }
        });
    });

    // Initial state
    refreshPmSelection();

    /* ── Notes character counter ──────────────────────────── */
    if (notesArea && notesCnt) {
        notesArea.addEventListener('input', function () {
            var len = notesArea.value.length;
            notesCnt.textContent = len;
            if (len > 220) {
                notesCnt.style.color = 'var(--c-warning)';
            } else if (len >= 250) {
                notesCnt.style.color = 'var(--c-danger)';
            } else {
                notesCnt.style.color = '';
            }
        });
    }

    /* ── Inline field error helpers ───────────────────────── */
    function showFieldError(input, message) {
        clearFieldError(input);
        input.classList.add('cko-input--error');
        var err = document.createElement('span');
        err.className = 'cko-field-error';
        err.setAttribute('role', 'alert');
        err.innerHTML = '<i class="bi bi-exclamation-circle" aria-hidden="true"></i> ' + escHtml(message);
        input.parentNode.appendChild(err);
    }

    function clearFieldError(input) {
        input.classList.remove('cko-input--error');
        var existing = input.parentNode.querySelector('.cko-field-error');
        if (existing) existing.remove();
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /* ── Scroll to first error ─────────────────────────────── */
    function scrollToError(el) {
        if (!el) return;
        var top = el.getBoundingClientRect().top + window.pageYOffset - 100;
        window.scrollTo({ top: top, behavior: 'smooth' });
        el.focus();
    }

    /* ── Remove errors on input change ────────────────────── */
    if (nameInput) {
        nameInput.addEventListener('input', function () {
            clearFieldError(nameInput);
        });
    }

    /* ── Form submit ──────────────────────────────────────── */
    var submitting = false;

    form.addEventListener('submit', function (e) {
        // Prevent double-submit
        if (submitting) {
            e.preventDefault();
            return;
        }

        var valid = true;

        // Validate name
        if (nameInput && !nameInput.value.trim()) {
            e.preventDefault();
            showFieldError(nameInput, 'Please enter your full name.');
            scrollToError(nameInput);
            valid = false;
        }

        // Validate payment method selected
        var pmSelected = form.querySelector('.cko-pm-radio:checked');
        if (!pmSelected) {
            e.preventDefault();
            var pmStep = document.querySelector('#cko-step3-title');
            if (pmStep) {
                var top = pmStep.getBoundingClientRect().top + window.pageYOffset - 100;
                window.scrollTo({ top: top, behavior: 'smooth' });
            }
            // Highlight payment section
            var payList = document.querySelector('.cko-payment-list');
            if (payList) {
                payList.style.outline = '2px solid var(--c-danger)';
                payList.style.outlineOffset = '4px';
                payList.style.borderRadius = '8px';
                setTimeout(function () {
                    payList.style.outline = '';
                    payList.style.outlineOffset = '';
                }, 2500);
            }
            valid = false;
        }

        if (!valid) return;

        // All valid — show spinner, disable button to prevent double-click
        submitting = true;

        if (placeBtn) {
            var btnText    = placeBtn.querySelector('.cko-place-btn__text');
            var btnSpinner = placeBtn.querySelector('.cko-place-btn__spinner');
            if (btnText)    btnText.hidden    = true;
            if (btnSpinner) btnSpinner.hidden = false;
            placeBtn.disabled = true;
            placeBtn.setAttribute('aria-disabled', 'true');
        }
    });

    // Reset button state — covers both bfcache restores and normal loads.
    // Without this, navigating back from the payment/success page leaves the spinner stuck.
    function resetBtn() {
        submitting = false;
        if (placeBtn) {
            var btnText    = placeBtn.querySelector('.cko-place-btn__text');
            var btnSpinner = placeBtn.querySelector('.cko-place-btn__spinner');
            if (btnText)    btnText.hidden    = false;
            if (btnSpinner) btnSpinner.hidden = true;
            // Only re-enable if not intentionally disabled by the server (hasIssues)
            if (!window.CheckoutPageState || !window.CheckoutPageState.hasIssues) {
                placeBtn.disabled = false;
                placeBtn.removeAttribute('aria-disabled');
            }
        }
    }

    // Run immediately on script load (handles normal page loads and hard refreshes)
    resetBtn();

    // Also run on pageshow — covers bfcache restores (browser back button)
    window.addEventListener('pageshow', function () {
        resetBtn();
    });

})();
