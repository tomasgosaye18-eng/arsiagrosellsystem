<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM) — Phase 7: Authentication
 * =========================================================
 * File        : modules/auth/models/LoginHistoryModel.php
 * Purpose     : Login history data access model.
 *               Records every login attempt (success and
 *               failure) in the login_history table.
 *               Used by AuthenticationService and the
 *               admin audit screens.
 * Dependencies: AAM\Core\BaseModel, login_history table.
 * How it works: Provides record() to insert an event, and
 *               paginated history queries for admin views.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Auth\Models;

use AAM\Core\BaseModel;

class LoginHistoryModel extends BaseModel
{
    protected string $table      = 'login_history';
    protected string $primaryKey = 'id';
    protected bool   $softDelete = false;

    protected array $fillable = [
        'user_id', 'username_attempted', 'was_successful',
        'ip_address', 'user_agent', 'failure_reason',
        'session_id', 'browser', 'os_name', 'device_type',
    ];

    /**
     * Record a login attempt.
     *
     * @param int|null $userId          NULL for unknown user (failed)
     * @param string   $usernameAttempted The credential that was submitted
     * @param bool     $success
     * @param string   $ipAddress
     * @param string   $userAgent
     * @param string   $failureReason   Empty on success
     * @param string   $sessionId       PHP session ID on success
     */
    public function record(
        ?int   $userId,
        string $usernameAttempted,
        bool   $success,
        string $ipAddress,
        string $userAgent,
        string $failureReason = '',
        string $sessionId     = ''
    ): int {
        [$browser, $osName, $deviceType] = $this->parseUserAgent($userAgent);

        return $this->db->execute(
            "INSERT INTO login_history
                (user_id, username_attempted, was_successful,
                 ip_address, user_agent, failure_reason,
                 session_id, browser, os_name, device_type, attempted_at)
             VALUES
                (:user_id, :username, :success,
                 :ip, :ua, :reason,
                 :session_id, :browser, :os, :device, NOW())",
            [
                'user_id'    => $userId,
                'username'   => substr($usernameAttempted, 0, 80),
                'success'    => $success ? 1 : 0,
                'ip'         => $ipAddress,
                'ua'         => substr($userAgent, 0, 255),
                'reason'     => $failureReason,
                'session_id' => $sessionId,
                'browser'    => $browser,
                'os'         => $osName,
                'device'     => $deviceType,
            ]
        );
    }

    /**
     * Record logout — update session end time.
     */
    public function recordLogout(string $sessionId): void
    {
        $this->db->execute(
            "UPDATE login_history
             SET logout_at = NOW()
             WHERE session_id = :sid AND logout_at IS NULL
             ORDER BY attempted_at DESC LIMIT 1",
            ['sid' => $sessionId]
        );
    }

    /**
     * Get recent login history for a specific user.
     *
     * @return array<int, array<string,mixed>>
     */
    public function getForUser(int $userId, int $limit = 10): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM login_history
             WHERE user_id = :uid
             ORDER BY attempted_at DESC
             LIMIT :lim",
            ['uid' => $userId, 'lim' => $limit]
        );
    }

    /**
     * Get paginated login history for admin view.
     *
     * @return array{data: array, total: int}
     */
    public function paginateAdmin(int $page = 1, int $perPage = 50, ?int $userId = null): array
    {
        $where  = $userId ? 'WHERE lh.user_id = :uid' : '';
        $params = $userId ? ['uid' => $userId] : [];
        $offset = ($page - 1) * $perPage;

        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM login_history lh {$where}",
            $params
        );

        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $data = $this->db->fetchAll(
            "SELECT lh.*, u.username, u.first_name, u.last_name
             FROM login_history lh
             LEFT JOIN users u ON u.id = lh.user_id
             {$where}
             ORDER BY lh.attempted_at DESC
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

    /**
     * Count recent failed attempts for an IP address (brute force detection).
     *
     * @param int $windowMinutes Look-back window in minutes
     */
    public function countRecentFailedByIp(string $ip, int $windowMinutes = 60): int
    {
        return (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM login_history
             WHERE ip_address   = :ip
               AND was_successful = 0
               AND attempted_at  > DATE_SUB(NOW(), INTERVAL :mins MINUTE)",
            ['ip' => $ip, 'mins' => $windowMinutes]
        );
    }

    /**
     * Parse User-Agent into browser, OS, device type.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function parseUserAgent(string $ua): array
    {
        // Browser detection
        $browser = 'Unknown';
        if (str_contains($ua, 'Chrome') && !str_contains($ua, 'Edg')) $browser = 'Chrome';
        elseif (str_contains($ua, 'Firefox'))  $browser = 'Firefox';
        elseif (str_contains($ua, 'Safari') && !str_contains($ua, 'Chrome')) $browser = 'Safari';
        elseif (str_contains($ua, 'Edg'))      $browser = 'Edge';
        elseif (str_contains($ua, 'Opera'))    $browser = 'Opera';
        elseif (str_contains($ua, 'MSIE') || str_contains($ua, 'Trident')) $browser = 'IE';

        // OS detection
        $os = 'Unknown';
        if (str_contains($ua, 'Windows'))      $os = 'Windows';
        elseif (str_contains($ua, 'Mac OS'))   $os = 'macOS';
        elseif (str_contains($ua, 'Linux'))    $os = 'Linux';
        elseif (str_contains($ua, 'Android'))  $os = 'Android';
        elseif (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) $os = 'iOS';

        // Device type
        $device = 'desktop';
        if (str_contains($ua, 'Mobile') || str_contains($ua, 'Android')) $device = 'mobile';
        elseif (str_contains($ua, 'iPad') || str_contains($ua, 'Tablet')) $device = 'tablet';

        return [$browser, $os, $device];
    }
}
