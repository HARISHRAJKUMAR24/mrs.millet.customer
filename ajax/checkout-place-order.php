<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Method not allowed.');

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

if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    jsonResponse(false, 'Cart is empty.');
}

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

        /* Not a pickup → clear pickup fields */
        $pickupBranchId   = 0;
        $pickupBranchName = '';

    } else {
        /* Store pickup */
        $apartmentId    = 0;
        $apartmentCode  = '';
        $apartmentName  = '';
        $division       = '';
        $divisionCharge = 0;

        if ($pickupBranchId <= 0) {
            jsonResponse(false, 'Please select a pickup branch.');
        }

        /* Verify branch + fetch fresh name */
        $bStmt = $pdo->prepare("SELECT branch_name FROM settings_branches WHERE id = ? LIMIT 1");
        $bStmt->execute([$pickupBranchId]);
        $branchRow = $bStmt->fetch(PDO::FETCH_ASSOC);
        if (!$branchRow) jsonResponse(false, 'Invalid pickup branch.');

        $pickupBranchName = $branchRow['branch_name'];
    }

    /* Auto-assign delivery boy for delivery orders */
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

    /* Build items */
    $items = [];
    $subtotal = 0;
    foreach ($_SESSION['cart'] as $c) {
        $qty = (int)($c['qty'] ?? 0);
        $price = (float)($c['price'] ?? 0);
        $line = $price * $qty;
        $subtotal += $line;

        $items[] = [
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

    /* Insert */
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

    $_SESSION['cart'] = [];

    jsonResponse(true, 'Order placed.', [
        'order_id'   => $orderId,
        'order_code' => $orderCode,
        'total'      => $total,
        'mode'       => $deliveryMode
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Server error placing order.');
}