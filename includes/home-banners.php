 <!-- =====================================================
             BANNERS (simple responsive)
        ====================================================== -->
        <?php if (!empty($homeBanners)): ?>
            <section class="mm-banners">
                <div class="mm-container">
                    <?php if (count($homeBanners) === 1): ?>
                        <div class="mm-banner-single">
                            <img src="<?= htmlspecialchars($homeBanners[0]) ?>" alt="Banner">
                        </div>
                    <?php else: ?>
                        <div class="mm-banner-slider">
                            <?php foreach ($homeBanners as $bUrl): ?>
                                <div class="mm-banner-slide">
                                    <img src="<?= htmlspecialchars($bUrl) ?>" alt="Banner">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($homeBanners) > 1): ?>
                            <div class="mm-banner-dots" id="bannerDots">
                                <?php foreach ($homeBanners as $i => $bUrl): ?>
                                    <button type="button"
                                        class="mm-banner-dot <?= $i === 0 ? 'active' : '' ?>"
                                        data-slide="<?= $i ?>"
                                        aria-label="Go to banner <?= $i + 1 ?>"></button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
        <script>
            (function() {
                "use strict";
                const slider = document.querySelector(".mm-banner-slider");
                const dots = document.querySelectorAll(".mm-banner-dot");
                if (!slider || dots.length === 0) return;

                let current = 0;
                const total = dots.length;
                let timer = null;

                function goTo(index) {
                    if (index < 0) index = total - 1;
                    if (index >= total) index = 0;
                    current = index;
                    slider.scrollTo({
                        left: slider.clientWidth * index,
                        behavior: "smooth"
                    });
                    dots.forEach((d, i) => d.classList.toggle("active", i === index));
                }

                function next() {
                    goTo(current + 1);
                }

                function startAuto() {
                    stopAuto();
                    if (total > 1) timer = setInterval(next, 5000);
                }

                function stopAuto() {
                    if (timer) {
                        clearInterval(timer);
                        timer = null;
                    }
                }

                dots.forEach((dot) => {
                    dot.addEventListener("click", () => {
                        goTo(Number(dot.dataset.slide));
                        startAuto();
                    });
                });

                /* Sync dots on manual swipe */
                slider.addEventListener("scroll", () => {
                    const index = Math.round(slider.scrollLeft / slider.clientWidth);
                    if (index !== current && index >= 0 && index < total) {
                        current = index;
                        dots.forEach((d, i) => d.classList.toggle("active", i === index));
                    }
                });

                /* Pause on hover */
                slider.addEventListener("mouseenter", stopAuto);
                slider.addEventListener("mouseleave", startAuto);

                /* Touch pause on drag */
                let touchTimer = null;
                slider.addEventListener("touchstart", () => {
                    stopAuto();
                    clearTimeout(touchTimer);
                }, {
                    passive: true
                });
                slider.addEventListener("touchend", () => {
                    touchTimer = setTimeout(startAuto, 4000);
                }, {
                    passive: true
                });

                startAuto();
            })();
        </script>

        <style>
            /* =========================================================
   BANNERS — SIMPLE, RESPONSIVE, NO SHADOW
   ========================================================= */
            .mm-banners {
                padding: 20px 0 0;
            }

            .mm-banner-single {
                border-radius: 16px;
                overflow: hidden;
                
                
            }

            .mm-banner-single img {
                width: 100%;
                height: auto;
                display: block;
                object-fit: contain;
            }

            .mm-banner-slider {
                display: flex;
                overflow-x: auto;
                scroll-snap-type: x mandatory;
                scrollbar-width: none;
                -webkit-overflow-scrolling: touch;
                border-radius: 16px;
               
                
            }

            .mm-banner-slider::-webkit-scrollbar {
                display: none;
            }

            .mm-banner-slide {
                flex: 0 0 100%;
                scroll-snap-align: start;
                aspect-ratio: 16 / 7;
            }

            .mm-banner-slide img {
                width: 100%;
                height: 100%;
                display: block;
                object-fit: contain;
            }

            .mm-banner-dots {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                margin-top: 2px;
            }

            .mm-banner-dot {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                border: 0;
                background: #e0d8cd;
                padding: 0;
                cursor: pointer;
                transition: .2s ease;
            }

            .mm-banner-dot.active {
                background: #b51f2c;
                width: 22px;
                border-radius: 999px;
            }

            @media (max-width: 640px) {
                .mm-banner-slide {
                    aspect-ratio: 16 / 9;
                }

                .mm-banners {
                    padding: 14px 0 0;
                }
            }
        </style>