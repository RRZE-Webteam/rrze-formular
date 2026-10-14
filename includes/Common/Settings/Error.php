<?php

namespace RRZE\Formular\Common\Settings;

use RRZE\Formular\Common\Settings\Settings;

defined('ABSPATH') || exit;

/**
 * Collects field-validation messages for a settings page during the current request.
 */
class Error
{
    /**
     * Settings controller whose option name identifies this error collection.
     *
     * @var Settings
     */
    public $settings;

    /**
     * Local collection published to request-global state when an error is added.
     *
     * @var \WP_Error
     */
    public $error;

    /**
     * Create an empty error collection for a settings page.
     *
     * @param Settings $settings Settings controller that owns the fields.
     */
    public function __construct(Settings $settings)
    {
        $this->error = new \WP_Error;
        $this->settings = $settings;
    }

    /**
     * Read the error collection published for this settings page.
     *
     * @return \WP_Error Recorded errors, or a new empty collection when none has been published.
     */
    public function getAll(): \WP_Error
    {
        global $wp_settings_error;

        if (isset($wp_settings_error[$this->settings->optionName]) && is_wp_error($wp_settings_error[$this->settings->optionName])) {
            return $wp_settings_error[$this->settings->optionName];
        }

        return new \WP_Error();
    }

    /**
     * Read the first error message recorded under a field key.
     *
     * @param string $key Field name used as the WordPress error code.
     * @return string First error message, or an empty string when the key has no message.
     */
    public function get(string $key): ?string
    {
        $errors = $this->getAll();

        return is_wp_error($errors) ? $errors->get_error_message($key) : null;
    }

    /**
     * Record a field error and publish the updated collection to request-global state.
     *
     * @param string $key Field name used as the WordPress error code.
     * @param string $message Translated validation feedback.
     * @return void
     */
    public function add(string $key, string $message): void
    {
        $this->error->add($key, $message);

        global $wp_settings_error;
        $wp_settings_error[$this->settings->optionName] = $this->error;
    }

    /**
     * Check whether the settings page has any recorded validation errors.
     *
     * @return bool True when the published error collection is not empty.
     */
    public function hasErrors(): bool
    {
        return ! empty($this->getAll()->errors);
    }
}
