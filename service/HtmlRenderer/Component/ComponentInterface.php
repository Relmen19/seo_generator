<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Component;

interface ComponentInterface
{
    /**
     * Render component HTML markup.
     * @param array $context Contextual data (article, template, profile, etc.)
     */
    public function renderHtml(array $context): string;

    /**
     * Return CSS rules specific to this component.
     */
    public function getCss(): string;

    /**
     * Return JavaScript code specific to this component.
     */
    public function getJs(): string;
}
