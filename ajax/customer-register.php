<?php
/* =========================================================
   MRS MILL@ — CUSTOMER REGISTER
   File: ./ajax/customer-register.php
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$name   = trim($_POST['full_name'] ?? '');
$mobile = trim($_POST['mobile_number'] ?? '');

if ($name === '' || mb_strlen($name) < 3) {
    jsonResponse(false, 'Name must be at least 3 characters.');
}

if (!preg_match('/^[0-9]{10,15}$/', $mobile)) {
    jsonResponse(false, 'Mobile number must be 10–15 digits.');
}

try {

    /* Double check not existing */
    $chk = $pdo->prepare("SELECT id FROM customers WHERE mobile_number = ? LIMIT 1");
    $chk->execute([$mobile]);
    $existing = $chk->fetch();

    if ($existing) {
        $_SESSION['customer_id']     = (int)$existing['id'];
        $_SESSION['customer_name']   = $name;
        $_SESSION['customer_mobile'] = $mobile;

        jsonResponse(true, 'Welcome back!', [
            'id'     => (int)$existing['id'],
            'name'   => $name,
            'mobile' => $mobile
        ]);
    }

    $ins = $pdo->prepare(
        "INSERT INTO customers (full_name, mobile_number, last_login_at)
         VALUES (?, ?, NOW())"
    );
    $ins->execute([$name, $mobile]);

    $newId = (int)$pdo->lastInsertId();

    $_SESSION['customer_id']     = $newId;
    $_SESSION['customer_name']   = $name;
    $_SESSION['customer_mobile'] = $mobile;

    jsonResponse(true, 'Account created successfully!', [
        'id'     => $newId,
        'name'   => $name,
        'mobile' => $mobile
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Server error. Please try again.');
}