<?php

declare(strict_types=1);

namespace Seo\Entity;

class SeoRawKeyword extends AbstractEntity {

    public const TABLE = 'seo_raw_keywords';

    public const SOURCE_YANDEX = 'yandex';
    public const SOURCE_GOOGLE = 'google';
    public const SOURCE_MANUAL = 'manual';

    public const SOURCES = [
        self::SOURCE_YANDEX,
        self::SOURCE_GOOGLE,
        self::SOURCE_MANUAL,
    ];

    protected int $jobId = 0;
    protected string $keyword = '';
    protected string $source = self::SOURCE_MANUAL;
    protected ?int $volume = null;
    protected ?float $competition = null;
    protected ?float $cpc = null;
    protected ?int $clusterId = null;
    protected bool $isProcessed = false;

    protected ?string $clusterName = null;

    protected function hydrate(array $data): void {
        if (array_key_exists('job_id', $data)) {
            $this->jobId = (int)$data['job_id'];
        }
        if (array_key_exists('keyword', $data)) {
            $this->keyword = (string)$data['keyword'];
        }
        if (array_key_exists('source', $data)) {
            $this->source = (string)$data['source'];
        }
        if (array_key_exists('volume', $data)) {
            $this->volume = $data['volume'] !== null ? (int)$data['volume'] : null;
        }
        if (array_key_exists('competition', $data)) {
            $this->competition = $data['competition'] !== null ? (float)$data['competition'] : null;
        }
        if (array_key_exists('cpc', $data)) {
            $this->cpc = $data['cpc'] !== null ? (float)$data['cpc'] : null;
        }
        if (array_key_exists('cluster_id', $data)) {
            $this->clusterId = $this->toNullableInt($data['cluster_id']);
        }
        if (array_key_exists('is_processed', $data)) {
            $this->isProcessed = $this->toBool($data['is_processed']);
        }
        // Computed
        if (array_key_exists('cluster_name', $data)) {
            $this->clusterName = $this->toNullableString($data['cluster_name']);
        }
    }

    public function toArray(): array {
        return [
            'job_id'       => $this->jobId,
            'keyword'      => $this->keyword,
            'source'       => $this->source,
            'volume'       => $this->volume,
            'competition'  => $this->competition,
            'cpc'          => $this->cpc,
            'cluster_id'   => $this->clusterId,
            'is_processed' => (int)$this->isProcessed,
        ];
    }

    public function toFullArray(): array {
        $arr = parent::toFullArray();
        if ($this->clusterName !== null) {
            $arr['cluster_name'] = $this->clusterName;
        }
        return $arr;
    }

    public function isClustered(): bool {
        return $this->clusterId !== null;
    }


    public function getJobId(): int {
        return $this->jobId;
    }

    public function setJobId(int $jobId): self {
        $this->jobId = $jobId;
        return $this;
    }

    public function getKeyword(): string {
        return $this->keyword;
    }

    public function setKeyword(string $keyword): self {
        $this->keyword = $keyword;
        return $this;
    }

    public function getSource(): string {
        return $this->source;
    }

    public function setSource(string $source): self {
        $this->source = $source;
        return $this;
    }

    public function getVolume(): ?int {
        return $this->volume;
    }

    public function setVolume(?int $volume): self {
        $this->volume = $volume;
        return $this;
    }

    public function getCompetition(): ?float {
        return $this->competition;
    }

    public function setCompetition(?float $competition): self {
        $this->competition = $competition;
        return $this;
    }

    public function getCpc(): ?float {
        return $this->cpc;
    }

    public function setCpc(?float $cpc): self {
        $this->cpc = $cpc;
        return $this;
    }

    public function getClusterId(): ?int {
        return $this->clusterId;
    }

    public function setClusterId(?int $clusterId): self {
        $this->clusterId = $clusterId;
        return $this;
    }

    public function isProcessed(): bool {
        return $this->isProcessed;
    }

    public function setIsProcessed(bool $isProcessed): self {
        $this->isProcessed = $isProcessed;
        return $this;
    }

    public function getClusterName(): ?string {
        return $this->clusterName;
    }
}