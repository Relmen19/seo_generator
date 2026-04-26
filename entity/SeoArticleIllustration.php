<?php

declare(strict_types=1);

namespace Seo\Entity;

final class SeoArticleIllustration {

    public const TABLE = 'seo_article_illustrations';

    public const KIND_HERO   = 'hero';
    public const KIND_OG     = 'og';
    public const KIND_INLINE = 'inline';

    public const KINDS = [self::KIND_HERO, self::KIND_OG, self::KIND_INLINE];

    public const STATUS_PENDING = 'pending';
    public const STATUS_READY   = 'ready';
    public const STATUS_FAILED  = 'failed';
    public const STATUS_STALE   = 'stale';
}
