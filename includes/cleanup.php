<?php
/**
 * Plugin Name: Atom WordPress Emoji Cleaner
 * Description: غیرفعال‌سازی اموجی‌ها به روش فانکشنال
 */

// جلوگیری از دسترسی مستقیم
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * تابع اصلی برای حذف هوک‌های اموجی
 */
function atom_disable_wp_emojis() {
    // حذف اسکریپت و استایل از بخش کاربری
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );

    // حذف اسکریپت و استایل از پنل مدیریت
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'admin_print_styles', 'print_emoji_styles' );

    // حذف از فیدهای RSS و ایمیل‌ها
    remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
    remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
    remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

    // حذف از ویرایشگر قدیمی (TinyMCE)
    add_filter( 'tiny_mce_plugins', 'atom_disable_emojis_tinymce' );

    // حذف درخواست پیش‌بارگذاری DNS به سرور s.w.org
    add_filter( 'wp_resource_hints', 'atom_disable_emojis_remove_dns_prefetch', 10, 2 );
}
// اجرای تابع اصلی در هوک init
add_action( 'init', 'atom_disable_wp_emojis' );

/**
 * تابع کمکی برای حذف پلاگین اموجی از ویرایشگر متنی
 */
function atom_disable_emojis_tinymce( $plugins ) {
    if ( is_array( $plugins ) ) {
        return array_diff( $plugins, array( 'wpemoji' ) );
    }
    return array();
}

/**
 * تابع کمکی برای حذف URL اموجی از لیست DNS Prefetch
 */
function atom_disable_emojis_remove_dns_prefetch( $urls, $relation_type ) {
    if ( 'dns-prefetch' === $relation_type ) {
        $emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/' );
        foreach ( $urls as $key => $url ) {
            if ( strpos( $url, $emoji_svg_url ) !== false ) {
                unset( $urls[$key] );
            }
        }
    }
    return $urls;
}