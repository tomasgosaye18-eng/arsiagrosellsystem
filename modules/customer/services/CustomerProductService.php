<?php
/**
 * =========================================================
 * AU-AgroSale — CustomerProductService
 * =========================================================
 * File    : modules/customer/services/CustomerProductService.php
 * Purpose : Product catalog queries for the customer storefront.
 *           Reads from existing products, categories,
 *           units_of_measure, shop_inventory, and shops tables.
 *           NO new tables. NO duplicate product system.
 * =========================================================
 */
declare(strict_types=1);

namespace AAM\Customer\Services;

use AAM\Core\Database;

class CustomerProductService
{
    private Database $db;

    /** Low-stock threshold: show warning badge below this fraction */
    private const LOW_STOCK_QTY = 5;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ──────────────────────────────────────────────────────
    // PRODUCT LIST (paginated, shop-scoped)
    // ──────────────────────────────────────────────────────

    /**
     * Get paginated products available at a specific shop.
     * Availability comes from shop_inventory.available_quantity.
     * Price comes from products.unit_price (the GM-managed selling price).
     *
     * @param int   $shopId     Selected shop ID
     * @param int   $page       1-based page number
     * @param int   $perPage    Products per page
     * @param array $filters    ['category_id' => int, 'search' => string, 'sort' => string]
     * @return array{data: array, total: int, page: int, perPage: int, totalPages: int}
     */
    public function getProductsByShop(
        int   $shopId,
        int   $page    = 1,
        int   $perPage = 16,
        array $filters = []
    ): array {
        $page    = max(1, $page);
        $perPage = min(max(1, $perPage), 60);
        $offset  = ($page - 1) * $perPage;

        [$where, $params] = $this->buildProductFilters($shopId, $filters);

        $countSql = "
            SELECT COUNT(*) AS c
            FROM products p
            INNER JOIN shop_inventory si
                   ON si.product_id = p.id
                  AND si.shop_id    = :shop_id_count
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE {$where}
        ";
        $countParams = array_merge(['shop_id_count' => $shopId], $params);
        $total = (int)($this->db->fetchOne($countSql, $countParams)['c'] ?? 0);

        $orderBy = $this->resolveOrderBy($filters['sort'] ?? '');

        $sql = "
            SELECT
                p.id,
                p.name              AS product_name,
                p.sku               AS product_code,
                p.description,
                p.unit_price        AS selling_price,
                p.is_featured,
                p.is_perishable,
                p.primary_image,
                p.min_stock_level,
                c.id                AS category_id,
                c.name              AS category_name,
                u.id                AS unit_id,
                u.name              AS unit_name,
                u.abbreviation      AS unit_abbreviation,
                si.available_quantity,
                si.current_quantity,
                si.min_stock_level  AS shop_min_stock_level,
                si.expiry_date,
                si.last_movement_at
            FROM products p
            INNER JOIN shop_inventory si
                   ON si.product_id = p.id
                  AND si.shop_id    = :shop_id
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN units_of_measure u ON u.id = p.unit_id
            WHERE {$where}
            {$orderBy}
            LIMIT :limit OFFSET :offset
        ";

        $dataParams = array_merge(['shop_id' => $shopId], $params, [
            'limit'  => $perPage,
            'offset' => $offset,
        ]);

        $data = $this->db->fetchAll($sql, $dataParams);

        // Attach availability status to each product
        foreach ($data as &$row) {
            $row['availability'] = $this->resolveAvailability(
                (float)$row['available_quantity'],
                (float)($row['shop_min_stock_level'] ?? $row['min_stock_level'] ?? 0),
                $row['expiry_date'] ?? null
            );
        }
        unset($row);

        return [
            'data'       => $data,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int) ceil($total / max(1, $perPage)),
            'from'       => $total > 0 ? $offset + 1 : 0,
            'to'         => min($offset + $perPage, $total),
        ];
    }

    // ──────────────────────────────────────────────────────
    // SINGLE PRODUCT DETAIL (shop-scoped)
    // ──────────────────────────────────────────────────────

    /**
     * Get a single product with its availability at a specific shop.
     * Returns null if product does not exist or is inactive.
     *
     * @param int      $productId
     * @param int|null $shopId    If null, returns product without availability data
     */
    public function getProductDetail(int $productId, ?int $shopId = null): ?array
    {
        $sql = "
            SELECT
                p.id,
                p.name              AS product_name,
                p.sku               AS product_code,
                p.barcode,
                p.description,
                p.unit_price        AS selling_price,
                p.is_featured,
                p.is_perishable,
                p.primary_image,
                p.min_stock_level,
                c.id                AS category_id,
                c.name              AS category_name,
                u.id                AS unit_id,
                u.name              AS unit_name,
                u.abbreviation      AS unit_abbreviation,
                u.unit_type
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN units_of_measure u ON u.id = p.unit_id
            WHERE p.id = :pid
              AND p.is_active = 1
              AND p.deleted_at IS NULL
              AND p.product_status = 'active'
            LIMIT 1
        ";

        $product = $this->db->fetchOne($sql, ['pid' => $productId]);

        if (!$product) {
            return null;
        }

        // Attach shop-specific availability
        if ($shopId !== null) {
            $inv = $this->db->fetchOne(
                "SELECT available_quantity, current_quantity, min_stock_level,
                        expiry_date, last_movement_at
                 FROM shop_inventory
                 WHERE shop_id = :sid AND product_id = :pid
                 LIMIT 1",
                ['sid' => $shopId, 'pid' => $productId]
            );

            $product['available_quantity']   = (float)($inv['available_quantity'] ?? 0);
            $product['current_quantity']     = (float)($inv['current_quantity']   ?? 0);
            $product['shop_min_stock_level'] = (float)($inv['min_stock_level']    ?? 0);
            $product['expiry_date']          = $inv['expiry_date']   ?? null;
            $product['last_movement_at']     = $inv['last_movement_at'] ?? null;
            $product['in_this_shop']         = $inv !== null;
        } else {
            // No shop selected — aggregate across all shops
            $totals = $this->db->fetchOne(
                "SELECT COALESCE(SUM(available_quantity), 0) AS available_quantity,
                        COUNT(DISTINCT shop_id)               AS shop_count
                 FROM shop_inventory
                 WHERE product_id = :pid",
                ['pid' => $productId]
            );
            $product['available_quantity']   = (float)($totals['available_quantity'] ?? 0);
            $product['current_quantity']     = $product['available_quantity'];
            $product['shop_min_stock_level'] = (float)($product['min_stock_level'] ?? 0);
            $product['expiry_date']          = null;
            $product['last_movement_at']     = null;
            $product['in_this_shop']         = false;
            $product['shop_count']           = (int)($totals['shop_count'] ?? 0);
        }

        $product['availability'] = $this->resolveAvailability(
            $product['available_quantity'],
            (float)($product['shop_min_stock_level'] ?? $product['min_stock_level'] ?? 0),
            $product['expiry_date']
        );

        // Attach product images
        $product['images'] = $this->db->fetchAll(
            "SELECT filename, alt_text, is_primary, sort_order
             FROM product_images
             WHERE product_id = :pid
             ORDER BY is_primary DESC, sort_order ASC",
            ['pid' => $productId]
        );

        return $product;
    }

    // ──────────────────────────────────────────────────────
    // SEARCH SUGGESTIONS (AJAX — shop-aware)
    // ──────────────────────────────────────────────────────

    /**
     * Search products for the suggestion dropdown.
     * Respects selected shop — only returns products available there.
     * If no shop selected, searches all active products.
     *
     * @param string   $query
     * @param int|null $shopId
     * @param int      $limit
     * @return array
     */
    public function searchSuggestions(string $query, ?int $shopId = null, int $limit = 8): array
    {
        $query = trim($query);
        if (strlen($query) < 2) {
            return [];
        }

        $like = '%' . $query . '%';

        if ($shopId !== null) {
            $sql = "
                SELECT
                    p.id,
                    p.name      AS product_name,
                    p.sku       AS product_code,
                    p.unit_price AS selling_price,
                    p.primary_image,
                    c.name      AS category_name,
                    u.abbreviation AS unit_abbreviation,
                    si.available_quantity
                FROM products p
                INNER JOIN shop_inventory si
                       ON si.product_id = p.id
                      AND si.shop_id    = :shop_id
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN units_of_measure u ON u.id = p.unit_id
                WHERE p.is_active = 1
                  AND p.deleted_at IS NULL
                  AND p.product_status = 'active'
                  AND (p.name LIKE :q1 OR p.sku LIKE :q2 OR c.name LIKE :q3)
                ORDER BY
                    CASE WHEN p.name LIKE :q4 THEN 0 ELSE 1 END,
                    si.available_quantity DESC,
                    p.name ASC
                LIMIT :lim
            ";
            return $this->db->fetchAll($sql, [
                'shop_id' => $shopId,
                'q1'      => $like,
                'q2'      => $like,
                'q3'      => $like,
                'q4'      => $query . '%',
                'lim'     => $limit,
            ]);
        }

        // No shop selected: search all active products
        $sql = "
            SELECT
                p.id,
                p.name      AS product_name,
                p.sku       AS product_code,
                p.unit_price AS selling_price,
                p.primary_image,
                c.name      AS category_name,
                u.abbreviation AS unit_abbreviation,
                COALESCE(
                    (SELECT SUM(si2.available_quantity)
                     FROM shop_inventory si2
                     WHERE si2.product_id = p.id), 0
                ) AS available_quantity
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN units_of_measure u ON u.id = p.unit_id
            WHERE p.is_active = 1
              AND p.deleted_at IS NULL
              AND p.product_status = 'active'
              AND (p.name LIKE :q1 OR p.sku LIKE :q2 OR c.name LIKE :q3)
            ORDER BY
                CASE WHEN p.name LIKE :q4 THEN 0 ELSE 1 END,
                p.name ASC
            LIMIT :lim
        ";
        return $this->db->fetchAll($sql, [
            'q1'  => $like,
            'q2'  => $like,
            'q3'  => $like,
            'q4'  => $query . '%',
            'lim' => $limit,
        ]);
    }

    // ──────────────────────────────────────────────────────
    // AVAILABILITY COUNTS (shop-scoped — for sidebar filter)
    // ──────────────────────────────────────────────────────

    /**
     * Count products by availability status at a specific shop.
     * Ignores the current availability filter so all buckets show real counts.
     *
     * @param int   $shopId
     * @param array $filters  Same filters as getProductsByShop (availability ignored here)
     * @return array{in_stock: int, low_stock: int, out_of_stock: int}
     */
    public function getAvailCountsByShop(int $shopId, array $filters = []): array
    {
        // Build filters without availability so we count across all buckets
        $filtersNoAvail = $filters;
        unset($filtersNoAvail['availability']);
        $filtersNoAvail['show_out_of_stock'] = true;

        [$where, $params] = $this->buildProductFilters($shopId, $filtersNoAvail);

        $sql = "
            SELECT
                SUM(CASE WHEN si.available_quantity > " . self::LOW_STOCK_QTY . " THEN 1 ELSE 0 END) AS in_stock,
                SUM(CASE WHEN si.available_quantity > 0
                          AND si.available_quantity <= " . self::LOW_STOCK_QTY . " THEN 1 ELSE 0 END) AS low_stock,
                SUM(CASE WHEN si.available_quantity <= 0 THEN 1 ELSE 0 END) AS out_of_stock
            FROM products p
            INNER JOIN shop_inventory si
                   ON si.product_id = p.id
                  AND si.shop_id    = :shop_id_cnt
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE {$where}
        ";
        $row = $this->db->fetchOne($sql, array_merge(['shop_id_cnt' => $shopId], $params)) ?? [];

        return [
            'in_stock'     => (int)($row['in_stock']     ?? 0),
            'low_stock'    => (int)($row['low_stock']    ?? 0),
            'out_of_stock' => (int)($row['out_of_stock'] ?? 0),
        ];
    }

    // ──────────────────────────────────────────────────────
    // ALL PRODUCTS (no shop — aggregate availability)
    // ──────────────────────────────────────────────────────

    /**
     * Get paginated products across all shops (no shop selected).
     * Availability is aggregated from all shop_inventory rows.
     *
     * @param string $search      Optional search query
     * @param string $availability 'in_stock'|'low_stock'|'out_of_stock'|''
     * @param int    $page
     * @param int    $perPage
     * @return array{data: array, total: int, page: int, perPage: int, totalPages: int,
     *               availCounts: array{in_stock: int, low_stock: int, out_of_stock: int}}
     */
    public function getAllProducts(
        string $search       = '',
        string $availability = '',
        int    $page         = 1,
        int    $perPage      = 16
    ): array {
        $page    = max(1, $page);
        $perPage = min(max(1, $perPage), 60);
        $offset  = ($page - 1) * $perPage;

        $baseWhere  = ["p.is_active = 1", "p.deleted_at IS NULL", "p.product_status = 'active'"];
        $baseParams = [];

        if ($search !== '') {
            $like = '%' . $search . '%';
            $baseWhere[]         = "(p.name LIKE :sq1 OR p.sku LIKE :sq2 OR c.name LIKE :sq3)";
            $baseParams['sq1']   = $like;
            $baseParams['sq2']   = $like;
            $baseParams['sq3']   = $like;
        }

        $baseCondition = implode(' AND ', $baseWhere);

        // Availability HAVING condition
        $havingClause = match($availability) {
            'in_stock'     => 'HAVING agg_qty > ' . self::LOW_STOCK_QTY,
            'low_stock'    => 'HAVING agg_qty > 0 AND agg_qty <= ' . self::LOW_STOCK_QTY,
            'out_of_stock' => 'HAVING agg_qty <= 0',
            default        => '',   // show all
        };

        // Counts per availability bucket (no pagination, no availability filter)
        // INNER JOIN ensures only products that exist in at least one shop inventory are counted.
        // Expired inventory rows are excluded — they must not appear to customers.
        $countsSql = "
            SELECT
                SUM(CASE WHEN agg_qty > " . self::LOW_STOCK_QTY . " THEN 1 ELSE 0 END) AS in_stock,
                SUM(CASE WHEN agg_qty > 0 AND agg_qty <= " . self::LOW_STOCK_QTY . " THEN 1 ELSE 0 END) AS low_stock,
                SUM(CASE WHEN agg_qty <= 0 THEN 1 ELSE 0 END) AS out_of_stock
            FROM (
                SELECT SUM(si.available_quantity) AS agg_qty
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                INNER JOIN shop_inventory si
                       ON si.product_id = p.id
                      AND (si.expiry_date IS NULL OR si.expiry_date >= CURDATE())
                WHERE {$baseCondition}
                GROUP BY p.id
            ) sub
        ";
        $countsRow = $this->db->fetchOne($countsSql, $baseParams) ?? [];
        $availCounts = [
            'in_stock'     => (int)($countsRow['in_stock']     ?? 0),
            'low_stock'    => (int)($countsRow['low_stock']    ?? 0),
            'out_of_stock' => (int)($countsRow['out_of_stock'] ?? 0),
        ];

        // Total with availability filter — only products in at least one non-expired shop inventory
        $totalSql = "
            SELECT COUNT(*) AS c FROM (
                SELECT p.id, SUM(si.available_quantity) AS agg_qty
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                INNER JOIN shop_inventory si
                       ON si.product_id = p.id
                      AND (si.expiry_date IS NULL OR si.expiry_date >= CURDATE())
                WHERE {$baseCondition}
                GROUP BY p.id
                {$havingClause}
            ) sub
        ";
        $total = (int)($this->db->fetchOne($totalSql, $baseParams)['c'] ?? 0);

        // Data — only products that have been added to at least one non-expired shop inventory
        $dataSql = "
            SELECT
                p.id,
                p.name              AS product_name,
                p.sku               AS product_code,
                p.description,
                p.unit_price        AS selling_price,
                p.is_featured,
                p.is_perishable,
                p.primary_image,
                p.min_stock_level,
                c.id                AS category_id,
                c.name              AS category_name,
                u.name              AS unit_name,
                u.abbreviation      AS unit_abbreviation,
                SUM(si.available_quantity) AS available_quantity,
                0                   AS shop_min_stock_level,
                NULL                AS expiry_date
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN units_of_measure u ON u.id = p.unit_id
            INNER JOIN shop_inventory si
                   ON si.product_id = p.id
                  AND (si.expiry_date IS NULL OR si.expiry_date >= CURDATE())
            WHERE {$baseCondition}
            GROUP BY
                p.id, p.name, p.sku, p.description, p.unit_price,
                p.is_featured, p.is_perishable, p.primary_image, p.min_stock_level,
                c.id, c.name, u.name, u.abbreviation
            {$havingClause}
            ORDER BY p.is_featured DESC, available_quantity DESC, p.name ASC
            LIMIT :limit OFFSET :offset
        ";
        $dataParams = array_merge($baseParams, ['limit' => $perPage, 'offset' => $offset]);
        $data = $this->db->fetchAll($dataSql, $dataParams);

        foreach ($data as &$row) {
            $row['availability'] = $this->resolveAvailability(
                (float)$row['available_quantity'], 0, null
            );
        }
        unset($row);

        return [
            'data'        => $data,
            'total'       => $total,
            'page'        => $page,
            'perPage'     => $perPage,
            'totalPages'  => (int)ceil($total / max(1, $perPage)),
            'from'        => $total > 0 ? $offset + 1 : 0,
            'to'          => min($offset + $perPage, $total),
            'availCounts' => $availCounts,
        ];
    }

    // ──────────────────────────────────────────────────────
    // FULL SEARCH RESULTS PAGE (shop-aware)
    // ──────────────────────────────────────────────────────

    /**
     * Full search with pagination for the search results page.
     */
    public function searchProducts(
        string $query,
        ?int   $shopId  = null,
        int    $page    = 1,
        int    $perPage = 16
    ): array {
        if (trim($query) === '') {
            return ['data' => [], 'total' => 0, 'page' => 1, 'perPage' => $perPage, 'totalPages' => 0, 'from' => 0, 'to' => 0];
        }

        // Delegate to getProductsByShop with a search filter if shop is set,
        // otherwise do a shop-agnostic search
        if ($shopId !== null) {
            return $this->getProductsByShop($shopId, $page, $perPage, ['search' => $query]);
        }

        $page    = max(1, $page);
        $perPage = min(max(1, $perPage), 60);
        $offset  = ($page - 1) * $perPage;
        $like    = '%' . trim($query) . '%';

        $countSql = "
            SELECT COUNT(*) AS c
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.is_active = 1
              AND p.deleted_at IS NULL
              AND p.product_status = 'active'
              AND (p.name LIKE :q1 OR p.sku LIKE :q2 OR c.name LIKE :q3)
              AND (
                    SELECT COALESCE(SUM(si.available_quantity), 0)
                    FROM shop_inventory si
                    WHERE si.product_id = p.id
              ) > 0
        ";
        $total = (int)($this->db->fetchOne($countSql, ['q1' => $like, 'q2' => $like, 'q3' => $like])['c'] ?? 0);

        $sql = "
            SELECT
                p.id,
                p.name              AS product_name,
                p.sku               AS product_code,
                p.description,
                p.unit_price        AS selling_price,
                p.is_featured,
                p.primary_image,
                c.id                AS category_id,
                c.name              AS category_name,
                u.abbreviation      AS unit_abbreviation,
                COALESCE(
                    (SELECT SUM(si.available_quantity)
                     FROM shop_inventory si
                     WHERE si.product_id = p.id), 0
                ) AS available_quantity,
                0 AS shop_min_stock_level,
                NULL AS expiry_date
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN units_of_measure u ON u.id = p.unit_id
            WHERE p.is_active = 1
              AND p.deleted_at IS NULL
              AND p.product_status = 'active'
              AND (p.name LIKE :q1 OR p.sku LIKE :q2 OR c.name LIKE :q3)
              AND (
                    SELECT COALESCE(SUM(si.available_quantity), 0)
                    FROM shop_inventory si
                    WHERE si.product_id = p.id
              ) > 0
            ORDER BY
                CASE WHEN p.name LIKE :q4 THEN 0 ELSE 1 END,
                p.name ASC
            LIMIT :limit OFFSET :offset
        ";

        $data = $this->db->fetchAll($sql, [
            'q1'     => $like,
            'q2'     => $like,
            'q3'     => $like,
            'q4'     => trim($query) . '%',
            'limit'  => $perPage,
            'offset' => $offset,
        ]);

        foreach ($data as &$row) {
            $row['availability'] = $this->resolveAvailability(
                (float)$row['available_quantity'], 0, null
            );
        }
        unset($row);

        return [
            'data'       => $data,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int) ceil($total / max(1, $perPage)),
            'from'       => $total > 0 ? $offset + 1 : 0,
            'to'         => min($offset + $perPage, $total),
        ];
    }

    // ──────────────────────────────────────────────────────
    // CATEGORIES (for filter sidebar)
    // ──────────────────────────────────────────────────────

    /**
     * Get active categories that have products available at a shop.
     * If no shopId, returns all categories with active products.
     */
    public function getCategories(?int $shopId = null): array
    {
        if ($shopId !== null) {
            return $this->db->fetchAll(
                "SELECT c.id, c.name, COUNT(DISTINCT p.id) AS product_count
                 FROM categories c
                 INNER JOIN products p
                        ON p.category_id = c.id
                       AND p.is_active = 1
                       AND p.deleted_at IS NULL
                       AND p.product_status = 'active'
                 INNER JOIN shop_inventory si
                        ON si.product_id = p.id
                       AND si.shop_id    = :sid
                 WHERE c.is_active = 1
                   AND c.deleted_at IS NULL
                 GROUP BY c.id, c.name
                 HAVING product_count > 0
                 ORDER BY product_count DESC, c.name ASC",
                ['sid' => $shopId]
            );
        }

        return $this->db->fetchAll(
            "SELECT c.id, c.name, COUNT(DISTINCT p.id) AS product_count
             FROM categories c
             INNER JOIN products p
                    ON p.category_id = c.id
                   AND p.is_active = 1
                   AND p.deleted_at IS NULL
                   AND p.product_status = 'active'
             INNER JOIN shop_inventory si ON si.product_id = p.id
             WHERE c.is_active = 1
               AND c.deleted_at IS NULL
             GROUP BY c.id, c.name
             HAVING product_count > 0
             ORDER BY product_count DESC, c.name ASC"
        );
    }

    // ──────────────────────────────────────────────────────
    // RECOMMENDED PRODUCTS (cart page — shop-scoped)
    // ──────────────────────────────────────────────────────

    /**
     * Get recommended products for the cart page.
     * Returns featured or top-available products at the shop,
     * excluding products already in the cart.
     *
     * @param int   $shopId
     * @param int[] $excludeProductIds  Products already in cart
     * @param int   $limit
     * @return array
     */
    public function getRecommendedProducts(int $shopId, array $excludeProductIds = [], int $limit = 8): array
    {
        $excludeClause = '';
        $params        = ['shop_id' => $shopId, 'lim' => $limit];

        if (!empty($excludeProductIds)) {
            $placeholders   = implode(',', array_fill(0, count($excludeProductIds), '?'));
            $excludeClause  = "AND p.id NOT IN ({$placeholders})";
        }

        $sql = "
            SELECT
                p.id,
                p.name              AS product_name,
                p.unit_price        AS selling_price,
                p.is_featured,
                p.primary_image,
                c.name              AS category_name,
                u.abbreviation      AS unit_abbreviation,
                si.available_quantity
            FROM products p
            INNER JOIN shop_inventory si
                   ON si.product_id = p.id
                  AND si.shop_id    = :shop_id
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN units_of_measure u ON u.id = p.unit_id
            WHERE p.is_active = 1
              AND p.deleted_at IS NULL
              AND p.product_status = 'active'
              AND si.available_quantity > 0
              {$excludeClause}
            ORDER BY p.is_featured DESC, si.available_quantity DESC, p.name ASC
            LIMIT :lim
        ";

        if (!empty($excludeProductIds)) {
            // fetchAll doesn't support mixed named+positional, build inline
            $inList = implode(',', array_map('intval', $excludeProductIds));
            $sql2   = "
                SELECT
                    p.id,
                    p.name              AS product_name,
                    p.unit_price        AS selling_price,
                    p.is_featured,
                    p.primary_image,
                    c.name              AS category_name,
                    u.abbreviation      AS unit_abbreviation,
                    si.available_quantity
                FROM products p
                INNER JOIN shop_inventory si
                       ON si.product_id = p.id
                      AND si.shop_id    = :shop_id
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN units_of_measure u ON u.id = p.unit_id
                WHERE p.is_active = 1
                  AND p.deleted_at IS NULL
                  AND p.product_status = 'active'
                  AND si.available_quantity > 0
                  AND p.id NOT IN ({$inList})
                ORDER BY p.is_featured DESC, si.available_quantity DESC, p.name ASC
                LIMIT :lim
            ";
            return $this->db->fetchAll($sql2, ['shop_id' => $shopId, 'lim' => $limit]);
        }

        return $this->db->fetchAll($sql, $params);
    }

    // ──────────────────────────────────────────────────────
    // VALIDATE STOCK FOR ADD-TO-CART
    // ──────────────────────────────────────────────────────

    /**
     * Server-side stock validation before adding to cart.
     * Returns ['valid' => bool, 'available' => float, 'message' => string].
     */
    public function validateStock(int $shopId, int $productId, float $requestedQty): array
    {
        $inv = $this->db->fetchOne(
            "SELECT si.available_quantity,
                    p.name AS product_name,
                    p.is_active,
                    p.product_status,
                    p.deleted_at,
                    u.abbreviation AS unit_abbr
             FROM shop_inventory si
             JOIN products p ON p.id = si.product_id
             LEFT JOIN units_of_measure u ON u.id = p.unit_id
             WHERE si.shop_id = :sid AND si.product_id = :pid
             LIMIT 1",
            ['sid' => $shopId, 'pid' => $productId]
        );

        if (!$inv) {
            return ['valid' => false, 'available' => 0, 'message' => 'Product is not available at this shop.'];
        }

        if (!$inv['is_active'] || $inv['product_status'] !== 'active' || $inv['deleted_at']) {
            return ['valid' => false, 'available' => 0, 'message' => 'Product is not available.'];
        }

        $available = (float)$inv['available_quantity'];

        if ($requestedQty > $available) {
            $unit = $inv['unit_abbr'] ?? 'units';
            return [
                'valid'     => false,
                'available' => $available,
                'message'   => "Only {$available} {$unit} available at this shop.",
            ];
        }

        return ['valid' => true, 'available' => $available, 'message' => 'OK'];
    }

    // ──────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────

    /**
     * Build WHERE clause for product listing queries.
     * Note: shop_id binding is done outside this method.
     */
    private function buildProductFilters(int $shopId, array $filters): array
    {
        $where  = [
            'p.is_active = 1',
            "p.deleted_at IS NULL",
            "p.product_status = 'active'",
            // Hard block: never show expired inventory to customers.
            '(si.expiry_date IS NULL OR si.expiry_date >= CURDATE())',
        ];
        $params = [];

        if (!empty($filters['category_id'])) {
            $where[]              = 'p.category_id = :cat_id';
            $params['cat_id']     = (int)$filters['category_id'];
        }

        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $where[]               = "(p.name LIKE :sq1 OR p.sku LIKE :sq2 OR c.name LIKE :sq3)";
            $params['sq1']         = $like;
            $params['sq2']         = $like;
            $params['sq3']         = $like;
        }

        // Availability filter
        $avail = $filters['availability'] ?? '';
        if ($avail === 'in_stock') {
            $where[] = 'si.available_quantity > ' . self::LOW_STOCK_QTY;
        } elseif ($avail === 'low_stock') {
            $where[] = 'si.available_quantity > 0 AND si.available_quantity <= ' . self::LOW_STOCK_QTY;
        } elseif ($avail === 'out_of_stock') {
            $where[] = 'si.available_quantity <= 0';
        } else {
            // Default: only show products with stock unless out_of_stock explicitly requested
            if (!isset($filters['show_out_of_stock']) || !$filters['show_out_of_stock']) {
                $where[] = 'si.available_quantity > 0';
            }
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * Resolve ORDER BY clause from sort parameter.
     */
    private function resolveOrderBy(string $sort): string
    {
        return match($sort) {
            'name_asc'    => 'ORDER BY p.name ASC',
            'name_desc'   => 'ORDER BY p.name DESC',
            'price_asc'   => 'ORDER BY p.unit_price ASC, p.name ASC',
            'price_desc'  => 'ORDER BY p.unit_price DESC, p.name ASC',
            'availability'=> 'ORDER BY si.available_quantity DESC, p.name ASC',
            default       => 'ORDER BY p.is_featured DESC, si.available_quantity DESC, p.name ASC',
        };
    }

    /**
     * Resolve availability status badge info.
     * Returns ['status' => 'available|low|unavailable', 'label' => '...', 'qty_label' => '...']
     */
    public function resolveAvailability(float $qty, float $minLevel, ?string $expiryDate): array
    {
        if ($expiryDate && strtotime($expiryDate) < time()) {
            return ['status' => 'unavailable', 'label' => 'Expired', 'qty_label' => ''];
        }

        if ($qty <= 0) {
            return ['status' => 'unavailable', 'label' => 'Out of stock', 'qty_label' => ''];
        }

        if ($qty <= self::LOW_STOCK_QTY || ($minLevel > 0 && $qty <= $minLevel)) {
            return [
                'status'    => 'low',
                'label'     => 'Only ' . $this->formatQty($qty) . ' left',
                'qty_label' => $this->formatQty($qty),
            ];
        }

        return [
            'status'    => 'available',
            'label'     => $this->formatQty($qty) . ' available',
            'qty_label' => $this->formatQty($qty),
        ];
    }

    /**
     * Format quantity for display — trim trailing zeros.
     */
    public function formatQty(float $qty): string
    {
        if ($qty == (int)$qty) {
            return (string)(int)$qty;
        }
        return rtrim(number_format($qty, 3), '0');
    }
}
