<?php

declare(strict_types=1);

namespace Seo\Entity;

class SeoAuditLog extends AbstractEntity {

    public const SEO_AUDIT_LOG_TABLE = 'seo_audit_log';

    public const ENTITY_ARTICLE  = 'article';
    public const ENTITY_TEMPLATE = 'template';
    public const ENTITY_CATALOG  = 'catalog';
    public const ENTITY_IMAGE    = 'image';
    public const ENTITY_LINK     = 'link';

    public const ACTION_CREATE     = 'create';
    public const ACTION_UPDATE     = 'update';
    public const ACTION_DELETE     = 'delete';
    public const ACTION_PUBLISH    = 'publish';
    public const ACTION_UNPUBLISH  = 'unpublish';
    public const ACTION_GENERATE   = 'generate';
    public const ACTION_REGENERATE = 'regenerate';

    protected string $entityType = '';
    protected int $entityId = 0;
    protected string $action = '';
    protected ?string $actor = null;
    protected ?array $details = null;


    protected function hydrate(array $data): void {
        if (array_key_exists('entity_type', $data)) {
            $this->entityType = (string)$data['entity_type'];
        }
        if (array_key_exists('entity_id', $data)) {
            $this->entityId = (int)$data['entity_id'];
        }
        if (array_key_exists('action', $data)) {
            $this->action = (string)$data['action'];
        }
        if (array_key_exists('actor', $data)) {
            $this->actor = $this->toNullableString($data['actor']);
        }
        if (array_key_exists('details', $data)) {
            $this->details = $this->decodeJson($data['details']);
        }
    }

    public function toArray(): array {
        return [
            'entity_type' => $this->entityType,
            'entity_id'   => $this->entityId,
            'action'      => $this->action,
            'actor'       => $this->actor,
            'details'     => $this->encodeJson($this->details),
        ];
    }

    public static function articleAction(int $articleId, string $action, ?string $actor = null, ?array $details = null): self {
        $log = new self();
        $log->entityType = self::ENTITY_ARTICLE;
        $log->entityId = $articleId;
        $log->action = $action;
        $log->actor = $actor;
        $log->details = $details;
        return $log;
    }

    public static function catalogAction(int $catalogId, string $action, ?string $actor = null, ?array $details = null): self {
        $log = new self();
        $log->entityType = self::ENTITY_CATALOG;
        $log->entityId = $catalogId;
        $log->action = $action;
        $log->actor = $actor;
        $log->details = $details;
        return $log;
    }

    public function getEntityType(): string {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): self {
        $this->entityType = $entityType;
        return $this;
    }

    public function getEntityId(): int {
        return $this->entityId;
    }

    public function setEntityId(int $entityId): self {
        $this->entityId = $entityId;
        return $this;
    }

    public function getAction(): string {
        return $this->action;
    }

    public function setAction(string $action): self {
        $this->action = $action;
        return $this;
    }

    public function getActor(): ?string {
        return $this->actor;
    }

    public function setActor(?string $actor): self {
        $this->actor = $actor;
        return $this;
    }

    public function getDetails(): ?array {
        return $this->details;
    }

    public function setDetails(?array $details): self {
        $this->details = $details;
        return $this;
    }
}
