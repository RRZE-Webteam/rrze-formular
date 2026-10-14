<?php

namespace RRZE\Formular\Common\Settings;

defined('ABSPATH') || exit;

/**
 * Outputs settings templates with named variables and a final markup filter.
 */
class Template
{
    /**
     * Output a settings template with named variables and a final output filter.
     *
     * Missing files produce no output. The rendered markup is echoed rather than
     * returned, after the rrze-formular_settings_template_include filter runs.
     *
     * @param string $fileName Template path relative to the templates directory, without .php.
     * @param array<string, mixed> $vars Variables made available inside the template.
     * @return void
     */
    public static function include($fileName, $vars = [])
    {
        foreach ($vars as $name => $value) {
            ${$name} = $value;
        }

        $path = __DIR__ . "/templates/{$fileName}.php";
        if (!file_exists($path)) {
            return;
        }

        ob_start();

        include $path;

        echo apply_filters('rrze-formular_settings_template_include', ob_get_clean(), $fileName, $vars);
    }
}
