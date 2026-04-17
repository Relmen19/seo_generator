<?php

declare(strict_types=1);

namespace Seo\Entity;

class SeoTelegramPost extends AbstractEntity {

    public const TABLE = 'seo_telegram_posts';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SENDING   = 'sending';
    public const STATUS_SENT      = 'sent';
    public const STATUS_FAILED    = 'failed';

    protected ?int $articleId = null;
    protected ?int $profileId = null;
    protected string $status = self::STATUS_DRAFT;
    protected string $postFormat = 'auto';
    protected ?string $scheduledAt = null;
    protected ?string $sentAt = null;
    protected ?array $postData = null;
    protected ?array $tgMessageIds = null;
    protected ?string $tgPostUrl = null;
    protected ?string $errorMessage = null;
    protected int $attempts = 0;

    protected function hydrate(array $data): void {
        if (array_key_exists('article_id', $data)) {
            $this->articleId = $this->toNullableInt($data['article_id']);
        }
        if (array_key_exists('profile_id', $data)) {
            $this->profileId = $this->toNullableInt($data['profile_id']);
        }
        if (array_key_exists('status', $data)) {
            $this->status = (string)$data['status'];
        }
        if (array_key_exists('post_format', $data)) {
            $this->postFormat = (string)$data['post_format'];
        }
        if (array_key_exists('scheduled_at', $data)) {
            $this->scheduledAt = $this->toNullableString($data['scheduled_at']);
        }
        if (array_key_exists('sent_at', $data)) {
            $this->sentAt = $this->toNullableString($data['sent_at']);
        }
        if (array_key_exists('post_data', $data)) {
            $this->postData = $this->decodeJson($data['post_data']);
        }
        if (array_key_exists('tg_message_ids', $data)) {
            $this->tgMessageIds = $this->decodeJson($data['tg_message_ids']);
        }
        if (array_key_exists('tg_post_url', $data)) {
            $this->tgPostUrl = $this->toNullableString($data['tg_post_url']);
        }
        if (array_key_exists('error_message', $data)) {
            $this->errorMessage = $this->toNullableString($data['error_message']);
        }
        if (array_key_exists('attempts', $data)) {
            $this->attempts = (int)$data['attempts'];
        }
    }

    public function toArray(): array {
        return [
            'article_id'     => $this->articleId,
            'profile_id'     => $this->profileId,
            'status'         => $this->status,
            'post_format'    => $this->postFormat,
            'scheduled_at'   => $this->scheduledAt,
            'sent_at'        => $this->sentAt,
            'post_data'      => $this->encodeJson($this->postData),
            'tg_message_ids' => $this->encodeJson($this->tgMessageIds),
            'tg_post_url'    => $this->tgPostUrl,
            'error_message'  => $this->errorMessage,
            'attempts'       => $this->attempts,
        ];
    }

    public function getArticleId(): ?int { return $this->articleId; }
    public function setArticleId(?int $v): self { $this->articleId = $v; return $this; }

    public function getProfileId(): ?int { return $this->profileId; }
    public function setProfileId(?int $v): self { $this->profileId = $v; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): self { $this->status = $v; return $this; }

    public function getPostFormat(): string { return $this->postFormat; }
    public function setPostFormat(string $v): self { $this->postFormat = $v; return $this; }

    public function getScheduledAt(): ?string { return $this->scheduledAt; }
    public function setScheduledAt(?string $v): self { $this->scheduledAt = $v; return $this; }

    public function getSentAt(): ?string { return $this->sentAt; }
    public function setSentAt(?string $v): self { $this->sentAt = $v; return $this; }

    public function getPostData(): ?array { return $this->postData; }
    public function setPostData(?array $v): self { $this->postData = $v; return $this; }

    public function getTgMessageIds(): ?array { return $this->tgMessageIds; }
    public function setTgMessageIds(?array $v): self { $this->tgMessageIds = $v; return $this; }

    public function getTgPostUrl(): ?string { return $this->tgPostUrl; }
    public function setTgPostUrl(?string $v): self { $this->tgPostUrl = $v; return $this; }

    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function setErrorMessage(?string $v): self { $this->errorMessage = $v; return $this; }

    public function getAttempts(): int { return $this->attempts; }
    public function setAttempts(int $v): self { $this->attempts = $v; return $this; }

    public function isDraft(): bool { return $this->status === self::STATUS_DRAFT; }
    public function isScheduled(): bool { return $this->status === self::STATUS_SCHEDULED; }
    public function isSent(): bool { return $this->status === self::STATUS_SENT; }
}
