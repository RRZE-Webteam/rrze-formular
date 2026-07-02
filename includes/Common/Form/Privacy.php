<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

class Privacy
{
    private const SLUG_DE = 'datenschutz';
    private const SLUG_EN = 'privacy';
    private const CONTACT_FRAGMENT = 'contact';

    public static function getPreferredSlug(): string
    {
        $language = strtolower((string) get_bloginfo('language'));
        if ($language !== '') {
            if (str_starts_with($language, 'de')) {
                return self::SLUG_DE;
            }

            if (str_starts_with($language, 'en')) {
                return self::SLUG_EN;
            }
        }

        $locale = FormLocale::getSiteLocale();

        return str_starts_with($locale, 'de') ? self::SLUG_DE : self::SLUG_EN;
    }

    public static function getSlug(): string
    {
        return self::getPreferredSlug();
    }

    public static function isGermanSite(): bool
    {
        return self::getPreferredSlug() === self::SLUG_DE;
    }

    public static function getUrl(): string
    {
        $page = self::getPage();
        if ($page instanceof \WP_Post) {
            $url = get_permalink($page);

            if (is_string($url) && $url !== '') {
                return self::appendContactFragment($url);
            }
        }

        return self::appendContactFragment(
            user_trailingslashit(home_url('/' . self::getPreferredSlug()))
        );
    }

    public static function getLabel(): string
    {
        return self::isGermanSite()
            ? __('Datenschutz', 'rrze-formular')
            : __('Privacy', 'rrze-formular');
    }

    public static function getPage(): ?\WP_Post
    {
        $page = get_page_by_path(self::getPreferredSlug(), OBJECT, 'page');

        return $page instanceof \WP_Post ? $page : null;
    }

    public static function getPublishedPage(): ?\WP_Post
    {
        $page = self::getPage();

        if ($page instanceof \WP_Post && $page->post_status === 'publish') {
            return $page;
        }

        return null;
    }

    public static function isPublished(): bool
    {
        return self::getPublishedPage() !== null;
    }

    public static function getPublishBlockedMessage(): string
    {
        return sprintf(
            /* translators: 1: page title (Datenschutz/Privacy), 2: expected URL with #contact fragment */
            __(
                'This page cannot be published because no published %1$s page exists at %2$s.',
                'rrze-formular'
            ),
            self::getLabel(),
            self::getUrl()
        );
    }

    public static function renderLink(): string
    {
        return sprintf(
            '<p class="rrze-formular__privacy"><a href="%1$s">%2$s</a></p>',
            esc_url(self::getUrl()),
            esc_html(self::getLabel())
        );
    }

    private static function appendContactFragment(string $url): string
    {
        $url = preg_replace('/#.*$/', '', $url) ?? $url;

        return $url . '#' . self::CONTACT_FRAGMENT;
    }
}
