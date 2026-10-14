<?php

namespace RRZE\Formular\Common\Blocks;

defined('ABSPATH') || exit;

/**
 * Registers block metadata directories and connects their editor-script translations.
 */
class Blocks
{
    /**
     * Block directory names, populated when a non-empty list is supplied.
     *
     * @var list<string>|null
     */
    private $blocks;

    /**
     * Validated block parent directory with a trailing slash.
     *
     * @var string|null
     */
    private $blockPath;

    /**
     * Validated plugin directory with a trailing slash.
     *
     * @var string|null
     */
    private $pluginPath;

    /**
     * Register block and translation hooks when the block list and directories are valid.
     *
     * @param list<string> $blocks Block directory names to register.
     * @param string $blockPath Absolute parent directory containing the blocks.
     * @param string $pluginPath Absolute plugin directory containing the languages directory.
     */
    public function __construct(array $blocks = [], string $blockPath = '', string $pluginPath = '')
    {
        // Ensure that the blocks array is not empty.
        if (empty($blocks)) {
            return;
        }

        // Set the blocks property to the provided array.
        // This allows for dynamic registration of blocks based on the provided array.
        $this->blocks = $blocks;

        // Ensure that the blocks directory exists.
        if (!is_dir($blockPath)) {
            return;
        }

        $this->blockPath = rtrim($blockPath, '/') . '/';

        // Ensure that the plugin path is set correctly.
        if (!is_dir($pluginPath)) {
            return;
        }

        // Ensure that the plugin path is set correctly.
        $this->pluginPath = rtrim($pluginPath, '/') . '/';

        // Register the blocks when WordPress initializes.
        add_action('init', [$this, 'register']);

        // Enqueue block editor assets for the blocks.
        add_action('enqueue_block_editor_assets', [$this, 'setBlockScriptTranslations']);
    }

    /**
     * Register each configured block from its metadata directory.
     *
     * @return void
     */
    public function register()
    {
        foreach ($this->blocks as $block) {
            register_block_type($this->blockPath . $block);
        }
    }

    /**
     * Connect registered form-editor script handles to the plugin translation directory.
     *
     * @return void
     */
    public function setBlockScriptTranslations()
    {
        foreach ($this->blocks as $block) {
            wp_set_script_translations(
                'rrze-formular-' . $block . '-editor-script',
                'rrze-formular',
                $this->pluginPath . 'languages'
            );
        }
    }
}
