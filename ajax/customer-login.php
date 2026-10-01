<?php
/* =========================================================
   MRS MILL@ — CUSTOMER LOGIN
   File: ./ajax/customer-login.php
   Accepts: mobile_number, password (optional)
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
        "SELECT id, full_name, password_hash, profile_completed
         FROM customers
         WHERE mobile_number = ? AND status = 1
         LIMIT 1"
    );
    $stmt->execute([$mobile]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    /* New customer */
    if (!$row) {
        jsonResponse(true, 'New customer', [
            'exists' => false,
            'mobile' => $mobile
        ]);
    }

    /* Existing customer — needs password */
    if (!empty($row['password_hash'])) {

        /* Password not provided yet — ask for it */
        if ($password === '') {
            jsonResponse(true, 'Password required', [
                'exists'          => true,
                'password_needed' => true,
                'id'              => (int)$row['id'],
                'name'            => $row['full_name']
            ]);
        }

        /* Password provided — verify */
        if (!password_verify($password, $row['password_hash'])) {
            jsonResponse(false, 'Incorrect password.');
        }
    }

    /* Success — set session */
    $_SESSION['customer_id']     = (int)$row['id'];
    $_SESSION['customer_name']   = $row['full_name'];
    $_SESSION['customer_mobile'] = $mobile;

    $pdo->prepare("UPDATE customers SET last_login_at = NOW() WHERE id = ?")
        ->execute([$row['id']]);

    jsonResponse(true, 'Welcome back, ' . $row['full_name'] . '!', [
        'exists'            => true,
        'id'                => (int)$row['id'],
        'name'              => $row['full_name'],
        'mobile'            => $mobile,
        'profile_completed' => (int)$row['profile_completed'] === 1
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Server error. Please try again.');
}