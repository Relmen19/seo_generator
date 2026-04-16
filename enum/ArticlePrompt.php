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

    /** JSON format example for richtext blocks */
    const RICHTEXT_FORMAT = '{"blocks":[{"type":"heading","text":"...","level":2},{"type":"paragraph","text":"..."},{"type":"list","items":["..."]},{"type":"highlight","text":"..."}]}';

    /** Rules for richtext sub-block types */
    const RICHTEXT_RULES = 'type "list" → "items"(массив строк). type "heading" → "text"+"level"(2/3). Остальные → "text"(строка). Мин. 6 подблоков, чередуй типы.';

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
        . "АНТИДУБЛЯЖ: Блоки НЕ повторяют информацию друг друга. Если данные уже есть в одном блоке — в другом их НЕТ.";

    /** User prompt: generate content for block. %s(1) = type, %s(2) = name */
    const BLOCK_USER_GENERATE = "Сгенерируй контент для [%s] «%s».";

    /** User prompt: response must be JSON */
    const BLOCK_USER_RESPONSE = "Ответ — JSON-объект.";
}
