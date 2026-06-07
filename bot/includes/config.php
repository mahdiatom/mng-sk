<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_get_bot_token() {
    return sc_get_setting('sc_token_club', '');
}

function bale_get_bot_username() {
    $username = sc_get_setting('sc_botname_club', '');
    return ltrim((string) $username, '@');
}

function bale_get_api_url() {
    $token = bale_get_bot_token();
    return 'https://tapi.bale.ai/bot' . $token . '/';
}

function bale_get_webhook_url() {
    return rest_url('bale-bot/v1/webhook');
}

function bale_is_configured() {
    return bale_get_bot_token() !== '' && bale_get_bot_username() !== '';
}

function bale_set_webhook($webhook_url = null) {
    if ($webhook_url === null) {
        $webhook_url = bale_get_webhook_url();
    }

    $url = bale_get_api_url() . 'setWebhook';
    $body = ['url' => $webhook_url];

    return wp_remote_post($url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => wp_json_encode($body, JSON_UNESCAPED_UNICODE),
        'timeout' => 20,
    ]);
}

function bale_delete_webhook() {
    $url = bale_get_api_url() . 'deleteWebhook';
    return wp_remote_post($url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => '{}',
        'timeout' => 20,
    ]);
}

function bale_get_webhook_info() {
    $url = bale_get_api_url() . 'getWebhookInfo';
    return wp_remote_post($url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => '{}',
        'timeout' => 20,
    ]);
}

function bale_get_me() {
    $url = bale_get_api_url() . 'getMe';
    return wp_remote_post($url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => '{}',
        'timeout' => 20,
    ]);
}
