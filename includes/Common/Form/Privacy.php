<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

class Privacy
{
    private const CONTACT_FRAGMENT = 'contact';

    public static function isPublished(): bool
    {
        return self::publishedPage() !== null;
    }

    public static function getPageUrl(): string
    {
        $page = self::publishedPage();
        if ($page instanceof \WP_Post) {
            $url = get_permalink($page);

            if (is_string($url) && $url !== '') {
                return $url;
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
        return str_starts_with(FormLocale::getSiteLocale(), 'de') ? 'datenschutz' : 'privacy';
    }

    private static function publishedPage(): ?\WP_Post
    {
        $page = get_page_by_path(self::slug(), OBJECT, 'page');

        return $page instanceof \WP_Post && $page->post_status === 'publish' ? $page : null;
    }
}
