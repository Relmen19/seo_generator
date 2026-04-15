<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class RichtextBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $content, string $id): string
    {
        $c = $content;
        $tocName = '';

        $blocks = $c['blocks'] ?? [];
        $normalized = [];
        foreach ($blocks as $b) {
            if (!is_array($b)) {
                if (is_string($b) && trim($b) !== '') {
                    $normalized[] = ['type' => 'paragraph', 'text' => $b];
                }
                continue;
            }
            $bType = $b['type'] ?? 'paragraph';
            if ($bType === 'richtext' && isset($b['content']) && is_array($b['content'])) {
                foreach ($b['content'] as $sub) {
                    if (is_array($sub)) $normalized[] = $sub;
                    elseif (is_string($sub) && trim($sub) !== '') $normalized[] = ['type' => 'paragraph', 'text' => $sub];
                }
            } else {
                $normalized[] = $b;
            }
        }

        if (empty($normalized)) {
            $fallback = $c['text'] ?? $c['content'] ?? null;
            if (is_string($fallback) && trim($fallback) !== '') {
                $normalized[] = ['type' => 'paragraph', 'text' => $fallback];
            } elseif (is_array($fallback)) {
                foreach ($fallback as $item) {
                    if (is_string($item)) $normalized[] = ['type' => 'paragraph', 'text' => $item];
                    elseif (is_array($item)) $normalized[] = $item;
                }
            }
        }

        $imageHtml = '';
        $imageHPos = 'center';
        $imageVPos = 'center';
        if (!empty($c['image_id'])) {
            $img = $this->db->fetchOne(
                "SELECT mime_type, data_base64 FROM seo_images WHERE id = ?",
                [(int)$c['image_id']]
            );
            if ($img && !empty($img['data_base64'])) {
                $imageLayout = $this->getImageLayout($c, 'center');
                [$imageHPos, $imageVPos] = $this->parseImageLayout($imageLayout);

                if ($imageHPos !== 'hidden') {
                    $mime = $this->e($img['mime_type'] ?? 'image/jpeg');
                    $alt  = $this->e($c['image_alt'] ?? '');
                    $cap  = $this->e($c['image_caption'] ?? '');

                    $isTopBot = ($imageHPos === 'full' && in_array($imageVPos, ['top', 'bottom']));
                    if ($isTopBot) {
                        $layoutClass = 'blk-img blk-img--' . $imageVPos;
                    } else {
                        switch ($imageHPos) {
                            case 'left' : $layoutClass = 'rt-img rt-img--left'; break;
                            case 'right': $layoutClass = 'rt-img rt-img--right'; break;
                            case 'full' : $layoutClass = 'rt-img rt-img--full'; break;
                            default: $layoutClass = 'rt-img rt-img--center';
                        }
                    }

                    $capClass = $isTopBot ? 'blk-img-caption' : '';
                    $imageHtml = '<figure class="'.$layoutClass.'">'
                        . '<div class="img-frame">'
                        . '<img src="data:'.$mime.';base64,'.$img['data_base64'].'" alt="'.$alt.'" loading="lazy">'
                        . '<div class="img-shine"></div>'
                        . '</div>'
                        . ($cap ? '<figcaption class="'.$capClass.'">'.$cap.'</figcaption>' : '')
                        . '</figure>';
                }
            }
        }

        $isTopBottom = ($imageHPos === 'full' && in_array($imageVPos, ['top', 'bottom']));
        $insertAfter = -1;
        $isFloat = in_array($imageHPos, ['left', 'right']);

        if ($imageHtml && !$isTopBottom) {
            $textCount = 0;
            $threshold = $isFloat ? 1 : (count($normalized) <= 4 ? 1 : 2);
            foreach ($normalized as $idx => $nb) {
                $t = $nb['type'] ?? 'paragraph';
                if ($t !== 'heading') $textCount++;
                if ($textCount >= $threshold) {
                    $insertAfter = $idx;
                    break;
                }
            }
            if ($insertAfter < 0) $insertAfter = 0;
        }

        $h = '<section id="'.$id.'" class="block-richtext reveal">'
            . '<div class="container">';

        /* Top-layout image goes before all content */
        if ($isTopBottom && $imageVPos === 'top' && $imageHtml) {
            $h .= $imageHtml;
            $imageHtml = '';
        }

        $inFloat = false;

        foreach ($normalized as $idx => $b) {
            if ($idx === $insertAfter && $imageHtml) {
                if ($isFloat) {
                    $h .= '<div class="rt-float-wrap">' . $imageHtml;
                    $inFloat = true;
                } else {
                    $h .= $imageHtml;
                }
                $imageHtml = '';
            }

            $h .= $this->renderRichtextSubblock($b, $tocName);
        }

        if ($inFloat) {
            $h .= '<div class="rt-float-clear"></div></div>';
            $inFloat = false;
        }

        if ($imageHtml) {
            $h .= $imageHtml;
        }

        $h = str_replace(
            'class="block-richtext reveal"',
            'class="block-richtext reveal" data-toc="'.$this->e($tocName ?: 'Описание').'"',
            $h
        );

        return $h.'</div></section>'."\n";
    }

    private function renderRichtextSubblock(array $b, string &$tocName): string
    {
        $t = $b['type'] ?? 'paragraph';
        $h = '';

        $txt = $b['text'] ?? $b['content'] ?? '';
        if (is_array($txt)) {
            $parts = [];
            foreach ($txt as $item) {
                if (is_string($item)) $parts[] = $item;
                elseif (is_array($item) && isset($item['text']) && is_string($item['text'])) $parts[] = $item['text'];
            }
            $txt = implode(' ', $parts);
        }
        if (!is_string($txt)) $txt = (string)$txt;

        if ($t === 'heading') {
            $l = max(2, min(6, (int)($b['level'] ?? 2)));
            $h .= "<h{$l}>".$this->e($txt)."</h{$l}>";
            if (!$tocName && $l <= 3) $tocName = $txt;

        } elseif ($t === 'list') {
            $items = $b['items'] ?? null;
            if ($items === null) {
                $raw = $b['text'] ?? $b['content'] ?? [];
                $items = is_array($raw) ? $raw : [$txt];
            }
            if (!is_array($items)) $items = [$items];
            $h .= '<ul>';
            foreach ($items as $li) {
                $liText = is_string($li) ? $li : (is_array($li) && isset($li['text']) ? $li['text'] : json_encode($li, JSON_UNESCAPED_UNICODE));
                $h .= '<li>'.$this->e($liText).'</li>';
            }
            $h .= '</ul>';

        } elseif ($t === 'highlight') {
            $h .= '<div class="highlight">'.$this->e($txt).'</div>';

        } elseif ($t === 'quote') {
            $h .= '<blockquote>'.$this->e($txt).'</blockquote>';

        } else {
            if (trim($txt) !== '') {
                $h .= '<p>'.$this->e($txt).'</p>';
            }
        }

        return $h;
    }

    public function getCss(): string
    {
        return '.block-richtext { padding:64px 0 }'
            . "\n" . '.rt-img { margin:2em auto }'
            . "\n" . '.rt-img .img-frame { border-radius:16px; overflow:hidden; box-shadow:0 8px 32px rgba(15,23,42,.08); position:relative }'
            . "\n" . '.rt-img .img-frame img { display:block; width:100%; height:auto; transition:transform .5s ease }'
            . "\n" . '.rt-img .img-frame:hover img { transform:scale(1.02) }'
            . "\n" . '.rt-img figcaption { margin-top:10px; font-size:.85rem; color:var(--muted); text-align:center; line-height:1.4; font-style:italic }'
            . "\n" . '.rt-img--center { max-width:640px; margin-left:auto; margin-right:auto }'
            . "\n" . '.rt-img--full { max-width:100% }'
            . "\n" . '.rt-img--left { float:left; max-width:340px; width:40%; margin:.4em 1.8em 1em 0; shape-outside:margin-box }'
            . "\n" . '.rt-img--right { float:right; max-width:340px; width:40%; margin:.4em 0 1em 1.8em; shape-outside:margin-box }'
            . "\n" . '.rt-float-wrap { overflow:hidden; margin:1.5em 0 }'
            . "\n" . '.rt-float-clear { clear:both; display:table; width:100% }'
            . "\n" . '@media(max-width:768px) {'
            .     '.rt-img--left,.rt-img--right { float:none; max-width:100%; width:100%; margin:1.2em 0; shape-outside:none }'
            .     '.rt-img--center { max-width:100% }'
            .     '.rt-float-wrap { overflow:visible }'
            .     '.rt-float-clear { display:none }'
            . '}';
    }

    public function getJs(): string
    {
        return '';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        foreach ($content['blocks'] ?? [] as $bl) {
            if (($bl['type'] ?? '') === 'heading' && ($bl['level'] ?? 3) <= 3) {
                return $bl['content'] ?? $bl['text'] ?? '';
            }
        }
        return $meta['name'] ?? 'Описание';
    }
}
