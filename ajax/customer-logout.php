<?php
/* =========================================================
   MRS MILL@ — CUSTOMER LOGOUT
   File: ./ajax/customer-logout.php
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

$customerRowId = isset($_SESSION['customer_id']) ? (int)$_SESSION['customer_id'] : 0;

if ($customerRowId > 0) {
    try {
        // Wipe the token from DB — next request with old token will be rejected.
        $pdo->prepare("UPDATE customers SET session_token = NULL WHERE id = ?")
            ->execute([$customerRowId]);
    } catch (PDOException $e) {
        // silent
    }
}

/* Clear only customer session keys */
unset(
    $_SESSION['customer_id'],
    $_SESSION['customer_code'],
    $_SESSION['customer_name'],
    $_SESSION['customer_mobile'],
    $_SESSION['customer_token'],
    $_SESSION['applied_discount'],
    $_SESSION['applied_coupon']
);

/* Optionally destroy entire session */
// session_destroy();

jsonResponse(true, 'Logged out.');