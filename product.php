<?php
require_once './config/config.php';
require_once './config/function.php';

$settings = getSettings($pdo);

$siteName = $settings['username'] ?? 'Mrs Mill@';
$logoUrl  = !empty($settings['logo_image'])    ? ADMIN_URL . $settings['logo_image']    : '';
$favicon  = !empty($settings['favicon_image']) ? ADMIN_URL . $settings['favicon_image'] : '';

/* ---------- GET PRODUCT ID ---------- */
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($productId <= 0) {
    header('Location: index.php');
    exit;
}

/* ---------- FETCH PRODUCT ---------- */
try {
    $stmt = $pdo->prepare(
        "SELECT p.*, c.category_name, c.category_slug, c.category_image
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.id = ? AND p.status = 1
         LIMIT 1"
    );
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    header('Location: index.php');
    exit;
}

/* ---------- FIND WHICH MENU IT BELONGS TO ---------- */
$activeMenus = getActiveMenus($pdo);
$productMenu = null;
$menuDiscount = null;
$badgeText = '';

// Function to get discount for a menu (Requires start_at and end_at)
function getMenuDiscount(PDO $pdo, array $menu): ?array
{
    try {
        // Prevent error if start_at/end_at are missing
        if (!isset($menu['start_at']) || !isset($menu['end_at'])) {
            return null;
        }

        $menuStart = strtotime($menu['start_at']);
        $menuEnd   = strtotime($menu['end_at']);
        $now       = time();
        if ($now < $menuStart || $now > $menuEnd) return null;

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

        foreach ($slots as $s) {
            $slotEndTs = strtotime($baseDay . ' ' . $s['end_time']);
            if ($slotEndTs < $prevEndEpoch) $slotEndTs += 86400;
            if ($now >= $prevEndEpoch && $now <= $slotEndTs) {
                return [
                    'name'        => $s['discount_name'],
                    'slot_name'   => $s['slot_name'],
                    'amount_type' => $s['amount_type'],
                    'amount'      => (float)$s['discount_amount'],
                    'delivery'    => (int)$s['delivery_enabled'],
                ];
            }
            $prevEndEpoch = $slotEndTs;
        }
    } catch (PDOException $e) {
    }
    return null;
}

// Check which active menu contains this product
foreach ($activeMenus as $menu) {
    $mStmt = $pdo->prepare("SELECT id FROM menu_products WHERE menu_code = ? AND product_code = ? LIMIT 1");
    $mStmt->execute([$menu['menu_code'], $product['product_code']]);
    if ($mStmt->fetch()) {
        $productMenu = $menu;
        $menuDiscount = getMenuDiscount($pdo, $menu);
        if ($menuDiscount && $menuDiscount['amount'] > 0) {
            $badgeText = $menuDiscount['amount_type'] === 'percent'
                ? number_format($menuDiscount['amount'], 0) . '% OFF'
                : '₹' . number_format($menuDiscount['amount'], 0) . ' OFF';
        }
        break; // Found the menu
    }
}

/* ---------- FETCH ALL VARIANTS ---------- */
$vStmt = $pdo->prepare(
    "SELECT id, quantity, quantity_unit, quantity_name, price, container_enabled, container_price
     FROM product_variants
     WHERE product_code = ? AND status = 1
     ORDER BY quantity ASC"
);
$vStmt->execute([$product['product_code']]);
$variants = $vStmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------- FETCH CART QTY FOR VARIANTS ---------- */
$customerRowId = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0
    ? (int)$_SESSION['customer_id'] : 0;
$menuCode = $productMenu ? $productMenu['menu_code'] : '';
$cartQtyByVariant = [];

if ($customerRowId > 0) {
    // Ignore menu_code here so it ALWAYS pre-fills the existing cart qty
    $cq = $pdo->prepare(
        "SELECT variant_id, SUM(qty) AS q
         FROM customer_cart
         WHERE customer_id = ? AND product_id = ?
         GROUP BY variant_id"
    );
    $cq->execute([$customerRowId, $productId]);
    while ($row = $cq->fetch(PDO::FETCH_ASSOC)) {
        $cartQtyByVariant[(int)$row['variant_id']] = (int)$row['q'];
    }
} else {
    if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $g) {
            if ((int)($g['product_id'] ?? 0) !== $productId) continue;

            // Ignore menu check for guest session so it pre-fills
            $vid = (int)($g['variant_id'] ?? 0);
            if ($vid > 0) {
                $cartQtyByVariant[$vid] = ($cartQtyByVariant[$vid] ?? 0) + (int)($g['qty'] ?? 0);
            }
        }
    }
}

/* ---------- FETCH RANDOM RELATED PRODUCTS (WITHOUT DUPLICATES) ---------- */
$relatedProducts = [];
if ($productMenu) {
    // Use GROUP BY to prevent duplicate products showing up twice
    // We fetch the full menu info so we can pass it to getMenuDiscount()
    $rStmt = $pdo->prepare(
        "SELECT p.id, p.product_name, p.product_image, c.category_name,
                MIN(mp.menu_code) as menu_code, MIN(m.menu_name) as menu_name,
                MIN(m.start_at) as start_at, MIN(m.end_at) as end_at
         FROM menu_products mp
         INNER JOIN products p ON p.product_code = mp.product_code
         INNER JOIN menus m ON m.menu_code = mp.menu_code
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE mp.menu_code = ? AND p.id != ? AND p.status = 1
         GROUP BY p.id
         ORDER BY RAND()
         LIMIT 4"
    );
    $rStmt->execute([$productMenu['menu_code'], $productId]);
    $rawRelated = $rStmt->fetchAll(PDO::FETCH_ASSOC);

    // Now attach the discount badge for each related product's menu
    foreach ($rawRelated as $rp) {
        // Find the discount for THIS specific menu by passing the full row (which has start_at & end_at)
        $relDiscount = getMenuDiscount($pdo, $rp);
        $relBadge = '';
        if ($relDiscount && $relDiscount['amount'] > 0) {
            $relBadge = $relDiscount['amount_type'] === 'percent'
                ? number_format($relDiscount['amount'], 0) . '% OFF'
                : '₹' . number_format($relDiscount['amount'], 0) . ' OFF';
        }

        $rp['rel_discount_badge'] = $relBadge;
        $rp['rel_label_class']    = ($rp['menu_code'] === $productMenu['menu_code']) ? 'today' : 'tomorrow';
        $rp['rel_label_text']     = ($rp['menu_code'] === $productMenu['menu_code']) ? 'Today' : 'Tomorrow';
        $rp['rel_icon']           = ($rp['menu_code'] === $productMenu['menu_code']) ? 'bi bi-fire' : 'bi bi-sunrise';

        $relatedProducts[] = $rp;
    }
}

/* ---------- CUSTOMER ---------- */
$customerLoggedIn = $customerRowId > 0;
$customerCode     = $_SESSION['customer_code']   ?? '';
$customerName     = $_SESSION['customer_name']   ?? '';
$customerMobile   = $_SESSION['customer_mobile'] ?? '';

/* ---------- CART TOTALS ---------- */
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include_once './includes/head_links.php'; ?>
    <style>
        /* Page Layout */
        .pp-wrapper {
            padding: 30px 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }

        @media (max-width: 768px) {
            .pp-wrapper {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }

        /* Image Column */
        .pp-image-col {
            position: relative;
            border-radius: 20px;
            overflow: hidden;
            background: #f7f2ec;
            aspect-ratio: 1/1;
        }

        .pp-image-col img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .pp-image-col>svg {
            width: 40%;
            height: 40%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #d5cbbd;
        }

        /* Discount Banner Top */
        .pp-discount-banner {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #d42a3a, #b51f2c);
            color: #fff;
            padding: 10px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 800;
            margin-bottom: 16px;
            box-shadow: 0 4px 12px rgba(181, 31, 44, .25);
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .pp-menu-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fdf1e2;
            color: #a35a0e;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 800;
            margin-bottom: 16px;
        }

        /* Details Column */
        .pp-details-col {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .pp-category {
            font-size: 12px;
            font-weight: 800;
            color: #b51f2c;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .pp-title {
            font-size: 28px;
            font-weight: 800;
            color: #302923;
            margin: 0;
            line-height: 1.2;
        }

        .pp-desc {
            font-size: 13px;
            color: #756d65;
            line-height: 1.6;
        }

        .pp-desc img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
        }

        .pp-desc ul,
        .pp-desc ol {
            padding-left: 20px;
            margin-bottom: 10px;
        }

        /* Variants Box */
        .pp-variants-box {
            border: 1.5px solid #e8e1d8;
            border-radius: 14px;
            padding: 16px;
            background: #fff;
            margin-top: 10px;
        }

        .pp-variants-box h3 {
            margin: 0 0 12px;
            font-size: 14px;
            font-weight: 800;
            color: #302923;
        }

        .pp-variant-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f0ebe4;
        }

        .pp-variant-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .pp-variant-label {
            font-size: 13px;
            font-weight: 800;
            color: #302923;
            flex: 1;
        }

        .pp-variant-price {
            font-size: 14px;
            font-weight: 800;
            color: #302923;
            min-width: 60px;
            text-align: right;
        }

        /* Qty Stepper */
        .mm-qty {
            display: inline-flex;
            align-items: center;
            border: 1.5px solid #b51f2c;
            border-radius: 8px;
            overflow: hidden;
            height: 32px;
            background: #fff;
            flex-shrink: 0;
        }

        .mm-qty button {
            width: 28px;
            height: 100%;
            border: 0;
            background: transparent;
            color: #b51f2c;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            transition: background .15s ease;
        }

        .mm-qty button:hover:not(:disabled) {
            background: #fdecec;
        }

        .mm-qty button:disabled {
            opacity: .35;
            cursor: not-allowed;
        }

        .mm-qty-val {
            min-width: 28px;
            text-align: center;
            font-size: 13px;
            font-weight: 800;
            color: #b51f2c;
            background: #fff5f5;
            height: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-left: 1.5px solid #b51f2c;
            border-right: 1.5px solid #b51f2c;
        }

        /* Add Button */
        .pp-add-btn {
            width: 100%;
            padding: 14px;
            background: #b51f2c;
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: .2s ease;
            margin-top: 10px;
        }

        .pp-add-btn:hover:not(:disabled) {
            background: #8e1722;
        }

        .pp-add-btn:disabled {
            opacity: .5;
            cursor: not-allowed;
            background: #d8d2c9;
        }

        /* Related Products */
        .pp-related {
            margin-top: 60px;
            padding-top: 40px;
            border-top: 1.5px solid #eee7dc;
        }

        .pp-related h2 {
            font-size: 22px;
            font-weight: 800;
            color: #302923;
            margin-bottom: 20px;
            font-family: "Playfair Display", serif;
        }

        .pp-related-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        @media (max-width: 768px) {
            .pp-related-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Related Card Styles */
        .pp-rel-card {
            background: #fff;
            border: 1.5px solid #e8e1d8;
            border-radius: 16px;
            overflow: hidden;
            text-decoration: none;
            display: block;
            transition: .2s;
            display: flex;
            flex-direction: column;
        }

        .pp-rel-card:hover {
            border-color: #d98a91;
        }

        .pp-rel-img-wrap {
            position: relative;
            aspect-ratio: 1/1;
            background: #f7f2ec;
            width: 100%;
        }

        .pp-rel-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .pp-rel-body {
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .pp-rel-name {
            font-size: 13.5px;
            font-weight: 800;
            color: #302923;
            margin: 0;
            line-height: 1.3;
            text-transform: uppercase;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Menu label & Badge for related cards */
        .mm-menu-label {
            position: absolute;
            top: 8px;
            left: 8px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #fff;
            z-index: 4;
            backdrop-filter: blur(4px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, .15);
        }

        .mm-menu-label.today {
            background: linear-gradient(135deg, #d42a3a, #b51f2c);
        }

        .mm-menu-label.tomorrow {
            background: linear-gradient(135deg, #3b82f6, #1e40af);
        }

        .mm-menu-label.default {
            background: #302923;
        }

        .mm-menu-label i {
            font-size: 10px;
        }

        .mm-discount-badge {
            position: absolute;
            bottom: 10px;
            left: 10px;
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
            box-shadow: 0 3px 0 #5c0d18, 0 6px 12px rgba(181, 31, 44, .45);
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

    <?php include_once './includes/nav-bar.php'; ?>

    <main class="mm-main">
        <div class="mm-container">
            <div class="pp-wrapper">

                <!-- IMAGE COL -->
                <div class="pp-image-col">
                    <?php $img = productImageUrl($product['product_image'] ?? ''); ?>
                    <?php if ($img): ?>
                        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($product['product_name']) ?>">
                    <?php else: ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                            <path d="M3.3 7L12 12l8.7-5" />
                            <path d="M12 22V12" />
                        </svg>
                    <?php endif; ?>
                </div>

                <!-- DETAILS COL -->
                <div class="pp-details-col">
                    <?php if ($badgeText): ?>
                        <div>
                            <span class="pp-discount-banner">
                                <i class="bi bi-tag-fill"></i>
                                Special Offer: <?= htmlspecialchars($badgeText) ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if ($productMenu): ?>
                        <div>
                            <span class="pp-menu-tag">
                                <i class="bi bi-clock-history"></i>
                                Available in: <?= htmlspecialchars($productMenu['menu_name']) ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <div>
                        <div class="pp-category"><?= htmlspecialchars($product['category_name'] ?? 'Category') ?></div>
                        <h1 class="pp-title"><?= htmlspecialchars($product['product_name']) ?></h1>
                    </div>

                    <div class="pp-desc">
                        <?= $product['description'] ? $product['description'] : '<p>No description available for this product.</p>' ?>
                    </div>

                    <!-- VARIANTS -->
                    <div class="pp-variants-box">
                        <h3>Select Quantity</h3>
                        <div id="variantList">
                            <?php foreach ($variants as $v):
                                $vid = (int)$v['id'];
                                $cartQty = $cartQtyByVariant[$vid] ?? 0;
                                $qtyLabel = $v['quantity_name'] ?: ($v['quantity'] . ' ' . $v['quantity_unit']);
                            ?>
                                <div class="pp-variant-row" data-variant-id="<?= $vid ?>" data-price="<?= $v['price'] ?>" data-original="<?= $cartQty ?>">
                                    <span class="pp-variant-label"><?= htmlspecialchars($qtyLabel) ?></span>
                                    <div class="mm-qty" data-qty="<?= $cartQty ?>">
                                        <button type="button" class="js-qty-minus" <?= $cartQty === 0 ? 'disabled' : '' ?>>−</button>
                                        <span class="mm-qty-val"><?= $cartQty ?></span>
                                        <button type="button" class="js-qty-plus">+</button>
                                    </div>
                                    <span class="pp-variant-price">Rs. <?= number_format((float)$v['price'], 0) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="button" class="pp-add-btn" id="addToCartBtn" disabled>
                            <i class="bi bi-cart-plus"></i>
                            <span>Add to Cart</span>
                        </button>
                    </div>
                </div>

            </div>

            <!-- RELATED PRODUCTS -->
            <?php if (!empty($relatedProducts)): ?>
                <div class="pp-related">
                    <h2>You may also like</h2>
                    <div class="pp-related-grid">
                        <?php foreach ($relatedProducts as $rp):
                            $rimg = productImageUrl($rp['product_image'] ?? '');
                        ?>
                            <a href="product.php?id=<?= (int)$rp['id'] ?>" class="pp-rel-card">
                                <div class="pp-rel-img-wrap">
                                    <?php if ($rimg): ?>
                                        <img src="<?= htmlspecialchars($rimg) ?>" class="pp-rel-img" alt="">
                                    <?php else: ?>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width: 100%; height: 100%; padding: 30%; color: #d5cbbd;">
                                            <path d="M21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                            <path d="M3.3 7L12 12l8.7-5" />
                                            <path d="M12 22V12" />
                                        </svg>
                                    <?php endif; ?>

                                    <span class="mm-menu-label <?= $rp['rel_label_class'] ?>">
                                        <i class="<?= $rp['rel_icon'] ?>"></i>
                                        <?= htmlspecialchars($rp['rel_label_text']) ?>
                                    </span>

                                    <?php if (!empty($rp['rel_discount_badge'])): ?>
                                        <span class="mm-discount-badge"><?= htmlspecialchars($rp['rel_discount_badge']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="pp-rel-body">
                                    <h3 class="pp-rel-name"><?= htmlspecialchars($rp['product_name']) ?></h3>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
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

        // Specific data for this page
        window.PP_PRODUCT_ID = <?= (int)$productId ?>;
        window.PP_MENU_CODE = "<?= htmlspecialchars($menuCode) ?>";
    </script>

    <script src="<?= MAIN_URL ?>js/home.js"></script>

    <!-- Specific Script for Product Page Inline Cart -->
    <script>
        (function() {
            const btn = document.getElementById("addToCartBtn");
            const rows = document.querySelectorAll(".pp-variant-row");
            if (!btn) return;

            // Helper to show toast safely
            function localToast(msg, type) {
                let t = document.getElementById("mmToast");
                if (!t) {
                    t = document.createElement("div");
                    t.id = "mmToast";
                    document.body.appendChild(t);
                }
                t.textContent = msg;
                t.className = "show" + (type === "error" ? " error" : "");
                clearTimeout(t._t);
                t._t = setTimeout(() => (t.className = ""), 2600);
            }

            // Update the button text and state (Add vs Update)
            function updateBtnState() {
                let anyQty = false;
                let anyChanged = false;
                rows.forEach(r => {
                    const q = Number(r.querySelector(".mm-qty").dataset.qty || 0);
                    const orig = Number(r.dataset.original || 0);
                    if (q > 0) anyQty = true;
                    if (q !== orig) anyChanged = true;
                });

                if (!anyQty && !anyChanged) {
                    btn.disabled = true;
                    btn.querySelector("span").textContent = "Add to Cart";
                } else if (anyChanged) {
                    btn.disabled = false;
                    btn.querySelector("span").textContent = "Update Cart";
                } else {
                    btn.disabled = false;
                    btn.querySelector("span").textContent = "Add to Cart";
                }
            }

            // Bind Plus/Minus buttons
            rows.forEach(row => {
                const minus = row.querySelector(".js-qty-minus");
                const plus = row.querySelector(".js-qty-plus");
                const val = row.querySelector(".mm-qty-val");
                const box = row.querySelector(".mm-qty");

                if (plus) {
                    plus.addEventListener("click", () => {
                        let q = Number(box.dataset.qty || 0);
                        q++;
                        box.dataset.qty = q;
                        val.textContent = q;
                        minus.disabled = false;
                        updateBtnState();
                    });
                }

                if (minus) {
                    minus.addEventListener("click", () => {
                        let q = Number(box.dataset.qty || 0);
                        if (q > 0) q--;
                        box.dataset.qty = q;
                        val.textContent = q;
                        minus.disabled = q === 0;
                        updateBtnState();
                    });
                }
            });

            // Sync to Cart
            btn.addEventListener("click", function() {
                if (!window.IS_LOGGED_IN) {
                    localToast("Please log in to add items.", "error");
                    const navBtn = document.getElementById("navLoginBtn");
                    if (navBtn) navBtn.click();
                    return;
                }

                const items = [];
                rows.forEach(r => {
                    const q = Number(r.querySelector(".mm-qty").dataset.qty || 0);
                    items.push({
                        variant_id: Number(r.dataset.variantId),
                        qty: q
                    });
                });

                btn.disabled = true;
                const oldText = btn.querySelector("span").textContent;
                btn.querySelector("span").textContent = "Saving...";

                const fd = new FormData();
                fd.append("product_id", window.PP_PRODUCT_ID);
                fd.append("menu_code", window.PP_MENU_CODE);
                fd.append("items", JSON.stringify(items));

                fetch(window.MAIN_URL + "ajax/cart-sync.php", {
                        method: "POST",
                        body: fd,
                        credentials: "same-origin"
                    })
                    .then(r => r.json().catch(() => null))
                    .then(res => {
                        if (!res || !res.success) {
                            localToast((res && res.message) || "Failed.", "error");
                            btn.disabled = false;
                            btn.querySelector("span").textContent = oldText;
                            return;
                        }

                        localToast("Cart updated.");

                        // Update the cart badge & sticky cart
                        if (res.data) {
                            if (typeof window.paintCartUI === "function") {
                                window.paintCartUI(res.data.cart_count, res.data.cart_total);
                            } else {
                                document.querySelectorAll(".js-cart-count, [data-cart-count]").forEach(el => {
                                    el.textContent = res.data.cart_count;
                                });
                                const stickyC = document.getElementById("stickyCartCount");
                                const stickyT = document.getElementById("stickyCartTotal");
                                if (stickyC) stickyC.textContent = res.data.cart_count;
                                if (stickyT) stickyT.textContent = "₹" + Math.round(res.data.cart_total);
                                if (res.data.cart_count > 0) {
                                    const bar = document.getElementById("stickyCartBar");
                                    if (bar) bar.style.display = "flex";
                                }
                            }
                        }

                        // CRITICAL: Update the "original" attribute so the button resets to "Update Cart" or "Add to Cart" properly
                        rows.forEach(r => {
                            const box = r.querySelector(".mm-qty");
                            r.dataset.original = box.dataset.qty;
                        });

                        updateBtnState();
                    })
                    .catch((err) => {
                        console.error(err);
                        localToast("Network error.", "error");
                        btn.disabled = false;
                        btn.querySelector("span").textContent = oldText;
                    });
            });

            updateBtnState();
        })();
    </script>
</body>

</html>