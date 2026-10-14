<?php

namespace RRZE\Formular\Common\Settings\Options;

use RRZE\Formular\Common\Settings\Options\Type;

defined('ABSPATH') || exit;

/**
 * Displays one checkbox and interprets its stored value as a checked state.
 */
class Checkbox extends Type
{
    /**
     * Name of the template in Common/Settings/templates/options, without .php.
     *
     * @var string
     */
    public $template = 'checkbox';

    /**
     * Read the checkbox value, using its default only when the stored value is false or missing.
     *
     * @return mixed Stored checkbox value or the configured default.
     */
    public function getValueAttribute()
    {
        $value = get_option($this->section->tab->settings->optionName)[$this->getArg('name')] ?? false;
        if ($value === false) {
            $value = $this->getArg('default');
        }
        return $value;
    }

    /**
     * Checks if the checkbox is checked.
     *
     * This method returns true if the checkbox is checked (i.e., the value is true),
     * and false otherwise.
     *
     * @return bool True if the checkbox is checked, false otherwise.
     */
    public function isChecked()
    {
        return (bool) $this->getValueAttribute();
    }
}
