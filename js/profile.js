/* =========================================================
   MRS MILL@ — PROFILE PAGE
   File: ./js/profile.js
   ========================================================= */

(function () {
    "use strict";

    var MAIN_URL = window.MAIN_URL || "./";
    var ADDRESS_TYPE = String(window.CUSTOMER_ADDRESS_TYPE || "apartment");
    var ORDERS = Array.isArray(window.ORDERS_DATA) ? window.ORDERS_DATA : [];

    /* ---------------- Toast ---------------- */
    function showToast(msg, isErr) {
        var t = document.getElementById("mmToast");
        if (!t) return;
        t.textContent = msg;
        t.className = "show" + (isErr ? " error" : "");
        clearTimeout(t._t);
        t._t = setTimeout(function () { t.className = ""; }, 2600);
    }

    /* ---------------- Tabs ---------------- */
    function activateTab(name) {
        document.querySelectorAll(".pf-tab").forEach(function (t) {
            t.classList.toggle("active", t.dataset.tab === name);
        });
        document.querySelectorAll(".pf-panel").forEach(function (p) {
            p.classList.toggle("active", p.id === "panel-" + name);
        });
    }

    document.querySelectorAll(".pf-tab").forEach(function (tab) {
        tab.addEventListener("click", function () {
            var name = this.dataset.tab;
            activateTab(name);

            try {
                var url = new URL(window.location.href);
                if (name === "overview") {
                    url.searchParams.delete("tab");
                } else {
                    url.searchParams.set("tab", name);
                }
                window.history.replaceState({}, "", url.toString());
            } catch (e) {}
        });
    });

    if (window.INITIAL_TAB) {
        var validTabs = ["overview", "orders", "wallet", "containers", "security"];
        if (validTabs.indexOf(window.INITIAL_TAB) !== -1) {
            activateTab(window.INITIAL_TAB);
        }
    }

    /* =========================================================
       APARTMENT DROPDOWN
       ========================================================= */
    var aptSearch    = document.getElementById("pfAptSearch");
    var aptResults   = document.getElementById("pfAptResults");
    var aptIdInput   = document.getElementById("pfAptId");
    var aptCodeInput = document.getElementById("pfAptCode");
    var divField     = document.getElementById("pfDivField");
    var divSelect    = document.getElementById("pfDivision");
    var divCharge    = document.getElementById("pfDivisionCharge");

    var apartments = Array.isArray(window.APARTMENTS) ? window.APARTMENTS : [];

    function escapeHtml(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;")
            .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    function loadDivisionsForApartment(apt, preselect) {
        if (!divSelect) return;

        var divs = [];
        if (apt) {
            if (Array.isArray(apt.divisions)) {
                divs = apt.divisions;
            } else if (typeof apt.divisions === "string") {
                try { divs = JSON.parse(apt.divisions); } catch (err) { divs = []; }
            }
        }

        if (!divs.length) {
            divSelect.innerHTML = '<option value="">— No divisions available —</option>';
            divCharge.value = "0";
            divField.style.display = "block";
            return;
        }

        divSelect.innerHTML = '<option value="">— Select division —</option>' +
            divs.map(function (d) {
                var dv = String(d.division || "");
                var dc = Number(d.charge || 0);
                var sel = (preselect !== undefined && String(preselect) === dv) ? " selected" : "";
                return '<option value="' + escapeHtml(dv) + '" data-charge="' + dc + '"' + sel + '>' +
                       'Division ' + escapeHtml(dv) + ' · ₹' + Math.round(dc) +
                       '</option>';
            }).join("");

        if (preselect !== undefined && String(preselect) !== "") {
            var opt = divSelect.options[divSelect.selectedIndex];
            divCharge.value = opt ? (opt.dataset.charge || 0) : 0;
        } else {
            divSelect.value = "";
            divCharge.value = "0";
        }

        divField.style.display = "block";
    }

    function renderAptResults(query) {
        if (!aptResults) return;

        var q = (query || "").trim().toLowerCase();
        var list = apartments.filter(function (a) {
            if (!q) return true;
            return (a.apartment_name || "").toLowerCase().indexOf(q) !== -1 ||
                   (a.apartment_code || "").toLowerCase().indexOf(q) !== -1;
        });

        var currentId = String(aptIdInput ? aptIdInput.value : "");

        if (!list.length) {
            aptResults.innerHTML = '<div style="padding:16px;text-align:center;color:#948c82;font-size:12px;">' +
                '<i class="bi bi-search" style="display:block;font-size:18px;color:#c8bfb4;margin-bottom:6px;"></i>' +
                'No apartments found</div>';
        } else {
            aptResults.innerHTML = list.slice(0, 50).map(function (a) {
                var isSel = String(a.id) === currentId;
                var bg = isSel ? "#fff5f5" : "transparent";

                return '<div class="pf-apt-item" ' +
                       'data-id="' + a.id + '" ' +
                       'data-code="' + escapeHtml(a.apartment_code) + '" ' +
                       'data-name="' + escapeHtml(a.apartment_name) + '" ' +
                       'style="padding:11px 14px;border-radius:10px;cursor:pointer;font-size:12.5px;font-weight:700;color:#302923;border-bottom:1px solid #f7f2ec;background:' + bg + ';"' +
                       ' onmouseover="this.style.background=\'#fdfaf4\'"' +
                       ' onmouseout="this.style.background=\'' + bg + '\'">' +
                            '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">' +
                                '<div>' +
                                    '<div>' + escapeHtml(a.apartment_name) + '</div>' +
                                    '<div style="font-size:10.5px;color:#948c82;font-weight:600;margin-top:2px;">#' + escapeHtml(a.apartment_code) + '</div>' +
                                '</div>' +
                                (isSel ? '<i class="bi bi-check-circle-fill" style="color:#b51f2c;font-size:14px;"></i>' : '') +
                            '</div>' +
                       '</div>';
            }).join("");
        }
        aptResults.style.display = "block";
    }

    aptSearch && aptSearch.addEventListener("focus", function () { renderAptResults(""); });
    aptSearch && aptSearch.addEventListener("input", function () { renderAptResults(aptSearch.value); });

    aptResults && aptResults.addEventListener("click", function (e) {
        var item = e.target.closest(".pf-apt-item");
        if (!item) return;

        aptIdInput.value   = item.dataset.id;
        aptCodeInput.value = item.dataset.code;
        aptSearch.value    = item.dataset.name;

        var apt = null;
        for (var i = 0; i < apartments.length; i++) {
            if (String(apartments[i].id) === String(item.dataset.id)) {
                apt = apartments[i];
                break;
            }
        }

        loadDivisionsForApartment(apt, "");
        aptResults.style.display = "none";
    });

    document.addEventListener("click", function (e) {
        if (!e.target.closest("#pfAptSearch") && !e.target.closest("#pfAptResults")) {
            if (aptResults) aptResults.style.display = "none";
        }
    });

    divSelect && divSelect.addEventListener("change", function () {
        var opt = this.options[this.selectedIndex];
        divCharge.value = opt ? (opt.dataset.charge || 0) : 0;
    });

    (function initialDivisionSync() {
        if (!aptIdInput || !aptIdInput.value) return;
        var apt = null;
        for (var i = 0; i < apartments.length; i++) {
            if (String(apartments[i].id) === String(aptIdInput.value)) {
                apt = apartments[i];
                break;
            }
        }
        if (apt) {
            var savedDivision = (window.CUSTOMER_DIVISION || "");
            loadDivisionsForApartment(apt, savedDivision);
        }
    })();

    /* =========================================================
       PASSWORD SHOW / HIDE
       ========================================================= */
    document.querySelectorAll(".pf-eye-btn").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var targetId = this.dataset.eye;
            var input = document.getElementById(targetId);
            if (!input) return;

            var icon = this.querySelector("i");
            var isPwd = input.type === "password";

            input.type = isPwd ? "text" : "password";

            if (icon) {
                icon.className = isPwd ? "bi bi-eye-slash" : "bi bi-eye";
            }
            this.classList.toggle("active", isPwd);
            this.setAttribute("aria-label", isPwd ? "Hide password" : "Show password");
        });
    });

    /* =========================================================
       RESET
       ========================================================= */
    var resetBtn = document.getElementById("pfResetBtn");
    resetBtn && resetBtn.addEventListener("click", function () {
        location.reload();
    });

    /* =========================================================
       SAVE PROFILE
       ========================================================= */
    var profileForm = document.getElementById("profileForm");
    profileForm && profileForm.addEventListener("submit", function (e) {
        e.preventDefault();

        var name = document.getElementById("pfName").value.trim();
        if (name.length < 3) { showToast("Please enter a valid name.", true); return; }

        var fd = new FormData();
        fd.append("full_name", name);

        if (ADDRESS_TYPE === "apartment" && aptIdInput && divSelect) {
            var aptId = aptIdInput.value;
            var div   = divSelect.value;

            if (!aptId) { showToast("Please choose an apartment.", true); return; }
            if (!div)   { showToast("Please choose a division.", true); return; }

            fd.append("apartment_id", aptId);
            fd.append("apartment_code", aptCodeInput.value);
            fd.append("division", div);
            fd.append("division_charge", divCharge.value);
        }

        var btn = this.querySelector("button[type=submit]");
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving...';

        fetch(MAIN_URL + "ajax/customer-update-profile.php", {
            method: "POST",
            body: fd,
            credentials: "same-origin"
        })
        .then(function (r) { return r.json().catch(function () { return null; }); })
        .then(function (res) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Save Changes';

            if (!res || !res.success) {
                showToast((res && res.message) || "Failed to save.", true);
                return;
            }
            showToast("Profile updated successfully.");
            setTimeout(function () { location.reload(); }, 900);
        })
        .catch(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Save Changes';
            showToast("Unable to connect.", true);
        });
    });

    /* =========================================================
       SET NEW PASSWORD
       ========================================================= */
    var passwordForm = document.getElementById("passwordForm");
    passwordForm && passwordForm.addEventListener("submit", function (e) {
        e.preventDefault();

        var newPass = document.getElementById("pfNewPass").value;
        var confirm = document.getElementById("pfConfirmPass").value;

        if (!newPass)            { showToast("Please enter a new password.", true); return; }
        if (newPass.length < 3)  { showToast("Password must be at least 3 characters.", true); return; }
        if (newPass !== confirm) { showToast("Passwords do not match.", true); return; }

        var fd = new FormData();
        fd.append("new_password", newPass);

        var btn = this.querySelector("button[type=submit]");
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Updating...';

        fetch(MAIN_URL + "ajax/customer-change-password.php", {
            method: "POST",
            body: fd,
            credentials: "same-origin"
        })
        .then(function (r) { return r.json().catch(function () { return null; }); })
        .then(function (res) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-shield-check"></i> Update Password';

            if (!res || !res.success) {
                showToast((res && res.message) || "Failed to update password.", true);
                return;
            }
            showToast("Password updated successfully.");
            passwordForm.reset();

            document.querySelectorAll(".pf-eye-btn").forEach(function (btn) {
                var inp = document.getElementById(btn.dataset.eye);
                if (inp) inp.type = "password";
                btn.classList.remove("active");
                var ic = btn.querySelector("i");
                if (ic) ic.className = "bi bi-eye";
            });
        })
        .catch(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-shield-check"></i> Update Password';
            showToast("Unable to connect.", true);
        });
    });

    /* =========================================================
       ORDER DETAILS MODAL
       ========================================================= */
    var modal       = document.getElementById("pfOrderModal");
    var modalClose  = document.getElementById("pfModalClose");
    var modalCode   = document.getElementById("pfModalOrderCode");
    var modalDate   = document.getElementById("pfModalOrderDate");
    var modalBody   = document.getElementById("pfModalBody");

    function moneyFmt(n) {
        return "₹" + Math.round(Number(n) || 0);
    }

    function escHtml(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;")
            .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    function fmtDateTime(s) {
        if (!s) return "—";
        try {
            var dt = new Date(s.replace(" ", "T"));
            if (isNaN(dt.getTime())) return s;
            return dt.toLocaleString("en-IN", {
                day: "2-digit", month: "short", year: "numeric",
                hour: "2-digit", minute: "2-digit"
            });
        } catch (e) { return s; }
    }

    function openOrderModal(order) {
        if (!order || !modal) return;

        modalCode.textContent = "#" + (order.order_code || "");
        modalDate.textContent = fmtDateTime(order.created_at);

        var statusClass = String(order.status || "").toLowerCase();
        var payClass    = String(order.payment_status || "").toLowerCase();

        /* Products */
        var productsHtml = "";
        if (Array.isArray(order.products) && order.products.length) {
            productsHtml = order.products.map(function (p) {
                var thumb = p.image
                    ? '<img src="' + escHtml(p.image) + '" alt="" onerror="this.style.display=\'none\';this.parentElement.innerHTML=\'📦\';">'
                    : '📦';
                var meta = [];
                if (p.variant_name) meta.push(escHtml(p.variant_name));
                if (p.variant_qty)  meta.push(escHtml(p.variant_qty));
                meta.push("Qty " + (p.qty || 1));
                return '<div class="pf-modal-prod">' +
                       '<div class="pf-modal-thumb">' + thumb + '</div>' +
                       '<div class="pf-modal-prod-info">' +
                           '<p class="pf-modal-prod-name">' + escHtml(p.name || "") + '</p>' +
                           '<p class="pf-modal-prod-meta">' + meta.join(" · ") + '</p>' +
                       '</div>' +
                       '<div class="pf-modal-prod-total">' + moneyFmt(p.line_total || (p.price * p.qty)) + '</div>' +
                       '</div>';
            }).join("");
        } else {
            productsHtml = '<div style="padding:14px;text-align:center;color:#948c82;font-size:12px;">No items</div>';
        }

        /* Address */
        var addr = order.address || {};
        var addressHtml = "";
        if (order.delivery_mode === "pickup") {
            addressHtml = '<div class="pf-modal-row">' +
                          '<span class="lbl">Branch</span>' +
                          '<span class="val">' + escHtml(order.pickup_branch_name || "—") + '</span>' +
                          '</div>';
        } else if (order.address_type === "individual_house") {
            addressHtml = '<div class="pf-modal-row">' +
                          '<span class="lbl">Type</span>' +
                          '<span class="val"><span class="pf-badge individual"><i class="bi bi-house-door"></i> Individual House</span></span>' +
                          '</div>' +
                          '<div class="pf-modal-row">' +
                          '<span class="lbl">Address</span>' +
                          '<span class="val">' + escHtml(addr.full_address || "—") + '</span>' +
                          '</div>';
        } else {
            addressHtml = '<div class="pf-modal-row">' +
                          '<span class="lbl">Apartment</span>' +
                          '<span class="val">' + escHtml(addr.apartment_name || "—") +
                          (addr.apartment_code ? ' #' + escHtml(addr.apartment_code) : '') + '</span>' +
                          '</div>' +
                          '<div class="pf-modal-row">' +
                          '<span class="lbl">Division</span>' +
                          '<span class="val">' + escHtml(addr.division_label || "—") + '</span>' +
                          '</div>';
        }

        /* Delivery boy */
        var dboyHtml = "";
        if (order.delivery_mode === "pickup") {
            dboyHtml = '<span class="pf-dboy-pickup"><i class="bi bi-shop"></i> Store Pickup</span>';
        } else if (order.delivery_boy_name) {
            dboyHtml = '<div class="pf-dboy">' +
                       '<div class="pf-dboy-avatar">' + escHtml(String(order.delivery_boy_name).charAt(0).toUpperCase()) + '</div>' +
                       '<div class="pf-dboy-info">' +
                           '<div class="pf-dboy-name">' + escHtml(order.delivery_boy_name) + '</div>' +
                           (order.delivery_boy_code ? '<div class="pf-dboy-code">#' + escHtml(order.delivery_boy_code) + '</div>' : '') +
                       '</div>' +
                       '</div>';
        } else {
            dboyHtml = '<span class="pf-dboy-empty"><i class="bi bi-dash-circle"></i> Not assigned</span>';
        }

        /* Totals */
        var totalsHtml = '';
        totalsHtml += '<div class="pf-modal-trow"><span>Subtotal</span><strong>' + moneyFmt(order.subtotal) + '</strong></div>';
        if (Number(order.division_charge) > 0) {
            totalsHtml += '<div class="pf-modal-trow"><span>Delivery charge</span><strong>' + moneyFmt(order.division_charge) + '</strong></div>';
        }
        if (Number(order.tax_amount) > 0) {
            totalsHtml += '<div class="pf-modal-trow"><span>Tax (' + Number(order.tax_rate) + '%)</span><strong>' + moneyFmt(order.tax_amount) + '</strong></div>';
        }
        totalsHtml += '<div class="pf-modal-trow grand"><span>Total</span><strong>' + moneyFmt(order.total_amount) + '</strong></div>';

        /* Order note */
        var noteHtml = "";
        if (order.order_note && String(order.order_note).trim() !== "") {
            noteHtml = '<div class="pf-modal-section">' +
                       '<h4 class="pf-modal-title"><i class="bi bi-chat-left-text"></i> Instructions</h4>' +
                       '<div class="pf-modal-note">' + escHtml(order.order_note) + '</div>' +
                       '</div>';
        }

        modalBody.innerHTML =
            '<div class="pf-modal-section">' +
                '<h4 class="pf-modal-title"><i class="bi bi-info-circle"></i> Status</h4>' +
                '<div class="pf-modal-info">' +
                    '<div class="pf-modal-row">' +
                        '<span class="lbl">Order Status</span>' +
                        '<span class="val"><span class="pf-badge ' + statusClass + '">' + escHtml(String(order.status || "").toUpperCase()) + '</span></span>' +
                    '</div>' +
                    '<div class="pf-modal-row">' +
                        '<span class="lbl">Payment</span>' +
                        '<span class="val"><span class="pf-badge ' + (payClass === "paid" ? "received" : "pending") + '">' + escHtml(String(order.payment_status || "").toUpperCase()) + '</span></span>' +
                    '</div>' +
                    '<div class="pf-modal-row">' +
                        '<span class="lbl">Delivered By</span>' +
                        '<span class="val">' + dboyHtml + '</span>' +
                    '</div>' +
                '</div>' +
            '</div>' +

            '<div class="pf-modal-section">' +
                '<h4 class="pf-modal-title"><i class="bi bi-geo-alt"></i> Delivery Address</h4>' +
                '<div class="pf-modal-info">' + addressHtml + '</div>' +
            '</div>' +

            '<div class="pf-modal-section">' +
                '<h4 class="pf-modal-title"><i class="bi bi-basket3"></i> Items</h4>' +
                '<div class="pf-modal-products">' + productsHtml + '</div>' +
            '</div>' +

            '<div class="pf-modal-section">' +
                '<h4 class="pf-modal-title"><i class="bi bi-receipt"></i> Payment Summary</h4>' +
                '<div class="pf-modal-totals">' + totalsHtml + '</div>' +
            '</div>' +

            noteHtml;

        modal.classList.add("show");
        document.body.style.overflow = "hidden";
    }

    function closeOrderModal() {
        modal.classList.remove("show");
        document.body.style.overflow = "";
    }

    modalClose && modalClose.addEventListener("click", closeOrderModal);
    modal && modal.addEventListener("click", function (e) {
        if (e.target === modal) closeOrderModal();
    });
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && modal && modal.classList.contains("show")) closeOrderModal();
    });

    document.querySelectorAll(".js-order-row").forEach(function (row) {
        row.addEventListener("click", function () {
            var idx = Number(this.dataset.orderIndex);
            var order = ORDERS[idx];
            if (order) openOrderModal(order);
        });
    });

})();