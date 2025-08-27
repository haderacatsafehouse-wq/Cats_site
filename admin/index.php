<?php
// אזור ניהול (מוגן ב-.htaccess)
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/cloudinary.php';

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['add_cat'])) {
        $name = trim($_POST['cat_name'] ?? '');
        $description = trim($_POST['cat_desc'] ?? '');
        $location_id = isset($_POST['cat_location']) && $_POST['cat_location'] !== '' ? (int)$_POST['cat_location'] : null;
        $tags_input = trim($_POST['cat_tags'] ?? '');
        if ($name === '') {
            $errors[] = 'יש להזין שם חתול';
        } else {
  $catId = add_cat($name, $description ?: null, $location_id);
  // תגיות
  if ($tags_input !== '') {
    $tags = parse_tags_input($tags_input);
    if ($tags) { add_tags_for_cat($catId, $tags); }
  }
  // טיפול במדיה (Cloudinary בלבד)
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
          if ($uploaded['success'] ?? false) {
            $publicId = $uploaded['public_id'] ?? null;
            $secureUrl = $uploaded['secure_url'] ?? null;
            add_media($catId, $resType, $publicId, $secureUrl);
          } else {
            $errMsg = isset($uploaded['error']) ? $uploaded['error'] : 'שגיאה לא ידועה בהעלאה ל-Cloudinary';
            $errors[] = 'נכשלה העלאה ל-Cloudinary עבור הקובץ: ' . htmlspecialchars($orig) . ' — ' . htmlspecialchars($errMsg);
          }
                }
            }
            $success = 'החתול נוסף בהצלחה';
        }
    }

  // עריכת תגיות לחתול קיים
  if (isset($_POST['edit_cat_tags'])) {
    $cid = isset($_POST['cid']) ? (int)$_POST['cid'] : 0;
    $tags_input2 = trim($_POST['tags'] ?? '');
    $tags2 = $tags_input2 !== '' ? parse_tags_input($tags_input2) : [];
    if ($cid > 0) {
      if (replace_tags_for_cat($cid, $tags2)) {
        $success = 'תגיות עודכנו';
      } else {
        $errors[] = 'עדכון התגיות נכשל';
      }
    }
  }
}

$locations = fetch_locations();
$all_tags = function_exists('fetch_all_tags') ? fetch_all_tags() : [];
// נטען חתולים קיימים להצגת עריכת תגיות
$cats_for_edit = fetch_cats(null, null);
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
    <a class="navbar-brand" href="/cat">חזרה לרשימה</a>
    <span class="navbar-text text-light">אזור ניהול</span>
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
            </div>
            <button class="btn btn-success" type="submit" name="add_cat" value="1">הוסף חתול</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header">עריכת תגיות לחתולים קיימים</div>
    <div class="card-body">
      <?php if (!$cats_for_edit): ?>
        <div class="text-muted">אין חתולים עדיין.</div>
      <?php else: ?>
        <div class="list-group">
          <?php foreach ($cats_for_edit as $c):
            $ctags = fetch_tags_for_cat((int)$c['id']);
            $current = implode(', ', array_map(function($t){ return '#' . $t; }, $ctags));
          ?>
          <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-start">
              <div class="me-3">
                <div class="fw-semibold"><?= htmlspecialchars($c['name']) ?></div>
                <?php if (!empty($ctags)): ?>
                  <div class="small text-muted">תגיות: 
                    <?php foreach ($ctags as $tg): ?>
                      <span class="badge text-bg-secondary me-1">#<?= htmlspecialchars($tg) ?></span>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <div class="small text-muted">אין תגיות</div>
                <?php endif; ?>
              </div>
              <form class="ms-auto" method="post">
                <input type="hidden" name="cid" value="<?= (int)$c['id'] ?>">
                <div class="input-group" style="max-width: 520px;">
                  <input list="all-tags" type="text" class="form-control" name="tags" value="<?= htmlspecialchars($current) ?>" placeholder="#one_eye, friendly">
                  <button class="btn btn-outline-primary" type="submit" name="edit_cat_tags" value="1">עדכון</button>
                </div>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
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
</body>
</html>
