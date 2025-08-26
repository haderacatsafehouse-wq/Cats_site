<?php
// חיבור למסד SQLite ויצירת טבלאות במידת הצורך
require_once __DIR__ . '/config.php';

function get_db() {
    static $pdo = null;
    static $db_path = null;
    if ($pdo === null) {
        // בדיקה האם דרייבר SQLite זמין ב-PDO, כדי למנוע שגיאת "could not find driver"
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            error_log('PDO SQLite driver is missing. Install/enable php-sqlite3 (pdo_sqlite).');
            header('Content-Type: text/html; charset=UTF-8');
            die('שגיאת שרת: דרייבר SQLite עבור PDO לא מותקן. יש להתקין/להפעיל את php-sqlite3 (pdo_sqlite) ולהפעיל מחדש את השרת.');
        }

        // קביעת נתיב בסיסי ומעבר לנתיב חלופי אם אין הרשאות כתיבה
        $candidate = DB_FILE;
        $dir = dirname($candidate);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            $altDir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'cats_sanctuary';
            if (!is_dir($altDir)) { @mkdir($altDir, 0775, true); }
            if (is_dir($altDir) && is_writable($altDir)) {
                error_log('DB path not writable: ' . $dir . ' — falling back to temp dir: ' . $altDir);
                $candidate = $altDir . DIRECTORY_SEPARATOR . 'cats_sanctuary.sqlite';
            } else {
                header('Content-Type: text/html; charset=UTF-8');
                die('שגיאת שרת: אין הרשאות כתיבה לתיקיית מסד הנתונים. נא להעניק הרשאות ל-' . htmlspecialchars($dir) . ' או להגדיר CATS_DB_FILE לנתיב ניתן לכתיבה.');
            }
        }

        $db_path = $candidate;
        $pdo = new PDO('sqlite:' . $db_path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        init_schema($pdo);
    }
    return $pdo;
}

function init_schema($pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS locations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS cats (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT,
        location_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(location_id) REFERENCES locations(id)
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS media (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cat_id INTEGER NOT NULL,
        type TEXT NOT NULL, -- image | video
        drive_file_id TEXT, -- בשימוש כעת ל-public_id של Cloudinary (שם עמודה נשמרת לצורך תאימות)
        local_path TEXT,    -- URL לתצוגה (Cloudinary secure_url) או נתיב/URL מקומי
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(cat_id) REFERENCES cats(id)
    )');
}

function fetch_locations() {
    $stmt = get_db()->query('SELECT id, name FROM locations ORDER BY name');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function add_location($name) {
    $stmt = get_db()->prepare('INSERT INTO locations(name) VALUES (:n)');
    try {
        return $stmt->execute([':n' => trim($name)]);
    } catch (PDOException $e) {
        return false; // ייתכן שכבר קיים
    }
}

function add_cat($name, $description, $location_id) {
    $stmt = get_db()->prepare('INSERT INTO cats(name, description, location_id) VALUES (:n, :d, :l)');
    $stmt->execute([
        ':n' => trim($name),
        ':d' => $description ?: null,
        ':l' => $location_id ?: null,
    ]);
    return (int)get_db()->lastInsertId();
}

function fetch_cats($location_id = null) {
    if ($location_id) {
        $stmt = get_db()->prepare('SELECT c.*, l.name AS location_name
                                   FROM cats c LEFT JOIN locations l ON c.location_id = l.id
                                   WHERE c.location_id = :lid
                                   ORDER BY c.created_at DESC');
        $stmt->execute([':lid' => $location_id]);
    } else {
        $stmt = get_db()->query('SELECT c.*, l.name AS location_name
                                  FROM cats c LEFT JOIN locations l ON c.location_id = l.id
                                  ORDER BY c.created_at DESC');
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function add_media($cat_id, $type, $drive_file_id, $local_path) {
    $stmt = get_db()->prepare('INSERT INTO media(cat_id, type, drive_file_id, local_path) VALUES (:c, :t, :df, :lp)');
    return $stmt->execute([
        ':c' => $cat_id,
        ':t' => $type,
        ':df' => $drive_file_id,
        ':lp' => $local_path,
    ]);
}

function fetch_media_for_cat($cat_id) {
    $stmt = get_db()->prepare('SELECT * FROM media WHERE cat_id = :c ORDER BY created_at DESC');
    $stmt->execute([':c' => $cat_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
