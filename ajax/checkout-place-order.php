<?php
/* =========================================================
   MRS MILL@ — PLACE ORDER
   File: ./ajax/checkout-place-order.php
   Reads cart from customer_cart (DB) when logged in,
   from $_SESSION['cart'] for guests.
   Stock reduced in menu_products using the menu_code stored
   on each cart line (fallback = currently active menu).
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

/* ---------- INPUT ---------- */
$payment_id       = trim($_POST['razorpay_payment_id'] ?? '');
$name             = trim($_POST['customer_name'] ?? '');
$mobile           = trim($_POST['customer_mobile'] ?? '');
$deliveryMode     = trim($_POST['delivery_mode'] ?? 'delivery');
$apartmentId      = (int)($_POST['apartment_id'] ?? 0);
$apartmentCode    = trim($_POST['apartment_code'] ?? '');
$division         = trim($_POST['division'] ?? '');
$divisionCharge   = (float)($_POST['division_charge'] ?? 0);
$pickupBranchId   = (int)($_POST['pickup_branch_id'] ?? 0);
$pickupBranchName = trim($_POST['pickup_branch_name'] ?? '');

if ($name === '' || !preg_match('/^[0-9]{10,15}$/', $mobile)) {
    jsonResponse(false, 'Invalid customer details.');
}
if (!in_array($deliveryMode, ['delivery', 'pickup'], true)) $deliveryMode = 'delivery';

/* ---------- CUSTOMER ---------- */
$customerId = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0
    ? (int)$_SESSION['customer_id']
    : 0;

/* ---------- LOAD CART (with menu_code) ---------- */
$cartItems = [];

try {
    if ($customerId > 0) {
        $stmt = $pdo->prepare(
            "SELECT menu_code,
                    product_id, product_code AS code, product_name AS name,
                    product_image AS image,
                    variant_id, variant_name, variant_qty,
                    price, qty
             FROM customer_cart
             WHERE customer_id = ?
             ORDER BY id ASC"
        );
        $stmt->execute([$customerId]);
        $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
            $cartItems = array_values($_SESSION['cart']);
        }
    }
} catch (PDOException $e) {
    $cartItems = [];
}

if (empty($cartItems)) {
    jsonResponse(false, 'Cart is empty.');
}

/* =========================================================
   MAIN FLOW
   ========================================================= */
$orderId   = 0;
$orderCode = '';
$total     = 0;

try {
    $apartmentName = '';

    if ($deliveryMode === 'delivery') {
        if ($apartmentId <= 0 || $division === '') {
            jsonResponse(false, 'Invalid apartment or division.');
        }

        $aStmt = $pdo->prepare(
            "SELECT apartment_code, apartment_name
             FROM apartments WHERE id = ? LIMIT 1"
        );
        $aStmt->execute([$apartmentId]);
        $apt = $aStmt->fetch(PDO::FETCH_ASSOC);
        if (!$apt) jsonResponse(false, 'Apartment not found.');

        $apartmentName = $apt['apartment_name'];
        $apartmentCode = $apt['apartment_code'];

        $pickupBranchId   = 0;
        $pickupBranchName = '';

    } else {
        $apartmentId    = 0;
        $apartmentCode  = '';
        $apartmentName  = '';
        $division       = '';
        $divisionCharge = 0;

        if ($pickupBranchId <= 0) {
            jsonResponse(false, 'Please select a pickup branch.');
        }

        $bStmt = $pdo->prepare("SELECT branch_name FROM settings_branches WHERE id = ? LIMIT 1");
        $bStmt->execute([$pickupBranchId]);
        $branchRow = $bStmt->fetch(PDO::FETCH_ASSOC);
        if (!$branchRow) jsonResponse(false, 'Invalid pickup branch.');

        $pickupBranchName = $branchRow['branch_name'];
    }

    /* Auto-assign delivery boy */
    $deliveryBoyId = null;
    if ($deliveryMode === 'delivery' && $apartmentCode !== '') {
        try {
            $dBoyStmt = $pdo->prepare(
                "SELECT adb.delivery_boy_id
                 FROM apartment_delivery_boys adb
                 INNER JOIN delivery_boys db ON db.id = adb.delivery_boy_id
                 WHERE adb.apartment_code = ?
                   AND db.status = 1
                 LIMIT 1"
            );
            $dBoyStmt->execute([$apartmentCode]);
            $row = $dBoyStmt->fetch(PDO::FETCH_ASSOC);
            if ($row) $deliveryBoyId = (int)$row['delivery_boy_id'];
        } catch (PDOException $e) {}
    }

    /* Build items + subtotal */
    $items = [];
    $subtotal = 0;
    foreach ($cartItems as $c) {
        $qty   = (int)($c['qty'] ?? 0);
        $price = (float)($c['price'] ?? 0);
        $line  = $price * $qty;
        $subtotal += $line;

        $items[] = [
            'menu_code'    => $c['menu_code'] ?? null,   /* ✅ carried through */
            'product_id'   => (int)($c['product_id'] ?? 0),
            'code'         => $c['code'] ?? '',
            'name'         => $c['name'] ?? '',
            'image'        => $c['image'] ?? '',
            'variant_id'   => (int)($c['variant_id'] ?? 0),
            'variant_name' => $c['variant_name'] ?? '',
            'variant_qty'  => $c['variant_qty'] ?? '',
            'price'        => $price,
            'qty'          => $qty,
            'line_total'   => $line
        ];
    }

    $total = $subtotal + ($deliveryMode === 'delivery' ? $divisionCharge : 0);

    /* Order code */
    $last = $pdo->query("SELECT order_code FROM orders ORDER BY id DESC LIMIT 1")->fetchColumn();
    $nextNum = 1;
    if ($last && preg_match('/(\d+)$/', $last, $m)) {
        $nextNum = (int)$m[1] + 1;
    }
    $orderCode = 'ORD' . str_pad((string)$nextNum, 6, '0', STR_PAD_LEFT);

    /* ---------- FALLBACK: active menu code ---------- */
    $activeMenuCode = null;
    try {
        $mStmt = $pdo->query(
            "SELECT menu_code FROM menus
             WHERE status = 1
               AND start_at <= NOW()
               AND end_at   >= NOW()
             ORDER BY start_at DESC
             LIMIT 1"
        );
        $activeMenuCode = $mStmt->fetchColumn() ?: null;
    } catch (PDOException $e) {
        $activeMenuCode = null;
    }

    /* ---------- Stock update statement (menu_products) ---------- */
    $stockStmt = $pdo->prepare(
        "UPDATE menu_products
         SET stock_count = GREATEST(stock_count - ?, 0)
         WHERE menu_code = ?
           AND variant_id = ?
           AND stock_unlimited = 0"
    );

    /* Optional order_items table */
    $hasOrderItems = false;
    try {
        $tblCheck = $pdo->query("SHOW TABLES LIKE 'order_items'");
        $hasOrderItems = (bool)$tblCheck->fetchColumn();
    } catch (PDOException $e) {
        $hasOrderItems = false;
    }

    /* ---------- TRANSACTION ---------- */
    $pdo->beginTransaction();

    /* Insert order */
    $ins = $pdo->prepare(
        "INSERT INTO orders
            (order_code, delivery_boy_id, customer_name, customer_mobile,
             apartment_id, apartment_code, apartment_name,
             division, division_charge, delivery_mode,
             pickup_branch_id, pickup_branch_name,
             subtotal, total_amount,
             products_json, status, delivery_status, payment_status, payment_ref, paid_at)
         VALUES
            (?, ?, ?, ?,
             ?, ?, ?,
             ?, ?, ?,
             ?, ?,
             ?, ?,
             ?, 'pending', 'disabled', 'paid', ?, NOW())"
    );

    $ins->execute([
        $orderCode,
        $deliveryBoyId,
        $name,
        $mobile,
        $apartmentId > 0 ? $apartmentId : null,
        $apartmentCode,
        $apartmentName,
        $division,
        $divisionCharge,
        $deliveryMode,
        $pickupBranchId > 0 ? $pickupBranchId : null,
        $pickupBranchName !== '' ? $pickupBranchName : null,
        $subtotal,
        $total,
        json_encode($items, JSON_UNESCAPED_UNICODE),
        $payment_id
    ]);

    $orderId = (int)$pdo->lastInsertId();

    $itemInsert = null;
    if ($hasOrderItems) {
        $itemInsert = $pdo->prepare(
            "INSERT INTO order_items
                (order_id, product_id, product_code, product_name, product_image,
                 variant_id, variant_name, variant_qty, price, qty, line_total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
    }

    foreach ($items as $it) {
        /* ✅ Stock minus in menu_products — using the menu_code saved at add-to-cart time */
        if ($it['variant_id'] > 0 && $it['qty'] > 0) {
            $menuForLine = $it['menu_code'] ?: $activeMenuCode;
            if ($menuForLine) {
                $stockStmt->execute([$it['qty'], $menuForLine, $it['variant_id']]);
            }
        }

        if ($itemInsert) {
            try {
                $itemInsert->execute([
                    $orderId,
                    $it['product_id'],
                    $it['code'],
                    $it['name'],
                    $it['image'],
                    $it['variant_id'],
                    $it['variant_name'],
                    $it['variant_qty'],
                    $it['price'],
                    $it['qty'],
                    $it['line_total']
                ]);
            } catch (PDOException $e) {}
        }
    }

    /* Clear cart */
    if ($customerId > 0) {
        $pdo->prepare("DELETE FROM customer_cart WHERE customer_id = ?")
            ->execute([$customerId]);
    }
    $_SESSION['cart'] = [];

    $pdo->commit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse(false, 'Server error: ' . $e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse(false, 'Server error: ' . $e->getMessage());
}

jsonResponse(true, 'Order placed.', [
    'order_id'   => $orderId,
    'order_code' => $orderCode,
    'total'      => $total,
    'mode'       => $deliveryMode
]);