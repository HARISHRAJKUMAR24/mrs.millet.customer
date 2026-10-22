<?php
/* =========================================================
   MRS MILL@ — CUSTOMER: UPDATE PROFILE
   File: ./ajax/customer-update-profile.php

   Only apartments can be edited here.
   Individual House addresses are locked (would need admin approval).
   Address goes into customers.address_json — NOT flat columns.
   ========================================================= */

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

$fullName       = trim($_POST['full_name']         ?? '');
$apartmentId    = (int)($_POST['apartment_id']     ?? 0);
$division       = trim($_POST['division']          ?? '');
$divisionCharge = (float)($_POST['division_charge'] ?? 0);

if (mb_strlen($fullName) < 3) {
    jsonResponse(false, 'Please enter a valid name.');
}
if ($apartmentId <= 0 || $division === '') {
    jsonResponse(false, 'Please choose apartment and division.');
}

try {
    /* Verify apartment + division */
    $aStmt = $pdo->prepare(
        "SELECT apartment_code, apartment_name, divisions
         FROM apartments WHERE id = ? AND status = 1 LIMIT 1"
    );
    $aStmt->execute([$apartmentId]);
    $apt = $aStmt->fetch(PDO::FETCH_ASSOC);
    if (!$apt) jsonResponse(false, 'Apartment not found.');

    $divs = json_decode($apt['divisions'] ?? '[]', true);
    $matched = null;
    $block   = ''; $door = ''; $floor = '';

    if (is_array($divs)) {
        foreach ($divs as $d) {
            $parts = [];
            if (!empty($d['block']))   $parts[] = "Blk " . $d['block'];
            if (!empty($d['door_no'])) $parts[] = "Door " . $d['door_no'];
            if (!empty($d['floor']))   $parts[] = "Fl " . $d['floor'];
            $label = $parts ? implode(" / ", $parts) : (string)($d['division'] ?? "");

            if (strcasecmp(trim($label), $division) === 0 ||
                strcasecmp(trim((string)($d['division'] ?? '')), $division) === 0) {
                $matched = (float)($d['charge'] ?? 0);
                $block   = (string)($d['block']   ?? '');
                $door    = (string)($d['door_no'] ?? '');
                $floor   = (string)($d['floor']   ?? '');
                break;
            }
        }
    }
    if ($matched === null) {
        jsonResponse(false, 'Division is not valid for this apartment.');
    }

    $addressJson = [
        'apartment_id'   => $apartmentId,
        'apartment_code' => $apt['apartment_code'],
        'apartment_name' => $apt['apartment_name'],
        'block'          => $block,
        'door_no'        => $door,
        'floor'          => $floor,
        'division_label' => $division,
        'charge'         => $matched,
    ];
    $addressJsonFinal = json_encode($addressJson, JSON_UNESCAPED_UNICODE);

    $upd = $pdo->prepare(
        "UPDATE customers
         SET full_name = ?,
             address_type = 'apartment',
             address_json = ?,
             updated_at = NOW()
         WHERE id = ?"
    );
    $upd->execute([
        $fullName,
        $addressJsonFinal,
        $customerId,
    ]);

    $_SESSION['customer_name'] = $fullName;

    jsonResponse(true, 'Profile updated.', [
        'address' => $addressJson,
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to update profile: ' . $e->getMessage());
}