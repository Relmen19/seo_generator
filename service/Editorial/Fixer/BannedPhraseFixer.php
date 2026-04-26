<?php

declare(strict_types=1);

namespace Seo\Service\Editorial\Fixer;

class BannedPhraseFixer extends AbstractPhraseFixer
{
    public function code(): string
    {
        return 'banned_phrase';
    }
}
