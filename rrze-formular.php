<?php

/*
Plugin Name:        RRZE Formular
Plugin URI:         https://github.com/RRZE-Webteam/rrze-formular
Version:            1.0.9
Description:        Simple forms for the block editor with automatic design, spam protection and secure mail delivery.
Author:             RRZE Webteam
Author URI:         https://www.wp.rrze.fau.de/
License:            GNU General Public License Version 3
License URI:        https://www.gnu.org/licenses/gpl-3.0.html
Text Domain:        rrze-formular
Domain Path:        /languages
Requires at least:  6.8
Requires PHP:       8.2
*/

namespace RRZE\Formular;

use RRZE\Formular\Common\Plugin\Plugin;

defined('ABSPATH') || exit;

const RRZE_FORMFORMULAR_PLUGIN = 'rrze-formular/rrze-formular.php';

spl_autoload_register(function ($class) {
    $prefix = __NAMESPACE__;
    $baseDir = __DIR__ . '/includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

register_deactivation_hook(__FILE__, __NAMESPACE__ . '\deactivation');
add_action('plugins_loaded', __NAMESPACE__ . '\loaded');

function deactivation(): void
{
}

function plugin(): Plugin
{
    static $instance;

    if (null === $instance) {
        $instance = new Plugin(__FILE__);
    }

    return $instance;
}

function main(): Main
{
    static $instance;

    if (null === $instance) {
        $instance = new Main();
    }

    return $instance;
}

function load_textdomain(): void
{
    load_plugin_textdomain(
        'rrze-formular',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}

function register_blocks(): void
{
    register_block_type_from_metadata(__DIR__ . '/blocks/formular');
}

function set_block_script_translations(): void
{
    $handle = generate_block_asset_handle('rrze-formular/formular', 'editorScript');
    wp_set_script_translations($handle, 'rrze-formular', plugin_dir_path(__FILE__) . 'languages');
}

function loaded(): void
{
    plugin()->loaded();

    add_action('init', __NAMESPACE__ . '\load_textdomain');

    $wpCompatible = is_wp_version_compatible(plugin()->getRequiresWP());
    $phpCompatible = is_php_version_compatible(plugin()->getRequiresPHP());

    if (!$wpCompatible || !$phpCompatible) {
        add_action('init', function () use ($wpCompatible, $phpCompatible) {
            if (!current_user_can('activate_plugins')) {
                return;
            }

            $pluginName = plugin()->getName();
            $error = '';

            if (!$wpCompatible) {
                $error = sprintf(
                    __('The server is running WordPress version %1$s. The plugin requires at least WordPress version %2$s.', 'rrze-formular'),
                    wp_get_wp_version(),
                    plugin()->getRequiresWP()
                );
            } elseif (!$phpCompatible) {
                $error = sprintf(
                    __('The server is running PHP version %1$s. The plugin requires at least PHP version %2$s.', 'rrze-formular'),
                    PHP_VERSION,
                    plugin()->getRequiresPHP()
                );
            }

            add_action('admin_notices', function () use ($pluginName, $error) {
                printf(
                    '<div class="notice notice-error"><p>' .
                    esc_html__('Plugins: %1$s: %2$s', 'rrze-formular') .
                    '</p></div>',
                    esc_html($pluginName),
                    esc_html($error)
                );
            });
        });

        return;
    }

    main();

    add_action('init', __NAMESPACE__ . '\register_blocks');
    add_action('enqueue_block_editor_assets', __NAMESPACE__ . '\set_block_script_translations');
}
