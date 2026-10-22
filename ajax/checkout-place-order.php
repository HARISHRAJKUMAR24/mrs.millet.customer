<?php
/* =========================================================
   MRS MILL@ — PLACE ORDER
   File: ./ajax/checkout-place-order.php

   Orders schema (current):
     customer_id         varchar(20)   MRSMCxxxxx
     address_type        enum('apartment','individual_house','pickup')
     address_json        longtext      JSON blob

   order_containers.customer_id  → int(11)  (customers.id)

   Wallet covers food + tax + delivery.
   Container deposit tracked but NOT added to total_amount.
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

/* ---------- PRE-DECLARE every variable that will be used in jsonResponse ---------- */
$orderId         = 0;
$orderCode       = '';
$customerCode    = '';
$addressType     = 'apartment';
$deliveryMode    = 'delivery';
$subtotal        = 0.0;
$taxAmount       = 0.0;
$taxRate         = 0.0;
$taxType         = 'exclusive';
$grossTotal      = 0.0;
$walletApplied   = 0.0;
$totalContainers = 0;
$containerAmount = 0.0;
$deliveryBoyId   = null;

/* ---------- INPUT ---------- */
$payment_id       = trim($_POST['razorpay_payment_id'] ?? '');
$name             = trim($_POST['customer_name']     ?? '');
$mobile           = trim($_POST['customer_mobile']   ?? '');
$deliveryMode     = trim($_POST['delivery_mode']     ?? 'delivery');
$addressType      = trim($_POST['address_type']      ?? 'apartment');

$apartmentId      = (int)($_POST['apartment_id']     ?? 0);
$apartmentCode    = trim($_POST['apartment_code']    ?? '');
$division         = trim($_POST['division']          ?? '');
$divisionCharge   = (float)($_POST['division_charge'] ?? 0);
$fullAddress      = trim($_POST['full_address']      ?? '');
$mapLink          = trim($_POST['map_link']          ?? '');

$pickupBranchId   = (int)($_POST['pickup_branch_id'] ?? 0);
$pickupBranchName = trim($_POST['pickup_branch_name'] ?? '');
$walletRequested  = (float)($_POST['wallet_applied'] ?? 0);

if ($name === '' || !preg_match('/^[0-9]{10,15}$/', $mobile)) {
    jsonResponse(false, 'Invalid customer details.');
}
if (!in_array($deliveryMode, ['delivery', 'pickup'], true))            $deliveryMode = 'delivery';
if (!in_array($addressType,  ['apartment', 'individual_house'], true)) $addressType  = 'apartment';

/* ---------- CUSTOMER ---------- */
$customerRowId = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0
    ? (int)$_SESSION['customer_id']
    : 0;

if ($customerRowId > 0) {
    try {
        $cs = $pdo->prepare("SELECT customer_id FROM customers WHERE id = ? LIMIT 1");
        $cs->execute([$customerRowId]);
        $customerCode = (string)$cs->fetchColumn();
    } catch (PDOException $e) {
        $customerCode = '';
    }
}
if ($customerCode === '') {
    jsonResponse(false, 'You must be logged in to place an order.');
}

/* ---------- LOAD CART ---------- */
$cartItems = [];
try {
    $stmt = $pdo->prepare(
        "SELECT menu_code, product_id, product_code AS code, product_name AS name,
                product_image AS image, variant_id, variant_name, variant_qty,
                price, qty
         FROM customer_cart WHERE customer_id = ? ORDER BY id ASC"
    );
    $stmt->execute([$customerRowId]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $cartItems = [];
}
if (empty($cartItems)) {
    jsonResponse(false, 'Cart is empty.');
}

/* ---------- BUILD ADDRESS JSON + resolved details ---------- */
$addressJson           = [];
$apartmentName         = '';
$resolvedCode          = '';
$divisionChargeForCalc = 0.0;

if ($deliveryMode === 'delivery') {

    if ($addressType === 'individual_house') {

        /* ---------- INDIVIDUAL HOUSE ---------- */
        if ($fullAddress === '') jsonResponse(false, 'Address is missing for this order.');

        $houseImage = '';
        try {
            $ci = $pdo->prepare("SELECT address_json FROM customers WHERE id = ? LIMIT 1");
            $ci->execute([$customerRowId]);
            $addr = json_decode((string)$ci->fetchColumn(), true);
            if (is_array($addr) && !empty($addr['house_image'])) {
                $houseImage = $addr['house_image'];
            }
        } catch (PDOException $e) {}

        $addressJson = [
            'full_address' => $fullAddress,
            'map_link'     => $mapLink,
            'house_image'  => $houseImage,
        ];

        $resolvedCode = 'IH_' . substr(md5(trim($fullAddress)), 0, 12);

        /* reset apartment fields */
        $apartmentId           = 0;
        $apartmentCode         = '';
        $apartmentName         = '';
        $division              = '';
        $divisionCharge        = 0;
        $divisionChargeForCalc = 0;

    } else {

        /* ---------- APARTMENT ---------- */
        if ($apartmentId <= 0 || $division === '') {
            jsonResponse(false, 'Invalid apartment or division.');
        }

        $aStmt = $pdo->prepare(
            "SELECT apartment_code, apartment_name FROM apartments WHERE id = ? LIMIT 1"
        );
        $aStmt->execute([$apartmentId]);
        $apt = $aStmt->fetch(PDO::FETCH_ASSOC);
        if (!$apt) jsonResponse(false, 'Apartment not found.');

        $apartmentCode = $apt['apartment_code'];
        $apartmentName = $apt['apartment_name'];

        /* Parse block / door / floor from division label */
        $block = ''; $door = ''; $floor = '';
        foreach (array_map('trim', explode('/', $division)) as $p) {
            if (stripos($p, 'Blk ')    === 0) $block = trim(substr($p, 4));
            elseif (stripos($p, 'Door ') === 0) $door  = trim(substr($p, 5));
            elseif (stripos($p, 'Fl ')   === 0) $floor = trim(substr($p, 3));
        }

        $addressJson = [
            'apartment_id'   => $apartmentId,
            'apartment_code' => $apartmentCode,
            'apartment_name' => $apartmentName,
            'block'          => $block,
            'door_no'        => $door,
            'floor'          => $floor,
            'division_label' => $division,
            'charge'         => $divisionCharge,
        ];

        $resolvedCode          = $apartmentCode;
        $divisionChargeForCalc = $divisionCharge;
    }

} else {
    /* ---------- PICKUP ---------- */
    if ($pickupBranchId <= 0) jsonResponse(false, 'Please select a pickup branch.');

    $bStmt = $pdo->prepare("SELECT branch_name FROM settings_branches WHERE id = ? LIMIT 1");
    $bStmt->execute([$pickupBranchId]);
    $branchRow = $bStmt->fetch(PDO::FETCH_ASSOC);
    if (!$branchRow) jsonResponse(false, 'Invalid pickup branch.');

    $pickupBranchName = $branchRow['branch_name'];

    $addressJson = [
        'branch_id'   => $pickupBranchId,
        'branch_name' => $pickupBranchName,
    ];

    $addressType           = 'pickup';
    $apartmentId           = 0;
    $apartmentCode         = '';
    $apartmentName         = '';
    $division              = '';
    $divisionCharge        = 0;
    $divisionChargeForCalc = 0;
    $resolvedCode          = '';
}

$addressJsonFinal = json_encode($addressJson, JSON_UNESCAPED_UNICODE);

/* ---------- AUTO-ASSIGN DELIVERY BOY ---------- */
if ($deliveryMode === 'delivery' && $resolvedCode !== '') {
    try {
        $dBoyStmt = $pdo->prepare(
            "SELECT adb.delivery_boy_id
             FROM apartment_delivery_boys adb
             INNER JOIN delivery_boys db ON db.id = adb.delivery_boy_id
             WHERE adb.apartment_code = ? AND db.status = 1
             ORDER BY adb.id ASC
             LIMIT 1"
        );
        $dBoyStmt->execute([$resolvedCode]);
        $row = $dBoyStmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $deliveryBoyId = (int)$row['delivery_boy_id'];
    } catch (PDOException $e) {}
}

/* ---------- BUILD ITEMS + CONTAINERS ---------- */
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

/* ---------- TAX ---------- */
$settings  = getSettings($pdo);
$taxStatus = (int)($settings['tax_status'] ?? 0);
$taxRate   = (float)($settings['tax_rate'] ?? 0);
$taxType   = strtolower($settings['tax_type'] ?? 'exclusive');
if (!in_array($taxType, ['inclusive', 'exclusive'], true)) $taxType = 'exclusive';

$taxAmount        = 0.0;
$deliveryPortion  = ($deliveryMode === 'delivery') ? $divisionChargeForCalc : 0;
$foodPlusDelivery = 0.0;

if ($taxStatus === 1 && $taxRate > 0) {
    if ($taxType === 'inclusive') {
        $taxAmount        = round($subtotal - ($subtotal / (1 + ($taxRate / 100))));
        $foodPlusDelivery = $subtotal + $deliveryPortion;
    } else {
        $taxAmount        = round($subtotal * ($taxRate / 100));
        $foodPlusDelivery = $subtotal + $deliveryPortion + $taxAmount;
    }
} else {
    $taxRate          = 0.0;
    $taxAmount        = 0.0;
    $foodPlusDelivery = $subtotal + $deliveryPortion;
}

$foodPlusDelivery = round($foodPlusDelivery);
$grossTotal       = $foodPlusDelivery;

/* ---------- WALLET ---------- */
$walletApplied = 0.0;
$walletBalance = 0.0;
$walletBefore  = 0.0;
$walletAfter   = 0.0;

if ($customerRowId > 0) {
    $wStmt = $pdo->prepare(
        "SELECT wallet_balance FROM customers WHERE id = ? LIMIT 1 FOR UPDATE"
    );
    $wStmt->execute([$customerRowId]);
    $walletBalance = (float)$wStmt->fetchColumn();

    $walletApplied = min($walletRequested, $walletBalance, $foodPlusDelivery);
    if ($walletApplied < 0) $walletApplied = 0;

    $walletBefore = $walletBalance;
    $walletAfter  = $walletBalance - $walletApplied;
}

/* ---------- ORDER CODE ---------- */
$last = $pdo->query("SELECT order_code FROM orders ORDER BY id DESC LIMIT 1")->fetchColumn();
$nextNum = 1;
if ($last && preg_match('/(\d+)$/', $last, $m)) $nextNum = (int)$m[1] + 1;
$orderCode = 'ORD' . str_pad((string)$nextNum, 6, '0', STR_PAD_LEFT);

/* ---------- ACTIVE MENU FALLBACK ---------- */
$activeMenuCode = null;
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

/* order_items table optional */
$hasOrderItems = false;
try {
    $tblCheck = $pdo->query("SHOW TABLES LIKE 'order_items'");
    $hasOrderItems = (bool)$tblCheck->fetchColumn();
} catch (PDOException $e) {
    $hasOrderItems = false;
}

$paymentStatus = !empty($payment_id) ? 'paid' : 'unpaid';

/* =========================================================
   TRANSACTION
   ========================================================= */
try {

    $pdo->beginTransaction();

    /* Deduct wallet */
    if ($walletApplied > 0 && $customerRowId > 0) {
        $updW = $pdo->prepare(
            "UPDATE customers SET wallet_balance = ?, updated_at = NOW() WHERE id = ?"
        );
        $updW->execute([$walletAfter, $customerRowId]);

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
                $customerRowId,
                $name,
                $mobile,
                $txnCode,
                $walletApplied,
                $walletBefore,
                $walletAfter,
                'Order ' . $orderCode . ' — wallet payment',
                $customerRowId,
                $name,
            ]);
        } catch (PDOException $e) {}
    }

    /* Insert order — uses ONLY real columns */
    $ins = $pdo->prepare(
        "INSERT INTO orders
            (order_code, customer_id, delivery_boy_id,
             address_type, address_json,
             delivery_mode,
             pickup_branch_id, pickup_branch_name,
             division_charge,
             tax_amount, tax_rate, tax_type,
             subtotal, total_amount, products_json,
             status, delivery_status, payment_status, payment_ref, paid_at, created_at)
         VALUES
            (?, ?, ?,
             ?, ?,
             ?,
             ?, ?,
             ?,
             ?, ?, ?,
             ?, ?, ?,
             'pending', 'disabled', ?, ?, ?, NOW())"
    );

    $ins->execute([
        $orderCode,
        $customerCode,           // varchar MRSMCxxxxx
        $deliveryBoyId,
        $addressType,            // 'apartment' | 'individual_house' | 'pickup'
        $addressJsonFinal,
        $deliveryMode,
        $pickupBranchId > 0 ? $pickupBranchId : null,
        $pickupBranchName !== '' ? $pickupBranchName : null,
        $divisionChargeForCalc,
        $taxAmount,
        $taxRate,
        $taxType,
        $subtotal,
        $grossTotal,
        json_encode($items, JSON_UNESCAPED_UNICODE),
        $paymentStatus,
        $payment_id !== '' ? $payment_id : null,
        $paymentStatus === 'paid' ? date('Y-m-d H:i:s') : null,
    ]);

    $orderId = (int)$pdo->lastInsertId();

    /* Optional order_items insert */
    $itemInsert = null;
    if ($hasOrderItems) {
        $itemInsert = $pdo->prepare(
            "INSERT INTO order_items
                (order_id, product_id, product_code, product_name, product_image,
                 variant_id, variant_name, variant_qty, price, qty, line_total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
    }

    /* Loop items: reduce stock + optional order_items */
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
                    $it['line_total'],
                ]);
            } catch (PDOException $e) {}
        }
    }

    /* Container row — customer_id is INT (customers.id) */
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
                $customerRowId,
                $totalContainers,
                $containerAmount,
            ]);
        } catch (PDOException $e) {}
    }

    /* Clear cart */
    $pdo->prepare("DELETE FROM customer_cart WHERE customer_id = ?")
        ->execute([$customerRowId]);
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
    'customer_code'    => $customerCode,
    'address_type'     => $addressType,
    'subtotal'         => $subtotal,
    'tax_amount'       => $taxAmount,
    'tax_rate'         => $taxRate,
    'tax_type'         => $taxType,
    'total'            => $grossTotal,
    'wallet_applied'   => $walletApplied,
    'container_count'  => $totalContainers,
    'container_amount' => $containerAmount,
    'mode'             => $deliveryMode,
    'delivery_boy_id'  => $deliveryBoyId,
]);