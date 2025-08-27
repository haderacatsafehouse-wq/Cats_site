# Cats Sanctuary Website

אתר PHP (RTL, עברית) להצגת חתולים ולניהול תוכן, עם SQLite, Bootstrap 5 ו-Cloudinary למדיה. אזור הניהול מוגן בסיסמא (Basic Auth) דרך Apache.
כולל תמיכה ב"קישור חתולים" (Cat Linking) — שיוך חתול לחתולים אחרים, וב"תמונת מפתח" (Main Image) — בחירת תמונה ראשית שתופיע כתמונה הממוזערת ובדף הראשי.

## מה יש כאן
- `index.php` — דף ראשי: מציג כרטיסיות חתולים, סינון בצד לקוח לפי מיקום, תמונות ממוזערות, מודל פרטי חתול.
- `index.php` — דף ראשי: מציג כרטיסיות חתולים, סינון בצד לקוח לפי מיקום, תמונות ממוזערות, מודל פרטי חתול. כולל חיפוש חופשי (fuzzy) בשרת.
- `admin/`
  - `index.php` — דשבורד ניהול: הוספת חתולים, מיקומים ותגיות; ניהול מדיה.
  כולל רכיב חיפוש מהיר (AJAX) להוספת "חתולים קשורים" בעת הוספת חתול.
  תצוגה מקדימה לבחירת "תמונת מפתח" בעת בחירת מספר קבצים (קליק/נגיעה על התמונה הרצויה).
  - `edit.php` — עמוד עריכת חתול ייעודי (Mobile-first), כולל חיפוש/סינון חתולים.
  כולל גם הוספה/הסרה של "חתולים קשורים" עם חיפוש AJAX.
  וכן פעולה מהירה "בחר כתמונת מפתח" על כל תמונה קיימת של החתול.
  - `search_cats.php` — API JSON לחיפוש חתולים (ל-AJAX, ראה בהמשך).
  - `.htaccess` — הגנות והגבלות העלאה לאזור הניהול; אפשרות Basic Auth.
  - `README_AUTH.txt` — הנחיות להצבת קובץ htpasswd בסביבת Apache.
- `inc/`
  - `config.php` — טעינת תצורה מ-`inc/config.local.json` או `inc/config.json` (לא בגיט). כולל הגדרות Cloudinary ונתיבי קבצים.
  - `config.example.json` — דוגמת תצורה לעריכה מקומית.
  - `db.php` — חיבור SQLite, סכימה ופונקציות DAO (חתולים, מדיה, מיקומים, תגיות). כולל עמודת `media.is_main` לתמונת מפתח והגירת סכימה אוטומטית בטוחה.
  - `cloudinary.php` — העלאה ל-Cloudinary וטרנספורמציות לתמונות/וידאו.
  - `google_drive.php` — Placeholder לא פעיל (המערכת הנוכחית משתמשת ב-Cloudinary).
  - `locations.json` — רשימת מיקומים ברירת מחדל להצגה/סינון.
  - `theme.css` — עיצוב בהתאמה אישית (פלטה סגלגלה, RTL, התאמות Bootstrap).
- `data/` — קובץ SQLite יווצר אוטומטית (מוגדר ב-`config.json`).
- `uploads/` — תיקייה מקומית עם חסימות אבטחה (Index + .htaccess); במדיניות הנוכחית משתמשים ב-Cloudinary ולא מגישים ישירות קבצים מהתיקייה.

## דרישות מערכת
- PHP 7.3+ (נתמך גם ב-8.x) עם PDO SQLite.
- Apache 2.x עם AllowOverride (לשימוש ב-.htaccess באזור `admin/`).
- חיבור אינטרנט ל-Cloudinary עבור העלאות/תמונות ממוזערות.

Debian (חבילות נפוצות): `apache2`, אחד מ-`libapache2-mod-php` או `php-fpm`, ו-`php-sqlite3`.

## תצורה
צרו `inc/config.local.json` (מועדף) או `inc/config.json` על בסיס הדוגמה:

```json
{
  "db_file": "../data/cats_sanctuary.sqlite",
  "uploads_dir": "../uploads",
  "cloudinary": {
    "cloud_name": "YOUR_CLOUD_NAME",
    "api_key": "YOUR_API_KEY",
    "api_secret": "YOUR_API_SECRET"
  }
}
```

הערות:
- משתנים אלה נטענים ב-`inc/config.php`. ניתן לספק נתיבים יחסיים (יהפכו ל-absolute יחסית ל-`inc/`).
- מפתחות Cloudinary אינם נשמרים בגיט; השתמשו בקבצי ה-JSON המקומיים בלבד.

דוגמה אופיינית ל-Debian (נתיבים מוחלטים):

```json
{
  "db_file": "/var/www/cats_sanctuary/data/cats_sanctuary.sqlite",
  "uploads_dir": "/var/www/cats_sanctuary/uploads",
  "cloudinary": {
    "cloud_name": "example-cloud",
    "api_key": "123456789012345",
    "api_secret": "REDACTED"
  }
}
```

## אבטחת אזור הניהול (Basic Auth)
יש שתי דרכים להגדיר htpasswd (דוגמה ללינוקס Debian/Ubuntu; ב-CentOS/RHEL הנתיב לרוב תחת `/etc/httpd/`):

1) הגדרת נתיב קשיח בקובץ `admin/.htaccess` (בטלו הערות משורות AuthType/Name/File/Require ועדכנו את AuthUserFile):
   - יצירת קובץ סיסמאות:
     ```bash
     sudo htpasswd -c /etc/apache2/cats_admin.htpasswd admin
     ```
   - עדכון:
     ```
     AuthUserFile "/etc/apache2/cats_admin.htpasswd"
     ```

2) שימוש במשתנה סביבה של Apache (מומלץ; ראו `admin/README_AUTH.txt`):
   - בקובץ ה-VirtualHost:
     ```
     SetEnv APACHE_HTPASSWD_PATH "/etc/apache2/cats_admin.htpasswd"
     ```
   - ודאו `AllowOverride All` כך ש-`.htaccess` ייטען.

מודולים רלוונטיים ב-Debian/Ubuntu (ברוב המקרים כבר פעילים):
```bash
sudo a2enmod auth_basic
sudo a2enmod authn_file
```

בנוסף, `admin/.htaccess` מגדיר הגבלות העלאה (גודל/זמן/זיכרון) עבור Apache + mod_php.

## נקודות קצה ו-API
- `admin/search_cats.php`
  - Query params: `q` (חיפוש טקסט חופשי בשם/תיאור/מיקום), `limit` (ברירת מחדל 200), `exclude` (רשימת מזהים מופרדת בפסיקים לסינון החוצה).
  - פלט: `{ success: true, items: [ { id, name, location_name, thumb_url } ] }`.
  - תמונות ממוזערות נוצרות ע"י טרנספורמציה של Cloudinary לשיפור ביצועים, ובוחרות את תמונת המפתח אם הוגדרה; אחרת תמונת הסטילס האחרונה.

### חיפוש בדף הראשי
- פרמטרים ב-URL: 
  - `q` — חיפוש חופשי (fuzzy) על שם, תיאור, שם מיקום ותגיות. החיפוש מבצע LIKE על כל מילה בשאילתה (AND בין מילים, OR בין שדות). תואם PHP 7.3 ו-SQLite.
- דוגמה: `/index.php?q=ג׳ינג׳י חמוד`
- מימוש בשרת: פונקציה `search_cats_fuzzy($q, $limit=200)` בקובץ `inc/db.php`.

### קישור חתולים (Cat Linking)
- סכימה: טבלת `cat_links(cat_id_a, cat_id_b)` עם CHECK שה-id הקטן קודם (קישור סימטרי; נשמר רשומה אחת לכל זוג). מוגדרת עם ON DELETE CASCADE.
- הוספה בעמוד הניהול (הוספת חתול): שדה "חתולים קשורים" עם אוטוקומפליט AJAX (חיפוש fuzzy בשרת). ניתן לבחור מספר חתולים; הפריטים מופיעים כתגיות ניתנות להסרה.
- עריכה בעמוד `admin/edit.php`: אותו רכיב בחירה בנוסף להצגה של הקישורים הקיימים והסרה בלחיצה.
- דף ראשי: במודל פרטי חתול תוצג קבוצה "חתולים קשורים"; לחיצה על פריט תסגור את המודל ותפתח את המודל של החתול שנבחר.

## מסד נתונים
- SQLite בקובץ שהוגדר ב-`config.json` (ברירת מחדל: `data/cats_sanctuary.sqlite`).
- סכימה בסיסית: טבלאות `locations`, `cats`, `media` (עם העמודה `is_main` לתמונת מפתח), וטבלאות תגיות (many-to-many), וכן טבלת `cat_links` לקישורים סימטריים בין חתולים. מופעל `PRAGMA foreign_keys = ON`.
- מיקומים: קריאה מ-`inc/locations.json`; ניתן להוסיף מיקומים חדשים דרך הממשק.

### שדרוג סכימה (תמונת מפתח)
- הגירת `is_main` בטבלת `media` מתבצעת אוטומטית בזמן אתחול (`ALTER TABLE media ADD COLUMN is_main INTEGER DEFAULT 0`), לאחר בדיקה שהעמודה אינה קיימת.
- לא מתבצעת מחיקה או שינוי של נתונים קיימים; ערכי ברירת מחדל ל-is_main הם 0 עבור כל המדיה הקיימת.
- המלצה: לפני העלאה ראשונית של הגרסה, גבו את קובץ ה-SQLite (הנתיב מוגדר ב-`inc/config*.json`).

## מדיה (Cloudinary)
- העלאות מבוצעות ל-Cloudinary (תמונות/וידאו). בקוד קיימת פונקציה לטרנספורמציית URL להצגת thumbs ותצוגה מקדימה קלה.
- `google_drive.php` נשאר כ-stub תיעודי בלבד ואינו פעיל בברירת מחדל.

### תמונת מפתח (Main Image)
- בעת הוספת חתול (`admin/index.php`): אחרי בחירת קבצים מרובים תופיע תצוגה מקדימה. הקליקו/געו על התמונה הרצויה כדי לסמן אותה כתמונת המפתח. אם לא נבחרה ידנית — התמונה הסטטית הראשונה תסומן אוטומטית.
- בעת עריכת חתול (`admin/edit.php`): בכל כרטיס תמונה קיים יש כפתור "בחר כתמונת מפתח". התמונה המסומנת תודגש בסימון ותקבל Badge.
- מגבלה: רק תמונות (image/*) יכולות להיות תמונת מפתח, לא סרטונים.
- השפעה באתר: התמונה הראשית בכרטיס הדף הראשי וב-thumbnailים תמיד תעדיף את תמונת המפתח; אם אין — תוצג תמונת סטילס ראשונה/אחרונה כמקודם.

## הרצה מקומית (Linux + Apache)
דוגמת VirtualHost (Debian/Ubuntu):

```
DocumentRoot "/var/www/cats_sanctuary"
<Directory "/var/www/cats_sanctuary">
  AllowOverride All
  Require all granted
</Directory>
```

הערות:
- ה-PHP built-in server לא יכבד כללי .htaccess; להרצת הניהול המוגן עדיף Apache.
- אם אתם משתמשים ב-PHP-FPM (ברירת המחדל ב-Debian מודרני), דיירקטיבות `php_value` מתוך `admin/.htaccess` לא יחולו; הגדירו מגבלות העלאה ב-`php.ini`/pool של FPM והפעילו מחדש את השירותים.

### Debian Apache2 — התקנה מהירה
```bash
sudo apt update
sudo apt install -y apache2 php php-sqlite3 libapache2-mod-php

# מודולים שימושיים
sudo a2enmod rewrite
sudo a2enmod headers
sudo a2enmod expires
# (אימות בסיסי)
sudo a2enmod auth_basic
sudo a2enmod authn_file

# שיבוץ קוד המקור ב-/var/www (התאימו נתיב לפי הצורך)
sudo mkdir -p /var/www/cats_sanctuary
sudo chown -R $USER:www-data /var/www/cats_sanctuary

# צרו קובץ אתר
sudo tee /etc/apache2/sites-available/cats_sanctuary.conf > /dev/null <<'EOF'
<VirtualHost *:80>
  ServerName localhost
  DocumentRoot "/var/www/cats_sanctuary"
  <Directory "/var/www/cats_sanctuary">
    AllowOverride All
    Require all granted
  </Directory>
  # אם משתמשים ב-PHP-FPM, קנפגו כאן ProxyPassMatch או PHP-FPM SetHandler בהתאם לפריסה שלכם
</VirtualHost>
EOF

sudo a2ensite cats_sanctuary
sudo systemctl reload apache2
```

אם אתם מעדיפים PHP-FPM במקום mod_php:
```bash
sudo apt install -y php-fpm
sudo a2dismod php*   # בטל mod_php אם היה טעון
sudo a2enconf php*-fpm
sudo systemctl reload apache2
```

## בדיקות מהירות (Verification)
- בדיקת הרחבת SQLite ב-PHP:
  ```bash
  php -m | grep -i sqlite    # צריך לראות sqlite3 או pdo_sqlite
  ```
- הרשאות כתיבה לתיקיית הנתונים וההעלאות (שיקולי SELinux/AppArmor לפי הצורך):
  ```bash
  sudo mkdir -p /var/www/cats_sanctuary/{data,uploads}
  sudo chown -R www-data:www-data /var/www/cats_sanctuary/{data,uploads}
  sudo find /var/www/cats_sanctuary/{data,uploads} -type d -exec chmod 775 {} \;
  ```
- בדיקת טעינת האתר: גלשו ל-`http://<host>/` — הדף הראשי אמור לעלות. אם אין נתונים עדיין, ממשק הניהול ישמש להוספה.
- בדיקת Basic Auth: גלשו ל-`/admin/` — אמורה להופיע בקשה לשם משתמש/סיסמה. אם לא, בדקו `AllowOverride All` ושהמודולים `auth_basic`/`authn_file` פעילים.
- בדיקת Cloudinary: הגדירו מפתחות ב-`inc/config.local.json`, התחברו ל-`/admin/`, הוסיפו חתול ונסו להעלות תמונה. ודאו שנוצר `secure_url` ומופיע thumbnail ברשימה.

טיפ: אם נדרש Healthcheck מהיר, ניתן ליצור זמנית סקריפט בדיקה (ולהסירו לאחר מכן):
```php
<?php
require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/db.php';
echo 'DB_FILE=' . DB_FILE . "\n";
$pdo = get_db();
echo 'DB OK; foreign_keys=on' . "\n";
echo 'Cloudinary set: ' . (defined('CLOUDINARY_CLOUD_NAME') ? CLOUDINARY_CLOUD_NAME : 'NO') . "\n";
```
שמרו כ-`healthcheck.php` בשורש, הריצו מול הדפדפן, ואז מחקו אותו.

## אבטחה
- `uploads/.htaccess` חוסם ריצת סקריפטים ורשימת תיקייה; קיים `uploads/index.php` המחזיר 403.
- `.gitignore` מחריג קובצי DB ו-uploads, וכן קובצי תצורה מקומיים (`inc/config.json`, `inc/config.local.json`).
- אל תשמרו סודות בגיט. הגדירו מגבלות גודל העלאה ב-`.htaccess` של `admin/` ובקוד.

## תצוגה ועיצוב
- Bootstrap 5.3 RTL דרך CDN + `inc/theme.css` להתאמות (פלטת צבע, כפתורים, מודלים, RTL).
- הממשק בעברית מלאה (RTL) הן באתר הראשי והן באזור הניהול.

## תקלות נפוצות
- 401/403 ב-`/admin`: בדקו שהוגדר `AuthUserFile` תקין או `APACHE_HTPASSWD_PATH` והפעלתם AllowOverride.
- 500 בהעלאה: ודאו שהוגדרו פרטי Cloudinary נכונים בקובץ התצורה, ושמוגדרות מגבלות העלאה תואמות ב-`admin/.htaccess`.
- חוסר ב-PDO SQLite: הפעילו את ההרחבה בסביבת ה-PHP שלכם.

---
עדכון: README זה עודכן כך שישקף את המצב הנוכחי של הפרויקט: Cloudinary הוא ברירת המחדל למדיה, API לחיפוש קיים ב-`admin/search_cats.php`, ואזור הניהול מפוצל לעמוד דשבורד ולעמוד עריכה ייעודי.
