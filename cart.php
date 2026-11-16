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

$customerId = $customerLoggedIn ? (int)$_SESSION['customer_id'] : 0;

/* ---------- Load cart items ---------- */
$items = [];
if ($customerId > 0) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id AS cart_id, menu_code, product_id, product_code AS code, product_name AS name,
                    product_image AS image, variant_id, variant_name, variant_qty,
                    price, qty
             FROM customer_cart WHERE customer_id = ? ORDER BY id DESC"
        );
        $stmt->execute([$customerId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
    }
} else {
    if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $c) {
            $items[] = [
                'cart_id'      => $c['key'] ?? '',
                'menu_code'    => $c['menu_code'] ?? '',
                'product_id'   => (int)($c['product_id'] ?? 0),
                'code'         => $c['code'] ?? '',
                'name'         => $c['name'] ?? '',
                'image'        => $c['image'] ?? '',
                'variant_id'   => (int)($c['variant_id'] ?? 0),
                'variant_name' => $c['variant_name'] ?? '',
                'variant_qty'  => $c['variant_qty'] ?? '',
                'price'        => (float)($c['price'] ?? 0),
                'qty'          => (int)($c['qty'] ?? 0)
            ];
        }
    }
}

$cartCount = 0;
$cartTotal = 0.0;
foreach ($items as $it) {
    $q = (int)($it['qty'] ?? 0);
    $cartCount += $q;
    $cartTotal += ((float)($it['price'] ?? 0)) * $q;
}

/* =========================================================
   MENU LOOKUP
   ========================================================= */
$menuMeta = [];
$cartMenuCodes = [];
foreach ($items as $it) {
    $mc = trim((string)($it['menu_code'] ?? ''));
    if ($mc !== '') $cartMenuCodes[$mc] = true;
}

if (!empty($cartMenuCodes)) {
    try {
        $in = implode(',', array_fill(0, count($cartMenuCodes), '?'));
        $mStmt = $pdo->prepare(
            "SELECT menu_code, menu_name, start_at, end_at
             FROM menus WHERE menu_code IN ($in)"
        );
        $mStmt->execute(array_keys($cartMenuCodes));
        $todayDate    = date('Y-m-d');
        $tomorrowDate = date('Y-m-d', strtotime('+1 day'));

        foreach ($mStmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
            $endDate = !empty($m['end_at']) ? date('Y-m-d', strtotime($m['end_at'])) : '';
            if ($endDate === $todayDate) {
                $label = 'Today';
                $cls   = 'today';
                $icon  = 'bi bi-fire';
            } elseif ($endDate === $tomorrowDate) {
                $label = 'Tomorrow';
                $cls   = 'tomorrow';
                $icon  = 'bi bi-sunrise';
            } else {
                $label = $m['menu_name'];
                $cls   = 'default';
                $icon  = 'bi bi-grid';
            }
            $menuMeta[$m['menu_code']] = [
                'label' => $label,
                'class' => $cls,
                'icon'  => $icon,
                'name'  => $m['menu_name'],
            ];
        }
    } catch (PDOException $e) {
    }
}

/* =========================================================
   LIVE STOCK CHECK
   ========================================================= */
$cartStock = []; // key: menu_code|product_code|variant_id => ['unlimited','count','oos']

if (!empty($items)) {
    $rowsByMenu = [];
    foreach ($items as $it) {
        $mc = trim((string)($it['menu_code'] ?? ''));
        if ($mc === '') continue;
        $rowsByMenu[$mc][] = $it;
    }

    foreach ($rowsByMenu as $mc => $rows) {
        try {
            $productCodes = array_unique(array_filter(array_map(
                fn($r) => trim((string)($r['code'] ?? '')),
                $rows
            )));
            if (empty($productCodes)) continue;

            $in = implode(',', array_fill(0, count($productCodes), '?'));
            $sStmt = $pdo->prepare(
                "SELECT mp.product_code, mp.variant_id,
                        COALESCE(mp.stock_unlimited,0) AS stock_unlimited,
                        COALESCE(mp.stock_count,0)     AS stock_count
                 FROM menu_products mp
                 WHERE mp.menu_code = ? AND mp.product_code IN ($in)"
            );
            $params = array_merge([$mc], $productCodes);
            $sStmt->execute($params);

            while ($s = $sStmt->fetch(PDO::FETCH_ASSOC)) {
                $key = $mc . '|' . $s['product_code'] . '|' . (int)$s['variant_id'];
                $unlimited = (int)$s['stock_unlimited'] === 1;
                $count     = (int)$s['stock_count'];
                $oos       = (!$unlimited && $count <= 0);

                $cartStock[$key] = [
                    'unlimited' => $unlimited,
                    'count'     => $count,
                    'oos'       => $oos,
                ];
            }
        } catch (PDOException $e) {
        }
    }
}

/* Helper: resolve stock for a row */
function cartRowStock(array $it, array $cartStock): array
{
    $mc   = trim((string)($it['menu_code'] ?? ''));
    $code = trim((string)($it['code']      ?? ''));
    $vid  = (int)($it['variant_id'] ?? 0);

    if ($mc === '' || $code === '' || $vid <= 0) {
        return ['unlimited' => true, 'count' => 9999, 'oos' => false];
    }
    $key = $mc . '|' . $code . '|' . $vid;
    if (isset($cartStock[$key])) return $cartStock[$key];
    return ['unlimited' => true, 'count' => 9999, 'oos' => false];
}

/* Global flag — any row sold out? */
$hasAnyOOS = false;
foreach ($items as $it) {
    $s = cartRowStock($it, $cartStock);
    if (!empty($s['oos'])) {
        $hasAnyOOS = true;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include_once './includes/head_links.php'; ?>

    <style>
        /* =====================================================
           PAGE WRAPPER
           ===================================================== */
        .ct-page {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px 16px 120px;
            width: 100%;
        }

        @media (min-width: 640px) {
            .ct-page {
                padding: 28px 24px 130px;
            }
        }

        @media (min-width: 1024px) {
            .ct-page {
                padding: 32px 32px 140px;
            }
        }

        /* ---------- Header ---------- */
        .ct-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .ct-title h1 {
            font-family: "Playfair Display", serif;
            font-size: 24px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 4px;
        }

        @media (min-width: 640px) {
            .ct-title h1 {
                font-size: 28px;
            }
        }

        .ct-title p {
            margin: 0;
            font-size: 12.5px;
            color: #817a71;
        }

        .ct-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 999px;
            background: #fbe8e9;
            color: #b51f2c;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .04em;
        }

        /* =====================================================
           LAYOUT
           ===================================================== */
        .ct-layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: 18px;
            align-items: start;
        }

        @media (min-width: 900px) {
            .ct-layout {
                grid-template-columns: 1fr 360px;
                gap: 24px;
            }
        }

        .ct-items {
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 18px;
            overflow: hidden;
        }

        .ct-item {
            display: grid;
            grid-template-columns: 68px 1fr auto;
            grid-template-areas:
                "thumb info remove"
                "thumb qty  qty";
            align-items: center;
            gap: 10px 14px;
            padding: 14px 16px;
            border-bottom: 1.5px solid #f5efe5;
            position: relative;
        }

        .ct-item:last-child {
            border-bottom: 0;
        }

        @media (min-width: 640px) {
            .ct-item {
                grid-template-columns: 68px 1fr auto auto;
                grid-template-areas: "thumb info qty remove";
                gap: 14px;
                padding: 16px 18px;
            }
        }

        .ct-item-thumb {
            grid-area: thumb;
            width: 68px;
            height: 68px;
            border-radius: 14px;
            background: #f7efe3;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #b0a79c;
            font-size: 24px;
            flex-shrink: 0;
        }

        .ct-item-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .ct-item-info {
            grid-area: info;
            min-width: 0;
        }

        .ct-item-name {
            font-size: 13.5px;
            font-weight: 700;
            color: #302923;
            margin: 0;
            line-height: 1.35;
        }

        .ct-item-meta {
            font-size: 11px;
            color: #948c82;
            margin: 3px 0 0;
            font-weight: 600;
        }

        .ct-item-price {
            font-family: "Playfair Display", serif;
            font-size: 14.5px;
            font-weight: 800;
            color: #b51f2c;
            margin: 5px 0 0;
        }

        /* ---------- Menu Badge ---------- */
        .ct-menu-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 9.5px;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #fff;
            line-height: 1.3;
            margin: 4px 0 2px;
        }

        .ct-menu-badge.today {
            background: linear-gradient(135deg, #d42a3a, #b51f2c);
        }

        .ct-menu-badge.tomorrow {
            background: linear-gradient(135deg, #3b82f6, #1e40af);
        }

        .ct-menu-badge.default {
            background: #302923;
        }

        .ct-menu-badge i {
            font-size: 9px;
        }

        /* ---------- Sold-Out chip ---------- */
        .ct-oos-chip {
            display: inline-block;
            margin-left: 6px;
            padding: 1px 7px;
            font-size: 9.5px;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #b51f2c;
            background: #fde6e6;
            border: 1px solid #f3c8cc;
            border-radius: 4px;
            vertical-align: middle;
        }

        /* ---------- Qty controls ---------- */
        .ct-qty {
            grid-area: qty;
            display: inline-flex;
            align-items: center;
            gap: 2px;
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 10px;
            padding: 3px;
            width: fit-content;
        }

        @media (min-width: 640px) {
            .ct-qty {
                justify-self: end;
            }
        }

        .ct-qty button {
            width: 28px;
            height: 28px;
            border: 0;
            background: transparent;
            color: #6f675f;
            border-radius: 7px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            transition: .15s ease;
        }

        .ct-qty button:hover:not(:disabled) {
            background: #fbe8e9;
            color: #b51f2c;
        }

        .ct-qty button:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        .ct-qty span {
            font-size: 13px;
            font-weight: 800;
            color: #302923;
            min-width: 24px;
            text-align: center;
        }

        /* ---------- Remove ---------- */
        .ct-remove {
            grid-area: remove;
            justify-self: end;
            width: 30px;
            height: 30px;
            border: 1.5px solid #f0d6d8;
            background: #fff;
            color: #b51f2c;
            border-radius: 9px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            transition: .15s ease;
        }

        .ct-remove:hover {
            background: #fde6e6;
        }

        /* ---------- Sold-Out row state (simple) ---------- */
        .ct-item.is-oos {
            background: #fdfaf4;
        }

        .ct-item.is-oos .ct-item-meta,
        .ct-item.is-oos .ct-item-price {
            color: #a19a90;
        }

        .ct-item.is-oos .ct-qty {
            opacity: .55;
        }

        .ct-item.is-oos .ct-qty button {
            cursor: not-allowed;
        }

        /* =====================================================
           SUMMARY
           ===================================================== */
        .ct-summary {
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 18px;
            padding: 20px;
        }

        @media (min-width: 900px) {
            .ct-summary {
                padding: 22px;
                position: sticky;
                top: 84px;
            }
        }

        .ct-summary h2 {
            font-family: "Playfair Display", serif;
            font-size: 17px;
            font-weight: 700;
            margin: 0 0 14px;
            color: #302923;
        }

        .ct-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #6f675f;
            padding: 6px 0;
            font-weight: 600;
            gap: 12px;
        }

        .ct-row strong {
            color: #302923;
            font-weight: 800;
        }

        .ct-row.grand {
            font-size: 16px;
            margin-top: 8px;
            padding-top: 12px;
            border-top: 1.5px dashed #e4ddd3;
        }

        .ct-row.grand strong {
            color: #b51f2c;
            font-size: 20px;
        }

        .ct-checkout-btn {
            width: 100%;
            height: 50px;
            margin-top: 16px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%);
            color: #fff;
            font-family: "DM Sans", sans-serif;
            font-size: 13.5px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: .2s ease;
            box-shadow: 0 10px 24px rgba(181, 31, 44, .22);
            text-decoration: none;
        }

        .ct-checkout-btn:hover {
            transform: translateY(-1px);
            color: #fff;
        }

        /* Disabled checkout state */
        .ct-checkout-btn.is-disabled {
            background: #d8d2c9;
            box-shadow: none;
            cursor: not-allowed;
            pointer-events: none;
            opacity: .85;
        }

        /* =====================================================
           EMPTY STATE
           ===================================================== */
        .ct-empty {
            text-align: center;
            padding: 60px 24px;
            background: #fff;
            border: 1.5px dashed #ece5da;
            border-radius: 18px;
            color: #948c82;
        }

        @media (min-width: 640px) {
            .ct-empty {
                padding: 80px 40px;
            }
        }

        .ct-empty i {
            font-size: 46px;
            color: #ece5da;
            display: block;
            margin-bottom: 14px;
        }

        .ct-empty h3 {
            font-family: "Playfair Display", serif;
            font-size: 19px;
            color: #302923;
            margin: 0 0 6px;
            font-weight: 700;
        }

        .ct-empty p {
            margin: 0 0 16px;
            font-size: 13px;
        }

        .ct-empty-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 7px 14px;
            background: #fff;
            color: #b51f2c;
            border: 1.5px solid #f0d6d8;
            border-radius: 9px;
            text-decoration: none;
            font-size: 11.5px;
            font-weight: 800;
            transition: .2s ease;
        }

        .ct-empty-btn i {
            font-size: 11px;
        }

        .ct-empty-btn:hover {
            background: #fde6e6;
            border-color: #b51f2c;
            color: #b51f2c;
        }
    </style>
</head>

<body>

    <?php include_once './includes/nav-bar.php'; ?>

    <main class="mm-main">
        <div class="ct-page">

            <div class="ct-header">
                <div class="ct-title">
                    <h1>Your Cart</h1>
                    <p>Review items before checkout</p>
                </div>
                <span class="ct-badge">
                    <i class="bi bi-bag-check-fill"></i>
                    <span id="cartHeaderCount"><?= (int)$cartCount ?></span>
                    item<span id="cartHeaderPlural"><?= $cartCount === 1 ? '' : 's' ?></span>
                </span>
            </div>

            <?php if (empty($items)): ?>
                <div class="ct-empty">
                    <i class="bi bi-bag-x"></i>
                    <h3>Your cart is empty</h3>
                    <p>Add some products to get started.</p>
                    <a href="<?= MAIN_URL ?>" class="ct-empty-btn">
                        Continue Shopping
                    </a>
                </div>
            <?php else: ?>

                <div class="ct-layout">

                    <!-- ============ ITEMS ============ -->
                    <div class="ct-items" id="cartItemsList">
                        <?php foreach ($items as $it):
                            $img       = !empty($it['image']) ? $it['image'] : '';
                            $lineTotal = ((float)$it['price']) * ((int)$it['qty']);
                            $stock     = cartRowStock($it, $cartStock);
                            $isOOS     = !empty($stock['oos']);
                            $maxStock  = $stock['unlimited'] ? 9999 : (int)$stock['count'];
                            $currentQ  = (int)$it['qty'];
                        ?>
                            <div class="ct-item <?= $isOOS ? 'is-oos' : '' ?>"
                                data-product-id="<?= (int)$it['product_id'] ?>"
                                data-variant-id="<?= (int)$it['variant_id'] ?>"
                                data-max-stock="<?= $maxStock ?>"
                                data-oos="<?= $isOOS ? '1' : '0' ?>">

                                <div class="ct-item-thumb">
                                    <?php if ($img): ?>
                                        <img src="<?= htmlspecialchars($img) ?>" alt="">
                                    <?php else: ?>
                                        <i class="bi bi-image"></i>
                                    <?php endif; ?>
                                </div>

                                <div class="ct-item-info">
                                    <p class="ct-item-name">
                                        <?= htmlspecialchars($it['name'] ?? '') ?>
                                        <?php if ($isOOS): ?>
                                            <span class="ct-oos-chip">Sold Out</span>
                                        <?php endif; ?>
                                    </p>

                                    <?php
                                    $mc = trim((string)($it['menu_code'] ?? ''));
                                    $menuInfo = $mc !== '' && isset($menuMeta[$mc]) ? $menuMeta[$mc] : null;
                                    ?>

                                    <?php if ($menuInfo): ?>
                                        <span class="ct-menu-badge <?= htmlspecialchars($menuInfo['class']) ?>"
                                            title="<?= htmlspecialchars($menuInfo['name']) ?>">
                                            <i class="<?= htmlspecialchars($menuInfo['icon']) ?>"></i>
                                            <?= htmlspecialchars($menuInfo['label']) ?>
                                        </span>
                                    <?php endif; ?>

                                    <p class="ct-item-meta">
                                        <?php if (!empty($it['variant_name'])): ?>
                                            <?= htmlspecialchars($it['variant_name']) ?> ·
                                        <?php endif; ?>
                                        <?php if (!empty($it['variant_qty'])): ?>
                                            <?= htmlspecialchars($it['variant_qty']) ?> ·
                                        <?php endif; ?>
                                        <strong>₹<?= number_format((float)$it['price'], 0) ?></strong> each
                                    </p>
                                    <p class="ct-item-price">₹<?= number_format($lineTotal, 0) ?></p>
                                </div>

                                <div class="ct-qty">
                                    <button type="button" class="js-dec"
                                        data-product-id="<?= (int)$it['product_id'] ?>"
                                        data-variant-id="<?= (int)$it['variant_id'] ?>"
                                        <?= $currentQ <= 1 ? 'disabled' : '' ?>>
                                        <i class="bi bi-dash"></i>
                                    </button>
                                    <span class="js-qty"><?= $currentQ ?></span>
                                    <button type="button" class="js-inc"
                                        data-product-id="<?= (int)$it['product_id'] ?>"
                                        data-variant-id="<?= (int)$it['variant_id'] ?>"
                                        <?= $isOOS ? 'disabled' : '' ?>>
                                        <i class="bi bi-plus"></i>
                                    </button>
                                </div>

                                <button type="button" class="ct-remove js-remove"
                                    data-product-id="<?= (int)$it['product_id'] ?>"
                                    data-variant-id="<?= (int)$it['variant_id'] ?>">
                                    <i class="bi bi-trash3"></i>
                                </button>

                            </div>
                        <?php endforeach; ?>
                    </div>


                    <!-- ============ SUMMARY ============ -->
                    <aside class="ct-summary">
                        <h2>Order Summary</h2>

                        <div class="ct-row">
                            <span>Subtotal</span>
                            <strong id="summarySubtotal">₹<?= number_format($cartTotal, 0) ?></strong>
                        </div>

                        <div class="ct-row">
                            <span>Delivery</span>
                            <strong>Calculated at checkout</strong>
                        </div>

                        <div class="ct-row grand">
                            <span>Total</span>
                            <strong id="summaryTotal">₹<?= number_format($cartTotal, 0) ?></strong>
                        </div>

                        <?php if ($hasAnyOOS): ?>
                            <button type="button" class="ct-checkout-btn is-disabled" id="checkoutBtn" disabled>
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                Remove Sold - Out Items
                            </button>
                        <?php else: ?>
                            <a href="<?= MAIN_URL ?>checkout.php" class="ct-checkout-btn" id="checkoutBtn">
                                <i class="bi bi-shield-lock-fill"></i>
                                Proceed to Checkout
                            </a>
                        <?php endif; ?>
                    </aside>

                </div>

            <?php endif; ?>

        </div>
    </main>

    <?php include_once './includes/mobile-nav-bar.php'; ?>
    <?php include_once './includes/login-poup.php'; ?>
    <?php include_once './includes/register-poup.php'; ?>

    <div id="mmToast"></div>

    <?php include_once './includes/footer.php'; ?>

    <script>
        window.MAIN_URL = "<?= MAIN_URL ?>";
        window.IS_LOGGED_IN = <?= $customerId > 0 ? 'true' : 'false' ?>;
        window.CART_COUNT = <?= (int)$cartCount ?>;
        window.HAS_OOS = <?= $hasAnyOOS ? 'true' : 'false' ?>;
    </script>
    <script src="<?= MAIN_URL ?>js/home.js"></script>
    <script src="<?= MAIN_URL ?>js/cart-page.js"></script>

    <?php if ($hasAnyOOS): ?>
        <script>
            /* Safety net — if the button is somehow clickable, block it */
            (function() {
                var btn = document.getElementById("checkoutBtn");
                if (!btn) return;
                btn.addEventListener("click", function(e) {
                    e.preventDefault();
                    e.stopImmediatePropagation();

                    var t = document.getElementById("mmToast");
                    if (!t) {
                        t = document.createElement("div");
                        t.id = "mmToast";
                        document.body.appendChild(t);
                    }
                    t.textContent = "Please remove sold-out items to continue.";
                    t.className = "show error";
                    clearTimeout(t._t);
                    t._t = setTimeout(function() {
                        t.className = "";
                    }, 2600);
                }, true);
            })();
        </script>
    <?php endif; ?>

    <?php if (!$hasAnyOOS && !empty($items)): ?>
        <script>
            /* Quiet auto-refresh every 20s to catch stock changes live.
           Stops refreshing once any OOS is detected (page will reload itself
           with the sold-out state and refresh loop won't trigger). */
            (function() {
                setTimeout(function() {
                    if (document.activeElement &&
                        /INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) return;
                    if (window.HAS_OOS) return;
                    window.location.reload();
                }, 20000);
            })();
        </script>
    <?php endif; ?>

</body>

</html>