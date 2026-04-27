<?php
/**
 * Topbar: heading + optional search slot + actions slot.
 * Reads $pageHeading, $pageSubheading from caller. Optional $topbarRight HTML for custom right-side controls.
 */
declare(strict_types=1);

$topbarRight = $topbarRight ?? '';
?>
<header x-data x-show="!($store.layout && $store.layout.hideTopbar)" x-cloak
        class="flex flex-col md:flex-row md:items-center gap-4 md:gap-6">
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

    <div x-data="profileChip()" x-init="init()" x-show="profile" x-cloak
         class="flex items-center gap-2 bg-sand-100 rounded-full pl-1 pr-4 py-1 shadow-rail">
      <template x-if="profile && profile.has_icon">
        <img :src="iconUrl()" alt="" class="w-8 h-8 rounded-full object-cover bg-sand-50">
      </template>
      <template x-if="profile && !profile.has_icon">
        <span class="w-8 h-8 rounded-full bg-ink-900 text-sand-50 grid place-items-center text-xs font-semibold"
              x-text="initials()"></span>
      </template>
      <span class="text-sm font-semibold text-ink-900" x-text="profile ? profile.name : ''"></span>
      <a href="/admin_advanced/seo_profile_page.php" title="Сменить профиль"
         class="text-ink-500 hover:text-ink-900 ml-1">
        <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor"><path d="M3 6l5 5 5-5" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </a>
    </div>
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
