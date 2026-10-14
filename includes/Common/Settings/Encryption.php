<?php

namespace RRZE\Formular\Common\Settings;

defined('ABSPATH') || exit;

/**
 * Encodes password-setting values using OpenSSL AES-256-CBC and WordPress secrets.
 *
 * When OpenSSL is unavailable, values pass through unchanged. Changing AUTH_KEY
 * or AUTH_SALT prevents previously encrypted values from being recovered.
 */
class Encryption
{
    /**
     * The cipher method used for encryption and decryption.
     *
     * This constant defines the cipher method that will be used for
     * encrypting and decrypting values. It is set to 'aes-256-cbc'.
     *
     * @var string
     */
    const CIPHER_METHOD = 'aes-256-cbc';

    /**
     * Encrypt a setting value with a random IV and an appended site salt.
     *
     * @param string $value Plain-text setting value.
     * @return string|false Base64-encoded IV and ciphertext, unchanged input without OpenSSL, or false on failure.
     */
    public static function encrypt($value)
    {
        if (!extension_loaded('openssl')) {
            return $value;
        }

        $method = self::CIPHER_METHOD;
        $ivlen  = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($ivlen);

        $rawValue = openssl_encrypt($value . self::getSalt(), $method, self::getKey(), 0, $iv);
        if (!$rawValue) {
            return false;
        }

        return base64_encode($iv . $rawValue);
    }

    /**
     * Decode an encrypted setting and verify its appended site salt.
     *
     * @param string $rawValue Stored encrypted value.
     * @return string|false Plain text, unchanged empty/input value without OpenSSL, or false on failure.
     */
    public static function decrypt($rawValue)
    {
        if (!extension_loaded('openssl') || empty($rawValue)) {
            return $rawValue;
        }

        $rawValue = base64_decode($rawValue, true);

        $method = self::CIPHER_METHOD;
        $ivlen = openssl_cipher_iv_length($method);
        $iv = substr($rawValue, 0, $ivlen);

        $rawValue = substr($rawValue, $ivlen);

        $value = openssl_decrypt($rawValue, $method, self::getKey(), 0, $iv);
        if (!$value || substr($value, -strlen(self::getSalt())) !== self::getSalt()) {
            return false;
        }

        return substr($value, 0, -strlen(self::getSalt()));
    }

    /**
     * Read the WordPress authentication key used for settings encryption.
     *
     * @return string AUTH_KEY, or the fixed fallback when the constant is missing or empty.
     */
    private static function getKey()
    {
        if (defined('AUTH_KEY') && '' !== AUTH_KEY) {
            return AUTH_KEY;
        }

        // If this is reached, you're either not on a live site or have a serious security issue.
        return 'this-is-a-fallback-key-but-not-secure';
    }

    /**
     * Read the site salt appended to values before encryption.
     *
     * @return string AUTH_SALT, or the fixed fallback when the constant is missing or empty.
     */
    private static function getSalt()
    {
        if (defined('AUTH_SALT') && '' !== AUTH_SALT) {
            return AUTH_SALT;
        }

        // If this is reached, you're either not on a live site or have a serious security issue.
        return 'this-is-a-fallback-salt-but-not-secure';
    }
}
