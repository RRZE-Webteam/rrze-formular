<?php

namespace RRZE\Formular\Common\Settings\Options;

use RRZE\Formular\Common\Settings\{
    Section,
    Template
};

defined('ABSPATH') || exit;

/**
 * Base implementation for settings-field rendering, value access and validation.
 */
abstract class Type
{
    /**
     * The section this option belongs to.
     *
     * @var Section
     */
    public $section;

    /**
     * Field configuration used by the template and accessor methods.
     *
     * @var array<string, mixed>
     */
    public $args = [];

    /**
     * The template used for rendering the option.
     *
     * This property defines the template file that will be used to render
     * the option in the settings section. It should be set in subclasses.
     *
     * @var string
     */
    public $template;

    /**
     * Attach a field handler to its section and configuration.
     *
     * @param Section $section Owning settings section.
     * @param array<string, mixed> $args Field name, label, default and optional display settings.
     */
    public function __construct($section, $args = [])
    {
        $this->section = $section;
        $this->args = $args;
    }

    /**
     * Output the option-type template with this handler as its option variable.
     *
     * @return void
     */
    public function render()
    {
        return Template::include('options/' . $this->template, ['option' => $this]);
    }

    /**
     * Read the validation message recorded for this field.
     *
     * @return string Error message, or an empty string when no error was recorded.
     */
    public function hasError()
    {
        return $this->section->tab->settings->errors->get($this->getArg('name'));
    }

    /**
     * Apply WordPress single-line text sanitization to the submitted value.
     *
     * @param mixed $value Submitted field value.
     * @return string Sanitized text; subclasses may use a different value format.
     */
    public function sanitize($value)
    {
        return sanitize_text_field($value);
    }

    /**
     * Accept a value unless a subclass supplies stricter validation.
     *
     * @param mixed $value Submitted field value; unused by the base implementation.
     * @return bool Always true in the base implementation.
     */
    public function validate($value)
    {
        return true;
    }

    /**
     * Read a non-empty field argument, evaluating it if it is callable.
     *
     * @param string $key Argument name.
     * @param mixed $fallback Value used when the argument is missing or empty.
     * @return mixed Argument value, callback result or fallback.
     */
    public function getArg($key, $fallback = null)
    {
        if (empty($this->args[$key])) {
            return $fallback;
        }

        if (is_callable($this->args[$key])) {
            return $this->args[$key]();
        }

        return $this->args[$key];
    }

    /**
     * Gets the label for the option.
     *
     * This method retrieves the label argument and escapes it for safe output.
     *
     * @return string The escaped label for the option.
     */
    public function getLabel()
    {
        return esc_html((string) $this->getArg('label', ''));
    }

    /**
     * Gets the ID attribute for the option.
     *
     * This method generates a sanitized ID based on the name of the option,
     * replacing brackets with underscores to ensure it is a valid HTML ID.
     *
     * @return string The sanitized ID attribute for the option.
     */
    public function getIdAttribute()
    {
        return $this->getArg('id', sanitize_title(str_replace('[', '_', $this->getNameAttribute())));
    }

    /**
     * Build the data attribute used to locate this field in the setup tour.
     *
     * @return string Escaped data-rrze-tour attribute with a leading space, or an empty string.
     */
    public function getTourDataAttribute(): string
    {
        $map = [
            'mail_subject_prefix' => 'mail-subject-prefix',
            'min_submit_seconds' => 'min-submit-seconds',
            'rate_limit_per_hour' => 'rate-limit',
        ];

        $name = $this->getName();

        if (!isset($map[$name])) {
            return '';
        }

        return ' data-rrze-tour="' . esc_attr($map[$name]) . '"';
    }

    /**
     * Gets the name of the option.
     *
     * This method retrieves the name argument for the option.
     *
     * @return string The name of the option.
     */
    public function getName()
    {
        return $this->getArg('name');
    }

    /**
     * Gets the synonym attribute for the option.
     *
     * This method retrieves the synonym argument and returns it if set,
     * otherwise returns null.
     *
     * @return string|null The synonym text or null if not set.
     */
    public function getsynonymAttribute()
    {
        $synonym = $this->getArg('synonym') ?? null;

        return $synonym ?: null;
    }

    /**
     * Gets the CSS classes for the option.
     *
     * This method retrieves the CSS classes defined in the args array.
     * It returns an array of classes that can be used for styling the option.
     *
     * @return array The CSS classes for the option.
     */
    public function getCss()
    {
        return $this->getArg('css', []);
    }

    /**
     * Gets the input class attribute for the option.
     *
     * This method retrieves the input class from the CSS array and returns it
     * as a string formatted for use in an HTML class attribute.
     *
     * @return string|null The input class attribute or null if not set.
     */
    public function getInputClassAttribute()
    {
        $class = $this->getCss()['input_class'] ?? null;

        return !empty($class) ? 'class="' . esc_attr($class) . '"' : null;
    }

    /**
     * Gets the label class attribute for the option.
     *
     * This method retrieves the label class from the CSS array and returns it
     * as a string formatted for use in an HTML class attribute.
     *
     * @return string|null The label class attribute or null if not set.
     */
    public function getLabelClassAttribute()
    {
        $class = $this->getCss()['label_class'] ?? null;

        return !empty($class) ? 'class="' . esc_attr($class) . '"' : null;
    }

    /**
     * Gets the name attribute for the option.
     *
     * This method constructs the name attribute for the option, which is used
     * in form submissions. It includes the section's tab settings option name
     * and the specific name of the option.
     *
     * @return string The name attribute for the option.
     */
    public function getNameAttribute()
    {
        return $this->section->tab->settings->optionName . '[' . $this->getArg('name') . ']';
    }

    /**
     * Read a saved field value, falling back to the configured default when empty.
     *
     * False-like stored values also use the default in this base implementation.
     * Checkbox handlers override this behavior to preserve their stored state.
     *
     * @return mixed Stored value, configured default or null.
     */
    public function getValueAttribute()
    {
        $value = get_option($this->section->tab->settings->optionName)[$this->getArg('name')] ?? false;

        return $value ? $value : $this->args['default'] ?? null;
    }
}
