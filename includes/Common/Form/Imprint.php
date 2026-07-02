<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

class Imprint
{
    private const SLUG_DE = 'impressum';
    private const SLUG_EN = 'imprint';

    public static function getSlug(): string
    {
        $locale = FormLocale::getSiteLocale();

        return str_starts_with($locale, 'de') ? self::SLUG_DE : self::SLUG_EN;
    }

    public static function getUrl(): string
    {
        return user_trailingslashit(home_url('/' . self::getSlug()));
    }

    public static function getLabel(): string
    {
        return self::getSlug() === self::SLUG_DE
            ? __('Impressum', 'rrze-formular')
            : __('Imprint', 'rrze-formular');
    }

    public static function getPage(): ?\WP_Post
    {
        $page = get_page_by_path(self::getSlug(), OBJECT, 'page');

        return $page instanceof \WP_Post ? $page : null;
    }

    public static function isPublished(): bool
    {
        $page = self::getPage();

        return $page !== null && $page->post_status === 'publish';
    }

    public static function getPublishBlockedMessage(): string
    {
        return sprintf(
            /* translators: 1: page title (Impressum/Imprint), 2: expected URL path */
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
            '<p class="rrze-formular__imprint"><a href="%1$s">%2$s</a></p>',
            esc_url(self::getUrl()),
            esc_html(self::getLabel())
        );
    }
}
