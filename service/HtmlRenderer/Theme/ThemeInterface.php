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

    /**
     * Google Fonts <link> tags for this theme.
     */
    public function getFontLinks(): string;

    /**
     * CSS class added to <body> for theme-specific component overrides.
     * Return '' for the default theme (no body class needed).
     */
    public function getBodyClass(): string;
}
