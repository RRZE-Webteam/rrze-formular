<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

class SpamProtection
{
    private const DEFAULT_TOKEN_TTL = 1800;
    private const CACHE_GROUP = 'rrze_formular_spam';

    public static function publicEndpointsAvailable(): bool
    {
        if (!self::requiresPersistentObjectCacheForPublicEndpoints()) {
            return true;
        }

        return self::usePersistentObjectCache();
    }

    public static function requiresPersistentObjectCacheForPublicEndpoints(): bool
    {
        $required = function_exists('is_multisite') && is_multisite();

        /**
         * Whether public form endpoints require a persistent object cache.
         *
         * The default is enabled on Multisite so anonymous token issuance and
         * rate limiting cannot create database write load through transients.
         */
        return (bool) apply_filters('rrze_formular_require_persistent_object_cache', $required);
    }

    public static function publicEndpointUnavailableMessage(): string
    {
        return __('The form could not be sent. Please try again later.', 'rrze-formular');
    }

    public static function persistentObjectCacheRequiredMessage(): string
    {
        return __('Form submissions are currently unavailable because this installation requires a persistent object cache for public form tokens.', 'rrze-formular');
    }

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
     * Validate token signature, timing and binding without consuming the nonce.
     *
     * @return array<string, mixed>|null
     */
    public static function validateTokenPayload(string $token, string $configHash, string $pageUrl = ''): ?array
    {
        $data = self::decodeToken($token);
        if ($data === null) {
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

        $options = Mailer::getOptions();
        $minSeconds = max(1, (int) ($options['min_submit_seconds'] ?? 3));
        if (($now - $issuedAt) < $minSeconds) {
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
     * @return array<string, mixed>|null Decoded token payload when valid and claimed.
     */
    public static function claimToken(string $token, string $configHash, string $pageUrl = ''): ?array
    {
        $data = self::validateTokenPayload($token, $configHash, $pageUrl);
        if ($data === null) {
            return null;
        }

        if (!self::claimTokenNonce((string) ($data['nonce'] ?? ''))) {
            return null;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>|null Decoded token payload when valid and nonce unused.
     * @deprecated Use claimToken() for submission processing.
     */
    public static function verifyToken(string $token, string $configHash, string $pageUrl = ''): ?array
    {
        $data = self::validateTokenPayload($token, $configHash, $pageUrl);
        if ($data === null) {
            return null;
        }

        return self::isNonceValid((string) ($data['nonce'] ?? '')) ? $data : null;
    }

    /**
     * Atomically consume a one-time nonce before processing a submission.
     */
    public static function claimTokenNonce(string $nonce): bool
    {
        $nonce = trim($nonce);

        if ($nonce === '') {
            return false;
        }

        if (self::usePersistentObjectCache()) {
            return (bool) wp_cache_delete(self::getNonceKey($nonce), self::CACHE_GROUP);
        }

        return (bool) delete_transient(self::getNonceKey($nonce));
    }

    /**
     * @param array<string, mixed> $tokenData
     * @deprecated Use claimTokenNonce() before mail delivery.
     */
    public static function consumeToken(array $tokenData): void
    {
        self::claimTokenNonce((string) ($tokenData['nonce'] ?? ''));
    }

    public static function checkHoneypot(string $value): bool
    {
        return trim($value) === '';
    }

    public static function tryAcquireSubmissionSlot(): bool
    {
        $options = Mailer::getOptions();
        $limit = max(1, (int) ($options['rate_limit_per_hour'] ?? 10));

        return self::tryIncrementCounter(self::getRateLimitKey(), HOUR_IN_SECONDS, $limit);
    }

    public static function tryAcquireTokenIssueSlot(): bool
    {
        $limit = (int) apply_filters('rrze_formular_token_rate_limit_per_minute', 30);
        $limit = max(1, min($limit, 600));

        return self::tryIncrementCounter(self::getTokenIssueRateLimitKey(), MINUTE_IN_SECONDS, $limit);
    }

    public static function tryAcquireConfirmationSlot(string $email): bool
    {
        $email = sanitize_email($email);
        if (!is_email($email)) {
            return false;
        }

        $options = Mailer::getOptions();
        $limit = max(1, (int) ($options['confirmation_rate_limit_per_hour'] ?? 3));

        return self::tryIncrementCounter(self::getConfirmationRateLimitKey($email), HOUR_IN_SECONDS, $limit);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeToken(string $token): ?array
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

        return is_array($data) ? $data : null;
    }

    private static function storeNonce(string $nonce, int $expiresAt): void
    {
        $ttl = max(60, $expiresAt - time());

        if (self::usePersistentObjectCache()) {
            wp_cache_add(self::getNonceKey($nonce), 1, self::CACHE_GROUP, $ttl);
            return;
        }

        set_transient(self::getNonceKey($nonce), 1, $ttl);
    }

    private static function isNonceValid(string $nonce): bool
    {
        if (self::usePersistentObjectCache()) {
            return wp_cache_get(self::getNonceKey($nonce), self::CACHE_GROUP) !== false;
        }

        return get_transient(self::getNonceKey($nonce)) !== false;
    }

    private static function tryIncrementCounter(string $storageKey, int $ttl, int $limit): bool
    {
        if (self::usePersistentObjectCache()) {
            return self::tryIncrementCacheCounter($storageKey, $ttl, $limit);
        }

        return self::tryIncrementOptionCounter($storageKey, $ttl, $limit);
    }

    private static function tryIncrementCacheCounter(string $storageKey, int $ttl, int $limit): bool
    {
        $name = 'rrze_fw_cnt_' . md5($storageKey);

        if (wp_cache_add($name, 1, self::CACHE_GROUP, $ttl)) {
            return true;
        }

        $count = wp_cache_incr($name, 1, self::CACHE_GROUP);
        if ($count === false) {
            wp_cache_set($name, 1, self::CACHE_GROUP, $ttl);
            $count = 1;
        }

        return (int) $count <= $limit;
    }

    private static function tryIncrementOptionCounter(string $storageKey, int $ttl, int $limit): bool
    {
        global $wpdb;

        $name = 'rrze_fw_cnt_' . md5($storageKey);
        $expiresName = $name . '_exp';
        $now = time();
        $expires = (int) get_option($expiresName, 0);

        if ($expires <= $now) {
            delete_option($name);
            update_option($expiresName, (string) ($now + $ttl), false);
        }

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '1', 'no')
             ON DUPLICATE KEY UPDATE option_value = CAST(option_value AS UNSIGNED) + 1",
            $name
        ));

        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
            $name
        ));

        return $count > 0 && $count <= $limit;
    }

    private static function usePersistentObjectCache(): bool
    {
        return function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache();
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

    private static function getTokenIssueRateLimitKey(): string
    {
        return 'rrze_fw_token_' . md5(self::getClientIp());
    }

    private static function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        return sanitize_text_field((string) $ip);
    }
}
