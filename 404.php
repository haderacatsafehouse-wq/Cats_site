<?php
// דף 404 בסיסי
http_response_code(404);
?><!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>404 - דף לא נמצא</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
  <link href="inc/theme.css" rel="stylesheet">
</head>
<body class="p-4">
  <div class="container">
    <div class="alert alert-warning">הדף שחיפשת לא נמצא.</div>
    <a href="/" class="btn btn-primary">חזרה לדף הבית</a>
  </div>
</body>
</html>
