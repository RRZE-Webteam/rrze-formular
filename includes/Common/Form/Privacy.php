<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

class Privacy
{
    private const CONTACT_FRAGMENT = 'contact';

    public static function isPublished(): bool
    {
        $url = self::getPageUrl();
        $reachable = self::isUrlReachable($url);

        /**
         * @param bool $reachable Whether the privacy page URL returns a success response.
         * @param string $url Checked URL without fragment.
         */
        return (bool) apply_filters('rrze_formular_privacy_page_reachable', $reachable, $url);
    }

    public static function getPageUrl(): string
    {
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

    private static function isUrlReachable(string $url): bool
    {
        if (!self::isSameSiteUrl($url)) {
            return false;
        }

        $response = wp_remote_head($url, [
            'timeout' => 5,
            'redirection' => 3,
        ]);

        if (!is_wp_error($response)) {
            $code = (int) wp_remote_retrieve_response_code($response);
            if ($code >= 200 && $code < 300) {
                return true;
            }

            if (!in_array($code, [405, 501], true)) {
                return false;
            }
        }

        $response = wp_remote_get($url, [
            'timeout' => 5,
            'redirection' => 3,
        ]);

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
