<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/general_manager/controllers/GMReportsController.php
 * Purpose : GM business reports hub. Delegates to existing
 *           report controllers. No admin/system reports.
 * Routes  : GET /gm/reports
 *           GET /gm/reports/audit
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\GeneralManager\Controllers;

use AAM\Core\BaseController;
use AAM\GeneralManager\Services\GeneralManagerService;
use AAM\Reports\Models\AdminReportModel;

class GMReportsController extends BaseController
{
    private GeneralManagerService $gms;
    private AdminReportModel $auditModel;

    public function __construct()
    {
        parent::__construct();
        $this->gms        = new GeneralManagerService();
        $this->auditModel = new AdminReportModel();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $this->render('general_manager.reports.index', [
            'pageTitle'      => 'Business Reports',
            'farmPerf'       => $this->gms->getFarmPerformance(),
            'warehousePerf'  => $this->gms->getWarehousePerformance(),
            'shopPerf'       => $this->gms->getShopPerformance(),
            'transferPipeline' => $this->gms->getTransferPipeline(),
        ]);
    }

    // ── GET /gm/reports/audit — Audit Summary Report ──────────────────────────

    public function audit(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        [$dateFrom, $dateTo] = $this->getDateRange();
        $filters = [
            'module'    => $this->query('module', ''),
            'user_id'   => (int)$this->query('user_id', 0),
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'search'    => $this->query('search', ''),
        ];
        $page = max(1, (int)$this->query('page', 1));

        $this->render('reports.admin.audit', [
            'pageTitle'  => 'Audit Summary Report',
            'summary'    => $this->auditModel->getAuditSummary($dateFrom, $dateTo),
            'byModule'   => $this->auditModel->getActionsByModule($dateFrom, $dateTo),
            'topUsers'   => $this->auditModel->getTopActiveUsers($dateFrom, $dateTo, 10),
            'auditTrend' => $this->auditModel->getAuditTrend($dateFrom, $dateTo),
            'logList'    => $this->auditModel->paginateAuditLog($page, 25, $filters),
            'roles'      => $this->auditModel->getRolesForFilter(),
            'filters'    => $filters,
            'dateFrom'   => $dateFrom,
            'dateTo'     => $dateTo,
            'datePreset' => $this->query('preset', 'month'),
            'userRole'   => $this->userRole(),
            'baseUrl'    => 'gm/reports/audit',
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

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
            default  => [date('Y-m-01'), date('Y-m-d')],
        };
    }
}
