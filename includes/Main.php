<?php

namespace RRZE\Formular;

use function RRZE\Formular\plugin;

use RRZE\Formular\Common\{
    API\FormAPI,
    Form\FormLocale,
    Settings\Settings
};

defined('ABSPATH') || exit;

class Main
{
    public Defaults $defaults;
    public Settings $settings;
    private FormAPI $formApi;

    public function __construct()
    {
        add_action('init', [$this, 'onInit']);
    }

    public function onInit(): void
    {
        $this->defaults = new Defaults();
        $this->settings();
        $this->formApi = new FormAPI();

        $this->registerAssets();
    }

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
            'nonce' => wp_create_nonce('wp_rest'),
            'siteLocale' => FormLocale::getSiteLocale(),
            'i18n' => [
                'submitting' => __('Sending…', 'rrze-formular'),
                'success' => __('Thank you. Your message has been sent.', 'rrze-formular'),
                'error' => __('The form could not be sent. Please try again later.', 'rrze-formular'),
                'validation' => __('Please fill in all required fields correctly.', 'rrze-formular'),
            ],
        ]);
    }

    public function settings(): void
    {
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
