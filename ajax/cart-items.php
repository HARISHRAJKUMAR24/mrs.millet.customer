<?php
/* =========================================================
   MRS MILL@ — CART ITEMS
   File: ./ajax/cart-items.php
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

$customerRowId = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0
    ? (int)$_SESSION['customer_id']
    : 0;
$customerCode = (string)($_SESSION['customer_code'] ?? '');

$items = [];
$count = 0;
$total = 0.0;

try {
    if ($customerRowId > 0) {

        if ($customerCode === '') {
            try {
                $cs = $pdo->prepare("SELECT customer_id FROM customers WHERE id = ? LIMIT 1");
                $cs->execute([$customerRowId]);
                $customerCode = (string)$cs->fetchColumn();
                $_SESSION['customer_code'] = $customerCode;
            } catch (PDOException $e) {}
        }

        $stmt = $pdo->prepare(
            "SELECT id AS cart_id, product_id, product_code AS code, product_name AS name,
                    product_image AS image, variant_id, variant_name, variant_qty,
                    price, qty
             FROM customer_cart
             WHERE customer_id = ?
             ORDER BY id DESC"
        );
        $stmt->execute([$customerRowId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $c) {
                $items[] = [
                    'cart_id'      => $c['key'] ?? '',
                    'product_id'   => (int)($c['product_id'] ?? 0),
                    'code'         => $c['code'] ?? '',
                    'name'         => $c['name'] ?? '',
                    'image'        => $c['image'] ?? '',
                    'variant_id'   => (int)($c['variant_id'] ?? 0),
                    'variant_name' => $c['variant_name'] ?? '',
                    'variant_qty'  => $c['variant_qty'] ?? '',
                    'price'        => (float)($c['price'] ?? 0),
                    'qty'          => (int)($c['qty'] ?? 0)
                ];
            }
        }
    }

    foreach ($items as $it) {
        $q      = (int)($it['qty'] ?? 0);
        $count += $q;
        $total += ((float)($it['price'] ?? 0)) * $q;
    }

    jsonResponse(true, 'OK', [
        'items'         => $items,
        'cart_count'    => $count,
        'cart_total'    => $total,
        'customer_id'   => $customerRowId,
        'customer_code' => $customerCode,
        'source'        => $customerRowId > 0 ? 'db' : 'session'
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load cart: ' . $e->getMessage());
}