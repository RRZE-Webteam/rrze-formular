<?php

declare(strict_types=1);

/**
 * Verifies removed legacy block attributes are ignored by trusted config.
 * Run: php tests/form-config-legacy-attributes.php
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
    if (array_key_exists('attachCsv', $trusted)) {
        fwrite(STDERR, "FAIL: legacy attachCsv attribute is still trusted\n");
        exit(1);
    }

    $signed = FormConfigAuth::sign($trusted);
    $verified = FormConfigAuth::verify($signed['payload'], $signed['signature']);
    if ($verified === null || array_key_exists('attachCsv', $verified)) {
        fwrite(STDERR, "FAIL: legacy attachCsv survived sign/verify round-trip\n");
        exit(1);
    }

    fwrite(STDOUT, "OK: legacy form config attributes ignored\n");
}
