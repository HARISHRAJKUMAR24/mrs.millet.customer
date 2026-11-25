<?php
/* =========================================================
   MRS MILL@ — CUSTOMER FORGOT PASSWORD REQUEST
   File: ./ajax/forgot-password-request.php
   Saves a password reset request; admin resolves it later.
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}

$mobile = trim($_POST['mobile_number'] ?? '');

if (!preg_match('/^[0-9]{10,15}$/', $mobile)) {
    jsonResponse(false, 'Mobile number must be 10–15 digits.');
}

try {
    /* Find customer (optional — request allowed even if not found) */
    $stmt = $pdo->prepare(
        "SELECT id, full_name FROM customers WHERE mobile_number = ? LIMIT 1"
    );
    $stmt->execute([$mobile]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    /* Avoid duplicate pending requests (within last 30 min) */
    $dup = $pdo->prepare(
        "SELECT id FROM password_reset_requests
         WHERE mobile_number = ? AND status = 0
           AND created_at >= (NOW() - INTERVAL 30 MINUTE)
         LIMIT 1"
    );
    $dup->execute([$mobile]);
    if ($dup->fetch()) {
        jsonResponse(true, 'A reset request was already submitted. Our team will contact you soon.');
    }

    $ins = $pdo->prepare(
        "INSERT INTO password_reset_requests
            (mobile_number, customer_id, customer_name, status)
         VALUES (?, ?, ?, 0)"
    );
    $ins->execute([
        $mobile,
        $customer ? (int)$customer['id'] : null,
        $customer ? $customer['full_name'] : null
    ]);

    jsonResponse(true, 'Request sent. Our team will contact you shortly.');
} catch (PDOException $e) {
    jsonResponse(false, 'Unable to save request: ' . $e->getMessage());
}