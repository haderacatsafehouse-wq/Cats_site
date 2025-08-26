<?php
// קובץ תצורה ראשי
// PHP 7.3 Compatible

// נתיב קובץ מסד הנתונים (SQLite)
// ניתן לשנות נתיב זה לפי הצורך או באמצעות משתנה סביבה CATS_DB_FILE
define('DB_FILE', getenv('CATS_DB_FILE') ?: (__DIR__ . '/../data/cats_sanctuary.sqlite'));

// תיקיית העלאות מקומית (לשימוש זמני/ביניים)
// ניתן לשנות גם באמצעות משתנה סביבה CATS_UPLOADS_DIR
define('UPLOADS_DIR', getenv('CATS_UPLOADS_DIR') ?: (__DIR__ . '/../uploads'));

// הגדרות Cloudinary — נדרש cloud_name כדי להעלות קבצים
// חשוב: מומלץ לשמור מפתחות מחוץ לקוד, אך כאן לפי בקשתכם לשימוש מיידי.
define('CLOUDINARY_CLOUD_NAME', 'YOUR_CLOUD_NAME'); // החליפו בשם הענן שלכם
define('CLOUDINARY_API_KEY', '854982932249496');
define('CLOUDINARY_API_SECRET', '1234'); // החליפו לאחר מכן

// הגדרות כלליות ל-UI
define('SITE_TITLE', 'מקלט חתולים - אינדקס');

// מאפייני אבטחה בסיסיים ל-uploads
$ALLOWED_IMAGE_MIME = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp'
];
$ALLOWED_VIDEO_MIME = [
    'video/mp4', 'video/webm', 'video/ogg'
];
$MAX_UPLOAD_BYTES = 50 * 1024 * 1024; // עד 50MB לקובץ
