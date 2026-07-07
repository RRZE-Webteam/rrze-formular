<?php

declare(strict_types=1);

/**
 * Ensures submissions never send a second mail to the submitter address.
 * Run: php tests/operator-mail-only.php
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

    $rrze_test_options = [
        'rrze-formular' => [
            'allowed_domains' => 'example.org',
            'rate_limit_per_hour' => '10',
            'min_submit_seconds' => '1',
            'include_sso_by_default' => '',
        ],
        'admin_email' => 'admin@example.org',
    ];
    $rrze_test_transients = [];
    $rrze_test_mail_log = [];

    class RRZE_Operator_Mail_Test_WPDB
    {
        public string $options = 'wp_options';

        public function prepare($query, ...$args)
        {
            return [$query, ...$args];
        }

        public function query($query)
        {
            global $rrze_test_options;

            if (!is_array($query)) {
                return 0;
            }

            [$sql, $name] = $query;
            if (is_string($sql) && str_contains($sql, 'INSERT INTO') && str_contains($sql, 'ON DUPLICATE KEY UPDATE')) {
                $current = (int) ($rrze_test_options[(string) $name] ?? 0);
                $rrze_test_options[(string) $name] = (string) ($current + 1);

                return 1;
            }

            return 0;
        }

        public function get_var($query)
        {
            global $rrze_test_options;

            if (!is_array($query)) {
                return '0';
            }

            [, $name] = $query;

            return (string) ($rrze_test_options[(string) $name] ?? '0');
        }
    }

    $GLOBALS['wpdb'] = new RRZE_Operator_Mail_Test_WPDB();

    function __($text, $domain = 'default')
    {
        return $text;
    }

    function get_option($name, $default = false)
    {
        global $rrze_test_options;

        return $rrze_test_options[$name] ?? $default;
    }

    function update_option($name, $value, $autoload = null)
    {
        global $rrze_test_options;
        $rrze_test_options[$name] = $value;

        return true;
    }

    function delete_option($name)
    {
        global $rrze_test_options;
        unset($rrze_test_options[$name]);

        return true;
    }

    function set_transient($transient, $value, $expiration)
    {
        global $rrze_test_transients;
        $rrze_test_transients[$transient] = $value;

        return true;
    }

    function get_transient($transient)
    {
        global $rrze_test_transients;

        return $rrze_test_transients[$transient] ?? false;
    }

    function delete_transient($transient)
    {
        global $rrze_test_transients;
        if (!array_key_exists($transient, $rrze_test_transients)) {
            return false;
        }

        unset($rrze_test_transients[$transient]);

        return true;
    }

    function wp_using_ext_object_cache(): bool
    {
        return false;
    }

    function wp_json_encode($data, $flags = 0, $depth = 512)
    {
        return json_encode($data, $flags, $depth);
    }

    function sanitize_key($key): string
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key)) ?? '';
    }

    function sanitize_text_field($value): string
    {
        return trim(strip_tags((string) $value));
    }

    function sanitize_textarea_field($value): string
    {
        return trim(strip_tags((string) $value));
    }

    function sanitize_email($email): string
    {
        return trim((string) $email);
    }

    function sanitize_user($username, $strict = false): string
    {
        return preg_replace('/[^a-z0-9_.@-]/i', '', (string) $username) ?? '';
    }

    function is_email($email): bool
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    function is_plugin_active($plugin): bool
    {
        return false;
    }

    function is_multisite(): bool
    {
        return false;
    }

    function wp_salt($scheme = 'auth'): string
    {
        return 'test-salt-' . $scheme;
    }

    function wp_generate_uuid4(): string
    {
        return '00000000-0000-4000-8000-000000000001';
    }

    function apply_filters($hook, $value, ...$args)
    {
        return $value;
    }

    function is_user_logged_in(): bool
    {
        return false;
    }

    function wp_get_current_user(): object
    {
        return (object) ['display_name' => '', 'user_email' => '', 'user_login' => ''];
    }

    function wp_get_referer()
    {
        return false;
    }

    function home_url($path = ''): string
    {
        return 'https://site.example.org' . (string) $path;
    }

    function esc_url_raw($url): string
    {
        return (string) $url;
    }

    function wp_http_validate_url($url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    function wp_parse_url($url, $component = -1)
    {
        return parse_url((string) $url, $component);
    }

    function url_to_postid($url): int
    {
        return 0;
    }

    function get_bloginfo($show = '', $filter = 'raw'): string
    {
        if ($show === 'name') {
            return 'Example Site';
        }

        if ($show === 'language') {
            return 'en-US';
        }

        return '';
    }

    function get_locale(): string
    {
        return 'en_US';
    }

    function wp_date($format): string
    {
        return date((string) $format, 1700000000);
    }

    function get_user_by($field, $value)
    {
        return false;
    }

    function add_action($hook, $callback, $priority = 10, $accepted_args = 1): void
    {
    }

    function remove_action($hook, $callback, $priority = 10): void
    {
    }

    function wp_mail($to, $subject, $message, $headers = [], $attachments = []): bool
    {
        global $rrze_test_mail_log;
        $rrze_test_mail_log[] = compact('to', 'subject', 'message', 'headers', 'attachments');

        return true;
    }

    function unload_textdomain($domain): bool
    {
        return true;
    }

    function load_textdomain($domain, $mofile): bool
    {
        return true;
    }

    function load_plugin_textdomain($domain, $deprecated = false, $plugin_rel_path = false): bool
    {
        return true;
    }

    function plugin_basename($file): string
    {
        return basename((string) $file);
    }
}

namespace RRZE\Formular {
    function plugin(): object
    {
        return new class {
            public function getPath(): string
            {
                return dirname(__DIR__) . '/';
            }

            public function getFile(): string
            {
                return dirname(__DIR__) . '/rrze-formular.php';
            }
        };
    }
}

namespace RRZE\Formular\Common\Form {
    require_once dirname(__DIR__) . '/includes/Common/Form/AllowedDomains.php';
    require_once dirname(__DIR__) . '/includes/Common/Form/FieldTypes.php';
    require_once dirname(__DIR__) . '/includes/Common/Form/FormConfigAuth.php';
    require_once dirname(__DIR__) . '/includes/Common/Form/FormLocale.php';
    require_once dirname(__DIR__) . '/includes/Common/Form/Mailer.php';
    require_once dirname(__DIR__) . '/includes/Common/Form/SpamProtection.php';
    require_once dirname(__DIR__) . '/includes/Common/Form/SSO.php';
    require_once dirname(__DIR__) . '/includes/Common/Form/FormHandler.php';

    function assert_true(bool $condition, string $message): void
    {
        if (!$condition) {
            fwrite(STDERR, "FAIL: {$message}\n");
            exit(1);
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

    $rawConfig = [
        'formTitle' => 'Contact',
        'recipientEmail' => 'team@example.org',
        'recipientName' => 'Team',
        'sendConfirmation' => true,
        'fields' => [
            ['id' => 'email', 'type' => 'email', 'label' => 'E-mail address', 'required' => true],
            ['id' => 'message', 'type' => 'textarea', 'label' => 'Message', 'required' => true],
        ],
    ];

    $trustedConfig = FormConfigAuth::buildTrustedConfig($rawConfig);
    $signature = FormConfigAuth::sign($rawConfig);
    $nonce = 'operator-only-nonce';
    set_transient('rrze_fw_nonce_' . hash('sha256', $nonce), 1, 60);

    $handler = new FormHandler();
    $result = $handler->handle([
        'formConfig' => $signature['payload'],
        'formConfigSig' => $signature['signature'],
        'values' => [
            'email' => 'victim@outside.example',
            'message' => 'Hello',
        ],
        'website' => '',
        'token' => build_test_token($nonce, FormConfigAuth::configHash($trustedConfig)),
        'pageUrl' => 'https://site.example.org/contact/',
        'formLocale' => 'en-US',
    ]);

    assert_true(($result['success'] ?? false) === true, 'Submission must succeed');
    assert_true(count($GLOBALS['rrze_test_mail_log']) === 1, 'Submission must send exactly one mail');
    assert_true(
        str_contains((string) $GLOBALS['rrze_test_mail_log'][0]['to'], 'team@example.org'),
        'The only mail must go to the configured operator recipient'
    );
    assert_true(
        !str_contains((string) $GLOBALS['rrze_test_mail_log'][0]['to'], 'victim@outside.example'),
        'The submitter address must never be used as mail recipient'
    );

    echo "OK: operator mail only\n";
}
