<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/function.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    echo json_encode(['success' => false, 'message' => 'Query too short']);
    exit;
}

try {
    // Search by product name
    $stmt = $pdo->prepare("
        SELECT p.id, p.product_name as name, p.product_image, c.category_name, 
               (SELECT MIN(price) FROM product_variants WHERE product_code = p.product_code) as min_price
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.status = 1 AND p.product_name LIKE ?
        LIMIT 10
    ");
    $stmt->execute(['%' . $q . '%']);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];
    foreach ($results as $r) {
        $img = !empty($r['product_image']) ? ADMIN_URL . $r['product_image'] : '';
        
        // If the image is missing, fallback to a blank or default image
        if (empty($img)) {
            $img = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="%23aaa"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/></svg>';
        }

        $data[] = [
            'id'       => $r['id'],
            'name'     => $r['name'],
            'category' => $r['category_name'] ?: 'Uncategorized',
            'image'    => $img,
            'price'    => (float)($r['min_price'] ?: 0)
        ];
    }

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}