<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

$mobile = trim($_GET['mobile'] ?? '');
if (!preg_match('/^[0-9]{10,15}$/', $mobile)) {
    jsonResponse(false, 'Invalid mobile.');
}

try {
    /* 1. Look for the most recent order that HAS an apartment (skip pickup-only orders) */
    $stmt = $pdo->prepare(
        "SELECT customer_name, apartment_id, apartment_code, apartment_name, division
         FROM orders
         WHERE customer_mobile = ?
           AND apartment_id IS NOT NULL
           AND apartment_id > 0
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute([$mobile]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        jsonResponse(true, 'Found in orders', [
            'found'          => true,
            'name'           => $row['customer_name'],
            'apartment_id'   => (int)$row['apartment_id'],
            'apartment_code' => $row['apartment_code'],
            'apartment_name' => $row['apartment_name'],
            'division'       => $row['division']
        ]);
    }

    /* 2. Fallback: check the customers table (name only) */
    $stmt2 = $pdo->prepare(
        "SELECT full_name FROM customers WHERE mobile_number = ? AND status = 1 LIMIT 1"
    );
    $stmt2->execute([$mobile]);
    $cust = $stmt2->fetch(PDO::FETCH_ASSOC);

    if ($cust) {
        jsonResponse(true, 'Found in customers', [
            'found'          => true,
            'name'           => $cust['full_name'],
            'apartment_id'   => 0,
            'apartment_code' => '',
            'apartment_name' => '',
            'division'       => ''
        ]);
    }

    jsonResponse(true, 'No previous records', ['found' => false]);

} catch (PDOException $e) {
    jsonResponse(false, 'Lookup failed.');
}