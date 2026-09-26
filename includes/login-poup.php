    <div class="cust-overlay" id="loginOverlay" aria-hidden="true">
        <div class="cust-modal">
            <button type="button" class="cust-close" id="loginClose">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                    <path d="M18 6L6 18M6 6l12 12" />
                </svg>
            </button>

            <div class="cust-head">
                <div class="cust-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="6" y="2" width="12" height="20" rx="2" />
                        <path d="M12 18h.01" />
                    </svg>
                </div>
                <h3>Enter your mobile</h3>
                <p>We'll check if you're already with us</p>
            </div>

            <div class="cust-body">
                <form id="loginForm" novalidate>
                    <div class="cust-field">
                        <label>Mobile Number</label>
                        <div class="cust-input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.2-1.2a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.7 2z" />
                            </svg>
                            <input type="tel" class="cust-input" id="loginMobile"
                                placeholder="10-digit mobile" maxlength="15" inputmode="numeric" autocomplete="tel">
                        </div>
                    </div>

                    <button type="submit" class="cust-btn" id="loginBtn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14" />
                            <path d="M13 6l6 6-6 6" />
                        </svg>
                        <span id="loginBtnText">Continue</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
