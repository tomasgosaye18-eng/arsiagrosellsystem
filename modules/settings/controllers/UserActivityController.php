<?php
/**
 * =========================================================
 * USER ACTIVITY CONTROLLER
 * =========================================================
 * File    : modules/settings/controllers/UserActivityController.php
 * Purpose : Display recent activity for all users:
 *           login history + activity/audit logs.
 *           System Administrator only.
 * Route   : GET /settings/user-activity
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Settings\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;

class UserActivityController extends BaseController
{
    public function index(): void
    {
        $this->requireRole(ROLE_ADMIN);

        $db = Database::getInstance();

        // ── Filters ──────────────────────────────────────────────
        $filterUser   = trim($this->query('user', ''));
        $filterAction = trim($this->query('action', ''));
        $filterDate   = trim($this->query('date', ''));
        $page         = max(1, (int)$this->query('page', 1));
        $perPage      = 25;
        $offset       = ($page - 1) * $perPage;

        // ── Login History ─────────────────────────────────────────
        $loginWhere  = ['1=1'];
        $loginParams = [];

        if ($filterUser !== '') {
            $loginWhere[]            = '(u.username LIKE :uq OR lh.username_attempted LIKE :uq2)';
            $loginParams['uq']       = "%{$filterUser}%";
            $loginParams['uq2']      = "%{$filterUser}%";
        }
        if ($filterDate !== '') {
            $loginWhere[]            = 'DATE(lh.attempted_at) = :ldate';
            $loginParams['ldate']    = $filterDate;
        }

        $loginWhereStr = implode(' AND ', $loginWhere);

        $totalLogins = (int)$db->fetchColumn(
            "SELECT COUNT(*) FROM login_history lh
             LEFT JOIN users u ON u.id = lh.user_id
             WHERE {$loginWhereStr}",
            $loginParams
        );

        $logins = $db->fetchAll(
            "SELECT
                lh.id,
                lh.username_attempted,
                lh.was_successful,
                lh.ip_address,
                lh.browser,
                lh.os_name,
                lh.device_type,
                lh.failure_reason,
                lh.attempted_at,
                u.id         AS user_id,
                CONCAT(u.first_name,' ',u.last_name) AS full_name,
                r.name       AS role_name
             FROM login_history lh
             LEFT JOIN users u  ON u.id  = lh.user_id
             LEFT JOIN roles r  ON r.id  = u.role_id
             WHERE {$loginWhereStr}
             ORDER BY lh.attempted_at DESC
             LIMIT :limit OFFSET :offset",
            array_merge($loginParams, ['limit' => $perPage, 'offset' => $offset])
        );

        // ── Activity Logs ─────────────────────────────────────────
        $actWhere  = ['1=1'];
        $actParams = [];

        if ($filterUser !== '') {
            $actWhere[]           = 'u.username LIKE :auq';
            $actParams['auq']     = "%{$filterUser}%";
        }
        if ($filterAction !== '') {
            $actWhere[]           = 'al.action LIKE :aact';
            $actParams['aact']    = "%{$filterAction}%";
        }
        if ($filterDate !== '') {
            $actWhere[]           = 'DATE(al.created_at) = :adate';
            $actParams['adate']   = $filterDate;
        }

        $actWhereStr = implode(' AND ', $actWhere);

        $recentActivity = $db->fetchAll(
            "SELECT
                al.id,
                al.action,
                al.description,
                al.subject_type,
                al.subject_id,
                al.ip_address,
                al.created_at,
                u.username,
                CONCAT(u.first_name,' ',u.last_name) AS full_name,
                r.name AS role_name
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE {$actWhereStr}
             ORDER BY al.created_at DESC
             LIMIT 50",
            $actParams
        );

        // ── Online Users (active sessions in last 15 min) ─────────
        $onlineUsers = $db->fetchAll(
            "SELECT DISTINCT
                u.id,
                u.username,
                CONCAT(u.first_name,' ',u.last_name) AS full_name,
                r.name AS role_name,
                MAX(lh.attempted_at) AS last_seen
             FROM login_history lh
             JOIN users u ON u.id = lh.user_id
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE lh.was_successful = 1
               AND lh.attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
             GROUP BY u.id, u.username, u.first_name, u.last_name, r.name
             ORDER BY last_seen DESC"
        );

        // ── Distinct action types for filter dropdown ─────────────
        $actionTypes = $db->fetchAll(
            "SELECT DISTINCT action FROM activity_logs ORDER BY action ASC"
        );

        // ── Summary stats ─────────────────────────────────────────
        $stats = [
            'total_logins_today'   => (int)$db->fetchColumn(
                "SELECT COUNT(*) FROM login_history WHERE DATE(attempted_at) = CURDATE()"
            ),
            'failed_logins_today'  => (int)$db->fetchColumn(
                "SELECT COUNT(*) FROM login_history WHERE DATE(attempted_at) = CURDATE() AND was_successful = 0"
            ),
            'active_users_today'   => (int)$db->fetchColumn(
                "SELECT COUNT(DISTINCT user_id) FROM login_history WHERE DATE(attempted_at) = CURDATE() AND was_successful = 1"
            ),
            'total_actions_today'  => (int)$db->fetchColumn(
                "SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()"
            ),
        ];

        $totalPages = (int)ceil($totalLogins / $perPage);

        $this->render('settings.user-activity', [
            'pageTitle'      => 'User Activity',
            'logins'         => $logins,
            'recentActivity' => $recentActivity,
            'onlineUsers'    => $onlineUsers,
            'actionTypes'    => $actionTypes,
            'stats'          => $stats,
            'totalLogins'    => $totalLogins,
            'totalPages'     => $totalPages,
            'currentPage'    => $page,
            'perPage'        => $perPage,
            'filterUser'     => $filterUser,
            'filterAction'   => $filterAction,
            'filterDate'     => $filterDate,
        ]);
    }
}
