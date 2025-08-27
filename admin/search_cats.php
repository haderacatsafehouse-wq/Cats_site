<?php
// החזרת רשימת חתולים בפורמט JSON עבור חיפוש AJAX
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/cloudinary.php';

header('Content-Type: application/json; charset=UTF-8');

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 200;
// ניתן להעביר exclude=1,2,3 כדי שלא יופיעו בתוצאות (לדוגמה החתול הנוכחי וקישורים שנבחרו כבר)
$exclude = isset($_GET['exclude']) ? (string)$_GET['exclude'] : '';
$excludeIds = [];
if ($exclude !== '') {
    foreach (preg_split('/[\s,;]+/', $exclude, -1, PREG_SPLIT_NO_EMPTY) as $tok) {
        $v = (int)$tok; if ($v > 0) { $excludeIds[$v] = true; }
    }
}

try {
    if ($q !== '') {
        // חיפוש בצד השרת (פאזי) — יעיל ומסנכרן עם דף הבית
        $cats = search_cats_fuzzy($q, $limit * 2);
    } else {
        $cats = fetch_cats();
    }
    // סינון מזהים מוחרגים
    if ($excludeIds) {
        $cats = array_values(array_filter($cats, function($c) use ($excludeIds){
            return empty($excludeIds[(int)$c['id']]);
        }));
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
