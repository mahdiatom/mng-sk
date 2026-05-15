<?php
/**
 * SportClub - Admin Dashboard Widgets
 *
 * مجموعه ابزارک‌های پیشخوان وردپرس برای مدیران (مدیر کل / مدیر باشگاه).
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * بررسی اینکه آیا کاربر فعلی اجازهٔ دیدن ابزارک‌های پیشخوان SportClub را دارد.
 * فقط نقش‌های: مدیر کل (administrator)، مدیر باشگاه (club_coach)، حسابدار (accountantt).
 * مربی، مدیر فروشگاه و سایر نقش‌ها ابزارک‌ها را نمی‌بینند.
 */
function sc_admin_dashboard_widgets_user_can() {
    if (!is_user_logged_in()) {
        return false;
    }
    $allowed_roles = ['administrator', 'club_coach', 'accountantt'];
    $user          = wp_get_current_user();
    if (!$user || empty($user->roles)) {
        return false;
    }
    $roles = array_values(array_map('strval', (array) $user->roles));

    return (bool) array_intersect($allowed_roles, $roles);
}

/**
 * ثبت همهٔ ابزارک‌های پیشخوان.
 */
add_action('wp_dashboard_setup', 'sc_register_admin_dashboard_widgets', 1000);
function sc_register_admin_dashboard_widgets() {
    if (!sc_admin_dashboard_widgets_user_can()) {
        return;
    }

    // 1) کاربران احراز هویت نشده
    wp_add_dashboard_widget(
        'sc_dw_unverified_members',
        'کاربران احراز هویت نشده',
        'sc_dw_render_unverified_members'
    );

    // 2) تیکت‌های باز (در انتظار پاسخ)
    if (function_exists('sc_is_pro_feature_support_tickets_enabled') && sc_is_pro_feature_support_tickets_enabled()) {
        wp_add_dashboard_widget(
            'sc_dw_open_tickets',
            'تیکت‌های باز',
            'sc_dw_render_open_tickets'
        );
    }

    // 3) سفارش‌های فروشگاه (همان لیست sc_orders — منتظر ارسال)
    if (class_exists('WooCommerce')) {
        wp_add_dashboard_widget(
            'sc_dw_wc_processing_orders',
            'سفارش‌های فروشگاه (منتظر ارسال)',
            'sc_dw_render_wc_processing_orders'
        );
    }

    // 4) افتخارات تایید نشده
    if (function_exists('sc_is_pro_feature_honors_enabled') && sc_is_pro_feature_honors_enabled()) {
        wp_add_dashboard_widget(
            'sc_dw_pending_honors',
            'افتخارات در انتظار تایید',
            'sc_dw_render_pending_honors'
        );
    }

    // 5) بیمه‌های منقضی شده
    wp_add_dashboard_widget(
        'sc_dw_expired_insurance',
        'بیمه‌های منقضی شده',
        'sc_dw_render_expired_insurance'
    );

    // 6) هشدارهای غیبت بیش از حد
    if (function_exists('sc_is_pro_feature_user_alerts_enabled') && sc_is_pro_feature_user_alerts_enabled()) {
        wp_add_dashboard_widget(
            'sc_dw_absence_alerts',
            'هشدار غیبت بیش از حد کاربران',
            'sc_dw_render_absence_alerts'
        );
    }

    // 7) درخواست‌های برداشت مربی‌ها در انتظار تایید
    wp_add_dashboard_widget(
        'sc_dw_coach_withdrawals',
        'درخواست‌های برداشت مربی‌ها',
        'sc_dw_render_coach_withdrawals'
    );

    // 8) آمار کدهای تخفیف استفاده شده
    wp_add_dashboard_widget(
        'sc_dw_discount_codes_usage',
        'کدهای تخفیف استفاده شده',
        'sc_dw_render_discount_codes_usage'
    );
}

/**
 * بارگذاری استایل ابزارک‌ها فقط در صفحه پیشخوان وردپرس.
 */
add_action('admin_enqueue_scripts', 'sc_admin_dashboard_widgets_enqueue');
function sc_admin_dashboard_widgets_enqueue($hook) {
    if ($hook !== 'index.php') {
        return;
    }
    if (!sc_admin_dashboard_widgets_user_can()) {
        return;
    }
    wp_enqueue_style(
        'sc-admin-dashboard-widgets',
        SC_ASSETS_URL . 'css/admin-dashboard-widgets.css',
        [],
        file_exists(SC_ASSETS_DIR . 'css/admin-dashboard-widgets.css')
            ? (string) filemtime(SC_ASSETS_DIR . 'css/admin-dashboard-widgets.css')
            : '1.0'
    );
}

/* ====================================================================
 * Helpers
 * ================================================================= */

/** ساخت نام نمایشی کاربر از روی row جدول members */
function sc_dw_member_display_name($row) {
    $name = trim((isset($row->first_name) ? (string) $row->first_name : '') . ' ' . (isset($row->last_name) ? (string) $row->last_name : ''));
    if ($name === '') {
        $name = isset($row->player_phone) ? (string) $row->player_phone : 'بدون نام';
    }
    return $name;
}

/** نمایش پیام خالی استاندارد */
function sc_dw_render_empty($message, $is_success = false) {
    $class = $is_success ? 'sc-dw-empty sc-dw-empty-success' : 'sc-dw-empty';
    echo '<div class="' . esc_attr($class) . '">' . esc_html($message) . '</div>';
}

/** ساخت لینک admin */
function sc_dw_admin_url($args) {
    return add_query_arg($args, admin_url('admin.php'));
}

/* ====================================================================
 * 1) کاربران احراز هویت نشده
 * ================================================================= */
function sc_dw_render_unverified_members() {
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';

    $rows = $wpdb->get_results(
        "SELECT id, first_name, last_name, player_phone, national_id, created_at
         FROM $members_table
         WHERE is_active = 1
           AND (identity_verified = 0 OR identity_verified IS NULL)
         ORDER BY created_at DESC
         LIMIT 5"
    );

    $total = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM $members_table
         WHERE is_active = 1 AND (identity_verified = 0 OR identity_verified IS NULL)"
    );

    $list_url = sc_dw_admin_url(['page' => 'sc-members', 'filter_identity' => 'pending']);

    echo '<div class="sc-dw-card sc-dw-card-red">';
    echo '<div class="sc-dw-card-head">';
    echo '<span class="sc-dw-badge sc-dw-badge-red">' . esc_html(number_format_i18n($total)) . '</span>';
    echo '<a class="sc-dw-link-btn" href="' . esc_url($list_url) . '">مشاهده همه</a>';
    echo '</div>';

    if (empty($rows)) {
        sc_dw_render_empty('✅ کاربر احراز هویت نشده‌ای وجود ندارد.', true);
    } else {
        echo '<ul class="sc-dw-list">';
        foreach ($rows as $row) {
            $view_url = sc_dw_admin_url(['page' => 'sc-view-member', 'player_id' => (int) $row->id]);
            $name     = sc_dw_member_display_name($row);
            $phone    = !empty($row->player_phone) ? $row->player_phone : '—';
            $created  = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($row->created_at) : $row->created_at;
            echo '<li class="sc-dw-list-item">';
            echo '<a class="sc-dw-list-link" href="' . esc_url($view_url) . '">';
            echo '<div class="sc-dw-list-main">';
            echo '<div class="sc-dw-list-title">' . esc_html($name) . '</div>';
            echo '<div class="sc-dw-list-meta">📞 ' . esc_html($phone) . ' <span class="sc-dw-sep">|</span> 📅 ' . esc_html($created) . '</div>';
            echo '</div>';
            echo '<span class="sc-dw-status sc-dw-status-warning">احراز نشده</span>';
            echo '</a>';
            echo '</li>';
        }
        echo '</ul>';
    }
    echo '</div>';
}

/* ====================================================================
 * 2) تیکت‌های باز
 * ================================================================= */
function sc_dw_render_open_tickets() {
    global $wpdb;
    $tickets_table = $wpdb->prefix . 'sc_support_tickets';
    $users_table   = $wpdb->users;

    $rows = $wpdb->get_results(
        "SELECT t.id, t.subject, t.status, t.department, t.updated_at, t.user_id,
                u.display_name
         FROM $tickets_table t
         LEFT JOIN $users_table u ON u.ID = t.user_id
         WHERE t.status = 'pending_reply'
         ORDER BY t.updated_at DESC
         LIMIT 5"
    );

    $total = function_exists('sc_support_count_pending_reply_for_admin')
        ? (int) sc_support_count_pending_reply_for_admin()
        : (int) $wpdb->get_var("SELECT COUNT(*) FROM $tickets_table WHERE status = 'pending_reply'");

    $list_url = sc_dw_admin_url(['page' => 'sc-support-tickets', 'filter_status' => 'pending_reply']);

    echo '<div class="sc-dw-card sc-dw-card-orange">';
    echo '<div class="sc-dw-card-head">';
    echo '<span class="sc-dw-badge sc-dw-badge-orange">' . esc_html(number_format_i18n($total)) . '</span>';
    echo '<a class="sc-dw-link-btn" href="' . esc_url($list_url) . '">مشاهده همه تیکت‌ها</a>';
    echo '</div>';

    if (empty($rows)) {
        sc_dw_render_empty('✅ تیکت بازی برای پاسخ‌گویی وجود ندارد.', true);
    } else {
        echo '<ul class="sc-dw-list">';
        foreach ($rows as $row) {
            $view_url = sc_dw_admin_url(['page' => 'sc-support-ticket-view', 'ticket_id' => (int) $row->id]);
            $user     = $row->display_name ?: 'کاربر #' . (int) $row->user_id;
            $updated  = function_exists('sc_date_shamsi') ? sc_date_shamsi($row->updated_at, 'Y/m/d - H:i') : $row->updated_at;
            echo '<li class="sc-dw-list-item">';
            echo '<a class="sc-dw-list-link" href="' . esc_url($view_url) . '">';
            echo '<div class="sc-dw-list-main">';
            echo '<div class="sc-dw-list-title">#' . esc_html($row->id) . ' - ' . esc_html($row->subject) . '</div>';
            echo '<div class="sc-dw-list-meta">👤 ' . esc_html($user) . ' <span class="sc-dw-sep">|</span> 🕒 ' . esc_html($updated) . '</div>';
            echo '</div>';
            echo '<span class="sc-dw-status sc-dw-status-warning">در انتظار پاسخ</span>';
            echo '</a>';
            echo '</li>';
        }
        echo '</ul>';
    }
    echo '</div>';
}

/* ====================================================================
 * 3) سفارش‌های فروشگاه — همان کوئری صفحه sc_orders (wc-processing، عضو + خط آیتم)
 * ================================================================= */
function sc_dw_render_wc_processing_orders() {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'wc_orders';

    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $orders_table)) !== $orders_table) {
        echo '<div class="sc-dw-card sc-dw-card-blue">';
        sc_dw_render_empty('جدول سفارش‌ها در دسترس نیست.');
        echo '</div>';
        return;
    }

    if (!defined('SC_TEMPLATES_ADMIN_DIR')) {
        echo '<div class="sc-dw-card sc-dw-card-blue">';
        sc_dw_render_empty('مسیر قالب‌های مدیریت تعریف نشده است.');
        echo '</div>';
        return;
    }

    if (!function_exists('sc_admin_sc_orders_query_list_snapshot')) {
        require_once SC_TEMPLATES_ADMIN_DIR . 'list_order.php';
    }

    if (!function_exists('sc_admin_sc_orders_query_list_snapshot')) {
        echo '<div class="sc-dw-card sc-dw-card-blue">';
        sc_dw_render_empty('ماژول لیست سفارش‌ها بارگذاری نشد.');
        echo '</div>';
        return;
    }

    $snap = sc_admin_sc_orders_query_list_snapshot([
        'filter_status' => 'processing',
        'limit' => 5,
        'offset' => 0,
        'orderby' => 'date_order',
        'order' => 'DESC',
    ]);

    $rows = isset($snap['items']) && is_array($snap['items']) ? $snap['items'] : [];
    $total = isset($snap['total']) ? (int) $snap['total'] : 0;
    $sum = isset($snap['sum_total']) ? (float) $snap['sum_total'] : 0.0;

    $list_url = admin_url('admin.php?page=sc_orders&filter_status=processing');

    echo '<div class="sc-dw-card sc-dw-card-blue">';
    echo '<div class="sc-dw-card-head">';
    echo '<span class="sc-dw-badge sc-dw-badge-blue">' . esc_html(number_format_i18n($total)) . '</span>';
    echo '<a class="sc-dw-link-btn" href="' . esc_url($list_url) . '">مشاهده همه</a>';
    echo '</div>';

    if ($total > 0) {
        $sum_fmt = function_exists('sc_format_amount_display') ? sc_format_amount_display($sum) : number_format($sum);
        echo '<div class="sc-dw-stat-row"><span>مجموع مبلغ (پرداخت شده، منتظر ارسال):</span> <strong>' . esc_html($sum_fmt) . ' تومان</strong></div>';
    }

    if (empty($rows)) {
        sc_dw_render_empty('✅ سفارشی با این وضعیت در لیست فروشگاه نیست.', true);
    } else {
        echo '<ul class="sc-dw-list">';
        foreach ($rows as $row) {
            $view_url = admin_url('admin.php?page=wc-orders&action=edit&id=' . (int) $row->id_order);
            $amount_fmt = function_exists('sc_format_amount_display') ? sc_format_amount_display($row->total_amount) : number_format($row->total_amount);
            $created = function_exists('sc_date_shamsi') ? sc_date_shamsi($row->date_created_gmt, 'Y/m/d - H:i') : $row->date_created_gmt;
            $full_name = trim((string) ($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
            if ($full_name === '') {
                $full_name = 'عضو';
            }
            $phone = '';
            if (function_exists('sanitize_iran_phone') && !empty($row->player_phone)) {
                $phone = sanitize_iran_phone($row->player_phone) ?: (string) $row->player_phone;
            } elseif (!empty($row->player_phone)) {
                $phone = (string) $row->player_phone;
            }
            $st = isset($row->status) ? (string) $row->status : '';
            $ui = function_exists('sc_admin_sc_orders_status_list_ui') ? sc_admin_sc_orders_status_list_ui($st) : ['label' => $st, 'color' => '#666', 'bg' => '#f5f5f5'];

            echo '<li class="sc-dw-list-item">';
            echo '<a class="sc-dw-list-link" href="' . esc_url($view_url) . '">';
            echo '<div class="sc-dw-list-main">';
            echo '<div class="sc-dw-list-title">#' . esc_html((string) $row->id_order) . ' — ' . esc_html($full_name) . ' — ' . esc_html($amount_fmt) . ' تومان</div>';
            echo '<div class="sc-dw-list-meta">';
            if ($phone !== '') {
                echo '📱 ' . esc_html($phone) . ' <span class="sc-dw-sep">|</span> ';
            }
            echo '🕒 ' . esc_html($created) . '</div>';
            echo '</div>';
            echo '<span class="sc-dw-status sc-dw-status-info" style="background:' . esc_attr($ui['bg']) . ';color:' . esc_attr($ui['color']) . ';">' . esc_html($ui['label']) . '</span>';
            echo '</a>';
            echo '</li>';
        }
        echo '</ul>';
    }
    echo '</div>';
}

/* ====================================================================
 * 4) افتخارات تایید نشده
 * ================================================================= */
function sc_dw_render_pending_honors() {
    global $wpdb;
    $honors_table  = $wpdb->prefix . 'sc_honors';
    $members_table = $wpdb->prefix . 'sc_members';
    $coaches_table = $wpdb->prefix . 'sc_coaches';

    $rows = $wpdb->get_results(
        "SELECT h.id, h.name, h.status, h.created_at, h.member_id, h.coach_id,
                m.first_name AS m_first, m.last_name AS m_last,
                co.first_name AS c_first, co.last_name AS c_last
         FROM $honors_table h
         LEFT JOIN $members_table m ON m.id = h.member_id
         LEFT JOIN $coaches_table co ON co.id = h.coach_id
         WHERE h.status = 'pending'
         ORDER BY h.created_at DESC
         LIMIT 5"
    );

    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $honors_table WHERE status = 'pending'");

    $list_url = sc_dw_admin_url(['page' => 'sc-honors', 'filter_status' => 'pending']);

    echo '<div class="sc-dw-card sc-dw-card-purple">';
    echo '<div class="sc-dw-card-head">';
    echo '<span class="sc-dw-badge sc-dw-badge-purple">' . esc_html(number_format_i18n($total)) . '</span>';
    echo '<a class="sc-dw-link-btn" href="' . esc_url($list_url) . '">مشاهده همه</a>';
    echo '</div>';

    if (empty($rows)) {
        sc_dw_render_empty('✅ افتخار در انتظار تاییدی وجود ندارد.', true);
    } else {
        echo '<ul class="sc-dw-list">';
        foreach ($rows as $row) {
            $owner = '';
            if (!empty($row->member_id)) {
                $owner = '🏃 ' . trim(($row->m_first ?? '') . ' ' . ($row->m_last ?? ''));
            } elseif (!empty($row->coach_id)) {
                $owner = '🧑‍🏫 ' . trim(($row->c_first ?? '') . ' ' . ($row->c_last ?? ''));
            }
            if ($owner === '' || $owner === '🏃 ' || $owner === '🧑‍🏫 ') {
                $owner = '—';
            }
            $created = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($row->created_at) : $row->created_at;
            echo '<li class="sc-dw-list-item">';
            echo '<a class="sc-dw-list-link" href="' . esc_url($list_url) . '">';
            echo '<div class="sc-dw-list-main">';
            echo '<div class="sc-dw-list-title">' . esc_html($row->name) . '</div>';
            echo '<div class="sc-dw-list-meta">' . esc_html($owner) . ' <span class="sc-dw-sep">|</span> 📅 ' . esc_html($created) . '</div>';
            echo '</div>';
            echo '<span class="sc-dw-status sc-dw-status-warning">در انتظار</span>';
            echo '</a>';
            echo '</li>';
        }
        echo '</ul>';
    }
    echo '</div>';
}

/* ====================================================================
 * 5) بیمه‌های منقضی شده
 * ================================================================= */
function sc_dw_render_expired_insurance() {
    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';

    $today_shamsi = function_exists('sc_get_today_shamsi') ? sc_get_today_shamsi() : '';
    if ($today_shamsi === '') {
        echo '<div class="sc-dw-card sc-dw-card-red">';
        sc_dw_render_empty('در دسترس نبودن توابع تاریخ شمسی.');
        echo '</div>';
        return;
    }

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, first_name, last_name, player_phone, insurance_expiry_date_shamsi
         FROM $members_table
         WHERE is_active = 1
           AND insurance_expiry_date_shamsi IS NOT NULL
           AND insurance_expiry_date_shamsi <> ''
           AND insurance_expiry_date_shamsi < %s
         ORDER BY insurance_expiry_date_shamsi DESC
         LIMIT 5",
        $today_shamsi
    ));

    $total = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $members_table
         WHERE is_active = 1
           AND insurance_expiry_date_shamsi IS NOT NULL
           AND insurance_expiry_date_shamsi <> ''
           AND insurance_expiry_date_shamsi < %s",
        $today_shamsi
    ));

    $list_url = sc_dw_admin_url(['page' => 'sc-members', 'filter_insurance' => 'expired']);

    echo '<div class="sc-dw-card sc-dw-card-red">';
    echo '<div class="sc-dw-card-head">';
    echo '<span class="sc-dw-badge sc-dw-badge-red">' . esc_html(number_format_i18n($total)) . '</span>';
    echo '<a class="sc-dw-link-btn" href="' . esc_url($list_url) . '">مشاهده همه</a>';
    echo '</div>';

    if (empty($rows)) {
        sc_dw_render_empty('✅ بیمهٔ منقضی‌شده‌ای ثبت نشده است.', true);
    } else {
        echo '<ul class="sc-dw-list">';
        foreach ($rows as $row) {
            $view_url = sc_dw_admin_url(['page' => 'sc-view-member', 'player_id' => (int) $row->id]);
            $name     = sc_dw_member_display_name($row);
            $phone    = !empty($row->player_phone) ? $row->player_phone : '—';
            echo '<li class="sc-dw-list-item">';
            echo '<a class="sc-dw-list-link" href="' . esc_url($view_url) . '">';
            echo '<div class="sc-dw-list-main">';
            echo '<div class="sc-dw-list-title">' . esc_html($name) . '</div>';
            echo '<div class="sc-dw-list-meta">📞 ' . esc_html($phone) . ' <span class="sc-dw-sep">|</span> 🛡️ انقضا: ' . esc_html($row->insurance_expiry_date_shamsi) . '</div>';
            echo '</div>';
            echo '<span class="sc-dw-status sc-dw-status-danger">منقضی شده</span>';
            echo '</a>';
            echo '</li>';
        }
        echo '</ul>';
    }
    echo '</div>';
}

/* ====================================================================
 * 6) هشدارهای غیبت بیش از حد کاربران (هشدارهای تایید‌نشده اولویت دارند)
 * ================================================================= */
function sc_dw_render_absence_alerts() {
    if (!function_exists('sc_get_admin_system_alert_notifications')) {
        echo '<div class="sc-dw-card sc-dw-card-orange">';
        sc_dw_render_empty('سیستم هشدارها در دسترس نیست.');
        echo '</div>';
        return;
    }

    $current_user_id = get_current_user_id();

    // اولویت با هشدارهای خوانده نشده (تایید نشده)
    $unread = sc_get_admin_system_alert_notifications($current_user_id, [
        'kind'     => 'absence_limit',
        'status'   => 'unread',
        'per_page' => 5,
        'offset'   => 0,
    ]);
    $unread = is_array($unread) ? $unread : [];

    $rows = $unread;

    // اگر هنوز ۵ تا نشدند، خوانده‌شده‌ها را هم اضافه کن
    if (count($rows) < 5) {
        $need = 5 - count($rows);
        $read = sc_get_admin_system_alert_notifications($current_user_id, [
            'kind'     => 'absence_limit',
            'status'   => 'read',
            'per_page' => $need,
            'offset'   => 0,
        ]);
        if (is_array($read)) {
            $rows = array_merge($rows, $read);
        }
    }

    $total_unread = (int) sc_get_admin_system_alert_notifications($current_user_id, [
        'kind'       => 'absence_limit',
        'status'     => 'unread',
        'count_only' => true,
    ]);
    $total_all = (int) sc_get_admin_system_alert_notifications($current_user_id, [
        'kind'       => 'absence_limit',
        'count_only' => true,
    ]);

    $list_url = sc_dw_admin_url(['page' => 'sc-user-alerts', 'filter_kind' => 'absence_limit']);
    $limit    = function_exists('sc_get_user_alert_absence_limit') ? sc_get_user_alert_absence_limit() : 3;

    echo '<div class="sc-dw-card sc-dw-card-orange">';
    echo '<div class="sc-dw-card-head">';
    echo '<span class="sc-dw-badge-group">';
    if ($total_unread > 0) {
        echo '<span class="sc-dw-badge sc-dw-badge-red" title="تایید نشده">' . esc_html(number_format_i18n($total_unread)) . ' تایید نشده</span>';
    }
    echo '<span class="sc-dw-badge sc-dw-badge-neutral">' . esc_html(number_format_i18n($total_all)) . ' کل</span>';
    echo '</span>';
    echo '<a class="sc-dw-link-btn" href="' . esc_url($list_url) . '">مشاهده همه</a>';
    echo '</div>';

    echo '<div class="sc-dw-stat-row">حد مجاز غیبت: <strong>' . esc_html(number_format_i18n($limit)) . '</strong></div>';

    if (empty($rows)) {
        sc_dw_render_empty('✅ هشدار غیبت بیش از حدی وجود ندارد.', true);
    } else {
        echo '<ul class="sc-dw-list">';
        foreach ($rows as $row) {
            $is_unread = empty($row->is_read);
            $confirm_url = wp_nonce_url(
                sc_dw_admin_url(['page' => 'sc-user-alerts', 'sc_alert_action' => 'confirm', 'notification_id' => (int) $row->id]),
                'sc_confirm_alert_' . (int) $row->id
            );
            $created = function_exists('sc_date_shamsi') ? sc_date_shamsi($row->created_at, 'Y/m/d - H:i') : $row->created_at;
            $item_class = $is_unread ? 'sc-dw-list-item is-unread' : 'sc-dw-list-item';
            echo '<li class="' . esc_attr($item_class) . '">';
            echo '<a class="sc-dw-list-link" href="' . esc_url($list_url) . '">';
            echo '<div class="sc-dw-list-main">';
            echo '<div class="sc-dw-list-title">';
            if ($is_unread) {
                echo '<span class="sc-dw-dot" aria-hidden="true"></span> ';
            }
            echo esc_html($row->content);
            echo '</div>';
            echo '<div class="sc-dw-list-meta">📅 ' . esc_html($created) . '</div>';
            echo '</div>';
            if ($is_unread) {
            } else {
                echo '<span class="sc-dw-status sc-dw-status-success">تایید شده</span>';
            }
            echo '</a>';
            echo '</li>';
        }
        echo '</ul>';
    }
    echo '</div>';
}

/* ====================================================================
 * 7) درخواست‌های برداشت مربی‌ها در انتظار بررسی/پرداخت
 * ================================================================= */
function sc_dw_render_coach_withdrawals() {
    global $wpdb;
    $withdrawals_table = $wpdb->prefix . 'sc_coach_withdrawal_requests';
    $coaches_table     = $wpdb->prefix . 'sc_coaches';

    $table_exists = (bool) $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $withdrawals_table));
    if (!$table_exists) {
        echo '<div class="sc-dw-card sc-dw-card-purple">';
        sc_dw_render_empty('جدول درخواست‌های برداشت در دسترس نیست.');
        echo '</div>';
        return;
    }

    $rows = $wpdb->get_results(
        "SELECT w.id, w.coach_id, w.amount, w.status, w.created_at,
                c.first_name, c.last_name
         FROM $withdrawals_table w
         LEFT JOIN $coaches_table c ON c.id = w.coach_id
         WHERE w.status IN ('pending', 'approved')
         ORDER BY CASE w.status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END,
                  w.created_at DESC
         LIMIT 5"
    );

    $count_pending  = (int) $wpdb->get_var("SELECT COUNT(*) FROM $withdrawals_table WHERE status = 'pending'");
    $count_approved = (int) $wpdb->get_var("SELECT COUNT(*) FROM $withdrawals_table WHERE status = 'approved'");
    $sum_pending    = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount), 0) FROM $withdrawals_table WHERE status IN ('pending','approved')");

    $list_url = sc_dw_admin_url(['page' => 'sc-coach-withdrawals']);

    echo '<div class="sc-dw-card sc-dw-card-purple">';
    echo '<div class="sc-dw-card-head">';
    echo '<span class="sc-dw-badge-group">';
    if ($count_pending > 0) {
        echo '<span class="sc-dw-badge sc-dw-badge-orange" title="در انتظار بررسی">' . esc_html(number_format_i18n($count_pending)) . ' در انتظار</span>';
    }
    if ($count_approved > 0) {
        echo '<span class="sc-dw-badge sc-dw-badge-blue" title="در انتظار پرداخت">' . esc_html(number_format_i18n($count_approved)) . ' پرداخت نشده</span>';
    }
    echo '</span>';
    echo '<a class="sc-dw-link-btn" href="' . esc_url($list_url) . '">مشاهده همه</a>';
    echo '</div>';

    if ($sum_pending > 0) {
        $sum_fmt = function_exists('sc_format_amount_display') ? sc_format_amount_display($sum_pending) : number_format($sum_pending);
        echo '<div class="sc-dw-stat-row">مجموع مبالغ در دست رسیدگی: <strong>' . esc_html($sum_fmt) . ' تومان</strong></div>';
    }

    if (empty($rows)) {
        sc_dw_render_empty('✅ درخواست برداشتی برای رسیدگی وجود ندارد.', true);
    } else {
        echo '<ul class="sc-dw-list">';
        foreach ($rows as $row) {
            $coach_name = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
            if ($coach_name === '') {
                $coach_name = 'مربی #' . (int) $row->coach_id;
            }
            $amount_fmt  = function_exists('sc_format_amount_display') ? sc_format_amount_display($row->amount) : number_format($row->amount);
            $created     = function_exists('sc_date_shamsi') ? sc_date_shamsi($row->created_at, 'Y/m/d - H:i') : $row->created_at;
            $status_text = $row->status === 'pending' ? 'در انتظار تایید' : 'در انتظار پرداخت';
            $status_cls  = $row->status === 'pending' ? 'sc-dw-status-warning' : 'sc-dw-status-info';
            echo '<li class="sc-dw-list-item">';
            echo '<a class="sc-dw-list-link" href="' . esc_url($list_url) . '">';
            echo '<div class="sc-dw-list-main">';
            echo '<div class="sc-dw-list-title">🧑‍🏫 ' . esc_html($coach_name) . ' - ' . esc_html($amount_fmt) . ' تومان</div>';
            echo '<div class="sc-dw-list-meta">📅 ' . esc_html($created) . '</div>';
            echo '</div>';
            echo '<span class="sc-dw-status ' . esc_attr($status_cls) . '">' . esc_html($status_text) . '</span>';
            echo '</a>';
            echo '</li>';
        }
        echo '</ul>';
    }
    echo '</div>';
}

/* ====================================================================
 * 8) آمار کدهای تخفیف استفاده شده (فروشگاه و دوره به‌صورت مجزا)
 * ================================================================= */
function sc_dw_render_discount_codes_usage() {
    global $wpdb;

    /* --- بخش دوره/رویداد: از جدول sc_discount_code_usages --- */
    $usages_table   = $wpdb->prefix . 'sc_discount_code_usages';
    $invoices_table = $wpdb->prefix . 'sc_invoices';

    $sc_usage_exists = (bool) $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $usages_table));

    $sc_total_count   = 0;
    $sc_total_amount  = 0.0;
    $sc_breakdown     = ['course' => ['count' => 0, 'amount' => 0.0], 'event' => ['count' => 0, 'amount' => 0.0], 'other' => ['count' => 0, 'amount' => 0.0]];

    if ($sc_usage_exists) {
        $sc_total_count  = (int) $wpdb->get_var("SELECT COUNT(*) FROM $usages_table");
        $sc_total_amount = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount_saved), 0) FROM $usages_table");

        // تفکیک بر اساس دوره/رویداد
        $rows = $wpdb->get_results(
            "SELECT
                CASE
                    WHEN i.course_id > 0 THEN 'course'
                    WHEN i.event_id > 0 THEN 'event'
                    ELSE 'other'
                END AS ctx,
                COUNT(*) AS cnt,
                COALESCE(SUM(u.amount_saved), 0) AS total_amount
             FROM $usages_table u
             LEFT JOIN $invoices_table i ON i.id = u.invoice_id
             GROUP BY ctx"
        );
        foreach ((array) $rows as $r) {
            $key = isset($r->ctx) && isset($sc_breakdown[$r->ctx]) ? $r->ctx : 'other';
            $sc_breakdown[$key]['count']  = (int) $r->cnt;
            $sc_breakdown[$key]['amount'] = (float) $r->total_amount;
        }
    }

    /* --- بخش فروشگاه (ووکامرس): از جدول wc_orders و coupon items --- */
    $orders_table       = $wpdb->prefix . 'wc_orders';
    $order_items_table  = $wpdb->prefix . 'woocommerce_order_items';
    $order_itemmeta_tbl = $wpdb->prefix . 'woocommerce_order_itemmeta';

    $wc_count  = 0;
    $wc_amount = 0.0;

    $wc_orders_exists = (bool) $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $orders_table));
    $wc_items_exists  = (bool) $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $order_items_table));

    if ($wc_orders_exists && $wc_items_exists) {
        $wc_count = (int) $wpdb->get_var(
            "SELECT COUNT(*)
             FROM $order_items_table oi
             INNER JOIN $orders_table o ON o.id = oi.order_id
             WHERE oi.order_item_type = 'coupon'
               AND o.status IN ('wc-completed', 'wc-processing')"
        );

        $wc_amount = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(CAST(im.meta_value AS DECIMAL(15,2))), 0)
             FROM $order_items_table oi
             INNER JOIN $orders_table o ON o.id = oi.order_id
             INNER JOIN $order_itemmeta_tbl im ON im.order_item_id = oi.order_item_id AND im.meta_key = 'discount_amount'
             WHERE oi.order_item_type = 'coupon'
               AND o.status IN ('wc-completed', 'wc-processing')"
        );
    }

    $codes_list_url = sc_dw_admin_url(['page' => 'sc-discount-codes']);
    $wc_coupons_url = admin_url('edit.php?post_type=shop_coupon');

    $fmt = function ($v) {
        return function_exists('sc_format_amount_display') ? sc_format_amount_display($v) : number_format((float) $v);
    };

    echo '<div class="sc-dw-card sc-dw-card-green">';

    // ---- دوره/رویداد ----
    echo '<div class="sc-dw-section">';
    echo '<div class="sc-dw-card-head">';
    echo '<span class="sc-dw-section-title">🏫 دوره و رویداد</span>';
    echo '<a class="sc-dw-link-btn" href="' . esc_url($codes_list_url) . '">مدیریت کدهای تخفیف</a>';
    echo '</div>';

    echo '<div class="sc-dw-stats-grid">';
    echo '<div class="sc-dw-stat-card">';
    echo '<span class="sc-dw-stat-label">دفعات استفاده (دوره)</span>';
    echo '<strong class="sc-dw-stat-value">' . esc_html(number_format_i18n($sc_breakdown['course']['count'])) . '</strong>';
    echo '</div>';
    echo '<div class="sc-dw-stat-card">';
    echo '<span class="sc-dw-stat-label">مجموع تخفیف (دوره)</span>';
    echo '<strong class="sc-dw-stat-value">' . esc_html($fmt($sc_breakdown['course']['amount'])) . ' تومان</strong>';
    echo '</div>';
    echo '<div class="sc-dw-stat-card">';
    echo '<span class="sc-dw-stat-label">دفعات استفاده (رویداد)</span>';
    echo '<strong class="sc-dw-stat-value">' . esc_html(number_format_i18n($sc_breakdown['event']['count'])) . '</strong>';
    echo '</div>';
    echo '<div class="sc-dw-stat-card">';
    echo '<span class="sc-dw-stat-label">مجموع تخفیف (رویداد)</span>';
    echo '<strong class="sc-dw-stat-value">' . esc_html($fmt($sc_breakdown['event']['amount'])) . ' تومان</strong>';
    echo '</div>';
    echo '</div>';

    if ($sc_total_count > 0) {
        echo '<div class="sc-dw-stat-row">جمع کل: <strong>' . esc_html(number_format_i18n($sc_total_count)) . '</strong> دفعه استفاده، مجموع تخفیف: <strong>' . esc_html($fmt($sc_total_amount)) . ' تومان</strong></div>';
    }
    echo '</div>'; // section

    // ---- فروشگاه (ووکامرس) ----
    echo '<div class="sc-dw-section sc-dw-section-divider">';
    echo '<div class="sc-dw-card-head">';
    echo '<span class="sc-dw-section-title">🛒 فروشگاه (ووکامرس)</span>';
    echo '<a class="sc-dw-link-btn" href="' . esc_url($wc_coupons_url) . '">مدیریت کوپن‌ها</a>';
    echo '</div>';

    echo '<div class="sc-dw-stats-grid">';
    echo '<div class="sc-dw-stat-card">';
    echo '<span class="sc-dw-stat-label">دفعات استفاده</span>';
    echo '<strong class="sc-dw-stat-value">' . esc_html(number_format_i18n($wc_count)) . '</strong>';
    echo '</div>';
    echo '<div class="sc-dw-stat-card">';
    echo '<span class="sc-dw-stat-label">مجموع تخفیف</span>';
    echo '<strong class="sc-dw-stat-value">' . esc_html($fmt($wc_amount)) . ' تومان</strong>';
    echo '</div>';
    echo '</div>';
    echo '</div>'; // section

    echo '</div>'; // card
}
