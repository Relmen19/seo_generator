<?php

declare(strict_types=1);

namespace Seo\Enum;

/**
 * Prompts for article outline generation.
 *
 * Outline = structured plan replacing flat article_plan.
 * Each section has narrative role, target block_type, content brief
 * and explicit references to research dossier item IDs (f1, b2, c1, ...).
 *
 * Output: strict JSON {sections: [...]}.
 */
abstract class OutlinePrompt
{
    const SYSTEM = "Ты — редактор-архитектор статьи. Строишь сквозной нарратив, а не набор виджетов."
        . "\n\nЗадача: на основе досье фактов разложить статью на секции так, чтобы они образовали"
        . " единый рассказ — каждая секция продолжает предыдущую и опирается на конкретные пункты досье."
        . "\n\nОтвет — ТОЛЬКО валидный JSON, без markdown, без комментариев, без преамбул."
        . "\n\nПравила:"
        . "\n1. Каждая секция должна иметь narrative_role — её драматургическую функцию в статье."
        . "\n2. Допустимые narrative_role: hook | problem | deep_dive | tradeoff | benchmark | faq | cta."
        . "\n3. block_type — ТОЛЬКО из списка доступных типов, который дан в user-сообщении."
        . "\n4. source_facts — массив ID из досье (например [\"f3\",\"b1\",\"c2\"]). Минимум 1 ID на секцию."
        . " ЗАПРЕЩЕНО ссылаться на ID, которых нет в досье. ЗАПРЕЩЕНО придумывать факты вне досье."
        . "\n5. content_brief — что именно говорится в секции (3-6 предложений по сути), с упоминанием"
        . " конкретных фактов из досье через описания (не ID)."
        . "\n6. h2_title — конкретный заголовок секции, не абстрактный («Что такое X», а не «Введение»)."
        . "\n7. id — короткий slug секции (s1, s2, ...) для последующих ссылок."
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
      "source_facts": ["f1", "b2", "c1"]
    }
  ]
}
JSON;

    const USER_TEMPLATE = "Тема статьи: «%s»\n%s%s\nДоступные типы блоков (block_type выбирать ТОЛЬКО из них):\n%s\n\nЯкорь статьи: %s\n\nДосье — пункты с ID, на которые надо ссылаться в source_facts:\n\n%s\n\nВерни JSON строго в формате:\n%s";
}
