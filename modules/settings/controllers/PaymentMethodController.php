<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File    : modules/settings/controllers/PaymentMethodController.php
 * Purpose : System Administration → System Configuration →
 *           Payment Methods management.
 *
 * ACCESS   : ROLE_ADMIN only (System Administrator).
 *            ROLE_GENERAL_MANAGER has read-only access via
 *            their own view (not this controller).
 *
 * SECURITY :
 *   - provider_config (credentials/keys) is NEVER returned
 *     to any view or JSON response.
 *   - Only GET /admin/settings/payment-methods/{id}/config
 *     allows an admin to see which fields are configured
 *     (not the values of sensitive fields).
 *   - All write actions require CSRF verification.
 *
 * Routes   (all under /settings/payment-methods, ROLE_ADMIN):
 *   GET    /settings/payment-methods           → index()
 *   POST   /settings/payment-methods/{id}/toggle → toggle()
 *   GET    /settings/payment-methods/{id}      → detail()
 *   GET    /settings/payment-methods/{id}/config → configForm()
 *   POST   /settings/payment-methods/{id}/config → saveConfig()
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Settings\Controllers;

use AAM\Core\BaseController;
use AAM\Settings\Services\PaymentMethodService;

class PaymentMethodController extends BaseController
{
    private PaymentMethodService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new PaymentMethodService();
    }

    // ─── Shared auth guard ────────────────────────────────────────────────
    private function guard(): void
    {
        $this->requireAuth();
        $this->requireRole(ROLE_ADMIN);
    }

    // =========================================================
    // GET /settings/payment-methods
    // Payment Methods list — all methods with status.
    // =========================================================
    public function index(): void
    {
        $this->guard();

        $methods = $this->service->getAllForAdmin();

        $this->render('settings.payment_methods.index', [
            'pageTitle' => 'Payment Methods',
            'methods'   => $methods,
            'userRole'  => $this->userRole(),
        ]);
    }

    // =========================================================
    // GET /settings/payment-methods/{id}
    // Payment method detail + audit history.
    // =========================================================
    public function detail(string $id): void
    {
        $this->guard();

        $methodId = (int)$id;
        $method   = $this->service->getById($methodId);

        if (!$method) {
            $this->flash(FLASH_ERROR, 'Payment method not found.');
            $this->redirect('/settings/payment-methods');
            return;
        }

        $audit = $this->service->getAuditHistory($methodId);

        $this->render('settings.payment_methods.detail', [
            'pageTitle' => 'Payment Method: ' . e($method['name']),
            'method'    => $method,
            'audit'     => $audit,
            'userRole'  => $this->userRole(),
        ]);
    }

    // =========================================================
    // POST /settings/payment-methods/{id}/toggle
    // Activate or deactivate a payment method.
    // Body: csrf_token
    // =========================================================
    public function toggle(string $id): void
    {
        $this->guard();
        $this->verifyCsrf();

        $methodId = (int)$id;

        try {
            $method = $this->service->getById($methodId);
            if (!$method) {
                throw new \RuntimeException('Payment method not found.');
            }

            if ((bool)$method['is_active']) {
                $this->service->deactivate($methodId, (int)$this->userId());
                $verb = 'deactivated';
            } else {
                $this->service->activate($methodId, (int)$this->userId());
                $verb = 'activated';
            }

            $this->flash(
                FLASH_SUCCESS,
                "Payment method \"{$method['name']}\" has been {$verb}."
            );
        } catch (\RuntimeException $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, 'An unexpected error occurred. Please try again.');
        }

        $this->redirect('/settings/payment-methods');
    }

    // =========================================================
    // GET /settings/payment-methods/{id}/config
    // Provider configuration form.
    // Shows which fields are configured — NOT their values.
    // Sensitive fields (api_key, app_key) are shown as password
    // inputs with placeholder "Leave blank to keep existing".
    // =========================================================
    public function configForm(string $id): void
    {
        $this->guard();

        $methodId = (int)$id;
        $method   = $this->service->getById($methodId);

        if (!$method) {
            $this->flash(FLASH_ERROR, 'Payment method not found.');
            $this->redirect('/settings/payment-methods');
            return;
        }

        if ($method['type'] === 'cash') {
            $this->flash(FLASH_INFO, 'Cash payment method does not require provider configuration.');
            $this->redirect("/settings/payment-methods/{$methodId}");
            return;
        }

        $fieldMeta = $this->service->getConfigFieldMeta($method['code']);

        $this->render('settings.payment_methods.config_form', [
            'pageTitle' => 'Configure: ' . e($method['name']),
            'method'    => $method,
            'fieldMeta' => $fieldMeta,
            'userRole'  => $this->userRole(),
        ]);
    }

    // =========================================================
    // POST /settings/payment-methods/{id}/config
    // Save provider configuration.
    // =========================================================
    public function saveConfig(string $id): void
    {
        $this->guard();
        $this->verifyCsrf();

        $methodId    = (int)$id;
        $description = trim($this->input('description', ''));

        $method = $this->service->getById($methodId);
        if (!$method) {
            $this->flash(FLASH_ERROR, 'Payment method not found.');
            $this->redirect('/settings/payment-methods');
            return;
        }

        if ($method['type'] === 'cash') {
            $this->flash(FLASH_ERROR, 'Cash does not support provider configuration.');
            $this->redirect('/settings/payment-methods');
            return;
        }

        // Collect only allowed config keys from POST
        $allowedKeys = $this->service->getAllowedConfigKeys($method['code']);
        $rawConfig   = [];
        foreach ($allowedKeys as $key) {
            $val = $this->input($key, '');
            if ($val !== '') {
                $rawConfig[$key] = $val;
            }
        }

        try {
            $this->service->updateProviderConfig(
                $methodId,
                $rawConfig,
                $description,
                (int)$this->userId()
            );

            $this->flash(
                FLASH_SUCCESS,
                "Provider configuration for \"{$method['name']}\" saved successfully."
            );
        } catch (\RuntimeException $e) {
            $this->flash(FLASH_ERROR, $e->getMessage());
        } catch (\Throwable $e) {
            $this->flash(FLASH_ERROR, 'Failed to save configuration. Please try again.');
        }

        $this->redirect("/settings/payment-methods/{$methodId}");
    }
}
