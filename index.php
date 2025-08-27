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
  /* כרטיסים בגובה אחיד ומדיה בגודל עקבי */
  .cat-card { display: flex; flex-direction: column; }
  .cat-media { width: 100%; aspect-ratio: 4 / 3; background: #e9ecef; overflow: hidden; }
  .cat-media img,
  .cat-media video { width: 100%; height: 100%; object-fit: cover; display: block; }
  .cat-card .card-body { display: flex; flex-direction: column; }
  /* אפשרות קליק לפתיחת מודל */
  .cat-card { cursor: pointer; }
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
        // מדיה למודל (כל הפריטים)
        $modalMedia = [];
        foreach ($media as $m) {
          $src = isset($m['local_path']) ? (string)$m['local_path'] : '';
          if (!$src) { continue; }
          if ($m['type'] === 'image') {
            if (strpos($src, 'res.cloudinary.com') !== false) {
              $src = cloudinary_transform_image_url($src);
            }
            $modalMedia[] = ['type' => 'image', 'src' => $src];
          } elseif ($m['type'] === 'video') {
            $modalMedia[] = ['type' => 'video', 'src' => $src];
          }
        }
    ?>
    <div class="col-12 col-sm-6 col-lg-4 mb-4 d-flex">
      <div class="card cat-card shadow-sm h-100 w-100">
        <div class="cat-media">
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
                echo '<img src="' . htmlspecialchars($src) . '" alt="תמונה של ' . htmlspecialchars($cat['name']) . '">';
                $imageShown = true;
                break;
              }
            }
          }
          if (!$imageShown) {
            foreach ($media as $m) {
              if ($m['type'] === 'video') {
                $vsrc = $m['local_path'] ?? '';
                if ($vsrc) {
                  echo '<video controls preload="metadata"><source src="' . htmlspecialchars($vsrc) . '"></video>';
                  break;
                }
              }
            }
          }
        ?>
        </div>
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
          
        </div>
        <?php
          // נתוני חתול למודל בפורמט JSON בתוך תגית script
          $catJson = [
            'id' => (int)$cat['id'],
            'name' => (string)$cat['name'],
            'location' => !empty($cat['location_name']) ? (string)$cat['location_name'] : '',
            'description' => !empty($cat['description']) ? (string)$cat['description'] : '',
            'tags' => array_values($tags),
            'media' => $modalMedia,
          ];
          $catJsonStr = json_encode($catJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
          if ($catJsonStr === false) { $catJsonStr = '{}'; }
          // הגנה בסיסית מול סיום תגית
          $catJsonStr = str_replace('</script>', '<\\/script>', $catJsonStr);
        ?>
        <script type="application/json" class="cat-data"><?= $catJsonStr ?></script>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="mt-4">
    <a class="btn btn-outline-secondary" href="/cat/admin/">אזור ניהול (מוגן)</a>
  </div>
</div>

<!-- מודל להצגת פרטי חתול -->
<div class="modal fade" id="catModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">פרטי חתול</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="סגור"></button>
      </div>
      <div class="modal-body">
        <!-- התוכן ייטען דינמית -->
      </div>
    </div>
  </div>
  
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // פונקציית עזר לאסקייפ של טקסט
  function escapeHtml(str) {
    if (str == null) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  // פתיחת מודל בלחיצה על כרטיס
  document.querySelectorAll('.cat-card').forEach(function(card){
    card.addEventListener('click', function(e){
      // אל תפתח אם לחצו על קישור/כפתור/וידאו
      if (e.target.closest('a, button, .badge, video')) { return; }
      var dataScript = card.querySelector('script.cat-data');
      if (!dataScript) { return; }
      var data;
      try { data = JSON.parse(dataScript.textContent || '{}'); }
      catch (err) { return; }

      var modalEl = document.getElementById('catModal');
      if (!modalEl) { return; }
      modalEl.querySelector('.modal-title').textContent = data.name || 'פרטי חתול';
      var body = modalEl.querySelector('.modal-body');

      var html = '';
      if (Array.isArray(data.media) && data.media.length) {
        if (data.media.length > 1) {
          html += '<div id="catCarousel" class="carousel slide mb-3" data-bs-ride="carousel">';
          html += '<div class="carousel-inner">';
          for (var i = 0; i < data.media.length; i++) {
            var m = data.media[i];
            var active = i === 0 ? ' active' : '';
            if (m.type === 'image') {
              html += '<div class="carousel-item'+active+'">'+
                        '<img src="'+escapeHtml(m.src)+'" class="d-block w-100" alt="">'+
                      '</div>';
            } else if (m.type === 'video') {
              html += '<div class="carousel-item'+active+'">'+
                        '<div class="ratio ratio-16x9">'+
                          '<video controls preload="metadata">'+
                            '<source src="'+escapeHtml(m.src)+'">'+
                          '</video>'+
                        '</div>'+
                      '</div>';
            }
          }
          html += '</div>';
          html += '<button class="carousel-control-prev" type="button" data-bs-target="#catCarousel" data-bs-slide="prev">'+
                    '<span class="carousel-control-prev-icon" aria-hidden="true"></span>'+ 
                    '<span class="visually-hidden">הקודם</span>'+ 
                  '</button>'; 
          html += '<button class="carousel-control-next" type="button" data-bs-target="#catCarousel" data-bs-slide="next">'+
                    '<span class="carousel-control-next-icon" aria-hidden="true"></span>'+ 
                    '<span class="visually-hidden">הבא</span>'+ 
                  '</button>'; 
          html += '</div>';
        } else {
          var m0 = data.media[0];
          if (m0.type === 'image') {
            html += '<img src="'+escapeHtml(m0.src)+'" class="img-fluid mb-3" alt="">';
          } else if (m0.type === 'video') {
            html += '<div class="ratio ratio-16x9 mb-3">'+
                      '<video controls preload="metadata">'+
                        '<source src="'+escapeHtml(m0.src)+'">'+
                      '</video>'+ 
                    '</div>';
          }
        }
      }

      if (data.location) {
        html += '<div class="text-muted small mb-1">מיקום: '+escapeHtml(data.location)+'</div>';
      }
      if (data.description) {
        html += '<p>' + escapeHtml(data.description).replace(/\n/g, '<br>') + '</p>';
      }
      if (Array.isArray(data.tags) && data.tags.length) {
        var urlParams = new URLSearchParams(window.location.search);
        var loc = urlParams.get('location');
        var locQ = loc ? ('&location=' + encodeURIComponent(loc)) : '';
        html += '<div class="mt-2">';
        for (var t = 0; t < data.tags.length; t++) {
          var tag = data.tags[t];
          var href = '?tag=' + encodeURIComponent(tag) + locQ;
          html += '<a href="'+href+'" class="badge rounded-pill text-bg-secondary text-decoration-none me-1">#'+escapeHtml(tag)+'</a>';
        }
        html += '</div>';
      }

      body.innerHTML = html;
      var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      modal.show();
    });
  });
</script>
</body>
</html>
