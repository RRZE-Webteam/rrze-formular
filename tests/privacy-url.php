<?php

declare(strict_types=1);

/**
 * Standalone checks for deterministic privacy URLs.
 * Run: php tests/privacy-url.php
 */

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__ . '/');
    }

    $rrze_test_language = 'en-US';
    $rrze_test_locale = 'en_US';

    function get_bloginfo(string $show = '', string $filter = 'raw'): string
    {
        return $show === 'language' ? $GLOBALS['rrze_test_language'] : '';
    }

    function get_locale(): string
    {
        return $GLOBALS['rrze_test_locale'];
    }

    function home_url(string $path = ''): string
    {
        return 'https://example.org' . $path;
    }

    function user_trailingslashit(string $url): string
    {
        return rtrim($url, '/') . '/';
    }

    function esc_url(string $url): string
    {
        return $url;
    }

    function esc_attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    function esc_html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

namespace RRZE\Formular\Common\Form {
    require_once dirname(__DIR__) . '/includes/Common/Form/Privacy.php';

    function assert_same(string $actual, string $expected, string $message): void
    {
        if ($actual !== $expected) {
            fwrite(STDERR, "FAIL: {$message}\nExpected: {$expected}\nActual:   {$actual}\n");
            exit(1);
        }
    }

    $GLOBALS['rrze_test_language'] = 'de-DE';
    assert_same(
        Privacy::getPageUrl(),
        'https://example.org/datenschutz/',
        'German language uses /datenschutz'
    );

    $GLOBALS['rrze_test_language'] = 'de-DE-formal';
    assert_same(
        Privacy::getPageUrl(),
        'https://example.org/datenschutz/',
        'German formal language uses /datenschutz'
    );

    $GLOBALS['rrze_test_language'] = '';
    $GLOBALS['rrze_test_locale'] = 'de_DE_formal';
    assert_same(
        Privacy::getPageUrl(),
        'https://example.org/datenschutz/',
        'German formal locale fallback uses /datenschutz'
    );

    $GLOBALS['rrze_test_language'] = 'en-US';
    $GLOBALS['rrze_test_locale'] = 'en_US';
    assert_same(
        Privacy::getPageUrl(),
        'https://example.org/privacy/',
        'Non-German language uses /privacy'
    );

    assert_same(
        Privacy::renderLink(),
        '<p class="rrze-formular__privacy"><a href="https://example.org/privacy/#contact">Privacy</a></p>',
        'Rendered link uses deterministic privacy URL and contact fragment'
    );

    echo "OK: privacy URLs\n";
}
