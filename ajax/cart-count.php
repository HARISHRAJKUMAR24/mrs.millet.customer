<?php
/* =========================================================
   MRS MILL@ — GET CART COUNT
   File: ./ajax/cart-count.php
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

$count = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $c) $count += (int)($c['qty'] ?? 0);
}

jsonResponse(true, 'OK', ['cart_count' => $count]);