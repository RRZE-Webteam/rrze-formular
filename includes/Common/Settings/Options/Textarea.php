<?php

namespace RRZE\Formular\Common\Settings\Options;

use RRZE\Formular\Common\Settings\Options\Type;

defined('ABSPATH') || exit;

/**
 * Displays a multiline text input and preserves line breaks during sanitization.
 */
class Textarea extends Type
{
    /**
     * Name of the template in Common/Settings/templates/options, without .php.
     *
     * @var string
     */
    public $template = 'textarea';

    /**
     * Sanitize multiline text while preserving valid line breaks.
     *
     * @param mixed $value Submitted textarea value.
     * @return string Text sanitized by sanitize_textarea_field().
     */
    public function sanitize($value)
    {
        return sanitize_textarea_field($value);
    }
}
