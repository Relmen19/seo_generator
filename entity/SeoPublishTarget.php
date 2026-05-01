<?php

declare(strict_types=1);

namespace Seo\Entity;

class SeoPublishTarget extends AbstractEntity {

    public const SEO_PUBLISH_TARGET_TABLE = 'seo_publish_targets';

    public const TYPE_SELFHOSTED = 'selfhosted';
    public const TYPE_FTP        = 'ftp';

    public const TYPES = [
        self::TYPE_SELFHOSTED,
        self::TYPE_FTP,
    ];

    protected ?int $profileId = null;
    protected string $name = '';
    protected string $type = self::TYPE_SELFHOSTED;
    protected array $config = [];
    protected string $baseUrl = '';
    protected bool $isActive = true;


    protected function hydrate(array $data): void {
        if (array_key_exists('profile_id', $data)) {
            $this->profileId = $this->toNullableInt($data['profile_id']);
        }
        if (array_key_exists('name', $data)) {
            $this->name = (string)$data['name'];
        }
        if (array_key_exists('type', $data)) {
            $type = (string)$data['type'];
            // legacy: 'hostia' renamed to 'selfhosted' in migration 042
            if ($type === 'hostia') $type = self::TYPE_SELFHOSTED;
            $this->type = $type;
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
            'profile_id' => $this->profileId,
            'name'       => $this->name,
            'type'       => $this->type,
            'config'     => $this->encodeJson($this->config),
            'base_url'   => $this->baseUrl,
            'is_active'  => (int)$this->isActive,
        ];
    }

    public function getProfileId(): ?int {
        return $this->profileId;
    }

    public function setProfileId(?int $id): self {
        $this->profileId = $id;
        return $this;
    }

    /**
     * API-friendly view: overrides DB-oriented toArray() so the config
     * field arrives at the client as a decoded object, not a JSON string.
     */
    public function toFullArray(): array {
        $full = parent::toFullArray();
        $full['config'] = $this->config;
        return $full;
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
