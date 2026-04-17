<?php

declare(strict_types=1);

namespace Seo\Entity;

class SeoArticle extends AbstractEntity {

    public const SEO_ARTICLE_TABLE = 'seo_articles';

    public const STATUS_DRAFT       = 'draft';
    public const STATUS_REVIEW      = 'review';
    public const STATUS_PUBLISHED   = 'published';
    public const STATUS_UNPUBLISHED = 'unpublished';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_REVIEW,
        self::STATUS_PUBLISHED,
        self::STATUS_UNPUBLISHED,
    ];

    protected ?int $profileId = null;
    protected ?int $catalogId = null;
    protected ?int $templateId = null;
    protected string $title = '';
    protected string $slug = '';
    protected ?string $keywords = null;
    protected ?string $metaTitle = null;
    protected ?string $metaDescription = null;
    protected ?string $article_plan = null;
    protected ?string $metaKeywords = null;
    protected string $status = self::STATUS_DRAFT;
    protected bool $isActive = true;
    protected ?string $publishedAt = null;
    protected ?string $publishedPath = null;
    protected ?string $publishedUrl = null;
    protected bool $tgExport = false;
    protected ?string $gptModel = 'gpt-4o';
    protected ?array $generationLog = null;
    protected int $version = 1;
    protected ?string $createdBy = null;

    protected array $blocks = [];
    protected array $images = [];
    protected array $links = [];

    protected ?SeoCatalog $catalog = null;
    protected ?SeoTemplate $template = null;

    protected function hydrate(array $data): void {
        if (array_key_exists('profile_id', $data)) {
            $this->profileId = $this->toNullableInt($data['profile_id']);
        }
        if (array_key_exists('catalog_id', $data)) {
            $this->catalogId = $this->toNullableInt($data['catalog_id']);
        }
        if (array_key_exists('template_id', $data)) {
            $this->templateId = $this->toNullableInt($data['template_id']);
        }
        if (array_key_exists('title', $data)) {
            $this->title = (string)$data['title'];
        }
        if (array_key_exists('slug', $data)) {
            $this->slug = (string)$data['slug'];
        }
        if (array_key_exists('keywords', $data)) {
            $this->keywords = $this->toNullableString($data['keywords']);
        }
        if (array_key_exists('meta_title', $data)) {
            $this->metaTitle = $this->toNullableString($data['meta_title']);
        }
        if (array_key_exists('article_plan', $data)) {
            $this->article_plan = $this->toNullableString($data['article_plan']);
        }
        if (array_key_exists('meta_description', $data)) {
            $this->metaDescription = $this->toNullableString($data['meta_description']);
        }
        if (array_key_exists('meta_keywords', $data)) {
            $this->metaKeywords = $this->toNullableString($data['meta_keywords']);
        }
        if (array_key_exists('status', $data)) {
            $this->status = (string)$data['status'];
        }
        if (array_key_exists('is_active', $data)) {
            $this->isActive = $this->toBool($data['is_active']);
        }
        if (array_key_exists('published_at', $data)) {
            $this->publishedAt = $this->toNullableString($data['published_at']);
        }
        if (array_key_exists('published_path', $data)) {
            $this->publishedPath = $this->toNullableString($data['published_path']);
        }
        if (array_key_exists('published_url', $data)) {
            $this->publishedUrl = $this->toNullableString($data['published_url']);
        }
        if (array_key_exists('tg_export', $data)) {
            $this->tgExport = $this->toBool($data['tg_export']);
        }
        if (array_key_exists('gpt_model', $data)) {
            $this->gptModel = $this->toNullableString($data['gpt_model']);
        }
        if (array_key_exists('generation_log', $data)) {
            $this->generationLog = $this->decodeJson($data['generation_log']);
        }
        if (array_key_exists('version', $data)) {
            $this->version = (int)$data['version'];
        }
        if (array_key_exists('created_by', $data)) {
            $this->createdBy = $this->toNullableString($data['created_by']);
        }
    }


    public function toArray(): array {
        return [
            'profile_id'       => $this->profileId,
            'catalog_id'       => $this->catalogId,
            'template_id'      => $this->templateId,
            'title'            => $this->title,
            'slug'             => $this->slug,
            'keywords'         => $this->keywords,
            'meta_title'       => $this->metaTitle,
            'article_plan'     => $this->article_plan,
            'meta_description'  => $this->metaDescription,
            'meta_keywords'    => $this->metaKeywords,
            'status'           => $this->status,
            'is_active'        => (int)$this->isActive,
            'published_at'     => $this->publishedAt,
            'published_path'   => $this->publishedPath,
            'published_url'    => $this->publishedUrl,
            'tg_export'        => (int)$this->tgExport,
            'gpt_model'        => $this->gptModel,
            'generation_log'   => $this->encodeJson($this->generationLog),
            'version'          => $this->version,
            'created_by'       => $this->createdBy,
        ];
    }

    public function toFullArray(): array {
        $arr = parent::toFullArray();
        if (!empty($this->blocks)) {
            $arr['blocks'] = array_map(fn(SeoArticleBlock $b) => $b->toFullArray(), $this->blocks);
        }
        if ($this->catalog !== null) {
            $arr['catalog'] = $this->catalog->toFullArray();
        }
        if ($this->template !== null) {
            $arr['template_name'] = $this->template->getName();
        }
        return $arr;
    }

    public function isDraft(): bool {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPublished(): bool {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function canPublish(): bool {
        return $this->isActive
            && in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REVIEW, self::STATUS_UNPUBLISHED], true)
            && !empty($this->blocks);
    }

    public function incrementVersion(): self {
        $this->version++;
        return $this;
    }

    public function getKeywordsArray(): array {
        if ($this->keywords === null || $this->keywords === '') {
            return [];
        }
        return array_map('trim', explode(',', $this->keywords));
    }

    public static function isValidStatus(string $status): bool {
        return in_array($status, self::STATUSES, true);
    }


    public function getProfileId(): ?int { return $this->profileId; }
    public function setProfileId(?int $profileId): self { $this->profileId = $profileId; return $this; }

    public function getCatalogId(): ?int {
        return $this->catalogId;
    }

    public function setCatalogId(?int $catalogId): self {
        $this->catalogId = $catalogId;
        return $this;
    }

    public function getTemplateId(): ?int {
        return $this->templateId;
    }

    public function setTemplateId(?int $templateId): self {
        $this->templateId = $templateId;
        return $this;
    }

    public function getTitle(): string {
        return $this->title;
    }

    public function setTitle(string $title): self {
        $this->title = $title;
        return $this;
    }

    public function getSlug(): string {
        return $this->slug;
    }

    public function setSlug(string $slug): self {
        $this->slug = $slug;
        return $this;
    }

    public function getKeywords(): ?string {
        return $this->keywords;
    }

    public function setKeywords(?string $keywords): self {
        $this->keywords = $keywords;
        return $this;
    }

    public function getMetaTitle(): ?string {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): self {
        $this->metaTitle = $metaTitle;
        return $this;
    }

    public function getArticlePlan(): ?string {
        return $this->article_plan;
    }

    public function setArticlePlan(?string $article_plan): self {
        $this->article_plan = $article_plan;
        return $this;
    }

    public function getMetaDescription(): ?string {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): self {
        $this->metaDescription = $metaDescription;
        return $this;
    }

    public function getMetaKeywords(): ?string {
        return $this->metaKeywords;
    }

    public function setMetaKeywords(?string $metaKeywords): self {
        $this->metaKeywords = $metaKeywords;
        return $this;
    }

    public function getStatus(): string {
        return $this->status;
    }

    public function setStatus(string $status): self {
        $this->status = $status;
        return $this;
    }

    public function isActive(): bool {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self {
        $this->isActive = $isActive;
        return $this;
    }

    public function getPublishedAt(): ?string {
        return $this->publishedAt;
    }

    public function setPublishedAt(?string $publishedAt): self {
        $this->publishedAt = $publishedAt;
        return $this;
    }

    public function getPublishedPath(): ?string {
        return $this->publishedPath;
    }

    public function setPublishedPath(?string $publishedPath): self {
        $this->publishedPath = $publishedPath;
        return $this;
    }

    public function getPublishedUrl(): ?string {
        return $this->publishedUrl;
    }

    public function setPublishedUrl(?string $publishedUrl): self {
        $this->publishedUrl = $publishedUrl;
        return $this;
    }

    public function getGptModel(): ?string {
        return $this->gptModel;
    }

    public function setGptModel(?string $gptModel): self {
        $this->gptModel = $gptModel;
        return $this;
    }

    public function getGenerationLog(): ?array {
        return $this->generationLog;
    }

    public function setGenerationLog(?array $generationLog): self {
        $this->generationLog = $generationLog;
        return $this;
    }

    public function getVersion(): int {
        return $this->version;
    }

    public function setVersion(int $version): self {
        $this->version = $version;
        return $this;
    }

    public function isTgExport(): bool { return $this->tgExport; }
    public function setTgExport(bool $v): self { $this->tgExport = $v; return $this; }

    public function getCreatedBy(): ?string {
        return $this->createdBy;
    }

    public function setCreatedBy(?string $createdBy): self {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getBlocks(): array {
        return $this->blocks;
    }

    public function setBlocks(array $blocks): self {
        $this->blocks = $blocks;
        return $this;
    }

    public function getBlocksSorted(): array {
        $blocks = $this->blocks;
        usort($blocks, static function (SeoArticleBlock $a, SeoArticleBlock $b) {
            return $a->getSortOrder() <=> $b->getSortOrder();
        });
        return $blocks;
    }

    public function getImages(): array {
        return $this->images;
    }

    public function setImages(array $images): self {
        $this->images = $images;
        return $this;
    }

    public function getLinks(): array {
        return $this->links;
    }

    public function setLinks(array $links): self {
        $this->links = $links;
        return $this;
    }

    public function getCatalog(): ?SeoCatalog {
        return $this->catalog;
    }

    public function setCatalog(?SeoCatalog $catalog): self {
        $this->catalog = $catalog;
        return $this;
    }

    public function getTemplate(): ?SeoTemplate {
        return $this->template;
    }

    public function setTemplate(?SeoTemplate $template): self {
        $this->template = $template;
        return $this;
    }
}
