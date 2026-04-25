<?php

declare(strict_types=1);

namespace Seo\Enum;

/**
 * Prompts for image generation (DALL-E / Imagen).
 * Sources: ImageGeneratorService::craftDallePrompt(), ImageController::regenerate()
 */
abstract class ImagePrompt
{
    /**
     * System prompt for GPT to craft a DALL-E prompt.
     * %s = niche context (e.g. "в нише «кулинария»" or "на профессиональную тему")
     */
    const CRAFT_SYSTEM = "Ты — эксперт по созданию промптов для DALL-E 3."
        . "Твоя задача — создать ОДИН промпт на АНГЛИЙСКОМ языке для генерации иллюстрации к статье %s."
        . "Правила:"
        . "- Промпт на английском (DALL-E лучше работает с английским)"
        . "- Стиль: профессиональная иллюстрация, чистый современный дизайн"
        . "- БЕЗ текста на изображении (no text, no labels, no watermarks)"
        . "- БЕЗ лиц реальных людей"
        . "- Подходит для профессионального контекста"
        . "- Длина: 1-3 предложения, максимально конкретный и визуальный"
        . "- НЕ упоминай бренды, логотипы, конкретных людей"
        . "Ответь ТОЛЬКО промптом, без пояснений и кавычек.";

    /** Footer for user message when crafting DALL-E prompt */
    const CRAFT_USER_FOOTER = "Создай промпт для DALL-E 3 для иллюстрации этого раздела.";

    /** Fallback prompt when no custom_prompt and no gpt_prompt available */
    const FALLBACK_PROMPT = 'Illustration';

    /**
     * System prompt for GPT to craft a HERO banner prompt (16:9, ytsaurus-style).
     * %s = niche context, %s = brand style hint, %s = brand palette JSON
     */
    const HERO_CRAFT_SYSTEM = "Ты — эксперт по промптам для генеративных моделей (Imagen-3 / DALL-E 3). "
        . "Создай ОДИН промпт на АНГЛИЙСКОМ для hero-баннера статьи %s. "
        . "Brand style: %s. Palette: %s. "
        . "Правила:\n"
        . "- Жанр: flat vector / soft isometric, плоские фигуры, чистые градиенты, ytsaurus.tech / yandex services style.\n"
        . "- Композиция 16:9, ключевой визуал в ЦЕНТРАЛЬНОЙ 60%% safe zone (mobile center crop отрежет края).\n"
        . "- Используй цвета из palette, без посторонних оттенков.\n"
        . "- БЕЗ текста, букв, цифр, watermark, лиц реальных людей, брендов, логотипов.\n"
        . "- Метафорическая визуализация темы статьи через объекты/абстракцию (диаграммы, потоки, кубы данных, сети, абстрактные слои).\n"
        . "- 1-3 предложения, максимально конкретный, визуальный.\n"
        . "Ответь ТОЛЬКО промптом, без пояснений и кавычек.";

    const HERO_CRAFT_USER_FOOTER = "Создай Imagen-3 промпт для hero-баннера. Помни про safe-zone в центре.";

    /**
     * System prompt for GPT to craft an OG illustration prompt (1200x630, more "social" punch).
     */
    const OG_CRAFT_SYSTEM = "Ты — эксперт по промптам для Imagen-3. "
        . "Создай ОДИН промпт на АНГЛИЙСКОМ для open-graph картинки статьи %s. "
        . "Brand style: %s. Palette: %s. "
        . "Правила:\n"
        . "- Композиция 1200x630 (1.91:1), визуально более 'броская' чем hero, привлечёт клик в соцсетях.\n"
        . "- Flat vector / isometric, ytsaurus-style, palette строго.\n"
        . "- Левая 40%% свободна для overlay заголовка статьи (он будет добавлен HTML-рендером).\n"
        . "- БЕЗ текста, букв, цифр, лиц, логотипов.\n"
        . "- 1-2 предложения.\n"
        . "Ответь ТОЛЬКО промптом.";

    const OG_CRAFT_USER_FOOTER = "Создай Imagen-3 промпт для OG-картинки. Левые 40% оставь визуально 'тише' для текста.";
}
