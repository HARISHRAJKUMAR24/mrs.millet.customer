<?php
require_once './config/config.php';
require_once './config/function.php';

$settings = getSettings($pdo);

$siteName = $settings['username'] ?? 'Mrs Mill@';
$logoUrl  = !empty($settings['logo_image'])    ? ADMIN_URL . $settings['logo_image']    : '';
$favicon  = !empty($settings['favicon_image']) ? ADMIN_URL . $settings['favicon_image'] : '';

/* Load data */
$categories = getCategories($pdo, 20);
$products   = getProducts($pdo, 8, 0);
$menuItems  = getMenuProducts($pdo, 4);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($siteName) ?> · Fresh Millet Products</title>

    <?php if ($favicon): ?>
        <link rel="icon" href="<?= htmlspecialchars($favicon) ?>">
    <?php endif; ?>

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#b51f2c',
                        'primary-dark': '#8e1722',
                        dark: '#302923',
                        muted: '#817a71',
                        cream: '#faf7f2',
                        'cream-dark': '#f5efe5',
                        gold: '#b8893c'
                    }
                }
            }
        }
    </script>

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        * {
            scrollbar-width: none;
        }

        *::-webkit-scrollbar {
            display: none;
        }

        body {
            font-family: "DM Sans", system-ui, -apple-system, BlinkMacSystemFont,
                "Segoe UI", sans-serif;
            background: #faf7f2;
            color: #302923;
        }

        .heading-font {
            font-family: "Playfair Display", Georgia, serif;
        }

        .category-scroll {
            overflow-x: auto;
            scroll-behavior: smooth;
        }

        .food-card {
            transition: .25s ease;
        }

        .food-card:hover {
            transform: translateY(-4px);
        }

        .restaurant-card {
            transition: .25s ease;
        }

        .restaurant-card:hover {
            transform: translateY(-3px);
        }

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</head>

<body>

    <!-- ================= NAVBAR ================= -->
    <header class="sticky top-0 z-50 bg-white/95 backdrop-blur border-b border-gray-100">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="h-16 flex items-center justify-between gap-4">

                <!-- Logo -->
                <a href="<?= MAIN_URL ?>" class="flex items-center gap-2 shrink-0">

                    <?php if ($logoUrl): ?>
                        <img src="<?= htmlspecialchars($logoUrl) ?>"
                            alt="Logo"
                            class="w-10 h-10 rounded-xl object-contain bg-white shadow-sm">
                    <?php else: ?>
                        <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center text-white text-xl">
                            <i class="bi bi-shop"></i>
                        </div>
                    <?php endif; ?>

                    <div class="hidden sm:block">
                        <h1 class="font-black text-xl tracking-tight">
                            <?= htmlspecialchars($siteName) ?>
                        </h1>
                        <p class="text-[10px] text-gray-400 -mt-1 uppercase tracking-widest">
                            Fresh Millet Store
                        </p>
                    </div>

                </a>

                <!-- Location -->
                <button class="hidden md:flex items-center gap-2 text-sm">
                    <span class="text-primary"><i class="bi bi-geo-alt-fill"></i></span>
                    <div class="text-left">
                        <p class="text-[10px] uppercase text-gray-400">Deliver to</p>
                        <p class="font-semibold">
                            Coimbatore
                            <i class="bi bi-chevron-down text-gray-400 text-xs"></i>
                        </p>
                    </div>
                </button>

                <!-- Search -->
                <div class="flex-1 max-w-xl">
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text"
                            placeholder="Search for products..."
                            class="w-full bg-gray-100 rounded-xl py-3 pl-11 pr-4
                                      text-sm outline-none focus:ring-2 focus:ring-red-100
                                      focus:bg-white transition">
                    </div>
                </div>

                <!-- Right -->
                <div class="flex items-center gap-2 sm:gap-4">

                    <a href="#" class="hidden sm:block text-sm font-medium hover:text-primary">
                        Offers
                    </a>

                    <a href="help.php" class="hidden sm:block text-sm font-medium hover:text-primary">
                        Help
                    </a>

                    <a href="#" class="relative">
                        <span class="text-xl"><i class="bi bi-cart3"></i></span>
                        <span class="absolute -top-2 -right-2 bg-primary text-white
                                     text-[9px] w-4 h-4 rounded-full
                                     flex items-center justify-center">
                            0
                        </span>
                    </a>

                    <a href="#" class="w-9 h-9 rounded-full bg-gray-100
                                       flex items-center justify-center hover:bg-red-50">
                        <i class="bi bi-person"></i>
                    </a>

                </div>

            </div>

        </div>

    </header>


    <!-- ================= MAIN ================= -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- ================= CATEGORIES ================= -->
        <section class="pt-7">

            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="heading-font text-xl sm:text-2xl font-bold">
                        Shop by Category
                    </h2>
                    <p class="text-sm text-gray-400 mt-1">
                        Choose your favourite millet products
                    </p>
                </div>

                <?php if (count($categories) > 4): ?>
                    <div class="hidden sm:flex gap-2">
                        <button onclick="scrollCategories(-250)"
                            class="w-9 h-9 rounded-full border border-gray-200
                                       flex items-center justify-center
                                       hover:border-primary hover:text-primary transition">
                            <i class="bi bi-arrow-left"></i>
                        </button>
                        <button onclick="scrollCategories(250)"
                            class="w-9 h-9 rounded-full border border-gray-200
                                       flex items-center justify-center
                                       hover:border-primary hover:text-primary transition">
                            <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (empty($categories)): ?>

                <div class="text-center py-12 text-gray-400 text-sm">
                    <i class="bi bi-tags" style="font-size:32px;"></i>
                    <p class="mt-2">No categories available yet.</p>
                </div>

            <?php else: ?>

                <div id="categoryScroll" class="category-scroll flex gap-5 pb-4">

                    <?php foreach ($categories as $c):
                        $img = categoryImageUrl($c['category_image'] ?? '');
                    ?>
                        <a href="category.php?slug=<?= urlencode($c['category_slug']) ?>"
                            class="shrink-0 text-center cursor-pointer group">

                            <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-full
                                        bg-white border-2 border-red-50
                                        flex items-center justify-center
                                        overflow-hidden group-hover:border-primary
                                        group-hover:shadow-lg transition">

                                <?php if ($img): ?>
                                    <img src="<?= htmlspecialchars($img) ?>"
                                        alt=""
                                        class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class="bi bi-basket text-3xl text-primary"></i>
                                <?php endif; ?>

                            </div>

                            <p class="text-xs font-semibold mt-2 max-w-[96px]
                                      line-clamp-2 leading-tight">
                                <?= htmlspecialchars($c['category_name']) ?>
                            </p>

                        </a>
                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>


        <!-- ================= FILTERS ================= -->
        <section class="mt-8">
            <div class="flex gap-2 overflow-x-auto">
                <button class="px-4 py-2 rounded-full bg-primary text-white text-sm whitespace-nowrap">
                    <i class="bi bi-grid-fill"></i> All
                </button>
                <button class="px-4 py-2 rounded-full border border-gray-200 text-sm whitespace-nowrap hover:border-primary hover:text-primary transition">
                    <i class="bi bi-star-fill text-gold"></i> Ratings 4.0+
                </button>
                <button class="px-4 py-2 rounded-full border border-gray-200 text-sm whitespace-nowrap hover:border-primary hover:text-primary transition">
                    <i class="bi bi-leaf-fill text-green-600"></i> Pure Veg
                </button>
                <button class="px-4 py-2 rounded-full border border-gray-200 text-sm whitespace-nowrap hover:border-primary hover:text-primary transition">
                    <i class="bi bi-lightning-charge-fill text-gold"></i> Fast Delivery
                </button>
                <button class="px-4 py-2 rounded-full border border-gray-200 text-sm whitespace-nowrap hover:border-primary hover:text-primary transition">
                    <i class="bi bi-currency-rupee"></i> Under ₹300
                </button>
            </div>
        </section>


        <!-- ================= MENU (TODAY) ================= -->
        <?php if (!empty($menuItems)): ?>

            <section class="mt-12">

                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h2 class="heading-font text-xl sm:text-2xl font-bold">
                            Today's Menu
                        </h2>
                        <p class="text-sm text-gray-400 mt-1">
                            Handpicked for today
                        </p>
                    </div>
                    <a href="menu.php" class="text-primary text-sm font-semibold hover:underline">
                        See all <i class="bi bi-arrow-right"></i>
                    </a>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    <?php foreach ($menuItems as $p):
                        $img = productImageUrl($p['product_image'] ?? '');
                    ?>
                        <div class="food-card border border-gray-100 rounded-2xl p-3 bg-white">

                            <div class="relative">
                                <div class="w-full aspect-square rounded-xl overflow-hidden bg-cream">
                                    <?php if ($img): ?>
                                        <img src="<?= htmlspecialchars($img) ?>"
                                            class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center">
                                            <i class="bi bi-box-seam text-4xl text-gray-300"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <button class="absolute bottom-2 right-2 bg-white shadow-lg
                                               w-9 h-9 rounded-full text-primary font-bold
                                               flex items-center justify-center
                                               hover:bg-primary hover:text-white transition">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>

                            <div class="pt-3">
                                <p class="text-[10px] text-gray-400 uppercase font-semibold">
                                    <?= htmlspecialchars($p['category_name'] ?? '') ?>
                                </p>
                                <h3 class="font-semibold mt-1 text-sm line-clamp-2 leading-tight">
                                    <?= htmlspecialchars($p['product_name']) ?>
                                </h3>
                                <p class="font-bold mt-1 text-primary">
                                    ₹<?= number_format((float)$p['min_price'], 0) ?>
                                </p>
                                <p class="text-xs text-gray-400 mt-1">
                                    #<?= htmlspecialchars($p['product_code']) ?>
                                </p>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>

            </section>

        <?php endif; ?>


        <!-- ================= PRODUCTS ================= -->
        <section class="mt-12 pb-28">

            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="heading-font text-xl sm:text-2xl font-bold">
                        All Products
                    </h2>
                    <p class="text-sm text-gray-400 mt-1">
                        Discover our full collection
                    </p>
                </div>
                <a href="products.php" class="text-primary text-sm font-semibold hover:underline">
                    See all <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <?php if (empty($products)): ?>

                <div class="text-center py-12 text-gray-400 text-sm">
                    <i class="bi bi-box" style="font-size:32px;"></i>
                    <p class="mt-2">No products available yet.</p>
                </div>

            <?php else: ?>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">

                    <?php foreach ($products as $p):
                        $img = productImageUrl($p['product_image'] ?? '');
                    ?>
                        <div class="food-card border border-gray-100 rounded-2xl p-3 bg-white">

                            <div class="relative">
                                <div class="w-full aspect-square rounded-xl overflow-hidden bg-cream">
                                    <?php if ($img): ?>
                                        <img src="<?= htmlspecialchars($img) ?>"
                                            class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center">
                                            <i class="bi bi-box-seam text-4xl text-gray-300"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <button class="absolute bottom-2 right-2 bg-white shadow-lg
                                               w-9 h-9 rounded-full text-primary font-bold
                                               flex items-center justify-center
                                               hover:bg-primary hover:text-white transition">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>

                            <div class="pt-3">
                                <p class="text-[10px] text-gray-400 uppercase font-semibold">
                                    <?= htmlspecialchars($p['category_name'] ?? '') ?>
                                </p>
                                <h3 class="font-semibold mt-1 text-sm line-clamp-2 leading-tight">
                                    <?= htmlspecialchars($p['product_name']) ?>
                                </h3>
                                <p class="font-bold mt-1 text-primary">
                                    ₹<?= number_format((float)$p['min_price'], 0) ?>
                                </p>
                                <p class="text-xs text-gray-400 mt-1">
                                    #<?= htmlspecialchars($p['product_code']) ?>
                                </p>
                            </div>

                        </div>
                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

    </main>


    <!-- ================= MOBILE BOTTOM NAV ================= -->
    <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-100 z-50 sm:hidden">
        <div class="grid grid-cols-4 h-16">

            <a href="<?= MAIN_URL ?>index.php"
                class="flex flex-col items-center justify-center gap-1 text-primary">
                <i class="bi bi-house-door-fill text-lg"></i>
                <span class="text-[10px] font-medium">Home</span>
            </a>

            <a href="search.php"
                class="flex flex-col items-center justify-center gap-1 text-gray-400 hover:text-primary">
                <i class="bi bi-search text-lg"></i>
                <span class="text-[10px]">Search</span>
            </a>

            <a href="cart.php"
                class="flex flex-col items-center justify-center gap-1 text-gray-400 hover:text-primary">
                <i class="bi bi-cart3 text-lg"></i>
                <span class="text-[10px]">Cart</span>
            </a>

            <a href="profile.php"
                class="flex flex-col items-center justify-center gap-1 text-gray-400 hover:text-primary">
                <i class="bi bi-person text-lg"></i>
                <span class="text-[10px]">Profile</span>
            </a>

        </div>
    </nav>


    <script>
        function scrollCategories(amount) {
            const container = document.getElementById("categoryScroll");
            if (!container) return;
            container.scrollBy({
                left: amount,
                behavior: "smooth"
            });
        }
    </script>

</body>

</html>