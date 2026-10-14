<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

/**
 * Supplies recipient-validation hints to the block editor without restricting post saving.
 */
class EditorConfig
{
    /**
     * Register editor settings and script-localization hooks.
     *
     * @return void
     */
    public static function register(): void
    {
        add_filter('block_editor_settings_all', [self::class, 'filterBlockEditorSettings'], 10, 2);
        add_action('enqueue_block_editor_assets', [self::class, 'enqueueEditorAssets'], 100);
    }

    /**
     * Add form-specific configuration to the block editor settings.
     *
     * @param array<string, mixed> $settings Existing editor settings.
     * @param \WP_Block_Editor_Context $context Editor context supplied by WordPress; unused.
     * @return array<string, mixed> Settings with the rrzeFormularEditor entry added.
     */
    public static function filterBlockEditorSettings(array $settings, $context): array
    {
        $settings['rrzeFormularEditor'] = self::editorConfig();

        return $settings;
    }

    /**
     * Attach form configuration to each registered form-editor script handle.
     *
     * @return void
     */
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
     * Collect allowed recipient domains and translated inline validation messages.
     *
     * @return array{allowedDomains: list<string>, i18n: array<string, string>} Editor configuration.
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
