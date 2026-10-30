<?php
/**
 * =========================================================
 * ARSI AGRI-MANAGE (AAM)
 * =========================================================
 * File        : modules/admin/controllers/AdminController.php
 * Purpose     : Global search AJAX endpoint.
 *               Returns role-scoped suggestions from the
 *               real database — never mock/static data.
 *
 * Route       : GET /api/search?q=...
 * Auth        : requireAuth() — session must be active
 * RBAC        : each entity group is filtered per role;
 *               no group is returned if the role cannot
 *               access it under normal navigation either.
 * =========================================================
 */

declare(strict_types=1);

namespace AAM\Admin\Controllers;

use AAM\Core\BaseController;
use AAM\Core\Database;
use AAM\Core\SessionManager;

class AdminController extends BaseController
{
    // ─── Result limits ────────────────────────────────────────────────────────
    private const MAX_PER_GROUP = 5;

    /**
     * GET /api/search?q=...
     * Role-aware global search endpoint.
     */
    public function globalSearch(): void
    {
        $this->requireAuth();

        $query = trim((string) $this->query('q', ''));

        if (strlen($query) < 2) {
            $this->jsonSuccess([]);
            return;
        }

        $role = $this->userRole() ?? '';
        $uid  = (int)($this->userId() ?? 0);
        $db   = Database::getInstance();
        $like = '%' . $query . '%';
        $n    = self::MAX_PER_GROUP;

        $results = [];

        try {
            // ─── 1. PRODUCTS ──────────────────────────────────────────────────
            // Visible to all roles but URL and scope differ.
            if ($role === ROLE_ADMIN || $role === ROLE_GENERAL_MANAGER) {
                $rows = $db->fetchAll(
                    "SELECT id, name, sku AS meta,
                            CONCAT(name, IF(sku IS NOT NULL, CONCAT(' [', sku, ']'), '')) AS label
                     FROM products
                     WHERE (name LIKE :q OR sku LIKE :q2 OR barcode LIKE :q3)
                       AND is_active = 1 AND deleted_at IS NULL
                     ORDER BY name ASC LIMIT :n",
                    ['q' => $like, 'q2' => $like, 'q3' => $like, 'n' => $n]
                );
                // Admin has /admin/products/{id} detail page; GM only has /gm/products list
                $results['products'] = array_map(fn($r) => [
                    'label' => $r['label'],
                    'url'   => $role === ROLE_ADMIN ? '/admin/products/' . $r['id'] : '/gm/products',
                    'meta'  => $r['meta'],
                ], $rows);

            } elseif ($role === ROLE_WAREHOUSE_MANAGER || $role === ROLE_SALESPERSON) {
                $rows = $db->fetchAll(
                    "SELECT id, name, sku AS meta,
                            CONCAT(name, IF(sku IS NOT NULL, CONCAT(' [', sku, ']'), '')) AS label
                     FROM products
                     WHERE (name LIKE :q OR sku LIKE :q2)
                       AND is_active = 1 AND deleted_at IS NULL
                     ORDER BY name ASC LIMIT :n",
                    ['q' => $like, 'q2' => $like, 'n' => $n]
                );
                $results['products'] = array_map(fn($r) => [
                    'label' => $r['label'],
                    'url'   => '/products/' . $r['id'],
                    'meta'  => $r['meta'],
                ], $rows);

            } elseif ($role === ROLE_FARM_MANAGER) {
                // Farm Manager: only products assigned to their farms
                $rows = $db->fetchAll(
                    "SELECT DISTINCT p.id, p.name,
                            p.sku AS meta,
                            CONCAT(p.name, IF(p.sku IS NOT NULL, CONCAT(' [', p.sku, ']'), '')) AS label
                     FROM products p
                     INNER JOIN farm_products fp ON fp.product_id = p.id AND fp.is_active = 1
                     INNER JOIN (
                         SELECT location_id FROM user_location_assignments
                         WHERE user_id = :uid1 AND location_type = 'farm'
                         UNION
                         SELECT location_id FROM organization_assignments
                         WHERE user_id = :uid2 AND location_type = 'farm' AND is_active = 1
                     ) assigned_farms ON fp.farm_id = assigned_farms.location_id
                     WHERE (p.name LIKE :q OR p.sku LIKE :q2)
                       AND p.is_active = 1 AND p.deleted_at IS NULL
                     ORDER BY p.name ASC LIMIT :n",
                    ['uid1' => $uid, 'uid2' => $uid, 'q' => $like, 'q2' => $like, 'n' => $n]
                );
                $results['products'] = array_map(fn($r) => [
                    'label' => $r['label'],
                    'url'   => '/farm/products/' . $r['id'],
                    'meta'  => $r['meta'],
                ], $rows);
            }

            // ─── 2. FARMS ─────────────────────────────────────────────────────
            // Admin / GM see all farms. Farm Manager sees only assigned.
            if ($role === ROLE_ADMIN || $role === ROLE_GENERAL_MANAGER) {
                $rows = $db->fetchAll(
                    "SELECT id, name, farm_type AS meta
                     FROM farms
                     WHERE name LIKE :q AND is_active = 1 AND deleted_at IS NULL
                     ORDER BY name ASC LIMIT :n",
                    ['q' => $like, 'n' => $n]
                );
                $base = $role === ROLE_ADMIN ? '/admin/organization/farms/' : '/gm/farms';
                $results['farms'] = array_map(fn($r) => [
                    'label' => $r['name'],
                    'url'   => $role === ROLE_ADMIN ? $base . $r['id'] : $base,
                    'meta'  => $r['meta'],
                ], $rows);

            } elseif ($role === ROLE_FARM_MANAGER) {
                $rows = $db->fetchAll(
                    "SELECT f.id, f.name, f.farm_type AS meta
                     FROM farms f
                     INNER JOIN (
                         SELECT location_id FROM user_location_assignments
                         WHERE user_id = :uid1 AND location_type = 'farm'
                         UNION
                         SELECT location_id FROM organization_assignments
                         WHERE user_id = :uid2 AND location_type = 'farm' AND is_active = 1
                     ) af ON f.id = af.location_id
                     WHERE f.name LIKE :q AND f.is_active = 1
                     ORDER BY f.name ASC LIMIT :n",
                    ['uid1' => $uid, 'uid2' => $uid, 'q' => $like, 'n' => $n]
                );
                $results['farms'] = array_map(fn($r) => [
                    'label' => $r['name'],
                    'url'   => '/farms/' . $r['id'],
                    'meta'  => $r['meta'],
                ], $rows);
            }

            // ─── 3. WAREHOUSES ────────────────────────────────────────────────
            // Admin / GM / Warehouse Manager
            if (in_array($role, [ROLE_ADMIN, ROLE_GENERAL_MANAGER, ROLE_WAREHOUSE_MANAGER], true)) {
                $rows = $db->fetchAll(
                    "SELECT id, name FROM warehouses
                     WHERE name LIKE :q AND is_active = 1 AND deleted_at IS NULL
                     ORDER BY name ASC LIMIT :n",
                    ['q' => $like, 'n' => $n]
                );
                $urlBase = match ($role) {
                    ROLE_ADMIN             => '/admin/organization/warehouses/',
                    ROLE_GENERAL_MANAGER   => '/gm/warehouses',
                    ROLE_WAREHOUSE_MANAGER => '/warehouse',
                    default                => '/warehouse',
                };
                $results['warehouses'] = array_map(fn($r) => [
                    'label' => $r['name'],
                    'url'   => ($role === ROLE_ADMIN) ? $urlBase . $r['id'] : $urlBase,
                    'meta'  => 'Warehouse',
                ], $rows);
            }

            // ─── 4. SHOPS ─────────────────────────────────────────────────────
            // Admin / GM / Salesperson (scoped)
            if ($role === ROLE_ADMIN || $role === ROLE_GENERAL_MANAGER) {
                $rows = $db->fetchAll(
                    "SELECT id, name FROM shops
                     WHERE name LIKE :q AND is_active = 1 AND deleted_at IS NULL
                     ORDER BY name ASC LIMIT :n",
                    ['q' => $like, 'n' => $n]
                );
                $results['shops'] = array_map(fn($r) => [
                    'label' => $r['name'],
                    'url'   => ($role === ROLE_ADMIN) ? '/admin/organization/shops/' . $r['id'] : '/gm/shops',
                    'meta'  => 'Shop',
                ], $rows);

            } elseif ($role === ROLE_SALESPERSON) {
                // Salesperson sees only their assigned shop
                $rows = $db->fetchAll(
                    "SELECT s.id, s.name
                     FROM shops s
                     INNER JOIN (
                         SELECT location_id FROM user_location_assignments
                         WHERE user_id = :uid AND location_type = 'shop'
                         UNION
                         SELECT location_id FROM organization_assignments
                         WHERE user_id = :uid2 AND location_type = 'shop' AND is_active = 1
                     ) as_ ON s.id = as_.location_id
                     WHERE s.name LIKE :q AND s.is_active = 1
                     ORDER BY s.name ASC LIMIT :n",
                    ['uid' => $uid, 'uid2' => $uid, 'q' => $like, 'n' => $n]
                );
                $results['shops'] = array_map(fn($r) => [
                    'label' => $r['name'],
                    'url'   => '/shops/' . $r['id'],
                    'meta'  => 'Shop',
                ], $rows);
            }

            // ─── 5. USERS ─────────────────────────────────────────────────────
            // Admin only — other roles must not search users
            if ($role === ROLE_ADMIN) {
                $rows = $db->fetchAll(
                    "SELECT u.id,
                            CONCAT(u.first_name, ' ', u.last_name) AS fullname,
                            u.username,
                            r.display_name AS role_name
                     FROM users u
                     LEFT JOIN roles r ON r.id = u.role_id
                     WHERE (u.first_name LIKE :q OR u.last_name LIKE :q2
                            OR u.username LIKE :q3 OR u.employee_id LIKE :q4)
                       AND u.account_status != 'deleted'
                     ORDER BY u.first_name ASC LIMIT :n",
                    ['q' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like, 'n' => $n]
                );
                $results['users'] = array_map(fn($r) => [
                    'label' => trim($r['fullname']) ?: $r['username'],
                    'url'   => '/admin/users/' . $r['id'],
                    'meta'  => $r['role_name'],
                ], $rows);
            }

            // ─── 6. TRANSFERS ─────────────────────────────────────────────────
            // Admin / GM / Warehouse Manager / Salesperson
            if (in_array($role, [ROLE_ADMIN, ROLE_GENERAL_MANAGER,
                                  ROLE_WAREHOUSE_MANAGER, ROLE_SALESPERSON], true)) {
                $rows = $db->fetchAll(
                    "SELECT t.id, t.transfer_number AS label, t.status AS meta
                     FROM warehouse_transfers t
                     WHERE t.transfer_number LIKE :q
                     ORDER BY t.created_at DESC LIMIT :n",
                    ['q' => $like, 'n' => $n]
                );
                $results['transfers'] = array_map(fn($r) => [
                    'label' => $r['label'],
                    'url'   => '/transfers/' . $r['id'],
                    'meta'  => $r['meta'],
                ], $rows);
            }

            // ─── 7. SALES ─────────────────────────────────────────────────────
            // GM / Salesperson
            if ($role === ROLE_GENERAL_MANAGER || $role === ROLE_SALESPERSON) {
                $scopeSql = '';
                $scopeBind = [];
                if ($role === ROLE_SALESPERSON) {
                    // Scope: sales from this salesperson's assigned shop
                    $scopeSql = " AND s.shop_id IN (
                        SELECT location_id FROM user_location_assignments
                        WHERE user_id = :uid AND location_type = 'shop'
                        UNION
                        SELECT location_id FROM organization_assignments
                        WHERE user_id = :uid2 AND location_type = 'shop' AND is_active = 1
                    )";
                    $scopeBind = ['uid' => $uid, 'uid2' => $uid];
                }
                $rows = $db->fetchAll(
                    "SELECT s.id, s.sale_number AS label, s.status AS meta
                     FROM sales s
                     WHERE s.sale_number LIKE :q {$scopeSql}
                     ORDER BY s.created_at DESC LIMIT :n",
                    array_merge(['q' => $like, 'n' => $n], $scopeBind)
                );
                $urlBase = $role === ROLE_SALESPERSON ? '/pos/receipt/' : '/gm/shops/sales';
                $results['sales'] = array_map(fn($r) => [
                    'label' => $r['label'],
                    'url'   => ($role === ROLE_SALESPERSON) ? $urlBase . $r['id'] : $urlBase,
                    'meta'  => $r['meta'],
                ], $rows);
            }

            // ─── 8. INVENTORY ─────────────────────────────────────────────────
            // Warehouse Manager / GM
            if ($role === ROLE_WAREHOUSE_MANAGER || $role === ROLE_GENERAL_MANAGER) {
                $rows = $db->fetchAll(
                    "SELECT wi.id,
                            p.name AS label,
                            CONCAT(CAST(wi.current_quantity AS CHAR), ' ', u.abbreviation) AS meta
                     FROM warehouse_inventory wi
                     INNER JOIN products p ON p.id = wi.product_id
                     LEFT JOIN units_of_measure u ON u.id = p.unit_id
                     WHERE p.name LIKE :q AND wi.current_quantity > 0
                     ORDER BY p.name ASC LIMIT :n",
                    ['q' => $like, 'n' => $n]
                );
                $results['inventory'] = array_map(fn($r) => [
                    'label' => $r['label'],
                    'url'   => '/inventory',
                    'meta'  => $r['meta'],
                ], $rows);
            }

        } catch (\Throwable $e) {
            // Log the error but still return any results gathered so far
            \AAM\Core\Logger::error('GlobalSearch error', [
                'error' => $e->getMessage(),
                'query' => $query,
                'role'  => $role,
            ]);
        }

        $this->jsonSuccess($results);
    }
}
