<?php
/* =========================================================
   MRS MILL@ — CUSTOMER REGISTER (with referral)  v2026-11-03e
   File: ./ajax/customer-register.php
   - Referrals saved only for real customers.
   - Request-only signups store password_hash on the request row.
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$name        = trim($_POST['full_name']        ?? '');
$mobile      = trim($_POST['mobile_number']    ?? '');
$password    = trim($_POST['password']         ?? '');
$reqType     = trim($_POST['request_type']     ?? '');
$referrer    = trim($_POST['referrer']         ?? '');

$apartmentId = (int)($_POST['apartment_id']    ?? 0);
$division    = trim($_POST['division']         ?? '');
$customApt   = trim($_POST['custom_apartment'] ?? '');
$customDiv   = trim($_POST['custom_division']  ?? '');

$houseAddr   = trim($_POST['house_address']    ?? '');
$mapLink     = trim($_POST['map_link']         ?? '');
$houseImgB64 = trim($_POST['house_image_data'] ?? '');

if ($name === '' || mb_strlen($name) < 3) {
    jsonResponse(false, 'Name must be at least 3 characters.');
}
if (!preg_match('/^[0-9]{10,15}$/', $mobile)) {
    jsonResponse(false, 'Mobile number must be 10–15 digits.');
}
if (strlen($password) < 3) {
    jsonResponse(false, 'Password must be at least 3 characters.');
}

/* =========================================================
   RESOLVE REFERRER
   ========================================================= */
function resolveReferrer(PDO $pdo, string $referrer): array
{
    if ($referrer === '') {
        return ['customer_id' => null, 'name' => null, 'mobile' => null];
    }
    $clean = preg_replace('/\D+/', '', $referrer);

    if ($clean !== '' && strlen($clean) >= 10 && strlen($clean) <= 15) {
        try {
            $st = $pdo->prepare("SELECT id FROM customers WHERE mobile_number = ? LIMIT 1");
            $st->execute([$clean]);
            $cid = $st->fetchColumn();
            return ['customer_id' => $cid ? (int)$cid : null, 'name' => null, 'mobile' => $clean];
        } catch (PDOException $e) {
            return ['customer_id' => null, 'name' => null, 'mobile' => $clean];
        }
    }
    try {
        $st = $pdo->prepare("SELECT id FROM customers WHERE full_name = ? LIMIT 1");
        $st->execute([$referrer]);
        $cid = $st->fetchColumn();
        return ['customer_id' => $cid ? (int)$cid : null, 'name' => $referrer, 'mobile' => null];
    } catch (PDOException $e) {
        return ['customer_id' => null, 'name' => $referrer, 'mobile' => null];
    }
}

/* =========================================================
   SAVE REFERRAL — real customers only (customerId > 0)
   ========================================================= */
function saveReferral(PDO $pdo, int $customerId, string $name, string $mobile, string $referrer): int
{
    if ($customerId <= 0 || $referrer === '') return 0;
    $ref = resolveReferrer($pdo, $referrer);

    try {
        $ins = $pdo->prepare(
            "INSERT INTO customer_referrals
                (customer_id, customer_name, customer_mobile,
                 referrer_name, referrer_mobile, referrer_customer_id, status)
             VALUES (?, ?, ?, ?, ?, ?, 1)"
        );
        $ins->execute([
            $customerId,
            $name,
            $mobile,
            $ref['name'],
            $ref['mobile'],
            $ref['customer_id']
        ]);
        $referralId = (int)$pdo->lastInsertId();

        $referredById = $ref['customer_id'] ?: $referralId;
        $pdo->prepare("UPDATE customers SET referred_by_id = ? WHERE id = ?")
            ->execute([$referredById, $customerId]);

        return $referralId;
    } catch (PDOException $e) {
        error_log('saveReferral failed: ' . $e->getMessage());
        return 0;
    }
}

/* =========================================================
   INDIVIDUAL HOUSE  →  request only (+ password + referral note)
   ========================================================= */
if ($reqType === 'individual_house') {

    if ($houseAddr === '') jsonResponse(false, 'Please enter your full address.');
    if ($mapLink === '')   jsonResponse(false, 'Please pin your location.');

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

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $ins = $pdo->prepare(
            "INSERT INTO customer_address_requests
                (customer_name, customer_mobile, password_hash,
                 requested_apartment, requested_division,
                 request_type, map_link, house_image)
             VALUES (?, ?, ?, ?, '', 'individual_house', ?, ?)"
        );
        $ins->execute([$name, $mobile, $passwordHash, $houseAddr, $mapLink, $savedImagePath]);
        $requestId = (int)$pdo->lastInsertId();

        jsonResponse(true, 'Request submitted successfully.', [
            'request_only'      => true,
            'already_requested' => false,
            'request_id'        => $requestId
        ]);
    } catch (PDOException $e) {
        error_log('house register failed: ' . $e->getMessage());
        jsonResponse(false, 'Server error: ' . $e->getMessage());
    }
}

/* =========================================================
   APARTMENT MODE
   ========================================================= */
$isCustom = ($customApt !== '');

try {

    /* ---------- CUSTOM APARTMENT (request only, store password) ---------- */
    if ($isCustom) {
        if ($customDiv === '') jsonResponse(false, 'Please type your division.');

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

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $ins = $pdo->prepare(
            "INSERT INTO customer_address_requests
                (customer_name, customer_mobile, password_hash,
                 requested_apartment, requested_division, request_type)
             VALUES (?, ?, ?, ?, ?, 'apartment')"
        );
        $ins->execute([$name, $mobile, $passwordHash, $customApt, $customDiv]);
        $requestId = (int)$pdo->lastInsertId();

        jsonResponse(true, 'Request submitted successfully.', [
            'request_only'      => true,
            'already_requested' => false,
            'request_id'        => $requestId
        ]);
    }

    /* ---------- NORMAL APARTMENT (real customer) ---------- */
    if ($apartmentId <= 0) jsonResponse(false, 'Please select an apartment.');
    if ($division === '')  jsonResponse(false, 'Please select Block / Door / Floor.');

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
    $block = '';
    $doorNo = '';
    $floor = '';

    if (is_array($divs)) {
        foreach ($divs as $d) {
            $parts = [];
            if (!empty($d['block']))   $parts[] = "Blk " . $d['block'];
            if (!empty($d['door_no'])) $parts[] = "Door " . $d['door_no'];
            if (!empty($d['floor']))   $parts[] = "Fl " . $d['floor'];
            $label = $parts ? implode(" / ", $parts) : (string)($d['division'] ?? "");

            if (
                strcasecmp(trim($label), $division) === 0 ||
                strcasecmp(trim((string)($d['division'] ?? '')), $division) === 0
            ) {
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
    $sessionToken = bin2hex(random_bytes(32));

    $chk = $pdo->prepare("SELECT id FROM customers WHERE mobile_number = ? LIMIT 1");
    $chk->execute([$mobile]);
    $existingCustomer = $chk->fetch(PDO::FETCH_ASSOC);

    if ($existingCustomer) {
        $upd = $pdo->prepare(
            "UPDATE customers
             SET full_name = ?, password_hash = ?, session_token = ?,
                 address_type = 'apartment', address_json = ?,
                 profile_completed = 1, last_login_at = NOW()
             WHERE id = ?"
        );
        $upd->execute([
            $name,
            $passwordHash,
            $sessionToken,
            $addressJsonFinal,
            (int)$existingCustomer['id']
        ]);
        $newId = (int)$existingCustomer['id'];
    } else {
        $customerCode = 'MRSMC' . random_int(10000, 99999);

        $ins = $pdo->prepare(
            "INSERT INTO customers
                (customer_id, full_name, mobile_number,
                 password_hash, session_token,
                 address_type, address_json,
                 profile_completed, last_login_at)
             VALUES (?, ?, ?, ?, ?, 'apartment', ?, 1, NOW())"
        );
        $ins->execute([
            $customerCode,
            $name,
            $mobile,
            $passwordHash,
            $sessionToken,
            $addressJsonFinal
        ]);
        $newId = (int)$pdo->lastInsertId();
    }

    $referralId = 0;
    if ($referrer !== '') {
        $referralId = saveReferral($pdo, $newId, $name, $mobile, $referrer);
    }

    $_SESSION['customer_id']     = $newId;
    $_SESSION['customer_name']   = $name;
    $_SESSION['customer_mobile'] = $mobile;
    $_SESSION['customer_token']  = $sessionToken;

    jsonResponse(true, 'Account created successfully!', [
        'id'                => $newId,
        'name'              => $name,
        'mobile'            => $mobile,
        'address_type'      => 'apartment',
        'address'           => $addressJson,
        'request_only'      => false,
        'already_requested' => false,
        'referral_id'       => $referralId,
        'referrer_received' => $referrer
    ]);
} catch (PDOException $e) {
    error_log('register failed: ' . $e->getMessage());
    jsonResponse(false, 'Server error: ' . $e->getMessage());
}
