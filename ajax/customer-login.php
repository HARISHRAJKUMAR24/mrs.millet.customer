<?php
/* =========================================================
   MRS MILL@ — CUSTOMER LOGIN
   File: ./ajax/customer-login.php
   Source: `customers` only (per current mrs_millet schema).
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$mobile   = trim($_POST['mobile_number'] ?? '');
$password = trim($_POST['password'] ?? '');

if (!preg_match('/^[0-9]{10,15}$/', $mobile)) {
    jsonResponse(false, 'Mobile number must be 10–15 digits.');
}

try {

    $stmt = $pdo->prepare(
        "SELECT id, customer_id, full_name, password_hash,
                profile_completed, address_type, address_json
         FROM customers
         WHERE mobile_number = ? AND status = 1
         LIMIT 1"
    );
    $stmt->execute([$mobile]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        jsonResponse(true, 'New customer', [
            'exists' => false,
            'mobile' => $mobile
        ]);
    }

    /* Needs a password? */
    if (!empty($row['password_hash'])) {
        if ($password === '') {
            jsonResponse(true, 'Password required', [
                'exists'          => true,
                'password_needed' => true,
                'id'              => (int)$row['id'],
                'name'            => $row['full_name']
            ]);
        }
        if (!password_verify($password, $row['password_hash'])) {
            jsonResponse(false, 'Incorrect password.');
        }
    }

    /* Login success */
    $_SESSION['customer_id']     = (int)$row['id'];
    $_SESSION['customer_code']   = $row['customer_id'];
    $_SESSION['customer_name']   = $row['full_name'];
    $_SESSION['customer_mobile'] = $mobile;

    $pdo->prepare("UPDATE customers SET last_login_at = NOW() WHERE id = ?")
        ->execute([$row['id']]);

    /* Decode address for the front-end */
    $addr = json_decode($row['address_json'] ?? '{}', true);
    if (!is_array($addr)) $addr = [];

    jsonResponse(true, 'Welcome back, ' . $row['full_name'] . '!', [
        'exists'            => true,
        'id'                => (int)$row['id'],
        'customer_id'       => $row['customer_id'],
        'name'              => $row['full_name'],
        'mobile'            => $mobile,
        'address_type'      => $row['address_type'] ?: 'apartment',
        'address'           => $addr,
        'profile_completed' => (int)$row['profile_completed'] === 1
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Server error: ' . $e->getMessage());
}