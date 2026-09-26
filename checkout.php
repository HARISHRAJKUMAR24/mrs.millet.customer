<?php
require_once './config/config.php';
require_once './config/function.php';

$settings = getSettings($pdo);

$siteName = $settings['username'] ?? 'Mrs Mill@';
$logoUrl  = !empty($settings['logo_image'])    ? ADMIN_URL . $settings['logo_image']    : '';
$favicon  = !empty($settings['favicon_image']) ? ADMIN_URL . $settings['favicon_image'] : '';

/* Logged-in customer (optional — checkout works without login) */
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

/* Empty cart → back to home */
if ($cartCount <= 0) {
    header('Location: ' . MAIN_URL . 'index.php');
    exit;
}

/* Razorpay key id from DB */
$razorpayKeyId = $settings['razorpay_key_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include_once './includes/head_links.php'; ?>
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

                    <!-- ===== Mobile ===== -->
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

                    <!-- ===== Address ===== -->
                    <div class="mm-co-card">
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

                    <!-- ===== Payment ===== -->
                    <div class="mm-co-card">
                        <h2 class="mm-co-title">Payment</h2>
                        <p class="mm-co-hint">Pay securely via UPI / Card / NetBanking.</p>
                    </div>

                </form>


                <!-- ================= RIGHT: SUMMARY ================= -->
                <aside class="mm-co-summary">
                    <h2 class="mm-co-title">Order Summary</h2>

                    <div class="mm-co-items">
                        <?php foreach ($cart as $item): ?>
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
                                    ₹<?= number_format(((float)$item['price']) * ((int)$item['qty']), 2) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mm-co-totals">
                        <div class="mm-co-row">
                            <span>Subtotal</span>
                            <span id="subTotal">₹<?= number_format($cartTotal, 2) ?></span>
                        </div>
                        <div class="mm-co-row">
                            <span>Delivery charge</span>
                            <span id="deliveryCharge">₹0.00</span>
                        </div>
                        <div class="mm-co-row mm-co-total">
                            <span>Total</span>
                            <span id="grandTotal">₹<?= number_format($cartTotal, 2) ?></span>
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
        window.CART_SUBTOTAL = <?= (float)$cartTotal ?>;
        window.RAZORPAY_KEY_ID = "<?= htmlspecialchars($razorpayKeyId) ?>";
        window.CUSTOMER_NAME = "<?= htmlspecialchars($customerName, ENT_QUOTES) ?>";
        window.CUSTOMER_MOBILE = "<?= htmlspecialchars($customerMobile, ENT_QUOTES) ?>";
    </script>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script src="<?= MAIN_URL ?>js/checkout.js"></script>

</body>

</html>