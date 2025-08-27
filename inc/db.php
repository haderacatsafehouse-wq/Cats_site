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
    // הפעלת תמיכת מפתחות זרים ב-SQLite
    $pdo->exec('PRAGMA foreign_keys = ON');
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

    // תגיות (האשטגים) לחתולים: many-to-many
    $pdo->exec('CREATE TABLE IF NOT EXISTS tags (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS cat_tags (
        cat_id INTEGER NOT NULL,
        tag_id INTEGER NOT NULL,
        PRIMARY KEY (cat_id, tag_id),
        FOREIGN KEY(cat_id) REFERENCES cats(id) ON DELETE CASCADE,
        FOREIGN KEY(tag_id) REFERENCES tags(id) ON DELETE CASCADE
    )');

    // סנכרון מיקומים מקובץ JSON חיצוני לבסיס הנתונים (לצורך שמירת מזהים ויחסים)
    try {
        $locations = read_locations_from_file();
        if ($locations) {
            $pdo->beginTransaction();
            $ins = $pdo->prepare('INSERT OR IGNORE INTO locations(name) VALUES (:n)');
            foreach ($locations as $locName) {
                if (!is_string($locName)) { continue; }
                $name = trim($locName);
                if ($name === '') { continue; }
                $ins->execute([':n' => $name]);
            }
            $pdo->commit();
        }
    } catch (Throwable $e) {
        error_log('Location sync failed: ' . $e->getMessage());
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
    }
}

function fetch_locations() {
    $stmt = get_db()->query('SELECT id, name FROM locations ORDER BY name');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function add_location($name) {
    $stmt = get_db()->prepare('INSERT INTO locations(name) VALUES (:n)');
    try {
    $ok = $stmt->execute([':n' => trim($name)]);
    // בנוסף, נעדכן את קובץ המיקומים אם הוא קיים ונגיש
    if ($ok) { safe_append_location_to_file($name); }
    return $ok;
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

function fetch_cats($location_id = null, $tag = null) {
    $pdo = get_db();
    $where = [];
    $params = [];
    $join = '';
    if ($location_id) {
        $where[] = 'c.location_id = :lid';
        $params[':lid'] = $location_id;
    }
    if ($tag) {
        // הצטרפות לטבלאות תגיות
        $join .= ' INNER JOIN cat_tags ct ON ct.cat_id = c.id INNER JOIN tags t ON t.id = ct.tag_id ';
        $where[] = 't.name = :tname';
        $params[':tname'] = normalize_tag($tag);
    }
    $sql = 'SELECT c.*, l.name AS location_name FROM cats c LEFT JOIN locations l ON c.location_id = l.id' . $join;
    if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
    $sql .= ' ORDER BY c.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
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

// --- עזר: קריאה/עדכון קובץ המיקומים ---
function read_locations_from_file() {
    $path = defined('LOCATIONS_FILE') ? LOCATIONS_FILE : null;
    if (!$path || !is_file($path)) { return []; }
    $json = @file_get_contents($path);
    if ($json === false) { return []; }
    $data = @json_decode($json, true);
    if (!is_array($data)) { return []; }
    // החזרת מערך של מחרוזות לא ריקות, ללא כפילויות
    $clean = [];
    foreach ($data as $v) {
        if (!is_string($v)) { continue; }
        $name = trim($v);
        if ($name === '') { continue; }
        $clean[$name] = true;
    }
    return array_keys($clean);
}

function safe_append_location_to_file($name) {
    $path = defined('LOCATIONS_FILE') ? LOCATIONS_FILE : null;
    if (!$path) { return; }
    // אם הקובץ לא קיים, ניצור אותו עם המיקום הראשון
    if (!is_file($path)) {
        @file_put_contents($path, json_encode([trim($name)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return;
    }
    $list = read_locations_from_file();
    $name = trim((string)$name);
    if ($name === '') { return; }
    if (!in_array($name, $list, true)) {
        $list[] = $name;
        @file_put_contents($path, json_encode($list, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}

// ---------------------------------
// תגיות (האשטגים) לחתולים
// ---------------------------------

/**
 * מנרמל תגית: מסיר #, חותך רווחים, תווך לאותיות קטנות, ומאפשר רק a-z0-9_-
 */
function normalize_tag($tag) {
    $t = (string)$tag;
    $t = trim($t);
    if ($t === '') { return ''; }
    if ($t[0] === '#') { $t = substr($t, 1); }
    // החלפת רווחים/רצפי רווחים בקו תחתון
    $t = preg_replace('/\s+/u', '_', $t);
    // השארת אותיות ומספרים מכל השפות + _ -
    $t = preg_replace('/[^\p{L}\p{N}_-]/u', '', $t);
    if (function_exists('mb_strtolower')) {
        $t = mb_strtolower($t, 'UTF-8');
    } else {
        $t = strtolower($t);
    }
    // אורך סביר (ללא mbstring נ fallback ל-strlen)
    $len = function_exists('mb_strlen') ? mb_strlen($t, 'UTF-8') : strlen($t);
    if ($t === '' || $len > 50) { return ''; }
    return $t;
}

/**
 * מפרק מחרוזת קלט של תגיות למערך תגיות מנורמלות ויחודיות.
 * תומך בהפרדה ברווחים, פסיקים, נקודה-פסיק ושורות.
 */
function parse_tags_input($str) {
    $str = (string)$str;
    if (trim($str) === '') { return []; }
    // המרה למפריד אחיד
    $s = str_replace(["\n", "\r", ';'], ' ', $str);
    $s = preg_replace('/[\s,]+/', ' ', $s);
    $parts = preg_split('/\s+/', $s, -1, PREG_SPLIT_NO_EMPTY);
    $set = [];
    foreach ($parts as $p) {
        $n = normalize_tag($p);
        if ($n !== '') { $set[$n] = true; }
    }
    return array_keys($set);
}

function get_or_create_tag_id($name) {
    $name = normalize_tag($name);
    if ($name === '') { return null; }
    $pdo = get_db();
    $sel = $pdo->prepare('SELECT id FROM tags WHERE name = :n');
    $sel->execute([':n' => $name]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);
    if ($row && isset($row['id'])) { return (int)$row['id']; }
    $ins = $pdo->prepare('INSERT INTO tags(name) VALUES (:n)');
    try {
        $ins->execute([':n' => $name]);
        return (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        // ייתכן שנוצר במקביל; ננסה שוב לבחור
        $sel->execute([':n' => $name]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }
}

function add_tags_for_cat($cat_id, array $tags) {
    if (!$tags) { return; }
    $pdo = get_db();
    $pdo->beginTransaction();
    try {
        $link = $pdo->prepare('INSERT OR IGNORE INTO cat_tags(cat_id, tag_id) VALUES (:c, :t)');
        foreach ($tags as $tname) {
            $tid = get_or_create_tag_id($tname);
            if ($tid) {
                $link->execute([':c' => (int)$cat_id, ':t' => $tid]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('add_tags_for_cat failed: ' . $e->getMessage());
    }
}

function fetch_tags_for_cat($cat_id) {
    $stmt = get_db()->prepare('SELECT t.name FROM tags t
                               INNER JOIN cat_tags ct ON ct.tag_id = t.id
                               WHERE ct.cat_id = :c
                               ORDER BY t.name');
    $stmt->execute([':c' => $cat_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) { $out[] = (string)$r['name']; }
    return $out;
}

function fetch_all_tags() {
    $stmt = get_db()->query('SELECT name FROM tags ORDER BY name');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) { $out[] = (string)$r['name']; }
    return $out;
}

function replace_tags_for_cat($cat_id, array $tags) {
    $pdo = get_db();
    $pdo->beginTransaction();
    try {
        $del = $pdo->prepare('DELETE FROM cat_tags WHERE cat_id = :c');
        $del->execute([':c' => (int)$cat_id]);
        if ($tags) {
            $ins = $pdo->prepare('INSERT OR IGNORE INTO cat_tags(cat_id, tag_id) VALUES (:c, :t)');
            foreach ($tags as $tname) {
                $tid = get_or_create_tag_id($tname);
                if ($tid) { $ins->execute([':c' => (int)$cat_id, ':t' => $tid]); }
            }
        }
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('replace_tags_for_cat failed: ' . $e->getMessage());
        return false;
    }
}
