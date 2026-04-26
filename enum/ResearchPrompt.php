<?php

declare(strict_types=1);

namespace Seo\Enum;

/**
 * Prompts for article research dossier generation.
 * Output is structured JSON — facts have stable IDs that outline.source_facts must reference.
 * Used as factual base for meta + blocks generation later.
 */
abstract class ResearchPrompt
{
    const SYSTEM = "Ты — исследователь-аналитик. Собираешь досье фактов для последующего написания статьи."
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

    const FORMAT = <<<JSON
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

    const USER_TEMPLATE = "Тема статьи: «%s»\n%s%s\nЗаполни досье по схеме ниже. Соблюдай минимумы, уникальные id, правила.\n\nСхема:\n%s";
}
