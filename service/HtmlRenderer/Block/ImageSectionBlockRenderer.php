<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class ImageSectionBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $content, string $id): string
    {
        $c = $content;
        $title   = $this->e($c['title'] ?? '');
        $text    = $this->e($c['text'] ?? '');
        $alt     = $this->e($c['image_alt'] ?? $c['title'] ?? '');
        $caption = $this->e($c['image_caption'] ?? '');

        // Unified layout: image_layout > image_align > legacy $c['layout'] > default left
        $legacyLayout = ($c['layout'] ?? '') === 'image-right' ? 'right' : 'left';
        $layout  = $this->getImageLayout($c, $legacyLayout);
        [$hPos, $vPos] = $this->parseImageLayout($layout);

        $imgSrc = '';
        if (!empty($c['image_id'])) {
            $img = $this->db->fetchOne(
                "SELECT mime_type, data_base64 FROM seo_images WHERE id = ?",
                [$c['image_id']]
            );
            if ($img) {
                $imgSrc = 'data:' . $img['mime_type'] . ';base64,' . $img['data_base64'];
            }
        }

        $captionHtml = $caption ? '<figcaption class="img-card-caption">' . $caption . '</figcaption>' : '';
        $tocAttr = 'data-toc="' . $this->e($c['title'] ?? 'Иллюстрация') . '"';

        // Stacked layouts: top, bottom, center, full (non-float)
        $isStacked = in_array($hPos, ['full', 'center']) || $hPos === 'hidden';
        if ($hPos === 'hidden') {
            $h = '<section id="' . $id . '" class="block-imgsec reveal" ' . $tocAttr . '>'
                . '<div class="container">';
            if ($title) $h .= '<h3>' . $title . '</h3>';
            $h .= '<p>' . $text . '</p>';
            return $h . '</div></section>' . "\n";
        }

        if ($isStacked) {
            $maxW = $hPos === 'center' ? 'max-width:640px;margin-left:auto;margin-right:auto;' : '';
            $figClass = 'blk-img blk-img--' . ($vPos === 'bottom' ? 'bottom' : 'top');
            $figHtml = '';
            if ($imgSrc) {
                $figHtml = '<figure class="' . $figClass . '" style="' . $maxW . '">'
                    . '<div class="img-frame">'
                    . '<img src="' . $imgSrc . '" alt="' . $alt . '" loading="lazy">'
                    . '<div class="img-shine"></div>'
                    . '</div>' . $captionHtml . '</figure>';
            }
            $h = '<section id="' . $id . '" class="block-imgsec reveal" ' . $tocAttr . '>'
                . '<div class="container">';
            if ($vPos !== 'bottom') $h .= $figHtml;
            if ($title) $h .= '<h3>' . $title . '</h3>';
            $h .= '<p>' . $text . '</p>';
            if ($vPos === 'bottom') $h .= $figHtml;
            return $h . '</div></section>' . "\n";
        }

        // Float layouts: left / right (with optional vPos for ordering)
        $revCls = ($hPos === 'right') ? ' imgsec--reverse' : '';
        $alignCls = '';
        if ($vPos === 'top') $alignCls = ' imgsec--align-top';
        elseif ($vPos === 'bottom') $alignCls = ' imgsec--align-bottom';
        $h = '<section id="' . $id . '" class="block-imgsec reveal' . $revCls . $alignCls . '" ' . $tocAttr . '>'
            . '<div class="container"><div class="imgsec-flex">';
        if ($imgSrc) {
            $h .= '<div class="imgsec-visual">'
                . '<div class="img-frame">'
                . '<img src="' . $imgSrc . '" alt="' . $alt . '" loading="lazy">'
                . '<div class="img-shine"></div>'
                . '</div>' . $captionHtml . '</div>';
        }
        $h .= '<div class="imgsec-text">';
        if ($title) $h .= '<h3>' . $title . '</h3>';
        $h .= '<p>' . $text . '</p>';
        return $h . '</div></div></div></section>' . "\n";
    }

    public function getCss(): string
    {
        return '.block-imgsec { padding:80px 0 }'
            . "\n" . '.imgsec-flex { display:flex; gap:48px; align-items:center }'
            . "\n" . '.imgsec--align-top .imgsec-flex { align-items:flex-start }'
            . "\n" . '.imgsec--align-bottom .imgsec-flex { align-items:flex-end }'
            . "\n" . '.imgsec-visual { flex:1; min-width:0 }'
            . "\n" . '.imgsec-text { flex:1; min-width:0 }'
            . "\n" . '.imgsec--reverse .imgsec-flex { flex-direction:row-reverse }'
            . "\n" . '@media(max-width:768px) {'
            .     '.imgsec-flex,.imgsec--reverse .imgsec-flex { flex-direction:column }'
            . '}';
    }

    public function getJs(): string
    {
        return '';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Иллюстрация';
    }
}
