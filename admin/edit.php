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
  $links_input = isset($_POST['linked_ids']) ? (string)$_POST['linked_ids'] : '';

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
        // קישורים
        $linkIdsSet = [];
        foreach (preg_split('/[\s,;]+/', $links_input, -1, PREG_SPLIT_NO_EMPTY) as $tok) {
          $v = (int)$tok; if ($v > 0) { $linkIdsSet[$v] = true; }
        }
        if (!replace_links_for_cat($cid, array_keys($linkIdsSet))) {
          $errors[] = 'עדכון קישורים נכשל';
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

  if ($action === 'set_main_media') {
    $cid = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $mid = isset($_POST['media_id']) ? (int)$_POST['media_id'] : 0;
    if ($cid > 0 && $mid > 0) {
      if (set_main_media($cid, $mid)) {
        $success = 'תמונת המפתח עודכנה';
      } else {
        $errors[] = 'עדכון תמונת המפתח נכשל';
      }
    }
    $selected_id = $cid ?: $selected_id;
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
$selected_links = $selected ? fetch_linked_cats_for_cat($selected_id) : [];
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
  .thumb { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; margin-inline-start: .5rem; }
  .thumb-placeholder { width: 36px; height: 36px; border-radius: 50%; background: #e9ecef; display: inline-block; margin-inline-start: .5rem; border: 1px solid #dee2e6; }
    @media (min-width: 992px) {
      .split { display: grid; grid-template-columns: 380px 1fr; gap: 1rem; }
      .cat-list { max-height: calc(100vh - 160px); overflow: auto; }
      .sticky-top-sm { top: 1rem; }
    }
  .main-badge { position: absolute; top: .25rem; inset-inline-end: .25rem; }
  .media-card { position: relative; }
  .media-card.main { outline: 2px solid #0d6efd; border-radius: .5rem; }
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

    <div id="edit-section">
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
                  <div class="row g-3">
                    <?php foreach ($selected_media as $m): ?>
                      <?php
                        $isImage = isset($m['type']) && strpos(strtolower((string)$m['type']), 'image') === 0;
                        $isVideo = isset($m['type']) && strpos(strtolower((string)$m['type']), 'video') === 0;
                        $src = isset($m['local_path']) ? (string)$m['local_path'] : '';
                        if ($src && $isImage && strpos($src, 'res.cloudinary.com') !== false) {
                          $src = cloudinary_transform_image_url($src, 'c_fill,w_320,h_240,q_auto,f_auto');
                        }
                        $isMain = !empty($m['is_main']);
                      ?>
                      <div class="col-6 col-md-4 col-lg-3">
                        <div class="card media-card <?= $isMain ? 'main' : '' ?>">
                          <div class="card-img-top ratio ratio-4x3 d-flex align-items-center justify-content-center bg-light">
                            <?php if ($isImage && $src): ?>
                              <img src="<?= htmlspecialchars($src) ?>" alt="" class="w-100 h-100" style="object-fit:cover; border-top-left-radius:.5rem;border-top-right-radius:.5rem;">
                            <?php elseif ($isVideo && $src): ?>
                              <span class="text-muted small">וידאו</span>
                            <?php else: ?>
                              <span class="text-muted small">ללא תצוגה</span>
                            <?php endif; ?>
                          </div>
                          <?php if ($isMain): ?>
                            <span class="badge text-bg-primary main-badge">תמונת מפתח</span>
                          <?php endif; ?>
                          <div class="card-body p-2 d-flex gap-2">
                            <?php if ($isImage): ?>
                              <form method="post" class="ms-auto">
                                <input type="hidden" name="action" value="set_main_media">
                                <input type="hidden" name="id" value="<?= (int)$selected['id'] ?>">
                                <input type="hidden" name="media_id" value="<?= (int)$m['id'] ?>">
                                <button type="submit" class="btn btn-sm <?= $isMain ? 'btn-primary' : 'btn-outline-primary' ?>" <?= $isMain ? 'disabled' : '' ?>>בחר כתמונת מפתח</button>
                              </form>
                            <?php endif; ?>
                            <form method="post" onsubmit="return confirm('למחוק פריט מדיה זה?');">
                              <input type="hidden" name="action" value="delete_media">
                              <input type="hidden" name="id" value="<?= (int)$selected['id'] ?>">
                              <input type="hidden" name="media_id" value="<?= (int)$m['id'] ?>">
                              <button type="submit" class="btn btn-sm btn-outline-danger">מחק</button>
                            </form>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <?php endif; ?>
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
                <label class="form-label">חתולים קשורים</label>
                <input id="linked_search" type="search" class="form-control" placeholder="חיפוש והוספה של חתולים קשורים" autocomplete="off">
                <div id="linked_suggest" class="list-group mt-1" style="max-height:220px; overflow:auto; display:none;"></div>
                <div id="linked_selected" class="mt-2 d-flex flex-wrap gap-2" aria-live="polite">
                  <?php if ($selected_links): foreach ($selected_links as $lc): ?>
                    <span class="badge text-bg-primary d-flex align-items-center" style="gap:.25rem;">
                      <span>#<?= (int)$lc['id'] ?> <?= htmlspecialchars($lc['name']) ?></span>
                      <button type="button" class="btn-close btn-close-white ms-1 js-remove-link" aria-label="הסר" data-id="<?= (int)$lc['id'] ?>"></button>
                    </span>
                  <?php endforeach; else: ?>
                    <span class="text-muted">לא נבחרו קישורים</span>
                  <?php endif; ?>
                </div>
                <?php $linkIds = $selected_links ? implode(',', array_map(function($x){ return (string)(int)$x['id']; }, $selected_links)) : ''; ?>
                <input id="linked_ids" type="hidden" name="linked_ids" value="<?= htmlspecialchars($linkIds) ?>">
                <div class="form-text">ניתן לקשר מספר חתולים. חפשו לפי שם/תיאור/מיקום ולחצו להוספה.</div>
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
  var didAutoScroll = false;

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
      var img = c.thumb_url ? '<img class="thumb" src="' + encodeURI(c.thumb_url) + '" alt="" loading="lazy">' : '<span class="thumb-placeholder" title="ללא תמונה"></span>';
  return '<a href="?id=' + c.id + '&q=' + q + '" class="list-group-item list-group-item-action d-flex align-items-center' + active + ' cat-card">' +
               img +
               '<div class="flex-fill">' +
                 '<div class="fw-semibold">#' + c.id + ' — ' + escapeHtml(c.name) + '</div>' +
                 '<div class="small text-muted">' + escapeHtml(loc) + '</div>' +
               '</div>' +
             '</a>';
    }).join('');
    listEl.innerHTML = html;

    // After rendering, scroll to the edit pane if a cat is selected (one time per load)
    if (!didAutoScroll && selectedId > 0) {
      var el = document.getElementById('edit-section');
      if (el && typeof el.scrollIntoView === 'function') {
        // Use smooth scroll and align to start
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } else {
        // Fallback: scroll to bottom
        var h = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);
        window.scrollTo({ top: h, behavior: 'smooth' });
      }
      didAutoScroll = true;
    }
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
  // Note: Scrolling is handled after the list renders (renderList)
})();
</script>
<script>
// בחירת חתולים קשורים בעמוד עריכה
(function(){
  var input = document.getElementById('linked_search');
  var list = document.getElementById('linked_suggest');
  var wrap = document.getElementById('linked_selected');
  var hidden = document.getElementById('linked_ids');
  var currentId = <?= (int)$selected_id ?>;
  if (!input || !list || !wrap || !hidden) return;

  function escapeHtml(str){
    if (str == null) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  // בונים סט קיים מה-HIDDEN והמרקר בדף
  var selected = {};
  (hidden.value || '').split(/[,\s;]+/).forEach(function(tok){ var v=parseInt(tok||'0',10); if (v>0) selected[v]=true; });

  function updateHidden(){
    var ids = Object.keys(selected).map(function(k){ return parseInt(k,10) || 0; }).filter(Boolean);
    hidden.value = ids.join(',');
  }
  function attachRemove(){
    wrap.querySelectorAll('.js-remove-link').forEach(function(btn){
      btn.addEventListener('click', function(){
        var id = parseInt(btn.getAttribute('data-id')||'0',10);
        if (id && selected[id]) { delete selected[id]; updateHidden(); btn.parentElement.remove(); if (!wrap.querySelector('.badge')) { wrap.innerHTML = '<span class="text-muted">לא נבחרו קישורים</span>'; } }
      });
    });
  }
  attachRemove();

  function hideSuggest(){ list.style.display = 'none'; list.innerHTML=''; }
  function showSuggest(items){
    if (!items || !items.length) { hideSuggest(); return; }
    var html = items.map(function(c){
      var img = c.thumb_url ? '<img src="'+encodeURI(c.thumb_url)+'" class="me-2" style="width:28px;height:28px;border-radius:50%;object-fit:cover;" alt="">' : '<span class="me-2" style="display:inline-block;width:28px;height:28px;border-radius:50%;background:#e9ecef;border:1px solid #dee2e6;"></span>';
      var loc = c.location_name ? '<div class="small text-muted">'+escapeHtml(c.location_name)+'</div>' : '';
      return '<button type="button" class="list-group-item list-group-item-action d-flex align-items-center" data-id="'+c.id+'" data-name="'+escapeHtml(c.name)+'">'+ img + '<div class="flex-fill"><div class="fw-semibold">#'+c.id+' — '+escapeHtml(c.name)+'</div>'+loc+'</div></button>';
    }).join('');
    list.innerHTML = html; list.style.display = 'block';
    list.querySelectorAll('.list-group-item').forEach(function(it){
      it.addEventListener('click', function(){
        var id = parseInt(it.getAttribute('data-id')||'0',10);
        if (id && !selected[id]) {
          selected[id] = true; updateHidden();
          // append chip
          if (wrap.querySelector('.text-muted')) { wrap.innerHTML = ''; }
          var span = document.createElement('span');
          span.className = 'badge text-bg-primary d-flex align-items-center'; span.style.gap='.25rem';
          span.innerHTML = '<span>#'+id+' '+escapeHtml(it.getAttribute('data-name')||'')+'</span><button type="button" class="btn-close btn-close-white ms-1 js-remove-link" aria-label="הסר" data-id="'+id+'"></button>';
          wrap.appendChild(span);
          attachRemove();
        }
        input.value = ''; hideSuggest(); input.focus();
      });
    });
  }

  var inflight = null, debTimer = null;
  function doSearch(q){
    var excludeIds = Object.keys(selected);
    if (currentId) excludeIds.push(String(currentId));
    var url = 'search_cats.php?q=' + encodeURIComponent(q||'') + '&limit=20' + (excludeIds.length?('&exclude='+encodeURIComponent(excludeIds.join(','))):'');
    if (inflight && typeof inflight.abort === 'function') { inflight.abort(); }
    var ctrl = new AbortController(); inflight = ctrl;
    fetch(url, { signal: ctrl.signal }).then(function(r){ return r.json(); }).then(function(d){ if (d && d.success) { showSuggest(d.items||[]); } }).catch(function(err){ if (err && err.name === 'AbortError') return; hideSuggest(); });
  }
  function debounced(){ if (debTimer) clearTimeout(debTimer); debTimer = setTimeout(function(){ var v=input.value.trim(); if (v) doSearch(v); else hideSuggest(); }, 200); }

  input.addEventListener('input', debounced);
  input.addEventListener('blur', function(){ setTimeout(hideSuggest, 200); });
})();
</script>
</body>
</html>
