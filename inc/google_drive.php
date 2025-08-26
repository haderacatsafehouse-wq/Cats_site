<?php
// Placeholder לפעולות מול Google Drive
// שימו לב: זהו קוד דמה ללא ספריות חיצוניות. יש להחליף במימוש אמיתי בהתאם ל-Google Drive API.
// צעדים:
// 1. ליצור פרויקט ב-Google Cloud Console ולהפעיל Drive API.
// 2. ליצור פרטי זיהוי (OAuth Client ID או Service Account). מומלץ Service Account להעלאות שרתיות.
// 3. להוריד קובץ JSON של המפתח ולאחסן מחוץ לגיט.
// 4. במימוש אמיתי, להשתמש ב-SDK הרשמי של Google (מצריך Composer) או קריאות REST חתומות.
// 5. להחליף את הפונקציות להלן במימוש אמיתי שמחזיר מזהי קבצים אמיתיים.

require_once __DIR__ . '/config.php';

function upload_to_google_drive(string $localFilePath, string $mimeType, string $fileName): array {
    // Placeholder: חזרה עם מזהה פיקטיבי ושמירת קובץ מקומית זמנית.
    // במימוש אמיתי: לשלוח בקשת multipart ל-Drive API ליצירת קובץ.
    <?php
    // קובץ זה הוצא משימוש. המערכת משתמשת כעת ב-Cloudinary להעלאת מדיה.
    // השארנו את הקובץ כ-stub לשמירת תאימות, אך אין בו שימוש פעיל.
    // ניתן למחוק אותו בבטחה אם תרצו.
    ];
