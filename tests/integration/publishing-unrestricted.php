<?php

declare(strict_types=1);

/**
 * Run in a local WordPress installation:
 * wp --skip-plugins --skip-themes eval-file wp-content/plugins/rrze-formular/tests/integration/publishing-unrestricted.php
 * Creates temporary posts/pages and deletes them in finally.
 */

if (!defined('WP_CLI') || !WP_CLI) {
    exit("Run this integration test with WP-CLI.\n");
}

if (!function_exists('RRZE\Formular\main')) {
    require dirname(__DIR__, 2) . '/rrze-formular.php';
    RRZE\Formular\loaded();
    RRZE\Formular\main()->onInit();
    RRZE\Formular\register_blocks();
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$originalUserId = get_current_user_id();
$administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
$assert($administrators !== [], 'A local administrator is required for REST publishing.');
wp_set_current_user((int) $administrators[0]);

$postIds = [];
$domains = [];
$domainFilter = static function () use (&$domains): array {
    return $domains;
};
$optionsFilter = static fn (): array => ['default_recipient_email' => 'website@blocked.invalid'];
add_filter('rrze_formular_allowed_domains', $domainFilter);
add_filter('pre_option_rrze-formular', $optionsFilter);

try {
    foreach ([
        'no domains' => [[], 'recipient@blocked.invalid'],
        'disallowed domain' => [['example.org'], 'recipient@blocked.invalid'],
        'allowed block recipient with disallowed default' => [['fau.de'], 'team@fau.de'],
    ] as $scenario => [$domains, $recipientEmail]) {
        // Each scenario must load its own configuration despite per-request caches.
        (new ReflectionProperty(RRZE\Formular\Common\Form\AllowedDomains::class, 'allowedDomains'))->setValue(null, null);
        (new ReflectionProperty(RRZE\Formular\Common\Form\Mailer::class, 'optionsCache'))->setValue(null, null);
        $assert(RRZE\Formular\Common\Form\AllowedDomains::getAllowedDomains() === $domains, 'Unexpected domains: ' . $scenario);

        foreach (['post' => 'posts', 'page' => 'pages'] as $postType => $restBase) {
            foreach (['publish', 'future'] as $status) {
                $attributes = [
                    'recipientEmail' => $recipientEmail,
                    'includeSsoInfo' => true,
                    'fields' => [['id' => 'message', 'type' => 'textarea', 'label' => 'Message']],
                ];
                $content = '<!-- wp:group --><div class="wp-block-group">'
                    . '<!-- wp:rrze-formular/formular ' . wp_json_encode($attributes) . ' /-->'
                    . '</div><!-- /wp:group -->';
                $timestamp = time() + ($status === 'future' ? DAY_IN_SECONDS : -MINUTE_IN_SECONDS);
                $context = $postType . '/' . $status . '/' . $scenario;

                $postId = wp_insert_post([
                    'post_type' => $postType,
                    'post_title' => 'RRZE Formular publishing regression test',
                    'post_status' => $status,
                    'post_date' => wp_date('Y-m-d H:i:s', $timestamp),
                    'post_date_gmt' => gmdate('Y-m-d H:i:s', $timestamp),
                    'post_content' => wp_slash($content),
                    'ping_status' => 'closed',
                ], true);
                $assert(!is_wp_error($postId), 'Direct insert failed: ' . $context);
                $postIds[] = $postId;
                $assert(get_post_status($postId) === $status, 'Direct insert changed status: ' . $context);

                $updated = wp_update_post([
                    'ID' => $postId,
                    'post_content' => wp_slash($content . '<!-- wp:paragraph --><p>Updated</p><!-- /wp:paragraph -->'),
                ], true);
                $assert(!is_wp_error($updated), 'Direct update failed: ' . $context);
                $assert(get_post_status($postId) === $status, 'Direct update changed status: ' . $context);

                $request = new WP_REST_Request('POST', '/wp/v2/' . $restBase);
                $request->set_body_params([
                    'title' => 'RRZE Formular REST publishing regression test',
                    'status' => $status,
                    'date' => wp_date('Y-m-d\TH:i:s', $timestamp),
                    'content' => $content,
                    'ping_status' => 'closed',
                ]);
                $response = rest_do_request($request);
                $data = $response->get_data();
                if (isset($data['id'])) {
                    $postIds[] = (int) $data['id'];
                }
                $assert($response->get_status() === 201, 'REST insert failed: ' . $context . ' ' . wp_json_encode($data));
                $assert(get_post_status($data['id']) === $status, 'REST insert changed status: ' . $context);

                $request = new WP_REST_Request('POST', '/wp/v2/' . $restBase . '/' . $data['id']);
                $request->set_body_params(['content' => $content . '<!-- wp:paragraph --><p>Updated</p><!-- /wp:paragraph -->']);
                $response = rest_do_request($request);
                $assert($response->get_status() === 200, 'REST update failed: ' . $context);
                $assert(get_post_status($data['id']) === $status, 'REST update changed status: ' . $context);
            }
        }
    }
} finally {
    foreach ($postIds as $postId) {
        wp_delete_post($postId, true);
    }
    remove_filter('rrze_formular_allowed_domains', $domainFilter);
    remove_filter('pre_option_rrze-formular', $optionsFilter);
    wp_set_current_user($originalUserId);
}

WP_CLI::success('48 direct/REST insert and update checks passed for published/scheduled posts and pages.');
