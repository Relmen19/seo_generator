<?php

declare(strict_types=1);

namespace Seo\Service\Editorial;

use Seo\Database;
use Seo\Service\GptClient;
use Seo\Service\TokenUsageLogger;
use Throwable;

/**
 * AI-driven coherence review. Asks a cheap model to score the article 0-10,
 * spot logical gaps / topic jumps / duplicated meaning, and persists results
 * as info|warn issues with code 'ai_review'.
 */
class AiReviewService
{
    private Database $db;
    private GptClient $gpt;
    private string $model;

    public function __construct(?Database $db = null, ?GptClient $gpt = null, string $model = 'gpt-4.1-mini')
    {
        $this->db    = $db  ?? Database::getInstance();
        $this->gpt   = $gpt ?? new GptClient();
        $this->model = $model;
    }

    /**
     * @return array{coherence:int, gaps:array, notes:string}|null
     */
    public function review(array $article, array $blocks): ?array
    {
        $articleId = (int)$article['id'];
        $body = $this->renderBody($blocks);
        if ($body === '') return null;

        $title = (string)($article['title'] ?? '');
        $messages = [
            ['role' => 'system', 'content' =>
                "Ты главный редактор. Оцени связность статьи 0-10. "
                . "Найди логические разрывы, дублирования смысла, скачки темы. "
                . "Отвечай строго JSON: "
                . "{\"coherence\":0-10,\"gaps\":[\"коротко: что не так\"],\"notes\":\"итог 1-2 предложения\"}. "
                . "gaps — до 5 элементов, каждый ≤ 200 символов."],
            ['role' => 'user', 'content' => "Заголовок: {$title}\n\n{$body}"],
        ];

        $this->gpt->setLogContext([
            'category'    => TokenUsageLogger::CATEGORY_ARTICLE_AI_REVIEW,
            'operation'   => 'ai_review',
            'profile_id'  => $article['profile_id'] ?? null,
            'entity_type' => 'article',
            'entity_id'   => $articleId,
        ]);

        try {
            $resp = $this->gpt->chatJson($messages, [
                'model'       => $this->model,
                'temperature' => 0.2,
                'max_tokens'  => 700,
            ]);
        } catch (Throwable $e) {
            error_log('[AiReviewService] GPT call failed: ' . $e->getMessage());
            $this->insertIssue($articleId, 'warn', 'AI-ревью упал: ' . $e->getMessage());
            return null;
        }

        $data = $resp['data'] ?? null;
        if (!is_array($data)) return null;

        $coherence = (int)($data['coherence'] ?? 0);
        if ($coherence < 0) $coherence = 0;
        if ($coherence > 10) $coherence = 10;
        $gaps = [];
        if (isset($data['gaps']) && is_array($data['gaps'])) {
            foreach ($data['gaps'] as $g) {
                $g = trim((string)$g);
                if ($g !== '') $gaps[] = mb_substr($g, 0, 200);
                if (count($gaps) >= 5) break;
            }
        }
        $notes = trim((string)($data['notes'] ?? ''));
        if (mb_strlen($notes) > 400) $notes = mb_substr($notes, 0, 400);

        $severity = $coherence < 6 ? 'warn' : 'info';
        $summary = "AI-ревью: связность {$coherence}/10";
        if ($notes !== '') $summary .= ". {$notes}";
        $this->insertIssue($articleId, $severity, $summary);

        foreach ($gaps as $g) {
            $this->insertIssue($articleId, 'info', 'Разрыв: ' . $g);
        }

        return ['coherence' => $coherence, 'gaps' => $gaps, 'notes' => $notes];
    }

    private function renderBody(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $b) {
            $content = TextExtractor::blockContent($b);
            $text = TextExtractor::collectText($content);
            $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
            if ($text === '') continue;
            if (mb_strlen($text) > 1200) $text = mb_substr($text, 0, 1200) . '…';
            $type = (string)($b['block_type'] ?? '');
            $parts[] = '[' . $type . '] ' . $text;
        }
        return implode("\n\n", $parts);
    }

    private function insertIssue(int $articleId, string $severity, string $message): void
    {
        try {
            $this->db->execute(
                'INSERT INTO seo_article_issues (article_id, severity, code, message, block_id)
                 VALUES (?, ?, ?, ?, ?)',
                [$articleId, $severity, 'ai_review', $message, null]
            );
        } catch (Throwable $e) {
            error_log('[AiReviewService] insert issue failed: ' . $e->getMessage());
        }
    }
}
