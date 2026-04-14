<?php

declare(strict_types=1);

namespace Seo\Service;

use RuntimeException;
use Seo\Database;
use Seo\Entity\SeoArticle;
use Seo\Entity\SeoArticleBlock;
use Seo\Entity\SeoLinkConstant;
use Seo\Entity\SeoTemplate;

class HtmlRendererService {

    private Database $db;
    private ?array $siteProfile = null;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function setSiteProfile(?array $profile): self {
        $this->siteProfile = $profile;
        return $this;
    }

    public function render(int $articleId, bool $preview = false): string {
        $article  = $this->loadArticle($articleId);
        $blocks   = $this->loadBlocks($articleId);
        $links    = $this->loadLinks($articleId);
        $template = $article['template_id']
            ? $this->loadTemplate((int)$article['template_id'])
            : null;

        // Load site profile for branding
        if ($this->siteProfile === null && !empty($article['profile_id'])) {
            $this->siteProfile = $this->db->fetchOne(
                "SELECT * FROM seo_site_profiles WHERE id = ?", [$article['profile_id']]
            );
        }

        $visibleBlocks = [];
        $bodyHtml = '';
        foreach ($blocks as $idx => $block) {
            if (!(int)($block['is_visible'] ?? 1)) continue;
            $content = is_string($block['content'])
                ? json_decode($block['content'], true)
                : ($block['content'] ?? []);
            if ($idx > 0 && $block['type'] !== 'hero') {
                $bodyHtml .= $this->renderDivider();
            }
            $bodyHtml .= $this->renderBlock($block['type'], $content, $block);
            $visibleBlocks[] = $block;
        }

        $toc      = $this->buildToc($visibleBlocks);

        $fullHtml = $this->wrapInDocument($article, $bodyHtml, $toc, $template, $preview);
        /* Replace link placeholders in entire document (body + navbar + footer) in one pass */
        return $this->replaceLinkPlaceholders($fullHtml, $links);
    }

    public function renderSingleBlock(string $type, array $content): string {
        return $this->renderBlock($type, $content, []);
    }

    private function renderBlock(string $type, array $content, array $meta): string {
        $id = 'block-' . ($meta['id'] ?? uniqid());
        switch ($type) {
            case 'hero':             return $this->renderHero($content, $id);
            case 'stats_counter':    return $this->renderStats($content, $id);
            case 'richtext':         return $this->renderRichtext($content, $id);
            case 'range_table':
            case 'norms_table':      return $this->renderNormsTable($content, $id);
            case 'accordion':        return $this->renderAccordion($content, $id);
            case 'chart':            return $this->renderChart($content, $id);
            case 'comparison_table': return $this->renderComparison($content, $id);
            case 'image_section':    return $this->renderImageSection($content, $id);
            case 'faq':              return $this->renderFaq($content, $id);
            case 'cta':              return $this->renderCta($content, $id);
            case 'feature_grid':      return $this->renderFeatureGrid($content, $id);
            case 'testimonial':       return $this->renderTestimonial($content, $id);
            case 'gauge_chart':       return $this->renderGaugeChart($content, $id);
            case 'timeline':          return $this->renderTimeline($content, $id);
            case 'heatmap':           return $this->renderHeatmap($content, $id);
            case 'funnel':            return $this->renderFunnel($content, $id);
            case 'spark_metrics':     return $this->renderSparkMetrics($content, $id);
            case 'radar_chart':       return $this->renderRadarChart($content, $id);
            case 'before_after':      return $this->renderBeforeAfter($content, $id);
            case 'stacked_area':      return $this->renderStackedArea($content, $id);
            case 'score_rings':       return $this->renderScoreRings($content, $id);
            case 'range_comparison':  return $this->renderRangeComparison($content, $id);
            case 'value_checker':     return $this->renderValueChecker($content, $id);
            case 'criteria_checklist':
            case 'symptom_checklist': return $this->renderSymptomChecklist($content, $id);
            case 'prep_checklist':    return $this->renderPrepChecklist($content, $id);
            case 'info_cards':        return $this->renderInfoCards($content, $id);
            case 'story_block':       return $this->renderStoryBlock($content, $id);
            case 'verdict_card':      return $this->renderVerdictCard($content, $id);
            case 'numbered_steps':    return $this->renderNumberedSteps($content, $id);
            case 'warning_block':     return $this->renderWarningBlock($content, $id);
            case 'mini_calculator':   return $this->renderMiniCalculator($content, $id);
            case 'comparison_cards':  return $this->renderComparisonCards($content, $id);
            case 'progress_tracker':  return $this->renderProgressTracker($content, $id);
            case 'key_takeaways':     return $this->renderKeyTakeaways($content, $id);
            case 'expert_panel':      return $this->renderExpertPanel($content, $id);
            default:                  return "<!-- unknown block: {$type} -->\n";
        }
    }


    private function renderInlineImage(array $c, string $defaultAlign = 'right'): string {
        if (empty($c['image_id'])) return '';

        $layout = $this->getImageLayout($c, $defaultAlign);
        [$hPos, $vPos] = $this->parseImageLayout($layout);

        // hidden = don't render image at all
        if ($hPos === 'hidden') return '';

        // top/bottom full-width are handled by renderBlockImageOnly
        if ($hPos === 'full' && ($vPos === 'top' || $vPos === 'bottom')) return '';

        // background is handled separately by the block
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

        // Map compound layout to CSS class (left-top → img-card--left, etc.)
        $cssLayout = $hPos; // left, right, center, full

        return '<figure class="img-card img-card--' . $cssLayout . '">'
            . '<div class="img-frame">'
            . '<img src="data:' . $img['mime_type'] . ';base64,' . $img['data_base64']
            . '" alt="' . $alt . '" loading="lazy">'
            . '<div class="img-shine"></div>'
            . '</div>'
            . $captionHtml
            . '</figure>';
    }

    private function renderBlockImageOnly(array $c): string {
        if (empty($c['image_id'])) return '';

        $layout = $this->getImageLayout($c, '');
        [$hPos, $vPos] = $this->parseImageLayout($layout);

        // Only render for full-width top/bottom images
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

    private function getImageLayout(array $c, string $default = 'right'): string {
        $layout = $c['image_layout'] ?? $c['image_align'] ?? $default;
        $valid = [
            'left', 'right', 'center', 'full', 'top', 'bottom',
            'left-top', 'left-bottom', 'right-top', 'right-bottom',
            'background', 'hidden'
        ];
        return in_array($layout, $valid) ? $layout : $default;
    }

    /**
     * Resolve compound layout into placement parts.
     * 'left-top'  → float=left,  vpos=top
     * 'left'      → float=left,  vpos=center (legacy: inline float)
     * 'top'       → float=none,  vpos=top    (full-width above content)
     * Returns [string $hPos, string $vPos] where hPos ∈ {left,right,center,full,background,hidden}
     */
    private function parseImageLayout(string $layout): array {
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

    /**
     * Resolve block images into placement slots.
     * Returns [$imgTop, $imgInline, $imgBot, $bgStyle].
     * $bgStyle is a CSS style string for background-image on the section (for 'background' layout).
     * Compound layouts like 'left-top' place the float image in $imgTop slot.
     */
    private function resolveBlockImages(array $c, string $defaultAlign = 'right'): array {
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
            // Inline image (left, right, center, full-center)
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


    private function renderDivider(): string {
        return '<div class="section-divider" aria-hidden="true"><div class="section-divider-line"></div></div>' . "\n";
    }

    private function renderHero(array $c, string $id): string {
        $title = $this->e($c['title'] ?? '');
        $sub   = $this->e($c['subtitle'] ?? '');
        $cta   = $this->e($c['cta_text'] ?? '');
        $ctaK  = $c['cta_link_key'] ?? '';
        $ctaH  = $cta
            ? '<a class="hero-cta" href="{{link:' . $this->e($ctaK) . '}}">' . $cta . '</a>'
            : '';

        $imgH = '';
        if (!empty($c['image_id'])) {
            $img = $this->db->fetchOne(
                "SELECT mime_type, data_base64 FROM seo_images WHERE id = ?",
                [$c['image_id']]
            );
            if ($img) {
                $alt  = $this->e($c['image_alt'] ?? $c['title'] ?? '');
                $imgH = '<div class="hero-visual">'
                    . '<div class="hero-img-frame">'
                    . '<img src="data:' . $img['mime_type'] . ';base64,' . $img['data_base64']
                    . '" alt="' . $alt . '" class="hero-img" loading="eager">'
                    . '<div class="img-shine"></div>'
                    . '</div></div>';
            }
        }

        return '<section id="' . $id . '" class="block-hero" data-toc="' . $this->e($c['title'] ?? 'Введение') . '">'
            . '<div class="hero-orb hero-orb--1"></div>'
            . '<div class="hero-orb hero-orb--2"></div>'
            . '<div class="hero-orb hero-orb--3"></div>'
            . '<div class="hero-grid-bg"></div>'
            . '<div class="container hero-inner">'
            . '<div class="hero-text">'
            . '<h1>' . $title . '</h1>'
            . '<p class="hero-subtitle">' . $sub . '</p>'
            . $ctaH
            . '</div>'
            . $imgH
            . '</div>'
            . '</section>' . "\n";
    }

    private function renderStats(array $c, string $id): string {
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-stats reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="Показатели">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="stats-grid">';
        foreach ($c['items'] ?? [] as $it) {
            $h .= '<div class="stat-card">'
                . '<div class="stat-value">'
                . $this->e((string)($it['value'] ?? ''))
                . $this->e($it['suffix'] ?? '')
                . '</div>'
                . '<div class="stat-label">' . $this->e($it['label'] ?? '') . '</div>'
                . '</div>';
        }
        return $h . '</div><div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
    }

    private function renderRichtext(array $c, string $id): string {
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

    private function renderRichtextSubblock(array $b, string &$tocName): string {
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

    private function renderNormsTable(array $c, string $id): string {
        $cap  = $this->e($c['caption'] ?? 'Показатели и нормы');
        $rows = $c['rows'] ?? [];
        /* New format: rows[].states is an array of 3–5 state objects */
        $hasStates = !empty($rows) && is_array($rows[0] ?? null) && !empty($rows[0]['states']);
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-norms reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $cap . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="mac-window">'
            . '<div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">' . $cap . '</div></div>'
            . '<div class="mac-body">';

        if ($hasStates) {
            /*
             * Interactive 5-state norms.
             * Each row JSON: { name, unit, active (0-based index), states: [ {key,label,range,pct,description}, ... ] }
             * Keys: very_low | low | ok | high | very_high  (3 or 5 states)
             */
            $stateStyles = [
                'very_low'     => ['bar' => 'linear-gradient(90deg,#991B1B,#EF4444)', 'badge' => '#FEE2E2', 'bc' => '#991B1B', 'icon' => '↓↓'],
                'critical_low' => ['bar' => 'linear-gradient(90deg,#991B1B,#EF4444)', 'badge' => '#FEE2E2', 'bc' => '#991B1B', 'icon' => '↓↓'],
                'low'          => ['bar' => 'linear-gradient(90deg,#F59E0B,#FCD34D)', 'badge' => '#FEF3C7', 'bc' => '#B45309', 'icon' => '↓'],
                'ok'           => ['bar' => 'linear-gradient(90deg,#16A34A,#4ADE80)', 'badge' => '#DCFCE7', 'bc' => '#16A34A', 'icon' => '✓'],
                'normal'       => ['bar' => 'linear-gradient(90deg,#16A34A,#4ADE80)', 'badge' => '#DCFCE7', 'bc' => '#16A34A', 'icon' => '✓'],
                'optimal'      => ['bar' => 'linear-gradient(90deg,#16A34A,#4ADE80)', 'badge' => '#DCFCE7', 'bc' => '#16A34A', 'icon' => '✓'],
                'high'         => ['bar' => 'linear-gradient(90deg,#F59E0B,#FCD34D)', 'badge' => '#FEF3C7', 'bc' => '#B45309', 'icon' => '↑'],
                'elevated'     => ['bar' => 'linear-gradient(90deg,#F59E0B,#FCD34D)', 'badge' => '#FEF3C7', 'bc' => '#B45309', 'icon' => '↑'],
                'very_high'    => ['bar' => 'linear-gradient(90deg,#991B1B,#EF4444)', 'badge' => '#FEE2E2', 'bc' => '#991B1B', 'icon' => '↑↑'],
            ];

            $h .= '<div class="norms-status-list">';
            foreach ($rows as $ri => $row) {
                if (!is_array($row)) continue;
                $name   = $this->e($row['name'] ?? '');
                $unit   = $this->e($row['unit'] ?? '');
                $states = $row['states'] ?? [];
                $active = max(0, min(count($states) - 1, (int)($row['active'] ?? 0)));

                /* Encode states JSON for JS */
                $statesJson = [];
                foreach ($states as $si => $st) {
                    $key   = $st['key'] ?? 'ok';
                    $style = $stateStyles[$key] ?? $stateStyles['ok'];
                    $statesJson[] = [
                        'key'   => $key,
                        'label' => $st['label'] ?? $key,
                        'range' => ($st['range'] ?? '') . ($unit ? ' ' . $unit : ''),
                        'pct'   => max(4, min(100, (int)($st['pct'] ?? 50))),
                        'desc'  => $st['description'] ?? '',
                        'bar'   => $style['bar'],
                        'badge' => $style['badge'],
                        'bc'    => $style['bc'],
                        'icon'  => $style['icon'],
                    ];
                }
                $jsonAttr = $this->e(json_encode($statesJson, JSON_UNESCAPED_UNICODE));
                $initState = $statesJson[$active] ?? $statesJson[0];

                /* Pills navigation (state selector) */
                $h .= '<div class="norm-card" data-norm-card data-states="' . $jsonAttr . '" data-active="' . $active . '">'
                    . '<div class="norm-card-header">'
                    . '<span class="norm-name">' . $name . '</span>'
                    . '<span class="norm-badge" style="background:' . $initState['badge'] . ';color:' . $initState['bc'] . '">'
                    . '<span class="norm-badge-icon">' . $this->e($initState['icon']) . '</span> '
                    . $this->e($initState['range'])
                    . '</span>'
                    . '</div>'
                    . '<div class="norm-pills">';
                foreach ($states as $si => $st) {
                    $key = $st['key'] ?? 'ok';
                    $stStyle = $stateStyles[$key] ?? $stateStyles['ok'];
                    $h .= '<button class="norm-pill' . ($si === $active ? ' is-active' : '') . '"'
                        . ' data-idx="' . $si . '"'
                        . ' style="--pill-c:' . $stStyle['bc'] . '"'
                        . '>' . $this->e($st['label'] ?? $key) . '</button>';
                }
                $h .= '</div>'
                    . '<div class="bar-track norm-bar-track"><div class="bar-fill norm-bar" style="background:' . $initState['bar'] . '" data-width="' . $initState['pct'] . '"></div></div>'
                    . '<div class="norm-desc">' . $this->e($initState['desc']) . '</div>'
                    . '</div>';
            }
            $h .= '</div>';

        } else {
            /* ── Plain table → premium responsive card/table hybrid ── */
            $columns = $c['columns'] ?? [];
            $h .= '<div class="table-wrap premium-table-wrap">';
            $h .= '<table class="premium-table">';
            $h .= '<thead><tr>';
            foreach ($columns as $col) {
                $h .= '<th>' . $this->e(is_array($col) ? ($col['label'] ?? $col['name'] ?? '') : $col) . '</th>';
            }
            $h .= '</tr></thead><tbody>';
            foreach ($rows as $row) {
                $h .= '<tr>';
                $cells = is_array($row) ? (isset($row[0]) ? $row : array_values($row)) : [$row];
                foreach ($cells as $ci => $cell) {
                    $dlabel = isset($columns[$ci])
                        ? ' data-label="' . $this->e(is_array($columns[$ci]) ? ($columns[$ci]['label'] ?? $columns[$ci]['name'] ?? '') : $columns[$ci]) . '"'
                        : '';
                    $h .= '<td' . $dlabel . '>' . $this->e((string)$cell) . '</td>';
                }
                $h .= '</tr>';
            }
            $h .= '</tbody></table></div>';
        }

        return $h . '</div></div><div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
    }

    private function renderAccordion(array $c, string $id): string {
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-accordion reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="Подробности">'
            . '<div class="container">'
            . $imgTop
            . $imgH;
        foreach ($c['items'] ?? [] as $i => $it) {
            $h .= '<details' . ($i === 0 ? ' open' : '') . '>'
                . '<summary>' . $this->e($it['title'] ?? '') . '</summary>'
                . '<div class="acc-body">' . $this->e($it['content'] ?? '') . '</div>'
                . '</details>';
        }
        return $h . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
    }

    private function renderChart(array $c, string $id): string {
        $title     = $this->e($c['title'] ?? 'График');
        $chartType = $c['chart_type'] ?? 'bar';
        $labels    = $c['labels'] ?? [];
        $datasets  = $c['datasets'] ?? [];
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';
        $useCSS    = in_array($chartType, ['bar', 'horizontalBar'])
            && count($datasets) <= 2
            && count($labels) <= 12;

        $h = '<section id="' . $id . '" class="block-chart reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="mac-window">'
            . '<div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">' . $title . '</div></div>'
            . '<div class="mac-body">';

        /* Optional description (not for doughnut/pie — those render it inside .donut-aside) */
        $desc = trim($c['description'] ?? '');
        $isCircularType = in_array($chartType, ['doughnut', 'pie']);
        if ($desc !== '' && !$isCircularType) {
            $h .= '<p class="chart-desc">' . $this->e($desc) . '</p>';
        }

        if ($useCSS && !empty($labels)) {
            /* ── Premium CSS horizontal bars ── */
            $vals   = $datasets[0]['data'] ?? [];
            $maxVal = max(1, max(array_map('floatval', $vals)));
            $gradients = [
                ['#2563EB','#60A5FA'],['#0D9488','#2DD4BF'],['#8B5CF6','#C4B5FD'],
                ['#F59E0B','#FCD34D'],['#EF4444','#FCA5A5'],['#16A34A','#4ADE80'],
                ['#EC4899','#F9A8D4'],['#06B6D4','#67E8F9'],['#F97316','#FDBA74'],
                ['#6366F1','#A5B4FC'],['#14B8A6','#5EEAD4'],['#E11D48','#FDA4AF'],
            ];

            $h .= '<div class="css-chart">';
            foreach ($labels as $i => $label) {
                $val = floatval($vals[$i] ?? 0);
                $pct = round(($val / $maxVal) * 100);
                $g   = $gradients[$i % count($gradients)];
                $h .= '<div class="chart-row">'
                    . '<span class="chart-label">' . $this->e($label) . '</span>'
                    . '<div class="bar-track"><div class="bar-fill bar-fill--shimmer" style="background:linear-gradient(90deg,' . $g[0] . ',' . $g[1] . ')" data-width="' . $pct . '"></div></div>'
                    . '<span class="chart-val">' . $this->e((string)$val) . '</span>'
                    . '</div>';
            }
            $h .= '</div>';

        } elseif (in_array($chartType, ['doughnut', 'pie'])) {
            /* ── Interactive SVG Doughnut / Pie — side-by-side layout ── */
            $isDoughnut = ($chartType === 'doughnut');
            $ds0 = $datasets[0] ?? [];
            $data = $ds0['data'] ?? [];
            $colors = $ds0['colors'] ?? $ds0['backgroundColor'] ?? [
                '#2563EB','#0D9488','#8B5CF6','#F59E0B','#EF4444','#16A34A','#EC4899','#06B6D4'
            ];
            $descriptions = $ds0['descriptions'] ?? [];
            $total = array_sum(array_map('floatval', $data));

            if (count($data) <= 8 && count($data) > 0) {
                $radius = 70;
                $strokeW = $isDoughnut ? 36 : 70;
                $circum = 2 * M_PI * $radius;

                /*
                 * Layout (CSS grid):
                 *   Desktop: desc + legend/detail on right, SVG on left
                 *   Mobile:  desc on top, SVG centered, legend + detail below
                 *
                 *   .donut-layout (grid)
                 *     .donut-aside-desc  — grid-area: desc  (or order:-1 on mobile)
                 *     .donut-visual      — grid-area: ring
                 *     .donut-aside       — grid-area: aside (legend + detail)
                 */
                $h .= '<div class="donut-layout" data-donut>';

                /* Description */
                if ($desc !== '') {
                    $h .= '<div class="donut-aside-desc">' . $this->e($desc) . '</div>';
                }

                /* ── SVG ring ── */
                $h .= '<div class="donut-visual">'
                    . '<div class="donut-svg-wrap">'
                    . '<svg class="donut-svg" viewBox="0 0 200 200">';

                $h .= '<circle cx="100" cy="100" r="' . $radius . '" fill="none"'
                    . ' stroke="var(--border)" stroke-width="' . $strokeW . '" />';

                $cumulative = 0;
                foreach ($data as $i => $v) {
                    $val = floatval($v);
                    $segLen = $total > 0 ? ($val / $total) * $circum : 0;
                    $offset = -$cumulative;
                    $clr = $colors[$i % count($colors)];

                    $h .= '<circle class="donut-seg" data-seg="' . $i . '"'
                        . ' cx="100" cy="100" r="' . $radius . '"'
                        . ' fill="none" stroke="' . $clr . '"'
                        . ' stroke-width="' . $strokeW . '"'
                        . ' stroke-dasharray="' . round($segLen, 3) . ' ' . round($circum, 3) . '"'
                        . ' stroke-dashoffset="' . round($offset, 3) . '"'
                        . ' transform="rotate(-90 100 100)"'
                        . ' style="cursor:pointer;transition:opacity .3s,stroke-width .3s,filter .3s" />';

                    $cumulative += $segLen;
                }

                $h .= '</svg>';

                if ($isDoughnut) {
                    $h .= '<div class="donut-hole">'
                        . '<span class="donut-total">' . $this->e((string)round($total)) . '</span>'
                        . '<span class="donut-total-label">всего</span>'
                        . '</div>';
                }
                $h .= '</div></div>';

                /* ── Aside: legend + detail ── */
                $h .= '<div class="donut-aside">';

                /* Legend */
                $h .= '<div class="donut-legend">';
                foreach ($labels as $i => $label) {
                    $v = floatval($data[$i] ?? 0);
                    $pctLabel = $total > 0 ? round(($v / $total) * 100, 1) : 0;
                    $clr = $colors[$i % count($colors)];
                    $h .= '<button class="donut-legend-item" data-seg-btn="' . $i . '">'
                        . '<span class="donut-legend-dot" style="background:' . $clr . '"></span>'
                        . '<span class="donut-legend-text">' . $this->e($label) . '</span>'
                        . '<span class="donut-legend-val">' . $this->e((string)$v) . ' <small>(' . $pctLabel . '%)</small></span>'
                        . '</button>';
                }
                $h .= '</div>';

                /* Segment detail */
                $segDescsJson = json_encode(
                    array_map(fn($i) => [
                        'label' => $labels[$i] ?? '',
                        'desc'  => $descriptions[$i] ?? '',
                        'color' => $colors[$i % count($colors)],
                    ], array_keys($data)),
                    JSON_UNESCAPED_UNICODE
                );
                $h .= '<div class="donut-detail" data-donut-detail>'
                    . '<div class="donut-detail-wrap">'
                    . '<div class="donut-detail-inner">'
                    . '<span class="donut-detail-dot"></span>'
                    . '<span class="donut-detail-label"></span>'
                    . '</div>'
                    . '<div class="donut-detail-desc"></div>'
                    . '</div>'
                    . '</div>';
                $h .= '<script type="application/json" class="donut-seg-data">'
                    . str_replace('</', '<\\/', $segDescsJson) . '</script>';

                $h .= '</div>'; /* /donut-aside */
                $h .= '</div>'; /* /donut-layout */

                /* We already rendered desc inside aside, so clear the earlier one */

            } else {
                /* Fallback to Chart.js for complex datasets */
                $cfg = $this->buildPremiumChartConfig($chartType, $labels, $datasets);
                $h .= '<div class="chartjs-wrap chartjs-wrap--donut"><canvas id="chart-' . $id . '"></canvas></div>';
                $h .= '<script>document.addEventListener("DOMContentLoaded",function(){'
                    . 'new Chart(document.getElementById("chart-' . $id . '"),'
                    . str_replace('</', '<\\/', $cfg) . ');});</script>';
            }

        } elseif ($chartType === 'line') {
            /* ── Premium Line Chart ── */
            $cfg = $this->buildPremiumChartConfig($chartType, $labels, $datasets);
            $h .= '<div class="chartjs-wrap chartjs-wrap--line"><canvas id="chart-' . $id . '"></canvas></div>';
            $h .= '<script>document.addEventListener("DOMContentLoaded",function(){'
                . 'var ctx=document.getElementById("chart-' . $id . '").getContext("2d");'
                . 'var grad=ctx.createLinearGradient(0,0,0,300);'
                . 'grad.addColorStop(0,"rgba(37,99,235,0.25)");grad.addColorStop(1,"rgba(37,99,235,0.01)");'
                . 'var cfg=' . str_replace('</', '<\\/', $cfg) . ';'
                . 'if(cfg.data&&cfg.data.datasets&&cfg.data.datasets[0]){'
                . 'cfg.data.datasets[0].backgroundColor=grad;cfg.data.datasets[0].fill=true;'
                . 'cfg.data.datasets[0].borderColor="#2563EB";cfg.data.datasets[0].borderWidth=2.5;'
                . 'cfg.data.datasets[0].pointBackgroundColor="#fff";cfg.data.datasets[0].pointBorderColor="#2563EB";'
                . 'cfg.data.datasets[0].pointBorderWidth=2;cfg.data.datasets[0].pointRadius=4;'
                . 'cfg.data.datasets[0].pointHoverRadius=6;cfg.data.datasets[0].tension=0.4;'
                . '}'
                . 'new Chart(ctx,cfg);});</script>';

        } else {
            /* ── Fallback: Chart.js with premium config ── */
            $cfg = $this->buildPremiumChartConfig($chartType, $labels, $datasets);
            $h .= '<div class="chartjs-wrap"><canvas id="chart-' . $id . '"></canvas></div>';
            $h .= '<script>document.addEventListener("DOMContentLoaded",function(){'
                . 'new Chart(document.getElementById("chart-' . $id . '"),'
                . str_replace('</', '<\\/', $cfg) . ');});</script>';
        }

        return $h . '</div></div><div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
    }

    private function buildPremiumChartConfig(string $type, array $labels, array $datasets): string {
        $isCircular = in_array($type, ['doughnut', 'pie']);
        $isLine     = ($type === 'line');

        $options = [
            'responsive'          => true,
            'maintainAspectRatio' => true,
            'animation'           => ['duration' => 800, 'easing' => 'easeOutQuart'],
            'plugins'             => [
                'legend' => [
                    'position' => $isCircular ? 'bottom' : 'top',
                    'labels'   => [
                        'padding'       => 20,
                        'usePointStyle' => true,
                        'pointStyle'    => 'circle',
                        'font'          => ['size' => 13, 'family' => '"Onest",sans-serif'],
                    ],
                ],
                'tooltip' => [
                    'backgroundColor' => 'rgba(15,23,42,0.92)',
                    'titleFont'       => ['size' => 13, 'family' => '"Geologica",sans-serif', 'weight' => 700],
                    'bodyFont'        => ['size' => 12, 'family' => '"Onest",sans-serif'],
                    'padding'         => 14,
                    'cornerRadius'    => 10,
                    'displayColors'   => true,
                    'boxPadding'      => 6,
                ],
            ],
        ];

        if ($isCircular) {
            $options['cutout'] = ($type === 'doughnut') ? '62%' : '0%';
            $options['elements'] = ['arc' => ['borderWidth' => 2, 'borderColor' => 'rgba(255,255,255,0.9)']];
        } else {
            $options['scales'] = [
                'x' => [
                    'grid'   => ['display' => false],
                    'ticks'  => ['font' => ['size' => 12, 'family' => '"Onest",sans-serif'], 'color' => '#64748B'],
                    'border' => ['display' => false],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'grid'        => ['color' => 'rgba(0,0,0,0.04)', 'drawBorder' => false],
                    'ticks'       => ['font' => ['size' => 12, 'family' => '"Onest",sans-serif'], 'color' => '#64748B', 'padding' => 8],
                    'border'      => ['display' => false, 'dash' => [4, 4]],
                ],
            ];
            if ($isLine) {
                $options['elements'] = ['line' => ['tension' => 0.4]];
            } else {
                $options['elements'] = ['bar' => ['borderRadius' => 8, 'borderSkipped' => false]];
                $options['barPercentage'] = 0.65;
            }
        }

        return json_encode([
            'type'    => $type,
            'data'    => ['labels' => $labels, 'datasets' => $datasets],
            'options' => $options,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function renderComparison(array $c, string $id): string {
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';
        $headers = $c['headers'] ?? [];
        $rows    = $c['rows'] ?? [];
        $title   = $this->e($c['title'] ?? 'Сравнение');

        /* Detect: if ≥ 3 columns and first column is a label → tabbed card view */
        $useTabs = (count($headers) >= 3 && count($rows) >= 2);

        $h = '<section id="' . $id . '" class="block-comparison reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<h2 class="sec-title">' . $title . '</h2>';

        /* Optional description */
        $desc = trim($c['description'] ?? '');
        if ($desc !== '') {
            $h .= '<p class="cmp-desc">' . $this->e($desc) . '</p>';
        }

        if ($useTabs) {
            /* ── Tabbed Cards (Claude-Code style) ── */
            /* First header is row label (e.g. "Feature"), rest are the tab names */
            $tabHeaders = array_slice($headers, 1);
            $rowLabel   = $headers[0] ?? '';

            /* Tab navigation */
            $h .= '<div class="cmp-tabs" data-tabs="' . $id . '">'
                . '<div class="cmp-tabs-nav">';
            foreach ($tabHeaders as $ti => $th) {
                $label = $this->e(is_string($th) ? $th : ($th['label'] ?? ''));
                $h .= '<button class="cmp-tab-btn' . ($ti === 0 ? ' is-active' : '') . '" data-tab="' . $ti . '">' . $label . '</button>';
            }
            $h .= '</div>';

            /* Tab panels — each panel shows rows for that column */
            foreach ($tabHeaders as $ti => $th) {
                $h .= '<div class="cmp-tab-panel' . ($ti === 0 ? ' is-active' : '') . '" data-panel="' . $ti . '">';
                $h .= '<div class="cmp-cards-grid">';
                foreach ($rows as $row) {
                    $cells = is_array($row) ? (isset($row[0]) ? $row : array_values($row)) : [$row];
                    $feature = $this->e((string)($cells[0] ?? ''));
                    $value   = (string)($cells[$ti + 1] ?? '');
                    $isYes   = in_array($value, ['✓', 'true', '1', 'да', 'Да'], true);
                    $isNo    = in_array($value, ['✗', 'false', '0', 'нет', 'Нет'], true);
                    $valClass = $isYes ? ' cmp-card-val--yes' : ($isNo ? ' cmp-card-val--no' : '');

                    $displayVal = $isYes ? '✓' : ($isNo ? '✗' : $this->e($value));

                    $h .= '<div class="cmp-card">'
                        . '<div class="cmp-card-feature">' . $feature . '</div>'
                        . '<div class="cmp-card-val' . $valClass . '">' . $displayVal . '</div>'
                        . '</div>';
                }
                $h .= '</div></div>';
            }
            $h .= '</div>';

            /* ── Desktop carousel below tabs (quick overview) ── */
            $h .= '<div class="cmp-carousel" data-carousel="' . $id . '">'
                . '<div class="cmp-carousel-track">';
            foreach ($tabHeaders as $ti => $th) {
                $label = $this->e(is_string($th) ? $th : ($th['label'] ?? ''));
                /* Count yes/positive values for this column */
                $yesCount = 0;
                $totalRows = count($rows);
                foreach ($rows as $row) {
                    $cells = is_array($row) ? (isset($row[0]) ? $row : array_values($row)) : [$row];
                    $v = (string)($cells[$ti + 1] ?? '');
                    if (in_array($v, ['✓', 'true', '1', 'да', 'Да'], true)) $yesCount++;
                }
                $h .= '<div class="cmp-carousel-card">'
                    . '<div class="cmp-carousel-card-title">' . $label . '</div>'
                    . '<div class="cmp-carousel-card-stat">' . $yesCount . '/' . $totalRows . '</div>'
                    . '<div class="cmp-carousel-card-label">совпадений</div>'
                    . '</div>';
            }
            $h .= '</div>';
            if (count($tabHeaders) > 3) {
                $h .= '<div class="cmp-carousel-nav">'
                    . '<button class="cmp-carousel-btn cmp-carousel-btn--prev" aria-label="Назад">←</button>'
                    . '<div class="cmp-carousel-dots"></div>'
                    . '<button class="cmp-carousel-btn cmp-carousel-btn--next" aria-label="Вперед">→</button>'
                    . '</div>';
            }
            $h .= '</div>';

        } else {
            /* ── Fallback: Premium responsive table ── */
            $h .= '<div class="mac-window">'
                . '<div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
                . '<div class="mac-title">' . $title . '</div></div>'
                . '<div class="mac-body">'
                . '<div class="table-wrap"><table class="cmp-table premium-table">';

            /* Build data-label attrs for mobile */
            $h .= '<thead><tr>';
            foreach ($headers as $hd) {
                $h .= '<th>' . $this->e(is_string($hd) ? $hd : ($hd['label'] ?? '')) . '</th>';
            }
            $h .= '</tr></thead><tbody>';
            foreach ($rows as $row) {
                $h .= '<tr>';
                $cells = is_array($row) ? (isset($row[0]) ? $row : array_values($row)) : [$row];
                foreach ($cells as $ci => $cell) {
                    $v   = (string)$cell;
                    $cls = '';
                    $dlabel = isset($headers[$ci]) ? ' data-label="' . $this->e(is_string($headers[$ci]) ? $headers[$ci] : ($headers[$ci]['label'] ?? '')) . '"' : '';
                    if (in_array($v, ['✓', 'true', '1'], true)) { $v = '✓'; $cls = ' class="cell-yes"'; }
                    elseif (in_array($v, ['✗', 'false', '0'], true)) { $v = '✗'; $cls = ' class="cell-no"'; }
                    $h .= '<td' . $cls . $dlabel . '>' . $this->e($v) . '</td>';
                }
                $h .= '</tr>';
            }
            $h .= '</tbody></table></div></div></div>';
        }

        return $h . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
    }

    private function renderImageSection(array $c, string $id): string {
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
        $h = '<section id="' . $id . '" class="block-imgsec reveal' . $revCls . '" ' . $tocAttr . '>'
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

    private function renderFaq(array $c, string $id): string {
        $items   = $c['items'] ?? [];
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';
        $schema  = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => []];

        $h = '<section id="' . $id . '" class="block-faq reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="FAQ">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<h2 class="sec-title">Часто задаваемые вопросы</h2>'
            . '<div class="faq-list">';

        foreach ($items as $it) {
            $q = $this->e($it['question'] ?? '');
            $a = $this->e($it['answer'] ?? '');
            $h .= '<div class="faq-item">'
                . '<button class="faq-q">' . $q . '<span class="faq-arr">+</span></button>'
                . '<div class="faq-a"><div class="faq-a-in">' . $a . '</div></div>'
                . '</div>';
            $schema['mainEntity'][] = [
                '@type'          => 'Question',
                'name'           => $it['question'] ?? '',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $it['answer'] ?? ''],
            ];
        }
        $h .= '</div><div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        if (!empty($schema['mainEntity'])) {
            $h .= '<script type="application/ld+json">'
                . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                . '</script>' . "\n";
        }
        return $h;
    }

    private function renderCta(array $c, string $id): string {
        $h = '<section id="' . $id . '" class="block-cta reveal">'
            . '<div class="cta-orb cta-orb--1"></div>'
            . '<div class="cta-orb cta-orb--2"></div>'
            . '<div class="cta-orb cta-orb--3"></div>'
            . '<div class="container" style="position:relative">';
        if (!empty($c['title'])) $h .= '<h2>' . $this->e($c['title']) . '</h2>';
        if (!empty($c['text']))  $h .= '<p>' . $this->e($c['text']) . '</p>';
        $h .= '<div class="cta-buttons">';
        if (!empty($c['primary_btn_text'])) {
            $h .= '<a class="btn-primary" href="{{link:' . $this->e($c['primary_btn_link_key'] ?? '') . '}}">'
                . $this->e($c['primary_btn_text']) . '</a>';
        }
        if (!empty($c['secondary_btn_text'])) {
            $h .= '<a class="btn-outline" href="{{link:' . $this->e($c['secondary_btn_link_key'] ?? '') . '}}">'
                . $this->e($c['secondary_btn_text']) . '</a>';
        }
        return $h . '</div></div></section>' . "\n";
    }

    private function renderFeatureGrid(array $c, string $id): string {
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-features reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="Особенности">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="features-grid">';
        foreach (array_values($c['items'] ?? $c['features'] ?? []) as $i => $it) {
            $num = str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT);
            $h .= '<div class="feature-card">'
                . '<div class="feature-icon">' . $this->e($it['icon'] ?? '📋') . '</div>'
                . '<div class="feature-num">' . $num . '</div>'
                . '<h4>' . $this->e($it['title'] ?? '') . '</h4>'
                . '<p>' . $this->e($it['description'] ?? $it['text'] ?? '') . '</p>'
                . '</div>';
        }
        return $h . '</div><div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
    }

    private function renderTestimonial(array $c, string $id): string {
        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-reviews reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="Отзывы">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="reviews-grid">';
        foreach ($c['items'] ?? $c['testimonials'] ?? [] as $it) {
            $text   = $this->e($it['text'] ?? $it['content'] ?? '');
            $author = $this->e($it['author'] ?? $it['name'] ?? '');
            $role   = $this->e($it['role'] ?? '');
            $words  = explode(' ', strip_tags($it['author'] ?? $it['name'] ?? '?'));
            $init   = strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice($words, 0, 2))));

            $h .= '<div class="review-card">'
                . '<div class="review-stars">★★★★★</div>'
                . '<div class="review-text">«' . $text . '»</div>'
                . '<div class="review-who">'
                . '<div class="review-av">' . $this->e($init) . '</div>'
                . '<div><div class="review-name">' . $author . '</div>'
                . ($role ? '<div class="review-role">' . $role . '</div>' : '')
                . '</div></div></div>';
        }
        return $h . '</div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
    }

    private function renderGaugeChart(array $c, string $id): string {
        $title = $this->e($c['title'] ?? 'Показатели');
        $items = $c['items'] ?? [];
        $jsonData = json_encode($items, JSON_UNESCAPED_UNICODE);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-gauge reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="mac-window">'
            . '<div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">' . $title . '</div></div>'
            . '<div class="mac-body">'
            . '<div class="gauge-grid" data-gauges="' . $id . '"></div>'
            . '<div class="gauge-detail" data-gauge-detail="' . $id . '"><div><div class="gauge-detail-inner">'
            . '<div class="gauge-detail-header"><span class="gauge-detail-dot"></span><span class="gauge-detail-label"></span><span class="gauge-detail-val"></span></div>'
            . '<div class="gauge-detail-desc"></div>'
            . '<div class="gauge-detail-bar-track"><div class="gauge-detail-bar"></div></div>'
            . '</div></div></div>'
            . '<script type="application/json" class="gauge-data">' . str_replace('</', '<\\/', $jsonData) . '</script>'
            . '</div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";

        return $h;
    }

    private function renderTimeline(array $c, string $id): string {
        $title = $this->e($c['title'] ?? 'Этапы');
        $items = $c['items'] ?? [];
        $colors = ['var(--blue)', 'var(--teal)', 'var(--purple)', 'var(--green)', 'var(--orange)',
            'var(--pink)', 'var(--cyan)', 'var(--warn)', 'var(--red)', 'var(--blue-dark)'];

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-timeline reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<h2 class="sec-title">' . $title . '</h2>'
            . '<div class="mac-window"><div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">Процесс</div></div>'
            . '<div class="mac-body">'
            . '<div class="tl-wrap" data-timeline="' . $id . '">'
            . '<div class="tl-line"></div>'
            . '<div class="tl-line-fill"></div>';

        foreach ($items as $i => $it) {
            $color = $this->e($it['color'] ?? $colors[$i % count($colors)]);
            $step = $this->e(('Шаг ' . $it['step']) ?? ('Шаг ' . ($i + 1)));
            $itTitle = $this->e($it['title'] ?? '');
            $summary = $this->e($it['summary'] ?? '');
            $detail = $this->e($it['detail'] ?? '');
            $meta = $this->e($it['meta'] ?? '');

            $h .= '<div class="tl-item" style="--tl-c:' . $color . '">'
                . '<div class="tl-dot"></div>'
                . '<div class="tl-card">'
                . '<div class="tl-step" style="color:' . $color . '">' . $step . '</div>'
                . '<div class="tl-title">' . $itTitle . '</div>'
                . '<p class="tl-summary">' . $summary . '</p>'
                . '<div class="tl-expand"><div>'
                . '<div class="tl-detail">' . $detail . '</div>'
                . ($meta ? '<div class="tl-meta">⏱ ' . $meta . '</div>' : '')
                . '</div></div>'
                . '</div></div>';
        }

        $h .= '</div></div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    private function renderHeatmap(array $c, string $id): string {
        $title = $this->e($c['title'] ?? 'Карта активности');
        $jsonData = json_encode([
            'rows' => $c['rows'] ?? [],
            'columns' => $c['columns'] ?? [],
            'data' => $c['data'] ?? [],
            'description' => $c['description'] ?? '',
        ], JSON_UNESCAPED_UNICODE);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-heatmap reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="mac-window"><div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">' . $title . '</div></div>'
            . '<div class="mac-body">'
            . '<div class="hm-wrap" data-heatmap="' . $id . '">'
            . '<div class="hm-legend"><span>Мин</span><div class="hm-legend-bar"></div><span>Макс</span></div>'
            . '<div class="hm-grid"></div>'
            . '<div class="hm-info"><div><div class="hm-info-inner">'
            . '<div class="hm-info-swatch"></div>'
            . '<div class="hm-info-text"><b></b><span></span></div>'
            . '</div></div></div>'
            . '</div>'
            . '<script type="application/json" class="hm-data">' . str_replace('</', '<\\/', $jsonData) . '</script>'
            . '</div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";

        return $h;
    }

    private function renderFunnel(array $c, string $id): string {
        $title = $this->e($c['title'] ?? 'Воронка');
        $items = $c['items'] ?? [];
        $jsonData = json_encode($items, JSON_UNESCAPED_UNICODE);
        $desc = trim($c['description'] ?? '');

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-funnel reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="mac-window"><div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">' . $title . '</div></div>'
            . '<div class="mac-body">';

        if ($desc !== '') {
            $h .= '<p class="chart-desc">' . $this->e($desc) . '</p>';
        }

        $h .= '<div class="fn-wrap" data-funnel="' . $id . '"></div>'
            . '<div class="fn-detail" data-fn-detail="' . $id . '"><div><div class="fn-detail-inner">'
            . '<div class="fn-detail-head"><span class="fn-detail-dot"></span><span class="fn-detail-name"></span><span class="fn-detail-big"></span></div>'
            . '<div class="fn-detail-desc"></div>'
            . '</div></div></div>'
            . '<script type="application/json" class="fn-data">' . str_replace('</', '<\\/', $jsonData) . '</script>'
            . '</div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";

        return $h;
    }

    private function renderSparkMetrics(array $c, string $id): string {
        $title = $this->e($c['title'] ?? 'Метрики');
        $items = $c['items'] ?? [];
        $jsonData = json_encode($items, JSON_UNESCAPED_UNICODE);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-spark reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<h2 class="sec-title">' . $title . '</h2>'
            . '<div class="sp-grid" data-sparks="' . $id . '"></div>'
            . '<div class="sp-detail" data-sp-detail="' . $id . '"><div><div class="sp-detail-inner"></div></div></div>'
            . '<script type="application/json" class="sp-data">' . str_replace('</', '<\\/', $jsonData) . '</script>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";

        return $h;
    }

    private function renderRadarChart(array $c, string $id): string {
        $title = $this->e($c['title'] ?? 'Профиль');
        $jsonData = json_encode([
            'axes' => $c['axes'] ?? [],
            'color' => $c['color'] ?? '#2563EB',
        ], JSON_UNESCAPED_UNICODE);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-radar reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="mac-window"><div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">' . $title . '</div></div>'
            . '<div class="mac-body">'
            . '<div class="radar-layout" data-radar="' . $id . '">'
            . '<div class="radar-svg-wrap"><svg class="radar-svg" viewBox="0 0 260 260"></svg></div>'
            . '<div class="radar-aside"></div>'
            . '</div>'
            . '<div class="radar-detail" data-radar-detail="' . $id . '"><div><div class="radar-detail-inner"></div></div></div>'
            . '<script type="application/json" class="radar-data">' . str_replace('</', '<\\/', $jsonData) . '</script>'
            . '</div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";

        return $h;
    }

    private function renderBeforeAfter(array $c, string $id): string {
        $title = $this->e($c['title'] ?? 'До и после');
        $metrics = $c['metrics'] ?? [];
        $jsonData = json_encode($metrics, JSON_UNESCAPED_UNICODE);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-beforeafter reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="mac-window"><div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">' . $title . '</div></div>'
            . '<div class="mac-body">'
            . '<div class="ba-container" data-beforeafter="' . $id . '"></div>'
            . '<script type="application/json" class="ba-data">' . str_replace('</', '<\\/', $jsonData) . '</script>'
            . '</div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";

        return $h;
    }

    private function renderStackedArea(array $c, string $id): string {
        $title = $this->e($c['title'] ?? 'Динамика');
        $jsonData = json_encode([
            'labels' => $c['labels'] ?? [],
            'series' => $c['series'] ?? [],
        ], JSON_UNESCAPED_UNICODE);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-stacked reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="mac-window"><div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">' . $title . '</div></div>'
            . '<div class="mac-body">'
            . '<div class="sa-layout" data-stacked="' . $id . '">'
            . '<div>'
            . '<div class="sa-chart"><svg class="sa-svg" viewBox="0 0 600 200" preserveAspectRatio="none"></svg>'
            . '<div class="sa-x-labels"></div></div>'
            . '<div class="sa-detail"><div><div class="sa-detail-inner"></div></div></div>'
            . '</div>'
            . '<div class="sa-legend"></div>'
            . '</div>'
            . '<script type="application/json" class="sa-data">' . str_replace('</', '<\\/', $jsonData) . '</script>'
            . '</div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";

        return $h;
    }

    private function renderScoreRings(array $c, string $id): string {
        $title = $this->e($c['title'] ?? 'Индекс здоровья');
        $totalLabel = $this->e($c['total_label'] ?? 'общий балл');
        $rings = $c['rings'] ?? [];
        $jsonData = json_encode([
            'rings' => $rings,
            'total_label' => $c['total_label'] ?? 'общий балл',
        ], JSON_UNESCAPED_UNICODE);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-rings reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="mac-window"><div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">' . $title . '</div></div>'
            . '<div class="mac-body">'
            . '<div class="ring-layout" data-rings="' . $id . '">'
            . '<div class="ring-svg-wrap">'
            . '<svg class="ring-svg" viewBox="0 0 220 220"></svg>'
            . '<div class="ring-center"><span class="ring-center-val"></span><span class="ring-center-label">' . $totalLabel . '</span></div>'
            . '</div>'
            . '<div>'
            . '<div class="ring-aside"></div>'
            . '<div class="ring-detail"><div><div class="ring-detail-inner"></div></div></div>'
            . '</div>'
            . '</div>'
            . '<script type="application/json" class="ring-data">' . str_replace('</', '<\\/', $jsonData) . '</script>'
            . '</div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";

        return $h;
    }

    private function renderRangeComparison(array $c, string $id): string {
        $title = $this->e($c['title'] ?? 'Референсные диапазоны');
        $jsonData = json_encode([
            'groups' => $c['groups'] ?? [],
            'rows' => $c['rows'] ?? [],
        ], JSON_UNESCAPED_UNICODE);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-ranges reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="mac-window"><div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">' . $title . '</div></div>'
            . '<div class="mac-body">'
            . '<div data-ranges="' . $id . '">'
            . '<div class="rc-toggle"></div>'
            . '<div class="rc-wrap"></div>'
            . '<div class="rc-detail"><div><div class="rc-detail-inner"></div></div></div>'
            . '</div>'
            . '<script type="application/json" class="rc-data">' . str_replace('</', '<\\/', $jsonData) . '</script>'
            . '</div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";

        return $h;
    }


    private function buildToc(array $blocks): string {
        $items = [];
        foreach ($blocks as $b) {
            $bid     = 'block-' . $b['id'];
            $content = is_string($b['content']) ? json_decode($b['content'], true) : ($b['content'] ?? []);
            $label   = $this->tocLabel($b['type'], $content, $b);
            if (mb_strlen($label) > 26) $label = mb_substr($label, 0, 24) . '…';
            $items[] = '<a href="#' . $this->e($bid) . '" class="toc-link" data-target="' . $this->e($bid) . '">'
                . $this->e($label) . '</a>';
        }
        if (empty($items)) return '';

        return '<nav class="toc" id="toc">'
            . '<div class="toc-inner">'
            . '<div class="toc-label">Содержание</div>'
            . implode('', $items)
            . '</div></nav>';
    }

    private function tocLabel(string $type, array $c, array $meta): string {
        switch ($type) {
            case 'hero':             return $c['title'] ?? 'Введение';
            case 'stats_counter':    return 'Показатели';
            case 'richtext':
                foreach ($c['blocks'] ?? [] as $bl) {
                    if (($bl['type'] ?? '') === 'heading' && ($bl['level'] ?? 3) <= 3) {
                        return $bl['content'] ?? $bl['text'] ?? '';
                    }
                }
                return $meta['name'] ?? 'Описание';
            case 'norms_table':      return $c['caption'] ?? 'Нормы';
            case 'accordion':        return 'Подробности';
            case 'chart':            return $c['title'] ?? 'График';
            case 'comparison_table': return $c['title'] ?? 'Сравнение';
            case 'image_section':    return $c['title'] ?? 'Иллюстрация';
            case 'faq':              return 'FAQ';
            case 'cta':              return $c['title'] ?? 'Действие';
            case 'feature_grid':     return 'Особенности';
            case 'testimonial':      return 'Отзывы';
            case 'gauge_chart':      return $c['title'] ?? 'Показатели';
            case 'timeline':         return $c['title'] ?? 'Этапы';
            case 'heatmap':          return $c['title'] ?? 'Карта активности';
            case 'funnel':           return $c['title'] ?? 'Воронка';
            case 'spark_metrics':    return $c['title'] ?? 'Метрики';
            case 'radar_chart':      return $c['title'] ?? 'Профиль';
            case 'before_after':     return $c['title'] ?? 'До и после';
            case 'stacked_area':     return $c['title'] ?? 'Динамика';
            case 'score_rings':      return $c['title'] ?? 'Индекс';
            case 'range_comparison': return $c['title'] ?? 'Диапазоны';
            case 'value_checker':    return $c['title'] ?? 'Проверьте результат';
            case 'symptom_checklist':return $c['title'] ?? 'Чеклист симптомов';
            case 'prep_checklist':   return $c['title'] ?? 'Подготовка';
            case 'info_cards':       return $c['title'] ?? 'Факты';
            case 'story_block':      return $c['lead'] ?? 'История';
            case 'verdict_card':     return $c['title'] ?? 'Мифы и факты';
            case 'numbered_steps':   return $c['title'] ?? 'Пошаговый план';
            case 'warning_block':    return $c['title'] ?? 'Внимание';
            case 'mini_calculator':  return $c['title'] ?? 'Калькулятор';
            case 'comparison_cards': return $c['title'] ?? 'Сравнение';
            case 'progress_tracker': return $c['title'] ?? 'Прогресс';
            case 'key_takeaways':    return $c['title'] ?? 'Главное';
            case 'expert_panel':     return ($c['name'] ?? '') ? 'Мнение: ' . ($c['name'] ?? '') : 'Мнение эксперта';
            default:                 return $meta['name'] ?? $type;
        }
    }

    private function buildNavbarSearch(int $articleId): string {
        $apiUrl   = json_encode(SEO_SEARCH_SCRIPT);
        $excludeId = $articleId;

        return
            '<div class="nav-search-wrap" id="navSearchWrap">' .
            '<button class="nav-search-toggle" id="navSearchToggle" aria-label="Поиск статей" title="Поиск статей">' .
            '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"' .
            ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' .
            '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>' .
            '</svg>' .
            '</button>' .
            '<div class="nav-search-box" id="navSearchBox">' .
            '<input type="search" id="navSearchInput" class="nav-search-input"' .
            ' placeholder="Поиск статей…" autocomplete="off" aria-label="Поиск">' .
            '<div class="nav-search-results" id="navSearchResults" role="listbox" aria-live="polite"></div>' .
            '</div>' .
            '</div>' .
            '<script>' .
            '(function(){' .
            'var API=' . $apiUrl . ', EXCL=' . $excludeId . ', DEB=280;' .
            'var toggle=document.getElementById(\'navSearchToggle\');' .
            'var box=document.getElementById(\'navSearchBox\');' .
            'var input=document.getElementById(\'navSearchInput\');' .
            'var results=document.getElementById(\'navSearchResults\');' .
            'var timer=null, last=\'\';' .

            'toggle.addEventListener(\'click\',function(){' .
            'var open=box.classList.toggle(\'is-open\');' .
            'toggle.classList.toggle(\'is-active\',open);' .
            'if(open){input.focus();}else{clear();}' .
            '});' .
            'document.addEventListener(\'click\',function(e){' .
            'if(!document.getElementById(\'navSearchWrap\').contains(e.target)){' .
            'box.classList.remove(\'is-open\');' .
            'toggle.classList.remove(\'is-active\');' .
            'clear();' .
            '}' .
            '});' .
            'input.addEventListener(\'input\',function(){' .
            'var q=input.value.trim();' .
            'clearTimeout(timer);' .
            'if(q.length<2){clear();return;}' .
            'if(q===last)return;' .
            'timer=setTimeout(function(){fetch_results(q);},DEB);' .
            '});' .
            'input.addEventListener(\'keydown\',function(e){' .
            'var items=results.querySelectorAll(\'.ns-item\');' .
            'if(!items.length)return;' .
            'var active=results.querySelector(\'.ns-item.is-focused\');' .
            'if(e.key===\'ArrowDown\'){' .
            'e.preventDefault();' .
            'var next=active?active.nextElementSibling:items[0];' .
            'if(next){if(active)active.classList.remove(\'is-focused\');next.classList.add(\'is-focused\');next.focus();}' .
            '}else if(e.key===\'ArrowUp\'){' .
            'e.preventDefault();' .
            'var prev=active?active.previousElementSibling:items[items.length-1];' .
            'if(prev){if(active)active.classList.remove(\'is-focused\');prev.classList.add(\'is-focused\');prev.focus();}' .
            '}else if(e.key===\'Escape\'){' .
            'box.classList.remove(\'is-open\');' .
            'toggle.classList.remove(\'is-active\');' .
            'clear();' .
            '}' .
            '});' .

            'function fetch_results(q){' .
            'last=q;' .
            'results.innerHTML=\'<div class="ns-loading">Поиск…</div>\';' .
            'fetch(API+\'?q=\'+encodeURIComponent(q)+\'&exclude=\'+EXCL+\'&limit=8\')' .
            '.then(function(r){return r.json();})' .
            '.then(function(d){render(d.results||[]);})' .
            '.catch(function(){results.innerHTML=\'<div class="ns-error">Ошибка поиска</div>\';});' .
            '}' .
            'function render(items){' .
            'if(!items.length){results.innerHTML=\'<div class="ns-empty">Ничего не найдено</div>\';return;}' .
            'results.innerHTML=items.map(function(it){' .
            'var d=it.description?\'<span class="ns-desc">\'+esc(it.description)+\'</span>\':\'\';' .
            'var spl=it.url.split("articles/");' .
            'var href="' . SEO_BASE_ART_URL . '"+spl[1];' .
            'return \'<a class="ns-item" href="\'+esc(href)+\'" tabindex="0"><span class="ns-title">\'+esc(it.title)+\'</span>\'+d+\'</a>\';' .
            '}).join(\'\');' .
            '}' .
            'function clear(){results.innerHTML=\'\';last=\'\';}' .
            'function esc(s){return String(s).replace(/&/g,\'&amp;\').replace(/</g,\'&lt;\').replace(/>/g,\'&gt;\').replace(/"/g,\'&quot;\');}' .
            '})();' .
            '</script>';
    }

    private function renderValueChecker(array $c, string $id): string {
        $title = $this->e($c['title'] ?? 'Проверьте свой результат');
        $desc  = $this->e($c['description'] ?? '');
        $label = $this->e($c['input_label'] ?? 'Значение');
        $ph    = $this->e($c['input_placeholder'] ?? '');
        $disclaimer = $this->e($c['disclaimer'] ?? '');
        $zones = $c['zones'] ?? [];
        $jsonData = json_encode($zones, JSON_UNESCAPED_UNICODE);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        /* Calculate proportional flex weights for zones */
        $totalRange = 0;
        foreach ($zones as $z) {
            $totalRange += max(1, ($z['to'] ?? 0) - ($z['from'] ?? 0));
        }

        $h = '<section id="' . $id . '" class="block-vcheck reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<h2 class="sec-title">' . $title . '</h2>'
            . ($desc ? '<p class="sec-desc">' . $desc . '</p>' : '')
            . '<div class="mac-window"><div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">' . $label . '</div></div>'
            . '<div class="mac-body">'
            . '<div class="vc-wrap" data-vcheck="' . $id . '">'
            . '<div class="vc-input-row">'
            . '<input type="number" class="vc-input" placeholder="' . $ph . '" step="any">'
            . '<button class="vc-btn">Проверить</button>'
            . '</div>'
            . '<div class="vc-scale"><div class="vc-scale-track">';
        foreach ($zones as $z) {
            $range = max(1, ($z['to'] ?? 0) - ($z['from'] ?? 0));
            $flex = $totalRange > 0 ? round($range / $totalRange * 100, 2) : 1;
            $h .= '<div class="vc-zone" style="flex:' . $flex . ';background:' . $this->e($z['color'] ?? '#ccc') . '" title="' . $this->e($z['label'] ?? '') . '"></div>';
        }
        $h .= '</div><div class="vc-marker" style="display:none"><div class="vc-marker-dot"></div><div class="vc-marker-line"></div></div></div>'
            . '<div class="vc-result" style="display:none">'
            . '<div class="vc-result-icon"></div>'
            . '<div class="vc-result-label"></div>'
            . '<div class="vc-result-text"></div>'
            . '</div>'
            . ($disclaimer ? '<div class="vc-disclaimer">' . $disclaimer . '</div>' : '')
            . '<script type="application/json" class="vc-data">' . str_replace('</', '<\\/', $jsonData) . '</script>'
            . '</div></div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    private function renderSymptomChecklist(array $c, string $id): string {
        $title = $this->e($c['title'] ?? 'Проверьте симптомы');
        $sub   = $this->e($c['subtitle'] ?? '');
        $items = $c['items'] ?? [];
        $thresholds = $c['thresholds'] ?? [];
        $ctaText = $c['cta_text'] ?? '';
        $ctaKey  = $c['cta_link_key'] ?? '';
        $jsonData = json_encode(['items' => $items, 'thresholds' => $thresholds], JSON_UNESCAPED_UNICODE);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-symcheck reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<h2 class="sec-title">' . $title . '</h2>'
            . ($sub ? '<p class="sec-desc">' . $sub . '</p>' : '')
            . '<div class="mac-window"><div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">Тест</div></div>'
            . '<div class="mac-body">'
            . '<div class="sc-wrap" data-symcheck="' . $id . '">'
            . '<div class="sc-progress"><div class="sc-progress-text">Отмечено: <span class="sc-count">0</span></div>'
            . '<div class="bar-track"><div class="sc-bar bar-fill" style="background:var(--blue);width:0"></div></div></div>'
            . '<div class="sc-items">';
        $groups = [];
        foreach ($items as $i => $it) {
            $g = $it['group'] ?? 'другие';
            $groups[$g][] = ['idx' => $i, 'item' => $it];
        }
        foreach ($groups as $gName => $gItems) {
            $h .= '<div class="sc-group"><div class="sc-group-label">' . $this->e(mb_convert_case($gName, MB_CASE_TITLE, 'UTF-8')) . '</div>';
            foreach ($gItems as $gi) {
                $w = (int)($gi['item']['weight'] ?? 1);
                $h .= '<label class="sc-item" data-weight="' . $w . '">'
                    . '<input type="checkbox" class="sc-check">'
                    . '<span class="sc-checkmark"></span>'
                    . '<span class="sc-text">' . $this->e($gi['item']['text'] ?? '') . '</span>'
                    . ($w >= 3 ? '<span class="sc-badge-important">!</span>' : '')
                    . '</label>';
            }
            $h .= '</div>';
        }
        $h .= '</div>'
            . '<div class="sc-result" style="display:none">'
            . '<div class="sc-result-icon"></div>'
            . '<div class="sc-result-label"></div>'
            . '<div class="sc-result-text"></div>'
            . '</div>';
        if ($ctaText) {
            $h .= '<div class="sc-cta" style="display:none"><a class="btn-primary" href="{{link:' . $this->e($ctaKey) . '}}">' . $this->e($ctaText) . '</a></div>';
        }
        $h .= '<script type="application/json" class="sc-data">' . str_replace('</', '<\\/', $jsonData) . '</script>'
            . '</div></div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    private function renderPrepChecklist(array $c, string $id): string {
        $title = $this->e($c['title'] ?? 'Подготовка');
        $sub   = $this->e($c['subtitle'] ?? '');
        $sections = $c['sections'] ?? [];
        $totalItems = 0;
        foreach ($sections as $s) $totalItems += count($s['items'] ?? []);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-prepcheck reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<h2 class="sec-title">' . $title . '</h2>'
            . ($sub ? '<p class="sec-desc">' . $sub . '</p>' : '')
            . '<div class="mac-window"><div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">Чек-лист</div></div>'
            . '<div class="mac-body">'
            . '<div class="pc-wrap" data-prepcheck="' . $id . '" data-total="' . $totalItems . '">'
            . '<div class="pc-progress-row"><span class="pc-progress-text">Готово <b class="pc-done">0</b> из <b>' . $totalItems . '</b></span>'
            . '<div class="bar-track"><div class="pc-bar bar-fill" style="background:var(--green);width:0"></div></div></div>'
            . '<div class="pc-milestone" style="display:none"></div>';
        foreach ($sections as $si => $sec) {
            $icon = $this->e($sec['icon'] ?? '📋');
            $name = $this->e($sec['name'] ?? '');
            $h .= '<div class="pc-section">'
                . '<div class="pc-section-header"><span class="pc-section-icon">' . $icon . '</span><span class="pc-section-name">' . $name . '</span></div>'
                . '<div class="pc-section-items">';
            foreach ($sec['items'] ?? [] as $it) {
                $imp = !empty($it['important']);
                $h .= '<label class="pc-item' . ($imp ? ' pc-item--important' : '') . '">'
                    . '<input type="checkbox" class="pc-check">'
                    . '<span class="pc-checkmark"></span>'
                    . '<span class="pc-text">' . $this->e($it['text'] ?? '') . '</span>'
                    . '</label>';
            }
            $h .= '</div></div>';
        }
        $h .= '<div class="pc-confetti" style="display:none">🎉 Вы готовы!</div>'
            . '</div></div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    private function renderInfoCards(array $c, string $id): string {
        $title  = $this->e($c['title'] ?? '');
        $layout_type = $c['layout'] ?? 'grid-3';
        $items  = $c['items'] ?? [];
        $cols   = $layout_type === 'grid-2' ? 'ic-grid--2' : 'ic-grid--3';

        /* Image support */
        $imgLayout = $this->getImageLayout($c, 'right');
        $imgTop = ($imgLayout === 'top')    ? $this->renderBlockImageOnly($c) : '';
        $imgBot = ($imgLayout === 'bottom') ? $this->renderBlockImageOnly($c) : '';
        $imgH   = ($imgLayout !== 'top' && $imgLayout !== 'bottom') ? $this->renderInlineImage($c, 'right') : '';

        $h = '<section id="' . $id . '" class="block-icards reveal" data-toc="' . ($title ?: 'Факты') . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . ($title ? '<h2 class="sec-title">' . $title . '</h2>' : '')
            . '<div class="ic-grid ' . $cols . '">';
        foreach ($items as $i => $it) {
            $color = $this->e($it['color'] ?? 'var(--blue)');
            $icon  = $this->e($it['icon'] ?? '📋');
            $h .= '<div class="ic-card" style="--ic-c:' . $color . '">'
                . '<div class="ic-icon">' . $icon . '</div>'
                . '<div class="ic-title">' . $this->e($it['title'] ?? '') . '</div>'
                . '<div class="ic-text">' . $this->e($it['text'] ?? '') . '</div>'
                . '</div>';
        }
        $h .= '</div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    private function renderStoryBlock(array $c, string $id): string {
        $variant = $c['variant'] ?? 'patient_story';
        $icon    = $this->e($c['icon'] ?? '💬');
        $accent  = $this->e($c['accent_color'] ?? '#8B5CF6');
        $lead    = $this->e($c['lead'] ?? '');
        $text    = $this->e($c['text'] ?? '');
        $hl      = $this->e($c['highlight'] ?? '');
        $fn      = $this->e($c['footnote'] ?? '');

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-story reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . ($lead ?: 'История') . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="sb-card sb-card--' . $this->e($variant) . '" style="--sb-c:' . $accent . '">'
            . '<div class="sb-accent"></div>'
            . '<div class="sb-body">'
            . '<div class="sb-icon">' . $icon . '</div>'
            . ($lead ? '<div class="sb-lead">' . $lead . '</div>' : '')
            . '<div class="sb-text">' . $text . '</div>'
            . ($hl ? '<div class="sb-highlight">' . $hl . '</div>' : '')
            . ($fn ? '<div class="sb-footnote">' . $fn . '</div>' : '')
            . '</div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    private function renderVerdictCard(array $c, string $id): string {
        $title = $this->e($c['title'] ?? 'Мифы и факты');
        $items = $c['items'] ?? [];
        $jsonData = json_encode($items, JSON_UNESCAPED_UNICODE);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-verdict reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<h2 class="sec-title">' . $title . '</h2>'
            . '<div class="vd-grid" data-verdict="' . $id . '">';
        foreach ($items as $i => $it) {
            $v = $it['verdict'] ?? 'myth';
            $vLabel = $v === 'truth' ? 'Правда' : ($v === 'partial' ? 'Полуправда' : 'Миф');
            $vClass = 'vd-verdict--' . $this->e($v);
            $h .= '<div class="vd-card" data-idx="' . $i . '">'
                . '<div class="vd-stamp ' . $vClass . '">' . $this->e($vLabel) . '</div>'
                . '<div class="vd-claim">' . $this->e($it['claim'] ?? '') . '</div>'
                . '<div class="vd-expand"><div>'
                . '<div class="vd-explanation">' . $this->e($it['explanation'] ?? '') . '</div>'
                . (!empty($it['source']) ? '<div class="vd-source">📎 ' . $this->e($it['source']) . '</div>' : '')
                . '</div></div>'
                . '</div>';
        }
        $h .= '</div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    private function renderNumberedSteps(array $c, string $id): string {
        $title = $this->e($c['title'] ?? 'Пошаговый план');
        $steps = $c['steps'] ?? [];
        $colors = ['var(--blue)', 'var(--teal)', 'var(--purple)', 'var(--green)', 'var(--orange)',
            'var(--pink)', 'var(--warn)', 'var(--red)'];

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-nsteps reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<h2 class="sec-title">' . $title . '</h2>'
            . '<div class="ns-track">';
        foreach ($steps as $i => $st) {
            $color = $colors[$i % count($colors)];
            $h .= '<div class="ns-step" style="--ns-c:' . $color . '">'
                . '<div class="ns-num">' . (int)($st['number'] ?? $i + 1) . '</div>'
                . '<div class="ns-card">'
                . '<div class="ns-title">' . $this->e($st['title'] ?? '') . '</div>'
                . '<div class="ns-text">' . $this->e($st['text'] ?? '') . '</div>'
                . (!empty($st['tip']) ? '<div class="ns-tip">💡 ' . $this->e($st['tip']) . '</div>' : '')
                . (!empty($st['duration']) ? '<div class="ns-duration">⏱ ' . $this->e($st['duration']) . '</div>' : '')
                . '</div></div>';
        }
        $h .= '</div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    private function renderWarningBlock(array $c, string $id): string {
        $variant = $c['variant'] ?? 'red_flags';
        $title   = $this->e($c['title'] ?? 'Внимание');
        $sub     = $this->e($c['subtitle'] ?? '');
        $items   = $c['items'] ?? [];
        $footer  = $this->e($c['footer'] ?? '');

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        switch ($variant) {
            case 'caution': $varClass = 'wb--caution'; break;
            case 'good_signs': $varClass = 'wb--good'; break;
            default: $varClass = 'wb--red';
        };

        $h = '<section id="' . $id . '" class="block-warning reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="wb-card ' . $varClass . '">'
            . '<div class="wb-header">'
            . '<div class="wb-icon">' . ($variant === 'good_signs' ? '✅' : '⚠️') . '</div>'
            . '<div><div class="wb-title">' . $title . '</div>'
            . ($sub ? '<div class="wb-subtitle">' . $sub . '</div>' : '')
            . '</div></div>'
            . '<div class="wb-items">';
        foreach ($items as $it) {
            $sev = $it['severity'] ?? 'warning';
            switch ($sev) {
                case 'emergency': $sevIcon = '🚑'; break;
                case 'urgent': $sevIcon = '🚨'; break;
                default: $sevIcon = '⚠️';
            };
            $h .= '<div class="wb-item wb-item--' . $this->e($sev) . '">'
                . '<span class="wb-item-icon">' . $sevIcon . '</span>'
                . '<span class="wb-item-text">' . $this->e($it['text'] ?? '') . '</span>'
                . '</div>';
        }
        $h .= '</div>';
        if ($footer) {
            $h .= '<div class="wb-footer">' . $footer . '</div>';
        }
        $h .= '</div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    private function renderMiniCalculator(array $c, string $id): string {
        $title = $this->e($c['title'] ?? 'Калькулятор');
        $desc  = $this->e($c['description'] ?? '');
        $inputs  = $c['inputs'] ?? [];
        $results = $c['results'] ?? [];
        $fDesc   = $this->e($c['formula_description'] ?? '');
        $disclaimer = $this->e($c['disclaimer'] ?? '');
        $jsonData = json_encode(['inputs' => $inputs, 'results' => $results], JSON_UNESCAPED_UNICODE);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-mcalc reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<h2 class="sec-title">' . $title . '</h2>'
            . ($desc ? '<p class="sec-desc">' . $desc . '</p>' : '')
            . '<div class="mac-window"><div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">Расчёт</div></div>'
            . '<div class="mac-body">'
            . '<div class="mc-wrap" data-mcalc="' . $id . '">'
            . '<div class="mc-inputs">';
        foreach ($inputs as $inp) {
            $key  = $this->e($inp['key'] ?? '');
            $lbl  = $this->e($inp['label'] ?? '');
            $type = $inp['type'] ?? 'number';
            $showIf = !empty($inp['show_if']) ? ' data-show-if-key="' . $this->e($inp['show_if']['key'] ?? '') . '" data-show-if-val="' . $this->e($inp['show_if']['value'] ?? '') . '"' : '';

            $h .= '<div class="mc-field"' . $showIf . '><label class="mc-label">' . $lbl . '</label>';
            if ($type === 'select') {
                $h .= '<select class="mc-select" data-key="' . $key . '">';
                foreach ($inp['options'] ?? [] as $opt) {
                    $h .= '<option value="' . $this->e($opt['value'] ?? '') . '">' . $this->e($opt['label'] ?? '') . '</option>';
                }
                $h .= '</select>';
            } else {
                $unit = $this->e($inp['unit'] ?? '');
                $mn = $inp['min'] ?? '';
                $mx = $inp['max'] ?? '';
                $h .= '<div class="mc-input-wrap"><input type="number" class="mc-input" data-key="' . $key . '"'
                    . ' placeholder="' . $this->e($inp['placeholder'] ?? '') . '"'
                    . ($mn !== '' ? ' min="' . (int)$mn . '"' : '')
                    . ($mx !== '' ? ' max="' . (int)$mx . '"' : '')
                    . '>'
                    . ($unit ? '<span class="mc-unit">' . $unit . '</span>' : '')
                    . '</div>';
            }
            $h .= '</div>';
        }
        $h .= '</div>'
            . '<button class="mc-btn">Рассчитать</button>'
            . '<div class="mc-result" style="display:none">'
            . '<div class="mc-result-value"></div>'
            . '<div class="mc-result-text"></div>'
            . '</div>'
            . ($fDesc ? '<div class="mc-formula-desc">' . $fDesc . '</div>' : '')
            . ($disclaimer ? '<div class="mc-disclaimer">' . $disclaimer . '</div>' : '')
            . '<script type="application/json" class="mc-data">' . str_replace('</', '<\\/', $jsonData) . '</script>'
            . '</div></div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    private function renderComparisonCards(array $c, string $id): string {
        $title = $this->e($c['title'] ?? 'Сравнение');

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-ccards reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<h2 class="sec-title">' . $title . '</h2>'
            . '<div class="cc-grid">';
        foreach (['card_a', 'card_b'] as $side) {
            $card = $c[$side] ?? [];
            $color = $this->e($card['color'] ?? 'var(--blue)');
            $name  = $this->e($card['name'] ?? '');
            $badge = $this->e($card['badge'] ?? '');
            $price = $this->e($card['price'] ?? '');
            $verdict = $this->e($card['verdict'] ?? '');

            $h .= '<div class="cc-card" style="--cc-c:' . $color . '">'
                . ($badge ? '<div class="cc-badge">' . $badge . '</div>' : '')
                . '<div class="cc-name">' . $name . '</div>';
            if (!empty($card['pros'])) {
                $h .= '<div class="cc-list cc-pros">';
                foreach ($card['pros'] as $p) $h .= '<div class="cc-li"><span class="cc-li-icon cc-ok">✓</span>' . $this->e($p) . '</div>';
                $h .= '</div>';
            }
            if (!empty($card['cons'])) {
                $h .= '<div class="cc-list cc-cons">';
                foreach ($card['cons'] as $cn) $h .= '<div class="cc-li"><span class="cc-li-icon cc-no">✗</span>' . $this->e($cn) . '</div>';
                $h .= '</div>';
            }
            if ($price)   $h .= '<div class="cc-price">' . $price . '</div>';
            if ($verdict) $h .= '<div class="cc-verdict">' . $verdict . '</div>';
            $h .= '</div>';
        }
        $h .= '</div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    private function renderProgressTracker(array $c, string $id): string {
        $title = $this->e($c['title'] ?? 'Ожидаемый прогресс');
        $unit  = $this->e($c['timeline_unit'] ?? 'месяц');
        $milestones = $c['milestones'] ?? [];
        $note  = $this->e($c['note'] ?? '');
        $jsonData = json_encode($milestones, JSON_UNESCAPED_UNICODE);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-ptrack reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<h2 class="sec-title">' . $title . '</h2>'
            . '<div class="mac-window"><div class="mac-bar"><div class="mac-dots"><span></span><span></span><span></span></div>'
            . '<div class="mac-title">Прогресс</div></div>'
            . '<div class="mac-body">'
            . '<div class="pt-wrap" data-ptrack="' . $id . '">'
            . '<div class="pt-track"><div class="pt-track-fill"></div>';
        foreach ($milestones as $i => $m) {
            $left = (int)($m['marker'] ?? 0);
            $h .= '<div class="pt-dot" data-idx="' . $i . '" style="left:' . $left . '%"><div class="pt-dot-inner"></div>'
                . '<div class="pt-label">' . $this->e($m['period'] ?? '') . '</div>'
                . '</div>';
        }
        $h .= '</div>'
            . '<div class="pt-detail" style="display:none">'
            . '<div class="pt-detail-period"></div>'
            . '<div class="pt-detail-text"></div>'
            . '<div class="pt-detail-metric"></div>'
            . '</div>'
            . ($note ? '<div class="pt-note">' . $note . '</div>' : '')
            . '<script type="application/json" class="pt-data">' . str_replace('</', '<\\/', $jsonData) . '</script>'
            . '</div></div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    private function renderKeyTakeaways(array $c, string $id): string {
        $title = $this->e($c['title'] ?? 'Главное');
        $items = $c['items'] ?? [];
        $style = $c['style'] ?? 'numbered';

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-takeaways reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="kt-card">'
            . '<div class="kt-header"><span class="kt-icon">⚡</span><span class="kt-title">' . $title . '</span></div>'
            . '<div class="kt-items kt-items--' . $this->e($style) . '">';
        foreach ($items as $i => $it) {
            $text = is_string($it) ? $it : ($it['text'] ?? '');
            $h .= '<div class="kt-item">'
                . ($style === 'numbered' ? '<span class="kt-num">' . ($i + 1) . '</span>' : '<span class="kt-bullet"></span>')
                . '<span class="kt-text">' . $this->e($text) . '</span>'
                . '</div>';
        }
        $h .= '</div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    private function renderExpertPanel(array $c, string $id): string {
        $name   = $this->e($c['name'] ?? '');
        $creds  = $this->e($c['credentials'] ?? '');
        $exp    = $this->e($c['experience'] ?? '');
        $ph     = $this->e($c['photo_placeholder'] ?? '?');
        $text   = $this->e($c['text'] ?? '');
        $hl     = $this->e($c['highlight'] ?? '');

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-expert reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="Мнение: ' . $name . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="ep-card">'
            . '<div class="ep-quote-mark">"</div>'
            . '<div class="ep-body">'
            . '<div class="ep-text">' . $text . '</div>'
            . ($hl ? '<div class="ep-highlight">' . $hl . '</div>' : '')
            . '</div>'
            . '<div class="ep-author">'
            . '<div class="ep-avatar">' . $ph . '</div>'
            . '<div class="ep-meta">'
            . '<div class="ep-name">' . $name . '</div>'
            . '<div class="ep-creds">' . $creds . '</div>'
            . ($exp ? '<div class="ep-exp">' . $exp . '</div>' : '')
            . '</div></div>'
            . '</div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    private function renderRelatedArticles(array $article): string {
        $articleId = (int)$article['id'];
        $catalogId = (int)($article['catalog_id'] ?? 0);
        $searchUrl = json_encode(SEO_SEARCH_SCRIPT);
        $trackUrl  = json_encode(SEO_TRACK_SCRIPT);
        $baseUrl   = json_encode(SEO_BASE_ART_URL);

        // Пустая секция — заполняется JS при загрузке страницы.
        // search.php?related=1&catalog_id=X&exclude=Y&limit=4 отдаёт статьи из того же каталога.
        $html  = '<section class="related-articles reveal" id="relatedArticles">';
        $html .= '<div class="container">';
        $html .= '<h2 class="ra-heading">Читайте также</h2>';
        $html .= '<div class="ra-grid" id="raGrid">';
        $html .= '<div class="ra-skeleton"></div>';
        $html .= '<div class="ra-skeleton"></div>';
        $html .= '<div class="ra-skeleton"></div>';
        $html .= '<div class="ra-skeleton"></div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</section>';
        $html .= '<script>';
        $html .= '(function(){';
        $html .= 'var SEARCH=' . $searchUrl . ',';
        $html .= 'TRACK=' . $trackUrl . ',';
        $html .= 'BASE=' . $baseUrl . ',';
        $html .= 'AID=' . $articleId . ',';
        $html .= 'CID=' . $catalogId . ';';
        $html .= 'function esc(s){return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");}';
        $html .= 'function buildUrl(u){return u;}';
        $html .= 'function renderCards(items){';
        $html .=   'var grid=document.getElementById("raGrid");';
        $html .=   'if(!grid)return;';
        $html .=   'if(!items||!items.length){document.getElementById("relatedArticles").style.display="none";return;}';
        $html .=   'grid.innerHTML=items.map(function(it){';
        $html .=     'var href=buildUrl(it.url);';
        $html .=     'var desc=it.description?"<p class=\"ra-card-desc\">"+esc(it.description.substring(0,120))+"</p>":"";';
        $html .=     'return "<a class=\"ra-card\" href=\""+esc(href)+"\" data-aid=\""+it.id+"\">"';
        $html .=       '+"<span class=\"ra-card-title\">"+esc(it.title)+"</span>"';
        $html .=       '+desc';
        $html .=       '+"<span class=\"ra-card-arrow\">\u2192</span>"';
        $html .=       '+"</a>";';
        $html .=   '}).join("");';
        $html .=   'grid.querySelectorAll(".ra-card").forEach(function(a){';
        $html .=     'a.addEventListener("click",function(){';
        $html .=       'var toAid=parseInt(a.getAttribute("data-aid")||"0");';
        $html .=       'if(toAid)navigator.sendBeacon(TRACK+"?aid="+toAid);';
        $html .=     '});';
        $html .=   '});';
        $html .= '}';
        $html .= 'var url=SEARCH+"?related=1&exclude="+AID+"&limit=4"+(CID?"&catalog_id="+CID:"");';
        $html .= 'fetch(url)';
        $html .=   '.then(function(r){return r.json();})';
        $html .=   '.then(function(d){renderCards(d.results||[]);})';
        $html .=   '.catch(function(){document.getElementById("relatedArticles").style.display="none";});';
        $html .= '})();';
        $html .= '</script>';

        return $html;
    }

    private function replaceLinkPlaceholders(string $html, array $links): string {
        $searchBase = SEO_SEARCH_SCRIPT;
        foreach ($links as $lnk) {
            $key = $lnk['key'] ?? '';
            if (!$key) continue;

            $url = $lnk['url'] ?? '';
            $tracked = (int)($lnk['is_tracked'] ?? 1);

            if ($tracked && $url !== '') {
                // Tracked link: redirect through search.php for click tracking
                $finalUrl = htmlspecialchars(
                    $searchBase . '?link=' . urlencode($key),
                    ENT_QUOTES, 'UTF-8'
                );
            } elseif ($url !== '') {
                // Direct link: insert URL as-is (navbar, logo, etc.)
                $finalUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            } else {
                continue;
            }

            $html = str_replace('{{link:' . $key . '}}', $finalUrl, $html);
        }
        // Remove unresolved placeholders
        $html = preg_replace('/\{\{link:[^}]+\}\}/', '#', $html);
        return $html;
    }
    private function wrapInDocument(array $a, string $body, string $toc, ?array $tpl, bool $preview): string {
        $title = $this->e($a['meta_title'] ?: $a['title']);
        $desc  = $this->e($a['meta_description'] ?? '');
        $kw    = $this->e($a['meta_keywords'] ?? '');
        $css   = $tpl ? $this->e($tpl['css_class'] ?? '') : '';
        $url   = $this->e($a['published_url'] ?? '');

        $chartJs = strpos($body, 'chartjs-wrap') !== false
            ? '<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>'
            : '';

        $fonts = '<link rel="preconnect" href="https://fonts.googleapis.com">'
            . '<link href="https://fonts.googleapis.com/css2?family=Geologica:wght@300;400;500;700;900&family=Onest:wght@300;400;500&display=swap" rel="stylesheet">';


        $parallaxLayer = '<div id="parallax-layer" aria-hidden="true">'
            . '<div class="p-orb p-orb--1"></div>'
            . '<div class="p-orb p-orb--2"></div>'
            . '<div class="p-orb p-orb--3"></div>'
            . '<div class="p-orb p-orb--4"></div>'
            . '<div class="p-orb p-orb--5"></div>'
            . '<div class="p-orb p-orb--6"></div>'
            . '<div class="p-orb p-orb--7"></div>'
            . '<div class="p-orb p-orb--8"></div>'
            . '<div class="p-orb p-orb--9"></div>'
            . '<div class="p-orb p-orb--10"></div>'
            . '<div class="p-orb p-orb--11"></div>'
            . '<div class="p-orb p-orb--12"></div>'
            . '<div class="p-orb p-orb--13"></div>'
            . '</div>';


        $navSearch   = $this->buildNavbarSearch($a['id']);
        $relatedHtml = $this->renderRelatedArticles($a);

        $logo = '/uploads/' . $this->siteProfile['icon_path'] ?? SEO_DEFAULT_LOGO_URL;
        $brandName = $this->siteProfile['brand_name'] ?? 'SEO Generator';

        // Split brand name for accent styling (first word normal, rest accented)
        $brandParts = explode(' ', $brandName, 2);
        $brandHtml = $this->e($brandParts[0]);
        if (isset($brandParts[1])) {
            $brandHtml .= '<span class="nav-accent">' . $this->e($brandParts[1]) . '</span>';
        }

        /* Navbar links: all hrefs use {{link:KEY}} placeholders
           so they go through replaceLinkPlaceholders() → search.php tracking */
        $navItems = $tpl['nav_items'] ?? null;
        $navLinksHtml = '<a href="{{link:home}}">Главная</a>';
        if (is_string($navItems)) {
            $navItems = json_decode($navItems, true);
        }
        if (is_array($navItems) && !empty($navItems)) {
            $navLinksHtml = '';
            foreach ($navItems as $ni) {
                $niLabel = $this->e($ni['label'] ?? '');
                $niKey   = $ni['link_key'] ?? '';
                if ($niLabel && $niKey) {
                    $navLinksHtml .= '<a href="{{link:' . $this->e($niKey) . '}}">' . $niLabel . '</a>';
                }
            }
        }

        $logoHtml = $logo ? '<span class="nav-logo-icon"><img src="' . $logo . '" alt=""></span>' : '';
        $navbar = '<header class="navbar" id="navbar">'
            . '<div class="navbar-inner">'
            . '<a href="{{link:home}}" class="nav-logo">' . $logoHtml . $brandHtml . '</a>'
            . '<nav class="nav-links">' . $navLinksHtml . $navSearch . '</nav>'
            . '<button id="theme-toggle" class="theme-toggle" aria-label="Переключить тему">🌙</button>'
            . '</div></header>';


        return '<!DOCTYPE html><html lang="ru"><head>'
            . '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">'
            . '<title>' . $title . '</title>'
            . '<meta name="description" content="' . $desc . '">'
            . '<meta name="keywords" content="' . $kw . '">'
            . '<meta property="og:title" content="' . $title . '">'
            . '<meta property="og:description" content="' . $desc . '">'
            . '<meta property="og:type" content="article">'
            . '<meta property="og:url" content="' . $url . '">'
            . '<link rel="canonical" href="' . $url . '">'
            . '<link rel="icon" href="' . $logo . '">'
            . $fonts
            . $chartJs
            . $this->getStylesheet()
            . '</head><body class="' . $css . '">'
            . $parallaxLayer
            . $navbar
            . $toc
            . '<main class="page-main" id="pageMain">' . $body . $relatedHtml . '</main>'
            . $this->getScripts((int)$a['id'])
            . '</body></html>';
    }


    private function getStylesheet(): string {
        return '<style>'
            /* ── VARIABLES: LIGHT MODE ── */
            . "\n" . ':root {'
            . '--blue:#2563EB;--blue-dark:#1D4ED8;--blue-light:#EFF6FF;'
            . '--teal:#0D9488;--green:#16A34A;--green-light:#DCFCE7;'
            . '--warn:#F59E0B;--red:#EF4444;'
            . '--dark:#0F172A;--dark2:#1E293B;--slate:#334155;--muted:#64748B;'
            . '--border:rgba(0,0,0,0.08);--bg:#F8FAFC;--white:#FFFFFF;'
            . '--r:14px;--fh:"Geologica",sans-serif;--fb:"Onest",sans-serif'
            . '}'

            /* ── VARIABLES: DARK MODE ── */
            . "\n" . '[data-theme="dark"] {'
            . '--bg:#050D1A;--white:rgba(255,255,255,0.04);'
            . '--dark:#E2E8F0;--dark2:#040C18;'
            . '--slate:#94A3B8;--muted:#5A7A9F;'
            . '--border:rgba(255,255,255,0.08);--blue-light:rgba(37,99,235,0.12)'
            . '}'

            /* ── RESET ── */
            . "\n" . '*,*::before,*::after { box-sizing:border-box; margin:0; padding:0 }'
            . "\n" . 'html { scroll-behavior:smooth; scroll-padding-top:80px }'
            . "\n" . 'body { font-family:var(--fb); background:var(--bg); color:var(--dark); line-height:1.7; overflow-x:hidden; transition:background .4s,color .3s }'

            /* ── TYPOGRAPHY ── */
            . "\n" . 'h1 { font-family:var(--fh); font-size:clamp(2rem,5vw,3.5rem); font-weight:900; line-height:1.08; letter-spacing:-1.5px; margin-bottom:.5em; color:var(--dark) }'
            . "\n" . 'h2 { font-family:var(--fh); font-size:clamp(1.5rem,3vw,2.2rem); font-weight:900; letter-spacing:-.5px; line-height:1.1; margin:1.5em 0 .7em; color:var(--dark) }'
            . "\n" . 'h3 { font-family:var(--fh); font-size:1.35rem; font-weight:700; margin:1.2em 0 .5em; color:var(--dark) }'
            . "\n" . 'h4 { font-family:var(--fh); font-size:1.05rem; font-weight:700; margin:.8em 0 .3em; color:var(--dark) }'
            . "\n" . 'p { margin-bottom:1em; color:var(--slate) }'
            . "\n" . 'ul,ol { margin:0 0 1em 1.5em; overflow:hidden }'
            . "\n" . 'li { margin-bottom:.4em; color:var(--slate); break-inside:avoid; page-break-inside:avoid }'
            . "\n" . 'a { color:var(--blue); text-decoration:none }'
            . "\n" . 'a:hover { text-decoration:underline }'
            . "\n" . 'img { max-width:100%; height:auto }'
            . "\n" . 'blockquote { border-left:4px solid var(--blue); padding:.8em 1.2em; margin:1em 0; background:var(--blue-light); border-radius:0 var(--r) var(--r) 0; font-style:italic; color:var(--slate) }'
            . "\n" . '.sec-title { font-family:var(--fh); font-size:clamp(1.5rem,3vw,2.2rem); font-weight:900; letter-spacing:-.5px; line-height:1.1; margin:0 0 .8em; color:var(--dark) }'
            . "\n" . '.container { max-width:960px; margin:0 auto; padding:0 24px }'
            . "\n" . 'section { padding:80px 0; position:relative; background:transparent }'
            . "\n" . '.clearfix::after { content:""; display:table; clear:both }'
            . "\n" . '.clearfix { clear:both }'

            /* ── BACKGROUND IMAGE LAYOUT ── */
            . "\n" . '.has-bg-img { background-size:cover; background-position:center; background-repeat:no-repeat; position:relative }'
            . "\n" . '.has-bg-img::before { content:""; position:absolute; inset:0; background:rgba(248,250,252,.88); backdrop-filter:blur(2px); z-index:0; pointer-events:none }'
            . "\n" . '[data-theme="dark"] .has-bg-img::before { background:rgba(5,13,26,.88) }'
            . "\n" . '.has-bg-img > .container { position:relative; z-index:1 }'

            /* ── UNIFIED PARALLAX BACKGROUND — fixed layer of 13 orbs ── */
            . "\n" . '#parallax-layer { position:fixed; inset:0; z-index:0; pointer-events:none; overflow:hidden }'

            /* Base orb shape */
            . "\n" . '.p-orb { position:absolute; border-radius:50%; will-change:transform }'

            /* Light mode: orbs are soft pastels */
            . "\n" . '.p-orb--1  { width:min(72vw,760px); height:min(72vw,760px); top:-8%;   left:-12%;  background:radial-gradient(circle,rgba(37,99,235,.28) 0%,rgba(37,99,235,.06) 90%,transparent 72%); filter:blur(80px); animation:pDrift1 22s ease-in-out infinite }'
            . "\n" . '.p-orb--2  { width:min(60vw,620px); height:min(60vw,620px); top:10%;   right:-14%; background:radial-gradient(circle,rgba(13,148,136,.24) 0%,rgba(13,148,136,.05) 90%,transparent 72%); filter:blur(70px); animation:pDrift2 28s ease-in-out infinite }'
            . "\n" . '.p-orb--3  { width:min(50vw,520px); height:min(50vw,520px); top:35%;   left:18%;   background:radial-gradient(circle,rgba(139,92,246,.22) 0%,rgba(139,92,246,.05) 90%,transparent 72%); filter:blur(75px); animation:pDrift3 19s ease-in-out infinite }'
            . "\n" . '.p-orb--4  { width:min(45vw,460px); height:min(45vw,460px); top:55%;   right:-8%;  background:radial-gradient(circle,rgba(236,72,153,.2)  0%,rgba(236,72,153,.04) 90%,transparent 72%); filter:blur(70px); animation:pDrift4 24s ease-in-out infinite }'
            . "\n" . '.p-orb--5  { width:min(40vw,400px); height:min(40vw,400px); top:75%;   left:-5%;   background:radial-gradient(circle,rgba(249,115,22,.22)  0%,rgba(249,115,22,.05) 90%,transparent 72%); filter:blur(65px); animation:pDrift5 31s ease-in-out infinite }'
            . "\n" . '.p-orb--6  { width:min(38vw,380px); height:min(38vw,380px); top:88%;   right:20%;  background:radial-gradient(circle,rgba(37,99,235,.18)  0%,rgba(37,99,235,.04) 90%,transparent 72%); filter:blur(72px); animation:pDrift6 20s ease-in-out infinite }'
            . "\n" . '.p-orb--7  { width:min(35vw,340px); height:min(35vw,340px); top:5%;    left:40%;   background:radial-gradient(circle,rgba(13,148,136,.18)  0%,rgba(13,148,136,.04) 90%,transparent 72%); filter:blur(68px); animation:pDrift7 26s ease-in-out infinite }'
            . "\n" . '.p-orb--8  { width:min(44vw,440px); height:min(44vw,440px); top:25%;   right:30%;  background:radial-gradient(circle,rgba(139,92,246,.15)  0%,rgba(139,92,246,.03) 90%,transparent 72%); filter:blur(76px); animation:pDrift8 17s ease-in-out infinite }'
            . "\n" . '.p-orb--9  { width:min(32vw,300px); height:min(32vw,300px); top:48%;   left:55%;   background:radial-gradient(circle,rgba(22,163,74,.18)   0%,rgba(22,163,74,.04)  90%,transparent 72%); filter:blur(64px); animation:pDrift9 33s ease-in-out infinite }'
            . "\n" . '.p-orb--10 { width:min(36vw,360px); height:min(36vw,360px); top:65%;   left:30%;   background:radial-gradient(circle,rgba(37,99,235,.16)  0%,rgba(37,99,235,.03) 90%,transparent 72%); filter:blur(70px); animation:pDrift10 21s ease-in-out infinite }'
            . "\n" . '.p-orb--11 { width:min(30vw,280px); height:min(30vw,280px); top:80%;   right:40%;  background:radial-gradient(circle,rgba(236,72,153,.14) 0%,rgba(236,72,153,.03) 90%,transparent 72%); filter:blur(62px); animation:pDrift11 29s ease-in-out infinite }'
            . "\n" . '.p-orb--12 { width:min(28vw,260px); height:min(28vw,260px); top:18%;   left:70%;   background:radial-gradient(circle,rgba(249,115,22,.16) 0%,rgba(249,115,22,.03) 90%,transparent 72%); filter:blur(60px); animation:pDrift12 23s ease-in-out infinite }'
            . "\n" . '.p-orb--13 { width:min(55vw,560px); height:min(55vw,560px); top:42%;   right:-18%; background:radial-gradient(circle,rgba(13,148,136,.16) 0%,rgba(13,148,136,.03) 90%,transparent 72%); filter:blur(82px); animation:pDrift13 36s ease-in-out infinite }'

            /* Dark mode: orbs are much brighter */
            . "\n" . '[data-theme="dark"] .p-orb--1  { background:radial-gradient(circle,rgba(96,165,250,.55)  0%,rgba(37,99,235,.18)  50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] .p-orb--2  { background:radial-gradient(circle,rgba(45,212,191,.5)   0%,rgba(13,148,136,.15) 50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] .p-orb--3  { background:radial-gradient(circle,rgba(196,181,253,.52) 0%,rgba(139,92,246,.16) 50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] .p-orb--4  { background:radial-gradient(circle,rgba(244,114,182,.48) 0%,rgba(236,72,153,.14) 50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] .p-orb--5  { background:radial-gradient(circle,rgba(251,146,60,.52)  0%,rgba(249,115,22,.15) 50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] .p-orb--6  { background:radial-gradient(circle,rgba(96,165,250,.4)   0%,rgba(37,99,235,.12)  50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] .p-orb--7  { background:radial-gradient(circle,rgba(45,212,191,.42)  0%,rgba(13,148,136,.12) 50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] .p-orb--8  { background:radial-gradient(circle,rgba(196,181,253,.38) 0%,rgba(139,92,246,.1)  50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] .p-orb--9  { background:radial-gradient(circle,rgba(74,222,128,.42)  0%,rgba(22,163,74,.12)  50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] .p-orb--10 { background:radial-gradient(circle,rgba(96,165,250,.38)  0%,rgba(37,99,235,.1)   50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] .p-orb--11 { background:radial-gradient(circle,rgba(244,114,182,.36) 0%,rgba(236,72,153,.1)  50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] .p-orb--12 { background:radial-gradient(circle,rgba(251,146,60,.4)   0%,rgba(249,115,22,.11) 50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] .p-orb--13 { background:radial-gradient(circle,rgba(45,212,191,.38)  0%,rgba(13,148,136,.1)  50%,transparent 72%) }'

            /* ── APPLE-STYLE SECTION DIVIDERS ── */
            . "\n" . '.section-divider { display:flex; align-items:center; justify-content:center; padding:0; height:1px; position:relative; z-index:2 }'
            . "\n" . '.section-divider-line { width:min(100%,960px); margin:0 auto; height:1px; background:var(--border) }'

            /* ── PREVIEW BANNER ── */
            . "\n" . '.preview-banner { position:fixed; top:0; left:0; right:0; background:#f59e0b; color:#000; text-align:center; padding:8px; font-weight:700; z-index:9999; font-size:14px }'

            /* ── NAVBAR ── */
            . "\n" . '.navbar { position:fixed; top:0; left:0; right:0; z-index:200; background:rgba(248,250,252,.82); backdrop-filter:blur(24px) saturate(180%); -webkit-backdrop-filter:blur(24px) saturate(180%); border-bottom:1px solid var(--border); transition:box-shadow .3s,background .4s }'
            . "\n" . '[data-theme="dark"] .navbar { background:rgba(5,13,26,.84) }'
            . "\n" . '.navbar.scrolled { box-shadow:0 1px 0 var(--border) }'
            . "\n" . '.navbar-inner { max-width:1200px; margin:0 auto; padding:14px 32px; display:flex; align-items:center; justify-content:space-between }'
            . "\n" . '.nav-logo { font-family:var(--fh); font-size:20px; font-weight:900; color:var(--dark); text-decoration:none; display:flex; align-items:center; gap:8px }'
            . "\n" . '.nav-logo:hover { text-decoration:none }'
            // background:var(--blue);
            . "\n" . '.nav-logo-icon { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:16px }'
            . "\n" . '.nav-accent { color:var(--blue) }'
            . "\n" . '.nav-links { align-items: center; display:flex; gap:28px }'
            . "\n" . '.nav-links a { font-size:14px; color:var(--muted); text-decoration:none; font-weight:500; transition:color .2s }'
            . "\n" . '.nav-links a:hover { color:var(--dark); text-decoration:none }'

            /* ── THEME TOGGLE ── */
            . "\n" . '.theme-toggle { background:none; border:1px solid var(--border); border-radius:100px; width:40px; height:40px; font-size:17px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all .2s; flex-shrink:0 }'
            . "\n" . '.theme-toggle:hover { background:var(--blue-light); border-color:rgba(37,99,235,.3); transform:scale(1.08) }'

            /* ── TOC SIDEBAR ── */
            . "\n" . '.toc { position:fixed; left:16px; top:50%; transform:translateY(-50%); z-index:100; opacity:.12; transition:opacity .4s ease; width:160px }'
            . "\n" . '.toc:hover { opacity:.96 }'
            . "\n" . '.toc-inner { background:rgba(248,250,252,.88); backdrop-filter:blur(20px); border:1px solid var(--border); border-radius:12px; padding:14px 10px; box-shadow:0 4px 24px rgba(15,23,42,.06) }'
            . "\n" . '[data-theme="dark"] .toc-inner { background:rgba(5,13,26,.88) }'
            . "\n" . '.toc-label { font-family:var(--fh); font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:2px; color:var(--muted); margin-bottom:10px; padding:0 8px }'
            . "\n" . '.toc-link { display:block; font-size:11.5px; color:var(--muted); padding:5px 8px; border-radius:6px; text-decoration:none; transition:all .2s; border-left:2px solid transparent; margin-bottom:1px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis }'
            . "\n" . '.toc-link:hover { color:var(--dark); background:var(--blue-light); text-decoration:none }'
            . "\n" . '.toc-link.active { color:var(--blue); font-weight:600; border-left-color:var(--blue); background:var(--blue-light) }'

            /* ── MAIN ── */
            . "\n" . '.page-main { padding-top:64px; position:relative; z-index:1 }'

            /* ── REVEAL ANIMATION ── */
            . "\n" . '.reveal { opacity:0; transform:translateY(28px); transition:opacity .6s ease,transform .6s ease }'
            . "\n" . '.reveal.vis { opacity:1; transform:none }'

            /* ── macOS WINDOW ── */
            . "\n" . '.mac-window { background:rgba(255,255,255,.7); backdrop-filter:blur(12px); border-radius:20px; border:1px solid var(--border); box-shadow:0 8px 40px rgba(15,23,42,.06); overflow:hidden }'
            . "\n" . '[data-theme="dark"] .mac-window { background:rgba(255,255,255,.04) }'
            . "\n" . '.mac-bar { padding:14px 20px; background:var(--dark2); display:flex; align-items:center; gap:10px }'
            . "\n" . '.mac-dots { display:flex; gap:6px }'
            . "\n" . '.mac-dots span { width:12px; height:12px; border-radius:50%; display:block }'
            . "\n" . '.mac-dots span:nth-child(1) { background:#FF5F57 }'
            . "\n" . '.mac-dots span:nth-child(2) { background:#FFBD2E }'
            . "\n" . '.mac-dots span:nth-child(3) { background:#28C840 }'
            . "\n" . '.mac-title { font-family:var(--fh); font-size:13px; font-weight:600; color:rgba(255,255,255,.5); margin-left:8px }'
            . "\n" . '.mac-body { padding:24px 28px }'

            /* ── PROGRESS BARS ── */
            . "\n" . '.bar-track { height:8px; background:var(--border); border-radius:100px; overflow:hidden; position:relative }'
            . "\n" . '.bar-fill { height:100%; border-radius:100px; width:0; transition:width 1.4s cubic-bezier(.4,0,.2,1) }'
            . "\n" . '.bar-fill--shimmer::after { content:""; position:absolute; top:0; left:0; right:0; bottom:0; background:linear-gradient(90deg,rgba(255,255,255,0) 0%,rgba(255,255,255,.45) 50%,rgba(255,255,255,0) 100%); animation:shimmer 2.5s ease-in-out infinite }'
            . "\n" . '.highlight { background:var(--blue-light); border-left:4px solid var(--blue); padding:18px 22px; border-radius:0 var(--r) var(--r) 0; margin:1.2em 0; color:var(--slate) }'

            /* ── IMG FRAME + SHINE ── */
            . "\n" . '.img-frame { position:relative; border-radius:20px; overflow:hidden; box-shadow:0 16px 56px rgba(15,23,42,.12) }'
            . "\n" . '.img-frame img { display:block; width:100%; height:auto; transition:transform .5s ease }'
            . "\n" . '.img-frame:hover img { transform:scale(1.03) }'
            . "\n" . '.img-shine { position:absolute; inset:0; background:linear-gradient(135deg,rgba(255,255,255,.2) 0%,transparent 50%,rgba(255,255,255,.05) 100%); pointer-events:none }'

            /* ── BLOCK IMAGE — TOP / BOTTOM LAYOUT ── */
            . "\n" . '.blk-img { margin:0 0 28px; width:100% }'
            . "\n" . '.blk-img--bottom { margin:28px 0 0 }'
            . "\n" . '.blk-img .img-frame { border-radius:20px; overflow:hidden; box-shadow:0 12px 40px rgba(15,23,42,.10); position:relative }'
            . "\n" . '.blk-img .img-frame img { display:block; width:100%; max-height:420px; object-fit:cover; transition:transform .5s ease }'
            . "\n" . '.blk-img .img-frame:hover img { transform:scale(1.02) }'
            . "\n" . '.blk-img-caption { display:block; text-align:center; font-size:.85rem; color:var(--muted); font-style:italic; margin-top:10px; line-height:1.4 }'

            /* ── UNIVERSAL INLINE IMAGE CARD ── */
            . "\n" . '.img-card { border-radius:20px; overflow:visible; background:transparent; border:none }'
            . "\n" . '.img-card--right { float:right; margin:4px 0 28px 36px; max-width:340px; width:42% }'
            . "\n" . '.img-card--left  { float:left;  margin:4px 36px 28px 0; max-width:340px; width:42% }'
            . "\n" . '.img-card--center { display:block; clear:both; margin:28px auto; max-width:600px }'
            . "\n" . '.img-card--full   { display:block; clear:both; margin:28px 0; width:100%; max-width:100% }'
            . "\n" . '.img-card .img-frame { border-radius:20px; overflow:hidden; box-shadow:0 16px 56px rgba(15,23,42,.12) }'
            . "\n" . '.img-card .img-frame img { max-height:380px; object-fit:cover }'
            . "\n" . '.img-card-caption { padding:10px 4px 0; font-size:12.5px; color:var(--muted); text-align:center; font-style:italic; line-height:1.4 }'
            . "\n" . '[data-theme="dark"] .img-card .img-frame { box-shadow:0 16px 56px rgba(0,0,0,.35) }'
            . "\n" . '[data-theme="dark"] .blk-img .img-frame { box-shadow:0 12px 40px rgba(0,0,0,.3) }'
            . "\n" . '[data-theme="dark"] .hero-img-frame { box-shadow:0 24px 80px rgba(0,0,0,.4) }'

            /* ── HERO ── */
            . "\n" . '.block-hero { position:relative; overflow:hidden; padding:clamp(100px,16vw,160px) 0 clamp(60px,10vw,100px); text-align:left }'
            . "\n" . '.hero-orb { display:none }'
            . "\n" . '.hero-grid-bg { position:absolute; inset:0; background-image:linear-gradient(rgba(37,99,235,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(37,99,235,.04) 1px,transparent 1px); background-size:60px 60px; mask-image:radial-gradient(ellipse at 50% 40%,rgba(0,0,0,.3) 0%,transparent 70%); -webkit-mask-image:radial-gradient(ellipse at 50% 40%,rgba(0,0,0,.3) 0%,transparent 70%); pointer-events:none }'
            . "\n" . '.hero-inner { position:relative; z-index:1; display:grid; grid-template-columns:2fr 1fr; gap:48px; align-items:center; max-width:1000px }'
            . "\n" . '.hero-text { animation:fadeUp .6s ease both }'
            . "\n" . '.hero-subtitle { font-size:clamp(1rem,2vw,1.25rem); font-weight:300; color:var(--slate); max-width:540px; line-height:1.65; margin-bottom:2em }'
            . "\n" . '.hero-cta { display:inline-block; background:var(--blue); color:#fff; font-family:var(--fb); font-size:1rem; font-weight:600; padding:18px 40px; border-radius:100px; transition:all .25s; box-shadow:0 8px 28px rgba(37,99,235,.35) }'
            . "\n" . '.hero-cta:hover { background:var(--blue-dark); transform:translateY(-2px); box-shadow:0 12px 36px rgba(37,99,235,.45); text-decoration:none }'
            . "\n" . '.hero-visual { animation:fadeUp .6s .15s ease both }'
            . "\n" . '.hero-img-frame { border-radius:20px; overflow:hidden; box-shadow:0 24px 80px rgba(15,23,42,.14); position:relative }'
            . "\n" . '.hero-img-frame img { display:block; width:100%; max-height:460px; object-fit:cover }'

            /* ── STATS ── */
            . "\n" . '.block-stats { padding:60px 0 }'
            . "\n" . '.stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:16px; text-align:center }'
            . "\n" . '.stat-card { padding:28px 16px; border-radius:var(--r); background:rgba(255,255,255,.6); backdrop-filter:blur(8px); border:1px solid var(--border); transition:all .25s }'
            . "\n" . '[data-theme="dark"] .stat-card { background:rgba(255,255,255,.04) }'
            . "\n" . '.stat-card:hover { box-shadow:0 8px 28px rgba(37,99,235,.1); transform:translateY(-4px); border-color:rgba(37,99,235,.3) }'
            . "\n" . '.stat-value { font-family:var(--fh); font-size:2.2rem; font-weight:900; color:var(--blue); letter-spacing:-1px; line-height:1 }'
            . "\n" . '.stat-label { font-size:.85rem; color:var(--muted); margin-top:8px }'

            /* ── RICHTEXT ── */
            . "\n" . '.block-richtext { padding:64px 0 }'

            /* ── NORMS ── */
            . "\n" . '.block-norms { }'
            . "\n" . '.norms-status-list { display:flex; flex-direction:column; gap:10px }'

            /* Interactive norm card */
            . "\n" . '.norm-card { padding:20px 22px; border-radius:16px; background:rgba(255,255,255,.5); backdrop-filter:blur(10px); border:1px solid var(--border); transition:all .3s }'
            . "\n" . '[data-theme="dark"] .norm-card { background:rgba(255,255,255,.03) }'
            . "\n" . '.norm-card:hover { border-color:rgba(37,99,235,.18); box-shadow:0 4px 20px rgba(37,99,235,.05) }'
            . "\n" . '.norm-card-header { display:flex; justify-content:space-between; align-items:flex-start; gap:8px; margin-bottom:12px }'
            . "\n" . '.norm-name { color:var(--dark); font-weight:700; font-size:15px; font-family:var(--fh); min-width:0; flex:1; line-height:1.3 }'
            . "\n" . '.norm-badge { font-size:12px; font-weight:700; padding:5px 14px; border-radius:100px; font-family:var(--fh); display:inline-flex; align-items:center; gap:5px; transition:all .3s; white-space:nowrap }'
            . "\n" . '.norm-badge-icon { font-size:11px; line-height:1 }'

            /* State pills */
            . "\n" . '.norm-pills { display:flex; gap:4px; margin-bottom:14px; flex-wrap:wrap }'
            . "\n" . '.norm-pill { padding:6px 14px; border:1px solid var(--border); background:transparent; border-radius:100px; font-family:var(--fh); font-size:11.5px; font-weight:600; color:var(--muted); cursor:pointer; transition:all .25s; white-space:nowrap }'
            . "\n" . '.norm-pill:hover { border-color:var(--pill-c,var(--blue)); color:var(--pill-c,var(--blue)); background:rgba(37,99,235,.04) }'
            . "\n" . '.norm-pill.is-active { background:var(--pill-c,var(--blue)); color:#fff; border-color:var(--pill-c,var(--blue)); box-shadow:0 2px 8px rgba(0,0,0,.1) }'
            . "\n" . '.norm-bar-track { height:10px; border-radius:100px; background:var(--border); overflow:hidden; margin-bottom:10px }'
            . "\n" . '.norm-bar { height:100%; border-radius:100px; transition:width .6s cubic-bezier(.4,0,.2,1), background .4s ease }'

            /* Description text below bar */
            . "\n" . '.norm-desc { font-size:13px; color:var(--muted); line-height:1.5; min-height:20px; transition:opacity .3s ease; font-style:italic }'
            . "\n" . '.norm-desc.is-fading { opacity:0 }'

            /* ── PREMIUM TABLE (responsive card mode on mobile) ── */
            . "\n" . '.premium-table-wrap { overflow-x:auto; border-radius:var(--r) }'
            . "\n" . '.premium-table { width:100%; border-collapse:separate; border-spacing:0; font-size:.9rem }'
            . "\n" . '.premium-table thead { background:linear-gradient(135deg,var(--dark2),#1a2744) }'
            . "\n" . '[data-theme="dark"] .premium-table thead { background:linear-gradient(135deg,#0a1628,#152040) }'
            . "\n" . '.premium-table th { padding:14px 18px; text-align:left; font-family:var(--fh); font-weight:600; font-size:.78rem; text-transform:uppercase; letter-spacing:.8px; color:rgba(255,255,255,.85); white-space:nowrap }'
            . "\n" . '.premium-table th:first-child { border-radius:var(--r) 0 0 0 }'
            . "\n" . '.premium-table th:last-child { border-radius:0 var(--r) 0 0 }'
            . "\n" . '.premium-table td { padding:13px 18px; border-bottom:1px solid var(--border); color:var(--slate); font-size:.88rem; transition:background .15s }'
            . "\n" . '.premium-table tr:last-child td { border-bottom:none }'
            . "\n" . '.premium-table tr:nth-child(even) td { background:rgba(37,99,235,.02) }'
            . "\n" . '.premium-table tr:hover td { background:rgba(37,99,235,.06) }'
            . "\n" . '.premium-table td:first-child { font-weight:600; color:var(--dark); font-family:var(--fh) }'
            . "\n" . '.table-wrap { overflow-x:auto; border-radius:var(--r) }'
            . "\n" . 'table { width:100%; border-collapse:collapse; font-size:.9rem }'
            . "\n" . 'thead { background:linear-gradient(135deg,var(--blue),var(--teal)) }'
            . "\n" . 'th { padding:14px 18px; text-align:left; font-family:var(--fh); font-weight:700; font-size:.8rem; text-transform:uppercase; letter-spacing:.5px; color:#fff }'
            . "\n" . 'td { padding:11px 18px; border-bottom:1px solid var(--border); color:var(--slate) }'
            . "\n" . 'tr:last-child td { border-bottom:none }'
            . "\n" . 'tr:nth-child(even) td { background:rgba(37,99,235,.03) }'
            . "\n" . 'tr:hover td { background:var(--blue-light) }'
            . "\n" . '.cmp-table td.cell-yes { color:var(--green); font-weight:700; font-size:1.2em; text-align:center }'
            . "\n" . '.cmp-table td.cell-no { color:var(--red); font-weight:700; font-size:1.2em; text-align:center }'

            /* ── PREMIUM DONUT / PIE (SVG interactive, side-by-side) ── */
            . "\n" . '.donut-layout { display:grid; grid-template-columns:auto 1fr; grid-template-rows:auto 1fr; gap:8px 36px; align-items:start }'
            . "\n" . '.donut-aside-desc { grid-column:2; grid-row:1; font-size:14px; color:var(--muted); line-height:1.65; margin:0; padding-top:4px }'
            . "\n" . '.donut-visual { grid-column:1; grid-row:1/3; align-self:center }'
            . "\n" . '.donut-aside { grid-column:2; grid-row:2; display:flex; flex-direction:column; gap:12px; min-width:0 }'

            /* SVG ring */
            . "\n" . '.donut-svg-wrap { position:relative; width:220px; height:220px }'
            . "\n" . '.donut-svg { width:100%; height:100%; filter:drop-shadow(0 4px 16px rgba(0,0,0,.08)) }'
            . "\n" . '.donut-seg { transition:opacity .35s ease, stroke-width .35s ease, filter .35s ease }'
            . "\n" . '.donut-seg:hover { filter:brightness(1.12) }'
            . "\n" . '[data-donut].has-active .donut-seg { opacity:.25 }'
            . "\n" . '[data-donut].has-active .donut-seg.is-active { opacity:1; filter:drop-shadow(0 0 8px rgba(0,0,0,.2)) }'

            /* Center hole */
            . "\n" . '.donut-svg-wrap .donut-hole { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:56%; height:56%; border-radius:50%; background:var(--white); display:flex; flex-direction:column; align-items:center; justify-content:center; box-shadow:inset 0 2px 12px rgba(0,0,0,.04); pointer-events:none }'
            . "\n" . '[data-theme="dark"] .donut-svg-wrap .donut-hole { background:var(--dark2) }'
            . "\n" . '.donut-total { font-family:var(--fh); font-size:1.8rem; font-weight:900; color:var(--dark); letter-spacing:-1px; line-height:1 }'
            . "\n" . '.donut-total-label { font-size:.72rem; color:var(--muted); text-transform:uppercase; letter-spacing:1.5px; margin-top:4px }'

            /* Legend (vertical stack in aside) */
            . "\n" . '.donut-legend { display:flex; flex-direction:column; gap:6px }'
            . "\n" . '.donut-legend-item { display:flex; align-items:center; gap:8px; padding:8px 14px; border-radius:10px; background:rgba(255,255,255,.5); border:1px solid var(--border); transition:all .25s; min-width:0; cursor:pointer; font-family:var(--fb); font-size:inherit; color:inherit }'
            . "\n" . '[data-theme="dark"] .donut-legend-item { background:rgba(255,255,255,.04) }'
            . "\n" . '.donut-legend-item:hover { border-color:rgba(37,99,235,.3); box-shadow:0 2px 12px rgba(37,99,235,.06) }'
            . "\n" . '.donut-legend-item.is-active { border-color:var(--blue); box-shadow:0 2px 12px rgba(37,99,235,.12); background:var(--blue-light) }'
            . "\n" . '[data-donut].has-active .donut-legend-item:not(.is-active) { opacity:.45 }'
            . "\n" . '.donut-legend-dot { width:10px; height:10px; border-radius:3px; flex-shrink:0; transition:transform .25s }'
            . "\n" . '.donut-legend-item.is-active .donut-legend-dot { transform:scale(1.3) }'
            . "\n" . '.donut-legend-text { font-size:13px; color:var(--slate); font-weight:500; white-space:normal; min-width:0; overflow:hidden }'
            . "\n" . '.donut-legend-val { font-family:var(--fh); font-size:13px; font-weight:700; color:var(--dark); margin-left:auto; white-space:nowrap }'
            . "\n" . '.donut-legend-val small { font-weight:400; color:var(--muted); font-size:11px }'

            /* Segment detail panel — no max-height clipping, use grid for smooth open */
            . "\n" . '.donut-detail { display:grid; grid-template-rows:0fr; transition:grid-template-rows .4s ease, opacity .3s ease; opacity:0 }'
            . "\n" . '.donut-detail.is-open { grid-template-rows:1fr; opacity:1 }'
            . "\n" . '.donut-detail > * { overflow:hidden }'
            . "\n" . '.donut-detail-wrap { padding:14px 16px; border-radius:12px; background:rgba(255,255,255,.45); border:1px solid var(--border); backdrop-filter:blur(6px) }'
            . "\n" . '[data-theme="dark"] .donut-detail-wrap { background:rgba(255,255,255,.03) }'
            . "\n" . '.donut-detail-inner { display:flex; align-items:center; gap:8px; margin-bottom:6px }'
            . "\n" . '.donut-detail-dot { width:8px; height:8px; border-radius:2px; flex-shrink:0 }'
            . "\n" . '.donut-detail-label { font-family:var(--fh); font-size:14px; font-weight:700; color:var(--dark) }'
            . "\n" . '.donut-detail-desc { font-size:13px; color:var(--muted); line-height:1.55 }'

            /* ── COMPARISON TABS (Claude-Code style) ── */
            . "\n" . '.cmp-desc { font-size:14px; color:var(--muted); line-height:1.6; margin-bottom:24px; max-width:680px }'
            . "\n" . '.cmp-tabs { margin-bottom:32px }'
            . "\n" . '.cmp-tabs-nav { display:flex; gap:4px; padding:4px; background:rgba(255,255,255,.5); backdrop-filter:blur(8px); border:1px solid var(--border); border-radius:14px; margin-bottom:20px; overflow-x:auto; -webkit-overflow-scrolling:touch }'
            . "\n" . '[data-theme="dark"] .cmp-tabs-nav { background:rgba(255,255,255,.04) }'
            . "\n" . '.cmp-tab-btn { flex:1; min-width:0; padding:12px 20px; border:none; background:transparent; font-family:var(--fh); font-size:14px; font-weight:600; color:var(--muted); cursor:pointer; border-radius:10px; transition:all .25s; white-space:nowrap }'
            . "\n" . '.cmp-tab-btn:hover { color:var(--dark); background:rgba(37,99,235,.06) }'
            . "\n" . '.cmp-tab-btn.is-active { color:var(--blue); background:var(--white); box-shadow:0 2px 8px rgba(0,0,0,.06); border:1px solid var(--border) }'
            . "\n" . '[data-theme="dark"] .cmp-tab-btn.is-active { background:rgba(37,99,235,.12); border-color:rgba(37,99,235,.3) }'

            . "\n" . '.cmp-tab-panel { display:none; animation:fadeUp .35s ease both }'
            . "\n" . '.cmp-tab-panel.is-active { display:block }'
            . "\n" . '.cmp-cards-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:12px }'
            . "\n" . '.cmp-card { padding:18px 20px; border-radius:14px; background:rgba(255,255,255,.6); backdrop-filter:blur(8px); border:1px solid var(--border); transition:all .25s; display:flex; justify-content:space-between; align-items:center; gap:12px; min-width:0 }'
            . "\n" . '[data-theme="dark"] .cmp-card { background:rgba(255,255,255,.03) }'
            . "\n" . '.cmp-card:hover { border-color:rgba(37,99,235,.25); box-shadow:0 4px 16px rgba(37,99,235,.06); transform:translateY(-2px) }'
            . "\n" . '.cmp-card-feature { font-size:14px; color:var(--dark); font-weight:500; min-width:0; word-break:break-word }'
            . "\n" . '.cmp-card-val { font-family:var(--fh); font-weight:700; font-size:15px; color:var(--slate); flex-shrink:0; max-width:55%; text-align:right; word-break:break-word }'
            . "\n" . '.cmp-card-val--yes { color:var(--green); font-size:1.3em }'
            . "\n" . '.cmp-card-val--no { color:var(--red); font-size:1.3em }'

            /* ── COMPARISON CAROUSEL (desktop overview cards) ── */
            . "\n" . '.cmp-carousel { margin-top:12px; overflow:hidden; position:relative }'
            . "\n" . '.cmp-carousel-track { display:flex; gap:12px; overflow-x:auto; scroll-snap-type:x mandatory; -webkit-overflow-scrolling:touch; scrollbar-width:none; padding:4px 0 12px }'
            . "\n" . '.cmp-carousel-track::-webkit-scrollbar { display:none }'
            . "\n" . '.cmp-carousel-card { flex:0 0 auto; width:180px; scroll-snap-align:start; padding:22px 20px; border-radius:16px; background:rgba(255,255,255,.55); backdrop-filter:blur(10px); border:1px solid var(--border); text-align:center; transition:all .3s; cursor:pointer }'
            . "\n" . '[data-theme="dark"] .cmp-carousel-card { background:rgba(255,255,255,.04) }'
            . "\n" . '.cmp-carousel-card:hover { border-color:rgba(37,99,235,.3); box-shadow:0 8px 28px rgba(37,99,235,.08); transform:translateY(-3px) }'
            . "\n" . '.cmp-carousel-card-title { font-family:var(--fh); font-size:15px; font-weight:700; color:var(--dark); margin-bottom:12px }'
            . "\n" . '.cmp-carousel-card-stat { font-family:var(--fh); font-size:2rem; font-weight:900; color:var(--blue); letter-spacing:-1px; line-height:1 }'
            . "\n" . '.cmp-carousel-card-label { font-size:12px; color:var(--muted); margin-top:6px }'
            . "\n" . '.cmp-carousel-nav { display:flex; align-items:center; justify-content:center; gap:12px; margin-top:12px }'
            . "\n" . '.cmp-carousel-btn { width:36px; height:36px; border:1px solid var(--border); background:rgba(255,255,255,.6); border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:16px; color:var(--muted); transition:all .2s }'
            . "\n" . '[data-theme="dark"] .cmp-carousel-btn { background:rgba(255,255,255,.04) }'
            . "\n" . '.cmp-carousel-btn:hover { border-color:var(--blue); color:var(--blue); background:var(--blue-light) }'
            . "\n" . '.cmp-carousel-dots { display:flex; gap:6px }'
            . "\n" . '.cmp-carousel-dot { width:8px; height:8px; border-radius:50%; background:var(--border); transition:all .2s }'
            . "\n" . '.cmp-carousel-dot.is-active { background:var(--blue); transform:scale(1.2) }'

            /* ── CHART ── */
            . "\n" . '.block-chart { }'
            . "\n" . '.chart-desc { font-size:14px; color:var(--muted); line-height:1.6; margin-bottom:20px; max-width:560px }'
            . "\n" . '.chartjs-wrap { max-width:600px; margin:0 auto; padding:8px }'
            . "\n" . '.chartjs-wrap--donut { max-width:420px }'
            . "\n" . '.chartjs-wrap--line { max-width:680px }'
            . "\n" . '.css-chart { display:flex; flex-direction:column; gap:10px }'
            . "\n" . '.chart-row { display:grid; grid-template-columns:120px 1fr 55px; gap:12px; align-items:center; padding:6px 0 }'
            . "\n" . '.chart-label { color:var(--slate); font-weight:500; font-size:13px; text-align:right; white-space:normal; line-height:1.3 }'
            . "\n" . '.chart-row .bar-track { height:32px; border-radius:10px; background:rgba(226,232,240,.35) }'
            . "\n" . '[data-theme="dark"] .chart-row .bar-track { background:rgba(255,255,255,.06) }'
            . "\n" . '.chart-row .bar-fill { border-radius:10px; position:relative; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08) }'
            . "\n" . '.chart-val { font-family:var(--fh); font-weight:700; color:var(--dark); font-size:14px }'

            /* ── ACCORDION ── */
            . "\n" . '.block-accordion { }'
            . "\n" . 'details { border:1px solid var(--border); border-radius:var(--r); margin-bottom:10px; overflow:hidden; background:rgba(255,255,255,.55); backdrop-filter:blur(8px); transition:all .25s }'
            . "\n" . '[data-theme="dark"] details { background:rgba(255,255,255,.04) }'
            . "\n" . 'details[open],details:hover { box-shadow:0 4px 20px rgba(37,99,235,.06); border-color:rgba(37,99,235,.25) }'
            . "\n" . 'summary { padding:18px 22px; font-family:var(--fh); font-weight:700; font-size:.95rem; cursor:pointer; background:transparent; list-style:none; display:flex; align-items:center; gap:10px; color:var(--dark); transition:background .2s }'
            . "\n" . 'details[open] summary { background:var(--blue-light) }'
            . "\n" . 'summary::-webkit-details-marker { display:none }'
            . "\n" . 'summary::before { content:""; flex-shrink:0; width:18px; height:18px; border-radius:50%; background:linear-gradient(135deg,var(--blue),var(--teal)); transition:transform .25s }'
            . "\n" . 'details[open] summary::before { transform:rotate(90deg) }'
            . "\n" . '.acc-body { padding:18px 22px; border-top:1px solid var(--border); color:var(--slate); line-height:1.65; font-size:.95rem }'

            /* ── IMAGE SECTION ── */
            . "\n" . '.block-imgsec { padding:80px 0 }'
            . "\n" . '.imgsec-flex { display:flex; gap:48px; align-items:center }'
            . "\n" . '.imgsec-visual { flex:1; min-width:0 }'
            . "\n" . '.imgsec-text { flex:1; min-width:0 }'
            . "\n" . '.imgsec--reverse .imgsec-flex { flex-direction:row-reverse }'

            /* ── FAQ ── */
            . "\n" . '.block-faq { }'
            . "\n" . '.faq-list { display:flex; flex-direction:column; gap:10px; max-width:780px }'
            . "\n" . '.faq-item { border:1px solid var(--border); border-radius:var(--r); overflow:hidden; transition:border-color .25s; background:rgba(255,255,255,.5); backdrop-filter:blur(8px) }'
            . "\n" . '[data-theme="dark"] .faq-item { background:rgba(255,255,255,.04) }'
            . "\n" . '.faq-item:hover,.faq-item.open { border-color:rgba(37,99,235,.3) }'
            . "\n" . '.faq-q { width:100%; background:transparent; color:var(--dark); font-family:var(--fb); font-size:15px; font-weight:500; padding:18px 22px; text-align:left; border:none; cursor:pointer; display:flex; justify-content:space-between; align-items:center; gap:16px; transition:background .2s }'
            . "\n" . '.faq-q:hover { background:var(--blue-light) }'
            . "\n" . '.faq-arr { font-size:20px; color:var(--blue); transition:transform .3s; flex-shrink:0; font-weight:300 }'
            . "\n" . '.faq-a { max-height:0; overflow:hidden; transition:max-height .4s ease }'
            . "\n" . '.faq-a-in { padding:18px 22px; border-top:1px solid var(--border); color:var(--slate); line-height:1.65; font-size:.95rem }'
            . "\n" . '.faq-item.open .faq-arr { transform:rotate(45deg) }'
            . "\n" . '.faq-item.open .faq-a { max-height:600px }'
            . "\n" . '.faq-item.open .faq-q { background:var(--blue-light) }'

            /* ── CTA ── */
            . "\n" . '.block-cta { background:linear-gradient(135deg,rgba(37,99,235,.88) 0%,rgba(13,148,136,.88) 100%); backdrop-filter:blur(24px); color:#fff; text-align:center; position:relative; overflow:hidden; padding:100px 0 }'
            . "\n" . '[data-theme="dark"] .block-cta { background:linear-gradient(135deg,rgba(30,58,138,.92) 0%,rgba(19,78,74,.92) 100%) }'
            . "\n" . '.cta-orb { position:absolute; border-radius:50%; pointer-events:none; filter:blur(70px) }'
            . "\n" . '.cta-orb--1 { width:min(50vw,500px); height:min(50vw,500px); top:-120px; right:-100px; background:radial-gradient(circle,rgba(255,255,255,.22) 0%,rgba(255,255,255,.06) 45%,transparent 70%); animation:pDrift1 7s ease-in-out infinite }'
            . "\n" . '.cta-orb--2 { width:min(40vw,400px); height:min(40vw,400px); bottom:-100px; left:-80px;  background:radial-gradient(circle,rgba(255,255,255,.18) 0%,rgba(255,255,255,.05) 45%,transparent 70%); animation:pDrift2 9s ease-in-out infinite }'
            . "\n" . '.cta-orb--3 { width:min(30vw,300px); height:min(30vw,300px); top:20%; left:35%;          background:radial-gradient(circle,rgba(255,255,255,.12) 0%,rgba(255,255,255,.03) 45%,transparent 70%); animation:pDrift3 11s ease-in-out infinite }'
            . "\n" . '.block-cta h2 { color:#fff; margin-top:0; position:relative }'
            . "\n" . '.block-cta p { color:rgba(255,255,255,.85); margin-bottom:0; position:relative }'
            . "\n" . '.cta-buttons { display:flex; gap:16px; justify-content:center; margin-top:2em; flex-wrap:wrap; position:relative }'
            . "\n" . '.btn-primary { display:inline-block; background:#fff; color:var(--blue); font-family:var(--fb); font-weight:700; font-size:1rem; padding:18px 40px; border-radius:100px; transition:all .25s; text-decoration:none }'
            . "\n" . '.btn-primary:hover { transform:translateY(-2px); box-shadow:0 10px 28px rgba(0,0,0,.2); text-decoration:none }'
            . "\n" . '.btn-outline { display:inline-block; background:transparent; color:#fff; font-family:var(--fb); font-weight:600; font-size:1rem; border:2px solid rgba(255,255,255,.65); padding:16px 38px; border-radius:100px; transition:all .25s; text-decoration:none }'
            . "\n" . '.btn-outline:hover { background:rgba(255,255,255,.15); border-color:#fff; text-decoration:none }'

            /* ── FEATURES ── */
            . "\n" . '.block-features { }'
            . "\n" . '.features-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:16px }'
            . "\n" . '.feature-card { background:rgba(255,255,255,.55); backdrop-filter:blur(10px); border:1px solid var(--border); border-radius:var(--r); padding:28px; transition:all .25s; position:relative; overflow:hidden }'
            . "\n" . '[data-theme="dark"] .feature-card { background:rgba(255,255,255,.04) }'
            . "\n" . '.feature-card::before { content:""; position:absolute; inset:0; background:linear-gradient(135deg,rgba(37,99,235,.06) 0%,transparent 60%); opacity:0; transition:opacity .25s }'
            . "\n" . '.feature-card:hover { border-color:rgba(37,99,235,.3); box-shadow:0 8px 28px rgba(37,99,235,.08); transform:translateY(-4px) }'
            . "\n" . '.feature-card:hover::before { opacity:1 }'
            . "\n" . '.feature-icon { font-size:2rem; margin-bottom:16px; display:block }'
            . "\n" . '.feature-num { font-family:var(--fh); font-size:22px; font-weight:900; color:var(--blue); opacity:.15; position:absolute; top:16px; right:20px; line-height:1 }'
            . "\n" . '.feature-card h4 { position:relative; color:var(--dark); margin:0 0 8px }'
            . "\n" . '.feature-card p { position:relative; font-size:13px; color:var(--muted); line-height:1.6; margin-bottom:0 }'

            /* ── REVIEWS ── */
            . "\n" . '.block-reviews { background:rgba(15,23,42,.9); backdrop-filter:blur(20px); padding:80px 0 }'
            . "\n" . '[data-theme="dark"] .block-reviews { background:rgba(2,8,16,.92) }'
            . "\n" . '.reviews-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:16px }'
            . "\n" . '.review-card { background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.08); border-radius:var(--r); padding:24px; transition:all .25s }'
            . "\n" . '.review-card:hover { transform:translateY(-3px); box-shadow:0 8px 28px rgba(0,0,0,.2); border-color:rgba(255,255,255,.15) }'
            . "\n" . '.review-stars { color:#FBBF24; font-size:14px; letter-spacing:2px; margin-bottom:12px }'
            . "\n" . '.review-text { font-size:14px; color:rgba(255,255,255,.75); line-height:1.65; margin-bottom:18px; font-style:italic }'
            . "\n" . '.review-who { display:flex; align-items:center; gap:10px }'
            . "\n" . '.review-av { width:38px; height:38px; border-radius:50%; background:linear-gradient(135deg,var(--blue),var(--teal)); display:flex; align-items:center; justify-content:center; font-family:var(--fh); font-size:13px; font-weight:700; color:#fff; flex-shrink:0 }'
            . "\n" . '.review-name { font-size:14px; font-weight:500; color:#fff }'
            . "\n" . '.review-role { font-size:12px; color:rgba(255,255,255,.4); margin-top:2px }'

            /* ── COMPARISON (legacy fallback) ── */
            . "\n" . '.block-comparison { }'
            . "\n" . '.block-comparison .sec-title { margin-bottom:24px }'

            /* ── ANIMATIONS ── */
            . "\n" . '@keyframes fadeUp { from { opacity:0; transform:translateY(24px) } to { opacity:1; transform:none } }'
            . "\n" . '@keyframes shimmer { 0% { transform:translateX(-100%) } 50%,100% { transform:translateX(100%) } }'
            /* 13 unique parallax idle drift animations */
            . "\n" . '@keyframes pDrift1  { 0%,100%{transform:translate(0,0)   scale(1)}    50%{transform:translate(45px, 55px)  scale(1.07)} }'
            . "\n" . '@keyframes pDrift2  { 0%,100%{transform:translate(0,0)   scale(1)}    50%{transform:translate(-50px,-42px) scale(1.05)} }'
            . "\n" . '@keyframes pDrift3  { 0%,100%{transform:translate(0,0)   scale(1)}    50%{transform:translate(32px,-58px) scale(1.08)} }'
            . "\n" . '@keyframes pDrift4  { 0%,100%{transform:translate(0,0)   scale(1)}    50%{transform:translate(-38px,48px) scale(1.06)} }'
            . "\n" . '@keyframes pDrift5  { 0%,100%{transform:translate(0,0)   scale(1)}    50%{transform:translate(60px,-30px) scale(1.09)} }'
            . "\n" . '@keyframes pDrift6  { 0%,100%{transform:translate(0,0)   scale(1)}    50%{transform:translate(-28px,62px) scale(1.04)} }'
            . "\n" . '@keyframes pDrift7  { 0%,100%{transform:translate(0,0)   scale(1)}    50%{transform:translate(52px, 38px) scale(1.06)} }'
            . "\n" . '@keyframes pDrift8  { 0%,100%{transform:translate(0,0)   scale(1)}    50%{transform:translate(-44px,-34px)scale(1.07)} }'
            . "\n" . '@keyframes pDrift9  { 0%,100%{transform:translate(0,0)   scale(1)}    50%{transform:translate(36px, 50px) scale(1.05)} }'
            . "\n" . '@keyframes pDrift10 { 0%,100%{transform:translate(0,0)   scale(1)}    50%{transform:translate(-55px,28px) scale(1.08)} }'
            . "\n" . '@keyframes pDrift11 { 0%,100%{transform:translate(0,0)   scale(1)}    50%{transform:translate(42px,-46px) scale(1.06)} }'
            . "\n" . '@keyframes pDrift12 { 0%,100%{transform:translate(0,0)   scale(1)}    50%{transform:translate(-32px,-52px)scale(1.05)} }'
            . "\n" . '@keyframes pDrift13 { 0%,100%{transform:translate(0,0)   scale(1)}    50%{transform:translate(58px, 44px) scale(1.07)} }'

            /* ── RESPONSIVE ── */
            . "\n" . '@media(max-width:1100px) { .toc { display:none } }'
            . "\n" . '@media(max-width:768px) {'
            .   'section { padding:56px 0 }'
            .   '.hero-inner { grid-template-columns:1fr; gap:32px; text-align:center }'
            .   '.hero-subtitle { margin-left:auto; margin-right:auto }'
            .   '.hero-cta { margin:0 auto; display:inline-block }'
            .   '.imgsec-flex,.imgsec--reverse .imgsec-flex { flex-direction:column }'
            .   '.stats-grid { grid-template-columns:repeat(2,1fr) }'
            .   '.features-grid { grid-template-columns:1fr }'
            .   '.chart-row { grid-template-columns:80px 1fr 45px }'
            .   '.navbar-inner { padding:12px 16px }'
            .   '.nav-links { display:none }'
            .   '.img-card--right,.img-card--left { float:none; margin:20px auto; max-width:100%; width:100% }'
            .   '.img-card--center { max-width:100% }'
            .   '.img-card .img-frame img { max-height:280px }'
            .   '.blk-img,.blk-img--bottom { margin:20px 0 }'
            .   '.blk-img .img-frame img { max-height:260px }'
            .   '.section-divider-line { width:calc(100% - 48px) }'
            /* Mobile: tables → card layout */
            .   '.premium-table { border:0 }'
            .   '.premium-table thead { display:none }'
            .   '.premium-table tbody tr { display:block; margin-bottom:12px; padding:16px; border-radius:14px; background:rgba(255,255,255,.6); border:1px solid var(--border); backdrop-filter:blur(8px) }'
            .   '[data-theme="dark"] .premium-table tbody tr { background:rgba(255,255,255,.04) }'
            .   '.premium-table tbody td { display:flex; justify-content:space-between; align-items:center; padding:8px 4px; border-bottom:1px solid rgba(0,0,0,.04); font-size:.88rem }'
            .   '[data-theme="dark"] .premium-table tbody td { border-bottom-color:rgba(255,255,255,.04) }'
            .   '.premium-table tbody td:last-child { border-bottom:none }'
            .   '.premium-table tbody td:first-child { font-size:.95rem; font-weight:700; color:var(--dark); padding-bottom:10px; border-bottom:1px solid var(--border) }'
            .   '.premium-table tbody td::before { content:attr(data-label); font-size:.78rem; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.5px; flex-shrink:0; margin-right:12px }'
            .   '.premium-table tbody td:first-child::before { display:none }'
            /* Mobile: donut stacked */
            .   '.donut-layout { display:flex; flex-direction:column; align-items:center; gap:20px }'
            .   '.donut-aside-desc { text-align:center; order:-1 }'
            .   '.donut-visual { order:0 }'
            .   '.donut-aside { order:1; width:100% }'
            .   '.donut-svg-wrap { width:180px; height:180px }'
            .   '.donut-total { font-size:1.4rem }'
            .   '.donut-legend { align-items:stretch }'
            .   '.donut-legend-item { justify-content:space-between }'
            /* Mobile: tabs scroll */
            .   '.cmp-tabs-nav { flex-wrap:nowrap; overflow-x:auto; -webkit-overflow-scrolling:touch }'
            .   '.cmp-tab-btn { flex:0 0 auto; padding:10px 16px; font-size:13px }'
            .   '.cmp-cards-grid { grid-template-columns:1fr }'
            .   '.cmp-card { flex-wrap:wrap }'
            .   '.cmp-card-feature { width:100%; max-width:100% }'
            .   '.cmp-card-val { max-width:100%; text-align:left }'
            /* Mobile: carousel */
            .   '.cmp-carousel-card { width:150px }'
            .   '.cmp-carousel-card-stat { font-size:1.6rem }'
            /* Mobile: norms pills */
            .   '.norm-pills { gap:3px }'
            .   '.norm-pill { padding:5px 10px; font-size:10.5px }'
            .   '.norm-card { padding:16px }'
            . '}'
            . "\n" . '@media(max-width:414px) {'
            .   '.hero-cta { padding:16px 28px; font-size:.95rem }'
            .   '.btn-primary,.btn-outline { padding:16px 24px; font-size:.95rem }'
            .   '.stat-value { font-size:1.8rem }'
            .   '.mac-body { padding:16px }'
            .   '.donut-svg-wrap { width:160px; height:160px }'
            .   '.chart-row { grid-template-columns:60px 1fr 40px; gap:8px }'
            .   '.chart-row .bar-track { height:24px }'
            .   '.chart-label { font-size:11px }'
            .   '.chart-val { font-size:12px }'
            .   '.norm-pill { padding:4px 8px; font-size:10px }'
            .   '.img-card .img-frame img { max-height:220px }'
            .   '.blk-img .img-frame img { max-height:200px }'
            .   '.hero-img-frame img { max-height:280px }'
            . '}'

            /* ── RICHTEXT IMAGES ── */
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
            . '}'


            /* ── NAVBAR SEARCH ── */
            . "\n" . '.nav-search-wrap{position:relative;display:flex;align-items:center;margin-left:auto;margin-right:.5rem}'
            . "\n" . '.nav-search-toggle{background:none;border:none;cursor:pointer;color:var(--text-muted,#8b949e);padding:.4rem;border-radius:6px;display:flex;align-items:center;transition:color .2s,background .2s}'
            . "\n" . '.nav-search-toggle:hover,.nav-search-toggle.is-active{color:var(--accent,#58a6ff);background:rgba(88,166,255,.08)}'
            . "\n" . '.nav-search-box{position:absolute;top:calc(100% + 8px);right:0;width:320px;background:var(--surface,#161b22);border:1px solid var(--border,#30363d);border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.5);overflow:hidden;display:none;flex-direction:column;z-index:9999}'
            . "\n" . '.nav-search-box.is-open{display:flex}'
            . "\n" . '.nav-search-input{width:100%;box-sizing:border-box;padding:.7rem 1rem;background:transparent;border:none;border-bottom:1px solid var(--border,#30363d);color:var(--text,#e6edf3);font-size:.95rem;outline:none}'
            . "\n" . '.nav-search-input::placeholder{color:var(--text-muted,#8b949e)}'
            . "\n" . '.nav-search-results{max-height:360px;overflow-y:auto}'
            . "\n" . '.ns-item{display:flex;flex-direction:column;gap:.2rem;padding:.65rem 1rem;color:var(--text,#e6edf3);text-decoration:none;border-bottom:1px solid var(--border,#30363d);transition:background .15s;cursor:pointer;outline:none}'
            . "\n" . '.ns-item:last-child{border-bottom:none}'
            . "\n" . '.ns-item:hover,.ns-item.is-focused{background:rgba(88,166,255,.08);color:var(--accent,#58a6ff)}'
            . "\n" . '.ns-title{font-size:.9rem;font-weight:500}'
            . "\n" . '.ns-desc{font-size:.78rem;color:var(--text-muted,#8b949e);line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}'
            . "\n" . '.ns-loading,.ns-empty,.ns-error{padding:.8rem 1rem;color:var(--text-muted,#8b949e);font-size:.85rem;text-align:center}'
            . "\n" . '.ns-error{color:#f85149}'
            . "\n" . '.related-articles{padding:3rem 0 4rem;background:var(--bg-alt,#0d1117)}'
            . "\n" . '.ra-heading{font-size:1.6rem;font-weight:700;margin-bottom:1.5rem;color:var(--text,#e6edf3)}'
            . "\n" . '.ra-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem}'
            . "\n" . '.ra-card{display:flex;flex-direction:column;gap:.5rem;padding:1.2rem 1.25rem;background:var(--surface,#161b22);border:1px solid var(--border,#30363d);border-radius:12px;text-decoration:none;color:var(--text,#e6edf3);transition:border-color .2s,transform .2s,box-shadow .2s}'
            . "\n" . '.ra-card:hover{border-color:var(--accent,#58a6ff);transform:translateY(-3px);box-shadow:0 8px 24px rgba(88,166,255,.12)}'
            . "\n" . '.ra-card-title{font-size:.95rem;font-weight:600;line-height:1.35;color:var(--text,#e6edf3)}'
            . "\n" . '.ra-card-desc{font-size:.8rem;color:var(--text-muted,#8b949e);line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin:0}'
            . "\n" . '.ra-card-arrow{margin-top:auto;color:var(--accent,#58a6ff);font-size:.9rem;font-weight:700;align-self:flex-end}'
            . "\n" . '.ra-skeleton{height:140px;border-radius:12px;background:linear-gradient(90deg,var(--border,rgba(255,255,255,.06)) 25%,rgba(255,255,255,.04) 50%,var(--border,rgba(255,255,255,.06)) 75%);background-size:200% 100%;animation:ra-shimmer 1.4s infinite}'
            . "\n" . '@keyframes ra-shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}'

            /* ══════ NEW BLOCKS CSS ══════ */
            . "\n" . '@keyframes pulseRing{0%,100%{box-shadow:0 0 0 0 var(--pulse-c,rgba(37,99,235,.3))}50%{box-shadow:0 0 0 8px transparent}}'
            . "\n" . '@keyframes spPulse{0%,100%{r:3;opacity:.3}50%{r:7;opacity:0}}'

            /* ── GAUGE CHART ── */
            . "\n" . '.gauge-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px}'
            . "\n" . '.gauge-card{padding:24px 18px 18px;border-radius:18px;background:rgba(255,255,255,.55);backdrop-filter:blur(12px);border:1px solid var(--border);text-align:center;transition:all .35s;cursor:pointer}'
            . "\n" . '[data-theme="dark"] .gauge-card{background:rgba(255,255,255,.03)}'
            . "\n" . '.gauge-card:hover,.gauge-card.is-active{transform:translateY(-4px);box-shadow:0 12px 40px rgba(37,99,235,.1);border-color:rgba(37,99,235,.25)}'
            . "\n" . '.gauge-card.is-active{border-color:var(--gauge-c,var(--blue));box-shadow:0 0 0 2px var(--gauge-c,var(--blue)),0 12px 40px rgba(37,99,235,.12)}'
            . "\n" . '[data-gauges].has-active .gauge-card:not(.is-active){opacity:.4;transform:scale(.97)}'
            . "\n" . '.gauge-svg{width:130px;height:75px;display:block;margin:0 auto 10px}'
            . "\n" . '.gauge-track{fill:none;stroke:var(--border);stroke-width:10;stroke-linecap:round}'
            . "\n" . '.gauge-fill{fill:none;stroke-width:10;stroke-linecap:round;transition:stroke-dashoffset 1.6s cubic-bezier(.4,0,.2,1);filter:drop-shadow(0 0 6px var(--gauge-glow,rgba(37,99,235,.25)))}'
            . "\n" . '.gauge-val{font-family:var(--fh);font-size:1.5rem;font-weight:900;color:var(--dark);letter-spacing:-1px;line-height:1}'
            . "\n" . '.gauge-val small{font-size:.5em;font-weight:500;color:var(--muted);margin-left:2px}'
            . "\n" . '.gauge-name{font-size:12.5px;color:var(--muted);margin-top:3px;font-weight:500}'
            . "\n" . '.gauge-range{display:flex;justify-content:space-between;font-size:9.5px;color:var(--muted);margin-top:6px;padding:0 8px;font-family:var(--fh);font-weight:600;opacity:.4}'
            . "\n" . '.gauge-detail{display:grid;grid-template-rows:0fr;transition:grid-template-rows .4s ease,opacity .35s ease;opacity:0;margin-top:16px}'
            . "\n" . '.gauge-detail.is-open{grid-template-rows:1fr;opacity:1}'
            . "\n" . '.gauge-detail>*{overflow:hidden}'
            . "\n" . '.gauge-detail-inner{padding:16px 18px;border-radius:14px;background:rgba(255,255,255,.45);border:1px solid var(--border);backdrop-filter:blur(6px)}'
            . "\n" . '[data-theme="dark"] .gauge-detail-inner{background:rgba(255,255,255,.03)}'
            . "\n" . '.gauge-detail-header{display:flex;align-items:center;gap:8px;margin-bottom:6px}'
            . "\n" . '.gauge-detail-dot{width:10px;height:10px;border-radius:3px;flex-shrink:0}'
            . "\n" . '.gauge-detail-label{font-family:var(--fh);font-size:14px;font-weight:700;color:var(--dark)}'
            . "\n" . '.gauge-detail-val{font-family:var(--fh);font-size:24px;font-weight:900;color:var(--dark);margin-left:auto}'
            . "\n" . '.gauge-detail-desc{font-size:13px;color:var(--muted);line-height:1.55}'
            . "\n" . '.gauge-detail-bar-track{height:6px;border-radius:100px;background:var(--border);overflow:hidden;margin-top:10px}'
            . "\n" . '.gauge-detail-bar{height:100%;border-radius:100px;transition:width .8s ease,background .4s ease}'

            /* ── TIMELINE ── */
            . "\n" . '.tl-wrap{position:relative;padding:8px 0 8px 44px;margin-top:12px}'
            . "\n" . '.tl-line{position:absolute;left:16px;top:0;bottom:0;width:2px;background:var(--border)}'
            . "\n" . '.tl-line-fill{position:absolute;left:16px;top:0;width:2px;height:0;background:linear-gradient(180deg,var(--blue),var(--teal),var(--purple),var(--green),var(--orange));border-radius:2px;transition:height 2s cubic-bezier(.4,0,.2,1)}'
            . "\n" . '.tl-item{position:relative;padding:0 0 14px;opacity:0;transform:translateX(-16px);transition:all .5s ease;cursor:pointer}'
            . "\n" . '.tl-item.is-shown{opacity:1;transform:translateX(0)}'
            . "\n" . '.tl-dot{position:absolute;left:-36px;top:8px;width:18px;height:18px;border-radius:50%;border:3px solid var(--tl-c,var(--blue));background:var(--bg);transition:all .4s;z-index:2}'
            . "\n" . '.tl-item.is-shown .tl-dot{background:var(--tl-c,var(--blue));box-shadow:0 0 0 4px rgba(37,99,235,.12)}'
            . "\n" . '.tl-item.is-active .tl-dot{animation:pulseRing 1.5s infinite;--pulse-c:var(--tl-c,rgba(37,99,235,.3))}'
            . "\n" . '.tl-card{padding:16px 20px;border-radius:14px;background:rgba(255,255,255,.55);backdrop-filter:blur(10px);border:1px solid var(--border);transition:all .3s}'
            . "\n" . '[data-theme="dark"] .tl-card{background:rgba(255,255,255,.03)}'
            . "\n" . '.tl-item.is-active .tl-card{border-color:var(--tl-c,var(--blue));box-shadow:0 4px 20px rgba(37,99,235,.08)}'
            . "\n" . '.tl-step{font-family:var(--fh);font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:3px}'
            . "\n" . '.tl-title{font-family:var(--fh);font-size:15px;font-weight:700;color:var(--dark);margin-bottom:2px}'
            . "\n" . '.tl-summary{font-size:13px;color:var(--muted);line-height:1.5;margin:0}'
            . "\n" . '.tl-expand{display:grid;grid-template-rows:0fr;transition:grid-template-rows .4s ease;overflow:hidden}'
            . "\n" . '.tl-item.is-active .tl-expand{grid-template-rows:1fr}'
            . "\n" . '.tl-expand>div{overflow:hidden}'
            . "\n" . '.tl-detail{padding-top:10px;font-size:13px;color:var(--slate);line-height:1.6;border-top:1px solid var(--border);margin-top:10px}'
            . "\n" . '.tl-meta{font-size:11px;color:var(--muted);font-family:var(--fh);font-weight:600;margin-top:6px;opacity:.6}'

            /* ── HEATMAP ── */
            . "\n" . '.hm-wrap{margin-top:12px}'
            . "\n" . '.hm-legend{display:flex;align-items:center;gap:8px;margin-bottom:14px;font-size:11px;color:var(--muted);font-family:var(--fh);font-weight:600}'
            . "\n" . '.hm-legend-bar{width:120px;height:10px;border-radius:6px;background:linear-gradient(90deg,rgba(37,99,235,.06),#93C5FD,#3B82F6,#1D4ED8,#1E3A8A)}'
            . "\n" . '[data-theme="dark"] .hm-legend-bar{background:linear-gradient(90deg,rgba(255,255,255,.04),#60A5FA,#3B82F6,#2563EB,#7C3AED)}'
            . "\n" . '.hm-grid{display:grid;gap:3px}'
            . "\n" . '.hm-row-label{font-family:var(--fh);font-size:11px;font-weight:600;color:var(--muted);display:flex;align-items:center;padding-right:6px;justify-content:flex-end}'
            . "\n" . '.hm-col-label{font-family:var(--fh);font-size:10px;font-weight:600;color:var(--muted);text-align:center;padding-bottom:4px}'
            . "\n" . '.hm-cell{aspect-ratio:1;border-radius:4px;transition:all .3s ease;cursor:pointer;position:relative;min-width:0}'
            . "\n" . '.hm-cell:hover{transform:scale(1.3);z-index:10;box-shadow:0 4px 16px rgba(0,0,0,.2);border-radius:6px}'
            . "\n" . '.hm-cell.is-active{outline:2px solid var(--blue);outline-offset:1px;transform:scale(1.3);z-index:10;border-radius:6px}'
            . "\n" . '[data-heatmap].has-active .hm-cell:not(.is-active):not(.is-row-hl):not(.is-col-hl){opacity:.3}'
            . "\n" . '.hm-cell.is-row-hl,.hm-cell.is-col-hl{opacity:.7}'
            . "\n" . '.hm-info{display:grid;grid-template-rows:0fr;transition:grid-template-rows .35s ease,opacity .3s;opacity:0;margin-top:14px}'
            . "\n" . '.hm-info.is-open{grid-template-rows:1fr;opacity:1}'
            . "\n" . '.hm-info>div{overflow:hidden}'
            . "\n" . '.hm-info-inner{padding:14px 18px;border-radius:12px;background:rgba(255,255,255,.45);border:1px solid var(--border);backdrop-filter:blur(6px);display:flex;align-items:center;gap:16px;flex-wrap:wrap}'
            . "\n" . '[data-theme="dark"] .hm-info-inner{background:rgba(255,255,255,.03)}'
            . "\n" . '.hm-info-swatch{width:36px;height:36px;border-radius:8px;flex-shrink:0}'
            . "\n" . '.hm-info-text{font-size:13px;color:var(--slate)}'
            . "\n" . '.hm-info-text b{font-family:var(--fh);color:var(--dark);font-size:18px;display:block;margin-bottom:2px}'

            /* ── FUNNEL ── */
            . "\n" . '.fn-wrap{display:flex;flex-direction:column;gap:4px}'
            . "\n" . '.fn-stage{cursor:pointer;transition:all .3s}'
            . "\n" . '.fn-stage:hover .fn-bar-track{box-shadow:0 0 0 2px rgba(37,99,235,.15)}'
            . "\n" . '.fn-stage.is-active .fn-bar-track{box-shadow:0 0 0 2px var(--fn-c,var(--blue))}'
            . "\n" . '[data-funnel].has-active .fn-stage:not(.is-active){opacity:.35}'
            . "\n" . '.fn-row{display:grid;grid-template-columns:120px 1fr 54px;gap:12px;align-items:center}'
            . "\n" . '.fn-label{font-family:var(--fh);font-size:13px;font-weight:600;color:var(--dark);text-align:right;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}'
            . "\n" . '.fn-bar-track{height:38px;border-radius:12px;background:var(--border);overflow:hidden;position:relative;transition:box-shadow .3s}'
            . "\n" . '.fn-bar-fill{height:100%;border-radius:12px;width:0;transition:width 1.4s cubic-bezier(.4,0,.2,1);position:relative;display:flex;align-items:center;justify-content:flex-end;padding-right:12px;overflow:hidden}'
            . "\n" . '.fn-bar-fill::after{content:"";position:absolute;top:0;left:0;right:0;bottom:0;background:linear-gradient(90deg,rgba(255,255,255,0) 0%,rgba(255,255,255,.2) 50%,rgba(255,255,255,0) 100%);animation:shimmer 3s ease-in-out infinite}'
            . "\n" . '.fn-inner-val{font-family:var(--fh);font-size:12px;font-weight:700;color:rgba(255,255,255,.9);position:relative;z-index:1;text-shadow:0 1px 3px rgba(0,0,0,.2)}'
            . "\n" . '.fn-pct{font-family:var(--fh);font-size:13px;font-weight:900;color:var(--dark);text-align:left}'
            . "\n" . '.fn-conn{display:grid;grid-template-columns:120px 1fr 54px;gap:12px;align-items:center;height:16px}'
            . "\n" . '.fn-conn-line{display:flex;align-items:center;justify-content:center}'
            . "\n" . '.fn-conn-arrow{width:0;height:0;border-left:5px solid transparent;border-right:5px solid transparent;border-top:7px solid var(--muted);opacity:.2}'
            . "\n" . '.fn-drop{font-size:10px;font-family:var(--fh);font-weight:700;color:var(--red);text-align:center;opacity:.5}'
            . "\n" . '.fn-detail{display:grid;grid-template-rows:0fr;transition:grid-template-rows .4s ease,opacity .3s;opacity:0;margin-top:12px}'
            . "\n" . '.fn-detail.is-open{grid-template-rows:1fr;opacity:1}'
            . "\n" . '.fn-detail>div{overflow:hidden}'
            . "\n" . '.fn-detail-inner{padding:16px 18px;border-radius:14px;background:rgba(255,255,255,.45);border:1px solid var(--border);backdrop-filter:blur(6px)}'
            . "\n" . '[data-theme="dark"] .fn-detail-inner{background:rgba(255,255,255,.03)}'
            . "\n" . '.fn-detail-head{display:flex;align-items:center;gap:8px;margin-bottom:6px}'
            . "\n" . '.fn-detail-dot{width:10px;height:10px;border-radius:3px;flex-shrink:0}'
            . "\n" . '.fn-detail-name{font-family:var(--fh);font-size:15px;font-weight:700;color:var(--dark)}'
            . "\n" . '.fn-detail-big{font-family:var(--fh);font-size:28px;font-weight:900;color:var(--dark);margin-left:auto;letter-spacing:-1px}'
            . "\n" . '.fn-detail-desc{font-size:13px;color:var(--muted);line-height:1.55}'

            /* ── SPARK METRICS ── */
            . "\n" . '.sp-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px}'
            . "\n" . '.sp-card{padding:20px 18px 16px;border-radius:16px;background:rgba(255,255,255,.55);backdrop-filter:blur(12px);border:1px solid var(--border);transition:all .35s;cursor:pointer;overflow:hidden}'
            . "\n" . '[data-theme="dark"] .sp-card{background:rgba(255,255,255,.03)}'
            . "\n" . '.sp-card:hover,.sp-card.is-active{transform:translateY(-3px);box-shadow:0 10px 36px rgba(37,99,235,.08);border-color:rgba(37,99,235,.18)}'
            . "\n" . '.sp-card.is-active{border-color:var(--sp-c,var(--blue));box-shadow:0 0 0 2px var(--sp-c,var(--blue)),0 10px 36px rgba(37,99,235,.1)}'
            . "\n" . '[data-sparks].has-active .sp-card:not(.is-active){opacity:.4;transform:scale(.97)}'
            . "\n" . '.sp-head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px}'
            . "\n" . '.sp-icon{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}'
            . "\n" . '.sp-trend{display:inline-flex;align-items:center;gap:2px;padding:3px 9px;border-radius:100px;font-family:var(--fh);font-size:10.5px;font-weight:700}'
            . "\n" . '.sp-trend--up{background:#DCFCE7;color:#16A34A}[data-theme="dark"] .sp-trend--up{background:rgba(22,163,74,.15);color:#4ADE80}'
            . "\n" . '.sp-trend--down{background:#FEE2E2;color:#EF4444}[data-theme="dark"] .sp-trend--down{background:rgba(239,68,68,.15);color:#FCA5A5}'
            . "\n" . '.sp-val{font-family:var(--fh);font-size:1.6rem;font-weight:900;color:var(--dark);letter-spacing:-1px;line-height:1}'
            . "\n" . '.sp-val small{font-size:.45em;font-weight:500;color:var(--muted);margin-left:2px}'
            . "\n" . '.sp-name{font-size:12.5px;color:var(--muted);margin-top:2px;font-weight:500}'
            . "\n" . '.sp-chart{margin-top:10px;height:40px}'
            . "\n" . '.sp-svg{width:100%;height:100%;display:block}'
            . "\n" . '.sp-line{fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:var(--sp-len,300);stroke-dashoffset:var(--sp-len,300);transition:stroke-dashoffset 2s ease}'
            . "\n" . '.sp-line.is-drawn{stroke-dashoffset:0}'
            . "\n" . '.sp-area{opacity:.12}'
            . "\n" . '.sp-dot{fill:var(--bg);stroke-width:2;filter:drop-shadow(0 1px 3px rgba(0,0,0,.15))}'
            . "\n" . '.sp-dot-pulse{opacity:.3;animation:spPulse 2s ease-in-out infinite}'
            . "\n" . '.sp-detail{display:grid;grid-template-rows:0fr;transition:grid-template-rows .4s ease,opacity .3s;opacity:0;margin-top:14px}'
            . "\n" . '.sp-detail.is-open{grid-template-rows:1fr;opacity:1}'
            . "\n" . '.sp-detail>div{overflow:hidden}'
            . "\n" . '.sp-detail-inner{padding:14px 18px;border-radius:12px;background:rgba(255,255,255,.45);border:1px solid var(--border);backdrop-filter:blur(6px)}'
            . "\n" . '[data-theme="dark"] .sp-detail-inner{background:rgba(255,255,255,.03)}'
            . "\n" . '.sp-detail-pair{display:flex;justify-content:space-between;align-items:center;padding:4px 0;font-size:13px;color:var(--slate)}'
            . "\n" . '.sp-detail-pair+.sp-detail-pair{border-top:1px solid var(--border)}'
            . "\n" . '.sp-detail-pair span:last-child{font-family:var(--fh);font-weight:700;color:var(--dark)}'

            /* ── RADAR CHART ── */
            . "\n" . '.radar-layout{display:grid;grid-template-columns:260px 1fr;gap:28px;align-items:start}'
            . "\n" . '.radar-svg-wrap{position:relative;width:260px;height:260px}'
            . "\n" . '.radar-svg{width:100%;height:100%}'
            . "\n" . '.radar-grid-line{fill:none;stroke:var(--border);stroke-width:1}'
            . "\n" . '.radar-axis{stroke:var(--border);stroke-width:1}'
            . "\n" . '.radar-shape{transition:d .6s cubic-bezier(.4,0,.2,1),opacity .3s;cursor:pointer}'
            . "\n" . '.radar-axis-label{font-family:var(--fh);font-size:11px;font-weight:600;fill:var(--muted);cursor:pointer;transition:fill .3s}'
            . "\n" . '.radar-axis-label:hover,.radar-axis-label.is-active{fill:var(--dark);font-weight:700}'
            . "\n" . '.radar-dot{transition:r .3s,opacity .3s;cursor:pointer}'
            . "\n" . '.radar-aside{display:flex;flex-direction:column;gap:8px}'
            . "\n" . '.radar-legend-item{display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:10px;background:rgba(255,255,255,.5);border:1px solid var(--border);transition:all .25s;cursor:pointer;font-size:13px;color:var(--slate)}'
            . "\n" . '[data-theme="dark"] .radar-legend-item{background:rgba(255,255,255,.04)}'
            . "\n" . '.radar-legend-item:hover{border-color:rgba(37,99,235,.3)}'
            . "\n" . '.radar-legend-item.is-active{border-color:var(--blue);box-shadow:0 2px 12px rgba(37,99,235,.12);background:var(--blue-light)}'
            . "\n" . '[data-radar].has-active .radar-legend-item:not(.is-active){opacity:.35}'
            . "\n" . '.radar-legend-dot{width:10px;height:10px;border-radius:3px;flex-shrink:0}'
            . "\n" . '.radar-legend-val{font-family:var(--fh);font-weight:700;color:var(--dark);margin-left:auto}'
            . "\n" . '.radar-detail{display:grid;grid-template-rows:0fr;transition:grid-template-rows .4s ease,opacity .3s;opacity:0;margin-top:10px}'
            . "\n" . '.radar-detail.is-open{grid-template-rows:1fr;opacity:1}'
            . "\n" . '.radar-detail>div{overflow:hidden}'
            . "\n" . '.radar-detail-inner{padding:12px 16px;border-radius:12px;background:rgba(255,255,255,.45);border:1px solid var(--border);font-size:13px;color:var(--muted);line-height:1.55}'
            . "\n" . '[data-theme="dark"] .radar-detail-inner{background:rgba(255,255,255,.03)}'

            /* ── BEFORE/AFTER ── */
            . "\n" . '.ba-container{max-width:680px;margin:0 auto}'
            . "\n" . '.ba-cards{display:grid;grid-template-columns:1fr 1fr;gap:0;border-radius:16px;overflow:hidden;border:1px solid var(--border);background:rgba(255,255,255,.5)}'
            . "\n" . '[data-theme="dark"] .ba-cards{background:rgba(255,255,255,.03)}'
            . "\n" . '.ba-side{padding:28px 24px}'
            . "\n" . '.ba-side--before{border-right:1px solid var(--border)}'
            . "\n" . '.ba-tag{font-family:var(--fh);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:2px;padding:4px 12px;border-radius:100px;display:inline-block;margin-bottom:14px}'
            . "\n" . '.ba-tag--before{background:#FEE2E2;color:#991B1B}'
            . "\n" . '.ba-tag--after{background:#DCFCE7;color:#16A34A}'
            . "\n" . '[data-theme="dark"] .ba-tag--before{background:rgba(239,68,68,.15);color:#FCA5A5}'
            . "\n" . '[data-theme="dark"] .ba-tag--after{background:rgba(22,163,74,.15);color:#4ADE80}'
            . "\n" . '.ba-metric{margin-bottom:12px}'
            . "\n" . '.ba-metric-val{font-family:var(--fh);font-size:1.8rem;font-weight:900;color:var(--dark);letter-spacing:-1px;line-height:1}'
            . "\n" . '.ba-metric-name{font-size:12px;color:var(--muted);font-weight:500;margin-top:2px}'
            . "\n" . '.ba-bar-track{height:8px;border-radius:100px;background:var(--border);overflow:hidden}'
            . "\n" . '.ba-bar-fill{height:100%;border-radius:100px;transition:width 1.2s cubic-bezier(.4,0,.2,1)}'
            . "\n" . '.ba-slider-row{display:flex;align-items:center;gap:12px;margin-top:20px}'
            . "\n" . '.ba-slider-label{font-family:var(--fh);font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px;white-space:nowrap}'
            . "\n" . '.ba-slider{flex:1;-webkit-appearance:none;appearance:none;height:6px;border-radius:100px;background:linear-gradient(90deg,var(--red),var(--warn),var(--green));outline:none;cursor:pointer}'
            . "\n" . '.ba-slider::-webkit-slider-thumb{-webkit-appearance:none;width:22px;height:22px;border-radius:50%;background:var(--white);border:3px solid var(--blue);box-shadow:0 2px 10px rgba(0,0,0,.15);cursor:pointer}'
            . "\n" . '.ba-delta{text-align:center;margin-top:16px;padding:12px;border-radius:12px;background:rgba(255,255,255,.4);border:1px solid var(--border)}'
            . "\n" . '[data-theme="dark"] .ba-delta{background:rgba(255,255,255,.03)}'
            . "\n" . '.ba-delta-val{font-family:var(--fh);font-size:1.3rem;font-weight:900;letter-spacing:-1px}'
            . "\n" . '.ba-delta-label{font-size:11px;color:var(--muted)}'

            /* ── STACKED AREA ── */
            . "\n" . '.sa-layout{display:grid;grid-template-columns:1fr 200px;gap:24px;align-items:start}'
            . "\n" . '.sa-chart{position:relative;border-radius:14px;overflow:hidden;background:rgba(255,255,255,.3);border:1px solid var(--border);padding:16px}'
            . "\n" . '[data-theme="dark"] .sa-chart{background:rgba(255,255,255,.02)}'
            . "\n" . '.sa-svg{width:100%;height:200px;display:block}'
            . "\n" . '.sa-area{transition:opacity .4s ease;cursor:pointer}'
            . "\n" . '.sa-area:hover{filter:brightness(1.1)}'
            . "\n" . '[data-stacked].has-active .sa-area{opacity:.15}'
            . "\n" . '[data-stacked].has-active .sa-area.is-active{opacity:1;filter:drop-shadow(0 0 6px rgba(0,0,0,.15))}'
            . "\n" . '.sa-x-labels{display:flex;justify-content:space-between;padding:6px 16px 0;font-family:var(--fh);font-size:10px;color:var(--muted);font-weight:600}'
            . "\n" . '.sa-legend{display:flex;flex-direction:column;gap:6px}'
            . "\n" . '.sa-legend-item{display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:10px;background:rgba(255,255,255,.5);border:1px solid var(--border);transition:all .25s;cursor:pointer;font-size:13px;color:var(--slate)}'
            . "\n" . '[data-theme="dark"] .sa-legend-item{background:rgba(255,255,255,.04)}'
            . "\n" . '.sa-legend-item.is-active{border-color:var(--blue);box-shadow:0 2px 10px rgba(37,99,235,.1);background:var(--blue-light)}'
            . "\n" . '[data-stacked].has-active .sa-legend-item:not(.is-active){opacity:.35}'
            . "\n" . '.sa-legend-dot{width:10px;height:10px;border-radius:3px;flex-shrink:0}'
            . "\n" . '.sa-legend-val{font-family:var(--fh);font-weight:700;color:var(--dark);margin-left:auto;font-size:12px}'
            . "\n" . '.sa-detail{display:grid;grid-template-rows:0fr;transition:grid-template-rows .4s ease,opacity .3s;opacity:0;margin-top:12px}'
            . "\n" . '.sa-detail.is-open{grid-template-rows:1fr;opacity:1}'
            . "\n" . '.sa-detail>div{overflow:hidden}'
            . "\n" . '.sa-detail-inner{padding:12px 16px;border-radius:12px;background:rgba(255,255,255,.45);border:1px solid var(--border);font-size:13px;color:var(--muted);line-height:1.55}'
            . "\n" . '[data-theme="dark"] .sa-detail-inner{background:rgba(255,255,255,.03)}'
            . "\n" . '.sa-detail-inner b{color:var(--dark);font-family:var(--fh)}'

            /* ── SCORE RINGS ── */
            . "\n" . '.ring-layout{display:grid;grid-template-columns:220px 1fr;gap:32px;align-items:center}'
            . "\n" . '.ring-svg-wrap{position:relative;width:220px;height:220px}'
            . "\n" . '.ring-svg{width:100%;height:100%}'
            . "\n" . '.ring-track{fill:none;stroke-width:16;stroke-linecap:round;opacity:.12}'
            . "\n" . '.ring-fill{fill:none;stroke-width:16;stroke-linecap:round;transition:stroke-dashoffset 1.8s cubic-bezier(.4,0,.2,1);filter:drop-shadow(0 0 8px var(--ring-glow,rgba(37,99,235,.3)))}'
            . "\n" . '.ring-center{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;pointer-events:none}'
            . "\n" . '.ring-center-val{font-family:var(--fh);font-size:2.2rem;font-weight:900;color:var(--dark);letter-spacing:-1px;line-height:1;transition:all .3s}'
            . "\n" . '.ring-center-label{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:1.5px;margin-top:4px;display:block}'
            . "\n" . '.ring-aside{display:flex;flex-direction:column;gap:8px}'
            . "\n" . '.ring-item{display:flex;align-items:center;gap:10px;padding:14px 16px;border-radius:12px;background:rgba(255,255,255,.5);border:1px solid var(--border);transition:all .3s;cursor:pointer}'
            . "\n" . '[data-theme="dark"] .ring-item{background:rgba(255,255,255,.04)}'
            . "\n" . '.ring-item:hover{border-color:rgba(37,99,235,.25)}'
            . "\n" . '.ring-item.is-active{border-color:var(--ring-c,var(--blue));box-shadow:0 0 0 2px var(--ring-c,var(--blue)),0 4px 16px rgba(37,99,235,.08)}'
            . "\n" . '[data-rings].has-active .ring-item:not(.is-active){opacity:.35}'
            . "\n" . '.ring-item-dot{width:14px;height:14px;border-radius:50%;flex-shrink:0}'
            . "\n" . '.ring-item-info{flex:1;min-width:0}'
            . "\n" . '.ring-item-name{font-family:var(--fh);font-size:14px;font-weight:700;color:var(--dark)}'
            . "\n" . '.ring-item-sub{font-size:12px;color:var(--muted)}'
            . "\n" . '.ring-item-val{font-family:var(--fh);font-size:18px;font-weight:900;color:var(--dark);letter-spacing:-1px}'
            . "\n" . '.ring-item-pct{font-size:10px;color:var(--muted)}'
            . "\n" . '.ring-detail{display:grid;grid-template-rows:0fr;transition:grid-template-rows .4s ease,opacity .3s;opacity:0;margin-top:10px}'
            . "\n" . '.ring-detail.is-open{grid-template-rows:1fr;opacity:1}'
            . "\n" . '.ring-detail>div{overflow:hidden}'
            . "\n" . '.ring-detail-inner{padding:12px 16px;border-radius:12px;background:rgba(255,255,255,.45);border:1px solid var(--border);font-size:13px;color:var(--muted);line-height:1.55}'
            . "\n" . '[data-theme="dark"] .ring-detail-inner{background:rgba(255,255,255,.03)}'

            /* ── RANGE COMPARISON ── */
            . "\n" . '.rc-wrap{display:flex;flex-direction:column;gap:8px}'
            . "\n" . '.rc-row{display:grid;grid-template-columns:100px 1fr;gap:14px;align-items:center;padding:12px 16px;border-radius:12px;background:rgba(255,255,255,.4);border:1px solid var(--border);cursor:pointer;transition:all .3s}'
            . "\n" . '[data-theme="dark"] .rc-row{background:rgba(255,255,255,.02)}'
            . "\n" . '.rc-row:hover{border-color:rgba(37,99,235,.2)}'
            . "\n" . '.rc-row.is-active{border-color:var(--blue);box-shadow:0 0 0 2px var(--blue),0 4px 20px rgba(37,99,235,.08)}'
            . "\n" . '[data-ranges].has-active .rc-row:not(.is-active){opacity:.35}'
            . "\n" . '.rc-label{font-family:var(--fh);font-size:13px;font-weight:700;color:var(--dark)}'
            . "\n" . '.rc-bars{display:flex;flex-direction:column;gap:6px}'
            . "\n" . '.rc-bar-row{display:flex;align-items:center;gap:8px}'
            . "\n" . '.rc-bar-name{font-size:10px;font-family:var(--fh);font-weight:600;color:var(--muted);width:30px;text-align:right;flex-shrink:0}'
            . "\n" . '.rc-bar-track{flex:1;height:12px;border-radius:100px;background:var(--border);position:relative;overflow:hidden}'
            . "\n" . '.rc-bar-range{position:absolute;top:0;bottom:0;border-radius:100px;transition:left .8s ease,width .8s ease}'
            . "\n" . '.rc-bar-marker{position:absolute;top:-3px;bottom:-3px;width:3px;border-radius:2px;background:var(--dark);transition:left .8s ease;z-index:2}'
            . "\n" . '.rc-bar-val{font-size:10px;font-family:var(--fh);font-weight:700;color:var(--dark);width:38px;flex-shrink:0}'
            . "\n" . '.rc-toggle{display:flex;gap:4px;margin-bottom:14px}'
            . "\n" . '.rc-toggle-btn{padding:8px 16px;border:1px solid var(--border);background:transparent;border-radius:10px;font-family:var(--fh);font-size:12px;font-weight:600;color:var(--muted);cursor:pointer;transition:all .25s}'
            . "\n" . '.rc-toggle-btn:hover{color:var(--dark);background:rgba(37,99,235,.06)}'
            . "\n" . '.rc-toggle-btn.is-active{color:#fff;background:var(--blue);border-color:var(--blue)}'
            . "\n" . '.rc-detail{display:grid;grid-template-rows:0fr;transition:grid-template-rows .4s ease,opacity .3s;opacity:0;margin-top:10px}'
            . "\n" . '.rc-detail.is-open{grid-template-rows:1fr;opacity:1}'
            . "\n" . '.rc-detail>div{overflow:hidden}'
            . "\n" . '.rc-detail-inner{padding:14px 18px;border-radius:12px;background:rgba(255,255,255,.45);border:1px solid var(--border);font-size:13px;color:var(--muted);line-height:1.55}'
            . "\n" . '[data-theme="dark"] .rc-detail-inner{background:rgba(255,255,255,.03)}'

            /* ── NEW BLOCKS RESPONSIVE ── */
            . "\n" . '@media(max-width:700px){'
            .   '.radar-layout,.sa-layout,.ring-layout{grid-template-columns:1fr}'
            .   '.radar-svg-wrap,.ring-svg-wrap{margin:0 auto}'
            .   '.ba-cards{grid-template-columns:1fr}'
            .   '.ba-side--before{border-right:none;border-bottom:1px solid var(--border)}'
            .   '.fn-row,.fn-conn{grid-template-columns:80px 1fr 44px;gap:8px}'
            .   '.rc-row{grid-template-columns:80px 1fr}'
            .   '.gauge-grid{grid-template-columns:repeat(2,1fr)}'
            .   '.sp-grid{grid-template-columns:1fr 1fr}'
            .   '.tl-wrap{padding-left:32px}'
            . '}'

            /* ══════ NEW INTENT BLOCKS CSS ══════ */

            /* ── VALUE CHECKER ── */
            . "\n" . '.vc-wrap{max-width:520px;margin:0 auto}'
            . "\n" . '.vc-input-row{display:flex;gap:10px;margin-bottom:20px}'
            . "\n" . '.vc-input{flex:1;padding:14px 18px;border:2px solid var(--border);border-radius:12px;font-size:18px;font-family:var(--fh);font-weight:700;color:var(--dark);background:var(--white);transition:border-color .3s;outline:none}'
            . "\n" . '.vc-input:focus{border-color:var(--blue)}'
            . "\n" . '[data-theme="dark"] .vc-input{background:rgba(255,255,255,.05);color:var(--dark)}'
            . "\n" . '.vc-btn{padding:14px 28px;border:none;border-radius:12px;background:var(--blue);color:#fff;font-family:var(--fh);font-size:15px;font-weight:700;cursor:pointer;transition:all .2s;white-space:nowrap}'
            . "\n" . '.vc-btn:hover{background:var(--blue-dark);transform:translateY(-1px);box-shadow:0 4px 16px rgba(37,99,235,.25)}'
            . "\n" . '.vc-scale{position:relative;margin:24px 0}'
            . "\n" . '.vc-scale-track{display:flex;height:12px;border-radius:100px;overflow:hidden;gap:2px}'
            . "\n" . '.vc-zone{flex:1;transition:opacity .4s}'
            . "\n" . '.vc-marker{position:absolute;top:-6px;transition:left .6s cubic-bezier(.4,0,.2,1);z-index:2}'
            . "\n" . '.vc-marker-dot{width:24px;height:24px;border-radius:50%;background:var(--dark);border:3px solid var(--bg);box-shadow:0 2px 8px rgba(0,0,0,.2);margin-left:-12px}'
            . "\n" . '.vc-marker-line{width:2px;height:18px;background:var(--dark);margin:2px auto 0;opacity:.4}'
            . "\n" . '.vc-result{padding:20px;border-radius:16px;background:rgba(255,255,255,.5);backdrop-filter:blur(8px);border:1px solid var(--border);text-align:center;animation:fadeInUp .5s ease}'
            . "\n" . '[data-theme="dark"] .vc-result{background:rgba(255,255,255,.03)}'
            . "\n" . '.vc-result-icon{font-size:2.5rem;margin-bottom:6px}'
            . "\n" . '.vc-result-label{font-family:var(--fh);font-size:1.3rem;font-weight:900;margin-bottom:4px}'
            . "\n" . '.vc-result-text{font-size:14px;color:var(--slate);line-height:1.6}'
            . "\n" . '.vc-disclaimer{font-size:11.5px;color:var(--muted);text-align:center;margin-top:16px;font-style:italic}'
            . "\n" . '@keyframes fadeInUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}'

            /* ── SYMPTOM CHECKLIST ── */
            . "\n" . '.sc-wrap{max-width:560px;margin:0 auto}'
            . "\n" . '.sc-progress{margin-bottom:20px}'
            . "\n" . '.sc-progress-text{font-size:13px;color:var(--muted);font-weight:600;margin-bottom:6px}'
            . "\n" . '.sc-count{color:var(--blue);font-family:var(--fh);font-weight:900}'
            . "\n" . '.sc-bar{transition:width .5s ease}'
            . "\n" . '.sc-group{margin-bottom:16px}'
            . "\n" . '.sc-group-label{font-family:var(--fh);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:var(--muted);margin-bottom:8px}'
            . "\n" . '.sc-item{display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:12px;cursor:pointer;transition:all .2s;border:1px solid transparent;margin-bottom:4px}'
            . "\n" . '.sc-item:hover{background:var(--blue-light);border-color:rgba(37,99,235,.12)}'
            . "\n" . '.sc-check{display:none}'
            . "\n" . '.sc-checkmark{width:22px;height:22px;border-radius:7px;border:2px solid var(--border);flex-shrink:0;display:flex;align-items:center;justify-content:center;transition:all .25s;font-size:12px;color:transparent}'
            . "\n" . '.sc-check:checked~.sc-checkmark{background:var(--blue);border-color:var(--blue);color:#fff}'
            . "\n" . '.sc-check:checked~.sc-checkmark::after{content:"✓"}'
            . "\n" . '.sc-text{font-size:14px;color:var(--slate);line-height:1.4}'
            . "\n" . '.sc-badge-important{background:var(--red);color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:100px}'
            . "\n" . '.sc-result{padding:20px;border-radius:16px;text-align:center;animation:fadeInUp .5s ease;margin-top:20px}'
            . "\n" . '.sc-result-icon{font-size:2.5rem;margin-bottom:6px}'
            . "\n" . '.sc-result-label{font-family:var(--fh);font-size:1.3rem;font-weight:900;margin-bottom:4px}'
            . "\n" . '.sc-result-text{font-size:14px;color:var(--slate);line-height:1.6}'
            . "\n" . '.sc-cta{text-align:center;margin-top:16px}'

            /* ── PREP CHECKLIST ── */
            . "\n" . '.pc-wrap{max-width:560px;margin:0 auto}'
            . "\n" . '.pc-progress-row{display:flex;align-items:center;gap:12px;margin-bottom:20px}'
            . "\n" . '.pc-progress-text{font-size:13px;color:var(--muted);font-weight:500;white-space:nowrap}'
            . "\n" . '.pc-progress-text b{color:var(--dark);font-family:var(--fh)}'
            . "\n" . '.pc-bar{transition:width .5s ease}'
            . "\n" . '.pc-section{margin-bottom:20px}'
            . "\n" . '.pc-section-header{display:flex;align-items:center;gap:10px;padding:10px 0;font-family:var(--fh);font-size:14px;font-weight:700;color:var(--dark)}'
            . "\n" . '.pc-section-icon{font-size:1.3em}'
            . "\n" . '.pc-item{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:10px;cursor:pointer;transition:all .2s;margin-bottom:3px}'
            . "\n" . '.pc-item:hover{background:var(--blue-light)}'
            . "\n" . '.pc-item--important{border-left:3px solid var(--warn)}'
            . "\n" . '.pc-check{display:none}'
            . "\n" . '.pc-checkmark{width:20px;height:20px;border-radius:6px;border:2px solid var(--border);flex-shrink:0;display:flex;align-items:center;justify-content:center;transition:all .25s;font-size:11px;color:transparent}'
            . "\n" . '.pc-check:checked~.pc-checkmark{background:var(--green);border-color:var(--green);color:#fff;transform:scale(1.15)}'
            . "\n" . '.pc-check:checked~.pc-checkmark::after{content:"✓"}'
            . "\n" . '.pc-check:checked~.pc-text{text-decoration:line-through;opacity:.5}'
            . "\n" . '.pc-text{font-size:14px;color:var(--slate);line-height:1.4}'
            . "\n" . '.pc-confetti{text-align:center;font-size:1.5rem;padding:16px;border-radius:14px;background:var(--green-light);color:var(--green);font-family:var(--fh);font-weight:700}'
            . "\n" . '.pc-milestone{text-align:center;font-size:14px;padding:10px 16px;border-radius:10px;background:var(--blue-light);color:var(--blue);font-family:var(--fh);font-weight:600;margin-top:12px}'

            /* ── INFO CARDS ── */
            . "\n" . '.ic-grid{display:grid;gap:16px}'
            . "\n" . '.ic-grid--3{grid-template-columns:repeat(3,1fr)}'
            . "\n" . '.ic-grid--2{grid-template-columns:repeat(2,1fr)}'
            . "\n" . '.ic-card{padding:24px 20px;border-radius:16px;background:rgba(255,255,255,.6);backdrop-filter:blur(10px);border:1px solid var(--border);border-left:4px solid var(--ic-c,var(--blue));transition:all .3s;cursor:default}'
            . "\n" . '[data-theme="dark"] .ic-card{background:rgba(255,255,255,.03)}'
            . "\n" . '.ic-card:hover{transform:translateY(-4px);box-shadow:0 12px 40px rgba(15,23,42,.08);border-color:rgba(37,99,235,.15)}'
            . "\n" . '.ic-icon{font-size:1.8rem;margin-bottom:10px}'
            . "\n" . '.ic-title{font-family:var(--fh);font-size:15px;font-weight:700;color:var(--dark);margin-bottom:6px}'
            . "\n" . '.ic-text{font-size:13px;color:var(--slate);line-height:1.55}'
            . "\n" . '@media(max-width:700px){.ic-grid--3{grid-template-columns:1fr 1fr}.ic-grid--2{grid-template-columns:1fr}}'

            /* ── STORY BLOCK ── */
            . "\n" . '.sb-card{border-radius:20px;background:rgba(255,255,255,.6);backdrop-filter:blur(12px);border:1px solid var(--border);overflow:hidden;display:flex}'
            . "\n" . '[data-theme="dark"] .sb-card{background:rgba(255,255,255,.03)}'
            . "\n" . '.sb-accent{width:6px;flex-shrink:0;background:var(--sb-c,var(--purple))}'
            . "\n" . '.sb-body{padding:28px 32px;flex:1}'
            . "\n" . '.sb-icon{font-size:1.5rem;margin-bottom:8px}'
            . "\n" . '.sb-lead{font-family:var(--fh);font-size:13px;font-weight:700;color:var(--sb-c,var(--purple));text-transform:uppercase;letter-spacing:1px;margin-bottom:10px}'
            . "\n" . '.sb-text{font-size:15px;color:var(--slate);line-height:1.7;font-style:italic}'
            . "\n" . '.sb-highlight{margin-top:14px;padding:12px 16px;border-radius:10px;background:var(--blue-light);font-family:var(--fh);font-size:14px;font-weight:700;color:var(--dark)}'
            . "\n" . '.sb-footnote{margin-top:12px;font-size:11px;color:var(--muted);font-style:italic}'

            /* ── VERDICT CARD ── */
            . "\n" . '.vd-grid{display:grid;gap:16px}'
            . "\n" . '.vd-card{padding:24px;border-radius:16px;background:rgba(255,255,255,.55);backdrop-filter:blur(10px);border:1px solid var(--border);cursor:pointer;transition:all .3s}'
            . "\n" . '[data-theme="dark"] .vd-card{background:rgba(255,255,255,.03)}'
            . "\n" . '.vd-card:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(15,23,42,.06)}'
            . "\n" . '.vd-stamp{display:inline-block;padding:4px 14px;border-radius:100px;font-family:var(--fh);font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:10px}'
            . "\n" . '.vd-verdict--myth{background:rgba(239,68,68,.12);color:#EF4444}'
            . "\n" . '.vd-verdict--truth{background:rgba(16,185,129,.12);color:#10B981}'
            . "\n" . '.vd-verdict--partial{background:rgba(245,158,11,.12);color:#F59E0B}'
            . "\n" . '.vd-claim{font-family:var(--fh);font-size:16px;font-weight:700;color:var(--dark);line-height:1.4}'
            . "\n" . '.vd-expand{display:grid;grid-template-rows:0fr;transition:grid-template-rows .4s ease;overflow:hidden}'
            . "\n" . '.vd-card.is-open .vd-expand{grid-template-rows:1fr}'
            . "\n" . '.vd-expand>div{overflow:hidden}'
            . "\n" . '.vd-explanation{padding-top:14px;font-size:14px;color:var(--slate);line-height:1.65;border-top:1px solid var(--border);margin-top:14px}'
            . "\n" . '.vd-source{font-size:11.5px;color:var(--muted);margin-top:8px;font-style:italic}'

            /* ── NUMBERED STEPS ── */
            . "\n" . '.ns-track{display:grid;gap:20px}'
            . "\n" . '.ns-step{display:grid;grid-template-columns:56px 1fr;gap:16px;align-items:start}'
            . "\n" . '.ns-num{width:56px;height:56px;border-radius:16px;background:var(--ns-c,var(--blue));color:#fff;font-family:var(--fh);font-size:1.5rem;font-weight:900;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 16px rgba(37,99,235,.2)}'
            . "\n" . '.ns-card{padding:20px 24px;border-radius:16px;background:rgba(255,255,255,.55);backdrop-filter:blur(10px);border:1px solid var(--border);transition:all .3s}'
            . "\n" . '[data-theme="dark"] .ns-card{background:rgba(255,255,255,.03)}'
            . "\n" . '.ns-title{font-family:var(--fh);font-size:16px;font-weight:700;color:var(--dark);margin-bottom:6px}'
            . "\n" . '.ns-text{font-size:14px;color:var(--slate);line-height:1.6}'
            . "\n" . '.ns-tip{margin-top:10px;padding:10px 14px;border-radius:10px;background:var(--blue-light);font-size:13px;color:var(--slate);line-height:1.5}'
            . "\n" . '.ns-duration{margin-top:8px;font-family:var(--fh);font-size:12px;font-weight:600;color:var(--muted)}'

            /* ── WARNING BLOCK ── */
            . "\n" . '.wb-card{border-radius:20px;padding:28px;border:2px solid var(--border)}'
            . "\n" . '.wb--red{border-color:rgba(239,68,68,.3);background:rgba(239,68,68,.04)}'
            . "\n" . '.wb--caution{border-color:rgba(245,158,11,.3);background:rgba(245,158,11,.04)}'
            . "\n" . '.wb--good{border-color:rgba(16,185,129,.3);background:rgba(16,185,129,.04)}'
            . "\n" . '[data-theme="dark"] .wb--red{background:rgba(239,68,68,.06)}'
            . "\n" . '[data-theme="dark"] .wb--caution{background:rgba(245,158,11,.06)}'
            . "\n" . '[data-theme="dark"] .wb--good{background:rgba(16,185,129,.06)}'
            . "\n" . '.wb-header{display:flex;align-items:center;gap:14px;margin-bottom:18px}'
            . "\n" . '.wb-icon{font-size:2rem}'
            . "\n" . '.wb-title{font-family:var(--fh);font-size:18px;font-weight:900;color:var(--dark)}'
            . "\n" . '.wb-subtitle{font-size:13px;color:var(--muted);margin-top:2px}'
            . "\n" . '.wb-items{display:grid;gap:8px}'
            . "\n" . '.wb-item{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:12px;background:rgba(255,255,255,.5);border:1px solid var(--border)}'
            . "\n" . '[data-theme="dark"] .wb-item{background:rgba(255,255,255,.03)}'
            . "\n" . '.wb-item--emergency{border-color:rgba(239,68,68,.3);animation:pulseRing 2s infinite;--pulse-c:rgba(239,68,68,.2)}'
            . "\n" . '.wb-item-icon{font-size:1.2em;flex-shrink:0}'
            . "\n" . '.wb-item-text{font-size:14px;color:var(--slate);line-height:1.4}'
            . "\n" . '.wb-footer{margin-top:16px;padding-top:14px;border-top:1px solid var(--border);font-size:13px;font-weight:700;color:var(--red);text-align:center}'

            /* ── MINI CALCULATOR ── */
            . "\n" . '.mc-wrap{max-width:520px;margin:0 auto}'
            . "\n" . '.mc-inputs{display:grid;gap:14px;margin-bottom:18px}'
            . "\n" . '.mc-field{display:grid;gap:4px}'
            . "\n" . '.mc-label{font-family:var(--fh);font-size:13px;font-weight:600;color:var(--dark)}'
            . "\n" . '.mc-select,.mc-input{padding:12px 16px;border:2px solid var(--border);border-radius:10px;font-size:15px;color:var(--dark);background:var(--white);font-family:var(--fb);transition:border-color .2s;outline:none;width:100%;box-sizing:border-box}'
            . "\n" . '[data-theme="dark"] .mc-select,[data-theme="dark"] .mc-input{background:rgba(255,255,255,.05);color:var(--dark)}'
            . "\n" . '.mc-select:focus,.mc-input:focus{border-color:var(--blue)}'
            . "\n" . '.mc-input-wrap{position:relative}'
            . "\n" . '.mc-unit{position:absolute;right:14px;top:50%;transform:translateY(-50%);font-size:13px;color:var(--muted);font-weight:500}'
            . "\n" . '.mc-btn{width:100%;padding:14px;border:none;border-radius:12px;background:var(--blue);color:#fff;font-family:var(--fh);font-size:15px;font-weight:700;cursor:pointer;transition:all .2s}'
            . "\n" . '.mc-btn:hover{background:var(--blue-dark);transform:translateY(-1px);box-shadow:0 4px 16px rgba(37,99,235,.25)}'
            . "\n" . '.mc-result{padding:20px;border-radius:16px;background:var(--blue-light);text-align:center;margin-top:18px;animation:fadeInUp .5s ease}'
            . "\n" . '.mc-result-value{font-family:var(--fh);font-size:2rem;font-weight:900;color:var(--blue)}'
            . "\n" . '.mc-result-text{font-size:14px;color:var(--slate);margin-top:4px}'
            . "\n" . '.mc-formula-desc{font-size:11.5px;color:var(--muted);text-align:center;margin-top:12px}'
            . "\n" . '.mc-disclaimer{font-size:11.5px;color:var(--muted);text-align:center;margin-top:8px;font-style:italic}'

            /* ── COMPARISON CARDS ── */
            . "\n" . '.cc-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}'
            . "\n" . '@media(max-width:700px){.cc-grid{grid-template-columns:1fr}}'
            . "\n" . '.cc-card{padding:28px 24px;border-radius:20px;background:rgba(255,255,255,.6);backdrop-filter:blur(12px);border:2px solid var(--border);position:relative;transition:all .3s}'
            . "\n" . '[data-theme="dark"] .cc-card{background:rgba(255,255,255,.03)}'
            . "\n" . '.cc-card:hover{transform:translateY(-4px);box-shadow:0 16px 48px rgba(15,23,42,.08);border-color:var(--cc-c,var(--blue))}'
            . "\n" . '.cc-badge{position:absolute;top:-1px;right:20px;padding:4px 14px;border-radius:0 0 10px 10px;font-family:var(--fh);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;background:var(--cc-c,var(--blue));color:#fff}'
            . "\n" . '.cc-name{font-family:var(--fh);font-size:1.3rem;font-weight:900;color:var(--dark);margin-bottom:16px;padding-right:60px}'
            . "\n" . '.cc-list{margin-bottom:14px}'
            . "\n" . '.cc-li{display:flex;align-items:flex-start;gap:8px;padding:6px 0;font-size:14px;color:var(--slate);line-height:1.5}'
            . "\n" . '.cc-li-icon{flex-shrink:0;font-size:14px;font-weight:700;margin-top:2px}'
            . "\n" . '.cc-ok{color:var(--green)}'
            . "\n" . '.cc-no{color:var(--red)}'
            . "\n" . '.cc-price{font-family:var(--fh);font-size:1.1rem;font-weight:700;color:var(--dark);padding:12px 0;border-top:1px solid var(--border)}'
            . "\n" . '.cc-verdict{font-size:13px;color:var(--muted);font-style:italic;line-height:1.5}'

            /* ── PROGRESS TRACKER ── */
            . "\n" . '.pt-wrap{padding:10px 0}'
            . "\n" . '.pt-track{position:relative;height:8px;background:var(--border);border-radius:100px;margin:40px 20px 50px}'
            . "\n" . '.pt-track-fill{position:absolute;top:0;left:0;height:100%;border-radius:100px;background:linear-gradient(90deg,var(--blue),var(--teal),var(--green));width:0;transition:width 1.5s cubic-bezier(.4,0,.2,1)}'
            . "\n" . '.pt-dot{position:absolute;top:50%;transform:translateY(-50%);cursor:pointer;z-index:2}'
            . "\n" . '.pt-dot-inner{width:20px;height:20px;border-radius:50%;background:var(--bg);border:3px solid var(--blue);margin-left:-10px;transition:all .3s;box-shadow:0 2px 8px rgba(37,99,235,.15)}'
            . "\n" . '.pt-dot:hover .pt-dot-inner,.pt-dot.is-active .pt-dot-inner{background:var(--blue);transform:scale(1.3)}'
            . "\n" . '.pt-label{position:absolute;top:28px;left:50%;transform:translateX(-50%);font-family:var(--fh);font-size:11px;font-weight:600;color:var(--muted);white-space:nowrap}'
            . "\n" . '.pt-detail{padding:18px 20px;border-radius:14px;background:rgba(255,255,255,.5);border:1px solid var(--border);backdrop-filter:blur(8px);animation:fadeInUp .4s ease}'
            . "\n" . '[data-theme="dark"] .pt-detail{background:rgba(255,255,255,.03)}'
            . "\n" . '.pt-detail-period{font-family:var(--fh);font-size:14px;font-weight:700;color:var(--blue);margin-bottom:4px}'
            . "\n" . '.pt-detail-text{font-size:14px;color:var(--slate);line-height:1.6}'
            . "\n" . '.pt-detail-metric{margin-top:8px;padding:8px 12px;border-radius:8px;background:var(--blue-light);font-family:var(--fh);font-size:13px;font-weight:600;color:var(--dark)}'
            . "\n" . '.pt-note{font-size:11.5px;color:var(--muted);text-align:center;margin-top:16px;font-style:italic}'

            /* ── KEY TAKEAWAYS ── */
            . "\n" . '.kt-card{border-radius:20px;background:linear-gradient(135deg,rgba(37,99,235,.06),rgba(13,148,136,.04));border:1px solid rgba(37,99,235,.12);padding:28px 32px}'
            . "\n" . '[data-theme="dark"] .kt-card{background:linear-gradient(135deg,rgba(37,99,235,.1),rgba(13,148,136,.06))}'
            . "\n" . '.kt-header{display:flex;align-items:center;gap:10px;margin-bottom:18px}'
            . "\n" . '.kt-icon{font-size:1.5rem}'
            . "\n" . '.kt-title{font-family:var(--fh);font-size:18px;font-weight:900;color:var(--dark)}'
            . "\n" . '.kt-items{display:grid;gap:10px}'
            . "\n" . '.kt-item{display:flex;align-items:flex-start;gap:12px;font-size:14px;color:var(--slate);line-height:1.6}'
            . "\n" . '.kt-num{width:28px;height:28px;border-radius:8px;background:var(--blue);color:#fff;font-family:var(--fh);font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}'
            . "\n" . '.kt-bullet{width:8px;height:8px;border-radius:50%;background:var(--blue);flex-shrink:0;margin-top:7px}'
            . "\n" . '.kt-text{flex:1}'

            /* ── EXPERT PANEL ── */
            . "\n" . '.ep-card{border-radius:20px;background:rgba(255,255,255,.6);backdrop-filter:blur(14px);border:1px solid var(--border);padding:32px 36px;position:relative;overflow:hidden}'
            . "\n" . '[data-theme="dark"] .ep-card{background:rgba(255,255,255,.03)}'
            . "\n" . '.ep-quote-mark{position:absolute;top:16px;right:28px;font-family:Georgia,serif;font-size:6rem;color:rgba(37,99,235,.08);line-height:1;pointer-events:none}'
            . "\n" . '[data-theme="dark"] .ep-quote-mark{color:rgba(96,165,250,.12)}'
            . "\n" . '.ep-text{font-size:16px;color:var(--slate);line-height:1.75;font-style:italic;position:relative;z-index:1}'
            . "\n" . '.ep-highlight{margin-top:16px;padding:14px 18px;border-radius:12px;background:var(--blue-light);font-family:var(--fh);font-size:15px;font-weight:700;color:var(--dark);font-style:normal;border-left:4px solid var(--blue)}'
            . "\n" . '.ep-author{display:flex;align-items:center;gap:14px;margin-top:24px;padding-top:20px;border-top:1px solid var(--border)}'
            . "\n" . '.ep-avatar{width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,var(--blue),var(--teal));color:#fff;font-family:var(--fh);font-size:18px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}'
            . "\n" . '.ep-name{font-family:var(--fh);font-size:15px;font-weight:700;color:var(--dark)}'
            . "\n" . '.ep-creds{font-size:13px;color:var(--muted)}'
            . "\n" . '.ep-exp{font-size:11.5px;color:var(--muted);opacity:.7}'

            /* ── SHARED ── */
            . "\n" . '.sec-desc{font-size:15px;color:var(--muted);margin-bottom:1.5em;line-height:1.6}'
            . "\n" . '.btn-primary{display:inline-block;padding:14px 28px;border-radius:12px;background:var(--blue);color:#fff;font-family:var(--fh);font-size:15px;font-weight:700;text-decoration:none;transition:all .2s}'
            . "\n" . '.btn-primary:hover{background:var(--blue-dark);transform:translateY(-1px);box-shadow:0 4px 16px rgba(37,99,235,.25);text-decoration:none;color:#fff}'

            /* ── NEW INTENT BLOCKS RESPONSIVE ── */
            . "\n" . '@media(max-width:700px){'
            .   '.vc-input-row{flex-direction:column}'
            .   '.ns-step{grid-template-columns:44px 1fr;gap:10px}'
            .   '.ns-num{width:44px;height:44px;font-size:1.2rem}'
            .   '.sb-body{padding:20px}'
            .   '.ep-card{padding:24px}'
            .   '.cc-grid{grid-template-columns:1fr}'
            . '}'

            . "\n" . '</style>';
    }

    private function getScripts(int $articleId = 0): string {
        $trackUrl = json_encode(SEO_TRACK_SCRIPT);
        $aidJs    = $articleId;

        return '<script>'

            // Трекинг визита на страницу — через sendBeacon при загрузке
            . "\n" . '(function(){'
            . 'var TRACK=' . $trackUrl . ',AID=' . $aidJs . ';'
            . 'if(!AID)return;'
            . 'if(document.readyState==="loading"){'
            .   'document.addEventListener("DOMContentLoaded",function(){navigator.sendBeacon(TRACK+"?aid="+AID);});'
            . '}else{'
            .   'navigator.sendBeacon(TRACK+"?aid="+AID);'
            . '}'
            // Трекинг клика на внешние ссылки (seo_link_constants, которые идут через search.php?link=...)
            . 'document.addEventListener("click",function(e){'
            .   'var a=e.target.closest("a[href]");'
            .   'if(!a)return;'
            .   'var href=a.getAttribute("href");'
            .   'if(href&&href.indexOf("search.php?link=")!==-1){'
            .     'navigator.sendBeacon(TRACK+"?aid="+AID+"&type=link_click&href="+encodeURIComponent(href));'
            .   '}'
            . '});'
            . '})();'

            . "\n" . '(function(){'
            . 'var t=localStorage.getItem("sl-theme");'
            . 'if(t)document.documentElement.setAttribute("data-theme",t);'
            . '})();'

            . "\n" . '(function(){'
            . 'var obs=new IntersectionObserver(function(entries){'
            . 'entries.forEach(function(e,i){'
            . 'if(e.isIntersecting){setTimeout(function(){e.target.classList.add("vis")},i*60);obs.unobserve(e.target)}'
            . '})},{threshold:.08});'
            . 'document.querySelectorAll(".reveal").forEach(function(el){obs.observe(el)});'
            . '})();'

            . "\n" . '(function(){'
            . 'var bo=new IntersectionObserver(function(entries){'
            . 'entries.forEach(function(e){'
            . 'if(e.isIntersecting){e.target.style.width=e.target.dataset.width+"%";bo.unobserve(e.target)}'
            . '})},{threshold:.2});'
            . 'document.querySelectorAll(".bar-fill").forEach(function(el){bo.observe(el)});'
            . '})();'

            . "\n" . 'document.querySelectorAll(".faq-q").forEach(function(btn){'
            . 'btn.addEventListener("click",function(){'
            . 'var item=btn.closest(".faq-item"),open=item.classList.contains("open");'
            . 'document.querySelectorAll(".faq-item.open").forEach(function(i){i.classList.remove("open")});'
            . 'if(!open)item.classList.add("open")'
            . '})});'

            . "\n" . 'window.addEventListener("scroll",function(){'
            . 'document.getElementById("navbar").classList.toggle("scrolled",window.scrollY>50)'
            . '});'

            . "\n" . '(function(){'
            . 'var toc=document.getElementById("toc");if(!toc)return;'
            . 'var links=toc.querySelectorAll(".toc-link");'
            . 'var secs=[];'
            . 'links.forEach(function(l){var t=document.getElementById(l.dataset.target);if(t)secs.push({el:t,link:l})});'
            . 'if(!secs.length)return;'
            . 'function upd(){'
            . 'var y=window.scrollY+140,cur=secs[0];'
            . 'secs.forEach(function(s){if(s.el.offsetTop<=y)cur=s});'
            . 'links.forEach(function(l){l.classList.remove("active")});'
            . 'cur.link.classList.add("active")'
            . '}'
            . 'window.addEventListener("scroll",upd);upd()'
            . '})();'
            . "\n" . '(function(){'
            . 'var cfg=['
            . '{s:".p-orb--1", y:0.14, x:0.03},'
            . '{s:".p-orb--2", y:-0.10,x:-0.04},'
            . '{s:".p-orb--3", y:0.20, x:0.06},'
            . '{s:".p-orb--4", y:-0.15,x:0.05},'
            . '{s:".p-orb--5", y:0.08, x:-0.03},'
            . '{s:".p-orb--6", y:-0.18,x:0.07},'
            . '{s:".p-orb--7", y:0.12, x:-0.05},'
            . '{s:".p-orb--8", y:-0.08,x:0.04},'
            . '{s:".p-orb--9", y:0.22, x:0.02},'
            . '{s:".p-orb--10",y:-0.12,x:-0.06},'
            . '{s:".p-orb--11",y:0.16, x:0.04},'
            . '{s:".p-orb--12",y:-0.06,x:0.08},'
            . '{s:".p-orb--13",y:0.10, x:-0.02}'
            . '];'
            . 'var orbs=cfg.map(function(c){return{el:document.querySelector(c.s),y:c.y,x:c.x}});'
            . 'var ticking=false;'
            . 'function applyParallax(){'
            . 'var sy=window.scrollY;'
            . 'orbs.forEach(function(o){'
            . 'if(o.el)o.el.style.transform="translate("+(sy*o.x)+"px,"+(sy*o.y)+"px)"'
            . '});'
            . 'ticking=false'
            . '}'
            . 'window.addEventListener("scroll",function(){'
            . 'if(!ticking){requestAnimationFrame(applyParallax);ticking=true}'
            . '},{passive:true});'
            . '})();'
            . "\n" . '(function(){'
            . 'var btn=document.getElementById("theme-toggle");'
            . 'if(!btn)return;'
            . 'var html=document.documentElement;'
            . 'function updateIcon(){'
            . 'btn.textContent=html.getAttribute("data-theme")==="dark"?"☀️":"🌙"'
            . '}'
            . 'updateIcon();'
            . 'btn.addEventListener("click",function(){'
            . 'var isDark=html.getAttribute("data-theme")==="dark";'
            . 'var next=isDark?"light":"dark";'
            . 'html.setAttribute("data-theme",next);'
            . 'localStorage.setItem("sl-theme",next);'
            . 'updateIcon()'
            . '});'
            . '})();'
            . "\n" . '(function(){'
            . 'document.querySelectorAll("[data-donut]").forEach(function(wrap){'
            . 'var segs=wrap.querySelectorAll(".donut-seg");'
            . 'var btns=wrap.querySelectorAll("[data-seg-btn]");'
            . 'var detail=wrap.querySelector("[data-donut-detail]");'
            . 'var jsonEl=wrap.querySelector(".donut-seg-data");'
            . 'var segData=[];'
            . 'try{segData=JSON.parse(jsonEl?jsonEl.textContent:"[]")}catch(e){}'
            . 'var active=-1;'
            . 'var holeTotal=wrap.querySelector(".donut-total");'
            . 'var holeLabel=wrap.querySelector(".donut-total-label");'
            . 'var originalTotal=holeTotal?holeTotal.textContent:"100";'
            . 'var originalLabel=holeLabel?holeLabel.textContent:"всего";'
            . 'var style = document.createElement("style");'
            . 'style.textContent = `'
            . '.donut-hole.has-selected .donut-total-label { display: none; }'
            . '.donut-hole.has-selected .donut-total { '
            . '    font-size: 2.5em;'
            . '    line-height: 1.2;'
            . '    display: flex;'
            . '    align-items: center;'
            . '    justify-content: center;'
            . '    height: 100%;'
            . '}`;'
            . 'document.head.appendChild(style);'
            . 'var hole = wrap.querySelector(".donut-hole");'
            . 'function select(i){'
            . 'if(i===active){deselect();return}'
            . 'active=i;'
            . 'wrap.classList.add("has-active");'
            . 'segs.forEach(function(s,si){s.classList.toggle("is-active",si===i)});'
            . 'btns.forEach(function(b,bi){b.classList.toggle("is-active",bi===i)});'
            . 'if(detail&&segData[i]){'
            . 'var d=segData[i];'
            . 'var dot=detail.querySelector(".donut-detail-dot");'
            . 'var lbl=detail.querySelector(".donut-detail-label");'
            . 'var desc=detail.querySelector(".donut-detail-desc");'
            . 'if(dot)dot.style.background=d.color;'
            . 'if(lbl)lbl.textContent=d.label;'
            . 'if(desc)desc.textContent=d.desc;'
            . 'detail.classList.add("is-open");'
            . 'if(holeTotal&&btns[i]){'
            . 'var val=btns[i].querySelector(".donut-legend-val");'
            . 'if(val){'
            . 'var valText=val.textContent.trim();'
            . 'var numMatch=valText.match(/^(\\d+(\\.\\d+)?)/);'
            . 'if(numMatch){'
            . 'holeTotal.textContent=numMatch[1];'
            . '}'
            . '}'
            . '}'
            . '}'
            . '}'
            . 'function deselect(){'
            . 'active=-1;'
            . 'wrap.classList.remove("has-active");'
            . 'if(hole) hole.classList.remove("has-selected");'
            . 'segs.forEach(function(s){s.classList.remove("is-active")});'
            . 'btns.forEach(function(b){b.classList.remove("is-active")});'
            . 'if(detail)detail.classList.remove("is-open");'
            . 'if(holeTotal)holeTotal.textContent=originalTotal;'
            . 'if(holeLabel)holeLabel.textContent=originalLabel;'
            . '}'
            . 'segs.forEach(function(seg){seg.addEventListener("click",function(){'
            . 'select(parseInt(seg.dataset.seg,10))'
            . '})});'
            . 'btns.forEach(function(btn){btn.addEventListener("click",function(){'
            . 'select(parseInt(btn.dataset.segBtn,10))'
            . '})});'
            . 'document.addEventListener("click",function(e){'
            . 'if(!wrap.contains(e.target))deselect()'
            . '});'
            . '})})();'
            . "\n" . '(function(){'
            . 'document.querySelectorAll("[data-tabs]").forEach(function(wrap){'
            . 'var btns=wrap.querySelectorAll(".cmp-tab-btn");'
            . 'var panels=wrap.querySelectorAll(".cmp-tab-panel");'
            . 'btns.forEach(function(btn){'
            . 'btn.addEventListener("click",function(){'
            . 'var idx=btn.dataset.tab;'
            . 'btns.forEach(function(b){b.classList.remove("is-active")});'
            . 'panels.forEach(function(p){p.classList.remove("is-active")});'
            . 'btn.classList.add("is-active");'
            . 'var panel=wrap.querySelector("[data-panel=\""+idx+"\"]");'
            . 'if(panel)panel.classList.add("is-active")'
            . '})})});'
            . '})();'
            . "\n" . '(function(){'
            . 'document.querySelectorAll("[data-carousel]").forEach(function(wrap){'
            . 'var track=wrap.querySelector(".cmp-carousel-track");'
            . 'if(!track)return;'
            . 'var prev=wrap.querySelector(".cmp-carousel-btn--prev");'
            . 'var next=wrap.querySelector(".cmp-carousel-btn--next");'
            . 'var step=200;'
            . 'if(prev)prev.addEventListener("click",function(){track.scrollBy({left:-step,behavior:"smooth"})});'
            . 'if(next)next.addEventListener("click",function(){track.scrollBy({left:step,behavior:"smooth"})});'
            . 'var cards=track.querySelectorAll(".cmp-carousel-card");'
            . 'cards.forEach(function(card,i){'
            . 'card.addEventListener("click",function(){'
            . 'var tabsWrap=wrap.closest("section").querySelector("[data-tabs]");'
            . 'if(!tabsWrap)return;'
            . 'var btn=tabsWrap.querySelector(".cmp-tab-btn[data-tab=\""+i+"\"]");'
            . 'if(btn)btn.click();'
            . 'tabsWrap.scrollIntoView({behavior:"smooth",block:"nearest"})'
            . '})'
            . '});'
            . '})})();'
            . "\n" . '(function(){'
            . 'document.querySelectorAll("[data-norm-card]").forEach(function(card){'
            . 'var states=JSON.parse(card.dataset.states||"[]");'
            . 'if(!states.length)return;'
            . 'var idx=parseInt(card.dataset.active||"0",10);'
            . 'var pills=card.querySelectorAll(".norm-pill");'
            . 'var badge=card.querySelector(".norm-badge");'
            . 'var bar=card.querySelector(".norm-bar");'
            . 'var desc=card.querySelector(".norm-desc");'
            . 'var icon=card.querySelector(".norm-badge-icon");'
            . 'function apply(i){'
            . 'idx=i;'
            . 'var s=states[i];if(!s)return;'
            . 'desc.classList.add("is-fading");'
            . 'pills.forEach(function(p,pi){p.classList.toggle("is-active",pi===i)});'
            . 'badge.style.background=s.badge;badge.style.color=s.bc;'
            . 'icon.textContent=s.icon;'
            . 'badge.childNodes[badge.childNodes.length-1].textContent=" "+s.range;'
            . 'bar.style.background=s.bar;bar.style.width=s.pct+"%";'
            . 'setTimeout(function(){desc.textContent=s.desc;desc.classList.remove("is-fading")},200);'
            . '}'
            . 'pills.forEach(function(pill){'
            . 'pill.addEventListener("click",function(){apply(parseInt(pill.dataset.idx,10))});'
            . '});'
            . 'card.querySelector(".norm-card-header").addEventListener("click",function(){'
            . 'apply((idx+1)%states.length);'
            . '});'
            . '});'
            . '})();'

            /* ══════ NEW BLOCKS JS ══════ */
            . "\n" . '(function(){'
            . 'function hColor(t){return "rgba(37,99,235,"+(0.06+t*0.8)+")"}'

            /* ── GAUGE CHART init ── */
            . 'document.querySelectorAll("[data-gauges]").forEach(function(grid){'
            . 'var jsonEl=grid.parentElement.querySelector(".gauge-data");'
            . 'var detail=grid.parentElement.querySelector("[data-gauge-detail]");'
            . 'if(!jsonEl)return;var data=[];try{data=JSON.parse(jsonEl.textContent)}catch(e){}'
            . 'var r=50,arcLen=Math.PI,circum=arcLen*r,active=-1;'
            . 'var colors=["#2563EB","#0D9488","#8B5CF6","#F59E0B","#EF4444","#16A34A","#EC4899","#06B6D4"];'
            . 'data.forEach(function(d,i){'
            . 'var mn=parseFloat(d.min||0),mx=parseFloat(d.max||100),val=parseFloat(d.value||0);'
            . 'var pct=Math.max(0,Math.min(1,(val-mn)/(mx-mn)));'
            . 'var off=circum-circum*pct;var clr=d.color||colors[i%colors.length];'
            . 'var card=document.createElement("div");card.className="gauge-card";card.style.setProperty("--gauge-c",clr);card.dataset.idx=i;'
            . 'card.innerHTML=\'<svg class="gauge-svg" viewBox="0 0 140 80"><path class="gauge-track" d="M10,70 A50,50 0 0,1 130,70"/><path class="gauge-fill" d="M10,70 A50,50 0 0,1 130,70" stroke="\'+clr+\'" style="--gauge-glow:\'+clr+\'33;stroke-dasharray:\'+circum+\';stroke-dashoffset:\'+circum+\'" data-off="\'+off+\'"/></svg><div class="gauge-val">\'+val+\'<small>\'+((d.unit||""))+"</small></div>"+'
            . '\'<div class="gauge-name">\'+((d.name||""))+\'</div><div class="gauge-range"><span>\'+mn+\'</span><span>\'+mx+"</span></div>";'
            . 'card.addEventListener("click",function(){select(i)});grid.appendChild(card)});'
            . 'function select(i){if(i===active){desel();return}active=i;grid.classList.add("has-active");'
            . 'grid.querySelectorAll(".gauge-card").forEach(function(c,ci){c.classList.toggle("is-active",ci===i)});'
            . 'var d=data[i],mn=parseFloat(d.min||0),mx=parseFloat(d.max||100),val=parseFloat(d.value||0);'
            . 'var pct=Math.max(0,Math.min(1,(val-mn)/(mx-mn)));var clr=d.color||colors[i%colors.length];'
            . 'if(detail){detail.querySelector(".gauge-detail-dot").style.background=clr;'
            . 'detail.querySelector(".gauge-detail-label").textContent=d.name||"";'
            . 'detail.querySelector(".gauge-detail-val").textContent=val+" "+(d.unit||"");'
            . 'detail.querySelector(".gauge-detail-desc").textContent=d.description||"";'
            . 'var bar=detail.querySelector(".gauge-detail-bar");bar.style.background=clr;bar.style.width=Math.round(pct*100)+"%";'
            . 'detail.classList.add("is-open")}}'
            . 'function desel(){active=-1;grid.classList.remove("has-active");grid.querySelectorAll(".gauge-card").forEach(function(c){c.classList.remove("is-active")});if(detail)detail.classList.remove("is-open")}'
            . 'document.addEventListener("click",function(e){if(!grid.closest("section").contains(e.target))desel()});'
            . 'new IntersectionObserver(function(e){e.forEach(function(x){if(x.isIntersecting){x.target.querySelectorAll(".gauge-fill").forEach(function(p){p.style.strokeDashoffset=p.dataset.off})}})},{threshold:.25}).observe(grid)'
            . '});'

            /* ── TIMELINE init ── */
            . 'document.querySelectorAll("[data-timeline]").forEach(function(wrap){'
            . 'var items=wrap.querySelectorAll(".tl-item");var fill=wrap.querySelector(".tl-line-fill");var active=-1;'
            . 'items.forEach(function(it,i){it.addEventListener("click",function(){'
            . 'if(active===i){it.classList.remove("is-active");active=-1}else{'
            . 'items.forEach(function(x){x.classList.remove("is-active")});it.classList.add("is-active");active=i}})});'
            . 'new IntersectionObserver(function(e){e.forEach(function(x){if(x.isIntersecting){items.forEach(function(it,i){'
            . 'setTimeout(function(){it.classList.add("is-shown");if(fill)fill.style.height=((i+1)/items.length*100)+"%"},i*220)})}})},{threshold:.12}).observe(wrap)'
            . '});'

            /* ── HEATMAP init ── */
            . 'document.querySelectorAll("[data-heatmap]").forEach(function(wrap){'
            . 'var jsonEl=wrap.parentElement.querySelector(".hm-data");if(!jsonEl)return;'
            . 'var cfg={};try{cfg=JSON.parse(jsonEl.textContent)}catch(e){}'
            . 'var rows=cfg.rows||[],cols=cfg.columns||[],matrix=cfg.data||[];'
            . 'var grid=wrap.querySelector(".hm-grid");var info=wrap.querySelector(".hm-info");'
            . 'var swatch=wrap.querySelector(".hm-info-swatch"),valEl=wrap.querySelector(".hm-info-text b"),lblEl=wrap.querySelector(".hm-info-text span");'
            . 'grid.style.gridTemplateColumns="36px repeat("+cols.length+",1fr)";'
            . 'var corner=document.createElement("div");grid.appendChild(corner);'
            . 'cols.forEach(function(m){var l=document.createElement("div");l.className="hm-col-label";l.textContent=m;grid.appendChild(l)});'
            . 'var allCells=[],active=null;'
            . 'rows.forEach(function(w,ri){'
            . 'var rl=document.createElement("div");rl.className="hm-row-label";rl.textContent=w;grid.appendChild(rl);'
            . 'cols.forEach(function(m,ci){'
            . 'var val=(matrix[ri]&&matrix[ri][ci]!=null)?matrix[ri][ci]:Math.round(Math.abs(Math.sin(ri*127.1+ci*311.7)*43758.5453)%1*100);'
            . 'var c=document.createElement("div");c.className="hm-cell";c.style.background=hColor(val/100);'
            . 'c.dataset.val=val;c.dataset.row=ri;c.dataset.col=ci;c.dataset.label=w+", "+m;'
            . 'c.addEventListener("click",function(){selectCell(c,ri,ci,val,m,w)});'
            . 'grid.appendChild(c);allCells.push(c)})});'
            . 'function selectCell(el,ri,ci,val,m,w){'
            . 'if(active===el){desel();return}active=el;wrap.classList.add("has-active");'
            . 'allCells.forEach(function(c){c.classList.remove("is-active","is-row-hl","is-col-hl");'
            . 'if(+c.dataset.row===ri)c.classList.add("is-row-hl");if(+c.dataset.col===ci)c.classList.add("is-col-hl")});'
            . 'el.classList.add("is-active");swatch.style.background=hColor(val/100);valEl.textContent=val+"%";lblEl.textContent=" — "+w+", "+m;info.classList.add("is-open")}'
            . 'function desel(){active=null;wrap.classList.remove("has-active");allCells.forEach(function(c){c.classList.remove("is-active","is-row-hl","is-col-hl")});info.classList.remove("is-open")}'
            . 'document.addEventListener("click",function(e){if(!wrap.contains(e.target))desel()});'
            . '});'

            /* ── FUNNEL init ── */
            . 'document.querySelectorAll("[data-funnel]").forEach(function(wrap){'
            . 'var jsonEl=wrap.parentElement.querySelector(".fn-data");var detail=wrap.parentElement.querySelector("[data-fn-detail]");'
            . 'if(!jsonEl)return;var data=[];try{data=JSON.parse(jsonEl.textContent)}catch(e){}'
            . 'if(!data.length)return;var maxVal=parseFloat(data[0].value||1);var active=-1;var stages=[];'
            . 'var gradients=["linear-gradient(90deg,#2563EB,#60A5FA)","linear-gradient(90deg,#0D9488,#2DD4BF)","linear-gradient(90deg,#8B5CF6,#C4B5FD)","linear-gradient(90deg,#F59E0B,#FCD34D)","linear-gradient(90deg,#16A34A,#4ADE80)","linear-gradient(90deg,#EC4899,#F9A8D4)","linear-gradient(90deg,#06B6D4,#67E8F9)"];'
            . 'var dotColors=["#2563EB","#0D9488","#8B5CF6","#F59E0B","#16A34A","#EC4899","#06B6D4"];'
            . 'data.forEach(function(d,i){'
            . 'var val=parseFloat(d.value||0);var pct=Math.round(val/maxVal*100);'
            . 'var color=d.color||gradients[i%gradients.length];var dotC=dotColors[i%dotColors.length];'
            . 'var stage=document.createElement("div");stage.className="fn-stage";stage.style.setProperty("--fn-c",dotC);'
            . 'stage.innerHTML=\'<div class="fn-row"><div class="fn-label">\'+((d.label||""))+\'</div><div class="fn-bar-track"><div class="fn-bar-fill" style="background:\'+color+\'" data-width="\'+pct+\'"><span class="fn-inner-val">\'+val.toLocaleString("ru-RU")+\'</span></div></div><div class="fn-pct">\'+pct+"%</div></div>";'
            . 'stage.addEventListener("click",function(){selectStage(i)});wrap.appendChild(stage);stages.push(stage);'
            . 'if(i<data.length-1){var drop=Math.round((parseFloat(data[i].value)-parseFloat(data[i+1].value))/parseFloat(data[i].value)*100);'
            . 'var cn=document.createElement("div");cn.className="fn-conn";cn.innerHTML=\'<div></div><div class="fn-conn-line"><div class="fn-conn-arrow"></div></div><div class="fn-drop">-\'+drop+"%</div>";wrap.appendChild(cn)}});'
            . 'function selectStage(i){if(i===active){desel();return}active=i;wrap.classList.add("has-active");'
            . 'stages.forEach(function(s,si){s.classList.toggle("is-active",si===i)});'
            . 'if(detail){var d=data[i];detail.querySelector(".fn-detail-dot").style.background=dotColors[i%dotColors.length];'
            . 'detail.querySelector(".fn-detail-name").textContent=d.label||"";'
            . 'detail.querySelector(".fn-detail-big").textContent=parseFloat(d.value||0).toLocaleString("ru-RU");'
            . 'detail.querySelector(".fn-detail-desc").textContent=d.description||"";detail.classList.add("is-open")}}'
            . 'function desel(){active=-1;wrap.classList.remove("has-active");stages.forEach(function(s){s.classList.remove("is-active")});if(detail)detail.classList.remove("is-open")}'
            . 'document.addEventListener("click",function(e){if(!wrap.closest("section").contains(e.target))desel()});'
            . 'new IntersectionObserver(function(e){e.forEach(function(x){if(x.isIntersecting){x.target.querySelectorAll(".fn-bar-fill").forEach(function(b,i){setTimeout(function(){b.style.width=b.dataset.width+"%"},i*100)})}})},{threshold:.2}).observe(wrap)'
            . '});'

            /* ── SPARK METRICS init ── */
            . 'document.querySelectorAll("[data-sparks]").forEach(function(grid){'
            . 'var jsonEl=grid.parentElement.querySelector(".sp-data");'
            . 'var detail=grid.parentElement.querySelector("[data-sp-detail]");'
            . 'if(!jsonEl)return;'
            . 'var cards=[];'
            . 'try{cards=JSON.parse(jsonEl.textContent)}catch(e){}'
            . 'var active=-1;'
            . 'var colors=["#2563EB","#0D9488","#8B5CF6","#16A34A","#F59E0B","#EF4444","#EC4899","#06B6D4"];'
            . 'cards.forEach(function(d,i){'
            . 'var card=document.createElement("div");'
            . 'card.className="sp-card";'
            . 'var clr=d.color||colors[i%colors.length];'
            . 'card.style.setProperty("--sp-c",clr);'
            . 'var w=180,h=40,pad=4;'
            . 'var pts=(d.points&&d.points.length>=2)?d.points:[50,50];'
            . 'var mn=Math.min.apply(null,pts);'
            . 'var mx=Math.max.apply(null,pts);'
            . 'var rng=mx-mn||1;'
            . 'var coords=pts.map(function(v,j){'
            . 'var x=pad+(w-2*pad)/(pts.length-1)*j;'
            . 'var y=h-pad-(v-mn)/rng*(h-2*pad);'
            . 'return x+","+y'
            . '});'
            . 'var poly=coords.join(" ");'
            . 'var last=coords[coords.length-1].split(",");'
            . 'var area="M "+coords[0]'
            . '+" L "+coords.slice(1).join(" ")'
            . '+" L "+(w-pad)+","+h'
            . '+" L "+pad+","+h+" Z";'
            . 'var lineLen=0;'
            . 'for(var k=1;k<coords.length;k++){'
            . 'var a=coords[k-1].split(","),b=coords[k].split(",");'
            . 'lineLen+=Math.sqrt(Math.pow(b[0]-a[0],2)+Math.pow(b[1]-a[1],2))'
            . '}'
            . 'var tUp=d.trend_up!==false&&d.trend_up!==0;'
            . 'var tCls=tUp?"sp-trend--up":"sp-trend--down";'
            . 'var tArr=tUp?"↑":"↓";'
            . 'card.innerHTML='
            . '"<div class=\"sp-head\">"'
            . '+"<div class=\"sp-icon\" style=\"background:"+(d.icon_bg||"#EFF6FF")+"\">"'
            . '+(d.icon||"📊")'
            . '+"</div>"'
            . '+"<span class=\"sp-trend "+tCls+"\">"'
            . '+tArr+" "+(d.trend||"")'
            . '+"</span>"'
            . '+"</div>"'
            . '+"<div class=\"sp-val\">"+(d.value||"0")+"<small>"+(d.unit||"")+"</small></div>"'
            . '+"<div class=\"sp-name\">"+(d.name||"")+"</div>"'
            . '+"<div class=\"sp-chart\">"'
            . '+"<svg class=\"sp-svg\" viewBox=\"0 0 "+w+" "+h+"\" preserveAspectRatio=\"none\">"'
            . '+"<path class=\"sp-area\" d=\""+area+"\" fill=\""+clr+"\"/>"'
            . '+"<polyline class=\"sp-line\" points=\""+poly+"\" stroke=\""+clr+"\" style=\"--sp-len:"+Math.ceil(lineLen)+"\"/>"'
            . '+"<circle class=\"sp-dot-pulse\" cx=\""+last[0]+"\" cy=\""+last[1]+"\" r=\"3\" stroke=\""+clr+"\" fill=\""+clr+"\"/>"'
            . '+"<circle class=\"sp-dot\" cx=\""+last[0]+"\" cy=\""+last[1]+"\" r=\"3\" stroke=\""+clr+"\"/>"'
            . '+"</svg>"'
            . '+"</div>";'
            . 'card.addEventListener("click",function(){selectSp(i)});'
            . 'grid.appendChild(card)'
            . '});'
            . 'function selectSp(idx){'
            . 'if(idx===active){deselSp();return}'
            . 'active=idx;'
            . 'grid.classList.add("has-active");'
            . 'grid.querySelectorAll(".sp-card").forEach(function(c,ci){'
            . 'c.classList.toggle("is-active",ci===idx)'
            . '});'
            . 'if(detail){'
            . 'var d=cards[idx];'
            . 'var inner=detail.querySelector(".sp-detail-inner");'
            . 'if(inner){'
            . 'inner.innerHTML=(d.details||[]).map(function(p){'
            . 'return"<div class=\"sp-detail-pair\"><span>"+p[0]+"</span><span>"+p[1]+"</span></div>"'
            . '}).join("")'
            . '}'
            . 'detail.classList.add("is-open")'
            . '}}'
            . 'function deselSp(){'
            . 'active=-1;'
            . 'grid.classList.remove("has-active");'
            . 'grid.querySelectorAll(".sp-card").forEach(function(c){c.classList.remove("is-active")});'
            . 'if(detail)detail.classList.remove("is-open")'
            . '}'
            . 'document.addEventListener("click",function(e){'
            . 'var section=grid.closest("section");'
            . 'if(!section||!section.contains(e.target))deselSp()'
            . '});'
            . 'new IntersectionObserver(function(entries){'
            . 'entries.forEach(function(x){'
            . 'if(x.isIntersecting){'
            . 'x.target.querySelectorAll(".sp-line").forEach(function(l){l.classList.add("is-drawn")})'
            . '}'
            . '});'
            . '},{threshold:0.2}).observe(grid)'
            . '});'

            /* ── RADAR CHART init ── */
            . 'document.querySelectorAll("[data-radar]").forEach(function(wrap){'
            . 'var jsonEl=wrap.parentElement.querySelector(".radar-data");if(!jsonEl)return;'
            . 'var cfg={};try{cfg=JSON.parse(jsonEl.textContent)}catch(e){}'
            . 'var axes=cfg.axes||[];var svg=wrap.querySelector(".radar-svg");var aside=wrap.querySelector(".radar-aside");'
            . 'var detailEl=wrap.parentElement.querySelector("[data-radar-detail]")||wrap.parentElement.querySelector(".radar-detail");'
            . 'var detailInner=detailEl?detailEl.querySelector(".radar-detail-inner"):null;'
            . 'var cx=130,cy=130,R=100,n=axes.length,active=-1;'
            . 'var colors=["#2563EB","#0D9488","#8B5CF6","#F59E0B","#EF4444","#16A34A","#EC4899","#06B6D4"];'
            . 'if(!n)return;'
            . '[.25,.5,.75,1].forEach(function(s){var pts=[];for(var i=0;i<n;i++){var a=-Math.PI/2+2*Math.PI/n*i;pts.push((cx+R*s*Math.cos(a))+","+(cy+R*s*Math.sin(a)))}var poly=document.createElementNS("http://www.w3.org/2000/svg","polygon");poly.setAttribute("points",pts.join(" "));poly.setAttribute("class","radar-grid-line");svg.appendChild(poly)});'
            . 'for(var i=0;i<n;i++){var a=-Math.PI/2+2*Math.PI/n*i;var line=document.createElementNS("http://www.w3.org/2000/svg","line");line.setAttribute("x1",cx);line.setAttribute("y1",cy);line.setAttribute("x2",cx+R*Math.cos(a));line.setAttribute("y2",cy+R*Math.sin(a));line.setAttribute("class","radar-axis");svg.appendChild(line);'
            . 'var lbl=document.createElementNS("http://www.w3.org/2000/svg","text");lbl.setAttribute("x",cx+(R+18)*Math.cos(a));lbl.setAttribute("y",cy+(R+18)*Math.sin(a));lbl.setAttribute("text-anchor","middle");lbl.setAttribute("dominant-baseline","middle");lbl.setAttribute("class","radar-axis-label");lbl.setAttribute("data-idx",i);lbl.textContent=axes[i].name||"";lbl.addEventListener("click",function(){selectAxis(+this.dataset.idx)});svg.appendChild(lbl)}'
            . 'var shapePts=axes.map(function(ax,i){var a=-Math.PI/2+2*Math.PI/n*i;var r=R*(parseFloat(ax.value||50))/100;return(cx+r*Math.cos(a))+","+(cy+r*Math.sin(a))});'
            . 'var shape=document.createElementNS("http://www.w3.org/2000/svg","polygon");shape.setAttribute("points",shapePts.join(" "));shape.setAttribute("fill","rgba(37,99,235,.15)");shape.setAttribute("stroke","#2563EB");shape.setAttribute("stroke-width","2");shape.setAttribute("class","radar-shape");svg.appendChild(shape);'
            . 'var dots=[];axes.forEach(function(ax,i){var a=-Math.PI/2+2*Math.PI/n*i;var r=R*(parseFloat(ax.value||50))/100;var dot=document.createElementNS("http://www.w3.org/2000/svg","circle");dot.setAttribute("cx",cx+r*Math.cos(a));dot.setAttribute("cy",cy+r*Math.sin(a));dot.setAttribute("r","4");dot.setAttribute("fill",colors[i%colors.length]);dot.setAttribute("stroke","#fff");dot.setAttribute("stroke-width","2");dot.setAttribute("class","radar-dot");dot.dataset.idx=i;dot.addEventListener("click",function(){selectAxis(+this.dataset.idx)});svg.appendChild(dot);dots.push(dot)});'
            . 'axes.forEach(function(ax,i){var item=document.createElement("button");item.className="radar-legend-item";item.dataset.idx=i;item.innerHTML=\'<span class="radar-legend-dot" style="background:\'+colors[i%colors.length]+\'"></span><span>\'+(ax.name||"")+\'</span><span class="radar-legend-val">\'+(ax.value||0)+"%</span>";item.addEventListener("click",function(){selectAxis(i)});aside.appendChild(item)});'
            . 'function selectAxis(i){if(i===active){desel();return}active=i;wrap.classList.add("has-active");'
            . 'aside.querySelectorAll(".radar-legend-item").forEach(function(el,ei){el.classList.toggle("is-active",ei===i)});'
            . 'svg.querySelectorAll(".radar-axis-label").forEach(function(el){el.classList.toggle("is-active",+el.dataset.idx===i)});'
            . 'dots.forEach(function(d,di){d.setAttribute("r",di===i?"7":"4")});'
            . 'if(detailInner){detailInner.innerHTML="<b style=\\"color:"+colors[i%colors.length]+"\\">"+(axes[i].name||"")+" — "+(axes[i].value||0)+"%</b><br>"+(axes[i].description||"");detailEl.classList.add("is-open")}}'
            . 'function desel(){active=-1;wrap.classList.remove("has-active");aside.querySelectorAll(".radar-legend-item").forEach(function(el){el.classList.remove("is-active")});svg.querySelectorAll(".radar-axis-label").forEach(function(el){el.classList.remove("is-active")});dots.forEach(function(d){d.setAttribute("r","4")});if(detailEl)detailEl.classList.remove("is-open")}'
            . 'document.addEventListener("click",function(e){if(!wrap.closest("section").contains(e.target))desel()});'
            . '});'

            /* ── BEFORE/AFTER init ── */
            . 'document.querySelectorAll("[data-beforeafter]").forEach(function(wrap){'
            . 'var jsonEl=wrap.parentElement.querySelector(".ba-data");if(!jsonEl)return;'
            . 'var metrics=[];try{metrics=JSON.parse(jsonEl.textContent)}catch(e){}'
            . 'if(!metrics.length)return;'
            . 'var h=\'<div class="ba-cards"><div class="ba-side ba-side--before"><div class="ba-tag ba-tag--before">До</div>\';'
            . 'metrics.forEach(function(m){h+=\'<div class="ba-metric"><div class="ba-metric-val">\'+m.before+\'<small style="font-size:.5em;color:var(--muted);margin-left:2px">\'+((m.unit||""))+\'</small></div><div class="ba-metric-name">\'+(m.name||"")+\'</div><div class="ba-bar-track"><div class="ba-bar-fill" style="background:var(--red);width:0" data-w="\'+Math.round(m.before/m.max*100)+\'"></div></div></div>\'});'
            . 'h+=\'</div><div class="ba-side ba-side--after"><div class="ba-tag ba-tag--after">После</div>\';'
            . 'metrics.forEach(function(m){h+=\'<div class="ba-metric"><div class="ba-metric-val">\'+m.after+\'<small style="font-size:.5em;color:var(--muted);margin-left:2px">\'+((m.unit||""))+\'</small></div><div class="ba-metric-name">\'+(m.name||"")+\'</div><div class="ba-bar-track"><div class="ba-bar-fill" style="background:var(--green);width:0" data-w="\'+Math.round(m.after/m.max*100)+\'"></div></div></div>\'});'
            . 'h+=\'</div></div><div class="ba-slider-row"><span class="ba-slider-label">До</span><input type="range" min="0" max="100" value="100" class="ba-slider"><span class="ba-slider-label">После</span></div><div class="ba-delta"><div class="ba-delta-val" style="color:var(--green)">↑ Результат</div><div class="ba-delta-label">Перетяните слайдер</div></div>\';'
            . 'wrap.innerHTML=h;'
            . 'wrap.querySelector(".ba-slider").addEventListener("input",function(){var t=+this.value/100;'
            . 'wrap.querySelector(".ba-delta-val").textContent=t>=.5?"↑ Улучшение: "+Math.round(t*100)+"%":"⏳ Прогресс: "+Math.round(t*100)+"%";'
            . 'wrap.querySelector(".ba-delta-val").style.color=t>=.5?"var(--green)":"var(--warn)"});'
            . 'new IntersectionObserver(function(e){e.forEach(function(x){if(x.isIntersecting){x.target.querySelectorAll(".ba-bar-fill").forEach(function(b){b.style.width=b.dataset.w+"%"})}})},{threshold:.2}).observe(wrap)'
            . '});'

            /* ── STACKED AREA init ── */
            . 'document.querySelectorAll("[data-stacked]").forEach(function(wrap){'
            . 'var jsonEl=wrap.parentElement.querySelector(".sa-data");if(!jsonEl)return;'
            . 'var cfg={};try{cfg=JSON.parse(jsonEl.textContent)}catch(e){}'
            . 'var labels=cfg.labels||[];var series=cfg.series||[];'
            . 'var svg=wrap.querySelector(".sa-svg");var legend=wrap.querySelector(".sa-legend");var xlEl=wrap.querySelector(".sa-x-labels");'
            . 'var detailEl=wrap.querySelector(".sa-detail");var detailInner=detailEl?detailEl.querySelector(".sa-detail-inner"):null;'
            . 'var W=600,H=200,n=labels.length,active=-1;if(!n||!series.length)return;'
            . 'xlEl.innerHTML=labels.map(function(l){return"<span>"+l+"</span>"}).join("");'
            . 'var totals=labels.map(function(_,i){return series.reduce(function(s,sr){return s+(sr.data[i]||0)},0)});var maxT=Math.max.apply(null,totals)||1;'
            . 'var areas=[];for(var si=series.length-1;si>=0;si--){var topP=[],botP=[];'
            . 'for(var xi=0;xi<n;xi++){var x=xi/(n-1)*W;var below=0;for(var k=0;k<si;k++)below+=(series[k].data[xi]||0);var top=below+(series[si].data[xi]||0);topP.push(x+","+(H-top/maxT*H));botP.push(x+","+(H-below/maxT*H))}'
            . 'var d="M"+topP.join(" L")+" L"+botP.reverse().join(" L")+" Z";var path=document.createElementNS("http://www.w3.org/2000/svg","path");path.setAttribute("d",d);path.setAttribute("fill",series[si].color||"#2563EB");path.setAttribute("class","sa-area");path.dataset.idx=si;'
            . 'path.addEventListener("click",function(){selectSA(+this.dataset.idx)});svg.appendChild(path);areas.push({el:path,idx:si})}'
            . 'series.forEach(function(s,i){var item=document.createElement("button");item.className="sa-legend-item";item.dataset.idx=i;'
            . 'item.innerHTML=\'<span class="sa-legend-dot" style="background:\'+(s.color||"#2563EB")+\'"></span><span>\'+(s.name||"")+\'</span><span class="sa-legend-val">\'+((s.data[s.data.length-1])||0)+"%</span>";'
            . 'item.addEventListener("click",function(){selectSA(i)});legend.appendChild(item)});'
            . 'function selectSA(i){if(i===active){desel();return}active=i;wrap.classList.add("has-active");'
            . 'areas.forEach(function(a){a.el.classList.toggle("is-active",a.idx===i)});'
            . 'legend.querySelectorAll(".sa-legend-item").forEach(function(el,ei){el.classList.toggle("is-active",ei===i)});'
            . 'if(detailInner){detailInner.innerHTML="<b style=\\"color:"+(series[i].color||"#2563EB")+"\\">"+(series[i].name||"")+"</b><br>"+(series[i].description||"");detailEl.classList.add("is-open")}}'
            . 'function desel(){active=-1;wrap.classList.remove("has-active");areas.forEach(function(a){a.el.classList.remove("is-active")});legend.querySelectorAll(".sa-legend-item").forEach(function(el){el.classList.remove("is-active")});if(detailEl)detailEl.classList.remove("is-open")}'
            . 'document.addEventListener("click",function(e){if(!wrap.closest("section").contains(e.target))desel()});'
            . '});'

            /* ── SCORE RINGS init ── */
            . 'document.querySelectorAll("[data-rings]").forEach(function(wrap){'
            . 'var jsonEl=wrap.parentElement.querySelector(".ring-data");if(!jsonEl)return;'
            . 'var cfg={};try{cfg=JSON.parse(jsonEl.textContent)}catch(e){}'
            . 'var rings=cfg.rings||[];var svg=wrap.querySelector(".ring-svg");var aside=wrap.querySelector(".ring-aside");'
            . 'var centerVal=wrap.querySelector(".ring-center-val");'
            . 'var detailEl=wrap.querySelector(".ring-detail");var detailInner=detailEl?detailEl.querySelector(".ring-detail-inner"):null;'
            . 'var active=-1;var radii=[90,72,54,36];'
            . 'var avgScore=Math.round(rings.reduce(function(s,r){return s+(parseFloat(r.value)||0)},0)/Math.max(1,rings.length));'
            . 'if(centerVal)centerVal.textContent=avgScore;'
            . 'rings.forEach(function(ring,i){'
            . 'var r=radii[i%radii.length];var circum=2*Math.PI*r;var pct=(parseFloat(ring.value)||0)/(parseFloat(ring.max)||100);var offset=circum*(1-pct);var clr=ring.color||"#2563EB";'
            . 'var track=document.createElementNS("http://www.w3.org/2000/svg","circle");track.setAttribute("cx","110");track.setAttribute("cy","110");track.setAttribute("r",r);track.setAttribute("class","ring-track");track.setAttribute("stroke",clr);track.setAttribute("stroke-width","16");svg.appendChild(track);'
            . 'var fill=document.createElementNS("http://www.w3.org/2000/svg","circle");fill.setAttribute("cx","110");fill.setAttribute("cy","110");fill.setAttribute("r",r);fill.setAttribute("class","ring-fill");fill.setAttribute("stroke",clr);fill.setAttribute("stroke-dasharray",circum);fill.setAttribute("stroke-dashoffset",circum);fill.setAttribute("transform","rotate(-90 110 110)");fill.style.setProperty("--ring-glow",clr+"55");fill.dataset.target=offset;fill.dataset.idx=i;fill.style.cursor="pointer";fill.addEventListener("click",function(){selectRing(+this.dataset.idx)});svg.appendChild(fill);'
            . 'var item=document.createElement("div");item.className="ring-item";item.style.setProperty("--ring-c",clr);item.dataset.idx=i;'
            . 'item.innerHTML=\'<div class="ring-item-dot" style="background:\'+clr+\'"></div><div class="ring-item-info"><div class="ring-item-name">\'+(ring.name||"")+\'</div><div class="ring-item-sub">\'+(ring.subtitle||"")+\'</div></div><div style="text-align:right"><div class="ring-item-val">\'+(ring.value||0)+\'</div><div class="ring-item-pct">из \'+(ring.max||100)+"</div></div>";'
            . 'item.addEventListener("click",function(){selectRing(i)});aside.appendChild(item)});'
            . 'function selectRing(i){if(i===active){desel();return}active=i;wrap.classList.add("has-active");'
            . 'aside.querySelectorAll(".ring-item").forEach(function(el,ei){el.classList.toggle("is-active",ei===i)});'
            . 'if(centerVal)centerVal.textContent=rings[i].value||0;'
            . 'if(detailInner){detailInner.innerHTML="<b style=\\"color:"+(rings[i].color||"#2563EB")+"\\">"+(rings[i].name||"")+" — "+(rings[i].value||0)+"/"+(rings[i].max||100)+"</b><br>"+(rings[i].description||"");detailEl.classList.add("is-open")}}'
            . 'function desel(){active=-1;wrap.classList.remove("has-active");aside.querySelectorAll(".ring-item").forEach(function(el){el.classList.remove("is-active")});if(centerVal)centerVal.textContent=avgScore;if(detailEl)detailEl.classList.remove("is-open")}'
            . 'document.addEventListener("click",function(e){if(!wrap.closest("section").contains(e.target))desel()});'
            . 'new IntersectionObserver(function(e){e.forEach(function(x){if(x.isIntersecting){svg.querySelectorAll(".ring-fill").forEach(function(f){f.style.strokeDashoffset=f.dataset.target})}})},{threshold:.25}).observe(svg)'
            . '});'

            /* ── RANGE COMPARISON init ── */
            . 'document.querySelectorAll("[data-ranges]").forEach(function(wrap){'
            . 'var jsonEl=wrap.parentElement.querySelector(".rc-data");if(!jsonEl)return;'
            . 'var cfg={};try{cfg=JSON.parse(jsonEl.textContent)}catch(e){}'
            . 'var groups=cfg.groups||[];var rows=cfg.rows||[];'
            . 'var toggle=wrap.querySelector(".rc-toggle");var rowsEl=wrap.querySelector(".rc-wrap");'
            . 'var detail=wrap.querySelector(".rc-detail");var detailInner=detail?detail.querySelector(".rc-detail-inner"):null;'
            . 'var colors=["#2563EB","#EC4899","#0D9488","#F59E0B"];var activeGroup=0;var activeRow=-1;'
            . 'groups.forEach(function(g,gi){var btn=document.createElement("button");btn.className="rc-toggle-btn"+(gi===0?" is-active":"");btn.textContent=g.key||"";btn.addEventListener("click",function(){activeGroup=gi;toggle.querySelectorAll(".rc-toggle-btn").forEach(function(b,bi){b.classList.toggle("is-active",bi===gi)});renderRows();if(activeRow>=0)showDetail(activeRow)});toggle.appendChild(btn)});'
            . 'function renderRows(){rowsEl.innerHTML="";rows.forEach(function(r,ri){'
            . 'var row=document.createElement("div");row.className="rc-row"+(ri===activeRow?" is-active":"");'
            . 'var barsHtml=\'<div class="rc-bars">\';'
            . 'groups.forEach(function(g,gi){var lo=r.ranges[gi][0],hi=r.ranges[gi][1],val=r.values[gi],mn=parseFloat(r.min||0),mx=parseFloat(r.max||200);'
            . 'var leftPct=((lo-mn)/(mx-mn)*100).toFixed(1);var widthPct=(((hi-lo)/(mx-mn))*100).toFixed(1);var markerPct=(((val-mn)/(mx-mn))*100).toFixed(1);var op=gi===activeGroup?"1":".25";'
            . 'barsHtml+=\'<div class="rc-bar-row"><span class="rc-bar-name" style="color:\'+colors[gi%colors.length]+\'">\'+((g.tag||""))+\'</span><div class="rc-bar-track"><div class="rc-bar-range" style="left:\'+leftPct+\'%;width:\'+widthPct+\'%;background:\'+colors[gi%colors.length]+\';opacity:\'+op+\'"></div><div class="rc-bar-marker" style="left:calc(\'+markerPct+\'% - 1px);background:\'+colors[gi%colors.length]+\';opacity:\'+Math.max(.5,+op)+\'"></div></div><span class="rc-bar-val">\'+val+" "+(r.unit||"")+"</span></div>"});'
            . 'barsHtml+="</div>";row.innerHTML=\'<div class="rc-label">\'+(r.name||"")+\'</div>\'+barsHtml;'
            . 'row.addEventListener("click",function(){if(activeRow===ri){deselRow();return}activeRow=ri;wrap.classList.add("has-active");rowsEl.querySelectorAll(".rc-row").forEach(function(x,xi){x.classList.toggle("is-active",xi===ri)});showDetail(ri)});'
            . 'rowsEl.appendChild(row)})}'
            . 'function showDetail(ri){var r=rows[ri],g=groups[activeGroup],val=r.values[activeGroup],lo=r.ranges[activeGroup][0],hi=r.ranges[activeGroup][1];'
            . 'var status=val>=lo&&val<=hi?"<span style=\\"color:var(--green)\\">✓ В норме</span>":"<span style=\\"color:var(--red)\\">⚠ Отклонение</span>";'
            . 'if(detailInner){detailInner.innerHTML="<b>"+(r.name||"")+" ("+(g.key||"")+")</b> — "+val+" "+(r.unit||"")+" "+status+"<br>Норма: "+lo+"–"+hi+" "+(r.unit||"")+"<br>"+(r.description||"");detail.classList.add("is-open")}}'
            . 'function deselRow(){activeRow=-1;wrap.classList.remove("has-active");rowsEl.querySelectorAll(".rc-row").forEach(function(x){x.classList.remove("is-active")});if(detail)detail.classList.remove("is-open")}'
            . 'document.addEventListener("click",function(e){if(!wrap.contains(e.target))deselRow()});'
            . 'renderRows()'
            . '});'

            . '})();'

            /* ══════ NEW INTENT BLOCKS JS ══════ */
            . "\n" . '(function(){'

            /* ── VALUE CHECKER ── */
            . 'document.querySelectorAll("[data-vcheck]").forEach(function(wrap){'
            . 'var jsonEl=wrap.querySelector(".vc-data");if(!jsonEl)return;'
            . 'var zones=[];try{zones=JSON.parse(jsonEl.textContent)}catch(e){}'
            . 'var input=wrap.querySelector(".vc-input");var btn=wrap.querySelector(".vc-btn");'
            . 'var marker=wrap.querySelector(".vc-marker");var result=wrap.querySelector(".vc-result");'
            . 'var rIcon=result.querySelector(".vc-result-icon");var rLabel=result.querySelector(".vc-result-label");var rText=result.querySelector(".vc-result-text");'
            . 'var track=wrap.querySelector(".vc-scale-track");'
            . 'var globalMin=Infinity,globalMax=-Infinity;'
            . 'zones.forEach(function(z){if(z.from<globalMin)globalMin=z.from;if(z.to>globalMax)globalMax=z.to});'
            . 'function check(){'
            . 'var val=parseFloat(input.value);if(isNaN(val))return;'
            . 'var pct=Math.max(0,Math.min(100,(val-globalMin)/(globalMax-globalMin)*100));'
            . 'marker.style.display="block";marker.style.left=pct+"%";'
            . 'var zone=zones.find(function(z){return val>=z.from&&val<z.to})||zones[zones.length-1];'
            . 'rIcon.textContent=zone.icon||"";rLabel.textContent=(zone.label||"");rLabel.style.color=zone.color||"var(--dark)";'
            . 'rText.textContent=zone.text||"";result.style.display="block";result.style.borderColor=zone.color||"var(--border)"}'
            . 'btn.addEventListener("click",check);input.addEventListener("keydown",function(e){if(e.key==="Enter")check()})});'

            /* ── SYMPTOM CHECKLIST ── */
            . 'document.querySelectorAll("[data-symcheck]").forEach(function(wrap){'
            . 'var jsonEl=wrap.querySelector(".sc-data");if(!jsonEl)return;'
            . 'var cfg={};try{cfg=JSON.parse(jsonEl.textContent)}catch(e){}'
            . 'var thresholds=cfg.thresholds||[];var items=cfg.items||[];'
            . 'var checks=wrap.querySelectorAll(".sc-check");var countEl=wrap.querySelector(".sc-count");'
            . 'var bar=wrap.querySelector(".sc-bar");var result=wrap.querySelector(".sc-result");'
            . 'var rIcon=result.querySelector(".sc-result-icon");var rLabel=result.querySelector(".sc-result-label");var rText=result.querySelector(".sc-result-text");'
            . 'var cta=wrap.querySelector(".sc-cta");'
            . 'var maxScore=items.reduce(function(s,it){return s+(it.weight||1)},0);'
            . 'function update(){'
            . 'var score=0,count=0;checks.forEach(function(ch,i){if(ch.checked){count++;score+=items[i]?items[i].weight||1:1}});'
            . 'countEl.textContent=count;bar.style.width=Math.round(score/maxScore*100)+"%";'
            . 'var th=thresholds.filter(function(t){return score>=t.min&&score<=t.max});'
            . 'if(th.length&&count>0){var t=th[0];rLabel.textContent=t.label||"";rLabel.style.color=t.color||"var(--dark)";'
            . 'rText.textContent=t.text||"";rIcon.textContent=score>=7?"🚨":score>=3?"⚠️":"✅";'
            . 'result.style.display="block";result.style.borderColor=t.color||"var(--border)";'
            . 'if(cta&&score>=3)cta.style.display="block"}else{result.style.display="none";if(cta)cta.style.display="none"}}'
            . 'checks.forEach(function(ch){ch.addEventListener("change",update)})});'

            /* ── PREP CHECKLIST ── */
            . 'document.querySelectorAll("[data-prepcheck]").forEach(function(wrap){'
            . 'var total=parseInt(wrap.dataset.total)||1;var checks=wrap.querySelectorAll(".pc-check");'
            . 'var doneEl=wrap.querySelector(".pc-done");var bar=wrap.querySelector(".pc-bar");'
            . 'var confetti=wrap.querySelector(".pc-confetti");var milestone=wrap.querySelector(".pc-milestone");'
            . 'var msgs=["","Хорошее начало! 💪","Уже половина! 👏","Почти готово! 🔥",""];'
            . 'function update(){'
            . 'var done=0;checks.forEach(function(ch){if(ch.checked)done++});'
            . 'var pct=Math.round(done/total*100);'
            . 'doneEl.textContent=done;bar.style.width=pct+"%";'
            . 'if(done===total&&confetti){confetti.style.display="block";confetti.style.animation="fadeInUp .5s ease";'
            . 'if(milestone)milestone.style.display="none"}'
            . 'else if(confetti){confetti.style.display="none"}'
            . 'if(milestone&&done<total){'
            . 'var mi=pct<25?0:pct<50?1:pct<75?2:3;'
            . 'if(msgs[mi]){milestone.textContent=msgs[mi];milestone.style.display="block";milestone.style.animation="fadeInUp .3s ease"}'
            . 'else{milestone.style.display="none"}}}'
            . 'checks.forEach(function(ch){ch.addEventListener("change",function(){'
            . 'var label=ch.closest(".pc-item");if(label&&ch.checked){label.style.transition="background .3s";label.style.background="rgba(22,163,74,.06)";'
            . 'setTimeout(function(){label.style.background=""},600)}'
            . 'update()})})});'

            /* ── VERDICT CARD ── */
            . 'document.querySelectorAll("[data-verdict]").forEach(function(grid){'
            . 'grid.querySelectorAll(".vd-card").forEach(function(card){'
            . 'card.addEventListener("click",function(){card.classList.toggle("is-open")})})});'

            /* ── MINI CALCULATOR ── */
            . 'document.querySelectorAll("[data-mcalc]").forEach(function(wrap){'
            . 'var jsonEl=wrap.querySelector(".mc-data");if(!jsonEl)return;'
            . 'var cfg={};try{cfg=JSON.parse(jsonEl.textContent)}catch(e){}'
            . 'var results=cfg.results||[];var inputs=cfg.inputs||[];'
            . 'var btn=wrap.querySelector(".mc-btn");var resultEl=wrap.querySelector(".mc-result");'
            . 'var rVal=resultEl.querySelector(".mc-result-value");var rText=resultEl.querySelector(".mc-result-text");'
            . 'var fields=wrap.querySelectorAll("[data-key]");'
            . 'var showIfFields=wrap.querySelectorAll("[data-show-if-key]");'
            . 'function updateVisibility(){'
            . 'showIfFields.forEach(function(f){'
            . 'var key=f.dataset.showIfKey;var val=f.dataset.showIfVal;'
            . 'var src=wrap.querySelector("[data-key=\\""+key+"\\"]");'
            . 'if(src){f.style.display=src.value===val?"grid":"none"}})}'
            . 'fields.forEach(function(f){f.addEventListener("change",updateVisibility)});updateVisibility();'
            . 'btn.addEventListener("click",function(){'
            . 'var vals={};fields.forEach(function(f){vals[f.dataset.key]=f.value});'
            . 'var match=results.find(function(r){'
            . 'var cond=r.condition||"";var parts=cond.split("&&");'
            . 'return parts.every(function(p){p=p.trim();var m=p.match(/^(\\w+)=(.+)$/);if(!m)return false;return vals[m[1]]===m[2]})});'
            . 'if(match){rVal.textContent=match.value||"";rText.textContent=match.text||"";resultEl.style.display="block"}else{rVal.textContent="—";rText.textContent="Заполните все поля";resultEl.style.display="block"}})});'

            /* ── PROGRESS TRACKER ── */
            . 'document.querySelectorAll("[data-ptrack]").forEach(function(wrap){'
            . 'var jsonEl=wrap.querySelector(".pt-data");if(!jsonEl)return;'
            . 'var milestones=[];try{milestones=JSON.parse(jsonEl.textContent)}catch(e){}'
            . 'var dots=wrap.querySelectorAll(".pt-dot");var fill=wrap.querySelector(".pt-track-fill");'
            . 'var detail=wrap.querySelector(".pt-detail");var dPeriod=detail.querySelector(".pt-detail-period");'
            . 'var dText=detail.querySelector(".pt-detail-text");var dMetric=detail.querySelector(".pt-detail-metric");'
            . 'var active=-1;'
            . 'dots.forEach(function(dot,i){dot.addEventListener("click",function(e){'
            . 'e.stopPropagation();if(active===i){desel();return}active=i;'
            . 'dots.forEach(function(d){d.classList.remove("is-active")});dot.classList.add("is-active");'
            . 'var m=milestones[i]||{};dPeriod.textContent=m.period||"";dText.textContent=m.text||"";'
            . 'dMetric.textContent=m.metric||"";detail.style.display="block";'
            . 'fill.style.width=(m.marker||0)+"%"})});'
            . 'function desel(){active=-1;dots.forEach(function(d){d.classList.remove("is-active")});detail.style.display="none"}'
            . 'document.addEventListener("click",function(e){if(!wrap.contains(e.target))desel()});'
            . 'new IntersectionObserver(function(e){e.forEach(function(x){if(x.isIntersecting){var last=milestones[milestones.length-1];if(last)fill.style.width=(last.marker||100)+"%"}})},{threshold:.3}).observe(wrap)'
            . '});'

            . '})();'
            . "\n" . '</script>';
    }


    private function loadArticle(int $id): array {
        $r = $this->db->fetchOne("SELECT * FROM " . SeoArticle::SEO_ARTICLE_TABLE . " WHERE id = ?", [$id]);
        if (!$r) throw new RuntimeException("Статья #{$id} не найдена");
        return $r;
    }

    private function loadBlocks(int $articleId): array {
        return $this->db->fetchAll(
            "SELECT * FROM " . SeoArticleBlock::SEO_ART_BLOCK_TABLE . " WHERE article_id = ? ORDER BY sort_order",
            [$articleId]
        );
    }

    private function loadLinks(int $articleId): array{
        return $this->db->fetchAll(
            "SELECT * FROM " . SeoLinkConstant::SEO_LINKS_TABLE . " WHERE article_id IS NULL OR article_id = ?",
            [$articleId]
        );
    }

    private function loadTemplate(int $id): array {
        return $this->db->fetchOne("SELECT * FROM " . SeoTemplate::TABLE . " WHERE id = ?", [$id]) ?? [];
    }

    private function e(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
