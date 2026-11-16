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

/* =========================================================
   FIND WHICH ACTIVE MENU CONTAINS THIS PRODUCT
   ========================================================= */
$activeMenus  = getActiveMenus($pdo);
$productMenu  = null;
$menuDiscount = null;
$badgeText    = '';

/* Per-menu discount helper */
function getMenuDiscount(PDO $pdo, array $menu): ?array
{
    if (!isset($menu['start_at']) || !isset($menu['end_at'])) return null;

    try {
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

/* Scan active menus for this product */
if (!empty($activeMenus)) {
    foreach ($activeMenus as $menu) {
        try {
            $mStmt = $pdo->prepare(
                "SELECT id FROM menu_products WHERE menu_code = ? AND product_code = ? LIMIT 1"
            );
            $mStmt->execute([$menu['menu_code'], $product['product_code']]);
            if ($mStmt->fetch()) {
                $productMenu  = $menu;
                $menuDiscount = getMenuDiscount($pdo, $menu);
                if ($menuDiscount && $menuDiscount['amount'] > 0) {
                    $badgeText = $menuDiscount['amount_type'] === 'percent'
                        ? number_format($menuDiscount['amount'], 0) . '% OFF'
                        : '₹' . number_format($menuDiscount['amount'], 0) . ' OFF';
                }
                break;
            }
        } catch (PDOException $e) {
        }
    }
}

$menuCode     = $productMenu ? $productMenu['menu_code'] : '';
$canAddToCart = !empty($productMenu);

/* =========================================================
   FETCH VARIANTS
   ========================================================= */
$variants = [];
try {
    $vStmt = $pdo->prepare(
        "SELECT id, quantity, quantity_unit, quantity_name, price, container_enabled, container_price
         FROM product_variants
         WHERE product_code = ? AND status = 1
         ORDER BY quantity ASC"
    );
    $vStmt->execute([$product['product_code']]);
    $variants = $vStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $variants = [];
}

/* =========================================================
   FETCH STOCK PER VARIANT (from menu_products)
   ========================================================= */
$stockByVariant   = []; // variant_id => ['unlimited' => bool, 'count' => int]
$soldOutByVariant = []; // variant_id => bool

if ($canAddToCart && !empty($variants)) {
    try {
        $sStmt = $pdo->prepare(
            "SELECT variant_id, stock_unlimited, stock_count
             FROM menu_products
             WHERE menu_code = ? AND product_code = ?"
        );
        $sStmt->execute([$menuCode, $product['product_code']]);
        foreach ($sStmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $stockByVariant[(int)$s['variant_id']] = [
                'unlimited' => (int)$s['stock_unlimited'] === 1,
                'count'     => (int)$s['stock_count'],
            ];
        }
    } catch (PDOException $e) {
        $stockByVariant = [];
    }
}

foreach ($variants as $v) {
    $vid = (int)$v['id'];
    $s   = $stockByVariant[$vid] ?? null;
    if (!$s) {
        $soldOutByVariant[$vid] = false; // no stock row → available
    } else {
        $soldOutByVariant[$vid] = (!$s['unlimited'] && $s['count'] <= 0);
    }
}

/* =========================================================
   CART QTY PER VARIANT (only if in menu / can add to cart)
   ========================================================= */
$customerRowId = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0
    ? (int)$_SESSION['customer_id'] : 0;

$cartQtyByVariant = [];

if ($canAddToCart) {
    if ($customerRowId > 0) {
        try {
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
        } catch (PDOException $e) {
        }
    } else {
        if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $g) {
                if ((int)($g['product_id'] ?? 0) !== $productId) continue;
                $vid = (int)($g['variant_id'] ?? 0);
                if ($vid > 0) {
                    $cartQtyByVariant[$vid] = ($cartQtyByVariant[$vid] ?? 0) + (int)($g['qty'] ?? 0);
                }
            }
        }
    }
}

/* Helper: format quantity like "650 gram" */
function fmtQty($qty, $unit): string
{
    $n = rtrim(rtrim(number_format((float)$qty, 2, '.', ''), '0'), '.');
    return trim($n . ' ' . $unit);
}

/* =========================================================
   RELATED PRODUCTS (same category, no menu logic)
   ========================================================= */
$relatedProducts = [];
try {
    $rStmt = $pdo->prepare(
        "SELECT p.id, p.product_name, p.product_image
         FROM products p
         WHERE p.category_id = ? AND p.id != ? AND p.status = 1
         ORDER BY RAND()
         LIMIT 4"
    );
    $rStmt->execute([$product['category_id'], $productId]);
    $relatedProducts = $rStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $relatedProducts = [];
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
} else if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $c) {
        $q = (int)($c['qty'] ?? 0);
        $cartCount += $q;
        $cartTotal += ((float)($c['price'] ?? 0)) * $q;
    }
}
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
            flex-wrap: wrap;
        }

        .mm-crumb a {
            color: #b51f2c;
            text-decoration: none;
            font-weight: 600;
        }

        .mm-crumb a:hover {
            text-decoration: underline;
        }

        .mm-crumb span.sep {
            color: #c9c2b8;
        }

        /* Page Layout */
        .pp-wrapper {
            padding: 10px 0 30px;
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
            object-fit: contain;
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

        /* Discount banner + menu tag */
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
            margin-bottom: 4px;
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
            margin-bottom: 4px;
        }

        /* ===== Variants box ===== */
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
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        /* READ-ONLY variant row */
        .pp-var-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .pp-var-row {
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-radius: 10px;
            background: #fdfaf4;
            border: 1px solid #f3ece0;
            font-size: 13px;
        }

        .pp-var-row:hover {
            background: #fff7ee;
            border-color: #ecdcc4;
        }

        .pp-var-label {
            color: #302923;
            font-weight: 700;
            min-width: 0;
        }

        .pp-var-label .vn {
            text-transform: uppercase;
            letter-spacing: .02em;
            font-size: 12px;
        }

        .pp-var-label .vq {
            font-size: 11.5px;
            color: #817a71;
            margin-left: 8px;
            font-weight: 600;
        }

        .pp-var-price {
            font-size: 14px;
            font-weight: 900;
            color: #b51f2c;
            white-space: nowrap;
        }

        .pp-no-vars {
            font-size: 12.5px;
            color: #948c82;
            font-style: italic;
            padding: 6px 0;
        }

        /* ACTIVE cart variant row */
        .pp-cart-var-row {
            display: grid;
            grid-template-columns: 1fr auto auto;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f0ebe4;
        }

        .pp-cart-var-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .pp-cart-var-label {
            font-size: 13px;
            font-weight: 800;
            color: #302923;
            min-width: 0;
        }

        .pp-cart-var-label .vn {
            text-transform: uppercase;
            font-size: 12px;
        }

        .pp-cart-var-label .vq {
            font-size: 11.5px;
            color: #817a71;
            margin-left: 6px;
            font-weight: 600;
        }

        .pp-cart-var-price {
            font-size: 14px;
            font-weight: 800;
            color: #302923;
            min-width: 60px;
            text-align: right;
        }

        /* ===== Sold-out variant ===== */
        .pp-cart-var-row.is-soldout .pp-cart-var-label {
            color: #a19a90;
        }

        .pp-cart-var-row.is-soldout .pp-cart-var-price {
            color: #a19a90;
        }

        .pp-cart-var-row.is-soldout .mm-qty {
            border-color: #d8d2c9;
        }

        .pp-cart-var-row.is-soldout .mm-qty-val {
            color: #a19a90;
            background: #f5f1ec;
            border-color: #d8d2c9;
        }

        .pp-var-soldout-tag {
            display: inline-block;
            margin-left: 8px;
            padding: 2px 7px;
            font-size: 9.5px;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #fff;
            background: linear-gradient(135deg, #d42a3a, #b51f2c);
            border-radius: 4px;
            vertical-align: middle;
        }

        /* Qty stepper */
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

        /* Add / Update button */
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

        /* ===== Recipe maker / About bio ===== */
        .pp-maker {
            margin-top: 60px;
            padding: 28px;
            background: #fdfaf4;
            border: 1.5px solid #f0e8da;
            border-radius: 20px;
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 22px;
            align-items: flex-start;
        }

        @media (max-width: 640px) {
            .pp-maker {
                grid-template-columns: 1fr;
                gap: 14px;
                padding: 20px;
                text-align: center;
            }
        }

        .pp-maker-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            background: #fff;
            border: 3px solid #b51f2c;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin: 0 auto;
        }

        .pp-maker-avatar img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .pp-maker-avatar svg {
            width: 55%;
            height: 55%;
            color: #b51f2c;
        }

        .pp-maker-name {
            font-family: "Playfair Display", serif;
            font-size: 22px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 4px;
        }

        .pp-maker-role {
            font-size: 11.5px;
            font-weight: 800;
            color: #b51f2c;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: 10px;
        }

        .pp-maker-bio {
            font-size: 13px;
            color: #756d65;
            line-height: 1.65;
            margin: 0 0 10px;
        }

        .pp-maker-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .pp-maker-tag {
            font-size: 10.5px;
            font-weight: 700;
            color: #a35a0e;
            background: #fff;
            border: 1px solid #f0e0c6;
            border-radius: 999px;
            padding: 3px 10px;
        }

        /* ===== Related Products ===== */
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

        .pp-rel-card {
            background: #fff;
            border: 1.5px solid #e8e1d8;
            border-radius: 16px;
            overflow: hidden;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            transition: .2s;
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
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</head>

<body>

    <?php include_once './includes/nav-bar.php'; ?>

    <main class="mm-main">
        <div class="mm-container">

            <!-- Breadcrumb -->
            <div class="mm-crumb">
                <a href="<?= MAIN_URL ?>">Home</a>
                <span class="sep">›</span>
                <?php if (!empty($product['category_slug'])): ?>
                    <a href="category.php?slug=<?= urlencode($product['category_slug']) ?>">
                        <?= htmlspecialchars($product['category_name'] ?? 'Category') ?>
                    </a>
                    <span class="sep">›</span>
                <?php endif; ?>
                <span><?= htmlspecialchars($product['product_name']) ?></span>
            </div>

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

                    <!-- =====================================================
                         VARIANTS
                         → In active menu: qty steppers (sold-out disables +) + Add/Update
                         → Not in menu: read-only price list
                         ===================================================== -->
                    <div class="pp-variants-box">

                        <?php if ($canAddToCart): ?>
                            <!-- ========== CART MODE ========== -->
                            <h3>Select Quantity</h3>

                            <div id="variantList">
                                <?php if (!empty($variants)): ?>
                                    <?php foreach ($variants as $v):
                                        $vid       = (int)$v['id'];
                                        $cartQty   = (int)($cartQtyByVariant[$vid] ?? 0);
                                        $vName     = trim((string)($v['quantity_name'] ?? ''));
                                        $vQty      = fmtQty($v['quantity'] ?? 0, $v['quantity_unit'] ?? '');
                                        $vPrice    = (float)($v['price'] ?? 0);
                                        $soldOut   = !empty($soldOutByVariant[$vid]);
                                    ?>
                                        <div class="pp-cart-var-row <?= $soldOut ? 'is-soldout' : '' ?>"
                                            data-variant-id="<?= $vid ?>"
                                            data-price="<?= $vPrice ?>"
                                            data-original="<?= $cartQty ?>"
                                            data-soldout="<?= $soldOut ? '1' : '0' ?>">

                                            <div class="pp-cart-var-label">
                                                <?php if ($vName !== ''): ?>
                                                    <span class="vn"><?= htmlspecialchars($vName) ?></span>
                                                <?php endif; ?>
                                                <?php if ($vQty !== ''): ?>
                                                    <span class="vq"><?= htmlspecialchars($vQty) ?></span>
                                                <?php endif; ?>
                                                <?php if ($soldOut): ?>
                                                    <span class="pp-var-soldout-tag">Sold Out</span>
                                                <?php endif; ?>
                                            </div>

                                            <div class="mm-qty" data-qty="<?= $cartQty ?>">
                                                <button type="button" class="js-qty-minus" <?= $cartQty === 0 ? 'disabled' : '' ?>>−</button>
                                                <span class="mm-qty-val"><?= $cartQty ?></span>
                                                <button type="button" class="js-qty-plus" <?= $soldOut ? 'disabled' : '' ?>>+</button>
                                            </div>

                                            <div class="pp-cart-var-price">₹<?= number_format($vPrice, 0) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="pp-no-vars">No variants available for this product.</div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($variants)): ?>
                                <button type="button" class="pp-add-btn" id="addToCartBtn" disabled>
                                    <i class="bi bi-cart-plus"></i>
                                    <span>Add to Cart</span>
                                </button>
                            <?php endif; ?>

                        <?php else: ?>
                            <!-- ========== READ-ONLY MODE ========== -->
                            <h3>Available Sizes &amp; Prices</h3>

                            <?php if (!empty($variants)): ?>
                                <div class="pp-var-list">
                                    <?php foreach ($variants as $v):
                                        $vName  = trim((string)($v['quantity_name'] ?? ''));
                                        $vQty   = fmtQty($v['quantity'] ?? 0, $v['quantity_unit'] ?? '');
                                        $vPrice = (float)($v['price'] ?? 0);
                                    ?>
                                        <div class="pp-var-row">
                                            <div class="pp-var-label">
                                                <?php if ($vName !== ''): ?>
                                                    <span class="vn"><?= htmlspecialchars($vName) ?></span>
                                                <?php endif; ?>
                                                <?php if ($vQty !== ''): ?>
                                                    <span class="vq"><?= htmlspecialchars($vQty) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="pp-var-price">₹<?= number_format($vPrice, 0) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="pp-no-vars">No variants available for this product.</div>
                            <?php endif; ?>

                        <?php endif; ?>
                    </div>

                </div>

            </div>

            <!-- ================= RECIPE MAKER / ABOUT ================= -->
            <section class="pp-maker">
                <div class="pp-maker-avatar">
                    <?php if ($logoUrl): ?>
                        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($siteName) ?>">
                    <?php else: ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="8" r="4" />
                            <path d="M4 21c0-4 4-7 8-7s8 3 8 7" />
                        </svg>
                    <?php endif; ?>
                </div>

                <div>
                    <h2 class="pp-maker-name"><?= htmlspecialchars($siteName) ?></h2>
                    <div class="pp-maker-role">Traditional Recipe Maker · Homemade with Love</div>

                    <p class="pp-maker-bio">
                        At <strong><?= htmlspecialchars($siteName) ?></strong>, every product is crafted the way our grandmothers made it -
                        slowly, patiently, and with ingredients you can actually pronounce. From stone-ground millet
                        batters to hand-pounded podis, we keep the old recipes alive so every meal tastes like home.
                    </p>
                    <p class="pp-maker-bio">
                        No preservatives. No shortcuts. Just fresh, traditional Tamil Nadu flavours delivered to your door -
                        the same way they've been made for generations.
                    </p>

                    <div class="pp-maker-tags">
                        <span class="pp-maker-tag">🌾 Stone-Ground</span>
                        <span class="pp-maker-tag">🏡 Homemade</span>
                        <span class="pp-maker-tag">🚫 No Preservatives</span>
                        <span class="pp-maker-tag">🌿 Traditional Recipes</span>
                        <span class="pp-maker-tag">📦 Fresh Daily</span>
                    </div>
                </div>
            </section>

            <!-- ================= RELATED PRODUCTS ================= -->
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
        window.CUSTOMER = {
            id: <?= (int)$customerRowId ?>,
            code: "<?= htmlspecialchars($customerCode,   ENT_QUOTES) ?>",
            name: "<?= htmlspecialchars($customerName,   ENT_QUOTES) ?>",
            mobile: "<?= htmlspecialchars($customerMobile, ENT_QUOTES) ?>"
        };
        window.CAN_ADD_TO_CART = <?= $canAddToCart ? 'true' : 'false' ?>;
        window.PP_PRODUCT_ID = <?= (int)$productId ?>;
        window.PP_MENU_CODE = "<?= htmlspecialchars($menuCode) ?>";
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

    <?php if ($canAddToCart && !empty($variants)): ?>
        <!-- Product-page inline cart — only runs when the product is in an active menu -->
        <script>
            (function() {
                const btn = document.getElementById("addToCartBtn");
                const rows = document.querySelectorAll(".pp-cart-var-row");
                if (!btn || rows.length === 0) return;

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

                function updateBtnState() {
                    let anyChanged = false;
                    let anyQty = false;
                    let anyBuyable = false;

                    rows.forEach(r => {
                        const q = Number(r.querySelector(".mm-qty").dataset.qty || 0);
                        const orig = Number(r.dataset.original || 0);
                        const soldOut = r.dataset.soldout === "1";

                        if (q > 0) anyQty = true;
                        if (q !== orig) anyChanged = true;
                        if (!soldOut && q > 0) anyBuyable = true;
                        if (!soldOut && q > orig) anyBuyable = true;
                    });

                    if (anyChanged && anyBuyable) {
                        btn.disabled = false;
                        btn.querySelector("span").textContent = "Update Cart";
                    } else if (anyQty && anyBuyable) {
                        btn.disabled = false;
                        btn.querySelector("span").textContent = "Add to Cart";
                    } else if (!anyBuyable && anyQty) {
                        /* Only sold-out variants still in cart → allow removing them via Update */
                        btn.disabled = false;
                        btn.querySelector("span").textContent = "Update Cart";
                    } else if (!anyBuyable) {
                        btn.disabled = true;
                        btn.querySelector("span").textContent = "Sold Out";
                    } else {
                        btn.disabled = true;
                        btn.querySelector("span").textContent = "Add to Cart";
                    }
                }

                rows.forEach(row => {
                    const minus = row.querySelector(".js-qty-minus");
                    const plus = row.querySelector(".js-qty-plus");
                    const val = row.querySelector(".mm-qty-val");
                    const box = row.querySelector(".mm-qty");
                    const isSoldOut = row.dataset.soldout === "1";

                    if (plus && !isSoldOut) plus.addEventListener("click", () => {
                        let q = Number(box.dataset.qty || 0);
                        q++;
                        box.dataset.qty = q;
                        val.textContent = q;
                        minus.disabled = false;
                        updateBtnState();
                    });

                    if (minus) minus.addEventListener("click", () => {
                        let q = Number(box.dataset.qty || 0);
                        if (q > 0) q--;
                        box.dataset.qty = q;
                        val.textContent = q;
                        minus.disabled = q === 0;
                        updateBtnState();
                    });
                });

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

                    // Filter out sold-out items (qty > 0 but sold out should never be posted)
                    const sentItems = items.filter(it => {
                        const row = document.querySelector(`.pp-cart-var-row[data-variant-id="${it.variant_id}"]`);
                        if (!row) return false;
                        if (row.dataset.soldout === "1" && it.qty > 0) return false;
                        return true;
                    });

                    if (sentItems.length === 0) {
                        localToast("All selected sizes are sold out.", "error");
                        btn.disabled = false;
                        updateBtnState();
                        return;
                    }

                    btn.disabled = true;
                    const oldText = btn.querySelector("span").textContent;
                    btn.querySelector("span").textContent = "Saving...";

                    const fd = new FormData();
                    fd.append("product_id", window.PP_PRODUCT_ID);
                    fd.append("menu_code", window.PP_MENU_CODE);
                    fd.append("items", JSON.stringify(sentItems));

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
    <?php endif; ?>

</body>

</html>