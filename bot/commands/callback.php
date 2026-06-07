<?php
if (!defined('ABSPATH')) {
    exit;
}

function bale_cb_cat_shop($chat_id) {
    $categories = get_terms([
        'taxonomy'   => 'product_cat',
        'orderby'    => 'name',
        'order'      => 'ASC',
        'hide_empty' => false,
    ]);

    if (empty($categories) || is_wp_error($categories)) {
        bale_send_message($chat_id, 'هیچ دسته‌بندی‌ای پیدا نشد.');
        return;
    }

    $buttons = [];
    foreach ($categories as $cat) {
        $buttons[] = [[
            'text'          => $cat->name . ' (' . $cat->count . ' محصول)',
            'callback_data' => 'shop_cat_' . $cat->term_id,
        ]];
    }

    bale_send_message_with_buttons($chat_id, 'یکی از دسته‌بندی‌ها را انتخاب کنید:', $buttons);
}

function bale_cb_show_products_in_category($chat_id, $cat_id) {
    $cat_id = absint($cat_id);
    $products = wc_get_products([
        'status'  => 'publish',
        'limit'   => 10,
        'category'=> [$cat_id],
    ]);

    if (empty($products)) {
        bale_send_message($chat_id, 'محصولی در این دسته‌بندی یافت نشد.');
        return;
    }

    $buttons = [];
    foreach ($products as $product) {
        $buttons[] = [bale_make_link_button($product->get_name(), get_permalink($product->get_id()))];
    }

    $cat = get_term($cat_id, 'product_cat');
    $cat_name = ($cat && !is_wp_error($cat)) ? $cat->name : 'دسته‌بندی';

    bale_send_message_with_buttons($chat_id, "محصولات دسته «{$cat_name}»:", $buttons);
}
