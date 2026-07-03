<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

class Privacy
{
    private const CONTACT_FRAGMENT = 'contact';

    private static ?bool $publishedCache = null;

    public static function isPublished(): bool
    {
        if (self::$publishedCache !== null) {
            return self::$publishedCache;
        }

        $url = self::getPageUrl();

        if (self::isRrzeLegalPrivacyAvailable()) {
            $reachable = true;
        } elseif (self::isPublishedPage(self::slug())) {
            $reachable = true;
        } else {
            $reachable = self::isUrlReachable($url);
        }

        /**
         * @param bool $reachable Whether the privacy page URL returns a success response.
         * @param string $url Checked URL without fragment.
         */
        return self::$publishedCache = (bool) apply_filters('rrze_formular_privacy_page_reachable', $reachable, $url);
    }

    public static function getPageUrl(): string
    {
        if (class_exists(\RRZE\Legal\TOS\Endpoint::class)) {
            $url = \RRZE\Legal\TOS\Endpoint::endpointUrl('privacy');
            if (is_string($url) && $url !== '') {
                return user_trailingslashit($url);
            }
        }

        return user_trailingslashit(home_url('/' . self::slug()));
    }

    public static function getPublishBlockedMessage(): string
    {
        return sprintf(
            /* translators: 1: page title, 2: expected page URL without fragment */
            __(
                'This page cannot be published because no published %1$s page exists at %2$s.',
                'rrze-formular'
            ),
            __('Privacy', 'rrze-formular'),
            self::getPageUrl()
        );
    }

    public static function renderLink(): string
    {
        return sprintf(
            '<p class="rrze-formular__privacy"><a href="%1$s#%2$s">%3$s</a></p>',
            esc_url(self::getPageUrl()),
            esc_attr(self::CONTACT_FRAGMENT),
            esc_html(__('Privacy', 'rrze-formular'))
        );
    }

    private static function slug(): string
    {
        $language = strtolower((string) get_bloginfo('language'));
        if ($language === '') {
            $language = strtolower(get_locale());
        }

        return str_starts_with($language, 'de') ? 'datenschutz' : 'privacy';
    }

    private static function isRrzeLegalPrivacyAvailable(): bool
    {
        if (!class_exists(\RRZE\Legal\TOS\Endpoint::class) || !function_exists('RRZE\Legal\plugin')) {
            return false;
        }

        if (function_exists('RRZE\Legal\tos')) {
            $tos = \RRZE\Legal\tos();
            if (
                is_object($tos)
                && method_exists($tos, 'overwriteEndpoints')
                && $tos->overwriteEndpoints()
            ) {
                $slugs = \RRZE\Legal\TOS\Endpoint::getSlugs();
                $pagePath = (string) ($slugs['privacy'] ?? self::slug());

                return self::isPublishedPage($pagePath);
            }
        }

        $langCode = class_exists(\RRZE\Legal\Locale::class)
            ? \RRZE\Legal\Locale::getLangCode()
            : 'en';
        $basePath = \RRZE\Legal\plugin()->getPath() . 'templates/tos/';
        $template = $basePath . 'privacy-' . $langCode . '.html';

        if (!is_readable($template)) {
            $template = $basePath . 'privacy-en.html';
        }

        return is_readable($template);
    }

    private static function isPublishedPage(string $slug): bool
    {
        $page = get_page_by_path($slug, OBJECT, 'page');

        return $page instanceof \WP_Post && $page->post_status === 'publish';
    }

    private static function isUrlReachable(string $url): bool
    {
        if (!self::isSameSiteUrl($url)) {
            return false;
        }

        $requestArgs = [
            'timeout' => 5,
            'redirection' => 3,
            'sslverify' => apply_filters('https_local_ssl_verify', false),
        ];

        $response = wp_remote_head($url, $requestArgs);

        if (!is_wp_error($response)) {
            $code = (int) wp_remote_retrieve_response_code($response);
            if ($code >= 200 && $code < 300) {
                return true;
            }

            if (!in_array($code, [405, 501], true)) {
                return false;
            }
        }

        $response = wp_remote_get($url, $requestArgs);

        if (is_wp_error($response)) {
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        return $code >= 200 && $code < 300;
    }

    private static function isSameSiteUrl(string $url): bool
    {
        $parsed = wp_parse_url($url);
        $site = wp_parse_url(home_url('/'));

        if (!is_array($parsed) || !is_array($site)) {
            return false;
        }

        $urlHost = strtolower((string) ($parsed['host'] ?? ''));
        $siteHost = strtolower((string) ($site['host'] ?? ''));

        return $urlHost !== '' && $urlHost === $siteHost;
    }
}
