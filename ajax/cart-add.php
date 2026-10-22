<?php
/* =========================================================
   MRS MILL@ — ADD TO CART
   File: ./ajax/cart-add.php
   - Logged-in → customer_cart (customer_id = customers.id INTEGER)
   - Guest     → $_SESSION['cart']
   Response includes BOTH:
     customer_id   → integer (customers.id)         ← FK used
     customer_code → varchar (customers.customer_id)  ← "MRSMCxxxxx"
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

/* Logged in? */
$customerRowId = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0
    ? (int)$_SESSION['customer_id']
    : 0;
$customerCode = (string)($_SESSION['customer_code'] ?? '');

try {
    /* ---------- 1. Load product + variant ---------- */
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

    $imageUrl = !empty($row['product_image']) ? ADMIN_URL . $row['product_image'] : '';

    /* ---------- 2. Active menu code ---------- */
    $menuCode = null;
    try {
        $mStmt = $pdo->query(
            "SELECT menu_code FROM menus
             WHERE status = 1
               AND start_at <= NOW()
               AND end_at   >= NOW()
             ORDER BY start_at DESC
             LIMIT 1"
        );
        $menuCode = $mStmt->fetchColumn() ?: null;
    } catch (PDOException $e) {
        $menuCode = null;
    }

    /* =========================================================
       LOGGED IN → customer_cart
       ========================================================= */
    if ($customerRowId > 0) {

        /* Fill customer_code if the session lost it */
        if ($customerCode === '') {
            try {
                $cs = $pdo->prepare(
                    "SELECT customer_id FROM customers WHERE id = ? LIMIT 1"
                );
                $cs->execute([$customerRowId]);
                $customerCode = (string)$cs->fetchColumn();
                $_SESSION['customer_code'] = $customerCode;
            } catch (PDOException $e) {
                $customerCode = '';
            }
        }

        $chk = $pdo->prepare(
            "SELECT id FROM customer_cart
             WHERE customer_id = ? AND product_id = ? AND variant_id = ?
             LIMIT 1"
        );
        $chk->execute([$customerRowId, $productId, $variantId]);
        $existingId = $chk->fetchColumn();

        if ($existingId) {
            $pdo->prepare(
                "UPDATE customer_cart
                 SET qty = qty + ?,
                     menu_code = COALESCE(menu_code, ?),
                     updated_at = NOW()
                 WHERE id = ?"
            )->execute([$qty, $menuCode, $existingId]);
        } else {
            $pdo->prepare(
                "INSERT INTO customer_cart
                    (customer_id, menu_code, product_id, product_code, product_name, product_image,
                     variant_id, variant_name, variant_qty, price, qty)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $customerRowId,
                $menuCode,
                (int)$row['id'],
                $row['product_code'],
                $row['product_name'],
                $imageUrl,
                (int)$row['variant_id'],
                $row['quantity_name'],
                $row['quantity'] . ' ' . $row['quantity_unit'],
                (float)$row['price'],
                $qty
            ]);
        }

        /* Merge guest session cart once */
        if (empty($_SESSION['cart_merged'])
            && !empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {

            foreach ($_SESSION['cart'] as $g) {
                $pid  = (int)($g['product_id'] ?? 0);
                $vid  = (int)($g['variant_id'] ?? 0);
                $gqty = (int)($g['qty'] ?? 0);
                if ($pid <= 0 || $vid <= 0 || $gqty <= 0) continue;

                $gMenu = $g['menu_code'] ?? $menuCode;

                $chk2 = $pdo->prepare(
                    "SELECT id FROM customer_cart
                     WHERE customer_id = ? AND product_id = ? AND variant_id = ?
                     LIMIT 1"
                );
                $chk2->execute([$customerRowId, $pid, $vid]);
                $gId = $chk2->fetchColumn();

                if ($gId) {
                    $pdo->prepare(
                        "UPDATE customer_cart
                         SET qty = qty + ?, menu_code = COALESCE(menu_code, ?)
                         WHERE id = ?"
                    )->execute([$gqty, $gMenu, $gId]);
                } else {
                    $pdo->prepare(
                        "INSERT INTO customer_cart
                            (customer_id, menu_code, product_id, product_code, product_name, product_image,
                             variant_id, variant_name, variant_qty, price, qty)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    )->execute([
                        $customerRowId,
                        $gMenu,
                        $pid,
                        $g['code'] ?? '',
                        $g['name'] ?? '',
                        $g['image'] ?? '',
                        $vid,
                        $g['variant_name'] ?? '',
                        $g['variant_qty'] ?? '',
                        (float)($g['price'] ?? 0),
                        $gqty
                    ]);
                }
            }
            $_SESSION['cart'] = [];
            $_SESSION['cart_merged'] = true;
        }

        /* Totals from DB */
        $tot = $pdo->prepare(
            "SELECT COALESCE(SUM(qty),0) AS c,
                    COALESCE(SUM(price * qty),0) AS t
             FROM customer_cart WHERE customer_id = ?"
        );
        $tot->execute([$customerRowId]);
        $t = $tot->fetch(PDO::FETCH_ASSOC);

        jsonResponse(true, 'Added to cart.', [
            'cart_count'    => (int)($t['c'] ?? 0),
            'cart_total'    => (float)($t['t'] ?? 0),
            'customer_id'   => $customerRowId,
            'customer_code' => $customerCode,
            'source'        => 'db'
        ]);
    }

    /* ============ GUEST → session ============ */
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $key = 'p' . $row['id'] . '_v' . $row['variant_id'];

    if (isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key]['qty'] += $qty;
        if (empty($_SESSION['cart'][$key]['menu_code']) && $menuCode) {
            $_SESSION['cart'][$key]['menu_code'] = $menuCode;
        }
    } else {
        $_SESSION['cart'][$key] = [
            'key'          => $key,
            'menu_code'    => $menuCode,
            'product_id'   => (int)$row['id'],
            'code'         => $row['product_code'],
            'name'         => $row['product_name'],
            'image'        => $imageUrl,
            'variant_id'   => (int)$row['variant_id'],
            'variant_name' => $row['quantity_name'],
            'variant_qty'  => $row['quantity'] . ' ' . $row['quantity_unit'],
            'price'        => (float)$row['price'],
            'qty'          => $qty
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
        'cart_count'    => $count,
        'cart_total'    => $total,
        'customer_id'   => 0,
        'customer_code' => '',
        'source'        => 'session'
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Server error: ' . $e->getMessage());
}