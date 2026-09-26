/* =========================================================
   MRS MILL@ — CHECKOUT PAGE
   File: ./js/checkout.js
   ========================================================= */

(function () {
    "use strict";

    const MAIN_URL = window.MAIN_URL || "./";

    /* ---------- DOM ---------- */
    const mobileInput       = document.getElementById("coMobile");
    const nameInput         = document.getElementById("coName");

    /* Apartment (searchable) */
    const aptSearch         = document.getElementById("coApartmentSearch");
    const aptResults        = document.getElementById("apartmentResults");
    const aptIdInput        = document.getElementById("coApartmentId");
    const aptCodeInput      = document.getElementById("coApartmentCode");

    /* Division (searchable) */
    const divisionField     = document.getElementById("divisionField");
    const divisionSearch    = document.getElementById("coDivisionSearch");
    const divisionResults   = document.getElementById("divisionResults");
    const divisionInput     = document.getElementById("coDivision");
    const divisionHint      = document.getElementById("divisionHint");

    const subTotalEl        = document.getElementById("subTotal");
    const deliveryEl        = document.getElementById("deliveryCharge");
    const grandTotalEl      = document.getElementById("grandTotal");
    const payNowBtn         = document.getElementById("payNowBtn");
    const mobileHint        = document.getElementById("mobileHint");

    let apartmentsCache     = [];
    let divisionsCache      = [];
    let selectedDivision    = null;
    let subtotal            = Number(window.CART_SUBTOTAL || 0);
    let deliveryCharge      = 0;

    /* ---------- HELPERS ---------- */
    function money(n) {
        return "₹" + Number(n || 0).toFixed(2);
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

    /* =========================================================
       MOBILE → auto lookup previous order
       ========================================================= */
    let mobileTimer = null;
    mobileInput?.addEventListener("input", () => {
        mobileInput.value = mobileInput.value.replace(/[^0-9]/g, "").slice(0, 15);
        clearTimeout(mobileTimer);

        const m = mobileInput.value.trim();
        if (m.length < 10) return;

        mobileTimer = setTimeout(() => {
            fetch(MAIN_URL + "ajax/checkout-lookup-customer.php?mobile=" + encodeURIComponent(m), {
                credentials: "same-origin"
            })
                .then(r => r.json().catch(() => null))
                .then(res => {
                    if (!res || !res.success) return;
                    if (!res.data || !res.data.found) return;

                    if (res.data.name && !nameInput.value.trim()) {
                        nameInput.value = res.data.name;
                    }

                    if (res.data.apartment_id) {
                        aptIdInput.value   = res.data.apartment_id;
                        aptCodeInput.value = res.data.apartment_code || "";
                        aptSearch.value    = res.data.apartment_name || "";

                        loadDivisions(res.data.apartment_id, res.data.division || "");
                    }

                    if (mobileHint) mobileHint.textContent = "Welcome back! Details auto-filled.";
                })
                .catch(() => {});
        }, 400);
    });

    /* =========================================================
       APARTMENT SEARCH
       ========================================================= */
    function loadApartments() {
        fetch(MAIN_URL + "ajax/checkout-apartments.php", { credentials: "same-origin" })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success || !Array.isArray(res.data)) return;
                apartmentsCache = res.data;
            })
            .catch(() => {});
    }
    loadApartments();

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

    aptSearch?.addEventListener("focus", () => renderApartmentResults(aptSearch.value));
    aptSearch?.addEventListener("input", () => renderApartmentResults(aptSearch.value));

    aptResults?.addEventListener("click", (e) => {
        const item = e.target.closest(".mm-co-dd-item");
        if (!item) return;

        aptIdInput.value   = item.dataset.id;
        aptCodeInput.value = item.dataset.code;
        aptSearch.value    = item.dataset.name;
        aptResults.classList.remove("open");

        loadDivisions(item.dataset.id);
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
       DIVISIONS (searchable)
       ========================================================= */
    function loadDivisions(apartmentId, preselect) {
        if (!apartmentId) return;

        fetch(MAIN_URL + "ajax/checkout-divisions.php?apartment_id=" + encodeURIComponent(apartmentId), {
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (!res || !res.success || !Array.isArray(res.data)) {
                    hideDivisionField();
                    return;
                }
                divisionsCache = res.data;
                if (!divisionsCache.length) {
                    hideDivisionField();
                    return;
                }

                /* reset field */
                divisionField.style.display = "block";
                divisionSearch.value = "";
                divisionInput.value  = "";
                divisionHint.textContent = "";
                selectedDivision = null;
                deliveryCharge = 0;
                updateTotals();

                /* preselect if auto-filled */
                if (preselect) {
                    const match = divisionsCache.find(d => String(d.division) === String(preselect));
                    if (match) {
                        selectDivision(match);
                    }
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
                <span>Delivery charge: ₹${Number(d.charge).toFixed(2)}</span>
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
        deliveryCharge = Number(d.charge || 0);
        divisionHint.textContent = "Delivery charge: ₹" + deliveryCharge.toFixed(2);
        updateTotals();
    }

    function updateTotals() {
        subTotalEl.textContent   = money(subtotal);
        deliveryEl.textContent   = money(deliveryCharge);
        grandTotalEl.textContent = money(subtotal + deliveryCharge);
    }
    updateTotals();

    /* =========================================================
       PAY NOW
       ========================================================= */
    payNowBtn?.addEventListener("click", () => {
        const mobile  = mobileInput.value.trim();
        const name    = nameInput.value.trim();
        const aptId   = aptIdInput.value;
        const aptCode = aptCodeInput.value;

        if (!/^[0-9]{10,15}$/.test(mobile)) { showToast("Enter a valid mobile number.", "error"); mobileInput.focus(); return; }
        if (name.length < 3) { showToast("Please enter your name.", "error"); nameInput.focus(); return; }
        if (!aptId) { showToast("Please choose an apartment.", "error"); aptSearch.focus(); return; }
        if (!selectedDivision) { showToast("Please select a division.", "error"); divisionSearch?.focus(); return; }

        const grandTotal = subtotal + deliveryCharge;

        const options = {
            key: window.RAZORPAY_KEY_ID,
            amount: Math.round(grandTotal * 100),
            currency: "INR",
            name: "Mrs Mill@",
            description: "Order payment",
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
                    apartment_id: aptId,
                    apartment_code: aptCode,
                    division: selectedDivision,
                    division_charge: deliveryCharge,
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

})();