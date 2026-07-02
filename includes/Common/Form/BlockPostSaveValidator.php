<?php

namespace RRZE\Formular\Common\Form;

use WP_REST_Request;

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
        add_filter('wp_insert_post_data', [self::class, 'filterPostData'], 99, 2);
        add_action('init', [self::class, 'registerRestFilters'], 20);
        add_action('enqueue_block_editor_assets', [self::class, 'enqueueEditorAssets']);
        add_action('admin_notices', [self::class, 'renderAdminNotice']);
    }

    public static function registerRestFilters(): void
    {
        foreach (array_keys(get_post_types(['show_in_rest' => true])) as $postType) {
            add_filter("rest_pre_insert_{$postType}", [self::class, 'filterRestPost'], 99, 2);
            add_action("rest_after_insert_{$postType}", [self::class, 'enforceDraftAfterRestSave'], 20, 3);
        }
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

        $content = self::resolveContent($data, $postarr);
        return self::applyDraftIfInvalid($data, $content);
    }

    /**
     * @param object $preparedPost
     */
    public static function filterRestPost($preparedPost, WP_REST_Request $request)
    {
        if (!is_object($preparedPost)) {
            return $preparedPost;
        }

        $content = $request->get_param('content');
        if (!is_string($content) || $content === '') {
            $content = isset($preparedPost->post_content) ? (string) $preparedPost->post_content : '';
        }

        $invalidEmails = self::findInvalidRecipientEmails($content);
        if ($invalidEmails === []) {
            return $preparedPost;
        }

        $preparedPost->post_status = 'draft';
        self::storeNotice($invalidEmails);

        return $preparedPost;
    }

    /**
     * @param \WP_Post        $post
     * @param WP_REST_Request $request
     */
    public static function enforceDraftAfterRestSave($post, WP_REST_Request $request, bool $creating): void
    {
        if (!($post instanceof \WP_Post)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $invalidEmails = self::findInvalidRecipientEmails((string) $post->post_content);
        if ($invalidEmails === [] || $post->post_status !== 'publish') {
            return;
        }

        wp_update_post([
            'ID' => $post->ID,
            'post_status' => 'draft',
        ], true);

        self::storeNotice($invalidEmails);
    }

    public static function enqueueEditorAssets(): void
    {
        if (!function_exists('generate_block_asset_handle')) {
            return;
        }

        $handle = generate_block_asset_handle('rrze-formular/formular', 'editorScript');
        if (!wp_script_is($handle, 'registered')) {
            return;
        }

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
                'publishBlocked' => __('Publishing is blocked until all form recipient addresses use an allowed domain.', 'rrze-formular'),
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
     * @param array<string, mixed> $data
     * @param array<string, mixed> $postarr
     */
    private static function resolveContent(array $data, array $postarr): string
    {
        $content = (string) ($data['post_content'] ?? '');
        if ($content !== '') {
            return $content;
        }

        return (string) ($postarr['post_content'] ?? '');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function applyDraftIfInvalid(array $data, string $content): array
    {
        $invalidEmails = self::findInvalidRecipientEmails($content);
        if ($invalidEmails === []) {
            return $data;
        }

        $data['post_status'] = 'draft';
        self::storeNotice($invalidEmails);

        return $data;
    }

    /**
     * @return list<string>
     */
    private static function findInvalidRecipientEmails(string $content): array
    {
        if ($content === '') {
            return [];
        }

        $invalid = [];

        if (has_blocks($content)) {
            self::walkBlocks(parse_blocks($content), $invalid);
        }

        if (preg_match_all('/"recipientEmail"\s*:\s*"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/', $content, $matches)) {
            foreach ($matches[1] as $rawEmail) {
                $email = trim(stripslashes((string) $rawEmail));
                if ($email !== '' && !AllowedDomains::isBlockRecipientAllowed($email)) {
                    $invalid[] = $email;
                }
            }
        }

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
