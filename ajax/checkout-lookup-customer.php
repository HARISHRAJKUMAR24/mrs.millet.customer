<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

$mobile = trim($_GET['mobile'] ?? '');
if (!preg_match('/^[0-9]{10,15}$/', $mobile)) {
    jsonResponse(false, 'Invalid mobile.');
}

try {
    $stmt = $pdo->prepare(
        "SELECT customer_name, apartment_id, apartment_code, apartment_name, division
         FROM orders
         WHERE customer_mobile = ?
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute([$mobile]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        jsonResponse(true, 'No previous order', ['found' => false]);
    }

    jsonResponse(true, 'Found', [
        'found'          => true,
        'name'           => $row['customer_name'],
        'apartment_id'   => (int)$row['apartment_id'],
        'apartment_code' => $row['apartment_code'],
        'apartment_name' => $row['apartment_name'],
        'division'       => $row['division']
    ]);
} catch (PDOException $e) {
    jsonResponse(false, 'Lookup failed.');
}