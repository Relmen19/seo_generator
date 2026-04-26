<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer;

use Seo\Database;

/**
 * Token-based theme resolution.
 *
 * Resolution order for an article:
 *   article.theme_code → profile.default_theme_code → 'default'
 *
 * Tokens are stored as JSON in seo_themes.tokens with shape:
 *   { color:{...}, type:{...}, space:{scale:[...]}, radius:{...}, layout:{...} }
 *
 * Emits two CSS blocks:
 *   - theme-vars: :root { --color-accent:...; --type-font-text:...; ... }
 *   - brand-overrides: from profile.brand_palette (named keys override matching tokens)
 */
class ThemeService
{
    private Database $db;
    /** @var array<string,array> in-process cache by theme code */
    private array $cache = [];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Load theme by code. Returns ['code'=>..., 'name'=>..., 'tokens'=>[...]].
     * Falls back to 'default' if missing; if 'default' also missing, returns built-in fallback.
     */
    public function loadTheme(string $code): array
    {
        if ($code === '') $code = 'default';
        if (isset($this->cache[$code])) return $this->cache[$code];

        $row = $this->db->fetchOne(
            "SELECT code, name, tokens FROM seo_themes WHERE code = ? AND is_active = 1",
            [$code]
        );
        if (!$row && $code !== 'default') {
            return $this->cache[$code] = $this->loadTheme('default');
        }
        if (!$row) {
            return $this->cache[$code] = [
                'code' => 'default',
                'name' => 'Default (fallback)',
                'tokens' => $this->fallbackTokens(),
            ];
        }
        $tokens = is_string($row['tokens']) ? json_decode($row['tokens'], true) : $row['tokens'];
        if (!is_array($tokens)) $tokens = $this->fallbackTokens();
        return $this->cache[$code] = [
            'code' => $row['code'],
            'name' => $row['name'],
            'tokens' => $tokens,
        ];
    }

    /**
     * Resolve theme code for an article: article → profile → 'default'.
     */
    public function resolveForArticle(array $article, ?array $profile = null): array
    {
        $code = trim((string)($article['theme_code'] ?? ''));
        if ($code === '' && $profile !== null) {
            $code = trim((string)($profile['default_theme_code'] ?? ''));
        }
        // Legacy fallback: profile.theme (string column from migration 021).
        if ($code === '' && $profile !== null) {
            $code = trim((string)($profile['theme'] ?? ''));
        }
        if ($code === '') $code = 'default';
        return $this->loadTheme($code);
    }

    /**
     * Render :root { --color-accent:...; ... } from tokens.
     * Also emits --space-1..--space-N from space.scale array.
     */
    public function renderCssVars(array $tokens): string
    {
        $lines = [];

        foreach (['color', 'type', 'radius', 'layout'] as $group) {
            if (empty($tokens[$group]) || !is_array($tokens[$group])) continue;
            foreach ($tokens[$group] as $k => $v) {
                if (!is_scalar($v)) continue;
                $lines[] = '--' . $group . '-' . $k . ':' . $v;
            }
        }

        if (!empty($tokens['space']['scale']) && is_array($tokens['space']['scale'])) {
            foreach (array_values($tokens['space']['scale']) as $i => $v) {
                if (!is_numeric($v)) continue;
                $lines[] = '--space-' . ($i + 1) . ':' . ((int)$v) . 'px';
            }
        }

        // Legacy aliases — keep all old renderers working through bridge.
        // PHP themes (DefaultTheme/Editorial/Brutalist) emit literal values that override
        // these aliases when active. When only DB theme is set, aliases drive every renderer.
        $lines[] = '--blue:var(--color-accent)';
        $lines[] = '--blue-light:var(--color-accent-soft)';
        $lines[] = '--blue-dark:var(--color-accent)';
        $lines[] = '--teal:var(--color-accent)';
        $lines[] = '--dark:var(--color-text)';
        $lines[] = '--dark2:var(--color-surface)';
        $lines[] = '--slate:var(--color-text-2)';
        $lines[] = '--muted:var(--color-text-3)';
        $lines[] = '--border:var(--color-border)';
        $lines[] = '--bg:var(--color-bg)';
        $lines[] = '--white:var(--color-surface)';
        $lines[] = '--red:var(--color-danger)';
        $lines[] = '--green:var(--color-success)';
        $lines[] = '--green-light:var(--color-accent-soft)';
        $lines[] = '--warn:var(--color-warn)';
        $lines[] = '--fh:var(--type-font-heading)';
        $lines[] = '--fb:var(--type-font-text)';
        $lines[] = '--r:var(--radius-md)';

        if (!$lines) return '';
        return ':root{' . implode(';', $lines) . '}';
    }

    /**
     * Render brand overrides from profile.brand_palette.
     * Maps known palette keys to color tokens:
     *   primary → --color-accent
     *   accent  → --color-accent (alias)
     *   ink     → --color-text
     *   bg      → --color-bg
     */
    public function renderBrandOverrides(?array $profile): string
    {
        if (!$profile || empty($profile['brand_palette'])) return '';
        $palette = is_string($profile['brand_palette'])
            ? json_decode($profile['brand_palette'], true)
            : $profile['brand_palette'];
        if (!is_array($palette)) return '';

        $map = [
            'primary' => '--color-accent',
            'accent'  => '--color-accent',
            'ink'     => '--color-text',
            'bg'      => '--color-bg',
        ];
        $lines = [];
        foreach ($map as $palKey => $cssVar) {
            if (!empty($palette[$palKey]) && is_string($palette[$palKey])) {
                $val = $this->sanitizeColor($palette[$palKey]);
                if ($val !== '') $lines[] = $cssVar . ':' . $val;
            }
        }
        if (!$lines) return '';
        return ':root{' . implode(';', $lines) . '}';
    }

    private function sanitizeColor(string $v): string
    {
        $v = trim($v);
        // Allow #rgb / #rrggbb / rgb()/rgba()/hsl()/hsla()
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $v)) return $v;
        if (preg_match('/^(rgb|rgba|hsl|hsla)\([^)]+\)$/i', $v)) return $v;
        return '';
    }

    private function fallbackTokens(): array
    {
        return [
            'color' => [
                'bg' => '#F8FAFC', 'surface' => '#FFFFFF',
                'text' => '#0F172A', 'text-2' => '#334155', 'text-3' => '#64748B',
                'accent' => '#2563EB', 'accent-soft' => '#EFF6FF',
                'success' => '#16A34A', 'warn' => '#F59E0B', 'danger' => '#EF4444',
                'border' => 'rgba(0,0,0,0.08)',
            ],
            'type' => [
                'font-text' => '"Onest",sans-serif',
                'font-heading' => '"Geologica",sans-serif',
                'font-mono' => 'ui-monospace,SFMono-Regular,Menlo,monospace',
                'size-text' => '17px', 'line-text' => '1.7',
                'size-h2' => 'clamp(1.5rem,3vw,2.2rem)', 'size-h3' => '1.35rem',
            ],
            'space' => ['scale' => [4, 8, 16, 24, 40, 64]],
            'radius' => ['sm' => '8px', 'md' => '14px', 'lg' => '20px'],
            'layout' => ['col-max' => '960px', 'col-wide' => '1180px'],
        ];
    }
}
