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

/* ---------- POPULAR PRODUCTS (top 8 by latest) ---------- */
if (!function_exists('getPopularProducts')) {
    function getPopularProducts($pdo, $limit = 4)
    {
        return getProducts($pdo, $limit, 0);
    }
}

/* ---------- ACTIVE MENU PRODUCTS ---------- */
if (!function_exists('getMenuProducts')) {
    function getMenuProducts($pdo, $limit = 4)
    {
        try {
            $menuStmt = $pdo->prepare(
                "SELECT menu_code FROM menus
                 WHERE status = 1
                   AND NOW() BETWEEN start_at AND end_at
                 ORDER BY id DESC LIMIT 1"
            );
            $menuStmt->execute();
            $menu = $menuStmt->fetch();

            if (!$menu) return [];

            $stmt = $pdo->prepare(
                "SELECT DISTINCT p.id, p.product_code, p.product_name, p.product_image,
                        p.category_id, c.category_name,
                        (SELECT MIN(v.price) FROM product_variants v
                         WHERE v.product_code = p.product_code AND v.status = 1) AS min_price
                 FROM menu_products mp
                 INNER JOIN products p ON p.product_code = mp.product_code
                 LEFT JOIN categories c ON c.id = p.category_id
                 WHERE mp.menu_code = ? AND p.status = 1
                 ORDER BY p.id DESC
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

/* ---------- IMAGE URL HELPER ---------- */
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
