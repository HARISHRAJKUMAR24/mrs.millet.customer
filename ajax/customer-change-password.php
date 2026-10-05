<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

if (!isset($_SESSION['customer_id']) || (int)$_SESSION['customer_id'] <= 0) {
    jsonResponse(false, 'Not logged in.');
}

$customerId = (int)$_SESSION['customer_id'];

$newPass = trim($_POST['new_password'] ?? '');

if ($newPass === '') {
    jsonResponse(false, 'Password is required.');
}
if (strlen($newPass) < 3) {
    jsonResponse(false, 'Password must be at least 3 characters.');
}

try {
    $newHash = password_hash($newPass, PASSWORD_DEFAULT);

    $upd = $pdo->prepare("UPDATE customers SET password_hash = ?, updated_at = NOW() WHERE id = ?");
    $upd->execute([$newHash, $customerId]);

    jsonResponse(true, 'Password updated.');
} catch (PDOException $e) {
    jsonResponse(false, 'Failed to update password.');
}