<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Method not allowed.');

$payment_id    = trim($_POST['razorpay_payment_id'] ?? '');
$name          = trim($_POST['customer_name'] ?? '');
$mobile        = trim($_POST['customer_mobile'] ?? '');
$apartmentId   = (int)($_POST['apartment_id'] ?? 0);
$apartmentCode = trim($_POST['apartment_code'] ?? '');
$division      = trim($_POST['division'] ?? '');
$divisionCharge = (float)($_POST['division_charge'] ?? 0);

if ($name === '' || !preg_match('/^[0-9]{10,15}$/', $mobile)) {
    jsonResponse(false, 'Invalid customer details.');
}
if ($apartmentId <= 0 || $division === '') {
    jsonResponse(false, 'Invalid apartment or division.');
}
if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    jsonResponse(false, 'Cart is empty.');
}

try {
    /* Apartment snapshot */
    $aStmt = $pdo->prepare("SELECT apartment_name FROM apartments WHERE id = ? LIMIT 1");
    $aStmt->execute([$apartmentId]);
    $apartmentName = $aStmt->fetchColumn() ?: '';

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

    $total = $subtotal + $divisionCharge;

    /* Generate order code */
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
             division, division_charge, subtotal, total_amount,
             products_json, status, payment_status, payment_ref, paid_at)
         VALUES
            (?, NULL, ?, ?,
             ?, ?, ?,
             ?, ?, ?, ?,
             ?, 'pending', 'paid', ?, NOW())"
    );

    $ins->execute([
        $orderCode, $name, $mobile,
        $apartmentId, $apartmentCode, $apartmentName,
        $division, $divisionCharge, $subtotal, $total,
        json_encode($items, JSON_UNESCAPED_UNICODE),
        $payment_id
    ]);

    $orderId = (int)$pdo->lastInsertId();

    /* Clear cart */
    $_SESSION['cart'] = [];

    jsonResponse(true, 'Order placed.', [
        'order_id'   => $orderId,
        'order_code' => $orderCode,
        'total'      => $total
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Server error placing order.');
}