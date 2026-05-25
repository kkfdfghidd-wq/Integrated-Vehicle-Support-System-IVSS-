<?php
if (!isset($pageTitle)) $pageTitle = 'Admin Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — IVSS Admin</title>
  <link rel="stylesheet" href="<?= SITE_URL ?>/css/style.css">
</head>
<body>
<div class="admin-wrapper">