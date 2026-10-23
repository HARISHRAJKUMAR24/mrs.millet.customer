<?php
/* =========================================================
   MRS MILL@ — CUSTOMER HELPERS
   File: ./config/function.php
   ========================================================= */

/* ---------- SETTINGS (id=1) ---------- */
if (!function_exists('getSettings')) {
    function getSettings($pdo)
    {
        try {
            $stmt = $pdo->query("SELECT * FROM settings WHERE id = 1 LIMIT 1");
            $row  = $stmt->fetch();
            if (!$row) {
                $pdo->exec("INSERT INTO settings (id) VALUES (1)");
                $stmt = $pdo->query("SELECT * FROM settings WHERE id = 1 LIMIT 1");
                $row  = $stmt->fetch();
            }
            return $row ?: [];
        } catch (PDOException $e) {
            return [];
        }
    }
}

/* ---------- CATEGORIES ---------- */
if (!function_exists('getCategories')) {
    function getCategories($pdo, $limit = 20)
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, category_name, category_slug, category_image
                 FROM categories WHERE status = 1
                 ORDER BY id ASC LIMIT ?"
            );
            $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) { return []; }
    }
}

/* ---------- PRODUCTS ---------- */
if (!function_exists('getProducts')) {
    function getProducts($pdo, $limit = 8, $offset = 0)
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT p.id, p.product_code, p.product_name, p.product_image,
                        p.category_id, c.category_name,
                        (SELECT MIN(v.price) FROM product_variants v
                         WHERE v.product_code = p.product_code AND v.status = 1) AS min_price
                 FROM products p
                 LEFT JOIN categories c ON c.id = p.category_id
                 WHERE p.status = 1
                 ORDER BY p.id DESC
                 LIMIT ? OFFSET ?"
            );
            $stmt->bindValue(1, (int)$limit,  PDO::PARAM_INT);
            $stmt->bindValue(2, (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) { return []; }
    }
}

/* ---------- POPULAR PRODUCTS ---------- */
if (!function_exists('getPopularProducts')) {
    function getPopularProducts($pdo, $limit = 4) {
        return getProducts($pdo, $limit, 0);
    }
}

/* ---------- ACTIVE MENUS (PLURAL) ---------- */
if (!function_exists('getActiveMenus')) {
    /**
     * Returns ALL currently-active menus ordered by start_at ASC.
     */
    function getActiveMenus($pdo)
    {
        try {
            $stmt = $pdo->query(
                "SELECT id, menu_code, menu_name, start_at, end_at
                 FROM menus
                 WHERE status = 1
                   AND start_at <= NOW()
                   AND end_at   >= NOW()
                 ORDER BY start_at ASC"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) { return []; }
    }
}

/* ---------- ACTIVE MENU (SINGULAR) ---------- */
if (!function_exists('getActiveMenu')) {
    function getActiveMenu($pdo)
    {
        $all = getActiveMenus($pdo);
        return $all ? $all[0] : null;
    }
}

/* ---------- MENU PRODUCTS (per menu) ---------- */
if (!function_exists('getMenuProducts')) {
    /**
     * Returns flat rows: one row per (product, variant) pair for a given menu.
     * Caller must group by product_id if needed.
     */
    function getMenuProducts($pdo, $limit = 100, $menuCode = null)
    {
        try {
            if ($menuCode === null) {
                $menu = getActiveMenu($pdo);
                if (!$menu) return [];
                $menuCode = $menu['menu_code'];
            }

            $stmt = $pdo->prepare(
                "SELECT
                        p.id, p.product_code, p.product_name, p.product_image,
                        p.category_id, c.category_name, c.category_slug, c.category_image,
                        mp.variant_id, mp.stock_unlimited, mp.stock_count,
                        v.quantity, v.quantity_unit, v.quantity_name, v.price,
                        v.container_enabled, v.container_price
                 FROM menu_products mp
                 INNER JOIN products p         ON p.product_code = mp.product_code
                 INNER JOIN product_variants v ON v.id = mp.variant_id
                 LEFT  JOIN categories c       ON c.id = p.category_id
                 WHERE mp.menu_code = ?
                   AND p.status = 1
                   AND v.status = 1
                 ORDER BY p.id ASC, v.quantity ASC
                 LIMIT ?"
            );
            $stmt->bindValue(1, $menuCode, PDO::PARAM_STR);
            $stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { return []; }
    }
}

/* ---------- MENU CATEGORIES (per menu) ---------- */
if (!function_exists('getMenuCategories')) {
    function getMenuCategories($pdo, $limit = 50, $menuCode = null)
    {
        try {
            if ($menuCode === null) {
                $menu = getActiveMenu($pdo);
                if (!$menu) return [];
                $menuCode = $menu['menu_code'];
            }

            $stmt = $pdo->prepare(
                "SELECT DISTINCT c.id, c.category_name, c.category_slug, c.category_image
                 FROM menu_products mp
                 INNER JOIN products p   ON p.product_code = mp.product_code
                 INNER JOIN categories c ON c.id = p.category_id
                 WHERE mp.menu_code = ?
                   AND p.status = 1
                   AND c.status = 1
                 ORDER BY c.id ASC
                 LIMIT ?"
            );
            $stmt->bindValue(1, $menuCode, PDO::PARAM_STR);
            $stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { return []; }
    }
}

/* ---------- GROUP VARIANTS PER PRODUCT ---------- */
if (!function_exists('groupMenuProductsByProduct')) {
    function groupMenuProductsByProduct($rows)
    {
        $byProduct = [];

        foreach ($rows as $r) {
            $pid = (int)$r['id'];

            if (!isset($byProduct[$pid])) {
                $byProduct[$pid] = [
                    'id'            => $pid,
                    'product_code'  => $r['product_code'],
                    'product_name'  => $r['product_name'],
                    'product_image' => $r['product_image'],
                    'category_name' => $r['category_name'] ?? '',
                    'category_slug' => $r['category_slug'] ?? '',
                    'min_price'     => (float)$r['price'],
                    'variants'      => []
                ];
            }

            $byProduct[$pid]['variants'][] = [
                'variant_id'        => (int)$r['variant_id'],
                'quantity'          => $r['quantity'],
                'quantity_unit'     => $r['quantity_unit'],
                'quantity_name'     => $r['quantity_name'],
                'price'             => (float)$r['price'],
                'container_enabled' => (int)$r['container_enabled'],
                'container_price'   => (float)$r['container_price'],
                'stock_unlimited'   => (int)$r['stock_unlimited'],
                'stock_count'       => (int)$r['stock_count']
            ];

            if ((float)$r['price'] < $byProduct[$pid]['min_price']) {
                $byProduct[$pid]['min_price'] = (float)$r['price'];
            }
        }

        return array_values($byProduct);
    }
}

/* ---------- IMAGE HELPERS ---------- */
if (!function_exists('productImageUrl')) {
    function productImageUrl($img) {
        return empty($img) ? '' : ADMIN_URL . $img;
    }
}
if (!function_exists('categoryImageUrl')) {
    function categoryImageUrl($img) {
        return empty($img) ? '' : ADMIN_URL . $img;
    }
}

/* ---------- JSON RESPONSE ---------- */
if (!function_exists('jsonResponse')) {
    function jsonResponse(bool $success, string $message = '', $data = null): void
    {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        $payload = ['success' => $success, 'message' => $message];
        if ($data !== null) $payload['data'] = $data;
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/* ---------- CLEAN EXPIRED MENU CART ITEMS ---------- */
if (!function_exists('cleanExpiredMenuCart')) {
    function cleanExpiredMenuCart($pdo)
    {
        $customerId = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0
            ? (int)$_SESSION['customer_id'] : 0;

        /* Get all active menu codes */
        $activeMenus = getActiveMenus($pdo);
        $activeCodes = array_column($activeMenus, 'menu_code');

        $removed = 0;

        try {
            if ($customerId > 0) {
                if (empty($activeCodes)) {
                    $stmt = $pdo->prepare("DELETE FROM customer_cart WHERE customer_id = ?");
                    $stmt->execute([$customerId]);
                    $removed = $stmt->rowCount();
                } else {
                    $placeholders = implode(',', array_fill(0, count($activeCodes), '?'));
                    $sql = "DELETE FROM customer_cart
                            WHERE customer_id = ?
                              AND (menu_code IS NULL OR menu_code NOT IN ($placeholders))";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(array_merge([$customerId], $activeCodes));
                    $removed = $stmt->rowCount();
                }
            } else {
                if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
                    foreach ($_SESSION['cart'] as $key => $item) {
                        $itemMenu = $item['menu_code'] ?? null;
                        if (empty($activeCodes) || $itemMenu === null || !in_array($itemMenu, $activeCodes, true)) {
                            unset($_SESSION['cart'][$key]);
                            $removed++;
                        }
                    }
                    /* Rebuild guest cart keys to include menu_code so they don't collide */
                    $rebuilt = [];
                    foreach ($_SESSION['cart'] as $it) {
                        $k = 'p' . (int)($it['product_id'] ?? 0) . '_v' . (int)($it['variant_id'] ?? 0) . '_' . ($it['menu_code'] ?? '');
                        $rebuilt[$k] = $it;
                    }
                    $_SESSION['cart'] = $rebuilt;
                }
            }
        } catch (PDOException $e) { }

        return $removed;
    }
}

cleanExpiredMenuCart($pdo);