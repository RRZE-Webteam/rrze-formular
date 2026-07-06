<?php

declare(strict_types=1);

/**
 * Ensures rendered forms do not persist submission tokens.
 * Run: php tests/form-renderer-tokenless.php
 */

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__ . '/');
    }

    $transient_writes = 0;
    $rrze_formular_test_is_feed = false;

    function __($text, $domain = 'default')
    {
        return $text;
    }

    function esc_html__($text, $domain = 'default')
    {
        return esc_html($text);
    }

    function esc_html($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }

    function esc_html_e($text, $domain = 'default')
    {
        echo esc_html($text);
    }

    function esc_attr($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }

    function esc_url($url)
    {
        return (string) $url;
    }

    function wp_kses_post($text)
    {
        return (string) $text;
    }

    function sanitize_text_field($str)
    {
        return is_string($str) ? trim(strip_tags($str)) : '';
    }

    function sanitize_textarea_field($str)
    {
        return is_string($str) ? trim($str) : '';
    }

    function sanitize_key($key)
    {
        return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $key) ?? '');
    }

    function wp_unique_id($prefix = '')
    {
        static $id = 0;
        $id++;

        return $prefix . $id;
    }

    function get_the_ID()
    {
        return 42;
    }

    function is_feed()
    {
        global $rrze_formular_test_is_feed;

        return $rrze_formular_test_is_feed;
    }

    function wp_json_encode($data, $options = 0, $depth = 512)
    {
        return json_encode($data, $options, $depth);
    }

    function wp_salt($scheme = 'auth')
    {
        return 'test-salt-' . $scheme;
    }

    function set_transient($transient, $value, $expiration)
    {
        global $transient_writes;
        $transient_writes++;

        return true;
    }

    function get_option($name, $default = false)
    {
        return $default;
    }

    function apply_filters($hook, $value, ...$args)
    {
        return $value;
    }

    function home_url($path = '')
    {
        return 'https://example.test' . $path;
    }

    function user_trailingslashit($string)
    {
        return rtrim($string, '/') . '/';
    }

    function get_page_by_path($page_path, $output = OBJECT, $post_type = 'page')
    {
        return null;
    }

    function get_bloginfo($show = '', $filter = 'raw')
    {
        return 'en';
    }

    require dirname(__DIR__) . '/includes/Common/Form/FieldTypes.php';
    require dirname(__DIR__) . '/includes/Common/Form/AllowedDomains.php';
    require dirname(__DIR__) . '/includes/Common/Form/FormConfigAuth.php';
    require dirname(__DIR__) . '/includes/Common/Form/Privacy.php';
    require dirname(__DIR__) . '/includes/Common/Form/FormRenderer.php';

    use RRZE\Formular\Common\Form\FormRenderer;

    $failures = 0;

    function assert_true(bool $condition, string $message): void
    {
        global $failures;

        if (!$condition) {
            echo "FAIL: {$message}\n";
            $failures++;
        }
    }

    $html = FormRenderer::render([
        'formTitle' => 'Contact',
        'fields' => [
            ['id' => 'email', 'type' => 'email', 'label' => 'E-mail', 'placeholder' => '', 'required' => true],
        ],
    ]);

    assert_true($transient_writes === 0, 'Rendering a form must not write submission token transients');
    assert_true(str_contains($html, 'name="token" value=""'), 'Rendered form must ship with an empty token field');
    assert_true(str_contains($html, 'data-post-id="42"'), 'Rendered form must expose the post ID for token issuance');
    assert_true(!preg_match('/name="token" value="[^"]+"/', $html), 'Rendered HTML must not contain a prefilled token');

    define('REST_REQUEST', true);
    $transient_writes = 0;
    $restHtml = FormRenderer::render([
        'formTitle' => 'REST Contact',
        'fields' => [
            ['id' => 'email', 'type' => 'email', 'label' => 'E-mail', 'placeholder' => '', 'required' => true],
        ],
    ]);
    assert_true($transient_writes === 0, 'REST-rendered form output must not write submission token transients');
    assert_true(str_contains($restHtml, 'name="token" value=""'), 'REST-rendered form output must ship with an empty token field');

    $rrze_formular_test_is_feed = true;
    $transient_writes = 0;
    $feedHtml = FormRenderer::render([
        'formTitle' => 'Feed Contact',
        'fields' => [
            ['id' => 'email', 'type' => 'email', 'label' => 'E-mail', 'placeholder' => '', 'required' => true],
        ],
    ]);
    assert_true($transient_writes === 0, 'Feed-rendered form output must not write submission token transients');
    assert_true(str_contains($feedHtml, 'name="token" value=""'), 'Feed-rendered form output must ship with an empty token field');

    if ($failures === 0) {
        echo "OK: form renderer is tokenless\n";
        exit(0);
    }

    echo "{$failures} check(s) failed\n";
    exit(1);
}
