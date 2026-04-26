<?php

declare(strict_types=1);

namespace Seo\Controller;

use Seo\Entity\SeoIntentType;
use Seo\Entity\SeoSiteProfile;
use Seo\Enum\BriefPrompt;
use Seo\Service\GptClient;
use Seo\Service\TemplateGeneratorService;
use Seo\Service\TokenUsageLogger;

/*
   GET    /profiles                              — list all profiles
   GET    /profiles/{id}                         — single profile
   POST   /profiles                              — create profile
   PUT    /profiles/{id}                         — update profile
   DELETE /profiles/{id}                         — delete profile
   GET    /profiles/{id}/stats                   — profile statistics (counts)
   POST   /profiles/{id}/icon                    — upload profile icon
   GET    /profiles/{id}/icon                    — serve profile icon
   DELETE /profiles/{id}/icon                    — remove profile icon
   POST   /profiles/{id}/generate-templates      — GPT-generate template proposals
   POST   /profiles/{id}/save-templates          — save confirmed template proposals
   POST   /profiles/generate-from-description    — GPT-generate profile fields from text description
 */
class SiteProfileController extends AbstractController {

    public function dispatch(string $method, ?string $action, ?int $id): void {
        if ($id !== null && $action === 'stats' && $method === 'GET') {
            $this->stats($id);
            return;
        }
        if ($id !== null && $action === 'token-usage' && $method === 'GET') {
            $this->tokenUsage($id);
            return;
        }
        if ($id !== null && $action === 'icon') {
            switch ($method) {
                case 'POST':   $this->uploadIcon($id); break;
                case 'GET':    $this->serveIcon($id); break;
                case 'DELETE': $this->deleteIcon($id); break;
                default:       $this->methodNotAllowed();
            }
            return;
        }
        if ($id !== null && $action === 'generate-templates' && $method === 'POST') {
            $this->generateTemplates($id);
            return;
        }
        if ($id !== null && $action === 'save-templates' && $method === 'POST') {
            $this->saveTemplates($id);
            return;
        }
        if ($id !== null && $action === 'generate-template-sse' && $method === 'POST') {
            $this->generateTemplateSse($id);
            return;
        }
        // POST /profiles/generate-from-description (action-only, no id)
        if ($action === 'generate-from-description' && $method === 'POST') {
            $this->generateFromDescription();
            return;
        }
        if ($id !== null && $action === 'generate-intents' && $method === 'POST') {
            $this->generateIntents($id);
            return;
        }
        if ($id !== null && $action === 'suggest-template-purposes' && $method === 'POST') {
            $this->suggestTemplatePurposes($id);
            return;
        }
        // POST /profiles/brief — step-by-step wizard (step in body: classify/audience/usp/...)
        if ($id === null && $action === 'brief' && $method === 'POST') {
            $this->briefStep();
            return;
        }
        // POST /profiles/{id}/brief — save compiled brief + derived persona/rules onto profile
        if ($id !== null && $action === 'brief' && $method === 'POST') {
            $this->briefSave($id);
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
        $activeOnly = (bool)(int)$this->getParam('active_only', 0);
        $where = $activeOnly ? 'WHERE is_active = 1' : '';

        $rows = $this->db->fetchAll(
            "SELECT p.*, "
            . "(SELECT COUNT(*) FROM seo_templates t WHERE t.profile_id = p.id) AS templates_count, "
            . "(SELECT COUNT(*) FROM seo_articles a WHERE a.profile_id = p.id) AS articles_count "
            . "FROM " . SeoSiteProfile::TABLE . " p {$where} ORDER BY p.id"
        );

        $items = array_map(
            static function(array $row) {
                $arr = (new SeoSiteProfile($row))->toFullArray();
                $arr['templates_count'] = (int)($row['templates_count'] ?? 0);
                $arr['articles_count']  = (int)($row['articles_count'] ?? 0);
                return $arr;
            },
            $rows
        );

        $this->success($items);
    }

    private function show(int $id): void {
        $row = $this->db->fetchOne(
            "SELECT * FROM " . SeoSiteProfile::TABLE . " WHERE id = :id",
            [':id' => $id]
        );
        if ($row === null) $this->notFound('Профиль');

        $data = (new SeoSiteProfile($row))->toFullArray();

        // Include counts
        $data['catalogs_count'] = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM seo_catalogs WHERE profile_id = :pid", [':pid' => $id]);
        $data['templates_count'] = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM seo_templates WHERE profile_id = :pid", [':pid' => $id]);
        $data['articles_count'] = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM seo_articles WHERE profile_id = :pid", [':pid' => $id]);
        $data['intents_count'] = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM seo_intent_types WHERE profile_id = :pid", [':pid' => $id]);

        $this->success($data);
    }

    private function create(): void {
        $data = $this->getJsonBody();
        // icon_path / icon_url управляется только uploadIcon — нельзя подсунуть через JSON
        // (иначе path traversal при serveIcon: UPLOADS_DIR . $iconPath).
        unset($data['icon_path'], $data['icon_url']);
        $this->abortIfErrors($this->validateRequired($data, ['name', 'slug']));

        $slug = trim((string)($data['slug'] ?? ''));
        if (!preg_match('/^[a-z0-9_-]{1,100}$/', $slug)) {
            $this->error("Slug должен содержать только строчные буквы, цифры, '-' и '_', длина 1-100", 422);
        }

        $existing = $this->db->fetchOne(
            "SELECT id FROM " . SeoSiteProfile::TABLE . " WHERE slug = :slug",
            [':slug' => $slug]
        );
        if ($existing) {
            $this->error("Профиль со slug '{$slug}' уже существует", 409);
        }

        $profile = new SeoSiteProfile($data);
        $newId = $this->db->insert(SeoSiteProfile::TABLE, $profile->toArray());
        $profile->setId($newId);

        $this->success($profile->toFullArray(), 201);
    }

    private function update(int $id): void {
        $existing = $this->db->fetchOne(
            "SELECT * FROM " . SeoSiteProfile::TABLE . " WHERE id = :id",
            [':id' => $id]
        );
        if ($existing === null) $this->notFound('Профиль');

        $data = $this->getJsonBody();
        // icon_path / icon_url управляется только uploadIcon (см. create()).
        unset($data['icon_path'], $data['icon_url']);

        // Validate slug uniqueness if changing
        if (isset($data['slug']) && $data['slug'] !== $existing['slug']) {
            $dup = $this->db->fetchOne(
                "SELECT id FROM " . SeoSiteProfile::TABLE . " WHERE slug = :slug AND id != :id",
                [':slug' => $data['slug'], ':id' => $id]
            );
            if ($dup) {
                $this->error("Профиль со slug '{$data['slug']}' уже существует", 409);
            }
        }

        $profile = new SeoSiteProfile($existing);
        $profile->fromArray($data);

        $this->db->update(SeoSiteProfile::TABLE, 'id = :id', $profile->toArray(), [':id' => $id]);

        $this->success($profile->toFullArray());
    }

    private function delete(int $id): void {
        $existing = $this->db->fetchOne(
            "SELECT * FROM " . SeoSiteProfile::TABLE . " WHERE id = :id",
            [':id' => $id]
        );
        if ($existing === null) $this->notFound('Профиль');

        // Check for linked data
        $articlesCount = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM seo_articles WHERE profile_id = :pid", [':pid' => $id]);
        if ($articlesCount > 0) {
            $force = ($this->getParam('force') === '1');
            if (!$force) {
                $this->error("Профиль содержит {$articlesCount} статей. Используйте ?force=1 для удаления.", 409);
            }
        }

        $this->db->delete(SeoSiteProfile::TABLE, 'id = :id', [':id' => $id]);
        $this->success(['deleted' => true]);
    }

    private function generateTemplates(int $id): void {
        $data = $this->getJsonBody();
        $niche = trim((string)($data['niche_description'] ?? ''));
        if ($niche === '') {
            $this->error('Поле niche_description обязательно — опишите нишу/тематику', 422);
        }

        $service = new TemplateGeneratorService();
        $result = $service->generateProposal($id, $niche, [
            'count' => $data['count'] ?? 5,
            'model' => $data['model'] ?? null,
        ]);

        $this->success($result);
    }

    private function saveTemplates(int $id): void {
        $data = $this->getJsonBody();
        $templates = $data['templates'] ?? [];
        if (empty($templates) || !is_array($templates)) {
            $this->error('Поле templates обязательно — массив шаблонов для сохранения', 422);
        }

        $service = new TemplateGeneratorService();
        $result = $service->saveProposal($id, $templates);

        $this->success($result, 201);
    }

    private function generateTemplateSse(int $id): void {
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
            echo "data: " . json_encode(['message' => 'Поле purpose обязательно — опишите назначение шаблона'], JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
            exit;
        }

        try {
            $service = new TemplateGeneratorService();
            $service->generateSingleTemplateSSE($id, $purpose, [
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

    private function stats(int $id): void {
        $existing = $this->db->fetchOne(
            "SELECT id FROM " . SeoSiteProfile::TABLE . " WHERE id = :id",
            [':id' => $id]
        );
        if ($existing === null) $this->notFound('Профиль');

        $this->success([
            'catalogs'        => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM seo_catalogs WHERE profile_id = :pid", [':pid' => $id]),
            'templates'       => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM seo_templates WHERE profile_id = :pid", [':pid' => $id]),
            'articles'        => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM seo_articles WHERE profile_id = :pid", [':pid' => $id]),
            'published'       => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM seo_articles WHERE profile_id = :pid AND status = 'published'", [':pid' => $id]),
            'intents'         => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM seo_intent_types WHERE profile_id = :pid", [':pid' => $id]),
            'keyword_jobs'    => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM seo_keyword_jobs WHERE profile_id = :pid", [':pid' => $id]),
            'clusters'        => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM seo_keyword_clusters WHERE profile_id = :pid", [':pid' => $id]),
            'publish_targets' => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM seo_publish_targets WHERE profile_id = :pid", [':pid' => $id]),
        ]);
    }

    private function tokenUsage(int $id): void {
        $existing = $this->db->fetchOne(
            "SELECT id FROM " . SeoSiteProfile::TABLE . " WHERE id = :id",
            [':id' => $id]
        );
        if ($existing === null) $this->notFound('Профиль');

        $limit = (int)$this->getParam('recent', 20);
        if ($limit < 0)   $limit = 0;
        if ($limit > 200) $limit = 200;

        $this->success((new TokenUsageLogger())->summaryForProfile($id, $limit));
    }

    // ── Icon upload ─────────────────────────────────────────

    private function getIconDir(): string {
        $dir = UPLOADS_DIR . 'profiles/icons';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private function uploadIcon(int $id): void {
        $existing = $this->db->fetchOne(
            "SELECT * FROM " . SeoSiteProfile::TABLE . " WHERE id = :id",
            [':id' => $id]
        );
        if ($existing === null) $this->notFound('Профиль');

        if (empty($_FILES['icon']) || $_FILES['icon']['error'] !== UPLOAD_ERR_OK) {
            $this->error('Файл иконки не загружен или произошла ошибка загрузки', 422);
        }

        $file = $_FILES['icon'];
        $mime = mime_content_type($file['tmp_name']);
        $allowed = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml', 'image/gif'];
        if (!in_array($mime, $allowed, true)) {
            $this->error('Допустимые форматы: PNG, JPEG, WebP, SVG, GIF', 422);
        }

        $maxSize = 2 * 1024 * 1024; // 2MB
        if ($file['size'] > $maxSize) {
            $this->error('Максимальный размер файла: 2 МБ', 422);
        }

        $extMap = [
            'image/png'     => 'png',
            'image/jpeg'    => 'jpg',
            'image/webp'    => 'webp',
            'image/svg+xml' => 'svg',
            'image/gif'     => 'gif',
        ];
        $ext = $extMap[$mime] ?? 'png';

        // Remove old icon if exists
        if ($existing['icon_path']) {
            $oldFile = $this->resolveSafeUploadPath($existing['icon_path']);
            if ($oldFile !== null && is_file($oldFile)) {
                unlink($oldFile);
            }
        }

        $filename = "profile_{$id}_" . time() . ".{$ext}";
        $relPath = "profiles/icons/{$filename}";
        $absPath = UPLOADS_DIR . $relPath;

        $this->getIconDir();
        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            $this->error('Не удалось сохранить файл', 500);
        }

        $this->db->update(
            SeoSiteProfile::TABLE,
            'id = :id',
            ['icon_path' => $relPath],
            [':id' => $id]
        );

        $this->success([
            'icon_path' => $relPath,
            'icon_url'  => 'controllers/router.php?r=profiles/' . $id . '/icon',
        ]);
    }

    private function serveIcon(int $id): void {
        $row = $this->db->fetchOne(
            "SELECT icon_path FROM " . SeoSiteProfile::TABLE . " WHERE id = :id",
            [':id' => $id]
        );
        if ($row === null) $this->notFound('Профиль');

        $iconPath = $row['icon_path'] ?? null;
        if (!$iconPath) {
            // Return default placeholder
            http_response_code(204);
            exit;
        }

        $absPath = $this->resolveSafeUploadPath($iconPath);
        if ($absPath === null || !is_file($absPath)) {
            http_response_code(204);
            exit;
        }

        $mime = mime_content_type($absPath);
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=86400');
        header('Content-Length: ' . filesize($absPath));
        readfile($absPath);
        exit;
    }

    /**
     * Defense-in-depth: даже если в БД попал traversal-путь, realpath() приведёт
     * к canonical-форме и проверка префикса отбросит выход за UPLOADS_DIR.
     * Возвращает абсолютный путь или null если файл вне разрешённой директории.
     */
    private function resolveSafeUploadPath(string $relPath): ?string {
        $base = realpath(UPLOADS_DIR);
        if ($base === false) return null;
        $abs = realpath(UPLOADS_DIR . $relPath);
        if ($abs === false) return null;
        $baseWithSep = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (strpos($abs, $baseWithSep) !== 0) return null;
        return $abs;
    }

    private function deleteIcon(int $id): void {
        $existing = $this->db->fetchOne(
            "SELECT * FROM " . SeoSiteProfile::TABLE . " WHERE id = :id",
            [':id' => $id]
        );
        if ($existing === null) $this->notFound('Профиль');

        if ($existing['icon_path']) {
            $absPath = $this->resolveSafeUploadPath($existing['icon_path']);
            if ($absPath !== null && is_file($absPath)) {
                unlink($absPath);
            }
        }

        $this->db->update(
            SeoSiteProfile::TABLE,
            'id = :id',
            ['icon_path' => null],
            [':id' => $id]
        );

        $this->success(['deleted' => true]);
    }

    // ── GPT: generate profile from description ──────────────

    private function generateFromDescription(): void {
        $data = $this->getJsonBody();
        $description = trim((string)($data['description'] ?? ''));
        if ($description === '') {
            $this->error('Поле description обязательно — опишите проект', 422);
        }

        $gpt = new GptClient();
        $gpt->setLogContext(['category' => TokenUsageLogger::CATEGORY_PROFILE_CREATE, 'operation' => 'generate_from_description']);
        $result = $gpt->chatJson([
            [
                'role' => 'system',
                'content' => <<<PROMPT
Ты — эксперт по SEO и контент-маркетингу. Пользователь описывает свой проект.
На основе описания сгенерируй параметры профиля для SEO-генератора.

Верни JSON со следующими полями:
{
  "name": "Краткое название проекта (для отображения)",
  "slug": "url-safe-slug (строчные латинские, дефисы, без пробелов)",
  "niche": "Ниша / тематика (кратко, 2-5 слов)",
  "brand_name": "Название бренда/компании (если упоминается, иначе предложи)",
  "language": "ru|en|uk (определи по описанию)",
  "tone": "professional|friendly|academic|casual|persuasive (определи по тематике)",
  "gpt_persona": "Системный промпт для GPT — кто он при генерации контента для этого проекта (3-5 предложений, подробно, на языке проекта)",
  "gpt_rules": "Дополнительные правила генерации контента (5-8 пунктов через \\n, на языке проекта)",
  "color_scheme": "HEX цвет, подходящий тематике (#xxxxxx)"
}

Все текстовые поля генерируй на языке описания.
PROMPT
            ],
            [
                'role' => 'user',
                'content' => $description,
            ],
        ], [
            'temperature' => SEO_TEMPERATURE_CREATIVE,
            'max_tokens'  => SEO_MAX_TOKENS_SMALL,
        ]);

        $this->success([
            'profile' => $result['data'],
            'usage'   => $result['usage'],
        ]);
    }

    private function generateIntents(int $profileId): void {
        $profile = $this->db->fetchOne(
            "SELECT * FROM " . SeoSiteProfile::TABLE . " WHERE id = :id",
            [':id' => $profileId]
        );
        if ($profile === null) {
            $this->notFound('Профиль');
        }

        $data = $this->getJsonBody();
        $niche = $data['niche'] ?? $profile['niche'] ?? $profile['name'] ?? '';

        $gpt = new GptClient();
        $gpt->setLogContext(['category' => TokenUsageLogger::CATEGORY_PROFILE_CREATE, 'operation' => 'generate_intents', 'profile_id' => $profileId]);
        $result = $gpt->chatJson([
            [
                'role' => 'system',
                'content' => <<<PROMPT
Ты — эксперт по SEO и поисковым интентам. Сгенерируй 5-8 типов интентов для ниши, описанной пользователем.

Каждый интент — это тип поискового запроса/статьи. Ответь JSON-массивом объектов:
[
  {
    "code": "уникальный_код_snake_case_латиницей",
    "label_ru": "Название на русском",
    "label_en": "English label",
    "color": "#hex_цвет",
    "description": "Когда применяется этот интент (1-2 предложения)",
    "gpt_hint": "Как AI должен распознать этот тип запроса (1-2 предложения)",
    "article_tone": "Каким тоном писать статью (1 предложение)",
    "article_open": "Пример открывающей фразы статьи"
  }
]

Правила:
- code: только a-z, 0-9 и _ (длина 1-30)
- color: различные HEX-цвета для визуального различения
- Генерируй интенты, специфичные для указанной ниши
- Не дублируй стандартные интенты (info, comparison, transactional и т.п.)
PROMPT
            ],
            [
                'role' => 'user',
                'content' => "Ниша: {$niche}\nОписание проекта: " . ($profile['description'] ?? ''),
            ],
        ], [
            'temperature' => SEO_TEMPERATURE_CREATIVE,
            'max_tokens'  => SEO_MAX_TOKENS_SMALL,
        ]);

        $intents = $result['data'];
        if (!is_array($intents)) {
            $this->error('AI вернул невалидный ответ', 500);
        }

        $count = 0;
        foreach ($intents as $intentData) {
            $code = $intentData['code'] ?? '';
            if (!SeoIntentType::isValidCode($code)) {
                continue;
            }

            // Check if code already exists
            $existing = $this->db->fetchOne(
                "SELECT code FROM " . SeoIntentType::TABLE . " WHERE code = :code",
                [':code' => $code]
            );
            if ($existing !== null) {
                continue;
            }

            $intent = new SeoIntentType($intentData);
            $intent->setCode($code);
            $intent->setProfileId($profileId);

            $this->db->insert(
                SeoIntentType::TABLE,
                array_merge(['code' => $code], $intent->toArray())
            );
            $count++;
        }

        $this->success(['count' => $count, 'total_generated' => count($intents)]);
    }

    /**
     * POST /profiles/{id}/suggest-template-purposes
     * Returns 5 template "purpose" options tailored to the brief.
     * Each option feeds later into /profiles/{id}/generate-template-sse as `purpose` field.
     */
    private function suggestTemplatePurposes(int $id): void {
        $profile = $this->db->fetchOne(
            "SELECT * FROM " . SeoSiteProfile::TABLE . " WHERE id = :id",
            [':id' => $id]
        );
        if ($profile === null) $this->notFound('Профиль');

        $brief = $profile['content_brief'] ?? null;
        if (is_string($brief) && $brief !== '') $brief = json_decode($brief, true);
        $briefJson = is_array($brief) ? json_encode($brief, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '{}';

        $system = BriefPrompt::ANTI_GENERIC . "\n\n"
            . <<<'EOT'
Ты — контент-стратег. На основе брифа проекта предложи 5 РАЗНЫХ типов статей (шаблонов), закрывающих разные интенты аудитории.
Каждый вариант должен отличаться по формату (обзор, сравнение, руководство, кейс, аналитика, FAQ-разбор и т.п.) и по ICP, на который нацелен.

Формат JSON:
{
  "options": [
    {
      "id": "tpl_1",
      "title": "Короткое имя типа статьи",
      "purpose": "Назначение шаблона — 2-3 предложения, с указанием ICP, цели чтения, как используется бренд и какие УТП обыгрываются",
      "format": "обзор|сравнение|руководство|кейс|аналитика|FAQ|другое",
      "target_icp": "На какой ICP из брифа нацелен",
      "suggested_blocks_hint": "Какие типы блоков напрашиваются (без кода — словами)"
    }
  ]
}
EOT;

        $userContent = "Бриф проекта (JSON):\n{$briefJson}\n\n"
            . "Ниша: " . ($profile['niche'] ?? '') . "\n"
            . "Бренд: " . ($profile['brand_name'] ?? '') . "\n"
            . "Описание: " . ($profile['description'] ?? '');

        $gpt = new GptClient();
        $gpt->setLogContext(['category' => TokenUsageLogger::CATEGORY_TEMPLATE_CREATE, 'operation' => 'suggest_template_purposes', 'profile_id' => $id]);
        $result = $gpt->chatJson([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $userContent],
        ], [
            'temperature' => SEO_TEMPERATURE_CREATIVE,
            'max_tokens'  => SEO_MAX_TOKENS_SMALL,
        ]);

        $this->success([
            'options' => $result['data']['options'] ?? [],
            'usage'   => $result['usage'],
        ]);
    }

    // ── Brief wizard ────────────────────────────────────────

    /**
     * POST /profiles/brief
     * Body: { step, description, brief?, model? }
     *   step: classify | audience | usp | competitors | voice | rules | compliance | phrases
     *   description: original project description (required)
     *   brief: accumulated brief state so far (optional, JSON object)
     * Returns GPT JSON result — list of options for user to pick/edit.
     */
    private function briefStep(): void {
        $data = $this->getJsonBody();
        $step = trim((string)($data['step'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        $brief = is_array($data['brief'] ?? null) ? $data['brief'] : [];
        $profileId = isset($data['profile_id']) ? (int)$data['profile_id'] : null;
        $hint = trim((string)($data['hint'] ?? ''));
        $existingOptions = is_array($data['existing_options'] ?? null) ? $data['existing_options'] : [];

        if ($description === '') {
            $this->error('Поле description обязательно', 422);
        }

        $stepMap = [
            'classify'    => BriefPrompt::CLASSIFY_SYSTEM,
            'audience'    => BriefPrompt::AUDIENCE_SYSTEM,
            'usp'         => BriefPrompt::USP_SYSTEM,
            'competitors' => BriefPrompt::COMPETITORS_SYSTEM,
            'voice'       => BriefPrompt::VOICE_SYSTEM,
            'rules'       => BriefPrompt::RULES_SYSTEM,
            'compliance'  => BriefPrompt::COMPLIANCE_SYSTEM,
            'phrases'     => BriefPrompt::PHRASES_SYSTEM,
        ];
        if (!isset($stepMap[$step])) {
            $this->error("Неизвестный шаг брифа: '{$step}'. Допустимо: " . implode(', ', array_keys($stepMap)), 422);
        }

        $systemPrompt = BriefPrompt::ANTI_GENERIC . "\n\n" . $stepMap[$step];

        if ($step === 'classify') {
            $userContent = sprintf(BriefPrompt::USER_DESCRIBE, $description);
        } else {
            $briefJson = json_encode($brief, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}';
            $userContent = sprintf(BriefPrompt::USER_WITH_BRIEF, $description, $briefJson);
        }
        if ($hint !== '') {
            $userContent .= "\n\nДополнительные пожелания пользователя: " . $hint;
        }
        if (!empty($existingOptions)) {
            $existJson = json_encode($existingOptions, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '[]';
            $userContent .= "\n\nУже предложены следующие варианты (НЕ повторяй их, предложи новые):\n" . $existJson;
        }

        $temperature = $step === 'classify' || $step === 'compliance'
            ? SEO_TEMPERATURE_PRECISE
            : SEO_TEMPERATURE_CREATIVE;

        $gpt = new GptClient();
        $gpt->setLogContext(['category' => TokenUsageLogger::CATEGORY_PROFILE_BRIEF, 'operation' => 'brief_' . $step, 'profile_id' => $profileId]);
        $result = $gpt->chatJson([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userContent],
        ], [
            'model' => $data['model'] ?? null,
            'temperature' => $temperature,
            'max_tokens' => SEO_MAX_TOKENS_SMALL,
        ]);

        $this->success([
            'step'  => $step,
            'data'  => $result['data'],
            'usage' => $result['usage'],
        ]);
    }

    /**
     * POST /profiles/{id}/brief
     * Body: { brief: {...} }
     * Persists content_brief JSON on profile and derives gpt_persona / gpt_rules
     * deterministically from the brief (no GPT call — template formatting).
     */
    private function briefSave(int $id): void {
        $existing = $this->db->fetchOne(
            "SELECT * FROM " . SeoSiteProfile::TABLE . " WHERE id = :id",
            [':id' => $id]
        );
        if ($existing === null) $this->notFound('Профиль');

        $data = $this->getJsonBody();
        $brief = $data['brief'] ?? null;
        if (!is_array($brief) || empty($brief)) {
            $this->error('Поле brief обязательно — передайте собранный бриф объектом', 422);
        }

        $persona = $this->compilePersonaFromBrief($brief);
        $rules   = $this->compileRulesFromBrief($brief);

        $profile = new SeoSiteProfile($existing);
        $profile->setContentBrief($brief);
        if ($persona !== null) $profile->setGptPersona($persona);
        if ($rules !== null)   $profile->setGptRules($rules);

        $this->db->update(SeoSiteProfile::TABLE, 'id = :id', $profile->toArray(), [':id' => $id]);

        $this->success($profile->toFullArray());
    }

    /**
     * Deterministic persona assembly from brief — no GPT call.
     * This is what makes persona rich: we hand-stitch named entities from the brief
     * instead of asking GPT to vomit generic sentences.
     */
    private function compilePersonaFromBrief(array $brief): ?string {
        $parts = [];

        $voice = $brief['voice'] ?? [];
        if (!empty($voice['archetype'])) {
            $label = $voice['label'] ?? $voice['archetype'];
            $parts[] = "Ты пишешь в роли {$label} ({$voice['archetype']}).";
        }

        $audience = $brief['audience'] ?? [];
        if (!empty($audience['label'])) {
            $pains = !empty($audience['pains']) ? implode('; ', array_slice((array)$audience['pains'], 0, 3)) : '';
            $goals = !empty($audience['goals']) ? implode('; ', array_slice((array)$audience['goals'], 0, 3)) : '';
            $parts[] = "Аудитория — {$audience['label']}"
                . (!empty($audience['demographics']) ? " ({$audience['demographics']})" : '')
                . ($pains ? ". Их боли: {$pains}" : '')
                . ($goals ? ". Их цели: {$goals}" : '')
                . '.';
        }

        $usps = $brief['usps'] ?? [];
        if (is_array($usps) && !empty($usps)) {
            $headlines = [];
            foreach (array_slice($usps, 0, 4) as $u) {
                if (!empty($u['headline'])) $headlines[] = $u['headline'];
            }
            if ($headlines) {
                $parts[] = 'Ключевые УТП проекта: ' . implode('; ', $headlines) . '.';
            }
        }

        if (!empty($voice['vocabulary_hints']) && is_array($voice['vocabulary_hints'])) {
            $parts[] = 'Характерные обороты: ' . implode(', ', array_slice($voice['vocabulary_hints'], 0, 6)) . '.';
        }

        if (!empty($voice['sample_explanation'])) {
            $parts[] = 'Образец фразы в голосе бренда: «' . $voice['sample_explanation'] . '».';
        }

        $compliance = $brief['compliance'] ?? [];
        if (!empty($compliance['forbidden_claims'])) {
            $phrases = [];
            foreach (array_slice((array)$compliance['forbidden_claims'], 0, 5) as $c) {
                $phrases[] = is_array($c) ? ($c['phrase'] ?? '') : (string)$c;
            }
            $phrases = array_filter($phrases);
            if ($phrases) {
                $parts[] = 'СТРОГО ЗАПРЕЩЕНО использовать формулировки: «' . implode('», «', $phrases) . '».';
            }
        }

        return $parts ? implode(' ', $parts) : null;
    }

    /**
     * Deterministic rules compilation — bullet list built from brief do/don't + competitor angles.
     */
    private function compileRulesFromBrief(array $brief): ?string {
        $lines = [];
        $n = 1;

        $do = $brief['rules']['do'] ?? [];
        foreach (array_slice((array)$do, 0, 8) as $r) {
            $rule = is_array($r) ? ($r['rule'] ?? '') : (string)$r;
            if ($rule !== '') $lines[] = ($n++) . '. ' . $rule;
        }

        $dont = $brief['rules']['dont'] ?? [];
        foreach (array_slice((array)$dont, 0, 8) as $r) {
            $rule = is_array($r) ? ($r['rule'] ?? '') : (string)$r;
            if ($rule !== '') $lines[] = ($n++) . '. НЕ: ' . $rule;
        }

        $competitors = $brief['competitors'] ?? [];
        foreach (array_slice((array)$competitors, 0, 3) as $c) {
            if (!is_array($c) || empty($c['angle'])) continue;
            $lines[] = ($n++) . '. Отстройка от «' . ($c['name'] ?? 'конкурент') . '»: ' . $c['angle'];
        }

        $disclaimers = $brief['compliance']['required_disclaimers'] ?? [];
        foreach (array_slice((array)$disclaimers, 0, 3) as $d) {
            $txt = (string)$d;
            if ($txt !== '') $lines[] = ($n++) . '. Обязательная оговорка: ' . $txt;
        }

        return $lines ? implode("\n", $lines) : null;
    }
}
