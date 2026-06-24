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

    public static function getSubjectPrefix(): string
    {
        $options = self::getOptions();
        $prefix = sanitize_text_field((string) ($options['mail_subject_prefix'] ?? ''));
        $prefix = trim($prefix);
        $prefix = trim($prefix, "[]");

        return trim($prefix);
    }

    public static function formatSubject(string $subject): string
    {
        $subject = trim($subject);
        $prefix = self::getSubjectPrefix();

        if ($prefix === '') {
            return $subject;
        }

        $prefix = sanitize_text_field((string) apply_filters('rrze_formular_mail_subject_prefix', $prefix));

        if ($prefix === '') {
            return $subject;
        }

        return sprintf('[%s] %s', $prefix, $subject);
    }

    public static function getHomepageUrl(): string
    {
        return esc_url_raw(home_url('/'));
    }

    public static function formatSiteLinkLine(): string
    {
        $title = sanitize_text_field(get_bloginfo('name'));
        $url = self::getHomepageUrl();

        if ($title === '') {
            return $url;
        }

        return $title . ': ' . $url;
    }

    public static function formatMailDateLine(): string
    {
        return __('Date', 'rrze-formular') . ': ' . wp_date(self::getMailDateTimeFormat());
    }

    private static function getMailDateTimeFormat(): string
    {
        $locale = function_exists('determine_locale') ? determine_locale() : get_locale();

        if (str_starts_with(strtolower((string) $locale), 'de')) {
            return 'd.m.Y H:i';
        }

        return 'Y-m-d H:i';
    }

    public static function resolveSubmissionUrl(string $pageUrl = ''): string
    {
        $pageUrl = esc_url_raw(trim($pageUrl));

        if ($pageUrl !== '' && self::isAllowedSubmissionUrl($pageUrl)) {
            return $pageUrl;
        }

        $referer = wp_get_referer(false);
        if (is_string($referer) && $referer !== '' && self::isAllowedSubmissionUrl($referer)) {
            return esc_url_raw($referer);
        }

        return '';
    }

    public static function websiteHeaders(string $submissionUrl): array
    {
        $submissionUrl = esc_url_raw(trim($submissionUrl));

        if ($submissionUrl === '') {
            return [];
        }

        return [sprintf('X-Website: %s', $submissionUrl)];
    }

    private static function isAllowedSubmissionUrl(string $url): bool
    {
        if (!wp_http_validate_url($url)) {
            return false;
        }

        $parsed = wp_parse_url($url);
        $site = wp_parse_url(home_url('/'));

        if (!is_array($parsed) || !is_array($site)) {
            return false;
        }

        $urlHost = strtolower((string) ($parsed['host'] ?? ''));
        $siteHost = strtolower((string) ($site['host'] ?? ''));

        return $urlHost !== '' && $urlHost === $siteHost;
    }

    private static function isAllowedConfirmationRecipient(string $email): bool
    {
        /**
         * Filter whether a confirmation mail may be sent to the given address.
         * Return false to block the recipient (e.g. domain allowlists).
         *
         * @param bool $allowed Default true.
         * @param string $email Sanitized recipient address.
         */
        return (bool) apply_filters('rrze_formular_allowed_confirmation_email', true, $email);
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

        $subject = self::formatSubject($subject);

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
        string $body,
        array $headers = []
    ): bool {
        if (!$enabled) {
            return false;
        }

        $submitterEmail = sanitize_email($submitterEmail);
        if (!is_email($submitterEmail)) {
            return false;
        }

        if (!self::isAllowedConfirmationRecipient($submitterEmail)) {
            return false;
        }

        return self::sendOperatorMail(
            $submitterEmail,
            $subject,
            $body,
            $headers
        );
    }
}
