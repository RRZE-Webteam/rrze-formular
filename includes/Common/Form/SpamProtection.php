<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

class SpamProtection
{
    private const DEFAULT_TOKEN_TTL = 1800;

    /**
     * @return array{token: string, issuedAt: int}
     */
    public static function createToken(string $formId, string $configHash, int $postId = 0): array
    {
        $issuedAt = time();
        $ttl = (int) apply_filters('rrze_formular_token_ttl', self::DEFAULT_TOKEN_TTL);
        $ttl = max(60, min($ttl, 7200));
        $expiresAt = $issuedAt + $ttl;
        $nonce = wp_generate_uuid4();

        $data = [
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'form_id' => sanitize_key($formId),
            'post_id' => max(0, $postId),
            'config' => $configHash,
            'nonce' => $nonce,
        ];

        $payload = wp_json_encode($data, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', (string) $payload, wp_salt('rrze-formular-token'));

        self::storeNonce($nonce, $expiresAt);

        return [
            'token' => base64_encode($payload . '.' . $signature),
            'issuedAt' => $issuedAt,
        ];
    }

    /**
     * @return array<string, mixed>|null Decoded token payload when valid.
     */
    public static function verifyToken(string $token, string $configHash, string $pageUrl = ''): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $decoded = base64_decode($token, true);
        if ($decoded === false || !str_contains($decoded, '.')) {
            return null;
        }

        [$payload, $signature] = explode('.', $decoded, 2);
        $expected = hash_hmac('sha256', $payload, wp_salt('rrze-formular-token'));

        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $data = json_decode($payload, true);
        if (!is_array($data)) {
            return null;
        }

        $issuedAt = (int) ($data['issued_at'] ?? 0);
        $expiresAt = (int) ($data['expires_at'] ?? 0);
        $nonce = (string) ($data['nonce'] ?? '');
        $tokenConfigHash = (string) ($data['config'] ?? '');
        $formId = sanitize_key((string) ($data['form_id'] ?? ''));
        $tokenPostId = (int) ($data['post_id'] ?? 0);

        if ($issuedAt <= 0 || $expiresAt <= 0 || $nonce === '' || $formId === '') {
            return null;
        }

        if ($configHash === '' || !hash_equals($configHash, $tokenConfigHash)) {
            return null;
        }

        $now = time();
        if ($now < $issuedAt || $now > $expiresAt) {
            return null;
        }

        $options = get_option('rrze-formular', []);
        $minSeconds = max(1, (int) ($options['min_submit_seconds'] ?? 3));
        if (($now - $issuedAt) < $minSeconds) {
            return null;
        }

        if (!self::isNonceValid($nonce)) {
            return null;
        }

        if ($tokenPostId > 0) {
            $submissionUrl = Mailer::resolveSubmissionUrl($pageUrl);
            if ($submissionUrl !== '') {
                $submitPostId = (int) url_to_postid($submissionUrl);
                if ($submitPostId > 0 && $submitPostId !== $tokenPostId) {
                    return null;
                }
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $tokenData
     */
    public static function consumeToken(array $tokenData): void
    {
        $nonce = (string) ($tokenData['nonce'] ?? '');
        if ($nonce === '') {
            return;
        }

        delete_transient(self::getNonceKey($nonce));
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

    private static function storeNonce(string $nonce, int $expiresAt): void
    {
        $ttl = max(60, $expiresAt - time());
        set_transient(self::getNonceKey($nonce), 1, $ttl);
    }

    private static function isNonceValid(string $nonce): bool
    {
        return get_transient(self::getNonceKey($nonce)) !== false;
    }

    private static function getNonceKey(string $nonce): string
    {
        return 'rrze_fw_nonce_' . hash('sha256', $nonce);
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
