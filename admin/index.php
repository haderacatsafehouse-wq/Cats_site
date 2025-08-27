<?php
// אזור ניהול (מוגן ב-.htaccess)
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/cloudinary.php';

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_location'])) {
        $name = trim($_POST['location_name'] ?? '');
        if ($name === '') {
            $errors[] = 'יש להזין שם מיקום';
        } else {
            if (add_location($name)) {
                $success = 'המיקום נוסף בהצלחה';
            } else {
                $errors[] = 'לא ניתן להוסיף את המיקום (ייתכן שכבר קיים)';
            }
        }
    }

    if (isset($_POST['add_cat'])) {
        $name = trim($_POST['cat_name'] ?? '');
        $description = trim($_POST['cat_desc'] ?? '');
        $location_id = isset($_POST['cat_location']) && $_POST['cat_location'] !== '' ? (int)$_POST['cat_location'] : null;
        if ($name === '') {
            $errors[] = 'יש להזין שם חתול';
        } else {
  $catId = add_cat($name, $description ?: null, $location_id);
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
}

$locations = fetch_locations();
?><!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ניהול - <?= htmlspecialchars(SITE_TITLE) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
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
    <div class="col-12 col-lg-5 mb-4">
      <div class="card">
        <div class="card-header">הוספת מיקום חדש</div>
        <div class="card-body">
          <form method="post">
            <div class="mb-3">
              <label class="form-label">שם המיקום</label>
              <input type="text" class="form-control" name="location_name" required>
            </div>
            <button class="btn btn-primary" type="submit" name="add_location" value="1">הוסף מיקום</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-7 mb-4">
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
    <div class="card-header">מיקומים קיימים</div>
    <div class="card-body">
      <?php if (!$locations): ?>
        <div class="text-muted">אין מיקומים עדיין.</div>
      <?php else: ?>
        <ul class="list-group">
          <?php foreach ($locations as $loc): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span><?= htmlspecialchars($loc['name']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  var form = document.getElementById('cat-form');
  if (!form) return;
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
})();
</script>
</body>
</html>
