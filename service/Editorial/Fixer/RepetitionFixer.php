<?php

declare(strict_types=1);

namespace Seo\Service\Editorial\Fixer;

class RepetitionFixer extends AbstractPhraseFixer
{
    public function code(): string
    {
        return 'repetition';
    }
}
