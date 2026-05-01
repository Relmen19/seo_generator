<?php
/**
 * Topbar: heading + optional search slot + actions slot.
 * Reads $pageHeading, $pageSubheading from caller. Optional $topbarRight HTML for custom right-side controls.
 */
declare(strict_types=1);

$topbarRight = $topbarRight ?? '';
?>
<header id="seo-topbar" class="flex items-center gap-4">
  <div class="flex-1 min-w-0">
    <?php if ($pageHeading): ?>
      <h1 class="text-2xl md:text-3xl font-bold tracking-tight truncate"><?= htmlspecialchars($pageHeading, ENT_QUOTES, 'UTF-8') ?></h1>
    <?php endif; ?>
    <?php if ($pageSubheading): ?>
      <p class="text-ink-500 mt-0.5 text-xs md:text-sm truncate"><?= htmlspecialchars($pageSubheading, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
  </div>

  <div class="flex items-center gap-2 shrink-0">
    <?php if ($topbarRight): ?>
      <?= $topbarRight ?>
    <?php endif; ?>

    <a x-data="profileChip()" x-init="init()" x-show="profile" x-cloak
       href="/admin_advanced/seo_profile_page.php"
       title="Сменить профиль"
       class="block w-9 h-9 rounded-full overflow-hidden bg-sand-100 ring-1 ring-sand-200 hover:ring-ink-300 transition">
      <template x-if="profile && profile.has_icon">
        <img :src="iconUrl()" :alt="profile.name" class="w-full h-full object-cover">
      </template>
      <template x-if="profile && !profile.has_icon">
        <span class="w-full h-full grid place-items-center bg-ink-900 text-sand-50 text-xs font-semibold"
              x-text="initials()"></span>
      </template>
    </a>
  </div>
</header>

<script>
function profileChip() {
  return {
    profile: null,
    async init() {
      await this.load();
      window.addEventListener('seo-profile-changed', () => this.load());
      window.addEventListener('storage', e => { if (e.key === 'seo_profile_id') this.load(); });
    },
    async load() {
      const id = SEO.profile.id;
      if (!id) { this.profile = null; return; }
      try { this.profile = await SEO.api('profiles/' + id, { silent: true }); }
      catch (_) { this.profile = null; }
    },
    iconUrl() { return SEO.profile.iconUrl(this.profile); },
    initials() { return (this.profile && this.profile.name ? this.profile.name : '?').slice(0, 2).toUpperCase(); },
  };
}
</script>
