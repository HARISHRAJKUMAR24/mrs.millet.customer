<?php
// Fetch header top content
$topContent = '';

try {
    $stmt = $pdo->query("SELECT header_top_content FROM settings LIMIT 1");
    $topContent = $stmt->fetchColumn();
} catch (PDOException $e) {
}
?>

<?php if (!empty($topContent)): ?>
    <div class="mm-top-banner">
        <div class="mm-marquee-content">
            <?= htmlspecialchars($topContent) ?>
        </div>
    </div>
<?php endif; ?>

<header class="mm-nav">
    <div class="mm-container">
        <div class="mm-nav-inner">

            <!-- LOGO -->
            <a href="<?= MAIN_URL ?>" class="mm-nav-logo">
                <?php if ($logoUrl): ?>
                    <img src="<?= htmlspecialchars($logoUrl) ?>"
                        alt="<?= htmlspecialchars($siteName) ?>">
                <?php else: ?>
                    <div class="mm-nav-logo-fallback">
                        <svg viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 9l1-5h16l1 5" />
                            <path d="M4 9v11h16V9" />
                            <path d="M9 22V12h6v10" />
                        </svg>
                    </div>
                <?php endif; ?>
            </a>

            <!-- SEARCH BAR -->
            <div class="mm-search-wrap">

                <div class="mm-search">

                    <svg viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="7" />
                        <path d="M21 21l-4.3-4.3" />
                    </svg>

                    <input
                        type="text"
                        id="mmSearchInput"
                        placeholder="Search for products..."
                        autocomplete="off">

                </div>

                <!-- SEARCH DROPDOWN -->
                <div class="mm-search-dropdown" id="mmSearchDropdown">

                    <div class="mm-search-loading" style="display:none;">
                        <span class="mm-spinner"></span>
                        Searching...
                    </div>

                    <div id="mmSearchResults"></div>

                </div>
            </div>

            <!-- NAV ACTIONS -->
            <div class="mm-nav-actions">

                <!-- CART -->
                <a href="cart.php" class="mm-nav-icon" aria-label="Cart">

                    <svg viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="20" r="1.5" />
                        <circle cx="18" cy="20" r="1.5" />
                        <path d="M2 3h2l2.4 12.2a2 2 0 0 0 2 1.8h8.7a2 2 0 0 0 2-1.6L21 7H5" />
                    </svg>

                    <span class="mm-nav-badge" id="navCartCount">
                        <?= (int)$cartCount ?>
                    </span>

                </a>

                <!-- PROFILE -->
                <?php if ($customerLoggedIn): ?>

                    <div class="mm-profile-dropdown-wrap">

                        <a href="javascript:void(0);"
                            class="mm-nav-avatar"
                            id="mmProfileBtn">
                            <?= htmlspecialchars(strtoupper(substr($customerName ?: 'U', 0, 1))) ?>
                        </a>

                        <div class="mm-profile-menu" id="mmProfileMenu">

                            <a href="profile.php" class="mm-menu-item">
                                <svg viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round"
                                    style="width:16px;height:16px;">
                                    <circle cx="12" cy="8" r="4" />
                                    <path d="M4 21c0-4 4-6 8-6s8 2 8 6" />
                                </svg>
                                My Profile
                            </a>

                            <a href="my-orders.php" class="mm-menu-item">
                                <svg viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round"
                                    style="width:16px;height:16px;">
                                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                                    <line x1="3" y1="6" x2="21" y2="6" />
                                    <path d="M16 10a4 4 0 0 1-8 0" />
                                </svg>
                                My Orders
                            </a>

                            <div class="mm-menu-divider"></div>

                            <a href="logout.php"
                                class="mm-menu-item mm-menu-logout">
                                <svg viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round"
                                    style="width:16px;height:16px;">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                                    <polyline points="16 17 21 12 16 7" />
                                    <line x1="21" y1="12" x2="9" y2="12" />
                                </svg>
                                Logout
                            </a>

                        </div>
                    </div>

                <?php else: ?>

                    <button type="button"
                        id="navLoginBtn"
                        class="mm-nav-icon"
                        aria-label="Login">

                        <svg viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
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
    /* ==========================================
   1. TOP MARQUEE BANNER
========================================== */

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
        white-space: nowrap;
        position: relative;
        display: flex;
        align-items: center;
    }

    .mm-marquee-content {
        display: inline-block;
        padding-left: 100%;
        animation: mmMarquee 40s linear infinite;
    }

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


    /* ==========================================
   2. NAVIGATION LAYOUT
========================================== */

    .mm-nav,
    .mm-nav .mm-container {
        box-sizing: border-box;
        width: 100%;
        max-width: 100%;
    }

    .mm-nav-inner {
        display: flex;
        align-items: center;
        width: 100%;
        min-width: 0;
        gap: 10px;
        box-sizing: border-box;
    }


    /* ==========================================
   3. LOGO
========================================== */

    .mm-nav-logo {
        display: flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        min-width: 0;
        text-decoration: none;
    }

    .mm-nav-logo img {
        display: block;
        max-height: 45px;
        max-width: 180px;
        width: auto;
        object-fit: contain;
        transition: all 0.3s ease;
    }

    .mm-nav-logo-fallback {
        width: 45px;
        height: 45px;
    }


    /* ==========================================
   4. SEARCH WRAPPER
========================================== */

    .mm-search-wrap {
        flex: 0 1 480px !important;
        width: 75% !important;
        max-width: 480px !important;
        min-width: 0;
        margin-left: auto !important;
        margin-right: auto !important;
    }


    /* ==========================================
   5. SEARCH FIELD
========================================== */

    .mm-search {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: flex-start !important;

        width: 100%;
        min-width: 0;
        height: 44px;

        gap: 9px !important;
        padding: 0 12px;

        background: #f4f1ec;
        border: 1px solid transparent;
        border-radius: 12px;

        box-sizing: border-box;
        overflow: hidden;
        transition: border-color 0.2s ease, background 0.2s ease;
    }

    .mm-search:focus-within {
        border-color: #d9c9c1;
        background: #ffffff;
    }


    /* Search icon */

    .mm-search>svg {
        position: static !important;
        display: block !important;

        width: 18px !important;
        height: 18px !important;

        min-width: 18px;
        flex: 0 0 18px !important;

        margin: 0 !important;
        padding: 0 !important;

        transform: none !important;
        pointer-events: none;
        color: #777;
    }


    /* Search input */

    .mm-search>input {
        position: static !important;
        display: block !important;

        flex: 1 1 auto !important;

        width: 100% !important;
        min-width: 0 !important;
        max-width: 100%;

        height: 100% !important;

        margin: 0 !important;
        padding: 0 3px !important;

        border: none !important;
        outline: none !important;
        box-shadow: none !important;

        border-radius: 0 !important;
        background: transparent !important;

        font-family: inherit;
        font-size: 14px !important;
        line-height: normal !important;

        color: #302923;
        box-sizing: border-box !important;
    }

    .mm-search>input::placeholder {
        font-size: 14px !important;
        color: #999;
        opacity: 1;
    }


    /* ==========================================
   6. SEARCH DROPDOWN
========================================== */

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
        box-sizing: border-box;
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
        flex-shrink: 0;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }


    /* ==========================================
   7. SEARCH RESULTS
========================================== */

    .mm-search-item {
        display: flex;
        align-items: center;
        gap: 12px;

        padding: 10px;
        border-radius: 8px;

        text-decoration: none;
        transition: background 0.15s;
        cursor: pointer;

        min-width: 0;
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


    /* ==========================================
   8. NAVIGATION ACTIONS
========================================== */

    .mm-nav-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;

        gap: 14px;
        flex: 0 0 auto;
        min-width: 0;
    }

    .mm-nav-icon {
        width: 42px;
        height: 42px;
        min-width: 42px;

        display: inline-flex;
        align-items: center;
        justify-content: center;

        position: relative;
        flex-shrink: 0;
    }

    .mm-nav-icon svg {
        width: 21px;
        height: 21px;

        display: block;
        flex-shrink: 0;
    }


    /* Cart badge */

    .mm-nav-badge {
        position: absolute;
        top: 0;
        right: 0;

        min-width: 17px;
        height: 17px;
        padding: 0 4px;

        display: flex;
        align-items: center;
        justify-content: center;

        border-radius: 50px;
        font-size: 10px;
        line-height: 1;

        box-sizing: border-box;
    }


    /* ==========================================
   9. PROFILE DROPDOWN
========================================== */

    .mm-profile-dropdown-wrap {
        position: relative;
        flex-shrink: 0;
    }

    .mm-nav-avatar {
        width: 38px;
        height: 38px;

        display: flex;
        align-items: center;
        justify-content: center;

        flex-shrink: 0;
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


    /* ==========================================
   10. TABLET RESPONSIVE
========================================== */

    @media (max-width: 768px) {

        .mm-top-banner {
            font-size: 11px;
            padding: 6px 0;
        }

        .mm-nav-inner {
            gap: 5px;
        }

        .mm-nav-logo img {
            max-height: 36px;
            max-width: 100px;
        }

        .mm-nav-logo-fallback {
            width: 36px;
            height: 36px;
        }

        /* Tablet */
        @media (max-width: 768px) {
            .mm-search-wrap {
                flex: 0 1 360px !important;
                width: 85% !important;
                max-width: 360px !important;
                margin-left: auto !important;
                margin-right: auto !important;
            }
        }

        .mm-search {
            height: 38px;
            padding: 0 8px;
            gap: 6px !important;
        }

        .mm-search>svg {
            width: 16px !important;
            height: 16px !important;
            min-width: 16px;
            flex-basis: 16px !important;
        }

        .mm-search>input {
            font-size: 12px !important;
        }

        .mm-search>input::placeholder {
            font-size: 11px !important;
        }

        .mm-nav-actions {
            gap: 6px;
        }

        .mm-nav-icon {
            width: 34px;
            height: 36px;
            min-width: 34px;
        }

        .mm-nav-icon svg {
            width: 19px;
            height: 19px;
        }

        .mm-nav-avatar {
            width: 32px;
            height: 32px;
            font-size: 13px;
        }

        .mm-nav-badge {
            min-width: 15px;
            height: 15px;
            font-size: 9px;
        }

        .mm-search-dropdown {
            position: fixed;
            top: 60px;
            left: 10px;
            right: 10px;
            width: auto;
            max-height: 60vh;
        }

        .mm-search-name {
            font-size: 12px;
        }

        .mm-search-price {
            font-size: 12px;
        }
    }


    /* ==========================================
   11. MOBILE RESPONSIVE
========================================== */

    @media (max-width: 480px) {

        .mm-top-banner {
            font-size: 10px;
            padding: 5px 0;
        }

        .mm-nav-inner {
            gap: 4px;
        }

        /* Logo */

        .mm-nav-logo {
            flex: 0 0 auto;
        }

        .mm-nav-logo img {
            max-height: 30px;
            max-width: 65px;
        }

        .mm-nav-logo-fallback {
            width: 30px;
            height: 30px;
        }

        /*search bar */

        @media (max-width: 480px) {
            .mm-search-wrap {
                flex: 0 1 190px !important;
                width: 85% !important;
                max-width: 190px !important;
                margin-left: auto !important;
                margin-right: auto !important;
            }
        }


        .mm-search {
            height: 35px;
            padding: 0 6px;
            gap: 5px !important;
            border-radius: 10px;
        }

        .mm-search>svg {
            width: 14px !important;
            height: 14px !important;
            min-width: 14px;
            flex-basis: 14px !important;
        }

        .mm-search>input {
            min-width: 0 !important;
            font-size: 11px !important;
            padding: 0 2px !important;
        }

        .mm-search>input::placeholder {
            font-size: 10px !important;
        }

        /* Cart and profile */

        .mm-nav-actions {
            gap: 3px;
        }

        .mm-nav-icon {
            width: 28px;
            height: 32px;
            min-width: 28px;
            padding: 0;
        }

        .mm-nav-icon svg {
            width: 16px;
            height: 16px;
        }

        .mm-nav-avatar {
            width: 27px;
            height: 27px;
            font-size: 11px;
        }

        .mm-nav-badge {
            min-width: 13px;
            height: 13px;
            font-size: 8px;
            top: -2px;
            right: -2px;
        }

        /* Search dropdown */

        .mm-search-dropdown {
            top: 55px;
            left: 8px;
            right: 8px;
            max-height: 65vh;
            padding: 6px;
        }

        .mm-search-item {
            gap: 8px;
            padding: 8px;
        }

        .mm-search-img {
            width: 38px;
            height: 38px;
        }

        .mm-search-name {
            font-size: 11px;
        }

        .mm-search-cat {
            font-size: 9px;
        }

        .mm-search-price {
            font-size: 11px;
        }

        .mm-search-empty {
            font-size: 11px;
        }

        /* Profile menu */

        .mm-profile-menu {
            min-width: 150px;
        }

        .mm-menu-item {
            font-size: 12px;
            padding: 10px 12px;
        }
    }


    /* ==========================================
   12. EXTRA SMALL MOBILE (280px - 360px)
========================================== */

    @media (max-width: 360px) {

        .mm-nav-inner {
            gap: 3px;
        }

        /* Logo */

        .mm-nav-logo img {
            max-height: 27px;
            max-width: 54px;
        }

        .mm-nav-logo-fallback {
            width: 26px;
            height: 26px;
        }

        /* Small mobile */
        @media (max-width: 360px) {
            .mm-search-wrap {
                flex: 0 1 150px !important;
                width: 85% !important;
                max-width: 150px !important;
                margin-left: auto !important;
                margin-right: auto !important;
            }
        }

        .mm-search {
            height: 32px;
            padding: 0 5px;
            gap: 4px !important;
        }

        .mm-search>svg {
            width: 12px !important;
            height: 12px !important;
            min-width: 12px;
            flex-basis: 12px !important;
        }

        .mm-search>input {
            font-size: 10px !important;
            padding: 0 1px !important;
        }

        .mm-search>input::placeholder {
            font-size: 9px !important;
        }

        /* Icons */

        .mm-nav-actions {
            gap: 2px;
        }

        .mm-nav-icon {
            width: 25px;
            height: 30px;
            min-width: 25px;
        }

        .mm-nav-icon svg {
            width: 14px;
            height: 14px;
        }

        .mm-nav-avatar {
            width: 24px;
            height: 24px;
            font-size: 10px;
        }

        .mm-nav-badge {
            min-width: 12px;
            height: 12px;
            font-size: 7px;
            top: -2px;
            right: -2px;
        }
    }
</style>


<script>
    document.addEventListener("DOMContentLoaded", function() {

        /* ==================================
           PROFILE DROPDOWN
        ================================== */

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


        /* ==================================
           LIVE SEARCH
        ================================== */

        const searchInput = document.getElementById("mmSearchInput");
        const searchDropdown = document.getElementById("mmSearchDropdown");
        const searchResults = document.getElementById("mmSearchResults");

        if (!searchInput || !searchDropdown || !searchResults) {
            return;
        }

        const loadingDiv = searchDropdown.querySelector(".mm-search-loading");

        let searchTimer;

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

                            searchResults.innerHTML =
                                `<div class="mm-search-empty">No products found for "${query}"</div>`;

                            return;
                        }

                        searchResults.innerHTML = data.data.map(p => `

                        <a href="product.php?id=${p.id}" class="mm-search-item">

                            <img
                                src="${p.image}"
                                class="mm-search-img"
                                alt="${p.name}"
                                onerror="this.style.display='none'"
                            >

                            <div class="mm-search-info">

                                <div class="mm-search-name">
                                    ${p.name}
                                </div>

                                <div class="mm-search-cat">
                                    ${p.category}
                                </div>

                            </div>

                            <div class="mm-search-price">
                                ₹${p.price}
                            </div>

                        </a>

                    `).join("");

                    })

                    .catch(() => {

                        loadingDiv.style.display = "none";

                        searchResults.innerHTML =
                            `<div class="mm-search-empty">Error connecting to server.</div>`;

                    });

            }, 300);

        });


        /* CLOSE DROPDOWN ON OUTSIDE CLICK */

        document.addEventListener("click", function(e) {

            if (
                !searchInput.contains(e.target) &&
                !searchDropdown.contains(e.target)
            ) {
                searchDropdown.classList.remove("show");
            }

        });


        /* CLOSE ON ESCAPE */

        searchInput.addEventListener("keydown", function(e) {

            if (e.key === "Escape") {

                searchDropdown.classList.remove("show");
                this.blur();

            }

        });

    });
</script>