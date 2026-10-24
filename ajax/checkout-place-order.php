<?php
/* =========================================================
   MRS MILL@ — PLACE ORDER
   File: ./ajax/checkout-place-order.php
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

/* ---------- PRE-DECLARE ---------- */
$orderId         = 0;
$orderCode       = '';
$customerCode    = '';
$addressType     = 'apartment';
$deliveryMode    = 'delivery';
$subtotal        = 0.0;
$discountAmount  = 0.0;
$taxAmount       = 0.0;
$taxRate         = 0.0;
$taxType         = 'exclusive';
$grossTotal      = 0.0;
$walletApplied   = 0.0;
$totalContainers = 0;
$containerAmount = 0.0;
$deliveryBoyId   = null;
$orderNote       = '';
$appliedCoupon   = '';
$couponDiscount  = 0.0;

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

/* ---------- ORDER NOTE ---------- */
$orderNote = trim($_POST['order_note'] ?? '');
if (mb_strlen($orderNote) > 500) $orderNote = mb_substr($orderNote, 0, 500);

/* ---------- SESSION COUPON ---------- */
$appliedCoupon = strtoupper(trim($_SESSION['applied_coupon'] ?? ''));

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

/* ---------- BUILD ADDRESS JSON ---------- */
$addressJson           = [];
$apartmentName         = '';
$resolvedCode          = '';
$divisionChargeForCalc = 0.0;

if ($deliveryMode === 'delivery') {

    if ($addressType === 'individual_house') {

        if ($fullAddress === '') jsonResponse(false, 'Address is missing for this order.');

        $houseImage = '';
        try {
            $ci = $pdo->prepare("SELECT address_json FROM customers WHERE id = ? LIMIT 1");
            $ci->execute([$customerRowId]);
            $addr = json_decode((string)$ci->fetchColumn(), true);
            if (is_array($addr) && !empty($addr['house_image'])) {
                $houseImage = $addr['house_image'];
            }
        } catch (PDOException $e) {
        }

        $addressJson = [
            'full_address' => $fullAddress,
            'map_link'     => $mapLink,
            'house_image'  => $houseImage,
        ];

        $resolvedCode = 'IH_' . substr(md5(trim($fullAddress)), 0, 12);
        $apartmentId = 0;
        $apartmentCode = '';
        $apartmentName = '';
        $division = '';
        $divisionCharge = 0;
        $divisionChargeForCalc = 0;
    } else {

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

        $block = '';
        $door = '';
        $floor = '';
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

    $addressType = 'pickup';
    $apartmentId = 0;
    $apartmentCode = '';
    $apartmentName = '';
    $division = '';
    $divisionCharge = 0;
    $divisionChargeForCalc = 0;
    $resolvedCode = '';
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
             ORDER BY adb.id ASC LIMIT 1"
        );
        $dBoyStmt->execute([$resolvedCode]);
        $row = $dBoyStmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $deliveryBoyId = (int)$row['delivery_boy_id'];
    } catch (PDOException $e) {
    }
}

/* ---------- BUILD ITEMS ---------- */
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
   RECALCULATE DISCOUNTS SERVER-SIDE (trust but verify)
   ========================================================= */
$totalDiscountAmount = 0;

/* A) TIME-BASED DISCOUNT (per menu) */
$cartGroupedByMenu = [];
foreach ($items as $c) {
    $mc = $c['menu_code'] ?: 'GENERAL';
    if (!isset($cartGroupedByMenu[$mc])) {
        $cartGroupedByMenu[$mc] = ['subtotal' => 0];
    }
    $cartGroupedByMenu[$mc]['subtotal'] += ($c['price'] * $c['qty']);
}

foreach ($cartGroupedByMenu as $menuCode => $group) {
    if ($menuCode === 'GENERAL') continue;
    $menuSubtotal = $group['subtotal'];

    try {
        $mStmt = $pdo->prepare("SELECT * FROM menus WHERE menu_code = ? LIMIT 1");
        $mStmt->execute([$menuCode]);
        $menuInfo = $mStmt->fetch(PDO::FETCH_ASSOC);
        if (!$menuInfo) continue;

        $menuStart = strtotime($menuInfo['start_at']);
        $menuEnd   = strtotime($menuInfo['end_at']);
        $now       = time();
        if ($now < $menuStart || $now > $menuEnd) continue;

        $dStmt = $pdo->prepare(
            "SELECT dt.amount_type, dt.discount_amount, dt.start_time, dt.end_time
             FROM discounts d
             INNER JOIN discount_times dt ON dt.discount_code = d.discount_code
             WHERE d.status = 1 AND d.discount_type = 'time'
             ORDER BY dt.id ASC"
        );
        $dStmt->execute();
        $slots = $dStmt->fetchAll(PDO::FETCH_ASSOC);

        $prevEndEpoch = $menuStart;
        $baseDay      = date('Y-m-d', $menuStart);
        $cur = null;

        foreach ($slots as $s) {
            $slotEndTs = strtotime($baseDay . ' ' . $s['end_time']);
            if ($slotEndTs < $prevEndEpoch) $slotEndTs += 86400;

            if ($now >= $prevEndEpoch && $now <= $slotEndTs) {
                $cur = $s;
                break;
            }
            $prevEndEpoch = $slotEndTs;
        }

        if ($cur && (float)$cur['discount_amount'] > 0) {
            if ($cur['amount_type'] === 'percent') {
                $totalDiscountAmount += round(($menuSubtotal * (float)$cur['discount_amount']) / 100);
            } else {
                $totalDiscountAmount += min((float)$cur['discount_amount'], $menuSubtotal);
            }
        }
    } catch (PDOException $e) {
    }
}

/* B) CUSTOMER LOYALTY DISCOUNT */
try {
    $lStmt = $pdo->prepare(
        "SELECT COUNT(id) FROM orders
         WHERE customer_id = ? AND status != 'cancelled'"
    );
    $lStmt->execute([$customerCode]);
    $orderCount = (int)$lStmt->fetchColumn();

    $tStmt = $pdo->prepare(
        "SELECT t.amount_type, t.discount_amount
         FROM discounts d
         INNER JOIN discount_customer_tiers t ON t.discount_code = d.discount_code
         WHERE d.status = 1
           AND d.discount_type = 'customer_type'
           AND ? BETWEEN t.min_orders AND t.max_orders
         LIMIT 1"
    );
    $tStmt->execute([$orderCount]);
    $tierRow = $tStmt->fetch(PDO::FETCH_ASSOC);

    if ($tierRow && (float)$tierRow['discount_amount'] > 0) {
        if ($tierRow['amount_type'] === 'percent') {
            $totalDiscountAmount += round(($subtotal * (float)$tierRow['discount_amount']) / 100);
        } else {
            $totalDiscountAmount += min((float)$tierRow['discount_amount'], $subtotal);
        }
    }
} catch (PDOException $e) {
}

/* C) COUPON DISCOUNT (with limit re-check) */
if ($appliedCoupon !== '') {
    try {
        $cStmt = $pdo->prepare(
            "SELECT d.id, d.discount_code, d.usage_limit, d.used_count,
                    d.valid_from_date, d.valid_to_date,
                    dt.start_time, dt.end_time, dt.amount_type, dt.discount_amount
             FROM discounts d
             INNER JOIN discount_times dt ON dt.discount_code = d.discount_code
             WHERE d.status = 1 AND d.discount_type = 'coupon' AND d.coupon_code = ?
             LIMIT 1"
        );
        $cStmt->execute([$appliedCoupon]);
        $cRow = $cStmt->fetch(PDO::FETCH_ASSOC);

        if ($cRow) {
            // Re-check limit
            if ((int)$cRow['usage_limit'] > 0 && (int)$cRow['used_count'] >= (int)$cRow['usage_limit']) {
                jsonResponse(false, 'This coupon has reached its usage limit.');
            }

            // Date check
            $nowD = date('Y-m-d');
            if (!empty($cRow['valid_from_date']) && $nowD < $cRow['valid_from_date']) {
                jsonResponse(false, 'Coupon is not yet active.');
            }
            if (!empty($cRow['valid_to_date']) && $nowD > $cRow['valid_to_date']) {
                jsonResponse(false, 'Coupon has expired.');
            }

            // Time window
            $nowT = date('H:i:s');
            if ($nowT < $cRow['start_time'] || $nowT > $cRow['end_time']) {
                jsonResponse(false, 'Coupon cannot be used at this time.');
            }

            $amt = (float)$cRow['discount_amount'];
            if ($cRow['amount_type'] === 'percent') {
                $couponDiscount = round(($subtotal * $amt) / 100);
            } else {
                $couponDiscount = min($amt, $subtotal);
            }
            $totalDiscountAmount += $couponDiscount;
        } else {
            // Coupon no longer exists / disabled → ignore silently
            $appliedCoupon = '';
            unset($_SESSION['applied_coupon']);
        }
    } catch (PDOException $e) {
    }
}

/* Cap total discount at subtotal */
if ($totalDiscountAmount > $subtotal) $totalDiscountAmount = $subtotal;
$discountAmount = round($totalDiscountAmount);
$discountedSubtotal = max(0, $subtotal - $discountAmount);

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
        $taxAmount        = round($discountedSubtotal - ($discountedSubtotal / (1 + ($taxRate / 100))));
        $foodPlusDelivery = $discountedSubtotal + $deliveryPortion;
    } else {
        $taxAmount        = round($discountedSubtotal * ($taxRate / 100));
        $foodPlusDelivery = $discountedSubtotal + $deliveryPortion + $taxAmount;
    }
} else {
    $taxRate          = 0.0;
    $taxAmount        = 0.0;
    $foodPlusDelivery = $discountedSubtotal + $deliveryPortion;
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
} catch (PDOException $e) {
}

$stockStmt = $pdo->prepare(
    "UPDATE menu_products
     SET stock_count = GREATEST(stock_count - ?, 0)
     WHERE menu_code = ? AND variant_id = ? AND stock_unlimited = 0"
);

$hasOrderItems = false;
try {
    $tblCheck = $pdo->query("SHOW TABLES LIKE 'order_items'");
    $hasOrderItems = (bool)$tblCheck->fetchColumn();
} catch (PDOException $e) {
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
        } catch (PDOException $e) {
        }
    }

    /* Insert order */
    $ins = $pdo->prepare(
        "INSERT INTO orders
            (order_code, customer_id, delivery_boy_id,
             address_type, address_json,
             delivery_mode,
             pickup_branch_id, pickup_branch_name,
             division_charge,
             tax_amount, tax_rate, tax_type,
             subtotal, total_amount, products_json,
             order_note,
             status, delivery_status, payment_status, payment_ref, paid_at, created_at)
         VALUES
            (?, ?, ?,
             ?, ?,
             ?,
             ?, ?,
             ?,
             ?, ?, ?,
             ?, ?, ?,
             ?,
             'pending', 'disabled', ?, ?, ?, NOW())"
    );

    $ins->execute([
        $orderCode,
        $customerCode,
        $deliveryBoyId,
        $addressType,
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
        $orderNote !== '' ? $orderNote : null,
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
            } catch (PDOException $e) {
            }
        }
    }

    /* Container row */
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
        } catch (PDOException $e) {
        }
    }

    /* ✅ TRACK COUPON USAGE (atomic) */
    if ($appliedCoupon !== '' && $couponDiscount > 0) {
        try {
            $cStmt = $pdo->prepare(
                "SELECT id, discount_code, usage_limit, used_count
                 FROM discounts WHERE coupon_code = ? LIMIT 1 FOR UPDATE"
            );
            $cStmt->execute([$appliedCoupon]);
            $cRow = $cStmt->fetch(PDO::FETCH_ASSOC);

            if ($cRow) {
                // Re-check limit inside the transaction
                if ((int)$cRow['usage_limit'] > 0 && (int)$cRow['used_count'] >= (int)$cRow['usage_limit']) {
                    // Rollback because limit was consumed between validation and now
                    $pdo->rollBack();
                    jsonResponse(false, 'This coupon has just reached its usage limit. Please remove it and try again.');
                }

                // Log usage
                $logStmt = $pdo->prepare(
                    "INSERT INTO coupon_usage
                        (discount_code, coupon_code, customer_id, customer_name,
                         customer_mobile, order_id, order_code, used_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
                );
                $logStmt->execute([
                    $cRow['discount_code'],
                    $appliedCoupon,
                    $customerRowId,
                    $name,
                    $mobile,
                    $orderId,
                    $orderCode
                ]);

                // Increment counter
                $pdo->prepare("UPDATE discounts SET used_count = used_count + 1 WHERE id = ?")
                    ->execute([$cRow['id']]);
            }
        } catch (PDOException $e) {
        }
    }

    /* Clear cart */
    $pdo->prepare("DELETE FROM customer_cart WHERE customer_id = ?")
        ->execute([$customerRowId]);
    $_SESSION['cart'] = [];

    /* Clear applied coupon */
    unset($_SESSION['applied_coupon']);

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
    'discount'         => $discountAmount,
    'coupon_code'      => $appliedCoupon,
    'coupon_discount'  => $couponDiscount,
    'tax_amount'       => $taxAmount,
    'tax_rate'         => $taxRate,
    'tax_type'         => $taxType,
    'total'            => $grossTotal,
    'wallet_applied'   => $walletApplied,
    'container_count'  => $totalContainers,
    'container_amount' => $containerAmount,
    'mode'             => $deliveryMode,
    'delivery_boy_id'  => $deliveryBoyId,
    'order_note'       => $orderNote,
]);
