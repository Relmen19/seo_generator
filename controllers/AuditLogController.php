<?php

declare(strict_types=1);

namespace Seo\Controller;

use Seo\Entity\SeoAuditLog;

/*
    GET /audit-log      — список с фильтрами и пагинацией
    GET /audit-log/{id} — одна запись
 */
class AuditLogController extends AbstractController {

    public function dispatch(string $method, ?string $action, ?int $id): void {
        if ($method !== 'GET') $this->methodNotAllowed();

        $id !== null ? $this->show($id) : $this->index();
    }

    private function index(): void {
        $page    = $this->getPage();
        $perPage = $this->getPerPage(50);

        $where  = '1=1';
        $params = [];

        $entityType = $this->getParam('entity_type');
        if ($entityType !== null) {
            $where .= ' AND entity_type = :entity_type';
            $params[':entity_type'] = $entityType;
        }

        $entityId = $this->getParam('entity_id');
        if ($entityId !== null) {
            $where .= ' AND entity_id = :entity_id';
            $params[':entity_id'] = (int)$entityId;
        }

        $actionFilter = $this->getParam('action');
        if ($actionFilter !== null) {
            $where .= ' AND action = :action';
            $params[':action'] = $actionFilter;
        }

        $actor = $this->getParam('actor');
        if ($actor !== null) {
            $where .= ' AND actor = :actor';
            $params[':actor'] = $actor;
        }

        $dateFrom = $this->getParam('from');
        if ($dateFrom !== null) {
            $where .= ' AND created_at >= :from';
            $params[':from'] = $dateFrom;
        }

        $dateTo = $this->getParam('to');
        if ($dateTo !== null) {
            $where .= ' AND created_at <= :to';
            $params[':to'] = $dateTo . ' 23:59:59';
        }

        $total = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM " . SeoAuditLog::SEO_AUDIT_LOG_TABLE . " WHERE {$where}",
            $params
        );

        $rows = $this->db->fetchAll(
            "SELECT * FROM " . SeoAuditLog::SEO_AUDIT_LOG_TABLE . " WHERE {$where}
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset",
            array_merge($params, [':limit' => $perPage, ':offset' => ($page - 1) * $perPage])
        );

        $items = array_map(fn(array $r) => (new SeoAuditLog($r))->toFullArray(), $rows);

        $this->paginated($items, $total, $page, $perPage);
    }

    private function show(int $id): void {
        $row = $this->db->fetchOne("SELECT * FROM " . SeoAuditLog::SEO_AUDIT_LOG_TABLE . " WHERE id = :id", [':id' => $id]);
        if ($row === null) $this->notFound('Запись аудит-лога');

        $this->success((new SeoAuditLog($row))->toFullArray());
    }
}
