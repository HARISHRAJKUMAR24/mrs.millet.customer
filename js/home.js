/* =========================================================
   MRS MILL@ — CUSTOMER STORE
   File: ./js/home.js
   ========================================================= */

(function () {
    "use strict";

    const MAIN_URL = (typeof window.MAIN_URL === "string" && window.MAIN_URL) ? window.MAIN_URL : "./";
    let IS_LOGGED_IN = !!window.IS_LOGGED_IN;

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
    const regHouseImg = document.getElementById("regHouseImg");
    const regHouseImgDrop = document.getElementById("regHouseImgDrop");
    const regHouseImgPrev = document.getElementById("regHouseImgPreview");

    const navCartCount = document.getElementById("navCartCount");

    const state = {
        cartCount: Number(window.CART_COUNT || 0),
        apartmentsCache: [],
        lookupTimer: null,
        lastLookedMobile: "",
        customApartment: false,
        regMode: "apartment",
        regHouseImgData: "",
        regHouseMapLink: "",
        currentVariants: [],
        selectedCharge: null
    };

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

    /* ---------- GLOBAL: close all dropdowns when clicking outside ---------- */
    function closeAllDropdowns() {
        if (regAptList) regAptList.style.display = "none";
        if (regBlockList) regBlockList.style.display = "none";
        if (regFloorList) regFloorList.style.display = "none";
        if (regDoorNoList) regDoorNoList.style.display = "none";
    }

    document.addEventListener("click", function (e) {
        // If click is NOT on the dropdown or its input, close all dropdowns
        const isInsideDropdown =
            e.target.closest("#regApartmentList") ||
            e.target.closest("#regBlockList") ||
            e.target.closest("#regFloorList") ||
            e.target.closest("#regDoorNoList") ||
            e.target.closest("#regApartmentSearch") ||
            e.target.closest("#regBlock") ||
            e.target.closest("#regFloor") ||
            e.target.closest("#regDoorNo");

        if (!isInsideDropdown) {
            closeAllDropdowns();
        }
    });

    /* Password eye toggle */
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

    /* LOGIN */
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
    if (loginOverlay) loginOverlay.addEventListener("click", e => { if (e.target === loginOverlay) closeLogin(); });
    if (navLoginBtn) navLoginBtn.addEventListener("click", openLogin);

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
            if (m.length >= 10) { clearTimeout(state.lookupTimer); autoLookupMobile(m); }
        });
    }

    function autoLookupMobile(mobile) {
        state.lastLookedMobile = mobile;
        fetch(MAIN_URL + "ajax/customer-lookup.php?mobile=" + encodeURIComponent(mobile), { credentials: "same-origin" })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success || !res.data) return;
                if (res.data.has_pending_request) {
                    closeLogin();
                    showToast("You've already sent a request. Our team will contact you soon.", "error");
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
                }
            })
            .catch(() => { });
    }

    if (loginForm) {
        loginForm.addEventListener("submit", function (e) {
            e.preventDefault();
            const mobile = (loginMobile.value || "").trim();
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
            loginBtnText.innerHTML = '...';
            fetch(MAIN_URL + "ajax/customer-lookup.php?mobile=" + encodeURIComponent(mobile), { credentials: "same-origin" })
                .then(r => r.json().catch(() => null))
                .then(res => {
                    loginBtn.disabled = false;
                    loginBtnText.textContent = "Continue";
                    if (!res || !res.success || !res.data) {
                        showToast((res && res.message) || "Lookup failed.", "error");
                        return;
                    }
                    if (res.data.has_pending_request) {
                        closeLogin();
                        showToast("You've already sent a request. Our team will contact you soon.", "error");
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
        loginBtnText.innerHTML = '...';
        const fd = new FormData();
        fd.append("mobile_number", mobile);
        fd.append("password", password);
        fetch(MAIN_URL + "ajax/customer-login.php", { method: "POST", body: fd, credentials: "same-origin" })
            .then(r => r.json().catch(() => null))
            .then(res => {
                loginBtn.disabled = false;
                loginBtnText.textContent = "Login";
                if (!res || !res.success) { showToast((res && res.message) || "Login failed.", "error"); return; }
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

    /* REGISTER */
    function setRegMode(mode) {
        state.regMode = mode;
        if (btnAptMode) btnAptMode.classList.toggle("active", mode === "apartment");
        if (btnHouseMode) btnHouseMode.classList.toggle("active", mode === "individual_house");
        if (regAptBlock) regAptBlock.style.display = mode === "apartment" ? "" : "none";
        if (regHouseBlock) regHouseBlock.style.display = mode === "individual_house" ? "" : "none";
    }
    if (btnAptMode) btnAptMode.addEventListener("click", () => setRegMode("apartment"));
    if (btnHouseMode) btnHouseMode.addEventListener("click", () => setRegMode("individual_house"));

    if (regHouseLocBtn) {
        regHouseLocBtn.addEventListener("click", function () {
            if (!navigator.geolocation) {
                if (regHouseLocHint) regHouseLocHint.textContent = "Browser does not support location.";
                return;
            }
            regHouseLocBtn.disabled = true;
            if (regHouseLocText) regHouseLocText.textContent = "Fetching location…";
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    const link = "https://www.google.com/maps?q=" + pos.coords.latitude + "," + pos.coords.longitude;
                    state.regHouseMapLink = link;
                    if (regHouseMap) regHouseMap.value = link;
                    regHouseLocBtn.disabled = false;
                    regHouseLocBtn.classList.add("success");
                    if (regHouseLocText) regHouseLocText.textContent = "Location Pinned ✓";
                    if (regHouseLocHint) regHouseLocHint.textContent = "Location saved.";
                },
                function () {
                    regHouseLocBtn.disabled = false;
                    if (regHouseLocText) regHouseLocText.textContent = "Get My Current Location";
                },
                { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
            );
        });
    }
    if (regHouseImgDrop) regHouseImgDrop.addEventListener("click", () => { if (regHouseImg) regHouseImg.click(); });
    if (regHouseImg) {
        regHouseImg.addEventListener("change", function () {
            const file = this.files && this.files[0];
            if (!file) return;
            if (!/^image\//.test(file.type)) { showToast("Please choose an image file.", "error"); return; }
            if (file.size > 3 * 1024 * 1024) { showToast("Image must be under 3 MB.", "error"); return; }
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

        if (regHouseAddress) regHouseAddress.value = "";
        if (regHouseMap) regHouseMap.value = "";
        if (regHouseLocHint) regHouseLocHint.textContent = "";
        if (regHouseLocBtn) { regHouseLocBtn.disabled = false; regHouseLocBtn.classList.remove("success"); }
        if (regHouseLocText) regHouseLocText.textContent = "Get My Current Location";
        if (regHouseImg) regHouseImg.value = "";
        if (regHouseImgPrev) {
            regHouseImgPrev.classList.add("img-drop-empty");
            regHouseImgPrev.innerHTML = '<span>Tap to upload a photo</span>';
        }
        state.regHouseImgData = "";
        state.regHouseMapLink = "";

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
                // Auto-focus block after selecting an apartment
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
        if (regVariantHint) regVariantHint.textContent = state.currentVariants.length ? "Pick block, floor, door no." : "No variants.";
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
        const filtered = q ? items.filter(v => v.toLowerCase().includes(q)) : items;
        if (!filtered.length) {
            listEl.innerHTML = `<div style="padding:12px;text-align:center;font-size:12px;color:#948c82;">No matches. You can type your own.</div>`;
            listEl.style.display = "block"; return;
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
        if (!block || !floor || !doorNo) return;
        const match = state.currentVariants.find(v =>
            v.block.toLowerCase() === block.toLowerCase() &&
            v.floor.toLowerCase() === floor.toLowerCase() &&
            v.door_no.toLowerCase() === doorNo.toLowerCase()
        );
        if (match) {
            state.selectedCharge = match.charge;
            if (regVariantHint) regVariantHint.textContent = "Delivery charge: ₹" + Math.round(match.charge);
        } else {
            if (regVariantHint) regVariantHint.textContent = "Combination not found (will be sent as a request).";
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
            });
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
                if (regAptHint) regAptHint.textContent = "Custom apartment — will be sent as a request to admin.";
            } else {
                state.customApartment = false;
                if (regAptHint) regAptHint.textContent = "";
            }
            renderRegAptList(this.value);
        });
        regAptSearch.addEventListener("blur", function () {
            // Small delay so click on list item registers first
            setTimeout(() => {
                if (regAptList) regAptList.style.display = "none";
            }, 200);
        });
    }

    if (regForm) {
        regForm.addEventListener("submit", function (e) {
            e.preventDefault();

            const mobile = regMobile ? (regMobile.value || "").trim() : "";
            const name = regName ? (regName.value || "").trim() : "";
            const password = regPassword ? (regPassword.value || "").trim() : "";

            if (name.length < 3) {
                showToast("Name must be at least 3 characters.", "error");
                return;
            }
            if (password.length < 3) {
                showToast("Password must be at least 3 characters.", "error");
                if (regPassword) regPassword.focus();
                return;
            }

            const fd = new FormData();
            fd.append("full_name", name);
            fd.append("mobile_number", mobile);
            fd.append("password", password);

            if (state.regMode === "individual_house") {
                const houseAddr = regHouseAddress ? (regHouseAddress.value || "").trim() : "";
                if (!houseAddr) {
                    showToast("Full address is required.", "error");
                    return;
                }
                if (!state.regHouseMapLink) {
                    showToast("Please pin your location first.", "error");
                    return;
                }
                fd.append("request_type", "individual_house");
                fd.append("house_address", houseAddr);
                fd.append("map_link", state.regHouseMapLink);
                if (state.regHouseImgData) fd.append("house_image_data", state.regHouseImgData);
            } else {
                const aptId = regAptId ? (regAptId.value || "").trim() : "";
                const aptText = regAptSearch ? (regAptSearch.value || "").trim() : "";
                const block = regBlock ? (regBlock.value || "").trim() : "";
                const floor = regFloor ? (regFloor.value || "").trim() : "";
                const doorNo = regDoorNo ? (regDoorNo.value || "").trim() : "";

                if (!aptText) {
                    showToast("Apartment is required.", "error");
                    return;
                }
                if (!block || !floor || !doorNo) {
                    showToast("Please enter Block, Floor and Door No.", "error");
                    return;
                }

                if (aptId && aptId !== "0") {
                    fd.append("apartment_id", aptId);
                    fd.append("division", "Blk " + block + " / Door " + doorNo + " / Fl " + floor);
                } else {
                    fd.append("custom_apartment", aptText);
                    fd.append("custom_division", "Blk " + block + " / Door " + doorNo + " / Fl " + floor);
                }
            }

            if (regBtn) regBtn.disabled = true;
            if (regBtnText) regBtnText.innerHTML = 'Creating…';

            fetch(MAIN_URL + "ajax/customer-register.php", { method: "POST", body: fd, credentials: "same-origin" })
                .then(r => r.json().catch(() => null))
                .then(res => {
                    if (regBtn) regBtn.disabled = false;
                    if (regBtnText) regBtnText.textContent = "Create Account";

                    if (!res || !res.success) {
                        showToast((res && res.message) || "Failed.", "error");
                        return;
                    }
                    closeRegister();
                    if (res.data && res.data.request_only) {
                        showToast(res.message || "Request submitted. Admin will contact you.");
                        return;
                    }
                    IS_LOGGED_IN = true;
                    showToast("Account created! Welcome!");
                    setTimeout(() => window.location.reload(), 800);
                })
                .catch(() => {
                    if (regBtn) regBtn.disabled = false;
                    if (regBtnText) regBtnText.textContent = "Create Account";
                    showToast("Unable to connect.", "error");
                });
        });
    }
    /* =========================================================
       PRODUCT CARDS
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
                variantsBox.innerHTML = variants.map(v => {
                    const qtyLabel = v.quantity_name || (v.quantity + " " + (v.quantity_unit || ""));
                    const cartQty = Number(v.cart_qty || 0);
                    return `
                        <div class="mm-variant-row" data-variant-id="${(v.id)}" data-price="${Number(v.price)}" data-original="${cartQty}">
                            <span class="mm-variant-label">${escapeHtml(qtyLabel)}</span>
                            <div class="mm-qty" data-qty="${cartQty}">
                                <button type="button" class="js-qty-minus" ${cartQty === 0 ? "disabled" : ""}>−</button>
                                <span class="mm-qty-val">${cartQty}</span>
                                <button type="button" class="js-qty-plus">+</button>
                            </div>
                            <span class="mm-variant-price">Rs. ${Number(v.price).toFixed(0)}</span>
                        </div>
                    `;
                }).join("");

                variantsBox.querySelectorAll(".mm-variant-row").forEach(row => {
                    const minus = row.querySelector(".js-qty-minus");
                    const plus = row.querySelector(".js-qty-plus");
                    const valEl = row.querySelector(".mm-qty-val");
                    const box = row.querySelector(".mm-qty");
                    plus.addEventListener("click", () => {
                        let q = Number(box.dataset.qty || 0);
                        q++;
                        box.dataset.qty = q;
                        valEl.textContent = q;
                        minus.disabled = false;
                        updateCardState(card);
                    });
                    minus.addEventListener("click", () => {
                        let q = Number(box.dataset.qty || 0);
                        if (q > 0) q--;
                        box.dataset.qty = q;
                        valEl.textContent = q;
                        minus.disabled = q === 0;
                        updateCardState(card);
                    });
                });

                updateCardState(card);
            })
            .catch(() => {
                variantsBox.innerHTML = '<div class="mm-variants-loading">Error.</div>';
            });
    }

    function updateCardState(card) {
        const btn = card.querySelector(".js-add-cart");
        const btnText = btn ? btn.querySelector("span") : null;
        if (!btn) return;
        const rows = card.querySelectorAll(".mm-variant-row");
        let anyChanged = false, anyQty = false;
        rows.forEach(row => {
            const cur = Number(row.querySelector(".mm-qty").dataset.qty || 0);
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
            if (!IS_LOGGED_IN) { showToast("Please log in.", "error"); openLogin(); return; }
            const productId = Number(card.dataset.productId || 0);
            const menuCode = card.dataset.menuCode || "";
            const rows = card.querySelectorAll(".mm-variant-row");
            const items = [];
            rows.forEach(row => {
                const qty = Number(row.querySelector(".mm-qty").dataset.qty || 0);
                items.push({ variant_id: Number(row.dataset.variantId), qty: qty });
            });

            btn.disabled = true;
            btn.classList.add("adding");
            btn.querySelector("span").textContent = "Saving…";

            const fd = new FormData();
            fd.append("product_id", productId);
            fd.append("menu_code", menuCode);
            fd.append("items", JSON.stringify(items));

            fetch(MAIN_URL + "ajax/cart-sync.php", { method: "POST", body: fd, credentials: "same-origin" })
                .then(r => r.json().catch(() => null))
                .then(res => {
                    btn.classList.remove("adding");
                    if (!res || !res.success) {
                        showToast((res && res.message) || "Failed.", "error");
                        updateCardState(card);
                        return;
                    }
                    showToast("Cart updated.");
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
                    showToast("Unable to connect.", "error");
                    updateCardState(card);
                });
        });
    });

})();