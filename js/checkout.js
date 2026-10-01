/* =========================================================
   MRS MILL@ — CHECKOUT PAGE
   File: ./js/checkout.js
   ========================================================= */

(function () {
    "use strict";

    const MAIN_URL = window.MAIN_URL || "./";
    const IS_LOGGED_IN = !!window.IS_LOGGED_IN;

    /* ---------- DOM ---------- */
    const mobileInput       = document.getElementById("coMobile");
    const nameInput         = document.getElementById("coName");

    const modeRadios        = document.querySelectorAll('input[name="delivery_mode"]');
    const addressBlock      = document.getElementById("addressBlock");
    const pickupInfo        = document.getElementById("pickupInfo");

    const pickupBranchSelect = document.getElementById("coPickupBranch");
    const pickupBranchHint   = document.getElementById("pickupBranchHint");

    const aptSearch         = document.getElementById("coApartmentSearch");
    const aptResults        = document.getElementById("apartmentResults");
    const aptIdInput        = document.getElementById("coApartmentId");
    const aptCodeInput      = document.getElementById("coApartmentCode");

    const divisionField     = document.getElementById("divisionField");
    const divisionSearch    = document.getElementById("coDivisionSearch");
    const divisionResults   = document.getElementById("divisionResults");
    const divisionInput     = document.getElementById("coDivision");
    const divisionHint      = document.getElementById("divisionHint");

    const subTotalEl        = document.getElementById("subTotal");
    const deliveryEl        = document.getElementById("deliveryCharge");
    const deliveryLabel     = document.getElementById("deliveryLabel");
    const grandTotalEl      = document.getElementById("grandTotal");
    const payNowBtn         = document.getElementById("payNowBtn");
    const mobileHint        = document.getElementById("mobileHint");

    let apartmentsCache     = [];
    let divisionsCache      = [];
    let branchesCache       = [];
    let apartmentsLoaded    = false;
    let selectedDivision    = null;
    let subtotal            = Number(window.CART_SUBTOTAL || 0);
    let deliveryCharge      = 0;
    let currentMode         = "delivery";

    /* ---------- Helpers ---------- */
    function money(n) {
        return "₹" + Math.round(Number(n) || 0);
    }

    function escapeHtml(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;")
            .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    function showToast(msg, type) {
        let t = document.getElementById("mmToast");
        if (!t) { t = document.createElement("div"); t.id = "mmToast"; document.body.appendChild(t); }
        t.textContent = msg;
        t.className = "show" + (type === "error" ? " error" : "");
        clearTimeout(t._t);
        t._t = setTimeout(() => (t.className = ""), 2600);
    }

    function getCheckedMode() {
        const r = document.querySelector('input[name="delivery_mode"]:checked');
        return r ? r.value : "delivery";
    }

    /* =========================================================
       BRANCHES  (only fetch when pickup tab is opened first time)
       ========================================================= */
    let branchesLoaded = false;
    function loadBranches() {
        if (!pickupBranchSelect || branchesLoaded) return;
        branchesLoaded = true;

        fetch(MAIN_URL + "ajax/get-branches.php", { credentials: "same-origin" })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success || !Array.isArray(res.data)) return;
                branchesCache = res.data;

                pickupBranchSelect.innerHTML =
                    `<option value="">— Select branch —</option>` +
                    res.data.map(b => `
                        <option value="${b.id}" data-name="${escapeHtml(b.branch_name)}">
                            ${escapeHtml(b.branch_name)}
                        </option>
                    `).join("");
            })
            .catch(() => {});
    }

    /* =========================================================
       DELIVERY MODE TOGGLE
       ========================================================= */
    function setMode(mode) {
        currentMode = mode;

        if (mode === "pickup") {
            if (addressBlock) addressBlock.classList.add("hidden");
            if (pickupInfo) pickupInfo.classList.add("show");

            /* Lazy load branches first time */
            loadBranches();

            /* Clear address-only fields */
            if (aptIdInput) aptIdInput.value = "";
            if (aptCodeInput) aptCodeInput.value = "";
            if (aptSearch) aptSearch.value = "";
            if (divisionInput) divisionInput.value = "";
            if (divisionSearch) divisionSearch.value = "";

            selectedDivision = null;
            divisionsCache = [];

            deliveryCharge = 0;
            if (deliveryLabel) deliveryLabel.textContent = "Pickup charge";
            if (deliveryEl) deliveryEl.textContent = "₹0";
            if (pickupBranchHint) pickupBranchHint.textContent = "Pick a branch to collect your order from.";

        } else {
            if (addressBlock) addressBlock.classList.remove("hidden");
            if (pickupInfo) pickupInfo.classList.remove("show");

            if (pickupBranchSelect) pickupBranchSelect.value = "";

            /* Restore customer's saved address + division */
            restoreCustomerAddress();

            if (deliveryLabel) deliveryLabel.textContent = "Delivery charge";
            if (deliveryEl) deliveryEl.textContent = money(deliveryCharge);
        }

        updateTotals();
    }

    modeRadios.forEach(r => {
        r.addEventListener("change", function () {
            setMode(this.value);
        });
    });

    /* =========================================================
       RESTORE CUSTOMER ADDRESS (from DB, no AJAX needed)
       ========================================================= */
    function restoreCustomerAddress() {
        const aptId = Number(window.CUSTOMER_APARTMENT_ID || 0);
        if (aptId <= 0) return;

        /* Only restore if the fields are empty (i.e. user hasn't picked another) */
        if (!aptIdInput.value) {
            aptIdInput.value   = aptId;
            aptCodeInput.value = window.CUSTOMER_APARTMENT_CODE || "";
            aptSearch.value    = window.CUSTOMER_APARTMENT_NAME || "";
        }

        /* If division was already selected keep it */
        if (selectedDivision) {
            updateTotals();
            return;
        }

        /* Use saved division from customer table directly */
        const savedDivision = window.CUSTOMER_DIVISION || "";
        const savedCharge   = Number(window.CUSTOMER_DIVISION_CHARGE || 0);

        if (savedDivision !== "") {
            divisionInput.value       = savedDivision;
            divisionSearch.value      = "Division " + savedDivision;
            selectedDivision          = savedDivision;
            deliveryCharge            = Math.round(savedCharge);
            divisionHint.textContent  = "Delivery charge: ₹" + deliveryCharge;
            divisionField.style.display = "block";

            /* Also fetch dropdown options so user can switch */
            loadDivisions(aptId, savedDivision, /*keepSelection*/ true);
        } else {
            /* No saved division → load options normally */
            loadDivisions(aptId, "");
        }

        updateTotals();
    }

    /* =========================================================
       MOBILE LOOKUP  (skip if logged-in — data already on page)
       ========================================================= */
    let mobileTimer = null;
    let lastLookupMobile = "";

    function runMobileLookup(mobile) {
        /* If customer is already logged in, we already have their data */
        if (IS_LOGGED_IN) return;
        if (!mobile || mobile.length < 10) return;
        if (mobile === lastLookupMobile) return;
        lastLookupMobile = mobile;

        fetch(MAIN_URL + "ajax/customer-lookup.php?mobile=" + encodeURIComponent(mobile), {
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success) return;
                if (!res.data || !res.data.found) {
                    if (mobileHint) mobileHint.textContent = "We'll use this for delivery updates.";
                    return;
                }

                if (res.data.name && !nameInput.value.trim()) {
                    nameInput.value = res.data.name;
                }

                if (res.data.apartment_id && res.data.apartment_id > 0) {
                    const deliveryRadio = document.querySelector('input[name="delivery_mode"][value="delivery"]');
                    if (deliveryRadio && !deliveryRadio.checked) {
                        deliveryRadio.checked = true;
                        setMode("delivery");
                    }

                    aptIdInput.value   = res.data.apartment_id;
                    aptCodeInput.value = res.data.apartment_code || "";
                    aptSearch.value    = res.data.apartment_name || "";

                    loadDivisions(res.data.apartment_id, res.data.division || "");

                    if (mobileHint) mobileHint.textContent = "Welcome back! Address auto-filled.";
                } else {
                    if (mobileHint) mobileHint.textContent = "Welcome back, " + (res.data.name || "");
                }
            })
            .catch(() => {});
    }

    if (!IS_LOGGED_IN) {
        mobileInput?.addEventListener("input", () => {
            mobileInput.value = mobileInput.value.replace(/[^0-9]/g, "").slice(0, 15);
            clearTimeout(mobileTimer);

            const m = mobileInput.value.trim();
            lastLookupMobile = "";

            if (m.length < 10) return;

            mobileTimer = setTimeout(() => runMobileLookup(m), 350);
        });

        mobileInput?.addEventListener("blur", () => {
            const m = mobileInput.value.trim();
            if (m.length >= 10) {
                clearTimeout(mobileTimer);
                runMobileLookup(m);
            }
        });

        mobileInput?.addEventListener("paste", () => {
            setTimeout(() => {
                const m = mobileInput.value.trim();
                if (m.length >= 10) runMobileLookup(m);
            }, 50);
        });
    }

    /* =========================================================
       APARTMENT SEARCH  (lazy-load cache on first open)
       ========================================================= */
    function loadApartments(cb) {
        if (apartmentsLoaded) {
            if (typeof cb === "function") cb();
            return;
        }
        apartmentsLoaded = true;

        fetch(MAIN_URL + "ajax/get-appartment-divisions.php", { credentials: "same-origin" })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (res && res.success && Array.isArray(res.data)) {
                    apartmentsCache = res.data;
                }
                if (typeof cb === "function") cb();
            })
            .catch(() => {
                if (typeof cb === "function") cb();
            });
    }

    function renderApartmentResults(query) {
        if (!aptResults) return;
        const q = (query || "").trim().toLowerCase();

        let list = apartmentsCache;
        if (q) {
            list = list.filter(a =>
                (a.apartment_name || "").toLowerCase().includes(q) ||
                (a.apartment_code || "").toLowerCase().includes(q) ||
                (a.apartment_address || "").toLowerCase().includes(q)
            );
        }

        if (!list.length) {
            aptResults.innerHTML = `<div class="mm-co-dd-empty">No apartments found</div>`;
            aptResults.classList.add("open");
            return;
        }

        aptResults.innerHTML = list.slice(0, 30).map(a => `
            <div class="mm-co-dd-item" data-id="${a.id}" data-code="${escapeHtml(a.apartment_code)}" data-name="${escapeHtml(a.apartment_name)}">
                <strong>${escapeHtml(a.apartment_name)}</strong>
                <span>${escapeHtml(a.apartment_address || "")}</span>
            </div>
        `).join("");
        aptResults.classList.add("open");
    }

    aptSearch?.addEventListener("focus", () => {
        loadApartments(() => renderApartmentResults(aptSearch.value));
    });

    aptSearch?.addEventListener("input", () => {
        loadApartments(() => renderApartmentResults(aptSearch.value));
    });

    aptResults?.addEventListener("click", (e) => {
        const item = e.target.closest(".mm-co-dd-item");
        if (!item) return;

        aptIdInput.value   = item.dataset.id;
        aptCodeInput.value = item.dataset.code;
        aptSearch.value    = item.dataset.name;
        aptResults.classList.remove("open");

        loadDivisions(item.dataset.id, "");
    });

    document.addEventListener("click", (e) => {
        if (!e.target.closest("#apartmentResults") && !e.target.closest("#coApartmentSearch")) {
            aptResults?.classList.remove("open");
        }
        if (!e.target.closest("#divisionResults") && !e.target.closest("#coDivisionSearch")) {
            divisionResults?.classList.remove("open");
        }
    });

    /* =========================================================
       DIVISIONS
       ========================================================= */
    function loadDivisions(apartmentId, preselect, keepSelection) {
        if (!apartmentId || getCheckedMode() === "pickup") return;

        fetch(MAIN_URL + "ajax/get-appartment-divisions.php?apartment_id=" + encodeURIComponent(apartmentId), {
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success || !Array.isArray(res.data)) {
                    if (!keepSelection) hideDivisionField();
                    return;
                }
                divisionsCache = res.data;
                if (!divisionsCache.length) {
                    if (!keepSelection) hideDivisionField();
                    return;
                }

                divisionField.style.display = "block";

                /* If keepSelection, don't reset the visible division */
                if (!keepSelection) {
                    divisionSearch.value = "";
                    divisionInput.value  = "";
                    divisionHint.textContent = "";
                    selectedDivision = null;
                    deliveryCharge = 0;
                    updateTotals();
                }

                if (preselect) {
                    const match = divisionsCache.find(d => String(d.division) === String(preselect));
                    if (match) selectDivision(match);
                }
            })
            .catch(() => {});
    }

    function hideDivisionField() {
        divisionField.style.display = "none";
        divisionSearch.value = "";
        divisionInput.value  = "";
        divisionHint.textContent = "";
        selectedDivision = null;
        deliveryCharge = 0;
        updateTotals();
    }

    function renderDivisionResults(query) {
        if (!divisionResults) return;
        const q = (query || "").trim().toLowerCase();

        let list = divisionsCache;
        if (q) {
            list = list.filter(d =>
                String(d.division).toLowerCase().includes(q) ||
                ("division " + d.division).toLowerCase().includes(q)
            );
        }

        if (!list.length) {
            divisionResults.innerHTML = `<div class="mm-co-dd-empty">No divisions found</div>`;
            divisionResults.classList.add("open");
            return;
        }

        divisionResults.innerHTML = list.map(d => `
            <div class="mm-co-dd-item"
                 data-division="${escapeHtml(d.division)}"
                 data-charge="${Number(d.charge)}">
                <strong>Division ${escapeHtml(d.division)}</strong>
                <span>Delivery charge: ₹${Math.round(Number(d.charge))}</span>
            </div>
        `).join("");
        divisionResults.classList.add("open");
    }

    divisionSearch?.addEventListener("focus", () => renderDivisionResults(divisionSearch.value));
    divisionSearch?.addEventListener("input", () => renderDivisionResults(divisionSearch.value));

    divisionResults?.addEventListener("click", (e) => {
        const item = e.target.closest(".mm-co-dd-item");
        if (!item) return;

        selectDivision({
            division: item.dataset.division,
            charge:   Number(item.dataset.charge || 0)
        });
        divisionResults.classList.remove("open");
    });

    function selectDivision(d) {
        divisionInput.value = d.division;
        divisionSearch.value = "Division " + d.division;
        selectedDivision = d.division;
        deliveryCharge = Math.round(Number(d.charge || 0));
        divisionHint.textContent = "Delivery charge: ₹" + deliveryCharge;
        updateTotals();
    }

    function updateTotals() {
        subTotalEl.textContent = money(subtotal);

        const mode = getCheckedMode();

        if (mode === "pickup") {
            deliveryEl.textContent = "₹0";
            grandTotalEl.textContent = money(subtotal);
        } else {
            deliveryEl.textContent = money(deliveryCharge);
            grandTotalEl.textContent = money(subtotal + deliveryCharge);
        }
    }
    updateTotals();

    /* =========================================================
       AUTO-FILL ADDRESS FROM CUSTOMER PROFILE (on load)
       ========================================================= */
    (function autoFillAddress() {
        if (Number(window.CUSTOMER_APARTMENT_ID || 0) > 0) {
            restoreCustomerAddress();
        }
    })();

    /* =========================================================
       PAY NOW
       ========================================================= */
    payNowBtn?.addEventListener("click", () => {
        const mobile  = mobileInput.value.trim();
        const name    = nameInput.value.trim();

        if (!/^[0-9]{10,15}$/.test(mobile)) { showToast("Enter a valid mobile number.", "error"); mobileInput.focus(); return; }
        if (name.length < 3) { showToast("Please enter your name.", "error"); nameInput.focus(); return; }

        const mode = getCheckedMode();
        currentMode = mode;

        let grandTotal = subtotal;
        let aptId = "", aptCode = "", division = "";
        let pickupBranchId = "", pickupBranchName = "";

        if (mode === "delivery") {
            aptId = aptIdInput.value;
            aptCode = aptCodeInput.value;

            if (!aptId) { showToast("Please choose an apartment.", "error"); aptSearch.focus(); return; }
            if (!selectedDivision) { showToast("Please select a division.", "error"); divisionSearch?.focus(); return; }

            grandTotal = subtotal + deliveryCharge;
            division = selectedDivision;
        } else {
            if (!pickupBranchSelect || !pickupBranchSelect.value) {
                showToast("Please select a pickup branch.", "error");
                pickupBranchSelect?.focus();
                return;
            }
            pickupBranchId = pickupBranchSelect.value;
            const opt = pickupBranchSelect.options[pickupBranchSelect.selectedIndex];
            pickupBranchName = opt ? (opt.dataset.name || opt.textContent.trim()) : "";
        }

        const options = {
            key: window.RAZORPAY_KEY_ID,
            amount: Math.round(grandTotal * 100),
            currency: "INR",
            name: "Mrs Mill@",
            description: mode === "pickup" ? "Store pickup order" : "Home delivery order",
            prefill: {
                name: name,
                contact: mobile
            },
            theme: { color: "#b51f2c" },
            handler: function (response) {
                finalizeOrder({
                    razorpay_payment_id: response.razorpay_payment_id,
                    customer_name: name,
                    customer_mobile: mobile,
                    delivery_mode: mode,
                    apartment_id: aptId,
                    apartment_code: aptCode,
                    division: division,
                    division_charge: mode === "delivery" ? deliveryCharge : 0,
                    pickup_branch_id: pickupBranchId,
                    pickup_branch_name: pickupBranchName,
                    total: grandTotal
                });
            },
            modal: {
                ondismiss: function () { showToast("Payment cancelled."); }
            }
        };

        try {
            const rzp = new Razorpay(options);
            rzp.open();
        } catch (err) {
            showToast("Unable to open payment. Check Razorpay key.", "error");
        }
    });

    /* =========================================================
       FINALIZE ORDER
       ========================================================= */
    function finalizeOrder(payload) {
        payNowBtn.disabled = true;
        payNowBtn.innerHTML = "Processing...";

        const fd = new FormData();
        Object.keys(payload).forEach(k => fd.append(k, payload[k]));

        fetch(MAIN_URL + "ajax/checkout-place-order.php", {
            method: "POST",
            body: fd,
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success) {
                    showToast((res && res.message) || "Failed to place order.", "error");
                    payNowBtn.disabled = false;
                    payNowBtn.innerHTML = "Pay Now";
                    return;
                }
                showToast("Order placed successfully!");
                setTimeout(() => {
                    window.location.href = MAIN_URL + "my-orders.php";
                }, 900);
            })
            .catch(() => {
                showToast("Unable to connect.", "error");
                payNowBtn.disabled = false;
                payNowBtn.innerHTML = "Pay Now";
            });
    }

    /* ---------- INIT ---------- */
    setMode(getCheckedMode());

})();