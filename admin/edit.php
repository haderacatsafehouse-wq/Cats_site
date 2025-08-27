<?php
// עמוד עריכה נפרד לחתולים (נייד תחילה)
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/cloudinary.php';

$errors = [];
$success = null;

// פרמטרים מה-GET
$selected_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

// טיפול בטפסים
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'update_cat') {
        $cid = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $loc  = isset($_POST['location_id']) && $_POST['location_id'] !== '' ? (int)$_POST['location_id'] : null;
        $tags_input = trim($_POST['tags'] ?? '');

        if ($cid <= 0) {
            $errors[] = 'מזהה חתול שגוי';
        } elseif ($name === '') {
            $errors[] = 'יש להזין שם';
        } else {
            if (!update_cat($cid, $name, $desc !== '' ? $desc : null, $loc)) {
                $errors[] = 'עדכון פרטי החתול נכשל';
            } else {
                // תגיות
                $tags = $tags_input !== '' ? parse_tags_input($tags_input) : [];
                if (!replace_tags_for_cat($cid, $tags)) {
                    $errors[] = 'עדכון תגיות נכשל';
                }
                // העלאת מדיה חדשה (אופציונלי)
                if (!empty($_FILES['media_files']['name'][0])) {
                    $count = count($_FILES['media_files']['name']);
                    for ($i = 0; $i < $count; $i++) {
                        $tmp = $_FILES['media_files']['tmp_name'][$i];
                        $orig = $_FILES['media_files']['name'][$i];
                        $type = $_FILES['media_files']['type'][$i];
                        $size = (int)$_FILES['media_files']['size'][$i];
                        if (!is_uploaded_file($tmp)) continue;
                        if ($size > $MAX_UPLOAD_BYTES) continue;
                        $isImage = in_array($type, $ALLOWED_IMAGE_MIME, true);
                        $isVideo = in_array($type, $ALLOWED_VIDEO_MIME, true);
                        if (!$isImage && !$isVideo) continue;

                        $resType = $isVideo ? 'video' : 'image';
                        $uploaded = upload_to_cloudinary($tmp, $resType, $orig, $type);
                        if (($uploaded['success'] ?? false) === true) {
                            add_media($cid, $resType, $uploaded['public_id'] ?? null, $uploaded['secure_url'] ?? null);
                        } else {
                            $errMsg = isset($uploaded['error']) ? $uploaded['error'] : 'שגיאה לא ידועה בהעלאה ל-Cloudinary';
                            $errors[] = 'נכשלה העלאה ל-Cloudinary עבור הקובץ: ' . htmlspecialchars($orig) . ' — ' . htmlspecialchars($errMsg);
                        }
                    }
                }
                if (!$errors) {
                    $success = 'החתול עודכן';
                    // נשארים באותו חתול
                    $selected_id = $cid;
                }
            }
        }
    }

  if ($action === 'delete_media') {
    $mid = isset($_POST['media_id']) ? (int)$_POST['media_id'] : 0;
    $cid = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($mid > 0) {
      $m = fetch_media_by_id($mid);
      if ($m && !empty($m['drive_file_id'])) {
        // נסה למחוק מה-Cloudinary (image/video)
        $rt = ($m['type'] === 'video') ? 'video' : 'image';
        $res = cloudinary_destroy($m['drive_file_id'], $rt);
        if (!($res['success'] ?? false)) {
          // לא חוסם; מדווח בלוג וממשיך למחוק מהרשומה
          error_log('Cloudinary destroy failed for ' . $m['drive_file_id'] . ': ' . ($res['error'] ?? 'unknown'));
        }
      }
      delete_media($mid);
    }
    $selected_id = $cid;
    $success = $success ?: 'המדיה הוסרה';
  }

  if ($action === 'delete_cat') {
    $cid = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($cid > 0) {
      // מחיקת אובייקטים מ-Cloudinary לפני מחיקת הרשומות
      $medias = fetch_media_for_cat($cid);
      foreach ($medias as $m) {
        if (!empty($m['drive_file_id'])) {
          $rt = ($m['type'] === 'video') ? 'video' : 'image';
          $res = cloudinary_destroy($m['drive_file_id'], $rt);
          if (!($res['success'] ?? false)) {
            error_log('Cloudinary destroy failed for ' . $m['drive_file_id'] . ': ' . ($res['error'] ?? 'unknown'));
          }
        }
      }
      if (delete_cat($cid)) {
        $success = 'החתול נמחק';
        $selected_id = 0; // חוזרים לבחירה
      } else {
        $errors[] = 'מחיקת החתול נכשלה';
      }
    }
  }
}

// נתונים להצגה
$locations = fetch_locations();
$cats = fetch_cats();

// סינון חיפוש בצד השרת (שם/תיאור/תגיות)
if ($q !== '') {
    $qLower = function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
    $cats = array_filter($cats, function($c) use ($qLower) {
        $hay = (string)($c['name'] . ' ' . ($c['description'] ?? '') . ' ' . ($c['location_name'] ?? ''));
        $hay = function_exists('mb_strtolower') ? mb_strtolower($hay, 'UTF-8') : strtolower($hay);
        return strpos($hay, $qLower) !== false;
    });
}

// לחתול שנבחר נטען גם תגיות ומדיה
$selected = $selected_id > 0 ? fetch_cat_by_id($selected_id) : null;
$selected_tags = $selected ? fetch_tags_for_cat($selected_id) : [];
$selected_media = $selected ? fetch_media_for_cat($selected_id) : [];
$all_tags = function_exists('fetch_all_tags') ? fetch_all_tags() : [];

?><!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>עריכת חתולים - <?= htmlspecialchars(SITE_TITLE) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
  <link href="../inc/theme.css" rel="stylesheet">
  <style>
    /* נייד תחילה: תצוגת רשימה קומפקטית */
    .cat-card { cursor: pointer; }
    .media-thumb { width: 90px; height: 90px; object-fit: cover; border-radius: 8px; }
    .sticky-top-sm { position: sticky; top: 0; z-index: 1020; }
    @media (min-width: 992px) {
      .split { display: grid; grid-template-columns: 380px 1fr; gap: 1rem; }
      .cat-list { max-height: calc(100vh - 160px); overflow: auto; }
      .sticky-top-sm { top: 1rem; }
    }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-3">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="/cat">
      <img class="brand-logo" src="https://www.haderacats.org.il/wp-content/uploads/2025/07/Untitled12.png" alt="לוגו" loading="eager">
      <span class="ms-2">חזרה לרשימה</span>
    </a>
    <span class="navbar-text text-light">עריכת חתולים</span>
  </div>
</nav>
<div class="container">
  <?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $e) { echo '<div>' . htmlspecialchars($e) . '</div>'; } ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <div class="split">
    <div>
      <div class="card mb-3 sticky-top-sm">
        <div class="card-body">
          <form class="d-flex" method="get" onsubmit="return false;">
            <input id="js-search" type="search" class="form-control me-2" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="חיפוש בשם/תיאור/מיקום" autocomplete="off">
            <button class="btn btn-outline-secondary" type="button" id="js-clear">נקה</button>
          </form>
        </div>
      </div>

      <div id="js-list" class="list-group cat-list"></div>
    </div>

    <div>
      <?php if (!$selected): ?>
        <div class="text-muted">בחר/י חתול מהרשימה לעריכה</div>
      <?php else: ?>
        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span>עריכת חתול: <?= htmlspecialchars($selected['name']) ?> (#{<?= (int)$selected['id'] ?>})</span>
            <form method="post" onsubmit="return confirm('למחוק את החתול וכל המידע הקשור? הפעולה בלתי הפיכה.');">
              <input type="hidden" name="id" value="<?= (int)$selected['id'] ?>">
              <input type="hidden" name="action" value="delete_cat">
              <button type="submit" class="btn btn-sm btn-danger">מחיקה</button>
            </form>
          </div>
          <div class="card-body">
            <form method="post" enctype="multipart/form-data">
              <input type="hidden" name="id" value="<?= (int)$selected['id'] ?>">
              <input type="hidden" name="action" value="update_cat">

              <div class="mb-3">
                <label class="form-label">שם</label>
                <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($selected['name']) ?>" required>
              </div>
              <div class="mb-3">
                <label class="form-label">תיאור</label>
                <textarea class="form-control" name="description" rows="3"><?= htmlspecialchars($selected['description'] ?? '') ?></textarea>
              </div>
              <div class="mb-3">
                <label class="form-label">מיקום</label>
                <select class="form-select" name="location_id">
                  <option value="">ללא</option>
                  <?php foreach ($locations as $loc): $sel = ($selected['location_id'] ?? null) == $loc['id'] ? 'selected' : ''; ?>
                    <option value="<?= (int)$loc['id'] ?>" <?= $sel ?>><?= htmlspecialchars($loc['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">תגיות</label>
                <?php $currentTags = implode(', ', array_map(function($t){ return '#' . $t; }, $selected_tags)); ?>
                <input list="all-tags" type="text" class="form-control" name="tags" value="<?= htmlspecialchars($currentTags) ?>" placeholder="#one_eye, friendly" autocomplete="off">
                <datalist id="all-tags">
                  <?php foreach ($all_tags as $tg): ?>
                    <option value="#<?= htmlspecialchars($tg) ?>"></option>
                  <?php endforeach; ?>
                </datalist>
                <div class="form-text">הפרד/י ברווחים, פסיקים או נקודה-פסיק. ניתן להקדים # או להשמיט.</div>
              </div>

              <div class="mb-3">
                <label class="form-label">הוספת מדיה (תמונה/וידאו)</label>
                <input id="media_files" type="file" class="form-control" name="media_files[]" multiple accept="image/*,video/*">
                <div class="form-text">הקבצים יועלו ל-Cloudinary בלבד.</div>
              </div>

              <button class="btn btn-primary" type="submit">שמירה</button>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-header">מדיה קיימת</div>
          <div class="card-body">
            <?php if (!$selected_media): ?>
              <div class="text-muted">אין מדיה</div>
            <?php else: ?>
              <div class="d-flex flex-wrap gap-2">
                <?php foreach ($selected_media as $m): ?>
                  <div class="border rounded p-2 d-flex align-items-center" style="gap:.5rem;">
                    <?php if ($m['type'] === 'image'): ?>
                      <?php $thumb = cloudinary_transform_image_url($m['local_path'], 'c_fill,w_150,h_150,q_auto,f_auto'); ?>
                      <img class="media-thumb" src="<?= htmlspecialchars($thumb ?: $m['local_path']) ?>" alt="" loading="lazy">
                    <?php else: ?>
                      <video class="media-thumb" src="<?= htmlspecialchars($m['local_path']) ?>" muted></video>
                    <?php endif; ?>
                    <form method="post" onsubmit="return confirm('להסיר פריט מדיה זה?');">
                      <input type="hidden" name="id" value="<?= (int)$selected['id'] ?>">
                      <input type="hidden" name="media_id" value="<?= (int)$m['id'] ?>">
                      <input type="hidden" name="action" value="delete_media">
                      <button class="btn btn-sm btn-outline-danger" type="submit">מחק</button>
                    </form>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  // אישור על וידאו בהעלאה
  var form = document.querySelector('form[enctype="multipart/form-data"]');
  if (form) {
    form.addEventListener('submit', function(e){
      var input = document.getElementById('media_files');
      if (input && input.files && input.files.length) {
        for (var i = 0; i < input.files.length; i++) {
          var f = input.files[i];
          if (f && f.type && f.type.indexOf('video') === 0) {
            if (!window.confirm('נבחרו קבצי וידאו. האם להמשיך בהעלאה?')) { e.preventDefault(); }
            break;
          }
        }
      }
    });
  }
})();
</script>
<script>
(function(){
  // AJAX search + render
  var listEl = document.getElementById('js-list');
  var searchEl = document.getElementById('js-search');
  var clearBtn = document.getElementById('js-clear');
  var selectedId = <?= (int)$selected_id ?>;

  function escapeHtml(s){
    return String(s).replace(/[&<>"']/g, function(c){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]);
    });
  }

  function renderList(items){
    if (!listEl) return;
    if (!items || !items.length) {
      listEl.innerHTML = '<div class="text-muted px-3 pb-3">אין חתולים</div>';
      return;
    }
    var q = encodeURIComponent(searchEl ? searchEl.value : '');
    var html = items.map(function(c){
      var active = (selectedId === c.id) ? ' active' : '';
      var loc = c.location_name ? c.location_name : 'ללא מיקום';
      return '<a href="?id=' + c.id + '&q=' + q + '" class="list-group-item list-group-item-action d-flex align-items-center' + active + ' cat-card">' +
               '<div class="flex-fill">' +
                 '<div class="fw-semibold">#' + c.id + ' — ' + escapeHtml(c.name) + '</div>' +
                 '<div class="small text-muted">' + escapeHtml(loc) + '</div>' +
               '</div>' +
             '</a>';
    }).join('');
    listEl.innerHTML = html;
  }

  var inflight = null;
  function doSearch(q){
    var url = 'search_cats.php?q=' + encodeURIComponent(q || '') + '&limit=400';
    if (inflight && typeof inflight.abort === 'function') { inflight.abort(); }
    var ctrl = new AbortController();
    inflight = ctrl;
    fetch(url, { signal: ctrl.signal })
      .then(function(r){ return r.json(); })
      .then(function(data){ if (data && data.success) { renderList(data.items || []); } })
      .catch(function(err){ if (err && err.name === 'AbortError') return; });
  }

  var debTimer = null;
  function debounced(){
    if (debTimer) { clearTimeout(debTimer); }
    debTimer = setTimeout(function(){ doSearch(searchEl ? searchEl.value : ''); }, 200);
  }

  if (searchEl) { searchEl.addEventListener('input', debounced); }
  if (clearBtn) { clearBtn.addEventListener('click', function(){ if (searchEl) { searchEl.value = ''; debounced(); } }); }

  // initial load
  doSearch(<?= json_encode($q, JSON_UNESCAPED_UNICODE) ?>);
})();
</script>
</body>
</html>
