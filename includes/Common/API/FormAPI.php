<?php

namespace RRZE\Formular\Common\API;

use RRZE\Formular\Common\Form\FormHandler;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

class FormAPI
{
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route('rrze-formular/v1', '/submit', [
            'methods' => 'POST',
            'callback' => [$this, 'submit'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function submit(WP_REST_Request $request): WP_REST_Response
    {
        $payload = [
            'attributes' => $request->get_param('attributes'),
            'values' => $request->get_param('values'),
            'website' => $request->get_param('website'),
            'token' => $request->get_param('token'),
        ];

        $handler = new FormHandler();
        $result = $handler->handle($payload);
        $status = (int) ($result['status'] ?? 200);

        unset($result['status']);

        return new WP_REST_Response($result, $status);
    }
}
