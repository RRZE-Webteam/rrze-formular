<?php

namespace RRZE\Formular\Common\Settings\Options;

use RRZE\Formular\Common\Settings\Options\Type;

defined('ABSPATH') || exit;

/**
 * Selects the legacy domain-management table template.
 *
 * The template expects a SyncAPI provider, which is not included in this plugin.
 */
class DomainsTable extends Type
{
    /**
     * Name of the template in Common/Settings/templates/options, without .php.
     *
     * @var string
     */
    public $template = 'domains-table';
}
