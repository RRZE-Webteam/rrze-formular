<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

/**
 * Loads and normalizes the domains allowed for form recipients.
 *
 * RRZE Settings takes precedence when active; otherwise plugin settings are used.
 * Matching accepts both an allowed domain and its subdomains. Results are cached
 * for the current PHP request.
 */
class AllowedDomains
{
    /**
     * Plugin basename used to detect RRZE Settings.
     *
     * @var string
     */
    private const SETTINGS_PLUGIN = 'rrze-settings/rrze-settings.php';

    /**
     * Option containing the grouped RRZE Settings values.
     *
     * @var string
     */
    private const SETTINGS_OPTION = 'rrze_settings';

    /**
     * Recipient-domain field within the RRZE Settings plugins group.
     *
     * @var string
     */
    private const SETTINGS_DOMAINS_KEY = 'rrze_formular_allowedDomains';

    /**
     * Cached activation check; null means the check has not run yet.
     *
     * @var bool|null
     */
    private static ?bool $rrzeSettingsActive = null;

    /**
     * Cached normalized domains; null means they have not been loaded yet.
     *
     * @var list<string>|null
     */
    private static ?array $allowedDomains = null;

    /**
     * Current and legacy option keys checked when reading stored domains.
     *
     * @var list<string>
     */
    private const SETTINGS_DOMAIN_OPTION_KEYS = [
        'rrze_formular_allowedDomains',
        'rrze-formular_allowedDomains',
    ];

    /**
     * Detect site or network activation of RRZE Settings, including filter overrides.
     *
     * @return bool Whether RRZE Settings is the source of recipient domains.
     */
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
     * Provide the legacy name for the RRZE Settings activation check.
     *
     * @return bool Whether RRZE Settings is active.
     * @deprecated 1.2.0 Use isRrzeSettingsActive().
     * @see self::isRrzeSettingsActive()
     */
    public static function isRrzeCmsActive(): bool
    {
        return self::isRrzeSettingsActive();
    }

    /**
     * Load, filter and cache the recipient-domain list.
     *
     * @return list<string> Unique normalized domains, or an empty list when none are configured.
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
     * Read domains from the active settings provider.
     *
     * An empty RRZE Settings list does not fall back to the plugin list.
     *
     * @return list<string> Parsed domains from RRZE Settings or the plugin options.
     */
    private static function loadPrimaryRecipientDomains(): array
    {
        if (self::isRrzeSettingsActive()) {
            return self::parseDomains(self::getRrzeSettingsDomainsRaw());
        }

        return self::parseDomains(self::getPluginRecipientDomainsRaw());
    }

    /**
     * Read the local allowed_domains option before normalization.
     *
     * @return string Stored domain list, or an empty string.
     */
    private static function getPluginRecipientDomainsRaw(): string
    {
        $options = Mailer::getOptions();

        return (string) ($options['allowed_domains'] ?? '');
    }

    /**
     * Check whether at least one valid recipient domain is configured.
     *
     * @return bool True when the normalized domain list is not empty.
     */
    public static function hasConfiguredDomains(): bool
    {
        return self::getAllowedDomains() !== [];
    }

    /**
     * Check an e-mail address against the configured domains and their subdomains.
     *
     * @param string $email Address to check.
     * @return bool Whether the address is valid and its domain is allowed.
     */
    public static function isEmailDomainAllowed(string $email): bool
    {
        return self::isEmailInDomains($email, self::getAllowedDomains());
    }

    /**
     * Validate an optional recipient override from a form block.
     *
     * @param string $email Block recipient address; an empty value selects the fallback recipient.
     * @return bool True for an empty value or a valid address on an allowed domain.
     */
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

    /**
     * Keep an allowed block recipient or clear the override.
     *
     * @param string $email Raw block recipient address.
     * @return string Trimmed allowed address, or an empty string to use recipient fallback.
     */
    public static function sanitizeAllowedRecipientEmail(string $email): string
    {
        $email = trim($email);
        if ($email === '') {
            return '';
        }

        return self::isBlockRecipientAllowed($email) ? $email : '';
    }

    /**
     * Normalize a stored domain list and discard invalid or duplicate entries.
     *
     * @param mixed $raw Newline-separated text, an array or an object containing domains.
     * @return list<string> Valid lowercase domains.
     */
    public static function parseDomains(mixed $raw): array
    {
        return self::normalizeDomains(self::domainLines($raw));
    }

    /**
     * Prepare a domain list for storage as one normalized domain per line.
     *
     * @param mixed $raw Domain values accepted by parseDomains().
     * @return string Unique valid domains joined with the platform line ending.
     */
    public static function sanitizeDomainList(mixed $raw): string
    {
        return implode(PHP_EOL, self::parseDomains($raw));
    }

    /**
     * Collect non-empty domain entries that fail syntax validation.
     *
     * @param mixed $raw Domain values accepted by parseDomains().
     * @return list<string> Unique invalid entries after normalization.
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
     * Extract domain strings from text, lists or keyed settings values.
     *
     * @param mixed $raw Newline-separated text, an array or an object.
     * @return list<string> Raw domain entries; non-numeric keys can supply missing values.
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
     * Normalize domain entries, keeping only valid unique values.
     *
     * @param list<string> $lines Raw domain entries.
     * @return list<string> Valid normalized domains in first-seen order.
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

    /**
     * Trim and lowercase a domain, converting international names when supported.
     *
     * @param string $domain Domain text, optionally surrounded by whitespace, dots or an at-sign.
     * @return string Normalized domain; syntax validation is performed separately.
     */
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

    /**
     * Check domain length, labels and allowed characters.
     *
     * @param string $domain Normalized ASCII domain to validate.
     * @return bool True for a dotted domain with valid labels; no DNS lookup is made.
     */
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

    /**
     * Read the first populated domain value from RRZE Settings or legacy options.
     *
     * The RRZE Settings API is preferred. Standalone option keys and grouped
     * network/site options provide compatibility with older storage formats.
     *
     * @return mixed Stored text, array or object; an empty string when no value is found.
     */
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

    /**
     * Check whether a stored value contains any domain entries.
     *
     * @param mixed $value Candidate text, array or object.
     * @return bool Whether the value is non-empty in a supported format.
     */
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

    /**
     * Extract the lowercase domain after the final at-sign.
     *
     * @param string $email E-mail address to inspect.
     * @return string Domain portion, or an empty string if no at-sign exists.
     */
    private static function emailDomain(string $email): string
    {
        $at = strrchr($email, '@');

        return $at === false ? '' : strtolower(substr($at, 1));
    }

    /**
     * Match a valid e-mail address against a supplied domain list.
     *
     * @param string $email Address to sanitize and validate.
     * @param list<string> $domains Allowed domains.
     * @return bool True for an exact domain match or a subdomain match.
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
