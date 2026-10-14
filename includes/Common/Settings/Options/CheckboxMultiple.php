<?php

namespace RRZE\Formular\Common\Settings\Options;

use RRZE\Formular\Common\Settings\Options\Type;

defined('ABSPATH') || exit;

/**
 * Displays a group of checkboxes with multiple submitted selections.
 */
class CheckboxMultiple extends Type
{
    /**
     * Name of the template in Common/Settings/templates/options, without .php.
     *
     * @var string
     */
    public $template = 'checkbox-multiple';

    /**
     * Gets the name attribute for the multiple checkboxes.
     *
     * This method appends '[]' to the name attribute to indicate that
     * it is an array of values.
     *
     * @return string The modified name attribute.
     */
    public function getNameAttribute()
    {
        $name = parent::getNameAttribute();

        return "{$name}[]";
    }

    /**
     * Read saved selections, using a one-element default array when the value is missing.
     *
     * @return mixed Stored selections, normally an array; otherwise an array containing the default.
     */
    public function getValueAttribute()
    {
        $value = get_option($this->section->tab->settings->optionName)[$this->getArg('name')] ?? false;
        if ($value === false) {
            $value = [$this->getArg('default')];
        }
        return $value;
    }

    /**
     * Sanitizes the input value as an array.
     *
     * This method converts the input value to an array, ensuring that
     * it can handle both single values and arrays of values.
     *
     * @param mixed $value The input value to sanitize.
     * @return array The sanitized value as an array.
     */
    public function sanitize($value)
    {
        return (array) $value;
    }
}
