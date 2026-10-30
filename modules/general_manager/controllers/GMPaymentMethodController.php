<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/general_manager/controllers/GMPaymentMethodController.php
 * Purpose : General Manager — read-only view of payment method
 *           availability and status.
 *
 * ACCESS  : ROLE_GENERAL_MANAGER only.
 *
 * SCOPE   : GM can VIEW which payment methods are active/inactive.
 *           GM CANNOT:
 *             - Activate or deactivate methods
 *             - Configure provider credentials
 *             - See API keys or secrets
 *           Those actions are exclusively for ROLE_ADMIN via
 *           /settings/payment-methods.
 *
 * Routes  :
 *   GET /gm/payment-methods   → index()
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\GeneralManager\Controllers;

use AAM\Core\BaseController;
use AAM\Settings\Services\PaymentMethodService;

class GMPaymentMethodController extends BaseController
{
    // =========================================================
    // GET /gm/payment-methods
    // Read-only overview of all payment methods and their status.
    // =========================================================
    public function index(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_GENERAL_MANAGER);

        $pmService = new PaymentMethodService();
        $methods   = $pmService->getAllForAdmin(); // safe — no provider_config returned

        $this->render('general_manager.payment_methods', [
            'pageTitle' => 'Payment Methods — Overview',
            'methods'   => $methods,
            'userRole'  => $this->userRole(),
        ]);
    }
}
