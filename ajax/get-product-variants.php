<?php
/* =========================================================
   MRS MILL@ — GET PRODUCT VARIANTS
   File: ./ajax/get-product-variants.php
   ========================================================= */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) jsonResponse(false, 'Invalid product ID.');

try {
    $stmt = $pdo->prepare(
        "SELECT id, product_code, product_name, product_image
         FROM products WHERE id = ? AND status = 1 LIMIT 1"
    );
    $stmt->execute([$id]);
    $p = $stmt->fetch();

    if (!$p) jsonResponse(false, 'Product not found.');

    $vStmt = $pdo->prepare(
        "SELECT id, quantity, quantity_unit, quantity_name, price
         FROM product_variants
         WHERE product_code = ? AND status = 1
         ORDER BY quantity ASC"
    );
    $vStmt->execute([$p['product_code']]);
    $rows = $vStmt->fetchAll();

    $variants = [];
    foreach ($rows as $v) {
        $variants[] = [
            'id'            => (int)$v['id'],
            'quantity'      => (float)$v['quantity'],
            'quantity_unit' => $v['quantity_unit'],
            'quantity_name' => $v['quantity_name'],
            'price'         => (float)$v['price']
        ];
    }

    jsonResponse(true, 'OK', [
        'id'           => (int)$p['id'],
        'product_code' => $p['product_code'],
        'product_name' => $p['product_name'],
        'image'        => !empty($p['product_image']) ? ADMIN_URL . $p['product_image'] : '',
        'variants'     => $variants
    ]);

} catch (PDOException $e) {
    jsonResponse(false, 'Failed to load product.');
}