<?php
/* =========================================================
   MRS MILL@ — ADD TO CART (session-based)
   File: ./ajax/cart-add.php
   Accepts: product_id, variant_id, qty
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$productId = (int)($_POST['product_id'] ?? 0);
$variantId = (int)($_POST['variant_id'] ?? 0);
$qty       = max(1, (int)($_POST['qty'] ?? 1));

if ($productId <= 0 || $variantId <= 0) {
    jsonResponse(false, 'Invalid product or variant.');
}

try {

    $stmt = $pdo->prepare(
        "SELECT p.id, p.product_code, p.product_name, p.product_image,
                v.id AS variant_id, v.quantity, v.quantity_unit, v.quantity_name, v.price
         FROM products p
         INNER JOIN product_variants v ON v.product_code = p.product_code
         WHERE p.id = ? AND v.id = ? AND p.status = 1 AND v.status = 1
         LIMIT 1"
    );
    $stmt->execute([$productId, $variantId]);
    $row = $stmt->fetch();

    if (!$row) jsonResponse(false, 'Product or variant not found.');

    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $key = 'p' . $row['id'] . '_v' . $row['variant_id'];

    if (isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key]['qty'] += $qty;
    } else {
        $_SESSION['cart'][$key] = [
            'key'         => $key,
            'product_id'  => (int)$row['id'],
            'code'        => $row['product_code'],
            'name'        => $row['product_name'],
            'image'       => !empty($row['product_image']) ? ADMIN_URL . $row['product_image'] : '',
            'variant_id'  => (int)$row['variant_id'],
            'variant_name' => $row['quantity_name'],
            'variant_qty' => $row['quantity'] . ' ' . $row['quantity_unit'],
            'price'       => (float)$row['price'],
            'qty'         => $qty
        ];
    }

    $count = 0;
    $total = 0;
    foreach ($_SESSION['cart'] as $c) {
        $q     = (int)($c['qty'] ?? 0);
        $count += $q;
        $total += ((float)($c['price'] ?? 0)) * $q;
    }

    jsonResponse(true, 'Added to cart.', [
        'cart_count' => $count,
        'cart_total' => $total,
        'item'       => $_SESSION['cart'][$key]
    ]);
} catch (PDOException $e) {
    jsonResponse(false, 'Server error. Please try again.');
}
