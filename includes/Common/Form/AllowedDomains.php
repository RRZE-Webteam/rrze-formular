<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

class AllowedDomains
{
    private const CMS_OPTION = 'rrze_formular_allowedDomains';

    public static function isRrzeCmsActive(): bool
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $pluginFiles = [
            'rrze-settings/rrze-settings.php',
        ];

        foreach ($pluginFiles as $pluginFile) {
            if (is_plugin_active($pluginFile)) {
                return true;
            }
        }

        return (bool) apply_filters('rrze_formular_rrze_cms_active', false);
    }

    /**
     * @return list<string>
     */
    public static function getAllowedDomains(): array
    {
        if (self::isRrzeCmsActive()) {
            $raw = get_option(self::CMS_OPTION, '');
        } else {
            $options = get_option('rrze-formular', []);
            $raw = is_array($options) ? (string) ($options['allowed_domains'] ?? '') : '';
        }

        return self::parseDomains((string) $raw);
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

    /**
     * @return list<string>
     */
    private static function parseDomains(string $raw): array
    {
        $domains = [];

        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $line = strtolower(trim((string) $line));
            $line = trim($line, '@.');
            if ($line !== '') {
                $domains[] = $line;
            }
        }

        return array_values(array_unique($domains));
    }

    private static function emailDomain(string $email): string
    {
        $at = strrchr($email, '@');

        return $at === false ? '' : strtolower(substr($at, 1));
    }
}
