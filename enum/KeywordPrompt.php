<?php

declare(strict_types=1);

namespace Seo\Enum;

/**
 * Prompts for keyword collection and clustering.
 * Sources: KeywordCollectorService
 */
abstract class KeywordPrompt
{
    /**
     * System prompt for GPT keyword generation.
     * %s(1) = niche name, %s(2) = pipe-delimited intent codes
     */
    const COLLECT_SYSTEM = "Ты -- SEO-специалист. Генерируй поисковые запросы для сайта в нише «%s».\n\nОтвет -- строго JSON массив:\n[{\"keyword\":\"...\",\"volume_estimate\":число,\"intent\":\"%s\"}]\n\nБез пояснений, только JSON.";

    /**
     * User prompt template for GPT keyword generation.
     * %d = keyword count, %s = seed keyword
     */
    const COLLECT_USER = "Сгенерируй %d поисковых запросов на русском по теме: \"%s\".\n\n"
        . "Требования:\n"
        . "1. Разнообразие интентов: обзоры, сравнения, инструкции, выбор, проблемы и решения, вопросы, транзакционные\n"
        . "2. Long-tail запросы (3-6 слов)\n"
        . "3. Вопросительные формы (как, почему, зачем, можно ли)\n"
        . "4. С модификаторами контекста (для начинающих, для бизнеса, для дома, в 2024)\n"
        . "5. Запросы-проблемы (не работает, как исправить, почему + тема)\n"
        . "6. volume_estimate -- примерная месячная частотность\n\n"
        . "Ответ -- JSON массив.";

    /** Clustering system prompt — intro */
    const CLUSTER_SYSTEM_INTRO = "Ты — профессиональный SEO-специалист с глубоким пониманием поискового намерения пользователей."
        . "\n\nЗадача: кластеризовать список поисковых запросов."
        . "\nЦель кластеризации: 1 кластер = 1 SEO статья, решающая конкретную «боль» пользователя.";

    /** Clustering system prompt — rules */
    const CLUSTER_SYSTEM_RULES = "\n\n══ Правила кластеризации ══"
        . "\n1. Объединяй запросы с ОДИНАКОВЫМ поисковым интентом (одинаковое намерение пользователя)"
        . "\n2. НЕ объединяй запросы, если интент разный, даже если слова похожи"
        . "\n3. Разные формулировки одного смысла = 1 кластер. Пример: 'товар отзывы', 'отзывы о товаре' = 1 кластер"
        . "\n4. Отдельные кластеры для разных интентов: 'что это', 'как выбрать', 'сравнение', 'цена', 'отзывы', 'инструкция'"
        . "\n5. Название кластера = главный SEO запрос (самый популярный и общий)";

    /** Clustering system prompt — intent section header */
    const CLUSTER_SYSTEM_INTENT_HEADER = "\n\n══ Типы интентов (ОБЯЗАТЕЛЬНО назначай один из них каждому кластеру) ══";

    /** Clustering system prompt — important disambiguation rules */
    const CLUSTER_SYSTEM_IMPORTANT = "\n\n══ Важно ══"
        . "\n- Не ставь intent='info' по умолчанию! Внимательно анализируй БОЛЬ пользователя за запросом."
        . "\n- Если человек описывает проблему и ищет причину — это problem_check, не info."
        . "\n- Если ищет 'как сделать/настроить' — это action_plan, не info."
        . "\n- Если ищет 'опасно ли / риски / последствия' — это risk_assessment, не info."
        . "\n- Если запрос привязан к конкретной ситуации (сезон, возраст, условие) — это context."
        . "\n- Если ищет 'как подготовиться / что нужно перед' — это preparation."
        . "\n- info — только для чисто познавательных запросов без конкретной проблемы.";

    /**
     * Clustering response format template.
     * %s = pipe-delimited intent codes
     */
    const CLUSTER_RESPONSE_FORMAT = "\n\nОтвет — строго JSON:"
        . "\n{\"clusters\":[{\"name\":\"...\",\"slug\":\"...\",\"intent\":\"%s\",\"summary\":\"...\",\"article_angle\":\"...\",\"template_id\":1,\"keywords\":[\"запрос1\",\"запрос2\"]}]}"
        . "\n\nНе пропускай запросы. Используй все запросы. Не придумывай новые. Только кластеризация.";

    /**
     * System prompt for merging batches.
     * %s = pipe-delimited intent codes
     */
    const MERGE_SYSTEM = "Объедини похожие кластеры из нескольких батчей.\n\nДопустимые intent: %s\n\nОтвет -- JSON:\n{\"merged\":[{\"keep_names\":[\"Название1\",\"Название2\"],\"final_name\":\"Итоговое название\",\"intent\":\"...\",\"article_angle\":\"...\"}]}";
}
