<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

/**
 * Signs form definitions and verifies that submitted configuration was issued by this site.
 *
 * Only normalized, supported attributes are used when processing a submission.
 * The configuration signature is separate from the single-use anti-spam token.
 */
class FormConfigAuth
{
    /**
     * Normalize the supported form attributes used for signing and processing.
     *
     * Unknown and retired attributes are discarded. Invalid recipient overrides
     * are cleared so recipient resolution can use its normal fallback.
     *
     * @param array<string, mixed> $attributes Raw block attributes or a verified configuration.
     * @return array<string, mixed> Sanitized titles, recipient settings, success message and fields.
     */
    public static function buildTrustedConfig(array $attributes): array
    {
        $normalized = [
            'formTitle' => sanitize_text_field((string) ($attributes['formTitle'] ?? '')),
            'formDescription' => sanitize_textarea_field((string) ($attributes['formDescription'] ?? '')),
            'successMessage' => sanitize_text_field((string) ($attributes['successMessage'] ?? '')),
            'recipientEmail' => AllowedDomains::sanitizeAllowedRecipientEmail(
                (string) ($attributes['recipientEmail'] ?? '')
            ),
            'recipientName' => sanitize_text_field((string) ($attributes['recipientName'] ?? '')),
            'fields' => is_array($attributes['fields'] ?? null) ? $attributes['fields'] : [],
        ];

        return [
            'formTitle' => $normalized['formTitle'],
            'formDescription' => $normalized['formDescription'],
            'successMessage' => $normalized['successMessage'],
            'recipientEmail' => $normalized['recipientEmail'],
            'recipientName' => $normalized['recipientName'],
            'fields' => FieldTypes::sanitizeFields($normalized['fields']),
        ];
    }

    /**
     * Hash the encoded configuration to bind a submission token to its form.
     *
     * @param array<string, mixed> $config Normalized form configuration.
     * @return string SHA-256 hash of the JSON payload.
     */
    public static function configHash(array $config): string
    {
        return hash('sha256', self::encodePayload($config));
    }

    /**
     * Encode a configuration and sign it with the site-specific configuration salt.
     *
     * @param array<string, mixed> $config Form configuration, normally built by buildTrustedConfig().
     * @return array{payload: string, signature: string} Base64 JSON payload and HMAC-SHA256 signature.
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
     * Authenticate an encoded configuration before normalizing its attributes.
     *
     * @param string $payloadBase64 Base64-encoded JSON configuration.
     * @param string $signature HMAC-SHA256 signature supplied with the payload.
     * @return array<string, mixed>|null Normalized configuration, or null for invalid encoding or signature.
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
     * Encode configuration as JSON without escaping Unicode or slashes.
     *
     * @param array<string, mixed> $config Configuration to encode.
     * @return string JSON payload, or an empty string if encoding fails.
     */
    private static function encodePayload(array $config): string
    {
        $payload = wp_json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($payload) ? $payload : '';
    }
}
