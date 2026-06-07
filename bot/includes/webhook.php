<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', 'bale_register_webhook');

function bale_register_webhook() {
    register_rest_route('bale-bot/v1', '/webhook', [
        'methods'             => 'POST',
        'callback'            => 'bale_webhook_handler',
        'permission_callback' => 'bale_webhook_permission',
    ]);
}

function bale_webhook_permission($request) {
    if (!bale_is_configured()) {
        return false;
    }

    $secret = sc_get_setting('sc_bale_webhook_secret', '');
    if ($secret === '') {
        return true;
    }

    $header = $request->get_header('x-bale-webhook-secret');
    return hash_equals($secret, (string) $header);
}

function bale_webhook_handler($request) {
    $data = json_decode($request->get_body(), true);

    if (!$data) {
        return ['status' => 'empty'];
    }

    bale_router($data);

    return ['status' => 'ok'];
}
