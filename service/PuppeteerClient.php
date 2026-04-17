<?php

declare(strict_types=1);

namespace Seo\Service;

use RuntimeException;

class PuppeteerClient {

    private string $serviceUrl;
    private int $timeout;

    public function __construct(?string $serviceUrl = null, int $timeout = 60) {
        $this->serviceUrl = rtrim($serviceUrl ?? PUPPETEER_SERVICE_URL, '/');
        $this->timeout = $timeout;
    }

    /**
     * Render HTML to PNG screenshot.
     *
     * @param string $html            Full HTML document
     * @param int    $width           Viewport width (px)
     * @param int    $deviceScaleFactor  Retina multiplier
     * @return array{image: string, width: int, height: int}  image = base64 PNG
     */
    public function screenshot(string $html, int $width = 800, int $deviceScaleFactor = 2): array {
        $payload = json_encode([
            'html'              => $html,
            'width'             => $width,
            'deviceScaleFactor' => $deviceScaleFactor,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($this->serviceUrl . '/screenshot');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("Puppeteer service error: {$err}");
        }
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode >= 400 || !is_array($data) || !isset($data['image'])) {
            $errMsg = $data['error'] ?? "HTTP {$httpCode}";
            throw new RuntimeException("Puppeteer screenshot failed: {$errMsg}");
        }

        return $data;
    }

    /**
     * Health check.
     */
    public function isHealthy(): bool {
        $ch = curl_init($this->serviceUrl . '/health');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return false;
        }

        $data = json_decode($response, true);
        return is_array($data) && ($data['status'] ?? '') === 'ok';
    }
}
