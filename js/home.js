/* =========================================================
   MRS MILL@ — CUSTOMER STORE
   File: ./js/home.js
   ========================================================= */

(function () {
    "use strict";

    const MAIN_URL = (typeof window.MAIN_URL === "string" && window.MAIN_URL) ? window.MAIN_URL : "./";
    let IS_LOGGED_IN = !!window.IS_LOGGED_IN;

    /* ---------- DOM ---------- */
    const loginOverlay       = document.getElementById("loginOverlay");
    const loginForm          = document.getElementById("loginForm");
    const loginMobile        = document.getElementById("loginMobile");
    const loginPassword      = document.getElementById("loginPassword");
    const loginPasswordField = document.getElementById("loginPasswordField");
    const loginBtn           = document.getElementById("loginBtn");
    const loginBtnText       = document.getElementById("loginBtnText");
    const loginClose         = document.getElementById("loginClose");
    const navLoginBtn        = document.getElementById("navLoginBtn");

    const regOverlay        = document.getElementById("registerOverlay");
    const regForm           = document.getElementById("registerForm");
    const regMobile         = document.getElementById("regMobile");
    const regName           = document.getElementById("regName");
    const regPassword       = document.getElementById("regPassword");
    const regAptSearch      = document.getElementById("regApartmentSearch");
    const regAptList        = document.getElementById("regApartmentList");
    const regAptId          = document.getElementById("regApartmentId");
    const regAptCode        = document.getElementById("regApartmentCode");
    const regAptHint        = document.getElementById("regApartmentHint");
    const regDivision       = document.getElementById("regDivision");
    const regDivisionText   = document.getElementById("regDivisionText");
    const regDivisionHint   = document.getElementById("regDivisionHint");
    const regBtn            = document.getElementById("registerBtn");
    const regBtnText        = document.getElementById("registerBtnText");
    const regClose          = document.getElementById("registerClose");

    const varOverlay        = document.getElementById("variantOverlay");
    const varProductName    = document.getElementById("variantProductName");
    const varProductCode    = document.getElementById("variantProductCode");
    const varList           = document.getElementById("variantList");
    const varAddBtn         = document.getElementById("variantAddBtn");
    const varClose          = document.getElementById("variantClose");

    const navCartCount      = document.getElementById("navCartCount");

    /* ---------- STATE ---------- */
    const state = {
        pendingProduct: null,
        selectedVariantId: null,
        buyNowMode: false,
        cartCount: Number(window.CART_COUNT || 0),
        apartmentsCache: [],
        lookupTimer: null,
        lastLookedMobile: "",
        customApartment: false   /* true when user typed an apartment not in the list */
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

    /* =========================================================
       PASSWORD EYE TOGGLE (global)
       ========================================================= */
    document.addEventListener("click", function (e) {
        const btn = e.target.closest(".pw-toggle");
        if (!btn) return;

        const targetId = btn.getAttribute("data-target");
        const input = document.getElementById(targetId);
        if (!input) return;

        const eyeOpen   = btn.querySelector(".eye-open");
        const eyeClosed = btn.querySelector(".eye-closed");

        if (input.type === "password") {
            input.type = "text";
            if (eyeOpen)   eyeOpen.style.display   = "none";
            if (eyeClosed) eyeClosed.style.display = "";
            btn.setAttribute("aria-label", "Hide password");
        } else {
            input.type = "password";
            if (eyeOpen)   eyeOpen.style.display   = "";
            if (eyeClosed) eyeClosed.style.display = "none";
            btn.setAttribute("aria-label", "Show password");
        }
    });

    /* =========================================================
       LIVE CART UI PAINTER
       ========================================================= */
    function paintCartUI(count, total) {
        count = Number(count) || 0;
        total = Number(total) || 0;
        state.cartCount = count;
        window.CART_COUNT = count;

        if (navCartCount) navCartCount.textContent = count;
        document.querySelectorAll(".js-cart-count, [data-cart-count]").forEach(el => {
            el.textContent = count;
        });

        const bar      = document.getElementById("stickyCartBar");
        const stickyC  = document.getElementById("stickyCartCount");
        const stickyT  = document.getElementById("stickyCartTotal");
        const stickyLb = document.getElementById("stickyCartItemLabel");
        const itemLbl  = bar ? bar.querySelector(".mm-cart-items span:last-child") : null;

        if (stickyC) stickyC.textContent = count;
        if (stickyT) stickyT.textContent = "₹" + Math.round(total);
        if (stickyLb) stickyLb.textContent = count === 1 ? "item" : "items";
        if (itemLbl) itemLbl.textContent = count === 1 ? "item" : "items";
        if (bar) bar.style.display = count > 0 ? "flex" : "none";

        const headCount  = document.getElementById("cartHeaderCount");
        const headPlural = document.getElementById("cartHeaderPlural");
        if (headCount) headCount.textContent = count;
        if (headPlural) headPlural.textContent = count === 1 ? "" : "s";

        const sumSub   = document.getElementById("summarySubtotal");
        const sumTotal = document.getElementById("summaryTotal");
        if (sumSub) sumSub.textContent = "₹" + Math.round(total);
        if (sumTotal) sumTotal.textContent = "₹" + Math.round(total);
    }

    function updateCartBadges(count, total) { paintCartUI(count, total); }

    let cartInflight = null;
    function refreshCartUI() {
        if (cartInflight) return cartInflight;
        cartInflight = fetch(MAIN_URL + "ajax/cart-count.php", {
            credentials: "same-origin",
            cache: "no-store"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                cartInflight = null;
                if (res && res.success && res.data) {
                    paintCartUI(res.data.cart_count, res.data.cart_total);
                }
                return res;
            })
            .catch(() => {
                cartInflight = null;
                return null;
            });
        return cartInflight;
    }

    window.refreshCartUI = refreshCartUI;
    window.paintCartUI = paintCartUI;

    document.addEventListener("visibilitychange", () => {
        if (!document.hidden) refreshCartUI();
    });
    window.addEventListener("focus", refreshCartUI);
    window.addEventListener("pageshow", (e) => {
        if (e.persisted) refreshCartUI();
    });

    document.addEventListener("DOMContentLoaded", refreshCartUI);
    if (document.readyState !== "loading") refreshCartUI();

    /* =========================================================
       LOGIN POPUP
       ========================================================= */
    function openLogin() {
        if (loginMobile) loginMobile.value = "";
        if (loginPassword) loginPassword.value = "";
        if (loginPasswordField) loginPasswordField.style.display = "none";
        if (loginBtnText) loginBtnText.textContent = "Continue";
        state.lastLookedMobile = "";

        openOverlay(loginOverlay);
        setTimeout(() => loginMobile && loginMobile.focus(), 200);
    }

    function closeLogin() { closeOverlay(loginOverlay); }

    if (loginClose) loginClose.addEventListener("click", closeLogin);
    if (loginOverlay) loginOverlay.addEventListener("click", e => {
        if (e.target === loginOverlay) closeLogin();
    });
    if (navLoginBtn) navLoginBtn.addEventListener("click", openLogin);

    /* =========================================================
       AUTO-LOOKUP
       ========================================================= */
    if (loginMobile) {
        loginMobile.addEventListener("input", function () {
            this.value = this.value.replace(/[^0-9]/g, "").slice(0, 15);

            clearTimeout(state.lookupTimer);
            const m = this.value.trim();

            if (loginPasswordField) loginPasswordField.style.display = "none";
            if (loginPassword) loginPassword.value = "";
            if (loginBtnText) loginBtnText.textContent = "Continue";

            if (m.length < 10) return;
            if (m === state.lastLookedMobile) return;

            state.lookupTimer = setTimeout(() => { autoLookupMobile(m); }, 350);
        });

        loginMobile.addEventListener("blur", function () {
            const m = this.value.trim();
            if (m.length >= 10) {
                clearTimeout(state.lookupTimer);
                autoLookupMobile(m);
            }
        });
    }

    function autoLookupMobile(mobile) {
        state.lastLookedMobile = mobile;

        fetch(MAIN_URL + "ajax/customer-lookup.php?mobile=" + encodeURIComponent(mobile), {
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success || !res.data) return;

                if (res.data.exists) {
                    if (res.data.has_password) {
                        loginPasswordField.style.display = "";
                        loginBtnText.textContent = "Login";
                        setTimeout(() => loginPassword && loginPassword.focus(), 100);
                    } else {
                        closeLogin();
                        openRegister(mobile, res.data);
                    }
                }
            })
            .catch(() => {});
    }

    if (loginForm) {
        loginForm.addEventListener("submit", function (e) {
            e.preventDefault();

            const mobile   = (loginMobile.value || "").trim();
            const password = (loginPassword.value || "").trim();

            if (!/^[0-9]{10,15}$/.test(mobile)) {
                showToast("Mobile must be 10–15 digits.", "error");
                loginMobile.focus();
                return;
            }

            if (loginPasswordField && loginPasswordField.style.display !== "none") {
                submitLogin(mobile, password);
                return;
            }

            loginBtn.disabled = true;
            loginBtnText.innerHTML = '<i class="bi bi-hourglass-split"></i> Checking...';

            fetch(MAIN_URL + "ajax/customer-lookup.php?mobile=" + encodeURIComponent(mobile), {
                credentials: "same-origin"
            })
                .then(r => r.json().catch(() => null))
                .then(res => {
                    loginBtn.disabled = false;
                    loginBtnText.textContent = "Continue";

                    if (!res || !res.success || !res.data) {
                        showToast((res && res.message) || "Lookup failed.", "error");
                        return;
                    }

                    if (res.data.exists) {
                        if (res.data.has_password) {
                            loginPasswordField.style.display = "";
                            loginBtnText.textContent = "Login";
                            setTimeout(() => loginPassword && loginPassword.focus(), 100);
                        } else {
                            closeLogin();
                            openRegister(mobile, res.data);
                        }
                    } else {
                        closeLogin();
                        openRegister(mobile, null);
                    }
                })
                .catch(() => {
                    loginBtn.disabled = false;
                    loginBtnText.textContent = "Continue";
                    showToast("Unable to connect.", "error");
                });
        });
    }

    function submitLogin(mobile, password) {
        if (!password) { showToast("Please enter your password.", "error"); loginPassword.focus(); return; }

        loginBtn.disabled = true;
        loginBtnText.innerHTML = '<i class="bi bi-hourglass-split"></i> Signing in...';

        const fd = new FormData();
        fd.append("mobile_number", mobile);
        fd.append("password", password);

        fetch(MAIN_URL + "ajax/customer-login.php", {
            method: "POST", body: fd, credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                loginBtn.disabled = false;
                loginBtnText.textContent = "Login";

                if (!res || !res.success) {
                    showToast((res && res.message) || "Login failed.", "error");
                    return;
                }

                IS_LOGGED_IN = true;
                closeLogin();
                showToast("Welcome back!");
                setTimeout(() => window.location.reload(), 700);
            })
            .catch(() => {
                loginBtn.disabled = false;
                loginBtnText.textContent = "Login";
                showToast("Unable to connect.", "error");
            });
    }

    /* =========================================================
       REGISTER POPUP
       ========================================================= */
    function openRegister(mobile, customerData) {
        if (regMobile) regMobile.value = mobile || "";
        if (regName) regName.value = (customerData && customerData.name) ? customerData.name : "";
        if (regPassword) regPassword.value = "";
        if (regAptList) regAptList.style.display = "none";
        if (regAptHint) regAptHint.textContent = "";

        /* Reset custom mode */
        state.customApartment = false;

        /* Reset division UI */
        if (regDivision) {
            regDivision.innerHTML = '<option value="">Select apartment first</option>';
            regDivision.disabled = true;
            regDivision.style.display = "";
        }
        if (regDivisionText) {
            regDivisionText.value = "";
            regDivisionText.style.display = "none";
        }
        if (regDivisionHint) regDivisionHint.textContent = "";
        if (regBtnText) regBtnText.textContent = "Create Account";

        if (customerData && customerData.apartment_id > 0) {
            if (regAptSearch) regAptSearch.value = customerData.apartment_name || "";
            if (regAptId) regAptId.value = customerData.apartment_id;
            if (regAptCode) regAptCode.value = customerData.apartment_code || "";
        } else {
            if (regAptSearch) regAptSearch.value = "";
            if (regAptId) regAptId.value = "";
            if (regAptCode) regAptCode.value = "";
        }

        loadRegisterApartments(function () {
            if (customerData && customerData.apartment_id > 0) {
                const apt = state.apartmentsCache.find(a => Number(a.id) === Number(customerData.apartment_id));
                if (apt) {
                    populateRegisterDivisions(apt, customerData.division || "");
                }
            }
        });

        openOverlay(regOverlay);
        setTimeout(() => regName && regName.focus(), 200);
    }

    function closeRegister() { closeOverlay(regOverlay); }

    if (regClose) regClose.addEventListener("click", closeRegister);
    if (regOverlay) regOverlay.addEventListener("click", e => {
        if (e.target === regOverlay) closeRegister();
    });

    function loadRegisterApartments(cb) {
        fetch(MAIN_URL + "ajax/get-appartment-divisions.php", { credentials: "same-origin" })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (res && res.success && Array.isArray(res.data)) {
                    state.apartmentsCache = res.data;
                } else {
                    state.apartmentsCache = [];
                }
                if (typeof cb === "function") cb();
            })
            .catch(() => {
                state.apartmentsCache = [];
                if (typeof cb === "function") cb();
            });
    }

    function renderRegAptList(query) {
        if (!regAptList) return;

        if (state.apartmentsCache.length === 0) {
            regAptList.innerHTML = `<div style="padding:14px;text-align:center;font-size:12px;color:#948c82;">No apartments available. You can type your own.</div>`;
            regAptList.style.display = "block";
            return;
        }

        const q = (query || "").trim().toLowerCase();
        let list = state.apartmentsCache;
        if (q) {
            list = list.filter(a =>
                (a.apartment_name || "").toLowerCase().includes(q) ||
                (a.apartment_code || "").toLowerCase().includes(q) ||
                (a.apartment_address || "").toLowerCase().includes(q)
            );
        }

        if (!list.length) {
            regAptList.innerHTML = `<div style="padding:14px;text-align:center;font-size:12px;color:#948c82;">
                No matches found. Keep typing to request a new apartment.
            </div>`;
            regAptList.style.display = "block";
            return;
        }

        regAptList.innerHTML = list.slice(0, 40).map(a => {
            const divsEncoded = encodeURIComponent(JSON.stringify(a.divisions || []));
            return `
                <div class="mm-co-dd-item"
                     data-id="${a.id}"
                     data-code="${escapeHtml(a.apartment_code)}"
                     data-name="${escapeHtml(a.apartment_name)}"
                     data-divisions-enc="${divsEncoded}">
                    <strong>${escapeHtml(a.apartment_name)}</strong>
                    <span>${escapeHtml(a.apartment_address || '')}</span>
                </div>
            `;
        }).join("");
        regAptList.style.display = "block";
    }

    function populateRegisterDivisions(apt, preselect) {
        if (!regDivision) return;

        const divs = Array.isArray(apt.divisions) ? apt.divisions : [];

        /* Known apartment → show SELECT dropdown */
        state.customApartment = false;
        if (regDivisionText) {
            regDivisionText.value = "";
            regDivisionText.style.display = "none";
        }
        regDivision.style.display = "";

        if (!divs.length) {
            regDivision.innerHTML = '<option value="">No divisions</option>';
            regDivision.disabled = true;
            if (regDivisionHint) regDivisionHint.textContent = "No divisions for this apartment.";
            return;
        }

        regDivision.innerHTML = '<option value="">— Select division —</option>' +
            divs.map(d => `
                <option value="${escapeHtml(d.division)}" data-charge="${Number(d.charge)}">
                    Division ${escapeHtml(d.division)} · ₹${Math.round(Number(d.charge))}
                </option>
            `).join("");
        regDivision.disabled = false;
        if (regDivisionHint) regDivisionHint.textContent = "";

        if (preselect) {
            regDivision.value = preselect;
            regDivision.dispatchEvent(new Event("change"));
        }
    }

    /* Called when user typed an apartment that is NOT in the list */
    function enableCustomApartmentMode() {
        state.customApartment = true;

        if (regAptId) regAptId.value = "";
        if (regAptCode) regAptCode.value = "";
        if (regAptHint) regAptHint.textContent = "We'll save your request — our team will contact you to confirm.";

        /* Swap division select → free-text input */
        if (regDivision) {
            regDivision.value = "";
            regDivision.disabled = true;
            regDivision.style.display = "none";
        }
        if (regDivisionText) {
            regDivisionText.style.display = "";
        }
        if (regDivisionHint) {
            regDivisionHint.textContent = "Type your division (e.g. 4, Block B, Tower 2).";
        }
    }

    if (regAptSearch) {
        regAptSearch.addEventListener("focus", () => renderRegAptList(regAptSearch.value));

        regAptSearch.addEventListener("input", function () {
            const typed = this.value.trim();

            /* Clear previously selected id/code — user is retyping */
            if (regAptId) regAptId.value = "";
            if (regAptCode) regAptCode.value = "";

            /* Look for exact match in cache */
            const exact = state.apartmentsCache.find(a =>
                (a.apartment_name || "").toLowerCase() === typed.toLowerCase()
            );

            if (exact) {
                /* Known apartment → normal mode */
                state.customApartment = false;
                if (regAptId) regAptId.value = exact.id;
                if (regAptCode) regAptCode.value = exact.apartment_code;
                if (regAptHint) regAptHint.textContent = "";
                populateRegisterDivisions(exact, "");
            } else if (typed.length > 0) {
                /* Unknown apartment → custom request mode */
                enableCustomApartmentMode();
            } else {
                /* Empty → reset */
                state.customApartment = false;
                if (regAptHint) regAptHint.textContent = "";
                if (regDivision) {
                    regDivision.style.display = "";
                    regDivision.innerHTML = '<option value="">Select apartment first</option>';
                    regDivision.disabled = true;
                }
                if (regDivisionText) {
                    regDivisionText.value = "";
                    regDivisionText.style.display = "none";
                }
                if (regDivisionHint) regDivisionHint.textContent = "";
            }

            renderRegAptList(this.value);
        });
    }

    if (regAptList) {
        regAptList.addEventListener("click", (e) => {
            const item = e.target.closest(".mm-co-dd-item");
            if (!item) return;

            /* User picked from list → normal mode */
            state.customApartment = false;

            regAptId.value     = item.dataset.id;
            regAptCode.value   = item.dataset.code;
            regAptSearch.value = item.dataset.name;
            regAptList.style.display = "none";
            if (regAptHint) regAptHint.textContent = "";

            let divs = [];
            try {
                divs = JSON.parse(decodeURIComponent(item.dataset.divisionsEnc || "[]"));
            } catch (err) { divs = []; }

            const apt = state.apartmentsCache.find(a => Number(a.id) === Number(item.dataset.id));
            if (apt) {
                populateRegisterDivisions(apt, "");
            } else if (divs.length) {
                populateRegisterDivisions({ divisions: divs }, "");
            }
        });
    }

    if (regDivision) {
        regDivision.addEventListener("change", function () {
            const opt = this.options[this.selectedIndex];
            if (!opt || !opt.value) {
                if (regDivisionHint) regDivisionHint.textContent = "";
                return;
            }
            if (regDivisionHint) {
                regDivisionHint.textContent = "Charge: ₹" + Math.round(Number(opt.dataset.charge || 0));
            }
        });
    }

    document.addEventListener("click", (e) => {
        if (regAptList && !e.target.closest("#regApartmentList") && !e.target.closest("#regApartmentSearch")) {
            regAptList.style.display = "none";
        }
    });

    /* =========================================================
       REGISTER SUBMIT
       ========================================================= */
    if (regForm) {
        regForm.addEventListener("submit", function (e) {
            e.preventDefault();

            const mobile   = (regMobile.value || "").trim();
            const name     = (regName.value || "").trim();
            const password = (regPassword.value || "").trim();

            const aptText  = (regAptSearch.value || "").trim();
            const aptId    = (regAptId.value || "").trim();
            const division = (regDivision.value || "").trim();
            const divisionText = (regDivisionText && regDivisionText.style.display !== "none")
                ? (regDivisionText.value || "").trim()
                : "";

            if (name.length < 3) { showToast("Please enter your name (3+ chars).", "error"); regName.focus(); return; }
            if (password.length < 3) { showToast("Password must be 3+ characters.", "error"); regPassword.focus(); return; }
            if (aptText === "") { showToast("Please enter your apartment.", "error"); regAptSearch.focus(); return; }

            /* Determine mode */
            const isCustom = state.customApartment || !aptId;

            if (isCustom) {
                /* Custom request → require typed division */
                if (divisionText === "") {
                    showToast("Please type your division.", "error");
                    regDivisionText && regDivisionText.focus();
                    return;
                }
            } else {
                /* Known apartment → require selected division */
                if (division === "") {
                    showToast("Please select a division.", "error");
                    regDivision.focus();
                    return;
                }
            }

            regBtn.disabled = true;
            regBtnText.innerHTML = '<i class="bi bi-hourglass-split"></i> Creating...';

            const fd = new FormData();
            fd.append("full_name", name);
            fd.append("mobile_number", mobile);
            fd.append("password", password);

            if (isCustom) {
                fd.append("custom_apartment", aptText);
                fd.append("custom_division", divisionText);
            } else {
                fd.append("apartment_id", aptId);
                fd.append("division", division);
            }

            fetch(MAIN_URL + "ajax/customer-register.php", {
                method: "POST", body: fd, credentials: "same-origin"
            })
                .then(r => r.json().catch(() => null))
                .then(res => {
                    regBtn.disabled = false;
                    regBtnText.textContent = "Create Account";

                    if (!res || !res.success) {
                        showToast((res && res.message) || "Registration failed.", "error");
                        return;
                    }

                    /* =========================================================
                       CUSTOM REQUEST MODE → do NOT log in
                       ========================================================= */
                    if (res.data && res.data.request_only) {
                        closeRegister();

                        if (res.data.already_requested) {
                            showToast("You've already sent a request. Our team will contact you soon.");
                        } else {
                            showToast("Request received! Our team will contact you soon.");
                        }
                        return;
                    }

                    /* Normal registration → log in and reload */
                    IS_LOGGED_IN = true;
                    closeRegister();
                    showToast("Account created!");
                    setTimeout(() => window.location.reload(), 800);
                })
                .catch(() => {
                    regBtn.disabled = false;
                    regBtnText.textContent = "Create Account";
                    showToast("Unable to connect.", "error");
                });
        });
    }

    /* =========================================================
       BUY NOW
       ========================================================= */
    document.querySelectorAll(".js-buy-now").forEach(btn => {
        btn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();

            const productId = Number(btn.dataset.productId || 0);
            if (productId <= 0) return;

            if (!IS_LOGGED_IN) {
                state.buyNowMode = true;
                openLogin();
                return;
            }

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