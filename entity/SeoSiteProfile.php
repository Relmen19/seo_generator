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
    protected string $tone = 'professional';
    protected string $colorScheme = '#6366f1';
    protected string $theme = 'default';
    protected bool $isActive = true;

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
        if (array_key_exists('tone', $data)) {
            $this->tone = (string)$data['tone'];
        }
        if (array_key_exists('color_scheme', $data)) {
            $this->colorScheme = (string)$data['color_scheme'];
        }
        if (array_key_exists('theme', $data)) {
            $this->theme = (string)$data['theme'];
        }
        if (array_key_exists('is_active', $data)) {
            $this->isActive = $this->toBool($data['is_active']);
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
            'tone'          => $this->tone,
            'color_scheme'  => $this->colorScheme,
            'theme'         => $this->theme,
            'is_active'     => (int)$this->isActive,
        ];
    }

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

    public function getTone(): string { return $this->tone; }
    public function setTone(string $tone): self { $this->tone = $tone; return $this; }

    public function getColorScheme(): string { return $this->colorScheme; }
    public function setColorScheme(string $colorScheme): self { $this->colorScheme = $colorScheme; return $this; }

    public function getTheme(): string { return $this->theme; }
    public function setTheme(string $theme): self { $this->theme = $theme; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }
}
