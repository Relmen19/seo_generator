<?php

declare(strict_types=1);

namespace Seo\Controller;

use Seo\Entity\SeoArticle;
use Seo\Entity\SeoTemplate;
use Seo\Entity\SeoTemplateBlock;
use Seo\Service\TemplateGeneratorService;

/*
    GET    /templates                       — список шаблонов
    GET    /templates/{id}                  — шаблон с блоками
    POST   /templates                       — создать шаблон
    PUT    /templates/{id}                  — обновить шаблон
    DELETE /templates/{id}                  — удалить шаблон
    POST   /templates/{id}/blocks           — добавить блок в шаблон
    PUT    /templates/{id}/blocks           — обновить блок шаблона (block_id в теле)
    DELETE /templates/{id}/blocks           — удалить блок (block_id в теле)
    POST   /templates/{id}/ai-review        — AI-ревью шаблона (оценка + улучшения)
    POST   /templates/{id}/ai-apply         — применить данные шаблона (после ревью/отката)
    POST   /templates/{id}/ai-regenerate-sse — перегенерировать шаблон через SSE
 */
class TemplateController extends AbstractController {

    public function dispatch(string $method, ?string $action, ?int $id): void {

        if ($action === 'blocks' && $id !== null) {
            $this->dispatchBlocks($method, $id);
            return;
        }
        if ($action === 'ai-review' && $id !== null && $method === 'POST') {
            $this->aiReview($id);
            return;
        }
        if ($action === 'ai-apply' && $id !== null && $method === 'POST') {
            $this->aiApply($id);
            return;
        }
        if ($action === 'ai-regenerate-sse' && $id !== null && $method === 'POST') {
            $this->aiRegenerateSse($id);
            return;
        }

        switch ($method) {
            case 'GET':
                $id !== null ? $this->show($id) : $this->index();
                break;
            case 'POST':
                $this->create();
                break;
            case 'PUT':
            case 'PATCH':
                $id !== null ? $this->update($id) : $this->error('ID обязателен');
                break;
            case 'DELETE':
                $id !== null ? $this->delete($id) : $this->error('ID обязателен');
                break;
            default:
                $this->methodNotAllowed();
        }
    }


    private function index(): void {
        $onlyActive = $this->getParam('active');

        $where = '1=1';
        $params = [];
        if ($onlyActive === '1') $where .= ' AND is_active = 1';

        $profileId = $this->getParam('profile_id');
        if ($profileId !== null && $profileId !== '') {
            $where .= ' AND (profile_id = :profile_id OR profile_id IS NULL)';
            $params[':profile_id'] = (int)$profileId;
        }

        $rows = $this->db->fetchAll("SELECT * FROM " . SeoTemplate::TABLE . " WHERE {$where} ORDER BY name", $params);

        $items = array_map(function (array $row) {
            $tpl = new SeoTemplate($row);
            $tpl->setBlocks($this->loadBlocks($tpl->getId()));
            return $tpl->toFullArray();
        }, $rows);

        $this->success($items);
    }

    private function show(int $id): void {
        $row = $this->db->fetchOne("SELECT * FROM " . SeoTemplate::TABLE . " WHERE id = :id", [':id' => $id]);
        if ($row === null) $this->notFound('Шаблон');

        $tpl = new SeoTemplate($row);
        $tpl->setBlocks($this->loadBlocks($id));

        $data = $tpl->toFullArray();
        $data['articles_count'] = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM " . SeoArticle::SEO_ARTICLE_TABLE ." WHERE template_id = :tid",
            [':tid' => $id]);

        $this->success($data);
    }

    private function create(): void {
        $data = $this->getJsonBody();
        $this->abortIfErrors($this->validateRequired($data, ['name', 'slug']));

        $tpl = new SeoTemplate($data);

        $this->db->transaction(function () use ($tpl, $data) {
            $newId = $this->db->insert(SeoTemplate::TABLE, $tpl->toArray());
            $tpl->setId($newId);

            if (!empty($data['blocks']) && is_array($data['blocks'])) {
                foreach ($data['blocks'] as $i => $blockData) {
                    $block = new SeoTemplateBlock($blockData);
                    $block->setTemplateId($newId);
                    if (!isset($blockData['sort_order'])) {
                        $block->setSortOrder($i + 1);
                    }
                    $this->db->insert(SeoTemplateBlock::SEO_TEMPLATE_BLOCK_TABLE, $block->toArray());
                }
            }
        });

        $tpl->setBlocks($this->loadBlocks($tpl->getId()));
        $this->success($tpl->toFullArray(), 201);
    }

    private function update(int $id): void {
        $existing = $this->db->fetchOne("SELECT * FROM " . SeoTemplate::TABLE . " WHERE id = :id", [':id' => $id]);
        if ($existing === null) $this->notFound('Шаблон');

        $data = $this->getJsonBody();
        $tpl = new SeoTemplate($existing);
        $tpl->fromArray($data);

        $this->db->update(SeoTemplate::TABLE, 'id = :id', $tpl->toArray(), [':id' => $id]);
        $tpl->setBlocks($this->loadBlocks($id));

        $this->success($tpl->toFullArray());
    }

    private function delete(int $id): void {
        $existing = $this->db->fetchOne("SELECT * FROM " . SeoTemplate::TABLE . " WHERE id = :id", [':id' => $id]);
        if ($existing === null) $this->notFound('Шаблон');

        $articlesCount = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM " . SeoArticle::SEO_ARTICLE_TABLE ." WHERE template_id = :tid",
            [':tid' => $id]);
        if ($articlesCount > 0) $this->error("Шаблон используется в {$articlesCount} статьях. Сначала переведите статьи на другой шаблон.", 409);

        $this->db->delete(SeoTemplate::TABLE, 'id = :id', [':id' => $id]);
        $this->success(['deleted' => true]);
    }

    private function dispatchBlocks(string $method, int $templateId): void {
        switch ($method) {
            case 'GET':
                $blocks = $this->loadBlocks($templateId);
                $this->success(array_map(fn(SeoTemplateBlock $b) => $b->toFullArray(), $blocks));
                break;

            case 'POST':
                $this->addBlock($templateId);
                break;

            case 'PUT':
            case 'PATCH':
                $this->updateBlock($templateId);
                break;

            case 'DELETE':
                $this->deleteBlock($templateId);
                break;

            default:
                $this->methodNotAllowed();
        }
    }

    private function addBlock(int $templateId): void {
        $data = $this->getJsonBody();
        $this->abortIfErrors($this->validateRequired($data, ['type', 'name']));

        $block = new SeoTemplateBlock($data);
        $block->setTemplateId($templateId);

        if (!isset($data['sort_order'])) {
            $maxSort = (int)$this->db->fetchColumn(
                "SELECT COALESCE(MAX(sort_order), 0) FROM " . SeoTemplateBlock::SEO_TEMPLATE_BLOCK_TABLE . " WHERE template_id = :tid",
                [':tid' => $templateId]
            );
            $block->setSortOrder($maxSort + 1);
        }

        $newId = $this->db->insert(SeoTemplateBlock::SEO_TEMPLATE_BLOCK_TABLE, $block->toArray());
        $block->setId($newId);

        $this->success($block->toFullArray(), 201);
    }

    private function updateBlock(int $templateId): void {
        $data = $this->getJsonBody();
        $blockId = (int)($data['block_id'] ?? $data['id'] ?? 0);
        if ($blockId === 0) $this->error('block_id обязателен');

        $existing = $this->db->fetchOne(
            "SELECT * FROM " . SeoTemplateBlock::SEO_TEMPLATE_BLOCK_TABLE . " WHERE id = :id AND template_id = :tid",
            [':id' => $blockId, ':tid' => $templateId]
        );
        if ($existing === null) $this->notFound('Блок шаблона');

        $block = new SeoTemplateBlock($existing);
        $block->fromArray($data);

        $this->db->update(SeoTemplateBlock::SEO_TEMPLATE_BLOCK_TABLE, 'id = :id', $block->toArray(), [':id' => $blockId]);
        $this->success($block->toFullArray());
    }

    private function deleteBlock(int $templateId): void {
        $data = $this->getJsonBody();
        $blockId = (int)($data['block_id'] ?? $data['id'] ?? 0);
        if ($blockId === 0) $this->error('block_id обязателен');

        $deleted = $this->db->delete(
            SeoTemplateBlock::SEO_TEMPLATE_BLOCK_TABLE,
            'id = :id AND template_id = :tid',
            [':id' => $blockId, ':tid' => $templateId]
        );

        if ($deleted === 0) $this->notFound('Блок шаблона');

        $this->success(['deleted' => true]);
    }

    // ── AI endpoints ──────────────────────────────────────────

    private function aiReview(int $id): void {
        $data = $this->getJsonBody();
        $service = new TemplateGeneratorService();
        $result = $service->reviewExistingTemplate($id, [
            'model' => $data['model'] ?? null,
        ]);
        $this->success($result);
    }

    private function aiApply(int $id): void {
        $data = $this->getJsonBody();
        $template = $data['template'] ?? null;
        if (!$template || !is_array($template)) {
            $this->error('Поле template обязательно', 422);
        }

        $service = new TemplateGeneratorService();
        $service->applyTemplateData($id, $template);

        // Return updated template with blocks
        $row = $this->db->fetchOne(
            "SELECT * FROM " . SeoTemplate::TABLE . " WHERE id = :id",
            [':id' => $id]
        );
        $tpl = new SeoTemplate($row);
        $tpl->setBlocks($this->loadBlocks($id));
        $this->success($tpl->toFullArray());
    }

    private function aiRegenerateSse(int $id): void {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        while (ob_get_level()) {
            ob_end_flush();
        }
        ob_implicit_flush();

        $body = [];
        $raw = file_get_contents('php://input');
        if ($raw) {
            $body = json_decode($raw, true) ?? [];
        }

        $purpose = trim((string)($body['purpose'] ?? ''));
        if ($purpose === '') {
            echo "event: error\n";
            echo "data: " . json_encode(['message' => 'Поле purpose обязательно'], JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
            exit;
        }

        try {
            $service = new TemplateGeneratorService();
            $service->regenerateTemplateSSE($id, $purpose, [
                'model' => $body['model'] ?? null,
                'hints' => $body['hints'] ?? null,
            ]);
        } catch (\Throwable $e) {
            echo "event: error\n";
            echo "data: " . json_encode(['message' => $e->getMessage()], JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
        }

        exit;
    }

    private function loadBlocks(int $templateId): array {
        $rows = $this->db->fetchAll(
            "SELECT * FROM " . SeoTemplateBlock::SEO_TEMPLATE_BLOCK_TABLE . " WHERE template_id = :tid ORDER BY sort_order",
            [':tid' => $templateId]
        );
        return array_map(fn(array $r) => new SeoTemplateBlock($r), $rows);
    }
}
