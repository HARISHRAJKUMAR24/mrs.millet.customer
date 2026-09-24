<?php

// =========================================
// DATABASE CONFIGURATION
// =========================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'mrs_millet');
define('DB_USER', 'root');
define('DB_PASS', '');


// =========================================
//  APPLICATION URL
// =========================================
define(
    'ADMIN_URL',
    'http://localhost/mrs.millet.admin/'
);


// Delivery Boy URL
define(
    'BASE_URL',
    'http://localhost/mrs.millet.delivery-boy/'
);

define(
    'MAIN_URL',
    'http://localhost/mrs.millet.customer/'
);


// =========================================
// TIMEZONE
// =========================================

date_default_timezone_set('Asia/Kolkata');


// =========================================
// DATABASE CONNECTION
// =========================================

try {

    $pdo = new PDO(
        "mysql:host=" . DB_HOST .
            ";dbname=" . DB_NAME .
            ";charset=utf8mb4",

        DB_USER,
        DB_PASS
    );

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $pdo->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );
} catch (PDOException $e) {

    die("Database connection failed: " .
        $e->getMessage());
}
