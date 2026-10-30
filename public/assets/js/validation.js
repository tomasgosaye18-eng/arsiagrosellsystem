/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * File     : public/assets/js/validation.js
 * Purpose  : Client-side form validation.
 *            Provides real-time feedback before form submit.
 *            Mirrors server-side Validator rules.
 *            Works with Bootstrap 5 valid/invalid classes.
 *            Loaded on all pages via master.php and
 *            minimal.php layouts.
 * =========================================================
 */

'use strict';

window.AAMValidation = window.AAMValidation || {};

// ─── Bootstrap DOM-ready ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    AAMValidation.init();
});

// ─── Initialise ──────────────────────────────────────────────────────────────
AAMValidation.init = function () {
    // 1. Disable native browser validation (we do our own)
    document.querySelectorAll('form[data-validate]').forEach(function (form) {
        form.setAttribute('novalidate', '');
        AAMValidation.attachForm(form);
    });

    // 2. Real-time feedback on blur
    document.querySelectorAll('[data-rules]').forEach(function (input) {
        input.addEventListener('blur', function () {
            AAMValidation.validateField(input);
        });
        input.addEventListener('input', function () {
            // Clear error while typing
            if (input.classList.contains('is-invalid')) {
                AAMValidation.clearField(input);
            }
        });
    });
};

// ─── Attach submit handler to a form ─────────────────────────────────────────
AAMValidation.attachForm = function (form) {
    form.addEventListener('submit', function (e) {
        let isValid = true;

        form.querySelectorAll('[data-rules]').forEach(function (field) {
            if (!AAMValidation.validateField(field)) {
                isValid = false;
            }
        });

        if (!isValid) {
            e.preventDefault();
            e.stopPropagation();

            // Scroll to first error
            const firstError = form.querySelector('.is-invalid');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
        }
    });
};

// ─── Validate a single field ──────────────────────────────────────────────────
/**
 * Validate one input element.
 * Reads rules from data-rules attribute: "required|min:8|email"
 * Returns true if valid, false if invalid.
 *
 * @param {HTMLElement} input
 * @returns {boolean}
 */
AAMValidation.validateField = function (input) {
    const rulesAttr = input.getAttribute('data-rules') || '';
    const rules     = rulesAttr.split('|').map(r => r.trim()).filter(Boolean);
    const value     = input.value.trim();
    const label     = input.getAttribute('data-label')
                   || input.getAttribute('placeholder')
                   || input.getAttribute('name')
                   || 'This field';

    // Clear previous state
    AAMValidation.clearField(input);

    for (const rule of rules) {
        const [ruleName, ruleParam] = rule.split(':');
        const error = AAMValidation.applyRule(ruleName, ruleParam, value, label, input);

        if (error) {
            AAMValidation.setInvalid(input, error);
            return false;
        }
    }

    // All rules passed
    AAMValidation.setValid(input);
    return true;
};

// ─── Rule Engine ─────────────────────────────────────────────────────────────
/**
 * Apply a single rule and return an error string, or null if valid.
 *
 * @param {string}      name   Rule name
 * @param {string}      param  Rule parameter (e.g. '8' for min:8)
 * @param {string}      value  Current field value
 * @param {string}      label  Field label for error messages
 * @param {HTMLElement} input  The input element
 * @returns {string|null}
 */
AAMValidation.applyRule = function (name, param, value, label, input) {
    switch (name) {

        case 'required':
            if (!value || value === '') return label + ' is required.';
            break;

        case 'min':
            if (value && value.length < parseInt(param)) {
                return label + ' must be at least ' + param + ' characters.';
            }
            break;

        case 'max':
            if (value && value.length > parseInt(param)) {
                return label + ' must not exceed ' + param + ' characters.';
            }
            break;

        case 'min_value':
            if (value !== '' && parseFloat(value) < parseFloat(param)) {
                return label + ' must be at least ' + param + '.';
            }
            break;

        case 'max_value':
            if (value !== '' && parseFloat(value) > parseFloat(param)) {
                return label + ' must not exceed ' + param + '.';
            }
            break;

        case 'email':
            if (value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                return label + ' must be a valid email address.';
            }
            break;

        case 'numeric':
            if (value && isNaN(Number(value))) {
                return label + ' must be a number.';
            }
            break;

        case 'integer':
            if (value && !/^-?\d+$/.test(value)) {
                return label + ' must be a whole number.';
            }
            break;

        case 'positive':
            if (value !== '' && parseFloat(value) <= 0) {
                return label + ' must be greater than zero.';
            }
            break;

        case 'alpha':
            if (value && !/^[a-zA-Z\s]+$/.test(value)) {
                return label + ' may only contain letters.';
            }
            break;

        case 'alpha_num':
            if (value && !/^[a-zA-Z0-9\s_-]+$/.test(value)) {
                return label + ' may only contain letters and numbers.';
            }
            break;

        case 'phone':
            // Ethiopian phone: +251XXXXXXXXX or 09XXXXXXXX or 07XXXXXXXX
            if (value && !/^(\+251|0)[79]\d{8}$/.test(value.replace(/\s/g, ''))) {
                return label + ' must be a valid phone number (e.g. 0912345678).';
            }
            break;

        case 'date':
            if (value) {
                const d = new Date(value);
                if (isNaN(d.getTime())) {
                    return label + ' must be a valid date.';
                }
            }
            break;

        case 'in':
            if (value && param) {
                const allowed = param.split(',').map(v => v.trim());
                if (!allowed.includes(value)) {
                    return label + ' must be one of: ' + allowed.join(', ') + '.';
                }
            }
            break;

        case 'confirmed': {
            // Looks for an input named {fieldName}_confirmation
            const fieldName    = input.name;
            const confField    = document.querySelector('[name="' + fieldName + '_confirmation"]');
            if (confField && value !== confField.value) {
                return label + ' confirmation does not match.';
            }
            break;
        }

        case 'password_strength': {
            // Checks: min 8, uppercase, lowercase, digit
            if (value) {
                if (value.length < 8)           return label + ' must be at least 8 characters.';
                if (!/[A-Z]/.test(value))        return label + ' must contain an uppercase letter.';
                if (!/[a-z]/.test(value))        return label + ' must contain a lowercase letter.';
                if (!/[0-9]/.test(value))        return label + ' must contain a number.';
            }
            break;
        }

        case 'nullable':
        case 'sometimes':
        case 'string':
            // These are pass-through — handled server-side
            break;

        default:
            break;
    }

    return null; // No error
};

// ─── Field State Helpers ──────────────────────────────────────────────────────

AAMValidation.setInvalid = function (input, message) {
    input.classList.remove('is-valid');
    input.classList.add('is-invalid');

    // If input is inside an .input-group, append feedback AFTER the group,
    // not inside it — Bootstrap's ~ sibling selector won't reach through buttons.
    var feedbackParent = input.parentElement;
    var isInGroup = feedbackParent && feedbackParent.classList.contains('input-group');
    if (isInGroup) {
        feedbackParent = feedbackParent.parentElement;
    }

    // Remove existing JS feedback in this parent
    var existing = feedbackParent.querySelector('.invalid-feedback.js-feedback');
    if (existing) existing.remove();

    // Add new feedback
    var div       = document.createElement('div');
    div.className = 'invalid-feedback js-feedback d-block';
    div.textContent = message;
    div.setAttribute('role', 'alert');

    if (isInGroup) {
        // Append after the .input-group element inside the parent
        var group = input.closest('.input-group');
        group.insertAdjacentElement('afterend', div);
    } else {
        feedbackParent.appendChild(div);
    }

    // Accessibility
    var errId = 'err-' + (input.id || input.name);
    input.setAttribute('aria-invalid', 'true');
    input.setAttribute('aria-describedby', errId);
    div.id = errId;
};

AAMValidation.setValid = function (input) {
    input.classList.remove('is-invalid');
    input.classList.add('is-valid');
    input.setAttribute('aria-invalid', 'false');
    input.removeAttribute('aria-describedby');

    // Remove JS feedback — could be inside input-group parent or after it
    var parent = input.parentElement;
    var isInGroup = parent && parent.classList.contains('input-group');
    var searchRoot = isInGroup ? parent.parentElement : parent;
    var existing = searchRoot ? searchRoot.querySelector('.invalid-feedback.js-feedback') : null;
    if (existing) existing.remove();
};

AAMValidation.clearField = function (input) {
    input.classList.remove('is-valid', 'is-invalid');
    input.removeAttribute('aria-invalid');

    var parent = input.parentElement;
    var isInGroup = parent && parent.classList.contains('input-group');
    var searchRoot = isInGroup ? parent.parentElement : parent;
    var existing = searchRoot ? searchRoot.querySelector('.invalid-feedback.js-feedback') : null;
    if (existing) existing.remove();
};

// ─── Password Strength Meter ─────────────────────────────────────────────────
/**
 * Attach a live password strength meter to a password input.
 * Usage: AAMValidation.attachStrengthMeter('password', 'strength-meter-id')
 *
 * @param {string} inputId   ID of the password input
 * @param {string} meterId   ID of the progress bar element
 */
AAMValidation.attachStrengthMeter = function (inputId, meterId) {
    const input = document.getElementById(inputId);
    const meter = document.getElementById(meterId);
    if (!input || !meter) return;

    input.addEventListener('input', function () {
        const score = AAMValidation.passwordScore(input.value);
        const pct   = score * 25;
        const cls   = ['', 'bg-danger', 'bg-warning', 'bg-info', 'bg-success'][score] || '';
        const label = ['', 'Weak', 'Fair', 'Good', 'Strong'][score] || '';

        meter.style.width  = pct + '%';
        meter.className    = 'progress-bar ' + cls;
        meter.textContent  = label;
        meter.setAttribute('aria-valuenow', pct);
    });
};

/**
 * Score a password 0-4 based on complexity.
 */
AAMValidation.passwordScore = function (password) {
    if (!password || password.length < 4) return 0;
    let score = 0;
    if (password.length >= 8)       score++;
    if (/[A-Z]/.test(password))     score++;
    if (/[0-9]/.test(password))     score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;
    return score;
};

// ─── Quantity Input Formatter ─────────────────────────────────────────────────
/**
 * Format a number input to a fixed number of decimal places on blur.
 * Usage: add data-decimals="3" to a number input.
 */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[data-decimals]').forEach(function (input) {
        input.addEventListener('blur', function () {
            const decimals = parseInt(input.getAttribute('data-decimals')) || 2;
            if (input.value !== '') {
                input.value = parseFloat(input.value).toFixed(decimals);
            }
        });
    });
});
