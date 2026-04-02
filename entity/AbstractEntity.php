<?php

declare(strict_types=1);

namespace Seo\Entity;

abstract class AbstractEntity {

    protected ?int $id = null;
    protected ?string $createdAt = null;
    protected ?string $updatedAt = null;

    public function __construct(array $data = []) {
        if (!empty($data)) {
            $this->fromArray($data);
        }
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function setId(?int $id): self {
        $this->id = $id;
        return $this;
    }

    public function getCreatedAt(): ?string {
        return $this->createdAt;
    }

    public function setCreatedAt(?string $createdAt): self {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?string {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?string $updatedAt): self {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function fromArray(array $data): self {

        if (array_key_exists('id', $data)) {
            $this->id = $data['id'] !== null ? (int)$data['id'] : null;
        }
        if (array_key_exists('created_at', $data)) {
            $this->createdAt = $data['created_at'];
        }
        if (array_key_exists('updated_at', $data)) {
            $this->updatedAt = $data['updated_at'];
        }

        $this->hydrate($data);

        return $this;
    }

    abstract protected function hydrate(array $data): void;

    abstract public function toArray(): array;

    public function toFullArray(): array {
        return array_merge(
            ['id' => $this->id],
            $this->toArray(),
            [
                'created_at' => $this->createdAt,
                'updated_at' => $this->updatedAt,
            ]
        );
    }

    protected function decodeJson($value): ?array {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : null;
    }

    protected function encodeJson(?array $value): ?string {
        if ($value === null) {
            return null;
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function toBool($value): bool {
        return (bool)(int)$value;
    }

    protected function toNullableInt($value): ?int {
        return $value !== null ? (int)$value : null;
    }

    protected function toNullableString($value): ?string {
        return $value !== null ? (string)$value : null;
    }
}
