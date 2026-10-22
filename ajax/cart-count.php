<?php
/* =========================================================
   MRS MILL@ — CART COUNT + TOTAL
   File: ./ajax/cart-count.php
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

$customerRowId = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0
    ? (int)$_SESSION['customer_id']
    : 0;
$customerCode = (string)($_SESSION['customer_code'] ?? '');

$count = 0;
$total = 0.0;

try {
    if ($customerRowId > 0) {

        /* Fill missing code */
        if ($customerCode === '') {
            try {
                $cs = $pdo->prepare("SELECT customer_id FROM customers WHERE id = ? LIMIT 1");
                $cs->execute([$customerRowId]);
                $customerCode = (string)$cs->fetchColumn();
                $_SESSION['customer_code'] = $customerCode;
            } catch (PDOException $e) {}
        }

        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(qty),0) AS c,
                    COALESCE(SUM(price * qty),0) AS t
             FROM customer_cart WHERE customer_id = ?"
        );
        $stmt->execute([$customerRowId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = (int)($row['c'] ?? 0);
        $total = (float)($row['t'] ?? 0);
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
    'cart_count'    => $count,
    'cart_total'    => $total,
    'customer_id'   => $customerRowId,
    'customer_code' => $customerCode,
    'source'        => $customerRowId > 0 ? 'db' : 'session'
]);