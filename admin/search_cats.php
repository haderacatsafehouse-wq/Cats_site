<?php
// החזרת רשימת חתולים בפורמט JSON עבור חיפוש AJAX
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';

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
    $out = [];
    foreach ($cats as $c) {
        $out[] = [
            'id' => (int)$c['id'],
            'name' => (string)$c['name'],
            'location_name' => isset($c['location_name']) ? (string)$c['location_name'] : null,
        ];
    }
    echo json_encode(['success' => true, 'items' => $out], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
