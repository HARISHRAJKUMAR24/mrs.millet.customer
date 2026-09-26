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
                <h3>Almost there!</h3>
                <p>Just tell us your name</p>
            </div>

            <div class="cust-body">
                <form id="registerForm" novalidate>
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
