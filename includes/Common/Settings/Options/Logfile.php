<?php

namespace RRZE\Formular\Common\Settings\Options;

use RRZE\Formular\Common\Settings\Options\Type;

defined('ABSPATH') || exit;

/**
 * Selects the legacy log-file display template.
 *
 * The template expects a Tools log reader, which is not included in this plugin.
 */
class Logfile extends Type
{
    /**
     * Name of the template in Common/Settings/templates/options, without .php.
     *
     * @var string
     */
    public $template = 'logfile';
}
