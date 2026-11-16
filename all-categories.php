<?php
require_once './config/config.php';
require_once './config/function.php';

$settings = getSettings($pdo);

$siteName = $settings['username'] ?? 'Mrs Mill@';
$logoUrl  = !empty($settings['logo_image'])    ? ADMIN_URL . $settings['logo_image']    : '';
$favicon  = !empty($settings['favicon_image']) ? ADMIN_URL . $settings['favicon_image'] : '';

/* ---------- CUSTOMER ---------- */
$customerLoggedIn = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0;
$customerRowId    = $customerLoggedIn ? (int)$_SESSION['customer_id'] : 0;
$customerCode     = $_SESSION['customer_code']   ?? '';
$customerName     = $_SESSION['customer_name']   ?? '';
$customerMobile   = $_SESSION['customer_mobile'] ?? '';

/* ---------- CART ---------- */
$cartCount = 0;
$cartTotal = 0.0;

if ($customerRowId > 0) {
    try {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(qty),0) AS c,
                    COALESCE(SUM(price * qty),0) AS t
             FROM customer_cart WHERE customer_id = ?"
        );
        $stmt->execute([$customerRowId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $cartCount = (int)($row['c'] ?? 0);
        $cartTotal = (float)($row['t'] ?? 0);
    } catch (PDOException $e) {
    }
} else {
    if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $c) {
            $q = (int)($c['qty'] ?? 0);
            $cartCount += $q;
            $cartTotal += ((float)($c['price'] ?? 0)) * $q;
        }
    }
}

/* =========================================================
   LOAD ALL ACTIVE CATEGORIES
   ========================================================= */
$categories = [];
try {
    $stmt = $pdo->query("SELECT id, category_name, category_slug, category_image FROM categories WHERE status = 1 ORDER BY category_name ASC");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include_once './includes/head_links.php'; ?>

    <style>
        .page-header { padding-top: 28px; margin-bottom: 24px; }
        .page-header h1 { font-family: "Playfair Display", serif; font-size: 28px; font-weight: 700; color: #302923; margin: 0 0 6px; }
        .page-header p { margin: 0; font-size: 13px; color: #817a71; }

        .cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 20px; padding-bottom: 80px; }
        .cat-box { background: #fff; border: 1.5px solid #ece5da; border-radius: 18px; overflow: hidden; text-decoration: none; display: flex; flex-direction: column; transition: all 0.25s cubic-bezier(0.2, 0.9, 0.3, 1.2); box-shadow: 0 4px 12px rgba(48, 41, 35, 0.03); }
        .cat-box:hover { transform: translateY(-5px); border-color: #d98a91; box-shadow: 0 12px 24px rgba(181, 31, 44, 0.08); }
        
        .cat-img { position: relative; width: 100%; padding-bottom: 80%; background: #fdfaf4; }
        .cat-img img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        .cat-img svg { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 38%; height: 38%; color: #d5cbbd; }
        
        .cat-info { padding: 16px 14px; text-align: center; flex: 1; display: flex; flex-direction: column; justify-content: space-between; gap: 12px; }
        .cat-name { font-size: 13px; font-weight: 800; color: #302923; margin: 0; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .cat-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-size: 11px; font-weight: 800; color: #b51f2c; text-transform: uppercase; letter-spacing: 0.05em; transition: 0.2s; }
        .cat-box:hover .cat-btn { color: #8e1722; }
        .cat-box:hover .cat-btn i { transform: translateX(4px); }
        .cat-btn i { font-size: 13px; transition: transform 0.2s; }

        @media (max-width: 768px) {
            .cat-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .cat-info { padding: 12px 10px; }
            .cat-name { font-size: 12px; }
            .page-header h1 { font-size: 22px; }
        }
        @media (max-width: 380px) {
            .cat-grid { gap: 10px; }
            .cat-name { font-size: 11px; }
        }
    </style>
</head>

<body>

    <?php include_once './includes/nav-bar.php'; ?>

    <main class="mm-main">
        <div class="mm-container">
            <div class="page-header">
                <h1>All Categories</h1>
                <p>Browse all our available product categories</p>
            </div>

            <?php if (!empty($categories)): ?>
                <div class="cat-grid">
                    <?php foreach ($categories as $c):
                        $cimg = categoryImageUrl($c['category_image'] ?? '');
                    ?>
                        <!-- ✅ FIXED: Links directly to category.php with the specific slug -->
                        <a href="category.php?slug=<?= urlencode($c['category_slug']) ?>" class="cat-box">
                            <div class="cat-img">
                                <?php if ($cimg): ?>
                                    <img src="<?= htmlspecialchars($cimg) ?>" alt="<?= htmlspecialchars($c['category_name']) ?>">
                                <?php else: ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                                        <path d="M3 6h18" />
                                        <path d="M16 10a4 4 0 0 1-8 0" />
                                    </svg>
                                <?php endif; ?>
                            </div>
                            <div class="cat-info">
                                <h3 class="cat-name"><?= htmlspecialchars($c['category_name']) ?></h3>
                                <span class="cat-btn">View Products <i class="bi bi-arrow-right"></i></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px;">
                    <div style="width: 80px; height: 80px; border-radius: 50%; background: #fdfaf4; color: #b8893c; display: inline-flex; align-items: center; justify-content: center; font-size: 32px; margin-bottom: 16px;">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <h3 style="font-family: 'Playfair Display', serif; font-size: 20px; color: #302923; margin: 0 0 8px;">No categories yet</h3>
                    <p style="color: #817a71; font-size: 13px;">Please check back later, we are adding new items.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include_once './includes/sticky-cart.php'; ?>
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
        window.CART_TOTAL = <?= (float)$cartTotal ?>;
        window.CUSTOMER = {
            id: <?= (int)$customerRowId ?>,
            code: "<?= htmlspecialchars($customerCode,   ENT_QUOTES) ?>",
            name: "<?= htmlspecialchars($customerName,   ENT_QUOTES) ?>",
            mobile: "<?= htmlspecialchars($customerMobile, ENT_QUOTES) ?>"
        };
    </script>

    <script src="<?= MAIN_URL ?>js/home.js"></script>

</body>

</html>