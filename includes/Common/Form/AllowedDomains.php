<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

class AllowedDomains
{
    private const SETTINGS_PLUGIN = 'rrze-settings/rrze-settings.php';
    private const SETTINGS_OPTION = 'rrze_settings';
    private const SETTINGS_DOMAINS_KEY = 'rrze_formular_allowedDomains';

    /**
     * @var list<string>
     */
    private const SETTINGS_DOMAIN_OPTION_KEYS = [
        'rrze_formular_allowedDomains',
        'rrze-formular_allowedDomains',
    ];

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

        $domains = self::parseDomains($raw);

        /**
         * @param list<string> $domains
         */
        $domains = apply_filters('rrze_formular_allowed_domains', $domains);

        return is_array($domains) ? array_values(array_unique(array_map('strval', $domains))) : [];
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
    public static function parseDomains(mixed $raw): array
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
}
