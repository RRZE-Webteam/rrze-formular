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
        return self::getAdministratorEmail();
    }

    public static function getSenderName(): string
    {
        return sanitize_text_field(get_bloginfo('name'));
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array{email: string, name: string, source: string}
     */
    public static function resolveRecipient(array $attributes): array
    {
        $blockEmail = trim((string) ($attributes['recipientEmail'] ?? ''));
        $blockName = sanitize_text_field((string) ($attributes['recipientName'] ?? ''));

        if ($blockEmail !== '') {
            $recipient = [
                'email' => sanitize_email($blockEmail),
                'name' => $blockName,
                'source' => 'block',
            ];
        } else {
            $options = self::getOptions();
            $defaultEmail = sanitize_email((string) ($options['default_recipient_email'] ?? ''));
            $defaultName = sanitize_text_field((string) ($options['default_recipient_name'] ?? ''));

            if ($defaultEmail !== '') {
                $recipient = [
                    'email' => $defaultEmail,
                    'name' => $defaultName,
                    'source' => 'settings',
                ];
            } else {
                $recipient = [
                    'email' => self::getAdministratorEmail(),
                    'name' => $defaultName,
                    'source' => 'default',
                ];
            }
        }

        /**
         * @param array{email: string, name: string, source: string} $recipient
         * @param array<string, mixed> $attributes
         */
        return apply_filters('rrze_formular_resolved_recipient', $recipient, $attributes);
    }

    /**
     * @param array{email: string, name: string, source: string} $recipient
     */
    public static function validateResolvedRecipient(array $recipient, string $configuredBlockEmail = ''): ?string
    {
        $configuredBlockEmail = trim($configuredBlockEmail);
        if ($configuredBlockEmail !== '' && !is_email($configuredBlockEmail)) {
            return __('Please enter a valid e-mail address.', 'rrze-formular');
        }

        $email = sanitize_email((string) ($recipient['email'] ?? ''));
        if ($email === '' || !is_email($email)) {
            return __('No valid recipient configured.', 'rrze-formular');
        }

        $source = (string) ($recipient['source'] ?? 'default');
        if ($source !== 'default' || AllowedDomains::hasConfiguredDomains()) {
            if (!AllowedDomains::hasConfiguredDomains()) {
                if ($source === 'block') {
                    return __('The recipient e-mail domain is not allowed.', 'rrze-formular');
                }

                return null;
            }

            if (!AllowedDomains::isEmailDomainAllowed($email)) {
                return __('The recipient e-mail domain is not allowed.', 'rrze-formular');
            }
        }

        return null;
    }

    public static function formatRecipientAddress(string $email, string $name = ''): string
    {
        $email = sanitize_email($email);
        $name = sanitize_text_field($name);

        if ($name !== '') {
            return sprintf('%s <%s>', $name, $email);
        }

        return $email;
    }

    public static function getRecipient(): string
    {
        $recipient = self::resolveRecipient([]);

        return sanitize_email((string) ($recipient['email'] ?? ''));
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
        if (AllowedDomains::hasConfiguredDomains()) {
            return AllowedDomains::isEmailDomainAllowed($email);
        }

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
        array $headers = [],
        string $recipientName = ''
    ): bool {
        $fromEmail = self::getSenderAddress();
        $fromName = self::getSenderName();
        $to = self::formatRecipientAddress($recipient, $recipientName);

        if (!is_email($fromEmail) || !is_email(self::extractEmailAddress($to))) {
            return false;
        }

        $subject = self::formatSubject($subject);

        $defaultHeaders = [
            'Content-Type: text/plain; charset=UTF-8',
            sprintf('From: %s <%s>', $fromName, $fromEmail),
        ];

        $allHeaders = array_merge($defaultHeaders, $headers);

        return (bool) wp_mail($to, $subject, $body, $allHeaders);
    }

    private static function extractEmailAddress(string $address): string
    {
        if (preg_match('/<([^>]+)>\s*$/', $address, $matches)) {
            return sanitize_email($matches[1]);
        }

        return sanitize_email($address);
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
