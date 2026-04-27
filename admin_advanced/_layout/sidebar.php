<?php
/**
 * Left navigation rail. Reads $activeNav from caller scope.
 */
declare(strict_types=1);

$nav = [
    [
        'id'    => 'articles',
        'href'  => '/admin_advanced/seo_page.php',
        'label' => 'Статьи',
        'icon'  => '<path d="M4 5h12M4 9h12M4 13h8" stroke-width="1.8" stroke-linecap="round"/>',
    ],
    [
        'id'    => 'clustering',
        'href'  => '/admin_advanced/seo_clustering_page.php',
        'label' => 'Кластеризация',
        'icon'  => '<circle cx="6" cy="6" r="2" stroke-width="1.8"/><circle cx="14" cy="6" r="2" stroke-width="1.8"/><circle cx="6" cy="14" r="2" stroke-width="1.8"/><circle cx="14" cy="14" r="2" stroke-width="1.8"/><path d="M8 6h4M6 8v4M14 8v4M8 14h4" stroke-width="1.8" stroke-linecap="round"/>',
    ],
    [
        'id'    => 'profile',
        'href'  => '/admin_advanced/seo_profile_page.php',
        'label' => 'Профили',
        'icon'  => '<path d="M10 10a3 3 0 100-6 3 3 0 000 6zM4 17c0-3 2.7-5 6-5s6 2 6 5" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
    ],
    [
        'id'    => 'themes',
        'href'  => '/admin_advanced/seo_themes_page.php',
        'label' => 'Темы',
        'icon'  => '<path d="M10 3a7 7 0 100 14 2 2 0 002-2v-1a2 2 0 012-2h1a2 2 0 002-2 7 7 0 00-7-7z" stroke-width="1.8" stroke-linejoin="round"/><circle cx="6.5" cy="9" r="1"/><circle cx="13.5" cy="6" r="1"/>',
    ],
    [
        'id'    => 'cost',
        'href'  => '/admin_advanced/cost_report.php',
        'label' => 'Расходы',
        'icon'  => '<path d="M10 4v12M6 7h6a2 2 0 110 4H8a2 2 0 100 4h6" stroke-width="1.8" stroke-linecap="round"/>',
    ],
];
?>
<aside class="shrink-0 flex flex-col items-center gap-6 py-6 px-2 md:px-3">

  <a href="/admin_advanced/seo_page.php" class="w-12 h-12 rounded-2xl bg-ink-900 text-sand-50 grid place-items-center font-bold text-lg shadow-rail" title="Главная">
    SE
  </a>

  <nav class="bg-sand-50 rounded-full shadow-rail py-3 px-2 flex flex-col gap-2">
    <?php foreach ($nav as $item): $isActive = ($item['id'] === $activeNav); ?>
      <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
         title="<?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>"
         class="w-11 h-11 rounded-full grid place-items-center transition <?= $isActive ? 'bg-ink-900 text-sand-50' : 'text-ink-700 hover:bg-sand-100' ?>">
        <svg viewBox="0 0 20 20" width="20" height="20" fill="none" stroke="currentColor"><?= $item['icon'] ?></svg>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="mt-auto flex flex-col items-center gap-3">
    <a href="/logout.php" title="Выход"
       class="w-11 h-11 rounded-full bg-sand-50 shadow-rail grid place-items-center text-ink-700 hover:bg-sand-100">
      <svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor">
        <path d="M9 4H5a1 1 0 00-1 1v10a1 1 0 001 1h4M13 7l3 3-3 3M16 10H8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </a>
    <div class="w-11 h-11 rounded-full bg-ink-900 text-sand-50 grid place-items-center text-sm font-semibold" title="<?= $adminUser ?>">
      <?= strtoupper(substr($adminUser, 0, 2)) ?>
    </div>
  </div>
</aside>
