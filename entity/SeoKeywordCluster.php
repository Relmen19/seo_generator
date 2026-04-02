<?php

declare(strict_types=1);

namespace Seo\Entity;

class SeoKeywordCluster extends AbstractEntity {

    public const TABLE = 'seo_keyword_clusters';

    public const STATUS_NEW             = 'new';
    public const STATUS_APPROVED        = 'approved';
    public const STATUS_ARTICLE_CREATED = 'article_created';
    public const STATUS_REJECTED        = 'rejected';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_APPROVED,
        self::STATUS_ARTICLE_CREATED,
        self::STATUS_REJECTED,
    ];

    public const INTENT_INFO          = 'info';
    public const INTENT_TRANSACTIONAL = 'transactional';
    public const INTENT_NAVIGATIONAL  = 'navigational';
    public const INTENT_COMPARISON    = 'comparison';

    public const INTENTS = [
        self::INTENT_INFO,
        self::INTENT_TRANSACTIONAL,
        self::INTENT_NAVIGATIONAL,
        self::INTENT_COMPARISON,
    ];

    protected int $jobId = 0;
    protected ?int $parentId = null;
    protected string $name = '';
    protected string $slug = '';
    protected ?string $intent = null;
    protected ?string $summary = null;
    protected ?string $articleAngle = null;
    protected ?int $templateId = null;
    protected int $totalVolume = 0;
    protected int $keywordCount = 0;
    protected int $priority = 0;
    protected ?int $articleId = null;
    protected string $status = self::STATUS_NEW;

    protected ?string $templateName = null;
    protected ?string $articleTitle = null;

    protected array $keywords = [];
    protected array $children = [];

    protected function hydrate(array $data): void {
        if (array_key_exists('job_id', $data)) {
            $this->jobId = (int)$data['job_id'];
        }
        if (array_key_exists('parent_id', $data)) {
            $this->parentId = $this->toNullableInt($data['parent_id']);
        }
        if (array_key_exists('name', $data)) {
            $this->name = (string)$data['name'];
        }
        if (array_key_exists('slug', $data)) {
            $this->slug = (string)$data['slug'];
        }
        if (array_key_exists('intent', $data)) {
            $this->intent = $this->toNullableString($data['intent']);
        }
        if (array_key_exists('summary', $data)) {
            $this->summary = $this->toNullableString($data['summary']);
        }
        if (array_key_exists('article_angle', $data)) {
            $this->articleAngle = $this->toNullableString($data['article_angle']);
        }
        if (array_key_exists('template_id', $data)) {
            $this->templateId = $this->toNullableInt($data['template_id']);
        }
        if (array_key_exists('total_volume', $data)) {
            $this->totalVolume = (int)$data['total_volume'];
        }
        if (array_key_exists('keyword_count', $data)) {
            $this->keywordCount = (int)$data['keyword_count'];
        }
        if (array_key_exists('priority', $data)) {
            $this->priority = (int)$data['priority'];
        }
        if (array_key_exists('article_id', $data)) {
            $this->articleId = $this->toNullableInt($data['article_id']);
        }
        if (array_key_exists('status', $data)) {
            $this->status = (string)$data['status'];
        }
        // Computed
        if (array_key_exists('template_name', $data)) {
            $this->templateName = $this->toNullableString($data['template_name']);
        }
        if (array_key_exists('article_title', $data)) {
            $this->articleTitle = $this->toNullableString($data['article_title']);
        }
    }

    public function toArray(): array {
        return [
            'job_id'        => $this->jobId,
            'parent_id'     => $this->parentId,
            'name'          => $this->name,
            'slug'          => $this->slug,
            'intent'        => $this->intent,
            'summary'       => $this->summary,
            'article_angle' => $this->articleAngle,
            'template_id'   => $this->templateId,
            'total_volume'  => $this->totalVolume,
            'keyword_count' => $this->keywordCount,
            'priority'      => $this->priority,
            'article_id'    => $this->articleId,
            'status'        => $this->status,
        ];
    }

    public function toFullArray(): array {
        $arr = parent::toFullArray();
        if ($this->templateName !== null) {
            $arr['template_name'] = $this->templateName;
        }
        if ($this->articleTitle !== null) {
            $arr['article_title'] = $this->articleTitle;
        }
        if (!empty($this->keywords)) {
            $arr['keywords'] = array_map(
                fn(SeoRawKeyword $kw) => $kw->toFullArray(),
                $this->keywords
            );
        }
        if (!empty($this->children)) {
            $arr['children'] = array_map(
                fn(SeoKeywordCluster $c) => $c->toFullArray(),
                $this->children
            );
        }
        return $arr;
    }

    public static function isValidStatus(string $status): bool {
        return in_array($status, self::STATUSES, true);
    }

    public static function isValidIntent(string $intent): bool {
        return in_array($intent, self::INTENTS, true);
    }

    public function isNew(): bool {
        return $this->status === self::STATUS_NEW;
    }

    public function isApproved(): bool {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool {
        return $this->status === self::STATUS_REJECTED;
    }

    public function hasArticle(): bool {
        return $this->articleId !== null;
    }

    public function canCreateArticle(): bool {
        return $this->isApproved() && !$this->hasArticle();
    }


    public function getJobId(): int {
        return $this->jobId;
    }

    public function setJobId(int $jobId): self {
        $this->jobId = $jobId;
        return $this;
    }

    public function getParentId(): ?int {
        return $this->parentId;
    }

    public function setParentId(?int $parentId): self {
        $this->parentId = $parentId;
        return $this;
    }

    public function getName(): string {
        return $this->name;
    }

    public function setName(string $name): self {
        $this->name = $name;
        return $this;
    }

    public function getSlug(): string {
        return $this->slug;
    }

    public function setSlug(string $slug): self {
        $this->slug = $slug;
        return $this;
    }

    public function getIntent(): ?string {
        return $this->intent;
    }

    public function setIntent(?string $intent): self {
        $this->intent = $intent;
        return $this;
    }

    public function getSummary(): ?string {
        return $this->summary;
    }

    public function setSummary(?string $summary): self {
        $this->summary = $summary;
        return $this;
    }

    public function getArticleAngle(): ?string {
        return $this->articleAngle;
    }

    public function setArticleAngle(?string $articleAngle): self {
        $this->articleAngle = $articleAngle;
        return $this;
    }

    public function getTemplateId(): ?int {
        return $this->templateId;
    }

    public function setTemplateId(?int $templateId): self {
        $this->templateId = $templateId;
        return $this;
    }

    public function getTotalVolume(): int {
        return $this->totalVolume;
    }

    public function setTotalVolume(int $totalVolume): self {
        $this->totalVolume = $totalVolume;
        return $this;
    }

    public function getKeywordCount(): int {
        return $this->keywordCount;
    }

    public function setKeywordCount(int $keywordCount): self {
        $this->keywordCount = $keywordCount;
        return $this;
    }

    public function getPriority(): int {
        return $this->priority;
    }

    public function setPriority(int $priority): self {
        $this->priority = min(10, max(0, $priority));
        return $this;
    }

    public function getArticleId(): ?int {
        return $this->articleId;
    }

    public function setArticleId(?int $articleId): self {
        $this->articleId = $articleId;
        return $this;
    }

    public function getStatus(): string {
        return $this->status;
    }

    public function setStatus(string $status): self {
        $this->status = $status;
        return $this;
    }

    public function getTemplateName(): ?string {
        return $this->templateName;
    }

    public function getArticleTitle(): ?string {
        return $this->articleTitle;
    }

    public function getKeywords(): array {
        return $this->keywords;
    }

    public function setKeywords(array $keywords): self {
        $this->keywords = $keywords;
        return $this;
    }

    public function getChildren(): array {
        return $this->children;
    }

    public function setChildren(array $children): self {
        $this->children = $children;
        return $this;
    }
}