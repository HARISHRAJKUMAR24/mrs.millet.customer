<?php
/* =========================================================
   MRS MILL@ — GET PRODUCT VARIANTS (per menu)
   File: ./ajax/get-product-variants.php
   Params: id, menu_code (optional)
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method not allowed.');
}

$productId = (int)($_GET['id'] ?? 0);
$menuCode  = trim($_GET['menu_code'] ?? '');
if ($productId <= 0) jsonResponse(false, 'Invalid product ID.');

$customerRowId = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0
    ? (int)$_SESSION['customer_id'] : 0;

try {
    $stmt = $pdo->prepare(
        "SELECT id, product_code, product_name, product_image
         FROM products WHERE id = ? AND status = 1 LIMIT 1"
    );
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) jsonResponse(false, 'Product not found.');

    /* If menu_code given, restrict variants to that menu */
    if ($menuCode !== '') {
        $vStmt = $pdo->prepare(
            "SELECT v.id, v.quantity, v.quantity_unit, v.quantity_name, v.price
             FROM product_variants v
             INNER JOIN menu_products mp ON mp.variant_id = v.id
             WHERE v.product_code = ?
               AND v.status = 1
               AND mp.menu_code = ?
             ORDER BY v.quantity ASC"
        );
        $vStmt->execute([$product['product_code'], $menuCode]);
    } else {
        $vStmt = $pdo->prepare(
            "SELECT id, quantity, quantity_unit, quantity_name, price
             FROM product_variants
             WHERE product_code = ? AND status = 1
             ORDER BY quantity ASC"
        );
        $vStmt->execute([$product['product_code']]);
    }
    $variants = $vStmt->fetchAll(PDO::FETCH_ASSOC);

    /* cart quantities */
    $cartQtyByVariant = [];
    if ($customerRowId > 0) {
        if ($menuCode !== '') {
            $cq = $pdo->prepare(
                "SELECT variant_id, SUM(qty) AS q
                 FROM customer_cart
                 WHERE customer_id = ? AND product_id = ? AND menu_code = ?
                 GROUP BY variant_id"
            );
            $cq->execute([$customerRowId, $productId, $menuCode]);
        } else {
            $cq = $pdo->prepare(
                "SELECT variant_id, SUM(qty) AS q
                 FROM customer_cart
                 WHERE customer_id = ? AND product_id = ?
                 GROUP BY variant_id"
            );
            $cq->execute([$customerRowId, $productId]);
        }
        while ($row = $cq->fetch(PDO::FETCH_ASSOC)) {
            $cartQtyByVariant[(int)$row['variant_id']] = (int)$row['q'];
        }
    } else {
        if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $g) {
                if ((int)($g['product_id'] ?? 0) !== $productId) continue;
                if ($menuCode !== '' && (string)($g['menu_code'] ?? '') !== $menuCode) continue;
                $vid = (int)($g['variant_id'] ?? 0);
                if ($vid > 0) {
                    $cartQtyByVariant[$vid] = ($cartQtyByVariant[$vid] ?? 0) + (int)($g['qty'] ?? 0);
                }
            }
        }
    }

    foreach ($variants as &$v) {
        $vid = (int)$v['id'];
        $v['cart_qty'] = $cartQtyByVariant[$vid] ?? 0;
        $v['in_cart']  = $v['cart_qty'] > 0;
        $v['price']    = (float)$v['price'];
    }
    unset($v);

    jsonResponse(true, 'OK', [
        'id'            => (int)$product['id'],
        'product_code'  => $product['product_code'],
        'product_name'  => $product['product_name'],
        'product_image' => $product['product_image'],
        'menu_code'     => $menuCode,
        'variants'      => $variants
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Server error: ' . $e->getMessage());
}