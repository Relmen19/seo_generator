<?php

declare(strict_types=1);

namespace Seo\Entity;

class SeoTelegramRenderedImage extends AbstractEntity {

    public const TABLE = 'seo_telegram_rendered_images';

    protected ?int $tgPostId = null;
    protected ?int $blockId = null;
    protected string $blockType = '';
    protected string $imageData = '';
    protected ?int $width = null;
    protected ?int $height = null;
    protected int $sortOrder = 0;

    protected function hydrate(array $data): void {
        if (array_key_exists('tg_post_id', $data)) {
            $this->tgPostId = $this->toNullableInt($data['tg_post_id']);
        }
        if (array_key_exists('block_id', $data)) {
            $this->blockId = $this->toNullableInt($data['block_id']);
        }
        if (array_key_exists('block_type', $data)) {
            $this->blockType = (string)$data['block_type'];
        }
        if (array_key_exists('image_data', $data)) {
            $this->imageData = (string)$data['image_data'];
        }
        if (array_key_exists('width', $data)) {
            $this->width = $this->toNullableInt($data['width']);
        }
        if (array_key_exists('height', $data)) {
            $this->height = $this->toNullableInt($data['height']);
        }
        if (array_key_exists('sort_order', $data)) {
            $this->sortOrder = (int)$data['sort_order'];
        }
    }

    public function toArray(): array {
        return [
            'tg_post_id'  => $this->tgPostId,
            'block_id'    => $this->blockId,
            'block_type'  => $this->blockType,
            'image_data'  => $this->imageData,
            'width'       => $this->width,
            'height'      => $this->height,
            'sort_order'  => $this->sortOrder,
        ];
    }

    public function getTgPostId(): ?int { return $this->tgPostId; }
    public function setTgPostId(?int $v): self { $this->tgPostId = $v; return $this; }

    public function getBlockId(): ?int { return $this->blockId; }
    public function setBlockId(?int $v): self { $this->blockId = $v; return $this; }

    public function getBlockType(): string { return $this->blockType; }
    public function setBlockType(string $v): self { $this->blockType = $v; return $this; }

    public function getImageData(): string { return $this->imageData; }
    public function setImageData(string $v): self { $this->imageData = $v; return $this; }

    public function getWidth(): ?int { return $this->width; }
    public function setWidth(?int $v): self { $this->width = $v; return $this; }

    public function getHeight(): ?int { return $this->height; }
    public function setHeight(?int $v): self { $this->height = $v; return $this; }

    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $v): self { $this->sortOrder = $v; return $this; }
}
