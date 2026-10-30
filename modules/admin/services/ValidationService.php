<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 8A
 * =========================================================
 * File        : modules/admin/services/ValidationService.php
 * Purpose     : Centralised validation service for admin
 *               operations. Wraps the Validator helper with
 *               business-specific rule sets and reusable
 *               uniqueness checks for user management.
 * Dependencies: Validator, Database.
 * How it works:
 *   validateCreateUser()  → returns errors array or empty
 *   validateUpdateUser()  → same for edit operations
 *   usernameAvailable()   → DB uniqueness check
 *   emailAvailable()      → DB uniqueness check
 *   employeeIdAvailable() → DB uniqueness check
 *   validatePassword()    → strength + common-password check
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Admin\Services;

use AAM\Helpers\Validator;
use AAM\Core\Database;
use AAM\Security\PasswordUtility;

class ValidationService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ─── User Create Validation ───────────────────────────────────────────────

    /**
     * Validate all fields for creating a new user.
     *
     * @param  array<string, mixed> $data  Raw form data
     * @return array<string, string[]>     Field-keyed errors (empty = valid)
     */
    public function validateCreateUser(array $data): array
    {
        $errors = [];

        // Basic field rules
        $validator = new Validator($data, [
            'first_name'  => 'required|min:2|max:80',
            'middle_name' => 'nullable|max:80',
            'last_name'   => 'required|min:2|max:80',
            'username'    => 'required|min:3|max:80',
            'gender'      => 'nullable|in:male,female,other,prefer_not_to_say',
            'email'       => 'nullable|email|max:191',
            'phone'       => 'nullable|phone',
            'role_id'     => 'required|integer',
            'employee_id' => 'nullable|max:30',
        ]);

        if ($validator->fails()) {
            $errors = array_merge($errors, $validator->errors());
        }

        // Username uniqueness
        if (!empty($data['username']) && !$this->usernameAvailable($data['username'])) {
            $errors['username'][] = 'This username is already taken.';
        }

        // Email uniqueness
        if (!empty($data['email']) && !$this->emailAvailable($data['email'])) {
            $errors['email'][] = 'This email address is already registered.';
        }

        // Employee ID uniqueness
        if (!empty($data['employee_id']) && !$this->employeeIdAvailable($data['employee_id'])) {
            $errors['employee_id'][] = 'This Employee ID is already assigned.';
        }

        // Password strength
        if (!empty($data['password'])) {
            $pwErrors = $this->validatePassword($data['password']);
            if (!empty($pwErrors)) {
                $errors['password'] = $pwErrors;
            }
        } else {
            $errors['password'][] = 'Password is required.';
        }

        return $errors;
    }

    // ─── User Update Validation ───────────────────────────────────────────────

    /**
     * Validate all fields for updating an existing user.
     * Username is excluded (read-only on edit).
     *
     * @param  array<string, mixed> $data    Raw form data
     * @param  int                  $userId  The user being edited (for self-exclusion)
     * @return array<string, string[]>
     */
    public function validateUpdateUser(array $data, int $userId): array
    {
        $errors = [];

        $validator = new Validator($data, [
            'first_name'  => 'required|min:2|max:80',
            'middle_name' => 'nullable|max:80',
            'last_name'   => 'required|min:2|max:80',
            'gender'      => 'nullable|in:male,female,other,prefer_not_to_say',
            'email'       => 'nullable|email|max:191',
            'phone'       => 'nullable|phone',
            'role_id'     => 'required|integer',
            'employee_id' => 'nullable|max:30',
        ]);

        if ($validator->fails()) {
            $errors = array_merge($errors, $validator->errors());
        }

        // Email uniqueness (excluding self)
        if (!empty($data['email']) && !$this->emailAvailable($data['email'], $userId)) {
            $errors['email'][] = 'This email is already registered to another account.';
        }

        // Employee ID uniqueness (excluding self)
        if (!empty($data['employee_id']) && !$this->employeeIdAvailable($data['employee_id'], $userId)) {
            $errors['employee_id'][] = 'This Employee ID is already in use by another user.';
        }

        return $errors;
    }

    // ─── Password Validation ──────────────────────────────────────────────────

    /**
     * Validate a password against strength rules and common password list.
     *
     * @return string[] Error messages (empty = valid)
     */
    public function validatePassword(string $password): array
    {
        $errors = [];

        $result = PasswordUtility::validate($password);
        if (!$result['valid']) {
            $errors = array_merge($errors, $result['errors']);
        }

        if (PasswordUtility::isCommonPassword($password)) {
            $errors[] = 'This password is too common. Please choose a more unique password.';
        }

        return $errors;
    }

    // ─── Uniqueness Checks ────────────────────────────────────────────────────

    /**
     * Check if a username is available (not already taken).
     *
     * @param int $exceptId  Exclude this user ID (for edit operations)
     */
    public function usernameAvailable(string $username, int $exceptId = 0): bool
    {
        $sql    = "SELECT COUNT(*) FROM users WHERE LOWER(username) = LOWER(:u)";
        $params = ['u' => $username];

        if ($exceptId > 0) {
            $sql .= " AND id != :id";
            $params['id'] = $exceptId;
        }

        return (int) $this->db->fetchColumn($sql, $params) === 0;
    }

    /**
     * Check if an email address is available.
     *
     * @param int $exceptId  Exclude this user ID (for edit operations)
     */
    public function emailAvailable(string $email, int $exceptId = 0): bool
    {
        if (empty($email)) {
            return true;
        }

        $sql    = "SELECT COUNT(*) FROM users WHERE LOWER(email) = LOWER(:e)";
        $params = ['e' => $email];

        if ($exceptId > 0) {
            $sql .= " AND id != :id";
            $params['id'] = $exceptId;
        }

        return (int) $this->db->fetchColumn($sql, $params) === 0;
    }

    /**
     * Check if an Employee ID is available.
     *
     * @param int $exceptId  Exclude this user ID (for edit operations)
     */
    public function employeeIdAvailable(string $employeeId, int $exceptId = 0): bool
    {
        if (empty($employeeId)) {
            return true;
        }

        $sql    = "SELECT COUNT(*) FROM users WHERE employee_id = :eid";
        $params = ['eid' => $employeeId];

        if ($exceptId > 0) {
            $sql .= " AND id != :id";
            $params['id'] = $exceptId;
        }

        return (int) $this->db->fetchColumn($sql, $params) === 0;
    }

    // ─── Profile Field Validation ─────────────────────────────────────────────

    /**
     * Validate profile edit fields (own profile).
     *
     * @param  array<string, mixed> $data
     * @param  int                  $userId
     * @return array<string, string[]>
     */
    public function validateProfileUpdate(array $data, int $userId): array
    {
        $errors = [];

        $validator = new Validator($data, [
            'first_name'  => 'required|min:2|max:80',
            'middle_name' => 'nullable|max:80',
            'last_name'   => 'required|min:2|max:80',
            'gender'      => 'nullable|in:male,female,other,prefer_not_to_say',
            'email'       => 'nullable|email|max:191',
            'phone'       => 'nullable|phone',
        ]);

        if ($validator->fails()) {
            $errors = array_merge($errors, $validator->errors());
        }

        if (!empty($data['email']) && !$this->emailAvailable($data['email'], $userId)) {
            $errors['email'][] = 'This email address is already registered to another account.';
        }

        return $errors;
    }

    /**
     * Validate an image upload for profile photo.
     *
     * @param  array<string, mixed> $file   $_FILES element
     * @return string[]  Error messages (empty = valid)
     */
    public function validateImageUpload(array $file): array
    {
        $errors = [];

        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['No file was uploaded.'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['Upload error. Please try again.'];
        }

        // Size: max 5 MB
        if ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'File size must not exceed 5 MB.';
        }

        // Extension whitelist
        $ext      = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        $allowed  = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            $errors[] = 'Allowed file types: JPG, PNG, WebP.';
        }

        // MIME type check (basic)
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo        = finfo_open(FILEINFO_MIME_TYPE);
        $mime         = finfo_file($finfo, $file['tmp_name'] ?? '');
        finfo_close($finfo);

        if ($mime && !in_array($mime, $allowedMimes, true)) {
            $errors[] = 'File content does not match an allowed image type.';
        }

        return $errors;
    }
}
