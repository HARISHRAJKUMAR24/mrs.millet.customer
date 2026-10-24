<?php
require_once './config/config.php';
require_once './config/function.php';

$settings = getSettings($pdo);

$siteName = $settings['username'] ?? 'Mrs Mill@';
$logoUrl  = !empty($settings['logo_image'])    ? ADMIN_URL . $settings['logo_image']    : '';
$favicon  = !empty($settings['favicon_image']) ? ADMIN_URL . $settings['favicon_image'] : '';

/* =========================================================
   CUSTOMER LOYALTY HELPER
   ========================================================= */
function getCustomerLoyaltyDiscount(PDO $pdo, int $customerRowId): ?array
{
    if ($customerRowId <= 0) return null;

    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(id) FROM orders
             WHERE customer_id = (SELECT customer_id FROM customers WHERE id = ?)
               AND status != 'cancelled'"
        );
        $stmt->execute([$customerRowId]);
        $orderCount = (int)$stmt->fetchColumn();

        $dStmt = $pdo->prepare(
            "SELECT d.discount_name, d.discount_code,
                    t.tier_name, t.amount_type, t.discount_amount
             FROM discounts d
             INNER JOIN discount_customer_tiers t ON t.discount_code = d.discount_code
             WHERE d.status = 1
               AND d.discount_type = 'customer_type'
               AND ? BETWEEN t.min_orders AND t.max_orders
             LIMIT 1"
        );
        $dStmt->execute([$orderCount]);
        $row = $dStmt->fetch(PDO::FETCH_ASSOC);

        if ($row && $row['discount_amount'] > 0) {
            return [
                'name'        => $row['discount_name'],
                'tier'        => $row['tier_name'],
                'amount_type' => $row['amount_type'],
                'amount'      => (float)$row['discount_amount'],
                'order_count' => $orderCount,
            ];
        }
    } catch (PDOException $e) {
    }
    return null;
}

/* =========================================================
   COUPON VALIDATION HELPER
   ========================================================= */
function validateCoupon(PDO $pdo, string $code, float $cartTotal): ?array
{
    if ($code === '') return null;

    try {
        $stmt = $pdo->prepare(
            "SELECT d.id, d.discount_code, d.discount_name, d.coupon_code,
                    d.valid_from_date, d.valid_to_date,
                    dt.start_time, dt.end_time, dt.amount_type, dt.discount_amount
             FROM discounts d
             INNER JOIN discount_times dt ON dt.discount_code = d.discount_code
             WHERE d.status = 1 AND d.discount_type = 'coupon' AND d.coupon_code = ?
             LIMIT 1"
        );
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return ['error' => 'Invalid coupon code.'];

        $now = date('Y-m-d');
        if (!empty($row['valid_from_date']) && $now < $row['valid_from_date']) {
            return ['error' => 'This coupon is not yet active.'];
        }
        if (!empty($row['valid_to_date']) && $now > $row['valid_to_date']) {
            return ['error' => 'This coupon has expired.'];
        }

        $nowTime = date('H:i:s');
        $startT  = $row['start_time'] ?? '00:00:00';
        $endT    = $row['end_time']   ?? '23:59:59';
        if ($nowTime < $startT || $nowTime > $endT) {
            return ['error' => 'This coupon can only be used between ' . date('h:i A', strtotime($startT)) . ' and ' . date('h:i A', strtotime($endT)) . '.'];
        }

        $amount = (float)$row['discount_amount'];
        if ($amount <= 0) return ['error' => 'Coupon has no discount value.'];

        if ($row['amount_type'] === 'percent') {
            $discountAmt = round(($cartTotal * $amount) / 100);
        } else {
            $discountAmt = min($amount, $cartTotal);
        }

        return [
            'code'        => $row['coupon_code'],
            'name'        => $row['discount_name'],
            'amount_type' => $row['amount_type'],
            'amount'      => $amount,
            'discount'    => $discountAmt,
            'label'       => $row['amount_type'] === 'percent'
                ? number_format($amount, 0) . '% OFF'
                : '₹' . number_format($amount, 0) . ' OFF'
        ];
    } catch (PDOException $e) {
        return ['error' => 'Server error while validating coupon.'];
    }
}

/* =========================================================
   LOGGED-IN CUSTOMER
   ========================================================= */
$customerLoggedIn = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0;
$customerRowId    = $customerLoggedIn ? (int)$_SESSION['customer_id'] : 0;

$customerCode    = $_SESSION['customer_code']   ?? '';
$customerName    = $_SESSION['customer_name']   ?? '';
$customerMobile  = $_SESSION['customer_mobile'] ?? '';

$customerAddressType = 'apartment';
$customerAddress     = [];

$customerApartmentId    = 0;
$customerApartmentCode  = '';
$customerApartmentName  = '';
$customerDivision       = '';
$customerDivisionCharge = 0;
$customerFullAddress    = '';
$customerMapLink        = '';
$customerHouseImage     = '';
$customerWalletBalance  = 0.0;

if ($customerRowId > 0) {
    try {
        $cStmt = $pdo->prepare(
            "SELECT customer_id, full_name, mobile_number,
                    address_type, address_json,
                    COALESCE(wallet_balance, 0) AS wallet_balance
             FROM customers
             WHERE id = ? AND status = 1
             LIMIT 1"
        );
        $cStmt->execute([$customerRowId]);
        $cust = $cStmt->fetch(PDO::FETCH_ASSOC);

        if ($cust) {
            $customerName    = $cust['full_name']     ?: $customerName;
            $customerMobile  = $cust['mobile_number'] ?: $customerMobile;
            $customerCode    = $cust['customer_id']   ?: $customerCode;

            $customerAddressType = $cust['address_type'] ?: 'apartment';
            $decoded = json_decode($cust['address_json'] ?? '[]', true);
            $customerAddress = is_array($decoded) ? $decoded : [];

            $customerWalletBalance = (float)($cust['wallet_balance'] ?? 0);

            if ($customerAddressType === 'individual_house') {
                $customerFullAddress = $customerAddress['full_address'] ?? '';
                $customerMapLink     = $customerAddress['map_link']     ?? '';
                $customerHouseImage  = $customerAddress['house_image']  ?? '';
            } else {
                $customerApartmentId    = (int)($customerAddress['apartment_id']  ?? 0);
                $customerApartmentCode  = $customerAddress['apartment_code'] ?? '';
                $customerApartmentName  = $customerAddress['apartment_name'] ?? '';
                $customerDivision       = $customerAddress['division_label'] ?? '';
                $customerDivisionCharge = (float)($customerAddress['charge'] ?? 0);
            }
        }
    } catch (PDOException $e) {
    }
}

/* =========================================================
   LOAD CART
   ========================================================= */
$cart = [];

if ($customerRowId > 0) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id AS cart_id, menu_code, product_id, product_code AS code, product_name AS name,
                    product_image AS image, variant_id, variant_name, variant_qty,
                    price, qty
             FROM customer_cart
             WHERE customer_id = ?
             ORDER BY id ASC"
        );
        $stmt->execute([$customerRowId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $cart[] = [
                'cart_id'      => $r['cart_id'],
                'menu_code'    => $r['menu_code'] ?? '',
                'product_id'   => (int)$r['product_id'],
                'code'         => $r['code'],
                'name'         => $r['name'],
                'image'        => $r['image'],
                'variant_id'   => (int)$r['variant_id'],
                'variant_name' => $r['variant_name'],
                'variant_qty'  => $r['variant_qty'],
                'price'        => (float)$r['price'],
                'qty'          => (int)$r['qty'],
            ];
        }
    } catch (PDOException $e) {
        $cart = [];
    }
} else {
    if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $c) {
            $cart[] = [
                'cart_id'      => $c['key'] ?? '',
                'menu_code'    => $c['menu_code'] ?? '',
                'product_id'   => (int)($c['product_id'] ?? 0),
                'code'         => $c['code'] ?? '',
                'name'         => $c['name'] ?? '',
                'image'        => $c['image'] ?? '',
                'variant_id'   => (int)($c['variant_id'] ?? 0),
                'variant_name' => $c['variant_name'] ?? '',
                'variant_qty'  => $c['variant_qty'] ?? '',
                'price'        => (float)($c['price'] ?? 0),
                'qty'          => (int)($c['qty'] ?? 0),
            ];
        }
    }
}

$cartCount = 0;
$cartTotal = 0.0;
foreach ($cart as $c) {
    $qty        = (int)($c['qty'] ?? 0);
    $cartCount += $qty;
    $cartTotal += ((float)($c['price'] ?? 0)) * $qty;
}

if ($cartCount <= 0) {
    header('Location: ' . MAIN_URL . 'index.php');
    exit;
}

/* =========================================================
   CONTAINER DEPOSIT
   ========================================================= */
$containerTotal  = 0;
$totalContainers = 0;

if (!empty($cart)) {
    try {
        $vStmt = $pdo->prepare(
            "SELECT container_enabled, container_price
             FROM product_variants WHERE id = ? LIMIT 1"
        );

        foreach ($cart as &$line) {
            $line['container_enabled']    = 0;
            $line['container_price']      = 0;
            $line['container_line_total'] = 0;

            if ($line['variant_id'] > 0) {
                $vStmt->execute([$line['variant_id']]);
                $v = $vStmt->fetch(PDO::FETCH_ASSOC);
                if ($v && (int)$v['container_enabled'] === 1 && (float)$v['container_price'] > 0) {
                    $line['container_enabled']    = 1;
                    $line['container_price']      = (float)$v['container_price'];
                    $line['container_line_total'] = $line['container_price'] * (int)$line['qty'];

                    $containerTotal  += $line['container_line_total'];
                    $totalContainers += (int)$line['qty'];
                }
            }
        }
        unset($line);
    } catch (PDOException $e) {
    }
}

$containerTotalRound = (int)round($containerTotal);

/* =========================================================
   COUPON APPLIED (Session)
   ========================================================= */
$couponCode   = '';
$couponResult = null;

if (!empty($_SESSION['applied_coupon'])) {
    $couponResult = validateCoupon($pdo, $_SESSION['applied_coupon'], $cartTotal);
    if ($couponResult && empty($couponResult['error'])) {
        $couponCode = $couponResult['code'];
    } else {
        unset($_SESSION['applied_coupon']);
        $couponResult = null;
    }
}

/* =========================================================
   DISCOUNT CALCULATION (TIME + LOYALTY + COUPON)
   ========================================================= */
$totalDiscountAmount = 0;
$discountLabels = [];

// --- A) TIME-BASED DISCOUNT ---
$cartGroupedByMenu = [];
foreach ($cart as $c) {
    $mc = $c['menu_code'] ?: 'GENERAL';
    if (!isset($cartGroupedByMenu[$mc])) {
        $cartGroupedByMenu[$mc] = ['items' => [], 'subtotal' => 0];
    }
    $cartGroupedByMenu[$mc]['items'][] = $c;
    $cartGroupedByMenu[$mc]['subtotal'] += ($c['price'] * $c['qty']);
}

foreach ($cartGroupedByMenu as $menuCode => $group) {
    $menuSubtotal = $group['subtotal'];
    $discountAmount = 0;

    if ($menuCode !== 'GENERAL') {
        $menuInfo = null;
        try {
            $mStmt = $pdo->prepare("SELECT * FROM menus WHERE menu_code = ? LIMIT 1");
            $mStmt->execute([$menuCode]);
            $menuInfo = $mStmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
        }

        if ($menuInfo) {
            $menuStart = strtotime($menuInfo['start_at']);
            $menuEnd   = strtotime($menuInfo['end_at']);
            $now       = time();

            if ($now >= $menuStart && $now <= $menuEnd) {
                $dStmt = $pdo->prepare(
                    "SELECT d.discount_code, d.discount_name,
                            dt.slot_name, dt.start_time, dt.end_time,
                            dt.amount_type, dt.discount_amount, dt.delivery_enabled
                     FROM discounts d
                     INNER JOIN discount_times dt ON dt.discount_code = d.discount_code
                     WHERE d.status = 1 AND d.discount_type = 'time'
                     ORDER BY dt.id ASC"
                );
                $dStmt->execute();
                $slots = $dStmt->fetchAll(PDO::FETCH_ASSOC);

                $prevEndEpoch = $menuStart;
                $baseDay      = date('Y-m-d', $menuStart);
                $currentDiscount = null;

                foreach ($slots as $s) {
                    $slotEndTs = strtotime($baseDay . ' ' . $s['end_time']);
                    if ($slotEndTs < $prevEndEpoch) $slotEndTs += 86400;

                    if ($now >= $prevEndEpoch && $now <= $slotEndTs) {
                        $currentDiscount = [
                            'name'        => $s['discount_name'],
                            'amount_type' => $s['amount_type'],
                            'amount'      => (float)$s['discount_amount'],
                        ];
                        break;
                    }
                    $prevEndEpoch = $slotEndTs;
                }

                if ($currentDiscount && $currentDiscount['amount'] > 0) {
                    if ($currentDiscount['amount_type'] === 'percent') {
                        $discountAmount = round(($menuSubtotal * $currentDiscount['amount']) / 100);
                        $discountLabel  = number_format($currentDiscount['amount'], 0) . '% OFF';
                    } else {
                        $discountAmount = min((float)$currentDiscount['amount'], $menuSubtotal);
                        $discountLabel  = '₹' . number_format($currentDiscount['amount'], 0) . ' OFF';
                    }
                    $totalDiscountAmount += $discountAmount;
                    $discountLabels[] = ['label' => $discountLabel, 'menu_name' => $menuInfo['menu_name']];
                }
            }
        }
    }
}

// --- B) CUSTOMER LOYALTY DISCOUNT ---
$loyaltyDiscount = getCustomerLoyaltyDiscount($pdo, $customerRowId);
$loyaltyAmount   = 0;
if ($loyaltyDiscount) {
    if ($loyaltyDiscount['amount_type'] === 'percent') {
        $loyaltyAmount = round(($cartTotal * $loyaltyDiscount['amount']) / 100);
    } else {
        $loyaltyAmount = min((float)$loyaltyDiscount['amount'], $cartTotal);
    }
    if ($loyaltyAmount > 0) {
        $totalDiscountAmount += $loyaltyAmount;
        $discountLabels[] = [
            'label' => $loyaltyDiscount['amount_type'] === 'percent'
                ? number_format($loyaltyDiscount['amount'], 0) . '% OFF'
                : '₹' . number_format($loyaltyDiscount['amount'], 0) . ' OFF',
            'menu_name' => $loyaltyDiscount['tier'] . ' (Loyalty)'
        ];
    }
}

// --- C) COUPON DISCOUNT ---
$couponAmount = 0;
if ($couponResult && empty($couponResult['error'])) {
    $couponAmount = $couponResult['discount'];
    if ($couponAmount > 0) {
        $totalDiscountAmount += $couponAmount;
        $discountLabels[] = [
            'label' => $couponResult['label'],
            'menu_name' => 'Coupon ' . $couponResult['code']
        ];
    }
}

// --- D) DISPLAY LABEL ---
$displayDiscountLabel = '';
if (count($discountLabels) === 1) {
    $displayDiscountLabel = $discountLabels[0]['label'];
} elseif (count($discountLabels) > 1) {
    $parts = [];
    foreach ($discountLabels as $lbl) {
        $parts[] = $lbl['label'];
    }
    $displayDiscountLabel = implode(' + ', $parts);
}

if ($totalDiscountAmount > $cartTotal) $totalDiscountAmount = $cartTotal;

$discountAmountRound = (int)round($totalDiscountAmount);
$discountedSubtotal  = max(0, $cartTotal - $discountAmountRound);
$discountedSubtotalRound = (int)round($discountedSubtotal);

/* =========================================================
   TAX
   ========================================================= */
$taxStatus = (int)($settings['tax_status'] ?? 0);
$taxRate   = (float)($settings['tax_rate'] ?? 0);
$taxType   = strtolower($settings['tax_type'] ?? 'exclusive');
if (!in_array($taxType, ['inclusive', 'exclusive'], true)) $taxType = 'exclusive';

$taxAmountRound = 0;
$foodPlusTax    = $discountedSubtotalRound;

if ($taxStatus === 1 && $taxRate > 0) {
    if ($taxType === 'inclusive') {
        $taxAmountRound = (int)round($discountedSubtotalRound - ($discountedSubtotalRound / (1 + ($taxRate / 100))));
        $foodPlusTax    = $discountedSubtotalRound;
    } else {
        $taxAmountRound = (int)round($discountedSubtotalRound * ($taxRate / 100));
        $foodPlusTax    = $discountedSubtotalRound + $taxAmountRound;
    }
}

/* =========================================================
   WALLET + PAYABLE
   ========================================================= */
$walletBalance      = (float)$customerWalletBalance;
$walletApplied      = min($walletBalance, $foodPlusTax);
$payableNow         = max(0, $foodPlusTax - $walletApplied);
$walletAppliedRound = (int)round($walletApplied);
$payableNowRound    = (int)round($payableNow);

$razorpayKeyId = $settings['razorpay_key_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include_once './includes/head_links.php'; ?>
    <style>
        /* --- Original Styles --- */
        .mm-mode-wrap {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .mm-mode-option {
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 14px 14px 12px;
            border: 1.5px solid #ece5da;
            background: #fffdf9;
            border-radius: 12px;
            cursor: pointer;
            transition: .18s ease;
            user-select: none;
        }

        .mm-mode-option:hover {
            border-color: #d98a91;
            background: #fff5f5;
        }

        .mm-mode-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .mm-mode-radio {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #d5cbbd;
            flex-shrink: 0;
            position: relative;
            transition: .15s ease;
        }

        .mm-mode-option input:checked~.mm-mode-radio {
            border-color: #b51f2c;
        }

        .mm-mode-option input:checked~.mm-mode-radio::after {
            content: "";
            position: absolute;
            inset: 3px;
            background: #b51f2c;
            border-radius: 50%;
        }

        .mm-mode-option:has(input:checked) {
            border-color: #b51f2c;
            background: #fff5f5;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .mm-mode-body {
            flex: 1;
            min-width: 0;
        }

        .mm-mode-title {
            font-size: 12.5px;
            font-weight: 800;
            color: #302923;
            margin: 0;
            line-height: 1.2;
        }

        .mm-mode-sub {
            font-size: 10.5px;
            color: #948c82;
            margin: 3px 0 0;
            font-weight: 600;
            line-height: 1.3;
        }

        .mm-mode-option svg.mm-mode-icon {
            width: 16px;
            height: 16px;
            color: #b51f2c;
            flex-shrink: 0;
        }

        .mm-pickup-info {
            margin-top: 14px;
            padding: 14px;
            border-radius: 12px;
            background: linear-gradient(135deg, #fdf7ec 0%, #fbe8e9 100%);
            border: 1px solid #f3dca5;
            display: none;
        }

        .mm-pickup-info.show {
            display: block;
        }

        .mm-pickup-row {
            display: flex;
            gap: 10px;
            font-size: 12px;
            color: #6f5a3f;
            padding: 4px 0;
        }

        .mm-pickup-row strong {
            color: #302923;
        }

        .mm-pickup-row svg {
            width: 14px;
            height: 14px;
            color: #b8893c;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .mm-address-block.hidden {
            display: none !important;
        }

        .mm-branch-select {
            width: 100%;
            height: 46px;
            border: 1.5px solid #ece5da;
            background: #fffdf9;
            border-radius: 12px;
            padding: 0 40px 0 40px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            color: #292521;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            outline: none;
            transition: .2s ease;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23948c82' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 14px;
        }

        .mm-branch-select:focus {
            border-color: #b51f2c;
            background-color: #fff;
            box-shadow: 0 0 0 4px rgba(181, 31, 44, .08);
        }

        .mm-wallet-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 12px;
            background: linear-gradient(135deg, #e8f6ea 0%, #d3edd8 100%);
            border: 1.5px solid #a7c8a9;
            margin-top: 14px;
            margin-bottom: 6px;
        }

        .mm-wallet-box .left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .mm-wallet-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: #1f7a3d;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .mm-wallet-label {
            font-size: 10px;
            font-weight: 800;
            color: #4e6f52;
            text-transform: uppercase;
            letter-spacing: .07em;
        }

        .mm-wallet-value {
            font-family: "Playfair Display", serif;
            font-size: 20px;
            font-weight: 700;
            color: #1b5e20;
            line-height: 1.1;
            margin-top: 2px;
        }

        .mm-wallet-msg {
            font-size: 11.5px;
            color: #2e7d32;
            font-weight: 700;
            text-align: right;
            line-height: 1.35;
        }

        .mm-wallet-msg.warn {
            color: #a35a0e;
        }

        .mm-co-row.tax-row {
            color: #b8893c;
        }

        .mm-co-row.tax-row span:last-child {
            color: #b8893c;
            font-weight: 800;
        }

        .mm-addr-readonly {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px;
            border-radius: 12px;
            background: #fdfaf4;
            border: 1.5px solid #ece5da;
        }

        .mm-addr-ro-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            color: #fff;
            flex-shrink: 0;
        }

        .mm-addr-ro-icon.apartment {
            background: linear-gradient(135deg, #a35a0e 0%, #7a4208 100%);
        }

        .mm-addr-ro-icon.house {
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
        }

        .mm-addr-ro-body {
            flex: 1;
            min-width: 0;
        }

        .mm-addr-ro-title {
            font-size: 13px;
            font-weight: 800;
            color: #302923;
            margin: 0 0 3px;
            line-height: 1.3;
            word-break: break-word;
        }

        .mm-addr-ro-meta {
            font-size: 11.5px;
            color: #6f5a3f;
            margin: 2px 0 0;
            font-weight: 600;
        }

        .mm-addr-ro-meta strong {
            color: #b51f2c;
        }

        .mm-addr-ro-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 9.5px;
            font-weight: 800;
            letter-spacing: .04em;
            padding: 3px 8px;
            border-radius: 6px;
            margin-top: 6px;
        }

        .mm-addr-ro-badge.apartment {
            background: #fdf1e2;
            color: #a35a0e;
        }

        .mm-addr-ro-badge.house {
            background: #eef7f0;
            color: #2e7d32;
        }

        .mm-addr-ro-links {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-top: 8px;
        }

        .mm-addr-ro-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11.5px;
            font-weight: 700;
            text-decoration: none;
            padding: 6px 10px;
            border-radius: 8px;
            width: fit-content;
        }

        .mm-addr-ro-link.map {
            background: #fdf1e2;
            color: #a35a0e;
            border: 1px dashed #f3dca5;
        }

        .mm-addr-ro-link.photo {
            background: #eef7f0;
            color: #2e7d32;
            border: 1px dashed #c9e3ce;
        }

        .mm-addr-ro-link svg {
            width: 13px;
            height: 13px;
        }

        .mm-co-input[readonly] {
            background: #f7f2ec;
            color: #6f5a3f;
            cursor: not-allowed;
        }

        .mm-co-input-wrap textarea.mm-co-input {
            background: transparent;
            border: 0;
            outline: none;
            width: 100%;
            color: #292521;
            font-size: 13px;
            font-weight: 600;
            resize: vertical;
            min-height: 64px;
            font-family: inherit;
            padding: 0;
            line-height: 1.5;
        }

        .mm-co-input-wrap textarea.mm-co-input::placeholder {
            color: #a19a90;
            font-weight: 500;
        }

        .mm-co-input-wrap.note-wrap {
            height: auto;
            padding: 12px 14px;
            align-items: flex-start;
        }

        /* --- Loyalty Banner --- */
        .mm-loyalty-box {
            margin-top: 12px;
            padding: 12px 14px;
            border-radius: 12px;
            background: linear-gradient(135deg, #f0e8fa 0%, #e8dcf5 100%);
            border: 1.5px solid #c9b3e0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .mm-loyalty-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: #6a1b9a;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .mm-loyalty-label {
            font-size: 10px;
            font-weight: 800;
            color: #6a1b9a;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .mm-loyalty-tier {
            font-size: 13px;
            font-weight: 700;
            color: #302923;
            margin-top: 2px;
        }

        .mm-loyalty-tier small {
            font-size: 11px;
            font-weight: 600;
            color: #756d65;
        }

        .mm-loyalty-amount {
            font-size: 12px;
            font-weight: 800;
            color: #6a1b9a;
            text-align: right;
            white-space: nowrap;
        }

        /* --- NEW COUPON STYLES --- */
        .mm-coupon-trigger {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 14px;
            border-radius: 12px;
            background: linear-gradient(135deg, #fffaf4 0%, #fdf1e2 100%);
            border: 1.5px dashed #e8d5a8;
            margin-top: 14px;
            cursor: pointer;
            transition: .2s;
        }

        .mm-coupon-trigger:hover {
            background: #fff5e6;
            border-color: #b8893c;
        }

        .mm-coupon-trigger-left {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .mm-coupon-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #b8893c;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .mm-coupon-title {
            font-size: 12.5px;
            font-weight: 800;
            color: #6f5a3f;
            line-height: 1.2;
        }

        .mm-coupon-applied-code {
            background: #1f7a3d;
            color: #fff;
            padding: 1px 6px;
            border-radius: 4px;
            font-size: 11px;
            letter-spacing: .05em;
            margin-left: 2px;
        }

        .mm-coupon-sub {
            font-size: 10.5px;
            color: #a35a0e;
            font-weight: 600;
            margin-top: 2px;
        }

        .mm-coupon-drawer-overlay {
            position: fixed;
            inset: 0;
            background: rgba(30, 25, 22, .55);
            backdrop-filter: blur(3px);
            display: flex;
            align-items: flex-end;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: .25s;
        }

        .mm-coupon-drawer-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .mm-coupon-drawer {
            background: #fff;
            width: 100%;
            max-width: 520px;
            border-radius: 20px 20px 0 0;
            padding: 20px;
            max-height: 80vh;
            overflow-y: auto;
            transform: translateY(100%);
            transition: transform .3s cubic-bezier(.2, .9, .3, 1.2);
        }

        .mm-coupon-drawer-overlay.show .mm-coupon-drawer {
            transform: translateY(0);
        }

        .mm-coupon-drawer-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 16px;
        }

        .mm-coupon-drawer-head h3 {
            margin: 0;
            font-family: "Playfair Display", serif;
            font-size: 18px;
            font-weight: 700;
            color: #302923;
        }

        .mm-coupon-drawer-head p {
            margin: 2px 0 0;
            font-size: 11.5px;
            color: #817a71;
        }

        .mm-coupon-drawer-close {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            border: 0;
            background: #f7f2ec;
            color: #6f675f;
            cursor: pointer;
            font-size: 14px;
        }

        .mm-coupon-drawer-close:hover {
            background: #fbeaea;
            color: #b51f2c;
        }

        .mm-coupon-manual {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
        }

        .mm-coupon-manual-input {
            flex: 1;
            height: 44px;
            border: 1.5px solid #ece5da;
            background: #fffdf9;
            border-radius: 10px;
            padding: 0 14px;
            font-family: "DM Sans", sans-serif;
            font-size: 12px;
            font-weight: 800;
            color: #302923;
            letter-spacing: .1em;
            text-transform: uppercase;
            outline: none;
        }

        .mm-coupon-manual-input:focus {
            border-color: #b51f2c;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }

        .mm-coupon-manual-btn {
            height: 44px;
            padding: 0 20px;
            border: none;
            background: #b51f2c;
            color: #fff;
            border-radius: 10px;
            font-size: 11.5px;
            font-weight: 800;
            cursor: pointer;
        }

        .mm-coupon-manual-btn:hover:not(:disabled) {
            background: #8e1722;
        }

        .mm-coupon-manual-btn:disabled {
            opacity: .6;
            cursor: not-allowed;
        }

        .mm-coupon-msg {
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 10px;
            display: none;
        }

        .mm-coupon-msg.error {
            color: #c62828;
            display: block;
        }

        .mm-coupon-msg.success {
            color: #1f7a3d;
            display: block;
        }

        .mm-coupon-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 12px;
        }

        .mm-coupon-loading {
            padding: 30px;
            text-align: center;
            color: #948c82;
            font-size: 12px;
        }

        .mm-coupon-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 12px;
            background: #fffdf9;
            border: 1.5px dashed #e4ddd3;
            cursor: pointer;
            transition: .2s;
        }

        .mm-coupon-card:hover {
            border-color: #d98a91;
            background: #fff5f5;
        }

        .mm-coupon-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .mm-coupon-card-info {
            flex: 1;
            min-width: 0;
        }

        .mm-coupon-card-title {
            font-size: 13px;
            font-weight: 800;
            color: #302923;
            margin: 0;
        }

        .mm-coupon-card-code {
            display: inline-block;
            margin-top: 3px;
            background: #fdf1e2;
            color: #a35a0e;
            font-weight: 800;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 4px;
            letter-spacing: .08em;
        }

        .mm-coupon-card-apply {
            padding: 8px 12px;
            border-radius: 8px;
            border: 1.5px solid #b51f2c;
            background: #fff;
            color: #b51f2c;
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap;
        }

        .mm-coupon-card-apply:hover {
            background: #b51f2c;
            color: #fff;
        }

        .mm-coupon-card-remove {
            padding: 8px 12px;
            border-radius: 8px;
            border: 1.5px solid #c62828;
            background: #fff;
            color: #c62828;
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap;
        }

        .mm-coupon-card-remove:hover {
            background: #c62828;
            color: #fff;
        }
    </style>
</head>

<body>

    <?php include_once './includes/nav-bar.php'; ?>

    <main class="mm-main">
        <div class="mm-container">

            <section class="mm-co-wrap">

                <!-- ================= LEFT: FORM ================= -->
                <form id="checkoutForm" class="mm-co-form" autocomplete="off" novalidate>

                    <!-- Contact -->
                    <div class="mm-co-card">
                        <h2 class="mm-co-title">Contact</h2>

                        <div class="mm-co-field">
                            <label for="coMobile">Mobile Number</label>
                            <div class="mm-co-input-wrap">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.2-1.2a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.7 2z" />
                                </svg>
                                <input type="tel" id="coMobile" class="mm-co-input"
                                    placeholder="10-digit mobile" maxlength="15" inputmode="numeric"
                                    value="<?= htmlspecialchars($customerMobile) ?>"
                                    <?= $customerLoggedIn ? 'readonly' : 'required' ?>>
                            </div>
                            <p class="mm-co-hint" id="mobileHint">
                                <?= $customerLoggedIn ? 'Your registered mobile — cannot be changed.' : "We'll use this for delivery updates." ?>
                            </p>
                        </div>

                        <div class="mm-co-field">
                            <label for="coName">Full Name</label>
                            <div class="mm-co-input-wrap">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="8" r="4" />
                                    <path d="M4 21c0-4 4-6 8-6s8 2 8 6" />
                                </svg>
                                <input type="text" id="coName" class="mm-co-input"
                                    placeholder="Your name" maxlength="150"
                                    value="<?= htmlspecialchars($customerName) ?>" required>
                            </div>
                        </div>
                    </div>

                    <!-- Delivery Mode -->
                    <div class="mm-co-card">
                        <h2 class="mm-co-title">How would you like to receive?</h2>
                        <div class="mm-mode-wrap">
                            <label class="mm-mode-option">
                                <input type="radio" name="delivery_mode" value="delivery" checked>
                                <span class="mm-mode-radio"></span>
                                <div class="mm-mode-body">
                                    <p class="mm-mode-title">Home Delivery</p>
                                    <p class="mm-mode-sub">Deliver to your address</p>
                                </div>
                                <svg class="mm-mode-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                                    <polyline points="9 22 9 12 15 12 15 22" />
                                </svg>
                            </label>
                            <label class="mm-mode-option">
                                <input type="radio" name="delivery_mode" value="pickup">
                                <span class="mm-mode-radio"></span>
                                <div class="mm-mode-body">
                                    <p class="mm-mode-title">Store Pickup</p>
                                    <p class="mm-mode-sub">Collect at our store</p>
                                </div>
                                <svg class="mm-mode-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 9l1-5h16l1 5" />
                                    <path d="M4 9v11h16V9" />
                                    <path d="M9 22V12h6v10" />
                                </svg>
                            </label>
                        </div>
                        <div class="mm-pickup-info" id="pickupInfo">
                            <div class="mm-pickup-row">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                    <circle cx="12" cy="10" r="3" />
                                </svg>
                                <span><strong>Choose your pickup branch</strong></span>
                            </div>
                            <div class="mm-co-field" style="margin-top:10px;margin-bottom:0;">
                                <div class="mm-co-input-wrap">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 9l1-5h16l1 5" />
                                        <path d="M4 9v11h16V9" />
                                        <path d="M9 22V12h6v10" />
                                    </svg>
                                    <select id="coPickupBranch" class="mm-branch-select">
                                        <option value="">— Select branch —</option>
                                    </select>
                                </div>
                                <p class="mm-co-hint" id="pickupBranchHint">Pick a branch to collect your order from.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Order Instructions -->
                    <div class="mm-co-card">
                        <h2 class="mm-co-title">
                            Order Instructions
                            <span style="font-size:11px;font-weight:600;color:#948c82;text-transform:none;letter-spacing:0;">(optional)</span>
                        </h2>
                        <div class="mm-co-field" style="margin-bottom:0;">
                            <div class="mm-co-input-wrap note-wrap">
                                <textarea id="coOrderNote" class="mm-co-input" rows="3"
                                    placeholder="Any special instructions? (e.g. less spicy, ring the bell, leave at door...)"
                                    maxlength="500"></textarea>
                            </div>
                            <p class="mm-co-hint">Optional — we'll pass this to the kitchen &amp; delivery team.</p>
                        </div>
                    </div>

                    <!-- Delivery Address -->
                    <div class="mm-co-card mm-address-block" id="addressBlock">
                        <h2 class="mm-co-title">Delivery Address</h2>

                        <?php if ($customerLoggedIn && $customerAddressType === 'individual_house' && $customerFullAddress !== ''): ?>
                            <div class="mm-addr-readonly">
                                <div class="mm-addr-ro-icon house">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;">
                                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                                        <polyline points="9 22 9 12 15 12 15 22" />
                                    </svg>
                                </div>
                                <div class="mm-addr-ro-body">
                                    <p class="mm-addr-ro-title"><?= htmlspecialchars($customerFullAddress) ?></p>
                                    <span class="mm-addr-ro-badge house"><i class="bi bi-house-door"></i> Individual House</span>
                                    <div class="mm-addr-ro-links">
                                        <?php if ($customerMapLink !== ''): ?>
                                            <a href="<?= htmlspecialchars($customerMapLink) ?>" target="_blank" rel="noopener noreferrer" class="mm-addr-ro-link map">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                                    <circle cx="12" cy="10" r="3" />
                                                </svg>
                                                View on Map
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($customerHouseImage !== ''): ?>
                                            <a href="<?= htmlspecialchars(MAIN_URL . $customerHouseImage) ?>" target="_blank" rel="noopener noreferrer" class="mm-addr-ro-link photo">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                    <rect x="3" y="3" width="18" height="18" rx="2" />
                                                    <circle cx="8.5" cy="8.5" r="1.5" />
                                                    <path d="M21 15l-5-5L5 21" />
                                                </svg>
                                                View House Photo
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" id="coAddressType" value="individual_house">
                            <input type="hidden" id="coFullAddress" value="<?= htmlspecialchars($customerFullAddress) ?>">
                            <input type="hidden" id="coMapLink" value="<?= htmlspecialchars($customerMapLink) ?>">
                            <input type="hidden" id="coHouseImage" value="<?= htmlspecialchars($customerHouseImage) ?>">

                        <?php elseif ($customerLoggedIn && $customerApartmentId > 0): ?>
                            <div class="mm-addr-readonly">
                                <div class="mm-addr-ro-icon apartment">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;">
                                        <path d="M3 21V8l9-5 9 5v13" />
                                        <path d="M9 21V12h6v9" />
                                    </svg>
                                </div>
                                <div class="mm-addr-ro-body">
                                    <p class="mm-addr-ro-title"><?= htmlspecialchars($customerApartmentName) ?></p>
                                    <p class="mm-addr-ro-meta">
                                        <strong>#<?= htmlspecialchars($customerApartmentCode) ?></strong>
                                        · <?= htmlspecialchars($customerDivision) ?>
                                    </p>
                                    <span class="mm-addr-ro-badge apartment"><i class="bi bi-building"></i> Apartment</span>
                                    <?php if ($customerDivisionCharge > 0): ?>
                                        <p class="mm-addr-ro-meta" style="margin-top:8px;">
                                            Delivery charge: <strong>₹<?= number_format($customerDivisionCharge, 0) ?></strong>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <input type="hidden" id="coAddressType" value="apartment">
                            <input type="hidden" id="coApartmentId" value="<?= (int)$customerApartmentId ?>">
                            <input type="hidden" id="coApartmentCode" value="<?= htmlspecialchars($customerApartmentCode) ?>">
                            <input type="hidden" id="coDivision" value="<?= htmlspecialchars($customerDivision) ?>">
                            <input type="hidden" id="coDivisionCharge" value="<?= (float)$customerDivisionCharge ?>">

                        <?php else: ?>
                            <div class="mm-co-field" style="position:relative;">
                                <label for="coApartmentSearch">Apartment / Community</label>
                                <div class="mm-co-input-wrap">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 21V8l9-5 9 5v13" />
                                        <path d="M9 21V12h6v9" />
                                    </svg>
                                    <input type="text" id="coApartmentSearch" class="mm-co-input" placeholder="Search apartment..." autocomplete="off">
                                </div>
                                <input type="hidden" id="coApartmentId" value="">
                                <input type="hidden" id="coApartmentCode" value="">
                                <div id="apartmentResults" class="mm-co-dropdown" role="listbox"></div>
                            </div>
                            <div class="mm-co-field" id="divisionField" style="display:none; position:relative;">
                                <label for="coDivisionSearch">Division</label>
                                <div class="mm-co-input-wrap">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="3" width="7" height="7" rx="1" />
                                        <rect x="14" y="3" width="7" height="7" rx="1" />
                                        <rect x="3" y="14" width="7" height="7" rx="1" />
                                        <rect x="14" y="14" width="7" height="7" rx="1" />
                                    </svg>
                                    <input type="text" id="coDivisionSearch" class="mm-co-input" placeholder="Search division..." autocomplete="off">
                                </div>
                                <input type="hidden" id="coDivision">
                                <input type="hidden" id="coDivisionCharge" value="0">
                                <div id="divisionResults" class="mm-co-dropdown" role="listbox"></div>
                                <p class="mm-co-hint" id="divisionHint"></p>
                            </div>
                            <input type="hidden" id="coAddressType" value="apartment">
                        <?php endif; ?>
                    </div>

                </form>

                <!-- ================= RIGHT: SUMMARY ================= -->
                <aside class="mm-co-summary">
                    <h2 class="mm-co-title">Order Summary</h2>

                    <div class="mm-co-items">
                        <?php foreach ($cart as $item):
                            $lineTotal = ((float)$item['price']) * ((int)$item['qty']);
                        ?>
                            <div class="mm-co-item">
                                <div class="mm-co-item-thumb">
                                    <?php if (!empty($item['image'])): ?>
                                        <img src="<?= htmlspecialchars($item['image']) ?>" alt="">
                                    <?php endif; ?>
                                </div>
                                <div class="mm-co-item-info">
                                    <p class="mm-co-item-name"><?= htmlspecialchars($item['name'] ?? '') ?></p>
                                    <p class="mm-co-item-meta">
                                        <?= htmlspecialchars($item['variant_name'] ?? '') ?> · Qty <?= (int)$item['qty'] ?>
                                    </p>
                                </div>
                                <div class="mm-co-item-price">₹<?= number_format($lineTotal, 0) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mm-co-totals">
                        <div class="mm-co-row">
                            <span>Subtotal</span>
                            <span id="subTotal">₹<?= number_format((int)round($cartTotal), 0) ?></span>
                        </div>

                        <?php if ($discountAmountRound > 0): ?>
                            <div class="mm-co-row" style="color:#1f7a3d;">
                                <span>Discount (<?= htmlspecialchars($displayDiscountLabel) ?>)</span>
                                <span style="color:#1f7a3d;font-weight:800;">−₹<?= number_format($discountAmountRound, 0) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="mm-co-row" id="deliveryRow">
                            <span id="deliveryLabel">Delivery charge</span>
                            <span id="deliveryCharge">₹0</span>
                        </div>

                        <div class="mm-co-row tax-row" id="taxRow" style="display:none;">
                            <span id="taxLabel">Tax</span>
                            <span id="taxAmount">₹0</span>
                        </div>

                        <div class="mm-co-row" id="walletRow" style="<?= ($walletAppliedRound > 0) ? 'display:flex;color:#1f7a3d;' : 'display:none;color:#1f7a3d;' ?>">
                            <span>Wallet applied</span>
                            <span style="color:#1f7a3d;font-weight:800;">−₹<span id="walletApplied"><?= $walletAppliedRound ?></span></span>
                        </div>

                        <div class="mm-co-row mm-co-total">
                            <span>Payable Now</span>
                            <span id="grandTotal">₹<?= number_format($payableNowRound, 0) ?></span>
                        </div>
                    </div>

                    <!-- COUPON BUTTON (clickable) -->
                    <div class="mm-coupon-trigger" id="couponTrigger">
                        <div class="mm-coupon-trigger-left">
                            <div class="mm-coupon-icon"><i class="bi bi-ticket-perforated-fill"></i></div>
                            <div>
                                <?php if ($couponCode !== ''): ?>
                                    <div class="mm-coupon-title">Coupon <span class="mm-coupon-applied-code"><?= htmlspecialchars($couponCode) ?></span> applied!</div>
                                    <div class="mm-coupon-sub">Tap to change or remove</div>
                                <?php else: ?>
                                    <div class="mm-coupon-title">Apply Coupon</div>
                                    <div class="mm-coupon-sub">Save more with available offers</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right" style="color:#b51f2c;font-size:14px;"></i>
                    </div>

                    <!-- LOYALTY BANNER -->
                    <?php if (!empty($loyaltyDiscount) && $loyaltyAmount > 0): ?>
                        <div class="mm-loyalty-box">
                            <div class="mm-loyalty-icon"><i class="bi bi-gem"></i></div>
                            <div style="flex:1;min-width:0;">
                                <div class="mm-loyalty-label">Loyalty Reward</div>
                                <div class="mm-loyalty-tier">
                                    <?= htmlspecialchars($loyaltyDiscount['tier']) ?>
                                    <small>(<?= (int)$loyaltyDiscount['order_count'] ?> orders)</small>
                                </div>
                            </div>
                            <div class="mm-loyalty-amount">
                                <?= $loyaltyDiscount['amount_type'] === 'percent'
                                    ? number_format($loyaltyDiscount['amount'], 0) . '% OFF'
                                    : '₹' . number_format($loyaltyDiscount['amount'], 0) . ' OFF' ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- WALLET BOX (Always visible if logged in) -->
                    <?php if ($customerLoggedIn && $customerWalletBalance > 0): ?>
                        <div class="mm-wallet-box" id="walletBox">
                            <div class="left">
                                <div class="mm-wallet-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20">
                                        <rect x="2" y="6" width="20" height="14" rx="2" />
                                        <path d="M2 10h20" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="mm-wallet-label">Wallet Balance</div>
                                    <div class="mm-wallet-value" id="walletBalance">₹<?= number_format((int)round($customerWalletBalance), 0) ?></div>
                                </div>
                            </div>
                            <div class="mm-wallet-msg" id="walletMsg">
                                <?= ($walletAppliedRound > 0) ? '₹' . number_format($walletAppliedRound, 0) . ' applied' : 'Available' ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <button type="button" id="payNowBtn" class="mm-co-pay-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="5" width="20" height="14" rx="2" />
                            <path d="M2 10h20" />
                        </svg>
                        <span id="payNowText">Pay Now</span>
                    </button>
                </aside>

            </section>

        </div>
    </main>

    <?php include_once './includes/mobile-nav-bar.php'; ?>
    <?php include_once './includes/login-poup.php'; ?>
    <?php include_once './includes/register-poup.php'; ?>

    <div id="mmToast"></div>

    <?php include_once './includes/footer.php'; ?>

    <!-- ================= COUPON DRAWER MODAL ================= -->
    <div class="mm-coupon-drawer-overlay" id="couponOverlay">
        <div class="mm-coupon-drawer" id="couponDrawer">
            <div class="mm-coupon-drawer-head">
                <div>
                    <h3>Available Coupons</h3>
                    <p>Apply a code to save on this order</p>
                </div>
                <button type="button" class="mm-coupon-drawer-close" id="couponClose"><i class="bi bi-x-lg"></i></button>
            </div>

            <div class="mm-coupon-manual">
                <input type="text" id="couponInput" class="mm-coupon-manual-input" placeholder="ENTER CODE" maxlength="50" autocomplete="off" spellcheck="false">
                <button type="button" id="couponApplyBtn" class="mm-coupon-manual-btn">Apply</button>
            </div>
            <div class="mm-coupon-msg" id="couponMsg"></div>

            <div class="mm-coupon-list" id="couponList">
                <div class="mm-coupon-loading"><i class="bi bi-hourglass-split"></i> Loading coupons...</div>
            </div>
        </div>
    </div>

    <script>
        window.MAIN_URL = "<?= MAIN_URL ?>";
        window.ADMIN_URL = "<?= ADMIN_URL ?>";
        window.IS_LOGGED_IN = <?= $customerLoggedIn ? 'true' : 'false' ?>;
        window.CART_COUNT = <?= (int)$cartCount ?>;
        window.CART_SUBTOTAL = <?= (int)$discountedSubtotalRound ?>;
        window.CART_DISCOUNT = <?= (int)$discountAmountRound ?>;
        window.DISCOUNT_LABEL = "<?= htmlspecialchars($displayDiscountLabel) ?>";
        window.DISCOUNT_ACTIVE = <?= $discountAmountRound > 0 ? 'true' : 'false' ?>;
        window.RAZORPAY_KEY_ID = "<?= htmlspecialchars($razorpayKeyId) ?>";
        window.COUPON_CODE = "<?= htmlspecialchars($couponCode) ?>";

        window.CUSTOMER_NAME = "<?= htmlspecialchars($customerName, ENT_QUOTES) ?>";
        window.CUSTOMER_MOBILE = "<?= htmlspecialchars($customerMobile, ENT_QUOTES) ?>";
        window.CUSTOMER_CODE = "<?= htmlspecialchars($customerCode, ENT_QUOTES) ?>";

        window.CUSTOMER_ADDRESS_TYPE = "<?= htmlspecialchars($customerAddressType, ENT_QUOTES) ?>";
        window.CUSTOMER_APARTMENT_ID = <?= (int)$customerApartmentId ?>;
        window.CUSTOMER_APARTMENT_CODE = "<?= htmlspecialchars($customerApartmentCode, ENT_QUOTES) ?>";
        window.CUSTOMER_APARTMENT_NAME = "<?= htmlspecialchars($customerApartmentName, ENT_QUOTES) ?>";
        window.CUSTOMER_DIVISION = "<?= htmlspecialchars($customerDivision, ENT_QUOTES) ?>";
        window.CUSTOMER_DIVISION_CHARGE = <?= (float)$customerDivisionCharge ?>;
        window.CUSTOMER_FULL_ADDRESS = "<?= htmlspecialchars($customerFullAddress, ENT_QUOTES) ?>";
        window.CUSTOMER_MAP_LINK = "<?= htmlspecialchars($customerMapLink, ENT_QUOTES) ?>";
        window.CUSTOMER_HOUSE_IMAGE = "<?= htmlspecialchars($customerHouseImage, ENT_QUOTES) ?>";

        window.WALLET_BALANCE = <?= (float)$customerWalletBalance ?>;
        window.WALLET_APPLIED = <?= (int)$walletAppliedRound ?>;
        window.PAYABLE_NOW = <?= (int)$payableNowRound ?>;

        window.CONTAINER_TOTAL = <?= (int)$containerTotalRound ?>;
        window.CONTAINER_COUNT = <?= (int)$totalContainers ?>;

        window.TAX_SETTINGS = {
            status: <?= (int)$taxStatus ?>,
            rate: <?= (float)$taxRate ?>,
            type: "<?= htmlspecialchars($taxType) ?>"
        };
        window.TAX_AMOUNT_INITIAL = <?= (int)$taxAmountRound ?>;
    </script>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script src="<?= MAIN_URL ?>js/checkout.js"></script>

    <!-- Coupon Drawer Logic -->
    <script>
        (function() {
            const trigger = document.getElementById('couponTrigger');
            const overlay = document.getElementById('couponOverlay');
            const closeBtn = document.getElementById('couponClose');
            const couponList = document.getElementById('couponList');
            const couponInput = document.getElementById('couponInput');
            const applyBtn = document.getElementById('couponApplyBtn');
            const couponMsg = document.getElementById('couponMsg');

            function showMsg(msg, type) {
                if (!couponMsg) return;
                couponMsg.textContent = msg;
                couponMsg.className = 'mm-coupon-msg ' + (type || '');
            }

            function openDrawer() {
                overlay.classList.add('show');
                document.body.style.overflow = 'hidden';
                loadCoupons();
            }

            function closeDrawer() {
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }

            if (trigger) trigger.addEventListener('click', openDrawer);
            if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) closeDrawer();
            });

            // Load coupons
            function loadCoupons() {
                couponList.innerHTML = '<div class="mm-coupon-loading"><i class="bi bi-hourglass-split"></i> Loading...</div>';
                fetch('<?= MAIN_URL ?>ajax/apply-coupon.php', {
                        credentials: 'same-origin'
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (!res.success || !res.data || !res.data.length) {
                            couponList.innerHTML = '<div class="mm-coupon-loading">No coupons available right now.</div>';
                            return;
                        }
                        const applied = "<?= htmlspecialchars($couponCode) ?>";
                        couponList.innerHTML = res.data.map(c => `
                        <div class="mm-coupon-card" data-code="${c.code}">
                            <div class="mm-coupon-card-icon"><i class="bi bi-ticket-perforated-fill"></i></div>
                            <div class="mm-coupon-card-info">
                                <p class="mm-coupon-card-title">${c.name} — ${c.label}</p>
                                <span class="mm-coupon-card-code">${c.code}</span>
                            </div>
                            ${applied === c.code 
                                ? `<button type="button" class="mm-coupon-card-remove" data-code="${c.code}">Remove</button>`
                                : `<button type="button" class="mm-coupon-card-apply" data-code="${c.code}">Apply</button>`
                            }
                        </div>
                    `).join('');

                        // Bind apply buttons
                        couponList.querySelectorAll('.mm-coupon-card-apply').forEach(btn => {
                            btn.addEventListener('click', function(e) {
                                e.stopPropagation();
                                applyCoupon(this.dataset.code);
                            });
                        });

                        // Bind remove buttons
                        couponList.querySelectorAll('.mm-coupon-card-remove').forEach(btn => {
                            btn.addEventListener('click', function(e) {
                                e.stopPropagation();
                                removeCoupon();
                            });
                        });
                    })
                    .catch(() => {
                        couponList.innerHTML = '<div class="mm-coupon-loading">Unable to load coupons.</div>';
                    });
            }

            function applyCoupon(code) {
                applyBtn.disabled = true;
                showMsg('Applying...', '');

                fetch('<?= MAIN_URL ?>ajax/apply-coupon.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        credentials: 'same-origin',
                        body: 'code=' + encodeURIComponent(code)
                    })
                    .then(r => r.json())
                    .then(res => {
                        applyBtn.disabled = false;
                        if (res.success) {
                            showMsg(res.message, 'success');
                            setTimeout(() => window.location.reload(), 500);
                        } else {
                            showMsg(res.message || 'Invalid coupon.', 'error');
                        }
                    })
                    .catch(() => {
                        applyBtn.disabled = false;
                        showMsg('Unable to connect.', 'error');
                    });
            }

            function removeCoupon() {
                fetch('<?= MAIN_URL ?>ajax/apply-coupon.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        credentials: 'same-origin',
                        body: 'remove=1'
                    })
                    .then(r => r.json())
                    .then(() => window.location.reload())
                    .catch(() => window.location.reload());
            }

            if (applyBtn) {
                applyBtn.addEventListener('click', function() {
                    const code = (couponInput.value || '').trim().toUpperCase();
                    if (!code) {
                        showMsg('Please enter a coupon code.', 'error');
                        return;
                    }
                    applyCoupon(code);
                });
            }
            if (couponInput) {
                couponInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        applyBtn.click();
                    }
                });
            }
        })();
    </script>

</body>

</html>