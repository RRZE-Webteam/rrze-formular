<?php

namespace RRZE\Formular\Common\API;

use RRZE\Formular\Common\Form\FormConfigAuth;
use RRZE\Formular\Common\Form\FormHandler;
use RRZE\Formular\Common\Form\SpamProtection;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit;

/**
 * Public REST endpoints for form submissions and token issuance.
 *
 * Routes are intentionally accessible without login. Protection is enforced
 * server-side via signed form configuration, one-time submission tokens,
 * honeypot, minimum submit delay and rate limiting — not via wp_rest nonce.
 *
 * Submission tokens are issued via REST (not during HTML rendering) so cached
 * pages do not share one-time nonces across visitors.
 */
class FormAPI
{
    /**
     * Register the callback that adds the public REST routes.
     */
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Register POST routes for token issuance and form submission.
     *
     * @return void
     */
    public function registerRoutes(): void
    {
        register_rest_route('rrze-formular/v1', '/token', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'issueToken'],
            'permission_callback' => [$this, 'allowPublicSubmit'],
            'args' => $this->getTokenArgs(),
        ]);

        register_rest_route('rrze-formular/v1', '/submit', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'submit'],
            'permission_callback' => [$this, 'allowPublicSubmit'],
            'args' => $this->getSubmitArgs(),
        ]);
    }

    /**
     * Allow anonymous and logged-in visitors to reach the form endpoints.
     *
     * @return bool Always true; request validation and anti-spam checks run in the handlers.
     */
    public function allowPublicSubmit(): bool
    {
        return true;
    }

    /**
     * Issue a token for a signed form configuration after checking storage and rate limits.
     *
     * @param WP_REST_Request $request Request containing the signed configuration, form ID and optional post ID.
     * @return WP_REST_Response Token and issue time on success, or a 400, 429 or 503 error.
     */
    public function issueToken(WP_REST_Request $request): WP_REST_Response
    {
        if (!SpamProtection::publicEndpointsAvailable()) {
            return $this->storageUnavailableResponse();
        }

        $trustedConfig = $this->resolveTrustedConfig($request);
        if ($trustedConfig === null) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Invalid form configuration.', 'rrze-formular'),
            ], 400);
        }

        $formId = sanitize_key((string) $request->get_param('formId'));
        if ($formId === '') {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Invalid form configuration.', 'rrze-formular'),
            ], 400);
        }

        if (!SpamProtection::tryAcquireTokenIssueSlot()) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Too many requests. Please try again later.', 'rrze-formular'),
            ], 429);
        }

        $tokenData = SpamProtection::createToken(
            $formId,
            FormConfigAuth::configHash($trustedConfig),
            max(0, (int) $request->get_param('postId'))
        );

        return new WP_REST_Response([
            'success' => true,
            'token' => $tokenData['token'],
            'issuedAt' => $tokenData['issuedAt'],
        ], 200);
    }

    /**
     * Pass submitted values to FormHandler and use its status for the REST response.
     *
     * @param WP_REST_Request $request Validated request containing field values and anti-spam credentials.
     * @return WP_REST_Response Submission result without the internal status key.
     */
    public function submit(WP_REST_Request $request): WP_REST_Response
    {
        if (!SpamProtection::publicEndpointsAvailable()) {
            return $this->storageUnavailableResponse();
        }

        $payload = [
            'formConfig' => $request->get_param('formConfig'),
            'formConfigSig' => $request->get_param('formConfigSig'),
            'values' => $request->get_param('values'),
            'website' => $request->get_param('website'),
            'token' => $request->get_param('token'),
            'pageUrl' => $request->get_param('pageUrl'),
            'formLocale' => $this->extractFormLocale($request),
        ];

        $handler = new FormHandler();
        $result = $handler->handle($payload);
        $status = (int) ($result['status'] ?? 200);

        unset($result['status']);

        return new WP_REST_Response($result, $status);
    }

    /**
     * Build the response used when public endpoints require an unavailable object cache.
     *
     * @return WP_REST_Response HTTP 503 response with a public error message.
     */
    private function storageUnavailableResponse(): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => false,
            'code' => 'persistent_object_cache_required',
            'message' => SpamProtection::publicEndpointUnavailableMessage(),
        ], 503);
    }

    /**
     * Read the requested form language from the JSON body.
     *
     * @param WP_REST_Request $request Incoming submission request.
     * @return string Sanitized formLocale value, legacy locale value, or an empty string.
     */
    private function extractFormLocale(WP_REST_Request $request): string
    {
        $json = $request->get_json_params();
        if (!is_array($json)) {
            $json = [];
        }

        $locale = $json['formLocale'] ?? $json['locale'] ?? '';

        return sanitize_text_field((string) $locale);
    }

    /**
     * Describe required token-request parameters and their validation callbacks.
     *
     * @return array<string, array<string, mixed>> WordPress REST argument definitions.
     */
    private function getTokenArgs(): array
    {
        return [
            'formConfig' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => [$this, 'sanitizeStringParam'],
                'validate_callback' => [$this, 'validateNonEmptyString'],
            ],
            'formConfigSig' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => [$this, 'sanitizeStringParam'],
                'validate_callback' => [$this, 'validateNonEmptyString'],
            ],
            'formId' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_key',
                'validate_callback' => [$this, 'validateNonEmptyString'],
            ],
            'postId' => [
                'required' => false,
                'type' => 'integer',
                'default' => 0,
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    /**
     * Describe submission parameters, defaults and validation callbacks.
     *
     * @return array<string, array<string, mixed>> WordPress REST argument definitions.
     */
    private function getSubmitArgs(): array
    {
        return [
            'formConfig' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => [$this, 'sanitizeStringParam'],
                'validate_callback' => [$this, 'validateNonEmptyString'],
            ],
            'formConfigSig' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => [$this, 'sanitizeStringParam'],
                'validate_callback' => [$this, 'validateNonEmptyString'],
            ],
            'values' => [
                'required' => true,
                'type' => 'object',
                'validate_callback' => [$this, 'validateValuesObject'],
            ],
            'token' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => [$this, 'sanitizeStringParam'],
                'validate_callback' => [$this, 'validateNonEmptyString'],
            ],
            'website' => [
                'required' => false,
                'type' => 'string',
                'default' => '',
                'sanitize_callback' => [$this, 'sanitizeStringParam'],
            ],
            'pageUrl' => [
                'required' => false,
                'type' => 'string',
                'default' => '',
                'sanitize_callback' => 'esc_url_raw',
                'validate_callback' => [$this, 'validateOptionalUrl'],
            ],
        ];
    }

    /**
     * Verify the signed form configuration supplied with a request.
     *
     * @param WP_REST_Request $request Request containing formConfig and formConfigSig.
     * @return array<string, mixed>|null Normalized configuration, or null if verification fails.
     */
    private function resolveTrustedConfig(WP_REST_Request $request): ?array
    {
        return FormConfigAuth::verify(
            (string) $request->get_param('formConfig'),
            (string) $request->get_param('formConfigSig')
        );
    }

    /**
     * Trim a scalar request value without altering its encoded content.
     *
     * @param mixed $value Raw parameter value.
     * @return string Trimmed value, or an empty string for non-scalar input.
     */
    public function sanitizeStringParam(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }

    /**
     * Check that a required REST parameter is a non-empty string.
     *
     * @param mixed $value Parameter value to validate.
     * @param WP_REST_Request $request Request supplied by WordPress; unused by this validator.
     * @param string $param Parameter name supplied by WordPress; unused by this validator.
     * @return bool True for a non-empty string; whitespace is handled by sanitization.
     */
    public function validateNonEmptyString($value, WP_REST_Request $request, string $param): bool
    {
        return is_string($value) && $value !== '';
    }

    /**
     * Check that submitted values form a field-name map with no nested objects.
     *
     * @param mixed $value Parameter value to validate.
     * @param WP_REST_Request $request Request supplied by WordPress; unused by this validator.
     * @param string $param Parameter name supplied by WordPress; unused by this validator.
     * @return bool True for string keys and scalar, null or one-level array values.
     */
    public function validateValuesObject($value, WP_REST_Request $request, string $param): bool
    {
        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $key => $item) {
            if (!is_string($key) || $key === '') {
                return false;
            }

            if (is_array($item)) {
                foreach ($item as $part) {
                    if (!is_scalar($part) && $part !== null) {
                        return false;
                    }
                }
                continue;
            }

            if (!is_scalar($item) && $item !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Accept an omitted URL or a syntactically valid URL string.
     *
     * @param mixed $value Parameter value to validate.
     * @param WP_REST_Request $request Request supplied by WordPress; unused by this validator.
     * @param string $param Parameter name supplied by WordPress; unused by this validator.
     * @return bool True for an empty value or a valid URL; this does not check the site host.
     */
    public function validateOptionalUrl($value, WP_REST_Request $request, string $param): bool
    {
        if ($value === '' || $value === null) {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}
