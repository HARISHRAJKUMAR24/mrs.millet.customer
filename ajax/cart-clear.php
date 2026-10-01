<?php
/* =========================================================
   MRS MILL@ — CLEAR CART
   File: ./ajax/cart-clear.php
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

$customerId = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0
    ? (int)$_SESSION['customer_id']
    : 0;

try {
    if ($customerId > 0) {
        $pdo->prepare("DELETE FROM customer_cart WHERE customer_id = ?")
            ->execute([$customerId]);
    }
    $_SESSION['cart'] = [];
    jsonResponse(true, 'Cart cleared.');
} catch (PDOException $e) {
    jsonResponse(false, 'Failed to clear cart.');
}