<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options') && !current_user_can('sc_view_coach_salary')) wp_die('دسترسی غیرمجاز.');
global $wpdb;
$is_coach = !empty($GLOBALS['sc_private_notes_is_coach']);
$list_page = $is_coach ? 'sc-coach-private-notes' : 'sc-private-notes';
$add_page = $is_coach ? 'sc-coach-add-private-note' : 'sc-add-private-note';
$members_table = $wpdb->prefix . 'sc_members';
if ($is_coach && function_exists('sc_support_get_coach_id_by_user_id')) {
    $coach_id = (int) sc_support_get_coach_id_by_user_id(get_current_user_id());
    $allowed_member_ids = $coach_id > 0 ? sc_private_notes_get_member_ids_for_coach($coach_id) : [];
    if (!empty($allowed_member_ids)) {
        $placeholders = implode(',', array_fill(0, count($allowed_member_ids), '%d'));
        $members = $wpdb->get_results($wpdb->prepare(
            "SELECT id, first_name, last_name, national_id FROM $members_table WHERE id IN ($placeholders) ORDER BY last_name ASC, first_name ASC",
            ...$allowed_member_ids
        ));
    } else {
        $members = [];
    }
} else {
    $members = $wpdb->get_results("SELECT id, first_name, last_name, national_id FROM $members_table WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC");
}
$filter_member = isset($_GET['filter_member']) ? absint($_GET['filter_member']) : 0;
$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$filter_date_from_shamsi = isset($_GET['filter_date_from_shamsi']) ? sanitize_text_field(wp_unslash($_GET['filter_date_from_shamsi'])) : '';
$filter_date_to_shamsi = isset($_GET['filter_date_to_shamsi']) ? sanitize_text_field(wp_unslash($_GET['filter_date_to_shamsi'])) : '';
$today_gregorian = current_time('Y-m-d');
$ten_days_before_gregorian = gmdate('Y-m-d', strtotime('-10 days', strtotime($today_gregorian)));
$default_from_display = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($ten_days_before_gregorian) : $ten_days_before_gregorian;
$default_to_display = function_exists('sc_date_shamsi_date_only') ? sc_date_shamsi_date_only($today_gregorian) : $today_gregorian;

$display_date_from_shamsi = $filter_date_from_shamsi !== '' ? $filter_date_from_shamsi : $default_from_display;
$display_date_to_shamsi = $filter_date_to_shamsi !== '' ? $filter_date_to_shamsi : $default_to_display;

$filter_date_from = $filter_date_from_shamsi !== ''
    ? sc_shamsi_to_gregorian_date($filter_date_from_shamsi)
    : $ten_days_before_gregorian;
$filter_date_to = $filter_date_to_shamsi !== ''
    ? sc_shamsi_to_gregorian_date($filter_date_to_shamsi)
    : $today_gregorian;
$result = sc_private_notes_query_admin(['member_id' => $filter_member, 'date_from' => $filter_date_from, 'date_to' => $filter_date_to, 'search' => $search, 'per_page' => 20, 'page' => $page]);
$rows = $result['rows'];
$legacy_rows = [];
if (!$is_coach) {
    $legacy_rows = $wpdb->get_results("SELECT n.*, TRIM(CONCAT(COALESCE(m.first_name,''), ' ', COALESCE(m.last_name,''))) AS member_name FROM {$wpdb->prefix}sc_private_notes n LEFT JOIN {$wpdb->prefix}sc_members m ON m.id = n.member_id ORDER BY n.created_at DESC LIMIT 20");
}
?>
<div class="wrap sc-private-notes-wrap">
    <h1 class="wp-heading-inline">پرونده‌های یادداشت خصوصی</h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $add_page)); ?>" class="page-title-action">افزودن یادداشت</a>
    <hr class="wp-header-end">
</div>
<div class="wrap sc-private-notes-wrap">

    <form method="GET" action="" class="form_fillter_attendance form_fillter_attendance_tab1">
        <input type="hidden" name="page" value="<?php echo esc_attr($list_page); ?>">
        <div class="sc-filter-grid">
            <div class="sc-filter-field">
                <label class="sc-filter-label">کاربر</label>
                <?php
                $selected_member_text = 'همه کاربران';
                if ($filter_member > 0) {
                    foreach ($members as $m) {
                        if ((int) $m->id === $filter_member) {
                            $selected_member_text = trim($m->first_name . ' ' . $m->last_name) . ' - ' . ($m->national_id ?: '-');
                            break;
                        }
                    }
                }
                ?>
                <div class="sc-searchable-dropdown">
                    <input type="hidden" name="filter_member" id="filter_member" value="<?php echo esc_attr($filter_member); ?>">
                    <div class="sc-dropdown-toggle">
                        <span class="sc-dropdown-placeholder" <?php if ($filter_member) echo 'style="display:none"'; ?>>همه کاربران</span>
                        <span class="sc-dropdown-selected" <?php if (!$filter_member) echo 'style="display:none"'; ?>><?php echo esc_html($selected_member_text); ?></span>
                        <span class="sc-dropdown-arrow">▼</span>
                    </div>
                    <div class="sc-dropdown-menu">
                        <div class="sc-dropdown-search"><input type="text" class="sc-search-input" placeholder="جستجوی نام یا کد ملی..."></div>
                        <div class="sc-dropdown-options">
                            <div class="sc-dropdown-option sc-visible" data-value="0" onclick="scSelectMemberFilter(this,'0','همه کاربران')">همه کاربران</div>
                            <?php foreach ($members as $member) : ?>
                                <?php
                                $label = trim((string) $member->first_name . ' ' . (string) $member->last_name);
                                if ($label === '') {
                                    $label = 'کاربر #' . (int) $member->id;
                                }
                                $label = $label . ' - ' . ($member->national_id ?: '-');
                                ?>
                                <div class="sc-dropdown-option sc-visible"
                                     data-value="<?php echo esc_attr($member->id); ?>"
                                     data-search="<?php echo esc_attr(strtolower($label)); ?>"
                                     onclick="scSelectMemberFilter(this,'<?php echo esc_js($member->id); ?>','<?php echo esc_js($label); ?>')"><?php echo esc_html($label); ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sc-filter-field sc-filter-date">
                <label class="sc-filter-label">بازه تاریخ</label>
                <div class="sc-date-range">
                    <input style="width: 50%;" type="text" id="filter_date_from_shamsi" name="filter_date_from_shamsi" class="sc-filter-control persian-date-input sc-no-default-date" value="<?php echo esc_attr($display_date_from_shamsi); ?>" readonly>
                    <span class="sc-date-separator">تا</span>
                    <input style="width: 50%;" type="text" id="filter_date_to_shamsi" name="filter_date_to_shamsi" class="sc-filter-control persian-date-input sc-no-default-date" value="<?php echo esc_attr($display_date_to_shamsi); ?>" readonly>
                    <input type="hidden" name="filter_date_from" id="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>">
                    <input type="hidden" name="filter_date_to" id="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>">
                </div>
            </div>
        </div>
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="جستجو در عنوان و متن..." class="regular-text">

        <p class="submit">
            <input type="submit" class="button button-primary" value="اعمال فیلتر">
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . $list_page)); ?>" class="sc_button" style="padding: 10px;">پاک کردن فیلترها</a>
        </p>
    </form>

    <div class="back_attendance_list">
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th class="column-row">ردیف</th><th>کاربر</th><th>نام پرونده</th><th>آخرین پیام</th><th>تعداد پیام</th><th>آخرین فعالیت</th><th>مشاهده</th></tr></thead>
            <tbody>
                <?php if (empty($rows)) : ?>
                    <tr><td colspan="7">موردی یافت نشد.</td></tr>
                <?php else : ?>
                    <?php $idx = (($result['page'] - 1) * $result['per_page']) + 1; ?>
                    <?php foreach ($rows as $row) : ?>
                        <?php $view_url = add_query_arg(['page' => 'sc-private-notes-view', 'thread_id' => (int) $row->id], admin_url('admin.php')); ?>
                        <tr>
                            <td><?php echo (int) $idx++; ?></td>
                            <td><?php echo esc_html($row->member_name ?: ('کاربر #' . (int) $row->member_id)); ?></td>
                            <td><?php echo esc_html(trim((string) $row->subject) !== '' ? (string) $row->subject : ('پرونده #' . (int) $row->id)); ?></td>
                            <td><?php echo esc_html(wp_trim_words(wp_strip_all_tags((string) $row->last_message_excerpt), 12)); ?></td>
                            <td><?php echo (int) $row->messages_count; ?></td>
                            <td><?php echo esc_html(sc_date_shamsi($row->updated_at, 'Y/m/d H:i')); ?></td>
                            <td><a class="button button-small" href="<?php echo esc_url($view_url); ?>">جزئیات</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($result['total_pages'] > 1) : ?>
        <div class="tablenav bottom sc_paginate" style="margin-top: 20px;">
            <div class="tablenav-pages">
                <?php
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%', admin_url('admin.php')),
                    'format' => '',
                    'prev_text' => '< قبلی ',
                    'next_text' => ' بعدی >',
                    'total' => $result['total_pages'],
                    'current' => $result['page'],
                    'add_args' => [
                        'page' => $list_page,
                        'filter_member' => $filter_member,
                        'filter_date_from_shamsi' => $filter_date_from_shamsi,
                        'filter_date_to_shamsi' => $filter_date_to_shamsi,
                        's' => $search,
                    ],
                ]);
                ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$is_coach) : ?>
    <h2 style="margin-top:24px;">یادداشت‌های قدیمی (Legacy)</h2>
    <table class="wp-list-table widefat striped">
        <thead><tr><th>کاربر</th><th>متن</th><th>تاریخ</th></tr></thead>
        <tbody>
            <?php if (empty($legacy_rows)) : ?>
                <tr><td colspan="3">موردی وجود ندارد.</td></tr>
            <?php else : foreach ($legacy_rows as $legacy) : ?>
                <tr>
                    <td><?php echo esc_html($legacy->member_name ?: ('کاربر #' . (int) $legacy->member_id)); ?></td>
                    <td><?php echo esc_html(wp_trim_words(wp_strip_all_tags((string) $legacy->content), 16)); ?></td>
                    <td><?php echo esc_html(sc_date_shamsi($legacy->created_at, 'Y/m/d H:i')); ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
