<?php

declare(strict_types=1);

namespace Seo\Entity;


class SeoTemplateBlock extends AbstractEntity {

    public const SEO_TEMPLATE_BLOCK_TABLE = 'seo_template_blocks';

    public const TYPES = [
        'hero',
        'stats_counter',
        'richtext',
        'range_table',
        'accordion',
        'chart',
        'comparison_table',
        'image_section',
        'faq',
        'cta',
        'feature_grid',
        'testimonial',
        'gauge_chart',
        'timeline',
        'heatmap',
        'funnel',
        'spark_metrics',
        'radar_chart',
        'before_after',
        'stacked_area',
        'score_rings',
        'range_comparison',
        'value_checker',
        'criteria_checklist',
        'prep_checklist',
        'info_cards',
        'story_block',
        'verdict_card',
        'numbered_steps',
        'warning_block',
        'mini_calculator',
        'comparison_cards',
        'progress_tracker',
        'key_takeaways',
        'expert_panel',
    ];

    protected int $templateId = 0;
    protected string $type = '';
    protected string $name = '';
    protected ?array $config = null;
    protected int $sortOrder = 0;
    protected bool $isRequired = true;

    protected function hydrate(array $data): void {
        if (array_key_exists('template_id', $data)) {
            $this->templateId = (int)$data['template_id'];
        }
        if (array_key_exists('type', $data)) {
            $this->type = (string)$data['type'];
        }
        if (array_key_exists('name', $data)) {
            $this->name = (string)$data['name'];
        }
        if (array_key_exists('config', $data)) {
            $this->config = $this->decodeJson($data['config']);
        }
        if (array_key_exists('sort_order', $data)) {
            $this->sortOrder = (int)$data['sort_order'];
        }
        if (array_key_exists('is_required', $data)) {
            $this->isRequired = $this->toBool($data['is_required']);
        }
    }


    public function toArray(): array {
        return [
            'template_id' => $this->templateId,
            'type'        => $this->type,
            'name'        => $this->name,
            'config'      => $this->encodeJson($this->config),
            'sort_order'  => $this->sortOrder,
            'is_required' => (int)$this->isRequired,
        ];
    }


    public function getTemplateId(): int {
        return $this->templateId;
    }

    public function setTemplateId(int $templateId): self {
        $this->templateId = $templateId;
        return $this;
    }

    public function getType(): string {
        return $this->type;
    }

    public function setType(string $type): self {
        $this->type = $type;
        return $this;
    }

    public function getName(): string {
        return $this->name;
    }

    public function setName(string $name): self {
        $this->name = $name;
        return $this;
    }

    public function getConfig(): ?array {
        return $this->config;
    }

    public function setConfig(?array $config): self {
        $this->config = $config;
        return $this;
    }

    public function getConfigValue(string $key, $default = null) {
        return $this->config[$key] ?? $default;
    }

    public function getHint(): ?string {
        return $this->getConfigValue('hint');
    }

    public function getFields(): array {
        return $this->getConfigValue('fields', []);
    }

    public function getSortOrder(): int {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    public function isRequired(): bool {
        return $this->isRequired;
    }

    public function setIsRequired(bool $isRequired): self {
        $this->isRequired = $isRequired;
        return $this;
    }

    public static function isValidType(string $type): bool {
        return in_array($type, self::TYPES, true);
    }
}
