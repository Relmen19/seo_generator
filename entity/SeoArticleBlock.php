<?php

declare(strict_types=1);

namespace Seo\Entity;

class SeoArticleBlock extends AbstractEntity {

    public const SEO_ART_BLOCK_TABLE = 'seo_article_blocks';

    protected int $articleId = 0;
    protected string $type = '';
    protected ?string $name = null;
    protected array $content = [];
    protected int $sortOrder = 0;
    protected bool $isVisible = true;
    protected ?string $gptPrompt = null;

    protected array $images = [];

    protected function hydrate(array $data): void {
        if (array_key_exists('article_id', $data)) {
            $this->articleId = (int)$data['article_id'];
        }
        if (array_key_exists('type', $data)) {
            $this->type = (string)$data['type'];
        }
        if (array_key_exists('name', $data)) {
            $this->name = $this->toNullableString($data['name']);
        }
        if (array_key_exists('content', $data)) {
            $this->content = $this->decodeJson($data['content']) ?? [];
        }
        if (array_key_exists('sort_order', $data)) {
            $this->sortOrder = (int)$data['sort_order'];
        }
        if (array_key_exists('is_visible', $data)) {
            $this->isVisible = $this->toBool($data['is_visible']);
        }
        if (array_key_exists('gpt_prompt', $data)) {
            $this->gptPrompt = $this->toNullableString($data['gpt_prompt']);
        }
    }

    public function toArray(): array {
        return [
            'article_id' => $this->articleId,
            'type'        => $this->type,
            'name'        => $this->name,
            'content'     => $this->encodeJson($this->content),
            'sort_order'  => $this->sortOrder,
            'is_visible'  => (int)$this->isVisible,
            'gpt_prompt'  => $this->gptPrompt,
        ];
    }

    public function getContentValue(string $key, $default = null) {
        return $this->content[$key] ?? $default;
    }

    public function setContentValue(string $key, $value): self {
        $this->content[$key] = $value;
        return $this;
    }

    public function getItems(): array {
        return $this->getContentValue('items', []);
    }


    public function getArticleId(): int {
        return $this->articleId;
    }

    public function setArticleId(int $articleId): self {
        $this->articleId = $articleId;
        return $this;
    }

    public function getType(): string {
        return $this->type;
    }

    public function setType(string $type): self {
        $this->type = $type;
        return $this;
    }

    public function getName(): ?string {
        return $this->name;
    }

    public function setName(?string $name): self {
        $this->name = $name;
        return $this;
    }

    public function getContent(): array {
        return $this->content;
    }

    public function setContent(array $content): self {
        $this->content = $content;
        return $this;
    }

    public function getSortOrder(): int {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    public function isVisible(): bool {
        return $this->isVisible;
    }

    public function setIsVisible(bool $isVisible): self {
        $this->isVisible = $isVisible;
        return $this;
    }

    public function getGptPrompt(): ?string {
        return $this->gptPrompt;
    }

    public function setGptPrompt(?string $gptPrompt): self {
        $this->gptPrompt = $gptPrompt;
        return $this;
    }

    public function getImages(): array {
        return $this->images;
    }

    public function setImages(array $images): self {
        $this->images = $images;
        return $this;
    }
}
