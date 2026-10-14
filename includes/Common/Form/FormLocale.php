<?php

namespace RRZE\Formular\Common\Form;

use function RRZE\Formular\plugin;

defined('ABSPATH') || exit;

/**
 * Chooses the language for form processing and reloads plugin translations as needed.
 */
class FormLocale
{
    /**
     * Run a callback in the resolved form locale and clean up the locale switch afterwards.
     *
     * Cleanup runs in a finally block, including when the callback throws.
     *
     * @param callable(): mixed $callback Work to perform while form translations are loaded.
     * @param array<string, mixed> $payload Optional formLocale or legacy locale hint.
     * @return mixed The callback result.
     * @throws \Throwable Any exception raised by the callback is passed to the caller.
     */
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

    /**
     * Resolve a supported locale from the payload, falling back to the site locale.
     *
     * @param array<string, mixed> $payload Optional formLocale or legacy locale hint.
     * @return string WordPress locale identifier.
     */
    public static function resolveLocale(array $payload = []): string
    {
        $requested = sanitize_text_field((string) ($payload['formLocale'] ?? $payload['locale'] ?? ''));

        if ($requested !== '') {
            $requested = self::normalizeLanguageTag($requested);
            $mapped = self::mapLanguageTagToLocale($requested);
            if ($mapped !== '') {
                return $mapped;
            }
        }

        return self::getSiteLocale();
    }

    /**
     * Extract the first language tag and strip Unicode locale extensions.
     *
     * @param string $tag Language tag, optionally followed by commas, semicolons or Unicode extensions.
     * @return string Trimmed base language tag.
     */
    private static function normalizeLanguageTag(string $tag): string
    {
        $tag = trim($tag);
        if ($tag === '') {
            return '';
        }

        $parts = preg_split('/[,;]/', $tag);
        $tag = trim((string) ($parts[0] ?? $tag));

        // Drop Unicode extensions such as -u-ca-gregorian.
        $tag = preg_replace('/-u-.*/i', '', $tag) ?? $tag;

        return trim($tag);
    }

    /**
     * Read the site language from WPLANG, WordPress locale or the language tag.
     *
     * @return string Site locale, with en_US as the final fallback.
     */
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

    /**
     * Find a locale candidate for which the plugin has a translation file.
     *
     * @param string $tag Language tag such as de or de-DE.
     * @return string Matching locale identifier, or an empty string if none is found.
     */
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

    /**
     * Check for a compiled catalog or script translations for a locale.
     *
     * @param string $languagesPath Path prefix for translation files, including the directory separator.
     * @param string $locale Locale identifier used in the filenames.
     * @return bool Whether a matching MO or JSON file exists.
     */
    private static function hasTranslationFile(string $languagesPath, string $locale): bool
    {
        if (is_readable($languagesPath . 'rrze-formular-' . $locale . '.mo')) {
            return true;
        }

        $pattern = $languagesPath . 'rrze-formular-' . $locale . '*.json';

        return (glob($pattern) ?: []) !== [];
    }

    /**
     * Reload plugin translations for the requested locale.
     *
     * A readable bundled MO file is preferred; otherwise WordPress handles the
     * plugin text-domain lookup.
     *
     * @param string $locale Locale identifier used to select the compiled catalog.
     * @return void
     */
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
