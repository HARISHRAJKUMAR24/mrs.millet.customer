<?php
require_once './config/config.php';
require_once './config/function.php';

$settings = getSettings($pdo);

$siteName = $settings['username'] ?? 'Mrs Mill@';
$logoUrl  = !empty($settings['logo_image'])    ? ADMIN_URL . $settings['logo_image']    : '';
$favicon  = !empty($settings['favicon_image']) ? ADMIN_URL . $settings['favicon_image'] : '';

/* Logged-in customer */
$customerLoggedIn = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0;
$customerName     = $_SESSION['customer_name'] ?? '';
$customerMobile   = $_SESSION['customer_mobile'] ?? '';

/* Cart */
$cart      = $_SESSION['cart'] ?? [];
$cartCount = 0;
$cartTotal = 0;
foreach ($cart as $c) {
    $qty        = (int)($c['qty'] ?? 0);
    $cartCount += $qty;
    $cartTotal += ((float)($c['price'] ?? 0)) * $qty;
}

/* Empty cart → home */
if ($cartCount <= 0) {
    header('Location: ' . MAIN_URL . 'index.php');
    exit;
}

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
         WHERE d.status = 1
           AND d.discount_type = 'time'
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
    $currentDiscount = null;
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

$discountedSubtotal = max(0, $cartTotal - $discountAmount);

$cartTotalRound          = (int)round($cartTotal);
$discountAmountRound     = (int)round($discountAmount);
$discountedSubtotalRound = (int)round($discountedSubtotal);

$razorpayKeyId = $settings['razorpay_key_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include_once './includes/head_links.php'; ?>

    <style>
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

        .mm-mode-option input:checked~.mm-mode-body .mm-mode-title {
            color: #b51f2c;
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

        /* Branch select styling */
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
            -moz-appearance: none;
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
    </style>
</head>

<body>

    <!-- ================= NAVBAR ================= -->
    <?php include_once './includes/nav-bar.php'; ?>


    <!-- ================= MAIN ================= -->
    <main class="mm-main">
        <div class="mm-container">

            <nav class="mm-crumb" aria-label="Breadcrumb">
                <a href="<?= MAIN_URL ?>">Home</a>
                <span class="sep">/</span>
                <a href="cart.php">Cart</a>
                <span class="sep">/</span>
                <span style="color:#302923;font-weight:600;">Checkout</span>
            </nav>

            <section class="mm-co-wrap">

                <!-- ================= LEFT: FORM ================= -->
                <form id="checkoutForm" class="mm-co-form" autocomplete="off" novalidate>

                    <!-- ===== Contact ===== -->
                    <div class="mm-co-card">
                        <h2 class="mm-co-title">Contact</h2>

                        <div class="mm-co-field">
                            <label for="coMobile">Mobile Number</label>
                            <div class="mm-co-input-wrap">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.2-1.2a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.7 2z" />
                                </svg>
                                <input type="tel" id="coMobile" class="mm-co-input"
                                    placeholder="10-digit mobile"
                                    maxlength="15" inputmode="numeric"
                                    value="<?= htmlspecialchars($customerMobile) ?>" required>
                            </div>
                            <p class="mm-co-hint" id="mobileHint">We'll use this for delivery updates.</p>
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


                    <!-- ===== Delivery Mode ===== -->
                    <div class="mm-co-card">
                        <h2 class="mm-co-title">How would you like to receive?</h2>

                        <div class="mm-mode-wrap">
                            <label class="mm-mode-option">
                                <input type="radio" name="delivery_mode" value="delivery" checked>
                                <span class="mm-mode-radio"></span>
                                <div class="mm-mode-body">
                                    <p class="mm-mode-title">Home Delivery</p>
                                    <p class="mm-mode-sub">Deliver to your apartment</p>
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

                        <!-- Store info + Branch dropdown shown only for pickup -->
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


                    <!-- ===== Address (hidden when pickup) ===== -->
                    <div class="mm-co-card mm-address-block" id="addressBlock">
                        <h2 class="mm-co-title">Delivery Address</h2>

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

                            <input type="hidden" id="coApartmentId">
                            <input type="hidden" id="coApartmentCode">

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
                            <div id="divisionResults" class="mm-co-dropdown" role="listbox"></div>
                            <p class="mm-co-hint" id="divisionHint"></p>
                        </div>
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
                            <span id="subTotal">₹<?= number_format($cartTotalRound, 0) ?></span>
                        </div>

                        <?php if ($discountAmountRound > 0): ?>
                            <div class="mm-co-row" style="color:#1f7a3d;">
                                <span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12" style="vertical-align:-2px;margin-right:4px;">
                                        <path d="M20.59 13.41L13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" />
                                        <line x1="7" y1="7" x2="7.01" y2="7" />
                                    </svg>
                                    Discount (<?= htmlspecialchars($discountLabel) ?>)
                                </span>
                                <span style="color:#1f7a3d;font-weight:800;">−₹<?= number_format($discountAmountRound, 0) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="mm-co-row" id="deliveryRow">
                            <span id="deliveryLabel">Delivery charge</span>
                            <span id="deliveryCharge">₹0</span>
                        </div>

                        <div class="mm-co-row mm-co-total">
                            <span>Total</span>
                            <span id="grandTotal">₹<?= number_format($discountedSubtotalRound, 0) ?></span>
                        </div>
                    </div>

                    <button type="button" id="payNowBtn" class="mm-co-pay-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="5" width="20" height="14" rx="2" />
                            <path d="M2 10h20" />
                        </svg>
                        Pay Now
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


    <!-- ================= STICKY CART BAR ================= -->
    <?php include_once './includes/sticky-cart.php'; ?>


    <!-- ================= MOBILE BOTTOM NAV ================= -->
    <?php include_once './includes/mobile-nav-bar.php'; ?>


    <!-- ================= LOGIN POPUP ================= -->
    <?php include_once './includes/login-poup.php'; ?>


    <!-- ================= REGISTER POPUP ================= -->
    <?php include_once './includes/register-poup.php'; ?>


    <div id="mmToast"></div>


    <!-- ================= GLOBALS ================= -->
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
    </script>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script src="<?= MAIN_URL ?>js/checkout.js"></script>

</body>

</html>