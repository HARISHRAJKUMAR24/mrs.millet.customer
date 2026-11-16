<?php
// ✅ 1. Fetch the Header Top Content from settings
$topContent = '';
try {
    $stmt = $pdo->query("SELECT header_top_content FROM settings LIMIT 1");
    $topContent = $stmt->fetchColumn();
} catch (PDOException $e) {
}
?>

<?php if (!empty($topContent)): ?>
    <!-- ✅ 2. Top Banner Bar with Marquee Animation -->
    <div class="mm-top-banner">
        <div class="mm-marquee-content">
            <?= htmlspecialchars($topContent) ?>
        </div>
    </div>
<?php endif; ?>

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

            <!-- SEARCH BAR WITH LIVE DROPDOWN -->
            <div class="mm-search-wrap">
                <div class="mm-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="7" />
                        <path d="M21 21l-4.3-4.3" />
                    </svg>
                    <input type="text" id="mmSearchInput" placeholder="Search for products..." autocomplete="off">
                </div>
                <!-- Live Search Dropdown -->
                <div class="mm-search-dropdown" id="mmSearchDropdown">
                    <div class="mm-search-loading" style="display:none;">
                        <span class="mm-spinner"></span> Searching...
                    </div>
                    <div id="mmSearchResults"></div>
                </div>
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

<style>
    /* ✅ Top Banner with Marquee Animation */
    .mm-top-banner {
        background-color: #b51f2c;
        color: #ffffff;
        padding: 8px 0;
        font-size: 13px;
        font-weight: 700;
        letter-spacing: 0.5px;
        font-family: 'DM Sans', sans-serif;
        width: 100%;
        overflow: hidden;
        /* Hide text that goes outside */
        white-space: nowrap;
        /* Force text on a single line */
        position: relative;
        display: flex;
        align-items: center;
    }

    /* The actual moving text */
    .mm-marquee-content {
        display: inline-block;
        padding-left: 100%;
        /* Start from the right */
        animation: mmMarquee 15s linear infinite;
        /* 15s to complete a loop */
    }

    /* Pause the animation when the user hovers over it */
    .mm-top-banner:hover .mm-marquee-content {
        animation-play-state: paused;
    }

    @keyframes mmMarquee {
        0% {
            transform: translateX(0);
        }

        100% {
            transform: translateX(-100%);
        }
    }

    /* ✅ Logo Responsiveness */
    .mm-nav-logo img {
        max-height: 45px;
        width: auto;
        transition: all 0.3s ease;
    }

    .mm-nav-logo-fallback {
        width: 45px;
        height: 45px;
    }

    /* =====================================================
       SEARCH DROPDOWN STYLES
       ===================================================== */
    .mm-search-wrap {
        position: relative;
        flex: 1;
        max-width: 600px;
        margin: 0 20px;
    }

    .mm-search-dropdown {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        right: 0;
        background: #fff;
        border: 1px solid #ece5da;
        border-radius: 12px;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        z-index: 200;
        display: none;
        max-height: 400px;
        overflow-y: auto;
        padding: 8px;
    }

    .mm-search-dropdown.show {
        display: block;
    }

    .mm-search-loading {
        padding: 16px;
        text-align: center;
        font-size: 12px;
        color: #817a71;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .mm-spinner {
        width: 14px;
        height: 14px;
        border: 2px solid #e8e1d8;
        border-top-color: #b51f2c;
        border-radius: 50%;
        animation: spin 0.7s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    .mm-search-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px;
        border-radius: 8px;
        text-decoration: none;
        transition: background 0.15s;
        cursor: pointer;
    }

    .mm-search-item:hover {
        background: #fdfaf4;
    }

    .mm-search-img {
        width: 44px;
        height: 44px;
        border-radius: 8px;
        background: #f7f2ec;
        object-fit: cover;
        flex-shrink: 0;
    }

    .mm-search-info {
        flex: 1;
        min-width: 0;
    }

    .mm-search-name {
        font-size: 13px;
        font-weight: 700;
        color: #302923;
        margin: 0 0 3px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .mm-search-cat {
        font-size: 10px;
        color: #948c82;
        font-weight: 600;
    }

    .mm-search-price {
        font-size: 13px;
        font-weight: 800;
        color: #b51f2c;
        margin-left: auto;
        flex-shrink: 0;
    }

    .mm-search-empty {
        padding: 20px;
        text-align: center;
        font-size: 12px;
        color: #948c82;
    }

    /* Profile Dropdown Styles */
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

    /* ✅ Mobile Responsiveness */
    @media (max-width: 768px) {
        .mm-top-banner {
            font-size: 11px;
            padding: 6px 0;
        }

        .mm-nav-logo img {
            max-height: 36px;
        }

        .mm-nav-logo-fallback {
            width: 36px;
            height: 36px;
        }

        .mm-search-wrap {
            margin: 0 10px;
        }

        .mm-search-dropdown {
            position: fixed;
            top: 60px;
            left: 10px;
            right: 10px;
            max-height: 60vh;
        }
    }
</style>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        /* ---------- Profile Dropdown Logic ---------- */
        const profileBtn = document.getElementById("mmProfileBtn");
        const profileWrap = document.querySelector(".mm-profile-dropdown-wrap");

        if (profileBtn && profileWrap) {
            profileBtn.addEventListener("click", function(e) {
                e.preventDefault();
                e.stopPropagation();
                profileWrap.classList.toggle("open");
            });
            document.addEventListener("click", function(e) {
                if (!profileWrap.contains(e.target)) {
                    profileWrap.classList.remove("open");
                }
            });
        }

        /* ---------- Live Search Logic ---------- */
        const searchInput = document.getElementById("mmSearchInput");
        const searchDropdown = document.getElementById("mmSearchDropdown");
        const searchResults = document.getElementById("mmSearchResults");
        const loadingDiv = searchDropdown.querySelector(".mm-search-loading");

        let searchTimer;

        if (searchInput) {
            // Detect typing
            searchInput.addEventListener("input", function() {
                const query = this.value.trim();
                clearTimeout(searchTimer);

                if (query.length < 2) {
                    searchDropdown.classList.remove("show");
                    return;
                }

                searchDropdown.classList.add("show");
                loadingDiv.style.display = "flex";
                searchResults.innerHTML = "";

                searchTimer = setTimeout(() => {
                    fetch(`<?= MAIN_URL ?>ajax/search-products.php?q=${encodeURIComponent(query)}`)
                        .then(res => res.json())
                        .then(data => {
                            loadingDiv.style.display = "none";

                            if (!data.success || !data.data || data.data.length === 0) {
                                searchResults.innerHTML = `<div class="mm-search-empty">No products found for "${query}"</div>`;
                                return;
                            }

                            searchResults.innerHTML = data.data.map(p => `
                                <a href="product.php?id=${p.id}" class="mm-search-item">
                                    <img src="${p.image}" class="mm-search-img" alt="${p.name}" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\\'http://www.w3.org/2000/svg\\' viewBox=\\'0 0 24 24\\' fill=\\'none\\' stroke=\\'%23aaa\\'><rect x=\\'3\\' y=\\'3\\' width=\\'18\\' height=\\'18\\' rx=\\'2\\'/><circle cx=\\'8.5\\' cy=\\'8.5\\' r=\\'1.5\\'/></svg>'">
                                    <div class="mm-search-info">
                                        <div class="mm-search-name">${p.name}</div>
                                        <div class="mm-search-cat">${p.category}</div>
                                    </div>
                                    <div class="mm-search-price">₹${p.price}</div>
                                </a>
                            `).join("");
                        })
                        .catch(() => {
                            loadingDiv.style.display = "none";
                            searchResults.innerHTML = `<div class="mm-search-empty">Error connecting to server.</div>`;
                        });
                }, 300); // 300ms debounce
            });

            // Close on outside click
            document.addEventListener("click", function(e) {
                if (!searchInput.contains(e.target) && !searchDropdown.contains(e.target)) {
                    searchDropdown.classList.remove("show");
                }
            });

            // Hide dropdown on Escape
            searchInput.addEventListener("keydown", function(e) {
                if (e.key === "Escape") {
                    searchDropdown.classList.remove("show");
                    this.blur();
                }
            });
        }
    });
</script>