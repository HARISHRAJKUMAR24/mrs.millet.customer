/* =========================================================
   MRS MILL@ — PROFILE PAGE
   File: ./js/profile.js
   ========================================================= */

(function () {
    "use strict";

    var MAIN_URL = window.MAIN_URL || "./";

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
            aptResults.innerHTML =
                '<div style="padding:16px;text-align:center;color:#948c82;font-size:12px;">' +
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
                       'style="padding:11px 14px;border-radius:10px;cursor:pointer;font-size:12.5px;font-weight:700;color:#302923;border-bottom:1px solid #f7f2ec;background:' + bg + ';transition:background .15s;"' +
                       ' onmouseover="this.style.background=\'#fdfaf4\'"' +
                       ' onmouseout="this.style.background=\'' + bg + '\'">' +

                            '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">' +
                                '<div>' +
                                    '<div>' + escapeHtml(a.apartment_name) + '</div>' +
                                    '<div style="font-size:10.5px;color:#948c82;font-weight:600;margin-top:2px;">' +
                                        '#' + escapeHtml(a.apartment_code) +
                                    '</div>' +
                                '</div>' +
                                (isSel ? '<i class="bi bi-check-circle-fill" style="color:#b51f2c;font-size:14px;"></i>' : '') +
                            '</div>' +
                       '</div>';
            }).join("");
        }
        aptResults.style.display = "block";
    }

    aptSearch && aptSearch.addEventListener("focus", function () {
        renderAptResults("");
    });

    aptSearch && aptSearch.addEventListener("input", function () {
        renderAptResults(aptSearch.value);
    });

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
       PASSWORD SHOW / HIDE (eye icon)
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

        var name  = document.getElementById("pfName").value.trim();
        var aptId = aptIdInput.value;
        var div   = divSelect.value;

        if (name.length < 3) { showToast("Please enter a valid name.", true); return; }
        if (!aptId)          { showToast("Please choose an apartment.", true); return; }
        if (!div)            { showToast("Please choose a division.", true); return; }

        var fd = new FormData();
        fd.append("full_name", name);
        fd.append("apartment_id", aptId);
        fd.append("apartment_code", aptCodeInput.value);
        fd.append("division", div);
        fd.append("division_charge", divCharge.value);

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
       SET NEW PASSWORD (no old password required)
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

})();