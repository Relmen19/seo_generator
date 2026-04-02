<?php

declare(strict_types=1);

namespace Seo\Controller;

use Seo\Entity\SeoPageStatDaily;

/*
   GET  /stats                     — общая сводка (дашборд)
   GET  /stats/{article_id}        — статистика по статье
   GET  /stats/{article_id}/daily  — дневная детализация
   POST /stats/aggregate           — запуск агрегации (крон)
 */
class StatsController extends AbstractController {

    public function dispatch(string $method, ?string $action, ?int $id): void {
        switch ($method) {
            case 'GET':
                if ($action === 'daily' && $id !== null) {
                    $this->daily($id);
                } elseif ($id !== null) {
                    $this->articleStats($id);
                } else {
                    $this->dashboard();
                }
                break;

            case 'POST':
                if ($action === 'aggregate') {
                    $this->aggregate();
                } else {
                    $this->methodNotAllowed();
                }
                break;

            default:
                $this->methodNotAllowed();
        }
    }


    private function dashboard(): void {
        $period = $this->getParam('period', '30'); // дней
        $days = max(1, min(365, (int)$period));
        $dateFrom = date('Y-m-d', strtotime("-{$days} days"));

        $profileJoin = '';
        $profileWhere = '';
        $profileParams = [];
        $profileId = $this->getParam('profile_id');
        if ($profileId !== null && $profileId !== '') {
            $profileJoin = ' JOIN seo_articles pa ON seo_page_stats_daily.article_id = pa.id';
            $profileWhere = ' AND pa.profile_id = :profile_id';
            $profileParams[':profile_id'] = (int)$profileId;
        }

        $totals = $this->db->fetchOne(
            "SELECT
                COALESCE(SUM(seo_page_stats_daily.views_total), 0) AS views_total,
                COALESCE(SUM(seo_page_stats_daily.views_unique), 0) AS views_unique,
                COALESCE(SUM(seo_page_stats_daily.views_bot), 0) AS views_bot
             FROM seo_page_stats_daily{$profileJoin}
             WHERE seo_page_stats_daily.date >= :d{$profileWhere}",
            array_merge([':d' => $dateFrom], $profileParams)
        );

        $statusWhere = ($profileId !== null && $profileId !== '') ? " WHERE profile_id = :profile_id" : '';
        $statuses = $this->db->fetchAll(
            "SELECT status, COUNT(*) AS cnt FROM seo_articles{$statusWhere} GROUP BY status",
            ($profileId !== null && $profileId !== '') ? [':profile_id' => (int)$profileId] : []
        );
        $statusMap = [];
        foreach ($statuses as $s) {
            $statusMap[$s['status']] = (int)$s['cnt'];
        }

        $topProfileWhere = ($profileId !== null && $profileId !== '') ? ' AND a.profile_id = :profile_id' : '';
        $topArticles = $this->db->fetchAll(
            "SELECT d.article_id, a.title, a.slug,
                    SUM(d.views_total) AS views_total,
                    SUM(d.views_unique) AS views_unique
             FROM seo_page_stats_daily d
             JOIN seo_articles a ON d.article_id = a.id
             WHERE d.date >= :d{$topProfileWhere}
             GROUP BY d.article_id, a.title, a.slug
             ORDER BY views_total DESC
             LIMIT 10",
            array_merge([':d' => $dateFrom], ($profileId !== null && $profileId !== '') ? [':profile_id' => (int)$profileId] : [])
        );

        $dailyJoin = $profileJoin;
        $dailyWhere = $profileWhere;
        $dailyChart = $this->db->fetchAll(
            "SELECT seo_page_stats_daily.date,
                    SUM(seo_page_stats_daily.views_total) AS views_total,
                    SUM(seo_page_stats_daily.views_unique) AS views_unique
             FROM seo_page_stats_daily{$dailyJoin}
             WHERE seo_page_stats_daily.date >= :d{$dailyWhere}
             GROUP BY seo_page_stats_daily.date
             ORDER BY seo_page_stats_daily.date",
            array_merge([':d' => $dateFrom], $profileParams)
        );

        $this->success([
            'period'       => $days,
            'date_from'    => $dateFrom,
            'totals'       => [
                'views_total'  => (int)$totals['views_total'],
                'views_unique' => (int)$totals['views_unique'],
                'views_bot'    => (int)$totals['views_bot'],
                'views_human'  => (int)$totals['views_total'] - (int)$totals['views_bot'],
            ],
            'statuses'     => $statusMap,
            'top_articles' => $topArticles,
            'daily_chart'  => $dailyChart,
        ]);
    }


    private function articleStats(int $articleId): void {
        $period = (int)$this->getParam('period', '30');
        $dateFrom = date('Y-m-d', strtotime("-{$period} days"));

        $totals = $this->db->fetchOne(
            "SELECT
                COALESCE(SUM(views_total), 0) AS views_total,
                COALESCE(SUM(views_unique), 0) AS views_unique,
                COALESCE(SUM(views_desktop), 0) AS views_desktop,
                COALESCE(SUM(views_mobile), 0) AS views_mobile,
                COALESCE(SUM(views_tablet), 0) AS views_tablet,
                COALESCE(SUM(views_bot), 0) AS views_bot
             FROM seo_page_stats_daily
             WHERE article_id = :aid AND date >= :d",
            [':aid' => $articleId, ':d' => $dateFrom]
        );

        $allTime = $this->db->fetchOne(
            "SELECT
                COALESCE(SUM(views_total), 0) AS views_total,
                COALESCE(SUM(views_unique), 0) AS views_unique
             FROM seo_page_stats_daily WHERE article_id = :aid",
            [':aid' => $articleId]
        );

        $recentDaily = $this->db->fetchAll(
            "SELECT date, views_total, views_unique, views_desktop, views_mobile, views_bot
             FROM seo_page_stats_daily
             WHERE article_id = :aid AND date >= :d
             ORDER BY date",
            [':aid' => $articleId, ':d' => date('Y-m-d', strtotime('-7 days'))]
        );

        $this->success([
            'article_id' => $articleId,
            'period'     => $period,
            'totals'     => $totals,
            'all_time'   => $allTime,
            'recent_daily' => $recentDaily,
        ]);
    }


    private function daily(int $articleId): void {
        $dateFrom = $this->getParam('from', date('Y-m-d', strtotime('-30 days')));
        $dateTo   = $this->getParam('to', date('Y-m-d'));

        $rows = $this->db->fetchAll(
            "SELECT * FROM seo_page_stats_daily
             WHERE article_id = :aid AND date BETWEEN :from AND :to
             ORDER BY date",
            [':aid' => $articleId, ':from' => $dateFrom, ':to' => $dateTo]
        );

        $items = array_map(fn(array $r) => (new SeoPageStatDaily($r))->toFullArray(), $rows);

        $this->success([
            'article_id' => $articleId,
            'from'       => $dateFrom,
            'to'         => $dateTo,
            'days'       => $items,
        ]);
    }


    private function aggregate(): void {
        $date = $this->getParam('date', date('Y-m-d', strtotime('-1 day')));

        $this->db->execute(
            "INSERT INTO seo_page_stats_daily
                (article_id, date, views_total, views_unique,
                 views_desktop, views_mobile, views_tablet, views_bot)
             SELECT
                article_id,
                DATE(visited_at) AS dt,
                COUNT(*) AS views_total,
                COUNT(DISTINCT ip) AS views_unique,
                SUM(device_type = 'desktop') AS views_desktop,
                SUM(device_type = 'mobile') AS views_mobile,
                SUM(device_type = 'tablet') AS views_tablet,
                SUM(device_type = 'bot') AS views_bot
             FROM seo_page_stats
             WHERE DATE(visited_at) = :dt
             GROUP BY article_id, DATE(visited_at)
             ON DUPLICATE KEY UPDATE
                views_total   = VALUES(views_total),
                views_unique  = VALUES(views_unique),
                views_desktop = VALUES(views_desktop),
                views_mobile  = VALUES(views_mobile),
                views_tablet  = VALUES(views_tablet),
                views_bot     = VALUES(views_bot)",
            [':dt' => $date]
        );

        // Подсчитать top_referers и top_countries
        $articles = $this->db->fetchAll(
            "SELECT DISTINCT article_id FROM seo_page_stats WHERE DATE(visited_at) = :dt",
            [':dt' => $date]
        );

        foreach ($articles as $art) {
            $aid = (int)$art['article_id'];

            $referers = $this->db->fetchAll(
                "SELECT referer, COUNT(*) AS cnt FROM seo_page_stats
                 WHERE article_id = :aid AND DATE(visited_at) = :dt AND referer IS NOT NULL AND referer != ''
                 GROUP BY referer ORDER BY cnt DESC LIMIT 5",
                [':aid' => $aid, ':dt' => $date]
            );

            $countries = $this->db->fetchAll(
                "SELECT country, COUNT(*) AS cnt FROM seo_page_stats
                 WHERE article_id = :aid AND DATE(visited_at) = :dt AND country IS NOT NULL
                 GROUP BY country ORDER BY cnt DESC LIMIT 5",
                [':aid' => $aid, ':dt' => $date]
            );

            $this->db->execute(
                "UPDATE seo_page_stats_daily
                 SET top_referers = :ref, top_countries = :ctr
                 WHERE article_id = :aid AND date = :dt",
                [
                    ':ref' => json_encode($referers, JSON_UNESCAPED_UNICODE),
                    ':ctr' => json_encode($countries, JSON_UNESCAPED_UNICODE),
                    ':aid' => $aid,
                    ':dt'  => $date,
                ]
            );
        }

        $this->success(['aggregated' => $date, 'articles_processed' => count($articles)]);
    }
}
