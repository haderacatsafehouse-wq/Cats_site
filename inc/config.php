<?php
// קובץ תצורה ראשי (ללא סודות בקוד)
// טוען תצורה מקובץ JSON שנערך מקומית: inc/config.local.json או inc/config.json (לא עולים ל-Git)
// נופל חזרה לברירות מחדל אם לא הוגדרו

// קריאת קובץ JSON מקומי אם קיים
$__CONF = [];
foreach ([__DIR__ . '/config.local.json', __DIR__ . '/config.json'] as $__path) {
    if (is_file($__path)) {
        $json = @file_get_contents($__path);
        $data = $json ? @json_decode($json, true) : null;
        if (is_array($data)) { $__CONF = $data; break; }
    }
}

// נתיב קובץ מסד הנתונים (SQLite)
// ניתן לשנות דרך JSON (db_file). ברירת מחדל: data/cats_sanctuary.sqlite
// הפיכת נתיבים ל-absolute יחסית לתיקיית inc/
function __conf_abs_path($p) {
    if (!$p) return $p;
    // אם מתחיל ב-/ (לינוקס), או אות כונן (Windows), או נתיב UNC \\\\ — השאר כמות שהוא
    if (preg_match('#^(?:[A-Za-z]:[\\/]|[\\/]{2}|/)#', $p)) {
        return $p;
    }
    // אחרת יחסית ל-inc/
    return rtrim(__DIR__, '/\\') . '/' . ltrim($p, '/\\');
}

$__db_file = isset($__CONF['db_file']) && $__CONF['db_file'] ? __conf_abs_path($__CONF['db_file']) : (__DIR__ . '/../data/cats_sanctuary.sqlite');
define('DB_FILE', $__db_file);

// תיקיית העלאות (לא בשימוש כש-Cloudinary בלבד, נשמר למקרי עתיד)
$__uploads_dir = isset($__CONF['uploads_dir']) && $__CONF['uploads_dir'] ? __conf_abs_path($__CONF['uploads_dir']) : (__DIR__ . '/../uploads');
define('UPLOADS_DIR', $__uploads_dir);

// הגדרות Cloudinary — מומלץ להגדיר בקובץ JSON:
// {
//   "cloudinary": { "cloud_name": "...", "api_key": "...", "api_secret": "..." }
// }
$__cloud = isset($__CONF['cloudinary']) && is_array($__CONF['cloudinary']) ? $__CONF['cloudinary'] : [];
define('CLOUDINARY_CLOUD_NAME', isset($__cloud['cloud_name']) ? $__cloud['cloud_name'] : 'YOUR_CLOUD_NAME');
define('CLOUDINARY_API_KEY', isset($__cloud['api_key']) ? $__cloud['api_key'] : '854982932249496');
define('CLOUDINARY_API_SECRET', isset($__cloud['api_secret']) ? $__cloud['api_secret'] : '1234');

// הגדרות כלליות ל-UI
define('SITE_TITLE', 'מקלט חתולים - אינדקס');

// קובץ מיקומים חיצוני (נכנס ל-Git). ניתן לשנות דרך JSON (locations_file).
// אם לא הוגדר, ישתמש ב-inc/locations.json
$__locations_file = isset($__CONF['locations_file']) && $__CONF['locations_file'] ? __conf_abs_path($__CONF['locations_file']) : (__DIR__ . '/locations.json');
define('LOCATIONS_FILE', $__locations_file);

// מאפייני אבטחה בסיסיים ל-uploads
$ALLOWED_IMAGE_MIME = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp'
];
$ALLOWED_VIDEO_MIME = [
    'video/mp4', 'video/webm', 'video/ogg'
];
$MAX_UPLOAD_BYTES = 50 * 1024 * 1024; // עד 50MB לקובץ
