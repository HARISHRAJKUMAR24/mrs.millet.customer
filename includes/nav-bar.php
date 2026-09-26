    <header class="mm-nav">
        <div class="mm-container">
            <div class="mm-nav-inner">

                <a href="<?= MAIN_URL ?>" class="mm-nav-logo">
                    <?php if ($logoUrl): ?>
                        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($siteName) ?>">
                    <?php else: ?>
                        <div class="mm-nav-logo-fallback">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 9l1-5h16l1 5" />
                                <path d="M4 9v11h16V9" />
                                <path d="M9 22V12h6v10" />
                            </svg>
                        </div>
                    <?php endif; ?>
                </a>

                <div class="mm-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="7" />
                        <path d="M21 21l-4.3-4.3" />
                    </svg>
                    <input type="text" placeholder="Search for products...">
                </div>

                <div class="mm-nav-actions">
                    <a href="cart.php" class="mm-nav-icon" aria-label="Cart">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="20" r="1.5" />
                            <circle cx="18" cy="20" r="1.5" />
                            <path d="M2 3h2l2.4 12.2a2 2 0 0 0 2 1.8h8.7a2 2 0 0 0 2-1.6L21 7H5" />
                        </svg>
                        <span class="mm-nav-badge" id="navCartCount"><?= (int)$cartCount ?></span>
                    </a>

                    <?php if ($customerLoggedIn): ?>
                        <a href="profile.php" class="mm-nav-avatar">
                            <?= htmlspecialchars(strtoupper(substr($customerName ?: 'U', 0, 1))) ?>
                        </a>
                    <?php else: ?>
                        <button type="button" id="navLoginBtn" class="mm-nav-icon" aria-label="Login">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="8" r="4" />
                                <path d="M4 21c0-4 4-6 8-6s8 2 8 6" />
                            </svg>
                        </button>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </header>