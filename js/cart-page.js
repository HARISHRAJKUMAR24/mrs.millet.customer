/* =========================================================
   MRS MILL@ — CART PAGE (live-updating)
   File: ./js/cart-page.js
   Updates qty + totals in place — no page reload.
   ========================================================= */

(function () {
    "use strict";

    const MAIN_URL = (typeof window.MAIN_URL === "string" && window.MAIN_URL) ? window.MAIN_URL : "./";

    const itemsList = document.getElementById("cartItemsList");
    if (!itemsList) return;

    function money(n) { return "₹" + Math.round(Number(n) || 0); }

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
        toast._timer = setTimeout(() => toast.className = "", 2400);
    }

    /* ---------- Refresh every count/total on the page ---------- */
    function updateAllTotals(count, total) {
        /* If home.js is loaded, it paints everything */
        if (typeof window.paintCartUI === "function") {
            window.paintCartUI(count, total);
            return;
        }

        /* Fallback: paint manually (in case home.js missing) */
        const navCart = document.getElementById("navCartCount");
        if (navCart) navCart.textContent = count;

        const stickyBar    = document.getElementById("stickyCartBar");
        const stickyCount  = document.getElementById("stickyCartCount");
        const stickyLabel  = document.getElementById("stickyCartItemLabel");
        const stickyTotal  = document.getElementById("stickyCartTotal");

        if (stickyCount) stickyCount.textContent = count;
        if (stickyLabel) stickyLabel.textContent = count === 1 ? "item" : "items";
        if (stickyTotal) stickyTotal.textContent = money(total);
        if (stickyBar)   stickyBar.style.display = count > 0 ? "flex" : "none";

        const headCount  = document.getElementById("cartHeaderCount");
        const headPlural = document.getElementById("cartHeaderPlural");
        if (headCount)  headCount.textContent = count;
        if (headPlural) headPlural.textContent = count === 1 ? "" : "s";

        const sumSub   = document.getElementById("summarySubtotal");
        const sumTotal = document.getElementById("summaryTotal");
        if (sumSub)   sumSub.textContent = money(total);
        if (sumTotal) sumTotal.textContent = money(total);
    }

    /* ---------- Update row qty + line total in place ---------- */
    function updateRowQty(row, newQty, unitPrice) {
        const qtySpan  = row.querySelector(".js-qty");
        const decBtn   = row.querySelector(".js-dec");
        const priceEl  = row.querySelector(".ct-item-price");

        if (qtySpan) qtySpan.textContent = newQty;
        if (decBtn)  decBtn.disabled = newQty <= 1;
        if (priceEl) priceEl.textContent = money(unitPrice * newQty);
    }

    /* ---------- Fire AJAX ---------- */
    function doAction(productId, variantId, action, buttonEl) {
        if (buttonEl) {
            buttonEl.disabled = true;
            buttonEl.style.opacity = "0.5";
        }

        const fd = new FormData();
        fd.append("product_id", productId);
        fd.append("variant_id", variantId);
        fd.append("action", action);

        fetch(MAIN_URL + "ajax/cart-update.php", {
            method: "POST",
            body: fd,
            credentials: "same-origin"
        })
            .then(r => r.json().catch(() => null))
            .then(res => {
                if (buttonEl) {
                    buttonEl.disabled = false;
                    buttonEl.style.opacity = "";
                }

                if (!res || !res.success) {
                    showToast((res && res.message) || "Update failed.", "error");
                    return;
                }

                const count = Number(res.data.cart_count || 0);
                const total = Number(res.data.cart_total || 0);

                /* Update every counter on the page */
                updateAllTotals(count, total);

                /* Update the row that was clicked */
                const row = itemsList.querySelector(
                    `.ct-item[data-product-id="${productId}"][data-variant-id="${variantId}"]`
                );
                if (!row) return;

                if (action === "remove") {
                    row.style.transition = "opacity .25s ease, transform .25s ease";
                    row.style.opacity = "0";
                    row.style.transform = "translateX(-10px)";
                    setTimeout(() => {
                        row.remove();
                        if (!itemsList.querySelector(".ct-item")) {
                            window.location.reload();
                        }
                    }, 250);
                    showToast("Item removed.");
                } else {
                    const currentQty = parseInt(row.querySelector(".js-qty").textContent, 10) || 1;
                    const unitPrice  = getUnitPrice(row);

                    let newQty = currentQty;
                    if (action === "inc") newQty = currentQty + 1;
                    if (action === "dec") newQty = Math.max(1, currentQty - 1);

                    updateRowQty(row, newQty, unitPrice);
                    showToast(action === "inc" ? "Quantity increased." : "Quantity decreased.");
                }
            })
            .catch(() => {
                if (buttonEl) {
                    buttonEl.disabled = false;
                    buttonEl.style.opacity = "";
                }
                showToast("Unable to connect.", "error");
            });
    }

    /* ---------- Read unit price from the row's meta text ---------- */
    function getUnitPrice(row) {
        const metaStrong = row.querySelector(".ct-item-meta strong");
        if (!metaStrong) return 0;
        const txt = metaStrong.textContent || "";
        const num = Number(txt.replace(/[^0-9.]/g, ""));
        return isFinite(num) ? num : 0;
    }

    /* ---------- Bind click via event delegation ---------- */
    itemsList.addEventListener("click", function (e) {
        const incBtn = e.target.closest(".js-inc");
        const decBtn = e.target.closest(".js-dec");
        const remBtn = e.target.closest(".js-remove");

        if (incBtn) {
            doAction(incBtn.dataset.productId, incBtn.dataset.variantId, "inc", incBtn);
        } else if (decBtn) {
            doAction(decBtn.dataset.productId, decBtn.dataset.variantId, "dec", decBtn);
        } else if (remBtn) {
            doAction(remBtn.dataset.productId, remBtn.dataset.variantId, "remove", remBtn);
        }
    });

    /* ---------- Sync once on page load ---------- */
    fetch(MAIN_URL + "ajax/cart-count.php", { credentials: "same-origin" })
        .then(r => r.json().catch(() => null))
        .then(res => {
            if (res && res.success && res.data) {
                updateAllTotals(
                    Number(res.data.cart_count || 0),
                    Number(res.data.cart_total || 0)
                );
            }
        })
        .catch(() => {});

})();