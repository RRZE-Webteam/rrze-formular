<?php

namespace RRZE\Formular\Common\Settings\Options;

use RRZE\Formular\Common\Settings\Options\Type;
use RRZE\Formular\Common\Settings\Encryption;

defined('ABSPATH') || exit;

/**
 * Displays a password input and delegates stored-value encryption to Encryption.
 */
class Password extends Type
{
    /**
     * Name of the template in Common/Settings/templates/options, without .php.
     *
     * @var string
     */
    public $template = 'password';

    /**
     * Decrypt the stored password for the settings field.
     *
     * @return string|false|null Plain text, false if decryption fails, or null when no value is stored.
     */
    public function getValueAttribute()
    {
        $value = get_option($this->section->tab->settings->optionName)[$this->getArg('name')] ?? false;

        return $value ? Encryption::decrypt($value) : null;
    }

    /**
     * Pass the submitted password to the settings encryption helper before storage.
     *
     * @param string $value Plain-text password.
     * @return string|false Encoded ciphertext, unchanged input without OpenSSL, or false on failure.
     */
    public function sanitize($value)
    {
        return Encryption::encrypt($value);
    }
}
