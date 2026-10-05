<?php
/* =========================================================
   MRS MILL@ — SIMPLE FOOTER
   File: ./includes/footer.php
   ========================================================= */

if (!isset($settings) || !is_array($settings)) {
    $settings = getSettings($pdo);
}

$fSiteName   = $settings['username']      ?? 'Mrs Mill@';
$fMobile     = $settings['mobile_number'] ?? '';
$fEmail      = $settings['email_address'] ?? '';
$fLogoUrl    = !empty($settings['logo_image']) ? ADMIN_URL . $settings['logo_image'] : '';

/* ---------- LOAD BRANCHES ---------- */
$footerBranches = [];
try {
    $stmt = $pdo->query(
        "SELECT id, branch_name, branch_address, branch_mobile
         FROM settings_branches
         ORDER BY branch_name ASC"
    );
    $footerBranches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $footerBranches = [];
}

$currentYear = (int)date('Y');
?>
<footer class="mm-footer">
    <div class="mm-footer-inner">

        <!-- TOP: logo + contact -->
        <div class="mm-footer-top">
            <div class="mm-footer-brand">
                <div class="mm-footer-logo">
                    <?php if ($fLogoUrl): ?>
                        <img src="<?= htmlspecialchars($fLogoUrl) ?>" alt="<?= htmlspecialchars($fSiteName) ?>">
                    <?php else: ?>
                        <span><?= htmlspecialchars(substr($fSiteName, 0, 1)) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mm-footer-contact">
                <?php if ($fMobile): ?>
                    <a href="tel:<?= htmlspecialchars($fMobile) ?>">
                        <i class="bi bi-telephone-fill"></i>
                        <?= htmlspecialchars($fMobile) ?>
                    </a>
                <?php endif; ?>

                <?php if ($fEmail): ?>
                    <a href="mailto:<?= htmlspecialchars($fEmail) ?>">
                        <i class="bi bi-envelope-fill"></i>
                        <?= htmlspecialchars($fEmail) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>


        <!-- BRANCHES -->
        <?php if (!empty($footerBranches)): ?>
            <div class="mm-footer-branches">
                <?php foreach ($footerBranches as $b):
                    $bName   = $b['branch_name']    ?? '';
                    $bAddr   = $b['branch_address'] ?? '';
                    $bMobile = $b['branch_mobile']  ?? '';

                    $bAddrClean = trim(preg_replace('/\s*\n\s*/', ', ', $bAddr));
                ?>
                    <div class="mm-footer-branch">
                        <i class="bi bi-geo-alt-fill"></i>
                        <div>
                            <h5><?= htmlspecialchars($bName) ?></h5>
                            <?php if ($bAddrClean !== ''): ?>
                                <p><?= htmlspecialchars($bAddrClean) ?></p>
                            <?php endif; ?>
                            <?php if ($bMobile): ?>
                                <a href="tel:<?= htmlspecialchars($bMobile) ?>">
                                    <i class="bi bi-telephone"></i>
                                    <?= htmlspecialchars($bMobile) ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>


        <!-- BOTTOM -->
        <div class="mm-footer-bottom">
            <div class="mm-footer-copy">
                &copy; <?= $currentYear ?> <?= htmlspecialchars($fSiteName) ?>
            </div>

            <div class="mm-footer-powered">
                Powered by
                <strong>One Mile Stone Technology Pvt Ltd</strong>
            </div>
        </div>

    </div>
</footer>


<style>
    .mm-footer {
        margin-top: 40px;
        background: #fdfaf4;
        border-top: 1.5px solid #ece5da;
        font-family: "DM Sans", sans-serif;
        color: #302923;
    }

    .mm-footer-inner {
        max-width: 1240px;
        margin: 0 auto;
        padding: 22px 24px 12px;
    }

    /* ---------- TOP ---------- */
    .mm-footer-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding-bottom: 14px;
        border-bottom: 1px solid #ece5da;
        flex-wrap: wrap;
    }

    .mm-footer-brand {
        display: flex;
        align-items: center;
    }

    /* Bigger logo — full shown */
    .mm-footer-logo {
        height: 54px;
        max-width: 200px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .mm-footer-logo img {
        max-height: 100%;
        max-width: 100%;
        width: auto;
        height: auto;
        object-fit: contain;
        display: block;
    }

    .mm-footer-logo span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 54px;
        height: 54px;
        border-radius: 12px;
        background: linear-gradient(135deg, #b51f2c 0%, #8e1722 100%);
        color: #fff;
        font-family: "Playfair Display", serif;
        font-size: 22px;
        font-weight: 800;
        box-shadow: 0 8px 20px rgba(181, 31, 44, .22);
    }

    .mm-footer-contact {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .mm-footer-contact a {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 13px;
        background: #fff;
        border: 1.5px solid #ece5da;
        border-radius: 10px;
        font-size: 11.5px;
        font-weight: 700;
        color: #302923;
        text-decoration: none;
        transition: .2s ease;
    }

    .mm-footer-contact a i {
        color: #b51f2c;
        font-size: 11px;
    }

    .mm-footer-contact a:hover {
        border-color: #b51f2c;
        color: #b51f2c;
        background: #fff5f5;
    }

    /* ---------- BRANCHES ---------- */
    .mm-footer-branches {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        padding: 16px 0;
        border-bottom: 1px solid #ece5da;
    }

    @media (max-width: 900px) {
        .mm-footer-branches { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 560px) {
        .mm-footer-branches { grid-template-columns: 1fr; }
    }

    .mm-footer-branch {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 12px;
        background: #fff;
        border: 1.5px solid #ece5da;
        border-radius: 11px;
        transition: .2s ease;
    }

    .mm-footer-branch:hover {
        border-color: #d98a91;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(48, 41, 35, .06);
    }

    .mm-footer-branch > i {
        width: 30px;
        height: 30px;
        border-radius: 9px;
        background: linear-gradient(135deg, #fdf1e2 0%, #fbe3c4 100%);
        color: #b8893c;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        flex-shrink: 0;
    }

    .mm-footer-branch h5 {
        font-size: 12px;
        font-weight: 800;
        color: #302923;
        margin: 0 0 3px;
        line-height: 1.3;
    }

    .mm-footer-branch p {
        font-size: 10.5px;
        color: #817a71;
        line-height: 1.45;
        margin: 0 0 5px;
        font-weight: 500;
    }

    .mm-footer-branch a {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 10.5px;
        font-weight: 700;
        color: #6f675f;
        text-decoration: none;
        transition: .18s ease;
    }

    .mm-footer-branch a i {
        color: #b8893c;
        font-size: 10px;
    }

    .mm-footer-branch a:hover {
        color: #b51f2c;
    }

    .mm-footer-branch a:hover i {
        color: #b51f2c;
    }

    /* ---------- BOTTOM (tight) ---------- */
    .mm-footer-bottom {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 0 4px;
        font-size: 11px;
        color: #948c82;
        font-weight: 600;
        flex-wrap: wrap;
    }

    .mm-footer-powered strong {
        color: #b51f2c;
        font-weight: 800;
    }

    @media (max-width: 560px) {
        .mm-footer-inner { padding: 18px 16px 10px; }

        .mm-footer-logo { height: 44px; }

        .mm-footer-bottom {
            justify-content: center;
            text-align: center;
            gap: 4px;
            padding: 8px 0 4px;
        }
    }
</style>