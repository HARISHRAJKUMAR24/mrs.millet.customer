<?php
/* =========================================================
   MRS MILL@ — CUSTOMER REGISTER
   File: ./ajax/customer-register.php

   Two modes:
   1) Normal : apartment_id + division
               → creates/updates customer, logs in
   2) Custom : custom_apartment + custom_division
               → checks duplicate by mobile
               → saves ONE row in customer_address_requests
               → does NOT create customer, does NOT log in
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

/* Normal path */
$apartmentId = (int)($_POST['apartment_id'] ?? 0);
$division    = trim($_POST['division'] ?? '');

/* Custom request path */
$customApartment = trim($_POST['custom_apartment'] ?? '');
$customDivision  = trim($_POST['custom_division'] ?? '');

/* ---------- Validation ---------- */
if ($name === '' || mb_strlen($name) < 3) {
    jsonResponse(false, 'Name must be at least 3 characters.');
}
if (!preg_match('/^[0-9]{10,15}$/', $mobile)) {
    jsonResponse(false, 'Mobile number must be 10–15 digits.');
}
if (strlen($password) < 3) {
    jsonResponse(false, 'Password must be at least 3 characters.');
}

$isCustom = ($customApartment !== '');

if (!$isCustom) {
    if ($apartmentId <= 0) jsonResponse(false, 'Please select an apartment.');
    if ($division === '')  jsonResponse(false, 'Please select a division.');
} else {
    if ($customDivision === '') jsonResponse(false, 'Please type your division.');
}

try {
    /* =========================================================
       CUSTOM REQUEST MODE
       ========================================================= */
    if ($isCustom) {

        /* --- 1. Duplicate check by mobile --- */
        $chk = $pdo->prepare(
            "SELECT id, requested_apartment, requested_division, created_at
             FROM customer_address_requests
             WHERE customer_mobile = ?
             ORDER BY id DESC
             LIMIT 1"
        );
        $chk->execute([$mobile]);
        $existing = $chk->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            /* Already has a pending request → do NOT insert again */
            jsonResponse(true, 'You have already sent a request. Our team will contact you soon.', [
                'request_only'      => true,
                'already_requested' => true,
                'request_id'        => (int)$existing['id'],
                'requested_apartment' => $existing['requested_apartment'],
                'requested_division'  => $existing['requested_division']
            ]);
        }

        /* --- 2. Insert new request row --- */
        $reqStmt = $pdo->prepare(
            "INSERT INTO customer_address_requests
                (customer_name, customer_mobile, requested_apartment, requested_division)
             VALUES (?, ?, ?, ?)"
        );
        $reqStmt->execute([$name, $mobile, $customApartment, $customDivision]);

        jsonResponse(true, 'Request submitted successfully.', [
            'request_only'      => true,
            'already_requested' => false,
            'request_id'        => (int)$pdo->lastInsertId()
        ]);
    }

    /* =========================================================
       NORMAL MODE — existing behaviour
       ========================================================= */
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

    /* Check if customer exists */
    $chk = $pdo->prepare("SELECT id FROM customers WHERE mobile_number = ? LIMIT 1");
    $chk->execute([$mobile]);
    $existingCustomer = $chk->fetch(PDO::FETCH_ASSOC);

    if ($existingCustomer) {
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
            $existingCustomer['id']
        ]);
        $newId = (int)$existingCustomer['id'];
    } else {
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
        'id'                => $newId,
        'name'              => $name,
        'mobile'            => $mobile,
        'request_only'      => false,
        'already_requested' => false
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Server error: ' . $e->getMessage());
}