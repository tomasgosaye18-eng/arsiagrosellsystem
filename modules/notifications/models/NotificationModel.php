<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/notifications/models/NotificationModel.php
 * Purpose : Thin data-access layer for the notifications table.
 *           Provides the single method used by NotificationService
 *           and the controller to read/write notifications.
 *           Does NOT duplicate the notifications table — it only
 *           wraps the existing `notifications` table.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Notifications\Models;

use AAM\Core\Database;

class NotificationModel
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ─── Insert ──────────────────────────────────────────────────────────────

    /**
     * Insert a single notification row.
     *
     * @param array{
     *   recipient_user_id: int,
     *   sender_user_id:    int|null,
     *   notification_type: string,
     *   title:             string,
     *   message:           string,
     *   priority:          string,
     *   reference_type:    string|null,
     *   reference_id:      int|null,
     *   related_url:       string|null
     * } $data
     */
    public function insert(array $data): int
    {
        $this->db->execute(
            "INSERT INTO notifications
                (recipient_user_id, sender_user_id, notification_type,
                 title, message, priority, reference_type, reference_id,
                 related_url, is_read, created_at)
             VALUES
                (:recipient_user_id, :sender_user_id, :notification_type,
                 :title, :message, :priority, :reference_type, :reference_id,
                 :related_url, 0, NOW())",
            [
                'recipient_user_id' => $data['recipient_user_id'],
                'sender_user_id'    => $data['sender_user_id']    ?? null,
                'notification_type' => $data['notification_type'],
                'title'             => $data['title'],
                'message'           => $data['message'],
                'priority'          => $data['priority']          ?? 'medium',
                'reference_type'    => $data['reference_type']    ?? null,
                'reference_id'      => $data['reference_id']      ?? null,
                'related_url'       => $data['related_url']       ?? null,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    // ─── Fetch ───────────────────────────────────────────────────────────────

    /**
     * Paginated list of notifications for a user.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forUser(int $userId, int $limit = 100, int $offset = 0): array
    {
        return $this->db->fetchAll(
            "SELECT n.*,
                    CONCAT(u.first_name, ' ', u.last_name) AS sender_name
             FROM notifications n
             LEFT JOIN users u ON u.id = n.sender_user_id
             WHERE n.recipient_user_id = :uid
             ORDER BY n.created_at DESC
             LIMIT :lim OFFSET :off",
            ['uid' => $userId, 'lim' => $limit, 'off' => $offset]
        );
    }

    /**
     * Count unread notifications for a user.
     */
    public function unreadCount(int $userId): int
    {
        return (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM notifications
             WHERE recipient_user_id = :uid AND is_read = 0",
            ['uid' => $userId]
        );
    }

    /**
     * Recent notifications for the bell dropdown (last 10).
     *
     * @return array<int, array<string, mixed>>
     */
    public function recentForUser(int $userId, int $limit = 10): array
    {
        return $this->db->fetchAll(
            "SELECT id, title, message, notification_type, priority,
                    is_read, created_at, related_url,
                    TIMESTAMPDIFF(MINUTE, created_at, NOW()) AS minutes_ago
             FROM notifications
             WHERE recipient_user_id = :uid
             ORDER BY created_at DESC
             LIMIT :lim",
            ['uid' => $userId, 'lim' => $limit]
        );
    }

    // ─── Users list helpers ───────────────────────────────────────────────────

    /**
     * Return all active users belonging to a given role name.
     *
     * @return array<int, array{id:int, first_name:string, last_name:string, username:string}>
     */
    public function usersByRole(string $roleName): array
    {
        return $this->db->fetchAll(
            "SELECT u.id, u.first_name, u.last_name, u.username
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE r.name = :role
               AND u.is_active = 1
               AND u.account_status = 'active'
             ORDER BY u.first_name, u.last_name",
            ['role' => $roleName]
        );
    }

    /**
     * Return IDs of all active users with a given role.
     *
     * @return int[]
     */
    public function userIdsByRole(string $roleName): array
    {
        $rows = $this->db->fetchAll(
            "SELECT u.id
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE r.name = :role
               AND u.is_active = 1
               AND u.account_status = 'active'",
            ['role' => $roleName]
        );
        return array_map(fn($r) => (int)$r['id'], $rows);
    }

    /**
     * Return IDs of ALL active users (for system-wide broadcast).
     *
     * @return int[]
     */
    public function allActiveUserIds(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT id FROM users
             WHERE is_active = 1
               AND account_status = 'active'"
        );
        return array_map(fn($r) => (int)$r['id'], $rows);
    }

    /**
     * Return all active users with their role name.
     *
     * @return array<int, array{id:int, first_name:string, last_name:string, username:string, role_name:string, role_display:string}>
     */
    public function allActiveUsers(): array
    {
        return $this->db->fetchAll(
            "SELECT u.id, u.first_name, u.last_name, u.username,
                    r.name AS role_name, r.display_name AS role_display
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.is_active = 1
               AND u.account_status = 'active'
             ORDER BY r.display_name, u.first_name, u.last_name"
        );
    }

    /**
     * Return all active roles.
     *
     * @return array<int, array{id:int, name:string, display_name:string}>
     */
    public function allRoles(): array
    {
        return $this->db->fetchAll(
            "SELECT id, name, display_name
             FROM roles
             WHERE is_active = 1
             ORDER BY display_name"
        );
    }
}
