<?php
/* =========================================================
   MRS MILL@ — STICKY CART BAR
   File: ./includes/sticky-cart.php
   ========================================================= */

$customerId = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0
    ? (int)$_SESSION['customer_id']
    : 0;

$cartCount = 0;
$cartTotal = 0.0;

if ($customerId > 0) {
    try {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(qty),0) AS c,
                    COALESCE(SUM(price * qty),0) AS t
             FROM customer_cart WHERE customer_id = ?"
        );
        $stmt->execute([$customerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $cartCount = (int)$row['c'];
        $cartTotal = (float)$row['t'];
    } catch (PDOException $e) {}
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
<a href="<?= MAIN_URL ?>cart.php" class="mm-cart-bar" id="stickyCartBar" aria-label="Go to cart"
   style="<?= $cartCount > 0 ? 'display:flex;' : '' ?>">
    <div class="mm-cart-left">
        <span class="mm-cart-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                <path d="M3 6h18" />
                <path d="M16 10a4 4 0 0 1-8 0" />
            </svg>
        </span>
        <span class="mm-cart-meta">
            <span class="mm-cart-label">Your Cart</span>
            <span class="mm-cart-items">
                <span class="mm-cart-badge" id="stickyCartCount"><?= (int)$cartCount ?></span>
                <span style="margin-left:6px;" id="stickyCartItemLabel">item<?= $cartCount === 1 ? '' : 's' ?></span>
            </span>
        </span>
    </div>
    <div class="mm-cart-right">
        <span class="mm-cart-total" id="stickyCartTotal">₹<?= number_format((float)$cartTotal, 0) ?></span>
        <span class="mm-cart-go">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14" />
                <path d="M13 6l6 6-6 6" />
            </svg>
        </span>
    </div>
</a>