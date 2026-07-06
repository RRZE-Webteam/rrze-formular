<?php

declare(strict_types=1);

/**
 * Standalone checks for recipient domain resolution fallbacks.
 * Run: php tests/allowed-domains-fallback.php
 */

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__ . '/');
    }

    $rrze_test_options = [
        'rrze-formular' => [
            'allowed_domains' => '',
            'allowed_confirmation_domains' => '',
        ],
        'admin_email' => 'admin@uni-example.de',
    ];

    function get_option(string $name, mixed $default = false): mixed
    {
        global $rrze_test_options;

        return $rrze_test_options[$name] ?? $default;
    }

    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
    {
        return $value;
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
        return $default;
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

        foreach (['allowedDomains', 'confirmationDomains', 'rrzeSettingsActive'] as $property) {
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

    $GLOBALS['rrze_test_options']['rrze-formular']['allowed_confirmation_domains'] = "uni-example.de\n";
    reset_allowed_domains_cache();
    assert_true(
        AllowedDomains::getAllowedDomains() === ['uni-example.de'],
        'Confirmation domains are used when recipient domains are empty'
    );
    assert_true(
        AllowedDomains::isBlockRecipientAllowed('team@uni-example.de'),
        'Recipient on confirmation fallback domain is allowed'
    );

    $GLOBALS['rrze_test_options']['rrze-formular']['allowed_domains'] = "fau.de\n";
    $GLOBALS['rrze_test_options']['rrze-formular']['allowed_confirmation_domains'] = '';
    reset_allowed_domains_cache();
    assert_true(
        AllowedDomains::getAllowedDomains() === ['fau.de'],
        'Plugin recipient domains take precedence over confirmation domains'
    );
    assert_true(
        AllowedDomains::getConfirmationDomains() === [],
        'Confirmation domains require a dedicated allowlist'
    );

    echo "OK: allowed-domains-fallback\n";
}
