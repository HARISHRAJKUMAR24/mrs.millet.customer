<?php
/* =========================================================
   MRS MILL@ — GET APARTMENTS + DIVISIONS (for register popup)
   File: ./ajax/get-appartment-divisions.php
   Returns divisions normalized to BOTH shapes so the frontend
   can always read block/door_no/floor. Legacy `division` rows
   are mapped into door_no automatically.
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

            if (!is_array($d)) continue;

            /* New shape */
            $block  = isset($d['block'])   ? (string)$d['block']   : '';
            $doorNo = isset($d['door_no']) ? (string)$d['door_no'] : '';
            $floor  = isset($d['floor'])   ? (string)$d['floor']   : '';
            $charge = (float)($d['charge'] ?? 0);

            /* Legacy shape fallback */
            if ($block === '' && $doorNo === '' && $floor === '') {
                if (isset($d['division'])) {
                    $doorNo = (string)$d['division'];
                } else {
                    continue;
                }
            }

            /* Emit BOTH shapes so old and new frontend code both work */
            $clean[] = [
                'block'    => $block,
                'door_no'  => $doorNo,
                'floor'    => $floor,
                'charge'   => $charge,

                /* Keep legacy key so nothing that still reads `division` breaks */
                'division' => $doorNo
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
    jsonResponse(false, 'Failed to load apartments: ' . $e->getMessage());
}