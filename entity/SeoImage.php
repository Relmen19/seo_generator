<?php

declare(strict_types=1);

namespace Seo\Entity;

class SeoImage extends AbstractEntity {

    public const SEO_IMAGE_TABLE = 'seo_images';

    public const SOURCE_GENERATED = 'generated';
    public const SOURCE_UPLOADED  = 'uploaded';
    public const SOURCE_RENDERED  = 'rendered';

    public const SOURCES = [
        self::SOURCE_GENERATED,
        self::SOURCE_UPLOADED,
        self::SOURCE_RENDERED,
    ];

    protected ?int $articleId = null;
    protected ?int $blockId = null;
    protected ?string $name = null;
    protected ?string $altText = null;
    protected string $mimeType = 'image/png';
    protected ?int $width = null;
    protected ?int $height = null;
    protected string $dataBase64 = '';
    protected string $source = self::SOURCE_GENERATED;
    protected ?string $gptPrompt = null;
    protected ?string $layout = 'center';

    protected function hydrate(array $data): void {
        if (array_key_exists('article_id', $data)) {
            $this->articleId = $this->toNullableInt($data['article_id']);
        }
        if (array_key_exists('block_id', $data)) {
            $this->blockId = $this->toNullableInt($data['block_id']);
        }
        if (array_key_exists('name', $data)) {
            $this->name = $this->toNullableString($data['name']);
        }
        if (array_key_exists('alt_text', $data)) {
            $this->altText = $this->toNullableString($data['alt_text']);
        }
        if (array_key_exists('mime_type', $data)) {
            $this->mimeType = (string)$data['mime_type'];
        }
        if (array_key_exists('width', $data)) {
            $this->width = $this->toNullableInt($data['width']);
        }
        if (array_key_exists('height', $data)) {
            $this->height = $this->toNullableInt($data['height']);
        }
        if (array_key_exists('data_base64', $data)) {
            $this->dataBase64 = (string)$data['data_base64'];
        }
        if (array_key_exists('source', $data)) {
            $this->source = (string)$data['source'];
        }
        if (array_key_exists('gpt_prompt', $data)) {
            $this->gptPrompt = $this->toNullableString($data['gpt_prompt']);
        }
        if (array_key_exists('layout', $data)) {
            $this->layout = $this->toNullableString($data['layout']);
        }
    }

    public function toArray(): array {
        return [
            'article_id'  => $this->articleId,
            'block_id'    => $this->blockId,
            'name'        => $this->name,
            'alt_text'    => $this->altText,
            'mime_type'   => $this->mimeType,
            'width'       => $this->width,
            'height'      => $this->height,
            'data_base64' => $this->dataBase64,
            'source'      => $this->source,
            'gpt_prompt'  => $this->gptPrompt,
            'layout'      => $this->layout,
        ];
    }

    public function toArrayLight(): array {
        $arr = $this->toFullArray();
        unset($arr['data_base64']);
        $arr['has_data'] = !empty($this->dataBase64);
        $arr['size_bytes'] = $this->getSizeBytes();
        return $arr;
    }

    public function getDataUri(): string {
        return sprintf('data:%s;base64,%s', $this->mimeType, $this->dataBase64);
    }

    public function getSizeBytes(): int {
        return (int)(strlen($this->dataBase64) * 3 / 4);
    }

    public function isGenerated(): bool {
        return $this->source === self::SOURCE_GENERATED;
    }





    public function getArticleId(): ?int {
        return $this->articleId;
    }

    public function setArticleId(?int $articleId): self {
        $this->articleId = $articleId;
        return $this;
    }

    public function getBlockId(): ?int {
        return $this->blockId;
    }

    public function setBlockId(?int $blockId): self {
        $this->blockId = $blockId;
        return $this;
    }

    public function getName(): ?string {
        return $this->name;
    }

    public function setName(?string $name): self {
        $this->name = $name;
        return $this;
    }

    public function getAltText(): ?string {
        return $this->altText;
    }

    public function setAltText(?string $altText): self {
        $this->altText = $altText;
        return $this;
    }

    public function getMimeType(): string {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getWidth(): ?int {
        return $this->width;
    }

    public function setWidth(?int $width): self {
        $this->width = $width;
        return $this;
    }

    public function getHeight(): ?int {
        return $this->height;
    }

    public function setHeight(?int $height): self {
        $this->height = $height;
        return $this;
    }

    public function getDataBase64(): string {
        return $this->dataBase64;
    }

    public function setDataBase64(string $dataBase64): self {
        $this->dataBase64 = $dataBase64;
        return $this;
    }

    public function getSource(): string {
        return $this->source;
    }

    public function setSource(string $source): self {
        $this->source = $source;
        return $this;
    }

    public function getGptPrompt(): ?string {
        return $this->gptPrompt;
    }

    public function setGptPrompt(?string $gptPrompt): self {
        $this->gptPrompt = $gptPrompt;
        return $this;
    }

    public function getLayout(): ?string {
        return $this->layout;
    }

    public function setLayout(?string $layout): self {
        $this->layout = $layout;
        return $this;
    }
}
