<?php
/* =========================================================
   MRS MILL@ — UPDATE CART QTY / REMOVE
   File: ./ajax/cart-update.php
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Method not allowed.');

$productId = (int)($_POST['product_id'] ?? 0);
$variantId = (int)($_POST['variant_id'] ?? 0);
$action    = trim($_POST['action'] ?? '');

if ($productId <= 0 || $variantId <= 0) jsonResponse(false, 'Invalid product or variant.');
if (!in_array($action, ['inc', 'dec', 'remove'], true)) jsonResponse(false, 'Invalid action.');

$customerId = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0
    ? (int)$_SESSION['customer_id']
    : 0;

try {
    /* ---------- LOGGED IN → DB ---------- */
    if ($customerId > 0) {
        $chk = $pdo->prepare(
            "SELECT id, qty FROM customer_cart
             WHERE customer_id = ? AND product_id = ? AND variant_id = ?
             LIMIT 1"
        );
        $chk->execute([$customerId, $productId, $variantId]);
        $row = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$row) jsonResponse(false, 'Item not in cart.');

        if ($action === 'remove') {
            $pdo->prepare("DELETE FROM customer_cart WHERE id = ?")->execute([$row['id']]);
        } elseif ($action === 'inc') {
            $pdo->prepare("UPDATE customer_cart SET qty = qty + 1, updated_at = NOW() WHERE id = ?")
                ->execute([$row['id']]);
        } elseif ($action === 'dec') {
            $newQty = max(1, (int)$row['qty'] - 1);
            $pdo->prepare("UPDATE customer_cart SET qty = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$newQty, $row['id']]);
        }

        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(qty),0) AS c,
                    COALESCE(SUM(price * qty),0) AS t
             FROM customer_cart WHERE customer_id = ?"
        );
        $stmt->execute([$customerId]);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);

        jsonResponse(true, 'Updated.', [
            'cart_count' => (int)$totals['c'],
            'cart_total' => (float)$totals['t']
        ]);
    }

    /* ---------- GUEST → session ---------- */
    if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        jsonResponse(false, 'Cart is empty.');
    }

    $key = 'p' . $productId . '_v' . $variantId;

    /* Fallback: find by product + variant if key doesn't match */
    if (!isset($_SESSION['cart'][$key])) {
        foreach ($_SESSION['cart'] as $k => $c) {
            if ((int)($c['product_id'] ?? 0) === $productId
                && (int)($c['variant_id'] ?? 0) === $variantId) {
                $key = $k;
                break;
            }
        }
    }

    if (!isset($_SESSION['cart'][$key])) jsonResponse(false, 'Item not in cart.');

    if ($action === 'remove') {
        unset($_SESSION['cart'][$key]);
    } elseif ($action === 'inc') {
        $_SESSION['cart'][$key]['qty'] = (int)$_SESSION['cart'][$key]['qty'] + 1;
    } elseif ($action === 'dec') {
        $_SESSION['cart'][$key]['qty'] = max(1, (int)$_SESSION['cart'][$key]['qty'] - 1);
    }

    $count = 0;
    $total = 0;
    foreach ($_SESSION['cart'] as $c) {
        $q = (int)($c['qty'] ?? 0);
        $count += $q;
        $total += ((float)($c['price'] ?? 0)) * $q;
    }

    jsonResponse(true, 'Updated.', [
        'cart_count' => $count,
        'cart_total' => $total
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Server error.');
}