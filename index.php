<?php
require_once './config/config.php';
require_once './config/function.php';

$settings = getSettings($pdo);

$siteName = $settings['username'] ?? 'Mrs Mill@';
$logoUrl  = !empty($settings['logo_image'])    ? ADMIN_URL . $settings['logo_image']    : '';
$favicon  = !empty($settings['favicon_image']) ? ADMIN_URL . $settings['favicon_image'] : '';

/* =========================================================
   MENU (needed for discount anchoring)
   ========================================================= */
$activeMenu   = getActiveMenu($pdo);
$isMenuActive = $activeMenu !== null;

/* =========================================================
   CURRENT DISCOUNT (anchored to menu launch)
   ========================================================= */
$currentDiscount   = null;
$discountBadgeText = '';

if ($isMenuActive) {
    try {
        $menuStart = strtotime($activeMenu['start_at']);
        $menuEnd   = strtotime($activeMenu['end_at']);
        $now       = time();

        if ($now >= $menuStart && $now <= $menuEnd) {

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
    } catch (PDOException $e) {
        $currentDiscount = null;
    }
}

if ($currentDiscount && $currentDiscount['amount'] > 0) {
    $discountBadgeText = $currentDiscount['amount_type'] === 'percent'
        ? number_format($currentDiscount['amount'], 0) . '% OFF'
        : '₹' . number_format($currentDiscount['amount'], 0) . ' OFF';
}

/* =========================================================
   MENU PRODUCTS / CATEGORIES
   ========================================================= */
$categories = [];
$products   = [];
$menuItems  = [];

if ($isMenuActive) {
    $menuItems  = getMenuProducts($pdo, 500);
    $products   = $menuItems;
    $categories = getMenuCategories($pdo, 50);
}

/* Logged-in customer */
$customerLoggedIn = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0;
$customerName     = $_SESSION['customer_name'] ?? '';
$customerMobile   = $_SESSION['customer_mobile'] ?? '';

/* Cart */
$cartCount = 0;
$cartTotal = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $c) {
        $qty = (int)($c['qty'] ?? 0);
        $cartCount += $qty;
        $cartTotal += ((float)($c['price'] ?? 0)) * $qty;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include_once './includes/head_links.php'; ?>

    <style>
        /* =====================================================
           PRODUCT CARD — 3D DISCOUNT BADGE (bottom-right)
           ===================================================== */
        .mm-product-img {
            position: relative;
        }

        .mm-discount-badge {
            position: absolute;
            bottom: 10px;
            left: 10px;
            top: auto;
            right: auto;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 12px;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #fff;
            line-height: 1;
            border-radius: 9px;
            background: linear-gradient(145deg, #d42a3a 0%, #b51f2c 45%, #7a1220 100%);
            box-shadow:
                0 1px 0 rgba(255, 255, 255, .25) inset,
                0 -1px 0 rgba(0, 0, 0, .25) inset,
                0 3px 0 #5c0d18,
                0 6px 12px rgba(181, 31, 44, .45);
            transform: rotate(-3deg);
            z-index: 3;
            text-shadow: 0 1px 0 rgba(0, 0, 0, .35);
        }

        .mm-discount-badge::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, #fff5c0, #ffd966 60%, #b8893c);
            box-shadow: 0 0 5px rgba(255, 217, 102, .9);
            display: inline-block;
        }
    </style>
</head>

<body>

    <!-- ================= NAVBAR ================= -->
    <?php include_once './includes/nav-bar.php'; ?>


    <!-- ================= MAIN ================= -->
    <main class="mm-main">
        <div class="mm-container">

            <?php if ($isMenuActive && !empty($products)): ?>

                <!-- ============= CATEGORIES ============= -->
                <?php if (!empty($categories)): ?>
                    <section style="padding-top:28px;">
                        <div class="mm-section-head">
                            <div>
                                <h2 class="mm-section-title">Shop by Category</h2>
                                <p class="mm-section-sub">
                                    <?= htmlspecialchars($activeMenu['menu_name'] ?? 'Today\'s Menu') ?>
                                </p>
                            </div>
                            <?php if (count($categories) > 4): ?>
                                <div class="mm-cat-nav">
                                    <button onclick="scrollCategories(-250)" class="mm-cat-btn" aria-label="Previous">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                                            <path d="M15 18l-6-6 6-6" />
                                        </svg>
                                    </button>
                                    <button onclick="scrollCategories(250)" class="mm-cat-btn" aria-label="Next">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14">
                                            <path d="M9 6l6 6-6 6" />
                                        </svg>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div id="categoryScroll" class="mm-cat-scroll">
                            <?php foreach ($categories as $c):
                                $img = categoryImageUrl($c['category_image'] ?? '');
                            ?>
                                <a href="category.php?slug=<?= urlencode($c['category_slug']) ?>" class="mm-cat-card">
                                    <div class="mm-cat-img">
                                        <?php if ($img): ?>
                                            <img src="<?= htmlspecialchars($img) ?>" alt="">
                                        <?php else: ?>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                                                <path d="M3 6h18" />
                                                <path d="M16 10a4 4 0 0 1-8 0" />
                                            </svg>
                                        <?php endif; ?>
                                    </div>
                                    <p class="mm-cat-name"><?= htmlspecialchars($c['category_name']) ?></p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>


                <!-- ============= TODAY'S MENU ============= -->
                <section style="margin-top:48px; margin-bottom:80px;">
                    <div class="mm-section-head">
                        <div>
                            <h2 class="mm-section-title">Today's Menu</h2>
                            <p class="mm-section-sub">
                                <?= htmlspecialchars($activeMenu['menu_name'] ?? 'Handpicked for today') ?>
                            </p>
                        </div>
                    </div>

                    <div class="mm-products">
                        <?php foreach ($products as $p):
                            $img = productImageUrl($p['product_image'] ?? '');
                        ?>
                            <div class="mm-product">
                                <div class="mm-product-img">
                                    <?php if ($img): ?>
                                        <img src="<?= htmlspecialchars($img) ?>" alt="">
                                    <?php else: ?>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                            <path d="M3.3 7L12 12l8.7-5" />
                                            <path d="M12 22V12" />
                                        </svg>
                                    <?php endif; ?>

                                    <?php if ($discountBadgeText): ?>
                                        <span class="mm-discount-badge"><?= htmlspecialchars($discountBadgeText) ?></span>
                                    <?php endif; ?>
                                </div>

                                <p class="mm-product-cat"><?= htmlspecialchars($p['category_name'] ?? '') ?></p>
                                <h3 class="mm-product-name"><?= htmlspecialchars($p['product_name']) ?></h3>
                                <p class="mm-product-price">₹<?= number_format((float)$p['min_price'], 0) ?></p>

                                <button type="button"
                                    class="mm-buy-btn js-buy-now"
                                    data-product-id="<?= (int)$p['id'] ?>"
                                    data-product-name="<?= htmlspecialchars($p['product_name'], ENT_QUOTES) ?>">
                                    <svg viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M13 2L3 14h7l-1 8 10-12h-7l1-8z" />
                                    </svg>
                                    Buy Now
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

            <?php else: ?>

                <!-- ============= EMPTY STATE ============= -->
                <section style="padding-top:40px; padding-bottom:120px;">
                    <div class="mm-empty">
                        <div class="mm-empty-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="9" />
                                <path d="M12 7v5l3 2" />
                            </svg>
                        </div>
                        <h3>No menu available right now</h3>
                        <p>
                            Our menu opens at scheduled times.<br>
                            Please check back soon for today's fresh selection.
                        </p>
                    </div>
                </section>

            <?php endif; ?>

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


    <!-- ================= VARIANT POPUP ================= -->
    <?php include_once './includes/variant-poup.php'; ?>


    <div id="mmToast"></div>


    <!-- ================= GLOBALS ================= -->
    <script>
        window.MAIN_URL = "<?= MAIN_URL ?>";
        window.ADMIN_URL = "<?= ADMIN_URL ?>";
        window.IS_LOGGED_IN = <?= $customerLoggedIn ? 'true' : 'false' ?>;
        window.CART_COUNT = <?= (int)$cartCount ?>;
        window.DISCOUNT = <?= json_encode($currentDiscount, JSON_UNESCAPED_UNICODE) ?>;
    </script>

    <script>
        function scrollCategories(amount) {
            const container = document.getElementById("categoryScroll");
            if (!container) return;
            container.scrollBy({
                left: amount,
                behavior: "smooth"
            });
        }
    </script>

    <script src="<?= MAIN_URL ?>js/home.js"></script>

</body>

</html>