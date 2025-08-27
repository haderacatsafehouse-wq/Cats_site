# Cats Sanctuary Website

[English](#english) | [עברית](#-עברית-hebrew)

## English

A PHP website (RTL, Hebrew) for displaying cats and managing content, using SQLite, Bootstrap 5, and Cloudinary for media. The admin area is password-protected: either via Apache Basic Auth or a session-based password defined in the config.
Includes support for Cat Linking — associating a cat with other cats, and Main Image — choosing a primary image that appears as the thumbnail and on the home page.

### What's here
- `index.php` — Home page: displays cat cards, client-side filtering by location, thumbnails, and a cat details modal.
- `index.php` — Home page: displays cat cards, client-side filtering by location, thumbnails, and a cat details modal. Includes server-side fuzzy search.
- `admin/`
  - `index.php` — Admin dashboard: add cats, locations, and tags; manage media. Includes a quick search (AJAX) component for adding "related cats" when adding a cat. Preview for choosing a "Main Image" when multiple files are selected (click/tap the desired image).
  - `edit.php` — Dedicated cat edit page (mobile-first), with cat search/filter. Also supports adding/removing "related cats" with AJAX search, plus a quick action "Set as Main Image" on each existing media item.
  - `search_cats.php` — JSON API for searching cats (used by AJAX, see below).
  - `.htaccess` — Protections and upload limits for the admin area; enables optional Basic Auth.
  - `README_AUTH.txt` — Notes for placing an htpasswd file in an Apache setup.
- `inc/`
  - `config.php` — Loads configuration from `inc/config.local.json` or `inc/config.json` (not in git). Includes Cloudinary settings and file paths.
  - `config.example.json` — Example config for local edits.
  - `db.php` — SQLite connection, schema, and DAO functions (cats, media, locations, tags). Includes column `media.is_main` for the Main Image and a safe automatic schema migration.
  - `cloudinary.php` — Uploads to Cloudinary and provides transformations for images/video.
  - `google_drive.php` — Non-active placeholder (current system uses Cloudinary).
  - `locations.json` — Default list of locations for display/filtering.
  - `theme.css` — Custom styling (purple-ish palette, RTL, Bootstrap tweaks).
- `data/` — SQLite DB file is created automatically (path set in `config.json`).
- `uploads/` — Local folder with security restrictions (Index + .htaccess); current policy uses Cloudinary and does not serve files directly from here.

### Requirements
- PHP 7.3+ (also works on 8.x) with PDO SQLite.
- Apache 2.x with AllowOverride (to use `.htaccess` in `admin/`).
- Internet connection for Cloudinary uploads/thumbnails.

Debian common packages: `apache2`, one of `libapache2-mod-php` or `php-fpm`, and `php-sqlite3`.

### Configuration
Create `inc/config.local.json` (preferred) or `inc/config.json` based on the example:

```json
{
  "db_file": "../data/cats_sanctuary.sqlite",
  "uploads_dir": "../uploads",
  "cloudinary": {
    "cloud_name": "YOUR_CLOUD_NAME",
    "api_key": "YOUR_API_KEY",
    "api_secret": "YOUR_API_SECRET"
  },
  "admin_password": "CHANGE_ME"
}
```

Notes:
- These variables are loaded by `inc/config.php`. You can provide relative paths (they'll be resolved to absolute paths relative to `inc/`).
- Cloudinary keys are not stored in git; use the local JSON files only.

Typical Debian setup (absolute paths):

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

### Admin area security
Choose one option (you can also combine them in production):

#### Session-based admin password
To enable login to `/admin/` and `/admin/edit.php`, add this key to `inc/config.local.json` or `inc/config.json`:

```json
{ "admin_password": "choose_a_password_here" }
```

After that, accessing admin pages will prompt for the password. The endpoint `admin/search_cats.php` returns 401 JSON if not logged in. If the key is not set, access is blocked until you define it.

#### Apache Basic Auth
Two ways to set htpasswd (example for Debian/Ubuntu; on CentOS/RHEL the path is usually under `/etc/httpd/`):

1) Hard-coded path in `admin/.htaccess` (uncomment the AuthType/Name/File/Require lines and update AuthUserFile):
   - Create the password file:
     ```bash
     sudo htpasswd -c /etc/apache2/cats_admin.htpasswd admin
     ```
   - Update the path in `.htaccess`:
     ```
     AuthUserFile "/etc/apache2/cats_admin.htpasswd"
     ```

2) Use an Apache environment variable (recommended; see `admin/README_AUTH.txt`):
   - In your VirtualHost:
     ```
     SetEnv APACHE_HTPASSWD_PATH "/etc/apache2/cats_admin.htpasswd"
     ```
   - Ensure `AllowOverride All` so `.htaccess` is honored.

Relevant Debian/Ubuntu modules (usually already enabled):
```bash
sudo a2enmod auth_basic
sudo a2enmod authn_file
```

Additionally, `admin/.htaccess` sets upload limits (size/time/memory) for Apache + mod_php.

### Endpoints & API
- `admin/search_cats.php`
  - Query params: `q` (free-text search on name/description/location), `limit` (default 200), `exclude` (comma-separated list of IDs to filter out).
  - Output: `{ success: true, items: [ { id, name, location_name, thumb_url } ] }`.
  - Thumbnails are produced via Cloudinary transforms for performance, preferring the Main Image if set; otherwise the last still image.

#### Search on the home page
- URL params:
  - `q` — free-text ("fuzzy") search across name, description, location name, and tags. Performs LIKE for each word in the query (AND between words, OR across fields). Compatible with PHP 7.3 and SQLite.
- Example: `/index.php?q=ginger cute`
- Server implementation: function `search_cats_fuzzy($q, $limit=200)` in `inc/db.php`.

### Cat Linking
- Schema: table `cat_links(cat_id_a, cat_id_b)` with a CHECK ensuring the smaller id comes first (symmetric link; only one row is stored per pair). Defined with ON DELETE CASCADE.
- Adding in admin (add cat): a "related cats" field with AJAX autocomplete (fuzzy search on the server). You can select multiple cats; items appear as removable tags.
- Editing in `admin/edit.php`: same picker plus shows existing links and allows removal on click.
- Home page: the cat details modal shows a "Related cats" group; clicking an item closes the modal and opens the selected cat's modal.

### Database
- SQLite with file path set in `config.json` (default: `data/cats_sanctuary.sqlite`).
- Basic schema: tables `locations`, `cats`, `media` (with `is_main` for the Main Image), tag tables (many-to-many), and `cat_links` for symmetric links between cats. `PRAGMA foreign_keys = ON` is enabled.
- Locations: read from `inc/locations.json`; you can add new locations via the UI.

#### Schema upgrade (Main Image)
- The `is_main` migration on the `media` table runs automatically at init (it checks the column and runs `ALTER TABLE media ADD COLUMN is_main INTEGER DEFAULT 0` only if missing).
- No deletions or changes to existing data are performed; default values for `is_main` are 0 for all existing media.
- Recommendation: before first deployment of this version, back up the SQLite file (path set in `inc/config*.json`).

### Media (Cloudinary)
- Uploads are made to Cloudinary (images/video). The code includes a function for URL transformations to display thumbs and lightweight previews.
- `google_drive.php` remains a documentation stub and is not active by default.

#### Main Image
- When adding a cat (`admin/index.php`): after selecting multiple files, a preview appears. Click/tap the desired image to mark it as the Main Image. If not selected manually, the first static image will be chosen automatically.
- When editing a cat (`admin/edit.php`): each existing image card has a "Set as Main Image" button. The selected image is highlighted and gets a badge.
- Limitation: only images (image/*) can be the Main Image, not videos.
- Site effect: the primary image on the home page card and thumbnails will always prefer the Main Image; if none is set, the first/last still will be shown as before.

### Local run (Linux + Apache)
Example VirtualHost (Debian/Ubuntu):

```
DocumentRoot "/var/www/cats_sanctuary"
<Directory "/var/www/cats_sanctuary">
  AllowOverride All
  Require all granted
</Directory>
```

Notes:
- The PHP built-in server does not honor `.htaccess`; for the protected admin area prefer Apache.
- If you use PHP-FPM (the default on modern Debian), the `php_value` directives in `admin/.htaccess` won't apply; set upload limits in `php.ini`/your FPM pool and restart services.

#### Debian Apache2 — quick install
```bash
sudo apt update
sudo apt install -y apache2 php php-sqlite3 libapache2-mod-php

# Useful modules
sudo a2enmod rewrite
sudo a2enmod headers
sudo a2enmod expires
# (Basic Auth)
sudo a2enmod auth_basic
sudo a2enmod authn_file

# Place source under /var/www (adjust path as needed)
sudo mkdir -p /var/www/cats_sanctuary
sudo chown -R $USER:www-data /var/www/cats_sanctuary

# Create a site file
sudo tee /etc/apache2/sites-available/cats_sanctuary.conf > /dev/null <<'EOF'
<VirtualHost *:80>
  ServerName localhost
  DocumentRoot "/var/www/cats_sanctuary"
  <Directory "/var/www/cats_sanctuary">
    AllowOverride All
    Require all granted
  </Directory>
  # If using PHP-FPM, configure ProxyPassMatch or a SetHandler for PHP-FPM according to your setup
</VirtualHost>
EOF

sudo a2ensite cats_sanctuary
sudo systemctl reload apache2
```

If you prefer PHP-FPM instead of mod_php:
```bash
sudo apt install -y php-fpm
sudo a2dismod php*   # disable mod_php if it was loaded
sudo a2enconf php*-fpm
sudo systemctl reload apache2
```

### Quick checks (Verification)
- Check SQLite extension in PHP:
  ```bash
  php -m | grep -i sqlite    # expect to see sqlite3 or pdo_sqlite
  ```
- Write permissions for the data and uploads directories (consider SELinux/AppArmor as needed):
  ```bash
  sudo mkdir -p /var/www/cats_sanctuary/{data,uploads}
  sudo chown -R www-data:www-data /var/www/cats_sanctuary/{data,uploads}
  sudo find /var/www/cats_sanctuary/{data,uploads} -type d -exec chmod 775 {} \;
  ```
- Load the site: browse to `http://<host>/` — the home page should load. If there is no data yet, use the admin interface to add some.
- Basic Auth test: browse to `/admin/` — you should get a username/password prompt. If not, check `AllowOverride All` and that `auth_basic`/`authn_file` modules are enabled.
- Cloudinary test: set keys in `inc/config.local.json`, log in to `/admin/`, add a cat, and upload an image. Verify a `secure_url` is created and a thumbnail appears in the list.

Tip: for a quick healthcheck you can temporarily create a script (remove after use):

```php
<?php
require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/db.php';
echo 'DB_FILE=' . DB_FILE . "\n";
$pdo = get_db();
echo 'DB OK; foreign_keys=on' . "\n";
echo 'Cloudinary set: ' . (defined('CLOUDINARY_CLOUD_NAME') ? CLOUDINARY_CLOUD_NAME : 'NO') . "\n";
```

Save it as `healthcheck.php` in the root, open it in your browser, then delete it.

### Security
- `uploads/.htaccess` blocks script execution and directory listing; `uploads/index.php` returns 403.
- `.gitignore` excludes DB and uploads, as well as local config files (`inc/config.json`, `inc/config.local.json`).
- Do not store secrets in git. Configure upload size limits in `admin/.htaccess` and in code.

### UI and styling
- Bootstrap 5.3 RTL via CDN + `inc/theme.css` for adjustments (color palette, buttons, modals, RTL).
- The interface is fully in Hebrew (RTL) both on the home site and in the admin area.

### Common issues
- 401/403 on `/admin`: ensure a valid `AuthUserFile` or `APACHE_HTPASSWD_PATH` and that AllowOverride is enabled.
- 500 on upload: verify your Cloudinary credentials in the config file and that upload limits match in `admin/.htaccess`.
- Missing PDO SQLite: enable the extension in your PHP environment.

---
Update: this README reflects the current project state: Cloudinary is the default for media, a search API exists at `admin/search_cats.php`, and the admin area is split into a dashboard page and a dedicated edit page.

## עברית (Hebrew)

אתר PHP (RTL, עברית) להצגת חתולים ולניהול תוכן, עם SQLite, Bootstrap 5 ו-Cloudinary למדיה. אזור הניהול מוגן בסיסמא: או Basic Auth של Apache או סיסמה מבוססת Session שמוגדרת בתצורה.
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
  },
  "admin_password": "CHANGE_ME"
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

## אבטחת אזור הניהול
ניתן לבחור באחת מהאפשרויות (ואף לשלב ביניהן בפרודקשן):

### סיסמת ניהול בקובץ תצורה (Session)
כדי לאפשר התחברות בטופס ל־/admin/ ו־/admin/edit.php, הוסיפו לקובץ `inc/config.local.json` או `inc/config.json` את המפתח:

```json
{ "admin_password": "בחרו_סיסמה_כאן" }
```

לאחר מכן, ניסיון גישה לעמודי הניהול יבקש סיסמה. נקודת הקצה `admin/search_cats.php` תחזיר 401 JSON אם לא מחוברים. אם המפתח לא מוגדר, הגישה תיחסם עד להגדרה.

### Basic Auth של Apache
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
