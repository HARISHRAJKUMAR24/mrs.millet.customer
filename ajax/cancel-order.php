<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

/* 1. Check Login */
if (!isset($_SESSION['customer_id']) || (int)$_SESSION['customer_id'] <= 0) {
    jsonResponse(false, 'Please log in to cancel the order.');
}

$customerRowId = (int)$_SESSION['customer_id'];
$orderId       = (int)($_POST['order_id'] ?? 0);
$reason        = trim($_POST['reason'] ?? 'Cancelled by customer');

if ($orderId <= 0) {
    jsonResponse(false, 'Invalid order ID.');
}

try {
    /* 2. Fetch order */
    $stmt = $pdo->prepare("
        SELECT o.*, c.id AS cust_id, c.customer_id AS cust_code
        FROM orders o
        JOIN customers c ON c.customer_id = o.customer_id
        WHERE o.id = ? AND c.id = ? LIMIT 1
    ");
    $stmt->execute([$orderId, $customerRowId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        jsonResponse(false, 'Order not found or does not belong to you.');
    }

    /* 3. Reject already-cancelled / delivered */
    if ($order['status'] === 'cancelled') {
        jsonResponse(false, 'This order is already cancelled.');
    }
    if ($order['status'] === 'delivered' || $order['delivery_status'] === 'enabled') {
        jsonResponse(false, 'Delivered orders cannot be cancelled.');
    }

    /* 4. Time-based refund calc */
    date_default_timezone_set('Asia/Kolkata');

    $orderTime   = strtotime($order['created_at']);
    $currentTime = time();
    $diffMinutes = round(($currentTime - $orderTime) / 60);

    $totalAmount     = (float)$order['total_amount'];
    $refundAmount    = 0.00;
    $deductionAmount = 0.00;

    if ($diffMinutes <= 60) {
        $refundAmount = $totalAmount;
    } else {
        $deductionAmount = round($totalAmount * 0.10, 2);
        $refundAmount    = $totalAmount - $deductionAmount;
    }

    /* 5. Begin transaction */
    $pdo->beginTransaction();

    /* -----------------------------------------------
       5a. RESTORE STOCK back into menu_products
       Parse products_json → for each line, add qty to
       menu_products.stock_count when not unlimited.
       ----------------------------------------------- */
    $productsJson = $order['products_json'] ?? '[]';
    $productsArr  = json_decode($productsJson, true);
    if (!is_array($productsArr)) $productsArr = [];

    if (!empty($productsArr)) {
        /* Prepare statements once */
        $stockStmt = $pdo->prepare(
            "SELECT id, stock_unlimited, stock_count
             FROM menu_products
             WHERE menu_code = ? AND product_code = ? AND variant_id = ?
             LIMIT 1"
        );
        $updStmt = $pdo->prepare(
            "UPDATE menu_products SET stock_count = ? WHERE id = ?"
        );

        foreach ($productsArr as $line) {
            $menuCode    = trim((string)($line['menu_code']    ?? ''));
            $productCode = trim((string)($line['code']         ?? ''));
            $variantId   = (int)($line['variant_id'] ?? 0);
            $qty         = (int)($line['qty']        ?? 0);

            if ($qty <= 0 || $menuCode === '' || $productCode === '' || $variantId <= 0) {
                continue;
            }

            $stockStmt->execute([$menuCode, $productCode, $variantId]);
            $row = $stockStmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) continue;

            /* Skip unlimited-stock variants */
            if ((int)$row['stock_unlimited'] === 1) continue;

            $newCount = (int)$row['stock_count'] + $qty;
            $updStmt->execute([$newCount, (int)$row['id']]);
        }
    }

    /* -----------------------------------------------
       5b. Mark order cancelled
       ----------------------------------------------- */
    $updateOrder = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
    $updateOrder->execute([$orderId]);

    /* -----------------------------------------------
       5c. Wallet refund
       ----------------------------------------------- */
    if ($refundAmount > 0) {
        $balStmt = $pdo->prepare("SELECT wallet_balance FROM customers WHERE id = ? LIMIT 1");
        $balStmt->execute([$customerRowId]);
        $currentBalance = (float)$balStmt->fetchColumn();
        $newBalance     = $currentBalance + $refundAmount;

        $updateWallet = $pdo->prepare("UPDATE customers SET wallet_balance = ? WHERE id = ?");
        $updateWallet->execute([$newBalance, $customerRowId]);

        $txnCode = 'TXN' . strtoupper(bin2hex(random_bytes(4)));
        $note    = "Refund for cancelled order #{$order['order_code']}.";
        if ($deductionAmount > 0) {
            $note .= " (10% late cancellation fee applied)";
        }

        $insertTxn = $pdo->prepare("
            INSERT INTO customer_wallet_transactions
            (customer_id, customer_name, customer_mobile, txn_code, txn_type, amount,
             balance_before, balance_after, source, note, created_by_name)
            VALUES (?, ?, ?, ?, 'credit', ?, ?, ?, 'refund', ?, 'System')
        ");
        $insertTxn->execute([
            $customerRowId,
            $_SESSION['customer_name']   ?? 'Customer',
            $_SESSION['customer_mobile'] ?? '',
            $txnCode,
            $refundAmount,
            $currentBalance,
            $newBalance,
            $note
        ]);
    }

    /* 6. Commit */
    $pdo->commit();

    /* 7. Response */
    $msg = "Order cancelled successfully. Stock has been restored.";
    if ($refundAmount > 0) {
        if ($deductionAmount > 0) {
            $msg .= " ₹" . number_format($refundAmount, 2) . " has been refunded to your wallet (10% late cancellation fee applied).";
        } else {
            $msg .= " ₹" . number_format($refundAmount, 2) . " has been refunded to your wallet.";
        }
    }

    jsonResponse(true, $msg, ['order_id' => $orderId]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse(false, 'Server error: ' . $e->getMessage());
}
