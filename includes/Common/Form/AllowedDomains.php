<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

class AllowedDomains
{
    private const SETTINGS_PLUGIN = 'rrze-settings/rrze-settings.php';
    private const SETTINGS_OPTION = 'rrze_settings';
    private const SETTINGS_DOMAINS_KEY = 'rrze_formular_allowedDomains';

    public static function isRrzeSettingsActive(): bool
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (is_plugin_active(self::SETTINGS_PLUGIN)) {
            return true;
        }

        if (
            is_multisite()
            && function_exists('is_plugin_active_for_network')
            && is_plugin_active_for_network(self::SETTINGS_PLUGIN)
        ) {
            return true;
        }

        /**
         * @deprecated 1.2.0 Use rrze_formular_rrze_settings_active.
         */
        $legacy = (bool) apply_filters('rrze_formular_rrze_cms_active', false);

        return $legacy || (bool) apply_filters('rrze_formular_rrze_settings_active', false);
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
        if (self::isRrzeSettingsActive()) {
            $raw = self::getRrzeSettingsDomainsRaw();
        } else {
            $options = get_option('rrze-formular', []);
            $raw = is_array($options) ? ($options['allowed_domains'] ?? '') : '';
        }

        return self::parseDomains($raw);
    }

    public static function hasConfiguredDomains(): bool
    {
        return self::getAllowedDomains() !== [];
    }

    public static function isEmailDomainAllowed(string $email): bool
    {
        $email = sanitize_email($email);
        if (!is_email($email)) {
            return false;
        }

        $domains = self::getAllowedDomains();
        if ($domains === []) {
            return false;
        }

        $domain = self::emailDomain($email);
        if ($domain === '') {
            return false;
        }

        foreach ($domains as $allowed) {
            if ($domain === $allowed || str_ends_with($domain, '.' . $allowed)) {
                return true;
            }
        }

        return false;
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
    private static function parseDomains(mixed $raw): array
    {
        if (is_array($raw)) {
            $lines = array_map('strval', $raw);
        } else {
            $lines = preg_split('/\R/', (string) $raw) ?: [];
        }

        $domains = [];

        foreach ($lines as $line) {
            $line = strtolower(trim((string) $line));
            $line = trim($line, '@.');
            if ($line !== '') {
                $domains[] = $line;
            }
        }

        return array_values(array_unique($domains));
    }

    private static function getRrzeSettingsDomainsRaw(): mixed
    {
        $standalone = get_site_option(self::SETTINGS_DOMAINS_KEY, '');
        if (self::hasDomainValue($standalone)) {
            return $standalone;
        }

        $settings = get_site_option(self::SETTINGS_OPTION, []);
        if (!is_array($settings)) {
            return '';
        }

        $plugins = $settings['plugins'] ?? [];
        if (!is_array($plugins)) {
            return '';
        }

        return $plugins[self::SETTINGS_DOMAINS_KEY] ?? '';
    }

    private static function hasDomainValue(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return false;
    }

    private static function emailDomain(string $email): string
    {
        $at = strrchr($email, '@');

        return $at === false ? '' : strtolower(substr($at, 1));
    }
}
