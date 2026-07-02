<?php

namespace RRZE\Formular;

use function RRZE\Formular\plugin;

use RRZE\Formular\Common\Form\AllowedDomains;

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
                'general' => $this->generalFields(),
                'spam' => $this->spamFields(),
            ],
        ];

        return apply_filters('rrze_formwizard_defaults', $defaults);
    }

    private function generalFields(): array
    {
        $adminEmail = sanitize_email((string) get_option('admin_email'));

        $fields = [
            [
                'name' => 'default_recipient_email',
                'label' => __('Default recipient e-mail', 'rrze-formular'),
                'description' => $adminEmail !== ''
                    ? sprintf(
                        /* translators: %s: site administrator e-mail address */
                        __('Optional. Leave empty to use the site e-mail address (%s). Must use an allowed domain when set.', 'rrze-formular'),
                        $adminEmail
                    )
                    : __('Optional. Leave empty to use the site e-mail address. Must use an allowed domain when set.', 'rrze-formular'),
                'type' => 'text',
                'default' => '',
                'sanitize' => static fn($value): string => sanitize_email((string) $value),
                'validate' => [
                    [
                        'callback' => static function ($value): bool {
                            $value = trim((string) $value);
                            if ($value === '') {
                                return true;
                            }

                            if (!is_email($value)) {
                                return false;
                            }

                            if (AllowedDomains::hasConfiguredDomains() && !AllowedDomains::isEmailDomainAllowed($value)) {
                                return false;
                            }

                            return true;
                        },
                        'feedback' => __('Please enter a valid e-mail address from an allowed domain.', 'rrze-formular'),
                    ],
                ],
            ],
            [
                'name' => 'default_recipient_name',
                'label' => __('Default recipient name', 'rrze-formular'),
                'description' => __('Optional display name for the default recipient.', 'rrze-formular'),
                'type' => 'text',
                'default' => '',
            ],
            [
                'name' => 'mail_subject_prefix',
                'label' => __('Mail subject prefix', 'rrze-formular'),
                'description' => __('Optional text prepended to every form mail subject in square brackets, e.g. [FAU].', 'rrze-formular'),
                'type' => 'text',
                'default' => '',
            ],
            [
                'name' => 'include_sso_by_default',
                'label' => __('Include SSO data by default', 'rrze-formular'),
                'description' => __('When a logged-in user submits a form, include name and email in the operator mail.', 'rrze-formular'),
                'type' => 'checkbox',
                'default' => '1',
            ],
        ];

        if (!AllowedDomains::isRrzeCmsActive()) {
            $fields[] = [
                'name' => 'allowed_domains',
                'label' => __('Allowed e-mail domains', 'rrze-formular'),
                'description' => __('One domain per line, e.g. uni-erlangen.de. Recipient addresses must match one of these domains.', 'rrze-formular'),
                'type' => 'textarea',
                'default' => '',
            ];
        }

        return $fields;
    }

    private function spamFields(): array
    {
        return [
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
        ];
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
