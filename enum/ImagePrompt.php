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
}
