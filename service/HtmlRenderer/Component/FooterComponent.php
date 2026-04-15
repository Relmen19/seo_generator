<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Component;

class FooterComponent implements ComponentInterface
{
    public function renderHtml(array $context): string
    {
        return '';
    }

    public function getCss(): string
    {
        return '';
    }

    public function getJs(): string
    {
        return '';
    }
}
