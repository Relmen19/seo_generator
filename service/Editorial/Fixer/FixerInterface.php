<?php

declare(strict_types=1);

namespace Seo\Service\Editorial\Fixer;

use Seo\Service\GptClient;

interface FixerInterface
{
    /** Issue code this fixer handles ("repetition", "banned_phrase", "empty_chart"). */
    public function code(): string;

    /**
     * Build new content for affected blocks.
     *
     * @param array $article  seo_articles row (incl. research_dossier).
     * @param array $blocks   list of seo_article_blocks rows.
     * @param array $issues   issues filtered by code() — each {id, severity, code, message, block_id}.
     * @param GptClient $gpt  pre-configured client (logContext set by caller).
     * @return array<int, array{block_id:int, content:array}>  блоки с новым content.
     */
    public function fix(array $article, array $blocks, array $issues, GptClient $gpt): array;
}
