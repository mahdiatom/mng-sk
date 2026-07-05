<?php 
if (!defined('ABSPATH')) {
    exit;
}
use HelloTheme\Includes\Script;


/**
 * ============================
 * Admin Menu
 * ============================
 */
add_action('admin_menu', 'sc_register_admin_menu');

function sc_register_admin_menu() {

    if (function_exists('sc_is_license_active') && !sc_is_license_active()) {
        if (function_exists('sc_register_license_only_admin_menu')) {
            sc_register_license_only_admin_menu();
        }
        return;
    }

    /* ================= Dashboard ================= */

    add_menu_page(
        'داشبورد مدیریت',
        'داشبورد مدیریت',
        'manage_options',
        'sc-dashboard',
        'sc_admin_dashboard_page',
        'dashicons-universal-access-alt',
        9
    );

    /* ================= Notifications & SMS (فقط وقتی امکانات پرو فعال است) ================= */
    if (function_exists('sc_is_pro_feature_notifications_enabled') && sc_is_pro_feature_notifications_enabled()) {
        add_menu_page(
            'اطلاعیه‌ها و پیامک',
            'اطلاعیه‌ها و پیامک',
            'manage_options',
            'sc-notifications',
            'sc_admin_notifications_list_page',
            'dashicons-email-alt',
            20
        );
        add_submenu_page(
            'sc-notifications',
            'لیست اطلاعیه‌ها',
            'لیست اطلاعیه‌ها',
            'manage_options',
            'sc-notifications',
            'sc_admin_notifications_list_page'
        );
        add_submenu_page(
            'sc-notifications',
            'ارسال  اطلاعیه و پیامک',
            'افزودن اطلاعیه و پیامک',
            'manage_options',
            'sc-add-notification',
            'sc_admin_add_notification_page'

        );

        
    }

    /* ================= اطلاعیه عمومی (بالای هدر) ================= */
    add_menu_page(
        'اطلاعیه عمومی',
        'اطلاعیه عمومی',
        'manage_options',
        'sc-public-announcement',
        'sc_admin_public_announcement_list_page',
        'dashicons-megaphone',
        22
    );
    add_submenu_page(
        'sc-public-announcement',
        'لیست اطلاعیه‌های عمومی',
        'لیست اطلاعیه‌ها',
        'manage_options',
        'sc-public-announcement',
        'sc_admin_public_announcement_list_page'
    );
    add_submenu_page(
        'sc-public-announcement',
        'افزودن اطلاعیه عمومی',
        'افزودن اطلاعیه',
        'manage_options',
        'sc-public-announcement-add',
        'sc_admin_public_announcement_add_page'
    );

    /* ================= Bale Bot ================= */
    add_menu_page(
        'ربات بله',
        'ربات بله',
        'manage_options',
        'sc-bale-bot-messages',
        'sc_admin_bale_bot_messages_list_page',
        'dashicons-format-chat',
        21
    );
    add_submenu_page(
        'sc-bale-bot-messages',
        'لیست پیام‌های ربات',
        'لیست پیام‌ها',
        'manage_options',
        'sc-bale-bot-messages',
        'sc_admin_bale_bot_messages_list_page'
    );
    add_submenu_page(
        'sc-bale-bot-messages',
        'ارسال پیام ربات',
        'ارسال پیام',
        'manage_options',
        'sc-bale-bot-send',
        'sc_admin_bale_bot_send_page'
    );

    if (function_exists('sc_is_pro_feature_user_alerts_enabled') && sc_is_pro_feature_user_alerts_enabled()) {
        add_menu_page(
            'هشدارهای کاربر',
            'هشدارهای کاربر',
            'manage_options',
            'sc-user-alerts',
            'sc_admin_user_alerts_page',
            'dashicons-warning',
            23
        );
    }

    if (function_exists('sc_is_pro_feature_users_export_enabled') && sc_is_pro_feature_users_export_enabled()) {
        add_menu_page(
            'خروجی اطلاعات کاربران',
            'خروجی اطلاعات کاربران',
            'manage_options',
            'sc-users-info-export',
            'sc_admin_users_info_export_page',
            'dashicons-media-spreadsheet',
            27
        );
        add_submenu_page(
            'sc-users-info-export',
            'خروجی اطلاعات کاربران',
            'خروجی اطلاعات کاربران',
            'manage_options',
            'sc-users-info-export',
            'sc_admin_users_info_export_page'
        );
        add_submenu_page(
            'sc-users-info-export',
            'تعریف قالب خروجی',
            'تعریف قالب خروجی',
            'manage_options',
            'sc-users-export-templates',
            'sc_admin_users_export_templates_page'
        );
    }

    if (function_exists('sc_is_pro_feature_bulk_actions_enabled') && sc_is_pro_feature_bulk_actions_enabled()) {
        add_menu_page(
            'کار های دست جمعی',
            'کار های دست جمعی',
            'manage_options',
            'sc-bulk-actions',
            'sc_admin_bulk_actions_page',
            'dashicons-update',
            28
        );
    }

    if (function_exists('sc_is_pro_feature_certificates_enabled') && sc_is_pro_feature_certificates_enabled()) {
        add_menu_page(
            'گواهینامه‌ها',
            'گواهینامه‌ها',
            'manage_options',
            'sc-certificates-issue',
            'sc_admin_certificates_issue_page',
            'dashicons-awards',
            19
        );
        add_submenu_page(
            'sc-certificates-issue',
            'صدور گواهینامه',
            'صدور گواهینامه',
            'manage_options',
            'sc-certificates-issue',
            'sc_admin_certificates_issue_page'
        );
        add_submenu_page(
            'sc-certificates-issue',
            'گواهینامه ها',
            'گواهینامه ها',
            'manage_options',
            'sc-certificates-list',
            'sc_admin_certificates_list_page'
        );
        add_submenu_page(
            'sc-certificates-issue',
            'تعریف قالب گواهینامه',
            'تعریف قالب گواهینامه',
            'manage_options',
            'sc-certificates-templates',
            'sc_admin_certificates_templates_page'
        );
    }

    /* ================= Members ================= */

    add_menu_page(
        'بازیکنان ',
        'بازیکنان',
        'manage_options',
        'sc-members',
        'sc_admin_members_list_page',
        'dashicons-groups',
        15
    );



    $list_member_sufix = add_submenu_page(
        'sc-members',
        'لیست بازیکنان',
        'لیست بازیکنان',
        'manage_options',
        'sc-members',
        'sc_admin_members_list_page'
    );
    add_action('load-' . $list_member_sufix, 'sc_members_screen_option');

    $add_member_sufix = add_submenu_page(
        'sc-members',
        'افزودن بازیکن',
        'افزودن بازیکن',
        'manage_options',
        'sc-add-member',
        'sc_admin_add_member_page'
    );

    add_submenu_page(
        null, // hidden menu
        'مشاهده اطلاعات بازیکن',
        'مشاهده اطلاعات بازیکن',
        'manage_options',
        'sc-view-member',
        'sc_admin_view_member_page'
    );


    if (function_exists('sc_is_pro_feature_attendance_enabled') && sc_is_pro_feature_attendance_enabled()) {
        add_menu_page(
            'ثبت حضور و غیاب',
            'ثبت حضور و غیاب',
            'sc_manage_attendance_or_admin', // capability سفارشی برای مربی و مدیر
            'sc-attendance-add',
            'sc_admin_attendance_add_page',
            'dashicons-insert-after',
            '18'
        );

        add_submenu_page(
            'sc-attendance-add',
            'لیست حضور و غیاب',
            'لیست حضور و غیاب',
            'sc_manage_attendance_or_admin', // capability سفارشی برای مربی و مدیر
            'sc-attendance-list',
            'sc_admin_attendance_list_page'
        );

        add_submenu_page(
            'sc-attendance-add',
            'گزارش حضور بازیکن',
            'گزارش حضور بازیکن',
            'sc_manage_attendance_or_admin',
            'sc-attendance-report',
            'sc_admin_attendance_report_page'
        );

        add_submenu_page(
            'sc-attendance-add',
            'تعطیلی بازهٔ جلسه',
            'تعطیلی بازهٔ جلسه',
            'sc_manage_attendance_or_admin',
            'sc-attendance-session-cancellations',
            'sc_admin_attendance_session_cancellations_page'
        );
    }

    /* ================= منوهای فقط مربی (نه مدیر کل و نه مدیر باشگاه): دستمزد، افتخارات، اطلاعیه، دوره‌های من، بازیکن‌های من، اطلاعات من، تیکت ================= */
    $is_coach_only = current_user_can('coach') && !current_user_can('administrator') && !current_user_can('club_coach');
    if ($is_coach_only) {

    /* ================= Coach Salary & Wallet (for coaches) - فقط وقتی امکانات پرو کیف پول مربیان و دستمزد فعال است ================= */
    if (function_exists('sc_is_pro_feature_coaches_wallet_salary_enabled') && sc_is_pro_feature_coaches_wallet_salary_enabled()) {
        add_menu_page(
            'دستمزد و کیف پول',
            'دستمزد و کیف پول',
            'sc_view_coach_salary',
            'sc-coach-salary',
            'sc_admin_coach_salary_page',
            'dashicons-money-alt',
            28.5
        );
        
        add_submenu_page(
            'sc-coach-salary',
            'لیست دستمزد',
            'لیست دستمزد',
            'sc_view_coach_salary',
            'sc-coach-salary',
            'sc_admin_coach_salary_page'
        );
        
        add_submenu_page(
            'sc-coach-salary',
            'کیف پول',
            'کیف پول',
            'sc_view_coach_salary',
            'sc-coach-wallet',
            'sc_admin_coach_wallet_page'
        );
        
        add_submenu_page(
            'sc-coach-salary',
            'درخواست‌های برداشت',
            'درخواست‌های برداشت',
            'sc_view_coach_salary',
            'sc-coach-withdrawals',
            'sc_admin_coach_withdrawals_page'
        );
    }

    if (function_exists('sc_is_pro_feature_honors_enabled') && sc_is_pro_feature_honors_enabled()) {
        add_menu_page(
            'افتخارات من',
            'افتخارات من',
            'sc_view_coach_salary',
            'sc-coach-honors',
            'sc_admin_coach_honors_page',
            'dashicons-awards',
            18
        );
    }

    /* ================= Coach Notifications (for coaches) - فقط وقتی امکانات پرو فعال است ================= */
    if (function_exists('sc_is_pro_feature_notifications_enabled') && sc_is_pro_feature_notifications_enabled()) {
        add_menu_page(
            'اطلاعیه‌ها',
            'اطلاعیه‌ها',
            'sc_view_coach_salary',
            'sc-coach-notifications',
            'sc_admin_coach_my_notifications_page',
            'dashicons-email-alt',
            16
        );
        add_submenu_page(
            'sc-coach-notifications',
            'اطلاعیه‌های من',
            'اطلاعیه‌های من',
            'sc_view_coach_salary',
            'sc-coach-notifications',
            'sc_admin_coach_my_notifications_page'
        );
        add_submenu_page(
            'sc-coach-notifications',
            'لیست اطلاعیه‌ها',
            'لیست اطلاعیه‌ها',
            'sc_view_coach_salary',
            'sc-coach-notifications-list',
            'sc_admin_coach_notifications_list_page'
        );
        add_submenu_page(
            'sc-coach-notifications',
            'افزودن اطلاعیه',
            'افزودن اطلاعیه',
            'sc_view_coach_salary',
            'sc-coach-add-notification',
            'sc_admin_coach_add_notification_page'
        );

        add_action('admin_menu', 'sc_coach_notifications_menu_badge', 999);
    }

    /* ================= Coach: دوره‌های من، بازیکن‌های من، اطلاعات من ================= */
    if (function_exists('sc_is_pro_feature_courses_enabled') && sc_is_pro_feature_courses_enabled()) {
        add_menu_page(
            'دوره‌های من',
            'دوره‌های من',
            'sc_view_coach_salary',
            'sc-coach-my-courses',
            'sc_admin_coach_my_courses_page',
            'dashicons-welcome-learn-more',
            13
        );
        if (current_user_can('sc_view_coach_salary')) {
            add_submenu_page(
                'sc-coach-my-courses',
                'برنامه هفتگی من',
                'برنامه هفتگی من',
                'read',
                'sc-coach-weekly-schedule',
                'sc_render_coach_weekly_schedule_page'
            );
            add_menu_page(
                'کلاس‌های خصوصی من',
                'کلاس‌های خصوصی من',
                'read',
                'sc-coach-private-classes',
                'sc_render_private_bookings_admin_page',
                'dashicons-calendar-alt',
                17
            );
            add_submenu_page(
                'sc-coach-private-classes',
                'لیست جلسات خصوصی',
                'لیست جلسات خصوصی',
                'read',
                'sc-coach-private-classes',
                'sc_render_private_bookings_admin_page'
            );
            // add_submenu_page(
            //     'sc-coach-my-courses',
            //     'کلاس‌های خصوصی من',
            //     'کلاس‌های خصوصی من',
            //     'sc_view_coach_salary',
            //     'sc-private-bookings-list',
            //     'sc_render_private_bookings_admin_page'
            // );
        }
    }

    add_menu_page(
        'بازیکن‌های من',
        'بازیکن‌های من',
        'sc_view_coach_salary',
        'sc-coach-my-players',
        'sc_admin_coach_my_players_page',
        'dashicons-groups',
        14
    );
    add_menu_page(
        'اطلاعات من',
        'اطلاعات من',
        'sc_view_coach_salary',
        'sc-coach-my-profile',
        'sc_admin_coach_my_profile_page',
        'dashicons-admin-users',
        28.8
    );
    add_submenu_page(
        'sc-coach-my-players',
        'بازیکن‌های من',
        'بازیکن‌های من',
        'sc_view_coach_salary',
        'sc-coach-my-players',
        'sc_admin_coach_my_players_page'
    );

    if (function_exists('sc_is_pro_feature_support_tickets_enabled') && sc_is_pro_feature_support_tickets_enabled()) {
        add_menu_page(
            'تیکت پشتیبانی',
            'تیکت پشتیبانی',
            'sc_view_coach_salary',
            'sc-coach-support-tickets',
            'sc_admin_coach_support_tickets_list_page',
            'dashicons-tickets-alt',
            28.85
        );
        add_submenu_page(
            null,
            'مشاهده تیکت',
            'مشاهده تیکت',
            'sc_view_coach_salary',
            'sc-coach-support-ticket-view',
            'sc_admin_coach_support_ticket_view_page'
        );
        add_submenu_page(
            null,
            'ارسال تیکت جدید',
            'ارسال تیکت جدید',
            'sc_view_coach_salary',
            'sc-coach-support-ticket-new',
            'sc_admin_coach_support_ticket_new_page'
        );
        add_action('admin_menu', 'sc_coach_support_tickets_menu_badge', 999);
    }

    if (function_exists('sc_is_pro_feature_private_notes_enabled') && sc_is_pro_feature_private_notes_enabled()) {
        add_menu_page(
            'یادداشت‌های خصوصی',
            'یادداشت‌های خصوصی',
            'sc_view_coach_salary',
            'sc-coach-private-notes',
            'sc_admin_coach_private_notes_list_page',
            'dashicons-media-text',
            28.86
        );
        add_submenu_page(
            'sc-coach-private-notes',
            'لیست یادداشت‌ها',
            'لیست یادداشت‌ها',
            'sc_view_coach_salary',
            'sc-coach-private-notes',
            'sc_admin_coach_private_notes_list_page'
        );
        add_submenu_page(
            'sc-coach-private-notes',
            'افزودن یادداشت',
            'افزودن یادداشت',
            'sc_view_coach_salary',
            'sc-coach-add-private-note',
            'sc_admin_coach_private_notes_add_page'
        );
    }
    $pro_feature_surveys = (int) sc_get_setting('pro_feature_surveys', 0);
    if($pro_feature_surveys){

    add_menu_page(
        'نظرسنجی‌ها',
        'نظرسنجی‌ها',
        'sc_view_coach_salary',
        'sc-coach-surveys',
        'sc_admin_coach_surveys_page',
        'dashicons-forms',
        19
    );

    } // پایان منوهای فقط مربی ($is_coach_only)

    /* ================= Surveys (مدیر / مدیر باشگاه) ================= */
    add_menu_page(
        'نظرسنجی',
        'نظرسنجی',
        'manage_options',
        'sc-surveys',
        'sc_admin_surveys_list_page',
        'dashicons-forms',
        21
    );
    add_submenu_page('sc-surveys', 'لیست نظرسنجی‌ها', 'لیست نظرسنجی‌ها', 'manage_options', 'sc-surveys', 'sc_admin_surveys_list_page');
    add_submenu_page('sc-surveys', 'افزودن نظرسنجی', 'افزودن نظرسنجی', 'manage_options', 'sc-add-survey', 'sc_admin_survey_add_page');
    add_submenu_page('sc-surveys', 'داده‌های نظرسنجی', 'داده‌های نظرسنجی', 'manage_options', 'sc-survey-data', 'sc_admin_survey_data_page');
    add_submenu_page('sc-surveys', 'آمار نظرسنجی', 'آمار نظرسنجی', 'manage_options', 'sc-survey-stats', 'sc_admin_survey_stats_page');
    }
    /* ================= Courses ================= */

    $list_courses_sufix = null;
    $add_course_sufix = null;
    if (function_exists('sc_is_pro_feature_courses_enabled') && sc_is_pro_feature_courses_enabled()) {
        add_menu_page(
            'دوره‌ها',
            'دوره‌ها',
            'manage_options',
            'sc-courses',
            'sc_admin_courses_list_page',
            'dashicons-welcome-learn-more',
            12
        );

        $list_courses_sufix = add_submenu_page(
            'sc-courses',
            'لیست دوره‌ها',
            'لیست دوره‌ها',
            'manage_options',
            'sc-courses',
            'sc_admin_courses_list_page'
        );
        add_action('load-' . $list_courses_sufix, 'sc_courses_screen_option');

        $add_course_sufix = add_submenu_page(
            'sc-courses',
            'افزودن دوره',
            'افزودن دوره',
            'manage_options',
            'sc-add-course',
            'sc_admin_add_course_page'
        );
        if (current_user_can('manage_options') || current_user_can('club_coach')) {
            add_submenu_page(
                'sc-courses',
                'کلاس‌های خصوصی',
                'کلاس‌های خصوصی',
                'read',
                'sc-private-bookings-list',
                'sc_render_private_bookings_admin_page'
            );
            add_submenu_page(
                'sc-setting',
                'کلاس‌های خصوصی',
                'کلاس‌های خصوصی',
                'read',
                'sc-private-bookings-list',
                'sc_render_private_bookings_admin_page'
            );
            if (function_exists('sc_is_private_booking_admin_approval_mode') && sc_is_private_booking_admin_approval_mode()) {
                add_menu_page(
                    'رزرو کلاس خصوصی',
                    'رزرو کلاس خصوصی',
                    'read',
                    'sc-private-booking-requests',
                    'sc_render_private_booking_requests_page',
                    'dashicons-calendar-alt',
                    15.5
                );
                add_submenu_page(
                    'sc-private-booking-requests',
                    'لیست رزروها',
                    'لیست رزروها',
                    'read',
                    'sc-private-booking-requests',
                    'sc_render_private_booking_requests_page'
                );
                add_submenu_page(
                    'sc-private-booking-requests',
                    'ثبت‌نام کلاس خصوصی',
                    'ثبت‌نام کلاس خصوصی',
                    'read',
                    'sc-private-booking-form',
                    'sc_render_private_booking_form_page'
                );
                add_action('admin_menu', 'sc_private_booking_requests_menu_badge', 999);
            } else {
                add_menu_page(
                    'ثبت‌نام کلاس خصوصی',
                    'ثبت‌نام کلاس خصوصی',
                    'read',
                    'sc-private-booking-form',
                    'sc_render_private_booking_form_page',
                    'dashicons-calendar-alt',
                    15.5
                );
            }
        }
    }


    /* ================= Coaches (فقط وقتی امکانات پرو فعال است) ================= */

    if (function_exists('sc_is_pro_feature_coaches_enabled') && sc_is_pro_feature_coaches_enabled()) {
        add_menu_page(
            'مربیان',
            'مربیان',
            'manage_options',
            'sc-coaches',
            'sc_admin_coaches_list_page',
            'dashicons-groups',
            16
        );

        $list_coaches_sufix = add_submenu_page(
            'sc-coaches',
            'لیست مربیان',
            'لیست مربیان',
            'manage_options',
            'sc-coaches',
            'sc_admin_coaches_list_page'
        );
        add_action('load-' . $list_coaches_sufix, 'sc_coaches_screen_option');

        $add_coach_sufix = add_submenu_page(
            'sc-coaches',
            'افزودن مربی',
            'افزودن مربی',
            'manage_options',
            'sc-add-coach',
            'sc_admin_add_coach_page'
        );
    }

    /* ================= Events ================= */

    $list_events_sufix = null;
    $add_event_sufix = null;
    $list_event_registrations_sufix = null;
    if (function_exists('sc_is_pro_feature_events_enabled') && sc_is_pro_feature_events_enabled()) {
        add_menu_page(
            'رویدادها',
            'رویدادها ',
            'manage_options',
            'sc-events',
            'sc_admin_events_list_page',
            'dashicons-calendar-alt',
            13
        );

        $list_events_sufix = add_submenu_page(
            'sc-events',
            'لیست رویداد ',
            'لیست رویداد ',
            'manage_options',
            'sc-events',
            'sc_admin_events_list_page'
        );

        $add_event_sufix = add_submenu_page(
            'sc-events',
            'ثبت رویداد ',
            'ثبت رویداد ',
            'manage_options',
            'sc-add-event',
            'sc_admin_add_event_page'
        );

        $list_event_registrations_sufix = add_submenu_page(
            'sc-events',
            'ثبت‌نامی‌های رویداد',
            'ثبت‌نامی‌های رویداد',
            'manage_options',
            'sc-event-registrations',
            'sc_admin_event_registrations_list_page'
        );
    }

    /* ================= Private Notes ================= */
    if (function_exists('sc_is_pro_feature_private_notes_enabled') && sc_is_pro_feature_private_notes_enabled()) {
        add_menu_page(
            'یادداشت‌های خصوصی',
            'یادداشت‌های خصوصی',
            'manage_options',
            'sc-private-notes',
            'sc_admin_private_notes_list_page',
            'dashicons-media-text',
            22
        );
        add_submenu_page(
            'sc-private-notes',
            'لیست یادداشت‌ها',
            'لیست یادداشت‌ها',
            'manage_options',
            'sc-private-notes',
            'sc_admin_private_notes_list_page'
        );
        add_submenu_page(
            'sc-private-notes',
            'افزودن یادداشت',
            'افزودن یادداشت',
            'manage_options',
            'sc-add-private-note',
            'sc_admin_private_notes_add_page'
        );
        add_submenu_page(
            null,
            'جزئیات یادداشت خصوصی',
            'جزئیات یادداشت خصوصی',
            'read',
            'sc-private-notes-view',
            'sc_admin_private_notes_view_page'
        );
    }

    /* ================= Support Tickets ================= */
    $list_support_tickets_sufix = null;
    if (function_exists('sc_is_pro_feature_support_tickets_enabled') && sc_is_pro_feature_support_tickets_enabled()) {
        add_menu_page(
            'تیکت پشتیبانی',
            'تیکت پشتیبانی',
            'sc_club_support_tickets',
            'sc-support-tickets',
            'sc_admin_support_tickets_list_page',
            'dashicons-tickets-alt',
            21
        );
        $list_support_tickets_sufix = add_submenu_page(
            'sc-support-tickets',
            'لیست تیکت‌ها',
            'لیست تیکت‌ها',
            'sc_club_support_tickets',
            'sc-support-tickets',
            'sc_admin_support_tickets_list_page'
        );

        add_action('load-' . $list_support_tickets_sufix, 'sc_support_tickets_screen_option');
        add_submenu_page(
            null,
            'مشاهده تیکت',
            'مشاهده تیکت',
            'sc_club_support_tickets',
            'sc-support-ticket-view',
            'sc_admin_support_ticket_view_page'
        );
        add_submenu_page(
            'sc-support-tickets',
            'ارسال تیکت جدید',
            'ارسال تیکت جدید',
            'sc_club_support_tickets',
            'sc-support-ticket-new',
            'sc_admin_support_ticket_new_page'
        );
        add_action('admin_menu', 'sc_admin_support_tickets_menu_badge', 999);
    }

    /* ================= Finance ================= */

    $list_invoices_sufix = null;
    $add_invoice_sufix = null;
    $list_expenses_sufix = null;
    $add_expense_sufix = null;
    if (function_exists('sc_is_pro_feature_invoices_enabled') && sc_is_pro_feature_invoices_enabled()) {
        add_menu_page(
            'صورت حساب‌ها',
            'صورت حساب‌ها',
            'manage_options',
            'sc-invoices',
            'sc_admin_invoices_list_page',
            'dashicons-money-alt',
            30
        );

        $list_invoices_sufix = add_submenu_page(
            'sc-invoices',
            'لیست صورت حساب‌ها',
            'لیست صورت حساب‌ها',
            'manage_options',
            'sc-invoices',
            'sc_admin_invoices_list_page'
        );

        $add_invoice_sufix = add_submenu_page(
            'sc-invoices',
            'ایجاد صورت حساب',
            'ایجاد صورت حساب',
            'manage_options',
            'sc-add-invoice',
            'sc_admin_add_invoice_page'
        );

        $list_expenses_sufix = add_submenu_page(
            'sc-invoices',
            'لیست هزینه‌ها',
            'لیست هزینه‌ها',
            'manage_options',
            'sc-expenses',
            'sc_admin_expenses_list_page'
        );

        $add_expense_sufix = add_submenu_page(
            'sc-invoices',
            'ثبت هزینه',
            'ثبت هزینه',
            'manage_options',
            'sc-add-expense',
            'sc_admin_add_expense_page'
        );

        add_submenu_page(
            'sc-invoices',
            'کدهای تخفیف',
            'کدهای تخفیف',
            'manage_options',
            'sc-discount-codes',
            'sc_admin_discount_codes_list_page'
        );

        add_submenu_page(
            'sc-invoices',
            'افزودن کد تخفیف',
            'افزودن کد تخفیف',
            'manage_options',
            'sc-add-discount-code',
            'sc_admin_discount_code_edit_page'
        );
    }

    /* ================= Wallet - کیف پول بازیکنان (فقط وقتی امکانات پرو فعال است) ================= */

    if (function_exists('sc_is_pro_feature_players_wallet_enabled') && sc_is_pro_feature_players_wallet_enabled()) {
        add_menu_page(
            'مدیریت کیف پول',
            'مدیریت کیف پول',
            'manage_options',
            'sc-wallet',
            'sc_admin_wallet_list_page',
            'dashicons-database-view',
            25
        );

        $wallet_list_sufix = add_submenu_page(
            'sc-wallet',
            'لیست تراکنش‌ها',
            'لیست تراکنش‌ها',
            'manage_options',
            'sc-wallet',
            'sc_admin_wallet_list_page'
        );

        $wallet_charge_sufix = add_submenu_page(
            'sc-wallet',
            'شارژ کیف پول',
            'شارژ کیف پول',
            'manage_options',
            'sc-wallet-charge',
            'sc_admin_wallet_charge_page'
        );

        $wallet_deduct_sufix = add_submenu_page(
            'sc-wallet',
            'کاهش کیف پول',
            'کاهش کیف پول',
            'manage_options',
            'sc-wallet-deduct',
            'sc_admin_wallet_deduct_page'
        );

        $wallet_manage_sufix = add_submenu_page(
            'sc-wallet',
            'مدیریت شارژ',
            'مدیریت شارژ',
            'manage_options',
            'sc-wallet-manage',
            'sc_admin_wallet_manage_page'
        );
    }

    /* ================= Honors ================= */

    $list_honors_sufix = null;
    if (function_exists('sc_is_pro_feature_honors_enabled') && sc_is_pro_feature_honors_enabled()) {
        add_menu_page(
            'افتخارات',
            'افتخارات',
            'manage_options',
            'sc-honors',
            'sc_admin_honors_list_page',
            'dashicons-awards',
            14
        );

        $list_honors_sufix = add_submenu_page(
            'sc-honors',
            'لیست افتخارات',
            'لیست افتخارات',
            'manage_options',
            'sc-honors',
            'sc_admin_honors_list_page'
        );
        add_action('load-' . $list_honors_sufix, 'sc_honors_screen_option');

        add_submenu_page(
            'sc-honors',
            'دسته‌بندی افتخارات',
            'دسته‌بندی افتخارات',
            'manage_options',
            'sc-honor-categories',
            'sc_admin_honor_categories_page'
        );

        add_submenu_page(
            'sc-honors',
            'افزودن افتخار برای بازیکن',
            'افزودن افتخار برای بازیکن',
            'manage_options',
            'sc-add-honor-for-member',
            'sc_admin_add_honor_for_member_page'
        );
    }

    /* ================= Settings ================= */

    $setting_sufix = add_menu_page(
        'تنظیمات مدیریت باشگاه',
        'تنظیمات مدیریت باشگاه',
        'manage_options',
        'sc_setting',
        'sc_setting_callback',
        'dashicons-admin-generic',
        59
        
    );

    add_submenu_page(
        'sc_setting',
        'جریمه',
        'جریمه',
        'manage_options',
        'admin.php?page=sc_setting&tab=penalty'
    );

    add_submenu_page(
        'sc_setting',
        'صورت حساب',
        'صورت حساب',
        'manage_options',
        'admin.php?page=sc_setting&tab=invoice'
    );

    add_submenu_page(
        'sc_setting',
        'پیامک',
        'پیامک',
        'manage_options',
        'admin.php?page=sc_setting&tab=sms'
    );

    add_submenu_page(
        'sc_setting',
        'ورود و عضویت',
        'ورود و عضویت',
        'manage_options',
        'admin.php?page=sc_setting&tab=login_register'
    );

    add_submenu_page(
        'sc_setting',
        'درباره مجموعه',
        'درباره مجموعه',
        'manage_options',
        'admin.php?page=sc_setting&tab=about'
    );
    add_submenu_page(
        'sc_setting',
        'هدر و فوتر',
        'هدر و فوتر',
        'manage_options',
        'admin.php?page=sc_setting&tab=header_footer'
    );
    add_submenu_page(
        'sc_setting',
        'اطلاعات بازیکن',
        'اطلاعات بازیکن',
        'manage_options',
        'admin.php?page=sc_setting&tab=player_info'
    );

    add_submenu_page(
        'sc_setting',
        'کیف پول',
        'کیف پول',
        'manage_options',
        'admin.php?page=sc_setting&tab=wallet'
    );

    add_submenu_page(
        'sc_setting',
        'حضور و غیاب',
        'حضور و غیاب',
        'manage_options',
        'admin.php?page=sc_setting&tab=attendance'
    );

    add_submenu_page(
        'sc_setting',
        'دستمزد مربی',
        'دستمزد مربی',
        'manage_options',
        'admin.php?page=sc_setting&tab=coach_salary'
    );
  

    add_submenu_page(
            'sc_setting',
            'کلاس‌ها',
            'کلاس‌ها',
            'manage_options',
            'admin.php?page=sc_setting&tab=classes'
        );

    /* ================= Coach Management (for admin) - فقط وقتی امکانات پرو کیف پول مربیان و دستمزد فعال است ================= */
    if (function_exists('sc_is_pro_feature_coaches_wallet_salary_enabled') && sc_is_pro_feature_coaches_wallet_salary_enabled()) {
        add_menu_page(
            'مدیریت مربیان',
            'مدیریت مربیان',
            'manage_options',
            'sc-coach-management',
            'sc_admin_coach_management_page',
            'dashicons-groups',
            17
        );
        
        add_submenu_page(
            'sc-coach-management',
            'کیف پول مربیان',
            'کیف پول مربیان',
            'manage_options',
            'sc-coach-management-wallet',
            'sc_admin_coach_management_wallet_page'
        );
        
        add_submenu_page(
            'sc-coach-management',
            'گزارش دستمزد مربیان',
            'گزارش دستمزد',
            'manage_options',
            'sc-coach-management-salary',
            'sc_admin_coach_management_salary_page'
        );
        
        add_submenu_page(
            'sc-coach-management',
            'درخواست‌های برداشت',
            'درخواست‌های برداشت',
            'manage_options',
            'sc-coach-management-withdrawals',
            'sc_admin_coach_management_withdrawals_page'
        );
    }


    /* ================= Reports ================= */

    if (function_exists('sc_is_pro_feature_reports_enabled') && sc_is_pro_feature_reports_enabled()) {
        add_menu_page(
            'گزارشات باشگاه',
            'گزارشات باشگاه',
            'sc_finance_reports_access',
            'sc-reports',
            'sc_report_data',
            'dashicons-chart-area',
            26
        );

        add_submenu_page(
            'sc-reports',
            'کاربران فعال',
            'کاربران فعال',
            'manage_options',
            'sc-reports-active-users',
            'sc_admin_reports_active_users_page'
        );

        add_submenu_page(
            'sc-reports',
            'مالی و حسابداری',
            'مالی و حسابداری',
            'sc_finance_reports_access',
            'sc-reports-income-expenses',
            'sc_admin_reports_income_expenses_page'
        );

        add_submenu_page(
            'sc-reports',
            'تحلیل و هوش تجاری',
            'تحلیل و هوش تجاری',
            'sc_finance_reports_access',
            'sc-reports-bi-analytics',
            'sc_admin_reports_bi_analytics_page'
        );

        if (function_exists('sc_is_pro_feature_coaches_enabled') && sc_is_pro_feature_coaches_enabled()) {
            add_submenu_page(
                'sc-reports',
                'عملکرد مربی',
                'عملکرد مربی',
                'sc_finance_reports_access',
                'sc-reports-coach-performance',
                'sc_admin_reports_coach_performance_page'
            );
        }

        add_submenu_page(
            'sc-reports',
            'بدهکاران',
            'بدهکاران',
            'manage_options',
            'sc-reports-debtors',
            'sc_admin_reports_debtors_page'
        );

        add_submenu_page(
            'sc-reports',
            'برنامه هفتگی',
            'برنامه هفتگی',
            'sc_finance_reports_access',
            'sc-reports-weekly-schedule',
            'sc_admin_reports_weekly_schedule_page'
        );

        if (function_exists('sc_is_pro_feature_sms_enabled') && sc_is_pro_feature_sms_enabled()) {
            add_submenu_page(
                'sc-reports',
                'گزارشات ارسال پیامک',
                'گزارشات ارسال پیامک',
                'manage_options',
                'sc-reports-sms-log',
                'sc_admin_reports_sms_log_page'
            );
            add_action('load-sc-reports_page_sc-reports-sms-log', 'sc_sms_log_screen_options');
        }

        add_submenu_page(
            'sc-reports',
            'لاگ فعالیت',
            'لاگ فعالیت',
            'manage_options',
            'sc-reports-activity-log',
            'sc_admin_activity_log_page'
        );

        if (function_exists('sc_is_pro_feature_attendance_enabled') && sc_is_pro_feature_attendance_enabled()) {
            add_submenu_page(
                'sc-reports',
                'گزارش  حضور و غیاب',
                ' حضور و غیاب',
                'manage_options',
                'sc-attendance-list_report',
                'sc_admin_attendance_list_page'
            );
            add_submenu_page(
                'sc-reports',
                'لاگ تردد های دستگاه ',
                'لاگ های تردد دستگاه حضور و غیاب',
                'manage_options',
                'sc-attendance-logs',
                'sc_admin_attendance_logs'
            );
        }
    }

    if (function_exists('sc_is_pro_feature_permalinks_enabled') && sc_is_pro_feature_permalinks_enabled()) {
        add_menu_page(
            'پیوند های یکتا',
            'پیوند های یکتا',
            'manage_options',
            'options-permalink.php',
            '',
            'dashicons-admin-links',
            60
        );
    }

 /* ================= cate_team and level ================= */

    if (function_exists('sc_is_pro_feature_team_level_enabled') && sc_is_pro_feature_team_level_enabled()) {
        add_menu_page(
            ' دسته بندی تیم و سطج ',
            'تیم و سطح ',
            'manage_options',
            'sc_team',
            'sc_admin_team_categories_page',
            'dashicons-universal-access',
            11
        );

        add_submenu_page(
            'sc_team',
            'دسته تیم بندی',
            'دسته تیم بندی',
            'manage_options',
            'sc_team',
            'sc_admin_team_categories_page'
        );

        add_submenu_page(
            'sc_team',
            'دسته سطح بندی',
            'دسته سطح بندی',
            'manage_options',
            'sc_level',
            'sc_admin_level_categories_page'
        );
    }

 /* ================= chapter ================= */

    if (function_exists('sc_is_pro_feature_chapters_enabled') && sc_is_pro_feature_chapters_enabled()) {
        add_menu_page(
            ' شعبه های باشگاه',
            'شعبه های باشگاه',
            'manage_options',
            'sc_chapter',
            'sc_admin_chapter_page',
            'dashicons-location',
            10
        );
    }

 /* ================= faq ================= */

    if (function_exists('sc_is_pro_feature_faq_enabled') && sc_is_pro_feature_faq_enabled()) {
        add_menu_page(
            ' سوالات متداول ',
            ' سوالات متداول ',
            'manage_options',
            'sc_faq',
            'sc_admin_faq',
            'dashicons-editor-help',
            30
        );
    }

    if (function_exists('sc_is_pro_feature_shop_enabled') && sc_is_pro_feature_shop_enabled()) {
        add_menu_page(
            ' فروشگاه',
            ' فروشگاه',
            'manage_woocommerce',
            'sc_orders',
            'sc_custom_orders',
            'dashicons-cart',
            35
        );
        add_submenu_page(
            'sc_orders',
            'لیست سفارشات',
            'لیست سفارشات',
            'manage_woocommerce',
            'sc_orders',
            'sc_custom_orders'
        );
        add_submenu_page(
            'sc_orders',
            'لیست محصولات',
            'لیست محصولات',
            'manage_woocommerce',
            'sc-products',
            'sc_custom_products'
        );
        add_submenu_page(
            'sc_orders',
            ' کد تخفیف ',
            ' لیست کد تخفیف ' ,
            'manage_woocommerce',
            'edit.php?post_type=shop_coupon',
            ''
        );

        add_submenu_page(
            'sc_orders',
            'تجزیه و تحلیل',
            'تجزیه و تحلیل',
            'manage_woocommerce',
            '/admin.php?page=wc-admin&path=%2Fanalytics%2Foverview',
            ''
        );
    }

    if (function_exists('sc_is_pro_feature_nav_menus_enabled') && sc_is_pro_feature_nav_menus_enabled()) {
        add_menu_page(
            ' فهرست های منو',
            ' فهرست های منو',
            'manage_options',
            'nav-menus.php',
            '',
            "dashicons-list-view",
            31
        );
    }

    add_action('admin_init', 'sc_redirect_legacy_product_list_page');

    /* ================= Load Hooks (همه حفظ شده) ================= */

    add_action('load-' . $add_member_sufix, 'callback_add_member_sufix');
    add_action('load-' . $list_member_sufix, 'procces_table_data');

    if (!empty($add_course_sufix)) {
        add_action('load-' . $add_course_sufix, 'callback_add_course_sufix');
    }
    if (!empty($list_courses_sufix)) {
        add_action('load-' . $list_courses_sufix, 'procces_courses_table_data');
    }

    if (!empty($add_event_sufix)) {
        add_action('load-' . $add_event_sufix, 'callback_add_event_sufix');
    }
    if (!empty($list_events_sufix)) {
        add_action('load-' . $list_events_sufix, 'process_events_table_data');
        add_action('load-' . $list_events_sufix, 'sc_events_screen_options');
    }

    if (!empty($add_invoice_sufix)) {
        add_action('load-' . $add_invoice_sufix, 'callback_add_invoice_sufix');
    }
    if (!empty($list_invoices_sufix)) {
        add_action('load-' . $list_invoices_sufix, 'process_invoices_table_data');
    }
    if (function_exists('sc_is_pro_feature_shop_enabled') && sc_is_pro_feature_shop_enabled()) {
        add_action('load-toplevel_page_sc_orders', 'process_orders_table_data');
        add_action('load-sc_orders_page_sc-products', 'process_products_table_data');
    }

    if (function_exists('sc_is_pro_feature_players_wallet_enabled') && sc_is_pro_feature_players_wallet_enabled() && isset($wallet_list_sufix)) {
        add_action('load-' . $wallet_list_sufix, 'process_wallet_transactions_table_data');
    }

    if (!empty($add_expense_sufix)) {
        add_action('load-' . $add_expense_sufix, 'callback_add_expense_sufix');
    }
    if (function_exists('sc_is_pro_feature_coaches_enabled') && sc_is_pro_feature_coaches_enabled() && isset($add_coach_sufix, $list_coaches_sufix)) {
        add_action('load-' . $add_coach_sufix, 'callback_add_coach_sufix');
        add_action('load-' . $list_coaches_sufix, 'process_coaches_table_data');
    }

    add_action('load-toplevel_page_sc-coach-my-players', 'sc_coach_my_players_load');
}

function sc_events_screen_options() {
    add_screen_option('per_page', [
        'label'   => 'تعداد رویداد در هر صفحه',
        'default' => 10,
        'option'  => 'events_per_page'
    ]);
}

add_filter('set-screen-option', 'sc_events_set_screen_option', 10, 3);

function sc_events_set_screen_option($status, $option, $value) {
    if ($option == 'events_per_page') {
        return (int) $value;
    }
    return $status;
}


function sc_members_screen_option() {
    $option = 'players_per_page';
    $args = [
        'label'   => 'تعداد بازیکن در هر صفحه',
        'default' => 50,
        'option'  => $option
    ];
    add_screen_option('per_page', $args);
}

add_filter('set-screen-option', function($status, $option, $value) {
    if ($option === 'players_per_page') return (int) $value;
    return $status;
}, 10, 3);

function sc_coaches_screen_option() {
    $option = 'coaches_per_page';
    $args = [
        'label'   => 'تعداد مربی در هر صفحه',
        'default' => 20,
        'option'  => $option
    ];
    add_screen_option('per_page', $args);
}

function sc_courses_screen_option() {
    $option = 'courses_per_page';
    $args = [
        'label'   => 'تعداد دوره در هر صفحه',
        'default' => 10,
        'option'  => $option
    ];
    add_screen_option('per_page', $args);
}

add_filter('set-screen-option', function($status, $option, $value) {
    if ($option === 'coaches_per_page') return (int) $value;
    if ($option === 'coach_players_per_page') return (int) $value;
    if ($option === 'courses_per_page') return (int) $value;
    return $status;
}, 10, 3);

// برای





/**
 * Redirect when accessing coach wallet/salary pages and the pro feature is disabled
 */
add_action('admin_init', 'sc_block_coach_wallet_salary_pages_when_disabled', 5);
function sc_block_coach_wallet_salary_pages_when_disabled() {
    if (function_exists('sc_is_pro_feature_coaches_wallet_salary_enabled') && sc_is_pro_feature_coaches_wallet_salary_enabled()) {
        return;
    }
    $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    $blocked_pages = [
        'sc-coach-management', 'sc-coach-management-wallet', 'sc-coach-management-salary', 'sc-coach-management-withdrawals',
        'sc-coach-salary', 'sc-coach-wallet', 'sc-coach-withdrawals',
    ];
    if (!empty($page) && in_array($page, $blocked_pages, true)) {
        wp_safe_redirect(admin_url('index.php'));
        exit;
    }
}

/**
 * Export Excel endpoints
 */
add_action('admin_init', 'sc_handle_excel_export');
function sc_handle_excel_export() {
    // بررسی اینکه آیا درخواست export است
    if (!isset($_GET['sc_export']) || $_GET['sc_export'] !== 'excel') {
        return;
    }

    if (!function_exists('sc_is_license_active') || !sc_is_license_active()) {
        wp_die(esc_html__('خروجی اکسل به‌دلیل غیرفعال بودن لایسنس در دسترس نیست.', 'sportclub-manager'));
    }
    
    // بررسی دسترسی
    if (!current_user_can('manage_options') && !current_user_can('sc_finance_reports_access')) {
        wp_die('شما دسترسی لازم را ندارید.');
    }
    
    // بررسی nonce
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'sc_export_excel')) {
        wp_die('خطای امنیتی. لطفاً دوباره تلاش کنید.');
    }
    
    $export_type = isset($_GET['export_type']) ? sanitize_text_field($_GET['export_type']) : '';
    
    switch ($export_type) {
        case 'invoices':
            sc_export_invoices_to_excel();
            break;
        case 'attendance':
            sc_export_attendance_to_excel();
            break;
        case 'attendance_overall':
            sc_export_attendance_overall_to_excel();
            break;
        case 'members':
            sc_export_members_to_excel();
            break;
        case 'members_coach':
            sc_export_members_to_excel_coach();
            break;

        case 'expenses':
            sc_export_expenses_to_excel();
            break;
        case 'debtors':
            sc_export_debtors_to_excel();
            break;
        case 'active_users':
            sc_export_active_users_to_excel();
            break;
        case 'payments':
            sc_export_payments_to_excel();
            break;
        case 'course_users':
            sc_export_course_users_to_excel();
            break;
        case 'event_registrations':
            sc_export_event_registrations_to_excel();
            break;
        case 'wallet_transactions':
            sc_export_wallet_transactions_to_excel();
            break;
        case 'coach_management_salary':
            sc_export_sc_coach_management_salary_to_excel();
            break;
        case 'coach_management_withdrawals':
            sc_export_coach_management_withdrawals_to_excel();
            break;
        case 'finance_course_income':
            sc_export_finance_course_income_to_excel();
            break;
        case 'finance_event_income':
            sc_export_finance_event_income_to_excel();
            break;
        case 'finance_coach_income':
        case 'finance_club_share':
        case 'finance_coach_share':
            sc_export_finance_coach_share_to_excel();
            break;
        case 'finance_receivables':
            sc_export_finance_receivables_to_excel();
            break;
        case 'finance_store_income':
            sc_export_finance_store_income_to_excel();
            break;
        case 'finance_cashflow':
            sc_export_finance_cashflow_to_excel();
            break;
        case 'finance_ledger':
            sc_export_finance_ledger_to_excel();
            break;
        default:
            wp_die('نوع export معتبر نیست.');
    }
    
    exit;
}

/**
 * ذخیره تنظیمات screen option برای تعداد رکوردها در هر صفحه
 */
add_filter('set-screen-option', 'sc_set_invoices_screen_option', 10, 3);
function sc_set_invoices_screen_option($status, $option, $value) {
    if ('invoices_per_page' === $option) {
        return $value;
    }
    if ('honors_per_page' === $option) {
        return $value;
    }
    if ('support_tickets_per_page' === $option) {
        return $value;
    }
    if ('sms_report_per_page' === $option) {
        return max(1, min(500, (int) $value));
    }
    if ('list_products_per_page' === $option) {
        return max(1, min(200, (int) $value));
    }
    if ('list_orders_per_page' === $option) {
        return max(1, min(200, (int) $value));
    }
    return $status;
}

function sc_sms_log_screen_options() {
    add_screen_option('per_page', [
        'label'   => 'تعداد در هر صفحه',
        'default' => 25,
        'option'  => 'sms_report_per_page',
    ]);
}

/**
 * Placeholder functions for admin pages
 */
function sc_admin_dashboard_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'dashboard.php';
}

// کلاس خصوصی

function sc_render_coach_weekly_schedule_page() {
    include SC_TEMPLATES_ADMIN_DIR . 'coach-weekly-schedule.php';
}



function sc_render_private_bookings_admin_page() {
    include SC_TEMPLATES_ADMIN_DIR . 'private-bookings-list.php';
}

//پایان کلاس خصوصی مربی ها 

function sc_admin_members_list_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'list_players.php';
}
function sc_report_data(){

include SC_TEMPLATES_ADMIN_DIR . 'sc_reaport_club.php';
}
function sc_custom_orders() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('شما به این صفحه دسترسی ندارید.', 'sportclub-manager'));
    }
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'orders-list.php';
}

function sc_custom_products() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('شما به این صفحه دسترسی ندارید.', 'sportclub-manager'));
    }
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'products-list.php';
}

/**
 * هدایت لیست قدیمی ووکامرس محصولات به صفحه سفارشی باشگاه
 */
function sc_redirect_legacy_product_list_page() {
    if (!function_exists('sc_is_pro_feature_shop_enabled') || !sc_is_pro_feature_shop_enabled()) {
        return;
    }
    global $pagenow;
    if ($pagenow !== 'edit.php' || !isset($_GET['post_type']) || $_GET['post_type'] !== 'product') {
        return;
    }
    if (isset($_GET['action']) || isset($_GET['post']) || isset($_GET['ids'])) {
        return;
    }
    if (!current_user_can('manage_woocommerce')) {
        return;
    }
    wp_safe_redirect(admin_url('admin.php?page=sc-products'));
    exit;
}

/**
 * آماده‌سازی لیست محصولات ادمین (فیلترها و صفحه‌بندی)
 */
function process_products_table_data() {
    if (!current_user_can('manage_woocommerce')) {
        return;
    }
    add_screen_option('per_page', [
        'default' => 20,
        'option' => 'list_products_per_page',
        'label' => 'تعداد محصولات در هر صفحه',
    ]);
    if (!class_exists('WP_List_Table')) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
    }
    require_once SC_TEMPLATES_ADMIN_DIR . 'list_products.php';
    $GLOBALS['products_list_table'] = new products_List_Table();
    $GLOBALS['products_list_table']->prepare_items();
}

/**
 * آماده‌سازی لیست سفارشات ادمین (فیلترها و صفحه‌بندی)
 */
function process_orders_table_data() {
    if (!current_user_can('manage_woocommerce')) {
        return;
    }
    sc_check_and_create_tables();
    add_screen_option('per_page', [
        'default' => 20,
        'option' => 'list_orders_per_page',
        'label' => 'تعداد سفارش‌ها در هر صفحه',
    ]);
    if (!class_exists('WP_List_Table')) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
    }
    require_once SC_TEMPLATES_ADMIN_DIR . 'list_order.php';
    $GLOBALS['orders_list_table'] = new orders_List_Table();
    $GLOBALS['orders_list_table']->prepare_items();
}

function sc_admin_view_member_page() {
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'member-view.php';
}

function sc_admin_add_member_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    global $wpdb ;
            $table_name = $wpdb->prefix . 'sc_members';
            $player=false;
    if (isset($_GET['player_id'])) {
        $player_id = absint($_GET['player_id']);
        if ($player_id) {
            $player = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $player_id));
        }
    }
    include SC_TEMPLATES_ADMIN_DIR . 'member-add.php';
}
function sc_setting_callback(){
    include SC_TEMPLATES_ADMIN_DIR . 'settings.php';
    


}



function sc_admin_user_alerts_page() {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی غیرمجاز.');
    }
    if (function_exists('sc_render_user_alerts_page')) {
        sc_render_user_alerts_page();
        return;
    }
    echo '<div class="wrap"><div class="notice notice-error"><p>خطا: ماژول هشدارها بارگذاری نشد.</p></div></div>';
}

/**
 * Honors management pages
 */
function sc_admin_honors_list_page() {
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'list_honors.php';
}

function sc_admin_team_categories_page() {
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'team.php';
}
function sc_admin_level_categories_page() {
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'level.php';
}
function sc_admin_chapter_page() {
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'chapter.php';
}
function sc_admin_honor_categories_page() {
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'honor_categories.php';
}

function sc_admin_add_honor_for_member_page() {
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'add_honor_for_member.php';
}

function sc_admin_notifications_list_page() {
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'notifications-list.php';
}

function sc_admin_add_notification_page() {
    sc_check_and_create_tables();
    $GLOBALS['sc_notification_is_coach'] = false;
    include SC_TEMPLATES_ADMIN_DIR . 'notification-add.php';
}

function sc_admin_bale_bot_messages_list_page() {
    sc_check_and_create_tables();
    if (function_exists('sc_create_bot_messages_table')) {
        sc_create_bot_messages_table();
    }
    include SC_TEMPLATES_ADMIN_DIR . 'bale-bot-messages-list.php';
}

function sc_admin_bale_bot_send_page() {
    sc_check_and_create_tables();
    if (function_exists('sc_create_bot_messages_table')) {
        sc_create_bot_messages_table();
    }
    include SC_TEMPLATES_ADMIN_DIR . 'bale-bot-message-add.php';
}

function sc_admin_surveys_list_page() {
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'survey-list.php';
}

function sc_admin_survey_add_page() {
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'survey-add.php';
}

function sc_admin_survey_data_page() {
    sc_check_and_create_tables();
    if (!empty($_GET['edit_response'])) {
        include SC_TEMPLATES_ADMIN_DIR . 'survey-response-edit.php';
        return;
    }
    include SC_TEMPLATES_ADMIN_DIR . 'survey-data.php';
}

function sc_admin_survey_stats_page() {
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'survey-stats.php';
}

function sc_admin_coach_surveys_page() {
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'survey-coach-list.php';
}

function sc_admin_private_notes_list_page() {
    sc_check_and_create_tables();
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی غیرمجاز.');
    }
    $GLOBALS['sc_private_notes_is_coach'] = false;
    include SC_TEMPLATES_ADMIN_DIR . 'private-notes-list.php';
}

function sc_admin_private_notes_add_page() {
    sc_check_and_create_tables();
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی غیرمجاز.');
    }
    $GLOBALS['sc_private_notes_is_coach'] = false;
    include SC_TEMPLATES_ADMIN_DIR . 'private-note-add.php';
}

function sc_admin_private_notes_view_page() {
    sc_check_and_create_tables();
    if (!is_user_logged_in()) {
        wp_die('دسترسی غیرمجاز.');
    }
    include SC_TEMPLATES_ADMIN_DIR . 'private-note-view.php';
}

function sc_admin_users_info_export_page() {
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'users-info-export.php';
}

function sc_admin_users_export_templates_page() {
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'users-export-templates.php';
}

function sc_admin_bulk_actions_page() {
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'bulk-actions.php';
}

function sc_admin_certificates_issue_page() {
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'certificates-issue.php';
}

function sc_admin_certificates_templates_page() {
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'certificates-templates.php';
}

function sc_admin_certificates_list_page() {
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'certificates-list.php';
}

function sc_admin_coach_my_notifications_page() {
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'coach-my-notifications.php';
}

function sc_admin_coach_notifications_list_page() {
    sc_check_and_create_tables();
    $GLOBALS['sc_notification_is_coach'] = true;
    include SC_TEMPLATES_ADMIN_DIR . 'notifications-list.php';
}

function sc_admin_coach_add_notification_page() {
    sc_check_and_create_tables();
    $GLOBALS['sc_notification_is_coach'] = true;
    include SC_TEMPLATES_ADMIN_DIR . 'notification-add.php';
}

function sc_admin_coach_private_notes_list_page() {
    sc_check_and_create_tables();
    if (!current_user_can('sc_view_coach_salary')) {
        wp_die('دسترسی غیرمجاز.');
    }
    $GLOBALS['sc_private_notes_is_coach'] = true;
    include SC_TEMPLATES_ADMIN_DIR . 'private-notes-list.php';
}

function sc_admin_coach_private_notes_add_page() {
    sc_check_and_create_tables();
    if (!current_user_can('sc_view_coach_salary')) {
        wp_die('دسترسی غیرمجاز.');
    }
    $GLOBALS['sc_private_notes_is_coach'] = true;
    include SC_TEMPLATES_ADMIN_DIR . 'private-note-add.php';
}

function sc_private_booking_requests_menu_badge() {
    if (!function_exists('sc_private_can_manage_booking_requests') || !sc_private_can_manage_booking_requests()) {
        return;
    }
    if (!function_exists('sc_is_private_booking_admin_approval_mode') || !sc_is_private_booking_admin_approval_mode()) {
        return;
    }
    if (!isset($GLOBALS['menu'])) {
        return;
    }
    $pending = function_exists('sc_count_pending_private_booking_requests')
        ? sc_count_pending_private_booking_requests()
        : 0;
    if ($pending <= 0) {
        return;
    }
    $badge = ' <span class="awaiting-mod count-' . esc_attr($pending) . '"><span class="pending-count">' . (int) $pending . '</span></span>';
    foreach ($GLOBALS['menu'] as $key => $item) {
        if (isset($item[2]) && $item[2] === 'sc-private-booking-requests') {
            $GLOBALS['menu'][$key][0] = 'رزرو کلاس خصوصی' . $badge;
            break;
        }
    }
}

function sc_coach_notifications_menu_badge() {
    if (!current_user_can('sc_view_coach_salary') || !isset($GLOBALS['submenu']['sc-coach-notifications'])) {
        return;
    }
    $unread = function_exists('sc_count_unread_notifications') ? sc_count_unread_notifications(get_current_user_id()) : 0;
    if ($unread <= 0) {
        return;
    }
    $badge = ' <span class="awaiting-mod count-' . esc_attr($unread) . '"><span class="pending-count">' . $unread . '</span></span>';
    foreach ($GLOBALS['submenu']['sc-coach-notifications'] as $key => $item) {
        if (isset($item[2]) && $item[2] === 'sc-coach-notifications') {
            $GLOBALS['submenu']['sc-coach-notifications'][$key][0] = 'اطلاعیه‌های من' . $badge;
            break;
        }
    }
}

function sc_admin_coach_my_courses_page() {
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'coach-my-courses.php';
}

function sc_admin_coach_my_players_page() {
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'coach-my-players.php';
}

function sc_coach_support_tickets_menu_badge() {
    if (!current_user_can('sc_view_coach_salary') || !isset($GLOBALS['menu'])) {
        return;
    }
    $coach_id = function_exists('sc_support_get_coach_id_by_user_id') ? sc_support_get_coach_id_by_user_id(get_current_user_id()) : 0;
    if ($coach_id <= 0) return;
    $pending = function_exists('sc_support_count_pending_reply_for_coach') ? sc_support_count_pending_reply_for_coach($coach_id) : 0;
    if ($pending <= 0) return;
    $badge = ' <span class="awaiting-mod"><span class="pending-count">' . (int) $pending . '</span></span>';
    foreach ($GLOBALS['menu'] as $key => $item) {
        if (isset($item[2]) && $item[2] === 'sc-coach-support-tickets') {
            $GLOBALS['menu'][$key][0] = 'تیکت پشتیبانی' . $badge;
            break;
        }
    }
}

function sc_admin_coach_support_tickets_list_page() {
    sc_check_and_create_tables();
    if (!current_user_can('sc_view_coach_salary')) wp_die('دسترسی غیرمجاز.');
    $coach_id = function_exists('sc_support_get_coach_id_by_user_id') ? sc_support_get_coach_id_by_user_id(get_current_user_id()) : 0;
    if ($coach_id <= 0) wp_die('اطلاعات مربی یافت نشد.');
    include SC_TEMPLATES_ADMIN_DIR . 'coach-support-tickets-list.php';
}

function sc_admin_coach_support_ticket_view_page() {
    sc_check_and_create_tables();
    if (!current_user_can('sc_view_coach_salary')) wp_die('دسترسی غیرمجاز.');
    $ticket_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    if ($ticket_id <= 0) wp_die('تیکت نامعتبر.');
    $ticket = function_exists('sc_support_get_ticket') ? sc_support_get_ticket($ticket_id) : null;
    if (!$ticket || !function_exists('sc_support_can_view_ticket') || !sc_support_can_view_ticket($ticket, get_current_user_id())) {
        wp_die('تیکت یافت نشد یا دسترسی ندارید.');
    }
    include SC_TEMPLATES_ADMIN_DIR . 'coach-support-ticket-view.php';
}

function sc_admin_coach_support_ticket_new_page() {
    sc_check_and_create_tables();
    if (!current_user_can('sc_view_coach_salary')) wp_die('دسترسی غیرمجاز.');
    $coach_id = function_exists('sc_support_get_coach_id_by_user_id') ? sc_support_get_coach_id_by_user_id(get_current_user_id()) : 0;
    if ($coach_id <= 0) wp_die('اطلاعات مربی یافت نشد.');
    include SC_TEMPLATES_ADMIN_DIR . 'coach-support-ticket-new.php';
}

function sc_support_tickets_screen_option() {
    add_screen_option('per_page', [
        'label' => 'تعداد تیکت در هر صفحه',
        'default' => 20,
        'option' => 'support_tickets_per_page',
    ]);
    require_once SC_TEMPLATES_ADMIN_DIR . 'list_support_tickets.php';
    $GLOBALS['support_tickets_list_table'] = new Support_Tickets_List_Table();
    $GLOBALS['support_tickets_list_table']->prepare_items();
}

function sc_admin_support_tickets_menu_badge() {
    if (!current_user_can('sc_club_support_tickets') || !isset($GLOBALS['menu'])) return;
    if (function_exists('sc_support_is_accountant_ticket_scope_only') && sc_support_is_accountant_ticket_scope_only()) {
        $pending = function_exists('sc_support_count_pending_assigned_accountant_tickets') ? sc_support_count_pending_assigned_accountant_tickets(get_current_user_id()) : 0;
    } else {
        $pending = function_exists('sc_support_count_pending_reply_for_admin') ? sc_support_count_pending_reply_for_admin() : 0;
    }
    if ($pending <= 0) return;
    $badge = ' <span class="awaiting-mod"><span class="pending-count">' . (int) $pending . '</span></span>';
    foreach ($GLOBALS['menu'] as $key => $item) {
        if (isset($item[2]) && $item[2] === 'sc-support-tickets') {
            $GLOBALS['menu'][$key][0] = 'تیکت پشتیبانی' . $badge;
            break;
        }
    }
}

function sc_admin_support_tickets_list_page() {
    sc_check_and_create_tables();
    if (!current_user_can('sc_club_support_tickets')) wp_die('دسترسی غیرمجاز.');

    // Handle bulk actions (delete / close)
    $action = '';
    if (!empty($_REQUEST['action']) && $_REQUEST['action'] !== '-1') {
        $action = sanitize_text_field($_REQUEST['action']);
    } elseif (!empty($_REQUEST['action2']) && $_REQUEST['action2'] !== '-1') {
        $action = sanitize_text_field($_REQUEST['action2']);
    }
    if (in_array($action, ['delete', 'close'], true)) {
        if (wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'bulk-tickets')) {
            $ids = isset($_REQUEST['ticket']) ? array_map('absint', (array) $_REQUEST['ticket']) : [];
            if (!empty($ids)) {
                global $wpdb;
                $tickets_table = $wpdb->prefix . 'sc_support_tickets';
                $messages_table = $wpdb->prefix . 'sc_support_ticket_messages';
                foreach ($ids as $tid) {
                    $tk = function_exists('sc_support_get_ticket') ? sc_support_get_ticket($tid) : null;
                    if (!$tk || !function_exists('sc_support_can_view_ticket') || !sc_support_can_view_ticket($tk, get_current_user_id())) {
                        continue;
                    }
                    if ($action === 'delete') {
                        $wpdb->delete($messages_table, ['ticket_id' => $tid], ['%d']);
                        $wpdb->delete($tickets_table, ['id' => $tid], ['%d']);
                    } elseif ($action === 'close') {
                        if (function_exists('sc_support_close_ticket')) {
                            sc_support_close_ticket($tid, get_current_user_id());
                        } else {
                            $wpdb->update($tickets_table, ['status' => 'closed', 'updated_at' => current_time('mysql')], ['id' => $tid], ['%s', '%s'], ['%d']);
                        }
                    }
                }
                // بعد از عملیات گروهی، صفحه را بدون فیلتر رفرش کن تا URL تمیز بماند.
                wp_redirect(add_query_arg(['page' => 'sc-support-tickets'], admin_url('admin.php')));
                exit;
            }
        }
    }

    $list_table = isset($GLOBALS['support_tickets_list_table']) ? $GLOBALS['support_tickets_list_table'] : null;
    if (!$list_table) {
        require_once SC_TEMPLATES_ADMIN_DIR . 'list_support_tickets.php';
        $list_table = new Support_Tickets_List_Table();
        $list_table->prepare_items();
    }
    ?>
        <?php
        // Prepare current filter values
        $filter_status      = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
        $filter_created_by  = isset($_GET['filter_created_by']) ? sanitize_text_field($_GET['filter_created_by']) : 'all';
        $filter_user_id     = isset($_GET['filter_user_id']) ? absint($_GET['filter_user_id']) : 0;
        $search             = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $scope_accountant   = function_exists('sc_support_is_accountant_ticket_scope_only') && sc_support_is_accountant_ticket_scope_only();
        $filter_department  = $scope_accountant ? 'accountant' : (isset($_GET['filter_department']) ? sanitize_text_field($_GET['filter_department']) : 'all');

        $filter_date_from_sh = isset($_GET['filter_date_from_shamsi']) ? sanitize_text_field(wp_unslash($_GET['filter_date_from_shamsi'])) : '';
        $filter_date_to_sh   = isset($_GET['filter_date_to_shamsi']) ? sanitize_text_field(wp_unslash($_GET['filter_date_to_shamsi'])) : '';

        $active_filters_count = 0;
        if ($search !== '') {
            $active_filters_count++;
        }
        if ($filter_status !== 'all') {
            $active_filters_count++;
        }
        if (!$scope_accountant && $filter_department !== 'all') {
            $active_filters_count++;
        }
        if ($filter_created_by !== 'all') {
            $active_filters_count++;
        }
        if ($filter_user_id > 0) {
            $active_filters_count++;
        }
        if ($filter_date_from_sh !== '' || $filter_date_to_sh !== '') {
            $active_filters_count++;
        }
        $filters_open = $active_filters_count > 0;

        global $wpdb;
        $t = $wpdb->prefix . 'sc_support_tickets';
        $m = $wpdb->prefix . 'sc_members';
        if ($scope_accountant) {
            $acc_uid = get_current_user_id();
            $users_with_tickets = $wpdb->get_results($wpdb->prepare(
                "SELECT DISTINCT t.user_id,
                        TRIM(CONCAT(COALESCE(m.first_name,''), ' ', COALESCE(m.last_name,''))) AS name,
                        m.national_id
                 FROM $t t
                 LEFT JOIN $m m ON m.user_id = t.user_id
                 WHERE t.user_id > 0 AND (
                    (t.department = 'accountant' AND t.coach_id = %d)
                    OR (t.created_by_type = 'accountant' AND t.created_by_user_id = %d)
                 )
                 ORDER BY name ASC",
                $acc_uid,
                $acc_uid
            ));
        } else {
            $users_with_tickets = $wpdb->get_results(
                "SELECT DISTINCT t.user_id,
                        TRIM(CONCAT(COALESCE(m.first_name,''), ' ', COALESCE(m.last_name,''))) AS name,
                        m.national_id
                 FROM $t t
                 LEFT JOIN $m m ON m.user_id = t.user_id
                 WHERE t.user_id > 0
                 ORDER BY name ASC"
            );
        }
        $selected_user_text = 'همه کاربران';
        if ($filter_user_id) {
            foreach ($users_with_tickets as $u) {
                if ((int) $u->user_id === $filter_user_id) {
                    $selected_user_text = trim($u->name) ?: 'کاربر #' . $u->user_id;
                    if (!empty($u->national_id)) {
                        $selected_user_text .= ' - ' . $u->national_id;
                    }
                    break;
                }
            }
        }
        ?>
    <div class="wrap sc-support-admin-wrap sc-ticket-list-wrap">
        <div class="sc-ticket-list-header">
            <div class="sc-ticket-list-header-text">
                <h1 class="sc-ticket-list-title">لیست تیکت‌های پشتیبانی</h1>
                <p class="sc-ticket-list-desc">مدیریت تیکت‌ها، فیلتر و پاسخ به درخواست‌های پشتیبانی</p>
            </div>
            <div class="sc-ticket-list-header-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-support-ticket-new')); ?>" class="sc-ticket-list-add-btn">ارسال تیکت جدید</a>
            </div>
        </div>

        <form method="get" class="sc-support-filter-form">
            <input type="hidden" name="page" value="sc-support-tickets">
            <?php wp_nonce_field('bulk-tickets'); ?>

        <div class="sc-ticket-list-filters-card<?php echo $filters_open ? ' is-open' : ''; ?>">
        <div class="sc-ticket-list-filters-toolbar">
            <button type="button" class="sc-ticket-list-filters-toggle" id="sc-ticket-filters-toggle" aria-expanded="<?php echo $filters_open ? 'true' : 'false'; ?>" aria-controls="sc-ticket-filters-panel">
                <span class="sc-ticket-list-filters-toggle-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </span>
                <span class="sc-ticket-list-filters-toggle-label" data-label-open="بستن فیلترها" data-label-closed="مشاهده فیلترها"><?php echo $filters_open ? 'بستن فیلترها' : 'مشاهده فیلترها'; ?></span>
                <?php if ($active_filters_count > 0) : ?>
                    <span class="sc-ticket-list-filters-badge"><?php echo (int) $active_filters_count; ?></span>
                <?php endif; ?>
                <span class="sc-ticket-list-filters-chevron" aria-hidden="true"></span>
            </button>
            <?php if ($active_filters_count > 0) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-support-tickets')); ?>" class="sc-ticket-list-filters-clear">پاک کردن فیلترها</a>
            <?php endif; ?>
        </div>
        <div class="sc-ticket-list-filters-panel" id="sc-ticket-filters-panel"<?php echo $filters_open ? '' : ' hidden'; ?>>

            <div class="sc-filter-grid">
                <div class="sc-filter-field">
                    <label class="sc-filter-label">جستجو</label>
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="جستجو در موضوع یا شناسه..." class="sc-filter-control">
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_status">وضعیت</label>
                    <select name="filter_status" id="filter_status" class="sc-filter-control">
                        <option value="all" <?php selected($filter_status, 'all'); ?>>همه</option>
                        <option value="pending_reply" <?php selected($filter_status, 'pending_reply'); ?>>در انتظار پاسخ</option>
                        <option value="answered" <?php selected($filter_status, 'answered'); ?>>پاسخ داده شده</option>
                        <option value="closed" <?php selected($filter_status, 'closed'); ?>>بسته شده</option>
                    </select>
                </div>

                <?php if (!$scope_accountant) : ?>
                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_department">بخش</label>
                    <select name="filter_department" id="filter_department" class="sc-filter-control">
                        <option value="all" <?php selected($filter_department, 'all'); ?>>همه</option>
                        <option value="manager" <?php selected($filter_department, 'manager'); ?>>مدیر باشگاه</option>
                        <option value="site_support" <?php selected($filter_department, 'site_support'); ?>>پشتیبانی سایت</option>
                        <option value="coach" <?php selected($filter_department, 'coach'); ?>>مربی</option>
                        <option value="accountant" <?php selected($filter_department, 'accountant'); ?>>حسابدار</option>
                    </select>
                </div>
                <?php endif; ?>

                <div class="sc-filter-field">
                    <label class="sc-filter-label" for="filter_created_by">ارسال‌کننده</label>
                    <select name="filter_created_by" id="filter_created_by" class="sc-filter-control">
                        <option value="all" <?php selected($filter_created_by, 'all'); ?>>همه</option>
                        <option value="user" <?php selected($filter_created_by, 'user'); ?>>کاربر (عضو)</option>
                        <option value="coach" <?php selected($filter_created_by, 'coach'); ?>>مربی</option>
                        <option value="admin" <?php selected($filter_created_by, 'admin'); ?>>مدیر</option>
                    </select>
                </div>

                <div class="sc-filter-field">
                    <label class="sc-filter-label">کاربر</label>
                    <div class="sc-searchable-dropdown">
                        <input type="hidden" name="filter_user_id" id="filter_user_id" value="<?php echo esc_attr($filter_user_id); ?>">
                        <div class="sc-dropdown-toggle">
                            <span class="sc-dropdown-placeholder" <?php if ($filter_user_id) echo 'style="display:none"'; ?>>همه کاربران</span>
                            <span class="sc-dropdown-selected" <?php if (!$filter_user_id) echo 'style="display:none"'; ?>><?php echo esc_html($selected_user_text); ?></span>
                            <span class="sc-dropdown-arrow">▼</span>
                        </div>
                        <div class="sc-dropdown-menu">
                            <div class="sc-dropdown-search">
                                <input type="text" class="sc-search-input" placeholder="جستجوی نام یا کد ملی...">
                            </div>
                            <div class="sc-dropdown-options">
                                <div class="sc-dropdown-option sc-visible" data-value="0" data-search="همه کاربران"
                                     onclick="scSelectMemberFilter(this,'0','همه کاربران')">همه کاربران</div>
                                <?php foreach ($users_with_tickets as $u) :
                                    $opt_text = trim($u->name) ?: 'کاربر #' . $u->user_id;
                                    if (!empty($u->national_id)) $opt_text .= ' - ' . $u->national_id;
                                    $search_str = strtolower(trim($u->name) . ' ' . ($u->national_id ?? ''));
                                ?>
                                <div class="sc-dropdown-option" data-value="<?php echo esc_attr($u->user_id); ?>" data-search="<?php echo esc_attr($search_str); ?>"
                                     onclick="scSelectMemberFilter(this,'<?php echo esc_js($u->user_id); ?>','<?php echo esc_js($opt_text); ?>')">
                                    <?php echo esc_html($opt_text); ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sc-filter-field sc-filter-date">
                    <label class="sc-filter-label">بازه تاریخ</label>
                    <div class="sc-date-range sc-ticket-date-range">
                        <input type="text" name="filter_date_from_shamsi" class="persian-date-input sc-no-default-date sc-filter-control" value="<?php echo esc_attr($filter_date_from_sh); ?>" placeholder="از تاریخ" readonly>
                        <input type="text" name="filter_date_to_shamsi" class="persian-date-input sc-no-default-date sc-filter-control" value="<?php echo esc_attr($filter_date_to_sh); ?>" placeholder="تا تاریخ" readonly>
                    </div>
                </div>
            </div>

            <div class="sc-ticket-list-filters-actions">
                <input type="submit" class="button button-primary" value="اعمال فیلتر">
                <a href="<?php echo esc_url(admin_url('admin.php?page=sc-support-tickets')); ?>" class="button delete_fillter">پاک کردن فیلترها</a>
            </div>
        </div>
        </div>

        <div class="sc-ticket-list-table-card">
            <?php $list_table->views(); ?>
            <?php $list_table->display(); ?>
        </div>
        </form>
    </div>
    <script>
    jQuery(function($){
        var $toggle = $('#sc-ticket-filters-toggle');
        var $panel = $('#sc-ticket-filters-panel');
        var $card = $toggle.closest('.sc-ticket-list-filters-card');
        var $label = $toggle.find('.sc-ticket-list-filters-toggle-label');
        $toggle.on('click', function(){
            var isOpen = $card.hasClass('is-open');
            if (isOpen) {
                $card.removeClass('is-open');
                $panel.attr('hidden', true);
                $toggle.attr('aria-expanded', 'false');
                $label.text($label.data('label-closed'));
            } else {
                $card.addClass('is-open');
                $panel.removeAttr('hidden');
                $toggle.attr('aria-expanded', 'true');
                $label.text($label.data('label-open'));
            }
        });
    });
    </script>
    <?php
}

function sc_admin_support_ticket_new_page() {
    sc_check_and_create_tables();
    if (!current_user_can('sc_club_support_tickets')) wp_die('دسترسی غیرمجاز.');
    include SC_TEMPLATES_ADMIN_DIR . 'admin-support-ticket-new.php';
}

function sc_admin_support_ticket_view_page() {
    sc_check_and_create_tables();
    if (!current_user_can('sc_club_support_tickets')) wp_die('دسترسی غیرمجاز.');
    $ticket_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    if ($ticket_id <= 0) wp_die('تیکت نامعتبر.');
    $ticket = sc_support_get_ticket($ticket_id);
    if (!$ticket || !sc_support_can_view_ticket($ticket, get_current_user_id())) wp_die('تیکت یافت نشد.');
    include SC_TEMPLATES_ADMIN_DIR . 'admin-support-ticket-view.php';
}

function sc_admin_coach_my_profile_page() {
    sc_check_and_create_tables();
    if (!current_user_can('sc_view_coach_salary')) {
        wp_die('دسترسی غیرمجاز.');
    }
    $coach_id = function_exists('sc_current_user_coach_id') ? sc_current_user_coach_id() : 0;
    if ($coach_id <= 0) {
        wp_die('اطلاعات مربی یافت نشد.');
    }
    global $wpdb;
    $coaches_table = $wpdb->prefix . 'sc_coaches';
    $redirect_url = admin_url('admin.php?page=sc-coach-my-profile');
    if (isset($_POST['submit_coach_profile']) && isset($_POST['sc_coach_profile_nonce']) && wp_verify_nonce($_POST['sc_coach_profile_nonce'], 'sc_coach_profile_edit')) {
        $first_name = isset($_POST['first_name']) ? trim(sanitize_text_field($_POST['first_name'])) : '';
        $last_name = isset($_POST['last_name']) ? trim(sanitize_text_field($_POST['last_name'])) : '';
        $national_id = isset($_POST['national_id']) ? trim(sanitize_text_field($_POST['national_id'])) : '';
        $mobile_phone = isset($_POST['mobile_phone']) ? trim(sanitize_text_field($_POST['mobile_phone'])) : '';
        if (empty($first_name) || empty($last_name) || empty($national_id) || empty($mobile_phone)) {
            wp_safe_redirect(add_query_arg('sc_status', 'error', $redirect_url));
            exit;
        }
        $data = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'national_id' => $national_id,
            'mobile_phone' => $mobile_phone,
            'gender' => isset($_POST['gender']) && $_POST['gender'] !== '' ? sanitize_text_field($_POST['gender']) : null,
            'specialization' => isset($_POST['specialization']) && trim($_POST['specialization']) !== '' ? sanitize_text_field($_POST['specialization']) : null,
            'coaching_level' => isset($_POST['coaching_level']) && $_POST['coaching_level'] !== '' ? sanitize_text_field($_POST['coaching_level']) : null,
            'coaching_experience' => isset($_POST['coaching_experience']) && $_POST['coaching_experience'] !== '' ? absint($_POST['coaching_experience']) : null,
            'sports_history' => isset($_POST['sports_history']) && trim($_POST['sports_history']) !== '' ? sanitize_textarea_field($_POST['sports_history']) : null,
            'personal_photo' => (isset($_POST['personal_photo']) && trim((string) $_POST['personal_photo']) !== '')
                ? esc_url_raw(wp_unslash($_POST['personal_photo']))
                : null,
            'updated_at' => current_time('mysql'),
        ];
        $format = ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s'];
        $updated = $wpdb->update($coaches_table, $data, ['id' => $coach_id], $format, ['%d']);
        if ($updated !== false) {
            $coach_row = $wpdb->get_row($wpdb->prepare("SELECT user_id FROM $coaches_table WHERE id = %d", $coach_id));
            if (!empty($coach_row->user_id)) {
                if (function_exists('wp_update_user')) {
                    wp_update_user([
                        'ID' => $coach_row->user_id,
                        'first_name' => $data['first_name'],
                        'last_name' => $data['last_name'],
                        'display_name' => $data['first_name'] . ' ' . $data['last_name'],
                    ]);
                    update_user_meta($coach_row->user_id, 'billing_phone', $data['mobile_phone']);
                }
                if (!empty($_POST['password'])) {
                    wp_set_password($_POST['password'], $coach_row->user_id);
                }
            }
            wp_safe_redirect(add_query_arg('sc_status', 'updated', $redirect_url));
            exit;
        }
        wp_safe_redirect(add_query_arg('sc_status', 'error', $redirect_url));
        exit;
    }
    include SC_TEMPLATES_ADMIN_DIR . 'coach-my-profile.php';
}

function sc_coach_my_players_load() {
    if (!current_user_can('sc_view_coach_salary')) {
        return;
    }
    $coach_id = function_exists('sc_current_user_coach_id') ? sc_current_user_coach_id() : 0;
    if ($coach_id <= 0) {
        return;
    }
    add_screen_option('per_page', [
        'label'   => 'تعداد بازیکن در هر صفحه',
        'default' => 20,
        'option'  => 'coach_players_per_page',
    ]);
    sc_check_and_create_tables();
    require_once SC_TEMPLATES_ADMIN_DIR . 'coach-players-list-table.php';
    $table = new Coach_Players_List_Table($coach_id);
    $table->prepare_items();
    $GLOBALS['coach_players_list_table'] = $table;
}


function sc_admin_coach_honors_page() {
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'coach_honors.php';
}

/**
 * Screen options for honors list
 */
function sc_honors_screen_option() {
    $option = 'per_page';
    $args = [
        'label' => 'تعداد رکورد در هر صفحه',
        'default' => 10,
        'option' => 'honors_per_page'
    ];
    add_screen_option($option, $args);
}

/**
 * Coaches management pages
 */
function sc_admin_coaches_list_page() {
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'list_coaches.php';
}

function sc_admin_add_coach_page() {
    sc_check_and_create_tables();
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_coaches';
    $coach = false;
    
    if (isset($_GET['coach_id'])) {
        $coach_id = absint($_GET['coach_id']);
        if ($coach_id) {
            $coach = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $coach_id));
        }
    }
    
    include SC_TEMPLATES_ADMIN_DIR . 'coach-add.php';
}

function process_coaches_table_data() {
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'coaches-list.php';
    $GLOBALS['coaches_list_table'] = new Coaches_List_Table();
    $GLOBALS['coaches_list_table']->prepare_items();
}

/**
 * Attendance management pages
 */
function sc_admin_attendance_add_page() {
    // بررسی دسترسی (مربی یا مدیر)
    if (!current_user_can('sc_manage_attendance') && !current_user_can('manage_options')) {
        wp_die('شما دسترسی لازم را ندارید.');
    }
    
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'attendance-add.php';
}

function sc_admin_attendance_list_page() {
    // بررسی دسترسی (مربی یا مدیر)
    if (!current_user_can('sc_manage_attendance') && !current_user_can('manage_options')) {
        wp_die('شما دسترسی لازم را ندارید.');
    }
    
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'attendance-list.php';
}

function sc_admin_attendance_session_cancellations_page() {
    if (!current_user_can('sc_manage_attendance') && !current_user_can('manage_options')) {
        wp_die('شما دسترسی لازم را ندارید.');
    }
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'course-session-cancellations.php';
}

function sc_admin_attendance_logs() {
    // بررسی دسترسی (مربی یا مدیر)
    if (!current_user_can('sc_manage_attendance') && !current_user_can('manage_options')) {
        wp_die('شما دسترسی لازم را ندارید.');
    }
    
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'list_attendance_logs.php';
}

function sc_admin_attendance_report_page() {
    if (!current_user_can('sc_manage_attendance') && !current_user_can('manage_options')) {
        wp_die('شما دسترسی لازم را ندارید.');
    }
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'attendance-report.php';
}
function sc_admin_faq() {
    
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'faq.php';
}

/**
 * Capability سفارشی برای دسترسی به حضور و غیاب
 * مدیران (administrator و club_coach) و مربیان (coach) دسترسی دارند
 */
add_filter('user_has_cap', 'sc_manage_attendance_or_admin_cap', 10, 4);
function sc_manage_attendance_or_admin_cap($allcaps, $caps, $args, $user) {
    // بررسی اینکه آیا یکی از capability های درخواست شده sc_manage_attendance_or_admin است
    foreach ($caps as $cap) {
        if ($cap === 'sc_manage_attendance_or_admin') {
            // اگر کاربر مدیر است (administrator یا club_coach)
            if (isset($allcaps['manage_options']) && $allcaps['manage_options']) {
                $allcaps['sc_manage_attendance_or_admin'] = true;
                break;
            }
            // اگر کاربر مربی است و sc_manage_attendance دارد
            elseif (isset($allcaps['sc_manage_attendance']) && $allcaps['sc_manage_attendance']) {
                $allcaps['sc_manage_attendance_or_admin'] = true;
                break;
            }
        }
    }
    return $allcaps;
}

/**
 * Capability for finance reports access
 * admins, club managers and accountants can access financial reports.
 */
add_filter('user_has_cap', 'sc_finance_reports_access_cap', 10, 4);
function sc_finance_reports_access_cap($allcaps, $caps, $args, $user) {
    foreach ($caps as $cap) {
        if ($cap === 'sc_finance_reports_access') {
            if (
                (!empty($allcaps['manage_options']) && $allcaps['manage_options']) ||
                (!empty($allcaps['club_coach']) && $allcaps['club_coach']) ||
                (!empty($allcaps['accountantt']) && $allcaps['accountantt'])
            ) {
                $allcaps['sc_finance_reports_access'] = true;
            }
            break;
        }
    }
    return $allcaps;
}

/**
 * Invoices management page
 */
function sc_admin_invoices_list_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'invoices-list.php';
}

function process_invoices_table_data() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    // افزودن screen option برای تعداد رکوردها در هر صفحه
    add_screen_option('per_page', [
        'default' => 20,
        'option' => 'invoices_per_page',
        'label' => 'تعداد صورت حساب‌ها در هر صفحه'
    ]);
    
    include SC_TEMPLATES_ADMIN_DIR . 'list_invoices.php';
    $GLOBALS['invoices_list_table'] = new Invoices_List_Table();
    $GLOBALS['invoices_list_table']->prepare_items();
}

/**
 * Create invoice page
 */
function sc_admin_add_invoice_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'invoice-add.php';
}

/**
 * Wallet management pages
 */
function sc_admin_wallet_list_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    // لود فایل (اگر قبلاً لود نشده باشد)
    require_once SC_TEMPLATES_ADMIN_DIR . 'wallet-list.php';
}

// آماده‌سازی جدول تراکنش‌های کیف پول
function process_wallet_transactions_table_data() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    // افزودن screen option برای تعداد رکوردها در هر صفحه
    add_screen_option('per_page', [
        'default' => 20,
        'option' => 'wallet_transactions_per_page',
        'label' => 'تعداد تراکنش‌ها در هر صفحه'
    ]);
    
    // لود کلاس جدول
    include SC_TEMPLATES_ADMIN_DIR . 'list_wallet_transactions.php';
    
    // ایجاد و آماده‌سازی جدول
    $GLOBALS['wallet_transactions_list_table'] = new Wallet_Transactions_List_Table();
    $GLOBALS['wallet_transactions_list_table']->prepare_items();
}

add_filter('set-screen-option', 'sc_wallet_set_screen_option', 10, 3);
function sc_wallet_set_screen_option($status, $option, $value) {
    if ('wallet_transactions_per_page' == $option) {
        return $value;
    }
    return $status;
}

function sc_admin_wallet_charge_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'wallet-charge.php';
}

function sc_admin_wallet_deduct_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'wallet-deduct.php';
}

function sc_admin_wallet_manage_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'wallet-manage.php';
}

/**
 * Reports pages
 */
function sc_admin_reports_active_users_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'reports-active-users.php';
}

function sc_admin_reports_income_expenses_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'reports-finance.php';
}

function sc_admin_reports_coach_performance_page() {
    sc_check_and_create_tables();
    if (!function_exists('sc_bi_coach_monthly_metrics')) {
        require_once SC_INCLUDES_DIR . 'bi-analytics-functions.php';
    }
    include SC_TEMPLATES_ADMIN_DIR . 'reports-coach-performance.php';
}

function sc_admin_reports_bi_analytics_page() {
    sc_check_and_create_tables();
    if (!function_exists('sc_bi_parse_date_filters')) {
        require_once SC_INCLUDES_DIR . 'bi-analytics-functions.php';
    }
    include SC_TEMPLATES_ADMIN_DIR . 'reports-bi-analytics.php';
}

function sc_admin_reports_debtors_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'reports-debtors.php';
}

function sc_admin_reports_weekly_schedule_page() {
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'reports-weekly-schedule.php';
}

function sc_admin_reports_sms_log_page() {
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'reports-sms-log.php';
}

function sc_admin_activity_log_page() {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی غیرمجاز.');
    }
    sc_check_and_create_tables();
    include SC_TEMPLATES_ADMIN_DIR . 'activity-log-list.php';
}

// function sc_admin_reports_payments_page() {
//     // بررسی و ایجاد جداول در صورت عدم وجود
//     sc_check_and_create_tables();
    
//     include SC_TEMPLATES_ADMIN_DIR . 'reports-payments.php';
// }

/**
 * Expenses management pages
 */
function sc_admin_add_expense_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'expense-add.php';
}

function sc_admin_expenses_list_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'expenses-list.php';
}

function sc_admin_add_event_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_events';
    $event = false;
    
    if (isset($_GET['event_id'])) {
        $event_id = absint($_GET['event_id']);
        if ($event_id) {
            $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $event_id));
        }
    }
    
    include SC_TEMPLATES_ADMIN_DIR . 'event-add.php';
}

function sc_admin_events_list_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'list_events.php';
}

/**
 * Process expense creation/update form
 */
function callback_add_expense_sufix() {
    if (isset($_GET['page']) && $_GET['page'] == 'sc-add-expense' && isset($_POST['submit_expense'])) {
        // بررسی nonce
        if (!isset($_POST['sc_expense_nonce']) || !wp_verify_nonce($_POST['sc_expense_nonce'], 'sc_add_expense')) {
            wp_die('خطای امنیتی. لطفاً دوباره تلاش کنید.');
        }
        
        // بررسی و ایجاد جداول در صورت عدم وجود
        sc_check_and_create_tables();
        
        global $wpdb;
        $expenses_table = $wpdb->prefix . 'sc_expenses';
        
        // اعتبارسنجی
        if (empty($_POST['expense_name'])) {
            wp_redirect(admin_url('admin.php?page=sc-add-expense&sc_status=expense_add_error'));
            exit;
        }
        
        $expense_name = sanitize_text_field($_POST['expense_name']);
        $chapter = !empty($_POST['chapter']) ? sanitize_text_field($_POST['chapter']) : null;
        $category_id = !empty($_POST['category_id']) ? absint($_POST['category_id']) : NULL;
        
        // دریافت مبلغ (حذف کاماها در صورت وجود)
        $amount_value = '';
        if (!empty($_POST['amount_raw'])) {
            $amount_value = sanitize_text_field($_POST['amount_raw']);
        } elseif (!empty($_POST['amount'])) {
            $amount_value = preg_replace('/[^0-9.]/', '', sanitize_text_field($_POST['amount']));
        }
        $amount = !empty($amount_value) && is_numeric($amount_value) ? floatval($amount_value) : 0;
        
        if ($amount <= 0) {
            wp_redirect(admin_url('admin.php?page=sc-add-expense&sc_status=expense_add_error'));
            exit;
        }
        
        // پردازش تاریخ
        $expense_date_shamsi = !empty($_POST['expense_date_shamsi']) ? sanitize_text_field($_POST['expense_date_shamsi']) : '';
        $expense_date_gregorian = NULL;
        
        if (!empty($expense_date_shamsi)) {
            $expense_date_gregorian = sc_shamsi_to_gregorian_date($expense_date_shamsi);
        } elseif (!empty($_POST['expense_date_gregorian'])) {
            $expense_date_gregorian = sanitize_text_field($_POST['expense_date_gregorian']);
        }
        
        if (!$expense_date_gregorian) {
            // تاریخ پیش‌فرض: امروز
            $expense_date_gregorian = current_time('Y-m-d');
            $today = new DateTime();
            $today_jalali = gregorian_to_jalali((int)$today->format('Y'), (int)$today->format('m'), (int)$today->format('d'));
            $expense_date_shamsi = $today_jalali[0] . '/' . 
                                   str_pad($today_jalali[1], 2, '0', STR_PAD_LEFT) . '/' . 
                                   str_pad($today_jalali[2], 2, '0', STR_PAD_LEFT);
        }
        
        $description = !empty($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        
        $expense_id = isset($_POST['expense_id']) ? absint($_POST['expense_id']) : 0;
        
        // ذخیره یا بروزرسانی هزینه
        $expense_data = [
            'name' => $expense_name,
            'chapter' => $chapter,
            'category_id' => $category_id,
            'expense_date_shamsi' => $expense_date_shamsi,
            'expense_date_gregorian' => $expense_date_gregorian,
            'amount' => $amount,
            'description' => $description,
            'updated_at' => current_time('mysql')
        ];
        
        if ($expense_id > 0) {
            // بروزرسانی
            $updated = $wpdb->update(
                $expenses_table,
                $expense_data,
                ['id' => $expense_id],
                ['%s', '%s', '%d', '%s', '%s', '%f', '%s', '%s'],
                ['%d']
            );
            
            if ($updated !== false) {
                wp_redirect(admin_url('admin.php?page=sc-add-expense&expense_id=' . $expense_id . '&sc_status=expense_updated'));
                exit;
            } else {
                wp_redirect(admin_url('admin.php?page=sc-add-expense&expense_id=' . $expense_id . '&sc_status=expense_update_error'));
                exit;
            }
        } else {
            // ایجاد جدید
            $expense_data['created_at'] = current_time('mysql');
            
            $inserted = $wpdb->insert(
                $expenses_table,
                $expense_data,
                ['%s', '%s', '%d', '%s', '%s', '%f', '%s', '%s', '%s']
            );
            
            if ($inserted !== false) {
                $expense_id = $wpdb->insert_id;
                wp_redirect(admin_url('admin.php?page=sc-add-expense&expense_id=' . $expense_id . '&sc_status=expense_add_true'));
                exit;
            } else {
                wp_redirect(admin_url('admin.php?page=sc-add-expense&sc_status=expense_add_error'));
                exit;
            }
        }
    }
}

/**
 * Process invoice creation form
 */
function callback_add_invoice_sufix() {
    
    if (isset($_GET['page']) && $_GET['page'] == 'sc-add-invoice' && isset($_POST['submit_invoice'])) {
        // بررسی nonce
        if (!isset($_POST['sc_invoice_nonce']) || !wp_verify_nonce($_POST['sc_invoice_nonce'], 'sc_add_invoice')) {
            wp_die('خطای امنیتی. لطفاً دوباره تلاش کنید.');
        }
        
        // بررسی و ایجاد جداول در صورت عدم وجود
        sc_check_and_create_tables();
        
        global $wpdb;
        $invoices_table = $wpdb->prefix . 'sc_invoices';
        $courses_table = $wpdb->prefix . 'sc_courses';
        $members_table = $wpdb->prefix . 'sc_members';
        
        // جمع آوری کاربران براساس فیلتر (مشابه بخش کارهای دست جمعی)
        $member_ids = array();
        if (function_exists('sc_bulk_actions_collect_filter_config') && function_exists('sc_bulk_actions_get_members')) {
            $payload = sc_bulk_actions_collect_filter_config($_POST);
            if ($payload['config']['member_status'] === 'all') {
                $payload['config']['member_status'] = 'active';
            }
            $members = sc_bulk_actions_get_members($payload['target_type'], $payload['config']);
            $member_ids = array_values(array_unique(array_map('absint', wp_list_pluck($members, 'id'))));
        }

        // پشتیبانی از حالت قدیمی تک کاربر (در صورت ارسال member_id)
        if (empty($member_ids) && !empty($_POST['member_id'])) {
            $member_ids = array(absint($_POST['member_id']));
        }

        $excluded_member_ids = isset($_POST['excluded_member_ids']) ? array_filter(array_map('absint', (array) $_POST['excluded_member_ids'])) : array();
        $included_member_ids = isset($_POST['included_member_ids']) ? array_filter(array_map('absint', (array) $_POST['included_member_ids'])) : array();
        if (function_exists('sc_audience_merge_included_member_ids')) {
            $member_ids = sc_audience_merge_included_member_ids($member_ids, $included_member_ids);
        } elseif (!empty($included_member_ids)) {
            $member_ids = array_values(array_unique(array_merge($member_ids, $included_member_ids)));
        }
        if (!empty($excluded_member_ids)) {
            $member_ids = array_values(array_diff($member_ids, $excluded_member_ids));
        }

        if (empty($member_ids)) {
            wp_redirect(admin_url('admin.php?page=sc-add-invoice&sc_status=invoice_add_empty_selection'));
            exit;
        }

        $course_id = !empty($_POST['course_id']) ? absint($_POST['course_id']) : NULL;
        $expense_name = !empty($_POST['expense_name']) ? sanitize_text_field($_POST['expense_name']) : NULL;
        $invoice_description = !empty($_POST['invoice_description']) ? sanitize_textarea_field($_POST['invoice_description']) : NULL;
        if ($invoice_description !== null) {
            $invoice_description = wp_kses_post($invoice_description);
            $invoice_description = trim($invoice_description) === '' ? null : $invoice_description;
        }
        
        // دریافت مبلغ (حذف کاماها در صورت وجود)
        $amount_value = '';
        if (!empty($_POST['amount_raw'])) {
            $amount_value = sanitize_text_field($_POST['amount_raw']);
        } elseif (!empty($_POST['amount'])) {
            // حذف کاماها و کاراکترهای غیر عددی
            $amount_value = preg_replace('/[^0-9.]/', '', sanitize_text_field($_POST['amount']));
        }
        $manual_amount = !empty($amount_value) && is_numeric($amount_value) ? floatval($amount_value) : 0;
        
        // محاسبه مبلغ کل
        $total_amount = $manual_amount;
        
        // اگر دوره انتخاب شده باشد، هزینه دوره را اضافه کن
        if ($course_id) {
            $course = $wpdb->get_row($wpdb->prepare(
                "SELECT price FROM $courses_table WHERE id = %d AND deleted_at IS NULL AND is_active = 1",
                $course_id
            ));
            
            if ($course) {
                $total_amount += floatval($course->price);
            } else {
                // اگر دوره معتبر نبود، فقط مبلغ دستی را استفاده کن
                $course_id = NULL;
            }
        }
        
        $disable_penalty = isset($_POST['disable_penalty']) ? 1 : 0;

        $member_courses_table = $wpdb->prefix . 'sc_member_courses';
        $created_count = 0;
        $first_invoice_id = 0;

        foreach ($member_ids as $member_id) {
            // فقط کاربران فعال
            $member = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $members_table WHERE id = %d AND is_active = 1",
                $member_id
            ));
            if (!$member) {
                continue;
            }

            // بررسی member_course_id در صورت وجود دوره
            $member_course_id = NULL;
            if ($course_id) {
                $member_course = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM $member_courses_table WHERE member_id = %d AND course_id = %d",
                    $member_id,
                    $course_id
                ));
                if ($member_course) {
                    $member_course_id = $member_course->id;
                }
            }

            $invoice_data = [
                'member_id' => $member_id,
                'course_id' => $course_id ? $course_id : 0,
                'member_course_id' => $member_course_id,
                'woocommerce_order_id' => NULL,
                'amount' => $total_amount,
                'expense_name' => $expense_name,
                'invoice_description' => $invoice_description,
                'penalty_amount' => 0.00,
                'penalty_applied' => 0,
                'status' => 'pending',
                'payment_date' => NULL,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ];
            $invoice_data['disable_penalty'] = $disable_penalty;

            $format_array = ['%d', '%d', '%d', '%d', '%f', '%s', '%s', '%f', '%d', '%s', '%s', '%s', '%s', '%d'];
            if (!$course_id) {
                $invoice_data['course_id'] = 0;
            }
            if (!$member_course_id) {
                $invoice_data['member_course_id'] = NULL;
                $format_array[2] = '%s';
            }
            if (!$expense_name) {
                $invoice_data['expense_name'] = NULL;
                $format_array[5] = '%s';
            }
            if ($invoice_description === null) {
                $format_array[6] = '%s';
            }

            $inserted = $wpdb->insert(
                $invoices_table,
                $invoice_data,
                $format_array
            );
            if ($inserted === false) {
                continue;
            }

            $invoice_id = (int) $wpdb->insert_id;
            if ($first_invoice_id === 0) {
                $first_invoice_id = $invoice_id;
            }
            $created_count++;

            do_action('sc_invoice_created', $invoice_id);

            $order_result = sc_create_woocommerce_order_for_invoice($invoice_id, $member_id, $course_id, $total_amount, $expense_name);
            if (!empty($order_result['success']) && !empty($order_result['order_id'])) {
                $wpdb->update(
                    $invoices_table,
                    ['woocommerce_order_id' => $order_result['order_id'], 'updated_at' => current_time('mysql')],
                    ['id' => $invoice_id],
                    ['%d', '%s'],
                    ['%d']
                );
            }
        }

        if ($created_count > 0) {
            $redirect_url = add_query_arg(
                array(
                    'page' => 'sc-add-invoice',
                    'sc_status' => 'invoice_add_true',
                    'created_count' => $created_count,
                    'total' => count($member_ids),
                    'invoice_id' => $first_invoice_id,
                ),
                admin_url('admin.php')
            );
            wp_redirect($redirect_url);
            exit;
        }

        wp_redirect(admin_url('admin.php?page=sc-add-invoice&sc_status=invoice_add_error'));
        exit;
    }
}

/**
 * Courses management pages
 */
function sc_admin_courses_list_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'courses-list.php';
}

function sc_admin_add_course_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_courses';
    $course = false;
    if (isset($_GET['course_id'])) {
        $course_id = absint($_GET['course_id']);
        if ($course_id) {
            $course = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d AND deleted_at IS NULL", $course_id));
        }
    }
    $course_schedule_blocks = [];
    if (is_object($course) && !empty($course->id) && function_exists('sc_get_course_weekly_schedule_rows') && function_exists('sc_group_course_schedule_for_form')) {
        $course_schedule_blocks = sc_group_course_schedule_for_form(sc_get_course_weekly_schedule_rows((int) $course->id));
    }
    if (empty($course_schedule_blocks)) {
        $course_schedule_blocks = [
            ['wd' => [], 'start' => '08:00:00', 'end' => '10:00:00'],
        ];
    }
    $course_group_rows = [];
    $has_grouping = 0;
    $player_can_select_group = 0;
    if (is_object($course) && !empty($course->id)) {
        if (function_exists('sc_course_has_grouping_enabled')) {
            $has_grouping = sc_course_has_grouping_enabled((int) $course->id) ? 1 : 0;
        }
        if (function_exists('sc_course_player_can_select_group')) {
            $player_can_select_group = sc_course_player_can_select_group((int) $course->id) ? 1 : 0;
        } elseif (isset($course->player_can_select_group)) {
            $player_can_select_group = (int) $course->player_can_select_group;
        }
        if (function_exists('sc_get_course_groups')) {
            foreach (sc_get_course_groups((int) $course->id) as $grow) {
                $course_group_rows[] = [
                    'name' => isset($grow->group_name) ? (string) $grow->group_name : '',
                    'description' => isset($grow->description) ? (string) $grow->description : '',
                    'chapter_name' => isset($grow->chapter_name) ? (string) $grow->chapter_name : '',
                    'coach_id' => isset($grow->coach_id) ? (int) $grow->coach_id : 0,
                ];
            }
        }
    }
    if (empty($course_group_rows)) {
        $course_group_rows = [['name' => '', 'description' => '', 'chapter_name' => '', 'coach_id' => 0]];
    }
    include SC_TEMPLATES_ADMIN_DIR . 'course-add.php';
}

function procces_courses_table_data() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'list_courses.php';
    $GLOBALS['courses_list_table'] = new Courses_List_Table();
    $GLOBALS['courses_list_table']->prepare_items();
}

function callback_add_course_sufix() {
    if (isset($_GET['page']) && $_GET['page'] == 'sc-add-course' && isset($_POST['submit_course'])) {
        // بررسی و ایجاد جداول در صورت عدم وجود
        sc_check_and_create_tables();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_courses';

        $course_type_value = (isset($_POST['course_type']) && in_array($_POST['course_type'], ['group', 'private'], true))
            ? sanitize_text_field($_POST['course_type'])
            : 'group';
        $is_private_course = ($course_type_value === 'private');
        $private_variable_coach_pricing = ($is_private_course && isset($_POST['private_variable_coach_pricing'])) ? 1 : 0;
        
        // پردازش قیمت از price_raw
        $price_value = 0;
        if (isset($_POST['price_raw']) && !empty($_POST['price_raw'])) {
            // حذف کاماها و کاراکترهای غیر عددی
            $price_raw_cleaned = str_replace(',', '', sanitize_text_field($_POST['price_raw']));
            $price_raw_cleaned = preg_replace('/[^\d.]/', '', $price_raw_cleaned);
            $price_value = floatval($price_raw_cleaned);
        } elseif (isset($_POST['price']) && !empty($_POST['price'])) {
            // حذف کاماها و کاراکترهای غیر عددی
            $price_cleaned = str_replace(',', '', sanitize_text_field($_POST['price']));
            $price_cleaned = preg_replace('/[^\d.]/', '', $price_cleaned);
            $price_value = floatval($price_cleaned);
        }
        
        // اطمینان از اینکه قیمت عدد معتبر است
        if (!is_numeric($price_value) || $price_value < 0) {
            $price_value = 0;
        }
        
        // پردازش قیمت هر جلسه از price_per_session_raw
        $price_per_session_value = 0;
        if (isset($_POST['price_per_session_raw']) && !empty($_POST['price_per_session_raw'])) {
            // حذف کاماها و کاراکترهای غیر عددی
            $price_per_session_raw_cleaned = str_replace(',', '', sanitize_text_field($_POST['price_per_session_raw']));
            $price_per_session_raw_cleaned = preg_replace('/[^\d.]/', '', $price_per_session_raw_cleaned);
            $price_per_session_value = floatval($price_per_session_raw_cleaned);
        } elseif (isset($_POST['price_per_session']) && !empty($_POST['price_per_session'])) {
            // حذف کاماها و کاراکترهای غیر عددی
            $price_per_session_cleaned = str_replace(',', '', sanitize_text_field($_POST['price_per_session']));
            $price_per_session_cleaned = preg_replace('/[^\d.]/', '', $price_per_session_cleaned);
            $price_per_session_value = floatval($price_per_session_cleaned);
        }
        
        // اطمینان از اینکه قیمت هر جلسه عدد معتبر است
        if (!is_numeric($price_per_session_value) || $price_per_session_value < 0) {
            $price_per_session_value = 0;
        }

        if ($is_private_course && $private_variable_coach_pricing) {
            $parsed_packages = function_exists('sc_parse_private_session_options_from_post') ? sc_parse_private_session_options_from_post() : [];
            if (is_wp_error($parsed_packages)) {
                $pkg_redirect = ['page' => 'sc-add-course', 'sc_status' => 'course_pkg_error'];
                if (isset($_GET['course_id'])) {
                    $pkg_redirect['course_id'] = absint($_GET['course_id']);
                }
                $pkg_err_code = $parsed_packages->get_error_code();
                if ($pkg_err_code === 'private_sess_dup') {
                    $pkg_redirect['sc_error'] = 'duplicate_sessions';
                }
                wp_redirect(add_query_arg($pkg_redirect, admin_url('admin.php')));
                exit;
            }
            if (empty($parsed_packages)) {
                $fallback_sessions = !empty($_POST['sessions_count']) ? absint($_POST['sessions_count']) : 10;
                if ($fallback_sessions > 0) {
                    $parsed_packages = [
                        ['sessions' => $fallback_sessions, 'price' => 0.0],
                    ];
                }
            }
            $price_value = 0;
            $price_per_session_value = 0;
        } else {
            $parsed_packages = function_exists('sc_parse_course_packages_from_post') ? sc_parse_course_packages_from_post() : [];
            if (is_wp_error($parsed_packages)) {
                $pkg_redirect = ['page' => 'sc-add-course', 'sc_status' => 'course_pkg_error'];
                if (isset($_GET['course_id'])) {
                    $pkg_redirect['course_id'] = absint($_GET['course_id']);
                }
                if ($parsed_packages->get_error_code() === 'pkg_dup') {
                    $pkg_redirect['sc_error'] = 'duplicate_sessions';
                }
                wp_redirect(add_query_arg($pkg_redirect, admin_url('admin.php')));
                exit;
            }
        }

        $has_course_packages = !empty($parsed_packages);
        $course_redirect_base = ['page' => 'sc-add-course'];
        if (isset($_GET['course_id'])) {
            $course_redirect_base['course_id'] = absint($_GET['course_id']);
        }

        // Validation
        if (empty($_POST['title'])) {
            wp_redirect(add_query_arg(array_merge($course_redirect_base, ['sc_status' => 'course_title_required']), admin_url('admin.php')));
            exit;
        }

        if ($is_private_course && $private_variable_coach_pricing) {
            // قیمت سراسری لازم نیست
        } elseif ($is_private_course) {
            if (!$has_course_packages && $price_value <= 0 && $price_per_session_value <= 0) {
                wp_redirect(add_query_arg(array_merge($course_redirect_base, ['sc_status' => 'course_private_price_required']), admin_url('admin.php')));
                exit;
            }
        } elseif (!$has_course_packages && $price_value <= 0) {
            wp_redirect(add_query_arg(array_merge($course_redirect_base, ['sc_status' => 'course_price_required']), admin_url('admin.php')));
            exit;
        }
        
        
        // پردازش تاریخ شمسی به میلادی
        $start_date = NULL;
        if (!empty($_POST['start_date_shamsi'])) {
            $start_date = sc_shamsi_to_gregorian_date(sanitize_text_field($_POST['start_date_shamsi']));
        } elseif (!empty($_POST['start_date'])) {
            $start_date = sanitize_text_field($_POST['start_date']);
        }
        
        $end_date = NULL;
        if (!empty($_POST['end_date_shamsi'])) {
            $end_date = sc_shamsi_to_gregorian_date(sanitize_text_field($_POST['end_date_shamsi']));
        } elseif (!empty($_POST['end_date'])) {
            $end_date = sanitize_text_field($_POST['end_date']);
        }

        $posted_chapters = [];
        if (!empty($_POST['course_chapters']) && is_array($_POST['course_chapters'])) {
            foreach ($_POST['course_chapters'] as $ch_name) {
                $ch_name = sanitize_text_field((string) $ch_name);
                if ($ch_name !== '') {
                    $posted_chapters[] = $ch_name;
                }
            }
        }
        $posted_chapters = array_values(array_unique($posted_chapters));
        $primary_chapter = !empty($posted_chapters) ? $posted_chapters[0] : '';

        // برای دوره گروهی/خصوصی انتخاب حداقل یک شعبه الزامی است
        if (in_array($course_type_value, ['group', 'private'], true) && empty($posted_chapters)) {
            wp_redirect(add_query_arg(array_merge($course_redirect_base, ['sc_status' => 'course_chapter_required']), admin_url('admin.php')));
            exit;
        }

        $data = [
            'title' => sanitize_text_field($_POST['title']),
            'description' => isset($_POST['description']) && !empty($_POST['description']) ? sanitize_textarea_field($_POST['description']) : NULL,
            'image' => !empty($_POST['image']) ? esc_url_raw(sanitize_text_field(wp_unslash($_POST['image']))) : null,
            'price' => ($is_private_course && $private_variable_coach_pricing) ? 0 : $price_value,
            'price_per_session' => ($is_private_course && $private_variable_coach_pricing) ? 0 : $price_per_session_value,
            'capacity' => ($is_private_course && $private_variable_coach_pricing) ? null : (!empty($_POST['capacity']) ? intval($_POST['capacity']) : NULL),
            'sessions_count' => !empty($_POST['sessions_count']) ? intval($_POST['sessions_count']) : NULL,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'restriction_enabled' => isset($_POST['restriction_enabled']) ? 1 : 0,
            'allowed_teams' => !empty($_POST['allowed_teams']) && is_array($_POST['allowed_teams']) ? wp_json_encode(array_values(array_map('sanitize_text_field', $_POST['allowed_teams'])), JSON_UNESCAPED_UNICODE) : NULL,
            'allowed_levels' => !empty($_POST['allowed_levels']) && is_array($_POST['allowed_levels']) ? wp_json_encode(array_values(array_map('sanitize_text_field', $_POST['allowed_levels'])), JSON_UNESCAPED_UNICODE) : NULL,
            'allowed_gender' => (isset($_POST['allowed_gender']) && in_array($_POST['allowed_gender'], ['male', 'female', 'both'], true)) ? sanitize_text_field($_POST['allowed_gender']) : 'both',
            'course_type' => $course_type_value,
            'private_variable_coach_pricing' => $private_variable_coach_pricing,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'updated_at' => current_time('mysql'),
            'chapter' => $primary_chapter,
        ];
        $has_grouping_flag = isset($_POST['has_grouping']) ? 1 : 0;
        if (
            !$has_grouping_flag
            && function_exists('sc_course_groups_has_names_in_post')
            && sc_course_groups_has_names_in_post()
        ) {
            $has_grouping_flag = 1;
        }
        $player_can_select_group_flag = ($has_grouping_flag && isset($_POST['player_can_select_group'])) ? 1 : 0;
        if (function_exists('sc_courses_has_grouping_column') && sc_courses_has_grouping_column()) {
            $data['has_grouping'] = $has_grouping_flag;
        }
        if (function_exists('sc_courses_player_can_select_group_column') && sc_courses_player_can_select_group_column()) {
            $data['player_can_select_group'] = $player_can_select_group_flag;
        }

        $course_id = 0;
        if (!empty($_POST['course_id'])) {
            $course_id = absint($_POST['course_id']);
        } elseif (isset($_GET['course_id'])) {
            $course_id = absint($_GET['course_id']);
        }

        // بروزرسانی
        if ($course_id) {
            $old_course = $wpdb->get_row($wpdb->prepare("SELECT title, price, price_per_session, is_active FROM $table_name WHERE id = %d", $course_id), ARRAY_A);
            $format = [];
            foreach ($data as $key => $value) {
                if ($value === NULL) {
                    $format[] = '%s';
                } elseif (in_array($key, ['price', 'price_per_session'], true)) {
                    $format[] = '%f';
                } elseif (in_array($key, ['capacity', 'sessions_count', 'restriction_enabled', 'is_active', 'private_variable_coach_pricing', 'has_grouping', 'player_can_select_group'], true)) {
                    $format[] = '%d';
                } else {
                    $format[] = '%s';
                }
            }
            
            $updated = $wpdb->update(
                $table_name,
                $data,
                ['id' => $course_id],
                $format,
                ['%d']
            );

            if ($updated !== false) {
                if (function_exists('sc_replace_course_packages')) {
                    sc_replace_course_packages($course_id, $parsed_packages, ($course_type_value === 'private' && $private_variable_coach_pricing));
                }
                if (function_exists('sc_save_course_weekly_schedule_from_post')) {
                    sc_save_course_weekly_schedule_from_post($course_id);
                }
                if (function_exists('sc_save_course_chapters')) {
                    sc_save_course_chapters($course_id, $posted_chapters);
                }
                if (function_exists('sc_save_course_coach_assignments_from_post')) {
                    sc_save_course_coach_assignments_from_post($course_id, $posted_chapters);
                }
                if (function_exists('sc_save_course_groups_from_post')) {
                    sc_save_course_groups_from_post($course_id, (bool) $has_grouping_flag);
                }
                if (function_exists('sc_private_sync_branch_capacities_to_course_ceiling')) {
                    sc_private_sync_branch_capacities_to_course_ceiling($course_id);
                }
                if (function_exists('sc_maybe_notify_course_capacity_waitlist')) {
                    sc_maybe_notify_course_capacity_waitlist($course_id);
                }
                if (function_exists('sc_log_activity') && $old_course) {
                    sc_log_activity('updated', 'course', $course_id, 'دوره «' . $data['title'] . '» ویرایش شد', $old_course, ['title' => $data['title'], 'price' => $data['price'], 'is_active' => $data['is_active']]);
                }
                wp_redirect(admin_url('admin.php?page=sc-add-course&sc_status=course_updated&course_id=' . $course_id));
                exit;
            } else {
                // لاگ خطا برای دیباگ
                if ($wpdb->last_error) {
                    error_log('SC Course Update Error: ' . $wpdb->last_error);
                    error_log('SC Course Update Query: ' . $wpdb->last_query);
                    error_log('SC Course Update Data: ' . print_r($data, true));
                    error_log('SC Course Update Format: ' . print_r($format, true));
                }
                wp_redirect(admin_url('admin.php?page=sc-add-course&sc_status=course_update_error&course_id=' . $course_id));
                exit;
            }
        } 
        // اضافه کردن جدید
        else {
            // آماده‌سازی داده‌ها برای insert با ترتیب صحیح
            $insert_data = [
                'title' => sanitize_text_field($_POST['title']),
                'description' => isset($_POST['description']) && !empty($_POST['description']) ? sanitize_textarea_field($_POST['description']) : NULL,
                'image' => !empty($_POST['image']) ? esc_url_raw(sanitize_text_field(wp_unslash($_POST['image']))) : null,
                'price' => ($is_private_course && $private_variable_coach_pricing) ? 0 : $price_value,
                'price_per_session' => ($is_private_course && $private_variable_coach_pricing) ? 0 : $price_per_session_value,
                'capacity' => ($is_private_course && $private_variable_coach_pricing) ? null : (!empty($_POST['capacity']) ? intval($_POST['capacity']) : NULL),
                'sessions_count' => !empty($_POST['sessions_count']) ? intval($_POST['sessions_count']) : NULL,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'restriction_enabled' => isset($_POST['restriction_enabled']) ? 1 : 0,
                'allowed_teams' => !empty($_POST['allowed_teams']) && is_array($_POST['allowed_teams']) ? wp_json_encode(array_values(array_map('sanitize_text_field', $_POST['allowed_teams'])), JSON_UNESCAPED_UNICODE) : NULL,
                'allowed_levels' => !empty($_POST['allowed_levels']) && is_array($_POST['allowed_levels']) ? wp_json_encode(array_values(array_map('sanitize_text_field', $_POST['allowed_levels'])), JSON_UNESCAPED_UNICODE) : NULL,
                'allowed_gender' => (isset($_POST['allowed_gender']) && in_array($_POST['allowed_gender'], ['male', 'female', 'both'], true)) ? sanitize_text_field($_POST['allowed_gender']) : 'both',
                'course_type' => $course_type_value,
                'private_variable_coach_pricing' => $private_variable_coach_pricing,
                'chapter' => $primary_chapter,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ];
            if (function_exists('sc_courses_has_grouping_column') && sc_courses_has_grouping_column()) {
                $insert_data['has_grouping'] = $has_grouping_flag;
            }
            if (function_exists('sc_courses_player_can_select_group_column') && sc_courses_player_can_select_group_column()) {
                $insert_data['player_can_select_group'] = $player_can_select_group_flag;
            }
            
            $format = [];
            foreach ($insert_data as $key => $value) {
                if ($value === NULL) {
                    $format[] = '%s';
                } elseif (in_array($key, ['price', 'price_per_session'], true)) {
                    $format[] = '%f';
                } elseif (in_array($key, ['capacity', 'sessions_count', 'restriction_enabled', 'is_active', 'private_variable_coach_pricing', 'has_grouping', 'player_can_select_group'], true)) {
                    $format[] = '%d';
                } else {
                    $format[] = '%s';
                }
            }
            
            $inserted = $wpdb->insert(
                $table_name, 
                $insert_data,
                $format
            );

            if ($inserted !== false) {
                $insert_id = $wpdb->insert_id;
                if (function_exists('sc_replace_course_packages')) {
                    sc_replace_course_packages($insert_id, $parsed_packages, ($course_type_value === 'private' && $private_variable_coach_pricing));
                }
                if (function_exists('sc_save_course_weekly_schedule_from_post')) {
                    sc_save_course_weekly_schedule_from_post($insert_id);
                }
                if (function_exists('sc_save_course_chapters')) {
                    sc_save_course_chapters($insert_id, $posted_chapters);
                }
                if (function_exists('sc_save_course_coach_assignments_from_post')) {
                    sc_save_course_coach_assignments_from_post($insert_id, $posted_chapters);
                }
                if (function_exists('sc_save_course_groups_from_post')) {
                    sc_save_course_groups_from_post($insert_id, (bool) $has_grouping_flag);
                }
                if (function_exists('sc_private_sync_branch_capacities_to_course_ceiling')) {
                    sc_private_sync_branch_capacities_to_course_ceiling($insert_id);
                }
                if (function_exists('sc_log_activity')) {
                    sc_log_activity('created', 'course', $insert_id, 'دوره «' . $insert_data['title'] . '» ایجاد شد', null, ['title' => $insert_data['title'], 'price' => $insert_data['price'], 'is_active' => $insert_data['is_active']]);
                }
                wp_redirect(admin_url('admin.php?page=sc-add-course&sc_status=course_add_true&course_id=' . $insert_id));
                exit;
            } else {
                // لاگ خطا برای دیباگ
                if ($wpdb->last_error) {
                    error_log('SC Course Insert Error: ' . $wpdb->last_error);
                    error_log('SC Course Insert Query: ' . $wpdb->last_query);
                    error_log('SC Course Insert Data: ' . print_r($insert_data, true));
                    error_log('SC Course Insert Format: ' . print_r($format, true));
                }
                wp_redirect(admin_url('admin.php?page=sc-add-course&sc_status=course_db_error'));
                exit;
            }
        }
    }
}



//for save data in new member -> wpdb
function callback_add_member_sufix(){

    // فقط برای ویرایش کاربر (وقتی player_id وجود دارد)
    if(isset($_GET['page']) && $_GET['page'] == 'sc-add-member' && isset($_POST['submit_player']) && isset($_GET['player_id']) && !empty($_GET['player_id'])) {
       // بررسی و ایجاد جداول در صورت عدم وجود
       sc_check_and_create_tables();
           
       global $wpdb;
       $table_name = $wpdb->prefix . 'sc_members';
       $player_id = isset($_GET['player_id']) ? absint($_GET['player_id']) : 0;
       $existing_for_validation = $player_id
           ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d LIMIT 1", $player_id))
           : null;

       if ($existing_for_validation && function_exists('sc_get_player_info_field_rules') && function_exists('sc_get_player_info_builtin_fields')) {
           $merge_rules = sc_get_player_info_field_rules();
           $merge_builtin = sc_get_player_info_builtin_fields();
           foreach ($merge_builtin as $field_key => $meta) {
               if (!sc_player_info_is_field_visible($field_key, $merge_rules) && property_exists($existing_for_validation, $field_key)) {
                   $existing_val = $existing_for_validation->{$field_key};
                   if (in_array($field_key, ['health_verified', 'info_verified'], true)) {
                       $_POST[$field_key] = (int) $existing_val === 1 ? '1' : '';
                   } else {
                       $_POST[$field_key] = $existing_val !== null ? (string) $existing_val : '';
                   }
               }
           }
       }
       
       // Validation - بررسی فیلدهای اجباری
       $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
       $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
       $national_id = isset($_POST['national_id']) ? trim($_POST['national_id']) : '';
       $remaining_sessions = isset($_POST['remaining_sessions']) ? $_POST['remaining_sessions'] : '';
       $player_id_corse_sessions = isset($_POST['player_id']) ? $_POST['player_id'] : '';

       if (function_exists('sc_player_info_validate_required_fields')) {
           $required_errors = sc_player_info_validate_required_fields($_POST, $_FILES, $existing_for_validation);
           if (!empty($required_errors)) {
               set_transient('sc_member_save_errors_' . get_current_user_id(), $required_errors, 60);
               wp_redirect(admin_url('admin.php?page=sc-add-member&sc_status=validation_error&player_id=' . $player_id));
               exit;
           }
       }
       
       if (empty($first_name) || empty($last_name) || empty($national_id)) {
           wp_redirect(admin_url('admin.php?page=sc-add-member&sc_status=add_error&player_id=' . $player_id));
           exit;
       }
       
       // آماده‌سازی داده‌ها
       $data = [
        'first_name'           => sanitize_text_field($first_name),
        'last_name'            => sanitize_text_field($last_name),
        'national_id'          => sanitize_text_field($national_id),
        'health_verified'      => isset($_POST['health_verified']) ? 1 : 0,
        'info_verified'        => isset($_POST['info_verified']) ? 1 : 0,
        'identity_verified'    => isset($_POST['identity_verified']) ? 1 : 0,
        'is_active'            => isset($_POST['is_active']) ? 1 : 0,
        'disable_auto_invoice'            => isset($_POST['disable_auto_invoice']) ? 1 : 0,
        'member_type'          => (isset($_POST['member_type']) && $_POST['member_type'] === 'team') ? 'team' : 'normal',
        'created_at'           => current_time('mysql'),
        'updated_at'           => current_time('mysql'),
       ];
       $new_identity_verified = isset($_POST['identity_verified']) ? 1 : 0;
       
       // فیلدهای اختیاری - همیشه به‌روزرسانی می‌شوند (حتی اگر خالی باشند)
       // برای فیلدهای متنی: اگر خالی باشند، NULL ذخیره می‌شود
       $data['father_name'] = isset($_POST['father_name']) && !empty(trim($_POST['father_name'])) ? sanitize_text_field($_POST['father_name']) : NULL;
       $data['player_phone'] = isset($_POST['player_phone']) && !empty(trim($_POST['player_phone'])) ? sanitize_text_field($_POST['player_phone']) : NULL;
       $data['father_phone'] = isset($_POST['father_phone']) && !empty(trim($_POST['father_phone'])) ? sanitize_text_field($_POST['father_phone']) : NULL;
       $data['mother_phone'] = isset($_POST['mother_phone']) && !empty(trim($_POST['mother_phone'])) ? sanitize_text_field($_POST['mother_phone']) : NULL;
       $data['landline_phone'] = isset($_POST['landline_phone']) && !empty(trim($_POST['landline_phone'])) ? sanitize_text_field($_POST['landline_phone']) : NULL;
       $data['province'] = isset($_POST['province']) && !empty(trim($_POST['province'])) ? sanitize_text_field($_POST['province']) : NULL;
       $data['city'] = isset($_POST['city']) && !empty(trim($_POST['city'])) ? sanitize_text_field($_POST['city']) : NULL;
       $data['gender'] = isset($_POST['gender']) && in_array($_POST['gender'], ['male', 'female'], true) ? sanitize_text_field($_POST['gender']) : NULL;
       $data['birth_date_shamsi'] = isset($_POST['birth_date_shamsi']) && !empty(trim($_POST['birth_date_shamsi'])) ? sanitize_text_field($_POST['birth_date_shamsi']) : NULL;
       $data['insurance_expiry_date_shamsi'] = isset($_POST['insurance_expiry_date_shamsi']) && !empty(trim($_POST['insurance_expiry_date_shamsi'])) ? sanitize_text_field($_POST['insurance_expiry_date_shamsi']) : NULL;
       
       // پردازش تاریخ تولد میلادی
       $birth_date_gregorian = NULL;
       if (!empty($data['birth_date_shamsi'])) {
           $birth_date_gregorian = sc_shamsi_to_gregorian_date($data['birth_date_shamsi']);
       } elseif (isset($_POST['birth_date_gregorian']) && !empty(trim($_POST['birth_date_gregorian']))) {
           $birth_date_gregorian = sanitize_text_field($_POST['birth_date_gregorian']);
       }
       $data['birth_date_gregorian'] = $birth_date_gregorian;
       
       // پردازش تاریخ انقضا بیمه میلادی
       $insurance_expiry_date_gregorian = NULL;
       if (!empty($data['insurance_expiry_date_shamsi'])) {
           $insurance_expiry_date_gregorian = sc_shamsi_to_gregorian_date($data['insurance_expiry_date_shamsi']);
       } elseif (isset($_POST['insurance_expiry_date_gregorian']) && !empty(trim($_POST['insurance_expiry_date_gregorian']))) {
           $insurance_expiry_date_gregorian = sanitize_text_field($_POST['insurance_expiry_date_gregorian']);
       }
       $data['insurance_expiry_date_gregorian'] = $insurance_expiry_date_gregorian;
       $data['personal_photo'] = isset($_POST['personal_photo']) && !empty(trim($_POST['personal_photo'])) ? esc_url_raw($_POST['personal_photo']) : NULL;
       $data['id_card_photo'] = isset($_POST['id_card_photo']) && !empty(trim($_POST['id_card_photo'])) ? esc_url_raw($_POST['id_card_photo']) : NULL;
       $data['sport_insurance_photo'] = isset($_POST['sport_insurance_photo']) && !empty(trim($_POST['sport_insurance_photo'])) ? esc_url_raw($_POST['sport_insurance_photo']) : NULL;
       $data['medical_condition'] = isset($_POST['medical_condition']) && !empty(trim($_POST['medical_condition'])) ? sanitize_textarea_field($_POST['medical_condition']) : NULL;
       $data['sports_history'] = isset($_POST['sports_history']) && !empty(trim($_POST['sports_history'])) ? sanitize_textarea_field($_POST['sports_history']) : NULL;
       $data['additional_info'] = isset($_POST['additional_info']) && !empty(trim($_POST['additional_info'])) ? sanitize_textarea_field($_POST['additional_info']) : NULL;
       $data['skill_level'] = isset($_POST['skill_level']) && !empty(trim($_POST['skill_level'])) ? sanitize_text_field($_POST['skill_level']) : NULL;
       $data['team_player'] = isset($_POST['team_player']) && !empty(trim($_POST['team_player'])) ? sanitize_text_field($_POST['team_player']) : NULL;

       $member_user_id_for_custom = ($existing_for_validation && !empty($existing_for_validation->user_id))
           ? (int) $existing_for_validation->user_id
           : get_current_user_id();
       if (function_exists('sc_player_info_extract_custom_values')) {
           $data['member_extra_fields'] = sc_player_info_extract_custom_values($_POST, $_FILES, $member_user_id_for_custom, $existing_for_validation);
       }
       if (function_exists('sc_player_info_apply_hidden_field_policy')) {
           sc_player_info_apply_hidden_field_policy($data, $existing_for_validation);
       }
                    
        // بروزرسانی
        if ($player_id) {
            // آماده‌سازی format برای update
            $format = [];
            foreach ($data as $key => $value) {
                if ($value === NULL) {
                    $format[] = '%s'; // NULL
                } elseif (in_array($key, ['health_verified', 'info_verified', 'identity_verified', 'is_active', 'user_id'])) {
                    $format[] = '%d'; // integer
                } elseif (in_array($key, ['price', 'capacity', 'sessions_count'])) {
                    $format[] = '%d'; // integer (برای دوره‌ها)
                } else {
                    $format[] = '%s'; // string
                }
            }
            
            $old_member = $wpdb->get_row($wpdb->prepare("SELECT first_name, last_name, national_id, player_phone, is_active, identity_verified FROM $table_name WHERE id = %d", $player_id), ARRAY_A);
            $updated = $wpdb->update(
                $table_name,
                $data,
                ['id' => $player_id],
                $format,
                ['%d']
            );

            if ($updated !== false) {
                // پردازش username و password برای به‌روزرسانی یا ایجاد کاربر WordPress
                $username = isset($_POST['username']) ? trim($_POST['username']) : '';
                $password = isset($_POST['password']) ? trim($_POST['password']) : '';
                
                // دریافت user_id فعلی
                $current_user_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT user_id FROM $table_name WHERE id = %d",
                    $player_id
                ));
                
                if (!empty($username)) {
                    // اگر username وارد شده و user_id وجود دارد، کاربر را به‌روزرسانی کن
                    if ($current_user_id) {
                        $user = get_userdata($current_user_id);
                        if ($user) {
                            // به‌روزرسانی username (اگر تغییر کرده باشد)
                            // توجه: WordPress به صورت پیش‌فرض اجازه تغییر username را نمی‌دهد
                            // برای تغییر username باید از plugin یا کد خاص استفاده کرد
                            // در اینجا فقط اگر username خالی نباشد و متفاوت باشد، لاگ می‌کنیم
                            if ($user->user_login !== $username && !empty($username)) {
                                // بررسی اینکه username جدید تکراری نباشد
                                if (!username_exists($username)) {
                                    // تغییر username در دیتابیس (این کار پیشنهاد نمی‌شود اما برای سازگاری انجام می‌شود)
                                    $wpdb->update(
                                        $wpdb->users,
                                        ['user_login' => sanitize_user($username, true)],
                                        ['ID' => $current_user_id],
                                        ['%s'],
                                        ['%d']
                                    );
                                    
                                    // پاک کردن cache
                                    clean_user_cache($current_user_id);
                                } else {
                                    error_log('SC Member: Username already exists - ' . $username);
                                }
                            }
                            
                            // به‌روزرسانی رمز عبور (اگر وارد شده باشد)
                            if (!empty($password)) {
                                wp_set_password($password, $current_user_id);
                            }
                            
                            // به‌روزرسانی اطلاعات کاربر
                            wp_update_user([
                                'ID' => $current_user_id,
                                'first_name' => $data['first_name'],
                                'last_name' => $data['last_name'],
                                'display_name' => $data['first_name'] . ' ' . $data['last_name']
                            ]);
                            
                            // به‌روزرسانی اطلاعات billing
                            if (!empty($data['player_phone'])) {
                                update_user_meta($current_user_id, 'billing_phone', $data['player_phone']);
                            }
                        }
                    } else {
                        // اگر user_id وجود ندارد، کاربر جدید ایجاد کن
                        // اگر username وارد نشده، به صورت خودکار از کد ملی یا شماره تماس استفاده می‌کنیم
                        if (empty($username)) {
                            // اول از کد ملی استفاده می‌کنیم
                            if (!empty($data['national_id'])) {
                                $username = sanitize_user($data['national_id'], true);
                            } 
                            // اگر کد ملی هم نبود، از شماره تماس استفاده می‌کنیم
                            elseif (!empty($data['player_phone'])) {
                                $username = sanitize_user($data['player_phone'], true);
                            }
                            // اگر هیچکدام نبود، از نام و نام خانوادگی استفاده می‌کنیم
                            else {
                                $username = sanitize_user($data['first_name'] . '_' . $data['last_name'], true);
                            }
                            
                            // بررسی تکراری بودن username و اضافه کردن عدد در صورت نیاز
                            $original_username = $username;
                            $counter = 1;
                            while (username_exists($username)) {
                                $username = $original_username . '_' . $counter;
                                $counter++;
                            }
                        }
                        
                        // اگر password وارد نشده، به صورت خودکار یک رمز عبور تصادفی ایجاد می‌کنیم
                        if (empty($password)) {
                            $password = wp_generate_password(12, false);
                        }
                        
                        // بررسی اینکه username تکراری نباشد (اگر به صورت دستی وارد شده باشد)
                        if (!username_exists($username)) {
                            // ایجاد email از شماره تماس یا username
                            $email = !empty($data['player_phone']) ? sanitize_email($data['player_phone'] . '@sportclub.local') : sanitize_email($username . '@sportclub.local');
                            
                            // اگر email معتبر نیست، از username استفاده کن
                            if (!is_email($email)) {
                                 $email = 'user_' . wp_generate_password(8, false) . '@example.local';
                            }
                            
                            $new_user_id = wp_create_user($username, $password, $email);
                            
                            if (!is_wp_error($new_user_id)) {
                                // تنظیم نقش کاربر (customer برای WooCommerce)
                                $user = new WP_User($new_user_id);
                                $user->set_role('customer');
                                
                                // تنظیم اطلاعات کاربر
                                wp_update_user([
                                    'ID' => $new_user_id,
                                    'first_name' => $data['first_name'],
                                    'last_name' => $data['last_name'],
                                    'display_name' => $data['first_name'] . ' ' . $data['last_name']
                                ]);
                                
                                // تنظیم اطلاعات billing
                                if (!empty($data['player_phone'])) {
                                    update_user_meta($new_user_id, 'billing_phone', $data['player_phone']);
                                }
                                
                                // ذخیره user_id در جدول members
                                $wpdb->update(
                                    $table_name,
                                    ['user_id' => $new_user_id],
                                    ['id' => $player_id],
                                    ['%d'],
                                    ['%d']
                                );
                            } else {
                                // اگر خطا در ایجاد کاربر بود، لاگ کن
                                error_log('SC Member: Error creating WordPress user - ' . $new_user_id->get_error_message());
                            }
                        } else {
                            // اگر username تکراری بود، لاگ کن
                            error_log('SC Member: Username already exists - ' . $username);
                        }
                    }
                }
                
                // ذخیره دوره‌های بازیکن
                $course_ids = isset($_POST['courses']) && is_array($_POST['courses']) ? array_map('absint', $_POST['courses']) : [];
                $course_flags_raw = isset($_POST['course_flags']) && is_array($_POST['course_flags']) ? $_POST['course_flags'] : [];
                $course_flags = sc_parse_member_course_flags_from_post($course_flags_raw, $course_ids);
                $course_package_sessions = [];
                if (isset($_POST['course_enrollment_package']) && is_array($_POST['course_enrollment_package'])) {
                    foreach ($_POST['course_enrollment_package'] as $cid => $sess) {
                        $course_package_sessions[absint($cid)] = absint($sess);
                    }
                }
                foreach ($course_ids as $cid_pkg) {
                    $cid_pkg = absint($cid_pkg);
                    if (!$cid_pkg) {
                        continue;
                    }
                    if (function_exists('sc_course_has_packages') && sc_course_has_packages($cid_pkg)) {
                        $sel = isset($course_package_sessions[$cid_pkg]) ? absint($course_package_sessions[$cid_pkg]) : 0;
                        if (!$sel || !function_exists('sc_get_course_package_by_sessions') || !sc_get_course_package_by_sessions($cid_pkg, $sel)) {
                            wp_redirect(admin_url('admin.php?page=sc-add-member&sc_status=member_pkg_error&player_id=' . $player_id));
                            exit;
                        }
                    }
                }
                sc_save_member_courses($player_id, $course_ids, $course_flags, $course_package_sessions, sc_parse_member_course_assignments_from_post());
                sc_update_profile_completed_status($player_id);
                if (isset($old_member['identity_verified']) && (int)$old_member['identity_verified'] !== 1 && $new_identity_verified === 1 && function_exists('sc_send_identity_verified_notifications')) {
                    sc_send_identity_verified_notifications($player_id);
                }
                if (function_exists('sc_log_activity') && $old_member) {
                    sc_log_activity('updated', 'member', $player_id, 'عضو «' . ($data['first_name'] . ' ' . $data['last_name']) . '» ویرایش شد', $old_member, ['first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'national_id' => $data['national_id'], 'is_active' => $data['is_active']]);
                }

                if (isset($_POST['remaining_sessions']) && is_array($_POST['remaining_sessions'])) {
                    $member_id = (int) $player_id;
                    $table = $wpdb->prefix . 'sc_member_courses';
                    foreach ($_POST['remaining_sessions'] as $course_id => $remaining) {
                        $course_id = (int) $course_id;
                        if ($course_id < 1) {
                            continue;
                        }
                        $remaining = (int) $remaining;
                        if ($remaining < 0) {
                            $remaining = 0;
                        }
                        $wpdb->update(
                            $table,
                            ['remaining_sessions' => $remaining],
                            ['member_id' => $member_id, 'course_id' => $course_id],
                            ['%d'],
                            ['%d', '%d']
                        );
                    }
                }




                wp_redirect(admin_url('admin.php?page=sc-add-member&sc_status=updated&player_id=' . $player_id));
                exit;
            } else {
                // نمایش خطای دیتابیس برای دیباگ
                if ($wpdb->last_error) {
                    error_log('WP Update Error: ' . $wpdb->last_error);
                    error_log('WP Last Query: ' . $wpdb->last_query);
                }
                wp_redirect(admin_url('admin.php?page=sc-add-member&sc_status=update_error&player_id=' . $player_id));
                exit;
            }
        } 
        // اضافه کردن جدید
        else {
            // بررسی تکراری بودن کد ملی
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE national_id = %s",
                $data['national_id']
            ));
            
            if ($existing) {
                wp_redirect(admin_url('admin.php?page=sc-add-member&sc_status=add_error'));
                exit;
            }
            
            // آماده‌سازی format array برای insert
            $format = [];
            foreach ($data as $key => $value) {
                if ($value === NULL) {
                    $format[] = '%s'; // NULL
                } elseif (in_array($key, ['health_verified', 'info_verified', 'identity_verified', 'is_active', 'user_id'])) {
                    $format[] = '%d'; // integer
                } else {
                    $format[] = '%s'; // string
                }
            }
            
            $inserted = $wpdb->insert($table_name, $data, $format);

            if ($inserted === false) {
                // نمایش خطای دیتابیس برای دیباگ
                if ($wpdb->last_error) {
                    error_log('WP Insert Member Error: ' . $wpdb->last_error);
                    error_log('WP Insert Member Query: ' . $wpdb->last_query);
                    error_log('WP Insert Member Data: ' . print_r($data, true));
                    error_log('WP Insert Member Format: ' . print_r($format, true));
                    error_log('WP Insert Member Data Count: ' . count($data));
                    error_log('WP Insert Member Format Count: ' . count($format));
                }
                wp_redirect(admin_url('admin.php?page=sc-add-member&sc_status=add_error'));
                exit;
            }

            if ($inserted !== false) {
                $insert_id = $wpdb->insert_id;
                
                // ایجاد کاربر WordPress
                $username = isset($_POST['username']) ? trim($_POST['username']) : '';
                $password = isset($_POST['password']) ? trim($_POST['password']) : '';
                $user_id = null;
                
                // اگر username وارد نشده، به صورت خودکار از کد ملی یا شماره تماس استفاده می‌کنیم
                if (empty($username)) {
                    // اول از کد ملی استفاده می‌کنیم
                    if (!empty($data['national_id'])) {
                        $username = sanitize_user($data['national_id'], true);
                    } 
                    // اگر کد ملی هم نبود، از شماره تماس استفاده می‌کنیم
                    elseif (!empty($data['player_phone'])) {
                        $username = sanitize_user($data['player_phone'], true);
                    }
                    // اگر هیچکدام نبود، از نام و نام خانوادگی استفاده می‌کنیم
                    else {
                        $username = sanitize_user($data['first_name'] . '_' . $data['last_name'], true);
                    }
                    
                    // بررسی تکراری بودن username و اضافه کردن عدد در صورت نیاز
                    $original_username = $username;
                    $counter = 1;
                    while (username_exists($username)) {
                        $username = $original_username . '_' . $counter;
                        $counter++;
                    }
                }
                
                // اگر password وارد نشده، به صورت خودکار یک رمز عبور تصادفی ایجاد می‌کنیم
                if (empty($password)) {
                    $password = wp_generate_password(12, false);
                }
                
                // بررسی اینکه username تکراری نباشد (اگر به صورت دستی وارد شده باشد)
                if (!username_exists($username)) {
                    // ایجاد email از شماره تماس یا username
                    $email = !empty($data['player_phone']) ? sanitize_email($data['player_phone'] . '@sportclub.local') : sanitize_email($username . '@sportclub.local');
                    
                    // اگر email معتبر نیست، از username استفاده کن
                    if (!is_email($email)) {
                         $email = 'user_' . wp_generate_password(8, false) . '@example.local';
                    }
                    
                    // ایجاد کاربر WordPress
                    $user_id = wp_create_user($username, $password, $email);
                    
                    if (!is_wp_error($user_id)) {
                        // تنظیم نقش کاربر (customer برای WooCommerce)
                        $user = new WP_User($user_id);
                        $user->set_role('customer');
                        
                        // تنظیم اطلاعات کاربر
                        wp_update_user([
                            'ID' => $user_id,
                            'first_name' => $data['first_name'],
                            'last_name' => $data['last_name'],
                            'display_name' => $data['first_name'] . ' ' . $data['last_name']
                        ]);
                        
                        // تنظیم اطلاعات billing
                        if (!empty($data['player_phone'])) {
                            update_user_meta($user_id, 'billing_phone', $data['player_phone']);
                        }
                        
                        // ذخیره user_id در جدول members
                        $wpdb->update(
                            $table_name,
                            ['user_id' => $user_id],
                            ['id' => $insert_id],
                            ['%d'],
                            ['%d']
                        );
                    } else {
                        // اگر خطا در ایجاد کاربر بود، لاگ کن
                        error_log('SC Member: Error creating WordPress user - ' . $user_id->get_error_message());
                    }
                } else {
                    // اگر username تکراری بود، لاگ کن
                    error_log('SC Member: Username already exists - ' . $username);
                }
                
                // ذخیره دوره‌های بازیکن
                $course_ids = isset($_POST['courses']) && is_array($_POST['courses']) ? array_map('absint', $_POST['courses']) : [];
                // دریافت فلگ‌های دوره‌ها - مهم: فلگ‌ها مستقل از تیک دوره هستند
                $course_flags_raw = isset($_POST['course_flags']) && is_array($_POST['course_flags']) ? $_POST['course_flags'] : [];
                $course_flags = sc_parse_member_course_flags_from_post($course_flags_raw, $course_ids);
                $course_package_sessions = [];
                if (isset($_POST['course_enrollment_package']) && is_array($_POST['course_enrollment_package'])) {
                    foreach ($_POST['course_enrollment_package'] as $cid => $sess) {
                        $course_package_sessions[absint($cid)] = absint($sess);
                    }
                }
                foreach ($course_ids as $cid_pkg) {
                    $cid_pkg = absint($cid_pkg);
                    if (!$cid_pkg) {
                        continue;
                    }
                    if (function_exists('sc_course_has_packages') && sc_course_has_packages($cid_pkg)) {
                        $sel = isset($course_package_sessions[$cid_pkg]) ? absint($course_package_sessions[$cid_pkg]) : 0;
                        if (!$sel || !function_exists('sc_get_course_package_by_sessions') || !sc_get_course_package_by_sessions($cid_pkg, $sel)) {
                            wp_redirect(admin_url('admin.php?page=sc-add-member&sc_status=member_pkg_error'));
                            exit;
                        }
                    }
                }
                sc_save_member_courses($insert_id, $course_ids, $course_flags, $course_package_sessions, sc_parse_member_course_assignments_from_post());
                sc_update_profile_completed_status($insert_id);
                if (function_exists('sc_log_activity')) {
                    sc_log_activity('created', 'member', $insert_id, 'عضو «' . ($data['first_name'] . ' ' . $data['last_name']) . '» ایجاد شد', null, ['first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'national_id' => $data['national_id']]);
                }
                wp_redirect(admin_url('admin.php?page=sc-add-member&sc_status=add_true&player_id=' . $insert_id));
                exit;
            } else {
                // نمایش خطای دیتابیس برای دیباگ
                if ($wpdb->last_error) {
                    error_log('WP Insert Error: ' . $wpdb->last_error);
                    error_log('WP Last Query: ' . $wpdb->last_query);
                }
                wp_redirect(admin_url('admin.php?page=sc-add-member&sc_status=add_error'));
                exit;
            }
        }
    }
 

}

/**
 * Save event custom fields
 */
function sc_save_event_fields($event_id, $post_data) {
    global $wpdb;
    $event_fields_table = $wpdb->prefix . 'sc_event_fields';
    
    // دریافت فیلدهای موجود
    $existing_fields = $wpdb->get_results($wpdb->prepare(
        "SELECT id FROM $event_fields_table WHERE event_id = %d",
        $event_id
    ));
    $existing_field_ids = array_map(function($f) { return $f->id; }, $existing_fields);
    
    // پردازش فیلدهای ارسال شده
    $submitted_field_ids = [];
    $field_order = 0;
    
    if (isset($post_data['event_fields']) && is_array($post_data['event_fields'])) {
        foreach ($post_data['event_fields'] as $field_key => $field_data) {
            $field_order++;
            
            // بررسی اینکه آیا فیلد جدید است یا موجود
            $is_new = (strpos($field_key, 'new_') === 0);
            $field_id = $is_new ? null : absint($field_key);
            
            // اعتبارسنجی
            if (empty($field_data['field_name']) || empty($field_data['field_type'])) {
                continue;
            }
            
            $field_name = sanitize_text_field($field_data['field_name']);
            $field_type = sanitize_text_field($field_data['field_type']);
            $is_required = isset($field_data['is_required']) ? 1 : 0;
            
            // پردازش field_options برای نوع select
            $field_options = null;
            if ($field_type === 'select' && !empty($field_data['field_options'])) {
                $options_string = sanitize_text_field($field_data['field_options']);
                $options_array = array_map('trim', explode(',', $options_string));
                $options_array = array_filter($options_array); // حذف مقادیر خالی
                if (!empty($options_array)) {
                    $field_options = json_encode(['options' => $options_array]);
                }
            }
            
            if ($is_new) {
                // افزودن فیلد جدید
                $wpdb->insert(
                    $event_fields_table,
                    [
                        'event_id' => $event_id,
                        'field_name' => $field_name,
                        'field_type' => $field_type,
                        'field_options' => $field_options,
                        'is_required' => $is_required,
                        'field_order' => $field_order,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ],
                    ['%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
                );
            } else {
                // به‌روزرسانی فیلد موجود
                $submitted_field_ids[] = $field_id;
                
                $wpdb->update(
                    $event_fields_table,
                    [
                        'field_name' => $field_name,
                        'field_type' => $field_type,
                        'field_options' => $field_options,
                        'is_required' => $is_required,
                        'field_order' => $field_order,
                        'updated_at' => current_time('mysql')
                    ],
                    ['id' => $field_id],
                    ['%s', '%s', '%s', '%d', '%d', '%s'],
                    ['%d']
                );
            }
        }
    }
    
    // حذف فیلدهایی که دیگر وجود ندارند
    $fields_to_delete = array_diff($existing_field_ids, $submitted_field_ids);
    if (!empty($fields_to_delete)) {
        $placeholders = implode(',', array_fill(0, count($fields_to_delete), '%d'));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $event_fields_table WHERE id IN ($placeholders)",
            $fields_to_delete
        ));
    }
}

/**
 * تبدیل POST فلگ‌های دوره به آرایهٔ نرمال‌شده.
 * اگر برای یک دورهٔ فعال هیچ فلگی POST نشود (همه تیک‌ها برداشته)، کلید course_flags در $_POST نیست؛
 * برای هر course_id انتخاب‌شده یک آرایهٔ خالی می‌گذاریم تا خالی‌سازی در DB اعمال شود.
 *
 * @param array $course_flags_raw
 * @param array<int,int> $course_ids
 * @return array<int, array<int, string>>
 */
function sc_parse_member_course_flags_from_post($course_flags_raw, $course_ids) {
    if (!is_array($course_flags_raw)) {
        $course_flags_raw = [];
    }
    $course_flags = [];
    foreach ($course_flags_raw as $course_id => $flags) {
        $course_id_int = absint($course_id);
        if (!$course_id_int || !is_array($flags)) {
            continue;
        }
        $flags_array = [];
        if (isset($flags['paused']) && (string) $flags['paused'] === '1') {
            $flags_array[] = 'paused';
        }
        if (isset($flags['completed']) && (string) $flags['completed'] === '1') {
            $flags_array[] = 'completed';
        }
        if (isset($flags['canceled']) && (string) $flags['canceled'] === '1') {
            $flags_array[] = 'canceled';
        }
        $course_flags[$course_id_int] = $flags_array;
    }
    if (is_array($course_ids)) {
        foreach ($course_ids as $cid) {
            $cid = absint($cid);
            if ($cid && !array_key_exists($cid, $course_flags)) {
                $course_flags[$cid] = [];
            }
        }
    }
    return $course_flags;
}

/**
 * Save member courses
 *
 * @param array<int,int> $course_package_sessions course_id => تعداد جلسهٔ پکیج انتخابی (در صورت وجود پکیج برای دوره)
 * @param array<int,array{chapter:string,coach_id:int}> $course_assignments
 */
function sc_save_member_courses($member_id, $course_ids, $course_flags = [], $course_package_sessions = [], $course_assignments = []) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sc_member_courses';

    $pre_counting_courses = [];
    if ($member_id) {
        $pre_counting_courses = $wpdb->get_col($wpdb->prepare(
            "SELECT course_id FROM $table_name WHERE member_id = %d AND status = 'active' AND (course_status_flags IS NULL OR TRIM(course_status_flags) = '')",
            $member_id
        ));
    }

    $resolve_sessions = static function ($course_id, $course_package_sessions) {
        $course_id = absint($course_id);
        $pkg_sel = isset($course_package_sessions[$course_id]) ? absint($course_package_sessions[$course_id]) : 0;
        if (function_exists('sc_member_course_session_fields_for_course')) {
            return sc_member_course_session_fields_for_course($course_id, $pkg_sel > 0 ? $pkg_sel : null);
        }
        return ['enrollment_sessions' => null, 'total_sessions' => 0, 'remaining_sessions' => 0];
    };

    $resolve_assignment = static function ($course_id, $existing_chapter = '', $existing_coach_id = 0, $existing_group = '') use ($course_assignments) {
        $course_id = absint($course_id);
        $sel_chapter = isset($course_assignments[$course_id]['chapter']) ? (string) $course_assignments[$course_id]['chapter'] : '';
        $sel_coach = isset($course_assignments[$course_id]['coach_id']) ? absint($course_assignments[$course_id]['coach_id']) : 0;
        $sel_group = isset($course_assignments[$course_id]['group_name']) ? (string) $course_assignments[$course_id]['group_name'] : '';
        if (function_exists('sc_resolve_member_course_assignment')) {
            $assignment = sc_resolve_member_course_assignment($course_id, $sel_chapter, $sel_coach, $existing_chapter, $existing_coach_id);
        } else {
            $assignment = [
                'chapter' => $sel_chapter,
                'coach_id' => $sel_coach > 0 ? $sel_coach : absint($existing_coach_id),
            ];
        }
        if (function_exists('sc_resolve_member_course_group')) {
            $assignment['group_name'] = sc_resolve_member_course_group($course_id, $sel_group, $existing_group);
        } else {
            $assignment['group_name'] = sanitize_text_field($sel_group !== '' ? $sel_group : $existing_group);
        }
        return $assignment;
    };

    // مهم: فلگ‌ها مستقل از تیک دوره هستند
    // اول فلگ‌ها را برای همه دوره‌ها (چه تیک خورده چه تیک نخورده) ذخیره می‌کنیم
    if (!empty($course_flags) && is_array($course_flags)) {
        foreach ($course_flags as $course_id => $flags_array) {
            $course_id = absint($course_id);
            if ($course_id) {
                // تبدیل flags به رشته؛ برای خالی از '' استفاده می‌کنیم (wpdb->update مقدار null را در SET نادیده می‌گیرد)
                $flags_string = (!empty($flags_array) && is_array($flags_array))
                    ? implode(',', array_map('sanitize_text_field', $flags_array))
                    : '';

                // بررسی وجود قبلی
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table_name WHERE member_id = %d AND course_id = %d",
                    $member_id,
                    $course_id
                ));

                if ($existing) {
                    $existing_row = $wpdb->get_row($wpdb->prepare(
                        "SELECT coach_id, chapter, group_name FROM $table_name WHERE id = %d LIMIT 1",
                        $existing
                    ));
                    $existing_coach_id = $existing_row ? (int) $existing_row->coach_id : 0;
                    $existing_chapter = $existing_row && isset($existing_row->chapter) ? (string) $existing_row->chapter : '';
                    $existing_group = $existing_row && isset($existing_row->group_name) ? (string) $existing_row->group_name : '';
                    $assignment = $resolve_assignment($course_id, $existing_chapter, $existing_coach_id, $existing_group);

                    // فقط فلگ‌ها را به‌روزرسانی می‌کنیم (status را تغییر نمی‌دهیم)
                    $flag_upd = [
                        'course_status_flags' => $flags_string,
                        'coach_id' => (int) $assignment['coach_id'],
                        'chapter' => $assignment['chapter'] !== '' ? $assignment['chapter'] : null,
                        'updated_at' => current_time('mysql'),
                    ];
                    $flag_fmt = ['%s', '%d', '%s', '%s'];
                    if (function_exists('sc_member_course_row_with_group')) {
                        $flag_upd = array_merge($flag_upd, sc_member_course_row_with_group($assignment));
                        $flag_fmt[] = '%s';
                    }
                    $wpdb->update(
                        $table_name,
                        $flag_upd,
                        ['id' => $existing],
                        $flag_fmt,
                        ['%d']
                    );
                } else {
                    // اگر رکورد وجود ندارد و تیک دوره هم خورده، رکورد جدید ایجاد می‌کنیم
                    if (!empty($course_ids) && in_array($course_id, array_map('absint', $course_ids), true)) {
                        $sf = $resolve_sessions($course_id, $course_package_sessions);
                        $assignment = $resolve_assignment($course_id, '', 0, '');
                        $insert_row = [
                            'member_id' => $member_id,
                            'course_id' => $course_id,
                            'coach_id' => (int) $assignment['coach_id'],
                            'chapter' => $assignment['chapter'] !== '' ? $assignment['chapter'] : null,
                            'enrollment_date' => current_time('Y-m-d'),
                            'status' => 'active',
                            'course_status_flags' => $flags_string,
                            'total_sessions' => (int) $sf['total_sessions'],
                            'remaining_sessions' => (int) $sf['remaining_sessions'],
                            'created_at' => current_time('mysql'),
                            'updated_at' => current_time('mysql'),
                        ];
                        if (function_exists('sc_member_course_row_with_group')) {
                            $insert_row = array_merge($insert_row, sc_member_course_row_with_group($assignment));
                        }
                        $fmt = ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s'];
                        if (function_exists('sc_member_courses_has_group_column') && sc_member_courses_has_group_column()) {
                            $fmt[] = '%s';
                        }
                        if ($sf['enrollment_sessions'] === null) {
                            $insert_row['enrollment_sessions'] = null;
                            $fmt[] = '%s';
                        } else {
                            $insert_row['enrollment_sessions'] = (int) $sf['enrollment_sessions'];
                            $fmt[] = '%d';
                        }
                        $wpdb->insert($table_name, $insert_row, $fmt);
                    }
                }
            }
        }
    }

    // غیرفعال کردن دوره‌هایی که دیگر انتخاب نشده‌اند (فقط status را تغییر می‌دهیم، فلگ‌ها حفظ می‌شوند)
    if (!empty($course_ids) && is_array($course_ids)) {
        $course_ids_safe = array_map('absint', $course_ids);
        $course_ids_imploded = implode(',', $course_ids_safe);
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_name 
             SET status = 'inactive', updated_at = %s 
             WHERE member_id = %d 
             AND course_id NOT IN ($course_ids_imploded)",
            current_time('mysql'),
            $member_id
        ));
    } else {
        // اگر هیچ دوره‌ای انتخاب نشده، همه را inactive کن (فلگ‌ها حفظ می‌شوند)
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_name 
             SET status = 'inactive', updated_at = %s 
             WHERE member_id = %d",
            current_time('mysql'),
            $member_id
        ));
    }

    // افزودن یا به‌روزرسانی دوره‌های جدید (تیک خورده)
    if (!empty($course_ids) && is_array($course_ids)) {
        foreach ($course_ids as $course_id) {
            $course_id = absint($course_id);
            if ($course_id) {
                // دریافت flags از آرایه course_flags (اگر وجود داشته باشد)
                $flags_array = isset($course_flags[$course_id]) && is_array($course_flags[$course_id])
                    ? $course_flags[$course_id]
                    : [];

                // تبدیل flags به رشته (خالی = '')
                $flags_string = !empty($flags_array) ? implode(',', array_map('sanitize_text_field', $flags_array)) : '';

                $sf = $resolve_sessions($course_id, $course_package_sessions);

                // بررسی وجود قبلی
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table_name WHERE member_id = %d AND course_id = %d",
                    $member_id,
                    $course_id
                ));

                if ($existing) {
                    $existing_row = $wpdb->get_row($wpdb->prepare(
                        "SELECT coach_id, chapter, group_name FROM $table_name WHERE id = %d LIMIT 1",
                        $existing
                    ));
                    $existing_coach_id = $existing_row ? (int) $existing_row->coach_id : 0;
                    $existing_chapter = $existing_row && isset($existing_row->chapter) ? (string) $existing_row->chapter : '';
                    $existing_group = $existing_row && isset($existing_row->group_name) ? (string) $existing_row->group_name : '';
                    $assignment = $resolve_assignment($course_id, $existing_chapter, $existing_coach_id, $existing_group);
                    $upd = [
                        'status' => 'active',
                        'coach_id' => (int) $assignment['coach_id'],
                        'chapter' => $assignment['chapter'] !== '' ? $assignment['chapter'] : null,
                        'course_status_flags' => $flags_string,
                        'enrollment_date' => current_time('Y-m-d'),
                        'total_sessions' => (int) $sf['total_sessions'],
                        'remaining_sessions' => (int) $sf['remaining_sessions'],
                        'updated_at' => current_time('mysql'),
                    ];
                    if (function_exists('sc_member_course_row_with_group')) {
                        $upd = array_merge($upd, sc_member_course_row_with_group($assignment));
                    }
                    $fmt = ['%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s'];
                    if (function_exists('sc_member_courses_has_group_column') && sc_member_courses_has_group_column()) {
                        $fmt[] = '%s';
                    }
                    if ($sf['enrollment_sessions'] === null) {
                        $upd['enrollment_sessions'] = null;
                        $fmt[] = '%s';
                    } else {
                        $upd['enrollment_sessions'] = (int) $sf['enrollment_sessions'];
                        $fmt[] = '%d';
                    }
                    $wpdb->update(
                        $table_name,
                        $upd,
                        ['id' => $existing],
                        $fmt,
                        ['%d']
                    );
                } else {
                    $assignment = $resolve_assignment($course_id, '', 0, '');
                    $insert_row = [
                        'member_id' => $member_id,
                        'course_id' => $course_id,
                        'coach_id' => (int) $assignment['coach_id'],
                        'chapter' => $assignment['chapter'] !== '' ? $assignment['chapter'] : null,
                        'enrollment_date' => current_time('Y-m-d'),
                        'status' => 'active',
                        'course_status_flags' => $flags_string,
                        'total_sessions' => (int) $sf['total_sessions'],
                        'remaining_sessions' => (int) $sf['remaining_sessions'],
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql'),
                    ];
                    if (function_exists('sc_member_course_row_with_group')) {
                        $insert_row = array_merge($insert_row, sc_member_course_row_with_group($assignment));
                    }
                    $fmt = ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s'];
                    if (function_exists('sc_member_courses_has_group_column') && sc_member_courses_has_group_column()) {
                        $fmt[] = '%s';
                    }
                    if ($sf['enrollment_sessions'] === null) {
                        $insert_row['enrollment_sessions'] = null;
                        $fmt[] = '%s';
                    } else {
                        $insert_row['enrollment_sessions'] = (int) $sf['enrollment_sessions'];
                        $fmt[] = '%d';
                    }
                    $wpdb->insert($table_name, $insert_row, $fmt);
                }
            }
        }
    }

    if (!empty($course_ids) && is_array($course_ids) && function_exists('sc_maybe_create_initial_invoices_after_member_courses_save')) {
        sc_maybe_create_initial_invoices_after_member_courses_save($member_id, $course_ids);
    }

    $post_counting_courses = [];
    if ($member_id) {
        $post_counting_courses = $wpdb->get_col($wpdb->prepare(
            "SELECT course_id FROM $table_name WHERE member_id = %d AND status = 'active' AND (course_status_flags IS NULL OR TRIM(course_status_flags) = '')",
            $member_id
        ));
    }
    $touch_course_ids = array_unique(array_merge(
        array_map('absint', (array) $pre_counting_courses),
        array_map('absint', (array) $post_counting_courses),
        array_map('absint', (array) $course_ids),
        array_map('absint', array_keys((array) $course_flags))
    ));
    if (function_exists('sc_maybe_notify_course_capacity_waitlist')) {
        foreach ($touch_course_ids as $cid) {
            if ($cid > 0) {
                sc_maybe_notify_course_capacity_waitlist($cid);
            }
        }
    }
}

/**
 * فعال‌سازی یک دوره برای یک عضو (بدون غیرفعال کردن سایر دوره‌های همان عضو).
 * همان منطق جلسات، مربی و صورت‌حساب اولیهٔ «ویرایش بازیکن»؛ اگر رکورد ثبت‌نام نباشد ایجاد می‌شود.
 *
 * @param int   $member_id
 * @param int   $course_id
 * @param array<int,int> $course_package_sessions شناسه دوره => تعداد جلسهٔ پکیج (اختیاری)
 * @return true|\WP_Error
 */
function sc_member_course_activate_one($member_id, $course_id, $course_package_sessions = []) {
    $member_id = absint($member_id);
    $course_id = absint($course_id);
    if ($member_id < 1 || $course_id < 1) {
        return new WP_Error('sc_mc_bad_id', 'شناسه عضو یا دوره نامعتبر است.');
    }

    global $wpdb;
    $members_table = $wpdb->prefix . 'sc_members';
    $table_name = $wpdb->prefix . 'sc_member_courses';
    $courses_table = $wpdb->prefix . 'sc_courses';
    $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
    $coaches_table = $wpdb->prefix . 'sc_coaches';

    $member = $wpdb->get_row($wpdb->prepare("SELECT id, first_name, last_name FROM {$members_table} WHERE id = %d LIMIT 1", $member_id));
    if (!$member) {
        return new WP_Error('sc_mc_no_member', 'بازیکن یافت نشد.');
    }

    $course = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$courses_table} WHERE id = %d AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00') AND is_active = 1 LIMIT 1",
        $course_id
    ));
    if (!$course) {
        return new WP_Error('sc_mc_no_course', 'دوره یافت نشد، حذف شده یا غیرفعال است.');
    }

    $pkg_sel = isset($course_package_sessions[$course_id]) ? absint($course_package_sessions[$course_id]) : 0;
    if ($pkg_sel === 0 && function_exists('sc_course_has_packages') && sc_course_has_packages($course_id)) {
        $pkgs = function_exists('sc_get_course_packages') ? sc_get_course_packages($course_id) : [];
        if (!empty($pkgs[0]->sessions_count)) {
            $pkg_sel = (int) $pkgs[0]->sessions_count;
        }
    }

    if (function_exists('sc_course_has_packages') && sc_course_has_packages($course_id)) {
        if ($pkg_sel < 1 || (function_exists('sc_get_course_package_by_sessions') && !sc_get_course_package_by_sessions($course_id, $pkg_sel))) {
            return new WP_Error('sc_mc_pkg', 'برای این دوره پکیج معتبر انتخاب نشده یا پکیج تعریف نشده است.');
        }
    }

    $sf = function_exists('sc_member_course_session_fields_for_course')
        ? sc_member_course_session_fields_for_course($course_id, $pkg_sel > 0 ? $pkg_sel : null)
        : ['enrollment_sessions' => null, 'total_sessions' => 0, 'remaining_sessions' => 0];

    $sel_chapter = isset($_POST['course_chapter'][$course_id]) ? sanitize_text_field((string) wp_unslash($_POST['course_chapter'][$course_id])) : '';
    $sel_coach = isset($_POST['course_coach'][$course_id]) ? absint($_POST['course_coach'][$course_id]) : 0;
    $sel_group = isset($_POST['course_group'][$course_id]) ? sanitize_text_field((string) wp_unslash($_POST['course_group'][$course_id])) : '';

    $flags_string = '';

    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name WHERE member_id = %d AND course_id = %d",
        $member_id,
        $course_id
    ));

    $apply_group_to_assignment = static function ($course_id, array $assignment, $group_name) {
        if ($group_name === '' || !function_exists('sc_resolve_enrollment_from_course_group')) {
            return $assignment;
        }
        $from_group = sc_resolve_enrollment_from_course_group($course_id, $group_name);
        if ($from_group['chapter'] !== '') {
            $assignment['chapter'] = $from_group['chapter'];
        }
        if ((int) $from_group['coach_id'] > 0) {
            $assignment['coach_id'] = (int) $from_group['coach_id'];
        }
        return $assignment;
    };

    if ($existing) {
        $existing_row = $wpdb->get_row($wpdb->prepare(
            "SELECT coach_id, chapter, group_name FROM $table_name WHERE id = %d LIMIT 1",
            $existing
        ));
        $existing_coach_id = $existing_row ? (int) $existing_row->coach_id : 0;
        $existing_chapter = $existing_row && isset($existing_row->chapter) ? (string) $existing_row->chapter : '';
        $existing_group = $existing_row && isset($existing_row->group_name) ? (string) $existing_row->group_name : '';
        $assignment = function_exists('sc_resolve_member_course_assignment')
            ? sc_resolve_member_course_assignment($course_id, $sel_chapter, $sel_coach, $existing_chapter, $existing_coach_id)
            : ['chapter' => $sel_chapter, 'coach_id' => $sel_coach];
        if (function_exists('sc_resolve_member_course_group')) {
            $assignment['group_name'] = sc_resolve_member_course_group($course_id, $sel_group, $existing_group);
        } else {
            $assignment['group_name'] = sanitize_text_field($sel_group !== '' ? $sel_group : $existing_group);
        }
        $assignment = $apply_group_to_assignment($course_id, $assignment, $assignment['group_name']);
        $upd = [
            'status' => 'active',
            'coach_id' => (int) $assignment['coach_id'],
            'chapter' => $assignment['chapter'] !== '' ? $assignment['chapter'] : null,
            'course_status_flags' => $flags_string,
            'enrollment_date' => current_time('Y-m-d'),
            'total_sessions' => (int) $sf['total_sessions'],
            'remaining_sessions' => (int) $sf['remaining_sessions'],
            'updated_at' => current_time('mysql'),
        ];
        $fmt = ['%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s'];
        if (function_exists('sc_member_course_row_with_group')) {
            $upd = array_merge($upd, sc_member_course_row_with_group($assignment));
            $fmt[] = '%s';
        }
        if ($sf['enrollment_sessions'] === null) {
            $upd['enrollment_sessions'] = null;
            $fmt[] = '%s';
        } else {
            $upd['enrollment_sessions'] = (int) $sf['enrollment_sessions'];
            $fmt[] = '%d';
        }
        $res = $wpdb->update($table_name, $upd, ['id' => (int) $existing], $fmt, ['%d']);
    } else {
        $assignment = function_exists('sc_resolve_member_course_assignment')
            ? sc_resolve_member_course_assignment($course_id, $sel_chapter, $sel_coach, '', 0)
            : ['chapter' => $sel_chapter, 'coach_id' => $sel_coach];
        if (function_exists('sc_resolve_member_course_group')) {
            $assignment['group_name'] = sc_resolve_member_course_group($course_id, $sel_group, '');
        } else {
            $assignment['group_name'] = sanitize_text_field($sel_group);
        }
        $assignment = $apply_group_to_assignment($course_id, $assignment, $assignment['group_name']);
        $insert_row = [
            'member_id' => $member_id,
            'course_id' => $course_id,
            'coach_id' => (int) $assignment['coach_id'],
            'chapter' => $assignment['chapter'] !== '' ? $assignment['chapter'] : null,
            'enrollment_date' => current_time('Y-m-d'),
            'status' => 'active',
            'course_status_flags' => $flags_string,
            'total_sessions' => (int) $sf['total_sessions'],
            'remaining_sessions' => (int) $sf['remaining_sessions'],
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];
        $fmt = ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s'];
        if (function_exists('sc_member_course_row_with_group')) {
            $insert_row = array_merge($insert_row, sc_member_course_row_with_group($assignment));
            $fmt[] = '%s';
        }
        if ($sf['enrollment_sessions'] === null) {
            $insert_row['enrollment_sessions'] = null;
            $fmt[] = '%s';
        } else {
            $insert_row['enrollment_sessions'] = (int) $sf['enrollment_sessions'];
            $fmt[] = '%d';
        }
        $res = $wpdb->insert($table_name, $insert_row, $fmt);
    }

    if ($res === false) {
        return new WP_Error('sc_mc_db', 'خطا در ذخیرهٔ ثبت‌نام دوره: ' . ($wpdb->last_error ?: 'نامشخص'));
    }

    if (function_exists('sc_maybe_create_initial_invoices_after_member_courses_save')) {
        sc_maybe_create_initial_invoices_after_member_courses_save($member_id, [$course_id]);
    }

    return true;
}

//callback display list member in 
function procces_table_data(){
  // بررسی و ایجاد جداول در صورت عدم وجود
  sc_check_and_create_tables();
  
  include SC_TEMPLATES_ADMIN_DIR . 'members-list.php';
  $GLOBALS['player_list_table'] = new Player_List_Table();
  $GLOBALS['player_list_table']->prepare_items();
}
add_action('admin_notices','sc_sprot_notices');
function sc_sprot_notices(){
        $type='';
        $messege='';
        if(isset($_GET['sc_status'])){
        $raw_status = wp_unslash($_GET['sc_status']);
        if (is_array($raw_status)) {
            $raw_status = reset($raw_status);
        }
        $status = sanitize_text_field(is_scalar($raw_status) ? (string) $raw_status : '');
        $raw_status2 = isset($_GET['sc_status2']) ? wp_unslash($_GET['sc_status2']) : '';
        if (is_array($raw_status2)) {
            $raw_status2 = reset($raw_status2);
        }
        $status2 = sanitize_text_field(is_scalar($raw_status2) ? (string) $raw_status2 : '');
        if($status == 'add_true'){
            $type='success';
            $messege="بازیکن با موفقیت اضافه شد";

        }
        if($status == 'add_error'){
            $type='error';
            $messege=" اخطار:  بازیکن اضافه نشد لطفا فیلد های ورودی رو بررسی کنید و دوباره تلاش کنید.";

        }
        if($status == 'validation_error'){
            $type='error';
            $validation_errors = get_transient('sc_member_save_errors_' . get_current_user_id());
            if (is_array($validation_errors) && !empty($validation_errors)) {
                delete_transient('sc_member_save_errors_' . get_current_user_id());
                $messege = implode('<br>', array_map('esc_html', $validation_errors));
            } else {
                $messege = 'لطفاً فیلدهای اجباری را تکمیل کنید.';
            }
        }
        if($status == 'updated'){
            $type='success';
            $messege="اطلاعات بازیکن به درستی بروزرسانی شد.";

        }
        if($status == 'update_error'){
            $type='error';
            $messege="خطا در بروزرسانی اطلاعات بازیکن ";

        }
        if($status == 'deleted'){
            $type='success';
            $messege="بازیکن مورد نظر شما حذف شد";

        }
        if($status == 'delete_error'){
            $type='error';
            $messege="خطا در حذف بازیکن ";

        }
        if($status == 'bulk_deleted'){
            $type='success';
            $messege="رکورد های انتخابی مورد نظر با موفقیت حذف شد";

        }
        // Course messages
        if($status == 'course_add_true'){
            $type='success';
            $messege="دوره با موفقیت اضافه شد";
        }
        if($status == 'course_title_required'){
            $type='error';
            $messege="ثبت دوره انجام نشد: عنوان دوره الزامی است.";
        }
        if($status == 'course_price_required'){
            $type='error';
            $messege="ثبت دوره انجام نشد: برای دوره گروهی، قیمت کل دوره یا حداقل یک پکیج معتبر الزامی است.";
        }
        if($status == 'course_private_price_required'){
            $type='error';
            $messege="ثبت دوره انجام نشد: برای دوره خصوصی، قیمت کل، قیمت هر جلسه یا حداقل یک پکیج معتبر الزامی است.";
        }
        if($status == 'course_add_error'){
            $type='error';
            $messege="ثبت دوره انجام نشد: لطفاً فیلدهای اجباری را بررسی کنید.";
        }
        if($status == 'course_db_error'){
            $type='error';
            $messege="ثبت دوره انجام نشد: خطا در ذخیره‌سازی اطلاعات در پایگاه داده. لطفاً دوباره تلاش کنید.";
        }
        if($status == 'course_pkg_error'){
            $type='error';
            $sc_error = isset($_GET['sc_error']) ? sanitize_text_field(wp_unslash($_GET['sc_error'])) : '';
            if ($sc_error === 'duplicate_sessions') {
                $messege="ثبت دوره انجام نشد: تعداد جلسه در پکیج‌ها یا گزینه‌های جلسه نباید تکراری باشد.";
            } else {
                $messege="ثبت دوره انجام نشد: در پکیج‌های قیمت، تعداد جلسه و قیمت باید هر دو وارد شوند.";
            }
        }
        if($status == 'course_chapter_required'){
            $type='error';
            $messege="ثبت دوره انجام نشد: برای دوره گروهی/خصوصی انتخاب حداقل یک شعبه الزامی است.";
        }
        if($status == 'member_pkg_error'){
            $type='error';
            $messege="برای دوره‌های دارای پکیج، انتخاب پکیج (تعداد جلسه) الزامی است.";
        }
        if($status == 'course_updated'){
            $type='success';
            $messege="اطلاعات دوره به درستی بروزرسانی شد.";
        }
        if($status == 'course_update_error'){
            $type='error';
            $messege="خطا در بروزرسانی اطلاعات دوره";
        }
        if($status == 'course_deleted'){
            $type='success';
            $messege="دوره انتخابی حذف شد.";
        }
        if($status == 'course_restored'){
            $type='success';
            $messege="دوره از زباله‌دان بازیابی شد";
        }
        if($status == 'course_bulk_deleted'){
            $type='success';
            $messege="دوره‌های انتخابی حذف شدند";
        }
        if($status == 'courses_activated'){
            $type='success';
            $messege="دوره‌های انتخابی با موفقیت فعال شدند";
        }
        if($status == 'courses_deactivated'){
            $type='success';
            $messege="دوره‌های انتخابی با موفقیت غیرفعال شدند";
        }
        // Invoice messages
        if($status == 'bulk_status_updated'){
            $type='success';
            $messege="وضعیت صورت حساب‌های انتخابی با موفقیت به‌روزرسانی شد";
        }
        if($status == 'bulk_deleted'){
            $type='success';
            $messege="صورت حساب‌های انتخابی با موفقیت حذف شدند";
        }
        if($status == 'invoice_add_true'){
            $type='success';
            $messege="صورت حساب با موفقیت ایجاد شد";
        }
        if($status == 'invoice_add_error'){
            $type='error';
            $messege="خطا در ایجاد صورت حساب. لطفاً فیلدهای ورودی را بررسی کنید.";
        }
        // if($status == 'invoice_add_team_member'){
        //     $type='error';
        //     $messege="بازیکن تیم صورت‌حساب دریافت نمی‌کند؛ هزینه هر جلسه از کیف پول کسر می‌شود.";
        // }
        if($status == 'pay_card_to_card'){
            $type='success';
            $raw_upd = isset($_GET['updated']) ? wp_unslash($_GET['updated']) : 0;
            if (is_array($raw_upd)) {
                $raw_upd = reset($raw_upd);
            }
            $updated = absint(is_scalar($raw_upd) ? $raw_upd : 0);
            $messege = $updated > 0
                ? sprintf('روش پرداخت «کارت به کارت» برای %d صورت حساب ثبت شد.', $updated)
                : 'روش پرداخت به‌روزرسانی شد.';
        }
        if($status == 'bulk_deleted' && $status2 == 'deleted_player' ){
            $type='success';
            $messege="بازیکن مورد نظر با موفیت حذف شد.";
        }
        if($status == 'bulk_activated' ){
            $type='success';
            $messege="بازیکن مورد نظر با موفقیت فعال شد";
        }
        if($status == 'bulk_deactivated' ){
            $type='success';
            $messege="بازیکن مورد نظر با موفقیت غیرفعال شد.";
        }
        if($status == 'bulk_identity_verified' ){
            $type='success';
            $messege="احراز هویت بازیکنان انتخاب‌شده با موفقیت تایید شد.";
        }
        if($status == 'bulk_deleted_register' ){
            $type='success';
            $messege="فرد مورد نظر با موفقیت حذف شد.";
        }
        if($status == 'expense_add_true' ){
            $type='success';
            $messege="هزینه با موفقیت ثبت شد";
        }
        if($status == 'expense_add_error' ){
            $type='success';
            $messege="خطا در ثبت هزینه - فیلد های ورودی را چک کنید.";
        }
        // Coach messages
        if($status == 'coach_add_true'){
            $type='success';
            $messege="مربی با موفقیت اضافه شد";
        }
        if($status == 'coach_add_error'){
            $type='error';
            $messege="خطا: مربی اضافه نشد لطفا فیلدهای ورودی را بررسی کنید.";
        }
        if($status == 'coaches_activated'){
            $type='success';
            $messege="مربیان انتخاب شده با موفقیت فعال شدند";
        }
        if($status == 'coaches_deactivated'){
            $type='success';
            $messege="مربیان انتخاب شده با موفقیت غیرفعال شدند";
        }
        if($status == 'coaches_deleted'){
            $type='success';
            $messege="مربیان انتخاب شده با موفقیت حذف شدند";
        }
        if($status == 'coach_updated'){
            $type='success';
            $messege="اطلاعات مربی به درستی بروزرسانی شد.";
        }
        if($status == 'coach_deleted'){
            $type='success';
            $messege="مربی مورد نظر حذف شد.";
        }
        if($status == 'coaches_activated'){
            $type='success';
            $messege="مربیان انتخابی با موفقیت فعال شدند";
        }
        if($status == 'coaches_deactivated'){
            $type='success';
            $messege="مربیان انتخابی با موفقیت غیرفعال شدند";
        }
        if($status == 'coaches_deleted'){
            $type='success';
            $messege="مربیان انتخابی حذف شدند";
        }
        
    }
        if($type && $messege){
            ?>
                <div class="notice notice-<?php echo $type; ?> is-dismissible">
                    <p><?php echo $messege; ?></p>
                </div>
            <?php
        }

}
add_action('wp_ajax_get_player_details_courses','get_player_details_courses');
function get_player_details_courses(){

        $id = intval($_POST['id']);
        global $wpdb;
        $member_courses_table = $wpdb->prefix . 'sc_member_courses';
        $courses_table = $wpdb->prefix . 'sc_courses';

        $courses = $wpdb->get_results($wpdb->prepare(
            "SELECT c.title 
            FROM $courses_table c
            INNER JOIN $member_courses_table mc ON c.id = mc.course_id
            WHERE mc.member_id = %d
            AND mc.status = 'active'
            AND (mc.course_status_flags IS NULL 
                    OR (mc.course_status_flags NOT LIKE '%canceled%' 
                        AND mc.course_status_flags NOT LIKE '%paused%' 
                        AND mc.course_status_flags NOT LIKE '%completed%'))
            AND c.deleted_at IS NULL
            LIMIT 30",
            $id
        ));

         if(!$courses) {
        echo "بازیکن یافت نشد.";
        wp_die();
    }
     wp_send_json_success($courses);
    
      
}


add_action('wp_ajax_get_player_details', 'get_player_details');
function get_player_details(){
    $id = intval($_POST['id']);
    global $wpdb;
    $table = $wpdb->prefix . 'sc_members';
    $table = $wpdb->prefix . 'sc_members';
    $player = $wpdb->get_row("SELECT * FROM $table WHERE id=$id", ARRAY_A);

    if(!$player) {
        echo "بازیکن یافت نشد.";
        wp_die();
    }
     wp_send_json_success($player);
    
}

/**
 * Get active course members (AJAX handler)
 */
add_action('wp_ajax_get_course_active_users', 'get_course_active_users');
function get_course_active_users() {
    if (!current_user_can('manage_options') && !current_user_can('club_coach')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
    }

    $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;

    if (!$course_id) {
        wp_send_json_error(['message' => 'شناسه دوره معتبر نیست.']);
    }

    global $wpdb;
    $courses_table = $wpdb->prefix . 'sc_courses';
    $course = $wpdb->get_row($wpdb->prepare(
        "SELECT id, title FROM $courses_table WHERE id = %d LIMIT 1",
        $course_id
    ));

    if (!$course) {
        wp_send_json_error(['message' => 'دوره یافت نشد.']);
    }

    $users = function_exists('sc_get_course_active_member_rows')
        ? sc_get_course_active_member_rows($course_id)
        : [];

    $html = function_exists('sc_render_course_active_users_modal_html')
        ? sc_render_course_active_users_modal_html($course_id, $users, (string) $course->title)
        : '';

    wp_send_json_success([
        'users' => $users,
        'count' => count($users),
        'course_id' => $course_id,
        'course_title' => (string) $course->title,
        'html' => $html,
    ]);
}

/**
 * Process event creation/update form
 */
function callback_add_event_sufix() {
    if (isset($_GET['page']) && $_GET['page'] == 'sc-add-event' && isset($_POST['submit'])) {
        // بررسی nonce
        if (!isset($_POST['sc_event_nonce']) || !wp_verify_nonce($_POST['sc_event_nonce'], 'sc_event_form')) {
            wp_redirect(admin_url('admin.php?page=sc-add-event&sc_status=security_error'));
            exit;
        }

        // بررسی و ایجاد جداول
        sc_check_and_create_tables();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'sc_events';
        
        // Validation
        if (empty($_POST['name']) || (!isset($_POST['is_free_event']) && empty($_POST['price']))) {
            wp_redirect(admin_url('admin.php?page=sc-add-event&sc_status=event_add_error'));
            exit;
        }

        
        // پردازش تاریخ شمسی به میلادی
        $start_date = NULL;
        $start_date_shamsi = NULL;
        if (!empty($_POST['start_date_shamsi'])) {
            $start_date_shamsi = sanitize_text_field($_POST['start_date_shamsi']);
            $start_date = sc_shamsi_to_gregorian_date($start_date_shamsi);
        } elseif (!empty($_POST['start_date'])) {
            $start_date = sanitize_text_field($_POST['start_date']);
        }
        
        $end_date = NULL;
        $end_date_shamsi = NULL;
        if (!empty($_POST['end_date_shamsi'])) {
            $end_date_shamsi = sanitize_text_field($_POST['end_date_shamsi']);
            $end_date = sc_shamsi_to_gregorian_date($end_date_shamsi);
        } elseif (!empty($_POST['end_date'])) {
            $end_date = sanitize_text_field($_POST['end_date']);
        }
        
        // پردازش تاریخ برگزاری
        $holding_date = NULL;
        $holding_date_shamsi = NULL;
        if (!empty($_POST['holding_date_shamsi'])) {
            $holding_date_shamsi = sanitize_text_field($_POST['holding_date_shamsi']);
            $holding_date = sc_shamsi_to_gregorian_date($holding_date_shamsi);
        } elseif (!empty($_POST['holding_date'])) {
            $holding_date = sanitize_text_field($_POST['holding_date']);
        }
        
        $has_age_limit = isset($_POST['has_age_limit']) ? 1 : 0;
        $min_age = ($has_age_limit && !empty($_POST['min_age'])) ? intval($_POST['min_age']) : NULL;
        $max_age = ($has_age_limit && !empty($_POST['max_age'])) ? intval($_POST['max_age']) : NULL;
        
        $event_location_lat = !empty($_POST['event_location_lat']) ? floatval($_POST['event_location_lat']) : NULL;
        $event_location_lng = !empty($_POST['event_location_lng']) ? floatval($_POST['event_location_lng']) : NULL;
        
        // پردازش قیمت از price_raw
        $price_value = 0;
        if (isset($_POST['price_raw']) && !empty($_POST['price_raw']) && $_POST['price_raw'] !== '0') {
            // حذف کاماها و تبدیل به عدد
            $price_raw_cleaned = str_replace(',', '', sanitize_text_field($_POST['price_raw']));
            $price_value = floatval($price_raw_cleaned);
        } elseif (isset($_POST['price']) && !empty($_POST['price'])) {
            // حذف کاماها و تبدیل به عدد
            $price_cleaned = str_replace(',', '', sanitize_text_field($_POST['price']));
            $price_value = floatval($price_cleaned);
        }
        if (isset($_POST['is_free_event']) && $_POST['is_free_event'] == '1') {
    $price_value = 0;
}
        // تبدیل به عدد صحیح (بدون اعشار)
        $price_value = intval($price_value);
        
        // پردازش توضیحات از WYSIWYG editor
        $description_content = '';
        if (isset($_POST['description']) && !empty($_POST['description'])) {
            $description_content = wp_kses_post($_POST['description']);
        }
        
        // پردازش زمان مسابقه از WYSIWYG editor
        $event_time_content = '';
        if (isset($_POST['event_time']) && !empty($_POST['event_time'])) {
            $event_time_content = wp_kses_post($_POST['event_time']);
        }
        
        $data = [
            'name' => sanitize_text_field($_POST['name']),
            'event_type' => !empty($_POST['event_type']) ? sanitize_text_field($_POST['event_type']) : 'event',
            'chapter' => !empty($_POST['chapter']) ? sanitize_text_field($_POST['chapter']) : null,
            'description' => !empty($description_content) ? $description_content : NULL,
            'price' => $price_value,
            'start_date_shamsi' => $start_date_shamsi,
            'start_date_gregorian' => $start_date,
            'end_date_shamsi' => $end_date_shamsi,
            'end_date_gregorian' => $end_date,
            'holding_date_shamsi' => $holding_date_shamsi,
            'holding_date_gregorian' => $holding_date,
            'image' => !empty($_POST['image']) ? esc_url_raw($_POST['image']) : NULL,
            'has_age_limit' => $has_age_limit,
            'min_age' => $min_age,
            'max_age' => $max_age,
            'capacity' => !empty($_POST['capacity']) ? intval($_POST['capacity']) : NULL,
            'event_time' => !empty($event_time_content) ? $event_time_content : NULL,
            'event_location' => !empty($_POST['event_location']) ? sanitize_text_field($_POST['event_location']) : NULL,
            'event_location_address' => !empty($_POST['event_location_address']) ? sanitize_textarea_field($_POST['event_location_address']) : NULL,
            'event_location_lat' => $event_location_lat,
            'event_location_lng' => $event_location_lng,
            'restriction_enabled' => isset($_POST['restriction_enabled']) ? 1 : 0,
            'allowed_teams' => !empty($_POST['allowed_teams']) && is_array($_POST['allowed_teams']) ? wp_json_encode(array_values(array_map('sanitize_text_field', $_POST['allowed_teams'])), JSON_UNESCAPED_UNICODE) : NULL,
            'allowed_levels' => !empty($_POST['allowed_levels']) && is_array($_POST['allowed_levels']) ? wp_json_encode(array_values(array_map('sanitize_text_field', $_POST['allowed_levels'])), JSON_UNESCAPED_UNICODE) : NULL,
            'allowed_gender' => (isset($_POST['allowed_gender']) && in_array($_POST['allowed_gender'], ['male', 'female', 'both'], true)) ? sanitize_text_field($_POST['allowed_gender']) : 'both',
            'is_public' => isset($_POST['is_public']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'updated_at' => current_time('mysql'),
        ];

        $event_id = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;

        // بروزرسانی
        if ($event_id) {
            $old_event = $wpdb->get_row($wpdb->prepare("SELECT name, event_type, price, is_active FROM $table_name WHERE id = %d", $event_id), ARRAY_A);
            $updated = $wpdb->update(
                $table_name,
                $data,
                ['id' => $event_id],
                (function($row){
                    $format = [];
                    foreach ($row as $key => $value) {
                        if ($value === NULL) {
                            $format[] = '%s';
                        } elseif (in_array($key, ['price', 'event_location_lat', 'event_location_lng'], true)) {
                            $format[] = '%f';
                        } elseif (in_array($key, ['has_age_limit', 'min_age', 'max_age', 'capacity', 'restriction_enabled', 'is_public', 'is_active'], true)) {
                            $format[] = '%d';
                        } else {
                            $format[] = '%s';
                        }
                    }
                    return $format;
                })($data),
                ['%d']
            );

            if ($updated !== false) {
                if (function_exists('sc_log_activity') && $old_event) {
                    sc_log_activity('updated', 'event', $event_id, 'رویداد «' . $data['name'] . '» ویرایش شد', $old_event, ['name' => $data['name'], 'event_type' => $data['event_type'], 'price' => $data['price'], 'is_active' => $data['is_active']]);
                }
                // ذخیره/به‌روزرسانی فیلدهای سفارشی
                sc_save_event_fields($event_id, $_POST);
                
                wp_redirect(admin_url('admin.php?page=sc-add-event&sc_status=event_updated&event_id=' . $event_id));
                exit;
            } else {
                wp_redirect(admin_url('admin.php?page=sc-add-event&sc_status=event_update_error&event_id=' . $event_id));
                exit;
            }
        } 
        // اضافه کردن جدید
        else {
            $data['created_at'] = current_time('mysql');
            $inserted = $wpdb->insert(
                $table_name, 
                $data,
                (function($row){
                    $format = [];
                    foreach ($row as $key => $value) {
                        if ($value === NULL) {
                            $format[] = '%s';
                        } elseif (in_array($key, ['price', 'event_location_lat', 'event_location_lng'], true)) {
                            $format[] = '%f';
                        } elseif (in_array($key, ['has_age_limit', 'min_age', 'max_age', 'capacity', 'restriction_enabled', 'is_public', 'is_active'], true)) {
                            $format[] = '%d';
                        } else {
                            $format[] = '%s';
                        }
                    }
                    return $format;
                })($data)
            );

            if ($inserted !== false) {
                $insert_id = $wpdb->insert_id;
                if (function_exists('sc_log_activity')) {
                    sc_log_activity('created', 'event', $insert_id, 'رویداد «' . $data['name'] . '» ایجاد شد', null, ['name' => $data['name'], 'event_type' => $data['event_type'], 'price' => $data['price'], 'is_active' => $data['is_active']]);
                }
                // ذخیره فیلدهای سفارشی
                sc_save_event_fields($insert_id, $_POST);
                
                wp_redirect(admin_url('admin.php?page=sc-add-event&sc_status=event_add_true&event_id=' . $insert_id));
                exit;
            } else {
                wp_redirect(admin_url('admin.php?page=sc-add-event&sc_status=event_add_error'));
                exit;
            }
        }
    }
}

/**
 * Event registrations list page
 */
function sc_admin_event_registrations_list_page() {
    // بررسی و ایجاد جداول در صورت عدم وجود
    sc_check_and_create_tables();
    
    include SC_TEMPLATES_ADMIN_DIR . 'list_event_registrations.php';
}

/**
 * Process event registrations table actions
 */
function process_event_registrations_table_data() {
    // این تابع برای پردازش bulk actions و سایر عملیات جدول استفاده می‌شود
    // در حال حاضر خالی است و بعداً تکمیل خواهد شد
}

/**
 * Ajax handler برای مشاهده اطلاعات ثبت‌نام
 */
add_action('wp_ajax_sc_get_registration_details', 'sc_ajax_get_registration_details');
function sc_ajax_get_registration_details() {
    // جلوگیری از output قبل از JSON
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // بررسی nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_registration_nonce')) {
        wp_send_json_error(['message' => 'خطای امنیتی']);
        wp_die();
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
        wp_die();
    }
    
    $registration_id = isset($_POST['registration_id']) ? absint($_POST['registration_id']) : 0;
    
    if (!$registration_id) {
        wp_send_json_error(['message' => 'شناسه ثبت‌نام معتبر نیست']);
        wp_die();
    }
    
    global $wpdb;
    $event_registrations_table = $wpdb->prefix . 'sc_event_registrations';
    $events_table = $wpdb->prefix . 'sc_events';
    $members_table = $wpdb->prefix . 'sc_members';
    $event_fields_table = $wpdb->prefix . 'sc_event_fields';
    
    $registration = $wpdb->get_row($wpdb->prepare(
        "SELECT r.*, e.name as event_name, m.first_name, m.last_name, m.player_phone
         FROM $event_registrations_table r
         LEFT JOIN $events_table e ON r.event_id = e.id
         LEFT JOIN $members_table m ON r.member_id = m.id
         WHERE r.id = %d",
        $registration_id
    ));
    
    if (!$registration) {
        wp_send_json_error(['message' => 'ثبت‌نام یافت نشد']);
        wp_die();
    }
    
    // دریافت فیلدهای رویداد
    $event_fields = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $event_fields_table WHERE event_id = %d ORDER BY field_order ASC, id ASC",
        $registration->event_id
    ));
    
    // پردازش field_data و files
    $field_data = !empty($registration->field_data) ? json_decode($registration->field_data, true) : [];
    $files = !empty($registration->files) ? json_decode($registration->files, true) : [];
    
    // ساخت HTML
    ob_start();
    $is_guest_registration = isset($registration->registration_source) && $registration->registration_source === 'guest';
    $registration_name = trim(($registration->first_name ?: '') . ' ' . ($registration->last_name ?: ''));
    if ($is_guest_registration) {
        $guest_full_name = trim(($registration->guest_first_name ?: '') . ' ' . ($registration->guest_last_name ?: ''));
        if (!empty($guest_full_name)) {
            $registration_name = $guest_full_name;
        }
    }
    $registration_phone = $is_guest_registration ? ($registration->guest_phone ?: $registration->player_phone) : ($registration->player_phone ?: '-');
    $formatted_date = '-';
    if (!empty($registration->created_at)) {
        $date = new DateTime($registration->created_at);
        $shamsi_date = gregorian_to_jalali(
            (int) $date->format('Y'),
            (int) $date->format('m'),
            (int) $date->format('d')
        );
        $formatted_date = $shamsi_date[0] . '/' .
            str_pad((string) $shamsi_date[1], 2, '0', STR_PAD_LEFT) . '/' .
            str_pad((string) $shamsi_date[2], 2, '0', STR_PAD_LEFT);
    }
    $name_parts = preg_split('/\s+/u', (string) ($registration_name ?: ''));
    $initials = '';
    if (!empty($name_parts[0])) {
        $initials .= mb_substr($name_parts[0], 0, 1);
    }
    if (!empty($name_parts[1])) {
        $initials .= mb_substr($name_parts[1], 0, 1);
    }
    if ($initials === '') {
        $initials = '؟';
    }
    ?>
    <div class="sc-reg-details">
        <div class="sc-reg-details-profile">
            <span class="sc-reg-details-avatar" aria-hidden="true"><?php echo esc_html($initials); ?></span>
            <div class="sc-reg-details-profile-info">
                <h3 class="sc-reg-details-profile-name">
                    <?php echo esc_html($registration_name ?: 'بدون نام'); ?>
                    <?php if ($is_guest_registration) : ?>
                        <span class="sc-badge sc-badge--danger">مهمان</span>
                    <?php endif; ?>
                </h3>
                <div class="sc-reg-details-profile-meta">
                    <?php if (!empty($registration_phone) && $registration_phone !== '-') : ?>
                        <span><?php echo esc_html($registration_phone); ?></span>
                    <?php endif; ?>
                    <span><?php echo esc_html($formatted_date); ?></span>
                </div>
            </div>
        </div>

        <h3 class="sc-reg-details-section-title">اطلاعات ثبت‌نام</h3>
        <div class="sc-reg-details-grid">
            <div class="sc-reg-details-item">
                <span class="sc-reg-details-label">نام رویداد</span>
                <span class="sc-reg-details-value"><?php echo esc_html($registration->event_name ?: '-'); ?></span>
            </div>
            <div class="sc-reg-details-item">
                <span class="sc-reg-details-label">نام کاربر</span>
                <span class="sc-reg-details-value">
                    <?php echo esc_html($registration_name ?: '-'); ?>
                    <?php if ($is_guest_registration) : ?>
                        <span class="sc-badge sc-badge--danger">مهمان</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="sc-reg-details-item">
                <span class="sc-reg-details-label">شماره تماس</span>
                <span class="sc-reg-details-value"><?php echo esc_html($registration_phone); ?></span>
            </div>
            <div class="sc-reg-details-item">
                <span class="sc-reg-details-label">تاریخ ثبت‌نام</span>
                <span class="sc-reg-details-value"><?php echo esc_html($formatted_date); ?></span>
            </div>
        </div>

        <?php if (!empty($event_fields)) : ?>
            <h3 class="sc-reg-details-section-title">اطلاعات تکمیلی</h3>
            <div class="sc-reg-details-grid">
                <?php foreach ($event_fields as $field) :
                    $field_id = $field->id;
                    $field_value = isset($field_data[$field_id]) ? $field_data[$field_id]['value'] : null;
                    $field_files = isset($files[$field_id]) ? $files[$field_id] : [];
                    $is_file_field = ($field->field_type === 'file' && !empty($field_files));
                    ?>
                    <div class="sc-reg-details-item<?php echo $is_file_field ? ' sc-reg-details-item--wide' : ''; ?>">
                        <span class="sc-reg-details-label"><?php echo esc_html($field->field_name); ?></span>
                        <div class="sc-reg-details-value">
                            <?php if ($is_file_field) : ?>
                                <div class="sc-reg-details-files">
                                    <?php foreach ($field_files as $file) : ?>
                                        <div class="sc-reg-details-file">
                                            <?php if (isset($file['type']) && strpos($file['type'], 'image/') === 0) : ?>
                                                <a href="<?php echo esc_url($file['url']); ?>" target="_blank" rel="noopener noreferrer" class="sc-reg-details-file-image">
                                                    <img src="<?php echo esc_url($file['url']); ?>" alt="<?php echo esc_attr($file['name']); ?>">
                                                </a>
                                            <?php endif; ?>
                                            <a href="<?php echo esc_url($file['url']); ?>" target="_blank" rel="noopener noreferrer" download class="sc-reg-details-file-link">
                                                <?php echo esc_html($file['name']); ?>
                                            </a>
                                            <?php if (isset($file['size'])) : ?>
                                                <small class="sc-reg-details-file-size"><?php echo esc_html(size_format($file['size'])); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else : ?>
                                <?php echo esc_html($field_value ? $field_value : '-'); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    $html = ob_get_clean();
    
    wp_send_json_success(['html' => $html]);
    wp_die();
}

/**
 * Ajax handler برای تغییر وضعیت ثبت‌نام
 */
add_action('wp_ajax_sc_change_registration_status', 'sc_ajax_change_registration_status');
function sc_ajax_change_registration_status() {
    // جلوگیری از output قبل از JSON
    if (ob_get_level()) {
        ob_clean();
    }
    
    // بررسی nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sc_change_status_nonce')) {
        wp_send_json_error(['message' => 'خطای امنیتی']);
        wp_die();
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'دسترسی غیرمجاز']);
        wp_die();
    }
    
    $registration_id = isset($_POST['registration_id']) ? absint($_POST['registration_id']) : 0;
    $invoice_id = isset($_POST['invoice_id']) ? absint($_POST['invoice_id']) : 0;

/**
 * AJAX handler for SMS testing
 */
    $new_status = isset($_POST['new_status']) ? sanitize_text_field($_POST['new_status']) : '';
    
    if (!$registration_id || !$invoice_id || empty($new_status)) {
        wp_send_json_error(['message' => 'پارامترهای ورودی معتبر نیست']);
        wp_die();
    }
    
    $allowed_statuses = ['completed', 'cancelled', 'processing', 'pending', 'on-hold'];
    if (!in_array($new_status, $allowed_statuses)) {
        wp_send_json_error(['message' => 'وضعیت معتبر نیست']);
        wp_die();
    }
    
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'sc_invoices';
    
    // به‌روزرسانی وضعیت invoice
    if (in_array($new_status, ['completed', 'processing'])) {
        // اگر وضعیت completed یا processing است، payment_date را تنظیم کن
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE $invoices_table 
             SET status = %s, payment_date = %s, updated_at = %s 
             WHERE id = %d",
            $new_status,
            current_time('mysql'),
            current_time('mysql'),
            $invoice_id
        ));
    } else {
        // برای سایر وضعیت‌ها، payment_date را null کن
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE $invoices_table 
             SET status = %s, payment_date = NULL, updated_at = %s 
             WHERE id = %d",
            $new_status,
            current_time('mysql'),
            $invoice_id
        ));
    }
    
    if ($updated === false) {
        wp_send_json_error(['message' => 'خطا در به‌روزرسانی وضعیت: ' . $wpdb->last_error]);
        wp_die();
    }
    
    // به‌روزرسانی وضعیت WooCommerce order اگر وجود دارد
    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT woocommerce_order_id FROM $invoices_table WHERE id = %d",
        $invoice_id
    ));
    
    if ($invoice && !empty($invoice->woocommerce_order_id) && function_exists('wc_get_order')) {
        $order = wc_get_order($invoice->woocommerce_order_id);
        if ($order) {
            $order->update_status($new_status);
        }
    }
    
    wp_send_json_success(['message' => 'وضعیت با موفقیت تغییر کرد']);
    wp_die();
}

/**
 * Process events table actions
 */
function process_events_table_data() {
    // این تابع برای پردازش bulk actions و سایر عملیات جدول استفاده می‌شود
    // در حال حاضر خالی است و بعداً تکمیل خواهد شد
}

/**
 * Sanitize coach settlement amount from form: only digits, no multiplication.
 * مقدار دستمزد ثابت را فقط از روی ارقام ورودی برمی‌گرداند (بدون ضرب یا تبدیل اضافه).
 */
function sc_sanitize_coach_settlement_amount($raw) {
    if ($raw === '' || $raw === null) {
        return 0.0;
    }
    $digits_only = preg_replace('/\D/', '', (string) $raw);
    return $digits_only === '' ? 0.0 : floatval($digits_only);
}

/**
 * Process coach creation/update form
 */
function callback_add_coach_sufix() {
    if (isset($_GET['page']) && $_GET['page'] == 'sc-add-coach' && isset($_POST['submit_coach'])) {
        if (!isset($_POST['sc_coach_nonce']) || !wp_verify_nonce($_POST['sc_coach_nonce'], 'sc_add_coach')) {
            wp_die('خطای امنیتی. لطفاً دوباره تلاش کنید.');
        }
        
        sc_check_and_create_tables();
        
        global $wpdb;
        $coaches_table = $wpdb->prefix . 'sc_coaches';
        $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';
        
        // اعتبارسنجی
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $national_id = sanitize_text_field($_POST['national_id']);
        $mobile_phone = sanitize_text_field($_POST['mobile_phone']);
        
        if (empty($first_name) || empty($last_name) || empty($national_id) || empty($mobile_phone)) {
            wp_redirect(admin_url('admin.php?page=sc-add-coach&sc_status=coach_add_error'));
            exit;
        }
        
        // بررسی تکراری بودن کد ملی (به جز خود مربی)
        $coach_id = isset($_POST['coach_id']) ? absint($_POST['coach_id']) : 0;
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $coaches_table WHERE national_id = %s AND id != %d",
            $national_id,
            $coach_id
        ));
        
        if ($existing) {
            wp_redirect(admin_url('admin.php?page=sc-add-coach&sc_status=coach_add_error&error=duplicate_national_id'));
            exit;
        }
        
        // آماده‌سازی داده‌ها
        $data = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'national_id' => $national_id,
            'mobile_phone' => $mobile_phone,
            'gender' => !empty($_POST['gender']) ? sanitize_text_field($_POST['gender']) : NULL,
            'specialization' => !empty($_POST['specialization']) ? sanitize_text_field($_POST['specialization']) : NULL,
            'coaching_level' => !empty($_POST['coaching_level']) ? sanitize_text_field($_POST['coaching_level']) : NULL,
            'coaching_experience' => !empty($_POST['coaching_experience']) ? intval($_POST['coaching_experience']) : NULL,
            'sports_history' => !empty($_POST['sports_history']) ? sanitize_textarea_field($_POST['sports_history']) : NULL,
            'personal_photo' => (isset($_POST['personal_photo']) && trim((string) $_POST['personal_photo']) !== '')
                ? esc_url_raw(wp_unslash($_POST['personal_photo']))
                : null,
            'settlement_type' => (function () {
                $allowed = ['fixed', 'percentage', 'both'];
                $raw = !empty($_POST['settlement_type']) ? sanitize_text_field(wp_unslash($_POST['settlement_type'])) : 'fixed';
                return in_array($raw, $allowed, true) ? $raw : 'fixed';
            })(),
            'settlement_amount' => sc_sanitize_coach_settlement_amount(isset($_POST['coach_settlement_amount_save']) ? $_POST['coach_settlement_amount_save'] : ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'is_private_enabled' => isset($_POST['is_private_enabled']) ? 1 : 0,
            'updated_at' => current_time('mysql')
        ];
        
        // مدیریت کاربر WordPress
        $username = !empty($_POST['username']) ? sanitize_user($_POST['username']) : '';
        $password = !empty($_POST['password']) ? $_POST['password'] : '';
        
        if ($coach_id) {
            // ویرایش
            $coach = $wpdb->get_row($wpdb->prepare("SELECT * FROM $coaches_table WHERE id = %d", $coach_id));
            $user_id = $coach->user_id;
            
            if ($user_id) {
                // به‌روزرسانی کاربر موجود
                if (!empty($password)) {
                    wp_set_password($password, $user_id);
                }
                wp_update_user([
                    'ID' => $user_id,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'display_name' => $first_name . ' ' . $last_name
                ]);
                update_user_meta($user_id, 'billing_phone', $mobile_phone);
            } else {
                // ایجاد کاربر جدید
                $user_id = sc_create_coach_wp_user($coach_id, $data, $username, $password);
                if ($user_id) {
                    $data['user_id'] = $user_id;
                }
            }
            
            $updated = $wpdb->update(
                $coaches_table,
                $data,
                ['id' => $coach_id],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%f', '%d', '%d', '%s'],
                ['%d']
            );
            
            if ($updated !== false) {
                if (function_exists('sc_log_activity') && $coach) {
                    sc_log_activity('updated', 'coach', $coach_id, 'مربی «' . $first_name . ' ' . $last_name . '» ویرایش شد', (array) $coach, ['first_name' => $first_name, 'last_name' => $last_name, 'mobile_phone' => $mobile_phone]);
                }
                // به‌روزرسانی دوره‌ها
                $course_percentages = isset($_POST['course_percentage']) && is_array($_POST['course_percentage']) ? $_POST['course_percentage'] : [];
                if (function_exists('sc_save_coach_course_assignments')) {
                    sc_save_coach_course_assignments($coach_id, sc_parse_coach_course_assignments_from_post());
                } else {
                    sc_save_coach_courses($coach_id, isset($_POST['courses']) ? $_POST['courses'] : [], $course_percentages);
                }
                
                wp_redirect(admin_url('admin.php?page=sc-add-coach&sc_status=coach_updated&coach_id=' . $coach_id));
                exit;
            }
        } else {
            // افزودن جدید
            $data['created_at'] = current_time('mysql');
            
            $inserted = $wpdb->insert($coaches_table, $data, ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%f', '%d', '%d', '%s', '%s']);
            
            if ($inserted !== false) {
                $new_coach_id = $wpdb->insert_id;
                if (function_exists('sc_log_activity')) {
                    sc_log_activity('created', 'coach', $new_coach_id, 'مربی «' . $first_name . ' ' . $last_name . '» ایجاد شد', null, ['first_name' => $first_name, 'last_name' => $last_name, 'mobile_phone' => $mobile_phone]);
                }
                // ایجاد کاربر WordPress
                $user_id = sc_create_coach_wp_user($new_coach_id, $data, $username, $password);
                if ($user_id) {
                    $wpdb->update($coaches_table, ['user_id' => $user_id], ['id' => $new_coach_id], ['%d'], ['%d']);
                }
                
                // ذخیره دوره‌ها (شامل درصد هر دوره، مانند ویرایش)
                $course_percentages_new = isset($_POST['course_percentage']) && is_array($_POST['course_percentage']) ? $_POST['course_percentage'] : [];
                if (function_exists('sc_save_coach_course_assignments')) {
                    sc_save_coach_course_assignments($new_coach_id, sc_parse_coach_course_assignments_from_post());
                } else {
                    sc_save_coach_courses($new_coach_id, isset($_POST['courses']) ? $_POST['courses'] : [], $course_percentages_new);
                }
                
                wp_redirect(admin_url('admin.php?page=sc-add-coach&sc_status=coach_add_true&coach_id=' . $new_coach_id));
                exit;
            }
        }
        
        wp_redirect(admin_url('admin.php?page=sc-add-coach&sc_status=coach_add_error'));
        exit;
    }
}

/**
 * Create WordPress user for coach
 */
function sc_create_coach_wp_user($coach_id, $data, $username = '', $password = '') {
    if (empty($username)) {
        $username = sanitize_user($data['national_id'], true);
    }
    
    // بررسی تکراری بودن
    $original_username = $username;
    $counter = 1;
    while (username_exists($username)) {
        $username = $original_username . '_' . $counter;
        $counter++;
    }
    
    if (empty($password)) {
        $password = wp_generate_password(12, false);
    }
    
    $email = sanitize_email($data['mobile_phone'] . '@sportclub.local');
    if (!is_email($email)) {
        $email = 'coach_' . wp_generate_password(8, false) . '@example.local';
    }
    
    $user_id = wp_create_user($username, $password, $email);
    
    if (!is_wp_error($user_id)) {
        $user = new WP_User($user_id);
        // نقش بعداً تعریف می‌شود
        $user->set_role('subscriber');
        
        wp_update_user([
            'ID' => $user_id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'display_name' => $data['first_name'] . ' ' . $data['last_name']
        ]);
        
        update_user_meta($user_id, 'billing_phone', $data['mobile_phone']);
        
        return $user_id;
    }
    
    return false;
}

/**
 * Save coach courses with salary percentage
 */
function sc_save_coach_courses($coach_id, $course_ids, $course_percentages = []) {
    global $wpdb;
    $course_coaches_table = $wpdb->prefix . 'sc_course_coaches';

    $wpdb->delete($course_coaches_table, ['coach_id' => $coach_id], ['%d']);

    if (!empty($course_ids) && is_array($course_ids)) {
        $now = current_time('mysql');
        foreach ($course_ids as $course_id) {
            $course_id = absint($course_id);
            if (!$course_id) {
                continue;
            }

            $chapters = function_exists('sc_get_course_chapters') ? sc_get_course_chapters($course_id) : [];
            if (empty($chapters)) {
                continue;
            }

            $salary_percentage = isset($course_percentages[$course_id]) ? floatval($course_percentages[$course_id]) : 0.00;
            if ($salary_percentage < 0) {
                $salary_percentage = 0;
            }
            if ($salary_percentage > 100) {
                $salary_percentage = 100;
            }

            foreach ($chapters as $chapter_name) {
                $chapter_name = sanitize_text_field((string) $chapter_name);
                if ($chapter_name === '') {
                    continue;
                }

                $wpdb->insert(
                    $course_coaches_table,
                    [
                        'coach_id' => $coach_id,
                        'course_id' => $course_id,
                        'chapter_name' => $chapter_name,
                        'salary_percentage' => $salary_percentage,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    ['%d', '%d', '%s', '%f', '%s', '%s']
                );
            }
        }
    }
}

/**
 * Coach Salary Page (for coaches)
 */
function sc_admin_coach_salary_page() {
    include SC_TEMPLATES_ADMIN_DIR . 'coach-salary-list.php';
}

/**
 * Coach Wallet Page (for coaches)
 */
function sc_admin_coach_wallet_page() {
    include SC_TEMPLATES_ADMIN_DIR . 'coach-wallet.php';
}

/**
 * Coach Withdrawals Page (for coaches)
 */
function sc_admin_coach_withdrawals_page() {
    include SC_TEMPLATES_ADMIN_DIR . 'coach-withdrawals.php';
}

/**
 * Coach Management Page (for admin)
 */
function sc_admin_coach_management_page() {
    include SC_TEMPLATES_ADMIN_DIR . 'coach-management.php';
}

/**
 * Coach Management Wallet Page (for admin)
 */
function sc_admin_coach_management_wallet_page() {
    include SC_TEMPLATES_ADMIN_DIR . 'coach-management-wallet.php';
}

/**
 * Coach Management Salary Report Page (for admin)
 */
function sc_admin_coach_management_salary_page() {
    include SC_TEMPLATES_ADMIN_DIR . 'coach-management-salary.php';
}

/**
 * Coach Management Withdrawals Page (for admin)
 */
function sc_admin_coach_management_withdrawals_page() {
    include SC_TEMPLATES_ADMIN_DIR . 'coach-management-withdrawals.php';
}

/**
 * Add SMS credit info to admin bar (فقط وقتی لایسنس فعال و ماژول پیامک بارگذاری شده)
 */
if (function_exists('sc_is_license_active') && sc_is_license_active()) {
    add_action('admin_bar_menu', 'sc_add_sms_credit_to_admin_bar', 999);
}

function sc_add_sms_credit_to_admin_bar($wp_admin_bar) {
    if (!function_exists('sc_is_license_active') || !sc_is_license_active()) {
        return;
    }
    if (!function_exists('sc_get_sms_credit')) {
        return;
    }
    // Only show for admins
    if ( !current_user_can('manage_options')  || current_user_can('coach') ) {
        return;
    }
    if( !function_exists('sc_is_pro_feature_sms_enabled') || !sc_is_pro_feature_sms_enabled()){
        return;
    }

    // Get SMS credit
    $credit_result = sc_get_sms_credit();

    if ($credit_result['success']) {
        $sms_count = floor($credit_result['credit']);
        $monetary_value = $sms_count * 219;

        $wp_admin_bar->add_node(array(
            'id'    => 'sc-sms-credit',
            'title' => '<span style="background: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px; line-height: 1.2;">اعتبار پنل: <span style="color: #2271b1; font-weight: bold;">📱 ' . $sms_count . ' پیامک</span> <span style="color: #666;">(' . number_format($monetary_value) . ' تومان)</span></span>',
            'meta'  => array(
                'title' => 'اعتبار پیامک'
            )
        ));
    } else {
        $wp_admin_bar->add_node(array(
            'id'    => 'sc-sms-credit',
            'title' => '<span style="background: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px; line-height: 1.2;">اعتبار پنل: <span style="color: #d63638; font-weight: bold;">📱 تنظیم نشده</span></span>',
            'meta'  => array(
                'title' => 'پیامک تنظیم نشده'
            )
        ));
    }
}


// ست کردن جایگاه منو
add_action('admin_menu','add_place_menu');

function add_place_menu(){
    register_nav_menu('main_menu_header' , 'منو اصلی هدر');
    register_nav_menu('maga_menu_product' , 'مگا منو محصولات  ');
}

/**
 * صفحه لیست اطلاعیه‌های عمومی
 */
function sc_admin_public_announcement_list_page() {
    include SC_PLUGIN_DIR . 'templates/admin/public-announcement-list.php';
}

/**
 * صفحه افزودن/ویرایش اطلاعیه عمومی
 */
function sc_admin_public_announcement_add_page() {
    include SC_PLUGIN_DIR . 'templates/admin/public-announcement-add.php';
}





