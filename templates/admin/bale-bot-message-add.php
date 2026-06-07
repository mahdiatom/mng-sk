<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!current_user_can('manage_options')) {
    wp_die('دسترسی غیرمجاز.');
}

$list_url = admin_url('admin.php?page=sc-bale-bot-messages');
$message = '';
$message_type = '';

if (isset($_POST['send_bale_message']) && check_admin_referer('sc_bale_send_message_nonce')) {
    $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    $content = isset($_POST['content']) ? sanitize_textarea_field(wp_unslash($_POST['content'])) : '';
    $delivery_mode = isset($_POST['delivery_mode']) ? sanitize_text_field(wp_unslash($_POST['delivery_mode'])) : 'bot_only';
    if (!in_array($delivery_mode, ['bot_only', 'safir_only', 'both'], true)) {
        $delivery_mode = 'bot_only';
    }

    list($target_type, $target_config) = sc_bale_parse_target_config_from_post($_POST);

    if ($title === '' || $content === '') {
        $message = 'عنوان و متن پیام الزامی است.';
        $message_type = 'error';
    } elseif (!bale_is_configured()) {
        $message = 'ابتدا توکن و نام کاربری ربات را در تنظیمات ذخیره کنید.';
        $message_type = 'error';
    } elseif ($delivery_mode !== 'bot_only' && !sc_bale_safir_is_configured()) {
        $message = 'برای ارسال هزینه‌دار، تنظیمات سفیر بله را در تب ربات بله تکمیل کنید.';
        $message_type = 'error';
    } elseif ($target_type === 'phone' && $delivery_mode === 'bot_only') {
        $message = 'ارسال به شماره مشخص فقط از طریق سفیر (هزینه‌دار) امکان‌پذیر است.';
        $message_type = 'error';
    } elseif ($target_type === 'phone' && empty($target_config['phone_numbers'])) {
        $message = 'لطفاً حداقل یک شماره موبایل وارد کنید یا فایل اکسل آپلود کنید.';
        $message_type = 'error';
    } else {
        $all = sc_bale_resolve_recipients($target_type, $target_config);
        $to_send = sc_bale_filter_recipients_for_send($all, $delivery_mode);

        if (empty($to_send)) {
            $stats = sc_bale_count_recipients_by_chat($all);
            $message = 'هیچ مخاطبی با این فیلتر و حالت ارسال یافت نشد. (دارای Chat ID: ' . $stats['with_chat_id'] . ' — بدون Chat ID: ' . $stats['without_chat_id'] . ')';
            $message_type = 'warning';
        } else {
            $result = sc_bale_send_bulk_message($title, $content, $target_type, $target_config, $delivery_mode);
            wp_safe_redirect(add_query_arg([
                'page'   => 'sc-bale-bot-messages',
                'sent'   => 1,
                'bot'    => $result['bot_sent'],
                'safir'  => $result['safir_sent'],
            ], admin_url('admin.php')));
            exit;
        }
    }
}

sc_check_and_create_tables();
global $wpdb;
$members_table = $wpdb->prefix . 'sc_members';
$coaches_table = $wpdb->prefix . 'sc_coaches';
$courses_table = $wpdb->prefix . 'sc_courses';
$events_table  = $wpdb->prefix . 'sc_events';

$members = $wpdb->get_results("SELECT id, first_name, last_name, national_id, user_id FROM $members_table WHERE user_id IS NOT NULL AND is_active = 1 ORDER BY last_name, first_name");
$coaches = $wpdb->get_results("SELECT id, first_name, last_name, national_id, user_id FROM $coaches_table WHERE user_id IS NOT NULL AND is_active = 1 ORDER BY last_name, first_name");
$courses_list = $wpdb->get_results("SELECT id, title FROM $courses_table WHERE deleted_at IS NULL AND is_active = 1 ORDER BY title");
$events_list = $wpdb->get_results("SELECT id, name FROM $events_table WHERE (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00') AND is_active = 1 ORDER BY name");

$saved = [];
$initial_target_type = 'all';
$safir_configured = sc_bale_safir_is_configured();
?>
<div class="wrap sc-bale-admin-wrap sc-notification-add-wrap sc-users-export-wrap sc-bulk-actions-wrap">
    <h1 class="sc-notification-add-title">ارسال پیام ربات بله</h1>
    <p>
        <a href="<?php echo esc_url($list_url); ?>">&larr; بازگشت به لیست</a>
        &nbsp;|&nbsp;
        <a href="<?php echo esc_url(admin_url('admin.php?page=sc_setting&tab=bale_bot')); ?>">تنظیمات ربات</a>
    </p>

    <?php if ($message) : ?>
        <div class="notice notice-<?php echo $message_type === 'error' ? 'error' : 'warning'; ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <div class="sc-notification-form-card">
    <form method="post" id="bale-bot-message-form" class="sc-notification-form" enctype="multipart/form-data">
        <?php wp_nonce_field('sc_bale_send_message_nonce'); ?>
        <input type="hidden" id="sc-bale-preview-nonce" value="<?php echo esc_attr(wp_create_nonce('sc_bale_admin_nonce')); ?>">
        <input type="hidden" id="sc-phone-excel-preview-nonce" value="<?php echo esc_attr(wp_create_nonce('sc_preview_phone_excel')); ?>">

        <table class="form-table sc-notification-form-table">
            <tr>
                <th scope="row"><label for="title">عنوان <span class="required">*</span></label></th>
                <td><input type="text" name="title" id="title" class="regular-text sc-notification-input" required></td>
            </tr>
            <tr>
                <th scope="row"><label for="content">متن پیام <span class="required">*</span></label></th>
                <td><textarea name="content" id="content" rows="6" class="large-text sc-notification-textarea" required></textarea></td>
            </tr>
        </table>

        <div class="sc-users-export-card sc-bale-delivery-mode-card">
            <h2>۱) مخاطبین ارسال — Chat ID</h2>
            <p class="description" style="margin-bottom:16px;">
                ابتدا مشخص کنید پیام به کدام گروه ارسال شود. ارسال از طریق ربات (Chat ID) رایگان است؛
                ارسال از طریق <a href="https://docs.bale.ai/safir" target="_blank" rel="noopener">سفیر</a> برای کاربران بدون Chat ID هزینه‌دار است.
            </p>
            <div class="sc-bale-delivery-options">
                <label class="sc-bale-delivery-option">
                    <input type="radio" name="delivery_mode" value="bot_only" checked>
                    <span class="sc-bale-delivery-option__title">فقط دارای Chat ID</span>
                    <span class="sc-bale-delivery-option__badge sc-bale-badge sc-bale-badge--free">رایگان</span>
                    <span class="sc-bale-delivery-option__desc">فقط کاربرانی که ربات را متصل کرده‌اند</span>
                </label>
                <label class="sc-bale-delivery-option<?php echo $safir_configured ? '' : ' is-disabled'; ?>">
                    <input type="radio" name="delivery_mode" value="safir_only" <?php disabled(!$safir_configured); ?>>
                    <span class="sc-bale-delivery-option__title">فقط بدون Chat ID</span>
                    <span class="sc-bale-delivery-option__badge sc-bale-badge sc-bale-badge--paid">هزینه‌دار — سفیر</span>
                    <span class="sc-bale-delivery-option__desc">کاربرانی که هنوز ربات را متصل نکرده‌اند</span>
                </label>
                <label class="sc-bale-delivery-option<?php echo $safir_configured ? '' : ' is-disabled'; ?>">
                    <input type="radio" name="delivery_mode" value="both" <?php disabled(!$safir_configured); ?>>
                    <span class="sc-bale-delivery-option__title">هر دو گروه</span>
                    <span class="sc-bale-delivery-option__badge sc-bale-badge sc-bale-badge--mixed">ترکیبی</span>
                    <span class="sc-bale-delivery-option__desc">دارای Chat ID رایگان + بدون Chat ID از سفیر</span>
                </label>
            </div>
            <?php if (!$safir_configured) : ?>
                <p class="description" style="color:#b45309;margin-top:12px;">
                    برای گزینه‌های هزینه‌دار، API سفیر را در <a href="<?php echo esc_url(admin_url('admin.php?page=sc_setting&tab=bale_bot')); ?>">تنظیمات ربات بله</a> وارد کنید.
                </p>
            <?php endif; ?>
            <div id="sc-bale-live-counts" class="sc-bale-live-counts" style="display:none;">
                <div class="sc-bale-stats">
                    <div class="sc-bale-stat sc-bale-stat--free"><strong id="sc-bale-count-with">0</strong><span>دارای Chat ID</span></div>
                    <div class="sc-bale-stat sc-bale-stat--paid"><strong id="sc-bale-count-without">0</strong><span>بدون Chat ID</span></div>
                    <div class="sc-bale-stat"><strong id="sc-bale-count-send">0</strong><span>قابل ارسال (حالت فعلی)</span></div>
                </div>
            </div>
        </div>

        <?php include SC_TEMPLATES_ADMIN_DIR . 'partials/bale-bot-audience-filters.php'; ?>

        <p class="submit">
            <button type="submit" name="send_bale_message" id="btn-send-bale-message" class="button button-primary">ارسال پیام</button>
        </p>
    </form>
    </div>
</div>
