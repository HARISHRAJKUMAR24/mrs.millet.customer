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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include_once './includes/head_links.php'; ?>

    <style>
        /* =====================================================
           PAGE WRAPPER — responsive left/right padding
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
           LAYOUT GRID
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

        /* ---------- Cart items card ---------- */
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

        /* ---------- Remove button ---------- */
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

        /* =====================================================
           SUMMARY CARD
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

        .ct-empty a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 20px;
            background: #b51f2c;
            color: #fff;
            border-radius: 10px;
            text-decoration: none;
            font-size: 12.5px;
            font-weight: 800;
        }

        .ct-empty a:hover {
            background: #8e1722;
            color: #fff;
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
                    <a href="<?= MAIN_URL ?>">
                        <i class="bi bi-arrow-left"></i>
                        Continue Shopping
                    </a>
                </div>
            <?php else: ?>

                <div class="ct-layout">

                    <!-- ============ ITEMS ============ -->
                    <div class="ct-items" id="cartItemsList">
                        <?php foreach ($items as $it):
                            $img = !empty($it['image']) ? $it['image'] : '';
                            $lineTotal = ((float)$it['price']) * ((int)$it['qty']);
                        ?>
                            <div class="ct-item"
                                data-product-id="<?= (int)$it['product_id'] ?>"
                                data-variant-id="<?= (int)$it['variant_id'] ?>">

                                <div class="ct-item-thumb">
                                    <?php if ($img): ?>
                                        <img src="<?= htmlspecialchars($img) ?>" alt="">
                                    <?php else: ?>
                                        <i class="bi bi-image"></i>
                                    <?php endif; ?>
                                </div>

                                <div class="ct-item-info">
                                    <p class="ct-item-name"><?= htmlspecialchars($it['name'] ?? '') ?></p>
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
                                    <button type="button" class="js-dec" data-product-id="<?= (int)$it['product_id'] ?>" data-variant-id="<?= (int)$it['variant_id'] ?>" <?= ((int)$it['qty']) <= 1 ? 'disabled' : '' ?>>
                                        <i class="bi bi-dash"></i>
                                    </button>
                                    <span class="js-qty"><?= (int)$it['qty'] ?></span>
                                    <button type="button" class="js-inc" data-product-id="<?= (int)$it['product_id'] ?>" data-variant-id="<?= (int)$it['variant_id'] ?>">
                                        <i class="bi bi-plus"></i>
                                    </button>
                                </div>

                                <button type="button" class="ct-remove js-remove" data-product-id="<?= (int)$it['product_id'] ?>" data-variant-id="<?= (int)$it['variant_id'] ?>">
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

                        <a href="<?= MAIN_URL ?>checkout.php" class="ct-checkout-btn" id="checkoutBtn">
                            <i class="bi bi-shield-lock-fill"></i>
                            Proceed to Checkout
                        </a>
                    </aside>

                </div>

            <?php endif; ?>

        </div>
    </main>


    <?php include_once './includes/mobile-nav-bar.php'; ?>
    <?php include_once './includes/login-poup.php'; ?>
    <?php include_once './includes/register-poup.php'; ?>

    <div id="mmToast"></div>


    <script>
        window.MAIN_URL = "<?= MAIN_URL ?>";
        window.IS_LOGGED_IN = <?= $customerId > 0 ? 'true' : 'false' ?>;
        window.CART_COUNT = <?= (int)$cartCount ?>;
    </script>
    <script src="<?= MAIN_URL ?>js/home.js"></script>
    <script src="<?= MAIN_URL ?>js/cart-page.js"></script>

</body>

</html>