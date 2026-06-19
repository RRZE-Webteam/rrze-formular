<?php

namespace RRZE\FormWizard\Common\Form;

defined('ABSPATH') || exit;

class Mailer
{
    public static function getOptions(): array
    {
        $options = get_option('rrze-formular', []);
        return is_array($options) ? $options : [];
    }

    public static function getSenderAddress(): string
    {
        /**
         * The sender address always comes from the server configuration.
         */
        $address = apply_filters('rrze_formwizard_sender_email', get_option('admin_email'));

        return sanitize_email((string) $address);
    }

    public static function getSenderName(): string
    {
        $options = self::getOptions();
        $name = $options['sender_name'] ?? get_bloginfo('name');

        return sanitize_text_field((string) $name);
    }

    public static function getDefaultRecipient(): string
    {
        $options = self::getOptions();
        $recipient = $options['default_recipient'] ?? '';

        if ($recipient === '') {
            $recipient = get_option('admin_email');
        }

        return sanitize_email((string) $recipient);
    }

    public static function getAllowedDomains(): array
    {
        $options = self::getOptions();
        $raw = (string) ($options['allowed_domains'] ?? '');

        return self::parseDomainList($raw);
    }

    public static function getAllowedConfirmationDomains(): array
    {
        $options = self::getOptions();
        $raw = (string) ($options['allowed_confirmation_domains'] ?? '');

        return self::parseDomainList($raw);
    }

    public static function parseDomainList(string $raw): array
    {
        $domains = [];
        foreach (preg_split('/\R/', strtolower($raw)) ?: [] as $line) {
            $line = trim($line);
            $line = ltrim($line, '@');
            if ($line !== '') {
                $domains[] = $line;
            }
        }

        return array_values(array_unique($domains));
    }

    public static function emailMatchesDomain(string $email, array $domains): bool
    {
        $email = strtolower(trim($email));
        if ($email === '' || !is_email($email)) {
            return false;
        }

        $at = strrpos($email, '@');
        if ($at === false) {
            return false;
        }

        $domain = substr($email, $at + 1);

        foreach ($domains as $allowed) {
            if ($domain === $allowed || str_ends_with($domain, '.' . $allowed)) {
                return true;
            }
        }

        return false;
    }

    public static function resolveRecipient(string $requestedRecipient = ''): string
    {
        $recipient = sanitize_email($requestedRecipient);
        $allowedDomains = self::getAllowedDomains();

        if ($recipient === '' || !is_email($recipient)) {
            $recipient = self::getDefaultRecipient();
        }

        if (!empty($allowedDomains) && !self::emailMatchesDomain($recipient, $allowedDomains)) {
            $recipient = self::getDefaultRecipient();
        }

        if (!is_email($recipient)) {
            return '';
        }

        return $recipient;
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

        $allowed = self::getAllowedConfirmationDomains();
        if (empty($allowed) || !self::emailMatchesDomain($submitterEmail, $allowed)) {
            return false;
        }

        return self::sendOperatorMail(
            $submitterEmail,
            $subject,
            $body
        );
    }
}
