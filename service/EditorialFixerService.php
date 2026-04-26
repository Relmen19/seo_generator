<?php

declare(strict_types=1);

namespace Seo\Service;

use RuntimeException;
use Throwable;
use Seo\Database;
use Seo\Entity\SeoArticleBlock;
use Seo\Entity\SeoAuditLog;
use Seo\Service\Editorial\Fixer\BannedPhraseFixer;
use Seo\Service\Editorial\Fixer\EmptyChartFixer;
use Seo\Service\Editorial\Fixer\FixerInterface;
use Seo\Service\Editorial\Fixer\RepetitionFixer;

class EditorialFixerService
{
    private Database $db;
    private GptClient $gpt;
    /** @var array<string, FixerInterface> */
    private array $fixers;

    /** @param FixerInterface[]|null $fixers */
    public function __construct(?Database $db = null, ?GptClient $gpt = null, ?array $fixers = null)
    {
        $this->db  = $db  ?? Database::getInstance();
        $this->gpt = $gpt ?? new GptClient();

        $list = $fixers ?? [
            new RepetitionFixer(),
            new BannedPhraseFixer(),
            new EmptyChartFixer($this->db),
        ];
        $this->fixers = [];
        foreach ($list as $f) {
            if ($f instanceof FixerInterface) $this->fixers[$f->code()] = $f;
        }
    }

    /**
     * Apply fixers to current unresolved issues.
     *
     * @param int      $articleId
     * @param string[] $codes  Restrict to these issue codes (empty = all supported).
     * @return array  {fixed_blocks, by_code: {code: {issues, blocks_updated, errors}}}
     */
    public function applyFixes(int $articleId, array $codes = []): array
    {
        $article = $this->db->fetchOne('SELECT * FROM seo_articles WHERE id = ?', [$articleId]);
        if (!$article) throw new RuntimeException("Статья #{$articleId} не найдена");

        $blocks = $this->db->fetchAll(
            'SELECT * FROM seo_article_blocks WHERE article_id = ? ORDER BY sort_order',
            [$articleId]
        );

        $issues = $this->db->fetchAll(
            'SELECT id, severity, code, message, block_id
             FROM seo_article_issues
             WHERE article_id = ? AND resolved_at IS NULL',
            [$articleId]
        );

        $byCode = [];
        foreach ($issues as $i) {
            $code = (string)($i['code'] ?? '');
            if ($code === '') continue;
            if (!empty($codes) && !in_array($code, $codes, true)) continue;
            if (!isset($this->fixers[$code])) continue;
            $byCode[$code][] = $i;
        }

        $report = ['fixed_blocks' => 0, 'by_code' => []];
        $updatedBlockIds = [];

        $this->gpt->setLogContext([
            'category'    => TokenUsageLogger::CATEGORY_ARTICLE_FIXER,
            'profile_id'  => $article['profile_id'] ?? null,
            'entity_type' => 'article',
            'entity_id'   => $articleId,
        ]);

        try {
            foreach ($byCode as $code => $codeIssues) {
                $fixer = $this->fixers[$code];
                $this->gpt->setLogContext(array_merge($this->gpt->getLogContext(), [
                    'operation' => 'fix_' . $code,
                ]));

                $fixerReport = ['issues' => count($codeIssues), 'blocks_updated' => 0, 'errors' => 0];

                try {
                    $updates = $fixer->fix($article, $blocks, $codeIssues, $this->gpt);
                } catch (Throwable $e) {
                    error_log("[EditorialFixerService] fixer {$code} failed: " . $e->getMessage());
                    $fixerReport['errors']++;
                    $report['by_code'][$code] = $fixerReport;
                    continue;
                }

                foreach ($updates as $u) {
                    $blockId = (int)($u['block_id'] ?? 0);
                    $newContent = $u['content'] ?? null;
                    if ($blockId <= 0 || !is_array($newContent) || empty($newContent)) continue;
                    try {
                        $this->db->update(
                            SeoArticleBlock::SEO_ART_BLOCK_TABLE,
                            'id = :bid AND article_id = :aid',
                            ['content' => json_encode($newContent, JSON_UNESCAPED_UNICODE)],
                            [':bid' => $blockId, ':aid' => $articleId]
                        );
                        $fixerReport['blocks_updated']++;
                        $updatedBlockIds[$blockId] = true;
                    } catch (Throwable $e) {
                        error_log("[EditorialFixerService] update block {$blockId} failed: " . $e->getMessage());
                        $fixerReport['errors']++;
                    }
                }
                $report['by_code'][$code] = $fixerReport;
            }
        } finally {
            $this->gpt->clearLogContext();
        }

        $report['fixed_blocks'] = count($updatedBlockIds);

        if ($report['fixed_blocks'] > 0 || !empty($report['by_code'])) {
            $this->writeAudit($articleId, $report);
        }

        return $report;
    }

    public function supportedCodes(): array
    {
        return array_keys($this->fixers);
    }

    private function writeAudit(int $articleId, array $report): void
    {
        try {
            $this->db->insert(
                SeoAuditLog::SEO_AUDIT_LOG_TABLE,
                SeoAuditLog::articleAction(
                    $articleId,
                    'fix',
                    'editorial_fixer',
                    ['report' => $report]
                )->toArray()
            );
        } catch (Throwable $e) {
            error_log('[EditorialFixerService] audit insert failed: ' . $e->getMessage());
        }
    }
}
