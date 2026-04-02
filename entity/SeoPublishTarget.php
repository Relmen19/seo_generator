<?php

declare(strict_types=1);

namespace Seo\Entity;

class SeoPublishTarget extends AbstractEntity {

    public const SEO_PUBLISH_TARGET_TABLE = 'seo_publish_targets';

    public const TYPE_HOSTIA = 'hostia';
    public const TYPE_FTP    = 'ftp';
    public const TYPE_SSH    = 'ssh';
    public const TYPE_API    = 'api';

    public const TYPES = [
        self::TYPE_HOSTIA,
        self::TYPE_FTP,
        self::TYPE_SSH,
        self::TYPE_API,
    ];

    protected string $name = '';
    protected string $type = self::TYPE_HOSTIA;
    protected array $config = [];
    protected string $baseUrl = '';
    protected bool $isActive = true;


    protected function hydrate(array $data): void {
        if (array_key_exists('name', $data)) {
            $this->name = (string)$data['name'];
        }
        if (array_key_exists('type', $data)) {
            $this->type = (string)$data['type'];
        }
        if (array_key_exists('config', $data)) {
            $this->config = $this->decodeJson($data['config']) ?? [];
        }
        if (array_key_exists('base_url', $data)) {
            $this->baseUrl = (string)$data['base_url'];
        }
        if (array_key_exists('is_active', $data)) {
            $this->isActive = $this->toBool($data['is_active']);
        }
    }


    public function toArray(): array {
        return [
            'name'      => $this->name,
            'type'      => $this->type,
            'config'    => $this->encodeJson($this->config),
            'base_url'  => $this->baseUrl,
            'is_active' => (int)$this->isActive,
        ];
    }


    public function getConfigValue(string $key, $default = null) {
        return $this->config[$key] ?? $default;
    }

    public function getHost(): ?string {
        return $this->getConfigValue('host');
    }

    public function getDocumentRoot(): ?string {
        return $this->getConfigValue('document_root');
    }

    public function buildPublicUrl(string $catalogPath, string $articleSlug): string {
        return rtrim($this->baseUrl, '/') . '/' . trim($catalogPath, '/') . '/' . $articleSlug;
    }


    public function getName(): string {
        return $this->name;
    }

    public function setName(string $name): self {
        $this->name = $name;
        return $this;
    }

    public function getType(): string {
        return $this->type;
    }

    public function setType(string $type): self {
        $this->type = $type;
        return $this;
    }

    public function getConfig(): array {
        return $this->config;
    }

    public function setConfig(array $config): self {
        $this->config = $config;
        return $this;
    }

    public function getBaseUrl(): string {
        return $this->baseUrl;
    }

    public function setBaseUrl(string $baseUrl): self {
        $this->baseUrl = $baseUrl;
        return $this;
    }

    public function isActive(): bool {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self {
        $this->isActive = $isActive;
        return $this;
    }
}
