/* =========================================================
   MRS MILL@ — CUSTOMER STORE
   File: ./js/home.js
   Block / Floor / Door No are three separate searchable fields.
   Address shape (current DB): customers.address_json
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

    /* Variant — three separate fields */
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

    /* House mode */
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

    const varOverlay = document.getElementById("variantOverlay");
    const varProductName = document.getElementById("variantProductName");
    const varProductCode = document.getElementById("variantProductCode");
    const varList = document.getElementById("variantList");
    const varAddBtn = document.getElementById("variantAddBtn");
    const varClose = document.getElementById("variantClose");

    const navCartCount = document.getElementById("navCartCount");

    /* ---------- STATE ---------- */
    const state = {
        pendingProduct: null,
        selectedVariantId: null,
        buyNowMode: false,
        cartCount: Number(window.CART_COUNT || 0),
        apartmentsCache: [],
        lookupTimer: null,
        lastLookedMobile: "",
        customApartment: false,
        regMode: "apartment",
        regHouseImgData: "",
        regHouseMapLink: "",

        /* selected apartment's variants (array of {block, floor, door_no, charge}) */
        currentVariants: [],
        selectedCharge: null
    };

    /* ---------- HELPERS ---------- */
    function escapeHtml(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;")
            .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    /* =========================================================
       UNPACK customer address (from address_json blob)
       Used by:
         - register popup when a customer record is preloaded
         - login / lookup response handling
       Handles apartment AND individual_house uniformly.
       ========================================================= */
    function unpackCustomerAddress(customerData) {
        if (!customerData || typeof customerData !== "object") return null;

        const a = (customerData.address && typeof customerData.address === "object")
            ? customerData.address
            : {};

        return {
            address_type:    customerData.address_type || a.address_type || "apartment",
            apartment_id:    Number(a.apartment_id || 0),
            apartment_code:  a.apartment_code   || "",
            apartment_name:  a.apartment_name   || "",
            block:           a.block            || "",
            door_no:         a.door_no          || "",
            floor:           a.floor            || "",
            division:        a.division_label   || "",
            division_charge: Number(a.charge || 0),
            full_address:    a.full_address     || "",
            map_link:        a.map_link         || "",
            house_image:     a.house_image      || ""
        };
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

    /* ---------- CART UI ---------- */
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

        fetch(MAIN_URL + "ajax/customer-lookup.php?mobile=" + encodeURIComponent(mobile), {
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success || !res.data) return;

                if (res.data.has_pending_request) {
                    closeLogin();
                    showToast(
                        "You've already sent a request. Our team will contact you soon.",
                        "error"
                    );
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

                    if (res.data.has_pending_request) {
                        closeLogin();
                        showToast(
                            "You've already sent a request. Our team will contact you soon.",
                            "error"
                        );
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
        fetch(MAIN_URL + "ajax/customer-login.php", { method: "POST", body: fd, credentials: "same-origin" })
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
       REGISTER — MODE TOGGLE
       ========================================================= */
    function setRegMode(mode) {
        state.regMode = mode;
        if (btnAptMode) btnAptMode.classList.toggle("active", mode === "apartment");
        if (btnHouseMode) btnHouseMode.classList.toggle("active", mode === "individual_house");
        if (regAptBlock) regAptBlock.style.display = mode === "apartment" ? "" : "none";
        if (regHouseBlock) regHouseBlock.style.display = mode === "individual_house" ? "" : "none";
    }
    if (btnAptMode) btnAptMode.addEventListener("click", () => setRegMode("apartment"));
    if (btnHouseMode) btnHouseMode.addEventListener("click", () => setRegMode("individual_house"));

    /* Location pin */
    if (regHouseLocBtn) {
        regHouseLocBtn.addEventListener("click", function () {
            if (!navigator.geolocation) {
                if (regHouseLocHint) regHouseLocHint.textContent = "Your browser does not support location.";
                return;
            }
            regHouseLocBtn.disabled = true;
            if (regHouseLocText) regHouseLocText.textContent = "Fetching location…";
            if (regHouseLocHint) regHouseLocHint.textContent = "Please allow location permission.";
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;
                    const link = "https://www.google.com/maps?q=" + lat + "," + lng;
                    state.regHouseMapLink = link;
                    if (regHouseMap) regHouseMap.value = link;
                    regHouseLocBtn.disabled = false;
                    regHouseLocBtn.classList.add("success");
                    if (regHouseLocText) regHouseLocText.textContent = "Location Pinned ✓";
                    if (regHouseLocHint) regHouseLocHint.textContent = "Your Google Maps location has been saved.";
                },
                function (err) {
                    regHouseLocBtn.disabled = false;
                    if (regHouseLocText) regHouseLocText.textContent = "Get My Current Location";
                    let msg = "Unable to fetch location.";
                    if (err.code === 1) msg = "Permission denied. Allow location access.";
                    else if (err.code === 2) msg = "Location unavailable. Check device settings.";
                    else if (err.code === 3) msg = "Timed out. Try again.";
                    if (regHouseLocHint) regHouseLocHint.textContent = msg;
                },
                { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
            );
        });
    }

    /* Image upload */
    if (regHouseImgDrop) {
        regHouseImgDrop.addEventListener("click", function () { if (regHouseImg) regHouseImg.click(); });
    }
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
                    regHouseImgPrev.innerHTML = '<img src="' + state.regHouseImgData + '" alt="House preview">';
                }
            };
            reader.readAsDataURL(file);
        });
    }

    /* =========================================================
       OPEN REGISTER — prefill from customerData.address_json
       ========================================================= */
    function openRegister(mobile, customerData) {
        const unpacked = unpackCustomerAddress(customerData);

        if (regMobile) regMobile.value = mobile || "";
        if (regName)   regName.value   = (customerData && customerData.name) ? customerData.name : "";
        if (regPassword) regPassword.value = "";
        if (regPasswordField) regPasswordField.style.display = "none";
        if (regAptList) regAptList.style.display = "none";
        if (regAptHint) regAptHint.textContent = "";

        state.customApartment = false;
        state.currentVariants = [];
        state.selectedCharge = null;

        if (regBlock)  regBlock.value = "";
        if (regFloor)  regFloor.value = "";
        if (regDoorNo) regDoorNo.value = "";
        if (regBlockList)  regBlockList.style.display = "none";
        if (regFloorList)  regFloorList.style.display = "none";
        if (regDoorNoList) regDoorNoList.style.display = "none";
        if (regVariantHint) regVariantHint.textContent = "";

        if (regBtnText) regBtnText.textContent = "Create Account";

        /* Prefill apartment from address_json */
        if (unpacked && unpacked.address_type === "apartment" && unpacked.apartment_id > 0) {
            if (regAptSearch) regAptSearch.value = unpacked.apartment_name || "";
            if (regAptId)     regAptId.value     = unpacked.apartment_id;
            if (regAptCode)   regAptCode.value   = unpacked.apartment_code || "";
        } else {
            if (regAptSearch) regAptSearch.value = "";
            if (regAptId)     regAptId.value     = "";
            if (regAptCode)   regAptCode.value   = "";
        }

        /* Reset house mode */
        if (regHouseAddress) regHouseAddress.value = "";
        if (regHouseMap) regHouseMap.value = "";
        if (regHouseLocHint) regHouseLocHint.textContent = "";
        if (regHouseLocBtn) {
            regHouseLocBtn.disabled = false;
            regHouseLocBtn.classList.remove("success");
        }
        if (regHouseLocText) regHouseLocText.textContent = "Get My Current Location";
        if (regHouseImg) regHouseImg.value = "";
        if (regHouseImgPrev) {
            regHouseImgPrev.classList.add("img-drop-empty");
            regHouseImgPrev.innerHTML = `
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:26px;height:26px;">
                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                    <circle cx="8.5" cy="8.5" r="1.5"/>
                    <path d="M21 15l-5-5L5 21"/>
                </svg>
                <span>Tap to upload a photo of your house</span>
            `;
        }
        state.regHouseImgData = "";
        state.regHouseMapLink = "";

        setRegMode("apartment");

        loadRegisterApartments(function () {
            if (unpacked && unpacked.address_type === "apartment" && unpacked.apartment_id > 0) {
                const apt = state.apartmentsCache.find(a => Number(a.id) === Number(unpacked.apartment_id));
                if (apt) {
                    selectApartmentForVariant(apt);
                    /* Prefill the 3 variant fields from the stored address */
                    if (regBlock)  regBlock.value  = unpacked.block   || "";
                    if (regFloor)  regFloor.value  = unpacked.floor   || "";
                    if (regDoorNo) regDoorNo.value = unpacked.door_no || "";
                    updateVariantCharge();
                }
            }
        });

        openOverlay(regOverlay);
        setTimeout(() => regName && regName.focus(), 200);
    }
    function closeRegister() { closeOverlay(regOverlay); }
    if (regClose) regClose.addEventListener("click", closeRegister);
    if (regOverlay) regOverlay.addEventListener("click", e => { if (e.target === regOverlay) closeRegister(); });

    /* =========================================================
       LOAD APARTMENTS
       ========================================================= */
    function loadRegisterApartments(cb) {
        fetch(MAIN_URL + "ajax/get-appartment-divisions.php", { credentials: "same-origin" })
            .then(r => r.json().catch(() => null))
            .then(res => {
                state.apartmentsCache = (res && res.success && Array.isArray(res.data)) ? res.data : [];
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
            regAptList.innerHTML = `<div style="padding:14px;text-align:center;font-size:12px;color:#948c82;">No matches found. Keep typing to request a new apartment.</div>`;
            regAptList.style.display = "block";
            return;
        }
        regAptList.innerHTML = list.slice(0, 40).map(a => `
            <div class="mm-co-dd-item"
                 data-id="${a.id}"
                 data-code="${escapeHtml(a.apartment_code)}"
                 data-name="${escapeHtml(a.apartment_name)}">
                <strong>${escapeHtml(a.apartment_name)}</strong>
                <span>${escapeHtml(a.apartment_address || '')}</span>
            </div>
        `).join("");
        regAptList.style.display = "block";
    }

    /* =========================================================
       VARIANT — normalise + render 3 fields
       ========================================================= */

    /* Normalise a raw variant object → {block, floor, door_no, charge} */
    function normalizeVariant(d) {
        if (!d || typeof d !== "object") return null;
        let block = d.block || "";
        let floor = d.floor || "";
        let doorNo = d.door_no || "";

        /* Legacy: only has `division` → put it into door_no */
        if (!block && !floor && !doorNo && d.division !== undefined) {
            doorNo = String(d.division || "");
        }
        if (!block && !floor && !doorNo) return null;
        return {
            block: String(block),
            floor: String(floor),
            door_no: String(doorNo),
            charge: Number(d.charge || 0)
        };
    }

    /* Load the chosen apartment's variants into state */
    function selectApartmentForVariant(apt) {
        const raw = Array.isArray(apt.divisions) ? apt.divisions : [];
        state.currentVariants = raw.map(normalizeVariant).filter(Boolean);
        state.customApartment = false;

        if (regPasswordField) regPasswordField.style.display = "";
        if (regBlock) regBlock.value = "";
        if (regFloor) regFloor.value = "";
        if (regDoorNo) regDoorNo.value = "";
        if (regBlockList) regBlockList.style.display = "none";
        if (regFloorList) regFloorList.style.display = "none";
        if (regDoorNoList) regDoorNoList.style.display = "none";
        if (regVariantHint) {
            regVariantHint.textContent = state.currentVariants.length
                ? "Pick block, floor, and door no."
                : "No variants for this apartment.";
        }
        state.selectedCharge = null;
    }

    /* Distict values helpers */
    function uniqueValues(arr, key) {
        const seen = {};
        const out = [];
        arr.forEach(v => {
            const val = String(v[key] || "").trim();
            if (!val) return;
            const k = val.toLowerCase();
            if (seen[k]) return;
            seen[k] = true;
            out.push(val);
        });
        return out.sort();
    }

    /* Render a dropdown for a specific field */
    function renderVariantDropdown(inputEl, listEl, items, currentValue, onPick) {
        if (!listEl) return;

        const q = (currentValue || "").trim().toLowerCase();
        const filtered = q
            ? items.filter(v => v.toLowerCase().includes(q))
            : items;

        if (!filtered.length) {
            listEl.innerHTML = `<div style="padding:12px;text-align:center;font-size:12px;color:#948c82;">No matches</div>`;
            listEl.style.display = "block";
            return;
        }

        listEl.innerHTML = filtered.slice(0, 60).map(v =>
            `<div class="mm-co-dd-item" data-value="${escapeHtml(v)}">${escapeHtml(v)}</div>`
        ).join("");
        listEl.style.display = "block";

        listEl.onclick = function (e) {
            const item = e.target.closest(".mm-co-dd-item");
            if (!item) return;
            const val = item.dataset.value;
            inputEl.value = val;
            listEl.style.display = "none";
            onPick(val);
        };
    }

    /* Recompute charge whenever block/floor/door no all filled */
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
            if (regVariantHint) {
                regVariantHint.textContent = "Delivery charge: ₹" + Math.round(match.charge);
            }
        } else {
            if (regVariantHint) {
                regVariantHint.textContent = "This combination doesn't exist for this apartment.";
            }
        }
    }

    /* --- BLOCK --- */
    if (regBlock) {
        regBlock.addEventListener("focus", function () {
            if (state.customApartment) return;
            const blocks = uniqueValues(state.currentVariants, "block");
            renderVariantDropdown(regBlock, regBlockList, blocks, regBlock.value, function (val) {
                regBlock.value = val;
                if (regFloor) regFloor.value = "";
                if (regDoorNo) regDoorNo.value = "";
                if (regVariantHint) regVariantHint.textContent = "Now pick floor.";
                if (regFloorList) regFloorList.style.display = "none";
                if (regDoorNoList) regDoorNoList.style.display = "none";
            });
        });
        regBlock.addEventListener("input", function () {
            if (state.customApartment) return;
            const blocks = uniqueValues(state.currentVariants, "block");
            renderVariantDropdown(regBlock, regBlockList, blocks, regBlock.value, function (val) {
                regBlock.value = val;
                if (regFloor) regFloor.value = "";
                if (regDoorNo) regDoorNo.value = "";
                if (regVariantHint) regVariantHint.textContent = "Now pick floor.";
            });
            updateVariantCharge();
        });
    }

    /* --- FLOOR --- */
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
                if (regDoorNoList) regDoorNoList.style.display = "none";
            });
        });
        regFloor.addEventListener("input", function () {
            if (state.customApartment) return;
            const block = (regBlock && regBlock.value || "").trim().toLowerCase();
            let source = state.currentVariants;
            if (block) source = source.filter(v => v.block.toLowerCase() === block);
            const floors = uniqueValues(source, "floor");
            renderVariantDropdown(regFloor, regFloorList, floors, regFloor.value, function (val) {
                regFloor.value = val;
                if (regDoorNo) regDoorNo.value = "";
                if (regVariantHint) regVariantHint.textContent = "Now pick door no.";
            });
            updateVariantCharge();
        });
    }

    /* --- DOOR NO --- */
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
        regDoorNo.addEventListener("input", function () {
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
            updateVariantCharge();
        });
    }

    /* Close all variant dropdowns on outside click */
    document.addEventListener("click", (e) => {
        if (!e.target.closest("#regBlock") && !e.target.closest("#regBlockList")) {
            if (regBlockList) regBlockList.style.display = "none";
        }
        if (!e.target.closest("#regFloor") && !e.target.closest("#regFloorList")) {
            if (regFloorList) regFloorList.style.display = "none";
        }
        if (!e.target.closest("#regDoorNo") && !e.target.closest("#regDoorNoList")) {
            if (regDoorNoList) regDoorNoList.style.display = "none";
        }
    });

    /* Apartment search */
    if (regAptSearch) {
        regAptSearch.addEventListener("focus", () => renderRegAptList(regAptSearch.value));
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
                if (regAptHint) regAptHint.textContent = "";
                selectApartmentForVariant(exact);
            } else if (typed.length > 0) {
                enableCustomApartmentMode();
            } else {
                state.customApartment = false;
                state.currentVariants = [];
                if (regAptHint) regAptHint.textContent = "";
                if (regBlock) regBlock.value = "";
                if (regFloor) regFloor.value = "";
                if (regDoorNo) regDoorNo.value = "";
                if (regVariantHint) regVariantHint.textContent = "";
                if (regPasswordField) regPasswordField.style.display = "none";
                if (regPassword) regPassword.value = "";
            }
            renderRegAptList(this.value);
        });
    }

    if (regAptList) {
        regAptList.addEventListener("click", (e) => {
            const item = e.target.closest(".mm-co-dd-item");
            if (!item) return;
            regAptId.value = item.dataset.id;
            regAptCode.value = item.dataset.code;
            regAptSearch.value = item.dataset.name;
            regAptList.style.display = "none";
            if (regAptHint) regAptHint.textContent = "";

            const apt = state.apartmentsCache.find(a => Number(a.id) === Number(item.dataset.id));
            if (apt) selectApartmentForVariant(apt);
        });
    }

    function enableCustomApartmentMode() {
        state.customApartment = true;
        state.currentVariants = [];
        if (regAptId) regAptId.value = "";
        if (regAptCode) regAptCode.value = "";
        if (regAptHint) regAptHint.textContent = "We'll save your request — our team will contact you soon.";

        if (regBlock) regBlock.value = "";
        if (regFloor) regFloor.value = "";
        if (regDoorNo) regDoorNo.value = "";
        if (regBlockList) regBlockList.style.display = "none";
        if (regFloorList) regFloorList.style.display = "none";
        if (regDoorNoList) regDoorNoList.style.display = "none";
        if (regVariantHint) regVariantHint.textContent = "Type block / floor / door no manually.";

        if (regPasswordField) regPasswordField.style.display = "none";
        if (regPassword) regPassword.value = "";
    }

    /* =========================================================
       REGISTER SUBMIT
       ========================================================= */
    if (regForm) {
        regForm.addEventListener("submit", function (e) {
            e.preventDefault();

            const mobile = (regMobile.value || "").trim();
            const name = (regName.value || "").trim();
            const password = (regPassword.value || "").trim();

            if (name.length < 3) {
                showToast("Please enter your name (3+ chars).", "error");
                regName.focus();
                return;
            }

            /* --- INDIVIDUAL HOUSE --- */
            if (state.regMode === "individual_house") {
                const houseAddr = (regHouseAddress.value || "").trim();
                if (houseAddr === "") { showToast("Please enter your full address.", "error"); regHouseAddress.focus(); return; }
                if (!state.regHouseMapLink) { showToast("Please pin your location on the map.", "error"); return; }

                regBtn.disabled = true;
                regBtnText.innerHTML = '<i class="bi bi-hourglass-split"></i> Sending...';

                const fd = new FormData();
                fd.append("full_name", name);
                fd.append("mobile_number", mobile);
                fd.append("request_type", "individual_house");
                fd.append("house_address", houseAddr);
                fd.append("map_link", state.regHouseMapLink);
                if (state.regHouseImgData) fd.append("house_image_data", state.regHouseImgData);

                fetch(MAIN_URL + "ajax/customer-register.php", { method: "POST", body: fd, credentials: "same-origin" })
                    .then(r => r.json().catch(() => null))
                    .then(res => {
                        regBtn.disabled = false;
                        regBtnText.textContent = "Create Account";
                        if (!res || !res.success) { showToast((res && res.message) || "Failed to send.", "error"); return; }
                        closeRegister();
                        showToast(res.data && res.data.already_requested
                            ? "You've already sent a request. Our team will contact you soon."
                            : "Request received! Our team will contact you soon.");
                    })
                    .catch(() => {
                        regBtn.disabled = false;
                        regBtnText.textContent = "Create Account";
                        showToast("Unable to connect.", "error");
                    });
                return;
            }

            /* --- APARTMENT --- */
            const aptText = (regAptSearch.value || "").trim();
            const aptId = (regAptId.value || "").trim();
            const block = (regBlock && regBlock.value || "").trim();
            const floor = (regFloor && regFloor.value || "").trim();
            const doorNo = (regDoorNo && regDoorNo.value || "").trim();

            if (aptText === "") { showToast("Please enter your apartment.", "error"); regAptSearch.focus(); return; }

            const isCustom = state.customApartment || !aptId;

            if (isCustom) {
                if (!block || !floor || !doorNo) {
                    showToast("Please type block, floor and door no.", "error");
                    return;
                }
            } else {
                if (!block || !floor || !doorNo) {
                    showToast("Please choose block, floor and door no.", "error");
                    return;
                }
                if (state.selectedCharge === null) {
                    showToast("This block/floor/door combination doesn't exist.", "error");
                    return;
                }
                if (password.length < 3) {
                    showToast("Password must be 3+ characters.", "error");
                    regPassword.focus();
                    return;
                }
            }

            const divisionLabel =
                "Blk " + block +
                " / Door " + doorNo +
                " / Fl " + floor;

            regBtn.disabled = true;
            regBtnText.innerHTML = '<i class="bi bi-hourglass-split"></i> Creating...';

            const fd = new FormData();
            fd.append("full_name", name);
            fd.append("mobile_number", mobile);
            fd.append("password", password);

            if (isCustom) {
                fd.append("custom_apartment", aptText);
                fd.append("custom_division", divisionLabel);
            } else {
                fd.append("apartment_id", aptId);
                fd.append("division", divisionLabel);
            }

            fetch(MAIN_URL + "ajax/customer-register.php", { method: "POST", body: fd, credentials: "same-origin" })
                .then(r => r.json().catch(() => null))
                .then(res => {
                    regBtn.disabled = false;
                    regBtnText.textContent = "Create Account";
                    if (!res || !res.success) {
                        showToast((res && res.message) || "Registration failed.", "error");
                        return;
                    }
                    if (res.data && res.data.request_only) {
                        closeRegister();
                        showToast(res.data.already_requested
                            ? "You've already sent a request. Our team will contact you soon."
                            : "Request received! Our team will contact you soon.");
                        return;
                    }
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
            e.preventDefault(); e.stopPropagation();
            const productId = Number(btn.dataset.productId || 0);
            if (productId <= 0) return;
            if (!IS_LOGGED_IN) { state.buyNowMode = true; openLogin(); return; }
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

    if (varClose) varClose.addEventListener("click", () => { closeOverlay(varOverlay); state.buyNowMode = false; });
    if (varOverlay) varOverlay.addEventListener("click", e => {
        if (e.target === varOverlay) { closeOverlay(varOverlay); state.buyNowMode = false; }
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

            fetch(MAIN_URL + "ajax/cart-add.php", { method: "POST", body: fd, credentials: "same-origin" })
                .then(r => r.json().catch(() => null))
                .then(res => {
                    varAddBtn.disabled = false;
                    varAddBtn.innerHTML = originalHtml;
                    if (!res || !res.success) { showToast((res && res.message) || "Failed to add.", "error"); return; }
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