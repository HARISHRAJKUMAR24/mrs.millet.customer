/* =========================================================
   MRS MILL@ — CHECKOUT
   File: ./js/checkout.js
   Address branch is decided by window.CUSTOMER_ADDRESS_TYPE.
   Wallet covers food + tax + delivery.
   ========================================================= */

(function () {
    "use strict";

    const MAIN_URL = window.MAIN_URL || "./";
    const IS_LOGGED_IN = !!window.IS_LOGGED_IN;
    const ADDRESS_TYPE = String(window.CUSTOMER_ADDRESS_TYPE || "apartment");

    /* ---------- DOM ---------- */
    const mobileInput = document.getElementById("coMobile");
    const nameInput = document.getElementById("coName");

    const modeRadios = document.querySelectorAll('input[name="delivery_mode"]');
    const addressBlock = document.getElementById("addressBlock");
    const pickupInfo = document.getElementById("pickupInfo");

    const pickupBranchSelect = document.getElementById("coPickupBranch");
    const pickupBranchHint = document.getElementById("pickupBranchHint");

    /* Optional elements (may be hidden inputs if logged-in read-only) */
    const aptSearch = document.getElementById("coApartmentSearch");
    const aptResults = document.getElementById("apartmentResults");
    const aptIdInput = document.getElementById("coApartmentId");
    const aptCodeInput = document.getElementById("coApartmentCode");

    const divisionField = document.getElementById("divisionField");
    const divisionSearch = document.getElementById("coDivisionSearch");
    const divisionResults = document.getElementById("divisionResults");
    const divisionInput = document.getElementById("coDivision");
    const divisionChargeInput = document.getElementById("coDivisionCharge");
    const divisionHint = document.getElementById("divisionHint");

    const addressTypeInput = document.getElementById("coAddressType");
    const fullAddressInput = document.getElementById("coFullAddress");
    const mapLinkInput = document.getElementById("coMapLink");
    const houseImageInput = document.getElementById("coHouseImage");

    const subTotalEl = document.getElementById("subTotal");
    const deliveryEl = document.getElementById("deliveryCharge");
    const deliveryLabel = document.getElementById("deliveryLabel");
    const grandTotalEl = document.getElementById("grandTotal");
    const payNowBtn = document.getElementById("payNowBtn");
    const payNowText = document.getElementById("payNowText");
    const mobileHint = document.getElementById("mobileHint");

    const walletRow = document.getElementById("walletRow");
    const walletAppliedEl = document.getElementById("walletApplied");
    const walletBox = document.getElementById("walletBox");
    const walletBalanceEl = document.getElementById("walletBalance");
    const walletMsg = document.getElementById("walletMsg");

    const taxRow = document.getElementById("taxRow");
    const taxLabelEl = document.getElementById("taxLabel");
    const taxAmountEl = document.getElementById("taxAmount");

    let apartmentsCache = [];
    let divisionsCache = [];
    let branchesCache = [];
    let apartmentsLoaded = false;
    let selectedDivision = null;
    let subtotal = Number(window.CART_SUBTOTAL || 0);
    let deliveryCharge = 0;
    let currentMode = "delivery";

    const walletBalance = Number(window.WALLET_BALANCE || 0);
    const containerTotal = Number(window.CONTAINER_TOTAL || 0);

    const TAX = (window.TAX_SETTINGS && typeof window.TAX_SETTINGS === "object")
        ? {
            status: Number(window.TAX_SETTINGS.status) || 0,
            rate: Number(window.TAX_SETTINGS.rate) || 0,
            type: (window.TAX_SETTINGS.type === "inclusive") ? "inclusive" : "exclusive"
        }
        : { status: 0, rate: 0, type: "exclusive" };

    /* ---------- Helpers ---------- */
    function money(n) { return "₹" + Math.round(Number(n) || 0); }

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

    function calcTax(foodSubtotal) {
        if (!TAX || Number(TAX.status) !== 1 || Number(TAX.rate) <= 0) {
            return { amount: 0, rate: 0, type: "exclusive", active: false };
        }
        const rate = Number(TAX.rate);
        const type = TAX.type;
        let amount = type === "inclusive"
            ? foodSubtotal - (foodSubtotal / (1 + (rate / 100)))
            : foodSubtotal * (rate / 100);
        return { amount: Math.round(amount), rate: rate, type: type, active: true };
    }

    /* ---------- BRANCHES ---------- */
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
            .catch(() => { });
    }

    /* ---------- MODE ---------- */
    function setMode(mode) {
        currentMode = mode;

        if (mode === "pickup") {
            if (addressBlock) addressBlock.classList.add("hidden");
            if (pickupInfo) pickupInfo.classList.add("show");

            loadBranches();

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

            restoreCustomerAddress();
            if (deliveryLabel) deliveryLabel.textContent = "Delivery charge";
            if (deliveryEl) deliveryEl.textContent = money(deliveryCharge);
        }

        updateTotals();
    }

    modeRadios.forEach(r => r.addEventListener("change", function () { setMode(this.value); }));

    /* ---------- RESTORE READ-ONLY ADDRESS ---------- */
    function restoreCustomerAddress() {
        if (ADDRESS_TYPE === "individual_house") {
            deliveryCharge = 0;
            updateTotals();
            return;
        }

        const aptId = Number(window.CUSTOMER_APARTMENT_ID || 0);
        const savedDiv = String(window.CUSTOMER_DIVISION || "");
        const savedChg = Number(window.CUSTOMER_DIVISION_CHARGE || 0);

        if (aptId > 0) {
            if (selectedDivision) { updateTotals(); return; }

            if (savedDiv !== "") {
                if (divisionInput) divisionInput.value = savedDiv;
                if (divisionSearch) divisionSearch.value = "Division " + savedDiv;
                selectedDivision = savedDiv;
                deliveryCharge = Math.round(savedChg);
                if (divisionHint) divisionHint.textContent = "Delivery charge: ₹" + deliveryCharge;
            }
        }

        updateTotals();
    }

    /* ---------- MOBILE LOOKUP (guests only) ---------- */
    let mobileTimer = null;
    let lastLookupMobile = "";

    function runMobileLookup(mobile) {
        if (IS_LOGGED_IN) return;
        if (!mobile || mobile.length < 10) return;
        if (mobile === lastLookupMobile) return;
        lastLookupMobile = mobile;

        fetch(MAIN_URL + "ajax/customer-lookup.php?mobile=" + encodeURIComponent(mobile), {
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success || !res.data || !res.data.found) return;

                if (res.data.name && nameInput && !nameInput.value.trim()) {
                    nameInput.value = res.data.name;
                }
                if (mobileHint) mobileHint.textContent = "Welcome back, " + (res.data.name || "");
            })
            .catch(() => { });
    }

    if (!IS_LOGGED_IN && mobileInput) {
        mobileInput.addEventListener("input", () => {
            mobileInput.value = mobileInput.value.replace(/[^0-9]/g, "").slice(0, 15);
            clearTimeout(mobileTimer);
            const m = mobileInput.value.trim();
            lastLookupMobile = "";
            if (m.length < 10) return;
            mobileTimer = setTimeout(() => runMobileLookup(m), 350);
        });
        mobileInput.addEventListener("blur", () => {
            const m = mobileInput.value.trim();
            if (m.length >= 10) {
                clearTimeout(mobileTimer);
                runMobileLookup(m);
            }
        });
    }

    /* ---------- APARTMENT MANUAL PICKER (guests) ---------- */
    function loadApartments(cb) {
        if (apartmentsLoaded) { if (typeof cb === "function") cb(); return; }
        apartmentsLoaded = true;
        fetch(MAIN_URL + "ajax/get-appartment-divisions.php", { credentials: "same-origin" })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (res && res.success && Array.isArray(res.data)) apartmentsCache = res.data;
                if (typeof cb === "function") cb();
            })
            .catch(() => { if (typeof cb === "function") cb(); });
    }

    function renderApartmentResults(query) {
        if (!aptResults) return;
        const q = (query || "").trim().toLowerCase();
        let list = apartmentsCache;
        if (q) {
            list = list.filter(a =>
                (a.apartment_name || "").toLowerCase().includes(q) ||
                (a.apartment_code || "").toLowerCase().includes(q) ||
                (a.apartment_address || "").toLowerCase().includes(q));
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

    if (aptSearch) {
        aptSearch.addEventListener("focus", () => loadApartments(() => renderApartmentResults(aptSearch.value)));
        aptSearch.addEventListener("input", () => loadApartments(() => renderApartmentResults(aptSearch.value)));
    }

    aptResults?.addEventListener("click", (e) => {
        const item = e.target.closest(".mm-co-dd-item");
        if (!item) return;
        if (aptIdInput) aptIdInput.value = item.dataset.id;
        if (aptCodeInput) aptCodeInput.value = item.dataset.code;
        if (aptSearch) aptSearch.value = item.dataset.name;
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

    /* ---------- DIVISION PICKER (guests only) ---------- */
    function loadDivisions(apartmentId, preselect) {
        if (!apartmentId || getCheckedMode() === "pickup") return;
        if (!divisionField) return;

        fetch(MAIN_URL + "ajax/get-appartment-divisions.php?apartment_id=" + encodeURIComponent(apartmentId), {
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success || !Array.isArray(res.data) || !res.data.length) {
                    divisionField.style.display = "none";
                    return;
                }
                divisionsCache = res.data;
                divisionField.style.display = "block";
                divisionSearch.value = "";
                divisionInput.value = "";
                divisionHint.textContent = "";
                selectedDivision = null;
                deliveryCharge = 0;
                updateTotals();
                if (preselect) {
                    const match = divisionsCache.find(d => String(d.division) === String(preselect));
                    if (match) selectDivision(match);
                }
            })
            .catch(() => { });
    }

    function renderDivisionResults(query) {
        if (!divisionResults) return;
        const q = (query || "").trim().toLowerCase();
        let list = divisionsCache;
        if (q) {
            list = list.filter(d =>
                String(d.division).toLowerCase().includes(q) ||
                ("division " + d.division).toLowerCase().includes(q));
        }
        if (!list.length) {
            divisionResults.innerHTML = `<div class="mm-co-dd-empty">No divisions found</div>`;
            divisionResults.classList.add("open");
            return;
        }
        divisionResults.innerHTML = list.map(d => `
            <div class="mm-co-dd-item" data-division="${escapeHtml(d.division)}" data-charge="${Number(d.charge)}">
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
        selectDivision({ division: item.dataset.division, charge: Number(item.dataset.charge || 0) });
        divisionResults.classList.remove("open");
    });

    function selectDivision(d) {
        if (!divisionInput) return;
        divisionInput.value = d.division;
        if (divisionSearch) divisionSearch.value = "Division " + d.division;
        if (divisionChargeInput) divisionChargeInput.value = Number(d.charge || 0);
        selectedDivision = d.division;
        deliveryCharge = Math.round(Number(d.charge || 0));
        if (divisionHint) divisionHint.textContent = "Delivery charge: ₹" + deliveryCharge;
        updateTotals();
    }

    /* ---------- TOTALS ---------- */
    function updateTotals() {
        subTotalEl.textContent = money(subtotal);

        const mode = getCheckedMode();
        const delivery = (mode === "pickup") ? 0 : Math.round(Number(deliveryCharge || 0));
        deliveryEl.textContent = money(delivery);

        const tax = calcTax(subtotal);
        if (taxRow && taxAmountEl && taxLabelEl) {
            if (tax.active) {
                taxLabelEl.textContent = "Tax (" + tax.rate + "%)" + (tax.type === "inclusive" ? " · incl." : "");
                taxAmountEl.textContent = money(tax.amount);
                taxRow.style.display = "flex";
            } else {
                taxRow.style.display = "none";
            }
        }

        let foodPlusDelivery;
        if (tax.active && tax.type === "exclusive") {
            foodPlusDelivery = subtotal + tax.amount + delivery;
        } else {
            foodPlusDelivery = subtotal + delivery;
        }

        const walletUsable = Math.max(0, Math.min(walletBalance, foodPlusDelivery));
        const payable = Math.max(0, foodPlusDelivery - walletUsable);

        if (walletRow) {
            if (walletUsable > 0) {
                walletRow.style.display = "flex";
                if (walletAppliedEl) walletAppliedEl.textContent = walletUsable;
            } else {
                walletRow.style.display = "none";
            }
        }
        if (walletMsg) {
            if (walletBalance > 0 && walletUsable > 0) {
                walletMsg.classList.remove("warn");
                walletMsg.textContent = money(walletUsable) + " applied";
            } else if (walletBalance > 0) {
                walletMsg.classList.add("warn");
                walletMsg.textContent = money(walletBalance) + " available";
            } else {
                walletMsg.classList.add("warn");
                walletMsg.textContent = "No balance";
            }
        }

        grandTotalEl.textContent = money(payable);
        if (payNowText) payNowText.textContent = payable > 0 ? "Pay Now" : "Place Order";
    }
    updateTotals();
    (function autoFillAddress() {
        if (Number(window.CUSTOMER_APARTMENT_ID || 0) > 0) restoreCustomerAddress();
    })();

    /* =========================================================
       PAY NOW
       ========================================================= */
    payNowBtn?.addEventListener("click", () => {
        const mobile = mobileInput.value.trim();
        const name = nameInput.value.trim();

        // NEW: order note
        const orderNote = (document.getElementById("coOrderNote")?.value || "").trim().slice(0, 500);

        if (!/^[0-9]{10,15}$/.test(mobile)) { showToast("Enter a valid mobile number.", "error"); mobileInput.focus(); return; }
        if (name.length < 3) { showToast("Please enter your name.", "error"); nameInput.focus(); return; }

        const mode = getCheckedMode();
        currentMode = mode;

        /* Address payload from DOM */
        let addrType = addressTypeInput ? addressTypeInput.value : "apartment";
        let aptId = aptIdInput ? aptIdInput.value : "";
        let aptCode = aptCodeInput ? aptCodeInput.value : "";
        let divLabel = divisionInput ? divisionInput.value : "";
        let divCharge = divisionChargeInput ? Number(divisionChargeInput.value || 0) : 0;
        let fullAddr = fullAddressInput ? fullAddressInput.value.trim() : "";
        let mapLink = mapLinkInput ? mapLinkInput.value.trim() : "";

        let pickupBranchId = "";
        let pickupBranchName = "";

        if (mode === "delivery") {
            if (addrType === "individual_house") {
                if (!fullAddr) { showToast("Your house address is missing.", "error"); return; }
                aptId = "";
                aptCode = "";
                divLabel = "";
                divCharge = 0;
            } else {
                if (!aptId) { showToast("Please choose an apartment.", "error"); aptSearch?.focus(); return; }
                if (!divLabel) { showToast("Please select a division.", "error"); divisionSearch?.focus(); return; }
                fullAddr = "";
                mapLink = "";
            }
        } else {
            if (!pickupBranchSelect || !pickupBranchSelect.value) {
                showToast("Please select a pickup branch.", "error");
                pickupBranchSelect?.focus();
                return;
            }
            pickupBranchId = pickupBranchSelect.value;
            const opt = pickupBranchSelect.options[pickupBranchSelect.selectedIndex];
            pickupBranchName = opt ? (opt.dataset.name || opt.textContent.trim()) : "";

            addrType = "pickup";
            aptId = "";
            aptCode = "";
            divLabel = "";
            divCharge = 0;
            fullAddr = "";
            mapLink = "";
        }

        /* Totals */
        const delivery = (mode === "pickup") ? 0 : Math.round(Number(divCharge || 0));
        const tax = calcTax(subtotal);

        let foodPlusDelivery;
        if (tax.active && tax.type === "exclusive") {
            foodPlusDelivery = subtotal + tax.amount + delivery;
        } else {
            foodPlusDelivery = subtotal + delivery;
        }

        const walletUsable = Math.max(0, Math.min(walletBalance, foodPlusDelivery));
        const payable = Math.max(0, foodPlusDelivery - walletUsable);

        const orderPayload = {
            customer_name: name,
            customer_mobile: mobile,
            delivery_mode: mode,
            address_type: addrType,
            apartment_id: aptId,
            apartment_code: aptCode,
            division: divLabel,
            division_charge: delivery,
            full_address: fullAddr,
            map_link: mapLink,
            pickup_branch_id: pickupBranchId,
            pickup_branch_name: pickupBranchName,
            wallet_applied: walletUsable,
            container_amount: containerTotal,
            total: foodPlusDelivery,
            order_note: orderNote,
        };

        /* Fully covered by wallet → place directly */
        if (payable <= 0) {
            payNowBtn.disabled = true;
            payNowText.textContent = "Placing...";
            orderPayload.razorpay_payment_id = "WALLET-" + Date.now();
            finalizeOrder(orderPayload);
            return;
        }

        const options = {
            key: window.RAZORPAY_KEY_ID,
            amount: Math.round(payable * 100),
            currency: "INR",
            name: "Mrs Mill@",
            description: mode === "pickup" ? "Store pickup order" : "Home delivery order",
            prefill: { name: name, contact: mobile },
            theme: { color: "#b51f2c" },
            handler: function (response) {
                orderPayload.razorpay_payment_id = response.razorpay_payment_id;
                finalizeOrder(orderPayload);
            },
            modal: { ondismiss: function () { showToast("Payment cancelled."); } }
        };

        try {
            const rzp = new Razorpay(options);
            rzp.open();
        } catch (err) {
            showToast("Unable to open payment. Check Razorpay key.", "error");
        }
    });

    function finalizeOrder(payload) {
        payNowBtn.disabled = true;
        payNowText.textContent = "Processing...";

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
                    payNowText.textContent = "Pay Now";
                    return;
                }
                showToast("Order placed successfully!");
                setTimeout(() => { window.location.href = MAIN_URL + "my-orders.php"; }, 900);
            })
            .catch(() => {
                showToast("Unable to connect.", "error");
                payNowBtn.disabled = false;
                payNowText.textContent = "Pay Now";
            });
    }

    /* ---------- INIT ---------- */
    setMode(getCheckedMode());

})();