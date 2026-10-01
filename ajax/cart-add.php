<?php
/* =========================================================
   MRS MILL@ — ADD TO CART
   File: ./ajax/cart-add.php
   Uses DB (customer_cart) when logged in, session for guests.
   Also saves the ACTIVE menu_code with each cart line so stock
   is reduced against the correct menu at order time.
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

$customerId = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0
    ? (int)$_SESSION['customer_id']
    : 0;

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

    /* ---------- 2. Resolve ACTIVE menu_code ---------- */
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

    /* ============ LOGGED IN → DB ============ */
    if ($customerId > 0) {

        /* Check if row exists */
        $chk = $pdo->prepare(
            "SELECT id, qty FROM customer_cart
             WHERE customer_id = ? AND product_id = ? AND variant_id = ?
             LIMIT 1"
        );
        $chk->execute([$customerId, $productId, $variantId]);
        $existing = $chk->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            /* If row exists but has no menu_code, set it now */
            $upd = $pdo->prepare(
                "UPDATE customer_cart
                 SET qty = qty + ?,
                     menu_code = COALESCE(menu_code, ?),
                     updated_at = NOW()
                 WHERE id = ?"
            );
            $upd->execute([$qty, $menuCode, $existing['id']]);
        } else {
            $ins = $pdo->prepare(
                "INSERT INTO customer_cart
                    (customer_id, menu_code, product_id, product_code, product_name, product_image,
                     variant_id, variant_name, variant_qty, price, qty)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $ins->execute([
                $customerId,
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

        /* Merge any guest session cart into DB (first time they log in) */
        if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $guestItem) {
                $pid  = (int)($guestItem['product_id'] ?? 0);
                $vid  = (int)($guestItem['variant_id'] ?? 0);
                $gqty = (int)($guestItem['qty'] ?? 0);
                if ($pid <= 0 || $vid <= 0 || $gqty <= 0) continue;

                $gMenu = $guestItem['menu_code'] ?? $menuCode;

                $chk2 = $pdo->prepare(
                    "SELECT id FROM customer_cart
                     WHERE customer_id = ? AND product_id = ? AND variant_id = ?
                     LIMIT 1"
                );
                $chk2->execute([$customerId, $pid, $vid]);
                $exists = $chk2->fetchColumn();

                if ($exists) {
                    $pdo->prepare(
                        "UPDATE customer_cart
                         SET qty = qty + ?,
                             menu_code = COALESCE(menu_code, ?)
                         WHERE id = ?"
                    )->execute([$gqty, $gMenu, $exists]);
                } else {
                    $pdo->prepare(
                        "INSERT INTO customer_cart
                            (customer_id, menu_code, product_id, product_code, product_name, product_image,
                             variant_id, variant_name, variant_qty, price, qty)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    )->execute([
                        $customerId,
                        $gMenu,
                        $pid,
                        $guestItem['code'] ?? '',
                        $guestItem['name'] ?? '',
                        $guestItem['image'] ?? '',
                        $vid,
                        $guestItem['variant_name'] ?? '',
                        $guestItem['variant_qty'] ?? '',
                        (float)($guestItem['price'] ?? 0),
                        $gqty
                    ]);
                }
            }
            $_SESSION['cart'] = [];
        }

        /* Fetch new totals from DB */
        $cntStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(qty),0) AS c,
                    COALESCE(SUM(price * qty),0) AS t
             FROM customer_cart WHERE customer_id = ?"
        );
        $cntStmt->execute([$customerId]);
        $totals = $cntStmt->fetch(PDO::FETCH_ASSOC);

        jsonResponse(true, 'Added to cart.', [
            'cart_count' => (int)$totals['c'],
            'cart_total' => (float)$totals['t'],
            'source'     => 'db'
        ]);
    }

    /* ============ GUEST → session ============ */
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $key = 'p' . $row['id'] . '_v' . $row['variant_id'];

    if (isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key]['qty'] += $qty;
        /* Backfill menu_code if missing */
        if (empty($_SESSION['cart'][$key]['menu_code']) && $menuCode) {
            $_SESSION['cart'][$key]['menu_code'] = $menuCode;
        }
    } else {
        $_SESSION['cart'][$key] = [
            'key'          => $key,
            'menu_code'    => $menuCode,           /* ✅ SAVED HERE */
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
        'cart_count' => $count,
        'cart_total' => $total,
        'source'     => 'session'
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Server error: ' . $e->getMessage());
}