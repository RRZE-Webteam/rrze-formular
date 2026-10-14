<?php

namespace RRZE\Formular\Common\Settings;


defined('ABSPATH') || exit;

use RRZE\Formular\Common\Settings\Section;

use RRZE\Formular\Common\Settings\Options\{
    Checkbox,
    CheckboxMultiple,
    Password,
    RadioGroup,
    Select,
    SelectMultiple,
    Text,
    Textarea,
    HR,
    Logfile,
    DomainsTable,
    Msg
};

/**
 * Wraps a typed settings field and its custom rendering, validation and sanitization callbacks.
 */
class Option
{
    /**
     * The section this option belongs to.
     * 
     * @var Section
     */
    public $section;

    /**
     * The type of the option.
     * 
     * @var string
     */
    public $type;

    /**
     * Field configuration, including its name, label, default and callbacks.
     *
     * @var array<string, mixed>
     */
    public $args = [];

    /**
     * Selected option-type handler, or null for an unregistered type.
     *
     * @var \RRZE\Formular\Common\Settings\Options\Type|null
     */
    public $implementation;

    /**
     * Create the field handler selected by the filtered option-type map.
     *
     * @param Section $section Owning settings section.
     * @param string $type Option type identifier.
     * @param array<string, mixed> $args Field configuration and optional callbacks.
     */
    public function __construct(Section $section, string $type, array $args = [])
    {
        $this->section = $section;
        $this->type = $type;
        $this->args = $args;

        $typeMap = apply_filters('rrze-formular_settings_option_type_map', [
            'checkbox' => Checkbox::class,
            'checkbox-multiple' => CheckboxMultiple::class,
            'password' => Password::class,
            'radio-group' => RadioGroup::class,
            'select' => Select::class,
            'select-multiple' => SelectMultiple::class,
            'text' => Text::class,
            'textarea' => Textarea::class,
            'hr' => HR::class,
            'logfile' => Logfile::class,
            'domains-table' => DomainsTable::class,
            'msg' => Msg::class            
        ]);

        if (isset($typeMap[$this->type])) {
            $this->implementation = new $typeMap[$this->type]($section, $args);
        } else {
            $this->implementation = null;
        }
    }

    /**
     * Returns the value of an argument or a fallback value.
     *
     * This method retrieves the value of a specific argument by its key.
     * If the argument does not exist, it returns the provided fallback value.
     *
     * @param string $key The key of the argument to retrieve.
     * @param mixed $fallback The fallback value if the argument is not set.
     * @return mixed The value of the argument or the fallback value.
     */
    public function getArg($key, $fallback = null)
    {
        return $this->args[$key] ?? $fallback;
    }

    /**
     * Sanitize a value with the custom callback or the option-type handler.
     *
     * @param mixed $value Submitted field value after request unslashing.
     * @return mixed Sanitized value; true when no callback or type handler is available.
     */
    public function sanitize($value)
    {
        if (is_callable($this->getArg('sanitize'))) {
            return $this->getArg('sanitize')($value);
        }

        return is_null($this->implementation) ?: $this->implementation->sanitize($value);
    }

    /**
     * Validate a field using configured rules or its option-type handler.
     *
     * An array of rules is evaluated in order and records feedback for the first
     * failed rule. A single callback or the type handler supplies its own result.
     * Unknown types without a validator are accepted.
     *
     * @param mixed $value Submitted field value before sanitization.
     * @return bool Whether validation accepts the value.
     */
    public function validate($value)
    {
        if (is_array($this->getArg('validate'))) {
            foreach ($this->getArg('validate') as $validate) {
                if (!is_callable($validate['callback'])) {
                    continue;
                }

                $valid = $validate['callback']($value);

                if (!$valid) {
                    $this->section->tab->settings->errors->add($this->getArg('name'), $validate['feedback']);

                    return false;
                }
            }

            return true;
        }

        if (is_callable($this->getArg('validate'))) {
            return $this->getArg('validate')($value);
        }

        return is_null($this->implementation) ?: $this->implementation->validate($value);
    }

    /**
     * Renders the option.
     *
     * This method outputs the HTML for the option based on its type and arguments.
     * It checks if the option is visible before rendering and allows for custom rendering
     * through a callback function.
     * 
     * @return void
     */
    public function render()
    {
        if (is_callable($this->getArg('visible')) && $this->getArg('visible')() === false) {
            return;
        }

        if (is_callable($this->getArg('render'))) {
            echo $this->getArg('render')($this->implementation);

            return;
        }

        echo is_null($this->implementation) ?: $this->implementation->render();
    }
}
