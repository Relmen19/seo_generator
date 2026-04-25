<?php

declare(strict_types=1);

namespace Seo\Enum;

/**
 * Prompts for article outline generation.
 *
 * Outline = structured plan replacing flat article_plan.
 * Each section has narrative role, target block_type, content brief
 * and explicit links to research dossier facts.
 *
 * Output: strict JSON {sections: [...]}.
 */
abstract class OutlinePrompt
{
    const SYSTEM = "Ты — редактор-архитектор статьи. Строишь сквозной нарратив, а не набор виджетов."
        . "\n\nЗадача: на основе досье фактов разложить статью на секции так, чтобы они образовали"
        . " единый рассказ — каждая секция продолжает предыдущую и опирается на факты досье."
        . "\n\nОтвет — ТОЛЬКО валидный JSON, без markdown, без комментариев, без преамбул."
        . "\n\nПравила:"
        . "\n1. Каждая секция должна иметь narrative_role — её драматургическую функцию в статье."
        . "\n2. Допустимые narrative_role: hook | problem | deep_dive | tradeoff | benchmark | faq | cta."
        . "\n3. block_type — ТОЛЬКО из списка доступных типов, который дан в user-сообщении."
        . "\n4. source_facts НЕ ПУСТОЙ — ссылки на пункты досье (короткие цитаты/идентификаторы пунктов)."
        . "\n5. content_brief — что именно говорится в секции (3-6 предложений), а не общее описание."
        . "\n6. h2_title — конкретный заголовок секции, не абстрактный («Что такое X», а не «Введение»)."
        . "\n7. id — короткий slug (s1, s2, ...) для последующих ссылок."
        . "\n8. Секций обычно 5-10. Открывает hook, закрывает cta или faq."
        . "\n9. Не дублируй темы между секциями. Каждая секция — новый угол.";

    const FORMAT = <<<JSON
{
  "sections": [
    {
      "id": "s1",
      "h2_title": "...",
      "narrative_role": "hook|problem|deep_dive|tradeoff|benchmark|faq|cta",
      "block_type": "<one of allowed types>",
      "content_brief": "Что говорит секция: 3-6 предложений по сути.",
      "source_facts": ["краткая ссылка на факт 1 из досье", "..."]
    }
  ]
}
JSON;

    const USER_TEMPLATE = "Тема статьи: «%s»\n%s%s\nДоступные типы блоков (block_type выбирать ТОЛЬКО из них):\n%s\n\nДосье фактов (используй как опору для source_facts):\n\n%s\n\nВерни JSON строго в формате:\n%s";
}
