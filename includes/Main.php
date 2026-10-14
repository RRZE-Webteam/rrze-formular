<?php

namespace RRZE\Formular;

use function RRZE\Formular\plugin;

use RRZE\Formular\Common\{
    API\FormAPI,
    Form\EditorConfig,
    Form\FormLocale,
    Form\SpamProtection,
    Settings\Settings
};

defined('ABSPATH') || exit;

/**
 * Connects the form API, editor support, assets and admin settings to WordPress.
 */
class Main
{
    /**
     * Settings definitions, loaded when the admin settings are initialized.
     *
     * @var Defaults|null
     */
    public ?Defaults $defaults = null;

    /**
     * Admin settings controller, or null before settings are initialized.
     *
     * @var Settings|null
     */
    public ?Settings $settings = null;

    /**
     * Public form endpoint controller, created during the init action.
     *
     * @var FormAPI
     */
    private FormAPI $formApi;

    /**
     * Schedule plugin initialization on the WordPress init action.
     */
    public function __construct()
    {
        add_action('init', [$this, 'onInit']);
    }

    /**
     * Register public form services and assets, then initialize admin-only features.
     *
     * @return void
     */
    public function onInit(): void
    {
        $this->formApi = new FormAPI();
        EditorConfig::register();
        $this->registerAssets();

        if (is_admin()) {
            $this->bootstrapSettings();
            $this->registerOperationalNotices();
        }
    }

    /**
     * Register admin warnings when the required token storage is unavailable.
     *
     * @return void
     */
    private function registerOperationalNotices(): void
    {
        if (SpamProtection::publicEndpointsAvailable()) {
            return;
        }

        add_action('admin_notices', [$this, 'renderObjectCacheNotice']);
        add_action('network_admin_notices', [$this, 'renderObjectCacheNotice']);
    }

    /**
     * Output the missing object-cache warning to administrators with the required capability.
     *
     * @return void
     */
    public function renderObjectCacheNotice(): void
    {
        $capability = function_exists('is_network_admin') && is_network_admin()
            ? 'manage_network_options'
            : 'manage_options';

        if (!current_user_can($capability)) {
            return;
        }

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html(
                SpamProtection::persistentObjectCacheRequiredMessage()
                . ' '
                . __('Enable a persistent object cache such as Redis or Memcached, or explicitly override the rrze_formular_require_persistent_object_cache filter for this environment.', 'rrze-formular')
            )
        );
    }

    /**
     * Load the settings definitions and build the admin settings page.
     *
     * @return void
     */
    private function bootstrapSettings(): void
    {
        $this->defaults = new Defaults();
        $this->settings();
    }

    /**
     * Register frontend CSS and JavaScript with endpoint URLs and translated messages.
     *
     * @return void
     */
    public function registerAssets(): void
    {
        $cssPath = plugin()->getPath() . 'build/css/rrze-formular.css';
        $jsPath = plugin()->getPath() . 'build/rrze-formular-frontend.js';

        wp_register_style(
            'rrze-formular-css',
            plugins_url('build/css/rrze-formular.css', plugin()->getBasename()),
            [],
            file_exists($cssPath) ? filemtime($cssPath) : '0.0.2'
        );

        wp_register_script(
            'rrze-formular-frontend',
            plugins_url('build/rrze-formular-frontend.js', plugin()->getBasename()),
            [],
            file_exists($jsPath) ? filemtime($jsPath) : '0.0.2',
            true
        );

        wp_localize_script('rrze-formular-frontend', 'RRZEFormular', [
            'restUrl' => rest_url('rrze-formular/v1/submit'),
            'tokenUrl' => rest_url('rrze-formular/v1/token'),
            'siteLocale' => FormLocale::getSiteLocale(),
            'i18n' => [
                'submitting' => __('Sending…', 'rrze-formular'),
                'success' => __('Thank you. Your message has been sent.', 'rrze-formular'),
                'error' => __('The form could not be sent. Please try again later.', 'rrze-formular'),
                'validation' => __('Please fill in all required fields correctly.', 'rrze-formular'),
                'fieldRequired' => __('This field is required.', 'rrze-formular'),
                'fieldInvalidEmail' => __('Please enter a valid e-mail address.', 'rrze-formular'),
                'chooseOption' => __('Please choose…', 'rrze-formular'),
                'confirmSelection' => __('Confirm selection', 'rrze-formular'),
            ],
        ]);
    }

    /**
     * Build settings tabs and fields from Defaults and register their WordPress hooks.
     *
     * @return void
     */
    public function settings(): void
    {
        if ($this->defaults === null) {
            $this->defaults = new Defaults();
        }

        $this->settings = new Settings($this->defaults->get('settings')['page_title']);

        $this->settings->setCapability($this->defaults->get('settings')['capability'])
            ->setOptionName($this->defaults->get('settings')['option_name'])
            ->setMenuTitle($this->defaults->get('settings')['menu_title'])
            ->setMenuPosition(7)
            ->setMenuParentSlug('options-general.php');

        foreach ($this->defaults->get('sections') as $section) {
            $tab = $this->settings->addTab(__($section['title'], 'rrze-formular'), $section['id']);
            $sec = $tab->addSection(__($section['title'], 'rrze-formular'), $section['id']);

            foreach ($this->defaults->get('fields')[$section['id']] as $field) {
                $sec->addOption($field['type'], array_intersect_key(
                    $field,
                    array_flip(['name', 'label', 'description', 'options', 'default', 'sanitize', 'validate'])
                ));
            }
        }

        $this->settings->build();
    }

}
