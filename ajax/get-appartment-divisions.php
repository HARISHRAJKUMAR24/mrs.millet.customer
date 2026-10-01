<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

$id = (int)($_GET['apartment_id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'Invalid apartment.');

try {
    $stmt = $pdo->prepare("SELECT divisions FROM apartments WHERE id = ? AND status = 1 LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) jsonResponse(false, 'Apartment not found.');

    $divisions = json_decode($row['divisions'] ?? '[]', true);
    if (!is_array($divisions)) $divisions = [];

    /* Normalize: [{division:"1", charge:30}, ...] */
    $clean = [];
    foreach ($divisions as $d) {
        if (!isset($d['division'])) continue;
        $clean[] = [
            'division' => (string)$d['division'],
            'charge'   => (float)($d['charge'] ?? 0)
        ];
    }

    jsonResponse(true, 'OK', $clean);
} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load divisions.');
}