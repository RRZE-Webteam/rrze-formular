<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

class FormConfigAuth
{
    /**
     * Build the server-trusted form configuration used for signing and processing.
     *
     * @param array<string, mixed> $attributes Raw block attributes.
     * @return array<string, mixed>
     */
    public static function buildTrustedConfig(array $attributes): array
    {
        $normalized = [
            'formTitle' => sanitize_text_field((string) ($attributes['formTitle'] ?? '')),
            'formDescription' => sanitize_textarea_field((string) ($attributes['formDescription'] ?? '')),
            'successMessage' => sanitize_text_field((string) ($attributes['successMessage'] ?? '')),
            'includeSsoInfo' => !empty($attributes['includeSsoInfo']),
            'sendConfirmation' => !empty($attributes['sendConfirmation']),
            'fields' => is_array($attributes['fields'] ?? null) ? $attributes['fields'] : [],
        ];

        return [
            'formTitle' => $normalized['formTitle'],
            'formDescription' => $normalized['formDescription'],
            'successMessage' => $normalized['successMessage'],
            'includeSsoInfo' => $normalized['includeSsoInfo'],
            'sendConfirmation' => $normalized['sendConfirmation'],
            'fields' => FieldTypes::sanitizeFields($normalized['fields']),
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function configHash(array $config): string
    {
        return hash('sha256', self::encodePayload($config));
    }

    /**
     * @param array<string, mixed> $config
     * @return array{payload: string, signature: string}
     */
    public static function sign(array $config): array
    {
        $payload = self::encodePayload($config);

        return [
            'payload' => base64_encode($payload),
            'signature' => hash_hmac('sha256', $payload, wp_salt('rrze-formular-config')),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function verify(string $payloadBase64, string $signature): ?array
    {
        $payloadBase64 = trim($payloadBase64);
        $signature = trim($signature);

        if ($payloadBase64 === '' || $signature === '') {
            return null;
        }

        $payload = base64_decode($payloadBase64, true);
        if ($payload === false || $payload === '') {
            return null;
        }

        $expected = hash_hmac('sha256', $payload, wp_salt('rrze-formular-config'));
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $config = json_decode($payload, true);
        if (!is_array($config)) {
            return null;
        }

        return self::buildTrustedConfig($config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function encodePayload(array $config): string
    {
        $payload = wp_json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($payload) ? $payload : '';
    }
}
