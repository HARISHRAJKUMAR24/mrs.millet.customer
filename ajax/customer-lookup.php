<?php
/* =========================================================
   MRS MILL@ — CUSTOMER LOOKUP
   File: ./ajax/customer-lookup.php
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

$mobile = trim($_GET['mobile'] ?? '');
if (!preg_match('/^[0-9]{10,15}$/', $mobile)) {
    jsonResponse(false, 'Invalid mobile.');
}

try {
    $stmt = $pdo->prepare(
        "SELECT id, full_name, password_hash,
                apartment_id, apartment_code, apartment_name,
                division, division_charge, profile_completed
         FROM customers
         WHERE mobile_number = ? AND status = 1
         LIMIT 1"
    );
    $stmt->execute([$mobile]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        jsonResponse(true, 'New customer', ['exists' => false]);
    }

    jsonResponse(true, 'Customer exists', [
        'exists'            => true,
        'id'                => (int)$row['id'],
        'name'              => $row['full_name'],
        'has_password'      => !empty($row['password_hash']),
        'apartment_id'      => (int)$row['apartment_id'],
        'apartment_code'    => $row['apartment_code'],
        'apartment_name'    => $row['apartment_name'],
        'division'          => $row['division'],
        'division_charge'   => (float)$row['division_charge'],
        'profile_completed' => (int)$row['profile_completed'] === 1
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Lookup failed.');
}