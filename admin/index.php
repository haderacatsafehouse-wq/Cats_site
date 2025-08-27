<?php
// אזור ניהול (מוגן בסיסמא דרך Session; ניתן בנוסף להגן ב-.htaccess)
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/cloudinary.php';

// הגנה על העמוד
if (isset($_GET['logout'])) { admin_logout(); }
require_admin_auth_or_login_form();

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['add_cat'])) {
        $name = trim($_POST['cat_name'] ?? '');
        $description = trim($_POST['cat_desc'] ?? '');
        $location_id = isset($_POST['cat_location']) && $_POST['cat_location'] !== '' ? (int)$_POST['cat_location'] : null;
        $tags_input = trim($_POST['cat_tags'] ?? '');
  $mainIndex = isset($_POST['main_media_index']) ? (int)$_POST['main_media_index'] : -1; // אינדקס הקובץ שסומן כתמונת מפתח
        if ($name === '') {
            $errors[] = 'יש להזין שם חתול';
        } else {
  $catId = add_cat($name, $description ?: null, $location_id);
  // תגיות
  if ($tags_input !== '') {
    $tags = parse_tags_input($tags_input);
    if ($tags) { add_tags_for_cat($catId, $tags); }
  }
  // קישורים לחתולים אחרים (אופציונלי)
  if (isset($_POST['linked_ids'])) {
    $linkedStr = (string)$_POST['linked_ids'];
    $ids = [];
    foreach (preg_split('/[\s,;]+/', $linkedStr, -1, PREG_SPLIT_NO_EMPTY) as $tok) {
      $v = (int)$tok; if ($v > 0) { $ids[$v] = true; }
    }
    if ($ids) { add_links_for_cat($catId, array_keys($ids)); }
  }
  // טיפול במדיה (Cloudinary בלבד)
    if (!empty($_FILES['media_files']['name'][0])) {
                $count = count($_FILES['media_files']['name']);
                $firstImageMid = null; $markedMain = false;
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
          if ($uploaded['success'] ?? false) {
            $publicId = $uploaded['public_id'] ?? null;
            $secureUrl = $uploaded['secure_url'] ?? null;
            $mid = add_media($catId, $resType, $publicId, $secureUrl);
            if ($isImage && $mid) {
              if ($firstImageMid === null) { $firstImageMid = (int)$mid; }
              if (!$markedMain && $i === $mainIndex) {
                if (set_main_media($catId, (int)$mid)) { $markedMain = true; }
              }
            }
          } else {
            $errMsg = isset($uploaded['error']) ? $uploaded['error'] : 'שגיאה לא ידועה בהעלאה ל-Cloudinary';
            $errors[] = 'נכשלה העלאה ל-Cloudinary עבור הקובץ: ' . htmlspecialchars($orig) . ' — ' . htmlspecialchars($errMsg);
          }
                }
                // אם לא נבחרה ידנית תמונת מפתח ויש תמונת סטילס — בחר את הראשונה
                if (!$markedMain && $firstImageMid) {
                    set_main_media($catId, (int)$firstImageMid);
                }
            }
            $success = 'החתול נוסף בהצלחה';
        }
    }
}

$locations = fetch_locations();
$all_tags = function_exists('fetch_all_tags') ? fetch_all_tags() : [];
?><!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ניהול - <?= htmlspecialchars(SITE_TITLE) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
  <link href="../inc/theme.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="/cat">
      <img class="brand-logo" src="https://www.haderacats.org.il/wp-content/uploads/2025/07/Untitled12.png" alt="לוגו" loading="eager">
      <span class="ms-2">חזרה לרשימה</span>
    </a>
  <span class="navbar-text text-light">אזור ניהול</span>
  <div class="ms-auto d-flex align-items-center gap-2">
    <a class="btn btn-sm btn-outline-light" href="/cat/admin/edit.php">עריכת חתולים</a>
    <a class="btn btn-sm btn-outline-warning" href="?logout=1" onclick="return confirm('לצאת מהמערכת?');">יציאה</a>
  </div>
  </div>
</nav>
<div class="container">
  <?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $e) { echo '<div>' . htmlspecialchars($e) . '</div>'; } ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <div class="row">
    <div class="col-12 mb-4">
      <div class="card">
        <div class="card-header">הוספת חתול</div>
        <div class="card-body">
          <form id="cat-form" method="post" enctype="multipart/form-data">
            <div class="mb-3">
              <label class="form-label">שם החתול</label>
              <input type="text" class="form-control" name="cat_name" required>
            </div>
            <div class="mb-3">
              <label class="form-label">תיאור (לא חובה)</label>
              <textarea class="form-control" name="cat_desc" rows="3"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">תגיות (האשטגים)</label>
              <input id="cat_tags" list="all-tags" type="text" class="form-control" name="cat_tags" placeholder="#one_eye, #friendly, #kitten" autocomplete="off">
              <datalist id="all-tags">
                <?php foreach ($all_tags as $tg): ?>
                  <option value="#<?= htmlspecialchars($tg) ?>"></option>
                <?php endforeach; ?>
              </datalist>
              <div class="form-text">הפרד/י ברווחים, פסיקים או נקודה-פסיק. ניתן להקדים # או להשמיט.</div>
              <?php if (!empty($all_tags)): ?>
              <div class="mt-2">
                <div class="small text-muted mb-1">תגיות קיימות (לחיצה מוסיפה לשדה):</div>
                <?php foreach ($all_tags as $tg): ?>
                  <button type="button" class="btn btn-sm btn-outline-secondary me-1 mb-1 js-add-tag" data-tag="#<?= htmlspecialchars($tg) ?>">#<?= htmlspecialchars($tg) ?></button>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>
            <div class="mb-3">
              <label class="form-label">חתולים קשורים (אופציונלי)</label>
              <input id="linked_search" type="search" class="form-control" placeholder="חיפוש והוספה של חתולים קשורים" autocomplete="off">
              <div id="linked_suggest" class="list-group mt-1" style="max-height:220px; overflow:auto; display:none;"></div>
              <div id="linked_selected" class="mt-2 d-flex flex-wrap gap-2" aria-live="polite"></div>
              <input id="linked_ids" type="hidden" name="linked_ids" value="">
              <div class="form-text">ניתן לקשר מספר חתולים. חפשו לפי שם/תיאור/מיקום ולחצו להוספה.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">מיקום</label>
              <select class="form-select" name="cat_location">
                <option value="">ללא</option>
                <?php foreach ($locations as $loc): ?>
                  <option value="<?= (int)$loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">מדיה (תמונות/וידאו, ניתן לבחור מספר קבצים)</label>
              <input id="media_files" type="file" class="form-control" name="media_files[]" multiple accept="image/*,video/*">
              <div class="form-text">הקבצים יועלו ל-Cloudinary בלבד (ללא שמירה מקומית).</div>
              <input type="hidden" id="main_media_index" name="main_media_index" value="-1">
              <div id="media_preview" class="row g-2 mt-2"></div>
            </div>
            <button class="btn btn-success" type="submit" name="add_cat" value="1">הוסף חתול</button>
          </form>
        </div>
      </div>
    </div>
  </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  var form = document.getElementById('cat-form');
  if (!form) return;
  var tagsInput = document.getElementById('cat_tags');
  // מניעת שליחה אוטומטית באמצעות מקש Enter (למעט בתוך textarea)
  form.addEventListener('keydown', function(e){
    var tag = e.target && e.target.tagName ? e.target.tagName.toUpperCase() : '';
    if (e.key === 'Enter' && tag !== 'TEXTAREA') {
      e.preventDefault();
    }
  });
  // בקשת אישור כאשר יש וידאו בין הקבצים
  form.addEventListener('submit', function(e){
    var input = document.getElementById('media_files');
    if (input && input.files && input.files.length) {
      for (var i = 0; i < input.files.length; i++) {
        var f = input.files[i];
        if (f && f.type && f.type.indexOf('video') === 0) {
          var ok = window.confirm('נבחרו קבצי וידאו. האם להמשיך בהעלאה?');
          if (!ok) { e.preventDefault(); }
          break;
        }
      }
    }
  });

  // תצוגת תצוגה מקדימה ובחירת תמונת מפתח
  (function(){
    var input = document.getElementById('media_files');
    var preview = document.getElementById('media_preview');
    var hiddenMain = document.getElementById('main_media_index');
    var objectUrls = [];
    function clean(){ objectUrls.forEach(function(u){ try{ URL.revokeObjectURL(u); }catch(e){} }); objectUrls = []; }
    function render(){
      if (!preview || !input) return;
      clean();
      preview.innerHTML = '';
      var files = input.files; if (!files || !files.length) { if (hiddenMain) hiddenMain.value = -1; return; }
      var firstImageIndex = -1;
      for (var i=0;i<files.length;i++){
        (function(i){
          var f = files[i]; var isImg = f && f.type && f.type.indexOf('image')===0;
          if (firstImageIndex === -1 && isImg) firstImageIndex = i;
          var col = document.createElement('div'); col.className = 'col-4 col-md-3';
          var card = document.createElement('div'); card.className = 'position-relative border rounded overflow-hidden'; card.style.cursor='pointer';
          var badge = document.createElement('span'); badge.className = 'badge bg-primary position-absolute'; badge.style.top='.25rem'; badge.style.insetInlineEnd='.25rem'; badge.textContent='תמונת מפתח'; badge.style.display='none';
          var inner;
          if (isImg) {
            var url = URL.createObjectURL(f); objectUrls.push(url);
            inner = document.createElement('img'); inner.src = url; inner.alt=''; inner.style.width='100%'; inner.style.height='90px'; inner.style.objectFit='cover';
          } else {
            inner = document.createElement('div'); inner.className='d-flex align-items-center justify-content-center bg-light'; inner.style.height='90px'; inner.innerHTML='<span class="small text-muted">וידאו</span>';
          }
          card.appendChild(inner); card.appendChild(badge); col.appendChild(card); preview.appendChild(col);
          function select(){ if (!isImg) return; if (hiddenMain) hiddenMain.value = String(i); updateBadges(); }
          card.addEventListener('click', select);
          card.addEventListener('keydown', function(ev){ if (ev.key==='Enter' || ev.key===' ') { ev.preventDefault(); select(); } });
          card.setAttribute('tabindex', isImg ? '0' : '-1');
        })(i);
      }
      function updateBadges(){
        var chosen = hiddenMain ? parseInt(hiddenMain.value||'-1',10) : -1;
        var cards = preview.querySelectorAll('.position-relative');
        cards.forEach(function(c, idx){ var b=c.querySelector('.badge'); if (!b) return; b.style.display = (idx===chosen) ? 'inline-block' : 'none'; c.style.outline = (idx===chosen)?'2px solid #0d6efd':'none'; });
      }
      // בחר אוטומטית את תמונת הסטילס הראשונה אם לא נבחרה
      if (hiddenMain && (parseInt(hiddenMain.value||'-1',10)<0) && firstImageIndex>=0) { hiddenMain.value = String(firstImageIndex); }
      updateBadges();
    }
    if (input) { input.addEventListener('change', render); }
  })();

  // הוספת תגית קיימת בלחיצה
  var tagButtons = document.querySelectorAll('.js-add-tag');
  tagButtons.forEach(function(btn){
    btn.addEventListener('click', function(){
      var t = btn.getAttribute('data-tag') || '';
      if (!tagsInput) return;
      var current = tagsInput.value.trim();
      if (!current) { tagsInput.value = t; return; }
      // אם כבר קיים בדיוק
      var norm = function(s){ return s.replace(/\s+/g,' ').trim(); };
      var items = norm(current).split(/[\s,;]+/).filter(Boolean);
      if (items.indexOf(t) === -1 && items.indexOf(t.replace(/^#/, '')) === -1 && items.indexOf(t.replace(/^#/, '')) === -1) {
        tagsInput.value = current + ', ' + t;
      }
      tagsInput.focus();
    });
  });

  // אוטוקומפליט יותר נוח: השארת חלק אחרון לשילוב עם datalist (רשימות מרובות)
  // בעת הקלדה, נוודא שהדאטאליסט עובד על המקטע האחרון בלבד
  if (tagsInput) {
    tagsInput.addEventListener('input', function(){
      // native datalist will still show suggestions; no-op here
    });
  }
})();
</script>
<script>
// בחירת חתולים קשורים בעמוד הוספה
(function(){
  var input = document.getElementById('linked_search');
  var list = document.getElementById('linked_suggest');
  var wrap = document.getElementById('linked_selected');
  var hidden = document.getElementById('linked_ids');
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

  var selected = {}; // id -> {id,name,thumb_url}

  function updateHidden(){
    var ids = Object.keys(selected).map(function(k){ return parseInt(k,10) || 0; }).filter(Boolean);
    hidden.value = ids.join(',');
  }
  function renderSelected(){
    var html = Object.keys(selected).map(function(k){
      var c = selected[k];
      var title = c.location_name ? (' — ' + c.location_name) : '';
      var img = c.thumb_url ? '<img src="'+encodeURI(c.thumb_url)+'" class="me-1" style="width:24px;height:24px;border-radius:50%;object-fit:cover;" alt="">' : '';
      return '<span class="badge text-bg-primary d-flex align-items-center" style="gap:.25rem;">'+ img +
             '<span>#'+c.id+' '+escapeHtml(c.name)+'</span>'+
             '<button type="button" class="btn-close btn-close-white ms-1" aria-label="הסר" data-id="'+c.id+'"></button>'+
             '</span>';
    }).join('');
    wrap.innerHTML = html || '<span class="text-muted">לא נבחרו קישורים</span>';
    wrap.querySelectorAll('.btn-close').forEach(function(btn){
      btn.addEventListener('click', function(){
        var id = parseInt(btn.getAttribute('data-id')||'0',10);
        if (id && selected[id]) { delete selected[id]; updateHidden(); renderSelected(); }
      });
    });
  }

  function hideSuggest(){ list.style.display = 'none'; list.innerHTML=''; }
  function showSuggest(items){
    if (!items || !items.length) { hideSuggest(); return; }
    var html = items.map(function(c){
      var img = c.thumb_url ? '<img src="'+encodeURI(c.thumb_url)+'" class="me-2" style="width:28px;height:28px;border-radius:50%;object-fit:cover;" alt="">' : '<span class="me-2" style="display:inline-block;width:28px;height:28px;border-radius:50%;background:#e9ecef;border:1px solid #dee2e6;"></span>';
      var loc = c.location_name ? '<div class="small text-muted">'+escapeHtml(c.location_name)+'</div>' : '';
      return '<button type="button" class="list-group-item list-group-item-action d-flex align-items-center" data-id="'+c.id+'" data-name="'+escapeHtml(c.name)+'" data-loc="'+escapeHtml(c.location_name||'')+'" data-thumb="'+(c.thumb_url?encodeURI(c.thumb_url):'')+'">'
             + img + '<div class="flex-fill"><div class="fw-semibold">#'+c.id+' — '+escapeHtml(c.name)+'</div>'+loc+'</div></button>';
    }).join('');
    list.innerHTML = html; list.style.display = 'block';
    list.querySelectorAll('button[list-group-item]').forEach(function(btn){
      // no-op safety; selector above might not match correctly in some engines
    });
    list.querySelectorAll('.list-group-item').forEach(function(it){
      it.addEventListener('click', function(){
        var id = parseInt(it.getAttribute('data-id')||'0',10);
        var nm = it.getAttribute('data-name')||'';
        var loc = it.getAttribute('data-loc')||'';
        var th = it.getAttribute('data-thumb')||'';
        if (id && !selected[id]) { selected[id] = {id:id, name:nm, location_name:loc, thumb_url:th}; updateHidden(); renderSelected(); }
        input.value = ''; hideSuggest(); input.focus();
      });
    });
  }

  var inflight = null, debTimer = null;
  function doSearch(q){
    var exclude = Object.keys(selected).join(',');
    var url = 'search_cats.php?q=' + encodeURIComponent(q||'') + '&limit=20' + (exclude?('&exclude='+encodeURIComponent(exclude)):'');
    if (inflight && typeof inflight.abort === 'function') { inflight.abort(); }
    var ctrl = new AbortController(); inflight = ctrl;
    fetch(url, { signal: ctrl.signal }).then(function(r){ return r.json(); }).then(function(d){
      if (d && d.success) { showSuggest(d.items||[]); }
    }).catch(function(err){ if (err && err.name === 'AbortError') return; hideSuggest(); });
  }
  function debounced(){ if (debTimer) clearTimeout(debTimer); debTimer = setTimeout(function(){ var v=input.value.trim(); if (v) doSearch(v); else hideSuggest(); }, 200); }

  input.addEventListener('input', debounced);
  input.addEventListener('blur', function(){ setTimeout(hideSuggest, 200); });

  // init empty
  updateHidden(); renderSelected();
})();
</script>
</body>
</html>
