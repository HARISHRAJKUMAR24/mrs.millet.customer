<?php
/* =========================================================
   MRS MILL@ — GET CART COUNT + TOTAL
   File: ./ajax/cart-count.php
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

$customerId = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0
    ? (int)$_SESSION['customer_id']
    : 0;

$count = 0;
$total = 0.0;

try {
    if ($customerId > 0) {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(qty),0) AS c,
                    COALESCE(SUM(price * qty),0) AS t
             FROM customer_cart WHERE customer_id = ?"
        );
        $stmt->execute([$customerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = (int)$row['c'];
        $total = (float)$row['t'];
    } else {
        if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $c) {
                $q = (int)($c['qty'] ?? 0);
                $count += $q;
                $total += ((float)($c['price'] ?? 0)) * $q;
            }
        }
    }
} catch (PDOException $e) {
    $count = 0;
    $total = 0.0;
}

jsonResponse(true, 'OK', [
    'cart_count' => $count,
    'cart_total' => $total,
    'source'     => $customerId > 0 ? 'db' : 'session'
]);