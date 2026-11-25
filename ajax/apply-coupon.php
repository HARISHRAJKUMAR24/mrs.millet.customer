<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

/* ---------- helper: safe JSON ---------- */
function jsonOut(bool $ok, string $msg, $data = null): void
{
    echo json_encode(['success' => $ok, 'message' => $msg, 'data' => $data]);
    exit;
}

/* ================= GET : list offers ================= */
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
        jsonOut(true, 'OK', $out);
    } catch (PDOException $e) {
        jsonOut(false, 'Failed to load coupons.');
    }
}

/* ================= POST : apply / remove ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* -------- REMOVE -------- */
    if (!empty($_POST['remove'])) {
        unset($_SESSION['applied_discount']);
        jsonOut(true, 'Discount removed.', [
            'removed'     => true,
            'coupon'      => null,
            'total'       => null,
        ]);
    }

    $code = strtoupper(trim($_POST['code'] ?? ''));
    if ($code === '') jsonOut(false, 'Please enter a coupon code.');

    /* -------- TIME offer (menu) -------- */
    if (strpos($code, 'TIME:') === 0) {
        $menuCode = substr($code, 5);
        try {
            $mStmt = $pdo->prepare("SELECT * FROM menus WHERE menu_code = ? AND status = 1 LIMIT 1");
            $mStmt->execute([$menuCode]);
            $menu = $mStmt->fetch(PDO::FETCH_ASSOC);
            if (!$menu) jsonOut(false, 'This offer is no longer available.');

            $now = time();
            if ($now < strtotime($menu['start_at']) || $now > strtotime($menu['end_at'])) {
                jsonOut(false, 'This offer is not active right now.');
            }

            $_SESSION['applied_discount'] = [
                'type'  => 'time',
                'code'  => $menuCode,
                'label' => $menu['menu_name'],
            ];
            jsonOut(true, 'Offer applied successfully!', [
                'type'  => 'time',
                'code'  => $menuCode,
                'label' => $menu['menu_name'],
            ]);
        } catch (PDOException $e) {
            jsonOut(false, 'Server error.');
        }
    }

    /* -------- COUPON -------- */
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

        if (!$row) jsonOut(false, 'Invalid coupon code.');

        if ((int)$row['usage_limit'] > 0 && (int)$row['used_count'] >= (int)$row['usage_limit']) {
            jsonOut(false, 'This coupon has reached its usage limit.');
        }

        $now = date('Y-m-d');
        if (!empty($row['valid_from_date']) && $now < $row['valid_from_date']) jsonOut(false, 'This coupon is not yet active.');
        if (!empty($row['valid_to_date'])   && $now > $row['valid_to_date'])   jsonOut(false, 'This coupon has expired.');

        $nowTime = date('H:i:s');
        $startT  = $row['start_time'] ?? '00:00:00';
        $endT    = $row['end_time']   ?? '23:59:59';
        if ($nowTime < $startT || $nowTime > $endT) {
            jsonOut(false, 'Coupon valid only between ' . date('h:i A', strtotime($startT)) . ' and ' . date('h:i A', strtotime($endT)) . '.');
        }

        $amount = (float)$row['discount_amount'];
        if ($amount <= 0) jsonOut(false, 'Coupon has no discount value.');

        $label = $row['amount_type'] === 'percent'
            ? number_format($amount, 0) . '% OFF'
            : '₹' . number_format($amount, 0) . ' OFF';

        $_SESSION['applied_discount'] = [
            'type'  => 'coupon',
            'code'  => $code,
            'label' => $row['discount_name'],
        ];

        jsonOut(true, 'Coupon applied successfully!', [
            'type'        => 'coupon',
            'code'        => $code,
            'name'        => $row['discount_name'],
            'label'       => $label,
            'amount_type' => $row['amount_type'],
            'amount'      => $amount,
        ]);
    } catch (PDOException $e) {
        jsonOut(false, 'Server error.');
    }
}

jsonOut(false, 'Method not allowed.');
