<?php

namespace RRZE\Formular\Common\Settings;

defined('ABSPATH') || exit;

/**
 * Stores settings-page feedback in request-local state, grouped by option name.
 */
class Flash
{
    /**
     * Settings page whose option name identifies its feedback message.
     *
     * @var Settings
     */
    public $settings;

    /**
     * Attach feedback storage to a settings page.
     *
     * @param Settings $settings Owning settings controller.
     */
    public function __construct($settings)
    {
        $this->settings = $settings;
    }

    /**
     * Read the feedback message currently stored for this settings page.
     *
     * @return array{status: string, message: string}|null Feedback data, or null when none is set.
     */
    public function has()
    {
        global $wp_settings_flash;

        return $wp_settings_flash[$this->settings->optionName] ?? null;
    }

    /**
     * Replace the feedback message for this settings page during the current request.
     *
     * @param string $status Notice status, such as success or error.
     * @param string $message Feedback text to display.
     * @return void
     */
    public function set($status, $message)
    {
        global $wp_settings_flash;

        $wp_settings_flash[$this->settings->optionName] = compact('status', 'message');
    }
}
