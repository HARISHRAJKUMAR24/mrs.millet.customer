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
                        <!-- LOGGED IN: Show Profile Avatar with Dropdown -->
                        <div class="mm-profile-dropdown-wrap">
                            <a href="javascript:void(0);" class="mm-nav-avatar" id="mmProfileBtn">
                                <?= htmlspecialchars(strtoupper(substr($customerName ?: 'U', 0, 1))) ?>
                            </a>

                            <div class="mm-profile-menu" id="mmProfileMenu">
                                <a href="profile.php" class="mm-menu-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;">
                                        <circle cx="12" cy="8" r="4" />
                                        <path d="M4 21c0-4 4-6 8-6s8 2 8 6" />
                                    </svg>
                                    My Profile
                                </a>
                                <a href="my-orders.php" class="mm-menu-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;">
                                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                                        <line x1="3" y1="6" x2="21" y2="6" />
                                        <path d="M16 10a4 4 0 0 1-8 0" />
                                    </svg>
                                    My Orders
                                </a>
                                <div class="mm-menu-divider"></div>
                                <a href="logout.php" class="mm-menu-item mm-menu-logout">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;">
                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                                        <polyline points="16 17 21 12 16 7" />
                                        <line x1="21" y1="12" x2="9" y2="12" />
                                    </svg>
                                    Logout
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- NOT LOGGED IN: Show Login Button -->
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

    <!-- Add this CSS to your stylesheet -->
    <style>
        .mm-profile-dropdown-wrap {
            position: relative;
        }

        .mm-profile-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: #fff;
            border: 1px solid #ece5da;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            min-width: 160px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-8px);
            transition: all 0.2s ease;
            z-index: 100;
            overflow: hidden;
        }

        .mm-profile-dropdown-wrap.open .mm-profile-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .mm-menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 600;
            color: #302923;
            text-decoration: none;
            transition: background 0.15s;
        }

        .mm-menu-item:hover {
            background: #faf7f0;
            color: #b51f2c;
        }

        .mm-menu-logout {
            color: #b51f2c;
        }

        .mm-menu-divider {
            height: 1px;
            background: #f0ebe4;
            margin: 4px 0;
        }
    </style>

    <!-- Add this JS at the bottom of your page -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const profileBtn = document.getElementById("mmProfileBtn");
            const profileWrap = document.querySelector(".mm-profile-dropdown-wrap");

            if (profileBtn && profileWrap) {
                // Toggle dropdown
                profileBtn.addEventListener("click", function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    profileWrap.classList.toggle("open");
                });

                // Close when clicking outside
                document.addEventListener("click", function(e) {
                    if (!profileWrap.contains(e.target)) {
                        profileWrap.classList.remove("open");
                    }
                });
            }
        });
    </script>