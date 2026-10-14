<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

/**
 * Resolves mail addresses and sends plain-text operator messages through WordPress.
 *
 * The website supplies the sender identity. A block recipient overrides the
 * plugin default, which in turn overrides the site administrator address.
 * Recipient-domain validation is performed separately before delivery.
 */
class Mailer
{
    /**
     * Plugin options cached for the current PHP request; null before the first read.
     *
     * @var array<string, mixed>|null
     */
    private static ?array $optionsCache = null;

    /**
     * Load and cache the plugin options for this request.
     *
     * @return array<string, mixed> Stored options, or an empty array if the option is invalid.
     */
    public static function getOptions(): array
    {
        if (self::$optionsCache === null) {
            $options = get_option('rrze-formular', []);
            self::$optionsCache = is_array($options) ? $options : [];
        }

        return self::$optionsCache;
    }

    /**
     * Read the site administrator e-mail used as the fallback recipient and sender.
     *
     * @return string Sanitized admin_email option.
     */
    public static function getAdministratorEmail(): string
    {
        return sanitize_email((string) get_option('admin_email'));
    }

    /**
     * Find the administrator display name, falling back to the site name.
     *
     * @return string Sanitized display name for the administrator address.
     */
    public static function getAdministratorName(): string
    {
        $email = self::getAdministratorEmail();
        $user = $email !== '' ? get_user_by('email', $email) : false;

        if ($user instanceof \WP_User && $user->display_name !== '') {
            return sanitize_text_field($user->display_name);
        }

        return sanitize_text_field(get_bloginfo('name'));
    }

    /**
     * Use the site administrator e-mail as the sender address.
     *
     * @return string Sanitized website sender address.
     */
    public static function getSenderAddress(): string
    {
        return self::getAdministratorEmail();
    }

    /**
     * Use the website name as the sender display name.
     *
     * @return string Sanitized site name.
     */
    public static function getSenderName(): string
    {
        return sanitize_text_field(get_bloginfo('name'));
    }

    /**
     * Resolve a recipient from the block, plugin settings or site administrator.
     *
     * The source is block, settings or default. The rrze_formular_resolved_recipient
     * filter can override the result; callers must validate it before sending.
     *
     * @param array<string, mixed> $attributes Optional recipientEmail and recipientName block attributes.
     * @return array{email: string, name: string, source: string} Filtered recipient and its source.
     */
    public static function resolveRecipient(array $attributes): array
    {
        $blockEmail = trim((string) ($attributes['recipientEmail'] ?? ''));
        $blockName = sanitize_text_field((string) ($attributes['recipientName'] ?? ''));
        $options = self::getOptions();
        $defaultName = sanitize_text_field((string) ($options['default_recipient_name'] ?? ''));

        if ($blockEmail !== '') {
            $recipient = [
                'email' => sanitize_email($blockEmail),
                'name' => $blockName !== '' ? $blockName : $defaultName,
                'source' => 'block',
            ];
        } else {
            $defaultEmail = sanitize_email((string) ($options['default_recipient_email'] ?? ''));

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
     * Validate a resolved recipient and apply the appropriate domain policy.
     *
     * Explicit block recipients require configured allowed domains. With no domain
     * list, plugin-default and administrator recipients are accepted if syntactically
     * valid. When domains are configured, all recipient sources must match.
     *
     * @param array{email: string, name: string, source: string} $recipient Recipient returned by resolveRecipient().
     * @param string $configuredBlockEmail Original configured override, used to detect an invalid address.
     * @return string|null Translated error message, or null when the recipient is valid.
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

    /**
     * Format an e-mail recipient using the common mailbox formatter.
     *
     * @param string $email E-mail address to sanitize.
     * @param string $name Optional display name.
     * @return string Address alone or a display name followed by the address in angle brackets.
     */
    public static function formatRecipientAddress(string $email, string $name = ''): string
    {
        return self::formatMailboxAddress($email, $name);
    }

    /**
     * Format a mailbox address, quoting a display name when necessary.
     *
     * @param string $email E-mail address to sanitize.
     * @param string $name Optional display name.
     * @return string Address alone or a display name followed by the address in angle brackets.
     */
    public static function formatMailboxAddress(string $email, string $name = ''): string
    {
        $email = sanitize_email($email);
        $name = sanitize_text_field($name);

        if ($name === '') {
            return $email;
        }

        if (preg_match('/[,;"\\\\]/', $name)) {
            $name = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $name) . '"';
        }

        return sprintf('%s <%s>', $name, $email);
    }

    /**
     * Resolve the fallback recipient without a block-specific override.
     *
     * @return string Sanitized recipient address; domain validation is not performed here.
     */
    public static function getRecipient(): string
    {
        $recipient = self::resolveRecipient([]);

        return sanitize_email((string) ($recipient['email'] ?? ''));
    }

    /**
     * Read the configured subject prefix and remove surrounding brackets.
     *
     * @return string Plain prefix text, or an empty string.
     */
    public static function getSubjectPrefix(): string
    {
        $options = self::getOptions();
        $prefix = sanitize_text_field((string) ($options['mail_subject_prefix'] ?? ''));
        $prefix = trim($prefix);
        $prefix = trim($prefix, "[]");

        return trim($prefix);
    }

    /**
     * Prepend a filtered subject prefix in square brackets when configured.
     *
     * @param string $subject Unprefixed subject text.
     * @return string Trimmed subject with the optional prefix.
     */
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

    /**
     * Return the sanitized URL of the website homepage.
     *
     * @return string Absolute homepage URL.
     */
    public static function getHomepageUrl(): string
    {
        return esc_url_raw(home_url('/'));
    }

    /**
     * Build the website identification line included in operator mail.
     *
     * @return string Site name and homepage URL, or the URL alone when the name is empty.
     */
    public static function formatSiteLinkLine(): string
    {
        $title = sanitize_text_field(get_bloginfo('name'));
        $url = self::getHomepageUrl();

        if ($title === '') {
            return $url;
        }

        return $title . ': ' . $url;
    }

    /**
     * Build a localized timestamp line using the WordPress timezone.
     *
     * @return string Translated date label and formatted current date/time.
     */
    public static function formatMailDateLine(): string
    {
        return __('Date', 'rrze-formular') . ': ' . wp_date(self::getMailDateTimeFormat());
    }

    /**
     * Choose a date/time format for the active locale.
     *
     * @return string German day-first format or year-first format for other locales.
     */
    private static function getMailDateTimeFormat(): string
    {
        $locale = function_exists('determine_locale') ? determine_locale() : get_locale();

        if (str_starts_with(strtolower((string) $locale), 'de')) {
            return 'd.m.Y H:i';
        }

        return 'Y-m-d H:i';
    }

    /**
     * Find a valid submission URL on the website host.
     *
     * @param string $pageUrl URL supplied by the form; the request referer is the fallback.
     * @return string Accepted URL, or an empty string when neither candidate is allowed.
     */
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

    /**
     * Build the optional X-Website header for the submission page.
     *
     * @param string $submissionUrl Validated page URL, normally from resolveSubmissionUrl().
     * @return list<string> Website header, or an empty list for an empty URL.
     */
    public static function websiteHeaders(string $submissionUrl): array
    {
        $submissionUrl = esc_url_raw(trim($submissionUrl));

        if ($submissionUrl === '') {
            return [];
        }

        return [sprintf('X-Website: %s', $submissionUrl)];
    }

    /**
     * Check WordPress URL validity and require the website hostname.
     *
     * @param string $url Candidate submission URL.
     * @return bool Whether the URL is valid and its hostname matches home_url().
     */
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

    /**
     * Send a plain-text UTF-8 message with the website sender and one operator recipient.
     *
     * A temporary phpmailer_init callback replaces the To list with the operator
     * address. The callback is removed after wp_mail() returns.
     *
     * @param string $recipient Operator e-mail address; the caller must apply domain validation.
     * @param string $subject Mail subject before the configured prefix is added.
     * @param string $body Plain-text message body.
     * @param list<string> $headers Additional headers, such as Reply-To and X-Website.
     * @param string $recipientName Optional operator display name.
     * @return bool Whether wp_mail() accepted the message; this does not confirm final delivery.
     */
    public static function sendOperatorMail(
        string $recipient,
        string $subject,
        string $body,
        array $headers = [],
        string $recipientName = ''
    ): bool {
        $fromEmail = self::getSenderAddress();
        $fromName = self::getSenderName();
        $recipientEmail = sanitize_email($recipient);
        $recipientName = sanitize_text_field($recipientName);

        if (!is_email($fromEmail) || !is_email($recipientEmail)) {
            return false;
        }

        $subject = self::formatSubject($subject);

        $defaultHeaders = [
            sprintf('From: %s', self::formatMailboxAddress($fromEmail, $fromName)),
            'Content-Type: text/plain; charset=UTF-8',
        ];

        $allHeaders = array_merge($defaultHeaders, $headers);

        $configureMailer = static function ($phpmailer) use (
            $recipientEmail,
            $recipientName
        ): void {
            if (!is_object($phpmailer) || !method_exists($phpmailer, 'clearAddresses')) {
                return;
            }

            $phpmailer->clearAddresses();
            $phpmailer->addAddress($recipientEmail, $recipientName);
            $phpmailer->CharSet = 'UTF-8';
            $phpmailer->isHTML(false);
        };

        add_action('phpmailer_init', $configureMailer, 99999, 1);

        $sent = (bool) wp_mail(
            self::formatMailboxAddress($recipientEmail, $recipientName),
            $subject,
            $body,
            $allHeaders
        );

        remove_action('phpmailer_init', $configureMailer, 99999);

        return $sent;
    }

    /**
     * Extract an e-mail address from a mailbox string.
     *
     * @param string $address Plain address or display name with an address in angle brackets.
     * @return string Sanitized e-mail portion.
     */
    private static function extractEmailAddress(string $address): string
    {
        if (preg_match('/<([^>]+)>\s*$/', $address, $matches)) {
            return sanitize_email($matches[1]);
        }

        return sanitize_email($address);
    }

}
