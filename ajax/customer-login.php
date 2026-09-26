<?php
/* =========================================================
   MRS MILL@ — CUSTOMER LOGIN
   File: ./ajax/customer-login.php
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$mobile = trim($_POST['mobile_number'] ?? '');

if (!preg_match('/^[0-9]{10,15}$/', $mobile)) {
    jsonResponse(false, 'Mobile number must be 10–15 digits.');
}

try {

    $stmt = $pdo->prepare(
        "SELECT id, full_name FROM customers WHERE mobile_number = ? AND status = 1 LIMIT 1"
    );
    $stmt->execute([$mobile]);
    $row = $stmt->fetch();

    if ($row) {
        $_SESSION['customer_id']     = (int)$row['id'];
        $_SESSION['customer_name']   = $row['full_name'];
        $_SESSION['customer_mobile'] = $mobile;

        /* Update last login */
        $pdo->prepare("UPDATE customers SET last_login_at = NOW() WHERE id = ?")
            ->execute([$row['id']]);

        jsonResponse(true, 'Welcome back, ' . $row['full_name'] . '!', [
            'exists' => true,
            'id'     => (int)$row['id'],
            'name'   => $row['full_name'],
            'mobile' => $mobile
        ]);
    }

    jsonResponse(true, 'New customer', [
        'exists' => false,
        'mobile' => $mobile
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Server error. Please try again.');
}