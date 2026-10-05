<?php
/* =========================================================
   MRS MILL@ — PLACE ORDER
   Container deposit is stored separately — NOT added to
   orders.total_amount. GST is applied to subtotal only.
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
$walletRequested  = (float)($_POST['wallet_applied'] ?? 0);

if ($name === '' || !preg_match('/^[0-9]{10,15}$/', $mobile)) {
    jsonResponse(false, 'Invalid customer details.');
}
if (!in_array($deliveryMode, ['delivery', 'pickup'], true)) $deliveryMode = 'delivery';

/* ---------- CUSTOMER ---------- */
$customerId = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0
    ? (int)$_SESSION['customer_id']
    : 0;

/* ---------- DEFAULTS ---------- */
$items           = [];
$subtotal        = 0.0;
$totalContainers = 0;
$containerAmount = 0.0;

$orderId     = 0;
$orderCode   = '';
$grossTotal  = 0.0;
$apartmentName = '';

$taxAmount = 0.0;
$taxRate   = 0.0;
$taxType   = 'exclusive';

$walletApplied = 0.0;
$walletBalance = 0.0;
$walletBefore  = 0.0;
$walletAfter   = 0.0;

$deliveryBoyId  = null;
$activeMenuCode = null;
$hasOrderItems  = false;
$paymentStatus  = 'unpaid';

/* ---------- LOAD CART ---------- */
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

/* ---------- Ensure schema ---------- */
try {
    $pdo->exec("ALTER TABLE customers ADD COLUMN wallet_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER division_charge");
} catch (PDOException $e) {}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS customer_wallet_transactions (
        id INT(11) NOT NULL AUTO_INCREMENT,
        customer_id INT(11) NOT NULL,
        customer_name VARCHAR(150) NOT NULL,
        customer_mobile VARCHAR(30) NOT NULL,
        txn_code VARCHAR(30) NOT NULL,
        txn_type ENUM('credit','debit') NOT NULL,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        balance_before DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        balance_after DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        source ENUM('admin','staff','system','order','refund') NOT NULL DEFAULT 'admin',
        note VARCHAR(255) DEFAULT NULL,
        created_by_id INT(11) DEFAULT NULL,
        created_by_name VARCHAR(150) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
        PRIMARY KEY (id),
        UNIQUE KEY txn_code (txn_code),
        KEY customer_id (customer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
} catch (PDOException $e) {}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_containers (
        id INT(11) NOT NULL AUTO_INCREMENT,
        order_id INT(11) NOT NULL,
        customer_id INT(11) DEFAULT NULL,
        total_containers INT(11) NOT NULL DEFAULT 0,
        received_containers INT(11) NOT NULL DEFAULT 0,
        container_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        status ENUM('not_received','partial','received') NOT NULL DEFAULT 'not_received',
        received_at DATETIME DEFAULT NULL,
        received_by_id INT(11) DEFAULT NULL,
        received_by_name VARCHAR(150) DEFAULT NULL,
        note VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
        PRIMARY KEY (id),
        UNIQUE KEY order_unique (order_id),
        KEY customer_id (customer_id),
        KEY status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
} catch (PDOException $e) {}

/* =========================================================
   MAIN FLOW
   ========================================================= */
try {
    /* ---------- Address / Pickup validation ---------- */
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

        if ($pickupBranchId <= 0) jsonResponse(false, 'Please select a pickup branch.');

        $bStmt = $pdo->prepare("SELECT branch_name FROM settings_branches WHERE id = ? LIMIT 1");
        $bStmt->execute([$pickupBranchId]);
        $branchRow = $bStmt->fetch(PDO::FETCH_ASSOC);
        if (!$branchRow) jsonResponse(false, 'Invalid pickup branch.');

        $pickupBranchName = $branchRow['branch_name'];
    }

    /* ---------- Auto-assign delivery boy ---------- */
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

    /* ---------- Build items ---------- */
    $items           = [];
    $subtotal        = 0.0;
    $totalContainers = 0;
    $containerAmount = 0.0;

    $vStmt = $pdo->prepare(
        "SELECT container_enabled, container_price FROM product_variants WHERE id = ? LIMIT 1"
    );

    foreach ($cartItems as $c) {
        $qty   = (int)($c['qty'] ?? 0);
        $price = (float)($c['price'] ?? 0);
        $line  = $price * $qty;
        $subtotal += $line;

        $containerEnabled = 0;
        $containerPrice   = 0.0;
        $lineContainerAmt = 0.0;

        if (!empty($c['variant_id'])) {
            $vStmt->execute([(int)$c['variant_id']]);
            $v = $vStmt->fetch(PDO::FETCH_ASSOC);
            if ($v && (int)$v['container_enabled'] === 1 && (float)$v['container_price'] > 0) {
                $containerEnabled = 1;
                $containerPrice   = (float)$v['container_price'];
                $lineContainerAmt = $containerPrice * $qty;

                $totalContainers += $qty;
                $containerAmount += $lineContainerAmt;
            }
        }

        $items[] = [
            'menu_code'            => $c['menu_code'] ?? null,
            'product_id'           => (int)($c['product_id'] ?? 0),
            'code'                 => $c['code'] ?? '',
            'name'                 => $c['name'] ?? '',
            'image'                => $c['image'] ?? '',
            'variant_id'           => (int)($c['variant_id'] ?? 0),
            'variant_name'         => $c['variant_name'] ?? '',
            'variant_qty'          => $c['variant_qty'] ?? '',
            'price'                => $price,
            'qty'                  => $qty,
            'line_total'           => $line,
            'container_enabled'    => $containerEnabled,
            'container_price'      => $containerEnabled ? $containerPrice : 0,
            'container_line_total' => $lineContainerAmt,
        ];
    }

    /* =========================================================
       APPLY TAX (from settings)
       - Exclusive : tax = subtotal × rate%   (added on top)
       - Inclusive : tax inside subtotal      (back-calculated)
       - Delivery charge is NOT taxed
       - Container deposit is NOT taxed
       - Rounded to whole rupee
    ========================================================= */
    $settings  = getSettings($pdo);
    $taxStatus = (int)($settings['tax_status'] ?? 0);
    $taxRate   = (float)($settings['tax_rate'] ?? 0);
    $taxType   = strtolower($settings['tax_type'] ?? 'exclusive');

    if (!in_array($taxType, ['inclusive', 'exclusive'], true)) {
        $taxType = 'exclusive';
    }

    $taxAmount           = 0.0;
    $deliveryPortion     = ($deliveryMode === 'delivery') ? $divisionCharge : 0;
    $foodPlusDelivery    = 0.0;

    if ($taxStatus === 1 && $taxRate > 0) {
        if ($taxType === 'inclusive') {
            $rawTax    = $subtotal - ($subtotal / (1 + ($taxRate / 100)));
            $taxAmount = round($rawTax);
            $foodPlusDelivery = $subtotal + $deliveryPortion;
        } else {
            $rawTax    = $subtotal * ($taxRate / 100);
            $taxAmount = round($rawTax);
            $foodPlusDelivery = $subtotal + $deliveryPortion + $taxAmount;
        }
    } else {
        $taxRate   = 0.0;
        $taxAmount = 0.0;
        $foodPlusDelivery = $subtotal + $deliveryPortion;
    }

    /* Round to whole rupee */
    $foodPlusDelivery = round($foodPlusDelivery);

    /* Order total = food + delivery + tax (container NOT included) */
    $grossTotal = $foodPlusDelivery;

    /* ---------- Wallet ---------- */
    if ($customerId > 0) {
        $wStmt = $pdo->prepare(
            "SELECT wallet_balance FROM customers WHERE id = ? LIMIT 1 FOR UPDATE"
        );
        $wStmt->execute([$customerId]);
        $walletBalance = (float)$wStmt->fetchColumn();

        $walletApplied = min($walletRequested, $walletBalance, $foodPlusDelivery);
        if ($walletApplied < 0) $walletApplied = 0;

        $walletBefore = $walletBalance;
        $walletAfter  = $walletBalance - $walletApplied;
    }

    /* ---------- Order code ---------- */
    $last = $pdo->query("SELECT order_code FROM orders ORDER BY id DESC LIMIT 1")->fetchColumn();
    $nextNum = 1;
    if ($last && preg_match('/(\d+)$/', $last, $m)) {
        $nextNum = (int)$m[1] + 1;
    }
    $orderCode = 'ORD' . str_pad((string)$nextNum, 6, '0', STR_PAD_LEFT);

    /* ---------- Active menu fallback ---------- */
    try {
        $mStmt = $pdo->query(
            "SELECT menu_code FROM menus
             WHERE status = 1 AND start_at <= NOW() AND end_at >= NOW()
             ORDER BY start_at DESC LIMIT 1"
        );
        $activeMenuCode = $mStmt->fetchColumn() ?: null;
    } catch (PDOException $e) {}

    $stockStmt = $pdo->prepare(
        "UPDATE menu_products
         SET stock_count = GREATEST(stock_count - ?, 0)
         WHERE menu_code = ? AND variant_id = ? AND stock_unlimited = 0"
    );

    try {
        $tblCheck = $pdo->query("SHOW TABLES LIKE 'order_items'");
        $hasOrderItems = (bool)$tblCheck->fetchColumn();
    } catch (PDOException $e) {}

    $paymentStatus = !empty($payment_id) ? 'paid' : 'unpaid';

    /* ---------- TRANSACTION ---------- */
    $pdo->beginTransaction();

    /* Deduct wallet */
    if ($walletApplied > 0 && $customerId > 0) {
        $updW = $pdo->prepare(
            "UPDATE customers SET wallet_balance = ?, updated_at = NOW() WHERE id = ?"
        );
        $updW->execute([$walletAfter, $customerId]);

        try {
            $txnCode = 'TXN' . date('ymd') . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);

            $logW = $pdo->prepare(
                "INSERT INTO customer_wallet_transactions
                    (customer_id, customer_name, customer_mobile, txn_code,
                     txn_type, amount, balance_before, balance_after,
                     source, note, created_by_id, created_by_name, created_at)
                 VALUES (?, ?, ?, ?, 'debit', ?, ?, ?, 'order', ?, ?, ?, NOW())"
            );
            $logW->execute([
                $customerId,
                $name,
                $mobile,
                $txnCode,
                $walletApplied,
                $walletBefore,
                $walletAfter,
                'Order ' . $orderCode . ' — wallet payment',
                $customerId,
                $name
            ]);
        } catch (PDOException $e) {}
    }

    /* Insert order */
    $ins = $pdo->prepare(
        "INSERT INTO orders
            (order_code, delivery_boy_id, customer_name, customer_mobile,
             apartment_id, apartment_code, apartment_name,
             division, division_charge,
             tax_amount, tax_rate, tax_type,
             delivery_mode,
             pickup_branch_id, pickup_branch_name,
             subtotal, total_amount,
             products_json, status, delivery_status, payment_status, payment_ref, paid_at)
         VALUES
            (?, ?, ?, ?,
             ?, ?, ?,
             ?, ?,
             ?, ?, ?,
             ?,
             ?, ?,
             ?, ?,
             ?, 'pending', 'disabled', ?, ?, NOW())"
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
        $taxAmount,
        $taxRate,
        $taxType,
        $deliveryMode,
        $pickupBranchId > 0 ? $pickupBranchId : null,
        $pickupBranchName !== '' ? $pickupBranchName : null,
        $subtotal,
        $grossTotal,
        json_encode($items, JSON_UNESCAPED_UNICODE),
        $paymentStatus,
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

    /* Insert container row (if any) */
    if ($totalContainers > 0) {
        try {
            $cIns = $pdo->prepare(
                "INSERT INTO order_containers
                    (order_id, customer_id, total_containers, received_containers,
                     container_amount, status, created_at)
                 VALUES (?, ?, ?, 0, ?, 'not_received', NOW())"
            );
            $cIns->execute([
                $orderId,
                $customerId > 0 ? $customerId : null,
                $totalContainers,
                $containerAmount
            ]);
        } catch (PDOException $e) {}
    }

    /* Clear cart */
    if ($customerId > 0) {
        $pdo->prepare("DELETE FROM customer_cart WHERE customer_id = ?")
            ->execute([$customerId]);
    }
    $_SESSION['cart'] = [];

    $pdo->commit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonResponse(false, 'Server error: ' . $e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonResponse(false, 'Server error: ' . $e->getMessage());
}

jsonResponse(true, 'Order placed.', [
    'order_id'         => $orderId,
    'order_code'       => $orderCode,
    'subtotal'         => $subtotal,
    'tax_amount'       => $taxAmount,
    'tax_rate'         => $taxRate,
    'tax_type'         => $taxType,
    'total'            => $grossTotal,
    'wallet_applied'   => $walletApplied,
    'container_count'  => $totalContainers,
    'container_amount' => $containerAmount,
    'mode'             => $deliveryMode
]);