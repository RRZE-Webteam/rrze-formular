<?php

namespace RRZE\Formular\Common\Settings\Options;

use RRZE\Formular\Common\Settings\Options\Type;

defined('ABSPATH') || exit;

/**
 * Displays a horizontal separator between settings fields.
 */
class HR extends Type
{
    /**
     * Name of the template in Common/Settings/templates/options, without .php.
     *
     * @var string
     */
    public $template = 'hr';
}
