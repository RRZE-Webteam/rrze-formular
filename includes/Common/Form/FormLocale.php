<?php

namespace RRZE\Formular\Common\Form;

use function RRZE\Formular\plugin;

defined('ABSPATH') || exit;

class FormLocale
{
    public static function withSiteLocale(callable $callback, array $payload = [])
    {
        $locale = self::resolveLocale($payload);
        $switched = false;

        if (function_exists('switch_to_locale')) {
            switch_to_locale($locale);
            $switched = true;
        }

        self::reloadTextdomain($locale);

        try {
            return $callback();
        } finally {
            if ($switched && function_exists('restore_previous_locale')) {
                restore_previous_locale();
                self::reloadTextdomain(self::getSiteLocale());
            }
        }
    }

    public static function resolveLocale(array $payload = []): string
    {
        $requested = sanitize_text_field((string) ($payload['locale'] ?? ''));

        if ($requested !== '') {
            $mapped = self::mapLanguageTagToLocale($requested);
            if ($mapped !== '') {
                return $mapped;
            }
        }

        return self::getSiteLocale();
    }

    public static function getSiteLocale(): string
    {
        $locale = get_option('WPLANG', '');
        if (is_string($locale) && $locale !== '') {
            return $locale;
        }

        $locale = get_locale();
        if ($locale !== '') {
            return $locale;
        }

        $language = get_bloginfo('language');
        if ($language !== '') {
            $mapped = self::mapLanguageTagToLocale($language);
            if ($mapped !== '') {
                return $mapped;
            }
        }

        return 'en_US';
    }

    private static function mapLanguageTagToLocale(string $tag): string
    {
        $tag = str_replace('-', '_', trim($tag));
        $candidates = [];

        if (preg_match('/^([a-z]{2})_([a-z]{2})$/i', $tag, $matches)) {
            $candidates[] = strtolower($matches[1]) . '_' . strtoupper($matches[2]);
        }

        if (preg_match('/^[a-z]{2}$/i', $tag)) {
            $candidates[] = strtolower($tag) . '_' . strtoupper($tag);
        }

        $candidates[] = strtolower($tag);

        $languagesPath = plugin()->getPath() . 'languages';

        foreach (array_unique($candidates) as $candidate) {
            if (self::hasTranslationFile($languagesPath, $candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private static function hasTranslationFile(string $languagesPath, string $locale): bool
    {
        if (is_readable($languagesPath . 'rrze-formular-' . $locale . '.mo')) {
            return true;
        }

        $pattern = $languagesPath . 'rrze-formular-' . $locale . '*.json';

        return (glob($pattern) ?: []) !== [];
    }

    private static function reloadTextdomain(string $locale): void
    {
        unload_textdomain('rrze-formular');

        $mofile = plugin()->getPath() . 'languages/rrze-formular-' . $locale . '.mo';
        if (is_readable($mofile)) {
            load_textdomain('rrze-formular', $mofile);

            return;
        }

        load_plugin_textdomain(
            'rrze-formular',
            false,
            dirname(plugin_basename(plugin()->getFile())) . '/languages'
        );
    }
}
