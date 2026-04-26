<?php

declare(strict_types=1);

namespace Seo\Entity;

class SeoSiteProfile extends AbstractEntity {

    public const TABLE = 'seo_site_profiles';

    protected string $name = '';
    protected string $slug = '';
    protected ?string $domain = null;
    protected ?string $niche = null;
    protected ?string $description = null;
    protected string $language = 'ru';
    protected ?string $brandName = null;
    protected ?string $logoUrl = null;
    protected ?string $iconPath = null;
    protected ?string $baseUrl = null;
    protected ?string $gptPersona = null;
    protected ?string $gptRules = null;
    protected ?array $contentBrief = null;
    protected string $tone = 'professional';
    protected string $colorScheme = '#6366f1';
    protected string $theme = 'default';
    protected ?string $defaultThemeCode = null;
    protected string $researchStrategy = 'single';
    protected ?string $tgBotToken = null;
    protected ?string $tgChannelId = null;
    protected string $tgPostFormat = 'auto';
    protected ?array $tgRenderBlocks = null;
    protected ?string $tgChannelName = null;
    protected ?string $tgChannelAvatar = null;
    protected bool $isActive = true;
    protected ?array $brandPalette = null;
    protected ?string $brandIllustrationStyle = null;
    protected ?int $brandLogoImageId = null;

    protected function hydrate(array $data): void {
        if (array_key_exists('name', $data)) {
            $this->name = (string)$data['name'];
        }
        if (array_key_exists('slug', $data)) {
            $this->slug = (string)$data['slug'];
        }
        if (array_key_exists('domain', $data)) {
            $this->domain = $this->toNullableString($data['domain']);
        }
        if (array_key_exists('niche', $data)) {
            $this->niche = $this->toNullableString($data['niche']);
        }
        if (array_key_exists('description', $data)) {
            $this->description = $this->toNullableString($data['description']);
        }
        if (array_key_exists('language', $data)) {
            $this->language = (string)$data['language'];
        }
        if (array_key_exists('brand_name', $data)) {
            $this->brandName = $this->toNullableString($data['brand_name']);
        }
        if (array_key_exists('logo_url', $data)) {
            $this->logoUrl = $this->toNullableString($data['logo_url']);
        }
        if (array_key_exists('icon_path', $data)) {
            $this->iconPath = $this->toNullableString($data['icon_path']);
        }
        if (array_key_exists('base_url', $data)) {
            $this->baseUrl = $this->toNullableString($data['base_url']);
        }
        if (array_key_exists('gpt_persona', $data)) {
            $this->gptPersona = $this->toNullableString($data['gpt_persona']);
        }
        if (array_key_exists('gpt_rules', $data)) {
            $this->gptRules = $this->toNullableString($data['gpt_rules']);
        }
        if (array_key_exists('content_brief', $data)) {
            $this->contentBrief = is_array($data['content_brief'])
                ? $data['content_brief']
                : $this->decodeJson($data['content_brief']);
        }
        if (array_key_exists('tone', $data)) {
            $this->tone = (string)$data['tone'];
        }
        if (array_key_exists('color_scheme', $data)) {
            $this->colorScheme = (string)$data['color_scheme'];
        }
        if (array_key_exists('theme', $data)) {
            $this->theme = (string)$data['theme'];
        }
        if (array_key_exists('default_theme_code', $data)) {
            $this->defaultThemeCode = $this->toNullableString($data['default_theme_code']);
        }
        if (array_key_exists('research_strategy', $data)) {
            $v = (string)$data['research_strategy'];
            $this->researchStrategy = in_array($v, ['single','split','split_search'], true) ? $v : 'single';
        }
        if (array_key_exists('tg_bot_token', $data)) {
            $this->tgBotToken = $this->toNullableString($data['tg_bot_token']);
        }
        if (array_key_exists('tg_channel_id', $data)) {
            $this->tgChannelId = $this->toNullableString($data['tg_channel_id']);
        }
        if (array_key_exists('tg_post_format', $data)) {
            $this->tgPostFormat = (string)($data['tg_post_format'] ?: 'auto');
        }
        if (array_key_exists('tg_render_blocks', $data)) {
            $this->tgRenderBlocks = $this->decodeJson($data['tg_render_blocks']);
        }
        if (array_key_exists('tg_channel_name', $data)) {
            $this->tgChannelName = $this->toNullableString($data['tg_channel_name']);
        }
        if (array_key_exists('tg_channel_avatar', $data)) {
            $this->tgChannelAvatar = $this->toNullableString($data['tg_channel_avatar']);
        }
        if (array_key_exists('is_active', $data)) {
            $this->isActive = $this->toBool($data['is_active']);
        }
        if (array_key_exists('brand_palette', $data)) {
            $this->brandPalette = is_array($data['brand_palette'])
                ? $data['brand_palette']
                : $this->decodeJson($data['brand_palette']);
        }
        if (array_key_exists('brand_illustration_style', $data)) {
            $this->brandIllustrationStyle = $this->toNullableString($data['brand_illustration_style']);
        }
        if (array_key_exists('brand_logo_image_id', $data)) {
            $this->brandLogoImageId = $data['brand_logo_image_id'] !== null && $data['brand_logo_image_id'] !== ''
                ? (int)$data['brand_logo_image_id'] : null;
        }
    }

    public function toArray(): array {
        return [
            'name'          => $this->name,
            'slug'          => $this->slug,
            'domain'        => $this->domain,
            'niche'         => $this->niche,
            'description'   => $this->description,
            'language'      => $this->language,
            'brand_name'    => $this->brandName,
            'logo_url'      => $this->logoUrl,
            'icon_path'     => $this->iconPath,
            'base_url'      => $this->baseUrl,
            'gpt_persona'   => $this->gptPersona,
            'gpt_rules'     => $this->gptRules,
            'content_brief' => $this->encodeJson($this->contentBrief),
            'tone'          => $this->tone,
            'color_scheme'  => $this->colorScheme,
            'theme'             => $this->theme,
            'default_theme_code' => $this->defaultThemeCode,
            'research_strategy' => $this->researchStrategy,
            'tg_bot_token'      => $this->tgBotToken,
            'tg_channel_id'     => $this->tgChannelId,
            'tg_post_format'    => $this->tgPostFormat,
            'tg_render_blocks'  => $this->encodeJson($this->tgRenderBlocks),
            'tg_channel_name'   => $this->tgChannelName,
            'tg_channel_avatar' => $this->tgChannelAvatar,
            'is_active'         => (int)$this->isActive,
            'brand_palette'             => $this->encodeJson($this->brandPalette),
            'brand_illustration_style'  => $this->brandIllustrationStyle,
            'brand_logo_image_id'       => $this->brandLogoImageId,
        ];
    }

    public function getBrandPalette(): ?array { return $this->brandPalette; }
    public function setBrandPalette(?array $v): self { $this->brandPalette = $v; return $this; }

    public function getBrandIllustrationStyle(): ?string { return $this->brandIllustrationStyle; }
    public function setBrandIllustrationStyle(?string $v): self { $this->brandIllustrationStyle = $v; return $this; }

    public function getBrandLogoImageId(): ?int { return $this->brandLogoImageId; }
    public function setBrandLogoImageId(?int $v): self { $this->brandLogoImageId = $v; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }

    public function getDomain(): ?string { return $this->domain; }
    public function setDomain(?string $domain): self { $this->domain = $domain; return $this; }

    public function getNiche(): ?string { return $this->niche; }
    public function setNiche(?string $niche): self { $this->niche = $niche; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getLanguage(): string { return $this->language; }
    public function setLanguage(string $language): self { $this->language = $language; return $this; }

    public function getBrandName(): ?string { return $this->brandName; }
    public function setBrandName(?string $brandName): self { $this->brandName = $brandName; return $this; }

    public function getLogoUrl(): ?string { return $this->logoUrl; }
    public function setLogoUrl(?string $logoUrl): self { $this->logoUrl = $logoUrl; return $this; }

    public function getIconPath(): ?string { return $this->iconPath; }
    public function setIconPath(?string $iconPath): self { $this->iconPath = $iconPath; return $this; }

    public function getBaseUrl(): ?string { return $this->baseUrl; }
    public function setBaseUrl(?string $baseUrl): self { $this->baseUrl = $baseUrl; return $this; }

    public function getGptPersona(): ?string { return $this->gptPersona; }
    public function setGptPersona(?string $gptPersona): self { $this->gptPersona = $gptPersona; return $this; }

    public function getGptRules(): ?string { return $this->gptRules; }
    public function setGptRules(?string $gptRules): self { $this->gptRules = $gptRules; return $this; }

    public function getContentBrief(): ?array { return $this->contentBrief; }
    public function setContentBrief(?array $brief): self { $this->contentBrief = $brief; return $this; }

    public function toFullArray(): array {
        $arr = parent::toFullArray();
        $arr['content_brief'] = $this->contentBrief;
        $arr['brand_palette'] = $this->brandPalette;
        return $arr;
    }

    public function getTone(): string { return $this->tone; }
    public function setTone(string $tone): self { $this->tone = $tone; return $this; }

    public function getColorScheme(): string { return $this->colorScheme; }
    public function setColorScheme(string $colorScheme): self { $this->colorScheme = $colorScheme; return $this; }

    public function getTheme(): string { return $this->theme; }
    public function setTheme(string $theme): self { $this->theme = $theme; return $this; }

    public function getDefaultThemeCode(): ?string { return $this->defaultThemeCode; }
    public function setDefaultThemeCode(?string $v): self { $this->defaultThemeCode = $v; return $this; }
    public function getResearchStrategy(): string { return $this->researchStrategy; }
    public function setResearchStrategy(string $v): self {
        $this->researchStrategy = in_array($v, ['single','split','split_search'], true) ? $v : 'single';
        return $this;
    }

    public function getTgBotToken(): ?string { return $this->tgBotToken; }
    public function setTgBotToken(?string $v): self { $this->tgBotToken = $v; return $this; }

    public function getTgChannelId(): ?string { return $this->tgChannelId; }
    public function setTgChannelId(?string $v): self { $this->tgChannelId = $v; return $this; }

    public function getTgPostFormat(): string { return $this->tgPostFormat; }
    public function setTgPostFormat(string $v): self { $this->tgPostFormat = $v; return $this; }

    public function getTgRenderBlocks(): ?array { return $this->tgRenderBlocks; }
    public function setTgRenderBlocks(?array $v): self { $this->tgRenderBlocks = $v; return $this; }

    public function getTgChannelName(): ?string { return $this->tgChannelName; }
    public function setTgChannelName(?string $v): self { $this->tgChannelName = $v; return $this; }

    public function getTgChannelAvatar(): ?string { return $this->tgChannelAvatar; }
    public function setTgChannelAvatar(?string $v): self { $this->tgChannelAvatar = $v; return $this; }

    public function hasTelegramConfig(): bool {
        return $this->tgBotToken !== null && $this->tgChannelId !== null;
    }

    public function getDefaultTgRenderBlocks(): array {
        return [
            'chart', 'gauges', 'before-after', 'comparison-table', 'timeline',
            'expert_panel', 'feature_grid', 'info_cards', 'radar_chart',
            'range_comparison', 'score_rings', 'spark_metrics', 'stacked_area',
            'stats_counter', 'verdict_card', 'warning_block',
        ];
    }

    public function getEffectiveTgRenderBlocks(): array {
        return $this->tgRenderBlocks ?? $this->getDefaultTgRenderBlocks();
    }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }
}
