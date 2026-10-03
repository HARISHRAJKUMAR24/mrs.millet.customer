<!-- ================= REGISTER POPUP ================= -->
<div class="cust-overlay" id="registerOverlay" aria-hidden="true">
    <div class="cust-modal">
        <button type="button" class="cust-close" id="registerClose">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                <path d="M18 6L6 18M6 6l12 12" />
            </svg>
        </button>

        <div class="cust-head">
            <div class="cust-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="10" cy="8" r="4" />
                    <path d="M2 21c0-4 3.6-6 8-6" />
                    <path d="M19 8v6M16 11h6" />
                </svg>
            </div>
            <h3>Create your account</h3>
            <p>Just a few quick details</p>
        </div>

        <div class="cust-body">
            <form id="registerForm" novalidate>

                <!-- Mobile -->
                <div class="cust-field">
                    <label>Mobile Number</label>
                    <div class="cust-input-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.2-1.2a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.7 2z" />
                        </svg>
                        <input type="tel" class="cust-input" id="regMobile"
                            placeholder="10-digit mobile" maxlength="15" inputmode="numeric" readonly>
                    </div>
                </div>

                <!-- Full name -->
                <div class="cust-field">
                    <label>Full Name</label>
                    <div class="cust-input-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="8" r="4" />
                            <path d="M4 21c0-4 4-6 8-6s8 2 8 6" />
                        </svg>
                        <input type="text" class="cust-input" id="regName"
                            placeholder="Enter your name" maxlength="150" autocomplete="name">
                    </div>
                </div>

                <!-- Password with eye toggle -->
                <div class="cust-field">
                    <label>Create Password</label>
                    <div class="cust-input-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" />
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                        <input type="password" class="cust-input" id="regPassword"
                            placeholder="Min 3 characters" maxlength="100" autocomplete="new-password">
                        <button type="button" class="pw-toggle" data-target="regPassword" aria-label="Show password">
                            <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                            <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" />
                                <line x1="1" y1="1" x2="23" y2="23" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Apartment (searchable OR free-typed) -->
                <div class="cust-field" style="position:relative;">
                    <label>Apartment / Community</label>
                    <div class="cust-input-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 21V8l9-5 9 5v13" />
                            <path d="M9 21V12h6v9" />
                        </svg>
                        <input type="text" class="cust-input" id="regApartmentSearch"
                            placeholder="Search or type your apartment..." autocomplete="off">
                    </div>
                    <input type="hidden" id="regApartmentId">
                    <input type="hidden" id="regApartmentCode">
                    <div id="regApartmentList" class="mm-co-dropdown" role="listbox" style="display:none;"></div>
                    <p class="mm-co-hint" id="regApartmentHint"></p>
                </div>

                <!-- Division (select OR free-typed) -->
                <div class="cust-field">
                    <label>Division</label>
                    <div class="cust-input-wrap" id="regDivisionWrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7" rx="1" />
                            <rect x="14" y="3" width="7" height="7" rx="1" />
                            <rect x="3" y="14" width="7" height="7" rx="1" />
                            <rect x="14" y="14" width="7" height="7" rx="1" />
                        </svg>
                        <select class="cust-input" id="regDivision">
                            <option value="">Select apartment first</option>
                        </select>
                        <input type="text" class="cust-input" id="regDivisionText"
                               placeholder="Type your division (e.g. 4, Block B)"
                               maxlength="100" style="display:none;">
                    </div>
                    <p class="mm-co-hint" id="regDivisionHint"></p>
                </div>

                <button type="submit" class="cust-btn" id="registerBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 6L9 17l-5-5" />
                    </svg>
                    <span id="registerBtnText">Create Account</span>
                </button>
            </form>
        </div>
    </div>
</div>

<style>
    /* ===== Eye toggle button ===== */
    .pw-toggle {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        width: 34px;
        height: 34px;
        border: 0;
        background: transparent;
        color: #948c82;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: .15s ease;
        padding: 0;
    }
    .pw-toggle:hover {
        background: #fbe8e9;
        color: #b51f2c;
    }
    .pw-toggle svg {
        width: 17px;
        height: 17px;
    }

    /* Make room for the eye icon inside password input */
    .cust-input-wrap:has(.pw-toggle) .cust-input {
        padding-right: 46px;
    }

    #regApartmentList {
        position: absolute;
        left: 0;
        right: 0;
        top: calc(100% + 4px);
        max-height: 220px;
        overflow-y: auto;
        background: #fff;
        border: 1.5px solid #ece5da;
        border-radius: 12px;
        box-shadow: 0 15px 35px rgba(0,0,0,.12);
        z-index: 30;
    }
    #regApartmentList .mm-co-dd-item {
        padding: 10px 14px;
        cursor: pointer;
        border-bottom: 1px solid #f7f2ec;
        font-size: 12.5px;
    }
    #regApartmentList .mm-co-dd-item:last-child { border-bottom: 0; }
    #regApartmentList .mm-co-dd-item:hover { background: #fff5f5; }
    #regApartmentList .mm-co-dd-item strong { display: block; color: #302923; font-weight: 700; }
    #regApartmentList .mm-co-dd-item span { font-size: 11px; color: #948c82; }

    #regDivision {
        padding-left: 42px;
        appearance: none;
        -webkit-appearance: none;
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23948c82' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
        background-repeat: no-repeat;
        background-position: right 14px center;
        background-size: 14px;
        cursor: pointer;
    }
    #regDivision:disabled {
        opacity: .6;
        cursor: not-allowed;
        background: #f7f2ec;
    }
</style>