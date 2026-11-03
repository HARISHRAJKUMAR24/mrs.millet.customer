<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $nowDate = date('Y-m-d');
        $stmt = $pdo->prepare(
            "SELECT d.discount_name, d.coupon_code, d.usage_limit, d.used_count,
                    dt.amount_type, dt.discount_amount
             FROM discounts d
             INNER JOIN discount_times dt ON dt.discount_code = d.discount_code
             WHERE d.status = 1 AND d.discount_type = 'coupon'
               AND d.coupon_code IS NOT NULL AND d.coupon_code != ''
               AND (d.valid_from_date IS NULL OR d.valid_from_date <= ?)
               AND (d.valid_to_date IS NULL OR d.valid_to_date >= ?)
             ORDER BY dt.discount_amount DESC"
        );
        $stmt->execute([$nowDate, $nowDate]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $r) {
            if ((int)$r['usage_limit'] > 0 && (int)$r['used_count'] >= (int)$r['usage_limit']) continue;
            $out[] = [
                'type'  => 'coupon',
                'code'  => $r['coupon_code'],
                'name'  => $r['discount_name'],
                'label' => $r['amount_type'] === 'percent'
                    ? number_format($r['discount_amount'], 0) . '% OFF'
                    : '₹' . number_format($r['discount_amount'], 0) . ' OFF',
            ];
        }

        try {
            $mStmt = $pdo->prepare(
                "SELECT menu_code, menu_name, start_at, end_at
                 FROM menus WHERE status = 1 AND start_at <= NOW() AND end_at >= NOW()"
            );
            $mStmt->execute();
            foreach ($mStmt->fetchAll(PDO::FETCH_ASSOC) as $menu) {
                $menuStart = strtotime($menu['start_at']);
                $menuEnd   = strtotime($menu['end_at']);
                $now       = time();
                if ($now < $menuStart || $now > $menuEnd) continue;

                $dStmt = $pdo->prepare(
                    "SELECT dt.amount_type, dt.discount_amount, dt.start_time, dt.end_time
                     FROM discounts d
                     INNER JOIN discount_times dt ON dt.discount_code = d.discount_code
                     WHERE d.status = 1 AND d.discount_type = 'time'
                     ORDER BY dt.id ASC"
                );
                $dStmt->execute();
                $slots = $dStmt->fetchAll(PDO::FETCH_ASSOC);

                $prevEndEpoch = $menuStart;
                $baseDay      = date('Y-m-d', $menuStart);
                $cur          = null;
                foreach ($slots as $s) {
                    $slotEndTs = strtotime($baseDay . ' ' . $s['end_time']);
                    if ($slotEndTs < $prevEndEpoch) $slotEndTs += 86400;
                    if ($now >= $prevEndEpoch && $now <= $slotEndTs) { $cur = $s; break; }
                    $prevEndEpoch = $slotEndTs;
                }
                if ($cur && (float)$cur['discount_amount'] > 0) {
                    $out[] = [
                        'type'  => 'time',
                        'code'  => 'TIME:' . $menu['menu_code'],
                        'name'  => $menu['menu_name'] . ' (Live)',
                        'label' => $cur['amount_type'] === 'percent'
                            ? number_format((float)$cur['discount_amount'], 0) . '% OFF'
                            : '₹' . number_format((float)$cur['discount_amount'], 0) . ' OFF',
                    ];
                }
            }
        } catch (PDOException $e) {}

        jsonResponse(true, 'OK', $out);
    } catch (PDOException $e) {
        jsonResponse(false, 'Failed to load coupons.');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['remove'])) {
        unset($_SESSION['applied_discount']);
        jsonResponse(true, 'Discount removed.');
    }

    $code = strtoupper(trim($_POST['code'] ?? ''));
    if ($code === '') jsonResponse(false, 'Please select a discount.');

    if (strpos($code, 'TIME:') === 0) {
        $menuCode = substr($code, 5);
        try {
            $mStmt = $pdo->prepare("SELECT * FROM menus WHERE menu_code = ? AND status = 1 LIMIT 1");
            $mStmt->execute([$menuCode]);
            $menu = $mStmt->fetch(PDO::FETCH_ASSOC);
            if (!$menu) jsonResponse(false, 'This offer is no longer available.');
            $now = time();
            if ($now < strtotime($menu['start_at']) || $now > strtotime($menu['end_at'])) {
                jsonResponse(false, 'This offer is not active right now.');
            }
            $_SESSION['applied_discount'] = ['type' => 'time', 'code' => $menuCode, 'label' => $menu['menu_name']];
            jsonResponse(true, 'Offer applied successfully!');
        } catch (PDOException $e) {
            jsonResponse(false, 'Server error.');
        }
        exit;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT d.id, d.discount_code, d.discount_name, d.coupon_code,
                    d.valid_from_date, d.valid_to_date, d.usage_limit, d.used_count,
                    dt.start_time, dt.end_time, dt.amount_type, dt.discount_amount
             FROM discounts d
             INNER JOIN discount_times dt ON dt.discount_code = d.discount_code
             WHERE d.status = 1 AND d.discount_type = 'coupon' AND d.coupon_code = ?
             LIMIT 1"
        );
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) jsonResponse(false, 'Invalid coupon code.');
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

        $_SESSION['applied_discount'] = [
            'type'  => 'coupon',
            'code'  => $code,
            'label' => $row['discount_name'],
        ];
        jsonResponse(true, 'Coupon applied successfully!');
    } catch (PDOException $e) {
        jsonResponse(false, 'Server error.');
    }
    exit;
}

jsonResponse(false, 'Method not allowed.');