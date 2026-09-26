    <a href="<?= MAIN_URL ?>checkout.php" class="mm-cart-bar" id="stickyCartBar" aria-label="Go to cart">
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
                    <span style="margin-left:6px;">item<?= $cartCount === 1 ? '' : 's' ?></span>
                </span>
            </span>
        </div>
        <div class="mm-cart-right">
            <span class="mm-cart-total" id="stickyCartTotal">₹<?= number_format((float)$cartTotal, 2) ?></span>
            <span class="mm-cart-go">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12h14" />
                    <path d="M13 6l6 6-6 6" />
                </svg>
            </span>
        </div>
    </a>