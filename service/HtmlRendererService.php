<?php

declare(strict_types=1);

namespace Seo\Service;

use Seo\Database;
use Seo\Service\HtmlRenderer\BlockRegistry;
use Seo\Service\HtmlRenderer\PageAssembler;

/**
 * Thin facade over the component-based rendering pipeline.
 *
 * Public API is unchanged — callers (PublishService, ArticleController)
 * don't need any modifications.
 */
class HtmlRendererService
{
    private Database $db;
    private ?array $siteProfile = null;
    private BlockRegistry $registry;
    private PageAssembler $assembler;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->registry = new BlockRegistry($this->db);
        $this->assembler = new PageAssembler($this->db, $this->registry);
    }

    public function setSiteProfile(?array $profile): self
    {
        $this->siteProfile = $profile;
        $this->assembler->setSiteProfile($profile);
        return $this;
    }

    /**
     * Render full article page.
     */
    public function render(int $articleId, bool $preview = false): string
    {
        return $this->assembler->render($articleId, $preview);
    }

    /**
     * Render a single block without page wrapper.
     */
    public function renderSingleBlock(string $type, array $content): string
    {
        return $this->assembler->renderSingleBlock($type, $content);
    }

    /**
     * Render a single block with minimal page structure for preview.
     */
    public function renderSingleBlockPreview(string $type, array $content): string
    {
        return $this->assembler->renderSingleBlockPreview($type, $content);
    }

    /**
     * Access the block registry for custom block registration.
     */
    public function getBlockRegistry(): BlockRegistry
    {
        return $this->registry;
    }

    /**
     * Access the page assembler for theme/component customization.
     */
    public function getPageAssembler(): PageAssembler
    {
        return $this->assembler;
    }
}
