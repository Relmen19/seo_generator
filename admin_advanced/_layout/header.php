<?php
/**
 * Shared layout header for admin_advanced.
 *
 * Caller-provided variables (all optional):
 *   $pageTitle       string  — <title>
 *   $activeNav       string  — id of active sidebar item: articles|clustering|profile|themes|cost
 *   $pageHeading     string  — large heading shown in topbar
 *   $pageSubheading  string  — small grey line under heading
 *   $bodyClass       string  — extra classes for <body>
 *   $extraHead       string  — raw HTML appended to <head>
 *   $hideTopbar      bool    — render bare layout without topbar
 *
 * Caller is expected to have already required config.php + auth.php and called requireAuth().
 */

declare(strict_types=1);

$pageTitle      = $pageTitle      ?? 'SEO admin';
$activeNav      = $activeNav      ?? '';
$pageHeading    = $pageHeading    ?? '';
$pageSubheading = $pageSubheading ?? '';
$bodyClass      = $bodyClass      ?? '';
$extraHead      = $extraHead      ?? '';
$hideTopbar     = $hideTopbar     ?? false;

$adminUser = htmlspecialchars($_SESSION['seo_user'] ?? 'admin', ENT_QUOTES, 'UTF-8');
$assetVer  = '23';
?><!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          sand:  { 50:'#FBF8F3', 100:'#F4EEE2', 200:'#ECE6DC', 300:'#DDD3BF', 400:'#C2B59A' },
          ink:   { 900:'#171511', 800:'#2A2620', 700:'#3A3530', 500:'#6F665B', 300:'#9A9285', 200:'#BDB5A6' },
          sun:   { 300:'#FBE08A', 400:'#F5C842', 500:'#EAB308' },
          ember: { 400:'#EF6E51', 500:'#E04E2C' },
        },
        fontFamily: { sans: ['Inter','system-ui','sans-serif'] },
        borderRadius: { '4xl': '2rem', '5xl': '2.5rem' },
        boxShadow: {
          card:  '0 1px 2px rgba(23,21,17,0.04), 0 8px 24px rgba(23,21,17,0.04)',
          rail:  '0 1px 2px rgba(23,21,17,0.04), 0 2px 8px rgba(23,21,17,0.04)',
        },
      }
    }
  };
</script>

<script defer src="https://unpkg.com/alpinejs@3.13.5/dist/cdn.min.js"></script>

<link rel="stylesheet" href="/admin_advanced/_assets/css/admin.css?v=<?= $assetVer ?>">
<?= $extraHead ?>
</head>
<body class="bg-sand-200 text-ink-900 font-sans antialiased min-h-screen <?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') ?>">

<div class="min-h-screen p-3 md:p-6 lg:p-8">
  <div class="mx-auto w-full max-w-[1760px] flex gap-4 md:gap-8">

    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="flex-1 min-w-0 bg-sand-50 rounded-4xl shadow-card p-5 md:p-10 lg:p-12">
      <?php if (!$hideTopbar) include __DIR__ . '/topbar.php'; ?>
      <main class="mt-8 md:mt-10">
