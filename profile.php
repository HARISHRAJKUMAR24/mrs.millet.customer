<?php
require_once './config/config.php';
require_once './config/function.php';

$settings = getSettings($pdo);

$siteName = $settings['username'] ?? 'Mrs Mill@';
$logoUrl  = !empty($settings['logo_image'])    ? ADMIN_URL . $settings['logo_image']    : '';
$favicon  = !empty($settings['favicon_image']) ? ADMIN_URL . $settings['favicon_image'] : '';

/* =========================================================
   AUTH
   ========================================================= */
$customerLoggedIn = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0;

if (!$customerLoggedIn) {
    header('Location: ' . MAIN_URL . 'login.php?redirect=profile.php');
    exit;
}

$customerId = (int)$_SESSION['customer_id'];

/* =========================================================
   LOAD CUSTOMER
   ========================================================= */
$customer = null;
try {
    $stmt = $pdo->prepare(
        "SELECT id, full_name, mobile_number,
                apartment_id, apartment_code, apartment_name,
                division, division_charge,
                COALESCE(wallet_balance, 0) AS wallet_balance,
                status, created_at
         FROM customers
         WHERE id = ? AND status = 1
         LIMIT 1"
    );
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $customer = null;
}

if (!$customer) {
    session_destroy();
    header('Location: ' . MAIN_URL . 'login.php');
    exit;
}

$customerName    = $customer['full_name'];
$customerMobile  = $customer['mobile_number'];
$customerWallet  = (float)$customer['wallet_balance'];
$customerAptId   = (int)($customer['apartment_id'] ?? 0);
$customerAptCode = $customer['apartment_code'] ?? '';
$customerAptName = $customer['apartment_name'] ?? '';
$customerDivision = $customer['division'] ?? '';
$customerDivCharge = (float)($customer['division_charge'] ?? 0);
$customerJoined  = $customer['created_at'];

/* =========================================================
   KPI
   ========================================================= */
$kpi = [
    'orders'     => 0,
    'spent'      => 0,
    'containers_held'     => 0,
    'containers_returned' => 0,
    'containers_total'    => 0,
];

try {
    $row = $pdo->prepare(
        "SELECT COUNT(*) AS cnt,
                COALESCE(SUM(total_amount),0) AS spent
         FROM orders
         WHERE customer_mobile = ? AND status <> 'cancelled'"
    );
    $row->execute([$customerMobile]);
    $r = $row->fetch(PDO::FETCH_ASSOC);
    $kpi['orders'] = (int)($r['cnt'] ?? 0);
    $kpi['spent']  = (float)($r['spent'] ?? 0);
} catch (PDOException $e) {}

try {
    $row = $pdo->prepare(
        "SELECT COALESCE(SUM(total_containers),0) AS total_c,
                COALESCE(SUM(received_containers),0) AS recv_c
         FROM order_containers
         WHERE customer_id = ?"
    );
    $row->execute([$customerId]);
    $r = $row->fetch(PDO::FETCH_ASSOC);
    $kpi['containers_total']    = (int)($r['total_c'] ?? 0);
    $kpi['containers_returned'] = (int)($r['recv_c'] ?? 0);
    $kpi['containers_held']     = max(0, $kpi['containers_total'] - $kpi['containers_returned']);
} catch (PDOException $e) {}

/* =========================================================
   PAGINATION
   ========================================================= */
$perPage = 10;

$walletPage    = max(1, (int)($_GET['wpage'] ?? 1));
$walletOffset  = ($walletPage - 1) * $perPage;

$containerPage   = max(1, (int)($_GET['cpage'] ?? 1));
$containerOffset = ($containerPage - 1) * $perPage;

/* =========================================================
   WALLET
   ========================================================= */
$walletTxns       = [];
$walletTotal      = 0;
$walletTotalPages = 1;

try {
    $cStmt = $pdo->prepare("SELECT COUNT(*) FROM customer_wallet_transactions WHERE customer_id = ?");
    $cStmt->execute([$customerId]);
    $walletTotal = (int)$cStmt->fetchColumn();
    $walletTotalPages = max(1, (int)ceil($walletTotal / $perPage));

    if ($walletPage > $walletTotalPages) $walletPage = $walletTotalPages;
    $walletOffset = ($walletPage - 1) * $perPage;

    $stmt = $pdo->prepare(
        "SELECT txn_code, txn_type, amount, balance_before, balance_after,
                source, note, created_at
         FROM customer_wallet_transactions
         WHERE customer_id = ?
         ORDER BY id DESC
         LIMIT $perPage OFFSET $walletOffset"
    );
    $stmt->execute([$customerId]);
    $walletTxns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

/* =========================================================
   CONTAINERS
   ========================================================= */
$containers          = [];
$containerTotal      = 0;
$containerTotalPages = 1;

try {
    $cStmt = $pdo->prepare("SELECT COUNT(*) FROM order_containers WHERE customer_id = ?");
    $cStmt->execute([$customerId]);
    $containerTotal = (int)$cStmt->fetchColumn();
    $containerTotalPages = max(1, (int)ceil($containerTotal / $perPage));

    if ($containerPage > $containerTotalPages) $containerPage = $containerTotalPages;
    $containerOffset = ($containerPage - 1) * $perPage;

    $stmt = $pdo->prepare(
        "SELECT oc.order_id, oc.total_containers, oc.received_containers,
                oc.container_amount, oc.status, oc.received_at, oc.created_at,
                o.order_code, o.total_amount
         FROM order_containers oc
         LEFT JOIN orders o ON o.id = oc.order_id
         WHERE oc.customer_id = ?
         ORDER BY oc.id DESC
         LIMIT $perPage OFFSET $containerOffset"
    );
    $stmt->execute([$customerId]);
    $containers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

/* =========================================================
   RECENT ORDERS (with delivery boy)
   ========================================================= */
$recentOrders = [];
try {
    $stmt = $pdo->prepare(
        "SELECT o.order_code,
                o.total_amount,
                o.status,
                o.payment_status,
                o.delivery_status,
                o.delivery_mode,
                o.created_at,
                o.delivery_boy_id,
                b.full_name     AS delivery_boy_name,
                b.delivery_code AS delivery_boy_code
         FROM orders o
         LEFT JOIN delivery_boys b ON b.id = o.delivery_boy_id
         WHERE o.customer_mobile = ?
         ORDER BY o.id DESC
         LIMIT 10"
    );
    $stmt->execute([$customerMobile]);
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

/* =========================================================
   APARTMENTS
   ========================================================= */
$apartments = [];
try {
    $apartments = $pdo->query(
        "SELECT id, apartment_code, apartment_name, divisions
         FROM apartments WHERE status = 1
         ORDER BY apartment_name ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

/* =========================================================
   HELPERS
   ========================================================= */
function rupees($n) {
    return '₹' . number_format((int)round($n));
}

function shortDate($ts) {
    $t = strtotime($ts);
    if ($t === false) return '';
    return date('d M Y', $t);
}

function shortTime($ts) {
    $t = strtotime($ts);
    if ($t === false) return '';
    return date('g:i A', $t);
}

function initials($name) {
    $name = trim((string)$name);
    if ($name === '') return '?';
    $parts = preg_split('/\s+/', $name);
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

function renderPager($baseUrl, $pageParam, $current, $totalPages) {
    if ($totalPages <= 1) return '';

    $sep = (strpos($baseUrl, '?') === false) ? '?' : '&';
    $html = '<div class="pf-pager">';

    $prevPage = max(1, $current - 1);
    $prevCls  = ($current <= 1) ? 'disabled' : '';
    $html .= '<a href="' . htmlspecialchars($baseUrl . $sep . $pageParam . '=' . $prevPage) . '" class="pf-page-btn ' . $prevCls . '"><i class="bi bi-chevron-left"></i></a>';

    $start = max(1, $current - 2);
    $end   = min($totalPages, $current + 2);

    if ($start > 1) {
        $html .= '<a href="' . htmlspecialchars($baseUrl . $sep . $pageParam . '=1') . '" class="pf-page-btn">1</a>';
        if ($start > 2) $html .= '<span class="pf-page-btn disabled">…</span>';
    }

    for ($i = $start; $i <= $end; $i++) {
        $cls = ($i === $current) ? 'active' : '';
        $html .= '<a href="' . htmlspecialchars($baseUrl . $sep . $pageParam . '=' . $i) . '" class="pf-page-btn ' . $cls . '">' . $i . '</a>';
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) $html .= '<span class="pf-page-btn disabled">…</span>';
        $html .= '<a href="' . htmlspecialchars($baseUrl . $sep . $pageParam . '=' . $totalPages) . '" class="pf-page-btn">' . $totalPages . '</a>';
    }

    $nextPage = min($totalPages, $current + 1);
    $nextCls  = ($current >= $totalPages) ? 'disabled' : '';
    $html .= '<a href="' . htmlspecialchars($baseUrl . $sep . $pageParam . '=' . $nextPage) . '" class="pf-page-btn ' . $nextCls . '"><i class="bi bi-chevron-right"></i></a>';

    $html .= '</div>';
    return $html;
}

$initials = initials($customerName);

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
        .pf-page { padding: 26px 0 80px; }

        /* ---------- HEADER CARD ---------- */
        .pf-header {
            position: relative;
            background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%);
            border-radius: 22px;
            padding: 28px 30px;
            color: #fff;
            overflow: hidden;
            margin-bottom: 22px;
            box-shadow: 0 18px 40px rgba(181, 31, 44, .22);
        }

        .pf-header::before {
            content: "";
            position: absolute;
            right: -60px;
            top: -60px;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .07);
        }

        .pf-header::after {
            content: "";
            position: absolute;
            right: 40px;
            bottom: -80px;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .05);
        }

        .pf-header-inner {
            position: relative;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            z-index: 2;
        }

        .pf-avatar {
            width: 82px;
            height: 82px;
            border-radius: 22px;
            background: #fff;
            color: #b51f2c;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: "Playfair Display", serif;
            font-size: 30px;
            font-weight: 800;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .18);
            flex-shrink: 0;
        }

        .pf-header-info { flex: 1; min-width: 200px; }

        .pf-header-info h1 {
            font-family: "Playfair Display", serif;
            font-size: 26px;
            font-weight: 700;
            margin: 0 0 6px;
            letter-spacing: -.3px;
        }

        .pf-header-info p {
            margin: 0 0 8px;
            font-size: 12.5px;
            opacity: .88;
        }

        .pf-header-meta {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .pf-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .15);
            border: 1px solid rgba(255, 255, 255, .22);
            font-size: 11px;
            font-weight: 700;
            backdrop-filter: blur(4px);
        }

        /* ---------- KPI STRIP ---------- */
        .pf-kpis {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 22px;
        }

        @media (max-width: 900px) { .pf-kpis { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 480px) { .pf-kpis { grid-template-columns: 1fr; } }

        .pf-kpi {
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 16px;
            padding: 16px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .pf-kpi-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .pf-kpi-icon.green  { background: #e8f6ea; color: #1b5e20; }
        .pf-kpi-icon.gold   { background: #fdf1e2; color: #b8893c; }
        .pf-kpi-icon.red    { background: #fdeaea; color: #b51f2c; }
        .pf-kpi-icon.blue   { background: #e5eefb; color: #1565c0; }

        .pf-kpi-label {
            font-size: 10.5px;
            font-weight: 800;
            color: #948c82;
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-bottom: 3px;
        }

        .pf-kpi-value {
            font-family: "DM Sans", sans-serif;
            font-size: 20px;
            font-weight: 800;
            color: #302923;
            line-height: 1.1;
        }

        .pf-kpi-value.green { color: #1b5e20; }
        .pf-kpi-value.gold  { color: #b8893c; }
        .pf-kpi-value.red   { color: #b51f2c; }

        /* ---------- TABS ---------- */
        .pf-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            padding: 5px;
            background: #fdfaf4;
            border: 1.5px solid #ece5da;
            border-radius: 14px;
            margin-bottom: 22px;
        }

        .pf-tab {
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
            cursor: pointer;
            white-space: nowrap;
            transition: color .18s ease, background .18s ease, box-shadow .18s ease;
            outline: none;
        }

        .pf-tab:hover { color: #b51f2c; background: #fff; }

        .pf-tab.active {
            background: #fff;
            color: #b51f2c;
            box-shadow: 0 4px 14px rgba(48, 41, 35, .08);
        }

        @media (max-width: 640px) {
            .pf-tab { padding: 9px 12px; font-size: 11.5px; }
        }

        .pf-panel { display: none; }
        .pf-panel.active { display: block; }

        /* ---------- CARD ---------- */
        .pf-card {
            background: #fff;
            border: 1.5px solid #ece5da;
            border-radius: 18px;
            padding: 22px 24px;
            margin-bottom: 20px;
        }

        .pf-card-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: "Playfair Display", serif;
            font-size: 17px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 18px;
        }

        .pf-card-title i {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            background: #fbe8e9;
            color: #b51f2c;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        /* ---------- FORM ---------- */
        .pf-field { margin-bottom: 16px; }

        .pf-field label {
            display: block;
            font-size: 11px;
            font-weight: 800;
            color: #4e4841;
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: 7px;
        }

        .pf-input {
            width: 100%;
            height: 46px;
            border: 1.5px solid #ece5da;
            background: #fffdf9;
            border-radius: 12px;
            padding: 0 14px;
            font-family: "DM Sans", sans-serif;
            font-size: 13px;
            font-weight: 600;
            color: #302923;
            outline: none;
            transition: border-color .2s ease, box-shadow .2s ease;
        }

        .pf-input:focus {
            border-color: #b51f2c;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(181, 31, 44, .08);
        }

        .pf-input:disabled {
            background: #f7f2ec;
            cursor: not-allowed;
        }

        /* ---------- PASSWORD EYE ---------- */
        .pf-pass-wrap { position: relative; }

        .pf-pass-wrap .pf-input { padding-right: 48px; }

        .pf-eye-btn {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            border: none;
            background: transparent;
            border-radius: 8px;
            color: #948c82;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            cursor: pointer;
            transition: color .15s ease, background .15s ease;
        }

        .pf-eye-btn:hover {
            color: #b51f2c;
            background: #fbe8e9;
        }

        .pf-eye-btn.active { color: #b51f2c; }

        .pf-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            height: 46px;
            padding: 0 22px;
            border-radius: 12px;
            border: none;
            font-family: "DM Sans", sans-serif;
            font-size: 12.5px;
            font-weight: 800;
            cursor: pointer;
            transition: .2s ease;
        }

        .pf-btn-primary {
            background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%);
            color: #fff;
            box-shadow: 0 10px 24px rgba(181, 31, 44, .22);
        }

        .pf-btn-primary:hover { transform: translateY(-1px); }

        .pf-btn-ghost {
            background: #fff;
            border: 1.5px solid #e4ddd3;
            color: #6f675f;
        }

        .pf-btn-ghost:hover { background: #faf7f0; color: #302923; }

        .pf-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        @media (max-width: 640px) { .pf-grid-2 { grid-template-columns: 1fr; } }

        /* ---------- TABLE ---------- */
        .pf-table-wrap {
            overflow-x: auto;
            border: 1px solid #f0ebe4;
            border-radius: 12px;
        }

        .pf-table {
            width: 100%;
            border-collapse: collapse;
            font-family: "DM Sans", sans-serif;
            min-width: 760px;
        }

        .pf-table thead th {
            background: #fdfaf4;
            padding: 12px 14px;
            text-align: left;
            font-size: 10.5px;
            font-weight: 800;
            color: #6f675f;
            text-transform: uppercase;
            letter-spacing: .06em;
            border-bottom: 1.5px solid #f0ebe4;
            white-space: nowrap;
        }

        .pf-table tbody td {
            padding: 12px 14px;
            font-size: 12.5px;
            color: #302923;
            border-bottom: 1px solid #f7f2ec;
            vertical-align: middle;
        }

        .pf-table tbody tr:last-child td { border-bottom: 0; }
        .pf-table tbody tr:hover { background: #fdfaf4; }

        .pf-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .pf-badge.credit  { background: #e8f6ea; color: #1b5e20; }
        .pf-badge.debit   { background: #fdecec; color: #b51f2c; }
        .pf-badge.pending { background: #fff7e7; color: #8a6a1e; }
        .pf-badge.confirmed { background: #eef4fd; color: #1565c0; }
        .pf-badge.delivered { background: #e8f6ea; color: #1b5e20; }
        .pf-badge.cancelled { background: #fdecec; color: #b51f2c; }
        .pf-badge.partial { background: #fff7e7; color: #8a6a1e; }
        .pf-badge.received { background: #e8f6ea; color: #1b5e20; }
        .pf-badge.not_received { background: #fdecec; color: #b51f2c; }

        .pf-txn-code {
            font-family: "DM Sans", monospace;
            font-size: 11px;
            font-weight: 700;
            color: #6f5a3f;
            background: #faf7f0;
            padding: 3px 8px;
            border-radius: 6px;
            letter-spacing: .04em;
        }

        .pf-amount {
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }

        .pf-amount.credit { color: #1b5e20; }
        .pf-amount.debit  { color: #b51f2c; }

        .pf-note {
            font-size: 11px;
            color: #948c82;
            font-weight: 600;
            max-width: 260px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: inline-block;
            vertical-align: middle;
        }

        /* ---------- DELIVERY BOY CELL ---------- */
        .pf-dboy {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }

        .pf-dboy-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 800;
            flex-shrink: 0;
        }

        .pf-dboy-info { min-width: 0; }

        .pf-dboy-name {
            font-size: 12px;
            font-weight: 700;
            color: #302923;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 140px;
        }

        .pf-dboy-code {
            font-size: 9.5px;
            color: #948c82;
            font-weight: 600;
            margin-top: 1px;
        }

        .pf-dboy-empty,
        .pf-dboy-pickup {
            font-size: 11.5px;
            color: #948c82;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .pf-dboy-pickup { color: #a35a0e; }

        /* ---------- EMPTY ---------- */
        .pf-empty {
            padding: 40px 20px;
            text-align: center;
            color: #948c82;
            font-size: 12.5px;
        }

        .pf-empty i {
            font-size: 34px;
            color: #ece5da;
            display: block;
            margin-bottom: 10px;
        }

        /* ---------- WALLET SUMMARY ---------- */
        .pf-wallet-summary {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 20px;
        }

        @media (max-width: 560px) { .pf-wallet-summary { grid-template-columns: 1fr; } }

        .pf-wallet-tile {
            padding: 16px 18px;
            border-radius: 14px;
            background: linear-gradient(135deg, #f6fbf7 0%, #eef8f0 100%);
            border: 1.5px solid #c8e6c9;
        }

        .pf-wallet-tile.gold {
            background: linear-gradient(135deg, #fffaf0 0%, #fff5e3 100%);
            border-color: #f3dca5;
        }

        .pf-wallet-tile-label {
            font-size: 10.5px;
            font-weight: 800;
            color: #948c82;
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-bottom: 6px;
        }

        .pf-wallet-tile-value {
            font-family: "DM Sans", sans-serif;
            font-size: 24px;
            font-weight: 800;
            color: #1b5e20;
            line-height: 1.1;
        }

        .pf-wallet-tile.gold .pf-wallet-tile-value { color: #b8893c; }

        /* ---------- PAGER ---------- */
        .pf-pager {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .pf-page-btn {
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

        .pf-page-btn:hover {
            background: #faf7f0;
            color: #302923;
            border-color: #d8c9b8;
        }

        .pf-page-btn.active {
            background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%);
            border-color: #b51f2c;
            color: #fff;
            box-shadow: 0 8px 20px rgba(181, 31, 44, .22);
        }

        .pf-page-btn.disabled {
            opacity: .45;
            pointer-events: none;
        }

        .pf-page-info {
            text-align: center;
            font-family: "DM Sans", sans-serif;
            font-size: 11.5px;
            color: #948c82;
            font-weight: 700;
            margin-top: 8px;
            letter-spacing: .03em;
        }

        /* =====================================================
           TOAST — top-right, slides in from right
           ===================================================== */
        #mmToast {
            position: fixed;
            top: 24px;
            right: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 13px 18px;
            border-radius: 12px;
            background: #fff;
            color: #302923;
            border: 1.5px solid #ece5da;
            border-left: 4px solid #1b5e20;
            font-family: "DM Sans", sans-serif;
            font-size: 12.5px;
            font-weight: 700;
            box-shadow: 0 18px 40px rgba(48, 41, 35, .14);
            opacity: 0;
            transform: translateX(30px);
            pointer-events: none;
            transition: opacity .28s ease, transform .28s cubic-bezier(.2, .9, .3, 1.1);
            z-index: 9999;
            max-width: 340px;
            line-height: 1.4;
        }

        #mmToast::before {
            content: "✓";
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #e8f6ea;
            color: #1b5e20;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 900;
            flex-shrink: 0;
        }

        #mmToast.show {
            opacity: 1;
            transform: translateX(0);
        }

        #mmToast.error {
            border-left-color: #b51f2c;
        }

        #mmToast.error::before {
            content: "!";
            background: #fdecec;
            color: #b51f2c;
        }

        @media (max-width: 640px) {
            #mmToast {
                top: 16px;
                left: 16px;
                right: 16px;
                max-width: none;
            }
        }
    </style>
</head>

<body>

    <?php include_once './includes/nav-bar.php'; ?>

    <main class="mm-main">
        <div class="mm-container">

            <div class="pf-page">

                <!-- HEADER -->
                <div class="pf-header">
                    <div class="pf-header-inner">
                        <div class="pf-avatar"><?= htmlspecialchars($initials) ?></div>
                        <div class="pf-header-info">
                            <h1><?= htmlspecialchars($customerName) ?></h1>
                            <p><?= htmlspecialchars($customerMobile) ?></p>
                            <div class="pf-header-meta">
                                <?php if ($customerAptName): ?>
                                    <span class="pf-chip">
                                        <i class="bi bi-building"></i>
                                        <?= htmlspecialchars($customerAptName) ?>
                                        <?php if ($customerDivision): ?>
                                            · Div <?= htmlspecialchars($customerDivision) ?>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                                <span class="pf-chip">
                                    <i class="bi bi-calendar3"></i>
                                    Joined <?= htmlspecialchars(shortDate($customerJoined)) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- KPI STRIP -->
                <div class="pf-kpis">
                    <div class="pf-kpi">
                        <div class="pf-kpi-icon blue"><i class="bi bi-bag-check"></i></div>
                        <div>
                            <div class="pf-kpi-label">Total Orders</div>
                            <div class="pf-kpi-value"><?= (int)$kpi['orders'] ?></div>
                        </div>
                    </div>

                    <div class="pf-kpi">
                        <div class="pf-kpi-icon green"><i class="bi bi-wallet2"></i></div>
                        <div>
                            <div class="pf-kpi-label">Wallet Balance</div>
                            <div class="pf-kpi-value green"><?= rupees($customerWallet) ?></div>
                        </div>
                    </div>

                    <div class="pf-kpi">
                        <div class="pf-kpi-icon red"><i class="bi bi-cash-coin"></i></div>
                        <div>
                            <div class="pf-kpi-label">Total Spent</div>
                            <div class="pf-kpi-value red"><?= rupees($kpi['spent']) ?></div>
                        </div>
                    </div>

                    <div class="pf-kpi">
                        <div class="pf-kpi-icon gold"><i class="bi bi-box2-heart"></i></div>
                        <div>
                            <div class="pf-kpi-label">Containers Held</div>
                            <div class="pf-kpi-value gold"><?= (int)$kpi['containers_held'] ?></div>
                        </div>
                    </div>
                </div>


                <!-- TABS -->
                <div class="pf-tabs">
                    <button type="button" class="pf-tab active" data-tab="overview">
                        <i class="bi bi-person-circle"></i> Overview
                    </button>
                    <button type="button" class="pf-tab" data-tab="orders">
                        <i class="bi bi-bag-check"></i> Orders
                    </button>
                    <button type="button" class="pf-tab" data-tab="wallet">
                        <i class="bi bi-wallet2"></i> Wallet History
                    </button>
                    <button type="button" class="pf-tab" data-tab="containers">
                        <i class="bi bi-box2-heart"></i> Containers
                    </button>
                    <button type="button" class="pf-tab" data-tab="security">
                        <i class="bi bi-shield-lock"></i> Security
                    </button>
                </div>


                <!-- ===================================================== -->
                <!-- OVERVIEW                                              -->
                <!-- ===================================================== -->
                <div class="pf-panel active" id="panel-overview">

                    <div class="pf-card">
                        <h2 class="pf-card-title">
                            <i class="bi bi-person-gear"></i>
                            Profile Details
                        </h2>

                        <form id="profileForm">
                            <div class="pf-grid-2">
                                <div class="pf-field">
                                    <label>Full Name</label>
                                    <input type="text" id="pfName" class="pf-input"
                                        value="<?= htmlspecialchars($customerName) ?>"
                                        maxlength="150" required>
                                </div>

                                <div class="pf-field">
                                    <label>Mobile Number</label>
                                    <input type="tel" class="pf-input"
                                        value="<?= htmlspecialchars($customerMobile) ?>"
                                        disabled>
                                </div>
                            </div>

                            <div class="pf-grid-2">
                                <div class="pf-field" style="position:relative;">
                                    <label>Apartment</label>

                                    <div style="position:relative;">
                                        <i class="bi bi-search" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#b0a79c;font-size:15px;pointer-events:none;"></i>
                                        <input type="text" id="pfAptSearch" class="pf-input"
                                            style="padding-left:40px;padding-right:40px;"
                                            placeholder="Search apartment by name or code..."
                                            value="<?= htmlspecialchars($customerAptName) ?>"
                                            autocomplete="off">
                                        <i class="bi bi-chevron-down" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:#b0a79c;font-size:12px;pointer-events:none;"></i>
                                    </div>

                                    <input type="hidden" id="pfAptId" value="<?= (int)$customerAptId ?>">
                                    <input type="hidden" id="pfAptCode" value="<?= htmlspecialchars($customerAptCode) ?>">

                                    <div id="pfAptResults"
                                        style="position:absolute;top:calc(100% + 6px);left:0;right:0;background:#fff;border:1.5px solid #ece5da;border-radius:14px;box-shadow:0 20px 40px rgba(48,41,35,.14);z-index:30;max-height:280px;overflow-y:auto;display:none;">
                                    </div>
                                </div>

                                <div class="pf-field" id="pfDivField" style="<?= $customerAptId > 0 ? '' : 'display:none;' ?>">
                                    <label>Division</label>
                                    <select id="pfDivision" class="pf-input">
                                        <option value="">— Select division —</option>
                                        <?php
                                        if ($customerAptId > 0) {
                                            foreach ($apartments as $a) {
                                                if ((int)$a['id'] === (int)$customerAptId) {
                                                    $divs = json_decode($a['divisions'] ?? '[]', true);
                                                    if (is_array($divs)) {
                                                        foreach ($divs as $d) {
                                                            $dv = (string)($d['division'] ?? '');
                                                            $dc = (float)($d['charge'] ?? 0);
                                                            $sel = ($dv === (string)$customerDivision) ? 'selected' : '';
                                                            echo '<option value="' . htmlspecialchars($dv) . '" data-charge="' . $dc . '" ' . $sel . '>'
                                                                 . 'Division ' . htmlspecialchars($dv) . ' · ₹' . (int)round($dc)
                                                                 . '</option>';
                                                        }
                                                    }
                                                    break;
                                                }
                                            }
                                        }
                                        ?>
                                    </select>
                                    <input type="hidden" id="pfDivisionCharge" value="<?= (float)$customerDivCharge ?>">
                                </div>
                            </div>

                            <div style="display:flex;gap:10px;margin-top:6px;flex-wrap:wrap;">
                                <button type="submit" class="pf-btn pf-btn-primary">
                                    <i class="bi bi-check-lg"></i> Save Changes
                                </button>
                                <button type="button" class="pf-btn pf-btn-ghost" id="pfResetBtn">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </button>
                            </div>
                        </form>
                    </div>

                </div>


                <!-- ===================================================== -->
                <!-- ORDERS                                                -->
                <!-- ===================================================== -->
                <div class="pf-panel" id="panel-orders">

                    <div class="pf-card">
                        <h2 class="pf-card-title">
                            <i class="bi bi-bag-check"></i>
                            Recent Orders
                        </h2>

                        <?php if (empty($recentOrders)): ?>
                            <div class="pf-empty">
                                <i class="bi bi-bag-x"></i>
                                You haven't placed any orders yet.
                            </div>
                        <?php else: ?>
                            <div class="pf-table-wrap">
                                <table class="pf-table">
                                    <thead>
                                        <tr>
                                            <th>Order</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Payment</th>
                                            <th>Delivered By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentOrders as $o):
                                            $s       = strtolower($o['status']);
                                            $p       = strtolower($o['payment_status']);
                                            $mode    = strtolower($o['delivery_mode'] ?? 'delivery');
                                            $boyName = trim($o['delivery_boy_name'] ?? '');
                                            $boyCode = trim($o['delivery_boy_code'] ?? '');
                                        ?>
                                            <tr>
                                                <td><strong>#<?= htmlspecialchars($o['order_code']) ?></strong></td>
                                                <td><?= htmlspecialchars(shortDate($o['created_at'])) ?></td>
                                                <td class="pf-amount"><?= rupees($o['total_amount']) ?></td>
                                                <td>
                                                    <span class="pf-badge <?= htmlspecialchars($s) ?>">
                                                        <?= htmlspecialchars(ucfirst($s)) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="pf-badge <?= $p === 'paid' ? 'received' : 'pending' ?>">
                                                        <?= htmlspecialchars(ucfirst($p)) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($mode === 'pickup'): ?>
                                                        <span class="pf-dboy-pickup">
                                                            <i class="bi bi-shop"></i> Store Pickup
                                                        </span>
                                                    <?php elseif ($boyName !== ''): ?>
                                                        <div class="pf-dboy">
                                                            <div class="pf-dboy-avatar">
                                                                <?= htmlspecialchars(strtoupper(substr($boyName, 0, 1))) ?>
                                                            </div>
                                                            <div class="pf-dboy-info">
                                                                <div class="pf-dboy-name"><?= htmlspecialchars($boyName) ?></div>
                                                                <?php if ($boyCode !== ''): ?>
                                                                    <div class="pf-dboy-code">#<?= htmlspecialchars($boyCode) ?></div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="pf-dboy-empty">
                                                            <i class="bi bi-dash-circle"></i> Not assigned
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div style="margin-top:14px;text-align:right;">
                                <a href="<?= MAIN_URL ?>my-orders.php" class="pf-btn pf-btn-ghost" style="text-decoration:none;">
                                    View All Orders <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>


                <!-- ===================================================== -->
                <!-- WALLET HISTORY                                        -->
                <!-- ===================================================== -->
                <div class="pf-panel" id="panel-wallet">

                    <div class="pf-wallet-summary">
                        <div class="pf-wallet-tile">
                            <div class="pf-wallet-tile-label">Current Balance</div>
                            <div class="pf-wallet-tile-value"><?= rupees($customerWallet) ?></div>
                        </div>

                        <div class="pf-wallet-tile gold">
                            <div class="pf-wallet-tile-label">Total Transactions</div>
                            <div class="pf-wallet-tile-value"><?= (int)$walletTotal ?></div>
                        </div>
                    </div>

                    <div class="pf-card">
                        <h2 class="pf-card-title">
                            <i class="bi bi-receipt"></i>
                            Wallet Transactions
                        </h2>

                        <?php if (empty($walletTxns)): ?>
                            <div class="pf-empty">
                                <i class="bi bi-wallet2"></i>
                                No wallet transactions yet.
                            </div>
                        <?php else: ?>
                            <div class="pf-table-wrap">
                                <table class="pf-table">
                                    <thead>
                                        <tr>
                                            <th>Txn Code</th>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Amount</th>
                                            <th>Before</th>
                                            <th>After</th>
                                            <th>Source</th>
                                            <th>Note</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($walletTxns as $t):
                                            $type = strtolower($t['txn_type']);
                                        ?>
                                            <tr>
                                                <td>
                                                    <span class="pf-txn-code"><?= htmlspecialchars($t['txn_code']) ?></span>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars(shortDate($t['created_at'])) ?>
                                                    <div style="font-size:10.5px;color:#948c82;"><?= htmlspecialchars(shortTime($t['created_at'])) ?></div>
                                                </td>
                                                <td>
                                                    <span class="pf-badge <?= $type === 'credit' ? 'credit' : 'debit' ?>">
                                                        <i class="bi bi-<?= $type === 'credit' ? 'plus-circle-fill' : 'dash-circle-fill' ?>"></i>
                                                        <?= htmlspecialchars(ucfirst($type)) ?>
                                                    </span>
                                                </td>
                                                <td class="pf-amount <?= $type === 'credit' ? 'credit' : 'debit' ?>">
                                                    <?= $type === 'credit' ? '+' : '−' ?><?= rupees($t['amount']) ?>
                                                </td>
                                                <td><?= rupees($t['balance_before']) ?></td>
                                                <td><strong><?= rupees($t['balance_after']) ?></strong></td>
                                                <td>
                                                    <span style="font-size:11px;color:#948c82;font-weight:700;">
                                                        <?= htmlspecialchars(ucfirst($t['source'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($t['note'])): ?>
                                                        <span class="pf-note" title="<?= htmlspecialchars($t['note']) ?>">
                                                            <?= htmlspecialchars($t['note']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span style="color:#c8bfb4;">—</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if ($walletTotalPages > 1): ?>
                                <?php echo renderPager('?tab=wallet', 'wpage', $walletPage, $walletTotalPages); ?>
                                <div class="pf-page-info">
                                    Showing page <?= (int)$walletPage ?> of <?= (int)$walletTotalPages ?>
                                    · <?= (int)$walletTotal ?> transaction<?= $walletTotal === 1 ? '' : 's' ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                </div>


                <!-- ===================================================== -->
                <!-- CONTAINERS                                            -->
                <!-- ===================================================== -->
                <div class="pf-panel" id="panel-containers">

                    <div class="pf-wallet-summary">
                        <div class="pf-wallet-tile gold">
                            <div class="pf-wallet-tile-label">Total Containers</div>
                            <div class="pf-wallet-tile-value"><?= (int)$kpi['containers_total'] ?></div>
                        </div>

                        <div class="pf-wallet-tile">
                            <div class="pf-wallet-tile-label">Currently Held</div>
                            <div class="pf-wallet-tile-value"><?= (int)$kpi['containers_held'] ?></div>
                        </div>
                    </div>

                    <div class="pf-card">
                        <h2 class="pf-card-title">
                            <i class="bi bi-box2-heart"></i>
                            Container History
                        </h2>

                        <?php if (empty($containers)): ?>
                            <div class="pf-empty">
                                <i class="bi bi-box2-heart"></i>
                                No container history yet.
                            </div>
                        <?php else: ?>
                            <div class="pf-table-wrap">
                                <table class="pf-table">
                                    <thead>
                                        <tr>
                                            <th>Order</th>
                                            <th>Given</th>
                                            <th>Returned</th>
                                            <th>Held</th>
                                            <th>Deposit</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($containers as $c):
                                            $given    = (int)$c['total_containers'];
                                            $returned = (int)$c['received_containers'];
                                            $held     = max(0, $given - $returned);
                                            $st       = strtolower($c['status'] ?? 'not_received');
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong>#<?= htmlspecialchars($c['order_code'] ?? ('ORD#' . $c['order_id'])) ?></strong>
                                                </td>
                                                <td><?= $given ?></td>
                                                <td><?= $returned ?></td>
                                                <td>
                                                    <strong style="color:<?= $held > 0 ? '#b51f2c' : '#1b5e20' ?>;">
                                                        <?= $held ?>
                                                    </strong>
                                                </td>
                                                <td><?= rupees($c['container_amount']) ?></td>
                                                <td>
                                                    <span class="pf-badge <?= htmlspecialchars($st) ?>">
                                                        <?php
                                                        if ($st === 'received') echo 'Returned';
                                                        elseif ($st === 'partial') echo 'Partial';
                                                        else echo 'Not Returned';
                                                        ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars(shortDate($c['received_at'] ?: $c['created_at'])) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if ($containerTotalPages > 1): ?>
                                <?php echo renderPager('?tab=containers', 'cpage', $containerPage, $containerTotalPages); ?>
                                <div class="pf-page-info">
                                    Showing page <?= (int)$containerPage ?> of <?= (int)$containerTotalPages ?>
                                    · <?= (int)$containerTotal ?> container record<?= $containerTotal === 1 ? '' : 's' ?>
                                </div>
                            <?php endif; ?>

                            <div style="margin-top:16px;padding:14px 16px;border-radius:12px;background:#fdf7ec;border:1px dashed #e8d5a8;font-size:12px;color:#8a6a1e;font-weight:700;line-height:1.6;">
                                <i class="bi bi-info-circle"></i>
                                Container deposit is refundable. Return the containers to the delivery person to get the deposit back into your wallet.
                            </div>
                        <?php endif; ?>
                    </div>

                </div>


                <!-- ===================================================== -->
                <!-- SECURITY                                              -->
                <!-- ===================================================== -->
                <div class="pf-panel" id="panel-security">

                    <div class="pf-card">
                        <h2 class="pf-card-title">
                            <i class="bi bi-shield-lock"></i>
                            Set New Password
                        </h2>

                        <form id="passwordForm">
                            <div class="pf-grid-2">
                                <div class="pf-field">
                                    <label>New Password</label>
                                    <div class="pf-pass-wrap">
                                        <input type="password" id="pfNewPass" class="pf-input"
                                            placeholder="At least 3 characters" autocomplete="new-password" required>
                                        <button type="button" class="pf-eye-btn" data-eye="pfNewPass" aria-label="Show password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="pf-field">
                                    <label>Confirm New Password</label>
                                    <div class="pf-pass-wrap">
                                        <input type="password" id="pfConfirmPass" class="pf-input"
                                            placeholder="Re-enter new password" autocomplete="new-password" required>
                                        <button type="button" class="pf-eye-btn" data-eye="pfConfirmPass" aria-label="Show password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="pf-btn pf-btn-primary" style="margin-top:6px;">
                                <i class="bi bi-shield-check"></i> Update Password
                            </button>
                        </form>
                    </div>

                </div>

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
        window.CUSTOMER_ID = <?= (int)$customerId ?>;
        window.CUSTOMER_DIVISION = "<?= htmlspecialchars($customerDivision, ENT_QUOTES) ?>";
        window.APARTMENTS = <?= json_encode($apartments, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        window.CURRENT_APARTMENT_ID = <?= (int)$customerAptId ?>;
        window.INITIAL_TAB = "<?= htmlspecialchars($_GET['tab'] ?? 'overview', ENT_QUOTES) ?>";
    </script>

    <script src="<?= MAIN_URL ?>js/home.js"></script>
    <script src="<?= MAIN_URL ?>js/profile.js"></script>

</body>

</html>