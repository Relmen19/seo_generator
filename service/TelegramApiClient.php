<?php

declare(strict_types=1);

namespace Seo\Service;

use RuntimeException;

class TelegramApiClient {

    private string $botToken;
    private string $baseUrl = 'https://api.telegram.org';
    private int $timeout = 30;

    public function __construct(string $botToken) {
        if (empty($botToken)) {
            throw new RuntimeException('Telegram bot token не задан');
        }
        $this->botToken = $botToken;
    }

    /**
     * Send a text message.
     */
    public function sendMessage(string $chatId, string $text, array $options = []): array {
        $params = array_merge([
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ], $options);

        return $this->request('sendMessage', $params);
    }

    /**
     * Send a single photo with optional caption.
     */
    public function sendPhoto(string $chatId, string $photoData, array $options = []): array {
        $params = array_merge([
            'chat_id'    => $chatId,
            'parse_mode' => 'HTML',
        ], $options);

        $tmpFile = tempnam(sys_get_temp_dir(), 'tg_photo_');
        file_put_contents($tmpFile, base64_decode($photoData));

        try {
            $result = $this->requestMultipart('sendPhoto', $params, [
                'photo' => $tmpFile,
            ]);
        } finally {
            @unlink($tmpFile);
        }

        return $result;
    }

    /**
     * Send a media group (album of photos).
     *
     * @param string $chatId
     * @param array  $images  Array of ['data' => base64, 'caption' => '...']
     * @return array
     */
    public function sendMediaGroup(string $chatId, array $images): array {
        $media = [];
        $files = [];
        $tmpFiles = [];

        foreach ($images as $i => $img) {
            $attachKey = 'photo_' . $i;
            $tmpFile = tempnam(sys_get_temp_dir(), 'tg_mg_');
            file_put_contents($tmpFile, base64_decode($img['data']));
            $tmpFiles[] = $tmpFile;

            $files[$attachKey] = $tmpFile;

            $mediaItem = [
                'type'  => 'photo',
                'media' => 'attach://' . $attachKey,
            ];
            if ($i === 0 && isset($img['caption'])) {
                $mediaItem['caption'] = $img['caption'];
                $mediaItem['parse_mode'] = 'HTML';
            }

            $media[] = $mediaItem;
        }

        $params = [
            'chat_id' => $chatId,
            'media'   => json_encode($media, JSON_UNESCAPED_UNICODE),
        ];

        try {
            $result = $this->requestMultipart('sendMediaGroup', $params, $files);
        } finally {
            foreach ($tmpFiles as $f) {
                @unlink($f);
            }
        }

        return $result;
    }

    /**
     * Get chat info (name, photo, member count).
     */
    public function getChat(string $chatId): array {
        return $this->request('getChat', ['chat_id' => $chatId]);
    }

    /**
     * Get chat member count.
     */
    public function getChatMemberCount(string $chatId): int {
        $result = $this->request('getChatMemberCount', ['chat_id' => $chatId]);
        return (int)($result['result'] ?? 0);
    }

    /**
     * Get file download URL by file_id.
     */
    public function getFileUrl(string $fileId): string {
        $result = $this->request('getFile', ['file_id' => $fileId]);
        $filePath = $result['result']['file_path'] ?? '';
        return $this->baseUrl . '/file/bot' . $this->botToken . '/' . $filePath;
    }

    /**
     * Download file content by file_id.
     */
    public function downloadFile(string $fileId): string {
        $url = $this->getFileUrl($fileId);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($data === false || $code >= 400) {
            throw new RuntimeException("Не удалось скачать файл из Telegram (HTTP {$code})");
        }

        return $data;
    }

    /**
     * JSON-based API request.
     */
    private function request(string $method, array $params): array {
        $url = $this->baseUrl . '/bot' . $this->botToken . '/' . $method;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($params, JSON_UNESCAPED_UNICODE),
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
            throw new RuntimeException("Telegram API curl error: {$err}");
        }
        curl_close($ch);

        $data = json_decode($response, true);

        if (!is_array($data) || empty($data['ok'])) {
            $errMsg = $data['description'] ?? "HTTP {$httpCode}";
            throw new RuntimeException("Telegram API error: {$errMsg}");
        }

        return $data;
    }

    /**
     * Multipart form-data request (for file uploads).
     */
    private function requestMultipart(string $method, array $params, array $files): array {
        $url = $this->baseUrl . '/bot' . $this->botToken . '/' . $method;

        $postFields = $params;
        foreach ($files as $key => $filePath) {
            $postFields[$key] = new \CURLFile($filePath, 'image/png', $key . '.png');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("Telegram API curl error: {$err}");
        }
        curl_close($ch);

        $data = json_decode($response, true);

        if (!is_array($data) || empty($data['ok'])) {
            $errMsg = $data['description'] ?? "HTTP {$httpCode}";
            throw new RuntimeException("Telegram API error: {$errMsg}");
        }

        return $data;
    }
}
