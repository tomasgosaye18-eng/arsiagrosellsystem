<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File        : modules/notifications/controllers/NotificationController.php
 * Purpose     : Notification controller.
 *               Handles notification listing, mark-as-read,
 *               AJAX polling, and the GM / Admin "send"
 *               notification feature.
 *
 *               SEND PERMISSION SCOPE
 *               ─────────────────────
 *               GM  (general_manager)  → farm_manager,
 *                                        warehouse_manager,
 *                                        salesperson,
 *                                        or a specific user
 *                                        within those roles.
 *               Admin (administrator)  → all users, any role,
 *                                        or a specific user.
 *
 * Dependencies: BaseController, NotificationService,
 *               NotificationModel, SessionManager.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Notifications\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;
use AAM\Core\SessionManager;
use AAM\Notifications\NotificationService;
use AAM\Notifications\Models\NotificationModel;

class NotificationController extends BaseController
{
    private NotificationService $service;
    private NotificationModel   $model;

    public function __construct()
    {
        parent::__construct();
        $this->service = new NotificationService();
        $this->model   = $this->service->getModel();
    }

    /**
     * GET /notifications
     * Show all notifications for the current user.
     */
    public function index(): void
    {
        $this->requireAuth();

        $userId        = $this->userId();
        $notifications = [];

        try {
            $notifications = $this->model->forUser($userId, 100, 0);
        } catch (\Throwable $e) {
            // Table may not exist yet — ignore gracefully
        }

        $this->render('notifications.index', [
            'pageTitle'     => 'Notifications',
            'notifications' => $notifications,
        ]);
    }

    /**
     * POST /notifications/{id}/read
     */
    public function markRead(string $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        try {
            Database::getInstance()->execute(
                "UPDATE notifications
                 SET is_read = 1, read_at = NOW()
                 WHERE id = :id AND recipient_user_id = :uid",
                ['id' => (int)$id, 'uid' => $this->userId()]
            );
        } catch (\Throwable $e) {
            // Ignore
        }

        $this->redirectBack('/notifications');
    }

    /**
     * POST /notifications/read-all
     */
    public function markAllRead(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        try {
            Database::getInstance()->execute(
                "UPDATE notifications
                 SET is_read = 1, read_at = NOW()
                 WHERE recipient_user_id = :uid AND is_read = 0",
                ['uid' => $this->userId()]
            );
        } catch (\Throwable $e) {
            // Ignore
        }

        $this->flash(FLASH_SUCCESS, 'All notifications marked as read.');
        $this->redirectBack('/notifications');
    }

    // ─── GM — Send Business Notification ─────────────────────────────────────

    /**
     * GET /notifications/send
     * GM compose form — business/operational notifications only.
     */
    public function sendForm(): void
    {
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $gmRoles = NotificationService::gmAllowedRoles();

        $users = $this->model->allActiveUsers();
        $users = array_values(array_filter(
            $users,
            fn($u) => in_array($u['role_name'], $gmRoles, true)
        ));

        $this->render('notifications.send_gm', [
            'pageTitle' => 'Send Business Notification',
            'gmRoles'   => $gmRoles,
            'users'     => $users,
        ]);
    }

    /**
     * POST /notifications/send
     * GM submit.
     */
    public function send(): void
    {
        $this->requireRole(ROLE_GENERAL_MANAGER);
        $this->verifyCsrf();

        $recipientType = trim((string) $this->input('recipient_type', ''));
        $recipientRole = trim((string) $this->input('recipient_role', ''));
        $recipientId   = (int) $this->input('recipient_id', 0);
        $title         = trim((string) $this->input('title', ''));
        $message       = trim((string) $this->input('message', ''));
        $priority      = trim((string) $this->input('priority', 'medium'));
        $senderId      = $this->userId() ?? 0;

        if ($title === '' || $message === '') {
            $this->flash(FLASH_ERROR, 'Title and message are required.');
            $this->redirect('notifications/send');
            return;
        }

        $validPriorities = ['low', 'medium', 'high', 'critical'];
        if (!in_array($priority, $validPriorities, true)) {
            $priority = 'medium';
        }

        try {
            if ($recipientType === 'role') {
                $count = $this->service->gmSendToRole(
                    $recipientRole, $title, $message, $priority, $senderId
                );
                $label = ucwords(str_replace('_', ' ', $recipientRole));
                $this->flash(FLASH_SUCCESS, "Notification sent to {$count} user(s) in role: {$label}.");

            } elseif ($recipientType === 'user' && $recipientId > 0) {
                $recipientUser = Database::getInstance()->fetchOne(
                    "SELECT u.id, r.name AS role_name
                     FROM users u JOIN roles r ON r.id = u.role_id
                     WHERE u.id = :id AND u.is_active = 1
                       AND u.account_status = 'active'",
                    ['id' => $recipientId]
                );

                if (!$recipientUser) {
                    $this->flash(FLASH_ERROR, 'Selected user not found or inactive.');
                    $this->redirect('notifications/send');
                    return;
                }

                $this->service->gmSendToUser(
                    (int) $recipientUser['id'],
                    (string) $recipientUser['role_name'],
                    $title,
                    $message,
                    $priority,
                    $senderId
                );
                $this->flash(FLASH_SUCCESS, 'Notification sent successfully.');

            } else {
                $this->flash(FLASH_ERROR, 'Please select a valid recipient type and recipient.');
                $this->redirect('notifications/send');
                return;
            }

        } catch (\InvalidArgumentException $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
            $this->redirect('notifications/send');
            return;
        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, 'Failed to send notification. Please try again.');
            $this->redirect('notifications/send');
            return;
        }

        $this->redirect('notifications/send');
    }

    // ─── Admin — Send System Notification ────────────────────────────────────

    /**
     * GET /admin/notifications/send
     * System Administrator compose form.
     */
    public function adminSendForm(): void
    {
        $this->requireRole(ROLE_ADMIN);

        $roles = $this->model->allRoles();
        $users = $this->model->allActiveUsers();

        $this->render('notifications.send_admin', [
            'pageTitle' => 'Send System Notification',
            'roles'     => $roles,
            'users'     => $users,
        ]);
    }

    /**
     * POST /admin/notifications/send
     * Admin submit — system-wide, role-based, or single-user.
     */
    public function adminSend(): void
    {
        $this->requireRole(ROLE_ADMIN);
        $this->verifyCsrf();

        $recipientType = trim((string) $this->input('recipient_type', ''));
        $recipientRole = trim((string) $this->input('recipient_role', ''));
        $recipientId   = (int) $this->input('recipient_id', 0);
        $title         = trim((string) $this->input('title', ''));
        $message       = trim((string) $this->input('message', ''));
        $priority      = trim((string) $this->input('priority', 'medium'));
        $senderId      = $this->userId() ?? 0;

        if ($title === '' || $message === '') {
            $this->flash(FLASH_ERROR, 'Title and message are required.');
            $this->redirect('admin/notifications/send');
            return;
        }

        $validPriorities = ['low', 'medium', 'high', 'critical'];
        if (!in_array($priority, $validPriorities, true)) {
            $priority = 'medium';
        }

        try {
            if ($recipientType === 'all') {
                $count = $this->service->sendToAll(
                    NOTIF_SYSTEM_ANNOUNCEMENT, $title, $message, $priority, $senderId
                );
                $this->flash(FLASH_SUCCESS, "System notification sent to all {$count} active users.");

            } elseif ($recipientType === 'role' && $recipientRole !== '') {
                $count = $this->service->sendToRole(
                    $recipientRole,
                    NOTIF_SYSTEM_ANNOUNCEMENT,
                    $title,
                    $message,
                    $priority,
                    $senderId
                );
                $label = ucwords(str_replace('_', ' ', $recipientRole));
                $this->flash(FLASH_SUCCESS, "Notification sent to {$count} user(s) in role: {$label}.");

            } elseif ($recipientType === 'user' && $recipientId > 0) {
                $this->service->sendToUser(
                    $recipientId,
                    NOTIF_SYSTEM_ANNOUNCEMENT,
                    $title,
                    $message,
                    $priority,
                    $senderId
                );
                $this->flash(FLASH_SUCCESS, 'Notification sent to user.');

            } else {
                $this->flash(FLASH_ERROR, 'Please select a valid recipient type.');
                $this->redirect('admin/notifications/send');
                return;
            }

        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, 'Failed to send notification. Please try again.');
            $this->redirect('admin/notifications/send');
            return;
        }

        $this->redirect('admin/notifications/send');
    }

    // ─── AJAX endpoints ───────────────────────────────────────────────────────

    /**
     * GET /api/notifications
     * AJAX endpoint — returns unread count and recent notifications.
     */
    public function apiList(): void
    {
        $this->requireAuth();

        $userId        = $this->userId();
        $unreadCount   = 0;
        $notifications = [];

        try {
            $unreadCount = $this->model->unreadCount($userId);
            $rows        = $this->model->recentForUser($userId, 10);

            foreach ($rows as &$n) {
                $n['created_ago'] = \AAM\Helpers\DateHelper::timeAgo($n['created_at'] ?? '');
            }
            unset($n);

            $notifications = $rows;

        } catch (\Throwable $e) {
            // Table may not exist yet — return empty
        }

        $this->jsonSuccess([
            'unread_count'  => $unreadCount,
            'notifications' => $notifications,
        ]);
    }

    /**
     * POST /api/notifications/{id}/read
     * AJAX mark-read.
     */
    public function apiMarkRead(string $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        try {
            Database::getInstance()->execute(
                "UPDATE notifications
                 SET is_read = 1, read_at = NOW()
                 WHERE id = :id AND recipient_user_id = :uid",
                ['id' => (int)$id, 'uid' => $this->userId()]
            );
        } catch (\Throwable $e) {
            // Ignore
        }

        $this->jsonSuccess(null, 'Marked as read.');
    }
}
