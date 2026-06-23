<?php

namespace RRZE\Formular;

use function RRZE\Formular\plugin;

defined('ABSPATH') || exit;

class Defaults
{
    private readonly array $defaults;

    public function __construct()
    {
        $this->defaults = $this->load();
    }

    private function load(): array
    {
        $defaults = [
            'settings' => [
                'option_name' => 'rrze-formular',
                'menu_title' => __('RRZE Formular', 'rrze-formular'),
                'page_title' => __('RRZE Formular Settings', 'rrze-formular'),
                'capability' => 'manage_options',
            ],
            'sections' => [
                ['id' => 'general', 'title' => __('General', 'rrze-formular')],
                ['id' => 'spam', 'title' => __('Spam Protection', 'rrze-formular')],
            ],
            'fields' => [
                'general' => [
                    [
                        'name' => 'include_sso_by_default',
                        'label' => __('Include SSO data by default', 'rrze-formular'),
                        'description' => __('When a logged-in user submits a form, include name and email in the operator mail.', 'rrze-formular'),
                        'type' => 'checkbox',
                        'default' => '1',
                    ],
                ],
                'spam' => [
                    [
                        'name' => 'min_submit_seconds',
                        'label' => __('Minimum fill time (seconds)', 'rrze-formular'),
                        'description' => __('Reject submissions that arrive faster than this threshold.', 'rrze-formular'),
                        'type' => 'text',
                        'default' => '3',
                    ],
                    [
                        'name' => 'rate_limit_per_hour',
                        'label' => __('Submissions per IP per hour', 'rrze-formular'),
                        'description' => __('Maximum number of accepted submissions from one IP address per hour.', 'rrze-formular'),
                        'type' => 'text',
                        'default' => '10',
                    ],
                ],
            ],
        ];

        return apply_filters('rrze_formwizard_defaults', $defaults);
    }

    public function get(string $key): mixed
    {
        return $this->defaults[$key] ?? null;
    }

    public function all(): array
    {
        return $this->defaults;
    }

    public function withPrefix(string $key = ''): string
    {
        $rawSlug = plugin()->getSlug();
        $clean = preg_replace('/[^a-z0-9]/', '', $rawSlug);

        $keep = min(3, strlen($clean));
        $part = substr($clean, 0, $keep);

        $needed = 6 - strlen($part);
        $hash = substr(md5($clean), 0, $needed);

        $prefix = $part . $hash;

        if (!preg_match('/^[a-z]/', $prefix)) {
            $prefix = 'p' . substr($prefix, 0, 5);
        }

        return $prefix . '_' . sanitize_key($key);
    }
}
