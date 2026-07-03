<?php

namespace RRZE\Formular\Common\API;

use RRZE\Formular\Common\Form\FormHandler;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit;

/**
 * Public REST endpoint for form submissions.
 *
 * The route is intentionally accessible without login. Protection is enforced
 * server-side in FormHandler via signed form configuration, one-time submission
 * tokens, honeypot, minimum submit delay and rate limiting — not via wp_rest nonce.
 */
class FormAPI
{
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route('rrze-formular/v1', '/submit', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'submit'],
            'permission_callback' => [$this, 'allowPublicSubmit'],
            'args' => $this->getSubmitArgs(),
        ]);
    }

    /**
     * Anonymous and logged-in visitors may submit forms.
     */
    public function allowPublicSubmit(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<string, mixed>>
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
            'locale' => [
                'required' => false,
                'type' => 'string',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => [$this, 'validateOptionalLocale'],
            ],
        ];
    }

    public function submit(WP_REST_Request $request): WP_REST_Response
    {
        $payload = [
            'formConfig' => $request->get_param('formConfig'),
            'formConfigSig' => $request->get_param('formConfigSig'),
            'values' => $request->get_param('values'),
            'website' => $request->get_param('website'),
            'token' => $request->get_param('token'),
            'pageUrl' => $request->get_param('pageUrl'),
            'locale' => $request->get_param('locale'),
        ];

        $handler = new FormHandler();
        $result = $handler->handle($payload);
        $status = (int) ($result['status'] ?? 200);

        unset($result['status']);

        return new WP_REST_Response($result, $status);
    }

    public function sanitizeStringParam(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }

    /**
     * @param mixed $value
     */
    public function validateNonEmptyString($value, WP_REST_Request $request, string $param): bool
    {
        return is_string($value) && $value !== '';
    }

    /**
     * @param mixed $value
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
     * @param mixed $value
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

    /**
     * @param mixed $value
     */
    public function validateOptionalLocale($value, WP_REST_Request $request, string $param): bool
    {
        if ($value === '' || $value === null) {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        return (bool) preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/i', $value);
    }
}
