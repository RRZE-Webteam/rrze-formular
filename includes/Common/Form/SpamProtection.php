<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

class SpamProtection
{
    public static function createToken(string $configHash = ''): array
    {
        $issuedAt = time();
        $data = ['t' => $issuedAt];

        if ($configHash !== '') {
            $data['c'] = $configHash;
        }

        $payload = wp_json_encode($data);
        $signature = hash_hmac('sha256', (string) $payload, wp_salt('auth'));

        return [
            'token' => base64_encode($payload . '.' . $signature),
            'issuedAt' => $issuedAt,
        ];
    }

    public static function verifyToken(string $token, string $configHash = ''): bool
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

        if ($configHash !== '') {
            $tokenConfigHash = (string) ($data['c'] ?? '');
            if ($tokenConfigHash === '' || !hash_equals($configHash, $tokenConfigHash)) {
                return false;
            }
        }

        $options = get_option('rrze-formular', []);
        $minSeconds = max(1, (int) ($options['min_submit_seconds'] ?? 3));

        return (time() - (int) $data['t']) >= $minSeconds;
    }

    public static function checkHoneypot(string $value): bool
    {
        return trim($value) === '';
    }

    public static function isWithinRateLimit(): bool
    {
        $options = get_option('rrze-formular', []);
        $limit = max(1, (int) ($options['rate_limit_per_hour'] ?? 10));
        $key = self::getRateLimitKey();
        $count = (int) get_transient($key);

        return $count < $limit;
    }

    public static function recordSubmission(): void
    {
        $key = self::getRateLimitKey();
        $count = (int) get_transient($key);
        set_transient($key, $count + 1, HOUR_IN_SECONDS);
    }

    public static function isWithinConfirmationRateLimit(string $email): bool
    {
        $email = sanitize_email($email);
        if (!is_email($email)) {
            return false;
        }

        $options = get_option('rrze-formular', []);
        $limit = max(1, (int) ($options['confirmation_rate_limit_per_hour'] ?? 5));
        $key = self::getConfirmationRateLimitKey($email);
        $count = (int) get_transient($key);

        return $count < $limit;
    }

    public static function recordConfirmationSend(string $email): void
    {
        $email = sanitize_email($email);
        if (!is_email($email)) {
            return;
        }

        $key = self::getConfirmationRateLimitKey($email);
        $count = (int) get_transient($key);
        set_transient($key, $count + 1, HOUR_IN_SECONDS);
    }

    private static function getRateLimitKey(): string
    {
        return 'rrze_fw_rate_' . md5(self::getClientIp());
    }

    private static function getConfirmationRateLimitKey(string $email): string
    {
        return 'rrze_fw_confirm_' . md5(strtolower($email));
    }

    private static function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return sanitize_text_field((string) $ip);
    }
}
