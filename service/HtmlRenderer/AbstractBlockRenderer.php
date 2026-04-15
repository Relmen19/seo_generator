<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer;

use Seo\Database;

abstract class AbstractBlockRenderer implements BlockRendererInterface
{
    protected Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getCss(): string
    {
        return '';
    }

    public function getJs(): string
    {
        return '';
    }

    protected function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    protected function getImageLayout(array $c, string $default = 'right'): string
    {
        $layout = $c['image_layout'] ?? $c['image_align'] ?? $default;
        $valid = [
            'left', 'right', 'center', 'full', 'top', 'bottom',
            'left-top', 'left-bottom', 'right-top', 'right-bottom',
            'background', 'hidden'
        ];
        return in_array($layout, $valid) ? $layout : $default;
    }

    /**
     * @return array{0: string, 1: string} [$hPos, $vPos]
     */
    protected function parseImageLayout(string $layout): array
    {
        $map = [
            'left-top'     => ['left', 'top'],
            'left'         => ['left', 'center'],
            'left-bottom'  => ['left', 'bottom'],
            'right-top'    => ['right', 'top'],
            'right'        => ['right', 'center'],
            'right-bottom' => ['right', 'bottom'],
            'center'       => ['center', 'center'],
            'full'         => ['full', 'center'],
            'top'          => ['full', 'top'],
            'bottom'       => ['full', 'bottom'],
            'background'   => ['background', 'center'],
            'hidden'       => ['hidden', 'center'],
        ];
        return $map[$layout] ?? ['right', 'center'];
    }

    protected function renderInlineImage(array $c, string $defaultAlign = 'right'): string
    {
        if (empty($c['image_id'])) return '';

        $layout = $this->getImageLayout($c, $defaultAlign);
        [$hPos, $vPos] = $this->parseImageLayout($layout);

        if ($hPos === 'hidden') return '';
        if ($hPos === 'full' && ($vPos === 'top' || $vPos === 'bottom')) return '';
        if ($hPos === 'background') return '';

        $img = $this->db->fetchOne(
            "SELECT mime_type, data_base64 FROM seo_images WHERE id = ?",
            [$c['image_id']]
        );
        if (!$img) return '';

        $alt     = $this->e($c['image_alt'] ?? $c['title'] ?? '');
        $caption = $this->e($c['image_caption'] ?? '');

        $captionHtml = $caption
            ? '<figcaption class="img-card-caption">' . $caption . '</figcaption>'
            : '';

        $cssLayout = $hPos;

        return '<figure class="img-card img-card--' . $cssLayout . '">'
            . '<div class="img-frame">'
            . '<img src="data:' . $img['mime_type'] . ';base64,' . $img['data_base64']
            . '" alt="' . $alt . '" loading="lazy">'
            . '<div class="img-shine"></div>'
            . '</div>'
            . $captionHtml
            . '</figure>';
    }

    protected function renderBlockImageOnly(array $c): string
    {
        if (empty($c['image_id'])) return '';

        $layout = $this->getImageLayout($c, '');
        [$hPos, $vPos] = $this->parseImageLayout($layout);

        if (!($hPos === 'full' && in_array($vPos, ['top', 'bottom']))) return '';

        $img = $this->db->fetchOne(
            "SELECT mime_type, data_base64 FROM seo_images WHERE id = ?",
            [$c['image_id']]
        );
        if (!$img) return '';

        $alt     = $this->e($c['image_alt'] ?? $c['title'] ?? '');
        $caption = $this->e($c['image_caption'] ?? '');

        $captionHtml = $caption
            ? '<figcaption class="blk-img-caption">' . $caption . '</figcaption>'
            : '';

        return '<figure class="blk-img blk-img--' . $vPos . '">'
            . '<div class="img-frame">'
            . '<img src="data:' . $img['mime_type'] . ';base64,' . $img['data_base64']
            . '" alt="' . $alt . '" loading="lazy">'
            . '<div class="img-shine"></div>'
            . '</div>'
            . $captionHtml
            . '</figure>';
    }

    /**
     * Resolve block images into placement slots.
     * @return array{0: string, 1: string, 2: string, 3: string} [$imgTop, $imgInline, $imgBot, $bgStyle]
     */
    protected function resolveBlockImages(array $c, string $defaultAlign = 'right'): array
    {
        if (empty($c['image_id'])) return ['', '', '', ''];

        $layout = $this->getImageLayout($c, $defaultAlign);
        [$hPos, $vPos] = $this->parseImageLayout($layout);

        if ($hPos === 'hidden') return ['', '', '', ''];

        $imgTop = '';
        $imgBot = '';
        $imgH   = '';
        $bgStyle = '';

        if ($hPos === 'full' && $vPos === 'top') {
            $imgTop = $this->renderBlockImageOnly($c);
        } elseif ($hPos === 'full' && $vPos === 'bottom') {
            $imgBot = $this->renderBlockImageOnly($c);
        } elseif ($hPos === 'background') {
            $img = $this->db->fetchOne(
                "SELECT mime_type, data_base64 FROM seo_images WHERE id = ?",
                [$c['image_id']]
            );
            if ($img) {
                $bgStyle = 'background-image:url(data:' . $img['mime_type'] . ';base64,' . $img['data_base64'] . ')';
            }
        } else {
            $inlineImg = $this->renderInlineImage($c, $defaultAlign);
            if ($vPos === 'top') {
                $imgTop = $inlineImg;
            } elseif ($vPos === 'bottom') {
                $imgBot = $inlineImg;
            } else {
                $imgH = $inlineImg;
            }
        }

        return [$imgTop, $imgH, $imgBot, $bgStyle];
    }
}
