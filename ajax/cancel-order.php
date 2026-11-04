<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Check Login
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
    // 2. Fetch order
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

    // 3. Check if already cancelled or delivered
    if ($order['status'] === 'cancelled') {
        jsonResponse(false, 'This order is already cancelled.');
    }
    if ($order['status'] === 'delivered' || $order['delivery_status'] === 'enabled') {
        jsonResponse(false, 'Delivered orders cannot be cancelled.');
    }

    // 4. Calculate time difference safely
    date_default_timezone_set('Asia/Kolkata'); // Ensure correct timezone

    $orderTime = strtotime($order['created_at']);
    $currentTime = time();
    $diffMinutes = round(($currentTime - $orderTime) / 60);

    $isPaid = ($order['payment_status'] === 'paid');
    $totalAmount = (float)$order['total_amount'];
    $refundAmount = 0.00;
    $deductionAmount = 0.00;

    // 5. Calculate Refund
    if ($isPaid) {
        if ($diffMinutes <= 60) {
            // Under 60 mins: Full refund
            $refundAmount = $totalAmount;
        } else {
            // Over 60 mins: 10% deduction
            $deductionAmount = round($totalAmount * 0.10, 2);
            $refundAmount = $totalAmount - $deductionAmount;
        }
    }

    // 6. Update Order Status
    $pdo->beginTransaction();

    $updateOrder = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
    $updateOrder->execute([$orderId]);

    // 7. Process Wallet Refund
    if ($isPaid && $refundAmount > 0) {
        // Get current balance
        $balStmt = $pdo->prepare("SELECT wallet_balance FROM customers WHERE id = ? LIMIT 1");
        $balStmt->execute([$customerRowId]);
        $currentBalance = (float)$balStmt->fetchColumn();
        $newBalance = $currentBalance + $refundAmount;

        // Update customer wallet
        $updateWallet = $pdo->prepare("UPDATE customers SET wallet_balance = ? WHERE id = ?");
        $updateWallet->execute([$newBalance, $customerRowId]);

        // Log Transaction
        $txnCode = 'TXN' . strtoupper(bin2hex(random_bytes(4)));
        $note = "Refund for cancelled order #{$order['order_code']}.";
        if ($deductionAmount > 0) {
            $note .= " (10% late cancellation fee applied)";
        }

        $insertTxn = $pdo->prepare("
            INSERT INTO customer_wallet_transactions 
            (customer_id, customer_name, customer_mobile, txn_code, txn_type, amount, balance_before, balance_after, source, note, created_by_name) 
            VALUES (?, ?, ?, ?, 'credit', ?, ?, ?, 'refund', ?, 'System')
        ");
        $insertTxn->execute([
            $customerRowId,
            $_SESSION['customer_name'] ?? 'Customer',
            $_SESSION['customer_mobile'] ?? '',
            $txnCode,
            $refundAmount,
            $currentBalance,
            $newBalance,
            $note
        ]);
    }

    $pdo->commit();

    // 8. Return Success
    $msg = "Order cancelled successfully.";
    if ($isPaid) {
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
