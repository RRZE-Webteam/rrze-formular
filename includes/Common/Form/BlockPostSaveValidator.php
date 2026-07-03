<?php

namespace RRZE\Formular\Common\Form;

use WP_Error;
use WP_REST_Request;

defined('ABSPATH') || exit;

class BlockPostSaveValidator
{
    private const NOTICE_TRANSIENT = 'rrze_formular_recipient_save_notice';

    /**
     * @var array<string, mixed>|null
     */
    private static ?array $editorConfigCache = null;

    /**
     * @var list<string>
     */
    private const BLOCK_NAMES = [
        'rrze-formular/formular',
    ];

    /**
     * @var list<string>
     */
    private const PUBLISH_STATUSES = [
        'publish',
        'future',
    ];

    public static function register(): void
    {
        add_filter('wp_insert_post_data', [self::class, 'filterPostData'], 99, 2);
        add_action('init', [self::class, 'registerRestFilters'], 20);
        add_filter('block_editor_settings_all', [self::class, 'filterBlockEditorSettings'], 10, 2);
        add_action('enqueue_block_editor_assets', [self::class, 'enqueueEditorAssets'], 100);
        add_action('admin_notices', [self::class, 'renderAdminNotice']);
    }

    public static function registerRestFilters(): void
    {
        foreach (self::getValidatedPostTypes() as $postType) {
            add_filter("rest_pre_insert_{$postType}", [self::class, 'filterRestPost'], 99, 2);
            add_action("rest_after_insert_{$postType}", [self::class, 'enforceDraftAfterRestSave'], 20, 3);
        }
    }

    /**
     * @return list<string>
     */
    private static function getValidatedPostTypes(): array
    {
        $types = apply_filters('rrze_formular_validated_post_types', ['post', 'page']);

        return array_values(array_unique(array_filter(array_map(
            static fn ($type): string => sanitize_key((string) $type),
            is_array($types) ? $types : []
        ))));
    }

    /**
     * @param array<string, mixed> $settings
     * @param mixed              $context
     * @return array<string, mixed>
     */
    public static function filterBlockEditorSettings(array $settings, $context): array
    {
        unset($context);

        $settings['rrzeFormularEditor'] = self::editorConfig();

        return $settings;
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

        $status = (string) ($data['post_status'] ?? '');
        if (!self::isPublishStatus($status)) {
            return $data;
        }

        $content = self::resolveContent($data, $postarr);
        $message = self::getPublishBlockMessage($content);

        if ($message === null) {
            return $data;
        }

        $data['post_status'] = 'draft';
        self::storeNoticeMessage($message);

        return $data;
    }

    /**
     * @param object $preparedPost
     * @return object|WP_Error
     */
    public static function filterRestPost($preparedPost, WP_REST_Request $request)
    {
        if (!is_object($preparedPost)) {
            return $preparedPost;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $preparedPost;
        }

        $status = self::resolveTargetStatus($request, $preparedPost);
        if (!self::isPublishStatus($status)) {
            return $preparedPost;
        }

        $content = self::resolveRestContent($request, $preparedPost);
        $message = self::getPublishBlockMessage($content);

        if ($message === null) {
            return $preparedPost;
        }

        return new WP_Error(
            'rrze_formular_publish_blocked',
            $message,
            ['status' => 400]
        );
    }

    /**
     * @param \WP_Post        $post
     * @param WP_REST_Request $request
     */
    public static function enforceDraftAfterRestSave($post, WP_REST_Request $request, bool $creating): void
    {
        unset($creating);

        if (!($post instanceof \WP_Post)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!self::isPublishStatus(self::resolveTargetStatus($request, $post))) {
            return;
        }

        if ($post->post_status !== 'publish') {
            return;
        }

        $message = self::getPublishBlockMessage((string) $post->post_content);
        if ($message === null) {
            return;
        }

        wp_update_post([
            'ID' => $post->ID,
            'post_status' => 'draft',
        ], true);

        self::storeNoticeMessage($message);
    }

    public static function enqueueEditorAssets(): void
    {
        if (!function_exists('generate_block_asset_handle')) {
            return;
        }

        $config = self::editorConfig();
        $handles = array_unique([
            generate_block_asset_handle('rrze-formular/formular', 'editorScript'),
            'rrze-formular-formular-editor-script',
        ]);

        foreach ($handles as $handle) {
            if (!wp_script_is($handle, 'registered')) {
                continue;
            }

            wp_localize_script($handle, 'RRZEFormularEditor', $config);
        }
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
     * @return array<string, mixed>
     */
    private static function editorConfig(): array
    {
        if (self::$editorConfigCache !== null) {
            return self::$editorConfigCache;
        }

        $userId = get_current_user_id();
        $notice = $userId > 0 ? get_transient(self::noticeKey($userId)) : false;

        if (is_string($notice) && $notice !== '') {
            delete_transient(self::noticeKey($userId));
        }

        $allowedDomains = AllowedDomains::getAllowedDomains();

        $options = Mailer::getOptions();

        return self::$editorConfigCache = [
            'allowedDomains' => $allowedDomains,
            'domainsConfigured' => $allowedDomains !== [],
            'confirmationDomains' => AllowedDomains::getConfirmationDomains(),
            'confirmationDomainsConfigured' => AllowedDomains::hasConfirmationDomainsConfigured(),
            'defaultRecipientEmail' => sanitize_email((string) ($options['default_recipient_email'] ?? '')),
            'administratorEmail' => Mailer::getAdministratorEmail(),
            'privacyPublished' => Privacy::isPublished(),
            'privacyUrl' => Privacy::getPageUrl(),
            'privacyLabel' => __('Privacy', 'rrze-formular'),
            'saveNotice' => is_string($notice) ? $notice : '',
            'i18n' => [
                'recipientInvalidEmail' => __('Please enter a valid e-mail address.', 'rrze-formular'),
                'recipientDomainNotAllowed' => __('The recipient e-mail domain is not allowed.', 'rrze-formular'),
                'recipientDomainsRequired' => __('A recipient e-mail requires configured allowed domains.', 'rrze-formular'),
                'publishBlocked' => __('Publishing is blocked until all form recipient addresses use an allowed domain.', 'rrze-formular'),
                'confirmationDomainsRequired' => __('Confirmation mails require configured allowed domains. Configure them in the plugin settings before enabling this option.', 'rrze-formular'),
                'privacyPublishBlockedFormat' => __(
                    'This page cannot be published because no published %1$s page exists at %2$s.',
                    'rrze-formular'
                ),
            ],
        ];
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

    private static function isPublishStatus(string $status): bool
    {
        return in_array($status, self::PUBLISH_STATUSES, true);
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

    private static function resolveRestContent(WP_REST_Request $request, object $preparedPost): string
    {
        $content = $request->get_param('content');
        if (is_array($content) && isset($content['raw']) && is_string($content['raw'])) {
            return $content['raw'];
        }

        if (is_string($content) && $content !== '') {
            return $content;
        }

        return isset($preparedPost->post_content) ? (string) $preparedPost->post_content : '';
    }

    private static function resolveTargetStatus(WP_REST_Request $request, object $preparedPost): string
    {
        $status = $request->get_param('status');
        if (is_string($status) && $status !== '') {
            return $status;
        }

        if ($preparedPost instanceof \WP_Post) {
            return (string) $preparedPost->post_status;
        }

        return isset($preparedPost->post_status) ? (string) $preparedPost->post_status : 'draft';
    }

    private static function getPublishBlockMessage(string $content): ?string
    {
        if (!self::contentHasFormBlocks($content)) {
            return null;
        }

        if (!Privacy::isPublished()) {
            return Privacy::getPublishBlockedMessage();
        }

        $invalidEmails = self::findInvalidRecipientEmails($content);
        if ($invalidEmails === []) {
            return null;
        }

        return self::buildRecipientNoticeMessage($invalidEmails);
    }

    public static function contentHasFormBlocks(string $content): bool
    {
        if ($content === '') {
            return false;
        }

        if (has_blocks($content)) {
            return self::blocksContainForm(parse_blocks($content));
        }

        return (bool) preg_match('/wp:rrze-formular\/formular/', $content);
    }

    /**
     * @param list<array<string, mixed>> $blocks
     */
    private static function blocksContainForm(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $name = (string) ($block['blockName'] ?? '');
            if (in_array($name, self::BLOCK_NAMES, true)) {
                return true;
            }

            $innerBlocks = $block['innerBlocks'] ?? [];
            if (is_array($innerBlocks) && $innerBlocks !== [] && self::blocksContainForm($innerBlocks)) {
                return true;
            }
        }

        return false;
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
     * @param list<string>               $invalid
     */
    private static function walkBlocks(array $blocks, array &$invalid): void
    {
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $name = (string) ($block['blockName'] ?? '');
            if (in_array($name, self::BLOCK_NAMES, true)) {
                $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
                $email = self::resolveBlockRecipientEmail($attrs);
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

    private static function storeNoticeMessage(string $message): void
    {
        $userId = get_current_user_id();
        if ($userId <= 0 || $message === '') {
            return;
        }

        set_transient(self::noticeKey($userId), $message, MINUTE_IN_SECONDS);
    }

    /**
     * @param list<string> $invalidEmails
     */
    private static function buildRecipientNoticeMessage(array $invalidEmails): string
    {
        $domains = AllowedDomains::getAllowedDomains();
        $domainList = $domains !== [] ? implode(', ', $domains) : __('none configured', 'rrze-formular');

        if (count($invalidEmails) === 1) {
            return sprintf(
                /* translators: 1: e-mail address, 2: comma-separated allowed domains */
                __('The page was saved as a draft because the form recipient address %1$s is not allowed. Allowed domains: %2$s.', 'rrze-formular'),
                $invalidEmails[0],
                $domainList
            );
        }

        return sprintf(
            /* translators: 1: comma-separated e-mail addresses, 2: comma-separated allowed domains */
            __('The page was saved as a draft because the form recipient addresses %1$s are not allowed. Allowed domains: %2$s.', 'rrze-formular'),
            implode(', ', $invalidEmails),
            $domainList
        );
    }

    private static function noticeKey(int $userId): string
    {
        return self::NOTICE_TRANSIENT . '_' . $userId;
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private static function resolveBlockRecipientEmail(array $attrs): string
    {
        $email = trim((string) ($attrs['recipientEmail'] ?? ''));
        if ($email !== '') {
            return $email;
        }

        return Mailer::resolveRecipient($attrs)['email'];
    }
}
