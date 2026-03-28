<?php
/**
 * WhatsApp Cloud API sender (Meta/Facebook Graph API).
 *
 * NOTE: In production, free-form messages may only work inside an active
 * customer session. Otherwise you must use a template.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * @return array{ok:bool,error:?string,raw:?array}
 */
function whatsapp_cloud_send_text(string $toE164Digits, string $body): array
{
    $accessToken = defined('WHATSAPP_CLOUD_ACCESS_TOKEN') ? trim((string) WHATSAPP_CLOUD_ACCESS_TOKEN) : '';
    $phoneNumberId = defined('WHATSAPP_CLOUD_PHONE_NUMBER_ID') ? trim((string) WHATSAPP_CLOUD_PHONE_NUMBER_ID) : '';

    if ($accessToken === '' || $phoneNumberId === '') {
        return [
            'ok' => false,
            'error' => 'WhatsApp Cloud API is not configured (missing access token / phone number id).',
            'raw' => null,
        ];
    }

    $toDigits = preg_replace('/\D/', '', $toE164Digits) ?? '';
    if ($toDigits === '') {
        return ['ok' => false, 'error' => 'Invalid destination phone number.', 'raw' => null];
    }

    $url = 'https://graph.facebook.com/v20.0/' . $phoneNumberId . '/messages';

    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $toDigits,
        'type' => 'text',
        'text' => [
            'body' => $body,
        ],
    ];

    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'error' => 'curl_init failed.', 'raw' => null];
    }

    $json = json_encode($payload);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_TIMEOUT => 25,
    ]);

    $raw = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = null;
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
    }

    if ($raw === false || $raw === null || $raw === '') {
        return [
            'ok' => false,
            'error' => 'HTTP request failed. curl_error=' . ($curlErr ?: 'unknown'),
            'raw' => $decoded,
        ];
    }

    if ($httpCode >= 400) {
        $apiError = null;
        if (is_array($decoded)) {
            $apiError = $decoded['error']['message'] ?? ($decoded['message'] ?? null);
        }
        $apiErrorStr = $apiError ? (string) $apiError : ('HTTP ' . $httpCode);
        return [
            'ok' => false,
            'error' => 'WhatsApp Cloud API failed: ' . $apiErrorStr,
            'raw' => is_array($decoded) ? $decoded : null,
        ];
    }

    return [
        'ok' => true,
        'error' => null,
        'raw' => is_array($decoded) ? $decoded : null,
    ];
}

