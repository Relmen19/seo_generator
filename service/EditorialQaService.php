<?php

declare(strict_types=1);

namespace Seo\Service;

use RuntimeException;
use Seo\Database;
use Seo\Service\Editorial\Rule\BannedPhrasesRule;
use Seo\Service\Editorial\Rule\BrokenLinksRule;
use Seo\Service\Editorial\Rule\EmptyChartRule;
use Seo\Service\Editorial\Rule\MinWordsRule;
use Seo\Service\Editorial\Rule\RepetitionRule;
use Seo\Service\Editorial\Rule\RuleInterface;
use Seo\Service\Editorial\Rule\UnknownInDossierRule;

class EditorialQaService
{
    private Database $db;
    /** @var RuleInterface[] */
    private array $rules;

    public function __construct(Database $db, array $rules = null)
    {
        $this->db = $db;
        $this->rules = $rules ?? [
            new MinWordsRule(),
            new BannedPhrasesRule(),
            new EmptyChartRule(),
            new RepetitionRule(),
            new UnknownInDossierRule(),
            new BrokenLinksRule(),
        ];
    }

    /**
     * Run all rules and persist issues. Returns the resulting issue list.
     */
    public function runChecks(int $articleId): array
    {
        $article = $this->db->fetchOne('SELECT * FROM seo_articles WHERE id = ?', [$articleId]);
        if (!$article) throw new RuntimeException("Статья #{$articleId} не найдена");

        $blocks = $this->db->fetchAll(
            'SELECT * FROM seo_article_blocks WHERE article_id = ? ORDER BY sort_order',
            [$articleId]
        );

        $this->db->execute(
            'UPDATE seo_article_issues SET resolved_at = CURRENT_TIMESTAMP
             WHERE article_id = ? AND resolved_at IS NULL',
            [$articleId]
        );

        $all = [];
        foreach ($this->rules as $rule) {
            $issues = $rule->run($article, $blocks);
            foreach ($issues as $i) {
                $this->db->execute(
                    'INSERT INTO seo_article_issues (article_id, severity, code, message, block_id)
                     VALUES (?, ?, ?, ?, ?)',
                    [
                        $articleId,
                        $i['severity'] ?? 'warn',
                        $i['code']     ?? 'unknown',
                        $i['message']  ?? '',
                        $i['block_id'] ?? null,
                    ]
                );
                $all[] = $i;
            }
        }

        return $this->listIssues($articleId, true);
    }

    public function listIssues(int $articleId, bool $unresolvedOnly = true): array
    {
        $sql = 'SELECT id, article_id, severity, code, message, block_id, created_at, resolved_at
                FROM seo_article_issues WHERE article_id = ?';
        if ($unresolvedOnly) $sql .= ' AND resolved_at IS NULL';
        $sql .= ' ORDER BY FIELD(severity, "error", "warn", "info"), created_at DESC';
        return $this->db->fetchAll($sql, [$articleId]);
    }

    public function resolveIssue(int $issueId): void
    {
        $this->db->execute(
            'UPDATE seo_article_issues SET resolved_at = CURRENT_TIMESTAMP
             WHERE id = ? AND resolved_at IS NULL',
            [$issueId]
        );
    }

    public function hasBlockingErrors(int $articleId): bool
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS cnt FROM seo_article_issues
             WHERE article_id = ? AND resolved_at IS NULL AND severity = "error"',
            [$articleId]
        );
        return (int)($row['cnt'] ?? 0) > 0;
    }
}
