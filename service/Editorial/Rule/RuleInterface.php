<?php

declare(strict_types=1);

namespace Seo\Service\Editorial\Rule;

interface RuleInterface
{
    /**
     * @param array $article  Raw article row.
     * @param array $blocks   Array of raw block rows (with 'content' as string|array).
     * @return array<int, array{severity:string,code:string,message:string,block_id:?int}>
     */
    public function run(array $article, array $blocks): array;
}
