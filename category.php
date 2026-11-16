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
   LOAD ALL ACTIVE PRODUCTS IN THIS CATEGORY (no menu logic)
   ========================================================= */
$products = [];

if ($category) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, product_code, product_name, description, product_image
             FROM products
             WHERE category_id = ? AND status = 1
             ORDER BY id ASC"
        );
        $stmt->execute([$category['id']]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $products = [];
    }

    /* Attach variants to each product */
    foreach ($products as &$p) {
        $p['variants'] = [];
        try {
            $vStmt = $pdo->prepare(
                "SELECT id, quantity, quantity_unit, quantity_name, price
                 FROM product_variants
                 WHERE product_code = ? AND status = 1
                 ORDER BY quantity ASC"
            );
            $vStmt->execute([$p['product_code']]);
            $p['variants'] = $vStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $p['variants'] = [];
        }
    }
    unset($p);
}

/* =========================================================
   LOGGED-IN CUSTOMER & CART
   ========================================================= */
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
} else {
    if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $c) {
            $q = (int)($c['qty'] ?? 0);
            $cartCount += $q;
            $cartTotal += ((float)($c['price'] ?? 0)) * $q;
        }
    }
}

$categoryImage = $category ? categoryImageUrl($category['category_image'] ?? '') : '';

/* Helper: format quantity like "650 gram" */
function fmtQty($qty, $unit): string
{
    $n = rtrim(rtrim(number_format((float)$qty, 2, '.', ''), '0'), '.');
    return trim($n . ' ' . $unit);
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

        /* =====================================================
           PRODUCT CARD — LEFT: image+info, RIGHT: variants
           ===================================================== */
        .mm-product-card {
            background: #fff;
            border: 1.5px solid #e8e1d8;
            border-radius: 16px;
            padding: 14px;
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 14px;
            transition: border-color .2s ease, box-shadow .2s ease;
            align-items: flex-start;
        }

        .mm-product-card:hover {
            border-color: #d98a91;
            box-shadow: 0 6px 18px rgba(181, 31, 44, .08);
        }

        /* LEFT column */
        .mm-pc-left {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .mm-pc-img-link {
            display: block;
            text-decoration: none;
        }

        .mm-pc-img {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            aspect-ratio: 1 / 1;
            background: #f7f2ec;
        }

        .mm-pc-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .mm-pc-img>svg {
            width: 50%;
            height: 50%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #d5cbbd;
        }

        /* RIGHT column */
        .mm-pc-right {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .mm-pc-name {
            font-size: 14px;
            font-weight: 800;
            color: #302923;
            margin: 0;
            line-height: 1.3;
            text-transform: uppercase;
            letter-spacing: .01em;
        }

        /* ---- Short description + View More ---- */
        .mm-pc-desc {
            font-size: 12px;
            color: #6f6860;
            margin: 0;
            line-height: 1.45;
            word-break: break-word;
        }

        .mm-pc-desc strong,
        .mm-pc-desc b {
            color: #302923;
            font-weight: 700;
        }

        .mm-pc-desc ul,
        .mm-pc-desc ol {
            margin: 4px 0 4px 18px;
            padding: 0;
        }

        .mm-pc-desc li {
            margin: 2px 0;
        }

        /* Smooth description expand / collapse */
        .mm-pc-desc {
            max-height: 2000px;
            overflow: hidden;
            transition: max-height .38s ease;
            position: relative;
        }

        .mm-pc-desc.clamped {
            max-height: 2.9em;
            /* approximately two lines */
        }

        .mm-pc-desc.clamped::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 1.45em;
            background: linear-gradient(to bottom, rgba(255, 255, 255, 0), #ffffff77 90%);
            pointer-events: none;
        }

        .mm-pc-desc-toggle {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: none;
            border: 0;
            padding: 0;
            font-size: 11.5px;
            font-weight: 800;
            color: #b51f2c;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: .05em;
            align-self: flex-start;
            margin-top: 2px;
        }

        .mm-pc-desc-toggle:hover {
            color: #8e1722;
            text-decoration: underline;
        }

        .mm-pc-desc-toggle i {
            font-size: 12px;
            transition: transform .2s;
        }

        .mm-pc-desc-toggle.open i {
            transform: rotate(180deg);
        }

        /* Variant table-like list */
        .mm-pc-variants {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-top: 6px;
            padding-top: 8px;
            border-top: 1px dashed #ece5da;
        }

        .mm-pc-var-row {
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            line-height: 1.35;
            padding: 5px 8px;
            border-radius: 8px;
            background: #fdfaf4;
            border: 1px solid #f3ece0;
        }

        .mm-pc-var-row:hover {
            background: #fff7ee;
            border-color: #ecdcc4;
        }

        .mm-pc-var-label {
            color: #302923;
            font-weight: 700;
            min-width: 0;
        }

        .mm-pc-var-label .vn {
            text-transform: uppercase;
            letter-spacing: .02em;
            font-size: 11px;
        }

        .mm-pc-var-label .vq {
            font-size: 10.5px;
            color: #817a71;
            margin-left: 6px;
            font-weight: 600;
        }

        .mm-pc-var-price {
            font-size: 13px;
            font-weight: 900;
            color: #b51f2c;
            white-space: nowrap;
        }

        .mm-pc-no-variants {
            font-size: 11.5px;
            color: #948c82;
            padding: 6px 0;
            font-style: italic;
        }

        .mm-empty {
            text-align: center;
            padding: 40px 20px;
            color: #948c82;
            font-size: 13px;
        }

        .mm-empty-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #fdfaf4;
            color: #b8893c;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }

        .mm-empty-icon svg {
            width: 36px;
            height: 36px;
        }

        .mm-empty h3 {
            font-family: "Playfair Display", serif;
            font-size: 20px;
            color: #302923;
            margin: 0 0 8px;
        }

        .mm-empty p {
            margin: 0;
            font-size: 13px;
        }

        /* Products grid */
        .mm-products {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
            margin-top: 24px;
        }

        @media (min-width: 700px) {
            .mm-products {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }
        }

        @media (min-width: 1100px) {
            .mm-products {
                grid-template-columns: repeat(3, 1fr);
                gap: 18px;
            }
        }

        @media (max-width: 420px) {
            .mm-product-card {
                grid-template-columns: 96px 1fr;
                padding: 10px;
                gap: 10px;
            }

            .mm-pc-name {
                font-size: 12.5px;
            }

            .mm-pc-desc {
                font-size: 11px;
            }

            .mm-pc-desc-toggle {
                font-size: 10.5px;
            }

            .mm-pc-var-row {
                font-size: 11px;
                padding: 4px 6px;
            }

            .mm-pc-var-price {
                font-size: 12px;
            }
        }
    </style>
</head>

<body>

    <?php include_once './includes/nav-bar.php'; ?>

    <main class="mm-main">
        <div class="mm-container">

            <?php if (!$category): ?>

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

                <div class="mm-crumb">
                    <a href="<?= MAIN_URL ?>">Home</a>
                    <span class="sep">›</span>
                    <span><?= htmlspecialchars($category['category_name']) ?></span>
                </div>

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
                            <?= count($products) ?> Product<?= count($products) === 1 ? '' : 's' ?> available
                        </p>
                    </div>
                </section>

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
                            <p>There are no active products in this category.</p>
                        </div>
                    </section>

                <?php else: ?>

                    <div class="mm-products">
                        <?php foreach ($products as $p):
                            $img      = productImageUrl($p['product_image'] ?? '');
                            $variants = $p['variants'];
                            $descHtml = trim((string)($p['description'] ?? ''));

                            /* Plain text (no tags) to decide whether a "View More" toggle is needed */
                            $descPlain = trim(preg_replace('/\s+/', ' ', strip_tags($descHtml)));

                            /* Show button if plain text is longer than ~90 chars OR contains block/list tags */
                            $needsToggle = mb_strlen($descPlain) > 90
                                || preg_match('/<(br|p|div|li|ul|ol|h[1-6])\b/i', $descHtml);
                        ?>
                            <div class="mm-product-card">

                                <!-- LEFT: image -->
                                <div class="mm-pc-left">
                                    <a href="product.php?id=<?= (int)$p['id'] ?>" class="mm-pc-img-link">
                                        <div class="mm-pc-img">
                                            <?php if ($img): ?>
                                                <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($p['product_name']) ?>">
                                            <?php else: ?>
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                                    <path d="M3.3 7L12 12l8.7-5" />
                                                    <path d="M12 22V12" />
                                                </svg>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                </div>

                                <!-- RIGHT: name + short desc + view more + variants -->
                                <div class="mm-pc-right">

                                    <h3 class="mm-pc-name"><?= htmlspecialchars($p['product_name']) ?></h3>

                                    <?php if ($descHtml !== ''): ?>
                                        <div class="mm-pc-desc <?= $needsToggle ? 'clamped' : '' ?>" data-full-desc>
                                            <?= $descHtml ?>
                                        </div>

                                        <?php if ($needsToggle): ?>
                                            <button type="button" class="mm-pc-desc-toggle" data-desc-toggle aria-expanded="false">
                                                View More <i class="bi bi-chevron-down"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if (!empty($variants)): ?>
                                        <div class="mm-pc-variants">
                                            <?php foreach ($variants as $v):
                                                $vName  = trim((string)($v['quantity_name'] ?? ''));
                                                $vQty   = fmtQty($v['quantity'] ?? 0, $v['quantity_unit'] ?? '');
                                                $vPrice = (float)($v['price'] ?? 0);
                                            ?>
                                                <div class="mm-pc-var-row">
                                                    <div class="mm-pc-var-label">
                                                        <?php if ($vName !== ''): ?>
                                                            <span class="vn"><?= htmlspecialchars($vName) ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($vQty !== ''): ?>
                                                            <span class="vq"><?= htmlspecialchars($vQty) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="mm-pc-var-price">₹<?= number_format($vPrice, 0) ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="mm-pc-no-variants">No variants available</div>
                                    <?php endif; ?>

                                </div>

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

    <script>
        /* Smooth, independent View More / View Less for each product */
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-desc-toggle]');
            if (!btn) return;

            var card = btn.closest('.mm-product-card');
            if (!card) return;

            var desc = card.querySelector('[data-full-desc]');
            if (!desc) return;

            var isOpening = desc.classList.contains('clamped');

            if (isOpening) {
                // Remove the two-line limit and animate to the full content height.
                desc.classList.remove('clamped');
                desc.style.maxHeight = desc.scrollHeight + 'px';
                btn.innerHTML = 'View Less <i class="bi bi-chevron-up"></i>';
                btn.classList.add('open');
                btn.setAttribute('aria-expanded', 'true');
            } else {
                // Start from the current full height, then animate back to two lines.
                desc.style.maxHeight = desc.scrollHeight + 'px';
                desc.offsetHeight; // force browser reflow before collapsing
                desc.classList.add('clamped');
                desc.style.maxHeight = '2.9em';
                btn.innerHTML = 'View More <i class="bi bi-chevron-down"></i>';
                btn.classList.remove('open');
                btn.setAttribute('aria-expanded', 'false');
            }
        });
    </script>

    <script src="<?= MAIN_URL ?>js/home.js"></script>

</body>

</html>