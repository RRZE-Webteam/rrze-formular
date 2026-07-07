<?php

declare(strict_types=1);

/**
 * Standalone checks for recipient domain allowlist behavior.
 * Run: php tests/allowed-domains-fallback.php
 */

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__ . '/');
    }

    $rrze_test_options = [
        'rrze-formular' => [
            'allowed_domains' => '',
        ],
        'admin_email' => 'admin@uni-example.de',
    ];
    $rrze_test_site_options = [];
    $rrze_test_filters = [];

    function get_option(string $name, mixed $default = false): mixed
    {
        global $rrze_test_options;

        return $rrze_test_options[$name] ?? $default;
    }

    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
    {
        return $GLOBALS['rrze_test_filters'][$hook] ?? $value;
    }

    function sanitize_email(string $email): string
    {
        return trim($email);
    }

    function is_email(string $email): bool
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    function is_plugin_active(string $plugin): bool
    {
        return false;
    }

    function is_multisite(): bool
    {
        return false;
    }

    function get_site_option(string $name, mixed $default = false): mixed
    {
        return $GLOBALS['rrze_test_site_options'][$name] ?? $default;
    }

    function get_user_by(string $field, mixed $value): false
    {
        return false;
    }

    function get_bloginfo(string $show = '', string $filter = 'raw'): string
    {
        return $show === 'name' ? 'Example University' : '';
    }
}

namespace RRZE\Formular\Common\Form {
    require_once dirname(__DIR__) . '/includes/Common/Form/AllowedDomains.php';
    require_once dirname(__DIR__) . '/includes/Common/Form/Mailer.php';

    function reset_allowed_domains_cache(): void
    {
        $reflection = new \ReflectionClass(AllowedDomains::class);

        foreach (['allowedDomains', 'rrzeSettingsActive'] as $property) {
            if ($reflection->hasProperty($property)) {
                $prop = $reflection->getProperty($property);
                $prop->setValue(null, null);
            }
        }

        $mailer = new \ReflectionClass(Mailer::class);
        if ($mailer->hasProperty('optionsCache')) {
            $prop = $mailer->getProperty('optionsCache');
            $prop->setValue(null, null);
        }
    }

    function assert_true(bool $condition, string $message): void
    {
        if (!$condition) {
            fwrite(STDERR, "FAIL: {$message}\n");
            exit(1);
        }
    }

    reset_allowed_domains_cache();
    assert_true(
        AllowedDomains::getAllowedDomains() === [],
        'No domains configured anywhere'
    );

    $GLOBALS['rrze_test_options']['rrze-formular']['allowed_domains'] = "fau.de\n";
    reset_allowed_domains_cache();
    assert_true(
        AllowedDomains::getAllowedDomains() === ['fau.de'],
        'Plugin recipient domains are used when RRZE Settings is inactive'
    );

    assert_true(
        AllowedDomains::parseDomains("Example.ORG\nexample.org\n@MAIL.EXAMPLE.ORG.\nsub.example.org\n") === [
            'example.org',
            'mail.example.org',
            'sub.example.org',
        ],
        'Domain parsing normalizes, trims and deduplicates valid domains'
    );
    assert_true(
        AllowedDomains::parseDomains("http://example.org\nbad domain\n_invalid.example.org\nexample\nvalid.example.org\n") === [
            'valid.example.org',
        ],
        'Domain parsing rejects syntactically invalid domains'
    );
    assert_true(
        AllowedDomains::invalidDomains("http://example.org\nbad domain\nvalid.example.org\n") === [
            'http://example.org',
            'bad domain',
        ],
        'Invalid configured domains are reported'
    );
    assert_true(
        AllowedDomains::sanitizeDomainList("Example.ORG\nexample.org\n@MAIL.EXAMPLE.ORG.\n") === "example.org\nmail.example.org",
        'Domain list sanitization stores normalized unique lines'
    );

    $GLOBALS['rrze_test_options']['rrze-formular']['allowed_domains'] = "plugin.example.org\n";
    $GLOBALS['rrze_test_filters']['rrze_formular_rrze_settings_active'] = true;
    reset_allowed_domains_cache();
    assert_true(
        AllowedDomains::getAllowedDomains() === [],
        'Active RRZE Settings with no domains does not fall back to plugin domains'
    );

    $GLOBALS['rrze_test_site_options']['rrze_formular_allowedDomains'] = "settings.example.org\n";
    reset_allowed_domains_cache();
    assert_true(
        AllowedDomains::getAllowedDomains() === ['settings.example.org'],
        'Active RRZE Settings uses rrze_formular_allowedDomains'
    );

    echo "OK: allowed-domains-fallback\n";
}
