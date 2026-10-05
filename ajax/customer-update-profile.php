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

$fullName      = trim($_POST['full_name'] ?? '');
$apartmentId   = (int)($_POST['apartment_id'] ?? 0);
$apartmentCode = trim($_POST['apartment_code'] ?? '');
$division      = trim($_POST['division'] ?? '');
$divisionCharge = (float)($_POST['division_charge'] ?? 0);

if (mb_strlen($fullName) < 3) {
    jsonResponse(false, 'Please enter a valid name.');
}
if ($apartmentId <= 0 || $division === '') {
    jsonResponse(false, 'Please choose apartment and division.');
}

try {
    /* Verify apartment + division */
    $aStmt = $pdo->prepare("SELECT apartment_code, apartment_name, divisions FROM apartments WHERE id = ? LIMIT 1");
    $aStmt->execute([$apartmentId]);
    $apt = $aStmt->fetch(PDO::FETCH_ASSOC);
    if (!$apt) jsonResponse(false, 'Apartment not found.');

    $divs = json_decode($apt['divisions'] ?? '[]', true);
    $matched = null;
    if (is_array($divs)) {
        foreach ($divs as $d) {
            if (strcasecmp(trim($d['division'] ?? ''), $division) === 0) {
                $matched = (float)($d['charge'] ?? 0);
                break;
            }
        }
    }
    if ($matched === null) {
        jsonResponse(false, 'Division is not valid for this apartment.');
    }

    $upd = $pdo->prepare(
        "UPDATE customers
         SET full_name = ?, apartment_id = ?, apartment_code = ?, apartment_name = ?,
             division = ?, division_charge = ?, updated_at = NOW()
         WHERE id = ?"
    );
    $upd->execute([
        $fullName,
        $apartmentId,
        $apt['apartment_code'],
        $apt['apartment_name'],
        $division,
        $matched,
        $customerId
    ]);

    $_SESSION['customer_name'] = $fullName;

    jsonResponse(true, 'Profile updated.');
} catch (PDOException $e) {
    jsonResponse(false, 'Failed to update profile.');
}