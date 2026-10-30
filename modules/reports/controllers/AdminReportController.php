<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/reports/controllers/AdminReportController.php
 * Purpose : System Administration Reports controller.
 *
 * Routes  :
 *   GET /reports/admin              → index()     — hub
 *   GET /reports/admin/users        → users()     — user report
 *   GET /reports/admin/organization → organization() — org report
 *   GET /reports/admin/audit        → audit()     — audit summary
 *
 * Access  : ROLE_ADMIN + ROLE_GENERAL_MANAGER only
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Reports\Controllers;

use AAM\Core\BaseController;
use AAM\Reports\Models\AdminReportModel;

class AdminReportController extends BaseController
{
    private AdminReportModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new AdminReportModel();
    }

    // ──────────────────────────────────────────────────────
    // GET /reports/admin  — Hub
    // ──────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_ADMIN, ROLE_GENERAL_MANAGER);

        $summary = $this->model->getHubSummary();

        $this->render('reports.admin.index', [
            'pageTitle' => 'Administration Reports',
            'summary'   => $summary,
            'userRole'  => $this->userRole(),
        ]);
    }

    // ──────────────────────────────────────────────────────
    // GET /reports/admin/users  — User Report (ADMIN only)
    // ──────────────────────────────────────────────────────

    public function users(): void
    {
        $this->requireAuth();
        // User report contains sensitive account data — System Administrator only.
        $this->requireRole(ROLE_ADMIN);

        $filters = [
            'search' => $this->query('search', ''),
            'role'   => $this->query('role', ''),
            'status' => $this->query('status', ''),
        ];
        [$dateFrom, $dateTo] = $this->getDateRange();
        $page   = max(1, (int)$this->query('page', 1));

        $summary    = $this->model->getUserSummary();
        $byRole     = $this->model->getUsersByRole();
        $userList   = $this->model->paginateUsers($page, 25, $filters);
        $loginTrend = $this->model->getLoginTrend($dateFrom, $dateTo);
        $failedLogins = $this->model->getRecentFailedLogins(15);
        $roles      = $this->model->getRolesForFilter();

        $this->render('reports.admin.users', [
            'pageTitle'    => 'User Report',
            'summary'      => $summary,
            'byRole'       => $byRole,
            'userList'     => $userList,
            'loginTrend'   => $loginTrend,
            'failedLogins' => $failedLogins,
            'roles'        => $roles,
            'filters'      => $filters,
            'dateFrom'     => $dateFrom,
            'dateTo'       => $dateTo,
            'datePreset'   => $this->query('preset', 'month'),
            'userRole'     => $this->userRole(),
        ]);
    }

    // ──────────────────────────────────────────────────────
    // GET /reports/admin/organization  — Organization Report
    // ──────────────────────────────────────────────────────

    public function organization(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_ADMIN, ROLE_GENERAL_MANAGER);

        $summary     = $this->model->getOrgSummary();
        $farms       = $this->model->getFarmsWithStaff();
        $warehouses  = $this->model->getWarehousesWithStaff();
        $shops       = $this->model->getShopsWithStaff();
        $assignments = $this->model->getAllAssignments();

        $this->render('reports.admin.organization', [
            'pageTitle'   => 'Organization Report',
            'summary'     => $summary,
            'farms'       => $farms,
            'warehouses'  => $warehouses,
            'shops'       => $shops,
            'assignments' => $assignments,
            'userRole'    => $this->userRole(),
        ]);
    }

    // ──────────────────────────────────────────────────────
    // GET /reports/admin/audit  — Audit Summary Report
    // ──────────────────────────────────────────────────────

    public function audit(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_ADMIN);

        [$dateFrom, $dateTo] = $this->getDateRange();
        $filters = [
            'module'    => $this->query('module', ''),
            'user_id'   => (int)$this->query('user_id', 0),
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'search'    => $this->query('search', ''),
        ];
        $page = max(1, (int)$this->query('page', 1));

        $summary     = $this->model->getAuditSummary($dateFrom, $dateTo);
        $byModule    = $this->model->getActionsByModule($dateFrom, $dateTo);
        $topUsers    = $this->model->getTopActiveUsers($dateFrom, $dateTo, 10);
        $auditTrend  = $this->model->getAuditTrend($dateFrom, $dateTo);
        $logList     = $this->model->paginateAuditLog($page, 25, $filters);
        $roles       = $this->model->getRolesForFilter();

        $this->render('reports.admin.audit', [
            'pageTitle'   => 'Audit Summary Report',
            'summary'     => $summary,
            'byModule'    => $byModule,
            'topUsers'    => $topUsers,
            'auditTrend'  => $auditTrend,
            'logList'     => $logList,
            'roles'       => $roles,
            'filters'     => $filters,
            'dateFrom'    => $dateFrom,
            'dateTo'      => $dateTo,
            'datePreset'  => $this->query('preset', 'month'),
            'userRole'    => $this->userRole(),
        ]);
    }

    // ──────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────

    /**
     * Resolve date range from preset or custom inputs.
     * Default: This Month.
     *
     * @return array{string, string} [dateFrom, dateTo]
     */
    private function getDateRange(): array
    {
        $preset   = $this->query('preset', 'month');
        $custFrom = $this->query('date_from', '');
        $custTo   = $this->query('date_to', '');

        return match($preset) {
            'today'  => [date('Y-m-d'), date('Y-m-d')],
            'week'   => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d')],
            'custom' => [
                $custFrom ?: date('Y-m-01'),
                $custTo   ?: date('Y-m-d'),
            ],
            default  => [date('Y-m-01'), date('Y-m-d')],  // month
        };
    }
}
