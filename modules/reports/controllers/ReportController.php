<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/reports/controllers/ReportController.php
 * Purpose : Business reports controller.
 *           Farm Manager is redirected to FarmReportController.
 *           Admin / GM / Warehouse see the
 *           general stub navigation.
 * Routes  :
 *   GET /reports                     â†’ index()
 *   GET /reports/farm-production     â†’ farmProduction()
 *   GET /reports/warehouse-inventory â†’ warehouseInventory()
 *   GET /reports/transfers           â†’ transfers()
 *   GET /reports/sales               â†’ sales()
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Reports\Controllers;

use AAM\Core\BaseController;

class ReportController extends BaseController
{
    private function stub(string $title): void
    {
        $this->requireAuth();
        $this->render('reports.stub', [
            'pageTitle' => $title,
            'module'    => $title,
        ]);
    }

    /** GET /reports */
    public function index(): void
    {
        $this->requireAuth();

        // Farm Managers only see farm-specific reports
        if ($this->userRole() === ROLE_FARM_MANAGER) {
            $this->redirect('/reports/farm');
            return;
        }

        // Admin and GM go to their administration reports hub
        if (in_array($this->userRole(), [ROLE_GENERAL_MANAGER], true)) {
            $this->redirect('/reports/admin');
            return;
        }

        // Warehouse Manager goes to warehouse reports
        if (in_array($this->userRole(), [ROLE_WAREHOUSE_MANAGER], true)) {
            $this->redirect('/reports/warehouse');
            return;
        }

        $this->stub('Reports');
    }

    /** GET /reports/farm-production */
    public function farmProduction(): void
    {
        $this->requireAuth();

        // Farm Managers should use the dedicated farm reports
        if ($this->userRole() === ROLE_FARM_MANAGER) {
            $this->redirect('/reports/farm/production');
            return;
        }

        $this->stub('Farm Production Report');
    }

    /** GET /reports/warehouse-inventory */
    public function warehouseInventory(): void { $this->stub('Warehouse Inventory Report'); }

    /** GET /reports/transfers */
    public function transfers(): void { $this->stub('Transfer Report'); }

    /** GET /reports/sales */
    public function sales(): void { $this->stub('Sales & Revenue Report'); }
}

