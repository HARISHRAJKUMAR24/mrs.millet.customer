<?php
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

/* Apartment inputs */
$apartmentId   = (int)($_POST['apartment_id']   ?? 0);
$apartmentName = trim($_POST['apartment_name']  ?? '');
$divisionText  = trim($_POST['division']        ?? '');
$blockIn       = trim($_POST['block']           ?? '');
$floorIn       = trim($_POST['floor']           ?? '');
$doorIn        = trim($_POST['door_no']         ?? '');
$customApt     = trim($_POST['custom_apartment'] ?? '');
$customDiv     = trim($_POST['custom_division']  ?? '');

/* House inputs */
$houseAddr   = trim($_POST['house_address']    ?? '');
$mapLink     = trim($_POST['map_link']         ?? '');
$houseImgB64 = trim($_POST['house_image_data'] ?? '');
$landmark    = trim($_POST['landmark']         ?? '');

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
   IMAGE UPLOAD HELPER
   ========================================================= */
function saveBase64Image($base64String) {
    if ($base64String === '') return '';
    if (preg_match('/^data:image\/(png|jpe?g|webp|gif);base64,/i', $base64String, $m)) {
        $ext  = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
        $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64String));

        if ($data !== false && strlen($data) <= 3 * 1024 * 1024) {
            $folder = 'uploads/houses/' . date('Ymd') . '/' . bin2hex(random_bytes(5));
            $absDir = rtrim(__DIR__ . '/../', '/\\') . '/' . $folder;
            if (!is_dir($absDir)) @mkdir($absDir, 0775, true);

            $fileName = 'house.' . $ext;
            if (@file_put_contents($absDir . '/' . $fileName, $data) !== false) {
                return $folder . '/' . $fileName;
            }
        }
    }
    return '';
}

/* =========================================================
   REFERRAL HELPERS
   ========================================================= */
function resolveReferrer(PDO $pdo, string $referrer): array
{
    if ($referrer === '') return ['customer_id' => null, 'name' => null, 'mobile' => null];
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
        $ins->execute([$customerId, $name, $mobile, $ref['name'], $ref['mobile'], $ref['customer_id']]);
        $referralId = (int)$pdo->lastInsertId();
        $referredById = $ref['customer_id'] ?: $referralId;
        $pdo->prepare("UPDATE customers SET referred_by_id = ? WHERE id = ?")->execute([$referredById, $customerId]);
        return $referralId;
    } catch (PDOException $e) {
        return 0;
    }
}

/* =========================================================
   INDIVIDUAL HOUSE (unchanged)
   ========================================================= */
if ($reqType === 'individual_house') {
    if ($houseAddr === '') jsonResponse(false, 'Please enter your full address.');

    $savedImagePath = saveBase64Image($houseImgB64);

    try {
        $chk = $pdo->prepare("SELECT id FROM customer_address_requests WHERE customer_mobile = ? AND status = 0 ORDER BY id DESC LIMIT 1");
        $chk->execute([$mobile]);
        $existing = $chk->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            jsonResponse(true, 'You have already sent a request. Our team will contact you soon.', [
                'request_only' => true, 'already_requested' => true, 'request_id' => (int)$existing['id']
            ]);
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $fullAddr = $houseAddr;
        if ($landmark !== '') $fullAddr .= " (Landmark: " . $landmark . ")";

        $ins = $pdo->prepare(
            "INSERT INTO customer_address_requests
                (customer_name, customer_mobile, password_hash,
                 requested_apartment, requested_division,
                 request_type, map_link, house_image)
             VALUES (?, ?, ?, ?, '', 'individual_house', ?, ?)"
        );
        $ins->execute([$name, $mobile, $passwordHash, $fullAddr, $mapLink, $savedImagePath]);
        $requestId = (int)$pdo->lastInsertId();

        jsonResponse(true, 'Request submitted successfully.', [
            'request_only' => true, 'already_requested' => false, 'request_id' => $requestId
        ]);
    } catch (PDOException $e) {
        jsonResponse(false, 'Server error: ' . $e->getMessage());
    }
}

/* =========================================================
   APARTMENT MODE
   ========================================================= */

/* Resolve apartment name typed by the user */
$typedName = '';
if ($customApt !== '') {
    $typedName = $customApt;
} elseif ($apartmentName !== '') {
    $typedName = $apartmentName;
} elseif ($apartmentId > 0) {
    try {
        $st = $pdo->prepare("SELECT apartment_name FROM apartments WHERE id = ? LIMIT 1");
        $st->execute([$apartmentId]);
        $typedName = (string)$st->fetchColumn();
    } catch (PDOException $e) {}
}

$typedName = trim($typedName);
if ($typedName === '') {
    jsonResponse(false, 'Please type your apartment name.');
}

/* Pull block/floor/door from either direct POST or the division text */
$block  = $blockIn;
$floor  = $floorIn;
$doorNo = $doorIn;

if ($block === '' && $floor === '' && $doorNo === '' && $divisionText !== '') {
    foreach (explode('/', $divisionText) as $seg) {
        $seg = trim($seg);
        if (preg_match('/^Blk\s+(.+)$/i', $seg, $m))       $block  = trim($m[1]);
        elseif (preg_match('/^Door\s+(.+)$/i', $seg, $m))  $doorNo = trim($m[1]);
        elseif (preg_match('/^Fl\s+(.+)$/i', $seg, $m))    $floor  = trim($m[1]);
    }
}

$divisionLabel = $divisionText;
if ($divisionLabel === '') {
    $parts = [];
    if ($block  !== '') $parts[] = "Blk "  . $block;
    if ($doorNo !== '') $parts[] = "Door " . $doorNo;
    if ($floor  !== '') $parts[] = "Fl "   . $floor;
    $divisionLabel = implode(' / ', $parts);
}

$savedImagePath = saveBase64Image($houseImgB64);

try {

    /* ---------- Look up apartment by NAME ---------- */
    $apt = null;
    try {
        $aStmt = $pdo->prepare(
            "SELECT id, apartment_code, apartment_name, divisions
             FROM apartments
             WHERE LOWER(TRIM(apartment_name)) = LOWER(TRIM(?))
             LIMIT 1"
        );
        $aStmt->execute([$typedName]);
        $apt = $aStmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}

    /* =========================================================
       CASE A — Apartment EXISTS  →  create customer
       ========================================================= */
    if ($apt) {
        $aptId   = (int)$apt['id'];
        $aptCode = $apt['apartment_code'];
        $aptName = $apt['apartment_name'];

        $divs = json_decode($apt['divisions'] ?? '[]', true);
        if (!is_array($divs)) $divs = [];

        /* Try to find matching division */
        $matchedCharge = null;
        $matchedBlock  = $block;
        $matchedFloor  = $floor;
        $matchedDoor   = $doorNo;

        foreach ($divs as $d) {
            $dBlock  = (string)($d['block']   ?? '');
            $dFloor  = (string)($d['floor']   ?? '');
            $dDoor   = (string)($d['door_no'] ?? '');

            $blockOk = ($dBlock !== '' && strcasecmp($dBlock, $block)  === 0);
            $floorOk = ($dFloor !== '' && strcasecmp($dFloor, $floor)  === 0);
            $doorOk  = ($dDoor  !== '' && strcasecmp($dDoor,  $doorNo) === 0);

            $partsMatch =
                ($block  === '' ? $dBlock  === '' : $blockOk) &&
                ($floor  === '' ? $dFloor  === '' : $floorOk) &&
                ($doorNo === '' ? $dDoor   === '' : $doorOk);

            if ($partsMatch) {
                $matchedCharge = (float)($d['charge'] ?? 0);
                $matchedBlock  = $dBlock;
                $matchedFloor  = $dFloor;
                $matchedDoor   = $dDoor;
                break;
            }
        }

        /* If division NOT found → append a NEW one
           ONLY when at least one of block/floor/door is provided.
           The new division inherits the LAST non-zero charge
           already used in this apartment (falls back to 0). */
        $newDivisionAdded = false;
        $hasAnyPart = ($block !== '' || $floor !== '' || $doorNo !== '');

        if ($matchedCharge === null) {

            /* Find the last non-zero charge among existing divisions */
            $lastKnownCharge = 0;
            foreach ($divs as $d) {
                $c = (float)($d['charge'] ?? 0);
                if ($c > 0) $lastKnownCharge = $c;
            }

            if ($hasAnyPart) {
                $divs[] = [
                    'block'   => $block,
                    'floor'   => $floor,
                    'door_no' => $doorNo,
                    'charge'  => $lastKnownCharge
                ];
                $newDivJson = json_encode($divs, JSON_UNESCAPED_UNICODE);
                try {
                    $u = $pdo->prepare("UPDATE apartments SET divisions = ? WHERE id = ?");
                    $u->execute([$newDivJson, $aptId]);
                    $newDivisionAdded = true;
                } catch (PDOException $e) { /* ignore */ }
            }

            $matchedCharge = $lastKnownCharge;
            $matchedBlock  = $block;
            $matchedFloor  = $floor;
            $matchedDoor   = $doorNo;
        }

        /* Build address_json for the customer */
        $addressJson = [
            'apartment_id'   => $aptId,
            'apartment_code' => $aptCode,
            'apartment_name' => $aptName,
            'block'          => $matchedBlock,
            'door_no'        => $matchedDoor,
            'floor'          => $matchedFloor,
            'division_label' => $divisionLabel,
            'charge'         => $matchedCharge,
            'landmark'       => $landmark,
            'map_link'       => $mapLink,
            'house_image'    => $savedImagePath
        ];
        $addressJsonFinal = json_encode($addressJson, JSON_UNESCAPED_UNICODE);

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $sessionToken = bin2hex(random_bytes(32));

        /* Upsert customer */
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
            $upd->execute([$name, $passwordHash, $sessionToken, $addressJsonFinal, (int)$existingCustomer['id']]);
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
            $ins->execute([$customerCode, $name, $mobile, $passwordHash, $sessionToken, $addressJsonFinal]);
            $newId = (int)$pdo->lastInsertId();
        }

        /* Referral */
        $referralId = 0;
        if ($referrer !== '') {
            $referralId = saveReferral($pdo, $newId, $name, $mobile, $referrer);
        }

        $_SESSION['customer_id']     = $newId;
        $_SESSION['customer_name']   = $name;
        $_SESSION['customer_mobile'] = $mobile;
        $_SESSION['customer_token']  = $sessionToken;

        jsonResponse(true, 'Account created successfully!', [
            'id'                 => $newId,
            'name'               => $name,
            'mobile'             => $mobile,
            'address_type'       => 'apartment',
            'address'            => $addressJson,
            'request_only'       => false,
            'already_requested'  => false,
            'new_division_added' => $newDivisionAdded,
            'referral_id'        => $referralId,
            'referrer_received'  => $referrer
        ]);
    }

    /* =========================================================
       CASE B — Apartment NOT in DB  →  save as request
       ========================================================= */
    $chk = $pdo->prepare("SELECT id FROM customer_address_requests WHERE customer_mobile = ? AND status = 0 ORDER BY id DESC LIMIT 1");
    $chk->execute([$mobile]);
    $existing = $chk->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        jsonResponse(true, 'You have already sent a request. Our team will contact you soon.', [
            'request_only' => true, 'already_requested' => true, 'request_id' => (int)$existing['id']
        ]);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $reqDivision = $divisionLabel;
    if ($landmark !== '') {
        $reqDivision .= ($reqDivision !== '' ? ' ' : '') . "(Landmark: " . $landmark . ")";
    }

    $ins = $pdo->prepare(
        "INSERT INTO customer_address_requests
            (customer_name, customer_mobile, password_hash,
             requested_apartment, requested_division,
             request_type, map_link, house_image)
         VALUES (?, ?, ?, ?, ?, 'apartment', ?, ?)"
    );
    $ins->execute([$name, $mobile, $passwordHash, $typedName, $reqDivision, $mapLink, $savedImagePath]);
    $requestId = (int)$pdo->lastInsertId();

    jsonResponse(true, 'Request submitted successfully.', [
        'request_only'       => true,
        'already_requested'  => false,
        'request_id'         => $requestId
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Server error: ' . $e->getMessage());
}