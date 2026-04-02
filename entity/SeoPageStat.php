<?php

declare(strict_types=1);

namespace Seo\Entity;

/**
 * Сырой хит посещения страницы.
 *
 * Таблица: seo_page_stats
 * Пишется через track.php при каждом посещении.
 */
class SeoPageStat extends AbstractEntity {

    public const DEVICE_DESKTOP = 'desktop';
    public const DEVICE_MOBILE  = 'mobile';
    public const DEVICE_TABLET  = 'tablet';
    public const DEVICE_BOT     = 'bot';
    public const DEVICE_UNKNOWN = 'unknown';

    protected int $articleId = 0;
    protected ?string $ip = null;
    protected ?string $userAgent = null;
    protected ?string $referer = null;
    protected ?string $country = null;
    protected string $deviceType = self::DEVICE_UNKNOWN;
    protected ?string $visitedAt = null;

    protected function hydrate(array $data): void {
        if (array_key_exists('article_id', $data)) {
            $this->articleId = (int)$data['article_id'];
        }
        if (array_key_exists('ip', $data)) {
            $this->ip = $this->toNullableString($data['ip']);
        }
        if (array_key_exists('user_agent', $data)) {
            $this->userAgent = $this->toNullableString($data['user_agent']);
        }
        if (array_key_exists('referer', $data)) {
            $this->referer = $this->toNullableString($data['referer']);
        }
        if (array_key_exists('country', $data)) {
            $this->country = $this->toNullableString($data['country']);
        }
        if (array_key_exists('device_type', $data)) {
            $this->deviceType = (string)$data['device_type'];
        }
        if (array_key_exists('visited_at', $data)) {
            $this->visitedAt = $this->toNullableString($data['visited_at']);
        }
    }


    public function toArray(): array {
        return [
            'article_id'  => $this->articleId,
            'ip'          => $this->ip,
            'user_agent'  => $this->userAgent,
            'referer'     => $this->referer,
            'country'     => $this->country,
            'device_type' => $this->deviceType,
        ];
    }

    /**
     * Создать из текущего HTTP-запроса.
     */
    public static function fromRequest(int $articleId): self {
        $entity = new self();
        $entity->articleId = $articleId;
        $entity->ip = $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_X_REAL_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? null;
        $entity->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $entity->referer = $_SERVER['HTTP_REFERER'] ?? null;
        $entity->deviceType = self::detectDevice($entity->userAgent);
        return $entity;
    }

    /**
     * Определение типа устройства по User-Agent.
     */
    public static function detectDevice(?string $ua): string {
        if ($ua === null || $ua === '') {
            return self::DEVICE_UNKNOWN;
        }

        $ua = strtolower($ua);

        // Боты
        $bots = ['bot', 'crawl', 'spider', 'slurp', 'facebook', 'twitter', 'linkedin', 'whatsapp'];
        foreach ($bots as $bot) {
            if (strpos($ua, $bot) !== false) {
                return self::DEVICE_BOT;
            }
        }

        // Планшеты (до мобилок, т.к. iPad не содержит 'mobile')
        if (strpos($ua, 'tablet') !== false || strpos($ua, 'ipad') !== false) {
            return self::DEVICE_TABLET;
        }

        // Мобилки
        $mobile = ['mobile', 'android', 'iphone', 'ipod', 'opera mini', 'opera mobi'];
        foreach ($mobile as $m) {
            if (strpos($ua, $m) !== false) {
                return self::DEVICE_MOBILE;
            }
        }

        return self::DEVICE_DESKTOP;
    }


    public function getArticleId(): int {
        return $this->articleId;
    }

    public function setArticleId(int $articleId): self {
        $this->articleId = $articleId;
        return $this;
    }

    public function getIp(): ?string {
        return $this->ip;
    }

    public function setIp(?string $ip): self {
        $this->ip = $ip;
        return $this;
    }

    public function getUserAgent(): ?string {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getReferer(): ?string {
        return $this->referer;
    }

    public function setReferer(?string $referer): self {
        $this->referer = $referer;
        return $this;
    }

    public function getCountry(): ?string {
        return $this->country;
    }

    public function setCountry(?string $country): self {
        $this->country = $country;
        return $this;
    }

    public function getDeviceType(): string {
        return $this->deviceType;
    }

    public function setDeviceType(string $deviceType): self {
        $this->deviceType = $deviceType;
        return $this;
    }

    public function getVisitedAt(): ?string {
        return $this->visitedAt;
    }
}
