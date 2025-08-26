# Cats Sanctuary Website

אתר PHP 7.3 עם Apache ו-SQLite לניהול אינדקס חתולים במקלט. ממשק המשתמש בעברית בלבד, Bootstrap דרך CDN. אזור ניהול מוגן בסיסמא באמצעות .htaccess.

תכולה:
- index.php — דף ראשי להצגת חתולים וסינון לפי מיקום.
- admin/ — אזור ניהול להוספת מיקומים וחתולים והעלאת מדיה.
- inc/ — תצורה, חיבור למסד, ו-Placeholder ל-Google Drive.
- data/ — קובץ מסד נתונים SQLite יווצר אוטומטית.
- uploads/ — תיקיית קבצים זמניים/ייצוג מקומי.

דרישות:
- PHP 7.3+, Apache 2 עם mod_php או PHP-FPM, mod_rewrite ו-AllowOverride לאזור admin.
- הרחבת PDO SQLite פעילה.

הגדרת אזור ניהול (Basic Auth):
1) צרו קובץ סיסמאות באמצעות htpasswd (Apache):
   htpasswd -c C:\Apache24\auth\cats_admin.htpasswd admin
2) פתחו את קובץ ההגדרות של ה-VirtualHost ב-Apache והגדירו משתנה סביבה:
   SetEnv APACHE_HTPASSWD_PATH "C:\\Apache24\\auth\\cats_admin.htpasswd"
   או ערכו את admin/.htaccess והכניסו נתיב קשיח ל-AuthUserFile.
3) ודאו AllowOverride All לתיקיית האתר כדי ש-.htaccess יטען.

Google Drive (Placeholder):
- inc/google_drive.php מכיל פונקציות דמה. להטמעה אמיתית:
  - צרו פרויקט ב-Google Cloud Console, הפעילו Drive API.
  - צרו Service Account והורידו JSON.
  - שלבו SDK של Google PHP (דורש Composer) או REST חתום ידנית.
  - החליפו את upload_to_google_drive() שתחזיר drive_file_id אמיתי.

פריסה מקומית ב-Apache (Windows):
- הגדירו VirtualHost שמצביע לתיקיית הפרויקט:
  DocumentRoot "C:/Users/ebuyum/git_projects/cats_sanctuary"
  <Directory "C:/Users/ebuyum/git_projects/cats_sanctuary">
      AllowOverride All
      Require all granted
  </Directory>

אבטחה והערות:
- אל תשמרו מפתחות/JSON של Google בגיט.
- מומלץ להגביל גודל העלאות ולעשות סינון MIME (בוצע בסיסית ב-admin/index.php).
- מומלץ להגיש קבצי תמונה דרך CDN/Drive בלבד; הקבצים המקומיים זמניים.
