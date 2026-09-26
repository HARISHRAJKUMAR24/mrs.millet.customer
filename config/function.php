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
                 FROM categories
                 WHERE status = 1
                 ORDER BY id ASC
                 LIMIT ?"
            );
            $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
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
        } catch (PDOException $e) {
            return [];
        }
    }
}

/* ---------- POPULAR PRODUCTS ---------- */
if (!function_exists('getPopularProducts')) {
    function getPopularProducts($pdo, $limit = 4)
    {
        return getProducts($pdo, $limit, 0);
    }
}

/* ---------- ACTIVE MENU ---------- */
if (!function_exists('getActiveMenu')) {
    /**
     * Returns the currently active menu row (or null).
     */
    function getActiveMenu($pdo)
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, menu_code, menu_name, start_at, end_at
                 FROM menus
                 WHERE status = 1
                   AND start_at <= NOW()
                   AND end_at   >= NOW()
                 ORDER BY start_at DESC
                 LIMIT 1"
            );
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }
}

/* ---------- MENU PRODUCTS (active menu only) ---------- */
if (!function_exists('getMenuProducts')) {
    function getMenuProducts($pdo, $limit = 100)
    {
        try {
            $menu = getActiveMenu($pdo);
            if (!$menu) return [];

            $stmt = $pdo->prepare(
                "SELECT DISTINCT
                        p.id,
                        p.product_code,
                        p.product_name,
                        p.product_image,
                        p.category_id,
                        c.category_name,
                        c.category_slug,
                        c.category_image,
                        (SELECT MIN(v.price) FROM product_variants v
                         WHERE v.product_code = p.product_code AND v.status = 1) AS min_price
                 FROM menu_products mp
                 INNER JOIN products p       ON p.product_code = mp.product_code
                 LEFT  JOIN categories c     ON c.id = p.category_id
                 WHERE mp.menu_code = ?
                   AND p.status = 1
                 ORDER BY p.id ASC
                 LIMIT ?"
            );
            $stmt->bindValue(1, $menu['menu_code'], PDO::PARAM_STR);
            $stmt->bindValue(2, (int)$limit,       PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
}

/* ---------- MENU CATEGORIES (categories that exist in active menu) ---------- */
if (!function_exists('getMenuCategories')) {
    function getMenuCategories($pdo, $limit = 20)
    {
        try {
            $menu = getActiveMenu($pdo);
            if (!$menu) return [];

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
            $stmt->bindValue(1, $menu['menu_code'], PDO::PARAM_STR);
            $stmt->bindValue(2, (int)$limit,       PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
}

/* ---------- IMAGE URL HELPERS ---------- */
if (!function_exists('productImageUrl')) {
    function productImageUrl($img)
    {
        if (empty($img)) return '';
        return ADMIN_URL . $img;
    }
}

if (!function_exists('categoryImageUrl')) {
    function categoryImageUrl($img)
    {
        if (empty($img)) return '';
        return ADMIN_URL . $img;
    }
}

/* ---------- JSON RESPONSE ---------- */
if (!function_exists('jsonResponse')) {
    function jsonResponse(bool $success, string $message = '', $data = null): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $payload = [
            'success' => $success,
            'message' => $message
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}