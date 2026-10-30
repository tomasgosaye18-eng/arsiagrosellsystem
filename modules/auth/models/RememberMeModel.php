<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 7: Authentication
 * =========================================================
 * File        : modules/auth/models/RememberMeModel.php
 * Purpose     : Remember-me token data access model.
 *               Manages the remember_me_tokens table.
 *               Uses selector/validator pattern:
 *               - selector = public cookie identifier
 *               - validator = secret, stored as SHA-256 hash
 * Dependencies: AAM\Core\BaseModel, remember_me_tokens table.
 * How it works: On "Remember Me" login, a selector+validator
 *               pair is generated. Selector goes in cookie,
 *               hashed validator stored in DB. On auto-login,
 *               we find by selector and verify validator hash.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Auth\Models;

use AAM\Core\BaseModel;

class RememberMeModel extends BaseModel
{
    protected string $table      = 'remember_me_tokens';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = false;

    protected array $fillable = [
        'user_id', 'selector', 'hashed_token',
        'user_agent', 'ip_address', 'expires_at',
    ];

    /**
     * Expose Database for direct queries (e.g. token rotation in AuthenticationService).
     */
    public function getDb(): \AAM\Core\Database
    {
        return $this->db;
    }

    /**
     * Store a new remember-me token.
     *
     * @param int    $userId
     * @param string $selector     Random 16-byte hex string (32 chars)
     * @param string $hashedToken  SHA-256 of the raw 32-byte validator
     * @param int    $days         Token lifetime in days
     */
    public function store(
        int    $userId,
        string $selector,
        string $hashedToken,
        string $userAgent = '',
        string $ipAddress = '',
        int    $days = 30
    ): void {
        $this->db->execute(
            "INSERT INTO remember_me_tokens
                (user_id, selector, hashed_token, user_agent, ip_address, expires_at)
             VALUES
                (:uid, :sel, :tok, :ua, :ip, DATE_ADD(NOW(), INTERVAL :days DAY))",
            [
                'uid'  => $userId,
                'sel'  => $selector,
                'tok'  => $hashedToken,
                'ua'   => substr($userAgent, 0, 255),
                'ip'   => $ipAddress,
                'days' => $days,
            ]
        );
    }

    /**
     * Find a valid (non-expired) token by selector.
     *
     * @return array<string,mixed>|null
     */
    public function findBySelector(string $selector): ?array
    {
        return $this->db->fetchOne(
            "SELECT rmt.*, u.id AS user_id, u.username, u.account_status,
                    u.is_active, u.is_locked
             FROM remember_me_tokens rmt
             JOIN users u ON u.id = rmt.user_id
             WHERE rmt.selector   = :sel
               AND rmt.expires_at > NOW()
             LIMIT 1",
            ['sel' => $selector]
        );
    }

    /**
     * Update last-used timestamp for a token.
     */
    public function touchToken(string $selector): void
    {
        $this->db->execute(
            "UPDATE remember_me_tokens SET last_used_at = NOW() WHERE selector = :sel",
            ['sel' => $selector]
        );
    }

    /**
     * Delete a specific token by selector (on logout).
     */
    public function deleteBySelector(string $selector): void
    {
        $this->db->execute(
            "DELETE FROM remember_me_tokens WHERE selector = :sel",
            ['sel' => $selector]
        );
    }

    /**
     * Delete all tokens for a user (logout all devices).
     */
    public function deleteForUser(int $userId): void
    {
        $this->db->execute(
            "DELETE FROM remember_me_tokens WHERE user_id = :uid",
            ['uid' => $userId]
        );
    }

    /**
     * Delete expired tokens (garbage collection).
     */
    public function deleteExpired(): int
    {
        return $this->db->execute(
            "DELETE FROM remember_me_tokens WHERE expires_at < NOW()"
        );
    }

    /**
     * Get all active tokens for a user (for "active sessions" display).
     *
     * @return array<int, array<string,mixed>>
     */
    public function getForUser(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT selector, user_agent, ip_address, created_at, expires_at, last_used_at
             FROM remember_me_tokens
             WHERE user_id = :uid AND expires_at > NOW()
             ORDER BY last_used_at DESC",
            ['uid' => $userId]
        );
    }
}
