<?php

declare(strict_types=1);

namespace Seo\Service;

use RuntimeException;

class GptClient {

    private string $apiKey;
    private string $defaultModel;
    private int    $timeout;
    private string $baseUrl = 'https://api.openai.com/v1';

    private array $lastUsage = [];

    public function __construct(?string $apiKey = null, ?string $model = null, ?int $timeout = null) {
        $this->apiKey = $apiKey ?? GPT_API_KEY;
        $this->defaultModel = $model ?? GPT_DEFAULT_MODEL;
        $this->timeout = $timeout ?? GPT_TIMEOUT;

        if (empty($this->apiKey)) {
            throw new RuntimeException('GPT_API_KEY не задан');
        }
    }


    public function chat(array $messages, array $options = []): array {
        $model = $options['model'] ?? $this->defaultModel;

        $payload = ['model' => $model, 'messages' => $messages,];

        if (isset($options['temperature'])) $payload['temperature'] = (float) $options['temperature'];
        if (isset($options['max_tokens'])) $payload['max_tokens'] = (int) $options['max_tokens'];
        if (isset($options['response_format'])) $payload['response_format'] = $options['response_format'];

        $result = $this->request('/chat/completions', $payload);

        $choice = $result['choices'][0] ?? [];
        $this->lastUsage = $result['usage'] ?? [];

        return [
            'content' => $choice['message']['content'] ?? '',
            'finish_reason' => $choice['finish_reason'] ?? 'unknown',
            'usage' => $this->lastUsage,
            'model' => $result['model'] ?? $model,
        ];
    }


    public function chatJson(array $messages, array $options = []): array {
        $options['response_format'] = ['type' => 'json_object'];

        $hasJsonHint = false;
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system' && stripos($msg['content'], 'json') !== false) {
                $hasJsonHint = true;
                break;
            }
        }
        unset($msg);

        if (!$hasJsonHint && !empty($messages)) {

            $found = false;
            foreach ($messages as &$msg) {
                if ($msg['role'] === 'system') {
                    $msg['content'] .= "\n\nОТВЕЧАЙ СТРОГО В ФОРМАТЕ JSON. Никакого текста вне JSON.";
                    $found = true;
                    break;
                }
            }

            unset($msg);
            if (!$found) {
                array_unshift($messages, [
                    'role'    => 'system',
                    'content' => 'Отвечай строго в формате JSON. Никакого текста вне JSON.',
                ]);
            }
        }

        $result = $this->chat($messages, $options);

        $decoded = json_decode($result['content'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {

            if (preg_match('/\{[\s\S]*\}/u', $result['content'], $m)) {
                $decoded = json_decode($m[0], true);
            }
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException(
                    'GPT вернул невалидный JSON: ' . json_last_error_msg()
                    . "\nОтвет: " . mb_substr($result['content'], 0, 500)
                );
            }
        }

        return [
            'data' => $decoded,
            'usage' => $result['usage'],
            'finish_reason' => $result['finish_reason'],
            'model' => $result['model'],
        ];
    }


    private function request(string $endpoint, array $payload, int $retries = 2): array {
        $url  = $this->baseUrl . $endpoint;
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $attempt = 0;
        $lastError = '';

        while ($attempt <= $retries) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $json,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey,
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($response === false) {
                $lastError = 'Curl error: ' . curl_error($ch);
                curl_close($ch);
                $attempt++;
                if ($attempt <= $retries) {
                    usleep(1000000 * (2 ** ($attempt - 1))); // 1s, 2s
                }
                continue;
            }
            curl_close($ch);

            $data = json_decode($response, true);

            if ($httpCode === 429 && $attempt < $retries) {
                $wait = (int) ($data['error']['retry_after'] ?? (2 ** $attempt));
                sleep(min($wait, 30));
                $attempt++;
                continue;
            }

            if ($httpCode >= 400) {
                $errMsg = $data['error']['message'] ?? "HTTP {$httpCode}";
                throw new RuntimeException("GPT API error: {$errMsg} (HTTP {$httpCode})");
            }

            return $data;
        }

        throw new RuntimeException("GPT API failed after {$retries} retries: {$lastError}");
    }


    public function getLastUsage(): array {
        return $this->lastUsage;
    }

    public function getDefaultModel(): string {
        return $this->defaultModel;
    }

    public function setBaseUrl(string $url): self {
        $this->baseUrl = rtrim($url, '/');
        return $this;
    }
}
