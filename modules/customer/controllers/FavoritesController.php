<?php
/**
 * =========================================================
 * AU-AgroSale — Customer FavoritesController
 * =========================================================
 * File    : modules/customer/controllers/FavoritesController.php
 *
 * Routes (all guarded by CustomerAuthMiddleware + CsrfMiddleware):
 *   POST /customer/favorites/toggle   → toggle()
 *
 * The Favorites page is rendered by AccountController::favorites()
 * at GET /customer/account/favorites so it fits inside the
 * existing Account Center layout (sidebar + content area).
 *
 * SECURITY:
 *   - user_id is derived from SessionManager — never trusted
 *     from the request body.
 *   - CustomerAuthMiddleware ensures only ROLE_CUSTOMER users
 *     reach these endpoints.
 *   - CsrfMiddleware protects state-changing POST requests.
 *   - All user_id scoping happens inside FavoritesService.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Customer\Controllers;

use AAM\Core\BaseController;
use AAM\Core\SessionManager;
use AAM\Customer\Services\FavoritesService;

class FavoritesController extends BaseController
{
    private FavoritesService $favService;

    public function __construct()
    {
        parent::__construct();
        $this->favService = new FavoritesService();
    }

    // ──────────────────────────────────────────────────────
    // POST /customer/favorites/toggle (AJAX)
    //
    // Toggles the favorite state for the given product.
    // Returns JSON — the client updates the heart icon state.
    //
    // Request body (URL-encoded):
    //   product_id=123
    //   csrf_token=...   (validated by CsrfMiddleware before this runs)
    //
    // Response:
    //   { success: true,  data: { favorited: true,  count: 5 }, message: "Added to favorites." }
    //   { success: true,  data: { favorited: false, count: 4 }, message: "Removed from favorites." }
    //   { success: false, message: "..." }
    // ──────────────────────────────────────────────────────
    public function toggle(): void
    {
        // CustomerAuthMiddleware already guaranteed authentication.
        // Derive user from session — never trust POST body.
        $userId    = $this->userId();
        $productId = (int)$this->input('product_id', 0);

        if (!$userId || !$productId) {
            $this->jsonError('Invalid request.', 400);
            return;
        }

        $result = $this->favService->toggle((int)$userId, $productId);

        if ($result['success']) {
            $this->jsonSuccess(
                [
                    'favorited' => $result['favorited'],
                    'count'     => $result['count'] ?? 0,
                ],
                $result['message']
            );
        } else {
            $this->jsonError($result['message'], 422);
        }
    }
}
