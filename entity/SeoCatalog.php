<?php

declare(strict_types=1);

namespace Seo\Entity;

// Adjacency list: parent_id → id

class SeoCatalog extends AbstractEntity {

    public const SEO_CATALOG_TABLE = 'seo_catalogs';

    protected ?int $parentId = null;
    protected string $name = '';
    protected string $slug = '';
    protected ?string $description = null;
    protected int $sortOrder = 0;
    protected bool $isActive = true;

    protected array $children = [];
    protected ?string $fullPath = null;

    protected function hydrate(array $data): void {
        if (array_key_exists('parent_id', $data)) {
            $this->parentId = $this->toNullableInt($data['parent_id']);
        }
        if (array_key_exists('name', $data)) {
            $this->name = (string)$data['name'];
        }
        if (array_key_exists('slug', $data)) {
            $this->slug = (string)$data['slug'];
        }
        if (array_key_exists('description', $data)) {
            $this->description = $this->toNullableString($data['description']);
        }
        if (array_key_exists('sort_order', $data)) {
            $this->sortOrder = (int)$data['sort_order'];
        }
        if (array_key_exists('is_active', $data)) {
            $this->isActive = $this->toBool($data['is_active']);
        }
        if (array_key_exists('full_path', $data)) {
            $this->fullPath = $this->toNullableString($data['full_path']);
        }
    }

    public function toArray(): array {
        return [
            'parent_id'   => $this->parentId,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'description' => $this->description,
            'sort_order'  => $this->sortOrder,
            'is_active'   => (int)$this->isActive,
        ];
    }

    public function toFullArray(): array {
        $arr = parent::toFullArray();
        $arr['full_path'] = $this->fullPath;
        if (!empty($this->children)) {
            $arr['children'] = array_map(fn(SeoCatalog $c) => $c->toFullArray(), $this->children);
        }
        return $arr;
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

    public function getDescription(): ?string {
        return $this->description;
    }

    public function setDescription(?string $description): self {
        $this->description = $description;
        return $this;
    }

    public function getSortOrder(): int {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    public function isActive(): bool {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self {
        $this->isActive = $isActive;
        return $this;
    }


    public function getChildren(): array {
        return $this->children;
    }

    public function setChildren(array $children): self {
        $this->children = $children;
        return $this;
    }

    public function addChild(SeoCatalog $child): self {
        $this->children[] = $child;
        return $this;
    }

    public function getFullPath(): ?string {
        return $this->fullPath;
    }

    public function setFullPath(?string $fullPath): self {
        $this->fullPath = $fullPath;
        return $this;
    }

    public function isRoot(): bool {
        return $this->parentId === null;
    }
}
