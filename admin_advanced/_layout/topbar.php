<?php
/**
 * Topbar: heading + optional search slot + actions slot.
 * Reads $pageHeading, $pageSubheading from caller. Optional $topbarRight HTML for custom right-side controls.
 */
declare(strict_types=1);

$topbarRight = $topbarRight ?? '';
?>
<header class="flex flex-col md:flex-row md:items-center gap-4 md:gap-6">
  <div class="flex-1 min-w-0">
    <?php if ($pageHeading): ?>
      <h1 class="text-3xl md:text-4xl font-bold tracking-tight"><?= htmlspecialchars($pageHeading, ENT_QUOTES, 'UTF-8') ?></h1>
    <?php endif; ?>
    <?php if ($pageSubheading): ?>
      <p class="text-ink-500 mt-1 text-sm md:text-base"><?= htmlspecialchars($pageSubheading, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
  </div>

  <div class="flex items-center gap-3">
    <?php if ($topbarRight): ?>
      <?= $topbarRight ?>
    <?php endif; ?>
  </div>
</header>
