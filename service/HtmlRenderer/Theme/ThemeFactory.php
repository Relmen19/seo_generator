<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Theme;

class ThemeFactory
{
    /** @var array<string, string> theme key => FQCN */
    private static $map = [
        'default'   => DefaultTheme::class,
        'editorial' => EditorialTheme::class,
        'brutalist' => BrutalistTheme::class,
    ];

    public static function create(string $key): ThemeInterface
    {
        $class = self::$map[$key] ?? self::$map['default'];
        return new $class();
    }

    /**
     * @return array<string, string> key => human label
     */
    public static function available(): array
    {
        return [
            'default'   => 'Apple Minimal',
            'editorial' => 'Editorial',
            'brutalist' => 'Brutalist',
        ];
    }
}
