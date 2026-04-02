<?php

declare(strict_types=1);

namespace Seo\Entity;


class SeoTemplate extends AbstractEntity {

    public const TABLE = 'seo_templates';

    protected ?int $profileId = null;
    protected string $name = '';
    protected string $slug = '';
    protected ?string $description = null;
    protected ?string $previewImage = null;
    protected ?string $gptSystemPrompt = null;
    protected ?string $cssClass = null;
    protected bool $isActive = true;

    protected array $blocks = [];

    protected function hydrate(array $data): void {
        if (array_key_exists('profile_id', $data)) {
            $this->profileId = $data['profile_id'] !== null ? (int)$data['profile_id'] : null;
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
        if (array_key_exists('preview_image', $data)) {
            $this->previewImage = $this->toNullableString($data['preview_image']);
        }
        if (array_key_exists('gpt_system_prompt', $data)) {
            $this->gptSystemPrompt = $this->toNullableString($data['gpt_system_prompt']);
        }
        if (array_key_exists('css_class', $data)) {
            $this->cssClass = $this->toNullableString($data['css_class']);
        }
        if (array_key_exists('is_active', $data)) {
            $this->isActive = $this->toBool($data['is_active']);
        }
    }

    public function toArray(): array {
        return [
            'profile_id'        => $this->profileId,
            'name'              => $this->name,
            'slug'              => $this->slug,
            'description'       => $this->description,
            'preview_image'     => $this->previewImage,
            'gpt_system_prompt' => $this->gptSystemPrompt,
            'css_class'         => $this->cssClass,
            'is_active'         => (int)$this->isActive,
        ];
    }

    public function toFullArray(): array {
        $arr = parent::toFullArray();
        if (!empty($this->blocks)) {
            $arr['blocks'] = array_map(fn(SeoTemplateBlock $b) => $b->toFullArray() , $this->blocks);
        }
        return $arr;
    }


    public function getProfileId(): ?int { return $this->profileId; }
    public function setProfileId(?int $profileId): self { $this->profileId = $profileId; return $this; }

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

    public function getPreviewImage(): ?string {
        return $this->previewImage;
    }

    public function setPreviewImage(?string $previewImage): self {
        $this->previewImage = $previewImage;
        return $this;
    }

    public function getGptSystemPrompt(): ?string {
        return $this->gptSystemPrompt;
    }

    public function setGptSystemPrompt(?string $gptSystemPrompt): self {
        $this->gptSystemPrompt = $gptSystemPrompt;
        return $this;
    }

    public function getCssClass(): ?string {
        return $this->cssClass;
    }

    public function setCssClass(?string $cssClass): self {
        $this->cssClass = $cssClass;
        return $this;
    }

    public function isActive(): bool {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self {
        $this->isActive = $isActive;
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
        usort($blocks, static function (SeoTemplateBlock $a, SeoTemplateBlock $b) {
            return $a->getSortOrder() <=> $b->getSortOrder();
        });
        return $blocks;
    }

    public function getRequiredBlocks(): array {
        return array_filter($this->blocks, static function (SeoTemplateBlock $b) {
            return $b->isRequired();
        });
    }
}
