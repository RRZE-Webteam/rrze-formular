<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

class SpamProtection
{
    public static function createToken(): array
    {
        $issuedAt = time();
        $payload = wp_json_encode(['t' => $issuedAt]);
        $signature = hash_hmac('sha256', (string) $payload, wp_salt('auth'));

        return [
            'token' => base64_encode($payload . '.' . $signature),
            'issuedAt' => $issuedAt,
        ];
    }

    public static function verifyToken(string $token): bool
    {
        $decoded = base64_decode($token, true);
        if ($decoded === false || !str_contains($decoded, '.')) {
            return false;
        }

        [$payload, $signature] = explode('.', $decoded, 2);
        $expected = hash_hmac('sha256', $payload, wp_salt('auth'));

        if (!hash_equals($expected, $signature)) {
            return false;
        }

        $data = json_decode($payload, true);
        if (!is_array($data) || empty($data['t'])) {
            return false;
        }

        $options = get_option('rrze-formular', []);
        $minSeconds = max(1, (int) ($options['min_submit_seconds'] ?? 3));

        return (time() - (int) $data['t']) >= $minSeconds;
    }

    public static function checkHoneypot(string $value): bool
    {
        return trim($value) === '';
    }

    public static function checkRateLimit(): bool
    {
        $options = get_option('rrze-formular', []);
        $limit = max(1, (int) ($options['rate_limit_per_hour'] ?? 10));
        $ip = self::getClientIp();
        $key = 'rrze_fw_rate_' . md5($ip);
        $count = (int) get_transient($key);

        if ($count >= $limit) {
            return false;
        }

        set_transient($key, $count + 1, HOUR_IN_SECONDS);
        return true;
    }

    private static function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return sanitize_text_field((string) $ip);
    }
}
