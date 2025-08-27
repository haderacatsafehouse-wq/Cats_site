<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/cloudinary.php';

// כיוון RTL ושפה עברית
?><!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(SITE_TITLE) ?></title>
    <!-- Bootstrap 5 via CDN (תואם UI מודרני; ניתן להחליף ל-RTL מלא במידת הצורך) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .cat-card img { object-fit: cover; height: 200px; }
        .cat-card video { width: 100%; height: auto; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container-fluid">
    <span class="navbar-brand"><?= htmlspecialchars(SITE_TITLE) ?></span>
  </div>
</nav>
<div class="container">
  <div class="row mb-3">
    <div class="col-12 col-md-6">
      <h1 class="h4">רשימת החתולים במקלט</h1>
    </div>
    <div class="col-12 col-md-6">
      <form class="d-flex" method="get">
        <select name="location" class="form-select me-2">
          <option value="">כל המיקומים</option>
          <?php $locs = fetch_locations(); $sel = isset($_GET['location']) ? (int)$_GET['location'] : 0; ?>
          <?php foreach ($locs as $loc): ?>
            <option value="<?= (int)$loc['id'] ?>" <?= $sel === (int)$loc['id'] ? 'selected' : '' ?>><?= htmlspecialchars($loc['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php $tagSel = isset($_GET['tag']) ? trim((string)$_GET['tag']) : ''; ?>
        <input type="text" name="tag" value="<?= htmlspecialchars($tagSel) ?>" class="form-control me-2" placeholder="#תגית או תגית">
        <button class="btn btn-primary" type="submit">סינון</button>
      </form>
    </div>
  </div>

  <div class="row" id="cats">
    <?php
  $locationFilter = isset($_GET['location']) && $_GET['location'] !== '' ? (int)$_GET['location'] : null;
  $tagFilter = isset($_GET['tag']) && $_GET['tag'] !== '' ? $_GET['tag'] : null;
  $cats = fetch_cats($locationFilter, $tagFilter);
      if (!$cats) {
          echo '<div class="col-12"><div class="alert alert-info">אין חתולים להצגה עדיין.</div></div>';
      }
      foreach ($cats as $cat):
        $media = fetch_media_for_cat((int)$cat['id']);
        $tags = function_exists('fetch_tags_for_cat') ? fetch_tags_for_cat((int)$cat['id']) : [];
    ?>
    <div class="col-12 col-sm-6 col-lg-4 mb-4">
      <div class="card cat-card shadow-sm">
        <?php
          $imageShown = false;
          foreach ($media as $m) {
            if ($m['type'] === 'image') {
              $src = $m['local_path'] ?? '';
              if ($src) {
                // אם זה URL של Cloudinary נבקש גרסה קלה יותר
                if (strpos($src, 'res.cloudinary.com') !== false) {
                  $src = cloudinary_transform_image_url($src);
                }
                echo '<img class="card-img-top" src="' . htmlspecialchars($src) . '" alt="תמונה של ' . htmlspecialchars($cat['name']) . '">';
                $imageShown = true;
                break;
              }
            }
          }
        ?>
        <div class="card-body">
          <h5 class="card-title mb-1"><?= htmlspecialchars($cat['name']) ?></h5>
          <?php if (!empty($cat['location_name'])): ?>
            <div class="text-muted small">מיקום: <?= htmlspecialchars($cat['location_name']) ?></div>
          <?php endif; ?>
          <?php if (!empty($cat['description'])): ?>
            <p class="card-text mt-2"><?= nl2br(htmlspecialchars($cat['description'])) ?></p>
          <?php endif; ?>
          <?php if (!empty($tags)): ?>
            <div class="mt-2" aria-label="תגיות">
              <?php
                // לשמירת סינון מיקום קיים
                $qLoc = $locationFilter ? ('&location=' . (int)$locationFilter) : '';
              ?>
              <?php foreach ($tags as $tg): ?>
                <?php $href = '?tag=' . urlencode($tg) . $qLoc; ?>
                <a href="<?= htmlspecialchars($href) ?>" class="badge rounded-pill text-bg-secondary text-decoration-none me-1">#<?= htmlspecialchars($tg) ?></a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <?php if (!$imageShown): ?>
            <?php foreach ($media as $m): if ($m['type'] === 'video') { $vsrc = $m['local_path'] ?? ''; if ($vsrc) { ?>
              <video controls class="mt-2"><source src="<?= htmlspecialchars($vsrc) ?>"></video>
            <?php break; } } endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="mt-4">
    <a class="btn btn-outline-secondary" href="/cat/admin/">אזור ניהול (מוגן)</a>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
