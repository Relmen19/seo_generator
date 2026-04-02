<?php

declare(strict_types=1);

namespace Seo\Entity;

class SeoKeywordJob extends AbstractEntity {

    public const TABLE = 'seo_keyword_jobs';

    public const STATUS_PENDING    = 'pending';
    public const STATUS_COLLECTING = 'collecting';
    public const STATUS_CLUSTERING = 'clustering';
    public const STATUS_DONE       = 'done';
    public const STATUS_ERROR      = 'error';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_COLLECTING,
        self::STATUS_CLUSTERING,
        self::STATUS_DONE,
        self::STATUS_ERROR,
    ];

    public const SOURCES = ['yandex', 'google', 'manual', 'gpt'];

    protected string $seedKeyword = '';
    protected string $source = 'manual';
    protected string $status = self::STATUS_PENDING;
    protected int $totalFound = 0;
    protected int $totalClusters = 0;
    protected ?array $config = null;
    protected ?string $errorLog = null;

    protected int $keywordCount = 0;
    protected int $clusterCount = 0;

    protected function hydrate(array $data): void {
        if (array_key_exists('seed_keyword', $data)) {
            $this->seedKeyword = (string)$data['seed_keyword'];
        }
        if (array_key_exists('source', $data)) {
            $this->source = (string)$data['source'];
        }
        if (array_key_exists('status', $data)) {
            $this->status = (string)$data['status'];
        }
        if (array_key_exists('total_found', $data)) {
            $this->totalFound = (int)$data['total_found'];
        }
        if (array_key_exists('total_clusters', $data)) {
            $this->totalClusters = (int)$data['total_clusters'];
        }
        if (array_key_exists('config', $data)) {
            $this->config = $this->decodeJson($data['config']);
        }
        if (array_key_exists('error_log', $data)) {
            $this->errorLog = $this->toNullableString($data['error_log']);
        }
        if (array_key_exists('keyword_count', $data)) {
            $this->keywordCount = (int)$data['keyword_count'];
        }
        if (array_key_exists('cluster_count', $data)) {
            $this->clusterCount = (int)$data['cluster_count'];
        }
    }

    public function toArray(): array {
        return [
            'seed_keyword'   => $this->seedKeyword,
            'source'         => $this->source,
            'status'         => $this->status,
            'total_found'    => $this->totalFound,
            'total_clusters' => $this->totalClusters,
            'config'         => $this->encodeJson($this->config),
            'error_log'      => $this->errorLog,
        ];
    }

    public function toFullArray(): array {
        $arr = parent::toFullArray();
        $arr['keyword_count'] = $this->keywordCount;
        $arr['cluster_count'] = $this->clusterCount;
        return $arr;
    }

    public static function isValidStatus(string $status): bool {
        return in_array($status, self::STATUSES, true);
    }

    public function isPending(): bool {
        return $this->status === self::STATUS_PENDING;
    }

    public function isDone(): bool {
        return $this->status === self::STATUS_DONE;
    }

    public function isError(): bool {
        return $this->status === self::STATUS_ERROR;
    }

    public function isInProgress(): bool {
        return in_array($this->status, [self::STATUS_COLLECTING, self::STATUS_CLUSTERING], true);
    }

    public function getSeedKeyword(): string {
        return $this->seedKeyword;
    }

    public function setSeedKeyword(string $seedKeyword): self {
        $this->seedKeyword = $seedKeyword;
        return $this;
    }

    public function getSource(): string {
        return $this->source;
    }

    public function setSource(string $source): self {
        $this->source = $source;
        return $this;
    }

    public function getStatus(): string {
        return $this->status;
    }

    public function setStatus(string $status): self {
        $this->status = $status;
        return $this;
    }

    public function getTotalFound(): int {
        return $this->totalFound;
    }

    public function setTotalFound(int $totalFound): self {
        $this->totalFound = $totalFound;
        return $this;
    }

    public function getTotalClusters(): int {
        return $this->totalClusters;
    }

    public function setTotalClusters(int $totalClusters): self {
        $this->totalClusters = $totalClusters;
        return $this;
    }

    public function getConfig(): ?array {
        return $this->config;
    }

    public function setConfig(?array $config): self {
        $this->config = $config;
        return $this;
    }

    public function getConfigValue(string $key, $default = null) {
        return $this->config[$key] ?? $default;
    }

    public function getErrorLog(): ?string {
        return $this->errorLog;
    }

    public function setErrorLog(?string $errorLog): self {
        $this->errorLog = $errorLog;
        return $this;
    }

    public function appendError(string $message): self {
        $timestamp = date('H:i:s');
        $this->errorLog = trim(($this->errorLog ?? '') . "\n[{$timestamp}] {$message}");
        return $this;
    }

    public function getKeywordCount(): int {
        return $this->keywordCount;
    }

    public function getClusterCount(): int {
        return $this->clusterCount;
    }
}