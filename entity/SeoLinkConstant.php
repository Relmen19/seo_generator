<?php

declare(strict_types=1);

namespace Seo\Entity;


class SeoLinkConstant extends AbstractEntity {

    public const SEO_LINKS_TABLE = 'seo_link_constants';

    protected ?int $articleId = null;
    protected string $key = '';
    protected string $url = '';
    protected bool $isActive = true;
    protected ?string $label = null;
    protected string $target = '_blank';
    protected bool $nofollow = false;
    protected ?string $description = null;

    protected function hydrate(array $data): void {
        if (array_key_exists('article_id', $data)) {
            $this->articleId = $this->toNullableInt($data['article_id']);
        }
        if (array_key_exists('key', $data)) {
            $this->key = (string)$data['key'];
        }
        if (array_key_exists('url', $data)) {
            $this->url = (string)$data['url'];
        }
        if (array_key_exists('label', $data)) {
            $this->label = $this->toNullableString($data['label']);
        }
        if (array_key_exists('target', $data)) {
            $this->target = (string)$data['target'];
        }
        if (array_key_exists('nofollow', $data)) {
            $this->nofollow = $this->toBool($data['nofollow']);
        }
        if (array_key_exists('description', $data)) {
            $this->description = $this->toNullableString($data['description']);
        }
        if (array_key_exists('is_active', $data)) {
            $this->isActive = $this->toBool($data['is_active']);
        }
    }

    public function toArray(): array {
        return [
            'article_id'  => $this->articleId,
            'key'         => $this->key,
            'url'         => $this->url,
            'label'       => $this->label,
            'target'      => $this->target,
            'nofollow'    => (int)$this->nofollow,
            'description' => $this->description,
            'is_active'   => (int)$this->isActive,
        ];
    }

    public function isGlobal(): bool {
        return $this->articleId === null;
    }

    public function getPlaceholder(): string {
        return '{{link:' . $this->key . '}}';
    }

    public function getRelAttribute(): string {
        $parts = [];
        if ($this->nofollow) {
            $parts[] = 'nofollow';
        }
        if ($this->target === '_blank') {
            $parts[] = 'noopener';
            $parts[] = 'noreferrer';
        }
        return implode(' ', $parts);
    }

    public function toHtmlTag(?string $text = null): string {
        $text = $text ?? $this->label ?? $this->url;
        $rel = $this->getRelAttribute();

        return sprintf(
            '<a href="%s" target="%s"%s>%s</a>',
            htmlspecialchars($this->url, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($this->target, ENT_QUOTES, 'UTF-8'),
            $rel ? ' rel="' . htmlspecialchars($rel, ENT_QUOTES, 'UTF-8') . '"' : '',
            htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
        );
    }

    public function getArticleId(): ?int {
        return $this->articleId;
    }

    public function setArticleId(?int $articleId): self {
        $this->articleId = $articleId;
        return $this;
    }

    public function getKey(): string {
        return $this->key;
    }

    public function setKey(string $key): self {
        $this->key = $key;
        return $this;
    }

    public function getUrl(): string {
        return $this->url;
    }

    public function setUrl(string $url): self {
        $this->url = $url;
        return $this;
    }

    public function getLabel(): ?string {
        return $this->label;
    }

    public function setLabel(?string $label): self {
        $this->label = $label;
        return $this;
    }

    public function getTarget(): string {
        return $this->target;
    }

    public function setTarget(string $target): self {
        $this->target = $target;
        return $this;
    }

    public function isNofollow(): bool {
        return $this->nofollow;
    }

    public function setNofollow(bool $nofollow): self {
        $this->nofollow = $nofollow;
        return $this;
    }

    public function getDescription(): ?string {
        return $this->description;
    }

    public function setDescription(?string $description): self {
        $this->description = $description;
        return $this;
    }

    public function getIsActive(): ?bool {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self {
        $this->isActive = $isActive;
        return $this;
    }
}
