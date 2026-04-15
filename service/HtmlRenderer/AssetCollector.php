<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer;

use Seo\Service\HtmlRenderer\Component\ComponentInterface;
use Seo\Service\HtmlRenderer\Theme\ThemeInterface;

class AssetCollector
{
    /** @var string[] className => CSS string */
    private array $cssBlocks = [];

    /** @var string[] className => JS string */
    private array $jsBlocks = [];

    /** @var string */
    private string $themeCss = '';

    /** @var string */
    private string $coreJs = '';

    public function addTheme(ThemeInterface $theme): void
    {
        $this->themeCss = $theme->getCssVariables() . "\n" . $theme->getBaseCss();
    }

    public function addComponent(ComponentInterface $component): void
    {
        $key = get_class($component);
        if (!isset($this->cssBlocks[$key])) {
            $css = $component->getCss();
            if ($css !== '') {
                $this->cssBlocks[$key] = $css;
            }
        }
        if (!isset($this->jsBlocks[$key])) {
            $js = $component->getJs();
            if ($js !== '') {
                $this->jsBlocks[$key] = $js;
            }
        }
    }

    public function addBlock(BlockRendererInterface $renderer): void
    {
        $key = get_class($renderer);
        if (!isset($this->cssBlocks[$key])) {
            $css = $renderer->getCss();
            if ($css !== '') {
                $this->cssBlocks[$key] = $css;
            }
        }
        if (!isset($this->jsBlocks[$key])) {
            $js = $renderer->getJs();
            if ($js !== '') {
                $this->jsBlocks[$key] = $js;
            }
        }
    }

    public function setCoreJs(string $js): void
    {
        $this->coreJs = $js;
    }

    public function buildStyleTag(): string
    {
        $css = $this->themeCss;
        foreach ($this->cssBlocks as $block) {
            $css .= "\n" . $block;
        }
        return '<style>' . $css . "\n" . '</style>';
    }

    public function buildScriptTag(): string
    {
        $js = $this->coreJs;
        foreach ($this->jsBlocks as $block) {
            $js .= "\n" . $block;
        }
        return '<script>' . $js . "\n" . '</script>';
    }
}
