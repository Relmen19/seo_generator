<?php

declare(strict_types=1);

namespace Seo\Service;

/**
 * Value object returned by TelegramBlockFormatterService.php::format().
 *
 * @property-read string     $text     MarkdownV2-ready text for Telegram
 * @property-read array|null $keyboard inline_keyboard structure (or null)
 */
final class TelegramBlock
{
    public function __construct(
        public string $text,
        public ?array $keyboard = null,
    ) {}

    public function isEmpty(): bool
    {
        return trim($this->text) === '' && $this->keyboard === null;
    }

    public static function empty(): self
    {
        return new self('');
    }
}