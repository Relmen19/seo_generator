<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;
use Seo\Service\HtmlRenderer\Util\InlineMarkdown;

class RichtextBlockRenderer extends AbstractBlockRenderer
{
    /** Footnotes collected during rendering of one block */
    private array $footnotes = [];

    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $content, string $id): string
    {
        $c = $content;
        $tocName = '';
        $this->footnotes = [];

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
            if ($isFloat && $imageVPos === 'bottom') {
                $textIdxes = [];
                foreach ($normalized as $idx => $nb) {
                    if (($nb['type'] ?? 'paragraph') !== 'heading') {
                        $textIdxes[] = $idx;
                    }
                }
                $pick = max(0, count($textIdxes) - 2);
                $insertAfter = $textIdxes[$pick] ?? max(0, count($normalized) - 1);
            } else {
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
        }

        // Detect long-form types — apply lf-* styling only when present.
        $longformTypes = ['callout', 'code', 'figure', 'table', 'footnote', 'steps', 'stat', 'pros_cons', 'definition'];
        $isLongform = false;
        foreach ($normalized as $nb) {
            if (in_array(($nb['type'] ?? ''), $longformTypes, true)) {
                $isLongform = true;
                break;
            }
        }
        $rootClass = $isLongform ? 'block-richtext lf-richtext reveal' : 'block-richtext reveal';

        $h = '<section id="'.$id.'" class="'.$rootClass.'">'
            . '<div class="container">';

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

            $h .= $this->renderRichtextSubblock($b, $tocName, $isLongform);
        }

        if ($inFloat) {
            $h .= '<div class="rt-float-clear"></div></div>';
            $inFloat = false;
        }

        if ($imageHtml) {
            $h .= $imageHtml;
        }

        // Footnotes list at end of block
        if (!empty($this->footnotes)) {
            $h .= '<ol class="lf-footnotes">';
            foreach ($this->footnotes as $fn) {
                $fid = $this->e((string)$fn['id']);
                $h .= '<li id="fn-'.$fid.'">'
                    . InlineMarkdown::render((string)$fn['text'])
                    . ' <a href="#fnref-'.$fid.'" class="lf-fn-back" aria-label="Назад">↩</a>'
                    . '</li>';
            }
            $h .= '</ol>';
        }

        $h = str_replace(
            'class="'.$rootClass.'"',
            'class="'.$rootClass.'" data-toc="'.$this->e($tocName ?: 'Описание').'"',
            $h
        );

        return $h.'</div></section>'."\n";
    }

    private function renderRichtextSubblock(array $b, string &$tocName, bool $longform = false): string
    {
        $t = $b['type'] ?? 'paragraph';
        $h = '';
        $inline = function (string $s) use ($longform): string {
            return $longform ? $this->renderInline($s) : $this->e($s);
        };

        // Block types that read scalar text
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
            $style = (string)($b['style'] ?? '');
            $isOrdered = $style === 'ordered' || !empty($b['ordered']);
            $tag = $isOrdered ? 'ol' : 'ul';
            $h .= "<{$tag}>";
            foreach ($items as $li) {
                $liText = is_string($li) ? $li : (is_array($li) && isset($li['text']) ? $li['text'] : json_encode($li, JSON_UNESCAPED_UNICODE));
                $h .= '<li>'.$inline((string)$liText).'</li>';
            }
            $h .= "</{$tag}>";

        } elseif ($t === 'steps') {
            $items = is_array($b['items'] ?? null) ? $b['items'] : [];
            if (!empty($items)) {
                $h .= '<ol class="lf-steps">';
                foreach ($items as $step) {
                    if (!is_array($step)) continue;
                    $title    = trim((string)($step['title'] ?? ''));
                    $body     = trim((string)($step['body'] ?? $step['text'] ?? ''));
                    $duration = trim((string)($step['duration'] ?? ''));
                    if ($title === '' && $body === '') continue;
                    $h .= '<li class="lf-step"><div class="lf-step-head">';
                    if ($title !== '') $h .= '<span class="lf-step-title">'.$inline($title).'</span>';
                    if ($duration !== '') $h .= '<span class="lf-step-duration">'.$this->e($duration).'</span>';
                    $h .= '</div>';
                    if ($body !== '') $h .= '<div class="lf-step-body">'.$inline($body).'</div>';
                    $h .= '</li>';
                }
                $h .= '</ol>';
            }

        } elseif ($t === 'stat') {
            $stats = isset($b['items']) && is_array($b['items']) ? $b['items'] : [$b];
            $cards = [];
            foreach ($stats as $st) {
                if (!is_array($st)) continue;
                $value = trim((string)($st['value'] ?? ''));
                $label = trim((string)($st['label'] ?? ''));
                if ($value === '' && $label === '') continue;
                $trend   = strtolower(trim((string)($st['trend'] ?? '')));
                $context = trim((string)($st['context'] ?? ''));
                $trendClass = '';
                $arrow = '';
                if ($trend === 'up')   { $trendClass = ' lf-stat--up';   $arrow = '↑'; }
                if ($trend === 'down') { $trendClass = ' lf-stat--down'; $arrow = '↓'; }
                $card  = '<div class="lf-stat'.$trendClass.'">';
                $card .= '<div class="lf-stat-value">';
                if ($arrow !== '') $card .= '<span class="lf-stat-arrow" aria-hidden="true">'.$arrow.'</span>';
                $card .= $this->e($value).'</div>';
                if ($label !== '')   $card .= '<div class="lf-stat-label">'.$inline($label).'</div>';
                if ($context !== '') $card .= '<div class="lf-stat-context">'.$inline($context).'</div>';
                $card .= '</div>';
                $cards[] = $card;
            }
            if (!empty($cards)) {
                $h .= '<div class="lf-stat-grid">'.implode('', $cards).'</div>';
            }

        } elseif ($t === 'pros_cons') {
            $pros = is_array($b['pros'] ?? null) ? $b['pros'] : [];
            $cons = is_array($b['cons'] ?? null) ? $b['cons'] : [];
            $proLabel = trim((string)($b['pros_label'] ?? 'За'));
            $conLabel = trim((string)($b['cons_label'] ?? 'Против'));
            if (!empty($pros) || !empty($cons)) {
                $h .= '<div class="lf-pros-cons">';
                $renderCol = function (string $label, array $items, string $variant) use ($inline) {
                    $col  = '<div class="lf-pc lf-pc--'.$variant.'">';
                    $col .= '<div class="lf-pc-head">'.$this->e($label).'</div>';
                    $col .= '<ul class="lf-pc-list">';
                    foreach ($items as $it) {
                        $line = is_string($it) ? $it : (is_array($it) && isset($it['text']) ? (string)$it['text'] : '');
                        $line = trim($line);
                        if ($line === '') continue;
                        $col .= '<li>'.$inline($line).'</li>';
                    }
                    $col .= '</ul></div>';
                    return $col;
                };
                $h .= $renderCol($proLabel, $pros, 'pros');
                $h .= $renderCol($conLabel, $cons, 'cons');
                $h .= '</div>';
            }

        } elseif ($t === 'definition') {
            $items = is_array($b['items'] ?? null) ? $b['items'] : [];
            if (!empty($items)) {
                $h .= '<dl class="lf-def">';
                foreach ($items as $it) {
                    if (!is_array($it)) continue;
                    $term = trim((string)($it['term'] ?? ''));
                    $def  = trim((string)($it['def'] ?? $it['definition'] ?? ''));
                    if ($term === '' || $def === '') continue;
                    $h .= '<dt>'.$inline($term).'</dt><dd>'.$inline($def).'</dd>';
                }
                $h .= '</dl>';
            }

        } elseif ($t === 'highlight') {
            $h .= '<div class="highlight">'.$inline($txt).'</div>';

        } elseif ($t === 'quote') {
            $author = isset($b['author']) ? (string)$b['author'] : '';
            $source = isset($b['source']) ? (string)$b['source'] : '';
            // Legacy: simple <blockquote>text</blockquote>. Long-form: structured citation.
            if (!$longform && $author === '' && $source === '') {
                $h .= '<blockquote>'.$this->e($txt).'</blockquote>';
            } else {
                $h .= '<blockquote class="lf-quote"><p>'.$inline($txt).'</p>';
                if ($author !== '' || $source !== '') {
                    $h .= '<footer class="lf-quote-cite">';
                    if ($author !== '') $h .= '<span class="lf-quote-author">'.$this->e($author).'</span>';
                    if ($source !== '') $h .= '<cite class="lf-quote-source">'.$this->e($source).'</cite>';
                    $h .= '</footer>';
                }
                $h .= '</blockquote>';
            }

        } elseif ($t === 'callout') {
            $variant = (string)($b['variant'] ?? 'info');
            if (!in_array($variant, ['info', 'warn', 'tip', 'danger'], true)) $variant = 'info';
            $icon = [
                'info'   => 'i',
                'warn'   => '!',
                'tip'    => '★',
                'danger' => '⚠',
            ][$variant];
            $h .= '<aside class="lf-callout lf-callout--'.$variant.'">'
                . '<span class="lf-callout-icon" aria-hidden="true">'.$icon.'</span>'
                . '<div class="lf-callout-body">'.$inline($txt).'</div>'
                . '</aside>';

        } elseif ($t === 'code') {
            $lang = (string)($b['lang'] ?? '');
            $code = (string)($b['code'] ?? $txt);
            $langSafe = preg_replace('/[^a-z0-9_+\-]/i', '', $lang) ?: 'plaintext';
            $cls = 'language-'.$langSafe;
            $h .= '<figure class="lf-code">'
                . '<div class="lf-code-bar"><span class="lf-code-lang">'.$this->e($langSafe).'</span></div>'
                . '<pre class="'.$cls.'"><code class="'.$cls.'">'
                . htmlspecialchars($code, ENT_QUOTES, 'UTF-8')
                . '</code></pre>'
                . '</figure>';

        } elseif ($t === 'figure') {
            $imgSrc = '';
            $alt = $this->e((string)($b['alt'] ?? ''));
            $caption = (string)($b['caption'] ?? '');
            if (!empty($b['image_id'])) {
                $img = $this->db->fetchOne(
                    "SELECT mime_type, data_base64 FROM seo_images WHERE id = ?",
                    [(int)$b['image_id']]
                );
                if ($img && !empty($img['data_base64'])) {
                    $mime = $this->e($img['mime_type'] ?? 'image/jpeg');
                    $imgSrc = 'data:'.$mime.';base64,'.$img['data_base64'];
                }
            } elseif (!empty($b['image_url'])) {
                $url = (string)$b['image_url'];
                if (preg_match('#^(https?://|/|data:)#i', $url)) {
                    $imgSrc = $url;
                }
            }
            if ($imgSrc !== '') {
                $h .= '<figure class="lf-figure">'
                    . '<div class="img-frame"><img src="'.$this->e($imgSrc).'" alt="'.$alt.'" loading="lazy"></div>';
                if ($caption !== '') {
                    $h .= '<figcaption>'.$inline($caption).'</figcaption>';
                }
                $h .= '</figure>';
            }

        } elseif ($t === 'table') {
            $headers = $b['headers'] ?? [];
            $rows = $b['rows'] ?? [];
            if (is_array($headers) && is_array($rows)) {
                $h .= '<div class="lf-table-wrap"><table class="lf-table">';
                if (!empty($headers)) {
                    $h .= '<thead><tr>';
                    foreach ($headers as $col) {
                        $h .= '<th>'.$inline((string)$col).'</th>';
                    }
                    $h .= '</tr></thead>';
                }
                $h .= '<tbody>';
                foreach ($rows as $row) {
                    if (!is_array($row)) continue;
                    $h .= '<tr>';
                    foreach ($row as $cell) {
                        $h .= '<td>'.$inline((string)$cell).'</td>';
                    }
                    $h .= '</tr>';
                }
                $h .= '</tbody></table></div>';
            }

        } elseif ($t === 'footnote') {
            // Collect; rendered at end of block
            $fid = (string)($b['id'] ?? (count($this->footnotes) + 1));
            $this->footnotes[] = ['id' => $fid, 'text' => $txt];

        } else {
            if (trim($txt) !== '') {
                $h .= '<p>'.$inline($txt).'</p>';
            }
        }

        return $h;
    }

    /**
     * Inline render: footnote refs [^id] + markdown.
     * Order: replace [^id] tokens first → markdown render escapes the rest.
     */
    private function renderInline(string $s): string
    {
        // Pull out footnote references before markdown so they don't get escaped.
        $refs = [];
        $s = preg_replace_callback('/\[\^([A-Za-z0-9_\-]+)\]/u', function ($m) use (&$refs) {
            $fid = $m[1];
            $refs[] = $fid;
            return "\x02FN" . (count($refs) - 1) . "\x02";
        }, $s) ?? $s;

        $rendered = InlineMarkdown::render($s);

        $rendered = preg_replace_callback('/\x02FN(\d+)\x02/', function ($m) use ($refs) {
            $fid = $refs[(int)$m[1]] ?? '';
            $safe = htmlspecialchars($fid, ENT_QUOTES, 'UTF-8');
            return '<sup class="lf-fnref" id="fnref-'.$safe.'"><a href="#fn-'.$safe.'">'.$safe.'</a></sup>';
        }, $rendered) ?? $rendered;

        return $rendered;
    }

    public function getCss(): string
    {
        // Tokenized: bind --lf-* aliases to design tokens (--color-*, --type-*, --radius-*).
        // Theme defines tokens; richtext keeps its own --lf-* namespace for stable selectors.
        return ':root{'
            . '--lf-text-size:var(--type-size-text,18px);--lf-line:var(--type-line-text,1.65);--lf-col:var(--layout-col-max,720px);'
            . '--lf-mono:var(--type-font-mono,"JetBrains Mono","Fira Code",ui-monospace,SFMono-Regular,Menlo,Consolas,monospace);'
            . '--lf-fg:var(--color-text,#1a1a1a);--lf-muted:var(--color-text-3,#5a6472);'
            . '--lf-bg-soft:var(--color-accent-soft,#f6f7f9);--lf-surface:var(--color-surface,#fff);'
            . '--lf-border:var(--color-border,#e3e6eb);--lf-accent:var(--color-accent,#2f6feb);'
            . '--lf-success:var(--color-success,#10b981);--lf-warn:var(--color-warn,#d97706);--lf-danger:var(--color-danger,#dc2626);'
            . '}'

            . '.block-richtext{padding:64px 0}'
            . '.lf-richtext .container{max-width:var(--lf-col);margin:0 auto}'
            . '.lf-richtext p{font-size:var(--lf-text-size);line-height:var(--lf-line);color:var(--lf-fg);margin:0 0 1.1em}'
            . '.lf-richtext li{font-size:var(--lf-text-size);line-height:var(--lf-line)}'
            . '.lf-richtext h2,.lf-richtext h3,.lf-richtext h4{margin:1.6em 0 .6em;line-height:1.25}'
            . '.lf-richtext a{color:var(--lf-accent);text-decoration:underline;text-underline-offset:2px}'

            . '.lf-ic{font-family:var(--lf-mono);font-size:.92em;background:var(--lf-bg-soft);'
            .   'border:1px solid var(--lf-border);border-radius:var(--radius-sm,4px);padding:.05em .35em}'

            . '.lf-quote{border-left:3px solid var(--lf-accent);background:var(--lf-bg-soft);'
            .   'margin:1.5em 0;padding:.9em 1.2em;border-radius:0 var(--radius-md,8px) var(--radius-md,8px) 0}'
            . '.lf-quote p{margin:0;font-style:italic}'
            . '.lf-quote-cite{margin-top:.5em;font-size:.9em;color:var(--lf-muted);font-style:normal}'
            . '.lf-quote-author{font-weight:600}'
            . '.lf-quote-source{margin-left:.5em;font-style:normal}'
            . '.lf-quote-source::before{content:"— "}'

            . '.lf-callout{display:flex;gap:.8em;margin:1.4em 0;padding:1em 1.2em;border-radius:var(--radius-md,10px);'
            .   'border:1px solid var(--lf-border);background:var(--lf-surface)}'
            . '.lf-callout-icon{flex:0 0 28px;height:28px;width:28px;border-radius:50%;display:inline-flex;'
            .   'align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:14px}'
            . '.lf-callout-body{flex:1;font-size:var(--lf-text-size);line-height:var(--lf-line)}'
            . '.lf-callout-body p{margin:0}'
            . '.lf-callout--info{background:var(--lf-bg-soft)}'
            . '.lf-callout--info .lf-callout-icon{background:var(--lf-accent)}'
            . '.lf-callout--warn .lf-callout-icon{background:var(--lf-warn)}'
            . '.lf-callout--tip .lf-callout-icon{background:var(--lf-success)}'
            . '.lf-callout--danger .lf-callout-icon{background:var(--lf-danger)}'

            // Code block: intentionally dark regardless of theme — keeps Prism tokens legible.
            . '.lf-code{margin:1.4em 0;border:1px solid var(--lf-border);border-radius:var(--radius-md,10px);overflow:hidden;'
            .   'background:#0f172a}'
            . '.lf-code-bar{display:flex;justify-content:space-between;align-items:center;padding:.5em .9em;'
            .   'background:#1e293b;border-bottom:1px solid #334155}'
            . '.lf-code-lang{font-family:var(--lf-mono);font-size:.78em;color:#94a3b8;text-transform:lowercase;'
            .   'letter-spacing:.05em}'
            . '.lf-code pre{margin:0;padding:1em 1.1em;overflow-x:auto;background:transparent;'
            .   'font-family:var(--lf-mono);font-size:14px;line-height:1.55;color:#e2e8f0}'
            . '.lf-code code{font-family:inherit;background:transparent;color:inherit;padding:0}'
            . '.lf-code pre[class*="language-"]{background:transparent;margin:0;padding:1em 1.1em;'
            .   'text-shadow:none;font-family:var(--lf-mono);font-size:14px;line-height:1.55}'
            . '.lf-code code[class*="language-"]{background:transparent;text-shadow:none;'
            .   'font-family:var(--lf-mono);font-size:inherit}'

            . '.lf-figure{margin:1.6em 0}'
            . '.lf-figure .img-frame{border-radius:var(--radius-md,10px);overflow:hidden;border:1px solid var(--lf-border)}'
            . '.lf-figure img{display:block;width:100%;height:auto}'
            . '.lf-figure figcaption{margin-top:.55em;text-align:center;color:var(--lf-muted);'
            .   'font-size:.92em;line-height:1.4;font-style:italic}'

            . '.lf-table-wrap{margin:1.5em 0;overflow-x:auto;border:1px solid var(--lf-border);border-radius:var(--radius-sm,8px)}'
            . '.lf-table{width:100%;border-collapse:collapse;font-size:.95em}'
            . '.lf-table th,.lf-table td{padding:.7em .9em;text-align:left;border-bottom:1px solid var(--lf-border);vertical-align:top}'
            . '.lf-table th{background:var(--lf-bg-soft);font-weight:600}'
            . '.lf-table tr:last-child td{border-bottom:0}'

            . '.lf-steps{counter-reset:lf-step;list-style:none;padding:0;margin:1.6em 0;display:flex;flex-direction:column;gap:1em}'
            . '.lf-step{counter-increment:lf-step;position:relative;padding:1em 1.2em 1em 3.2em;border:1px solid var(--lf-border);'
            .   'background:var(--lf-surface);border-radius:var(--radius-md,10px)}'
            . '.lf-step::before{content:counter(lf-step);position:absolute;left:1em;top:1em;width:1.8em;height:1.8em;'
            .   'border-radius:50%;background:var(--lf-accent);color:#fff;font-weight:700;display:flex;align-items:center;justify-content:center;font-size:.95em}'
            . '.lf-step-head{display:flex;justify-content:space-between;align-items:baseline;gap:.8em;margin-bottom:.3em}'
            . '.lf-step-title{font-weight:600;font-size:1.05em}'
            . '.lf-step-duration{font-size:.85em;color:var(--lf-muted);background:var(--lf-bg-soft);padding:.15em .55em;border-radius:var(--radius-sm,6px);white-space:nowrap}'
            . '.lf-step-body{font-size:var(--lf-text-size);line-height:var(--lf-line);color:var(--lf-fg)}'

            . '.lf-stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1em;margin:1.6em 0}'
            . '.lf-stat{padding:1em 1.1em;border:1px solid var(--lf-border);border-radius:var(--radius-md,10px);background:var(--lf-surface)}'
            . '.lf-stat-value{font-size:2em;font-weight:700;line-height:1.1;display:flex;align-items:baseline;gap:.2em}'
            . '.lf-stat-arrow{font-size:.85em}'
            . '.lf-stat-label{margin-top:.3em;color:var(--lf-fg);font-weight:500}'
            . '.lf-stat-context{margin-top:.2em;font-size:.88em;color:var(--lf-muted);line-height:1.4}'
            . '.lf-stat--up .lf-stat-value{color:var(--lf-success)}'
            . '.lf-stat--down .lf-stat-value{color:var(--lf-danger)}'

            . '.lf-pros-cons{display:grid;grid-template-columns:1fr 1fr;gap:1em;margin:1.6em 0}'
            . '.lf-pc{padding:1em 1.1em;border:1px solid var(--lf-border);border-radius:var(--radius-md,10px);background:var(--lf-surface)}'
            . '.lf-pc-head{font-weight:700;margin-bottom:.5em;font-size:.95em;text-transform:uppercase;letter-spacing:.05em}'
            . '.lf-pc--pros .lf-pc-head{color:var(--lf-success)}'
            . '.lf-pc--cons .lf-pc-head{color:var(--lf-danger)}'
            . '.lf-pc-list{margin:0;padding-left:1.2em}'
            . '.lf-pc-list li{margin:.35em 0;line-height:1.5}'
            . '@media(max-width:640px){.lf-pros-cons{grid-template-columns:1fr}}'

            . '.lf-def{margin:1.4em 0;padding:1em 1.2em;border:1px solid var(--lf-border);border-radius:var(--radius-md,10px);background:var(--lf-bg-soft)}'
            . '.lf-def dt{font-weight:600;margin-top:.6em;color:var(--lf-fg)}'
            . '.lf-def dt:first-child{margin-top:0}'
            . '.lf-def dd{margin:.15em 0 0;padding-left:0;color:var(--lf-fg);line-height:1.55}'

            . '.lf-fnref{font-size:.7em;line-height:0;vertical-align:super;margin-left:1px}'
            . '.lf-fnref a{text-decoration:none;color:var(--lf-accent)}'
            . '.lf-footnotes{margin:2.4em 0 0;padding:1em 1em 1em 2.2em;border-top:1px solid var(--lf-border);'
            .   'font-size:.92em;color:var(--lf-muted);line-height:1.5}'
            . '.lf-footnotes li{margin:.4em 0}'
            . '.lf-fn-back{text-decoration:none;color:var(--lf-accent);margin-left:.3em}'

            . '.rt-img{margin:2em auto}'
            . '.rt-img .img-frame{border-radius:var(--radius-lg,16px);overflow:hidden;box-shadow:0 8px 32px rgba(15,23,42,.08);position:relative}'
            . '.rt-img .img-frame img{display:block;width:100%;height:auto;transition:transform .5s ease}'
            . '.rt-img .img-frame:hover img{transform:scale(1.02)}'
            . '.rt-img figcaption{margin-top:10px;font-size:.85rem;color:var(--lf-muted);text-align:center;line-height:1.4;font-style:italic}'
            . '.rt-img--center{max-width:640px;margin-left:auto;margin-right:auto}'
            . '.rt-img--full{max-width:100%}'
            . '.rt-img--left{float:left;max-width:340px;width:40%;margin:.4em 1.8em 1em 0;shape-outside:margin-box}'
            . '.rt-img--right{float:right;max-width:340px;width:40%;margin:.4em 0 1em 1.8em;shape-outside:margin-box}'
            . '.rt-float-wrap{overflow:hidden;margin:1.5em 0}'
            . '.rt-float-clear{clear:both;display:table;width:100%}'
            . '@media(max-width:768px){'
            .   '.rt-img--left,.rt-img--right{float:none;max-width:100%;width:100%;margin:1.2em 0;shape-outside:none}'
            .   '.rt-img--center{max-width:100%}'
            .   '.rt-float-wrap{overflow:visible}'
            .   '.rt-float-clear{display:none}'
            .   '.lf-richtext .container{padding:0 16px}'
            . '}';
    }

    public function getJs(): string
    {
        // Conditional Prism.js loader: fires once if any .lf-code is present.
        // Autoloader pulls the matching language grammar by class language-*.
        return <<<'JS'
(function(){
  if (!document.querySelector('.lf-code code')) return;
  if (window.__lfPrismLoaded) return;
  window.__lfPrismLoaded = true;
  var base = 'https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/';
  var link = document.createElement('link');
  link.rel = 'stylesheet';
  link.href = base + 'themes/prism-tomorrow.min.css';
  document.head.appendChild(link);
  var core = document.createElement('script');
  core.src = base + 'components/prism-core.min.js';
  core.onload = function(){
    var auto = document.createElement('script');
    auto.src = base + 'plugins/autoloader/prism-autoloader.min.js';
    auto.onload = function(){
      if (window.Prism && Prism.plugins && Prism.plugins.autoloader) {
        Prism.plugins.autoloader.languages_path = base + 'components/';
      }
      if (window.Prism) Prism.highlightAll();
    };
    document.head.appendChild(auto);
  };
  document.head.appendChild(core);
})();
JS;
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
