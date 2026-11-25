/* =========================================================
   MRS MILL@ — CUSTOMER STORE
   File: ./js/home.js   (v2026-11-16e — login lookup fix + 10s request alert)
   ========================================================= */

(function () {
    "use strict";

    const MAIN_URL = (typeof window.MAIN_URL === "string" && window.MAIN_URL) ? window.MAIN_URL : "./";
    let IS_LOGGED_IN = !!window.IS_LOGGED_IN;

    const Swal_ = (typeof window.Swal !== "undefined") ? window.Swal : null;

    /* ---------------------------------------------------------
       Toast
       --------------------------------------------------------- */
    function sToast(msg, type) {
        type = type || "info";
        if (Swal_) {
            const icon = (type === "error") ? "error" : (type === "success" ? "success" : "info");
            Swal_.fire({
                toast: true, position: "top-end", icon: icon, title: msg,
                showConfirmButton: false, timer: 3000, timerProgressBar: true,
                customClass: { container: 'swal-high-z-index' }
            });
            return;
        }
        let t = document.getElementById("mmToast");
        if (!t) { t = document.createElement("div"); t.id = "mmToast"; document.body.appendChild(t); }
        t.textContent = msg;
        t.className = "show" + (type === "error" ? " error" : "");
        clearTimeout(t._t);
        t._t = setTimeout(() => (t.className = ""), 2800);
    }

    /* ---------------------------------------------------------
       sModal — supports { timer, showConfirm, confirmText }
       No alert() fallback.
       --------------------------------------------------------- */
    function sModal(title, html, icon, options) {
        options = options || {};
        const timer = Number(options.timer) || 0;
        const showConfirm = options.showConfirm !== false;

        if (Swal_) {
            const cfg = {
                title: title || "",
                html: html || "",
                icon: icon || "info",
                confirmButtonColor: "#b51f2c",
                confirmButtonText: options.confirmText || "OK",
                allowOutsideClick: true,
                allowEscapeKey: true,
                customClass: { container: 'swal-high-z-index' }
            };
            if (timer > 0) {
                cfg.timer = timer;
                cfg.timerProgressBar = true;
                cfg.showConfirmButton = showConfirm;
            } else if (!showConfirm) {
                cfg.showConfirmButton = false;
            }
            return Swal_.fire(cfg);
        }

        // No-Swal fallback modal (never alert())
        return new Promise(function (resolve) {
            const existing = document.getElementById("mmFallbackModal");
            if (existing) existing.remove();

            const overlay = document.createElement("div");
            overlay.id = "mmFallbackModal";
            overlay.style.cssText =
                "position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:99999;" +
                "display:flex;align-items:center;justify-content:center;padding:16px;";

            const card = document.createElement("div");
            card.style.cssText =
                "background:#fff;border-radius:16px;max-width:420px;width:100%;" +
                "padding:24px;box-shadow:0 20px 60px rgba(0,0,0,.3);" +
                "font-family:inherit;text-align:center;";

            card.innerHTML =
                '<h3 style="margin:0 0 10px;color:#302923;font-size:18px;">' +
                String(title || "") + '</h3>' +
                '<div style="color:#6f675f;font-size:13px;line-height:1.55;">' +
                String(html || "") + '</div>';

            if (showConfirm || timer === 0) {
                const btn = document.createElement("button");
                btn.textContent = options.confirmText || "OK";
                btn.style.cssText =
                    "margin-top:18px;padding:10px 22px;border:0;border-radius:10px;" +
                    "background:#b51f2c;color:#fff;font-weight:700;cursor:pointer;";
                btn.onclick = () => { overlay.remove(); resolve(); };
                card.appendChild(btn);
            }

            overlay.appendChild(card);
            document.body.appendChild(overlay);

            if (timer > 0) {
                setTimeout(() => { overlay.remove(); resolve(); }, timer);
            }
        });
    }

    /* Auto-close helper (default 10s) */
    function sModalAuto(title, html, icon, seconds) {
        return sModal(title, html, icon || "success", {
            timer: (Number(seconds) || 10) * 1000,
            showConfirm: true,
            confirmText: "Got it"
        });
    }

    window.sToast = sToast;
    window.sModal = sModal;
    window.sModalAuto = sModalAuto;

    /* ---------- DOM ---------- */
    const loginOverlay = document.getElementById("loginOverlay");
    const loginForm = document.getElementById("loginForm");
    const loginMobile = document.getElementById("loginMobile");
    const loginPassword = document.getElementById("loginPassword");
    const loginPasswordField = document.getElementById("loginPasswordField");
    const loginBtn = document.getElementById("loginBtn");
    const loginBtnText = document.getElementById("loginBtnText");
    const loginClose = document.getElementById("loginClose");
    const navLoginBtn = document.getElementById("navLoginBtn");

    const regOverlay = document.getElementById("registerOverlay");
    const regForm = document.getElementById("registerForm");
    const regMobile = document.getElementById("regMobile");
    const regName = document.getElementById("regName");
    const regPassword = document.getElementById("regPassword");
    const regPasswordField = document.getElementById("regPasswordField");
    const regReferrer = document.getElementById("regReferrer");
    const regAptSearch = document.getElementById("regApartmentSearch");
    const regAptList = document.getElementById("regApartmentList");
    const regAptId = document.getElementById("regApartmentId");
    const regAptCode = document.getElementById("regApartmentCode");
    const regAptHint = document.getElementById("regApartmentHint");

    const regBlock = document.getElementById("regBlock");
    const regBlockList = document.getElementById("regBlockList");
    const regFloor = document.getElementById("regFloor");
    const regFloorList = document.getElementById("regFloorList");
    const regDoorNo = document.getElementById("regDoorNo");
    const regDoorNoList = document.getElementById("regDoorNoList");
    const regVariantHint = document.getElementById("regVariantHint");

    const regHouseLandmark = document.getElementById("regHouseLandmark");
    const regHouseLandmark2 = document.getElementById("regHouseLandmark2");

    const regAptAddress = document.getElementById("regAptAddress");

    const regAptMap = document.getElementById("regAptMap");
    const regAptLocBtn = document.getElementById("regAptLocBtn");
    const regAptLocText = document.getElementById("regAptLocBtnText");
    const regAptLocHint = document.getElementById("regAptLocHint");
    const regAptLocActions = document.getElementById("regAptLocActions");
    const regAptLocEdit = document.getElementById("regAptLocEdit");
    const regAptLocUndo = document.getElementById("regAptLocUndo");
    const regAptImg = document.getElementById("regAptImg");
    const regAptImgDrop = document.getElementById("regAptImgDrop");
    const regAptImgPrev = document.getElementById("regAptImgPreview");

    const regBtn = document.getElementById("registerBtn");
    const regBtnText = document.getElementById("registerBtnText");
    const regClose = document.getElementById("registerClose");

    const btnAptMode = document.getElementById("btnAptMode");
    const btnHouseMode = document.getElementById("btnHouseMode");
    const regAptBlock = document.getElementById("regApartmentBlock");
    const regHouseBlock = document.getElementById("regHouseBlock");
    const regHouseAddress = document.getElementById("regHouseAddress");
    const regHouseMap = document.getElementById("regHouseMap");
    const regHouseLocBtn = document.getElementById("regHouseLocBtn");
    const regHouseLocText = document.getElementById("regHouseLocBtnText");
    const regHouseLocHint = document.getElementById("regHouseLocHint");
    const regHouseLocActions = document.getElementById("regHouseLocActions");
    const regHouseLocEdit = document.getElementById("regHouseLocEdit");
    const regHouseLocUndo = document.getElementById("regHouseLocUndo");
    const regHouseImg = document.getElementById("regHouseImg");
    const regHouseImgDrop = document.getElementById("regHouseImgDrop");
    const regHouseImgPrev = document.getElementById("regHouseImgPreview");

    const navCartCount = document.getElementById("navCartCount");

    /* ✅ State — includes new lookup guards */
    const state = {
        cartCount: Number(window.CART_COUNT || 0),
        apartmentsCache: [],
        lookupTimer: null,
        lastLookedMobile: "",
        lookupInFlight: false,
        lookupToken: 0,
        lastAppliedMobile: "",
        customApartment: false,
        regMode: "apartment",
        regHouseImgData: "",
        regHouseMapLink: "",
        regAptMapLink: "",
        regAptImgData: "",
        currentVariants: [],
        selectedCharge: null
    };

    function escapeHtml(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;")
            .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
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

    function closeAllDropdowns() {
        if (regAptList) regAptList.style.display = "none";
        if (regBlockList) regBlockList.style.display = "none";
        if (regFloorList) regFloorList.style.display = "none";
        if (regDoorNoList) regDoorNoList.style.display = "none";
    }

    document.addEventListener("click", function (e) {
        const inside =
            e.target.closest("#regApartmentList") || e.target.closest("#regBlockList") ||
            e.target.closest("#regFloorList") || e.target.closest("#regDoorNoList") ||
            e.target.closest("#regApartmentSearch") || e.target.closest("#regBlock") ||
            e.target.closest("#regFloor") || e.target.closest("#regDoorNo");
        if (!inside) closeAllDropdowns();
    });

    document.addEventListener("click", function (e) {
        const btn = e.target.closest(".pw-toggle");
        if (!btn) return;
        const targetId = btn.getAttribute("data-target");
        const input = document.getElementById(targetId);
        if (!input) return;
        const eyeOpen = btn.querySelector(".eye-open");
        const eyeClosed = btn.querySelector(".eye-closed");
        if (input.type === "password") {
            input.type = "text";
            if (eyeOpen) eyeOpen.style.display = "none";
            if (eyeClosed) eyeClosed.style.display = "";
        } else {
            input.type = "password";
            if (eyeOpen) eyeOpen.style.display = "";
            if (eyeClosed) eyeClosed.style.display = "none";
        }
    });

    function paintCartUI(count, total) {
        count = Number(count) || 0;
        total = Number(total) || 0;
        state.cartCount = count;
        window.CART_COUNT = count;

        if (navCartCount) navCartCount.textContent = count;
        document.querySelectorAll(".js-cart-count, [data-cart-count]").forEach(el => {
            el.textContent = count;
        });

        const bar = document.getElementById("stickyCartBar");
        const stickyC = document.getElementById("stickyCartCount");
        const stickyT = document.getElementById("stickyCartTotal");
        const stickyLb = document.getElementById("stickyCartItemLabel");
        const itemLbl = bar ? bar.querySelector(".mm-cart-items span:last-child") : null;

        if (stickyC) stickyC.textContent = count;
        if (stickyT) stickyT.textContent = "₹" + Math.round(total);
        if (stickyLb) stickyLb.textContent = count === 1 ? "item" : "items";
        if (itemLbl) itemLbl.textContent = count === 1 ? "item" : "items";
        if (bar) bar.style.display = count > 0 ? "flex" : "none";

        const headCount = document.getElementById("cartHeaderCount");
        const headPlural = document.getElementById("cartHeaderPlural");
        if (headCount) headCount.textContent = count;
        if (headPlural) headPlural.textContent = count === 1 ? "" : "s";

        const sumSub = document.getElementById("summarySubtotal");
        const sumTotal = document.getElementById("summaryTotal");
        if (sumSub) sumSub.textContent = "₹" + Math.round(total);
        if (sumTotal) sumTotal.textContent = "₹" + Math.round(total);
    }
    function updateCartBadges(count, total) { paintCartUI(count, total); }

    let cartInflight = null;
    function refreshCartUI() {
        if (cartInflight) return cartInflight;
        cartInflight = fetch(MAIN_URL + "ajax/cart-count.php", {
            credentials: "same-origin", cache: "no-store"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                cartInflight = null;
                if (res && res.success && res.data) {
                    paintCartUI(res.data.cart_count, res.data.cart_total);
                }
                return res;
            })
            .catch(() => { cartInflight = null; return null; });
        return cartInflight;
    }
    window.refreshCartUI = refreshCartUI;
    window.paintCartUI = paintCartUI;

    document.addEventListener("visibilitychange", () => { if (!document.hidden) refreshCartUI(); });
    window.addEventListener("focus", refreshCartUI);
    window.addEventListener("pageshow", (e) => { if (e.persisted) refreshCartUI(); });
    document.addEventListener("DOMContentLoaded", refreshCartUI);
    if (document.readyState !== "loading") refreshCartUI();

    /* ---------- LOGIN ---------- */
    const loginTitle = document.getElementById("loginTitle");
    const loginSubtitle = document.getElementById("loginSubtitle");
    const loginMobileField = document.getElementById("loginMobileField");
    const loginPendingBox = document.getElementById("loginPendingBox");
    const loginPendingText = document.getElementById("loginPendingText");
    const loginPendingCloseBtn = document.getElementById("loginPendingCloseBtn");
    const loginForgotRow = document.getElementById("loginForgotRow");
    const loginForgotBtn = document.getElementById("loginForgotBtn");

    const forgotOverlay = document.getElementById("forgotOverlay");
    const forgotForm = document.getElementById("forgotForm");
    const forgotMobile = document.getElementById("forgotMobile");
    const forgotBtn = document.getElementById("forgotBtn");
    const forgotBtnText = document.getElementById("forgotBtnText");
    const forgotClose = document.getElementById("forgotClose");

    function resetLoginUI() {
        if (loginTitle) loginTitle.textContent = "Enter your mobile";
        if (loginSubtitle) loginSubtitle.textContent = "We'll check if you're already with us";
        if (loginMobileField) loginMobileField.style.display = "";
        if (loginPendingBox) loginPendingBox.style.display = "none";
        if (loginForgotRow) loginForgotRow.style.display = "none";
        if (loginPasswordField) loginPasswordField.style.display = "none";
        if (loginPassword) loginPassword.value = "";
        if (loginBtn) loginBtn.style.display = "";
        if (loginBtnText) loginBtnText.textContent = "Continue";
        if (loginBtn) loginBtn.disabled = false;
        state.lastLookedMobile = "";
        state.lastAppliedMobile = "";
        state.lookupInFlight = false;
    }

    function openLogin() {
        resetLoginUI();
        if (loginMobile) loginMobile.value = "";
        openOverlay(loginOverlay);
        setTimeout(() => loginMobile && loginMobile.focus(), 200);
    }
    function closeLogin() { closeOverlay(loginOverlay); }
    if (loginClose) loginClose.addEventListener("click", closeLogin);
    if (loginOverlay) loginOverlay.addEventListener("click", e => { if (e.target === loginOverlay) closeLogin(); });
    if (navLoginBtn) navLoginBtn.addEventListener("click", openLogin);

    /* ✅ CHANGED: input + blur with duplicate / stale guards */
    if (loginMobile) {
        loginMobile.addEventListener("input", function () {
            this.value = this.value.replace(/[^0-9]/g, "").slice(0, 15);

            clearTimeout(state.lookupTimer);

            const m = this.value.trim();

            // Collapse any previously-shown password / pending state
            if (loginPasswordField) loginPasswordField.style.display = "none";
            if (loginPassword) loginPassword.value = "";
            if (loginForgotRow) loginForgotRow.style.display = "none";
            if (loginBtn) loginBtn.style.display = "";
            if (loginBtnText) loginBtnText.textContent = "Continue";
            if (loginBtn) loginBtn.disabled = false;

            state.lastAppliedMobile = "";

            if (m.length < 10) {
                state.lastLookedMobile = "";
                return;
            }
            if (m === state.lastLookedMobile) return;

            state.lookupTimer = setTimeout(() => {
                autoLookupMobile(m, { silent: false });
            }, 400);
        });

        loginMobile.addEventListener("blur", function () {
            const m = this.value.trim();
            if (!/^[0-9]{10,15}$/.test(m)) return;
            clearTimeout(state.lookupTimer);

            // Skip if lookup already fired / applied for this exact number
            if (m === state.lastLookedMobile && (state.lookupInFlight || m === state.lastAppliedMobile)) {
                return;
            }
            autoLookupMobile(m, { silent: false });
        });
    }

    /* ✅ CHANGED: token-guarded lookup (no stale / duplicate responses) */
    function autoLookupMobile(mobile, opts) {
        opts = opts || {};
        mobile = (mobile || "").trim();
        if (!/^[0-9]{10,15}$/.test(mobile)) return;

        if (mobile === state.lastAppliedMobile && !opts.force) return;
        if (state.lookupInFlight && mobile === state.lastLookedMobile) return;

        state.lastLookedMobile = mobile;
        const myToken = ++state.lookupToken;
        state.lookupInFlight = true;

        if (loginBtn) loginBtn.disabled = true;
        if (loginBtnText && !opts.silent) loginBtnText.textContent = "Checking…";

        fetch(MAIN_URL + "ajax/customer-lookup.php?mobile=" + encodeURIComponent(mobile), {
            credentials: "same-origin",
            cache: "no-store"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (myToken !== state.lookupToken) return;              // stale
                const currentMobile = loginMobile ? (loginMobile.value || "").trim() : "";
                if (currentMobile !== mobile) return;                   // user edited

                state.lookupInFlight = false;
                if (loginBtn) loginBtn.disabled = false;

                if (!res || !res.success || !res.data) {
                    if (loginBtnText) loginBtnText.textContent = "Continue";
                    return;
                }

                state.lastAppliedMobile = mobile;
                applyLookupResult(mobile, res.data, { silent: !!opts.silent });
            })
            .catch(() => {
                if (myToken !== state.lookupToken) return;
                state.lookupInFlight = false;
                if (loginBtn) loginBtn.disabled = false;
                if (loginBtnText) loginBtnText.textContent = "Continue";
            });
    }

    /* ✅ CHANGED: apply result immediately, focus via requestAnimationFrame */
    function applyLookupResult(mobile, data, opts) {
        opts = opts || {};
        if (!data) return;

        // Case A: approved customer with password → show password field
        if (data.exists && data.has_password) {
            if (loginMobileField) loginMobileField.style.display = "";
            if (loginPendingBox) loginPendingBox.style.display = "none";
            if (loginTitle) loginTitle.textContent = "Welcome back";
            if (loginSubtitle) loginSubtitle.textContent = "Enter your password to continue";
            if (loginForgotRow) loginForgotRow.style.display = "";

            if (loginPasswordField && loginPasswordField.style.display === "none") {
                loginPasswordField.style.display = "";
            }

            if (loginBtn) loginBtn.style.display = "";
            if (loginBtnText) loginBtnText.textContent = "Login";

            if (loginPassword && !opts.silent) {
                requestAnimationFrame(() => {
                    loginPassword.focus();
                });
            }
            return;
        }

        // Case B: existing customer without password → go to register
        if (data.exists && !data.has_password) {
            closeLogin();
            openRegister(mobile, data);
            return;
        }

        // Case C: pending request → show pending box
        if (!data.exists && data.has_pending_request) {
            if (loginPasswordField) loginPasswordField.style.display = "none";
            if (loginForgotRow) loginForgotRow.style.display = "none";
            if (loginBtn) loginBtn.style.display = "none";
            if (loginTitle) loginTitle.textContent = "Almost there!";
            if (loginSubtitle) loginSubtitle.textContent = "Your request is being reviewed";
            if (loginPendingBox) loginPendingBox.style.display = "block";
            if (loginPendingText) {
                const pr = data.pending_request || {};
                const what = pr.requested_apartment || "your address";
                loginPendingText.innerHTML =
                    "You already sent a request for <strong>" + escapeHtml(what) + "</strong>." +
                    "<br>Admin approval is pending. We'll contact you on <strong>" +
                    escapeHtml(mobile) + "</strong> soon.";
            }
            return;
        }

        // Case D: brand new number → register
        closeLogin();
        openRegister(mobile, null);
    }

    /* Pending popup close */
    if (loginPendingCloseBtn) {
        loginPendingCloseBtn.addEventListener("click", function () {
            if (loginPendingBox) loginPendingBox.style.display = "none";
            if (loginBtn) loginBtn.style.display = "";
            resetLoginUI();
        });
    }

    /* Login submit */
    if (loginForm) {
        loginForm.addEventListener("submit", function (e) {
            e.preventDefault();
            const mobile = (loginMobile.value || "").trim();
            const password = (loginPassword.value || "").trim();
            if (!/^[0-9]{10,15}$/.test(mobile)) { sToast("Mobile must be 10–15 digits.", "error"); return; }

            const passwordVisible = loginPasswordField && loginPasswordField.style.display !== "none";

            if (passwordVisible) {
                if (!password) { sToast("Please enter your password.", "error"); return; }
                loginBtn.disabled = true; loginBtnText.innerHTML = '...';
                const fd = new FormData();
                fd.append("mobile_number", mobile);
                fd.append("password", password);
                fetch(MAIN_URL + "ajax/customer-login.php", { method: "POST", body: fd, credentials: "same-origin" })
                    .then(r => r.json().catch(() => null))
                    .then(res => {
                        loginBtn.disabled = false; loginBtnText.textContent = "Login";
                        if (!res || !res.success) { sToast((res && res.message) || "Login failed.", "error"); return; }
                        IS_LOGGED_IN = true; closeLogin(); sToast("Welcome back!", "success");
                        setTimeout(() => window.location.reload(), 800);
                    })
                    .catch(() => {
                        loginBtn.disabled = false; loginBtnText.textContent = "Login";
                        sToast("Unable to connect.", "error");
                    });
                return;
            }

            // No password field yet → lookup once (force so Continue always re-checks)
            autoLookupMobile(mobile, { force: true });
        });
    }

    /* ---------- FORGOT PASSWORD ---------- */
    function openForgot() {
        if (forgotMobile) forgotMobile.value = "";
        if (forgotBtn) forgotBtn.disabled = false;
        if (forgotBtnText) forgotBtnText.textContent = "Send Request";
        closeLogin();
        openOverlay(forgotOverlay);
        setTimeout(() => forgotMobile && forgotMobile.focus(), 200);
    }
    function closeForgot() { closeOverlay(forgotOverlay); }
    if (loginForgotBtn) loginForgotBtn.addEventListener("click", openForgot);
    if (forgotClose) forgotClose.addEventListener("click", closeForgot);
    if (forgotOverlay) forgotOverlay.addEventListener("click", e => { if (e.target === forgotOverlay) closeForgot(); });

    if (forgotMobile) {
        forgotMobile.addEventListener("input", function () {
            this.value = this.value.replace(/[^0-9]/g, "").slice(0, 15);
        });
    }
    if (forgotForm) {
        forgotForm.addEventListener("submit", function (e) {
            e.preventDefault();
            const mobile = (forgotMobile.value || "").trim();
            if (!/^[0-9]{10,15}$/.test(mobile)) { sToast("Mobile must be 10–15 digits.", "error"); return; }

            forgotBtn.disabled = true; forgotBtnText.innerHTML = 'Sending…';
            const fd = new FormData();
            fd.append("mobile_number", mobile);
            fetch(MAIN_URL + "ajax/forgot-password-request.php", { method: "POST", body: fd, credentials: "same-origin" })
                .then(r => r.json().catch(() => null))
                .then(res => {
                    forgotBtn.disabled = false; forgotBtnText.textContent = "Send Request";
                    if (!res || !res.success) {
                        sToast((res && res.message) || "Could not send request.", "error");
                        return;
                    }
                    closeForgot();
                    sModal("Request Sent", res.message || "Our team will contact you shortly.", "success");
                })
                .catch(() => {
                    forgotBtn.disabled = false; forgotBtnText.textContent = "Send Request";
                    sToast("Unable to connect.", "error");
                });
        });
    }

    /* ---------- REGISTER ---------- */
    function setRegMode(mode) {
        state.regMode = mode;
        if (btnAptMode) btnAptMode.classList.toggle("active", mode === "apartment");
        if (btnHouseMode) btnHouseMode.classList.toggle("active", mode === "individual_house");
        if (regAptBlock) regAptBlock.style.display = mode === "apartment" ? "" : "none";
        if (regHouseBlock) regHouseBlock.style.display = mode === "individual_house" ? "" : "none";
    }
    if (btnAptMode) btnAptMode.addEventListener("click", () => setRegMode("apartment"));
    if (btnHouseMode) btnHouseMode.addEventListener("click", () => setRegMode("individual_house"));

    function setLocState(opts) {
        const { input, btn, btnText, hint, actions } = opts;
        if (opts.hasLink) {
            if (input) input.value = opts.link || "";
            if (btn) { btn.disabled = true; btn.classList.add("success"); }
            if (btnText) btnText.textContent = "Location Pinned ✓";
            if (hint) hint.textContent = "Location saved. Use Edit / Undo below.";
            if (actions) actions.style.display = "flex";
        } else {
            if (input) input.value = "";
            if (btn) { btn.disabled = false; btn.classList.remove("success"); }
            if (btnText) btnText.textContent = "Get My Current Location";
            if (hint) hint.textContent = "";
            if (actions) actions.style.display = "none";
        }
    }

    function requestLocation(opts) {
        const { btn, btnText, hint } = opts;
        if (!navigator.geolocation) {
            if (hint) hint.textContent = "Browser does not support location.";
            return;
        }
        if (btn) btn.disabled = true;
        if (btnText) btnText.textContent = "Fetching location…";
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                const link = "https://www.google.com/maps?q=" + pos.coords.latitude + "," + pos.coords.longitude;
                opts.onSuccess(link);
                setLocState(Object.assign({}, opts, { hasLink: true, link: link }));
            },
            function () {
                if (btn) btn.disabled = false;
                if (btnText) btnText.textContent = "Get My Current Location";
                if (hint) hint.textContent = "Permission denied or unavailable.";
            },
            { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
        );
    }

    if (regAptLocBtn) {
        regAptLocBtn.addEventListener("click", function () {
            requestLocation({
                input: regAptMap, btn: regAptLocBtn, btnText: regAptLocText,
                hint: regAptLocHint, actions: regAptLocActions,
                onSuccess: function (link) { state.regAptMapLink = link; }
            });
        });
    }
    if (regAptLocEdit) {
        regAptLocEdit.addEventListener("click", function () {
            if (state.regAptMapLink) { window.open(state.regAptMapLink, "_blank"); }
        });
    }
    if (regAptLocUndo) {
        regAptLocUndo.addEventListener("click", function () {
            state.regAptMapLink = "";
            setLocState({
                input: regAptMap, btn: regAptLocBtn, btnText: regAptLocText,
                hint: regAptLocHint, actions: regAptLocActions, hasLink: false
            });
        });
    }

    if (regHouseLocBtn) {
        regHouseLocBtn.addEventListener("click", function () {
            requestLocation({
                input: regHouseMap, btn: regHouseLocBtn, btnText: regHouseLocText,
                hint: regHouseLocHint, actions: regHouseLocActions,
                onSuccess: function (link) { state.regHouseMapLink = link; }
            });
        });
    }
    if (regHouseLocEdit) {
        regHouseLocEdit.addEventListener("click", function () {
            if (state.regHouseMapLink) { window.open(state.regHouseMapLink, "_blank"); }
        });
    }
    if (regHouseLocUndo) {
        regHouseLocUndo.addEventListener("click", function () {
            state.regHouseMapLink = "";
            setLocState({
                input: regHouseMap, btn: regHouseLocBtn, btnText: regHouseLocText,
                hint: regHouseLocHint, actions: regHouseLocActions, hasLink: false
            });
        });
    }

    if (regAptImgDrop) regAptImgDrop.addEventListener("click", () => { if (regAptImg) regAptImg.click(); });
    if (regAptImg) {
        regAptImg.addEventListener("change", function () {
            const file = this.files && this.files[0];
            if (!file) return;
            if (!/^image\//.test(file.type)) { sToast("Please choose an image file.", "error"); return; }
            if (file.size > 3 * 1024 * 1024) { sToast("Image must be under 3 MB.", "error"); return; }
            const reader = new FileReader();
            reader.onload = function (evt) {
                state.regAptImgData = evt.target.result;
                if (regAptImgPrev) {
                    regAptImgPrev.classList.remove("img-drop-empty");
                    regAptImgPrev.innerHTML = '<img src="' + state.regAptImgData + '" alt="Preview">';
                }
            };
            reader.readAsDataURL(file);
        });
    }

    if (regHouseImgDrop) regHouseImgDrop.addEventListener("click", () => { if (regHouseImg) regHouseImg.click(); });
    if (regHouseImg) {
        regHouseImg.addEventListener("change", function () {
            const file = this.files && this.files[0];
            if (!file) return;
            if (!/^image\//.test(file.type)) { sToast("Please choose an image file.", "error"); return; }
            if (file.size > 3 * 1024 * 1024) { sToast("Image must be under 3 MB.", "error"); return; }
            const reader = new FileReader();
            reader.onload = function (evt) {
                state.regHouseImgData = evt.target.result;
                if (regHouseImgPrev) {
                    regHouseImgPrev.classList.remove("img-drop-empty");
                    regHouseImgPrev.innerHTML = '<img src="' + state.regHouseImgData + '" alt="Preview">';
                }
            };
            reader.readAsDataURL(file);
        });
    }

    function openRegister(mobile, customerData) {
        if (regMobile) regMobile.value = mobile || "";
        if (regName) regName.value = (customerData && customerData.name) ? customerData.name : "";
        if (regPassword) regPassword.value = "";
        if (regPasswordField) regPasswordField.style.display = "";
        if (regReferrer) regReferrer.value = "";

        closeAllDropdowns();
        state.customApartment = false;
        state.currentVariants = [];
        state.selectedCharge = null;

        if (regBlock) regBlock.value = "";
        if (regFloor) regFloor.value = "";
        if (regDoorNo) regDoorNo.value = "";
        if (regVariantHint) regVariantHint.textContent = "";

        if (regAptSearch) regAptSearch.value = "";
        if (regAptId) regAptId.value = "";
        if (regAptCode) regAptCode.value = "";
        if (regAptHint) regAptHint.textContent = "";
        if (regAptAddress) regAptAddress.value = "";

        if (regHouseLandmark) regHouseLandmark.value = "";
        if (regHouseLandmark2) regHouseLandmark2.value = "";

        state.regAptMapLink = "";
        setLocState({
            input: regAptMap, btn: regAptLocBtn, btnText: regAptLocText,
            hint: regAptLocHint, actions: regAptLocActions, hasLink: false
        });
        if (regAptImg) regAptImg.value = "";
        if (regAptImgPrev) {
            regAptImgPrev.classList.add("img-drop-empty");
            regAptImgPrev.innerHTML = '<span>Tap to upload a photo</span>';
        }
        state.regAptImgData = "";

        if (regHouseAddress) regHouseAddress.value = "";
        state.regHouseMapLink = "";
        setLocState({
            input: regHouseMap, btn: regHouseLocBtn, btnText: regHouseLocText,
            hint: regHouseLocHint, actions: regHouseLocActions, hasLink: false
        });
        if (regHouseImg) regHouseImg.value = "";
        if (regHouseImgPrev) {
            regHouseImgPrev.classList.add("img-drop-empty");
            regHouseImgPrev.innerHTML = '<span>Tap to upload a photo</span>';
        }
        state.regHouseImgData = "";

        setRegMode("apartment");
        loadRegisterApartments(function () { });

        openOverlay(regOverlay);
        setTimeout(() => regName && regName.focus(), 200);
    }
    function closeRegister() { closeOverlay(regOverlay); }
    if (regClose) regClose.addEventListener("click", closeRegister);
    if (regOverlay) regOverlay.addEventListener("click", e => { if (e.target === regOverlay) closeRegister(); });

    function loadRegisterApartments(cb) {
        fetch(MAIN_URL + "ajax/get-appartment-divisions.php", { credentials: "same-origin" })
            .then(r => r.json().catch(() => null))
            .then(res => {
                state.apartmentsCache = (res && res.success && Array.isArray(res.data)) ? res.data : [];
                if (typeof cb === "function") cb();
            })
            .catch(() => { state.apartmentsCache = []; if (typeof cb === "function") cb(); });
    }

    function renderRegAptList(query) {
        if (!regAptList) return;
        if (state.apartmentsCache.length === 0) {
            regAptList.innerHTML = `<div style="padding:14px;text-align:center;font-size:12px;color:#948c82;">No apartments in the system. Type your apartment name — it will be sent as a request.</div>`;
            regAptList.style.display = "block";
            return;
        }
        const q = (query || "").trim().toLowerCase();
        let list = state.apartmentsCache;
        if (q) list = list.filter(a =>
            (a.apartment_name || "").toLowerCase().includes(q) ||
            (a.apartment_code || "").toLowerCase().includes(q)
        );
        if (!list.length) {
            regAptList.innerHTML = `<div style="padding:14px;text-align:center;font-size:12px;color:#948c82;">No matches. You can type your own — it will be sent as a request.</div>`;
            regAptList.style.display = "block";
            return;
        }
        regAptList.innerHTML = list.slice(0, 40).map(a => `
            <div class="mm-co-dd-item" data-id="${a.id}" data-code="${escapeHtml(a.apartment_code)}" data-name="${escapeHtml(a.apartment_name)}">
                <strong>${escapeHtml(a.apartment_name)}</strong>
                <span>${escapeHtml(a.apartment_address || '')}</span>
            </div>
        `).join("");
        regAptList.style.display = "block";

        regAptList.querySelectorAll(".mm-co-dd-item").forEach(item => {
            item.addEventListener("click", function () {
                if (regAptSearch) regAptSearch.value = this.dataset.name;
                if (regAptId) regAptId.value = this.dataset.id;
                if (regAptCode) regAptCode.value = this.dataset.code;

                const apt = state.apartmentsCache.find(a => String(a.id) === String(this.dataset.id));
                if (apt) selectApartmentForVariant(apt);

                closeAllDropdowns();
                setTimeout(() => regBlock && regBlock.focus(), 100);
            });
        });
    }

    function normalizeVariant(d) {
        if (!d || typeof d !== "object") return null;
        let block = d.block || "", floor = d.floor || "", doorNo = d.door_no || "";
        if (!block && !floor && !doorNo && d.division !== undefined) doorNo = String(d.division || "");
        if (!block && !floor && !doorNo) return null;
        return { block: String(block), floor: String(floor), door_no: String(doorNo), charge: Number(d.charge || 0) };
    }

    function selectApartmentForVariant(apt) {
        const raw = Array.isArray(apt.divisions) ? apt.divisions : [];
        state.currentVariants = raw.map(normalizeVariant).filter(Boolean);
        state.customApartment = false;
        if (regBlock) regBlock.value = "";
        if (regFloor) regFloor.value = "";
        if (regDoorNo) regDoorNo.value = "";
        if (regVariantHint) {
            regVariantHint.textContent = state.currentVariants.length
                ? "Pick block, floor, door no — or type a new combination."
                : "No saved combinations — type block / floor / door no. New values will be sent as a request.";
        }
        state.selectedCharge = null;
    }

    function uniqueValues(arr, key) {
        const seen = {}, out = [];
        arr.forEach(v => {
            const val = String(v[key] || "").trim();
            if (!val) return;
            const k = val.toLowerCase();
            if (seen[k]) return;
            seen[k] = true; out.push(val);
        });
        return out.sort();
    }

    function renderVariantDropdown(inputEl, listEl, items, currentValue, onPick) {
        if (!listEl) return;
        const q = (currentValue || "").trim().toLowerCase();
        const filtered = q
            ? items.filter(v => v.toLowerCase().includes(q))
            : items;

        if (!filtered.length) {
            if (q) {
                listEl.innerHTML =
                    `<div class="mm-co-dd-item mm-co-dd-custom" data-value="${escapeHtml(currentValue)}">
                    <strong>Use "${escapeHtml(currentValue)}"</strong>
                    <span>New value — will be sent to admin</span>
                </div>`;
                listEl.style.display = "block";
                listEl.onclick = function (e) {
                    const item = e.target.closest(".mm-co-dd-item");
                    if (!item) return;
                    inputEl.value = item.dataset.value;
                    closeAllDropdowns();
                    onPick(item.dataset.value);
                };
            } else {
                listEl.style.display = "none";
            }
            return;
        }

        listEl.innerHTML = filtered.slice(0, 60).map(v =>
            `<div class="mm-co-dd-item" data-value="${escapeHtml(v)}">${escapeHtml(v)}</div>`
        ).join("");

        listEl.style.display = "block";
        listEl.onclick = function (e) {
            const item = e.target.closest(".mm-co-dd-item");
            if (!item) return;
            inputEl.value = item.dataset.value;
            closeAllDropdowns();
            onPick(item.dataset.value);
        };
    }

    function updateVariantCharge() {
        const block = (regBlock && regBlock.value || "").trim();
        const floor = (regFloor && regFloor.value || "").trim();
        const doorNo = (regDoorNo && regDoorNo.value || "").trim();
        state.selectedCharge = null;

        if (!block || !floor || !doorNo) {
            if (regVariantHint) regVariantHint.textContent = "Fill block, floor and door no.";
            return;
        }

        const match = state.currentVariants.find(v =>
            v.block.toLowerCase() === block.toLowerCase() &&
            v.floor.toLowerCase() === floor.toLowerCase() &&
            v.door_no.toLowerCase() === doorNo.toLowerCase()
        );

        if (match) {
            state.selectedCharge = match.charge;
            if (regVariantHint) regVariantHint.textContent = "Delivery charge: ₹" + Math.round(match.charge);
        } else {
            if (regVariantHint) {
                regVariantHint.textContent =
                    "New combination (“Blk " + block + " / Door " + doorNo + " / Fl " + floor +
                    "”) — will be sent as a request.";
            }
        }
    }

    if (regBlock) {
        regBlock.addEventListener("focus", function () {
            if (state.customApartment) return;
            const blocks = uniqueValues(state.currentVariants, "block");
            renderVariantDropdown(regBlock, regBlockList, blocks, regBlock.value, function (val) {
                regBlock.value = val;
                if (regFloor) regFloor.value = "";
                if (regDoorNo) regDoorNo.value = "";
                if (regVariantHint) regVariantHint.textContent = "Now pick floor.";
                setTimeout(() => regFloor && regFloor.focus(), 100);
            });
        });
        regBlock.addEventListener("blur", function () {
            if (regBlock.value.trim() !== "" && !state.currentVariants.some(v => v.block.toLowerCase() === regBlock.value.trim().toLowerCase())) {
                setTimeout(closeAllDropdowns, 4500);
            } else {
                setTimeout(closeAllDropdowns, 150);
            }
            updateVariantCharge();
        });
    }

    if (regFloor) {
        regFloor.addEventListener("focus", function () {
            if (state.customApartment) return;
            const block = (regBlock && regBlock.value || "").trim().toLowerCase();
            let source = state.currentVariants;
            if (block) source = source.filter(v => v.block.toLowerCase() === block);
            const floors = uniqueValues(source, "floor");
            renderVariantDropdown(regFloor, regFloorList, floors, regFloor.value, function (val) {
                regFloor.value = val;
                if (regDoorNo) regDoorNo.value = "";
                if (regVariantHint) regVariantHint.textContent = "Now pick door no.";
                setTimeout(() => regDoorNo && regDoorNo.focus(), 100);
            });
        });
        regFloor.addEventListener("blur", function () {
            const block = (regBlock && regBlock.value || "").trim().toLowerCase();
            let source = state.currentVariants;
            if (block) source = source.filter(v => v.block.toLowerCase() === block);
            const floors = uniqueValues(source, "floor");

            if (regFloor.value.trim() !== "" && !floors.some(f => f.toLowerCase() === regFloor.value.trim().toLowerCase())) {
                setTimeout(closeAllDropdowns, 4500);
            } else {
                setTimeout(closeAllDropdowns, 150);
            }
            updateVariantCharge();
        });
    }

    if (regDoorNo) {
        regDoorNo.addEventListener("focus", function () {
            if (state.customApartment) return;
            const block = (regBlock && regBlock.value || "").trim().toLowerCase();
            const floor = (regFloor && regFloor.value || "").trim().toLowerCase();
            let source = state.currentVariants;
            if (block) source = source.filter(v => v.block.toLowerCase() === block);
            if (floor) source = source.filter(v => v.floor.toLowerCase() === floor);
            const doorNos = uniqueValues(source, "door_no");
            renderVariantDropdown(regDoorNo, regDoorNoList, doorNos, regDoorNo.value, function (val) {
                regDoorNo.value = val;
                updateVariantCharge();
                setTimeout(closeAllDropdowns, 150);
            });
        });
        regDoorNo.addEventListener("blur", function () {
            const block = (regBlock && regBlock.value || "").trim().toLowerCase();
            const floor = (regFloor && regFloor.value || "").trim().toLowerCase();
            let source = state.currentVariants;
            if (block) source = source.filter(v => v.block.toLowerCase() === block);
            if (floor) source = source.filter(v => v.floor.toLowerCase() === floor);
            const doorNos = uniqueValues(source, "door_no");

            if (regDoorNo.value.trim() !== "" && !doorNos.some(d => d.toLowerCase() === regDoorNo.value.trim().toLowerCase())) {
                setTimeout(closeAllDropdowns, 4500);
            } else {
                setTimeout(closeAllDropdowns, 150);
            }
            updateVariantCharge();
        });
    }

    if (regAptSearch) {
        regAptSearch.addEventListener("focus", function () {
            renderRegAptList(regAptSearch.value);
        });

        regAptSearch.addEventListener("input", function () {
            const typed = this.value.trim();
            if (regAptId) regAptId.value = "";
            if (regAptCode) regAptCode.value = "";

            const exact = state.apartmentsCache.find(a =>
                (a.apartment_name || "").toLowerCase() === typed.toLowerCase()
            );

            if (exact) {
                if (regAptId) regAptId.value = exact.id;
                if (regAptCode) regAptCode.value = exact.apartment_code;
                selectApartmentForVariant(exact);
                state.customApartment = false;
                if (regAptHint) regAptHint.textContent = "";
            } else if (typed.length > 0) {
                state.customApartment = true;
                state.currentVariants = [];
                if (regAptHint) {
                    regAptHint.textContent =
                        "“" + typed + "” is a new apartment — it will be sent as a request.";
                }
            } else {
                state.customApartment = false;
                if (regAptHint) regAptHint.textContent = "";
            }

            renderRegAptList(this.value);
        });

        regAptSearch.addEventListener("blur", function () {
            if (regAptSearch.value.trim() !== "" && state.customApartment) {
                setTimeout(() => {
                    if (regAptList) regAptList.style.display = "none";
                }, 4500);
            } else {
                setTimeout(() => {
                    if (regAptList) regAptList.style.display = "none";
                }, 150);
            }
        });
    }

    /* ---------- REGISTER SUBMIT ---------- */
    if (regForm) {
        regForm.addEventListener("submit", function (e) {
            e.preventDefault();

            const mobile = regMobile ? (regMobile.value || "").trim() : "";
            const name = regName ? (regName.value || "").trim() : "";
            const password = regPassword ? (regPassword.value || "").trim() : "";
            const referrer = regReferrer ? (regReferrer.value || "").trim() : "";

            if (name.length < 3) { sModal("Invalid Name", "Name must be at least 3 characters.", "warning"); if (regName) regName.focus(); return; }
            if (password.length < 3) { sModal("Invalid Password", "Password must be at least 3 characters.", "warning"); if (regPassword) regPassword.focus(); return; }

            const fd = new FormData();
            fd.append("full_name", name);
            fd.append("mobile_number", mobile);
            fd.append("password", password);
            fd.append("referrer", referrer);

            if (state.regMode === "individual_house") {
                const houseAddr = regHouseAddress ? (regHouseAddress.value || "").trim() : "";
                const houseLandmarkVal = regHouseLandmark2 ? (regHouseLandmark2.value || "").trim() : "";

                if (!houseAddr) { sModal("Missing Address", "Full address is required.", "warning"); if (regHouseAddress) regHouseAddress.focus(); return; }

                fd.append("request_type", "individual_house");
                fd.append("house_address", houseAddr);
                fd.append("landmark", houseLandmarkVal);
                if (state.regHouseMapLink) fd.append("map_link", state.regHouseMapLink);
                if (state.regHouseImgData) fd.append("house_image_data", state.regHouseImgData);
            } else {
                const aptId = regAptId ? (regAptId.value || "").trim() : "";
                const aptText = regAptSearch ? (regAptSearch.value || "").trim() : "";
                const block = regBlock ? (regBlock.value || "").trim() : "";
                const floor = regFloor ? (regFloor.value || "").trim() : "";
                const doorNo = regDoorNo ? (regDoorNo.value || "").trim() : "";
                const landmark = regHouseLandmark ? (regHouseLandmark.value || "").trim() : "";
                const aptFullAddress = regAptAddress ? (regAptAddress.value || "").trim() : "";

                if (!aptText) { sModal("Missing Apartment", "Apartment is required.", "warning"); if (regAptSearch) regAptSearch.focus(); return; }
                if (!aptFullAddress) { sModal("Missing Address", "Please enter your full address.", "warning"); if (regAptAddress) regAptAddress.focus(); return; }

                const divisionText = (block || floor || doorNo)
                    ? "Blk " + block + " / Door " + doorNo + " / Fl " + floor
                    : "";

                if (aptId && aptId !== "0") fd.append("apartment_id", aptId);
                fd.append("apartment_name", aptText);
                fd.append("division", divisionText);

                if (block !== "" || floor !== "" || doorNo !== "") {
                    fd.append("block", block);
                    fd.append("floor", floor);
                    fd.append("door_no", doorNo);
                }

                fd.append("house_address", aptFullAddress);
                fd.append("landmark", landmark);
                if (state.regAptMapLink) fd.append("map_link", state.regAptMapLink);
                if (state.regAptImgData) fd.append("house_image_data", state.regAptImgData);

                if (!aptId || aptId === "0") {
                    fd.append("custom_apartment", aptText);
                    fd.append("custom_division", divisionText);
                }
            }

            if (regBtn) regBtn.disabled = true;
            if (regBtnText) regBtnText.innerHTML = 'Creating…';
            closeRegister();

            fetch(MAIN_URL + "ajax/customer-register.php", {
                method: "POST", body: fd, credentials: "same-origin"
            })
                .then(r => r.json().catch(() => null))
                .then(res => {
                    if (regBtn) regBtn.disabled = false;
                    if (regBtnText) regBtnText.textContent = "Create Account";

                    if (!res || !res.success) {
                        sModal("Registration Failed", (res && res.message) || "Please try again.", "error");
                        return;
                    }

                    // Request-only → 10-second auto-close SweetAlert
                    if (res.data && res.data.request_only) {
                        const extra = res.data.already_requested
                            ? "<br><br>You already sent a request earlier — we'll keep that one."
                            : "";
                        sModalAuto(
                            "Request Submitted",
                            (res.message || "Our team will review and contact you soon.") + extra,
                            "success",
                            10
                        );
                        return;
                    }

                    IS_LOGGED_IN = true;
                    if (res.data && res.data.new_division_added) {
                        sToast("Account created!", "success");
                    } else {
                        sToast("Account created! Welcome!", "success");
                    }
                    setTimeout(() => window.location.reload(), 1000);
                })
                .catch(() => {
                    if (regBtn) regBtn.disabled = false;
                    if (regBtnText) regBtnText.textContent = "Create Account";
                    sModal("Network Error", "Unable to connect. Please try again.", "error");
                });
        });
    }

    /* =========================================================
       PRODUCT CARDS & CART (STOCK-AWARE + OOS-SAFE)
       ========================================================= */
    const productCards = document.querySelectorAll(".js-product-card");

    function variantsBoxReset(card) {
        const variantsBox = card.querySelector(".mm-variants");
        if (!variantsBox) return;
        variantsBox.dataset.loaded = "0";
        variantsBox.innerHTML = '<div class="mm-variants-loading">Loading variants…</div>';
        loadVariantsForCard(card);
    }

    function loadVariantsForCard(card) {
        const variantsBox = card.querySelector(".mm-variants");
        if (!variantsBox || variantsBox.dataset.loaded === "1") return;
        const productId = Number(card.dataset.productId || 0);
        const menuCode = card.dataset.menuCode || "";
        if (productId <= 0) return;
        variantsBox.dataset.loaded = "1";

        let url = MAIN_URL + "ajax/get-product-variants.php?id=" + encodeURIComponent(productId);
        if (menuCode) url += "&menu_code=" + encodeURIComponent(menuCode);

        fetch(url, { credentials: "same-origin" })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success || !res.data || !Array.isArray(res.data.variants)) {
                    variantsBox.innerHTML = '<div class="mm-variants-loading">Failed.</div>';
                    return;
                }
                const variants = res.data.variants;
                if (!variants.length) {
                    variantsBox.innerHTML = '<div class="mm-variants-loading">No variants.</div>';
                    return;
                }

                let anyAvailable = false;

                variantsBox.innerHTML = variants.map(v => {
                    const qtyLabel = v.quantity_name || (v.quantity + " " + (v.quantity_unit || ""));
                    const cartQty = Number(v.cart_qty || 0);
                    const isUnlimited = Number(v.stock_unlimited) === 1;
                    const maxStock = isUnlimited ? 9999 : Math.max(0, Number(v.stock_count) || 0);
                    const available = isUnlimited ? 9999 : Math.max(0, maxStock - cartQty);
                    const isOOS = !isUnlimited && available <= 0;

                    if (!isOOS) anyAvailable = true;

                    return `
                        <div class="mm-variant-row ${isOOS ? 'is-out-of-stock' : ''}"
                             data-variant-id="${v.id}"
                             data-price="${Number(v.price)}"
                             data-original="${cartQty}"
                             data-max-stock="${isUnlimited ? 9999 : maxStock}">
                            <span class="mm-variant-label">
                                ${escapeHtml(qtyLabel)}
                                ${isOOS ? '<span class="mm-oos-badge">Sold Out</span>' : ''}
                            </span>
                            <div class="mm-qty" data-qty="${isOOS ? 0 : cartQty}" ${isOOS ? 'style="display:none"' : ''}>
                                <button type="button" class="js-qty-minus" ${cartQty === 0 || isOOS ? "disabled" : ""}>−</button>
                                <span class="mm-qty-val">${isOOS ? 0 : cartQty}</span>
                                <button type="button" class="js-qty-plus" ${isOOS || cartQty >= maxStock ? "disabled" : ""}>+</button>
                            </div>
                            <span class="mm-variant-price">Rs. ${Number(v.price).toFixed(0)}</span>
                        </div>
                    `;
                }).join("");

                variantsBox.querySelectorAll(".mm-variant-row").forEach(row => {
                    if (row.classList.contains("is-out-of-stock")) return;

                    const minus = row.querySelector(".js-qty-minus");
                    const plus = row.querySelector(".js-qty-plus");
                    const valEl = row.querySelector(".mm-qty-val");
                    const box = row.querySelector(".mm-qty");
                    const maxStock = Number(row.dataset.maxStock);

                    if (plus) plus.addEventListener("click", () => {
                        let q = Number(box.dataset.qty || 0);
                        if (q < maxStock) {
                            q++;
                            box.dataset.qty = q;
                            valEl.textContent = q;
                            if (minus) minus.disabled = false;
                            if (q >= maxStock) plus.disabled = true;
                            updateCardState(card);
                        }
                    });

                    if (minus) minus.addEventListener("click", () => {
                        let q = Number(box.dataset.qty || 0);
                        if (q > 0) q--;
                        box.dataset.qty = q;
                        valEl.textContent = q;
                        minus.disabled = q === 0;
                        if (q < maxStock) plus.disabled = false;
                        updateCardState(card);
                    });
                });

                const addBtn = card.querySelector(".js-add-cart");
                if (!anyAvailable && addBtn) {
                    card.classList.add("is-sold-out-all");
                    addBtn.disabled = true;
                    addBtn.classList.add("is-sold-out");
                    const lbl = addBtn.querySelector("span");
                    if (lbl) lbl.textContent = "SOLD OUT";
                } else {
                    card.classList.remove("is-sold-out-all");
                    if (addBtn) addBtn.classList.remove("is-sold-out");
                    updateCardState(card);
                }
            })
            .catch(() => {
                variantsBox.innerHTML = '<div class="mm-variants-loading">Error.</div>';
            });
    }

    function updateCardState(card) {
        const btn = card.querySelector(".js-add-cart");
        if (!btn) return;
        const btnText = btn.querySelector("span");
        const rows = card.querySelectorAll(".mm-variant-row");

        const allOOS = rows.length > 0 && Array.from(rows).every(r => r.classList.contains("is-out-of-stock"));
        if (allOOS) {
            card.classList.add("is-sold-out-all");
            btn.disabled = true;
            btn.classList.add("is-sold-out");
            if (btnText) btnText.textContent = "SOLD OUT";
            return;
        }

        card.classList.remove("is-sold-out-all");

        let anyChanged = false, anyQty = false;
        rows.forEach(row => {
            if (row.classList.contains("is-out-of-stock")) return;
            const qtyBox = row.querySelector(".mm-qty");
            if (!qtyBox) return;
            const cur = Number(qtyBox.dataset.qty || 0);
            const orig = Number(row.dataset.original || 0);
            if (cur > 0) anyQty = true;
            if (cur !== orig) anyChanged = true;
        });

        if (!anyQty && !anyChanged) {
            btn.disabled = true;
            if (btnText) btnText.textContent = "Add to Cart";
        } else if (anyChanged) {
            btn.disabled = false;
            if (btnText) btnText.textContent = "Update Cart";
        } else {
            btn.disabled = false;
            if (btnText) btnText.textContent = "Add to Cart";
        }
    }

    if ("IntersectionObserver" in window) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach(en => {
                if (en.isIntersecting) {
                    loadVariantsForCard(en.target);
                    io.unobserve(en.target);
                }
            });
        }, { rootMargin: "200px" });
        productCards.forEach(card => io.observe(card));
    } else {
        productCards.forEach(card => loadVariantsForCard(card));
    }

    document.querySelectorAll(".js-add-cart").forEach(btn => {
        btn.addEventListener("click", function () {
            const card = btn.closest(".js-product-card");
            if (!card) return;
            if (!IS_LOGGED_IN) { sToast("Please log in.", "error"); openLogin(); return; }
            const productId = Number(card.dataset.productId || 0);
            const menuCode = card.dataset.menuCode || "";
            const rows = card.querySelectorAll(".mm-variant-row");

            const items = [];
            rows.forEach(row => {
                if (row.classList.contains("is-out-of-stock")) return;
                const qtyBox = row.querySelector(".mm-qty");
                if (!qtyBox) return;
                const qty = Number(qtyBox.dataset.qty || 0);
                items.push({ variant_id: Number(row.dataset.variantId), qty: qty });
            });

            if (items.length === 0) {
                sToast("All variants are sold out.", "error");
                updateCardState(card);
                return;
            }

            btn.disabled = true;
            btn.classList.add("adding");
            const lbl = btn.querySelector("span");
            if (lbl) lbl.textContent = "Saving…";

            const fd = new FormData();
            fd.append("product_id", productId);
            fd.append("menu_code", menuCode);
            fd.append("items", JSON.stringify(items));

            fetch(MAIN_URL + "ajax/cart-sync.php", { method: "POST", body: fd, credentials: "same-origin" })
                .then(r => r.json().catch(() => null))
                .then(res => {
                    btn.classList.remove("adding");
                    if (!res || !res.success) {
                        sToast((res && res.message) || "Failed.", "error");
                        updateCardState(card);
                        return;
                    }
                    sToast("Cart updated.", "success");
                    if (res.data) {
                        updateCartBadges(
                            Number(res.data.cart_count || 0),
                            Number(res.data.cart_total || 0)
                        );
                    }
                    variantsBoxReset(card);
                })
                .catch(() => {
                    btn.classList.remove("adding");
                    sToast("Unable to connect.", "error");
                    updateCardState(card);
                });
        });
    });

})();