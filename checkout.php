<?php
require_once './config/config.php';
require_once './config/function.php';

$settings = getSettings($pdo);

$siteName = $settings['username'] ?? 'Mrs Mill@';
$logoUrl  = !empty($settings['logo_image'])    ? ADMIN_URL . $settings['logo_image']    : '';
$favicon  = !empty($settings['favicon_image']) ? ADMIN_URL . $settings['favicon_image'] : '';

/* =========================================================
   LOGGED-IN CUSTOMER
   customer.id            = integer (internal FK)
   customer.customer_id   = varchar MRSMCxxxxx (orders FK)
   customer.address_type  = 'apartment' | 'individual_house'
   customer.address_json  = JSON blob (details)
   ========================================================= */
$customerLoggedIn = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0;
$customerRowId    = $customerLoggedIn ? (int)$_SESSION['customer_id'] : 0;

$customerCode    = $_SESSION['customer_code']   ?? ''; // MRSMCxxxxx
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
   CONTAINER DEPOSIT — computed but NOT shown on checkout
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
   CURRENT DISCOUNT
   ========================================================= */
$currentDiscount = null;
$discountAmount  = 0;
$discountLabel   = '';

try {
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

    $activeMenuForDiscount = getActiveMenu($pdo);

    if ($activeMenuForDiscount) {
        $menuStart = strtotime($activeMenuForDiscount['start_at']);
        $menuEnd   = strtotime($activeMenuForDiscount['end_at']);
        $now       = time();

        if ($now >= $menuStart && $now <= $menuEnd) {
            $prevEndEpoch = $menuStart;
            $baseDay      = date('Y-m-d', $menuStart);

            foreach ($slots as $s) {
                $slotEndTs = strtotime($baseDay . ' ' . $s['end_time']);
                if ($slotEndTs < $prevEndEpoch) $slotEndTs += 86400;

                if ($now >= $prevEndEpoch && $now <= $slotEndTs) {
                    $currentDiscount = [
                        'name'        => $s['discount_name'],
                        'slot_name'   => $s['slot_name'],
                        'amount_type' => $s['amount_type'],
                        'amount'      => (float)$s['discount_amount'],
                        'delivery'    => (int)$s['delivery_enabled'],
                    ];
                    break;
                }
                $prevEndEpoch = $slotEndTs;
            }
        }
    }
} catch (PDOException $e) {
}

if ($currentDiscount && $currentDiscount['amount'] > 0) {
    if ($currentDiscount['amount_type'] === 'percent') {
        $discountAmount = round(($cartTotal * $currentDiscount['amount']) / 100);
        $discountLabel  = number_format($currentDiscount['amount'], 0) . '% OFF';
    } else {
        $discountAmount = min((float)$currentDiscount['amount'], $cartTotal);
        $discountLabel  = '₹' . number_format($currentDiscount['amount'], 0) . ' OFF';
    }
}

$discountAmountRound     = (int)round((float)$discountAmount);
$discountedSubtotal      = max(0, $cartTotal - $discountAmount);
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
        .mm-mode-wrap { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .mm-mode-option {
            position: relative; display: flex; align-items: center; gap: 10px;
            padding: 14px 14px 14px 12px;
            border: 1.5px solid #ece5da; background: #fffdf9; border-radius: 12px;
            cursor: pointer; transition: .18s ease; user-select: none;
        }
        .mm-mode-option:hover { border-color: #d98a91; background: #fff5f5; }
        .mm-mode-option input { position: absolute; opacity: 0; pointer-events: none; }
        .mm-mode-radio {
            width: 20px; height: 20px; border-radius: 50%;
            border: 2px solid #d5cbbd; flex-shrink: 0;
            position: relative; transition: .15s ease;
        }
        .mm-mode-option input:checked~.mm-mode-radio { border-color: #b51f2c; }
        .mm-mode-option input:checked~.mm-mode-radio::after {
            content: ""; position: absolute; inset: 3px;
            background: #b51f2c; border-radius: 50%;
        }
        .mm-mode-option:has(input:checked) {
            border-color: #b51f2c; background: #fff5f5;
            box-shadow: 0 0 0 3px rgba(181, 31, 44, .06);
        }
        .mm-mode-body { flex: 1; min-width: 0; }
        .mm-mode-title { font-size: 12.5px; font-weight: 800; color: #302923; margin: 0; line-height: 1.2; }
        .mm-mode-sub { font-size: 10.5px; color: #948c82; margin: 3px 0 0; font-weight: 600; line-height: 1.3; }
        .mm-mode-option svg.mm-mode-icon { width: 16px; height: 16px; color: #b51f2c; flex-shrink: 0; }

        .mm-pickup-info {
            margin-top: 14px; padding: 14px; border-radius: 12px;
            background: linear-gradient(135deg, #fdf7ec 0%, #fbe8e9 100%);
            border: 1px solid #f3dca5; display: none;
        }
        .mm-pickup-info.show { display: block; }
        .mm-pickup-row { display: flex; gap: 10px; font-size: 12px; color: #6f5a3f; padding: 4px 0; }
        .mm-pickup-row strong { color: #302923; }
        .mm-pickup-row svg { width: 14px; height: 14px; color: #b8893c; flex-shrink: 0; margin-top: 2px; }

        .mm-address-block.hidden { display: none !important; }

        .mm-branch-select {
            width: 100%; height: 46px;
            border: 1.5px solid #ece5da; background: #fffdf9; border-radius: 12px;
            padding: 0 40px 0 40px; font-family: inherit; font-size: 13px; font-weight: 600;
            color: #292521; cursor: pointer; appearance: none; -webkit-appearance: none;
            outline: none; transition: .2s ease;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23948c82' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
            background-repeat: no-repeat; background-position: right 14px center; background-size: 14px;
        }
        .mm-branch-select:focus { border-color: #b51f2c; background-color: #fff; box-shadow: 0 0 0 4px rgba(181, 31, 44, .08); }

        .mm-wallet-box {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; padding: 14px 16px; border-radius: 12px;
            background: linear-gradient(135deg, #e8f6ea 0%, #d3edd8 100%);
            border: 1.5px solid #a7c8a9; margin-top: 14px; margin-bottom: 6px;
        }
        .mm-wallet-box .left { display: flex; align-items: center; gap: 12px; }
        .mm-wallet-icon {
            width: 40px; height: 40px; border-radius: 12px;
            background: #1f7a3d; color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
        }
        .mm-wallet-label {
            font-size: 10px; font-weight: 800; color: #4e6f52;
            text-transform: uppercase; letter-spacing: .07em;
        }
        .mm-wallet-value {
            font-family: "Playfair Display", serif;
            font-size: 20px; font-weight: 700;
            color: #1b5e20; line-height: 1.1; margin-top: 2px;
        }
        .mm-wallet-msg {
            font-size: 11.5px; color: #2e7d32; font-weight: 700;
            text-align: right; line-height: 1.35;
        }
        .mm-wallet-msg.warn { color: #a35a0e; }
        .mm-co-row.tax-row { color: #b8893c; }
        .mm-co-row.tax-row span:last-child { color: #b8893c; font-weight: 800; }

        /* ============ READONLY ADDRESS ============ */
        .mm-addr-readonly {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 14px; border-radius: 12px;
            background: #fdfaf4; border: 1.5px solid #ece5da;
        }
        .mm-addr-ro-icon {
            width: 40px; height: 40px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 17px; color: #fff; flex-shrink: 0;
        }
        .mm-addr-ro-icon.apartment { background: linear-gradient(135deg, #a35a0e 0%, #7a4208 100%); }
        .mm-addr-ro-icon.house     { background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%); }
        .mm-addr-ro-body { flex: 1; min-width: 0; }
        .mm-addr-ro-title {
            font-size: 13px; font-weight: 800; color: #302923;
            margin: 0 0 3px; line-height: 1.3;
            word-break: break-word;
        }
        .mm-addr-ro-meta {
            font-size: 11.5px; color: #6f5a3f;
            margin: 2px 0 0; font-weight: 600;
        }
        .mm-addr-ro-meta strong { color: #b51f2c; }
        .mm-addr-ro-badge {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 9.5px; font-weight: 800; letter-spacing: .04em;
            padding: 3px 8px; border-radius: 6px; margin-top: 6px;
        }
        .mm-addr-ro-badge.apartment { background: #fdf1e2; color: #a35a0e; }
        .mm-addr-ro-badge.house     { background: #eef7f0; color: #2e7d32; }

        .mm-addr-ro-links { display: flex; flex-direction: column; gap: 6px; margin-top: 8px; }
        .mm-addr-ro-link {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 11.5px; font-weight: 700; text-decoration: none;
            padding: 6px 10px; border-radius: 8px;
            width: fit-content;
        }
        .mm-addr-ro-link.map   { background: #fdf1e2; color: #a35a0e; border: 1px dashed #f3dca5; }
        .mm-addr-ro-link.photo { background: #eef7f0; color: #2e7d32; border: 1px dashed #c9e3ce; }
        .mm-addr-ro-link svg   { width: 13px; height: 13px; }

        .mm-co-input[readonly] {
            background: #f7f2ec; color: #6f5a3f; cursor: not-allowed;
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

                    <!-- =========================================================
                         DELIVERY ADDRESS — two variants
                         ========================================================= -->
                    <div class="mm-co-card mm-address-block" id="addressBlock">
                        <h2 class="mm-co-title">Delivery Address</h2>

                        <?php if ($customerLoggedIn && $customerAddressType === 'individual_house' && $customerFullAddress !== ''): ?>

                            <!-- ========= INDIVIDUAL HOUSE — READ-ONLY ========= -->
                            <div class="mm-addr-readonly">
                                <div class="mm-addr-ro-icon house">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;">
                                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                        <polyline points="9 22 9 12 15 12 15 22"/>
                                    </svg>
                                </div>
                                <div class="mm-addr-ro-body">
                                    <p class="mm-addr-ro-title"><?= htmlspecialchars($customerFullAddress) ?></p>
                                    <span class="mm-addr-ro-badge house">
                                        <i class="bi bi-house-door"></i> Individual House
                                    </span>

                                    <div class="mm-addr-ro-links">
                                        <?php if ($customerMapLink !== ''): ?>
                                            <a href="<?= htmlspecialchars($customerMapLink) ?>" target="_blank" rel="noopener noreferrer" class="mm-addr-ro-link map">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                                    <circle cx="12" cy="10" r="3"/>
                                                </svg>
                                                View on Map
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($customerHouseImage !== ''): ?>
                                            <a href="<?= htmlspecialchars(MAIN_URL . $customerHouseImage) ?>" target="_blank" rel="noopener noreferrer" class="mm-addr-ro-link photo">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                                    <path d="M21 15l-5-5L5 21"/>
                                                </svg>
                                                View House Photo
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Hidden inputs so JS can submit these reliably -->
                            <input type="hidden" id="coAddressType"  value="individual_house">
                            <input type="hidden" id="coFullAddress"  value="<?= htmlspecialchars($customerFullAddress) ?>">
                            <input type="hidden" id="coMapLink"      value="<?= htmlspecialchars($customerMapLink) ?>">
                            <input type="hidden" id="coHouseImage"   value="<?= htmlspecialchars($customerHouseImage) ?>">

                        <?php elseif ($customerLoggedIn && $customerApartmentId > 0): ?>

                            <!-- ========= APARTMENT — READ-ONLY ========= -->
                            <div class="mm-addr-readonly">
                                <div class="mm-addr-ro-icon apartment">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;">
                                        <path d="M3 21V8l9-5 9 5v13"/>
                                        <path d="M9 21V12h6v9"/>
                                    </svg>
                                </div>
                                <div class="mm-addr-ro-body">
                                    <p class="mm-addr-ro-title"><?= htmlspecialchars($customerApartmentName) ?></p>
                                    <p class="mm-addr-ro-meta">
                                        <strong>#<?= htmlspecialchars($customerApartmentCode) ?></strong>
                                        · <?= htmlspecialchars($customerDivision) ?>
                                    </p>
                                    <span class="mm-addr-ro-badge apartment">
                                        <i class="bi bi-building"></i> Apartment
                                    </span>
                                    <?php if ($customerDivisionCharge > 0): ?>
                                        <p class="mm-addr-ro-meta" style="margin-top:8px;">
                                            Delivery charge:
                                            <strong>₹<?= number_format($customerDivisionCharge, 0) ?></strong>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <input type="hidden" id="coAddressType"        value="apartment">
                            <input type="hidden" id="coApartmentId"        value="<?= (int)$customerApartmentId ?>">
                            <input type="hidden" id="coApartmentCode"      value="<?= htmlspecialchars($customerApartmentCode) ?>">
                            <input type="hidden" id="coDivision"           value="<?= htmlspecialchars($customerDivision) ?>">
                            <input type="hidden" id="coDivisionCharge"     value="<?= (float)$customerDivisionCharge ?>">

                        <?php else: ?>

                            <!-- ========= GUEST or NO ADDRESS — manual picker ========= -->
                            <div class="mm-co-field" style="position:relative;">
                                <label for="coApartmentSearch">Apartment / Community</label>
                                <div class="mm-co-input-wrap">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 21V8l9-5 9 5v13" />
                                        <path d="M9 21V12h6v9" />
                                    </svg>
                                    <input type="text" id="coApartmentSearch" class="mm-co-input"
                                        placeholder="Search apartment..." autocomplete="off">
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
                                    <input type="text" id="coDivisionSearch" class="mm-co-input"
                                        placeholder="Search division..." autocomplete="off">
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
                                    <?php else: ?>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                            <path d="M3.3 7L12 12l8.7-5" />
                                            <path d="M12 22V12" />
                                        </svg>
                                    <?php endif; ?>
                                </div>
                                <div class="mm-co-item-info">
                                    <p class="mm-co-item-name"><?= htmlspecialchars($item['name'] ?? '') ?></p>
                                    <p class="mm-co-item-meta">
                                        <?= htmlspecialchars($item['variant_name'] ?? '') ?>
                                        <?php if (!empty($item['variant_qty'])): ?>
                                            · <?= htmlspecialchars($item['variant_qty']) ?>
                                        <?php endif; ?>
                                        · Qty <?= (int)$item['qty'] ?>
                                    </p>
                                </div>
                                <div class="mm-co-item-price">
                                    ₹<?= number_format($lineTotal, 0) ?>
                                </div>
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
                                <span>Discount (<?= htmlspecialchars($discountLabel) ?>)</span>
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

                        <div class="mm-co-row" id="walletRow" style="display:none;color:#1f7a3d;">
                            <span>Wallet applied</span>
                            <span style="color:#1f7a3d;font-weight:800;">−₹<span id="walletApplied">0</span></span>
                        </div>

                        <div class="mm-co-row mm-co-total">
                            <span>Payable Now</span>
                            <span id="grandTotal">₹<?= number_format($payableNowRound, 0) ?></span>
                        </div>
                    </div>

                    <div class="mm-wallet-box" id="walletBox" style="<?= ($customerLoggedIn && $customerWalletBalance > 0) ? '' : 'display:none;' ?>">
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
                            <?php if ($walletAppliedRound > 0): ?>
                                ₹<?= number_format($walletAppliedRound, 0) ?> applied
                            <?php else: ?>
                                No balance to apply
                            <?php endif; ?>
                        </div>
                    </div>

                    <button type="button" id="payNowBtn" class="mm-co-pay-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="5" width="20" height="14" rx="2" />
                            <path d="M2 10h20" />
                        </svg>
                        <span id="payNowText">Pay Now</span>
                    </button>

                    <p class="mm-co-secure">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2l8 4v6c0 5-3.5 9-8 10-4.5-1-8-5-8-10V6z" />
                        </svg>
                        Secure payment powered by Razorpay
                    </p>
                </aside>

            </section>

        </div>
    </main>

    <?php include_once './includes/mobile-nav-bar.php'; ?>
    <?php include_once './includes/login-poup.php'; ?>
    <?php include_once './includes/register-poup.php'; ?>

    <div id="mmToast"></div>

    <?php include_once './includes/footer.php'; ?>

    <script>
        window.MAIN_URL = "<?= MAIN_URL ?>";
        window.ADMIN_URL = "<?= ADMIN_URL ?>";
        window.IS_LOGGED_IN = <?= $customerLoggedIn ? 'true' : 'false' ?>;
        window.CART_COUNT = <?= (int)$cartCount ?>;
        window.CART_SUBTOTAL = <?= (int)$discountedSubtotalRound ?>;
        window.CART_DISCOUNT = <?= (int)$discountAmountRound ?>;
        window.DISCOUNT_LABEL = "<?= htmlspecialchars($discountLabel) ?>";
        window.DISCOUNT_ACTIVE = <?= $discountAmountRound > 0 ? 'true' : 'false' ?>;
        window.RAZORPAY_KEY_ID = "<?= htmlspecialchars($razorpayKeyId) ?>";

        window.CUSTOMER_NAME = "<?= htmlspecialchars($customerName, ENT_QUOTES) ?>";
        window.CUSTOMER_MOBILE = "<?= htmlspecialchars($customerMobile, ENT_QUOTES) ?>";
        window.CUSTOMER_CODE = "<?= htmlspecialchars($customerCode, ENT_QUOTES) ?>";

        /* Address data */
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
            rate:   <?= (float)$taxRate ?>,
            type:   "<?= htmlspecialchars($taxType) ?>"
        };
        window.TAX_AMOUNT_INITIAL = <?= (int)$taxAmountRound ?>;
    </script>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script src="<?= MAIN_URL ?>js/checkout.js"></script>

</body>

</html>