<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

class EditorConfig
{
    public static function register(): void
    {
        add_filter('block_editor_settings_all', [self::class, 'filterBlockEditorSettings'], 10, 2);
        add_action('enqueue_block_editor_assets', [self::class, 'enqueueEditorAssets'], 100);
    }

    /**
     * @param array<string, mixed> $settings
     * @param mixed              $context
     * @return array<string, mixed>
     */
    public static function filterBlockEditorSettings(array $settings, $context): array
    {
        $settings['rrzeFormularEditor'] = self::editorConfig();

        return $settings;
    }

    public static function enqueueEditorAssets(): void
    {
        if (!function_exists('generate_block_asset_handle')) {
            return;
        }

        $config = self::editorConfig();
        $handles = array_unique([
            generate_block_asset_handle('rrze-formular/formular', 'editorScript'),
            'rrze-formular-formular-editor-script',
        ]);

        foreach ($handles as $handle) {
            if (!wp_script_is($handle, 'registered')) {
                continue;
            }

            wp_localize_script($handle, 'RRZEFormularEditor', $config);
        }
    }

    /**
     * Supplies inline recipient feedback without affecting post saving.
     *
     * @return array<string, mixed>
     */
    private static function editorConfig(): array
    {
        return [
            'allowedDomains' => AllowedDomains::getAllowedDomains(),
            'i18n' => [
                'recipientInvalidEmail' => __('Please enter a valid e-mail address.', 'rrze-formular'),
                'recipientDomainNotAllowed' => __('The recipient e-mail domain is not allowed.', 'rrze-formular'),
                'recipientDomainsRequired' => __('A recipient e-mail requires configured allowed domains.', 'rrze-formular'),
            ],
        ];
    }
}
