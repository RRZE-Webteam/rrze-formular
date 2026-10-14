<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

/**
 * Manages signed submission tokens, honeypot checks and per-IP rate limits.
 *
 * Token nonces and counters use a persistent object cache when available.
 * Otherwise they use WordPress transients and options, unless the deployment
 * requires a persistent cache and disables the public endpoints without one.
 */
class SpamProtection
{
    /**
     * Default token lifetime in seconds before the token_ttl filter is applied.
     *
     * @var int
     */
    private const DEFAULT_TOKEN_TTL = 1800;

    /**
     * Object-cache group shared by token nonces and rate-limit counters.
     *
     * @var string
     */
    private const CACHE_GROUP = 'rrze_formular_spam';

    /**
     * Check whether token storage satisfies the deployment requirements.
     *
     * @return bool False only when a required persistent object cache is unavailable.
     */
    public static function publicEndpointsAvailable(): bool
    {
        if (!self::requiresPersistentObjectCacheForPublicEndpoints()) {
            return true;
        }

        return self::usePersistentObjectCache();
    }

    /**
     * Resolve whether public form endpoints require a persistent object cache.
     *
     * Multisite supplies the initial default. The plugin-level global can override
     * it, and rrze_formular_require_persistent_object_cache is applied last.
     *
     * @return bool Effective requirement after the plugin global and filter overrides.
     */
    public static function requiresPersistentObjectCacheForPublicEndpoints(): bool
    {
        $required = function_exists('is_multisite') && is_multisite();

        if (array_key_exists('rrze_formular_require_persistent_object_cache', $GLOBALS)) {
            $required = (bool) $GLOBALS['rrze_formular_require_persistent_object_cache'];
        }

        /**
         * Whether public form endpoints require a persistent object cache.
         *
         * The plugin-level global can define the site default; this filter is
         * the last override point for environment-specific deployments.
         */
        return (bool) apply_filters('rrze_formular_require_persistent_object_cache', $required);
    }

    /**
     * Return the public error shown when submission infrastructure is unavailable.
     *
     * @return string Translated message suitable for visitors.
     */
    public static function publicEndpointUnavailableMessage(): string
    {
        return __('The form could not be sent. Please try again later.', 'rrze-formular');
    }

    /**
     * Return the detailed storage error shown to administrators.
     *
     * @return string Translated explanation of the persistent-cache requirement.
     */
    public static function persistentObjectCacheRequiredMessage(): string
    {
        return __('Form submissions are currently unavailable because this installation requires a persistent object cache for public form tokens.', 'rrze-formular');
    }

    /**
     * Create a signed token and store its unused nonce until expiry.
     *
     * Token lifetime is filtered and clamped to 60–7200 seconds. This method writes
     * nonce state and belongs in the token endpoint, not in cached page rendering.
     *
     * @param string $formId Identifier of the rendered form instance.
     * @param string $configHash Hash of the trusted form configuration.
     * @param int $postId Optional page/post ID to bind to the token; zero omits that binding.
     * @return array{token: string, issuedAt: int} Encoded token and Unix issue timestamp.
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
     * Validate token signature, time window, minimum fill time and form binding.
     *
     * This check neither reads nor consumes nonce state. If the page URL resolves
     * to a post, its ID must match a nonzero post binding in the token.
     *
     * @param string $token Encoded token returned by createToken().
     * @param string $configHash Expected hash of the trusted form configuration.
     * @param string $pageUrl Optional submission URL used to check a resolvable post binding.
     * @return array<string, mixed>|null Decoded token payload, or null when a check fails.
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
     * Validate a token and claim its nonce before processing the submission.
     *
     * A successfully claimed token cannot be used again, even if later field
     * validation or mail delivery fails.
     *
     * @param string $token Encoded token returned by createToken().
     * @param string $configHash Expected hash of the trusted form configuration.
     * @param string $pageUrl Optional submission URL used to check a resolvable post binding.
     * @return array<string, mixed>|null Payload when valid and claimed, otherwise null.
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
     * Validate a token and check that its nonce is still present without consuming it.
     *
     * @param string $token Encoded token returned by createToken().
     * @param string $configHash Expected hash of the trusted form configuration.
     * @param string $pageUrl Optional submission URL used to check a resolvable post binding.
     * @return array<string, mixed>|null Payload when valid and unused, otherwise null.
     * @deprecated Use claimToken() for submission processing.
     * @see self::claimToken()
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
     * Consume an unused nonce by deleting its storage entry.
     *
     * The delete result determines which request claims the nonce. Call this
     * before delivery rather than checking and deleting in separate steps.
     *
     * @param string $nonce Unique nonce from an authenticated token payload.
     * @return bool Whether deletion succeeded; false for an empty or missing nonce.
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
     * Consume the nonce from a previously decoded token without reporting success.
     *
     * @param array<string, mixed> $tokenData Decoded payload containing a nonce.
     * @return void
     * @deprecated Use claimTokenNonce() before mail delivery.
     * @see self::claimTokenNonce()
     */
    public static function consumeToken(array $tokenData): void
    {
        self::claimTokenNonce((string) ($tokenData['nonce'] ?? ''));
    }

    /**
     * Accept only an empty hidden honeypot field.
     *
     * @param string $value Submitted honeypot value.
     * @return bool True when the trimmed value is empty.
     */
    public static function checkHoneypot(string $value): bool
    {
        return trim($value) === '';
    }

    /**
     * Count a submission attempt against the client IP hourly limit.
     *
     * @return bool Whether the incremented count is within the configured limit.
     */
    public static function tryAcquireSubmissionSlot(): bool
    {
        $options = Mailer::getOptions();
        $limit = max(1, (int) ($options['rate_limit_per_hour'] ?? 10));

        return self::tryIncrementCounter(self::getRateLimitKey(), HOUR_IN_SECONDS, $limit);
    }

    /**
     * Count a token request against the client IP minute limit.
     *
     * The token-issuance limit defaults to 30 and is clamped to 1–600 per minute.
     *
     * @return bool Whether the incremented count is within the filtered limit.
     */
    public static function tryAcquireTokenIssueSlot(): bool
    {
        $limit = (int) apply_filters('rrze_formular_token_rate_limit_per_minute', 30);
        $limit = max(1, min($limit, 600));

        return self::tryIncrementCounter(self::getTokenIssueRateLimitKey(), MINUTE_IN_SECONDS, $limit);
    }

    /**
     * Decode the token envelope and verify its HMAC before reading the payload.
     *
     * @param string $token Base64-encoded JSON payload and signature.
     * @return array<string, mixed>|null Authenticated JSON payload, or null for an invalid token.
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

    /**
     * Store a nonce as unused in the cache or transient backend.
     *
     * @param string $nonce Unique token nonce.
     * @param int $expiresAt Unix expiry timestamp; storage lasts at least 60 seconds.
     * @return void
     */
    private static function storeNonce(string $nonce, int $expiresAt): void
    {
        $ttl = max(60, $expiresAt - time());

        if (self::usePersistentObjectCache()) {
            wp_cache_add(self::getNonceKey($nonce), 1, self::CACHE_GROUP, $ttl);
            return;
        }

        set_transient(self::getNonceKey($nonce), 1, $ttl);
    }

    /**
     * Check whether nonce storage still contains an unused entry.
     *
     * @param string $nonce Token nonce to look up.
     * @return bool Whether the nonce exists; this does not consume it.
     */
    private static function isNonceValid(string $nonce): bool
    {
        if (self::usePersistentObjectCache()) {
            return wp_cache_get(self::getNonceKey($nonce), self::CACHE_GROUP) !== false;
        }

        return get_transient(self::getNonceKey($nonce)) !== false;
    }

    /**
     * Increment a rate-limit counter using the available storage backend.
     *
     * @param string $storageKey Logical key identifying the client and counter.
     * @param int $ttl Counter lifetime in seconds.
     * @param int $limit Maximum accepted count in that window.
     * @return bool Whether the resulting count is within the limit.
     */
    private static function tryIncrementCounter(string $storageKey, int $ttl, int $limit): bool
    {
        if (self::usePersistentObjectCache()) {
            return self::tryIncrementCacheCounter($storageKey, $ttl, $limit);
        }

        return self::tryIncrementOptionCounter($storageKey, $ttl, $limit);
    }

    /**
     * Create or increment a counter in the persistent object cache.
     *
     * An existing counter keeps its expiry. If incrementing fails, the counter is
     * recreated with a count of one and the supplied lifetime.
     *
     * @param string $storageKey Logical key identifying the client and counter.
     * @param int $ttl Counter lifetime in seconds.
     * @param int $limit Maximum accepted count in that window.
     * @return bool Whether the resulting count is within the limit.
     */
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

    /**
     * Increment a database-backed counter, resetting its value after expiry.
     *
     * The count uses an SQL upsert in the current site options table. A separate
     * non-autoloaded option stores the expiry timestamp.
     *
     * @param string $storageKey Logical key identifying the client and counter.
     * @param int $ttl Counter lifetime in seconds.
     * @param int $limit Maximum accepted count in that window.
     * @return bool Whether the database reports a positive count within the limit.
     */
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

    /**
     * Detect whether WordPress uses an external persistent object cache.
     *
     * @return bool Whether the object-cache backend is available.
     */
    private static function usePersistentObjectCache(): bool
    {
        return function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache();
    }

    /**
     * Hash a nonce to obtain its cache/transient key.
     *
     * @param string $nonce Unique token nonce.
     * @return string Namespaced storage key.
     */
    private static function getNonceKey(string $nonce): string
    {
        return 'rrze_fw_nonce_' . hash('sha256', $nonce);
    }

    /**
     * Build the submission-counter key for the current client IP.
     *
     * @return string Namespaced key containing the hashed client IP.
     */
    private static function getRateLimitKey(): string
    {
        return 'rrze_fw_rate_' . md5(self::getClientIp());
    }

    /**
     * Build the token-issuance counter key for the current client IP.
     *
     * @return string Namespaced key containing the hashed client IP.
     */
    private static function getTokenIssueRateLimitKey(): string
    {
        return 'rrze_fw_token_' . md5(self::getClientIp());
    }

    /**
     * Read the client address reported by the web server.
     *
     * Forwarded headers are not consulted. A trusted proxy setup must provide the
     * correct REMOTE_ADDR at the server level for per-client rate limiting.
     *
     * @return string Sanitized REMOTE_ADDR, or 0.0.0.0 when unavailable.
     */
    private static function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        return sanitize_text_field((string) $ip);
    }
}
