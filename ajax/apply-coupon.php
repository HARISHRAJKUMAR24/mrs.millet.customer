<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

/* =========================================================
   GET: LIST AVAILABLE COUPONS
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $nowDate = date('Y-m-d');

        $stmt = $pdo->prepare(
            "SELECT d.discount_name, d.coupon_code, d.usage_limit, d.used_count,
                    dt.amount_type, dt.discount_amount
             FROM discounts d
             INNER JOIN discount_times dt ON dt.discount_code = d.discount_code
             WHERE d.status = 1
               AND d.discount_type = 'coupon'
               AND d.coupon_code IS NOT NULL
               AND d.coupon_code != ''
               AND (d.valid_from_date IS NULL OR d.valid_from_date <= ?)
               AND (d.valid_to_date IS NULL OR d.valid_to_date >= ?)
             ORDER BY dt.discount_amount DESC"
        );
        $stmt->execute([$nowDate, $nowDate]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $r) {
            // Skip if the coupon has reached its limit
            if ((int)$r['usage_limit'] > 0 && (int)$r['used_count'] >= (int)$r['usage_limit']) {
                continue;
            }

            $out[] = [
                'code'        => $r['coupon_code'],
                'name'        => $r['discount_name'],
                'amount_type' => $r['amount_type'],
                'amount'      => (float)$r['discount_amount'],
                'label'       => $r['amount_type'] === 'percent'
                    ? number_format($r['discount_amount'], 0) . '% OFF'
                    : '₹' . number_format($r['discount_amount'], 0) . ' OFF',
                'left'        => ((int)$r['usage_limit'] > 0)
                    ? max(0, (int)$r['usage_limit'] - (int)$r['used_count'])
                    : null
            ];
        }

        jsonResponse(true, 'OK', $out);
    } catch (PDOException $e) {
        jsonResponse(false, 'Failed to load coupons.');
    }
    exit;
}

/* =========================================================
   POST: APPLY OR REMOVE COUPON
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Remove coupon
    if (!empty($_POST['remove'])) {
        unset($_SESSION['applied_coupon']);
        jsonResponse(true, 'Coupon removed.');
    }

    $code = strtoupper(trim($_POST['code'] ?? ''));
    if ($code === '') jsonResponse(false, 'Please enter a coupon code.');

    try {
        $stmt = $pdo->prepare(
            "SELECT d.id, d.discount_code, d.discount_name, d.coupon_code,
                    d.valid_from_date, d.valid_to_date,
                    d.usage_limit, d.used_count,
                    dt.start_time, dt.end_time, dt.amount_type, dt.discount_amount
             FROM discounts d
             INNER JOIN discount_times dt ON dt.discount_code = d.discount_code
             WHERE d.status = 1 AND d.discount_type = 'coupon' AND d.coupon_code = ?
             LIMIT 1"
        );
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) jsonResponse(false, 'Invalid coupon code.');

        // Check usage limit
        if ((int)$row['usage_limit'] > 0 && (int)$row['used_count'] >= (int)$row['usage_limit']) {
            jsonResponse(false, 'This coupon has reached its usage limit.');
        }

        $now = date('Y-m-d');
        if (!empty($row['valid_from_date']) && $now < $row['valid_from_date']) jsonResponse(false, 'This coupon is not yet active.');
        if (!empty($row['valid_to_date']) && $now > $row['valid_to_date']) jsonResponse(false, 'This coupon has expired.');

        $nowTime = date('H:i:s');
        $startT  = $row['start_time'] ?? '00:00:00';
        $endT    = $row['end_time']   ?? '23:59:59';
        if ($nowTime < $startT || $nowTime > $endT) {
            jsonResponse(false, 'Coupon valid only between ' . date('h:i A', strtotime($startT)) . ' and ' . date('h:i A', strtotime($endT)) . '.');
        }

        // Save in session (usage tracked at order placement)
        $_SESSION['applied_coupon'] = $code;
        jsonResponse(true, 'Coupon applied successfully!');

    } catch (PDOException $e) {
        jsonResponse(false, 'Server error.');
    }
    exit;
}

jsonResponse(false, 'Method not allowed.');