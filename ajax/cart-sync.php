<?php
/* =========================================================
   MRS MILL@ — CART SYNC
   File: ./ajax/cart-sync.php
   Params: product_id, menu_code, items (JSON)
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$productId = (int)($_POST['product_id'] ?? 0);
$menuCode  = trim($_POST['menu_code'] ?? '');
$itemsRaw  = $_POST['items'] ?? '[]';
$items     = json_decode($itemsRaw, true);

if ($productId <= 0 || !is_array($items)) {
    jsonResponse(false, 'Invalid request.');
}

$customerRowId = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0
    ? (int)$_SESSION['customer_id'] : 0;
$customerCode  = (string)($_SESSION['customer_code'] ?? '');

try {
    $stmt = $pdo->prepare(
        "SELECT id, product_code, product_name, product_image
         FROM products WHERE id = ? AND status = 1 LIMIT 1"
    );
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) jsonResponse(false, 'Product not found.');

    $vStmt = $pdo->prepare(
        "SELECT id, quantity, quantity_unit, quantity_name, price
         FROM product_variants WHERE product_code = ? AND status = 1"
    );
    $vStmt->execute([$product['product_code']]);
    $variantRows = $vStmt->fetchAll(PDO::FETCH_ASSOC);
    $variantById = [];
    foreach ($variantRows as $vr) $variantById[(int)$vr['id']] = $vr;

    /* fallback to active menu if menu_code not provided */
    if ($menuCode === '') {
        $activeMenu = getActiveMenu($pdo);
        $menuCode   = $activeMenu ? $activeMenu['menu_code'] : null;
    }

    $imageUrl = !empty($product['product_image']) ? ADMIN_URL . $product['product_image'] : '';

    if ($customerRowId > 0) {
        if ($customerCode === '') {
            try {
                $cs = $pdo->prepare("SELECT customer_id FROM customers WHERE id = ? LIMIT 1");
                $cs->execute([$customerRowId]);
                $customerCode = (string)$cs->fetchColumn();
                $_SESSION['customer_code'] = $customerCode;
            } catch (PDOException $e) { $customerCode = ''; }
        }

        foreach ($items as $it) {
            $vid = (int)($it['variant_id'] ?? 0);
            $qty = max(0, (int)($it['qty'] ?? 0));
            if ($vid <= 0 || !isset($variantById[$vid])) continue;
            $vr = $variantById[$vid];

            if ($qty > 0) {
                $chk = $pdo->prepare(
                    "SELECT id FROM customer_cart
                     WHERE customer_id = ? AND product_id = ? AND variant_id = ? AND menu_code = ?
                     LIMIT 1"
                );
                $chk->execute([$customerRowId, $productId, $vid, $menuCode]);
                $existingId = $chk->fetchColumn();

                if ($existingId) {
                    $pdo->prepare(
                        "UPDATE customer_cart
                         SET qty = ?, updated_at = NOW()
                         WHERE id = ?"
                    )->execute([$qty, $existingId]);
                } else {
                    $pdo->prepare(
                        "INSERT INTO customer_cart
                            (customer_id, menu_code, product_id, product_code, product_name, product_image,
                             variant_id, variant_name, variant_qty, price, qty)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    )->execute([
                        $customerRowId, $menuCode,
                        (int)$product['id'], $product['product_code'],
                        $product['product_name'], $imageUrl,
                        $vid, $vr['quantity_name'],
                        $vr['quantity'] . ' ' . $vr['quantity_unit'],
                        (float)$vr['price'], $qty
                    ]);
                }
            } else {
                $pdo->prepare(
                    "DELETE FROM customer_cart
                     WHERE customer_id = ? AND product_id = ? AND variant_id = ? AND menu_code = ?"
                )->execute([$customerRowId, $productId, $vid, $menuCode]);
            }
        }

        $tot = $pdo->prepare(
            "SELECT COALESCE(SUM(qty),0) AS c,
                    COALESCE(SUM(price * qty),0) AS t
             FROM customer_cart WHERE customer_id = ?"
        );
        $tot->execute([$customerRowId]);
        $t = $tot->fetch(PDO::FETCH_ASSOC);

        jsonResponse(true, 'Cart updated.', [
            'cart_count'    => (int)($t['c'] ?? 0),
            'cart_total'    => (float)($t['t'] ?? 0),
            'customer_id'   => $customerRowId,
            'customer_code' => $customerCode,
            'source'        => 'db'
        ]);
    }

    /* GUEST — session cart */
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    foreach ($items as $it) {
        $vid = (int)($it['variant_id'] ?? 0);
        $qty = max(0, (int)($it['qty'] ?? 0));
        if ($vid <= 0 || !isset($variantById[$vid])) continue;
        $vr = $variantById[$vid];
        $key = 'p' . $productId . '_v' . $vid . '_' . ($menuCode ?: '');

        if ($qty > 0) {
            if (isset($_SESSION['cart'][$key])) {
                $_SESSION['cart'][$key]['qty'] = $qty;
                $_SESSION['cart'][$key]['menu_code'] = $menuCode;
            } else {
                $_SESSION['cart'][$key] = [
                    'key'          => $key,
                    'menu_code'    => $menuCode,
                    'product_id'   => (int)$product['id'],
                    'code'         => $product['product_code'],
                    'name'         => $product['product_name'],
                    'image'        => $imageUrl,
                    'variant_id'   => $vid,
                    'variant_name' => $vr['quantity_name'],
                    'variant_qty'  => $vr['quantity'] . ' ' . $vr['quantity_unit'],
                    'price'        => (float)$vr['price'],
                    'qty'          => $qty
                ];
            }
        } else {
            if (isset($_SESSION['cart'][$key])) unset($_SESSION['cart'][$key]);
        }
    }

    $count = 0; $total = 0;
    foreach ($_SESSION['cart'] as $c) {
        $q = (int)($c['qty'] ?? 0);
        $count += $q;
        $total += ((float)($c['price'] ?? 0)) * $q;
    }

    jsonResponse(true, 'Cart updated.', [
        'cart_count'    => $count,
        'cart_total'    => $total,
        'customer_id'   => 0,
        'customer_code' => '',
        'source'        => 'session'
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Server error: ' . $e->getMessage());
}