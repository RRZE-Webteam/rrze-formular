<?php

declare(strict_types=1);

/**
 * Ensures settings save unslashes request data before sanitizing.
 * Run: php tests/settings-save-unslash.php
 */

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__ . '/');
    }

    $rrze_test_options = [];
    $rrze_test_filters = [];
    $rrze_test_verified_nonce = null;
    $rrze_test_died = false;

    class WP_Error
    {
        public array $errors = [];

        public function add(string $code, string $message): void
        {
            $this->errors[$code][] = $message;
        }

        public function get_error_message(string $code): string
        {
            return (string) ($this->errors[$code][0] ?? '');
        }
    }

    function __($text, $domain = 'default')
    {
        return $text;
    }

    function sanitize_title($title)
    {
        return strtolower(preg_replace('/[^a-z0-9_\-]+/', '-', (string) $title) ?? '');
    }

    function sanitize_key($key)
    {
        return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $key) ?? '');
    }

    function sanitize_text_field($value)
    {
        return trim(strip_tags((string) $value));
    }

    function sanitize_textarea_field($value)
    {
        return trim(strip_tags((string) $value));
    }

    function wp_unslash($value)
    {
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }

        return is_string($value) ? stripslashes($value) : $value;
    }

    function wp_parse_args($args, $defaults = [])
    {
        return array_merge((array) $defaults, (array) $args);
    }

    function wp_verify_nonce($nonce, $action = -1)
    {
        global $rrze_test_verified_nonce;
        $rrze_test_verified_nonce = $nonce;

        return $nonce === "valid'nonce";
    }

    function current_user_can($capability)
    {
        return $capability === 'manage_options';
    }

    function wp_die($message = '')
    {
        global $rrze_test_died;
        $rrze_test_died = true;
    }

    function get_option($name, $default = false)
    {
        global $rrze_test_options;

        return $rrze_test_options[$name] ?? $default;
    }

    function update_option($name, $value)
    {
        global $rrze_test_options;
        $rrze_test_options[$name] = $value;

        return true;
    }

    function apply_filters($hook, $value, ...$args)
    {
        global $rrze_test_filters;

        if (!isset($rrze_test_filters[$hook])) {
            return $value;
        }

        foreach ($rrze_test_filters[$hook] as $callback) {
            $value = $callback($value, ...$args);
        }

        return $value;
    }

    function do_action($hook, ...$args)
    {
    }

    function is_wp_error($thing)
    {
        return $thing instanceof WP_Error;
    }

    require dirname(__DIR__) . '/includes/Common/Settings/Error.php';
    require dirname(__DIR__) . '/includes/Common/Settings/Flash.php';
    require dirname(__DIR__) . '/includes/Common/Settings/Options/Type.php';
    require dirname(__DIR__) . '/includes/Common/Settings/Options/Text.php';
    require dirname(__DIR__) . '/includes/Common/Settings/Options/Textarea.php';
    require dirname(__DIR__) . '/includes/Common/Settings/Options/CheckboxMultiple.php';
    require dirname(__DIR__) . '/includes/Common/Settings/Option.php';
    require dirname(__DIR__) . '/includes/Common/Settings/Section.php';
    require dirname(__DIR__) . '/includes/Common/Settings/Tab.php';
    require dirname(__DIR__) . '/includes/Common/Settings/Settings.php';
}

namespace RRZE\Formular\Common\Settings {
    use RRZE\Formular\Common\Settings\Error;
    use RRZE\Formular\Common\Settings\Flash;
    use RRZE\Formular\Common\Settings\Settings;

    $failures = 0;

    function assert_true(bool $condition, string $message): void
    {
        global $failures;

        if (!$condition) {
            echo "FAIL: {$message}\n";
            $failures++;
        }
    }

    $settings = new Settings('RRZE Formular Test');
    $settings->setOptionName('rrze_test_settings');
    $settings->errors = new Error($settings);
    $settings->flash = new Flash($settings);

    $tab = $settings->addTab('General', 'general');
    $section = $tab->addSection('Main');
    $section->addOption('text', [
        'name' => 'text_value',
        'default' => '',
    ]);
    $section->addOption('textarea', [
        'name' => 'textarea_value',
        'default' => '',
    ]);
    $section->addOption('checkbox-multiple', [
        'name' => 'array_value',
        'default' => '',
    ]);

    $_GET = [];
    $_REQUEST = [];
    $_POST = [
        'rrze-formular_settings_save' => addslashes("valid'nonce"),
        'rrze_test_settings' => [
            'text_value' => addslashes("O'Reilly \\\\ path <b>tag</b>"),
            'textarea_value' => addslashes("Line \"quoted\" \\\\ <script>alert(1)</script>"),
            'array_value' => [
                addslashes("one\\two"),
                addslashes("quote's"),
            ],
        ],
    ];

    $settings->save();
    $saved = \get_option('rrze_test_settings', []);

    assert_true($GLOBALS['rrze_test_verified_nonce'] === "valid'nonce", 'Nonce must be unslashed before verification');
    assert_true($saved['text_value'] === "O'Reilly \\\\ path tag", 'Text option must be unslashed before sanitizing');
    assert_true($saved['textarea_value'] === 'Line "quoted" \\\\ alert(1)', 'Textarea option must be unslashed before sanitizing');
    assert_true($saved['array_value'] === ['one\\two', "quote's"], 'Array option values must be recursively unslashed');
    assert_true(!$GLOBALS['rrze_test_died'], 'Authorized save must not die');

    if ($failures === 0) {
        echo "OK: settings save unslash\n";
        exit(0);
    }

    echo "{$failures} check(s) failed\n";
    exit(1);
}
