<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Component;

class DividerComponent implements ComponentInterface
{
    public function renderHtml(array $context): string
    {
        return '<div class="section-divider" aria-hidden="true"><div class="section-divider-line"></div></div>' . "\n";
    }

    public function getCss(): string
    {
        // Divider CSS is defined in DefaultTheme::getBaseCss()
        return '';
    }

    public function getJs(): string
    {
        return '';
    }
}
