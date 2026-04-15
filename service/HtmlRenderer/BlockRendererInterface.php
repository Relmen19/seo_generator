<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer;

interface BlockRendererInterface
{
    /**
     * Render block HTML markup.
     */
    public function renderHtml(array $content, string $id): string;

    /**
     * Return CSS rules specific to this block type.
     */
    public function getCss(): string;

    /**
     * Return JavaScript code specific to this block type.
     */
    public function getJs(): string;

    /**
     * Return label for the table of contents.
     */
    public function getTocLabel(array $content, array $meta): string;
}
