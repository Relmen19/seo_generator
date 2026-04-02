<?php

declare(strict_types=1);

namespace Seo\Entity;

class SeoBlockType extends AbstractEntity {

    public const TABLE = 'seo_block_types';

    protected string $code = '';
    protected string $displayName = '';
    protected ?string $description = null;
    protected string $category = 'content';
    protected ?string $icon = null;
    protected ?array $jsonSchema = null;
    protected ?string $gptHint = null;
    protected bool $isActive = true;
    protected int $sortOrder = 0;

    protected function hydrate(array $data): void {
        if (array_key_exists('code', $data)) {
            $this->code = (string)$data['code'];
        }
        if (array_key_exists('display_name', $data)) {
            $this->displayName = (string)$data['display_name'];
        }
        if (array_key_exists('description', $data)) {
            $this->description = $this->toNullableString($data['description']);
        }
        if (array_key_exists('category', $data)) {
            $this->category = (string)$data['category'];
        }
        if (array_key_exists('icon', $data)) {
            $this->icon = $this->toNullableString($data['icon']);
        }
        if (array_key_exists('json_schema', $data)) {
            $this->jsonSchema = $this->decodeJson($data['json_schema']);
        }
        if (array_key_exists('gpt_hint', $data)) {
            $this->gptHint = $this->toNullableString($data['gpt_hint']);
        }
        if (array_key_exists('is_active', $data)) {
            $this->isActive = $this->toBool($data['is_active']);
        }
        if (array_key_exists('sort_order', $data)) {
            $this->sortOrder = (int)$data['sort_order'];
        }
    }

    public function toArray(): array {
        return [
            'display_name'  => $this->displayName,
            'description'   => $this->description,
            'category'      => $this->category,
            'icon'          => $this->icon,
            'json_schema'   => $this->encodeJson($this->jsonSchema),
            'gpt_hint'      => $this->gptHint,
            'is_active'     => (int)$this->isActive,
            'sort_order'    => $this->sortOrder,
        ];
    }

    public function toFullArray(): array {
        return array_merge(
            ['code' => $this->code],
            $this->toArray(),
            ['created_at' => $this->createdAt],
        );
    }

    public function getCode(): string { return $this->code; }
    public function setCode(string $code): self { $this->code = $code; return $this; }

    public function getDisplayName(): string { return $this->displayName; }
    public function setDisplayName(string $displayName): self { $this->displayName = $displayName; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getCategory(): string { return $this->category; }
    public function setCategory(string $category): self { $this->category = $category; return $this; }

    public function getIcon(): ?string { return $this->icon; }
    public function setIcon(?string $icon): self { $this->icon = $icon; return $this; }

    public function getJsonSchema(): ?array { return $this->jsonSchema; }
    public function setJsonSchema(?array $jsonSchema): self { $this->jsonSchema = $jsonSchema; return $this; }

    public function getGptHint(): ?string { return $this->gptHint; }
    public function setGptHint(?string $gptHint): self { $this->gptHint = $gptHint; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }

    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $sortOrder): self { $this->sortOrder = $sortOrder; return $this; }

    public static function validCategories(): array {
        return ['layout', 'content', 'data', 'interactive', 'cta'];
    }
}
