<?php

declare(strict_types=1);

/**
 * Standalone checks for confirmation mail domain rules.
 * Run: php tests/confirmation-domains.php
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

    require dirname(__DIR__) . '/includes/Common/Form/Mailer.php';
    require dirname(__DIR__) . '/includes/Common/Form/AllowedDomains.php';

    use RRZE\Formular\Common\Form\AllowedDomains;
    use RRZE\Formular\Common\Form\Mailer;

    $failures = 0;

    function assert_true(bool $condition, string $message): void
    {
        global $failures;

        if (!$condition) {
            echo "FAIL: {$message}\n";
            $failures++;
        }
    }

    function reset_domain_caches(): void
    {
        foreach ([Mailer::class, AllowedDomains::class] as $class) {
            $reflection = new ReflectionClass($class);
            foreach ($reflection->getProperties() as $property) {
                if (!$property->isStatic()) {
                    continue;
                }

                $property->setValue(null, null);
            }
        }
    }

    assert_true(
        !AllowedDomains::isConfirmationEmailAllowed('user@example.com'),
        'No configured domains must block confirmations'
    );
    assert_true(
        !AllowedDomains::hasConfirmationDomainsConfigured(),
        'No configured domains must report confirmations disabled'
    );

    reset_domain_caches();
    $rrze_test_options['rrze-formular']['allowed_domains'] = "uni-example.de\n";
    assert_true(
        AllowedDomains::isConfirmationEmailAllowed('user@uni-example.de'),
        'Recipient domains should be used as confirmation fallback'
    );
    assert_true(
        !AllowedDomains::isConfirmationEmailAllowed('user@other.example'),
        'Non-allowed confirmation domains must be rejected'
    );

    reset_domain_caches();
    $rrze_test_options['rrze-formular']['allowed_confirmation_domains'] = "staff.example.org\n";
    assert_true(
        AllowedDomains::isConfirmationEmailAllowed('user@staff.example.org'),
        'Dedicated confirmation domains must be accepted'
    );
    assert_true(
        !AllowedDomains::isConfirmationEmailAllowed('user@uni-example.de'),
        'Dedicated confirmation list must override recipient domains'
    );

    if ($failures === 0) {
        echo "OK: confirmation domain checks passed\n";
        exit(0);
    }

    echo "{$failures} check(s) failed\n";
    exit(1);
}
