<?php

declare(strict_types=1);

namespace Seo\Controller;

use Seo\Service\EditorialFixerService;
use Seo\Service\EditorialQaService;
use Throwable;

/*
   GET    /qa/{articleId}                  — list unresolved issues
   GET    /qa/{articleId}/all              — list all issues incl. resolved
   POST   /qa/{articleId}/run              — run all rules and persist
   POST   /qa/{articleId}/fix              — body: { "codes": ["repetition","banned_phrase","empty_chart"] }
   POST   /qa/{articleId}/resolve          — body|query: { "issue_id": N }
   GET    /qa/{articleId}/has-errors       — { has_errors: bool }

   Note: router.php parses only one $action segment, поэтому issue_id передаётся
   через query/JSON-body, а не path. Sync с фактическим поведением.
 */
class ArticleQaController extends AbstractController {

    public function dispatch(string $method, ?string $action, ?int $id): void {
        if ($id === null) {
            $this->error('Укажите article_id: /qa/{articleId}[/run|/resolve/{issueId}|/has-errors|/all]');
            return;
        }

        $svc = new EditorialQaService($this->db);

        try {
            if ($method === 'GET' && $action === null) {
                $this->success($svc->listIssues($id, true));
                return;
            }

            if ($method === 'GET' && $action === 'all') {
                $this->success($svc->listIssues($id, false));
                return;
            }

            if ($method === 'GET' && $action === 'has-errors') {
                $this->success(['has_errors' => $svc->hasBlockingErrors($id)]);
                return;
            }

            if ($method === 'POST' && $action === 'run') {
                $issues = $svc->runChecks($id);
                $this->success([
                    'issues'     => $issues,
                    'has_errors' => $svc->hasBlockingErrors($id),
                ]);
                return;
            }

            if ($method === 'POST' && $action === 'fix') {
                $body = $this->getJsonBody();
                $codes = [];
                if (isset($body['codes']) && is_array($body['codes'])) {
                    foreach ($body['codes'] as $c) {
                        $c = trim((string)$c);
                        if ($c !== '') $codes[] = $c;
                    }
                }
                $fixer = new EditorialFixerService($this->db);
                $report = $fixer->applyFixes($id, $codes);
                $issues = $svc->runChecks($id);
                $this->success([
                    'report'     => $report,
                    'issues'     => $issues,
                    'has_errors' => $svc->hasBlockingErrors($id),
                ]);
                return;
            }

            if ($method === 'POST' && $action === 'resolve') {
                $issueId = $this->getIntParam('issue_id', 0);
                if ($issueId <= 0) {
                    $body = $this->getJsonBody();
                    $issueId = (int)($body['issue_id'] ?? 0);
                }
                if ($issueId <= 0) {
                    $this->error('issue_id обязателен');
                    return;
                }
                $svc->resolveIssue($issueId);
                $this->success(['resolved' => $issueId]);
                return;
            }

            $this->methodNotAllowed();
        } catch (Throwable $e) {
            $this->error($e->getMessage(), 500);
        }
    }
}
