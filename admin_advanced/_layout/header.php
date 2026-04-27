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
$assetVer  = '1';
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

<style type="text/tailwindcss">
  @layer components {
    .card        { @apply bg-sand-50 rounded-3xl shadow-card p-5; }
    .card-dark   { @apply bg-ink-900 text-sand-50 rounded-3xl shadow-card p-5; }
    .card-tinted { @apply bg-sand-100 rounded-3xl p-5; }

    .btn         { @apply inline-flex items-center justify-center gap-2 h-10 px-4 rounded-full text-sm font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed; }
    .btn-primary { @apply btn bg-ink-900 text-sand-50 hover:bg-ink-700; }
    .btn-soft    { @apply btn bg-sand-100 text-ink-900 hover:bg-sand-200; }
    .btn-ghost   { @apply btn text-ink-700 hover:bg-sand-100; }
    .btn-accent  { @apply btn bg-sun-400 text-ink-900 hover:bg-sun-300; }
    .btn-danger  { @apply btn bg-ember-400 text-white hover:bg-ember-500; }
    .btn-icon    { @apply inline-flex items-center justify-center w-10 h-10 rounded-full bg-sand-100 hover:bg-sand-200 text-ink-700; }

    .input       { @apply h-10 w-full px-4 rounded-full bg-sand-100 border border-transparent text-sm text-ink-900 placeholder:text-ink-300 focus:bg-sand-50 focus:border-sand-300 outline-none transition; }
    .textarea    { @apply w-full px-4 py-3 rounded-2xl bg-sand-100 border border-transparent text-sm text-ink-900 placeholder:text-ink-300 focus:bg-sand-50 focus:border-sand-300 outline-none transition; }
    .select      { @apply input pr-10 select-arrow; }

    .label       { @apply block text-xs font-semibold uppercase tracking-wide text-ink-500 mb-1.5; }

    .badge       { @apply inline-flex items-center gap-1 px-2.5 h-6 rounded-full text-xs font-semibold; }
    .badge-soft  { @apply badge bg-sand-100 text-ink-700; }
    .badge-ok    { @apply badge bg-emerald-100 text-emerald-800; }
    .badge-warn  { @apply badge bg-amber-100 text-amber-800; }
    .badge-err   { @apply badge bg-red-100 text-red-800; }
    .badge-sun   { @apply badge bg-sun-400/30 text-ink-900; }

    .tabs        { @apply flex items-center gap-1 p-1 bg-sand-100 rounded-full w-max; }
    .tab         { @apply h-9 px-4 rounded-full text-sm font-semibold text-ink-500 hover:text-ink-900 transition cursor-pointer; }
    .tab-active  { @apply bg-sand-50 text-ink-900 shadow-rail; }

    .tbl                     { @apply w-full text-sm; }
    .tbl thead th            { @apply text-left text-xs font-semibold uppercase tracking-wide text-ink-500 px-3 py-2; }
    .tbl tbody td            { @apply px-3 py-2.5 border-t border-sand-200 text-ink-900; }
    .tbl tbody tr:hover td   { @apply bg-sand-100; }

    .toast       { @apply pointer-events-auto bg-ink-900 text-sand-50 px-4 py-2.5 rounded-2xl shadow-card text-sm font-medium max-w-sm; }
    .toast-err   { @apply bg-ember-500 text-white; }
    .toast-ok    { @apply bg-emerald-600 text-white; }

    .modal-backdrop { @apply fixed inset-0 z-[900] bg-ink-900/40 backdrop-blur-sm flex items-center justify-center p-4; }
    .modal-card     { @apply bg-sand-50 rounded-3xl shadow-card w-full max-w-2xl max-h-[90vh] overflow-auto; }

    .progress-bar       { @apply h-2 w-full rounded-full bg-sand-200 overflow-hidden; }
    .progress-bar > span{ @apply block h-full bg-ink-900 transition-all; }

    .divider { @apply h-px bg-sand-200 my-4; }
  }
</style>
<?= $extraHead ?>
</head>
<body class="bg-sand-200 text-ink-900 font-sans antialiased min-h-screen <?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') ?>">

<div class="min-h-screen p-3 md:p-6 lg:p-8">
  <div class="mx-auto w-full max-w-[1760px] flex gap-4 md:gap-8">

    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="flex-1 min-w-0 bg-sand-50 rounded-4xl shadow-card p-5 md:p-10 lg:p-12">
      <?php if (!$hideTopbar) include __DIR__ . '/topbar.php'; ?>
      <main class="mt-8 md:mt-10">
