<?php
require_once './config/config.php';
require_once './config/function.php';

$settings = getSettings($pdo);
$siteName = $settings['username'] ?? 'Mrs Mill@';
$logoUrl  = !empty($settings['logo_image'])    ? ADMIN_URL . $settings['logo_image']    : '';
$favicon  = !empty($settings['favicon_image']) ? ADMIN_URL . $settings['favicon_image'] : '';

$slug     = trim($_GET['slug'] ?? '');
$menuCode = trim($_GET['menu'] ?? '');

$category = null;
$products = [];
$menuRow  = null;

/* ---------- Load category ---------- */
if ($slug !== '') {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, category_name, category_slug, category_image
             FROM categories WHERE category_slug = ? AND status = 1 LIMIT 1"
        );
        $stmt->execute([$slug]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
    }
}

/* ---------- Load menu ---------- */
if ($menuCode !== '') {
    try {
        $stmt = $pdo->prepare(
            "SELECT * FROM menus WHERE menu_code = ? AND status = 1 LIMIT 1"
        );
        $stmt->execute([$menuCode]);
        $menuRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
    }
}

/* ---------- Products in this category ∩ menu ---------- */
if ($category && $menuRow) {
    try {
        $stmt = $pdo->prepare(
            "SELECT DISTINCT p.id, p.product_code, p.product_name, p.product_image, p.description
             FROM menu_products mp
             INNER JOIN products p ON p.product_code = mp.product_code
             WHERE mp.menu_code = ? AND p.category_id = ? AND p.status = 1
             ORDER BY p.id ASC"
        );
        $stmt->execute([$menuCode, $category['id']]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
    }
}

/* ---------- Menu discount (same as today-menu.php) ---------- */
function getMenuDiscount(PDO $pdo, array $menu): ?array
{
    try {
        $tz        = new DateTimeZone('Asia/Kolkata');
        $menuStart = new DateTimeImmutable($menu['start_at'], $tz);
        $menuEnd   = new DateTimeImmutable($menu['end_at'],   $tz);
        $now       = new DateTimeImmutable('now', $tz);
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

        $prevEndEpoch = $menuStart->getTimestamp();
        $baseDay      = $menuStart->format('Y-m-d');
        $nowEpoch     = $now->getTimestamp();

        foreach ($slots as $s) {
            $slotEnd = new DateTimeImmutable($baseDay . ' ' . $s['end_time'], $tz);
            $slotEndTs = $slotEnd->getTimestamp();
            if ($slotEndTs < $prevEndEpoch) $slotEndTs += 86400;

            if ($nowEpoch >= $prevEndEpoch && $nowEpoch <= $slotEndTs && $nowEpoch <= $menuEnd->getTimestamp()) {
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
    } catch (Throwable $e) {
    }
    return null;
}

$menuDiscount = ($menuRow) ? getMenuDiscount($pdo, $menuRow) : null;
$badgeText = '';
if ($menuDiscount && $menuDiscount['amount'] > 0) {
    $badgeText = $menuDiscount['amount_type'] === 'percent'
        ? number_format($menuDiscount['amount'], 0) . '% OFF'
        : '₹' . number_format($menuDiscount['amount'], 0) . ' OFF';
}

/* ---------- Customer & cart ---------- */
$customerLoggedIn = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0;
$customerRowId    = $customerLoggedIn ? (int)$_SESSION['customer_id'] : 0;
$customerCode     = $_SESSION['customer_code']   ?? '';
$customerName     = $_SESSION['customer_name']   ?? '';
$customerMobile   = $_SESSION['customer_mobile'] ?? '';

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

        /* ===== CATEGORY HEADER ===== */
        .mm-cat-head {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 12px 0 18px;
            border-bottom: 1.5px solid #eee7dc;
            margin-bottom: 22px;
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

        .mm-cat-head-info {
            min-width: 0;
        }

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
            .mm-cat-head-img {
                width: 84px;
                height: 84px;
                border-radius: 20px;
            }

            .mm-cat-head-title {
                font-size: 26px;
            }
        }

        /* ===== Product grid ===== */
        .mm-products {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 14px;
            align-items: stretch;
            margin-bottom: 80px;
        }

        /* ===== Product card ===== */
        .mm-product {
            background: #fff;
            border: 1.5px solid #e8e1d8;
            border-radius: 16px;
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            transition: border-color .2s ease, box-shadow .2s ease;
            height: 100%;
        }

        .mm-product:hover {
            border-color: #d98a91;
            box-shadow: 0 6px 18px rgba(181, 31, 44, .08);
        }

        .mm-product-img-link {
            display: block;
            text-decoration: none;
            flex-shrink: 0;
        }

        .mm-product-img {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            aspect-ratio: 1/1;
            background: #f7f2ec;
            display: block;
        }

        .mm-product-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .mm-product-img>svg {
            width: 50%;
            height: 50%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #d5cbbd;
        }

        .mm-product-name {
            font-size: 13px;
            font-weight: 800;
            color: #302923;
            margin: 2px 0 4px;
            line-height: 1.3;
            text-transform: uppercase;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* ===== Menu label ===== */
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
            background: linear-gradient(135deg, #d42a3a, #b51f2c);
        }

        /* ===== Discount badge ===== */
        .mm-discount-badge {
            position: absolute;
            bottom: 10px;
            left: 10px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #fff;
            line-height: 1;
            border-radius: 8px;
            background: linear-gradient(145deg, #d42a3a 0%, #b51f2c 45%, #7a1220 100%);
            box-shadow:
                0 1px 0 rgba(255, 255, 255, .25) inset,
                0 -1px 0 rgba(0, 0, 0, .25) inset,
                0 2px 0 #5c0d18,
                0 4px 8px rgba(181, 31, 44, .4);
            transform: rotate(-3deg);
            z-index: 3;
            text-shadow: 0 1px 0 rgba(0, 0, 0, .35);
        }

        .mm-discount-badge::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, #fff5c0, #ffd966 60%, #b8893c);
            box-shadow: 0 0 5px rgba(255, 217, 102, .9);
            display: inline-block;
            flex-shrink: 0;
        }

        /* ===== Variants container (populated by home.js) ===== */
        .mm-variants {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 4px;
            min-height: 30px;
        }

        .mm-variants-loading {
            font-size: 11px;
            color: #948c82;
            padding: 6px 0;
            text-align: center;
        }

        .mm-variant-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
        }

        .mm-variant-label {
            font-size: 10px;
            font-weight: 800;
            color: #302923;
            text-transform: uppercase;
            letter-spacing: .04em;
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .mm-variant-price {
            font-size: 11.5px;
            font-weight: 800;
            color: #302923;
            flex-shrink: 0;
            min-width: 48px;
            text-align: right;
        }

        /* ===== Qty stepper (same as index) ===== */
        .mm-qty {
            display: inline-flex;
            align-items: center;
            border: 1.5px solid #b51f2c;
            border-radius: 6px;
            overflow: hidden;
            height: 24px;
            background: #fff;
            flex-shrink: 0;
        }

        .mm-qty button {
            width: 20px;
            height: 100%;
            border: 0;
            background: transparent;
            color: #b51f2c;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            line-height: 1;
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
            min-width: 20px;
            text-align: center;
            font-size: 11px;
            font-weight: 800;
            color: #b51f2c;
            padding: 0 2px;
            border-left: 1.5px solid #b51f2c;
            border-right: 1.5px solid #b51f2c;
            height: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff5f5;
        }

        /* ===== Add button ===== */
        .mm-add-btn {
            margin-top: auto;
            width: 100%;
            max-width: 160px;
            align-self: center;
            padding: 7px 12px;
            border: 1.5px solid #b51f2c;
            background: #b51f2c;
            color: #fff;
            border-radius: 8px;
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .05em;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: .18s ease;
            line-height: 1;
        }

        .mm-add-btn:hover:not(:disabled) {
            background: #8e1722;
            border-color: #8e1722;
        }

        .mm-add-btn:disabled {
            opacity: .45;
            cursor: not-allowed;
            background: #d8d2c9;
            border-color: #d8d2c9;
        }

        .mm-add-btn.adding {
            pointer-events: none;
            opacity: .7;
        }

        .mm-add-btn svg {
            stroke: currentColor;
            width: 13px;
            height: 13px;
            flex-shrink: 0;
        }

        /* ===== Empty ===== */
        .mm-empty {
            text-align: center;
            padding: 60px 20px;
            color: #948c82;
            font-size: 13px;
        }

        /* ===== Responsive ===== */
        @media (max-width: 768px) {
            .mm-products {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .mm-product {
                padding: 9px;
                gap: 6px;
            }

            .mm-product-name {
                font-size: 11.5px;
            }

            .mm-variant-label {
                font-size: 9.5px;
            }

            .mm-variant-price {
                font-size: 11px;
                min-width: 42px;
            }

            .mm-add-btn {
                max-width: 100%;
                padding: 6px 10px;
                font-size: 9.5px;
                gap: 4px;
            }

            .mm-add-btn svg {
                width: 11px;
                height: 11px;
            }

            .mm-discount-badge {
                font-size: 10px;
                padding: 5px 8px;
            }

            .mm-menu-label {
                font-size: 9px;
                padding: 4px 8px;
            }
        }

        @media (max-width: 380px) {
            .mm-products {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }

            .mm-product {
                padding: 8px;
            }

            .mm-add-btn {
                font-size: 9px;
                padding: 6px 8px;
            }
        }
    </style>
</head>

<body>

    <?php include_once './includes/nav-bar.php'; ?>

    <main class="mm-main">
        <div class="mm-container">

            <?php if (!$category || !$menuRow): ?>

                <div class="mm-empty">
                    <h3>Category or menu not found</h3>
                    <p><a href="<?= MAIN_URL ?>" style="color:#b51f2c;font-weight:700;">← Back to Home</a></p>
                </div>

            <?php else: ?>

                <!-- Breadcrumb -->
                <div class="mm-crumb">
                    <a href="<?= MAIN_URL ?>">Home</a>
                    <span class="sep">›</span>
                    <a href="today-menu.php">Today's Menu</a>
                    <span class="sep">›</span>
                    <span><?= htmlspecialchars($category['category_name']) ?></span>
                </div>

                <!-- Category header -->
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
                        <h1 class="mm-cat-head-title"><?= htmlspecialchars($category['category_name']) ?></h1>
                        <p class="mm-cat-head-sub">
                            From <strong><?= htmlspecialchars($menuRow['menu_name']) ?></strong>
                            · <?= count($products) ?> product<?= count($products) === 1 ? '' : 's' ?>
                        </p>
                    </div>
                </section>

                <!-- Products -->
                <?php if (empty($products)): ?>

                    <div class="mm-empty">
                        <h3>No products in this category for the current menu</h3>
                        <p>Try another category or check back soon.</p>
                    </div>

                <?php else: ?>

                    <div class="mm-products">
                        <?php foreach ($products as $p):
                            $img = productImageUrl($p['product_image'] ?? '');
                        ?>
                            <div class="mm-product js-product-card"
                                data-product-id="<?= (int)$p['id'] ?>"
                                data-menu-code="<?= htmlspecialchars($menuCode) ?>">

                                <!-- Image (clickable → product.php) -->
                                <a href="product.php?id=<?= (int)$p['id'] ?>" class="mm-product-img-link">
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

                                        <span class="mm-menu-label">
                                            <i class="bi bi-fire"></i> Today
                                        </span>

                                        <?php if ($badgeText): ?>
                                            <span class="mm-discount-badge"><?= htmlspecialchars($badgeText) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </a>

                                <h3 class="mm-product-name"><?= htmlspecialchars($p['product_name']) ?></h3>

                                <!-- Variants loaded by home.js -->
                                <div class="mm-variants" data-loaded="0">
                                    <div class="mm-variants-loading">Loading variants…</div>
                                </div>

                                <!-- Add to cart button (home.js wires this up) -->
                                <button type="button"
                                    class="mm-add-btn js-add-cart"
                                    data-product-id="<?= (int)$p['id'] ?>"
                                    data-menu-code="<?= htmlspecialchars($menuCode) ?>"
                                    disabled>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="9" cy="20" r="1.5" />
                                        <circle cx="18" cy="20" r="1.5" />
                                        <path d="M2 3h2l2.4 12.2a2 2 0 0 0 2 1.8h8.7a2 2 0 0 0 2-1.6L21 7H5" />
                                    </svg>
                                    <span>Add to Cart</span>
                                </button>

                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php endif; ?>

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