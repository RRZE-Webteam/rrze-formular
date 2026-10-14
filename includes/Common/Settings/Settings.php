<?php

namespace RRZE\Formular\Common\Settings;

use function RRZE\Formular\plugin;

use RRZE\Formular\Common\Settings\{
    Builder,
    Error,
    Flash,
    Option,
    Section,
    Tab,
    Template,
    Worker
};

defined('ABSPATH') || exit;

/**
 * Builds and renders the plugin settings page and saves its active fields.
 *
 * Tabs contain sections, and sections contain typed options. Saving validates
 * the request nonce and capability, then validates and sanitizes the fields
 * shown in the active tab. Other stored settings retain their current values.
 */
class Settings
{
    /**
     * Visible settings-page title.
     *
     * @var string
     */
    public $title;

    /**
     * Optional menu label; the page title is used when unset.
     *
     * @var string|null
     */
    public $menuTitle;

    /**
     * Page identifier used by the admin menu and request URLs.
     *
     * @var string
     */
    public $slug;

    /**
     * Parent menu slug, or null for a top-level menu.
     *
     * @var string|null
     */
    public $parentSlug;

    /**
     * WordPress capability required to access and save this page.
     *
     * @var string
     */
    public $capability = 'manage_options';

    /**
     * Optional Dashicon name or icon URL for a top-level menu.
     *
     * @var string|null
     */
    public $menuIcon;

    /**
     * Preferred admin menu position, or null for WordPress ordering.
     *
     * @var int|float|null
     */
    public $menuPosition;

    /**
     * WordPress option key containing this page’s field values.
     *
     * @var string
     */
    public $optionName;

    /**
     * Settings tabs in display order.
     *
     * @var list<Tab>
     */
    public $tabs = [];

    /**
     * Validation-message helper, initialized by build().
     *
     * @var Error|null
     */
    public $errors;

    /**
     * Request-local feedback helper, initialized by build().
     *
     * @var Flash|null
     */
    public $flash;

    /**
     * Set the page title and derive default page and option identifiers.
     *
     * @param string $title Visible settings-page title.
     * @param string|null $slug Optional page slug; generated from the title when omitted.
     */
    public function __construct($title, $slug = null)
    {
        $this->title = $title;
        $this->optionName = strtolower(str_replace('-', '_', sanitize_title($this->title)));
        $this->slug = $slug;

        if ($this->slug === null) {
            $this->slug = sanitize_title($title);
        }
    }

    /**
     * Place the settings page below an existing admin menu.
     *
     * @param string $slug Parent menu slug, such as options-general.php.
     * @return self This settings controller for method chaining.
     */
    public function setMenuParentSlug($slug)
    {
        $this->parentSlug = $slug;
        return $this;
    }

    /**
     * Set the label used in the admin menu.
     *
     * @param string $title Menu label.
     * @return self This settings controller for method chaining.
     */
    public function setMenuTitle($title)
    {
        $this->menuTitle = $title;
        return $this;
    }

    /**
     * Return the custom menu label, falling back to the page title.
     *
     * @return string Admin menu label.
     */
    public function getMenuTitle()
    {
        return $this->menuTitle ?? $this->title;
    }

    /**
     * Set the capability required to view and save settings.
     *
     * @param string $capability WordPress capability name.
     * @return self This settings controller for method chaining.
     */
    public function setCapability($capability)
    {
        $this->capability = $capability;
        return $this;
    }

    /**
     * Set the WordPress option key used to store all field values.
     *
     * @param string $name Option key.
     * @return self This settings controller for method chaining.
     */
    public function setOptionName($name)
    {
        $this->optionName = $name;
        return $this;
    }

    /**
     * Set the icon for a top-level admin menu entry.
     *
     * @param string $icon Dashicon name or icon URL accepted by WordPress.
     * @return self This settings controller for method chaining.
     */
    public function setMenuIcon($icon)
    {
        $this->menuIcon = $icon;
        return $this;
    }

    /**
     * Set the preferred admin menu position.
     *
     * @param int|float|null $position Menu position, or null for WordPress ordering.
     * @return self This settings controller for method chaining.
     */
    public function setMenuPosition($position)
    {
        $this->menuPosition = $position;
        return $this;
    }

    /**
     * Register a submenu or top-level menu page using the configured parent.
     *
     * @return void
     */
    public function addToMenu()
    {
        if ($this->parentSlug) {
            add_submenu_page(
                $this->parentSlug,
                $this->title,
                $this->getMenuTitle(),
                $this->capability,
                $this->slug,
                [$this, 'render'],
                $this->menuPosition
            );
        } else {
            add_menu_page(
                $this->title,
                $this->getMenuTitle(),
                $this->capability,
                $this->slug,
                [$this, 'render'],
                $this->menuIcon,
                $this->menuPosition
            );
        }
    }

    /**
     * Initialize feedback helpers and register admin settings and tour hooks.
     *
     * @return void
     */
    public function build()
    {
        $this->errors = new Error($this);
        $this->flash = new Flash($this);

        add_action('admin_init', [$this, 'save'], 20);
        add_action('admin_menu', [$this, 'addToMenu'], 20);
        add_action('admin_head', [$this, 'styling'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueueGuidedTour']);
        add_action('wp_ajax_rrze_formular_dismiss_guided_tour', [$this, 'dismissGuidedTour']);
        add_action('wp_ajax_rrze_formular_dismiss_setup_tour', [$this, 'dismissSetupTour']);
    }

    /**
     * Load guided-tour assets and per-user tour configuration on the settings screen.
     *
     * @param string $hook Admin page hook supplied by WordPress; screen detection is used instead.
     * @return void
     */
    public function enqueueGuidedTour(string $hook): void
    {
        unset($hook);

        if (!$this->isOnSettingsPage()) {
            return;
        }

        $script_path = plugin()->getPath() . 'build/rrze-formular-guided-tour.js';
        $asset_path = plugin()->getPath() . 'build/rrze-formular-guided-tour.asset.php';

        if (!is_readable($script_path) || !is_readable($asset_path)) {
            return;
        }

        /** @var array{dependencies: string[], version: string} $asset_file */
        $asset_file = include $asset_path;

        wp_enqueue_style('dashicons');
        wp_enqueue_style('wp-components');

        $admin_css = plugin()->getPath() . 'build/css/rrze-formular-admin.css';
        if (is_readable($admin_css)) {
            wp_enqueue_style(
                'rrze-formular-admin-css',
                plugin()->getUrl() . 'build/css/rrze-formular-admin.css',
                [],
                (string) filemtime($admin_css)
            );
        }

        wp_enqueue_script(
            'rrze-formular-guided-tour',
            plugin()->getUrl() . 'build/rrze-formular-guided-tour.js',
            $asset_file['dependencies'],
            $asset_file['version'],
            true
        );

        wp_set_script_translations(
            'rrze-formular-guided-tour',
            'rrze-formular',
            plugin()->getPath() . 'languages'
        );

        $setupTourStepId = '';
        if (isset($_GET['rrze_setup_tour_step'])) {
            $setupTourStepId = sanitize_key((string) wp_unslash($_GET['rrze_setup_tour_step']));
        }

        wp_localize_script('rrze-formular-guided-tour', 'rrzeFormularGuide', [
            'autoStart' => !get_user_meta(get_current_user_id(), 'rrze_formular_guided_tour_dismissed', true),
            'autoStartSetup' => isset($_GET['rrze_setup_tour']),
            'setupTourStepId' => $setupTourStepId,
            'settingsUrl' => $this->getUrl(),
            'activeTab' => $this->getActiveTab()->slug,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rrze_formular_guided_tour'),
            'setupTourNonce' => wp_create_nonce('rrze_formular_setup_tour'),
        ]);
    }

    /**
     * Record dismissal of the setup tour for the current user.
     *
     * The AJAX handler checks the tour nonce and settings capability, updates user
     * metadata, then sends a JSON response and terminates the request.
     *
     * @return void
     */
    public function dismissSetupTour(): void
    {
        check_ajax_referer('rrze_formular_setup_tour', 'nonce');

        if (!current_user_can($this->capability)) {
            wp_send_json_error(null, 403);
        }

        update_user_meta(get_current_user_id(), 'rrze_formular_setup_tour_dismissed', 1);
        wp_send_json_success();
    }

    /**
     * Record dismissal of the introductory tour for the current user.
     *
     * The AJAX handler checks the tour nonce and settings capability, updates user
     * metadata, then sends a JSON response and terminates the request.
     *
     * @return void
     */
    public function dismissGuidedTour(): void
    {
        check_ajax_referer('rrze_formular_guided_tour', 'nonce');

        if (!current_user_can($this->capability)) {
            wp_send_json_error(null, 403);
        }

        update_user_meta(get_current_user_id(), 'rrze_formular_guided_tour_dismissed', 1);
        wp_send_json_success();
    }

    /**
     * Check whether the current admin screen matches this settings page.
     *
     * @return bool True for the matching settings_page screen base.
     */
    public function isOnSettingsPage()
    {
        $screen = get_current_screen();
        if (is_null($screen)) {
            return false;
        }

        if ($screen->base === 'settings_page_' . $this->slug) {
            return true;
        }

        return false;
    }

    /**
     * Output field-error styling only on the plugin settings screen.
     *
     * @return void
     */
    public function styling()
    {
        if (!$this->isOnSettingsPage()) {
            return;
        }

        echo '<style>.rrze-formular-settings-error {color: #d63638; margin: 5px 0;}</style>';
    }

    /**
     * Find a tab by its exact slug.
     *
     * @param string $slug Tab slug to look up.
     * @return Tab|false Matching tab, or false when unknown.
     */
    public function getTabBySlug($slug)
    {
        foreach ($this->tabs as $tab) {
            if ($tab->slug === $slug) {
                return $tab;
            }
        }

        return false;
    }

    /**
     * Resolve the requested tab, falling back to the first configured tab.
     *
     * @return Tab|false Active tab, or false when no tabs exist.
     */
    public function getActiveTab()
    {
        $default = $this->tabs[0] ?? false;

        if (isset($_GET['tab'])) {
            $requestedTab = (string) wp_unslash($_GET['tab']);

            return in_array($requestedTab, array_map(function ($tab) {
                return $tab->slug;
            }, $this->tabs), true) ? $this->getTabBySlug($requestedTab) : $default;
        }

        return $default;
    }

    /**
     * Append a tab to the settings page.
     *
     * @param string $title Visible tab title.
     * @param string|null $slug Optional slug; generated from the title when omitted.
     * @return Tab Newly created tab.
     */
    public function addTab($title, $slug = null)
    {
        $tab = new Tab($this, $title, $slug);

        $this->tabs[] = $tab;

        return $tab;
    }

    /**
     * Append a section to the last tab, creating a default tab if needed.
     *
     * @param string $title Visible section title.
     * @param array<string, mixed>|string $args Section settings, or a legacy string that uses defaults.
     * @return Section Newly created section.
     */
    public function addSection($title, $args = [])
    {
        if (empty($this->tabs)) {
            $tab = $this->addTab(__('Unnamed tab', 'rrze-formular'));
        } else {
            $tab = end($this->tabs);
        }

        return $tab->addSection($title, $args);
    }

    /**
     * Append an option to the last section of the last tab.
     *
     * @param string $type Registered option type, such as text or checkbox.
     * @param array<string, mixed> $args Field name, label, default and optional callbacks.
     * @return Option|false New option, or false when no tab or section is available.
     */
    public function addOption($type, $args = [])
    {
        $tab = end($this->tabs);

        if (!$tab instanceof Tab) {
            return false;
        }

        $section = end($tab->sections);

        if (!$section instanceof Section) {
            return false;
        }

        return $section->addOption($type, $args);
    }

    /**
     * Check whether a tab navigation menu is needed.
     *
     * @return bool True when more than one tab is configured.
     */
    public function shouldMakeTabs()
    {
        return count($this->tabs) > 1;
    }

    /**
     * Build the admin URL for the settings page without tab or section selection.
     *
     * @return string Settings-page URL.
     */
    public function getUrl()
    {
        if ($this->parentSlug && strpos($this->parentSlug, '.php') !== false) {
            return add_query_arg('page', $this->slug, admin_url($this->parentSlug));
        }

        return admin_url("admin.php?page=$this->slug");
    }

    /**
     * Add the current tab and selected linked section to the settings-page URL.
     *
     * @return string Settings-page URL including active navigation parameters.
     */
    public function getFullUrl()
    {
        $params = [];

        if ($active_tab = $this->getActiveTab()) {
            $params['tab'] = $active_tab->slug;

            if ($active_section = $active_tab->getActiveSection()) {
                $params['section'] = $active_section->slug;
            }
        }

        return add_query_arg($params, $this->getUrl());
    }

    /**
     * Output tab navigation when more than one tab is available.
     *
     * @return void
     */
    public function renderTabMenu()
    {
        if (!$this->shouldMakeTabs()) {
            return;
        }

        Template::include('tab-menu', ['settings' => $this]);
    }

    /**
     * Output the active tab sections through the settings template.
     *
     * @return void
     */
    public function renderActiveSections()
    {
        Template::include('sections', ['settings' => $this]);
    }

    /**
     * Initialize the settings builder and output the complete settings-page template.
     *
     * @return void
     */
    public function render()
    {
        Worker::setBuilder(new Builder);

        Worker::enqueue();

        Template::include('settings-page', ['settings' => $this]);
    }

    /**
     * Validate and save submitted values for the active settings sections.
     *
     * Requests without a valid nonce are ignored; insufficient capabilities stop
     * the request. Input is unslashed before validation and sanitization. Invalid
     * fields keep their previous values and record field-specific errors.
     *
     * @return void
     */
    public function save()
    {
        $nonce = isset($_POST['rrze-formular_settings_save'])
            ? (string) wp_unslash($_POST['rrze-formular_settings_save'])
            : '';

        if (
            $nonce === ''
            || !wp_verify_nonce($nonce, 'rrze-formular_settings_save_' . $this->optionName)
        ) {
            return;
        }

        if (!current_user_can($this->capability)) {
            wp_die(__('You do not have enough permissions to do that.', 'rrze-formular'));
        }

        $currentOptions = $this->getOptions();
        $rawSubmittedOptions = $_POST[$this->optionName] ?? [];
        $rawSubmittedOptions = is_array($rawSubmittedOptions) ? $rawSubmittedOptions : [];
        $submittedOptions = apply_filters(
            'rrze-formular_settings_new_options',
            wp_unslash($rawSubmittedOptions),
            $currentOptions
        );
        $submittedOptions = is_array($submittedOptions) ? $submittedOptions : [];
        $newOptions = $currentOptions;

        foreach ($this->getActiveTab()->getActiveSections() as $section) {
            foreach ($section->options as $option) {
                $value = $submittedOptions[$option->implementation->getName()] ?? null;

                $valid = $option->validate($value);

                if (!$valid) {
                    continue;
                }

                $value = apply_filters('rrze-formular_settings_new_option_' . $option->implementation->getName(), $option->sanitize($value), $option->implementation);

                $newOptions[$option->implementation->getName()] = $value;
            }
        }

        $this->updateOptions($newOptions);

        $this->flash->set('success', __('Settings saved.', 'rrze-formular'));
    }

    /**
     * Collect default values from all registered tabs and sections.
     *
     * @return array<string, mixed> Defaults keyed by option name.
     */
    public function defaultOptions()
    {
        $options = [];
        foreach ($this->tabs as $tab) {
            foreach ($tab->sections as $section) {
                foreach ($section->options as $option) {
                    $options[$option->args['name']] = $option->args['default'] ?? null;
                }
            }
        }

        return $options;
    }

    /**
     * Merge stored values with defaults and discard unknown option keys.
     *
     * @return array<string, mixed> Values for the currently defined settings fields.
     */
    public function getOptions()
    {
        $defaults = $this->defaultOptions();
        $options = get_option($this->optionName, []);
        $options = wp_parse_args($options, $defaults);
        $options = array_intersect_key($options, $defaults);

        return $options;
    }

    /**
     * Read one setting from the merged option values.
     *
     * @param string $option Field name to look up.
     * @return mixed Stored/default value, or null for an unknown field.
     */
    public function getOption($option)
    {
        $options = $this->getOptions();
        return $options[$option] ?? null;
    }

    /**
     * Persist an option array and notify listeners after the update call.
     *
     * This low-level method does not validate fields; normal form saves use save().
     *
     * @param array<string, mixed> $options Complete option values to store.
     * @return void
     */
    public function updateOptions($options)
    {
        update_option($this->optionName, $options);
        do_action('rrze-formular_settings_after_update_option', $this->optionName, $options);
    }
}
