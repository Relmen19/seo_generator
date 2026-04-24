<?php

declare(strict_types=1);

namespace Seo\Service;

use RuntimeException;
use Seo\Database;
use Seo\Entity\SeoBlockType;
use Seo\Entity\SeoSiteProfile;
use Seo\Entity\SeoTemplate;
use Seo\Entity\SeoTemplateBlock;
use Seo\Enum\TemplatePrompt;
use Throwable;

class TemplateGeneratorService {

    private GptClient $gpt;
    private Database $db;
    private TelegramBlockFormatterService $telegramFormatter;

    public function __construct(
        ?GptClient $gpt = null,
        ?TelegramBlockFormatterService $telegramFormatter = null,
    ) {
        $this->gpt              = $gpt ?? new GptClient();
        $this->db               = Database::getInstance();
        $this->telegramFormatter = $telegramFormatter ?? new TelegramBlockFormatterService();
    }

    /**
     * Generate a set of templates for a profile based on niche description.
     * Returns the GPT proposal (array of templates with blocks) for review before saving.
     */
    public function generateProposal(int $profileId, string $nicheDescription, array $options = []): array {
        $profile = $this->loadProfile($profileId);
        $blockTypes = $this->loadActiveBlockTypes();
        $count = min((int)($options['count'] ?? 5), 10);

        $systemPrompt = $this->buildSystemPrompt($profile, $blockTypes, $count);
        $userPrompt = $this->buildUserPrompt($profile, $nicheDescription, $count);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        $gptOptions = [
            'model' => $options['model'] ?? GPT_DEFAULT_MODEL,
            'temperature' => $options['temperature'] ?? SEO_TEMPERATURE_CREATIVE,
            'max_tokens' => $options['max_tokens'] ?? SEO_MAX_TOKENS_LARGE,
        ];

        $this->gpt->setLogContext(['category' => TokenUsageLogger::CATEGORY_TEMPLATE_CREATE, 'operation' => 'generate_proposal', 'profile_id' => $profileId]);
        $result = $this->gpt->chatJson($messages, $gptOptions);

        $templates = $result['data']['templates'] ?? [];
        if (empty($templates)) {
            throw new RuntimeException('GPT не вернул шаблоны. Попробуйте уточнить описание ниши.');
        }

        // Validate block types in the proposal
        $validTypes = array_column($blockTypes, 'code');
        foreach ($templates as &$tpl) {
            $tpl['blocks'] = array_filter($tpl['blocks'] ?? [], static function (array $block) use ($validTypes) {
                return in_array($block['type'] ?? '', $validTypes, true);
            });
            $tpl['blocks'] = array_values($tpl['blocks']);
        }
        unset($tpl);

        return [
            'profile_id' => $profileId,
            'templates' => $templates,
            'usage' => $result['usage'],
            'model' => $result['model'],
        ];
    }

    /**
     * Save confirmed templates from a proposal into the database.
     */
    public function saveProposal(int $profileId, array $templates): array {
        $this->loadProfile($profileId); // validate exists

        $savedIds = [];

        $this->db->transaction(function () use ($profileId, $templates, &$savedIds) {
            foreach ($templates as $tplData) {
                $slug = $this->generateUniqueSlug($tplData['slug'] ?? $this->slugify($tplData['name'] ?? 'template'));

                $templateId = $this->db->insert(SeoTemplate::TABLE, [
                    'profile_id' => $profileId,
                    'name' => $tplData['name'] ?? 'Без названия',
                    'slug' => $slug,
                    'description' => $tplData['description'] ?? null,
                    'gpt_system_prompt' => $tplData['gpt_system_prompt'] ?? null,
                    'css_class' => $tplData['css_class'] ?? null,
                    'is_active' => 1,
                ]);

                foreach (($tplData['blocks'] ?? []) as $i => $blockData) {
                    $this->db->insert(SeoTemplateBlock::SEO_TEMPLATE_BLOCK_TABLE, [
                        'template_id' => $templateId,
                        'type' => $blockData['type'],
                        'name' => $blockData['name'] ?? $blockData['type'],
                        'config' => json_encode([
                            'hint' => $blockData['hint'] ?? '',
                            'fields' => $blockData['fields'] ?? [],
                        ], JSON_UNESCAPED_UNICODE),
                        'sort_order' => $blockData['sort_order'] ?? ($i + 1),
                        'is_required' => (int)($blockData['is_required'] ?? true),
                    ]);
                }

                $savedIds[] = $templateId;
            }
        });

        return ['saved_template_ids' => $savedIds];
    }

    public function generateTelegramPost(array $blocks): array
    {
        $payloads = [];

        foreach ($blocks as $block) {
            $type = $block['type'] ?? '';
            $data = $block['data'] ?? [];

            if ($type === '') {
                continue;
            }

            $formatted = $this->telegramFormatter->format($type, $data);

            if ($formatted->isEmpty()) {
                continue;
            }

            $payloads[] = [
                'type'     => $type,
                'text'     => $formatted->text,
                'keyboard' => $formatted->keyboard,
            ];
        }

        return $payloads;
    }

    /**
     * Generate a single template via SSE with AI review.
     */
    public function generateSingleTemplateSSE(int $profileId, string $purpose, array $options = []): void {
        $totalUsage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
        $model = $options['model'] ?? GPT_DEFAULT_MODEL;

        $profile = $this->loadProfile($profileId);
        $blockTypes = $this->loadActiveBlockTypes();

        $this->sendSSE('start', ['profile_id' => $profileId, 'purpose' => $purpose]);

        // ── Step 1: Generate template ──
        $this->sendSSE('generation_start', []);

        try {
            $systemPrompt = $this->buildSingleTemplateSystemPrompt($profile, $blockTypes, $purpose);
            $userPrompt = $this->buildSingleTemplateUserPrompt($profile, $purpose, $options['hints'] ?? null);

            $result = $this->gpt->chatJson([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ], [
                'model' => $model,
                'temperature' => SEO_TEMPERATURE_CREATIVE,
                'max_tokens' => SEO_MAX_TOKENS_LARGE,
            ]);

            $this->addUsage($totalUsage, $result['usage'] ?? []);

            $template = $result['data']['template'] ?? $result['data'] ?? null;
            if (!$template || empty($template['name'])) {
                $this->sendSSE('error', ['message' => 'AI не вернул шаблон. Попробуйте уточнить описание.', 'step' => 'generation']);
                return;
            }

            // Validate block types
            $validTypes = array_column($blockTypes, 'code');
            $template['blocks'] = array_values(array_filter(
                $template['blocks'] ?? [],
                static function (array $block) use ($validTypes) {
                    return in_array($block['type'] ?? '', $validTypes, true);
                }
            ));

            $this->sendSSE('generation_done', ['template' => $template]);
        } catch (Throwable $e) {
            $this->sendSSE('error', ['message' => $e->getMessage(), 'step' => 'generation']);
            return;
        }

        // ── Step 2: Review template ──
        $this->sendSSE('review_start', []);

        try {
            $reviewPrompt = $this->buildReviewPrompt($template, $profile, $blockTypes);

            $this->gpt->setLogContext(['category' => TokenUsageLogger::CATEGORY_TEMPLATE_REVIEW, 'operation' => 'review_new_single_sse', 'profile_id' => $profileId]);
            $reviewResult = $this->gpt->chatJson([
                ['role' => 'system', 'content' => $reviewPrompt],
                ['role' => 'user', 'content' => TemplatePrompt::USER_DO_REVIEW],
            ], [
                'model' => $model,
                'temperature' => SEO_TEMPERATURE_PRECISE,
                'max_tokens' => SEO_MAX_TOKENS_LARGE,
            ]);

            $this->addUsage($totalUsage, $reviewResult['usage'] ?? []);

            $review = $reviewResult['data'] ?? [];
            $score = (int)($review['score'] ?? 7);
            $suggestions = $review['suggestions'] ?? [];
            $improved = $review['improved_template'] ?? null;

            // Use improved template if provided and has blocks
            if ($improved && !empty($improved['blocks'])) {
                $improved['blocks'] = array_values(array_filter(
                    $improved['blocks'] ?? [],
                    static function (array $block) use ($validTypes) {
                        return in_array($block['type'] ?? '', $validTypes, true);
                    }
                ));
                if (!empty($improved['blocks'])) {
                    $template = $improved;
                }
            }

            $this->sendSSE('review_done', [
                'review' => [
                    'score' => $score,
                    'suggestions' => $suggestions,
                ],
                'template' => $template,
            ]);
        } catch (Throwable $e) {
            // Review failed — save original template anyway
            $this->sendSSE('review_done', [
                'review' => [
                    'score' => 0,
                    'suggestions' => ['Ревью не удалось: ' . $e->getMessage()],
                ],
                'template' => $template,
            ]);
        }

        // ── Step 3: Save template ──
        $this->sendSSE('save_start', []);

        try {
            $saved = $this->saveProposal($profileId, [$template]);
            $templateId = $saved['saved_template_ids'][0] ?? null;

            $this->sendSSE('save_done', ['template_id' => $templateId]);
        } catch (Throwable $e) {
            $this->sendSSE('error', ['message' => $e->getMessage(), 'step' => 'save']);
            return;
        }

        $this->sendSSE('done', ['usage' => $totalUsage]);
    }

    /**
     * Review an existing saved template (non-SSE, returns JSON).
     */
    public function reviewExistingTemplate(int $templateId, array $options = []): array
    {
        $template = $this->loadTemplateAsArray($templateId);
        $profileId = $template['_profile_id'];
        unset($template['_profile_id']);

        $profile = $this->loadProfile((int)$profileId);
        $blockTypes = $this->loadActiveBlockTypes();

        $model = $options['model'] ?? GPT_DEFAULT_MODEL;
        $reviewPrompt = $this->buildReviewPrompt($template, $profile, $blockTypes);

        $this->gpt->setLogContext(['category' => TokenUsageLogger::CATEGORY_TEMPLATE_REVIEW, 'operation' => 'review_existing', 'profile_id' => (int)$profileId, 'entity_type' => 'template', 'entity_id' => $templateId]);
        $reviewResult = $this->gpt->chatJson([
            ['role' => 'system', 'content' => $reviewPrompt],
            ['role' => 'user', 'content' => TemplatePrompt::USER_DO_REVIEW],
        ], [
            'model' => $model,
            'temperature' => SEO_TEMPERATURE_PRECISE,
            'max_tokens' => SEO_MAX_TOKENS_LARGE,
        ]);

        $review = $reviewResult['data'] ?? [];
        $improved = $review['improved_template'] ?? null;

        $validTypes = array_column($blockTypes, 'code');
        if ($improved && !empty($improved['blocks'])) {
            $improved['blocks'] = array_values(array_filter(
                $improved['blocks'],
                static function (array $block) use ($validTypes) {
                    return in_array($block['type'] ?? '', $validTypes, true);
                }
            ));
            if (empty($improved['blocks'])) {
                $improved = null;
            }
        }

        return [
            'score' => (int)($review['score'] ?? 0),
            'suggestions' => $review['suggestions'] ?? [],
            'improved_template' => $improved,
            'original_template' => $template,
            'usage' => $reviewResult['usage'] ?? [],
        ];
    }

    /**
     * Apply reviewed/improved template data to an existing template (replace fields + blocks).
     */
    public function applyTemplateData(int $templateId, array $templateData): void
    {
        $tplRow = $this->db->fetchOne(
            "SELECT id FROM " . SeoTemplate::TABLE . " WHERE id = ?",
            [$templateId]
        );
        if (!$tplRow) {
            throw new RuntimeException("Шаблон #{$templateId} не найден");
        }

        $this->db->transaction(function () use ($templateId, $templateData) {
            $updateFields = [];
            foreach (['name', 'description', 'gpt_system_prompt', 'css_class'] as $f) {
                if (isset($templateData[$f])) {
                    $updateFields[$f] = $templateData[$f];
                }
            }

            if (!empty($updateFields)) {
                $this->db->update(SeoTemplate::TABLE, 'id = :id', $updateFields, [':id' => $templateId]);
            }

            if (isset($templateData['blocks']) && is_array($templateData['blocks'])) {
                $this->db->delete(
                    SeoTemplateBlock::SEO_TEMPLATE_BLOCK_TABLE,
                    'template_id = :tid',
                    [':tid' => $templateId]
                );

                foreach ($templateData['blocks'] as $i => $blockData) {
                    $this->db->insert(SeoTemplateBlock::SEO_TEMPLATE_BLOCK_TABLE, [
                        'template_id' => $templateId,
                        'type' => $blockData['type'],
                        'name' => $blockData['name'] ?? $blockData['type'],
                        'config' => json_encode([
                            'hint' => $blockData['hint'] ?? '',
                            'fields' => $blockData['fields'] ?? [],
                        ], JSON_UNESCAPED_UNICODE),
                        'sort_order' => $blockData['sort_order'] ?? ($i + 1),
                        'is_required' => (int)($blockData['is_required'] ?? true),
                    ]);
                }
            }
        });
    }

    /**
     * Regenerate a template via SSE — updates existing template in-place.
     */
    public function regenerateTemplateSSE(int $templateId, string $purpose, array $options = []): void
    {
        $tplRow = $this->db->fetchOne(
            "SELECT * FROM " . SeoTemplate::TABLE . " WHERE id = ?",
            [$templateId]
        );
        if (!$tplRow) {
            $this->sendSSE('error', ['message' => "Шаблон #{$templateId} не найден"]);
            return;
        }

        $totalUsage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
        $model = $options['model'] ?? GPT_DEFAULT_MODEL;
        $profileId = (int)$tplRow['profile_id'];

        $profile = $this->loadProfile($profileId);
        $blockTypes = $this->loadActiveBlockTypes();
        $validTypes = array_column($blockTypes, 'code');

        $this->sendSSE('start', ['template_id' => $templateId, 'purpose' => $purpose]);

        // ── Step 1: Generate template ──
        $this->sendSSE('generation_start', []);

        try {
            $systemPrompt = $this->buildSingleTemplateSystemPrompt($profile, $blockTypes, $purpose);
            $userPrompt = $this->buildSingleTemplateUserPrompt($profile, $purpose, $options['hints'] ?? null);

            $this->gpt->setLogContext(['category' => TokenUsageLogger::CATEGORY_TEMPLATE_CREATE, 'operation' => 'regenerate_single_sse', 'profile_id' => $profileId, 'entity_type' => 'template', 'entity_id' => $templateId]);
            $result = $this->gpt->chatJson([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ], [
                'model' => $model,
                'temperature' => SEO_TEMPERATURE_CREATIVE,
                'max_tokens' => SEO_MAX_TOKENS_LARGE,
            ]);

            $this->addUsage($totalUsage, $result['usage'] ?? []);

            $template = $result['data']['template'] ?? $result['data'] ?? null;
            if (!$template || empty($template['name'])) {
                $this->sendSSE('error', ['message' => 'AI не вернул шаблон.', 'step' => 'generation']);
                return;
            }

            $template['blocks'] = array_values(array_filter(
                $template['blocks'] ?? [],
                static function (array $block) use ($validTypes) {
                    return in_array($block['type'] ?? '', $validTypes, true);
                }
            ));

            $this->sendSSE('generation_done', ['template' => $template]);
        } catch (Throwable $e) {
            $this->sendSSE('error', ['message' => $e->getMessage(), 'step' => 'generation']);
            return;
        }

        // ── Step 2: Review template ──
        $this->sendSSE('review_start', []);

        try {
            $reviewPrompt = $this->buildReviewPrompt($template, $profile, $blockTypes);
            $this->gpt->setLogContext(['category' => TokenUsageLogger::CATEGORY_TEMPLATE_REVIEW, 'operation' => 'review_regen_sse', 'profile_id' => $profileId, 'entity_type' => 'template', 'entity_id' => $templateId]);
            $reviewResult = $this->gpt->chatJson([
                ['role' => 'system', 'content' => $reviewPrompt],
                ['role' => 'user', 'content' => TemplatePrompt::USER_DO_REVIEW],
            ], [
                'model' => $model,
                'temperature' => SEO_TEMPERATURE_PRECISE,
                'max_tokens' => SEO_MAX_TOKENS_LARGE,
            ]);

            $this->addUsage($totalUsage, $reviewResult['usage'] ?? []);

            $review = $reviewResult['data'] ?? [];
            $score = (int)($review['score'] ?? 7);
            $suggestions = $review['suggestions'] ?? [];
            $improved = $review['improved_template'] ?? null;

            if ($improved && !empty($improved['blocks'])) {
                $improved['blocks'] = array_values(array_filter(
                    $improved['blocks'] ?? [],
                    static function (array $block) use ($validTypes) {
                        return in_array($block['type'] ?? '', $validTypes, true);
                    }
                ));
                if (!empty($improved['blocks'])) {
                    $template = $improved;
                }
            }

            $this->sendSSE('review_done', [
                'review' => ['score' => $score, 'suggestions' => $suggestions],
                'template' => $template,
            ]);
        } catch (Throwable $e) {
            $this->sendSSE('review_done', [
                'review' => ['score' => 0, 'suggestions' => ['Ревью не удалось: ' . $e->getMessage()]],
                'template' => $template,
            ]);
        }

        // ── Step 3: Update template in-place ──
        $this->sendSSE('save_start', []);

        try {
            $this->applyTemplateData($templateId, $template);
            $this->sendSSE('save_done', ['template_id' => $templateId]);
        } catch (Throwable $e) {
            $this->sendSSE('error', ['message' => $e->getMessage(), 'step' => 'save']);
            return;
        }

        $this->sendSSE('done', ['usage' => $totalUsage]);
    }

    /**
     * Load a template with blocks as a plain array (for review prompt).
     */
    private function loadTemplateAsArray(int $templateId): array
    {
        $tplRow = $this->db->fetchOne(
            "SELECT * FROM " . SeoTemplate::TABLE . " WHERE id = ?",
            [$templateId]
        );
        if (!$tplRow) {
            throw new RuntimeException("Шаблон #{$templateId} не найден");
        }

        $blocks = $this->db->fetchAll(
            "SELECT * FROM " . SeoTemplateBlock::SEO_TEMPLATE_BLOCK_TABLE
            . " WHERE template_id = ? ORDER BY sort_order",
            [$templateId]
        );

        return [
            '_profile_id' => $tplRow['profile_id'],
            'name' => $tplRow['name'],
            'slug' => $tplRow['slug'],
            'description' => $tplRow['description'],
            'css_class' => $tplRow['css_class'],
            'gpt_system_prompt' => $tplRow['gpt_system_prompt'],
            'blocks' => array_map(static function (array $b) {
                $config = json_decode($b['config'] ?? '{}', true);
                if (!is_array($config)) {
                    $config = [];
                }
                return [
                    'type' => $b['type'],
                    'name' => $b['name'],
                    'hint' => $config['hint'] ?? '',
                    'fields' => $config['fields'] ?? [],
                    'sort_order' => (int)$b['sort_order'],
                    'is_required' => (bool)(int)$b['is_required'],
                ];
            }, $blocks),
        ];
    }

    private function buildSingleTemplateSystemPrompt(array $profile, array $blockTypes, string $purpose): string {
        $typeList = $this->formatBlockTypeList($blockTypes);
        $profileContext = $this->formatProfileContext($profile);
        $briefContext = $this->formatBriefContext($profile);

        return TemplatePrompt::ARCHITECT_ROLE . "\n\n"
            . TemplatePrompt::ANTI_GENERIC . "\n\n"
            . "КОНТЕКСТ ПРОФИЛЯ:\n{$profileContext}\n"
            . ($briefContext !== '' ? "\nДЕТАЛЬНЫЙ БРИФ:\n{$briefContext}\n" : '')
            . "\nНАЗНАЧЕНИЕ ШАБЛОНА:\n{$purpose}\n\n"
            . "ДОСТУПНЫЕ ТИПЫ БЛОКОВ:\n{$typeList}\n\n"
            . TemplatePrompt::SINGLE_RULES . "\n\n"
            . TemplatePrompt::SINGLE_RESPONSE_FORMAT;
    }

    private function formatProfileContext(array $profile): string {
        $ctx = '';
        if (!empty($profile['niche']))       $ctx .= "Ниша: {$profile['niche']}\n";
        if (!empty($profile['brand_name']))  $ctx .= "Бренд: {$profile['brand_name']}\n";
        if (!empty($profile['tone']))        $ctx .= "Тон: {$profile['tone']}\n";
        if (!empty($profile['gpt_persona'])) $ctx .= "Персона контента: {$profile['gpt_persona']}\n";
        if (!empty($profile['gpt_rules']))   $ctx .= "Правила: {$profile['gpt_rules']}\n";
        if (!empty($profile['description'])) $ctx .= "Описание проекта: {$profile['description']}\n";
        return $ctx;
    }

    /**
     * Render content_brief JSON into a human-readable block feeding richer context to GPT.
     * Returns empty string when brief is missing.
     */
    private function formatBriefContext(array $profile): string {
        $brief = $profile['content_brief'] ?? null;
        if (is_string($brief) && $brief !== '') {
            $brief = json_decode($brief, true);
        }
        if (!is_array($brief) || empty($brief)) {
            return '';
        }

        $lines = [];

        $audience = $brief['audience'] ?? null;
        if (is_array($audience) && !empty($audience['label'])) {
            $lines[] = "АУДИТОРИЯ: {$audience['label']}"
                . (!empty($audience['demographics']) ? " — {$audience['demographics']}" : '');
            if (!empty($audience['pains']))    $lines[] = 'Боли: ' . implode('; ', (array)$audience['pains']);
            if (!empty($audience['goals']))    $lines[] = 'Цели: ' . implode('; ', (array)$audience['goals']);
            if (!empty($audience['triggers'])) $lines[] = 'Триггер поиска: ' . $audience['triggers'];
        }

        $usps = $brief['usps'] ?? [];
        if (is_array($usps) && $usps) {
            $lines[] = 'УТП (использовать в hint и system_prompt):';
            foreach (array_slice($usps, 0, 5) as $i => $u) {
                if (!is_array($u)) continue;
                $line = '- ' . ($u['headline'] ?? '');
                if (!empty($u['proof']))          $line .= ' (подтверждение: ' . $u['proof'] . ')';
                if (!empty($u['differentiator'])) $line .= ' [отличие: ' . $u['differentiator'] . ']';
                $lines[] = $line;
            }
        }

        $competitors = $brief['competitors'] ?? [];
        if (is_array($competitors) && $competitors) {
            $lines[] = 'КОНКУРЕНТЫ И УГЛЫ ОТСТРОЙКИ:';
            foreach (array_slice($competitors, 0, 4) as $c) {
                if (!is_array($c)) continue;
                $lines[] = '- ' . ($c['name'] ?? 'архетип')
                    . (!empty($c['weaknesses']) ? ' (слабости: ' . implode(', ', (array)$c['weaknesses']) . ')' : '')
                    . (!empty($c['angle']) ? ' → угол: ' . $c['angle'] : '');
            }
        }

        $voice = $brief['voice'] ?? null;
        if (is_array($voice) && !empty($voice['archetype'])) {
            $lines[] = 'VOICE: ' . ($voice['label'] ?? $voice['archetype']) . " ({$voice['archetype']})";
            if (!empty($voice['sample_explanation'])) $lines[] = 'Образец стиля: «' . $voice['sample_explanation'] . '»';
            if (!empty($voice['vocabulary_hints']))   $lines[] = 'Характерные обороты: ' . implode(', ', (array)$voice['vocabulary_hints']);
        }

        $rules = $brief['rules'] ?? [];
        if (is_array($rules)) {
            $do = $rules['do'] ?? [];
            if (is_array($do) && $do) {
                $lines[] = 'DO (соблюдать):';
                foreach (array_slice($do, 0, 6) as $r) {
                    $lines[] = '- ' . (is_array($r) ? ($r['rule'] ?? '') : (string)$r);
                }
            }
            $dont = $rules['dont'] ?? [];
            if (is_array($dont) && $dont) {
                $lines[] = "DON'T (запрещено):";
                foreach (array_slice($dont, 0, 6) as $r) {
                    $lines[] = '- ' . (is_array($r) ? ($r['rule'] ?? '') : (string)$r);
                }
            }
        }

        $compliance = $brief['compliance'] ?? null;
        if (is_array($compliance)) {
            if (!empty($compliance['forbidden_claims'])) {
                $phrases = [];
                foreach ((array)$compliance['forbidden_claims'] as $c) {
                    $phrases[] = is_array($c) ? ($c['phrase'] ?? '') : (string)$c;
                }
                $phrases = array_filter($phrases);
                if ($phrases) {
                    $lines[] = 'COMPLIANCE — ЗАПРЕЩЁННЫЕ ФОРМУЛИРОВКИ (дословно НЕ использовать):';
                    foreach (array_slice($phrases, 0, 8) as $p) $lines[] = '- «' . $p . '»';
                }
            }
            if (!empty($compliance['required_disclaimers'])) {
                $lines[] = 'ОБЯЗАТЕЛЬНЫЕ ОГОВОРКИ: ' . implode(' | ', (array)$compliance['required_disclaimers']);
            }
        }

        $evidence = $brief['evidence'] ?? null;
        if (is_array($evidence) && !empty($evidence['data_sources'])) {
            $lines[] = 'РАЗРЕШЁННЫЕ ИСТОЧНИКИ ДАННЫХ: ' . implode(', ', (array)$evidence['data_sources']);
        }

        return implode("\n", $lines);
    }

    private function buildSingleTemplateUserPrompt(array $profile, string $purpose, ?string $hints = null): string {
        $parts = [sprintf(TemplatePrompt::USER_CREATE_TEMPLATE, $purpose)];

        if ($hints !== null && trim($hints) !== '') {
            $parts[] = "Дополнительные подсказки от менеджера: {$hints}";
        }

        $parts[] = TemplatePrompt::USER_PICK_BLOCKS;

        return implode("\n\n", $parts);
    }

    private function buildReviewPrompt(array $template, array $profile, array $blockTypes): string {
        $tplJson = json_encode($template, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $typeList = $this->formatBlockTypeList($blockTypes);

        $profileContext = '';
        if (!empty($profile['niche'])) {
            $profileContext .= "Ниша: {$profile['niche']}\n";
        }
        if (!empty($profile['tone'])) {
            $profileContext .= "Тон: {$profile['tone']}\n";
        }

        return TemplatePrompt::REVIEWER_ROLE . "\n\n"
            . "ПРОФИЛЬ:\n{$profileContext}\n\n"
            . "ДОСТУПНЫЕ ТИПЫ БЛОКОВ:\n{$typeList}\n\n"
            . "ШАБЛОН НА РЕВЬЮ:\n{$tplJson}\n\n"
            . TemplatePrompt::REVIEW_CRITERIA . "\n\n"
            . TemplatePrompt::REVIEW_RESPONSE_FORMAT . "\n\n"
            . TemplatePrompt::REVIEW_INSTRUCTION;
    }

    private function formatBlockTypeList(array $blockTypes): string {
        $typeList = '';
        foreach ($blockTypes as $bt) {
            $typeList .= "- `{$bt['code']}` ({$bt['category']}): {$bt['display_name']}";
            if (!empty($bt['description'])) {
                $typeList .= " — {$bt['description']}";
            }
            $typeList .= "\n";
            if (!empty($bt['gpt_hint'])) {
                $typeList .= "  Структура: {$bt['gpt_hint']}\n";
            }
        }
        return $typeList;
    }

    private function sendSSE(string $event, array $data): void {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    private function addUsage(array &$total, array $usage): void {
        foreach (['prompt_tokens', 'completion_tokens', 'total_tokens'] as $k) {
            $total[$k] += ($usage[$k] ?? 0);
        }
    }

    private function buildSystemPrompt(array $profile, array $blockTypes, int $count): string {
        $typeList = $this->formatBlockTypeList($blockTypes);
        $briefContext = $this->formatBriefContext($profile);

        return "Ты — архитектор SEO-шаблонов. Твоя задача — спроектировать {$count} шаблонов статей для конкретной ниши.\n\n"
            . "Каждый шаблон — это набор блоков, которые будут заполнены контентом через GPT.\n\n"
            . TemplatePrompt::ANTI_GENERIC . "\n\n"
            . ($briefContext !== '' ? "ДЕТАЛЬНЫЙ БРИФ ПРОЕКТА:\n{$briefContext}\n\n" : '')
            . "ДОСТУПНЫЕ ТИПЫ БЛОКОВ:\n{$typeList}\n\n"
            . TemplatePrompt::PROPOSAL_RULES . "\n\n"
            . TemplatePrompt::PROPOSAL_RESPONSE_FORMAT;
    }

    private function buildUserPrompt(array $profile, string $nicheDescription, int $count): string {
        $parts = ["Ниша: {$nicheDescription}"];

        if (!empty($profile['niche']))      $parts[] = "Тематика профиля: {$profile['niche']}";
        if (!empty($profile['brand_name'])) $parts[] = "Бренд: {$profile['brand_name']}";
        if (!empty($profile['tone']))       $parts[] = "Тон: {$profile['tone']}";
        if (!empty($profile['gpt_persona'])) $parts[] = "Персона: {$profile['gpt_persona']}";

        $parts[] = sprintf(TemplatePrompt::USER_PROPOSAL_CREATE, $count);
        $parts[] = TemplatePrompt::USER_PROPOSAL_COVERAGE;

        return implode("\n", $parts);
    }

    private function loadProfile(int $id): array {
        $row = $this->db->fetchOne(
            "SELECT * FROM " . SeoSiteProfile::TABLE . " WHERE id = ? AND is_active = 1",
            [$id]
        );
        if (!$row) throw new RuntimeException("Профиль #{$id} не найден или неактивен");
        return $row;
    }

    private function loadActiveBlockTypes(): array {
        return $this->db->fetchAll(
            "SELECT code, display_name, description, category, gpt_hint FROM " . SeoBlockType::TABLE
            . " WHERE is_active = 1 ORDER BY category, sort_order"
        );
    }

    private function slugify(string $text): string {
        $translitMap = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh',
            'з'=>'z','и'=>'i','й'=>'j','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o',
            'п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'kh','ц'=>'ts',
            'ч'=>'ch','ш'=>'sh','щ'=>'shch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
        ];

        $slug = mb_strtolower($text);
        $slug = strtr($slug, $translitMap);
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = preg_replace('/-{2,}/', '-', $slug);
        return trim($slug, '-');
    }

    private function generateUniqueSlug(string $base): string {
        $slug = $base;
        $suffix = 1;
        while ($this->db->fetchOne("SELECT id FROM " . SeoTemplate::TABLE . " WHERE slug = ?", [$slug])) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }
        return $slug;
    }
}
