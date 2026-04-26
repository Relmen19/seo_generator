<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer;

use RuntimeException;
use Seo\Database;
use Seo\Entity\SeoArticle;
use Seo\Entity\SeoArticleBlock;
use Seo\Entity\SeoLinkConstant;
use Seo\Entity\SeoTemplate;
use Seo\Service\HtmlRenderer\Component\DividerComponent;
use Seo\Service\HtmlRenderer\Component\FooterComponent;
use Seo\Service\HtmlRenderer\Component\NavbarComponent;
use Seo\Service\HtmlRenderer\Component\NavSearchComponent;
use Seo\Service\HtmlRenderer\Component\ParallaxComponent;
use Seo\Service\HtmlRenderer\Component\TocComponent;
use Seo\Service\HtmlRenderer\Component\TrackingComponent;
use Seo\Service\HtmlRenderer\Theme\DefaultTheme;
use Seo\Service\HtmlRenderer\Theme\ThemeFactory;
use Seo\Service\HtmlRenderer\Theme\ThemeInterface;

class PageAssembler
{
    private Database $db;
    private BlockRegistry $registry;
    private ?array $siteProfile = null;
    private ThemeInterface $theme;
    private bool $themeOverridden = false;

    public function __construct(Database $db, BlockRegistry $registry)
    {
        $this->db = $db;
        $this->registry = $registry;
        $this->theme = new DefaultTheme();
    }

    public function setSiteProfile(?array $profile): self
    {
        $this->siteProfile = $profile;
        return $this;
    }

    public function setTheme(ThemeInterface $theme): self
    {
        $this->theme = $theme;
        $this->themeOverridden = true;
        return $this;
    }

    public function render(int $articleId, bool $preview = false): string
    {
        $article  = $this->loadArticle($articleId);
        $blocks   = $this->loadBlocks($articleId);
        $links    = $this->loadLinks($articleId);
        $template = $article['template_id']
            ? $this->loadTemplate((int)$article['template_id'])
            : null;

        if ($this->siteProfile === null && !empty($article['profile_id'])) {
            $this->siteProfile = $this->db->fetchOne(
                "SELECT * FROM seo_site_profiles WHERE id = ?", [$article['profile_id']]
            );
        }

        // Auto-select theme from profile (unless explicitly overridden)
        if (!$this->themeOverridden && $this->siteProfile !== null && !empty($this->siteProfile['theme'])) {
            $this->theme = ThemeFactory::create($this->siteProfile['theme']);
        }

        $assets = new AssetCollector();
        $assets->addTheme($this->theme);

        // Components
        $parallax  = new ParallaxComponent();
        $navbar    = new NavbarComponent();
        $navSearch = new NavSearchComponent();
        $toc       = new TocComponent();
        $footer    = new FooterComponent();
        $divider   = new DividerComponent();
        $tracking  = new TrackingComponent((int)$article['id']);

        $assets->addComponent($parallax);
        $assets->addComponent($navbar);
        $assets->addComponent($navSearch);
        $assets->addComponent($toc);
        $assets->addComponent($footer);
        $assets->addComponent($divider);
        $assets->addComponent($tracking);

        // Render blocks
        $visibleBlocks = [];
        $bodyHtml = '';
        foreach ($blocks as $idx => $block) {
            if (!(int)($block['is_visible'] ?? 1)) continue;
            $content = is_string($block['content'])
                ? json_decode($block['content'], true)
                : ($block['content'] ?? []);
            // Inject article_id so block renderers can pull article-scoped data
            // (e.g. HeroBlockRenderer reads seo_article_illustrations).
            if (!is_array($content)) $content = [];
            $content['article_id'] = $block['article_id'] ?? null;

            $renderer = $this->registry->get($block['type']);
            if (!$renderer) {
                $bodyHtml .= "<!-- unknown block: {$block['type']} -->\n";
                $visibleBlocks[] = $block;
                continue;
            }

            if ($idx > 0 && $block['type'] !== 'hero') {
                $bodyHtml .= $divider->renderHtml([]);
            }

            $blockId = 'block-' . ($block['id'] ?? uniqid());
            $bodyHtml .= $renderer->renderHtml($content, $blockId);
            $assets->addBlock($renderer);
            $visibleBlocks[] = $block;
        }

        // Build TOC — provide block renderers keyed by type
        $blockRenderers = [];
        foreach ($visibleBlocks as $vb) {
            $t = $vb['type'];
            if (!isset($blockRenderers[$t])) {
                $r = $this->registry->get($t);
                if ($r) {
                    $blockRenderers[$t] = $r;
                }
            }
        }
        $tocContext = [
            'blocks' => $visibleBlocks,
            'blockRenderers' => $blockRenderers,
        ];
        $tocHtml = $toc->renderHtml($tocContext);

        // Build navbar search
        $navSearchHtml = $navSearch->renderHtml([
            'articleId' => (int)$article['id'],
            'profileId' => (int)($article['profile_id'] ?? 0),
        ]);

        // Build navbar
        $navbarContext = [
            'article'     => $article,
            'template'    => $template,
            'siteProfile' => $this->siteProfile,
            'navSearch'   => $navSearchHtml,
        ];
        $navbarHtml = $navbar->renderHtml($navbarContext);

        // Build parallax
        $parallaxHtml = $parallax->renderHtml([]);

        // Build footer
        $footerHtml = $footer->renderHtml([
            'article'     => $article,
            'template'    => $template,
            'siteProfile' => $this->siteProfile,
        ]);

        // Related articles
        $relatedHtml = $this->renderRelatedArticles($article);

        // Assemble document
        $title = $this->e($article['meta_title'] ?: $article['title']);
        $desc  = $this->e($article['meta_description'] ?? '');
        $kw    = $this->e($article['meta_keywords'] ?? '');
        $css   = $template ? $this->e($template['css_class'] ?? '') : '';
        $url   = $this->e($article['published_url'] ?? '');

        // Resolve OG illustration → absolute URL pointing to /images/{id}/raw on same host as published_url.
        $ogImageMeta = '';
        $ogIll = $this->db->fetchOne(
            "SELECT image_id FROM seo_article_illustrations
             WHERE article_id = ? AND kind = 'og' AND status = 'ready' AND image_id IS NOT NULL",
            [(int)$articleId]
        );
        if ($ogIll && !empty($ogIll['image_id'])) {
            $base = '';
            $rawUrl = (string)($article['published_url'] ?? '');
            if ($rawUrl !== '') {
                $parts = parse_url($rawUrl);
                if (!empty($parts['scheme']) && !empty($parts['host'])) {
                    $base = $parts['scheme'] . '://' . $parts['host']
                          . (!empty($parts['port']) ? ':' . $parts['port'] : '');
                }
            }
            if ($base === '' && defined('SITE_BASE_URL')) {
                $base = rtrim(SITE_BASE_URL, '/');
            }
            $ogImgUrl = $base . '/images/' . (int)$ogIll['image_id'] . '/raw';
            $ogImageMeta = '<meta property="og:image" content="' . $this->e($ogImgUrl) . '">'
                . '<meta property="og:image:width" content="1200">'
                . '<meta property="og:image:height" content="630">'
                . '<meta name="twitter:card" content="summary_large_image">'
                . '<meta name="twitter:image" content="' . $this->e($ogImgUrl) . '">';
        }

        $chartJs = strpos($bodyHtml, 'chartjs-wrap') !== false
            ? '<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>'
            : '';

        $prismAssets = strpos($bodyHtml, 'language-') !== false
            ? '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/themes/prism-tomorrow.min.css">'
              . '<script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-core.min.js"></script>'
              . '<script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>'
            : '';

        $fonts = $this->theme->getFontLinks();

        // Token-based theme vars (new system) + brand overrides from profile.
        $themeService = new ThemeService($this->db);
        $resolved = $themeService->resolveForArticle($article, $this->siteProfile);
        $themeVarsCss = $themeService->renderCssVars($resolved['tokens']);
        $brandCss = $themeService->renderBrandOverrides($this->siteProfile);
        $themeVarsTag = $themeVarsCss !== '' ? '<style id="theme-vars">' . $themeVarsCss . '</style>' : '';
        $brandTag = $brandCss !== '' ? '<style id="brand-overrides">' . $brandCss . '</style>' : '';

        $logo = '/uploads/' . ($this->siteProfile['icon_path'] ?? '') ?: (defined('SEO_DEFAULT_LOGO_URL') ? SEO_DEFAULT_LOGO_URL : '');

        $themeClass = $this->theme->getBodyClass();
        $bodyClass = trim($css . ($themeClass !== '' ? ' ' . $themeClass : ''));

        $fullHtml = '<!DOCTYPE html><html lang="ru"><head>'
            . '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">'
            . '<title>' . $title . '</title>'
            . '<meta name="description" content="' . $desc . '">'
            . '<meta name="keywords" content="' . $kw . '">'
            . '<meta property="og:title" content="' . $title . '">'
            . '<meta property="og:description" content="' . $desc . '">'
            . '<meta property="og:type" content="article">'
            . '<meta property="og:url" content="' . $url . '">'
            . $ogImageMeta
            . '<link rel="canonical" href="' . $url . '">'
            . '<link rel="icon" href="' . $logo . '">'
            . $fonts
            . $chartJs
            . $prismAssets
            . $themeVarsTag
            . $assets->buildStyleTag()
            . $brandTag
            . '</head><body class="' . $bodyClass . '">'
            . $parallaxHtml
            . $navbarHtml
            . $tocHtml
            . '<main class="page-main" id="pageMain">' . $bodyHtml . $relatedHtml . '</main>'
            . $footerHtml
            . $assets->buildScriptTag()
            . '</body></html>';

        return $this->replaceLinkPlaceholders($fullHtml, $links);
    }

    /**
     * Render a single block with minimal page wrapping (for preview).
     */
    public function renderSingleBlockPreview(string $type, array $content): string
    {
        // Auto-select theme from profile for preview rendering
        if (!$this->themeOverridden && $this->siteProfile !== null && !empty($this->siteProfile['theme'])) {
            $this->theme = ThemeFactory::create($this->siteProfile['theme']);
        }

        $renderer = $this->registry->get($type);
        if (!$renderer) {
            return '<!-- unknown block: ' . htmlspecialchars($type) . ' -->';
        }

        $assets = new AssetCollector();
        $assets->addTheme($this->theme);
        $assets->addBlock($renderer);

        $blockHtml = $renderer->renderHtml($content, 'block-preview');

        $fonts = $this->theme->getFontLinks();
        $themeClass = $this->theme->getBodyClass();

        $chartJs = strpos($blockHtml, 'chartjs-wrap') !== false
            ? '<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>'
            : '';

        $prismAssets = strpos($blockHtml, 'language-') !== false
            ? '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/themes/prism-tomorrow.min.css">'
              . '<script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-core.min.js"></script>'
              . '<script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>'
            : '';

        return '<!DOCTYPE html><html lang="ru"><head>'
            . '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">'
            . $fonts
            . $chartJs
            . $prismAssets
            . $assets->buildStyleTag()
            . '<style>.reveal{opacity:1!important;transform:none!important}section{padding:24px 0!important}body{margin:0;padding:0}</style>'
            . '</head><body' . ($themeClass !== '' ? ' class="' . $themeClass . '"' : '') . '>'
            . $blockHtml
            . $assets->buildScriptTag()
            . '</body></html>';
    }

    /**
     * Render a single block without page wrapper.
     */
    public function renderSingleBlock(string $type, array $content): string
    {
        $renderer = $this->registry->get($type);
        if (!$renderer) {
            return "<!-- unknown block: {$type} -->\n";
        }
        return $renderer->renderHtml($content, 'block-' . uniqid());
    }

    private function renderRelatedArticles(array $article): string
    {
        $articleId = (int)$article['id'];
        $catalogId = (int)($article['catalog_id'] ?? 0);
        $profileId = (int)($article['profile_id'] ?? 0);
        $searchUrl = json_encode(SEO_SEARCH_SCRIPT);
        $trackUrl  = json_encode(SEO_TRACK_SCRIPT);
        $baseUrl   = json_encode(SEO_BASE_ART_URL);

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
        $html .= 'CID=' . $catalogId . ',';
        $html .= 'PID=' . $profileId . ';';
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
        $html .= 'var url=SEARCH+"?related=1&exclude="+AID+"&limit=4"+(CID?"&catalog_id="+CID:"")+(PID?"&profile_id="+PID:"");';
        $html .= 'fetch(url)';
        $html .=   '.then(function(r){return r.json();})';
        $html .=   '.then(function(d){renderCards(d.results||[]);})';
        $html .=   '.catch(function(){document.getElementById("relatedArticles").style.display="none";});';
        $html .= '})();';
        $html .= '</script>';

        return $html;
    }

    private function replaceLinkPlaceholders(string $html, array $links): string
    {
        $searchBase = SEO_SEARCH_SCRIPT;
        foreach ($links as $lnk) {
            $key = $lnk['key'] ?? '';
            if (!$key) continue;

            $url = $lnk['url'] ?? '';
            $tracked = (int)($lnk['is_tracked'] ?? 1);

            if ($tracked && $url !== '') {
                $finalUrl = htmlspecialchars(
                    $searchBase . '?link=' . urlencode($key),
                    ENT_QUOTES, 'UTF-8'
                );
            } elseif ($url !== '') {
                $finalUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            } else {
                continue;
            }

            $html = str_replace('{{link:' . $key . '}}', $finalUrl, $html);
        }
        $html = preg_replace('/\{\{link:[^}]+\}\}/', '#', $html);
        return $html;
    }

    private function loadArticle(int $id): array
    {
        $r = $this->db->fetchOne("SELECT * FROM " . SeoArticle::SEO_ARTICLE_TABLE . " WHERE id = ?", [$id]);
        if (!$r) throw new RuntimeException("Статья #{$id} не найдена");
        return $r;
    }

    private function loadBlocks(int $articleId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM " . SeoArticleBlock::SEO_ART_BLOCK_TABLE . " WHERE article_id = ? ORDER BY sort_order",
            [$articleId]
        );
    }

    private function loadLinks(int $articleId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM " . SeoLinkConstant::SEO_LINKS_TABLE . " WHERE article_id IS NULL OR article_id = ?",
            [$articleId]
        );
    }

    private function loadTemplate(int $id): array
    {
        return $this->db->fetchOne("SELECT * FROM " . SeoTemplate::TABLE . " WHERE id = ?", [$id]) ?? [];
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
