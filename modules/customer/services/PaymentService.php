<?php
/**
 * =========================================================
 * AU-AgroSale — Customer PaymentService
 * =========================================================
 * File    : modules/customer/services/PaymentService.php
 * Purpose : Thin facade over POS MobilePaymentService for
 *           customer-facing payment operations.
 *           All heavy lifting is delegated to the existing
 *           POS payment infrastructure.
 *
 *           Phase 5: Real implementation delegates to
 *           AAM\POS\Services\MobilePaymentService.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Customer\Services;

use AAM\POS\Services\MobilePaymentService;
use AAM\Settings\Services\PaymentMethodService;

class PaymentService
{
    private MobilePaymentService $mpService;
    private PaymentMethodService $pmService;

    public function __construct()
    {
        $this->mpService = new MobilePaymentService();
        $this->pmService = new PaymentMethodService();
    }

    /**
     * Get active mobile banking methods for customer checkout.
     */
    public function getActiveMobileMethods(): array
    {
        $all = $this->pmService->getAllForAdmin();
        return array_filter($all, fn($m) => $m['is_active'] && $m['type'] === 'mobile_banking');
    }

    /**
     * Check if a provider is currently active.
     */
    public function isProviderActive(string $providerCode): bool
    {
        return $this->pmService->isProviderActive($providerCode);
    }

    /**
     * Get payment method by ID.
     */
    public function getMethodById(int $id): ?array
    {
        return $this->pmService->getById($id);
    }

    /**
     * Get payment status for a mobile_payments row.
     */
    public function getStatus(int $mobilePaymentId): array
    {
        return $this->mpService->getPaymentStatus($mobilePaymentId);
    }
}
