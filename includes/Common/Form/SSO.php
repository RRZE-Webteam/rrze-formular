<?php

namespace RRZE\FormWizard\Common\Form;

defined('ABSPATH') || exit;

class SSO
{
    public static function getUserData(): ?array
    {
        $data = null;

        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $data = [
                'name' => sanitize_text_field($user->display_name),
                'email' => sanitize_email($user->user_email),
                'login' => sanitize_user($user->user_login, true),
                'source' => 'wordpress',
            ];
        }

        /**
         * Allow SSO or identity providers to supply submitter data.
         *
         * @param array|null $data Associative array with at least name and email.
         */
        $data = apply_filters('rrze_formwizard_sso_user_data', $data);

        if (!is_array($data)) {
            return null;
        }

        $name = sanitize_text_field((string) ($data['name'] ?? ''));
        $email = sanitize_email((string) ($data['email'] ?? ''));

        if ($name === '' && $email === '') {
            return null;
        }

        return [
            'name' => $name,
            'email' => $email,
            'login' => sanitize_user((string) ($data['login'] ?? ''), true),
            'source' => sanitize_key((string) ($data['source'] ?? 'unknown')),
        ];
    }

    public static function formatForMail(?array $data): string
    {
        if ($data === null) {
            return '';
        }

        $lines = [
            __('SSO / logged-in user', 'rrze-formular'),
            __('Name', 'rrze-formular') . ': ' . $data['name'],
            __('E-mail', 'rrze-formular') . ': ' . $data['email'],
        ];

        if (!empty($data['login'])) {
            $lines[] = __('Login', 'rrze-formular') . ': ' . $data['login'];
        }

        if (!empty($data['source'])) {
            $lines[] = __('Source', 'rrze-formular') . ': ' . $data['source'];
        }

        return implode("\n", $lines);
    }
}
