<?php

declare(strict_types=1);

namespace Seo\Enum;

/**
 * Prompts for template generation, review, and proposal.
 * Sources: TemplateGeneratorService
 */
abstract class TemplatePrompt
{
    /** Architect role for single-template generation */
    const ARCHITECT_ROLE = "Ты — архитектор SEO-шаблонов. Твоя задача — спроектировать ОДИН шаблон статьи для конкретного типа контента.";

    /** Rules for single-template generation */
    const SINGLE_RULES = <<<'EOT'
ПРАВИЛА:
1. Шаблон ДОЛЖЕН начинаться с `hero` и заканчиваться `cta`.
2. Используй 5-10 блоков в зависимости от сложности типа статьи.
3. gpt_system_prompt — подробная инструкция для GPT при генерации контента по этому шаблону (3-5 предложений).
4. hint для каждого блока — детальная подсказка GPT что именно генерировать в этом блоке с учётом назначения шаблона.
5. Подбирай блоки, которые максимально подходят для данного типа статьи.
6. Имена, описания и промпты — на русском языке.
7. slug — латиницей, через дефис.
EOT;

    /** JSON response format for single-template generation */
    const SINGLE_RESPONSE_FORMAT = <<<'EOT'
ФОРМАТ ОТВЕТА (строго JSON):
{
  "template": {
    "name": "Название шаблона",
    "slug": "template-slug",
    "description": "Описание назначения шаблона — для какого типа статей",
    "css_class": "tpl-slug",
    "gpt_system_prompt": "Подробный системный промпт для GPT при генерации статьи по этому шаблону...",
    "blocks": [
      {
        "type": "hero",
        "name": "Название блока",
        "hint": "Детальная подсказка GPT для генерации этого блока",
        "fields": ["title", "subtitle"],
        "sort_order": 1,
        "is_required": true
      }
    ]
  }
}
EOT;

    /** Rules for multi-template proposal */
    const PROPOSAL_RULES = <<<'EOT'
ПРАВИЛА:
1. Каждый шаблон ДОЛЖЕН начинаться с `hero` и заканчиваться `cta`.
2. Используй 5-8 блоков на шаблон.
3. Каждый шаблон должен быть уникальным по структуре и назначению.
4. gpt_system_prompt — инструкция для GPT при генерации контента по этому шаблону.
5. hint для каждого блока — подсказка GPT что генерировать в этом блоке.
6. Имена, описания и промпты — на русском языке.
7. slug — латиницей, через дефис.
EOT;

    /** JSON response format for multi-template proposal */
    const PROPOSAL_RESPONSE_FORMAT = <<<'EOT'
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
EOT;

    /** Reviewer role */
    const REVIEWER_ROLE = "Ты — ревьюер SEO-шаблонов. Проверь качество шаблона и предложи улучшения.";

    /** Review evaluation criteria */
    const REVIEW_CRITERIA = <<<'EOT'
КРИТЕРИИ ОЦЕНКИ:
1. Логичность структуры блоков (порядок, необходимость каждого блока)
2. Качество gpt_system_prompt (достаточно ли подробный, учитывает ли нишу)
3. Качество hint для каждого блока (конкретность, полезность для GPT)
4. Покрытие нужных аспектов типа статьи
5. Нет ли лишних или недостающих блоков
EOT;

    /** JSON response format for review */
    const REVIEW_RESPONSE_FORMAT = <<<'EOT'
ФОРМАТ ОТВЕТА (строго JSON):
{
  "score": 8,
  "suggestions": ["Замечание 1", "Замечание 2"],
  "improved_template": {
    "name": "...",
    "slug": "...",
    "description": "...",
    "css_class": "...",
    "gpt_system_prompt": "Улучшенный промпт...",
    "blocks": [...]
  }
}
EOT;

    /** Review closing instruction */
    const REVIEW_INSTRUCTION = "Если шаблон хорош — поставь высокую оценку и верни его без изменений в improved_template.\nЕсли есть что улучшить — улучши и верни исправленную версию.";

    /** User prompt: create template for purpose. %s = purpose */
    const USER_CREATE_TEMPLATE = "Создай шаблон для следующего типа статьи: %s";

    /** User prompt: pick suitable blocks */
    const USER_PICK_BLOCKS = 'Подбери блоки, которые лучше всего подходят для этого типа контента. Для каждого блока напиши подробный hint.';

    /** User prompt: create N templates. %d = count */
    const USER_PROPOSAL_CREATE = "Создай %d шаблонов статей, подходящих для этой ниши.";

    /** User prompt: coverage instruction */
    const USER_PROPOSAL_COVERAGE = 'Шаблоны должны покрывать разные типы контента: информационный, сравнительный, обзорный, руководство и т.д.';

    /** User prompt: review instruction */
    const USER_DO_REVIEW = 'Проведи ревью шаблона и предложи улучшения.';
}
