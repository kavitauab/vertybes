<?php
/**
 * Shared admin layout — head + sidebar open.
 * Usage (from an admin page):
 *   $pageTitle = 'Kontaktai'; $activeNav = 'leads';
 *   require_once __DIR__ . '/includes/head.php';
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers/app.php';
requireAuth();

$pageTitle = $pageTitle ?? 'Administravimas';
$activeNav = $activeNav ?? '';
$currentUser = getCurrentUser();
?><!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= htmlspecialchars($pageTitle) ?> — Vertybių testas</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="css/admin.css?v=<?= assetVersion('css/admin.css') ?>">
</head>
<body>
<div class="layout">
<?php require __DIR__ . '/sidebar.php'; ?>
<main class="main">
<header class="topbar">
  <h1><?= htmlspecialchars($pageTitle) ?></h1>
  <div class="topbar-user">
    <span><?= htmlspecialchars($currentUser['name'] ?? '') ?></span>
    <a href="/logout" title="Atsijungti"><i class="bi bi-box-arrow-right"></i></a>
  </div>
</header>
<div class="content">
