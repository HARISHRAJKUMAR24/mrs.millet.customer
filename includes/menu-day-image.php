<?php

$menuTimezone = new DateTimeZone('Asia/Kolkata');
$menuNow = new DateTimeImmutable('now', $menuTimezone);

$todayDate = $menuNow->format('Y-m-d');
$tomorrowDate = $menuNow->modify('+1 day')->format('Y-m-d');

$todayMenuAvailable = false;
$tomorrowMenuAvailable = false;

$todayOfferImg = !empty($settings['today_offer_image'])
    ? ADMIN_URL . $settings['today_offer_image']
    : '';

$tomorrowOfferImg = !empty($settings['tomorrow_offer_image'])
    ? ADMIN_URL . $settings['tomorrow_offer_image']
    : '';

try {

    $stmt = $pdo->query("
        SELECT menu_code, menu_name, start_at, end_at
        FROM menus
        WHERE status = 1
        ORDER BY start_at ASC
    ");

    $scheduledMenus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($scheduledMenus as $menu) {

        if (empty($menu['start_at']) || empty($menu['end_at'])) {
            continue;
        }

        $start = new DateTimeImmutable(
            $menu['start_at'],
            $menuTimezone
        );

        $end = new DateTimeImmutable(
            $menu['end_at'],
            $menuTimezone
        );

        // Ignore menus which have not started or have expired.
        if ($menuNow < $start || $menuNow > $end) {
            continue;
        }

        $endDate = $end->format('Y-m-d');

        // Menu ending today = Today's Menu
        if ($endDate === $todayDate) {
            $todayMenuAvailable = true;
        }

        // Menu ending tomorrow = Tomorrow's Menu
        if ($endDate === $tomorrowDate) {
            $tomorrowMenuAvailable = true;
        }
    }
} catch (Throwable $e) {

    error_log('Menu schedule error: ' . $e->getMessage());

    $todayMenuAvailable = false;
    $tomorrowMenuAvailable = false;
}

?>


<?php if ($todayOfferImg || $tomorrowOfferImg): ?>

    <!-- ================= OFFER CARDS ================= -->

    <section class="mm-offers">

        <div class="mm-container">

            <div class="mm-offers-grid">

                <!-- LEFT = TOMORROW OFFER -->

                <div class="mm-offer-card mm-offer-tomorrow">

                    <?php if ($tomorrowOfferImg): ?>

                        <img
                            src="<?= htmlspecialchars($tomorrowOfferImg, ENT_QUOTES, 'UTF-8') ?>"
                            alt="Tomorrow Offer">

                    <?php else: ?>

                        <div class="mm-offer-placeholder">
                            <i class="bi bi-sunrise"></i>
                        </div>

                    <?php endif; ?>

                    <?php if ($tomorrowMenuAvailable): ?>

                        <div class="mm-offer-btn-wrap">

                            <a href="tomorrow-menu.php"
                                class="mm-offer-btn mm-offer-btn-tomorrow">

                                <i class="bi bi-arrow-right"></i>
                                <span>View Menu</span>

                            </a>

                        </div>

                    <?php endif; ?>

                </div>


                <!-- RIGHT = TODAY OFFER -->

                <div class="mm-offer-card mm-offer-today">

                    <?php if ($todayOfferImg): ?>

                        <img
                            src="<?= htmlspecialchars($todayOfferImg, ENT_QUOTES, 'UTF-8') ?>"
                            alt="Today Offer">

                    <?php else: ?>

                        <div class="mm-offer-placeholder">
                            <i class="bi bi-fire"></i>
                        </div>

                    <?php endif; ?>

                    <?php if ($todayMenuAvailable): ?>

                        <div class="mm-offer-btn-wrap">

                            <a href="today-menu.php"
                                class="mm-offer-btn mm-offer-btn-today">

                                <i class="bi bi-arrow-right"></i>
                                <span>View Menu</span>

                            </a>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </section>


    <!-- ================= OFFER CARD STYLES ================= -->

    <style>
        .mm-offers {
            padding: 22px 0 0;
        }

        /* Always 2 columns */

        .mm-offers-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .mm-offer-card {
            display: flex;
            flex-direction: column;
            background: transparent;
            min-width: 0;
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

        /* Button alignment */

        .mm-offer-btn-wrap {
            display: flex;
            justify-content: center;
            margin-top: 12px;
        }

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

        /* Today — Red */

        .mm-offer-btn-today {
            background: linear-gradient(135deg,
                    #d42a3a 0%,
                    #b51f2c 100%);
        }

        /* Tomorrow — Blue */

        .mm-offer-btn-tomorrow {
            background: linear-gradient(135deg,
                    #3b82f6 0%,
                    #1e40af 100%);
        }

        .mm-offer-btn i {
            font-size: 12px;
        }

        /* Mobile responsive */

        @media (max-width: 640px) {

            .mm-offers {
                padding: 16px 0 0;
            }

            .mm-offers-grid {
                gap: 10px;
            }

            .mm-offer-btn {
                padding: 8px 12px;
                font-size: 10px;
                gap: 4px;
            }

            .mm-offer-card img,
            .mm-offer-placeholder {
                aspect-ratio: 4 / 3;
            }

        }
    </style>

<?php endif; ?>