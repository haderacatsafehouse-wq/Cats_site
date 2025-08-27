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

// סיסמת ניהול פשוטה מבוססת Session (חלופה/תוספת ל-Basic Auth של Apache)
// הוגדרה בקובץ JSON תחת admin_password
$__admin_password = isset($__CONF['admin_password']) ? (string)$__CONF['admin_password'] : '';
define('ADMIN_PASSWORD', $__admin_password);

// הגדרות כלליות ל-UI
define('SITE_TITLE', 'חתולים בבית המחסה');

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

// ----------------------------
// עזר: אימות מנהל מבוסס Session
// ----------------------------
if (session_status() === PHP_SESSION_NONE) {
    // שם סשן ייחודי כדי לא להתנגש עם אתרים אחרים באותו דומיין
    @session_name('cats_admin');
    @session_start();
}

/** בדיקה אם המשתמש כבר מחובר כמנהל */
function is_admin_authenticated() {
    return !empty($_SESSION['is_admin']);
}

/** ניסיון התחברות עם סיסמה; מחזיר true אם הצליח */
function admin_login($password) {
    $pwd = (string)$password;
    $conf = defined('ADMIN_PASSWORD') ? ADMIN_PASSWORD : '';
    if ($conf !== '' && hash_equals((string)$conf, $pwd)) {
        $_SESSION['is_admin'] = true;
        return true;
    }
    // אם לא הוגדרה סיסמה בקובץ התצורה, לא נאפשר כניסה (שיקול אבטחה)
    return false;
}

/** יציאה מהאזור המוגן */
function admin_logout() {
    unset($_SESSION['is_admin']);
}

/**
 * הגנת עמודי ניהול: אם לא מחובר, מציג טופס התחברות מינימלי (HTML) ושם קוד 401.
 * יש להשתמש בראש קובץ admin/*.php (למעט נקודות קצה JSON מיוחדות שיחזירו 401 JSON).
 */
function require_admin_auth_or_login_form() {
    if (is_admin_authenticated()) { return; }
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
        if (admin_login($_POST['admin_password'])) {
            // רענון GET למניעת resubmit של הטופס — בנייה בטוחה של ה-URL הנוכחי
            $path = isset($_SERVER['PHP_SELF']) ? (string)$_SERVER['PHP_SELF'] : '/';
            $qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? ('?' . $_SERVER['QUERY_STRING']) : '';
            $redir = $path . $qs;
            // הסרת תווי שורה להגנה על כותרות
            $redir = preg_replace('/[\r\n]+/', '', $redir);
            if ($redir === '' || $redir[0] !== '/') { $redir = '/'; }
            header('Location: ' . $redir, true, 302);
            exit;
        } else {
            $err = 'סיסמה שגויה';
        }
    }
    http_response_code(401);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="he" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>כניסה — ניהול חתולים</title>'
       . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">'
       . '<link rel="stylesheet" href="../inc/theme.css">'
       . '</head><body class="bg-light">'
       . '<div class="container py-5"><div class="row justify-content-center"><div class="col-12 col-md-6 col-lg-4">'
       . '<div class="card shadow-sm"><div class="card-body">'
       . '<h1 class="h5 mb-3">כניסה לאזור הניהול</h1>';
    if ($err !== '') {
        echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    echo '<form method="post">'
       . '  <div class="mb-3">'
       . '    <label class="form-label" for="admin_password">סיסמה</label>'
       . '    <input autofocus required type="password" class="form-control" id="admin_password" name="admin_password" autocomplete="current-password">'
       . '  </div>'
       . '  <button type="submit" class="btn btn-primary w-100">כניסה</button>'
       . '</form>'
       . '<div class="text-center mt-3"><a class="small" href="/">חזרה לאתר</a></div>'
       . '</div></div></div></div></div>'
       . '</body></html>';
    exit;
}
