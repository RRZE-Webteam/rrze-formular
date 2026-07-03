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

    $options = [
        'rrze-formular' => [
            'rate_limit_per_hour' => '2',
        ],
    ];
    $wpdb_rows = [];

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
        global $wpdb_rows;
        $wpdb_rows['_transient_' . $transient] = $value;

        return true;
    }

    function get_transient($transient)
    {
        global $wpdb_rows;

        return $wpdb_rows['_transient_' . $transient] ?? false;
    }

    function wp_cache_delete($key, $group = '')
    {
        return true;
    }

    function sanitize_text_field($str)
    {
        return (string) $str;
    }

    function sanitize_email($email)
    {
        return trim((string) $email);
    }

    function is_email($email)
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
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

    $nonce = 'nonce-123';
    set_transient('rrze_fw_nonce_' . hash('sha256', $nonce), 1, 60);

    assert_true(SpamProtection::claimTokenNonce($nonce), 'First claim must succeed');
    assert_true(!SpamProtection::claimTokenNonce($nonce), 'Second claim must fail');
    assert_true(!SpamProtection::claimTokenNonce($nonce), 'Parallel-style second claim must stay rejected');

    assert_true(SpamProtection::tryAcquireSubmissionSlot(), 'First submission slot must be acquired');
    assert_true(SpamProtection::tryAcquireSubmissionSlot(), 'Second submission slot must be acquired');
    assert_true(!SpamProtection::tryAcquireSubmissionSlot(), 'Third submission slot must be rejected with limit 2');

    if ($failures === 0) {
        echo "OK: spam protection atomic checks passed\n";
        exit(0);
    }

    echo "{$failures} check(s) failed\n";
    exit(1);
}
