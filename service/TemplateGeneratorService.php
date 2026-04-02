<?php

declare(strict_types=1);

namespace Seo\Service;

use RuntimeException;
use Seo\Database;
use Seo\Entity\SeoBlockType;
use Seo\Entity\SeoSiteProfile;
use Seo\Entity\SeoTemplate;
use Seo\Entity\SeoTemplateBlock;

class TemplateGeneratorService {

    private GptClient $gpt;
    private Database $db;

    public function __construct(?GptClient $gpt = null) {
        $this->gpt = $gpt ?? new GptClient();
        $this->db = Database::getInstance();
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

    private function buildSystemPrompt(array $profile, array $blockTypes, int $count): string {
        $typeList = '';
        foreach ($blockTypes as $bt) {
            $typeList .= "- `{$bt['code']}` ({$bt['category']}): {$bt['display_name']}";
            if (!empty($bt['description'])) {
                $typeList .= " — {$bt['description']}";
            }
            $typeList .= "\n";
        }

        return <<<PROMPT
Ты — архитектор SEO-шаблонов. Твоя задача — спроектировать {$count} шаблонов статей для конкретной ниши.

Каждый шаблон — это набор блоков, которые будут заполнены контентом через GPT.

ДОСТУПНЫЕ ТИПЫ БЛОКОВ:
{$typeList}

ПРАВИЛА:
1. Каждый шаблон ДОЛЖЕН начинаться с `hero` и заканчиваться `cta`.
2. Используй 5-8 блоков на шаблон.
3. Каждый шаблон должен быть уникальным по структуре и назначению.
4. gpt_system_prompt — инструкция для GPT при генерации контента по этому шаблону.
5. hint для каждого блока — подсказка GPT что генерировать в этом блоке.
6. Имена, описания и промпты — на русском языке.
7. slug — латиницей, через дефис.

ФОРМАТ ОТВЕТА (строго JSON):
{
  "templates": [
    {
      "name": "Название шаблона",
      "slug": "template-slug",
      "description": "Описание назначения шаблона",
      "css_class": "tpl-slug",
      "gpt_system_prompt": "Системный промпт для GPT...",
      "blocks": [
        {
          "type": "hero",
          "name": "Название блока",
          "hint": "Подсказка GPT для генерации этого блока",
          "fields": ["title", "subtitle"],
          "sort_order": 1,
          "is_required": true
        }
      ]
    }
  ]
}
PROMPT;
    }

    private function buildUserPrompt(array $profile, string $nicheDescription, int $count): string {
        $parts = ["Ниша: {$nicheDescription}"];

        if (!empty($profile['niche'])) {
            $parts[] = "Тематика профиля: {$profile['niche']}";
        }
        if (!empty($profile['brand_name'])) {
            $parts[] = "Бренд: {$profile['brand_name']}";
        }
        if (!empty($profile['tone'])) {
            $parts[] = "Тон: {$profile['tone']}";
        }
        if (!empty($profile['gpt_persona'])) {
            $parts[] = "Персона: {$profile['gpt_persona']}";
        }

        $parts[] = "Создай {$count} шаблонов статей, подходящих для этой ниши.";
        $parts[] = "Шаблоны должны покрывать разные типы контента: информационный, сравнительный, обзорный, руководство и т.д.";

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
            "SELECT code, display_name, description, category FROM " . SeoBlockType::TABLE
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
