<?php
// החזרת רשימת חתולים בפורמט JSON עבור חיפוש AJAX
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/cloudinary.php';

header('Content-Type: application/json; charset=UTF-8');

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 200;

try {
    $cats = fetch_cats();
    if ($q !== '') {
        $ql = function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
        $cats = array_filter($cats, function($c) use ($ql) {
            $hay = (string)($c['name'] . ' ' . ($c['description'] ?? '') . ' ' . ($c['location_name'] ?? ''));
            $hay = function_exists('mb_strtolower') ? mb_strtolower($hay, 'UTF-8') : strtolower($hay);
            return strpos($hay, $ql) !== false;
        });
    }
    // סדר וחתוך ל-limit
    $cats = array_slice(array_values($cats), 0, $limit);

    // Thumb map: latest image per cat
    $ids = array_map(function($c){ return (int)$c['id']; }, $cats);
    $thumbMap = fetch_latest_image_map_for_cats($ids);

    $out = [];
    foreach ($cats as $c) {
        $thumb = isset($thumbMap[(int)$c['id']]) ? $thumbMap[(int)$c['id']] : null;
        if ($thumb) {
            // Transform for tiny thumb
            $thumb = cloudinary_transform_image_url($thumb, 'c_fill,w_48,h_48,q_auto,f_auto');
        }
        $out[] = [
            'id' => (int)$c['id'],
            'name' => (string)$c['name'],
            'location_name' => isset($c['location_name']) ? (string)$c['location_name'] : null,
            'thumb_url' => $thumb,
        ];
    }
    echo json_encode(['success' => true, 'items' => $out], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
