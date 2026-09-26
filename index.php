<?php
require_once './config/config.php';
require_once './config/function.php';

$settings = getSettings($pdo);

$siteName = $settings['username'] ?? 'Mrs Mill@';
$logoUrl  = !empty($settings['logo_image'])    ? ADMIN_URL . $settings['logo_image']    : '';
$favicon  = !empty($settings['favicon_image']) ? ADMIN_URL . $settings['favicon_image'] : '';

/* =========================================================
   MENU-ONLY MODE
   ========================================================= */
$activeMenu   = getActiveMenu($pdo);
$isMenuActive = $activeMenu !== null;

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

/* Cart count + total */
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
                <section style="margin-top:48px;">
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

        (function() {
            var bar = document.getElementById('stickyCartBar');
            var count = Number(window.CART_COUNT || 0);
            if (!bar) return;
            bar.style.display = count > 0 ? 'flex' : 'none';
        })();
    </script>

    <script src="<?= MAIN_URL ?>js/home.js"></script>

</body>

</html>