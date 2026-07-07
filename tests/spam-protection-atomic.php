<?php

declare(strict_types=1);

/**
 * Checks atomic one-time nonce consumption.
 * Run: php tests/spam-protection-atomic.php
 */

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__ . '/');
    }

    if (!defined('HOUR_IN_SECONDS')) {
        define('HOUR_IN_SECONDS', 3600);
    }

    if (!defined('MINUTE_IN_SECONDS')) {
        define('MINUTE_IN_SECONDS', 60);
    }

    $options = [
        'rrze-formular' => [
            'rate_limit_per_hour' => '2',
            'min_submit_seconds' => '1',
        ],
    ];
    $wpdb_rows = [];
    $object_cache_rows = [];
    $use_ext_object_cache = false;
    $is_multisite = false;
    $test_filters = [];

    class RRZE_Test_WPDB
    {
        public string $options = 'wp_options';

        public function prepare($query, ...$args)
        {
            return [$query, ...$args];
        }

        public function query($query)
        {
            global $wpdb_rows, $options;

            if (!is_array($query)) {
                return 0;
            }

            [$sql, $value] = $query;

            if (is_string($sql) && str_contains($sql, 'INSERT INTO') && str_contains($sql, 'ON DUPLICATE KEY UPDATE')) {
                $name = (string) $value;
                $current = (int) ($options[$name] ?? 0);
                $options[$name] = (string) ($current + 1);

                return 1;
            }

            if (!is_string($sql) || !str_contains($sql, 'DELETE FROM')) {
                return 0;
            }

            $option = (string) $value;
            if (!isset($wpdb_rows[$option])) {
                return 0;
            }

            unset($wpdb_rows[$option]);

            return 1;
        }

        public function get_var($query)
        {
            global $options;

            if (!is_array($query)) {
                return '0';
            }

            [$sql, $name] = $query;
            if (!is_string($sql) || !str_contains($sql, 'SELECT option_value')) {
                return '0';
            }

            return (string) ($options[(string) $name] ?? '0');
        }
    }

    $GLOBALS['wpdb'] = new RRZE_Test_WPDB();

    function get_option($name, $default = false)
    {
        global $options;

        return $options[$name] ?? $default;
    }

    function update_option($name, $value, $autoload = null)
    {
        global $options;
        $options[$name] = $value;

        return true;
    }

    function delete_option($name)
    {
        global $options;
        unset($options[$name]);

        return true;
    }

    function set_transient($transient, $value, $expiration)
    {
        global $wpdb_rows, $object_cache_rows, $use_ext_object_cache;
        if ($use_ext_object_cache) {
            $object_cache_rows[$transient] = $value;

            return true;
        }

        $wpdb_rows['_transient_' . $transient] = $value;

        return true;
    }

    function get_transient($transient)
    {
        global $wpdb_rows, $object_cache_rows, $use_ext_object_cache;
        if ($use_ext_object_cache) {
            return $object_cache_rows[$transient] ?? false;
        }

        return $wpdb_rows['_transient_' . $transient] ?? false;
    }

    function delete_transient($transient)
    {
        global $wpdb_rows, $object_cache_rows, $use_ext_object_cache;
        if ($use_ext_object_cache) {
            if (!isset($object_cache_rows[$transient])) {
                return false;
            }

            unset($object_cache_rows[$transient]);

            return true;
        }

        $option = '_transient_' . $transient;
        if (!isset($wpdb_rows[$option])) {
            return false;
        }

        unset($wpdb_rows[$option], $wpdb_rows['_transient_timeout_' . $transient]);

        return true;
    }

    function wp_using_ext_object_cache()
    {
        global $use_ext_object_cache;

        return $use_ext_object_cache;
    }

    function is_multisite(): bool
    {
        global $is_multisite;

        return $is_multisite;
    }

    function wp_cache_key(string $key, string $group = ''): string
    {
        return $group . ':' . $key;
    }

    function wp_cache_add($key, $value, $group = '', $expiration = 0)
    {
        global $object_cache_rows;

        $cacheKey = wp_cache_key((string) $key, (string) $group);
        if (array_key_exists($cacheKey, $object_cache_rows)) {
            return false;
        }

        $object_cache_rows[$cacheKey] = $value;

        return true;
    }

    function wp_cache_set($key, $value, $group = '', $expiration = 0)
    {
        global $object_cache_rows;

        $object_cache_rows[wp_cache_key((string) $key, (string) $group)] = $value;

        return true;
    }

    function wp_cache_get($key, $group = '')
    {
        global $object_cache_rows;

        $cacheKey = wp_cache_key((string) $key, (string) $group);

        return $object_cache_rows[$cacheKey] ?? false;
    }

    function wp_cache_incr($key, $offset = 1, $group = '')
    {
        global $object_cache_rows;

        $cacheKey = wp_cache_key((string) $key, (string) $group);
        if (!array_key_exists($cacheKey, $object_cache_rows)) {
            return false;
        }

        $object_cache_rows[$cacheKey] = (int) $object_cache_rows[$cacheKey] + (int) $offset;

        return $object_cache_rows[$cacheKey];
    }

    function wp_cache_delete($key, $group = '')
    {
        global $object_cache_rows;

        $cacheKey = wp_cache_key((string) $key, (string) $group);
        if (!array_key_exists($cacheKey, $object_cache_rows)) {
            return false;
        }

        unset($object_cache_rows[$cacheKey]);

        return true;
    }

    function sanitize_text_field($str)
    {
        return (string) $str;
    }

    function sanitize_key($key)
    {
        return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $key) ?? '');
    }

    function wp_json_encode($data, $options = 0, $depth = 512)
    {
        return json_encode($data, $options, $depth);
    }

    function wp_salt($scheme = 'auth')
    {
        return 'test-salt-' . $scheme;
    }

    function wp_generate_uuid4()
    {
        return '00000000-0000-4000-8000-000000000001';
    }

    function url_to_postid($url)
    {
        return 0;
    }

    function sanitize_email($email)
    {
        return trim((string) $email);
    }

    function is_email($email)
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    function apply_filters($hook, $value, ...$args)
    {
        global $test_filters;

        return $test_filters[$hook] ?? $value;
    }

    require dirname(__DIR__) . '/includes/Common/Form/Mailer.php';
    require dirname(__DIR__) . '/includes/Common/Form/SpamProtection.php';

    use RRZE\Formular\Common\Form\SpamProtection;

    $failures = 0;

    function assert_true(bool $condition, string $message): void
    {
        global $failures;

        if (!$condition) {
            echo "FAIL: {$message}\n";
            $failures++;
        }
    }

    function build_test_token(string $nonce, string $configHash): string
    {
        $payload = wp_json_encode([
            'issued_at' => time() - 5,
            'expires_at' => time() + 300,
            'form_id' => 'rrze-fw-test',
            'post_id' => 0,
            'config' => $configHash,
            'nonce' => $nonce,
        ], JSON_UNESCAPED_SLASHES);

        return base64_encode($payload . '.' . hash_hmac('sha256', (string) $payload, wp_salt('rrze-formular-token')));
    }

    $nonce = 'nonce-123';
    set_transient('rrze_fw_nonce_' . hash('sha256', $nonce), 1, 60);

    assert_true(SpamProtection::claimTokenNonce($nonce), 'First claim must succeed');
    assert_true(!SpamProtection::claimTokenNonce($nonce), 'Second claim must fail');
    assert_true(!SpamProtection::claimTokenNonce($nonce), 'Parallel-style second claim must stay rejected');

    $use_ext_object_cache = true;
    $objectCacheNonce = 'nonce-object-cache';
    wp_cache_add(
        'rrze_fw_nonce_' . hash('sha256', $objectCacheNonce),
        1,
        'rrze_formular_spam',
        60
    );

    assert_true(SpamProtection::claimTokenNonce($objectCacheNonce), 'Object-cache transient claim must succeed');
    assert_true(!SpamProtection::claimTokenNonce($objectCacheNonce), 'Object-cache transient claim must be one-time');

    $wpdb_rows = [];
    $object_cache_rows = [];
    SpamProtection::createToken('rrze-fw-test', 'config-hash', 0);
    assert_true($wpdb_rows === [], 'Object-cache token creation must not write transient rows');
    assert_true($object_cache_rows !== [], 'Object-cache token creation must store nonce in object cache');

    $use_ext_object_cache = false;

    $claimNonce = 'nonce-claim-token';
    $configHash = 'config-hash';
    $token = build_test_token($claimNonce, $configHash);
    set_transient('rrze_fw_nonce_' . hash('sha256', $claimNonce), 1, 60);

    assert_true(is_array(SpamProtection::claimToken($token, $configHash)), 'Token claim must validate and consume in one step');
    assert_true(SpamProtection::claimToken($token, $configHash) === null, 'Second token claim must fail');
    assert_true(SpamProtection::verifyToken($token, $configHash) === null, 'Consumed token must no longer verify');

    assert_true(SpamProtection::tryAcquireSubmissionSlot(), 'First submission slot must be acquired');
    assert_true(SpamProtection::tryAcquireSubmissionSlot(), 'Second submission slot must be acquired');
    assert_true(!SpamProtection::tryAcquireSubmissionSlot(), 'Third submission slot must be rejected with limit 2');

    $optionsBeforeObjectCacheCounters = $options;
    $object_cache_rows = [];
    $use_ext_object_cache = true;

    assert_true(SpamProtection::tryAcquireSubmissionSlot(), 'Object-cache submission slot 1 must be acquired');
    assert_true(SpamProtection::tryAcquireSubmissionSlot(), 'Object-cache submission slot 2 must be acquired');
    assert_true(!SpamProtection::tryAcquireSubmissionSlot(), 'Object-cache submission slot 3 must be rejected');
    assert_true($options === $optionsBeforeObjectCacheCounters, 'Object-cache counters must not write options');

    $test_filters['rrze_formular_token_rate_limit_per_minute'] = 2;
    assert_true(SpamProtection::tryAcquireTokenIssueSlot(), 'Object-cache token slot 1 must be acquired');
    assert_true(SpamProtection::tryAcquireTokenIssueSlot(), 'Object-cache token slot 2 must be acquired');
    assert_true(!SpamProtection::tryAcquireTokenIssueSlot(), 'Object-cache token slot 3 must be rejected');
    assert_true($options === $optionsBeforeObjectCacheCounters, 'Token issue limiter must not write options with object cache');

    $use_ext_object_cache = false;
    $is_multisite = false;
    assert_true(SpamProtection::publicEndpointsAvailable(), 'Single-site may use DB fallback when no persistent object cache exists');

    $is_multisite = true;
    assert_true(!SpamProtection::publicEndpointsAvailable(), 'Multisite must block public endpoints without persistent object cache by default');

    $use_ext_object_cache = true;
    assert_true(SpamProtection::publicEndpointsAvailable(), 'Multisite may use public endpoints with persistent object cache');

    $use_ext_object_cache = false;
    $test_filters['rrze_formular_require_persistent_object_cache'] = false;
    assert_true(SpamProtection::publicEndpointsAvailable(), 'Filter may explicitly allow DB fallback for public endpoints');

    if ($failures === 0) {
        echo "OK: spam protection atomic checks passed\n";
        exit(0);
    }

    echo "{$failures} check(s) failed\n";
    exit(1);
}
