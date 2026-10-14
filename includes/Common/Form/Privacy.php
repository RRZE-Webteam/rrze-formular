<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

/**
 * Builds the privacy-policy link displayed below each form.
 *
 * The link follows the site language; no page-existence check is performed.
 */
class Privacy
{
    /**
     * Section anchor appended to the privacy-policy link.
     *
     * @var string
     */
    private const CONTACT_FRAGMENT = 'contact';

    /**
     * Build the privacy-page URL with the site permalink trailing-slash convention.
     *
     * @return string Absolute privacy-page URL without a fragment.
     */
    public static function getPageUrl(): string
    {
        return user_trailingslashit(home_url('/' . self::slug()));
    }

    /**
     * Render the translated privacy link pointing to the contact section.
     *
     * @return string Escaped paragraph and anchor markup.
     */
    public static function renderLink(): string
    {
        return sprintf(
            '<p class="rrze-formular__privacy"><a href="%1$s#%2$s">%3$s</a></p>',
            esc_url(self::getPageUrl()),
            esc_attr(self::CONTACT_FRAGMENT),
            esc_html(__('Privacy', 'rrze-formular'))
        );
    }

    /**
     * Choose the privacy-page slug from the website language.
     *
     * @return string datenschutz for German sites, otherwise privacy.
     */
    private static function slug(): string
    {
        $language = strtolower(str_replace('_', '-', (string) get_bloginfo('language')));
        if ($language === '') {
            $language = strtolower(str_replace('_', '-', get_locale()));
        }

        return str_starts_with($language, 'de') ? 'datenschutz' : 'privacy';
    }
}
