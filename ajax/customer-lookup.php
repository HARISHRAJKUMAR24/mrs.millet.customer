<?php
/* =========================================================
   MRS MILL@ — CUSTOMER LOOKUP
   File: ./ajax/customer-lookup.php
   Reads from `customers` + `customer_address_requests` only.
   Returns everything the JS needs (name, address, wallet,
   pending request status) so the login/register popups
   can decide what to show next.
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

/* Only GET — JS uses fetch() without POST */
$mobile = trim($_GET['mobile'] ?? '');

if (!preg_match('/^[0-9]{10,15}$/', $mobile)) {
    jsonResponse(false, 'Mobile number must be 10–15 digits.');
}

try {

    /* ---------- 1) Pending request check ---------- */
    $hasPendingRequest = false;
    $requestRow = null;

    try {
        $rq = $pdo->prepare(
            "SELECT id, request_type, requested_apartment, requested_division,
                    map_link, house_image, created_at
             FROM customer_address_requests
             WHERE customer_mobile = ? AND status = 0
             ORDER BY id DESC LIMIT 1"
        );
        $rq->execute([$mobile]);
        $requestRow = $rq->fetch(PDO::FETCH_ASSOC);
        if ($requestRow) $hasPendingRequest = true;
    } catch (PDOException $e) {
        /* table missing → no pending request */
        $hasPendingRequest = false;
    }

    /* ---------- 2) Look up the customer ---------- */
    $stmt = $pdo->prepare(
        "SELECT id, customer_id, full_name, mobile_number,
                password_hash, wallet_balance, profile_completed,
                address_type, address_json, status
         FROM customers
         WHERE mobile_number = ?
         LIMIT 1"
    );
    $stmt->execute([$mobile]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    /* ---------- 3) Not registered yet ---------- */
    if (!$customer) {
        jsonResponse(true, 'OK', [
            'exists'              => false,
            'has_password'        => false,
            'has_pending_request' => $hasPendingRequest,
            'pending_request'     => $requestRow ? [
                'id'                  => (int)$requestRow['id'],
                'request_type'        => $requestRow['request_type'],
                'requested_apartment' => $requestRow['requested_apartment'],
                'requested_division'  => $requestRow['requested_division'],
                'map_link'            => $requestRow['map_link'],
                'house_image'         => $requestRow['house_image'],
                'created_at'          => $requestRow['created_at'],
            ] : null,
            'mobile'              => $mobile,
            'name'                => '',
        ]);
    }

    /* ---------- 4) Decode the address JSON ---------- */
    $address = [];
    if (!empty($customer['address_json'])) {
        $decoded = json_decode($customer['address_json'], true);
        if (is_array($decoded)) $address = $decoded;
    }

    /* ---------- 5) Active customer ---------- */
    $isActive = ((int)($customer['status'] ?? 1)) === 1;

    jsonResponse(true, 'OK', [
        'exists'              => $isActive,
        'has_password'        => !empty($customer['password_hash']),
        'has_pending_request' => $hasPendingRequest,
        'pending_request'     => $requestRow ? [
            'id'                  => (int)$requestRow['id'],
            'request_type'        => $requestRow['request_type'],
            'requested_apartment' => $requestRow['requested_apartment'],
            'requested_division'  => $requestRow['requested_division'],
            'map_link'            => $requestRow['map_link'],
            'house_image'         => $requestRow['house_image'],
            'created_at'          => $requestRow['created_at'],
        ] : null,
        'id'                  => (int)$customer['id'],
        'customer_id'         => $customer['customer_id'],
        'name'                => $customer['full_name'],
        'mobile'              => $customer['mobile_number'],
        'wallet_balance'      => (float)$customer['wallet_balance'],
        'profile_completed'   => (int)$customer['profile_completed'] === 1,
        'address_type'        => $customer['address_type'] ?: 'apartment',
        /* The JS will look at `address.apartment_id`, `address.full_address` etc. */
        'address'             => $address,
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Lookup failed: ' . $e->getMessage());
}