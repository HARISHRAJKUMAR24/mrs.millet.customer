    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($siteName) ?> · Fresh Millet Products</title>

    <?php if ($favicon): ?>
        <link rel="icon" href="<?= htmlspecialchars($favicon) ?>">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">

        <link
        href="<?= MAIN_URL ?>assets/css/style.css"
        rel="stylesheet">
    </head>