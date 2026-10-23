<?php
require_once './config/config.php';
require_once './config/function.php';

$settings = getSettings($pdo);

$siteName = $settings['username'] ?? 'Mrs Mill@';
$logoUrl  = !empty($settings['logo_image'])    ? ADMIN_URL . $settings['logo_image']    : '';
$favicon  = !empty($settings['favicon_image']) ? ADMIN_URL . $settings['favicon_image'] : '';

/* ---------- FETCH ACTIVE MENUS (Get the First = Today) ---------- */
$activeMenus = getActiveMenus($pdo);
$todayMenu   = $activeMenus[0] ?? null;

if (!$todayMenu) {
    header('Location: index.php');
    exit;
}

$todayMenuCode = $todayMenu['menu_code'];

/* ---------- OFFER IMAGE ---------- */
$todayOfferImg = !empty($settings['today_offer_image']) ? ADMIN_URL . $settings['today_offer_image'] : '';

/* ---------- FETCH PRODUCTS & CATEGORIES ---------- */
$rawRows    = getMenuProducts($pdo, 500, $todayMenuCode);
$products   = groupMenuProductsByProduct($rawRows);
$categories = getMenuCategories($pdo, 50, $todayMenuCode);

/* ---------- DISCOUNT ---------- */
function getMenuDiscount(PDO $pdo, array $menu): ?array
{
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

$menuDiscount = getMenuDiscount($pdo, $todayMenu);
$badgeText = '';
if ($menuDiscount && $menuDiscount['amount'] > 0) {
    $badgeText = $menuDiscount['amount_type'] === 'percent'
        ? number_format($menuDiscount['amount'], 0) . '% OFF'
        : '₹' . number_format($menuDiscount['amount'], 0) . ' OFF';
}

/* ---------- CUSTOMER & CART ---------- */
$customerLoggedIn = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0;
$customerRowId    = $customerLoggedIn ? (int)$_SESSION['customer_id'] : 0;
$customerCode     = $_SESSION['customer_code']   ?? '';
$customerName     = $_SESSION['customer_name']   ?? '';
$customerMobile   = $_SESSION['customer_mobile'] ?? '';

$cartCount = 0;
$cartTotal = 0.0;

if ($customerRowId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(qty),0) AS c, COALESCE(SUM(price * qty),0) AS t FROM customer_cart WHERE customer_id = ?");
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
        /* =====================================================
           OFFER HERO
           ===================================================== */
        .mm-offer-hero {
            padding: 24px 0 0;
        }

        .mm-offer-banner {
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 10px 26px rgba(48, 41, 35, .08);
        }

        .mm-offer-banner img {
            display: block;
            width: 100%;
            aspect-ratio: 16 / 9;
            object-fit: contain;
        }

        @media (min-width: 768px) {
            .mm-offer-banner img {
                aspect-ratio: 16 / 7;
            }
        }

        @media (min-width: 1100px) {
            .mm-offer-banner img {
                aspect-ratio: 16 / 6;
            }
        }

        /* =====================================================
           PAGE HEADER
           ===================================================== */
        .mm-page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px 0;
            margin-top: 30px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .mm-page-header h2 {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            color: #302923;
            font-family: "Playfair Display", serif;
        }

        .mm-page-header .sub {
            font-size: 12px;
            color: #817a71;
        }

        .mm-page-header .badge {
            background: #b51f2c;
            color: #fff;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
            white-space: nowrap;
        }

        /* =====================================================
           PRODUCTS GRID — SAME AS INDEX (responsive auto-fill)
           ===================================================== */
        .mm-products {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 14px;
            align-items: stretch;
            margin-bottom: 80px;
        }

        /* =====================================================
           PRODUCT CARD
           ===================================================== */
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
            aspect-ratio: 1 / 1;
            background: #f7f2ec;
            display: block;
        }

        .mm-product-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .mm-product-name {
            font-size: 13px;
            font-weight: 800;
            color: #302923;
            margin: 2px 0 4px;
            line-height: 1.3;
            letter-spacing: .01em;
            text-transform: uppercase;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* ---------- MENU LABEL ---------- */
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

        /* ---------- DISCOUNT BADGE ---------- */
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

        /* ---------- VARIANTS ---------- */
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
            line-height: 1.2;
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

        /* ---------- QTY STEPPER ---------- */
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

        /* ---------- ADD TO CART ---------- */
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

        /* =====================================================
           RESPONSIVE
           ===================================================== */
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
        <!-- Offer Hero -->
        <?php if (!empty($todayOfferImg)): ?>
            <section class="mm-offer-hero">
                <div class="mm-container">
                    <div class="mm-offer-banner">
                        <img src="<?= htmlspecialchars($todayOfferImg) ?>" alt="Today Offer">
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <div class="mm-container">
            <!-- Page Header -->
            <div class="mm-page-header">
                <div>
                    <h2>Today's Menu</h2>
                    <span class="sub"><?= htmlspecialchars($todayMenu['menu_name']) ?></span>
                </div>
                <?php if ($badgeText): ?>
                    <span class="badge"><?= htmlspecialchars($badgeText) ?></span>
                <?php endif; ?>
            </div>

            <!-- Products Grid -->
            <?php if (!empty($products)): ?>
                <div class="mm-products">
                    <?php foreach ($products as $p):
                        $img = productImageUrl($p['product_image'] ?? '');
                    ?>
                        <div class="mm-product js-product-card"
                            data-product-id="<?= (int)$p['id'] ?>"
                            data-menu-code="<?= htmlspecialchars($todayMenuCode) ?>">

                            <!-- ONLY IMAGE IS CLICKABLE -->
                            <a href="product.php?id=<?= (int)$p['id'] ?>" class="mm-product-img-link">
                                <div class="mm-product-img">
                                    <?php if ($img): ?>
                                        <img src="<?= htmlspecialchars($img) ?>" alt="">
                                    <?php endif; ?>

                                    <span class="mm-menu-label today">
                                        <i class="bi bi-fire"></i> Today
                                    </span>

                                    <?php if ($badgeText): ?>
                                        <span class="mm-discount-badge"><?= htmlspecialchars($badgeText) ?></span>
                                    <?php endif; ?>
                                </div>
                            </a>

                            <h3 class="mm-product-name"><?= htmlspecialchars($p['product_name']) ?></h3>

                            <div class="mm-variants" data-loaded="0">
                                <div class="mm-variants-loading">Loading variants…</div>
                            </div>

                            <button type="button"
                                class="mm-add-btn js-add-cart"
                                data-product-id="<?= (int)$p['id'] ?>"
                                data-menu-code="<?= htmlspecialchars($todayMenuCode) ?>"
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
            <?php else: ?>
                <div class="mm-empty" style="text-align:center; padding:40px; color:#948c82;">
                    No products available in today's menu.
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
            id: <?= (int)$customerRowId ?>
        };
    </script>

    <script src="<?= MAIN_URL ?>js/home.js"></script>
</body>

</html>