<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

class AllowedDomains
{
    private const SETTINGS_PLUGIN = 'rrze-settings/rrze-settings.php';
    private const SETTINGS_OPTION = 'rrze_settings';
    private const SETTINGS_DOMAINS_KEY = 'rrze_formular_allowedDomains';

    private static ?bool $rrzeSettingsActive = null;

    /**
     * @var list<string>|null
     */
    private static ?array $allowedDomains = null;

    /**
     * @var list<string>|null
     */
    private static ?array $confirmationDomains = null;

    /**
     * @var list<string>
     */
    private const SETTINGS_DOMAIN_OPTION_KEYS = [
        'rrze_formular_allowedDomains',
        'rrze-formular_allowedDomains',
    ];

    public static function isRrzeSettingsActive(): bool
    {
        if (self::$rrzeSettingsActive !== null) {
            return self::$rrzeSettingsActive;
        }

        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (is_plugin_active(self::SETTINGS_PLUGIN)) {
            return self::$rrzeSettingsActive = true;
        }

        if (
            is_multisite()
            && function_exists('is_plugin_active_for_network')
            && is_plugin_active_for_network(self::SETTINGS_PLUGIN)
        ) {
            return self::$rrzeSettingsActive = true;
        }

        /**
         * @deprecated 1.2.0 Use rrze_formular_rrze_settings_active.
         */
        $legacy = (bool) apply_filters('rrze_formular_rrze_cms_active', false);

        return self::$rrzeSettingsActive = $legacy || (bool) apply_filters('rrze_formular_rrze_settings_active', false);
    }

    /**
     * @deprecated 1.2.0 Use isRrzeSettingsActive().
     */
    public static function isRrzeCmsActive(): bool
    {
        return self::isRrzeSettingsActive();
    }

    /**
     * @return list<string>
     */
    public static function getAllowedDomains(): array
    {
        if (self::$allowedDomains !== null) {
            return self::$allowedDomains;
        }

        $domains = self::loadPrimaryRecipientDomains();

        /**
         * @param list<string> $domains
         */
        $domains = apply_filters('rrze_formular_allowed_domains', $domains);

        return self::$allowedDomains = self::parseDomains($domains);
    }

    /**
     * @return list<string>
     */
    private static function loadPrimaryRecipientDomains(): array
    {
        if (self::isRrzeSettingsActive()) {
            return self::parseDomains(self::getRrzeSettingsDomainsRaw());
        }

        return self::parseDomains(self::getPluginRecipientDomainsRaw());
    }

    private static function getPluginRecipientDomainsRaw(): string
    {
        $options = Mailer::getOptions();

        return (string) ($options['allowed_domains'] ?? '');
    }

    private static function getPluginConfirmationDomainsRaw(): string
    {
        $options = Mailer::getOptions();

        return (string) ($options['allowed_confirmation_domains'] ?? '');
    }

    public static function hasConfiguredDomains(): bool
    {
        return self::getAllowedDomains() !== [];
    }

    public static function isEmailDomainAllowed(string $email): bool
    {
        return self::isEmailInDomains($email, self::getAllowedDomains());
    }

    /**
     * Domains that may receive automatic confirmation mails.
     *
     * Uses only the dedicated confirmation allowlist. An empty result disables
     * confirmations.
     *
     * @return list<string>
     */
    public static function getConfirmationDomains(): array
    {
        if (self::$confirmationDomains !== null) {
            return self::$confirmationDomains;
        }

        $domains = self::parseDomains(self::getPluginConfirmationDomainsRaw());

        /**
         * @param list<string> $domains
         */
        $domains = apply_filters('rrze_formular_confirmation_domains', $domains);

        return self::$confirmationDomains = self::parseDomains($domains);
    }

    public static function hasConfirmationDomainsConfigured(): bool
    {
        return self::getConfirmationDomains() !== [];
    }

    public static function isConfirmationEmailAllowed(string $email): bool
    {
        return self::isEmailInDomains($email, self::getConfirmationDomains());
    }

    public static function isBlockRecipientAllowed(string $email): bool
    {
        $email = trim($email);
        if ($email === '') {
            return true;
        }

        if (!is_email($email)) {
            return false;
        }

        if (!self::hasConfiguredDomains()) {
            return false;
        }

        return self::isEmailDomainAllowed($email);
    }

    public static function sanitizeAllowedRecipientEmail(string $email): string
    {
        $email = trim($email);
        if ($email === '') {
            return '';
        }

        return self::isBlockRecipientAllowed($email) ? $email : '';
    }

    /**
     * @return list<string>
     */
    public static function parseDomains(mixed $raw): array
    {
        return self::normalizeDomains(self::domainLines($raw));
    }

    public static function sanitizeDomainList(mixed $raw): string
    {
        return implode(PHP_EOL, self::parseDomains($raw));
    }

    /**
     * @return list<string>
     */
    public static function invalidDomains(mixed $raw): array
    {
        $invalid = [];

        foreach (self::domainLines($raw) as $line) {
            $domain = self::normalizeDomain((string) $line);
            if ($domain === '') {
                continue;
            }

            if (!self::isValidDomain($domain)) {
                $invalid[] = $domain;
            }
        }

        return array_values(array_unique($invalid));
    }

    /**
     * @return list<string>
     */
    private static function domainLines(mixed $raw): array
    {
        $lines = [];

        if (is_array($raw)) {
            foreach ($raw as $key => $value) {
                if (is_string($value) && trim($value) !== '') {
                    $lines[] = $value;
                } elseif (is_string($key) && trim($key) !== '' && !is_numeric($key)) {
                    $lines[] = $key;
                }
            }
        } elseif (is_object($raw)) {
            foreach (get_object_vars($raw) as $key => $value) {
                if (is_string($value) && trim($value) !== '') {
                    $lines[] = $value;
                } elseif (is_string($key) && trim($key) !== '') {
                    $lines[] = $key;
                }
            }
        } else {
            $lines = preg_split('/\R/', (string) $raw) ?: [];
        }

        return array_values(array_map('strval', $lines));
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private static function normalizeDomains(array $lines): array
    {
        $domains = [];

        foreach ($lines as $line) {
            $domain = self::normalizeDomain($line);
            if ($domain !== '' && self::isValidDomain($domain)) {
                $domains[] = $domain;
            }
        }

        return array_values(array_unique($domains));
    }

    private static function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = trim($domain, '@.');

        if ($domain === '') {
            return '';
        }

        if (function_exists('idn_to_ascii') && defined('IDNA_DEFAULT') && defined('INTL_IDNA_VARIANT_UTS46')) {
            $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (is_string($ascii) && $ascii !== '') {
                $domain = strtolower($ascii);
            }
        }

        return $domain;
    }

    private static function isValidDomain(string $domain): bool
    {
        if ($domain === '' || strlen($domain) > 253 || !str_contains($domain, '.')) {
            return false;
        }

        if (preg_match('/[\s\/@:]/', $domain)) {
            return false;
        }

        foreach (explode('.', $domain) as $label) {
            if ($label === '' || strlen($label) > 63) {
                return false;
            }

            if (!preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label)) {
                return false;
            }
        }

        return true;
    }

    private static function getRrzeSettingsDomainsRaw(): mixed
    {
        if (class_exists('\RRZE\Settings\Options')) {
            $options = \RRZE\Settings\Options::getSiteOptions();
            $plugins = $options->plugins ?? null;
            if (is_object($plugins) && isset($plugins->{self::SETTINGS_DOMAINS_KEY})) {
                $value = $plugins->{self::SETTINGS_DOMAINS_KEY};
                if (self::hasDomainValue($value)) {
                    return $value;
                }
            }
        }

        foreach (self::SETTINGS_DOMAIN_OPTION_KEYS as $optionKey) {
            foreach ([static fn (): mixed => get_site_option($optionKey, ''), static fn (): mixed => get_option($optionKey, '')] as $reader) {
                $value = $reader();
                if (self::hasDomainValue($value)) {
                    return $value;
                }
            }
        }

        foreach ([static fn (): mixed => get_site_option(self::SETTINGS_OPTION, []), static fn (): mixed => get_option(self::SETTINGS_OPTION, [])] as $reader) {
            $settings = $reader();
            if (!is_array($settings)) {
                continue;
            }

            $plugins = $settings['plugins'] ?? [];
            if (!is_array($plugins)) {
                continue;
            }

            foreach (self::SETTINGS_DOMAIN_OPTION_KEYS as $optionKey) {
                if (isset($plugins[$optionKey]) && self::hasDomainValue($plugins[$optionKey])) {
                    return $plugins[$optionKey];
                }
            }
        }

        return '';
    }

    private static function hasDomainValue(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return $value !== [];
        }

        if (is_object($value)) {
            return get_object_vars($value) !== [];
        }

        return false;
    }

    private static function emailDomain(string $email): string
    {
        $at = strrchr($email, '@');

        return $at === false ? '' : strtolower(substr($at, 1));
    }

    /**
     * @param list<string> $domains
     */
    private static function isEmailInDomains(string $email, array $domains): bool
    {
        $email = sanitize_email($email);
        if (!is_email($email) || $domains === []) {
            return false;
        }

        $domain = self::emailDomain($email);
        if ($domain === '') {
            return false;
        }

        foreach ($domains as $allowed) {
            $allowed = strtolower(trim((string) $allowed));
            if ($allowed === '') {
                continue;
            }

            if ($domain === $allowed || str_ends_with($domain, '.' . $allowed)) {
                return true;
            }
        }

        return false;
    }
}
