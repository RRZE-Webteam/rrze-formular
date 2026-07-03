<?php

declare(strict_types=1);

/**
 * Verifies attachCsv survives signing without a database connection.
 * Run: php tests/attach-csv-config-roundtrip.php
 */

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__ . '/');
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
        return is_string($key) ? strtolower(preg_replace('/[^a-z0-9_\-]/', '', $key) ?? '') : '';
    }

    function wp_json_encode($data, $options = 0, $depth = 512)
    {
        return json_encode($data, $options, $depth);
    }

    function wp_salt($scheme)
    {
        return 'rrze-formular-test-salt';
    }

    function __($text, $domain = 'default')
    {
        return $text;
    }
}

namespace RRZE\Formular\Common\Form {
    class AllowedDomains
    {
        public static function sanitizeAllowedRecipientEmail(string $email): string
        {
            return sanitize_text_field($email);
        }
    }

    class FieldTypes
    {
        public static function sanitizeFields(array $fields): array
        {
            return $fields;
        }
    }

    require __DIR__ . '/../includes/Common/Form/FormConfigAuth.php';

    $attributes = [
        'formTitle' => 'Kontakt',
        'attachCsv' => true,
        'fields' => [
            ['id' => 'email', 'type' => 'email', 'label' => 'E-mail', 'required' => true, 'options' => []],
        ],
    ];

    $trusted = FormConfigAuth::buildTrustedConfig($attributes);
    if (empty($trusted['attachCsv'])) {
        fwrite(STDERR, "FAIL: buildTrustedConfig dropped attachCsv\n");
        exit(1);
    }

    $signed = FormConfigAuth::sign($trusted);
    $verified = FormConfigAuth::verify($signed['payload'], $signed['signature']);
    if ($verified === null || empty($verified['attachCsv'])) {
        fwrite(STDERR, "FAIL: attachCsv lost in sign/verify round-trip\n");
        exit(1);
    }

  // Simulate the previous FormRenderer bug (missing attachCsv in normalize output).
    $brokenNormalize = [
        'formTitle' => $attributes['formTitle'],
        'fields' => $attributes['fields'],
        'includeSsoInfo' => true,
        'sendConfirmation' => false,
    ];
    $brokenTrusted = FormConfigAuth::buildTrustedConfig($brokenNormalize);
    if (!empty($brokenTrusted['attachCsv'])) {
        fwrite(STDERR, "FAIL: broken normalize unexpectedly has attachCsv\n");
        exit(1);
    }

    $fixedNormalize = $brokenNormalize;
    $fixedNormalize['attachCsv'] = !empty($attributes['attachCsv']);
    $fixedTrusted = FormConfigAuth::buildTrustedConfig($fixedNormalize);
    if (empty($fixedTrusted['attachCsv'])) {
        fwrite(STDERR, "FAIL: fixed normalize still drops attachCsv\n");
        exit(1);
    }

    fwrite(STDOUT, "OK: attachCsv config round-trip\n");
}
