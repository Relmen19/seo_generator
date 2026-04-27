<?php

declare(strict_types=1);

namespace Seo\Service\Editorial\Fixer;

use Seo\Service\Editorial\TextExtractor;
use Seo\Service\GptClient;
use Throwable;

abstract class AbstractPhraseFixer implements FixerInterface
{
    public function fix(array $article, array $blocks, array $issues, GptClient $gpt): array
    {
        $phrases = $this->extractPhrases($issues);
        if (empty($phrases)) return [];

        $updates = [];
        foreach ($blocks as $b) {
            $blockId = isset($b['id']) ? (int)$b['id'] : 0;
            if ($blockId <= 0) continue;
            $content = TextExtractor::blockContent($b);
            if (empty($content)) continue;
            $text = mb_strtolower(TextExtractor::collectText($content), 'UTF-8');
            if ($text === '') continue;

            $hits = [];
            foreach ($phrases as $p) {
                if (mb_strpos($text, mb_strtolower($p, 'UTF-8')) !== false) $hits[] = $p;
            }
            if (empty($hits)) continue;

            try {
                $new = $this->rewriteBlock($gpt, $b, $content, $hits);
            } catch (Throwable $e) {
                \Seo\Service\Logger::warn(\Seo\Service\Logger::CHANNEL_EDITORIAL, static::class . ' block rewrite failed', ['block_id' => $blockId, 'error' => $e->getMessage()]);
                continue;
            }
            if (!is_array($new) || empty($new)) continue;
            $updates[] = ['block_id' => $blockId, 'content' => $new];
        }
        return $updates;
    }

    /** @return string[] unique phrases pulled from issue messages. */
    protected function extractPhrases(array $issues): array
    {
        $out = [];
        foreach ($issues as $i) {
            $msg = (string)($i['message'] ?? '');
            if (preg_match_all('/«([^»]+)»/u', $msg, $m) && !empty($m[1])) {
                foreach ($m[1] as $p) {
                    $p = trim($p);
                    if ($p !== '') $out[$p] = true;
                }
            }
        }
        return array_keys($out);
    }

    protected function rewriteBlock(GptClient $gpt, array $block, array $content, array $phrases): array
    {
        $type = (string)($block['type'] ?? '');
        $list = '';
        foreach ($phrases as $p) $list .= "  • {$p}\n";
        $contentJson = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $messages = [
            ['role' => 'system', 'content' =>
                "Ты редактор-стилист. Получаешь JSON одного блока SEO-статьи. "
                . "Перепиши тексты внутри так, чтобы полностью убрать запрещённые фразы/n-граммы из списка. "
                . "Сохрани смысл, тон и структуру JSON ключ-в-ключ. Не меняй ключи и схему массивов. "
                . "Если фраза встречается — переформулируй предложение другими словами. "
                . "Не добавляй новые ключи, не удаляй существующие. "
                . "Отвечай строго JSON-объектом с теми же полями, что и во входе."],
            ['role' => 'user', 'content' =>
                "Тип блока: {$type}\n"
                . "Запрещённые фразы:\n{$list}\n"
                . "Текущий content:\n{$contentJson}\n\n"
                . "Верни новый content JSON."],
        ];

        $resp = $gpt->chatJson($messages, [
            'temperature' => 0.5,
            'max_tokens'  => 2500,
        ]);
        $data = $resp['data'] ?? null;
        if (!is_array($data)) return [];
        return $data;
    }
}
