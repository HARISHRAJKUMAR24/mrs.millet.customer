<?php
$todayOfferImg    = !empty($settings['today_offer_image'])    ? ADMIN_URL . $settings['today_offer_image']    : '';
$tomorrowOfferImg = !empty($settings['tomorrow_offer_image']) ? ADMIN_URL . $settings['tomorrow_offer_image'] : '';
?>
<?php if ($todayOfferImg || $tomorrowOfferImg): ?>
    <!-- ================= OFFER CARDS ================= -->
    <section class="mm-offers">
        <div class="mm-container">
            <div class="mm-offers-grid">

                <!-- LEFT = TOMORROW OFFER -->
                <div class="mm-offer-card mm-offer-tomorrow">
                    <?php if ($tomorrowOfferImg): ?>
                        <img src="<?= htmlspecialchars($tomorrowOfferImg) ?>" alt="Tomorrow Offer">
                    <?php else: ?>
                        <div class="mm-offer-placeholder">
                            <i class="bi bi-sunrise"></i>
                        </div>
                    <?php endif; ?>
                    <div class="mm-offer-btn-wrap">
                        <a href="tomorrow-menu.php" class="mm-offer-btn mm-offer-btn-tomorrow">
                            <i class="bi bi-arrow-right"></i>
                            <span>View Menu</span>
                        </a>
                    </div>
                </div>

                <!-- RIGHT = TODAY OFFER -->
                <div class="mm-offer-card mm-offer-today">
                    <?php if ($todayOfferImg): ?>
                        <img src="<?= htmlspecialchars($todayOfferImg) ?>" alt="Today Offer">
                    <?php else: ?>
                        <div class="mm-offer-placeholder">
                            <i class="bi bi-fire"></i>
                        </div>
                    <?php endif; ?>
                    <div class="mm-offer-btn-wrap">
                        <a href="today-menu.php" class="mm-offer-btn mm-offer-btn-today">
                            <i class="bi bi-arrow-right"></i>
                            <span>View Menu</span>
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <style>
        .mm-offers {
            padding: 22px 0 0;
        }

        /* ✅ Always 2 columns (side-by-side) */
        .mm-offers-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .mm-offer-card {
            display: flex;
            flex-direction: column;
            background: transparent;
        }

        .mm-offer-card img {
            display: block;
            width: 100%;
            aspect-ratio: 16 / 7;
            object-fit: contain;
            object-position: center;
            border-radius: 18px;
        }

        .mm-offer-placeholder {
            width: 100%;
            aspect-ratio: 16 / 7;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 18px;
            background: #faf7f0;
        }

        .mm-offer-placeholder i {
            font-size: 34px;
            color: #d5cbbd;
        }

        /* Centered button area */
        .mm-offer-btn-wrap {
            display: flex;
            justify-content: center;
            margin-top: 12px;
        }

        /* ✅ Removed shadows, kept layout */
        .mm-offer-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .02em;
            color: #fff;
            border: none;
            transition: transform .15s ease, filter .15s ease;
            cursor: pointer;
        }

        .mm-offer-btn:hover {
            transform: translateY(-1px);
            filter: brightness(1.06);
        }

        /* TODAY button — red gradient (No shadow) */
        .mm-offer-btn-today {
            background: linear-gradient(135deg, #d42a3a 0%, #b51f2c 100%);
        }

        /* TOMORROW button — blue gradient (No shadow) */
        .mm-offer-btn-tomorrow {
            background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
        }

        .mm-offer-btn i {
            font-size: 12px;
        }

        /* ✅ Responsive adjustments (Still side-by-side, text visible) */
        @media (max-width: 640px) {
            .mm-offers {
                padding: 16px 0 0;
            }
            
            .mm-offers-grid {
                gap: 10px; /* Tighter gap on mobile */
            }

            /* Slightly adjust button size for mobile */
            .mm-offer-btn {
                padding: 8px 12px;
                font-size: 10px; /* Smaller text to fit side-by-side */
                gap: 4px;
            }

            /* Tweak aspect ratio for mobile so they fit nicely side-by-side */
            .mm-offer-card img,
            .mm-offer-placeholder {
                aspect-ratio: 4 / 3;
            }
        }
    </style>
<?php endif; ?>