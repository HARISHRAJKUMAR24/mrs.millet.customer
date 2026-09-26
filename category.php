<?php
require_once './config/config.php';
require_once './config/function.php';

$settings = getSettings($pdo);

$siteName = $settings['username'] ?? 'Mrs Mill@';
$logoUrl  = !empty($settings['logo_image'])    ? ADMIN_URL . $settings['logo_image']    : '';
$favicon  = !empty($settings['favicon_image']) ? ADMIN_URL . $settings['favicon_image'] : '';

/* =========================================================
   Get category from slug
   ========================================================= */
$slug     = trim($_GET['slug'] ?? '');
$category = null;

if ($slug !== '') {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, category_name, category_slug, category_image
             FROM categories
             WHERE category_slug = ? AND status = 1
             LIMIT 1"
        );
        $stmt->execute([$slug]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        $category = null;
    }
}

/* =========================================================
   MENU-AWARE product loading
   - Menu active → only products in this category AND in the menu
   - No menu (expired/none) → all products in this category
   ========================================================= */
$activeMenu   = getActiveMenu($pdo);
$isMenuActive = $activeMenu !== null;

$products = [];

if ($category) {
    if ($isMenuActive) {
        try {
            $stmt = $pdo->prepare(
                "SELECT DISTINCT
                        p.id,
                        p.product_code,
                        p.product_name,
                        p.product_image,
                        p.category_id,
                        c.category_name,
                        (SELECT MIN(v.price) FROM product_variants v
                         WHERE v.product_code = p.product_code AND v.status = 1) AS min_price
                 FROM menu_products mp
                 INNER JOIN products p    ON p.product_code = mp.product_code
                 LEFT  JOIN categories c  ON c.id = p.category_id
                 WHERE mp.menu_code = ?
                   AND p.category_id = ?
                   AND p.status = 1
                 ORDER BY p.id ASC"
            );
            $stmt->execute([$activeMenu['menu_code'], $category['id']]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $products = [];
        }
    } else {
        try {
            $stmt = $pdo->prepare(
                "SELECT p.id, p.product_code, p.product_name, p.product_image,
                        p.category_id, c.category_name,
                        (SELECT MIN(v.price) FROM product_variants v
                         WHERE v.product_code = p.product_code AND v.status = 1) AS min_price
                 FROM products p
                 LEFT JOIN categories c ON c.id = p.category_id
                 WHERE p.status = 1 AND p.category_id = ?
                 ORDER BY p.id DESC"
            );
            $stmt->execute([$category['id']]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $products = [];
        }
    }
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

$categoryImage = $category ? categoryImageUrl($category['category_image'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include_once './includes/head_links.php'; ?>
    <style>
        /* ===== BREADCRUMB ===== */
.mm-crumb {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: #948c82;
    margin: 22px 0 10px;
}
.mm-crumb a {
    color: #b51f2c;
    text-decoration: none;
    font-weight: 600;
}
.mm-crumb a:hover { text-decoration: underline; }
.mm-crumb span.sep { color: #c9c2b8; }

/* ===== CATEGORY HEADER ===== */
.mm-cat-head {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 12px 0 2px;
}
.mm-cat-head-img {
    width: 72px;
    height: 72px;
    border-radius: 18px;
    overflow: hidden;
    background: #fff;
    border: 1px solid #fbeaea;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.mm-cat-head-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.mm-cat-head-img svg {
    width: 30px;
    height: 30px;
    color: #b51f2c;
}
.mm-cat-head-info { min-width: 0; }
.mm-cat-head-title {
    font-family: "Playfair Display", serif;
    font-size: 22px;
    font-weight: 700;
    color: #302923;
    margin: 0;
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.mm-cat-head-sub {
    font-size: 13px;
    color: #a19a90;
    margin: 4px 0 0;
}

@media (min-width: 640px) {
    .mm-cat-head-img { width: 84px; height: 84px; border-radius: 20px; }
    .mm-cat-head-title { font-size: 26px; }
}
        </style>
</head>

<body>

    <!-- ================= NAVBAR ================= -->
    <?php include_once './includes/nav-bar.php'; ?>


    <!-- ================= MAIN ================= -->
    <main class="mm-main">
        <div class="mm-container">

            <?php if (!$category): ?>

                <!-- ============= CATEGORY NOT FOUND ============= -->
                <section style="padding-top:40px; padding-bottom:120px;">
                    <div class="mm-empty">
                        <div class="mm-empty-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="9" />
                                <path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3" />
                                <path d="M12 17h.01" />
                            </svg>
                        </div>
                        <h3>Category not found</h3>
                        <p>The category you're looking for doesn't exist or has been removed.</p>
                        <p style="margin-top:14px;">
                            <a href="<?= MAIN_URL ?>" style="color:#b51f2c;font-weight:700;text-decoration:none;">← Back to Home</a>
                        </p>
                    </div>
                </section>

            <?php else: ?>

                <!-- ============= BREADCRUMB ============= -->
                <nav class="mm-crumb" aria-label="Breadcrumb">
                    <a href="<?= MAIN_URL ?>">Home</a>
                    <span class="sep">/</span>
                    <span style="color:#302923;font-weight:600;"><?= htmlspecialchars($category['category_name']) ?></span>
                </nav>

                <!-- ============= CATEGORY HEADER ============= -->
                <section class="mm-cat-head">
                    <div class="mm-cat-head-img">
                        <?php if ($categoryImage): ?>
                            <img src="<?= htmlspecialchars($categoryImage) ?>" alt="">
                        <?php else: ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                                <path d="M3 6h18" />
                                <path d="M16 10a4 4 0 0 1-8 0" />
                            </svg>
                        <?php endif; ?>
                    </div>

                    <div class="mm-cat-head-info">
                        <h1 class="mm-cat-head-title">
                            <?= htmlspecialchars($category['category_name']) ?>
                        </h1>
                        <p class="mm-cat-head-sub">
                            <?= count($products) ?> product<?= count($products) === 1 ? '' : 's' ?>
                            <?php if ($isMenuActive): ?>
                                · in today's menu
                            <?php endif; ?>
                        </p>
                    </div>
                </section>

                <!-- ============= PRODUCTS ============= -->
                <?php if (empty($products)): ?>

                    <section style="padding-top:32px; padding-bottom:120px;">
                        <div class="mm-empty">
                            <div class="mm-empty-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                    <path d="M3.3 7L12 12l8.7-5" />
                                    <path d="M12 22V12" />
                                </svg>
                            </div>
                            <h3>No products here yet</h3>
                            <p>
                                <?php if ($isMenuActive): ?>
                                    This category isn't part of today's menu.<br>
                                    Please check back soon.
                                <?php else: ?>
                                    There are no products in this category right now.
                                <?php endif; ?>
                            </p>
                            <p style="margin-top:14px;">
                                <a href="<?= MAIN_URL ?>" style="color:#b51f2c;font-weight:700;text-decoration:none;">← Back to Home</a>
                            </p>
                        </div>
                    </section>

                <?php else: ?>

                    <section style="margin-top:22px;">
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

                <?php endif; ?>

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