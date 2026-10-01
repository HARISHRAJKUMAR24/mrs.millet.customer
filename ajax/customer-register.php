<?php
/* =========================================================
   MRS MILL@ — CUSTOMER REGISTER
   File: ./ajax/customer-register.php
   Accepts: full_name, mobile_number, password, apartment_id, division
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$name        = trim($_POST['full_name'] ?? '');
$mobile      = trim($_POST['mobile_number'] ?? '');
$password    = trim($_POST['password'] ?? '');
$apartmentId = (int)($_POST['apartment_id'] ?? 0);
$division    = trim($_POST['division'] ?? '');

/* Validation */
if ($name === '' || mb_strlen($name) < 3) {
    jsonResponse(false, 'Name must be at least 3 characters.');
}
if (!preg_match('/^[0-9]{10,15}$/', $mobile)) {
    jsonResponse(false, 'Mobile number must be 10–15 digits.');
}
if (strlen($password) < 3) {
    jsonResponse(false, 'Password must be at least 3 characters.');
}
if ($apartmentId <= 0) {
    jsonResponse(false, 'Please select an apartment.');
}
if ($division === '') {
    jsonResponse(false, 'Please select a division.');
}

try {
    /* Verify apartment + division */
    $aStmt = $pdo->prepare(
        "SELECT id, apartment_code, apartment_name, divisions
         FROM apartments WHERE id = ? AND status = 1 LIMIT 1"
    );
    $aStmt->execute([$apartmentId]);
    $apt = $aStmt->fetch(PDO::FETCH_ASSOC);
    if (!$apt) jsonResponse(false, 'Apartment not found.');

    $aptCode = $apt['apartment_code'];
    $aptName = $apt['apartment_name'];

    $divs = json_decode($apt['divisions'] ?? '[]', true);
    $matchedCharge = null;
    if (is_array($divs)) {
        foreach ($divs as $d) {
            if (strcasecmp(trim($d['division'] ?? ''), $division) === 0) {
                $matchedCharge = (float)($d['charge'] ?? 0);
                break;
            }
        }
    }
    if ($matchedCharge === null) {
        jsonResponse(false, 'Division is not valid for this apartment.');
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    /* Check if exists */
    $chk = $pdo->prepare("SELECT id FROM customers WHERE mobile_number = ? LIMIT 1");
    $chk->execute([$mobile]);
    $existing = $chk->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        /* Update existing record */
        $upd = $pdo->prepare(
            "UPDATE customers
             SET full_name = ?, password_hash = ?,
                 apartment_id = ?, apartment_code = ?, apartment_name = ?,
                 division = ?, division_charge = ?,
                 profile_completed = 1, last_login_at = NOW()
             WHERE id = ?"
        );
        $upd->execute([
            $name, $passwordHash,
            $apartmentId, $aptCode, $aptName,
            $division, $matchedCharge,
            $existing['id']
        ]);
        $newId = (int)$existing['id'];
    } else {
        /* Insert new */
        $ins = $pdo->prepare(
            "INSERT INTO customers
                (full_name, mobile_number, password_hash,
                 apartment_id, apartment_code, apartment_name,
                 division, division_charge,
                 profile_completed, last_login_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())"
        );
        $ins->execute([
            $name, $mobile, $passwordHash,
            $apartmentId, $aptCode, $aptName,
            $division, $matchedCharge
        ]);
        $newId = (int)$pdo->lastInsertId();
    }

    /* Set session */
    $_SESSION['customer_id']     = $newId;
    $_SESSION['customer_name']   = $name;
    $_SESSION['customer_mobile'] = $mobile;

    jsonResponse(true, 'Account created successfully!', [
        'id'     => $newId,
        'name'   => $name,
        'mobile' => $mobile
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Server error. Please try again.');
}