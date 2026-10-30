<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/notifications/NotificationService.php
 * Purpose : Shared service for creating notifications.
 *           Used by the controller's "send" action AND by
 *           other modules that trigger notifications
 *           automatically (transfers, dispatches, etc.).
 *
 *           SCOPE RULES
 *           ──────────────────────────────────────────────
 *           GM (general_manager) may send to:
 *             - farm_manager
 *             - warehouse_manager
 *             - salesperson
 *             - a specific user (any of the above roles)
 *           GM must NOT send to administrator.
 *
 *           Admin (administrator) may send to:
 *             - any role (system-wide)
 *             - a specific user
 *
 *           Any other caller (service) may pass any
 *           recipient — no scope restriction enforced here.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Notifications;

use AAM\Notifications\Models\NotificationModel;

class NotificationService
{
    private NotificationModel $model;

    public function __construct()
    {
        $this->model = new NotificationModel();
    }

    // ─── Core send primitives ─────────────────────────────────────────────────

    /**
     * Send a notification to a single user.
     */
    public function sendToUser(
        int    $recipientId,
        string $type,
        string $title,
        string $message,
        string $priority     = 'medium',
        ?int   $senderId     = null,
        ?string $refType     = null,
        ?int   $refId        = null,
        ?string $relatedUrl  = null
    ): int {
        return $this->model->insert([
            'recipient_user_id' => $recipientId,
            'sender_user_id'    => $senderId,
            'notification_type' => $type,
            'title'             => $title,
            'message'           => $message,
            'priority'          => $priority,
            'reference_type'    => $refType,
            'reference_id'      => $refId,
            'related_url'       => $relatedUrl,
        ]);
    }

    /**
     * Send a notification to every active user that belongs to $roleName.
     * Returns the number of notifications inserted.
     */
    public function sendToRole(
        string $roleName,
        string $type,
        string $title,
        string $message,
        string $priority    = 'medium',
        ?int   $senderId    = null,
        ?string $refType    = null,
        ?int   $refId       = null,
        ?string $relatedUrl = null
    ): int {
        $ids   = $this->model->userIdsByRole($roleName);
        $count = 0;
        foreach ($ids as $uid) {
            $this->sendToUser($uid, $type, $title, $message, $priority,
                              $senderId, $refType, $refId, $relatedUrl);
            $count++;
        }
        return $count;
    }

    /**
     * Broadcast a notification to ALL active users.
     * Returns the number of notifications inserted.
     */
    public function sendToAll(
        string $type,
        string $title,
        string $message,
        string $priority    = 'medium',
        ?int   $senderId    = null,
        ?string $relatedUrl = null
    ): int {
        $ids   = $this->model->allActiveUserIds();
        $count = 0;
        foreach ($ids as $uid) {
            $this->sendToUser($uid, $type, $title, $message, $priority,
                              $senderId, null, null, $relatedUrl);
            $count++;
        }
        return $count;
    }

    // ─── GM-scoped send (enforces recipient role whitelist) ───────────────────

    /**
     * GM allowed target roles.
     *
     * @return string[]
     */
    public static function gmAllowedRoles(): array
    {
        return [
            ROLE_FARM_MANAGER,
            ROLE_WAREHOUSE_MANAGER,
            ROLE_SALESPERSON,
        ];
    }

    /**
     * Send on behalf of the GM — validates that the target role is
     * within the GM's operational scope (not administrator).
     *
     * @throws \InvalidArgumentException if role is outside GM scope
     */
    public function gmSendToRole(
        string $targetRole,
        string $title,
        string $message,
        string $priority    = 'medium',
        int    $senderId    = 0,
        ?string $relatedUrl = null
    ): int {
        if (!in_array($targetRole, self::gmAllowedRoles(), true)) {
            throw new \InvalidArgumentException(
                "GM cannot send notifications to role: {$targetRole}"
            );
        }
        return $this->sendToRole(
            $targetRole,
            NOTIF_USER_MESSAGE,
            $title,
            $message,
            $priority,
            $senderId ?: null,
            null, null,
            $relatedUrl
        );
    }

    /**
     * Send on behalf of the GM to a specific user — validates the
     * recipient's role is within GM scope.
     *
     * @throws \InvalidArgumentException if recipient is outside GM scope
     */
    public function gmSendToUser(
        int    $recipientId,
        string $recipientRole,
        string $title,
        string $message,
        string $priority    = 'medium',
        int    $senderId    = 0,
        ?string $relatedUrl = null
    ): int {
        if (!in_array($recipientRole, self::gmAllowedRoles(), true)) {
            throw new \InvalidArgumentException(
                "GM cannot send notifications to a user with role: {$recipientRole}"
            );
        }
        return $this->sendToUser(
            $recipientId,
            NOTIF_USER_MESSAGE,
            $title,
            $message,
            $priority,
            $senderId ?: null,
            null, null,
            $relatedUrl
        );
    }

    // ─── Model passthrough (for controllers that need read helpers) ───────────

    public function getModel(): NotificationModel
    {
        return $this->model;
    }
}
