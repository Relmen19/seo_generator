<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Theme;

interface ThemeInterface
{
    /**
     * CSS variables (:root and dark-mode overrides).
     */
    public function getCssVariables(): string;

    /**
     * Base CSS: reset, typography, container, animations, shared elements.
     */
    public function getBaseCss(): string;
}
