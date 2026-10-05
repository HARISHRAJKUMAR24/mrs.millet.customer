<?php
require_once './config/config.php';
require_once './config/function.php';

$settings = getSettings($pdo);

$siteName = $settings['username'] ?? 'Mrs Mill@';
$logoUrl  = !empty($settings['logo_image'])    ? ADMIN_URL . $settings['logo_image']    : '';
$favicon  = !empty($settings['favicon_image']) ? ADMIN_URL . $settings['favicon_image'] : '';
/* =========================================================
   AUTH — must be logged in
   ========================================================= */
$customerLoggedIn = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0;

if (!$customerLoggedIn) {
    header('Location: ' . MAIN_URL . 'login.php?redirect=my-orders.php');
    exit;
}

$customerId     = (int)$_SESSION['customer_id'];
$customerName   = $_SESSION['customer_name']   ?? '';
$customerMobile = $_SESSION['customer_mobile'] ?? '';

/* =========================================================
   FILTERS
   ========================================================= */
$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'pending', 'confirmed', 'delivered', 'cancelled'], true)) {
    $filter = 'all';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$filterWhere = "";
if ($filter !== 'all') {
    $filterWhere = " AND status = " . $pdo->quote($filter);
}

/* =========================================================
   LOAD ORDERS
   ========================================================= */
$orders = [];
$totalOrders = 0;
$totalPages  = 1;

try {
    /* Count */
    $cStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM orders
         WHERE customer_mobile = ? $filterWhere"
    );
    $cStmt->execute([$customerMobile]);
    $totalOrders = (int)$cStmt->fetchColumn();
    $totalPages  = max(1, (int)ceil($totalOrders / $perPage));

    if ($page > $totalPages) $page = $totalPages;
    $offset = ($page - 1) * $perPage;

    /* Load orders */
    $stmt = $pdo->prepare(
        "SELECT id, order_code, customer_name, customer_mobile,
                apartment_name, division, delivery_mode,
                pickup_branch_name,
                subtotal, division_charge, tax_amount, tax_rate, tax_type,
                total_amount, products_json, status, delivery_status,
                payment_status, created_at
         FROM orders
         WHERE customer_mobile = ? $filterWhere
         ORDER BY id DESC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute([$customerMobile]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $orders = [];
}

/* =========================================================
   FILTER COUNTS (for tab badges)
   ========================================================= */
$filterCounts = [
    'all'       => 0,
    'pending'   => 0,
    'confirmed' => 0,
    'delivered' => 0,
    'cancelled' => 0,
];

try {
    $rows = $pdo->prepare(
        "SELECT status, COUNT(*) AS cnt
         FROM orders
         WHERE customer_mobile = ?
         GROUP BY status"
    );
    $rows->execute([$customerMobile]);

    while ($r = $rows->fetch(PDO::FETCH_ASSOC)) {
        $s = strtolower($r['status']);
        if (isset($filterCounts[$s])) {
            $filterCounts[$s] = (int)$r['cnt'];
        }
        $filterCounts['all'] += (int)$r['cnt'];
    }
} catch (PDOException $e) {
}

/* =========================================================
   HELPERS
   ========================================================= */
function rupees($n)
{
    return '₹' . number_format((int)round($n));
}

function formatOrderDate($ts)
{
    $t = strtotime($ts);
    if ($t === false) return '';

    $today = strtotime(date('Y-m-d 00:00:00'));
    $day   = strtotime(date('Y-m-d 00:00:00', $t));

    $daysDiff = (int)round(($today - $day) / 86400);

    if ($daysDiff === 0) return 'Today, ' . date('g:i A', $t);
    if ($daysDiff === 1) return 'Yesterday, ' . date('g:i A', $t);
    if ($daysDiff < 7)  return $daysDiff . ' days ago';
    return date('d M Y, g:i A', $t);
}

function statusMeta($status, $paymentStatus = '')
{
    $status = strtolower($status);
    $paymentStatus = strtolower($paymentStatus);

    /* Cancelled overrides everything */
    if ($status === 'cancelled') {
        return [
            'label' => 'Cancelled',
            'class' => 'cancelled',
            'icon'  => 'bi-x-circle-fill'
        ];
    }

    /* Delivered */
    if ($status === 'delivered') {
        return [
            'label' => 'Delivered',
            'class' => 'delivered',
            'icon'  => 'bi-check-circle-fill'
        ];
    }

    /* Processing / Confirmed */
    if ($status === 'processing' || $status === 'confirmed') {
        return [
            'label' => 'Confirmed',
            'class' => 'confirmed',
            'icon'  => 'bi-check-circle-fill'
        ];
    }

    /* Pending */
    return [
        'label' => 'Pending',
        'class' => 'pending',
        'icon'  => 'bi-clock-fill'
    ];
}

function paymentMeta($paymentStatus)
{
    $p = strtolower($paymentStatus);
    switch ($p) {
        case 'paid':
            return ['label' => 'Paid', 'class' => 'paid'];
        case 'failed':
            return ['label' => 'Failed', 'class' => 'failed'];
        default:
            return ['label' => 'Unpaid', 'class' => 'unpaid'];
    }
}

/* Cart */
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

    <style>
        /* =====================================================
           MY ORDERS PAGE
           ===================================================== */
        .mo-page {
            padding: 26px 0 80px;
        }

        .mo-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }

        .mo-head h1 {
            font-family: "Playfair Display", serif;
            font-size: 30px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 6px;
        }

        .mo-head p {
            margin: 0;
            font-size: 13px;
            color: #817a71;
        }

        .mo-head-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .mo-btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 11px 20px;
            border-radius: 12px;
            background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%);
            color: #fff;
            font-family: "DM Sans", sans-serif;
            font-size: 12.5px;
            font-weight: 800;
            text-decoration: none;
            box-shadow: 0 10px 24px rgba(181, 31, 44, .22);
            transition: .2s ease;
            border: none;
            cursor: pointer;
        }

        .mo-btn-primary:hover {
            transform: translateY(-1px);
            color: #fff;
        }

        .mo-btn-ghost {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 11px 20px;
            border-radius: 12px;
            background: #fff;
            border: 1.5px solid #e4ddd3;
            color: #6f675f;
            font-family: "DM Sans", sans-serif;
            font-size: 12.5px;
            font-weight: 800;
            text-decoration: none;
            transition: .2s ease;
        }

        .mo-btn-ghost:hover {
            background: #faf7f0;
            color: #302923;
        }

        /* ---------- FILTER TABS ---------- */
        .mo-tabs {
            display: flex;
            gap: 6px;
            padding: 5px;
            background: #fdfaf4;
            border: 1.5px solid #ece5da;
            border-radius: 14px;
            margin-bottom: 22px;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .mo-tabs::-webkit-scrollbar { display: none; }

        .mo-tab {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 16px;
            border: none;
            background: transparent;
            border-radius: 10px;
            font-family: "DM Sans", sans-serif;
            font-size: 12px;
            font-weight: 800;
            color: #6f675f;
            text-decoration: none;
            white-space: nowrap;
            cursor: pointer;
            transition: .18s ease;
        }

        .mo-tab:hover {
            color: #b51f2c;
            background: #fff;
        }

        .mo-tab.active {
            background: #fff;
            color: #b51f2c;
            box-shadow: 0 4px 14px rgba(48, 41, 35, .08);
        }

        .mo-tab .count {
            padding: 2px 8px;
            border-radius: 999px;
            background: #f5efe5;
            color: #948c82;
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: .04em;
        }

        .mo-tab.active .count {
            background: #b51f2c;
            color: #fff;
        }

        /* ---------- ORDER CARDS ---------- */
        .mo-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .mo-order {
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 18px;
            overflow: hidden;
            transition: .22s ease;
        }

        .mo-order:hover {
            border-color: #d98a91;
            box-shadow: 0 14px 30px rgba(48, 41, 35, .08);
            transform: translateY(-2px);
        }

        .mo-order-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 20px;
            background: #fdfaf4;
            border-bottom: 1.5px solid #f0ebe4;
            flex-wrap: wrap;
        }

        .mo-order-head-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .mo-order-code {
            font-family: "DM Sans", sans-serif;
            font-size: 13.5px;
            font-weight: 800;
            color: #302923;
            letter-spacing: .04em;
        }

        .mo-order-date {
            font-size: 11.5px;
            color: #948c82;
            font-weight: 600;
        }

        .mo-order-head-right {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .mo-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 11px;
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        .mo-status.pending {
            background: #fff7e7;
            color: #8a6a1e;
        }

        .mo-status.confirmed {
            background: #eef4fd;
            color: #1565c0;
        }

        .mo-status.delivered {
            background: #e8f6ea;
            color: #1b5e20;
        }

        .mo-status.cancelled {
            background: #fdecec;
            color: #b51f2c;
        }

        .mo-pay {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        .mo-pay.paid    { background: #e8f6ea; color: #1b5e20; }
        .mo-pay.unpaid  { background: #fff7e7; color: #a35a0e; }
        .mo-pay.failed  { background: #fdecec; color: #b51f2c; }

        /* ---------- ORDER BODY ---------- */
        .mo-order-body {
            padding: 18px 20px;
        }

        .mo-items {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 16px;
        }

        .mo-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border: 1px solid #f0ebe4;
            border-radius: 11px;
            background: #fffdf9;
        }

        .mo-item-thumb {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: #f7efe3;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
            color: #b0a79c;
            font-size: 18px;
        }

        .mo-item-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .mo-item-info {
            flex: 1;
            min-width: 0;
        }

        .mo-item-name {
            font-size: 12.5px;
            font-weight: 700;
            color: #302923;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mo-item-meta {
            font-size: 10.5px;
            color: #948c82;
            margin: 3px 0 0;
            font-weight: 600;
        }

        .mo-item-price {
            font-family: "Playfair Display", serif;
            font-size: 14px;
            font-weight: 800;
            color: #b51f2c;
            white-space: nowrap;
        }

        /* ---------- ORDER FOOTER ---------- */
        .mo-order-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 14px 20px;
            background: #fdfaf4;
            border-top: 1.5px solid #f0ebe4;
            flex-wrap: wrap;
        }

        .mo-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 11.5px;
            color: #6f675f;
            font-weight: 600;
        }

        .mo-meta span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .mo-meta i {
            color: #948c82;
            font-size: 13px;
        }

        .mo-total-line {
            display: flex;
            align-items: baseline;
            gap: 8px;
            margin-left: auto;
        }

        .mo-total-label {
            font-size: 11.5px;
            color: #948c82;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .mo-total-value {
            font-family: "Playfair Display", serif;
            font-size: 22px;
            font-weight: 800;
            color: #302923;
        }

        .mo-tax-note {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 9px;
            border-radius: 6px;
            background: #fdf1e2;
            color: #b8893c;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .03em;
            margin-left: 6px;
        }

        /* ---------- EMPTY STATE ---------- */
        .mo-empty {
            padding: 80px 24px;
            text-align: center;
            background: #fff;
            border: 1.5px dashed #ece5da;
            border-radius: 20px;
        }

        .mo-empty-icon {
            width: 82px;
            height: 82px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: #fdf1e2;
            color: #b8893c;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
        }

        .mo-empty h3 {
            font-family: "Playfair Display", serif;
            font-size: 22px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 8px;
        }

        .mo-empty p {
            margin: 0 0 22px;
            font-size: 13px;
            color: #948c82;
            line-height: 1.6;
        }

        /* ---------- PAGINATION ---------- */
        .mo-pagination {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-top: 26px;
            flex-wrap: wrap;
        }

        .mo-page-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 38px;
            height: 38px;
            padding: 0 12px;
            border-radius: 10px;
            background: #fff;
            border: 1.5px solid #e4ddd3;
            color: #6f675f;
            font-family: "DM Sans", sans-serif;
            font-size: 12px;
            font-weight: 800;
            text-decoration: none;
            transition: .2s ease;
        }

        .mo-page-btn:hover {
            background: #faf7f0;
            color: #302923;
        }

        .mo-page-btn.active {
            background: #b51f2c;
            border-color: #b51f2c;
            color: #fff;
        }

        .mo-page-btn.disabled {
            opacity: .5;
            pointer-events: none;
        }

        /* ---------- RESPONSIVE ---------- */
        @media (max-width: 640px) {
            .mo-head h1 { font-size: 24px; }
            .mo-order-head { padding: 14px 16px; }
            .mo-order-body { padding: 14px 16px; }
            .mo-order-foot { padding: 12px 16px; }
            .mo-total-line { margin-left: 0; width: 100%; justify-content: space-between; }
            .mo-meta { gap: 12px; }
        }
    </style>
</head>

<body>

    <?php include_once './includes/nav-bar.php'; ?>

    <main class="mm-main">
        <div class="mm-container">

            <div class="mo-page">

                <div class="mo-head">
                    <div>
                        <h1>My Orders</h1>
                        <p>Track and review all your orders from <?= htmlspecialchars($siteName) ?>.</p>
                    </div>

                    <div class="mo-head-actions">
                        <a href="<?= MAIN_URL ?>" class="mo-btn-ghost">
                            <i class="bi bi-arrow-left"></i>
                            Continue Shopping
                        </a>
                    </div>
                </div>

                <!-- FILTER TABS -->
                <div class="mo-tabs">
                    <a href="?filter=all" class="mo-tab <?= $filter === 'all' ? 'active' : '' ?>">
                        <i class="bi bi-grid-3x3-gap-fill"></i>
                        All
                        <span class="count"><?= (int)$filterCounts['all'] ?></span>
                    </a>
                    <a href="?filter=pending" class="mo-tab <?= $filter === 'pending' ? 'active' : '' ?>">
                        <i class="bi bi-clock-fill"></i>
                        Pending
                        <span class="count"><?= (int)$filterCounts['pending'] ?></span>
                    </a>
                    <a href="?filter=confirmed" class="mo-tab <?= $filter === 'confirmed' ? 'active' : '' ?>">
                        <i class="bi bi-check-circle-fill"></i>
                        Confirmed
                        <span class="count"><?= (int)$filterCounts['confirmed'] ?></span>
                    </a>
                    <a href="?filter=delivered" class="mo-tab <?= $filter === 'delivered' ? 'active' : '' ?>">
                        <i class="bi bi-box-seam-fill"></i>
                        Delivered
                        <span class="count"><?= (int)$filterCounts['delivered'] ?></span>
                    </a>
                    <a href="?filter=cancelled" class="mo-tab <?= $filter === 'cancelled' ? 'active' : '' ?>">
                        <i class="bi bi-x-circle-fill"></i>
                        Cancelled
                        <span class="count"><?= (int)$filterCounts['cancelled'] ?></span>
                    </a>
                </div>


                <!-- ORDERS LIST -->
                <?php if (empty($orders)): ?>

                    <div class="mo-empty">
                        <div class="mo-empty-icon">
                            <i class="bi bi-bag-x"></i>
                        </div>
                        <h3>No orders yet</h3>
                        <p>
                            You haven't placed any orders <?= $filter !== 'all' ? 'in this category' : '' ?>.<br>
                            Start ordering your favourite fresh products today!
                        </p>
                        <a href="<?= MAIN_URL ?>" class="mo-btn-primary">
                            <i class="bi bi-bag-plus"></i>
                            Start Shopping
                        </a>
                    </div>

                <?php else: ?>

                    <div class="mo-list">

                        <?php foreach ($orders as $o):
                            $status = statusMeta($o['status'], $o['payment_status']);
                            $pay    = paymentMeta($o['payment_status']);
                            $items  = json_decode($o['products_json'] ?? '[]', true);
                            if (!is_array($items)) $items = [];
                        ?>
                            <div class="mo-order">

                                <!-- HEAD -->
                                <div class="mo-order-head">
                                    <div class="mo-order-head-left">
                                        <span class="mo-order-code">
                                            #<?= htmlspecialchars($o['order_code']) ?>
                                        </span>
                                        <span class="mo-order-date">
                                            <i class="bi bi-clock"></i>
                                            <?= htmlspecialchars(formatOrderDate($o['created_at'])) ?>
                                        </span>
                                    </div>

                                    <div class="mo-order-head-right">
                                        <span class="mo-status <?= $status['class'] ?>">
                                            <i class="bi <?= $status['icon'] ?>"></i>
                                            <?= $status['label'] ?>
                                        </span>
                                        <span class="mo-pay <?= $pay['class'] ?>">
                                            <i class="bi bi-<?= $pay['class'] === 'paid' ? 'check-circle-fill' : 'exclamation-circle-fill' ?>"></i>
                                            <?= $pay['label'] ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- BODY: items -->
                                <div class="mo-order-body">
                                    <div class="mo-items">
                                        <?php foreach (array_slice($items, 0, 3) as $it): ?>
                                            <div class="mo-item">
                                                <div class="mo-item-thumb">
                                                    <?php if (!empty($it['image'])): ?>
                                                        <img src="<?= htmlspecialchars($it['image']) ?>" alt=""
                                                            onerror="this.style.display='none';this.parentElement.innerHTML='<i class=\'bi bi-image\'></i>';">
                                                    <?php else: ?>
                                                        <i class="bi bi-image"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mo-item-info">
                                                    <p class="mo-item-name"><?= htmlspecialchars($it['name'] ?? 'Item') ?></p>
                                                    <p class="mo-item-meta">
                                                        <?= htmlspecialchars($it['variant_name'] ?? '') ?>
                                                        <?php if (!empty($it['variant_qty'])): ?>
                                                            · <?= htmlspecialchars($it['variant_qty']) ?>
                                                        <?php endif; ?>
                                                        · Qty <?= (int)($it['qty'] ?? 1) ?>
                                                        <?php if (!empty($it['container_enabled']) && (int)$it['container_enabled'] === 1): ?>
                                                            · <span style="color:#b8893c;font-weight:800;">+Container ₹<?= number_format((float)($it['container_price'] ?? 0), 0) ?></span>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                                <div class="mo-item-price">
                                                    ₹<?= number_format((float)($it['line_total'] ?? 0), 0) ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>

                                        <?php if (count($items) > 3): ?>
                                            <div style="text-align:center;font-size:11.5px;color:#948c82;font-weight:700;padding:4px 0;">
                                                +<?= count($items) - 3 ?> more item<?= (count($items) - 3) === 1 ? '' : 's' ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- FOOTER -->
                                <div class="mo-order-foot">

                                    <div class="mo-meta">
                                        <?php if (($o['delivery_mode'] ?? 'delivery') === 'pickup'): ?>
                                            <span>
                                                <i class="bi bi-shop"></i>
                                                Pickup<?= !empty($o['pickup_branch_name']) ? ' · ' . htmlspecialchars($o['pickup_branch_name']) : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <span>
                                                <i class="bi bi-geo-alt"></i>
                                                <?= htmlspecialchars($o['apartment_name'] ?: '—') ?>
                                                <?php if (!empty($o['division'])): ?>
                                                    · Div <?= htmlspecialchars($o['division']) ?>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mo-total-line">
                                        <span class="mo-total-label">Total</span>
                                        <span class="mo-total-value"><?= rupees($o['total_amount']) ?></span>

                                        <?php if ((float)($o['tax_amount'] ?? 0) > 0): ?>
                                            <span class="mo-tax-note">
                                                <i class="bi bi-receipt"></i>
                                                GST <?= rupees($o['tax_amount']) ?> (<?= (int)round((float)$o['tax_rate']) ?>%)
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                </div>

                            </div>
                        <?php endforeach; ?>

                    </div>

                    <!-- PAGINATION -->
                    <?php if ($totalPages > 1): ?>
                        <div class="mo-pagination">

                            <a href="?filter=<?= $filter ?>&page=<?= max(1, $page - 1) ?>"
                                class="mo-page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>

                            <?php
                            $start = max(1, $page - 2);
                            $end   = min($totalPages, $page + 2);

                            if ($start > 1) {
                                echo '<a href="?filter=' . $filter . '&page=1" class="mo-page-btn">1</a>';
                                if ($start > 2) echo '<span class="mo-page-btn disabled">…</span>';
                            }

                            for ($i = $start; $i <= $end; $i++) {
                                echo '<a href="?filter=' . $filter . '&page=' . $i . '" class="mo-page-btn ' . ($i === $page ? 'active' : '') . '">' . $i . '</a>';
                            }

                            if ($end < $totalPages) {
                                if ($end < $totalPages - 1) echo '<span class="mo-page-btn disabled">…</span>';
                                echo '<a href="?filter=' . $filter . '&page=' . $totalPages . '" class="mo-page-btn">' . $totalPages . '</a>';
                            }
                            ?>

                            <a href="?filter=<?= $filter ?>&page=<?= min($totalPages, $page + 1) ?>"
                                class="mo-page-btn <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>

                        </div>
                    <?php endif; ?>

                <?php endif; ?>

            </div>

        </div>
    </main>


    <?php include_once './includes/mobile-nav-bar.php'; ?>
    <?php include_once './includes/login-poup.php'; ?>
    <?php include_once './includes/register-poup.php'; ?>

    <div id="mmToast"></div>
    <?php include_once './includes/footer.php'; ?> 
    <script>
        window.MAIN_URL = "<?= MAIN_URL ?>";
        window.ADMIN_URL = "<?= ADMIN_URL ?>";
        window.IS_LOGGED_IN = <?= $customerLoggedIn ? 'true' : 'false' ?>;
    </script>

    <script src="<?= MAIN_URL ?>js/home.js"></script>

</body>

</html>