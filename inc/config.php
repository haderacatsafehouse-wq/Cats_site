<?php
// קובץ תצורה ראשי
// PHP 7.3 Compatible

// נתיב קובץ מסד הנתונים (SQLite)
// ניתן לשנות נתיב זה לפי הצורך
define('DB_FILE', __DIR__ . '/../data/cats_sanctuary.sqlite');

// תיקיית העלאות מקומית (לשימוש זמני/ביניים)
// מומלץ להשאיר לשימוש תצוגה מקומית כל עוד ההעלאה לגוגל דרייב היא מצבית
define('UPLOADS_DIR', __DIR__ . '/../uploads');

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
