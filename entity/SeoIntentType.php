<?php

declare(strict_types=1);

namespace Seo\Entity;

class SeoIntentType extends AbstractEntity {

    public const TABLE = 'seo_intent_types';

    // Universal intent codes
    public const CODE_INFO                = 'info';
    public const CODE_PROBLEM_CHECK       = 'problem_check';
    public const CODE_RESULT_INTERPRET    = 'result_interpret';
    public const CODE_ACTION_PLAN         = 'action_plan';
    public const CODE_RISK_ASSESSMENT     = 'risk_assessment';
    public const CODE_CONTEXT             = 'context';
    public const CODE_PREPARATION         = 'preparation';
    public const CODE_COMPARISON          = 'comparison';
    public const CODE_TRANSACTIONAL       = 'transactional';
    public const CODE_NAVIGATIONAL        = 'navigational';
    public const CODE_MYTH_DEBUNK         = 'myth_debunk';

    // Legacy aliases (medical profile)
    public const CODE_SYMPTOM_CHECK       = 'symptom_check';
    public const CODE_DIAGNOSIS_INTERPRET = 'diagnosis_interpret';
    public const CODE_LIFE_CONTEXT        = 'life_context';
    public const CODE_DOCTOR_PREP         = 'doctor_prep';


    protected string $code = '';
    protected ?int $profileId = null;
    protected string $labelRu = '';
    protected string $labelEn = '';
    protected string $color = '#6366f1';
    protected int $sortOrder = 0;
    protected string $description = '';
    protected string $gptHint = '';
    protected ?string $articleTone = null;
    protected ?string $articleOpen = null;
    protected bool $isActive = true;

    protected function hydrate(array $data): void {
        if (array_key_exists('code', $data)) {
            $this->code = (string)$data['code'];
        }
        if (array_key_exists('profile_id', $data)) {
            $this->profileId = $data['profile_id'] !== null ? (int)$data['profile_id'] : null;
        }
        if (array_key_exists('label_ru', $data)) {
            $this->labelRu = (string)$data['label_ru'];
        }
        if (array_key_exists('label_en', $data)) {
            $this->labelEn = (string)$data['label_en'];
        }
        if (array_key_exists('color', $data)) {
            $this->color = (string)$data['color'];
        }
        if (array_key_exists('sort_order', $data)) {
            $this->sortOrder = (int)$data['sort_order'];
        }
        if (array_key_exists('description', $data)) {
            $this->description = (string)$data['description'];
        }
        if (array_key_exists('gpt_hint', $data)) {
            $this->gptHint = (string)$data['gpt_hint'];
        }
        if (array_key_exists('article_tone', $data)) {
            $this->articleTone = $this->toNullableString($data['article_tone']);
        }
        if (array_key_exists('article_open', $data)) {
            $this->articleOpen = $this->toNullableString($data['article_open']);
        }
        if (array_key_exists('is_active', $data)) {
            $this->isActive = $this->toBool($data['is_active']);
        }
    }

    public function toArray(): array {
        return [
            'profile_id'   => $this->profileId,
            'label_ru'     => $this->labelRu,
            'label_en'     => $this->labelEn,
            'color'        => $this->color,
            'sort_order'   => $this->sortOrder,
            'description'  => $this->description,
            'gpt_hint'     => $this->gptHint,
            'article_tone' => $this->articleTone,
            'article_open' => $this->articleOpen,
            'is_active'    => (int)$this->isActive,
        ];
    }

    public function toFullArray(): array {
        return array_merge(
            ['code' => $this->code],
            $this->toArray(),
            ['created_at' => $this->createdAt],
        );
    }

    public function getProfileId(): ?int { return $this->profileId; }
    public function setProfileId(?int $profileId): self { $this->profileId = $profileId; return $this; }

    public function getCode(): string {
        return $this->code;
    }

    public function setCode(string $code): self {
        $this->code = $code;
        return $this;
    }

    public function getLabelRu(): string {
        return $this->labelRu;
    }

    public function setLabelRu(string $labelRu): self {
        $this->labelRu = $labelRu;
        return $this;
    }

    public function getLabelEn(): string {
        return $this->labelEn;
    }

    public function setLabelEn(string $labelEn): self {
        $this->labelEn = $labelEn;
        return $this;
    }

    public function getColor(): string {
        return $this->color;
    }

    public function setColor(string $color): self {
        $this->color = $color;
        return $this;
    }

    public function getSortOrder(): int {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function setDescription(string $description): self {
        $this->description = $description;
        return $this;
    }

    public function getGptHint(): string {
        return $this->gptHint;
    }

    public function setGptHint(string $gptHint): self {
        $this->gptHint = $gptHint;
        return $this;
    }

    public function getArticleTone(): ?string {
        return $this->articleTone;
    }

    public function setArticleTone(?string $articleTone): self {
        $this->articleTone = $articleTone;
        return $this;
    }

    public function getArticleOpen(): ?string {
        return $this->articleOpen;
    }

    public function setArticleOpen(?string $articleOpen): self {
        $this->articleOpen = $articleOpen;
        return $this;
    }

    public function isActive(): bool {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self {
        $this->isActive = $isActive;
        return $this;
    }

    public static function isValidCode(string $code): bool {
        // только строчные буквы, цифры и подчёркивания, длина 1-30
        return (bool)preg_match('/^[a-z0-9_]{1,30}$/', $code);
    }
}