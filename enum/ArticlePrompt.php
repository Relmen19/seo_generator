<?php

declare(strict_types=1);

namespace Seo\Enum;

/**
 * Prompts for article content generation.
 * Sources: PromptBuilder
 */
abstract class ArticlePrompt
{
    /** Default SEO copywriter persona */
    const DEFAULT_PERSONA = "Ты — профессиональный SEO-копирайтер. JSON-формат. Профессиональный, но доступный стиль. Конкретные факты, данные, примеры.";

    /** Backwards-compatible format example — only original 5 types (default). */
    const RICHTEXT_FORMAT_BASIC = '{"blocks":[{"type":"heading","text":"...","level":2},{"type":"paragraph","text":"..."},{"type":"list","items":["..."]},{"type":"highlight","text":"..."},{"type":"quote","text":"..."}]}';

    /** JSON format example for richtext blocks (long-form: ytsaurus.tech style) */
    const RICHTEXT_FORMAT = '{"blocks":['
        . '{"type":"heading","text":"...","level":2},'
        . '{"type":"paragraph","text":"... с `inline_code`, **жирным**, *курсивом*, [ссылкой](https://...)"},'
        . '{"type":"list","items":["..."]},'
        . '{"type":"quote","text":"...","author":"...","source":"..."},'
        . '{"type":"callout","variant":"info|warn|tip|danger","text":"..."},'
        . '{"type":"code","lang":"python|bash|sql|js|go|yaml|json","code":"..."},'
        . '{"type":"figure","image_url":"https://...","caption":"...","alt":"..."},'
        . '{"type":"table","headers":["..."],"rows":[["..."]]},'
        . '{"type":"footnote","id":"1","text":"..."},'
        . '{"type":"highlight","text":"..."}'
        . ']}';

    /** Backwards-compatible rules — only original 5 types (default). */
    const RICHTEXT_RULES_BASIC = 'type "list" → "items"(массив строк). type "heading" → "text"+"level"(2/3). Остальные → "text"(строка). Мин. 6 подблоков, чередуй типы.';

    /** Long-form rules — used only when template opts in via block_types. */
    const RICHTEXT_RULES =
          'list → items(массив строк). heading → text+level(2/3). '
        . 'quote → text(+author, source опционально). '
        . 'callout → variant(info|warn|tip|danger)+text. '
        . 'code → lang+code(многострочный). '
        . 'figure → image_url+caption+alt (или image_id если есть). '
        . 'table → headers(массив)+rows(массив массивов строк). '
        . 'footnote → id(строка)+text. Ссылка на сноску в paragraph через [^id]. '
        . 'paragraph/highlight → text(строка). '
        . 'Inline в paragraph/list/quote/callout: `code`, **bold**, *italic*, [text](url). '
        . 'Мин. 8 подблоков, чередуй типы. Для технической темы — мин. 1 code ИЛИ table ИЛИ figure. '
        . 'callout не чаще 1 на 3 параграфа. inline `code` для команд, имён файлов, типов, флагов.';

    /** Warning: do not wrap richtext in outer object */
    const RICHTEXT_NO_WRAP = "НЕ оборачивай в {\"type\":\"richtext\",\"content\":[...]}!\n";

    /** Base content requirements for system message */
    const SYSTEM_REQUIREMENTS = "Требования:\n"
        . "1. Контент подробный, с фактами и конкретикой. Никаких общих фраз.\n"
        . "2. Списки — мин. 4 пункта с пояснениями. Параграфы — мин. 3 предложения.\n"
        . "3. Каждый блок содержит ТОЛЬКО свою информацию. Не дублируй данные между блоками.\n"
        . "4. Заголовки визуальных блоков — понятные целевой аудитории.\n";

    /** Meta planning instruction (system message part) */
    const META_PLAN_INSTRUCTION = " Спланируй структуру статьи как единый рассказ.\n\n"
        . "Ответ — строго JSON:\n"
        . "{\"meta_title\":\"60-70 символов\",\"meta_description\":\"150-160 символов\",\"meta_keywords\":\"5-10 слов через запятую\",\"article_plan\":\"до 1000 символов\"}\n\n"
        . "article_plan — пошаговый редакторский план, НЕ список блоков.\n"
        . "Формат: [Тип] Конкретное содержание → [Тип] Содержание → ...\n"
        . "Каждый блок логически вытекает из предыдущего. Конкретика по теме, не переименование блоков.";

    /** User-side suffix for meta planning */
    const META_USER_PLAN_SUFFIX = "Составь план: для каждого блока — конкретное содержание по теме «%s».";

    /** Full article format instructions */
    const FULL_ARTICLE_FORMAT = "\n\nФОРМАТ: JSON {\"block_0\":{...}, \"block_1\":{...}, ...}. Все блоки в одном JSON.\n"
        . "Страница — единый нарратив. Каждый блок логически вытекает из предыдущего.\n"
        . "АНТИДУБЛЯЖ: Блоки НЕ повторяют информацию друг друга. Если данные уже есть в одном блоке — в другом их НЕТ.\n"
        . "АНТИ-ОБЁРТКА: НЕ оборачивай содержимое блока в объект с именем типа. НЕПРАВИЛЬНО: \"block_0\":{\"comparison_table\":{\"headers\":[...]}}. ПРАВИЛЬНО: \"block_0\":{\"headers\":[...],\"rows\":[...]}. Поля блока — сразу в block_N. То же для \"content\", \"data\", \"fields\" как обёрток.\n"
        . "ОБЯЗАТЕЛЬНЫЙ title: у каждого блока, где поле title присутствует в схеме — title ДОЛЖЕН быть заполнен и соответствовать теме статьи. Не пропускай title.";

    /** User prompt: generate content for block. %s(1) = type, %s(2) = name */
    const BLOCK_USER_GENERATE = "Сгенерируй контент для [%s] «%s».";

    /** User prompt: response must be JSON */
    const BLOCK_USER_RESPONSE = "Ответ — JSON-объект.";
}
