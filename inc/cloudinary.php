<?php
// אינטגרציה עם Cloudinary ללא ספריות חיצוניות (PHP 7.3)
// שימוש ב-API חתום (signed upload)

require_once __DIR__ . '/config.php';

function cloudinary_is_configured() {
    return defined('CLOUDINARY_CLOUD_NAME') && CLOUDINARY_CLOUD_NAME !== 'YOUR_CLOUD_NAME'
        && defined('CLOUDINARY_API_KEY') && CLOUDINARY_API_KEY
        && defined('CLOUDINARY_API_SECRET') && CLOUDINARY_API_SECRET;
}

function upload_to_cloudinary($localFilePath, $resourceType, $fileName, $mimeType = null) {
    if (!cloudinary_is_configured()) {
        return [ 'success' => false, 'error' => 'Cloudinary לא מוגדר (חסר cloud_name או מפתחות)' ];
    }

    if (!is_file($localFilePath)) {
        return [ 'success' => false, 'error' => 'קובץ לא נמצא להעלאה' ];
    }

    if ($mimeType === null) {
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) { $mimeType = finfo_file($fi, $localFilePath) ?: null; finfo_close($fi); }
        }
    }

    $timestamp = time();
    // חתימה לפי Cloudinary: שרשור המחרוזת "timestamp=..." + API_SECRET
    $toSign = 'timestamp=' . $timestamp;
    $signature = sha1($toSign . CLOUDINARY_API_SECRET);

    $endpoint = 'https://api.cloudinary.com/v1_1/' . rawurlencode(CLOUDINARY_CLOUD_NAME) . '/' . ($resourceType === 'video' ? 'video' : 'image') . '/upload';

    $ch = curl_init($endpoint);
    $postFields = [
        'file' => new CURLFile($localFilePath, $mimeType ?: 'application/octet-stream', $fileName),
        'api_key' => CLOUDINARY_API_KEY,
        'timestamp' => $timestamp,
        'signature' => $signature,
    ];

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 60,
    ]);

    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        return [ 'success' => false, 'error' => 'שגיאת cURL: ' . $err ];
    }

    $data = @json_decode($resp, true);
    if ($code >= 200 && $code < 300 && is_array($data) && isset($data['secure_url'])) {
        return [
            'success' => true,
            'public_id' => $data['public_id'] ?? null,
            'secure_url' => $data['secure_url'] ?? null,
            'resource_type' => $data['resource_type'] ?? $resourceType,
        ];
    }

    $msg = is_array($data) && isset($data['error']['message']) ? $data['error']['message'] : ('קוד תגובה: ' . $code);
    return [ 'success' => false, 'error' => 'העלאה נכשלה: ' . $msg ];
}

function cloudinary_transform_image_url($secureUrl, $transformation = 'c_fill,w_600,h_400,q_auto,f_auto') {
    // משנה את ה-URL של Cloudinary כדי להחזיר גרסה קלה יותר להצגה ברשימות
    // מחפש את הסגמנט '/image/upload/' ומוסיף את מחרוזת הטרנספורמציה אחריו
    $needle = '/image/upload/';
    $pos = strpos($secureUrl, $needle);
    if ($pos === false) return $secureUrl; // לא Cloudinary או URL שונה
    return substr($secureUrl, 0, $pos + strlen($needle)) . $transformation . '/' . substr($secureUrl, $pos + strlen($needle));
}

// מחיקת אובייקט מ-Cloudinary לפי public_id
function cloudinary_destroy($publicId, $resourceType = 'image') {
    if (!cloudinary_is_configured()) {
        return [ 'success' => false, 'error' => 'Cloudinary לא מוגדר' ];
    }
    $publicId = (string)$publicId;
    if ($publicId === '') {
        return [ 'success' => false, 'error' => 'public_id חסר' ];
    }
    $timestamp = time();
    // חתימה: public_id=...&timestamp=... + API_SECRET
    $toSign = 'public_id=' . $publicId . '&timestamp=' . $timestamp;
    $signature = sha1($toSign . CLOUDINARY_API_SECRET);
    $endpoint = 'https://api.cloudinary.com/v1_1/' . rawurlencode(CLOUDINARY_CLOUD_NAME) . '/' . ($resourceType === 'video' ? 'video' : 'image') . '/destroy';

    $ch = curl_init($endpoint);
    $postFields = [
        'public_id' => $publicId,
        'api_key' => CLOUDINARY_API_KEY,
        'timestamp' => $timestamp,
        'signature' => $signature,
    ];
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 60,
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        return [ 'success' => false, 'error' => 'שגיאת cURL: ' . $err ];
    }
    $data = @json_decode($resp, true);
    if ($code >= 200 && $code < 300 && is_array($data)) {
        // Cloudinary מחזירה { result: 'ok' } או 'not found'
        if (isset($data['result']) && ($data['result'] === 'ok' || $data['result'] === 'not found')) {
            return [ 'success' => true, 'result' => $data['result'] ];
        }
    }
    $msg = is_array($data) && isset($data['error']['message']) ? $data['error']['message'] : ('קוד תגובה: ' . $code);
    return [ 'success' => false, 'error' => 'מחיקה נכשלה: ' . $msg ];
}
