<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

class Mailer
{
    public static function getOptions(): array
    {
        $options = get_option('rrze-formular', []);
        return is_array($options) ? $options : [];
    }

    public static function getAdministratorEmail(): string
    {
        return sanitize_email((string) get_option('admin_email'));
    }

    public static function getAdministratorName(): string
    {
        $email = self::getAdministratorEmail();
        $user = $email !== '' ? get_user_by('email', $email) : false;

        if ($user instanceof \WP_User && $user->display_name !== '') {
            return sanitize_text_field($user->display_name);
        }

        return sanitize_text_field(get_bloginfo('name'));
    }

    public static function getSenderAddress(): string
    {
        $address = apply_filters('rrze_formwizard_sender_email', self::getAdministratorEmail());

        return sanitize_email((string) $address);
    }

    public static function getSenderName(): string
    {
        $name = apply_filters('rrze_formular_sender_name', self::getAdministratorName());

        return sanitize_text_field((string) $name);
    }

    public static function getRecipient(): string
    {
        $recipient = apply_filters('rrze_formular_recipient_email', self::getAdministratorEmail());

        return sanitize_email((string) $recipient);
    }

    public static function sendOperatorMail(
        string $recipient,
        string $subject,
        string $body,
        array $headers = []
    ): bool {
        $fromEmail = self::getSenderAddress();
        $fromName = self::getSenderName();

        if (!is_email($fromEmail) || !is_email($recipient)) {
            return false;
        }

        $defaultHeaders = [
            'Content-Type: text/plain; charset=UTF-8',
            sprintf('From: %s <%s>', $fromName, $fromEmail),
        ];

        $allHeaders = array_merge($defaultHeaders, $headers);

        return (bool) wp_mail($recipient, $subject, $body, $allHeaders);
    }

    public static function maybeSendConfirmation(
        bool $enabled,
        string $submitterEmail,
        string $subject,
        string $body
    ): bool {
        if (!$enabled) {
            return false;
        }

        $submitterEmail = sanitize_email($submitterEmail);
        if (!is_email($submitterEmail)) {
            return false;
        }

        return self::sendOperatorMail(
            $submitterEmail,
            $subject,
            $body
        );
    }
}
