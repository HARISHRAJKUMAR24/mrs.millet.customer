/* =========================================================
   MRS MILL@ — CUSTOMER STORE
   File: ./js/customer-store.js
   - Login/register popups
   - Variant picker
   - Buy Now flow
   - Sticky cart bar + badge live updates
   ========================================================= */

(function () {
    "use strict";

    const MAIN_URL = (typeof window.MAIN_URL === "string" && window.MAIN_URL) ? window.MAIN_URL : "./";

    let IS_LOGGED_IN = !!window.IS_LOGGED_IN;

    /* ---------- DOM ---------- */
    const loginOverlay    = document.getElementById("loginOverlay");
    const loginForm       = document.getElementById("loginForm");
    const loginMobile     = document.getElementById("loginMobile");
    const loginBtn        = document.getElementById("loginBtn");
    const loginBtnText    = document.getElementById("loginBtnText");
    const loginClose      = document.getElementById("loginClose");
    const navLoginBtn     = document.getElementById("navLoginBtn");

    const regOverlay      = document.getElementById("registerOverlay");
    const regForm         = document.getElementById("registerForm");
    const regName         = document.getElementById("regName");
    const regMobile       = document.getElementById("regMobile");
    const regBtn          = document.getElementById("registerBtn");
    const regBtnText      = document.getElementById("registerBtnText");
    const regClose        = document.getElementById("registerClose");

    const varOverlay      = document.getElementById("variantOverlay");
    const varProductName  = document.getElementById("variantProductName");
    const varProductCode  = document.getElementById("variantProductCode");
    const varList         = document.getElementById("variantList");
    const varAddBtn       = document.getElementById("variantAddBtn");
    const varClose        = document.getElementById("variantClose");

    const navCartCount    = document.getElementById("navCartCount");

    /* ---------- STATE ---------- */
    const state = {
        pendingProduct: null,
        selectedVariantId: null,
        buyNowMode: false,
        cartCount: Number(window.CART_COUNT || 0)
    };

    /* ---------- HELPERS ---------- */
    function escapeHtml(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;")
            .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    function showToast(msg, type) {
        let toast = document.getElementById("mmToast");
        if (!toast) {
            toast = document.createElement("div");
            toast.id = "mmToast";
            document.body.appendChild(toast);
        }
        toast.textContent = msg;
        toast.className = "show" + (type === "error" ? " error" : "");
        clearTimeout(toast._timer);
        toast._timer = setTimeout(() => toast.className = "", 2600);
    }

    function openOverlay(el) {
        if (!el) return;
        el.classList.add("show");
        el.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";
    }

    function closeOverlay(el) {
        if (!el) return;
        el.classList.remove("show");
        el.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
    }

    /* ---------- CART BADGES + STICKY BAR (LIVE) ---------- */
    function updateCartBadges(count, total) {
        state.cartCount = count;

        // Nav badge
        if (navCartCount) navCartCount.textContent = count;

        // Sticky bottom bar
        const bar     = document.getElementById("stickyCartBar");
        const cntEl   = document.getElementById("stickyCartCount");
        const totEl   = document.getElementById("stickyCartTotal");
        const itemLbl = bar ? bar.querySelector(".mm-cart-items span:last-child") : null;

        if (cntEl) cntEl.textContent = count;

        if (totEl && total !== undefined && total !== null) {
            totEl.textContent = "₹" + Number(total).toFixed(2);
        }

        if (itemLbl) itemLbl.textContent = count === 1 ? "item" : "items";

        if (bar) {
            bar.style.display = count > 0 ? "flex" : "none";
        }
    }

    /* ---------- LOGIN ---------- */
    function openLogin() {
        openOverlay(loginOverlay);
        setTimeout(() => loginMobile && loginMobile.focus(), 200);
    }
    function closeLogin() { closeOverlay(loginOverlay); }

    if (loginClose) loginClose.addEventListener("click", closeLogin);
    if (loginOverlay) loginOverlay.addEventListener("click", e => {
        if (e.target === loginOverlay) closeLogin();
    });

    if (navLoginBtn) navLoginBtn.addEventListener("click", openLogin);

    if (loginMobile) {
        loginMobile.addEventListener("input", () => {
            loginMobile.value = loginMobile.value.replace(/[^0-9]/g, "").slice(0, 15);
        });
    }

    if (loginForm) {
        loginForm.addEventListener("submit", function (e) {
            e.preventDefault();

            const mobile = (loginMobile.value || "").trim();

            if (!mobile) { showToast("Please enter your mobile number.", "error"); loginMobile.focus(); return; }
            if (!/^[0-9]{10,15}$/.test(mobile)) { showToast("Mobile must be 10–15 digits.", "error"); loginMobile.focus(); return; }

            loginBtn.disabled = true;
            loginBtnText.innerHTML = '<i class="bi bi-hourglass-split"></i> Checking...';

            const fd = new FormData();
            fd.append("mobile_number", mobile);

            fetch(MAIN_URL + "ajax/customer-login.php", {
                method: "POST", body: fd, credentials: "same-origin"
            })
                .then(r => r.json().catch(() => null))
                .then(res => {
                    loginBtn.disabled = false;
                    loginBtnText.innerHTML = '<i class="bi bi-arrow-right"></i> Continue';

                    if (!res || !res.success) {
                        showToast((res && res.message) || "Something went wrong.", "error");
                        return;
                    }

                    if (res.data && res.data.exists) {
                        IS_LOGGED_IN = true;
                        closeLogin();
                        showToast(res.message || "Welcome back!");

                        setTimeout(() => {
                            window.location.reload();
                        }, 700);
                    } else {
                        closeLogin();
                        regMobile.value = mobile;
                        openRegister();
                    }
                })
                .catch(() => {
                    loginBtn.disabled = false;
                    loginBtnText.innerHTML = '<i class="bi bi-arrow-right"></i> Continue';
                    showToast("Unable to connect.", "error");
                });
        });
    }

    /* ---------- REGISTER ---------- */
    function openRegister() {
        openOverlay(regOverlay);
        setTimeout(() => regName && regName.focus(), 200);
    }
    function closeRegister() { closeOverlay(regOverlay); }

    if (regClose) regClose.addEventListener("click", closeRegister);
    if (regOverlay) regOverlay.addEventListener("click", e => {
        if (e.target === regOverlay) closeRegister();
    });

    if (regForm) {
        regForm.addEventListener("submit", function (e) {
            e.preventDefault();

            const name   = (regName.value || "").trim();
            const mobile = (regMobile.value || "").trim();

            if (!name) { showToast("Please enter your name.", "error"); regName.focus(); return; }
            if (name.length < 3) { showToast("Name must be 3+ characters.", "error"); regName.focus(); return; }

            regBtn.disabled = true;
            regBtnText.innerHTML = '<i class="bi bi-hourglass-split"></i> Creating...';

            const fd = new FormData();
            fd.append("full_name", name);
            fd.append("mobile_number", mobile);

            fetch(MAIN_URL + "ajax/customer-register.php", {
                method: "POST", body: fd, credentials: "same-origin"
            })
                .then(r => r.json().catch(() => null))
                .then(res => {
                    regBtn.disabled = false;
                    regBtnText.innerHTML = '<i class="bi bi-check-lg"></i> Create Account';

                    if (res && res.success) {
                        IS_LOGGED_IN = true;
                        closeRegister();
                        showToast(res.message || "Account created!");

                        setTimeout(() => {
                            window.location.reload();
                        }, 700);
                    } else {
                        showToast((res && res.message) || "Failed.", "error");
                    }
                })
                .catch(() => {
                    regBtn.disabled = false;
                    regBtnText.innerHTML = '<i class="bi bi-check-lg"></i> Create Account';
                    showToast("Unable to connect.", "error");
                });
        });
    }

    /* ---------- BUY NOW ---------- */
    document.querySelectorAll(".js-buy-now").forEach(btn => {
        btn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();

            const productId = Number(btn.dataset.productId || 0);
            if (productId <= 0) return;

            state.buyNowMode = true;
            openVariantPicker(productId);
        });
    });

    function openVariantPicker(productId) {
        varList.innerHTML = '<div style="text-align:center;padding:20px;color:#948c82;font-size:12px;">Loading variants…</div>';
        openOverlay(varOverlay);

        fetch(MAIN_URL + "ajax/get-product-variants.php?id=" + encodeURIComponent(productId), { credentials: "same-origin" })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success || !res.data) {
                    varList.innerHTML = '<div style="text-align:center;padding:20px;color:#b51f2c;font-size:12px;">Failed to load variants.</div>';
                    return;
                }

                const p = res.data;
                state.pendingProduct = p;
                state.selectedVariantId = null;

                varProductName.textContent = p.product_name || "Product";
                varProductCode.textContent = "#" + (p.product_code || "");

                if (!Array.isArray(p.variants) || !p.variants.length) {
                    varList.innerHTML = '<div style="text-align:center;padding:20px;color:#948c82;font-size:12px;">No variants available.</div>';
                    return;
                }

                varList.innerHTML = p.variants.map(v => {
                    const qtyLabel = v.quantity_name || (v.quantity + " " + (v.quantity_unit || ""));

                    return `
                        <div class="var-option" data-vid="${escapeHtml(v.id)}">
                            <div class="var-radio"></div>
                            <div style="flex:1;min-width:0;">
                                <div class="var-name">${escapeHtml(qtyLabel)}</div>
                                <div class="var-qty">${escapeHtml(v.quantity)} ${escapeHtml(v.quantity_unit || "")}</div>
                            </div>
                            <div class="var-price">₹${Number(v.price).toFixed(0)}</div>
                        </div>
                    `;
                }).join("");
            })
            .catch(() => {
                varList.innerHTML = '<div style="text-align:center;padding:20px;color:#b51f2c;font-size:12px;">Unable to connect.</div>';
            });
    }

    if (varList) varList.addEventListener("click", function (e) {
        const opt = e.target.closest(".var-option");
        if (!opt) return;

        varList.querySelectorAll(".var-option").forEach(o => o.classList.remove("selected"));
        opt.classList.add("selected");
        state.selectedVariantId = Number(opt.dataset.vid);
    });

    if (varClose) varClose.addEventListener("click", () => {
        closeOverlay(varOverlay);
        state.buyNowMode = false;
    });

    if (varOverlay) varOverlay.addEventListener("click", e => {
        if (e.target === varOverlay) {
            closeOverlay(varOverlay);
            state.buyNowMode = false;
        }
    });

    if (varAddBtn) {
        varAddBtn.addEventListener("click", function () {
            if (!state.pendingProduct) return;
            if (!state.selectedVariantId) { showToast("Please choose a variant.", "error"); return; }

            const product = state.pendingProduct;
            const variant = (product.variants || []).find(v => Number(v.id) === state.selectedVariantId);
            if (!variant) { showToast("Invalid variant.", "error"); return; }

            const fd = new FormData();
            fd.append("product_id", product.id);
            fd.append("variant_id", variant.id);
            fd.append("qty", 1);

            // Disable button while adding
            varAddBtn.disabled = true;
            const originalHtml = varAddBtn.innerHTML;
            varAddBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Adding...';

            fetch(MAIN_URL + "ajax/cart-add.php", {
                method: "POST", body: fd, credentials: "same-origin"
            })
                .then(r => r.json().catch(() => null))
                .then(res => {
                    varAddBtn.disabled = false;
                    varAddBtn.innerHTML = originalHtml;

                    if (!res || !res.success) {
                        showToast((res && res.message) || "Failed to add.", "error");
                        return;
                    }

                    const newCount = Number(res.data && res.data.cart_count ? res.data.cart_count : 0);
                    const newTotal = Number(res.data && res.data.cart_total ? res.data.cart_total : 0);

                    // ✅ Instantly update badges + sticky bar
                    updateCartBadges(newCount, newTotal);

                    closeOverlay(varOverlay);
                    showToast("Added to cart.");
                    state.buyNowMode = false;
                })
                .catch(() => {
                    varAddBtn.disabled = false;
                    varAddBtn.innerHTML = originalHtml;
                    showToast("Unable to connect.", "error");
                });
        });
    }

})();

