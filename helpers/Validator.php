<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File     : helpers/Validator.php
 * Purpose  : Server-side input validation helper.
 *            Supports all common validation rules.
 *            Returns typed error messages per field.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Helpers;

use AAM\Core\Database;

class Validator
{
    /** @var array<string, mixed> Input data to validate */
    private array $data;

    /** @var array<string, string|array> Validation rules */
    private array $rules;

    /** @var array<string, string[]> Validation errors per field */
    private array $errors = [];

    /**
     * @param array<string, mixed>        $data
     * @param array<string, string|array> $rules
     */
    public function __construct(array $data, array $rules)
    {
        $this->data  = $data;
        $this->rules = $rules;
    }

    /**
     * Run all validation rules.
     * Returns true if all pass, false if any fail.
     */
    public function passes(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_string($ruleSet) ? explode('|', $ruleSet) : (array) $ruleSet;
            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }

        return empty($this->errors);
    }

    /**
     * Alias — run validation and return true if fails.
     */
    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * Get all validation errors.
     *
     * @return array<string, string[]>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get first error for a field.
     */
    public function firstError(string $field): string
    {
        return $this->errors[$field][0] ?? '';
    }

    // ─── Rule Application ─────────────────────────────────────────────────────

    /**
     * Apply a single rule to a field value.
     *
     * @param mixed $value
     */
    private function applyRule(string $field, mixed $value, string $rule): void
    {
        // Parse rule with parameters: 'min:8', 'max:255', 'in:a,b,c'
        $parts  = explode(':', $rule, 2);
        $name   = trim($parts[0]);
        $param  = $parts[1] ?? null;

        $label = ucfirst(str_replace('_', ' ', $field));

        match($name) {
            'required'  => $this->validateRequired($field, $value, $label),
            'string'    => $this->validateString($field, $value, $label),
            'integer'   => $this->validateInteger($field, $value, $label),
            'numeric'   => $this->validateNumeric($field, $value, $label),
            'min'       => $this->validateMin($field, $value, (int)($param ?? 0), $label),
            'max'       => $this->validateMax($field, $value, (int)($param ?? 0), $label),
            'min_value' => $this->validateMinValue($field, $value, (float)($param ?? 0), $label),
            'max_value' => $this->validateMaxValue($field, $value, (float)($param ?? 0), $label),
            'email'     => $this->validateEmail($field, $value, $label),
            'url'       => $this->validateUrl($field, $value, $label),
            'date'      => $this->validateDate($field, $value, $label),
            'in'        => $this->validateIn($field, $value, $param ?? '', $label),
            'not_in'    => $this->validateNotIn($field, $value, $param ?? '', $label),
            'alpha'     => $this->validateAlpha($field, $value, $label),
            'alpha_num' => $this->validateAlphaNum($field, $value, $label),
            'phone'     => $this->validatePhone($field, $value, $label),
            'positive'  => $this->validatePositive($field, $value, $label),
            'boolean'   => $this->validateBoolean($field, $value, $label),
            'unique'    => $this->validateUnique($field, $value, $param ?? '', $label),
            'confirmed' => $this->validateConfirmed($field, $value, $label),
            'nullable'  => null, // Passes always — just marks as optional
            'sometimes' => null, // Passes if field not present
            default     => null,
        };
    }

    // ─── Individual Validators ────────────────────────────────────────────────

    private function validateRequired(string $field, mixed $value, string $label): void
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            $this->addError($field, "{$label} is required.");
        }
    }

    private function validateString(string $field, mixed $value, string $label): void
    {
        if ($value !== null && $value !== '' && !is_string($value)) {
            $this->addError($field, "{$label} must be a text value.");
        }
    }

    private function validateInteger(string $field, mixed $value, string $label): void
    {
        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->addError($field, "{$label} must be a whole number.");
        }
    }

    private function validateNumeric(string $field, mixed $value, string $label): void
    {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->addError($field, "{$label} must be a number.");
        }
    }

    private function validateMin(string $field, mixed $value, int $min, string $label): void
    {
        if ($value !== null && strlen((string)$value) < $min) {
            $this->addError($field, "{$label} must be at least {$min} characters.");
        }
    }

    private function validateMax(string $field, mixed $value, int $max, string $label): void
    {
        if ($value !== null && strlen((string)$value) > $max) {
            $this->addError($field, "{$label} must not exceed {$max} characters.");
        }
    }

    private function validateMinValue(string $field, mixed $value, float $min, string $label): void
    {
        if ($value !== null && $value !== '' && (float)$value < $min) {
            $this->addError($field, "{$label} must be at least {$min}.");
        }
    }

    private function validateMaxValue(string $field, mixed $value, float $max, string $label): void
    {
        if ($value !== null && $value !== '' && (float)$value > $max) {
            $this->addError($field, "{$label} must not exceed {$max}.");
        }
    }

    private function validateEmail(string $field, mixed $value, string $label): void
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, "{$label} must be a valid email address.");
        }
    }

    private function validateUrl(string $field, mixed $value, string $label): void
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, "{$label} must be a valid URL.");
        }
    }

    private function validateDate(string $field, mixed $value, string $label): void
    {
        if ($value !== null && $value !== '') {
            $date = \DateTime::createFromFormat('Y-m-d', (string)$value);
            if (!$date || $date->format('Y-m-d') !== $value) {
                $this->addError($field, "{$label} must be a valid date (YYYY-MM-DD).");
            }
        }
    }

    private function validateIn(string $field, mixed $value, string $param, string $label): void
    {
        if ($value !== null && $value !== '') {
            $allowed = array_map('trim', explode(',', $param));
            if (!in_array((string)$value, $allowed, true)) {
                $this->addError($field, "{$label} must be one of: " . implode(', ', $allowed) . ".");
            }
        }
    }

    private function validateNotIn(string $field, mixed $value, string $param, string $label): void
    {
        $forbidden = array_map('trim', explode(',', $param));
        if ($value !== null && in_array((string)$value, $forbidden, true)) {
            $this->addError($field, "{$label} contains an invalid value.");
        }
    }

    private function validateAlpha(string $field, mixed $value, string $label): void
    {
        if ($value !== null && $value !== '' && !ctype_alpha(str_replace(' ', '', (string)$value))) {
            $this->addError($field, "{$label} may only contain letters.");
        }
    }

    private function validateAlphaNum(string $field, mixed $value, string $label): void
    {
        if ($value !== null && $value !== '' && !ctype_alnum(str_replace([' ', '-', '_'], '', (string)$value))) {
            $this->addError($field, "{$label} may only contain letters and numbers.");
        }
    }

    private function validatePhone(string $field, mixed $value, string $label): void
    {
        if ($value !== null && $value !== '') {
            // Ethiopian phone: +251XXXXXXXXX or 09XXXXXXXX or 07XXXXXXXX
            if (!preg_match('/^(\+251|0)[79]\d{8}$/', preg_replace('/\s+/', '', (string)$value))) {
                $this->addError($field, "{$label} must be a valid phone number.");
            }
        }
    }

    private function validatePositive(string $field, mixed $value, string $label): void
    {
        if ($value !== null && $value !== '' && (float)$value <= 0) {
            $this->addError($field, "{$label} must be greater than zero.");
        }
    }

    private function validateBoolean(string $field, mixed $value, string $label): void
    {
        $valid = [true, false, 0, 1, '0', '1', 'true', 'false', 'on', 'off'];
        if ($value !== null && !in_array($value, $valid, true)) {
            $this->addError($field, "{$label} must be true or false.");
        }
    }

    /**
     * Validate uniqueness against a database table column.
     * param format: 'table,column' or 'table,column,except_id'
     */
    private function validateUnique(string $field, mixed $value, string $param, string $label): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $parts    = explode(',', $param);
        $table    = trim($parts[0] ?? '');
        $column   = trim($parts[1] ?? $field);
        $exceptId = trim($parts[2] ?? '');

        if (!$table) {
            return;
        }

        try {
            $sql    = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :value";
            $params = ['value' => $value];

            if ($exceptId !== '') {
                $sql .= " AND `id` != :except_id";
                $params['except_id'] = (int)$exceptId;
            }

            $count = (int) Database::getInstance()->fetchColumn($sql, $params);
            if ($count > 0) {
                $this->addError($field, "{$label} already exists. Please use a different value.");
            }
        } catch (\Throwable $e) {
            // If DB check fails, let it through — uniqueness enforced at DB level too
        }
    }

    /**
     * Validate that a field matches its confirmation field.
     * Looks for {field}_confirmation in input data.
     */
    private function validateConfirmed(string $field, mixed $value, string $label): void
    {
        $confirmation = $this->data[$field . '_confirmation'] ?? null;
        if ($value !== $confirmation) {
            $this->addError($field, "{$label} confirmation does not match.");
        }
    }

    // ─── Error Management ─────────────────────────────────────────────────────

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}
