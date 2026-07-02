<?php

namespace RRZE\Formular\Common\Form;

defined('ABSPATH') || exit;

class BlockPostSaveValidator
{
    private const NOTICE_TRANSIENT = 'rrze_formular_recipient_save_notice';

    /**
     * @var list<string>
     */
    private const BLOCK_NAMES = [
        'rrze-formular/formular',
        'rrze-formular/form-wizard',
    ];

    public static function register(): void
    {
        add_filter('wp_insert_post_data', [self::class, 'filterPostData'], 20, 2);
        add_action('enqueue_block_editor_assets', [self::class, 'enqueueEditorAssets']);
        add_action('admin_notices', [self::class, 'renderAdminNotice']);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $postarr
     * @return array<string, mixed>
     */
    public static function filterPostData(array $data, array $postarr): array
    {
        if (!self::shouldValidate($data, $postarr)) {
            return $data;
        }

        $content = (string) ($data['post_content'] ?? '');
        $invalidEmails = self::findInvalidRecipientEmails($content);
        if ($invalidEmails === []) {
            return $data;
        }

        $data['post_status'] = 'draft';
        self::storeNotice($invalidEmails);

        return $data;
    }

    public static function enqueueEditorAssets(): void
    {
        if (!function_exists('generate_block_asset_handle')) {
            return;
        }

        $handle = generate_block_asset_handle('rrze-formular/formular', 'editorScript');
        $userId = get_current_user_id();
        $notice = $userId > 0 ? get_transient(self::noticeKey($userId)) : false;

        if (is_string($notice) && $notice !== '') {
            delete_transient(self::noticeKey($userId));
        }

        wp_localize_script($handle, 'RRZEFormularEditor', [
            'allowedDomains' => AllowedDomains::getAllowedDomains(),
            'domainsConfigured' => AllowedDomains::hasConfiguredDomains(),
            'saveNotice' => is_string($notice) ? $notice : '',
            'i18n' => [
                'recipientInvalidEmail' => __('Please enter a valid e-mail address.', 'rrze-formular'),
                'recipientDomainNotAllowed' => __('The recipient e-mail domain is not allowed.', 'rrze-formular'),
                'recipientDomainsRequired' => __('A recipient e-mail requires configured allowed domains.', 'rrze-formular'),
            ],
        ]);
    }

    public static function renderAdminNotice(): void
    {
        if (!is_admin() || !function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();
        if ($screen === null || $screen->base !== 'post') {
            return;
        }

        $userId = get_current_user_id();
        if ($userId <= 0) {
            return;
        }

        $notice = get_transient(self::noticeKey($userId));
        if (!is_string($notice) || $notice === '') {
            return;
        }

        delete_transient(self::noticeKey($userId));

        printf(
            '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
            esc_html($notice)
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $postarr
     */
    private static function shouldValidate(array $data, array $postarr): bool
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }

        $postType = (string) ($data['post_type'] ?? $postarr['post_type'] ?? 'post');
        if (!is_post_type_viewable($postType)) {
            return false;
        }

        $status = (string) ($data['post_status'] ?? '');
        if (in_array($status, ['inherit', 'revision', 'auto-draft'], true)) {
            return false;
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private static function findInvalidRecipientEmails(string $content): array
    {
        if ($content === '' || !has_blocks($content)) {
            return [];
        }

        $invalid = [];
        self::walkBlocks(parse_blocks($content), $invalid);

        return array_values(array_unique($invalid));
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @param list<string> $invalid
     */
    private static function walkBlocks(array $blocks, array &$invalid): void
    {
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $name = (string) ($block['blockName'] ?? '');
            if (in_array($name, self::BLOCK_NAMES, true)) {
                $email = trim((string) ($block['attrs']['recipientEmail'] ?? ''));
                if ($email !== '' && !AllowedDomains::isBlockRecipientAllowed($email)) {
                    $invalid[] = $email;
                }
            }

            $innerBlocks = $block['innerBlocks'] ?? [];
            if (is_array($innerBlocks) && $innerBlocks !== []) {
                self::walkBlocks($innerBlocks, $invalid);
            }
        }
    }

    /**
     * @param list<string> $invalidEmails
     */
    private static function storeNotice(array $invalidEmails): void
    {
        $userId = get_current_user_id();
        if ($userId <= 0) {
            return;
        }

        $domains = AllowedDomains::getAllowedDomains();
        $domainList = $domains !== [] ? implode(', ', $domains) : __('none configured', 'rrze-formular');

        if (count($invalidEmails) === 1) {
            $message = sprintf(
                /* translators: 1: e-mail address, 2: comma-separated allowed domains */
                __('The page was saved as a draft because the form recipient address %1$s is not allowed. Allowed domains: %2$s.', 'rrze-formular'),
                $invalidEmails[0],
                $domainList
            );
        } else {
            $message = sprintf(
                /* translators: 1: comma-separated e-mail addresses, 2: comma-separated allowed domains */
                __('The page was saved as a draft because the form recipient addresses %1$s are not allowed. Allowed domains: %2$s.', 'rrze-formular'),
                implode(', ', $invalidEmails),
                $domainList
            );
        }

        set_transient(self::noticeKey($userId), $message, MINUTE_IN_SECONDS);
    }

    private static function noticeKey(int $userId): string
    {
        return self::NOTICE_TRANSIENT . '_' . $userId;
    }
}
