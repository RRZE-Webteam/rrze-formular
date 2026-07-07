<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

class Privacy
{
    private const CONTACT_FRAGMENT = 'contact';

    public static function getPageUrl(): string
    {
        return user_trailingslashit(home_url('/' . self::slug()));
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
        $language = strtolower(str_replace('_', '-', (string) get_bloginfo('language')));
        if ($language === '') {
            $language = strtolower(str_replace('_', '-', get_locale()));
        }

        return str_starts_with($language, 'de') ? 'datenschutz' : 'privacy';
    }
}
