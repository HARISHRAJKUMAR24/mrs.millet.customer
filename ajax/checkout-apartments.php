<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $stmt = $pdo->query(
        "SELECT id, apartment_code, apartment_name, apartment_address
         FROM apartments
         WHERE status = 1
         ORDER BY apartment_name ASC"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(true, 'OK', $rows);
} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load apartments.');
}