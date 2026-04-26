<?php

declare(strict_types=1);

namespace Seo\Enum;

/**
 * Prompts for article research dossier generation.
 *
 * Three pipelines:
 *   - single (legacy): SYSTEM + FORMAT + USER_TEMPLATE — one big call.
 *   - split: SYSTEM_OUTLINE → cheap list of sub-questions, then SYSTEM_FILL per section.
 *   - split_search: split + web_search tool for benchmarks/sources phases.
 *
 * Output is structured JSON — items have stable IDs that outline.source_facts must reference.
 * Token usage logged under TokenUsageLogger::CATEGORY_ARTICLE_RESEARCH.
 */
abstract class ResearchPrompt
{
    public const SYSTEM = "Ты — исследователь-аналитик. Собираешь досье фактов для последующего написания статьи."
        . " Пишешь на русском. Ответ — ТОЛЬКО валидный JSON по заданной схеме, без markdown, без преамбул."
        . "\n\nПравила:"
        . "\n1. Каждый факт — конкретный, с цифрами/механизмом, без воды и общих фраз."
        . "\n2. Если факт нельзя подтвердить — НЕ включай его в массив (лучше пусто, чем выдумка)."
        . "\n3. Каждый item имеет уникальный id с префиксом своего типа: f1,f2 (facts), b1,b2 (benchmarks),"
        . " c1,c2 (comparisons), ct1 (counter_theses), q1 (quotes_cases), t1 (terms), e1 (entities), src1 (sources)."
        . "\n4. Не используй маркетинговые штампы («революционный», «уникальный», «современный»)."
        . "\n5. Числа всегда с единицей и условиями (что, сколько, при каких условиях)."
        . "\n6. Сравнения — только по измеримым осям (axes — массив строк-осей)."
        . "\n7. Не дублируй информацию между секциями — каждый item уникален."
        . "\n8. angle — единый якорь 1-2 предложения, который будет держать всю статью."
        . "\n9. Минимумы: facts ≥ 8, benchmarks ≥ 3, comparisons ≥ 1, counter_theses ≥ 2, terms ≥ 3, entities ≥ 3."
        . "\n10. Поле source у facts/benchmarks/sources может быть null если источника нет.";

    public const FORMAT = <<<JSON
{
  "title": "...",
  "angle": "1-2 предложения — конкретный вопрос, на который отвечает статья",
  "audience": "кто читатель и его уровень",
  "facts": [
    {"id":"f1","claim":"тезис","evidence":"обоснование/механизм/число","source":"url|null"}
  ],
  "entities": [
    {"id":"e1","name":"...","definition":"одна строка"}
  ],
  "benchmarks": [
    {"id":"b1","metric":"...","value":"число с единицей","conditions":"при каких условиях","source":"url|null"}
  ],
  "comparisons": [
    {"id":"c1","x":"...","y":"...","axes":["ось1","ось2"],"summary":"X=..., Y=... в чём отличие"}
  ],
  "counter_theses": [
    {"id":"ct1","thesis":"тезис","objection":"возражение/когда не работает"}
  ],
  "quotes_cases": [
    {"id":"q1","kind":"quote|case","text":"...","attribution":"автор или компания"}
  ],
  "terms": [
    {"id":"t1","term":"...","definition":"..."}
  ],
  "open_questions": ["вопрос 1", "вопрос 2"],
  "sources": [
    {"id":"src1","url":"https://...","title":"..."}
  ]
}
JSON;

    /** Backwards-compat alias — service code historically referenced SKELETON. */
    public const SKELETON = self::FORMAT;

    public const USER_TEMPLATE = "Тема статьи: «%s»\n%s%s\nЗаполни досье по схеме ниже. Соблюдай минимумы, уникальные id, правила.\n\nСхема:\n%s";

    // ─── Phase 1: outline (split / split_search) ───

    public const SYSTEM_OUTLINE = "Ты — research-планировщик. По теме статьи формулируешь список конкретных под-вопросов,"
        . " на которые нужно ответить, чтобы написать качественную фактологическую статью."
        . "\n\nПравила:"
        . "\n1. Возвращай ТОЛЬКО валидный JSON по схеме."
        . "\n2. Под-вопросы — конкретные, проверяемые, с количественной/механизмной природой ответа."
        . "\n3. Покрывай все секции досье: facts, entities, benchmarks, comparisons, counter_theses, quotes_cases, terms."
        . "\n4. Не дублируй формулировки между секциями."
        . "\n5. На каждую секцию — 2-4 вопроса. Всего 14-20 вопросов.";

    public const OUTLINE_FORMAT = <<<JSON
{
  "title": "...",
  "angle": "1-2 предложения — конкретный угол статьи",
  "audience": "целевая аудитория",
  "questions": {
    "facts":          ["под-вопрос 1", "под-вопрос 2"],
    "entities":       ["..."],
    "benchmarks":     ["..."],
    "comparisons":    ["..."],
    "counter_theses": ["..."],
    "quotes_cases":   ["..."],
    "terms":          ["..."]
  }
}
JSON;

    public const USER_OUTLINE_TEMPLATE = "Тема статьи: «%s»\n%s%s\nСформулируй план под-вопросов по схеме ниже.\n\nСхема:\n%s";

    // ─── Phase 2: fill (split / split_search) ───

    public const SYSTEM_FILL = "Ты — исследователь-фактограф. Получаешь список под-вопросов по одной секции досье"
        . " и заполняешь только эту секцию. Каждый item — конкретный, проверяемый, с числами и условиями."
        . "\n\nПравила:"
        . "\n1. ТОЛЬКО валидный JSON по схеме секции, без markdown, без преамбул."
        . "\n2. Если ответ невозможно подтвердить — пропусти item (лучше меньше, чем выдумка)."
        . "\n3. Уникальные id с префиксом секции (f1,b1,c1,ct1,q1,t1,e1,src1)."
        . "\n4. Никаких штампов («революционный», «уникальный», «в современном мире»)."
        . "\n5. Числа всегда с единицей и условиями."
        . "\n6. Не дублируй формулировки между item'ами.";

    /** Per-section schemas to be injected into FILL prompt. */
    public const FILL_SECTION_SCHEMAS = [
        'facts'          => '{"items":[{"id":"f1","claim":"тезис","evidence":"обоснование/механизм/число","source":"url|null"}]}',
        'entities'       => '{"items":[{"id":"e1","name":"...","definition":"одна строка"}]}',
        'benchmarks'     => '{"items":[{"id":"b1","metric":"...","value":"число с единицей","conditions":"при каких условиях","source":"url|null"}]}',
        'comparisons'    => '{"items":[{"id":"c1","x":"...","y":"...","axes":["ось1","ось2"],"summary":"кратко в чём отличие"}]}',
        'counter_theses' => '{"items":[{"id":"ct1","thesis":"тезис","objection":"возражение/когда не работает"}]}',
        'quotes_cases'   => '{"items":[{"id":"q1","kind":"quote|case","text":"...","attribution":"автор/компания"}]}',
        'terms'          => '{"items":[{"id":"t1","term":"...","definition":"..."}]}',
        'sources'        => '{"items":[{"id":"src1","url":"https://...","title":"..."}]}',
    ];

    public const USER_FILL_TEMPLATE = "Тема статьи: «%s»\nУгол: %s\nСекция: %s\n\nПод-вопросы:\n%s\n\nЗаполни массив items по схеме (ровно по одному item на под-вопрос там, где это возможно):\n%s";
}
