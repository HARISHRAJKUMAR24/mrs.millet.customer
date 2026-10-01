<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $stmt = $pdo->query(
        "SELECT id, branch_name, branch_address, branch_mobile
         FROM settings_branches
         ORDER BY branch_name ASC"
    );
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(true, 'OK', $branches);
} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load branches.');
}