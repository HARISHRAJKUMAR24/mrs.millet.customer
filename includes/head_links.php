<?php
/* =========================================================
   MRS MILL@ — ADVANCED SEO HEAD
   Website: https://mrsmillet.in
   ========================================================= */

$seoSiteName = $siteName ?? 'MRS MILL@';

$seoTitle = $seoTitle ?? (
    $seoSiteName . ' | Buy Millet Flour & Healthy Traditional Food Online'
);

$seoDescription = $seoDescription ?? (
    'Shop MRS MILL@ for quality millet flour, siruthaniyam maavu, '
    . 'traditional millet products and healthy food essentials. '
    . 'Explore millet-based foods and order online at mrsmillet.in.'
);

$seoKeywords = implode(', ', [
    'MRS MILLET',
    'buy millet products online',
    'buy millet flour online',
    'mrsmillet',
    'mrsmille@',
    'buy millet food products',
    'buy millet food online',
    'millet food products',
    'buy millet food online',
    'millet food online',
    'buy millet products',
    'buy millet flour',
    'millet products',
    'millet flour',
    'millet flour online',
    'buy millet flour online',
    'siruthaniyam maavu',
    'millet maavu kadai',
    'traditional millet food',
    'healthy food products',
    'millet food Tamil Nadu',
    'சிறுதானிய மாவு',
    'மில்லெட் மாவு கடை',
    'ஆரோக்கிய உணவு'
]);

$seoBaseUrl = rtrim(MAIN_URL, '/') . '/';

$seoPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$seoCanonical = $seoBaseUrl . ltrim($seoPath, '/');

$seoImage = !empty($favicon)
    ? $favicon
    : $seoBaseUrl . 'assets/images/og-image.jpg';

$seoImage = preg_match('~^https?://~i', $seoImage)
    ? $seoImage
    : $seoBaseUrl . ltrim($seoImage, '/');

$seoRobots = 'index, follow, max-image-preview:large, '
    . 'max-snippet:-1, max-video-preview:-1';

$escapeSEO = static function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
?>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= $escapeSEO($seoTitle) ?></title>

<meta name="description" content="<?= $escapeSEO($seoDescription) ?>">
<meta name="keywords" content="<?= $escapeSEO($seoKeywords) ?>">
<meta name="author" content="MRS MILL@">
<meta name="robots" content="<?= $escapeSEO($seoRobots) ?>">

<link rel="canonical" href="<?= $escapeSEO($seoCanonical) ?>">

<meta name="theme-color" content="#b51f2c">

<!-- Open Graph / Facebook / WhatsApp -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= $escapeSEO($seoSiteName) ?>">
<meta property="og:title" content="<?= $escapeSEO($seoTitle) ?>">
<meta property="og:description" content="<?= $escapeSEO($seoDescription) ?>">
<meta property="og:url" content="<?= $escapeSEO($seoCanonical) ?>">
<meta property="og:image" content="<?= $escapeSEO($seoImage) ?>">
<meta property="og:image:alt" content="MRS MILL@ Millet Food Products">

<!-- Twitter / X -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= $escapeSEO($seoTitle) ?>">
<meta name="twitter:description" content="<?= $escapeSEO($seoDescription) ?>">
<meta name="twitter:image" content="<?= $escapeSEO($seoImage) ?>">

<!-- Favicon -->
<?php if (!empty($favicon)): ?>
    <link rel="icon" href="<?= $escapeSEO($favicon) ?>">
<?php endif; ?>

<!-- Performance -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<!-- Fonts -->
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap Icons -->
<link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<!-- Website CSS -->
<link href="<?= $escapeSEO(MAIN_URL) ?>assets/css/style.css" rel="stylesheet">

<!-- Organization Structured Data -->
<script type="application/ld+json">
    <?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'MRS MILL@',
        'url' => $seoBaseUrl,
        'description' => $seoDescription
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
</script>

<!-- Website Structured Data -->
<script type="application/ld+json">
    <?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => 'MRS MILL@',
        'url' => $seoBaseUrl,
        'inLanguage' => ['en-IN', 'ta-IN']
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
</script>

<!-- SweetAlert -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
</head>