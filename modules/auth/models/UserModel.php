<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 7: Authentication
 * =========================================================
 * File        : modules/auth/models/UserModel.php
 * Purpose     : User data access model.
 *               Handles all database operations for the
 *               users table: lookup by credentials,
 *               status management, failed login tracking,
 *               password reset, and profile updates.
 * Dependencies: AAM\Core\BaseModel, users table (Phase 5A)
 * How it works: Extends BaseModel for CRUD. Adds auth-
 *               specific queries: findByUsername,
 *               findByCredential, incrementFailedLogins,
 *               lockAccount, storeResetToken, etc.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Auth\Models;

use AAM\Core\BaseModel;

class UserModel extends BaseModel
{
    protected string $table      = 'users';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = false; // We use account_status instead

    protected array $fillable = [
        'employee_id', 'role_id', 'username', 'password_hash',
        'first_name', 'middle_name', 'last_name', 'gender',
        'email', 'phone', 'profile_photo', 'is_active', 'account_status',
        'failed_login_attempts', 'is_locked', 'must_change_password',
        'last_login_at', 'password_changed_at', 'password_reset_token',
        'password_reset_expires_at', 'remember_token', 'suspension_reason',
        'updated_at', 'created_by',
    ];

    // ─── Credential Lookups ───────────────────────────────────────────────────

    /**
     * Find a user by username (case-insensitive).
     * Returns the full user row including role name.
     *
     * @return array<string,mixed>|null
     */
    public function findByUsername(string $username): ?array
    {
        return $this->db->fetchOne(
            "SELECT u.*, r.name AS role_name, r.display_name AS role_display_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE LOWER(u.username) = LOWER(:username)
             LIMIT 1",
            ['username' => $username]
        );
    }

    /**
     * Find a user by any credential: username OR phone number.
     * Supports multi-field login.
     * Also loads the user's active location assignment (farm/warehouse/shop)
     * so that SESSION_LOCATION_TYPE and SESSION_LOCATION_ID are set on login.
     *
     * @return array<string,mixed>|null
     */
    public function findByCredential(string $credential): ?array
    {
        $user = $this->db->fetchOne(
            "SELECT u.*, r.name AS role_name, r.display_name AS role_display_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE LOWER(u.username) = LOWER(:c1)
                OR u.phone = :c2
             LIMIT 1",
            ['c1' => $credential, 'c2' => $credential]
        );

        if ($user === null) {
            return null;
        }

        // Attach location assignment so SessionManager::loginUser() can store it.
        // Checks user_location_assignments first (Phase 5A table), then falls
        // back to organization_assignments (Phase 8C table) if neither has a row.
        $this->attachLocationAssignment($user);

        return $user;
    }

    /**
     * Attach the user's active location assignment to the user array.
     * Populates `location_type` and `location_id` keys so that
     * SessionManager::loginUser() stores them in the session.
     *
     * @param array<string,mixed> &$user
     */
    private function attachLocationAssignment(array &$user): void
    {
        $userId = (int)$user['id'];

        // Try user_location_assignments (Phase 5A, maintained by UserService)
        try {
            $row = $this->db->fetchOne(
                "SELECT location_type, location_id
                 FROM user_location_assignments
                 WHERE user_id = :uid
                 LIMIT 1",
                ['uid' => $userId]
            );
            if ($row) {
                $user['location_type'] = $row['location_type'];
                $user['location_id']   = (int)$row['location_id'];
                return;
            }
        } catch (\Throwable) {}

        // Fallback: organization_assignments (Phase 8C, maintained by AssignmentController)
        try {
            $row = $this->db->fetchOne(
                "SELECT location_type, location_id
                 FROM organization_assignments
                 WHERE user_id = :uid AND is_active = 1
                 ORDER BY assigned_at DESC
                 LIMIT 1",
                ['uid' => $userId]
            );
            if ($row) {
                $user['location_type'] = $row['location_type'];
                $user['location_id']   = (int)$row['location_id'];
            }
        } catch (\Throwable) {}
    }

    /**
     * Find user with role info by ID.
     *
     * @return array<string,mixed>|null
     */
    public function findWithRole(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT u.*, r.name AS role_name, r.display_name AS role_display_name,
                    r.description AS role_description
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id
             LIMIT 1",
            ['id' => $id]
        );
    }

    // ─── Login Tracking ───────────────────────────────────────────────────────

    /**
     * Record a successful login: reset failure counter, update last_login_at.
     */
    public function recordSuccessfulLogin(int $userId): void
    {
        $this->db->execute(
            "UPDATE users SET
                failed_login_attempts = 0,
                is_locked             = 0,
                last_login_at         = NOW(),
                account_status        = CASE WHEN account_status = 'active' THEN 'active' ELSE account_status END
             WHERE id = :id",
            ['id' => $userId]
        );
    }

    /**
     * Increment failed login counter.
     * Automatically locks account when max attempts reached.
     *
     * @return int New failed attempt count
     */
    public function incrementFailedLogins(int $userId, int $maxAttempts): int
    {
        $this->db->execute(
            "UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = :id",
            ['id' => $userId]
        );

        $attempts = (int) $this->db->fetchColumn(
            "SELECT failed_login_attempts FROM users WHERE id = :id",
            ['id' => $userId]
        );

        // Auto-lock if max reached
        if ($attempts >= $maxAttempts) {
            $this->lockAccount($userId);
        }

        return $attempts;
    }

    /**
     * Lock an account (is_locked = 1, account_status = 'locked').
     */
    public function lockAccount(int $userId): void
    {
        $this->db->execute(
            "UPDATE users SET is_locked = 1, account_status = 'locked' WHERE id = :id",
            ['id' => $userId]
        );
    }

    /**
     * Unlock an account — reset lock flags and failure counter.
     */
    public function unlockAccount(int $userId): void
    {
        $this->db->execute(
            "UPDATE users SET
                is_locked             = 0,
                account_status        = 'active',
                failed_login_attempts = 0
             WHERE id = :id",
            ['id' => $userId]
        );
    }

    // ─── Account Status ───────────────────────────────────────────────────────

    /**
     * Check whether a user account is allowed to log in.
     * Returns null if allowed, or a reason string if blocked.
     */
    public function getLoginBlockReason(array $user): ?string
    {
        if ((int)($user['is_active'] ?? 1) === 0) {
            return 'Your account has been deactivated. Contact the administrator.';
        }

        $status = $user['account_status'] ?? 'active';

        return match($status) {
            'inactive'  => 'Your account is inactive. Contact the administrator.',
            'suspended' => 'Your account has been suspended. Reason: ' . ($user['suspension_reason'] ?? 'No reason provided.'),
            'locked'    => 'Your account is locked due to too many failed login attempts. Contact the administrator.',
            'pending'   => 'Your account is pending approval. Please wait for administrator approval.',
            'deleted'   => 'This account no longer exists.',
            default     => null,
        };
    }

    /**
     * Update account status.
     */
    public function setStatus(int $userId, string $status, ?string $reason = null): void
    {
        $data = ['status' => $status, 'id' => $userId];
        $reasonSql = '';
        if ($reason !== null) {
            $data['reason'] = $reason;
            $reasonSql = ', suspension_reason = :reason';
        }

        $this->db->execute(
            "UPDATE users SET account_status = :status {$reasonSql} WHERE id = :id",
            $data
        );
    }

    // ─── Password Operations ──────────────────────────────────────────────────

    /**
     * Update a user's password hash.
     * Also clears must_change_password and updates timestamp.
     */
    public function updatePassword(int $userId, string $hash): void
    {
        $this->db->execute(
            "UPDATE users SET
                password_hash        = :hash,
                must_change_password = 0,
                password_changed_at  = NOW(),
                password_reset_token      = NULL,
                password_reset_expires_at = NULL
             WHERE id = :id",
            ['hash' => $hash, 'id' => $userId]
        );
    }

    /**
     * Store a hashed password reset token with expiry.
     *
     * @param string $hashedToken SHA-256 hash of the raw token
     * @param int    $expiryMins  Minutes until expiry (default 60)
     */
    public function storeResetToken(int $userId, string $hashedToken, int $expiryMins = 60): void
    {
        $this->db->execute(
            "UPDATE users SET
                password_reset_token      = :token,
                password_reset_expires_at = DATE_ADD(NOW(), INTERVAL :mins MINUTE)
             WHERE id = :id",
            ['token' => $hashedToken, 'mins' => $expiryMins, 'id' => $userId]
        );
    }

    /**
     * Find a user by their hashed reset token (not yet expired).
     *
     * @return array<string,mixed>|null
     */
    public function findByResetToken(string $hashedToken): ?array
    {
        return $this->db->fetchOne(
            "SELECT u.*, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.password_reset_token      = :token
               AND u.password_reset_expires_at > NOW()
               AND u.account_status NOT IN ('deleted','suspended')
             LIMIT 1",
            ['token' => $hashedToken]
        );
    }

    /**
     * Clear the reset token after use.
     */
    public function clearResetToken(int $userId): void
    {
        $this->db->execute(
            "UPDATE users SET
                password_reset_token      = NULL,
                password_reset_expires_at = NULL
             WHERE id = :id",
            ['id' => $userId]
        );
    }

    // ─── Remember Me ─────────────────────────────────────────────────────────

    /**
     * Store remember-me token data in the users table (validator hash).
     */
    public function storeRememberToken(int $userId, string $hashedValidator): void
    {
        $this->db->execute(
            "UPDATE users SET remember_token = :token WHERE id = :id",
            ['token' => $hashedValidator, 'id' => $userId]
        );
    }

    /**
     * Clear remember-me token.
     */
    public function clearRememberToken(int $userId): void
    {
        $this->db->execute(
            "UPDATE users SET remember_token = NULL WHERE id = :id",
            ['id' => $userId]
        );
    }

    // ─── Profile ──────────────────────────────────────────────────────────────

    /**
     * Update user profile fields.
     * Phase 8A: expanded to include middle_name, gender, email, employee_id.
     */
    public function updateProfile(int $userId, array $data): void
    {
        $allowed = [
            'first_name', 'middle_name', 'last_name', 'gender',
            'email', 'phone', 'profile_photo', 'employee_id',
        ];
        $updates = array_intersect_key($data, array_flip($allowed));
        $updates['updated_at'] = date('Y-m-d H:i:s');

        if (empty($updates)) {
            return;
        }

        $setParts = [];
        foreach (array_keys($updates) as $col) {
            $setParts[] = "`{$col}` = :{$col}";
        }
        $updates['id'] = $userId;

        $this->db->execute(
            "UPDATE users SET " . implode(', ', $setParts) . " WHERE id = :id",
            $updates
        );
    }

    /**
     * Get paginated users list with role name for admin user management.
     *
     * @return array{data: array, total: int, page: int, perPage: int, totalPages: int}
     */
    public function paginateWithRole(
        int    $page    = 1,
        int    $perPage = 20,
        string $search  = '',
        string $roleFilter = ''
    ): array {
        $page   = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $params = [];
        $wheres = ["u.account_status != 'deleted'"];

        if ($search !== '') {
            $wheres[] = "(u.username LIKE :s1 OR u.first_name LIKE :s2 OR u.last_name LIKE :s3 OR u.phone LIKE :s4)";
            $params['s1'] = "%{$search}%";
            $params['s2'] = "%{$search}%";
            $params['s3'] = "%{$search}%";
            $params['s4'] = "%{$search}%";
        }

        if ($roleFilter !== '') {
            $wheres[]         = 'r.name = :role';
            $params['role']   = $roleFilter;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $wheres);

        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id {$whereClause}",
            $params
        );

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $data = $this->db->fetchAll(
            "SELECT u.id, u.username, u.first_name, u.last_name, u.phone,
                    u.profile_photo, u.is_active, u.account_status,
                    u.failed_login_attempts, u.is_locked, u.must_change_password,
                    u.last_login_at, u.created_at,
                    r.name AS role_name, r.display_name AS role_display_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             {$whereClause}
             ORDER BY u.created_at DESC
             LIMIT :limit OFFSET :offset",
            $params
        );

        return [
            'data'       => $data,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int) ceil($total / $perPage),
        ];
    }
}
