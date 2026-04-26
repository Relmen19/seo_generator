<?php

declare(strict_types=1);

namespace Seo\Service\Editorial\Fixer;

use Seo\Database;
use Seo\Service\Editorial\TextExtractor;
use Seo\Service\GptClient;
use Throwable;

class EmptyChartFixer implements FixerInterface
{
    private Database $db;
    private array $schemaCache = [];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function code(): string
    {
        return 'empty_chart';
    }

    public function fix(array $article, array $blocks, array $issues, GptClient $gpt): array
    {
        $dossierRaw = trim((string)($article['research_dossier'] ?? ''));
        if ($dossierRaw === '') return [];
        $dossier = json_decode($dossierRaw, true);
        if (!is_array($dossier) || empty($dossier)) return [];

        $blocksById = [];
        foreach ($blocks as $b) {
            if (isset($b['id'])) $blocksById[(int)$b['id']] = $b;
        }

        $updates = [];
        foreach ($issues as $i) {
            $blockId = (int)($i['block_id'] ?? 0);
            if ($blockId <= 0 || !isset($blocksById[$blockId])) continue;
            $block = $blocksById[$blockId];
            $type = (string)($block['type'] ?? '');
            if ($type === '') continue;

            $schema = $this->loadSchema($type);
            $content = TextExtractor::blockContent($block);

            try {
                $new = $this->callGpt($gpt, $article, $block, $content, $schema, $dossier);
            } catch (Throwable $e) {
                error_log("[EmptyChartFixer] block {$blockId} failed: " . $e->getMessage());
                continue;
            }
            if (!is_array($new) || empty($new)) continue;
            $updates[] = ['block_id' => $blockId, 'content' => $new];
        }
        return $updates;
    }

    private function loadSchema(string $type): ?array
    {
        if (array_key_exists($type, $this->schemaCache)) return $this->schemaCache[$type];
        $row = $this->db->fetchOne(
            'SELECT json_schema FROM seo_block_types WHERE code = ? LIMIT 1',
            [$type]
        );
        $schema = null;
        if ($row && !empty($row['json_schema'])) {
            $decoded = json_decode((string)$row['json_schema'], true);
            if (is_array($decoded)) $schema = $decoded;
        }
        $this->schemaCache[$type] = $schema;
        return $schema;
    }

    private function callGpt(GptClient $gpt, array $article, array $block, array $content, ?array $schema, array $dossier): array
    {
        $type = (string)($block['type'] ?? '');
        $name = (string)($block['name'] ?? '');
        $blockTopic = $name !== ''
            ? $name
            : trim((string)($content['title'] ?? $content['heading'] ?? ''));
        $articleTitle = (string)($article['title'] ?? '');
        $contentJson  = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $schemaJson   = $schema !== null
            ? json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            : '(нет схемы — следуй имеющимся ключам в content)';
        $dossierJson  = json_encode($this->trimDossier($dossier, $blockTopic), JSON_UNESCAPED_UNICODE);

        $messages = [
            ['role' => 'system', 'content' =>
                "Ты аналитик данных для SEO-блоков типа график/диаграмма. "
                . "Заполняешь поле data блока на основании research-досье. "
                . "Бери только реальные числа/бенчмарки, ничего не выдумывай. "
                . "Сохрани все имеющиеся ключи content (title, subtitle, caption и т.п.), "
                . "только дополни/обнови ключ с данными согласно schema. "
                . "Отвечай строго JSON-объектом — обновлённый content целиком."],
            ['role' => 'user', 'content' =>
                "Статья: {$articleTitle}\n"
                . "Блок ({$type}): {$blockTopic}\n\n"
                . "Schema блока:\n{$schemaJson}\n\n"
                . "Текущий content (поле данных пустое):\n{$contentJson}\n\n"
                . "Релевантные факты из research-досье:\n{$dossierJson}\n\n"
                . "Верни новый content JSON с заполненным полем данных."],
        ];

        $resp = $gpt->chatJson($messages, [
            'temperature' => 0.3,
            'max_tokens'  => 2000,
        ]);
        $data = $resp['data'] ?? null;
        return is_array($data) ? $data : [];
    }

    /**
     * Возвращаем подмножество фактов с числами, релевантных теме блока.
     * Если совпадений по ключевым словам нет — берём первые ~30 пунктов с цифрами.
     */
    private function trimDossier(array $dossier, string $topic): array
    {
        $needles = [];
        if ($topic !== '') {
            $tokens = preg_split('/\s+/u', mb_strtolower($topic, 'UTF-8')) ?: [];
            foreach ($tokens as $t) {
                if (mb_strlen($t) >= 4) $needles[] = $t;
            }
        }
        $matched = [];
        $numericFallback = [];
        foreach ($dossier as $item) {
            if (!is_array($item)) continue;
            $text = mb_strtolower(json_encode($item, JSON_UNESCAPED_UNICODE) ?: '', 'UTF-8');
            $hasNum = preg_match('/\d/u', $text) === 1;
            if (!$hasNum) continue;

            $hit = false;
            foreach ($needles as $n) {
                if (mb_strpos($text, $n) !== false) { $hit = true; break; }
            }
            if ($hit) $matched[] = $item;
            elseif (count($numericFallback) < 30) $numericFallback[] = $item;

            if (count($matched) >= 30) break;
        }
        return !empty($matched) ? $matched : $numericFallback;
    }
}
