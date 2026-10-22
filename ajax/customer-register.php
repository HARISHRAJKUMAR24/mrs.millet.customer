<?php
/* =========================================================
   MRS MILL@ — CUSTOMER REGISTER
   File: ./ajax/customer-register.php
   Schema (per mrs_millet):
     customers: id, customer_id, full_name, mobile_number,
                address_type, address_json, password_hash,
                wallet_balance, profile_completed, status, ...
   Address is stored as JSON — NOT flat columns.
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$name        = trim($_POST['full_name']     ?? '');
$mobile      = trim($_POST['mobile_number'] ?? '');
$password    = trim($_POST['password']      ?? '');
$reqType     = trim($_POST['request_type']  ?? '');

$apartmentId = (int)($_POST['apartment_id']  ?? 0);
$division    = trim($_POST['division']       ?? '');
$customApt   = trim($_POST['custom_apartment'] ?? '');
$customDiv   = trim($_POST['custom_division']  ?? '');

$houseAddr   = trim($_POST['house_address']  ?? '');
$mapLink     = trim($_POST['map_link']       ?? '');
$houseImgB64 = trim($_POST['house_image_data'] ?? '');

/* ---------- Shared validation ---------- */
if ($name === '' || mb_strlen($name) < 3) {
    jsonResponse(false, 'Name must be at least 3 characters.');
}
if (!preg_match('/^[0-9]{10,15}$/', $mobile)) {
    jsonResponse(false, 'Mobile number must be 10–15 digits.');
}

/* =========================================================
   INDIVIDUAL HOUSE — stored as a customer_address_request
   (staff approves → row is moved into `customers` manually)
   ========================================================= */
if ($reqType === 'individual_house') {

    if ($houseAddr === '') jsonResponse(false, 'Please enter your full address.');
    if ($mapLink === '')   jsonResponse(false, 'Please pin your location.');

    /* Save image (base64) */
    $savedImagePath = '';
    if ($houseImgB64 !== '') {
        if (preg_match('/^data:image\/(png|jpe?g|webp|gif);base64,/i', $houseImgB64, $m)) {
            $ext  = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
            $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $houseImgB64));

            if ($data !== false && strlen($data) <= 3 * 1024 * 1024) {
                $folder = 'uploads/houses/' . date('Ymd') . '/' . bin2hex(random_bytes(5));
                $absDir = rtrim(__DIR__ . '/../', '/\\') . '/' . $folder;
                if (!is_dir($absDir)) @mkdir($absDir, 0775, true);

                $fileName = 'house.' . $ext;
                if (@file_put_contents($absDir . '/' . $fileName, $data) !== false) {
                    $savedImagePath = $folder . '/' . $fileName;
                }
            }
        }
    }

    try {
        $chk = $pdo->prepare(
            "SELECT id FROM customer_address_requests
             WHERE customer_mobile = ? AND status = 0
             ORDER BY id DESC LIMIT 1"
        );
        $chk->execute([$mobile]);
        $existing = $chk->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            jsonResponse(true, 'You have already sent a request. Our team will contact you soon.', [
                'request_only'      => true,
                'already_requested' => true,
                'request_id'        => (int)$existing['id']
            ]);
        }

        $ins = $pdo->prepare(
            "INSERT INTO customer_address_requests
                (customer_name, customer_mobile, requested_apartment,
                 requested_division, request_type, map_link, house_image)
             VALUES (?, ?, ?, '', 'individual_house', ?, ?)"
        );
        $ins->execute([$name, $mobile, $houseAddr, $mapLink, $savedImagePath]);

        jsonResponse(true, 'Request submitted successfully.', [
            'request_only'      => true,
            'already_requested' => false,
            'request_id'        => (int)$pdo->lastInsertId()
        ]);
    } catch (PDOException $e) {
        jsonResponse(false, 'Server error: ' . $e->getMessage());
    }
}

/* =========================================================
   APARTMENT MODE
   ========================================================= */
$isCustom = ($customApt !== '');

if (!$isCustom) {
    if (strlen($password) < 3) jsonResponse(false, 'Password must be at least 3 characters.');
    if ($apartmentId <= 0)     jsonResponse(false, 'Please select an apartment.');
    if ($division === '')      jsonResponse(false, 'Please select Block / Door / Floor.');
} else {
    if ($customDiv === '')     jsonResponse(false, 'Please type your division.');
}

try {

    /* ---------- Custom apartment request ---------- */
    if ($isCustom) {
        $chk = $pdo->prepare(
            "SELECT id, requested_apartment, requested_division
             FROM customer_address_requests
             WHERE customer_mobile = ? AND status = 0
             ORDER BY id DESC LIMIT 1"
        );
        $chk->execute([$mobile]);
        $existing = $chk->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            jsonResponse(true, 'You have already sent a request. Our team will contact you soon.', [
                'request_only'        => true,
                'already_requested'   => true,
                'request_id'          => (int)$existing['id'],
                'requested_apartment' => $existing['requested_apartment'],
                'requested_division'  => $existing['requested_division']
            ]);
        }

        $ins = $pdo->prepare(
            "INSERT INTO customer_address_requests
                (customer_name, customer_mobile, requested_apartment,
                 requested_division, request_type)
             VALUES (?, ?, ?, ?, 'apartment')"
        );
        $ins->execute([$name, $mobile, $customApt, $customDiv]);

        jsonResponse(true, 'Request submitted successfully.', [
            'request_only'      => true,
            'already_requested' => false,
            'request_id'        => (int)$pdo->lastInsertId()
        ]);
    }

    /* ---------- Normal apartment registration ---------- */
    $aStmt = $pdo->prepare(
        "SELECT id, apartment_code, apartment_name, divisions
         FROM apartments WHERE id = ? AND status = 1 LIMIT 1"
    );
    $aStmt->execute([$apartmentId]);
    $apt = $aStmt->fetch(PDO::FETCH_ASSOC);
    if (!$apt) jsonResponse(false, 'Apartment not found.');

    $aptCode = $apt['apartment_code'];
    $aptName = $apt['apartment_name'];

    /* Match the division */
    $divs = json_decode($apt['divisions'] ?? '[]', true);
    $matchedCharge = null;
    $block = ''; $doorNo = ''; $floor = '';

    if (is_array($divs)) {
        foreach ($divs as $d) {
            $parts = [];
            if (!empty($d['block']))   $parts[] = "Blk " . $d['block'];
            if (!empty($d['door_no'])) $parts[] = "Door " . $d['door_no'];
            if (!empty($d['floor']))   $parts[] = "Fl " . $d['floor'];
            $label = $parts ? implode(" / ", $parts) : (string)($d['division'] ?? "");

            if (strcasecmp(trim($label), $division) === 0 ||
                strcasecmp(trim((string)($d['division'] ?? '')), $division) === 0) {
                $matchedCharge = (float)($d['charge'] ?? 0);
                $block  = (string)($d['block']   ?? '');
                $doorNo = (string)($d['door_no'] ?? '');
                $floor  = (string)($d['floor']   ?? '');
                break;
            }
        }
    }
    if ($matchedCharge === null) {
        jsonResponse(false, 'Selected Block / Door / Floor is not valid for this apartment.');
    }

    /* Build the address JSON (same shape order flow already uses) */
    $addressJson = [
        'apartment_id'   => $apartmentId,
        'apartment_code' => $aptCode,
        'apartment_name' => $aptName,
        'block'          => $block,
        'door_no'        => $doorNo,
        'floor'          => $floor,
        'division_label' => $division,
        'charge'         => $matchedCharge,
    ];
    $addressJsonFinal = json_encode($addressJson, JSON_UNESCAPED_UNICODE);

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $chk = $pdo->prepare("SELECT id FROM customers WHERE mobile_number = ? LIMIT 1");
    $chk->execute([$mobile]);
    $existingCustomer = $chk->fetch(PDO::FETCH_ASSOC);

    if ($existingCustomer) {
        $upd = $pdo->prepare(
            "UPDATE customers
             SET full_name = ?,
                 password_hash = ?,
                 address_type = 'apartment',
                 address_json = ?,
                 profile_completed = 1,
                 last_login_at = NOW()
             WHERE id = ?"
        );
        $upd->execute([
            $name,
            $passwordHash,
            $addressJsonFinal,
            (int)$existingCustomer['id']
        ]);
        $newId = (int)$existingCustomer['id'];
    } else {
        /* Generate customer_id (MRSMCxxxxx) */
        $customerCode = 'MRSMC' . random_int(10000, 99999);

        $ins = $pdo->prepare(
            "INSERT INTO customers
                (customer_id, full_name, mobile_number,
                 password_hash, address_type, address_json,
                 profile_completed, last_login_at)
             VALUES (?, ?, ?, ?, 'apartment', ?, 1, NOW())"
        );
        $ins->execute([
            $customerCode,
            $name,
            $mobile,
            $passwordHash,
            $addressJsonFinal
        ]);
        $newId = (int)$pdo->lastInsertId();
    }

    $_SESSION['customer_id']     = $newId;
    $_SESSION['customer_name']   = $name;
    $_SESSION['customer_mobile'] = $mobile;

    jsonResponse(true, 'Account created successfully!', [
        'id'                => $newId,
        'name'              => $name,
        'mobile'            => $mobile,
        'address_type'      => 'apartment',
        'address'           => $addressJson,
        'request_only'      => false,
        'already_requested' => false
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Server error: ' . $e->getMessage());
}