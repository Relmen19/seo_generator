<?php

declare(strict_types=1);

namespace Seo\Entity;

/**
 * Дневная агрегированная статистика.
 *
 * Таблица: seo_page_stats_daily
 * Считается крон-джобом из seo_page_stats.
 */
class SeoPageStatDaily extends AbstractEntity {

    protected int $articleId = 0;
    protected string $date = '';
    protected int $viewsTotal = 0;
    protected int $viewsUnique = 0;
    protected int $viewsDesktop = 0;
    protected int $viewsMobile = 0;
    protected int $viewsTablet = 0;
    protected int $viewsBot = 0;
    protected ?array $topReferers = null;
    protected ?array $topCountries = null;


    protected function hydrate(array $data): void {
        if (array_key_exists('article_id', $data)) {
            $this->articleId = (int)$data['article_id'];
        }
        if (array_key_exists('date', $data)) {
            $this->date = (string)$data['date'];
        }
        if (array_key_exists('views_total', $data)) {
            $this->viewsTotal = (int)$data['views_total'];
        }
        if (array_key_exists('views_unique', $data)) {
            $this->viewsUnique = (int)$data['views_unique'];
        }
        if (array_key_exists('views_desktop', $data)) {
            $this->viewsDesktop = (int)$data['views_desktop'];
        }
        if (array_key_exists('views_mobile', $data)) {
            $this->viewsMobile = (int)$data['views_mobile'];
        }
        if (array_key_exists('views_tablet', $data)) {
            $this->viewsTablet = (int)$data['views_tablet'];
        }
        if (array_key_exists('views_bot', $data)) {
            $this->viewsBot = (int)$data['views_bot'];
        }
        if (array_key_exists('top_referers', $data)) {
            $this->topReferers = $this->decodeJson($data['top_referers']);
        }
        if (array_key_exists('top_countries', $data)) {
            $this->topCountries = $this->decodeJson($data['top_countries']);
        }
    }


    public function toArray(): array {
        return [
            'article_id'    => $this->articleId,
            'date'          => $this->date,
            'views_total'   => $this->viewsTotal,
            'views_unique'  => $this->viewsUnique,
            'views_desktop' => $this->viewsDesktop,
            'views_mobile'  => $this->viewsMobile,
            'views_tablet'  => $this->viewsTablet,
            'views_bot'     => $this->viewsBot,
            'top_referers'  => $this->encodeJson($this->topReferers),
            'top_countries' => $this->encodeJson($this->topCountries),
        ];
    }

    /**
     * Процент уникальных от общего.
     */
    public function getUniquePercent(): float {
        if ($this->viewsTotal === 0) {
            return 0.0;
        }
        return round(($this->viewsUnique / $this->viewsTotal) * 100, 1);
    }

    /**
     * Просмотры людей (без ботов).
     */
    public function getHumanViews(): int {
        return $this->viewsTotal - $this->viewsBot;
    }


    public function getArticleId(): int {
        return $this->articleId;
    }

    public function setArticleId(int $articleId): self {
        $this->articleId = $articleId;
        return $this;
    }

    public function getDate(): string {
        return $this->date;
    }

    public function setDate(string $date): self {
        $this->date = $date;
        return $this;
    }

    public function getViewsTotal(): int {
        return $this->viewsTotal;
    }

    public function setViewsTotal(int $viewsTotal): self {
        $this->viewsTotal = $viewsTotal;
        return $this;
    }

    public function getViewsUnique(): int {
        return $this->viewsUnique;
    }

    public function setViewsUnique(int $viewsUnique): self {
        $this->viewsUnique = $viewsUnique;
        return $this;
    }

    public function getViewsDesktop(): int {
        return $this->viewsDesktop;
    }

    public function setViewsDesktop(int $viewsDesktop): self {
        $this->viewsDesktop = $viewsDesktop;
        return $this;
    }

    public function getViewsMobile(): int {
        return $this->viewsMobile;
    }

    public function setViewsMobile(int $viewsMobile): self {
        $this->viewsMobile = $viewsMobile;
        return $this;
    }

    public function getViewsTablet(): int {
        return $this->viewsTablet;
    }

    public function setViewsTablet(int $viewsTablet): self {
        $this->viewsTablet = $viewsTablet;
        return $this;
    }

    public function getViewsBot(): int {
        return $this->viewsBot;
    }

    public function setViewsBot(int $viewsBot): self {
        $this->viewsBot = $viewsBot;
        return $this;
    }

    public function getTopReferers(): ?array {
        return $this->topReferers;
    }

    public function setTopReferers(?array $topReferers): self {
        $this->topReferers = $topReferers;
        return $this;
    }

    public function getTopCountries(): ?array {
        return $this->topCountries;
    }

    public function setTopCountries(?array $topCountries): self {
        $this->topCountries = $topCountries;
        return $this;
    }
}
