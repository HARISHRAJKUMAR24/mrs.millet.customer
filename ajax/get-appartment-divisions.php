<?php
/* =========================================================
   MRS MILL@ — GET APARTMENTS + DIVISIONS (for register popup)
   File: ./ajax/get-appartment-divisions.php
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $stmt = $pdo->query(
        "SELECT id, apartment_code, apartment_name, apartment_address, divisions
         FROM apartments
         WHERE status = 1
         ORDER BY apartment_name ASC"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $r) {
        $divs = json_decode($r['divisions'] ?? '[]', true);
        if (!is_array($divs)) $divs = [];

        $clean = [];
        foreach ($divs as $d) {
            if (!isset($d['division'])) continue;
            $clean[] = [
                'division' => (string)$d['division'],
                'charge'   => (float)($d['charge'] ?? 0)
            ];
        }

        $out[] = [
            'id'                => (int)$r['id'],
            'apartment_code'    => $r['apartment_code'],
            'apartment_name'    => $r['apartment_name'],
            'apartment_address' => $r['apartment_address'],
            'divisions'         => $clean
        ];
    }

    jsonResponse(true, 'OK', $out);
} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load apartments.');
}